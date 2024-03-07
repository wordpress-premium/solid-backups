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
	'success' => false,
	'status'  => array(),
);

if ( ! $restore_id ) {
	$response['error'] = __( 'Missing restore ID.', 'it-l10n-backupbuddy' );
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
}

$response['success'] = true;
$response['status']  = backupbuddy_restore()->get_js_status( $restore );
$response['is_done'] = in_array( $restore['status'], backupbuddy_restore()->get_completed_statuses(), true );

if ( $response['is_done'] && empty( $restore['viewed'] ) ) {
	backupbuddy_restore()->restore_viewed( $restore_id );
} elseif ( BackupBuddy_Restore::STATUS_NOT_STARTED === $restore['status'] ) {
	// Sometimes restore needs a kick start.
	backupbuddy_restore()->schedule_cron();
} elseif ( time() % 2 === 0 ) {
	/*
	* Keep restore moving by checking every 2 seconds.
	* If it is already running, this will do nothing.
	*/
	backupbuddy_restore()->process( $restore_id );

}

wp_send_json( $response );
exit();
