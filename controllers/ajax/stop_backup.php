<?php
/**
 * Button to Stop Backup
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();
$serial = pb_backupbuddy::_POST( 'serial' );
set_transient( 'pb_backupbuddy_stop_backup-' . $serial, true, ( 60 * 60 * 24 ) );

die( '1' );
