<?php
/**
 * Handles everything remote destinations and passes onto individual destination
 * class functions.
 *
 * @package BackupBuddy
 * @author Dustin Bolton
 */

/**
 * Destinations Class
 */
class pb_backupbuddy_destinations {

	/**
	 * Object containing destination.
	 *
	 * @var object
	 */
	private $_destination;

	/**
	 * Array of settings for the destination.
	 *
	 * @var array
	 */
	private $_settings;

	/**
	 * Destination type.
	 *
	 * @var string
	 */
	private $_destination_type;

	/**
	 * Default destination information.
	 *
	 * @var array
	 */
	private static $_destination_info_defaults = array(
		'name'                    => '{Err_3448}',
		'description'             => '{Err_4586. Unknown destination type.}',
		'disable_file_management' => '0',
	);

	/**
	 * Initialize destination, load class, and apply defaults to passed settings.
	 *
	 * @param array $destination_settings_or_id  Array of destination settings.
	 *
	 * @return array|false  Array with key value pairs. Keys: class, settings, info. Bool FALSE on failure.
	 */
	private static function _init_destination( $destination_settings_or_id ) {
		if ( is_array( $destination_settings_or_id ) ) { // Settings array.
			$destination_settings = $destination_settings_or_id;
		} else { // ID number.
			$destination_settings = pb_backupbuddy::$options['remote_destinations'][ $destination_settings_or_id ];
		}
		unset( $destination_settings_or_id );

		pb_backupbuddy::status( 'details', 'Initializing destination.' );
		if ( empty( $destination_settings['type'] ) ) {
			$error = 'Error #8548833: Missing destination settings parameters. Details: `' . print_r( $destination_settings, true ) . '`.';
			pb_backupbuddy::status( 'error', $error );
			echo $error;
			return false;
		}

		// Bypass deprecated s3 destination types.
		$destination_settings = self::enforce_s3_type( $destination_settings );

		if ( true !== self::_typePhpSupport( $destination_settings['type'] ) ) {
			pb_backupbuddy::status( 'error', 'Your server does not support this destination. You may need to upgrade to a newer PHP version.' );
			return false;
		}

		$destination_type  = $destination_settings['type'];
		$destination_class = 'pb_backupbuddy_destination_' . $destination_type;

		if ( ! class_exists( $destination_class ) ) {
			// Load init file.
			$destination_init_file = pb_backupbuddy::plugin_path() . '/destinations/' . $destination_type . '/init.php';
			pb_backupbuddy::status( 'details', 'Loading destination init file `' . $destination_init_file . '`.' );
			if ( file_exists( $destination_init_file ) ) {
				require_once $destination_init_file;
			} else {
				pb_backupbuddy::status( 'error', 'Destination type `' . $destination_type . '` init.php file not found. Unable to load class `' . $destination_class . '`.' );
				return false;
			}

			// File loaded but class was not correct.
			if ( ! class_exists( $destination_class ) ) {
				pb_backupbuddy::status( 'error', 'Destination type `' . $destination_type . '` class not found. Unable to load class `' . $destination_class . '`.' );
				return false;
			}
			pb_backupbuddy::status( 'details', 'Destination init loaded.' );
		}

		if ( method_exists( $destination_class, 'init' ) ) {
			call_user_func_array( "{$destination_class}::init", array() ); // Initialize.
			pb_backupbuddy::status( 'details', 'Initialized `' . $destination_type . '` destination.' );
		}

		// Get default settings from class. Was using a variable class name but had to change this for PHP 5.2 compat.
		pb_backupbuddy::status( 'details', 'Applying destination-specific defaults.' );
		$vars             = get_class_vars( $destination_class );
		$default_settings = $vars['default_settings'];
		unset( $vars );
		$destination_settings = array_merge( $default_settings, $destination_settings ); // Merge in defaults.

		// Get default info from class. Was using a variable class name but had to change this for PHP 5.2 compat.
		pb_backupbuddy::status( 'details', 'Applying global destination defaults.' );
		$vars         = get_class_vars( $destination_class );
		$default_info = $vars['destination_info'];
		unset( $vars );
		$destination_info = array_merge( self::$_destination_info_defaults, $default_info ); // Merge in defaults.

		return array(
			'class'    => $destination_class,
			'settings' => $destination_settings,
			'info'     => $destination_info,
		);
	} // End _init_destination().


	/**
	 * Returns destination info.
	 *
	 * @param string $destination_type  Destination Type.
	 *
	 * @return array  Defaults + Class vars.
	 */
	public static function get_info( $destination_type ) {

		// Initialize destination.
		$destination = self::_init_destination( array( 'type' => $destination_type ) );
		if ( false === $destination ) {
			pb_backupbuddy::status( 'warning', 'Unable to load destination `' . $destination_type . '` Some destinations require a newer PHP version.' );
			return false;
		}

		return $destination['info'];

	} // End get_details().

	/**
	 * Returns destination settings.
	 *
	 * @param array $settings  Array of settings.
	 *
	 * @return array  Normalized settings array.
	 */
	public static function get_normalized_settings( $settings ) {

		// Initialize destination.
		$destination = self::_init_destination( $settings );
		if ( false === $destination ) {
			pb_backupbuddy::status( 'warning', 'Unable to load destination `' . $destination['name'] . '` Some destinations require a newer PHP version.' );
			return false;
		}

		return $destination['settings'];

	} // End get_details().


	/**
	 * Default Settings
	 *
	 * @param array $settings  Array of settings.
	 */
	private static function _defaults( $settings ) {

	} // End _defaults().


