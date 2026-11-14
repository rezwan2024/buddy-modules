<?php
/**
 * Plugin Name:       Buddy Modules
 * Plugin URI:        https://github.com/rezwan2024/buddy-modules
 * Description:       A module host for BuddyBoss sites. Includes Profile and Group Tab Creator and future modules.
 * Version:           2.0.9
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            MD. Rezwan Shiblu
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       buddy-modules
 * Domain Path:       /languages
 *
 * @package BuddyModules
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

// A second copy of the plugin (installed in another folder) must not load:
// it would redeclare the classes and run a second update checker. It only
// tells the admin which copy to keep.
if ( defined( 'BDM_FILE' ) ) {
	$bdm_duplicate_notice = static function (): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$running   = basename( dirname( BDM_FILE ) );
		$duplicate = basename( __DIR__ );
		$keep      = 'buddy-modules' === $duplicate ? $duplicate : $running;
		$remove    = $keep === $running ? $duplicate : $running;

		printf(
			'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'Buddy Modules is installed twice.', 'buddy-modules' ),
			sprintf(
				/* translators: 1: folder to keep, 2: folder to remove. */
				esc_html__( 'Keep the copy in %1$s (updates and uploads use that folder). On the Plugins screen, deactivate the copy in %2$s and then delete it. Your tabs and settings are kept.', 'buddy-modules' ),
				'<code>wp-content/plugins/' . esc_html( $keep ) . '/</code>',
				'<code>wp-content/plugins/' . esc_html( $remove ) . '/</code>'
			)
		);
	};
	add_action( 'admin_notices', $bdm_duplicate_notice );
	add_action( 'bdm_admin_notices', $bdm_duplicate_notice );
	return;
}

define( 'BDM_VERSION', '2.0.9' );
define( 'BDM_FILE', __FILE__ );
define( 'BDM_DIR', plugin_dir_path( __FILE__ ) );
define( 'BDM_URL', plugin_dir_url( __FILE__ ) );

require_once BDM_DIR . 'includes/Autoloader.php';
( new BuddyModules\Autoloader() )->register();

// Updates from GitHub Releases. Runs outside plugins_loaded so the update
// checker works even when the BuddyBoss Platform dependency is missing.
BuddyModules\Updater::boot();

register_activation_hook(
	__FILE__,
	function (): void {
		BuddyModules\Plugin::on_activate();
	}
);

register_deactivation_hook(
	__FILE__,
	function (): void {
		BuddyModules\Plugin::on_deactivate();
	}
);

add_action(
	'plugins_loaded',
	function (): void {
		BuddyModules\Plugin::get_instance();
	},
	10
);
