/**
 * Toast notification list.
 *
 * Renders the current toasts from ToastContext in a fixed overlay.
 *
 * @since 2.0.0
 */

import { __ } from '@wordpress/i18n';
import { useToast } from '../hooks/useToast';

/**
 * Renders all active toast notifications.
 *
 * @since 2.0.0
 *
 * @returns {JSX.Element|null}
 */
export function Toast() {
	const { toasts, removeToast } = useToast();

	if ( ! toasts.length ) {
		return null;
	}

	return (
		<div className="bdm-admin-toast-list" role="region" aria-label={ __( 'Notifications', 'buddy-modules' ) } aria-live="polite">
			{ toasts.map( ( toast ) => (
				<div
					key={ toast.id }
					className={ `bdm-admin-toast bdm-admin-toast--${ toast.type }` }
					role="alert"
				>
					<span className="bdm-admin-toast__message">{ toast.message }</span>
					<button
						type="button"
						className="bdm-admin-toast__dismiss"
						onClick={ () => removeToast( toast.id ) }
						aria-label={ __( 'Dismiss', 'buddy-modules' ) }
					>
						&times;
					</button>
				</div>
			) ) }
		</div>
	);
}
