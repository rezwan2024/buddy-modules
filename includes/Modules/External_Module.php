<?php
/**
 * Module card for a separate WordPress plugin.
 *
 * The toggle calls activate_plugin() / deactivate_plugin() instead of the
 * registry's bdm_active_modules option.
 *
 * @package BuddyModules
 * @since 2.0.0
 */

namespace BuddyModules\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard module card that represents a separate WordPress plugin.
 *
 * @since 2.0.0
 */
class External_Module extends Module {

	/**
	 * Plugin file relative to the plugins directory, e.g. 'buddyboss-smtp/buddyboss-smtp.php'.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected string $plugin_file = '';

	/**
	 * Boot the module. External plugins boot themselves, so this does nothing.
	 *
	 * @since 2.0.0
	 */
	public function boot(): void {}

	/**
	 * Whether this is an external-plugin module card.
	 *
	 * @since 2.0.0
	 */
	public function is_external(): bool {
		return true;
	}

	/**
	 * Check whether the external plugin is currently active.
	 *
	 * @since 2.0.0
	 */
	public function is_plugin_active(): bool {
		return is_plugin_active( $this->plugin_file );
	}

	/**
	 * Check whether the external plugin file exists (is installed).
	 *
	 * @since 2.0.0
	 */
	public function is_plugin_installed(): bool {
		return file_exists( WP_PLUGIN_DIR . '/' . $this->plugin_file );
	}

	/**
	 * Get the plugin file relative to the plugins directory.
	 *
	 * @since 2.0.0
	 */
	public function get_plugin_file(): string {
		return $this->plugin_file;
	}

	/**
	 * Serialise the module as an array for the REST API, with plugin file and install state.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $active Whether the module is currently active.
	 */
	public function to_array( bool $active ): array {
		$data                = parent::to_array( $active );
		$data['plugin_file'] = $this->plugin_file;
		$data['installed']   = $this->is_plugin_installed();
		return $data;
	}
}
