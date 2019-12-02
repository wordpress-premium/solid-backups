<?php
/**
 * PHP Memory Test Results AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::load();

$results = backupbuddy_core::php_memory_test_results();

if ( false === $results ) {
	$tested_memory_sofar = '';
	$test_file           = backupbuddy_core::getLogDirectory() . 'php_memory_test.txt';
	if ( file_exists( $test_file ) ) {
		$tested_memory = @file_get_contents( $test_file );
		if ( false !== $tested_memory ) {
			if ( is_numeric( trim( $tested_memory ) ) ) {
				$tested_memory_sofar = ' ' . $tested_memory . ' ' . __( 'MB so far.', 'it-l10n-backupbuddy' );
			}
		}
	}

	die( esc_html__( 'This may take a few minutes...', 'it-l10n-backupbuddy' ) . $tested_memory_sofar );
}

die( $results );
