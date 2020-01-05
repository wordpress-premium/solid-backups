<?php
/**
 * Backup Info AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$zip_file       = pb_backupbuddy::_GET( 'backup' );
$destination_id = pb_backupbuddy::_GET( 'destination' );
$return         = trim( strtolower( pb_backupbuddy::_GET( 'return' ) ) );
$response       = array(
	'success' => false,
	'info'    => array(),
	'source'  => 'unavailable',
);

if ( ! $zip_file ) {
	$response['error'] = __( 'Missing zip file.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

$response['success'] = true;
$response['info']    = backupbuddy_core::parse_file( $zip_file );

if ( ! $destination_id && '0' !== $destination_id && 0 !== $destination_id ) {
	$destination_id = false;
}

$data     = backupbuddy_data_file()->get( $zip_file, $destination_id );
$zip_size = false;

if ( $data ) {
	$response['source']             = 'dat_file';
	$response['info']['wp_version'] = $data['wp_version'];
	$zip_size                       = $data['zip_size'];
} else {
	$zip_path = backupbuddy_core::getBackupDirectory() . $zip_file;
	if ( file_exists( $zip_path ) ) {
		$zip_size           = filesize( $zip_path );
		$response['source'] = 'zip_file';
	}
}

if ( $zip_size ) {
	$response['info']['size'] = pb_backupbuddy::$format->file_size( $zip_size );
}

if ( 'html' === $return ) {
	ob_start();
	include pb_backupbuddy::plugin_path() . '/views/backups/backup-detail.php';
	$response['html'] = ob_get_clean();
}

wp_send_json( $response );
exit();
