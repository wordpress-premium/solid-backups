<?php
/* BackupBuddy Stash Live Remote Tables Viewer
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



// Load required files.
require_once( pb_backupbuddy::plugin_path() . '/destinations/live/init.php' );



// Settings.
$destinationID = $destination_id;
if ( isset( pb_backupbuddy::$options['remote_destinations'][$destination_id] ) ) {
	if ( ! isset( pb_backupbuddy::$options['remote_destinations'][ $destinationID ] ) ) {
		die( 'Error #23793442: Destination not found.' );
	}
	$settings = &pb_backupbuddy::$options['remote_destinations'][ $destinationID ];
	$settings = pb_backupbuddy_destination_live::_formatSettings( $settings );
}


$remotePath = 'wp-content/uploads/backupbuddy_temp/SERIAL/';


// Handle deletion.
if ( pb_backupbuddy::_POST( 'bulk_action' ) == 'delete_backup' ) {
	pb_backupbuddy::verify_nonce();
	$deleteFiles = array();
	foreach( (array)pb_backupbuddy::_POST( 'items' ) as $file ) {
		$file = base64_decode( $file );
		
		if ( FALSE !== strstr( $file, '?' ) ) {
			$file = substr( $file, 0, strpos( $file, '?' ) );
		}
		$deleteFiles[] = $file;
	}
	$deleteSettings = $settings;
	$deleteSettings['directory'] = $remotePath;
	$response = pb_backupbuddy_destination_live::deleteFiles( $deleteSettings, $deleteFiles );
	
	if ( true === $response ) {
		pb_backupbuddy::alert( 'Deleted ' . implode( ', ', $deleteFiles ) . '.' );
	} else {
		pb_backupbuddy::alert( 'Failed to delete one or more files. Details: `' . $response . '`.' );
	}
	echo '<br>';
} // end deletion.



// Handle download link
if ( pb_backupbuddy::_GET( 'downloadlink_file' ) != '' ) {
	$downloadSettings = $settings;
	$downloadSettings['directory'] = $remotePath;
	$link = pb_backupbuddy_destination_live::getFileURL( $downloadSettings, base64_decode( pb_backupbuddy::_GET( 'downloadlink_file' ) ) );
	pb_backupbuddy::alert( 'You may download this backup (' . base64_decode( pb_backupbuddy::_GET( 'downloadlink_file' ) ) . ') with <a href="' . $link . '">this link</a>. The link is valid for one hour.' );
	echo '<br>';
} // end download link.



$marker = null;
if ( '' != pb_backupbuddy::_GET( 'marker' ) ) { // Jump to specific spot.
	$marker = base64_decode( urldecode( pb_backupbuddy::_GET( 'marker' ) ) );
}
$files = pb_backupbuddy_destination_live::listFiles( $settings, $remotePath, $marker );
if ( ! is_array( $files ) ) {
	pb_backupbuddy::alert( 'Error #892329b: ' . $files );
	die();
}

/*
echo '<pre>';
print_r( $files );
echo '</pre>';
*/



$backup_list_temp = array();
foreach( (array)$files as $file ) {
	$last_modified = strtotime( $file['LastModified'] );
	while ( isset( $backup_list_temp[ $last_modified ] ) ) {
		$last_modified += 0.1; // Add .1 repeatedly until timestamp is free.
	}
	$backup_list_temp[ $last_modified ] = $file;
}
krsort( $backup_list_temp );

$backup_list = array();
foreach( $backup_list_temp as $file ) {
	$last_modified = strtotime( $file['LastModified'] );
	$size = (double) $file['Size'];
	
	$key = base64_encode( $file['Key'] );
	$backup_list[ $key ] = array(
		array( $key, '<span title="' . $file['Key'] . '">' . $file['Key'] . '</span>' ),
		pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $last_modified ) ) . '<br><span class="description">(' . pb_backupbuddy::$format->time_ago( $last_modified ) . ' ago)</span>',
		pb_backupbuddy::$format->file_size( $size ),
	);
}


$marker = end( $backup_list );
reset( $backup_list );
$marker = $marker[0][0];

$urlPrefix = pb_backupbuddy::nonce_url( pb_backupbuddy::page_url() . '&live_action=view_files_tables' );
?>



<center>
	<b><?php $backup_count = count( $backup_list ); echo $backup_count; ?> files displayed.</b><br><br>
	
	<?php if ( $backup_count >= $settings['max_filelist_keys'] ) { ?>
		<?php if ( '' != pb_backupbuddy::_GET( 'marker' ) ) { ?>
			<a href="<?php echo pb_backupbuddy::nonce_url( pb_backupbuddy::page_url() . '&live_action=view_files_tables&marker=' . urlencode( pb_backupbuddy::_GET( 'back' ) ) ); ?>" class="button button-secondary button-tertiary">Previous Page</a>
		<?php } ?>
		&nbsp;
		<a href="<?php echo pb_backupbuddy::nonce_url( pb_backupbuddy::page_url() . '&live_action=view_files_tables&marker=' . urlencode( $marker ) . '&back=' . urlencode( pb_backupbuddy::_GET( 'marker' ) ) ); ?>" class="button button-secondary button-tertiary">Next Page</a>
	<?php } ?>
</center>



<?php
// Render table listing files.
if ( count( $backup_list ) == 0 ) {
	echo '<center><br><b>';
	_e( 'You have not completed sending anything to this destination for this site yet.', 'it-l10n-backupbuddy' );
	echo '</b></center>';
} else {
	pb_backupbuddy::$ui->list_table(
		$backup_list,
		array(
			'action'		=>	$urlPrefix,
			'columns'		=>	array( 'Backup File <img src="' . pb_backupbuddy::plugin_url() . '/images/sort_down.png" style="vertical-align: 0px;" title="Sorted alphabetically">', 'Uploaded', 'File Size' ),
			'hover_actions'	=>	array( $urlPrefix . '&downloadlink_file=' => 'Get download link' ),
			'hover_action_column_key'	=>	'0',
			'bulk_actions'	=>	array( 'delete_backup' => 'Delete' ),
			'css'			=>		'width: 100%;',
		)
	);
}
?>


<center>
	<b><?php echo count( $backup_list ); ?> files displayed.</b><br><br>
	
	<?php if ( $backup_count >= $settings['max_filelist_keys'] ) { ?>
		<?php if ( '' != pb_backupbuddy::_GET( 'marker' ) ) { ?>
			<a href="<?php echo pb_backupbuddy::page_url(); ?>&live_action=view_files&marker=<?php echo urlencode( pb_backupbuddy::_GET( 'back' ) ); ?>" class="button button-secondary button-tertiary">Previous Page</a>
		<?php } ?>
		&nbsp;
		<a href="<?php echo pb_backupbuddy::nonce_url( pb_backupbuddy::page_url() . '&live_action=view_files_tables&marker=' . urlencode( $marker ) . '&back=' . urlencode( pb_backupbuddy::_GET( 'marker' ) ) ); ?>" class="button button-secondary button-tertiary">Next Page</a>
	<?php } ?>
</center>

<?php
echo '<br style="clear: both;">';
return;
