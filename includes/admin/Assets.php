<?php
/**
 * Enqueue admin scripts and styles for the Buddy Modules page.
 *
 * @since 2.0.0
 */

namespace BuddyModules\Admin;

defined( 'ABSPATH' ) || exit;

class Assets {

	/** Script/style handle. */
	const SCRIPT_HANDLE = 'bdm-admin';
	const STYLE_HANDLE  = 'bdm-admin-style';

	/**
	 * Hook into WordPress.
	 *
	 * @since 2.0.0
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Enqueue scripts and styles on the Buddy Modules page.
	 *
	 * @since 2.0.0
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue( string $hook ): void {
		if ( false === strpos( $hook, 'toplevel_page_buddy-modules' ) &&
			'toplevel_page_buddy-modules' !== $hook ) {
			return;
		}

		$asset_file = BDM_DIR . 'build/admin.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: [ 'dependencies' => [], 'version' => BDM_VERSION ];

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			BDM_URL . 'build/admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			self::STYLE_HANDLE,
			BDM_URL . 'build/admin.css',
			[ 'wp-components' ],
			$asset['version']
		);

		$this->localize( $asset['version'] );

		// JS translations.
		wp_set_script_translations( self::SCRIPT_HANDLE, 'buddy-modules', BDM_DIR . 'languages' );
	}

	/**
	 * Pass PHP data to JS via bdmAdminData.
	 *
	 * @since 2.0.0
	 *
	 * @param string $version Asset version string.
	 */
	private function localize( string $version ): void {
		$registry = \BuddyModules\Modules\Registry::get_instance();

		$modules = [];
		foreach ( $registry->all() as $module ) {
			$modules[] = $module->to_array( $registry->is_active( $module->get_id() ) );
		}

		usort( $modules, fn( $a, $b ) => $a['order'] <=> $b['order'] );

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'bdmAdminData',
			[
				'restRoot' => esc_url_raw( rest_url( 'buddy-modules/v1' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'adminUrl' => esc_url_raw( admin_url( 'admin.php?page=buddy-modules' ) ),
				'logoUrl'  => esc_url_raw( BDM_URL . 'assets/images/logo.svg' ),
				'version'  => $version,
				'modules'  => $modules,
			]
		);
	}
}
