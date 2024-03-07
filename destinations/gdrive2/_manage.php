<?php
/**
 * Manage Google Drive (v2) Files
 *
 * Incoming vars:
 *   array $destination  Destination settings array.
 *
 * Available GET vars:
 *   int destination_id  Destination ID.
 *
 * @package BackupBuddy
 */

if ( isset( $destination['disabled'] ) && ( '1' == $destination['disabled'] ) ) {
	die( __( '<span class="description">This destination is currently disabled based on its settings. Re-enable it under its Advanced Settings.</span>', 'it-l10n-backupbuddy' ) );
}

// Settings.
if ( isset( pb_backupbuddy::$options['remote_destinations'][ pb_backupbuddy::_GET( 'destination_id' ) ] ) ) {
	$destination_id = (int) pb_backupbuddy::_GET( 'destination_id' );
	$settings       = (array) pb_backupbuddy::$options['remote_destinations'][ $destination_id ];
} else {
	die( 'Error #844893: Invalid destination ID.' );
}

require_once pb_backupbuddy::plugin_path() . '/destinations/gdrive2/init.php';
$settings = array_merge( pb_backupbuddy_destination_gdrive2::$default_settings, $settings );
pb_backupbuddy_destination_gdrive2::add_settings( $settings );

$folder_id = pb_backupbuddy_destination_gdrive2::get_root_folder();

// Handle deletion.
if ( 'delete_backup' === pb_backupbuddy::_POST( 'bulk_action' ) ) {
	pb_backupbuddy::verify_nonce();
	$deleted_files = 0;

	if ( ! class_exists( 'pb_backupbuddy_destinations' ) ) {
		require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
	}

	foreach ( (array) pb_backupbuddy::_POST( 'items' ) as $item ) {
		if ( true === pb_backupbuddy_destinations::delete( $settings, $item ) ) {
			$deleted_files++;
		} else {
			pb_backupbuddy::alert( 'Error: Unable to delete `' . esc_attr( $item ) . '`. Verify permissions or try again.' );
		}
	}

	if ( $deleted_files > 0 ) {
		pb_backupbuddy::alert( 'Deleted ' . $deleted_files . ' file(s).' );
	}
	echo '<br>';
}

// Copy file to local.
if ( '' != pb_backupbuddy::_GET( 'cpy' ) ) {
	$file_id = pb_backupbuddy::_GET( 'cpy' );
	$copy    = pb_backupbuddy_destination_gdrive2::get_file_meta( false, $file_id );

	if ( ! $copy ) {
		pb_backupbuddy::alert( __( 'Unable to copy the requested file. File not found.', 'it-l10n-backupbuddy' ), true );
	} else {
		pb_backupbuddy::alert( 'The remote file has been scheduled to be copied down to your local backups. Check your logs for more details.' );
		echo '<br>';
		pb_backupbuddy::status( 'details', 'Scheduling Cron for creating Google Drive (v2) copy.' );
		backupbuddy_core::schedule_single_event( time(), 'process_destination_copy', array( pb_backupbuddy_destination_gdrive2::$settings, $copy->getName(), $file_id ) );

		backupbuddy_core::maybe_spawn_cron();
	}
}

pb_backupbuddy::flush();

$info = pb_backupbuddy_destination_gdrive2::getDriveInfo();

if ( ! $info ) {
	global $bb_gdrive_error;
	pb_backupbuddy::alert( 'Error connecting to Google Drive. ' . $bb_gdrive_error, true );
	return;
}

require pb_backupbuddy::plugin_path() . '/destinations/gdrive2/views/quota.php';

backupbuddy_backups()->set_destination_id( $destination_id );
backupbuddy_backups()->show_cleanup();

$backups = pb_backupbuddy_destination_gdrive2::listFiles();

if ( false === $backups ) {
	die( 'Error #834843: Error attempting to list files.' );
}

backupbuddy_backups()->table(
	'default',
	$backups,
	array(
		'action'         => pb_backupbuddy::ajax_url( 'remoteClient' ) . '&destination_id=' . htmlentities( $destination_id ),
		'destination_id' => $destination_id,
		'class'          => 'minimal',
	)
);
