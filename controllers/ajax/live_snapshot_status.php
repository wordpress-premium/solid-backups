<?php
/**
 * Live Snapshot Status AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

require_once pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php';

$destination_settings = backupbuddy_live_periodic::get_destination_settings();
$additional_params    = array(
	'snapshot' => pb_backupbuddy::_POST( 'snapshot_id' ),
);

require_once pb_backupbuddy::plugin_path() . '/destinations/live/init.php';
$response = pb_backupbuddy_destination_live::stashAPI( $destination_settings, 'live-snapshot-status', $additional_params );
if ( ! is_array( $response ) ) {
	$error = 'Error #3497943a: Unable to get Live snapshot status. Details: `' . $response . '`.';
	pb_backupbuddy::status( 'error', $error );
	die( $error );
}

pb_backupbuddy::status( 'details', 'Retrieved live snapshot status.' );
if ( '3' == pb_backupbuddy::$options['log_level'] ) { // Full logging enabled.
	pb_backupbuddy::status( 'details', 'live-snapshot-status response due to logging level: `' . print_r( $response, true ) . '`. Call params: `' . print_r( $additional_params, true ) . ' `.' );
}

// If no impoortbuddy password is set then remove importbuddy from response so it is not shown for download.
if ( '' == pb_backupbuddy::$options['importbuddy_pass_hash'] ) {
	unset( $response['snapshot']['importbuddy'] );
}

$response['current_time'] = time();

die( json_encode( $response ) );
