<?php
/**
 * Send backup archive to a remote destination manually. Optionally sends importbuddy.php with files.
 * Sends are scheduled to run in a cron and are passed to the cron.php remote_send() method.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$response = array(
	'success' => false,
	'notice'  => __( 'Error', 'it-l10n-backupbuddy' ),
);

pb_backupbuddy::anti_directory_browsing( backupbuddy_core::getTempDirectory(), false );

$importbuddy_file = backupbuddy_core::getTempDirectory() . 'importbuddy.php';

// Render Importer to temp location.
backupbuddy_core::importbuddy( $importbuddy_file );
if ( file_exists( $importbuddy_file ) ) {
	$result = backupbuddy_core::send_remote_destination( pb_backupbuddy::_GET( 'destination_id' ), $importbuddy_file, 'manual' );
	if ( true === $result ) {
		$response['success'] = true;
		$response['notice']  = esc_html__( 'Importer file successfully sent.', 'it-l10n-backupbuddy' );
	} else {
		$response['notice'] = esc_html__( 'Importer file send failure. Verify your destination settings & check logs for details.', 'it-l10n-backupbuddy' );
	}
} else {
	$response['notice'] = esc_html__( 'Error #4589: Local importbuddy.php file not found for sending. Check directory permissions and / or manually migrating by downloading importbuddy.php.', 'it-l10n-backupbuddy' );
}

if ( file_exists( $importbuddy_file ) ) {
	if ( false === unlink( $importbuddy_file ) ) { // Delete temporary Importer file.
		$response['notice'] .= ' <strong>' . __( 'Unable to delete file. For security please manually delete it', 'it-l10n-backupbuddy' ) . ': `' . $importbuddy_file . '`.</strong>';
	}
}

wp_send_json( $response );
exit();
