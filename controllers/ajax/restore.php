<?php
/**
 * Restore Files AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$response = array(
	'success' => false,
);

$files_array  = pb_backupbuddy::_POST( 'backupbuddy_restore' );
$tables_array = pb_backupbuddy::_POST( 'backupbuddy_restore_tables' );
$archive      = pb_backupbuddy::_POST( 'backupbuddy_zip' );
$destination  = pb_backupbuddy::_POST( 'backupbuddy_restore_destination' );
$what         = pb_backupbuddy::_POST( 'backupbuddy_restore_type' );

if ( empty( $archive ) ) {
	$response['error'] = __( 'Invalid restore request.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

if ( '*' === $files_array ) {
	$files = '*';
} elseif ( ! empty( $files_array ) ) {
	// Needed when only 1 item is restored.
	if ( ! is_array( $files_array ) ) {
		$files_array = array( $files_array );
	}

	if ( ! is_array( $files_array ) ) {
		$response['error'] = __( 'Invalid restore request.', 'it-l10n-backupbuddy' );
		wp_send_json( $response );
		exit();
	}

	$files = array();

	foreach ( $files_array as $file ) {
		if ( '/' === substr( $file, -1 ) ) { // If directory then add wildcard.
			$file = $file . '*';
		}
		$files[ $file ] = $file;
	}

	unset( $files_array );

	if ( ! count( $files ) ) {
		$response['error'] = __( 'No files to restore.', 'it-l10n-backupbuddy' );
		wp_send_json( $response );
		exit();
	}
} else {
	$files = array();
}


if ( '*' === $tables_array ) {
	$tables = '*';
} elseif ( ! empty( $tables_array ) ) {
	// Needed when only 1 table is restored.
	if ( ! is_array( $tables_array ) ) {
		$tables_array = array( $tables_array );
	}

	if ( ! is_array( $tables_array ) ) {
		$response['error'] = __( 'Invalid restore request.', 'it-l10n-backupbuddy' );
		wp_send_json( $response );
		exit();
	}

	$tables = $tables_array;

	unset( $tables_array );

	if ( ! count( $tables ) ) {
		$response['error'] = __( 'No tables to restore.', 'it-l10n-backupbuddy' );
		wp_send_json( $response );
		exit();
	}
} else {
	$tables = array();
}

$restore_id = backupbuddy_restore()->queue( $archive, $files, $tables, $destination, $what );

if ( $restore_id ) {
	$response['success']    = true;
	$response['restore_id'] = $restore_id;
	backupbuddy_restore()->schedule_cron();
} else {
	$response['error'] = __( 'Unable to queue restore. Try again later.', 'it-l10n-backupbuddy' );
}

wp_send_json( $response );
exit();
