/**
 * Module settings side navigation.
 *
 * Shows a "Back to Modules" button at the top, then a vertical list of panel
 * items (optional icon + label). Highlights the active panel. Matches the
 * BuddyBoss Platform settings side navigation.
 *
 * @since 2.0.0
 */

import { __ } from '@wordpress/i18n';

/**
 * Icon class for a panel: 'dashicons-*' keeps Dashicons, anything else is a
 * BuddyBoss line icon name (bb-icons-rl-*).
 *
 * @since 2.0.0
 *
 * @param {string} icon Icon identifier from the panel schema.
 * @return {string} Class names for the icon element.
 */
function getIconClass( icon ) {
	if ( 0 === icon.indexOf( 'dashicons-' ) ) {
		return 'dashicons ' + icon;
	}
	return 'bb-icons-rl ' + icon;
}

/**
 * @since 2.0.0
 *
 * @param {Object}   props
 * @param {Array}    props.panels      Array of panel objects: { id, label, icon? } (icon = bb-icons-rl-* or dashicons-*).
 * @param {string}   props.activePanel Currently-active panel ID.
 * @param {Function} props.onSelect    Called with panel ID when an item is clicked.
 * @param {Function} props.onBack      Called when the Back button is clicked.
 * @return {JSX.Element} The side navigation.
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
				<span
					className="bdm-admin-sidenav__back-icon bb-icons-rl-arrow-left"
					aria-hidden="true"
				/>
				{ __( 'Back to Modules', 'buddy-modules' ) }
			</button>

			<ul className="bdm-admin-sidenav__list">
				{ panels.map( function ( panel ) {
					const isActive = panel.id === activePanel;
					return (
						<li
							key={ panel.id }
							className={
								'bdm-admin-sidenav__item' +
								( isActive
									? ' bdm-admin-sidenav__item--active'
									: '' )
							}
						>
							<button
								type="button"
								className="bdm-admin-sidenav__link"
								onClick={ function () {
									onSelect( panel.id );
								} }
								aria-current={ isActive ? 'page' : undefined }
							>
								{ panel.icon && (
									<span
										className={
											'bdm-admin-sidenav__icon ' +
											getIconClass( panel.icon )
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
