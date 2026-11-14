<?php
/**
 * REST controller for existing (built-in) profile and group tab overrides.
 *
 * GET    /buddy-modules/v1/tab-creator/existing-tabs              — list slugs + overrides
 * POST   /buddy-modules/v1/tab-creator/existing-tabs/reorder      — save display order
 * GET    /buddy-modules/v1/tab-creator/existing-tabs/{slug}       — get single override
 * PATCH  /buddy-modules/v1/tab-creator/existing-tabs/{slug}       — save override
 * DELETE /buddy-modules/v1/tab-creator/existing-tabs/{slug}       — remove override
 *
 * @package BuddyModules\TabCreator
 * @since 2.0.0
 */

namespace BuddyModules\TabCreator\REST;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for overrides of existing profile and group tabs.
 *
 * @since 2.0.0
 */
class Existing_Tabs_Controller extends \BuddyModules\REST\Controller {

	/**
	 * REST base.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $rest_base = 'tab-creator/existing-tabs';

	/**
	 * Register routes.
	 *
	 * @since 2.0.0
	 */
	public function register_routes(): void {
		// Collection.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permission_callback' ),
					'args'                => array(
						'tab_type' => array(
							'required'          => true,
							'type'              => 'string',
							'enum'              => array( 'profile', 'group' ),
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);

		// /reorder — before (?P<slug>) to avoid slug matching.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reorder',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reorder_items' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		// Single slug.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<slug>[a-z0-9_-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);
	}

	/**
	 * GET /tab-creator/existing-tabs — list built-in slugs merged with any saved overrides.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ): \WP_REST_Response {
		$tab_type  = $request->get_param( 'tab_type' );
		$overrides = \BuddyModules\TabCreator\DB::get_all_overrides( $tab_type );

		// Index overrides by slug for quick lookup.
		$override_map = array();
		foreach ( $overrides as $override ) {
			$override_map[ $override->original_slug ] = $override;
		}

		if ( 'profile' === $tab_type ) {
			$slugs  = \BuddyModules\TabCreator\Builtin_Tabs::get_known_profile_slugs();
			$labels = \BuddyModules\TabCreator\Builtin_Tabs::get_profile_nav_labels();
			$order  = get_option( 'bdm_tc_existing_profile_tab_order', array() );
		} else {
			$slugs  = \BuddyModules\TabCreator\Builtin_Tabs::get_known_group_slugs();
			$labels = \BuddyModules\TabCreator\Builtin_Tabs::get_group_nav_labels();
			$order  = get_option( 'bdm_tc_existing_group_tab_order', array() );
		}

		$items = array();
		foreach ( $slugs as $slug ) {
			$override = $override_map[ $slug ] ?? null;
			$items[]  = $this->prepare_item( $tab_type, $slug, $labels[ $slug ] ?? $slug, $override );
		}

		// Apply saved order if present.
		if ( ! empty( $order ) ) {
			$ordered = array();
			foreach ( $order as $slug ) {
				foreach ( $items as $item ) {
					if ( $item['original_slug'] === $slug ) {
						$ordered[] = $item;
						break;
					}
				}
			}
			// Append any slugs not in the saved order.
			foreach ( $items as $item ) {
				if ( ! in_array( $item['original_slug'], $order, true ) ) {
					$ordered[] = $item;
				}
			}
			$items = $ordered;
		}

		return rest_ensure_response(
			array(
				'tabs'  => $items,
				'total' => count( $items ),
			)
		);
	}

	/**
	 * GET /tab-creator/existing-tabs/{slug} — get override for one slug.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$tab_type = sanitize_key( $request->get_param( 'tab_type' ) ?? '' );
		$slug     = sanitize_key( $request->get_param( 'slug' ) );

		if ( ! $tab_type ) {
			$tab_type = $this->infer_tab_type_from_slug( $slug );
		}

		if ( ! $tab_type ) {
			return $this->error( 'bdm_tc_missing_tab_type', __( 'tab_type is required.', 'buddy-modules' ) );
		}

		$override = \BuddyModules\TabCreator\DB::get_override( $tab_type, $slug );
		$labels   = 'profile' === $tab_type
			? \BuddyModules\TabCreator\Builtin_Tabs::get_profile_nav_labels()
			: \BuddyModules\TabCreator\Builtin_Tabs::get_group_nav_labels();

		return rest_ensure_response( $this->prepare_item( $tab_type, $slug, $labels[ $slug ] ?? $slug, $override ) );
	}

	/**
	 * PATCH /tab-creator/existing-tabs/{slug} — save an override.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$body     = $request->get_json_params() ?? array();
		$tab_type = sanitize_key( $body['tab_type'] ?? $request->get_param( 'tab_type' ) ?? '' );
		$slug     = sanitize_key( $request->get_param( 'slug' ) );

		if ( ! in_array( $tab_type, array( 'profile', 'group' ), true ) ) {
			return $this->error( 'bdm_tc_invalid_type', __( 'tab_type must be profile or group.', 'buddy-modules' ) );
		}

		$fields = array(
			'label_override'    => isset( $body['label_override'] ) ? sanitize_text_field( $body['label_override'] ) : null,
			'position_override' => isset( $body['position_override'] ) ? (int) $body['position_override'] : null,
			'is_enabled'        => isset( $body['is_enabled'] ) ? (int) $body['is_enabled'] : null,
			'is_hidden'         => isset( $body['is_hidden'] ) ? (int) $body['is_hidden'] : null,
			'is_default'        => isset( $body['is_default'] ) ? (int) $body['is_default'] : null,
			'site_admin_only'   => isset( $body['site_admin_only'] ) ? (int) $body['site_admin_only'] : null,
			'tab_link'          => isset( $body['tab_link'] ) ? esc_url_raw( $body['tab_link'] ) : null,
			'item_css_id'       => isset( $body['item_css_id'] ) ? sanitize_html_class( $body['item_css_id'] ) : null,
			'rules'             => (array) ( $body['rules'] ?? array() ),
		);

		// Strip null fields so they don't clobber existing values.
		$fields = array_filter( $fields, fn( $v ) => null !== $v );

		$result = \BuddyModules\TabCreator\Tab_Service::save_override( $tab_type, $slug, $fields );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$override = \BuddyModules\TabCreator\DB::get_override( $tab_type, $slug );
		$labels   = 'profile' === $tab_type
			? \BuddyModules\TabCreator\Builtin_Tabs::get_profile_nav_labels()
			: \BuddyModules\TabCreator\Builtin_Tabs::get_group_nav_labels();

		return rest_ensure_response( $this->prepare_item( $tab_type, $slug, $labels[ $slug ] ?? $slug, $override ) );
	}

	/**
	 * DELETE /tab-creator/existing-tabs/{slug} — remove override.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$body     = $request->get_json_params() ?? array();
		$tab_type = sanitize_key( $body['tab_type'] ?? $request->get_param( 'tab_type' ) ?? '' );
		$slug     = sanitize_key( $request->get_param( 'slug' ) );

		if ( ! in_array( $tab_type, array( 'profile', 'group' ), true ) ) {
			return $this->error( 'bdm_tc_invalid_type', __( 'tab_type must be profile or group.', 'buddy-modules' ) );
		}

		$override = \BuddyModules\TabCreator\DB::get_override( $tab_type, $slug );

		if ( ! $override ) {
			return rest_ensure_response( array( 'deleted' => false ) );
		}

		$deleted = \BuddyModules\TabCreator\DB::delete_override( (int) $override->id );

		return rest_ensure_response( array( 'deleted' => $deleted ) );
	}

	/**
	 * POST /tab-creator/existing-tabs/reorder — save display order for existing tabs.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function reorder_items( $request ) {
		$body     = $request->get_json_params() ?? array();
		$tab_type = sanitize_key( $body['tab_type'] ?? '' );
		$slugs    = array_map( 'sanitize_key', (array) ( $body['slugs'] ?? array() ) );

		if ( ! in_array( $tab_type, array( 'profile', 'group' ), true ) ) {
			return $this->error( 'bdm_tc_invalid_type', __( 'tab_type must be profile or group.', 'buddy-modules' ) );
		}

		$saved = \BuddyModules\TabCreator\Tab_Service::save_existing_tab_order( $tab_type, $slugs );

		return rest_ensure_response( array( 'reordered' => $saved ) );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a response item merging the built-in slug info with any override data.
	 *
	 * @since 2.0.0
	 *
	 * @param string      $tab_type      'profile' or 'group'.
	 * @param string      $slug          Slug of the existing tab.
	 * @param string      $default_label Default label of the existing tab.
	 * @param object|null $override      DB row or null.
	 * @return array
	 */
	private function prepare_item( string $tab_type, string $slug, string $default_label, ?object $override ): array {
		$item = array(
			'original_slug'     => $slug,
			'tab_type'          => $tab_type,
			'default_label'     => $default_label,
			'label_override'    => null,
			'position_override' => null,
			'is_enabled'        => 1,
			'is_hidden'         => 0,
			'is_default'        => 0,
			'site_admin_only'   => 0,
			'tab_link'          => null,
			'item_css_id'       => null,
			'override_id'       => null,
			'rules'             => array(),
		);

		if ( $override ) {
			$item['override_id']       = (int) $override->id;
			$item['label_override']    = $override->label_override;
			$item['position_override'] = null !== $override->position_override ? (int) $override->position_override : null;
			$item['is_enabled']        = (int) $override->is_enabled;
			$item['is_hidden']         = (int) $override->is_hidden;
			$item['is_default']        = (int) $override->is_default;
			$item['site_admin_only']   = (int) $override->site_admin_only;
			$item['tab_link']          = $override->tab_link;
			$item['item_css_id']       = $override->item_css_id;

			$rules         = \BuddyModules\TabCreator\DB::get_rules_for_override( (int) $override->id );
			$item['rules'] = array_map( fn( $r ) => (array) $r, $rules );
		}

		return $item;
	}

	/**
	 * Guess tab_type from slug when the caller did not pass it explicitly.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug Slug of the existing tab.
	 * @return string 'profile'|'group'|''
	 */
	private function infer_tab_type_from_slug( string $slug ): string {
		$group_only = array( 'feed', 'subgroups', 'photos', 'albums', 'videos', 'documents', 'messages', 'manage' );

		if ( in_array( $slug, $group_only, true ) ) {
			return 'group';
		}

		return '';
	}
}
