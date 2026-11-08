<?php
defined('ABSPATH') or die;

if (!class_exists('NpMultiLanguagesHybridRoutingRegisterHelper')) {
    /**
     * Registers WordPress rewrite rules for Nicepage hybrid multilingual routes.
     * The class declares endpoint/query variables and adds explicit rules for routes
     * that WordPress or WooCommerce do not understand with a trailing /lang/{code}/ suffix.
     * It is intentionally separated from runtime parsing so route registration stays isolated.
     */
    class NpMultiLanguagesHybridRoutingRegisterHelper
    {
        const NP_ROUTE_QUERY_KEY = 'np_route';
        const NP_ROUTE_QUERY_VALUE = '1';

        /**
         * Register language endpoint and all custom rewrite rules.
         *
         * @param string $rewrite_version
         *
         * @return void
         */
        public static function register_language_endpoint($rewrite_version)
        {
            add_rewrite_endpoint('lang', EP_ROOT | EP_PAGES | EP_PERMALINK | EP_ALL_ARCHIVES | EP_SEARCH);
            self::register_blog_archive_language_rule();
            self::register_blog_category_language_rule();
            self::register_post_permalink_language_rule();
            self::register_product_archive_language_rule();
            self::register_product_permalink_language_rule();
            self::register_product_category_language_rule();
            self::register_login_pages_language_rules();
            self::register_nicepage_shop_language_rules();
            self::register_fallback_language_rules();

            if (get_option('np_lang_endpoint_version') !== $rewrite_version) {
                flush_rewrite_rules(false);
                update_option('np_lang_endpoint_version', $rewrite_version, false);
            }
        }

        /**
         * Register fallback rewrite rules for search and generic pages.
         *
         * @return void
         */
        public static function register_fallback_language_rules()
        {
            add_rewrite_rule(
                '^search/(.+)/lang/([^/]+)/?$',
                self::append_np_route_query('index.php?s=$matches[1]&lang=$matches[2]'),
                'top'
            );
            add_rewrite_rule(
                '^index\.php/search/(.+)/lang/([^/]+)/?$',
                self::append_np_route_query('index.php?s=$matches[1]&lang=$matches[2]'),
                'top'
            );

            add_rewrite_rule(
                '^(.+?)/lang/([^/]+)/?$',
                self::append_np_route_query('index.php?pagename=$matches[1]&lang=$matches[2]'),
                'top'
            );
            add_rewrite_rule(
                '^index\.php/(.+?)/lang/([^/]+)/?$',
                self::append_np_route_query('index.php?pagename=$matches[1]&lang=$matches[2]'),
                'top'
            );
        }

        /**
         * Register language-aware rewrite rules for blog archive.
         *
         * @return void
         */
        public static function register_blog_archive_language_rule()
        {
            $blog_path = NpPath::get_blog_archive_rewrite_path();
            if ($blog_path === '') {
                return;
            }

            self::add_rewrite_rule_with_index_prefix(
                preg_quote($blog_path, '/') . '/page/([0-9]{1,})/lang/([^/]+)/?$',
                'index.php?post_type=post&paged=$matches[1]&lang=$matches[2]'
            );

            self::add_rewrite_rule_with_index_prefix(
                preg_quote($blog_path, '/') . '/lang/([^/]+)/page/([0-9]{1,})/?$',
                'index.php?post_type=post&paged=$matches[2]&lang=$matches[1]'
            );

            self::add_rewrite_rule_with_index_prefix(
                preg_quote($blog_path, '/') . '/lang/([^/]+)/?$',
                'index.php?post_type=post&lang=$matches[1]'
            );

            self::add_rewrite_rule_with_index_prefix(
                preg_quote($blog_path, '/') . '/page/([0-9]{1,})/?$',
                'index.php?post_type=post&paged=$matches[1]'
            );

            self::add_rewrite_rule_with_index_prefix(
                preg_quote($blog_path, '/') . '/?$',
                'index.php?post_type=post'
            );
        }

        /**
         * Register language-aware rewrite rules for blog category URLs.
         *
         * @return void
         */
        public static function register_blog_category_language_rule()
        {
            $category_base = get_option('category_base');
            $category_path = is_string($category_base) && trim($category_base, '/') !== '' ? trim($category_base, '/') : 'category';
            $category_pattern = preg_quote($category_path, '/');

            self::add_rewrite_rule_with_index_prefix(
                $category_pattern . '/(.+?)/page/([0-9]{1,})/lang/([^/]+)/?$',
                'index.php?category_name=$matches[1]&paged=$matches[2]&lang=$matches[3]'
            );
            self::add_rewrite_rule_with_index_prefix(
                $category_pattern . '/(.+?)/lang/([^/]+)/page/([0-9]{1,})/?$',
                'index.php?category_name=$matches[1]&paged=$matches[3]&lang=$matches[2]'
            );
            self::add_rewrite_rule_with_index_prefix(
                $category_pattern . '/(.+?)/(?:feed/)?(feed|rdf|rss|rss2|atom)/lang/([^/]+)/?$',
                'index.php?category_name=$matches[1]&feed=$matches[2]&lang=$matches[3]'
            );
            self::add_rewrite_rule_with_index_prefix(
                $category_pattern . '/(.+?)/lang/([^/]+)/?$',
                'index.php?category_name=$matches[1]&lang=$matches[2]'
            );
        }

        /**
         * Register language-aware rewrite rule for standard WordPress post permalinks.
         *
         * @return void
         */
        public static function register_post_permalink_language_rule()
        {
            $permalink_structure = trim((string) get_option('permalink_structure'), '/');
            if ($permalink_structure === '' || strpos($permalink_structure, '%postname%') === false) {
                return;
            }
            if (strpos($permalink_structure, 'index.php/') === 0) {
                $permalink_structure = substr($permalink_structure, strlen('index.php/'));
            }
            if ($permalink_structure === '%postname%') {
                return;
            }

            $tokens = preg_split('/(%[^%]+%)/', $permalink_structure, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            $token_rules = array(
                '%year%' => array('([0-9]{4})', 'year'),
                '%monthnum%' => array('([0-9]{1,2})', 'monthnum'),
                '%day%' => array('([0-9]{1,2})', 'day'),
                '%hour%' => array('([0-9]{1,2})', 'hour'),
                '%minute%' => array('([0-9]{1,2})', 'minute'),
                '%second%' => array('([0-9]{1,2})', 'second'),
                '%post_id%' => array('([0-9]+)', 'p'),
                '%postname%' => array('([^/]+)', 'name'),
                '%category%' => array('(.+?)', 'category_name'),
                '%author%' => array('([^/]+)', 'author_name'),
            );
            $pattern = '';
            $query_parts = array();
            $match_index = 1;
            foreach ($tokens as $token) {
                if (isset($token_rules[$token])) {
                    $pattern .= $token_rules[$token][0];
                    $query_parts[] = $token_rules[$token][1] . '=$matches[' . $match_index . ']';
                    $match_index++;
                } else {
                    $pattern .= preg_quote($token, '/');
                }
            }
            $query_parts[] = 'lang=$matches[' . $match_index . ']';

            self::add_rewrite_rule_with_index_prefix(
                $pattern . '/lang/([^/]+)/?$',
                'index.php?' . implode('&', $query_parts)
            );
        }

        /**
         * Register language-aware rewrite rules for product archive.
         *
         * @return void
         */
        public static function register_product_archive_language_rule()
        {
            $shop_path = NpPath::get_product_archive_rewrite_path();
            if ($shop_path === '') {
                return;
            }

            self::add_rewrite_rule_with_index_prefix(
                preg_quote($shop_path, '/') . '/page/([0-9]{1,})/lang/([^/]+)/?$',
                'index.php?post_type=product&paged=$matches[1]&lang=$matches[2]'
            );

            self::add_rewrite_rule_with_index_prefix(
                preg_quote($shop_path, '/') . '/lang/([^/]+)/page/([0-9]{1,})/?$',
                'index.php?post_type=product&paged=$matches[2]&lang=$matches[1]'
            );

            self::add_rewrite_rule_with_index_prefix(
                preg_quote($shop_path, '/') . '/(?:feed/)?(feed|rdf|rss|rss2|atom)/lang/([^/]+)/?$',
                'index.php?post_type=product&feed=$matches[1]&lang=$matches[2]'
            );

            self::add_rewrite_rule_with_index_prefix(
                preg_quote($shop_path, '/') . '/lang/([^/]+)/?$',
                'index.php?post_type=product&lang=$matches[1]'
            );
        }

        /**
         * Register language-aware rewrite rules for single product URLs.
         *
         * @return void
         */
        public static function register_product_permalink_language_rule()
        {
            $product_path = NpPath::get_product_permalink_rewrite_pattern();
            if ($product_path === '') {
                return;
            }

            self::add_rewrite_rule_with_index_prefix(
                $product_path . '/([^/]+)/lang/([^/]+)/?$',
                'index.php?product=$matches[1]&lang=$matches[2]'
            );

            self::add_rewrite_rule_with_index_prefix(
                $product_path . '/([^/]+)(?:/([0-9]+))?/?$',
                'index.php?product=$matches[1]&page=$matches[2]'
            );
        }

        /**
         * Register language-aware rewrite rules for product category URLs.
         *
         * @return void
         */
        public static function register_product_category_language_rule()
        {
            $category_path = NpPath::get_product_category_rewrite_path();
            if ($category_path === '') {
                return;
            }

            $category_pattern = preg_quote($category_path, '/');
            self::add_rewrite_rule_with_index_prefix(
                $category_pattern . '/(.+?)/page/([0-9]{1,})/lang/([^/]+)/?$',
                'index.php?product_cat=$matches[1]&paged=$matches[2]&lang=$matches[3]'
            );
            self::add_rewrite_rule_with_index_prefix(
                $category_pattern . '/(.+?)/lang/([^/]+)/page/([0-9]{1,})/?$',
                'index.php?product_cat=$matches[1]&paged=$matches[3]&lang=$matches[2]'
            );
            self::add_rewrite_rule_with_index_prefix(
                $category_pattern . '/(.+?)/(?:feed/)?(feed|rdf|rss|rss2|atom)/lang/([^/]+)/?$',
                'index.php?product_cat=$matches[1]&feed=$matches[2]&lang=$matches[3]'
            );
            self::add_rewrite_rule_with_index_prefix(
                $category_pattern . '/(.+?)/lang/([^/]+)/?$',
                'index.php?product_cat=$matches[1]&lang=$matches[2]'
            );
        }

        /**
         * Register language-aware rewrite rules for login-related routes.
         *
         * @return void
         */
        public static function register_login_pages_language_rules()
        {
            $login_rules = array(
                'login' => 'login',
                'lostpassword' => 'lostpassword',
                'register' => 'register',
            );
            foreach ($login_rules as $slug => $action) {
                self::register_login_page_language_rule($slug, $action);
            }
        }

        /**
         * Register language-aware rewrite rule for one login page route.
         *
         * @param string $slug
         * @param string $action
         *
         * @return void
         */
        public static function register_login_page_language_rule($slug, $action)
        {
            $slug = trim((string) $slug, '/');
            $action = sanitize_key((string) $action);
            if ($slug === '') {
                return;
            }

            self::add_rewrite_rule_with_index_prefix(
                preg_quote($slug, '/') . '/?$',
                'index.php?action=' . $action
            );

            $slug_pattern = preg_quote($slug, '/') . '/lang/([^/]+)/?$';
            self::add_rewrite_rule_with_index_prefix(
                $slug_pattern,
                'index.php?action=' . $action . '&lang=$matches[1]'
            );
        }

        /**
         * Register custom route query vars in WordPress whitelist.
         *
         * @param array $vars
         *
         * @return array
         */
        public static function register_route_query_vars($vars)
        {
            $route_vars = array('products-list', 'product-id', 'thank-you', 'productsList', 'productId', 'thankYou', 'action', self::NP_ROUTE_QUERY_KEY);
            foreach ($route_vars as $route_var) {
                if (!in_array($route_var, $vars, true)) {
                    $vars[] = $route_var;
                }
            }

            return $vars;
        }

        /**
         * Register language-aware rewrite rules for Nicepage shop routes.
         *
         * @return void
         */
        public static function register_nicepage_shop_language_rules()
        {
            self::register_nicepage_shop_route_rule('products-list', 'products-list', false);
            self::register_nicepage_shop_route_rule('product-id', 'product-id', true);
            self::register_nicepage_shop_route_rule('thank-you', 'thank-you', true, true);
            self::register_nicepage_shop_route_rule('productsList', 'productsList', false);
            self::register_nicepage_shop_route_rule('productId', 'productId', true);
            self::register_nicepage_shop_route_rule('thankYou', 'thankYou', true, true);
        }

        /**
         * Register language-aware rewrite rules for one Nicepage shop route.
         *
         * @param string $query_key
         * @param string $path_slug
         * @param bool   $has_value
         * @param bool   $allow_empty_value
         *
         * @return void
         */
        public static function register_nicepage_shop_route_rule($query_key, $path_slug, $has_value, $allow_empty_value = false)
        {
            $path_slug = trim((string) $path_slug, '/');
            if ($path_slug === '') {
                return;
            }

            if ($has_value) {
                self::add_rewrite_rule_with_index_prefix(
                    preg_quote($path_slug, '/') . '/([^/]+)/lang/([^/]+)/?$',
                    'index.php?' . $query_key . '=$matches[1]&lang=$matches[2]'
                );
                self::add_rewrite_rule_with_index_prefix(
                    preg_quote($path_slug, '/') . '/([^/]+)/?$',
                    'index.php?' . $query_key . '=$matches[1]'
                );
                if ($allow_empty_value) {
                    self::add_rewrite_rule_with_index_prefix(
                        preg_quote($path_slug, '/') . '/lang/([^/]+)/?$',
                        'index.php?' . $query_key . '=1&lang=$matches[1]'
                    );
                    self::add_rewrite_rule_with_index_prefix(
                        preg_quote($path_slug, '/') . '/?$',
                        'index.php?' . $query_key . '=1'
                    );
                }
                return;
            }

            self::add_rewrite_rule_with_index_prefix(
                preg_quote($path_slug, '/') . '/([^/]+)/lang/([^/]+)/?$',
                'index.php?' . $query_key . '=$matches[1]&lang=$matches[2]'
            );
            self::add_rewrite_rule_with_index_prefix(
                preg_quote($path_slug, '/') . '/lang/([^/]+)/?$',
                'index.php?' . $query_key . '=1&lang=$matches[1]'
            );
            self::add_rewrite_rule_with_index_prefix(
                preg_quote($path_slug, '/') . '/([^/]+)/?$',
                'index.php?' . $query_key . '=$matches[1]'
            );
            self::add_rewrite_rule_with_index_prefix(
                preg_quote($path_slug, '/') . '/?$',
                'index.php?' . $query_key . '=1'
            );
        }

        /**
         * Add rewrite rules with and without index.php prefix.
         *
         * @param string $pattern
         * @param string $query
         *
         * @return void
         */
        private static function add_rewrite_rule_with_index_prefix($pattern, $query)
        {
            $query = self::append_np_route_query($query);
            add_rewrite_rule('^' . $pattern, $query, 'top');
            add_rewrite_rule('^index\.php/' . $pattern, $query, 'top');
        }

        /**
         * Move Nicepage route rules before generic rules in rewrite list.
         *
         * @param array $rules
         *
         * @return array
         */
        public static function prioritize_np_route_rewrite_rules($rules)
        {
            if (!is_array($rules)) {
                return $rules;
            }

            $np_rules = array();
            $np_fallback_rules = array();
            $other_rules = array();
            foreach ($rules as $pattern => $query) {
                if (is_string($query)
                    && strpos($query, self::NP_ROUTE_QUERY_KEY . '=' . self::NP_ROUTE_QUERY_VALUE) !== false
                ) {
                    if (strpos($query, 'pagename=') !== false) {
                        $np_fallback_rules[$pattern] = $query;
                    } else {
                        $np_rules[$pattern] = $query;
                    }
                } else {
                    $other_rules[$pattern] = $query;
                }
            }

            return $np_rules + $np_fallback_rules + $other_rules;
        }

        /**
         * Append internal route marker to rewrite query when absent.
         *
         * @param string $query
         *
         * @return string
         */
        private static function append_np_route_query($query)
        {
            if (!is_string($query) || $query === '') {
                return $query;
            }

            if (strpos($query, self::NP_ROUTE_QUERY_KEY . '=') !== false) {
                return $query;
            }

            return $query . '&' . self::NP_ROUTE_QUERY_KEY . '=' . self::NP_ROUTE_QUERY_VALUE;
        }
    }
}
