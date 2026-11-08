<?php $skip_min_height = false; ?><section class="u-align-center u-clearfix u-container-align-center u-section-1" id="block-2">
  <div class="u-clearfix u-sheet u-valign-middle u-sheet-1"><!--products--><!--products_options_json--><!--{"type":"Recent","source":"","tags":"","count":""}--><!--/products_options_json-->
    <?php $showSecondImage = false; ?><div class="u-expanded-width u-products u-products-1 u-cms" data-site-sorting-prop="created" data-site-sorting-order="desc" data-items-per-page="6" data-products-id="1" data-products-datasource="site">
      <div class="has-categories-listbox u-list-control"><!--products_categories_filter-->
        <?php
            ob_start(); ?><div class="u-categories-listbox"><!--products_categories_filter_select-->
          <select class="u-border-2 u-border-grey-30 u-input u-select-categories">
            {categories_filters}
            
            
            
            
            
            
          </select><!--/products_categories_filter_select-->
          <svg class="u-caret u-caret-svg" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="16px" height="16px" viewBox="0 0 16 16" style="fill:currentColor;" xml:space="preserve"><polygon class="st0" points="8,12 2,4 14,4 "></polygon></svg>
        </div><?php
            $categories_template = ob_get_clean();
            ob_start(); ?><option value="{categories_filters_value}">{categories_filters_content}</option><?php
            $categories_filter_option = ob_get_clean();
            echo get_categories_filter_html( 
              array(
                      'template' => $categories_template,
                      'itemTemplate' => $categories_filter_option,
                      'cmsTemplate' => 'npShop',
              )
      ); ?><!--/products_categories_filter-->
      </div>
      <div class="u-repeater u-repeater-1"><?php global $npProductsData; $countItems = $npProductsData['countItems'];
            foreach ($npProductsData['products'] as $current_index => $product) {
            
            $productId = isset($product['id']) && $product['id'] ? $product['id'] : 0;
	        $productData = get_product_data($product, $productId);
	        $allCategories = isset($npProductsData['categories']) ? $npProductsData['categories'] : array();
	        $productInfo = findElementById($npProductsData['products'], $productId);
	        $productInfo['categoriesData'] = getCategoriesData($productInfo['categories'], $allCategories);
	        $productInfo['images'] = prepareImagesData($productInfo['images']);
            $productJson = htmlspecialchars(json_encode($productInfo));
            
            $templateOrder = $current_index % $countItems;
            ?><?php if ($templateOrder == 0) { ?><!--product_item-->
        <div class="u-align-center u-container-align-center u-container-style u-products-item u-repeater-item u-white u-repeater-item-1" data-product-id="4">
          <div class="u-container-layout u-similar-container u-valign-top u-container-layout-1"><!--product_image-->
            <?php if ($productData['image_url']) : ?><img alt="" class="u-expanded-width u-image u-image-contain u-image-default u-product-control u-image-1" src="<?php echo $productData['image_url']; ?>"><?php else: ?><div class="hidden-image"></div><?php endif; ?>
                                <?php if ($showSecondImage && isset($productData['second_image_url']) && $productData['second_image_url']): ?><img alt="" class="u-expanded-width u-image u-image-contain u-image-default u-product-control u-image-1 u-product-second-image" src="<?php echo $productData['second_image_url']; ?>"><?php endif; ?><!--/product_image--><!--product_title-->
            <h4 class="u-align-center u-product-control u-text u-text-default u-text-1">
              <a class="u-product-title-link" href="<?php echo $productData['productUrl']; ?>"><?php echo $productData['title']; ?></a>
            </h4><!--/product_title--><!--product_price-->
            <div class="u-align-center u-product-control u-product-price u-product-price-1" data-add-zero-cents="true">
              <div class="u-price-wrapper u-spacing-10"><!--product_old_price-->
                <div class="u-old-price" style="text-decoration: line-through !important;"><?php echo $productData['price_old']; ?></div><!--/product_old_price--><!--product_regular_price-->
                <div class="u-price u-text-palette-2-base" style="font-size: 1.25rem; font-weight: 700;"><?php echo $productData['price']; ?></div><!--/product_regular_price-->
              </div>
            </div><!--/product_price--><!--product_button--><!--options_json--><!--{"clickType":"buy-now","content":"Buy Now"}--><!--/options_json-->
            <a href="#popup-0111" class="u-align-center u-border-2 u-border-grey-25 u-btn u-btn-rectangle u-button-style u-none u-product-control u-text-body-color u-btn-1 u-dialog-link u-payment-button" data-product-button-click-type="buy-now" data-product-id="<?php echo $productId; ?>" data-product="<?php echo $productJson; ?>"><!--product_button_content-->Buy Now<!--/product_button_content--></a><!--/product_button-->
          </div>
        </div><!--/product_item--><?php } ?><?php if ($templateOrder == 1) { ?><!--product_item-->
        <div class="u-align-center u-container-align-center u-container-style u-products-item u-repeater-item u-white u-repeater-item-2" data-product-id="5">
          <div class="u-container-layout u-similar-container u-valign-top u-container-layout-2"><!--product_image-->
            <?php if ($productData['image_url']) : ?><img alt="" class="u-expanded-width u-image u-image-contain u-image-default u-product-control u-image-2" src="<?php echo $productData['image_url']; ?>"><?php else: ?><div class="hidden-image"></div><?php endif; ?>
                                <?php if ($showSecondImage && isset($productData['second_image_url']) && $productData['second_image_url']): ?><img alt="" class="u-expanded-width u-image u-image-contain u-image-default u-product-control u-image-2 u-product-second-image" src="<?php echo $productData['second_image_url']; ?>"><?php endif; ?><!--/product_image--><!--product_title-->
            <h4 class="u-align-center u-product-control u-text u-text-default u-text-2">
              <a class="u-product-title-link" href="<?php echo $productData['productUrl']; ?>"><?php echo $productData['title']; ?></a>
            </h4><!--/product_title--><!--product_price-->
            <div class="u-align-center u-product-control u-product-price u-product-price-2" data-add-zero-cents="true">
              <div class="u-price-wrapper u-spacing-10"><!--product_old_price-->
                <div class="u-old-price" style="text-decoration: line-through !important;"><?php echo $productData['price_old']; ?></div><!--/product_old_price--><!--product_regular_price-->
                <div class="u-price u-text-palette-2-base" style="font-size: 1.25rem; font-weight: 700;"><?php echo $productData['price']; ?></div><!--/product_regular_price-->
              </div>
            </div><!--/product_price--><!--product_button--><!--options_json--><!--{"clickType":"buy-now","content":"Buy Now"}--><!--/options_json-->
            <a href="#popup-0111" class="u-align-center u-border-2 u-border-grey-25 u-btn u-btn-rectangle u-button-style u-none u-product-control u-text-body-color u-btn-2 u-dialog-link u-payment-button" data-product-button-click-type="buy-now" data-product-id="<?php echo $productId; ?>" data-product="<?php echo $productJson; ?>"><!--product_button_content-->Buy Now<!--/product_button_content--></a><!--/product_button-->
          </div>
        </div><!--/product_item--><?php } ?><?php if ($templateOrder == 2) { ?><!--product_item-->
        <div class="u-align-center u-container-align-center u-container-style u-products-item u-repeater-item u-white u-repeater-item-3" data-product-id="6">
          <div class="u-container-layout u-similar-container u-valign-top u-container-layout-3"><!--product_image-->
            <?php if ($productData['image_url']) : ?><img alt="" class="u-expanded-width u-image u-image-contain u-image-default u-product-control u-image-3" src="<?php echo $productData['image_url']; ?>"><?php else: ?><div class="hidden-image"></div><?php endif; ?>
                                <?php if ($showSecondImage && isset($productData['second_image_url']) && $productData['second_image_url']): ?><img alt="" class="u-expanded-width u-image u-image-contain u-image-default u-product-control u-image-3 u-product-second-image" src="<?php echo $productData['second_image_url']; ?>"><?php endif; ?><!--/product_image--><!--product_title-->
            <h4 class="u-align-center u-product-control u-text u-text-default u-text-3">
              <a class="u-product-title-link" href="<?php echo $productData['productUrl']; ?>"><?php echo $productData['title']; ?></a>
            </h4><!--/product_title--><!--product_price-->
            <div class="u-align-center u-product-control u-product-price u-product-price-3" data-add-zero-cents="true">
              <div class="u-price-wrapper u-spacing-10"><!--product_old_price-->
                <div class="u-old-price" style="text-decoration: line-through !important;"><?php echo $productData['price_old']; ?></div><!--/product_old_price--><!--product_regular_price-->
                <div class="u-price u-text-palette-2-base" style="font-size: 1.25rem; font-weight: 700;"><?php echo $productData['price']; ?></div><!--/product_regular_price-->
              </div>
            </div><!--/product_price--><!--product_button--><!--options_json--><!--{"clickType":"buy-now","content":"Buy Now"}--><!--/options_json-->
            <a href="#popup-0111" class="u-align-center u-border-2 u-border-grey-25 u-btn u-btn-rectangle u-button-style u-none u-product-control u-text-body-color u-btn-3 u-dialog-link u-payment-button" data-product-button-click-type="buy-now" data-product-id="<?php echo $productId; ?>" data-product="<?php echo $productJson; ?>"><!--product_button_content-->Buy Now<!--/product_button_content--></a><!--/product_button-->
          </div>
        </div><!--/product_item--><?php } ?><?php if ($templateOrder == 3) { ?><!--product_item-->
        <div class="u-align-center u-container-align-center u-container-style u-products-item u-repeater-item u-white u-repeater-item-4" data-product-id="6">
          <div class="u-container-layout u-similar-container u-valign-top u-container-layout-4"><!--product_image-->
            <?php if ($productData['image_url']) : ?><img alt="" class="u-expanded-width u-image u-image-contain u-image-default u-product-control u-image-4" src="<?php echo $productData['image_url']; ?>"><?php else: ?><div class="hidden-image"></div><?php endif; ?>
                                <?php if ($showSecondImage && isset($productData['second_image_url']) && $productData['second_image_url']): ?><img alt="" class="u-expanded-width u-image u-image-contain u-image-default u-product-control u-image-4 u-product-second-image" src="<?php echo $productData['second_image_url']; ?>"><?php endif; ?><!--/product_image--><!--product_title-->
            <h4 class="u-align-center u-product-control u-text u-text-default u-text-4">
              <a class="u-product-title-link" href="<?php echo $productData['productUrl']; ?>"><?php echo $productData['title']; ?></a>
            </h4><!--/product_title--><!--product_price-->
            <div class="u-align-center u-product-control u-product-price u-product-price-4" data-add-zero-cents="true">
              <div class="u-price-wrapper u-spacing-10"><!--product_old_price-->
                <div class="u-old-price" style="text-decoration: line-through !important;"><?php echo $productData['price_old']; ?></div><!--/product_old_price--><!--product_regular_price-->
                <div class="u-price u-text-palette-2-base" style="font-size: 1.25rem; font-weight: 700;"><?php echo $productData['price']; ?></div><!--/product_regular_price-->
              </div>
            </div><!--/product_price--><!--product_button--><!--options_json--><!--{"clickType":"buy-now","content":"Buy Now"}--><!--/options_json-->
            <a href="#popup-0111" class="u-align-center u-border-2 u-border-grey-25 u-btn u-btn-rectangle u-button-style u-none u-product-control u-text-body-color u-btn-4 u-dialog-link u-payment-button" data-product-button-click-type="buy-now" data-product-id="<?php echo $productId; ?>" data-product="<?php echo $productJson; ?>"><!--product_button_content-->Buy Now<!--/product_button_content--></a><!--/product_button-->
          </div>
        </div><!--/product_item--><?php } ?><?php if ($templateOrder == 4) { ?><!--product_item-->
        <div class="u-align-center u-container-align-center u-container-style u-products-item u-repeater-item u-white u-repeater-item-5" data-product-id="6">
          <div class="u-container-layout u-similar-container u-valign-top u-container-layout-5"><!--product_image-->
            <?php if ($productData['image_url']) : ?><img alt="" class="u-expanded-width u-image u-image-contain u-image-default u-product-control u-image-5" src="<?php echo $productData['image_url']; ?>"><?php else: ?><div class="hidden-image"></div><?php endif; ?>
                                <?php if ($showSecondImage && isset($productData['second_image_url']) && $productData['second_image_url']): ?><img alt="" class="u-expanded-width u-image u-image-contain u-image-default u-product-control u-image-5 u-product-second-image" src="<?php echo $productData['second_image_url']; ?>"><?php endif; ?><!--/product_image--><!--product_title-->
            <h4 class="u-align-center u-product-control u-text u-text-default u-text-5">
              <a class="u-product-title-link" href="<?php echo $productData['productUrl']; ?>"><?php echo $productData['title']; ?></a>
            </h4><!--/product_title--><!--product_price-->
            <div class="u-align-center u-product-control u-product-price u-product-price-5" data-add-zero-cents="true">
              <div class="u-price-wrapper u-spacing-10"><!--product_old_price-->
                <div class="u-old-price" style="text-decoration: line-through !important;"><?php echo $productData['price_old']; ?></div><!--/product_old_price--><!--product_regular_price-->
                <div class="u-price u-text-palette-2-base" style="font-size: 1.25rem; font-weight: 700;"><?php echo $productData['price']; ?></div><!--/product_regular_price-->
              </div>
            </div><!--/product_price--><!--product_button--><!--options_json--><!--{"clickType":"buy-now","content":"Buy Now"}--><!--/options_json-->
            <a href="#popup-0111" class="u-align-center u-border-2 u-border-grey-25 u-btn u-btn-rectangle u-button-style u-none u-product-control u-text-body-color u-btn-5 u-dialog-link u-payment-button" data-product-button-click-type="buy-now" data-product-id="<?php echo $productId; ?>" data-product="<?php echo $productJson; ?>"><!--product_button_content-->Buy Now<!--/product_button_content--></a><!--/product_button-->
          </div>
        </div><!--/product_item--><?php } ?><?php if ($templateOrder == 5) { ?><!--product_item-->
        <div class="u-align-center u-container-align-center u-container-style u-products-item u-repeater-item u-white u-repeater-item-6" data-product-id="6">
          <div class="u-container-layout u-similar-container u-valign-top u-container-layout-6"><!--product_image-->
            <?php if ($productData['image_url']) : ?><img alt="" class="u-expanded-width u-image u-image-contain u-image-default u-product-control u-image-6" src="<?php echo $productData['image_url']; ?>"><?php else: ?><div class="hidden-image"></div><?php endif; ?>
                                <?php if ($showSecondImage && isset($productData['second_image_url']) && $productData['second_image_url']): ?><img alt="" class="u-expanded-width u-image u-image-contain u-image-default u-product-control u-image-6 u-product-second-image" src="<?php echo $productData['second_image_url']; ?>"><?php endif; ?><!--/product_image--><!--product_title-->
            <h4 class="u-align-center u-product-control u-text u-text-default u-text-6">
              <a class="u-product-title-link" href="<?php echo $productData['productUrl']; ?>"><?php echo $productData['title']; ?></a>
            </h4><!--/product_title--><!--product_price-->
            <div class="u-align-center u-product-control u-product-price u-product-price-6" data-add-zero-cents="true">
              <div class="u-price-wrapper u-spacing-10"><!--product_old_price-->
                <div class="u-old-price" style="text-decoration: line-through !important;"><?php echo $productData['price_old']; ?></div><!--/product_old_price--><!--product_regular_price-->
                <div class="u-price u-text-palette-2-base" style="font-size: 1.25rem; font-weight: 700;"><?php echo $productData['price']; ?></div><!--/product_regular_price-->
              </div>
            </div><!--/product_price--><!--product_button--><!--options_json--><!--{"clickType":"buy-now","content":"Buy Now"}--><!--/options_json-->
            <a href="#popup-0111" class="u-align-center u-border-2 u-border-grey-25 u-btn u-btn-rectangle u-button-style u-none u-product-control u-text-body-color u-btn-6 u-dialog-link u-payment-button" data-product-button-click-type="buy-now" data-product-id="<?php echo $productId; ?>" data-product="<?php echo $productJson; ?>"><!--product_button_content-->Buy Now<!--/product_button_content--></a><!--/product_button-->
          </div>
        </div><!--/product_item--><?php } ?><?php } ?>
      </div>
      <div class="u-list-control"></div>
    </div><!--/products-->
  </div>
</section><?php if ($skip_min_height) { echo "<style> .u-section-1, .u-section-1 .u-sheet {min-height: auto;}</style>"; } ?>