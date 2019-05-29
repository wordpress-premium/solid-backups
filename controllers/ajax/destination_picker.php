<?php
/**
 * Iframe remote destination selector page.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::load();

pb_backupbuddy::$ui->ajax_header( true, true );

$mode = 'destination';
require_once '_destination_picker.php';

pb_backupbuddy::$ui->ajax_footer();
die();
