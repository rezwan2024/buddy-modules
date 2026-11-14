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

import { useState, useEffect, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	TextControl,
	SelectControl,
	ToggleControl,
	Spinner,
} from '@wordpress/components';
import { apiRequest } from '../../common/utils/api';
import { useToast } from '../../common/hooks/useToast';
import { parseRules, serializeRules } from '../utils/rulesHelpers';
import SearchSelect from './SearchSelect';
import RoleSelect from './RoleSelect';
import { FieldRow } from '../../common/components/FieldRow';
import { RowActions } from '../../common/components/RowActions';

/**
 * Slugify a label (mirrors sanitize_title behaviour).
 *
 * @since 2.0.0
 *
 * @param {string} label Tab label.
 * @return {string} The slug.
 */
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
 * @param {Function} props.onSaved  Called after a successful save (closes the modal).
 * @param {Function} props.onCancel Called when the user cancels (closes the modal).
 * @return {JSX.Element} The tab editor.
 */
export default function TabEditor( { route, onSaved, onCancel } ) {
	const tabType = route.panel === 'group-tabs' ? 'group' : 'profile';
	const editId = route.view === 'edit' ? route.id : null;
	const isNew = ! editId;

	const { addToast } = useToast();

	// Form state.
	const [ form, setForm ] = useState( {
		label: '',
		slug: '',
		status: 'active',
		content_source: 'content',
		content: '',
		external_url: '',
		url_new_tab: 0,
		// Availability / Enabled-for.
		availability: [],
		enabled_for: [],
		// Visibility.
		visibility: [],
	} );

	// SearchSelect item state (needs {id, label} not just IDs).
	const [ assocUsers, setAssocUsers ] = useState( [] );
	const [ assocGroups, setAssocGroups ] = useState( [] );
	const [ excludedGroups, setExcludedGroups ] = useState( [] );

	// Sub-tabs.
	const [ subtabs, setSubtabs ] = useState( [] );
	const [ subtabForm, setSubtabForm ] = useState( null ); // null = closed; {id, label, slug, content, is_enabled} = open
	const [ subtabSlugged, setSubtabSlugged ] = useState( false ); // user manually edited subtab slug

	// WP roles and groups (fetched once).
	const [ wpRoles, setWpRoles ] = useState( [] );

	const [ loading, setLoading ] = useState( !! editId );
	const [ saving, setSaving ] = useState( false );
	const [ errors, setErrors ] = useState( {} );
	const [ sluggedByUser, setSluggedByUser ] = useState( false );

	// Track dirty state to warn on Cancel.
	const initialFormRef = useRef( null );
	// Set when a user/group picker changes (those lists live outside `form`).
	const pickersChangedRef = useRef( false );

	// -------------------------------------------------------------------------
	// Load tab data for edit mode.
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
			if ( ! editId ) {
				setLoading( false );
				return;
			}

			setLoading( true );

			apiRequest( { path: '/tab-creator/tabs/' + editId } )
				.then( function ( tab ) {
					const parsed = parseRules( tab.rules, tabType );

					const nextForm = {
						label: tab.label || '',
						slug: tab.slug || '',
						status: tab.status || 'active',
						content_source: tab.content_source || 'content',
						content: tab.content || '',
						external_url: tab.external_url || '',
						url_new_tab: tab.url_new_tab ? 1 : 0,
						availability: parsed.availability,
						enabled_for: parsed.enabled_for,
						visibility: parsed.visibility,
					};
					setForm( nextForm );
					setSluggedByUser( true ); // existing slug → don't auto-rewrite.
					initialFormRef.current = JSON.stringify( nextForm );

					// Resolve associated user IDs → {id, label}.
					if ( parsed.associated_users.length > 0 ) {
						resolveUsers( parsed.associated_users ).then(
							setAssocUsers
						);
					}
					if ( 'group' === tabType ) {
						if ( parsed.associated_groups.length > 0 ) {
							resolveGroups( parsed.associated_groups ).then(
								setAssocGroups
							);
						}
						if ( parsed.excluded_groups.length > 0 ) {
							resolveGroups( parsed.excluded_groups ).then(
								setExcludedGroups
							);
						}
					}

					// Load sub-tabs.
					return apiRequest( {
						path:
							'/tab-creator/tabs?tab_type=' +
							tabType +
							'&parent_id=' +
							editId,
					} );
				} )
				.then( function ( data ) {
					if ( data ) {
						setSubtabs( data.tabs || [] );
					}
				} )
				.catch( function () {
					addToast(
						__( 'Failed to load tab.', 'buddy-modules' ),
						'error'
					);
				} )
				.finally( function () {
					setLoading( false );
				} );
		},
		[ editId ]
	); // eslint-disable-line react-hooks/exhaustive-deps

	// -------------------------------------------------------------------------
	// Lookup helpers.
	// -------------------------------------------------------------------------

	function fetchUsers( search ) {
		return apiRequest( {
			path:
				'/tab-creator/lookup/users?search=' +
				encodeURIComponent( search ) +
				'&limit=30',
		} ).then( function ( data ) {
			return ( data.users || [] ).map( function ( u ) {
				return {
					id: u.id,
					label: u.display_name + ' (' + u.user_login + ')',
				};
			} );
		} );
	}

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

	function resolveUsers( ids ) {
		return fetchUsers( '' ).then( function ( all ) {
			const found = all.filter( function ( u ) {
				return ids.indexOf( u.id ) !== -1;
			} );
			// Fallback for IDs not in the first 30.
			const missingIds = ids.filter( function ( id ) {
				return ! found.some( function ( u ) {
					return u.id === id;
				} );
			} );
			const fallback = missingIds.map( function ( id ) {
				return { id, label: 'User #' + id };
			} );
			return found.concat( fallback );
		} );
	}

	function resolveGroups( ids ) {
		return fetchGroups( '' ).then( function ( all ) {
			const found = all.filter( function ( g ) {
				return ids.indexOf( g.id ) !== -1;
			} );
			const missingIds = ids.filter( function ( id ) {
				return ! found.some( function ( g ) {
					return g.id === id;
				} );
			} );
			const fallback = missingIds.map( function ( id ) {
				return { id, label: 'Group #' + id };
			} );
			return found.concat( fallback );
		} );
	}

	// -------------------------------------------------------------------------
	// Field change handlers.
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

	function handleLabelChange( val ) {
		setField( 'label', val );
		if ( ! sluggedByUser ) {
			setForm( function ( prev ) {
				return Object.assign( {}, prev, {
					label: val,
					slug: slugify( val ),
				} );
			} );
		}
	}

	function handleSlugChange( val ) {
		setSluggedByUser( true );
		setField( 'slug', val );
	}

	// -------------------------------------------------------------------------
	// Save.
	// -------------------------------------------------------------------------

	function validate() {
		const errs = {};
		if ( ! form.label.trim() ) {
			errs.label = __( 'Label is required.', 'buddy-modules' );
		}
		return errs;
	}

	function handleSave() {
		const errs = validate();
		if ( Object.keys( errs ).length ) {
			setErrors( errs );
			return;
		}

		setSaving( true );
		setErrors( {} );

		const rules = serializeRules(
			{ availability: form.availability, enabled_for: form.enabled_for },
			assocUsers.map( function ( u ) {
				return u.id;
			} ),
			assocGroups.map( function ( g ) {
				return g.id;
			} ),
			excludedGroups.map( function ( g ) {
				return g.id;
			} )
		);

		const body = {
			tab_type: tabType,
			label: form.label,
			slug: form.slug,
			status: form.status,
			content_source: form.content_source,
			content: form.content,
			external_url: form.external_url,
			url_new_tab: form.url_new_tab,
			rules,
		};

		const req = isNew
			? apiRequest( {
					method: 'POST',
					path: '/tab-creator/tabs',
					data: body,
			  } )
			: apiRequest( {
					method: 'PATCH',
					path: '/tab-creator/tabs/' + editId,
					data: body,
			  } );

		req.then( function () {
			addToast(
				isNew
					? __( 'Tab created.', 'buddy-modules' )
					: __( 'Tab saved.', 'buddy-modules' ),
				'success'
			);
			onSaved();
		} )
			.catch( function ( err ) {
				// WP REST errors arrive as { code, message, data }.
				if ( err && err.message ) {
					setErrors( { _server: err.message } );
				} else {
					setErrors( {
						_server: __(
							'Could not save tab. Please try again.',
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
				: form.label.trim() !== '' );

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
	// Sub-tab management.
	// -------------------------------------------------------------------------

	function handleAddSubtab() {
		setSubtabForm( {
			id: null,
			label: '',
			slug: '',
			content: '',
			is_enabled: 1,
		} );
		setSubtabSlugged( false );
	}

	function handleEditSubtab( st ) {
		setSubtabForm( {
			id: st.id,
			label: st.label,
			slug: st.slug,
			content: st.content || '',
			is_enabled: st.is_enabled,
		} );
		setSubtabSlugged( true );
	}

	function handleSubtabLabelChange( val ) {
		setSubtabForm( function ( prev ) {
			const next = Object.assign( {}, prev, { label: val } );
			if ( ! subtabSlugged ) {
				next.slug = slugify( val );
			}
			return next;
		} );
	}

	function handleSubtabSlugChange( val ) {
		setSubtabSlugged( true );
		setSubtabForm( function ( prev ) {
			return Object.assign( {}, prev, { slug: val } );
		} );
	}

	function handleSubtabSave() {
		if ( ! subtabForm || ! subtabForm.label.trim() ) {
			return;
		}

		const body = {
			tab_type: tabType,
			parent_id: editId || 0,
			label: subtabForm.label,
			slug: subtabForm.slug,
			content: subtabForm.content,
			is_enabled: subtabForm.is_enabled,
			status: 'active',
			content_source: 'content',
		};

		const req = subtabForm.id
			? apiRequest( {
					method: 'PATCH',
					path: '/tab-creator/tabs/' + subtabForm.id,
					data: body,
			  } )
			: apiRequest( {
					method: 'POST',
					path: '/tab-creator/tabs',
					data: body,
			  } );

		req.then( function ( saved ) {
			if ( subtabForm.id ) {
				setSubtabs( function ( prev ) {
					return prev.map( function ( st ) {
						return st.id === subtabForm.id ? saved : st;
					} );
				} );
			} else {
				setSubtabs( function ( prev ) {
					return prev.concat( [ saved ] );
				} );
			}
			setSubtabForm( null );
			addToast( __( 'Subtab saved.', 'buddy-modules' ), 'success' );
		} ).catch( function () {
			addToast(
				__( 'Could not save subtab.', 'buddy-modules' ),
				'error'
			);
		} );
	}

	function handleSubtabDelete( st ) {
		if (
			// eslint-disable-next-line no-alert
			! window.confirm(
				sprintf(
					// translators: %s is the subtab label.
					__( 'Delete "%s"?', 'buddy-modules' ),
					st.label
				)
			)
		) {
			return;
		}

		apiRequest( { method: 'DELETE', path: '/tab-creator/tabs/' + st.id } )
			.then( function () {
				setSubtabs( function ( prev ) {
					return prev.filter( function ( s ) {
						return s.id !== st.id;
					} );
				} );
				addToast( __( 'Subtab deleted.', 'buddy-modules' ), 'success' );
			} )
			.catch( function () {
				addToast(
					__( 'Could not delete subtab.', 'buddy-modules' ),
					'error'
				);
			} );
	}

	// -------------------------------------------------------------------------
	// Options lists.
	// -------------------------------------------------------------------------

	const availabilityOptions = [
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

			{ /* Settings rows (Platform settings-form layout) */ }
			<div className="bdm-admin-tab-editor__fields">
				<FieldRow
					label={ __( 'Label', 'buddy-modules' ) }
					htmlFor="bdm-admin-tab-label"
					error={ errors.label }
				>
					<TextControl
						id="bdm-admin-tab-label"
						value={ form.label }
						onChange={ handleLabelChange }
					/>
				</FieldRow>

				<FieldRow
					label={ __( 'Slug', 'buddy-modules' ) }
					htmlFor="bdm-admin-tab-slug"
					help={ __(
						'URL-friendly identifier. Auto-generated from label; edit to override.',
						'buddy-modules'
					) }
				>
					<TextControl
						id="bdm-admin-tab-slug"
						value={ form.slug }
						onChange={ handleSlugChange }
					/>
				</FieldRow>

				<FieldRow
					label={ __( 'Status', 'buddy-modules' ) }
					htmlFor="bdm-admin-tab-status"
				>
					<SelectControl
						id="bdm-admin-tab-status"
						value={ form.status }
						onChange={ function ( v ) {
							setField( 'status', v );
						} }
						options={ [
							{
								value: 'active',
								label: __( 'Active', 'buddy-modules' ),
							},
							{
								value: 'inactive',
								label: __( 'Inactive', 'buddy-modules' ),
							},
						] }
					/>
				</FieldRow>

				<FieldRow
					label={ __( 'Content Source', 'buddy-modules' ) }
					htmlFor="bdm-admin-tab-content-source"
				>
					<SelectControl
						id="bdm-admin-tab-content-source"
						value={ form.content_source }
						onChange={ function ( v ) {
							setField( 'content_source', v );
						} }
						options={ [
							{
								value: 'content',
								label: __( 'Custom Content', 'buddy-modules' ),
							},
							{
								value: 'url',
								label: __( 'External URL', 'buddy-modules' ),
							},
						] }
					/>
				</FieldRow>

				{ 'content' === form.content_source && (
					<FieldRow
						label={ __( 'Content', 'buddy-modules' ) }
						htmlFor="bdm-admin-tab-content"
					>
						<textarea
							id="bdm-admin-tab-content"
							className="bdm-admin-tab-editor__textarea"
							value={ form.content }
							onChange={ function ( e ) {
								setField( 'content', e.target.value );
							} }
							rows={ 6 }
						/>
					</FieldRow>
				) }

				{ 'url' === form.content_source && (
					<FieldRow
						label={ __( 'External URL', 'buddy-modules' ) }
						htmlFor="bdm-admin-tab-external-url"
					>
						<TextControl
							id="bdm-admin-tab-external-url"
							value={ form.external_url }
							onChange={ function ( v ) {
								setField( 'external_url', v );
							} }
							type="url"
							placeholder="https://"
						/>
					</FieldRow>
				) }

				{ 'url' === form.content_source && (
					<FieldRow
						label={ __( 'Open in New Tab', 'buddy-modules' ) }
					>
						<ToggleControl
							label={ __(
								'Open the external URL in a new browser tab.',
								'buddy-modules'
							) }
							checked={ !! form.url_new_tab }
							onChange={ function ( v ) {
								setField( 'url_new_tab', v ? 1 : 0 );
							} }
						/>
					</FieldRow>
				) }

				{ /* Availability (profile) */ }
				{ 'profile' === tabType && (
					<FieldRow
						label={ __( 'Availability', 'buddy-modules' ) }
						htmlFor="bdm-admin-tab-availability"
						help={ __(
							"Control who can see this tab on a member's profile.",
							'buddy-modules'
						) }
					>
						<RoleSelect
							id="bdm-admin-tab-availability"
							options={ availabilityOptions }
							selected={ form.availability }
							onChange={ function ( v ) {
								setField( 'availability', v );
							} }
						/>
					</FieldRow>
				) }

				{ 'profile' === tabType && (
					<FieldRow
						label={ __( 'Associated Users', 'buddy-modules' ) }
						htmlFor="bdm-admin-tab-assoc-users"
						help={ __(
							"The tab appears on these members' profiles only.",
							'buddy-modules'
						) }
					>
						<SearchSelect
							inputId="bdm-admin-tab-assoc-users"
							selectedItems={ assocUsers }
							onChange={ function ( items ) {
								pickersChangedRef.current = true;
								setAssocUsers( items );
							} }
							fetchOptions={ fetchUsers }
							placeholder={ __(
								'Search users…',
								'buddy-modules'
							) }
						/>
					</FieldRow>
				) }

				{ /* Availability (group) */ }
				{ 'group' === tabType && (
					<FieldRow
						label={ __( 'Enabled For', 'buddy-modules' ) }
						htmlFor="bdm-admin-tab-enabled-for"
						help={ __(
							'Enable this tab only for selected group types.',
							'buddy-modules'
						) }
					>
						<RoleSelect
							id="bdm-admin-tab-enabled-for"
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
						htmlFor="bdm-admin-tab-assoc-groups"
						help={ __(
							'Show the tab only in these groups.',
							'buddy-modules'
						) }
					>
						<SearchSelect
							inputId="bdm-admin-tab-assoc-groups"
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
						htmlFor="bdm-admin-tab-excluded-groups"
						help={ __(
							'Hide the tab in these groups.',
							'buddy-modules'
						) }
					>
						<SearchSelect
							inputId="bdm-admin-tab-excluded-groups"
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
							className="bdm-admin-button-icon"
							onClick={ handleAddSubtab }
							disabled={ !! subtabForm }
						>
							<i
								className="bb-icons-rl bb-icons-rl-plus"
								aria-hidden="true"
							/>
							{ __( 'Add Sub-tab', 'buddy-modules' ) }
						</Button>
					</div>

					{ subtabs.length === 0 && ! subtabForm && (
						<p className="bdm-admin-tab-editor__empty">
							{ __( 'No sub-tabs yet.', 'buddy-modules' ) }
						</p>
					) }

					{ subtabs.map( function ( st ) {
						const isEditing = subtabForm && subtabForm.id === st.id;
						return (
							<div
								key={ st.id }
								className="bdm-admin-tab-editor__subtab"
							>
								{ isEditing ? (
									<SubtabForm
										form={ subtabForm }
										onLabelChange={
											handleSubtabLabelChange
										}
										onSlugChange={ handleSubtabSlugChange }
										onChange={ function ( k, v ) {
											setSubtabForm( function ( prev ) {
												return Object.assign(
													{},
													prev,
													{ [ k ]: v }
												);
											} );
										} }
										onSave={ handleSubtabSave }
										onCancel={ function () {
											setSubtabForm( null );
										} }
									/>
								) : (
									<div className="bdm-admin-tab-editor__subtab-row">
										<div>
											<strong>{ st.label }</strong>
										</div>
										<div className="bdm-admin-tab-editor__subtab-actions">
											<RowActions
												label={ sprintf(
													// translators: %s is the sub-tab label.
													__(
														'More options for %s',
														'buddy-modules'
													),
													st.label
												) }
												actions={ [
													{
														key: 'edit',
														label: __(
															'Edit',
															'buddy-modules'
														),
														icon: 'bb-icons-rl-pencil-simple',
														onClick() {
															handleEditSubtab(
																st
															);
														},
													},
													{
														key: 'delete',
														label: __(
															'Delete',
															'buddy-modules'
														),
														icon: 'bb-icons-rl-trash',
														isDestructive: true,
														onClick() {
															handleSubtabDelete(
																st
															);
														},
													},
												] }
											/>
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
								onChange={ function ( k, v ) {
									setSubtabForm( function ( prev ) {
										return Object.assign( {}, prev, {
											[ k ]: v,
										} );
									} );
								} }
								onSave={ handleSubtabSave }
								onCancel={ function () {
									setSubtabForm( null );
								} }
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
				<Button
					variant="primary"
					isBusy={ saving }
					disabled={ saving }
					onClick={ handleSave }
				>
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
 * @return {JSX.Element} The subtab form.
 */
function SubtabForm( {
	form,
	onLabelChange,
	onSlugChange,
	onChange,
	onSave,
	onCancel,
} ) {
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
				<label
					className="bdm-admin-tab-editor__label"
					htmlFor="bdm-admin-subtab-content"
				>
					{ __( 'Content', 'buddy-modules' ) }
				</label>
				<textarea
					id="bdm-admin-subtab-content"
					className="bdm-admin-tab-editor__textarea"
					value={ form.content }
					onChange={ function ( e ) {
						onChange( 'content', e.target.value );
					} }
					rows={ 4 }
				/>
			</div>
			<div className="bdm-admin-tab-editor__subtab-form-actions">
				<Button variant="secondary" size="small" onClick={ onCancel }>
					{ __( 'Cancel', 'buddy-modules' ) }
				</Button>
				<Button variant="primary" size="small" onClick={ onSave }>
					{ __( 'Save', 'buddy-modules' ) }
				</Button>
			</div>
		</div>
	);
}
