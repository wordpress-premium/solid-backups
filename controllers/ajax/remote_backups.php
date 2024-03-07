<?php
/**
 * Remote Backups Listing AJAX Controller by Destination.
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

$a_mode = pb_backupbuddy::_GET( 'mode' );
$modes  = pb_backupbuddy::_GET( 'modes' );

// Force modes array.
if ( ! empty( $a_mode ) && empty( $modes ) ) {
	$modes = array( $a_mode );
} elseif ( ! is_array( $modes ) ) {
	$modes = array();
}

$destination_id    = pb_backupbuddy::_GET( 'destination_id' );
$response['index'] = pb_backupbuddy::_GET( 'index' );

if ( ! $destination_id && ( '0' !== $destination_id && 0 !== $destination_id ) ) {
	$destination_id = false;
}

// Return early if no usable remote destinations.
if ( false === $destination_id || empty( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) {
	$response['log'][] = __( 'Invalid destination id.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

if ( empty( $modes ) ) {
	$response['error'] = esc_html__( 'Missing table mode.', 'it-l10n-backupbuddy' );
	wp_send_json( $response );
	exit();
}

// Remove s32 as a supported type in a later version.  Note added Feb 6, 2024 (v 9.1.8)
$supported = array( 'local', 's33', 's32', 'stash3', 'stash2', 'sftp', 'ftp', 'dropbox2', 'dropbox3', 'gdrive2', 'onedrive' );

$destination_settings = pb_backupbuddy::$options['remote_destinations'][ $destination_id ];

require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';

if ( ! in_array( $destination_settings['type'], $supported, true ) ) {
	$response['log'][] = __( 'Skipping unsupported destination type ', 'it-l10n-backupbuddy' ) . sprintf( '`%s` (%s).', $destination_settings['type'], $destination_id );
	wp_send_json( $response );
	exit();
}

// Download ALL .dat files.
if ( ! pb_backupbuddy_destinations::download_dat_files( $destination_settings ) ) {
	// If we don't have the .dat files, use traditional restore.
	$response['log'][] = sprintf( __( 'Could not download dat files for destination type', 'it-l10n-backupbuddy' ) . ' `%s` (%s).', $destination_settings['type'], $destination_id );
	wp_send_json( $response );
	exit();
}

backupbuddy_backups()->set_destination_id( $destination_id );

if ( ! empty( $modes ) ) {
	foreach ( $modes as $the_mode ) {
		$backups = pb_backupbuddy_destinations::listFiles( $destination_settings, $the_mode, true );

		if ( is_string( $backups ) ) {
			$response['errors'][] = $backups;
		} elseif ( is_array( $backups ) && count( $backups ) ) {
			// Only show backups that have dat files.
			$backups = backupbuddy_backups()->filter_by_dat( $backups );

			ob_start();
			backupbuddy_backups()->table(
				$the_mode,
				$backups,
				array(
					'destination_id'     => $destination_id,
					'class'              => 'minimal',
					'disable_pagination' => true,
					'disable_assets'     => true,
				)
			);
			$table = ob_get_clean();

			$response['backups'][][ $the_mode ] = $table;
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
