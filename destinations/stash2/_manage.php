<?php
// @author Dustin Bolton 2015.
// Incoming variables: $destination, $destination_id
// Maybe incoming: $live_mode, $hide_quota

if ( isset( $destination['disabled'] ) && ( '1' == $destination['disabled'] ) ) {
	die( __( '<span class="description">This destination is currently disabled based on its settings. Re-enable it under its Advanced Settings.</span>', 'it-l10n-backupbuddy' ) );
}

if ( ! isset( $destination_id ) ) {
	$destination_id = pb_backupbuddy::_GET('destination_id');
}

$remote_path = '';
if ( '' != pb_backupbuddy::_GET( 'remote_path' ) ) {
	$remote_path = pb_backupbuddy::_GET( 'remote_path' );
}


if ( ! isset( $live_mode ) ) {
	$live_mode = false;
	$url_prefix = pb_backupbuddy::ajax_url( 'remoteClient' ) . '&destination_id=' . $destination_id . '&remote_path=' . $remote_path;
} else {
	$url_prefix = pb_backupbuddy::ajax_url( 'live_stash_files' ); //pb_backupbuddy::page_url() . '&live_action=view_stash_files';
}

if ( ! isset( $hide_quota ) ) {
	$hide_quota = false;
}

?>


<script type="text/javascript">
	jQuery(document).ready(function() {

		jQuery( '.pb_backupbuddy_hoveraction_copy' ).click( function() {
			var backup_file = jQuery(this).attr( 'rel' );
			var backup_url = '<?php echo $url_prefix; ?>&cpy_file=' + backup_file;

			window.location.href = backup_url;

			return false;
		} );

		jQuery( '.pb_backupbuddy_hoveraction_download_link' ).click( function() {
			var backup_file = jQuery(this).attr( 'rel' );
			var backup_url = '<?php echo $url_prefix; ?>&downloadlink_file=' + backup_file;

			window.location.href = backup_url;

			return false;
		} );

	});
</script>


<?php
// Load required files.
require_once( pb_backupbuddy::plugin_path() . '/destinations/s32/init.php' );


