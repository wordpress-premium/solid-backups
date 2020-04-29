<?php
/**
 * GDrive and GDrive (v2) Folder Select
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::load();

$response = array(
	'success' => false,
	'error'   => '',
);

$parent_id = pb_backupbuddy::_POST( 'parentID' ); // GDrive folder parent ID to list within. Use ROOT for looking in root of account.
$parent_id = str_replace( array( '\\', '/', "'", '"' ), '', $parent_id );
$version   = (int) pb_backupbuddy::_POST( 'gdrive_version' );
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
$settings['disable_gzip'] = (int) pb_backupbuddy::_POST( 'disable_gzip' );

require_once pb_backupbuddy::plugin_path() . '/destinations/gdrive' . $version . '/init.php';
$return_folders = array();

ob_start();

// Get all folders in this parent location.
if ( 2 === $version ) {
	if ( ! pb_backupbuddy_destination_gdrive2::is_ready( $settings ) ) {
		$response['error'] = __( 'Invalid response when retrieving folder listing.', 'it-l10n-backupbuddy' );
		wp_send_json( $response );
	}
	$args    = array(
		'query'      => 'folders',
		'query_args' => array(
			$parent_id,
		),
	);
	$folders = pb_backupbuddy_destination_gdrive2::get_folder_contents( $parent_id, $args );
} else {
	$args    = 'mimeType = "application/vnd.google-apps.folder" AND "' . $parent_id . '" in parents AND trashed=false';
	$folders = pb_backupbuddy_destination_gdrive::listFiles( $settings, $args );
}
$output = ob_get_clean();

if ( $output ) {
	$response['error'] = __( 'Potential Error:', 'it-l10n-backupbuddy' ) . ' ' . $output;
}

if ( ! is_array( $folders ) ) {
	$folder_error      = __( 'Invalid response when retrieving folder listing.', 'it-l10n-backupbuddy' );
	$response['error'] = $folder_error . rtrim( ' ' . $response['error'] );

	$response['folders_value'] = print_r( $folders, true );
	wp_send_json( $response );
	exit();
}


foreach ( (array) $folders as $folder ) {
	if ( 2 === $version ) {
		$folder_array = array(
			'id'         => $folder->getId(),
			'title'      => $folder->getName(),
			'created'    => '',
			'createdAgo' => '',
		);

		if ( $folder->getCreatedTime() ) {
			$file_array['created']    = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( strtotime( $folder->getCreatedTime() ) ) );
			$file_array['createdAgo'] = pb_backupbuddy::$format->time_ago( strtotime( $folder->getCreatedTime() ) );
		}

		$return_folders[] = $folder_array;
	} else {
		if ( '1' != $folder->editable ) { // Only show folders we can write to.
			continue;
		}
		$return_folders[] = array(
			'id'         => $folder->id,
			'title'      => $folder->title,
			'created'    => pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( strtotime( $folder->createdDate ) ) ),
			'createdAgo' => pb_backupbuddy::$format->time_ago( strtotime( $folder->createdDate ) ),
		);
	}
}

$response['success'] = true;
$response['folders'] = $return_folders;
wp_send_json( $response );
exit();
