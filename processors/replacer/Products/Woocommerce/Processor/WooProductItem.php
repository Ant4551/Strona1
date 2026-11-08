<?php

class WooProductItem extends ProductItem {
    protected $_options;
    public $productData;

    /**
     * @param array $options Options
     */
    protected function __construct( $options ) {
        parent::__construct($options);
        $this->_options = $options;
    }

    /**
     * Process product item
     *
     * @param string $content  Page content
     * @param array  $products Site products
     *
     * @return string|string[]|null
     */
    protected function _processProductItem($content, $products) {
        $this->products = $products;
        $reProductItem = '/<\!--product_item-->([\s\S]+?)<\!--\/product_item-->/';
        preg_match_all($reProductItem, $content, $matches, PREG_SET_ORDER);
        $allTemplates = count($matches);
        if ($allTemplates > 0) {
            $allProductsHtml = '';
            global $products_control_query;
            if (get_option('np_theme_appearance') === 'plugin-option' && function_exists('wc_get_product') && (is_shop() || is_product_category())) {
                global $wp_query;
                $products_control_query = $wp_query;
            }
            if (($products_control_query && method_exists($products_control_query, 'have_posts')) || $products) {
                if (count($products) < 1) {
                    return ''; // remove cell, if products is missing
                }
                $productsCount = isset($this->_options['count']) ? (int) $this->_options['count'] : '';
                if ($productsCount && count($products) > $productsCount) {
                    $products = array_slice($products, 0, $productsCount);
                }

                $i = 0;
                while(count($products) > 0) :
                    $tmplIndex = $i % $allTemplates;
                    $productItemHtml = $matches[$tmplIndex][1];
                    $products_control_query->the_post();
                    $product = array_shift($products);
                    $this->productData = np_data_product($product->ID);
                    if (count($this->productData) > 0) {
                        $allProductsHtml .= $this->_replaceProductItemControls($productItemHtml);
                    }
                    $i++;
                endwhile;
            }
        }
        $content = preg_replace('/<!--product_item-->([\s\S]+)<!--\/product_item-->/', $allProductsHtml, $content);
        $content = BaseController::processPagination($content, 'products', $this->_options['siteProductsProcess']);
        $content = BaseController::processCategoriesFilter($content, $this->_options, 'products');
        $content = $this->processSorting($content);
        $content = $this->_buildGridAutoRows($products, $this->_options, $content);
        return $content;
    }

