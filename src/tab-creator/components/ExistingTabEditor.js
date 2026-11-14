/**
 * ExistingTabEditor — Override settings for a built-in BuddyBoss tab.
 *
 * Receives:
 *   route.view  'edit'
 *   route.id    slug (string — stored in route.id as text for existing tabs)
 *   route.panel 'profile-existing-tabs' | 'group-existing-tabs'
 *
 * @since 2.0.0
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	TextControl,
	ToggleControl,
	Spinner,
} from '@wordpress/components';
import { apiRequest } from '../../common/utils/api';
import { useToast } from '../../common/hooks/useToast';
import { parseRules, serializeRules } from '../utils/rulesHelpers';
import SearchSelect from './SearchSelect';
import RoleSelect from './RoleSelect';
import { FieldRow } from '../../common/components/FieldRow';

/**
 * @param {Object}   props
 * @param {Object}   props.route
 * @param {Function} props.onSaved  Called after a successful save (closes the modal).
 * @param {Function} props.onCancel Called when the user cancels (closes the modal).
 * @return {JSX.Element} The existing tab editor.
 */
export default function ExistingTabEditor( { route, onSaved, onCancel } ) {
	const tabType = route.panel === 'group-existing-tabs' ? 'group' : 'profile';
	const slug = route.id; // stored as the slug string in route.id.

	const { addToast } = useToast();

	const [ tab, setTab ] = useState( null );
	const [ form, setForm ] = useState( {
		label_override: '',
		is_hidden: 0,
		// Rules.
		availability: [],
		enabled_for: [],
		visibility: [],
	} );

	const [ assocGroups, setAssocGroups ] = useState( [] );
	const [ excludedGroups, setExcludedGroups ] = useState( [] );
	const [ wpRoles, setWpRoles ] = useState( [] );

	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ errors, setErrors ] = useState( {} );

	const initialFormRef = useRef( null );
	// Set when a user/group picker changes (those lists live outside `form`).
	const pickersChangedRef = useRef( false );

	// Sub-tabs under existing tab (edit mode for existing tabs can also have sub-tabs via custom tabs with parent association, but legacy admin doesn't show these here — skip for P6 parity).

	// -------------------------------------------------------------------------
	// Load.
	// -------------------------------------------------------------------------

	useEffect( function () {
		apiRequest( { path: '/tab-creator/lookup/roles' } ).then(
			function ( data ) {
				setWpRoles( data.roles || [] );
			}
		);
	}, [] );

	useEffect(
		function () {
			if ( ! slug ) {
				setLoading( false );
				return;
			}

			setLoading( true );

			apiRequest( {
				path:
					'/tab-creator/existing-tabs/' +
					slug +
					'?tab_type=' +
					tabType,
			} )
				.then( function ( data ) {
					setTab( data );

					const parsed = parseRules( data.rules || [], tabType );

					const nextForm = {
						label_override: data.label_override || '',
						is_hidden: data.is_hidden ? 1 : 0,
						availability: parsed.availability,
						enabled_for: parsed.enabled_for,
						visibility: parsed.visibility,
					};

					setForm( nextForm );
					initialFormRef.current = JSON.stringify( nextForm );

					if ( 'group' === tabType ) {
						if ( parsed.associated_groups.length > 0 ) {
							fetchGroups( '' ).then( function ( all ) {
								const found = all.filter( function ( g ) {
									return (
										parsed.associated_groups.indexOf(
											g.id
										) !== -1
									);
								} );
								setAssocGroups( found );
							} );
						}
						if ( parsed.excluded_groups.length > 0 ) {
							fetchGroups( '' ).then( function ( all ) {
								const found = all.filter( function ( g ) {
									return (
										parsed.excluded_groups.indexOf(
											g.id
										) !== -1
									);
								} );
								setExcludedGroups( found );
							} );
						}
					}
				} )
				.catch( function () {
					addToast(
						__( 'Failed to load tab settings.', 'buddy-modules' ),
						'error'
					);
				} )
				.finally( function () {
					setLoading( false );
				} );
		},
		[ slug, tabType ]
	); // eslint-disable-line react-hooks/exhaustive-deps

	// -------------------------------------------------------------------------
	// Lookup.
	// -------------------------------------------------------------------------

	function fetchGroups( search ) {
		return apiRequest( {
			path:
				'/tab-creator/lookup/groups?search=' +
				encodeURIComponent( search ) +
				'&limit=30',
		} ).then( function ( data ) {
			return ( data.groups || [] ).map( function ( g ) {
				return { id: g.id, label: g.name };
			} );
		} );
	}

	// -------------------------------------------------------------------------
	// Field helpers.
	// -------------------------------------------------------------------------

	function setField( key, value ) {
		setForm( function ( prev ) {
			return Object.assign( {}, prev, { [ key ]: value } );
		} );
		if ( errors[ key ] ) {
			setErrors( function ( prev ) {
				const next = Object.assign( {}, prev );
				delete next[ key ];
				return next;
			} );
		}
	}

	// -------------------------------------------------------------------------
	// Save.
	// -------------------------------------------------------------------------

	function handleSave() {
		setSaving( true );
		setErrors( {} );

		const rules = serializeRules(
			{ availability: form.availability, enabled_for: form.enabled_for },
			[],
			assocGroups.map( function ( g ) {
				return g.id;
			} ),
			excludedGroups.map( function ( g ) {
				return g.id;
			} )
		);

		const body = {
			tab_type: tabType,
			label_override: form.label_override,
			is_hidden: form.is_hidden,
			rules,
		};

		apiRequest( {
			method: 'PATCH',
			path: '/tab-creator/existing-tabs/' + slug,
			data: body,
		} )
			.then( function () {
				addToast( __( 'Settings saved.', 'buddy-modules' ), 'success' );
				onSaved();
			} )
			.catch( function ( err ) {
				if ( err && err.message ) {
					setErrors( { _server: err.message } );
				} else {
					setErrors( {
						_server: __(
							'Could not save settings. Please try again.',
							'buddy-modules'
						),
					} );
				}
			} )
			.finally( function () {
				setSaving( false );
			} );
	}

	function handleCancel() {
		const isDirty =
			pickersChangedRef.current ||
			( initialFormRef.current
				? JSON.stringify( form ) !== initialFormRef.current
				: false );

		if ( isDirty ) {
			if (
				// eslint-disable-next-line no-alert
				! window.confirm(
					__( 'Discard unsaved changes?', 'buddy-modules' )
				)
			) {
				return;
			}
		}

		onCancel();
	}

	// -------------------------------------------------------------------------
	// Options.
	// -------------------------------------------------------------------------

	const profileAvailabilityOptions = [
		{ value: 'all_members', label: __( 'All Members', 'buddy-modules' ) },
		{
			value: 'none_wp',
			label: __( 'None (hide from everyone)', 'buddy-modules' ),
		},
	].concat(
		wpRoles.map( function ( r ) {
			return { value: r.slug, label: r.label };
		} )
	);

	const enabledForOptions = [
		{ value: 'all', label: __( 'All Groups', 'buddy-modules' ) },
		{ value: 'public', label: __( 'Public Groups', 'buddy-modules' ) },
		{ value: 'private', label: __( 'Private Groups', 'buddy-modules' ) },
		{ value: 'hidden', label: __( 'Hidden Groups', 'buddy-modules' ) },
		{ value: 'none', label: __( 'None', 'buddy-modules' ) },
	];

	// -------------------------------------------------------------------------
	// Render.
	// -------------------------------------------------------------------------

	if ( loading ) {
		return (
			<div className="bdm-admin-tab-editor__loading">
				<Spinner />
			</div>
		);
	}

	const defaultLabel = tab ? tab.default_label : slug;

	return (
		<div className="bdm-admin-tab-editor">
			{ /* Header */ }
			<div className="bdm-admin-tab-editor__header">
				<h3 className="bdm-admin-tab-editor__title">
					{ sprintf(
						// translators: %s is the tab's default label.
						__( 'Override: %s', 'buddy-modules' ),
						defaultLabel
					) }
				</h3>
				<div className="bdm-admin-tab-editor__header-actions">
					<Button variant="secondary" onClick={ handleCancel }>
						{ __( 'Cancel', 'buddy-modules' ) }
					</Button>
					<Button
						variant="primary"
						isBusy={ saving }
						disabled={ saving }
						onClick={ handleSave }
					>
						{ __( 'Save Settings', 'buddy-modules' ) }
					</Button>
				</div>
			</div>

			{ errors._server && (
				<div className="bdm-admin-tab-editor__server-error">
					{ errors._server }
				</div>
			) }

			{ /* Settings rows (Platform settings-form layout) */ }
			<div className="bdm-admin-tab-editor__fields">
				<FieldRow
					label={ __( 'Label Override', 'buddy-modules' ) }
					htmlFor="bdm-admin-existing-tab-label"
					help={ __(
						'Leave blank to use the default label.',
						'buddy-modules'
					) }
				>
					<TextControl
						id="bdm-admin-existing-tab-label"
						value={ form.label_override }
						onChange={ function ( v ) {
							setField( 'label_override', v );
						} }
						placeholder={ defaultLabel }
					/>
				</FieldRow>

				<FieldRow label={ __( 'Hide Tab', 'buddy-modules' ) }>
					<ToggleControl
						label={ __(
							'Hide this tab from the navigation.',
							'buddy-modules'
						) }
						checked={ !! form.is_hidden }
						onChange={ function ( v ) {
							setField( 'is_hidden', v ? 1 : 0 );
						} }
					/>
				</FieldRow>

				{ /* Availability override (profile) */ }
				{ 'profile' === tabType && (
					<FieldRow
						label={ __( 'Availability Override', 'buddy-modules' ) }
						htmlFor="bdm-admin-existing-tab-availability"
					>
						<RoleSelect
							id="bdm-admin-existing-tab-availability"
							options={ profileAvailabilityOptions }
							selected={ form.availability }
							onChange={ function ( v ) {
								setField( 'availability', v );
							} }
						/>
					</FieldRow>
				) }

				{ /* Enabled For override (group) */ }
				{ 'group' === tabType && (
					<FieldRow
						label={ __( 'Enabled For', 'buddy-modules' ) }
						htmlFor="bdm-admin-existing-tab-enabled-for"
					>
						<RoleSelect
							id="bdm-admin-existing-tab-enabled-for"
							options={ enabledForOptions }
							selected={ form.enabled_for }
							onChange={ function ( v ) {
								setField( 'enabled_for', v );
							} }
						/>
					</FieldRow>
				) }

				{ 'group' === tabType && (
					<FieldRow
						label={ __( 'Associated Groups', 'buddy-modules' ) }
						htmlFor="bdm-admin-existing-tab-assoc-groups"
					>
						<SearchSelect
							inputId="bdm-admin-existing-tab-assoc-groups"
							selectedItems={ assocGroups }
							onChange={ function ( items ) {
								pickersChangedRef.current = true;
								setAssocGroups( items );
							} }
							fetchOptions={ fetchGroups }
							placeholder={ __(
								'Search groups…',
								'buddy-modules'
							) }
						/>
					</FieldRow>
				) }

				{ 'group' === tabType && (
					<FieldRow
						label={ __( 'Excluded Groups', 'buddy-modules' ) }
						htmlFor="bdm-admin-existing-tab-excluded-groups"
					>
						<SearchSelect
							inputId="bdm-admin-existing-tab-excluded-groups"
							selectedItems={ excludedGroups }
							onChange={ function ( items ) {
								pickersChangedRef.current = true;
								setExcludedGroups( items );
							} }
							fetchOptions={ fetchGroups }
							placeholder={ __(
								'Search groups…',
								'buddy-modules'
							) }
						/>
					</FieldRow>
				) }
			</div>

			{ /* Footer */ }
			<div className="bdm-admin-tab-editor__footer">
				<Button variant="secondary" onClick={ handleCancel }>
					{ __( 'Cancel', 'buddy-modules' ) }
				</Button>
				<Button
					variant="primary"
					isBusy={ saving }
					disabled={ saving }
					onClick={ handleSave }
				>
					{ __( 'Save Settings', 'buddy-modules' ) }
				</Button>
			</div>
		</div>
	);
}
