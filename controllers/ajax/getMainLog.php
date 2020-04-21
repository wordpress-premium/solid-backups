<?php
/**
 * Dump out contents of the main log file.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$log_file  = backupbuddy_core::getLogDirectory() . 'log-' . pb_backupbuddy::$options['log_serial'] . '.txt';
$confirmed = pb_backupbuddy::_POST( 'confirmed' );

if ( file_exists( $log_file ) ) {
	if ( ! $confirmed ) {
		$size = filesize( $log_file );
		if ( $size >= 30 * MB_IN_BYTES ) {
			$response = array(
				'size' => pb_backupbuddy::$format->file_size( $size ),
			);
			wp_send_json( $response );
			exit();
		}
	}
	readfile( $log_file );
} else {
	echo esc_html__( 'Nothing has been logged.', 'it-l10n-backupbuddy' );
}

die();
