<?php
/**
 * Quick Start AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::$ui->ajax_header();
require_once pb_backupbuddy::plugin_path() . '/views/_quicksetup.php';
pb_backupbuddy::$ui->ajax_footer();

die();
