<?php
global $search_custom_template;
$search_custom_template = 'searchTemplate';
$language = ThemeMultiLanguagesResolver::get_current_language();

add_action(
    'theme_content_styles',
    function () use ($search_custom_template) {
        theme_search_content_styles($search_custom_template);
    }
);

function theme_search_body_class_filter($classes) {
    $classes[] = 'u-body u-xl-mode';
    return $classes;
}
add_filter('body_class', 'theme_search_body_class_filter');

function theme_search_body_style_attribute() {
    return "";
}
add_filter('add_body_style_attribute', 'theme_search_body_style_attribute');

function theme_search_body_back_to_top() {
    ob_start(); ?>
    
    <?php
    return ob_get_clean();
}
add_filter('add_back_to_top', 'theme_search_body_back_to_top');

function theme_search_get_local_fonts() {
    return '';
}
add_filter('get_local_fonts', 'theme_search_get_local_fonts');

get_header();

theme_layout_before('search', '', $search_custom_template);

global $wp_query;
$first_repeatable = 0;
$last_repeatable = 0;

$template_used = array();
$templates_count = 1;

$search_sections_count = $last_repeatable + 1;

if ($search_sections_count) {
    for ($template_idx = 0; $template_idx < $templates_count; $template_idx++) {
        if ($template_idx < $first_repeatable && !empty($template_used[$template_idx])) {
            if ($search_sections_count == $first_repeatable) {
                break;
            } else {
                continue;
            }
        }
        $template_used[$template_idx] = true;

        $translations = '';
        if ($language) {
            if (file_exists(get_template_directory() . '/template-parts/'. $search_custom_template . '/translations/' . $language .'/search-content-' . ($template_idx + 1) . '.php')) {
                $translations = '/translations/' . $language;
            }
        }
        $search_content_path = get_template_directory() . '/template-parts/'. $search_custom_template . $translations . '/search-content-' . ($template_idx + 1) . '.php';
        ob_start();
        if (file_exists($search_content_path)) {
            include $search_content_path;
        }
        $search_dialog_path = get_template_directory() . '/template-parts/'. $search_custom_template . $translations . '/search-dialogs.php';
        if (file_exists($search_dialog_path)) {
            include $search_dialog_path;
        }
        $content = ob_get_clean();
        if (function_exists('renderTemplate')) {
            renderTemplate($content, '', 'echo', 'custom');
        } else {
            echo $content;
        }
    }
}

theme_layout_after('search');
get_footer();
remove_action('theme_content_styles', 'theme_search_content_styles');
remove_filter('body_class', 'theme_search_body_class_filter');
