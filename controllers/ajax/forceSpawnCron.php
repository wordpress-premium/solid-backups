<?php
/**
 * Force Spawns a Cron AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

die( 'NOT IMPLEMENTED. SEE _getBackupStatus.php for implementation.' );

if ( ! is_admin() ) {
	die( 'Access denied.' ); // Is this necessary due to verifyAjaxAccess?
}

if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
	update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
	spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
}
