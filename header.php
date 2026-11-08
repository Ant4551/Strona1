<?php
/**
 * The template for displaying the header
 *
 * Displays all of the head element and everything up until the "site-content" div.
 */

?>
<?php
$language = ThemeMultiLanguagesResolver::get_current_language();
$metaGeneratorContent = 'Nicepage 8.8.2, nicepage.com';
$meta_generator = '';
if ($metaGeneratorContent) {
    remove_action('wp_head', 'wp_generator');
    $meta_generator = '<meta name="generator" content="' . $metaGeneratorContent . '" />' . "\n";
    $GLOBALS['meta_generator'] = $metaGeneratorContent;
}
$metaReferrer = '';
$meta_referrer = '';
if ($metaReferrer) {
    if (theme_woocommerce_enabled() && is_cart()) {
        $meta_referrer = '';
        $GLOBALS['meta_referrer'] = '';
    } else {
        $meta_referrer = '<meta name="referrer" content="origin" />' . "\n";
        $GLOBALS['meta_referrer'] = $metaReferrer;
    }
}
$hideHeader = false; // default header is visible
global $hideFooter;
$hideFooter = false; // default footer is visible
$pageBlog = is_home() || is_category() || is_search();
$pagePost = is_single();
$page404 = is_404();
$pageLogin = is_wplogin();
$pageProducts = theme_woocommerce_enabled() ? is_shop() || is_product_category() : false;
$pageProduct = theme_woocommerce_enabled() ? is_product() : false;
$pageCart = theme_woocommerce_enabled() ? is_cart() : false;
$pageCheckout = theme_woocommerce_enabled() ? is_checkout() : false;
$defaultPath = $pageProducts || $pageProduct || $pageCart || $pageCheckout ? '/woocommerce' : '';
if ($pageBlog) {
    $template = 'blog';
}
if ($pagePost) {
    $template = 'post';
}
if ($page404) {
    $template = 'page404';
}
if ($pageLogin) {
    $template = 'pageLogin';
}
if ($pageProducts) {
    $template = 'products';
}
if ($pageProduct) {
    $template = 'product';
}
if ($pageCart) {
    $template = 'cart';
}
if ($pageCheckout) {
    $template = 'checkout';
}
$wpCustomTemplate = false;
global $isWpCustomTemplate, $blog_custom_template, $post_custom_template;
if ($isWpCustomTemplate) {
    $template = $blog_custom_template ? $blog_custom_template : $post_custom_template;
    if ($template) {
        $wpCustomTemplate = true;
    }
}
ob_start();
if ($pageBlog || $pagePost || $page404 || $pageLogin || $pageProducts || $pageProduct || $pageCart || $pageCheckout || $wpCustomTemplate) {
    $defaultName = $pageCart ? '‌shoppingCart' : $template;
    global ${$template . "_custom_template"};
    ${$template . "_custom_template"} = ${$template . "_custom_template"} ? ${$template . "_custom_template"} : $defaultName . 'Template';
    $customPath = $wpCustomTemplate ? $template : ${$template . "_custom_template"};
    $fileWithOptions = get_template_directory() . $defaultPath . '/template-parts/' . $customPath . '/custom-template-options.php';
    if ( file_exists( $fileWithOptions ) ) {
        include_once $fileWithOptions;
    }
} ?>
<!DOCTYPE html>
<html <?php theme_language_attributes(); ?> class="no-js" style="font-size:<?php echo apply_filters('theme_base_font_size', '16'); ?>px">
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php echo $meta_generator; ?>
    <link rel="profile" href="http://gmpg.org/xfn/11">
    <?php if ( is_singular() && pings_open( get_queried_object() ) ) : ?>
        <link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">
    <?php endif; ?>
    <?php wp_head(); ?>
    
    
    <?php echo $meta_referrer; ?>
    
</head>

<body <?php body_class(); ?><?php body_style_attribute(); ?> <?php body_data_attributes(); ?>>
<?php if (version_compare( $wp_version, '5.2', '>=' )) { wp_body_open(); } ?>
<div id="page" class="site">
    <a class="skip-link screen-reader-text" href="#content"><?php _e( 'Skip to content', 'site1' ); ?></a>
    <?php if (!$hideHeader) {
        $translations = '';
        if ($language) {
            if (file_exists(get_template_directory() . '/' . 'template-parts/' . '/translations/' . $language .'/header-content' . '.php')) {
                $translations = '/translations/' . $language;
            }
        }
        get_template_part('template-parts' . $translations . '/header-content');
        $header_dialog_path = get_template_directory() . '/template-parts/' . $translations . '/header-dialogs.php';
        if (!file_exists($header_dialog_path)) {
            $header_dialog_path = get_template_directory() . '/template-parts/header-dialogs.php';
        }
        if (file_exists($header_dialog_path)) {
            include $header_dialog_path;
        }
    } ?>
    <div id="content">
<?php $header = ob_get_clean();
if (function_exists('renderTemplate')) {
    renderTemplate($header, '', 'echo', 'header');
} else {
    echo $header;
} ?>