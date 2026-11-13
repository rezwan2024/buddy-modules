/**
 * Buddy Modules client-side router.
 *
 * Maps the current route object (derived from URL query params) to the
 * correct screen component. ModuleScreen and deeper views are added in P3+.
 *
 * @since 2.0.0
 */

import Dashboard from './screens/Dashboard';
import { ModuleScreen } from './screens/ModuleScreen';

/**
 * Renders the screen that matches the current route.
 *
 * @since 2.0.0
 *
 * @param {Object}   props
 * @param {{ module: string|null, panel: string|null, view: string|null, id: number|null }} props.route
 * @param {Function} props.navigate
 * @returns {JSX.Element}
 */
export function Router( { route, navigate } ) {
	// No module selected → Dashboard.
	if ( ! route.module ) {
		return <Dashboard route={ route } navigate={ navigate } />;
	}

	return <ModuleScreen route={ route } navigate={ navigate } />;
}
