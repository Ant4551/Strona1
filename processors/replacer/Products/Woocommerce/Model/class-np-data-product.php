<?php
// phpcs:disable WordPress.Security.EscapeOutput
defined('ABSPATH') or die;

class NpDataProduct {

    public static $product;

    /**
     * Get woocommerce product object
     *
     * @param int $product_id
     *
     * @return object $product
     */
    public static function getProduct($product_id) {
        if (function_exists('wc_get_product')) {
            return $product = wc_get_product($product_id);
        } else {
            return null;
        }
    }

    /**
     * Get full data product for np
     *
     * @param bool $editor
     *
     * @return array $product_data
     */
    public static function getProductData($editor = false) {
        $product_data = array(
            'product'               => self::$product,
            'created'               => self::getProductDate(),
            'type'                  => self::getProductType(),
            'title'                 => self::getProductTitle(),
            'fullDesc'              => self::getProductFullDesc(),
            'shortDesc'             => self::getProductShortDesc(),
            'image_url'             => self::getProductImageUrl(),
            'second_image_url'      => self::getSecondProductImageUrl(),
            'price'                 => self::getProductPrice($editor),
            'price_old'             => self::getProductPriceOld($editor),
            'price_sorting'         => self::getSortingProductPrice($editor),
            'add_to_cart_text'      => self::getProductAddToCartText(),
            'attributes'            => self::getProductAttributes(),
            'variations_attributes' => self::getProductVariationAttributes(),
            'gallery_images_ids'    => self::getProductImagesIds(),
            'tabs'                  => self::getProductDefaultProductTabs(),
            'meta'                  => self::getProductMeta(),
            'categories'            => self::getProductCategories(),
            'product-is-new'        => self::getProductIsNew(),
            'product-sale'          => self::getProductSale(),
            'product-out-of-stock'  => self::getProductOutOfStock(),
            'product-sku'           => self::getProductSku(),
        );
        return $product_data;
    }

    /**
     * Get product type
     *
     * @return string $product_type
     */
    public static function getProductType() {
        return $product_type = self::$product->get_type();
    }

    /**
     * Get product date created
     *
     * @return int $date
     */
    public static function getProductDate() {
        $dateCreated = self::$product->get_date_created();
        if ($dateCreated) {
            if ((class_exists('WC_DateTime') && $dateCreated instanceof WC_DateTime) || $dateCreated instanceof \DateTime) {
                return (int) $dateCreated->getTimestamp() * 1000;
            }
        }
        return 0;
    }


    /**
     * Get product title
     *
     * @return string $title
     */
    public static function getProductTitle() {
        $title = self::$product->get_title();
        return apply_filters('woocommerce_product_title', $title, self::$product);
    }

    /**
     * Get product short description
     *
     * @return string $desc
     */
    public static function getProductShortDesc() {
        $product_id  = self::$product->get_id();
        $desc = function_exists('plugin_trim_long_str') ? plugin_trim_long_str(NpAdminActions::getTheExcerpt($product_id), 250) : theme_trim_long_str(getTheExcerpt($product_id), 250);
        return apply_filters('woocommerce_short_description', $desc);
    }

    /**
     * Get product full description
     *
     * @return string $fullDesc
     */
    public static function getProductFullDesc() {
        $fullDesc = wpautop(self::$product->get_description());
        return apply_filters('the_content', $fullDesc);
    }

    /**
     * Get product description
     *
     * @return string $desc
     */
    public static function getProductMeta() {
        $product_id  = self::$product->get_id();
        return $meta = get_post_meta($product_id);
    }

