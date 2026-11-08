<?php
/**
 * Npwizard
 */

class Npwizard {

    protected $version = '2.8.0';
    protected $theme_name = '';
    protected $theme_title = '';
    protected $page_slug = '';
    protected $page_title = '';
    protected $options_steps = array();
    protected $plugin_url = '';
    protected $plugin_path = '';
    protected $parent_slug = '';
    protected $tgmpa_instance;
    protected $tgmpa_menu_slug = 'tgmpa-install-plugins';
    protected $tgmpa_url = 'themes.php?page=tgmpa-install-plugins';

    /**
     * Constructor
     *
     * @param $options options
     */
    public function __construct($options) {
        $this->set_options($options);
        $this->init();
    }

    /**
     * Set options
     *
     * @param $options options
     */
    public function set_options($options) {

        locate_template(array('library/class-tgm-plugin-activation.php'), true);

        if(isset($options['page_slug'])) {
            $this->page_slug = esc_attr($options['page_slug']);
        }
        if(isset($options['page_title'])) {
            $this->page_title = esc_attr($options['page_title']);
        }
        if(isset($options['steps'])) {
            $this->options_steps = $options['steps'];
        }
        $this->plugin_path = trailingslashit(dirname(__FILE__));
        $relative_url = str_replace(get_template_directory(), '', $this->plugin_path);
        $this->plugin_url = trailingslashit(get_template_directory_uri() . $relative_url);
        $current_theme = wp_get_theme();
        $this->theme_title = $current_theme->get('Name');
        $this->theme_name = strtolower(preg_replace('#[^a-zA-Z]#', '', $current_theme->get('Name')));
        $this->page_slug = apply_filters($this->theme_name . '_theme_setup_wizard_page_slug', $this->theme_name . '-setup');
        $this->parent_slug = apply_filters($this->theme_name . '_theme_setup_wizard_parent_slug', '');
        //set relative plugin path url
        $this->plugin_path = trailingslashit($this->cleanPath(dirname(__FILE__)));
        $relative_url = str_replace($this->cleanPath(get_template_directory()), '', $this->plugin_path);
        $this->plugin_url = trailingslashit(get_template_directory_uri() . $relative_url);
    }


    /**
     * Redirect when activated theme
     */
    public function redirect_to_wizard() {
        global $pagenow;
        if(is_admin() && 'themes.php' == $pagenow && isset($_GET['activated']) && current_user_can('manage_options')) {
            wp_redirect(admin_url('themes.php?page=' . esc_attr($this->page_slug)));
        }
    }

