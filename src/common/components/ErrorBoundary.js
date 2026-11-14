/**
 * React error boundary for the Buddy Modules admin.
 *
 * @since 2.0.0
 */

import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Catches unhandled render errors and shows a fallback message.
 *
 * @since 2.0.0
 */
export class ErrorBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { hasError: false, message: '' };
	}

	static getDerivedStateFromError( error ) {
		return { hasError: true, message: error.message || '' };
	}

	componentDidCatch( error, info ) {
		// eslint-disable-next-line no-console
		console.error( 'Buddy Modules error boundary caught:', error, info );
	}

	render() {
		if ( this.state.hasError ) {
			return (
				<div className="bdm-admin-error-boundary notice notice-error">
					<p>
						<strong>
							{ __(
								'Buddy Modules encountered an error.',
								'buddy-modules'
							) }
						</strong>
					</p>
					{ this.state.message && (
						<p>
							<code>{ this.state.message }</code>
						</p>
					) }
					<p>
						{ __(
							'Please reload the page. If the problem persists, deactivate and reactivate the plugin.',
							'buddy-modules'
						) }
					</p>
				</div>
			);
		}
		return this.props.children;
	}
}
