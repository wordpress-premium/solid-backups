<?php
/**
 * Iframe remote destination selector page.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::load();

pb_backupbuddy::$ui->ajax_header( true, true, 'backupbuddy-destination-picker-iframe backupbuddy-admin-iframe-white backup-profiles-destination-picker-iframe' );

$mode = 'destination';
require_once '_destination_picker.php';

pb_backupbuddy::$ui->ajax_footer();
die();
