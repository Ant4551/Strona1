<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
$language = ThemeMultiLanguagesResolver::get_current_language();
add_action(
    'theme_content_styles',
    function () {
        $path = "/shop/template-parts/products-styles.php";
        if (file_exists(get_template_directory() . $path)) {
            require get_template_directory() . $path;
        }
    }
);

function shop_products_body_class_filter($classes) {
    $classes[] = 'u-body u-xl-mode';
    return $classes;
}
add_filter('body_class', 'shop_products_body_class_filter');

function shop_products_body_style_attribute() {
    return "";
}
add_filter('add_body_style_attribute', 'shop_products_body_style_attribute');

function shop_products_body_back_to_top() {
    ob_start(); ?>
    
    <?php
    return ob_get_clean();
}
add_filter('add_back_to_top', 'shop_products_body_back_to_top');


function shop_products_get_local_fonts() {
    return '';
}
add_filter('get_local_fonts', 'shop_products_get_local_fonts');

get_header();  ?>

<?php
$first_repeatable = 0;
$last_repeatable = 0;

$template_used = array();
$templates_count = 1;

$products_sections_count = $last_repeatable + 1;

$json_path = '/shop/products.json';
if (file_exists(get_template_directory() . $json_path)) {
    $data = file_get_contents(get_template_directory() . $json_path);
    $data = json_decode($data, true);
}
if (!isset($data) || !is_array($data)) {
    $data = array();
}

$translations = '';
$defaultLanguage = ThemeMultiLanguagesResolver::get_default_language();
if ($language && $language !== $defaultLanguage) {
    $translations = '/translations/' . $language;
}

if ($data && isset($data['products']) && count($data['products']) > 0 && isset($_GET['products-list']) && $products_sections_count) {
    global $npProductsData;
    $npProductsData = array();
    $npProductsData['countItems'] = 6;
    $npProductsData['options'] = json_decode('{"type":"Recent","source":"","tags":"","count":""}', true);
    $npProductsData['countProducts'] = count($data['products']);
    $maxCountItems = isset($npProductsData['options']['count']) ? $npProductsData['options']['count'] : '';
    if ($maxCountItems) {
        if ($npProductsData['countItems'] > $maxCountItems) {
            $npProductsData['countItems'] = $maxCountItems;
        }
        $data['products'] = array_slice($data['products'], 0, $npProductsData['countItems']);
    }
    $npProductsData['products'] = $data['products'];
    if (isset($data['categories'])) {
        $npProductsData['categories'] = $data['categories'];
    }

    ob_start();
    for ($template_idx = 0; $template_idx < $templates_count; $template_idx++) {
        get_template_part('/shop/template-parts' . $translations . '/products-content-' . ($template_idx + 1));
    }
    $products_dialog_path = get_template_directory() . '/shop/template-parts/products-dialogs.php';
    if (file_exists($products_dialog_path)) {
        include $products_dialog_path;
    }
    $content = ob_get_clean();

    if (function_exists('renderTemplate')) {
        renderTemplate($content, '', 'echo', 'custom');
    } else {
        echo $content;
    }
} ?>
<?php get_footer();
remove_action('theme_content_styles', 'theme_product_content_styles');
remove_filter('body_class', 'shop_products_body_class_filter');

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
