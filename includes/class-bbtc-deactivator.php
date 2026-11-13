<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin deactivation. Flushes rewrite rules only — no data is deleted.
 */
class BBTC_Deactivator {

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
