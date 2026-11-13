<?php
/**
 * One-time migration of legacy bbtc_* data to bdm_tc_* tables and options.
 *
 * @since 2.0.0
 */

namespace BuddyModules\TabCreator;

defined( 'ABSPATH' ) || exit;

class Migration {

	const DONE_OPTION      = 'bdm_tc_migration_done';
	const NOTICE_TRANSIENT = 'bdm_tc_migration_notice';

	/**
	 * Run the migration once, then mark it as done.
	 * No-op if already run or if no legacy tables exist.
	 *
	 * @since 2.0.0
	 */
	public static function maybe_migrate(): void {
		if ( get_option( self::DONE_OPTION ) ) {
			return;
		}

		global $wpdb;

		// If the legacy tabs table doesn't exist there is nothing to migrate.
		$legacy_tabs = $wpdb->prefix . 'bbtc_tabs';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy_tabs ) ) !== $legacy_tabs ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			update_option( self::DONE_OPTION, '1', false );
			return;
		}

		self::copy_tables();
		self::copy_options();

		update_option( self::DONE_OPTION, '1', false );
		set_transient( self::NOTICE_TRANSIENT, '1', HOUR_IN_SECONDS );
	}

	/**
	 * Copy all rows from each legacy bbtc_* table into the matching bdm_tc_* table.
	 * Uses INSERT IGNORE to preserve IDs and skip conflicts.
	 *
	 * @since 2.0.0
	 */
	private static function copy_tables(): void {
		global $wpdb;

		$map = [
			$wpdb->prefix . 'bbtc_tabs'                        => DB::get_tabs_table(),
			$wpdb->prefix . 'bbtc_tab_rules'                   => DB::get_rules_table(),
			$wpdb->prefix . 'bbtc_existing_tab_overrides'      => DB::get_overrides_table(),
			$wpdb->prefix . 'bbtc_existing_tab_override_rules' => DB::get_override_rules_table(),
		];

		foreach ( $map as $src => $dst ) {
			// Skip if source table doesn't exist.
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $src ) ) !== $src ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				continue;
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "INSERT IGNORE INTO {$dst} SELECT * FROM {$src}" );
		}
	}

	/**
	 * Copy legacy order options into the new bdm_tc_* option names.
	 * Only writes if the destination option does not already exist.
	 *
	 * @since 2.0.0
	 */
	private static function copy_options(): void {
		$options = [
			'bbtc_existing_profile_tab_order' => 'bdm_tc_existing_profile_tab_order',
			'bbtc_existing_group_tab_order'   => 'bdm_tc_existing_group_tab_order',
		];

		foreach ( $options as $src => $dst ) {
			$src_value = get_option( $src );
			if ( false !== $src_value && false === get_option( $dst ) ) {
				update_option( $dst, $src_value, false );
			}
		}
	}

	/**
	 * Register admin notices for migration result and legacy plugin guard.
	 * Hook onto bdm_admin_notices via Module::boot().
	 *
	 * @since 2.0.0
	 */
	public static function register_notices(): void {
		if ( get_transient( self::NOTICE_TRANSIENT ) ) {
			add_action( 'bdm_admin_notices', [ self::class, 'show_migration_notice' ] );
		}

		// Warn if the legacy plugin is still active alongside Buddy Modules.
		$active_plugins = (array) get_option( 'active_plugins', [] );
		if ( in_array( 'buddyboss-tab-creator/buddyboss-tab-creator.php', $active_plugins, true ) ) {
			add_action( 'bdm_admin_notices', [ self::class, 'show_legacy_guard_notice' ] );
		}
	}

	/**
	 * Show a one-time success notice after migration.
	 *
	 * @since 2.0.0
	 */
	public static function show_migration_notice(): void {
		delete_transient( self::NOTICE_TRANSIENT );
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Tab Creator: your existing tabs and settings have been migrated to Buddy Modules successfully.', 'buddy-modules' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Show a persistent warning while the legacy Tab Creator plugin is active.
	 *
	 * @since 2.0.0
	 */
	public static function show_legacy_guard_notice(): void {
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'BuddyBoss Tab Creator is still active.', 'buddy-modules' ); ?></strong>
				<?php esc_html_e( 'Please deactivate it — Buddy Modules now manages all profile and group tabs.', 'buddy-modules' ); ?>
			</p>
		</div>
		<?php
	}
}
