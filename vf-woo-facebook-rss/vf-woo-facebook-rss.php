<?php
/**
 * Plugin Name:       VF Woo Facebook RSS Feed
 * Plugin URI:        https://github.com/VF-Jules/vf-woo-facebook-rss
 * Description:       Generates a Facebook Catalog compatible RSS feed for WooCommerce products.
 * Version:           1.0.0
 * Author:            Jules
 * Author URI:        https://va.new/jules
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vf-woo-facebook-rss
 * Domain Path:       /languages
 *
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'VF_FB_RSS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'VF_FB_RSS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VF_FB_RSS_VERSION', '1.0.0' );

/**
 * Checks if WooCommerce is active. If not, the plugin does nothing.
 */
function vf_fb_rss_activation_check() {
    if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        add_action( 'admin_notices', 'vf_fb_rss_wc_not_active_notice' );
        return false;
    }
    return true;
}

/**
 * Displays an admin notice if WooCommerce is not active.
 */
function vf_fb_rss_wc_not_active_notice() {
    ?>
    <div class="error">
        <p><?php _e( '<strong>VF Woo Facebook RSS Feed</strong> requires WooCommerce to be installed and active.', 'vf-woo-facebook-rss' ); ?></p>
    </div>
    <?php
}

/**
 * Initializes the plugin.
 *
 * Loads all necessary files and hooks into WordPress.
 */
function vf_fb_rss_init() {
    if ( ! vf_fb_rss_activation_check() ) {
        return;
    }

    // Load plugin files
    require_once VF_FB_RSS_PLUGIN_PATH . 'includes/class-vf-fb-rss-settings.php';
    require_once VF_FB_RSS_PLUGIN_PATH . 'includes/class-vf-fb-rss-feed.php';

    // Add integration to WooCommerce settings
    add_filter( 'woocommerce_integrations', 'vf_fb_rss_add_integration' );

    // Initialize the feed generator
    VF_FB_RSS_Feed::get_instance();

    // Register WP-CLI command if running in that context
    if ( defined( 'WP_CLI' ) && WP_CLI ) {
        require_once VF_FB_RSS_PLUGIN_PATH . 'includes/class-vf-fb-rss-cli.php';
        WP_CLI::add_command( 'vf-feed:generate', 'VF_FB_RSS_CLI_Command' );
    }
}
add_action( 'plugins_loaded', 'vf_fb_rss_init' );

/**
 * Adds the integration to the WooCommerce integrations array.
 *
 * @param array $integrations The existing integrations.
 * @return array The modified integrations array.
 */
function vf_fb_rss_add_integration( $integrations ) {
    $integrations[] = 'VF_FB_RSS_Settings';
    return $integrations;
}