    /**
     * Add styles and scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_style('npwizard-style', $this->plugin_url . 'assets/css/npwizard-admin-style.css', array(), $this->version);
        wp_register_script('npwizard', $this->plugin_url . 'assets/js/npwizard.js', array('jquery'), time());
        wp_localize_script(
            'npwizard',
            'npwizard_params',
            array(
                'ajaxurl' 		 => admin_url('admin-ajax.php'),
                'wpnonce' 		 => wp_create_nonce('npwizard_nonce'),
                'verify_text'    => esc_html('verifying', 'site1'),
                'urlContent'     => admin_url('admin-ajax.php'),
                'settingsUrl'    => admin_url('admin.php?page=np_settings'),
                'wpnonceContent' => wp_create_nonce('theme-content-importer'),
                'wpnonceThemeAppearance' => wp_create_nonce('np-theme-appearance'),
                'actionImportContent'  => 'theme_import_content',
                'actionReplaceContent'  => 'theme_replace_content',
                'actionImportProducts'  => 'theme_import_products',
                'actionReplaceProducts'  => 'theme_replace_products',
            )
        );
        wp_enqueue_script('npwizard');
    }

    /**
     * @return Npwizard
     */
    public static function get_instance() {
        if (! self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * @param $status
     * @return bool
     */
    public function tgmpa_load($status) {
        return is_admin() || current_user_can('install_themes');
    }

    /**
     * Get configured TGMPA instance
     *
     * @access public
     */
    public function get_tgmpa_instance() {
        $this->tgmpa_instance = call_user_func(array(get_class($GLOBALS['tgmpa']), 'get_instance'));
    }

    /**
     * Update $tgmpa_menu_slug and $tgmpa_parent_slug from TGMPA instance
     *
     * @access public
     */
    public function set_tgmpa_url() {
        $this->tgmpa_menu_slug = (property_exists($this->tgmpa_instance, 'menu')) ? $this->tgmpa_instance->menu : $this->tgmpa_menu_slug;
        $this->tgmpa_menu_slug = apply_filters($this->theme_name . '_theme_setup_wizard_tgmpa_menu_slug', $this->tgmpa_menu_slug);
        $tgmpa_parent_slug = (property_exists($this->tgmpa_instance, 'parent_slug') && $this->tgmpa_instance->parent_slug !== 'themes.php') ? 'admin.php' : 'themes.php';
        $this->tgmpa_url = apply_filters($this->theme_name . '_theme_setup_wizard_tgmpa_url', $tgmpa_parent_slug . '?page=' . $this->tgmpa_menu_slug);
    }

    /**
     * Make a modal screen for the wizard
     */
    public function menu_page() {
        add_theme_page(esc_html__('Theme Wizard', 'site1'), esc_html__('Theme Wizard', 'site1'), 'manage_options', $this->page_slug, array($this, 'wizard_page'));
    }

    /**
     * Make an interface for the wizard
     */
    public function wizard_page() {
        tgmpa_load_bulk_installer();
        // install plugins with TGM.
        if (! class_exists('TGM_Plugin_Activation') || ! isset($GLOBALS['tgmpa'])) {
            die('Failed to find TGM');
        }
        $url = wp_nonce_url(add_query_arg(array('plugins' => 'go')), 'npwizard-setup');

        // copied from TGM
        $method = ''; // Leave blank so WP_Filesystem can populate it as necessary.
        $fields = array_keys($_POST); // Extra fields to pass to WP_Filesystem.
        if (false === ($creds = request_filesystem_credentials(esc_url_raw($url), $method, false, false, $fields))) {
            return true; // Stop the normal page form from displaying, credential request form will be shown.
        }
        // Now we have some credentials, setup WP_Filesystem.
        if (! WP_Filesystem($creds)) {
            // Our credentials were no good, ask the user for them again.
            request_filesystem_credentials(esc_url_raw($url), $method, true, false, $fields);
            return true;
        }
        /* If we arrive here, we have the filesystem */ ?>
        <div class="wrap npwizard-wrap-perent">
            <?php
            echo '<div class="card npwizard-wrap">';
            $steps = $this->get_steps();
            echo '<ul class="npwizard-menu">';
            foreach($steps as $step) {
                $class = 'step step-' . esc_attr($step['id']);
                echo '<li data-step="' . esc_attr($step['id']) . '" class="' . esc_attr($class) . '">';
                printf('<h2>%s</h2>', esc_html($step['title']));
                // $content split
                $content = call_user_func(array($this, $step['view']));
                if(isset($content['summary'])) {
                    $importOptions = '';
                    if (isset($content['import_options'])) {
                        $importOptions = $content['import_options'];
                    }
                    printf(
                        '<div class="summary">%s</div>',
                        wp_kses_post($content['summary']) . $importOptions
                    );
                }
                if(isset($content['buttons'])) {
                    echo $content['buttons'];
                }
                if(isset($content['detail'])) {
                    printf(
                        '<div class="detail">%s</div>',
                        $content['detail'] // Need to escape this
                    );
                }
                // Next button
                if(isset($step['button_text']) && $step['button_text']) {
                    printf(
                        '<div class="button-wrap"><a href="#" class="button button-primary do-it" data-callback="%s" data-step="%s">%s</a></div>',
                        esc_attr($step['callback']),
                        esc_attr($step['id']),
                        esc_html($step['button_text'])
                    );
                }
                // Replace button
                if(isset($step['button2_text']) && $step['button2_text']) {
                    printf(
                        '<div class="button-wrap" style="margin-left: 0.5em;"><a href="#" class="button button-secondary do-it" data-callback="%s" data-step="%s">%s</a></div>',
                        esc_attr($step['callback2']),
                        esc_attr($step['id']),
                        esc_html($step['button2_text'])
                    );
                }
                // Theme appearance button
                if (isset($step['button3_text']) && $step['button3_text']) {
                    printf(
                        '<div class="button-wrap" style="margin-left: 0.5em;"><a href="#" class="button button-primary do-it" data-callback="%s" data-step="%s">%s</a></div>',
                        esc_attr($step['callback3']),
                        esc_attr($step['id']),
                        esc_html($step['button3_text'])
                    );
                }
                // Products import button
                if (isset($step['button_products_import_text']) && $step['button_products_import_text']) {
                    printf(
                        '<div class="button-wrap"><a href="#" class="button button-primary do-it" data-callback="%s" data-step="%s">%s</a></div>',
                        esc_attr($step['callback_products_import']),
                        esc_attr($step['id']),
                        esc_html($step['button_products_import_text'])
                    );
                }
                // Products replace button
                if (isset($step['button_products_replace_text']) && $step['button_products_replace_text']) {
                    printf(
                        '<div class="button-wrap" style="margin-left: 0.5em;"><a href="#" class="button button-secondary do-it" data-callback="%s" data-step="%s">%s</a></div>',
                        esc_attr($step['callback_products_replace']),
                        esc_attr($step['id']),
                        esc_html($step['button_products_replace_text'])
                    );
                }
                // Skip button
                if(isset($step['can_skip']) && $step['can_skip']) {
                    printf(
                        '<div class="button-wrap" style="margin-left: 0.5em;"><a href="#" class="button button-secondary do-it" data-callback="%s" data-step="%s">%s</a></div>',
                        'do_next_step',
                        esc_attr($step['id']),
                        __('Skip', 'site1')
                    );
                }

                echo '</li>';
            }
            echo '</ul>';
            ?>
            <div class="step-loading"><span class="spinner"></span></div>
            <?php
            if (defined('APP_PLUGIN_VERSION') && isset($GLOBALS['npThemeVersion']) && (float)APP_PLUGIN_VERSION > (float)$GLOBALS['npThemeVersion']) {
                // if our theme older then plugin
                echo sprintf('<div class="npwizard-warning"><p>%s</p></div>', 'The active theme has a version lower than the plugin version. Please update the theme too.', 'site1');
            }
            ?>
        </div><!-- .npwizard-wrap -->

        </div><!-- .wrap -->
    <?php }

    /**
     * Set options for the steps
     *
     * @return array
     */
    public function get_steps() {
        $steps = array(
            'done' => array(
                'id'			=> 'done',
                'title'			=> __('Your website is ready!', 'site1'),
                'icon'			=> 'yes',
                'view'			=> 'get_step_done',
                'callback'		=> ''
            )
        );
        $theme_appearance_step = array(
            'theme_appearance' => array(
                'id' => 'theme_appearance',
                'title' => __('Select Site Style', 'site1'),
                'icon' => 'welcome-content-menus',
                'view' => 'getStepThemeAppearance',
                'callback3' => 'theme_appearance_update',
                'button3_text' => __('Apply', 'site1'),
                'can_skip' => true,
                'can_replace' => true
            ),
        );
        if (get_option('whiteLabelName') && !get_option('np_theme_appearance')) {
            $steps = $theme_appearance_step + $steps;
        }
        $import_content_step = array(
            'content' => array(
                'id'			=> 'content',
                'title'			=> __('Import Content', 'site1'),
                'icon'			=> 'welcome-content-menus',
                'view'			=> 'get_step_content',
                'callback'		=> 'import_content',
                'callback2'		=> 'replace_content',
                'button_text'	=> __('Import Content', 'site1'),
                'button2_text'	=> __('Replace previously imported Content', 'site1'),
                'can_skip'		=> true,
                'can_replace'	=> true
            ),
        );
        $steps = $import_content_step + $steps;
        $import_products_step = array(
            'products' => array(
                'id' => 'products',
                'title' => __('Import Products', 'site1'),
                'icon' => 'welcome-content-menus',
                'view' => 'getStepProducts',
                'callback_products_import' => 'import_products',
                'callback_products_replace' => 'replace_products',
                'button_products_import_text' => __('Import Products', 'site1'),
                'button_products_replace_text' => __('Replace previously imported Products', 'site1'),
                'can_skip' => true,
                'can_replace' => true
            ),
        );
        $productsJsonPath = get_template_directory() . '/shop/products.json';
        $contentJsonPath = dirname(dirname(__FILE__)) . '/content/content.json';
        if (file_exists($productsJsonPath)) {
            $jsonContent = file_get_contents($productsJsonPath);
            $data = json_decode($jsonContent, true);
            if (!empty($data['products'])) {
                $steps = $import_products_step + $steps;
            }
        } else if (file_exists($contentJsonPath)) {
            $jsonContent = file_get_contents($contentJsonPath);
            $data = json_decode($jsonContent, true);

            if (!empty($data['Parameters']['productsJson'])) {
                $steps = $import_products_step + $steps;
            }
        }
        $install_plugin_step = array(
            'plugins' => array(
                'id'			=> 'plugins',
                'title'			=> __('Install Theme Plugins', 'site1'),
                'icon'			=> 'admin-plugins',
                'view'			=> 'get_step_plugins',
                'callback'		=> 'install_plugins',
                'button_text'	=> __('Install Plugins', 'site1'),
                'can_skip'		=> false
            ),
        );
        $steps = $install_plugin_step + $steps;
        return $steps;
    }

    /**
     * Get the content for the plugins step
     * @return $content array
     */
    public function get_step_plugins() {
        $plugins = $this->get_plugins();
        $content = array();
        // The summary element will be the content visible to the user
        $content['summary'] = sprintf(
            '<p>%s</p>',
            __('Install plugins included with the Theme. <br>Click the "Install Plugins" button to start the installation.', 'site1')
        );
        $content = apply_filters('npwizard_filter_summary_content', $content);

        // The detail element is initially hidden from the user
        $content['detail'] = '<ul class="npwizard-do-plugins">';
        // Add each plugin into a list
        foreach($plugins['all'] as $slug=>$plugin) {
            $content['detail'] .= '<li data-slug="' . esc_attr($slug) . '">' . esc_html($plugin['name']) . '<span>';
            $keys = array();
            if (isset($plugins['install'][ $slug ])) {
                $keys[] = 'Installation';
            }
            if (isset($plugins['update'][ $slug ])) {
                $keys[] = 'Update';
            }
            if (isset($plugins['activate'][ $slug ])) {
                $keys[] = 'Activation';
            }
            $content['detail'] .= implode(' and ', $keys) . ' required';
            $content['detail'] .= '</span></li>';
        }
        $content['detail'] .= '</ul>';

        return $content;
    }

    /**
     * Print the content for the widgets step
     *
     */
    public function get_step_content() {
        $content = array();
        // Check if the content imported
        $hideImport = get_option('themler_hide_import_notice');
        if($hideImport) {
            $content['summary'] = sprintf(
                '<p>%s</p>',
                __('Content has already been imported. Please skip this step', 'site1')
           );
        } else {
            $content['summary'] = sprintf(
                '<p>%s</p>',
                __('Theme has Pages, Images, Menu, Header, and Footer. </br></br>Do you want to import the Content?', 'site1')
            );
        }
        $content['import_options'] = sprintf(
            '<p style="margin: 20px 0 0 0;" class="import-options"><input type="checkbox" id="importSidebarsContent" name="importSidebarsContent" checked="checked"><label for="importSidebarsContent">%s</label></p>',
            __('Import Sidebars Content', 'site1')
        );
        if (class_exists('Woocommerce')) {
            $content['import_options'] .= sprintf(
                '<p style="margin: 5px 0 0 0;" class="import-options"><input type="checkbox" id="importProductsContent" name="importProductsContent" checked="checked"><label for="importProductsContent">%s</label></p>',
                __('Import Products Content to Woocommerce', 'site1')
            );
        }

        $content = apply_filters('npwizard_filter_content', $content);
        return $content;
    }

    /**
     * Print the content for the widgets step
     */
    public function getStepProducts() {
        $content = array();
        $options = '';

        $contentPath = dirname(dirname(dirname(__FILE__))) . '/content/content.json';
        if (file_exists($contentPath)) {
            $jsonContent = file_get_contents($contentPath);
            $data = json_decode($jsonContent, true);
            if (!empty($data['Parameters']['productsJson'])) {
                $productsData = json_decode($data['Parameters']['productsJson'], true);
                if (!empty($productsData['products'])) {
                    $options .= '<option value="content-source" ' . selected(get_option('np_products_source'), 'content-source', false) . '>' . __('Content', 'site1') . '</option>';
                }
            }
        }

        $themeProductsPath = get_template_directory() . '/shop/products.json';
        if (file_exists($themeProductsPath)) {
            $jsonContent = file_get_contents($themeProductsPath);
            $data = json_decode($jsonContent, true);
            if (!empty($data['products'])) {
                $options .= '<option value="theme-source" ' . selected(get_option('np_products_source'), 'theme-source', false) . '>' . __('Theme', 'site1') . '</option>';
            }
        }

        $content['detail'] = '
    <div class="products-options">
        <label for="np_products_source">' . __('We have found Products that you can import to your site. </br></br>Do you want to continue with importing?', 'site1') . '</label>
        </br></br>
        <select name="np_products_source" id="np_products_source">
            ' . $options . '
        </select>
    </div>
    <p>';

        // Check if the products imported
        $old_products_data = getProductsJson();
        if (isset($old_products_data['products']) && count($old_products_data['products']) > 0) {
            $content['detail'] .= sprintf(
                '<p><b>%s</b></p>',
                __('Please note that some Products already exist in your database, and the import will overwrite the current ones.', 'site1')
            );
        }
        return $content;
    }

    /**
     * Print the theme appearance for the widgets step
     */
    public function getStepThemeAppearance() {
        $content['detail'] = '
    <div class="theme-appearance-options">
        <label for="np_theme_appearance">' . __('Choose the source for your site\'s Colors, Fonts, Header, and Footer, as well as for the Blog, E-Commerce, and other Templates.', 'site1') . '</label>
        </br></br>
        <select name="np_theme_appearance" id="np_theme_appearance">
            <option value="theme-option" ' . selected(get_option('np_theme_appearance'), 'theme-option', false) . '>' . __('Theme', 'site1') . '</option>
            <option value="plugin-option" ' . selected(get_option('np_theme_appearance'), 'plugin-option', false) . '>' . __('Plugin', 'site1') . '</option>
        </select>
    </div>
    <p>' . sprintf(
                __('You can change it later in the <a href="%s">Nicepage Settings</a>', 'site1'),
                esc_url(admin_url('admin.php?page=np_settings'))
            ) . '</p>';
        return $content;
    }