    /**
     * Process sorting for products controls
     *
     * @param string $content
     *
     * @return string $content
     */
    public function processSorting($content) {
        $content = preg_replace_callback(
            '/<\!--products_sorting-->([\s\S]+?)<\!--\/products_sorting-->/',
            function ($sortingMatch) {
                $sortingHtml = $sortingMatch[1];
                $sortingHtml = str_replace('<select', '<select aria-label="Sorting items by"', $sortingHtml);
                if (get_option('np_theme_appearance') === 'plugin-option' && function_exists('wc_get_product') && (is_shop() || is_product_category())) {
                    $pathMode = class_exists('NpPath') && NpPath::use_path_language_urls();
                    $formAttributes = ' method="get"';
                    if ($pathMode) {
                        $actionUrl = '';
                        if (is_product_category()) {
                            $term = get_queried_object();
                            if ($term instanceof WP_Term) {
                                $actionUrl = get_term_link($term);
                            }
                        } elseif (function_exists('wc_get_page_id')) {
                            $actionUrl = get_permalink(wc_get_page_id('shop'));
                        }
                        if (is_wp_error($actionUrl)) {
                            $actionUrl = '';
                        }
                        if ($actionUrl) {
                            $language = class_exists('NpMultiLanguagesResolver') ? NpMultiLanguagesResolver::get_current_language() : '';
                            if ($language && class_exists('NpMultiLanguages')) {
                                $actionUrl = NpMultiLanguages::build_language_url($actionUrl, $language);
                            } elseif ($language && class_exists('ThemeMultiLanguages')) {
                                $actionUrl = ThemeMultiLanguages::build_language_url($actionUrl, $language);
                            }
                            $formAttributes .= ' action="' . esc_url($actionUrl) . '"';
                        }
                    }
                    $hiddenInputs = $pathMode ? '' : '<input type="hidden" name="paged" value="1"><input type="hidden" name="post_type" value="product">';
                    $sortingHtml = str_replace('u-select-sorting', 'u-select-sorting orderby', $sortingHtml);
                    $sortingHtml = str_replace('u-sorting"', 'u-sorting woocommerce-ordering"', $sortingHtml);
                    $sortingHtml = str_replace('<select', '<form' . $formAttributes . '><select', $sortingHtml);
                    $sortingHtml = str_replace('</select>', '</select>' . $hiddenInputs . '</form>', $sortingHtml);
                    $sortingHtml = str_replace('name="sorting"', 'name="orderby"', $sortingHtml);
                }
                preg_match('/<option[\s\S]*?>[\s\S]+?<\/option>/', $sortingHtml, $sortingOptions);
                $firstOptionHtml = $sortingOptions[0];
                $sortingHtml = preg_replace('/<option[\s\S]*?>[\s\S]*<\/option>/', '{sortingOptions}', $sortingHtml);
                $sorting_options = array(
                    'default'    => np_translate('Default sorting'),
                    'popularity' => np_translate('Sort by popularity'),
                    'rating'     => np_translate('Sort by average rating'),
                    'date'       => np_translate('Sort by latest'),
                    'price'      => np_translate('Sort by price: low to high'),
                    'price-desc' => np_translate('Sort by price: high to low'),
                );
                $sortingOptionsHtml = '';
                $activeSorting = isset($_GET['sorting']) ? $_GET['sorting'] : false;
                if (get_option('np_theme_appearance') === 'plugin-option' && function_exists('wc_get_product') && (is_shop() || is_product_category())) {
                    $activeSorting = isset($_GET['orderby']) ? $_GET['orderby'] : false;
                }
                foreach ($sorting_options as $name => $sorting_option) {
                    $doubleOptionHtml = $firstOptionHtml;
                    $doubleOptionHtml = preg_replace('/value=[\'"][\s\S]*?[\'"]/', 'value="' . $name . '"', $doubleOptionHtml);
                    $doubleOptionHtml = preg_replace('/(<option[\s\S]*?>)[\s\S]+?<\/option>/', '$1' . $sorting_option . '</option>', $doubleOptionHtml);
                    if ($activeSorting && $name === $activeSorting) {
                        $doubleOptionHtml = str_replace('<option', '<option selected="selected"', $doubleOptionHtml);
                    }
                    $sortingOptionsHtml .= $doubleOptionHtml;
                }
                $sortingHtml = str_replace('{sortingOptions}', $sortingOptionsHtml, $sortingHtml);
                return $sortingHtml;
            },
            $content
        );
        return $content;
    }

    /**
     * Replace placeholder for product item controls
     *
     * @param string $content
     *
     * @return string $content
     */
    protected function _replaceProductItemControls($content) {
        $product = $this->productData['product'];
        if (!$product) {
            return $content;
        }
        $content = $this->_replaceHooks($content);
        $content = $this->_replaceTitle($content);
        $content = $this->_replaceFullDesc($content);
        $content = $this->_replaceShortDesc($content);
        $content = $this->_replaceImage($content);
        $content = $this->_replaceButton($content);
        $content = $this->_replacePrice($content);
        $content = $this->_replaceGallery($content);
        $content = $this->_replaceVariations($content);
        $content = $this->_replaceTabs($content);
        $content = $this->_replaceQuantity($content);
        $content = $this->_replaceCategory($content);
        $content = $this->_setProductBadge($content);
        $content = $this->_replaceOutOfStock($content);
        $content = $this->_replaceSku($content);
        if ($this->_options['productName'] === 'product-details') {
            $content = $this->_addProductSchema($content);
        }
        return $content;
    }

