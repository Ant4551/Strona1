<?php
defined('ABSPATH') or die;

if (!class_exists('NpMultiLanguagesExternalCanonicalHelper')) {
    /**
     * Coordinates Nicepage language URLs with external multilingual plugins.
     * The helper detects plugins such as Polylang, WPML, and TranslatePress and redirects
     * Nicepage /lang/{code}/ aliases to the canonical URL format owned by those plugins.
     * This keeps legacy/internal Nicepage links from becoming duplicate or broken URLs.
     */
    class NpMultiLanguagesExternalCanonicalHelper
    {
        /**
         * Detect known external multilingual plugins.
         *
         * @return bool
         */
        public static function has_external_multilang()
        {
            return
                defined('POLYLANG_ACTIVE') ||
                defined('POLYLANG_VERSION') ||
                function_exists('pll_current_language') ||
                defined('ICL_SITEPRESS_VERSION') ||
                class_exists('SitePress') ||
                function_exists('icl_object_id') ||
                class_exists('TRP_Translate_Press');
        }

        /**
         * Resolve current language from active external multilingual plugin.
         *
         * @return string
         */
        public static function get_external_current_language()
        {
            if (function_exists('pll_current_language')) {
                $language = pll_current_language('slug');
                if (is_string($language) && $language !== '') {
                    return sanitize_key($language);
                }
            }

            if (defined('ICL_SITEPRESS_VERSION') || class_exists('SitePress') || function_exists('icl_object_id')) {
                $language = apply_filters('wpml_current_language', null);
                if (is_string($language) && $language !== '') {
                    return sanitize_key($language);
                }
                if (defined('ICL_LANGUAGE_CODE') && is_string(ICL_LANGUAGE_CODE) && ICL_LANGUAGE_CODE !== '') {
                    return sanitize_key(ICL_LANGUAGE_CODE);
                }
            }

            if (class_exists('TRP_Translate_Press')) {
                global $TRP_LANGUAGE;
                if (is_string($TRP_LANGUAGE) && $TRP_LANGUAGE !== '') {
                    return sanitize_key($TRP_LANGUAGE);
                }
            }

            return '';
        }

        /**
         * Redirect /lang/{code}/ aliases to external plugin canonical URL.
         *
         * @param string|null $owner_class
         *
         * @return void
         */
        public static function redirect_external_multilang_alias_url($owner_class = null)
        {
            if (!self::has_external_multilang() || NpMultiLanguagesHybridRoutingRuntimeHelper::is_np_route_request()) {
                return;
            }
            if (is_string($owner_class)
                && $owner_class !== ''
                && method_exists($owner_class, 'should_use_internal_language')
                && call_user_func(array($owner_class, 'should_use_internal_language'))
            ) {
                return;
            }
            if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
                return;
            }

            $language = NpMultiLanguagesHybridRoutingRuntimeHelper::get_language_from_request_path();
            if (!$language) {
                return;
            }

            $current_url = self::get_current_request_url();
            if (!$current_url) {
                return;
            }

            $clean_url = self::remove_language_from_url($current_url);
            if (!$clean_url || $clean_url === $current_url) {
                return;
            }

            $target_url = self::build_external_canonical_url($clean_url, $language);
            if (!$target_url || self::urls_are_equal($target_url, $current_url)) {
                return;
            }

            wp_safe_redirect($target_url, 301);
            exit;
        }

        /**
         * Build absolute URL for current HTTP request.
         *
         * @return string
         */
        private static function get_current_request_url()
        {
            if (!isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['REQUEST_URI'])) {
                return '';
            }

            $scheme = is_ssl() ? 'https://' : 'http://';
            return $scheme . (string) wp_unslash($_SERVER['HTTP_HOST']) . (string) wp_unslash($_SERVER['REQUEST_URI']);
        }

        /**
         * Remove /lang/{code}/ suffix from URL path.
         *
         * @param string $url
         *
         * @return string
         */
        private static function remove_language_from_url($url)
        {
            $parts = wp_parse_url(html_entity_decode((string) $url));
            if (!is_array($parts)) {
                return '';
            }

            $path = isset($parts['path']) ? NpPath::remove_language_from_path($parts['path']) : '/';
            return NpUrlHelper::build_url_from_parts($parts, $path);
        }

        /**
         * Resolve canonical translated URL through external plugin APIs.
         *
         * @param string $url
         * @param string $language
         *
         * @return string
         */
        private static function build_external_canonical_url($url, $language)
        {
            $language = sanitize_key((string) $language);
            if (!$language) {
                return '';
            }

            if (defined('ICL_SITEPRESS_VERSION') || class_exists('SitePress') || function_exists('icl_object_id')) {
                $resolved = apply_filters('wpml_permalink', $url, $language, true);
                if (is_string($resolved) && $resolved !== '') {
                    return $resolved;
                }
            }

            if (class_exists('TRP_Translate_Press')) {
                $resolved = self::resolve_translatepress_url($url, $language);
                if ($resolved) {
                    return $resolved;
                }
            }

            if (function_exists('pll_current_language')) {
                $resolved = self::resolve_polylang_url($url, $language);
                if ($resolved) {
                    return $resolved;
                }
            }

            return $url;
        }

        /**
         * Build translated URL via TranslatePress URL converter.
         *
         * @param string $url
         * @param string $language
         *
         * @return string
         */
        private static function resolve_translatepress_url($url, $language)
        {
            if (!class_exists('TRP_Translate_Press') || !method_exists('TRP_Translate_Press', 'get_trp_instance')) {
                return '';
            }

            $language_code = self::resolve_translatepress_language_code($language);

            $trp = TRP_Translate_Press::get_trp_instance();
            if (!is_object($trp) || !method_exists($trp, 'get_component')) {
                return '';
            }

            $url_converter = $trp->get_component('url_converter');
            if (!is_object($url_converter) || !method_exists($url_converter, 'get_url_for_language')) {
                return '';
            }

            $resolved = $url_converter->get_url_for_language($language_code, $url, '');
            return is_string($resolved) ? $resolved : '';
        }

        /**
         * Map short language slug to TranslatePress language code.
         *
         * @param string $language
         *
         * @return string
         */
        private static function resolve_translatepress_language_code($language)
        {
            $settings = get_option('trp_settings');
            if (!is_array($settings)) {
                return $language;
            }

            $languages = array();
            if (isset($settings['publish-languages']) && is_array($settings['publish-languages'])) {
                $languages = array_merge($languages, $settings['publish-languages']);
            }
            if (isset($settings['translation-languages']) && is_array($settings['translation-languages'])) {
                $languages = array_merge($languages, $settings['translation-languages']);
            }
            if (isset($settings['default-language']) && is_string($settings['default-language'])) {
                $languages[] = $settings['default-language'];
            }

            $needle = strtolower((string) $language);
            foreach ($languages as $candidate) {
                $candidate_string = strtolower((string) $candidate);
                if ($candidate_string === $needle || strpos($candidate_string, $needle . '_') === 0) {
                    return (string) $candidate;
                }
            }

            return $language;
        }

        /**
         * Build translated URL via Polylang helpers and language list.
         *
         * @param string $url
         * @param string $language
         *
         * @return string
         */
        private static function resolve_polylang_url($url, $language)
        {
            if (function_exists('pll_the_languages')) {
                $translations = pll_the_languages(array('raw' => 1, 'hide_if_no_translation' => 0));
                if (is_array($translations)) {
                    foreach ($translations as $translation) {
                        if (!is_array($translation)) {
                            continue;
                        }
                        if (isset($translation['slug']) && $translation['slug'] === $language && !empty($translation['url'])) {
                            return (string) $translation['url'];
                        }
                    }
                }
            }

            if (!function_exists('pll_home_url')) {
                return '';
            }

            $home = trailingslashit(home_url('/'));
            $lang_home = trailingslashit(pll_home_url($language));

            if (strpos($url, $home) !== 0) {
                return '';
            }

            $relative = ltrim(substr($url, strlen($home)), '/');
            return $lang_home . $relative;
        }

        /**
         * Compare two URLs ignoring trailing slash differences.
         *
         * @param string $first
         * @param string $second
         *
         * @return bool
         */
        private static function urls_are_equal($first, $second)
        {
            $first = rtrim((string) $first, '/');
            $second = rtrim((string) $second, '/');
            return $first === $second;
        }
    }
}
