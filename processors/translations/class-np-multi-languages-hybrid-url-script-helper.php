<?php
defined('ABSPATH') or die;

if (!class_exists('NpMultiLanguagesHybridUrlScriptHelper')) {
    /**
     * Outputs browser-side link normalization for Nicepage multilingual pages.
     * The script keeps generated links on the current language when HTML was rendered
     * before the final request URL was known, especially for editable templates and CMS grids.
     * Server-side builders remain primary; this helper repairs links that only exist in markup.
     */
    class NpMultiLanguagesHybridUrlScriptHelper
    {
        /**
         * Inject client-side URL normalizer to rewrite links with current language.
         *
         * @param string $owner_class
         *
         * @return void
         */
        public static function disable_server_cache_for_links_script($owner_class)
        {
            $language = call_user_func(array($owner_class, 'get_current_language'));
            if (!$language) {
                $language = call_user_func(array($owner_class, 'get_np_default_lang'));
            }
            $supported_langs = call_user_func(array($owner_class, 'get_supported_langs'));
            $site_url = get_site_url();
            $use_path_language_urls = NpPath::use_path_language_urls();
            $use_index_permalinks = NpPath::use_index_permalinks();
            global $wp_rewrite;
            $pagination_base = (isset($wp_rewrite) && is_object($wp_rewrite) && isset($wp_rewrite->pagination_base) && is_string($wp_rewrite->pagination_base) && $wp_rewrite->pagination_base !== '')
                ? $wp_rewrite->pagination_base
                : 'page';
            ?>
            <script>
                function normalizePathname(pathname) {
                    pathname = pathname || '/';
                    return pathname.charAt(0) === '/' ? pathname : '/' + pathname;
                }

                function getPathSegments(pathname) {
                    pathname = normalizePathname(pathname);
                    var rawSegments = pathname.split('/');
                    var segments = [];
                    for (var i = 0; i < rawSegments.length; i++) {
                        if (rawSegments[i]) {
                            segments.push(rawSegments[i]);
                        }
                    }
                    return segments;
                }

                function stringStartsWith(value, search) {
                    return value.indexOf(search) === 0;
                }

                function stringEndsWith(value, search) {
                    return value.slice(-search.length) === search;
                }

                function parseUrl(url, baseHref) {
                    var link = document.createElement('a');
                    link.href = baseHref || window.location.href;
                    if (url) {
                        link.href = url;
                    }
                    link.pathname = normalizePathname(link.pathname);
                    return link;
                }

                function updateQueryParam(search, key, value) {
                    var pairs = [];
                    var source = search ? search.replace(/^\?/, '') : '';
                    var items = source ? source.split('&') : [];
                    var replaced = false;

                    for (var i = 0; i < items.length; i++) {
                        if (!items[i]) {
                            continue;
                        }
                        var separatorIndex = items[i].indexOf('=');
                        var encodedName = separatorIndex === -1 ? items[i] : items[i].slice(0, separatorIndex);
                        var name = decodeURIComponent(encodedName.replace(/\+/g, '%20'));
                        if (name === key) {
                            if (value !== null) {
                                pairs.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
                                replaced = true;
                            }
                            continue;
                        }
                        pairs.push(items[i]);
                    }

                    if (value !== null && !replaced) {
                        pairs.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
                    }

                    return pairs.length ? '?' + pairs.join('&') : '';
                }

                function isNumericString(value) {
                    if (!value) {
                        return false;
                    }
                    for (var i = 0; i < value.length; i++) {
                        var code = value.charCodeAt(i);
                        if (code < 48 || code > 57) {
                            return false;
                        }
                    }
                    return true;
                }

                function isSupportedLanguage(value, supportedLangs) {
                    if (!value || !supportedLangs || !supportedLangs.length) {
                        return false;
                    }
                    var normalized = (value + '').toLowerCase();
                    for (var i = 0; i < supportedLangs.length; i++) {
                        if ((supportedLangs[i] + '').toLowerCase() === normalized) {
                            return true;
                        }
                    }
                    return false;
                }

                function addPathLanguage(pathname, language, sitePath, useIndexPermalinks, supportedLangs, paginationBase) {
                    var segments = getPathSegments(pathname);
                    var cleanedSegments = [];
                    var pageNumber = null;
                    var paginationSegment = (paginationBase || 'page').toLowerCase();
                    for (var i = 0; i < segments.length; i++) {
                        var current = segments[i].toLowerCase();
                        if (current === 'lang' && i + 1 < segments.length && isSupportedLanguage(segments[i + 1], supportedLangs)) {
                            i++;
                            continue;
                        }
                        if (current === paginationSegment && i + 1 < segments.length && isNumericString(segments[i + 1])) {
                            pageNumber = segments[i + 1];
                            i++;
                            continue;
                        }
                        cleanedSegments.push(segments[i]);
                    }
                    segments = cleanedSegments;
                    if (useIndexPermalinks) {
                        var siteSegments = getPathSegments(sitePath);
                        var relativeSegments = segments.slice(siteSegments.length);
                        if (relativeSegments[0] !== 'index.php') {
                            segments = siteSegments.concat(['index.php']).concat(relativeSegments);
                        }
                    }
                    segments.push('lang');
                    segments.push(language);
                    if (pageNumber !== null) {
                        segments.push(paginationBase || 'page');
                        segments.push(pageNumber);
                    }
                    return '/' + segments.join('/') + '/';
                }

                function addLangToUrls(selector, attr) {
                    var siteUrl = parseUrl(<?php echo wp_json_encode($site_url); ?>);
                    var siteOrigin = siteUrl.protocol + '//' + siteUrl.host;
                    var language = <?php echo wp_json_encode($language); ?>;
                    var supportedLangs = <?php echo wp_json_encode($supported_langs); ?>;
                    var usePathLanguageUrls = <?php echo wp_json_encode($use_path_language_urls); ?>;
                    var useIndexPermalinks = <?php echo wp_json_encode($use_index_permalinks); ?>;
                    var paginationBase = <?php echo wp_json_encode($pagination_base); ?>;
                    var sitePath = stringEndsWith(siteUrl.pathname, '/') && siteUrl.pathname.length > 1
                        ? siteUrl.pathname.slice(0, -1)
                        : siteUrl.pathname;
                    jQuery(selector).each(function() {
                        var url = jQuery(this).attr(attr);
                        if (url) {
                            var linkLanguage = jQuery(this).attr('data-language') || language;
                            if (url === '#' ||
                                stringStartsWith(url, '#') ||
                                url.indexOf('tel:') > -1 ||
                                url.indexOf('mailto:') > -1
                            ) {
                                return;
                            }
                            try {
                                var parsedUrl = parseUrl(url, window.location.href);
                                var parsedOrigin = parsedUrl.protocol + '//' + parsedUrl.host;
                                if (parsedOrigin !== siteOrigin || parsedUrl.pathname.indexOf(sitePath) !== 0) {
                                    return;
                                }
                                if (usePathLanguageUrls) {
                                    parsedUrl.search = updateQueryParam(parsedUrl.search, 'lang', null);
                                    parsedUrl.pathname = addPathLanguage(parsedUrl.pathname, linkLanguage, sitePath, useIndexPermalinks, supportedLangs, paginationBase);
                                } else {
                                    parsedUrl.search = updateQueryParam(parsedUrl.search, 'lang', linkLanguage);
                                }
                                jQuery(this).attr(attr, parsedUrl.href);
                            } catch (e) {
                            }
                        }
                    });
                }
                jQuery(document).ready(function () {
                    addLangToUrls('a:not([type="submit"])', 'href');
                    addLangToUrls('[data-href]', 'data-href');
                });
            </script>
            <?php
        }
    }
}