    /**
     * Get product categories
     *
     * @return array $categories
     */
    public static function getProductCategories() {
        $default_cat_id = get_option('default_product_cat') ? (int)get_option('default_product_cat'): 0;
        $categories = array(
            0 => array(
                'id' => $default_cat_id,
                'title' => 'Uncategorized',
                'link' => $default_cat_id ? get_term_link($default_cat_id, 'product_cat') : '#',
            )
        );
        $product_id = self::$product->get_id();
        $terms = get_the_terms($product_id, 'product_cat');
        if ($terms) {
            foreach ($terms as $index => $term) {
                if (!isset($categories[$index])) {
                    $categories[$index] = array();
                }
                $categories[$index]['id'] = isset($term->term_id) ? $term->term_id : 0;
                $categories[$index]['title'] = isset($term->name) ? np_get_translation_term($term) : 'Uncategorized';
                $categories[$index]['link'] = isset($term->term_id) ? get_term_link($term->term_id, 'product_cat') : '#';
            }
        }
        return $categories;
    }

    /**
     * Product is new
     */
    public static function getProductIsNew() {
        $currentDate = (int) (microtime(true) * 1000);
        if (self::$product->get_date_created()) {
            $createdDate = (int) strtotime(self::$product->get_date_created()) * 1000;
        } else {
            $createdDate = $currentDate;
        }
        $milliseconds30Days = 30 * (60 * 60 * 24 * 1000); // 30 days in milliseconds
        if (($currentDate - $createdDate) <= $milliseconds30Days) {
            return true;
        }
        return false;
    }

    /**
     * Sale for product
     */
    public static function getProductSale() {
        $price = 0;
        if (self::$product->get_sale_price()) {
            $price = (float) self::$product->get_sale_price();
        }
        $oldPrice = 0;
        if (self::$product->get_regular_price()) {
            $oldPrice = (float) self::$product->get_regular_price();
        }
        $sale = '';
        if ($price && $oldPrice && $price < $oldPrice) {
            $sale = '-' . (int) ( 100 - ( $price * 100 / $oldPrice ) ) . '%';
        }
        return $sale;
    }

    /**
     * Get product out of stock
     *
     * @return bool outOfStock
     */
    public static function getProductOutOfStock() {
        $outOfStocks = false;
        if (self::$product->get_stock_status()) {
            $outOfStocks = self::$product->get_stock_status() === 'outofstock' ? true : false;
        }
        return $outOfStocks;
    }

    /**
     * Get product sku
     *
     * @return string sku
     */
    public static function getProductSku() {
        $sku = '';
        if (self::$product->get_sku()) {
            $sku = self::$product->get_sku();
        }
        return $sku;
    }

    /**
     * Get product image url
     *
     * @return string $image_url
     */
    public static function getProductImageUrl() {
        $image_id  = self::$product->get_image_id();
        $image_url = wp_get_attachment_image_url($image_id, 'full');
        return $image_url;
    }

    /**
     * Get product second image url
     *
     * @return string $second_image_url
     */
    public static function getSecondProductImageUrl() {
        $gallery_ids = self::$product->get_gallery_image_ids();

        if (!empty($gallery_ids) && isset($gallery_ids[1])) {
            return wp_get_attachment_image_url($gallery_ids[1], 'full');
        }

        return null;
    }

    /**
     * Get product price
     *
     * @param bool $editor
     *
     * @return int $price
     */
    public static function getProductPrice($editor = false) {
        $price = self::$product->get_price();
        if ($editor) {
            $price = (!$price || $price === '') ? 0 : $price;
        }
        if ($price !== '') {
            $price = wc_price($price);
        }
        if (self::$product->is_type('variable')) {
            if (wc_price(self::$product->get_variation_sale_price('min', true)) === wc_price(self::$product->get_variation_sale_price('max', true))) {
                $price = wc_price(self::$product->get_variation_sale_price('min', true));
            } else {
                $price = wc_price(self::$product->get_variation_sale_price('min', true)) . ' - ' . wc_price(self::$product->get_variation_sale_price('max', true));
            }
        }
        $price_suffix = self::$product->get_price_suffix();
        return $price . '<span style="color:rgb(0, 0, 0);margin-left: 6px;font-size: 94%;">' . apply_filters('woocommerce_get_price_suffix', $price_suffix, self::$product, $price, 1) . '</span>';
    }


