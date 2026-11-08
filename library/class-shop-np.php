<?php
/**
 * SHOP_NP class.
 */
class SHOP_NP {

	/**
	 * Theme init.
	 */
	public static function init() {
		$shopON = true;
		if ($shopON) {
			locate_template(array('shop/functions.php'), true);
		}
	}
}

SHOP_NP::init();