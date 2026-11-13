<?php
/**
 * Profile and Group Tab Creator module.
 *
 * Boots the REST controllers and runs the DB installer on plugin activate.
 * Frontend Nav/Visibility logic is wired in P7.
 *
 * @since 2.0.0
 */

namespace BuddyModules\TabCreator;

defined( 'ABSPATH' ) || exit;

class Module extends \BuddyModules\Modules\Module {

	protected string $id          = 'tab-creator';
	protected string $label       = 'Profile and Group Tab Creator';
	protected string $description = 'Create and manage unlimited custom tabs and sub-tabs for BuddyBoss member profiles and groups. No coding required.';
	protected string $icon        = 'dashicons-menu-alt';
	protected string $category    = 'Community';
	protected array  $requires    = [ 'members' ];
	protected string $settings_route = '?page=buddy-modules&module=tab-creator';
	protected int    $order       = 10;

	/**
	 * Boot the module on each page load when active.
	 *
	 * Runs the DB version check and registers REST routes.
	 * Frontend Nav/Visibility classes are wired in P7.
	 *
	 * @since 2.0.0
	 */
	public function boot(): void {
		Installer::maybe_upgrade();

		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
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
			[
				'label' => __( 'Profile Tabs', 'buddy-modules' ),
				'icon'  => 'dashicons-admin-users',
			]
		);

		\BuddyModules\Settings\Fields::register_section(
			$module,
			'profile-tabs',
			'general',
			[
				'label' => __( 'General', 'buddy-modules' ),
			]
		);

		\BuddyModules\Settings\Fields::register_field(
			$module,
			'profile-tabs',
			'general',
			'enable_profile_tabs',
			[
				'type'        => 'toggle',
				'label'       => __( 'Enable Profile Tabs', 'buddy-modules' ),
				'description' => __( 'Allow custom tabs on member profile pages.', 'buddy-modules' ),
				'default'     => false,
			]
		);

		// ── Group Tabs panel ──────────────────────────────────────────────────

		\BuddyModules\Settings\Fields::register_panel(
			$module,
			'group-tabs',
			[
				'label' => __( 'Group Tabs', 'buddy-modules' ),
				'icon'  => 'dashicons-groups',
			]
		);

		\BuddyModules\Settings\Fields::register_section(
			$module,
			'group-tabs',
			'general',
			[
				'label' => __( 'General', 'buddy-modules' ),
			]
		);
	}
}
