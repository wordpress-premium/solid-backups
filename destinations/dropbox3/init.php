<?php
/**
 * Dropbox (v3) Remote Destination Main Class
 *
 * @package BackupBuddy
 */

use \Dropbox as dbx;

/**
 * Dropbox (v3) Destination init class.
 */
class pb_backupbuddy_destination_dropbox3 {

	/**
	 * Destination details.
	 *
	 * @var array
	 */
	public static $destination_info = array(
		'name'        => 'Dropbox v3',
		'description' => 'Dropbox.com support for servers running PHP v5.3 or newer. Supports multipart chunked uploads for larger file support, improved memory handling, and reliability.',
		'category'    => 'best', // best, normal, legacy.
	);

	/**
	 * Default settings. Should be public static for auto-merging.
	 *
	 * @var array
	 */
	public static $default_settings = array(
		// Default settings.
		'type'                    => 'dropbox3', // Required destination slug.
		'title'                   => '', // Required destination field (provided by user).

		'oauth_code'              => '', // Dropbox oAuth access code.
		'oauth_state'             => '', // Session state.
		'oauth_token'             => '', // oAuth token.
		'oauth_token_expires'     => 0,
		'refresh_token'           => '', // oAuth refresh token.

		'dropbox_folder_id'       => false, // Remote Dropbox directory ID to store into.
		'dropbox_folder_path'     => '', // Remote Dropbox directory path to store into.
		'dropbox_folder_name'     => '', // Remote Dropbox directory name to store into.

		'full_archive_limit'      => 5, // Max number of full archives allowed in destination directory.
		'db_archive_limit'        => 5, // Max number of db archives allowed in destination directory.
		'themes_archive_limit'    => 5, // Max number of themes archives allowed in destination directory.
		'plugins_archive_limit'   => 5, // Max number of plugins archives allowed in destination directory.
		'media_archive_limit'     => 5, // Max number of media archives allowed in destination directory.
		'files_archive_limit'     => 5, // Max number of files archives allowed in destination directory.

		'max_chunk_size'          => '80', // Maximum chunk size in MB. Anything larger will be chunked up into pieces this size (or less for last piece). This allows larger files to be sent than would otherwise be possible.
		'disable_file_management' => '0', // When 1, _manage.php will not load which renders remote file management DISABLED.

		// Instance variables for transfer-specific settings such as multipart/chunking.
		'_chunk_upload_id'        => '', // Instance var. Internal use only for continuing a chunked upload.
		'_chunk_offset'           => '', // Instance var. Internal use only for continuing a chunked upload.
		'_chunk_maxsize'          => '', // Instance var. Internal use only for continuing a chunked upload.
		'_chunk_next_offset'      => 0, // Instance var. Internal use only for continuing a chunked upload. - Next chunk byte offset to seek to for sending.
		'_chunk_total_sent'       => 0, // Instance var. Internal use only for continuing a chunked upload. - Total bytes sent.
		'_chunk_sent_count'       => 0, // Instance var. Internal use only for continuing a chunked upload. - Number of chunks sent.
		'_chunk_total_count'      => 0, // Instance var. Internal use only for continuing a chunked upload. - Total number of chunks that will be sent..
		'_chunk_transfer_speeds'  => array(), // Instance var. Internal use only for continuing a chunked upload. Array of time spent actually transferring. Used for calculating send speeds and such.
		'disabled'                => '0', // When 1, disable this destination.
	); // default_settings.

	/**
	 * Dropbox Client Auth.
	 *
	 * @var object
	 */
	private static $client = false;

	/**
	 * Dropbox Client API.
	 *
	 * @var object
	 */
	private static $api = false;

	/**
	 * Destination Settings.
	 *
	 * @var array
	 */
	public static $settings = array();

