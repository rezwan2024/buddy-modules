/**
 * Module settings screen.
 *
 * Layout: SideNavigation (left) + main area (right).
 * The main area always renders the SettingsForm for schema-driven fields.
 * If CUSTOM_PANEL_SCREENS has an entry for the active panel, it is rendered
 * below the form as a Suspense-wrapped custom section.
 *
 * @since 2.0.0
 */

import { useState, useEffect, Suspense } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { apiRequest } from '../../common/utils/api';
import { SideNavigation } from '../../common/components/SideNavigation';
import { SettingsForm } from '../../common/components/SettingsForm';
import { useAutosave } from '../../common/hooks/useAutosave';
import { CUSTOM_PANEL_SCREENS } from '../modules';

/**
 * @since 2.0.0
 *
 * @param {Object}                                 props
 * @param {{ module: string, panel: string|null }} props.route
 * @param {Function}                               props.navigate
 * @return {JSX.Element} The module screen.
 */
export function ModuleScreen( { route, navigate } ) {
	const module = route.module;

	const [ loading, setLoading ] = useState( true );
	const [ schema, setSchema ] = useState( [] );
	const [ values, setValues ] = useState( {} );
	const [ activePanel, setPanel ] = useState( route.panel || null );

	// Sync route.panel → activePanel when user navigates via back/forward.
	useEffect(
		function () {
			if ( route.panel ) {
				setPanel( route.panel );
			}
		},
		[ route.panel ]
	);

	// Fetch schema + values when the module changes.
	useEffect(
		function () {
			setLoading( true );

			apiRequest( { path: '/settings/' + module } )
				.then( function ( data ) {
					const panels = data.panels || [];
					setSchema( panels );
					setValues( data.values || {} );

					// Default to the first panel if none is set in the URL.
					if ( ! route.panel && panels.length ) {
						setPanel( panels[ 0 ].id );
						navigate( { module, panel: panels[ 0 ].id } );
					}
				} )
				.finally( function () {
					setLoading( false );
				} );
		},
		[ module ]
	); // eslint-disable-line react-hooks/exhaustive-deps

	const save = useAutosave( module, activePanel );

	function handleFieldChange( fieldId, newValue ) {
		const nextPanelValues = Object.assign(
			{},
			values[ activePanel ] || {},
			{
				[ fieldId ]: newValue,
			}
		);
		setValues(
			Object.assign( {}, values, { [ activePanel ]: nextPanelValues } )
		);
		save( nextPanelValues );
	}

	function handlePanelSelect( panelId ) {
		setPanel( panelId );
		navigate( { module, panel: panelId } );
	}

	function handleBack() {
		navigate( {} );
	}

	if ( loading ) {
		return (
			<div className="bdm-admin-module-screen__loading">
				<Spinner />
			</div>
		);
	}

	const activePanelSchema = schema.find( function ( p ) {
		return p.id === activePanel;
	} );
	const customKey = module + '/' + activePanel;
	const CustomScreen = CUSTOM_PANEL_SCREENS[ customKey ] || null;
	const panelValues = values[ activePanel ] || {};

	const sections = activePanelSchema ? activePanelSchema.sections || [] : [];
	const panelTitle = activePanelSchema ? activePanelSchema.label : '';

	return (
		<div className="bdm-admin-module-screen">
			<SideNavigation
				panels={ schema }
				activePanel={ activePanel }
				onSelect={ handlePanelSelect }
				onBack={ handleBack }
			/>

			<main className="bdm-admin-module-screen__main">
				{ /* Schema-driven settings get their own card. */ }
				{ sections.length > 0 && (
					<section className="bdm-admin-card">
						{ panelTitle && (
							<div className="bdm-admin-card__header">
								<h2 className="bdm-admin-card__title">
									{ panelTitle }
								</h2>
							</div>
						) }
						<div className="bdm-admin-card__body">
							<SettingsForm
								sections={ sections }
								values={ panelValues }
								onChange={ handleFieldChange }
							/>
						</div>
					</section>
				) }

				{ /* Custom screens render their own card (title + header actions). */ }
				{ CustomScreen && (
					<Suspense fallback={ <Spinner /> }>
						<CustomScreen
							route={ route }
							navigate={ navigate }
							title={ panelTitle }
							values={ panelValues }
							onChange={ handleFieldChange }
						/>
					</Suspense>
				) }
			</main>
		</div>
	);
}
