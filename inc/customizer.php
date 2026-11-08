<?php
/**
 * Customizer functionality
 */


/**
 * Customizer registration
 *
 * @param WP_Customize_Manager $wp_customize The Customizer object.
 */
function theme_customize_register($wp_customize) {
    global $theme_default_options;
    $wp_customize->get_setting('blogname')->transport = 'postMessage';
    $wp_customize->get_setting('blogdescription')->transport = 'postMessage';

    if (isset($wp_customize->selective_refresh)) {
        $wp_customize->selective_refresh->add_partial('blogname', array(
            'selector' => '.site-title a',
            'container_inclusive' => false,
            'render_callback' => 'theme_customize_partial_blogname',
        ));
        $wp_customize->selective_refresh->add_partial('blogdescription', array(
            'selector' => '.site-description',
            'container_inclusive' => false,
            'render_callback' => 'theme_customize_partial_blogdescription',
        ));
    }

    // Add color scheme setting and control.
    $wp_customize->add_setting('color_scheme', array(
        'default' => 'default',
        'sanitize_callback' => 'sanitize_text_field',
        'transport' => 'postMessage',
    ));

    $wp_customize->add_control('color_scheme', array(
        'label' => __('Color Scheme', 'site1'),
        'section' => 'colors',
        'type' => 'select',
        'choices' => array(),
        'priority' => 1,
    ));

    $wp_customize->remove_control('background_color');
    $wp_customize->remove_section('header_image');
    $wp_customize->remove_section('background_image');

    for ($i = 1; $i <= 5; $i++) {
        $wp_customize->add_setting("color_$i", array(
            'default' => '#',
            'sanitize_callback' => 'sanitize_hex_color',
            'transport' => 'postMessage',
        ));
        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, "color_$i", array(
            'label' => sprintf(__('Color %s', 'site1'), $i),
            'section' => 'colors',
        )));
    }

    $wp_customize->add_setting("color_white_contrast", array(
        'default' => '#000000',
        'sanitize_callback' => 'sanitize_hex_color',
        'transport' => 'postMessage',
    ));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, "color_white_contrast", array(
        'label' => __('Text Dark', 'site1'),
        'section' => 'colors',
    )));

    $wp_customize->add_setting("color_shading_contrast", array(
        'default' => '#ffffff',
        'sanitize_callback' => 'sanitize_hex_color',
        'transport' => 'postMessage',
    ));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, "color_shading_contrast", array(
        'label' => __('Text Light', 'site1'),
        'section' => 'colors',
    )));

    $wp_customize->add_setting("color_background", array(
        'default' => '#ffffff',
        'sanitize_callback' => 'sanitize_hex_color',
        'transport' => 'postMessage',
    ));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, "color_background", array(
        'label' => __('Background', 'site1'),
        'section' => 'colors',
    )));


    $wp_customize->add_setting('colors_css', array(
        'default' => $theme_default_options['colors_css'],
        'sanitize_callback' => 'theme_sanitize_raw',
        'transport' => 'postMessage',
    ));

    $wp_customize->add_setting('resolved_css', array(
        'default' => '',
        'sanitize_callback' => 'theme_sanitize_raw',
        'transport' => 'postMessage',
    ));

    // Remove the core header textcolor control, as it shares the main text color.
    $wp_customize->remove_control('header_textcolor');


    /**
     * Fonts
     */
    $wp_customize->add_section('fonts', array(
        'capability' => 'edit_theme_options',
        'title' => __('Fonts', 'site1'),
        'priority' => 40,
    ));

    $wp_customize->add_setting('font_scheme', array(
        'default' => 'default',
        'sanitize_callback' => 'sanitize_text_field',
        'transport' => 'postMessage',
    ));

    $wp_customize->add_control('font_scheme', array(
        'label' => __('Font Scheme', 'site1'),
        'section' => 'fonts',
        'type' => 'select',
        'choices' => array(),
    ));

    $wp_customize->add_setting('fonts_css', array(
        'default' => $theme_default_options['fonts_css'],
        'sanitize_callback' => 'theme_sanitize_raw',
        'transport' => 'postMessage',
    ));

    $wp_customize->add_setting('fonts_link', array(
        'default' => $theme_default_options['fonts_link'],
        'sanitize_callback' => 'theme_sanitize_raw',
        'transport' => 'postMessage',
    ));

    $wp_customize->add_setting("font_heading", array(
        'default' => '',
        'sanitize_callback' => 'sanitize_text_field',
        'transport' => 'postMessage',
    ));
    $wp_customize->add_control('font_heading', array(
        'label' => __('Heading Font', 'site1'),
        'section' => 'fonts',
        'type' => 'select',
        'choices' => array(),
    ));

    $wp_customize->add_setting("font_text", array(
        'default' => '',
        'sanitize_callback' => 'sanitize_text_field',
        'transport' => 'postMessage',
    ));
    $wp_customize->add_control('font_text', array(
        'label' => __('Text Font', 'site1'),
        'section' => 'fonts',
        'type' => 'select',
        'choices' => array(),
    ));


    /**
     * Typography
     */
    $wp_customize->add_section('typography', array(
        'capability' => 'edit_theme_options',
        'title' => __('Typography', 'site1'),
        'priority' => 40,
    ));

    $wp_customize->add_setting('typography_scheme', array(
        'default' => 'default',
        'sanitize_callback' => 'sanitize_text_field',
        'transport' => 'postMessage',
    ));

    $wp_customize->add_control('typography_scheme', array(
        'label' => __('Typography Scheme', 'site1'),
        'section' => 'typography',
        'type' => 'select',
        'choices' => array(),
    ));

    $wp_customize->add_setting('typography_css', array(
        'default' => $theme_default_options['typography_css'],
        'sanitize_callback' => 'theme_sanitize_raw',
        'transport' => 'postMessage',
    ));

    $wp_customize->add_setting('typography_base_size', array(
        'default' => 16,//$theme_default_options['typography_base_size'],
        'sanitize_callback' => 'absint',
        'transport' => 'postMessage',
    ));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'typography_base_size', array(
        'type' => 'number',
        'label' => __('Base Size', 'site1'),
        'section' => 'typography',
        'input_attrs' => array(
            'min'   => 13,
            'max'   => 24,
            'step'  => 1,
        ),
    )));

    $defined_settings = apply_filters('np_theme_settings', array());
    $theme_font_weight = $defined_settings && $defined_settings['typography']['h1']['font-weight'] ? $defined_settings['typography']['h1']['font-weight'] : '400';
    $wp_customize->add_setting("typography_heading_weight", array(
        'default' => $theme_font_weight,//$theme_default_options['typography_heading_weight'],
        'sanitize_callback' => 'sanitize_text_field',
        'transport' => 'postMessage',
    ));
    $wp_customize->add_control('typography_heading_weight', array(
        'label' => __('Heading Weight', 'site1'),
        'section' => 'typography',
        'type' => 'select',
        'choices' => array(
            '100' => __('100 Thin', 'site1'),
            '300' => __('300 Light', 'site1'),
            '400' => __('400 Regular', 'site1'),
            '500' => __('500 Medium', 'site1'),
            '700' => __('700 Bold', 'site1'),
            '900' => __('900 Black', 'site1'),
        ),
    ));


    /**
     * Logo options
     */
    $wp_customize->add_setting('logo_width', array(
        'default' => $theme_default_options['logo_width'],
        'sanitize_callback' => 'absint',
        'transport' => 'postMessage',
    ));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'logo_width', array(
        'type' => 'number',
        'label' => __('Logo max width (px)', 'site1'),
        'section' => 'title_tagline',
        'priority' => 9,
    )));

    $wp_customize->add_setting('logo_height', array(
        'default' => $theme_default_options['logo_height'],
        'sanitize_callback' => 'absint',
        'transport' => 'postMessage',
    ));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'logo_height', array(
        'type' => 'number',
        'label' => __('Logo max height (px)', 'site1'),
        'section' => 'title_tagline',
        'priority' => 9,
    )));

    $wp_customize->add_setting('logo_link', array(
        'default' => $theme_default_options['logo_link'],
        'sanitize_callback' => 'esc_url_raw',
        'transport' => 'postMessage',
    ));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'logo_link', array(
        'type' => 'url',
        'label' => __('Logo link href', 'site1'),
        'section' => 'title_tagline',
        'priority' => 9,
    )));


    $wp_customize->add_setting('custom_favicon', array(
        'theme_supports' => array(),
        'transport' => 'postMessage',
        'sanitize_callback' => 'theme_sanitize_raw'
    ));
    $wp_customize->add_control(new WP_Customize_Cropped_Image_Control($wp_customize, 'custom_favicon', array(
        'label' => __('Favicon', 'site1'),
        'section' => 'title_tagline',
        'priority' => 8,
        'height' => '64',
        'width' => '64',
        'button_labels' => array(
            'select' => __('Select favicon', 'site1'),
            'change' => __('Change favicon', 'site1'),
            'remove' => __('Remove', 'site1'),
            'default' => __('Default', 'site1'),
            'placeholder' => __('No favicon selected', 'site1'),
            'frame_title' => __('Select favicon', 'site1'),
            'frame_button' => __('Choose favicon', 'site1'),
        ),
    )));


    /**
     * Menu options
     */
    $wp_customize->add_section('menu_options', array(
        'capability' => 'edit_theme_options',
        'title' => __('Theme options', 'site1'),
        'panel' => 'nav_menus',
    ));

    $wp_customize->add_setting('menu_trim_title', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'theme_sanitize_checkbox',
        'default' => $theme_default_options['menu_trim_title'],
    ));
    $wp_customize->add_control('menu_trim_title', array(
        'type' => 'checkbox',
        'section' => 'menu_options',
        'label' => __('Trim long menu items', 'site1'),
    ));

    $wp_customize->add_setting('menu_trim_len', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'absint',
        'default' => $theme_default_options['menu_trim_len'],
    ));
    $wp_customize->add_control('menu_trim_len', array(
        'type' => 'number',
        'section' => 'menu_options',
        'label' => __('Limit each item to [N] characters', 'site1'),
    ));

    $wp_customize->add_setting('submenu_trim_len', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'absint',
        'default' => $theme_default_options['submenu_trim_len'],
    ));
    $wp_customize->add_control('submenu_trim_len', array(
        'type' => 'number',
        'section' => 'menu_options',
        'label' => __('Limit each subitem to [N] characters', 'site1'),
    ));

    $wp_customize->add_setting('menu_use_tag_filter', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'theme_sanitize_checkbox',
        'default' => $theme_default_options['menu_use_tag_filter'],
    ));
    $wp_customize->add_control('menu_use_tag_filter', array(
        'type' => 'checkbox',
        'section' => 'menu_options',
        'label' => __('Apply menu item tag filter', 'site1'),
    ));

    $wp_customize->add_setting('menu_allowed_tags', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => $theme_default_options['menu_allowed_tags'],
    ));
    $wp_customize->add_control('menu_allowed_tags', array(
        'type' => 'text',
        'section' => 'menu_options',
        'label' => __('Allowed menu item tags', 'site1'),
    ));

    $wp_customize->add_setting('use_default_menu', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'theme_sanitize_checkbox',
        'default' => $theme_default_options['use_default_menu'],
    ));
    $wp_customize->add_control('use_default_menu', array(
        'type' => 'checkbox',
        'section' => 'menu_options',
        'label' => __('Use not stylized menu', 'site1'),
        'description' => __('Used standart wp_nav_menu when option is enabled (need for some third-party plugins).', 'site1'),
    ));


    /**
     * Excerpt options
     */
    $wp_customize->add_section('excerpt_options', array(
        'capability' => 'edit_theme_options',
        'title' => __('Excerpt', 'site1'),
    ));

    $wp_customize->add_setting('excerpt_auto', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'theme_sanitize_checkbox',
        'default' => $theme_default_options['excerpt_auto'],
    ));
    $wp_customize->add_control('excerpt_auto', array(
        'type' => 'checkbox',
        'section' => 'excerpt_options',
        'label' => __('Use auto excerpts', 'site1'),
        'description' => __('Generate post excerpts automatically (When neither more-tag nor post excerpt is used)', 'site1'),
    ));

    $wp_customize->add_setting('excerpt_words', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'absint',
        'default' => $theme_default_options['excerpt_words'],
    ));
    $wp_customize->add_control('excerpt_words', array(
        'type' => 'number',
        'section' => 'excerpt_options',
        'label' => __('Excerpt length (words)', 'site1'),
    ));

    $wp_customize->add_setting('excerpt_min_remainder', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'absint',
        'default' => $theme_default_options['excerpt_min_remainder'],
    ));
    $wp_customize->add_control('excerpt_min_remainder', array(
        'type' => 'number',
        'section' => 'excerpt_options',
        'label' => __('Excerpt balance (words)', 'site1'),
    ));

    $wp_customize->add_setting('excerpt_strip_shortcodes', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'theme_sanitize_checkbox',
        'default' => $theme_default_options['excerpt_strip_shortcodes'],
    ));
    $wp_customize->add_control('excerpt_strip_shortcodes', array(
        'type' => 'checkbox',
        'section' => 'excerpt_options',
        'label' => __('Remove shortcodes from excerpt', 'site1'),
    ));

    $wp_customize->add_setting('excerpt_use_tag_filter', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'theme_sanitize_checkbox',
        'default' => $theme_default_options['excerpt_use_tag_filter'],
    ));
    $wp_customize->add_control('excerpt_use_tag_filter', array(
        'type' => 'checkbox',
        'section' => 'excerpt_options',
        'label' => __('Apply excerpt tag filter', 'site1'),
    ));

    $wp_customize->add_setting('excerpt_allowed_tags', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => $theme_default_options['excerpt_allowed_tags'],
    ));
    $wp_customize->add_control('excerpt_allowed_tags', array(
        'type' => 'text',
        'section' => 'excerpt_options',
        'label' => __('Allowed excerpt tags', 'site1'),
    ));

    $wp_customize->add_setting('show_morelink', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'theme_sanitize_checkbox',
        'default' => $theme_default_options['show_morelink'],
    ));
    $wp_customize->add_control('show_morelink', array(
        'type' => 'checkbox',
        'section' => 'excerpt_options',
        'label' => __('Show More Link', 'site1'),
    ));

    $wp_customize->add_setting('morelink_template', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'theme_sanitize_raw',
        'default' => $theme_default_options['morelink_template'],
    ));
    $wp_customize->add_control('morelink_template', array(
        'type' => 'text',
        'section' => 'excerpt_options',
        'label' => __('More Link Template', 'site1'),
        'description' => sprintf(__('ShortTags: %s', 'site1'), '[url], [text]'),
    ));


    /**
     * SEO options
     */
    $wp_customize->add_section('seo_options', array(
        'capability' => 'edit_theme_options',
        'title' => __('SEO', 'site1'),
        'priority' => 200,
    ));

    $wp_customize->add_setting('seo_og', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'theme_sanitize_checkbox',
        'default' => $theme_default_options['seo_og'],
    ));
    $wp_customize->add_control('seo_og', array(
        'type' => 'checkbox',
        'section' => 'seo_options',
        'label' => __('Include Open Graph meta tags', 'site1'),
    ));

    $wp_customize->add_setting('seo_ld', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'theme_sanitize_checkbox',
        'default' => $theme_default_options['seo_ld'],
    ));
    $wp_customize->add_control('seo_ld', array(
        'type' => 'checkbox',
        'section' => 'seo_options',
        'label' => __('Include schema.org JSON-LD syntax markup', 'site1'),
    ));

    /**
     * Templates options
     */
    $wp_customize->add_section('templates_options', array(
        'capability' => 'edit_theme_options',
        'title' => __('Templates', 'site1'),
        'priority' => 200,
    ));

    if (!isset($templatesOptions)) {
        global $theme_options;
        $templatesOptions = array();
        $templates_parameters = array(
            array('blog', 'blog', 'Blog Template'),
		    array('post', 'post', 'Post Template'),
		    array('page404', '404', '404 Template'),
		    array('pageLogin', 'login', 'Login Template'),
        );
        $template = get_option('stylesheet');
        $woocommerceTemplateNames = array('products', 'product', 'shoppingCart', 'checkout');
        foreach ($templates_parameters as $templateIndex => $template_options) {
            $templateKey = $template_options[0];
            $templateName = $template_options[1];
            $templateLabel = $template_options[2];
            foreach ($theme_options as $op) {
                if (isset($op['options'])) {
                    foreach ($op['options'] as $key => $option) {
                        if (strpos($key, $templateKey . 'Template') === 0) {
                            if (!isset($templatesOptions[$templateKey])) {
                                $templatesOptions[$templateKey] = array();
                            }
                            $templatesOptions[$templateKey][$key] = __($option, 'site1');
                        }
                    }
                }
            }
            if (!in_array($templateKey, $woocommerceTemplateNames) || (in_array($templateKey, $woocommerceTemplateNames) && theme_woocommerce_enabled())) {
                $wp_customize->add_setting( 'theme_template_' . $template . '_' . $templateName . '-template', array(
                    'capability'        => 'edit_theme_options',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => $theme_default_options[ 'theme_template_' . $template . '_' . $templateName . '-template' ],
                ) );
                $wp_customize->add_control( 'theme_template_' . $template . '_' . $templateName . '-template', array(
                    'type'        => 'select',
                    'choices'     => $templatesOptions[ $templateKey ],
                    'section'     => 'templates_options',
                    'label'       => __( $templateLabel, 'site1' ),
                    'description' => '',
                ) );
            }
        }
    }

    /**
     * E-commerce templates options
     */
    if (file_exists(get_template_directory() . '/shop/products.json')) {
        $data = file_get_contents(get_template_directory() . '/shop/products.json');
        $data = json_decode($data, true);
        if (isset ($data['products']) && $data['products']) {
            $wp_customize->add_section('products_options', array(
                'capability' => 'edit_theme_options',
                'title' => __('E-Commerce Templates', 'site1'),
                'priority' => 200,
            ));
            $wp_customize->add_setting('links_title', array(
                'sanitize_callback' => 'theme_sanitize_raw',
                'default' => '',
            ));
            $wp_customize->add_control('links_title', array(
                'type' => 'text',
                'section' => 'products_options',
                'label' => __('Theme With E-Commerce Templates', 'site1'),
                'description' => __('The installed Theme contains the E-Commerce Page Templates, which display Products on your website.
</br></br>You have installed the Theme without the Editor Plugin. Therefore, the Menu Product Items still need to be created.
</br></br>Please add the links to your E-Commerce Store pages manually.
</br></br>Please copy the following links and paste them into your CMS Menu Item.', 'site1'),
            ));
            $wp_customize->add_setting('products_info', array(
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ));
            $wp_customize->add_control('products_info', array(
                'type' => 'text',
                'section' => 'products_options',
                'label' => __('Product List Page Template:', 'site1'),
                'description' => ThemeMultiLanguagesResolver::build_np_route_url('products'),
            ));
            $wp_customize->add_setting('product_info', array(
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ));
            $wp_customize->add_control('product_info', array(
                'type' => 'text',
                'section' => 'products_options',
                'label' => __('Product Details Page Template:', 'site1'),
                'description' => ThemeMultiLanguagesResolver::build_np_route_url('product', 1),
            ));
        }
    }

    /**
     * Other options
     */
    $wp_customize->add_section('other_options', array(
        'capability' => 'edit_theme_options',
        'title' => __('Other settings', 'site1'),
        'priority' => 200,
    ));

    $wp_customize->add_setting('include_jquery', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'theme_sanitize_checkbox',
        'default' => $theme_default_options['include_jquery'],
    ));
    $wp_customize->add_control('include_jquery', array(
        'type' => 'checkbox',
        'section' => 'other_options',
        'label' => __('Use jQuery from theme', 'site1'),
    ));

    /**
     * Sidebar options
     */
    $wp_customize->add_section('sidebar_options', array(
        'capability' => 'edit_theme_options',
        'title' => __('Sidebars', 'site1'),
        'priority' => 100,
    ));

    $layouts_choices = array(
        '' => __('Default', 'site1'),
        'left-sidebar' => __('Left', 'site1'),
        'right-sidebar' => __('Right', 'site1'),
        'none' => __('None', 'site1'),
    );

    $wp_customize->add_setting('sidebars_layout_blog', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => $theme_default_options['sidebars_layout_blog'],
    ));
    $wp_customize->add_control('sidebars_layout_blog', array(
        'type' => 'select',
        'choices' => $layouts_choices,
        'section' => 'sidebar_options',
        'label' => __('Blog Template', 'site1'),
        'description' => __('Templates for displaying blogs, archives, search results', 'site1'),
    ));

    $wp_customize->add_setting('sidebars_layout_post', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => $theme_default_options['sidebars_layout_post'],
    ));
    $wp_customize->add_control('sidebars_layout_post', array(
        'type' => 'select',
        'choices' => $layouts_choices,
        'section' => 'sidebar_options',
        'label' => __('Post Template', 'site1'),
        'description' => __('Templates for displaying single posts', 'site1'),
    ));

    $wp_customize->add_setting('sidebars_layout_default', array(
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => $theme_default_options['sidebars_layout_default'],
    ));
    $wp_customize->add_control('sidebars_layout_default', array(
        'type' => 'select',
        'choices' => $layouts_choices,
        'section' => 'sidebar_options',
        'label' => __('Default Template', 'site1'),
        'description' => __('Templates for displaying single pages and everything else', 'site1'),
    ));
}
add_action('customize_register', 'theme_customize_register', 11);

