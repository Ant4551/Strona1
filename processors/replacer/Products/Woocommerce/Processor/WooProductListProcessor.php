<?php

class WooProductListProcessor extends WooProductItem {
    protected $_html = '';
    protected $_options;
    public $products = null;

    /**
     * @param string $html    Control html
     * @param array  $options Global parameters
     */
    public function __construct( $html, $options ) {
        $this->_html    = $html;
        $this->_options = $options;
        $GLOBALS['addToCartClasses'] = $this->getAddToCartClasses($html);
        parent::__construct($options);
    }

    /**
     * Build control
     *
     * @return void
     */
    public function build() {
        $products = $this->getProducts($this->_options);
        if (get_option('np_theme_appearance') === 'plugin-option' && function_exists('wc_get_product') && (is_shop() || is_product_category())) {
            global $wp_query;
            $products = isset($wp_query->posts) ? $wp_query->posts : $products;
        }
        if (count($products) < 1) {
            $this->_html = '';
            return;
        }
        $this->processProductItem($products);
    }

    /**
     * @param array $products Product List
     *
     * @return void
     */
    public function processProductItem( $products ) {
        $this->_html = $this->_processProductItem($this->_html, $products);
    }

    /**
     * Get build result
     *
     * @return array|string|string[]|null
     */
    public function getResult() {
        return $this->_html;
    }

    /**
     * Get add to cart button css classes
     *
     * @param string $html
     *
     * @return mixed|string
     */
    public function getAddToCartClasses($html) {
        $pattern = '/<!-- {{add_to_cart_button_classes: ?(.*?) ?}} -->/';
        if (preg_match($pattern, $html, $matches)) {
            return isset($matches[1]) ? $matches[1] : '';
        } else {
            return '';
        }
    }
}