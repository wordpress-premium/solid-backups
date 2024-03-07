<?php
/**
 * OneDrive File Listing
 *
 * Incoming vars:
 *     $destination (destination defaults)
 *
 * @author Brian DiChiara
 * @package BackupBuddy
 */

if ( isset( pb_backupbuddy::$options['remote_destinations'][ pb_backupbuddy::_GET( 'destination_id' ) ] ) ) {
	$destination_id = (int) pb_backupbuddy::_GET( 'destination_id' );
	$settings       = pb_backupbuddy::$options['remote_destinations'][ $destination_id ];
	pb_backupbuddy_destination_onedrive::add_settings( $settings );
} else {
	die( 'Error #844893: Invalid destination ID.' );
}

if ( isset( $settings['disabled'] ) && '1' === $settings['disabled'] ) {
	die( esc_html__( '<span class="description">This destination is currently disabled based on its settings. Re-enable it under its Advanced Settings.</span>', 'it-l10n-backupbuddy' ) );
}

// Handle remote file deletion.
if ( 'delete_backup' === pb_backupbuddy::_POST( 'bulk_action' ) ) {
	pb_backupbuddy::verify_nonce();
	$deleted_files = 0;
	$delete_items  = (array) pb_backupbuddy::_POST( 'items' );

	if ( ! empty( $delete_items ) ) {

		if ( ! class_exists( 'pb_backupbuddy_destinations' ) ) {
			require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
		}

		foreach ( $delete_items as $item ) {
			if ( true === pb_backupbuddy_destinations::delete( $settings, $item ) ) {
				$deleted_files++;
			} else {
				pb_backupbuddy::alert( 'Error: Unable to delete `' . esc_attr( $item ) . '`. Verify permissions or try again.' );
			}
		}

		if ( $deleted_files > 0 ) {
			pb_backupbuddy::alert( 'Deleted ' . $deleted_files . ' file(s).' );
			echo '<br>';
		}
	}
}

if ( '' !== pb_backupbuddy::_GET( 'cpy' ) ) {
	$copy       = pb_backupbuddy::_GET( 'cpy' );
	$drive_file = pb_backupbuddy_destination_onedrive::get_drive_item( false, $copy );

	if ( $drive_file ) {
		pb_backupbuddy::alert( 'The remote file is now being copied to your local backups. If the transfer gets interrupted, click the "Refresh" icon after the transfer is complete to try again.' );

		pb_backupbuddy::status( 'details', 'Scheduling Cron for OneDrive file copy to local.' );
		backupbuddy_core::schedule_single_event( time(), 'process_destination_copy', array( $settings, $drive_file->name, $copy ) );

		backupbuddy_core::maybe_spawn_cron();
	} else {
		pb_backupbuddy::alert( 'Invalid remote file.', true );
	}
}

$quota = pb_backupbuddy_destination_onedrive::get_quota();

if ( is_object( $quota ) ) {
	include pb_backupbuddy::plugin_path() . '/destinations/onedrive/views/quota.php';
}

// Find backups in directory.
backupbuddy_backups()->set_destination_id( $destination_id );
backupbuddy_backups()->show_cleanup();

$backups = pb_backupbuddy_destinations::listFiles( $settings );

backupbuddy_backups()->table(
	'default',
	$backups,
	array(
		'action'         => pb_backupbuddy::ajax_url( 'remoteClient' ) . '&destination_id=' . htmlentities( $destination_id ),
		'destination_id' => $destination_id,
		'class'          => 'minimal',
	)
);
