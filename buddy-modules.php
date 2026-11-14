<?php
/**
 * Plugin Name:       Buddy Modules
 * Plugin URI:        https://github.com/rezwan2024/buddy-modules
 * Description:       A module host for BuddyBoss sites. Includes Profile and Group Tab Creator and future modules.
 * Version:           2.0.6
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

define( 'BDM_VERSION', '2.0.6' );
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
