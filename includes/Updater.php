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
 * @since 2.0.0
 */
class Updater {

	/** GitHub repository that publishes the releases. */
	const REPO_URL = 'https://github.com/rezwan2024/buddy-modules/';

	/** Plugin slug, also used by the update checker for its option and cron names. */
	const SLUG = 'buddy-modules';

	/** Branch the workflow publishes built files to (fallback when no release exists). */
	const BRANCH = 'main';

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

		$checker = PucFactory::buildUpdateChecker( self::REPO_URL, BDM_FILE, self::SLUG );
		$checker->setBranch( self::BRANCH );
		$checker->getVcsApi()->enableReleaseAssets( self::ASSET_REGEX );
	}
}
