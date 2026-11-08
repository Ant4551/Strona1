<?php
defined('ABSPATH') or die;

require_once dirname(__FILE__) . '/../../processors/translations/class-np-path.php';
require_once dirname(__FILE__) . '/../../processors/translations/class-np-multi-languages-hybrid-routing-register-helper.php';
require_once dirname(__FILE__) . '/../../processors/translations/class-np-multi-languages-hybrid-routing-runtime-helper.php';
require_once dirname(__FILE__) . '/../../processors/translations/class-np-multi-languages-runtime-helper.php';
require_once dirname(__FILE__) . '/../../processors/translations/class-np-multi-languages-hybrid-url-server-helper.php';
require_once dirname(__FILE__) . '/../../processors/translations/class-np-multi-languages-hybrid-url-script-helper.php';
require_once dirname(__FILE__) . '/../../processors/translations/class-np-multi-languages-external-canonical-helper.php';
require_once dirname(__FILE__) . '/../../processors/translations/class-np-multi-languages-resolver.php';
require_once dirname(__FILE__) . '/../../processors/translations/class-np-multi-languages-known-links-helper.php';

class ThemeMultiLanguages {

    const LANG_REWRITE_VERSION = 'hybrid13';

	/**
	 * Pre init
	 */
	public static function pre_init() {
        $is_multilingual = self::is_multilingual();
        $should_bootstrap_routes = self::should_bootstrap_routes();
        $has_external_multilang = self::has_external_multilang();
        add_action('admin_init', array('NpPath', 'maybe_update_multilang_url_mode_from_permalink_save'));
        add_action('update_option_permalink_structure', array('NpPath', 'update_multilang_url_mode_from_permalinks'));
        if ($should_bootstrap_routes) {
            add_action('init', array(__CLASS__, 'register_language_endpoint'), 0);
            add_filter('query_vars', array(__CLASS__, 'register_route_query_vars'));
            add_filter('rewrite_rules_array', array(__CLASS__, 'prioritize_np_route_rewrite_rules'), 0);
        }
		if (!is_admin()) {
            if ($should_bootstrap_routes) {
                add_action('parse_request', array(__CLASS__, 'initialize_request_language_from_parse_request'), 0);
                add_action('parse_request', array(__CLASS__, 'normalize_legacy_route_query_vars'), 1);
                add_action('template_redirect', array(__CLASS__, 'redirect_plain_language_path_to_query'), 0);
            }
            if ($is_multilingual) {
                add_action('send_headers', array(__CLASS__, 'set_language_cookie'));
                if (!$has_external_multilang) {
                    add_action('wp_head', array(__CLASS__, 'disable_server_cache_for_links_script'));
                    add_action('template_redirect', array(__CLASS__, 'language_redirect'));
                    add_filter('redirect_canonical', array(__CLASS__, 'preserve_language_front_page_url'), 10, 2);
                    add_filter('locale', array(__CLASS__, 'set_my_locale'));
                } else {
                    add_action('template_redirect', array(__CLASS__, 'redirect_external_multilang_alias_url'), 1);
                }
            }
		}
        if ($is_multilingual) {
            self::init_category_translations();
        }
	}

    /**
     * Check whether more than one theme language is available.
     *
     * @return bool
     */
    public static function is_multilingual()
    {
        return count(self::get_supported_langs()) > 1;
    }

    /**
     * Check whether Nicepage custom routes should be bootstrapped.
     *
     * @return bool
     */
    public static function should_bootstrap_routes()
    {
        return self::is_multilingual() || NpPath::use_path_language_urls();
    }

    /**
     * Check whether this multilingual implementation supports hybrid routing.
     *
     * @return bool
     */
    public static function supports_hybrid_routing()
    {
        return self::LANG_REWRITE_VERSION === 'hybrid13';
    }

    /**
     * Check if known external multilingual plugins are active.
     *
     * @return bool
     */
    public static function has_external_multilang()
    {
        return NpMultiLanguagesExternalCanonicalHelper::has_external_multilang();
    }

