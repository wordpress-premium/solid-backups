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
	 * @return [type] [description]
	 */
	public static function _formatSettings( $settings ) {
		// Apply defaults.
		$settings = array_merge( self::$default_settings, $settings );

		$settings['path'] = rtrim( $settings['path'], '/' ) . '/'; // Force trailing slash.

		return $settings;
	} // _formatSettings.

	/**
	 * Send one or more files.
	 *
	 * @param array  $settings  Destination Settings.
	 * @param array  $files     Array of one or more files to send.
	 * @param string $send_id   True on success, else false.
	 *
	 * @return boolean  If send was successful.
	 */
	public static function send( $settings = array(), $files = array(), $send_id = '' ) {
		$settings = self::_formatSettings( $settings ); // Format all settings.

		global $pb_backupbuddy_destination_errors;
		if ( '1' == $settings['disabled'] ) {
			$pb_backupbuddy_destination_errors[] = __( 'Error #48933: This destination is currently disabled. Enable it under this destination\'s Advanced Settings.', 'it-l10n-backupbuddy' );
			return false;
		}
		if ( ! is_array( $files ) ) {
			$files = array( $files );
		}

		$limit = $settings['archive_limit'];
		$path  = $settings['path'];
		if ( ! file_exists( $settings['path'] ) ) {
			pb_backupbuddy::$filesystem->mkdir( $settings['path'] );
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

			// Start remote backup limit.
			if ( $limit > 0 ) {
				pb_backupbuddy::status( 'details', 'Archive limit of `' . $limit . '` in settings.' );

				pb_backupbuddy::status( 'details', 'path: ' . $path . '*.zip' );
				$remote_files = glob( $path . '/*.zip' );
				if ( !is_array( $remote_files ) ) {
					$remote_files = array();
				}
				if ( ! function_exists( 'backupbuddy_local_backup_sort' ) ) {
					/**
					 * Sort Files by modified time.
					 *
					 * @param string $a  Path to file A.
					 * @param string $b  Path to file B.
					 *
					 * @return int  Difference in modified time.
					 */
					function backupbuddy_local_backup_sort( $a, $b ) {
						return filemtime( $a ) - filemtime( $b );
					}
				}
				usort( $remote_files, 'backupbuddy_local_backup_sort' );
				pb_backupbuddy::status( 'details', 'Found `' . count( $remote_files ) . '` backups.' );

				// Create array of backups and organize by date.
				$bkupprefix = backupbuddy_core::backup_prefix();

				foreach ( $remote_files as $file_key => $remote_file ) {
					if ( false === stripos( $remote_file, 'backup-' . $bkupprefix . '-' ) ) {
						pb_backupbuddy::status( 'details', 'backup-' . $bkupprefix . '-' . 'not in file: ' . $remote_file );
						unset( $backups[ $file_key ] );
					}
				}
				arsort( $remote_files );
				pb_backupbuddy::status( 'details', 'Found `' . count( $remote_files ) . '` backups.' );

				if ( ( count( $remote_files ) ) > $limit ) {
					pb_backupbuddy::status( 'details', 'More archives (' . count( $remote_files ) . ') than limit (' . $limit . ') allows. Trimming...' );
					$i                 = 0;
					$delete_fail_count = 0;
					foreach ( $remote_files as $remote_file ) {
						$i++;
						if ( $i > $limit ) {
							pb_backupbuddy::status ( 'details', 'Trimming excess file `' . $remote_file . '`...' );
							if ( !unlink( $remote_file ) ) {
								pb_backupbuddy::status( 'details',  'Unable to delete excess local file `' . $remote_file . '`.' );
								$delete_fail_count++;
							}
						}
					}
					pb_backupbuddy::status( 'details', 'Finished trimming excess backups.' );
					if ( $delete_fail_count !== 0 ) {
						$error_message = 'Local remote limit could not delete ' . $delete_fail_count . ' backups.';
						pb_backupbuddy::status( 'error', $error_message );
						backupbuddy_core::mail_error( $error_message );
					}
				}
			} else {
				pb_backupbuddy::status( 'details',  'No local destination file limit to enforce.' );
			} // End remote backup limit.
		} // end foreach.

		// Load fileoptions to the send.
		pb_backupbuddy::status( 'details', 'About to load fileoptions data.' );
		require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
		pb_backupbuddy::status( 'details', 'Fileoptions instance #11.' );
		$fileoptions_obj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt', false, false, false );
		$result          = $fileoptions_obj->is_ok();
		if ( true !== $result ) {
			pb_backupbuddy::status( 'error', __( 'Fatal Error #9034.2344848. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
			return false;
		}
		pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );
		$fileoptions = &$fileoptions_obj->options;

		$fileoptions['write_speed'] = ( $total_transfer_time / $total_transfer_size );

		return true;
	} // End send().

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

		$path = rtrim( $settings['path'], '/\\' );
		$url  = rtrim( $settings['url'], '/\\' );

		if ( ! file_exists( $path ) ) {
			pb_backupbuddy::$filesystem->mkdir( $path );
		}

		if ( is_writable( $path ) !== true ) {
			return __( 'Failure', 'it-l10n-backupbuddy' ) . '; The path does not allow writing. Please verify write file permissions.';
		}

		if ( '' != $url ) {
			$test_filename  = 'migrate_test_' . pb_backupbuddy::random_string( 10 ) . '.php';
			$test_file_path = $path . '/' . $test_filename;
			$test_file_url  = $url . '/' . $test_filename;

			// Make file.
			file_put_contents( $test_file_path, "<?php die( '1' ); ?>" );

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

			unlink( $test_file_path );

			if ( is_wp_error( $response ) ) {
				return __( 'Failure. Unable to connect to the provided URL.', 'it-l10n-backupbuddy' );
			}

			if ( trim( $response['body'] ) != '1' ) {
				return __( 'Failure. The path appears valid but the URL does not correspond to it. Leave the URL blank if not using this destination for migrations.', 'it-l10n-backupbuddy' );
			}
		}

		// Made it this far so success.
		return true;

	} // test.

	/**
	 * Get array of backup files.
	 *
	 * @param array $settings  Destination settings array.
	 *
	 * @return array  Array of backups.
	 */
	public static function get_files( $settings ) {
		$backups = glob( $settings['path'] . '*.zip' );
		if ( ! is_array( $backups ) ) {
			$backups = array();
		}
		return $backups;
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

			$backup_date = backupbuddy_core::parse_file( $backup, 'timestamp' );

			$backup_array = array(
				array(
					$backup,
					$backup_date,
				),
				//pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( filemtime( $backup ) ) ) . '<br /><span class="description">(' . pb_backupbuddy::$format->time_ago( filemtime( $backup ) ) . ' ago)</span>',
				backupbuddy_core::pretty_backup_type( $backup_type ),
				pb_backupbuddy::$format->file_size( filesize( $backup ) ),
			);

			if ( 'restore' === $mode ) {
				$backup_array[] = backupbuddy_backups()->get_details_link( $backup );
			}

			if ( 'default' === $mode ) {
				$backup_array[] = backupbuddy_backups()->get_action_menu( $backup );
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
	 * Download all backup dat files.
	 *
	 * @param array $settings  Destination Settings array.
	 *
	 * @return bool  If successful or not.
	 */
	public static function download_dat_files( $settings ) {
		$backups = glob( $settings['path'] . '*.zip' );
		if ( ! count( $backups ) ) {
			return false;
		}
		$success = true;
		foreach ( $backups as $backup ) {
			$dat_file = str_replace( '.zip', '.dat', $backup );
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
	 * @param string $file  Remote file name.
	 *
	 * @return bool  If successful.
	 */
	public static function getFile( $settings, $file ) {
		$remote_copy = $settings['path'] . $file;

		if ( ! file_exists( $remote_copy ) ) {
			return false;
		}

		$local_copy  = backupbuddy_core::getBackupDirectory() . $file;
		$remote_time = filemtime( $remote_copy );

		if ( ! @copy( $remote_copy, $local_copy ) ) {
			return false;
		}

		touch( $local_copy, $remote_time );

		// Download .dat file if necessary.
		if ( '.zip' === substr( $file, -4 ) ) {
			$dat        = str_replace( '.zip', '.dat', $file );
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
	 * Delete a file from remote destination.
	 *
	 * @param array  $settings  Remote destination settings.
	 * @param string $file      File to delete.
	 *
	 * @return bool  If deleted successfully.
	 */
	public static function delete( $settings, $file ) {
		$full_path = $settings['path'] . $file;
		if ( ! file_exists( $full_path ) ) {
			return false;
		}

		@unlink( $path );

		if ( file_exists( $full_path ) ) {
			return false;
		}

		// Delete Local and Remote dat files if necessary.
		if ( '.zip' === substr( $file, -4 ) ) {
			$dat        = str_replace( '.zip', '.dat', $file );
			$remote_dat = $settings['path'] . $dat;
			$local_dat  = backupbuddy_core::getBackupDirectory() . $dat;
			if ( file_exists( $remote_dat ) ) {
				@unlink( $remote_dat );
			}
			if ( file_exists( $local_dat ) ) {
				@unlink( $local_dat );
			}
		}

		return true;
	}
} // End class.
