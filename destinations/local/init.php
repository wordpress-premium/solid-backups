<?php
/**
 * Local Directory Copy Main Class
 *
 * DO NOT CALL THIS CLASS DIRECTLY. CALL VIA: pb_backupbuddy_destination in bootstrap.php.
 *
 * @package BackupBuddy
 */

/**
 * Remote Destination Init Class for Local Directory Copy
 */
class pb_backupbuddy_destination_local {

	/**
	 * Destination Properties
	 *
	 * @var array
	 */
	public static $destination_info = array(
		'name'        => 'Local Directory Copy',
		'description' => 'Send files to another directory on this server / hosting account. This is useful for storing copies locally in another location. This is also a possible destination for automated migrations.',
		'category'    => 'normal', // best, normal, legacy.
	);

	/**
	 * Default settings. Should be public static for auto-merging.
	 *
	 * @var array
	 */
	public static $default_settings = array(
		'type'                    => 'local', // MUST MATCH your destination slug. Required destination field.
		'title'                   => '', // Required destination field.
		'path'                    => '', // Local file path for destination.
		'url'                     => '', // Corresponding web URL for this location.
		'created_at'              => 0,
		'temporary'               => false,
		'archive_limit'           => '0',
		'disable_file_management' => '0', // When 1, _manage.php will not load which renders remote file management DISABLED.
		'disabled'                => '0', // When 1, disable this destination.
	);

	/**
	 * Normalize settings array.
	 *
	 * @param array $settings  User defined destination settings.
	 *
	 * @return array  Array of normalized settings.
	 */
	public static function _formatSettings( array $settings ): array {
		$settings['path'] = rtrim( $settings['path'], '/' ) . '/'; // Force trailing slash.

		return $settings;
	} // _formatSettings.

