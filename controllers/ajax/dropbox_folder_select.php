<?php
/**
 * Dropbox Folder Select
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::load();

$response       = array(
	'success' => false,
	'folders' => array(),
);
$destination_id = pb_backupbuddy::_POST( 'destination_id' ); // Dropbox folder parent ID to list within.
$parent_path    = pb_backupbuddy::_POST( 'parent_path' ); // Dropbox folder parent to list within.

if ( '' != $destination_id ) {
	$settings = pb_backupbuddy::$options['remote_destinations'][ $destination_id ];
} else {
	$settings = array(
		'oauth_code'  => pb_backupbuddy::_POST( 'oauth_code' ),
		'oauth_state' => pb_backupbuddy::_POST( 'oauth_state' ),
		'oauth_token' => pb_backupbuddy::_POST( 'oauth_token' ),
	);
}

require_once pb_backupbuddy::plugin_path() . '/destinations/dropbox3/init.php';
pb_backupbuddy_destination_dropbox3::add_settings( $settings );

$return_folders = array();

// Get all folders in this parent location.
$folders = pb_backupbuddy_destination_dropbox3::get_folders( $parent_path );
if ( ! is_array( $folders ) ) {
	global $pb_backupbuddy_destination_errors;
	$response['error'] = __( 'Invalid response from Dropbox API', 'it-l10n-backupbuddy' );
	if ( is_array( $pb_backupbuddy_destination_errors ) && count( $pb_backupbuddy_destination_errors ) ) {
		$response['error'] .= ' (' . $pb_backupbuddy_destination_errors[0] . ')';
	}
	wp_send_json( $response );
	exit();
}

if ( count( $folders ) ) {
	foreach ( (array) $folders as $folder ) {
		$return_folders[] = array(
			'id'   => $folder['id'],
			'name' => $folder['name'],
			'path' => $folder['path_display'],
		);
	}
}

$response['success'] = true;
$response['folders'] = $return_folders;

wp_send_json( $response );
exit();
