<?php
/**
 * Abstract base REST controller for Buddy Modules.
 *
 * All controllers extend this class. Routes live under buddy-modules/v1 and
 * require the manage_options capability.
 *
 * @package BuddyModules
 * @since 2.0.0
 */

namespace BuddyModules\REST;

defined( 'ABSPATH' ) || exit;

/**
 * Base REST controller for the buddy-modules/v1 namespace.
 *
 * @since 2.0.0
 */
abstract class Controller extends \WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $namespace = 'buddy-modules/v1';

	/**
	 * Check whether the current user has permission to use the endpoint.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool
	 */
	public function permission_callback( \WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Build a standardised error response.
	 *
	 * @since 2.0.0
	 *
	 * @param string $code    Error code.
	 * @param string $message Human-readable message.
	 * @param int    $status  HTTP status code.
	 * @return \WP_Error
	 */
	protected function error( string $code, string $message, int $status = 400 ): \WP_Error {
		return new \WP_Error(
			$code,
			$message,
			array( 'status' => $status )
		);
	}
}
