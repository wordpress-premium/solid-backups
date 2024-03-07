<?php
/**
 * Send backup archive to a remote destination manually. Optionally sends importbuddy.php with files.
 * Sends are scheduled to run in a cron and are passed to the cron.php remote_send() method.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$success_output = false; // Set to true onece a leading 1 has been sent to the javascript to indicate success.
$destination_id = pb_backupbuddy::_POST( 'destination_id' );
$backup_file    = '';

if ( 'importbuddy.php' != pb_backupbuddy::_POST( 'file' ) ) {
	$backup_file = backupbuddy_core::getBackupDirectory() . pb_backupbuddy::_POST( 'file' );
	if ( ! file_exists( $backup_file ) ) { // Error if file to send did not exist!
		$error_message = 'Unable to find file `' . esc_html( $backup_file ) . '` to send. File does not appear to exist. You can try again in a moment or turn on full error logging and try again to log for support.';
		pb_backupbuddy::status( 'error', $error_message );
		echo $error_message;
		die();
	}
	if ( is_dir( $backup_file ) ) { // Error if a directory is trying to be sent.
		$error_message = 'You are attempting to send a directory, `' . esc_html( $backup_file ) . '`. Try again and verify there were no javascript errors.';
		pb_backupbuddy::status( 'error', $error_message );
		echo $error_message;
		die();
	}
}

// Send Importer along-side?
if ( '1' == pb_backupbuddy::_POST( 'send_importbuddy' ) ) {
	$send_importbuddy = true;
	pb_backupbuddy::status( 'details', 'Cron send to be scheduled with importbuddy sending.' );
} else {
	$send_importbuddy = false;
	pb_backupbuddy::status( 'details', 'Cron send to be scheduled WITHOUT importbuddy sending.' );
}

// Delete local copy after send completes?
if ( 'true' == pb_backupbuddy::_POST( 'delete_after' ) ) {
	$delete_after = true;
	pb_backupbuddy::status( 'details', 'Remote send set to delete after successful send.' );
} else {
	$delete_after = false;
	pb_backupbuddy::status( 'details', 'Remote send NOT set to delete after successful send.' );
}

if ( ! isset( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) {
	die( 'Error #833383: Invalid destination ID `' . htmlentities( $destination_id ) . '`.' );
}

pb_backupbuddy::status( 'details', 'Scheduling cron to send to this remote destination...' );
$schedule_result = backupbuddy_core::trigger_async_event( 'remote_send', array( $destination_id, $backup_file, pb_backupbuddy::_POST( 'trigger' ), $send_importbuddy, $delete_after ) );

if ( false === $schedule_result ) {
	$err = 'Error scheduling file transfer. Please check your Solid Backups error log for details. A plugin may have prevented scheduling or the database rejected it.';
	pb_backupbuddy::status( 'error', $err );
	echo $err;
} else {
	pb_backupbuddy::status( 'details', 'Cron to send to remote destination scheduled.' );
}

backupbuddy_core::maybe_spawn_cron();

// SEE cron.php remote_send() for sending function that we pass to via the cron above.
if ( false === $success_output ) {
	echo 1;
}

die();
