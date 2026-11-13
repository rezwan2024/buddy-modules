/**
 * Buddy Modules Dashboard screen.
 *
 * Shows filter pills, a category dropdown, and a grid of module cards.
 * Each card has an optimistic toggle that calls POST /modules/{id}.
 *
 * @since 2.0.0
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { apiRequest } from '../../common/utils/api';
import { useToast } from '../../common/hooks/useToast';

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Extract the unique set of categories from the modules array.
 *
 * @since 2.0.0
 *
 * @param {Array} modules
 * @returns {string[]}
 */
function getCategories( modules ) {
	const seen = {};
	return modules.reduce( ( acc, m ) => {
		if ( m.category && ! seen[ m.category ] ) {
			seen[ m.category ] = true;
			acc.push( m.category );
		}
		return acc;
	}, [] );
}

/**
 * Group modules by their category, preserving order.
 *
 * @since 2.0.0
 *
 * @param {Array}  modules
 * @param {string} [onlyCategory] When set, include only this category.
 * @returns {Array<{ category: string, modules: Array }>}
 */
function groupByCategory( modules, onlyCategory ) {
	const map = {};
	const order = [];

	modules.forEach( ( m ) => {
		const cat = m.category || __( 'General', 'buddy-modules' );
		if ( onlyCategory && cat !== onlyCategory ) {
			return;
		}
		if ( ! map[ cat ] ) {
			map[ cat ] = [];
			order.push( cat );
		}
		map[ cat ].push( m );
	} );

	return order.map( ( cat ) => ( { category: cat, modules: map[ cat ] } ) );
}

// ── BigToggle ─────────────────────────────────────────────────────────────────

/**
 * A large pill-style toggle, styled to match the Platform 3.0 design.
 *
 * @since 2.0.0
 *
 * @param {Object}   props
 * @param {boolean}  props.checked
 * @param {Function} props.onChange  Called with the new boolean value.
 * @param {boolean}  props.disabled
 * @param {string}   props.label    Screen-reader label.
 * @returns {JSX.Element}
 */
function BigToggle( { checked, onChange, disabled, label } ) {
	var classes = 'bdm-admin-big-toggle';
	if ( checked ) {
		classes += ' is-checked';
	}
	if ( disabled ) {
		classes += ' is-disabled';
	}

	return (
		<label className={ classes }>
			<span className="screen-reader-text">{ label }</span>
			<input
				type="checkbox"
				className="bdm-admin-big-toggle__input"
				checked={ checked }
				onChange={ function( e ) {
					if ( ! disabled ) {
						onChange( e.target.checked );
					}
				} }
				disabled={ disabled }
			/>
			<span className="bdm-admin-big-toggle__track" aria-hidden="true">
				<span className="bdm-admin-big-toggle__thumb" />
			</span>
		</label>
	);
}

// ── ModuleCard ────────────────────────────────────────────────────────────────

/**
 * A single module card with an optimistic toggle.
 *
 * @since 2.0.0
 *
 * @param {Object}   props
 * @param {Object}   props.module   Module data object from bdmAdminData.
 * @param {Function} props.onToggle Called with (id, newActiveState) after a successful API call.
 * @param {Function} props.navigate SPA navigate function.
 * @returns {JSX.Element}
 */
function ModuleCard( { module, onToggle, navigate } ) {
	const [ active, setActive ]   = useState( module.active );
	const [ pending, setPending ] = useState( false );
	const { addToast }            = useToast();

	/**
	 * Toggle handler: optimistically flip state, call API, revert on error.
	 *
	 * @since 2.0.0
	 */
	function handleToggle( newActive ) {
		setActive( newActive );
		setPending( true );

		apiRequest( {
			path:   '/modules/' + module.id,
			method: 'POST',
			data:   { active: newActive },
		} )
			.then( function() {
				onToggle( module.id, newActive );
				addToast(
					newActive
						? __( 'Module activated.', 'buddy-modules' )
						: __( 'Module deactivated.', 'buddy-modules' ),
					'success'
				);
			} )
			.catch( function( err ) {
				// Revert optimistic update.
				setActive( ! newActive );
				addToast(
					( err && err.message )
						? err.message
						: __( 'Could not update the module. Please try again.', 'buddy-modules' ),
					'error'
				);
			} )
			.finally( function() {
				setPending( false );
			} );
	}

	function handleSettings( e ) {
		e.preventDefault();
		navigate( { module: module.id } );
	}

	var isDashicon = module.icon && 0 === module.icon.indexOf( 'dashicons-' );

	return (
		<div className={ 'bdm-admin-module-card' + ( active ? ' is-active' : '' ) }>
			<div className="bdm-admin-module-card__icon-wrap" aria-hidden="true">
				{ isDashicon && (
					<span className={ 'dashicons ' + module.icon } />
				) }
				{ ! isDashicon && module.icon && (
					<span className={ 'bb-icon-rf bb-icon-' + module.icon } />
				) }
			</div>

			<h3 className="bdm-admin-module-card__title">{ module.label }</h3>
			<p className="bdm-admin-module-card__description">{ module.description }</p>

			<div className="bdm-admin-module-card__footer">
				<button
					type="button"
					className={ 'bdm-admin-module-card__settings-btn' + ( ! active ? ' is-disabled' : '' ) }
					onClick={ handleSettings }
					disabled={ ! active }
					aria-label={
						/* translators: %s: module label */
						module.label + ' — ' + __( 'Settings', 'buddy-modules' )
					}
				>
					<span className="dashicons dashicons-admin-generic" aria-hidden="true" />
					{ __( 'Settings', 'buddy-modules' ) }
				</button>

				<BigToggle
					checked={ active }
					onChange={ handleToggle }
					disabled={ pending || ! module.available }
					label={
						/* translators: %s: module label */
						__( 'Toggle', 'buddy-modules' ) + ' ' + module.label
					}
				/>
			</div>
		</div>
	);
}

