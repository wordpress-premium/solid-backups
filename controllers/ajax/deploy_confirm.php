<?php
/**
 * Deploy Confirm AJAX Controller
 *
 * Note: importbuddy, backup files, etc should have already been cleaned up by importbuddy itself at this point.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$serial    = pb_backupbuddy::_POST( 'serial' );
$direction = pb_backupbuddy::_POST( 'direction' );

pb_backupbuddy::load();

if ( 'pull' == $direction ) { // Local so clean up here.

	// Remove Temp Tables.
	require_once pb_backupbuddy::plugin_path() . '/classes/housekeeping.php';
	backupbuddy_housekeeping::remove_temp_tables( $serial, 0 );

	// Remove importbudy Directory.
	if ( file_exists( ABSPATH . 'importbuddy/' ) ) {
		pb_backupbuddy::$filesystem->unlink_recursive( ABSPATH . 'importbuddy/' );
	}

	// Remove importbuddy files.
	$importbuddy_files = glob( ABSPATH . 'importbuddy*.php' );
	if ( ! is_array( $importbuddy_files ) ) {
		$importbuddy_files = array();
	}
	foreach ( $importbuddy_files as $importbuddy_file ) {
		unlink( $importbuddy_file );
	}

	die( '1' );

} elseif ( 'push' == $direction ) { // Remote so call API to clean up.

	require_once pb_backupbuddy::plugin_path() . '/classes/remote_api.php';

	$destination_id = pb_backupbuddy::_POST( 'destinationID' );
	if ( ! isset( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) {
		die( 'Error #8383983: Invalid destination ID `' . htmlentities( $destination_id ) . '`.' );
	}
	$destination_array = pb_backupbuddy::$options['remote_destinations'][ $destination_id ];
	if ( 'site' != $destination_array['type'] ) {
		die( 'Error #8378332: Destination with ID `' . htmlentities( $destination_id ) . '` not of "site" type.' );
	}
	$api_key      = $destination_array['api_key'];
	$api_settings = backupbuddy_remote_api::key_to_array( $api_key );
	$response     = backupbuddy_remote_api::remoteCall( $api_settings, 'confirmDeployment', array( 'serial' => $serial ), 30, array(), true );

	if ( false === $response ) {
		$message = 'Error #2378378324. Unable to confirm remote deployment with serial `' . $serial . '` via remote API. This is a non-fatal warning. BackupBuddy will automatically clean up temporary data later.';
		pb_backupbuddy::status( 'error', $message );
		die( $message );
	} else {
		$response_decoded = @unserialize( $response );
		if ( false === $response_decoded ) {
			$message = 'Error #239872373. Unable to decode remote deployment response with serial `' . $serial . '` via remote API. This is a non-fatal warning. BackupBuddy will automatically clean up temporary data later. Remote server response: `' . print_r( $response_decoded, true ) . '`.';
			pb_backupbuddy::status( 'error', $message );
			die( $message );
		}
		if ( isset( $response_decoded['success'] ) && true === $response_decoded['success'] ) {
			die( '1' );
		}
		$message = 'Error #839743. Unable to confirm remote deployment with serial `' . $serial . '` via remote API. This is a non-fatal warning. BackupBuddy will automatically clean up temporary data later. Remote server response: `' . print_r( $response, true ) . '`.';
		pb_backupbuddy::status( 'error', $message );
		die( $message );
	}
}

// Unknown; error.
die( 'Error #8383293: Unknown direction `' . esc_html( $direction ) . '` for deployment confirmation.' );