    /**
     * Get sorting product price - only number
     *
     * @param bool $editor
     *
     * @return int $price
     */
    public static function getSortingProductPrice($editor = false) {
        $price = self::$product->get_price();
        if (self::$product->is_type('variable')) {
            return 0;
        }
        if ($editor) {
            $price = (!$price || $price === '') ? 0 : $price;
        }
        if (!$price || $price === '') {
            return $editor ? 0 : '';
        }
        return floatval($price);
    }

    /**
     * Get product price old
     *
     * @param bool $editor
     *
     * @return int $price_old
     */
    public static function getProductPriceOld($editor = false) {
        $price_old = self::$product->get_regular_price();
        if ($editor) {
            $price_old = (!$price_old || $price_old === '') ? 0 : $price_old;
        }
        if ($price_old !== '') {
            $price_old = wc_price($price_old);
        }
        return $price_old;
    }

    /**
     * Get product add to cart text
     *
     * @return string $add_to_cart_text
     */
    public static function getProductAddToCartText() {
        return $add_to_cart_text = self::$product->add_to_cart_text();
    }

    /**
     * Get product attributes as an entity for attributes
     * or ready-made values ​​for custom attributes created when editing a product
     *
     * @return array $productAttributes
     */
    public static function getProductAttributes() {
        return $productAttributes = self::$product->get_attributes();
    }

    /**
     * Get product variation attributes
     *
     * @return array $variation_attributes
     */
    public static function getProductVariationAttributes() {
        $product_type         = self::getProductType(self::$product);
        $variation_attributes = array();
        if ($product_type === 'variable') {
            $variation_attributes = self::$product->get_variation_attributes();
        }
        return $variation_attributes;
    }

    /**
     * Get product gallery images ids
     *
     * @return object $attachment_ids
     */
    public static function getProductImagesIds() {
        return $attachment_ids = self::$product->get_gallery_image_ids();
    }

    /**
     * Get product default tabs
     *
     * @return array $tabs
     */
    /**
     * Get product default tabs
     *
     * @return array $tabs
     */
    public static function getProductDefaultProductTabs() {
        if (!isset(self::$product) || !self::$product instanceof WC_Product) {
            return array();
        }
        $product_id = self::$product->get_id();
        if (!$product_id) {
            return array();
        }

        global $post, $product;
        $postId = isset($post->ID) ? $post->ID : 0;
        $isNp = function_exists('np_data_provider') && np_data_provider($postId)->isNp();
        $post_old = $post;
        $post = get_post($product_id);
        $post->isNp = $isNp;
        remove_filter('comments_template', array('WC_Template_Loader', 'comments_template_loader'));
        $product = self::$product;
        $tabs = apply_filters('woocommerce_product_tabs', array());
        if (!is_array($tabs)) {
            error_log('woocommerce_product_tabs returned non-array: ' . print_r($tabs, true));
            $tabs = array();
        }
        if (empty($tabs)) {
            $review_count = self::$product->get_review_count();

            $tabs['description'] = array(
                'title'    => np_translate('Description'),
                'priority' => 10,
                'callback' => array(__CLASS__, 'productDescriptionTab'),
            );
            $tabs['reviews'] = array(
                'title'    => sprintf(np_translate('Reviews (%d)'), $review_count),
                'priority' => 30,
                'callback' => array(__CLASS__, 'productReviewsTab'),
            );
        }
        $resultTabs = array();
        foreach ($tabs as $key => $tab) {
            ob_start();
            if (isset($tab['callback'])) {
                call_user_func($tab['callback'], $key, $tab);
            }
            $content = ob_get_clean();
            $resultTabs[] = array(
                'title'   => $tab['title'],
                'content' => $content,
            );
        }
        $post = $post_old;
        return $resultTabs;
    }

