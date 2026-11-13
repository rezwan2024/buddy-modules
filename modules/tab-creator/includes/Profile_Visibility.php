<?php
/**
 * Checks profile tab availability and visibility on the front end.
 *
 * @since 2.0.0
 */

namespace BuddyModules\TabCreator;

defined( 'ABSPATH' ) || exit;

class Profile_Visibility {

	/**
	 * Determines whether a tab is available on a specific user's profile.
	 *
	 * @since 2.0.0
	 *
	 * @param int $tab_id           The tab ID.
	 * @param int $displayed_user_id The ID of the profile being viewed.
	 * @return bool
	 */
	public static function can_use_tab( int $tab_id, int $displayed_user_id ): bool {
		$rules = DB::get_rules_for_tab( $tab_id );

		$availability_rules = array_filter( $rules, fn( $r ) => 'availability' === $r->rule_type );

		if ( empty( $availability_rules ) ) {
			return true;
		}

		foreach ( $availability_rules as $rule ) {
			if ( 'scope' === $rule->rule_key ) {
				if ( 'all_members' === $rule->rule_value ) {
					return true;
				}
				if ( 'none' === $rule->rule_value ) {
					return false;
				}
			}

			if ( 'role' === $rule->rule_key ) {
				if ( user_can( $displayed_user_id, $rule->rule_value ) ) {
					return true;
				}
			}

			if ( 'user_id' === $rule->rule_key ) {
				if ( (int) $rule->rule_value === $displayed_user_id ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Determines whether the current visitor can see a tab on a profile.
	 *
	 * @since 2.0.0
	 *
	 * @param int $tab_id            The tab ID.
	 * @param int $displayed_user_id The ID of the profile being viewed.
	 * @param int $current_user_id   The ID of the logged-in visitor (0 if logged out).
	 * @return bool
	 */
	public static function can_view_tab( int $tab_id, int $displayed_user_id, int $current_user_id ): bool {
		$rules = DB::get_rules_for_tab( $tab_id );

		$visibility_rules = array_filter( $rules, fn( $r ) => 'visibility' === $r->rule_type );

		if ( empty( $visibility_rules ) ) {
			return true;
		}

		foreach ( $visibility_rules as $rule ) {
			// Skip admin-only flags.
			if ( 'audience' === $rule->rule_key && in_array( $rule->rule_value, [ 'do_not_modify', 'none_remove' ], true ) ) {
				continue;
			}

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

					case 'owner_only':
						if ( $current_user_id > 0 && $current_user_id === $displayed_user_id ) {
							return true;
						}
						break;

					case 'site_admin':
						if ( current_user_can( 'manage_options' ) ) {
							return true;
						}
						break;

					case 'friends':
						if ( $current_user_id > 0 && function_exists( 'friends_check_friendship' ) ) {
							if ( friends_check_friendship( $displayed_user_id, $current_user_id ) ) {
								return true;
							}
						}
						break;
				}
			}

			if ( 'role' === $rule->rule_key ) {
				if ( $current_user_id > 0 && user_can( $current_user_id, $rule->rule_value ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
