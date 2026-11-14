<?php
/**
 * Renders the React admin page shell and suppresses foreign admin notices.
 *
 * @package BuddyModules
 * @since 2.0.0
 */

namespace BuddyModules\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin page shell for Buddy Modules.
 *
 * @since 2.0.0
 */
class Page {

	/**
	 * Hook into WordPress.
	 *
	 * Suppresses all third-party admin_notices and all_admin_notices on every
	 * Buddy Modules page so users never see unrelated marketing, warnings or
	 * popups from other plugins. Only notices added via bdm_admin_notices are
	 * allowed through.
	 *
	 * @since 2.0.0
	 */
	public static function register(): void {
		// Priority -1 fires before every other plugin's notices (priority 10).
		// remove_all_actions() inside the hook clears all remaining callbacks
		// for that hook, so nothing else renders on our pages.
		add_action( 'admin_notices', array( __CLASS__, 'suppress_foreign_notices' ), -1 );
		add_action( 'all_admin_notices', array( __CLASS__, 'suppress_foreign_notices_all' ), -1 );
	}

	/**
	 * Remove all admin_notices callbacks on Buddy Modules pages, then
	 * re-add only the BDM-specific notice hook.
	 *
	 * @since 2.0.0
	 */
	public static function suppress_foreign_notices(): void {
		if ( ! self::is_bdm_screen() ) {
			return;
		}
		remove_all_actions( 'admin_notices' );
		// Allow our own notices back in at a known priority.
		do_action( 'bdm_admin_notices' );
	}

	/**
	 * Remove all all_admin_notices callbacks on Buddy Modules pages.
	 *
	 * @since 2.0.0
	 */
	public static function suppress_foreign_notices_all(): void {
		if ( ! self::is_bdm_screen() ) {
			return;
		}
		remove_all_actions( 'all_admin_notices' );
	}

	/**
	 * Detect whether the current screen belongs to Buddy Modules.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_bdm_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen instanceof \WP_Screen ) {
			// Matches toplevel_page_buddy-modules and any future submenu pages.
			return 0 === strpos( $screen->id, 'toplevel_page_buddy-modules' )
				|| 0 === strpos( $screen->id, 'buddy-modules_page_' );
		}
		// Fallback for very early calls.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['page'] ) && 'buddy-modules' === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Output the page shell.
	 *
	 * The wp-header-end HR is required for WP to know where to inject admin
	 * notices. The React root mounts inside #bdm-admin-root.
	 *
	 * @since 2.0.0
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap bdm-admin-wrap">
			<hr class="wp-header-end">
			<div id="bdm-admin-root"></div>
		</div>
		<?php
	}
}
