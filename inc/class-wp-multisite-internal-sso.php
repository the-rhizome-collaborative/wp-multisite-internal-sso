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
    private $primary_site  = 'multisite.lndo.site';
    private $secondary_site = 'bar.site';

    /**
     * Constructor.
     */
    public function __construct() {
        // Hook to an action to confirm the plugin is running.
        add_action( 'init', [ $this, 'init_action' ] );

        // Create a shortcode to display the site names (temporary for debugging).
        add_shortcode( 'wpmssso_sites', [ $this, 'display_sites' ] );
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

}
