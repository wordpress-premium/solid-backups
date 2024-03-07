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

backupbuddy_core::maybe_spawn_cron();
