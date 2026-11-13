<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bbtc_tabs" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bbtc_tab_rules" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bbtc_existing_tab_overrides" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bbtc_existing_tab_override_rules" );

delete_option( 'bbtc_db_version' );
delete_metadata( 'user', 0, 'bbtc_experimental_notice_shown', '', true );
