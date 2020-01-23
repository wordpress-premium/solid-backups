<?php
/**
 * OneDrive Folder Select
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::load();

$response       = array(
	'success' => false,
	'folders' => array(),
);
$destination_id = pb_backupbuddy::_POST( 'destination_id' ); // OneDrive folder parent ID to list within.
$parent_id      = pb_backupbuddy::_POST( 'parent_id' ); // OneDrive folder parent ID to list within.
$parent_id      = str_replace( array( '\\', '/', "'", '"' ), '', $parent_id );
if ( 'root' === $parent_id ) {
	$parent_id = false;
}

if ( '' != $destination_id ) {
	$settings = pb_backupbuddy::$options['remote_destinations'][ $destination_id ];
} else {
	$settings = array(
		'oauth_code'     => pb_backupbuddy::_POST( 'oauth_code' ),
		'onedrive_state' => pb_backupbuddy::_POST( 'onedrive_state' ),
	);
}

require_once pb_backupbuddy::plugin_path() . '/destinations/onedrive/init.php';
pb_backupbuddy_destination_onedrive::add_settings( $settings );

$return_folders = array();

// Get all folders in this parent location.
$folders = pb_backupbuddy_destination_onedrive::get_folders( $parent_id );
if ( ! is_array( $folders ) ) {
	$response['error'] = __( 'Invalid response from OneDrive API', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

if ( count( $folders ) ) {
	foreach ( (array) $folders as $folder ) {
		$created_time     = $folder->createdDateTime->getTimestamp();
		$return_folders[] = array(
			'id'          => $folder->id,
			'name'        => $folder->name,
			'created'     => pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $created_time ) ),
			'created_ago' => pb_backupbuddy::$format->time_ago( $created_time ),
		);
	}
}

$response['success'] = true;
$response['folders'] = $return_folders;

wp_send_json( $response );
exit();
