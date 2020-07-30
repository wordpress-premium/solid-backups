<?php
/**
 * Handle sFTP Key Clear.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$response = array(
	'success' => false,
	'error'   => '',
);

// Collect required parameters.
$log_serial = pb_backupbuddy::_POST( 'log_serial' );
if ( ! $log_serial ) {
	$response['error'] = esc_html__( 'Missing required parameters.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

$upload_dir = wp_upload_dir();
$key_path   = $upload_dir['basedir'] . '/backupbuddy-sftp-key-' . $log_serial . '.txt';

// Make sure key file exists.
if ( ! file_exists( $key_path ) ) {
	$response['error'] = esc_html__( 'sFTP key file not found.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

// Attempt to delete the key file.
@unlink( $key_path );

// Make sure key file got deleted.
if ( file_exists( $key_path ) ) {
	$response['error'] = esc_html__( 'Could not clear sFTP key file. Check file permissions.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

// Looks to be all set!
$response['success'] = true;

wp_send_json( $response );
exit();
