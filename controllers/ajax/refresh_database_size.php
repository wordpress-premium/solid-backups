<?php
/**
 * Server info page database size update.
 *
 * Server info page database size refresh. Echos out the new site size (pretty version).
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$database_size = backupbuddy_core::get_database_size(); // array( database_size, database_size_sans_exclusions ).

echo pb_backupbuddy::$format->file_size( $database_size[0] );

die();
