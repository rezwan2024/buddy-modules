/**
 * Buddy Modules fixed admin header bar.
 *
 * @since 2.0.0
 */

import { __ } from '@wordpress/i18n';

/**
 * Renders the fixed header with the plugin logo.
 *
 * @since 2.0.0
 *
 * @param {Object}   props
 * @param {Function} props.navigate Navigate to a new route.
 * @returns {JSX.Element}
 */
export function Header( { navigate } ) {
	const logoUrl = ( window.bdmAdminData && window.bdmAdminData.logoUrl ) || '';

	function handleLogoClick( e ) {
		e.preventDefault();
		navigate( {} );
	}

	return (
		<div className="bdm-admin-header" role="banner">
			<button
				type="button"
				className="bdm-admin-header__logo"
				onClick={ handleLogoClick }
				aria-label={ __( 'Buddy Modules — back to dashboard', 'buddy-modules' ) }
			>
				{ logoUrl && (
					<img
						src={ logoUrl }
						alt=""
						aria-hidden="true"
					/>
				) }
				<span>{ __( 'Buddy Modules', 'buddy-modules' ) }</span>
			</button>
			<div className="bdm-admin-header__spacer" aria-hidden="true" />
		</div>
	);
}
