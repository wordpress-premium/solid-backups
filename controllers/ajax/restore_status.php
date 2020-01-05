<?php
/**
 * Restore Status AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$restore_id = pb_backupbuddy::_GET( 'restore_id' );
$abort      = pb_backupbuddy::_GET( 'abort' );
$response   = array(
	'success'     => false,
	'overlapping' => false,
	'status'      => array(),
);

if ( ! $restore_id ) {
	$response['error'] = __( 'Missing restore ID.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

if ( ! $abort && backupbuddy_restore()->status_request_in_progress() ) {
	pb_backupbuddy::status( 'details', __( 'Aborting restore status request. Another status request is already in progress.', 'it-l10n-backupbuddy' ) );
	$response['overlapping'] = true;
	wp_send_json( $response );
	exit();
}

$restore = backupbuddy_restore()->details( $restore_id );

if ( false === $restore ) {
	$response['error'] = __( 'Invalid restore ID.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

if ( $abort ) {
	$restore = backupbuddy_restore()->user_abort( $restore );
} else {
	// Make sure cron is scheduled.
	backupbuddy_restore()->schedule_cron();
}

$response['success'] = true;
$response['status']  = backupbuddy_restore()->get_js_status( $restore );
$response['is_done'] = in_array( $restore['status'], backupbuddy_restore()->get_completed_statuses(), true );

if ( $response['is_done'] ) {
	backupbuddy_restore()->restore_viewed( $restore_id );
}

backupbuddy_restore()->status_request_complete();

wp_send_json( $response );
exit();
