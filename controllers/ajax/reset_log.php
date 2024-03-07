<?php
/**
 * Reset a Solid Backups Log File.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$response = array(
	'success' => false,
	'error'   => '',
	'message' => '',
);

$log = pb_backupbuddy::_POST( 'log' );

if ( 'main' === $log ) {
	$suffix = pb_backupbuddy::$options['log_serial'];
} elseif ( 'remote' === $log ) {
	$suffix = pb_backupbuddy::$options['log_serial'] . '-remote_api';
} else {
	$response['error'] = esc_html__( 'Log Reset Error: Invalid log parameter.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

$log_file = backupbuddy_core::getLogDirectory() . 'log-' . $suffix . '.txt';

if ( file_exists( $log_file ) ) {
	@unlink( $log_file );
}

if ( file_exists( $log_file ) ) { // Didn't unlink.
	$response['error'] = esc_html__( 'Unable to clear log file. Please verify permissions on file', 'it-l10n-backupbuddy' ) . ' `' . $log_file . '`';
} else { // Unlinked.
	$response['message'] = esc_html__( 'Log file reset.', 'it-l10n-backupbuddy' );
	$response['success'] = true;
}

wp_send_json( $response );
exit();
