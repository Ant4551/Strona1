<?php
add_action('wp_enqueue_scripts', 'add_theme_np_shop_script', 1003);
function add_theme_np_shop_script() {
    if (get_query_var('products-list', null) !== null || isset($_GET['products-list'])) {
        wp_register_script('theme-np-shop-scripts', get_template_directory_uri() . '/shop/js/theme-np-shop-scripts.js', array());
        wp_enqueue_script('theme-np-shop-scripts');
    }
}
// add custom urls for product and products templates
if ( ! function_exists( 'shop_templates_url_init' )) {
    function shop_templates_url_init() {
        add_rewrite_tag('%product-id%','([^/]+)');
        add_rewrite_rule('^product-id/([^/]+)/lang/([^/]+)/?','index.php?product-id=$matches[1]&lang=$matches[2]', 'top');
        add_rewrite_rule('^product-id/([^/]+)/?','index.php?product-id=$matches[1]', 'top');
        add_rewrite_tag('%products-list%','([^/]+)');
        add_rewrite_rule('^products-list/lang/([^/]+)/?','index.php?products-list=1&lang=$matches[1]', 'top');
        add_rewrite_rule('^products-list/([^/]+)/lang/([^/]+)/?','index.php?products-list=$matches[1]&lang=$matches[2]', 'top');
        add_rewrite_rule('^products-list/([^/]+)/?','index.php?products-list=$matches[1]', 'top');
        add_rewrite_rule('^products-list/?$','index.php?products-list=1', 'top');
        add_rewrite_tag('%thank-you%','([^/]+)');
        add_rewrite_rule('^thank-you/lang/([^/]+)/?','index.php?thank-you=1&lang=$matches[1]', 'top');
        add_rewrite_rule('^thank-you/([^/]+)/lang/([^/]+)/?','index.php?thank-you=$matches[1]&lang=$matches[2]', 'top');
        add_rewrite_rule('^thank-you/([^/]+)/?','index.php?thank-you=$matches[1]', 'top');
        add_rewrite_rule('^thank-you/?$','index.php?thank-you=1', 'top');
    }
    add_action('init', 'shop_templates_url_init');
}
// require product and products templates
if ( ! function_exists( 'shop_custom_templates' )) {
    function shop_custom_templates($template) {
        $path = false;
        if ( get_query_var('product-id', null) !== null ) {
            $path = '/shop/product.php';
        }
        if ( get_query_var('products-list', null) !== null ) {
            $path = '/shop/products.php';
        }
        if ( get_query_var('thank-you', null) !== null ) {
            $path = '/shop/thank-you.php';
        }
        if ($path) {
            $template = get_template_directory() . $path;
        }
        return $template;
    }
    add_filter('template_include', 'shop_custom_templates', 50);
}

if ( ! function_exists( 'get_product_data' )) {
    function get_product_data($product, $productId) {
        $productData = array();
        if (isset($product['images'])) {
            $product['images'] = prepareImagesData($product['images']);
        }
        $productData['title'] = isset($product['title']) ? $product['title'] : '';
        $productData['shortDesc'] = isset($product['description']) ? mb_strimwidth($product['description'], 0, 250, '...') : '';
        $productData['fullDesc'] = isset($product['fullDescription']) ? $product['fullDescription'] : '';
        $productData['price'] = get_np_full_price($product);
        $productData['price_old'] = get_np_full_price($product, 'old');
        $productData['images'] = isset($product['images']) ? $product['images'] : array();
        $productData['image_url'] = isset($product['images']) && count($product['images']) > 0 ? array_shift($product['images'])['url'] : '';
        $productData['second_image_url'] = isset($product['images']) && count($product['images']) > 0 ? array_shift($product['images'])['url'] : '';
        $productData['productUrl'] = $productId
            ? ThemeMultiLanguagesResolver::build_np_route_url('product', $productId, 'theme')
            : '#';
        $productData['add_to_cart_text'] = 'Add to Cart';
        $productData['product-is-new'] = getProductIsNew($product);
        $productData['product-sale'] = getProductSale($product);
        $productData['categories'] = getProductCategories($product);
        $productData['product-out-of-stock'] = getProductOutOfStock($product);
        $productData['product-sku'] = getProductSku($product);
        $productData['saleEnabled'] = isset($product['saleEnabled']) ? $product['saleEnabled'] : false;
        $productData['saleStart'] = isset($product['saleStart']) ? $product['saleStart'] : false;
        $productData['saleEnd'] = isset($product['saleEnd']) ? $product['saleEnd'] : false;
        return $productData;
    }
}

