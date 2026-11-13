<?php
/**
 * Settings field registry.
 *
 * Modules call the static registration methods to declare their settings schema.
 * The schema is in-memory only; values live in the WP option bdm_settings_{module}.
 *
 * @since 2.0.0
 */

namespace BuddyModules\Settings;

defined( 'ABSPATH' ) || exit;

class Fields {

	/** @var array<string, array> In-memory registry keyed by module ID. */
	private static array $registry = [];

	/**
	 * Register a settings panel for a module.
	 *
	 * @since 2.0.0
	 *
	 * @param string $module   Module ID (e.g. 'tab-creator').
	 * @param string $panel_id Unique panel slug within the module.
	 * @param array  $args {
	 *     @type string $label Human-readable panel title.
	 *     @type string $icon  Optional icon: BuddyBoss line icon (e.g. 'bb-icons-rl-user') or dashicons class (e.g. 'dashicons-menu-alt').
	 * }
	 */
	public static function register_panel( string $module, string $panel_id, array $args ): void {
		self::ensure( $module );
		self::$registry[ $module ]['panels'][ $panel_id ] = $args;
	}

	/**
	 * Register a settings section inside a panel.
	 *
	 * @since 2.0.0
	 *
	 * @param string $module     Module ID.
	 * @param string $panel_id   Panel slug.
	 * @param string $section_id Unique section slug within the panel.
	 * @param array  $args {
	 *     @type string $label       Section heading (optional).
	 *     @type string $description Section description (optional).
	 * }
	 */
	public static function register_section( string $module, string $panel_id, string $section_id, array $args ): void {
		self::ensure( $module );
		self::$registry[ $module ]['sections'][ $panel_id ][ $section_id ] = $args;
	}

	/**
	 * Register a settings field inside a section.
	 *
	 * @since 2.0.0
	 *
	 * @param string $module     Module ID.
	 * @param string $panel_id   Panel slug.
	 * @param string $section_id Section slug.
	 * @param string $field_id   Unique field slug within the module.
	 * @param array  $args {
	 *     @type string $type        Field type: toggle|text|textarea|select|radio|checkbox_list|number|url.
	 *     @type string $label       Field label shown in the UI.
	 *     @type string $description Help text (optional).
	 *     @type mixed  $default     Default value.
	 *     @type array  $options     Choices for select/radio/checkbox_list: [ [ 'value' => '', 'label' => '' ], … ].
	 * }
	 */
	public static function register_field( string $module, string $panel_id, string $section_id, string $field_id, array $args ): void {
		self::ensure( $module );
		self::$registry[ $module ]['fields'][ $panel_id ][ $section_id ][ $field_id ] = $args;
	}

	/**
	 * Return the full schema for a module (panels with nested sections and fields).
	 *
	 * @since 2.0.0
	 *
	 * @param string $module Module ID.
	 * @return array
	 */
	public static function get_schema( string $module ): array {
		$result = [];
		$panels = self::$registry[ $module ]['panels'] ?? [];

		foreach ( $panels as $panel_id => $panel ) {
			$panel_data             = $panel;
			$panel_data['id']       = $panel_id;
			$panel_data['sections'] = [];

			$sections = self::$registry[ $module ]['sections'][ $panel_id ] ?? [];

			foreach ( $sections as $section_id => $section ) {
				$section_data           = $section;
				$section_data['id']     = $section_id;
				$section_data['fields'] = [];

				$fields = self::$registry[ $module ]['fields'][ $panel_id ][ $section_id ] ?? [];

				foreach ( $fields as $field_id => $field ) {
					$field['id']              = $field_id;
					$section_data['fields'][] = $field;
				}

				$panel_data['sections'][] = $section_data;
			}

			$result[] = $panel_data;
		}

		return $result;
	}

	/**
	 * Return all registered panels for a module as a map keyed by panel ID.
	 *
	 * @since 2.0.0
	 *
	 * @param string $module Module ID.
	 * @return array<string, array>
	 */
	public static function get_panels( string $module ): array {
		return self::$registry[ $module ]['panels'] ?? [];
	}

	/**
	 * Return all fields for a panel as a flat map keyed by field ID.
	 *
	 * @since 2.0.0
	 *
	 * @param string $module   Module ID.
	 * @param string $panel_id Panel slug.
	 * @return array<string, array>
	 */
	public static function get_flat_fields( string $module, string $panel_id ): array {
		$result   = [];
		$sections = self::$registry[ $module ]['sections'][ $panel_id ] ?? [];

		foreach ( array_keys( $sections ) as $section_id ) {
			$fields = self::$registry[ $module ]['fields'][ $panel_id ][ $section_id ] ?? [];
			foreach ( $fields as $field_id => $field ) {
				$result[ $field_id ] = $field;
			}
		}

		return $result;
	}

	/**
	 * Sanitize a single value for the given field type.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type  Field type.
	 * @param mixed  $value Raw value.
	 * @return mixed Sanitized value.
	 */
	public static function sanitize_value( string $type, $value ) {
		switch ( $type ) {
			case 'toggle':
				return (bool) $value;
			case 'number':
				return intval( $value );
			case 'url':
				return esc_url_raw( (string) $value );
			case 'textarea':
				return sanitize_textarea_field( (string) $value );
			case 'select':
			case 'radio':
				return sanitize_key( (string) $value );
			case 'checkbox_list':
				if ( ! is_array( $value ) ) {
					return [];
				}
				return array_map( 'sanitize_key', $value );
			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/** @since 2.0.0 */
	private static function ensure( string $module ): void {
		if ( ! isset( self::$registry[ $module ] ) ) {
			self::$registry[ $module ] = [
				'panels'   => [],
				'sections' => [],
				'fields'   => [],
			];
		}
	}
}
