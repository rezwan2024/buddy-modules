<?php
/**
 * Front-end output of custom tab / sub-tab content.
 *
 * @since 2.0.0
 */

namespace BuddyModules\TabCreator;

defined( 'ABSPATH' ) || exit;

/**
 * Formats stored tab content for display on profile and group pages.
 *
 * @since 2.0.0
 */
class Content {

	/**
	 * Returns tab content ready to print.
	 *
	 * Same order WordPress uses for post content: line breaks become paragraphs
	 * (wpautop), shortcodes that sit on their own line are unwrapped
	 * (shortcode_unautop), then shortcodes run. The result is passed through
	 * wp_kses_post(), as before.
	 *
	 * @since 2.0.0
	 *
	 * @param string $content Stored content (after the bdm_tc_tab_content filter).
	 * @return string Safe HTML.
	 */
	public static function render( string $content ): string {
		return wp_kses_post( do_shortcode( shortcode_unautop( wpautop( $content ) ) ) );
	}
}
