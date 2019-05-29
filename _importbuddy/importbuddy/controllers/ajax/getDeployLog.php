<?php
if ( ! defined( 'PB_IMPORTBUDDY' ) || ( true !== PB_IMPORTBUDDY ) ) {
	die( '<html></html>' );
}

if ( 'true' != pb_backupbuddy::_GET( 'deploy' ) ) {
	die( 'Access denied.' );
}

// Only allow access to this file if it has a serial hiding it. Used by deployment.
global $importbuddy_file;
$importFileSerial = backupbuddy_core::get_serial_from_file( $importbuddy_file );
if ( '' == $importFileSerial ) {
	die( 'Access denied.' );
}

pb_backupbuddy::status( 'details', '*** End ImportBuddy Log Section (Deployment)' );

// Log to make a copy of retrieved deploy info into.
$log_directory = backupbuddy_core::getLogDirectory();
$backup_log_file = $log_directory . 'status-deploycopy-' . $importFileSerial . '.txt';

$status_lines = pb_backupbuddy::get_status( '', true, false, true, $backup_log_file ); // Clear file, dont unlink file, supress status retrieval msg, backup into $log_file
echo implode( '', $status_lines );