// Settings.
if ( isset( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) {
	if ( ! isset( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) {
		die( 'Error #9828332: Destination not found with id `' . htmlentities( $destination_id ) . '`.' );
	}
	$settings = &pb_backupbuddy::$options['remote_destinations'][ $destination_id ];
	$settings = pb_backupbuddy_destination_stash2::_formatSettings( $settings );
}


// Handle deletion.
if ( pb_backupbuddy::_POST( 'bulk_action' ) == 'delete_backup' ) {
	pb_backupbuddy::verify_nonce();
	$deleteFiles = array();
	foreach( (array)pb_backupbuddy::_POST( 'items' ) as $file ) {
		$file = base64_decode( $file );

		$startPos = pb_backupbuddy_destination_stash2::strrpos_count( $file, '/', 2 ) + 1; // next to last slash.
		$file = substr( $file, $startPos );
		if ( FALSE !== strstr( $file, '?' ) ) {
			$file = substr( $file, 0, strpos( $file, '?' ) );
		}
		$deleteFiles[] = $file;
	}
	$response = pb_backupbuddy_destination_stash2::deleteFiles( $settings, $deleteFiles );

	if ( true === $response ) {
		pb_backupbuddy::alert( 'Deleted ' . implode( ', ', $deleteFiles ) . '.' );
	} else {
		pb_backupbuddy::alert( 'Failed to delete one or more files. Details: `' . $response . '`.' );
	}
	echo '<br>';
} // end deletion.


// Handle copying files to local
if ( pb_backupbuddy::_GET( 'cpy_file' ) != '' ) {
	pb_backupbuddy::alert( 'The remote file is now being copied to your local backups. If the backup gets marked as bad during copying, please wait a bit then click the `Refresh` icon to rescan after the transfer is complete.' );
	echo '<br>';
	pb_backupbuddy::status( 'details',  'Scheduling Cron for creating Stash copy.' );

	$file = base64_decode( pb_backupbuddy::_GET( 'cpy_file' ) );
	backupbuddy_core::schedule_single_event( time(), 'process_remote_copy', array( 'stash2', $file, $settings ) );

	if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
		update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
		spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
	}
} // end copying to local.


// Handle download link
if ( pb_backupbuddy::_GET( 'downloadlink_file' ) != '' ) {
	pb_backupbuddy::alert( 'Download the selected backup file with <a href="' . esc_url( base64_decode( pb_backupbuddy::_GET( 'downloadlink_file' ) ) ) . '">this link</a>. The link is valid for one hour.' );
	echo '<br>';
} // end download link.


if ( 'live' == $destination['type'] ) {
	$remotePath = 'snapshot-';// . backupbuddy_core::backup_prefix();
	$site_only = true;
} else {
	// Get list of files for this site.
	$remotePath = 'backup-';// . backupbuddy_core::backup_prefix();
	$site_only = true;
}
$files = pb_backupbuddy_destination_stash2::listFiles( $settings, '', $site_only ); //2nd param was $remotePath.
if ( ! is_array( $files ) ) {
	pb_backupbuddy::alert( 'Error #892329c: ' . $files );
	die();
}

//echo 'FILES:<pre>' . print_r( $files, true ) . '</pre>';
$backup_list_temp = array();
foreach( (array)$files as $file ) {
	/*
	echo '<br><pre>';
	print_r( $file );
	echo '</pre>';
	*/

	/*
	if ( ( ! preg_match( pb_backupbuddy_destination_s32::BACKUP_FILENAME_PATTERN, $file['basename'] ) ) && ( 'importbuddy.php' !== $file ) ) { // Do not display any files that do not appear to be a BackupBuddy backup file (except importbuddy.php).
		continue;
	}
	*/

	if ( ( '' != $remotePath ) && ( ! backupbuddy_core::startsWith( basename( $file['filename'] ), $remotePath ) ) ) { // Only show backups for this site unless set to show all.
		continue;
	}

	$backup_type = backupbuddy_core::getBackupTypeFromFile( $file['filename'], false, true );

	if ( ! $backup_type ) {
		continue;
	}

	$last_modified = $file['uploaded_timestamp'];
	$size = (double) $file['size'];

	// Generate array of table rows.
	while( isset( $backup_list_temp[$last_modified] ) ) { // Avoid collisions.
		$last_modified += 0.1;
	}

	if ( 'live' == $destination['type'] ) {
		$backup_list_temp[$last_modified] = array(
			array( base64_encode( $file['url'] ), '<span class="backupbuddy-stash-file-list-title">' . pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $last_modified ) ) . ' <span class="description">(' . pb_backupbuddy::$format->time_ago( $last_modified ) . ' ago)</span></span><br><span title="' . $file['filename'] . '">' . basename( $file['filename'] ) . '</span>' ),
			pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $last_modified ) ) . '<br /><span class="description">(' . pb_backupbuddy::$format->time_ago( $last_modified ) . ' ago)</span>',
			pb_backupbuddy::$format->file_size( $size ),
			backupbuddy_core::pretty_backup_type( $backup_type ),
		);
	} else {
		$backup_list_temp[$last_modified] = array(
			array( base64_encode( $file['url'] ), '<span title="' . $file['filename'] . '">' . basename( $file['filename'] ) . '</span>' ),
			pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $last_modified ) ) . '<br /><span class="description">(' . pb_backupbuddy::$format->time_ago( $last_modified ) . ' ago)</span>',
			pb_backupbuddy::$format->file_size( $size ),
			backupbuddy_core::pretty_backup_type( $backup_type ),
		);
	}

}


krsort( $backup_list_temp );
$backup_list = array();
foreach( $backup_list_temp as $backup_item ) {
	$backup_list[ $backup_item[0][0] ] = $backup_item;
}
unset( $backup_list_temp );
$urlPrefix = $url_prefix;


if ( false === $hide_quota ) {
	$quota = pb_backupbuddy_destination_stash2::get_quota( $settings );
	echo pb_backupbuddy_destination_stash2::get_quota_bar( $quota, $settings, true );
}


$hover_actions = array( $url_prefix . '&cpy_file=' => '<span class="dashicons dashicons-migrate"></span> Copy to Local', 'stash_download_file' => '<span class="dashicons dashicons-download"></span> Download' );
/*
if ( 'live' == $destination['type'] ) {
	$hover_actions = array( $url_prefix . '&cpy_file=' => '<span class="dashicons dashicons-migrate"></span> Copy to Local', 'live_download_file' => '<span class="dashicons dashicons-download"></span> Download' );
} else { // Stash
	$hover_actions = array( $url_prefix . '&cpy_file=' => '<span class="dashicons dashicons-migrate"></span> Copy to Local', $url_prefix . '&downloadlink_file=' => '<span class="dashicons dashicons-download"></span> Get download link' );
}
*/

