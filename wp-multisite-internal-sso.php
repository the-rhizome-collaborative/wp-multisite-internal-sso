<?php
/**
 * Plugin Name: WP Multisite Internal SSO
 * Plugin URI:  https://github.com/9ete/wp-multisite-internal-sso
 * Description: Enables automatic login (SSO) for users from one multisite installation to another.
 * Version:     0.1.8
 * Author:      9ete
 * Author URI:  https://petelower.com
 * Network:     true
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPMIS_SSO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPMIS_SSO_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPMIS_SSO_PLUGIN_VERSION',  get_file_data(__FILE__, array('Version' => 'Version'))['Version'] );

require_once WPMIS_SSO_PLUGIN_PATH . 'includes/class-wp-multisite-internal-sso.php';

function wpmis_sso_init() {
    $GLOBALS['wp_multisite_internal_sso'] = new WP_Multisite_Internal_SSO();
}

if ( is_multisite() && wpmisso_allow_request() && ! isset( $_GET['wpmisso_ignore'] ) ) {
    add_action( 'plugins_loaded', 'wpmis_sso_init' );
}

function wpmisso_allow_request() {

    error_log("\n\n" . 'REQUEST: ' . $_SERVER['REQUEST_URI'] . "\n\n", 3, WP_CONTENT_DIR . '/sso-debug.log' );

    $file_requests_to_ignore = [
        '*.ico',
        'robots.txt',
        'sitemap.xml',
        'wp-cron.php',
        'admin-ajax.php',
        'wp-json',
        '*.png',
        '*.jpg',
        '*.jpeg',
        '*.gif',
        '*.css',
        '*.js',
        '*.woff',
        '*.woff2',
        '*.ttf',
        '*.svg',
        '*.eot',
    ];

    foreach ($file_requests_to_ignore as $ignored_file) {
        $pattern = '/' . str_replace(['*', '.'], ['.*', '\.'], $ignored_file) . '$/';
        if (preg_match($pattern, $_SERVER['REQUEST_URI'])) {
            error_log( "WPMIS SSO: " . 'Skipping SSO due to request of ' . $ignored_file . 'URI: ' . $_SERVER['REQUEST_URI'] . "\n", 3, WP_CONTENT_DIR . '/sso-debug.log' );
            return false;
        }
    }

    return true;
}

// add init hook to check for $_GET['wpmisso_request'] and if it exists either create a cookie with a value of one or increment the cookie value by one
add_action( 'init', 'wpmisso_check_request' );
function wpmisso_check_request() {
    if ( isset( $_GET['wpmisso_request'] ) ) {
        $cookie_name = 'wpmisso_request';
        $cookie_value = isset( $_COOKIE[$cookie_name] ) ? $_COOKIE[$cookie_name] + 1 : 1;
        setcookie( $cookie_name, $cookie_value, time() + 3600, '/', true );
    }
}