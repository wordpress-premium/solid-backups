<?php
/**
 * Main destination file for FTP
 *
 * Note: DO NOT CALL THIS CLASS DIRECTLY. CALL VIA: pb_backupbuddy_destination in bootstrap.php.
 *
 * @package BackupBuddy
 */

/**
 * Destination class for FTP.
 */
class pb_backupbuddy_destination_ftp {

	/**
	 * Destination Properties
	 *
	 * @var array
	 */
	public static $destination_info = array(
		'name'        => 'FTP',
		'description' => 'File Transport Protocol. This is the most common way of sending larger files between servers. Most web hosting accounts provide FTP access. This common and well-known transfer method is tried and true.',
		'category'    => 'normal', // best, normal, legacy.
	);

	/**
	 * Default settings. Should be public static for auto-merging.
	 *
	 * @var array
	 */
	public static $default_settings = array(
		'type'                    => 'ftp',  // MUST MATCH your destination slug.
		'title'                   => '',     // Required destination field.
		'address'                 => '',
		'username'                => '',
		'password'                => '',
		'path'                    => '',
		'active_mode'             => 0,   // 1 = active, 0=passive mode (default > v3.1.8).
		'ftps'                    => 0,
		'archive_limit'           => 0,
		'url'                     => '',     // optional url for migration that corresponds to this ftp/path.
		'disable_file_management' => '0',        // When 1, _manage.php will not load which renders remote file management DISABLED.
		'disabled'                => '0',        // When 1, disable this destination.
	);

