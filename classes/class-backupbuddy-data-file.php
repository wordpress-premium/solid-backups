<?php
/**
 * Data file class for creating, compressing and parsing.
 *
 * @package BackupBuddy
 */

/**
 * Creates .dat file to accompany backup files.
 */
class BackupBuddy_Data_File {

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
	 * File Extension
	 *
	 * @var string
	 */
	private $extension = 'dat';

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
			$data_file      = str_replace( '.zip', '.' . $this->extension, $zip_file );
			$data_file_path = false;
			if ( file_exists( $data_file ) ) { // Full Path.
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
		$data_file_path .= $zip_info['filename'] . '.' . $this->extension;

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
		if ( empty( $file_info['extension'] ) || $this->extension !== $file_info['extension'] ) {
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
		if ( 1 === (int) pb_backupbuddy::$options['disable_dat_file_creation'] ) {
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
		$plugins = array();

		foreach ( $this->zip_file_list as $file_array ) {
			$file        = $file_array[0];
			$plugin_file = $this->maybe_get_plugin_file( $file );

			if ( false === $plugin_file ) {
				continue;
			}

			$plugin_file_content = $this->zipbuddy->get_file_contents( $this->zip_file, $plugin_file );
			$plugin_data         = $this->maybe_get_plugin_data( $plugin_file_content );

			if ( false !== $plugin_data ) {
				$plugin_file             = str_replace( 'wp-content/plugins/', '', $plugin_file );
				$plugin_file             = str_replace( 'wp-content/mu-plugins/', '', $plugin_file );
				$plugins[ $plugin_file ] = $plugin_data;
			}
		}

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
}