	/**
	 * Init destination and settings.
	 *
	 * @param int $destination_id  ID of destination.
	 *
	 * @return bool  If initialized.
	 */
	public static function init( $destination_id = false ) {
		require_once pb_backupbuddy::plugin_path() . '/destinations/dropbox3/lib/Dropbox/autoload.php';
		if ( false !== $destination_id ) {
			if ( ! empty( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) {
				$settings = pb_backupbuddy::$options['remote_destinations'][ $destination_id ];
				self::add_settings( $settings );
			}
		}
		return true;
	} // init.

	/**
	 * Get the API credentials.
	 *
	 * @param string $param  Individual setting parameter.
	 *
	 * @return false|array  Array of credentials or false if invalid.
	 */
	public static function get_config( $param = false ) {
		$config_path = pb_backupbuddy::plugin_path() . '/destinations/dropbox3/creds.php';
		if ( ! file_exists( $config_path ) ) {
			self::error( __( 'Dropbox API Credentials Missing.', 'it-l10n-backupbuddy' ) );
			return false;
		}
		$config = include $config_path;
		if ( ! is_array( $config ) || empty( $config['DROPBOX_API_KEY'] ) ) {
			self::error( __( 'Invalid Dropbox API Credentials.', 'it-l10n-backupbuddy' ) );
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
	 * Set the oAuth State.
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
			self::$settings['oauth_state'] = wp_json_encode( $state );
		} else {
			self::$settings['oauth_state'] = $state;
		}

		return true;
	} // set_state.

	/**
	 * Get Dropbox State
	 *
	 * @param bool $return_object  If Object or raw value should be returned.
	 *
	 * @return false|object|string  Session state object, raw value or false.
	 */
	public static function get_state( $return_object = true ) {
		if ( empty( self::$settings['oauth_state'] ) ) {
			return false;
		}
		$state = self::$settings['oauth_state'];

		// Convert back to object from raw stored value when needed.
		if ( true === $return_object ) {
			$state = json_decode( $state );
		}

		return $state;
	} // get_state.

	/**
	 * Create the Dropbox client.
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
			self::error( __( 'Could not retrieve Dropbox Credentials.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		$args = array();
		if ( self::get_state() ) {
			$args['state'] = self::get_state();
		}

		self::$client = new Stevenmaguire\OAuth2\Client\Provider\Dropbox(
			array(
				'clientId'     => $config['DROPBOX_API_KEY'],
				'clientSecret' => $config['DROPBOX_API_SECRET'],
				'redirectUri'  => $config['DROPBOX_REDIRECT_URI'],
			)
		);

		// If token is refreshed, self::settings will be updated with new token before proceeding.
		self::maybe_refresh_token();

		if ( ! empty( self::$settings['oauth_token'] ) ) {
			try {
				self::$api = new dbx\Client( self::$settings['oauth_token'], 'Solid Backups v' . pb_backupbuddy::settings( 'version' ) );
			} catch ( \Exception $e ) {
				self::error( 'Dropbox Error: ' . $e->getMessage() );
				return false;
			}
		}

		return self::$client;
	}

	/**
	 * Create a redirect URL to Dropbox.
	 *
	 * @param bool $save_state  Should the state be added to settings.
	 *
	 * @return string  Redirect URL.
	 */
	public static function init_client( $save_state = true ) {
		if ( false === self::get_client() ) {
			return false;
		}

		$url = self::$client->getAuthorizationUrl(
			array(
				'redirect_uri'      => self::get_config( 'DROPBOX_REDIRECT_URI' ),
				'state'             => backupbuddy_get_oauth_source_url( 'dropbox' ),
				'token_access_type' => 'offline',
			)
		);

		if ( true === $save_state ) {
			self::set_state( self::$client->getState() );
		}

		return $url;
	}

	/**
	 * Check if state exists and contains token.
	 *
	 * @return bool  If connected to Dropbox.
	 */
	public static function is_connected() {
		if ( false === self::$client ) {
			return false;
		}

		$state = self::$client->getState();

		if ( ! $state ) {
			return false;
		}

		if ( ! self::$settings['oauth_token'] ) {
			return false;
		}

		if ( false === self::$api ) {
			return false;
		}

		return true;
	}

	/**
	 * Make sure we're ready to use the API.
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
			self::error( __( 'There was a problem connecting Dropbox. Please contact support for assistance.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		if ( false === self::$api ) {
			self::error( __( 'There was a problem initializing the Dropbox API. Please contact support for assistance.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		return true;
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
			self::error( __( 'There was a problem initializing Dropbox. Please contact support for assistance.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		self::init_client(); // Create new client or restore stored session.

		if ( self::$api ) {
			return true;
		}

		if ( empty( self::$settings['oauth_code'] ) ) {
			pb_backupbuddy::status( 'details', __( 'Could not connect to Dropbox client. Missing OAuth code. May not be authorized yet.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		// This will log its own error if it fails.
		$token = self::maybe_refresh_token();
		if ( false !== $token ) {
			// We got our token.
			return true;
		}

		try {
			// Obtain the token using the code received by the Dropbox API.
			$token = self::$client->getAccessToken(
				'authorization_code',
				array(
					'code' => self::$settings['oauth_code'],
				)
			);

		} catch ( Exception $e ) {
			self::error(
				sprintf(
					__( 'Error retrieving Dropbox authorization code. Message: `%s`', 'it-l10n-backupbuddy' ),
					$e->getMessage()
				)
			);
			return false;
		}

		// Persist the Dropbox client's state for future API requests.
		try {
			self::$settings['oauth_token']         = $token->getToken();
			self::$settings['refresh_token']       = $token->getRefreshToken();
			self::$settings['oauth_token_expires'] = $token->getExpires();
		} catch ( \Exception $e ) {
			self::error(
				sprintf(
					__( 'Error retrieving Dropbox authentication token: `%s`', 'it-l10n-backupbuddy' ),
					$e->getMessage()
				)
			);
			return false;
		}

		self::set_state( self::$client->getState() );

		return true;
	} // connect.

	/**
	 * Refresh the Token if enough time has passed.
	 *
	 * Note that false does not necessarily mean an error. It simply may not be time to refresh.
	 *
	 * @return bool  Token, if it was refreshed, else false.
	 */
	private static function maybe_refresh_token() {

		// WIthout a refresh token, we can't refresh.
		if ( empty( self::$settings['refresh_token'] ) ) {
			return false;
		}

		// if token expires more than 10 minutes from now, don't refresh.
		$ten_minutes_from_now = time() + ( 60 * 10 ); // Ten minutes from now.
		$expiry               = self::$settings['oauth_token_expires'];

		// If no expiry found, continue on. Else, check to see if token expires in the next 10 minutes.
		if ( ! empty( $expiry ) && ( $ten_minutes_from_now < $expiry ) ) {
			return false;
		}

		$config = self::get_config();
		if ( ! $config ) {
			self::error( __( 'Could not retrieve Dropbox Credentials when attempting to refresh the token.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		// New client without redirectUri.
		self::$client = new Stevenmaguire\OAuth2\Client\Provider\Dropbox(
			array(
				'clientId'     => $config['DROPBOX_API_KEY'],
				'clientSecret' => $config['DROPBOX_API_SECRET'],
			)
		);

		if ( ! self::$client ) {
			self::error( __( 'Could not fetch a new Client when attempting to refresh the token.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		try {
			// This must be located before we update the token data.
			$destination_id = self::get_destination_id_by_oauth_expiration();

			$token = self::$client->getAccessToken(
				'refresh_token',
				array(
					'refresh_token' => self::$settings['refresh_token'],
				)
			);

			self::$settings['oauth_token']         = $token->getToken();
			self::$settings['oauth_token_expires'] = $token->getExpires();
			$refresh_token                         = $token->getRefreshToken();
			self::$settings['refresh_token']       =  ! empty( $refresh_token ) ? $refresh_token : self::$settings['refresh_token'];

			/*
			 * If we have located the destination ID, save the settings.
			 * If not, this will at least temporarily update the settings in the class property.
			 */
			if ( false !== $destination_id ) {
				// Update the Settings.
				pb_backupbuddy::$options['remote_destinations'][ $destination_id ] = self::$settings;
				pb_backupbuddy::save();
			}

			// Update the state.
			self::set_state( self::$client->getState() );
			return true;
		} catch ( \Exception $e ) {
			self::error( __( 'Error refreshing the Dropbox authentication token: ', 'it-l10n-backupbuddy' ) . $e->getMessage() );
			return false;
		}

		return false;
	}

	/**
	 * Redirect user to Dropbox Login.
	 *
	 * This will authenticate the user with Dropbox,
	 * then redirect the user to our OAuth server,
	 * which will redirect the user back to their site
	 * along with a "code" variable.
	 *
	 * This is triggered in init_admin.php with a GET variable "oauth-authorize".
	 */
	public static function oauth_redirect() {
		if ( false === self::init() ) {
			self::error( __( 'Could not initialize Dropbox.', 'it-l10n-backupbuddy' ) );
			die( esc_html__( 'There was a problem initializing Dropbox. Please contact support for assistance.', 'it-l10n-backupbuddy' ) );
		}

		$url = self::init_client( false );

		header( 'HTTP/1.1 302 Found', true, 302 );
		header( "Location: $url" );
		exit();
	}

	/**
	 * Locate the proper Destination ID using a matching expiration date.
	 *
	 * @return int|false  Destination ID or false if not found.
	 */
	protected static function get_destination_id_by_oauth_expiration() {
		$destinations  = pb_backupbuddy::$options['remote_destinations'];
		$token         = self::$settings['oauth_token'];
		foreach( $destinations as $id => $settings ) {
			if ( ( 'dropbox3' ===  $settings['type']  ) && ( $token === $settings['oauth_token'] ) ) {
				return $id;
			}
		}

		return false;
	}

	/**
	 * Get Dropbox Quota.
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
			$quota = self::$api->getSpaceUsage();
		} catch ( \Exception $e ) {
			self::error( __( 'There was an error fetching Dropbox quota: ', 'it-l10n-backupbuddy' ) . $e->getMessage() );
			return false;
		}

		return $quota;
	}

	/**
	 * Get the root folder ID.
	 *
	 * @param string $return          What to return. Default is folder array.
	 * @param bool   $trailing_slash  If trailing slash should be added.
	 *
	 * @return string|false  Folder ID, folder name, folder array, or false on error.
	 */
	public static function get_root_folder( $return = 'array', $trailing_slash = true ) {
		if ( empty( self::$settings['dropbox_folder_path'] ) ) {
			return '/';
		}

		$path = rtrim( self::$settings['dropbox_folder_path'], '/' );
		if ( $trailing_slash ) {
			$path .= '/';
		}

		if ( 'name' === $return ) {
			return basename( self::$settings['dropbox_folder_name'] );
		} elseif ( 'path' === $return ) {
			return $path;
		}

		$folder = self::file_info( $path );
		if ( ! $folder || ! is_array( $folder ) ) {
			return false;
		}

		return $folder;
	} // get_root_folder.

	/**
	 * Get Dropbox folder array.
	 *
	 * @param string $parent  Parent Folder ID.
	 *
	 * @return array  Array of folder objects.
	 */
	public static function get_folders( $parent = false ) {
		if ( ! self::is_ready() ) {
			return false;
		}

		if ( ! $parent ) {
			$parent = self::get_root_folder( 'path' );
		}

		$items   = self::get_folder_contents( $parent, 'alpha' );
		$folders = array();

		if ( ! is_array( $items ) ) {
			return array();
		}

		foreach ( $items as $item ) {
			if ( ! $item || empty( $item['.tag'] ) ) {
				continue;
			}
			if ( 'folder' !== $item['.tag'] ) {
				continue;
			}
			$folders[] = $item;
		}

		return $folders;
	} // get_folders.

	/**
	 * Get contents of folder.
	 *
	 * @param string $folder  Folder to get contents.
	 * @param string $sort    If files should be sorted, alpha or date.
	 *
	 * @return array  Array of items.
	 */
	public static function get_folder_contents( $folder = false, $sort = false ) {
		if ( ! self::is_ready() ) {
			return false;
		}

		if ( ! $folder ) {
			$folder = self::get_root_folder( 'path' );
		}
		if ( '/' !== $folder ) {
			$folder = rtrim( $folder, '/' );
		}

		try {
			$response = self::$api->getMetadataWithChildren( $folder );
		} catch ( \Exception $e ) {
			self::error( __( 'There was an error retrieving Dropbox folder contents for', 'it-l10n-backupbuddy' ) . ' `' . $folder . '`: ' . $e->getMessage() );
			return false;
		}

		if ( is_string( $response ) && ! empty( $response ) ) {
			self::error( __( 'Error getting Dropbox folder contents for', 'it-l10n-backupbuddy' ) . ' `' . $folder . '`: ' . $response );
			return false;
		}

		if ( ! $response || ! is_array( $response ) || empty( $response['entries'] ) ) {
			return array();
		}

		$contents = $response['entries'];
		if ( 'alpha' === $sort ) {
			usort( $contents, array( 'pb_backupbuddy_destination_dropbox3', 'sort_files_alpha' ) );
		} elseif ( 'date' === $sort ) {
			usort( $contents, array( 'pb_backupbuddy_destination_dropbox3', 'sort_files_date' ) );
		}

		return $contents;
	}

	/**
	 * Sort files alphabetically.
	 *
	 * @param array $a  Dropbox item.
	 * @param array $b  Dropbox item.
	 *
	 * @return int  1 or -1 depending on name.
	 */
	public static function sort_files_alpha( $a, $b ) {
		if ( ! is_array( $a ) || ! is_array( $b ) || empty( $a['name'] ) || empty( $b['name'] ) ) {
			return 0;
		}
		return strcmp( strtolower( $a['name'] ), strtolower( $b['name'] ) );
	}

	/**
	 * Sort files by modified date.
	 *
	 * @param array $a  Dropbox item.
	 * @param array $b  Dropbox item.
	 *
	 * @return int  1 or -1 depending on date.
	 */
	public static function sort_files_date( $a, $b ) {
		if ( ! is_array( $a ) || ! is_array( $b ) || empty( $a['client_modified'] ) || empty( $b['client_modified'] ) ) {
			return 0;
		}
		return strtotime( $a['client_modified'] ) > strtotime( $b['client_modified'] ) ? -1 : 1;
	}

	/**
	 * Create a folder.
	 *
	 * @param string $path  Folder Name including path.
	 *
	 * @return object  Create Folder response.
	 */
	public static function create_folder( $path ) {
		if ( ! self::is_ready() ) {
			return false;
		}
		try {
			$response = self::$api->createFolderV2( $path );
		} catch ( \Exception $e ) {
			self::error( __( 'Error creating new Dropbox folder: ', 'it-l10n-backupbuddy' ) . $e->getMessage() );
			return false;
		}

		if ( ! is_array( $response ) ) {
			$error = is_string( $response ) ? ': ' . $response : '.';
			self::error( __( 'Invalid response from Dropbox API.', 'it-l10n-backupbuddy' ) . $error );
			return false;
		}

		return $response;
	}

	/**
	 * Get properties of a file by ID or path.
	 *
	 * @param string $file  ID of file path.
	 *
	 * @return false|array  File properties or false.
	 */
	public static function file_info( $file ) {
		if ( ! self::is_ready() ) {
			return false;
		}

		try {
			$info = self::$api->getMetadata( $file );
		} catch ( \Exception $e ) {
			self::error( __( 'Unexpected response retrieving Dropbox file info for file: ', 'it-l10n-backupbuddy' ) . $file );
			return false;
		}

		return $info;
	}

	/**
	 * Check if remote file exixts.
	 *
	 * @param string $file  Remote path to file.
	 *
	 * @return bool  If remote file exists.
	 */
	public static function file_exists( $file ) {
		if ( ! self::is_ready() ) {
			return false;
		}

		$info = self::file_info( $file );
		if ( false === $info ) {
			return false;
		}
		return true;
	}

	/**
	 * List files in this destination & directory.
	 *
	 * @param array  $settings  Destination settings.
	 * @param string $mode      File list mode.
	 *
	 * @return array  Array of items in directory.
	 */
	public static function listFiles( $settings = false, $mode = 'default' ) {
		if ( false !== $settings ) {
			self::add_settings( $settings );
		}

		$folder_path  = self::get_root_folder( 'path' );
		$remote_files = self::get_folder_contents( $folder_path, 'date' );

		if ( ! is_array( $remote_files ) ) {
			self::error( __( 'Unexpected response retrieving Dropbox folder contents for folder: ', 'it-l10n-backupbuddy' ) . $folder_path );
			return array();
		}

		$backup_list       = array();
		$backup_sort_dates = array();

		$prefix = backupbuddy_core::backup_prefix();

		if ( $prefix ) {
			$prefix .= '-';
		} else {
			$prefix = '';
		}

		foreach ( $remote_files as $index => $remote_file ) {
			// Skip anything not a file.
			if ( 'file' !== $remote_file['.tag'] ) {
				continue;
			}

			$filename = $remote_file['name'];

			// Skip non-zip files.
			if ( '.zip' !== substr( $filename, -4 ) ) {
				continue;
			}

			// Appears to not be a backup file for this site.
			if ( strpos( $filename, 'backup-' . $prefix ) === false ) {
				continue;
			}

			$backup = $filename;

			$backup_type = backupbuddy_core::getBackupTypeFromFile( basename( $backup ) );

			if ( ! $backup_type ) {
				continue;
			}

			$backup_date   = backupbuddy_core::parse_file( $backup, 'datetime' );
			$download_link = false;

			if ( $remote_file['is_downloadable'] ) {
				$download_link = admin_url() . sprintf(
					'?dropbox-destination-id=%s&dropbox-download=%s',
					backupbuddy_backups()->get_destination_id(),
					rawurlencode( $backup )
				);
			}

			$backup_array = array(
				array(
					$backup,
					$backup_date,
					$download_link,
				),
				backupbuddy_core::pretty_backup_type( $backup_type ),
				pb_backupbuddy::$format->file_size( $remote_file['size'] ),
			);

			if ( 'default' === $mode ) {
				$copy_link = '&cpy=' . rawurlencode( $backup );
				$actions   = array();

				if ( $download_link ) {
					$actions[ $download_link ] = __( 'Download Backup', 'it-l10n-backupbuddy' );
				}

				$actions[ $copy_link ] = __( 'Copy to Local', 'it-l10n-backupbuddy' );

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
	} // listFiles.

	/**
	 * Send one or more files up to Dropbox
	 *
	 * @param array  $settings             Destination Settings array.
	 * @param array  $file                 Path of file to send.
	 * @param string $send_id              Send ID.
	 * @param bool   $delete_after         Delete after successful send.
	 * @param bool   $delete_remote_after  Delete remote file after (for tests).
	 *
	 * @return bool  True on success single-process, array on multipart with remaining steps, else false (failed).
	 */
	public static function send( $settings = false, $file = false, $send_id = '', $delete_after = false, $delete_remote_after = false ) {
		if ( ! self::is_ready( $settings ) ) {
			return false;
		}

		if ( is_array( $file ) ) {
			$file = $file[0];
		}

		if ( ! file_exists( $file ) ) {
			return false;
		}

		$backup_type = backupbuddy_core::getBackupTypeFromFile( $file );

		pb_backupbuddy::status( 'details', 'Dropbox (v3) send function started. Remote send id: `' . $send_id . '`.' );

		// Continue Multipart Chunked Upload.
		if ( ! empty( self::$settings['_chunk_upload_id'] ) ) {

			$file = self::$settings['_chunk_file'];
			pb_backupbuddy::status( 'details', 'Dropbox (v3) preparing to send chunked multipart upload part ' . ( self::$settings['_chunk_sent_count'] + 1 ) . ' of ' . self::$settings['_chunk_total_count'] . ' with set chunk size of `' . self::$settings['max_chunk_size'] . '` MB. Dropbox Upload ID: `' . self::$settings['_chunk_upload_id'] . '`.' );

			// Prevent timeout and memory issues.
			pb_backupbuddy::set_greedy_script_limits();

			pb_backupbuddy::status( 'details', 'Opening file `' . basename( $file ) . '` to send.' );
			$f = @fopen( $file, 'r' );
			if ( false === $f ) {
				self::error( 'Error #87954435. Unable to open file `' . $file . '` to send to Dropbox.' );
				return false;
			}

			// Seek to next chunk location.
			pb_backupbuddy::status( 'details', 'Seeking file to byte `' . self::$settings['_chunk_next_offset'] . '`.' );
			if ( 0 != fseek( $f, self::$settings['_chunk_next_offset'] ) ) { // return of 0 is success.
				self::error( 'Unable to seek file to proper location offset `' . self::$settings['_chunk_next_offset'] . '`.' );
			} else {
				pb_backupbuddy::status( 'details', 'Seek success.' );
			}

			// Read this file chunk into memory.
			pb_backupbuddy::status( 'details', 'Reading chunk into memory.' );
			$data = fread( $f, self::$settings['_chunk_maxsize'] );
			if ( false === $data ) {
				self::error( 'Dropbox Error #484938376: Unable to read in chunk.' );
				return false;
			}

			pb_backupbuddy::status( 'details', 'About to put chunk to Dropbox for continuation.' );
			$send_time = -( microtime( true ) );
			try {
				$response = self::$api->chunkedUploadContinue( self::$settings['_chunk_upload_id'], self::$settings['_chunk_next_offset'], $data );
			} catch ( \Exception $e ) {
				self::error( 'Dropbox Error #8754646: ' . $e->getMessage() );
				return false;
			}

			// Examine response from Dropbox.
			if ( true === $response ) { // Upload success.
				pb_backupbuddy::status( 'details', 'Chunk upload continuation success with valid offset.' );
			} elseif ( false === $response ) { // Failed.
				self::error( 'Chunk upload continuation failed at offset `' . self::$settings['_chunk_next_offset'] . '`.' );
				return false;
			} elseif ( is_numeric( $response ) ) { // offset wrong. Update to use this.
				pb_backupbuddy::status( 'details', 'Chunk upload continuation received an updated offset response of `' . $response . '` when we tried `' . self::$settings['_chunk_next_offset'] . '`.' );
				self::$settings['_chunk_next_offset'] = $response;
				// Try resending with corrected offset.
				try {
					$response = self::$api->chunkedUploadContinue( self::$settings['_chunk_upload_id'], self::$settings['_chunk_next_offset'], $data );
				} catch ( \Exception $e ) {
					self::error( 'Dropbox Error #8263836: ' . $e->getMessage() );
					return false;
				}
				pb_backupbuddy::status( 'details', 'Chunked upload finish results: `' . print_r( $response, true ) . '`.' );
			}

			$send_time  += microtime( true );
			$data_length = strlen( $data );
			unset( $data );

			// Calculate some stats to log.
			$chunk_transfer_speed = $data_length / $send_time;
			pb_backupbuddy::status( 'details', 'Dropbox chunk transfer stats - Sent: `' . pb_backupbuddy::$format->file_size( $data_length ) . '`, Transfer duration: `' . $send_time . '`, Speed: `' . pb_backupbuddy::$format->file_size( $chunk_transfer_speed ) . '`.' );

			// Set options for subsequent step chunks.
			$session_settings = self::$settings;
			$prev_offset      = $session_settings['_chunk_next_offset'];

			$session_settings['_chunk_total_sent'] += $data_length;
			$session_settings['_chunk_offset']      = $data_length;
			$session_settings['_chunk_sent_count']++;
			$session_settings['_chunk_next_offset']       = $session_settings['_chunk_total_sent'];
			$session_settings['_chunk_transfer_speeds'][] = $chunk_transfer_speed;

			// Load destination fileoptions.
			pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #19...' );
			require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
			$fileoptions_obj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt', false, false, false );
			$result          = $fileoptions_obj->is_ok();
			$fileoptions     = false;
			if ( true !== $result ) {
				self::error( __( 'Fatal Error #9034.84838. Unable to access fileoptions data. Error: ', 'it-l10n-backupbuddy' ) . $result );
			} else {
				pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );
				$fileoptions = &$fileoptions_obj->options;
			}

			// Multipart send completed. Send finished signal to Dropbox to seal the deal.
			if ( ! $f || true === feof( $f ) ) {

				pb_backupbuddy::status( 'details', 'At end of file. Finishing transfer and notifying Dropbox of file transfer completion.' );

				$session_settings['_chunk_upload_id'] = ''; // Unset since chunking finished.
				$destination_path = self::get_root_folder( 'path' ) . basename( $file );

				try {
					$response = self::$api->chunkedUploadFinish( self::$settings['_chunk_upload_id'], $destination_path, dbx\WriteMode::add(), filesize( $file ) );
				} catch ( \Exception $e ) {
					self::error( 'Dropbox Error #549838979: ' . $e->getMessage() );
					return false;
				}
				pb_backupbuddy::status( 'details', 'Chunked upload finish results: `' . print_r( $response, true ) . '`.' );
				$local_size = filesize( self::$settings['_chunk_file'] );
				if ( $local_size != $response['size'] ) {
					self::error( 'Error #8958944. Dropbox reported file size differs from local size. The file upload may have been corrupted. Local size: `' . $local_size . '`. Remote size: `' . $response['size'] . '`.' );
					return false;
				}
				if ( false !== $fileoptions ) {
					$fileoptions['write_speed']       = array_sum( $session_settings['_chunk_transfer_speeds'] ) / $session_settings['_chunk_sent_count'];
					$fileoptions['_multipart_status'] = 'Sent part ' . $session_settings['_chunk_sent_count'] . ' of ' . $session_settings['_chunk_total_count'] . '.';
					$fileoptions['finish_time']       = microtime( true );
					$fileoptions['status']            = 'success';
					$fileoptions_obj->save();
				}
				unset( $fileoptions_obj );
			}
			fclose( $f );

			pb_backupbuddy::status( 'details', 'Sent chunk number `' . $session_settings['_chunk_sent_count'] . '` to Dropbox with upload ID: `' . $session_settings['_chunk_upload_id'] . '`. Next offset: `' . $session_settings['_chunk_next_offset'] . '`.' );

			// Schedule to continue if anything is left to upload for this multipart of any individual files.
			if ( ! empty( $session_settings['_chunk_upload_id'] ) ) {
				pb_backupbuddy::status( 'details', 'Dropbox multipart upload has more parts left. Scheduling next part send.' );

				$cron_time    = time();
				$cron_args    = array( $session_settings, $file, $send_id, $delete_after, $delete_remote_after );
				$cron_hash_id = md5( $cron_time . serialize( $cron_args ) );
				$cron_args[]  = $cron_hash_id;

				$schedule_result = backupbuddy_core::schedule_single_event( $cron_time, 'destination_send', $cron_args );
				if ( true === $schedule_result ) {
					pb_backupbuddy::status( 'details', 'Next Dropbox chunk step cron event scheduled.' );
				} else {
					self::error( 'Next Dropbox chunk step cron event FAILED to be scheduled.' );
				}

				backupbuddy_core::maybe_spawn_cron();

				return array( $session_settings['_chunk_upload_id'], 'Sent ' . $session_settings['_chunk_sent_count'] . ' of ' . $session_settings['_chunk_total_count'] . ' parts.' );
			}
		} else { // Not continuing chunk send.

			// Prevent timeout and memory issues.
			pb_backupbuddy::set_greedy_script_limits();

			$file_size = filesize( $file );

			pb_backupbuddy::status( 'details', 'Opening file `' . basename( $file ) . '` to send.' );
			$f = @fopen( $file, 'r' );
			if ( false === $f ) {
				self::error( 'Error #8457573. Unable to open file `' . $file . '` to send to Dropbox.' );
				return false;
			}

			if ( self::$settings['max_chunk_size'] >= 5 && ( $file_size / 1024 / 1024 ) > self::$settings['max_chunk_size'] ) { // chunked send.

				pb_backupbuddy::status( 'details', 'File exceeds chunking limit of `' . self::$settings['max_chunk_size'] . '` MB. Using chunked upload for this file transfer.' );

				// Read a small chunk first (1 MB).
				$initial_chunk = 1 * 1024 * 1024;
				pb_backupbuddy::status( 'details', 'Reading first chunk into memory.' );
				$data = fread( $f, $initial_chunk );
				if ( false === $data ) {
					@fclose( $f );
					pb_backupbuddy::status( 'error', 'Dropbox Error #328663: Unable to read in chunk.' );
					return false;
				}

				// Start chunk upload to get upload ID. Sends first chunk piece.
				$send_time = -( microtime( true ) );
				pb_backupbuddy::status( 'details', 'About to start chunked upload for file `' . basename( $file ) . '` to Dropbox (v3).' );

				try {
					$session_id = self::$api->chunkedUploadStart( $data );
				} catch ( \Exception $e ) {
					@fclose( $f );
					self::error( 'Dropbox Error: ' . $e->getMessage() );
					return false;
				}
				$send_time += microtime( true );
				@fclose( $f );
				$data_length = strlen( $data );
				unset( $data );

				// Calculate some stats to log.
				$chunk_transfer_speed = $data_length / $send_time;
				pb_backupbuddy::status( 'details', 'Dropbox chunk transfer stats - Sent: `' . pb_backupbuddy::$format->file_size( $data_length ) . '`, Transfer duration: `' . $send_time . '`, Speed: `' . pb_backupbuddy::$format->file_size( $chunk_transfer_speed ) . '`.' );

				// Set options for subsequent step chunks.
				$session_settings     = self::$settings;
				$max_chunk_size_bytes = self::$settings['max_chunk_size'] * 1024 * 1024;

				$session_settings['_chunk_file']        = $file;
				$session_settings['_chunk_maxsize']     = $max_chunk_size_bytes;
				$session_settings['_chunk_upload_id']   = $session_id;
				$session_settings['_chunk_offset']      = $data_length;
				$session_settings['_chunk_total_sent']  = $data_length;
				$session_settings['_chunk_next_offset'] = $data_length; // Send first chunk after session created.
				$session_settings['_chunk_sent_count']  = 1;
				$session_settings['_chunk_total_count'] = (int) ceil( ( $file_size - $initial_chunk ) / $max_chunk_size_bytes ) + 1;
				pb_backupbuddy::status( 'details', 'Sent first chunk to Dropbox with upload ID: `' . $session_settings['_chunk_upload_id'] . '`. Offset: `' . $session_settings['_chunk_offset'] . '`.' );

				// Schedule next chunk to send.
				pb_backupbuddy::status( 'details', 'Dropbox (v3) scheduling send of next part(s).' );

				$cron_time    = time();
				$cron_args    = array( $session_settings, $file, $send_id, $delete_after, $delete_remote_after );
				$cron_hash_id = md5( $cron_time . serialize( $cron_args ) );
				$cron_args[]  = $cron_hash_id;

				if ( false === backupbuddy_core::schedule_single_event( $cron_time, 'destination_send', $cron_args ) ) {
					self::error( 'Error #948844: Unable to schedule next Dropbox (v3) cron chunk.' );
					return false;
				} else {
					pb_backupbuddy::status( 'details', 'Success scheduling next cron chunk.' );
				}

				backupbuddy_core::maybe_spawn_cron();

				pb_backupbuddy::status( 'details', 'Dropbox (v3) scheduled send of next part(s). Done for this cycle.' );

				return array( $session_settings['_chunk_upload_id'], 'Sent 1 of ' . $session_settings['_chunk_total_count'] . ' parts.' );

			} else { // normal (non-chunked) send.

				pb_backupbuddy::status( 'details', 'Dropbox send not set to be chunked.' );
				pb_backupbuddy::status( 'details', 'About to put file `' . basename( $file ) . '` (' . pb_backupbuddy::$format->file_size( $file_size ) . ') to Dropbox (v3).' );
				pb_backupbuddy::status( 'details', 'Send Directory: ' . self::get_root_folder( 'path' ) );

				$destination_path = self::get_root_folder( 'path' ) . basename( $file );

				$send_time = -( microtime( true ) );
				try {
					$response = self::$api->uploadFile( $destination_path, dbx\WriteMode::add(), $f, $file_size );
				} catch ( \Exception $e ) {
					@fclose( $f );
					self::error( 'Dropbox Error: ' . $e->getMessage() );
					return false;
				}
				$send_time += microtime( true );
				@fclose( $f );

				pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #18...' );
				require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
				$fileoptions_obj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt', false, false, true );
				$result          = $fileoptions_obj->is_ok();
				if ( true !== $result ) {
					self::error( __( 'Fatal Error #9034.2344848. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
					return false;
				}
				pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );
				$fileoptions = &$fileoptions_obj->options;

				// Calculate some stats to log.
				$data_length    = $file_size;
				$transfer_speed = $data_length / $send_time;
				pb_backupbuddy::status( 'details', 'Dropbox (non-chunked) transfer stats - Sent: `' . pb_backupbuddy::$format->file_size( $data_length ) . '`, Transfer duration: `' . $send_time . '`, Speed: `' . pb_backupbuddy::$format->file_size( $transfer_speed ) . '/sec`.' );
				$fileoptions['write_speed'] = $transfer_speed;
				$fileoptions_obj->save();
				unset( $fileoptions_obj );

			} // end normal (non-chunked) send.
		} // End non-continuation send.

		pb_backupbuddy::status( 'message', 'Success sending `' . basename( $file ) . '` to Dropbox!' );

		if ( $delete_remote_after ) {
			self::delete( false, basename( $destination_path ) );
		}

		if ( $backup_type ) {
			self::prune( $backup_type );
		}

		// End remote backup limit.
		pb_backupbuddy::status( 'details', 'Dropbox send() complete.' );

		return true; // Success if made it this far.
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

		pb_backupbuddy::status( 'details', 'Dropbox archive limit enforcement beginning.' );

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
			pb_backupbuddy::status( 'warning', 'Warning #34352453244. Dropbox was unable to determine backup type (reported: `' . $backup_type . '`) so archive limits NOT enforced for this backup.' );
		}

		if ( $limit <= 0 ) {
			pb_backupbuddy::status( 'details', 'No Dropbox archive file limit to enforce.' );
			return false;
		}

		pb_backupbuddy::status( 'details', 'Dropbox backup archive limit of `' . $limit . '` of type `' . $backup_type . '` based on destination settings.' );

		// Get file listing.
		$search_count = 1;
		$folder_path  = self::get_root_folder( 'path' );
		$remote_files = self::get_folder_contents( $folder_path, 'date' );

		if ( ! $remote_files ) {
			pb_backupbuddy::status( 'details', 'No Dropbox remote files found.' );
			return false;
		}

		// Filter backups by backup type.
		$backups = array();
		$prefix  = backupbuddy_core::backup_prefix();

		if ( $prefix ) {
			$prefix .= '-';
		} else {
			$prefix = '';
		}

		foreach ( $remote_files as $index => $remote_file ) {
			// Skip anything not a file.
			if ( 'file' !== $remote_file['.tag'] ) {
				continue;
			}

			$filename = $remote_file['name'];

			// Skip non-zip files.
			if ( '.zip' !== substr( $filename, -4 ) ) {
				continue;
			}

			// Appears to not be a backup file for this site.
			if ( strpos( $filename, 'backup-' . $prefix ) === false ) {
				continue;
			}

			// Appears to not be the same type of backup.
			if ( strpos( $filename, '-' . $backup_type . '-' ) === false ) {
				continue;
			}

			$backup_date = backupbuddy_core::parse_file( $filename, 'datetime' );

			$backups[ basename( $filename ) ] = $backup_date;
		}

		arsort( $backups );
		$backup_count        = count( $backups );
		$delete_failures     = array();
		$backup_delete_count = 0;

		pb_backupbuddy::status( 'details', 'Dropbox found `' . count( $backups ) . '` backups of this type when checking archive limits.' );

		if ( $backup_count > $limit ) {
			$delete_backups      = array_slice( $backups, $limit );
			$backup_delete_count = count( $delete_backups );

			pb_backupbuddy::status( 'details', 'More archives (' . count( $backups ) . ') than limit (' . $limit . ') allows. Pruning...' );

			if ( ! class_exists( 'pb_backupbuddy_destinations' ) ) {
				require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
			}

			foreach ( $delete_backups as $filename => $backup_time ) {
				pb_backupbuddy::status( 'details', 'Deleting excess file `' . $filename . '`...' );

				if ( true !== pb_backupbuddy_destinations::delete( self::$settings, $filename ) ) {
					pb_backupbuddy::status( 'details', 'Unable to delete excess Dropbox file `' . $filename . '`. Details: `' . print_r( $pb_backupbuddy_destination_errors, true ) . '`.' );
					$delete_failures[] = $filename;
				}
			}

			pb_backupbuddy::status( 'details', 'Finished pruning excess backups.' );
		}

		$delete_fail_count = count( $delete_failures );

		if ( $delete_fail_count ) {
			$error_message = 'Dropbox remote limit could not delete ' . $delete_fail_count . ' backups. (' . implode( $delete_failures, ', ' );
			self::error( $error_message, 'mail' );
		}

		pb_backupbuddy::status( 'details', 'Dropbox completed archive limiting.' );

		if ( $backup_delete_count === $delete_fail_count ) {
			// No pruning has occurred.
			return false;
		}

		return true;
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
		$files   = self::get_folder_contents();

		if ( ! is_array( $files ) ) {
			return false;
		}

		// Create an array of backup filenames.
		foreach ( $backups_array as $backup_array ) {
			$backups[] = $backup_array[0][0];
		}

		$prefix = backupbuddy_core::backup_prefix();

		if ( $prefix ) {
			$prefix .= '-';
		} else {
			$prefix = '';
		}

		// Loop through all files looking for dat orphans.
		foreach ( $files as $index => $file ) {
			if ( 'file' !== $file['.tag'] ) {
				continue;
			}

			$filename = $file['name'];

			// Skip if not a .dat file.
			if ( '.dat' !== substr( $filename, -4 ) ) {
				continue;
			}

			// Appears to not be a dat file for this site.
			if ( strpos( $filename, 'backup-' . $prefix ) === false ) {
				continue;
			}

			// Skip dat files with backup files.
			$backup_name = str_replace( '.dat', '.zip', $filename ); // TODO: Move to backupbuddy_data_file() method.
			if ( in_array( $backup_name, $backups, true ) ) {
				continue;
			}

			$orphans[] = $filename;
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
		$backups = self::listFiles( $settings );
		if ( ! is_array( $backups ) || ! count( $backups ) ) {
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
	 * Copy remote file to local
	 *
	 * @param array  $settings     Destination settings array.
	 * @param string $remote_file  Remote file to download.
	 * @param bool   $local_file   Local File location.
	 *
	 * @return bool  If successful.
	 */
	public static function getFile( $settings = array(), $remote_file = '', $local_file = false ) {
		if ( ! self::is_ready( $settings ) ) {
			return false;
		}

		if ( ! $remote_file ) {
			self::error( __( 'Missing required remote file parameter for Dropbox file copy.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		if ( ! $local_file ) {
			$local_file = backupbuddy_core::getBackupDirectory() . basename( $remote_file );
		}

		$f = @fopen( $local_file, 'w+' );
		if ( false === $f ) {
			self::error( 'Error #54894985: Unable to open local file for writing `' . esc_attr( $local_file ) . '`.' );
			return false;
		}

		if ( ! self::file_exists( self::get_root_folder( 'path' ) . $remote_file ) ) {
			self::error( 'Error #202007131155: Requested remote Dropbox file not found `' . self::get_root_folder( 'path' ) . $remote_file . '`.' );
			return false;
		}

		try {
			$file_meta = self::$api->getFile( self::get_root_folder( 'path' ) . $remote_file, $f );
		} catch ( \Exception $e ) {
			fclose( $f );
			@unlink( $local_file );
			self::error( sprintf( __( 'There was an error downloading Dropbox file `%s`: ', 'it-l10n-backupbuddy' ), $remote_file ) . $e->getMessage() );
			return false;
		}

		fclose( $f );

		if ( null === $file_meta ) {
			@unlink( $local_file );
			pb_backupbuddy::status( 'error', 'Invalid or unable to access. Remote Dropbox file: `' . $remote_file . '`.' );
			return false;
		}

		if ( ! $file_meta ) {
			self::error( __( 'Dropbox File Download Error #202003031401: ', 'it-l10n-backupbuddy' ) . print_r( $file_meta, true ) );
			return false;
		}

		return true;
	}

	/**
	 * Delete file(s) from this destination.
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

		foreach ( $files as $filename ) {
			if ( ! self::file_exists( self::get_root_folder( 'path' ) . basename( $filename ) ) ) {
				pb_backupbuddy::status( 'details', 'Tried to delete Dropbox file that does not exist: ' . self::get_root_folder( 'path' ) . basename( $filename ) );
				continue;
			}

			pb_backupbuddy::status( 'details', 'Deleting Dropbox file `' . basename( $filename ) . '`.' );

			try {
				$result = self::$api->delete( self::get_root_folder( 'path' ) . basename( $filename ) );
			} catch ( \Exception $e ) {
				self::error( $e->getMessage(), 'echo' );
				return false;
			}

			if ( ! is_array( $result ) ) {
				self::error( $result, 'echo' );
				return false;
			}

			pb_backupbuddy::status( 'details', 'Dropbox file `' . $filename . '` deleted.' );
		}

		return true;
	} // delete.

	/**
	 * Test Upload to Dropbox
	 *
	 * @param array  $settings  Destination settings.
	 * @param string $file      File to use for testing.
	 *
	 * @return bool  True on success, string error message on failure.
	 */
	public static function test( $settings = false, $file = false ) {
		if ( true !== self::is_ready( $settings ) ) {
			echo 'Could not connect to Dropbox.';
			return false;
		}

		pb_backupbuddy::status( 'details', 'Testing Dropbox destination. Sending remote-send-test.php.' );

		if ( false !== $file ) {
			$files = array( $file );
		} else {
			$files = array( pb_backupbuddy::plugin_path() . '/destinations/remote-send-test.php' );
		}

		$result = self::send( false, $files, pb_backupbuddy::random_string( 12 ), false, true );

		if ( true !== $result ) {
			echo 'Dropbox test file send failed.';
			return false;
		}

		return true;
	} // test.

	/**
	 * Force File Download.
	 *
	 * @param array  $settings  Destination settings.
	 * @param string $file      Dropbox filename.
	 *
	 * @return false|void  False on error, void when successful.
	 */
	public static function force_download( $settings = false, $file = '' ) {
		if ( ! self::is_ready( $settings ) ) {
			return false;
		}

		if ( ! $file ) {
			self::error( __( 'Missing Dropbox File for download.', 'it-l10n-backupbuddy' ), 'echo' );
			return false;
		}

		try {
			$download = self::$api->createTemporaryDirectLink( self::get_root_folder( 'path' ) . $file );
		} catch ( \Exception $e ) {
			self::error( __( 'There was an error getting Dropbox file URL for download: ', 'it-l10n-backupbuddy' ) . $e->getMessage(), 'echo' );
			return false;
		}

		if ( is_string( $download ) ) {
			self::error( __( 'There was an error getting Dropbox file URL for download: ', 'it-l10n-backupbuddy' ) . $download, 'echo' );
			return false;
		}

		header( 'Location: ' . $download['link'] );
		exit();
	}

	/**
	 * Folder Selector UI.
	 *
	 * @param int $destination_id  Destination ID.
	 */
	public static function folder_selector( $destination_id ) {
		include_once pb_backupbuddy::plugin_path() . '/destinations/dropbox3/views/folder-selector.php';

		if ( ! is_numeric( $destination_id ) ) {
			$destination_id = 'NEW';
		}
		?>
		<script>
			jQuery( function( $ ) {
				var $destination_wrap = BackupBuddy.DropboxFolderSelector.get_destination_wrap( '<?php echo esc_html( $destination_id ); ?>' ),
					$template = $( '.backupbuddy-dropbox-folder-selector[data-is-template="true"]' ).clone().removeAttr( 'data-is-template' ),
					$folder_row = $destination_wrap.find( 'td.backupbuddy-dropbox-folder-row:first' );

				$template.show().appendTo( $folder_row ).attr( 'data-destination-id', '<?php echo esc_html( $destination_id ); ?>' );

				BackupBuddy.DropboxFolderSelector.folder_select( '<?php echo esc_html( $destination_id ); ?>' );
			});
		</script>
		<?php
	} // folder_selector.

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

} // pb_backupbuddy_destination_dropbox3.
