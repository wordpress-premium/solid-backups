<?php
/**
 * Handle allowing download of archive.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

// If a Network and NOT the superadmin must make sure they can only download the specific subsite backups for security purposes.
if ( is_multisite() && ! current_user_can( 'manage_network' ) ) {

	// Only allow downloads of their own backups.
	if ( ! strstr( pb_backupbuddy::_GET( 'backupbuddy_backup' ), backupbuddy_core::backup_prefix() ) ) {
		die( 'Access Denied. You may only download backups specific to your Multisite Subsite. Only Network Admins may download backups for another subsite in the network.' );
	}
}

// Make sure file exists we are trying to get.
if ( ! file_exists( backupbuddy_core::getBackupDirectory() . pb_backupbuddy::_GET( 'backupbuddy_backup' ) ) ) { // Does not exist.
	die( 'Error #548957857584784332. The requested backup file does not exist. It may have already been deleted.' );
}

$abspath    = str_replace( '\\', '/', ABSPATH ); // Change slashes to handle Windows as we store backup_directory with Linux-style slashes even on Windows.
$backup_dir = str_replace( '\\', '/', backupbuddy_core::getBackupDirectory() );

// Make sure file to download is in a publicly accessible location (beneath WP web root technically).
if ( false === stristr( $backup_dir, $abspath ) ) {
	die( 'Error #5432532. You cannot download backups stored outside of the WordPress web root. Please use FTP or other means.' );
}

// Made it this far so download dir is within this WP install.
$sitepath     = str_replace( $abspath, '', $backup_dir );
$download_url = rtrim( site_url(), '/\\' ) . '/' . trim( $sitepath, '/\\' ) . '/' . pb_backupbuddy::_GET( 'backupbuddy_backup' );

if ( '1' === pb_backupbuddy::$options['lock_archives_directory'] ) { // High security mode.
	require_once pb_backupbuddy::plugin_path() . '/classes/housekeeping.php';
	backupbuddy_housekeeping::allow_remote_connection();
	header( 'Location: ' . $download_url );
} else { // Normal mode.
	header( 'Location: ' . $download_url );
}

die();
