<?php
/**
 * Live Stash Files AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();
pb_backupbuddy::$ui->ajax_header( true, false );

require_once pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php';
require_once pb_backupbuddy::plugin_path() . '/destinations/live/live.php';

$destination_id = backupbuddy_live::getLiveID();
$destination    = backupbuddy_live_periodic::get_destination_settings();

$hide_quota = true;
$live_mode  = true;
require_once pb_backupbuddy::plugin_path() . '/destinations/stash2/init.php';

if ( '1' == $destination['disable_file_management'] ) {
	esc_html_e( 'Remote file management has been disabled for Stash Live. Its files cannot be viewed & managed from within BackupBuddy. To re-enable you must Disconnect and Re-connect Stash Live. You may also manage your files at <a href="https://sync.ithemes.com" target="_new">https://sync.ithemes.com</a>.', 'it-l10n-backupbuddy' );
} else {
	require_once pb_backupbuddy::plugin_path() . '/destinations/stash2/_manage.php';
}

pb_backupbuddy::$ui->ajax_footer( true );