function theme_print_sidebars_blog_caption() {
    printf('<h3>%s</h3>', __('Display sidebars on:', 'site1'));
}
add_action('customize_render_control_sidebars_layout_blog', 'theme_print_sidebars_blog_caption');

/**
 * Render the site title for the selective refresh partial.
 */
function theme_customize_partial_blogname() {
	bloginfo('name');
}

/**
 * Render the site tagline for the selective refresh partial.
 */
function theme_customize_partial_blogdescription() {
	bloginfo('description');
}

/**
 * Binds the JS listener to make Customizer color_scheme control.
 *
 * Passes color scheme data as colorScheme global.
 */
function theme_customize_control_js() {
    wp_enqueue_script('theme-customizer-scripts', get_template_directory_uri() . '/js/customizer.js', array('customize-controls', 'iris', 'underscore', 'wp-util'), '20160816', true);
}
add_action('customize_controls_enqueue_scripts', 'theme_customize_control_js');

/**
 * Binds JS handlers to make the Customizer preview reload changes asynchronously.
 */
function theme_customize_preview_js() {
	wp_enqueue_script('theme-customize-preview', get_template_directory_uri() . '/js/customize-preview.js', array( 'customize-preview' ), '20160816', true);
}
add_action('customize_preview_init', 'theme_customize_preview_js');

