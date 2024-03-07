<?php
/**
 * Microsoft OneDrive Destination
 *
 * @package BackupBuddy
 */

use Krizalys\Onedrive\Onedrive,
	Krizalys\Onedrive\Constant\AccessTokenStatus;

/**
 * OneDrive main destination class.
 */
class pb_backupbuddy_destination_onedrive {

	/**
	 * Destination info array.
	 *
	 * @var array
	 */
	public static $destination_info = array(
		'name'        => 'OneDrive',
		'description' => 'Microsoft OneDrive Remote Destination.',
		'category'    => 'best',
	);

	/**
	 * OneDrive Client Object.
	 *
	 * @var object
	 */
	private static $client = false;

	/**
	 * Destination Settings.
	 *
	 * @var array
	 */
	public static $settings = array();

	/**
	 * Default settings. Should be public static for auto-merging.
	 *
	 * @var array
	 */
	public static $default_settings = array(
		// Default settings.
		'type'                    => 'onedrive', // Required destination slug.
		'title'                   => '', // Required destination field.

		'oauth_code'              => '', // OneDrive oAuth access code.
		'onedrive_state'          => '', // Session state.
		'onedrive_folder_id'      => false, // OneDrive Folder ID.
		'onedrive_folder_name'    => '', // OneDrive Folder Name (label).

		'full_archive_limit'      => '0', // Maximum number of full backups for this site in this directory for this account. No limit if zero 0.
		'db_archive_limit'        => '0', // Maximum number of db backups for this site in this directory for this account. No limit if zero 0.
		'themes_archive_limit'    => '0', // Maximum number of theme backups for this site in this directory for this account. No limit if zero 0.
		'plugins_archive_limit'   => '0', // Maximum number of plugin backups for this site in this directory for this account. No limit if zero 0.
		'media_archive_limit'     => '0', // Maximum number of media backups for this site in this directory for this account. No limit if zero 0.
		'files_archive_limit'     => '0', // Maximum number of file/custom backups for this site in this directory for this account. No limit if zero 0.

		'disabled'                => '0', // When 1, disable this destination.
		'disable_file_management' => '0', // When 1, _manage.php will not load which renders remote file management DISABLED.
	);

	/**
	 * Send Stats array.
	 *
	 * @var array
	 */
	public static $send_stats = array(
		'start_time' => false,
	);

