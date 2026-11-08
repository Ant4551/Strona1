<?php
defined('ABSPATH') or die;

if (!class_exists('NpMultiLanguagesHybridUrlServerHelper')) {
    /**
     * Builds and redirects multilingual URLs on the server side.
     * The helper decides when to use /lang/{code}/ path URLs or legacy query parameters,
     * preserves special WordPress/WooCommerce routes, and prevents invalid language suffixes.
     * It contains request-safe URL operations shared by the theme and plugin facades.
     */
    class NpMultiLanguagesHybridUrlServerHelper
    {
        /**
         * Check whether language suffix should not be applied to given path.
         *
         * @param string $path
         *
         * @return bool
         */
        public static function is_excluded_path_for_language_url($path)
        {
            $segments = NpPath::get_path_segments($path);
            if (!$segments) {
                return false;
            }

            $home_parts = wp_parse_url(home_url('/'));
            $home_path = isset($home_parts['path']) ? trim((string) $home_parts['path'], '/') : '';
            if ($home_path !== '') {
                $home_segments = NpPath::get_path_segments($home_path);
                $home_count = count($home_segments);
                if ($home_count > 0 && count($segments) >= $home_count && array_slice($segments, 0, $home_count) === $home_segments) {
                    $segments = array_slice($segments, $home_count);
                }
            }

            if (!$segments) {
                return false;
            }

            if (strtolower((string) $segments[0]) === 'index.php') {
                $segments = array_slice($segments, 1);
            }

            if (!$segments) {
                return false;
            }

            $first = strtolower((string) $segments[0]);
            $blocked = array('wp-admin', 'wp-login.php', 'wp-json', 'xmlrpc.php', 'wp-cron.php');

            return in_array($first, $blocked, true);
        }

        /**
         * Build URL with target language in path or query by permalink mode.
         *
         * @param string $url
         * @param string $language
         *
         * @return string
         */
        public static function build_language_url($url, $language)
        {
            if (!$url || !$language) {
                return $url;
            }

            if (!NpPath::use_path_language_urls()) {
                $url = remove_query_arg('lang', $url);
                $url = NpUrlHelper::remove_duplicate_route_query_args($url);
                return add_query_arg('lang', $language, $url);
            }

            $parts = wp_parse_url(html_entity_decode($url));
            if (!$parts) {
                return $url;
            }

            if (!isset($parts['host'])) {
                $parts = wp_parse_url(home_url($url));
                if (!$parts) {
                    return $url;
                }
            }

            $query = array();
            if (isset($parts['query'])) {
                parse_str($parts['query'], $query);
                unset($query['lang']);
            }

            if (isset($query['post_type']) && $query['post_type'] === 'post') {
                $blog_parts = wp_parse_url(html_entity_decode(NpPath::get_blog_archive_url()));
                if ($blog_parts && isset($blog_parts['path']) && (!isset($blog_parts['query']) || strpos($blog_parts['query'], 'post_type=post') === false)) {
                    $parts = $blog_parts;
                    unset($query['post_type']);
                }
            }

            $path = isset($parts['path']) ? $parts['path'] : '/';
            if (self::is_excluded_path_for_language_url($path)) {
                return $url;
            }
            $path = rtrim(NpPath::normalize_path_for_permalinks($path), '/');
            $path = ($path ? $path : '') . '/lang/' . rawurlencode($language) . '/';

            return NpUrlHelper::build_url_from_parts($parts, $path, $query);
        }

        /**
         * Redirect path language URLs to query format when plain permalinks are active.
         *
         * @param string $owner_class
         *
         * @return void
         */
        public static function redirect_plain_language_path_to_query($owner_class)
        {
            if (get_option('permalink_structure')) {
                return;
            }
            if (!isset($_SERVER['REQUEST_URI'])) {
                return;
            }

            $request_uri = wp_unslash($_SERVER['REQUEST_URI']);
            $parts = wp_parse_url($request_uri);
            if (!$parts || !isset($parts['path'])) {
                return;
            }

            $relative_path = self::get_relative_request_path($parts['path']);
            $language = self::extract_supported_language_from_path($relative_path, $owner_class);
            if (!$language) {
                return;
            }

            $path = NpPath::remove_language_from_path($relative_path);
            if (self::is_excluded_path_for_language_url($path)) {
                return;
            }

            $query = array();
            if (isset($parts['query']) && is_string($parts['query'])) {
                parse_str($parts['query'], $query);
            }
            $query['lang'] = $language;

            $target = self::build_plain_language_redirect_url($path, $query);
            if (!$target) {
                return;
            }

            wp_redirect($target, 301);
            exit;
        }

        /**
         * Build plain permalink redirect URL for supported Nicepage and query routes.
         *
         * @param string $path
         * @param array  $query
         *
         * @return string
         */
        private static function build_plain_language_redirect_url($path, $query)
        {
            $segments = NpPath::get_path_segments($path);
            $route_query = self::get_plain_route_query_from_segments($segments);
            if ($route_query) {
                $query = array_merge($query, $route_query);
                $path = '/';
            } elseif ($segments) {
                return '';
            }

            $target = home_url($path);
            return NpUrlHelper::build_url_from_parts(wp_parse_url($target), wp_parse_url($target, PHP_URL_PATH), $query);
        }

        /**
         * Convert known Nicepage path segments to plain permalink query vars.
         *
         * @param array $segments
         *
         * @return array
         */
        private static function get_plain_route_query_from_segments($segments)
        {
            if (!$segments) {
                return array();
            }

            $route = (string) $segments[0];
            if (($route === 'products-list' || $route === 'productsList') && count($segments) === 1) {
                return array($route => '');
            }
            if (($route === 'thank-you' || $route === 'thankYou') && count($segments) === 1) {
                return array($route => '');
            }
            if (($route === 'product-id' || $route === 'productId' || $route === 'thank-you' || $route === 'thankYou') && count($segments) === 2) {
                return array($route => $segments[1]);
            }

            return array();
        }

        /**
         * Strip site base path from request path.
         *
         * @param string $path
         *
         * @return string
         */
        private static function get_relative_request_path($path)
        {
            $segments = NpPath::get_path_segments($path);
            $home_path = wp_parse_url(home_url('/'), PHP_URL_PATH);
            $home_segments = NpPath::get_path_segments(is_string($home_path) ? $home_path : '');
            if ($home_segments) {
                $home_count = count($home_segments);
                if (count($segments) >= $home_count && array_slice($segments, 0, $home_count) === $home_segments) {
                    $segments = array_slice($segments, $home_count);
                }
            }

            if ($segments && strtolower((string) $segments[0]) === 'index.php') {
                $segments = array_slice($segments, 1);
            }

            return $segments ? '/' . implode('/', $segments) . '/' : '/';
        }

        /**
         * Extract supported language code from request path.
         *
         * @param string $path
         * @param string $owner_class
         *
         * @return string
         */
        private static function extract_supported_language_from_path($path, $owner_class)
        {
            $segments = NpPath::get_path_segments($path);
            $supported_langs = call_user_func(array($owner_class, 'get_supported_langs'));
            $count = count($segments);
            for ($i = 0; $i < $count - 1; $i++) {
                if (strtolower((string) $segments[$i]) !== 'lang') {
                    continue;
                }

                $language = sanitize_key(rawurldecode($segments[$i + 1]));
                return in_array($language, $supported_langs, true) ? $language : '';
            }

            return '';
        }

        /**
         * Redirect request to URL with language when cookie language is set.
         *
         * @param string $owner_class
         * @param bool   $check_preview
         *
         * @return void
         */
        public static function language_redirect($owner_class, $check_preview)
        {
            if (!NpMultiLanguagesHybridRoutingRuntimeHelper::is_np_route_request()) {
                return;
            }

            $language = isset($_COOKIE['np_lang']) && is_string($_COOKIE['np_lang'])
                ? sanitize_key(wp_unslash($_COOKIE['np_lang']))
                : '';

            if (!$language || NpMultiLanguagesHybridRoutingRuntimeHelper::get_requested_language($owner_class)) {
                return;
            }

            $language = NpMultiLanguagesRuntimeHelper::resolve_supported_language($language, $owner_class);
            if (!$language) {
                return;
            }

            if (!isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['REQUEST_URI'])) {
                return;
            }

            $current_url = (is_ssl() ? 'https://' : 'http://') . wp_unslash($_SERVER['HTTP_HOST']) . wp_unslash($_SERVER['REQUEST_URI']);
            $location = self::build_language_url($current_url, $language);
            if ($location === $current_url) {
                return;
            }

            if ($check_preview) {
                $request_query = NpUrlHelper::get_request_query_params();
                $location = NpUrlHelper::preserve_route_query_arg($location, $request_query, 'products-list', 'productsList');
                $location = NpUrlHelper::preserve_route_query_arg($location, $request_query, 'product-id', 'productId');
                $location = NpUrlHelper::preserve_route_query_arg($location, $request_query, 'thank-you', 'thankYou');

                global $post;
                if (function_exists('np_data_provider') && isset($post->ID)) {
                    $data_provider = np_data_provider($post->ID);
                    if ($data_provider->isPreview()) {
                        return;
                    }
                }
            }

            wp_redirect($location);
            exit;
        }
    }
}
