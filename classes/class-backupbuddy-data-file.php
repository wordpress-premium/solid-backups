<?php
/**
 * Data file class for creating, compressing and parsing.
 *
 * .dat files are used to store information
 * for this plugin. They are a way to
 * quickly access information about a backup
 * zip file.
 *
 * By default, they are stored alongside the
 * .zip files in /wp-content/uploads/backupbuddy_backups/
 *
 * By default, they are also sent and stored at a remote destination
 * when a backup .zip is sent there. This allows us to quickly access
 * the information we need for backup listings and other operations without
 * first downloading the entire backup.
 *
 * @package BackupBuddy
 */

/**
 * Creates .dat file to accompany backup files.
 */
class BackupBuddy_Data_File {

	// This cannot contain the . character as it is used in comparisons.
	const EXTENSION = 'dat';

	/**
	 * Data File Version.
	 *
	 * @var string
	 */
	private $version = '1.0.0';

	/**
	 * Stores single instance of this object.
	 *
	 * @var object
	 */
	private static $instance;

	/**
	 * Backup array from create init.
	 *
	 * @var array
	 */
	private $backup_array;

	/**
	 * Path to zip file.
	 *
	 * @var string
	 */
	private $zip_file;

	/**
	 * Stores backup profile array.
	 *
	 * @var array
	 */
	private $backup_profile = array();

	/**
	 * Zip file object used for data file.
	 *
	 * @var object
	 */
	private $zip;

	/**
	 * Contains instance of zipbuddy.
	 *
	 * @var object
	 */
	private $zipbuddy;

	/**
	 * Data used for data file.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Compressed data.
	 *
	 * @var string
	 */
	private $compressed;

	/**
	 * List of files in zip.
	 *
	 * @var array
	 */
	private $zip_file_list = array();

	/**
	 * Class Constructor.
	 */
	public function __construct() {
		return $this;
	}

	/**
	 * Instance generator.
	 *
	 * @return object  BackupBuddy_Data_File instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new BackupBuddy_Data_File();
		}
		return self::$instance;
	}

	/**
	 * Gets extension of file.
	 *
	 * @todo  Move to a helper function.
	 *
	 * @param string $file  File or path to file.
	 *
	 * @return string|false  File extension or false.
	 */
	public function get_extension( $file ) {
		$file_info = pathinfo( $file );
		if ( empty( $file_info['extension'] ) ) {
			return false;
		}
		return $file_info['extension'];
	}

	/**
	 * Convert zip file path to data file path.
	 *
	 * @param string     $zip_file        Path to zip file.
	 * @param int|string $destination_id  Destination ID.
	 * @param bool       $exists_check    Checks if file exists.
	 *
	 * @return false|string  Data file path or false.
	 */
	public function get_path( $zip_file, $destination_id = false, $exists_check = true ) {
		$backup_directory = backupbuddy_core::getBackupDirectory();
		$zip_file_path    = false;

		if ( false !== $destination_id ) {
			// Check to see if dat file exists.
			$data_file      = str_replace( '.zip', '.' . self::EXTENSION, $zip_file ); // TODO: Move to method.
			$data_file_path = false;
			if ( realpath( $data_file ) === $data_file && file_exists( $data_file ) ) { // Full Path.
				$data_file_path = $data_file;
			} elseif ( file_exists( $backup_directory . basename( $data_file ) ) ) { // Filename.
				$data_file_path = $backup_directory . basename( $data_file );
			}

			if ( false === $data_file_path ) {
				// Download .dat file from remote destination.
				// .dat files should already be downloaded from remote destinations.
			}

			return $data_file_path;
		}

		if ( file_exists( $zip_file ) ) { // Full Path.
			$zip_file_path = $zip_file;
		} elseif ( file_exists( $backup_directory . $zip_file ) ) { // Filename.
			$zip_file_path = $backup_directory . $zip_file;
		}

		if ( false === $zip_file_path ) {
			return false;
		}

		$zip_info = pathinfo( $zip_file_path );

		// Not a zip file.
		if ( empty( $zip_info['extension'] ) || 'zip' !== $zip_info['extension'] ) {
			return false;
		}

		$data_file_path  = ! empty( $zip_info['dirname'] ) ? $zip_info['dirname'] . DIRECTORY_SEPARATOR : '';
		$data_file_path .= $zip_info['filename'] . '.' . self::EXTENSION;

		if ( $exists_check && ! file_exists( $data_file_path ) ) {
			return false;
		}

		return $data_file_path;
	}

	/**
	 * Locate Data file based on zip file path.
	 *
	 * @param string     $zip_file        Zip file or path to zip file.
	 * @param int|string $destination_id  Destination ID.
	 *
	 * @return false|string  Data file path or false.
	 */
	public function locate( $zip_file, $destination_id = false ) {
		if ( ! $zip_file ) {
			return false;
		}

		// Bail early if not a zip file.
		if ( '.zip' !== substr( $zip_file, -4 ) ) {
			return false;
		}

		$data_file_path = $this->get_path( $zip_file, $destination_id );

		if ( false === $data_file_path ) {
			return false;
		}

		return $data_file_path;
	}

	/**
	 * Delete Data file.
	 *
	 * @param string $data_file_path  Path to data file.
	 *
	 * @return bool  If successful.
	 */
	public function delete( $data_file_path ) {
		if ( ! $data_file_path || ! file_exists( $data_file_path ) ) {
			return false;
		}

		$file_info = pathinfo( $data_file_path );

		// Not a data file.
		if ( empty( $file_info['extension'] ) || self::EXTENSION !== $file_info['extension'] ) {
			return false;
		}

		return @unlink( $data_file_path );
	}

