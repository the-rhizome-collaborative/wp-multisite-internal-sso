<?php
/**
 * WP Multisite Internal SSO SSO Handling Class
 *
 * @package WP_Multisite_Internal_SSO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WP_Multisite_Internal_SSO_SSO {

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
     * Handle login redirection based on user roles.
     *
     * @param string $redirect_to The redirect destination URL.
     * @param string $request     The requested redirect destination URL passed as a parameter.
     * @param WP_User $user        WP_User object.
     * @return string Redirect URL.
     */
    public function wpmis_sso_login_redirect( $redirect_to, $request, $user ) {
        // Is there a user to check?
        if ( isset( $user->roles ) && is_array( $user->roles ) ) {
            // Check for administrators
            if ( in_array( 'administrator', $user->roles, true ) ) {
                // Redirect them to the admin dashboard
                return admin_url();
            } else {
                return home_url();
            }
        } else {
            return $redirect_to;
        }
    }

    /**
     * Check and handle SSO logic on 'template_redirect' hook.
     */
    public function check_sso() {
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            $this->utils->debug_message( __( 'Skipping SSO logic for admin and AJAX requests.', 'wp-multisite-internal-sso' ) );
            return;
        }

        $current_host = $this->get_current_site_url();

        if ( $current_host === $this->settings->get_primary_site() ) {
            $this->utils->debug_message( __( 'Running SSO logic for primary site.', 'wp-multisite-internal-sso' ) );
            $this->handle_primary_site_logic();
        } elseif ( in_array( $current_host, $this->settings->get_secondary_sites(), true ) ) {
            $this->utils->debug_message( __( 'Running SSO logic for secondary site.', 'wp-multisite-internal-sso' ) );
            $this->handle_secondary_site_logic();
        } else {
            $this->utils->debug_message( __( 'No SSO logic for current host.', 'wp-multisite-internal-sso' ) . ' ' . $current_host . ' ' . $this->settings->get_primary_site() );
        }
    }

    /**
     * Handle primary site SSO logic.
     */
    private function handle_primary_site_logic() {
        if ( isset( $_GET['wpmssso_redirect'] ) && '1' === $_GET['wpmssso_redirect'] ) {
            if ( is_user_logged_in() ) {
                $current_user = wp_get_current_user();
                $user_login   = $current_user->user_login;
                $return_url   = isset( $_GET['wpmssso_return'] ) ? esc_url_raw( $_GET['wpmssso_return'] ) : '';

                if ( ! empty( $return_url ) ) {
                    $time  = time();
                    $token = $this->generate_sso_token( $user_login, $time );

                    $redirect_back = add_query_arg(
                        array(
                            'wpmssso_user'  => rawurlencode( $user_login ),
                            'wpmssso_token' => rawurlencode( $token ),
                            'wpmssso_time'  => absint( $time ),
                        ),
                        $return_url
                    );

                    $this->utils->debug_message( __( 'Sending token to secondary site for user', 'wp-multisite-internal-sso' ) . ' ' . $user_login );

                    wp_redirect( $redirect_back );
                    exit;
                }
            } else {
                $this->utils->debug_message( __( 'User not logged in on primary site. Redirecting to secondary site.', 'wp-multisite-internal-sso' ) );
                wp_redirect( $this->settings->get_secondary_sites()[0] );
                exit;
            }
        }
    }

    /**
     * Handle secondary site SSO logic.
     */
    private function handle_secondary_site_logic() {
        if ( is_user_logged_in() ) {
            $this->utils->debug_message( __( 'User already logged in on secondary site.', 'wp-multisite-internal-sso' ) );
            return;
        }

        if ( isset( $_GET['wpmssso_user'], $_GET['wpmssso_token'], $_GET['wpmssso_time'] ) ) {
            $user_login = sanitize_user( wp_unslash( $_GET['wpmssso_user'] ), true );
            $token      = sanitize_text_field( wp_unslash( $_GET['wpmssso_token'] ) );
            $time       = absint( $_GET['wpmssso_time'] );

            if ( $this->verify_sso_token( $user_login, $token, $time ) ) {
                // Log the user in
                $auth = new WP_Multisite_Internal_SSO_Auth( $this->settings, $this->utils );
                $auth->log_user_in( $user_login );

                $this->clear_redirect_cookie();
                $this->utils->debug_message( __( 'Successfully logged in user on secondary site.', 'wp-multisite-internal-sso' ) . ' ' . $user_login );
                wp_redirect( remove_query_arg( array( 'wpmssso_user', 'wpmssso_token', 'wpmssso_time' ) ) );
                exit;
            } else {
                $this->utils->debug_message( __( 'Invalid or expired token for user.', 'wp-multisite-internal-sso' ) . ' ' . $user_login );
                return;
            }
        } else {
            if ( ! isset( $_COOKIE[ $this->settings->get_redirect_cookie_name() ] ) ) {
                $this->set_redirect_cookie();
                $this->utils->debug_message( __( 'Redirecting to primary site for SSO.', 'wp-multisite-internal-sso' ) );
                $redirect_url = add_query_arg( 'wpmssso_redirect', '1', $this->settings->get_primary_site() );
                $redirect_url = add_query_arg( 'wpmssso_return', urlencode( $this->get_current_site_url() ), $redirect_url );
                wp_redirect( $redirect_url );
                exit;
            } else {
                $this->utils->debug_message( __( 'Redirect already attempted on secondary site. No further action.', 'wp-multisite-internal-sso' ) );
            }
        }
    }

    /**
     * Generate SSO token.
     *
     * @param string $user_login User login name.
     * @param int    $time       Timestamp.
     * @return string Generated token.
     */
    private function generate_sso_token( $user_login, $time ) {
        $data = $user_login . '|' . $time . '|' . AUTH_SALT;
        $hash = wp_hash( $data, 'auth' );
        $this->utils->debug_message( __( 'Generating SSO token.', 'wp-multisite-internal-sso' ) );
        return $hash;
    }

    /**
     * Verify SSO token.
     *
     * @param string $user_login User login name.
     * @param string $token      Token to verify.
     * @param int    $time       Timestamp.
     * @return bool True if valid, false otherwise.
     */
    private function verify_sso_token( $user_login, $token, $time ) {
        if ( ( time() - $time ) > $this->settings->get_token_expiration() ) {
            return false;
        }
        $expected = $this->generate_sso_token( $user_login, $time );
        $this->utils->debug_message( __( 'Verifying SSO token.', 'wp-multisite-internal-sso' ) );
        return hash_equals( $expected, $token );
    }

    /**
     * Set redirect cookie.
     */
    private function set_redirect_cookie() {
        setcookie( $this->settings->get_redirect_cookie_name(), '1', time() + 300, COOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), false );
        $this->utils->debug_message( __( 'Redirect cookie set.', 'wp-multisite-internal-sso' ) );
    }

    /**
     * Clear redirect cookie.
     */
    private function clear_redirect_cookie() {
        setcookie( $this->settings->get_redirect_cookie_name(), '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), false );
        $this->utils->debug_message( __( 'Redirect cookie cleared.', 'wp-multisite-internal-sso' ) );
    }

    /**
     * Get the current site URL.
     *
     * @return string Current site URL.
     */
    private function get_current_site_url() {
        return trailingslashit( home_url() );
    }
}