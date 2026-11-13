<?php
/**
 * Register the Profile and Group Tab Creator module with Buddy Modules.
 *
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

BuddyModules\Modules\Registry::get_instance()->register(
	new BuddyModules\TabCreator\Module()
);
