<?php
defined('ABSPATH') or die;

if (!class_exists('NpMultiLanguagesRuntimeHelper')) {
    /**
     * Holds runtime language behavior shared by Nicepage theme and plugin classes.
     * The helper resolves the active language, maps it to WordPress locales, stores cookies,
     * and renders/saves translated taxonomy labels used by exported multilingual templates.
     * Owner classes pass themselves in so theme and plugin can still work independently.
     */
    class NpMultiLanguagesRuntimeHelper
    {
        /**
         * Resolve requested language from internal or external multilingual context.
         *
         * @param string $owner_class
         *
         * @return string
         */
        public static function get_requested_language($owner_class)
        {
            if (self::has_external_multilang($owner_class) && !self::should_use_internal_language($owner_class)) {
                $language = self::get_external_current_language($owner_class);
                if ($language) {
                    return self::resolve_supported_language($language, $owner_class);
                }
            }

            return NpMultiLanguagesHybridRoutingRuntimeHelper::get_requested_language($owner_class);
        }

        /**
         * Resolve current language from internal or external multilingual context.
         *
         * @param string $owner_class
         *
         * @return string
         */
        public static function get_current_language($owner_class)
        {
            if (self::has_external_multilang($owner_class) && !self::should_use_internal_language($owner_class)) {
                $language = self::get_external_current_language($owner_class);
                if ($language) {
                    return self::resolve_supported_language($language, $owner_class);
                }
            }

            return NpMultiLanguagesHybridRoutingRuntimeHelper::get_current_language($owner_class);
        }

        /**
         * Resolve language to supported set with fallback to default.
         *
         * @param string $language
         * @param string $owner_class
         *
         * @return string
         */
        public static function resolve_supported_language($language, $owner_class)
        {
            $language = sanitize_key((string) $language);
            if (!$language) {
                return '';
            }

            $supported_langs = call_user_func(array($owner_class, 'get_supported_langs'));
            if (!is_array($supported_langs)) {
                $supported_langs = (array) $supported_langs;
            }

            if (in_array($language, $supported_langs, true)) {
                return $language;
            }

            return call_user_func(array($owner_class, 'get_np_default_lang'));
        }

        /**
         * Set language cookie for the current request language.
         *
         * @param string $owner_class
         *
         * @return void
         */
        public static function set_language_cookie($owner_class)
        {
            if (self::has_external_multilang($owner_class) && !self::should_use_internal_language($owner_class)) {
                return;
            }

            $language = call_user_func(array($owner_class, 'get_requested_language'));
            $cookie_language = isset($_COOKIE['np_lang']) && is_string($_COOKIE['np_lang'])
                ? sanitize_key(wp_unslash($_COOKIE['np_lang']))
                : '';
            if ($language && $cookie_language !== $language) {
                setcookie('np_lang', $language, time() + 3 * YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
                $_COOKIE['np_lang'] = $language;
            }
        }

        /**
         * Set WordPress locale from the current Nicepage language.
         *
         * @param string $lang
         * @param string $owner_class
         *
         * @return string
         */
        public static function set_my_locale($lang, $owner_class)
        {
            $language = call_user_func(array($owner_class, 'get_current_language'));
            if ($language) {
                return call_user_func(array($owner_class, 'get_locale_by_lang'), $language);
            }

            return $lang;
        }

        /**
         * Get WordPress locale code by Nicepage language code.
         *
         * @param string $lang
         *
         * @return string
         */
        public static function get_locale_by_lang($lang)
        {
            $default_locale = 'en_US';
            $associations = array(
                'aa' => $default_locale,
                'ab' => $default_locale,
                'ae' => $default_locale,
                'af' => 'af',
                'ak' => 'ak',
                'am' => 'am',
                'an' => $default_locale,
                'ar' => 'ar',
                'as' => 'as',
                'av' => $default_locale,
                'ay' => $default_locale,
                'az' => 'az',
                'ba' => 'ba',
                'be' => 'bel',
                'bg' => 'bg_BG',
                'bh' => $default_locale,
                'bi' => $default_locale,
                'bm' => $default_locale,
                'bn' => 'bn_BD',
                'bo' => 'bo',
                'br' => 'bre',
                'bs' => 'bs_BA',
                'ca' => 'ca',
                'ce' => $default_locale,
                'ch' => $default_locale,
                'co' => 'co',
                'cr' => $default_locale,
                'cs' => 'cs_CZ',
                'cv' => $default_locale,
                'cy' => 'cy',
                'da' => 'da_DK',
                'de' => 'de_DE',
                'dz' => 'dzo',
                'ee' => $default_locale,
                'el' => 'el',
                'en' => $default_locale,
                'eo' => 'eo',
                'es' => 'es_ES',
                'et' => 'et',
                'eu' => 'eu',
                'fa' => 'fa_IR',
                'fi' => 'fi',
                'fj' => $default_locale,
                'fo' => 'fo',
                'fr' => 'fr_FR',
                'fy' => 'fy',
                'ga' => 'ga',
                'gd' => 'gd',
                'gl' => 'gl_ES',
                'gn' => 'gn',
                'gu' => 'gu',
                'ha' => $default_locale,
                'he' => 'he_IL',
                'hi' => 'hi_IN',
                'ho' => $default_locale,
                'hr' => 'hr',
                'ht' => $default_locale,
                'hu' => 'hu_HU',
                'hy' => 'hy',
                'hz' => $default_locale,
                'ia' => $default_locale,
                'id' => 'id_ID',
                'ie' => $default_locale,
                'ig' => $default_locale,
                'ii' => $default_locale,
                'ik' => $default_locale,
                'io' => $default_locale,
                'is' => 'is_IS',
                'it' => 'it_IT',
                'iu' => $default_locale,
                'ja' => 'ja',
                'jv' => 'jv_ID',
                'ka' => 'ka_GE',
                'kg' => $default_locale,
                'ki' => $default_locale,
                'kj' => $default_locale,
                'kk' => 'kk',
                'kl' => $default_locale,
                'km' => 'km',
                'kn' => 'kn',
                'ko' => 'ko_KR',
                'kr' => $default_locale,
                'ks' => $default_locale,
                'ku' => $default_locale,
                'kv' => $default_locale,
                'kw' => $default_locale,
                'ky' => 'ky_KY',
                'la' => $default_locale,
                'lb' => 'lb_LU',
                'lg' => $default_locale,
                'li' => 'li',
                'ln' => $default_locale,
                'lo' => 'lo',
                'lt' => 'lt_LT',
                'lv' => 'lv',
                'mg' => 'mg_MG',
                'mh' => $default_locale,
                'mi' => $default_locale,
                'mk' => 'mk_MK',
                'ml' => 'ml_IN',
                'mn' => 'mn',
                'mo' => $default_locale,
                'mr' => 'mr',
                'ms' => 'ms_MY',
                'mt' => $default_locale,
                'my' => 'my_MM',
                'na' => $default_locale,
                'nd' => $default_locale,
                'ne' => 'ne_NP',
                'ng' => $default_locale,
                'nl' => 'nl_NL',
                'no' => $default_locale,
                'nr' => $default_locale,
                'nv' => $default_locale,
                'oc' => 'oci',
                'oj' => $default_locale,
                'or' => 'ory',
                'os' => 'os',
                'pa' => 'pa_IN',
                'pi' => $default_locale,
                'pl' => 'pl_PL',
                'ps' => 'ps',
                'pt' => 'pt_PT',
                'qu' => $default_locale,
                'rm' => $default_locale,
                'rn' => $default_locale,
                'ro' => 'ro_RO',
                'ru' => 'ru_RU',
                'rw' => $default_locale,
                'sa' => 'sa_IN',
                'sd' => $default_locale,
                'se' => $default_locale,
                'sg' => $default_locale,
                'si' => 'si_LK',
                'sk' => 'sk_SK',
                'sl' => 'sl_SI',
                'sm' => $default_locale,
                'sn' => $default_locale,
                'so' => 'so_SO',
                'sq' => 'sq',
                'sr' => 'sr_RS',
                'ss' => $default_locale,
                'st' => $default_locale,
                'su' => 'su_ID',
                'sv' => 'sv_SE',
                'sw' => 'sw',
                'ta' => 'ta_IN',
                'te' => 'te',
                'tg' => 'tg',
                'th' => 'th',
                'ti' => 'tir',
                'tk' => $default_locale,
                'tl' => 'tl',
                'tn' => $default_locale,
                'to' => $default_locale,
                'tr' => 'tr_TR',
                'ts' => $default_locale,
                'tt' => 'tt_RU',
                'tw' => $default_locale,
                'ty' => $default_locale,
                'ug' => 'ug_CN',
                'uk' => 'uk',
                'ur' => 'ur',
                'uz' => 'uz_UZ',
                've' => $default_locale,
                'vi' => 'vi',
                'vo' => $default_locale,
                'wa' => 'wa',
                'wo' => $default_locale,
                'xh' => $default_locale,
                'yi' => $default_locale,
                'yo' => 'yor',
                'za' => $default_locale,
                'zh' => 'zh_CN',
                'zu' => $default_locale,
            );

            return isset($associations[$lang]) ? $associations[$lang] : $default_locale;
        }

        /**
         * Init category filter option translation hooks.
         *
         * @param string $owner_class
         *
         * @return void
         */
        public static function init_category_translations($owner_class)
        {
            // WooCommerce
            add_action('product_cat_add_form_fields', array($owner_class, 'render_lang_fields'), 10);
            add_action('product_cat_edit_form_fields', array($owner_class, 'render_lang_fields'), 11);
            add_action('created_product_cat', array($owner_class, 'save_term_lang_names'));
            add_action('edited_product_cat', array($owner_class, 'save_term_lang_names'));
            // Blog
            add_action('category_add_form_fields', array($owner_class, 'render_lang_fields'), 10);
            add_action('category_edit_form_fields', array($owner_class, 'render_lang_fields'), 11);
            add_action('created_category', array($owner_class, 'save_term_lang_names'));
            add_action('edited_category', array($owner_class, 'save_term_lang_names'));
        }

        /**
         * Render fields for translation in admin add/edit category.
         *
         * @param WP_Term|null $term
         * @param string       $owner_class
         *
         * @return void
         */
        public static function render_lang_fields($term = null, $owner_class = '')
        {
            $langs = call_user_func(array($owner_class, 'get_supported_langs'));
            $default_lang = call_user_func(array($owner_class, 'get_np_default_lang'));
            if (count($langs) < 2) {
                return;
            }

            foreach ($langs as $lang) {
                if ($lang === $default_lang) {
                    continue;
                }
                $value = (is_object($term) && isset($term->term_id))
                    ? get_term_meta($term->term_id, '_name_' . $lang, true)
                    : '';
                if (is_object($term)) {
                    ?>
                    <tr class="form-field">
                        <th scope="row">
                            <label><?php echo 'Name (' . strtoupper($lang) . ')'; ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="np_term_name[<?php echo esc_attr($lang); ?>]"
                                   value="<?php echo esc_attr($value); ?>"
                                   class="regular-text">
                        </td>
                    </tr>
                    <?php
                } else {
                    ?>
                    <div class="form-field">
                        <label><?php echo 'Name (' . strtoupper($lang) . ')'; ?></label>
                        <input type="text" name="np_term_name[<?php echo esc_attr($lang); ?>]" value="">
                    </div>
                    <?php
                }
            }
        }

        /**
         * Save translation fields in admin.
         *
         * @param int $term_id Term ID.
         *
         * @return void
         */
        public static function save_term_lang_names($term_id)
        {
            if (empty($_POST['np_term_name']) || !is_array($_POST['np_term_name'])) {
                return;
            }
            foreach ($_POST['np_term_name'] as $lang => $value) {
                update_term_meta(
                    $term_id,
                    '_name_' . sanitize_key($lang),
                    sanitize_text_field($value)
                );
            }
        }

        /**
         * Get translation for category.
         *
         * @param WP_Term $term
         * @param string  $owner_class
         *
         * @return string
         */
        public static function get_term_name_by_lang($term, $owner_class)
        {
            if (!is_object($term)) {
                return '';
            }

            $default_lang = call_user_func(array($owner_class, 'get_np_default_lang'));
            $current_lang = call_user_func(array($owner_class, 'get_current_language'));
            if (!$current_lang) {
                $current_lang = $default_lang;
            }

            $translated = get_term_meta($term->term_id, '_name_' . $current_lang, true);

            return $translated ?: $term->name;
        }

        /**
         * Check whether external multilingual plugin is active.
         *
         * @param string $owner_class
         *
         * @return bool
         */
        private static function has_external_multilang($owner_class)
        {
            return call_user_func(array($owner_class, 'has_external_multilang'));
        }

        /**
         * Check whether current request should use internal Nicepage language.
         *
         * @param string $owner_class
         *
         * @return bool
         */
        private static function should_use_internal_language($owner_class)
        {
            return call_user_func(array($owner_class, 'should_use_internal_language'));
        }

        /**
         * Get current language from external multilingual plugin.
         *
         * @param string $owner_class
         *
         * @return string
         */
        private static function get_external_current_language($owner_class)
        {
            return call_user_func(array($owner_class, 'get_external_current_language'));
        }
    }
}
