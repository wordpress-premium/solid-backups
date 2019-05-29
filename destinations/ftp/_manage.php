<?php
/**
 * Manage FTP Remote Destination
 *
 * @package BackupBuddy
 * @author Skyler Moore
 */

if ( isset( $destination['disabled'] ) && '1' == $destination['disabled'] ) {
	die(
		sprintf( '<span class="description">%s</span>',
			esc_html__( 'This destination is currently disabled based on its settings. Re-enable it under its Advanced Settings.', 'it-l10n-backupbuddy' )
		)
	);
}

// FTP connection information.
$ftp_server    = $destination['address'];
$ftp_username  = $destination['username'];
$ftp_password  = $destination['password'];
$ftp_directory = (string) $destination['path'];
$ftps          = $destination['ftps'];
if ( ! empty( $ftp_directory ) ) {
	$ftp_directory = $ftp_directory . '/';
}
$active = true;
if ( isset( $destination['active_mode'] ) && '0' == $destination['active_mode'] ) {
	$active = false;
}

$port = '21';
if ( strstr( $ftp_server, ':' ) ) {
	$server_params = explode( ':', $ftp_server );
	$ftp_server    = $server_params[0];
	$port          = $server_params[1];
}

// Delete ftp backups.
if ( pb_backupbuddy::_POST( 'bulk_action' ) == 'delete_backup' ) {

	pb_backupbuddy::verify_nonce();

	$delete_count = 0;

	$delete_items = (array) pb_backupbuddy::_POST( 'items' );

	if ( ! empty( $delete_items ) ) {
		// Connect to server.
		if ( '1' == $ftps ) { // Connect with FTPs.
			if ( function_exists( 'ftp_ssl_connect' ) ) {
				$conn_id = ftp_ssl_connect( $ftp_server, $port );
				if ( false === $conn_id ) {
					pb_backupbuddy::status( 'details', __( 'Unable to connect to FTPS  (check address/FTPS support).', 'it-l10n-backupbuddy' ), 'error' );
					return false;
				} else {
					pb_backupbuddy::status( 'details', __( 'Connected to FTPs.', 'it-l10n-backupbuddy' ) );
				}
			} else {
				pb_backupbuddy::status( 'details', __( 'Your web server doesnt support FTPS in PHP.', 'it-l10n-backupbuddy' ), 'error' );
				return false;
			}
		} else { // Connect with FTP (normal).
			if ( function_exists( 'ftp_connect' ) ) {
				$conn_id = ftp_connect( $ftp_server, $port );
				if ( false === $conn_id ) {
					pb_backupbuddy::status( 'details', __( 'ERROR: Unable to connect to FTP (check address).', 'it-l10n-backupbuddy' ), 'error' );
					return false;
				} else {
					pb_backupbuddy::status( 'details', __( 'Connected to FTP.', 'it-l10n-backupbuddy' ) );
				}
			} else {
				pb_backupbuddy::status( 'details', __( 'Your web server doesnt support FTP in PHP.', 'it-l10n-backupbuddy' ), 'error' );
				return false;
			}
		}


		// login with username and password.
		$login_result = ftp_login( $conn_id, $ftp_username, $ftp_password );

		if ( $active === true ) {
			// do nothing, active is default.
			pb_backupbuddy::status( 'details', 'Active FTP mode based on settings.' );
		} elseif ( $active === false ) {
			// Turn passive mode on.
			pb_backupbuddy::status( 'details', 'Passive FTP mode based on settings.' );
			ftp_pasv( $conn_id, true );
		} else {
			pb_backupbuddy::status( 'error', 'Unknown FTP active/passive mode: `' . $active . '`.' );
		}

		ftp_chdir( $conn_id, (string) $ftp_directory );

		// Loop through and delete ftp backup files.
		foreach ( $delete_items as $backup ) {
			// Try to delete backup.
			if ( ftp_delete( $conn_id, $backup ) ) {
				$delete_count++;
			} else {
				pb_backupbuddy::alert( 'Unable to delete file `' . $destination['path'] . '/' . $backup . '`.' );
			}
		}

		// Close this connection.
		ftp_close( $conn_id );
	}

	if ( $delete_count > 0 ) {
		pb_backupbuddy::alert( sprintf( _n( 'Deleted %d file.', 'Deleted %d files.', $delete_count, 'it-l10n-backupbuddy' ), $delete_count ) );
	} else {
		pb_backupbuddy::alert( __('No backups were deleted.', 'it-l10n-backupbuddy' ) );
	}
	echo '<br>';
}

// Copy ftp backups to the local backup files.
if ( ! empty( $_GET['cpy_file'] ) ) {
	pb_backupbuddy::alert( 'The remote file is now being copied to your local backups. If the backup gets marked as bad during copying, please wait a bit then click the `Refresh` icon to rescan after the transfer is complete.' );
	echo '<br>';
	pb_backupbuddy::status( 'details',  'Scheduling Cron for creating ftp copy.' );
	backupbuddy_core::schedule_single_event( time(), 'process_ftp_copy', array( $_GET['cpy_file'], $ftp_server, $ftp_username, $ftp_password, $ftp_directory, $port, $ftps ) );

	if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
		update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
		spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
	}
}


