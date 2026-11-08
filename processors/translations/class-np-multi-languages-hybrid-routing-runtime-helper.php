<?php
defined('ABSPATH') or die;

if (!class_exists('NpMultiLanguagesHybridRoutingRuntimeHelper')) {
    /**
     * Reads and normalizes Nicepage multilingual route data during a request.
     * The helper extracts language/source information from path and query variables,
     * mirrors legacy route keys when needed, and prepares request state for existing code.
     * It does not register rewrite rules; it only interprets the current request.
     */
    class NpMultiLanguagesHybridRoutingRuntimeHelper
    {
        const NP_ROUTE_SOURCE_QUERY_KEY = 'np_route_source';

        /**
         * Initialize request language during parse_request hook.
         *
         * @param WP     $wp
         * @param string $owner_class
         *
         * @return void
         */
        public static function initialize_request_language_from_parse_request($wp, $owner_class)
        {
            $query_vars = isset($wp->query_vars) ? $wp->query_vars : null;
            $is_np_route = self::is_np_route_request($query_vars);

            $language = '';
            if (isset($wp->query_vars['lang']) && is_string($wp->query_vars['lang'])) {
                $language = sanitize_key(wp_unslash($wp->query_vars['lang']));
            }
            if (!$language) {
                $language = self::get_requested_language($owner_class);
            }
            if (!$language) {
                return;
            }
            if (!$is_np_route && !self::is_front_page_request_path()) {
                return;
            }

            $_GET['lang'] = $language;
            $_REQUEST['lang'] = $language;
            self::initialize_front_page_request($wp);
        }

        /**
         * Mirror route query vars between hyphen and camelCase keys.
         *
         * @param WP $wp
         *
         * @return void
         */
        public static function normalize_legacy_route_query_vars($wp)
        {
            if (!isset($wp->query_vars) || !is_array($wp->query_vars)) {
                return;
            }
            if (!self::is_np_route_request($wp->query_vars)) {
                return;
            }

            if (isset($wp->query_vars['lang']) && is_string($wp->query_vars['lang'])) {
                $language = sanitize_key(wp_unslash($wp->query_vars['lang']));
                $_GET['lang'] = $language;
                $_REQUEST['lang'] = $language;
            }

            $route_pairs = array(
                'products-list' => 'productsList',
                'product-id' => 'productId',
                'thank-you' => 'thankYou',
            );
            $route_source = self::get_route_source_from_query_vars($wp->query_vars);
            if ($route_source) {
                self::set_route_source($wp, $route_source);
            }

            foreach ($route_pairs as $hyphen_key => $camel_key) {
                $has_value = false;
                $value = '';

                if (array_key_exists($hyphen_key, $wp->query_vars)) {
                    $value = $wp->query_vars[$hyphen_key];
                    $has_value = true;
                } elseif (array_key_exists($camel_key, $wp->query_vars)) {
                    $value = $wp->query_vars[$camel_key];
                    $has_value = true;
                }

                if (!$has_value) {
                    continue;
                }

                $_GET[$hyphen_key] = (string) $value;
                $_GET[$camel_key] = (string) $value;
                $_REQUEST[$hyphen_key] = (string) $value;
                $_REQUEST[$camel_key] = (string) $value;
            }
        }

        /**
         * Get original Nicepage route source before legacy query vars are mirrored.
         *
         * @param string $default
         *
         * @return string
         */
        public static function get_route_source($default = 'plugin')
        {
            $source = '';
            if (isset($_GET[self::NP_ROUTE_SOURCE_QUERY_KEY])) {
                $source = self::normalize_route_source($_GET[self::NP_ROUTE_SOURCE_QUERY_KEY]);
            }
            if (!$source && isset($_REQUEST[self::NP_ROUTE_SOURCE_QUERY_KEY])) {
                $source = self::normalize_route_source($_REQUEST[self::NP_ROUTE_SOURCE_QUERY_KEY]);
            }

            global $wp;
            if (!$source && isset($wp) && isset($wp->query_vars) && is_array($wp->query_vars)) {
                $source = self::get_route_source_from_query_vars($wp->query_vars);
            }
            if (!$source) {
                $source = self::get_route_source_from_query_vars($_GET);
            }
            if (!$source) {
                $source = self::get_route_source_from_query_vars($_REQUEST);
            }

            return $source ? $source : self::normalize_route_source($default, 'plugin');
        }

        /**
         * Attach front-page page_id when home request has no content vars.
         *
         * @param WP $wp WordPress request object.
         *
         * @return void
         */
        public static function initialize_front_page_request($wp = null)
        {
            if (get_option('show_on_front') !== 'page') {
                return;
            }

            $front_page_id = (int) get_option('page_on_front');
            if (!$front_page_id || self::has_content_query_vars() || !self::is_front_page_request_path()) {
                return;
            }

            $_GET['page_id'] = (string) $front_page_id;
            $_REQUEST['page_id'] = (string) $front_page_id;
            if ($wp && isset($wp->query_vars) && is_array($wp->query_vars)) {
                $wp->query_vars['page_id'] = (string) $front_page_id;
            }
        }

        /**
         * Check if request already targets specific content query vars.
         *
         * @return bool
         */
        public static function has_content_query_vars()
        {
            $content_keys = array('p', 'page_id', 'name', 'pagename', 'post_type', 'product', 'product_id', 'product-id', 'productId', 'productsList', 'products-list', 'thankYou', 'thank-you', 'action', 'attachment', 'attachment_id', 's', 'category_name', 'cat', 'tag', 'tag_id', 'product_cat', 'product_tag', 'taxonomy', 'term', 'author', 'year', 'monthnum', 'day');
            foreach ($content_keys as $key) {
                if (isset($_GET[$key]) && $_GET[$key] !== '') {
                    return true;
                }
            }
            return false;
        }

        /**
         * Check if current request path points to front page URL.
         *
         * @return bool
         */
        public static function is_front_page_request_path()
        {
            if (!isset($_SERVER['REQUEST_URI'])) {
                return false;
            }

            $request_path = wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH);
            if (!is_string($request_path)) {
                return false;
            }

            $request_path = rtrim(NpPath::remove_language_from_path($request_path), '/');
            $home_path = wp_parse_url(home_url('/'), PHP_URL_PATH);
            $home_path = is_string($home_path) ? rtrim($home_path, '/') : '';

            if ($request_path === $home_path) {
                return true;
            }

            return NpPath::use_index_permalinks() && $request_path === $home_path . '/index.php';
        }

        /**
         * Prevent canonical redirect from dropping language on front page.
         *
         * @param string|false $redirect_url
         * @param string       $owner_class
         *
         * @return string|false
         */
        public static function preserve_language_front_page_url($redirect_url, $owner_class)
        {
            if (self::is_np_route_request()) {
                return false;
            }

            if (!NpPath::use_path_language_urls() || !self::get_requested_language($owner_class) || !self::is_front_page_request_path()) {
                return $redirect_url;
            }

            return false;
        }

        /**
         * Extract language code from /lang/{code}/ suffix in request path.
         *
         * @return string
         */
        public static function get_language_from_request_path()
        {
            if (!isset($_SERVER['REQUEST_URI'])) {
                return '';
            }

            $request_path = wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH);
            if (!is_string($request_path)) {
                return '';
            }

            $segments = NpPath::get_path_segments($request_path);
            $count = count($segments);
            if ($count < 2 || strtolower($segments[$count - 2]) !== 'lang') {
                return '';
            }

            return sanitize_key(rawurldecode($segments[$count - 1]));
        }

        /**
         * Resolve requested language from query vars or path.
         *
         * @param string $owner_class
         *
         * @return string
         */
        public static function get_requested_language($owner_class)
        {
            $language = '';

            if (isset($_GET['lang']) && is_string($_GET['lang'])) {
                $language = sanitize_key(wp_unslash($_GET['lang']));
            }

            if (!$language && NpPath::use_path_language_urls()) {
                $language = self::get_language_from_request_path();
            }

            return NpMultiLanguagesRuntimeHelper::resolve_supported_language($language, $owner_class);
        }

        /**
         * Resolve current language from request first, then cookie fallback.
         *
         * @param string $owner_class
         *
         * @return string
         */
        public static function get_current_language($owner_class)
        {
            $language = self::get_requested_language($owner_class);
            if (!$language && isset($_COOKIE['np_lang']) && is_string($_COOKIE['np_lang'])) {
                $language = sanitize_key(wp_unslash($_COOKIE['np_lang']));
            }

            return NpMultiLanguagesRuntimeHelper::resolve_supported_language($language, $owner_class);
        }

        /**
         * Check whether current request matches Nicepage-managed routes.
         *
         * @param array|null $query_vars
         *
         * @return bool
         */
        public static function is_np_route_request($query_vars = null)
        {
            $route_keys = array('products-list', 'product-id', 'thank-you', 'productsList', 'productId', 'thankYou');

            if (is_array($query_vars)) {
                if (isset($query_vars['np_route']) && self::is_truthy_route_marker($query_vars['np_route'])) {
                    return true;
                }
                foreach ($route_keys as $route_key) {
                    if (array_key_exists($route_key, $query_vars)) {
                        return true;
                    }
                }
                return false;
            }

            global $wp;
            if (isset($wp) && isset($wp->query_vars) && is_array($wp->query_vars)) {
                if (self::is_np_route_request($wp->query_vars)) {
                    return true;
                }
            }

            if (isset($_GET['np_route']) && self::is_truthy_route_marker($_GET['np_route'])) {
                return true;
            }
            if (isset($_REQUEST['np_route']) && self::is_truthy_route_marker($_REQUEST['np_route'])) {
                return true;
            }

            foreach ($route_keys as $route_key) {
                if (isset($_GET[$route_key]) || isset($_REQUEST[$route_key])) {
                    return true;
                }
            }

            if (self::is_login_request_path()) {
                return true;
            }

            return false;
        }

        /**
         * Check if internal route marker value should be treated as true.
         *
         * @param mixed $value
         *
         * @return bool
         */
        private static function is_truthy_route_marker($value)
        {
            $value = (string) $value;
            return $value === '1' || $value === 'true' || $value === 'yes';
        }

        /**
         * Store original route source for later link generation.
         *
         * @param WP     $wp
         * @param string $source
         *
         * @return void
         */
        private static function set_route_source($wp, $source)
        {
            $source = self::normalize_route_source($source);
            if (!$source) {
                return;
            }

            $wp->query_vars[self::NP_ROUTE_SOURCE_QUERY_KEY] = $source;
            $_GET[self::NP_ROUTE_SOURCE_QUERY_KEY] = $source;
            $_REQUEST[self::NP_ROUTE_SOURCE_QUERY_KEY] = $source;
        }

        /**
         * Detect route source from query vars.
         *
         * @param array $query_vars
         *
         * @return string
         */
        private static function get_route_source_from_query_vars($query_vars)
        {
            if (isset($query_vars[self::NP_ROUTE_SOURCE_QUERY_KEY])) {
                return self::normalize_route_source($query_vars[self::NP_ROUTE_SOURCE_QUERY_KEY]);
            }

            $route_pairs = array(
                'products-list' => 'productsList',
                'product-id' => 'productId',
                'thank-you' => 'thankYou',
            );

            foreach ($route_pairs as $hyphen_key => $camel_key) {
                if (array_key_exists($hyphen_key, $query_vars)) {
                    return 'theme';
                }
                if (array_key_exists($camel_key, $query_vars)) {
                    return 'plugin';
                }
            }

            return '';
        }

        /**
         * Normalize route source to supported values.
         *
         * @param string $source
         * @param string $fallback
         *
         * @return string
         */
        private static function normalize_route_source($source, $fallback = '')
        {
            return $source === 'theme' || $source === 'plugin' ? $source : $fallback;
        }

        /**
         * Check if current request path targets login-style routes.
         *
         * @return bool
         */
        private static function is_login_request_path()
        {
            if (!isset($_SERVER['REQUEST_URI'])) {
                return false;
            }

            $path = wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH);
            if (!is_string($path)) {
                return false;
            }

            $segments = NpPath::get_path_segments($path);
            if (!$segments) {
                return false;
            }

            $home_path = wp_parse_url(home_url('/'), PHP_URL_PATH);
            $home_segments = NpPath::get_path_segments(is_string($home_path) ? $home_path : '');
            if ($home_segments) {
                $home_count = count($home_segments);
                if (count($segments) >= $home_count && array_slice($segments, 0, $home_count) === $home_segments) {
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
            return in_array($first, array('login', 'lostpassword', 'register'), true);
        }
    }
}
