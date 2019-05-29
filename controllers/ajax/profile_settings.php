<?php
/**
 * Settings page backup profile editing.
 *
 * View a specified profile's settings.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::$ui->ajax_header();
require_once pb_backupbuddy::plugin_path() . '/views/settings/_includeexclude.php';
pb_backupbuddy::$ui->ajax_footer();

die();
