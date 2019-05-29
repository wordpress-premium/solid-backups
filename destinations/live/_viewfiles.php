<?php
/* BackupBuddy Stash Live Remote Files Viewer
 *
 * @author Dustin Bolton
 * @since 7.0
 *
 */

pb_backupbuddy::verify_nonce();

// @author Dustin Bolton 2015.
// Incoming variables: $destination, $destination_id
if ( isset( $destination['disabled'] ) && ( '1' == $destination['disabled'] ) ) {
	die( __( '<span class="description">This destination is currently disabled based on its settings. Re-enable it under its Advanced Settings.</span>', 'it-l10n-backupbuddy' ) );
}
?>



<script type="text/javascript">
	jQuery(document).ready(function() {
		
		jQuery( '.pb_backupbuddy_hoveraction_copy' ).click( function() {
			var backup_file = jQuery(this).attr( 'rel' );
			var backup_url = '<?php echo pb_backupbuddy::page_url(); ?>&live_action=view_files&cpy_file=' + backup_file;
			
			window.location.href = backup_url;
			
			return false;
		} );
		
		jQuery( '.pb_backupbuddy_hoveraction_download_link' ).click( function() {
			var backup_file = jQuery(this).attr( 'rel' );
			var backup_url = '<?php echo pb_backupbuddy::page_url(); ?>&live_action=view_files&downloadlink_file=' + backup_file;
			
			window.location.href = backup_url;
			
			return false;
		} );
		
	});
</script>



<?php
// Load required files.
require_once( pb_backupbuddy::plugin_path() . '/destinations/live/init.php' );



// Settings.
$destinationID = $destination_id;
if ( isset( pb_backupbuddy::$options['remote_destinations'][$destination_id] ) ) {
	if ( ! isset( pb_backupbuddy::$options['remote_destinations'][ $destinationID ] ) ) {
		die( 'Error #957484: Destination not found.' );
	}
	$settings = &pb_backupbuddy::$options['remote_destinations'][ $destinationID ];
	$settings = pb_backupbuddy_destination_live::_formatSettings( $settings );
}



// Handle deletion.
if ( pb_backupbuddy::_POST( 'bulk_action' ) == 'delete_backup' ) {
	pb_backupbuddy::verify_nonce();
	$deleteFiles = array();
	print_r( pb_backupbuddy::_POST( 'items' ) );
	foreach( (array)pb_backupbuddy::_POST( 'items' ) as $file ) {
		$file = base64_decode( $file );
		
		if ( FALSE !== strstr( $file, '?' ) ) {
			$file = substr( $file, 0, strpos( $file, '?' ) );
		}
		$deleteFiles[] = $file;
	}
	$response = pb_backupbuddy_destination_live::deleteFiles( $settings, $deleteFiles );
	
	if ( true === $response ) {
		pb_backupbuddy::alert( 'Deleted ' . implode( ', ', $deleteFiles ) . '.' );
	} else {
		pb_backupbuddy::alert( 'Failed to delete one or more files. Details: `' . $response . '`.' );
	}
	echo '<br>';
} // end deletion.



// Handle copying files to local
/*
if ( pb_backupbuddy::_GET( 'cpy_file' ) != '' ) {
	pb_backupbuddy::alert( 'The remote file is now being copied to your server.' );
	echo '<br>';
	pb_backupbuddy::status( 'details',  'Scheduling Cron for creating Stash copy.' );
	
	$file = base64_decode( pb_backupbuddy::_GET( 'cpy_file' ) );
	backupbuddy_core::schedule_single_event( time(), 'process_remote_copy', array( 'live', $file, $settings ) );
	
	if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
		update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
		spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
	}
} // end copying to local.
*/


// Handle download link
if ( pb_backupbuddy::_GET( 'downloadlink_file' ) != '' ) {
	
	
	$link = pb_backupbuddy_destination_live::getFileURL( $settings, base64_decode( pb_backupbuddy::_GET( 'downloadlink_file' ) ) );
	pb_backupbuddy::alert( 'You may download this backup (' . pb_backupbuddy::_GET( 'downloadlink_file' ) . ') with <a href="' . $link . '">this link</a>. The link is valid for one hour.' );
	echo '<br>';
} // end download link.