    /**
     * Print the content for the final step
     */
    public function get_step_done() {
        $content = array();
        $content['summary'] = sprintf(
            '<p>%s</p>',
            __('Congratulations! The theme has been activated and your website is ready.', 'site1')
        );
        $content['summary'] .= sprintf( '<p>%s</p>', 'Create a new page with the Nicepage Editor.', 'site1' );
        $content['buttons'] = '<br><a href="' . admin_url('post-new.php?post_type=page&np_new=1') . '" class="button button-primary">Create Page</a>';
        $content['buttons'] .= '<a href="' . get_site_url() . '" style="margin-left: 5px;" id="visit-site" class="button button-secondary">Visit Site</a>';
        $content['buttons'] .= '<a href="' . get_admin_url() . '" style="margin-left: 5px;" id="visit-site" class="button button-secondary">Close</a>';
        return $content;
    }

	/**
	 * Get the plugins registered with TGMPA
	 */
	public function get_plugins() {
		$instance = call_user_func(array(get_class($GLOBALS['tgmpa']), 'get_instance'));
		$plugins = array(
			'all' 		=> array(),
			'install'	=> array(),
			'update'	=> array(),
			'activate'	=> array()
		);
		foreach($instance->plugins as $slug=>$plugin) {
			if($instance->is_plugin_active($slug) && false === $instance->does_plugin_have_update($slug)) {
				// Plugin is installed and up to date
				continue;
			} else {
				$plugins['all'][$slug] = $plugin;
				if(! $instance->is_plugin_installed($slug)) {
					$plugins['install'][$slug] = $plugin;
				} else {
					if(false !== $instance->does_plugin_have_update($slug)) {
						$plugins['update'][$slug] = $plugin;
					}
					if($instance->can_plugin_activate($slug)) {
						$plugins['activate'][$slug] = $plugin;
					}
				}
			}
		}
		return $plugins;
	}

