<?php
/**
 * Test Error Log AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$test_message = 'BackupBuddy Test - This is only a test. A user triggered BackupBuddy to determine if writing to the PHP error log is working as expected.';
error_log( $test_message );
die( 'Your PHP error log should contain the following if logging is enabled and functioning properly:' . "\n\n" . '"' . $test_message . '".' );
