<?php
/**
 * Plugin Name: WP Multisite Internal SSO
 * Plugin URI:  https://example.com
 * Description: Enables automatic login (SSO) for users from one multisite installation to another.
 * Version:     0.0.6
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
// define('WPMSSSOMS_SSO_PLUGIN_URL', plugin_dir_url( __FILE__ ));

// Include the main class file.
require_once WPMSSSOMS_SSO_PLUGIN_DIR . 'inc/class-wp-multisite-internal-sso.php';

// Initialize the plugin class on plugins_loaded to ensure all WordPress functions are available.
add_action('plugins_loaded', 'my_ms_sso_plugin_init');

function my_ms_sso_plugin_init() {
    // Instantiate the main plugin class.
    $GLOBALS['wp_multisite_internal_sso'] = new WP_Multisite_Internal_SSO();

    // Add a debug log entry.
    // if ( WP_DEBUG && WP_DEBUG_LOG ) {
    //     error_log( 'WP Multisite Internal SSO plugin initialized.' );
    // }
}

add_action( 'wp_body_open', 'display_logged_in_status' );

function display_logged_in_status() {
    if ( is_user_logged_in() ) {
        echo '<div style="position: relative; top: 0; right: 0; background: #000; color: #fff; padding: 10px;">Logged in</div>';
    } else {
        echo '<div style="position: relative; top: 0; right: 0; background: #000; color: #fff; padding: 10px;">Not logged in</div>';
    }
}

// function logoutUser(){
//     if ( isset($_GET["forcelogout"]) && $_GET["forcelogout"] == 'true' ) {
//         // // set forcelogout cookie
//         // setcookie('forcelogout', 'true', time() + 3600, COOKIEPATH, COOKIE_DOMAIN);
//         // wp_logout();
//         // header("refresh:0.5;url=".$_SERVER['REQUEST_URI']."");
//     }
// }
// add_action('init', 'logoutUser');

// add_action('wp_body_open', 'display_logout_button');

// function display_logout_button() {
//     if ( is_user_logged_in() ) {
//         echo '<div style="position: relative; top: 0; right: 0; background: #000; color: #fff; padding: 10px;"><a href="https://' . $this->primary_site . '/?forcelogout=true&source=' . $this->secondary_site.'">Logout</a></div>';
//     }
// }


// // add button to header for logged in users which logs the user out of all sites
// add_action( 'wp_body_open', 'display_logout_button' );

// function display_logout_button() {
//     if ( is_user_logged_in() ) {
//         // Generate a custom logout URL that redirects to the current page after logging out
//         $logout_url = add_query_arg( 'action', 'logout_multisite', home_url() );
//         echo '<div style="position: relative; top: 0; left: 0; background: #000; color: #fff; padding: 10px;"><a href="' . esc_url( $logout_url ) . '">Logout</a></div>';
//     }
// }

// add_action( 'init', 'handle_multisite_logout' );

// function handle_multisite_logout() {
//     if ( isset( $_GET['action'] ) && $_GET['action'] === 'logout_multisite' ) {
//         if ( is_user_logged_in() ) {
//             // Log out the user from all sites in the multisite
//             global $wpdb;

//             $user_id = get_current_user_id();

//             // Get all blogs/sites in the network
//             $blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs}", ARRAY_A );

//             if ( $blogs ) {
//                 foreach ( $blogs as $blog ) {
//                     $blog_id = $blog['blog_id'];

//                     // Switch to each blog and clear the session for that site
//                     switch_to_blog( $blog_id );
//                     wp_logout();
//                     restore_current_blog();
//                 }
//             }

//             // Redirect to the home URL after logout
//             wp_redirect( home_url() );
//             exit;
//         }
//     }
// }

// // when a user logs in, redirect them to the home page
// add_filter( 'login_redirect', 'redirect_after_login', 10, 3 );

// function redirect_after_login( $redirect_to, $request, $user ) {
//     return home_url();
// }


// // on logout clear the wpmssso_redirect_attempt cookie
// add_action( 'wp_logout', 'clear_redirect_cookie' );

// function clear_redirect_cookie() {
//     // if ( isset( $_COOKIE['wpmssso_redirect_attempt'] ) ) {
//         setcookie( 'wpmssso_redirect_attempt', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
//     // }
// }