	/**
	 * Returns settings form object. false on error.
	 *
	 * @param array  $destination_settings  Array of settings.
	 * @param string $mode                  Add, edit, or save.
	 * @param int    $destination_id        ID of destination.
	 * @param string $override_url          Enter custom URL, otherwise uses default ajax url.
	 *
	 * @return false|object  False or Settings form object.
	 */
	public static function configure( $destination_settings, $mode, $destination_id = '', $override_url = '' ) {

		pb_backupbuddy::status( 'details', 'Configuring destination.' );
		$destination = self::_init_destination( $destination_settings );
		if ( false === $destination ) {
			$error = '{Error #546893498ac. Cannot load destination. This may be due to your PHP version being too old to support this destination (most likely) or its init file is missing.}';
			pb_backupbuddy::status( 'error', $error );
			echo $error;
			return false;
		}

		$destination_settings = $destination['settings']; // Settings with defaults applied, normalized, etc.
		if ( '' == $override_url ) {
			$url = pb_backupbuddy::ajax_url( 'destination_picker' ) . '&destination_id=' . $destination_id . '&sending=' . pb_backupbuddy::_GET( 'sending' ) . '&selecting=' . pb_backupbuddy::_GET( 'selecting' );
		} else {
			$url = $override_url;
		}

		$settings_form = new pb_backupbuddy_settings( 'settings', $destination_settings, $url );
		$settings_form->add_setting(
			array(
				'type'  => 'hidden',
				'name'  => 'type',
				'value' => $destination_settings['type'],
			)
		);

		$config_file = pb_backupbuddy::plugin_path() . '/destinations/' . $destination_settings['type'] . '/_configure.php';
		pb_backupbuddy::status( 'details', 'Loading destination configure file `' . $config_file . '`.' );

		if ( file_exists( $config_file ) ) {
			require $config_file;
		} else {

			$error = '{Error #54556543. Missing destination config file `' . $config_file . '`.}';
			pb_backupbuddy::status( 'error', $error );
			echo $error;
			return false;
		}

		return $settings_form;

	} // End configure().

	/**
	 * Loads manage file.
	 *
	 * Returns settings form object. false on error.
	 *
	 * @param array $destination_settings  Array of settings.
	 * @param int   $destination_id        ID of destination.
	 *
	 * @return bool  False and error echo'd, or true.
	 */
	public static function manage( $destination_settings, $destination_id = '' ) {
		$destination = self::_init_destination( $destination_settings );
		if ( false === $destination ) {
			echo '{Error #546893498ad. Destination configuration file missing.}';
			return false;
		}

		// Don't allow management of S32 or Stash2  destinations if using PHP 8 or higher
		if ( version_compare( phpversion(), '8', '>=' ) && in_array( $destination_settings['type'], array( 'stash2', 's32' ) ) ) {
			echo '<div class="notice notice-error"><p>This destination does not work with PHP 8+. Please delete it and add the updated version of the destination.</p></div>';
			return false;
		}


		$destination_settings = array_merge( self::$_destination_info_defaults, $destination['settings'] ); // Noramlize defaults.
		$destination_info     = $destination['info'];

		if ( '0' != $destination_settings['disable_file_management'] ) {
			esc_html_e( 'Remote file management has been disabled for this destination. Its files cannot be viewed or managed from within Solid Backups. To re-enable you must create a new destination.', 'it-l10n-backupbuddy' );
			return false;
		}

		$manage_file = pb_backupbuddy::plugin_path() . '/destinations/' . $destination_settings['type'] . '/_manage.php';
		if ( file_exists( $manage_file ) ) {
			$destination = &$destination_settings;
			?>
			<div class="backupbuddy-destination-container">
				<?php
				require $manage_file; // Incoming variables available to manage file: $destination.
				pb_backupbuddy::load_script( 'common' ); // Needed for table 'select all' feature.
				?>
			</div>
			<?php
			return true;
		} else {
			?>
			<div class="backupbuddy-destination-container">
			<?php
			esc_html_e( 'Files stored at this destination cannot be viewed within Solid Backups.', 'it-l10n-backupbuddy' );
			?>
			</div>
			<?php
			return false;
		}

	} // End manage().

	/**
	 * List all files / directories in a destination.
	 *
	 * @param array  $destination_settings  Array of destination settings.
	 * @param string $mode                  Array mode (default, restore, legacy).
	 * @param bool   $return                Return or echo result.
	 *
	 * @return array|false  Array of files on success, else bool FALSE.
	 */
	public static function listFiles( $destination_settings, $mode = 'default', $return = false ) {
		$destination = self::_init_destination( $destination_settings );
		if ( false === $destination ) {
			$error = '{Error #546893498c. Destination configuration file missing.}';
			if ( true === $return ) {
				return $error;
			}
			echo $error;
			return false;
		}

		$destination_settings = $destination['settings']; // Settings with defaults applied, normalized, etc.
		$destination_class    = $destination['class'];

		if ( false === method_exists( $destination_class, 'listFiles' ) ) {
			$error = esc_html__( 'listFiles destination function called on destination not supporting it.', 'it-l10n-backupbuddy' );
			pb_backupbuddy::status( 'error', $error );
			if ( true === $return ) {
				return $error;
			}
			return false;
		}

		return call_user_func_array( "{$destination_class}::listFiles", array( $destination_settings, $mode ) );
	} // listFiles.

	/**
	 * Download ALL Backup Dat files.
	 *
	 * @param array $destination_settings  Destination Settings array.
	 * @param bool  $return                Return or echo result.
	 *
	 * @return bool|string  If successful or not, or error on error.
	 */
	public static function download_dat_files( $destination_settings, $return = false ) {
		if ( backupbuddy_data_file()::creation_is_disabled() ) {
			pb_backupbuddy::status( 'details', 'Not downloading .dat files as `disable .dat file creation` is set to `true` in Advanced Settings.' );
			// Since this is not an error, return true.
			return true;
		}

		$destination = self::_init_destination( $destination_settings );
		if ( false === $destination ) {
			$error = '{Error #546893498c. Destination configuration file missing.}';
			if ( true === $return ) {
				return $error;
			}
			echo $error;
			return false;
		}

		$destination_settings = $destination['settings']; // Settings with defaults applied, normalized, etc.
		$destination_class    = $destination['class'];

		if ( false === method_exists( $destination_class, 'download_dat_files' ) ) {
			$error = esc_html__( 'download_dat_files destination function called on destination not supporting it.', 'it-l10n-backupbuddy' );
			pb_backupbuddy::status( 'error', $error );
			if ( true === $return ) {
				return $error;
			}
			return false;
		}

		return call_user_func_array( "{$destination_class}::download_dat_files", array( $destination_settings ) );
	}

