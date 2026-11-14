/**
 * Generic settings form.
 *
 * Renders sections and typed fields from a panel's schema. Calls onChange
 * with ( fieldId, newValue ) whenever a field changes.
 *
 * @since 2.0.0
 */

import {
	ToggleControl,
	TextControl,
	TextareaControl,
	SelectControl,
	RadioControl,
	CheckboxControl,
} from '@wordpress/components';

// ── Field renderers ───────────────────────────────────────────────────────────

/**
 * Render the appropriate input component for a field's type.
 *
 * @since 2.0.0
 *
 * @param {Object}   props
 * @param {Object}   props.field    Field schema (id, type, options, …).
 * @param {*}        props.value    Current value.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element|null} The field control, or null for an unknown field type.
 */
function FieldInput( { field, value, onChange } ) {
	switch ( field.type ) {
		case 'toggle':
			return (
				<ToggleControl
					checked={ !! value }
					onChange={ onChange }
					__nextHasNoMarginBottom
				/>
			);

		case 'number':
			return (
				<TextControl
					type="number"
					value={
						null !== value && undefined !== value
							? String( value )
							: ''
					}
					onChange={ function ( v ) {
						onChange( v );
					} }
					__nextHasNoMarginBottom
				/>
			);

		case 'url':
			return (
				<TextControl
					type="url"
					value={ value || '' }
					onChange={ onChange }
					__nextHasNoMarginBottom
				/>
			);

		case 'textarea':
			return (
				<TextareaControl
					value={ value || '' }
					onChange={ onChange }
					__nextHasNoMarginBottom
				/>
			);

		case 'select':
			return (
				<SelectControl
					value={ value || '' }
					options={ field.options || [] }
					onChange={ onChange }
					__nextHasNoMarginBottom
				/>
			);

		case 'radio':
			return (
				<RadioControl
					selected={ value || '' }
					options={ field.options || [] }
					onChange={ onChange }
				/>
			);

		case 'checkbox_list':
			return (
				<div className="bdm-admin-settings-form__checkbox-list">
					{ ( field.options || [] ).map( function ( opt ) {
						const checked =
							Array.isArray( value ) &&
							value.includes( opt.value );
						return (
							<CheckboxControl
								key={ opt.value }
								label={ opt.label }
								checked={ checked }
								onChange={ function ( isChecked ) {
									let next = Array.isArray( value )
										? value.slice()
										: [];
									if ( isChecked ) {
										if ( ! next.includes( opt.value ) ) {
											next.push( opt.value );
										}
									} else {
										next = next.filter( function ( v ) {
											return v !== opt.value;
										} );
									}
									onChange( next );
								} }
								__nextHasNoMarginBottom
							/>
						);
					} ) }
				</div>
			);

		case 'text':
		default:
			return (
				<TextControl
					value={ value || '' }
					onChange={ onChange }
					__nextHasNoMarginBottom
				/>
			);
	}
}

// ── SettingsForm ──────────────────────────────────────────────────────────────

/**
 * Render a full settings form for a panel's sections and fields.
 *
 * @since 2.0.0
 *
 * @param {Object}   props
 * @param {Array}    props.sections Array of section objects with nested fields.
 * @param {Object}   props.values   Current field values keyed by field ID.
 * @param {Function} props.onChange Called with ( fieldId, newValue ) on change.
 * @return {JSX.Element} The settings form.
 */
export function SettingsForm( { sections, values, onChange } ) {
	if ( ! sections || ! sections.length ) {
		return null;
	}

	return (
		<div className="bdm-admin-settings-form">
			{ sections.map( function ( section ) {
				return (
					<div
						key={ section.id }
						className="bdm-admin-settings-form__section"
					>
						{ section.label && (
							<h3 className="bdm-admin-settings-form__section-title">
								{ section.label }
							</h3>
						) }
						{ section.description && (
							<p className="bdm-admin-settings-form__section-desc">
								{ section.description }
							</p>
						) }

						<div className="bdm-admin-settings-form__fields">
							{ ( section.fields || [] ).map( function ( field ) {
								return (
									<div
										key={ field.id }
										className={
											'bdm-admin-settings-form__row' +
											' bdm-admin-settings-form__row--' +
											field.type
										}
									>
										<div className="bdm-admin-settings-form__meta">
											<span className="bdm-admin-settings-form__label">
												{ field.label }
											</span>
											{ field.description && (
												<p className="bdm-admin-settings-form__desc">
													{ field.description }
												</p>
											) }
										</div>
										<div className="bdm-admin-settings-form__control">
											<FieldInput
												field={ field }
												value={ values[ field.id ] }
												onChange={ function ( val ) {
													onChange( field.id, val );
												} }
											/>
										</div>
									</div>
								);
							} ) }
						</div>
					</div>
				);
			} ) }
		</div>
	);
}
