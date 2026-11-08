<?php
defined('ABSPATH') or die;

if (!class_exists('BaseController')) {
    include_once dirname(__FILE__) . '/BaseController.php';
}
if (!class_exists('GridHelper')) {
    include_once dirname(__FILE__) . '/class-np-grid-helper.php';
}
if (!class_exists('ProductItem')) {
    include_once dirname(__FILE__) . '/replacer/Products/ProductItem.php';
}
$file_path = dirname(__FILE__) . '/../includes/theme-builder/replacer/BlogPostTemplateDataProvider.php';
if (file_exists($file_path) && is_readable($file_path)) {
    include_once $file_path;
}
if (!class_exists('NpDataProduct')) {
    include_once dirname(__FILE__) . '/replacer/Products/Woocommerce/Model/class-np-data-product.php';
}
if (!class_exists('SiteDataProduct')) {
    include_once dirname(__FILE__) . '/replacer/Products/Site/Model/class-site-data-product.php';
}

class NpShopDataReplacer {

    public static $siteProductsProcess = false;
    public static $productsJson = array();

    /**
     * NpShopDataReplacer process.
     *
     * @param string $content
     *
     * @return string $content
     */
    public static function process($content) {
        self::$productsJson = getProductsJson();
        global $post;
        global $current_post_object;
        $current_post_object = $post;
        $content = self::_processProducts($content);
        if (is_woocommerce_active()) {
            $content = self::_processCartControl($content);
        }
        $post = $current_post_object;
        return $content;
    }

    /**
     * Process products
     *
     * @param string $content
     *
     * @return string $content
     */
    private static function _processProducts($content) {
        $content = self::_processProductsListControl($content);
        $content = self::_processProductControl($content);
        $content = self::_processCategoriesControl($content);
        $content = str_replace('_dollar_symbol_', '$', $content);
        return $content;
    }

    public static $params;

    /**
     * Process Product List Control
     *
     * @param string $content Page content
     *
     * @return string|string[]|null
     */
    private static function _processProductsListControl($content) {
        return preg_replace_callback(
            '/<\!--products-->([\s\S]+?)<\!--\/products-->/',
            function ($productsMatch) {
                $productsHtml = $productsMatch[1];
                // Add params for grid height update
                $productsHtml = preg_replace(
                    '/class=(["\'])([^"\']*)(["\'])/i',
                    'class=$1$2 u-cms u-hidden-visibility$3 ' . np_get_grid_hash_key(),
                    $productsHtml,
                    1
                );

                if (strpos($productsHtml, 'data-products-datasource') === false) {
                    $source = isset($_GET['productsList']) || isset($_GET['products-list']) ? 'site' : 'cms';
                    $productsHtml = str_replace('data-site-sorting-order', 'data-products-id="1" data-products-datasource="' . $source . '" data-site-sorting-order', $productsHtml);
                }

                if (strpos($productsHtml, 'data-products-datasource="cms"') !== false) {
                    $source = isset($_GET['productsList']) || isset($_GET['products-list']) ? 'site' : 'cms';
                    $productsHtml = str_replace('data-products-datasource="cms"', 'data-products-id="1" data-products-datasource="' . $source . '"', $productsHtml);
                }

                if (strpos($productsHtml, 'data-products-id="1"') === false) {
                    $productsHtml = str_replace('data-products-datasource', 'data-products-id="1" data-products-datasource', $productsHtml);
                }

                if (get_option('np_theme_appearance') === 'plugin-option' && function_exists('wc_get_product') && (is_shop() || is_product_category())) {
                    $productsHtml = str_replace('data-products-datasource="site"', 'data-products-datasource="cms"', $productsHtml);
                }

                if (preg_match('/data-site-category="([\s\S]*?)"/', $productsHtml, $matches)) {
                    $catId = isset($matches[1]) ? $matches[1] : '';
                }

                $productsOptions = array(
                    'productsJson' => self::$productsJson,
                    'paginationProps' => null,
                    'quantityExists' => false,
                    'showSecondImage' => strpos($productsHtml, 'u-show-second-image') !== false ? true : false,
                    'siteProductsProcess' => strpos($productsHtml, 'data-products-datasource="site"') !== false ? true : false,
                    'productName' => 'products-list',
                    'typeControl' => 'products',
                    'catId' => isset($catId) ? $catId : '',
                );
                $params = self::prepareProductsParams($productsHtml, $productsOptions);

                if (strpos($productsHtml, 'data-products-datasource="site"') !== false) {
                    self::$siteProductsProcess = true;
                    $processor = new SiteProductListProcessor($productsHtml, $params);
                } else {
                    $processor = new WooProductListProcessor($productsHtml, $params);
                }

                $processor->build();
                $productsHtml = $processor->getResult();
                return $productsHtml;
            },
            $content
        );
    }

