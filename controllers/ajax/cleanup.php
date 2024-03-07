<?php
/**
 * Cleanup backup files.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$rel = pb_backupbuddy::_GET( 'rel' );

$response = array(
	'success' => false,
	'error'   => '',
);

// Local is used for local backups, all other use the Destination ID.
if ( 'local' !== $rel ) {
	if ( ! backupbuddy_backups()->set_destination_id( $rel ) ) {
		$response['error'] = '<p>' . __( 'Invalid destination ID.', 'it-l10n-backupbuddy' ) . '</p>';
		wp_send_json( $response );
		exit();
	}
}

if ( ! backupbuddy_backups()->do_cleanup() ) {
	$response['error'] = '<p>' . __( 'Solid Backups encountered a problem during file clean up. Please contact support for more information.', 'it-l10n-backupbuddy' ) . '</p>';
} else {
	$response['success'] = true;
}

wp_send_json( $response );
exit();
