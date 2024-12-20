<?php
/**
 * WP Multisite Internal SSO Authentication Class
 *
 * @package WP_Multisite_Internal_SSO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WP_Multisite_Internal_SSO_Auth {

    /**
     * Settings Manager.
     *
     * @var WP_Multisite_Internal_SSO_Settings
     */
    private $settings;

    /**
     * Utility Functions.
     *
     * @var WP_Multisite_Internal_SSO_Utils
     */
    private $utils;

    /**
     * Constructor.
     *
     * @param WP_Multisite_Internal_SSO_Settings $settings Settings manager instance.
     * @param WP_Multisite_Internal_SSO_Utils    $utils    Utility functions instance.
     */
    public function __construct( $settings, $utils ) {
        $this->settings = $settings;
        $this->utils    = $utils;
    }

    /**
     * Handle nonce verification and actions.
     */
    public function handle_actions() {
        if ( isset( $_GET['_wpnonce'] ) ) {
            if ( isset( $_GET['forcelogout'] ) && 'true' === $_GET['forcelogout'] ) {
                if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'wpmis_sso_logout' ) ) {
                    wp_die( __( 'Nonce verification failed.', 'wp-multisite-internal-sso' ) );
                }
                $this->logout_user();
            }

            if ( isset( $_GET['clear_cookies'] ) && 'true' === $_GET['clear_cookies'] ) {
                if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'wpmis_sso_clear_cookies' ) ) {
                    wp_die( __( 'Nonce verification failed.', 'wp-multisite-internal-sso' ) );
                }
                $this->clear_auth_cookies();
            }
        }
    }

    /**
     * Log user in based on username.
     *
     * @param string $user_login User login name.
     */
    public function log_user_in( $user_login ) {
        $user = get_user_by( 'login', $user_login );
        if ( $user && $user->exists() ) {
            wp_set_auth_cookie( $user->ID, false, is_ssl() );
            wp_set_current_user( $user->ID );

            if ( is_user_logged_in() ) {
                $this->utils->debug_message( __( 'User logged in successfully on secondary site.', 'wp-multisite-internal-sso' ) . ' ' . $user_login );
            } else {
                $this->utils->debug_message( __( 'Login failed for user.', 'wp-multisite-internal-sso' ) . ' ' . $user_login );
            }
        } else {
            $this->utils->debug_message( __( 'User not found on secondary site.', 'wp-multisite-internal-sso' ) . ' ' . $user_login );
        }
    }

    /**
     * Logout user from all sites.
     */
    private function logout_user() {
        $this->utils->debug_message( __( 'Logging out user from all sites.', 'wp-multisite-internal-sso' ) );

        if ( is_user_logged_in() ) {
            global $wpdb;

            $user_id = get_current_user_id();

            $blogs = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );

            if ( $blogs ) {
                foreach ( $blogs as $blog_id ) {
                    switch_to_blog( $blog_id );
                    $this->clear_auth_cookies();
                    restore_current_blog();
                }
            }

            wp_logout();
            $this-utils->wpmis_wp_redirect(home_url());
            exit;
        }
    }

    /**
     * Clear authentication cookies.
     */
    private function clear_auth_cookies() {
        $this->utils->debug_message( __( 'Clearing authentication cookies.', 'wp-multisite-internal-sso' ) );

        wp_clear_auth_cookie();

        setcookie( LOGGED_IN_COOKIE, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), true );
        setcookie( LOGGED_IN_COOKIE, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), true );
        setcookie( AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), true );
        setcookie( AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), true );
        setcookie( SECURE_AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), true );
        setcookie( SECURE_AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), true );
        setcookie( 'wordpress_logged_in_' . COOKIEHASH, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), true );
        setcookie( 'wordpress_logged_in_' . COOKIEHASH, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), true );
        setcookie( $this->settings->get_redirect_cookie_name(), '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), false );

        $this->utils->debug_message( __( 'Authentication cookies cleared.', 'wp-multisite-internal-sso' ) );

        if ( $user_id = get_current_user_id() ) {
            $this->utils->debug_message( __( 'Destroying session for user.', 'wp-multisite-internal-sso' ) . ' ' . $user_id );
            $session_manager = WP_Session_Tokens::get_instance( $user_id );
            $session_manager->destroy_all();
        }

        if ( function_exists( 'delete_user_meta' ) && $user_id ) {
            $this->utils->debug_message( __( 'Deleting user meta for user.', 'wp-multisite-internal-sso' ) . ' ' . $user_id );
            delete_user_meta( $user_id, 'session_tokens' );
        }

        if ( isset( $_GET['source'] ) && $this->utils->is_valid_site_url( $_GET['source'], $this->settings->get_secondary_sites() ) ) {
            $this->utils->debug_message( __( 'Redirecting to source site.', 'wp-multisite-internal-sso' ) . ' ' . esc_url_raw( $_GET['source'] ) );
            $this->utils->wpmis_wp_redirect( esc_url_raw( $_GET['source'] ) );
            exit;
        } else {
            $this->utils->debug_message( __( 'Redirecting to home URL.', 'wp-multisite-internal-sso' ) );
            $this->utils->wpmis_wp_redirect( home_url() );
            exit;
        }
    }
}