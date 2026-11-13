/**
 * Buddy Modules admin entry point.
 *
 * @since 2.0.0
 */
import { createRoot } from '@wordpress/element';

const root = document.getElementById( 'bdm-admin-root' );
if ( root ) {
	createRoot( root ).render(
		<div className="bdm-admin-wrap">
			<h1>Buddy Modules</h1>
		</div>
	);
}
