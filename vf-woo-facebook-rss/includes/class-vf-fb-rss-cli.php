<?php
/**
 * WP-CLI command for VF Facebook RSS Feed
 *
 * @package vf-woo-facebook-rss
 * @version 1.1.0
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manages the VF Facebook RSS Feed file.
 *
 * @class VF_FB_RSS_CLI_Command
 */
class VF_FB_RSS_CLI_Command extends WP_CLI_Command {

	/**
	 * Regenerates the Facebook RSS feed file.
	 *
	 * This command generates or overwrites the static XML feed file.
	 * It's useful for cron jobs or for manually ensuring the feed is up-to-date.
	 *
	 * ## EXAMPLES
	 *
	 *     # Regenerate the feed file
	 *     wp vf-feed:generate
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Associated arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		WP_CLI::line( 'Regenerating feed file...' );

        try {
            // Ensure admin class is loaded for get_feed_directory()
            if ( ! class_exists('VF_FB_RSS_Admin') ) {
                require_once dirname( __FILE__ ) . '/class-vf-fb-rss-admin.php';
            }

            VF_FB_RSS_Feed::get_instance()->regenerate_file();

            $feed_dir = VF_FB_RSS_Admin::get_feed_directory();
            $feed_url = $feed_dir['url'] . '/facebook.xml';

            WP_CLI::success( 'Feed file regenerated successfully.' );
            WP_CLI::line( 'The feed is available at: ' . $feed_url );
        } catch ( Exception $e ) {
            WP_CLI::error( 'An error occurred during feed generation: ' . $e->getMessage() );
        }
	}
}