    /**
     * Replace placeholder for woo hooks
     *
     * @param string $content
     *
     * @return string $content
     */
    protected function _replaceHooks($content) {
        $content = str_replace('<!-- {{woocommerce_before_shop_loop_item}} -->', getHookOutput('woocommerce_before_shop_loop_item'), $content);
        return str_replace('<!-- {{woocommerce_after_shop_loop_item}} -->', getHookOutput('woocommerce_after_shop_loop_item'), $content);
    }

    /**
     * Replace placeholder for product title
     *
     * @param string $content
     *
     * @return string $content
     */
    protected function _replaceTitle($content) {
        return preg_replace_callback(
            '/<!--product_title-->([\s\S]+?)<!--\/product_title-->/',
            function ($titleMatch) {
                $titleHtml = $titleMatch[1];
                $titleHtml = $this->_replaceTitleUrl($titleHtml);
                $titleHtml = $this->_replaceTitleContent($titleHtml);
                return $titleHtml;
            },
            $content
        );
    }

    /**
     * Replace placeholder for product title content
     *
     * @param string $content title html
     *
     * @return string $content
     */
    private function _replaceTitleContent($content) {
        $productTitle = $this->productData['title'];
        $productTitle = $productTitle ? $productTitle : $this->productData['product']->post_title;
        if (isset($productTitle) && $productTitle != '') {
            $content = preg_replace_callback(
                '/(<a\b[^>]*>).*?(<\/a>)/s', function ($matches) use ($productTitle) {
                    return $matches[1] . $productTitle . $matches[2];
                }, $content
            );
        }
        return $content;
    }

    /**
     * Replace placeholder for product title url
     *
     * @param string $content title html
     *
     * @return string $content
     */
    protected function _replaceTitleUrl($content) {
        $postUrl = get_permalink($this->productData['product']->get_id());
        $postUrl = $postUrl ? $postUrl : '#';
        if ($postUrl) {
            $content = preg_replace('/href=[\'|"][\s\S]+?[\'|"]/', 'href="' . $postUrl . '"', $content);
        }
        return $content;
    }

    /**
     * Replace placeholder for product description
     *
     * @param string $content
     *
     * @return string $content
     */
    protected function _replaceFullDesc($content) {
        return preg_replace_callback(
            '/<!--product_description-->([\s\S]+?)<!--\/product_description-->/',
            function ($textMatch) {
                $product = $this->productData['product'];
                $textHtml = $textMatch[1];
                $productContent = $this->productData['fullDesc'];
                if (isset($productContent)) {
                    $stock_html = wc_get_stock_html($product);
                    if ($stock_html && $stock_html !== '') {
                        $productContent .= '</br>' . $stock_html;
                    }
                    $textHtml = preg_replace('/<!--product_description_content-->([\s\S]+?)<!--\/product_description_content-->/s', $productContent, $textHtml);
                }
                return $textHtml;
            },
            $content
        );
    }

    /**
     * Replace placeholder for product short description
     *
     * @param string $content
     *
     * @return string $content
     */
    private function _replaceShortDesc($content) {
        return preg_replace_callback(
            '/<!--product_content-->([\s\S]+?)<!--\/product_content-->/',
            function ($textMatch) {
                $product = $this->productData['product'];
                $textHtml = $textMatch[1];
                $productContent = $this->productData['shortDesc'];
                if (isset($productContent)) {
                    $stock_html = wc_get_stock_html($product);
                    if ($stock_html && $stock_html !== '') {
                        $productContent .= '</br>' . $stock_html;
                    }
                    $textHtml = preg_replace('/<!--product_content_content-->([\s\S]+?)<!--\/product_content_content-->/s', $productContent, $textHtml);
                }
                return $textHtml;
            },
            $content
        );
    }