/**
 * Enqueues front-end CSS colors.
 */
function theme_colors_css() {
    if (is_customize_preview()) {
        $colors_css = get_theme_mod('colors_css');
        if ($colors_css) {
            echo "$colors_css\n";
        }
    }
}
add_action('wp_head', 'theme_colors_css', 1003);

function theme_color_customized($theme_color) {
    $color_1 = get_theme_mod('color_1');
    return $color_1 ? $color_1 : $theme_color;
}
add_filter('theme_color', 'theme_color_customized');

/**
 * Enqueues front-end CSS fonts.
 */
function theme_fonts_css() {
    if (is_customize_preview()) {
        $fonts_css = get_theme_mod('fonts_css');
        if ($fonts_css) {
            echo "$fonts_css\n";
        }
    }
    $fonts_link = get_theme_mod('fonts_link');
    if ($fonts_link) {
        echo "$fonts_link\n";
    }
}
add_action('wp_head', 'theme_fonts_css', 1003);

/**
 * Enqueues front-end CSS typography.
 */
function theme_typography_css() {
    if (is_customize_preview()) {
        $typography_css = get_theme_mod('typography_css');
        if ($typography_css) {
            echo "$typography_css\n";
        }
    }
}
add_action('wp_head', 'theme_typography_css', 1003);