    /**
     * Prepare products params
     *
     * @param string $productsHtml
     * @param array  $options
     *
     * @return array $params
     */
    public static function prepareProductsParams($productsHtml, $options) {
        $params = array(
            'order' => 'DESC',
            'orderby' => 'date',
        );
        $productsOptions = array();
        if (preg_match('/<\!--products_options_json--><\!--([\s\S]+?)--><\!--\/products_options_json-->/', $productsHtml, $matches)) {
            $productsOptions = json_decode($matches[1], true);
            $productsHtml = str_replace($matches[0], '', $productsHtml);
        }
        $productsSourceType = isset($productsOptions['type']) ? $productsOptions['type'] : '';
        if ($productsSourceType === 'Tags') {
            $params['source'] = 'tags:' . (isset($productsOptions['tags']) && $productsOptions['tags'] ? $productsOptions['tags'] : '');
        } else if ($productsSourceType === 'products-featured') {
            $params['source'] = 'featured';
        } else if ($productsSourceType === 'products-related') {
            if (function_exists('is_product') && is_product()) {
                $params['source'] = 'relative';
            }
        } else {
            $params['source'] = isset($productsOptions['source']) && $productsOptions['source'] ? $productsOptions['source'] : false;
        }
        $params['count'] = isset($productsOptions['count']) ? $productsOptions['count'] : '';
        // if $params['source'] == false - get last posts
        $params['entity_type'] = 'product';
        if (empty($_GET['sorting'])) {
            if (strpos($productsHtml, 'data-site-sorting-order="asc"') !== false) {
                $params['order'] = 'ASC';
            }
            if (strpos($productsHtml, 'data-site-sorting-prop="price"') !== false) {
                $params['orderby'] = 'meta_value_num';
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
            }
            if (strpos($productsHtml, 'data-site-sorting-prop="title"') !== false) {
                $params['orderby'] = 'title';
            }
        }
        if (empty($_GET['sorting'])) {
            if (strpos($productsHtml, 'data-site-sorting-prop="price"') !== false) {
                $params['orderby'] = 'meta_value_num';
                $params['meta_query'] = array(
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
            }
        }
        $params = array_merge($options, $params);
        return $params;
    }

    /**
     * Process Product control
     *
     * @param string $content Page content
     *
     * @return string|string[]|null
     */
    private static function _processProductControl($content) {
        return preg_replace_callback(
            '/<\!--product-->([\s\S]+?)<\!--\/product-->/',
            function ($productMatch) {
                $productHtml = $productMatch[1];
                $source = NpMultiLanguagesResolver::get_np_route_source('plugin');
                $prefix = $source === 'theme' ? 'product-id' : 'productId';
                if (isset($_GET[$prefix]) || strpos($productHtml, 'data-product-id') !== false) {
                    $siteProducts = isset(self::$productsJson['products']) ? self::$productsJson['products'] : array();
                    $products = array_combine(array_column($siteProducts, 'id'), $siteProducts);
                }

                if (strpos($productHtml, 'data-products-datasource') === false) {
                    $source = isset($_GET[$prefix]) ? 'site' : 'cms';
                    $productHtml = str_replace('data-product-id', 'data-products-datasource="' . $source . '" data-product-id', $productHtml);
                }

                if (strpos($productHtml, 'data-products-datasource="cms"') !== false) {
                    $source = isset($_GET[$prefix]) ? 'site' : 'cms';
                    $productHtml = str_replace('data-products-datasource="cms"', 'data-products-datasource="' . $source . '"', $productHtml);
                }

                if (strpos($productHtml, 'data-products-datasource="site"') !== false) {
                    self::$siteProductsProcess = true;
                }

                if (self::$siteProductsProcess) {
                    if (isset($_GET[$prefix]) && $_GET[$prefix] || strpos($productHtml, 'data-product-id') !== false) {
                        if (isset($_GET[$prefix])) {
                            $productId = $_GET[$prefix];
                        } else {
                            if (preg_match('/data-product-id="([\s\S]+?)"/', $productHtml, $matchesId)) {
                                $productId = $matchesId[1];
                            }
                        }
                        $product = isset($products[$productId]) ? $products[$productId] : array();
                        $productId = isset($product['id']) ? $product['id'] : 0;
                    }
                }
                $params = self::prepareProductParams($productHtml);
                $productsSource = isset($params['source']) && $params['source'] ? $params['source'] : false;
                // if $productsSource == false - get last posts
                if (!self::$siteProductsProcess) {
                    if ($productsSource) {
                        $products = NpAdminActions::getPost($productsSource);
                    } else {
                        $products = NpAdminActions::getPosts($productsSource, 1, 'product');
                    }
                    $params['source'] = ''; //reset source after get product
                }
                if (get_option('np_theme_appearance') === 'plugin-option' && function_exists('wc_get_product') && is_product()) {
                    global $wp_query;
                    $products = isset($wp_query->posts) ? $wp_query->posts : array();
                }

                if (count($products) < 1) {
                    return ''; // remove cell, if post is missing
                }
                if (!self::$siteProductsProcess) {
                    $product = array_shift($products);
                    $productId = $product->ID;
                }

                $productsOptions = array(
                    'productsJson' => self::$productsJson,
                    'paginationProps' => null,
                    'quantityExists' => false,
                    'showSecondImage' => strpos($productHtml, 'u-show-second-image') !== false ? true : false,
                    'siteProductsProcess' => strpos($productHtml, 'data-products-datasource="site"') !== false ? true : false,
                    'productName' => 'product-details',
                    'productId' => $productId,
                    'typeControl' => 'product',
                );
                $params = array_merge($productsOptions, $params);

                if (strpos($productHtml, 'data-products-datasource="site"') !== false) {
                    $processor = new SiteProductDetailsProcessor($productHtml, $params);
                } else {
                    $processor = new WooProductDetailsProcessor($productHtml, $params);
                }

                $processor->build();
                $productHtml = $processor->getResult();
                return $productHtml;
            },
            $content
        );
    }

    /**
     * Prepare product params
     *
     * @param string $productHtml
     *
     * @return array $params
     */
    public static function prepareProductParams($productHtml) {
        $productOptions = array();
        if (preg_match('/<\!--product_options_json--><\!--([\s\S]+?)--><\!--\/product_options_json-->/', $productHtml, $matches)) {
            $productOptions = json_decode($matches[1], true);
            $productHtml = str_replace($matches[0], '', $productHtml);
        }
        $productOptions['entity_type'] = 'product';
        return $productOptions;
    }

    /**
     * Process Categories Control
     *
     * @param string $content Page content
     *
     * @return string|string[]|null
     */
    private static function _processCategoriesControl($content) {
        return preg_replace_callback(
            '/<\!--categories-->([\s\S]+?)<\!--\/categories-->/',
            function ($categoriesMatch) {
                $categoriesHtml = $categoriesMatch[1];
                $cmsTemplate = strpos($categoriesHtml, 'data-categories-type="blog"') !== false ? 'blog' : 'products';
                if ($cmsTemplate === 'blog') {
                    return $categoriesMatch[0];
                }
                if (get_option('np_theme_appearance') === 'plugin-option' && function_exists('wc_get_product') && (is_shop() || is_product_category())) {
                    $categoriesHtml = str_replace('data-products-datasource="site"', 'data-products-datasource="cms"', $categoriesHtml);
                }
                self::$siteProductsProcess = strpos($categoriesHtml, 'data-products-datasource="site"') !== false ? true : false;
                $categories = self::$siteProductsProcess ? (isset(self::$productsJson['categories']) ? self::$productsJson['categories'] : array()) : (is_woocommerce_active() ? get_terms('product_cat', array('hide_empty' => false)) : array());
                if ($categories && count($categories) > 0) {
                    $categoriesHtmlCopy = $categoriesHtml;
                    $categoriesItem = self::extractCategoryItem($categoriesHtmlCopy);
                    $categoriesHtml = preg_replace('/<ul[^>]*>[\s\S]*<\/ul>/', '<ul class="u-unstyled">{categories}</ul>', $categoriesHtml, 1);
                    $type = strpos($categoriesHtml, 'u-categories-horizontal') !== false ? 'horizontal' : 'vertical';
                    $args = array(
                        'template' => $categoriesHtml,
                        'itemTemplate' => $categoriesItem,
                        'type' => $type,
                        'cmsTemplate' => $cmsTemplate,
                        'isNpProducts' => self::$siteProductsProcess,
                    );
                    $categoriesHtml = self::getCategoriesHtml($args);
                }
                self::$siteProductsProcess = false;
                return $categoriesHtml;
            },
            $content
        );
    }

    /**
     * Get categories html
     *
     * @param array $args
     *
     * @return string $categories_html
     */
    public static function getCategoriesHtml($args) {
        if ($args['isNpProducts']) {
            return self::getNpShopCategoriesHtml($args);
        }
        $categories_html = '';
        $category = get_queried_object();
        $category_id = isset($category->term_id) ? $category->term_id : 0;
        $showIcon = 'fill-opacity="1"';
        $hideIcon = 'fill-opacity="0"';
        $linkTitle = '{content}';
        $linkUrl = '{url}';
        $isActiveLi = '{activeLi}';
        $isActiveLink = '{activeLink}';
        $iconOpen = '#icon-categories-open';
        $iconClosed = '#icon-categories-closed';
        $liOpen = 'u-expand-open';
        $liClosed = 'u-expand-closed';
        $categoryCheck = $args['cmsTemplate'] === 'blog' ? is_category() : (is_woocommerce_active() ? is_product_category() : is_category());
        if ($category_id) {
            if ($categoryCheck) {
                // add back link
                if ($category->parent) {
                    // parent cat url
                    $blogCatUrl = esc_url(get_category_link($category->parent));
                    $shopCatUrl = is_woocommerce_active() ? get_term_link($category->parent, 'product_cat') : $blogCatUrl;
                    $linkHref = $args['cmsTemplate'] === 'blog' ? $blogCatUrl : $shopCatUrl;
                } else {
                    $blogUrl = NpMultiLanguagesResolver::get_blog_archive_url();
                    $shopUrl = is_woocommerce_active() ? get_permalink(wc_get_page_id('shop')) : $blogUrl;
                    $linkHref = $args['cmsTemplate'] === 'blog' ? $blogUrl : $shopUrl;
                }
                $backIcon = $args['type'] === 'horizontal' ? '&#10094; &nbsp;' : '';
                $categories_html .= str_replace(
                    array($linkTitle, $linkUrl, $isActiveLi, $isActiveLink, $showIcon),
                    array($backIcon . __('Back', 'nicepage'), $linkHref, '', '', $hideIcon),
                    $args['itemTemplate']
                );
            }
            $subCats_html = self::getSubCatHtml($category_id, $args);
            // add current cat link
            $needShowIcon = self::isCategoryHasChild($category);
            $catName = esc_attr(np_get_translation_term($category));
            $catLink = esc_url(get_term_link($category));
            $categories_html .= str_replace(
                array($linkTitle, $linkUrl, $isActiveLi, $isActiveLink, $iconOpen, $liOpen, $showIcon, '</li>'),
                array($catName, $catLink, 'u-active', 'active', $iconClosed, $liClosed, $needShowIcon,  $subCats_html . '</li>'),
                $args['itemTemplate']
            );
            if ($args['type'] === 'horizontal') {
                $categories_html .= self::getSubCatHtml($category_id, $args, true);
            }
        } else {
            $template_cats = self::npGetCategories($category_id, $args['cmsTemplate']);
            if ($template_cats) {
                foreach ($template_cats as $template_category) {
                    $needShowIcon = self::isCategoryHasChild($template_category);
                    $catName = esc_attr(np_get_translation_term($template_category));
                    $catLink = esc_url(get_term_link($template_category));
                    if ($needShowIcon === $showIcon) {
                        $subCats_html = self::getSubCatHtml($template_category->term_id, $args);
                    } else {
                        $subCats_html = '';
                    }
                    $categories_html .= str_replace(
                        array($linkTitle, $linkUrl, $isActiveLi, $isActiveLink, $showIcon, $iconOpen, $liOpen, 'u-expand-leaf', '</li>'),
                        array($catName, $catLink, '', '', $needShowIcon, $iconClosed, $liClosed, 'u-expand-closed', $subCats_html . '</li>'),
                        $args['itemTemplate']
                    );
                }
            }
        }
        $categories_html = strtr($args['template'], array('{categories}' => $categories_html));
        return $categories_html;
    }

    /**
     * Get categories html for np shop
     *
     * @param array $args
     *
     * @return string
     */
    public static function getNpShopCategoriesHtml($args) {
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
        $template_cats = self::npGetNpCategories();
        $source = NpMultiLanguagesResolver::get_np_route_source('plugin');
        array_unshift(
            $template_cats,
            array(
                'id'    => '',
                'title' => 'All',
                'link'  => NpMultiLanguagesResolver::build_np_route_url('products', '', $source),
            )
        );
        if ($template_cats) {
            foreach ($template_cats as $template_category) {
                $needShowIcon = is_np_category_has_child($template_category);
                if ($needShowIcon === $showIcon) {
                    $childs = self::getChildCategories($template_category['id'], $template_cats);
                    $subCats_html = self::getShopSubCatHtml($childs, $args);
                } else {
                    $subCats_html = '';
                }
                $categories_html .= str_replace(
                    array($linkTitle, $linkUrl, $isActiveLi, $isActiveLink, $showIcon, $iconOpen, $liOpen, 'u-expand-leaf', '</li>'),
                    array(
                        $template_category['title'],
                        NpMultiLanguagesResolver::build_np_products_category_url($template_category['id'], $source),
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

    /**
     * Get np products categories tree
     *
     * @return array $result
     */
    public static function npGetNpCategories() {
        $allCategories = self::npGetAllCategories();
        $result = self::buildCategoryTree($allCategories);
        return $result;
    }

    /**
     * Get np products categories from products json
     *
     * @return array $categories
     */
    public static function npGetAllCategories() {
        $data = getProductsJson();
        return isset($data['categories']) ? $data['categories'] : array();
    }

    /**
     * Build categories tree
     *
     * @param $categories
     * @param null $parentId
     *
     * @return array
     */
    public static function buildCategoryTree($categories, $parentId = null) {
        $categoryTree = array();
        foreach ($categories as $category) {
            if ($category['categoryId'] == $parentId) {
                $children = self::buildCategoryTree($categories, $category['id']);
                if ($children) {
                    $category['items'] = $children;
                }
                $categoryTree[] = $category;
            }
        }
        return $categoryTree;
    }

    /**
     * Get child categories
     *
     * @param $parentCategoryId
     * @param $categories
     *
     * @return array|mixed
     */
    public static function getChildCategories($parentCategoryId, $categories) {
        $result = array();
        foreach ($categories as $category) {
            if ($category['id'] == $parentCategoryId) {
                $result = isset($category['items']) ? $category['items'] : array();
            }
        }
        return $result;
    }

    /**
     * Get shop sub cat html
     *
     * @param $categories
     * @param $args
     * @param false $onlyItems
     *
     * @return string
     */
    public static function getShopSubCatHtml($categories, $args, $onlyItems=false) {
        $output = "";
        if (empty($categories)) {
            return $output;
        }
        if ($args['type'] === 'vertical') {
            $source = NpMultiLanguagesResolver::get_np_route_source('plugin');
            foreach ($categories as $category) {
                $args['itemTemplate'] = str_replace('</li>', '', $args['itemTemplate']);
                $output .= str_replace(
                    array('{content}', '{url}', '{activeLi}', '{activeLink}', 'u-root'),
                    array(
                        $category['title'],
                        NpMultiLanguagesResolver::build_np_products_category_url($category['id'], $source),
                        '',
                        ''
                    ),
                    $args['itemTemplate']
                );
                if (!empty($category['items'])) {
                    $output = str_replace('u-expand-leaf', 'u-expand-closed', $output);
                    $output .= self::getShopSubCatHtml($category['items'], $args);
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

    /**
     * Extract categories item from categories html
     *
     * @param string $categoriesHtml
     *
     * @return string
     */
    public static function extractCategoryItem($categoriesHtml) {
        if (preg_match('/<!--categories_item0-->([\s\S]*?)<!--\/categories_item0-->/s', $categoriesHtml, $matches)) {
            $item = isset($matches[1]) ? $matches[1] : '';
            if ($item) {
                $item = preg_replace('/<ul[^>]*>[\s\S]*<\/ul>/s', '', $item);
                $item = preg_replace('/(<div[^>]*class=["\'][^"\']*)["\']/', '$1 {activeLi}"', $item);
                $item = preg_replace('/(<a[^>]*class=["\'][^"\']*)["\']/', '$1 {activeLink}"', $item);
                $item = preg_replace('/(?<!xlink:)\bhref=["\'][^"\']*["\']/', 'href="{url}"', $item);
                $item = preg_replace_callback(
                    '/(<a[^>]*>)(.*?)(<\/a>)/s', function ($matches) {
                        $before = str_replace('<a', '<a style="outline:none;"', $matches[1]);
                        $content = $matches[2];
                        $after = $matches[3];
                        if (preg_match('/(<svg.*?<\/svg>)(.*)/s', $content, $contentMatches)) {
                            return $before . '<span class="u-icon" style="padding-right: 5px">' . $contentMatches[1] . '</span>' . '{content}' . $after;
                        }
                        return $before . '{content}' . $after;
                    },
                    $item
                );
            }
            return $item;
        }
        return '';
    }

    /**
     * Get sub category html
     *
     * @param int   $catId
     * @param array $args
     * @param bool  $onlyItems
     *
     * @return string
     */
    public static function getSubCatHtml($catId, $args, $onlyItems=false) {
        $subCats_html = '';
        $sub_cats = self::npGetCategories($catId, $args['cmsTemplate']);
        if (count($sub_cats) > 0) {
            foreach ($sub_cats as $sub_category) {
                $subCats_html .= str_replace(
                    array('{content}', '{url}', '{activeLi}', '{activeLink}', 'fill-opacity="1"', 'u-root'),
                    array(esc_attr(np_get_translation_term($sub_category)), esc_url(get_term_link($sub_category)), '', '', 'fill-opacity="0"', ''),
                    $args['itemTemplate']
                );
            }
            if (!$onlyItems) {
                $subCats_html = '<ul class="u-unstyled">' . $subCats_html . '</ul>';
            }
        }
        return $subCats_html;
    }

    /**
     * Check has category child and return style
     *
     * @param object $category
     *
     * @return string
     */
    public static function isCategoryHasChild($category) {
        $term_childs = get_term_children($category->term_id, $category->taxonomy);
        return count($term_childs) > 0 ? 'fill-opacity="1"' : 'fill-opacity="0"';
    }

    /**
     * Get posts categories
     *
     * @param int    $category_id
     * @param string $cmsTemplate
     *
     * @return object
     */
    public static function npGetCategories($category_id, $cmsTemplate='blog') {
        $sub_args = array(
            'orderby'      => 'id',
            'taxonomy'     => $cmsTemplate === 'blog' ? 'category' : 'product_cat',
            'parent'       => $category_id,
            'child_of'     => 0,
            'show_count'   => 0,
            'pad_counts'   => 0,
            'hierarchical' => 0,
            'title_li'     => '',
            'hide_empty'   => 1
        );
        return get_categories($sub_args);
    }

    /**
     * Process cart for WooCommerce
     *
     * @param string $content
     *
     * @return string $content
     */
    private static function _processCartControl($content) {
        $content = preg_replace_callback(
            '/<\!--shopping_cart-->([\s\S]+?)<\!--\/shopping_cart-->/',
            function ($shoppingCartMatch) {
                $shoppingCartHtml = $shoppingCartMatch[1];

                if (!function_exists('WC') || !isset(WC()->cart)) {
                    return $shoppingCartHtml;
                }

                $shoppingCartHtml = self::_replace_cart_url($shoppingCartHtml);
                $shoppingCartHtml = self::_replace_cart_count($shoppingCartHtml);
                $script = <<<SCRIPT
<script type="text/javascript">
        if (window.sessionStorage) {
            window.sessionStorage.setItem('wc_cart_created', '');
        }
        (function () {
            if (window.npShoppingCartIconPreserver) {
                return;
            }
            window.npShoppingCartIconPreserver = true;
            var fallbackCartHtml = [];
            function hasClass(element, className) {
                return element && element.className && (' ' + element.className + ' ').indexOf(' ' + className + ' ') !== -1;
            }
            function getCartOwner(widget) {
                var node = widget ? widget.parentNode : null;
                while (node && node.nodeType === 1) {
                    if (hasClass(node, 'u-shopping-cart')) {
                        return node;
                    }
                    node = node.parentNode;
                }
                return widget ? widget.parentNode : null;
            }
            function getCartWidgets() {
                return document.querySelectorAll('.u-shopping-cart .widget_shopping_cart_content');
            }
            function getCartCount(widget) {
                var count = widget.querySelector('.u-shopping-cart-count');
                if (count) {
                    return count.innerHTML;
                }
                var quantities = widget.querySelectorAll('.woocommerce-mini-cart-item .quantity');
                if (!quantities.length) {
                    return widget.querySelector('.woocommerce-mini-cart__empty-message') ? '0' : null;
                }
                var total = 0;
                for (var i = 0; i < quantities.length; i++) {
                    var match = quantities[i].textContent.match(/\d+/);
                    total += match ? parseInt(match[0], 10) : 0;
                }
                return String(total);
            }
            function setCartCount(widget, value) {
                var count = widget.querySelector('.u-shopping-cart-count');
                if (count && value !== null) {
                    count.innerHTML = value;
                }
            }
            function rememberCarts() {
                var widgets = getCartWidgets();
                for (var i = 0; i < widgets.length; i++) {
                    if (!widgets[i].innerHTML) {
                        continue;
                    }
                    var owner = getCartOwner(widgets[i]);
                    if (owner && !owner.__npShoppingCartHtml) {
                        owner.__npShoppingCartHtml = widgets[i].innerHTML;
                    }
                    if (!fallbackCartHtml[i]) {
                        fallbackCartHtml[i] = widgets[i].innerHTML;
                    }
                }
            }
            function restoreCarts() {
                var widgets = getCartWidgets();
                for (var i = 0; i < widgets.length; i++) {
                    var owner = getCartOwner(widgets[i]);
                    var cartHtml = owner && owner.__npShoppingCartHtml ? owner.__npShoppingCartHtml : fallbackCartHtml[i];
                    if (!cartHtml) {
                        continue;
                    }
                    var count = getCartCount(widgets[i]);
                    if (!widgets[i].querySelector('.u-shopping-cart-icon') || widgets[i].innerHTML !== cartHtml) {
                        widgets[i].innerHTML = cartHtml;
                    }
                    setCartCount(widgets[i], count);
                }
            }
            function syncCarts() {
                rememberCarts();
                restoreCarts();
            }
            function bindCartEvents() {
                rememberCarts();
                window.setTimeout(syncCarts, 0);
                window.setTimeout(syncCarts, 500);
                window.setTimeout(syncCarts, 1500);
                if (window.jQuery) {
                    window.jQuery(document.body)
                        .on('wc_fragment_refresh', rememberCarts)
                        .on('wc_fragments_loaded wc_fragments_refreshed added_to_cart removed_from_cart', restoreCarts);
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindCartEvents);
            } else {
                bindCartEvents();
            }
        })();
    </script>
SCRIPT;

                $cartParentOpen = '<div>';
                if (preg_match('/<a[\s\S]+?class=[\'"]([\s\S]+?)[\'"]/', $shoppingCartHtml, $matches)) {
                    $cartParentOpen = '<div class="' . $matches[1] . '">';
                    $shoppingCartHtml = str_replace($matches[1], '', $shoppingCartHtml);
                }
                $cart_open = '<div class="widget_shopping_cart_content">';
                $cart_close = '</div>';
                return $script . $cartParentOpen . $cart_open . $shoppingCartHtml . $cart_close . '</div>';
            },
            $content
        );
        return $content;
    }

    /**
     * Replace shipping cart url
     *
     * @param string $content
     *
     * @return string $content
     */
    private static function _replace_cart_url($content) {
        return preg_replace('/(\s+href=[\'"])([\s\S]+?)([\'"])/', '$1' . wc_get_cart_url() . '$3', $content);
    }

    /**
     * Replace shipping cart count
     *
     * @param string $content
     *
     * @return string $content
     */
    private static function _replace_cart_count($content) {
        return preg_replace_callback(
            '/<\!--shopping_cart_count-->([\s\S]+?)<\!--\/shopping_cart_count-->/',
            function () {
                $count = WC()->cart->get_cart_contents_count();
                return isset($count) ? $count : 0;
            },
            $content
        );
    }
}

class NpBlogPostDataReplacer {

    public static $_post;
    public static $_posts;
    public static $_postId = 0;
    public static $_postType = 'full';
    public static $_blogPostTemplateFromPlugin = false;
    public static $_blogPostTemplateData;
    public static $_activeSorting = null;

    /**
     * NpBlogPostDataReplacer process.
     *
     * @param string $content
     *
     * @return string $content
     */
    public static function process($content) {
        $activeSorting = null;
        if (self::_isPluginTemplateBlogContext()) {
            global $wp_query;
            $isSortingContext = function_exists('np_get_sorting_template_type')
                ? np_get_sorting_template_type($wp_query)
                : !(function_exists('wc_get_product') && (is_shop() || is_product_category()));
            $sortingContent = $isSortingContext ? $content : '';
            $activeSorting = self::$_activeSorting !== null ? self::$_activeSorting : self::resolveBlogSorting($sortingContent);
        }
        $content = self::_processBlogControl($content, $activeSorting);
        $content = self::_processPostControl($content);
        $content = self::_processCategoriesControl($content);
        return $content;
    }

    /**
     * Replace categories control for blog
     *
     * @param string $content
     *
     * @return string
     */
    private static function _processCategoriesControl($content) {
        return preg_replace_callback(
            '/<\!--categories-->([\s\S]+?)<\!--\/categories-->/',
            function ($categoriesMatch) {
                $categoriesHtml = $categoriesMatch[1];
                $cmsTemplate = strpos($categoriesHtml, 'data-categories-type="blog"') !== false ? 'blog' : 'products';
                if ($cmsTemplate === 'products') {
                    return $categoriesMatch[0];
                }
                $categories = get_terms(
                    array(
                        'taxonomy' => 'category',
                        'hide_empty' => false,
                    )
                );
                if ($categories && count($categories) > 0) {
                    $categoriesHtmlCopy = $categoriesHtml;
                    $categoriesItem = NpShopDataReplacer::extractCategoryItem($categoriesHtmlCopy);
                    $categoriesHtml = preg_replace('/<ul[^>]*>[\s\S]*<\/ul>/', '<ul class="u-unstyled">{categories}</ul>', $categoriesHtml, 1);
                    $type = strpos($categoriesHtml, 'u-categories-horizontal') !== false ? 'horizontal' : 'vertical';
                    $args = array(
                        'template' => $categoriesHtml,
                        'itemTemplate' => $categoriesItem,
                        'type' => $type,
                        'cmsTemplate' => $cmsTemplate,
                        'isNpProducts' => false,
                    );
                    $categoriesHtml = NpShopDataReplacer::getCategoriesHtml($args);
                }
                return $categoriesHtml;
            },
            $content
        );
    }

    /**
     * Check whether Blog controls use the plugin template main query.
     *
     * @return bool
     */
    private static function _isPluginTemplateBlogContext() {
        return get_option('np_theme_appearance') === 'plugin-option'
            && (is_home() || is_archive() || is_search());
    }

    /**
     * Process blog controls
     *
     * @param string      $content
     * @param string|null $activeSorting
     *
     * @return string $content
     */
    private static function _processBlogControl($content, $activeSorting = null) {
        $content = preg_replace_callback(
            '/<\!--blog-->([\s\S]+?)<\!--\/blog-->/',
            function ($blogMatch) use ($activeSorting) {
                $params = array(
                    'order' => 'DESC',
                    'entity_type' => 'post',
                    'orderby' => 'date',
                );
                $blogHtml = $blogMatch[1];
                $blogSorting = $activeSorting === null ? self::resolveBlogSorting($blogHtml) : $activeSorting;
                // Add params for grid height update
                $blogHtml = preg_replace(
                    '/class=(["\'])([^"\']*)(["\'])/i',
                    'class=$1$2 u-hidden-visibility$3 ' . np_get_grid_hash_key(),
                    $blogHtml,
                    1
                );

                $blogOptions = array();
                if (preg_match('/<\!--blog_options_json--><\!--([\s\S]+?)--><\!--\/blog_options_json-->/', $blogHtml, $matches)) {
                    $blogOptions = json_decode($matches[1], true);
                    $blogHtml = str_replace($matches[0], '', $blogHtml);
                }
                $blogSourceType = isset($blogOptions['type']) ? $blogOptions['type'] : '';
                if ($blogSourceType === 'Tags') {
                    $params['source'] = 'tags:' . (isset($blogOptions['tags']) && $blogOptions['tags'] ? $blogOptions['tags'] : '');
                } else {
                    $params['source'] = isset($blogOptions['source']) && $blogOptions['source'] ? $blogOptions['source'] : false;
                    if (isset($blogOptions['type']) && $blogOptions['type'] === 'Recent') {
                        $params['source'] = false;
                    }
                }
                $site_category_id = isset($_GET['postsCategoryId']) ? $_GET['postsCategoryId'] : 0;
                if ($site_category_id) {
                    $params['id'] = $site_category_id;
                }
                $params['count'] = isset($blogOptions['count']) ? $blogOptions['count'] : '';
                global $blog_control_query;
                if ($activeSorting !== null) {
                    global $wp_query;
                    $posts = isset($wp_query->posts) ? $wp_query->posts : array();
                    $blog_control_query = $wp_query;
                } else {
                    $params = self::_prepareSortingQuery($params, $blogHtml, $blogSorting);
                    // if $params['source'] == false - get last posts in the WP_Query
                    $blog_control_query = getWpQuery($params);
                    $posts = isset($blog_control_query->posts) ? $blog_control_query->posts : array();
                }
                $blogHtml = self::_processPost($blogHtml, 'intro', $blogSorting);

                $blogGridProps = isset($blogOptions['gridProps']) ? $blogOptions['gridProps'] : array();
                $blogHtml .= GridHelper::buildGridAutoRowsStyles($blogGridProps, count($posts));

                if (is_search() && !have_posts()) {
                    return '';
                }
                return $blogHtml;
            },
            $content
        );
        return $content;
    }

    /**
     * Process post control - Full control
     *
     * @param string $content
     *
     * @return string $content
     */
    private static function _processPostControl($content) {
        $content = preg_replace_callback(
            '/<\!--post_details-->([\s\S]+?)<\!--\/post_details-->/',
            function ($postMatch) {
                $postHtml = $postMatch[1];
                $postOptions = array();
                if (preg_match('/<\!--post_details_options_json--><\!--([\s\S]+?)--><\!--\/post_details_options_json-->/', $postHtml, $matches)) {
                    $postOptions = json_decode($matches[1], true);
                    $postHtml = str_replace($matches[0], '', $postHtml);
                }
                $postSource = isset($postOptions['source']) && $postOptions['source'] ? $postOptions['source'] : false;
                NpBlogPostDataReplacer::$_posts = NpAdminActions::getPosts($postSource, 1);
                if (get_option('np_theme_appearance') === 'plugin-option' && is_singular('post')) {
                    global $wp_query;
                    NpBlogPostDataReplacer::$_posts = isset($wp_query->posts) ? $wp_query->posts : array();
                    self::$_blogPostTemplateFromPlugin = true;
                }
                if (count(NpBlogPostDataReplacer::$_posts) < 1) {
                    return ''; // remove cell, if post is missing
                }
                NpBlogPostDataReplacer::$_post = array_shift(NpBlogPostDataReplacer::$_posts);
                NpBlogPostDataReplacer::$_postId = NpBlogPostDataReplacer::$_post->ID;
                if (self::$_blogPostTemplateFromPlugin) {
                    NpBlogPostDataReplacer::$_blogPostTemplateData = new BlogPostTemplateDataProvider(NpBlogPostDataReplacer::$_post);
                }
                $postHtml = self::blogPostProcess($postHtml, 'full');
                if (strpos($postHtml, '[[$]]') !== false) {
                    $postHtml = str_replace('[[$]]', '$', $postHtml);
                }
                return $postHtml;
            },
            $content
        );
        return $content;
    }

    /**
     * Process post controls - Control parts
     *
     * @param string      $content
     * @param string      $type
     * @param string|null $activeSorting
     *
     * @return string $content
     */
    private static function _processPost($content, $type='full', $activeSorting = null) {
        $reBlogPost = '/<\!--blog_post-->([\s\S]+?)<\!--\/blog_post-->/';
        preg_match_all($reBlogPost, $content, $matches, PREG_SET_ORDER);
        $allTemplates = count($matches);
        if ($allTemplates > 0) {
            $allPostsHtml = '';
            global $blog_control_query;
            if ($blog_control_query && method_exists($blog_control_query, 'have_posts')) {
                global $post;
                $current_post = $post;
                $i = 0;
                while($blog_control_query->have_posts()) :
                    $blog_control_query->the_post();
                    if (count($blog_control_query->posts) < 1) {
                        return ''; // remove cell, if post is missing
                    }
                    NpBlogPostDataReplacer::$_post = $blog_control_query->post;
                    $tmplIndex = $i % $allTemplates;
                    $postHtml = $matches[$tmplIndex][0];
                    if ($postHtml && strpos($postHtml, 'u-shortcode') !== false) {
                        $postHtml = do_shortcode($postHtml);
                    }
                    if (get_option('np_theme_appearance') === 'plugin-option' && (is_home() || is_archive() || is_search())) {
                        self::$_blogPostTemplateFromPlugin = true;
                    }
                    NpBlogPostDataReplacer::$_postId = NpBlogPostDataReplacer::$_post->ID;
                    if (self::$_blogPostTemplateFromPlugin) {
                        NpBlogPostDataReplacer::$_blogPostTemplateData = new BlogPostTemplateDataProvider(NpBlogPostDataReplacer::$_post);
                    }
                    $allPostsHtml .= self::blogPostProcess($postHtml, $type);
                    $i++;
                endwhile;
                $post = $current_post;
            }
        }
        $content = preg_replace('/<!--blog_post-->([\s\S]+)<!--\/blog_post-->/', $allPostsHtml, $content);
        $content = BaseController::processPagination($content);
        $content = BaseController::processCategoriesFilter($content, array(), 'blog');
        $content = self::processSorting($content, $activeSorting);
        if (strpos($content, '[[$]]') !== false) {
            $content = str_replace('[[$]]', '$', $content);
        }
        return $content;
    }

    /**
     * Process with post controls for blog control
     *
     * @param string $content
     * @param string $type
     *
     * @return string $content
     */
    public static function blogPostProcess($content, $type='full') {
        NpBlogPostDataReplacer::$_postType = $type;
        global $post, $original_post;
        if (isset($original_post) && $original_post) {
            $post = $original_post;
        }
        $content = preg_replace_callback(
            '/<!--blog_post-->([\s\S]+?)<!--\/blog_post-->/',
            function ($content) {
                $content[1] = self::_replace_blog_post_header($content[1]);
                $content[1] = self::_replace_blog_post_content($content[1]);
                $content[1] = self::_replace_blog_post_image($content[1]);
                $content[1] = self::_replace_blog_post_readmore($content[1]);
                $content[1] = self::_replace_blog_post_metadata($content[1]);
                $content[1] = self::_replace_blog_post_tags($content[1]);
                return $content[1];
            },
            $content
        );
        $original_post = $post;
        return $content;
    }

    /**
     * Replace blog post header
     *
     * @param string $content
     *
     * @return string $content
     */
    private static function _replace_blog_post_header($content) {
        return preg_replace_callback(
            '/<!--blog_post_header-->([\s\S]+?)<!--\/blog_post_header-->/',
            function ($content) {
                $postTitle = self::$_blogPostTemplateFromPlugin ? self::$_blogPostTemplateData->get_title() : NpBlogPostDataReplacer::$_post->post_title;
                if (strpos($postTitle, '$') !== false) {
                    $postTitle = str_replace('$', '[[$]]', $postTitle);
                }
                $postUrl = get_permalink(NpBlogPostDataReplacer::$_postId);
                $postUrl = $postUrl ? $postUrl : '#';
                if ($postUrl) {
                    $content[1] = preg_replace('/href=[\'|"][\s\S]+?[\'|"]/', 'href="' . $postUrl . '"', $content[1]);
                    if (isset($postTitle) && $postTitle != '') {
                        $content[1] = preg_replace('/<!--blog_post_header_content-->([\s\S]+?)<!--\/blog_post_header_content-->/', $postTitle, $content[1]);
                    }
                }
                return $content[1];
            },
            $content
        );
    }

    /**
     * Replace blog post content
     *
     * @param string $content
     *
     * @return string $content
     */
    private static function _replace_blog_post_content($content) {
        return preg_replace_callback(
            '/<!--blog_post_content-->([\s\S]+?)<!--\/blog_post_content-->/',
            function ($content) {
                $postContent = NpBlogPostDataReplacer::$_postType === 'full' ? NpBlogPostDataReplacer::$_post->post_content : plugin_trim_long_str(NpAdminActions::getTheExcerpt(NpBlogPostDataReplacer::$_post->ID), 150);
                $postContent = self::$_blogPostTemplateFromPlugin ? self::$_blogPostTemplateData->get_content(self::$_postType) : $postContent;
                if (strpos($postContent, '$') !== false) {
                    $postContent = str_replace('$', '[[$]]', $postContent);
                }
                return preg_replace('/<!--blog_post_content_content-->([\s\S]+?)<!--\/blog_post_content_content-->/', $postContent, $content[1]);
            },
            $content
        );
    }

    /**
     * Replace blog post image
     *
     * @param string $content
     *
     * @return string $content
     */
    private static function _replace_blog_post_image($content) {
        return preg_replace_callback(
            '/<!--blog_post_image-->([\s\S]+?)<!--\/blog_post_image-->/',
            function ($content) {
                $imageHtml = $content[1];
                $thumb_id = get_post_thumbnail_id(NpBlogPostDataReplacer::$_postId);
                $image_alt = '';
                if ($thumb_id) {
                    $url = get_attached_file($thumb_id);
                    $image_alt = get_post_meta($thumb_id, '_wp_attachment_image_alt', true);
                } else {
                    // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- fallback parsing image from post content
                    preg_match('/<img[\s\S]+?src=[\'"]([\s\S]+?)[\'"] [\s\S]+?>/', NpBlogPostDataReplacer::$_post->post_content, $regexResult);
                    if (count($regexResult) < 1) {
                        return '<div class="none-post-image" style="display: none;"></div>';
                    }
                    $url = $regexResult[1];
                }
                $isBackgroundImage = strpos($imageHtml, '<div') !== false ? true : false;
                $uploads = wp_upload_dir();
                $url = str_replace($uploads['basedir'], $uploads['baseurl'], $url);
                if ($isBackgroundImage) {
                    if (strpos($imageHtml, 'data-bg') !== false) {
                        $imageHtml = preg_replace('/(data-bg=(["\']))(.*?)(\2)/', '$1url(' . $url . ')$4', $imageHtml);
                    } else {
                        if (preg_match('/url\(([\s\S]+?)\)/', $imageHtml, $imageUrl) && isset($imageUrl[1])) {
                            $imageHtml = str_replace($imageUrl[1], $url, $imageHtml);
                        }
                    }
                } else {
                    $imageHtml = preg_replace('/(src=[\'"])([\s\S]+?)([\'"])/', '$1' . $url . '$3', $imageHtml);
                }
                if ($image_alt) {
                    $imageHtml = preg_replace('/(alt=[\'"])([\s\S]*?)([\'"])/', '$1' . $image_alt . '$3', $imageHtml);
                }
                if (isset(NpBlogPostDataReplacer::$_postType) && NpBlogPostDataReplacer::$_postType === 'intro') {
                    preg_match('/class=[\'"]([\s\S]+?)[\'"]/', $imageHtml, $imgClasses);
                    if (strpos($imageHtml, '<img') !== false) {
                        $imgClasses[1] = str_replace('u-preserve-proportions', '', $imgClasses[1]);
                        return '<a class="' . $imgClasses[1] . '" href="' . get_permalink(NpBlogPostDataReplacer::$_postId) . '">' . $imageHtml . '</a>';
                    } else {
                        $imageHtml = str_replace('<div', '<div data-href="' . get_permalink(NpBlogPostDataReplacer::$_postId) . '"', $imageHtml);
                    }
                }
                return $imageHtml;
            },
            $content
        );
    }

    /**
     * Replace blog post readmore
     *
     * @param string $content
     *
     * @return string $content
     */
    private static function _replace_blog_post_readmore($content) {
        return preg_replace_callback(
            '/<!--blog_post_readmore-->([\s\S]+?)<!--\/blog_post_readmore-->/',
            function ($content) {
                $buttonHtml = preg_replace('/href=[\'|"][\s\S]+?[\'|"]/', 'href="' . get_permalink(NpBlogPostDataReplacer::$_postId) . '"', $content[1]);
                $buttonHtml = html_entity_decode($buttonHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                return preg_replace_callback(
                    '/<!--blog_post_readmore_content-->([\s\S]+?)<!--\/blog_post_readmore_content-->/',
                    function ($buttonHtmlMatches) {
                        $text = __('Read More', 'nicepage');
                        if (preg_match('/<\!--options_json--><\!--([\s\S]+?)--><\!--\/options_json-->/', $buttonHtmlMatches[1], $matches)) {
                            $controlOptions = json_decode($matches[1], true);
                            $text = isset($controlOptions['content']) && $controlOptions['content'] ? $controlOptions['content'] : $text;
                        }
                        return $text;
                    },
                    $buttonHtml
                );
            },
            $content
        );
    }

    /**
     * Replace blog post metadata
     *
     * @param string $content
     *
     * @return string $content
     */
    private static function _replace_blog_post_metadata($content) {
        return preg_replace_callback(
            '/<!--blog_post_metadata-->([\s\S]+?)<!--\/blog_post_metadata-->/',
            function ($content) {
                $content[1] = self::_replace_blog_post_metadata_author($content[1]);
                $content[1] = self::_replace_blog_post_metadata_date($content[1]);
                $content[1] = self::_replace_blog_post_metadata_category($content[1]);
                $content[1] = self::_replace_blog_post_metadata_comments($content[1]);
                $content[1] = self::_replace_blog_post_metadata_edit($content[1]);
                return $content[1];
            },
            $content
        );
    }

    /**
     * Replace blog post metadata author
     *
     * @param string $content
     *
     * @return string $content
     */
    private static function _replace_blog_post_metadata_author($content) {
        return preg_replace_callback(
            '/<!--blog_post_metadata_author-->([\s\S]+?)<!--\/blog_post_metadata_author-->/',
            function ($content) {
                $authorId = NpBlogPostDataReplacer::$_post->post_author;
                $authorName = get_the_author_meta('display_name', $authorId);
                $authorLink = get_author_posts_url($authorId);
                if ($authorName == '') {
                    $authorName = 'User';
                    $authorLink = '#';
                }
                // translators: %s is the author name.
                $link = '<a class="url u-textlink" href="' . $authorLink . '" title="' . esc_attr(sprintf(__('View all posts by %s', 'nicepage'), $authorName)) . '"><span class="fn n">' . $authorName . '</span></a>';
                return $content[1] = preg_replace('/<!--blog_post_metadata_author_content-->([\s\S]+?)<!--\/blog_post_metadata_author_content-->/', $link, $content[1]);
            },
            $content
        );
    }

    /**
     * Replace blog post metadata date
     *
     * @param string $content
     *
     * @return string $content
     */
    private static function _replace_blog_post_metadata_date($content) {
        return preg_replace_callback(
            '/<!--blog_post_metadata_date-->([\s\S]+?)<!--\/blog_post_metadata_date-->/',
            function ($content) {
                $postDate = get_the_date('', NpBlogPostDataReplacer::$_postId);
                return $content[1] = preg_replace('/<!--blog_post_metadata_date_content-->([\s\S]+?)<!--\/blog_post_metadata_date_content-->/', $postDate, $content[1]);
            },
            $content
        );
    }

    /**
     * Replace blog post metadata category
     *
     * @param string $content
     *
     * @return string $content
     */
    private static function _replace_blog_post_metadata_category($content) {
        return preg_replace_callback(
            '/<!--blog_post_metadata_category-->([\s\S]+?)<!--\/blog_post_metadata_category-->/',
            function ($content) {
                $postCategories = str_replace(
                    '<a',
                    '<a class="u-textlink"',
                    get_the_category_list(_x(', ', 'Used between list items, there is a space after the comma.', 'nicepage'), '', NpBlogPostDataReplacer::$_postId)
                );
                return $content[1] = preg_replace('/<!--blog_post_metadata_category_content-->([\s\S]+?)<!--\/blog_post_metadata_category_content-->/', $postCategories, $content[1]);
            },
            $content
        );
    }

    /**
     * Replace blog post metadata comments
     *
     * @param string $content
     *
     * @return string $content
     */
    private static function _replace_blog_post_metadata_comments($content) {
        return preg_replace_callback(
            '/<!--blog_post_metadata_comments-->([\s\S]+?)<!--\/blog_post_metadata_comments-->/',
            function ($content) {
                // translators: %d is the number of comments.
                $link = '<a class="u-textlink" href="' . get_comments_link(NpBlogPostDataReplacer::$_postId) . '">' . sprintf(__('Comments (%d)', 'nicepage'), (int)get_comments_number(NpBlogPostDataReplacer::$_postId)) . '</a>';
                return $content[1] = preg_replace('/<!--blog_post_metadata_comments_content-->([\s\S]+?)<!--\/blog_post_metadata_comments_content-->/', $link, $content[1]);
            },
            $content
        );
    }


    /**
     * Replace blog post metadata edit
     *
     * @param string $content
     *
     * @return string $content
     */
    private static function _replace_blog_post_metadata_edit($content) {
        return preg_replace_callback(
            '/<!--blog_post_metadata_edit-->([\s\S]+?)<!--\/blog_post_metadata_edit-->/',
            function ($content) {
                if (current_user_can('edit_post', get_the_ID())) {
                    $link = '<a href="' . get_edit_post_link(NpBlogPostDataReplacer::$_postId) . '">'. __('Edit', 'nicepage') . '</a>';
                    return preg_replace('/<!--blog_post_metadata_edit_content-->([\s\S]+?)<!--\/blog_post_metadata_edit_content-->/', $link, $content[1]);
                }
                return '';
            },
            $content
        );
    }

    /**
     * Replace blog post tags
     *
     * @param string $content
     *
     * @return string $content
     */
    private static function _replace_blog_post_tags($content) {
        return preg_replace_callback(
            '/<!--blog_post_tags-->([\s\S]+?)<!--\/blog_post_tags-->/',
            function ($content) {
                $tags = get_the_tag_list('', _x(', ', 'Used between list items, there is a space after the comma.', 'nicepage'), '', NpBlogPostDataReplacer::$_postId);
                $tags = $tags ? $tags : '';
                $content[1] = preg_replace('/<!--blog_post_tags_content-->([\s\S]+?)<!--\/blog_post_tags_content-->/', $tags, $content[1]);
                return $content[1];
            },
            $content
        );
    }

    /**
     * Process sorting for blog controls
     *
     * @param string      $content
     * @param string|null $activeSorting
     *
     * @return string $content
     */
    public static function processSorting($content, $activeSorting = null) {
        if ($activeSorting === null) {
            $activeSorting = self::resolveBlogSorting($content);
        }
        $content = preg_replace_callback(
            '/<\!--blog_sorting-->([\s\S]+?)<\!--\/blog_sorting-->/',
            function ($sortingMatch) use ($activeSorting) {
                $sortingHtml = $sortingMatch[1];
                $sortingHtml = str_replace('<select', '<select onchange="window.location.href = this.value;" aria-label="Sort items by"', $sortingHtml);
                preg_match('/<option[\s\S]*?>[\s\S]+?<\/option>/', $sortingHtml, $sortingOptions);
                $firstOptionHtml = isset($sortingOptions[0]) ? $sortingOptions[0] : '';
                $sortingHtml = preg_replace('/<option[\s\S]*?>[\s\S]*<\/option>/', '{sortingOptions}', $sortingHtml);
                $sorting_options = array(
                    'name_asc'  => __('Name, A to Z', 'nicepage'),
                    'name_desc' => __('Name, Z to A', 'nicepage'),
                    'date_asc'  => __('Date, old to new', 'nicepage'),
                    'date_desc' => __('Date, new to old', 'nicepage'),
                );
                $sortingOptionsHtml = '';

                foreach ($sorting_options as $name => $sorting_option) {
                    $doubleOptionHtml = $firstOptionHtml;
                    $doubleOptionHtml = preg_replace('/value=[\'"][\s\S]*?[\'"]/', 'value="' . esc_attr(add_query_arg('postsSorting', $name, self::get_current_url_without_get_params(array('postsSorting', 'paged')))) . '"', $doubleOptionHtml);
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
     * Get sorting from request or blog control settings
     *
     * @param string $content
     *
     * @return string
     */
    public static function resolveBlogSorting($content = '') {
        $supportedSorting = array('name_asc', 'name_desc', 'date_asc', 'date_desc');
        $hasRequestedSorting = isset($_GET['postsSorting']) && $_GET['postsSorting'] !== '';
        if ($hasRequestedSorting) {
            $sorting = sanitize_text_field($_GET['postsSorting']);
            return in_array($sorting, $supportedSorting, true) ? $sorting : '';
        }

        $prop = false;
        $order = false;
        $tagPattern = '/<[a-z][a-z0-9:-]*\b(?:[^>"\']++|"[^"]*"|\'[^\']*\')*>/i';
        $offset = 0;
        while (preg_match($tagPattern, $content, $tag, PREG_OFFSET_CAPTURE, $offset)) {
            $tagStr = $tag[0][0];
            $offset = $tag[0][1] + strlen($tagStr);
            if (!preg_match('/\sclass\s*=\s*(["\'])([\s\S]*?)\1/i', $tagStr, $classMatch)
                || !preg_match('/(?:^|\s)u-blog(?:\s|$)/', $classMatch[2])
            ) {
                continue;
            }
            preg_match('/\sdata-site-sorting-prop\s*=\s*(["\'])(title|created)\1/i', $tagStr, $propMatch);
            preg_match('/\sdata-site-sorting-order\s*=\s*(["\'])(asc|desc)\1/i', $tagStr, $orderMatch);
            $prop = isset($propMatch[2]) ? $propMatch[2] : false;
            $order = isset($orderMatch[2]) ? $orderMatch[2] : false;
            break;
        }
        $defaultMap = array(
            'title-asc'    => 'name_asc',
            'title-desc'   => 'name_desc',
            'created-asc'  => 'date_asc',
            'created-desc' => 'date_desc',
        );
        $key = $prop && $order ? $prop . '-' . $order : false;
        return isset($defaultMap[$key]) ? $defaultMap[$key] : '';
    }

    /**
     * Get current url without GET params
     *
     * @param array $params
     *
     * @return string
     */
    public static function get_current_url_without_get_params($params) {
        $current_url = esc_url($_SERVER['REQUEST_URI']);
        $parsed_url = wp_parse_url($current_url);
        $query_params = array();
        if (isset($parsed_url['query']) && $parsed_url['query'] !== '') {
            parse_str($parsed_url['query'], $query_params);
        }
        foreach ($params as $param) {
            unset($query_params[$param]);
        }
        $updated_query = http_build_query($query_params);
        return $parsed_url['path'] . ($updated_query ? '?' . $updated_query : '');
    }

    /**
     * Add to WP_QUERY $params sorting types
     *
     * @param array       $params
     * @param string      $content
     * @param string|null $sorting
     *
     * @return array $params
     */
    public static function _prepareSortingQuery($params, $content = '', $sorting = null)
    {
        if ($sorting === null) {
            $sorting = self::resolveBlogSorting($content);
        }
        if ($sorting) {
            switch ($sorting) {
            case 'name_asc':
                $params['orderby'] = 'title';
                $params['order'] = 'ASC';
                break;
            case 'name_desc':
                $params['orderby'] = 'title';
                $params['order'] = 'DESC';
                break;
            case 'date_asc':
                $params['orderby'] = 'date';
                $params['order'] = 'ASC';
                break;
            case 'date_desc':
                $params['orderby'] = 'date';
                $params['order'] = 'DESC';
                break;
            }
        }
        return $params;
    }
}
