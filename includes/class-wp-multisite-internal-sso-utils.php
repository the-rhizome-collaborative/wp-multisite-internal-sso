<?php
/**
 * WP Multisite Internal SSO Utility Class
 *
 * @package WP_Multisite_Internal_SSO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WP_Multisite_Internal_SSO_Utils {

    /**
     * Log messages to the debug log if enabled.
     *
     * @param string $message Message to log.
     */
    public function debug_message( $message ) {
        if ( WP_DEBUG && WP_DEBUG_LOG ) {
            error_log( "WPMIS SSO: " . $message . "\n", 3, WP_CONTENT_DIR . '/sso-debug.log' );
        }
    }

    /**
     * Validate if the given URL is a valid secondary site.
     *
     * @param string $url         URL to validate.
     * @param array  $secondary_sites Array of secondary sites URLs.
     * @return bool True if valid, false otherwise.
     */
    public function is_valid_site_url( $url, $secondary_sites = array() ) {
        $url = esc_url_raw( $url );
        $secondary_sites = empty( $secondary_sites ) ? array() : $secondary_sites;
        return in_array( trailingslashit( $url ), $secondary_sites, true );
    }
}