<?php
/**
 * REST controller for custom profile and group tabs.
 *
 * GET    /buddy-modules/v1/tab-creator/tabs              — list tabs
 * POST   /buddy-modules/v1/tab-creator/tabs              — create tab
 * POST   /buddy-modules/v1/tab-creator/tabs/reorder      — reorder tabs
 * POST   /buddy-modules/v1/tab-creator/tabs/bulk         — bulk action
 * GET    /buddy-modules/v1/tab-creator/tabs/{id}         — get single tab
 * PATCH  /buddy-modules/v1/tab-creator/tabs/{id}         — update tab
 * DELETE /buddy-modules/v1/tab-creator/tabs/{id}         — delete tab
 *
 * @since 2.0.0
 */

namespace BuddyModules\TabCreator\REST;

defined( 'ABSPATH' ) || exit;

class Tabs_Controller extends \BuddyModules\REST\Controller {

	/** @var string REST base. */
	protected $rest_base = 'tab-creator/tabs';

	/**
	 * Register routes.
	 *
	 * @since 2.0.0
	 */
	public function register_routes(): void {
		// Collection + create.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'permission_callback' ],
					'args'                => [
						'tab_type'  => [
							'required'          => true,
							'type'              => 'string',
							'enum'              => [ 'profile', 'group' ],
							'sanitize_callback' => 'sanitize_key',
						],
						'parent_id' => [
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						],
					],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'permission_callback' ],
				],
			]
		);

		// /reorder — must be before (?P<id>) to avoid matching as an ID.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reorder',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'reorder_items' ],
					'permission_callback' => [ $this, 'permission_callback' ],
				],
			]
		);

		// /bulk — must be before (?P<id>) to avoid matching as an ID.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bulk',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'bulk_action' ],
					'permission_callback' => [ $this, 'permission_callback' ],
				],
			]
		);

		// Single item.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'permission_callback' ],
				],
				[
					'methods'             => 'PATCH',
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'permission_callback' ],
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'permission_callback' ],
				],
			]
		);
	}

	/**
	 * GET /tab-creator/tabs — list tabs.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ): \WP_REST_Response {
		$tab_type  = $request->get_param( 'tab_type' );
		$parent_id = (int) $request->get_param( 'parent_id' );

		$args = [ 'parent_id' => $parent_id ];
		$tabs = \BuddyModules\TabCreator\DB::get_tabs( $tab_type, $args );
		$data = array_map( [ $this, 'prepare_tab' ], $tabs );

		return rest_ensure_response(
			[
				'tabs'  => $data,
				'total' => count( $data ),
			]
		);
	}

	/**
	 * GET /tab-creator/tabs/{id} — get single tab.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$id  = (int) $request->get_param( 'id' );
		$tab = \BuddyModules\TabCreator\DB::get_tab( $id );

		if ( ! $tab ) {
			return $this->error( 'bdm_tc_not_found', __( 'Tab not found.', 'buddy-modules' ), 404 );
		}

		return rest_ensure_response( $this->prepare_tab( $tab ) );
	}

	/**
	 * POST /tab-creator/tabs — create tab.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$tab_type = sanitize_key( $request->get_param( 'tab_type' ) ?? '' );

		if ( ! in_array( $tab_type, [ 'profile', 'group' ], true ) ) {
			return $this->error( 'bdm_tc_invalid_type', __( 'tab_type must be profile or group.', 'buddy-modules' ) );
		}

		$fields = $this->extract_tab_fields( $request );
		$result = \BuddyModules\TabCreator\Tab_Service::save_tab( $tab_type, $fields, 0 );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$tab = \BuddyModules\TabCreator\DB::get_tab( $result );

		return rest_ensure_response( $this->prepare_tab( $tab ) );
	}

	/**
	 * PATCH /tab-creator/tabs/{id} — update tab.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$id  = (int) $request->get_param( 'id' );
		$tab = \BuddyModules\TabCreator\DB::get_tab( $id );

		if ( ! $tab ) {
			return $this->error( 'bdm_tc_not_found', __( 'Tab not found.', 'buddy-modules' ), 404 );
		}

		$tab_type = $tab->tab_type;
		$fields   = $this->extract_tab_fields( $request );
		$result   = \BuddyModules\TabCreator\Tab_Service::save_tab( $tab_type, $fields, $id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $this->prepare_tab( \BuddyModules\TabCreator\DB::get_tab( $id ) ) );
	}

	/**
	 * DELETE /tab-creator/tabs/{id} — delete tab.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$id  = (int) $request->get_param( 'id' );
		$tab = \BuddyModules\TabCreator\DB::get_tab( $id );

		if ( ! $tab ) {
			return $this->error( 'bdm_tc_not_found', __( 'Tab not found.', 'buddy-modules' ), 404 );
		}

		$deleted = \BuddyModules\TabCreator\Tab_Service::delete_tab( $id );

		return rest_ensure_response( [ 'deleted' => $deleted ] );
	}

	/**
	 * POST /tab-creator/tabs/reorder — save new display order.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function reorder_items( $request ): \WP_REST_Response {
		$ids = array_map( 'absint', (array) ( $request->get_param( 'ids' ) ?? [] ) );

		\BuddyModules\TabCreator\Tab_Service::reorder_tabs( $ids );

		return rest_ensure_response( [ 'reordered' => true ] );
	}

	/**
	 * POST /tab-creator/tabs/bulk — bulk activate/deactivate/delete.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function bulk_action( $request ) {
		$action = sanitize_key( $request->get_param( 'action' ) ?? '' );
		$ids    = array_values( array_filter( array_map( 'absint', (array) ( $request->get_param( 'ids' ) ?? [] ) ) ) );

		if ( ! in_array( $action, [ 'activate', 'deactivate', 'delete' ], true ) ) {
			return $this->error( 'bdm_tc_invalid_action', __( 'Invalid bulk action.', 'buddy-modules' ) );
		}

		if ( empty( $ids ) ) {
			return $this->error( 'bdm_tc_no_ids', __( 'No tab IDs provided.', 'buddy-modules' ) );
		}

		$count = 0;

		foreach ( $ids as $id ) {
			switch ( $action ) {
				case 'activate':
					if ( \BuddyModules\TabCreator\DB::update_tab( $id, [ 'status' => 'active' ] ) ) {
						$count++;
					}
					break;
				case 'deactivate':
					if ( \BuddyModules\TabCreator\DB::update_tab( $id, [ 'status' => 'inactive' ] ) ) {
						$count++;
					}
					break;
				case 'delete':
					if ( \BuddyModules\TabCreator\Tab_Service::delete_tab( $id ) ) {
						$count++;
					}
					break;
			}
		}

		return rest_ensure_response( [ 'action' => $action, 'count' => $count ] );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a tab data array from a REST request.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request
	 * @return array
	 */
	private function extract_tab_fields( \WP_REST_Request $request ): array {
		$body = $request->get_json_params() ?? [];

		return [
			'label'               => sanitize_text_field( $body['label'] ?? '' ),
			'slug'                => sanitize_title( $body['slug'] ?? '' ),
			'is_enabled'          => (int) ( $body['is_enabled'] ?? 1 ),
			'is_predefined'       => (int) ( $body['is_predefined'] ?? 0 ),
			'site_admin_only'     => (int) ( $body['site_admin_only'] ?? 0 ),
			'is_default'          => (int) ( $body['is_default'] ?? 0 ),
			'position'            => isset( $body['position'] ) ? (int) $body['position'] : null,
			'tab_link'            => esc_url_raw( $body['tab_link'] ?? '' ),
			'default_subnav_slug' => sanitize_key( $body['default_subnav_slug'] ?? '' ),
			'item_css_id'         => sanitize_html_class( $body['item_css_id'] ?? '' ),
			'add_to_admin_bar'    => (int) ( $body['add_to_admin_bar'] ?? 0 ),
			'administer_item_id'  => sanitize_text_field( $body['administer_item_id'] ?? '' ),
			'content_source'      => sanitize_key( $body['content_source'] ?? 'content' ),
			'content'             => wp_kses_post( $body['content'] ?? '' ),
			'external_url'        => esc_url_raw( $body['external_url'] ?? '' ),
			'url_new_tab'         => (int) ( $body['url_new_tab'] ?? 0 ),
			'status'              => sanitize_key( $body['status'] ?? 'active' ),
			'rules'               => (array) ( $body['rules'] ?? [] ),
		];
	}

	/**
	 * Prepare a tab row (plus its rules) for the REST response.
	 *
	 * @since 2.0.0
	 *
	 * @param object $tab DB row.
	 * @return array
	 */
	private function prepare_tab( object $tab ): array {
		$data  = (array) $tab;
		$rules = \BuddyModules\TabCreator\DB::get_rules_for_tab( (int) $tab->id );

		$data['rules'] = array_map( fn( $r ) => (array) $r, $rules );

		return $data;
	}
}
