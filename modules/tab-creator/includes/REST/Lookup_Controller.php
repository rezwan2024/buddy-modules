<?php
/**
 * REST controller for lookup endpoints used by the Tab Creator UI.
 *
 * GET /buddy-modules/v1/tab-creator/lookup/users          — search users
 * GET /buddy-modules/v1/tab-creator/lookup/groups         — search groups
 * GET /buddy-modules/v1/tab-creator/lookup/roles          — list WP roles
 *
 * @package BuddyModules\TabCreator
 * @since 2.0.0
 */

namespace BuddyModules\TabCreator\REST;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for the user, group and role lookups used by the Tab Creator UI.
 *
 * @since 2.0.0
 */
class Lookup_Controller extends \BuddyModules\REST\Controller {

	/**
	 * REST base.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $rest_base = 'tab-creator/lookup';

	/**
	 * Register routes.
	 *
	 * @since 2.0.0
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/users',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_users' ),
					'permission_callback' => array( $this, 'permission_callback' ),
					'args'                => array(
						'search' => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'limit'  => array(
							'type'              => 'integer',
							'default'           => 20,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/groups',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_groups' ),
					'permission_callback' => array( $this, 'permission_callback' ),
					'args'                => array(
						'search' => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'limit'  => array(
							'type'              => 'integer',
							'default'           => 20,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/roles',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_roles' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);
	}

	/**
	 * GET /tab-creator/lookup/users — search WP users.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response
	 */
	public function get_users( $request ): \WP_REST_Response {
		$search = $request->get_param( 'search' );
		$limit  = max( 1, min( 100, (int) $request->get_param( 'limit' ) ) );

		$args = array(
			'number'  => $limit,
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'fields'  => array( 'ID', 'display_name', 'user_login', 'user_email' ),
		);

		if ( $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'display_name', 'user_login', 'user_email' );
		}

		$users  = get_users( $args );
		$result = array();

		foreach ( $users as $user ) {
			$result[] = array(
				'id'           => (int) $user->ID,
				'display_name' => $user->display_name,
				'user_login'   => $user->user_login,
			);
		}

		return rest_ensure_response(
			array(
				'users' => $result,
				'total' => count( $result ),
			)
		);
	}

	/**
	 * GET /tab-creator/lookup/groups — search BuddyPress groups.
	 *
	 * Falls back to an empty list when the groups component is inactive.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response
	 */
	public function get_groups( $request ): \WP_REST_Response {
		if ( ! function_exists( 'groups_get_groups' ) ) {
			return rest_ensure_response(
				array(
					'groups' => array(),
					'total'  => 0,
				)
			);
		}

		$search = $request->get_param( 'search' );
		$limit  = max( 1, min( 100, (int) $request->get_param( 'limit' ) ) );

		$args = array(
			'per_page'    => $limit,
			'page'        => 1,
			'orderby'     => 'name',
			'order'       => 'ASC',
			'show_hidden' => true,
		);

		if ( $search ) {
			$args['search_terms'] = $search;
		}

		$query  = groups_get_groups( $args );
		$result = array();

		if ( ! empty( $query['groups'] ) ) {
			foreach ( $query['groups'] as $group ) {
				$result[] = array(
					'id'     => (int) $group->id,
					'name'   => $group->name,
					'status' => $group->status,
				);
			}
		}

		return rest_ensure_response(
			array(
				'groups' => $result,
				'total'  => count( $result ),
			)
		);
	}

	/**
	 * GET /tab-creator/lookup/roles — list all WP roles.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response
	 */
	public function get_roles( $request ): \WP_REST_Response {
		$wp_roles = wp_roles();
		$result   = array();

		foreach ( $wp_roles->roles as $slug => $role ) {
			$result[] = array(
				'slug'  => $slug,
				'label' => translate_user_role( $role['name'] ),
			);
		}

		return rest_ensure_response(
			array(
				'roles' => $result,
				'total' => count( $result ),
			)
		);
	}
}
