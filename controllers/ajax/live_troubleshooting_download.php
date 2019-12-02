<?php
/**
 * Live Troubleshooting Download AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

require pb_backupbuddy::plugin_path() . '/destinations/live/_troubleshooting.php';
backupbuddy_live_troubleshooting::run();
$output = "**File best viewed with wordwrap OFF**\n\n" . print_r( backupbuddy_live_troubleshooting::get_raw_results(), true );


header( 'Content-Description: File Transfer' );
header( 'Content-Type: text/plain; name=backupbuddy-live_troubleshooting-' . backupbuddy_core::backup_prefix() . '.txt' );
header( 'Content-Disposition: attachment; filename=backupbuddy-live_troubleshooting-' . backupbuddy_core::backup_prefix() . '.txt' );
header( 'Expires: 0' );
header( 'Content-Length: ' . strlen( $output ) );

pb_backupbuddy::flush();
echo $output;
pb_backupbuddy::flush();


die();
