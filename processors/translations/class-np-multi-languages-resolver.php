<?php
defined('ABSPATH') or die;

if (!class_exists('NpMultiLanguagesResolver')) {
    /**
     * Provides context-aware URLs for templates, posts, blogs, and Nicepage product routes.
     * Theme and plugin code use this class to ask for the correct current-language URL
     * without duplicating source detection or permalink-mode branching in template processors.
     * It is a small facade over the active multilingual owner class and path builders.
     */
    class NpMultiLanguagesResolver
    {
        /**
         * Get blog archive URL from multilingual class or fallback.
         *
         * @param string $owner_class
         *
         * @return string
         */
        public static function get_blog_archive_url($owner_class = '')
        {
            $owner_class = self::get_owner_class($owner_class);
            if ($owner_class && method_exists($owner_class, 'get_blog_archive_url')) {
                return call_user_func(array($owner_class, 'get_blog_archive_url'));
            }

            return get_option('show_on_front') === 'posts' ? get_home_url() : home_url('/?post_type=post');
        }

        /**
         * Build Nicepage route URL with fallback implementation.
         *
         * @param string $route
         * @param string $value
         * @param string $source
         * @param string $owner_class
         *
         * @return string
         */
        public static function build_np_route_url($route, $value = '', $source = 'theme', $owner_class = '')
        {
            $owner_class = self::get_owner_class($owner_class);
            if ($owner_class && method_exists($owner_class, 'build_np_route_url')) {
                return call_user_func(array($owner_class, 'build_np_route_url'), $route, $value, $source);
            }

            if ($route === 'products') {
                return $source === 'theme' ? home_url('?products-list') : home_url('?productsList');
            }
            if ($route === 'product') {
                return $source === 'theme' ? home_url('?product-id=' . $value) : home_url('?productId=' . $value);
            }
            if ($route === 'thankYou') {
                return $source === 'theme' ? home_url('?thank-you=' . $value) : home_url('?thankYou=' . $value);
            }

            return home_url('/');
        }

        /**
         * Build products category URL for Nicepage shop routing.
         *
         * @param int|string $category_id
         * @param string     $source
         * @param string     $owner_class
         *
         * @return string
         */
        public static function build_np_products_category_url($category_id, $source = 'theme', $owner_class = '')
        {
            $owner_class = self::get_owner_class($owner_class);
            if ($owner_class && method_exists($owner_class, 'build_np_products_category_url')) {
                return call_user_func(array($owner_class, 'build_np_products_category_url'), $category_id, $source);
            }

            return self::build_np_route_url('products', '', $source, $owner_class) . '#/1///' . $category_id;
        }

        /**
         * Get original Nicepage route source.
         *
         * @param string $default
         * @param string $owner_class
         *
         * @return string
         */
        public static function get_np_route_source($default = 'plugin', $owner_class = '')
        {
            $owner_class = self::get_owner_class($owner_class);
            if ($owner_class && method_exists($owner_class, 'get_np_route_source')) {
                return call_user_func(array($owner_class, 'get_np_route_source'), $default);
            }

            return self::resolve_np_route_source($default);
        }

        /**
         * Get current language with fallback when multilingual class is absent.
         *
         * @param string $fallback
         * @param string $owner_class
         *
         * @return string
         */
        public static function get_current_language($fallback = '', $owner_class = '')
        {
            $owner_class = self::get_owner_class($owner_class);
            if ($owner_class && method_exists($owner_class, 'get_current_language')) {
                return call_user_func(array($owner_class, 'get_current_language'));
            }

            return isset($_GET['lang']) && is_string($_GET['lang']) ? sanitize_key(wp_unslash($_GET['lang'])) : $fallback;
        }

        /**
         * Get default language with fallback when multilingual class is absent.
         *
         * @param string $fallback
         * @param string $owner_class
         *
         * @return string
         */
        public static function get_default_language($fallback = 'en', $owner_class = '')
        {
            $owner_class = self::get_owner_class($owner_class);
            if ($owner_class && method_exists($owner_class, 'get_np_default_lang')) {
                return call_user_func(array($owner_class, 'get_np_default_lang'));
            }

            return $fallback;
        }

        /**
         * Resolve multilingual facade class for the current runtime context.
         *
         * @param string $owner_class
         *
         * @return string
         */
        private static function get_owner_class($owner_class = '')
        {
            if (is_string($owner_class) && $owner_class !== '' && class_exists($owner_class)) {
                return $owner_class;
            }
            if (class_exists('NpMultiLanguages')) {
                return 'NpMultiLanguages';
            }
            if (class_exists('ThemeMultiLanguages')) {
                return 'ThemeMultiLanguages';
            }

            return '';
        }

        /**
         * Resolve route source without multilingual runtime helper.
         *
         * @param string $default
         *
         * @return string
         */
        private static function resolve_np_route_source($default)
        {
            if (isset($_GET['np_route_source']) && ($_GET['np_route_source'] === 'theme' || $_GET['np_route_source'] === 'plugin')) {
                return $_GET['np_route_source'];
            }
            if (isset($_REQUEST['np_route_source']) && ($_REQUEST['np_route_source'] === 'theme' || $_REQUEST['np_route_source'] === 'plugin')) {
                return $_REQUEST['np_route_source'];
            }

            if (isset($_GET['productsList']) || isset($_GET['productId']) || isset($_GET['thankYou'])) {
                return 'plugin';
            }
            if (isset($_GET['products-list']) || isset($_GET['product-id']) || isset($_GET['thank-you'])) {
                return 'theme';
            }

            return $default === 'theme' ? 'theme' : 'plugin';
        }
    }
}