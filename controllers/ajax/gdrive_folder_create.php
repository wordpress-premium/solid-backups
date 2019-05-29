<?php
/**
 * GDrive Folder Create AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::load();

$parent_id   = pb_backupbuddy::_POST( 'parentID' ); // Gdrive folder parent ID to list within. Use ROOT for looking in root of account.
$parent_id   = str_replace( array( '\\', '/', "'", '"' ), '', $parent_id );
$folder_name = pb_backupbuddy::_POST( 'folderName' ); // BackupBuddy destination ID number for remote destinations array.

$settings      = array();
$client_id     = pb_backupbuddy::_POST( 'clientID' );
$client_secret = pb_backupbuddy::_POST( 'clientSecret' );
$tokens        = pb_backupbuddy::_POST( 'tokens' );

$service_account_email             = pb_backupbuddy::_POST( 'service_account_email' );
$service_account_file              = pb_backupbuddy::_POST( 'service_account_file' );
$settings['service_account_email'] = $service_account_email;
$settings['service_account_file']  = $service_account_file;

$settings['client_id']     = $client_id;
$settings['client_secret'] = $client_secret;
$settings['tokens']        = $tokens;


require_once pb_backupbuddy::plugin_path() . '/destinations/gdrive/init.php';
$return_files = array();

$response = pb_backupbuddy_destination_gdrive::createFolder( $settings, $parent_id, $folder_name );
if ( false === $response ) { // Failed pb_backupbuddy::$options['remote_destinations'][ $destinationID ].
	die(); // Function will have echo'd out the error already.
} else { // Success.
	die(
		json_encode(
			array(
				'success'     => true,
				'folderID'    => $response[0],
				'folderTitle' => $response[1],
			)
		)
	);
}
