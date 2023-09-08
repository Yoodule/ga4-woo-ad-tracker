<?php
/**
 * @link              https://yoodule.com?utm_source=james-kelly&utm_campaign=analytic-plugin&utm_medium=wp-dash
 * @since             1.0.0
 * @package           Ga4_Woo_Ad_Tracker
 *
 * @wordpress-plugin
 * Plugin Name:       GA4, Woocommerce and Ad Conversion Tracker
 * Plugin URI:        https://yoodule.com?utm_source=james-kelly&utm_campaign=analytic-plugin&utm_medium=wp-dash
 * Description:       Google Analytic 4, Woocommerce and Google Ads Conversion Tracking made easy - [3 in 1]
 * Version:           1.0.0
 * Author:            Yoodule
 * Author URI:        https://yoodule.com?utm_source=james-kelly&utm_campaign=analytic-plugin&utm_medium=wp-dash
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ga4-woo-ad-tracker
 * Domain Path:       /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 5.0.0
 */


// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Load all the plugin files and dependencies
 */
function ga4_woo_ad_tracker_includes() {
    // Include main plugin class
    require_once plugin_dir_path(__FILE__) . 'includes/class-ga4-woo-ad-tracker.php';
    
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        // WooCommerce-specific hooks and functionality
        require_once plugin_dir_path(__FILE__) . 'woocommerce/ga4-woo-tracking.php';
    }
}

ga4_woo_ad_tracker_includes();

/**
 * Initialize the plugin
 */
function run_ga4_woo_ad_tracker() {
    $plugin = new Ga4_Woo_Ad_Tracker();
    $plugin->run();
}

run_ga4_woo_ad_tracker();
