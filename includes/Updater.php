<?php
/**
 * Plugin updates from GitHub Releases.
 *
 * @package BuddyModules
 * @since 2.0.0
 */

namespace BuddyModules;

use YahnisElsts\PluginUpdateChecker\v6\PucFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the bundled Plugin Update Checker so the standard WordPress
 * "update available / update now" flow works from GitHub Releases.
 *
 * By default the check goes through the Buddy Modules update Worker, which
 * returns the latest GitHub release and counts active installs (hashed site
 * ID + plugin, WordPress and PHP versions). Returning false from the
 * `bdm_send_update_stats` filter checks GitHub directly instead.
 *
 * @since 2.0.0
 */
class Updater {

	/** GitHub repository that publishes the releases. */
	const REPO_URL = 'https://github.com/rezwan2024/buddy-modules/';

	/** Plugin slug, also used by the update checker for its option and cron names. */
	const SLUG = 'buddy-modules';

	/** Branch the workflow publishes built files to (fallback when no release exists). */
	const BRANCH = 'main';

	/** Update Worker: Plugin Update Checker metadata for the latest release. */
	const METADATA_URL = 'https://buddy-modules-updates.buddy-modules-update-worker.workers.dev/update';

	/** Name of the release asset to install. */
	const ASSET_REGEX = '/^buddy-modules\.zip$/';

	/**
	 * Build the update checker.
	 *
	 * A git checkout (development copy) is skipped by default, so an update
	 * never replaces a working tree with the release zip.
	 *
	 * @since 2.0.0
	 */
	public static function boot(): void {
		$enabled = ! is_dir( BDM_DIR . '.git' );

		/**
		 * Filters whether Buddy Modules checks GitHub for plugin updates.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $enabled True unless the plugin runs from a git checkout.
		 */
		if ( ! apply_filters( 'bdm_update_checker_enabled', $enabled ) ) {
			return;
		}

		$loader = BDM_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
		if ( ! file_exists( $loader ) ) {
			return;
		}

		require_once $loader;

		/**
		 * Filters whether update checks go through the Buddy Modules update
		 * Worker, which counts active installs. Return false to check
		 * GitHub directly and send nothing.
		 *
		 * @since 2.0.2
		 *
		 * @param bool $send True to use the update Worker.
		 */
		if ( apply_filters( 'bdm_send_update_stats', true ) ) {
			/**
			 * Filters the update Worker URL.
			 *
			 * @since 2.0.2
			 *
			 * @param string $url Metadata URL.
			 */
			$url     = apply_filters( 'bdm_update_metadata_url', self::METADATA_URL );
			$checker = PucFactory::buildUpdateChecker( $url, BDM_FILE, self::SLUG );
			$checker->addQueryArgFilter( array( self::class, 'stats_query_args' ) );
			return;
		}

		$checker = PucFactory::buildUpdateChecker( self::REPO_URL, BDM_FILE, self::SLUG );
		$checker->setBranch( self::BRANCH );
		$checker->getVcsApi()->enableReleaseAssets( self::ASSET_REGEX );
	}

	/**
	 * Add the hashed site ID and WordPress version to the update request.
	 * The update checker already sends the plugin and PHP versions.
	 *
	 * @since 2.0.2
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function stats_query_args( $args ) {
		$args['site'] = hash( 'sha256', 'buddy-modules|' . untrailingslashit( home_url() ) );
		$args['wp']   = get_bloginfo( 'version' );

		return $args;
	}
}
