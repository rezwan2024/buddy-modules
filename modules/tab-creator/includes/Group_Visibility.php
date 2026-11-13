<?php
/**
 * Checks group tab availability and visibility on the front end.
 *
 * @since 2.0.0
 */

namespace BuddyModules\TabCreator;

defined( 'ABSPATH' ) || exit;

class Group_Visibility {

	/**
	 * Determines whether a tab is available on a specific group.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $tab_id          The tab ID.
	 * @param object $current_group   The current group object.
	 * @param int    $current_user_id The ID of the logged-in user (0 if logged out).
	 * @return bool
	 */
	public static function can_show_tab( int $tab_id, object $current_group, int $current_user_id ): bool {
		$rules = DB::get_rules_for_tab( $tab_id );

		$availability_rules = array_filter( $rules, fn( $r ) => 'availability' === $r->rule_type );

		if ( empty( $availability_rules ) ) {
			return true;
		}

		// Excluded groups always override everything else.
		foreach ( $availability_rules as $rule ) {
			if ( 'excluded_group_id' === $rule->rule_key && (int) $rule->rule_value === (int) $current_group->id ) {
				return false;
			}
		}

		// Scope rule: all → show everywhere; none → hide everywhere.
		foreach ( $availability_rules as $rule ) {
			if ( 'scope' === $rule->rule_key ) {
				if ( 'all' === $rule->rule_value ) {
					return true;
				}
				if ( 'none' === $rule->rule_value ) {
					return false;
				}
			}
		}

		// Group status rules.
		$group_status_rules = array_filter( $availability_rules, fn( $r ) => 'group_status' === $r->rule_key );
		if ( ! empty( $group_status_rules ) ) {
			$group_status = $current_group->status ?? '';
			$status_match = false;

			foreach ( $group_status_rules as $rule ) {
				if ( $rule->rule_value === $group_status ) {
					$status_match = true;
					break;
				}
			}

			if ( ! $status_match ) {
				return false;
			}
		}

		// Group ID rules (specific groups).
		$group_id_rules = array_filter( $availability_rules, fn( $r ) => 'group_id' === $r->rule_key );
		if ( ! empty( $group_id_rules ) ) {
			$id_match = false;

			foreach ( $group_id_rules as $rule ) {
				if ( (int) $rule->rule_value === (int) $current_group->id ) {
					$id_match = true;
					break;
				}
			}

			if ( ! $id_match ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determines whether the current user can see a tab on a group page.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $tab_id          The tab ID.
	 * @param object $current_group   The current group object.
	 * @param int    $current_user_id The ID of the logged-in user (0 if logged out).
	 * @return bool
	 */
	public static function can_view_tab( int $tab_id, object $current_group, int $current_user_id ): bool {
		$rules = DB::get_rules_for_tab( $tab_id );

		$visibility_rules = array_filter( $rules, fn( $r ) => 'visibility' === $r->rule_type );

		if ( empty( $visibility_rules ) ) {
			return true;
		}

		foreach ( $visibility_rules as $rule ) {
			// Skip admin-context meta-values.
			if ( 'audience' === $rule->rule_key && 'do_not_modify' === $rule->rule_value ) {
				continue;
			}

			// Skip inherit — handled by the caller.
			if ( 'inherit' === $rule->rule_key ) {
				continue;
			}

			if ( 'audience' === $rule->rule_key ) {
				switch ( $rule->rule_value ) {
					case 'anyone':
						return true;

					case 'logged_in':
						if ( is_user_logged_in() ) {
							return true;
						}
						break;

					case 'members':
						if ( $current_user_id > 0 && function_exists( 'groups_is_user_member' ) ) {
							if ( groups_is_user_member( (int) $current_group->id, $current_user_id ) ) {
								return true;
							}
						}
						break;

					case 'mods':
						if ( $current_user_id > 0 && function_exists( 'groups_is_user_mod' ) ) {
							if ( groups_is_user_mod( (int) $current_group->id, $current_user_id ) ) {
								return true;
							}
						}
						break;

					case 'admins':
						if ( $current_user_id > 0 && function_exists( 'groups_is_user_admin' ) ) {
							if ( groups_is_user_admin( (int) $current_group->id, $current_user_id ) ) {
								return true;
							}
						}
						break;

					case 'site_admin':
						if ( current_user_can( 'manage_options' ) ) {
							return true;
						}
						break;
				}
			}
		}

		return false;
	}
}
