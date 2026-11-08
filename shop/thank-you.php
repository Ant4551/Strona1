<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
$language = ThemeMultiLanguagesResolver::get_current_language();
add_action(
    'theme_content_styles',
    function () {
        $path = "/shop/template-parts/thank-you-styles.php";
        if (file_exists(get_template_directory() . $path)) {
            require get_template_directory() . $path;
        }
    }
);

function shop_thankYou_single_body_class_filter($classes) {
    $classes[] = 'u-body u-xl-mode';
    return $classes;
}
add_filter('body_class', 'shop_thankYou_single_body_class_filter');

function shop_thankYou_single_body_style_attribute() {
    return "";
}
add_filter('add_body_style_attribute', 'shop_thankYou_single_body_style_attribute');

function shop_thankYou_single_body_back_to_top() {
    ob_start(); ?>
    
    <?php
    return ob_get_clean();
}
add_filter('add_back_to_top', 'shop_thankYou_single_body_back_to_top');


function shop_thankYou_single_get_local_fonts() {
    return '';
}
add_filter('get_local_fonts', 'shop_thankYou_single_get_local_fonts');

get_header();  ?>

<?php
$translations = '';
$defaultLanguage = ThemeMultiLanguagesResolver::get_default_language();
if ($language && $language !== $defaultLanguage) {
    $translations = '/translations/' . $language;
}
ob_start();
$path = '/shop/template-parts' . $translations . '/thank-you-content.php';
if (file_exists(get_template_directory() . $path)) {
    require get_template_directory() . $path;
}
$thank_you_dialog_path = get_template_directory() . '/shop/template-parts/thank-you-dialogs.php';
if (file_exists($thank_you_dialog_path)) {
    include $thank_you_dialog_path;
}
$content = ob_get_clean();

if (function_exists('renderTemplate')) {
    renderTemplate($content, '', 'echo', 'custom');
} else {
    echo $content;
}
get_footer();
remove_action('theme_content_styles', 'theme_thankYou_content_styles');
remove_filter('body_class', 'shop_thankYou_single_body_class_filter');

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
