<?php
/**
 * Manage FTP Remote Destination
 * Incoming variables:
 *   array $destination     Destination settings.
 *
 * @package BackupBuddy
 * @author Skyler Moore
 */

if ( isset( $destination['disabled'] ) && '1' == $destination['disabled'] ) {
	die(
		sprintf(
			'<span class="description">%s</span>',
			esc_html__( 'This destination is currently disabled based on its settings. Re-enable it under its Advanced Settings.', 'it-l10n-backupbuddy' )
		)
	);
}

$destination_id = pb_backupbuddy::_GET( 'destination_id' );

// Set reference to destination.
if ( isset( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) {
	$destination = &pb_backupbuddy::$options['remote_destinations'][ $destination_id ];
} else {
	pb_backupbuddy::alert( 'Error: Invalid Destination `' . $destination_id . '`.', true, '', '', '', array( 'class' => 'below-h2' ) );
	return;
}

// Delete ftp backups.
if ( 'delete_backup' === pb_backupbuddy::_POST( 'bulk_action' ) ) {

	pb_backupbuddy::verify_nonce();

	$delete_count = 0;
	$delete_items = (array) pb_backupbuddy::_POST( 'items' );

	if ( ! empty( $delete_items ) ) {
		$conn_id = pb_backupbuddy_destination_ftp::connect( $destination );
		if ( $conn_id ) {
			// Loop through and delete ftp backup files.
			foreach ( $delete_items as $backup ) {
				// Try to delete backup.
				if ( pb_backupbuddy_destination_ftp::delete( $destination, $backup, $conn_id ) ) {
					$delete_count++;
				} else {
					pb_backupbuddy::alert( 'Unable to delete file `' . $ftp_directory . '/' . $backup . '`.' );
				}
			}

			// Close this connection.
			ftp_close( $conn_id );
		}
	}

	if ( $delete_count > 0 ) {
		pb_backupbuddy::alert( sprintf( _n( 'Deleted %d file.', 'Deleted %d files.', $delete_count, 'it-l10n-backupbuddy' ), $delete_count ) );
	} else {
		pb_backupbuddy::alert( __( 'No backups were deleted.', 'it-l10n-backupbuddy' ) );
	}
	echo '<br>';
}

// Copy ftp backups to the local backup files.
if ( pb_backupbuddy::_GET( 'cpy' ) ) {
	$copy = pb_backupbuddy::_GET( 'cpy' );
	pb_backupbuddy::alert( 'The remote file is now being copied to your local backups. If the backup gets marked as bad during copying, please wait a bit then click the `Refresh` icon to rescan after the transfer is complete.' );
	echo '<br>';
	pb_backupbuddy::status( 'details', 'Scheduling Cron for creating ftp copy.' );
	backupbuddy_core::schedule_single_event( time(), 'process_destination_copy', array( $destination, $copy ) );

	if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
		update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
		spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
	}
}

// Find backups in directory.
backupbuddy_backups()->set_destination_id( $destination_id );

$backups = pb_backupbuddy_destinations::listFiles( $destination );

backupbuddy_backups()->table(
	'default',
	$backups,
	array(
		'action'         => pb_backupbuddy::ajax_url( 'remoteClient' ) . '&destination_id=' . htmlentities( $destination_id ),
		'destination_id' => $destination_id,
		'class'          => 'minimal',
	)
);
