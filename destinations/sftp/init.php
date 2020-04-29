<?php
/**
 * Main destination file for sFTP
 *
 * Note: DO NOT CALL THIS CLASS DIRECTLY. CALL VIA: pb_backupbuddy_destination in bootstrap.php.
 *
 * @package BackupBuddy
 */

/**
 * Destination class for sFTP.
 */
class pb_backupbuddy_destination_sftp {

	/**
	 * Destination Properties
	 *
	 * @var array
	 */
	public static $destination_info = array(
		'name'        => 'sFTP',
		'description' => 'Secure File Transport Protocol (over SSH) is a more secure way of sending files between servers than FTP by using SSH. Web hosting accounts are more frequently providing this feature for greater security. This implementation is fully in PHP so PHP memory limits may be a limiting factor on some servers.',
		'category'    => 'best', // best, normal, legacy.
	);

	/**
	 * Default settings. Should be public static for auto-merging.
	 *
	 * @var array
	 */
	public static $default_settings = array(
		'type'                    => 'sftp', // MUST MATCH your destination slug.
		'title'                   => '', // Required destination field.
		'address'                 => '',
		'username'                => '',
		'password'                => '',
		'path'                    => '',
		'archive_limit'           => 0,
		'url'                     => '', // optional url for migration that corresponds to this sftp/path.
		'disable_file_management' => '0', // When 1, _manage.php will not load which renders remote file management DISABLED.
		'disabled'                => '0', // When 1, disable this destination.
	);

	/**
	 * Initialize.
	 */
	public static function _init() {
		if ( ! class_exists( 'Net_SFTP' ) ) {
			set_include_path( get_include_path() . PATH_SEPARATOR . pb_backupbuddy::plugin_path() . '/destinations/sftp/lib/phpseclib' );
			require_once pb_backupbuddy::plugin_path() . '/destinations/sftp/lib/phpseclib/Net/SFTP.php';
		}

		// Try to include phpseclib's version of Blowfish to avoid conflicts with PEAR version.
		if ( ! class_exists( 'Crypt_Blowfish' ) ) {
			require_once pb_backupbuddy::plugin_path() . '/destinations/sftp/lib/phpseclib/Crypt/Blowfish.php';
		}

		// Crank up logging level if in debug mode.
		if ( '3' == pb_backupbuddy::$options['log_level'] && ! defined( 'NET_SFTP_LOGGING' ) ) {
			define( 'NET_SFTP_LOGGING', NET_SFTP_LOG_COMPLEX );
		}
	} // end _init().

	/**
	 * Get password or sFTP key.
	 *
	 * @param array $settings  Settings array.
	 *
	 * @return string  sFTP Password or Key.
	 */
	public static function get_pass_or_key( $settings = array() ) {
		$key        = '';
		$upload_dir = wp_upload_dir();
		$key_file   = $upload_dir['basedir'] . '/backupbuddy-sftp-key-' . pb_backupbuddy::$options['log_serial'] . '.txt';
		if ( file_exists( $key_file ) ) {
			pb_backupbuddy::status( 'details', 'Key file found at `' . $key_file . '`.' );
			$key = file_get_contents( $key_file );
			if ( false === $key ) {
				pb_backupbuddy::status( 'error', 'Error #4839493843: Unable to read key file contents from `' . $key_file . '`.' );
				$key = '';
			} else {
				pb_backupbuddy::status( 'details', 'Loaded key file.' );
			}
		} else {
			pb_backupbuddy::status( 'details', 'No key file found at `' . $key_file . '`. Using password only.' );
		}

		if ( '' !== $key ) { // Use key file.
			pb_backupbuddy::status( 'details', 'Using contents of key file `' . $key_file . '`.' );
			$pass_or_key = $key;
			require_once pb_backupbuddy::plugin_path() . '/destinations/sftp/lib/phpseclib/Crypt/RSA.php';
			$crypt = new Crypt_RSA();
			if ( '' != $settings['password'] ) {
				$crypt->setPassword( $settings['password'] );
			}
			$pass_or_key = $crypt->loadKey( $key );
			global $backupbuddy_sftp_using_key_file;
			$backupbuddy_sftp_using_key_file = true;
		} else { // Normal password.
			pb_backupbuddy::status( 'details', 'Using password.' );
			$pass_or_key = $settings['password'];
		}

		return $pass_or_key;
	}

