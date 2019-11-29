<?php
/**
 * Backup Status AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

// IMPORTANT: MUST provide 3rd param, backup serial ID, when using pb_backupbuddy::status() within this function for it to show for this backup.
$serial = trim( pb_backupbuddy::_POST( 'serial' ) );
$serial = str_replace( '/\\', '', $serial );

$init_retry_count = (int) trim( pb_backupbuddy::_POST( 'initwaitretrycount' ) );
$special_action   = pb_backupbuddy::_POST( 'specialAction' );
$sql_file         = pb_backupbuddy::_POST( 'sqlFile' );

backupbuddy_api::getBackupStatus( $serial, $special_action, $init_retry_count, $sql_file, true );

die();
