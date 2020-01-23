<?php
/**
 * Server info page site size (sans exclusions) update. Echos out the new site size (pretty version).
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$site_size = backupbuddy_core::get_site_size(); // array( site_size, site_size_sans_exclusions ).

echo pb_backupbuddy::$format->file_size( $site_size[1] );

die();
