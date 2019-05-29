<?php
/**
 * Server info page icicle for GUI file listing.
 *
 * Builds and returns graphical directory size listing. Echos.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::set_greedy_script_limits(); // Building the directory tree can take a bit.

$response = backupbuddy_core::build_icicle( ABSPATH, ABSPATH, '', -1 );

echo $response[0];
die();
