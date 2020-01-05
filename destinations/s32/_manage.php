<?php
/**
 * Manage Amazon S3v2 Destination
 *
 * Incoming variables:
 *     $destination
 *
 * @author Dustin Bolton 2015.
 * @package BackupBuddy
 */

if ( isset( $destination['disabled'] ) && ( '1' == $destination['disabled'] ) ) {
	die( __( '<span class="description">This destination is currently disabled based on its settings. Re-enable it under its Advanced Settings.</span>', 'it-l10n-backupbuddy' ) );
}
$destination_id = pb_backupbuddy::_GET( 'destination_id' );

// Welcome text.
$site_only = 'true' != pb_backupbuddy::_GET( 'listAll' );
$action    = $site_only ? 'true' : 'false';
$text      = $site_only ? __( 'List all site\'s files', 'it-l10n-backupbuddy' ) : __( 'Only list this site\'s files', 'it-l10n-backupbuddy' );
$swap_list = sprintf( '<a href="%s&destination_id=%s&listAll=%s" style="text-decoration: none;">%s</a>',
	esc_attr( pb_backupbuddy::ajax_url( 'remoteClient' ) ),
	esc_attr( htmlentities( $destination_id ) ),
	esc_attr( $action ),
	esc_html( $text )
);
printf( '<center>%s</center>', wp_kses_post( $swap_list ) );

// Load required files.
require_once pb_backupbuddy::plugin_path() . '/destinations/s32/init.php';

$settings = array();

// Settings.
if ( $destination_id || '0' === $destination_id || 0 === $destination_id ) {
	if ( empty( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) {
		die( 'Error #9828332: Destination not found.' );
	}
	$settings = &pb_backupbuddy::$options['remote_destinations'][ $destination_id ];
	$settings = pb_backupbuddy_destination_s32::_formatSettings( $settings );
}

// Handle deletion.
if ( 'delete_backup' === pb_backupbuddy::_POST( 'bulk_action' ) ) {
	pb_backupbuddy::verify_nonce();
	$delete_files = array();
	foreach ( (array) pb_backupbuddy::_POST( 'items' ) as $item ) {
		$delete_files[] = $item;
	}
	$response = pb_backupbuddy_destination_s32::deleteFiles( $settings, $delete_files );

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
	pb_backupbuddy::status( 'details', 'Scheduling Cron for creating S3 copy.' );
	backupbuddy_core::schedule_single_event( time(), 'process_remote_copy', array( 's32', pb_backupbuddy::_GET( 'cpy' ), $settings ) );

	if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
		update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
		spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
	}
} // end copying to local.

// Handle pagination.
$marker = null;
if ( pb_backupbuddy::_GET( 'marker' ) ) { // Jump to specific spot.
	$marker = base64_decode( urldecode( pb_backupbuddy::_GET( 'marker' ) ) );
}

// Get list of files for this site.
$remote_path = $site_only ? $settings['directory'] . 'backup-' . backupbuddy_core::backup_prefix() : $settings['directory'];
// Find backups in directory.
backupbuddy_backups()->set_destination_id( $destination_id );
$settings['remote_path'] = $remote_path;
$settings['marker']      = $marker;
$backups                 = pb_backupbuddy_destinations::listFiles( $settings );

if ( ! is_array( $backups ) ) {
	die( 'Error listing files: `' . esc_html( $backups ) . '`.' );
}

// Handle pagination.
$marker = end( $backups );
reset( $backups );
$marker = base64_encode( $marker[0][0] );

$url_prefix   = pb_backupbuddy::ajax_url( 'remoteClient' ) . '&destination_id=' . htmlentities( $destination_id );
$backup_count = count( $backups );
?>
<center>
	<b><?php echo esc_html( number_format_i18n( $backup_count ) ); ?> <?php echo esc_html( _n( 'file', 'files', $backup_count, 'it-l10n-backupbuddy' ) ); ?> displayed.</b><br><br>
	<?php if ( pb_backupbuddy::_GET( 'marker' ) ) { ?>
		<a href="<?php echo esc_attr( $url_prefix ); ?>&marker=<?php echo rawurlencode( pb_backupbuddy::_GET( 'back' ) ); ?>" class="button button-secondary button-tertiary">Previous Page</a>
	<?php } ?>
	<?php if ( $backup_count >= $settings['max_filelist_keys'] ) { ?>
		&nbsp;
		<a href="<?php echo esc_attr( $url_prefix ); ?>&marker=<?php echo esc_attr( rawurlencode( $marker ) ); ?>&back=<?php echo esc_attr( rawurlencode( pb_backupbuddy::_GET( 'marker' ) ) ); ?>" class="button button-secondary button-tertiary">Next Page</a>
	<?php } ?>
</center>
<?php
// Render table listing files.
if ( 0 === $backup_count ) {
	echo '<b>';
	if ( $site_only ) { // Only this site.
		esc_html_e( 'You have not completed sending any backups to this S3 v2 destination (bucket + directory) for this site yet.', 'it-l10n-backupbuddy' );
	} else { // All sites.
		esc_html_e( 'You have not completed sending any backups to this S3 v2 destination (bucket + directory).', 'it-l10n-backupbuddy' );
	}
	echo '</b>';
} else {
	pb_backupbuddy::load_script( 'backupbuddy.min.js' );
	pb_backupbuddy::load_style( 'backupbuddy-core.css' );

	backupbuddy_backups()->table( 'default', $backups, array(
		'action'         => $url_prefix . '&remote_path=' . htmlentities( pb_backupbuddy::_GET( 'remote_path' ) ),
		'destination_id' => $destination_id,
		'class'          => 'minimal',
	) );
}

// Display troubleshooting subscriber key.
echo '<br style="clear: both;">';

return;
