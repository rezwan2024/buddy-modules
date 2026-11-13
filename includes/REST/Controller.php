<?php
/**
 * Abstract base REST controller for Buddy Modules.
 *
 * All controllers extend this class. Routes live under buddy-modules/v1 and
 * require the manage_options capability.
 *
 * @since 2.0.0
 */

namespace BuddyModules\REST;

defined( 'ABSPATH' ) || exit;

abstract class Controller extends \WP_REST_Controller {

	/** @var string REST namespace. */
	protected $namespace = 'buddy-modules/v1';

	/**
	 * Check whether the current user has permission to use the endpoint.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request
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
			[ 'status' => $status ]
		);
	}
}
