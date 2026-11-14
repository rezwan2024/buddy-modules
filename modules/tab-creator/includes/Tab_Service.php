<?php
/**
 * Business logic for Tab Creator: save, delete, reorder.
 *
 * No HTML, no redirects, no nonces — pure data manipulation via DB.
 * REST controllers call these methods after sanitizing request data.
 *
 * @package BuddyModules\TabCreator
 * @since 2.0.0
 */

namespace BuddyModules\TabCreator;

defined( 'ABSPATH' ) || exit;

/**
 * Saves, deletes and reorders Tab Creator tabs and overrides.
 *
 * @since 2.0.0
 */
class Tab_Service {

	// -------------------------------------------------------------------------
	// Custom tabs
	// -------------------------------------------------------------------------

	/**
	 * Insert or update a custom tab (profile or group) and rebuild its rules.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tab_type 'profile' or 'group'.
	 * @param array  $fields   Sanitized tab fields plus a 'rules' sub-array.
	 * @param int    $tab_id   0 to insert, existing ID to update.
	 * @return int|\WP_Error   New/updated tab ID, or WP_Error on failure.
	 */
	public static function save_tab( string $tab_type, array $fields, int $tab_id = 0 ): int|\WP_Error {
		$is_new = ( 0 === $tab_id );

		if ( $is_new && empty( $fields['label'] ) ) {
			return new \WP_Error(
				'bdm_tc_label_required',
				__( 'Tab label is required.', 'buddy-modules' ),
				array( 'status' => 400 )
			);
		}

		// Auto-assign position for new tabs.
		if ( $is_new && ! isset( $fields['position'] ) ) {
			$last     = DB::get_tabs(
				$tab_type,
				array(
					'orderby' => 'position',
					'order'   => 'DESC',
					'limit'   => 1,
				)
			);
			$position = ! empty( $last ) ? ( (int) $last[0]->position + 10 ) : 10;
		} else {
			$position = (int) ( $fields['position'] ?? 10 );
		}

		$tab_data = array(
			'is_enabled'      => (int) ( $fields['is_enabled'] ?? 1 ),
			'is_predefined'   => (int) ( $fields['is_predefined'] ?? 0 ),
			'site_admin_only' => (int) ( $fields['site_admin_only'] ?? 0 ),
			'is_default'      => (int) ( $fields['is_default'] ?? 0 ),
			'label'           => sanitize_text_field( $fields['label'] ?? '' ),
			'position'        => $position,
			'tab_link'        => esc_url_raw( $fields['tab_link'] ?? '' ),
			'item_css_id'     => sanitize_html_class( $fields['item_css_id'] ?? '' ),
			'content_source'  => in_array( $fields['content_source'] ?? 'content', array( 'content', 'url' ), true )
				? ( $fields['content_source'] ?? 'content' )
				: 'content',
			'content'         => wp_kses_post( $fields['content'] ?? '' ),
			'external_url'    => esc_url_raw( $fields['external_url'] ?? '' ),
			'url_new_tab'     => (int) ( $fields['url_new_tab'] ?? 0 ),
			'status'          => in_array( $fields['status'] ?? 'active', array( 'active', 'inactive' ), true )
				? ( $fields['status'] ?? 'active' )
				: 'active',
			'updated_at'      => current_time( 'mysql' ),
		);

		if ( 'profile' === $tab_type ) {
			$tab_data['default_subnav_slug'] = sanitize_key( $fields['default_subnav_slug'] ?? '' );
			$tab_data['add_to_admin_bar']    = (int) ( $fields['add_to_admin_bar'] ?? 0 );
			$tab_data['administer_item_id']  = sanitize_text_field( $fields['administer_item_id'] ?? '' );
		}

		// On update, write only the fields the request sent. Fields the editor
		// doesn't show (position, tab link, item CSS ID, …) keep their stored value.
		if ( ! $is_new ) {
			$tab_data = array_intersect_key( $tab_data, $fields );

			$tab_data['updated_at'] = current_time( 'mysql' );
		}

		if ( $is_new ) {
			$slug = sanitize_title( $fields['slug'] ?? '' );
			if ( '' === $slug ) {
				$slug = sanitize_title( $fields['label'] ?? 'tab' );
			}

			$parent_id = absint( $fields['parent_id'] ?? 0 );

			$slug_error = self::validate_new_slug( $tab_type, $slug, $parent_id );
			if ( is_wp_error( $slug_error ) ) {
				return $slug_error;
			}

			$tab_data['tab_type']   = $tab_type;
			$tab_data['parent_id']  = $parent_id;
			$tab_data['slug']       = $slug;
			$tab_data['created_at'] = current_time( 'mysql' );

			$tab_id = DB::insert_tab( $tab_data );

			if ( ! $tab_id ) {
				return new \WP_Error(
					'bdm_tc_insert_failed',
					__( 'Failed to save tab. Please try again.', 'buddy-modules' ),
					array( 'status' => 500 )
				);
			}
		} else {
			DB::update_tab( $tab_id, $tab_data );
		}

		if ( $is_new || isset( $fields['rules'] ) ) {
			self::rebuild_tab_rules( $tab_type, $tab_id, $fields['rules'] ?? array() );
		}

		DB::clear_cache();

		return $tab_id;
	}