if ( 'live' == $destination['type'] ) {
	$backup_title = __( 'Snapshots Stored Remotely on Stash Live Servers', 'it-l10n-backupbuddy' );
} else {
	$backup_title = __( 'Stash Traditional Backup Files', 'it-l10n-backupbuddy' );
}

// Render table listing files.
if ( count( $backup_list ) == 0 ) {
	echo '<br><b>';
	if ( 'live' == $destination['type'] ) {
		_e( 'Your remote BackupBuddy Stash storage does not contain any Snapshot zip files yet. It may take several minutes after a Snapshot for them to display.', 'it-l10n-backupbuddy' );
	} else {
		_e( 'Your remote BackupBuddy Stash storage does not contain any traditional backup zip files yet.', 'it-l10n-backupbuddy' );
	}
	echo '</b></center>';
} else {
	$tableArgs = array(
		'action'		=>	$url_prefix,
		'columns'		=>	array( '<b>' . $backup_title . '</b>', 'Uploaded <img src="' . pb_backupbuddy::plugin_url() . '/images/sort_down.png" style="vertical-align: 0px;" title="Sorted most recent first">', 'File Size', 'Type' ),
		'hover_actions'	=>	$hover_actions,
		'hover_action_column_key'	=>	'0',
		'bulk_actions'	=>	array( 'delete_backup' => __( 'Delete', 'it-l10n-backupbuddy' ) ),
		'css'			=>		'width: 100%;',
	);
	if ( 'live' == $destination['type'] ) {
		$tableArgs['bulk_actions'] = array();
	}
	pb_backupbuddy::$ui->list_table( $backup_list, $tableArgs );
}

// Display troubleshooting subscriber key.
echo '<br style="clear: both;">';


//if ( 'live' == $destination['type'] ) {
?>
<script>
	// Create Base64 Object
	var Base64={_keyStr:"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",encode:function(e){var t="";var n,r,i,s,o,u,a;var f=0;e=Base64._utf8_encode(e);while(f<e.length){n=e.charCodeAt(f++);r=e.charCodeAt(f++);i=e.charCodeAt(f++);s=n>>2;o=(n&3)<<4|r>>4;u=(r&15)<<2|i>>6;a=i&63;if(isNaN(r)){u=a=64}else if(isNaN(i)){a=64}t=t+this._keyStr.charAt(s)+this._keyStr.charAt(o)+this._keyStr.charAt(u)+this._keyStr.charAt(a)}return t},decode:function(e){var t="";var n,r,i;var s,o,u,a;var f=0;e=e.replace(/[^A-Za-z0-9\+\/\=]/g,"");while(f<e.length){s=this._keyStr.indexOf(e.charAt(f++));o=this._keyStr.indexOf(e.charAt(f++));u=this._keyStr.indexOf(e.charAt(f++));a=this._keyStr.indexOf(e.charAt(f++));n=s<<2|o>>4;r=(o&15)<<4|u>>2;i=(u&3)<<6|a;t=t+String.fromCharCode(n);if(u!=64){t=t+String.fromCharCode(r)}if(a!=64){t=t+String.fromCharCode(i)}}t=Base64._utf8_decode(t);return t},_utf8_encode:function(e){e=e.replace(/\r\n/g,"\n");var t="";for(var n=0;n<e.length;n++){var r=e.charCodeAt(n);if(r<128){t+=String.fromCharCode(r)}else if(r>127&&r<2048){t+=String.fromCharCode(r>>6|192);t+=String.fromCharCode(r&63|128)}else{t+=String.fromCharCode(r>>12|224);t+=String.fromCharCode(r>>6&63|128);t+=String.fromCharCode(r&63|128)}}return t},_utf8_decode:function(e){var t="";var n=0;var r=c1=c2=0;while(n<e.length){r=e.charCodeAt(n);if(r<128){t+=String.fromCharCode(r);n++}else if(r>191&&r<224){c2=e.charCodeAt(n+1);t+=String.fromCharCode((r&31)<<6|c2&63);n+=2}else{c2=e.charCodeAt(n+1);c3=e.charCodeAt(n+2);t+=String.fromCharCode((r&15)<<12|(c2&63)<<6|c3&63);n+=3}}return t}}

	jQuery( '.pb_backupbuddy_hoveraction_stash_download_file' ).click( function(e){
		e.preventDefault();
		url = Base64.decode( jQuery(this).attr( 'rel' ) );
		//console.dir( decoded );
		document.getElementById( 'stash_download_iframe' ).src = url;
	});
</script>
<iframe id="stash_download_iframe" style="display: none;"></iframe>
<?php
//}
return;