    /**
     * Get current language from external multilingual plugin.
     *
     * @return string $language
     */
    public static function get_external_current_language()
    {
        return NpMultiLanguagesExternalCanonicalHelper::get_external_current_language();
    }

    /**
     * Redirect alias language URL to external plugin canonical URL.
     *
     * @return void
     */
    public static function redirect_external_multilang_alias_url()
    {
        NpMultiLanguagesExternalCanonicalHelper::redirect_external_multilang_alias_url(__CLASS__);
    }

    /**
     * Redirect path language URLs to query format when plain permalinks are active.
     *
     * @return void
     */
    public static function redirect_plain_language_path_to_query()
    {
        NpMultiLanguagesHybridUrlServerHelper::redirect_plain_language_path_to_query(__CLASS__);
    }

    /**
     * Check whether current request should use Site54 internal language flow.
     *
     * @return bool
     */
    public static function should_use_internal_language()
    {
        if (NpMultiLanguagesHybridRoutingRuntimeHelper::is_np_route_request()) {
            return true;
        }

        return NpMultiLanguagesHybridRoutingRuntimeHelper::get_requested_language(__CLASS__) !== '';
    }

    /**
     * Register language rewrite endpoint and rules.
     *
     * @return void
     */
    public static function register_language_endpoint()
    {
        NpMultiLanguagesHybridRoutingRegisterHelper::register_language_endpoint(self::LANG_REWRITE_VERSION);
    }

    /**
     * Register custom route query vars.
     *
     * @param array $vars
     *
     * @return array $vars
     */
    public static function register_route_query_vars($vars)
    {
        return NpMultiLanguagesHybridRoutingRegisterHelper::register_route_query_vars($vars);
    }

    /**
     * Move Nicepage route rewrite rules before generic ones.
     *
     * @param array $rules
     *
     * @return array $rules
     */
    public static function prioritize_np_route_rewrite_rules($rules)
    {
        return NpMultiLanguagesHybridRoutingRegisterHelper::prioritize_np_route_rewrite_rules($rules);
    }

    /**
     * Normalize legacy route query vars in parse_request phase.
     *
     * @param WP $wp
     *
     * @return void
     */
    public static function normalize_legacy_route_query_vars($wp)
    {
        NpMultiLanguagesHybridRoutingRuntimeHelper::normalize_legacy_route_query_vars($wp);
    }

    /**
     * Get original Nicepage route source.
     *
     * @param string $default
     *
     * @return string
     */
    public static function get_np_route_source($default = 'plugin')
    {
        return NpMultiLanguagesHybridRoutingRuntimeHelper::get_route_source($default);
    }

    /**
     * Initialize request language during parse_request.
     *
     * @param WP $wp
     *
     * @return void
     */
    public static function initialize_request_language_from_parse_request($wp)
    {
        NpMultiLanguagesHybridRoutingRuntimeHelper::initialize_request_language_from_parse_request($wp, __CLASS__);
    }

    /**
     * Get blog archive URL for current permalink mode.
     *
     * @return string $url
     */
    public static function get_blog_archive_url()
    {
        if (self::should_use_plugin_legacy_urls()) {
            return get_option('show_on_front') === 'posts' ? get_home_url() : home_url('/?post_type=post');
        }

        return NpPath::get_blog_archive_url();
    }

    /**
     * Build Nicepage route URL.
     *
     * @param string     $route
     * @param string|int $value
     * @param string     $source
     *
     * @return string $url
     */
    public static function build_np_route_url($route, $value = '', $source = 'theme')
    {
        if (self::should_use_plugin_legacy_urls()) {
            return self::build_legacy_np_route_url($route, $value, $source);
        }

        return NpPath::build_np_route_url($route, $value, $source);
    }

    /**
     * Build Nicepage products category URL.
     *
     * @param string|int $category_id
     * @param string     $source
     *
     * @return string $url
     */
    public static function build_np_products_category_url($category_id, $source = 'theme')
    {
        if (self::should_use_plugin_legacy_urls()) {
            return self::build_legacy_np_route_url('products', '', $source) . '#/1///' . rawurlencode((string) $category_id);
        }

        return NpPath::build_np_products_category_url($category_id, $source);
    }