	/**
	 * Connect to FTP and return FTP connection.
	 *
	 * @param array $settings  Destination settings array.
	 * @param bool  $chdir     Automatically change directory.
	 *
	 * @return stream|false  FTP connection stream or false.
	 */
	public static function connect( $settings, $chdir = true ) {
		global $pb_backupbuddy_destination_errors;
		if ( '1' == $settings['disabled'] ) {
			$pb_backupbuddy_destination_errors[] = __( 'Error #48933: This destination is currently disabled. Enable it under this destination\'s Advanced Settings.', 'it-l10n-backupbuddy' );
			return false;
		}

		if ( ! $settings['address'] || ! $settings['username'] || ! $settings['password'] ) {
			$pb_backupbuddy_destination_errors[] = __( 'Missing required input.', 'it-l10n-backupbuddy' );
			return false;
		}

		$active_mode = ( '0' != $settings['active_mode'] );
		$server      = $settings['address'];
		$username    = $settings['username'];
		$password    = $settings['password'];
		$path        = $settings['path'];
		$ftps        = $settings['ftps'];
		$port        = '21'; // Default FTP port.

		if ( strstr( $server, ':' ) ) { // Handle custom FTP port.
			$server_params = explode( ':', $server );
			$server        = $server_params[0];
			$port          = $server_params[1];
		}

		// Connect to server.
		if ( '1' == $ftps ) { // Connect with FTPs.
			if ( function_exists( 'ftp_ssl_connect' ) ) {
				$conn_id = ftp_ssl_connect( $server, $port );
				if ( false === $conn_id ) {
					$pb_backupbuddy_destination_errors[] = __( 'Error #9040 Could not connect to destination using FTPs.', 'it-l10n-backupbuddy' );
					pb_backupbuddy::status( 'details', 'Unable to connect to FTPS  `' . $server . '` on port `' . $port . '` (check address/FTPS support and that server can connect to this address via this port).', 'error' );
					return false;
				} else {
					pb_backupbuddy::status( 'details', 'Connected to FTPs.' );
				}
			} else {
				$pb_backupbuddy_destination_errors[] = __( 'Error #9041 FTPs with PHP is unsupported by your web server.', 'it-l10n-backupbuddy' );
				pb_backupbuddy::status( 'details', 'Your web server doesnt support FTPS in PHP.', 'error' );
				return false;
			}
		} else { // Connect with FTP (normal).
			if ( function_exists( 'ftp_connect' ) ) {
				$conn_id = ftp_connect( $server, $port );
				if ( false === $conn_id ) {
					$pb_backupbuddy_destination_errors[] = __( 'Error #9050 Could not connect to destination using FTP.', 'it-l10n-backupbuddy' );
					pb_backupbuddy::status( 'details', 'ERROR: Unable to connect to FTP server `' . $server . '` on port `' . $port . '` (check address and that server can connect to this address via this port).', 'error' );
					return false;
				} else {
					pb_backupbuddy::status( 'details', 'Connected to FTP.' );
				}
			} else {
				$pb_backupbuddy_destination_errors[] = __( 'Error #9051 FTP with PHP is unsupported by your web server.', 'it-l10n-backupbuddy' );
				pb_backupbuddy::status( 'details', 'Your web server doesnt support FTP in PHP.', 'error' );
				return false;
			}
		}

		// Log in.
		$login_result = @ftp_login( $conn_id, $username, $password );
		if ( false === $login_result ) {
			backupbuddy_core::mail_error( 'ERROR #9011 ( https://ithemeshelp.zendesk.com/hc/en-us/articles/211132377-Error-Codes-#9011 ).  FTP/FTPs login failed on scheduled FTP.' );
			return false;
		} else {
			pb_backupbuddy::status( 'details', 'Logged in successfully to FTP Destination.' );
		}

		if ( false === $active_mode ) {
			// Turn passive mode on.
			pb_backupbuddy::status( 'details', 'Passive FTP mode based on settings.' );
			ftp_pasv( $conn_id, true );
		} else {
			pb_backupbuddy::status( 'details', 'Active FTP mode based on settings.' );
		}

		if ( $chdir && $path && ! self::chdir( $conn_id, $path ) ) {
			pb_backupbuddy::status( 'error', __( 'Could not change FTP directory.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		return $conn_id;
	}

	/**
	 * Change directory for FTP connection.
	 *
	 * @param stream $conn_id  FTP Stream.
	 * @param string $path  Path to change to.
	 *
	 * @return bool  If directory changed.
	 */
	public static function chdir( $conn_id, $path ) {
		if ( ! $conn_id || ! $path ) {
			return false;
		}
		// Create directory if it does not exist.
		pb_backupbuddy::status( 'details', 'Creating FTP directory `' . $path . '` if not exists.' );
		@ftp_mkdir( $conn_id, $path );

		// Change to directory.
		pb_backupbuddy::status( 'details', 'Attempting to change into directory...' );
		if ( true === ftp_chdir( $conn_id, $path ) ) {
			pb_backupbuddy::status( 'details', 'Changed into directory.' );
			return true;
		}
		pb_backupbuddy::status( 'error', __( 'Unable to change into specified path. Verify the path is correct with valid permissions.', 'it-l10n-backupbuddy' ) );
		return false;
	}

	/**
	 * Get Remote file size.
	 *
	 * @param array  $settings  Destination Settings array.
	 * @param string $file      Filename.
	 * @param stream $conn_id   FTP connection stream.
	 *
	 * @return bool|int  Size of file.
	 */
	public static function get_size( $settings, $file, $conn_id = false ) {
		if ( false === $conn_id ) {
			$conn_id = self::connect( $settings );
		}
		if ( ! $conn_id ) {
			return false;
		}

		$size = @ftp_size( $conn_id, $file );
		if ( -1 === $size ) {
			return false;
		}
		return $size;
	}

	/**
	 * Check if remote file exists.
	 *
	 * @param array  $settings  Destination Settings array.
	 * @param string $file      Filename.
	 * @param stream $conn_id   FTP connection stream.
	 *
	 * @return bool  Check if remote file exists.
	 */
	public static function remote_file_exists( $settings, $file, $conn_id = false ) {
		return ( false !== self::get_size( $settings, $file, $conn_id ) );
	}

	/**
	 * Download backup file to local.
	 *
	 * @param array  $settings          Destination ettings array.
	 * @param string $remote_file       Remote filename to get.
	 * @param string $destination_file  Local filename to move to.
	 *
	 * @return bool  If successful or not.
	 */
	public static function getFile( $settings, $remote_file, $destination_file = false ) {
		$conn_id = self::connect( $settings );

		if ( ! $conn_id ) {
			return false;
		}

		if ( ! self::remote_file_exists( $settings, $remote_file, $conn_id ) ) {
			return false;
		}

		if ( ! $destination_file ) {
			$destination_file = backupbuddy_core::getBackupDirectory() . basename( $remote_file );
		}

		if ( ! ftp_get( $conn_id, $destination_file, $remote_file, FTP_BINARY ) ) {
			pb_backupbuddy::status( 'error', __( 'Unable to get FTP file.', 'it-l10n-backupbuddy' ) );
			ftp_close( $conn_id );
			return false;
		}

		pb_backupbuddy::status( 'message', 'Successfully wrote remote file locally to `' . $destination_file . '`.' );

		// Grab dat file too.
		if ( '.zip' === substr( $remote_file, -4 ) ) {
			$dat = str_replace( '.zip', '.dat', $remote_file );
			if ( self::remote_file_exists( $settings, $dat, $conn_id ) ) {
				$local_dat = backupbuddy_core::getBackupDirectory() . $dat;
				if ( ! file_exists( $local_dat ) ) {
					ftp_get( $conn_id, $dat, $local_dat, FTP_BINARY );
				}
			}
		}

		ftp_close( $conn_id );

		return true;
	}

	/**
	 * Download all backup dat files.
	 *
	 * @param array $settings  Destination Settings array.
	 *
	 * @return bool  If successful or not.
	 */
	public static function download_dat_files( $settings ) {
		$backups = self::listFiles( $settings );
		if ( ! count( $backups ) ) {
			return false;
		}
		$success = true;
		foreach ( $backups as $backup ) {
			$dat_file = str_replace( '.zip', '.dat', $backup[0][0] );

			if ( ! self::getFile( $settings, basename( $dat_file ) ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Download file from FTP.
	 *
	 * @param array  $settings  Destination settings array.
	 * @param string $file      File to download.
	 */
	public static function stream_download( $settings, $file ) {
		$conn_id = self::connect( $settings );

		if ( ! $conn_id ) {
			return false;
		}

		if ( ! self::remote_file_exists( $settings, $file, $conn_id ) ) {
			return false;
		}

		$handle = fopen( 'php://temp', 'r+' );
		ftp_fget( $conn_id, $handle, $file, FTP_BINARY );
		ftp_close( $conn_id );

		rewind( $handle );
		$fstats = fstat( $handle );

		flush();
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename=' . basename( $file ) );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Accept-Ranges: bytes' );
		header( 'Expires: 0' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . $fstats['size'] );

		set_time_limit( 3600 );

		echo stream_get_contents( $handle );
		fclose( $handle );
		exit();
	}

	/**
	 * List Backup Files array.
	 *
	 * @param array  $settings  Destination settings array.
	 * @param string $mode      List mode.
	 *
	 * @return array  Array of backups.
	 */
	public static function listFiles( $settings = array(), $mode = 'default' ) {
		global $pb_backupbuddy_destination_errors;
		if ( '1' == $settings['disabled'] ) {
			$pb_backupbuddy_destination_errors[] = __( 'Error #48933: This destination is currently disabled. Enable it under this destination\'s Advanced Settings.', 'it-l10n-backupbuddy' );
			return false;
		}

		$conn_id = self::connect( $settings );
		if ( ! $conn_id ) {
			pb_backupbuddy::status( 'error', __( 'Unable to list sFTP files. sFTP connection failed.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		$backups = ftp_nlist( $conn_id, '' );

		$backup_list       = array();
		$backup_sort_dates = array();

		foreach ( $backups as $backup ) {
			if ( false === stristr( $backup, 'backup-' ) ) { // only show backup files.
				continue;
			}

			$backup_type = backupbuddy_core::getBackupTypeFromFile( basename( $backup ) );

			if ( ! $backup_type ) {
				continue;
			}

			$backup_date   = backupbuddy_core::parse_file( $backup, 'datetime' );
			$backup_size   = self::get_size( $settings, $backup, $conn_id );
			$download_link = admin_url() . sprintf( '?ftp-destination-id=%s&ftp-download=%s', backupbuddy_backups()->get_destination_id(), rawurlencode( $backup ) );

			$backup_array = array(
				array(
					$backup,
					$backup_date,
					$download_link,
				),
				backupbuddy_core::pretty_backup_type( $backup_type ),
				pb_backupbuddy::$format->file_size( $backup_size ),
			);

			if ( 'default' === $mode ) {
				$copy_link      = '&cpy=' . rawurlencode( $backup );
				$actions        = array(
					$download_link => __( 'Download Backup', 'it-l10n-backupbuddy' ),
					$copy_link     => __( 'Copy to Local', 'it-l10n-backupbuddy' ),
				);
				$backup_array[] = backupbuddy_backups()->get_action_menu( $backup, $actions );
			} elseif ( 'restore' === $mode ) {
				$backup_array[] = backupbuddy_backups()->get_details_link( $backup );
				$backup_array[] = backupbuddy_backups()->get_restore_buttons( $backup, $backup_type );
			}

			$backup_list[ basename( $backup ) ]       = $backup_array;
			$backup_sort_dates[ basename( $backup ) ] = $backup_date;
		}

		ftp_close( $conn_id );

		$backup_list = backupbuddy_backups()->sort_backups( $backup_list, $backup_sort_dates );

		return $backup_list;
	}

	/**
	 * Send one or more files via FTP
	 *
	 * @param array  $settings  Destination settings array.
	 * @param array  $files     Array of one or more files to send.
	 * @param string $send_id   Send ID.
	 *
	 * @return bool  If sent successfully.
	 */
	public static function send( $settings = array(), $files = array(), $send_id = '' ) {
		pb_backupbuddy::status( 'details', 'FTP class send() function started.' );

		$conn_id = self::connect( $settings );

		if ( ! $conn_id ) {
			return false;
		}

		$path = $settings['path'];

		if ( ! is_array( $files ) ) {
			$files = array( $files );
		}

		pb_backupbuddy::status( 'details', 'Sending backup via FTP/FTPs...' );

		// Upload files.
		$total_transfer_size = 0;
		$total_transfer_time = 0;
		foreach ( $files as $file ) {
			if ( ! file_exists( $file ) ) {
				pb_backupbuddy::status( 'error', 'Error #859485495. Could not upload local file `' . $file . '` to send to FTP as it does not exist. Verify the file exists, permissions of file, parent directory, and that ownership is correct. You may need suphp installed on the server.' );
			}
			if ( ! is_readable( $file ) ) {
				pb_backupbuddy::status( 'error', 'Error #8594846548. Could not read local file `' . $file . '` to sendto FTP as it is not readable. Verify permissions of file, parent directory, and that ownership is correct. You may need suphp installed on the server.' );
			}

			$filesize             = filesize( $file );
			$total_transfer_size += $filesize;

			$destination_file = basename( $file ); // Using chdir() so path not needed. $path . '/' . basename( $file );
			pb_backupbuddy::status( 'details', 'About to put to FTP local file `' . $file . '` of size `' . pb_backupbuddy::$format->file_size( $filesize ) . '` to remote file `' . $destination_file . '`.' );
			$send_time            = -microtime( true );
			$upload               = ftp_put( $conn_id, $destination_file, $file, FTP_BINARY );
			$send_time           += microtime( true );
			$total_transfer_time += $send_time;
			if ( false === $upload ) {
				$error_message = 'ERROR #9012 ( https://ithemeshelp.zendesk.com/hc/en-us/articles/211132377-Error-Codes-#9012 ).  FTP/FTPs file upload failed. Check file permissions & disk quota.';
				pb_backupbuddy::status( 'error', $error_message );
				backupbuddy_core::mail_error( $error_message );

				return false;
			} else {
				pb_backupbuddy::status( 'details', 'Success completely sending `' . basename( $file ) . '` to destination.' );
				self::prune( $settings, $conn_id );
			}
		} // end $files loop.

		// Load destination fileoptions.
		pb_backupbuddy::status( 'details', 'About to load fileoptions data.' );
		require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
		pb_backupbuddy::status( 'details', 'Fileoptions instance #13.' );
		$fileoptions_obj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt', false, false, false );
		$result          = $fileoptions_obj->is_ok();
		if ( true !== $result ) {
			pb_backupbuddy::status( 'error', __( 'Fatal Error #9034.84838. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
			return false;
		}
		pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );
		$fileoptions = &$fileoptions_obj->options;

		// Save stats.
		$fileoptions['write_speed'] = $total_transfer_size / $total_transfer_time;
		// $fileoptions['finish_time'] = time();
		// $fileoptions['status'] = 'success';
		$fileoptions_obj->save();
		unset( $fileoptions_obj );

		ftp_close( $conn_id );

		return true;

	} // send.

	/**
	 * Prune backups when limit is reached.
	 *
	 * @param array  $settings  Destination Settings array.
	 * @param stream $conn_id   FTP Connection ID resource.
	 *
	 * @return bool  If prune successful.
	 */
	public static function prune( $settings, $conn_id = false ) {
		if ( false === $conn_id ) {
			$conn_id = self::connect( $settings );
		}
		if ( ! $conn_id ) {
			return false;
		}

		$limit = $settings['archive_limit'];

		if ( $limit <= 0 ) {
			pb_backupbuddy::status( 'details', 'No FTP file limit to enforce.' );
			return false;
		}

		// Start remote backup limit.
		pb_backupbuddy::status( 'details', 'Getting contents of backup directory.' );
		$contents = ftp_nlist( $conn_id, '' );

		// Create array of backups.
		$bkupprefix = backupbuddy_core::backup_prefix();

		$backups = array();
		foreach ( $contents as $backup ) {
			// check if file is backup.
			if ( false !== strpos( $backup, 'backup-' . $bkupprefix . '-' ) ) {
				array_push( $backups, $backup );
			}
		}
		arsort( $backups ); // some ftp servers seem to not report in proper order so reversing insufficiently reliable. need to reverse sort by filename. array_reverse( (array)$backups );

		if ( count( $backups ) > $limit ) {
			$delete_fail_count = 0;
			$i                 = 0;
			foreach ( $backups as $backup ) {
				$i++;
				if ( $i > $limit ) {
					if ( ! ftp_delete( $conn_id, $backup ) ) {
						pb_backupbuddy::status( 'details', 'Unable to delete excess FTP file `' . $backup . '` in path `' . $path . '`.' );
						$delete_fail_count++;
					}
				}
			}
			if ( 0 !== $delete_fail_count ) {
				backupbuddy_core::mail_error( sprintf( __( 'FTP remote limit could not delete %s backups. Please check and verify file permissions.', 'it-l10n-backupbuddy' ), $delete_fail_count ) );
			}
		}
		// End remote backup limit.

		return true;
	}

	/**
	 * Test Connection/Send.
	 *
	 * @param array $settings  Destination Settings array.
	 *
	 * @return bool|string  True on success, string error message on failure.
	 */
	public static function test( $settings ) {
		// Try sending a file.
		$send_id       = 'TEST-' . pb_backupbuddy::random_string( 12 );
		$send_response = pb_backupbuddy_destinations::send( $settings, pb_backupbuddy::plugin_path() . '/destinations/remote-send-test.php', $send_id ); // 3rd param true forces clearing of any current uploads.
		if ( false === $send_response ) {
			$send_response = 'Error sending test file to FTP.';
		} else {
			$send_response = 'Success.';
		}

		// Now we will need to go and cleanup this potentially uploaded file.
		$delete_response = 'Error deleting test file from FTP.'; // Default.

		// Delete test file.
		pb_backupbuddy::status( 'details', 'FTP test: Deleting temp test file.' );
		if ( true === self::delete( $settings, $path . '/remote-send-test.php', $conn_id ) ) {
			$delete_response = 'Success.';
		}

		// Close FTP connection.
		pb_backupbuddy::status( 'details', 'FTP test: Closing FTP connection.' );
		@ftp_close( $conn_id );

		// Load destination fileoptions.
		pb_backupbuddy::status( 'details', 'About to load fileoptions data.' );
		require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
		pb_backupbuddy::status( 'details', 'Fileoptions instance #12.' );
		$fileoptions_obj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt', false, false, false );
		$result          = $fileoptions_obj->is_ok();
		if ( true !== $result ) {
			pb_backupbuddy::status( 'error', __( 'Fatal Error #9034.72373. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
			return false;
		}
		pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );
		$fileoptions = &$fileoptions_obj->options;

		if ( 'Success.' != $send_response || 'Success.' != $delete_response ) {
			$fileoptions['status'] = 'failure';

			$fileoptions_obj->save();
			unset( $fileoptions_obj );

			return 'Send details: `' . $send_response . '`. Delete details: `' . $delete_response . '`.';
		} else {
			$fileoptions['status']      = 'success';
			$fileoptions['finish_time'] = microtime( true );
		}

		$fileoptions_obj->save();
		unset( $fileoptions_obj );

		return true;

	} // test.

	/**
	 * Delete a remote file.
	 *
	 * @param array  $settings  Destination Settings array.
	 * @param string $file      File to delete.
	 * @param stream $conn_id   FTP connection resource.
	 *
	 * @return bool  If delete successful.
	 */
	public static function delete( $settings, $file, $conn_id = false ) {
		if ( false === $conn_id ) {
			$conn_id = self::connect( $settings );
		}
		if ( ! $conn_id ) {
			return false;
		}

		return ftp_delete( $conn_id, $file );
	}

} // End class.
