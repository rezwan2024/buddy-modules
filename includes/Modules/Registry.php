<?php
/**
 * Module registry — registers modules, tracks active state, activates/deactivates.
 *
 * Active module IDs are stored in the WP option `bdm_active_modules` as a plain array.
 *
 * @package BuddyModules
 * @since 2.0.0
 */

namespace BuddyModules\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Registry of all Buddy Modules modules and their active state.
 *
 * @since 2.0.0
 */
class Registry {

	/** Option name for storing active module IDs. */
	const OPTION = 'bdm_active_modules';

	/**
	 * The single registry instance.
	 *
	 * @since 2.0.0
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Registered modules keyed by ID.
	 *
	 * @since 2.0.0
	 * @var Module[]
	 */
	private array $modules = array();

	/**
	 * Active module IDs.
	 *
	 * @since 2.0.0
	 * @var string[]
	 */
	private array $active = array();

	/**
	 * Load the active module IDs from the option.
	 *
	 * @since 2.0.0
	 */
	private function __construct() {
		$stored       = get_option( self::OPTION, array() );
		$this->active = is_array( $stored ) ? $stored : array();
	}

	/**
	 * Get or create the singleton.
	 *
	 * @since 2.0.0
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register a module with the registry and boot it if active.
	 *
	 * @since 2.0.0
	 *
	 * @param Module $module Module to register.
	 */
	public function register( Module $module ): void {
		$id = $module->get_id();
		if ( isset( $this->modules[ $id ] ) ) {
			return;
		}

		$this->modules[ $id ] = $module;

		if ( $this->is_active( $id ) && $module->dependencies_met() ) {
			$module->boot();
		}
	}

	/**
	 * Retrieve a registered module by ID.
	 *
	 * @since 2.0.0
	 *
	 * @param string $id Module ID.
	 * @return Module|null
	 */
	public function get( string $id ): ?Module {
		return $this->modules[ $id ] ?? null;
	}

	/**
	 * Return all registered modules.
	 *
	 * @since 2.0.0
	 *
	 * @return Module[]
	 */
	public function all(): array {
		return $this->modules;
	}

	/**
	 * Check whether a module is currently active.
	 *
	 * @since 2.0.0
	 *
	 * @param string $id Module ID.
	 */
	public function is_active( string $id ): bool {
		return in_array( $id, $this->active, true );
	}

	/**
	 * Activate a module.
	 *
	 * @since 2.0.0
	 *
	 * @param string $id Module ID.
	 * @return true|\WP_Error
	 */
	public function activate( string $id ) {
		$module = $this->get( $id );
		if ( null === $module ) {
			return new \WP_Error( 'bdm_not_found', __( 'Module not found.', 'buddy-modules' ) );
		}

		if ( ! $module->dependencies_met() ) {
			return new \WP_Error(
				'bdm_deps_missing',
				sprintf(
					/* translators: %s: module label */
					__( '%s requires additional BuddyBoss components to be active.', 'buddy-modules' ),
					$module->get_label()
				)
			);
		}

		if ( ! $this->is_active( $id ) ) {
			$this->active[] = $id;
			update_option( self::OPTION, $this->active );
			$module->activate();
			$module->boot();
		}

		return true;
	}

	/**
	 * Deactivate a module.
	 *
	 * @since 2.0.0
	 *
	 * @param string $id Module ID.
	 * @return true|\WP_Error
	 */
	public function deactivate( string $id ) {
		$module = $this->get( $id );
		if ( null === $module ) {
			return new \WP_Error( 'bdm_not_found', __( 'Module not found.', 'buddy-modules' ) );
		}

		if ( $this->is_active( $id ) ) {
			$this->active = array_values( array_diff( $this->active, array( $id ) ) );
			update_option( self::OPTION, $this->active );
			$module->deactivate();
		}

		return true;
	}
}
