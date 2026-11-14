<?php
/**
 * PSR-4-ish autoloader for Buddy Modules.
 *
 * BuddyModules\TabCreator\Foo  → modules/tab-creator/includes/Foo.php
 * BuddyModules\Foo\Bar         → includes/Foo/Bar.php
 *
 * @package BuddyModules
 * @since 2.0.0
 */

namespace BuddyModules;

defined( 'ABSPATH' ) || exit;

/**
 * Load Buddy Modules classes from their files on demand.
 *
 * @since 2.0.0
 */
class Autoloader {

	/**
	 * Register the autoloader with SPL.
	 *
	 * @since 2.0.0
	 */
	public function register(): void {
		spl_autoload_register( array( $this, 'load' ) );
	}

	/**
	 * Load a class by its fully-qualified name.
	 *
	 * @since 2.0.0
	 *
	 * @param string $class_name FQCN.
	 */
	public function load( string $class_name ): void {
		$prefix = 'BuddyModules\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );

		// Namespace-to-path overrides. Keys are sub-namespace prefixes (with trailing \\).
		// Admin\\ maps to includes/admin/ (lowercase) because on macOS the mkdir for
		// includes/Admin/ resolved to the pre-existing legacy includes/admin/ folder;
		// using the explicit lowercase path keeps things predictable on Linux too.
		$ns_map = array(
			'TabCreator\\' => BDM_DIR . 'modules/tab-creator/includes/',
			'Admin\\'      => BDM_DIR . 'includes/admin/',
		);

		foreach ( $ns_map as $ns => $base_dir ) {
			if ( 0 === strpos( $relative, $ns ) ) {
				$sub  = substr( $relative, strlen( $ns ) );
				$file = $base_dir . str_replace( '\\', '/', $sub ) . '.php';
				if ( file_exists( $file ) ) {
					require_once $file;
				}
				return;
			}
		}

		// Core namespace (all other sub-namespaces): BuddyModules\Foo\Bar → includes/Foo/Bar.php.
		$file = BDM_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
