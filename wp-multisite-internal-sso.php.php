<?php
/**
 * Plugin Name: WP Multisite Internal SSO
 * Plugin URI:  https://github.com/9ete/wp-multisite-internal-sso
 * Description: Enables automatic login (SSO) for users from one multisite installation to another.
 * Version:     0.1.5
 * Author:      9ete
 * Author URI:  https://petelower.com
 * Network:     true
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// TODO: add ability to set redirect url after logout and login
// TODO: on secondary site login, first check if the user exists on the primary site, if so, redirect them to that login page and pass the credentials (auto log them in)
// TODO: move config from individual site settings to multisite settings menu
// TODO: add toggle for dev debugging

define( 'WPMIS_SSO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPMIS_SSO_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPMIS_SSO_PLUGIN_VERSION',  get_file_data(__FILE__, array('Version' => 'Version'))['Version'] );

require_once WPMIS_SSO_PLUGIN_PATH . 'includes/class-wp-multisite-internal-sso.php';

function wpmis_sso_init() {
    $GLOBALS['wp_multisite_internal_sso'] = new WP_Multisite_Internal_SSO();
}

if ( is_multisite() && ! isset( $_GET['wpmisso_ignore'] ) ) {
    add_action( 'plugins_loaded', 'wpmis_sso_init' );
}