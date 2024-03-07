<?php
/**
 * Remote Client AJAX Controller
 *
 * Used to display destination settings on the Destination Management page.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::$ui->ajax_header( true, true, 'backupbuddy-admin-iframe backupbuddy-remote-client-iframe' );

$destination_id = pb_backupbuddy::_GET( 'destination_id' );

if ( empty( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) {
	echo 'Error #438934894349. Invalid destination ID `' . esc_html( $destination_id ) . '`.';
	return;
}

$destination = pb_backupbuddy::$options['remote_destinations'][ $destination_id ];

require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
pb_backupbuddy_destinations::manage( $destination, $destination_id );

pb_backupbuddy::$ui->ajax_footer();
die();
