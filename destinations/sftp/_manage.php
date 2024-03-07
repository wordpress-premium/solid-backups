<?php
/**
 * Manage Destination for sFTP
 *
 * Incoming variables:
 *   array $destination  Destination settings.
 *
 * @requires pb_backupbuddy
 * @requires pb_backupbuddy_destination_sftp
 * @requires Net_SFTP
 * @requires backupbuddy_core
 *
 * @package BackupBuddy
 * @author Dustin Bolton
 * @copyright 2013 (Summer)
 */

if ( isset( $destination['disabled'] ) && '1' == $destination['disabled'] ) {
	die( __( '<span class="description">This destination is currently disabled based on its settings. Re-enable it under its Advanced Settings.</span>', 'it-l10n-backupbuddy' ) );
}

require_once pb_backupbuddy::plugin_path() . '/destinations/sftp/init.php';

$destination_id = pb_backupbuddy::_GET( 'destination_id' );

// Set reference to destination.
if ( isset( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) {
	$destination = &pb_backupbuddy::$options['remote_destinations'][ $destination_id ];
} else {
	pb_backupbuddy::alert( 'Error: Invalid Destination `' . $destination_id . '`.', true, '', '', '', array( 'class' => 'below-h2' ) );
	return;
}

$sftp = pb_backupbuddy_destination_sftp::connect( $destination );

if ( ! $sftp ) {
	pb_backupbuddy::alert( __( 'Connection to sFTP server failed. Please check your sFTP credentials.', 'it-l10n-backupbuddy' ), true, '', '', '', array( 'class' => 'below-h2' ) );
	return false;
}

// Handle Copy.
if ( pb_backupbuddy::_GET( 'cpy' ) ) {
	// Copy sFTP backups to the local backup files.
	$copy = pb_backupbuddy::_GET( 'cpy' );
	pb_backupbuddy::alert( 'The remote file is now being copied to your local backups. If the transfer gets interrupted, click the "Refresh" icon after the transfer is complete to try again.' );
	echo '<br>';
	pb_backupbuddy::status( 'details', 'Scheduling Cron for creating sFTP copy.' );
	backupbuddy_core::schedule_single_event( time(), 'process_destination_copy', array( $destination, $copy ) );

	backupbuddy_core::maybe_spawn_cron();
}

// Delete sftp backups.
if ( 'delete_backup' === pb_backupbuddy::_POST( 'bulk_action' ) ) {
	pb_backupbuddy::verify_nonce();

	$delete_count = 0;

	if ( ! class_exists( 'pb_backupbuddy_destinations' ) ) {
		require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
	}

	// Loop through and delete sftp backup files.
	foreach ( (array) pb_backupbuddy::_POST( 'items' ) as $backup ) {
		// Try to delete backup.
		if ( true === pb_backupbuddy_destinations::delete( $destination, $backup ) ) {
			$delete_count++;
		} else {
			pb_backupbuddy::alert( 'Unable to delete file `' . $destination['path'] . '/' . $backup . '`.', false, '', '', '', array( 'class' => 'below-h2' ) );
		}
	}

	if ( $delete_count > 0 ) {
		// Translators: Number of deleted files.
		pb_backupbuddy::alert( sprintf( _n( 'Deleted %d file.', 'Deleted %d files.', $delete_count, 'it-l10n-backupbuddy' ), $delete_count ), false, '', '', '', array( 'class' => 'below-h2' ) );
	} else {
		pb_backupbuddy::alert( __( 'No backups were deleted.', 'it-l10n-backupbuddy' ), false, '', '', '', array( 'class' => 'below-h2' ) );
	}
	echo '<br>';
}

// Find backups in directory.
backupbuddy_backups()->set_destination_id( $destination_id );
backupbuddy_backups()->show_cleanup();

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