if ( ! function_exists('get_np_full_price')) {
    function get_np_full_price($product, $type = 'new') {
        $language = ThemeMultiLanguagesResolver::get_current_language();
        if ($type === 'new') {
            $result = isset($product['fullPrice']) ? $product['fullPrice'] : '';
            if ($language) {
                if (isset($product['translations'][$language]['fullPrice'])) {
                    $result = $product['translations'][$language]['fullPrice'];
                }
            }
        } else {
            $result = isset($product['fullPriceOld']) ? $product['fullPriceOld'] : '';
            if ($language) {
                if (isset($product['translations'][$language]['fullPriceOld'])) {
                    $result = $product['translations'][$language]['fullPriceOld'];
                }
            }
        }
        return $result;
    }
}

if ( ! function_exists( 'getProductIsNew' )) {
    /**
     * Product is new
     */
    function getProductIsNew($product) {
        $currentDate = (int) (microtime(true) * 1000);
        if (isset($product['created'])) {
            $createdDate = (int) $product['created'];
        } else {
            $createdDate = $currentDate;
        }
        $milliseconds30Days = 30 * (60 * 60 * 24 * 1000); // 30 days in milliseconds
        if (($currentDate - $createdDate) <= $milliseconds30Days) {
            return true;
        }
        return false;
    }
}

if ( ! function_exists( 'getProductSale' )) {
    /**
     * Sale for product
     */
    function getProductSale($product) {
        $price = 0;
        if ( isset($product['price']) ) {
            $price = $product['price'];
        }
        $oldPrice = 0;
        if ( isset($product['oldPrice']) ) {
            $oldPrice = $product['oldPrice'];
        }
        $sale = '';
        if ( $price && $oldPrice && $price < $oldPrice ) {
            $sale = '-' . (int) ( 100 - ( $price * 100 / $oldPrice ) ) . '%';
        }
        return $sale;
    }
}

if ( ! function_exists( 'getProductCategories' )) {
    /**
     * Get product categories
     *
     * @return array $categories
     */
    function getProductCategories($product) {
        $categories = array(
            0 => array(
                'id' => 0,
                'title' => 'Uncategorized',
                'link' => '#',
            )
        );
        $data = array();
        if (file_exists(get_template_directory() . '/shop/products.json')) {
            $data = file_get_contents(get_template_directory() . '/shop/products.json');
            $data = json_decode($data, true);
        }
        if (!$data) {
            return $categories;
        }
        $all_categories = isset($data['categories']) ? $data['categories'] : array();
        $product_categories = isset($product['categories']) ? $product['categories'] : array();
        if ($product_categories) {
            $categories = array();
            foreach ($product_categories as $id) {
                $category = findElementById($all_categories, $id);
                if ($category) {
                    array_push(
                        $categories,
                        array(
                            'title' => isset($category['title']) ? $category['title'] : 'Uncategorized',
                            'link'  => ThemeMultiLanguagesResolver::build_np_products_category_url($id, 'theme'),
                        )
                    );
                }
            }
        }
        return $categories;
    }
}

if ( ! function_exists( 'findElementById' )) {
    function findElementById($all_categories, $cat_id) {
        foreach ($all_categories as $element) {
            if ($element['id'] == $cat_id) {
                return $element;
            }
        }
        return null;
    }
}

if ( ! function_exists( 'getProductOutOfStock' )) {
    /**
     * Get product out of stock
     *
     * @return bool outOfStock
     */
    function getProductOutOfStock($product) {
        return isset($product['outOfStock']) ? $product['outOfStock'] : false;
    }
}

if ( ! function_exists( 'getProductSku' )) {
    /**
     * Get product sku
     *
     * @return string sku
     */
    function getProductSku($product) {
        return isset($product['sku']) ? $product['sku'] : '';
    }
}

if (! function_exists( 'get_np_shop_categories_html' )) {
    /**
     * Get categories html for np shop
     *
     * @param $args
     * @return string
     */
    function get_np_shop_categories_html($args) {
        $categories_html = '';
        $showIcon = 'fill-opacity="1"';
        $linkTitle = '{content}';
        $linkUrl = '{url}';
        $isActiveLi = '{activeLi}';
        $isActiveLink = '{activeLink}';
        $iconOpen = '#icon-categories-open';
        $iconClosed = '#icon-categories-closed';
        $liOpen = 'u-expand-open';
        $liClosed = 'u-expand-closed';
        $template_cats = npGetCategories();
        array_unshift( $template_cats, array(
            'id'    => '',
            'title' => 'All',
            'link'  => ThemeMultiLanguagesResolver::build_np_route_url('products', '', 'theme'),
        ) );
        if($template_cats) {
            foreach($template_cats as $template_category) {
                $needShowIcon = is_np_category_has_child($template_category);
                if ($needShowIcon === $showIcon) {
                    $childs = getChildCategories($template_category['id'], $template_cats);
                    $subCats_html = getShopSubCatHtml($childs, $args);
                } else {
                    $subCats_html = '';
                }
                $categories_html .= str_replace(
                    array($linkTitle, $linkUrl, $isActiveLi, $isActiveLink, $showIcon, $iconOpen, $liOpen, 'u-expand-leaf', '</li>'),
                    array(
                        $template_category['title'],
                        ThemeMultiLanguagesResolver::build_np_products_category_url($template_category['id'], 'theme'),
                        '',
                        '',
                        $needShowIcon,
                        $iconClosed,
                        $liClosed,
                        'u-expand-closed',
                        $subCats_html . '</li>'
                    ),
                    $args['itemTemplate']
                );
            }
        }
        $categories_html = strtr($args['template'], array('{categories}' => $categories_html));
        return $categories_html;
    }
}

