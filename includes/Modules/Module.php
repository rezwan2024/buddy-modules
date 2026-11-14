<?php
/**
 * Abstract base class for all Buddy Modules modules.
 *
 * @package BuddyModules
 * @since 2.0.0
 */

namespace BuddyModules\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Base class that every Buddy Modules module extends.
 *
 * @since 2.0.0
 */
abstract class Module {

	/**
	 * Unique slug, e.g. 'tab-creator'.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected string $id = '';

	/**
	 * Human-readable name.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected string $label = '';

	/**
	 * Short description shown on the dashboard card.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected string $description = '';

	/**
	 * Icon identifier (bb-icons-rl class suffix or 'dashicons-…').
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected string $icon = 'dashicons-layout';

	/**
	 * Dashboard category, e.g. 'Community'.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected string $category = 'General';

	/**
	 * BuddyBoss BP component slugs this module depends on.
	 * Array of strings: 'members', 'groups', 'activity', etc.
	 *
	 * @since 2.0.0
	 * @var string[]
	 */
	protected array $requires = array();

	/**
	 * URL path to the module's settings screen, e.g. '?page=buddy-modules&module=tab-creator'.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected string $settings_route = '';

	/**
	 * Order in the dashboard card grid. Lower = earlier.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected int $order = 10;

	/**
	 * Boot the module (called on every page load when the module is active).
	 *
	 * @since 2.0.0
	 */
	abstract public function boot(): void;

	/**
	 * Register settings panels, sections, and fields for this module.
	 *
	 * Called on every request (regardless of active state) so the REST
	 * controller can always serve and save settings. Override in subclasses.
	 *
	 * @since 2.0.0
	 */
	public function register_settings(): void {}

	/**
	 * Called once when the module is activated through the registry.
	 *
	 * @since 2.0.0
	 */
	public function activate(): void {}

	/**
	 * Called once when the module is deactivated through the registry.
	 *
	 * @since 2.0.0
	 */
	public function deactivate(): void {}

	/**
	 * Get the module ID.
	 *
	 * @since 2.0.0
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Get the module name.
	 *
	 * @since 2.0.0
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Get the module description.
	 *
	 * @since 2.0.0
	 */
	public function get_description(): string {
		return $this->description;
	}

	/**
	 * Get the module icon identifier.
	 *
	 * @since 2.0.0
	 */
	public function get_icon(): string {
		return $this->icon;
	}

	/**
	 * Get the module dashboard category.
	 *
	 * @since 2.0.0
	 */
	public function get_category(): string {
		return $this->category;
	}

	/**
	 * Get the BP component slugs this module depends on.
	 *
	 * @since 2.0.0
	 * @return string[]
	 */
	public function get_requires(): array {
		return $this->requires;
	}

	/**
	 * Get the URL path to the module's settings screen.
	 *
	 * @since 2.0.0
	 */
	public function get_settings_route(): string {
		return $this->settings_route;
	}

	/**
	 * Get the module order in the dashboard card grid.
	 *
	 * @since 2.0.0
	 */
	public function get_order(): int {
		return $this->order;
	}

	/**
	 * Whether this is an external-plugin module card.
	 *
	 * @since 2.0.0
	 */
	public function is_external(): bool {
		return false;
	}

	/**
	 * Check whether all required BP components are active.
	 *
	 * @since 2.0.0
	 */
	public function dependencies_met(): bool {
		foreach ( $this->requires as $component ) {
			if ( ! function_exists( 'bp_is_active' ) || ! bp_is_active( $component ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Serialise the module as an array for the REST API.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $active Whether the module is currently active.
	 */
	public function to_array( bool $active ): array {
		return array(
			'id'             => $this->id,
			'label'          => $this->label,
			'description'    => $this->description,
			'icon'           => $this->icon,
			'category'       => $this->category,
			'active'         => $active,
			'available'      => $this->dependencies_met(),
			'requires'       => $this->requires,
			'settings_route' => $this->settings_route,
			'order'          => $this->order,
			'external'       => $this->is_external(),
		);
	}
}
