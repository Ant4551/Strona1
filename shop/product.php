<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
$language = ThemeMultiLanguagesResolver::get_current_language();
add_action(
    'theme_content_styles',
    function () {
        $path = "/shop/template-parts/product-styles.php";
        if (file_exists(get_template_directory() . $path)) {
            require get_template_directory() . $path;
        }
    }
);

function shop_product_single_body_class_filter($classes) {
    $classes[] = 'u-body u-xl-mode';
    return $classes;
}
add_filter('body_class', 'shop_product_single_body_class_filter');

function shop_product_single_body_style_attribute() {
    return "";
}
add_filter('add_body_style_attribute', 'shop_product_single_body_style_attribute');

function shop_product_single_body_back_to_top() {
    ob_start(); ?>
    
    <?php
    return ob_get_clean();
}
add_filter('add_back_to_top', 'shop_product_single_body_back_to_top');


function shop_product_single_get_local_fonts() {
    return '';
}
add_filter('get_local_fonts', 'shop_product_single_get_local_fonts');

get_header();  ?>

<?php
$json_path = '/shop/products.json';
if (file_exists(get_template_directory() . $json_path)) {
    $data = file_get_contents(get_template_directory() . $json_path);
    $data = json_decode($data, true);
}
if (!isset($data) || !is_array($data)) {
    $data = array();
}
$productId = isset($_GET['product-id']) ? $_GET['product-id'] : 0;
$product = findElementById($data['products'], $productId);
$allCategories = isset($data['categories']) ? $data['categories'] : array();
$product['categoriesData'] = getCategoriesData($product['categories'], $allCategories);
$product['images'] = prepareImagesData($product['images']);
$productData = get_product_data($product, $productId);
$productJson = htmlspecialchars(json_encode($product));

$galleryImages = array();
if (isset($productData['images']) && count($productData['images']) > 0) {
    $images = $productData['images'];
    $fullImage = $images[0]['url'];
    for($i = 0; $i < count($images); $i++) {
        array_push($galleryImages, $images[$i]['url']);
    }
}

$translations = '';
$defaultLanguage = ThemeMultiLanguagesResolver::get_default_language();
if ($language && $language !== $defaultLanguage) {
    $translations = '/translations/' . $language;
}
ob_start();
$path = '/shop/template-parts' . $translations . '/product-content.php';
if (file_exists(get_template_directory() . $path)) {
    require get_template_directory() . $path;
}
$product_dialog_path = get_template_directory() . '/shop/template-parts/product-dialogs.php';
if (file_exists($product_dialog_path)) {
    include $product_dialog_path;
}
$content = ob_get_clean();

if (function_exists('renderTemplate')) {
    renderTemplate($content, '', 'echo', 'custom');
} else {
    echo $content;
}
get_footer();
remove_action('theme_content_styles', 'theme_product_content_styles');
remove_filter('body_class', 'shop_product_single_body_class_filter');

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
