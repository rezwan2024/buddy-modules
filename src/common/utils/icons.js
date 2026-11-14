/**
 * Icon helpers shared by the dashboard cards and the side navigation.
 *
 * @since 2.0.0
 */

/**
 * Icon class for a module or panel icon: 'dashicons-*' keeps Dashicons,
 * anything else is a BuddyBoss line icon name (bb-icons-rl-*), the icon set
 * the BuddyBoss Platform admin uses.
 *
 * @since 2.0.0
 *
 * @param {string} icon Icon identifier from the module/panel schema.
 * @return {string} Class names for the icon element.
 */
export function getIconClass( icon ) {
	if ( 0 === icon.indexOf( 'dashicons-' ) ) {
		return 'dashicons ' + icon;
	}
	return 'bb-icons-rl ' + icon;
}