if (! function_exists('is_np_category_has_child')) {
    function is_np_category_has_child($category) {
        return isset($category['items']) && count($category['items']) > 0 ? 'fill-opacity="1"' : 'fill-opacity="0"';
    }
}

if (! function_exists('getShopSubCatHtml')) {
    function getShopSubCatHtml($categories, $args, $onlyItems=false) {
        $output = "";
        if (empty($categories)) {
            return $output;
        }
        if ($args['type'] === 'vertical') {
            foreach ($categories as $category) {
                $args['itemTemplate'] = str_replace('</li>', '', $args['itemTemplate']);
                $output .= str_replace(
                    array('{content}', '{url}', '{activeLi}', '{activeLink}', 'u-root'),
                    array(
                        $category['title'],
                        ThemeMultiLanguagesResolver::build_np_products_category_url($category['id'], 'theme'),
                        '',
                        ''
                    ),
                    $args['itemTemplate']
                );
                if (!empty($category['items'])) {
                    $output = str_replace('u-expand-leaf', 'u-expand-closed', $output);
                    $output .= getShopSubCatHtml($category['items'], $args);
                    if (!$onlyItems) {
                        $output = '<ul class="u-unstyled">' . $output . '</ul>';
                    }
                }
                $output .= "</li>";
            }
            if (!$onlyItems) {
                $output = '<ul class="u-unstyled">' . $output . '</ul>';
            }
        }
        return $output;
    }
}

if (!function_exists('getChildCategories')) {
    function getChildCategories($parentCategoryId, $categories) {
        $result = array();
        foreach ($categories as $category) {
            if ($category['id'] == $parentCategoryId) {
                $result = isset($category['items']) ? $category['items'] : array();
            }
        }
        return $result;
    }
}

if (!function_exists('np_get_all_categories')) {
    function np_get_all_categories() {
        if (file_exists(get_template_directory() . '/shop/products.json')) {
            $data = file_get_contents(get_template_directory() . '/shop/products.json');
            $data = json_decode($data, true);
        }
        return isset($data['categories']) ? $data['categories'] : array();
    }
}

if (!function_exists('npGetCategories')) {
    function npGetCategories() {
        $allCategories = np_get_all_categories();
        $result = buildCategoryTree($allCategories);
        return $result;
    }
}

if (!function_exists('buildCategoryTree')) {
    function buildCategoryTree($categories, $parentId = null) {
        $categoryTree = array();
        foreach ($categories as $category) {
            if ($category['categoryId'] == $parentId) {
                $children = buildCategoryTree($categories, $category['id']);
                if ($children) {
                    $category['items'] = $children;
                }
                $categoryTree[] = $category;
            }
        }
        return $categoryTree;
    }
}

if (! function_exists('get_np_shop_categories_filter_html')) {
    /**
     * Get categories filters html
     *
     * @param array $args
     *
     * @return string $categories_filter_html
     */
    function get_np_shop_categories_filter_html($args) {
        $categories_filter_html = '';
        $categories = np_get_all_categories();
        // add item all
        $categories_filter_html .= strtr($args['itemTemplate'], array('{categories_filters_content}' => __('All', 'site1'), '{categories_filters_value}' => ''));
        // add item featured
        $item = strtr($args['itemTemplate'], array('{categories_filters_content}' => __('Featured', 'site1'), '{categories_filters_value}' => 'featured'));
        $categories_filter_html .= $item;
        // add all categories with hierarchy
        $categories_filter_html .= generate_np_category_options($categories, $args);
        $categories_filter_html = strtr($args['template'], array('{categories_filters}' => $categories_filter_html));
        return $categories_filter_html;
    }
}

if (! function_exists('generate_np_category_options')) {
    /**
     * Generate categories filter options with hierarchy
     *
     * @param array $categories
     * @param string $itemTemplate
     * @param int $parent
     * @param string $prefix
     *
     * @return string $result
     */
    function generate_np_category_options( $categories, $args, $parent = 0, $prefix = '' ) {
        $result = '';
        foreach ( $categories as $category ) {
            if ( $category['categoryId'] == $parent ) {
                $item = strtr( $args['itemTemplate'], array(
                    '{categories_filters_content}' => $prefix . $category['title'],
                    '{categories_filters_value}'   => $category['id'],
                ) );
                $result .= $item;
                $result .= generate_np_category_options( $categories, $args, $category['id'], $prefix . '-' );
            }
        }

        return $result;
    }
}