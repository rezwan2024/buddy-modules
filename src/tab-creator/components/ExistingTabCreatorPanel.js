/**
 * ExistingTabCreatorPanel — panel for Existing (built-in) Tabs.
 *
 * ExistingTabsList is always visible. Edit opens a Modal instead of
 * navigating to a full-page editor. Reset override shows a confirmation Modal.
 *
 * @since 2.0.0
 */

import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Modal } from '@wordpress/components';
import ExistingTabsList from './ExistingTabsList';
import ExistingTabEditor from './ExistingTabEditor';
import { apiRequest } from '../../common/utils/api';
import { useToast } from '../../common/hooks/useToast';

/**
 * @param {Object} props
 * @param {Object} props.route
 * @param {string} props.title Panel title, shown in the list card header.
 * @return {JSX.Element} The existing tab creator panel.
 */
export default function ExistingTabCreatorPanel( props ) {
	const route = props.route;
	const tabType = 'group-existing-tabs' === route.panel ? 'group' : 'profile';
	const { addToast } = useToast();

	const [ modalOpen, setModalOpen ] = useState( false );
	const [ modalView, setModalView ] = useState( null ); // 'edit' | 'reset'
	const [ modalTabId, setModalTabId ] = useState( null );
	const [ modalTabLabel, setModalTabLabel ] = useState( '' );
	const [ resetting, setResetting ] = useState( false );
	const [ refreshKey, setRefreshKey ] = useState( 0 );

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
		setRefreshKey( function ( k ) {
			return k + 1;
		} );
	}

	function handleResetConfirm() {
		setResetting( true );
		apiRequest( {
			method: 'DELETE',
			path: '/tab-creator/existing-tabs/' + modalTabId,
			data: { tab_type: tabType },
		} )
			.then( function () {
				closeModal();
				setRefreshKey( function ( k ) {
					return k + 1;
				} );
				addToast( __( 'Override reset.', 'buddy-modules' ), 'success' );
			} )
			.catch( function () {
				addToast(
					__( 'Could not reset override.', 'buddy-modules' ),
					'error'
				);
			} )
			.finally( function () {
				setResetting( false );
			} );
	}

	const editorRoute = Object.assign( {}, route, {
		view: 'edit',
		id: modalTabId,
	} );

	return (
		<div>
			<ExistingTabsList
				route={ route }
				title={ props.title }
				openModal={ openModal }
				refreshKey={ refreshKey }
			/>

			{ modalOpen && 'edit' === modalView && (
				<Modal
					title={ __( 'Edit Built-in Tab', 'buddy-modules' ) }
					className="bdm-admin-tab-editor-modal"
					onRequestClose={ closeModal }
				>
					<div className="bdm-admin-tab-editor-modal__body">
						<ExistingTabEditor
							route={ editorRoute }
							onSaved={ handleSaved }
							onCancel={ closeModal }
						/>
					</div>
				</Modal>
			) }

			{ modalOpen && 'reset' === modalView && (
				<Modal
					title={ __( 'Reset Override', 'buddy-modules' ) }
					className="bdm-admin-tab-editor-modal bdm-admin-tab-editor-modal--delete"
					onRequestClose={ closeModal }
				>
					<div className="bdm-admin-tab-editor-modal__delete-body">
						<p>
							{ sprintf(
								// translators: %s is the tab label.
								__(
									'Reset "%s" to its default settings?',
									'buddy-modules'
								),
								modalTabLabel
							) }
						</p>
					</div>
					<div className="bdm-admin-tab-editor-modal__delete-footer">
						<Button
							variant="secondary"
							onClick={ closeModal }
							disabled={ resetting }
						>
							{ __( 'Cancel', 'buddy-modules' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							isBusy={ resetting }
							disabled={ resetting }
							onClick={ handleResetConfirm }
						>
							{ __( 'Reset', 'buddy-modules' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
