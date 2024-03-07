<?php
/**
 *  Solid Backups Stash Live Remote Destination class.
 *
 * @author Dustin Bolton
 * @since 7.0
 *
 * NOTE: DO NOT CALL THIS CLASS DIRECTLY FOR MOST USES.
 * CALL VIA: pb_backupbuddy_destination in bootstrap.php.
 */

class pb_backupbuddy_destination_live {
	/**
	 * Number of seconds to fudge up the time elapsed to give a little wiggle room
	 * so we don't accidently hit the edge and time out.
	 */
	const TIME_WIGGLE_ROOM = 5;

	// Transient name where live action response is cached.
	const LIVE_ACTION_TRANSIENT_NAME = 'backupbuddy_live_action_response';

	/**
	 * Caches for the reported expire time minus this amount to prevent
	 * issues with servers being off GMT for some reason.
	 */
	const LIVE_ACTION_TRANSIENT_EXPIRE_WIGGLE = '5400';

	// How long to cache transient.
	const LIVE_ACTION_TRANSIENT_CACHE_TIME = 10800;

	public static $destination_info = array(
		'name'        => 'Solid Backups Stash Live',
		'description' => 'Simply synchronize your site into the cloud without hassle.',
		'category'    => 'best', // best, normal, legacy
	);

	// Default settings. Should be public static for auto-merging.
	public static $default_settings = array(
		'type'            => 'live', // MUST MATCH your destination slug.
		'title'           => '',     // Required destination field.
		'itxapi_username' => '',     // Username to connect to SolidWP API.
		'itxapi_token'    => '',     // Site token for SolidWP API.

		/*
		 * Note: Neither of the periods below are associated with actual
		 * cron tasks - the hourly live periodic cron task just uses the
		 * interval period to decide when action(s) shuld be taken based
		 * on when they were last taken.
		 */
		'periodic_process_period' => 'itbub-twicedaily', // How often to run periodic process.
		'remote_snapshot_period'  => 'itbub-daily',      // How often to trigger a remote snapshot. NOTE: This does not happen until all periodic process steps are complete.

		'enabled'                     => '1',   // Enabled (1) or not (0).
		'disable_logging'             => '0',   // 1 to skip log redirection.
		'max_send_details_limit'      => '5',   // Maximum number of remote send filesoptions and logs to keep. Keeps most recent.
		'postmeta_key_excludes'       => '',    // STRING. _getOption( 'xx', TRUE ) converts to array. Postmeta keys to exclude from triggering an update to live db.
		'options_excludes'            => '',    // STRING. _getOption( 'xx', TRUE ) converts to array. Options table names to exclude from triggering an update to live db.
		'file_excludes'               => '',    // ADDITIONAL files to exclude beyond base global BB file excludes.
		'table_excludes'              => '',    // ADDITIONAL tables to exclude beyond base global BB table excludes.
		'disabled'                    => '0',   // When 1, disable this destination.
		'live_mode'                   => '1',   // Master destination is Live. NOTE: For Live use stash_mode AND live_mode as 1.
		'pause_continuous'            => '0',   // Pauses continous operations.
		'pause_periodic'              => '0',   // Paurses periodic operations, including scheduling subsequent sends.
		'max_daily_failures'          => '50',  // Maximum number of files to allow to fail before halting further file sends.
		'max_filelist_keys'           => '250', // Maximum number of files to list from server via listObjects calls.
		'send_snapshot_notification'  => '1',   // Whether or not to send a snapshot notification for snapshot completions. Note: First email is always sent.
		'show_admin_bar'              => '0',   // Whether or not to show stats in admin bar.
		'no_new_snapshots_error_days' => '10',  // Sends error emails if no Snapshots
		'max_wait_on_transfers_time'  => '5',   // Maximum minutes to wait for pending transfers to complete before falling back to Snapshotting.
		'email'                       => '',    // Email to send snapshot notifications to. If blank it will use SolidWP Member Account email.
		'max_delete_burst'            => '100', // Max number of files per delete per burst. Eg number of files to pass into deleteFiles() function.
		'disable_file_management'     => '0',
		'destination_version'         => '3',   // Which Stash remote destination version to use.

		// Archive Limits.
		'limit_db_daily'        => '5',
		'limit_db_weekly'       => '2',
		'limit_db_monthly'      => '1',
		'limit_db_yearly'       => '0',
		'limit_full_daily'      => '1',
		'limit_full_weekly'	    => '1',
		'limit_full_monthly'    => '1',
		'limit_full_yearly'	    => '0',
		'limit_plugins_daily'   => '0',
		'limit_plugins_weekly'  => '0',
		'limit_plugins_monthly' => '0',
		'limit_plugins_yearly'  => '0',
		'limit_themes_daily'    => '0',
		'limit_themes_weekly'   => '0',
		'limit_themes_monthly'  => '0',
		'limit_themes_yearly'   => '0',

		// s33 settings
		'ssl'                           => '1',        // Whether or not to use SSL encryption for connecting.
		'server_encryption'             => 'AES256',   // Encryption (if any) to have the destination enact. Empty string for none.
		'max_time'                      => '',         // Default max time in seconds to allow a send to run for. Set to 0 for no time limit. Aka no chunking. Blank to use auto-detected number. Adjusted number for use by Stash Live to be stored in _max_time instance var.
		'_max_time'                     => '30',       // Calculated and adjusted max time based on detected runtime and user settings. Instance var.
		'max_burst'                     => '10',       // Max size in mb of each burst within the same page load.
		'use_server_cert'               => '0',        // When 1, use the packaged cacert.pem file included with the AWS SDK.
		'disable_hostpeer_verficiation' => '0',        // Disables SSL host/peer verification.
		'storage'                       => 'STANDARD', // Whether to use standard or reduced redundancy storage. Allowed values: STANDARD, REDUCED_REDUNDANCY
		'_database_table'               => '',         // Table name if sending a database file.
	);

