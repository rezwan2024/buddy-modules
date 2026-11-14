<?php
/**
 * Core plugin singleton. Boots all subsystems.
 *
 * @package BuddyModules
 * @since 2.0.0
 */

namespace BuddyModules;

defined( 'ABSPATH' ) || exit;

/**
 * Main Buddy Modules plugin class that boots all subsystems.
 *
 * @since 2.0.0
 */
class Plugin {

	/**
	 * The single instance of the plugin.
	 *
	 * @since 2.0.0
	 * @var self|null
	 */
	private static ?self $instance = null;

	/** Minimum BuddyBoss Platform version required. */
	const MIN_PLATFORM_VERSION = '2.8';

	/**
	 * Private constructor. Use get_instance() instead.
	 *
	 * @since 2.0.0
	 */
	private function __construct() {}

	/**
	 * Retrieve or create the singleton instance.
	 *
	 * @since 2.0.0
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Check dependencies and boot all subsystems.
	 *
	 * @since 2.0.0
	 */
	private function init(): void {
		// Translations from the plugin's languages/ folder (PHP strings; the JS
		// strings load through wp_set_script_translations() in Admin\Assets).
		add_action( 'init', array( $this, 'load_textdomain' ) );

		if ( ! $this->platform_ok() ) {
			add_action( 'admin_notices', array( $this, 'show_platform_notice' ) );
			return;
		}

		// Defer module loading to bp_init so bp_is_active() is reliable.
		// BP components (active_components) are not populated until bp_init fires on init.
		// Priority 5: after bp_setup_globals (priority 4, which populates active_components)
		// but before bp_setup_nav (priority 6, which fires do_action('bp_setup_nav')).
		// Our Profile_Nav::init() / Group_Nav::init() register callbacks on bp_setup_nav,
		// so they must be hooked before that action fires.
		add_action( 'bp_init', array( $this, 'load_and_boot_modules' ), 5 );

		// Boot admin UI.
		if ( is_admin() ) {
			( new Admin\Menu() )->register();
			( new Admin\Assets() )->register();
			Admin\Page::register();
		}

		// Boot REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Load the plugin's translation files.
	 *
	 * @since 2.0.0
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'buddy-modules', false, dirname( plugin_basename( BDM_FILE ) ) . '/languages' );
	}

	/**
	 * Load modules and register their settings. Called on bp_init so bp_is_active() is ready.
	 *
	 * @since 2.0.0
	 */
	public function load_and_boot_modules(): void {
		$registry = Modules\Registry::get_instance();
		$this->load_modules();

		// Register settings schemas for all modules (always, regardless of active state).
		foreach ( $registry->all() as $module ) {
			$module->register_settings();
		}
	}

	/**
	 * Load all module registration files.
	 *
	 * @since 2.0.0
	 */
	private function load_modules(): void {
		$module_files = glob( BDM_DIR . 'modules/*/module.php' );
		if ( ! is_array( $module_files ) ) {
			return;
		}
		foreach ( $module_files as $file ) {
			require_once $file;
		}
	}

	/**
	 * Register all REST routes.
	 *
	 * @since 2.0.0
	 */
	public function register_rest_routes(): void {
		( new REST\Modules_Controller() )->register_routes();
		( new REST\Settings_Controller() )->register_routes();
	}

	/**
	 * Show an admin notice when BuddyBoss Platform is missing or too old.
	 *
	 * @since 2.0.0
	 */
	public function show_platform_notice(): void {
		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Buddy Modules', 'buddy-modules' ),
			esc_html__(
				'requires BuddyBoss Platform version 2.8 or higher to be installed and active.',
				'buddy-modules'
			)
		);
	}

	/**
	 * Check whether BuddyBoss Platform meets the minimum version.
	 *
	 * @since 2.0.0
	 */
	private function platform_ok(): bool {
		return defined( 'BP_PLATFORM_VERSION' )
			&& version_compare( BP_PLATFORM_VERSION, self::MIN_PLATFORM_VERSION, '>=' );
	}

	/**
	 * Run on plugin activation.
	 *
	 * @since 2.0.0
	 */
	public static function on_activate(): void {
		if ( ! get_option( 'bdm_active_modules' ) ) {
			add_option( 'bdm_active_modules', array() );
		}
		flush_rewrite_rules();
	}

	/**
	 * Run on plugin deactivation.
	 *
	 * @since 2.0.0
	 */
	public static function on_deactivate(): void {
		flush_rewrite_rules();
	}
}
