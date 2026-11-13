<?php
/**
 * All database CRUD for Tab Creator. No other class may call $wpdb directly.
 *
 * @since 2.0.0
 */

namespace BuddyModules\TabCreator;

defined( 'ABSPATH' ) || exit;

class DB {

	private static array $tabs_cache      = [];
	private static array $rules_cache     = [];
	private static array $overrides_cache = [];

	// -------------------------------------------------------------------------
	// Table name getters
	// -------------------------------------------------------------------------

	public static function get_tabs_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'bdm_tc_tabs';
	}

	public static function get_rules_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'bdm_tc_tab_rules';
	}

	public static function get_overrides_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'bdm_tc_existing_tab_overrides';
	}

	public static function get_override_rules_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'bdm_tc_existing_tab_override_rules';
	}

	// -------------------------------------------------------------------------
	// Tab CRUD
	// -------------------------------------------------------------------------

	/**
	 * Insert a new tab row.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data Column => value pairs.
	 * @return int|false  Inserted ID or false on failure.
	 */
	public static function insert_tab( array $data ): int|false {
		global $wpdb;

		$now = current_time( 'mysql' );

		if ( ! isset( $data['created_at'] ) ) {
			$data['created_at'] = $now;
		}
		if ( ! isset( $data['updated_at'] ) ) {
			$data['updated_at'] = $now;
		}

		$result = $wpdb->insert( self::get_tabs_table(), $data );

		if ( false === $result ) {
			return false;
		}

		self::clear_cache();

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing tab row.
	 *
	 * @since 2.0.0
	 *
	 * @param int   $id   Tab ID.
	 * @param array $data Fields to update.
	 * @return bool
	 */
	public static function update_tab( int $id, array $data ): bool {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			self::get_tabs_table(),
			$data,
			[ 'id' => $id ]
		);

		self::clear_cache();

		return false !== $result;
	}

	/**
	 * Get a single tab by ID.
	 *
	 * @since 2.0.0
	 *
	 * @param int $id
	 * @return object|null
	 */
	public static function get_tab( int $id ): object|null {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$wpdb->prefix}bdm_tc_tabs WHERE id = %d",
				$id
			)
		);

		return $row ?? null;
	}

	/**
	 * Get tabs by type with optional filters.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tab_type 'profile' or 'group'.
	 * @param array  $args     Optional query args.
	 * @return array
	 */
	public static function get_tabs( string $tab_type, array $args = [] ): array {
		if ( is_admin() ) {
			return self::query_tabs( $tab_type, $args );
		}

		$cache_key = $tab_type . '_' . md5( serialize( $args ) );

		if ( isset( self::$tabs_cache[ $cache_key ] ) ) {
			return self::$tabs_cache[ $cache_key ];
		}

		$results                        = self::query_tabs( $tab_type, $args );
		self::$tabs_cache[ $cache_key ] = $results;

		return $results;
	}

	/**
	 * @since 2.0.0
	 */
	private static function query_tabs( string $tab_type, array $args = [] ): array {
		global $wpdb;

		$where = $wpdb->prepare( 'tab_type = %s', $tab_type );

		if ( isset( $args['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $args['status'] );
		}

		if ( isset( $args['parent_id'] ) ) {
			$where .= $wpdb->prepare( ' AND parent_id = %d', (int) $args['parent_id'] );
		}

		if ( isset( $args['parent_existing_slug'] ) ) {
			$where .= $wpdb->prepare( ' AND parent_existing_slug = %s', $args['parent_existing_slug'] );
		}

		$allowed_orderby = [ 'id', 'label', 'slug', 'position', 'status', 'created_at', 'updated_at' ];
		$orderby         = isset( $args['orderby'] ) && in_array( $args['orderby'], $allowed_orderby, true )
			? $args['orderby']
			: 'position';

		$order  = isset( $args['order'] ) && 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
		$limit  = isset( $args['limit'] ) ? (int) $args['limit'] : 9999;
		$offset = isset( $args['offset'] ) ? (int) $args['offset'] : 0;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}bdm_tc_tabs WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
			$limit,
			$offset
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql ) ?: [];
	}

	/**
	 * Count tabs by type with optional filters.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tab_type
	 * @param array  $args
	 * @return int
	 */
	public static function get_tabs_count( string $tab_type, array $args = [] ): int {
		global $wpdb;

		$where = $wpdb->prepare( 'tab_type = %s', $tab_type );

		if ( isset( $args['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $args['status'] );
		}

		if ( isset( $args['parent_id'] ) ) {
			$where .= $wpdb->prepare( ' AND parent_id = %d', (int) $args['parent_id'] );
		}

		if ( isset( $args['parent_existing_slug'] ) ) {
			$where .= $wpdb->prepare( ' AND parent_existing_slug = %s', $args['parent_existing_slug'] );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}bdm_tc_tabs WHERE {$where}"
		);
	}

	/**
	 * Delete a tab and its rules.
	 *
	 * @since 2.0.0
	 *
	 * @param int $id Tab ID.
	 * @return bool
	 */
	public static function delete_tab( int $id ): bool {
		global $wpdb;

		self::delete_rules_for_tab( $id );

		$result = $wpdb->delete( self::get_tabs_table(), [ 'id' => $id ] );

		self::clear_cache();

		return false !== $result;
	}

	// -------------------------------------------------------------------------
	// Rules CRUD
	// -------------------------------------------------------------------------

	/**
	 * Insert a rule row.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data
	 * @return int|false
	 */
	public static function insert_rule( array $data ): int|false {
		global $wpdb;

		$result = $wpdb->insert( self::get_rules_table(), $data );

		if ( false === $result ) {
			return false;
		}

		self::clear_cache();

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get all rules for a tab.
	 *
	 * @since 2.0.0
	 *
	 * @param int $tab_id
	 * @return array
	 */
	public static function get_rules_for_tab( int $tab_id ): array {
		global $wpdb;

		$cache_key = 'rules_tab_' . $tab_id;

		if ( ! is_admin() && isset( self::$rules_cache[ $cache_key ] ) ) {
			return self::$rules_cache[ $cache_key ];
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$wpdb->prefix}bdm_tc_tab_rules WHERE tab_id = %d",
				$tab_id
			)
		) ?: [];

		if ( ! is_admin() ) {
			self::$rules_cache[ $cache_key ] = $results;
		}

		return $results;
	}

	/**
	 * Delete all rules for a tab.
	 *
	 * @since 2.0.0
	 *
	 * @param int $tab_id
	 * @return bool
	 */
	public static function delete_rules_for_tab( int $tab_id ): bool {
		global $wpdb;

		$result = $wpdb->delete( self::get_rules_table(), [ 'tab_id' => $tab_id ] );

		self::clear_cache();

		return false !== $result;
	}

	// -------------------------------------------------------------------------
	// Overrides CRUD
	// -------------------------------------------------------------------------

	/**
	 * Insert or update an existing-tab override row.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tab_type
	 * @param string $original_slug
	 * @param array  $data
	 * @return int|false Override ID or false on failure.
	 */
	public static function insert_or_update_override( string $tab_type, string $original_slug, array $data ): int|false {
		global $wpdb;

		$existing = self::get_override( $tab_type, $original_slug );

		$data['updated_at'] = current_time( 'mysql' );

		if ( $existing ) {
			$result = $wpdb->update(
				self::get_overrides_table(),
				$data,
				[ 'id' => (int) $existing->id ]
			);

			self::clear_cache();

			return false !== $result ? (int) $existing->id : false;
		}

		$data['tab_type']      = $tab_type;
		$data['original_slug'] = $original_slug;

		$result = $wpdb->insert( self::get_overrides_table(), $data );

		if ( false === $result ) {
			return false;
		}

		self::clear_cache();

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get an override row.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tab_type
	 * @param string $original_slug
	 * @return object|null
	 */
	public static function get_override( string $tab_type, string $original_slug ): object|null {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$wpdb->prefix}bdm_tc_existing_tab_overrides WHERE tab_type = %s AND original_slug = %s",
				$tab_type,
				$original_slug
			)
		);

		return $row ?? null;
	}

	/**
	 * Get all overrides for a tab type.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tab_type
	 * @return array
	 */
	public static function get_all_overrides( string $tab_type ): array {
		if ( is_admin() ) {
			return self::query_overrides( $tab_type );
		}

		$cache_key = 'overrides_' . $tab_type;

		if ( isset( self::$overrides_cache[ $cache_key ] ) ) {
			return self::$overrides_cache[ $cache_key ];
		}

		$results                              = self::query_overrides( $tab_type );
		self::$overrides_cache[ $cache_key ] = $results;

		return $results;
	}

	/**
	 * @since 2.0.0
	 */
	private static function query_overrides( string $tab_type ): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$wpdb->prefix}bdm_tc_existing_tab_overrides WHERE tab_type = %s",
				$tab_type
			)
		) ?: [];
	}

	/**
	 * Delete an override row and its rules.
	 *
	 * @since 2.0.0
	 *
	 * @param int $id Override ID.
	 * @return bool
	 */
	public static function delete_override( int $id ): bool {
		global $wpdb;

		self::delete_rules_for_override( $id );

		$result = $wpdb->delete( self::get_overrides_table(), [ 'id' => $id ] );

		self::clear_cache();

		return false !== $result;
	}

	// -------------------------------------------------------------------------
	// Override rules CRUD
	// -------------------------------------------------------------------------

	/**
	 * Insert an override rule row.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data
	 * @return int|false
	 */
	public static function insert_override_rule( array $data ): int|false {
		global $wpdb;

		$result = $wpdb->insert( self::get_override_rules_table(), $data );

		if ( false === $result ) {
			return false;
		}

		self::clear_cache();

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get all rules for an override.
	 *
	 * @since 2.0.0
	 *
	 * @param int $override_id
	 * @return array
	 */
	public static function get_rules_for_override( int $override_id ): array {
		global $wpdb;

		$cache_key = 'rules_override_' . $override_id;

		if ( ! is_admin() && isset( self::$rules_cache[ $cache_key ] ) ) {
			return self::$rules_cache[ $cache_key ];
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$wpdb->prefix}bdm_tc_existing_tab_override_rules WHERE override_id = %d",
				$override_id
			)
		) ?: [];

		if ( ! is_admin() ) {
			self::$rules_cache[ $cache_key ] = $results;
		}

		return $results;
	}

	/**
	 * Delete all rules for an override.
	 *
	 * @since 2.0.0
	 *
	 * @param int $override_id
	 * @return bool
	 */
	public static function delete_rules_for_override( int $override_id ): bool {
		global $wpdb;

		$result = $wpdb->delete( self::get_override_rules_table(), [ 'override_id' => $override_id ] );

		self::clear_cache();

		return false !== $result;
	}

	// -------------------------------------------------------------------------
	// Cache
	// -------------------------------------------------------------------------

	/**
	 * Clear in-memory caches.
	 *
	 * @since 2.0.0
	 */
	public static function clear_cache(): void {
		self::$tabs_cache      = [];
		self::$rules_cache     = [];
		self::$overrides_cache = [];
	}
}
