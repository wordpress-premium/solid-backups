<?php
/**
 * Create .dat file from backup zip.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$zip_file = pb_backupbuddy::_GET( 'zip_file' );
$response = array(
	'success' => false,
);

if ( ! $zip_file ) {
	$response['error'] = __( 'Missing .dat file', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

if ( ! backupbuddy_data_file()->locate( $zip_file ) ) {
	// Fake the backup fileoptions array for now.
	$backup_array = array(
		'archive_file' => backupbuddy_core::getBackupDirectory() . $zip_file,
		'profile'      => array(
			'type' => backupbuddy_core::parse_file( $zip_file, 'type' ),
		),
	);

	$result = backupbuddy_data_file()->create( $backup_array );

	if ( ! $result ) {
		$response['error'] = __( 'Unable to create dat file.', 'it-l10n-backupbuddy' );
		wp_send_json( $response );
		exit();
	}
}

$response['success'] = true;
wp_send_json( $response );
exit();
