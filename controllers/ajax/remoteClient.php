<?php
/**
 * Remote Client AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::$ui->ajax_header();

$destination_id = pb_backupbuddy::_GET( 'destination_id' );

if ( isset( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) {
	$destination = pb_backupbuddy::$options['remote_destinations'][ $destination_id ];
} else {
	echo 'Error #438934894349. Invalid destination ID `' . $destination_id . '`.';
	return;
}

require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
pb_backupbuddy_destinations::manage( $destination, $destination_id );

pb_backupbuddy::$ui->ajax_footer();
die();
