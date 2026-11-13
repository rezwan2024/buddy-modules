/**
 * Helpers to convert between the flat REST rules array and the structured
 * form state used by TabEditor / ExistingTabEditor.
 *
 * REST format (read): [ { rule_type, rule_key, rule_value }, ... ]
 *
 * Form format (write):
 *   Profile tabs —
 *     availability:      string[]  ('all_members', 'none_wp', '<wp-role-slug>')
 *     associated_users:  number[]  (user IDs)
 *     visibility:        string[]  ('anyone', 'logged_in', …, '<wp-role-slug>')
 *
 *   Group tabs —
 *     enabled_for:         string[]  ('all', 'public', 'private', 'hidden', 'none')
 *     associated_groups:   number[]
 *     excluded_groups:     number[]
 *     visibility:          string[]  ('anyone', 'logged_in', 'members', …)
 *
 * @since 2.0.0
 */

/**
 * Parse a flat rules array from the REST response into form state.
 *
 * @since 2.0.0
 *
 * @param {Array}  rules   Tab / override rules array from REST.
 * @param {string} tabType 'profile' or 'group'.
 * @returns {Object}
 */
export function parseRules( rules, tabType ) {
	var out = {
		availability:      [],
		associated_users:  [],
		enabled_for:       [],
		associated_groups: [],
		excluded_groups:   [],
		visibility:        [],
	};

	if ( ! Array.isArray( rules ) ) {
		return out;
	}

	rules.forEach( function( r ) {
		if ( 'availability' === r.rule_type ) {
			if ( 'scope' === r.rule_key ) {
				if ( 'profile' === tabType ) {
					if ( 'all_members' === r.rule_value ) {
						out.availability.push( 'all_members' );
					} else if ( 'none' === r.rule_value ) {
						out.availability.push( 'none_wp' );
					}
				} else {
					if ( 'all' === r.rule_value ) {
						out.enabled_for.push( 'all' );
					} else if ( 'none' === r.rule_value ) {
						out.enabled_for.push( 'none' );
					}
				}
			} else if ( 'role' === r.rule_key && 'profile' === tabType ) {
				out.availability.push( r.rule_value );
			} else if ( 'user_id' === r.rule_key ) {
				var uid = parseInt( r.rule_value, 10 );
				if ( uid > 0 ) {
					out.associated_users.push( uid );
				}
			} else if ( 'group_status' === r.rule_key ) {
				out.enabled_for.push( r.rule_value );
			} else if ( 'group_id' === r.rule_key ) {
				var gid = parseInt( r.rule_value, 10 );
				if ( gid > 0 ) {
					out.associated_groups.push( gid );
				}
			} else if ( 'excluded_group_id' === r.rule_key ) {
				var eid = parseInt( r.rule_value, 10 );
				if ( eid > 0 ) {
					out.excluded_groups.push( eid );
				}
			}
		} else if ( 'visibility' === r.rule_type ) {
			if ( 'audience' === r.rule_key || 'role' === r.rule_key ) {
				out.visibility.push( r.rule_value );
			}
		}
	} );

	return out;
}

/**
 * Serialize form rule state into the payload expected by Tab_Service::save_tab.
 *
 * @since 2.0.0
 *
 * @param {Object} formRules  Structured rules from form state.
 * @param {number[]} associatedUserIds   User IDs for associated_users.
 * @param {number[]} associatedGroupIds  Group IDs for associated_groups.
 * @param {number[]} excludedGroupIds    Group IDs for excluded_groups.
 * @returns {Object}
 */
export function serializeRules( formRules, associatedUserIds, associatedGroupIds, excludedGroupIds ) {
	return {
		availability:      formRules.availability      || [],
		associated_users:  associatedUserIds           || [],
		enabled_for:       formRules.enabled_for       || [],
		associated_groups: associatedGroupIds          || [],
		excluded_groups:   excludedGroupIds            || [],
		visibility:        formRules.visibility        || [],
	};
}
