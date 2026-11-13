<?php
/**
 * Module registry — registers modules, tracks active state, activates/deactivates.
 *
 * Active module IDs are stored in the WP option `bdm_active_modules` as a plain array.
 *
 * @since 2.0.0
 */

namespace BuddyModules\Modules;

defined( 'ABSPATH' ) || exit;

class Registry {

	/** Option name for storing active module IDs. */
	const OPTION = 'bdm_active_modules';

	/** @var self|null */
	private static ?self $instance = null;

	/** @var Module[] Registered modules keyed by ID. */
	private array $modules = [];

	/** @var string[] Active module IDs. */
	private array $active = [];

	private function __construct() {
		$stored       = get_option( self::OPTION, [] );
		$this->active = is_array( $stored ) ? $stored : [];
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
	 * @param Module $module
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
	 * @param string $id
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
	 * @param string $id
	 */
	public function is_active( string $id ): bool {
		return in_array( $id, $this->active, true );
	}

	/**
	 * Activate a module.
	 *
	 * @since 2.0.0
	 *
	 * @param string $id
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
	 * @param string $id
	 * @return true|\WP_Error
	 */
	public function deactivate( string $id ) {
		$module = $this->get( $id );
		if ( null === $module ) {
			return new \WP_Error( 'bdm_not_found', __( 'Module not found.', 'buddy-modules' ) );
		}

		if ( $this->is_active( $id ) ) {
			$this->active = array_values( array_diff( $this->active, [ $id ] ) );
			update_option( self::OPTION, $this->active );
			$module->deactivate();
		}

		return true;
	}
}
