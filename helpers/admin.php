<?php
/**
 * Admin Helper Functions
 *
 * @package BackupBuddy
 */

/**
 * Checks if current page is BackupBuddy Page.
 *
 * @return bool  If is BackupBuddy Admin page.
 */
function backupbuddy_is_admin_page() {
	$screen = get_current_screen();
	if ( false === strpos( $screen->base, 'pb_backupbuddy_' ) ) {
		return false;
	}
	return true;
}
