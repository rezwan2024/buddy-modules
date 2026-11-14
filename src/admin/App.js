/**
 * Buddy Modules React app shell.
 *
 * Owns the route state (read from URL query params), provides navigation
 * helpers, listens for popstate, and wraps the Router in context providers.
 *
 * @since 2.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { ToastProvider } from '../common/hooks/useToast';
import { Toast } from '../common/components/Toast';
import { Header } from '../common/components/Header';
import { Router } from './Router';

/**
 * Read the current route from the URL query params.
 *
 * @since 2.0.0
 *
 * @return {{ module: string|null, panel: string|null, view: string|null, id: number|null }} The current route.
 */
function readRoute() {
	const params = new URLSearchParams( window.location.search );
	const rawId = params.get( 'id' );
	return {
		module: params.get( 'module' ),
		panel: params.get( 'panel' ),
		view: params.get( 'view' ),
		id: rawId ? parseInt( rawId, 10 ) : null,
	};
}

/**
 * Build the new URL by keeping page=buddy-modules and replacing route params.
 *
 * @since 2.0.0
 *
 * @param {Object} params Desired module/panel/view/id values (null = remove).
 * @return {string} Full URL string.
 */
function buildUrl( params ) {
	const url = new URL( window.location.href );
	[ 'module', 'panel', 'view', 'id' ].forEach( ( k ) =>
		url.searchParams.delete( k )
	);
	Object.entries( params ).forEach( ( [ k, v ] ) => {
		if ( null !== v && undefined !== v ) {
			url.searchParams.set( k, v );
		}
	} );
	return url.toString();
}

/**
 * Keep the Buddy Modules WP admin menu item highlighted during SPA navigation.
 *
 * WP sets the highlight on page load from the query string; pushState changes
 * do not trigger a re-evaluation, so we fix it manually.
 *
 * @since 2.0.0
 */
function fixMenuHighlight() {
	const menu = document.getElementById( 'toplevel_page_buddy-modules' );
	if ( ! menu ) {
		return;
	}

	// Remove "current" from every top-level item first.
	const allItems = document.querySelectorAll( '#adminmenu > li' );
	allItems.forEach( function ( li ) {
		li.classList.remove(
			'current',
			'wp-has-current-submenu',
			'wp-menu-open'
		);
		const a = li.querySelector( 'a.menu-top' );
		if ( a ) {
			a.classList.remove( 'current', 'wp-has-current-submenu' );
		}
	} );

	// Highlight ours.
	menu.classList.add( 'current', 'wp-has-current-submenu', 'wp-menu-open' );
	const menuLink = menu.querySelector( 'a.menu-top' );
	if ( menuLink ) {
		menuLink.classList.add( 'current', 'wp-has-current-submenu' );
		menuLink.setAttribute( 'aria-current', 'page' );
	}
}

/**
 * Root App component.
 *
 * @since 2.0.0
 *
 * @return {JSX.Element} The app.
 */
export function App() {
	const [ route, setRoute ] = useState( readRoute );

	/**
	 * Navigate to a new route by updating the URL and state.
	 *
	 * @since 2.0.0
	 *
	 * @param {Object} params New route params (empty object = dashboard).
	 */
	function navigate( params ) {
		const url = buildUrl( params );
		window.history.pushState( {}, '', url );
		setRoute( readRoute() );
		fixMenuHighlight();
		window.scrollTo( 0, 0 );
	}

	useEffect( function () {
		fixMenuHighlight();

		function onPopState() {
			setRoute( readRoute() );
			fixMenuHighlight();
			window.scrollTo( 0, 0 );
		}

		window.addEventListener( 'popstate', onPopState );
		return function () {
			window.removeEventListener( 'popstate', onPopState );
		};
	}, [] );

	return (
		<ToastProvider>
			<Header navigate={ navigate } />
			<div
				className={
					'bdm-admin-content' +
					( route.module ? ' bdm-admin-content--module' : '' )
				}
			>
				<Router route={ route } navigate={ navigate } />
			</div>
			<Toast />
		</ToastProvider>
	);
}
