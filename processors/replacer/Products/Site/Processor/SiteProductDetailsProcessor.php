<?php

class SiteProductDetailsProcessor extends SiteProductItem {
    protected $_html = '';
    protected $_options;

    /**
     * @param string $html    Control html
     * @param array  $options Global options
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
        $products = $this->getProducts('');
        $productId = isset($this->_options['productId']) ? $this->_options['productId'] : 0;
        $products = isset($products[$productId]) ? array($products[$productId]) : array();
        $this->processProductItem($products);
    }

    /**
     * Process items
     *
     * @param array $products Product list
     *
     * @return void
     */
    public function processProductItem( $products ) {
        $this->_html = $this->_processProductItem($this->_html, $products);
    }

    /**
     * Get build result
     *
     * @return mixed|string
     */
    public function getResult() {
        return $this->_html;
    }
}