    /**
     * Prevent canonical redirect from dropping language on front page.
     *
     * @param string $redirect_url
     *
     * @return string|false $redirect_url
     */
    public static function preserve_language_front_page_url($redirect_url)
    {
        return NpMultiLanguagesHybridRoutingRuntimeHelper::preserve_language_front_page_url($redirect_url, __CLASS__);
    }

    /**
     * Get requested language from request context.
     *
     * @return string $language
     */
    public static function get_requested_language()
    {
        return NpMultiLanguagesRuntimeHelper::get_requested_language(__CLASS__);
    }

    /**
     * Get effective current language.
     *
     * @return string $language
     */
    public static function get_current_language()
    {
        return NpMultiLanguagesRuntimeHelper::get_current_language(__CLASS__);
    }

    /**
     * Build language URL from base URL.
     *
     * @param string $url
     * @param string $language
     *
     * @return string $url
     */
    public static function build_language_url($url, $language)
    {
        if (self::should_use_plugin_legacy_urls()) {
            $url = remove_query_arg('lang', $url);
            return add_query_arg('lang', $language, $url);
        }

        return NpMultiLanguagesHybridUrlServerHelper::build_language_url($url, $language);
    }

    /**
     * Check whether an active old plugin should keep legacy query URLs.
     *
     * @return bool
     */
    private static function should_use_plugin_legacy_urls()
    {
        return class_exists('NpMultiLanguages') && !self::active_plugin_supports_hybrid_routing();
    }

    /**
     * Check if active plugin supports the same hybrid routing version as the theme.
     *
     * @return bool
     */
    private static function active_plugin_supports_hybrid_routing()
    {
        return method_exists('NpMultiLanguages', 'supports_hybrid_routing') && call_user_func(array('NpMultiLanguages', 'supports_hybrid_routing'));
    }

    /**
     * Build legacy query URL for Nicepage routes.
     *
     * @param string     $route
     * @param string|int $value
     * @param string     $source
     *
     * @return string $url
     */
    private static function build_legacy_np_route_url($route, $value = '', $source = 'theme')
    {
        $source = $source === 'plugin' ? 'plugin' : 'theme';
        $query_key_map = array(
            'products' => $source === 'plugin' ? 'productsList' : 'products-list',
            'product' => $source === 'plugin' ? 'productId' : 'product-id',
            'thankYou' => $source === 'plugin' ? 'thankYou' : 'thank-you',
        );

        if (!isset($query_key_map[$route])) {
            return home_url('/');
        }

        return add_query_arg(array($query_key_map[$route] => (string) $value), home_url('/'));
    }

    /**
     * Disable server cache for links
     */
    public static function disable_server_cache_for_links_script()
    {
        NpMultiLanguagesHybridUrlScriptHelper::disable_server_cache_for_links_script(__CLASS__);
    }

	/**
	 * Get supported languages
	 *
	 * @return array $supported_langs
	 */
	public static function get_supported_langs() {
		$supported_langs = json_decode('["en"]', true);  // format: 'en','de'
		return $supported_langs;
	}

	/**
	 * Get default np language
	 *
	 * @return string $lang
	 */
	public static function get_np_default_lang() {
		$lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : 'en';
		$default_lang = 'en';
		if ($default_lang !== '{default_lang}') {
			$lang = $default_lang;
		}
		return $lang;
	}

	/**
	 * Set cookie language
	 */
	public static function set_language_cookie() {
        NpMultiLanguagesRuntimeHelper::set_language_cookie(__CLASS__);
	}

	/**
	 * Redirect by cookie language
	 */
    public static function language_redirect() {
        NpMultiLanguagesHybridUrlServerHelper::language_redirect(__CLASS__, false);
	}

	/**
	 * Change language on the live site
	 *
	 * @param string $lang
	 *
	 * @return string $lang
	 */
	public static function set_my_locale($lang) {
        return NpMultiLanguagesRuntimeHelper::set_my_locale($lang, __CLASS__);
	}

