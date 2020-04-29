<?php
/**
 * Remote destination testing. Echos.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

if ( defined( 'PB_DEMO_MODE' ) ) {
	die( 'Access denied in demo mode.' );
}

global $pb_backupbuddy_destination_errors;
$pb_backupbuddy_destination_errors = array();

require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';

$form_settings = array();
foreach ( pb_backupbuddy::_POST() as $posted_key => $posted_value ) {
	if ( substr( $posted_key, 0, 15 ) === 'pb_backupbuddy_' ) {
		$key = substr( $posted_key, 15 );
		if ( $key ) {
			$form_settings[ $key ] = $posted_value; // Consider using sanitize_text_field() here.
		}
	}
}

ob_start();
$test_result = pb_backupbuddy_destinations::test( $form_settings );
$output      = ob_get_clean();

if ( true === $test_result ) {
	echo 'Test successful.';
} else {
	echo 'Test failed.';
	if ( is_string( $test_result ) ) {
		echo "\r\n" . $test_result;
	}
	foreach ( $pb_backupbuddy_destination_errors as $err ) {
		echo "\r\n" . $err;
	}
}

if ( $output ) {
	echo "\r\n\r\n" . $output;
}

die();
