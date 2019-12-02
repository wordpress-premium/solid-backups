<?php
/**
 * One Drive File Listing
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
		foreach ( $delete_items as $item ) {
			$response = pb_backupbuddy_destination_onedrive::deleteFile( false, $item );
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

if ( '' !== pb_backupbuddy::_GET( 'download' ) ) {
	// TODO: Use download feature to stream download contents instead of this link.
	$file_id    = pb_backupbuddy::_GET( 'download' );
	$drive_file = pb_backupbuddy_destination_onedrive::get_drive_item( false, $file_id );

	if ( $drive_file ) {
		$permission_proxy = $drive_file->createLink( 'view' );
		pb_backupbuddy::alert( '<a href="' . esc_attr( $permission_proxy->link->webUrl ) . '" target="_new">Click here</a> to view & download this file from OneDrive. You must be logged in to OneDrive to access it.' );
	} else {
		pb_backupbuddy::alert( 'Invalid remote file.', true );
	}
}

if ( '' !== pb_backupbuddy::_GET( 'copy' ) ) {
	$file_id    = pb_backupbuddy::_GET( 'copy' );
	$drive_file = pb_backupbuddy_destination_onedrive::get_drive_item( false, $file_id );

	if ( $drive_file ) {
		pb_backupbuddy::alert( 'The remote file is now being copied to your local backups. If the backup gets marked as bad during copying, please wait a bit then click the `Refresh` icon to rescan after the transfer is complete.' );

		pb_backupbuddy::status( 'details', 'Scheduling Cron for OneDrive file copy to local.' );
		backupbuddy_core::schedule_single_event( time(), 'process_destination_copy', array( $settings, $drive_file->name, $file_id ) );

		if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
			update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
			spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
		}
	} else {
		pb_backupbuddy::alert( 'Invalid remote file.', true );
	}
}

$quota = pb_backupbuddy_destination_onedrive::get_quota();

if ( is_object( $quota ) ) {
	include pb_backupbuddy::plugin_path() . '/destinations/onedrive/views/quota.php';
}

$backup_files = array();
$files        = pb_backupbuddy_destination_onedrive::listFiles();

if ( is_array( $files ) && count( $files ) ) {
	foreach ( $files as $file ) {
		$filename    = $file->name;
		$backup_type = backupbuddy_core::getBackupTypeFromFile( $filename );

		if ( ! $backup_type ) {
			continue;
		}

		$created   = $file->createdDateTime->getTimestamp();
		$file_id   = $file->id;
		$file_size = $file->size;

		$backup_files[ $file_id ] = array(
			array( $file_id, $filename ),
			pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $created ) ) . '<br /><span class="description">(' . pb_backupbuddy::$format->time_ago( $created ) . ' ago)</span>',
			pb_backupbuddy::$format->file_size( $file_size ),
			$backup_type,
		);
	}
}

if ( ! count( $backup_files ) ) {
	esc_html_e( 'No backup files found.', 'it-l10n-backupbuddy' );
} else {
	$url_prefix          = pb_backupbuddy::ajax_url( 'remoteClient' ) . '&destination_id=' . $destination_id;
	$download_url_prefix = admin_url() . sprintf( '?onedrive-destination-id=%s&onedrive-download=', $destination_id );

	pb_backupbuddy::$ui->list_table(
		$backup_files,
		array(
			'action'                  => pb_backupbuddy::ajax_url( 'remoteClient' ) . '&function=remoteClient&destination_id=' . htmlentities( $destination_id ) . '&remote_path=' . htmlentities( pb_backupbuddy::_GET( 'remote_path' ) ),
			'columns'                 => array(
				__( 'Backup File', 'it-l10n-backupbuddy' ),
				__( 'Uploaded', 'it-l10n-backupbuddy' ) . ' <img src="' . pb_backupbuddy::plugin_url() . '/images/sort_down.png" style="vertical-align: 0px;" title="Sorted most recent first">',
				__( 'File Size', 'it-l10n-backupbuddy' ),
				__( 'Type', 'it-l10n-backupbuddy' ),
			),
			'hover_actions'           => array(
				$url_prefix . '&copy=' => __( 'Copy to Local', 'it-l10n-backupbuddy' ),
				$download_url_prefix   => __( 'Download', 'it-l10n-backupbuddy' ),
			),
			'hover_action_column_key' => '0',
			'bulk_actions'            => array(
				'delete_backup' => __( 'Delete', 'it-l10n-backupbuddy' ),
			),
			'css'                     => 'width: 100%;',
		)
	);
}