	/**
	 * Creates data file from zip.
	 *
	 * @param array $backup_array  Backup array.
	 *
	 * @return bool  If creation was successful or not.
	 */
	public function create( $backup_array ) {
		if ( self::creation_is_disabled() ) {
			pb_backupbuddy::status( 'details', __( 'Data file creation failed: Creation is disabled.', 'it-l10n-backupbuddy' ) );
			// This is not an error, but the .dat was not created.
			return false;
		}
		if ( ! is_array( $backup_array ) || empty( $backup_array['archive_file'] ) ) {
			pb_backupbuddy::status( 'details', __( 'Data file creation failed: Invalid backup data.', 'it-l10n-backupbuddy' ) );
			return false;
		}
		if ( ! file_exists( $backup_array['archive_file'] ) ) {
			pb_backupbuddy::status( 'details', __( 'Data file creation failed: Path to zip invalid.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		$this->backup_array = $backup_array;
		$this->zip_file     = $this->backup_array['archive_file'];
		$serial             = backupbuddy_core::parse_file( $this->zip_file, 'serial' );

		if ( ! $this->start_creation( $serial ) ) {
			pb_backupbuddy::status( 'details', __( 'Data file creation already in progress.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		if ( ! class_exists( 'pluginbuddy_zipbuddy' ) ) {
			require_once pb_backupbuddy::plugin_path() . '/lib/zipbuddy/zipbuddy.php';
		}

		$this->zipbuddy = new pluginbuddy_zipbuddy( backupbuddy_core::getBackupDirectory() );

		if ( ! empty( $this->backup_array['profile'] ) && is_array( $this->backup_array['profile'] ) ) {
			$this->backup_profile = $this->backup_array['profile'];
		}

		pb_backupbuddy::status( 'details', __( 'Building data file...', 'it-l10n-backupbuddy' ) );
		$this->build_data_array();

		// We're done with the backup array, profile and zip object.
		$this->backup_array   = null;
		$this->zipbuddy       = null;
		$this->backup_profile = null;

		pb_backupbuddy::status( 'details', __( 'Compressing data file...', 'it-l10n-backupbuddy' ) );

		$this->compress_data_array();

		pb_backupbuddy::status( 'details', __( 'Generating data file...', 'it-l10n-backupbuddy' ) );

		$checksum = $this->generate_data_file();

		$this->end_creation( $serial );

		if ( false === $checksum ) {
			pb_backupbuddy::status( 'details', __( 'Data file creation failed: Could not write data file.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		pb_backupbuddy::status( 'details', __( 'Data file created successfully.', 'it-l10n-backupbuddy' ) );

		return $checksum;
	}

	/**
	 * Create lock file to prevent multiple runs.
	 *
	 * @param string $serial  Backup Serial.
	 *
	 * @return bool  If lock file created or already exists.
	 */
	public function start_creation( $serial ) {
		$lock_dir  = backupbuddy_core::getLogDirectory();
		$lock_file = sprintf( 'backupbuddy-data-file-lock-%s.txt', $serial );
		$lock_path = trailingslashit( $lock_dir ) . $lock_file;
		if ( file_exists( $lock_path ) ) {
			return false;
		}

		$lock = fopen( $lock_path, 'w' );

		// Wipe file first.
		if ( is_resource( $lock ) ) {
			fwrite( $lock, '' );
		}

		fwrite( $lock, $serial );
		fclose( $lock );

		return true;
	}

	/**
	 * Delete lock file and clear memory.
	 *
	 * @param string $serial  Backup Serial.
	 *
	 * @return bool  If lock file deleted or not.
	 */
	public function end_creation( $serial ) {
		// Clear memory.
		$this->compressed = null;
		$this->data       = null;
		$this->zip_file   = null;

		$lock_dir  = backupbuddy_core::getLogDirectory();
		$lock_file = sprintf( 'backupbuddy-data-file-lock-%s.txt', $serial );
		$lock_path = trailingslashit( $lock_dir ) . $lock_file;
		if ( ! file_exists( $lock_path ) ) {
			return false;
		}
		return @unlink( $lock_path );
	}

	/**
	 * Get data file version.
	 *
	 * @return string  Current data file version number.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Sets up data array.
	 */
	private function build_data_array() {
		global $wp_version;

		$this->zip_file_list = $this->zipbuddy->get_file_list( $this->zip_file );

		// TODO: Check backup type and only store what is needed.
		$this->data = array(
			'_version'       => $this->get_version(), // Data file version.
			'wp_version'     => $wp_version,
			'zip_size'       => $this->get_zip_size(),
			'backup_profile' => $this->get_backup_profile(),
			'theme_data'     => $this->get_theme_data(),
			'plugin_data'    => $this->get_plugin_data(),
			'zip_contents'   => $this->get_zip_contents(),
			'recent_edits'   => pb_backupbuddy::$options['recent_edits'],
		);
	}

	/**
	 * Returns the backup profile information.
	 *
	 * @param string $data  Requested profile data key.
	 *
	 * @return array  Backup Profile array.
	 */
	private function get_backup_profile( $data = false ) {
		if ( ! is_array( $this->backup_profile ) ) {
			return false;
		}
		if ( false !== $data ) {
			if ( ! empty( $this->backup_profile[ $data ] ) ) {
				return $this->backup_profile[ $data ];
			}
			return false;
		}
		return $this->backup_profile;
	}

	/**
	 * Gets file list and formats for JSON file storage.
	 *
	 * @return array  Zip contents array.
	 */
	private function get_zip_contents() {
		$zip_contents = array();

		foreach ( $this->zip_file_list as $file_array ) {
			$file = $file_array[0];

			// Skip empty values.
			if ( ! $file ) {
				continue;
			}

			// Files and folders.
			$file_props = array(
				'path'     => $file,
				'modified' => $file_array[3],
			);

			// Add size to files.
			if ( '/' !== substr( $file, -1 ) ) {
				$file_props['size'] = $file_array[1];
			}

			$zip_contents[] = $file_props;
		}

		return $zip_contents;
	}

	/**
	 * Get the size of the zip file.
	 *
	 * @return int  Size of zip in bytes.
	 */
	private function get_zip_size() {
		return filesize( $this->zip_file );
	}

	/**
	 * Collect theme information.
	 *
	 * @return array  Array of theme data.
	 */
	private function get_theme_data() {
		$themes = array();

		foreach ( $this->zip_file_list as $file_array ) {
			$file       = $file_array[0];
			$theme_file = $this->maybe_get_theme_file( $file );

			if ( false === $theme_file ) {
				continue;
			}

			$theme_file_content = $this->zipbuddy->get_file_contents( $this->zip_file, $theme_file );
			$theme_data         = $this->maybe_get_theme_data( $theme_file_content );

			if ( false !== $theme_data ) {
				$theme_file            = str_replace( 'wp-content/themes/', '', $theme_file );
				$themes[ $theme_file ] = $theme_data;
			}
		}

		return $themes;
	}

	/**
	 * Check to see if file is theme style.css file.
	 *
	 * @param string $file  File.
	 *
	 * @return false|string  File or false if not valid.
	 */
	private function maybe_get_theme_file( $file ) {
		if ( empty( $file ) || is_array( $file ) ) {
			return false;
		}

		$prefix = '';

		if ( 'themes' === $this->get_backup_profile( 'type' ) ) {
			$prefix = 'wp-content/themes/';
		}

		// Must be in themes folder.
		$path = 'wp-content/themes/';
		if ( strlen( $prefix . $file ) <= strlen( $path ) || substr( $prefix . $file, 0, strlen( $path ) ) !== $path ) {
			return false;
		}

		// Must not be in theme subfolders.
		if ( 3 !== substr_count( $prefix . $file, '/' ) ) {
			return false;
		}

		// Must be style.css file.
		if ( 'style.css' !== substr( $prefix . $file, -9 ) ) {
			return false;
		}

		return $file;
	}

	/**
	 * Check a files contents for theme information.
	 *
	 * @param string $file_contents  Contents of file.
	 *
	 * @return false|array  Array of theme data or false if invalid.
	 */
	private function maybe_get_theme_data( $file_contents ) {
		if ( empty( $file_contents ) ) {
			return false;
		}

		$headers = array(
			'Name'    => 'Theme Name',
			'Version' => 'Version',
		);

		foreach ( $headers as $header ) {
			if ( false === strpos( $file_contents, $header ) ) {
				return false;
			}
		}

		$theme_data = array();

		foreach ( $headers as $field => $regex ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $file_contents, $match ) && $match[1] ) {
				$theme_data[ $field ] = _cleanup_header_comment( $match[1] );
			} else {
				$theme_data[ $field ] = '';
			}
		}

		// Integrity check.
		if ( 2 !== count( $theme_data ) ) {
			return false;
		}

		return $theme_data;
	}

	/**
	 * Collect plugin information.
	 *
	 * @return array  Array of plugin data.
	 */
	private function get_plugin_data() {
		$plugins  = array();
		$priority = array();
		$others   = array();
		$found    = array();

		// Grab a list of potential plugin files.
		foreach ( $this->zip_file_list as $file_array ) {
			$file        = $file_array[0];
			$plugin_file = $this->maybe_get_plugin_file( $file );

			if ( false === $plugin_file ) {
				continue;
			}

			$basename = str_replace( '.php', '', basename( $plugin_file ) );

			// Prioritize the likely files.
			if ( false !== strpos( $plugin_file, $basename . '/' . $basename . '.php' ) ) {
				$priority[] = $plugin_file;
			} elseif ( false !== strpos( $plugin_file, str_replace( '-', '_', $basename ) . '/' . $basename . '.php' ) ) {
				$priority[] = $plugin_file;
			} elseif ( false !== strpos( $plugin_file, str_replace( '_', '-', $basename ) . '/' . $basename . '.php' ) ) {
				$priority[] = $plugin_file;
			} else {
				$other[] = $plugin_file;
			}
		}

		$plugin_files = array_merge( $priority, $others );

		// Free up memory.
		unset( $file, $file_array, $priority, $others );

		// Locate plugin file that contains plugin information.
		foreach ( $plugin_files as $plugin_file ) {
			// Skip plugins we've already found files for.
			$plugin_folder = basename( dirname( $plugin_file ) );
			if ( in_array( $plugin_folder, $found, true ) ) {
				continue;
			}

			$plugin_file_content = $this->zipbuddy->get_file_contents( $this->zip_file, $plugin_file );
			$plugin_data         = $this->maybe_get_plugin_data( $plugin_file_content );

			if ( false !== $plugin_data ) {
				// Plugin file should be plugin-name/plugin-file.php.
				$plugin_file = str_replace( 'wp-content/plugins/', '', $plugin_file );
				$plugin_file = str_replace( 'wp-content/mu-plugins/', '', $plugin_file );

				$plugins[ $plugin_file ] = $plugin_data;

				// Only need 1 file per plugin.
				$found[] = $plugin_folder;
			}
		}

		// Free up memory.
		unset( $plugin_file_content, $plugin_data, $found, $plugin_files, $plugin_file, $plugin_folder );

		return $plugins;
	}

	/**
	 * Check to see if file is main plugin file.
	 *
	 * @param string $file  File.
	 *
	 * @return false|string  File or false if not valid.
	 */
	private function maybe_get_plugin_file( $file ) {
		if ( empty( $file ) || is_array( $file ) ) {
			return false;
		}

		$prefix = '';

		if ( 'plugins' === $this->get_backup_profile( 'type' ) ) {
			$prefix = 'wp-content/plugins/';
		}

		// Must be in plugins or mu-plugins folder.
		$plugin_folders = array( 'wp-content/plugins/', 'wp-content/mu-plugins/' );
		$is_plugin      = false;
		foreach ( $plugin_folders as $plugin_folder_path ) {
			if ( strlen( $prefix . $file ) <= strlen( $plugin_folder_path ) || substr( $prefix . $file, 0, strlen( $plugin_folder_path ) ) !== $plugin_folder_path ) {
				continue; // Try the next directory.
			}
			$is_plugin = true;
			break;
		}

		if ( ! $is_plugin ) {
			return false;
		}

		// Must not be in plugin subfolders.
		if ( substr_count( $prefix . $file, '/' ) > 3 ) {
			return false;
		}

		// Must be a php file.
		if ( '.php' !== substr( $file, -4 ) ) {
			return false;
		}

		return $file;
	}

	/**
	 * Check a files contents for plugin information.
	 *
	 * @param string $file_contents  Contents of file.
	 *
	 * @return false|array  Array of plugin data or false if invalid.
	 */
	private function maybe_get_plugin_data( $file_contents ) {
		if ( empty( $file_contents ) ) {
			return false;
		}

		$headers = array(
			'Name'    => 'Plugin Name',
			'Version' => 'Version',
		);

		foreach ( $headers as $header ) {
			if ( false === strpos( $file_contents, $header ) ) {
				return false;
			}
		}

		$plugin_data = array();

		foreach ( $headers as $field => $regex ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $file_contents, $match ) && $match[1] ) {
				$plugin_data[ $field ] = _cleanup_header_comment( $match[1] );
			} else {
				$plugin_data[ $field ] = '';
			}
		}

		if ( 2 !== count( $plugin_data ) ) {
			return false;
		}

		return $plugin_data;
	}

	/**
	 * JSON Encode and Compress the data array.
	 */
	private function compress_data_array() {
		if ( ! class_exists( 'BackupBuddy_Data_Compression' ) ) {
			require_once pb_backupbuddy::plugin_path() . '/classes/class-backupbuddy-data-compression.php';
		}

		$compressor       = new BackupBuddy_Data_Compression();
		$this->compressed = $compressor->compress( wp_json_encode( $this->data ) );
	}

	/**
	 * Generate data file with zip file.
	 *
	 * @return bool  If file was created successfully.
	 */
	private function generate_data_file() {
		if ( ! $this->compressed ) {
			return false;
		}

		$checksum       = md5( $this->compressed );
		$data_file_path = $this->get_path( $this->zip_file, false, false );
		if ( ! $data_file_path ) {
			return false;
		}

		$data_file = fopen( $data_file_path, 'w' );

		// Wipe file first.
		if ( is_resource( $data_file ) ) {
			fwrite( $data_file, '' );
		}
		fwrite( $data_file, $this->compressed );
		fclose( $data_file );

		return $checksum;
	}

	/**
	 * Get compression method of data file.
	 *
	 * @param string $path_to_data_file  Path to data file.
	 *
	 * @return string|false  Compression method (key) used or false if invalid.
	 */
	public function get_compression_method( $path_to_data_file ) {
		if ( ! $path_to_data_file || ! file_exists( $path_to_data_file ) ) {
			return false;
		}

		if ( ! class_exists( 'BackupBuddy_Data_Compression' ) ) {
			require_once pb_backupbuddy::plugin_path() . '/classes/class-backupbuddy-data-compression.php';
		}

		$type = mime_content_type( $path_to_data_file );

		return BackupBuddy_Data_Compression::get_method_from_type( $type );
	}

	/**
	 * Get the data file for a zip.
	 *
	 * @param string     $zip_file        Zip file.
	 * @param int|string $destination_id  Destination ID.
	 *
	 * @return array  JSON decoded data file.
	 */
	public function get( $zip_file, $destination_id = false ) {
		$data_file_path = $this->get_path( $zip_file, $destination_id );
		if ( false === $data_file_path ) {
			return false;
		}
		return $this->get_file( $data_file_path );
	}

	/**
	 * Get the data file data.
	 *
	 * @param string $path_to_data_file  Path to data file.
	 *
	 * @return array  JSON decoded data file.
	 */
	private function get_file( $path_to_data_file ) {
		$method = $this->get_compression_method( $path_to_data_file );
		if ( false === $method ) {
			return false;
		}
		$compressor      = new BackupBuddy_Data_Compression();
		$decompress_func = $compressor->get_method( $method, true );
		if ( false === $decompress_func ) {
			return false;
		}

		$file_contents = file_get_contents( $path_to_data_file );
		$json_string   = $decompress_func( $file_contents );

		return json_decode( $json_string, true );
	}

	/**
	 * Get the DAT file contents as an array.
	 *
	 * @param string $dat_file  Full path to DAT file to decode and parse.
	 *
	 * @return array|false  Array of DAT content. Bool false when unable to read.
	 */
	public function get_dat_file_array( $dat_file ) {
		pb_backupbuddy::status( 'details', __( 'Loading backup dat file.', 'it-l10n-backupbuddy' ) );

		if ( file_exists( $dat_file ) ) {
			$backupdata = file_get_contents( $dat_file );
		} else { // Missing.
			pb_backupbuddy::status(
				'error',
				sprintf(
					// @todo is this comment appropriate for the ways it is used?
					// translators: %1$s is the error number, %2$s is the file name.
					__( 'Error %1$s: Solid Backups data file (`%2$s`) missing or unreadable. There may be a problem with the backup file, the files could not be extracted (you may manually extract the zip file in this directory to manually do this portion of restore), or the files were deleted before this portion of the restore was reached.  Start the import process over or try manually extracting (unzipping) the files then starting over. Restore will not continue to protect integrity of any existing data.', 'it-l10n-backupbuddy' ),
					'#9003',
					basename( $dat_file )
				)
			);
			return false;
		}

		// Unserialize data; If it fails it then decodes the obscufated data then unserializes it. (new dat file method starting at 2.0).
		$return = ! is_serialized( $backupdata ) ? false : unserialize( $backupdata );
		if ( false === $return ) {
			// Skip first line.
			$second_line_pos = strpos( $backupdata, "\n" ) + 1;
			$backupdata      = substr( $backupdata, $second_line_pos );

			// Decode back into an array.
			$return = unserialize( base64_decode( $backupdata ) );
		}

		if ( ! is_array( $return ) ) { // Invalid DAT content.
			pb_backupbuddy::status(
				'error',
				sprintf(
					// translators: %s is the error number.
					__( 'Error %s. Unable to read/decode DAT file.', 'it-l10n-backupbuddy' ),
					'#545545'
				)
			);
			return false;
		}

		pb_backupbuddy::status(
			'details',
			esc_html(
				sprintf(
					// translators: %s is the file name.
					__( 'Successfully loaded backup dat file `%s`.', 'it-l10n-backupbuddy' ),
					$dat_file
				)
			)
		);
		$return_censored                = $return;
		$return_censored['db_password'] = '*HIDDEN*';
		$return_censored                = print_r( $return_censored, true );
		$return_censored                = str_replace( array( "\n", "\r" ), '; ', $return_censored );
		pb_backupbuddy::status( 'details', 'DAT contents: ' . $return_censored );
		return $return;
	}

	/**
	 * Returns array of dat contents on success, else string error message.
	 *
	 * @param array  $settings  Settings array.
	 * @param string $dat_file  Path to dat file.
	 *
	 * @return array  Array of dat contents, otherwise error message on fail.
	 */
	public function render_dat_contents( $settings, $dat_file ) {

		$settings = array_merge(
			array(
				'start_time'           => 0,
				'backup_type'          => '',
				'profile'              => array(),
				'serial'               => '',
				'breakout_tables'      => array(),
				'table_sizes'          => array(),
				'force_single_db_file' => false,
				'deployment_direction' => '',
				'trigger'              => '',
				'skip_database_dump'   => false,
				'db_excludes'          => array(),
				'db_includes'          => array(),
			), $settings
		);

		pb_backupbuddy::status( 'details', __( 'Creating DAT (data) file snapshotting site & backup information.', 'it-l10n-backupbuddy' ) );

		global $wpdb, $current_blog;

		$is_multisite_export = false; // $from_multisite is from a site within a network.
		$is_multisite        = $is_multisite_export;
		$upload_url          = '';
		$upload_url_rewrite  = $upload_url;
		if ( ( is_multisite() && 'scheduled' === $settings['trigger'] ) || ( is_multisite() && is_network_admin() ) ) { // MS Network Export IF ( in a network and triggered by a schedule ) OR ( in a network and logged in as network admin).
			$is_multisite = true;
		} elseif ( is_multisite() ) { // MS Export (individual site).
			$is_multisite_export = true;
			$uploads             = wp_upload_dir();
			$upload_url_rewrite  = site_url( str_replace( ABSPATH, '', $uploads['basedir'] ) ); // URL we rewrite uploads to. REAL direct url.
			$upload_url          = $uploads['baseurl']; // Pretty virtual path to uploads directory.
		}

		// Handle wp-config.php file in a parent directory.
		if ( 'full' === $settings['backup_type'] ) {
			$wp_config_parent = false;
			if ( file_exists( ABSPATH . 'wp-config.php' ) ) { // wp-config in normal place.
				pb_backupbuddy::status( 'details', 'wp-config.php found in normal location.' );
			} else { // wp-config not in normal place.
				pb_backupbuddy::status( 'message', 'wp-config.php not found in normal location; checking parent directory.' );
				if ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) { // Config in parent. Errors suppressed due to possible open_basedir restrictions.
					$wp_config_parent = true;
				} else { // Found no wp-config.php anywhere.
					if ( ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
						pb_backupbuddy::status( 'error', 'Error #839348: wp-config.php not found in normal location (`' . ABSPATH . '`) nor parent directory (`' . dirname( ABSPATH ) . '`). Check that file exists and has proper read permissions. Check log above for more errors.' );
					} else {
						pb_backupbuddy::status( 'error', 'Error #839348b: wp-config.php not found in normal location (`' . ABSPATH . '`) nor parent directory (`' . dirname( ABSPATH ) . '`) without wp-settings.php. Check that file exists and has proper read permissions. Check log above for more errors.' );
					}
				}
			}
		} else {
			$wp_config_parent = false;
		}

		global $wp_version;

		// Posts.
		$total_posts = 0;
		foreach ( wp_count_posts( 'post' ) as $counttype => $count ) {
			$total_posts += $count;
		}

		// Pages.
		$total_pages = 0;
		foreach ( wp_count_posts( 'page' ) as $counttype => $count ) {
			$total_pages += $count;
		}

		// Comments.
		$total_comments = wp_count_comments();
		$total_comments = empty( $total_comments->all ) ? 0 : (int) $total_comments->all;

		// Users
		$total_users = count_users();
		$total_users = $total_users['total_users'];

		if ( ! isset( $settings['custom_root'] ) || ( '' == $settings['custom_root'] ) ) {
			pb_backupbuddy::status(
				'startSubFunction', json_encode(
					array(
						'function' => 'post_count',
						'title'    => 'Found ' . $total_posts . ' posts, ' . $total_pages . ' pages, and ' . $total_comments . ' comments.',
					)
				)
			);
			pb_backupbuddy::status(
				'startSubFunction', json_encode(
					array(
						'function' => 'post_count',
						'title'    => 'Found ' . $total_users . ' user accounts.',
					)
				)
			);
		}

		$dat_content = array(

			// Backup Info.
			'backupbuddy_version'  => pb_backupbuddy::settings( 'version' ),
			'wordpress_version'    => $wp_version,                                         // WordPress version.
			'php_version'          => PHP_VERSION,

			'backup_time'          => $settings['start_time'],                             // Time backup began.
			'backup_type'          => $settings['backup_type'],                            // Backup type: full, db, files.
			'profile'              => $settings['profile'],                                // Array of profile settings.
			'default_profile'      => pb_backupbuddy::$options['profiles'][0],             // Default profile.
			'serial'               => $settings['serial'],                                 // Unique identifier (random) for this backup.
			'trigger'              => $settings['trigger'],                                // What triggered this backup. Valid values: scheduled, manual.
			'wp-config_in_parent'  => $wp_config_parent,                                   // Whether or not the wp-config.php file is in one parent directory up. If in parent directory it will be copied into the temp serial directory along with the .sql and DAT file. On restore we will NOT place in a parent directory due to potential permission issues, etc. It will be moved into the normal location. Value set to true later in this function if applicable.
			'deployment_direction' => $settings['deployment_direction'],                   // Deployment direction, if any.

			// WordPress Info.
			'abspath'              => ABSPATH,
			'siteurl'              => site_url(),
			'homeurl'              => home_url(),
			'blogname'             => get_option( 'blogname' ),
			'blogdescription'      => get_option( 'blogdescription' ),
			'active_plugins'       => implode( ', ', get_option( 'active_plugins' ) ),              // List of active plugins at time of backup.
			'posts'                => $total_posts,                                                 // Total WP posts, publishes, draft, private, trash, etc.
			'pages'                => $total_pages,                                                 // Total WP pages, publishes, draft, private, trash, etc.
			'comments'             => $total_comments,                                              // Total WP comments, approved, spam, etc.
			'users'                => $total_users,                                                 // Total users on site.
			'wp_content_url'       => WP_CONTENT_URL,
			'wp_content_dir'       => WP_CONTENT_DIR,

			// Database Info. Remaining sensitive info added in after printing out DAT (for security).
			'db_charset'           => $wpdb->charset,                                              // Charset of the database. Eg utf8, utfmb4. @since v6.0.0.6.
			'db_collate'           => $wpdb->collate,                                              // Collate of the database. Eg utf8, utfmb4. @since v6.0.0.6.
			'db_prefix'            => $wpdb->prefix,                                               // DB prefix. (Example: wp_).
			'db_server'            => DB_HOST,                                                     // DB host / server address.
			'db_name'              => DB_NAME,                                                     // DB name.
			'db_user'              => '',                                                          // Set several lines down after printing out DAT.
			'db_password'          => '',                                                          // Set several lines down after printing out DAT.
			'db_exclusions'        => implode( ',', $settings['db_excludes'] ),
			'db_inclusions'        => implode( ',', $settings['db_includes'] ),
			'db_version'           => $wpdb->db_version(),                                     // Database server (mysql) version.
			'breakout_tables'      => $settings['breakout_tables'],                            // Tables broken out into individual backup steps.
			'tables_sizes'         => $settings['table_sizes'],                                // Tables backed up and their sizes.
			'force_single_db_file' => $settings['force_single_db_file'],                       // Tables forced into a single db_1.sql file.
			'skip_database_dump'   => $settings['skip_database_dump'],

			// Multisite Info.
			'is_multisite'         => $is_multisite,                                               // Full Network backup?
			'is_multisite_export'  => $is_multisite_export,                                        // Subsite backup (export)?
			'domain'               => is_object( $current_blog ) ? $current_blog->domain : '',     // Ex: bob.com.
			'path'                 => is_object( $current_blog ) ? $current_blog->path : '',       // Ex: /wordpress/.
			'upload_url'           => $upload_url,                                                 // Pretty URL.
			'upload_url_rewrite'   => $upload_url_rewrite,                                         // Real existing URL that the pretty URL will be rewritten to.

			// Importer Options.
			// 'import_display_previous_values'	=>	pb_backupbuddy::$options['import_display_previous_values'],	// Whether or not to display the previous values from the source on import. Useful if customer does not want to blatantly display previous values to anyone restoring the backup.
		); // End setting $dat_content.

		// If currently using SSL or forcing admin SSL then we will check the hardcoded defined URL to make sure it matches.
		if ( is_ssl() or ( defined( 'FORCE_SSL_ADMIN' ) && true == FORCE_SSL_ADMIN ) ) {
			$dat_content['siteurl'] = get_option( 'siteurl' );
			pb_backupbuddy::status( 'details', __( 'Compensating for SSL in siteurl.', 'it-l10n-backupbuddy' ) );
		}

		// Output for troubleshooting.
		pb_backupbuddy::status( 'details', 'DAT file contents (sans database user/pass): ' . str_replace( "\n", '; ', print_r( $dat_content, true ) ) );

		// Remaining DB settings.
		$dat_content['db_user']     = DB_USER;
		$dat_content['db_password'] = DB_PASSWORD;

		// Serialize .dat file array.
		$encoded_dat_content = "<?php die('Access Denied.'); // <!-- ?>\n" . base64_encode( serialize( $dat_content ) );

		// TODO: remove exists note if no more problems with this after adding the above directory making.
		$existed = 'no';
		if ( file_exists( $dat_file ) ) {
			$existed = 'yes';
		}

		// Write data to the dat file.
		$file_handle = fopen( $dat_file, 'w' );
		if ( false === $file_handle ) {
			$error = 'Error #9017: Unable to fopen DAT file `' . esc_html( $dat_file ) . '`. Check file/directory permissions. Already existed?: `' . esc_html( $existed ) . '`.';
			pb_backupbuddy::status( 'error', $error );
			@fclose( $file_handle );
			return $error;
		}
		if ( false === fwrite( $file_handle, $encoded_dat_content ) ) {
			$error = 'Error #348934843: Unable to fwrite to DAT file `' . esc_html( $dat_file ) . '`. Check file/directory permissions. Already existed?: `' . esc_html( $existed ) . '`.';
			pb_backupbuddy::status( 'error', $error );
			@fclose( $file_handle );
			return $error;
		}
		@fclose( $file_handle );

		return $dat_content; // Array of dat content which was written to DAT file.

	}

	/**
	 * Retrieves Zip contents from data file.
	 *
	 * Array looks like this:
	 *     0 => array(
	 *         'path'     => '/relative/file/path.php',
	 *         'size'     => int filesize,
	 *         'modified' => int unix timestamp,
	 *     )
	 *
	 * @param string     $path_to_zip     Path to zip file.
	 * @param string     $path_in_zip     Path inside zip to return.
	 * @param int|string $destination_id  ID of remote destination.
	 *
	 * @return array  Zip contents array.
	 */
	public function get_file_zip_contents( $path_to_zip, $path_in_zip = false, $destination_id = false ) {
		$data = $this->get( $path_to_zip, $destination_id );

		if ( false === $data || ! is_array( $data ) ) {
			return false;
		}

		if ( empty( $data['zip_contents'] ) ) {
			return false;
		}

		if ( ! is_array( $data['zip_contents'] ) ) {
			$data['zip_contents'] = explode( "\n", $data['zip_contents'] );
		}

		// Filter paths not matching criteria.
		if ( false !== $path_in_zip ) {
			$top_level = '__root__' === $path_in_zip;
			$return    = array();

			foreach ( $data['zip_contents'] as $file ) {
				if ( ! is_array( $file ) ) {
					continue;
				}

				$slashes = substr_count( $file['path'], '/' );

				if ( $top_level ) {
					if ( 0 === $slashes ) {
						$return[] = $file;
					} elseif ( 1 === $slashes && '/' === substr( $file['path'], -1 ) && ! in_array( $file, $return, true ) ) {
						$return[] = $file;
					} elseif ( 1 === $slashes && ! in_assoc_array( 'path', dirname( $file['path'] ) . '/', $return, true ) ) {
						// Sometimes the base folder entry isn't in zip_contents, so let's make sure there is one.
						$return[] = array(
							'path'     => dirname( $file['path'] ) . '/', // Always end in trailing slash.
							'modified' => $file['modified'],
						);
					}
					continue;
				}

				// Filter out files above the requested path and the matching directory.
				if ( strlen( $file['path'] ) <= strlen( $path_in_zip ) ) {
					continue;
				}

				// Make sure this file matches the requested path.
				if ( substr( $file['path'], 0, strlen( $path_in_zip ) ) !== $path_in_zip ) {
					continue;
				}

				// Filter out files/folders that are too deep.
				$path_slashes = substr_count( $path_in_zip, '/' );
				$is_dir       = '/' === substr( $file['path'], -1 );

				// Filter out folders that are too deep.
				if ( $is_dir && $slashes > $path_slashes + 1 ) {
					continue;
				}

				// Make sure a folder is represented for these files.
				if ( ! $is_dir && $slashes === $path_slashes + 1 ) {
					$dir         = $file;
					$dir['path'] = trailingslashit( dirname( $dir['path'] ) );
					unset( $dir['size'] );
					$file   = $dir;
					$is_dir = true;
				}

				// Filter out files that are too deep.
				if ( ! $is_dir && $slashes !== $path_slashes ) {
					continue;
				}

				// Modified date is not needed and causes duplicates when no folder is represented.
				if ( $is_dir && isset( $file['modified'] ) ) {
					unset( $file['modified'] );
				}

				$file['path'] = str_replace( $path_in_zip, '', $file['path'] );
				if ( ! in_array( $file, $return, true ) ) {
					$return[] = $file;
				}
			}

			return $return;
		}

		return $data['zip_contents'];
	}

	/**
	 * Get the backup file plugin detail.
	 *
	 * @param string $path_to_zip  Path to zip file.
	 *
	 * @return array  Array of backup plugin details.
	 */
	public function get_plugin_info( $path_to_zip ) {
		$data = $this->get( $path_to_zip );

		if ( false === $data || ! is_array( $data ) ) {
			return false;
		}

		if ( empty( $data['plugin_data'] ) ) {
			return false;
		}

		return $data['plugin_data'];
	}

	/**
	 * Get the backup file theme detail.
	 *
	 * @param string $path_to_zip  Path to zip file.
	 *
	 * @return array  Array of backup theme details.
	 */
	public function get_theme_info( $path_to_zip ) {
		$data = $this->get( $path_to_zip );

		if ( false === $data || ! is_array( $data ) ) {
			return false;
		}

		if ( empty( $data['theme_data'] ) ) {
			return false;
		}

		return $data['theme_data'];
	}

	/**
	 * Get the backup file recent edits array.
	 *
	 * @param string $path_to_zip  Path to zip file.
	 *
	 * @return array  Array of backup recent edits.
	 */
	public function get_recent_edits( $path_to_zip ) {
		$data = $this->get( $path_to_zip );

		if ( false === $data || ! is_array( $data ) ) {
			return false;
		}

		if ( empty( $data['recent_edits'] ) ) {
			return false;
		}

		return $data['recent_edits'];
	}

	/**
	 * Determine if dat file creation is disabled.
	 *
	 * @return bool
	 */
	public static function creation_is_disabled() : bool {
		return 1 === (int) pb_backupbuddy::$options['disable_dat_file_creation'];
	}

	/**
	 * If the dat file creation setting is disabled, delete all existing data files.
	 *
	 * @todo, if creation is enabled, should we create all dat files?
	 *
	 * @since 9.1.9
	 *
	 * @param array $old_settings  Old settings.
	 * @param array $new_settings  New settings.
	 *
	 * @return int  Number of .dat files deleted.
	 */
	public function disable_dat_file_creation( array $old_settings, array $new_settings ) : int {
		$count = 0;

		// If nothing's changed, nothing changes.
		if ( $old_settings['disable_dat_file_creation'] === $new_settings['disable_dat_file_creation'] ) {
			return $count;
		}

		// If disabling file creation is NOT true, skip.
		if ( 1 !== (int) $new_settings['disable_dat_file_creation'] ) {
			return $count;
		}

		require_once pb_backupbuddy::plugin_path() . '/classes/housekeeping.php';

		// Remove the .dat file generated when creating a backup.
		if ( backupbuddy_housekeeping::remove_dat_file_from_root() ) {
			$count = 1;
		}

		// Delete all .dat files in the local backup directory.
		$local_count = $this->delete_local_dats();

		return $count + $local_count;
	}

	/**
	 * Delete all .dat files in the local backup directory.
	 *
	 * Although this deletes all the local .dat files,
	 * if a backup .zip exists, its .dat will be
	 * regenerated on the next page load.
	 *
	 * This method is useful for removing orphaned .dat files,
	 * or, when coupled with the disable_dat_file_creation
	 * setting, to remove all .dat files.
	 *
	 * @return int|array  Number of .dat files deleted. | array of files that errored
	 */
	public function delete_local_dats() {
		$backups_dir = backupbuddy_core::getBackupDirectory();
		return $this->recursive_delete_local_dats( $backups_dir );
	}

	/**
	 * Recursively delete all .dat files in a local directory.
	 *
	 * @param string $directory  Directory to search for .dat files.
	 * @param int    $count      Number of .dat files deleted.
	 *
	 * @return int|array  Number of .dat files deleted. | array of files that errored
	 */
	public function recursive_delete_local_dats( string $directory, int $count = 0 ) {
		// Get all files and directories in the current directory
		$items = glob( $directory . '/*' );
		$errors = array();

		// Iterate through each item
		foreach ( $items as $item ) {
			if ( is_dir( $item ) ) {
				// Recursively delete files in subdirectories
				$this->recursive_delete_local_dats( $item, $count );
			} elseif ( pathinfo( $item, PATHINFO_EXTENSION ) === self::EXTENSION ) {
				// Check if the file matches the specified type.
				if ( unlink( $item ) ) {
					$count++;
				} else {
					$errors[] = $item;
				}
			}
		}

		if ( count( $errors ) > 0 ) {
			return $errors;
		} else {
			return $count;
		}
	}
}
