<?php
/**
 * Plugin Name:       VF Woo Facebook RSS Feed
 * Plugin URI:        https://memorybrigade.pt/
 * Description:       Generates a Facebook Catalog compatible RSS feed for WooCommerce products.
 * Version:           1.1.0
 * Author:            Joseph V.
 * Author URI:        https://memorybrigade.pt/
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
define( 'VF_FB_RSS_VERSION', '1.1.0' );

/**
 * Checks if WooCommerce is active. If not, the plugin does nothing.
 */
function vf_fb_rss_activation_check() {
    if ( ! class_exists( 'WooCommerce' ) ) {
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
    require_once VF_FB_RSS_PLUGIN_PATH . 'includes/class-vf-fb-rss-feed.php';
    require_once VF_FB_RSS_PLUGIN_PATH . 'includes/class-vf-fb-rss-admin.php';

    // Initialize the feed generator and admin page
    VF_FB_RSS_Feed::get_instance();
    new VF_FB_RSS_Admin();

    // Register WP-CLI command if running in that context
    if ( defined( 'WP_CLI' ) && WP_CLI ) {
        require_once VF_FB_RSS_PLUGIN_PATH . 'includes/class-vf-fb-rss-cli.php';
        WP_CLI::add_command( 'vf-feed:generate', 'VF_FB_RSS_CLI_Command' );
    }
}
add_action( 'plugins_loaded', 'vf_fb_rss_init' );

/**
 * Declares compatibility with WooCommerce features.
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

