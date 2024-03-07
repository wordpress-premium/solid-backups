<?php
/**
 * Admin Helper Functions
 *
 * @package BackupBuddy
 */

/**
 * Checks if current page is Solid Backups Page.
 *
 * @return bool  If is Solid Backups Admin page.
 */
function backupbuddy_is_admin_page() {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return false;
	}
	if ( false === strpos( $screen->base, 'pb_backupbuddy_' ) ) {
		return false;
	}
	return true;
}
