<?php
/*
require_once( pb_backupbuddy::plugin_path() . '/classes/live.php' );
pb_backupbuddy_live::generate_queue();
*/

if ( pb_backupbuddy::_GET( 'zip_viewer' ) != '' ) {
	require_once( '_zip_viewer.php' );
	return;
}


// Display upgrade notifcation if running an old major version.
if ( false !== ( $latestVersion = backupbuddy_core::determineLatestVersion() ) ) {
	if ( version_compare( pb_backupbuddy::settings( 'version' ), $latestVersion[1], '<' ) ) {
		$message = 'A new BackupBuddy version is available, v' . $latestVersion[0] . '. You are currently running v' . pb_backupbuddy::settings( 'version' ) . '. Update on the <a href="plugins.php">WordPress Plugins page</a>.';
		$hash = md5( $message );
		pb_backupbuddy::disalert( $hash, $message );
	}
}


backupbuddy_core::versions_confirm();
echo '<!-- BB-versions_confirm done-->';

$alert_message = array();
$preflight_checks = backupbuddy_core::preflight_check();
echo '<!-- BB-preflight_check done -->';
$disableBackingUp = false;
foreach( $preflight_checks as $preflight_check ) {
	if ( $preflight_check['success'] !== true ) {
		//$alert_message[] = $preflight_check['message'];
		pb_backupbuddy::disalert( $preflight_check['test'], $preflight_check['message'] );
		if ( 'backup_dir_permissions' == $preflight_check['test'] ) {
			$disableBackingUp = true;
		} elseif ( 'temp_dir_permissions' == $preflight_check['test'] ) {
			$disableBackingUp = true;
		}
	}
}
if ( count( $alert_message ) > 0 ) {
	//pb_backupbuddy::alert( implode( '<hr style="border: 1px dashed #E6DB55; border-bottom: 0;">', $alert_message ) );
}

echo '<div class="bb_show_preflight" style="display: none;"><h3>Preflight Check Results</h3><pre>';
print_r( $preflight_checks );
echo '</pre></div>';


echo '<!-- BB-listing backups-PRE -->';
$view_data['backups'] = backupbuddy_core::backups_list( 'default' );
echo '<!-- BB-listing backups-POST -->';
$view_data['disableBackingUp'] = $disableBackingUp;
pb_backupbuddy::load_view( '_backup-home', $view_data );