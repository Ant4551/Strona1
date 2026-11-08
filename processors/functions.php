<?php
defined('ABSPATH') or die;
if (!function_exists('getProductsJson')) {
    /**
     * Get products json
     * This json used in the payment products buttons
     *
     * @return array[]
     */
    function getProductsJson() {
        $return = json_encode(get_option('_products_json'));
        $return = json_decode(fixImagePaths($return), true);
        $return = prepareProductsJson($return);
        return $return ? $return : array('products' => array ());
    }
}
if (!function_exists('prepareProductsJson')) {
    /**
     * Add products data from cms
     *
     * @param array $data
     *
     * @return array $data
     */
    function prepareProductsJson($data) {
        if ($data) {
            $products = isset($data['products']) ? $data['products'] : array();
            $allCategories = isset($data['categories']) ? $data['categories'] : array();
            foreach ($products as $index => $product) {
                if (!isset($data['products'][$index]['categories'])) {
                    $data['products'][$index]['categories'] = array();
                }
                $data['products'][$index]['categoriesData'] = getNpCategoriesData($data['products'][$index]['categories'], $allCategories);
                $source = isset($_GET['product']) ? 'theme' : NpMultiLanguagesResolver::get_np_route_source('plugin');
                $data['products'][$index]['link'] = NpMultiLanguagesResolver::build_np_route_url('product', $product['id'], $source);
            }
        }
        return $data;
    }
}
if (!function_exists('fixImagePaths')) {
    /**
     * Fix image paths
     *
     * @param string $content Content
     *
     * @return mixed
     */
    function fixImagePaths($content) {
        return str_replace('[[site_path_live]]', get_site_url(), $content);
    }
}
if (!function_exists('getNpCategoriesData')) {
    /**
     * Get CategoriesData
     *
     * @param array $productCategories
     * @param array $allCategories
     *
     * @return array $categoriesData
     */
    function getNpCategoriesData($productCategories, $allCategories) {
        $source = (isset($_GET['np_from']) && $_GET['np_from'] == 'theme') || isset($_GET['product'])
            ? 'theme'
            : NpMultiLanguagesResolver::get_np_route_source('plugin');
        $categoriesData = array();
        if ($productCategories) {
            foreach ($productCategories as $id) {
                $category = array_filter(
                    $allCategories, function ($category) use ($id) {
                        return $category['id'] === $id;
                    }
                );

                if (empty($category)) {
                    continue;
                }
                $category = array_shift($category);
                if ($category) {
                    $categoriesData[] = array(
                        'title' => isset($category['title']) ? $category['title'] : 'Uncategorized',
                        'link'  => NpMultiLanguagesResolver::build_np_products_category_url($id, $source),
                    );
                }
                if (empty($categoriesData)) {
                    $categoriesData[] = array('id' => 0, 'title' => 'Uncategorized');
                }
            }
        }
        return $categoriesData;
    }
}
if (!function_exists('getWpQuery')) {
    /**
     * Get WP_QUERY with posts by category or with all posts
     *
     * @param array $params Params for request
     *
     * @return object|WP_Query $result
     */
    function getWpQuery($params) {
        if (!isset($params['orderby'])) {
            $params['orderby'] = 'date';
        }
        if (!isset($params['order'])) {
            $params['order'] = 'DESC';
        }
        if (!isset($params['count'])) {
            $params['count'] = 999999999;
        }
        if ($params['count'] === '') {
            $post_per_page = -1;
        } else {
            $post_per_page = $params['count'];
        }
        $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
        $control_query = new stdClass();
        $cat_id = 0;
        $tags = '';
        if (preg_match('/^tags:/', $params['source'])) {
            $tags = str_replace('tags:', '', $params['source']);
        } else {
            $params['source'] = html_entity_decode($params['source'], ENT_QUOTES, 'UTF-8');
            $cat_id = getCatIdByType($params['source'], $params['entity_type']);
            if (isset($params['id']) && $params['id']) {
                $cat_id = $params['id'];
            }
            if ($params['source'] && $params['source'] !== 'featured' && $params['source'] !== 'relative' && $cat_id < 1) {
                return $control_query;
            }
        }
        $request_params = array(
            'post_type' => $params['entity_type'],
            'numberposts' => $params['count'],
            'orderby' => $params['orderby'],
            'order' => $params['order'],
            'suppress_filters' => false,
            'paged' => $paged,
        );
        if ($post_per_page && $post_per_page !== '') {
            $request_params['posts_per_page'] = $post_per_page;
        }
        if ($cat_id && $params['entity_type'] == 'post') {
            $request_params['cat'] = $cat_id;
        }
        if ($params['entity_type'] == 'product' && $params['source'] && $params['source'] != 'featured') {
            if ($params['source'] === 'relative') {
                $id = get_the_ID();
                if (!empty($GLOBALS['pluginTemplatesExists'])) {
                    global $original_post;
                    if (isset($original_post->ID)) {
                        $id = $original_post->ID;
                    }
                }
                $current_product = wc_get_product($id);
                if ($current_product) {
                    $categories = $current_product->get_category_ids();
                    $tagsIds = $current_product->get_tag_ids();
                    $request_params['post__not_in'] = [$id]; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
                    $request_params['orderby'] = 'rand';
                    $request_params['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                        'relation' => 'OR',
                        [
                            'taxonomy' => 'product_cat',
                            'field'    => 'id',
                            'terms'    => $categories,
                            'operator' => 'IN',
                        ],
                        [
                            'taxonomy' => 'product_tag',
                            'field'    => 'id',
                            'terms'    => $tagsIds,
                            'operator' => 'IN',
                        ],
                    ];
                }
            } else {
                $request_params['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'name',
                        'terms'    => $params['source'],
                    ),
                );
            }
        }
        if ($params['source'] == 'featured') {
            // The tax query
            $tax_query[] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                'taxonomy' => 'product_visibility',
                'terms'    => $params['source'],
                'field'    => 'name',
                'operator' => 'IN',
            );
            $request_params['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
        }
        if ($tags) {
            $request_params['tag'] = array_map('trim', explode(',', $tags));
        }
        if (isset($params['meta_key']) && $params['meta_key']) {
            $request_params['meta_key'] = $params['meta_key']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        }
        if (isset($params['meta_value']) && $params['meta_value']) {
            $request_params['meta_value'] = $params['meta_value']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        }
        if (isset($params['meta_query']) && $params['meta_query']) {
            $request_params['meta_query'] = $params['meta_query']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        }
        if (isset($params['tax_query']) && $params['tax_query']) {
            $request_params['tax_query'] = $params['tax_query']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
        }
        if (isset($params['productId']) && $params['productId']) {
            $request_params['p'] = $params['productId'];
        }
        $control_query = new WP_Query($request_params);
        return $control_query;
    }
}
if (!function_exists('getCatIdByType')) {
    /**
     * Get id category for post or product
     *
     * @param string $categoryName
     * @param string $type         of control
     *
     * @return int
     */
    function getCatIdByType($categoryName, $type = 'blog') {
        $taxonomy = $categoryName === 'featured' ? 'product_visibility' : 'product_cat';
        $product_cat = get_term_by('name', $categoryName, $taxonomy);
        $product_cat_id = 0;
        if (isset($product_cat->term_id)) {
            $product_cat_id = $product_cat->term_id;
        }
        $cat_id = $type == 'blog' || $type == 'post' ? get_cat_ID($categoryName) : $product_cat_id;
        return $cat_id = $cat_id ? $cat_id : 0;
    }
}
if (!function_exists('getHookOutput')) {
    /**
     * Get result of hook
     *
     * @param string $hook Hook Name.
     *
     * @return string Hook result.
     */
    function getHookOutput( $hook ) {
        if (strpos((string) $hook, 'woocommerce_') === 0) {
            $requires_product_hooks = array(
                'woocommerce_before_single_product',
                'woocommerce_single_product_summary',
                'woocommerce_after_single_product',
            );
            if (in_array((string) $hook, $requires_product_hooks, true)) {
                global $product;
                if (!is_object($product) || !method_exists($product, 'get_id')) {
                    return '';
                }
            }
        }
        ob_start();
        do_action($hook);
        return ob_get_clean();
    }
}