// ── Dashboard ─────────────────────────────────────────────────────────────────

/**
 * Dashboard screen: filter pills, category heading, 4-column card grid.
 *
 * @since 2.0.0
 *
 * @param {Object}   props
 * @param {Object}   props.route    Current route (unused at dashboard level).
 * @param {Function} props.navigate SPA navigate function.
 * @returns {JSX.Element}
 */
export default function Dashboard( { navigate } ) {
	const [ modules, setModules ] = useState(
		function() {
			return ( window.bdmAdminData && window.bdmAdminData.modules ) || [];
		}
	);
	const [ filter, setFilter ]           = useState( 'all' );
	const [ categoryFilter, setCategoryFilter ] = useState( 'all' );

	var activeCount   = modules.filter( function( m ) { return m.active; } ).length;
	var inactiveCount = modules.length - activeCount;

	// Apply status filter.
	var statusFiltered = modules.filter( function( m ) {
		if ( 'active' === filter ) {
			return m.active;
		}
		if ( 'inactive' === filter ) {
			return ! m.active;
		}
		return true;
	} );

	var categories = getCategories( modules );
	var groups     = groupByCategory( statusFiltered, 'all' !== categoryFilter ? categoryFilter : undefined );

	/**
	 * Update a module's active state after a successful API call.
	 *
	 * @since 2.0.0
	 *
	 * @param {string}  id
	 * @param {boolean} newActive
	 */
	function handleToggle( id, newActive ) {
		setModules( function( prev ) {
			return prev.map( function( m ) {
				return m.id === id ? { ...m, active: newActive } : m;
			} );
		} );
	}

	return (
		<div className="bdm-admin-dashboard">
			{ /* Filter bar */ }
			<div className="bdm-admin-filter-bar">
				<div className="bdm-admin-filter-pills" role="group" aria-label={ __( 'Filter modules', 'buddy-modules' ) }>
					<button
						type="button"
						className={ 'bdm-admin-filter-pills__pill' + ( 'all' === filter ? ' is-active' : '' ) }
						onClick={ function() { setFilter( 'all' ); } }
					>
						{ /* translators: %d: total module count */
							__( 'All', 'buddy-modules' ) + ' (' + modules.length + ')' }
					</button>
					<button
						type="button"
						className={ 'bdm-admin-filter-pills__pill' + ( 'active' === filter ? ' is-active' : '' ) }
						onClick={ function() { setFilter( 'active' ); } }
					>
						{ /* translators: %d: active module count */
							__( 'Active', 'buddy-modules' ) + ' (' + activeCount + ')' }
					</button>
					<button
						type="button"
						className={ 'bdm-admin-filter-pills__pill' + ( 'inactive' === filter ? ' is-active' : '' ) }
						onClick={ function() { setFilter( 'inactive' ); } }
					>
						{ /* translators: %d: inactive module count */
							__( 'Inactive', 'buddy-modules' ) + ' (' + inactiveCount + ')' }
					</button>
				</div>

				{ categories.length > 1 && (
					<select
						className="bdm-admin-category-select"
						value={ categoryFilter }
						onChange={ function( e ) { setCategoryFilter( e.target.value ); } }
						aria-label={ __( 'Filter by category', 'buddy-modules' ) }
					>
						<option value="all">{ __( 'All', 'buddy-modules' ) }</option>
						{ categories.map( function( cat ) {
							return <option key={ cat } value={ cat }>{ cat }</option>;
						} ) }
					</select>
				) }
			</div>

			{ /* Category groups */ }
			{ groups.length > 0 ? (
				groups.map( function( group ) {
					return (
						<div key={ group.category } className="bdm-admin-category-group">
							<h2 className="bdm-admin-category-heading">
								{ group.category.toUpperCase() }
							</h2>
							<div className="bdm-admin-module-grid">
								{ group.modules.map( function( mod ) {
									return (
										<ModuleCard
											key={ mod.id }
											module={ mod }
											onToggle={ handleToggle }
											navigate={ navigate }
										/>
									);
								} ) }
							</div>
						</div>
					);
				} )
			) : (
				<div className="bdm-admin-empty-state">
					<p>{ __( 'No modules match the current filter.', 'buddy-modules' ) }</p>
				</div>
			) }
		</div>
	);
}
