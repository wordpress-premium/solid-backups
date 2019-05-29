<?php
/**
 * Multisite Import Class
 *
 * Used in controllers/pages/multisite_import.php
 *
 * @package BackupBuddy
 */

/**
 * Import class for Multisite
 */
class pluginbuddy_ms_import {

	/**
	 * Import Options
	 *
	 * @var array
	 */
	private $import_options;

	/**
	 * Import Steps Array storage
	 *
	 * @var array
	 */
	private $import_steps = array();

	/**
	 * Stores Backup data from dat file.
	 *
	 * @var array
	 */
	private $_backupdata = false;

	/**
	 * Tracks when import started
	 *
	 * @var int
	 */
	private $time_start = 0;

	/**
	 * Max execution time.
	 *
	 * @var int
	 */
	private $detected_max_execution_time;

	/**
	 * Class constructor, allows action and import options to be passed.
	 *
	 * @param string $action          Action, based on GET var.
	 * @param array  $import_options  Array of import options.
	 */
	public function __construct( $action = '', $import_options = array() ) {
		$this->set_import_options( $import_options );
		$this->init();

		$this->import_steps = array(
			'step1' => array(
				'number'  => 1,
				'title'   => __( 'Select Backup & Site Address', 'it-l10n-backupbuddy' ),
				'include' => '_step1',
			),
			'step2' => array(
				'number'  => 2,
				'title'   => __( 'Create Site', 'it-l10n-backupbuddy' ),
				'include' => '_step2',
			),
			'step3' => array(
				'number'  => 3,
				'title'   => __( 'Unzipping Backup File', 'it-l10n-backupbuddy' ),
				'include' => '_step3',
			),
			'step4' => array(
				'number'  => 4,
				'title'   => __( 'Migrating Files (Media, Plugins, Themes, and more)', 'it-l10n-backupbuddy' ),
				'include' => '_step4',
			),
			'step5' => array(
				'number'  => 5,
				'title'   => __( 'Importing Database Content', 'it-l10n-backupbuddy' ),
				'include' => '_step5',
			),
			'step6' => array(
				'number'  => 6,
				'title'   => __( 'Migrating Database Content (URLs, Paths, and more)', 'it-l10n-backupbuddy' ),
				'include' => '_step6',
			),
			'step7' => array(
				'number'  => 7,
				'title'   => __( 'Migrating Users & Accounts', 'it-l10n-backupbuddy' ),
				'include' => '_step7',
			),
			'step8' => array(
				'number'  => 8,
				'title'   => __( 'Final Cleanup', 'it-l10n-backupbuddy' ),
				'include' => '_step8',
			),
		);

		if ( empty( $action ) || 'step' !== substr( $action, 0, 4 ) ) {
			$action = 'step1';
		}

		$total_steps = count( $this->import_steps );
		if ( isset( $this->import_steps[ $action ] ) ) {
			$step = $this->import_steps[ $action ];
			$include_path = pb_backupbuddy::plugin_path() . '/controllers/pages/_ms_import/' . $step['include'] . '.php';

			if ( ! file_exists( $include_path ) ) {
				$this->status( 'error', 'Unable to load multisite import step: ' . $action );
				return;
			}

			$current_blog = get_blog_details( get_current_blog_id() );

			printf( '<h3>Step %d of %d: %s</h3>', esc_html( $step['number'] ), esc_html( $total_steps ), esc_html( $step['title'] ) );
			require $include_path;
		}
	}

	/**
	 * Set the Import Options array
	 *
	 * @param array $import_options  Array of Import options.
	 */
	private function set_import_options( $import_options ) {
		$this->import_options = apply_filters( 'itbub_multisite_import_options', $import_options );
	}

	/**
	 * Initialize the import optinos and set time_start.
	 */
	public function init() {
		if ( '' == $this->import_options['zip_id'] && isset( $this->import_options['file'] ) ) {
			$this->import_options['zip_id'] = $this->get_zip_id( basename( $this->import_options['file'] ) );
		}

		// Detect max execution time for database steps so they can pause when needed for additional PHP processes.
		$this->detected_max_execution_time = str_ireplace( 's', '', ini_get( 'max_execution_time' ) );
		if ( is_numeric( $this->detected_max_execution_time ) === false ) {
			$this->detected_max_execution_time = 30;
		}

		$this->set_advanced_options();

		$this->time_start = microtime( true );

		// Temporarily unzips into the main sites uploads temp
		$wp_uploads                         = wp_upload_dir();
		$this->import_options['extract_to'] = $wp_uploads['basedir'] . '/backupbuddy_temp/import_' . $this->import_options['zip_id'];
	}

	/**
	 * Setup Advanced Import Options
	 *
	 * @param string $advanced_options  JSON String Formatted array of advanced options.
	 */
	public function set_advanced_options( $advanced_options = '' ) {
		if ( empty( $advanced_options ) ) {
			// Attempt to grab Advanced Options from $_POST array.
			if ( isset( $_POST['global_options'] ) && '' != $_POST['global_options'] ) {
				$advanced_options = $_POST['global_options'];
			}
		}

		// Set advanced options if they have been passed along.
		if ( ! empty( $advanced_options ) ) {
			$this->advanced_options = json_decode( base64_decode( $advanced_options ), true );
		}
	}

	/**
	 * Load the backup dat file.
	 */
	public function load_backup_dat() {
		pb_backupbuddy::anti_directory_browsing( backupbuddy_core::getTempDirectory(), $die = false );

		$dat_file          = $this->import_options['extract_to'] . '/' . str_replace( ABSPATH, '', backupbuddy_core::getTempDirectory() ) . $this->import_options['zip_id'] . '/backupbuddy_dat.php';
		$this->_backupdata = $this->get_backup_dat( $dat_file );
	}