if (!function_exists('np_translate')) {
    /**
     * Light migration translation from woocommerce domain to nicepage domain
     *
     * @param string $text
     * @param string $context
     *
     * @return string|void
     */
    function np_translate($text, $context = '') {
        if (!is_string($text) || empty($text)) {
            return '';
        }

        $has_translation = np_translate_text_with_context($text, $context, 'nicepage') !== $text;
        if ($has_translation) {
            return $context
                ? np_translate_text_with_context($text, $context, 'nicepage')
                : np_translate_text($text, 'nicepage');
        }

        // Fallback WooCommerce
        return $context
            ? np_translate_text_with_context($text, $context, 'woocommerce')
            : np_translate_text($text, 'woocommerce');
    }
}

if (!function_exists('np_translate_text')) {
    /**
     * Safe alternative to __() without text-domain warnings
     *
     * @param string $text
     * @param string $domain
     *
     * @return string
     */
    function np_translate_text($text, $domain = 'default') {
        if (!is_string($text)) {
            return $text;
        }
        $translations = get_translations_for_domain($domain);
        return $translations->translate($text);
    }
}

if (!function_exists('np_translate_text_with_context')) {
    /**
     * Safe alternative to _x() without warnings
     *
     * @param string $text
     * @param string $context
     * @param string $domain
     *
     * @return string
     */
    function np_translate_text_with_context($text, $context, $domain = 'default') {
        if (!is_string($text)) {
            return $text;
        }
        $translations = get_translations_for_domain($domain);
        $key = $context . "\004" . $text;
        return $translations->translate($key);
    }
}

