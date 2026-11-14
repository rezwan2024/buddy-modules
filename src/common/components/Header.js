/**
 * Buddy Modules fixed admin header bar.
 *
 * @since 2.0.0
 */

import { __ } from '@wordpress/i18n';

/**
 * Renders the fixed header with the plugin name (links back to the dashboard).
 *
 * @since 2.0.0
 *
 * @param {Object}   props
 * @param {Function} props.navigate Navigate to a new route.
 * @return {JSX.Element} The header.
 */
export function Header( { navigate } ) {
	function handleTitleClick( e ) {
		e.preventDefault();
		navigate( {} );
	}

	return (
		<div className="bdm-admin-header" role="banner">
			<button
				type="button"
				className="bdm-admin-header__title"
				onClick={ handleTitleClick }
				aria-label={ __(
					'Buddy Modules — back to dashboard',
					'buddy-modules'
				) }
			>
				{ __( 'Buddy Modules', 'buddy-modules' ) }
			</button>
			<div className="bdm-admin-header__spacer" aria-hidden="true" />
		</div>
	);
}
