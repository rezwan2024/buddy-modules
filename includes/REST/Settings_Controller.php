<?php
/**
 * REST controller for module settings.
 *
 * Routes:
 *   GET  /buddy-modules/v1/settings/{module}  — returns schema + current values.
 *   POST /buddy-modules/v1/settings/{module}  — saves a panel's settings.
 *
 * Settings are stored in the WP option bdm_settings_{module} as a nested
 * array: { panel_id: { field_id: value } }.
 *
 * @package BuddyModules
 * @since 2.0.0
 */

namespace BuddyModules\REST;

use BuddyModules\Settings\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller that reads and saves module settings.
 *
 * @since 2.0.0
 */
class Settings_Controller extends Controller {

	/**
	 * REST base.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $rest_base = 'settings';

	/**
	 * Register REST routes.
	 *
	 * @since 2.0.0
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<module>[\w-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permission_callback' ),
					'args'                => array(
						'module' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'permission_callback' ),
					'args'                => array(
						'module'   => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
						'panel'    => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
						'settings' => array(
							'type'     => 'object',
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * GET /settings/{module} — schema + current values for all panels.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$module = $request->get_param( 'module' );
		$schema = Fields::get_schema( $module );
		$values = get_option( 'bdm_settings_' . $module, array() );

		if ( ! is_array( $values ) ) {
			$values = array();
		}

		return rest_ensure_response(
			array(
				'panels' => $schema,
				'values' => $values,
			)
		);
	}

	/**
	 * POST /settings/{module} — save one panel's settings.
	 *
	 * Body: { panel: string, settings: { field_id: value } }
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$module   = $request->get_param( 'module' );
		$panel    = $request->get_param( 'panel' );
		$settings = $request->get_param( 'settings' );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$panels = Fields::get_panels( $module );
		if ( ! isset( $panels[ $panel ] ) ) {
			return $this->error(
				'bdm_invalid_panel',
				__( 'Invalid panel.', 'buddy-modules' ),
				400
			);
		}

		$flat    = Fields::get_flat_fields( $module, $panel );
		$current = get_option( 'bdm_settings_' . $module, array() );

		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$sanitized = $current[ $panel ] ?? array();

		foreach ( $flat as $field_id => $field ) {
			if ( array_key_exists( $field_id, $settings ) ) {
				$sanitized[ $field_id ] = Fields::sanitize_value(
					$field['type'] ?? 'text',
					$settings[ $field_id ]
				);
			}
		}

		$current[ $panel ] = $sanitized;
		update_option( 'bdm_settings_' . $module, $current );

		return rest_ensure_response(
			array(
				'panel'    => $panel,
				'settings' => $sanitized,
			)
		);
	}
}