    /**
     * Replace placeholder for product image
     *
     * @param string $content
     *
     * @return string $content
     */
    protected function _replaceImage($content) {
        return preg_replace_callback(
            '/<!--product_image-->([\s\S]+?)<!--\/product_image-->/',
            function ($imageMatch) {
                $imageHtml = $imageMatch[1];
                $url = $this->productData['image_url'];
                if (!$url) {
                    return '<div class="none-post-image" style="display: none;"></div>';
                }
                $isBackgroundImage = strpos($imageHtml, '<div') !== false ? true : false;
                $link = get_permalink($this->productData['product']->get_id());
                if ($isBackgroundImage) {
                    $imageHtml = str_replace('<div', '<div data-product-control="' . $link . '"', $imageHtml);
                    if (strpos($imageHtml, 'data-bg') !== false) {
                        $imageHtml = preg_replace('/(data-bg=(["\']))(.*?)(\2)/', '$1url(' . $url . ')$4', $imageHtml);
                    } else {
                        $imageHtml = str_replace('<div', '<div' . ' style="background-image:url(' . $url . ')"', $imageHtml);
                    }
                } else {
                    if (strpos($imageHtml, 'data-product-control') !== false) {
                        $imageHtml = preg_replace('/data-product-control="[\s\S]*?"/', '<div data-product-control="' . $link . '"', $imageHtml);
                        $imageHtml = preg_replace('/(src=[\'"])([\s\S]+?)([\'"])/', '$1' . $url . '$3 style="cursor:pointer;" ', $imageHtml);
                    } else {
                        $imageHtml = preg_replace('/(src=[\'"])([\s\S]+?)([\'"])/', '$1' . $url . '$3 style="cursor:pointer;" data-product-control="' . $link . '"', $imageHtml);
                    }
                }
                if ($this->_options['showSecondImage']) {
                    if (isset($this->productData['second_image_url'])) {
                        $url = $this->productData['second_image_url'];
                        if ($isBackgroundImage) {
                            // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
                            $secondImageHtml = '<img class="u-product-second-image" src="' . $url . '"/>';
                        } else {
                            $secondImageHtml = preg_replace('/(class=[\'"])([\s\S]+?)([\'"])/', '$1u-product-second-image$3', $imageHtml);
                            $secondImageHtml = preg_replace('/(src=[\'"])([\s\S]+?)([\'"])/', '$1' . $url . '$3 style="cursor:pointer;" data-product-control="' . $link . '"', $secondImageHtml);
                        }
                        $imageHtml = $imageHtml . $secondImageHtml;
                    }
                }
                return $imageHtml;
            },
            $content
        );
    }