	/**
	 * Insert or update a subtab (child tab) and rebuild its rules.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tab_type   'profile' or 'group'.
	 * @param array  $fields     Sanitized subtab fields. Must include 'parent_id'.
	 * @param int    $subtab_id  0 to insert, existing ID to update.
	 * @return int|\WP_Error
	 */
	public static function save_subtab( string $tab_type, array $fields, int $subtab_id = 0 ): int|\WP_Error {
		$is_new    = ( 0 === $subtab_id );
		$parent_id = (int) ( $fields['parent_id'] ?? 0 );
		$label     = sanitize_text_field( $fields['label'] ?? '' );

		if ( '' === $label ) {
			return new \WP_Error(
				'bdm_tc_label_required',
				__( 'Subtab label is required.', 'buddy-modules' ),
				array( 'status' => 400 )
			);
		}

		$subtab_data = array(
			'tab_type'        => $tab_type,
			'parent_id'       => $parent_id,
			'label'           => $label,
			'is_enabled'      => (int) ( $fields['is_enabled'] ?? 1 ),
			'is_predefined'   => 0,
			'site_admin_only' => (int) ( $fields['site_admin_only'] ?? 0 ),
			'position'        => (int) ( $fields['position'] ?? 10 ),
			'tab_link'        => esc_url_raw( $fields['tab_link'] ?? '' ),
			'item_css_id'     => sanitize_html_class( $fields['item_css_id'] ?? '' ),
			'content'         => wp_kses_post( $fields['content'] ?? '' ),
			'content_source'  => 'content',
			'status'          => 'active',
			'is_default'      => 0,
			'updated_at'      => current_time( 'mysql' ),
		);

		if ( 'profile' === $tab_type ) {
			$subtab_data['add_to_admin_bar']   = (int) ( $fields['add_to_admin_bar'] ?? 0 );
			$subtab_data['administer_item_id'] = sanitize_text_field( $fields['administer_item_id'] ?? '' );
		}

		if ( $is_new ) {
			$slug = sanitize_title( $fields['slug'] ?? '' );
			if ( '' === $slug ) {
				$slug = sanitize_title( $label );
			}
			$subtab_data['slug']       = $slug;
			$subtab_data['created_at'] = current_time( 'mysql' );

			$subtab_id = DB::insert_tab( $subtab_data );

			if ( ! $subtab_id ) {
				return new \WP_Error(
					'bdm_tc_insert_failed',
					__( 'Failed to save subtab. Please try again.', 'buddy-modules' ),
					array( 'status' => 500 )
				);
			}
		} else {
			DB::update_tab( $subtab_id, $subtab_data );
		}

		self::rebuild_tab_rules( $tab_type, $subtab_id, $fields['rules'] ?? array() );

		DB::clear_cache();

		return $subtab_id;
	}

