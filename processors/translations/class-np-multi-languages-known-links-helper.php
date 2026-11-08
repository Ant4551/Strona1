<?php
defined('ABSPATH') or die;

if (!class_exists('NpMultiLanguagesKnownLinksHelper')) {
    /**
     * Updates stored Nicepage links after the WordPress permalink mode changes.
     * Editor HTML and saved menu items can keep old same-site query/path URLs until
     * a user opens the page or saves the menu again. This helper rebuilds only links
     * that can be resolved to WordPress or Nicepage routes and leaves unknown/external
     * URLs unchanged.
     */
    class NpMultiLanguagesKnownLinksHelper
    {
        private static $_current_source = 'theme';
        private static $_current_owner_class = '';

        /**
         * Update editor HTML links to the current WordPress permalink mode.
         *
         * @param string $content
         *
         * @return string
         */
        public static function update_editor_html_links_for_permalink_mode($content)
        {
            if (!is_string($content) || $content === '') {
                return $content;
            }

            self::$_current_source = self::get_preferred_route_source('plugin');
            self::$_current_owner_class = self::get_owner_class();

            return preg_replace_callback('/\b(href|data-href)=(["\'])([^"\']+)\2/i', array(__CLASS__, 'normalize_html_link_callback'), $content);
        }

        /**
         * Update one editor menu URL to the current WordPress permalink mode.
         *
         * @param string $href
         *
         * @return string
         */
        public static function update_menu_href_for_permalink_mode($href)
        {
            return self::normalize_url($href, self::get_preferred_route_source('plugin'), self::get_owner_class());
        }

        /**
         * Pick Nicepage route source from the current Site Style Source setting.
         *
         * @param string $default
         *
         * @return string
         */
        public static function get_preferred_route_source($default = 'plugin')
        {
            if (get_option('np_theme_appearance') === 'plugin-option') {
                return 'plugin';
            }
            if (get_option('np_theme_appearance') === 'theme-option') {
                return 'theme';
            }

            return $default === 'plugin' ? 'plugin' : 'theme';
        }

        /**
         * Resolve active multilingual owner class.
         *
         * @return string
         */
        private static function get_owner_class()
        {
            if (class_exists('NpMultiLanguages')) {
                return 'NpMultiLanguages';
            }
            if (class_exists('ThemeMultiLanguages')) {
                return 'ThemeMultiLanguages';
            }

            return '';
        }

        /**
         * Normalize one HTML link attribute match.
         *
         * @param array $matches
         *
         * @return string
         */
        private static function normalize_html_link_callback($matches)
        {
            $url = self::normalize_url($matches[3], self::$_current_source, self::$_current_owner_class);
            if ($url === $matches[3]) {
                return $matches[0];
            }

            return $matches[1] . '=' . $matches[2] . esc_url($url) . $matches[2];
        }

        /**
         * Convert a known same-site URL to the current permalink-aware URL.
         *
         * @param string $url
         * @param string $source
         * @param string $owner_class
         *
         * @return string
         */
        private static function normalize_url($url, $source, $owner_class)
        {
            if (!is_string($url) || $url === '' || self::is_ignored_href($url)) {
                return $url;
            }

            $decoded_url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
            $parts = wp_parse_url($decoded_url);
            if (!is_array($parts) || !self::is_local_url_parts($parts)) {
                return $url;
            }

            $query = self::get_url_query($parts);
            $normalized_url = self::normalize_np_path_route($parts, $query, $source, $owner_class);
            if ($normalized_url) {
                return $normalized_url;
            }

            if (!empty($query)) {
                $normalized_url = self::build_url_from_known_query($query, $parts, $source, $owner_class);
                if ($normalized_url) {
                    return $normalized_url;
                }
            }

            $normalized_url = self::normalize_wp_object_url($decoded_url, $parts);
            if ($normalized_url) {
                return $normalized_url;
            }

            $normalized_url = self::normalize_term_url($parts);
            if ($normalized_url) {
                return $normalized_url;
            }

            return $url;
        }

        /**
         * Normalize page/post/product URLs using WordPress object resolution.
         *
         * @param string $url
         * @param array  $parts
         *
         * @return string
         */
        private static function normalize_wp_object_url($url, $parts)
        {
            $post_id = url_to_postid($url);
            if (!$post_id) {
                $post_id = self::find_post_id_from_path($parts);
            }
            if (!$post_id) {
                return '';
            }

            return self::append_remaining_query(get_permalink($post_id), self::get_url_query($parts), array('p', 'page_id'), $parts);
        }

        /**
         * Resolve a post id from a local URL path when url_to_postid() cannot.
         *
         * @param array $parts
         *
         * @return int
         */
        private static function find_post_id_from_path($parts)
        {
            $path = self::get_clean_local_path($parts);
            if ($path === '') {
                return 0;
            }

            $post = get_page_by_path($path, OBJECT, array('page', 'post', 'product'));
            if (!$post instanceof WP_Post) {
                $segments = explode('/', $path);
                $post = get_page_by_path(end($segments), OBJECT, array('page', 'post', 'product'));
            }

            return $post instanceof WP_Post ? (int) $post->ID : 0;
        }

        /**
         * Normalize category/product-category URLs using WordPress term links.
         *
         * @param array $parts
         *
         * @return string
         */
        private static function normalize_term_url($parts)
        {
            $path = self::get_clean_local_path($parts);
            if ($path === '') {
                return '';
            }

            $segments = explode('/', $path);
            if (count($segments) < 2) {
                return '';
            }

            $slug = end($segments);
            $taxonomy = self::get_taxonomy_from_path_segments($segments);
            if (!$taxonomy) {
                return '';
            }

            $term = get_term_by('slug', sanitize_title($slug), $taxonomy);
            if ($term && !is_wp_error($term)) {
                return self::append_remaining_query(get_term_link($term), self::get_url_query($parts), array(), $parts);
            }

            return '';
        }

        /**
         * Normalize saved Nicepage path routes such as /products-list/lang/en/.
         *
         * @param array  $parts
         * @param array  $query
         * @param string $source
         * @param string $owner_class
         *
         * @return string
         */
        private static function normalize_np_path_route($parts, $query, $source, $owner_class)
        {
            $segments = NpPath::get_path_segments(self::get_clean_local_path($parts));
            if (!$segments) {
                return '';
            }

            $route = $segments[0];
            if ($route === 'products-list' || $route === 'productsList') {
                $route_source = $route === 'productsList' ? 'plugin' : 'theme';
                return self::append_remaining_query(self::build_np_route_url('products', '', $route_source, $owner_class), $query, array(), $parts);
            }
            if (($route === 'product-id' || $route === 'productId') && isset($segments[1]) && $segments[1] !== '') {
                $route_source = $route === 'productId' ? 'plugin' : 'theme';
                return self::append_remaining_query(self::build_np_route_url('product', $segments[1], $route_source, $owner_class), $query, array(), $parts);
            }
            if ($route === 'thank-you' || $route === 'thankYou') {
                $route_source = $route === 'thankYou' ? 'plugin' : 'theme';
                $order_id = isset($segments[1]) ? $segments[1] : '';
                return self::append_remaining_query(self::build_np_route_url('thankYou', $order_id, $route_source, $owner_class), $query, array(), $parts);
            }

            return '';
        }

        /**
         * Resolve taxonomy from a known category route path.
         *
         * @param array $segments
         *
         * @return string
         */
        private static function get_taxonomy_from_path_segments($segments)
        {
            $base = implode('/', array_slice($segments, 0, -1));
            if ($base === trim(NpPath::get_product_category_rewrite_path(), '/')) {
                return taxonomy_exists('product_cat') ? 'product_cat' : '';
            }

            $category_base = trim((string) get_option('category_base'), '/');
            if ($category_base === '') {
                $category_base = 'category';
            }

            if ($base === $category_base) {
                return taxonomy_exists('category') ? 'category' : '';
            }

            return '';
        }

        /**
         * Get local path without site base, index.php, and language suffix.
         *
         * @param array $parts
         *
         * @return string
         */
        private static function get_clean_local_path($parts)
        {
            $path = isset($parts['path']) ? trim((string) $parts['path'], '/') : '';
            if ($path === '') {
                return '';
            }

            $home_parts = wp_parse_url(home_url('/'));
            $home_path = isset($home_parts['path']) ? trim((string) $home_parts['path'], '/') : '';
            if ($home_path !== '' && strpos($path, $home_path . '/') === 0) {
                $path = substr($path, strlen($home_path) + 1);
            }
            if (strpos($path, 'index.php/') === 0) {
                $path = substr($path, 10);
            }

            $path = NpPath::remove_language_from_path('/' . $path . '/');
            return trim($path, '/');
        }

        /**
         * Build URL for known Nicepage/WP query routes.
         *
         * @param array  $query
         * @param array  $parts
         * @param string $source
         * @param string $owner_class
         *
         * @return string
         */
        private static function build_url_from_known_query($query, $parts, $source, $owner_class)
        {
            if (isset($query['page_id'])) {
                return self::append_remaining_query(get_permalink((int) $query['page_id']), $query, array('page_id'), $parts);
            }
            if (isset($query['p'])) {
                return self::append_remaining_query(get_permalink((int) $query['p']), $query, array('p'), $parts);
            }
            if (isset($query['post_type']) && $query['post_type'] === 'post') {
                return self::append_remaining_query(self::get_blog_url($owner_class), $query, array('post_type'), $parts);
            }
            if (isset($query['product_cat'])) {
                $term = get_term_by('slug', sanitize_title($query['product_cat']), 'product_cat');
                if ($term && !is_wp_error($term)) {
                    return self::append_remaining_query(get_term_link($term), $query, array('product_cat'), $parts);
                }
            }
            if (isset($query['product'])) {
                $product = get_page_by_path(sanitize_title($query['product']), OBJECT, 'product');
                if ($product instanceof WP_Post) {
                    return self::append_remaining_query(get_permalink($product->ID), $query, array('product'), $parts);
                }
            }
            if (array_key_exists('productsList', $query) || array_key_exists('products-list', $query)) {
                $route_source = array_key_exists('productsList', $query) ? 'plugin' : 'theme';
                return self::append_remaining_query(self::build_np_route_url('products', '', $route_source, $owner_class), $query, array('productsList', 'products-list'), $parts);
            }
            if (isset($query['productId']) || isset($query['product-id'])) {
                $route_source = isset($query['productId']) ? 'plugin' : 'theme';
                $product_id = isset($query['productId']) ? $query['productId'] : $query['product-id'];
                return self::append_remaining_query(self::build_np_route_url('product', $product_id, $route_source, $owner_class), $query, array('productId', 'product-id'), $parts);
            }
            if (isset($query['thankYou']) || isset($query['thank-you'])) {
                $route_source = isset($query['thankYou']) ? 'plugin' : 'theme';
                $order_id = isset($query['thankYou']) ? $query['thankYou'] : $query['thank-you'];
                return self::append_remaining_query(self::build_np_route_url('thankYou', $order_id, $route_source, $owner_class), $query, array('thankYou', 'thank-you'), $parts);
            }

            return '';
        }

        /**
         * Get the current blog archive URL.
         *
         * @param string $owner_class
         *
         * @return string
         */
        private static function get_blog_url($owner_class)
        {
            if (class_exists('NpMultiLanguagesResolver')) {
                return NpMultiLanguagesResolver::get_blog_archive_url($owner_class);
            }

            return get_option('show_on_front') === 'posts' ? get_home_url() : home_url('/?post_type=post');
        }

        /**
         * Build the current Nicepage route URL.
         *
         * @param string $route
         * @param string $value
         * @param string $source
         * @param string $owner_class
         *
         * @return string
         */
        private static function build_np_route_url($route, $value, $source, $owner_class)
        {
            if (class_exists('NpMultiLanguagesResolver')) {
                return NpMultiLanguagesResolver::build_np_route_url($route, $value, $source, $owner_class);
            }

            $fallback_key = array(
                'products' => $source === 'plugin' ? 'productsList' : 'products-list',
                'product' => $source === 'plugin' ? 'productId' : 'product-id',
                'thankYou' => $source === 'plugin' ? 'thankYou' : 'thank-you',
            );

            return isset($fallback_key[$route]) ? add_query_arg(array($fallback_key[$route] => $value), home_url('/')) : home_url('/');
        }

        /**
         * Preserve unrelated query parameters and the original fragment.
         *
         * @param string $target_url
         * @param array  $query
         * @param array  $route_keys
         * @param array  $parts
         *
         * @return string
         */
        private static function append_remaining_query($target_url, $query, $route_keys, $parts)
        {
            if (!is_string($target_url) || $target_url === '') {
                return '';
            }

            foreach ($route_keys as $route_key) {
                unset($query[$route_key]);
            }
            if (isset($query['lang'])) {
                unset($query['lang']);
            }
            if (!empty($query)) {
                $target_url = add_query_arg($query, $target_url);
            }

            return self::append_fragment($target_url, $parts);
        }

        /**
         * Parse URL query into an array.
         *
         * @param array $parts
         *
         * @return array
         */
        private static function get_url_query($parts)
        {
            if (empty($parts['query'])) {
                return array();
            }

            $query = array();
            parse_str($parts['query'], $query);

            return is_array($query) ? $query : array();
        }

        /**
         * Preserve original URL fragment.
         *
         * @param string $target_url
         * @param array  $parts
         *
         * @return string
         */
        private static function append_fragment($target_url, $parts)
        {
            if (!is_string($target_url) || $target_url === '') {
                return '';
            }
            if (!empty($parts['fragment'])) {
                $target_url .= '#' . $parts['fragment'];
            }

            return $target_url;
        }

        /**
         * Check if parsed URL points to the current WordPress site.
         *
         * @param array $parts
         *
         * @return bool
         */
        private static function is_local_url_parts($parts)
        {
            if (empty($parts['host'])) {
                return true;
            }

            $home_parts = wp_parse_url(home_url('/'));
            if (!is_array($home_parts) || empty($home_parts['host'])) {
                return false;
            }

            $parts_host = strtolower($parts['host']);
            $home_host = strtolower($home_parts['host']);
            $parts_port = isset($parts['port']) ? (int) $parts['port'] : 0;
            $home_port = isset($home_parts['port']) ? (int) $home_parts['port'] : 0;

            return $parts_host === $home_host && $parts_port === $home_port;
        }

        /**
         * Skip anchors, scripts, and protocol links that should not be normalized.
         *
         * @param string $href
         *
         * @return bool
         */
        private static function is_ignored_href($href)
        {
            return $href[0] === '#'
                || stripos($href, 'mailto:') === 0
                || stripos($href, 'tel:') === 0
                || stripos($href, 'javascript:') === 0;
        }
    }
}