	/**
	 * Send one or more files.
	 *
	 * @param array  $settings             Destination Settings.
	 * @param array  $files                Array of one or more files to send.
	 * @param string $send_id              True on success, else false.
	 * @param bool   $delete_after         Delete file after.
	 * @param bool   $delete_remote_after  Delete remote file after (for tests).
	 *
	 * @return boolean  If send was successful.
	 */
	public static function send( $settings = array(), $files = array(), $send_id = '', $delete_after = false, $delete_remote_after = false ) {
		$settings = self::_formatSettings( $settings ); // Format all settings.

		global $pb_backupbuddy_destination_errors;
		if ( '1' == $settings['disabled'] ) {
			$pb_backupbuddy_destination_errors[] = __( 'Error #48933: This destination is currently disabled. Enable it under this destination\'s Advanced Settings.', 'it-l10n-backupbuddy' );
			return false;
		}
		if ( ! is_array( $files ) ) {
			$files = array( $files );
		}

		$path = $settings['path'];
		if ( ! file_exists( $path ) ) {
			pb_backupbuddy::$filesystem->mkdir( $path );
		}

		if ( is_writable( $path ) !== true ) {
			pb_backupbuddy::status( 'error', __( 'Failure: The path does not allow writing. Please verify write file permissions.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		$total_transfer_time = 0;
		$total_transfer_size = 0;
		foreach ( $files as $file ) {
			pb_backupbuddy::status( 'details', 'Starting send to `' . $path . '`.' );

			$filesize             = filesize( $file );
			$total_transfer_size += $filesize;

			$send_time = -( microtime( true ) );
			if ( true !== @copy( $file, $path . '/' . basename( $file ) ) ) {
				pb_backupbuddy::status( 'error', 'Unable to copy file `' . $file . '` of size `' . pb_backupbuddy::$format->file_size( $filesize ) . '` to local path `' . $path . '`. Please verify the directory exists and permissions permit writing.' );
				backupbuddy_core::mail_error( $error_message );
				return false;
			} else {
				pb_backupbuddy::status( 'details', 'Send success.' );
			}
			$send_time           += microtime( true );
			$total_transfer_time += $send_time;

			if ( $delete_remote_after ) {
				pb_backupbuddy::status( 'details', 'Deleting `' . basename( $file ) . '` per `delete_remote_after` parameter.' );
				self::delete( $settings, basename( $file ) );
			}

			self::prune( $settings );
		} // end foreach.

		// Load fileoptions to the send.
		pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #11...' );
		require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
		$fileoptions_obj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt', false, false, true );
		$result          = $fileoptions_obj->is_ok();
		if ( true !== $result ) {
			pb_backupbuddy::status( 'error', __( 'Fatal Error #9034.2344848. Unable to access fileoptions data. Error: ', 'it-l10n-backupbuddy' ) . $result );
		} else {
			pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );
			$fileoptions = &$fileoptions_obj->options;

			$fileoptions['write_speed'] = ( $total_transfer_time / $total_transfer_size );
			$fileoptions_obj->save();
		}

		return true;
	} // End send().

	/**
	 * Prune backups based on archive limit settings.
	 *
	 * @param array $settings  Destination Settings array.
	 *
	 * @return bool  If prune occurred.
	 */
	public static function prune( $settings ) {
		$limit = $settings['archive_limit'];

		// Start remote backup limit.
		if ( $limit <= 0 ) {
			pb_backupbuddy::status( 'details', 'No local destination file limit to enforce.' );
			return false;
		}

		$remote_files = self::listFiles( $settings );

		if ( count( $remote_files ) >= $limit ) {
			pb_backupbuddy::status( 'details', 'Archives count (' . count( $remote_files ) . ') fewer or equal to limit (' . $limit . '). Trimming unnecessary.' );
			return false;
		}

		pb_backupbuddy::status( 'details', 'More archives (' . count( $remote_files ) . ') than limit (' . $limit . ') allows. Trimming...' );

		$i                 = 0;
		$delete_fail_count = 0;

		if ( ! class_exists( 'pb_backupbuddy_destinations' ) ) {
			require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
		}

		foreach ( $remote_files as $remote_file ) {
			$i++;
			if ( $i > $limit ) {
				pb_backupbuddy::status( 'details', 'Trimming excess file `' . $remote_file[0][0] . '`...' );
				if ( false === pb_backupbuddy_destinations::delete( $settings, $remote_file[0][0] ) ) {
					pb_backupbuddy::status( 'details', 'Unable to delete excess local file `' . $remote_file[0][0] . '`.' );
					$delete_fail_count++;
				}
			}
		}

		pb_backupbuddy::status( 'details', 'Finished trimming excess backups.' );

		if ( 0 !== $delete_fail_count ) {
			$error_message = 'Local remote limit could not delete ' . $delete_fail_count . ' backups.';
			pb_backupbuddy::status( 'error', $error_message );
			backupbuddy_core::mail_error( $error_message );
			return false;
		}

		return true;
	}

	/**
	 * Tests ability to write to this remote destination.
	 * TODO: Should this delete the temporary test directory to clean up after itself?
	 *
	 * @param array $settings  Destination settings.
	 * @param array $files     Array of files to use for testing.
	 *
	 * @return bool|string  True on success, string error message on failure.
	 */
	public static function test( $settings, $files = array() ) {
		$settings = self::_formatSettings( $settings ); // Format all settings.

		$test_file = pb_backupbuddy::plugin_path() . '/destinations/remote-send-test.php';
		$send_id   = 'TEST-' . pb_backupbuddy::random_string( 12 );

		if ( true !== self::send( $settings, $test_file, $send_id, false, true ) ) {
			pb_backupbuddy::status( 'details', 'Local test: Failure copying test file.' );
			return __( 'Failure sending test file. Check path & permissions.', 'it-l10n-backupbuddy' );
		}

		if ( ! empty( $settings['url'] ) ) {
			$url            = rtrim( $settings['url'], '/\\' );
			$test_file_path = $settings['path'] . '/' . basename( $test_file );
			$test_file_url  = $url . '/' . basename( $test_file );

			pb_backupbuddy::status( 'details', 'Local test: Veryifing `' . $test_file_url . '` points to `' . $test_file_path . '`.' );

			// Test URL points to file.
			$response = wp_remote_get(
				$test_file_url,
				array(
					'method'      => 'GET',
					'timeout'     => 45,
					'redirection' => 5,
					'httpversion' => '1.0',
					'blocking'    => true,
					'headers'     => array(),
					'body'        => null,
					'cookies'     => array(),
				)
			);

			if ( is_wp_error( $response ) ) {
				pb_backupbuddy::status( 'error', 'Local URL Check Error: ' . $response->get_error_message() );
				return __( 'Failure. Unable to connect to the provided URL.', 'it-l10n-backupbuddy' );
			}

			if ( stristr( $response['body'], 'backupbuddy' ) === false ) {
				return __( 'Failure. The path appears valid but the URL does not correspond to it. Leave the URL blank if not using this destination for migrations.', 'it-l10n-backupbuddy' );
			}
		}

		// Made it this far so success.
		return true;

	} // test.

	/**
	 * Get array of backup files.
	 *
	 * @param array  $settings  Destination settings array.
	 * @param string $filter    Filter to pass into glob function.
	 *
	 * @return array  Array of files.
	 */
	public static function get_files( $settings, $filter = 'backup*.zip' ) {
		$settings = self::_formatSettings( $settings ); // Format all settings.
		pb_backupbuddy::status( 'details', 'Getting files with: ' . $settings['path'] . $filter );
		$files = glob( $settings['path'] . $filter );
		if ( ! is_array( $files ) ) {
			$files = array();
		}
		return $files;
	}

	/**
	 * Get backups listing array.
	 *
	 * @param array  $settings  Destination Settings.
	 * @param string $mode      Output mode.
	 *
	 * @return array  Array of backups.
	 */
	public static function listFiles( $settings = array(), $mode = 'default' ) {
		global $pb_backupbuddy_destination_errors;
		if ( '1' == $settings['disabled'] ) {
			$pb_backupbuddy_destination_errors[] = __( 'Error #48933: This destination is currently disabled. Enable it under this destination\'s Advanced Settings.', 'it-l10n-backupbuddy' );
			return false;
		}

		$backups           = self::get_files( $settings );
		$backup_list       = array();
		$backup_sort_dates = array();

		foreach ( $backups as $backup ) {
			$backup_type = backupbuddy_core::getBackupTypeFromFile( basename( $backup ) );

			if ( ! $backup_type ) {
				continue;
			}

			$backup_date   = backupbuddy_core::parse_file( $backup, 'datetime' );
			$download_link = admin_url() . sprintf(
				'?local-destination-id=%s&local-download=%s',
				backupbuddy_backups()->get_destination_id(),
				basename( $backup )
			);

			$backup_array = array(
				array(
					$backup,
					$backup_date,
					$download_link,
				),
				backupbuddy_core::pretty_backup_type( $backup_type ),
				pb_backupbuddy::$format->file_size( filesize( $backup ) ),
			);

			if ( 'default' === $mode ) {
				$actions        = array(
					$download_link => __( 'Download Backup', 'it-l10n-backupbuddy' ),
				);
				$backup_array[] = backupbuddy_backups()->get_action_menu( $backup, $actions );
			} elseif ( 'restore' === $mode ) {
				$backup_array[] = backupbuddy_backups()->get_details_link( $backup );
				$backup_array[] = backupbuddy_backups()->get_restore_buttons( $backup, $backup_type );
			}

			$backup_list[ basename( $backup ) ]       = $backup_array;
			$backup_sort_dates[ basename( $backup ) ] = $backup_date;
		}

		$backup_list = backupbuddy_backups()->sort_backups( $backup_list, $backup_sort_dates );

		return $backup_list;
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

		$orphans = array();
		$backups = array();
		$files   = self::get_files( $settings, 'backup*.dat' );

		// Create an array of backup filenames.
		foreach ( $backups_array as $backup_array ) {
			$backups[] = $backup_array[0][0];
		}

		// Loop through all files for dat orphans.
		foreach ( $files as $file ) {
			if ( in_array( $file, array( '.', '..' ), true ) ) {
				continue;
			}

			// Skip dat files with backup files.
			$backup_name = str_replace( '.dat', '.zip', $file ); // TODO: Move to backupbuddy_data_file() method.
			if ( in_array( $backup_name, $backups, true ) ) {
				continue;
			}

			$orphans[] = basename( $file ); // Path gets added during delete().
		}

		return $orphans;
	}

	/**
	 * Download all backup dat files.
	 *
	 * @param array $settings  Destination Settings array.
	 *
	 * @return bool  If successful or not.
	 */
	public static function download_dat_files( $settings ) {
		$backups = self::get_files( $settings );
		if ( ! count( $backups ) ) {
			return false;
		}
		$success = true;
		foreach ( $backups as $backup ) {
			$dat_file = str_replace( '.zip', '.dat', $backup ); // TODO: Move to backupbuddy_data_file() method.
			if ( ! file_exists( $dat_file ) ) {
				continue;
			}

			if ( ! self::getFile( $settings, basename( $dat_file ) ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Download a remote file to local.
	 *
	 * @param array  $settings          Destination settings array.
	 * @param string $file              Remote file name.
	 * @param string $destination_file  Local file destination to place remote file.
	 *
	 * @return bool  If successful.
	 */
	public static function getFile( $settings, $file, $destination_file = false ) {
		$settings    = self::_formatSettings( $settings ); // Format all settings.
		$remote_copy = $settings['path'] . $file;

		if ( ! file_exists( $remote_copy ) ) {
			return false;
		}

		$remote_time = filemtime( $remote_copy );
		if ( ! $destination_file ) {
			$destination_file = backupbuddy_core::getBackupDirectory() . $file;
		}

		if ( ! @copy( $remote_copy, $destination_file ) ) {
			return false;
		}

		touch( $destination_file, $remote_time );

		// Download .dat file if necessary.
		if ( '.zip' === substr( $file, -4 ) ) {
			$dat        = str_replace( '.zip', '.dat', $file ); // TODO: Move to backupbuddy_data_file() method.
			$remote_dat = $settings['path'] . $dat;
			$local_dat  = backupbuddy_core::getBackupDirectory() . $dat;
			if ( ! file_exists( $local_dat ) && file_exists( $remote_dat ) ) {
				$remote_dat_time = filemtime( $remote_dat );
				if ( @copy( $remote_dat, $local_dat ) ) {
					touch( $local_dat, $remote_dat_time );
				}
			}
		}

		return true;
	}

	/**
	 * Force Download Local Backup file.
	 *
	 * @param array  $settings  Destination settings array.
	 * @param string $file      Filename to download.
	 */
	public static function force_download( $settings = false, $file = '' ) {
		$settings = $settings ? array_merge( self:: $default_settings, $settings) : self::$default_settings;
		$settings  = self::_formatSettings( $settings );
		$file_path = $settings['path'] . basename( $file );
		
		if ( 'zip' !== pathinfo( $file, PATHINFO_EXTENSION ) ) {
			$err = __( 'Invalid file type.', 'it-l10n-backupbuddy' );
			pb_backupbuddy::status( 'error', $err );
			echo $err;
			exit();
		}
		
		if ( ! file_exists( $file_path ) ) {
			$err = __( 'Missing Local File for download.', 'it-l10n-backupbuddy' );
			pb_backupbuddy::status( 'error', $err );
			echo $err;
			exit();
		}

		flush();

		pb_backupbuddy::set_greedy_script_limits();

		$size = filesize( $file_path );

		flush();

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename=' . basename( $file_path ) );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Accept-Ranges: bytes' );
		header( 'Expires: 0' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . $size );

		readfile( $file_path );

		exit();
	}

	/**
	 * Delete a file from remote destination.
	 *
	 * @param array $settings  Remote destination settings.
	 * @param array $files     File or files to delete.
	 *
	 * @return bool  If deleted successfully.
	 */
	public static function delete( $settings, $files ) {
		$settings = self::_formatSettings( $settings ); // Format all settings.

		if ( ! is_array( $files ) ) {
			$files = array( $files );
		}

		foreach ( $files as $file ) {
			$full_path = $settings['path'] . $file;
			if ( ! file_exists( $full_path ) ) {
				pb_backupbuddy::status( 'details', __( 'Attempt to delete Local destination file `' . $file . '` failed: File not found.', 'it-l10n-backupbuddy' ) );
				return false;
			}

			@unlink( $full_path );

			if ( file_exists( $full_path ) ) {
				pb_backupbuddy::status( 'error', __( 'Attempt to delete Local destination file `' . $file . '` failed: Unlink unsuccessful.', 'it-l10n-backupbuddy' ) );
				return false;
			}
		}

		return true;
	}
} // End class.