    /**
     * Replace placeholder for product button add to cart
     *
     * @param string $content
     *
     * @return string $content
     */
    protected function _replaceButton($content) {
        return preg_replace_callback(
            '/<!--product_button-->([\s\S]+?)<!--\/product_button-->/',
            function ($buttonMatch) {
                global $product;
                $product = $this->productData['product'];
                $button_html = $buttonMatch[1];
                $controlOptions = array();
                if (preg_match('/<\!--options_json--><\!--([\s\S]+?)--><\!--\/options_json-->/', $button_html, $matches)) {
                    $controlOptions = json_decode($matches[1], true);
                    $button_html = str_replace($matches[0], '', $button_html);
                }
                $button_html = str_replace(array('u-dialog-link', 'u-payment-button'), array('', ''), $button_html);
                if (isset($controlOptions['content']) && $controlOptions['content']) {
                    $this->productData['add_to_cart_text'] = $controlOptions['content'];
                }
                $buttonText = sprintf(np_translate('%s'), $this->productData['add_to_cart_text']);
                if ($this->_options['typeControl'] === "products") {
                    $button_html = preg_replace('/href=[\'|"][\s\S]*?[\'|"]/', 'href="%s"', $button_html);
                    if (preg_match('/<!--product_button_content-->([\s\S]+?)<!--\/product_button_content-->/', $button_html, $matchesContent)) {
                        $content = $matchesContent[1];
                        if (preg_match('/<[^>]*class="[^"]*u-icon[^"]*"[^>]*>/s', $content, $matchesIcon)) {
                            $controlOptions['content'] = $content;
                        }
                        if (get_option('np_theme_appearance') === 'plugin-option' && (is_shop() || is_product_category())) {
                            $controlOptions['content'] = '';
                            $button_html = str_replace('u-add-to-cart-link', '', $button_html);
                        }
                        if (is_product()) {
                            $button_html = str_replace('u-add-to-cart-link', '', $button_html);
                        }
                        if (get_option('np_theme_appearance') === 'plugin-option' && isset($_GET['product-id'])) {
                            $controlOptions['content'] = '';
                        }
                        $button_html = str_replace($matchesContent[0], '%s', $button_html);
                    }
                }
                $goToProduct = false;
                if (isset($controlOptions['clickType']) && $controlOptions['clickType'] === 'go-to-page') {
                    $goToProduct = true;
                }
                if ($goToProduct) {
                    $button_html = sprintf(
                        $button_html,
                        get_permalink($this->productData['product']->get_id()),
                        $buttonText
                    );
                } else {
                    if ($this->_options['typeControl'] === "products") {
                        $button_html = preg_replace('/class=[\'|"]([\s\S]+?)[\'|"]/', 'class="$1 %s"', $button_html);
                        $button_html = str_replace('href', 'data-quantity="1" data-product_id="%s" data-product_sku="%s" href', $button_html);
                    }
                    $button_html = NpDataProduct::getProductButtonHtml($button_html, $product, $this->_options['typeControl'], $controlOptions);
                    if (get_option('np_theme_appearance') === 'plugin-option' && isset($_GET['product-id']) && strpos($button_html, 'data-product_sku') !== false) {
                        $button_html = str_replace('u-add-to-cart-link', '', $button_html);
                    }
                    if ($this->_options['typeControl'] === "products") {
                        global $current_post_object;
                        $postId = isset($current_post_object->ID) ? $current_post_object->ID : 0;
                        $postType = isset($current_post_object->post_type) ? $current_post_object->post_type : '';
                        if (!is_product() && empty($_GET['product-id']) && !np_data_provider($postId)->isNp()
                            || (!is_product() && !empty($GLOBALS['pluginTemplatesExists']) && $postType === 'template')
                        ) {
                            return class_exists('Nicepage') && Nicepage::$override_with_plugin ? '' : $button_html;
                        }
                    }
                    if ($this->_options['typeControl'] === "product" && $this->productData['type'] !== "variable") {
                        ob_start();
                        woocommerce_template_single_add_to_cart();
                        $form = ob_get_clean();
                        $form = preg_replace('/<p class="stock.+">[\s\S]+?<\/p>/', '', $form);
                        return $form = preg_replace('/(<form[\s\S]*?>)([\s\S]+?)(<\/form>)/', '$1' . $button_html . '<div style="display:none;">$2</div>' . '$3', $form);
                    }
                }
                return $button_html;
            },
            $content
        );
    }

    /**
     * Replace placeholder for product price
     *
     * @param string $content
     *
     * @return string $content
     */
    protected function _replacePrice($content) {
        return preg_replace_callback(
            '/<!--product_price-->([\s\S]+?)<!--\/product_price-->/',
            function ($priceHtml) {
                $product = $this->productData['product'];
                $priceHtml = $priceHtml[1];
                $price = $this->productData['price'];
                $price_old = $this->productData['price_old'];
                if ($product->get_regular_price() == $product->get_price()) {
                    $price_old = '';
                }
                $priceHtml = preg_replace('/<\!--product_old_price-->([\s\S]+?<div[\s\S]+?>)[\s\S]+?(<\/div>)<\!--\/product_old_price-->/', '$1 ' . $price_old . ' $2', $priceHtml, 2);
                return preg_replace('/<\!--product_regular_price-->([\s\S]+?<div[\s\S]+?>)[\s\S]+?(<\/div>)<\!--\/product_regular_price-->/', ('$1 ' . $price . ' $2'), $priceHtml);
            },
            $content
        );
    }

    /**
     * @param array $params
     *
     * @return array $products
     */
    public function getProducts($params) {
        global $products_control_query;
        $params = $this->_prepareSortingQuery($params);
        $params = $this->_prepareCategoriesFilterQuery($params);
        $products_control_query = getWpQuery($params);
        return isset($products_control_query->posts) ? $products_control_query->posts : array();
    }

