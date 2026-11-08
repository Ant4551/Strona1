<?php

class SiteProductItem extends ProductItem {
    protected $_options;
    public $productData;

    /**
     * @param array $options Options
     */
    protected function __construct( $options ) {
        parent::__construct($options);
        $this->_options = $options;
    }

    /**
     * Process product item
     *
     * @param string $content  Page content
     * @param array  $products Site products
     *
     * @return string|string[]|null
     */
    protected function _processProductItem($content, $products) {
        $this->products = $products;
        $reProductItem = '/<\!--product_item-->([\s\S]+?)<\!--\/product_item-->/';
        preg_match_all($reProductItem, $content, $matches, PREG_SET_ORDER);
        $allTemplates = count($matches);
        if ($allTemplates > 0) {
            $allProductsHtml = '';
            global $products_control_query;
            if (($products_control_query && method_exists($products_control_query, 'have_posts')) || $products) {
                if (count($products) < 1) {
                    return ''; // remove cell, if products is missing
                }
                $productsCount = isset($this->_options['count']) ? (int) $this->_options['count'] : '';
                if ($productsCount && count($products) > $productsCount) {
                    $products = array_slice($products, 0, $productsCount);
                }

                $i = 0;
                while(count($products) > 0) :
                    $tmplIndex = $i % $allTemplates;
                    $productItemHtml = $matches[$tmplIndex][1];
                    $product = array_shift($products);
                    $this->productData = site_data_product($product);
                    if (count($this->productData) > 0) {
                        $allProductsHtml .= $this->_replaceProductItemControls($productItemHtml);
                    }
                    $i++;
                endwhile;
            }
        }
        $content = preg_replace('/<!--product_item-->([\s\S]+)<!--\/product_item-->/', $allProductsHtml, $content);
        $content = BaseController::processPagination($content, 'products', $this->_options['siteProductsProcess']);
        $content = BaseController::processCategoriesFilter($content, $this->_options, 'np_products');
        $content = $this->_buildGridAutoRows($products, $this->_options, $content);
        return $content;
    }

    /**
     * Replace placeholder for product item controls
     *
     * @param string $content
     *
     * @return string $content
     */
    public function _replaceProductItemControls($content) {
        $product = $this->productData['product'];
        if (!$product) {
            return $content;
        }
        $content = $this->_replaceTitle($content);
        $content = $this->_replaceFullDesc($content);
        $content = $this->_replaceShortDesc($content);
        $content = $this->_replaceImage($content);
        $content = $this->_replaceButton($content);
        $content = $this->_replacePrice($content);
        $content = $this->_replaceGallery($content);
        $content = $this->_replaceVariations($content);
        $content = $this->_replaceTabs($content);
        $content = $this->_replaceQuantity($content);
        $content = $this->_replaceCategory($content);
        $content = $this->_setProductBadge($content);
        $content = $this->_replaceOutOfStock($content);
        $content = $this->_replaceSku($content);
        $content = $this->_replaceCountDown($content);
        return $content;
    }

    /**
     * Replace placeholder for product title
     *
     * @param string $content
     *
     * @return string $content
     */
    protected function _replaceTitle($content) {
        return preg_replace_callback(
            '/<!--product_title-->([\s\S]+?)<!--\/product_title-->/',
            function ($titleMatch) {
                $titleHtml = $titleMatch[1];
                $titleHtml = $this->_replaceTitleUrl($titleHtml);
                $titleHtml = $this->_replaceTitleContent($titleHtml);
                return $titleHtml;
            },
            $content
        );
    }

    /**
     * Replace placeholder for product title content
     *
     * @param string $content title html
     *
     * @return string $content
     */
    private function _replaceTitleContent($content) {
        $productTitle = $this->productData['title'];
        $productTitle = $productTitle ? $productTitle : $this->productData['product']->post_title;
        if (isset($productTitle) && $productTitle != '') {
            $content = preg_replace_callback(
                '/(<a\b[^>]*>).*?(<\/a>)/s', function ($matches) use ($productTitle) {
                    return $matches[1] . $productTitle . $matches[2];
                }, $content
            );
        }
        return $content;
    }