// Connect to server.
if ( $ftps == '1' ) { // Connect with FTPs.
	if ( function_exists( 'ftp_ssl_connect' ) ) {
		$conn_id = ftp_ssl_connect( $ftp_server, $port );
		if ( $conn_id === false ) {
			pb_backupbuddy::status( 'details', 'Unable to connect to FTPS  `' . $ftp_server . '` on port `' . $port . '` (check address/FTPS support and that server can connect to this address via this port).', 'error' );
			return false;
		} else {
			pb_backupbuddy::status( 'details', 'Connected to FTPs.' );
		}
	} else {
		pb_backupbuddy::status( 'details', 'Your web server doesnt support FTPS in PHP.', 'error' );
		return false;
	}
} else { // Connect with FTP (normal).
	if ( function_exists( 'ftp_connect' ) ) {
		$conn_id = ftp_connect( $ftp_server, $port );
		if ( $conn_id === false ) {
			pb_backupbuddy::status( 'details', 'ERROR: Unable to connect to FTP server `' . $ftp_server . '` on port `' . $port . '` (check address and that server can connect to this address via this port).', 'error' );
			return false;
		} else {
			pb_backupbuddy::status( 'details', 'Connected to FTP.' );
		}
	} else {
		pb_backupbuddy::status( 'details', 'Your web server doesnt support FTP in PHP.', 'error' );
		return false;
	}
}


// Login with username and password.
$login_result = @ftp_login( $conn_id, $ftp_username, $ftp_password );
if ( false === $login_result ) {
	pb_backupbuddy::alert( 'Failure attempting to log in. PHP function ftp_login() returned false.' );
	die();
}

if ( $active === true ) {
	// do nothing, active is default.
	pb_backupbuddy::status( 'details', 'Active FTP mode based on settings.' );
} elseif ( $active === false ) {
	// Turn passive mode on.
	pb_backupbuddy::status( 'details', 'Passive FTP mode based on settings.' );
	ftp_pasv( $conn_id, true );
} else {
	pb_backupbuddy::status( 'error', 'Unknown FTP active/passive mode: `' . $active . '`.' );
}


// Get contents of the current directory.
ftp_chdir( $conn_id, $ftp_directory );
$contents = ftp_nlist( $conn_id, '' );

// Create array of backups and sizes.
$backups = array();
$backup_list = array();
$got_modified = false;
foreach ( $contents as $backup ) {
	// Check if file is backup.
	$pos = strpos( $backup, 'backup-' );
	if ( $pos !== FALSE ) {
		$backup_type = backupbuddy_core::getBackupTypeFromFile( $backup );
		if ( ! $backup_type ) {
			continue;
		}

		$mod_time = ftp_mdtm( $conn_id, $ftp_directory . $backup );
		if ( $mod_time > -1 ) {
			$got_modified = true;
		}
		$file_size = ftp_size( $conn_id, $ftp_directory . $backup );
		$backup_list[ $backup ] = array(
			$backup,
			pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $mod_time ) ) . '<br /><span class="description">(' . pb_backupbuddy::$format->time_ago( $mod_time ) . ' ago)</span>',
			pb_backupbuddy::$format->file_size( $file_size ),
			$backup_type,
		);

	}
}

// Close this connection.
ftp_close( $conn_id );


if ( $got_modified === true ) { // FTP server supports sorting by modified date.
	// Custom sort function for multidimension array usage.
	function backupbuddy_number_sort( $a,$b ) {
		return $a['modified']<$b['modified'];
	}
	// Sort by modified using custom sort function above.
	usort( $backups, 'backupbuddy_number_sort' );
}

$urlPrefix = pb_backupbuddy::ajax_url( 'remoteClient' ) . '&destination_id=' . htmlentities( pb_backupbuddy::_GET( 'destination_id' ) );

// Render table listing files.
if ( count( $backup_list ) == 0 ) {
	printf( '<strong>%s</strong>',
		esc_html__( 'You have not completed sending any backups to this FTP destination for this site yet.', 'it-l10n-backupbuddy' )
	);
} else {
	pb_backupbuddy::$ui->list_table(
		$backup_list,
		array(
			'action'		=>	$urlPrefix . '&remote_path=' . htmlentities( pb_backupbuddy::_GET( 'remote_path' ) ),
			'columns'		=>	array( 'Backup File', 'Uploaded <img src="' . pb_backupbuddy::plugin_url() . '/images/sort_down.png" style="vertical-align: 0px;" title="Sorted most recent first">', 'File Size', 'Type' ),
			//'hover_actions'	=>	array( 'copy' => 'Copy to Local' ),
			'hover_action_column_key'	=>	'0',
			'bulk_actions'	=>	array( 'delete_backup' => 'Delete' ),
			'css'			=>		'width: 100%;',
		)
	);
}
