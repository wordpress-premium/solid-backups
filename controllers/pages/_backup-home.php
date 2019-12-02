<?php
/**
 * Main Backup Page Controller
 *
 * @package BackupBuddy
 */

if ( pb_backupbuddy::_GET( 'zip_viewer' ) != '' ) {
	require_once '_zip_viewer.php';
	return;
}

// Display upgrade notifcation if running an old major version.
$latestVersion = backupbuddy_core::determineLatestVersion();
if ( false !== $latestVersion ) {
	if ( version_compare( pb_backupbuddy::settings( 'version' ), $latestVersion[1], '<' ) ) {
		$message = 'A new BackupBuddy version is available, v' . $latestVersion[0] . '. You are currently running v' . pb_backupbuddy::settings( 'version' ) . '. Update on the <a href="plugins.php">WordPress Plugins page</a>.';
		$hash    = md5( $message );
		pb_backupbuddy::disalert( $hash, $message );
	}
}

backupbuddy_core::versions_confirm();
echo '<!-- BB-versions_confirm done-->';

pb_backupbuddy::load_view( '_backup-home' );