    /**
     * Callback for tab Description.
     *
     * @param string $key Key name.
     * @param array  $tab Tab data.
     */
    public static function productDescriptionTab($key, $tab) {
        $heading = apply_filters('woocommerce_product_description_heading', np_translate('Description'));
        echo '<h2>' . esc_html($heading) . '</h2>';
        echo self::$product->get_description();
    }

    /**
     * Callback for tab Reviews.
     *
     * @param string $key Key name.
     * @param array  $tab Tab data.
     */
    public static function productReviewsTab($key, $tab) {
        global $product;
        $product = self::getProduct(self::$product->get_id()) === null ? $product : self::getProduct(self::$product->get_id());
        global $withcomments;
        $withcomments = true;
        comments_template();
    }

    /**
     * Get product attribute by id
     *
     * @param int $attribute_id
     *
     * @return object $attribute
     */
    public static function getProductAttribute($attribute_id) {
        return $attribute = wc_get_attribute($attribute_id);
    }

    /**
     * Get button add to cart html
     *
     * @param string $button_html
     * @param object $product
     * @param string $type
     * @param array  $options
     *
     * @return string $button_html
     */
    public static function getProductButtonHtml($button_html, $product, $type, $options = array()) {
        $product_id  = $product->get_id();

        $button_class = implode(
            ' ',
            array_filter(
                array(
                    'button',
                    'product_type_' . $product->get_type(),
                    $product->is_purchasable() && $product->is_in_stock() ? 'add_to_cart_button' : '',
                    $product->supports('ajax_add_to_cart') && $product->is_purchasable() && $product->is_in_stock() ? 'ajax_add_to_cart' : '',
                )
            )
        );

        if ($type === "product") {
            $button_class = '';
        }
        $button_text = sprintf(
            np_translate('%s'),
            NpDataProduct::getProductAddToCartText($product)
        );
        if (class_exists('Nicepage') && !Nicepage::$isWooShopProductTemplate && ($button_text && isset($options['content']) && $options['content'])) {
            $button_text = $button_text === 'Add to cart' && $options['content'] === 'Select options' ? $button_text : $options['content'];
        }
        $button_html = apply_filters(
            'woocommerce_loop_add_to_cart_link',
            sprintf(
                $button_html,
                esc_attr($product_id),
                esc_attr($product->get_sku()),
                esc_url($product->add_to_cart_url()),
                $button_class,
                $button_text
            ),
            $product
        );
        if (class_exists('Nicepage') && Nicepage::$isWooShopProductTemplate) {
            $button_html = preg_replace('/<!--product_button_content-->[\s\S]+?<!--\/product_button_content-->/', $button_text, $button_html);
        }
        return $button_html;
    }

    /**
     * Get product variation title
     *
     * @param object $attribute
     * @param object $productAttribute
     *
     * @return string $variation_title
     */
    public static function getProductVariationTitle($attribute, $productAttribute) {
        if (isset($attribute->name)) {
            return $attribute->name;
        }

        $attr_object = $productAttribute->get_taxonomy_object();
        return $attr_object->attribute_label ?: $attr_object->attribute_name;
    }

    /**
     * Get product variation option title
     *
     * @param array|string $variation_option
     *
     * @return string $variation_option_title
     */
    public static function getProductVariationOptionTitle($variation_option) {
        if (is_string($variation_option)) {
            return $variation_option;
        }
        return $variation_option->name ?: '';
    }


    /**
     * Get product variation option value
     *
     * @param array|string $variation_option
     *
     * @return string $variation_option_value
     */
    public static function getProductVariationOptionValue($variation_option) {
        if (is_string($variation_option)) {
            return $variation_option;
        }
        return $variation_option->slug ?: '';
    }

}

/**
 * Add scripts and styles for woocommerce
 */