	/**
	 * Connect to sFTP and return sFTP object.
	 *
	 * @param array $settings  Destination settings array.
	 * @param bool  $chdir     Automatically change directory.
	 *
	 * @return object|false  sFTP object or false on failure.
	 */
	public static function connect( $settings, $chdir = true ) {
		// Connect to server.
		self::_init();
		$server = $settings['address'];
		$port   = '22'; // Default sFTP port.
		if ( strstr( $server, ':' ) ) { // Handle custom sFTP port.
			$server_params = explode( ':', $server );
			$server        = $server_params[0];
			$port          = $server_params[1];
		}

		$pass_or_key = self::get_pass_or_key( $settings );

		pb_backupbuddy::status( 'details', 'Connecting to sFTP server...' );
		$sftp = new Net_SFTP( $server, $port );

		if ( ! $sftp->login( $settings['username'], $pass_or_key ) ) {
			pb_backupbuddy::status( 'error', __( 'Connection to sFTP server failed.', 'it-l10n-backupbuddy' ) );
			pb_backupbuddy::status( 'details', 'sFTP log (if available & enabled via full logging mode): `' . $sftp->getSFTPLog() . '`.' );
			return false;
		}

		if ( $chdir && $settings['path'] && ! self::chdir( $sftp, $settings['path'] ) ) {
			pb_backupbuddy::status( 'error', __( 'Could not change sFTP directory.', 'it-l10n-backupbuddy' ) );
			pb_backupbuddy::status( 'details', 'sFTP log (if available & enabled via full logging mode): `' . $sftp->getSFTPLog() . '`.' );
			return false;
		}

		pb_backupbuddy::status( 'details', 'Success connecting to sFTP server.' );
		return $sftp;
	}

