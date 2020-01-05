<?php
/**
 * OneDrive Folder Create AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::load();

$response    = array(
	'success' => false,
	'id'      => '',
	'name'    => '',
);
$parent_id   = pb_backupbuddy::_POST( 'parent_id' ); // OneDrive folder parent ID to list within.
$parent_id   = trim( str_replace( array( '\\', '/', "'", '"' ), '', $parent_id ) );
$folder_name = pb_backupbuddy::_POST( 'folder_name' ); // BackupBuddy destination ID number for remote destinations array.

if ( ! $parent_id ) {
	$parent_id = false;
}

$settings = array(
	'oauth_code'     => pb_backupbuddy::_POST( 'oauth_code' ),
	'onedrive_state' => pb_backupbuddy::_POST( 'onedrive_state' ),
);

require_once pb_backupbuddy::plugin_path() . '/destinations/onedrive/init.php';
pb_backupbuddy_destination_onedrive::add_settings( $settings );

$new_folder = pb_backupbuddy_destination_onedrive::create_folder( $folder_name, $parent_id );
if ( ! is_object( $new_folder ) ) {
	$response['error'] = __( 'There was a problem creating the folder.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

$response['success'] = true;
$response['id']      = $new_folder->getId();
$response['name']    = $new_folder->getName();

wp_send_json( $response );
exit();
