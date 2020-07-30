<?php
/**
 * Ajax Controller to Delete restore log
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$restore_id = pb_backupbuddy::_GET( 'id' );
$is_corrupt = (bool) (int) pb_backupbuddy::_GET( 'corrupt' );
$response   = array(
	'success' => false,
	'error'   => '',
);

if ( ! $restore_id ) {
	$response['error'] = esc_html__( 'Missing required parameter: id.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

if ( false !== strpos( $restore_id, '/' ) || false !== strpos( $restore_id, '\\' ) ) {
	$response['error'] = esc_html__( 'Invalid restore file path.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

if ( $is_corrupt ) {
	$file_path = backupbuddy_core::getLogDirectory() . $restore_id;
} else {
	$file_path = backupbuddy_core::getLogDirectory() . 'backupbuddy-restore-' . $restore_id . '.txt';
}

if ( ! file_exists( $file_path ) ) {
	$response['error'] = esc_html__( 'Could not locate restore file.', 'it-l10n-backupbuddy' );
	$response['path']  = $file_path;
	wp_send_json( $response );
	exit();
}

if ( false === @unlink( $file_path ) ) {
	$response['error'] = esc_html__( 'Could not delete restore file.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

$response['success'] = true;
wp_send_json( $response );
exit();
