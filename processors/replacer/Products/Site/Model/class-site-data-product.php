<?php
defined('ABSPATH') or die;

class SiteDataProduct
{

    public static $product;
    public static $language;

    /**
     * Get full data product for np
     *
     * @param bool $editor
     *
     * @return array $product_data
     */
    public static function getProductData($editor = false)
    {
        self::$language = NpMultiLanguagesResolver::get_current_language();
        $product_data = array(
            'product'               => self::$product,
            'type'                  => 'simple',
            'title'                 => self::getProductTitle(),
            'fullDesc'              => self::getProductFullDesc(),
            'shortDesc'             => self::getProductShortDesc(),
            'image_url'             => self::getProductImageUrl(0),
            'second_image_url'      => self::getProductImageUrl(1),
            'price'                 => self::getProductPrice($editor),
            'price_old'             => self::getProductPriceOld($editor),
            'add_to_cart_text'      => 'Add to cart',
            'attributes'            => array(),
            'variations_attributes' => array(),
            'gallery_images'        => self::getProductImagesUrls(),
            'tabs'                  => array(),
            'meta'                  => '',
            'categories'            => self::getProductCategories(),
            'product-is-new'        => self::getProductIsNew(),
            'product-sale'          => self::getProductSale(),
            'product-out-of-stock'  => self::getProductOutOfStock(),
            'product-sku'           => self::getProductSku(),
            'saleEnabled'           => self::getSaleEnabled(),
            'saleStart'             => self::getSaleStart(),
            'saleEnd'               => self::getSaleEnd(),
        );
        return $product_data;
    }

    /**
     * Get product title
     *
     * @return string $title
     */
    public static function getProductTitle()
    {
        return $title = isset(self::$product['title']) ? self::$product['title'] : 'Product title';
    }

    /**
     * Get product full description
     *
     * @return string $fullDesc
     */
    public static function getProductFullDesc()
    {
        $fullDesc = isset(self::$product['fullDescription']) ? self::$product['fullDescription'] : '';
        return $fullDesc;
    }

    /**
     * Get product short description
     *
     * @return string $desc
     */
    public static function getProductShortDesc()
    {
        $desc = isset(self::$product['description']) ? self::$product['description'] : '';
        return mb_strimwidth($desc, 0, 250, '...');
    }

    /**
     * Get product image url
     *
     * @param int $index
     *
     * @return string $image_url
     */
    public static function getProductImageUrl($index)
    {
        $image_url = '';
        if (isset(self::$product['images'][$index]) && count(self::$product['images']) > 0) {
            $image = isset(self::$product['images'][$index]) ? self::$product['images'][$index] : '';
            $image_url = isset($image['url']) ? fixImagePaths($image['url']) : '';
        }
        return $image_url;
    }

    /**
     * Get product price
     *
     * @param bool $editor
     *
     * @return int $price
     */
    public static function getProductPrice($editor = false)
    {
        $price = isset(self::$product['fullPrice']) ? self::$product['fullPrice'] : '';
        if (self::$language) {
            if (isset(self::$product['translations'][self::$language]['fullPrice'])) {
                $price = self::$product['translations'][self::$language]['fullPrice'];
            }
        }
        $price = str_replace('$', '_dollar_symbol_', $price);
        return $price;
    }

    /**
     * Get product price old
     *
     * @param bool $editor
     *
     * @return int $price_old
     */
    public static function getProductPriceOld($editor = false)
    {
        $price_old = isset(self::$product['fullPriceOld']) ? self::$product['fullPriceOld'] : '';
        if (self::$language) {
            if (isset(self::$product['translations'][self::$language]['fullPriceOld'])) {
                $price_old = self::$product['translations'][self::$language]['fullPriceOld'];
            }
        }
        $price_old = str_replace('$', '_dollar_symbol_', $price_old);
        return $price_old;
    }

    /**
     * Get product gallery images
     *
     * @return array $images urls
     */
    public static function getProductImagesUrls()
    {
        $imagesUrls = array();
        if (isset(self::$product['images']) && self::$product['images']) {
            foreach (self::$product['images'] as $image) {
                if ($image['url']) {
                    $imagesUrls[] = fixImagePaths($image['url']);
                }
            }
        }
        return $imagesUrls;
    }

