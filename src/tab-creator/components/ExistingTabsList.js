/**
 * ExistingTabsList — built-in (platform) tabs list screen for Profile and Group panels.
 *
 * Shows all known BuddyBoss built-in tabs with their override state and
 * allows drag-to-reorder. Override data is saved per-slug in the DB via the
 * existing-tabs REST endpoint.
 *
 * @since 2.0.0
 */

import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Spinner } from '@wordpress/components';
import { DragDropContext, Droppable, Draggable } from 'react-beautiful-dnd';
import { apiRequest } from '../../common/utils/api';
import { useToast } from '../../common/hooks/useToast';

/**
 * @param {Object}   props
 * @param {{ module: string, panel: string }} props.route
 * @param {Function} props.openModal  Called with {view, id?, label?} to open Edit/Reset modal.
 * @param {number}   props.refreshKey Increment from parent triggers a list reload.
 * @returns {JSX.Element}
 */
export default function ExistingTabsList( { route, openModal, refreshKey } ) {
	var tabType = route.panel === 'group-existing-tabs' ? 'group' : 'profile';

	var [ tabs,    setTabs    ] = useState( [] );
	var [ loading, setLoading ] = useState( true );
	var { addToast } = useToast();

	var tabsRef = useRef( tabs );
	useEffect( function() { tabsRef.current = tabs; }, [ tabs ] );

	var load = useCallback( function() {
		setLoading( true );
		return apiRequest( { path: '/tab-creator/existing-tabs?tab_type=' + tabType } )
			.then( function( data ) {
				setTabs( data.tabs || [] );
			} )
			.catch( function() {
				addToast( __( 'Failed to load existing tabs.', 'buddy-modules' ), 'error' );
			} )
			.finally( function() { setLoading( false ); } );
	}, [ tabType ] ); // eslint-disable-line react-hooks/exhaustive-deps

	useEffect( function() { load(); }, [ load ] );

	// Reload when parent signals a refresh (e.g. after a reset from the modal).
	useEffect( function() {
		if ( refreshKey > 0 ) {
			load();
		}
	}, [ refreshKey ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// -------------------------------------------------------------------------
	// Drag to reorder
	// -------------------------------------------------------------------------

	function handleDragEnd( result ) {
		if ( ! result.destination || result.destination.index === result.source.index ) {
			return;
		}
		var before    = tabsRef.current.slice();
		var reordered = before.slice();
		var moved     = reordered.splice( result.source.index, 1 )[ 0 ];
		reordered.splice( result.destination.index, 0, moved );
		setTabs( reordered );

		apiRequest( {
			method: 'POST',
			path:   '/tab-creator/existing-tabs/reorder',
			data:   {
				tab_type: tabType,
				slugs:    reordered.map( function( t ) { return t.original_slug; } ),
			},
		} ).catch( function() {
			setTabs( before );
			addToast( __( 'Could not save order.', 'buddy-modules' ), 'error' );
		} );
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	return (
		<div className="bdm-admin-tabs-list bdm-admin-existing-tabs-list">

			{ /* Loading */ }
			{ loading && (
				<div className="bdm-admin-tabs-list__loading">
					<Spinner />
				</div>
			) }

			{ /* Empty */ }
			{ ! loading && tabs.length === 0 && (
				<p className="bdm-admin-tabs-list__empty">
					{ __( 'No built-in tabs found.', 'buddy-modules' ) }
				</p>
			) }

			{ /* Table */ }
			{ ! loading && tabs.length > 0 && (
				<DragDropContext onDragEnd={ handleDragEnd }>
					<table className="bdm-admin-tabs-list__table widefat striped">
						<thead>
							<tr>
								<th className="bdm-admin-tabs-list__col bdm-admin-tabs-list__col--handle"></th>
								<th className="bdm-admin-tabs-list__col bdm-admin-tabs-list__col--label">
									{ __( 'Label', 'buddy-modules' ) }
								</th>
								<th className="bdm-admin-tabs-list__col">
									{ __( 'Override', 'buddy-modules' ) }
								</th>
								<th className="bdm-admin-tabs-list__col">
									{ __( 'Visibility', 'buddy-modules' ) }
								</th>
								<th className="bdm-admin-tabs-list__col">
									{ __( 'Default', 'buddy-modules' ) }
								</th>
								<th className="bdm-admin-tabs-list__col bdm-admin-tabs-list__col--actions">
									{ __( 'Actions', 'buddy-modules' ) }
								</th>
							</tr>
						</thead>
						<Droppable droppableId="bdm-existing-tabs-list">
							{ function( droppable ) {
								return (
									<tbody
										ref={ droppable.innerRef }
										{ ...droppable.droppableProps }
									>
										{ tabs.map( function( tab, index ) {
											var displayLabel = tab.label_override || tab.default_label;
											var hasOverride  = !! tab.override_id;
											var isHidden     = !! tab.is_hidden;
											return (
												<Draggable
													key={ tab.original_slug }
													draggableId={ tab.original_slug }
													index={ index }
												>
													{ function( drag, snapshot ) {
														return (
															<tr
																ref={ drag.innerRef }
																{ ...drag.draggableProps }
																className={
																	'bdm-admin-tabs-list__row' +
																	( snapshot.isDragging ? ' bdm-admin-tabs-list__row--dragging' : '' )
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
																<td className="bdm-admin-tabs-list__col bdm-admin-tabs-list__col--label">
																	<strong>{ displayLabel }</strong>
																	{ tab.label_override && tab.label_override !== tab.default_label && (
																		<span className="bdm-admin-tabs-list__override-hint">
																			{ ' (' + tab.default_label + ')' }
																		</span>
																	) }
																	<br />
																	<code className="bdm-admin-tabs-list__slug">{ tab.original_slug }</code>
																</td>
																<td className="bdm-admin-tabs-list__col">
																	{ hasOverride
																		? <span className="bdm-admin-badge bdm-admin-badge--custom">{ __( 'Custom', 'buddy-modules' ) }</span>
																		: <span className="bdm-admin-tabs-list__dash">&mdash;</span>
																	}
																</td>
																<td className="bdm-admin-tabs-list__col">
																	{ isHidden
																		? <span className="bdm-admin-badge bdm-admin-badge--inactive">{ __( 'Hidden', 'buddy-modules' ) }</span>
																		: <span className="bdm-admin-badge bdm-admin-badge--active">{ __( 'Visible', 'buddy-modules' ) }</span>
																	}
																</td>
																<td className="bdm-admin-tabs-list__col">
																	{ tab.is_default
																		? <span className="bdm-admin-badge bdm-admin-badge--default">{ __( 'Default', 'buddy-modules' ) }</span>
																		: <span className="bdm-admin-tabs-list__dash">&mdash;</span>
																	}
																</td>
																<td className="bdm-admin-tabs-list__col bdm-admin-tabs-list__col--actions">
																	<Button
																		variant="secondary"
																		size="small"
																		onClick={ function() {
																			openModal( { view: 'edit', id: tab.original_slug } );
																		} }
																	>
																		{ __( 'Edit', 'buddy-modules' ) }
																	</Button>
																	{ hasOverride && (
																		<Button
																			variant="tertiary"
																			size="small"
																			isDestructive
																			onClick={ function() {
																				openModal( {
																					view:  'reset',
																					id:    tab.original_slug,
																					label: tab.label_override || tab.default_label,
																				} );
																			} }
																		>
																			{ __( 'Reset', 'buddy-modules' ) }
																		</Button>
																	) }
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
