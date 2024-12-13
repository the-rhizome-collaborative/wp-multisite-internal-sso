<?php
/**
 * Plugin Name: WP Multisite Internal SSO
 * Plugin URI:  https://github.com/9ete/wp-multisite-internal-sso
 * Description: Enables automatic login (SSO) for users from one multisite installation to another.
 * Version:     0.0.9
 * Author:      9ete
 * Author URI:  https://petelower.com
 * Network:     true
 * License:     GPL2
 */

if (! defined('ABSPATH')) {
    exit;
}

define('WPMSSSOMS_SSO_PLUGIN_DIR', plugin_dir_path( __FILE__ ));

// define('WPMIS_PRIMARY', 'https://foo.site');
// define('WPMIS_SECONDARY', 'https://bar.site');

require_once WPMSSSOMS_SSO_PLUGIN_DIR . 'inc/class-wp-multisite-internal-sso.php';
add_action('plugins_loaded', 'my_ms_sso_plugin_init');

function my_ms_sso_plugin_init() {
    $GLOBALS['wp_multisite_internal_sso'] = new WP_Multisite_Internal_SSO();
}

add_action( 'wp_body_open', 'display_logged_in_status' );

function display_logged_in_status() {
    if ( is_user_logged_in() ) {
        echo '<div style="position: relative; top: 0; right: 0; background: #000; color: #fff; padding: 10px;">Logged in - ' .get_site_url(). '</div>';
    } else {
        echo '<div style="position: relative; top: 0; right: 0; background: #000; color: #fff; padding: 10px;">Not logged in - ' .get_site_url(). '</div>';
    }
}