    /**
     * Get button add to cart html
     *
     * @param string $button_html
     * @param array  $controlOptions
     * @param array  $allProducts
     * @param array  $categories
     *
     * @return string $button_html
     */
    public static function getProductButtonHtml($button_html, $controlOptions, $allProducts, $categories)
    {
        $addToCart = false;
        $goToProduct = false;
        $byNow = false;
        if (isset($controlOptions['clickType'])) {
            if ($controlOptions['clickType'] === 'go-to-page') {
                $goToProduct = true;
            } elseif ($controlOptions['clickType'] === 'buy-now') {
                $byNow = true;
            } else {
                $addToCart = true;
            }
        }
        if (self::$product && isset(self::$product['id'])) {
            $button_html = str_replace('data-product-id=""', 'data-product-id="' . self::$product['id']  . '"', $button_html);
            $productInfo = isset($allProducts[self::$product['id']]) ? $allProducts[self::$product['id']] : $allProducts[0];
            $productCategories = isset($productInfo['categories']) ? $productInfo['categories'] : array();
            $productInfo['categoriesData'] = getNpCategoriesData($productCategories, $categories);
            $productJson = htmlspecialchars(json_encode($productInfo));
            $productJson = str_replace('$', '_dollar_symbol_', $productJson);
            $button_html = str_replace('<a', '<a data-product="' . $productJson  . '"', $button_html);
            if ($goToProduct) {
                return preg_replace_callback(
                    '/href=[\"\']([\s\S]*?)[\"\']/',
                    function ($matchHref) use ($productInfo) {
                        return 'href="' . $productInfo['link'] . '"';
                    },
                    $button_html
                );
            }
        }
        if (!$allProducts) {
            if ($goToProduct) {
                return preg_replace_callback(
                    '/href=[\"\']{1}product-?(\d+)[\"\']{1}/',
                    function ($hrefMatch) {
                        $source = NpMultiLanguagesResolver::get_np_route_source('plugin');
                        $prefix = $source === 'theme' ? 'product-id' : 'productId';
                        return 'href="' . home_url('?'. $prefix . '=' . $hrefMatch[1]) . '"';
                    },
                    $button_html
                );
            }
        }
        if ($addToCart) {
            $button_html = str_replace('class="', 'class="u-add-to-cart-link ', $button_html);
            $button_html = str_replace('u-dialog-link', '', $button_html);
        }
        if ($byNow) {
            $button_html = str_replace('class="', 'class="u-dialog-link ', $button_html);
            $button_html = str_replace('u-add-to-cart-link', '', $button_html);
            $paymentDialogAnchor = self::getPaymentDialogAnchor();
            if ($paymentDialogAnchor) {
                $button_html = preg_replace('/\s+href=(["\'])#\1/i', ' href=$1#' . esc_attr($paymentDialogAnchor) . '$1', $button_html, 1);
            }
        }
        return $button_html;
    }

    /**
     * Get payment dialog anchor for site product Buy Now buttons.
     *
     * @return string
     */
    private static function getPaymentDialogAnchor()
    {
        $postId = get_the_ID();
        if ($postId) {
            $anchor = self::getPaymentDialogAnchorFromDialogs(get_post_meta($postId, 'dialogs', true));
            if ($anchor) {
                return $anchor;
            }
        }

        if (!class_exists('NpMeta')) {
            return '';
        }

        return self::getPaymentDialogAnchorFromDialogs(NpMeta::get('publishDialogs'));
    }

    /**
     * Find payment dialog anchor in stored dialog metadata.
     *
     * @param string|array $dialogs
     *
     * @return string
     */
    private static function getPaymentDialogAnchorFromDialogs($dialogs)
    {
        if (!$dialogs) {
            return '';
        }

        if (is_string($dialogs)) {
            $dialogs = json_decode($dialogs, true);
        }
        if (!is_array($dialogs)) {
            return '';
        }

        foreach ($dialogs as $dialog) {
            if (!is_array($dialog) || empty($dialog['sectionAnchorId'])) {
                continue;
            }
            $publishHtml = isset($dialog['publishHtml']) ? $dialog['publishHtml'] : '';
            if ($publishHtml && strpos($publishHtml, 'u-payment-dialog') === false) {
                continue;
            }

            return ltrim($dialog['sectionAnchorId'], '#');
        }

        return '';
    }

    /**
     * Get product categories
     *
     * @return array $categories
     */
    public static function getProductCategories() {
        return isset(self::$product['categoriesData']) ? self::$product['categoriesData'] : array();
    }

    /**
     * Product is new
     */
    public static function getProductIsNew() {
        $currentDate = (int) (microtime(true) * 1000);
        if (isset(self::$product['created'])) {
            $createdDate = self::$product['created'];
        } else {
            $createdDate = $currentDate;
        }
        $milliseconds30Days = 30 * (60 * 60 * 24 * 1000); // 30 days in milliseconds
        if (($currentDate - $createdDate) <= $milliseconds30Days) {
            return true;
        }
        return false;
    }

    /**
     * Sale for product
     */
    public static function getProductSale() {
        $price = 0;
        if (isset(self::$product['price'])) {
            $price = (float) self::$product['price'];
        }
        $oldPrice = 0;
        if (isset(self::$product['oldPrice'])) {
            $oldPrice = (float) self::$product['oldPrice'];
        }
        $sale = '';
        if ($price && $oldPrice && $price < $oldPrice) {
            $sale = '-' . (int)(100 - ($price * 100 / $oldPrice)) . '%';
        }
        return $sale;
    }

    /**
     * Out of stock for product
     */
    public static function getProductOutOfStock() {
        return isset(self::$product['outOfStock']) && self::$product['outOfStock'] ? true : false;
    }

    /**
     * Get product sku
     *
     * @return string sku
     */
    public static function getProductSku() {
        return isset(self::$product['sku']) ? self::$product['sku'] : '';
    }

    /**
     * Get product saleEnabled
     *
     * @return string saleEnabled
     */
    public static function getSaleEnabled() {
        return isset(self::$product['saleEnabled']) ? self::$product['saleEnabled'] : '';
    }

    /**
     * Get product saleStart
     *
     * @return string saleStart
     */
    public static function getSaleStart() {
        return isset(self::$product['saleStart']) ? self::$product['saleStart'] : '';
    }

    /**
     * Get product saleEnd
     *
     * @return string saleEnd
     */
    public static function getSaleEnd() {
        return isset(self::$product['saleEnd']) ? self::$product['saleEnd'] : '';
    }
}

/**
 * Construct SiteDataProduct object
 *
 * @param int  $product Product from json
 * @param bool $editor  Need to check editor or live site
 *
 * @return array SiteDataProduct
 */
function site_data_product($product = array(), $editor = false)
{
    SiteDataProduct::$product = $product;
    return SiteDataProduct::$product ? SiteDataProduct::getProductData($editor) : array();
}
