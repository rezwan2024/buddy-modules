<?php
/**
 * Remove Buddy Modules tables, options and transients on uninstall.
 *
 * @package BuddyModules
 * @since 2.0.0
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

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

// Delete Tab Creator transients.
delete_transient( 'bdm_tc_builtin_profile_nav_v2' );
delete_transient( 'bdm_tc_builtin_profile_nav_labels' );
delete_transient( 'bdm_tc_builtin_group_nav_v5' );
delete_transient( 'bdm_tc_builtin_group_nav_labels' );
delete_transient( 'bdm_tc_migration_notice' );