	/**
	 * Get the API credentials.
	 *
	 * @param string $param  Individual setting parameter.
	 *
	 * @return false|array  Array of credentials or false if invalid.
	 */
	public static function get_config( $param = false ) {
		$config_path = pb_backupbuddy::plugin_path() . '/destinations/onedrive/creds.php';
		if ( ! file_exists( $config_path ) ) {
			pb_backupbuddy::status( 'error', __( 'OneDrive API Credentials Missing.', 'it-l10n-backupbuddy' ) );
			return false;
		}
		$config = include $config_path;
		if ( ! is_array( $config ) || empty( $config['ONEDRIVE_CLIENT_ID'] ) ) {
			pb_backupbuddy::status( 'error', __( 'Invalid OneDrive API Credentials.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		if ( false !== $param ) {
			if ( empty( $config[ $param ] ) ) {
				return false;
			}
			return $config[ $param ];
		}

		return $config;
	}

	/**
	 * Init Destination, load any necessary libraries.
	 *
	 * @return bool  If init was successful.
	 */
	public static function init() {
		return true;
	} // init.

	/**
	 * Put the destination settings into the class static property.
	 *
	 * @param array $settings  Destination settings array.
	 */
	public static function add_settings( $settings ) {
		if ( ! is_array( $settings ) || ! count( $settings ) ) {
			return false;
		}
		self::$settings = $settings;
	}

	/**
	 * Save destination settings (only for existing destinations).
	 *
	 * @return bool  If settings were saved.
	 */
	public static function save() {
		$destination_id = pb_backupbuddy::_GET( 'destination_id' );
		if ( ( ! $destination_id && '0' !== $destination_id && 0 !== $destination_id ) || 'NEW' === $destination_id ) {
			return false;
		}

		if ( empty( self::$settings ) ) {
			return false;
		}

		pb_backupbuddy::$options['remote_destinations'][ $destination_id ] = self::$settings;
		pb_backupbuddy::save();
		return true;
	}

	/**
	 * Create the OneDrive client.
	 *
	 * @param bool $fresh  If the cached client should be ignored.
	 *
	 * @return bool  If successful.
	 */
	public static function get_client( $fresh = false ) {
		if ( false !== self::$client && false === $fresh ) {
			return self::$client;
		}

		$config = self::get_config();
		if ( ! $config ) {
			self::error( __( 'Could not retrieve OneDrive Credentials.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		$args = array();
		if ( self::get_state() ) {
			$args['state'] = self::get_state();
		}

		self::$client = Onedrive::client( $config['ONEDRIVE_CLIENT_ID'], $args );

		return self::$client;
	}

	/**
	 * Get OneDrive State
	 *
	 * @param bool $return_object  If Object or raw value should be returned.
	 *
	 * @return false|object|string  Session state object, raw value or false.
	 */
	public static function get_state( $return_object = true ) {
		if ( empty( self::$settings['onedrive_state'] ) ) {
			return false;
		}
		$state = self::$settings['onedrive_state'];

		// Convert back to object from raw stored value when needed.
		if ( true === $return_object ) {
			$state = json_decode( $state );
		}

		return $state;
	} // get_state.

	/**
	 * Set the OneDrive State.
	 *
	 * @param object|string $state  State object or raw value.
	 *
	 * @return bool  If set successfully.
	 */
	public static function set_state( $state ) {
		if ( empty( $state ) || ( ! is_object( $state ) && ! is_string( $state ) ) ) {
			return false;
		}

		// Convert to storable string if object.
		if ( is_object( $state ) ) {
			self::$settings['onedrive_state'] = wp_json_encode( $state );
		} else {
			self::$settings['onedrive_state'] = $state;
		}

		return true;
	} // set_state.

	/**
	 * Create a redirect URL to OneDrive.
	 *
	 * @param bool $save_state  Should the state be added to settings.
	 *
	 * @return string  Redirect URL.
	 */
	public static function init_client( $save_state = true ) {
		if ( false === self::get_client() ) {
			pb_backupbuddy::status( 'error', __( 'Could not create OneDrive client.', 'it-l10n-backupbuddy' ) );
			die( esc_html__( 'There was a problem creating the OneDrive client. Please contact support for assistance.', 'it-l10n-backupbuddy' ) );
		}

		// Our OAuth server URL.
		$redirect = self::get_config( 'ONEDRIVE_REDIRECT_URI' );

		// getLogInUrl does not throw any Exceptions.
		$url = self::$client->getLogInUrl(
			array(
				'files.read',
				'files.read.all',
				'files.readwrite',
				'files.readwrite.all',
				'offline_access',
			),
			$redirect,
			backupbuddy_get_oauth_source_url( 'onedrive' )
		);

		if ( true === $save_state ) {
			// getState does not throw an Exception.
			self::set_state( self::$client->getState() );
		}

		return $url;
	}

	/**
	 * Redirect user to OneDrive Login.
	 *
	 * This will authenticate the user with OneDrive,
	 * then redirect the user to our OAuth server,
	 * which will redirect the user back to their site
	 * along with a "code" variable.
	 *
	 * This is triggered in init_admin.php with a GET variable "oauth-authorize".
	 */
	public static function oauth_redirect() {
		if ( false === self::init() ) {
			pb_backupbuddy::status( 'error', __( 'Could not initialize OneDrive.', 'it-l10n-backupbuddy' ) );
			die( esc_html__( 'There was a problem initializing OneDrive. Please contact support for assistance.', 'it-l10n-backupbuddy' ) );
		}

		$url = self::init_client( false );

		header( 'HTTP/1.1 302 Found', true, 302 );
		header( "Location: $url" );
		exit();
	}

	/**
	 * Connect to the Remote Account
	 *
	 * @return bool  If connection was successful.
	 */
	public static function connect() {
		if ( self::is_connected() ) {
			return true;
		}

		if ( false === self::init() ) {
			self::error( __( 'There was a problem initializing OneDrive. Please contact support for assistance.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		self::init_client(); // Create new client or restore stored session.

		if ( self::is_connected() ) {
			return true;
		}

		if ( empty( self::$settings['oauth_code'] ) ) {
			pb_backupbuddy::status( 'details', __( 'Could not connect to OneDrive client. Missing OAuth code. May not be authorized yet.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		try {
			// Obtain the token using the code received by the OneDrive API.
			self::$client->obtainAccessToken( self::get_config( 'ONEDRIVE_CLIENT_SECRET' ), self::$settings['oauth_code'] );
		} catch ( \Exception $e ) {
			self::error( __( 'There was an error obtaining the OneDrive ccess token: ', 'it-l10n-backupbuddy' ) . $e->getMessage() );
			return false;
		}

		// Persist the OneDrive client's state for future API requests. (No exceptions thrown here).
		self::set_state( self::$client->getState() );

		return true;
	} // connect.

	/**
	 * Check if state exists and contains token.
	 *
	 * @return bool  If connected to OneDrive.
	 */
	public static function is_connected() {
		if ( false === self::$client ) {
			return false;
		}

		// This does not throw an Exception.
		$state = self::$client->getState();

		if ( ! $state ) {
			return false;
		}

		// getAccessTokenStatus does not throw any Exceptions.
		$status = self::$client->getAccessTokenStatus();

		// Make sure we have a token.
		if ( AccessTokenStatus::MISSING === $status ) {
			return false;
		}

		// Make sure token is fresh.
		if ( in_array( $status, array( AccessTokenStatus::EXPIRED, AccessTokenStatus::EXPIRING ), true ) ) {
			try {
				self::$client->renewAccessToken( self::get_config( 'ONEDRIVE_CLIENT_SECRET' ) );
			} catch ( \Exception $e ) {
				self::error( __( 'There was an error renewing OneDrive access token: ', 'it-l10n-backupbuddy' ) . $e->getMessage() );
				return false;
			}

			// getState does not throw any Exceptions.
			if ( ! self::set_state( self::$client->getState() ) ) {
				self::error( __( 'An error has occurred during access token refresh.', 'it-l10n-backupbuddy' ) );
				return false;
			}

			// getAccessTokenStatus does not throw any Exceptions.
			if ( AccessTokenStatus::VALID !== self::$client->getAccessTokenStatus() ) {
				self::error( __( 'Access to OneDrive has expired and could not be refreshed.', 'it-l10n-backupbuddy' ) );
				return false;
			}

			self::save();
		}

		return true;
	}

	/**
	 * Make sure we're connected
	 *
	 * @param array $settings  Destination settings.
	 *
	 * @return bool  If is ready.
	 */
	public static function is_ready( $settings = false ) {
		if ( false !== $settings ) {
			self::add_settings( $settings );
		}

		if ( ! empty( self::$settings['disabled'] ) && 1 === (int) self::$settings['disabled'] ) {
			self::error( __( 'Error #201910150840: This destination is currently disabled. Enable it under this destination\'s Advanced Settings.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		if ( false === self::connect() ) {
			self::error( __( 'There was a problem connecting OneDrive. Please contact support for assistance.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		return true;
	}

	/**
	 * Send one or more files up to OneDrive
	 *
	 * @param array  $settings             Destination Settings array.
	 * @param array  $files                File or Files to send.
	 * @param string $send_id              Send ID.
	 * @param bool   $delete_after         Delete after successful send.
	 * @param bool   $delete_remote_after  Delete remote file after (for tests).
	 *
	 * @return bool  True on success single-process, array on multipart with remaining steps, else false (failed).
	 */
	public static function send( $settings = false, $files = array(), $send_id = '', $delete_after = false, $delete_remote_after = false ) {
		if ( ! self::is_ready( $settings ) ) {
			return false;
		}

		if ( ! is_array( $files ) ) {
			$files = array( $files );
		}

		pb_backupbuddy::status( 'details', 'OneDrive send() function started. Settings: `' . print_r( self::$settings, true ) . '`.' );

		self::$send_stats['start_time'] = microtime( true );

		$folder_id = self::get_the_folder();
		$folder    = self::get_drive_item( false, $folder_id );

		if ( ! is_object( $folder ) ) {
			// translators: %s represents the filename being uploaded.
			self::error( sprintf( __( 'There was an error uploading `%s` to OneDrive: Invalid folder ID.', 'it-l10n-backupbuddy' ), basename( $file ) ) );
			return false;
		}

		$total_transfer_size = 0;
		$total_transfer_time = 0;

		pb_backupbuddy::set_greedy_script_limits();

		foreach ( $files as $file ) {
			if ( ! file_exists( $file ) ) {
				self::error( __( 'Error #201910150843: File selected to send not found', 'it-l10n-backupbuddy' ) . ': `' . $file . '`.' );
				continue;
			}

			// Determine backup type for limiting later.
			$backup_type = backupbuddy_core::getBackupTypeFromFile( $file );

			$file_size = filesize( $file );
			$file_info = pathinfo( $file );
			$file_ext  = $file_info['extension'];

			if ( 'zip' === $file_ext ) {
				$mime_type = 'application/zip';
			} elseif ( 'php' === $file_ext ) {
				$mime_type = 'application/x-httpd-php';
			} else { // TODO: Set mime type for dat files?
				$mime_type = '';
			}

			$total_transfer_size += $file_size;
			$send_time            = -microtime( true );

			pb_backupbuddy::status( 'details', __( 'Starting OneDrive upload for', 'it-l10n-backupbuddy' ) . ' `' . basename( $file ) . '`.' );

			$args = array(
				'range_size' => 10 * 1024 * 1024, // 10 MB.
			);
			if ( $mime_type ) {
				$args['contentType'] = $mime_type;
			}

			try {
				// Returns UploadSessionProxy object.
				$upload = $folder->startUpload( basename( $file ), fopen( $file, 'r' ), $args );
			} catch ( \Exception $e ) {
				self::error( __( 'Error', 'it-l10n-backupbuddy' ) . ' #201910150859: ' . __( 'Could not initiate upload for', 'it-l10n-backupbuddy' ) . ' `' . basename( $file ) . '`. ' . __( 'Details', 'it-l10n-backupbuddy' ) . ': ' . $e->getMessage() );
				return false;
			}

			pb_backupbuddy::status( 'details', 'Checking OneDrive upload status for `' . basename( $file ) . '`.' );

			try {
				// Potentially returns DriveItemProxy object.
				$new_file = $upload->complete();
			} catch ( \Exception $e ) {
				self::error( __( 'Error', 'it-l10n-backupbuddy' ) . ' #201910161524: ' . __( 'OneDrive upload failed for', 'it-l10n-backupbuddy' ) . ' `' . basename( $file ) . '`. ' . __( 'Details', 'it-l10n-backupbuddy' ) . ': ' . $e->getMessage() );
				pb_backupbuddy::status( 'details', 'OneDrive upload status for `' . basename( $file ) . '` showed: ' . print_r( $new_file, true ) );
				return false;
			}

			// Don't use isset or empty here.
			if ( ! $new_file->id ) {
				pb_backupbuddy::status( 'details', 'OneDrive upload status for `' . basename( $file ) . '` was missing ID property: ' . print_r( $new_file->id, true ) );
				continue;
			}

			// Success!
			pb_backupbuddy::status( 'details', 'OneDrive upload of file `' . basename( $file ) . '` completed successfully.' );

			if ( $send_id ) {
				$send_time           += microtime( true );
				$total_transfer_time += $send_time;

				pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #3...' );
				require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
				$fileoptions_obj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt', false, false, true );
				$result          = $fileoptions_obj->is_ok();
				if ( true !== $result ) {
					pb_backupbuddy::status( 'error', __( 'Fatal Error #9034.397237. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
				} else {
					pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );
					$fileoptions = &$fileoptions_obj->options;

					$fileoptions['start_time']  = self::$send_stats['start_time'];
					$fileoptions['finish_time'] = microtime( true );
					$fileoptions['write_speed'] = round( $total_transfer_size / $total_transfer_time, 5 );
					$fileoptions['status']      = 'success';
					$fileoptions_obj->save();
				}
				unset( $fileoptions_obj );
			}

			if ( $delete_remote_after ) {
				pb_backupbuddy::status( 'details', 'Deleting `' . $new_file->id . '` (' . $new_file->name . ') per `delete_remote_after` parameter.' );
				self::delete( false, $new_file->id );
			}

			if ( $backup_type ) {
				self::prune( $backup_type );
			}
		} // foreach.

		self::$send_stats['end_time'] = microtime( true );

		// Made it this far then success.
		return true;
	} // send.

	/**
	 * Prune uploads based on limit restrictions.
	 *
	 * @param string $backup_type  Type of backup to prune.
	 *
	 * @return bool  If pruning occurred.
	 */
	public static function prune( $backup_type = false ) {
		global $pb_backupbuddy_destination_errors;

		pb_backupbuddy::status( 'details', 'OneDrive archive limit enforcement beginning.' );

		$limit = 0;

		if ( 'full' === $backup_type ) {
			$limit = (int) self::$settings['full_archive_limit'];
		} elseif ( 'db' === $backup_type ) {
			$limit = (int) self::$settings['db_archive_limit'];
		} elseif ( 'themes' === $backup_type ) {
			$limit = (int) self::$settings['themes_archive_limit'];
		} elseif ( 'plugins' === $backup_type ) {
			$limit = (int) self::$settings['plugins_archive_limit'];
		} elseif ( 'media' === $backup_type ) {
			$limit = (int) self::$settings['media_archive_limit'];
		} elseif ( 'files' === $backup_type ) {
			$limit = (int) self::$settings['files_archive_limit'];
		} else {
			pb_backupbuddy::status( 'warning', 'Warning #34352453244. OneDrive was unable to determine backup type (reported: `' . $backup_type . '`) so archive limits NOT enforced for this backup.' );
		}

		if ( $limit <= 0 ) {
			pb_backupbuddy::status( 'details', 'No OneDrive archive file limit to enforce.' );
			return false;
		}

		pb_backupbuddy::status( 'details', 'OneDrive backup archive limit of `' . $limit . '` of type `' . $backup_type . '` based on destination settings.' );

		// Get file listing.
		$search_count = 1;
		$remote_files = array();
		while ( count( $remote_files ) === 0 && $search_count < 5 ) {
			pb_backupbuddy::status( 'details', 'Checking archive limits. Attempt ' . $search_count . '.' );
			$remote_files = self::listFiles();
			sleep( 1 );
			$search_count++;
		}

		// Filter backups by backup type.
		$backups = array();

		foreach ( $remote_files as $file_id => $backup ) {
			$filename = $backup[0][0];
			$type     = backupbuddy_core::getBackupTypeFromFile( basename( $filename ) );

			if ( ! $type ) { // Skip non-zip, non-backup files.
				continue;
			}

			// Only prune backups of this type.
			if ( $type !== $backup_type ) {
				continue;
			}

			$backups[ $file_id ] = backupbuddy_core::parse_file( $filename, 'datetime' );
		}

		arsort( $backups );
		$backup_delete_count = count( $backups );
		$delete_fail_count   = 0;

		pb_backupbuddy::status( 'details', 'OneDrive found `' . count( $backups ) . '` backups of this type when checking archive limits.' );

		if ( $backup_delete_count > $limit ) {
			$delete_backups = array_slice( $backups, $limit );

			pb_backupbuddy::status( 'details', 'More archives (' . count( $backups ) . ') than limit (' . $limit . ') allows. Pruning...' );

			if ( ! class_exists( 'pb_backupbuddy_destinations' ) ) {
				require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
			}

			foreach ( $delete_backups as $file_id => $backup_time ) {
				pb_backupbuddy::status( 'details', 'Deleting excess file `' . $file_id . '`...' );

				if ( true !== pb_backupbuddy_destinations::delete( self::$settings, $file_id ) ) {
					pb_backupbuddy::status( 'details', 'Unable to delete excess OneDrive file `' . $file_id . '`. Details: `' . print_r( $pb_backupbuddy_destination_errors, true ) . '`.' );
					$delete_fail_count++;
				}
			}

			pb_backupbuddy::status( 'details', 'Finished pruning excess backups.' );

			if ( 0 !== $delete_fail_count ) {
				$error_message = 'OneDrive remote limit could not delete ' . $delete_fail_count . ' backups.';
				self::error( $error_message, 'mail' );
			}
		}

		pb_backupbuddy::status( 'details', 'OneDrive completed archive limiting.' );

		if ( $backup_delete_count === $delete_fail_count ) {
			// No pruning has occurred.
			return false;
		}

		return true;
	}

	/**
	 * Test Upload to OneDrive
	 *
	 * @param array  $settings  Destination settings.
	 * @param string $file      File to use for testing.
	 *
	 * @return bool  True on success, string error message on failure.
	 */
	public static function test( $settings = false, $file = false ) {
		if ( false !== $settings ) {
			self::add_settings( $settings );
		}

		if ( true !== self::connect() ) {
			echo 'Could not connect to OneDrive.';
			return false;
		}

		pb_backupbuddy::status( 'details', 'Testing OneDrive destination. Sending remote-send-test.php.' );

		if ( false !== $file ) {
			$files = array( $file );
		} else {
			$files = array( pb_backupbuddy::plugin_path() . '/destinations/remote-send-test.php' );
		}

		$send_id = 'TEST-' . pb_backupbuddy::random_string( 12 );
		$result  = self::send( false, $files, $send_id, false, true );

		if ( true !== $result ) {
			echo 'OneDrive test file send failed.';
			return false;
		}

		return true;
	} // test.

	/**
	 * Get OneDrive Quota.
	 *
	 * @param bool $settings  Destination Settings.
	 *
	 * @return object|false  Quota object of false on failure.
	 */
	public static function get_quota( $settings = false ) {
		if ( ! self::is_ready( $settings ) ) {
			return false;
		}

		try {
			$quota = self::$client->fetchQuota();
		} catch ( \Exception $e ) {
			self::error( __( 'There was an error fetching OneDrive quota: ', 'it-l10n-backupbuddy' ) . $e->getMessage() );
			return false;
		}

		return $quota;
	}

	/**
	 * List files in this destination & directory.
	 *
	 * @param array  $settings  Destination settings.
	 * @param string $mode      Mode for listFiles.
	 *
	 * @return array|false  Array of items in directory OR bool FALSE on failure.
	 */
	public static function listFiles( $settings = false, $mode = 'default' ) {
		if ( false !== $settings ) {
			self::add_settings( $settings );
		}

		$folder_id = self::get_the_folder();
		$files     = self::get_folder_contents( $folder_id );

		if ( ! is_array( $files ) ) {
			self::error( __( 'Unexpected response retrieving OneDrive folder contents for folder ID: ', 'it-l10n-backupbuddy' ) . $folder_id );
			return false;
		}

		$prefix = backupbuddy_core::backup_prefix();
		if ( $prefix ) {
			$prefix .= '-';
		}

		$backup_list       = array();
		$backup_sort_dates = array();

		foreach ( $files as $file ) {
			$filename = $file->name;
			if ( false === stristr( $filename, 'backup-' . $prefix ) ) { // only show backup files for this site.
				continue;
			}

			// This checks for .zip extension.
			$backup_type = backupbuddy_core::getBackupTypeFromFile( basename( $filename ) );

			if ( ! $backup_type ) {
				continue;
			}

			$backup        = $filename;
			$backup_date   = backupbuddy_core::parse_file( $backup, 'datetime' );
			$download_link = admin_url() . sprintf( '?onedrive-destination-id=%s&onedrive-download=%s', backupbuddy_backups()->get_destination_id(), rawurlencode( $file->id ) );

			$backup_array = array(
				array(
					$backup,
					$backup_date,
					$download_link,
				),
				backupbuddy_core::pretty_backup_type( $backup_type ),
				pb_backupbuddy::$format->file_size( $file->size ),
			);

			if ( 'default' === $mode ) {
				$copy_link      = '&cpy=' . rawurlencode( $backup );
				$actions        = array(
					$download_link => __( 'Download Backup', 'it-l10n-backupbuddy' ),
					$copy_link     => __( 'Copy to Local', 'it-l10n-backupbuddy' ),
				);
				$backup_array[] = backupbuddy_backups()->get_action_menu( $backup, $actions );
				$key            = $file->id;
			} elseif ( 'restore' === $mode ) {
				$backup_array[] = backupbuddy_backups()->get_details_link( $backup, $file->id );
				$backup_array[] = backupbuddy_backups()->get_restore_buttons( $backup, $backup_type );
				$key            = basename( $backup );
			}

			$backup_list[ $key ]       = $backup_array;
			$backup_sort_dates[ $key ] = $backup_date;
		}

		$backup_list = backupbuddy_backups()->sort_backups( $backup_list, $backup_sort_dates );

		return $backup_list;
	} // listFiles.

	/**
	 * Get Stored folder from settings. If empty, use root.
	 *
	 * @return string  OneDrive Folder ID.
	 */
	public static function get_the_folder() {
		$folder_id = self::$settings['onedrive_folder_id'];
		if ( ! $folder_id ) {
			$folder_id = self::get_root_folder();
		}
		return $folder_id;
	}

	/**
	 * Retrieve remote file object/information.
	 *
	 * @param array  $settings  Destination settings array.
	 * @param string $file_id   OneDrive File ID.
	 *
	 * @return object|false  File object or false on failure.
	 */
	public static function get_drive_item( $settings = false, $file_id = '' ) {
		if ( ! self::is_ready( $settings ) ) {
			return false;
		}

		if ( ! $file_id ) {
			pb_backupbuddy::status( 'error', __( 'Could not retrieve OneDrive file properties, missing file ID.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		try {
			$item = self::$client->getDriveItemById( $file_id );
		} catch ( \Exception $e ) {
			self::error( __( 'There was an error getting OneDrive file properties for', 'it-l10n-backupbuddy' ) . ' `' . $file_id . '`: ' . $e->getMessage() );
			return false;
		}

		return $item;
	}

	/**
	 * Download a file to local.
	 *
	 * @param array  $settings        Destination settings.
	 * @param string $remote_file_id  Remote file to retrieve. Filename only. Directory, path, bucket, etc handled in $destination_settings.
	 * @param string $local_file      Local file to save to.
	 *
	 * @return array|bool  Array of items in directory OR FALSE on failure.
	 */
	public static function getFile( $settings = false, $remote_file_id = '', $local_file = '' ) {
		if ( ! self::is_ready( $settings ) ) {
			return false;
		}

		if ( ! $remote_file_id || ! $local_file ) {
			self::error( __( 'Missing required parameters for remote file copy.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		$drive_file = self::get_drive_item( false, $remote_file_id );

		if ( false === $drive_file ) {
			return false;
		}

		try {
			$contents = $drive_file->download();
		} catch ( \Exception $e ) {
			self::error( __( 'There was an error downloading OneDrive file', 'it-l10n-backupbuddy' ) . ' `' . $file_id . '`: ' . $e->getMessage() );
			return false;
		}

		if ( false === file_put_contents( $local_file, $contents->read( $contents->getSize() ) ) ) {
			self::error( __( 'Error #201910141448: Unable to save requested OneDrive file contents into file', 'it-l10n-backupbuddy' ) . ' `' . $local_file . '`.' );
			return false;
		}

		return true;
	} // getFile.

	/**
	 * Download all backup dat files.
	 *
	 * @param array $settings  Destination Settings array.
	 *
	 * @return bool  If successful or not.
	 */
	public static function download_dat_files( $settings = false ) {
		$backups_array = self::listFiles( $settings );
		if ( ! is_array( $backups_array ) || ! count( $backups_array ) ) {
			return false;
		}

		$files = self::get_folder_contents();
		if ( ! is_array( $files ) || ! count( $files ) ) {
			return false;
		}

		$backups = array();
		foreach ( $backups_array as $backup ) {
			$backups[] = $backup[0][0];
		}

		$success = true;
		foreach ( $files as $file_id => $file ) {
			// Only looking for dat files.
			if ( '.dat' !== substr( $file->name, -4 ) ) {
				continue;
			}

			$backup_name = str_replace( '.dat', '.zip', $file->name ); // TODO: Move to backupbuddy_data_file() method.
			if ( ! in_array( $backup_name, $backups, true ) ) {
				continue;
			}

			$local_file = backupbuddy_core::getBackupDirectory() . '/' . $file->name;
			if ( ! self::getFile( $settings, $file->id, $local_file ) ) {
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

		$files = self::get_folder_contents();

		if ( ! count( $files ) ) {
			return false;
		}

		$orphans = array();
		$backups = array();

		// Create an array of backup filenames.
		foreach ( $backups_array as $backup_array ) {
			$backups[] = $backup_array[0][0];
		}

		$prefix = backupbuddy_core::backup_prefix();
		if ( $prefix ) {
			$prefix .= '-';
		}

		// Loop through all dat files looking for orphans.
		foreach ( $files as $file ) {
			$filename = $file->name;

			// Skip if not a .dat file.
			if ( '.dat' !== substr( $filename, -4 ) ) {
				continue;
			}

			// Only show dat files for this site.
			if ( false === stristr( $filename, 'backup-' . $prefix ) ) {
				continue;
			}

			// Skip dat files with backup files.
			$backup_name = str_replace( '.dat', '.zip', $filename ); // TODO: Move to backupbuddy_data_file() method.
			if ( in_array( $backup_name, $backups, true ) ) {
				continue;
			}

			$orphans[ $file->id ] = $filename;
		}

		return $orphans;
	}

	/**
	 * Delete files from this destination.
	 *
	 * @param array $settings  Destination settings.
	 * @param array $files     File or array of files.
	 *
	 * @return bool  If successful or not.
	 */
	public static function deleteFile( $settings = false, $files = array() ) {
		return self::delete( $settings, $files );
	} // delete.

	/**
	 * Delete files from this destination.
	 *
	 * @param array $settings  Destination settings.
	 * @param array $files     File or array of files.
	 *
	 * @return bool  If successful or not.
	 */
	public static function delete( $settings = false, $files = array() ) {
		if ( ! self::is_ready( $settings ) ) {
			return false;
		}

		if ( ! is_array( $files ) ) {
			$files = array( $files );
		}

		if ( empty( $files ) ) {
			// Nothing to delete.
			return false;
		}

		foreach ( $files as $file_id ) {
			pb_backupbuddy::status( 'details', 'Deleting OneDrive file with ID `' . $file_id . '`.' );
			$drive_file = self::get_drive_item( false, $file_id );
			if ( ! is_object( $drive_file ) ) {
				self::error( __( 'There was a problem deleting OneDrive item: ', 'it-l10n-backupbuddy' ) . $file_id );
				continue;
			}

			$file_name = $drive_file->name;

			try {
				$drive_file->delete();
			} catch ( \Exception $e ) {
				self::error( $e->getMessage(), 'echo' );
				return false;
			}

			pb_backupbuddy::status( 'details', 'OneDrive file `' . $file_name . '` deleted.' );
		}

		return true;
	}

	/**
	 * Get contents of folder by ID.
	 *
	 * @param string $folder_id  Folder ID.
	 * @param array  $options    Array of options for fetchDriveItems().
	 *
	 * @return array  Array of items.
	 */
	public static function get_folder_contents( $folder_id = false, $options = array() ) {
		if ( ! self::is_ready() ) {
			return false;
		}

		if ( false === $folder_id ) {
			$folder_id = self::get_the_folder();
		}

		if ( ! $folder_id ) {
			self::error( __( 'Could not retrieve OneDrive folder contents, missing folder ID.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		try {
			$folder = self::get_drive_item( false, $folder_id );
		} catch ( \Exception $e ) {
			self::error( __( 'There was an error opening OneDrive folder', 'it-l10n-backupbuddy' ) . ' `' . $folder_id . '`: ' . $e->getMessage() );
			return false;
		}

		try {
			$contents = $folder->getChildren( $options );
		} catch ( \Exception $e ) {
			self::error( __( 'There was an error retrieving OneDrive folder contents for', 'it-l10n-backupbuddy' ) . ' `' . $folder_id . '`: ' . $e->getMessage() );
			return false;
		}

		if ( ! $contents || ! is_array( $contents ) ) {
			return array();
		}

		return $contents;
	}

	/**
	 * Get OneDrive folder array.
	 *
	 * @param string $parent  Parent Folder ID.
	 *
	 * @return array  Array of folder objects.
	 */
	public static function get_folders( $parent = false ) {
		if ( ! $parent ) {
			$parent = self::get_root_folder();
			if ( false === $parent ) {
				self::error( __( 'There was a problem reading root OneDrive folder. Please contact support for assistance.', 'it-l10n-backupbuddy' ) );
				return false;
			}
		}

		$items   = self::get_folder_contents( $parent );
		$folders = array();

		if ( ! is_array( $items ) ) {
			return array();
		}

		foreach ( $items as $item ) {
			if ( is_a( $item, '\Krizalys\Onedrive\Folder' ) ) {
				$folders[] = $item;
			} elseif ( is_a( $item, '\Krizalys\Onedrive\File' ) ) {
				continue;
			} else {
				// isFolder does not throw any Exceptions.
				if ( ! self::$client->isFolder( $item ) ) {
					continue;
				}
				$folders[] = $item;
			}
		}

		return $folders;
	} // get_folders.

	/**
	 * Get the root folder ID.
	 *
	 * @return string|false  Folder ID or false on failure.
	 */
	public static function get_root_folder() {
		if ( ! self::is_ready() ) {
			return false;
		}

		try {
			$parent = self::$client->getRoot();
		} catch ( \Exception $e ) {
			self::error( __( 'There was an error reading root OneDrive folder: ', 'it-l10n-backupbuddy' ) . $e->getMessage() );
			return false;
		}

		if ( ! $parent ) {
			return false;
		}

		return $parent->id;
	} // get_root_folder.

	/**
	 * Create a folder.
	 *
	 * @param string $name    Folder Name.
	 * @param string $parent  Parent folder ID.
	 *
	 * @return object  Create Folder response.
	 */
	public static function create_folder( $name, $parent = false ) {
		if ( ! self::is_ready() ) {
			return false;
		}

		if ( ! $parent ) {
			$parent = self::get_root_folder();
			if ( false === $parent ) {
				self::error( __( 'There was a problem reading root OneDrive folder. Please contact support for assistance.', 'it-l10n-backupbuddy' ) );
				return false;
			}
		}

		try {
			$result = self::$client->createFolder( sanitize_text_field( $name ) );
		} catch ( \Exception $e ) {
			self::error( __( 'There was an error creating the OneDrive folder: ', 'it-l10n-backupbuddy' ) . $e->getMessage() );
			return false;
		}

		return $result;
	}

	/**
	 * Folder Selector UI.
	 *
	 * @param int $destination_id  Destination ID.
	 */
	public static function folder_selector( $destination_id ) {
		include_once pb_backupbuddy::plugin_path() . '/destinations/onedrive/views/folder-selector.php';

		if ( ! is_numeric( $destination_id ) ) {
			$destination_id = 'NEW';
		}
		?>
		<script>
			jQuery( function( $ ) {
				var $destination_wrap = BackupBuddy.OneDriveFolderSelector.get_destination_wrap( '<?php echo esc_html( $destination_id ); ?>' ),
					$template = $( '.backupbuddy-onedrive-folder-selector[data-is-template="true"]' ).clone().removeAttr( 'data-is-template' ),
					$folder_row = $destination_wrap.find( 'td.backupbuddy-onedrive-folder-row:first' );

				$template.show().appendTo( $folder_row ).attr( 'data-destination-id', '<?php echo esc_html( $destination_id ); ?>' );

				BackupBuddy.OneDriveFolderSelector.folder_select( '<?php echo esc_html( $destination_id ); ?>' );
			});
		</script>
		<?php
	} // folder_selector.

	/**
	 * Force File Download.
	 *
	 * @param array  $settings  Destination settings.
	 * @param string $file_id   OneDrive file ID.
	 *
	 * @return false|void  False on error, void when successful.
	 */
	public static function force_download( $settings = false, $file_id = '' ) {
		if ( ! self::is_ready( $settings ) ) {
			return false;
		}

		if ( ! $file_id ) {
			self::error( __( 'Missing OneDrive File ID for download.', 'it-l10n-backupbuddy' ), 'echo' );
			exit();
		}

		pb_backupbuddy::status( 'details', __( 'Attempting to download OneDrive file: ', 'it-l10n-backupbuddy' ) . $file_id );

		try {
			$drive_file = self::get_drive_item( false, $file_id );
		} catch ( \Exception $e ) {
			self::error( __( 'There was an error getting OneDrive file properties for', 'it-l10n-backupbuddy' ) . ' `' . $file_id . '`: ' . $e->getMessage(), 'echo' );
			exit();
		}

		flush();

		pb_backupbuddy::set_greedy_script_limits();

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( sprintf( 'Content-Disposition: attachment; filename="%s"', $drive_file->name ) );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Accept-Ranges: bytes' );
		header( 'Expires: 0' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . $drive_file->size );

		try {
			$contents = $drive_file->download();
		} catch ( \Exception $e ) {
			self::error( __( 'There was an error downloading OneDrive file', 'it-l10n-backupbuddy' ) . ' `' . $file_id . '`: ' . $e->getMessage(), 'echo' );
			exit();
		}

		echo $contents->read( $contents->getSize() );
		exit();
	}

	/**
	 * Log error and add to global errors.
	 *
	 * @param string $error   Error message.
	 * @param bool   $action  Post logging action (mail, echo or both).
	 */
	public static function error( $error, $action = false ) {
		global $pb_backupbuddy_destination_errors;
		pb_backupbuddy::status( 'error', $error );
		$pb_backupbuddy_destination_errors[] = $error;
		if ( false !== $action ) {
			if ( 'mail' === $action || 'both' === $action ) {
				backupbuddy_core::mail_error( $error );
			}
			if ( 'echo' === $action || 'both' === $action ) {
				echo $error;
			}
		}
	}

} // pb_backupbuddy_destination_onedrive.