    /**
     * Replace placeholder for product title url
     *
     * @param string $content title html
     *
     * @return string $content
     */
    protected function _replaceTitleUrl($content) {
        $productId = isset($this->productData['product']['id']) ? $this->productData['product']['id'] : 0;
        $postUrl = $this->getProductRouteLink($productId);
        $postUrl = $postUrl ? $postUrl : '#';
        if ($postUrl) {
            $content = preg_replace('/href=[\'|"][\s\S]+?[\'|"]/', 'href="' . $postUrl . '"', $content);
        }
        return $content;
    }

    /**
     * Replace placeholder for product description
     *
     * @param string $content
     *
     * @return string $content
     */
    protected function _replaceFullDesc($content) {
        return preg_replace_callback(
            '/<!--product_description-->([\s\S]+?)<!--\/product_description-->/',
            function ($textMatch) {
                $textHtml = $textMatch[1];
                $productContent = $this->productData['fullDesc'];
                if (isset($productContent)) {
                    $textHtml = preg_replace('/([\s\S]+?>).*?(<\/[\s\S]+?>)/s', '$1' . $productContent . '$2', $textHtml);
                }
                return $textHtml;
            },
            $content
        );
    }

    /**
     * Replace placeholder for product short description
     *
     * @param string $content
     *
     * @return string $content
     */
    protected function _replaceShortDesc($content) {
        return preg_replace_callback(
            '/<!--product_content-->([\s\S]+?)<!--\/product_content-->/',
            function ($textMatch) {
                $textHtml = $textMatch[1];
                $productContent = $this->productData['shortDesc'];
                if (isset($productContent)) {
                    $textHtml = preg_replace('/([\s\S]+?>).*?(<\/[\s\S]+?>)/s', '$1{shortDesc}$2', $textHtml);
                    $textHtml = str_replace('{shortDesc}', $productContent, $textHtml);
                }
                return $textHtml;
            },
            $content
        );
    }

    /**
     * Replace placeholder for product image
     *
     * @param string $content
     *
     * @return string $content
     */
    protected function _replaceImage($content) {
        return preg_replace_callback(
            '/<!--product_image-->([\s\S]+?)<!--\/product_image-->/',
            function ($imageMatch) {
                $imageHtml = $imageMatch[1];
                $url = $this->productData['image_url'];
                $isBackgroundImage = strpos($imageHtml, '<div') !== false ? true : false;
                $productId = isset($this->productData['product']['id']) ? $this->productData['product']['id'] : 0;
                $link = $this->getProductRouteLink($productId);
                if (preg_match('/href="product-[\d]+?"/', $imageHtml, $matches)) {
                    $imageHtml = str_replace($matches[0], 'href="product-' . $this->productData['product']['id'] . '"', $imageHtml);
                }
                if ($isBackgroundImage) {
                    $imageHtml = str_replace('<div', '<div data-product-control="' . $link . '"', $imageHtml);
                    if (strpos($imageHtml, 'data-bg') !== false) {
                        $imageHtml = preg_replace('/(data-bg=(["\']))(.*?)(\2)/', '$1url(' . $url . ')$4', $imageHtml);
                    } else {
                        $imageHtml = str_replace('<div', '<div' . ' style="background-image:url(' . $url . ')"', $imageHtml);
                    }
                } else {
                    if (strpos($imageHtml, 'data-product-control') !== false) {
                        $imageHtml = preg_replace('/data-product-control="[\s\S]*?"/', '<div data-product-control="' . $link . '"', $imageHtml);
                        $imageHtml = preg_replace('/(src=[\'"])([\s\S]+?)([\'"])/', '$1' . $url . '$3 style="cursor:pointer;" ', $imageHtml);
                    } else {
                        $imageHtml = preg_replace('/(src=[\'"])([\s\S]+?)([\'"])/', '$1' . $url . '$3 style="cursor:pointer;" data-product-control="' . $link . '"', $imageHtml);
                    }
                }
                if ($this->_options['showSecondImage']) {
                    $url = $this->productData['second_image_url'];
                    if ($isBackgroundImage) {
                        // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
                        $secondImageHtml = '<img class="u-product-second-image" src="' . $url . '"/>';
                    } else {
                        $secondImageHtml = preg_replace('/(class=[\'"])([\s\S]+?)([\'"])/', '$1u-product-second-image$3', $imageHtml);
                        $secondImageHtml = preg_replace('/(src=[\'"])([\s\S]+?)([\'"])/', '$1' . $url . '$3 style="cursor:pointer;" data-product-control="' . $link . '"', $secondImageHtml);
                    }
                    $imageHtml = $imageHtml . $secondImageHtml;
                }
                return $imageHtml;
            },
            $content
        );
    }