    /**
     * Add to WP_QUERY $params sorting types
     *
     * @param array $params
     *
     * @return array $params
     */
    public function _prepareSortingQuery($params)
    {
        $sorting = isset($_GET['sorting']) ? sanitize_text_field($_GET['sorting']) : '';
        if ($sorting) {
            switch ($sorting) {
            case 'popularity':
                $params['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                $params['orderby'] = 'meta_value_num';
                break;
            case 'rating':
                $params['meta_key'] = '_wc_average_rating'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                $params['orderby'] = 'meta_value_num';
                $params['order'] = 'DESC';
                break;
            case 'date':
                $params['orderby'] = 'date';
                break;
            case 'price':
                $params['orderby'] = 'meta_value_num';
                $params['order'] = 'ASC';
                $params['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    'relation' => 'OR',
                    array(
                        'key' => '_price',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key' => '_price',
                        'value' => array(0, PHP_INT_MAX),
                        'compare' => 'BETWEEN',
                        'type' => 'NUMERIC'
                    ),
                );
                break;
            case 'price-desc':
                $params['orderby'] = 'meta_value_num';
                $params['order'] = 'DESC';
                $params['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    'relation' => 'OR',
                    array(
                        'key' => '_price',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key' => '_price',
                        'value' => array(0, PHP_INT_MAX),
                        'compare' => 'BETWEEN',
                        'type' => 'NUMERIC'
                    ),
                );
                break;
            default:
                $params['order'] = 'DESC';
                break;
            }
        }
        return $params;
    }

    /**
     * Add to WP_QUERY $params for categories filter
     *
     * @param array $params
     *
     * @return array $params
     */
    public function _prepareCategoriesFilterQuery($params)
    {
        $filter = isset($_GET['categoryId']) ? sanitize_text_field($_GET['categoryId']) : '';
        if ($filter) {
            switch ($filter) {
            case 'all':
                break;
            case 'featured':
                $tax_query[] = array(
                    array(
                        'taxonomy' => 'product_visibility',
                        'field'    => 'name',
                        'terms'    => 'featured',
                        'operator' => 'IN',
                    ),
                );
                $params['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                break;
            default:
                $tax_query[] = array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'term_id',
                        'terms'    => $filter,
                    ),
                );
                $params['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                break;
            }
        }
        return $params;
    }

    /**
     * Add product schema for seo
     *
     * @param string $content
     *
     * @return string $content
     */
    protected function _addProductSchema($content) {
        $product = $this->productData['product'];
        $meta = $this->productData['meta'] ?: get_post_meta($product->get_id());
        $stock = isset($meta['_stock_status'][0]) && $meta['_stock_status'][0] == 'instock' ? 'InStock' : 'OutOfStock';
        if ($product->get_regular_price() != null) {
            $price = $product->get_regular_price();
        } elseif ($product->get_price() != null) {
            $price = $product->get_price();
        }
        if (($price > $product->get_sale_price()) && ($product->get_sale_price() != null)) {
            $price = $product->get_sale_price();
        }
        $product_description = $this->productData['fullDesc'] != null ? $this->productData['fullDesc'] : '';
        $image = $this->productData['image_url'] ?: '';
        global $current_post_object;
        $product_json = array(
            "@context" => "http://schema.org",
            "@type" => "Product",
            "name" => get_the_title($this->productData['product']->get_id()),
            "image" => $image,
            "offers" => array(
                "@type" => "Offer",
                "priceCurrency" => get_woocommerce_currency(),
                "price" => $price,
                "itemCondition" => "http://schema.org/NewCondition",
                "availability" => "http://schema.org/" . $stock,
                "url" => get_permalink($current_post_object->ID),
                "description" => $product_description,
            ),
        );
        $content .= '<script type="application/ld+json">' . json_encode($product_json) . "</script>\n";
        return $content;
    }
}