if (!function_exists('is_woocommerce_active')) {
    /**
     * Check active woocommerce plugin
     *
     * @return bool
     */
    function is_woocommerce_active() {
        return class_exists('Woocommerce');
    }
}
// Checkout template functions
if (!function_exists('np_woocommerce_form_field')) {
    /**
     * Outputs a checkout/address form field.
     *
     * @param string $key   Key.
     * @param mixed  $args  Arguments.
     * @param string $value (default: null).
     *
     * @return string
     */
    function np_woocommerce_form_field($key, $args, $value = null)
    {
        $defaults = array(
            'type' => 'text',
            'label' => '',
            'description' => '',
            'placeholder' => '',
            'maxlength' => false,
            'required' => false,
            'autocomplete' => false,
            'id' => $key,
            'class' => array(),
            'label_class' => array(),
            'input_class' => array(),
            'return' => false,
            'options' => array(),
            'custom_attributes' => array(),
            'validate' => array(),
            'default' => '',
            'autofocus' => '',
            'priority' => '',
        );

        $args = wp_parse_args($args, $defaults);
        $args = apply_filters('woocommerce_form_field_args', $args, $key, $value);

        if ($args['required']) {
            $args['class'][] = 'validate-required';
            $required = '&nbsp;<abbr class="required" title="' . esc_attr(np_translate('required')) . '">*</abbr>';
        } else {
            $required = '&nbsp;<span class="optional">(' . esc_html(np_translate('optional')) . ')</span>';
        }

        if (is_string($args['label_class'])) {
            $args['label_class'] = array($args['label_class']);
        }

        if (is_null($value)) {
            $value = $args['default'];
        }

        // Custom attribute handling.
        $custom_attributes = array();
        $args['custom_attributes'] = array_filter((array)$args['custom_attributes'], 'strlen');

        if ($args['maxlength']) {
            $args['custom_attributes']['maxlength'] = absint($args['maxlength']);
        }

        if (!empty($args['autocomplete'])) {
            $args['custom_attributes']['autocomplete'] = $args['autocomplete'];
        }

        if (true === $args['autofocus']) {
            $args['custom_attributes']['autofocus'] = 'autofocus';
        }

        if ($args['description']) {
            $args['custom_attributes']['aria-describedby'] = $args['id'] . '-description';
        }

        if (!empty($args['custom_attributes']) && is_array($args['custom_attributes'])) {
            foreach ($args['custom_attributes'] as $attribute => $attribute_value) {
                $custom_attributes[] = esc_attr($attribute) . '="' . esc_attr($attribute_value) . '"';
            }
        }

        if (!empty($args['validate'])) {
            foreach ($args['validate'] as $validate) {
                $args['class'][] = 'validate-' . $validate;
            }
        }

        $field = '';
        $label_id = $args['id'];
        $sort = $args['priority'] ? $args['priority'] : '';
        $field_container = '<div class="form-row %1$s" id="%2$s" data-priority="' . esc_attr($sort) . '">%3$s</div>';

        switch ($args['type']) {
        case 'country':
            $countries = 'shipping_country' === $key ? WC()->countries->get_shipping_countries() : WC()->countries->get_allowed_countries();

            if (1 === count($countries)) {

                $field .= '<strong>' . current(array_values($countries)) . '</strong>';

                $field .= '<input type="hidden" name="' . esc_attr($key) . '" id="' . esc_attr($args['id']) . '" value="' . current(array_keys($countries)) . '" ' . implode(' ', $custom_attributes) . ' class="country_to_state" readonly="readonly" />';

            } else {
                $data_label = !empty($args['label']) ? 'data-label="' . esc_attr($args['label']) . '"' : '';

                $field = '<select name="' . esc_attr($key) . '" id="' . esc_attr($args['id']) . '" class="country_to_state country_select ' . esc_attr(implode(' ', $args['input_class'])) . '" ' . implode(' ', $custom_attributes) . ' data-placeholder="' . esc_attr($args['placeholder'] ? $args['placeholder'] : np_translate('Select a country / region&hellip;')) . '" ' . $data_label . '><option value="">' . esc_html(np_translate('Select a country / region&hellip;')) . '</option>';

                foreach ($countries as $ckey => $cvalue) {
                    $field .= '<option value="' . esc_attr($ckey) . '" ' . selected($value, $ckey, false) . '>' . esc_html($cvalue) . '</option>';
                }

                $field .= '</select>';

                $field .= '<noscript><button type="submit" name="woocommerce_checkout_update_totals" value="' . esc_attr(np_translate('Update country / region')) . '">' . esc_html(np_translate('Update country / region')) . '</button></noscript>';
            }

            break;
        case 'state':
            /* Get country this state field is representing */
            $for_country = isset($args['country']) ? $args['country'] : WC()->checkout->get_value('billing_state' === $key ? 'billing_country' : 'shipping_country');
            $states = WC()->countries->get_states($for_country);

            if (is_array($states) && empty($states)) {

                $field_container = '<p class="form-row %1$s" id="%2$s" style="display: none">%3$s</p>';

                $field .= '<input type="hidden" class="hidden" name="' . esc_attr($key) . '" id="' . esc_attr($args['id']) . '" value="" ' . implode(' ', $custom_attributes) . ' placeholder="' . esc_attr($args['placeholder']) . '" readonly="readonly" data-input-classes="' . esc_attr(implode(' ', $args['input_class'])) . '"/>';

            } elseif (!is_null($for_country) && is_array($states)) {
                $data_label = !empty($args['label']) ? 'data-label="' . esc_attr($args['label']) . '"' : '';

                $field .= '<select name="' . esc_attr($key) . '" id="' . esc_attr($args['id']) . '" class="state_select ' . esc_attr(implode(' ', $args['input_class'])) . '" ' . implode(' ', $custom_attributes) . ' data-placeholder="' . esc_attr($args['placeholder'] ? $args['placeholder'] : esc_html(np_translate('Select an option&hellip;'))) . '"  data-input-classes="' . esc_attr(implode(' ', $args['input_class'])) . '" ' . $data_label . '>
                <option value="">' . esc_html(np_translate('Select an option&hellip;')) . '</option>';

                foreach ($states as $ckey => $cvalue) {
                    $field .= '<option value="' . esc_attr($ckey) . '" ' . selected($value, $ckey, false) . '>' . esc_html($cvalue) . '</option>';
                }

                $field .= '</select>';

            } else {

                $field .= '<input type="text" class="input-text ' . esc_attr(implode(' ', $args['input_class'])) . '" value="' . esc_attr($value) . '"  placeholder="' . esc_attr($args['placeholder']) . '" name="' . esc_attr($key) . '" id="' . esc_attr($args['id']) . '" ' . implode(' ', $custom_attributes) . ' data-input-classes="' . esc_attr(implode(' ', $args['input_class'])) . '"/>';

            }

            break;
        case 'textarea':
            $field .= '<textarea name="' . esc_attr($key) . '" class="input-text ' . esc_attr(implode(' ', $args['input_class'])) . '" id="' . esc_attr($args['id']) . '" placeholder="' . esc_attr($args['placeholder']) . '" ' . (empty($args['custom_attributes']['rows']) ? ' rows="2"' : '') . (empty($args['custom_attributes']['cols']) ? ' cols="5"' : '') . implode(' ', $custom_attributes) . '>' . esc_textarea($value) . '</textarea>';

            break;
        case 'checkbox':
            $field = '<label class="checkbox ' . implode(' ', $args['label_class']) . '" ' . implode(' ', $custom_attributes) . '>
                    <input type="' . esc_attr($args['type']) . '" class="input-checkbox ' . esc_attr(implode(' ', str_replace('u-input ', '', $args['input_class']))) . '" name="' . esc_attr($key) . '" id="' . esc_attr($args['id']) . '" value="1" ' . checked($value, 1, false) . ' /> ' . $args['label'] . $required . '</label>';

            break;
        case 'text':
        case 'password':
        case 'datetime':
        case 'datetime-local':
        case 'date':
        case 'month':
        case 'time':
        case 'week':
        case 'number':
        case 'email':
        case 'url':
        case 'tel':
            $field .= '<input type="' . esc_attr($args['type']) . '" class="input-text ' . esc_attr(implode(' ', $args['input_class'])) . '" name="' . esc_attr($key) . '" id="' . esc_attr($args['id']) . '" placeholder="' . esc_attr($args['placeholder']) . '"  value="' . esc_attr($value) . '" ' . implode(' ', $custom_attributes) . ' />';

            break;
        case 'hidden':
            $field .= '<input type="' . esc_attr($args['type']) . '" class="input-hidden ' . esc_attr(implode(' ', $args['input_class'])) . '" name="' . esc_attr($key) . '" id="' . esc_attr($args['id']) . '" value="' . esc_attr($value) . '" ' . implode(' ', $custom_attributes) . ' />';

            break;
        case 'select':
            $field = '';
            $options = '';

            if (!empty($args['options'])) {
                foreach ($args['options'] as $option_key => $option_text) {
                    if ('' === $option_key) {
                        // If we have a blank option, select2 needs a placeholder.
                        if (empty($args['placeholder'])) {
                            $args['placeholder'] = $option_text ? $option_text : np_translate('Choose an option');
                        }
                        $custom_attributes[] = 'data-allow_clear="true"';
                    }
                    $options .= '<option value="' . esc_attr($option_key) . '" ' . selected($value, $option_key, false) . '>' . esc_html($option_text) . '</option>';
                }

                $field .= '<select name="' . esc_attr($key) . '" id="' . esc_attr($args['id']) . '" class="select ' . esc_attr(implode(' ', $args['input_class'])) . '" ' . implode(' ', $custom_attributes) . ' data-placeholder="' . esc_attr($args['placeholder']) . '">
                        ' . $options . '
                    </select>';
            }

            break;
        case 'radio':
            $label_id .= '_' . current(array_keys($args['options']));

            if (!empty($args['options'])) {
                foreach ($args['options'] as $option_key => $option_text) {
                    $field .= '<input type="radio" class="input-radio ' . esc_attr(implode(' ', $args['input_class'])) . '" value="' . esc_attr($option_key) . '" name="' . esc_attr($key) . '" ' . implode(' ', $custom_attributes) . ' id="' . esc_attr($args['id']) . '_' . esc_attr($option_key) . '"' . checked($value, $option_key, false) . ' />';
                    $field .= '<label for="' . esc_attr($args['id']) . '_' . esc_attr($option_key) . '" class="radio ' . implode(' ', $args['label_class']) . '">' . esc_html($option_text) . '</label>';
                }
            }

            break;
        }

        if (!empty($field)) {
            $field_html = '';

            if ($args['label'] && 'checkbox' !== $args['type']) {
                $field_html .= '<label for="' . esc_attr($label_id) . '" class="' . esc_attr(implode(' ', $args['label_class'])) . '">' . wp_kses_post($args['label']) . $required . '</label>';
            }

            $field_html .= '<span class="woocommerce-input-wrapper">' . $field;

            if ($args['description']) {
                $field_html .= '<span class="description" id="' . esc_attr($args['id']) . '-description" aria-hidden="true">' . wp_kses_post($args['description']) . '</span>';
            }

            $field_html .= '</span>';

            $container_class = esc_attr(implode(' ', $args['class']));
            $container_id = esc_attr($args['id']) . '_field';
            $field = sprintf($field_container, $container_class, $container_id, $field_html);
        }

        /**
         * Filter by type.
         */
        $field = apply_filters('woocommerce_form_field_' . $args['type'], $field, $key, $args, $value);

        /**
         * General filter on form fields.
         *
         * @since 3.4.0
         */
        $field = apply_filters('woocommerce_form_field', $field, $key, $args, $value);

        if ($args['return']) {
            return $field;
        } else {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $field;
        }
    }
}