    /**
     * Build product details URL for current rendering context.
     *
     * @param int|string $productId
     *
     * @return string
     */
    private function getProductRouteLink($productId)
    {
        if (!$productId) {
            return '#';
        }

        return NpMultiLanguagesResolver::build_np_route_url('product', $productId, $this->getProductsRouteSource());
    }

    /**
     * Detect whether product links should use theme or plugin route keys.
     *
     * @return string
     */
    private function getProductsRouteSource()
    {
        return NpMultiLanguagesResolver::get_np_route_source('theme');
    }

    /**
     * Replace placeholder for product button add to cart
     *
     * @param string $content
     *
     * @return string $content
     */
    protected function _replaceButton($content) {
        return preg_replace_callback(
            '/<!--product_button-->([\s\S]+?)<!--\/product_button-->/',
            function ($buttonMatch) {
                $button_html = $buttonMatch[1];
                $controlOptions = array();
                if (preg_match('/<\!--options_json--><\!--([\s\S]+?)--><\!--\/options_json-->/', $button_html, $matches)) {
                    $controlOptions = json_decode($matches[1], true);
                    $button_html = str_replace($matches[0], '', $button_html);
                }
                $categories = isset($this->_options['productsJson']['categories']) ? $this->_options['productsJson']['categories'] : array();
                return SiteDataProduct::getProductButtonHtml($button_html, $controlOptions, $this->products, $categories);
            },
            $content
        );
    }

    /**
     * Replace placeholder for product price
     *
     * @param string $content
     *
     * @return string $content
     */
    protected function _replacePrice($content) {
        return preg_replace_callback(
            '/<!--product_price-->([\s\S]+?)<!--\/product_price-->/',
            function ($priceHtml) {
                $priceHtml = $priceHtml[1];
                $price = $this->productData['price'];
                $price_old = $this->productData['price_old'];
                if ($price_old == $price) {
                    $price_old = '';
                }
                $addZeroCents = strpos($priceHtml, 'data-add-zero-cents="true"') !== false ? true : false;
                $price = $this->priceProcess($price, $addZeroCents);
                $price_old = $price_old ? $this->priceProcess($price_old, $addZeroCents) : $price_old;
                $priceHtml = preg_replace('/<\!--product_old_price-->([\s\S]+?<div[\s\S]+?>)[\s\S]+?(<\/div>)<\!--\/product_old_price-->/', '$1 ' . $price_old . ' $2', $priceHtml, 2);
                return preg_replace('/<\!--product_regular_price-->([\s\S]+?<div[\s\S]+?>)[\s\S]+?(<\/div>)<\!--\/product_regular_price-->/', ('$1 ' . $price . ' $2'), $priceHtml);
            },
            $content
        );
    }

    /**
     * Get price with/without cents
     *
     * @param string $price
     * @param bool   $addZeroCents
     *
     * @return string $price
     */
    public function priceProcess($price, $addZeroCents) {
        $currentPrice = '0';
        if (preg_match('/\d+(\.\d+)?/', $price, $matches)) {
            if (strpos($price, ',') !== false && strpos($price, '.') !== false) {
                return $price;
            }
            $currentPrice = $matches[0];
            $price = str_replace($matches[0], '{currentPrice}', $price);
        }
        $priceParams = explode('.', $currentPrice);
        $cents = isset($priceParams[1]) ? $priceParams[1] : '00';
        if ($cents === '00') {
            $currentPrice = $priceParams[0];
        }
        if ($addZeroCents) {
            $currentPrice = $priceParams[0] . '.' . $cents;
        }
        return str_replace('{currentPrice}', $currentPrice, $price);
    }

    /**
     * @param string $catId
     *
     * @return array $products
     */
    public function getProducts($catId) {
        $products = isset($this->_options['productsJson']['products']) ? $this->_options['productsJson']['products'] : array();

        if ($catId) {
            $result = array();
            foreach ($products as $product) {
                if (in_array($catId, $product['categories'])) {
                    array_push($result, $product);
                }
                if ($catId === 'featured' && isset($product['isFeatured']) && $product['isFeatured']) {
                    array_push($result, $product);
                }
            }
            $products = $result;
        }

        return $productsSortById = array_combine(array_column($products, 'id'), $products);
    }
}