	/**
	 * Get Backup Dat File Contents
	 *
	 * @param string $dat_file  Path to Dat File.
	 *
	 * @return array  Dat file contents array.
	 */
	public function get_backup_dat( $dat_file ) {
		require_once pb_backupbuddy::plugin_path() . '/classes/import.php';
		$import = new pb_backupbuddy_import();

		return backupbuddy_core::get_dat_file_array( $dat_file );
	}

	/**
	 * Get MultiSite option value.
	 *
	 * @param int    $blog_id      Blog ID.
	 * @param string $option_name  Option name.
	 *
	 * @return mixed  Value of option.
	 */
	public function get_ms_option( $blog_id, $option_name ) {
		global $wpdb;

		$db_name      = DB_NAME;
		$prefix       = $wpdb->get_blog_prefix( $blog_id );
		$query        = $wpdb->prepare( "SELECT option_value FROM `$db_name`.`{$prefix}options` WHERE `option_name` = %s", $option_name );
		$option_value = $wpdb->get_var( $query );
		return $option_value;
	}

	/**
	 * Removes array values in $remove from $array.
	 *
	 * @param array $array   Source array. This will have values removed and be returned.
	 * @param mixed $remove  Array of values to search for in $array and remove.
	 *
	 * @return array  Returns array $array stripped of all values found in $remove
	 */
	public function array_remove( $array, $remove ) {
		if ( ! is_array( $remove ) ) {
			$remove = array( $remove );
		}
		return array_values( array_diff( $array, $remove ) );
	}

	/**
	 * Displays a textarea for placing status text into.
	 *
	 * @param string $default_text First line of text to display.
	 *
	 * @return string  HTML for textarea.
	 */
	public function status_box( $default_text = '' ) {
		return '<textarea readonly="readonly" style="width: 100%;" rows="10" cols="75" id="importbuddy_status" wrap="off">' . $default_text . '</textarea><br><br>';
	}

	/**
	 * Write a status line into an existing textarea created with the status_box() function.
	 *
	 * @param string $type     Message, details, error, or warning. Currently not in use.
	 * @param string $message  Message to append to the status box.
	 */
	public function status( $type, $message ) {
		pb_backupbuddy::status( $type, $message );

		$status_lines = pb_backupbuddy::get_status( 'ms_import', true, true, true );
		if ( false !== $status_lines ) { // Only add lines if there is status contents.
			foreach ( $status_lines as $status_line ) {
				$status_line         = json_decode( $status_line, true );
				$status_line['time'] = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $status_line['time'] ) );
				$status_line['run'] .= 'sec';
				$status_line['mem'] .= 'MB';
				echo '<script type="text/javascript">jQuery( "#importbuddy_status" ).append( "\n' .
					esc_html( $status_line['time'] ) . "\t" . esc_html( $status_line['run'] ) . "\t" . esc_html( $status_line['mem'] ) . "\t" . esc_html( $status_line['event'] ) . "\t" . esc_html( $status_line['data'] ) . '");	textareaelem = document.getElementById( "importbuddy_status" );	textareaelem.scrollTop = textareaelem.scrollHeight;	</script>';
				pb_backupbuddy::flush();
			}
		}
	}

	/**
	 * Given a BackupBuddy ZIP file, extracts the random ZIP ID from the filename. This random string determines
	 * where BackupBuddy will find the temporary directory in the backup's wp-uploads directory. IE a zip ID of
	 * 3poje9j34 will mean the temporary directory is wp-uploads/temp_3poje9j34/. backupbuddy_dat.php is in this
	 * directory as well as the SQL dump.
	 *
	 * Currently handles old BackupBuddy ZIP file format. Remove this backward compatibility at some point.
	 *
	 * @param string $file  BackupBuddy ZIP filename.
	 *
	 * @return string  ZIP ID characters.
	 */
	public function get_zip_id( $file ) {
		$posa = strrpos( $file, '_' ) + 1;
		$posb = strrpos( $file, '-' ) + 1;
		if ( $posa < $posb ) {
			$zip_id = strrpos( $file, '-' ) + 1;
		} else {
			$zip_id = strrpos( $file, '_' ) + 1;
		}

		$zip_id = substr( $file, $zip_id, - 4 );
		return $zip_id;
	}

	/**
	 * Deletes a file.
	 *
	 * @param string $file              Full path to file to delete.
	 * @param string $description       Description of file for logging.
	 * @param bool   $error_on_missing  Default false. Whether to log an error for a missing file.
	 */
	public function remove_file( $file, $description, $error_on_missing = false ) {
		$this->status( 'message', 'Deleting ' . $description . '...' );
		$mode = apply_filters( 'itbub-default-file-mode', 0755 );
		@chmod( $file, $mode ); // High permissions to delete.

		if ( is_dir( $file ) ) { // directory.
			$this->remove_dir( $file );
			if ( file_exists( $file ) ) {
				$this->status( 'error', 'Unable to delete directory: `' . $description . '` named `' . $file . '`. You should manually delete it.' );
			} else {
				$this->status( 'message', 'Deleted `' . $file . '`.' );
			}
		} else { // file.
			if ( file_exists( $file ) ) {
				if ( true !== @unlink( $file ) ) {
					$this->status( 'error', 'Unable to delete file: `' . $description . '` named `' . $file . '`. You should manually delete it.' );
				} else {
					$this->status( 'message', 'Deleted `' . $file . '`.' );
				}
			} else {
				$this->status( 'message', 'File does not exist; nothing to delete.' );
			}
		}
	}
}
