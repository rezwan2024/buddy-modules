/**
 * Buddy Modules api-fetch wrapper.
 *
 * Initialises @wordpress/api-fetch with the nonce and REST root from
 * bdmAdminData on first use, then re-exports it as apiRequest.
 *
 * @since 2.0.0
 */

import apiFetch from '@wordpress/api-fetch';

let initialized = false;

/**
 * Register api-fetch nonce middleware on first use.
 */
function init() {
	if ( initialized ) {
		return;
	}
	initialized = true;

	const data = window.bdmAdminData || {};
	if ( data.nonce ) {
		apiFetch.use( apiFetch.createNonceMiddleware( data.nonce ) );
	}
}

/**
 * Make a REST API request.
 *
 * Paths are relative to `bdmAdminData.restRoot`, e.g. '/modules/tab-creator'.
 * We build the absolute URL ourselves (restRoot + path) so the namespace is
 * never stripped by api-fetch's root-URL middleware.
 *
 * @since 2.0.0
 *
 * @param {Object} options apiFetch options (path, method, data, …).
 * @returns {Promise<*>}
 */
export function apiRequest( options ) {
	init();

	if ( options.path ) {
		const root        = ( window.bdmAdminData && window.bdmAdminData.restRoot ) || '';
		const { path, ...rest } = options;
		return apiFetch( { ...rest, url: root + path } );
	}

	return apiFetch( options );
}
