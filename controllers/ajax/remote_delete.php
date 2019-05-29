<?php
/**
 * Ajax Controller to Delete remote destination
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::verify_nonce(); // Security check.

// Destination ID.
$destination_id = pb_backupbuddy::_GET( 'pb_backupbuddy_destinationid' );

// Delete the destination.
require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
$delete_response = pb_backupbuddy_destinations::delete_destination( $destination_id, true );

// Response.
if ( true !== $delete_response ) { // Some kind of error so just echo it.
	echo 'Error #544558: `' . esc_html( $delete_response ) . '`.';
} else { // Success.
	echo 'Destination deleted.';
}

die();