	public function setup_plugins() {
		if (! check_ajax_referer('npwizard_nonce', 'wpnonce') || empty($_POST['slug'])) {
			wp_send_json_error(array('error' => 1, 'message' => esc_html__('No Slug Found', 'site1')));
		}
		$json = array();
		// send back some json we use to hit up TGM
		$plugins = $this->get_plugins();
        $whiteLabelJson = get_option('whiteLabelName');
        $whiteLabelOptions = json_decode($whiteLabelJson);
		// what are we doing with this plugin?
		foreach ($plugins['activate'] as $slug => $plugin) {
			if ($_POST['slug'] == $slug) {
				$json = array(
					'url'           => admin_url($this->tgmpa_url),
					'plugin'        => array($slug),
					'tgmpa-page'    => $this->tgmpa_menu_slug,
					'plugin_status' => 'all',
					'_wpnonce'      => wp_create_nonce('bulk-plugins'),
					'action'        => 'tgmpa-bulk-activate',
					'action2'       => - 1,
					'message'       => esc_html__('Activating Plugin', 'site1'),
                    'db_np'         => isset($whiteLabelOptions->name) ? $whiteLabelOptions->name : $plugin['name'],
                    'current_np'    => $plugin['name'],
                    'plugin_url'    => admin_url('plugins.php'),
				);
				break;
			}
		}
		foreach ($plugins['update'] as $slug => $plugin) {
			if ($_POST['slug'] == $slug) {
				$json = array(
					'url'           => admin_url($this->tgmpa_url),
					'plugin'        => array($slug),
					'tgmpa-page'    => $this->tgmpa_menu_slug,
					'plugin_status' => 'all',
					'_wpnonce'      => wp_create_nonce('bulk-plugins'),
					'action'        => 'tgmpa-bulk-update',
					'action2'       => - 1,
					'message'       => esc_html__('Updating Plugin', 'site1'),
				);
				break;
			}
		}
		foreach ($plugins['install'] as $slug => $plugin) {
			if ($_POST['slug'] == $slug) {
				$json = array(
					'url'           => admin_url($this->tgmpa_url),
					'plugin'        => array($slug),
					'tgmpa-page'    => $this->tgmpa_menu_slug,
					'plugin_status' => 'all',
					'_wpnonce'      => wp_create_nonce('bulk-plugins'),
					'action'        => 'tgmpa-bulk-install',
					'action2'       => - 1,
					'message'       => esc_html__('Installing Plugin', 'site1'),
				);
				break;
			}
		}
		if ($json) {
			$json['hash'] = md5(serialize($json)); // used for checking if duplicates happen, move to next plugin
			wp_send_json($json);
		} else {
			wp_send_json(array('done' => 1, 'message' => esc_html__('Success', 'site1')));
		}
		exit;
	}

