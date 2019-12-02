<?php
/**
 * Abort an in-progress demote destination file transfer. Dies with outputting "1" on success.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$send_id = pb_backupbuddy::_GET( 'send_id' );
$send_id = str_replace( '/\\', '', $send_id );

pb_backupbuddy::status( 'details', 'About to load fileoptions data.' );
require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
pb_backupbuddy::status( 'details', 'Fileoptions instance #25.' );
$fileoptions_obj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt', false, true, false );
$result          = $fileoptions_obj->is_ok();
if ( true !== $result ) {
	pb_backupbuddy::status( 'error', __( 'Fatal Error #9034.324544. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
	return false;
}
pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );
$fileoptions = &$fileoptions_obj->options;

$fileoptions['status'] = 'aborted';
$fileoptions_obj->save();

die( '1' );
