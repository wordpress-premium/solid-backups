<?php
/**
 * Stash v2 Destination Manage Page.
 *
 * Incoming variables:
 *     $destination
 *     $destination_id
 *
 * Maybe incoming:
 *     $live_mode
 *     $hide_quota
 *
 * @author Dustin Bolton 2015.
 * @package BackupBuddy
 */

if ( isset( $destination['disabled'] ) && '1' == $destination['disabled'] ) {
	die( __( '<span class="description">This destination is currently disabled based on its settings. Re-enable it under its Advanced Settings.</span>', 'it-l10n-backupbuddy' ) );
}

if ( ! isset( $destination_id ) ) {
	$destination_id = pb_backupbuddy::_GET( 'destination_id' );
}

$remote_path = '';
if ( pb_backupbuddy::_GET( 'remote_path' ) ) {
	$remote_path = pb_backupbuddy::_GET( 'remote_path' );
}

if ( ! isset( $live_mode ) ) {
	$live_mode  = false;
	$url_prefix = pb_backupbuddy::ajax_url( 'remoteClient' ) . '&destination_id=' . $destination_id . '&remote_path=' . $remote_path;
} else {
	$url_prefix = pb_backupbuddy::ajax_url( 'live_stash_files' ); //pb_backupbuddy::page_url() . '&live_action=view_stash_files';
}

if ( ! isset( $hide_quota ) ) {
	$hide_quota = false;
}

// Load required files.
require_once pb_backupbuddy::plugin_path() . '/destinations/s32/init.php';

