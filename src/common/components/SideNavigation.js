/**
 * Module settings side navigation.
 *
 * Shows a "← Back" button at the top, then a vertical list of panel items
 * (optional dashicon + label). Highlights the active panel.
 *
 * @since 2.0.0
 */

import { __ } from '@wordpress/i18n';

/**
 * @since 2.0.0
 *
 * @param {Object}   props
 * @param {Array}    props.panels      Array of panel objects: { id, label, icon? }.
 * @param {string}   props.activePanel Currently-active panel ID.
 * @param {Function} props.onSelect    Called with panel ID when an item is clicked.
 * @param {Function} props.onBack      Called when the Back button is clicked.
 * @returns {JSX.Element}
 */
export function SideNavigation( { panels, activePanel, onSelect, onBack } ) {
	return (
		<nav
			className="bdm-admin-sidenav"
			aria-label={ __( 'Module panels', 'buddy-modules' ) }
		>
			<button
				type="button"
				className="bdm-admin-sidenav__back"
				onClick={ onBack }
			>
				{ __( '← Back', 'buddy-modules' ) }
			</button>

			<ul className="bdm-admin-sidenav__list" role="list">
				{ panels.map( function( panel ) {
					var isActive = panel.id === activePanel;
					return (
						<li
							key={ panel.id }
							className={
								'bdm-admin-sidenav__item' +
								( isActive ? ' bdm-admin-sidenav__item--active' : '' )
							}
						>
							<button
								type="button"
								className="bdm-admin-sidenav__link"
								onClick={ function() { onSelect( panel.id ); } }
								aria-current={ isActive ? 'page' : undefined }
							>
								{ panel.icon && (
									<span
										className={
											'bdm-admin-sidenav__icon dashicons ' + panel.icon
										}
										aria-hidden="true"
									/>
								) }
								<span className="bdm-admin-sidenav__label">
									{ panel.label }
								</span>
							</button>
						</li>
					);
				} ) }
			</ul>
		</nav>
	);
}
