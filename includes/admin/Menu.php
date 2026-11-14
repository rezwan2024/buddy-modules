<?php
/**
 * Register the top-level Buddy Modules admin menu.
 *
 * The menu is inserted directly after the BuddyBoss Platform menu entry
 * using the custom_menu_order / menu_order filters, mirroring the technique
 * BuddyBoss Membership uses.
 *
 * @package BuddyModules
 * @since 2.0.0
 */

namespace BuddyModules\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Top-level Buddy Modules admin menu.
 *
 * @since 2.0.0
 */
class Menu {

	/** WP admin page slug. */
	const SLUG = 'buddy-modules';

	/**
	 * Hook suffix returned by add_menu_page().
	 *
	 * @since 2.0.0
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Hook into WordPress admin.
	 *
	 * @since 2.0.0
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_filter( 'custom_menu_order', '__return_true' );
		add_filter( 'menu_order', array( $this, 'reorder_menu' ), 20 );
	}

	/**
	 * Add the top-level menu page.
	 *
	 * @since 2.0.0
	 */
	public function add_menu(): void {
		$this->hook_suffix = add_menu_page(
			__( 'Buddy Modules', 'buddy-modules' ),
			__( 'Buddy Modules', 'buddy-modules' ),
			'manage_options',
			self::SLUG,
			array( Page::class, 'render' ),
			'dashicons-layout',
			4
		);
	}

	/**
	 * Move our menu entry directly after the BuddyBoss Platform entry.
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $order Menu order array.
	 * @return string[]
	 */
	public function reorder_menu( array $order ): array {
		$our_slug = self::SLUG;
		$bb_slug  = 'buddyboss-platform';

		// Remove our slug if it already appears.
		$order = array_values( array_filter( $order, fn( $s ) => $s !== $our_slug ) );

		$bb_pos = array_search( $bb_slug, $order, true );

		if ( false !== $bb_pos ) {
			array_splice( $order, $bb_pos + 1, 0, array( $our_slug ) );
		} else {
			// BuddyBoss Platform not found — append near top.
			array_unshift( $order, $our_slug );
		}

		return $order;
	}

	/**
	 * Return the hook suffix for asset enqueueing.
	 *
	 * @since 2.0.0
	 */
	public function get_hook_suffix(): string {
		return $this->hook_suffix;
	}
}
