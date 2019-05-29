<?php
/**
 * Dump out contents of the main log file.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$log_file = backupbuddy_core::getLogDirectory() . 'log-' . pb_backupbuddy::$options['log_serial'] . '.txt';
if ( file_exists( $log_file ) ) {
	readfile( $log_file );
} else {
	echo esc_html__( 'Nothing has been logged.', 'it-l10n-backupbuddy' );
}

die();