// Settings.
if ( isset( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) {
	if ( ! isset( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) {
		die( 'Error #9828332: Destination not found with id `' . htmlentities( $destination_id ) . '`.' );
	}
	$destination = &pb_backupbuddy::$options['remote_destinations'][ $destination_id ];
	$destination = pb_backupbuddy_destination_stash2::_formatSettings( $destination );
}

// Handle deletion.
if ( 'delete_backup' === pb_backupbuddy::_POST( 'bulk_action' ) ) {
	pb_backupbuddy::verify_nonce();
	$delete_files = array();
	foreach ( (array) pb_backupbuddy::_POST( 'items' ) as $file ) {
		$file      = $file;
		$start_pos = pb_backupbuddy_destination_stash2::strrpos_count( $file, '/', 2 ) + 1; // next to last slash.
		$file      = substr( $file, $start_pos );
		if ( false !== strstr( $file, '?' ) ) {
			$file = substr( $file, 0, strpos( $file, '?' ) );
		}
		$delete_files[] = $file;
	}
	$response = pb_backupbuddy_destination_stash2::deleteFiles( $destination, $delete_files );

	if ( true === $response ) {
		pb_backupbuddy::alert( 'Deleted ' . implode( ', ', $delete_files ) . '.' );
	} else {
		pb_backupbuddy::alert( 'Failed to delete one or more files. Details: `' . $response . '`.' );
	}
	echo '<br>';
} // end deletion.

// Handle copying files to local.
if ( pb_backupbuddy::_GET( 'cpy' ) ) {
	pb_backupbuddy::alert( 'The remote file is now being copied to your local backups. If the backup gets marked as bad during copying, please wait a bit then click the `Refresh` icon to rescan after the transfer is complete.' );
	echo '<br>';
	pb_backupbuddy::status( 'details', 'Scheduling Cron for creating Stash copy.' );

	$file = pb_backupbuddy::_GET( 'cpy' );
	backupbuddy_core::schedule_single_event( time(), 'process_remote_copy', array( 'stash2', $file, $destination ) );

	if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
		update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
		spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
	}
} // end copying to local.

backupbuddy_backups()->set_destination_id( $destination_id );
$backups = pb_backupbuddy_destination_stash2::listFiles( $destination );
if ( ! is_array( $backups ) ) {
	pb_backupbuddy::alert( 'Error #892329c: ' . $files );
	die();
}

if ( false === $hide_quota ) {
	$quota = pb_backupbuddy_destination_stash2::get_quota( $destination );
	pb_backupbuddy_destination_stash2::get_quota_bar( $quota, true );
}

$backup_count = count( $backups );

// Render table listing files.
if ( 0 === $backup_count ) {
	echo '<br><center><b>';
	if ( 'live' === $destination['type'] ) {
		esc_html_e( 'Your remote BackupBuddy Stash storage does not contain any Snapshot zip files yet. It may take several minutes after a Snapshot for them to display.', 'it-l10n-backupbuddy' );
	} else {
		esc_html_e( 'Your remote BackupBuddy Stash storage does not contain any traditional backup zip files yet.', 'it-l10n-backupbuddy' );
	}
	echo '</b></center>';
} else {
	pb_backupbuddy::load_script( 'backupbuddy.min.js' );
	pb_backupbuddy::load_style( 'backupbuddy-core.css' );

	$table_args = array(
		'action'         => $url_prefix,
		'destination_id' => $destination_id,
		'class'          => 'minimal',
	);

	if ( 'live' === $destination['type'] ) {
		$table_args['bulk_actions'] = array();
	}

	backupbuddy_backups()->table( 'default', $backups, $table_args );
}

// Display troubleshooting subscriber key.
echo '<br style="clear: both;">';

//if ( 'live' == $destination['type'] ) {
/*
?>
<script>
	// Create Base64 Object
	var Base64={_keyStr:"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",encode:function(e){var t="";var n,r,i,s,o,u,a;var f=0;e=Base64._utf8_encode(e);while(f<e.length){n=e.charCodeAt(f++);r=e.charCodeAt(f++);i=e.charCodeAt(f++);s=n>>2;o=(n&3)<<4|r>>4;u=(r&15)<<2|i>>6;a=i&63;if(isNaN(r)){u=a=64}else if(isNaN(i)){a=64}t=t+this._keyStr.charAt(s)+this._keyStr.charAt(o)+this._keyStr.charAt(u)+this._keyStr.charAt(a)}return t},decode:function(e){var t="";var n,r,i;var s,o,u,a;var f=0;e=e.replace(/[^A-Za-z0-9\+\/\=]/g,"");while(f<e.length){s=this._keyStr.indexOf(e.charAt(f++));o=this._keyStr.indexOf(e.charAt(f++));u=this._keyStr.indexOf(e.charAt(f++));a=this._keyStr.indexOf(e.charAt(f++));n=s<<2|o>>4;r=(o&15)<<4|u>>2;i=(u&3)<<6|a;t=t+String.fromCharCode(n);if(u!=64){t=t+String.fromCharCode(r)}if(a!=64){t=t+String.fromCharCode(i)}}t=Base64._utf8_decode(t);return t},_utf8_encode:function(e){e=e.replace(/\r\n/g,"\n");var t="";for(var n=0;n<e.length;n++){var r=e.charCodeAt(n);if(r<128){t+=String.fromCharCode(r)}else if(r>127&&r<2048){t+=String.fromCharCode(r>>6|192);t+=String.fromCharCode(r&63|128)}else{t+=String.fromCharCode(r>>12|224);t+=String.fromCharCode(r>>6&63|128);t+=String.fromCharCode(r&63|128)}}return t},_utf8_decode:function(e){var t="";var n=0;var r=c1=c2=0;while(n<e.length){r=e.charCodeAt(n);if(r<128){t+=String.fromCharCode(r);n++}else if(r>191&&r<224){c2=e.charCodeAt(n+1);t+=String.fromCharCode((r&31)<<6|c2&63);n+=2}else{c2=e.charCodeAt(n+1);c3=e.charCodeAt(n+2);t+=String.fromCharCode((r&15)<<12|(c2&63)<<6|c3&63);n+=3}}return t}}

	jQuery( '.pb_backupbuddy_hoveraction_stash_download_file' ).on( 'click', function(e){
		e.preventDefault();
		var url = Base64.decode( jQuery(this).attr( 'rel' ) );
		jQuery( '#stash_download_iframe' ).src = url;
	});
</script>
<iframe id="stash_download_iframe" style="display: none;"></iframe>
<?php
//}
*/
return;