	/**
	 * Get wordpress locale code by np language code
	 *
	 * @param string $lang
	 *
	 * @return string $lang
	 */
	public static function get_locale_by_lang($lang) {
        return NpMultiLanguagesRuntimeHelper::get_locale_by_lang($lang);
	}

    /**
     * Init category filter option translation
     */
    public static function init_category_translations()
    {
        NpMultiLanguagesRuntimeHelper::init_category_translations(__CLASS__);
    }

    /**
     * Render fields for translation in admin add/edit category
     *
     * @param WP_Term|null $term
     */
    public static function render_lang_fields($term = null)
    {
        NpMultiLanguagesRuntimeHelper::render_lang_fields($term, __CLASS__);
    }

    /**
     * Save translation fields in admin
     *
     * @param int $term_id  Term ID.
     */
    public static function save_term_lang_names($term_id)
    {
        NpMultiLanguagesRuntimeHelper::save_term_lang_names($term_id);
    }

    /**
     * Get translation for category
     *
     * @param WP_Term $term
     *
     * @return string
     */
    public static function get_term_name_by_lang($term)
    {
        return NpMultiLanguagesRuntimeHelper::get_term_name_by_lang($term, __CLASS__);
    }
}

/**
 * Resolves theme-owned multilingual URLs through the shared Nicepage resolver.
 * The class forces common URL builders to use ThemeMultiLanguages as the owner,
 * so an active Nicepage plugin cannot accidentally override theme-only language settings.
 * It keeps exported themes independent when the plugin is missing or older than the theme.
 */
class ThemeMultiLanguagesResolver
{
    /**
     * Get blog archive URL from theme multilingual context.
     *
     * @return string
     */
    public static function get_blog_archive_url()
    {
        return NpMultiLanguagesResolver::get_blog_archive_url('ThemeMultiLanguages');
    }

    /**
     * Build Nicepage route URL from theme multilingual context.
     *
     * @param string $route
     * @param string $value
     * @param string $source
     *
     * @return string
     */
    public static function build_np_route_url($route, $value = '', $source = 'theme')
    {
        return NpMultiLanguagesResolver::build_np_route_url($route, $value, $source, 'ThemeMultiLanguages');
    }

    /**
     * Build products category URL from theme multilingual context.
     *
     * @param int|string $category_id
     * @param string     $source
     *
     * @return string
     */
    public static function build_np_products_category_url($category_id, $source = 'theme')
    {
        return NpMultiLanguagesResolver::build_np_products_category_url($category_id, $source, 'ThemeMultiLanguages');
    }

    /**
     * Get original Nicepage route source from theme multilingual context.
     *
     * @param string $default
     *
     * @return string
     */
    public static function get_np_route_source($default = 'plugin')
    {
        return NpMultiLanguagesResolver::get_np_route_source($default, 'ThemeMultiLanguages');
    }

    /**
     * Get current language from theme multilingual context.
     *
     * @param string $fallback
     *
     * @return string
     */
    public static function get_current_language($fallback = '')
    {
        return NpMultiLanguagesResolver::get_current_language($fallback, 'ThemeMultiLanguages');
    }

    /**
     * Get default language from theme multilingual context.
     *
     * @param string $fallback
     *
     * @return string
     */
    public static function get_default_language($fallback = 'en')
    {
        return NpMultiLanguagesResolver::get_default_language($fallback, 'ThemeMultiLanguages');
    }
}

if (!function_exists('getLanguageLink')) {
	function getLanguageLink($language = 'en') {
        $current_url = (is_ssl() ? 'https://' : 'http://')
            . (isset($_SERVER['HTTP_HOST']) ? wp_unslash($_SERVER['HTTP_HOST']) : '')
            . (isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '');
        return ThemeMultiLanguages::build_language_url($current_url, $language);
    }
}

if (!class_exists('NpMultiLanguages')) {
	ThemeMultiLanguages::pre_init();
}
