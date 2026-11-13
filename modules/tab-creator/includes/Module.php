<?php
/**
 * Profile and Group Tab Creator module stub.
 *
 * Full frontend and REST logic is wired in P4–P7. For now this stub
 * registers the module with the dashboard and allows it to be toggled.
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
	 * Frontend Nav/Visibility classes are wired here in P7. Stubs only for P1.
	 *
	 * @since 2.0.0
	 */
	public function boot(): void {}
}
