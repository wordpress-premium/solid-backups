<?php
/**
 * Remote Client AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::$ui->ajax_header();

if ( isset( pb_backupbuddy::$options['remote_destinations'][ pb_backupbuddy::_GET( 'destination_id' ) ] ) ) {
	$destination = pb_backupbuddy::$options['remote_destinations'][ (int) $_GET['destination_id'] ];
} else {
	echo 'Error #438934894349. Invalid destination ID `' . pb_backupbuddy::_GET( 'destination_id' ) . '`.';
	return;
}

require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
pb_backupbuddy_destinations::manage( $destination, $_GET['destination_id'] );

pb_backupbuddy::$ui->ajax_footer();
die();
