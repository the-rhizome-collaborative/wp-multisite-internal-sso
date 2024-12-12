<?php
/**
 * Plugin Name: WP Multisite Internal SSO
 * Plugin URI:  https://example.com
 * Description: Enables automatic login (SSO) for users from one multisite installation to another.
 * Version:     0.0.5
 * Author:      
 * Author URI:  https://example.com
 * Network:     true
 * License:     GPL2
 */

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
define('WPMSSSOMS_SSO_PLUGIN_DIR', plugin_dir_path( __FILE__ ));
define('WPMSSSOMS_SSO_PLUGIN_URL', plugin_dir_url( __FILE__ ));

// Include the main class file.
require_once WPMSSSOMS_SSO_PLUGIN_DIR . 'inc/class-wp-multisite-internal-sso.php';

// Initialize the plugin class on plugins_loaded to ensure all WordPress functions are available.
add_action('plugins_loaded', 'my_ms_sso_plugin_init');

function my_ms_sso_plugin_init() {
    // Instantiate the main plugin class.
    $GLOBALS['wp_multisite_internal_sso'] = new WP_Multisite_Internal_SSO();

    // Add a debug log entry.
    if ( WP_DEBUG && WP_DEBUG_LOG ) {
        error_log( 'WP Multisite Internal SSO plugin initialized.' );
    }
}

add_action( 'wp_body_open', 'display_logged_in_status' );

function display_logged_in_status() {
    if ( is_user_logged_in() ) {
        echo '<div style="position: fixed; top: 35px; right: 0; background: #000; color: #fff; padding: 10px;">Logged in</div>';
    } else {
        echo '<div style="position: fixed; top: 0; right: 0; background: #000; color: #fff; padding: 10px;">Not logged in</div>';
    }
}
