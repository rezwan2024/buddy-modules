<?php
/**
 * Profile and Group Tab Creator module.
 *
 * Boots the Tab Creator module: DB installer, migration, REST controllers,
 * and front-end nav/visibility hooks.
 *
 * @package BuddyModules\TabCreator
 * @since 2.0.0
 */

namespace BuddyModules\TabCreator;

defined( 'ABSPATH' ) || exit;

/**
 * Profile and Group Tab Creator module definition.
 *
 * @since 2.0.0
 */
class Module extends \BuddyModules\Modules\Module {

	/**
	 * Unique module slug.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected string $id = 'tab-creator';

	/**
	 * Human-readable module name.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected string $label = 'Profile and Group Tab Creator';

	/**
	 * Short description shown on the dashboard card.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected string $description = 'Create and manage unlimited custom tabs and sub-tabs for BuddyBoss member profiles and groups. No coding required.';

	/**
	 * Dashboard card icon.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected string $icon = 'bb-icons-rl-tabs';

	/**
	 * Dashboard category.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected string $category = 'Community';

	/**
	 * BuddyBoss components this module depends on.
	 *
	 * @since 2.0.0
	 * @var string[]
	 */
	protected array $requires = array( 'members' );

	/**
	 * URL path to the module's settings screen.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected string $settings_route = '?page=buddy-modules&module=tab-creator';

	/**
	 * Order in the dashboard card grid.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected int $order = 10;

	/**
	 * Boot the module on each page load when active.
	 *
	 * @since 2.0.0
	 */
	public function boot(): void {
		Installer::maybe_upgrade();
		Migration::maybe_migrate();
		Migration::register_notices();

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Front-end nav: profile tabs.
		if ( ! is_admin() && bp_is_active( 'members' ) ) {
			Profile_Nav::init();
		}

		// Front-end nav: group tabs.
		if ( ! is_admin() && bp_is_active( 'groups' ) ) {
			Group_Nav::init();
		}
	}

	/**
	 * Register Tab Creator REST routes.
	 *
	 * @since 2.0.0
	 */
	public function register_rest_routes(): void {
		( new REST\Tabs_Controller() )->register_routes();
		( new REST\Existing_Tabs_Controller() )->register_routes();
		( new REST\Lookup_Controller() )->register_routes();
	}

	/**
	 * Register settings panels, sections, and fields for Tab Creator.
	 *
	 * @since 2.0.0
	 */
	public function register_settings(): void {
		$module = $this->id;

		// ── Profile Tabs panel ────────────────────────────────────────────────

		\BuddyModules\Settings\Fields::register_panel(
			$module,
			'profile-tabs',
			array(
				'label' => __( 'Profile Tabs', 'buddy-modules' ),
				'icon'  => 'bb-icons-rl-user',
			)
		);

		// ── Group Tabs panel ──────────────────────────────────────────────────

		\BuddyModules\Settings\Fields::register_panel(
			$module,
			'group-tabs',
			array(
				'label' => __( 'Group Tabs', 'buddy-modules' ),
				'icon'  => 'bb-icons-rl-users-three',
			)
		);

		// ── Profile Built-in Tabs panel ───────────────────────────────────────

		\BuddyModules\Settings\Fields::register_panel(
			$module,
			'profile-existing-tabs',
			array(
				'label' => __( 'Profile Built-in Tabs', 'buddy-modules' ),
				'icon'  => 'bb-icons-rl-user-list',
			)
		);

		// ── Group Built-in Tabs panel ─────────────────────────────────────────

		\BuddyModules\Settings\Fields::register_panel(
			$module,
			'group-existing-tabs',
			array(
				'label' => __( 'Group Built-in Tabs', 'buddy-modules' ),
				'icon'  => 'bb-icons-rl-list-bullets',
			)
		);
	}
}
