<?php
/**
 * Plugin Name: WP Multisite Internal SSO
 * Plugin URI:  https://github.com/the-rhizome-collaborative/wp-multisite-internal-sso
 * Description: Enables automatic login (SSO) for users from one multisite installation to another.
 * Version:     0.2.03
 * Author:      The Rhizome Collaborative
 * Author URI:  https://rhizomecollaborative.com
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
define( 'WPBYPASS_SSO', false );

require_once WPMIS_SSO_PLUGIN_PATH . 'includes/class-wp-multisite-internal-sso-utils.php';
require_once WPMIS_SSO_PLUGIN_PATH . 'includes/class-wp-multisite-internal-sso.php';

/**
 * Check if current request is from a web crawler or bot
 *
 * @return boolean True if request is from a crawler
 */
function wpmisso_is_crawler() {
    $utils = new WP_Multisite_Internal_SSO_Utils();
	$utils->debug_message('Checking for Crawler', true);

    // List of known crawler user agents
    $crawler_agents = array(
        'facebookexternalhit',
        'Facebot',
        'Googlebot',
        'bingbot',
        'Twitterbot',
        'LinkedInBot',
        'Slackbot',
        'TelegramBot',
        'WhatsApp',
        'Pinterest',
        'vkShare',
        'W3C_Validator',
        'baiduspider',
        'yandexbot'
    );

    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
		$utils->debug_message('Found User Agent: ' . $user_agent, true);
        foreach ($crawler_agents as $crawler) {
            if (strpos($user_agent, strtolower($crawler)) !== false) {
                $utils->debug_message('Bypassing SSO for crawler: ' . $_SERVER['HTTP_USER_AGENT'], true);
                return true;
            }
        }
    }

    return false;
}

/**
 * Check if the current request type should allow SSO
 *
 * @return boolean True if the request should proceed with SSO
 */
function wpmisso_allow_request() {
    $utils = new WP_Multisite_Internal_SSO_Utils();
    
    $utils->debug_message('REQUEST: ' . $_SERVER['REQUEST_URI'] . "\n", true);

    // Bypass for crawlers
    if (wpmisso_is_crawler()) {
        return false;
    }

    // Check if request is for media or static files
    $file_requests_to_ignore = [
        '*.ico',
        'robots.txt',
        'sitemap*.xml',
        'wp-cron.php',
        'admin-ajax.php',
        'wp-json',
        'wp-content/uploads/*',
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
        '*.pdf',
        '*.mp4',
        '*.webp',
        'favicon*',
    ];

    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

    foreach ($file_requests_to_ignore as $ignored_file) {
        // Properly escape path separators and convert wildcard patterns to regex
        $escaped_pattern = str_replace(['*', '.'], ['.*', '\.'], $ignored_file);

        // Add delimiters properly and make case-insensitive
        $pattern = '#' . $escaped_pattern . '#i';

        if (preg_match($pattern, $request_uri)) {
            $utils->debug_message('Skipping SSO due to request of ' . $ignored_file . ' - URI: ' . $request_uri . "\n");
            return false;
        }
    }

    return true;
}

function wpmis_sso_init() {
    $GLOBALS['wp_multisite_internal_sso'] = new WP_Multisite_Internal_SSO();
}

// Only initialize SSO if conditions are met
if (is_multisite() && wpmisso_allow_request() && !isset($_GET['wpmisso_ignore'])) {
    add_action('plugins_loaded', 'wpmis_sso_init');
}

add_action('init', 'wpmisso_redirect_cookie_count');
function wpmisso_redirect_cookie_count() {
    $utils = new WP_Multisite_Internal_SSO_Utils();
    $utils->debug_message('Setting cookie count');
    if (isset($_GET['wpmisso_request'])) {
        $utils->debug_message('Has wpmisso_request');
        $cookie_name = 'wpmisso_request';
        $cookie_value = isset($_COOKIE[$cookie_name]) ? $_COOKIE[$cookie_name] + 1 : 1;
        setcookie($cookie_name, $cookie_value, time() + 3600, '/', '', isset($_SERVER['HTTPS']), true);
    } else {
        $utils->debug_message('No wpmisso_request');
    }
}