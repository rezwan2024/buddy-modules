/**
 * TabsList — custom tabs list screen for Profile Tabs and Group Tabs panels.
 *
 * Renders as a draggable table with bulk actions. Receives props from
 * ModuleScreen via CUSTOM_PANEL_SCREENS. The `tabType` is derived from the
 * active panel: 'profile-tabs' → 'profile', 'group-tabs' → 'group'.
 *
 * Done-when (P5): real data loads, reorder/bulk/delete work without reload,
 * label shown as plain text (no row-action mixing — legacy Label column bug).
 *
 * @since 2.0.0
 */

import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Spinner } from '@wordpress/components';
import { DragDropContext, Droppable, Draggable } from 'react-beautiful-dnd';
import { apiRequest } from '../../common/utils/api';
import { useToast } from '../../common/hooks/useToast';
import { getRuleSummary } from '../utils/ruleLabels';

/**
 * @param {Object} props
 * @param {{ module: string, panel: string }} props.route
 * @param {Function} props.navigate
 * @returns {JSX.Element}
 */
export default function TabsList( { route, navigate } ) {
	var tabType = route.panel === 'group-tabs' ? 'group' : 'profile';

	var [ tabs,       setTabs       ] = useState( [] );
	var [ loading,    setLoading    ] = useState( true );
	var [ selected,   setSelected   ] = useState( [] );
	var [ bulkAction, setBulkAction ] = useState( '' );
	var [ deleting,   setDeleting   ] = useState( null );
	var { addToast } = useToast();

	// Keep a stable ref to the latest tabs so the drag-end closure can revert.
	var tabsRef = useRef( tabs );
	useEffect( function() { tabsRef.current = tabs; }, [ tabs ] );

	var load = useCallback( function() {
		setLoading( true );
		return apiRequest( { path: '/tab-creator/tabs?tab_type=' + tabType + '&parent_id=0' } )
			.then( function( data ) {
				setTabs( data.tabs || [] );
			} )
			.catch( function() {
				addToast( __( 'Failed to load tabs.', 'buddy-modules' ), 'error' );
			} )
			.finally( function() { setLoading( false ); } );
	}, [ tabType ] ); // eslint-disable-line react-hooks/exhaustive-deps

	useEffect( function() { load(); }, [ load ] );

	// -------------------------------------------------------------------------
	// Status toggle — optimistic
	// -------------------------------------------------------------------------

	function handleStatusToggle( tab ) {
		var newStatus = tab.status === 'active' ? 'inactive' : 'active';
		setTabs( function( prev ) {
			return prev.map( function( t ) {
				return t.id === tab.id ? Object.assign( {}, t, { status: newStatus } ) : t;
			} );
		} );
		apiRequest( {
			method: 'PATCH',
			path:   '/tab-creator/tabs/' + tab.id,
			data:   { status: newStatus },
		} ).catch( function() {
			setTabs( function( prev ) {
				return prev.map( function( t ) {
					return t.id === tab.id ? Object.assign( {}, t, { status: tab.status } ) : t;
				} );
			} );
			addToast( __( 'Could not update status.', 'buddy-modules' ), 'error' );
		} );
	}

	// -------------------------------------------------------------------------
	// Single delete
	// -------------------------------------------------------------------------

	function handleDelete( tab ) {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( sprintf(
			// translators: %s is the tab label.
			__( 'Delete "%s"? This cannot be undone.', 'buddy-modules' ),
			tab.label
		) ) ) {
			return;
		}
		setDeleting( tab.id );
		apiRequest( { method: 'DELETE', path: '/tab-creator/tabs/' + tab.id } )
			.then( function() {
				setTabs( function( prev ) {
					return prev.filter( function( t ) { return t.id !== tab.id; } );
				} );
				setSelected( function( prev ) {
					return prev.filter( function( id ) { return id !== tab.id; } );
				} );
				addToast( __( 'Tab deleted.', 'buddy-modules' ), 'success' );
			} )
			.catch( function() {
				addToast( __( 'Could not delete tab.', 'buddy-modules' ), 'error' );
			} )
			.finally( function() { setDeleting( null ); } );
	}

	// -------------------------------------------------------------------------
	// Drag to reorder
	// -------------------------------------------------------------------------

	function handleDragEnd( result ) {
		if ( ! result.destination || result.destination.index === result.source.index ) {
			return;
		}
		var before   = tabsRef.current.slice();
		var reordered = before.slice();
		var moved     = reordered.splice( result.source.index, 1 )[ 0 ];
		reordered.splice( result.destination.index, 0, moved );
		setTabs( reordered );

		apiRequest( {
			method: 'POST',
			path:   '/tab-creator/tabs/reorder',
			data:   { ids: reordered.map( function( t ) { return t.id; } ) },
		} ).catch( function() {
			setTabs( before );
			addToast( __( 'Could not save order.', 'buddy-modules' ), 'error' );
		} );
	}

	// -------------------------------------------------------------------------
	// Bulk actions
	// -------------------------------------------------------------------------

	var allChecked     = tabs.length > 0 && selected.length === tabs.length;
	var someChecked    = selected.length > 0 && selected.length < tabs.length;

	function handleSelectAll( e ) {
		setSelected( e.target.checked ? tabs.map( function( t ) { return t.id; } ) : [] );
	}

	function handleSelectOne( id, checked ) {
		setSelected( function( prev ) {
			return checked
				? prev.concat( [ id ] )
				: prev.filter( function( v ) { return v !== id; } );
		} );
	}

	function handleBulkApply() {
		if ( ! bulkAction || selected.length === 0 ) {
			return;
		}
		if ( 'delete' === bulkAction ) {
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( __( 'Delete the selected tabs? This cannot be undone.', 'buddy-modules' ) ) ) {
				return;
			}
		}
		apiRequest( {
			method: 'POST',
			path:   '/tab-creator/tabs/bulk',
			data:   { action: bulkAction, ids: selected },
		} )
			.then( function() {
				setSelected( [] );
				setBulkAction( '' );
				load();
				addToast( __( 'Action applied.', 'buddy-modules' ), 'success' );
			} )
			.catch( function() {
				addToast( __( 'Bulk action failed.', 'buddy-modules' ), 'error' );
			} );
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	return (
		<div className="bdm-admin-tabs-list">

			{ /* Toolbar */ }
			<div className="bdm-admin-tabs-list__toolbar">
				<div className="bdm-admin-tabs-list__bulk-wrap">
					<select
						className="bdm-admin-tabs-list__bulk-select"
						value={ bulkAction }
						onChange={ function( e ) { setBulkAction( e.target.value ); } }
					>
						<option value="">{ __( 'Bulk Actions', 'buddy-modules' ) }</option>
						<option value="activate">{ __( 'Activate', 'buddy-modules' ) }</option>
						<option value="deactivate">{ __( 'Deactivate', 'buddy-modules' ) }</option>
						<option value="delete">{ __( 'Delete', 'buddy-modules' ) }</option>
					</select>
					<Button
						variant="secondary"
						onClick={ handleBulkApply }
						disabled={ ! bulkAction || selected.length === 0 }
					>
						{ __( 'Apply', 'buddy-modules' ) }
					</Button>
				</div>

				<Button
					variant="primary"
					onClick={ function() {
						navigate( { module: route.module, panel: route.panel, view: 'add' } );
					} }
				>
					{ __( '+ Add Tab', 'buddy-modules' ) }
				</Button>
			</div>

			{ /* Loading */ }
			{ loading && (
				<div className="bdm-admin-tabs-list__loading">
					<Spinner />
				</div>
			) }

			{ /* Empty */ }
			{ ! loading && tabs.length === 0 && (
				<p className="bdm-admin-tabs-list__empty">
					{ __( 'No tabs found. Add your first tab.', 'buddy-modules' ) }
				</p>
			) }

			{ /* Table */ }
			{ ! loading && tabs.length > 0 && (
				<DragDropContext onDragEnd={ handleDragEnd }>
					<table className="bdm-admin-tabs-list__table widefat striped">
						<thead>
							<tr>
								<th className="bdm-admin-tabs-list__col bdm-admin-tabs-list__col--handle"></th>
								<th className="bdm-admin-tabs-list__col bdm-admin-tabs-list__col--cb">
									<input
										type="checkbox"
										checked={ allChecked }
										ref={ function( el ) {
											if ( el ) { el.indeterminate = someChecked; }
										} }
										onChange={ handleSelectAll }
										aria-label={ __( 'Select all', 'buddy-modules' ) }
									/>
								</th>
								<th className="bdm-admin-tabs-list__col bdm-admin-tabs-list__col--label">
									{ __( 'Label', 'buddy-modules' ) }
								</th>
								<th className="bdm-admin-tabs-list__col">
									{ __( 'Type', 'buddy-modules' ) }
								</th>
								<th className="bdm-admin-tabs-list__col">
									{ __( 'Availability', 'buddy-modules' ) }
								</th>
								<th className="bdm-admin-tabs-list__col">
									{ __( 'Visibility', 'buddy-modules' ) }
								</th>
								<th className="bdm-admin-tabs-list__col">
									{ __( 'Status', 'buddy-modules' ) }
								</th>
								<th className="bdm-admin-tabs-list__col">
									{ __( 'Default', 'buddy-modules' ) }
								</th>
								<th className="bdm-admin-tabs-list__col">
									{ __( 'Sub-tabs', 'buddy-modules' ) }
								</th>
								<th className="bdm-admin-tabs-list__col bdm-admin-tabs-list__col--actions">
									{ __( 'Actions', 'buddy-modules' ) }
								</th>
							</tr>
						</thead>
						<Droppable droppableId="bdm-tabs-list">
							{ function( droppable ) {
								return (
									<tbody
										ref={ droppable.innerRef }
										{ ...droppable.droppableProps }
									>
										{ tabs.map( function( tab, index ) {
											return (
												<Draggable
													key={ String( tab.id ) }
													draggableId={ String( tab.id ) }
													index={ index }
												>
													{ function( drag, snapshot ) {
														return (
															<tr
																ref={ drag.innerRef }
																{ ...drag.draggableProps }
																className={
																	'bdm-admin-tabs-list__row' +
																	( snapshot.isDragging ? ' bdm-admin-tabs-list__row--dragging' : '' ) +
																	( selected.indexOf( tab.id ) !== -1 ? ' bdm-admin-tabs-list__row--selected' : '' )
																}
															>
																<td
																	{ ...drag.dragHandleProps }
																	className="bdm-admin-tabs-list__col bdm-admin-tabs-list__col--handle"
																>
																	<span
																		className="bdm-admin-drag-handle"
																		title={ __( 'Drag to reorder', 'buddy-modules' ) }
																	>
																		⠿
																	</span>
																</td>
																<td className="bdm-admin-tabs-list__col bdm-admin-tabs-list__col--cb">
																	<input
																		type="checkbox"
																		checked={ selected.indexOf( tab.id ) !== -1 }
																		onChange={ function( e ) {
																			handleSelectOne( tab.id, e.target.checked );
																		} }
																		aria-label={ sprintf(
																			// translators: %s is the tab label.
																			__( 'Select %s', 'buddy-modules' ),
																			tab.label
																		) }
																	/>
																</td>
																<td className="bdm-admin-tabs-list__col bdm-admin-tabs-list__col--label">
																	<strong>{ tab.label }</strong>
																	<br />
																	<code className="bdm-admin-tabs-list__slug">{ tab.slug }</code>
																</td>
																<td className="bdm-admin-tabs-list__col">
																	<span className="bdm-admin-badge bdm-admin-badge--type">
																		{ __( 'Custom', 'buddy-modules' ) }
																	</span>
																</td>
																<td className="bdm-admin-tabs-list__col">
																	{ getRuleSummary( tab.rules, 'availability' ) }
																</td>
																<td className="bdm-admin-tabs-list__col">
																	{ getRuleSummary( tab.rules, 'visibility' ) }
																</td>
																<td className="bdm-admin-tabs-list__col">
																	<button
																		type="button"
																		className={
																			'bdm-admin-status-toggle' +
																			( tab.status === 'active'
																				? ' bdm-admin-status-toggle--active'
																				: ' bdm-admin-status-toggle--inactive'
																			)
																		}
																		onClick={ function() { handleStatusToggle( tab ); } }
																	>
																		{ tab.status === 'active'
																			? __( 'Active', 'buddy-modules' )
																			: __( 'Inactive', 'buddy-modules' )
																		}
																	</button>
																</td>
																<td className="bdm-admin-tabs-list__col">
																	{ tab.is_default
																		? <span className="bdm-admin-badge bdm-admin-badge--default">{ __( 'Default', 'buddy-modules' ) }</span>
																		: <span className="bdm-admin-tabs-list__dash">&mdash;</span>
																	}
																</td>
																<td className="bdm-admin-tabs-list__col">
																	<span className="bdm-admin-tabs-list__subtab-count">
																		{ tab.subtab_count || 0 }
																	</span>
																</td>
																<td className="bdm-admin-tabs-list__col bdm-admin-tabs-list__col--actions">
																	<Button
																		variant="secondary"
																		size="small"
																		onClick={ function() {
																			navigate( { module: route.module, panel: route.panel, view: 'edit', id: tab.id } );
																		} }
																	>
																		{ __( 'Edit', 'buddy-modules' ) }
																	</Button>
																	<Button
																		variant="tertiary"
																		size="small"
																		isDestructive
																		isBusy={ deleting === tab.id }
																		disabled={ deleting !== null }
																		onClick={ function() { handleDelete( tab ); } }
																	>
																		{ __( 'Delete', 'buddy-modules' ) }
																	</Button>
																</td>
															</tr>
														);
													} }
												</Draggable>
											);
										} ) }
										{ droppable.placeholder }
									</tbody>
								);
							} }
						</Droppable>
					</table>
				</DragDropContext>
			) }
		</div>
	);
}
