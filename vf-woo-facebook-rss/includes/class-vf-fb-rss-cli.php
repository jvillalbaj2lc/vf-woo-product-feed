<?php
/**
 * WP-CLI command for VF Facebook RSS Feed
 *
 * @package vf-woo-facebook-rss
 * @version 1.0.0
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manages the VF Facebook RSS Feed.
 *
 * @class VF_FB_RSS_CLI_Command
 */
class VF_FB_RSS_CLI_Command extends WP_CLI_Command {

	/**
	 * Regenerates the Facebook RSS feed cache.
	 *
	 * This command will first clear any existing feed cache and then regenerate it.
	 * It's useful for cron jobs or for manually ensuring the feed is up-to-date.
	 *
	 * ## EXAMPLES
	 *
	 *     # Regenerate the feed cache
	 *     wp vf-feed:generate
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Associated arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		$feed_generator = VF_FB_RSS_Feed::get_instance();

        WP_CLI::line( 'Clearing feed cache...' );
        $feed_generator->clear_cache();
        WP_CLI::success( 'Feed cache cleared.' );

		WP_CLI::line( 'Regenerating feed cache...' );

        try {
            $feed_generator->regenerate_cache();
            WP_CLI::success( 'Feed cache regenerated successfully.' );
            WP_CLI::line( 'The feed is available at: ' . site_url( '/facebook-rss.xml' ) );
        } catch ( Exception $e ) {
            WP_CLI::error( 'An error occurred during feed generation: ' . $e->getMessage() );
        }
	}
}