	/**
	 * Insert or update an existing-tab override and rebuild its rules.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tab_type 'profile' or 'group'.
	 * @param string $slug     The built-in BP slug.
	 * @param array  $fields   Override fields plus a 'rules' sub-array.
	 * @return int|\WP_Error   Override ID or WP_Error.
	 */
	public static function save_override( string $tab_type, string $slug, array $fields ): int|\WP_Error {
		$label_override = sanitize_text_field( $fields['label_override'] ?? '' );

		$override_data = array(
			'is_enabled'      => (int) ( $fields['is_enabled'] ?? 1 ),
			'is_predefined'   => (int) ( $fields['is_predefined'] ?? 1 ),
			'label_override'  => '' !== $label_override ? $label_override : null,
			'is_default'      => (int) ( $fields['is_default'] ?? 0 ),
			'is_hidden'       => (int) ( $fields['is_hidden'] ?? 0 ),
			'site_admin_only' => (int) ( $fields['site_admin_only'] ?? 0 ),
			'tab_link'        => esc_url_raw( $fields['tab_link'] ?? '' ),
			'item_css_id'     => sanitize_html_class( $fields['item_css_id'] ?? '' ),
			'updated_at'      => current_time( 'mysql' ),
		);

		if ( 'group' === $tab_type ) {
			$pos_raw                            = $fields['position_override'] ?? null;
			$override_data['position_override'] = ( null !== $pos_raw && '' !== $pos_raw )
				? absint( $pos_raw )
				: null;
		}

		// An existing override keeps every field the request didn't send
		// (the editor has no Default, Site Admin Only, Tab Link, … fields).
		$existing = DB::get_override( $tab_type, $slug );
		if ( $existing ) {
			$override_data = array_intersect_key( $override_data, $fields );

			$override_data['updated_at'] = current_time( 'mysql' );
		}

		$kept_visibility = array();
		if ( $existing && ! array_key_exists( 'visibility', $fields['rules'] ?? array() ) ) {
			$kept_visibility = array_filter(
				DB::get_rules_for_override( (int) $existing->id ),
				fn( $rule ) => 'visibility' === $rule->rule_type
			);
		}

		$override_id = DB::insert_or_update_override( $tab_type, $slug, $override_data );

		if ( ! $override_id ) {
			return new \WP_Error(
				'bdm_tc_override_failed',
				__( 'Failed to save override. Please try again.', 'buddy-modules' ),
				array( 'status' => 500 )
			);
		}

		DB::delete_rules_for_override( $override_id );

		self::rebuild_override_rules( $tab_type, $override_id, $fields['rules'] ?? array() );

		foreach ( $kept_visibility as $rule ) {
			DB::insert_override_rule(
				array(
					'override_id' => $override_id,
					'rule_type'   => $rule->rule_type,
					'rule_key'    => $rule->rule_key,
					'rule_value'  => $rule->rule_value,
				)
			);
		}

		DB::clear_cache();

		return $override_id;
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
		return DB::delete_tab( $id );
	}

	/**
	 * Save drag-and-drop order for custom tabs.
	 *
	 * @since 2.0.0
	 *
	 * @param array $ordered_ids Tab IDs in new display order.
	 */
	public static function reorder_tabs( array $ordered_ids ): void {
		foreach ( $ordered_ids as $index => $tab_id ) {
			$tab_id = (int) $tab_id;
			if ( $tab_id > 0 ) {
				DB::update_tab( $tab_id, array( 'position' => ( $index + 1 ) * 10 ) );
			}
		}

		DB::clear_cache();
	}

