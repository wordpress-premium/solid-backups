<?php
/**
 * Remote destination saving.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::verify_nonce();

$response = array(
	'success' => false,
	'status'  => '',
);

require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
$settings_form = pb_backupbuddy_destinations::configure( array( 'type' => pb_backupbuddy::_POST( 'pb_backupbuddy_type' ) ), 'save' );

$save_result = $settings_form->process();

$destination_id = trim( pb_backupbuddy::_GET( 'pb_backupbuddy_destinationid' ) );

if ( isset( $save_result['errors'] ) ) {
	if ( count( $save_result['errors'] ) === 0 ) { // NO ERRORS SO SAVE.

		if ( 'NEW' == $destination_id ) { // ADD NEW.

			// Copy over dropbox token.
			$save_result['data']['token'] = pb_backupbuddy::$options['dropboxtemptoken'];

			pb_backupbuddy::$options['remote_destinations'][] = $save_result['data'];

			$destination_id = array_key_last( pb_backupbuddy::$options['remote_destinations'] );

			$new_destination          = array();
			$new_destination['title'] = $save_result['data']['title'];
			$new_destination['type']  = $save_result['data']['type'];
			backupbuddy_core::addNotification( 'destination_created', 'Remote destination created', 'A new remote destination "' . $new_destination['title'] . '" has been created.', $new_destination );

			pb_backupbuddy::save();
			$response['success'] = true;
			$response['status']  = 'added';
			$response['new_tab'] = 'destination-' . $save_result['data']['type'] . '-' . $destination_id;
		} elseif ( ! isset( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) { // EDITING NONEXISTANT.
			$response['error'] = 'Error #54859. Invalid destination ID `' . esc_html( $destination_id ) . '`.';
		} else { // EDITING EXISTING -- Save!

			// Copy over dropbox token.
			pb_backupbuddy::$options['remote_destinations'][ $destination_id ] = array_merge( pb_backupbuddy::$options['remote_destinations'][ $destination_id ], $save_result['data'] );
			pb_backupbuddy::save();

			$response['status']  = 'saved';
			$response['success'] = true;

			$edited_destination          = array();
			$edited_destination['title'] = $save_result['data']['title'];
			$edited_destination['type']  = $save_result['data']['type'];
			backupbuddy_core::addNotification( 'destination_updated', 'Remote destination updated', 'An existing remote destination "' . $edited_destination['title'] . '" has been updated.', $edited_destination );
		}
	} else {
		$response['status'] = 'Error saving settings. ' . implode( "\n", $save_result['errors'] );
	}
}

wp_send_json( $response );
die();
