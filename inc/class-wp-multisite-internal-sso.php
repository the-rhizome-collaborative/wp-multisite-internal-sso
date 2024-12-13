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
        // add_shortcode( 'wpmssso_sites', [ $this, 'display_sites' ] );
        add_action( 'template_redirect', [ $this, 'check_sso' ] );
        // add_action('init', [ $this, 'logoutUser' ]);
        add_action('wp_body_open', [ $this, 'display_logout_button']);
    }

    private function debug_message($message) {
        if ( WP_DEBUG && WP_DEBUG_LOG ) {
            error_log( 'WPMSSSO: ' . $message );
        }
    }

    public function init_action() {
        // $this->debug_message('Init action triggered.');
    }

    // public function display_sites() {
    //     $output  = '<div class="wpmssso-sites">';
    //     $output .= '<p>Primary Site: ' . esc_html( $this->primary_site ) . '</p>';
    //     $output .= '<p>Secondary Site: ' . esc_html( $this->secondary_site ) . '</p>';
    //     $output .= '</div>';

    //     $this->debug_message( 'Displaying site names on front end.' );

    //     return $output;
    // }

    public function check_sso() {
        $this->debug_message( 'Checking SSO.' );
        if ( is_admin() || ( defined('DOING_AJAX') && DOING_AJAX ) ) {
            $this->debug_message( 'Skipping SSO logic for admin and AJAX requests.' );
            return;
        }

        // // switch to blog 1
        // if ( function_exists( 'get_current_user_id' ) && function_exists( 'get_user_by' ) ) {
        //     $user_id = get_current_user_id();
        //     $user = get_user_by('id', $user_id);
        //     if ( $user ) {
        //         $user_name = $user->user_login;
        //     } else {
        //         $this->debug_message( 'User not found.' );
        //         return;
        //     }
        // } else {
        //     $this->debug_message( 'Required functions are not available.' );
        //     return;
        // }
        // switch_to_blog(1);
        // // check if user exits in blog 1
        // $user = get_user_by('login', $user_name);
        // if ( $user ) {
        //     $this->debug_message( 'User ' . $user_name . ' exists in blog 1.' );
        // } else {
        //     $this->debug_message( 'User ' . $user_name . ' does not exist in blog 1.' );
        //     exit;
        // }

        // if ( isset( $_GET['clear_cookies'] ) && $_GET['clear_cookies'] == 'true' ) {
        //     $this->clear_auth_cookies();
        // }

        if ( $this->user_exists_on_primary_site() ) {
            $this->debug_message( 'User exists on primary site.' );
        } else {
            $this->debug_message( 'User does not exist on primary site.' );
            return;
        }

        $current_host = $_SERVER['HTTP_HOST'];

        if ( $current_host === $this->secondary_site ) {
            $this->debug_message( 'Running SSO logic for secondary site.' );
            $this->handle_secondary_site_logic();
        } elseif ( $current_host === $this->primary_site ) {
            $this->debug_message( 'Running SSO logic for primary site.' );
            $this->handle_primary_site_logic();
        }
    }
    
    private function user_exists_on_primary_site() {
        if ( function_exists( 'get_current_user_id' ) && function_exists( 'get_user_by' ) ) {
            $user_id = get_current_user_id();
            $user = get_user_by('id', $user_id);
            if ( $user ) {
                $user_name = $user->user_login;
            } else {
                $this->debug_message( 'User not found.' );
                return false;
            }
        } else {
            $this->debug_message( 'Required functions are not available.' );
            return false;
        }
        switch_to_blog(1);
        // check if user exits in blog 1
        $user = get_user_by('login', $user_name);
        if ( $user ) {
            $this->debug_message( 'User ' . $user_name . ' exists in blog 1.' );
            return true;
        } else {
            $this->debug_message( 'User ' . $user_name . ' does not exist in blog 1.' );
            return false;
        }
    }

    public function logoutUser() {

        // $redirect_back = add_query_arg(
        //     [ 
        //         'wpmssso_user'  => $user_login,
        //         'wpmssso_token' => $token,
        //         'wpmssso_time'  => $time
        //     ],
        //     $return_url
        // );

        // if ( WP_DEBUG && WP_DEBUG_LOG ) {
        //     error_log( 'Sending token back to secondary site for user ' . $user_login );
        // }

        // wp_redirect( $redirect_back );
        // exit;

        // first send a request to the primary site to logout with the params forcelogout=true and source=SiteUrl

        // // change blog to site 1
        // switch_to_blog(1);
        // // display the current site url
        // $site_name = get_site_url();

        // // switch back to the current site
        // restore_current_blog();

        // if ( $site_name === $this->secondary_site ) {
        //     return;
        // }

        wp_redirect( 'https://' . $this->primary_site . '/?forcelogout=true&source=' . $this->secondary_site );

        // then we must logout the user from the secondary site



        // if ( isset($_GET["forcelogout"]) && $_GET["forcelogout"] == 'true' ) {
        //     // // set forcelogout cookie
        //     // setcookie('forcelogout', 'true', time() + 3600, COOKIEPATH, COOKIE_DOMAIN);
        //     wp_logout();
        //     header("refresh:0.5;url=".$_SERVER['REQUEST_URI']."");
        // }
    }

    public function display_logout_button() {
        // change blog to site 1
        switch_to_blog(1);
        // display the current site url
        echo get_site_url();

        // switch back to the current site
        restore_current_blog();
        if ( is_user_logged_in() ) {
            echo '<div style="position: relative; top: 0; right: 0; background: #000; color: #fff; padding: 10px;"><a href="https://' . $this->primary_site . '/?forcelogout=true&source=' . $this->secondary_site.'">Logout</a></div>';
            echo '<div style="position: relative; top: 0; right: 0; background: #000; color: #fff; padding: 10px;"><a href="'. 'https://' . $_SERVER['HTTP_HOST'] . '/?clear_cookies=true' .'">Clear Cookies</a></div>';
        }
    }

    public function clear_auth_cookies() {
        // Clear authentication cookies
        wp_clear_auth_cookie();
    
        // Expire WordPress-specific cookies
        setcookie( LOGGED_IN_COOKIE, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
        setcookie( LOGGED_IN_COOKIE, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );
        setcookie( AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
        setcookie( AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );
        setcookie( SECURE_AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
        setcookie( SECURE_AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );
        setcookie( 'wordpress_logged_in_' . COOKIEHASH, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
        setcookie( 'wordpress_logged_in_' . COOKIEHASH, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );
        setcookie( $this->redirect_cookie_name, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );


        $this->debug_message('Clearing authentication cookies.' );
    
        // Destroy the user's session
        $user_id = get_current_user_id();
        if ( $user_id ) {
            $this->debug_message( 'Destroying session for user ' . $user_id );
            $session_manager = WP_Session_Tokens::get_instance( $user_id );
            $session_manager->destroy_all();
        }
    
        // Optionally destroy persistent login data (e.g., "Remember Me" functionality)
        if ( function_exists( 'delete_user_meta' ) ) {
            $this->debug_message( 'Deleting user meta for user ' . $user_id );
            delete_user_meta( $user_id, 'session_tokens' );
        }
    
        // Redirect after clearing cookies
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

                // Clear the redirect cookie
                setcookie( $this->redirect_cookie_name, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );

                $this->debug_message( 'Successfully logged in user ' . $user . ' on secondary site.' );

                // Remove query args and redirect.
                wp_redirect( remove_query_arg( [ 'wpmssso_user', 'wpmssso_token', 'wpmssso_time' ] ) );
                exit;
            } else {
                $this->debug_message( 'Invalid or expired token for user ' . $user );
                return;
            }
        } else {
            if ( ! isset( $_COOKIE[ $this->redirect_cookie_name ] ) ) {
                // Set a cookie to prevent infinite redirects
                setcookie( $this->redirect_cookie_name, '1', time() + 300, COOKIEPATH, COOKIE_DOMAIN );

                $this->debug_message( 'Redirecting to primary site for SSO.' );

                $redirect_url = 'https://' . $this->primary_site . add_query_arg( 'wpmssso_redirect', 1, '/' );
                $redirect_url = add_query_arg( 'wpmssso_return', urlencode( 'https://' . $this->secondary_site . '/' ), $redirect_url );

                wp_redirect( $redirect_url );
                exit;
            } else {
                $this->debug_message( 'Redirect already attempted on secondary site. No further action.' );
            }
        }
    }

    private function handle_primary_site_logic() {

        if ( isset($_GET["forcelogout"]) && $_GET["forcelogout"] == 'true' ) {

            // // set forcelogout cookie
            // setcookie('forcelogout', 'true', time() + 3600, COOKIEPATH, COOKIE_DOMAIN);
            // if ( isset( $_GET["source"] ) ) {
            //     if ( WP_DEBUG && WP_DEBUG_LOG ) {
            //         error_log( 'Logging out user from all sites and redirecting to ' . $_GET["source"] );
            //     }
            //     wp_logout( 'https://' . $_GET["source"] );
            // } else {
                $this->debug_message( 'Logging out user from all sites.' );
                // wp_logout();
                if ( is_user_logged_in() ) {
                    // Log out the user from all sites in the multisite
                    global $wpdb;
        
                    $user_id = get_current_user_id();
        
                    // Get all blogs/sites in the network
                    $blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs}", ARRAY_A );
        
                    if ( $blogs ) {
                        foreach ( $blogs as $blog ) {
                            $blog_id = $blog['blog_id'];

                            $this->debug_message( 'Logging out user from site ' . $blog_id );
        
                            // Switch to each blog and clear the session for that site
                            switch_to_blog( $blog_id );
                            // wp_logout();
                            $this->clear_auth_cookies();
                            restore_current_blog();
                        }
                    }
        
                    // Redirect to the home URL after logout
                    wp_redirect( home_url() );
                    exit;
                }
            // }
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
                wp_redirect( 'https://' . $this->secondary_site );
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