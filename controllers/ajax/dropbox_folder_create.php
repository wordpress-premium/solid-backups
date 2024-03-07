<?php
/**
 * Dropbox Folder Create AJAX Controller
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
$folder_path = pb_backupbuddy::_POST( 'folder_path' ); // Solid Backups destination ID number for remote destinations array.
$folder_path = '/' . ltrim( $folder_path, '/' );

$settings = array(
	'oauth_code'  => pb_backupbuddy::_POST( 'oauth_code' ),
	'oauth_state' => pb_backupbuddy::_POST( 'oauth_state' ),
	'oauth_token' => pb_backupbuddy::_POST( 'oauth_token' ),
);

require_once pb_backupbuddy::plugin_path() . '/destinations/dropbox3/init.php';
pb_backupbuddy_destination_dropbox3::add_settings( $settings );

// Consider using parent folder ID here, then getting parent path from metadata.
$new_folder = pb_backupbuddy_destination_dropbox3::create_folder( $folder_path );

if ( ! is_array( $new_folder ) ) {
	$response['error'] = __( 'There was a problem creating the folder.', 'it-l10n-backupbuddy' );
	global $pb_backupbuddy_destination_errors;
	if ( is_string( $new_folder ) ) {
		$response['error'] .= ' - ' . $new_folder;
	} elseif ( $pb_backupbuddy_destination_errors ) {
		$response['error'] .= ' - ' . $pb_backupbuddy_destination_errors[0];
	}
	wp_send_json( $response );
	exit();
}

$response['success'] = true;
$response['id']      = $new_folder['metadata']['id'];
$response['name']    = basename( $new_folder['metadata']['path_display'] );
$response['path']    = $new_folder['metadata']['path_display'];

wp_send_json( $response );
exit();
