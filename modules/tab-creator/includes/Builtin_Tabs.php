<?php
/**
 * Helpers for discovering built-in BuddyBoss profile and group nav tabs.
 *
 * Labels and slugs are cached from the front end via transients. These helpers
 * are used by the REST controllers and the legacy admin list screens.
 *
 * @package BuddyModules\TabCreator
 * @since 2.0.0
 */

namespace BuddyModules\TabCreator;

defined( 'ABSPATH' ) || exit;

/**
 * Helpers that list the built-in BuddyBoss profile and group nav tabs.
 *
 * @since 2.0.0
 */
class Builtin_Tabs {

	// -------------------------------------------------------------------------
	// Profile tabs
	// -------------------------------------------------------------------------

	/**
	 * Returns built-in BuddyBoss profile nav slugs that are active and
	 * visible to any visitor (not just the profile owner).
	 *
	 * Primary source: transient populated on the front end at bp_setup_nav
	 * priority 15. Fallback: bp_is_active() checks.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int,string>
	 */
	public static function get_known_profile_slugs(): array {
		$cached = get_transient( 'bdm_tc_builtin_profile_nav_v2' );
		if ( ! empty( $cached ) && is_array( $cached ) ) {
			return (array) apply_filters( 'bdm_tc_known_profile_nav_slugs', $cached );
		}

		// Fallback: derive from active components (owner-only tabs excluded).
		$slugs = array();

		if ( function_exists( 'bp_is_active' ) ) {
			if ( bp_is_active( 'activity' ) ) {
				$slugs[] = 'activity';
			}
			if ( bp_is_active( 'xprofile' ) ) {
				$slugs[] = 'profile';
			}
			if ( bp_is_active( 'friends' ) ) {
				$slugs[] = 'friends';
			}
			if ( bp_is_active( 'groups' ) ) {
				$slugs[] = 'groups';
			}
			if ( bp_is_active( 'forums' ) ) {
				$slugs[] = 'forums';
			}

			if ( bp_is_active( 'media' ) ) {
				$on = ! function_exists( 'bp_is_profile_media_support_enabled' )
					|| bp_is_profile_media_support_enabled();
				if ( $on ) {
					$slugs[] = 'media';
				}
			}

			if ( bp_is_active( 'video' ) ) {
				$on = ! function_exists( 'bp_is_profile_video_support_enabled' )
					|| bp_is_profile_video_support_enabled();
				if ( $on ) {
					$slugs[] = 'video';
				}
			}

			if ( bp_is_active( 'document' ) ) {
				$on = ! function_exists( 'bp_is_profile_document_support_enabled' )
					|| bp_is_profile_document_support_enabled();
				if ( $on ) {
					$slugs[] = 'document';
				}
			}
		}

		return (array) apply_filters( 'bdm_tc_known_profile_nav_slugs', $slugs );
	}

	/**
	 * Returns a slug => label map for built-in BuddyBoss profile nav items.
	 *
	 * Primary source: transient written on the first frontend profile page visit.
	 * Fallback: BuddyBoss default tab labels.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string,string>
	 */
	public static function get_profile_nav_labels(): array {
		$cached = get_transient( 'bdm_tc_builtin_profile_nav_labels' );
		if ( ! empty( $cached ) && is_array( $cached ) ) {
			return $cached;
		}

		return array(
			'activity' => __( 'Timeline', 'buddy-modules' ),
			'profile'  => __( 'Profile', 'buddy-modules' ),
			'friends'  => __( 'Connections', 'buddy-modules' ),
			'groups'   => __( 'Groups', 'buddy-modules' ),
			'forums'   => __( 'Forums', 'buddy-modules' ),
			'media'    => __( 'Photos', 'buddy-modules' ),
			'video'    => __( 'Videos', 'buddy-modules' ),
			'document' => __( 'Documents', 'buddy-modules' ),
		);
	}

	// -------------------------------------------------------------------------
	// Group tabs
	// -------------------------------------------------------------------------

	/**
	 * Returns built-in BuddyBoss group nav slugs.
	 *
	 * Primary source: transient populated from the front end. Fallback: bp_is_active().
	 *
	 * @since 2.0.0
	 *
	 * @return array<int,string>
	 */
	public static function get_known_group_slugs(): array {
		$cached = get_transient( 'bdm_tc_builtin_group_nav_v5' );
		if ( ! empty( $cached ) && is_array( $cached ) ) {
			return (array) apply_filters( 'bdm_tc_known_group_nav_slugs', $cached );
		}

		$slugs = array( 'feed', 'members' );

		if ( function_exists( 'bp_is_active' ) ) {
			if ( bp_is_active( 'groups' ) ) {
				$slugs[] = 'subgroups';
			}

			if ( bp_is_active( 'media' ) ) {
				$slugs[] = 'photos';
				$slugs[] = 'albums';
			}

			if ( bp_is_active( 'video' ) ) {
				$slugs[] = 'videos';
			}

			if ( bp_is_active( 'document' ) ) {
				$slugs[] = 'documents';
			}

			if ( bp_is_active( 'messages' ) ) {
				$slugs[] = 'messages';
			}
		}

		$slugs[] = 'manage';

		return apply_filters( 'bdm_tc_known_group_nav_slugs', $slugs );
	}

	/**
	 * Returns a slug => label map for built-in BuddyBoss group nav items.
	 *
	 * Primary source: transient populated from the front end. Returns empty array
	 * on first request before the front end has been visited.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string,string>
	 */
	public static function get_group_nav_labels(): array {
		$cached = get_transient( 'bdm_tc_builtin_group_nav_labels' );
		if ( ! empty( $cached ) && is_array( $cached ) ) {
			return $cached;
		}

		return array();
	}
}
