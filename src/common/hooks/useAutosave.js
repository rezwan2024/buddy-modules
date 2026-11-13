/**
 * Autosave hook — 1-second debounce, single-flight, toast feedback.
 *
 * Returns a stable `save( settings )` function. Call it any time a field
 * changes; it debounces 1 s, then sends one POST to /settings/{module} and
 * shows Saving… → Saved (or Error) toasts.
 *
 * @since 2.0.0
 */

import { useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { apiRequest } from '../utils/api';
import { useToast } from './useToast';

/**
 * @since 2.0.0
 *
 * @param {string} module Module ID (e.g. 'tab-creator').
 * @param {string} panel  Panel slug (e.g. 'profile-tabs').
 * @returns {Function} save( settings ) — call with the full panel settings object.
 */
export function useAutosave( module, panel ) {
	const { addToast, removeToast } = useToast();

	// Use refs so the stable save() closure always reads the latest values.
	const addToastRef    = useRef( addToast );
	const removeToastRef = useRef( removeToast );
	const moduleRef      = useRef( module );
	const panelRef       = useRef( panel );

	addToastRef.current    = addToast;
	removeToastRef.current = removeToast;
	moduleRef.current      = module;
	panelRef.current       = panel;

	const timerRef    = useRef( null );
	const inFlightRef = useRef( false );
	const toastRef    = useRef( null );

	// Stable function created once; reads latest values via refs.
	const save = useRef( function saveSettings( settings ) {
		// Cancel any pending debounce.
		if ( timerRef.current ) {
			clearTimeout( timerRef.current );
		}

		timerRef.current = setTimeout( async function() {
			// Single-flight: skip if a save is already in progress.
			if ( inFlightRef.current ) {
				return;
			}

			inFlightRef.current = true;

			// Dismiss the previous toast.
			if ( null !== toastRef.current ) {
				removeToastRef.current( toastRef.current );
				toastRef.current = null;
			}

			toastRef.current = addToastRef.current(
				__( 'Saving…', 'buddy-modules' ),
				'saving'
			);

			try {
				await apiRequest( {
					path:   '/settings/' + moduleRef.current,
					method: 'POST',
					data:   { panel: panelRef.current, settings },
				} );

				removeToastRef.current( toastRef.current );
				toastRef.current = addToastRef.current(
					__( 'Saved', 'buddy-modules' ),
					'success'
				);
			} catch ( _err ) {
				removeToastRef.current( toastRef.current );
				toastRef.current = addToastRef.current(
					__( 'Error saving settings.', 'buddy-modules' ),
					'error'
				);
			} finally {
				inFlightRef.current = false;
			}
		}, 1000 );
	} ).current;

	return save;
}