function add_shop_scripts() {
    global $post;
    $post_id = isset($post->ID) ? $post->ID : 0;
    if (is_woocommerce_active() && function_exists('np_data_provider') && np_data_provider($post_id)->isNp()) {
        wp_register_script('woocommerce-np-scripts', APP_PLUGIN_URL . 'includes/woocommerce/js/woocommerce-np-scripts.js', array('jquery'), time(), false);
        wp_enqueue_script('woocommerce-np-scripts');
        wp_register_style("woocommerce-np-styles", APP_PLUGIN_URL . 'includes/woocommerce/css/woocommerce-np-styles.css', array(), APP_PLUGIN_VERSION);
        wp_enqueue_style("woocommerce-np-styles");
    }
}

/**
 * Construct NpDataProduct object
 *
 * @param int  $product_id Product Id
 * @param bool $editor     Need to check editor or live site
 *
 * @return array NpDataProduct
 */
function np_data_product($product_id = 0, $editor = false)
{
    NpDataProduct::$product = NpDataProduct::getProduct($product_id);
    return NpDataProduct::$product ? NpDataProduct::getProductData($editor) : array();
}

/**
 * @param string $output
 *
 * @return string $output
 */
function change_comments_template_path($output) {
    global $post;
    if ($post->isNp) {
        return APP_PLUGIN_PATH . 'includes/controls/product-tabs/reviews/template.php';
    } else {
        return $output;
    }
}

add_filter('comments_template', 'change_comments_template_path', 10, 1);

if (!function_exists('np_review_ratings_enabled')) {
    /**
     * @return bool
     */
    function np_review_ratings_enabled() {
        return 'yes' === get_option('woocommerce_enable_reviews') && 'yes' === get_option('woocommerce_enable_review_rating');
    }
}
if (!function_exists('np_review_ratings_required')) {
    /**
     * @return bool
     */
    function np_review_ratings_required() {
        return 'yes' === get_option('woocommerce_review_rating_required');
    }
}

add_action('wp_enqueue_scripts', 'add_shop_scripts', 1003);

if (!function_exists('add_woo_cat')) {
    /**
     * Create woo category
     *
     * @param $category_name
     * @param int $parent_id
     *
     * @return int|mixed
     */
    function add_woo_cat($category_name, $parent_id = 0) {
        $category_exists = get_term_by('name', $category_name, 'product_cat');
        if (!$category_exists) {
            $new_category = wp_insert_term($category_name, 'product_cat', array('parent' => $parent_id));
            if (!is_wp_error($new_category)) {
                return $new_category['term_id'];
            }
        } else {
            return $category_exists->term_id;
        }
        return 0;
    }
}

if (!function_exists('import_categories_in_woocommerce')) {
    /**
     * Import woo categories
     *
     * @param array $categories
     * @param array $added_terms
     *
     * @return array $added_terms
     */
    function import_categories_in_woocommerce($categories, $added_terms) {
        foreach ($categories as $products_category) {
            $old_id = isset($products_category['id']) ? $products_category['id'] : null;
            $parent_id = isset($products_category['categoryId']) ? $products_category['categoryId'] : null;
            $title = isset($products_category['title']) ? $products_category['title'] : null;
            if ($old_id && $title && !$parent_id) {
                $category_old_new_ids[$old_id] = add_woo_cat($title);
            }
            if ($old_id && $title && $parent_id && isset($category_old_new_ids[$parent_id])) {
                $category_old_new_ids[$old_id] = add_woo_cat($title, $category_old_new_ids[$parent_id]);
            }
            if (isset($category_old_new_ids[$old_id])) {
                $added_terms[] = array(
                    'term_id' => (int)$category_old_new_ids[$old_id],
                    'taxonomy' => 'product_cat'
                );
            }

        }
        if ($category_old_new_ids) {
            update_option('woo_category_old_new_ids', $category_old_new_ids);
        }
        return $added_terms;
    }
}
// phpcs:enable