	/**
	 * Change directory for sFTP connection.
	 *
	 * @param object $sftp  sFTP Object.
	 * @param string $path  Path to change to.
	 *
	 * @return bool  If directory changed.
	 */
	public static function chdir( $sftp, $path ) {
		// Change to directory.
		pb_backupbuddy::status( 'details', 'Attempting to change into directory...' );
		if ( true === $sftp->chdir( $path ) ) {
			pb_backupbuddy::status( 'details', 'Changed into directory.' );
			return true;
		}
		pb_backupbuddy::status( 'error', __( 'Unable to change into specified path. Verify the path is correct with valid permissions.', 'it-l10n-backupbuddy' ) );
		pb_backupbuddy::status( 'details', 'sFTP log (if available & enabled via full logging mode): `' . $sftp->getSFTPLog() . '`.' );
		return false;
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

		$sftp = self::connect( $settings );
		if ( ! $sftp ) {
			pb_backupbuddy::status( 'error', __( 'Unable to list sFTP files. sFTP connection failed.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		$backups           = $sftp->rawlist( $settings['path'] );
		$backup_list       = array();
		$backup_sort_dates = array();

		foreach ( $backups as $filename => $file ) {
			if ( false === stristr( $filename, 'backup-' ) ) { // only show backup files.
				continue;
			}

			$backup = $filename;

			$backup_type = backupbuddy_core::getBackupTypeFromFile( basename( $backup ) );

			if ( ! $backup_type ) {
				continue;
			}

			$backup_date   = backupbuddy_core::parse_file( $backup, 'datetime' );
			$download_link = admin_url() . sprintf( '?sftp-destination-id=%s&sftp-download=%s', backupbuddy_backups()->get_destination_id(), rawurlencode( $backup ) );

			$backup_array = array(
				array(
					$backup,
					$backup_date,
					$download_link,
				),
				backupbuddy_core::pretty_backup_type( $backup_type ),
				pb_backupbuddy::$format->file_size( $file['size'] ),
			);

			if ( 'restore' === $mode ) {
				$backup_array[] = backupbuddy_backups()->get_details_link( $backup );
			}

			if ( 'default' === $mode ) {
				$copy_link      = '&cpy=' . rawurlencode( $backup );
				$actions        = array(
					$download_link => __( 'Download Backup', 'it-l10n-backupbuddy' ),
					$copy_link     => __( 'Copy to Local', 'it-l10n-backupbuddy' ),
				);
				$backup_array[] = backupbuddy_backups()->get_action_menu( $backup, $actions );
			} elseif ( 'restore' === $mode ) {
				$backup_array[] = backupbuddy_backups()->get_restore_buttons( $backup, $backup_type );
			}

			$backup_list[ basename( $backup ) ]       = $backup_array;
			$backup_sort_dates[ basename( $backup ) ] = $backup_date;
		}

		$backup_list = backupbuddy_backups()->sort_backups( $backup_list, $backup_sort_dates );

		return $backup_list;
	}

	/**
	 * Send one or more files.
	 *
	 * @param array  $settings  Settings array.
	 * @param array  $files     Array of one or more files to send.
	 * @param string $send_id   ID of the send.
	 *
	 * @return bool  True on success, else false.
	 */
	public static function send( $settings = array(), $files = array(), $send_id = '' ) {
		global $pb_backupbuddy_destination_errors;
		if ( '1' == $settings['disabled'] ) {
			$pb_backupbuddy_destination_errors[] = __( 'Error #48933: This destination is currently disabled. Enable it under this destination\'s Advanced Settings.', 'it-l10n-backupbuddy' );
			return false;
		}

		if ( ! is_array( $files ) ) {
			$files = array( $files );
		}

		pb_backupbuddy::status( 'details', 'sFTP class send() function started.' );
		$sftp = self::connect( $settings, false );
		if ( ! $sftp ) {
			pb_backupbuddy::status( 'error', 'Connection to sFTP server FAILED.' );
			return false;
		}

		pb_backupbuddy::status( 'details', 'Attempting to create path (if it does not exist)...' );

		if ( $settings['path'] ) {
			if ( true === $sftp->mkdir( $settings['path'] ) ) { // Try to make directory.
				pb_backupbuddy::status( 'details', 'Directory created.' );
			} else {
				pb_backupbuddy::status( 'details', 'Directory not created.' );
			}

			if ( ! self::chdir( $sftp, $settings['path'] ) ) {
				return false;
			}
		}

		// Upload files.
		$total_transfer_size = 0;
		$total_transfer_time = 0;
		foreach ( $files as $file ) {
			if ( ! file_exists( $file ) ) {
				pb_backupbuddy::status( 'error', 'Error #859485495. Could not upload local file `' . $file . '` to send to sFTP as it does not exist. Verify the file exists, permissions of file, parent directory, and that ownership is correct. You may need suphp installed on the server.' );
			}
			if ( ! is_readable( $file ) ) {
				pb_backupbuddy::status( 'error', 'Error #8594846548. Could not read local file `' . $file . '` to send to sFTP as it is not readable. Verify permissions of file, parent directory, and that ownership is correct. You may need suphp installed on the server.' );
			}

			$filesize             = filesize( $file );
			$total_transfer_size += $filesize;

			$destination_file = basename( $file );
			pb_backupbuddy::status( 'details', 'About to put to sFTP local file `' . $file . '` of size `' . pb_backupbuddy::$format->file_size( $filesize ) . '` to remote file `' . $destination_file . '`.' );
			$send_time            = -microtime( true );
			$upload               = $sftp->put( $destination_file, $file, NET_SFTP_LOCAL_FILE );
			$send_time           += microtime( true );
			$total_transfer_time += $send_time;
			if ( false === $upload ) { // Failed sending.
				$error_message = 'ERROR #9012b ( https://ithemeshelp.zendesk.com/hc/en-us/articles/211132377-Error-Codes-#9012 ).  sFTP file upload failed. Check file permissions & disk quota.';
				pb_backupbuddy::status( 'error', $error_message );
				backupbuddy_core::mail_error( $error_message );
				pb_backupbuddy::status( 'details', 'sFTP log (if available & enabled via full logging mode): `' . $sftp->getSFTPLog() . '`.' );
				return false;
			} else { // Success sending.
				pb_backupbuddy::status( 'details', 'Success completely sending `' . basename( $file ) . '` to destination.' );

				// Start remote backup limit.
				if ( $settings['archive_limit'] > 0 ) {
					pb_backupbuddy::status( 'details', 'Archive limit enabled. Getting contents of backup directory.' );
					$contents = $sftp->rawlist( '.' ); //$settings['path'] ); // already in destination directory/path.

					// Create array of backups.
					$bkupprefix = backupbuddy_core::backup_prefix();

					$backups = array();
					foreach ( $contents as $filename => $backup ) {
						// Check if file is backup.
						$pos = strpos( $filename, 'backup-' . $bkupprefix . '-' );
						if ( false !== $pos ) {
							$backups[] = array(
								'file'     => $filename,
								'modified' => $backup['mtime'],
							);
						}
					}

					function backupbuddy_number_sort( $a, $b ) {
						return $a['modified'] < $b['modified'];
					}

					// Sort by modified using custom sort function above.
					usort( $backups, 'backupbuddy_number_sort' );

					if ( count( $backups ) > $settings['archive_limit'] ) {
						pb_backupbuddy::status( 'details', 'More backups found (' . count( $backups ) . ') than limit permits (' . $settings['archive_limit'] . ').' . print_r( $backups, true ) );
						$delete_fail_count = 0;
						$i                 = 0;
						foreach ( $backups as $backup ) {
							$i++;
							if ( $i > $settings['archive_limit'] ) {
								//if ( false === $sftp->delete( $settings['path'] . '/' . $backup['file'] ) ) {
								if ( false === $sftp->delete( $backup['file'] ) ) {
									pb_backupbuddy::status( 'details', 'Unable to delete excess sFTP file `' . $backup['file'] . '` in current path `' . $settings['path'] . '`.' );
									$delete_fail_count++;
								} else {
									pb_backupbuddy::status( 'details', 'Deleted excess sFTP file `' . $backup['file'] . '` in current path `' . $settings['path'] . '`.' );
								}
							}
						}
						if ( 0 !== $delete_fail_count ) {
							backupbuddy_core::mail_error( sprintf( __( 'sFTP remote limit could not delete %s backups. Please check and verify file permissions.', 'it-l10n-backupbuddy' ), $delete_fail_count  ) );
							pb_backupbuddy::status( 'error', 'Unable to delete one or more excess backup archives. File storage limit may be exceeded. Manually clean up backups and check permissions.' );
						} else {
							pb_backupbuddy::status( 'details', 'No problems encountered deleting excess backups.' );
						}
					} else {
						pb_backupbuddy::status( 'details', 'Not enough backups found (' . count( $backups ) . ' backups for this site out of ' . count( $contents ) . ' on server) to exceed limit (' . $settings['archive_limit'] . '). Skipping limit enforcement.' );
					}
				} else {
					pb_backupbuddy::status( 'details', 'No sFTP archive file limit to enforce.' );
				}
				// End remote backup limit.
			}
		} // end $files loop.

		// Load destination fileoptions.
		pb_backupbuddy::status( 'details', 'About to load fileoptions data.' );
		require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
		pb_backupbuddy::status( 'details', 'Fileoptions instance #6.' );
		$fileoptions_obj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt', false, false, false );
		$result          = $fileoptions_obj->is_ok();
		if ( true !== $result ) {
			pb_backupbuddy::status( 'error', __( 'Fatal Error #9034.843498. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
			return false;
		}
		pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );
		$fileoptions = &$fileoptions_obj->options;

		// Save stats.
		$fileoptions['write_speed'] = $total_transfer_size / $total_transfer_time;
		$fileoptions_obj->save();
		unset( $fileoptions_obj );

		return true;
	} // End send().

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
	 * Check if remote file exists.
	 *
	 * @param object $sftp  sFTP object.
	 * @param string $file  Filename.
	 *
	 * @return bool  Check if remote file exists.
	 */
	public static function remote_file_exists( $sftp, $file ) {
		$stat = $sftp->stat( $file );
		if ( ! is_array( $stat ) ) {
			return false;
		}
		return true;
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
		$sftp = self::connect( $settings );

		if ( ! $sftp ) {
			return false;
		}

		if ( ! self::remote_file_exists( $sftp, $remote_file ) ) {
			return false;
		}

		if ( ! $destination_file ) {
			$destination_file = backupbuddy_core::getBackupDirectory() . basename( $remote_file );
		}

		if ( ! $sftp->get( $remote_file, $destination_file ) ) {
			return false;
		}

		if ( '.zip' === substr( $remote_file, -4 ) ) {
			$dat = str_replace( '.zip', '.dat', $remote_file );
			if ( self::remote_file_exists( $sftp, $dat ) ) {
				$local_dat = backupbuddy_core::getBackupDirectory() . $dat;
				if ( ! file_exists( $local_dat ) ) {
					$sftp->get( $dat, $local_dat );
				}
			}
		}

		return true;
	}

	/**
	 * Download file from sFTP.
	 *
	 * @param array  $settings  Destination settings array.
	 * @param string $file      File to download.
	 */
	public static function stream_download( $settings, $file ) {
		$sftp = self::connect( $settings );

		if ( ! $sftp ) {
			return false;
		}

		if ( ! self::remote_file_exists( $sftp, $file ) ) {
			return false;
		}

		flush();
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename=' . basename( $file ) );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Accept-Ranges: bytes' );
		header( 'Expires: 0' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . $sftp->size( $file ) );

		set_time_limit( 3600 );
		$sftp->get( $file, 'php://output' );
	}

	/**
	 * Test sFTP connection.
	 *
	 * @param array $settings  Destination settings array.
	 *
	 * @return bool  True on success, string error message on failure.
	 */
	public static function test( $settings ) {
		$sftp = self::connect( $settings );
		if ( ! $sftp ) {
			global $backupbuddy_sftp_using_key_file;
			$using_key = '';
			if ( isset( $backupbuddy_sftp_using_key_file ) && true === $backupbuddy_sftp_using_key_file ) {
				$using_key = ' (Note: Using key file.)';
			}

			pb_backupbuddy::status( 'error', 'Connection to sFTP server FAILED.' . $using_key );
			pb_backupbuddy::status( 'details', 'sFTP log (if available & enabled via full logging mode): `' . $sftp->getSFTPLog() . '`.' );
			return __( 'Unable to connect to server using host, username, and password combination provided.', 'it-l10n-backupbuddy' ) . $using_key;
		}

		pb_backupbuddy::status( 'details', 'Success connecting to sFTP server.' );

		pb_backupbuddy::status( 'details', 'Attempting to create path (if it does not exist)...' );
		if ( true === $sftp->mkdir( $settings['path'] ) ) { // Try to make directory.
			pb_backupbuddy::status( 'details', 'Directory created.' );
		} else {
			pb_backupbuddy::status( 'details', 'Directory not created.' );
		}

		if ( empty( $settings['path'] ) ) {
			$destination_file = 'backupbuddy_test.txt';
		} else {
			$destination_file = $settings['path'] . '/backupbuddy_test.txt';
		}
		pb_backupbuddy::status( 'details', 'About to put to sFTP test file `backupbuddy_test.txt` to remote location `' . $destination_file . '`.' );
		$send_time = -microtime( true );
		if ( true !== $sftp->put( $destination_file, 'Upload test for BackupBuddy destination. Delete me.' ) ) {
			pb_backupbuddy::status( 'details', 'sFTP test: Failure uploading test file.' );
			$sftp->delete( $destination_file ); // Just in case it partionally made file. This has happened oddly.
			pb_backupbuddy::status( 'details', 'sFTP log (if available & enabled via full logging mode): `' . $sftp->getSFTPLog() . '`.' );
			return __( 'Failure uploading. Check path & permissions.', 'it-l10n-backupbuddy' );
		} else { // File uploaded.
			pb_backupbuddy::status( 'details', 'File uploaded.' );
			if ( '' != $settings['url'] ) {
				$response = wp_remote_get(
					rtrim( $settings['url'], '/\\' ) . '/backupbuddy_test.txt',
					array(
						'method'      => 'GET',
						'timeout'     => 20,
						'redirection' => 5,
						'httpversion' => '1.0',
						'blocking'    => true,
						'headers'     => array(),
						'body'        => null,
						'cookies'     => array(),
					)
				);

				if ( is_wp_error( $response ) ) {
					return __( 'Failure. Unable to connect to the provided optional URL.', 'it-l10n-backupbuddy' );
				}

				if ( stristr( $response['body'], 'backupbuddy' ) === false ) {
					return __('Failure. The path appears valid but the URL does not correspond to it. Leave the URL blank if not using this destination for migrations.', 'it-l10n-backupbuddy' );
				}
			}

			pb_backupbuddy::status( 'details', 'sFTP test: Deleting temp test file.' );
			$sftp->delete( $destination_file );
		}

		return true; // Success if we got this far.
	} // End test().

} // End class.
