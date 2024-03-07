<?php
if ( isset( $destination['disabled'] ) && ( '1' == $destination['disabled'] ) ) {
	die( __( '<span class="description">This destination is currently disabled based on its settings. Re-enable it under its Advanced Settings.</span>', 'it-l10n-backupbuddy' ) );
}

// Settings.
if ( isset( pb_backupbuddy::$options['remote_destinations'][ pb_backupbuddy::_GET('destination_id') ] ) ) {
	$destinationID = (int) pb_backupbuddy::_GET('destination_id');
	$settings      = (array) pb_backupbuddy::$options['remote_destinations'][ $destinationID ];
} else {
	die( 'Error #844893: Invalid destination ID.' );
}
$urlPrefix = pb_backupbuddy::ajax_url( 'remoteClient' ) . '&destination_id=' . $destinationID;

require_once( pb_backupbuddy::plugin_path() . '/destinations/gdrive/init.php' );
$settings = array_merge( pb_backupbuddy_destination_gdrive::$default_settings, $settings );

$folderID = $settings['folderID'];
if ( '' == $folderID ) {
	$folderID = 'root';
}

// Handle deletion.
if ( pb_backupbuddy::_POST( 'bulk_action' ) == 'delete_backup' ) {
	pb_backupbuddy::verify_nonce();
	$deleted_files = 0;
	foreach( (array)pb_backupbuddy::_POST( 'items' ) as $item ) {
		$response = pb_backupbuddy_destination_gdrive::deleteFile( $settings, $item );
		if ( true === $response ) {
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

// Download link
if ( '' != pb_backupbuddy::_GET( 'downloadlink_file' ) ) {
	$fileMeta = pb_backupbuddy_destination_gdrive::getFileMeta( $settings, pb_backupbuddy::_GET( 'downloadlink_file' ) );
	pb_backupbuddy::alert( '<a href="' . $fileMeta->alternateLink . '" target="_new">Click here</a> to view & download this file from Google Drive. You must log in to Google to access it.' );
}

// Copy file to local
if ( '' != pb_backupbuddy::_GET( 'cpy_file' ) ) {
	$destinationFile =
	$fileMeta = pb_backupbuddy_destination_gdrive::getFileMeta( $settings, pb_backupbuddy::_GET( 'cpy_file' ) );

	pb_backupbuddy::alert( 'The remote file is now being copied to your local backups. If the backup gets marked as bad during copying, please wait a bit then click the `Refresh` icon to rescan after the transfer is complete.' );
	echo '<br>';
	pb_backupbuddy::status( 'details',  'Scheduling Cron for creating Google Drive copy.' );
	backupbuddy_core::schedule_single_event( time(), 'process_destination_copy', array( $destination, $fileMeta->originalFilename, pb_backupbuddy::_GET( 'cpy_file' ) ) );

	backupbuddy_core::maybe_spawn_cron();
}
?>


<span id="backupbuddy_gdrive_loading"><h3><img src="<?php echo pb_backupbuddy::plugin_url(); ?>/images/loading.gif" alt="' . __('Loading...', 'it-l10n-backupbuddy' ) . '" title="' . __('Loading...', 'it-l10n-backupbuddy' ) . '" width="16" height="16" style="vertical-align: -3px;"> <?php _e( 'Loading...', 'it-l10n-backupbuddy' ); ?></h3></span>


<?php
pb_backupbuddy::flush();


$info = pb_backupbuddy_destination_gdrive::getDriveInfo( $settings );

$folderMeta = pb_backupbuddy_destination_gdrive::getFileMeta( $settings, $folderID );
if ( false === $folderMeta ) {
	global $bb_gdrive_error;
	pb_backupbuddy::alert( 'Error connecting to Google Drive. ' . $bb_gdrive_error );
	?>
	<script>jQuery( '#backupbuddy_gdrive_loading' ).hide();</script>
	<?php
	return false;
}

$usagePercent = ceil( ( $info['quotaUsed'] / $info['quotaTotal'] ) * 100 );
echo '<center><b>Usage</b>:&nbsp; ' . pb_backupbuddy::$format->file_size( $info['quotaUsed'] ) . ' &nbsp;of&nbsp; ' . pb_backupbuddy::$format->file_size( $info['quotaTotal'] ) . ' &nbsp;( ' . $usagePercent . ' % )';
if ( '' != $settings['service_account_file'] ) {
	echo ' [Service Account]';
}
echo '</center>';

$files = pb_backupbuddy_destination_gdrive::listFiles( $settings, "title contains 'backup-' AND '" . $folderID . "' IN parents AND trashed=false" ); //"title contains 'backup' and trashed=false" );

if ( false === $files ) {
	die( 'Error #834843: Error attempting to list files.' );
}
?>
<script>jQuery( '#backupbuddy_gdrive_loading' ).hide();</script>
<?php


$backup_files = array();
foreach( $files as $file ) {
	if ( '' == $file->originalFilename ) {
		continue;
	}

	$backup_type = backupbuddy_core::getBackupTypeFromFile( $file->originalFilename );

	if ( ! $backup_type ) {
		continue;
	}

	$created = strtotime( $file->createdDate );

	$backup_files[ $file->id ] = array(
		array( $file->id, $file->originalFilename ),
		pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $created ) ) . '<br /><span class="description">(' . pb_backupbuddy::$format->time_ago( $created ) . ' ago)</span>',
		pb_backupbuddy::$format->file_size( $file->fileSize ),
		$backup_type
	);
}



// Render table listing files.
if ( count( $backup_files ) == 0 ) {
	echo '<b>';
	_e( 'You have not completed sending any backups to Google Drive for this site yet.', 'it-l10n-backupbuddy' );
	echo '</b>';
} else {
	pb_backupbuddy::$ui->list_table(
		$backup_files,
		array(
			'action'		=>	pb_backupbuddy::ajax_url( 'remoteClient' ) . '&function=remoteClient&destination_id=' . htmlentities( pb_backupbuddy::_GET( 'destination_id' ) ) . '&remote_path=' . htmlentities( pb_backupbuddy::_GET( 'remote_path' ) ),
			'columns'		=>	array( 'Backup File', 'Uploaded <img src="' . pb_backupbuddy::plugin_url() . '/images/sort_down.png" style="vertical-align: 0px;" title="Sorted most recent first">', 'File Size', 'Type' ),
			'hover_actions'	=>	array( $urlPrefix . '&downloadlink_file=' => 'Get download link' ), // $urlPrefix . '&cpy_file=' => 'Copy to Local'
			'hover_action_column_key'	=>	'0',
			'bulk_actions'	=>	array( 'delete_backup' => 'Delete' ),
			'css'			=>		'width: 100%;',
		)
	);
}