	/**
	 * Send one or more files.
	 *
	 * @param  array  $settings      Destination settings.
	 * @param  array  $file          Array of one or more files to send.
	 * @param  string $send_id       Send ID to use. If blank, one will be generated.
	 * @param  bool   $delete_after  Whether or not to delete the file after sending.
	 *
	 * @return bool True on success, else false.
	 */
	public static function send( $settings = array(), $file = '', $send_id = '', $delete_after = false ) {
		$settings = self::_init( $settings );
		global $pb_backupbuddy_destination_errors;
		if ( isset( $settings['disabled'] ) && ( '1' == $settings['disabled'] ) ) {
			$pb_backupbuddy_destination_errors[] = __( 'Error #48933: This destination is currently disabled. Enable it under this destination\'s Advanced Settings.', 'it-l10n-backupbuddy' );
			return false;
		}

		// Get credentials for working with Live files.
		$response = self::_get_live_action_response( $settings );
		if ( false === $response ) {
			return false;
		}

		$settings['stash_mode']  = '0';
		$settings['live_mode']   = '1'; // Live is calling the s33 destination.
		$settings['bucket']      = $response['bucket'];
		$settings['credentials'] = $response['credentials'];
		$settings['directory']   = $response['prefix'];

		if ( isset( $response['client_settings'] ) ) {
			$settings['client_settings'] = $response['client_settings'];
		}
		if ( isset( $response['settings_override'] ) ) {
			$settings['settings_override'] = $response['settings_override'];
		}

		if ( '' != $settings['_database_table'] ) { // Database file.
			$settings['directory'] .= '/wp-content/uploads/backupbuddy_temp/SERIAL/';
		} else { // Normal file.
			// Calculate subdirectory file needs to go in relative to ABSPATH.
			$abspath_len = strlen( ABSPATH );
			$file_subdir = substr( $file, $abspath_len ); // Remove ABSPATH.
			$file_subdir = substr( $file_subdir, 0, strlen( $file_subdir ) - strlen( basename( $file_subdir ) ) ); // Remove filename, leaving us with fileless path relative to ABSPATH.
			$settings['directory'] .= '/' . $file_subdir;
		}

		// Send file.
		$result = call_user_func_array( array( 'pb_backupbuddy_destination_s33', 'send' ), array( $settings, $file, $send_id, $delete_after ) );

		if ( true === $result ) {
			require_once( pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php' );
			if ( empty( $settings['_database_table'] ) ) { // Normal file.
				$abspathlen    = strlen( ABSPATH );
				$relative_file = substr( $file, $abspathlen - 1 );
				backupbuddy_live_periodic::set_file_backed_up( $relative_file );
			} else { // Database file.
				backupbuddy_live_periodic::set_file_backed_up( $file, $settings['_database_table'] );
			}
		}

		// Success AFTER multipart send so kick Live along. For single-pass sends we continue on in live_periodic.php.
		if ( ( true === $result ) && ( ! empty( $settings['_multipart_status'] ) ) ) {

			pb_backupbuddy::status( 'details', 'Live mode. Preparing to schedule next preferred step (if applicable).' );
			if ( isset( $settings['_live_next_step'] ) && ( 2 === count( $settings['_live_next_step'] ) ) ) { // Next step is defined and has proper arg count.
				backupbuddy_live_periodic::schedule_next_event( $settings['_live_next_step'][0], $settings['_live_next_step'][1] );
			}
		}

		return $result;
	}

	/**
	 * Run the destination test by sending a file.
	 *
	 * @param  array  $settings  Destination settings.
	 *
	 * @return  bool|string True on success, string error message on failure.
	 */
	public static function test( $settings ) {

		/*
		 * if ( ( $settings['address'] == '' ) || ( $settings['username'] == '' ) || ( $settings['password'] == '' ) ) {
		 *     return __('Missing required input.', 'it-l10n-backupbuddy' );
		 * }
		 */

		// 3rd param true forces clearing of any current uploads.
		return pb_backupbuddy_destinations::send( $settings, dirname( dirname( __FILE__ ) ) . '/remote-send-test.php', $send_id = 'TEST-' . pb_backupbuddy::random_string( 12 ) );
	}

	/**
	 * Delete a file.
	 *
	 * Alias to deleteFiles().
	 *
	 * @param array        $settings Destination settings.
	 * @param string|array $file     File or array of files to delete.
	 *
	 * @return bool|string True on success, string error message on failure.
	 */
	public static function deleteFile( $settings, $file ) {
		if ( ! is_array( $file ) ) {
			$file = array( $file );
		}
		return self::deleteFiles( $settings, $file );
	}

	/**
	 * Delete files.
	 *
	 * @param array $settings Destination settings.
	 * @param array $files    Array of files to delete.
	 *
	 * @return bool|string True if success, else string error message.
	 */
	public static function deleteFiles( $settings, $files = array() ) {
		$settings = self::_init( $settings );

		if ( ! is_array( $files ) ) {
			$file = array( $files );
		}

		// Get credentials for working with Live files.
		$response = self::_get_live_action_response( $settings );
		if ( false === $response ) {
			return false;
		}

		$settings['bucket'] = $response['bucket'];
		$settings['credentials'] = $response['credentials'];

		if ( isset( $response['client_settings'] ) ) {
			$settings['client_settings'] = $response['client_settings'];
		}
		if ( isset( $response['settings_override'] ) ) {
			$settings['settings_override'] = $response['settings_override'];
		}

		$directory = '';
		if ( isset( $settings['directory'] ) && ( '' != $settings['directory'] ) ) {
			$directory = $settings['directory'];
		}
		$settings['directory'] = $response['prefix'] . '/' . $directory;
		return call_user_func_array( array( 'pb_backupbuddy_destination_s33', 'deleteFiles' ), array( $settings, $files ) );

	}

	/**
	 * Get list of files with optional prefix. Returns stashAPI response.
	 *
	 * @param array  $settings Destination settings.
	 * @param string $prefix   Optional prefix to filter files by.
	 * @param string $marker   Optional marker to start listing from.
	 *
	 * @return array|bool StashAPI response on success, false on failure.
	 */
	public static function listFiles( $settings, $prefix = '', $marker = '' ) {
		$settings = self::_init( $settings );

		// Get credentials for working with Live files.
		$response = self::_get_live_action_response( $settings );
		if ( false === $response ) {
			pb_backupbuddy::status( 'error', 'Could not get response from Stash Live Server in listFiles(). Settings: ' . print_r( $settings, true ) );
			return false;
		}

		$settings['stash_mode']  = '1'; // Stash is calling the s33 destination.
		$settings['bucket']      = $response['bucket'];
		$settings['credentials'] = $response['credentials'];

		if ( isset( $response['client_settings'] ) ) {
			$settings['client_settings'] = $response['client_settings'];
		}
		if ( isset( $response['settings_override'] ) ) {
			$settings['settings_override'] = $response['settings_override'];
		}

		$prefix = $response['prefix'] . '/' . $prefix;
		$prefix = rtrim( $prefix, '/' );

		if ( '' != $marker ) {
			$settings['marker'] = $prefix . $marker;
		}

		$settings['remote_path'] = $prefix;

		$files = call_user_func_array( array( 'pb_backupbuddy_destination_s33', 'get_files' ), array( $settings ) );
		if ( ! is_array( $files ) ) {
			pb_backupbuddy::status( 'error', 'Erorr #43894394734: listFiles() did not return array. Details: `' . print_r( $files ) . '`.' );
			return array();
		}

		// Strip prefix from keys.
		$prefix_len = strlen( $prefix );
		foreach ( $files as &$file ) {
			$file['Key'] = substr( $file['Key'], $prefix_len );
		}

		return $files;
	}

	/**
	 * Get download URL for a file.
	 *
	 * @param array   $settings   Destination settings.
	 * @param string  $remoteFile Filename of remote file. Does NOT contain path as it is calculated from $settings.
	 * @param int     $expires    Timestamp to expire. Defaults to 1 hour.
	 *
	 * @return
	 */
	public static function getFileURL( $settings, $remoteFile, $expires = 0 ) {
		$settings = self::_init( $settings );

		// Get credentials for working with Live files.
		$response = self::_get_live_action_response( $settings );
		if ( false === $response ) {
			return false;
		}

		$settings['bucket']      = $response['bucket'];
		$settings['credentials'] = $response['credentials'];

		if ( isset( $response['client_settings'] ) ) {
			$settings['client_settings'] = $response['client_settings'];
		}
		if ( isset( $response['settings_override'] ) ) {
			$settings['settings_override'] = $response['settings_override'];
		}

		$directory = '';
		if ( isset( $settings['directory'] ) && ( '' != $settings['directory'] ) ) {
			$directory = $settings['directory'];
		}
		$settings['directory'] = $response['prefix'] . '/' . $directory;

		return call_user_func_array( array( 'pb_backupbuddy_destination_s33', 'getFileURL' ), array( $settings, $remoteFile, $expires ) );
	}

	/**
	 * Send the Request to Stash API.
	 *
	 * @param array  $settings          Destination settings.
	 * @param string $action            Stash API action to call.
	 * @param array  $additionalParams  Additional parameters to pass to Stash API.
	 * @param bool   $blocking          Whether to block until response is received.
	 * @param bool   $passthru_errors   Whether to pass through errors from Stash API.
	 * @param int    $timeout           Timeout in seconds.
	 *
	 * @return array|bool StashAPI response on success, false on failure.
	 */
	public static function stashAPI( $settings, $action, $additionalParams = array(), $blocking = true, $passthru_errors = false, $timeout = 60 ) {
		require_once( pb_backupbuddy::plugin_path() . '/lib/stash/stash-api.php' );

		$settings = self::_formatSettings( $settings );

		return BackupBuddy_Stash_API::request( $action, $settings, $additionalParams, $blocking, $passthru_errors, $timeout );
	}

	/**
	 * Retrieves response from calling the 'live' Stash API action. Caches response for a period of time unless forced to bust.
	 *
	 * @param array $settings   Destination settings.
	 * @param bool  $bust_cache Whether to bust the cache and force a new response.
	 *
	 * @return array|bool StashAPI response on success, false on failure.
	 */
	private static function _get_live_action_response( $settings, $bust_cache = false ) {
		if ( true === $bust_cache ) {
			delete_transient( self::LIVE_ACTION_TRANSIENT_NAME );
		}

		// Get credentials via LIVE action and cache them for future calls.
		if ( false == ( $response = get_transient( self::LIVE_ACTION_TRANSIENT_NAME ) ) ) {
			require_once( pb_backupbuddy::plugin_path() . '/lib/stash/stash-api.php' );

			$settings = self::_formatSettings( $settings );

			$response = BackupBuddy_Stash_API::get_upload_credentials( $settings['itxapi_username'], $settings['itxapi_token'], false, '3' );

			if ( is_array( $response ) ) {
				if ( pb_backupbuddy::full_logging() ) {
					pb_backupbuddy::status( 'details', 'Live API live action response due to logging level: `' . print_r( $response, true ) . '`. Call params: `' . print_r( array(), true ) . ' `.' );
				}

				pb_backupbuddy::status( 'details', 'Caching Live action response data in transient `' . self::LIVE_ACTION_TRANSIENT_NAME . '`.' );

				$cache_time = self::LIVE_ACTION_TRANSIENT_CACHE_TIME;

				if ( isset( $response['expires'] ) && ( $response['expires'] - time() < self::LIVE_ACTION_TRANSIENT_CACHE_TIME ) ) {
					/*
					 * Ensure that the cache timeout is shorter than the expires timeout sent by the server. Remove an
					 * additional 30 minutes to ensure that running code doesn't attempt to use it when the access
					 * expires.
					 */
					$cache_time = $response['expires'] - time() - ( 30 * MINUTE_IN_SECONDS );
				}
			} else {
				$error = 'Error #344080: Unable to initiate Live send retrieval of manage data. Details: `' . $response . '`.';
				pb_backupbuddy::status( 'error', $error );
				backupbuddy_core::addNotification( 'live_error', 'Solid Backups Stash Live Error', $error );

				/*
				 * Cache the response temporarily so that the site doesn't make continuous requests to the server when
				 * there is an error response due to a temporary server issue or a problem with the account.
				 */
				$cache_time = 5 * MINUTE_IN_SECONDS;
				$response = time() + $cache_time;
			}

			// Ensure a non-zero cache timeout.
			$cache_time = max( $cache_time, MINUTE_IN_SECONDS );

			set_transient( self::LIVE_ACTION_TRANSIENT_NAME, $response, $cache_time );
		}

		if ( is_string( $response ) && preg_match( '/^\d+$/', $response ) ) {
			if ( $response < time() ) {
				return self::_get_live_action_response( $settings, true );
			} else {
				return false;
			}
		}

		return $response;
	}

	/**
	 * Purge cached credentials.
	 */
	public static function clear_cached_credentials() {
		delete_transient( self::LIVE_ACTION_TRANSIENT_NAME );
		pb_backupbuddy::alert( 'Deleted cached Live credentials.' );
	}

	/**
	 * Initialize the destination.
	 *
	 * @param array $settings Destination settings.
	 *
	 * @return array Destination settings.
	 */
	public static function _init( $settings ) {
		$settings = self::_formatSettings( $settings );

		/*
		 * If using PHP8+, require Stash3. This includes updating the Live options to set Stash3 to default.
		 * Only run this if we're greater than PHP8 and it's the first attempt at a send.
		 *
		 * @todo jr3 remove this once the conversion to always use v3 is complete (around v9.1.11).
		 */
		if ( version_compare( phpversion(), '8', '>=' ) && (int) $settings['destination_version'] < 3 ) {
			$live_id = false;
			foreach ( pb_backupbuddy::$options['remote_destinations'] as $destination_id => $destination_settings ) {
				if ( 'live' === $destination_settings['type'] ) {
					$live_id = $destination_id;
					break;
				}
			}

			if ( false === $live_id ) {
				return $settings;
			}

			$settings['destination_version'] = '3';
			pb_backupbuddy::$options['remote_destinations'][ $live_id ]['destination_version'] = '3';
			pb_backupbuddy::save();
		}

		require_once( pb_backupbuddy::plugin_path() . '/destinations/s33/init.php' );

		return $settings;

	}

	/**
	 * Merge default settings with passed settings.
	 *
	 * @param array $settings Destination settings.
	 *
	 * @return array Destination settings.
	 */
	public static function _formatSettings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		// Apply defaults.
		return array_merge( self::$default_settings, $settings );
	}
}
