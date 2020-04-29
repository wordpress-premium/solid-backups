<?php
/**
 * Manage Local Directory Copy Destination
 *
 * @author Dustin Bolton 2012.
 * @package BackupBuddy
 */

if ( isset( $destination['disabled'] ) && '1' == $destination['disabled'] ) {
	die( __( '<span class="description">This destination is currently disabled based on its settings. Re-enable it under its Advanced Settings.</span>', 'it-l10n-backupbuddy' ) );
}

// Load required files.
require_once pb_backupbuddy::plugin_path() . '/destinations/local/init.php';

$destination_id = pb_backupbuddy::_GET( 'destination_id' );

// Set reference to destination.
if ( isset( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) {
	$destination = &pb_backupbuddy::$options['remote_destinations'][ $destination_id ];
	$destination = pb_backupbuddy_destination_local::_formatSettings( $destination );
} else {
	pb_backupbuddy::alert( 'Error: Invalid Destination `' . $destination_id . '`.', true, '', '', '', array( 'class' => 'below-h2' ) );
	return;
}

// Handle Copy.
if ( pb_backupbuddy::_GET( 'cpy' ) ) {
	$copy = pb_backupbuddy::_GET( 'cpy' );
	pb_backupbuddy::status( 'details', 'Scheduling Cron for creating Local copy.' );
	backupbuddy_core::schedule_single_event( time(), 'process_destination_copy', array( $destination, $copy ) );

	if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
		update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
		spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
	}
}

// Handle deletion.
if ( 'delete_backup' == pb_backupbuddy::_POST( 'bulk_action' ) ) {
	pb_backupbuddy::verify_nonce();
	$deleted_files = array();
	foreach ( (array) pb_backupbuddy::_POST( 'items' ) as $item ) {
		if ( ! pb_backupbuddy_destination_local::delete( $destination, $item ) ) {
			pb_backupbuddy::alert( 'Error: Unable to delete `' . $item . '`. Verify permissions.', true, '', '', 'margin:0 0 15px;' );
		} else {
			$deleted_files[] = $item;
		}
	}

	if ( count( $deleted_files ) > 0 ) {
		pb_backupbuddy::alert( 'Deleted ' . implode( ', ', $deleted_files ) . '.', false, '', '', 'margin:0 0 15px;' );
	}
}

add_filter( 'backupbuddy_backup_columns', 'backupbuddy_local_backup_columns' );

/**
 * Alter the first column heading for local destination.
 *
 * @param array $columns  Array of table columns.
 *
 * @return array  Modified columns.
 */
function backupbuddy_local_backup_columns( $columns ) {
	$destination_id = pb_backupbuddy::_GET( 'destination_id' );
	$destination    = array();

	// Set reference to destination.
	if ( isset( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) {
		$destination = &pb_backupbuddy::$options['remote_destinations'][ $destination_id ];
		$destination = pb_backupbuddy_destination_local::_formatSettings( $destination );
	}

	if ( empty( $destination['path'] ) ) {
		return $columns;
	}

	$columns[0] .= ' <span class="description">' . esc_html__( 'in local directory', 'it-l10n-backupbuddy' ) . '`' . $destination['path'] . '`</span>';

	return $columns;
}

// Find backups in directory.
backupbuddy_backups()->set_destination_id( $destination_id );

$backups = pb_backupbuddy_destinations::listFiles( $destination );

backupbuddy_backups()->table(
	'default',
	$backups,
	array(
		'action'         => pb_backupbuddy::ajax_url( 'remoteClient' ) . '&destination_id=' . htmlentities( $destination_id ),
		'destination_id' => $destination_id,
		'class'          => 'minimal',
	)
);
