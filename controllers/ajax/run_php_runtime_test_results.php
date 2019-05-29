<?php
/**
 * PHP Runtime Test Results AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::load();

$results = backupbuddy_core::php_runtime_test_results();

if ( false === $results ) {
	$tested_runtime_sofar = '';
	$test_file = backupbuddy_core::getLogDirectory() . 'php_runtime_test.txt';
	if ( file_exists( $test_file ) ) {
		$tested_runtime = @file_get_contents( $test_file );
		if ( false !== $tested_runtime ) {
			if ( is_numeric( trim( $tested_runtime ) ) ) {
				$tested_runtime_sofar = ' ' . $tested_runtime . ' ' . __( 'secs so far.', 'it-l10n-backupbuddy' );
			}
		}
	}

	die( esc_html__( 'This may take a few minutes...', 'it-l10n-backupbuddy' ) . esc_html( $tested_runtime_sofar ) );
}

die( $results );
