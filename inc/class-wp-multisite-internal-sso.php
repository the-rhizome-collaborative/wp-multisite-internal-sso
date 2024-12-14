<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Multisite_Internal_SSO {

    private $primary_site;
    private $secondary_site;
    private $redirect_cookie_name;
    private $server_protocol;

    public function __construct() {
        $this->primary_site = defined('WPMIS_PRIMARY') ? WPMIS_PRIMARY : get_site_url(1);
        $this->secondary_site = defined('WPMIS_SECONDARY') ? WPMIS_SECONDARY : get_site_url(2);
        $this->redirect_cookie_name = defined('WPMIS_COOKIE_NAME') ? WPMIS_COOKIE_NAME : 'wpmssso_redirect_attempt';
        $this->server_protocol = isset( $_SERVER['HTTPS'] ) ? 'https://' : 'http://';

        add_action( 'init', [ $this, 'init_action' ], 1 );
        add_action( 'template_redirect', [ $this, 'check_sso' ] );
        add_action('wp_body_open', [ $this, 'display_logout_button']);
    }

    private function debug_message($message) {
        if ( WP_DEBUG && WP_DEBUG_LOG ) {
            error_log( 'WPMSSSO: ' . $message );
        }
    }

    public function init_action() {
        $this->debug_message( 'Init action triggered.' );
        $this->debug_message( 'Primary site: ' . $this->primary_site . ' ' );
        $this->debug_message( 'Secondary site: ' . $this->secondary_site . ' ' );

        if ( isset($_GET["forcelogout"]) && $_GET["forcelogout"] == 'true' ) {

            if ( ! is_user_logged_in() ) {
                $this->debug_message( 'User not logged in. Cannot log them out.' );
            } else {
                $this->logoutUser();
            }

            // if source is set, redirect to source
            if ( isset( $_GET['source'] ) ) {
                $this->debug_message( 'Redirecting to ' . $_GET['source'] );
                $redirect_url = add_query_arg( 'forcelogout', 'true', esc_url_raw( $_GET['source'] ) );
                wp_redirect( $redirect_url );
                exit;
            }
        }
    }

    public function check_sso() {
        $this->debug_message( 'Checking SSO.' );
        if ( is_admin() || ( defined('DOING_AJAX') && DOING_AJAX ) ) {
            $this->debug_message( 'Skipping SSO logic for admin and AJAX requests.' );
            return;
        }

        // if ( $this->user_exists_on_primary_site() ) {
        //     $this->debug_message( 'User exists on primary site. ' . get_site_url() );
        // } else {
        //     $this->debug_message( 'User does not exist on primary site. Looking at site: ' . get_site_url() );
        //     return;
        // }

        $current_host = $this->server_protocol . $_SERVER['HTTP_HOST'];

        if ( $current_host === $this->secondary_site ) {
            $this->debug_message( 'Running SSO logic for secondary site.' . $current_host );
            $this->handle_secondary_site_logic();
        } elseif ( $current_host === $this->primary_site ) {
            $this->debug_message( 'Running SSO logic for primary site.' . $current_host );
            $this->handle_primary_site_logic();
        } else {
            $this->debug_message( 'No SSO logic for current host. ' . $current_host );
        }
    }
    
    private function user_exists_on_primary_site() {
        if ( function_exists( 'get_current_user_id' ) && function_exists( 'get_user_by' ) ) {
            $user_id = get_current_user_id();
            $user = get_user_by('id', $user_id);
            if ( $user ) {
                $user_name = $user->user_login;
            } else {
                $this->debug_message( 'User not found. ' . $user_id . ' on ' . get_site_url() );
                return false;
            }
        } else {
            $this->debug_message( 'Required functions are not available.' );
            return false;
        }

        $user = get_user_by('login', $user_name) ?? 'UserNotFound';
        
        if ( $user ) {
            $this->debug_message( 'User ' . $user_name . ' exists in blog 1.' );
            return true;
        } else {
            $this->debug_message( 'User ' . $user_name . ' does not exist in blog 1.' );
            return false;
        }
    }

    public function logoutUser() {
        $this->debug_message( 'Logging out user from all sites. PARAMS/?' . $this->primary_site . '/?forcelogout=true&source=' . $this->secondary_site );
        if ( get_site_url() === $this->secondary_site ) {
            wp_redirect( $this->primary_site . '/?forcelogout=true&source=' . $this->secondary_site );
        }
        $this->clear_auth_cookies();
        wp_logout();
        wp_redirect( home_url() );
        exit;
    }

    public function display_logout_button() {
        if ( is_user_logged_in() ) {
            
            if ( get_site_url() === $this->primary_site ) {
                echo '<div style="position: relative; top: 0; right: 0; background: #000; color: #fff; padding: 10px;"><a href="' . $this->secondary_site . '/?forcelogout=true&source=' . $this->primary_site . '">Logout</a></div>';
            } else {
                echo '<div style="position: relative; top: 0; right: 0; background: #000; color: #fff; padding: 10px;"><a href="' . $this->primary_site . '/?forcelogout=true&source=' . $this->secondary_site . '">Logout</a></div>';
            }
            
            
            
            echo '<div style="position: relative; top: 0; right: 0; background: #000; color: #fff; padding: 10px;"><a href="' . $this->server_protocol . $_SERVER['HTTP_HOST'] . '/?clear_cookies=true' .'">Clear Cookies</a></div>';
        }
    }

    public function clear_auth_cookies() {
        $this->debug_message('Clearing authentication cookies.' );
        wp_clear_auth_cookie();

        setcookie( LOGGED_IN_COOKIE, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
        setcookie( LOGGED_IN_COOKIE, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );
        setcookie( AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
        setcookie( AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );
        setcookie( SECURE_AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
        setcookie( SECURE_AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );
        setcookie( 'wordpress_logged_in_' . COOKIEHASH, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
        setcookie( 'wordpress_logged_in_' . COOKIEHASH, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );
        setcookie( $this->redirect_cookie_name, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );


        $this->debug_message('Cleared authentication cookies.' );

        $user_id = get_current_user_id();
        if ( $user_id ) {
            $this->debug_message( 'Destroying session for user ' . $user_id );
            $session_manager = WP_Session_Tokens::get_instance( $user_id );
            $session_manager->destroy_all();
        }

        if ( function_exists( 'delete_user_meta' ) ) {
            $this->debug_message( 'Deleting user meta for user ' . $user_id );
            delete_user_meta( $user_id, 'session_tokens' );
        }

        if ( isset( $_GET['source'] ) ) {
            $this->debug_message( 'Redirecting to ' . $_GET['source'] );
            wp_redirect( esc_url_raw( $_GET['source'] ) );
            exit;
        } else {
            $this->debug_message( 'Redirecting to home URL.' );
            wp_redirect( home_url() );
            exit;
        }
    }

    private function handle_secondary_site_logic() {
        if ( is_user_logged_in() ) {
            $this->debug_message( 'User already logged in on secondary site.' );
            return;
        }

        if ( isset( $_GET['wpmssso_user'], $_GET['wpmssso_token'], $_GET['wpmssso_time'] ) ) {
            $user  = sanitize_user( $_GET['wpmssso_user'] );
            $token = sanitize_text_field( $_GET['wpmssso_token'] );
            $time  = absint( $_GET['wpmssso_time'] );

            if ( $this->verify_sso_token( $user, $token, $time ) ) {
                $this->log_user_in( $user );
                setcookie( $this->redirect_cookie_name, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
                $this->debug_message( 'Successfully logged in user ' . $user . ' on secondary site.' );
                wp_redirect( remove_query_arg( [ 'wpmssso_user', 'wpmssso_token', 'wpmssso_time' ] ) );
                exit;
            } else {
                $this->debug_message( 'Invalid or expired token for user ' . $user );
                return;
            }
        } else {
            if ( ! isset( $_COOKIE[ $this->redirect_cookie_name ] ) ) {
                setcookie( $this->redirect_cookie_name, '1', time() + 300, COOKIEPATH, COOKIE_DOMAIN );
                $this->debug_message( 'Redirecting to primary site for SSO.' );
                $redirect_url = $this->primary_site . add_query_arg( 'wpmssso_redirect', 1, '/' );
                $redirect_url = add_query_arg( 'wpmssso_return', urlencode( $this->secondary_site . '/' ), $redirect_url );
                wp_redirect( $redirect_url );
                exit;
            } else {
                $this->debug_message( 'Redirect already attempted on secondary site. No further action.' );
            }
        }
    }

    private function handle_primary_site_logic() {

        if ( isset($_GET["forcelogout"]) && $_GET["forcelogout"] == 'true' ) {

                if ( ! is_user_logged_in() ) {
                    $this->debug_message( 'User not logged in on primary site. Cannot log them out.' );
                    if ( isset( $_GET['source'] ) ) {
                        $this->debug_message( 'Redirecting to ' . $_GET['source'] );
                        wp_redirect( esc_url_raw( $_GET['source'] ) );
                        exit;
                    }
                    return;
                }

                $this->debug_message( 'Logging out user from all sites.' );

                if ( is_user_logged_in() ) {
                    
                    global $wpdb;
        
                    $user_id = get_current_user_id();

                    $blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs}", ARRAY_A );
        
                    if ( $blogs ) {
                        foreach ( $blogs as $blog ) {
                            $blog_id = $blog['blog_id'];

                            $this->debug_message( 'Logging out user from site ' . $blog_id );

                            switch_to_blog( $blog_id );
                            $this->clear_auth_cookies();
                            restore_current_blog();
                        }
                    }

                    wp_redirect( home_url() );
                    exit;
                }
            header("refresh:0.5;url=".$_SERVER['REQUEST_URI']."");
        }


        if ( isset( $_GET['wpmssso_redirect'] ) && $_GET['wpmssso_redirect'] == 1 ) {
            if ( is_user_logged_in() ) {
                $current_user = wp_get_current_user();
                if ( $current_user && $current_user->exists() ) {
                    $user_login = $current_user->user_login;
                    $return_url = isset( $_GET['wpmssso_return'] ) ? esc_url_raw( $_GET['wpmssso_return'] ) : '';

                    if ( ! empty( $return_url ) ) {
                        $time  = time();
                        $token = $this->generate_sso_token( $user_login, $time );

                        $redirect_back = add_query_arg(
                            [ 
                                'wpmssso_user'  => $user_login,
                                'wpmssso_token' => $token,
                                'wpmssso_time'  => $time
                            ],
                            $return_url
                        );

                        $this->debug_message( 'Sending token back to secondary site for user ' . $user_login );

                        wp_redirect( $redirect_back );
                        exit;
                    }
                }
            } else {
                $this->debug_message( 'User not logged in on primary site. Cannot proceed with SSO.' );
                wp_redirect( $this->secondary_site );
            }
        }
    }

    private function generate_sso_token( $user_login, $time ) {
        $data = $user_login . '|' . $time . '|' . AUTH_SALT;
        $hash = wp_hash( $data, 'auth' );
        $this->debug_message( 'time  ' . $time );
        $this->debug_message( 'data  ' . $data );
        $this->debug_message( 'hash  ' . $hash );
        return $hash;
    }

    private function verify_sso_token( $user_login, $token, $time ) {
        if ( ( time() - $time ) > 300 ) {
            return false;
        }
        $expected = $this->generate_sso_token( $user_login, $time );
        $this->debug_message( 'expected  ' . $expected );
        $this->debug_message( 'expected  ' . $time );
        $this->debug_message( 'token  ' . $token );
        return hash_equals( $expected, $token );
    }

    private function log_user_in( $user_login ) {
        $user = get_user_by( 'login', $user_login );
        if ( $user && $user->exists() ) {
            wp_set_auth_cookie( $user->ID, false );
            wp_set_current_user( $user->ID );

            if ( is_user_logged_in() ) {
                $this->debug_message( 'User ' . $user_login . ' logged in successfully on secondary site.' );
            } else {
                $this->debug_message( 'Login failed for user ' . $user_login );
            }
        } else {
            $this->debug_message( 'User ' . $user_login . ' not found on secondary site.' );
        }
    }
}