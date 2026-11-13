/**
 * TabEditor — Add or Edit a custom tab (profile or group).
 *
 * Receives:
 *   route.view  'add' | 'edit'
 *   route.id    tab ID (edit mode only)
 *   route.panel 'profile-tabs' | 'group-tabs'
 *
 * Done-when (P6): creating a tab via this editor produces the same result
 * as creating it via the legacy PHP admin.
 *
 * @since 2.0.0
 */

import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, TextControl, SelectControl, ToggleControl, Spinner } from '@wordpress/components';
import { apiRequest } from '../../common/utils/api';
import { useToast } from '../../common/hooks/useToast';
import { parseRules, serializeRules } from '../utils/rulesHelpers';
import SearchSelect from './SearchSelect';

/** Slugify a label (mirrors sanitize_title behaviour). */
function slugify( label ) {
	return label
		.toLowerCase()
		.trim()
		.replace( /[^\w\s-]/g, '' )
		.replace( /[\s_]+/g, '-' )
		.replace( /^-+|-+$/g, '' );
}

/**
 * @param {Object}   props
 * @param {Object}   props.route
 * @param {Function} props.navigate
 * @returns {JSX.Element}
 */
export default function TabEditor( { route, navigate } ) {
	var tabType   = route.panel === 'group-tabs' ? 'group' : 'profile';
	var editId    = route.view === 'edit' ? route.id : null;
	var isNew     = ! editId;

	var { addToast } = useToast();

	// Form state.
	var [ form, setForm ] = useState( {
		label:            '',
		slug:             '',
		status:           'active',
		is_default:       0,
		site_admin_only:  0,
		content_source:   'content',
		content:          '',
		external_url:     '',
		url_new_tab:      0,
		// Availability / Enabled-for.
		availability:     [],
		enabled_for:      [],
		// Visibility.
		visibility:       [],
	} );

	// SearchSelect item state (needs {id, label} not just IDs).
	var [ assocUsers,    setAssocUsers    ] = useState( [] );
	var [ assocGroups,   setAssocGroups   ] = useState( [] );
	var [ excludedGroups, setExcludedGroups ] = useState( [] );

	// Sub-tabs.
	var [ subtabs,         setSubtabs         ] = useState( [] );
	var [ subtabForm,      setSubtabForm      ] = useState( null );   // null = closed; {id, label, slug, content, is_enabled} = open
	var [ subtabSlugged,   setSubtabSlugged   ] = useState( false );  // user manually edited subtab slug

	// WP roles and groups (fetched once).
	var [ wpRoles,  setWpRoles  ] = useState( [] );

	var [ loading,  setLoading  ] = useState( !! editId );
	var [ saving,   setSaving   ] = useState( false );
	var [ errors,   setErrors   ] = useState( {} );
	var [ sluggedByUser, setSluggedByUser ] = useState( false );

	// Track dirty state to warn on Cancel.
	var initialFormRef = useRef( null );

	// -------------------------------------------------------------------------
	// Load tab data for edit mode.
	// -------------------------------------------------------------------------

	useEffect( function() {
		apiRequest( { path: '/tab-creator/lookup/roles' } )
			.then( function( data ) { setWpRoles( data.roles || [] ); } );
	}, [] );

	useEffect( function() {
		if ( ! editId ) {
			setLoading( false );
			return;
		}

		setLoading( true );

		apiRequest( { path: '/tab-creator/tabs/' + editId } )
			.then( function( tab ) {
				var parsed = parseRules( tab.rules, tabType );

				var nextForm = {
					label:           tab.label || '',
					slug:            tab.slug  || '',
					status:          tab.status || 'active',
					is_default:      tab.is_default ? 1 : 0,
					site_admin_only: tab.site_admin_only ? 1 : 0,
					content_source:  tab.content_source || 'content',
					content:         tab.content || '',
					external_url:    tab.external_url || '',
					url_new_tab:     tab.url_new_tab ? 1 : 0,
					availability:    parsed.availability,
					enabled_for:     parsed.enabled_for,
					visibility:      parsed.visibility,
				};
				setForm( nextForm );
				setSluggedByUser( true ); // existing slug → don't auto-rewrite.
				initialFormRef.current = JSON.stringify( nextForm );

				// Resolve associated user IDs → {id, label}.
				if ( parsed.associated_users.length > 0 ) {
					resolveUsers( parsed.associated_users ).then( setAssocUsers );
				}
				if ( 'group' === tabType ) {
					if ( parsed.associated_groups.length > 0 ) {
						resolveGroups( parsed.associated_groups ).then( setAssocGroups );
					}
					if ( parsed.excluded_groups.length > 0 ) {
						resolveGroups( parsed.excluded_groups ).then( setExcludedGroups );
					}
				}

				// Load sub-tabs.
				return apiRequest( { path: '/tab-creator/tabs?tab_type=' + tabType + '&parent_id=' + editId } );
			} )
			.then( function( data ) {
				if ( data ) {
					setSubtabs( data.tabs || [] );
				}
			} )
			.catch( function() {
				addToast( __( 'Failed to load tab.', 'buddy-modules' ), 'error' );
			} )
			.finally( function() { setLoading( false ); } );
	}, [ editId ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// -------------------------------------------------------------------------
	// Lookup helpers.
	// -------------------------------------------------------------------------

	function fetchUsers( search ) {
		return apiRequest( { path: '/tab-creator/lookup/users?search=' + encodeURIComponent( search ) + '&limit=30' } )
			.then( function( data ) {
				return ( data.users || [] ).map( function( u ) {
					return { id: u.id, label: u.display_name + ' (' + u.user_login + ')' };
				} );
			} );
	}

	function fetchGroups( search ) {
		return apiRequest( { path: '/tab-creator/lookup/groups?search=' + encodeURIComponent( search ) + '&limit=30' } )
			.then( function( data ) {
				return ( data.groups || [] ).map( function( g ) {
					return { id: g.id, label: g.name };
				} );
			} );
	}

	function resolveUsers( ids ) {
		return fetchUsers( '' ).then( function( all ) {
			var found = all.filter( function( u ) { return ids.indexOf( u.id ) !== -1; } );
			// Fallback for IDs not in the first 30.
			var missingIds = ids.filter( function( id ) {
				return ! found.some( function( u ) { return u.id === id; } );
			} );
			var fallback = missingIds.map( function( id ) { return { id: id, label: 'User #' + id }; } );
			return found.concat( fallback );
		} );
	}

	function resolveGroups( ids ) {
		return fetchGroups( '' ).then( function( all ) {
			var found = all.filter( function( g ) { return ids.indexOf( g.id ) !== -1; } );
			var missingIds = ids.filter( function( id ) {
				return ! found.some( function( g ) { return g.id === id; } );
			} );
			var fallback = missingIds.map( function( id ) { return { id: id, label: 'Group #' + id }; } );
			return found.concat( fallback );
		} );
	}

	// -------------------------------------------------------------------------
	// Field change handlers.
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

	function handleLabelChange( val ) {
		setField( 'label', val );
		if ( ! sluggedByUser ) {
			setForm( function( prev ) { return Object.assign( {}, prev, { label: val, slug: slugify( val ) } ); } );
		}
	}

	function handleSlugChange( val ) {
		setSluggedByUser( true );
		setField( 'slug', val );
	}

	function toggleCheckbox( stateKey, value ) {
		setForm( function( prev ) {
			var arr = prev[ stateKey ] || [];
			var next = arr.indexOf( value ) !== -1
				? arr.filter( function( v ) { return v !== value; } )
				: arr.concat( [ value ] );
			return Object.assign( {}, prev, { [ stateKey ]: next } );
		} );
	}

	// -------------------------------------------------------------------------
	// Save.
	// -------------------------------------------------------------------------

	function validate() {
		var errs = {};
		if ( ! form.label.trim() ) {
			errs.label = __( 'Label is required.', 'buddy-modules' );
		}
		return errs;
	}

	function handleSave() {
		var errs = validate();
		if ( Object.keys( errs ).length ) {
			setErrors( errs );
			return;
		}

		setSaving( true );
		setErrors( {} );

		var rules = serializeRules(
			{ availability: form.availability, enabled_for: form.enabled_for, visibility: form.visibility },
			assocUsers.map( function( u ) { return u.id; } ),
			assocGroups.map( function( g ) { return g.id; } ),
			excludedGroups.map( function( g ) { return g.id; } )
		);

		var body = {
			tab_type:        tabType,
			label:           form.label,
			slug:            form.slug,
			status:          form.status,
			is_default:      form.is_default,
			site_admin_only: form.site_admin_only,
			content_source:  form.content_source,
			content:         form.content,
			external_url:    form.external_url,
			url_new_tab:     form.url_new_tab,
			rules:           rules,
		};

		var req = isNew
			? apiRequest( { method: 'POST', path: '/tab-creator/tabs', data: body } )
			: apiRequest( { method: 'PATCH', path: '/tab-creator/tabs/' + editId, data: body } );

		req
			.then( function() {
				addToast( isNew
					? __( 'Tab created.', 'buddy-modules' )
					: __( 'Tab saved.', 'buddy-modules' ),
				'success' );
				navigate( { module: route.module, panel: route.panel } );
			} )
			.catch( function( err ) {
				// WP REST errors arrive as { code, message, data }.
				if ( err && err.message ) {
					setErrors( { _server: err.message } );
				} else {
					setErrors( { _server: __( 'Could not save tab. Please try again.', 'buddy-modules' ) } );
				}
			} )
			.finally( function() { setSaving( false ); } );
	}

	function handleCancel() {
		var isDirty = initialFormRef.current
			? JSON.stringify( form ) !== initialFormRef.current
			: form.label.trim() !== '';

		if ( isDirty ) {
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( __( 'Discard unsaved changes?', 'buddy-modules' ) ) ) {
				return;
			}
		}

		navigate( { module: route.module, panel: route.panel } );
	}

	// -------------------------------------------------------------------------
	// Sub-tab management.
	// -------------------------------------------------------------------------

	function handleAddSubtab() {
		setSubtabForm( { id: null, label: '', slug: '', content: '', is_enabled: 1 } );
		setSubtabSlugged( false );
	}

	function handleEditSubtab( st ) {
		setSubtabForm( { id: st.id, label: st.label, slug: st.slug, content: st.content || '', is_enabled: st.is_enabled } );
		setSubtabSlugged( true );
	}

	function handleSubtabLabelChange( val ) {
		setSubtabForm( function( prev ) {
			var next = Object.assign( {}, prev, { label: val } );
			if ( ! subtabSlugged ) {
				next.slug = slugify( val );
			}
			return next;
		} );
	}

	function handleSubtabSlugChange( val ) {
		setSubtabSlugged( true );
		setSubtabForm( function( prev ) { return Object.assign( {}, prev, { slug: val } ); } );
	}

	function handleSubtabSave() {
		if ( ! subtabForm || ! subtabForm.label.trim() ) {
			return;
		}

		var body = {
			tab_type:    tabType,
			parent_id:   editId || 0,
			label:       subtabForm.label,
			slug:        subtabForm.slug,
			content:     subtabForm.content,
			is_enabled:  subtabForm.is_enabled,
			status:      'active',
			content_source: 'content',
		};

		var req = subtabForm.id
			? apiRequest( { method: 'PATCH', path: '/tab-creator/tabs/' + subtabForm.id, data: body } )
			: apiRequest( { method: 'POST', path: '/tab-creator/tabs', data: body } );

		req
			.then( function( saved ) {
				if ( subtabForm.id ) {
					setSubtabs( function( prev ) {
						return prev.map( function( st ) { return st.id === subtabForm.id ? saved : st; } );
					} );
				} else {
					setSubtabs( function( prev ) { return prev.concat( [ saved ] ); } );
				}
				setSubtabForm( null );
				addToast( __( 'Subtab saved.', 'buddy-modules' ), 'success' );
			} )
			.catch( function() {
				addToast( __( 'Could not save subtab.', 'buddy-modules' ), 'error' );
			} );
	}

	function handleSubtabDelete( st ) {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( sprintf(
			// translators: %s is the subtab label.
			__( 'Delete "%s"?', 'buddy-modules' ),
			st.label
		) ) ) {
			return;
		}

		apiRequest( { method: 'DELETE', path: '/tab-creator/tabs/' + st.id } )
			.then( function() {
				setSubtabs( function( prev ) { return prev.filter( function( s ) { return s.id !== st.id; } ); } );
				addToast( __( 'Subtab deleted.', 'buddy-modules' ), 'success' );
			} )
			.catch( function() {
				addToast( __( 'Could not delete subtab.', 'buddy-modules' ), 'error' );
			} );
	}

	// -------------------------------------------------------------------------
	// Options lists.
	// -------------------------------------------------------------------------

	var availabilityOptions = [
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

	var profileVisibilityOptions = [
		{ value: 'do_not_modify', label: __( 'Do Not Modify', 'buddy-modules' ) },
		{ value: 'anyone',        label: __( 'Anyone', 'buddy-modules' ) },
		{ value: 'none_remove',   label: __( 'None (remove tab)', 'buddy-modules' ) },
		{ value: 'logged_in',     label: __( 'Logged-in Users', 'buddy-modules' ) },
		{ value: 'owner_only',    label: __( 'Profile Owner Only', 'buddy-modules' ) },
		{ value: 'friends',       label: __( 'Friends', 'buddy-modules' ) },
		{ value: 'site_admin',    label: __( 'Site Admins', 'buddy-modules' ) },
	].concat(
		wpRoles.map( function( r ) { return { value: r.slug, label: r.label }; } )
	);

	var groupVisibilityOptions = [
		{ value: 'do_not_modify', label: __( 'Do Not Modify', 'buddy-modules' ) },
		{ value: 'anyone',        label: __( 'Anyone', 'buddy-modules' ) },
		{ value: 'logged_in',     label: __( 'Logged-in Users', 'buddy-modules' ) },
		{ value: 'members',       label: __( 'Group Members', 'buddy-modules' ) },
		{ value: 'mods',          label: __( 'Group Mods', 'buddy-modules' ) },
		{ value: 'admins',        label: __( 'Group Admins', 'buddy-modules' ) },
		{ value: 'site_admin',    label: __( 'Site Admins', 'buddy-modules' ) },
	];

	var visibilityOptions = 'profile' === tabType ? profileVisibilityOptions : groupVisibilityOptions;

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

	return (
		<div className="bdm-admin-tab-editor">

			{ /* Header */ }
			<div className="bdm-admin-tab-editor__header">
				<h3 className="bdm-admin-tab-editor__title">
					{ isNew
						? __( 'Add New Tab', 'buddy-modules' )
						: sprintf(
							// translators: %s is the tab label.
							__( 'Edit Tab: %s', 'buddy-modules' ),
							form.label
						)
					}
				</h3>
				<div className="bdm-admin-tab-editor__header-actions">
					<Button variant="secondary" onClick={ handleCancel }>
						{ __( 'Cancel', 'buddy-modules' ) }
					</Button>
					<Button variant="primary" isBusy={ saving } disabled={ saving } onClick={ handleSave }>
						{ __( 'Save Tab', 'buddy-modules' ) }
					</Button>
				</div>
			</div>

			{ /* Server error */ }
			{ errors._server && (
				<div className="bdm-admin-tab-editor__server-error">
					{ errors._server }
				</div>
			) }

			{ /* Basic Info */ }
			<div className="bdm-admin-tab-editor__section">
				<h4 className="bdm-admin-tab-editor__section-title">
					{ __( 'Basic Info', 'buddy-modules' ) }
				</h4>

				<div className="bdm-admin-tab-editor__field">
					<TextControl
						label={ __( 'Label', 'buddy-modules' ) }
						value={ form.label }
						onChange={ handleLabelChange }
					/>
					{ errors.label && (
						<p className="bdm-admin-tab-editor__field-error">{ errors.label }</p>
					) }
				</div>

				<div className="bdm-admin-tab-editor__field">
					<TextControl
						label={ __( 'Slug', 'buddy-modules' ) }
						value={ form.slug }
						onChange={ handleSlugChange }
						help={ __( 'URL-friendly identifier. Auto-generated from label; edit to override.', 'buddy-modules' ) }
					/>
				</div>

				<div className="bdm-admin-tab-editor__row">
					<div className="bdm-admin-tab-editor__field">
						<SelectControl
							label={ __( 'Status', 'buddy-modules' ) }
							value={ form.status }
							onChange={ function( v ) { setField( 'status', v ); } }
							options={ [
								{ value: 'active',   label: __( 'Active', 'buddy-modules' ) },
								{ value: 'inactive', label: __( 'Inactive', 'buddy-modules' ) },
							] }
						/>
					</div>

					<div className="bdm-admin-tab-editor__field">
						<ToggleControl
							label={ __( 'Set as Default Tab', 'buddy-modules' ) }
							checked={ !! form.is_default }
							onChange={ function( v ) { setField( 'is_default', v ? 1 : 0 ); } }
						/>
					</div>

					<div className="bdm-admin-tab-editor__field">
						<ToggleControl
							label={ __( 'Site Admin Only', 'buddy-modules' ) }
							checked={ !! form.site_admin_only }
							onChange={ function( v ) { setField( 'site_admin_only', v ? 1 : 0 ); } }
						/>
					</div>
				</div>
			</div>

			{ /* Content */ }
			<div className="bdm-admin-tab-editor__section">
				<h4 className="bdm-admin-tab-editor__section-title">
					{ __( 'Content', 'buddy-modules' ) }
				</h4>

				<div className="bdm-admin-tab-editor__field">
					<SelectControl
						label={ __( 'Content Source', 'buddy-modules' ) }
						value={ form.content_source }
						onChange={ function( v ) { setField( 'content_source', v ); } }
						options={ [
							{ value: 'content', label: __( 'Custom Content', 'buddy-modules' ) },
							{ value: 'url',     label: __( 'External URL', 'buddy-modules' ) },
						] }
					/>
				</div>

				{ 'content' === form.content_source && (
					<div className="bdm-admin-tab-editor__field">
						<label className="bdm-admin-tab-editor__label">
							{ __( 'Content', 'buddy-modules' ) }
						</label>
						<textarea
							className="bdm-admin-tab-editor__textarea"
							value={ form.content }
							onChange={ function( e ) { setField( 'content', e.target.value ); } }
							rows={ 6 }
						/>
					</div>
				) }

				{ 'url' === form.content_source && (
					<div>
						<div className="bdm-admin-tab-editor__field">
							<TextControl
								label={ __( 'External URL', 'buddy-modules' ) }
								value={ form.external_url }
								onChange={ function( v ) { setField( 'external_url', v ); } }
								type="url"
								placeholder="https://"
							/>
						</div>
						<div className="bdm-admin-tab-editor__field">
							<ToggleControl
								label={ __( 'Open in New Tab', 'buddy-modules' ) }
								checked={ !! form.url_new_tab }
								onChange={ function( v ) { setField( 'url_new_tab', v ? 1 : 0 ); } }
							/>
						</div>
					</div>
				) }
			</div>

			{ /* Availability (profile) */ }
			{ 'profile' === tabType && (
				<div className="bdm-admin-tab-editor__section">
					<h4 className="bdm-admin-tab-editor__section-title">
						{ __( 'Availability', 'buddy-modules' ) }
					</h4>
					<p className="bdm-admin-tab-editor__section-desc">
						{ __( 'Control who can see this tab on a member\'s profile.', 'buddy-modules' ) }
					</p>

					<div className="bdm-admin-tab-editor__checkboxes">
						{ availabilityOptions.map( function( opt ) {
							return (
								<label key={ opt.value } className="bdm-admin-tab-editor__checkbox-label">
									<input
										type="checkbox"
										checked={ form.availability.indexOf( opt.value ) !== -1 }
										onChange={ function() { toggleCheckbox( 'availability', opt.value ); } }
									/>
									{ ' ' }{ opt.label }
								</label>
							);
						} ) }
					</div>

					<div className="bdm-admin-tab-editor__field">
						<label className="bdm-admin-tab-editor__label">
							{ __( 'Associated Users (tab appears on their profiles only)', 'buddy-modules' ) }
						</label>
						<SearchSelect
							selectedItems={ assocUsers }
							onChange={ setAssocUsers }
							fetchOptions={ fetchUsers }
							placeholder={ __( 'Search users…', 'buddy-modules' ) }
						/>
					</div>
				</div>
			) }

			{ /* Availability (group) */ }
			{ 'group' === tabType && (
				<div className="bdm-admin-tab-editor__section">
					<h4 className="bdm-admin-tab-editor__section-title">
						{ __( 'Enabled For', 'buddy-modules' ) }
					</h4>
					<p className="bdm-admin-tab-editor__section-desc">
						{ __( 'Enable this tab only for selected group types.', 'buddy-modules' ) }
					</p>

					<div className="bdm-admin-tab-editor__checkboxes">
						{ enabledForOptions.map( function( opt ) {
							return (
								<label key={ opt.value } className="bdm-admin-tab-editor__checkbox-label">
									<input
										type="checkbox"
										checked={ form.enabled_for.indexOf( opt.value ) !== -1 }
										onChange={ function() { toggleCheckbox( 'enabled_for', opt.value ); } }
									/>
									{ ' ' }{ opt.label }
								</label>
							);
						} ) }
					</div>

					<div className="bdm-admin-tab-editor__field">
						<label className="bdm-admin-tab-editor__label">
							{ __( 'Associated Groups (show only in these groups)', 'buddy-modules' ) }
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
							{ __( 'Excluded Groups (hide in these groups)', 'buddy-modules' ) }
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

			{ /* Visibility */ }
			<div className="bdm-admin-tab-editor__section">
				<h4 className="bdm-admin-tab-editor__section-title">
					{ __( 'Visibility', 'buddy-modules' ) }
				</h4>
				<p className="bdm-admin-tab-editor__section-desc">
					{ __( 'Control who can see the content of this tab.', 'buddy-modules' ) }
				</p>

				<div className="bdm-admin-tab-editor__checkboxes">
					{ visibilityOptions.map( function( opt ) {
						return (
							<label key={ opt.value } className="bdm-admin-tab-editor__checkbox-label">
								<input
									type="checkbox"
									checked={ form.visibility.indexOf( opt.value ) !== -1 }
									onChange={ function() { toggleCheckbox( 'visibility', opt.value ); } }
								/>
								{ ' ' }{ opt.label }
							</label>
						);
					} ) }
				</div>
			</div>

			{ /* Sub-tabs (edit mode only — parent_id requires a saved tab ID) */ }
			{ ! isNew && (
				<div className="bdm-admin-tab-editor__section">
					<div className="bdm-admin-tab-editor__section-header">
						<h4 className="bdm-admin-tab-editor__section-title">
							{ __( 'Sub-tabs', 'buddy-modules' ) }
						</h4>
						<Button
							variant="secondary"
							size="small"
							onClick={ handleAddSubtab }
							disabled={ !! subtabForm }
						>
							{ __( '+ Add Sub-tab', 'buddy-modules' ) }
						</Button>
					</div>

					{ subtabs.length === 0 && ! subtabForm && (
						<p className="bdm-admin-tab-editor__empty">
							{ __( 'No sub-tabs yet.', 'buddy-modules' ) }
						</p>
					) }

					{ subtabs.map( function( st ) {
						var isEditing = subtabForm && subtabForm.id === st.id;
						return (
							<div key={ st.id } className="bdm-admin-tab-editor__subtab">
								{ isEditing ? (
									<SubtabForm
										form={ subtabForm }
										onLabelChange={ handleSubtabLabelChange }
										onSlugChange={ handleSubtabSlugChange }
										onChange={ function( k, v ) {
											setSubtabForm( function( prev ) {
												return Object.assign( {}, prev, { [ k ]: v } );
											} );
										} }
										onSave={ handleSubtabSave }
										onCancel={ function() { setSubtabForm( null ); } }
									/>
								) : (
									<div className="bdm-admin-tab-editor__subtab-row">
										<div>
											<strong>{ st.label }</strong>
											{ ' ' }
											<code className="bdm-admin-tabs-list__slug">{ st.slug }</code>
										</div>
										<div className="bdm-admin-tab-editor__subtab-actions">
											<Button
												variant="secondary"
												size="small"
												onClick={ function() { handleEditSubtab( st ); } }
											>
												{ __( 'Edit', 'buddy-modules' ) }
											</Button>
											<Button
												variant="tertiary"
												size="small"
												isDestructive
												onClick={ function() { handleSubtabDelete( st ); } }
											>
												{ __( 'Delete', 'buddy-modules' ) }
											</Button>
										</div>
									</div>
								) }
							</div>
						);
					} ) }

					{ /* New subtab form */ }
					{ subtabForm && ! subtabForm.id && (
						<div className="bdm-admin-tab-editor__subtab bdm-admin-tab-editor__subtab--new">
							<SubtabForm
								form={ subtabForm }
								onLabelChange={ handleSubtabLabelChange }
								onSlugChange={ handleSubtabSlugChange }
								onChange={ function( k, v ) {
									setSubtabForm( function( prev ) {
										return Object.assign( {}, prev, { [ k ]: v } );
									} );
								} }
								onSave={ handleSubtabSave }
								onCancel={ function() { setSubtabForm( null ); } }
							/>
						</div>
					) }
				</div>
			) }

			{ /* Footer buttons */ }
			<div className="bdm-admin-tab-editor__footer">
				<Button variant="secondary" onClick={ handleCancel }>
					{ __( 'Cancel', 'buddy-modules' ) }
				</Button>
				<Button variant="primary" isBusy={ saving } disabled={ saving } onClick={ handleSave }>
					{ __( 'Save Tab', 'buddy-modules' ) }
				</Button>
			</div>
		</div>
	);
}

// -------------------------------------------------------------------------
// Sub-tab inline form (extracted for readability).
// -------------------------------------------------------------------------

/**
 * @param {Object}   props
 * @param {Object}   props.form
 * @param {Function} props.onLabelChange
 * @param {Function} props.onSlugChange
 * @param {Function} props.onChange
 * @param {Function} props.onSave
 * @param {Function} props.onCancel
 * @returns {JSX.Element}
 */
function SubtabForm( { form, onLabelChange, onSlugChange, onChange, onSave, onCancel } ) {
	return (
		<div className="bdm-admin-tab-editor__subtab-form">
			<div className="bdm-admin-tab-editor__row">
				<TextControl
					label={ __( 'Label', 'buddy-modules' ) }
					value={ form.label }
					onChange={ onLabelChange }
				/>
				<TextControl
					label={ __( 'Slug', 'buddy-modules' ) }
					value={ form.slug }
					onChange={ onSlugChange }
				/>
			</div>
			<div className="bdm-admin-tab-editor__field">
				<label className="bdm-admin-tab-editor__label">
					{ __( 'Content', 'buddy-modules' ) }
				</label>
				<textarea
					className="bdm-admin-tab-editor__textarea"
					value={ form.content }
					onChange={ function( e ) { onChange( 'content', e.target.value ); } }
					rows={ 4 }
				/>
			</div>
			<div className="bdm-admin-tab-editor__subtab-form-actions">
				<Button variant="primary" size="small" onClick={ onSave }>
					{ __( 'Save', 'buddy-modules' ) }
				</Button>
				<Button variant="tertiary" size="small" onClick={ onCancel }>
					{ __( 'Cancel', 'buddy-modules' ) }
				</Button>
			</div>
		</div>
	);
}
