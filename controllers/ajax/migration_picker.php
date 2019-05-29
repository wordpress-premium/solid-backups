<?php
/**
 * Migration Picker AJAX Controller
 *
 * Same as destination picker but in migration mode (only limited destinations are available).
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::load();

pb_backupbuddy::$ui->ajax_header();

$mode = 'migration';
require_once '_destination_picker.php';

pb_backupbuddy::$ui->ajax_footer();
die();
