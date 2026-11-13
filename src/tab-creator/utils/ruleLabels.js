/**
 * Human-readable labels for tab availability and visibility rules.
 *
 * JS port of the legacy PHP label_availability_rule / label_visibility_rule
 * helpers. Used by TabsList and ExistingTabsList to summarise rules columns.
 *
 * @since 2.0.0
 */

import { __ } from '@wordpress/i18n';

/**
 * @param {string} key   rule_key from DB.
 * @param {string} value rule_value from DB.
 * @returns {string}
 */
function labelAvailabilityRule( key, value ) {
	if ( 'scope' === key ) {
		const map = {
			all_members: __( 'All Members', 'buddy-modules' ),
			none:        __( 'None', 'buddy-modules' ),
		};
		return map[ value ] || value;
	}
	if ( 'role' === key ) {
		return value;
	}
	if ( 'user_id' === key ) {
		// translators: %s is a numeric user ID.
		return 'User #' + value;
	}
	if ( 'inherit' === key ) {
		return __( 'Inherited', 'buddy-modules' );
	}
	return value;
}

/**
 * @param {string} key   rule_key from DB.
 * @param {string} value rule_value from DB.
 * @returns {string}
 */
function labelVisibilityRule( key, value ) {
	if ( 'audience' === key ) {
		const map = {
			do_not_modify: __( 'Do Not Modify', 'buddy-modules' ),
			anyone:        __( 'Anyone', 'buddy-modules' ),
			none_remove:   __( 'None', 'buddy-modules' ),
			logged_in:     __( 'Logged In', 'buddy-modules' ),
			owner_only:    __( 'Profile Owner', 'buddy-modules' ),
			friends:       __( 'Friends', 'buddy-modules' ),
			site_admin:    __( 'Site Admin', 'buddy-modules' ),
		};
		return map[ value ] || value;
	}
	if ( 'role' === key ) {
		return value;
	}
	if ( 'inherit' === key ) {
		return __( 'Inherited', 'buddy-modules' );
	}
	return value;
}

/**
 * Build a summary string for a rules array and rule_type.
 *
 * Returns 'All' (translated) when no rules of that type exist, matching
 * the legacy list table behaviour.
 *
 * @since 2.0.0
 *
 * @param {Array}  rules     Tab's rules array from the REST response.
 * @param {string} ruleType  'availability' or 'visibility'.
 * @returns {string}
 */
export function getRuleSummary( rules, ruleType ) {
	if ( ! Array.isArray( rules ) ) {
		return __( 'All', 'buddy-modules' );
	}

	var parts = rules
		.filter( function( r ) { return r.rule_type === ruleType; } )
		.map( function( r ) {
			return 'availability' === ruleType
				? labelAvailabilityRule( r.rule_key, r.rule_value )
				: labelVisibilityRule( r.rule_key, r.rule_value );
		} )
		.filter( Boolean );

	if ( parts.length === 0 ) {
		return __( 'All', 'buddy-modules' );
	}

	return parts.join( ', ' );
}