if (!function_exists('np_get_translation_term')) {
    /**
     * Get translation for category term
     *
     * @param WP_Term $term
     *
     * @return string
     */
    function np_get_translation_term($term)
    {
        if (!is_object($term) || !isset($term->term_id)) {
            return '';
        }
        if (class_exists('NpMultiLanguages') && method_exists('NpMultiLanguages', 'get_term_name_by_lang')) {
            return NpMultiLanguages::get_term_name_by_lang($term);
        }
        if (class_exists('ThemeMultiLanguages') && method_exists('ThemeMultiLanguages', 'get_term_name_by_lang')) {
            return ThemeMultiLanguages::get_term_name_by_lang($term);
        }
        return $term->name;
    }
}

if (!function_exists('np_get_grid_hash_key')) {
    /**
     * Returns a unique key for the grid depending on the page/template type.
     *
     * @return string
     */
    function np_get_grid_hash_key() {
        $grid_type = '';
        if (class_exists('Woocommerce') && (is_shop() || is_product_category() || is_product_tag() || is_post_type_archive('product'))) {
            $grid_type = 'products';
        } elseif (class_exists('Woocommerce') && is_singular('product')) {
            $grid_type = 'product-' . get_queried_object_id();
        } elseif (is_home() || is_archive()) {
            $grid_type = 'blog';
        } elseif (is_singular('post')) {
            $grid_type = 'post-' . get_queried_object_id();
        } elseif (is_page()) {
            $grid_type = 'page-' . get_queried_object_id();
        } elseif (is_search()) {
            $grid_type = 'search';
        }
        if ($grid_type) {
            return 'data-np-grid="' . esc_attr($grid_type) . '"';
        }
        return '';
    }
}
