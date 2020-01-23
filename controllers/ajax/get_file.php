<?php
/**
 * Get File Contents from Zip AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$response = array(
	'success' => false,
);

$backup = pb_backupbuddy::_GET( 'backup' );
$file   = pb_backupbuddy::_GET( 'path' );
$mode   = pb_backupbuddy::_GET( 'mode' );
$type   = pb_backupbuddy::_GET( 'type' );

if ( ! $backup ) {
	$response['error'] = 'Missing zip file.';
	wp_send_json( $response );
	exit();
}

$uploads_dir = wp_upload_dir();
$path_to_zip = $uploads_dir['basedir'] . '/backupbuddy_backups/' . $backup;

if ( ! file_exists( $path_to_zip ) ) {
	$response['error'] = 'Backup zip file does not exist: ' . $path_to_zip;
	wp_send_json( $response );
	exit();
}

if ( ! class_exists( 'pluginbuddy_zipbuddy' ) ) {
	require_once pb_backupbuddy::plugin_path() . '/lib/zipbuddy/zipbuddy.php';
}

$zip = new pluginbuddy_zipbuddy( backupbuddy_core::getBackupDirectory() );

if ( ! $zip ) {
	$response['error'] = 'Failed to initialize Zip.';
	wp_send_json( $response );
	exit();
}

$contents = $zip->get_file_contents( $path_to_zip, $file );
if ( false === $contents ) {
	$response['error'] = 'Could not get file contents for: ' . esc_html( $file );
	wp_send_json( $response );
	exit();
}

if ( 'code' === $mode ) {
	$editor_mode = 'text';
	if ( '.css' === substr( $file, -4 ) ) {
		$editor_mode = 'css';
	} elseif ( '.scss' === substr( $file, -5 ) ) {
		$editor_mode = 'scss';
	} elseif ( '.sass' === substr( $file, -5 ) ) {
		$editor_mode = 'sass';
	} elseif ( '.js' === substr( $file, -3 ) ) {
		$editor_mode = 'javascript';
	} elseif ( '.php' === substr( $file, -4 ) ) {
		$editor_mode = 'php';
	} elseif ( '.sql' === substr( $file, -4 ) ) {
		$editor_mode = 'sql';
	}

	$response['success']  = true;
	$response['contents'] = $contents;
	$response['mode']     = $editor_mode;
} elseif ( 'image' === $mode ) {
	$response['success'] = true;
	if ( 'svg' === $type ) {
		$response['contents'] = $contents;
	} else {
		$response['contents'] = '<img src="data:image/jpeg;base64,' . base64_encode( $contents ) . '"/>';
	}
} else {
	$response['error'] = 'Could not determine view mode for: ' . esc_html( $file );
}

wp_send_json( $response );
exit();
