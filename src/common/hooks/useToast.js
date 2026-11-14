/**
 * Toast notification context, provider and hook.
 *
 * @since 2.0.0
 */

import {
	createContext,
	useContext,
	useState,
	useRef,
} from '@wordpress/element';

/** @type {Object} Context holding { toasts, addToast, removeToast }, or null outside the provider. */
export const ToastContext = createContext( null );

/**
 * Provide toast state to the component tree.
 *
 * @since 2.0.0
 *
 * @param {Object}      props
 * @param {JSX.Element} props.children
 * @return {JSX.Element} The provider wrapping the children.
 */
export function ToastProvider( { children } ) {
	const [ toasts, setToasts ] = useState( [] );
	const idRef = useRef( 0 );
	const timers = useRef( {} );

	/**
	 * Add a toast notification.
	 *
	 * @since 2.0.0
	 *
	 * @param {string}                     message          Toast text.
	 * @param {'success'|'error'|'saving'} [type='success'] Toast style.
	 * @return {number} Toast ID (pass to removeToast to dismiss early).
	 */
	function addToast( message, type = 'success' ) {
		const id = ++idRef.current;
		setToasts( ( prev ) => [ ...prev, { id, message, type } ] );

		if ( 'error' !== type ) {
			timers.current[ id ] = setTimeout( () => removeToast( id ), 3000 );
		}
		return id;
	}

	/**
	 * Remove a toast by ID.
	 *
	 * @since 2.0.0
	 *
	 * @param {number} id
	 */
	function removeToast( id ) {
		clearTimeout( timers.current[ id ] );
		delete timers.current[ id ];
		setToasts( ( prev ) => prev.filter( ( t ) => t.id !== id ) );
	}

	return (
		<ToastContext.Provider value={ { toasts, addToast, removeToast } }>
			{ children }
		</ToastContext.Provider>
	);
}

/**
 * Consume the ToastContext.
 *
 * @since 2.0.0
 *
 * @return {{ toasts: Array, addToast: Function, removeToast: Function }} The toast state and actions.
 */
export function useToast() {
	return useContext( ToastContext );
}
