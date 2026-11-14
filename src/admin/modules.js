/**
 * Custom panel screens for modules.
 *
 * Maps "moduleId/panelId" to a lazy-loaded React component. ModuleScreen
 * renders the SettingsForm first (for schema-driven fields), then renders
 * the custom panel below it for module-specific content.
 *
 * @since 2.0.0
 */

import { lazy } from '@wordpress/element';

/**
 * Map of "moduleId/panelId" to a lazy-loaded React component.
 *
 * Each component receives: { route, navigate, values, onChange }.
 *
 * @type {Object.<string, Object>}
 */
export const CUSTOM_PANEL_SCREENS = {
	'tab-creator/profile-tabs': lazy( function () {
		return import( '../tab-creator/components/TabCreatorPanel' );
	} ),

	'tab-creator/group-tabs': lazy( function () {
		return import( '../tab-creator/components/TabCreatorPanel' );
	} ),

	'tab-creator/profile-existing-tabs': lazy( function () {
		return import( '../tab-creator/components/ExistingTabCreatorPanel' );
	} ),

	'tab-creator/group-existing-tabs': lazy( function () {
		return import( '../tab-creator/components/ExistingTabCreatorPanel' );
	} ),
};
