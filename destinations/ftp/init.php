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
	 * FTP connection instance.
	 *
	 * @var stream
	 */
	public static $conn_id = false;

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

		if ( false !== self::$conn_id ) {
			return self::$conn_id;
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

		self::$conn_id = $conn_id;

		if ( $chdir && $path && ! self::chdir( $path ) ) {
			pb_backupbuddy::status( 'error', __( 'Could not change FTP directory.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		return self::$conn_id;
	}

	/**
	 * Change directory for FTP connection.
	 *
	 * @param string $path  Path to change to.
	 *
	 * @return bool  If directory changed.
	 */
	public static function chdir( $path ) {
		if ( ! self::$conn_id || ! $path ) {
			return false;
		}

		// Create directory if it does not exist.
		pb_backupbuddy::status( 'details', 'Creating FTP directory `' . $path . '` if not exists.' );
		@ftp_mkdir( self::$conn_id, $path );

		// Change to directory.
		pb_backupbuddy::status( 'details', 'Attempting to change into directory...' );
		if ( true === ftp_chdir( self::$conn_id, $path ) ) {
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
	 *
	 * @return bool|int  Size of file.
	 */
	public static function get_size( $settings, $file ) {
		if ( false === self::$conn_id ) {
			self::connect( $settings );
		}

		if ( ! self::$conn_id ) {
			return false;
		}

		$size = ftp_size( self::$conn_id, $file );

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
	 *
	 * @return bool  Check if remote file exists.
	 */
	public static function remote_file_exists( $settings, $file ) {
		return ( false !== self::get_size( $settings, $file ) );
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
		if ( false === self::$conn_id ) {
			self::connect( $settings );
		}

		if ( ! self::$conn_id ) {
			pb_backupbuddy::status( 'error', __( 'Could not connect to FTP server to getFile.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		if ( ! self::remote_file_exists( $settings, $remote_file ) ) {
			pb_backupbuddy::status( 'error', __( 'FTP Remote file does not exist: ', 'it-l10n-backupbuddy' ) . $remote_file . '.' );
			return false;
		}

		if ( ! $destination_file ) {
			$destination_file = backupbuddy_core::getBackupDirectory() . basename( $remote_file );
		}

		if ( ! ftp_get( self::$conn_id, $destination_file, $remote_file, FTP_BINARY ) ) {
			pb_backupbuddy::status( 'error', __( 'Unable to get FTP file.', 'it-l10n-backupbuddy' ) );
			self::close();
			return false;
		}

		pb_backupbuddy::status( 'message', 'Successfully wrote remote file locally to `' . esc_attr( $destination_file ) . '`.' );

		// Grab dat file too.
		if ( '.zip' === substr( $remote_file, -4 ) ) {
			$dat = str_replace( '.zip', '.dat', $remote_file ); // TODO: Move to backupbuddy_data_file() method.
			if ( self::remote_file_exists( $settings, $dat ) ) {
				$local_dat = backupbuddy_core::getBackupDirectory() . $dat;
				if ( ! file_exists( $local_dat ) ) {
					ftp_get( self::$conn_id, $dat, $local_dat, FTP_BINARY );
				}
			}
		}

		pb_backupbuddy::status( 'details', 'FTP getFile: Closing FTP connection.' );
		self::close();

		return true;
	}

	/**
	 * Close the FTP connection.
	 */
	public static function close() {
		ftp_close( self::$conn_id );
		self::$conn_id = false;
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
			$dat_file = str_replace( '.zip', '.dat', $backup[0][0] ); // TODO: Move to backupbuddy_data_file() method.

			if ( ! self::getFile( $settings, basename( $dat_file ) ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Get a list of dat files not associated with backups.
	 *
	 * @param array $settings  Destination Settings array.
	 *
	 * @return array  Array of dat files.
	 */
	public static function get_dat_orphans( $settings ) {
		$backups_array = self::listFiles( $settings );
		if ( ! is_array( $backups_array ) ) {
			return false;
		}

		if ( false === self::$conn_id ) {
			self::connect( $settings );
		}

		if ( ! self::$conn_id ) {
			return false;
		}

		$orphans = array();
		$backups = array();
		$files   = self::get_folder_contents();

		// Create an array of backup filenames.
		foreach ( $backups_array as $backup_array ) {
			$backups[] = $backup_array[0][0];
		}

		// Loop through all files again, this time looking for dat orphans.
		foreach ( $files as $file ) {
			if ( in_array( $file, array( '.', '..' ), true ) ) {
				continue;
			}

			// Skip if not a .dat file.
			if ( '.dat' !== substr( $file, -4 ) ) {
				continue;
			}

			// Skip dat files with backup files.
			$backup_name = str_replace( '.dat', '.zip', $file );
			if ( in_array( $backup_name, $backups, true ) ) {
				continue;
			}

			$orphans[] = $file;
		}

		return $orphans;
	}

	/**
	 * Download file from FTP.
	 *
	 * @param array  $settings  Destination settings array.
	 * @param string $file      File to download.
	 */
	public static function stream_download( $settings, $file ) {
		if ( false === self::$conn_id ) {
			self::connect( $settings );
		}

		if ( ! self::$conn_id ) {
			return false;
		}

		if ( ! self::remote_file_exists( $settings, $file ) ) {
			return false;
		}

		$handle = fopen( 'php://temp', 'r+' );
		ftp_fget( self::$conn_id, $handle, $file, FTP_BINARY );

		pb_backupbuddy::status( 'details', 'FTP download: Closing FTP connection.' );
		self::close();

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
	 * Get contents of a folder.
	 *
	 * @param string $folder  Folder path.
	 *
	 * @return array  Array of files.
	 */
	public static function get_folder_contents( $folder = '' ) {
		if ( ! self::$conn_id ) {
			pb_backupbuddy::status( 'error', __( 'FTP get_folder_contents was called before successful FTP connection was made.', 'it-l10n-backupbuddy' ) );
			return false;
		}
		return ftp_nlist( self::$conn_id, $folder );
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

		if ( false === self::$conn_id ) {
			self::connect( $settings );
		}

		if ( ! self::$conn_id ) {
			pb_backupbuddy::status( 'error', __( 'Unable to list FTP files. FTP connection failed.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		$files = self::get_folder_contents();

		$backup_list       = array();
		$backup_sort_dates = array();

		foreach ( $files as $file ) {
			if ( false === stristr( $file, 'backup-' ) ) { // only show backup files.
				continue;
			}

			// This checks to make sure it's a zip.
			$backup_type = backupbuddy_core::getBackupTypeFromFile( basename( $file ) );

			if ( ! $backup_type ) {
				continue;
			}

			$backup        = $file;
			$backup_date   = backupbuddy_core::parse_file( $backup, 'datetime' );
			$backup_size   = self::get_size( $settings, $backup );
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

		pb_backupbuddy::status( 'details', 'FTP listFiles: Closing FTP connection.' );
		self::close();

		$backup_list = backupbuddy_backups()->sort_backups( $backup_list, $backup_sort_dates );

		return $backup_list;
	}

	/**
	 * Send one or more files via FTP
	 *
	 * @param array  $settings             Destination settings array.
	 * @param array  $files                Array of one or more files to send.
	 * @param string $send_id              Send ID.
	 * @param bool   $delete_after         Delete file after.
	 * @param bool   $delete_remote_after  Delete remote file after (for tests).
	 *
	 * @return bool  If sent successfully.
	 */
	public static function send( $settings = array(), $files = array(), $send_id = '', $delete_after = false, $delete_remote_after = false ) {
		pb_backupbuddy::status( 'details', 'FTP class send() function started.' );

		if ( false === self::$conn_id ) {
			self::connect( $settings );
		}

		if ( ! self::$conn_id ) {
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
		$start_time          = time();

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
			pb_backupbuddy::status( 'details', 'About to put to FTP local file `' . $file . '` of size `' . pb_backupbuddy::$format->file_size( $filesize ) . '` to remote file `' . esc_attr( $destination_file ) . '`.' );
			$send_time            = -microtime( true );
			$upload               = ftp_put( self::$conn_id, $destination_file, $file, FTP_BINARY );
			$send_time           += microtime( true );
			$total_transfer_time += $send_time;
			if ( false === $upload ) {
				$error_message = 'ERROR #9012 ( https://ithemeshelp.zendesk.com/hc/en-us/articles/211132377-Error-Codes-#9012 ).  FTP/FTPs file upload failed. Check file permissions & disk quota.';
				pb_backupbuddy::status( 'error', $error_message );
				backupbuddy_core::mail_error( $error_message );

				return false;
			} else {
				pb_backupbuddy::status( 'details', 'Success completely sending `' . esc_attr( $destination_file ) . '` to destination.' );
			}

			if ( $delete_remote_after ) {
				pb_backupbuddy::status( 'details', 'Deleting `' . esc_attr( $destination_file ) . '` per `delete_remote_after` parameter.' );
				self::delete( $settings, $destination_file );
			}

			self::prune( $settings );
		} // end $files loop.

		// Load destination fileoptions.
		pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #13...' );
		require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
		$fileoptions_obj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt', false, false, true );
		$result          = $fileoptions_obj->is_ok();
		if ( true !== $result ) {
			pb_backupbuddy::status( 'error', __( 'Fatal Error #9034.84838. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
		} else {
			pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );
			$fileoptions = &$fileoptions_obj->options;

			// Save stats.
			$fileoptions['write_speed'] = $total_transfer_size / $total_transfer_time;
			$fileoptions['start_time']  = $start_time;
			$fileoptions['finish_time'] = time();
			$fileoptions['status']      = 'success';
			$fileoptions_obj->save();
		}
		unset( $fileoptions_obj );

		pb_backupbuddy::status( 'details', 'FTP send: Closing FTP connection.' );
		self::close();

		return true;

	} // send.

	/**
	 * Prune backups when limit is reached.
	 *
	 * @param array $settings  Destination Settings array.
	 *
	 * @return bool  If prune successful.
	 */
	public static function prune( $settings ) {
		if ( false === self::$conn_id ) {
			self::connect( $settings );
		}
		if ( ! self::$conn_id ) {
			return false;
		}

		$limit = $settings['archive_limit'];

		if ( $limit <= 0 ) {
			pb_backupbuddy::status( 'details', 'No FTP file limit to enforce.' );
			return false;
		}

		// Start remote backup limit.
		pb_backupbuddy::status( 'details', 'Getting contents of backup directory.' );
		$contents = ftp_nlist( self::$conn_id, '' );

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

			if ( ! class_exists( 'pb_backupbuddy_destinations' ) ) {
				require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
			}

			foreach ( $backups as $backup ) {
				$i++;
				if ( $i > $limit ) {
					if ( ! pb_backupbuddy_destinations::delete( $settings, $backup ) ) {
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
		$send_id   = 'TEST-' . pb_backupbuddy::random_string( 12 );
		$test_file = pb_backupbuddy::plugin_path() . '/destinations/remote-send-test.php';

		if ( true !== self::send( $settings, $test_file, $send_id, false, true ) ) { // 3rd param true forces clearing of any current uploads.
			self::delete( $settings, basename( $test_file ) ); // In case partial file upload occurred.
			return false;
		}

		return true;

	} // test.

	/**
	 * Delete a remote file.
	 *
	 * @param array $settings  Destination Settings array.
	 * @param array $files     Files to delete.
	 *
	 * @return bool  If delete successful.
	 */
	public static function delete( $settings, $files = array() ) {
		if ( false === self::$conn_id ) {
			self::connect( $settings );
		}

		if ( ! self::$conn_id ) {
			return false;
		}

		if ( ! is_array( $files ) ) {
			$files = array( $files );
		}

		foreach ( $files as $file ) {
			if ( ! self::remote_file_exists( $settings, $file ) ) {
				continue;
			}
			if ( ! ftp_delete( self::$conn_id, $file ) ) {
				return false;
			}
		}

		return true;
	}

} // End class.
