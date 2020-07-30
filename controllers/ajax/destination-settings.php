<?php
/**
 * Destination Settings Loader
 *
 * @package BackupBuddy
 */

// Make sure ajax cam be accessed.
backupbuddy_core::verifyAjaxAccess();

$destination_id = pb_backupbuddy::_GET( 'destination_id' );
$mode           = pb_backupbuddy::_GET( 'mode' );

if ( ! $destination_id && '0' !== $destination_id && 0 !== $destination_id ) {
	echo 'ERROR: Invalid destination ID.';
	return;
}

if ( empty( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) {
	echo 'Error #556656b. Unable to display configuration. This destination\'s settings may be corrupt. Removing this destination. Please refresh the page.';
	unset( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] );
	pb_backupbuddy::save();
	return;
}

// Destinations may hide the add and test buttons by altering these variables.
global $pb_hide_save;
global $pb_hide_test;
$pb_hide_save = false;
$pb_hide_test = false;

if ( ! class_exists( 'pb_backupbuddy_destinations' ) ) {
	require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
}

$destination = pb_backupbuddy::$options['remote_destinations'][ $destination_id ];
$settings    = pb_backupbuddy_destinations::configure( $destination, 'edit', $destination_id );

if ( false === $settings ) {
	echo 'Error #556656b. Unable to display configuration. This destination\'s settings may be corrupt. Removing this destination. Please refresh the page.';
	unset( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] );
	pb_backupbuddy::save();
	return;
}
$save_and_delete_button = '';
$test_button            = '';

echo '<h3 style="margin-left: 0;">' . esc_html__( 'Destination Settings', 'it-l10n-backupbuddy' ) . '</h3>';

if ( true !== $pb_hide_test ) {
	$test_button = '<a href="#" class="button secondary-button pb_backupbuddy_destpicker_test" href="#" title="Test destination settings.">Test Settings<img class="pb_backupbuddy_destpicker_testload" src="' . esc_attr( pb_backupbuddy::plugin_url() ) . '/images/loading.gif" title="Testing... This may take several seconds..."></a>&nbsp;&nbsp;';
}

if ( false === apply_filters( 'itbub_disable_delete_destination_button', false ) ) :
	$save_and_delete_button = '<a href="#" class="button secondary-button pb_backupbuddy_destpicker_delete" href="javascript:void(0)" title="Delete this Destination">Delete Destination</a>&nbsp;&nbsp;';
endif;

echo $settings->display_settings(
	'Save Settings', // title.
	$save_and_delete_button . $test_button, // before.
	' <img class="pb_backupbuddy_destpicker_saveload" src="' . esc_attr( pb_backupbuddy::plugin_url() ) . '/images/loading.gif" title="Saving... This may take a few seconds...">', // after.
	'pb_backupbuddy_destpicker_save' // class.
);
