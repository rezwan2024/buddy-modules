<?php
/**
 * REST controller for the /modules endpoint.
 *
 * GET  /buddy-modules/v1/modules        → list all modules with active status.
 * POST /buddy-modules/v1/modules/{id}   → { "active": true|false } → activate or deactivate.
 *
 * @package BuddyModules
 * @since 2.0.0
 */

namespace BuddyModules\REST;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller that lists modules and turns them on or off.
 *
 * @since 2.0.0
 */
class Modules_Controller extends Controller {

	/**
	 * REST base.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $rest_base = 'modules';

	/**
	 * Register routes.
	 *
	 * @since 2.0.0
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[a-z0-9\-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'permission_callback' ),
					'args'                => $this->get_endpoint_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * GET /modules — return all registered modules.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ): \WP_REST_Response {
		$registry = \BuddyModules\Modules\Registry::get_instance();
		$items    = array();

		foreach ( $registry->all() as $module ) {
			$items[] = $module->to_array( $registry->is_active( $module->get_id() ) );
		}

		usort( $items, fn( $a, $b ) => $a['order'] <=> $b['order'] );

		return rest_ensure_response( $items );
	}

	/**
	 * POST /modules/{id} — activate or deactivate a module.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$id       = $request->get_param( 'id' );
		$registry = \BuddyModules\Modules\Registry::get_instance();
		$module   = $registry->get( $id );

		if ( null === $module ) {
			return $this->error(
				'bdm_not_found',
				__( 'Module not found.', 'buddy-modules' ),
				404
			);
		}

		$active = (bool) $request->get_param( 'active' );
		$result = $active ? $registry->activate( $id ) : $registry->deactivate( $id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			$module->to_array( $registry->is_active( $id ) )
		);
	}

	/**
	 * Args schema for POST /modules/{id}.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	private function get_endpoint_args(): array {
		return array(
			'active' => array(
				'description'       => __( 'Whether the module should be active.', 'buddy-modules' ),
				'type'              => 'boolean',
				'required'          => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}

	/**
	 * Schema for a module item.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_item_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'buddy-modules-module',
			'type'       => 'object',
			'properties' => array(
				'id'             => array( 'type' => 'string' ),
				'label'          => array( 'type' => 'string' ),
				'description'    => array( 'type' => 'string' ),
				'icon'           => array( 'type' => 'string' ),
				'category'       => array( 'type' => 'string' ),
				'active'         => array( 'type' => 'boolean' ),
				'available'      => array( 'type' => 'boolean' ),
				'requires'       => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'settings_route' => array( 'type' => 'string' ),
				'order'          => array( 'type' => 'integer' ),
				'external'       => array( 'type' => 'boolean' ),
			),
		);
	}
}
