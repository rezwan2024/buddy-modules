<?php
/**
 * Module card for a separate WordPress plugin.
 *
 * The toggle calls activate_plugin() / deactivate_plugin() instead of the
 * registry's bdm_active_modules option.
 *
 * @since 2.0.0
 */

namespace BuddyModules\Modules;

defined( 'ABSPATH' ) || exit;

class External_Module extends Module {

	/**
	 * Plugin file relative to the plugins directory, e.g. 'buddyboss-smtp/buddyboss-smtp.php'.
	 *
	 * @var string
	 */
	protected string $plugin_file = '';

	/** @since 2.0.0 */
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

	/** @since 2.0.0 */
	public function get_plugin_file(): string {
		return $this->plugin_file;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since 2.0.0
	 */
	public function to_array( bool $active ): array {
		$data                = parent::to_array( $active );
		$data['plugin_file'] = $this->plugin_file;
		$data['installed']   = $this->is_plugin_installed();
		return $data;
	}
}
