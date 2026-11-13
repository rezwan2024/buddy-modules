<?php
/**
 * Plugin Name: BuddyBoss Tab Creator
 * Plugin URI:  https://github.com/buddyboss-tab-creator
 * Description: Create and manage unlimited custom tabs and sub-tabs for BuddyBoss member profiles and groups. No coding required.
 * Version:     1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author:      BuddyBoss Tab Creator
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: buddyboss-tab-creator
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

// Constants.
define( 'BBTC_VERSION',     '1.0.0' );
define( 'BBTC_PLUGIN_FILE', __FILE__ );
define( 'BBTC_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'BBTC_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'BBTC_DB_VERSION',  '1.0.0' );

// Bootstrap.
require_once BBTC_PLUGIN_DIR . 'includes/class-bbtc-activator.php';
require_once BBTC_PLUGIN_DIR . 'includes/class-bbtc-deactivator.php';
require_once BBTC_PLUGIN_DIR . 'includes/class-bbtc-db.php';
require_once BBTC_PLUGIN_DIR . 'includes/class-bbtc-plugin.php';

// Activation hook — dependency check + table creation.
// Activation hook — table creation only, no dependency check here.
register_activation_hook(
	__FILE__,
	function (): void {
		BBTC_Activator::activate();
	}
);

// Deactivation hook.
register_deactivation_hook( __FILE__, [ 'BBTC_Deactivator', 'deactivate' ] );

// Dependency check and plugin init.
add_action( 'plugins_loaded', function() {
	if ( ! defined( 'BP_PLATFORM_VERSION' ) || ! version_compare( BP_PLATFORM_VERSION, '2.8', '>=' ) ) {
		add_action( 'admin_notices', function() {
			// Security fix: use esc_html__ for all translatable text; no raw user input here
			// but WP coding standards require proper escaping on all echo output.
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'BuddyBoss Tab Creator', 'buddyboss-tab-creator' ),
				esc_html__( 'requires BuddyBoss Platform version 2.8 or higher.', 'buddyboss-tab-creator' )
			);
		} );
		return;
	}
	BBTC_Plugin::get_instance();
}, 10 );