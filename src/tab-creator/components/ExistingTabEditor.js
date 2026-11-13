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
import { Button, TextControl, ToggleControl, Spinner } from '@wordpress/components';
import { apiRequest } from '../../common/utils/api';
import { useToast } from '../../common/hooks/useToast';
import { parseRules, serializeRules } from '../utils/rulesHelpers';
import SearchSelect from './SearchSelect';

/**
 * RoleSelect — compact multi-select dropdown showing chips for each option.
 *
 * @param {Object}   props
 * @param {Array}    props.options   Array of {value, label}.
 * @param {Array}    props.selected  Array of selected value strings.
 * @param {Function} props.onChange  Called with the new selected array.
 * @returns {JSX.Element}
 */
function RoleSelect( { options, selected, onChange } ) {
	var [ open, setOpen ] = useState( false );
	var wrapRef = useRef( null );

	useEffect( function() {
		if ( ! open ) {
			return;
		}
		function handleOutsideClick( e ) {
			if ( wrapRef.current && ! wrapRef.current.contains( e.target ) ) {
				setOpen( false );
			}
		}
		document.addEventListener( 'mousedown', handleOutsideClick );
		return function() {
			document.removeEventListener( 'mousedown', handleOutsideClick );
		};
	}, [ open ] );

	function getSummary() {
		if ( selected.length === 0 ) {
			return __( 'None selected', 'buddy-modules' );
		}
		if ( selected.length === 1 ) {
			var found = options.find( function( o ) { return o.value === selected[ 0 ]; } );
			return found ? found.label : selected[ 0 ];
		}
		return sprintf(
			// translators: %d is the number of selected roles/options.
			__( '%d selected', 'buddy-modules' ),
			selected.length
		);
	}

	function toggleOption( value ) {
		var next = selected.indexOf( value ) !== -1
			? selected.filter( function( v ) { return v !== value; } )
			: selected.concat( [ value ] );
		onChange( next );
	}

	return (
		<div className="bdm-admin-role-select" ref={ wrapRef }>
			<button
				type="button"
				className="bdm-admin-role-select__trigger"
				onClick={ function() { setOpen( function( v ) { return ! v; } ); } }
				aria-expanded={ open }
			>
				<span className="bdm-admin-role-select__summary">{ getSummary() }</span>
				<span className="bdm-admin-role-select__arrow" aria-hidden="true">
					{ open ? '▲' : '▼' }
				</span>
			</button>
			{ open && (
				<div className="bdm-admin-role-select__dropdown">
					<div className="bdm-admin-role-select__chips">
						{ options.map( function( opt ) {
							var isSelected = selected.indexOf( opt.value ) !== -1;
							return (
								<button
									key={ opt.value }
									type="button"
									className={ 'bdm-admin-role-select__chip' + ( isSelected ? ' bdm-admin-role-select__chip--selected' : '' ) }
									onClick={ function() { toggleOption( opt.value ); } }
								>
									{ opt.label }
								</button>
							);
						} ) }
					</div>
				</div>
			) }
		</div>
	);
}

/**
 * @param {Object}   props
 * @param {Object}   props.route
 * @param {Function} props.onSaved  Called after a successful save (closes the modal).
 * @param {Function} props.onCancel Called when the user cancels (closes the modal).
 * @returns {JSX.Element}
 */
