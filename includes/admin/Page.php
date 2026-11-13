<?php
/**
 * Renders the React admin page shell.
 *
 * @since 2.0.0
 */

namespace BuddyModules\Admin;

defined( 'ABSPATH' ) || exit;

class Page {

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
