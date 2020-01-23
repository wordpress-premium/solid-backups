<?php
/**
 * Remote Backups Listing AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$response = array(
	'success' => false,
	'backups' => array(),
	'errors'  => array(),
	'log'     => array(),
);

// Return early if no remote destinations.
if ( ! count( pb_backupbuddy::$options['remote_destinations'] ) ) {
	$response['log'][] = __( 'No destinations found.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

$destinations = array();
foreach ( pb_backupbuddy::$options['remote_destinations'] as $destination_id => $destination_settings ) {
	if ( 'live' === $destination_settings['type'] ) {
		continue;
	}
	$destinations[ $destination_id ] = $destination_settings;
}

// Return early if no usable remote destinations.
if ( ! count( $destinations ) ) {
	$response['log'][] = __( 'No supported destinations found.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

$mode  = pb_backupbuddy::_GET( 'mode' );
$modes = pb_backupbuddy::_GET( 'modes' );

if ( empty( $mode ) && empty( $modes ) ) {
	$response['error'] = esc_html__( 'Missing table mode.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

$supported = array( 'local', 's33', 's32', 'stash3', 'stash2' );

require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';

foreach ( $destinations as $destination_id => $destination_settings ) {
	if ( ! in_array( $destination_settings['type'], $supported, true ) ) {
		$response['log'][] = sprintf( __( 'Skipping unsupported destination type `%s` (%s).', 'it-l10n-backupbuddy' ), $destination_settings['type'], $destination_id );
		continue;
	}

	// Download ALL .dat files.
	if ( ! pb_backupbuddy_destinations::download_dat_files( $destination_settings ) ) {
		// If we don't have the .dat files, use traditional restore.
		$response['log'][] = sprintf( __( 'Could not download dat files for destination type `%s` (%s).', 'it-l10n-backupbuddy' ), $destination_settings['type'], $destination_id );
		continue;
	}

	backupbuddy_backups()->set_destination_id( $destination_id );
	if ( ! empty( $modes ) && is_array( $modes ) ) {
		foreach ( $modes as $mode ) {
			$backups = pb_backupbuddy_destinations::listFiles( $destination_settings, $mode, true );

			if ( is_string( $backups ) ) {
				$response['errors'][] = $backups;
			} elseif ( is_array( $backups ) && count( $backups ) ) {
				ob_start();
				backupbuddy_backups()->table( $mode, $backups, array(
					'destination_id'     => $destination_id,
					'class'              => 'minimal',
					'disable_pagination' => true,
				) );
				$table = ob_get_clean();

				$response['backups'][][ $mode ] = $table;
			}
		}
	} else {
		$backups = pb_backupbuddy_destinations::listFiles( $destination_settings, $mode, true );

		if ( is_string( $backups ) ) {
			$response['errors'][] = $backups;
		} elseif ( is_array( $backups ) && count( $backups ) ) {
			ob_start();
			backupbuddy_backups()->table( $mode, $backups, array(
				'destination_id'     => $destination_id,
				'class'              => 'minimal',
				'disable_pagination' => true,
			) );
			$table = ob_get_clean();

			$response['backups'][][ $mode ] = $table;
		}
	}
}

if ( count( $response['backups'] ) ) {
	$response['success'] = true;
} else {
	$response['log'][] = __( 'No remote backups found.', 'it-l10n-backupbuddy' );
}

wp_send_json( $response );
exit();
