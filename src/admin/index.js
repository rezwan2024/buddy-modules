/**
 * Buddy Modules admin entry point.
 *
 * @since 2.0.0
 */

import { createRoot } from '@wordpress/element';
import { ErrorBoundary } from '../common/components/ErrorBoundary';
import { App } from './App';
import '../styles/admin.scss';

const root = document.getElementById( 'bdm-admin-root' );
if ( root ) {
	createRoot( root ).render(
		<ErrorBoundary>
			<App />
		</ErrorBoundary>
	);
}