export default function ExistingTabEditor( { route, onSaved, onCancel } ) {
	var tabType = route.panel === 'group-existing-tabs' ? 'group' : 'profile';
	var slug    = route.id; // stored as the slug string in route.id.

	var { addToast } = useToast();

	var [ tab,     setTab     ] = useState( null );
	var [ form,    setForm    ] = useState( {
		label_override:  '',
		is_hidden:       0,
		// Rules.
		availability:    [],
		enabled_for:     [],
		visibility:      [],
	} );

	var [ assocGroups,    setAssocGroups    ] = useState( [] );
	var [ excludedGroups, setExcludedGroups ] = useState( [] );
	var [ wpRoles,        setWpRoles        ] = useState( [] );

	var [ loading, setLoading ] = useState( true );
	var [ saving,  setSaving  ] = useState( false );
	var [ errors,  setErrors  ] = useState( {} );

	var initialFormRef = useRef( null );

	// Sub-tabs under existing tab (edit mode for existing tabs can also have sub-tabs via custom tabs with parent association, but legacy admin doesn't show these here — skip for P6 parity).

	// -------------------------------------------------------------------------
	// Load.
	// -------------------------------------------------------------------------

	useEffect( function() {
		apiRequest( { path: '/tab-creator/lookup/roles' } )
			.then( function( data ) { setWpRoles( data.roles || [] ); } );
	}, [] );

	useEffect( function() {
		if ( ! slug ) {
			setLoading( false );
			return;
		}

		setLoading( true );

		apiRequest( { path: '/tab-creator/existing-tabs/' + slug + '?tab_type=' + tabType } )
			.then( function( data ) {
				setTab( data );

				var parsed = parseRules( data.rules || [], tabType );

				var nextForm = {
					label_override:  data.label_override || '',
					is_hidden:       data.is_hidden ? 1 : 0,
					availability:    parsed.availability,
					enabled_for:     parsed.enabled_for,
					visibility:      parsed.visibility,
				};

				setForm( nextForm );
				initialFormRef.current = JSON.stringify( nextForm );

				if ( 'group' === tabType ) {
					if ( parsed.associated_groups.length > 0 ) {
						fetchGroups( '' ).then( function( all ) {
							var found = all.filter( function( g ) {
								return parsed.associated_groups.indexOf( g.id ) !== -1;
							} );
							setAssocGroups( found );
						} );
					}
					if ( parsed.excluded_groups.length > 0 ) {
						fetchGroups( '' ).then( function( all ) {
							var found = all.filter( function( g ) {
								return parsed.excluded_groups.indexOf( g.id ) !== -1;
							} );
							setExcludedGroups( found );
						} );
					}
				}
			} )
			.catch( function() {
				addToast( __( 'Failed to load tab settings.', 'buddy-modules' ), 'error' );
			} )
			.finally( function() { setLoading( false ); } );
	}, [ slug, tabType ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// -------------------------------------------------------------------------
	// Lookup.
	// -------------------------------------------------------------------------

	function fetchGroups( search ) {
		return apiRequest( { path: '/tab-creator/lookup/groups?search=' + encodeURIComponent( search ) + '&limit=30' } )
			.then( function( data ) {
				return ( data.groups || [] ).map( function( g ) {
					return { id: g.id, label: g.name };
				} );
			} );
	}

	// -------------------------------------------------------------------------
	// Field helpers.
	// -------------------------------------------------------------------------

	function setField( key, value ) {
		setForm( function( prev ) { return Object.assign( {}, prev, { [ key ]: value } ); } );
		if ( errors[ key ] ) {
			setErrors( function( prev ) {
				var next = Object.assign( {}, prev );
				delete next[ key ];
				return next;
			} );
		}
	}

	function toggleCheckbox( stateKey, value ) {
		setForm( function( prev ) {
			var arr  = prev[ stateKey ] || [];
			var next = arr.indexOf( value ) !== -1
				? arr.filter( function( v ) { return v !== value; } )
				: arr.concat( [ value ] );
			return Object.assign( {}, prev, { [ stateKey ]: next } );
		} );
	}

	// -------------------------------------------------------------------------
	// Save.
	// -------------------------------------------------------------------------

	function handleSave() {
		setSaving( true );
		setErrors( {} );

		var rules = serializeRules(
			{ availability: form.availability, enabled_for: form.enabled_for, visibility: [] },
			[],
			assocGroups.map( function( g ) { return g.id; } ),
			excludedGroups.map( function( g ) { return g.id; } )
		);

		var body = {
			tab_type:        tabType,
			label_override:  form.label_override,
			is_hidden:       form.is_hidden,
			rules:           rules,
		};

		apiRequest( { method: 'PATCH', path: '/tab-creator/existing-tabs/' + slug, data: body } )
			.then( function() {
				addToast( __( 'Settings saved.', 'buddy-modules' ), 'success' );
				onSaved();
			} )
			.catch( function( err ) {
				if ( err && err.message ) {
					setErrors( { _server: err.message } );
				} else {
					setErrors( { _server: __( 'Could not save settings. Please try again.', 'buddy-modules' ) } );
				}
			} )
			.finally( function() { setSaving( false ); } );
	}

	function handleCancel() {
		var isDirty = initialFormRef.current
			? JSON.stringify( form ) !== initialFormRef.current
			: false;

		if ( isDirty ) {
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( __( 'Discard unsaved changes?', 'buddy-modules' ) ) ) {
				return;
			}
		}

		onCancel();
	}

	// -------------------------------------------------------------------------
	// Options.
	// -------------------------------------------------------------------------

	var profileAvailabilityOptions = [
		{ value: 'all_members', label: __( 'All Members', 'buddy-modules' ) },
		{ value: 'none_wp',     label: __( 'None (hide from everyone)', 'buddy-modules' ) },
	].concat(
		wpRoles.map( function( r ) { return { value: r.slug, label: r.label }; } )
	);

	var enabledForOptions = [
		{ value: 'all',     label: __( 'All Groups', 'buddy-modules' ) },
		{ value: 'public',  label: __( 'Public Groups', 'buddy-modules' ) },
		{ value: 'private', label: __( 'Private Groups', 'buddy-modules' ) },
		{ value: 'hidden',  label: __( 'Hidden Groups', 'buddy-modules' ) },
		{ value: 'none',    label: __( 'None', 'buddy-modules' ) },
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

	var defaultLabel = tab ? tab.default_label : slug;

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
					<Button variant="primary" isBusy={ saving } disabled={ saving } onClick={ handleSave }>
						{ __( 'Save Settings', 'buddy-modules' ) }
					</Button>
				</div>
			</div>

			{ errors._server && (
				<div className="bdm-admin-tab-editor__server-error">
					{ errors._server }
				</div>
			) }

			{ /* Basic overrides */ }
			<div className="bdm-admin-tab-editor__section">
				<h4 className="bdm-admin-tab-editor__section-title">
					{ __( 'Display', 'buddy-modules' ) }
				</h4>

				<div className="bdm-admin-tab-editor__field">
					<TextControl
						label={ __( 'Label Override', 'buddy-modules' ) }
						value={ form.label_override }
						onChange={ function( v ) { setField( 'label_override', v ); } }
						placeholder={ defaultLabel }
						help={ __( 'Leave blank to use the default label.', 'buddy-modules' ) }
					/>
				</div>

				<div className="bdm-admin-tab-editor__row">
					<div className="bdm-admin-tab-editor__field">
						<ToggleControl
							label={ __( 'Hide Tab', 'buddy-modules' ) }
							checked={ !! form.is_hidden }
							onChange={ function( v ) { setField( 'is_hidden', v ? 1 : 0 ); } }
						/>
					</div>
				</div>
			</div>

			{ /* Availability override (profile) */ }
			{ 'profile' === tabType && (
				<div className="bdm-admin-tab-editor__section">
					<h4 className="bdm-admin-tab-editor__section-title">
						{ __( 'Availability Override', 'buddy-modules' ) }
					</h4>
					<div className="bdm-admin-tab-editor__field">
						<RoleSelect
							options={ profileAvailabilityOptions }
							selected={ form.availability }
							onChange={ function( v ) { setField( 'availability', v ); } }
						/>
					</div>
				</div>
			) }

			{ /* Enabled For override (group) */ }
			{ 'group' === tabType && (
				<div className="bdm-admin-tab-editor__section">
					<h4 className="bdm-admin-tab-editor__section-title">
						{ __( 'Enabled For', 'buddy-modules' ) }
					</h4>
					<div className="bdm-admin-tab-editor__field">
						<RoleSelect
							options={ enabledForOptions }
							selected={ form.enabled_for }
							onChange={ function( v ) { setField( 'enabled_for', v ); } }
						/>
					</div>

					<div className="bdm-admin-tab-editor__field">
						<label className="bdm-admin-tab-editor__label">
							{ __( 'Associated Groups', 'buddy-modules' ) }
						</label>
						<SearchSelect
							selectedItems={ assocGroups }
							onChange={ setAssocGroups }
							fetchOptions={ fetchGroups }
							placeholder={ __( 'Search groups…', 'buddy-modules' ) }
						/>
					</div>

					<div className="bdm-admin-tab-editor__field">
						<label className="bdm-admin-tab-editor__label">
							{ __( 'Excluded Groups', 'buddy-modules' ) }
						</label>
						<SearchSelect
							selectedItems={ excludedGroups }
							onChange={ setExcludedGroups }
							fetchOptions={ fetchGroups }
							placeholder={ __( 'Search groups…', 'buddy-modules' ) }
						/>
					</div>
				</div>
			) }

			{ /* Footer */ }
			<div className="bdm-admin-tab-editor__footer">
				<Button variant="secondary" onClick={ handleCancel }>
					{ __( 'Cancel', 'buddy-modules' ) }
				</Button>
				<Button variant="primary" isBusy={ saving } disabled={ saving } onClick={ handleSave }>
					{ __( 'Save Settings', 'buddy-modules' ) }
				</Button>
			</div>
		</div>
	);
}
