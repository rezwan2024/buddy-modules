/**
 * TabCreatorPanel — panel for Profile Tabs and Group Tabs.
 *
 * TabsList is always visible. Add/Edit/Delete actions open a Modal
 * instead of navigating to a full-page editor view.
 *
 * @since 2.0.0
 */

import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Modal } from '@wordpress/components';
import TabsList  from './TabsList';
import TabEditor from './TabEditor';
import { apiRequest } from '../../common/utils/api';
import { useToast } from '../../common/hooks/useToast';

/**
 * @param {Object} props
 * @param {Object} props.route
 * @returns {JSX.Element}
 */
export default function TabCreatorPanel( props ) {
	var route        = props.route;
	var { addToast } = useToast();

	var [ modalOpen,     setModalOpen     ] = useState( false );
	var [ modalView,     setModalView     ] = useState( null );   // 'add' | 'edit' | 'delete'
	var [ modalTabId,    setModalTabId    ] = useState( null );
	var [ modalTabLabel, setModalTabLabel ] = useState( '' );
	var [ deleting,      setDeleting      ] = useState( false );
	var [ refreshKey,    setRefreshKey    ] = useState( 0 );

	function openModal( opts ) {
		setModalView( opts.view );
		setModalTabId( opts.id || null );
		setModalTabLabel( opts.label || '' );
		setModalOpen( true );
	}

	function closeModal() {
		setModalOpen( false );
		setModalView( null );
		setModalTabId( null );
		setModalTabLabel( '' );
	}

	function handleSaved() {
		closeModal();
		setRefreshKey( function( k ) { return k + 1; } );
	}

	function handleDeleteConfirm() {
		setDeleting( true );
		apiRequest( { method: 'DELETE', path: '/tab-creator/tabs/' + modalTabId } )
			.then( function() {
				closeModal();
				setRefreshKey( function( k ) { return k + 1; } );
				addToast( __( 'Tab deleted.', 'buddy-modules' ), 'success' );
			} )
			.catch( function() {
				addToast( __( 'Could not delete tab.', 'buddy-modules' ), 'error' );
			} )
			.finally( function() { setDeleting( false ); } );
	}

	var editorRoute = Object.assign( {}, route, {
		view: modalView || 'add',
		id:   modalTabId,
	} );

	var modalTitle = 'edit' === modalView
		? __( 'Edit Tab', 'buddy-modules' )
		: __( 'Add Tab', 'buddy-modules' );

	return (
		<div>
			<TabsList
				route={ route }
				openModal={ openModal }
				refreshKey={ refreshKey }
			/>

			{ modalOpen && ( 'add' === modalView || 'edit' === modalView ) && (
				<Modal
					title={ modalTitle }
					className="bdm-admin-tab-editor-modal"
					onRequestClose={ closeModal }
				>
					<div className="bdm-admin-tab-editor-modal__body">
						<TabEditor
							route={ editorRoute }
							onSaved={ handleSaved }
							onCancel={ closeModal }
						/>
					</div>
				</Modal>
			) }

			{ modalOpen && 'delete' === modalView && (
				<Modal
					title={ __( 'Delete Tab', 'buddy-modules' ) }
					className="bdm-admin-tab-editor-modal bdm-admin-tab-editor-modal--delete"
					onRequestClose={ closeModal }
				>
					<div className="bdm-admin-tab-editor-modal__delete-body">
						<p>
							{ sprintf(
								// translators: %s is the tab label.
								__( 'Delete "%s"? This cannot be undone.', 'buddy-modules' ),
								modalTabLabel
							) }
						</p>
					</div>
					<div className="bdm-admin-tab-editor-modal__delete-footer">
						<Button
							variant="primary"
							isDestructive
							isBusy={ deleting }
							disabled={ deleting }
							onClick={ handleDeleteConfirm }
						>
							{ __( 'Delete', 'buddy-modules' ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ closeModal }
							disabled={ deleting }
						>
							{ __( 'Cancel', 'buddy-modules' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