	/**
	 * Save drag-and-drop order for existing BP tabs.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tab_type 'profile' or 'group'.
	 * @param array  $slugs    Slugs in new order (validated against known slugs).
	 * @return bool  True when order saved, false when slugs are invalid.
	 */
	public static function save_existing_tab_order( string $tab_type, array $slugs ): bool {
		if ( 'group' === $tab_type ) {
			$known = Builtin_Tabs::get_known_group_slugs();
		} else {
			$known = Builtin_Tabs::get_known_profile_slugs();
		}

		$slugs = array_values( array_intersect( $slugs, $known ) );

		$option_key = 'profile' === $tab_type
			? 'bdm_tc_existing_profile_tab_order'
			: 'bdm_tc_existing_group_tab_order';

		update_option( $option_key, $slugs, false );

		return true;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Check that a new tab's slug doesn't clash with a BuddyBoss Platform nav
	 * item or with another custom tab at the same level.
	 *
	 * A main tab with a Platform slug (e.g. "activity", "members") would share
	 * the URL of the built-in tab; two siblings with one slug would share a URL.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tab_type  'profile' or 'group'.
	 * @param string $slug      Sanitized slug.
	 * @param int    $parent_id Parent custom tab ID, 0 for a main tab.
	 * @return true|\WP_Error True when the slug is free.
	 */
	private static function validate_new_slug( string $tab_type, string $slug, int $parent_id ) {
		if ( 0 === $parent_id ) {
			$builtin = 'group' === $tab_type
				? Builtin_Tabs::get_known_group_slugs()
				: Builtin_Tabs::get_known_profile_slugs();

			// Owner-only profile tabs (Settings, Messages, Notifications, …) are not
			// in the known list, so also reserve every active component's slug.
			if ( 'profile' === $tab_type && function_exists( 'buddypress' ) ) {
				foreach ( array_keys( (array) buddypress()->active_components ) as $component ) {
					if ( ! empty( buddypress()->{$component}->slug ) ) {
						$builtin[] = buddypress()->{$component}->slug;
					}
				}
			}

			if ( in_array( $slug, $builtin, true ) ) {
				return new \WP_Error(
					'bdm_tc_slug_builtin',
					sprintf(
						/* translators: %s: tab slug. */
						__( 'The slug "%s" is used by a built-in BuddyBoss tab. Please choose another slug.', 'buddy-modules' ),
						$slug
					),
					array( 'status' => 400 )
				);
			}
		}

		foreach ( DB::get_tabs( $tab_type, array( 'parent_id' => $parent_id ) ) as $sibling ) {
			if ( $sibling->slug === $slug ) {
				return new \WP_Error(
					'bdm_tc_slug_exists',
					sprintf(
						/* translators: %s: tab slug. */
						__( 'Another tab already uses the slug "%s". Please choose another slug.', 'buddy-modules' ),
						$slug
					),
					array( 'status' => 400 )
				);
			}
		}

		return true;
	}

	/**
	 * Rebuild rules for a custom tab from a structured rules array.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tab_type 'profile' or 'group'.
	 * @param int    $tab_id   Tab ID.
	 * @param array  $rules    Rules keyed by 'availability'|'enabled_for', 'visibility' and 'associated_users'|'associated_groups'|'excluded_groups', each a list of values.
	 */
	private static function rebuild_tab_rules( string $tab_type, int $tab_id, array $rules ): void {
		// The editor has no Visibility field: keep stored visibility rules unless the request sends them.
		$kept_visibility = array();
		if ( ! array_key_exists( 'visibility', $rules ) ) {
			$kept_visibility = array_filter(
				DB::get_rules_for_tab( $tab_id ),
				fn( $rule ) => 'visibility' === $rule->rule_type
			);
		}

		DB::delete_rules_for_tab( $tab_id );

		if ( 'profile' === $tab_type ) {
			self::write_profile_tab_rules( $tab_id, $rules );
		} else {
			self::write_group_tab_rules( $tab_id, $rules );
		}

		foreach ( $kept_visibility as $rule ) {
			DB::insert_rule(
				array(
					'tab_id'     => $tab_id,
					'rule_type'  => $rule->rule_type,
					'rule_key'   => $rule->rule_key,
					'rule_value' => $rule->rule_value,
				)
			);
		}
	}

	/**
	 * Write profile tab rules (availability, visibility, associated users).
	 *
	 * @since 2.0.0
	 *
	 * @param int   $tab_id Tab ID.
	 * @param array $rules  Rules from the request, keyed by rule type.
	 */
	private static function write_profile_tab_rules( int $tab_id, array $rules ): void {
		$wp_role_slugs = array_keys( wp_roles()->roles );

		// Availability.
		$allowed_avail = array_merge( array( 'all_members', 'none_wp' ), $wp_role_slugs );
		$availability  = array_filter(
			array_map( 'sanitize_key', (array) ( $rules['availability'] ?? array() ) ),
			fn( $v ) => in_array( $v, $allowed_avail, true )
		);

		foreach ( $availability as $avail_val ) {
			if ( 'all_members' === $avail_val ) {
				DB::insert_rule(
					array(
						'tab_id'     => $tab_id,
						'rule_type'  => 'availability',
						'rule_key'   => 'scope',
						'rule_value' => 'all_members',
					)
				);
			} elseif ( 'none_wp' === $avail_val ) {
				DB::insert_rule(
					array(
						'tab_id'     => $tab_id,
						'rule_type'  => 'availability',
						'rule_key'   => 'scope',
						'rule_value' => 'none',
					)
				);
			} elseif ( in_array( $avail_val, $wp_role_slugs, true ) ) {
				DB::insert_rule(
					array(
						'tab_id'     => $tab_id,
						'rule_type'  => 'availability',
						'rule_key'   => 'role',
						'rule_value' => $avail_val,
					)
				);
			}
		}

		// Associated users.
		$assoc_users = array_values( array_filter( array_map( 'absint', (array) ( $rules['associated_users'] ?? array() ) ) ) );
		foreach ( $assoc_users as $user_id ) {
			if ( $user_id > 0 ) {
				DB::insert_rule(
					array(
						'tab_id'     => $tab_id,
						'rule_type'  => 'availability',
						'rule_key'   => 'user_id',
						'rule_value' => (string) $user_id,
					)
				);
			}
		}

		// Visibility.
		$known_audiences = array( 'do_not_modify', 'anyone', 'none_remove', 'logged_in', 'owner_only', 'friends', 'site_admin' );
		$allowed_vis     = array_merge( $known_audiences, $wp_role_slugs );
		$visibility      = array_filter(
			array_map( 'sanitize_key', (array) ( $rules['visibility'] ?? array() ) ),
			fn( $v ) => in_array( $v, $allowed_vis, true )
		);

		foreach ( $visibility as $vis_val ) {
			if ( in_array( $vis_val, $known_audiences, true ) ) {
				DB::insert_rule(
					array(
						'tab_id'     => $tab_id,
						'rule_type'  => 'visibility',
						'rule_key'   => 'audience',
						'rule_value' => $vis_val,
					)
				);
			} elseif ( in_array( $vis_val, $wp_role_slugs, true ) ) {
				DB::insert_rule(
					array(
						'tab_id'     => $tab_id,
						'rule_type'  => 'visibility',
						'rule_key'   => 'role',
						'rule_value' => $vis_val,
					)
				);
			}
		}
	}

	/**
	 * Write group tab rules (enabled_for, visibility, associated/excluded groups).
	 *
	 * @since 2.0.0
	 *
	 * @param int   $tab_id Tab ID.
	 * @param array $rules  Rules from the request, keyed by rule type.
	 */
	private static function write_group_tab_rules( int $tab_id, array $rules ): void {
		// Enabled for (availability).
		$allowed_ef  = array( 'all', 'public', 'private', 'hidden', 'none' );
		$enabled_for = array_filter(
			array_map( 'sanitize_key', (array) ( $rules['enabled_for'] ?? array() ) ),
			fn( $v ) => in_array( $v, $allowed_ef, true )
		);

		foreach ( $enabled_for as $ef_val ) {
			if ( 'all' === $ef_val ) {
				DB::insert_rule(
					array(
						'tab_id'     => $tab_id,
						'rule_type'  => 'availability',
						'rule_key'   => 'scope',
						'rule_value' => 'all',
					)
				);
			} elseif ( 'none' === $ef_val ) {
				DB::insert_rule(
					array(
						'tab_id'     => $tab_id,
						'rule_type'  => 'availability',
						'rule_key'   => 'scope',
						'rule_value' => 'none',
					)
				);
			} elseif ( in_array( $ef_val, array( 'public', 'private', 'hidden' ), true ) ) {
				DB::insert_rule(
					array(
						'tab_id'     => $tab_id,
						'rule_type'  => 'availability',
						'rule_key'   => 'group_status',
						'rule_value' => $ef_val,
					)
				);
			}
		}

		// Associated groups.
		$assoc_groups = array_values( array_filter( array_map( 'absint', (array) ( $rules['associated_groups'] ?? array() ) ) ) );
		foreach ( $assoc_groups as $group_id ) {
			if ( $group_id > 0 ) {
				DB::insert_rule(
					array(
						'tab_id'     => $tab_id,
						'rule_type'  => 'availability',
						'rule_key'   => 'group_id',
						'rule_value' => (string) $group_id,
					)
				);
			}
		}

		// Excluded groups.
		$excl_groups = array_values( array_filter( array_map( 'absint', (array) ( $rules['excluded_groups'] ?? array() ) ) ) );
		foreach ( $excl_groups as $group_id ) {
			if ( $group_id > 0 ) {
				DB::insert_rule(
					array(
						'tab_id'     => $tab_id,
						'rule_type'  => 'availability',
						'rule_key'   => 'excluded_group_id',
						'rule_value' => (string) $group_id,
					)
				);
			}
		}

		// Visibility.
		$allowed_vis = array( 'do_not_modify', 'anyone', 'logged_in', 'members', 'mods', 'admins', 'site_admin' );
		$visibility  = array_filter(
			array_map( 'sanitize_key', (array) ( $rules['visibility'] ?? array() ) ),
			fn( $v ) => in_array( $v, $allowed_vis, true )
		);

		foreach ( $visibility as $vis_val ) {
			DB::insert_rule(
				array(
					'tab_id'     => $tab_id,
					'rule_type'  => 'visibility',
					'rule_key'   => 'audience',
					'rule_value' => $vis_val,
				)
			);
		}
	}

	/**
	 * Rebuild override rules from a structured rules array.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tab_type    'profile' or 'group'.
	 * @param int    $override_id Override ID.
	 * @param array  $rules       Rules from the request, keyed by rule type.
	 */
	private static function rebuild_override_rules( string $tab_type, int $override_id, array $rules ): void {
		if ( 'profile' === $tab_type ) {
			self::write_profile_override_rules( $override_id, $rules );
		} else {
			self::write_group_override_rules( $override_id, $rules );
		}
	}

	/**
	 * Write profile override rules.
	 *
	 * @since 2.0.0
	 *
	 * @param int   $override_id Override ID.
	 * @param array $rules       Rules from the request, keyed by rule type.
	 */
	private static function write_profile_override_rules( int $override_id, array $rules ): void {
		$wp_role_slugs = array_keys( wp_roles()->roles );

		// Availability.
		$avail_raw = $rules['availability'] ?? null;
		if ( ! empty( $avail_raw ) ) {
			$allowed_avail = array_merge( array( 'all_members', 'none_wp' ), $wp_role_slugs );
			$availability  = array_filter(
				array_map( 'sanitize_key', (array) $avail_raw ),
				fn( $v ) => in_array( $v, $allowed_avail, true )
			);

			foreach ( $availability as $avail_val ) {
				if ( 'all_members' === $avail_val ) {
					DB::insert_override_rule(
						array(
							'override_id' => $override_id,
							'rule_type'   => 'availability',
							'rule_key'    => 'scope',
							'rule_value'  => 'all_members',
						)
					);
				} elseif ( 'none_wp' === $avail_val ) {
					DB::insert_override_rule(
						array(
							'override_id' => $override_id,
							'rule_type'   => 'availability',
							'rule_key'    => 'scope',
							'rule_value'  => 'none',
						)
					);
				} elseif ( in_array( $avail_val, $wp_role_slugs, true ) ) {
					DB::insert_override_rule(
						array(
							'override_id' => $override_id,
							'rule_type'   => 'availability',
							'rule_key'    => 'role',
							'rule_value'  => $avail_val,
						)
					);
				}
			}
		}

		// Visibility.
		$vis_raw = $rules['visibility'] ?? null;
		if ( ! empty( $vis_raw ) ) {
			$known_audiences = array( 'do_not_modify', 'anyone', 'none_remove', 'logged_in', 'owner_only', 'friends', 'site_admin' );
			$allowed_vis     = array_merge( $known_audiences, $wp_role_slugs );
			$visibility      = array_filter(
				array_map( 'sanitize_key', (array) $vis_raw ),
				fn( $v ) => in_array( $v, $allowed_vis, true )
			);

			foreach ( $visibility as $vis_val ) {
				if ( in_array( $vis_val, $known_audiences, true ) ) {
					DB::insert_override_rule(
						array(
							'override_id' => $override_id,
							'rule_type'   => 'visibility',
							'rule_key'    => 'audience',
							'rule_value'  => $vis_val,
						)
					);
				} elseif ( in_array( $vis_val, $wp_role_slugs, true ) ) {
					DB::insert_override_rule(
						array(
							'override_id' => $override_id,
							'rule_type'   => 'visibility',
							'rule_key'    => 'role',
							'rule_value'  => $vis_val,
						)
					);
				}
			}
		}
	}

	/**
	 * Write group override rules.
	 *
	 * @since 2.0.0
	 *
	 * @param int   $override_id Override ID.
	 * @param array $rules       Rules from the request, keyed by rule type.
	 */
	private static function write_group_override_rules( int $override_id, array $rules ): void {
		// Enabled for (availability).
		$ef_raw = $rules['enabled_for'] ?? null;
		if ( ! empty( $ef_raw ) ) {
			$allowed_ef  = array( 'all', 'public', 'private', 'hidden', 'none' );
			$enabled_for = array_filter(
				array_map( 'sanitize_key', (array) $ef_raw ),
				fn( $v ) => in_array( $v, $allowed_ef, true )
			);

			foreach ( $enabled_for as $ef_val ) {
				if ( 'all' === $ef_val ) {
					DB::insert_override_rule(
						array(
							'override_id' => $override_id,
							'rule_type'   => 'availability',
							'rule_key'    => 'scope',
							'rule_value'  => 'all',
						)
					);
				} elseif ( 'none' === $ef_val ) {
					DB::insert_override_rule(
						array(
							'override_id' => $override_id,
							'rule_type'   => 'availability',
							'rule_key'    => 'scope',
							'rule_value'  => 'none',
						)
					);
				} elseif ( in_array( $ef_val, array( 'public', 'private', 'hidden' ), true ) ) {
					DB::insert_override_rule(
						array(
							'override_id' => $override_id,
							'rule_type'   => 'availability',
							'rule_key'    => 'group_status',
							'rule_value'  => $ef_val,
						)
					);
				}
			}
		}

		// Associated groups.
		$assoc_groups = array_values( array_filter( array_map( 'absint', (array) ( $rules['associated_groups'] ?? array() ) ) ) );
		foreach ( $assoc_groups as $group_id ) {
			if ( $group_id > 0 ) {
				DB::insert_override_rule(
					array(
						'override_id' => $override_id,
						'rule_type'   => 'availability',
						'rule_key'    => 'group_id',
						'rule_value'  => (string) $group_id,
					)
				);
			}
		}

		// Excluded groups.
		$excl_groups = array_values( array_filter( array_map( 'absint', (array) ( $rules['excluded_groups'] ?? array() ) ) ) );
		foreach ( $excl_groups as $group_id ) {
			if ( $group_id > 0 ) {
				DB::insert_override_rule(
					array(
						'override_id' => $override_id,
						'rule_type'   => 'availability',
						'rule_key'    => 'excluded_group_id',
						'rule_value'  => (string) $group_id,
					)
				);
			}
		}

		// Visibility.
		$vis_raw = $rules['visibility'] ?? null;
		if ( ! empty( $vis_raw ) ) {
			$allowed_vis = array( 'do_not_modify', 'anyone', 'logged_in', 'members', 'mods', 'admins', 'site_admin' );
			$visibility  = array_filter(
				array_map( 'sanitize_key', (array) $vis_raw ),
				fn( $v ) => in_array( $v, $allowed_vis, true )
			);

			foreach ( $visibility as $vis_val ) {
				DB::insert_override_rule(
					array(
						'override_id' => $override_id,
						'rule_type'   => 'visibility',
						'rule_key'    => 'audience',
						'rule_value'  => $vis_val,
					)
				);
			}
		}
	}
}
