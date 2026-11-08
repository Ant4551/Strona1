<?php
defined('ABSPATH') or die;

if (!class_exists('NpUrlHelper')) {
    /**
     * Provides small URL parsing and reconstruction utilities used by routing helpers.
     * It safely rebuilds URLs from parsed parts, decodes query keys, and preserves
     * scheme, host, port, query, and fragment when only the path needs to change.
     * This keeps URL mutation consistent across theme, plugin, and common processors.
     */
    class NpUrlHelper
    {
        /**
         * Decode query key name from raw query pair.
         *
         * @param string $pair
         *
         * @return string
         */
        public static function decode_query_key_from_pair($pair)
        {
            $pair = (string) $pair;
            if ($pair === '') {
                return '';
            }

            $separator = strpos($pair, '=');
            $encoded_key = $separator === false ? $pair : substr($pair, 0, $separator);
            return rawurldecode(str_replace('+', ' ', $encoded_key));
        }

        /**
         * Detect original route key variant used in query string.
         *
         * @param string $query_string
         * @param string $hyphen_key
         * @param string $camel_key
         *
         * @return string
         */
        public static function get_route_query_key_from_query_string($query_string, $hyphen_key, $camel_key)
        {
            if (!is_string($query_string) || $query_string === '') {
                return '';
            }

            $pairs = explode('&', $query_string);
            foreach ($pairs as $pair) {
                $query_key = self::decode_query_key_from_pair($pair);
                if ($query_key === $hyphen_key || $query_key === $camel_key) {
                    return $query_key;
                }
            }

            return '';
        }

        /**
         * Remove duplicate route query aliases while keeping original key form.
         *
         * @param string $url
         *
         * @return string
         */
        public static function remove_duplicate_route_query_args($url)
        {
            $parts = wp_parse_url(html_entity_decode((string) $url));
            if (!$parts || !isset($parts['query']) || !is_string($parts['query']) || $parts['query'] === '') {
                return $url;
            }

            $query = array();
            parse_str($parts['query'], $query);
            if (!$query || !is_array($query)) {
                return $url;
            }

            $route_pairs = array(array('products-list', 'productsList'), array('product-id', 'productId'), array('thank-you', 'thankYou'));
            foreach ($route_pairs as $route_pair) {
                $hyphen_key = $route_pair[0];
                $camel_key = $route_pair[1];
                if (!array_key_exists($hyphen_key, $query) || !array_key_exists($camel_key, $query)) {
                    continue;
                }

                $keep_key = self::get_route_query_key_from_query_string($parts['query'], $hyphen_key, $camel_key);
                if (!$keep_key) {
                    $keep_key = $hyphen_key;
                }

                $drop_key = $keep_key === $hyphen_key ? $camel_key : $hyphen_key;
                $url = remove_query_arg($drop_key, $url);
                unset($query[$drop_key]);
            }

            return $url;
        }

        /**
         * Build URL from parsed parts with optional query array/string.
         *
         * @param array        $parts
         * @param string       $path
         * @param array|string $query
         *
         * @return string
         */
        public static function build_url_from_parts($parts, $path, $query = null)
        {
            $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
            $host = isset($parts['host']) ? $parts['host'] : '';
            $port = isset($parts['port']) ? ':' . $parts['port'] : '';
            $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

            $query_string = '';
            if ($query === null) {
                $query_string = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
            } elseif ($query) {
                $query_string = '?' . (is_array($query) ? NpPath::build_query_string($query) : (string) $query);
            }

            return $scheme . $host . $port . $path . $query_string . $fragment;
        }

        /**
         * Parse and return current request query params.
         *
         * @return array
         */
        public static function get_request_query_params()
        {
            if (!isset($_SERVER['REQUEST_URI'])) {
                return array();
            }

            $query = wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_QUERY);
            if (!is_string($query) || $query === '') {
                return array();
            }

            $params = array();
            parse_str($query, $params);
            return is_array($params) ? $params : array();
        }

        /**
         * Pick route query key variant preserving original request style.
         *
         * @param array  $request_query
         * @param string $hyphen_key
         * @param string $camel_key
         *
         * @return string
         */
        public static function pick_route_query_key($request_query, $hyphen_key, $camel_key)
        {
            if (is_array($request_query)) {
                $query_keys = array_keys($request_query);
                foreach ($query_keys as $query_key) {
                    if ($query_key === $hyphen_key || $query_key === $camel_key) {
                        return $query_key;
                    }
                }
            }

            return '';
        }

        /**
         * Append original route query arg to redirect location.
         *
         * @param string $location
         * @param array  $request_query
         * @param string $hyphen_key
         * @param string $camel_key
         *
         * @return string
         */
        public static function preserve_route_query_arg($location, $request_query, $hyphen_key, $camel_key)
        {
            $query_key = self::pick_route_query_key($request_query, $hyphen_key, $camel_key);
            if (!$query_key) {
                return $location;
            }

            $query_value = '';
            if (is_array($request_query) && array_key_exists($query_key, $request_query)) {
                $query_value = $request_query[$query_key];
            } elseif (isset($_GET[$query_key])) {
                $query_value = $_GET[$query_key];
            }

            return add_query_arg(array($query_key => $query_value), $location);
        }
    }
}
