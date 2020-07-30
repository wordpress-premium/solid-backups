<?php
/**
 * Handle sFTP Key Upload.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$response = array(
	'success' => false,
	'error'   => '',
);

$file_array = isset( $_FILES['sftp_key'] ) ? $_FILES['sftp_key'] : false;
$log_serial = pb_backupbuddy::_POST( 'log_serial' );

if ( empty( $file_array ) || UPLOAD_ERR_NO_FILE === $file_array['error'] || ! $log_serial ) {
	$response['error'] = esc_html__( 'Missing required parameters.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

$allowed_types = array(
	'text/plain',
);

if ( ! in_array( $file_array['type'], $allowed_types, true ) ) {
	$response['error'] = esc_html__( 'Only plain text files are allowed.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

if ( ! function_exists( 'wp_handle_upload' ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
}

$upload = wp_handle_upload( $_FILES['sftp_key'], array( 'test_form' => false ) );
if ( is_wp_error( $upload ) ) {
	$response['error'] = esc_html__( 'There was an error processing your upload', 'it-l10n-backupbuddy' ) . ': ' . $upload->get_error_message();
	wp_send_json( $response );
	exit();
}

if ( empty( $upload['file'] ) || ! file_exists( $upload['file'] ) ) {
	$response['error'] = esc_html__( 'An unexpected error has occurred with the file upload.', 'it-l10n-backupbuddy' );
	if ( ! empty( $upload['error'] ) ) {
		$response['error'] = rtrim( $response['error'], '.' ) . ': ' . $upload['error'];
	}
	wp_send_json( $response );
	exit();
}

// Move file to correct location.
$upload_dir   = wp_upload_dir();
$new_location = $upload_dir['basedir'] . '/backupbuddy-sftp-key-' . $log_serial . '.txt';

@rename( $upload['file'], $new_location );

if ( ! file_exists( $new_location ) ) {
	$response['error'] = esc_html__( 'Could not move key file to correct location.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

$response['success'] = true;
wp_send_json( $response );
exit();
