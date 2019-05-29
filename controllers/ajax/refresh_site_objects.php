<?php
/**
 * Server info page site objects file count update. Echos out the new site file count (pretty version).
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$site_size = backupbuddy_core::get_site_size(); // array( site_size, site_size_sans_exclusions ).

echo $site_size[2];

die();
