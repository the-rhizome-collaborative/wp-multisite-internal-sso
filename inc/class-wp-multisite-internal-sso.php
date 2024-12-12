<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class for WP Multisite Internal SSO.
 */
class WP_Multisite_Internal_SSO {

    /**
     * Sites we plan to handle.
     * For now, hard-code them. Later, these will be configurable.
     */
    private $primary_site   = 'multisite.lndo.site';
    private $secondary_site = 'bar.site';

    /**
     * Cookie name to detect if we've already attempted an SSO redirect.
     */
    private $redirect_cookie_name = 'wpmssso_redirect_attempt';

    /**
     * Constructor.
     */
    public function __construct() {
        // Basic init action for debugging.
        add_action( 'init', [ $this, 'init_action' ] );

        // Add shortcode for testing.
        add_shortcode( 'wpmssso_sites', [ $this, 'display_sites' ] );

        // SSO logic hookup: run late enough so that WP is mostly loaded.
        add_action( 'template_redirect', [ $this, 'check_sso' ] );
    }

    /**
     * Runs on init to log that the plugin is active.
     */
    public function init_action() {
        if ( WP_DEBUG && WP_DEBUG_LOG ) {
            error_log( 'WP Multisite Internal SSO init action triggered.' );
        }
    }

    /**
     * Shortcode callback to display the two site names.
     * This will help us confirm on the front end that the plugin is active.
     *
     * Usage: [wpmssso_sites]
     */
    public function display_sites() {
        $output  = '<div class="wpmssso-sites">';
        $output .= '<p>Primary Site: ' . esc_html( $this->primary_site ) . '</p>';
        $output .= '<p>Secondary Site: ' . esc_html( $this->secondary_site ) . '</p>';
        $output .= '</div>';

        // Log to debug log for confirmation.
        if ( WP_DEBUG && WP_DEBUG_LOG ) {
            error_log( 'WP_Multisite_Internal_SSO: Displaying site names on front end.' );
        }

        return $output;
    }

    /**
     * Main SSO check logic.
     * This will:
     * - On secondary site: If user not logged in, attempt to redirect to primary to get SSO token.
     * - On primary site: If requested and user logged in, send user+token back to secondary.
     * - On secondary site (on return): Verify token and log the user in.
     */
    public function check_sso() {
        // Determine the current host.
        $current_host = $_SERVER['HTTP_HOST'];

        // Check if we are on the secondary site or the primary site.
        // Adjust logic if you have different conditions. For now, we rely on $this->primary_site and $this->secondary_site.
        if ( $current_host === $this->secondary_site ) {
            $this->handle_secondary_site_logic();
        } elseif ( $current_host === $this->primary_site ) {
            $this->handle_primary_site_logic();
        }
    }

