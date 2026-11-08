<?php

class SiteProductListProcessor extends SiteProductItem {
    protected $_html = '';
    protected $_options;

    /**
     * @param string $html    Control html
     * @param array  $options Global parameters
     */
    public function __construct( $html, $options ) {
        $this->_html = $html;
        $this->_options = $options;
        parent::__construct($options);
    }

    /**
     * Build control
     *
     * @return void
     */
    public function build() {
        $products = $this->getProducts($this->_options['catId']);
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
        return $this->_html = preg_replace('/^<div/', '<div data-products-id="1" data-products-datasource="site"', trim($this->_html));
    }
}