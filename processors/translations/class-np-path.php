<?php
defined('ABSPATH') or die;

require_once dirname(__FILE__) . '/class-np-url-helper.php';

if (!class_exists('NpPath')) {
    /**
     * Contains low-level path and permalink helpers independent from language ownership.
     * The class reads WordPress/WooCommerce permalink settings, splits and cleans URL paths,
     * and builds route paths used by both theme and plugin multilingual code.
     * Keeping this here prevents path parsing logic from being duplicated in translators.
     */
    class NpPath
    {
        /**
         * Use path-based URLs only for rewrite permalinks.
         *
         * @return bool
         */
        public static function use_path_language_urls()
        {
            return self::get_multilang_url_mode() === 'path' && (bool) get_option('permalink_structure');
        }

        /**
         * Get Nicepage multilingual URL mode.
         *
         * @return string
         */
        public static function get_multilang_url_mode()
        {
            return get_option('np_multilang_url_mode') === 'path' ? 'path' : 'legacy_query';
        }

        /**
         * Update Nicepage multilingual URL mode after permalink settings save.
         *
         * @return void
         */
        public static function update_multilang_url_mode_from_permalinks()
        {
            update_option('np_multilang_url_mode', get_option('permalink_structure') ? 'path' : 'legacy_query', false);
        }

        /**
         * Update multilingual URL mode when the permalink settings page is submitted.
         *
         * @return void
         */
        public static function maybe_update_multilang_url_mode_from_permalink_save()
        {
            global $pagenow;

            if ($pagenow !== 'options-permalink.php' || !isset($_SERVER['REQUEST_METHOD']) || strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
                return;
            }

            add_action('shutdown', array(__CLASS__, 'update_multilang_url_mode_from_permalinks'));
        }

        /**
         * Check if index.php permalinks are enabled.
         *
         * @return bool
         */
        public static function use_index_permalinks()
        {
            return strpos((string) get_option('permalink_structure'), '/index.php/') === 0;
        }

        /**
         * Build permalink-aware blog archive URL.
         *
         * @return string
         */
        public static function get_blog_archive_url()
        {
            if (get_option('show_on_front') === 'posts') {
                return get_home_url();
            }

            if (!self::use_path_language_urls()) {
                return home_url('/?post_type=post');
            }

            $posts_page_id = (int) get_option('page_for_posts');
            if ($posts_page_id > 0) {
                $posts_url = get_permalink($posts_page_id);
                if (is_string($posts_url) && $posts_url !== '') {
                    return $posts_url;
                }
            }

            $blog_page = get_page_by_path('blog');
            if ($blog_page instanceof WP_Post) {
                $blog_url = get_permalink($blog_page->ID);
                if (is_string($blog_url) && $blog_url !== '') {
                    return $blog_url;
                }
            }

            $blog_path = self::use_index_permalinks() ? '/index.php/blog/' : '/blog/';
            return home_url($blog_path);
        }

        /**
         * Resolve synthetic blog archive path.
         *
         * @return string
         */
        public static function get_default_blog_archive_path()
        {
            return 'blog';
        }

        /**
         * Resolve blog archive path independently from permalink mode.
         *
         * @return string
         */
        public static function get_blog_archive_rewrite_path()
        {
            if (get_option('show_on_front') === 'posts') {
                return '';
            }

            $posts_page_id = (int) get_option('page_for_posts');
            if ($posts_page_id > 0) {
                $posts_path = get_page_uri($posts_page_id);
                if (is_string($posts_path) && $posts_path !== '') {
                    return trim($posts_path, '/');
                }

                $posts_page = get_post($posts_page_id);
                if ($posts_page instanceof WP_Post && !empty($posts_page->post_name)) {
                    return trim($posts_page->post_name, '/');
                }
            }

            $blog_page = get_page_by_path('blog');
            if ($blog_page instanceof WP_Post) {
                $blog_path = get_page_uri($blog_page->ID);
                if (is_string($blog_path) && $blog_path !== '') {
                    return trim($blog_path, '/');
                }

                if (!empty($blog_page->post_name)) {
                    return trim($blog_page->post_name, '/');
                }
            }

            return self::get_default_blog_archive_path();
        }

        /**
         * Build permalink-aware URL for Nicepage products routes.
         *
         * @param string     $route  products|product|thankYou
         * @param string|int $value
         * @param string     $source theme|plugin
         *
         * @return string
         */
        public static function build_np_route_url($route, $value = '', $source = 'theme')
        {
            $route = (string) $route;
            $source = $source === 'plugin' ? 'plugin' : 'theme';

            $query_key_map = array(
                'products' => $source === 'plugin' ? 'productsList' : 'products-list',
                'product' => $source === 'plugin' ? 'productId' : 'product-id',
                'thankYou' => $source === 'plugin' ? 'thankYou' : 'thank-you',
            );

            if (!isset($query_key_map[$route])) {
                return home_url('/');
            }

            if (!self::use_path_language_urls()) {
                return add_query_arg(array($query_key_map[$route] => (string) $value), home_url('/'));
            }

            $slug_map = array(
                'products' => $source === 'plugin' ? 'productsList' : 'products-list',
                'product' => $source === 'plugin' ? 'productId' : 'product-id',
                'thankYou' => $source === 'plugin' ? 'thankYou' : 'thank-you',
            );

            $path = '/' . $slug_map[$route] . '/';
            if ($value !== '' && $value !== null) {
                $path .= rawurlencode((string) $value) . '/';
            }
            if (self::use_index_permalinks()) {
                $path = '/index.php/' . trim($path, '/') . '/';
            }

            return home_url($path);
        }

        /**
         * Build category hash link for Nicepage products list.
         *
         * @param string|int $category_id
         * @param string     $source
         *
         * @return string
         */
        public static function build_np_products_category_url($category_id, $source = 'theme')
        {
            return self::build_np_route_url('products', '', $source) . '#/1///' . rawurlencode((string) $category_id);
        }

        /**
         * Resolve the Woo shop archive path independently from permalink mode.
         *
         * @return string
         */
        public static function get_product_archive_rewrite_path()
        {
            $shop_page_id = 0;
            if (function_exists('wc_get_page_id')) {
                $shop_page_id = (int) wc_get_page_id('shop');
            }

            if (!$shop_page_id) {
                $shop_page_id = (int) get_option('woocommerce_shop_page_id');
            }

            if ($shop_page_id > 0) {
                $shop_path = get_page_uri($shop_page_id);
                if (is_string($shop_path) && $shop_path !== '') {
                    return trim($shop_path, '/');
                }

                $shop_post = get_post($shop_page_id);
                if ($shop_post instanceof WP_Post && !empty($shop_post->post_name)) {
                    return trim($shop_post->post_name, '/');
                }
            }

            if (post_type_exists('product')) {
                $product_type = get_post_type_object('product');
                if ($product_type) {
                    if (is_string($product_type->has_archive) && $product_type->has_archive !== '') {
                        return trim($product_type->has_archive, '/');
                    }

                    if ($product_type->has_archive && !empty($product_type->rewrite['slug'])) {
                        return trim($product_type->rewrite['slug'], '/');
                    }
                }
            }

            return '';
        }

        /**
         * Resolve the Woo single-product permalink base.
         *
         * @return string
         */
        public static function get_product_permalink_rewrite_path()
        {
            $base = '';
            $permalinks = get_option('woocommerce_permalinks');

            if (is_array($permalinks) && !empty($permalinks['product_base'])) {
                $base = trim((string) $permalinks['product_base'], '/');
            }

            if ($base === '') {
                $base = 'product';
            }

            return trim($base, '/');
        }

        /**
         * Resolve the Woo single-product permalink base as a rewrite pattern.
         *
         * @return string
         */
        public static function get_product_permalink_rewrite_pattern()
        {
            $base = self::get_product_permalink_rewrite_path();
            if (!self::has_static_permalink_segment($base)) {
                return '';
            }

            return self::build_permalink_base_pattern($base, array('product_cat'));
        }

        /**
         * Convert a permalink base with %tokens% to a regex-safe rewrite pattern.
         *
         * @param string $base
         * @param array  $multi_segment_tags
         *
         * @return string
         */
        private static function build_permalink_base_pattern($base, $multi_segment_tags = array())
        {
            $pattern = preg_quote(trim((string) $base, '/'), '/');
            foreach ($multi_segment_tags as $tag) {
                $pattern = str_replace('%' . $tag . '%', '(?:.+?)', $pattern);
            }

            return preg_replace('/%[^%]+%/', '(?:[^/]+)', $pattern);
        }

        /**
         * Check if permalink base has a fixed segment to keep top rewrite rules narrow.
         *
         * @param string $base
         *
         * @return bool
         */
        private static function has_static_permalink_segment($base)
        {
            $segments = self::get_path_segments($base);
            foreach ($segments as $segment) {
                if (strpos($segment, '%') === false) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Resolve the Woo product-category permalink base.
         *
         * @return string
         */
        public static function get_product_category_rewrite_path()
        {
            $base = '';
            $permalinks = get_option('woocommerce_permalinks');

            if (is_array($permalinks) && !empty($permalinks['category_base'])) {
                $base = trim((string) $permalinks['category_base'], '/');
            }

            if ($base === '' && taxonomy_exists('product_cat')) {
                $taxonomy = get_taxonomy('product_cat');
                if ($taxonomy && is_array($taxonomy->rewrite) && !empty($taxonomy->rewrite['slug'])) {
                    $base = trim((string) $taxonomy->rewrite['slug'], '/');
                }
            }

            if ($base === '') {
                $base = 'product-category';
            }

            return trim($base, '/');
        }

        /**
         * Split path into segments.
         *
         * @param string $path
         *
         * @return array
         */
        public static function get_path_segments($path)
        {
            $path = trim((string) $path, '/');
            if ($path === '') {
                return array();
            }

            return array_values(array_filter(explode('/', $path), 'strlen'));
        }

        /**
         * Remove trailing /lang/{code}/ from path.
         *
         * @param string $path
         *
         * @return string
         */
        public static function remove_language_from_path($path)
        {
            $segments = self::get_path_segments($path);
            if (!$segments) {
                return '/';
            }

            $clean_segments = array();
            $count = count($segments);
            for ($i = 0; $i < $count; $i++) {
                if (strtolower($segments[$i]) === 'lang' && $i + 1 < $count) {
                    $i++;
                    continue;
                }
                $clean_segments[] = $segments[$i];
            }

            return $clean_segments ? '/' . implode('/', $clean_segments) . '/' : '/';
        }

        /**
         * Normalize path for the current permalink mode.
         *
         * @param string $path
         *
         * @return string
         */
        public static function normalize_path_for_permalinks($path)
        {
            $path = self::remove_language_from_path($path);

            if (!self::use_index_permalinks()) {
                return $path;
            }

            $home_parts = wp_parse_url(home_url('/'));
            $home_path = isset($home_parts['path']) ? rtrim($home_parts['path'], '/') : '';
            $relative_path = $path;

            if ($home_path !== '' && strpos($path, $home_path) === 0) {
                $relative_path = substr($path, strlen($home_path));
            }

            $relative_path = trim((string) $relative_path, '/');
            if ($relative_path === 'index.php' || strpos($relative_path, 'index.php/') === 0) {
                return $path;
            }

            $base_path = ($home_path ? $home_path : '') . '/index.php';
            return $relative_path === '' ? $base_path . '/' : $base_path . '/' . $relative_path . '/';
        }

        /**
         * Build query string with compatibility for older PHP versions.
         *
         * @param array $query
         *
         * @return string
         */
        public static function build_query_string($query)
        {
            if (!$query) {
                return '';
            }

            if (defined('PHP_QUERY_RFC3986')) {
                return http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            }

            return http_build_query($query, '', '&');
        }
    }
}
