/**
 * Custom panel screens for modules.
 *
 * Maps "moduleId/panelId" to a lazy-loaded React component. ModuleScreen
 * renders the SettingsForm first (for schema-driven fields), then renders
 * the custom panel below it for module-specific content.
 *
 * Tab Creator's profile-tabs and group-tabs are placeholder divs for now;
 * real list screens are wired in P5.
 *
 * @since 2.0.0
 */

import { lazy } from '@wordpress/element';

/**
 * Map of "moduleId/panelId" to a lazy-loaded React component.
 *
 * Each component receives: { route, navigate, values, onChange }.
 *
 * @type {Object.<string, React.LazyExoticComponent>}
 */
export var CUSTOM_PANEL_SCREENS = {
	'tab-creator/profile-tabs': lazy( function() {
		return Promise.resolve( {
			default: function TabCreatorProfileTabs() {
				return (
					<div className="bdm-admin-placeholder">
						<p>{ 'Profile Tabs list — coming in P5.' }</p>
					</div>
				);
			},
		} );
	} ),

	'tab-creator/group-tabs': lazy( function() {
		return Promise.resolve( {
			default: function TabCreatorGroupTabs() {
				return (
					<div className="bdm-admin-placeholder">
						<p>{ 'Group Tabs list — coming in P5.' }</p>
					</div>
				);
			},
		} );
	} ),
};