/**
 * Enqueues front-end CSS
 */
function theme_resolved_css() {
    if (!is_customize_preview()) {
        $css = get_theme_mod('resolved_css');
        if ($css) {
            $isNp = false;
            if (function_exists('np_data_provider')) {
                global $post;
                $postId = isset($post->ID) ? $post->ID : 0;
                $isNp = np_data_provider($postId)->isNp();
            }
            $stylesFromPlugin = get_option('np_theme_appearance') === 'plugin-option';
            if (!$isNp && !$stylesFromPlugin) {
                echo "<style>$css</style>\n";
            }
        }
    }
}
add_action('wp_head', 'theme_resolved_css', 1003);

function theme_base_font_size_customized($font_size) {
    if ($size = get_theme_mod('typography_base_size')) {
        if ($font_size < $size) {
            $font_size = $size;
        }
    }
    return $font_size;
}
add_filter('theme_base_font_size', 'theme_base_font_size_customized');

function theme_sanitize_checkbox($checked) {
    return $checked ? '1' : '';
}

function theme_sanitize_raw($text) {
    return $text;
}

function theme_customize_control_scripts_and_styles() {
    ?>
    <script type="text/html" id="tmpl-nav-menu-theme-options-header">
        <span class="customize-control-title customize-section-title-menu_locations-heading">Theme options</span>
        <p class="customize-control-description customize-section-title-menu_locations-description"></p>
    </script>
    <script>
        window.nicepageThemeSettings = <?php echo wp_json_encode(apply_filters('np_theme_settings', array())); ?>;
        <?php
        $url = get_template_directory() . '/css/customizer-style.css';
        $file_style_customizer = '';
        if (false !== ($creds = request_filesystem_credentials($url, '', false, false, null) ) ) {
            if (WP_Filesystem($creds) ) {
                global $wp_filesystem;
                $file_style_customizer = $wp_filesystem->get_contents($url);
            }
        }
        ?>
        window.nicepageStyleTemplate = <?php echo wp_json_encode($file_style_customizer); ?>;
    </script>
    <style>
        #accordion-section-menu_options {
            margin-top: 10px;
        }

        .disable-control {
            opacity: 0.7;
            pointer-events: none;
        }
        #customize-control-font_scheme option[value=default],
        #customize-control-font_scheme option[value=current],
        #customize-control-typography option[value=default] {
            font-weight: 500;
        }

        #_customize-input-links_title, #_customize-input-product_info, #_customize-input-products_info {
            display: none;
        }

        <?php foreach (array(100, 300, 400, 500, 700, 900) as $weight): ?>
        #customize-control-typography_heading_weight option[value="<?php echo $weight; ?>"] { font-weight: <?php echo $weight; ?>; }
        <?php endforeach; ?>
    </style>
    <?php
}
add_action('customize_controls_print_footer_scripts', 'theme_customize_control_scripts_and_styles');