    public function setup_content() {
        if (! check_ajax_referer('npwizard_nonce', 'wpnonce') || empty($_POST['slug'])) {
            wp_send_json_error(array('error' => 1, 'message' => esc_html__('No Slug Found', 'site1')));
        }
        $json = array(
            'url'           => admin_url('admin-ajax.php'),
            '_wpnonce'      => wp_create_nonce('theme-content-importer'),
            'action'        => 'theme_import_content',
            'message'       => esc_html__('Import Content', 'site1'),
       );
        if ($json) {
            $json['hash'] = md5(serialize($json)); // used for checking if duplicates happen, move to next plugin
            wp_send_json($json);
        } else {
            wp_send_json(array('done' => 1, 'message' => esc_html__('Success', 'site1')));
        }
        exit;
    }

    /**
     * Clean a path and return
     *
     * @param string $path
     *
     * @return mixed|string
     */
    public static function cleanPath($path) {
        $path = str_replace('', '', str_replace(array("\\", "\\\\"), '/', $path));
        if ($path[ strlen($path) - 1 ] === '/') {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    /**
     * Hooks and filters
     */
    public function init() {
        add_action('after_switch_theme', array($this, 'redirect_to_wizard'));
        if (class_exists('TGM_Plugin_Activation') && isset($GLOBALS['tgmpa'])) {
            add_action('init', array($this, 'get_tgmpa_instance'), 30);
            add_action('init', array($this, 'set_tgmpa_url'), 40);
            add_action('in_admin_header', function () {
                $pagename = get_admin_page_title();
                if ($pagename !== "Theme Wizard") return;
                remove_all_actions('admin_notices');
                remove_all_actions('all_admin_notices');
            }, 1000);
        }
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_menu', array($this, 'menu_page'));
        add_action('admin_init', array($this, 'get_plugins'), 30);
        add_filter('tgmpa_load', array($this, 'tgmpa_load'), 10, 1);
        add_action('wp_ajax_setup_plugins', array($this, 'setup_plugins'));
        add_action('wp_ajax_setup_content', array($this, 'setup_content'));
    }

}