    /**
     * Logic that runs on the secondary site (bar.site).
     * If the user is not logged in:
     *  - If we have ?wpmssso_user and ?wpmssso_token, verify and log them in.
     *  - Else, if no redirect attempt, set cookie and redirect to primary site.
     */
    private function handle_secondary_site_logic() {
        if ( is_user_logged_in() ) {
            // Already logged in, do nothing.
            return;
        }

        // Check if we got a return from the primary site with user and token.
        if ( isset( $_GET['wpmssso_user'] ) && isset( $_GET['wpmssso_token'] ) ) {
            $user  = sanitize_user( $_GET['wpmssso_user'] );
            $token = sanitize_text_field( $_GET['wpmssso_token'] );

            if ( $this->verify_sso_token( $user, $token ) ) {
                // Valid token, log the user in.
                $this->log_user_in( $user );

                // Clear the redirect cookie if present.
                if ( isset( $_COOKIE[ $this->redirect_cookie_name ] ) ) {
                    setcookie( $this->redirect_cookie_name, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
                }

                if ( WP_DEBUG && WP_DEBUG_LOG ) {
                    error_log( 'WP_Multisite_Internal_SSO: Successfully logged in user ' . $user . ' via SSO on secondary site.' );
                }

                // Redirect to a clean URL without the query params.
                wp_redirect( remove_query_arg( [ 'wpmssso_user', 'wpmssso_token' ] ) );
                exit;
            } else {
                if ( WP_DEBUG && WP_DEBUG_LOG ) {
                    error_log( 'WP_Multisite_Internal_SSO: Invalid token for user ' . $user );
                }
                // Invalid token. You could optionally redirect or show an error message.
                return;
            }
        } else {
            // No user/token in the query, attempt to initiate SSO if no previous redirect attempt.
            if ( ! isset( $_COOKIE[ $this->redirect_cookie_name ] ) ) {
                // Set a short-lived cookie to prevent infinite redirects.
                setcookie( $this->redirect_cookie_name, '1', time() + 300, COOKIEPATH, COOKIE_DOMAIN ); // 5 min
                if ( WP_DEBUG && WP_DEBUG_LOG ) {
                    error_log( 'WP_Multisite_Internal_SSO: Not logged in on secondary, redirecting to primary for SSO.' );
                }

                // Redirect to primary site with a param indicating we want SSO.
                $redirect_url = 'https://' . $this->primary_site . add_query_arg( 'wpmssso_redirect', 1, '/' );
                // Include a return URL param so primary site knows where to send back.
                $redirect_url = add_query_arg( 'wpmssso_return', 'https://' . $this->secondary_site . '/', $redirect_url );

                wp_redirect( $redirect_url );
                exit;
            } else {
                // Already tried redirecting once. Avoid looping.
                if ( WP_DEBUG && WP_DEBUG_LOG ) {
                    error_log( 'WP_Multisite_Internal_SSO: Already attempted redirect on secondary site. No further action.' );
                }
            }
        }
    }

    /**
     * Logic that runs on the primary site (multisite.lndo.site).
     * If we get ?wpmssso_redirect=1 and the user is logged in, generate token and redirect back.
     */
    private function handle_primary_site_logic() {
        if ( isset( $_GET['wpmssso_redirect'] ) && $_GET['wpmssso_redirect'] == 1 ) {
            // We are here because secondary site asked for SSO.
            if ( is_user_logged_in() ) {
                // User is logged in on primary, generate token and send them back.
                $current_user = wp_get_current_user();
                if ( $current_user && $current_user->exists() ) {
                    $user_login = $current_user->user_login;

                    // Verify we have a return URL from secondary site.
                    $return_url = isset( $_GET['wpmssso_return'] ) ? esc_url_raw( $_GET['wpmssso_return'] ) : '';

                    if ( ! empty( $return_url ) ) {
                        // Create a nonce as the token.
                        $token = wp_create_nonce( 'wpmssso_sso_' . $user_login );
                        $redirect_back = add_query_arg(
                            [ 
                                'wpmssso_user'  => $user_login,
                                'wpmssso_token' => $token
                            ],
                            $return_url
                        );

                        if ( WP_DEBUG && WP_DEBUG_LOG ) {
                            error_log( 'WP_Multisite_Internal_SSO: User ' . $user_login . ' logged in on primary, sending token back to secondary.' );
                        }

                        wp_redirect( $redirect_back );
                        exit;
                    } else {
                        if ( WP_DEBUG && WP_DEBUG_LOG ) {
                            error_log( 'WP_Multisite_Internal_SSO: No return URL provided by secondary site.' );
                        }
                    }
                }
            } else {
                // Not logged in on primary. The user should log in here and then refresh to get redirected back.
                // For now, do nothing. In a real scenario, you might redirect to login page or show a prompt.
                if ( WP_DEBUG && WP_DEBUG_LOG ) {
                    error_log( 'WP_Multisite_Internal_SSO: User not logged in on primary. Cannot proceed with SSO.' );
                }
            }
        }
    }

    /**
     * Verify the SSO token (nonce).
     *
     * @param string $user_login The username received.
     * @param string $token The token (nonce) to verify.
     * @return bool True if valid, false otherwise.
     */
    private function verify_sso_token( $user_login, $token ) {
        return wp_verify_nonce( $token, 'wpmssso_sso_' . $user_login ) !== false;
    }

    /**
     * Log a user in by username.
     *
     * @param string $user_login The username of the user to log in.
     */
    private function log_user_in( $user_login ) {
        $user = get_user_by( 'login', $user_login );
        if ( $user && $user->exists() ) {
            wp_set_auth_cookie( $user->ID, false ); // Set auth cookie, not "remember me" by default
            wp_set_current_user( $user->ID );       // Set current user
        } else {
            if ( WP_DEBUG && WP_DEBUG_LOG ) {
                error_log( 'WP_Multisite_Internal_SSO: Could not find user ' . $user_login . ' on secondary site.' );
            }
        }
    }
}