	/**
	 * Get ALL Orphan Dat files.
	 *
	 * @param array $destination_settings  Destination Settings array.
	 *
	 * @return array  Array of orphan dat files.
	 */
	public static function get_dat_orphans( $destination_settings ) {
		$destination = self::_init_destination( $destination_settings );
		if ( false === $destination ) {
			pb_backupbuddy::status( 'error', esc_html__( 'Error #546893498e. Destination configuration file missing.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		$destination_settings = $destination['settings']; // Settings with defaults applied, normalized, etc.
		$destination_class    = $destination['class'];

		if ( false === method_exists( $destination_class, 'get_dat_orphans' ) ) {
			pb_backupbuddy::status( 'warning', esc_html__( 'get_dat_orphans destination function called on destination not supporting it.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		pb_backupbuddy::status( 'details', esc_html__( 'Calling get_dat_orphans for ' . $destination_settings['type'] . ' destination.', 'it-l10n-backupbuddy' ) );

		return call_user_func_array( "{$destination_class}::get_dat_orphans", array( $destination_settings ) );
	}

	/**
	 * Delete one or more files.
	 *
	 * @param array  $destination_settings  Array of destination settings.
	 * @param string $file_or_files         File or files for deletion.
	 *
	 * @return bool  True if all deleted, else false if one or more failed to delete.
	 */
	public static function delete( $destination_settings, $file_or_files ) {
		$destination = self::_init_destination( $destination_settings );

		if ( false === $destination ) {
			echo '{Error #546893498f. Destination configuration file missing.}';
			return false;
		}

		$destination_settings = $destination['settings']; // Settings with defaults applied, normalized, etc.
		$destination_class    = $destination['class'];

		if ( false === method_exists( $destination_class, 'delete' ) ) {
			pb_backupbuddy::status( 'error', 'Delete function called on destination not supporting it.' );
			return false;
		}

		// Make sure we always have an array.
		if ( ! is_array( $file_or_files ) ) {
			$file_or_files = array( $file_or_files );
		}

		$success = call_user_func_array( "{$destination_class}::delete", array( $destination_settings, $file_or_files ) );

		// Delete .dat file if delete was successful.
		if ( $success ) {
			self::delete_dat( $destination_settings, $file_or_files );
		}

		return $success;
	} // delete.

	/**
	 * Delete corresponding backup .dat files.
	 *
	 * @param array $destination_settings  Destination settings array.
	 * @param array $file_or_files         Array of files for deletion.
	 *
	 * @return bool  If files were deleted.
	 */
	public static function delete_dat( $destination_settings, $file_or_files ) {
		$destination = self::_init_destination( $destination_settings );

		if ( false === $destination ) {
			echo '{Error #546893498g. Destination configuration file missing.}';
			return false;
		}

		$destination_settings = $destination['settings']; // Settings with defaults applied, normalized, etc.
		$destination_class    = $destination['class'];

		// Call custom delete_dat if available.
		if ( false !== method_exists( $destination_class, 'delete_dat' ) ) {
			pb_backupbuddy::status( 'details', 'Attempting to delete corresponding .dat files using delete_dat method.' );
			return call_user_func_array( "{$destination_class}::delete_dat", array( $destination_settings, $file_or_files ) );
		}

		// Make sure delete method exists first.
		if ( false === method_exists( $destination_class, 'delete' ) ) {
			pb_backupbuddy::status( 'error', 'Delete function called on destination not supporting it.' );
			return false;
		}

		// Try to automate deleting of .dat files.
		$dats = array();
		foreach ( $file_or_files as $backup ) {
			// Make sure filename has .zip and is not a file ID.
			if ( false === strpos( $backup, '.zip' ) ) {
				continue;
			}
			$dats[] = str_replace( '.zip', '.dat', $backup ); // TODO: Move to backupbuddy_data_file() method.
		}

		// Bail if no dats to delete.
		if ( ! count( $dats ) ) {
			return false;
		}

		pb_backupbuddy::status( 'details', 'Attempting to delete corresponding .dat files using delete method.' );

		return call_user_func_array( "{$destination_class}::delete", array( $destination_settings, $dats ) );
	} // delete_dat.

	/**
	 * Get a remote file and store locally.
	 *
	 * @param array  $destination_settings  Array of destination settings.
	 * @param string $remote_file           Remote file to retrieve. Filename only. Directory, path, bucket, etc handled in $destination_settings.
	 * @param string $local_file            Local file to save to.
	 *
	 * @return bool True on success, else false.
	 */
	public static function getFile( $destination_settings, $remote_file, $local_file ) {

		$remote_file = basename( $remote_file ); // Sanitize just in case.

		$destination = self::_init_destination( $destination_settings );
		if ( false === $destination ) {
			echo '{Error #546893498d. Destination configuration file missing.}';
			return false;
		}

		$destination_settings = $destination['settings']; // Settings with defaults applied, normalized, etc.
		$destination_class    = $destination['class'];

		if ( false === method_exists( $destination_class, 'getFile' ) ) {
			pb_backupbuddy::status( 'error', 'getFile destination function called on destination not supporting it.' );
			return false;
		}

		pb_backupbuddy::status( 'error', 'Calling getFile on `' . $destination_class . '`.' );
		$result = call_user_func_array( "{$destination_class}::getFile", array( $destination_settings, $remote_file, $local_file ) );

		if ( $result && file_exists( $local_file ) ) {
			if ( ! backupbuddy_data_file()->locate( basename( $local_file ) ) ) {
				// Fake the backup fileoptions array for now.
				$backup_array = array(
					'archive_file' => $local_file,
					'profile'      => array(
						'type' => backupbuddy_core::parse_file( $local_file, 'type' ),
					),
				);
				backupbuddy_data_file()->create( $backup_array );
			}
		}

		return $result;
	} // End getFile().


	/**
	 * Send backup to remote destination.
	 *
	 * @param array  $destination_settings  Array of settings to pass to destination.
	 * @param string $file                  Full file path + filename to send. Was array pre-6.1.0.1. Can be array for Deployment.
	 * @param string $send_id               Send ID.
	 * @param bool   $delete_after          Delete backup after send.
	 * @param bool   $is_retry              If is retry attempt.
	 * @param string $trigger               Trigger for send.
	 * @param string $destination_id        ID of destination.
	 *
	 * @return bool|array True success, false on failure, array for multipart send information (transfer is being chunked up into parts).
	 */
	public static function send( $destination_settings, $file, $send_id = '', $delete_after = false, $is_retry = false, $trigger = '', $destination_id = '' ) {
		register_shutdown_function( 'pb_backupbuddy_destinations::shutdown_function' );

		// If Live, check if still connected.
		if ( 'live' == $destination_settings['type'] ) {
			require_once pb_backupbuddy::plugin_path() . '/destinations/live/live.php';
			if ( false === backupbuddy_live::getLiveID() ) {
				pb_backupbuddy::status( 'error', 'Error #3849834: Trying to send to a Live destination which has been disconnected. Halting.' );
				return false;
			}
		}

		if ( ! is_array( $file ) ) {
			if ( ! file_exists( $file ) ) {
				pb_backupbuddy::status( 'error', 'Error #3892383: File `' . $file . '` does not exist or blocked by permissions. Was it deleted?' );
				return false;
			}
		} else {
			foreach ( $file as $file_item ) {
				if ( ! file_exists( $file_item ) ) {
					pb_backupbuddy::status( 'error', 'Error #38232392383: File `' . $file_item . '` does not exist or blocked by permissions. Was it deleted?' );
					return false;
				}
			}
		}

		if ( '' == $send_id ) {
			pb_backupbuddy::status( 'details', 'Send ID not specified. Generating random ID.' );
			$send_id = pb_backupbuddy::random_string( 12 );
		}

		if ( '' != $send_id ) {
			$do_delete_after = $delete_after ? 'Yes' : 'No';
			pb_backupbuddy::add_status_serial( 'remote_send-' . $send_id );
			pb_backupbuddy::status( 'details', '----- Initiating master send function for Solid Backups v' . pb_backupbuddy::settings( 'version' ) . '.' );
			if ( is_array( $file ) ) {
				pb_backupbuddy::status( 'details', 'Sending multiple files (' . count( $file ) . ') in this single send. Post-send deletion: ' . $do_delete_after );
			} else {
				pb_backupbuddy::status( 'details', 'Basename file: `' . basename( $file ) . '`. Post-send deletion: ' . $do_delete_after );
			}

			require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
			$fileoptions_file = backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt';
			if ( ! file_exists( $fileoptions_file ) ) {
				$fileoptions_obj = new pb_backupbuddy_fileoptions( $fileoptions_file, false, true, true );
			} else {
				$fileoptions_obj = new pb_backupbuddy_fileoptions( $fileoptions_file, false, false, false );
			}
			$result = $fileoptions_obj->is_ok();
			if ( true !== $result ) {
				pb_backupbuddy::status( 'error', __( 'Fatal Error #9034.239737. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
				return false;
			}

			$fileoptions = &$fileoptions_obj->options;
			if ( '' == $fileoptions ) {
				// Set defaults.
				$fileoptions = backupbuddy_core::get_remote_send_defaults();

				$fileoptions['type']    = $destination_settings['type'];
				$fileoptions['file']    = $file;
				$fileoptions['retries'] = 0;
			}
			$fileoptions['sendID']              = $send_id;
			$fileoptions['destinationSettings'] = $destination_settings; // always store the LATEST settings for resume info and retry function.

			$prev_update_time = 0;
			if ( isset( $fileoptions['update_time'] ) ) {
				$prev_update_time = $fileoptions['update_time'];
			}
			$fileoptions['update_time'] = microtime( true );
			$fileoptions['deleteAfter'] = $delete_after;

			$size_file = is_array( $file ) && count( $file ) === 1 ? reset( $file ) : $file;
			if ( is_array( $size_file ) ) {
				$fileoptions['file_size'] = -1;
			} elseif ( file_exists( $size_file ) ) {
				if ( filesize( $size_file ) ) {
					$fileoptions['file_size'] = filesize( $size_file );
				}
			}

			if ( '' != $trigger ) {
				$fileoptions['trigger'] = $trigger;
			}
			if ( '' != $destination_id ) {
				$fileoptions['destination'] = $destination_id;
			}

			if ( true === $is_retry ) {
				empty( $fileoptions['retries'] ) ? $fileoptions['retries'] = 0 : $fileoptions['retries']++;
				pb_backupbuddy::status( 'details', '~~~ This is RETRY attempt #' . $fileoptions['retries'] . ' of a previous failed send step potentially due to timeout. ~~~' );
				if ( $fileoptions['retries'] > ( 10 ) ) { // Backup safety beyond the normal retry attempt limiting system. Hardcoded to prevent runaway attempts.
					pb_backupbuddy::status( 'error', 'Error #398333: A remote send retry exceeded the maximum hard limit number of retry attempts.  Cancelling send to prevent runaway situation.' );
					$fileoptions_obj->options['status']      = 'failure'; // Mark as failed so it won't retry anymore.
					$fileoptions_obj->options['finish_time'] = '-1'; // Mark as failed so it won't retry anymore.
					$fileoptions_obj->save();
					unset( $fileoptions_obj );
					return false;
				}
			}

			// Make sure remote send is not extremely old.  Give up on any sends that began over a month ago as a failsafe.
			if ( ( '' != $prev_update_time ) && $prev_update_time > 0 ) { // We have a previous update time.
				if ( ( microtime( true ) - $prev_update_time ) > backupbuddy_constants::REMOTE_SEND_MAX_TIME_SINCE_START_TO_BAIL ) {
					pb_backupbuddy::status( 'error', 'Error #22823983: A remote send that began over a month ago tried to send. Giving up as it is too old.  This is a failsafe.' );
					$fileoptions_obj->options['status']      = 'failure'; // Mark as failed so it won't retry anymore.
					$fileoptions_obj->options['finish_time'] = '-1'; // Mark as failed so it won't retry anymore.
					$fileoptions_obj->save();
					unset( $fileoptions_obj );
					return false;
				}
			}

			$fileoptions_obj->save();

			if ( isset( $fileoptions['status'] ) && 'aborted' == $fileoptions['status'] ) {
				pb_backupbuddy::status( 'warning', 'Destination send triggered on an ABORTED transfer. Ending send function.' );
				return false;
			}

			unset( $fileoptions_obj );
		}

		$destination = self::_init_destination( $destination_settings );
		if ( false === $destination ) {
			$error = '{Error #546893498a. Destination configuration file missing. Destination may have been deleted.}';
			echo $error;
			pb_backupbuddy::status( 'error', $error );
			if ( '' != $send_id ) {
				pb_backupbuddy::remove_status_serial( 'remote_send-' . $send_id );
			}
			return false;
		}

		$destination_settings = $destination['settings']; // Settings with defaults applied, normalized, etc.
		if ( ! is_array( $file ) && ( ! file_exists( $file ) ) ) {
			pb_backupbuddy::status( 'error', 'Error #58459458743. The file that was attempted to be sent to a remote destination, `' . $file . '`, was not found. It either does not exist or permissions prevent accessing it. Check that local backup limits are not causing it to be deleted.' );
			if ( '' != $send_id ) {
				pb_backupbuddy::remove_status_serial( 'remote_send-' . $send_id );
			}
			return false;
		}

		if ( ! method_exists( $destination['class'], 'send' ) ) {
			pb_backupbuddy::status( 'error', 'Destination class `' . $destination['class'] . '` does not support send operation -- missing function.' );
			if ( '' != $send_id ) {
				pb_backupbuddy::remove_status_serial( 'remote_send-' . $send_id );
			}
			return false;
		}

		pb_backupbuddy::status( 'details', 'Calling destination-specific send method `' . "{$destination['class']}::send" . '`.' );
		global $pb_backupbuddy_destination_errors;
		$pb_backupbuddy_destination_errors = array();
		$result                            = call_user_func_array( "{$destination['class']}::send", array( $destination_settings, $file, $send_id, $delete_after ) );

		/**
		 * $result potential values.
		 *
		 * - false  Transfer FAILED.
		 * - true   Non-chunked transfer succeeded.
		 * - array  [
		 *             multipart_id, // Unique string ID for multipart send. Empty string if last chunk finished sending successfully.
		 *             multipart_status_message
		 *          ]
		 *
		 * @var bool|array
		 */
		if ( false === $result ) {
			$error_details = '';
			if ( is_array( $pb_backupbuddy_destination_errors ) && count( $pb_backupbuddy_destination_errors ) > 0 ) {
				$error_details = esc_html__( ' `Details: ', 'it-l10n-backupbuddy' ) . print_r( $pb_backupbuddy_destination_errors, true ) . '`';
			}
			pb_backupbuddy::status( 'error', 'Error #8239823: One or more errors were encountered attempting to send. See log above for more information such as specific error numbers or the following details.' . print_r( $error_details, true ) );

			$log_directory = backupbuddy_core::getLogDirectory();

			// Send error email if enabled and NOT a LIVE or SITE(Deployment) type.
			if ( 'live' != $destination_settings['type'] && 'site' != $destination_settings['type'] ) {
				$pre_error = 'There was an error sending to the remote destination titled `' . $destination_settings['title'] . '` of type `' . backupbuddy_core::pretty_destination_type( $destination_settings['type'] ) . '`. One or more files may have not been fully transferred. Please see error details for additional information. If the error persists, enable full error logging and try again for full details and troubleshooting. Details: ' . "\n\n";
				$log_file  = $log_directory . 'status-remote_send-' . $send_id . '_' . pb_backupbuddy::$options['log_serial'] . '.txt';
				pb_backupbuddy::status( 'details', 'Looking for remote send log file to send in error email: `' . $log_file . '`.' );
				if ( ! file_exists( $log_file ) ) {
					pb_backupbuddy::status( 'details', 'Remote send log file not found.' );
					backupbuddy_core::mail_error( $pre_error . $error_details );
				} else { // Log exists. Attach.
					pb_backupbuddy::status( 'details', 'Remote send log file found. Attaching to error email.' );
					backupbuddy_core::mail_error( $pre_error . $error_details . "\n\nSee the attached log for details.", '', array( $log_file ) );
				}
			}

			// Save error details into fileoptions for this send.
			pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #45...' );
			require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
			$fileoptions_obj    = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt', false, false, false );
			$fileoptions_result = $fileoptions_obj->is_ok();
			if ( true !== $fileoptions_result ) {
				pb_backupbuddy::status( 'error', __( 'Error #9034.32731. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $fileoptions_result );
			}
			$fileoptions                 = &$fileoptions_obj->options;
			$fileoptions['status']       = 'failed';
			$fileoptions['error']        = 'Error sending.' . $error_details;
			$fileoptions['updated_time'] = microtime( true );
			$fileoptions_obj->save();
			unset( $fileoptions_obj );

		}

		if ( is_array( $result ) ) { // Send is multipart.
			pb_backupbuddy::status( 'details', 'Multipart chunk mode completed a pass of the send function. Resuming will be needed. Result: `' . print_r( $result, true ) . '`.' );
			if ( '' != $send_id ) {
				require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
				$fileoptions_obj    = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt', false, false, false );
				$fileoptions_result = $fileoptions_obj->is_ok();
				if ( true !== $fileoptions_result ) {
					pb_backupbuddy::status( 'error', __( 'Fatal Error #9034.387462. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $fileoptions_result );
					return false;
				}
				$fileoptions = &$fileoptions_obj->options;

				$fileoptions['_multipart_status'] = $result[1];
				$fileoptions['updated_time']      = microtime( true );
				$fileoptions_obj->save();
				unset( $fileoptions_obj );
				pb_backupbuddy::status( 'details', 'Next multipart chunk will be processed shortly. Now waiting on its cron...' );
			}
		} else { // Single all-at-once send.
			if ( false === $result ) {
				pb_backupbuddy::status( 'details', 'Completed send function. Failure. Post-send deletion will be skipped if enabled.' );
			} elseif ( true === $result ) {
				pb_backupbuddy::status( 'details', 'Completed send function. Success.' );
			} else {
				pb_backupbuddy::status( 'warning', 'Completed send function. Unknown result: `' . $result . '`.' );
			}

			pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #16...' );
			require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
			$fileoptions_obj    = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt', false, false, false );
			$fileoptions_result = $fileoptions_obj->is_ok();
			if ( true !== $fileoptions_result ) {
				pb_backupbuddy::status( 'error', __( 'Error #9034.387462. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $fileoptions_result );
			}
			pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );
			$fileoptions                 = &$fileoptions_obj->options;
			$fileoptions['updated_time'] = microtime( true );
			if ( true === $result ) {
				$fileoptions['finish_time'] = microtime( true );
			}

			unset( $fileoptions_obj );
		}

		// File transfer completely finished successfully.
		if ( true === $result ) {

			if ( ! is_array( $file ) ) {
				$file_size        = filesize( $file );
				$pretty_file_size = pb_backupbuddy::$format->file_size( $file_size );
			} else {
				$file_size        = -1;
				$pretty_file_size = 'n/a';
			}
			if ( is_array( $file ) ) {
				$serial = '';
			} else {
				$serial = backupbuddy_core::get_serial_from_file( $file );
			}

			// Handle deletion of send file if enabled.
			if ( true === $delete_after && false !== $result ) {
				pb_backupbuddy::status( 'details', __( 'Post-send deletion enabled.', 'it-l10n-backupbuddy' ) );
				if ( false === $result ) {
					pb_backupbuddy::status( 'details', 'Skipping post-send deletion since transfer failed.' );
				} else {
					pb_backupbuddy::status( 'details', 'Performing post-send deletion since transfer succeeded.' );
					pb_backupbuddy::status( 'details', 'Deleting local file `' . $file . '`.' );
					// Handle post-send deletion on success.
					if ( file_exists( $file ) ) {
						$unlink_result = @unlink( $file );
						if ( true !== $unlink_result ) {
							pb_backupbuddy::status( 'error', 'Unable to unlink local file `' . $file . '`.' );
						} else {
							$data_file = backupbuddy_data_file()->locate( $file );
							if ( false !== $data_file ) {
								backupbuddy_data_file()->delete( $data_file );
							}
						}
					}
					if ( file_exists( $file ) ) { // File still exists.
						pb_backupbuddy::status( 'details', __( 'Error. Unable to delete local file `' . $file . '` after send as set in settings.', 'it-l10n-backupbuddy' ) );
						backupbuddy_core::mail_error( 'Solid Backups was unable to delete local file `' . $file . '` after successful remove transfer though post-remote send deletion is enabled. You may want to delete it manually. This can be caused by permission problems or improper server configuration.' );
					} else { // Deleted.
						pb_backupbuddy::status( 'details', __( 'Deleted local file after successful remote destination send based on settings.', 'it-l10n-backupbuddy' ) );
						pb_backupbuddy::status( 'archiveDeleted', '' );
					}
				}
			} else {
				pb_backupbuddy::status( 'details', 'Post-send deletion not enabled.' );
			}

			if ( ! isset( $destination_settings['live_mode'] ) || '1' != $destination_settings['live_mode'] ) {
				// Send email notification if enabled.
				if ( '' != pb_backupbuddy::$options['email_notify_send_finish'] ) {
					pb_backupbuddy::status( 'details', __( 'Sending finished destination send email notification.', 'it-l10n-backupbuddy' ) );

					$extra_replacements = array();
					$extra_replacements = array(
						'{backup_file}'   => is_array( $file ) ? implode( ',', $file ) : $file,
						'{backup_size}'   => $file_size,
						'{backup_serial}' => $serial,
					);

					backupbuddy_core::mail_notify_scheduled( $serial, 'destinationComplete', __( 'Destination send complete to', 'it-l10n-backupbuddy' ) . ' ' . backupbuddy_core::pretty_destination_type( $destination_settings['type'] ), $extra_replacements );
				} else {
					pb_backupbuddy::status( 'details', __( 'Finished sending email NOT enabled. Skipping.', 'it-l10n-backupbuddy' ) );
				}

				// Save notification of final results.
				$data                = array();
				$data['serial']      = $serial;
				$data['file']        = $file;
				$data['size']        = $file_size;
				$data['pretty_size'] = $pretty_file_size;
				backupbuddy_core::addNotification( 'remote_send_success', 'Remote file transfer completed', 'A file has successfully completed sending to a remote location.', $data );
			}
		}

		// NOTE: Call this before removing status serial so it shows in log.
		pb_backupbuddy::status( 'details', 'Ending send() function pass.' );

		// Return logging to normal file.
		if ( '' != $send_id ) {
			pb_backupbuddy::remove_status_serial( 'remote_send-' . $send_id );
		}

		return $result;

	} // End send().

	/**
	 * Test Destination
	 *
	 * @param array $destination_settings  Array of settings.
	 *
	 * @return bool|string  Return true on success, else error message.
	 */
	public static function test( $destination_settings ) {
		$destination = self::_init_destination( $destination_settings );
		if ( false === $destination ) {
			echo '{Error #546893498ab. Destination configuration file missing.}';
			return false;
		}

		$destination_settings = $destination['settings']; // Settings with defaults applied, normalized, etc.
		$destination_type     = $destination_settings['type'];
		$destination_class    = 'pb_backupbuddy_destination_' . $destination_type;

		if ( ! method_exists( $destination_class, 'test' ) ) {
			echo '{Error #546893498ab. Destination does not support testing.}';
			return false;
		}

		// test() returns true on success, else error message.
		return call_user_func_array( "{$destination_class}::test", array( $destination_settings ) );
	} // End test().


	/**
	 * Just pass through.
	 *
	 * @param array $destination_settings  Array of settings.
	 *
	 * @return bool  Result of cleanup.
	 */
	public static function multipart_cleanup( $destination_settings ) {

		$destination = self::_init_destination( $destination_settings );
		if ( false === $destination ) {
			echo '{Error #546893498d. Destination configuration file missing.}';
			return false;
		}

		$destination_settings = $destination['settings']; // Settings with defaults applied, normalized, etc.
		$destination_type     = $destination_settings['type'];
		$destination_class    = 'pb_backupbuddy_destination_' . $destination_type;

		// Just pass through whatever response.
		return call_user_func_array( "{$destination_class}::multipart_cleanup", array( $destination_settings ) );

	} // End test().

	/**
	 * Used for catching fatal PHP errors during backup to write to log for debugging.
	 */
	public static function shutdown_function() {
		// error_log ('shutdown_function()');
		// Get error message.
		// Error types: http://php.net/manual/en/errorfunc.constants.php
		$e = error_get_last();
		if ( null === $e ) { // No error of any kind.
			return;
		} else { // Some type of error.
			if ( ! is_array( $e ) || $e['type'] != E_ERROR && $e['type'] != E_USER_ERROR ) { // Return if not a fatal error.
				return;
			}
		}

		// Calculate log directory.
		$log_directory = backupbuddy_core::getLogDirectory(); // Also handles when importbuddy.
		$main_file     = $log_directory . 'log-' . pb_backupbuddy::$options['log_serial'] . '.txt';

		// Determine if writing to a serial log.
		if ( '' != pb_backupbuddy::$_status_serial ) {
			$serial_files   = array();
			$status_serials = pb_backupbuddy::$_status_serial;
			if ( ! is_array( $status_serials ) ) {
				$status_serials = array( $status_serials );
			}
			foreach ( $status_serials as $serial ) {
				$serial_files[] = $log_directory . 'status-' . $serial . '_' . pb_backupbuddy::$options['log_serial'] . '.txt';
			}
			$write_serial = true;
		} else {
			$write_serial = false;
		}

		// Format error message.
		$e_string = "---\n" . __( 'Fatal PHP error encountered:', 'it-l10n-backupbuddy' ) . "\n";
		foreach ( (array) $e as $e_line_title => $e_line ) {
			$e_string .= $e_line_title . ' => ' . $e_line . "\n";
		}
		$e_string .= "---\n";

		// Write to log.
		file_put_contents( $main_file, $e_string, FILE_APPEND );
		if ( true === $write_serial ) {
			foreach ( $serial_files as $serial_file ) {
				@file_put_contents( $serial_file, $e_string, FILE_APPEND );
			}
		}

	} // End shutdown_function.

	/**
	 * Does this server's PHP support this destination type?
	 *
	 * @param string $destination_type  Name of destination type / class / directory.
	 *
	 * @return bool|string  True if no minimum, else PHP version minimum (unsupported destination)
	 */
	private static function _typePhpSupport( $destination_type ) {

		$destinations_root = dirname( __FILE__ ) . '/';
		if ( file_exists( $destinations_root . $destination_type . '/_phpmin.php' ) ) {
			$php_minimum = file_get_contents( $destinations_root . $destination_type . '/_phpmin.php' );
			if ( version_compare( PHP_VERSION, $php_minimum, '<' ) ) { // Server's PHP is insufficient.
				return $php_minimum;
			}
		}
		return true;

	} // End _typePhpSupport().

	/**
	 * Return an array of remote destinations. By default only gives those compatible with this server.
	 *
	 * @param bool $show_unavailable  Whether or not to return destinations that are incompatible with this server. Default: false.
	 *
	 * @return array  Array of destination information. 'compatible' key bool whether or not it is compatible with this server ('compatibility' key includes server settings required if incompatible). Incompatible destinations not shown by default.
	 */
	public static function get_destinations_list( $show_unavailable = false ) {
		$destinations_root = dirname( __FILE__ ) . '/';

		$destination_dirs = glob( $destinations_root . '*', GLOB_ONLYDIR );
		if ( ! is_array( $destination_dirs ) ) {
			$destination_dirs = array();
		}

		$destination_list = array();
		foreach ( $destination_dirs as $destination_dir ) {
			$destination_dir = str_replace( $destinations_root, '', $destination_dir );
			if ( substr( $destination_dir, 0, 1 ) == '_' ) { // Skip destinations beginning in underscore as they are not an actual destination.
				continue;
			}
			//
			// Remove Stash2 and S32 if PHP version is greater than 8.
			if ( version_compare( phpversion(), '8', '>=' ) && in_array( $destination_dir, array( 'stash2', 's32' ) ) ) {
				continue;
			}

			$phpmin = self::_typePhpSupport( $destination_dir );

			$destination = self::get_info( $destination_dir );
			if ( false === $destination ) {
				continue;
			}

			if ( ! empty( $destination['deprecated'] ) ) {
				continue;
			}

			// Hotfix to fix s32 destination breaking due to namespaces in init.php.  Need to re-work so init.php doesn't break things.
			if ( true !== $phpmin ) {
				$destination['compatible']    = false;
				$destination['name']          = $destination_dir;
				$destination['compatibility'] = __( 'Requires PHP v', 'it-l10n-backupbuddy' ) . $phpmin;
			} else {
				$destination['compatible'] = true;
				$destination['type']       = $destination_dir;
			}

			$destination_list[ $destination_dir ] = $destination;
		}

		// Change some ordering.
		$stash3_destination = array();
		if ( isset( $destination_list['stash3'] ) ) {
			$stash3_destination = array( 'stash3' => $destination_list['stash3'] );
			unset( $destination_list['stash3'] );
		}

		$stash2_destination = array();
		if ( isset( $destination_list['stash2'] ) ) {
			$stash2_destination = array( 'stash2' => $destination_list['stash2'] );
			unset( $destination_list['stash2'] );
		}

		$stash_destination = array();
		if ( isset( $destination_list['stash'] ) ) {
			$stash_destination = array( 'stash' => $destination_list['stash'] );
			unset( $destination_list['stash'] );
		}

		$deploy_destination = array();
		if ( isset( $destination_list['site'] ) ) {
			$deploy_destination = array( 'site' => $destination_list['site'] );
			unset( $destination_list['site'] );
		}

		$s33_destination = array();
		if ( isset( $destination_list['s33'] ) ) {
			$s33_destination = array( 's33' => $destination_list['s33'] );
			unset( $destination_list['s33'] );
		}

		$s32_destination = array();
		if ( isset( $destination_list['s32'] ) ) {
			$s32_destination = array( 's32' => $destination_list['s32'] );
			unset( $destination_list['s32'] );
		}

		$destination_list = array_merge( $stash3_destination, $stash2_destination, $stash_destination, $deploy_destination, $s33_destination, $s32_destination, $destination_list );
		$destination_list = apply_filters( 'backupbuddy_destinations', $destination_list );

		return $destination_list;
	} // End get_destinations().

	/**
	 * Handles removing destination from schedules also.
	 *
	 * @param int  $destination_id  ID of Destination.
	 * @param bool $confirm         Must be set to true.
	 *
	 * @return bool|string True on success, else error message.
	 */
	public static function delete_destination( $destination_id, $confirm = false ) {

		if ( false === $confirm ) {
			return 'Error #54858597. Not deleted. Confirmation parameter missing.';
		}

		// Delete destination.
		$deleted_destination          = array();
		$destination_settings         = pb_backupbuddy::$options['remote_destinations'][ $destination_id ];
		$deleted_destination['type']  = $destination_settings['type'];
		$deleted_destination['title'] = $destination_settings['title'];

		// Allow destinations to handle cleanup upon deletion.
		$destination = self::_init_destination( $destination_settings );
		do_action( 'backupbuddy_delete_destination_' . $deleted_destination['type'], $destination_settings );

		unset( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] );

		// Remove this destination from all schedules using it.
		foreach ( pb_backupbuddy::$options['schedules'] as $schedule_id => $schedule ) {
			$remote_list         = '';
			$trimmed_destination = false;

			$remote_destinations = explode( '|', $schedule['remote_destinations'] );
			foreach ( $remote_destinations as $remote_destination ) {
				if ( $remote_destination == $destination_id ) {
					$trimmed_destination = true;
				} else {
					$remote_list .= $remote_destination . '|';
				}
			}

			if ( true === $trimmed_destination ) {
				pb_backupbuddy::$options['schedules'][ $schedule_id ]['remote_destinations'] = $remote_list;
			}
		} // end foreach.

		pb_backupbuddy::save();

		backupbuddy_core::addNotification( 'destination_deleted', 'Remote destination deleted', 'An existing remote destination "' . $deleted_destination['title'] . '" has been deleted.', $deleted_destination );

		return true;

	} // End delete_destination().

	/**
	 * Filters the destination settings to force S3 (v3) for all S3-related destinations.
	 *
	 * This is used because we may not have the Destination ID available for saving the settings.
	 *
	 * Because s32/s33 and stash2/stash3 have identical settings we can just change the type.
	 *
	 * Note that there is a similar function in housekeeping.php that saves the settings.
	 *
	 * @todo this can be removed in a future version.
	 *
	 * @since 9.1.8
	 *
	 * @param array $destination Destination settings.
	 *
	 * @return array The destination settings.
	 */
	private static function enforce_s3_type( $destination ) {
		if ( 's32' === $destination['type'] ) {
			$destination['type'] = 's33';
		} else if ( 'stash2' === $destination['type'] ) {
			$destination['type'] = 'stash3';
		} elseif ( 'live' === $destination['type']
			&& ( ! isset( $destination['destination_version'] ) || $destination['destination_version'] !== '3' )
		) {
			$destination['destination_version'] = '3';
		}
		return $destination;
	}

	/**
	 * Check if a destination type is in use.
	 *
	 * "In use" means that a destination of this type is configured in the settings.
	 *
	 * @param string $check_type  Destination type.
	 *
	 * @return bool  True if in use, else false.
	 */
	public static function is_using_destination_type( $check_type ) {
		$all_destinations = pb_backupbuddy::$options['remote_destinations'];
		foreach( $all_destinations as $destination ) {
			if ( $destination['type'] === $check_type ) {
				return true;
			}
		}
		return false;

	}

} // End class.
