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

if ( isset( pb_backupbuddy::$options['remote_destinations'][ pb_backupbuddy::_GET( 'destination_id' ) ] ) ) {
	$destination_id = (int) pb_backupbuddy::_GET( 'destination_id' );
	pb_backupbuddy_destination_dropbox3::init( $destination_id );
	$settings = pb_backupbuddy_destination_dropbox3::$settings;
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
		foreach ( $delete_items as $item ) {
			$response = pb_backupbuddy_destinations::delete( $settings, $item );
			if ( true === $response ) {
				$deleted_files++;
			} else {
				pb_backupbuddy::alert( 'Error: Unable to delete `' . $item . '`. Verify permissions or try again.' );
			}
		}

		if ( $deleted_files > 0 ) {
			pb_backupbuddy::alert( 'Deleted ' . $deleted_files . ' file(s).' );
			echo '<br>';
		}
	}
}

if ( '' !== pb_backupbuddy::_GET( 'cpy' ) ) {
	$copy = pb_backupbuddy::_GET( 'cpy' );

	pb_backupbuddy::alert( 'The remote file has been scheduled to be copied down to your local backups. Check your logs for more details.' );

	pb_backupbuddy::status( 'details', 'Scheduling Cron for Dropbox file copy to local.' );
	backupbuddy_core::schedule_single_event( time(), 'process_destination_copy', array( $settings, $copy ) );

	if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
		update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
		spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
	}
}

$quota = pb_backupbuddy_destination_dropbox3::get_quota();

if ( is_array( $quota ) ) {
	include pb_backupbuddy::plugin_path() . '/destinations/dropbox3/views/quota.php';
}

// Find backups in directory.
backupbuddy_backups()->set_destination_id( $destination_id );

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
