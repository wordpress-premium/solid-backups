<?php
/**
 * Dropbox (v3) File Listing
 *
 * Incoming vars:
 *     $destination (destination defaults)
 *
 * @author Brian DiChiara
 * @package BackupBuddy
 */

if ( empty( pb_backupbuddy::$options['remote_destinations'][ pb_backupbuddy::_GET( 'destination_id' ) ] ) ) {
	die( 'Error #844893: Invalid destination ID.' );
}

$destination_id = (int) pb_backupbuddy::_GET( 'destination_id' );
pb_backupbuddy_destination_dropbox3::init( $destination_id );
$settings = pb_backupbuddy_destination_dropbox3::$settings;

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
			$file_str = _n( 'file', 'files', $deleted_files, 'it-l10n-backupbuddy' );
			pb_backupbuddy::alert( 'Deleted ' . $deleted_files . ' ' . $file_str . '.' );
			echo '<br>';
		}
	}
}

if ( '' !== pb_backupbuddy::_GET( 'cpy' ) ) {
	$copy = pb_backupbuddy::_GET( 'cpy' );

	pb_backupbuddy::alert( 'The remote file has been scheduled to be copied down to your local backups. Check your logs for more details.' );

	pb_backupbuddy::status( 'details', 'Scheduling Cron for Dropbox file copy to local.' );
	backupbuddy_core::schedule_single_event( time(), 'process_destination_copy', array( $settings, $copy ) );

	backupbuddy_core::maybe_spawn_cron();
}

$quota = pb_backupbuddy_destination_dropbox3::get_quota();

if ( is_array( $quota ) ) {
	include pb_backupbuddy::plugin_path() . '/destinations/dropbox3/views/quota.php';
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