$marker = null;
if ( '' != pb_backupbuddy::_GET( 'marker' ) ) { // Jump to specific spot.
	$marker = base64_decode( urldecode( pb_backupbuddy::_GET( 'marker' ) ) );
}
$files = pb_backupbuddy_destination_live::listFiles( $settings, $remotePath = '', $marker );
if ( ! is_array( $files ) ) {
	pb_backupbuddy::alert( 'Error #892329a: ' . $files );
	die();
}
/*
echo 'Files (count: ' . count( $files ) . '):<pre>';
print_r( $files );
echo '</pre>';
*/


$backup_list_temp = array();
foreach( (array)$files as $file ) {
	$last_modified = strtotime( $file['LastModified'] );
	$size = (double) $file['Size'];
	
	$key = base64_encode( $file['Key'] );
	$backup_list[ $key ] = array(
		array( $key, '<span title="' . $file['Key'] . '">/' . $file['Key'] . '</span>' ),
		pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $last_modified ) ) . '<br><span class="description">(' . pb_backupbuddy::$format->time_ago( $last_modified ) . ' ago)</span>',
		pb_backupbuddy::$format->file_size( $size ),
	);
}


$marker = end( $backup_list );
reset( $backup_list );
$marker = $marker[0][0];

$urlPrefix = pb_backupbuddy::page_url() . '&live_action=view_files';
?>



<center>
	<b><?php $backup_count = count( $backup_list ); echo $backup_count; ?> files displayed.</b><br><br>
	
	<?php if ( $backup_count >= $settings['max_filelist_keys'] ) { ?>
		<?php if ( '' != pb_backupbuddy::_GET( 'marker' ) ) { ?>
			<a href="<?php echo pb_backupbuddy::nonce_url( pb_backupbuddy::page_url() . '&live_action=view_files&marker=' . urlencode( pb_backupbuddy::_GET( 'back' ) ) ); ?>" class="button button-secondary button-tertiary">Previous Page</a>
		<?php } ?>
		&nbsp;
		<a href="<?php echo pb_backupbuddy::nonce_url( pb_backupbuddy::page_url() . '&live_action=view_files&marker=' . urlencode( $marker ) . '&back=' . urlencode( pb_backupbuddy::_GET( 'marker' ) ) ); ?>" class="button button-secondary button-tertiary">Next Page</a>
	<?php } ?>
</center>



<?php
/*echo '<pre>';
print_r( $backup_list );
echo '</pre>';
*/

// Render table listing files.
if ( count( $backup_list ) == 0 ) {
	echo '<center><br><b>';
	_e( 'You have not completed sending anything to this destination for this site yet.', 'it-l10n-backupbuddy' );
	echo '</b></center>';
} else {
	pb_backupbuddy::$ui->list_table(
		$backup_list,
		array(
			'action'		=>	pb_backupbuddy::page_url() . '&live_action=view_files',
			'columns'		=>	array( 'Backup File <img src="' . pb_backupbuddy::plugin_url() . '/images/sort_down.png" style="vertical-align: 0px;" title="Sorted alphabetically">', 'Uploaded', 'File Size' ),
			'hover_actions'	=>	array( pb_backupbuddy::nonce_url( $urlPrefix ) . '&downloadlink_file=' => 'Get download link' ), // pb_backupbuddy::nonce_url( $urlPrefix ) . '&cpy_file=' => 'Copy to Local'
			'hover_action_column_key'	=>	'0',
			'bulk_actions'	=>	array( 'delete_backup' => 'Delete' ),
			'css'			=>		'width: 100%;',
		)
	);
}
?>


<center>
	<b><?php echo count( $backup_list ); ?> files displayed.</b><br><br>
	
	<?php if ( '' != pb_backupbuddy::_GET( 'marker' ) ) { ?>
		<a href="<?php echo pb_backupbuddy::nonce_url( pb_backupbuddy::page_url() . '&live_action=view_files&marker=' . urlencode( pb_backupbuddy::_GET( 'back' ) ) ); ?>" class="button button-secondary button-tertiary">Previous Page</a>
	<?php } ?>
	&nbsp;
	<a href="<?php echo pb_backupbuddy::nonce_url( pb_backupbuddy::page_url() . '&live_action=view_files&marker=' . urlencode( $marker ) . '&back=' . urlencode( pb_backupbuddy::_GET( 'marker' ) ) ); ?>" class="button button-secondary button-tertiary">Next Page</a>
</center>

<?php
echo '<br style="clear: both;">';
return;
