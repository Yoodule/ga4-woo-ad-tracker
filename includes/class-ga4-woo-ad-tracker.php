<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://http://yoodule.com?utm_source=james-kelly&utm_campaign=analytic-plugin&utm_medium=wp-dash
 * @since      1.0.0
 *
 * @package    Ga4_Woo_Ad_Tracker
 * @subpackage Ga4_Woo_Ad_Tracker/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Ga4_Woo_Ad_Tracker
 * @subpackage Ga4_Woo_Ad_Tracker/includes
 * @author     Yoodule <help@yoodule.com>
 */

 class Ga4_Woo_Ad_Tracker {

    private $plugin_name;    

    public function add_plugin_page() {
        add_menu_page(
            'GA4, Woocommerce and Ad Conversion Tracker', // Page title
            'GA4 Tracker', // Menu title
            'manage_options', // Capability
            $this->plugin_name, // Menu slug
            [$this, 'options_page'], // Callback
            'dashicons-analytics' // Icon URL
        );
    }
    

    public function options_page() {
        ?>
        <div class="wrap">
            <h1><?php echo get_admin_page_title(); ?></h1>
            <form action="options.php" method="post">
                <?php
                // Output nonce, action, and option_page fields
                settings_fields($this->plugin_name);
    
                // Output settings sections and their fields
                do_settings_sections($this->plugin_name);
    
                // Output save settings button
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }
    

    public function settings_init() {
        // Register settings
        register_setting($this->plugin_name, 'ga4_id');
        register_setting($this->plugin_name, 'conversion_id');
    
        // Add settings section
        add_settings_section(
            $this->plugin_name . '_main', // ID
            'Main Settings', // Title
            [$this, 'settings_section_callback'], // Callback
            $this->plugin_name // Page
        );
    
        // For GA4 ID field
        add_settings_field(
            'ga4_id', // ID
            'Google Analytics 4 ID', // Title
            [$this, 'ga4_id_render'], // Callback
            $this->plugin_name, // Page
            $this->plugin_name . '_main', // Section
            [ 'label_for' => 'ga4_id', 'description' => 'Enter your Google Analytics 4 tracking ID here. Format: GA-XXXXXXXXX' ] // Extra arguments
        );

        // For Conversion ID field
        add_settings_field(
            'conversion_id', // ID
            'Google Ad Conversion ID', // Title
            [$this, 'conversion_id_render'], // Callback
            $this->plugin_name, // Page
            $this->plugin_name . '_main', // Section
            [
                'label_for' => 'conversion_id', 
                'description' => 'Enter your Google Ad Conversion ID here. Format: AW-XXXXXXXXX'
            ] // Extra arguments
        );

    }

    public function settings_section_callback() {
        echo '<p>Enter your Google Analytics 4 and Google Ad Conversion IDs to enable tracking.</p>';
    }
    

    public function ga4_id_render( $args ) {
        $ga4_id = get_option('ga4_id', '');
        echo '<input type="text" id="' . esc_attr( $args['label_for'] ) . '" name="ga4_id" value="' . esc_attr($ga4_id) . '">';
        echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
    }
    
    public function conversion_id_render( $args ) {
        $conversion_id = get_option('conversion_id', '');
        echo '<input type="text" id="' . esc_attr( $args['label_for'] ) . '" name="conversion_id" value="' . esc_attr($conversion_id) . '">';
        echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
    }
    
    public function add_tracking_code() {
        // render global script
        do_action('render_ga4_global_script');
    }
    
    public function render_ga4_woo_ad_tracker_global_script() {

        // check if defined
        if(defined('GA4_SCRIPT_RENDERED')) return ;

        // define
        define('GA4_SCRIPT_RENDERED', 1);

        // Get options from database and remove 'AW-' prefix if present
        $ga4_id = get_option('ga4_id');
        $conversion_id = preg_replace('/^AW-/', '', get_option('conversion_id'));
        
        // Add GA4 Tracking Code
        if (!empty($ga4_id)) {
            echo "<!-- Global site tag (gtag.js) - Google Analytics -->";
            echo "<script async src='https://www.googletagmanager.com/gtag/js?id=" . esc_js($ga4_id) . "'></script>";
            echo "<script>
                    window.dataLayer = window.dataLayer || [];
                    function gtag(){dataLayer.push(arguments);}
                    gtag('js', new Date());
                    gtag('config', '" . esc_js($ga4_id) . "');
                  </script>";
        }
    
        // Add Google Ad Conversion Tracking Code
        if (!empty($conversion_id)) {
            echo "<!-- Global site tag (gtag.js) - Google Ads: " . esc_js($conversion_id) . " -->";
            echo "<script async src='https://www.googletagmanager.com/gtag/js?id=AW-" . esc_js($conversion_id) . "'></script>";
            echo "<script>
                    gtag('config', 'AW-" . esc_js($conversion_id) . "');
                  </script>";
        }
    }

    function add_plugin_page_settings_link($links) {
        $settings_link = "<a href='admin.php?page={$this->plugin_name}'>" . __('Settings', $this->plugin_name) . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function run() {
        $this->plugin_name = 'ga4-woo-ad-tracker';
    
        // Add admin menu
        add_action('admin_menu', [$this, 'add_plugin_page']);
    
        // Initialize settings
        add_action('admin_init', [$this, 'settings_init']);
        
        // Add GA4 and Conversion tracking
        add_action('render_ga4_global_script', [$this, 'render_ga4_woo_ad_tracker_global_script']);

        // Add GA4 and Conversion tracking
        add_action('wp_head', [$this, 'add_tracking_code']);
        
        // add setting link to plugin menu
        add_filter("plugin_action_links_$this->plugin_name/$this->plugin_name.php", [$this, 'add_plugin_page_settings_link']);

    }  
}
