<?php
/**
 * Settings page backup profile editing.
 *
 * View a specified profile's settings.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::$ui->ajax_header( true, true, 'backupbuddy-admin-iframe-white backupbuddy-profile-settings-iframe' );
require_once pb_backupbuddy::plugin_path() . '/views/settings/_includeexclude.php';
pb_backupbuddy::$ui->ajax_footer();

die();
