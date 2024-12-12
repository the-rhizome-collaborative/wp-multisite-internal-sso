<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Multisite_Internal_SSO {

    private $primary_site   = 'multisite.lndo.site';
    private $secondary_site = 'bar.site';
    private $redirect_cookie_name = 'wpmssso_redirect_attempt';

    public function __construct() {
        add_action( 'init', [ $this, 'init_action' ] );
        add_shortcode( 'wpmssso_sites', [ $this, 'display_sites' ] );
        add_action( 'template_redirect', [ $this, 'check_sso' ] );
    }

    public function init_action() {
        if ( WP_DEBUG && WP_DEBUG_LOG ) {
            error_log( 'WP Multisite Internal SSO init action triggered.' );
        }
    }

    public function display_sites() {
        $output  = '<div class="wpmssso-sites">';
        $output .= '<p>Primary Site: ' . esc_html( $this->primary_site ) . '</p>';
        $output .= '<p>Secondary Site: ' . esc_html( $this->secondary_site ) . '</p>';
        $output .= '</div>';

        if ( WP_DEBUG && WP_DEBUG_LOG ) {
            error_log( 'WP_Multisite_Internal_SSO: Displaying site names on front end.' );
        }

        return $output;
    }

    public function check_sso() {
        $current_host = $_SERVER['HTTP_HOST'];
        if ( $current_host === $this->secondary_site ) {
            if ( WP_DEBUG && WP_DEBUG_LOG ) {
                error_log( 'WP_Multisite_Internal_SSO: Detected secondary site.' );
            }
            $this->handle_secondary_site_logic();
        } elseif ( $current_host === $this->primary_site ) {
            if ( WP_DEBUG && WP_DEBUG_LOG ) {
                error_log( 'WP_Multisite_Internal_SSO: Detected primary site.' );
            }
            $this->handle_primary_site_logic();
        }
    }

    private function handle_secondary_site_logic() {
        if ( is_user_logged_in() ) {
            if ( WP_DEBUG && WP_DEBUG_LOG ) {
                error_log( 'WP_Multisite_Internal_SSO: User already logged in on secondary site.' );
            }
            return;
        }

        if ( isset( $_GET['wpmssso_user'] ) && isset( $_GET['wpmssso_token'] ) && isset( $_GET['wpmssso_time'] ) ) {
            $user  = sanitize_user( $_GET['wpmssso_user'] );
            $token = sanitize_text_field( $_GET['wpmssso_token'] );
            $time  = absint( $_GET['wpmssso_time'] );

            if ( $this->verify_sso_token( $user, $token, $time ) ) {
                $this->log_user_in( $user );

                if ( isset( $_COOKIE[ $this->redirect_cookie_name ] ) ) {
                    setcookie( $this->redirect_cookie_name, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
                }

                if ( WP_DEBUG && WP_DEBUG_LOG ) {
                    error_log( 'WP_Multisite_Internal_SSO: Successfully logged in user ' . $user . ' via SSO on secondary site.' );
                }

                // Remove query args and redirect.
                wp_redirect( remove_query_arg( [ 'wpmssso_user', 'wpmssso_token', 'wpmssso_time' ] ) );
                exit;
            } else {
                if ( WP_DEBUG && WP_DEBUG_LOG ) {
                    error_log( 'WP_Multisite_Internal_SSO: Invalid token or expired token for user ' . $user );
                }
                return;
            }
        } else {
            if ( ! isset( $_COOKIE[ $this->redirect_cookie_name ] ) ) {
                setcookie( $this->redirect_cookie_name, '1', time() + 300, COOKIEPATH, COOKIE_DOMAIN );

                if ( WP_DEBUG && WP_DEBUG_LOG ) {
                    error_log( 'WP_Multisite_Internal_SSO: Not logged in on secondary, redirecting to primary for SSO.' );
                }

                $redirect_url = 'https://' . $this->primary_site . add_query_arg( 'wpmssso_redirect', 1, '/' );
                $redirect_url = add_query_arg( 'wpmssso_return', urlencode( 'https://' . $this->secondary_site . '/' ), $redirect_url );

                wp_redirect( $redirect_url );
                exit;
            } else {
                if ( WP_DEBUG && WP_DEBUG_LOG ) {
                    error_log( 'WP_Multisite_Internal_SSO: Already attempted redirect on secondary site. No further action.' );
                }
            }
        }
    }

    private function handle_primary_site_logic() {
        if ( isset( $_GET['wpmssso_redirect'] ) && $_GET['wpmssso_redirect'] == 1 ) {
            if ( is_user_logged_in() ) {
                $current_user = wp_get_current_user();
                if ( $current_user && $current_user->exists() ) {
                    $user_login = $current_user->user_login;
                    $return_url = isset( $_GET['wpmssso_return'] ) ? esc_url_raw( $_GET['wpmssso_return'] ) : '';

                    if ( ! empty( $return_url ) ) {
                        // Create a custom token using a shared secret (AUTH_SALT) and timestamp.
                        $time  = time(); // current timestamp
                        $token = $this->generate_sso_token( $user_login, $time );

                        $redirect_back = add_query_arg(
                            [ 
                                'wpmssso_user'  => $user_login,
                                'wpmssso_token' => $token,
                                'wpmssso_time'  => $time
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
                if ( WP_DEBUG && WP_DEBUG_LOG ) {
                    error_log( 'WP_Multisite_Internal_SSO: User not logged in on primary. Cannot proceed with SSO.' );
                }
            }
        }
    }

    /**
     * Generate a stable token using wp_hash and a shared secret.
     * We incorporate username and a timestamp. The secondary site will verify the same way.
     */
    private function generate_sso_token( $user_login, $time ) {
        // We assume both sites share AUTH_SALT (they should in a multisite).
        $data = $user_login . '|' . $time . '|' . AUTH_SALT;
        return wp_hash( $data, 'auth' );
    }

    /**
     * Verify the SSO token on the secondary site.
     * Checks if token matches what we'd generate and ensures it's not too old (for security).
     * Let's say it must be within 5 minutes (300 seconds).
     */
    private function verify_sso_token( $user_login, $token, $time ) {
        // Check age of the token to avoid replay attacks.
        if ( ( time() - $time ) > 300 ) { // 5 minutes expiry
            return false;
        }

        $expected = $this->generate_sso_token( $user_login, $time );
        return hash_equals( $expected, $token );
    }

    private function log_user_in( $user_login ) {
        $user = get_user_by( 'login', $user_login );
        if ( $user && $user->exists() ) {
            wp_set_auth_cookie( $user->ID, false );
            wp_set_current_user( $user->ID );
        } else {
            if ( WP_DEBUG && WP_DEBUG_LOG ) {
                error_log( 'WP_Multisite_Internal_SSO: Could not find user ' . $user_login . ' on secondary site.' );
            }
        }
    }

}