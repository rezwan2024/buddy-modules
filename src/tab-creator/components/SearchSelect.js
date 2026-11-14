/**
 * SearchSelect — async search + multi-select for users or groups.
 *
 * Props:
 *   selectedItems  [{id, label}]       — currently selected items.
 *   onChange       fn([{id, label}])   — called when selection changes.
 *   fetchOptions   async fn(search) → [{id, label}]
 *   placeholder    string
 *
 * @since 2.0.0
 */

import { useState, useRef, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Spinner } from '@wordpress/components';

/**
 * @param {Object}   props
 * @param {Array}    props.selectedItems
 * @param {Function} props.onChange
 * @param {Function} props.fetchOptions
 * @param {string}   [props.placeholder]
 * @param {string}   [props.inputId]     ID for the search input (for an outside <label>).
 * @return {JSX.Element} The search select.
 */
export default function SearchSelect( {
	selectedItems,
	onChange,
	fetchOptions,
	placeholder,
	inputId,
} ) {
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const [ open, setOpen ] = useState( false );

	const debounceRef = useRef( null );
	const wrapRef = useRef( null );

	const doSearch = useCallback(
		function ( term ) {
			setLoading( true );
			fetchOptions( term )
				.then( function ( opts ) {
					setResults( opts );
					setOpen( true );
				} )
				.finally( function () {
					setLoading( false );
				} );
		},
		[ fetchOptions ]
	);

	function handleSearchChange( e ) {
		const val = e.target.value;
		setSearch( val );

		if ( debounceRef.current ) {
			clearTimeout( debounceRef.current );
		}
		debounceRef.current = setTimeout( function () {
			doSearch( val );
		}, 300 );
	}

	function handleSearchFocus() {
		if ( results.length === 0 && ! loading ) {
			doSearch( search );
		} else {
			setOpen( true );
		}
	}

	function handleSelect( item ) {
		const alreadySelected = selectedItems.some( function ( s ) {
			return s.id === item.id;
		} );
		if ( ! alreadySelected ) {
			onChange( selectedItems.concat( [ item ] ) );
		}
		setSearch( '' );
		setOpen( false );
	}

	function handleRemove( id ) {
		onChange(
			selectedItems.filter( function ( s ) {
				return s.id !== id;
			} )
		);
	}

	function handleBlur( e ) {
		// Close only if focus moves outside the wrapper.
		if (
			wrapRef.current &&
			! wrapRef.current.contains( e.relatedTarget )
		) {
			setOpen( false );
		}
	}

	// Escape closes the result list first (not the surrounding modal).
	function handleKeyDown( e ) {
		if ( 'Escape' === e.key && open ) {
			e.stopPropagation();
			e.preventDefault();
			setOpen( false );
		}
	}

	const selectedIds = selectedItems.map( function ( s ) {
		return s.id;
	} );
	const unselected = results.filter( function ( r ) {
		return selectedIds.indexOf( r.id ) === -1;
	} );

	return (
		<div
			className="bdm-admin-search-select"
			ref={ wrapRef }
			onBlur={ handleBlur }
			onKeyDown={ handleKeyDown }
			role="presentation"
		>
			{ /* Chips */ }
			{ selectedItems.length > 0 && (
				<div className="bdm-admin-search-select__chips">
					{ selectedItems.map( function ( item ) {
						return (
							<span
								key={ item.id }
								className="bdm-admin-search-select__chip"
							>
								{ item.label }
								<button
									type="button"
									className="bdm-admin-search-select__chip-remove"
									onClick={ function () {
										handleRemove( item.id );
									} }
									aria-label={ sprintf(
										/* translators: %s: selected user or group name. */
										__( 'Remove %s', 'buddy-modules' ),
										item.label
									) }
								>
									&times;
								</button>
							</span>
						);
					} ) }
				</div>
			) }

			{ /* Input */ }
			<div className="bdm-admin-search-select__input-wrap">
				<input
					type="text"
					id={ inputId }
					className="bdm-admin-search-select__input"
					value={ search }
					onChange={ handleSearchChange }
					onFocus={ handleSearchFocus }
					placeholder={
						placeholder || __( 'Search…', 'buddy-modules' )
					}
					autoComplete="off"
				/>
				{ loading && <Spinner /> }
			</div>

			{ /* Dropdown */ }
			{ open && unselected.length > 0 && (
				<ul className="bdm-admin-search-select__dropdown">
					{ unselected.map( function ( item ) {
						return (
							<li key={ item.id }>
								<button
									type="button"
									className="bdm-admin-search-select__option"
									onMouseDown={ function ( e ) {
										// Keep focus in the field so blur doesn't close the list first.
										e.preventDefault();
									} }
									onClick={ function () {
										handleSelect( item );
									} }
								>
									{ item.label }
								</button>
							</li>
						);
					} ) }
				</ul>
			) }

			{ open && ! loading && unselected.length === 0 && search && (
				<div className="bdm-admin-search-select__empty">
					{ __( 'No results found.', 'buddy-modules' ) }
				</div>
			) }
		</div>
	);
}
