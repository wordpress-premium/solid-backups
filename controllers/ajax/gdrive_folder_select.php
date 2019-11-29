<?php
/**
 * GDrive Folder Select
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::load();

$parent_id = pb_backupbuddy::_POST( 'parentID' ); // Gdrive folder parent ID to list within. Use ROOT for looking in root of account.
$parent_id = str_replace( array( '\\', '/', "'", '"' ), '', $parent_id );

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
$settings['disable_gzip']  = pb_backupbuddy::_POST( 'disable_gzip' );



require_once pb_backupbuddy::plugin_path() . '/destinations/gdrive/init.php';
$return_files = array();

// Get all folders in this parent location.
$files = pb_backupbuddy_destination_gdrive::listFiles( $settings, 'mimeType = "application/vnd.google-apps.folder" AND "' . $parent_id . '" in parents AND trashed=false' ); // "title contains 'backup' and trashed=false" ); //"title contains 'backup' and trashed=false" );
foreach ( (array) $files as $file ) {
	if ( '1' != $file->editable ) { // Only show folders we can write to.
		continue;
	}
	$return_files[] = array(
		'id'         => $file->id,
		'title'      => $file->title,
		'created'    => pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( strtotime( $file->createdDate ) ) ),
		'createdAgo' => pb_backupbuddy::$format->time_ago( strtotime( $file->createdDate ) ),
	);
}

die(
	json_encode(
		array(
			'success' => true,
			'folders' => $return_files,
		)
	)
);
