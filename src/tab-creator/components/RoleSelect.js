/**
 * RoleSelect — compact chip-toggle dropdown used by the tab editors
 * (profile availability, group "enabled for").
 *
 * @since 2.0.0
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * RoleSelect — compact multi-select dropdown showing chips for each option.
 *
 * @param {Object}   props
 * @param {Array}    props.options  Array of {value, label}.
 * @param {Array}    props.selected Array of selected value strings.
 * @param {Function} props.onChange Called with the new selected array.
 * @param {string}   [props.id]     ID for the trigger button (for an outside <label>).
 * @return {JSX.Element} The role select.
 */
export default function RoleSelect( { options, selected, onChange, id } ) {
	const [ open, setOpen ] = useState( false );
	const wrapRef = useRef( null );
	const triggerRef = useRef( null );

	useEffect(
		function () {
			if ( ! open ) {
				return;
			}
			function handleOutsideClick( e ) {
				if (
					wrapRef.current &&
					! wrapRef.current.contains( e.target )
				) {
					setOpen( false );
				}
			}
			document.addEventListener( 'mousedown', handleOutsideClick );
			return function () {
				document.removeEventListener( 'mousedown', handleOutsideClick );
			};
		},
		[ open ]
	);

	function getSummary() {
		if ( selected.length === 0 ) {
			return __( 'None selected', 'buddy-modules' );
		}
		if ( selected.length === 1 ) {
			const found = options.find( function ( o ) {
				return o.value === selected[ 0 ];
			} );
			return found ? found.label : selected[ 0 ];
		}
		return sprintf(
			// translators: %d is the number of selected roles/options.
			__( '%d selected', 'buddy-modules' ),
			selected.length
		);
	}

	// Escape closes only the dropdown (not the surrounding modal) and
	// returns focus to the trigger.
	function handleKeyDown( e ) {
		if ( 'Escape' === e.key && open ) {
			e.stopPropagation();
			e.preventDefault();
			setOpen( false );
			if ( triggerRef.current ) {
				triggerRef.current.focus();
			}
		}
	}

	function toggleOption( value ) {
		const next =
			selected.indexOf( value ) !== -1
				? selected.filter( function ( v ) {
						return v !== value;
				  } )
				: selected.concat( [ value ] );
		onChange( next );
	}

	return (
		<div className="bdm-admin-role-select" ref={ wrapRef }>
			<button
				type="button"
				id={ id }
				ref={ triggerRef }
				className="bdm-admin-role-select__trigger"
				onClick={ function () {
					setOpen( function ( v ) {
						return ! v;
					} );
				} }
				aria-expanded={ open }
				onKeyDown={ handleKeyDown }
			>
				<span className="bdm-admin-role-select__summary">
					{ getSummary() }
				</span>
				<i
					className={
						'bdm-admin-role-select__arrow bb-icons-rl ' +
						( open
							? 'bb-icons-rl-caret-up'
							: 'bb-icons-rl-caret-down' )
					}
					aria-hidden="true"
				/>
			</button>
			{ open && (
				<div className="bdm-admin-role-select__dropdown">
					<div className="bdm-admin-role-select__chips">
						{ options.map( function ( opt ) {
							const isSelected =
								selected.indexOf( opt.value ) !== -1;
							return (
								<button
									key={ opt.value }
									type="button"
									className={
										'bdm-admin-role-select__chip' +
										( isSelected
											? ' bdm-admin-role-select__chip--selected'
											: '' )
									}
									aria-pressed={ isSelected }
									onClick={ function () {
										toggleOption( opt.value );
									} }
									onKeyDown={ handleKeyDown }
								>
									{ opt.label }
								</button>
							);
						} ) }
					</div>
				</div>
			) }
		</div>
	);
}
