<?php
/**
 * GDrive and GDrive (v2) Folder Create AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::load();

$response = array(
	'success' => false,
	'error'   => '',
);

$parent_id   = pb_backupbuddy::_POST( 'parentID' ); // Gdrive folder parent ID to list within. Use ROOT for looking in root of account.
$parent_id   = str_replace( array( '\\', '/', "'", '"' ), '', $parent_id );
$folder_name = pb_backupbuddy::_POST( 'folderName' ); // Solid Backups destination ID number for remote destinations array.
$version     = (int) pb_backupbuddy::_POST( 'gdrive_version' );
if ( ! $version ) {
	$version = '';
}

$settings      = array();
$client_id     = pb_backupbuddy::_POST( 'clientID' );
$client_secret = pb_backupbuddy::_POST( 'clientSecret' );
$token         = pb_backupbuddy::_POST( 'tokens' ) ? pb_backupbuddy::_POST( 'tokens' ) : pb_backupbuddy::_POST( 'token' );

$service_account_email             = pb_backupbuddy::_POST( 'service_account_email' );
$service_account_file              = pb_backupbuddy::_POST( 'service_account_file' );
$settings['service_account_email'] = $service_account_email;
$settings['service_account_file']  = $service_account_file;

if ( $client_id ) {
	$settings['client_id'] = $client_id;
}
if ( $client_secret ) {
	$settings['client_secret'] = $client_secret;
}
if ( $token ) {
	$var              = 2 === $version ? 'token' : 'tokens';
	$settings[ $var ] = $token;
}

require_once pb_backupbuddy::plugin_path() . '/destinations/gdrive' . $version . '/init.php';
$class = 'pb_backupbuddy_destination_gdrive' . $version;

ob_start();
$create = $class::createFolder( $settings, $parent_id, $folder_name );
$output = ob_get_clean();

if ( false === $create ) {
	$response['error'] = $output ? $output : __( 'An unknown error has occurred creating the folder.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

$response['success'] = true;
if ( 2 === $version ) {
	$response['folder_id']   = $create[0];
	$response['folder_name'] = $create[1];
} else {
	$response['folderID']    = $create[0];
	$response['folderTitle'] = $create[1];
}
wp_send_json( $response );
exit();
