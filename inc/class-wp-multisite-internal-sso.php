<?php
/**
 * WP Multisite Internal SSO Main Class
 *
 * @package WP_Multisite_Internal_SSO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Main class for handling Multisite Internal SSO.
 */
class WP_Multisite_Internal_SSO {

    /**
     * Primary site URL.
     *
     * @var string
     */
    private $primary_site;

    /**
     * Array of secondary site URLs.
     *
     * @var array
     */
    private $secondary_sites = array();

    /**
     * Redirect cookie name.
     *
     * @var string
     */
    private $redirect_cookie_name;

    /**
     * Token expiration time in seconds.
     *
     * @var int
     */
    private $token_expiration;

    /**
     * Whether to enforce secure cookies.
     *
     * @var bool
     */
    private $secure_cookies;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->load_textdomain();
        $this->set_defaults();
        $this->register_hooks();
    }

    /**
     * Load plugin textdomain for translations.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'wp-multisite-internal-sso', false, dirname( plugin_basename( __FILE__ ) ) . '/../languages' );
    }

    /**
     * Set default values from settings or constants.
     */
    private function set_defaults() {
        // Fetch settings from the admin page
        $settings = get_option( 'wpmis_sso_settings', array() );

        $this->primary_site       = isset( $settings['primary_site'] ) ? esc_url_raw( $settings['primary_site'] ) : get_site_url( get_main_site_id() );
        $this->secondary_sites    = isset( $settings['secondary_sites'] ) ? array_map( 'esc_url_raw', (array) $settings['secondary_sites'] ) : [get_site_url( 2 )];
        $this->redirect_cookie_name = isset( $settings['redirect_cookie_name'] ) ? sanitize_text_field( $settings['redirect_cookie_name'] ) : 'wpmssso_redirect_attempt';
        $this->token_expiration   = isset( $settings['token_expiration'] ) ? absint( $settings['token_expiration'] ) : 300; // Default 5 minutes
        $this->secure_cookies     = isset( $settings['secure_cookies'] ) ? boolval( $settings['secure_cookies'] ) : is_ssl();
    
        // add slash to end of all secondary site urls, big fix
        $this->secondary_sites = array_map( 'trailingslashit', $this->secondary_sites );
    }

    /**
     * Register WordPress hooks.
     */
    private function register_hooks() {
        add_action( 'init', array( $this, 'init_action' ), 1 );
        add_action( 'template_redirect', array( $this, 'check_sso' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_filter( 'login_redirect', array( $this, 'wpmis_sso_login_redirect' ), 10, 3 );

        if ( WP_DEBUG ) {
            add_action( 'wp_body_open', array( $this, 'display_user_status' ) );
        }
    }

    public function wpmis_sso_login_redirect($redirect_to, $request, $user) {
        //is there a user to check?
        if (isset($user->roles) && is_array($user->roles)) {
            //check for admins
            if (in_array('administrator', $user->roles)) {
                // redirect them to the default place
                return home_url('/wp-admin/');
            } else {
                return home_url();
            }
        } else {
            return $redirect_to;
        }
    }

    /**
     * Initialize actions on 'init' hook.
     */
    public function init_action() {
        $this->debug_message( __( 'Init action triggered.', 'wp-multisite-internal-sso' ) );
        $this->debug_message( __( 'Primary site:', 'wp-multisite-internal-sso' ) . ' ' . $this->primary_site );
        $this->debug_message( __( 'Secondary sites:', 'wp-multisite-internal-sso' ) . ' ' . implode( ', ', $this->secondary_sites ) );

        // Handle forced logout
        if ( isset( $_GET['forcelogout'] ) && 'true' === $_GET['forcelogout'] ) {
            if ( is_user_logged_in() ) {
                $this->logout_user();
            }

            if ( isset( $_GET['source'] ) && $this->is_valid_site_url( $_GET['source'] ) ) {
                $redirect_url = add_query_arg( 'forcelogout', 'true', esc_url_raw( $_GET['source'] ) );
                wp_redirect( $redirect_url );
                exit;
            } else {
                wp_redirect( home_url() );
                exit;
            }
        }
    }

    /**
     * Check and handle SSO logic on 'template_redirect' hook.
     */
    public function check_sso() {
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            $this->debug_message( __( 'Skipping SSO logic for admin and AJAX requests.', 'wp-multisite-internal-sso' ) );
            return;
        }

        $current_host = $this->get_current_site_url();

        if ( $current_host === $this->primary_site ) {
            $this->debug_message( __( 'Running SSO logic for primary site.', 'wp-multisite-internal-sso' ) );
            $this->handle_primary_site_logic();
        } elseif ( in_array( $current_host, $this->secondary_sites, true ) ) {
            $this->debug_message( __( 'Running SSO logic for secondary site.', 'wp-multisite-internal-sso' ) );
            $this->handle_secondary_site_logic();
        } else {
            $this->debug_message( __( 'No SSO logic for current host.', 'wp-multisite-internal-sso' ) . ' ' . $current_host . ' ' . $this->primary_site );
        }
    }

    /**
     * Display user login status and action buttons.
     */
    public function display_user_status() {

        $clear_cookies_button = '<button onclick="document.cookie = \'wpmssso_redirect_attempt=;expires=Thu, 01 Jan 1970 00:00:00 GMT\';">' . esc_html__( 'Clear Cookies', 'wp-multisite-internal-sso' ) . '</button>';

        if ( ! is_user_logged_in() ) {
            echo '<div class="wpmis-sso-status not-logged-in">' . esc_html__( 'Not logged in - ', 'wp-multisite-internal-sso' ) . esc_url( get_site_url() ) . '</div>';
            echo ' | ';
            echo $clear_cookies_button;
            return;
        }

        echo '<div class="wpmis-sso-status logged-in">' . esc_html__( 'Logged in - ', 'wp-multisite-internal-sso' ) . esc_url( get_site_url() ) . '</div>';

        // Display logout button
        echo '<div class="wpmis-sso-actions">';
        echo '<a href="' . esc_url( $this->get_logout_url() ) . '">' . esc_html__( 'Logout', 'wp-multisite-internal-sso' ) . '</a>';
        echo ' | ';
        echo $clear_cookies_button;
        echo '</div>';

        // Enqueue CSS for styling
        wp_enqueue_style( 'wpmis-sso-styles', WPMIS_SSO_PLUGIN_URL . 'assets/css/wpmis-sso.css', array(), WPMIS_SSO_PLUGIN_VERSION );
    }

    /**
     * Enqueue admin scripts.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'settings_page_wp-multisite-internal-sso' !== $hook ) {
            return;
        }

        wp_enqueue_script( 'wpmis-sso-admin-js', WPMIS_SSO_PLUGIN_URL . 'assets/js/wpmis-sso-admin.js', array(), WPMIS_SSO_PLUGIN_VERSION, true );
    }

    /**
     * Add admin menu for plugin settings.
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'WP Multisite Internal SSO Settings', 'wp-multisite-internal-sso' ),
            __( 'Multisite SSO', 'wp-multisite-internal-sso' ),
            'manage_options',
            'wp-multisite-internal-sso',
            array( $this, 'settings_page' )
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting( 'wpmis_sso_settings_group', 'wpmis_sso_settings', array( $this, 'sanitize_settings' ) );

        add_settings_section(
            'wpmis_sso_main_section',
            __( 'Main Settings', 'wp-multisite-internal-sso' ),
            null,
            'wp-multisite-internal-sso'
        );

        add_settings_field(
            'primary_site',
            __( 'Primary Site URL', 'wp-multisite-internal-sso' ),
            array( $this, 'primary_site_callback' ),
            'wp-multisite-internal-sso',
            'wpmis_sso_main_section'
        );

        add_settings_field(
            'secondary_sites',
            __( 'Secondary Sites URLs', 'wp-multisite-internal-sso' ),
            array( $this, 'secondary_sites_callback' ),
            'wp-multisite-internal-sso',
            'wpmis_sso_main_section'
        );

        add_settings_field(
            'token_expiration',
            __( 'Token Expiration (seconds)', 'wp-multisite-internal-sso' ),
            array( $this, 'token_expiration_callback' ),
            'wp-multisite-internal-sso',
            'wpmis_sso_main_section'
        );

        add_settings_field(
            'redirect_cookie_name',
            __( 'Redirect Cookie Name', 'wp-multisite-internal-sso' ),
            array( $this, 'redirect_cookie_name_callback' ),
            'wp-multisite-internal-sso',
            'wpmis_sso_main_section'
        );

        add_settings_field(
            'secure_cookies',
            __( 'Secure Cookies', 'wp-multisite-internal-sso' ),
            array( $this, 'secure_cookies_callback' ),
            'wp-multisite-internal-sso',
            'wpmis_sso_main_section'
        );
    }

    /**
     * Sanitize plugin settings input.
     *
     * @param array $input Input settings.
     * @return array Sanitized settings.
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        if ( isset( $input['primary_site'] ) ) {
            $sanitized['primary_site'] = trailingslashit( esc_url_raw( $input['primary_site'] ) );
        }
    
        if ( isset( $input['secondary_sites'] ) && is_array( $input['secondary_sites'] ) ) {
            $sanitized['secondary_sites'] = array_map( 'trailingslashit', array_map( 'esc_url_raw', $input['secondary_sites'] ) );
        }

        if ( isset( $input['token_expiration'] ) ) {
            $sanitized['token_expiration'] = absint( $input['token_expiration'] );
        }

        if ( isset( $input['redirect_cookie_name'] ) ) {
            $sanitized['redirect_cookie_name'] = sanitize_text_field( $input['redirect_cookie_name'] );
        }

        if ( isset( $input['secure_cookies'] ) ) {
            $sanitized['secure_cookies'] = boolval( $input['secure_cookies'] );
        }

        return $sanitized;
    }

    /**
     * Callback for primary site URL field.
     */
    public function primary_site_callback() {
        $settings = get_option( 'wpmis_sso_settings', array() );
        ?>
        <input type="url" name="wpmis_sso_settings[primary_site]" value="<?php echo isset( $settings['primary_site'] ) ? esc_attr( $settings['primary_site'] ) : esc_url( get_site_url( get_main_site_id() ) ); ?>" size="50" required />
        <p class="description"><?php esc_html_e( 'Enter the URL of the primary site where users will authenticate.', 'wp-multisite-internal-sso' ); ?></p>
        <?php
    }

    /**
     * Callback for secondary sites URLs field.
     */
    public function secondary_sites_callback() {
        $settings = get_option( 'wpmis_sso_settings', array() );
        $secondary_sites = isset( $settings['secondary_sites'] ) ? $settings['secondary_sites'] : array( '' );
        ?>
        <div id="secondary-sites-wrapper">
            <?php foreach ( $secondary_sites as $index => $site_url ) : ?>
                <div class="secondary-site-field">
                    <input type="url" name="wpmis_sso_settings[secondary_sites][]" value="<?php echo esc_attr( $site_url ); ?>" size="50" required />
                    <?php if ( $index > 0 ) : ?>
                        <button type="button" class="button remove-secondary-site"><?php esc_html_e( 'Remove', 'wp-multisite-internal-sso' ); ?></button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button" id="add-secondary-site"><?php esc_html_e( 'Add Secondary Site', 'wp-multisite-internal-sso' ); ?></button>
        <p class="description"><?php esc_html_e( 'Enter the URLs of the secondary sites that should accept SSO from the primary site.', 'wp-multisite-internal-sso' ); ?></p>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('add-secondary-site').addEventListener('click', function(e) {
                    e.preventDefault();
                    var wrapper = document.getElementById('secondary-sites-wrapper');
                    var field = document.createElement('div');
                    field.className = 'secondary-site-field';
                    field.innerHTML = '<input type="url" name="wpmis_sso_settings[secondary_sites][]" value="" size="50" required /> <button type="button" class="button remove-secondary-site"><?php esc_html_e( 'Remove', 'wp-multisite-internal-sso' ); ?></button>';
                    wrapper.appendChild(field);
                });

                document.getElementById('secondary-sites-wrapper').addEventListener('click', function(e) {
                    if ( e.target && e.target.classList.contains('remove-secondary-site') ) {
                        e.target.parentElement.remove();
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * Callback for token expiration field.
     */
    public function token_expiration_callback() {
        $settings = get_option( 'wpmis_sso_settings', array() );
        $expiration = isset( $settings['token_expiration'] ) ? absint( $settings['token_expiration'] ) : 300;
        ?>
        <input type="number" name="wpmis_sso_settings[token_expiration]" value="<?php echo esc_attr( $expiration ); ?>" min="60" step="60" />
        <p class="description"><?php esc_html_e( 'Define how long SSO tokens remain valid (in seconds). Default is 300 seconds (5 minutes).', 'wp-multisite-internal-sso' ); ?></p>
        <?php
    }

    /**
     * Callback for redirect cookie name field.
     */
    public function redirect_cookie_name_callback() {
        $settings = get_option( 'wpmis_sso_settings', array() );
        $cookie_name = isset( $settings['redirect_cookie_name'] ) ? sanitize_text_field( $settings['redirect_cookie_name'] ) : 'wpmssso_redirect_attempt';
        ?>
        <input type="text" name="wpmis_sso_settings[redirect_cookie_name]" value="<?php echo esc_attr( $cookie_name ); ?>" size="30" />
        <p class="description"><?php esc_html_e( 'Specify a custom name for the redirect cookie used during the SSO process.', 'wp-multisite-internal-sso' ); ?></p>
        <?php
    }

    /**
     * Callback for secure cookies field.
     */
    public function secure_cookies_callback() {
        $settings = get_option( 'wpmis_sso_settings', array() );
        $secure = isset( $settings['secure_cookies'] ) ? boolval( $settings['secure_cookies'] ) : is_ssl();
        ?>
        <input type="checkbox" name="wpmis_sso_settings[secure_cookies]" value="1" <?php checked( $secure, true ); ?> />
        <p class="description"><?php esc_html_e( 'Enable secure cookies to ensure cookies are transmitted only over HTTPS.', 'wp-multisite-internal-sso' ); ?></p>
        <?php
    }

    /**
     * Render the settings page.
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'WP Multisite Internal SSO Settings', 'wp-multisite-internal-sso' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wpmis_sso_settings_group' );
                do_settings_sections( 'wp-multisite-internal-sso' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
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

                    $this->debug_message( __( 'Sending token to secondary site for user', 'wp-multisite-internal-sso' ) . ' ' . $user_login );

                    wp_redirect( $redirect_back );
                    exit;
                }
            } else {
                $this->debug_message( __( 'User not logged in on primary site. Redirecting to secondary site.', 'wp-multisite-internal-sso' ) );
                wp_redirect( $this->secondary_sites[0] );
                exit;
            }
        }
    }

    /**
     * Handle secondary site SSO logic.
     */
    private function handle_secondary_site_logic() {
        if ( is_user_logged_in() ) {
            $this->debug_message( __( 'User already logged in on secondary site.', 'wp-multisite-internal-sso' ) );
            return;
        }

        if ( isset( $_GET['wpmssso_user'], $_GET['wpmssso_token'], $_GET['wpmssso_time'] ) ) {
            $user_login = sanitize_user( wp_unslash( $_GET['wpmssso_user'] ), true );
            $token      = sanitize_text_field( wp_unslash( $_GET['wpmssso_token'] ) );
            $time       = absint( $_GET['wpmssso_time'] );

            if ( $this->verify_sso_token( $user_login, $token, $time ) ) {
                $this->log_user_in( $user_login );
                $this->clear_redirect_cookie();
                $this->debug_message( __( 'Successfully logged in user on secondary site.', 'wp-multisite-internal-sso' ) . ' ' . $user_login );
                wp_redirect( remove_query_arg( array( 'wpmssso_user', 'wpmssso_token', 'wpmssso_time' ) ) );
                exit;
            } else {
                $this->debug_message( __( 'Invalid or expired token for user.', 'wp-multisite-internal-sso' ) . ' ' . $user_login );
                return;
            }
        } else {
            if ( ! isset( $_COOKIE[ $this->redirect_cookie_name ] ) ) {
                $this->set_redirect_cookie();
                $this->debug_message( __( 'Redirecting to primary site for SSO.', 'wp-multisite-internal-sso' ) );
                $redirect_url = add_query_arg( 'wpmssso_redirect', '1', $this->primary_site );
                $redirect_url = add_query_arg( 'wpmssso_return', urlencode( $this->get_current_site_url() ), $redirect_url );
                wp_redirect( $redirect_url );
                exit;
            } else {
                $this->debug_message( __( 'Redirect already attempted on secondary site. No further action.', 'wp-multisite-internal-sso' ) );
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
        $this->debug_message( __( 'Generating SSO token.', 'wp-multisite-internal-sso' ) );
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
        if ( ( time() - $time ) > $this->token_expiration ) {
            return false;
        }
        $expected = $this->generate_sso_token( $user_login, $time );
        $this->debug_message( __( 'Verifying SSO token.', 'wp-multisite-internal-sso' ) );
        return hash_equals( $expected, $token );
    }

    /**
     * Log user in based on username.
     *
     * @param string $user_login User login name.
     */
    private function log_user_in( $user_login ) {
        $user = get_user_by( 'login', $user_login );
        if ( $user && $user->exists() ) {
            wp_set_auth_cookie( $user->ID, false, is_ssl() );
            wp_set_current_user( $user->ID );

            if ( is_user_logged_in() ) {
                $this->debug_message( __( 'User logged in successfully on secondary site.', 'wp-multisite-internal-sso' ) . ' ' . $user_login );
            } else {
                $this->debug_message( __( 'Login failed for user.', 'wp-multisite-internal-sso' ) . ' ' . $user_login );
            }
        } else {
            $this->debug_message( __( 'User not found on secondary site.', 'wp-multisite-internal-sso' ) . ' ' . $user_login );
        }
    }

    /**
     * Logout user from all sites.
     */
    private function logout_user() {
        $this->debug_message( __( 'Logging out user from all sites.', 'wp-multisite-internal-sso' ) );

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
            wp_redirect( home_url() );
            exit;
        }
    }

    /**
     * Clear authentication cookies.
     */
    private function clear_auth_cookies() {
        $this->debug_message( __( 'Clearing authentication cookies.', 'wp-multisite-internal-sso' ) );

        wp_clear_auth_cookie();

        setcookie( LOGGED_IN_COOKIE, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, $this->secure_cookies, true );
        setcookie( LOGGED_IN_COOKIE, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN, $this->secure_cookies, true );
        setcookie( AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, $this->secure_cookies, true );
        setcookie( AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN, $this->secure_cookies, true );
        setcookie( SECURE_AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, $this->secure_cookies, true );
        setcookie( SECURE_AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN, $this->secure_cookies, true );
        setcookie( 'wordpress_logged_in_' . COOKIEHASH, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, $this->secure_cookies, true );
        setcookie( 'wordpress_logged_in_' . COOKIEHASH, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN, $this->secure_cookies, true );
        setcookie( $this->redirect_cookie_name, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, $this->secure_cookies, false );

        $this->debug_message( __( 'Authentication cookies cleared.', 'wp-multisite-internal-sso' ) );

        $user_id = get_current_user_id();
        if ( $user_id ) {
            $this->debug_message( __( 'Destroying session for user.', 'wp-multisite-internal-sso' ) . ' ' . $user_id );
            $session_manager = WP_Session_Tokens::get_instance( $user_id );
            $session_manager->destroy_all();
        }

        if ( function_exists( 'delete_user_meta' ) ) {
            $this->debug_message( __( 'Deleting user meta for user.', 'wp-multisite-internal-sso' ) . ' ' . $user_id );
            delete_user_meta( $user_id, 'session_tokens' );
        }

        if ( isset( $_GET['source'] ) && $this->is_valid_site_url( $_GET['source'] ) ) {
            $this->debug_message( __( 'Redirecting to source site.', 'wp-multisite-internal-sso' ) . ' ' . esc_url_raw( $_GET['source'] ) );
            wp_redirect( esc_url_raw( $_GET['source'] ) );
            exit;
        } else {
            $this->debug_message( __( 'Redirecting to home URL.', 'wp-multisite-internal-sso' ) );
            wp_redirect( home_url() );
            exit;
        }
    }

    /**
     * Generate logout URL with nonce.
     *
     * @return string Logout URL.
     */
    private function get_logout_url() {
        $args = array(
            'forcelogout' => 'true',
            '_wpnonce'    => wp_create_nonce( 'wpmis_sso_logout' ),
        );

        $current_host = $this->get_current_site_url();
        if ( in_array( $current_host, $this->secondary_sites, true ) ) {
            $args['source'] = $current_host;
        }

        return add_query_arg( $args, $this->get_current_site_url() );
    }

    /**
     * Generate clear cookies URL with nonce.
     *
     * @return string Clear cookies URL.
     */
    private function get_clear_cookies_url() {
        return add_query_arg( array(
            'clear_cookies' => 'true',
            '_wpnonce'      => wp_create_nonce( 'wpmis_sso_clear_cookies' ),
        ), $this->get_current_site_url() );
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
     * Set redirect cookie.
     */
    private function set_redirect_cookie() {
        setcookie( $this->redirect_cookie_name, '1', time() + 300, COOKIEPATH, COOKIE_DOMAIN, $this->secure_cookies, false );
        $this->debug_message( __( 'Redirect cookie set.', 'wp-multisite-internal-sso' ) );
    }

    /**
     * Clear redirect cookie.
     */
    private function clear_redirect_cookie() {
        setcookie( $this->redirect_cookie_name, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, $this->secure_cookies, false );
        $this->debug_message( __( 'Redirect cookie cleared.', 'wp-multisite-internal-sso' ) );
    }

    /**
     * Get the current site URL.
     *
     * @return string Current site URL.
     */
    private function get_current_site_url() {
        return trailingslashit( home_url() );
    }

    /**
     * Validate if the given URL is a valid secondary site.
     *
     * @param string $url URL to validate.
     * @return bool True if valid, false otherwise.
     */
    private function is_valid_site_url( $url ) {
        $url = esc_url_raw( $url );
        return in_array( trailingslashit( $url ), $this->secondary_sites, true );
    }

    /**
     * Log messages to the debug log if enabled.
     *
     * @param string $message Message to log.
     */
    private function debug_message( $message ) {
        if ( WP_DEBUG && WP_DEBUG_LOG ) {
            error_log( "WPMIS SSO: " . $message . "\n", 3, WP_CONTENT_DIR . '/sso-debug.log' );
        }
    }
}