<?php
/**
 * Remove Buddy Modules tables, options and transients on uninstall.
 *
 * @package BuddyModules
 * @since 2.0.0
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Deleting one of two installed copies (e.g. a duplicate in another folder)
// must not remove the data the other copy still uses.
if ( ! function_exists( 'get_plugins' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

foreach ( array_keys( get_plugins() ) as $bdm_plugin_file ) {
	if ( WP_UNINSTALL_PLUGIN !== $bdm_plugin_file && 'buddy-modules.php' === basename( $bdm_plugin_file ) ) {
		return;
	}
}

// Dropping the plugin's own tables on uninstall.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
global $wpdb;

// Drop Tab Creator tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bdm_tc_tabs" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bdm_tc_tab_rules" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bdm_tc_existing_tab_overrides" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bdm_tc_existing_tab_override_rules" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery

// Delete Tab Creator options.
delete_option( 'bdm_tc_db_version' );
delete_option( 'bdm_tc_migration_done' );
delete_option( 'bdm_tc_existing_profile_tab_order' );
delete_option( 'bdm_tc_existing_group_tab_order' );

// Delete core module options.
delete_option( 'bdm_active_modules' );

// Delete the update checker's stored state and cron event.
delete_site_option( 'external_updates-buddy-modules' );
wp_clear_scheduled_hook( 'puc_cron_check_updates-buddy-modules' );

// Delete Tab Creator transients.
delete_transient( 'bdm_tc_builtin_profile_nav_v2' );
delete_transient( 'bdm_tc_builtin_profile_nav_labels' );
delete_transient( 'bdm_tc_builtin_group_nav_v5' );
delete_transient( 'bdm_tc_builtin_group_nav_labels' );
delete_transient( 'bdm_tc_migration_notice' );
