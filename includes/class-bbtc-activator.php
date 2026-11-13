<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation: creates DB tables and sets version option.
 */
class BBTC_Activator {

	public static function activate(): void {
		self::create_tables();
		update_option( 'bbtc_db_version', BBTC_DB_VERSION );
		flush_rewrite_rules();
		set_transient( 'bbtc_activation_redirect', true, 30 );
	}

	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$sql_tabs = "CREATE TABLE {$wpdb->prefix}bbtc_tabs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tab_type VARCHAR(20) NOT NULL,
			parent_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			parent_existing_slug VARCHAR(100) DEFAULT NULL,
			label VARCHAR(255) NOT NULL,
			slug VARCHAR(100) NOT NULL,
			position INT NOT NULL DEFAULT 10,
			is_enabled TINYINT(1) NOT NULL DEFAULT 1,
			is_predefined TINYINT(1) NOT NULL DEFAULT 0,
			site_admin_only TINYINT(1) NOT NULL DEFAULT 0,
			tab_link VARCHAR(500) DEFAULT NULL,
			default_subnav_slug VARCHAR(100) DEFAULT NULL,
			item_css_id VARCHAR(100) DEFAULT NULL,
			add_to_admin_bar TINYINT(1) NOT NULL DEFAULT 0,
			administer_item_id VARCHAR(100) DEFAULT NULL,
			content_source VARCHAR(20) NOT NULL DEFAULT 'content',
			content LONGTEXT,
			external_url VARCHAR(500) DEFAULT NULL,
			url_new_tab TINYINT(1) NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			is_default TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY tab_type (tab_type),
			KEY parent_id (parent_id)
		) $charset_collate;";

		$sql_rules = "CREATE TABLE {$wpdb->prefix}bbtc_tab_rules (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tab_id BIGINT UNSIGNED NOT NULL,
			rule_type VARCHAR(30) NOT NULL,
			rule_key VARCHAR(50) NOT NULL,
			rule_value VARCHAR(255) NOT NULL,
			PRIMARY KEY (id),
			KEY tab_id (tab_id)
		) $charset_collate;";

		$sql_overrides = "CREATE TABLE {$wpdb->prefix}bbtc_existing_tab_overrides (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tab_type VARCHAR(20) NOT NULL,
			original_slug VARCHAR(100) NOT NULL,
			label_override VARCHAR(255) DEFAULT NULL,
			position_override INT DEFAULT NULL,
			is_enabled TINYINT(1) NOT NULL DEFAULT 1,
			is_predefined TINYINT(1) NOT NULL DEFAULT 1,
			site_admin_only TINYINT(1) NOT NULL DEFAULT 0,
			tab_link VARCHAR(500) DEFAULT NULL,
			item_css_id VARCHAR(100) DEFAULT NULL,
			is_hidden TINYINT(1) NOT NULL DEFAULT 0,
			is_default TINYINT(1) NOT NULL DEFAULT 0,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY tab_type_slug (tab_type, original_slug)
		) $charset_collate;";

		$sql_override_rules = "CREATE TABLE {$wpdb->prefix}bbtc_existing_tab_override_rules (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			override_id BIGINT UNSIGNED NOT NULL,
			rule_type VARCHAR(30) NOT NULL,
			rule_key VARCHAR(50) NOT NULL,
			rule_value VARCHAR(255) NOT NULL,
			PRIMARY KEY (id),
			KEY override_id (override_id)
		) $charset_collate;";

		dbDelta( $sql_tabs );
		dbDelta( $sql_rules );
		dbDelta( $sql_overrides );
		dbDelta( $sql_override_rules );
	}
}
