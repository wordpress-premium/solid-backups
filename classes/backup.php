<?php
/**
 * Handles the actual backup procedures.
 *
 * USED BY:
 *     1) Full & DB backups
 *     2) Multisite backups & exports
 *
 * @package BackupBuddy
 */

/**
 * Main backup class.
 */
class pb_backupbuddy_backup {

	const CRON_GROUP = 'solid-backups-create';

	/**
	 * TODO: No longer used? Remove?
	 *
	 * @var array
	 */
	private $_errors = array();

	/**
	 * Marked true once anything has been status logged during this process. Used by status().
	 *
	 * @var bool
	 */
	private $_status_logging_started = false;

	/**
	 * When running a backup, this is the current index in the steps array within the fileoptions.
	 *
	 * @var string
	 */
	private $_currentStepIndex = '';

	/**
	 * Array of backup data.
	 *
	 * @var array
	 */
	public $_backup = array();

	/**
	 * Object of backup options.
	 *
	 * @var object
	 */
	public $_backup_options;

	// Constants for Zip Build Strategy - here for now but will be moved to central file.
	/**
	 * Single-Burst/Single-Step
	 */
	const ZIP_BUILD_STRATEGY_SBSS = 2;

	/**
	 * Multi-Burst/Single-Step
	 */
	const ZIP_BUILD_STRATEGY_MBSS = 3;

	/**
	 * Multi-Burst/Multi-Step
	 */
	const ZIP_BUILD_STRATEGY_MBMS = 4;

	/**
	 * Minimum zip build strategy
	 */
	const ZIP_BUILD_STRATEGY_MIN = self::ZIP_BUILD_STRATEGY_SBSS;

	/**
	 * Maximum zip build strategy.
	 */
	const ZIP_BUILD_STRATEGY_MAX = self::ZIP_BUILD_STRATEGY_MBMS;

	/**
	 * Default constructor. Initialized core and zipbuddy classes.
	 */
	public function __construct() {

		// Load core if it has not been instantiated yet.
		if ( ! class_exists( 'backupbuddy_core' ) ) {
			require_once pb_backupbuddy::plugin_path() . '/classes/core.php';
		}

		require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';

		// Load zipbuddy if it has not been instantiated yet.
		if ( ! isset( pb_backupbuddy::$classes['zipbuddy'] ) ) {
			require_once pb_backupbuddy::plugin_path() . '/lib/zipbuddy/zipbuddy.php';
			pb_backupbuddy::$classes['zipbuddy'] = new pluginbuddy_zipbuddy( backupbuddy_core::getBackupDirectory() );
		}

		// Register PHP shutdown function to help catch and log fatal PHP errors during backup.
		register_shutdown_function( array( &$this, 'shutdown_function' ) );

	}

	/**
	 * Used for catching fatal PHP errors during backup to write to log for debugging.
	 */
	public function shutdown_function() {
		// Get error message.
		// Error types: http://php.net/manual/en/errorfunc.constants.php
		$e = error_get_last();
		if ( $e === null ) { // No error of any kind.
			return;
		} else { // Some type of error.
			if ( ! is_array( $e ) || ( $e['type'] != E_ERROR ) && ( $e['type'] != E_USER_ERROR ) ) { // Return if not a fatal error.
				return;
			}
		}

		// Calculate log directory.
		$log_directory = backupbuddy_core::getLogDirectory();
		$main_file     = $log_directory . 'log-' . pb_backupbuddy::$options['log_serial'] . '.txt';

		// Determine if writing to a serial log.
		if ( pb_backupbuddy::$_status_serial != '' ) {
			$serial       = pb_backupbuddy::$_status_serial;
			$serial_file  = $log_directory . 'status-' . $serial . '_' . pb_backupbuddy::$options['log_serial'] . '.txt';
			$write_serial = true;
		} else {
			$write_serial = false;
		}

		// Format error message.
		$e_string = 'PHP_ERROR ' . __( 'Error #32893. Fatal PHP error encountered:', 'it-l10n-backupbuddy' );
		foreach ( (array) $e as $e_line_title => $e_line ) {
			$e_string .= $e_line_title . ' => ' . $e_line . '; ';
		}
		$e_string .= ".\n";

		// Write to log.
		@file_put_contents( $main_file, $e_string, FILE_APPEND );
		if ( $write_serial === true ) {
			@file_put_contents( $serial_file, $e_string, FILE_APPEND );
		}
	}

	/**
	 * Initializes the entire backup process.
	 *
	 * @param array  $profile                    Backup profile array. Previously (pre-4.0): Valid values: db, full, export.
	 * @param string $trigger                    What triggered this backup. Valid values: ::d, manual.
	 * @param array  $pre_backup                 Array of functions to prepend to the backup steps array.
	 * @param array  $post_backup                Array of functions to append to the backup steps array. I.e. sending to remote destination.
	 * @param string $schedule_title             Title name of schedule. Used for tracking what triggered this in logging. For debugging.
	 * @param string $serial_override            If provided then this serial will be used instead of an auto-generated one.
	 * @param array  $export_plugins             For use in export backup type. List of plugins to export.
	 * @param string $deployDirection            Blank for not deploy, push, or pull.
	 * @param string $deployDestinationSettings  Destination settings for the deployment. Empty string when not deployment.
	 *
	 * @return bool  True on success; false otherwise.
	 */
	public function start_backup_process(
		$profile,
		$trigger = 'manual',
		$pre_backup = array(),
		$post_backup = array(),
		$schedule_title = '',
		$serial_override = '',
		$export_plugins = array(),
		$deployDirection = '',
		$deployDestinationSettings = ''
	) {

		if ( $serial_override != '' ) {
			$serial = $serial_override;
		} else {
			$serial = pb_backupbuddy::random_string( 10 );
		}

		// Default logging serial.
		pb_backupbuddy::set_status_serial( $serial );

		// Temporarily hold excludes, so we can put them back later after merging defaults.
		$originalExcludes = $profile['excludes'];

		// Merge global profile defaults array. Save if merging added anything.
		$default_global_profile = pb_backupbuddy::settings( 'default_options' );
		$default_global_profile = $default_global_profile['profiles'][0];
		$default_global_profile = array_merge( $default_global_profile, pb_backupbuddy::$options['profiles'][0] );
		if ( $default_global_profile != pb_backupbuddy::$options['profiles'][0] ) {
			pb_backupbuddy::$options['profiles'][0] = $default_global_profile;
			pb_backupbuddy::save();
			pb_backupbuddy::status( 'details', 'Default global profile needed updated with merged defaults. Updated and saved.' );
		}

		// Load profile defaults.
		$profile = array_merge( pb_backupbuddy::settings( 'profile_defaults' ), $profile );
		foreach ( $profile as $profile_item_name => &$profile_item ) { // replace non-overridden defaults with actual default value.
			if ( '-1' == $profile_item ) { // Set to use default so go grab default.
				if ( isset( pb_backupbuddy::$options['profiles'][0][ $profile_item_name ] ) ) {
					$profile_item = pb_backupbuddy::$options['profiles'][0][ $profile_item_name ]; // Grab value from defaults profile and replace with it.
				}
			}
		}

		// Re-apply original excludes without defaults merged ( handle these in get_directory_exclusions() ).
		$profile['excludes'] = $originalExcludes;

		global $wp_version;
		pb_backupbuddy::status( 'details', 'Solid Backups v' . pb_backupbuddy::settings( 'version' ) . ' using WordPress v' . $wp_version . ' with PHP v' . PHP_VERSION . ' on ' . PHP_OS . ' operating system.' );
		// pb_backupbuddy::status( 'details', __('Peak memory usage', 'it-l10n-backupbuddy' ) . ': ' . round( memory_get_peak_usage() / 1048576, 3 ) . ' MB' );

		if ( '1' == $profile['backup_mode'] ) { // Profile forces classic.
			$backup_mode = '1';
		} elseif ( '2' == $profile['backup_mode'] ) { // Profiles forces modern.
			$backup_mode = '2';
		} else {
			pb_backupbuddy::status( 'warning', 'Warning #38984984: Unknown backup mode `' . $profile['backup_mode'] . '`. Defaulting to modern mode (2).' );
			$backup_mode = '2';
		}
		pb_backupbuddy::status( 'details', 'Backup mode value setting to: `' . $backup_mode . '`. Profile was: `' . $profile['backup_mode'] . '`. Global default is: `' . (string) pb_backupbuddy::$options['profiles'][0]['backup_mode'] . '`.' );
		$profile['backup_mode'] = $backup_mode;
		unset( $backup_mode );

		// If classic mode then we need to redirect output to displaying inline via JS instead of AJAX-based.
		if ( '1' == $profile['backup_mode'] ) {
			// global $pb_backupbuddy_js_status;
			// $pb_backupbuddy_js_status = true;
		}

		$type = $profile['type'];

		$archiveFile = backupbuddy_core::calculateArchiveFilename( $serial, $type, $profile );

		// Set up the backup data structure containing steps, set up temp directories, etc.
		$pre_backup_success = $this->pre_backup( $serial, $archiveFile, $profile, $trigger, $pre_backup, $post_backup, $schedule_title, $export_plugins, $deployDirection, $deployDestinationSettings );
		if ( false === $pre_backup_success ) {
			pb_backupbuddy::status( 'details', 'pre_backup() function failed.' );
			return false;
		}

		if ( ( $trigger == 'scheduled' ) && ( pb_backupbuddy::$options['email_notify_scheduled_start'] != '' ) ) {
			pb_backupbuddy::status( 'details', __( 'Sending scheduled backup start email notification if applicable.', 'it-l10n-backupbuddy' ) );
			backupbuddy_core::mail_notify_scheduled( $serial, 'start', __( 'Scheduled backup', 'it-l10n-backupbuddy' ) . ' (' . $this->_backup['schedule_title'] . ') has begun.' );
		}

		if ( $profile['backup_mode'] == '2' ) { // Modern mode with crons.

			pb_backupbuddy::status( 'message', 'Running in modern backup mode based on settings. Mode value: `' . $profile['backup_mode'] . '`. Trigger: `' . $trigger . '`.' );

			unset( $this->_backup_options ); // File unlocking is handled on deconstruction.  Make sure unlocked before firing off another cron spawn.

			if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) {
				pb_backupbuddy::status( 'details', 'IMPORTANT NOTE: ALTERNATE_WP_CRON defined AND enabled!' );
			}

			$this->cron_next_step( $this->_backup['serial'], $trigger );

		} else { // Classic mode; everything runs in this single PHP page load.

			pb_backupbuddy::status( 'message', 'Running in classic backup mode based on settings. Mode code: `' . $profile['backup_mode'] . '`.' );
			$this->process_backup( $this->_backup['serial'], $trigger );

		}

		return true;

	}

	/**
	 * Set up the backup data structure containing steps, set up temp directories, etc.
	 *
	 * @param string $serial                     Unique backup identifier.
	 * @param string $archiveFile                Backup filename.
	 * @param array  $profile                    Backup profile array data. Prev (pre-4.0): Backup type. Valid values: db, full, export.
	 * @param string $trigger                    What triggered this backup. Valid values: scheduled, manual.
	 * @param array  $pre_backup                 Array of functions to prepend to the backup steps array.
	 * @param array  $post_backup                Array of functions to append to the backup steps array. I.e. sending to remote destination.
	 * @param string $schedule_title             Title name of schedule. Used for tracking what triggered this in logging. For debugging.
	 * @param array  $export_plugins             For use in export backup type. List of plugins to export.
	 * @param string $deployDirection            Blank for not deploy, push, or pull.
	 * @param array  $deployDestinationSettings  Destination settings for the deployment. Empty string when not deployment.
	 *
	 * @return bool  True on success; false otherwise.
	 */
	public function pre_backup( $serial, $archiveFile, $profile, $trigger, $pre_backup = array(), $post_backup = array(), $schedule_title = '', $export_plugins = array(), $deployDirection = '', $deployDestinationSettings = array() ) {

		pb_backupbuddy::status(
			'startFunction',
			json_encode(
				array(
					'function' => 'pre_backup',
					'title'    => 'Getting ready to backup',
				)
			)
		);

		$type = $profile['type'];

		// Log some status information.
		pb_backupbuddy::status( 'details', __( 'Performing pre-backup procedures.', 'it-l10n-backupbuddy' ) );

		$message_type = 'message';
		switch ( $type ) :
			case 'full':
				$message = __( 'Full backup mode.', 'it-l10n-backupbuddy' );
				break;
			case 'db':
				$message = __( 'Database only backup mode.', 'it-l10n-backupbuddy' );
				break;
			case 'files':
				$message = __( 'Files only backup mode.', 'it-l10n-backupbuddy' );
				break;
			case 'media':
				$message = __( 'Media only backup mode.', 'it-l10n-backupbuddy' );
				break;
			case 'themes':
				$message = __( 'Themes only backup mode.', 'it-l10n-backupbuddy' );
				break;
			case 'plugins':
				$message = __( 'Plugins only backup mode.', 'it-l10n-backupbuddy' );
				break;
			case 'export':
				$message = __( 'Multisite subsite export mode.', 'it-l10n-backupbuddy' );
				break;
			default:
				$message_type = 'error';
				$message      = sprintf(
					__( 'Error %1$s. Unknown backup type `%2$s`.', 'it-l10n-backupbuddy' ),
					'#32893',
					htmlentities( $type )
				);
			endswitch;
			pb_backupbuddy::status( $message_type, $message );

		if ( ! empty( $deployDirection ) ) {
			pb_backupbuddy::status( 'details', 'Deployment direction: `' . $deployDirection . '`.' );
		}

		if ( '1' == pb_backupbuddy::$options['prevent_flush'] ) {
			pb_backupbuddy::status( 'details', 'Flushing will be skipped based on advanced settings.' );
		} else {
			pb_backupbuddy::status( 'details', 'Flushing will not be skipped (default).' );
		}

		// Schedule daily housekeeping.
		backupbuddy_core::verifyHousekeeping();

		// Verify directories.
		pb_backupbuddy::status( 'details', 'Verifying directories ...' );
		if ( false === backupbuddy_core::verify_directories() ) {
			pb_backupbuddy::status( 'error', 'Error #18573. Error verifying directories. See details above. Backup halted.' );
			pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
			die();
		} else {
			pb_backupbuddy::status( 'details', 'Directories verified.' );
		}

		// Delete all backup archives if this troubleshooting option is enabled.
		if ( '1' == pb_backupbuddy::$options['delete_archives_pre_backup'] ) {
			pb_backupbuddy::status( 'message', 'Deleting all existing backups prior to backup as configured on the settings page.' );
			$file_list = glob( backupbuddy_core::getBackupDirectory() . 'backup*.zip' );
			if ( is_array( $file_list ) && ! empty( $file_list ) ) {
				foreach ( $file_list as $file ) {
					if ( backupbuddy_backups()->delete( basename( $file ) ) ) {
						pb_backupbuddy::status( 'details', 'Deleted backup archive `' . basename( $file ) . '` based on settings to delete all backups.' );
					} else {
						pb_backupbuddy::status( 'details', 'Unable to delete backup archive `' . basename( $file ) . '` based on settings to delete all backups. Verify permissions.' );
					}
				}
			}
		}

		// Generate unique serial ID.
		pb_backupbuddy::status( 'details', 'Backup serial generated: `' . $serial . '`.' );

		pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #40 in create mode...' );
		require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
		$this->_backup_options = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/' . $serial . '.txt', $read_only = false, $ignore_lock = false, $create_file = true );
		if ( true !== ( $result = $this->_backup_options->is_ok() ) ) {
			pb_backupbuddy::status( 'error', __( 'Fatal Error #9034 A. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
			pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
			return false;
		}
		pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );
		$this->_backup = &$this->_backup_options->options; // Set reference.

		// Cleanup internal stats. Deployments should not impact stats.
		if ( empty( $deployDirection ) ) {
			pb_backupbuddy::status( 'details', 'Updating statistics for last backup start.' );
			pb_backupbuddy::$options['last_backup_start']  = microtime( true ); // Reset time since last backup.
			pb_backupbuddy::$options['last_backup_serial'] = $serial;
			pb_backupbuddy::save();
		}

		// Output active plugins list for debugging...
		if ( ( 'full' == $type ) && ( ( ! isset( $profile['custom_root'] ) ) || ( '' == $profile['custom_root'] ) ) ) {
			$activePlugins = get_option( 'active_plugins' );
			pb_backupbuddy::status( 'details', 'Active WordPress plugins (' . count( $activePlugins ) . '): `' . implode( '; ', $activePlugins ) . '`.' );
			pb_backupbuddy::status(
				'startSubFunction',
				json_encode(
					array(
						'function' => 'wp_plugins_found',
						'title'    => 'Found ' . count( $activePlugins ) . ' active WordPress plugins.',
					)
				)
			);
			unset( $activePlugins );
		}

		// Compression to bool.
		/*
		if ( $profile['compression'] == '1' ) {
			$profile['compression'] = true;
		} else {
			$profile['compression'] = false;
		}
		*/
		$compression = ( '1' == pb_backupbuddy::$options['compression'] );
		$archiveURL = '';
		$abspath    = str_replace( '\\', '/', ABSPATH ); // Change slashes to handle Windows as we store backup_directory with Linux-style slashes even on Windows.
		$backup_dir = str_replace( '\\', '/', backupbuddy_core::getBackupDirectory() );
		if ( false !== stristr( $backup_dir, $abspath ) ) { // Make sure file to download is in a publicly accessible location (beneath WP web root technically).
			$sitepath   = str_replace( $abspath, '', $backup_dir );
			$archiveURL = rtrim( site_url(), '/\\' ) . '/' . trim( $sitepath, '/\\' ) . '/' . basename( $archiveFile );
		}

		$forceSingleDatabaseFile = false;
		if ( '1' == pb_backupbuddy::$options['force_single_db_file'] ) {
			$forceSingleDatabaseFile = true;
		}

		$dir_excludes = backupbuddy_core::get_directory_exclusions( $profile, false, $serial );

		$custom_root = '';
		// Files type profile with a custom root does NOT currently support exclusions at all. Strip all excludes.
		if ( ( 'files' === $type ) && ( ! empty( $profile['custom_root'] ) ) ) {
			$custom_root = ABSPATH . ltrim( $profile['custom_root'], '/\\' );
		}

		// Force custom root trailing slash
		if ( ! empty( $custom_root ) ) {
			$custom_root = rtrim( $custom_root, '/\\' ) . '/';
			pb_backupbuddy::status( 'details', 'Custom backup root after enforcing trailing slash: `' . $custom_root . '`.' );
		}

		// Set up the backup data.
		$this->_backup = array(
			'data_version'              => 1,                                              // Data structure version. Upped to 1 for BBv5.0.
			'backupbuddy_version'       => pb_backupbuddy::settings( 'version' ),          // BB version used for this backup.
			'serial'                    => $serial,                                        // Unique identifier.
			'init_complete'             => false,                                          // Whether pre_backup() completed or not. Other step status is already tracked and stored in data structure but pre_backup 'step' was not until now. Jan 6, 2013.
			'backup_mode'               => $profile['backup_mode'],                        // Tells whether modern or classic mode.
			'type'                      => $type,                                          // db, full, or export.
			'profile'                   => $profile,                                       // Backup profile data.
			'default_profile'           => pb_backupbuddy::$options['profiles'][0],        // Default profile.
			'start_time'                => time(),                                         // When backup started. Now.
			'finish_time'               => 0,
			'updated_time'              => time(),                                         // When backup last updated. Subsequent steps update this.
			'status'                    => array(),                                        // TODO: what goes in this?
			'max_execution_time'        => backupbuddy_core::adjustedMaxExecutionTime(),   // Max execution time for chunking, taking into account user-specified override in settings (if any).
			'archive_size'              => 0,
			'schedule_title'            => $schedule_title,                                // Title of the schedule that made this backup happen (if applicable).
			'backup_directory'          => backupbuddy_core::getBackupDirectory(),         // Directory backups stored in.
			'archive_file'              => $archiveFile,                                   // Unique backup ZIP filename.
			'archive_url'               => $archiveURL,                                    // Target download URL.
			'trigger'                   => $trigger,                                       // How backup was triggered: manual or scheduled.
			'zip_method_strategy'       => pb_backupbuddy::$options['zip_method_strategy'], // Enumerated zip method strategy
			'compression'               => $compression,                                   // $profile['compression'], // Boolean - future enumerated?
			'ignore_zip_warnings'       => pb_backupbuddy::$options['ignore_zip_warnings'], // Boolean - future bitmask?
			'ignore_zip_symlinks'       => pb_backupbuddy::$options['ignore_zip_symlinks'], // Boolean - future bitmask?
			'steps'                     => array(),                                        // Backup steps to perform. Set next in this code.
			'integrity'                 => array(),                                        // Used later for tests and stats post backup.
			'temp_directory'            => '',                                             // Temp directory to store SQL and DAT file. Differs for exports. Defined in a moment...
			'backup_root'               => '',                                             // Where to start zipping from. Usually root of site. Defined in a moment...
			'export_plugins'            => array(),                                        // Plugins to export during MS export of a subsite.
			'additional_table_includes' => array(),
			'additional_table_excludes' => array(),
			'directory_exclusions'      => $dir_excludes, // Do not trim trailing slash
			'table_sizes'               => array(),                                        // Array of tables to back up AND their sizes.
			'breakout_tables'           => array(),                                        // Array of tables that will be broken out to separate steps.
			'force_single_db_file'      => $forceSingleDatabaseFile,                       // Whether forcing to a single db_1.sql file.
			'deployment_log'            => '',                                             // PUSH: URL to the importbuddy status log for deployments. PULL: serial for the backup status log to retrieve via remote api.
			'deployment_direction'      => $deployDirection,                               // Deployment direction, if any. valid: '', push, pull.
			'deployment_destination'    => $deployDestinationSettings,                     // Deployment remote destination settings if deployment.
			'runnerUID'                 => get_current_user_id(),                          // UID of whomever is running this backup. 0 if scheduled or ran by other automation means.
			'wp-config_in_parent'       => false,
			'custom_root'               => $custom_root,
			'data_checksum'             => '',                                             // Used to verify data file integrity.
		);

		if ( ( $this->is_files_backup() ) && ( ! empty( $profile['custom_root'] ) ) ) {
			pb_backupbuddy::status(
				'startSubFunction',
				json_encode(
					array(
						'function' => 'file_excludes',
						'title'    => 'Found ' . count( $this->_backup['directory_exclusions'] ) . ' file or directory exclusions.',
					)
				)
			);
		}

		// Warn if excluding key paths.
		$alertFileExcludes = backupbuddy_core::alert_core_file_excludes( $this->_backup['directory_exclusions'] );
		foreach ( $alertFileExcludes as $alertFileExcludeId => $alertFileExclude ) {
			pb_backupbuddy::status( 'warning', $alertFileExclude );
		}

		pb_backupbuddy::anti_directory_browsing( backupbuddy_core::getTempDirectory(), false );

		// Figure out paths.
		$this->_backup['temp_directory'] = backupbuddy_core::getTempDirectory() . $serial . '/';
		if ( $this->is_full_backup() || $this->is_files_backup() ) {
			$this->_backup['backup_root'] = ABSPATH; // ABSPATH contains trailing slash.

			// Support for custom root for files backup type if set.
			if ( ( $this->is_files_backup() ) && ( isset( $profile['custom_root'] ) ) && ( '' != $profile['custom_root'] ) ) {
				$this->_backup['backup_root'] = ABSPATH . rtrim( trim( $profile['custom_root'], '\\/' ), '\\/' ) . '/';
				pb_backupbuddy::status( 'warning', 'Warning #3894743: Custom backup root set. Use with caution. Custom root: `' . $this->_backup['backup_root'] . '`.' );
				if ( ! file_exists( $this->_backup['backup_root'] ) ) {
					pb_backupbuddy::status( 'error', 'Error #32893444. Custom backup root directory NOT found! Custom root: `' . $this->_backup['backup_root'] . '`.' );
					return false;
				}
			}
		} elseif ( $this->is_db_backup() ) {
			$this->_backup['backup_root'] = $this->_backup['temp_directory'];
		} elseif ( $this->is_export_backup()) {
			// WordPress unzips into WordPress subdirectory by default so must include that in path.
			// We store temp data for export within the temporary WordPress installation within the temp directory. A bit confusing; sorry about that.
			$this->_backup['temp_directory'] = backupbuddy_core::getTempDirectory() . $serial . '/wordpress/wp-content/uploads/backupbuddy_temp/' . $serial . '/';
			$this->_backup['backup_root']    = backupbuddy_core::getTempDirectory() . $serial . '/wordpress/';
		} elseif ( $this->is_media_backup()) {
			$this->_backup['backup_root'] = backupbuddy_core::get_media_root();
		} elseif ( $this->is_themes_backup() ) {
			$this->_backup['backup_root'] = backupbuddy_core::get_themes_root();
		} elseif ( $this->is_plugins_backup() ) {
			$this->_backup['backup_root'] = backupbuddy_core::get_plugins_root();
		} else {
			pb_backupbuddy::status( 'error', __( 'Backup failed. Unknown backup type.', 'it-l10n-backupbuddy' ) );
			pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
		}
		pb_backupbuddy::status( 'details', 'Temp directory: `' . $this->_backup['temp_directory'] . '`.' );
		pb_backupbuddy::status( 'details', 'Backup root: `' . $this->_backup['backup_root'] . '`.' );

		// Plugins to export (only for MS exports).
		if ( count( $export_plugins ) > 0 ) {
			$this->_backup['export_plugins'] = $export_plugins;
		}

		// Calculate additional database table inclusion/exclusion.
		$this->_backup['additional_table_includes'] = backupbuddy_core::get_mysqldump_additional( 'includes', $profile );
		$this->_backup['additional_table_excludes'] = backupbuddy_core::get_mysqldump_additional( 'excludes', $profile );

		// Verify wp-config.php exists if not a files type.
		if ( ! $this->is_fileish_backup() ) {
			// Does wp-config.php exist in parent dir instead of normal location?
			if ( file_exists( ABSPATH . 'wp-config.php' ) ) { // wp-config in normal place.
				pb_backupbuddy::status( 'details', 'wp-config.php found in normal location.' );
			} elseif ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) { // Config in parent.
				pb_backupbuddy::status( 'message', 'wp-config.php found in parent directory. Copying wp-config.php to temporary location for backing up.' );
				$this->_backup['wp-config_in_parent'] = true;
				$wp_config_parent                     = true;
			} else {
				if ( ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
					pb_backupbuddy::status( 'error', 'Error #3289493: wp-config.php not found in any normal location. Check file permissions. open_basedir() restrictions may block readability of wp-config.php in parent directory.' );
				} else {
					pb_backupbuddy::status( 'error', 'Error #3289493b: wp-config.php found in parent directory but wp-settings.php was also located and indicates incorrect wp-config.php for this site. Check file permissions. open_basedir() restrictions may block readability of wp-config.php in parent directory.' );
				}
			}
		}

		/********* Begin setting up steps array. */

		if ( $this->is_export_backup() ) {
			pb_backupbuddy::status( 'details', 'Setting up export-specific steps.' );

			$this->_backup['steps'][] = array(
				'function'    => 'ms_download_extract_wordpress',
				'args'        => array(),
				'start_time'  => 0,
				'finish_time' => 0,
				'attempts'    => 0,
			);
			$this->_backup['steps'][] = array(
				'function'    => 'ms_create_wp_config',
				'args'        => array(),
				'start_time'  => 0,
				'finish_time' => 0,
				'attempts'    => 0,
			);
			$this->_backup['steps'][] = array(
				'function'    => 'ms_copy_plugins',
				'args'        => array(),
				'start_time'  => 0,
				'finish_time' => 0,
				'attempts'    => 0,
			);
			$this->_backup['steps'][] = array(
				'function'    => 'ms_copy_themes',
				'args'        => array(),
				'start_time'  => 0,
				'finish_time' => 0,
				'attempts'    => 0,
			);
			$this->_backup['steps'][] = array(
				'function'    => 'ms_copy_media',
				'args'        => array(),
				'start_time'  => 0,
				'finish_time' => 0,
				'attempts'    => 0,
			);
			$this->_backup['steps'][] = array(
				'function'    => 'ms_copy_users_table', // Create temp user and usermeta tables.
				'args'        => array(),
				'start_time'  => 0,
				'finish_time' => 0,
				'attempts'    => 0,
			);
		}

		if ( ( 'pull' != $deployDirection ) && ( '1' != $profile['skip_database_dump'] ) && ( $profile['type'] != 'files' ) && ( $profile['type'] != 'plugins' ) && ( $profile['type'] != 'themes' ) && ( $profile['type'] != 'media' ) ) { // Backup database if not skipping AND not a files only backup.

			global $wpdb;
			// Default tables to back up.
			if ( $this->is_export_backup() ) { // Multisite Subsite export only dumps tables specific to this subsite prefix.
				$base_dump_mode = 'prefix';
			} else { // Non-multisite export so use profile to determine tables to backup.
				if ( $profile['backup_nonwp_tables'] == '1' ) { // Backup all tables.
					$base_dump_mode = 'all';
				} elseif ( $profile['backup_nonwp_tables'] == '2' ) { // Backup no tables by default. Relies on listed additional tables.
					$base_dump_mode = 'none';
				} else { // Only backup matching prefix.
					$base_dump_mode = 'prefix';
				}
			}

			$additional_tables = $this->_backup['additional_table_includes'];
			if ( $this->is_export_backup() ) {
				global $wpdb;
				array_push( $additional_tables, $wpdb->prefix . 'users' );
				array_push( $additional_tables, $wpdb->prefix . 'usermeta' );
			}

			// Warn if excluding key WP tables.
			$tableExcludes = backupbuddy_core::alert_core_table_excludes( $this->_backup['additional_table_excludes'] );
			foreach ( $tableExcludes as $tableExcludeId => $tableExclude ) {
				pb_backupbuddy::status( 'warning', $tableExclude );
			}

			// Calculate tables to dump based on the provided information. $tables will be an array of tables.
			$tables = backupbuddy_core::calculate_tables( $base_dump_mode, $additional_tables, $this->_backup['additional_table_excludes'] );
			$tables = is_array( $tables ) ? $tables : array();
			pb_backupbuddy::status(
				'startSubFunction',
				json_encode(
					array(
						'function' => 'calculate_tables',
						'title'    => 'Found ' . count( $tables ) . ' tables to backup based on settings.',
						'more'     => 'Tables: ' . implode(
							', ',
							$tables
						),
					)
				)
			);

			// If calculations show NO database tables should be backed up then change mode to skip database dump.
			if ( empty( $tables ) ) {
				pb_backupbuddy::status( 'warning', 'WARNING #857272: No database tables will be backed up based on current settings. This will not be a complete backup. Adjust settings if this is not intended and use with caution. Skipping database dump step.' );
				$profile['skip_database_dump']                  = '1';
				$this->_backup['profile']['skip_database_dump'] = '1';
			} else { // One or more tables set to back up.

				// Obtain tables sizes. Surround each table name by a single quote and implode with commas for SQL query to get sizes.
				$tables_formatted = array();
				foreach ( $tables as $key => $formatted ) {
					$tables_formatted[ $key ] = "'" . esc_sql( $formatted ) . "'";
				}
				$tables_formatted = implode( ',', $tables_formatted );
				$sql              = "SHOW TABLE STATUS WHERE Name IN({$tables_formatted});";
				$rows             = $wpdb->get_results( $sql, ARRAY_A );
				if ( false === $rows ) {
					pb_backupbuddy::alert( 'Error #85473474: Unable to retrieve table status. Query: `' . $sql . '`.', true );
					return false;
				}
				$totalDatabaseSize = 0;
				foreach ( $rows as $row ) {
					$this->_backup['table_sizes'][ $row['Name'] ] = ( $row['Data_length'] + $row['Index_length'] );
					$totalDatabaseSize                           += $this->_backup['table_sizes'][ $row['Name'] ];
				}
				unset( $rows );
				unset( $tables_formatted );

				$databaseSize = pb_backupbuddy::$format->file_size( $totalDatabaseSize );
				pb_backupbuddy::status( 'details', 'Total calculated database size: `' . $databaseSize . '`.' );

				// Step through tables we want to break out and figure out which ones were indeed set to be backed up and break them out.
				if ( '0' == pb_backupbuddy::$options['breakout_tables'] ) { // Breaking out DISABLED.
					pb_backupbuddy::status( 'details', 'Breaking out tables DISABLED based on settings.' );
				} else { // Breaking out ENABLED.
					// Tables we will try to break out into standalone steps if possible.
					$breakout_tables_defaults = array(
						$wpdb->prefix . 'posts',
						$wpdb->prefix . 'postmeta',
					);

					pb_backupbuddy::status( 'details', 'Breaking out tables enabled based on settings. Tables to be broken out into individual steps: `' . print_r( $breakout_tables_defaults, true ) . '`.' );
					foreach ( (array) $breakout_tables_defaults as $breakout_tables_default ) {
						if ( in_array( $breakout_tables_default, $tables ) ) {
							$this->_backup['breakout_tables'][] = $breakout_tables_default;
							$tables                             = array_diff( $tables, array( $breakout_tables_default ) ); // Remove from main table backup list.
						}
					}
					unset( $breakout_tables_defaults ); // No longer needed.
				}

				$this->_backup['steps'][] = array(
					'function'    => 'backup_create_database_dump',
					'args'        => array( $tables ),
					'start_time'  => 0,
					'finish_time' => 0,
					'attempts'    => 0,
				);

				// Set up backup steps for additional broken out tables.
				foreach ( (array) $this->_backup['breakout_tables'] as $breakout_table ) {
					$this->_backup['steps'][] = array(
						'function'    => 'backup_create_database_dump',
						'args'        => array( array( $breakout_table ) ),
						'start_time'  => 0,
						'finish_time' => 0,
						'attempts'    => 0,
					);
				}
			} // end there being tables to back up.
		} else {
			pb_backupbuddy::status( 'message', __( 'Skipping database dump based on settings / profile type.', 'it-l10n-backupbuddy' ) . ' Backup type: `' . $type . '`.' );
		}

		if ( 'pull' != $deployDirection ) {
			$this->_backup['steps'][] = array(
				'function'    => 'backup_zip_files',
				'args'        => array(),
				'start_time'  => 0,
				'finish_time' => 0,
				'attempts'    => 0,
			);

			if ( $type == 'export' ) {
				$this->_backup['steps'][] = array( // Multisite export specific cleanup.
					'function'    => 'ms_cleanup', // Removes temp user and usermeta tables.
					'args'        => array(),
					'start_time'  => 0,
					'finish_time' => 0,
					'attempts'    => 0,
				);
			}

			if ( $profile['integrity_check'] == '1' ) {
				pb_backupbuddy::status( 'details', __( 'Integrity check will be performed based on settings for this profile.', 'it-l10n-backupbuddy' ) );
				$this->_backup['steps'][] = array(
					'function'    => 'integrity_check',
					'args'        => array(),
					'start_time'  => 0,
					'finish_time' => 0,
					'attempts'    => 0,
				);
			} else {
				pb_backupbuddy::status( 'details', __( 'Skipping integrity check step based on settings for this profile.', 'it-l10n-backupbuddy' ) );
			}
		}

		$this->_backup['steps'][] = array(
			'function'    => 'post_backup',
			'args'        => array(),
			'start_time'  => 0,
			'finish_time' => 0,
			'attempts'    => 0,
		);

		// Prepend and append pre backup and post backup steps.
		$this->_backup['steps'] = array_merge( $pre_backup, $this->_backup['steps'], $post_backup );

		/********* End setting up steps array. */

		// Save what we have so far so that any errors below will end up displayed to user.
		$this->_backup_options->save();

		/********* Begin directory creation and security. */

		pb_backupbuddy::anti_directory_browsing( backupbuddy_core::getBackupDirectory() );

		// Prepare temporary directory for holding SQL and data file.
		if ( empty( backupbuddy_core::getTempDirectory() ) ) {
			pb_backupbuddy::status( 'error', 'Error #54534344. Temp directory blank. Please deactivate then reactivate plugin to reset.' );
			return false;
		}

		if ( ! file_exists( $this->_backup['temp_directory'] ) ) {
			if ( pb_backupbuddy::$filesystem->mkdir( $this->_backup['temp_directory'] ) === false ) {
				pb_backupbuddy::status( 'error', 'Error #9002b. Unable to create temporary storage directory (' . $this->_backup['temp_directory'] . ')' );
				return false;
			}
		}
		if ( ! is_writable( $this->_backup['temp_directory'] ) ) {
			pb_backupbuddy::status( 'error', 'Error #9015. Temp data directory is not writable. Check your permissions. (' . $this->_backup['temp_directory'] . ')' );
			return false;
		}
		pb_backupbuddy::anti_directory_browsing( ABSPATH . 'wp-content/uploads/backupbuddy_temp/' );

		// Prepare temporary directory for holding ZIP file while it is being generated.
		$this->_backup['temporary_zip_directory'] = backupbuddy_core::getBackupDirectory() . 'temp_zip_' . $this->_backup['serial'] . '/';
		if ( ! file_exists( $this->_backup['temporary_zip_directory'] ) ) {
			if ( pb_backupbuddy::$filesystem->mkdir( $this->_backup['temporary_zip_directory'] ) === false ) {
				pb_backupbuddy::status( 'details', 'Error #9002c. Unable to create temporary ZIP storage directory (' . $this->_backup['temporary_zip_directory'] . ')' );
				return false;
			}
		}
		if ( ! is_writable( $this->_backup['temporary_zip_directory'] ) ) {
			pb_backupbuddy::status( 'error', 'Error #9015. Temp data directory is not writable. Check your permissions. (' . $this->_backup['temporary_zip_directory'] . ')' );
			return false;
		}

		/********* End directory creation and security */

		// Generate backup DAT (data) file containing details about the backup.
		if ( true !== $this->backup_create_dat_file( $trigger ) ) {
			pb_backupbuddy::status( 'details', __( 'Problem creating DAT file.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		// Generating Importer file to include in the backup for FULL BACKUPS ONLY currently. Cannot put in DB because it would be in root and be excluded or conflict on extraction.
		if ( $this->is_full_backup() ) {
			if ( '1' == pb_backupbuddy::$options['include_importbuddy'] ) {
				pb_backupbuddy::status( 'details', 'Generating Importer tool to include in backup archive: `' . $this->_backup['temp_directory'] . 'importbuddy.php`.' );
				pb_backupbuddy::status( 'startAction', 'importbuddyCreation' );
				backupbuddy_core::importbuddy( $this->_backup['temp_directory'] . 'importbuddy.php' );
				pb_backupbuddy::status( 'finishAction', 'importbuddyCreation' );
				pb_backupbuddy::status( 'details', 'Importer generation complete.' );
			} else { // dont include importbuddy.
				pb_backupbuddy::status( 'details', 'Importer tool inclusion in ZIP backup archive skipped based on settings or backup type.' );
			}
		}

		// Delete malware transient to force new checks on restored sites
		delete_transient( 'pb_backupbuddy_malwarescan' );
		pb_backupbuddy::status( 'details', __( 'Deleting malware transient.', 'it-l10n-backupbuddy' ) );

		// Save all of this.
		$this->_backup['init_complete'] = true; // pre_backup() completed.
		$this->_backup_options->save();

		pb_backupbuddy::status( 'details', __( 'Finished pre-backup procedures.', 'it-l10n-backupbuddy' ) );
		pb_backupbuddy::status( 'milestone', 'finish_settings' );

		pb_backupbuddy::status( 'finishFunction', json_encode( array( 'function' => 'pre_backup' ) ) );
		return true;

	}

	/**
	 * Process and run the next backup step.
	 *
	 * @param string $serial  Unique backup identifier.
	 * @param string $trigger What triggered this processing: manual or scheduled.
	 *
	 * @return bool  True on success, false otherwise.
	 */
	public function process_backup( $serial, $trigger = 'manual' ) {

		// Assign reference to back up data structure for this backup.
		if ( ! isset( $this->_backup_options ) ) {
			$attempt_transient_prefix = 'pb_backupbuddy_lock_attempts-';
			pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #39 for serial `' . $serial . '`...' );
			require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';

			$fileoptions_file = backupbuddy_core::getLogDirectory() . 'fileoptions/' . $serial . '.txt';

			if ( ! file_exists( $fileoptions_file ) ) {
				pb_backupbuddy::status( 'error', 'Error #8493484894: fileoptions file `' . $fileoptions_file . '` for this backup not found. Halting.' );
			}

			$this->_backup_options = new pb_backupbuddy_fileoptions( $fileoptions_file );

			if ( true !== ( $result = $this->_backup_options->is_ok() ) ) { // Unable to access fileoptions.

				$attempt_delay_base = 10; // Base number of seconds to delay. Each subsequent attempt increases this delay by a multiple of the attempt number.
				$max_attempts       = 30; // Max number of attempts to try to delay around a file lock. Delay increases each time.

				$this->_backup['serial'] = $serial; // Needs to be populated for use by cron schedule step.
				pb_backupbuddy::status( 'warning', __( 'Warning #9034 B. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Warning: ' . $result, $serial );

				// Track lock attempts in transient system. This is not vital & since locks may be having issues track this elsewhere.
				$lock_attempts = get_transient( $attempt_transient_prefix . $serial );
				if ( false === $lock_attempts ) {
					$lock_attempts = 0;
				}
				$lock_attempts++;
				set_transient( $attempt_transient_prefix . $serial, $lock_attempts, ( 60 * 60 * 24 ) ); // Increment lock attempts. Hold attempt count for 24 hours to help make sure we don't lose attempt count if very low site activity, etc.

				if ( $lock_attempts > $max_attempts ) {
					pb_backupbuddy::status( 'error', 'Backup halted. Maximum number of attempts made attempting to access locked fileoptions file. This may be caused by something causing backup steps to run out of order or file permission issues on the temporary directory holding the file `' . $fileoptions_file . '`. Verify correct permissions.', $serial );
					pb_backupbuddy::status( 'haltScript', '', $serial ); // Halt JS on page.
					delete_transient( $attempt_transient_prefix . $serial );
					return false;
				}

				$wait_time = $attempt_delay_base * $lock_attempts;
				pb_backupbuddy::status( 'warning', 'Warning #893943466: (This is not a problem if no other errors/warnings are encountered). A scheduled step attempted to run before the previous step completed. The previous step may have failed or two steps may be attempting to run simultaneously.', $serial );
				pb_backupbuddy::status( 'message', 'Waiting `' . $wait_time . '` seconds before continuing. Attempt #' . $lock_attempts . ' of ' . $max_attempts . ' max allowed before giving up.', $serial );
				return false;

			} else { // Accessed fileoptions. Clear/reset any attempt count.
				delete_transient( $attempt_transient_prefix . $serial );
			}
			pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );
			$this->_backup = &$this->_backup_options->options;
		}

		if ( '-1' == $this->_backup_options->options['finish_time'] ) {
			pb_backupbuddy::status( 'details', 'Warning #8328332: This backup is marked as cancelled. Halting.' );
			return false;
		}

		if ( $this->_backup_options->options['profile']['backup_mode'] != '1' ) { // Only check for cronPass action if in modern mode.
			pb_backupbuddy::status( 'finishAction', 'cronPass' );
		}

		// Handle cancelled backups (stop button).
		if ( ! empty( get_transient( 'pb_backupbuddy_stop_backup-' . $serial ) ) ) { // Backup flagged for stoppage. Proceed directly to clean up.

			pb_backupbuddy::status( 'message', 'Backup Stopped by user. Post backup cleanup step has been scheduled to clean up any temporary files.' );
			foreach ( $this->_backup['steps'] as $step_id => $step ) {
				if ( $step['function'] != 'post_backup' ) {
					if ( $step['start_time'] == 0 ) {
						$this->_backup['steps'][ $step_id ]['start_time'] = -1; // Flag for skipping.
					}
				} else { // Post backup step.
					$this->_backup['steps'][ $step_id ]['args'] = array( true, true ); // Run post_backup in fail mode & delete backup file.
				}
				backupbuddy_core::unschedule_event(
					backupbuddy_constants::CRON_HOOK,
					array(
						'process_backup',
						array(
							$serial,
							$step['function'],
						),
					),
					self::CRON_GROUP
				);

			}

			// pb_backupbuddy::save();
			$this->_backup_options->save();
			pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.

		}

		$found_next_step = false;

		// Loop through steps finding first step that has not run.
		$foundComplete = 0;
		foreach ( (array) $this->_backup['steps'] as $step_index => $step ) {
			$this->_currentStepIndex = $step_index;

			// A step is not marked to be skipped, has begun but has not finished. This should not happen but the WP cron is funky. Wait a while before continuing.
			if ( ( $step['start_time'] != -1 ) && ( $step['start_time'] != 0 ) && ( $step['finish_time'] == 0 ) ) {

				// Re-load, respecting locks to help avoid race conditions.
				$this->_backup_options->load( false, false, 0 );
				if ( true !== ( $this->_backup_options->is_ok() ) ) { // Unable to access fileoptions.
					pb_backupbuddy::status( 'warning', 'Unable to update out of order step attempt count due to file lock. It may be being written to by the other step at this moment.' );
				} else {
					pb_backupbuddy::status( 'details', 'Saving update to step attempt count.' );
					$this->_backup['steps'][ $step_index ]['attempts']++; // Increment this as an attempt.
					$this->_backup_options->save();
				}

				if ( ( $step['attempts'] < 6 ) ) {
					$wait_time = 60 * $step['attempts']; // Each attempt adds a minute of wait time.
					pb_backupbuddy::status( 'warning', 'A scheduled step attempted to run before the previous step completed. Waiting `' . $wait_time . '` seconds before continuing for it to catch up. Attempt number `' . $step['attempts'] . '`.' );
					$this->cron_next_step( $this->_backup['serial'], $step['function'], $wait_time );
					return false;
				} else { // Too many attempts to run this step.
					pb_backupbuddy::status( 'error', 'A scheduled step attempted to run before the previous step completed. After several attempts (`' . $step['attempts'] . '`) of failure Solid Backups has given up. Halting backup.' );
					return false;
				}

				break;

			} elseif ( 0 == $step['start_time'] ) { // Step that is not marked to be skipped and has not started yet.
				$found_next_step                                     = true;
				$this->_backup['steps'][ $step_index ]['start_time'] = microtime( true ); // Set this step time to now.
				$this->_backup['steps'][ $step_index ]['attempts']++; // Increment this as an attempt.
				$this->_backup_options->save();

				pb_backupbuddy::status( 'details', 'Found next step to run: `' . $step['function'] . '`.' );

				break; // Break out of foreach loop to continue.
			} elseif ( $step['start_time'] == -1 ) { // Step flagged for skipping. Do not run.
				pb_backupbuddy::status( 'details', 'Step `' . $step['function'] . '` flagged for skipping. Skipping.' );
			} else { // Last case: Finished. Skip.
				// Do nothing for completed steps.
				$foundComplete++;
			}
		}

		if ( empty( $found_next_step ) ) { // No more steps to perform; return.

			if ( count( $this->_backup['steps'] ) === $foundComplete ) {
				pb_backupbuddy::status( 'details', 'No more steps found. Total found completed: `' . $foundComplete . '`.' );
				return false;
			}

			/*
			 * NOTE: This should normally NOT be seen.
			 * If it is run then a cron was scheduled despite there being no steps left which would not make sense.
			 * This does appear some though as of Jul 22, 2015 for unknown reasons.
			 * Missing post_backup() function?
			 */
			pb_backupbuddy::status( 'details', 'Backup steps:' );
			pb_backupbuddy::status( 'details', print_r( $this->_backup['steps'], true ) );
			pb_backupbuddy::status( 'warning', 'No more unfinished steps found. This is not normal, but may not be harmful to the backup. Total found completed: `' . $foundComplete . '`.' );
			return false;
		}

		pb_backupbuddy::status(
			'details',
			sprintf(
				__( 'Peak memory usage: %s MB', 'it-l10n-backupbuddy' ),
				round( memory_get_peak_usage() / 1048576, 3 )
			)
		);

		/********* Begin Running Step Function */
		if ( method_exists( $this, $step['function'] ) ) {

			pb_backupbuddy::status( 'details', '-----' );
			pb_backupbuddy::status( 'details', 'Starting step function `' . $step['function'] . '`. Attempt #' . ( $step['attempts'] + 1 ) . '.' ); // attempts 0-indexed.

			$functionTitle    = $step['function'];
			$subFunctionTitle = '';
			$functionTitle    = backupbuddy_core::prettyFunctionTitle( $step['function'], $step['args'] );
			pb_backupbuddy::status(
				'startFunction',
				json_encode(
					array(
						'function' => $step['function'],
						'title'    => $functionTitle,
					)
				)
			);
			if ( '' != $subFunctionTitle ) {
				pb_backupbuddy::status(
					'startSubFunction',
					json_encode(
						array(
							'function' => $step['function'] . '_subfunctiontitle',
							'title'    => $subFunctionTitle,
						)
					)
				);
			}

			$response = call_user_func_array( array( &$this, $step['function'] ), $step['args'] );
		} else {
			pb_backupbuddy::status( 'error', __( 'Error #82783745: Invalid function `' . $step['function'] . '`' ) );
			$response = false;
		}
		/********* End Running Step Function */
		// unset( $step );

		if ( false === $response ) { // Function finished but reported failure.

			// Failure caused by backup cancellation.
			if ( ! empty( get_transient( 'pb_backupbuddy_stop_backup-' . $serial ) ) ) {
				pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
				return false;
			}

			pb_backupbuddy::status( 'error', 'Failed function `' . $this->_backup['steps'][ $step_index ]['function'] . '`. Backup terminated.' );
			pb_backupbuddy::status( 'errorFunction', $this->_backup['steps'][ $step_index ]['function'] );
			pb_backupbuddy::status( 'details', __( 'Peak memory usage', 'it-l10n-backupbuddy' ) . ': ' . round( memory_get_peak_usage() / 1048576, 3 ) . ' MB' );
			pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.

			$args            = print_r( $this->_backup['steps'][ $step_index ]['args'], true );
			$attachment      = null;
			$attachment_note = 'Enable full logging for troubleshooting (a log will be sent with future error emails while enabled).';

			if ( pb_backupbuddy::full_logging() ) {
				// Log file will be attached.
				$log_file = backupbuddy_core::getLogDirectory() . 'status-' . $serial . '_' . pb_backupbuddy::$options['log_serial'] . '.txt';
				if ( file_exists( $log_file ) ) {
					$attachment      = $log_file;
					$attachment_note = 'A log file is attached which may provide further details.';
				} else {
					$attachment = null;
				}
			}

			// Send error notification email.
			backupbuddy_core::mail_error(
				'One or more backup steps reported a failure. ' . $attachment_note . ' Backup failure running function `' . $this->_backup['steps'][ $step_index ]['function'] . '` with the arguments `' . $args . '` with backup serial `' . $serial . '`. Please run a manual backup of the same type to verify backups are working properly or view the backup status log.',
				null,
				$attachment
			);

			return false;

		} else { // Function finished successfully.

			$this->_backup['steps'][ $step_index ]['finish_time'] = microtime( true );
			if (
				( 'backup_create_database_dump' != $this->_backup['steps'][ $step_index ]['function'] ) // Do not wipe DB backup steps which track table dumps.
				&&
				( 'send_remote_destination' != $this->_backup['steps'][ $step_index ]['function'] ) // Do not wipe remote sends as these occur after integrity check and need to remain.
				) { // Wipe arguments.  Keeps fileoptions for growing crazily for finished steps containing state data such as deployment or new zip functionality passing chunking state.
				$this->_backup['steps'][ $step_index ]['args'] = time();
			}
			$this->_backup['updated_time'] = microtime( true );
			$this->_backup_options->save();

			pb_backupbuddy::status( 'details', sprintf( __( 'Finished function `%s`. Peak memory usage', 'it-l10n-backupbuddy' ) . ': ' . round( memory_get_peak_usage() / 1048576, 3 ) . ' MB', $this->_backup['steps'][ $step_index ]['function'] ) . ' with Solid Backups v' . pb_backupbuddy::settings( 'version' ) ) . '.';
			pb_backupbuddy::status( 'finishFunction', json_encode( array( 'function' => $this->_backup['steps'][ $step_index ]['function'] ) ) );
			pb_backupbuddy::status( 'details', '-----' );

			// If full logging, output fileoptions state data to browser for display in console.
			if (pb_backupbuddy::full_logging() ) {
				$thisBackup = $this->_backup;
				if ( '' != $this->_backup['deployment_direction'] ) { // Remove steps for deployment because it gets too large.
					$thisBackup['steps'] = '** Removed since deployment type **';
				}
				pb_backupbuddy::status( 'backupState', json_encode( $thisBackup ) ); // base64_encode( json_encode( $this->_backup ) ) );
			}

			// Check for more steps and insert into cron (possibly trigger).
			$found_another_step = false;
			foreach ( $this->_backup['steps'] as $next_step ) { // Loop through each step and see if any have not started yet.
				if ( $next_step['start_time'] == 0 ) { // Another step that was not started exists. Schedule it.
					$found_another_step = true;
					if ( $this->_backup['profile']['backup_mode'] == '2' ) { // Modern mode with crons.

						// Close down fileoptions so it unlocks now.
						pb_backupbuddy::status( 'details', 'Closing & unlocking fileoptions.' );
						$this->_backup_options = '';
						$this->cron_next_step( $this->_backup['serial'], $next_step['function'] );

					} elseif ( $this->_backup['profile']['backup_mode'] == '1' ) { // classic mode
						pb_backupbuddy::status( 'details', 'Classic mode; skipping cron & triggering next step.' );
						$this->process_backup( $this->_backup['serial'], $trigger );
					} else {
						pb_backupbuddy::status( 'error', 'Error #3838932: Fatal error. Unknown backup mode `' . $this->_backup['profile']['backup_mode'] . '`. Expected 1 (classic) or 2 (modern).' );
						return false;
					}

					break;
				}
			}

			// No more steps (note: fileoptions still open; only closed prior to cron triggering).
			if ( empty( $found_another_step ) ) {
				pb_backupbuddy::status( 'details', __( 'No more backup steps remain. Finishing...', 'it-l10n-backupbuddy' ) );
				$this->_backup['finish_time'] = microtime( true );
				$this->_backup_options->save();
				pb_backupbuddy::status(
					'startFunction',
					json_encode(
						array(
							'function' => 'backup_success',
							'title'    => __(
								'Backup completed successfully.',
								'it-l10n-backupbuddy'
							),
						)
					)
				);
				pb_backupbuddy::status( 'finishFunction', json_encode( array( 'function' => 'backup_success' ) ) );

				// Notification for manual and scheduled backups (omits deployment stuff).
				if ( ( $this->_backup['trigger'] == 'manual' ) || ( 'scheduled' == $this->_backup['trigger'] ) ) {

					$data                  = array();
					$data['serial']        = $this->_backup['serial'];
					$data['type']          = $this->_backup['type'];
					$data['profile_title'] = $this->_backup['profile']['title'];
					if ( '' != $this->_backup['schedule_title'] ) {
						$data['schedule_title'] = $this->_backup['schedule_title'];
					}

					// Close down fileoptions so it unlocks now.
					pb_backupbuddy::status( 'details', 'Closing & unlocking fileoptions.' );
					$this->_backup_options = '';

					backupbuddy_core::addNotification( 'backup_success', 'Backup completed successfully.', 'A ' . $this->_backup['trigger'] . ' backup has completed successfully on your site.', $data );
				}
			}

			pb_backupbuddy::status( 'details', 'Completed step function `' . $step['function'] . '`.' );

			return true;
		}

	}

	/**
	 * Schedule the next step into the cron. Defaults to scheduling to happen _NOW_. Automatically opens a loopback to trigger cron in another process by default.
	 *
	 * NOTE: fileoptions ($this->_backup) should be closed prior to running this. Not doing so could result in race condition issues.
	 *
	 * @param string $serial         Unique backup identifier.
	 * @param string $function_hame  Optional text title/function name/whatever of the next step to run. Useful for troubleshooting. Status logged.
	 * @param int    $wait_time  Seconds in the future for this process to run. Most likely set $spawn_cron false if using an offset. Default: -155 to force to top.
	 *
	 * @return void|false  Mixed return value.
	 */
	public function cron_next_step( $serial, $function_name = '', $wait_time = 0 ) {

		if ( '' != $this->_backup ) {
			pb_backupbuddy::status( 'warnings', 'Warning #438943984983. This warning may be okay and ignored. $this->_backup still appears to be set. Still a fileoptions object? May result in race condition issues if fileoptions still open and/or locked. Set = empty string to verify it shuts down properly prior to calling this function.' );
		}

		$message = sprintf(
			'Scheduling Cron for `%1$s`%2$s.',
			$serial,
			! empty( $function_name ) ? ' with function `' . $function_name . '`' : ''
		);

		pb_backupbuddy::status( 'details', $message );

		// Need to make sure the database connection is active. Sometimes it goes away during long bouts doing other things -- sigh.
		// This is not essential so use include and not require (suppress any warning)
		@include_once pb_backupbuddy::plugin_path() . '/lib/wpdbutils/wpdbutils.php';
		if ( class_exists( 'pluginbuddy_wpdbutils' ) ) {
			global $wpdb;
			$dbhelper = new pluginbuddy_wpdbutils( $wpdb );
			if ( ! $dbhelper->kick() ) {
				pb_backupbuddy::status( 'error', __( 'Database Server has gone away, unable to schedule next backup step. The backup cannot continue. This is most often caused by mysql running out of memory or timing out far too early. Please contact your host.', 'it-l10n-backupbuddy' ) );
				pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
				return false;
			}
		} else {
			pb_backupbuddy::status( 'details', __( 'Database Server connection status unverified.', 'it-l10n-backupbuddy' ) );
		}

		// Trigger event.
		$cron_args = array( $serial, $function_name );
		pb_backupbuddy::status(
			'details',
			sprintf(
				__( 'Triggering next step with cron hook %1$s to run method `process_backup` and serial arguments `%2$s`.', 'it-l10n-backupbuddy' ),
				backupbuddy_constants::CRON_HOOK,
				implode( ',', $cron_args )
			)
		);

		$has_scheduled_event = backupbuddy_core::has_pending_scheduled_event(
			self::CRON_GROUP
		);

		if ( ! $has_scheduled_event ) {

			pb_backupbuddy::status(
				'details',
				sprintf(
					__( 'Triggering next step with cron hook %1$s to run method `process_backup` and serial arguments `%2$s`.', 'it-l10n-backupbuddy' ),
					backupbuddy_constants::CRON_HOOK,
					implode( ',', $cron_args )
				)
			);

			// Trigger the event.
			if ( ! $wait_time ) {
				$scheduled = backupbuddy_core::trigger_async_event(
					'process_backup',
					$cron_args,
					self::CRON_GROUP
				);
			} else {
				$scheduled = backupbuddy_core::schedule_single_event(
					time() + $wait_time,
					'process_backup',
					$cron_args,
					self::CRON_GROUP
				);
			}
		}

		if ( false !== $scheduled ) {
			pb_backupbuddy::status( 'details', 'Next step scheduled.' );
			pb_backupbuddy::status( 'startAction', 'cronPass' );
			pb_backupbuddy::status(
				'cronParams',
				base64_encode(
					json_encode(
						array(
							'tag'    => backupbuddy_constants::CRON_HOOK,
							'method' => 'process_backup',
							'args'   => $cron_args,
						)
					)
				)
			);
		} else {
			pb_backupbuddy::status( 'details', 'Next step not scheduled. Verify that another plugin is not preventing / conflicting.' );
		}

		$next_step_note = '';
		if ( '' != $function_name ) {
			$next_step_note = ' (' . $function_name . ' expected)';
		}

		// The spaces here are intentional.
		pb_backupbuddy::status( 'details', 'About to run next step' . $next_step_note . "\n" . '---------- 	-------	------	If the backup does not proceed in 1 minute then something another process may be running, or you may be experiencing issues with WP-Cron.' );
		return;
	}

	/**
	 * Remove all pending backups.
	 *
	 * This is used by the Diagnostics page's
	 * "Force Cancel of all backups & transfers" button.
	 *
	 * @return int The number of events deleted, or false on error.
	 */
	public static function remove_pending_events() {
		return self::remove_events_by_status( ActionScheduler_Store::STATUS_PENDING );
	}

	/**
	 * Remove Completed & Cancelled backup creation events.
	 *
	 * Used by class backupbuddy_housekeeping.
	 *
	 * @return int The number of events deleted.
	 */
	public static function housekeeping() {
		$count = self::remove_events_by_status( ActionScheduler_Store::STATUS_COMPLETE );
		$count += self::remove_events_by_status( ActionScheduler_Store::STATUS_CANCELED );
		return $count;
	}

	/**
	 * Remove backup creation events.
	 *
	 * @return int The number of events deleted, or 0 on error.
	 */
	private static function remove_events_by_status( $status = '' ) {
		if ( empty( $status ) ) {
			$status = ActionScheduler_Store::STATUS_COMPLETE;
		}

		$query_args = array(
			'group'  => self::CRON_GROUP,
			'status' => $status,
		);

		$deleted = backupbuddy_core::delete_events( $query_args );

		if ( is_numeric( $deleted ) ) {
			return $deleted;
		}

		return 0;
	}

	/**
	 * Generates backupbuddy_dat.php within the temporary directory containing the
	 * random serial in its name. This file contains a serialized array that has been
	 * XOR encrypted for security. The XOR key is backupbuddy_SERIAL where SERIAL
	 * is the randomized set of characters in the ZIP filename. This file contains
	 * various information about the source site.
	 *
	 * @param string $trigger  What triggered this backup. Valid values: scheduled, manual.
	 *
	 * @return bool  True on success making dat file; else false.
	 */
	public function backup_create_dat_file( $trigger ) {
		$settings = array(
			'start_time'           => $this->_backup['start_time'],
			'backup_type'          => $this->_backup['type'],
			'profile'              => $this->_backup['profile'],
			'serial'               => $this->_backup['serial'],
			'breakout_tables'      => $this->_backup['breakout_tables'],
			'table_sizes'          => $this->_backup['table_sizes'],
			'force_single_db_file' => $this->_backup['force_single_db_file'],
			'deployment_direction' => $this->_backup['deployment_direction'],
			'trigger'              => $this->_backup['trigger'],
			'skip_database_dump'   => $this->_backup['profile']['skip_database_dump'],
			'db_excludes'          => backupbuddy_core::get_mysqldump_additional( 'excludes', $this->_backup['profile'] ),
			'db_includes'          => backupbuddy_core::get_mysqldump_additional( 'includes', $this->_backup['profile'] ),
			'custom_root'          => $this->_backup['custom_root'],
		);

		$dat_file    = $this->_backup['temp_directory'] . 'backupbuddy_dat.php';
		$dat_content = backupbuddy_data_file()->render_dat_contents( $settings, $dat_file );
		if ( ! is_array( $dat_content ) ) {
			pb_backupbuddy::status( 'error', 'Error #34894834: Unable to render DAT file. Check permissions. Details: `' . $dat_content . '`. Fatal error.' );
			return false;
		}

		// Handle wp-config.php file in a parent directory, copying to temp location.
		if ( true === $dat_content['wp-config_in_parent'] ) {
			if ( true === copy( dirname( ABSPATH ) . '/wp-config.php', $this->_backup['temp_directory'] . 'wp-config.php' ) ) {
				pb_backupbuddy::status( 'details', 'Copied wp-config.php from parent directory into working temp directory for backup.' );
			} else {
				pb_backupbuddy::status( 'error', 'Error #82394474: Failed to copy wp-config.php from parent directory into working temp directory for backup.' );
			}
		}

		pb_backupbuddy::status( 'details', __( 'Finished creating DAT (data) file.', 'it-l10n-backupbuddy' ) );

		return true;

	}

	/**
	 * Prepares configuration and passes to the mysqlbuddy library to handle backing up the database.
	 * Automatically handles falling back to compatibility modes.
	 *
	 * @param array $tables      Array of tables.
	 * @param int   $rows_start  Row to start.
	 *
	 * @return bool  True on success; false otherwise.
	 */
	public function backup_create_database_dump( $tables, $rows_start = 0 ) {

		pb_backupbuddy::status( 'milestone', 'start_database' );
		pb_backupbuddy::status( 'message', __( 'Starting database backup process.', 'it-l10n-backupbuddy' ) );

		if ( 'php' == pb_backupbuddy::$options['database_method_strategy'] ) {
			$force_methods = array( 'php' );
		} elseif ( 'commandline' == pb_backupbuddy::$options['database_method_strategy'] ) {
			$force_methods = array( 'commandline' );
		} elseif ( 'all' == pb_backupbuddy::$options['database_method_strategy'] ) {
			$force_methods = array( 'php', 'commandline' );
		} else {
			pb_backupbuddy::status( 'error', 'Error #48934: Invalid forced database dump method setting: `' . pb_backupbuddy::$options['database_method_strategy'] . '`.' );
			return false;
		}

		$maxExecution = backupbuddy_core::adjustedMaxExecutionTime();
		if ( $this->_backup['profile']['backup_mode'] == '1' ) { // Disable DB chunking when in classic mode.
			$maxExecution = -1;
		}

		// Load mysqlbuddy and perform dump.
		require_once pb_backupbuddy::plugin_path() . '/lib/mysqlbuddy/mysqlbuddy.php';
		global $wpdb;
		pb_backupbuddy::$classes['mysqlbuddy'] = new pb_backupbuddy_mysqlbuddy( DB_HOST, DB_NAME, DB_USER, DB_PASSWORD, $wpdb->prefix, $force_methods, $maxExecution ); // $database_host, $database_name, $database_user, $database_pass, $old_prefix, $force_method = array()

		// Force to single db_1.sql file if enabled via advanced options.
		if ( '1' == pb_backupbuddy::$options['force_single_db_file'] ) {
			pb_backupbuddy::$classes['mysqlbuddy']->force_single_db_file( true );
		}

		// Do the database dump.
		$result = pb_backupbuddy::$classes['mysqlbuddy']->dump( $this->_backup['temp_directory'], $tables, $rows_start ); // if is array, returns tables, rowstart

		if ( is_array( $result ) ) { // Chunking.
			$newStep = array(
				'function'    => 'backup_create_database_dump',
				'args'        => array( $result[0], $result[1] ),
				'start_time'  => 0,
				'finish_time' => 0,
				'attempts'    => 0,
			);
			array_splice( $this->_backup_options->options['steps'], $this->_currentStepIndex + 1, 0, array( $newStep ) );
			$this->_backup_options->save();
			pb_backupbuddy::status( 'details', 'Inserted additional database dump step at `' . ( $this->_currentStepIndex + 1 ) . '` to resume at row `' . $result[1] . '`. The next chunk will proceed shortly.' );
		}

		pb_backupbuddy::status( 'milestone', 'end_database' );

		return $result;

	}

	/**
	 * Builds the backup metadata array for inclusion in zip archive comment
	 *
	 * The $meta_overrides array allows us to provide an array of key=>value to
	 * add to or override values determined in the function. For example, if we allow
	 * the user to define a note pre backup creation (rather than adding afterward)
	 * then could be provided as array( 'note' => 'User provided note.')
	 *
	 * @param array $meta_overrides  Array of additional or override meta values
	 *
	 * @return array  Array of backup meta data.
	 */
	public function backup_build_backup_meta_data_array( $meta_overrides = array() ) {

		$meta = array();

		/*
		 * Calculate some statistics to store in meta later.
		 * These need to be calculated before zipping in case the DB goes away later to prevent a possible failure.
		 */

		// Posts
		$totalPosts = 0;
		foreach ( wp_count_posts( 'post' ) as $type => $count ) {
			$totalPosts += $count;
		}

		// Pages
		$totalPages = 0;
		foreach ( wp_count_posts( 'page' ) as $type => $count ) {
			$totalPages += $count;
		}

		// Comments
		$totalComments = wp_count_comments();
		$totalComments = empty( $totalComments->all ) ? 0 : (int) $totalComments->all;

		// Users
		$totalUsers = count_users();
		$totalUsers = $totalUsers['total_users'];

		global $wpdb;
		$db_prefix = $wpdb->prefix;

		global $wp_version;
		$meta = array(
			'serial'     => $this->_backup['serial'],
			'siteurl'    => site_url(),
			'type'       => $this->_backup['type'],
			'profile'    => $this->_backup['profile']['title'],
			'created'    => $this->_backup['start_time'],
			'generator'  => 'backupbuddy',
			'db_prefix'  => $db_prefix,
			'bb_version' => pb_backupbuddy::settings( 'version' ),
			'wp_version' => $wp_version,
			'dat_path'   => str_replace( $this->_backup['backup_root'], '', $this->_backup['temp_directory'] . 'backupbuddy_dat.php' ),
			'posts'      => $totalPosts,
			'pages'      => $totalPages,
			'comments'   => $totalComments,
			'users'      => $totalUsers,
			'note'       => '',
		);

		// Now merge in possible additions/overrides
		$meta = array_merge( $meta, $meta_overrides );

		return $meta;

	}

	/**
	 * Create ZIP file containing everything.
	 * Currently, this is just a wrapper around zip system specific functions
	 *
	 * @param array $state  State array.
	 *
	 * @return bool  True on success; false otherwise.
	 */
	public function backup_zip_files( $state = array() ) {

		if ( isset( pb_backupbuddy::$options['alternative_zip_2'] ) && ( '1' == pb_backupbuddy::$options['alternative_zip_2'] ) ) {

			$result = $this->backup_zip_files_alternate( $state );

		} else {

			$result = $this->backup_zip_files_standard();

		}

		return $result;

	}

	/**
	 * Create ZIP file containing everything.
	 *
	 * @return bool  True on success; false otherwise.
	 */
	protected function backup_zip_files_standard() {

		pb_backupbuddy::status( 'milestone', 'start_files' );
		pb_backupbuddy::status( 'details', 'Backup root: `' . $this->_backup['backup_root'] . '`.' );

		// Set compression on / off.
		// pb_backupbuddy::$classes['zipbuddy']->set_compression( $this->_backup['compression'] );

		// Currently we'll still allow skipping the addition of the metadata in the comment
		// but eventually this will become mandatory (in al likelihood)

		// Save meta information in comment.
		if ( '0' == pb_backupbuddy::$options['save_comment_meta'] ) {

			pb_backupbuddy::status( 'details', 'Skipping saving meta data to zip comment based on settings.' );
			$comment = '';

		} else {

			pb_backupbuddy::status( 'details', 'Saving meta data to zip comment.' );
			$comment = backupbuddy_core::normalize_comment_data( $this->backup_build_backup_meta_data_array() );

		}

		// The zip archive is always created in a temporary working location and only moved to the storage
		// location if successful, so we start by defining the working archive file to use
		$working_archive_file = $this->_backup['temporary_zip_directory'] . basename( $this->_backup['archive_file'] );

		// Always create the empty zip archive with the optional metadata comment added at this point.
		// Also pass in a directory that can be used by any functionality associated with creating the archive - we'll
		// use the temporary directory where we are building the working zip archive.
		// This is method independent so is done just in zipbuddy.
		$zip_response = pb_backupbuddy::$classes['zipbuddy']->create_empty_zip(
			$working_archive_file,
			$comment,
			$this->_backup['temporary_zip_directory']
		);

		if ( false === $zip_response ) {

			// Delete temporary data directory.
			if ( file_exists( $this->_backup['temp_directory'] ) ) {
				pb_backupbuddy::status( 'details', __( 'Removing temp data directory.', 'it-l10n-backupbuddy' ) );
				pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temp_directory'] );
			}

			// Delete temporary ZIP directory.
			if ( file_exists( $this->_backup['temporary_zip_directory'] ) ) {
				pb_backupbuddy::status( 'details', __( 'Removing temp zip directory.', 'it-l10n-backupbuddy' ) );
				pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temporary_zip_directory'] );
			}

			pb_backupbuddy::status( 'error', __( 'Error #4001: Unable to successfully generate ZIP archive. Backup FAILED. See logs above for more information.', 'it-l10n-backupbuddy' ) );
			pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
			return false;

		}

		// Now add a "dummy"" file to the empty zip so that we can "grow" the archive with every zip method.
		// This _has_ to be done for command line zip because it will not grow an empty zip but will revert to
		// "copy and add" and also output a warning which we do not want - and GoDaddy shared servers have a bad
		// failure mode that can happen with copy and add because the server does not handle the files properly,
		// and it ends up leaving us with two archive files so if we add teh dummy file then we can grow the
		// archive and thus avoid the copy and add behaviour to work around the GoDaddy server failure mode.
		// Note: this capability is only supported by the PclZip method but this will be taken care of byCount
		// zipbuddy as it will know that it has to invoke the function on that method.
		$virtual_file_descriptor = array(
			array(
				'filename' => '.itbub',
				'content'  => '',
			),
		);

		$zip_response = pb_backupbuddy::$classes['zipbuddy']->add_virtual_file_to_zip(
			$working_archive_file,
			$virtual_file_descriptor,
			$this->_backup['temporary_zip_directory']
		);

		if ( false === $zip_response ) {

			// Delete temporary data directory.
			if ( file_exists( $this->_backup['temp_directory'] ) ) {
				pb_backupbuddy::status( 'details', __( 'Removing temp data directory.', 'it-l10n-backupbuddy' ) );
				pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temp_directory'] );
			}

			// Delete temporary ZIP directory.
			if ( file_exists( $this->_backup['temporary_zip_directory'] ) ) {
				pb_backupbuddy::status( 'details', __( 'Removing temp zip directory.', 'it-l10n-backupbuddy' ) );
				pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temporary_zip_directory'] );
			}

			pb_backupbuddy::status( 'error', __( 'Error #4001: Unable to successfully generate ZIP archive. Backup FAILED. See logs above for more information.', 'it-l10n-backupbuddy' ) );
			pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
			return false;

		}

		// We need to check that the initialized zip archive file is "visible" to the functionality that is
		// going to grow it with additional files. It seems on some hosting processing gets "distributed" so
		// a spawned process may not be running on the same server as the spawning process (according to one
		// host where this issue was apparent) and it may take some moments for the created file to be "visible"
		// to a process on another server. The problem manifests as the command line zip utility not finding the
		// zip archive that has been created and so it then creates a new one that has neither the comment with
		// the metadata nor the initial virtual file.
		// Note that the zip archive at this point should be in the temp directory so that's where we look.
		pb_backupbuddy::status( 'details', __( 'Verifying initialized zip archive file exists.', 'it-l10n-backupbuddy' ) );

		$retry_count = 3;
		$retry_delay = 2;

		$zip_response = pb_backupbuddy::$classes['zipbuddy']->file_exists( $working_archive_file, '.itbub' );

		// Either exit from this loop with $zip_response true because we found the zip and file or false
		// because we failed to find the zip or file
		while ( ( false === $zip_response ) && ( 0 < $retry_count-- ) ) {

			// Short delay and try again...
			pb_backupbuddy::status( 'details', __( 'Not verified, trying again...', 'it-l10n-backupbuddy' ) );
			sleep( $retry_delay );

			$zip_response = pb_backupbuddy::$classes['zipbuddy']->file_exists( $working_archive_file, '.itbub' );

		}

		// Zip results.
		if ( true === $zip_response ) { // Appears to exist...

			pb_backupbuddy::status( 'details', __( 'Verified initialized zip archive file exists.', 'it-l10n-backupbuddy' ) );

		} else { // Hmm, we couldn't find the zip, or it didn't contain the initial file

			pb_backupbuddy::status( 'details', __( 'Could not verify existence of initialized zip archive file.', 'it-l10n-backupbuddy' ) );

		}

		if ( false === $zip_response ) {

			// Delete temporary data directory.
			if ( file_exists( $this->_backup['temp_directory'] ) ) {
				pb_backupbuddy::status( 'details', __( 'Removing temp data directory.', 'it-l10n-backupbuddy' ) );
				pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temp_directory'] );
			}

			// Delete temporary ZIP directory.
			if ( file_exists( $this->_backup['temporary_zip_directory'] ) ) {
				pb_backupbuddy::status( 'details', __( 'Removing temp zip directory.', 'it-l10n-backupbuddy' ) );
				pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temporary_zip_directory'] );
			}

			pb_backupbuddy::status( 'error', __( 'Error #4001: Unable to successfully generate ZIP archive. Backup FAILED. See logs above for more information.', 'it-l10n-backupbuddy' ) );
			pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
			return false;

		} // end zip failure.

		// Create zip file!
		$zip_response = pb_backupbuddy::$classes['zipbuddy']->add_directory_to_zip(
			$this->_backup['archive_file'],                                 // string	Zip file to create.
			$this->_backup['backup_root'],                                  // string	Directory to zip up (root).
			$this->_backup['directory_exclusions'],                         // array	Files/directories to exclude. (array of strings).
			$this->_backup['temporary_zip_directory']                       // string	Temp directory location to store zip file in.
		);

		// Zip results.
		if ( true === $zip_response ) { // Zip success.

			// We got a valid zip archive file created in the working directory so now try and move
			// to the final storage location.
			pb_backupbuddy::status( 'details', __( 'Moving Zip Archive file to local archive directory.', 'it-l10n-backupbuddy' ) );

			// Make sure no stale file information
			clearstatcache();

			// Relocate the temporary zip file to final location
			@rename( $working_archive_file, $this->_backup['archive_file'] );

			// Check that we moved the file ok
			if ( @file_exists( $this->_backup['archive_file'] ) ) {

				// Managed to move the archive to the local archive storage, so basically we've done it
				pb_backupbuddy::status( 'details', __( 'Zip Archive file moved to local archive directory.', 'it-l10n-backupbuddy' ) );

				// $this->log_archive_file_stats( $zip );

				// We made it...try and set standard permissions and whatever happens drop through as a success
				pb_backupbuddy::status( 'message', __( 'Backup ZIP file successfully created.', 'it-l10n-backupbuddy' ) );
				if ( chmod( $this->_backup['archive_file'], 0644 ) ) {
					pb_backupbuddy::status( 'details', __( 'Chmod of ZIP file to 0644 succeeded.', 'it-l10n-backupbuddy' ) );
				} else {
					pb_backupbuddy::status( 'details', __( 'Chmod of ZIP file to 0644 failed.', 'it-l10n-backupbuddy' ) );
				}

				// We'll let the temp_directory and temporary_zip_directory stay in existence as they will be cleaned
				// up in teh post backup step - in theory we could at least remove the temporary_zip_directory as only
				// we should be using it but for now we'll let it be.

			} else {

				// For whatever reason we couldn't move the file to the local archive storage, so we have to bail out
				pb_backupbuddy::status( 'details', __( 'Zip Archive file could not be moved to local archive directory.', 'it-l10n-backupbuddy' ) );

				// Delete temporary data directory.
				if ( file_exists( $this->_backup['temp_directory'] ) ) {
					pb_backupbuddy::status( 'details', __( 'Removing temp data directory.', 'it-l10n-backupbuddy' ) );
					pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temp_directory'] );
				}

				// Delete temporary ZIP directory.
				if ( file_exists( $this->_backup['temporary_zip_directory'] ) ) {
					pb_backupbuddy::status( 'details', __( 'Removing temp zip directory.', 'it-l10n-backupbuddy' ) );
					pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temporary_zip_directory'] );
				}

				pb_backupbuddy::status( 'error', __( 'Error #4001: Unable to successfully generate ZIP archive. Backup FAILED. See logs above for more information.', 'it-l10n-backupbuddy' ) );
				pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
				return false;

			}
		} else { // Zip failure.

			// Failed to get a zip for some reason that the creation function will have logged
			// Nothing more to do but clean up and bail out

			// Delete temporary data directory.
			if ( file_exists( $this->_backup['temp_directory'] ) ) {
				pb_backupbuddy::status( 'details', __( 'Removing temp data directory.', 'it-l10n-backupbuddy' ) );
				pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temp_directory'] );
			}

			// Delete temporary ZIP directory.
			if ( file_exists( $this->_backup['temporary_zip_directory'] ) ) {
				pb_backupbuddy::status( 'details', __( 'Removing temp zip directory.', 'it-l10n-backupbuddy' ) );
				pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temporary_zip_directory'] );
			}

			pb_backupbuddy::status( 'error', __( 'Error #4001: Unable to successfully generate ZIP archive. Backup FAILED. See logs above for more information.', 'it-l10n-backupbuddy' ) );
			pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
			return false;

		} // end zip failure.

		// Need to make sure the database connection is active. Sometimes it goes away during long bouts doing other things -- sigh.
		// This is not essential so use include and not require (suppress any warning)
		@include_once pb_backupbuddy::plugin_path() . '/lib/wpdbutils/wpdbutils.php';
		if ( class_exists( 'pluginbuddy_wpdbutils' ) ) {
			// This is the database object we want to use
			global $wpdb;

			// Get our helper object and let it use us to output status messages
			$dbhelper = new pluginbuddy_wpdbutils( $wpdb );

			// If we cannot kick the database into life then signal the error and return false which will stop the backup
			// Otherwise all is ok, and we can just fall through and let the function return true
			if ( ! $dbhelper->kick() ) {
				pb_backupbuddy::status( 'error', __( 'Backup FAILED. Backup file produced but Database Server has gone away, unable to schedule next backup step', 'it-l10n-backupbuddy' ) );
				return false;
			} else {
				// pb_backupbuddy::status( 'details', 'Database seems to still be connected.' );
			}
		} else {
			// Utils not available so cannot verify database connection status - just notify
			pb_backupbuddy::status( 'details', __( 'Database Server connection status unverified.', 'it-l10n-backupbuddy' ) );
		}

		pb_backupbuddy::status( 'milestone', 'finish_files' );

		return true;

	}

	/**
	 * Create ZIP file containing everything.
	 *
	 * @param array $state  Zip creation state information for subsequent steps
	 *
	 * @return bool|array  True on success (completion); state array for continuation; false on failure.
	 */
	protected function backup_zip_files_alternate( $state = array() ) {

		pb_backupbuddy::status( 'milestone', 'start_files_alternate' );

		// Dependent on the zip build strategy chosen we will need to set various operational
		// parameters on the zipbuddy object to be used by the method building the zip. Eventually
		// we can use strategy objects but for now we'll do it the old-fashioned way.
		// Strategies are:
		// Single-Burst/Single-Step: Step Period = Infinite; Min/Max Burst Content Size = Infinite;
		// Multi-Burst/Single-Step: Step Period = Infinite; Min/Max Burst Content Size = Per-Config;
		// Multi-Burst/Multi-Step: Step Period = Per-Config; Min/Max Burst Content Size = Per-Config;

		$zip_build_strategy_name = array(
			self::ZIP_BUILD_STRATEGY_SBSS => 'Single-Burst/Single-Step',
			self::ZIP_BUILD_STRATEGY_MBSS => 'Multi-Burst/Single-Step',
			self::ZIP_BUILD_STRATEGY_MBMS => 'Multi-Burst/Multi-Step',
		);

		// Get the current strategy
		if ( isset( pb_backupbuddy::$options['zip_build_strategy'] ) ) {

			$zip_build_strategy = pb_backupbuddy::$options['zip_build_strategy'];
			if ( ( self::ZIP_BUILD_STRATEGY_MIN > $zip_build_strategy ) || ( self::ZIP_BUILD_STRATEGY_MAX < $zip_build_strategy ) ) {

				// Hmm, not valid - have to revert to default
				$zip_build_strategy = self::ZIP_BUILD_STRATEGY_MBSS;
				pb_backupbuddy::status( 'details', 'Zip Build Strategy not recognized - reverting to: ' . $zip_build_strategy_name[ $zip_build_strategy ] );

			} else {

				pb_backupbuddy::status( 'details', 'Zip Build Strategy: ' . $zip_build_strategy_name[ $zip_build_strategy ] );

			}
		} else {

			// Hmm, should be set - have to revert to default
			$zip_build_strategy = self::ZIP_BUILD_STRATEGY_MBSS;
			pb_backupbuddy::status( 'details', 'Zip Build Strategy not set - reverting to: ' . $zip_build_strategy_name[ $zip_build_strategy ] );

		}

		// Now we have to check if running in Classic mode. If yes then we cannot use multi-step without continually
		// resetting the "start" time for the zip monitor. The better approach is to override the zip build strategy
		// if it is a multi-step strategy and at least revert it to multi-burst/single-step. If it is already this
		// or single-burst/single-step we can leave it as it is
		// The backup mode details _should_ be available through this class variable created in pre_backup() function.
		if ( $this->_backup['profile']['backup_mode'] == '1' ) {

			// Running in Classic mode...
			if ( self::ZIP_BUILD_STRATEGY_MBSS < $zip_build_strategy ) {

				$zip_build_strategy = self::ZIP_BUILD_STRATEGY_MBSS;
				pb_backupbuddy::status( 'details', 'Zip Build Strategy overridden as incompatible with Classic backup mode - reverting to: ' . $zip_build_strategy_name[ $zip_build_strategy ] );

			}
		}

		// Now based on the strategy set build parameters that we will set on the zipbuddy object that
		// define the zip build behaviour
		switch ( $zip_build_strategy ) {

			case self::ZIP_BUILD_STRATEGY_SBSS:
				$step_period       = PHP_INT_MAX; // Effectively infinite
				$burst_min_content = ( 4 == PHP_INT_SIZE ) ? (float) ( pow( 2, 63 ) - 1 ) : (float) PHP_INT_MAX; // Hack to get large value for either 32 or 64 bit PHP
				$burst_max_content = ( 4 == PHP_INT_SIZE ) ? (float) ( pow( 2, 63 ) - 1 ) : (float) PHP_INT_MAX;
				break;
			case self::ZIP_BUILD_STRATEGY_MBSS:
				$step_period       = PHP_INT_MAX;
				$burst_min_content = null;
				$burst_max_content = null;
				break;
			case self::ZIP_BUILD_STRATEGY_MBMS:
				$step_period       = null; // Force the option value to be used
				$burst_min_content = null;
				$burst_max_content = null;
				break;

		}

		// We can set the values on the zipbuddy object at this point
		pb_backupbuddy::$classes['zipbuddy']->set_step_period( $step_period );
		pb_backupbuddy::$classes['zipbuddy']->set_min_burst_content( $burst_min_content );
		pb_backupbuddy::$classes['zipbuddy']->set_max_burst_content( $burst_max_content );

		if ( empty( $state ) ) {

			// This is our first (and perhaps only) call, so do first time stuff

			pb_backupbuddy::status( 'milestone', 'start_files' );
			pb_backupbuddy::status( 'details', 'Backup root: `' . $this->_backup['backup_root'] . '`.' );

			// Set compression on / off.
			// pb_backupbuddy::$classes['zipbuddy']->set_compression( $this->_backup['compression'] );

			// Currently we'll still allow skipping the addition of the metadata in the comment
			// but eventually this will become mandatory (in al likelihood)

			// Save meta information in comment.
			if ( '0' == pb_backupbuddy::$options['save_comment_meta'] ) {

				pb_backupbuddy::status( 'details', 'Skipping saving meta data to zip comment based on settings.' );
				$comment = '';

			} else {

				pb_backupbuddy::status( 'details', 'Saving meta data to zip comment.' );
				$comment = backupbuddy_core::normalize_comment_data( $this->backup_build_backup_meta_data_array() );

			}

			// The zip archive is always created in a temporary working location and only moved to the storage
			// location if successful, so we start by defining the working archive file to use
			$working_archive_file = $this->_backup['temporary_zip_directory'] . basename( $this->_backup['archive_file'] );

			// Always create the empty zip archive with the optional metadata comment added at this point.
			// Also pass in a directory that can be used by any functionality associated with creating the archive - we'll
			// use the temporary directory where we are building the working zip archive
			// This is method independent so is done just in zipbuddy.
			$zip_response = pb_backupbuddy::$classes['zipbuddy']->create_empty_zip(
				$working_archive_file,
				$comment,
				$this->_backup['temporary_zip_directory']
			);

			if ( false === $zip_response ) {

				// Delete temporary data directory.
				if ( file_exists( $this->_backup['temp_directory'] ) ) {
					pb_backupbuddy::status( 'details', __( 'Removing temp data directory.', 'it-l10n-backupbuddy' ) );
					pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temp_directory'] );
				}

				// Delete temporary ZIP directory.
				if ( file_exists( $this->_backup['temporary_zip_directory'] ) ) {
					pb_backupbuddy::status( 'details', __( 'Removing temp zip directory.', 'it-l10n-backupbuddy' ) );
					pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temporary_zip_directory'] );
				}

				pb_backupbuddy::status( 'error', __( 'Error #4001: Unable to successfully generate ZIP archive. Backup FAILED. See logs above for more information.', 'it-l10n-backupbuddy' ) );
				pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
				return false;

			}

			// Now add a "dummy"" file to the empty zip so that we can "grow" the archive with every zip method.
			// This _has_ to be done for command line zip because it will not grow an empty zip but will revert to
			// "copy and add" and also output a warning which we do not want - and GoDaddy shared servers have a bad
			// failure mode that can happen with copy and add because the server does not handle the files properly,
			// and it ends up leaving us with two archive files so if we add teh dummy file then we can grow the
			// archive and thus avoid the copy and add behaviour to work around the GoDaddy server failure mode.
			// Note: this capability is only supported by the PclZip method but this will be taken care of byCount
			// zipbuddy as it will know that it has to invoke the function on that method.
			$virtual_file_descriptor = array(
				array(
					'filename' => '.itbub',
					'content'  => '',
				),
			);

			$zip_response = pb_backupbuddy::$classes['zipbuddy']->add_virtual_file_to_zip(
				$working_archive_file,
				$virtual_file_descriptor,
				$this->_backup['temporary_zip_directory']
			);

			if ( false === $zip_response ) {

				// Delete temporary data directory.
				if ( file_exists( $this->_backup['temp_directory'] ) ) {
					pb_backupbuddy::status( 'details', __( 'Removing temp data directory.', 'it-l10n-backupbuddy' ) );
					pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temp_directory'] );
				}

				// Delete temporary ZIP directory.
				if ( file_exists( $this->_backup['temporary_zip_directory'] ) ) {
					pb_backupbuddy::status( 'details', __( 'Removing temp zip directory.', 'it-l10n-backupbuddy' ) );
					pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temporary_zip_directory'] );
				}

				pb_backupbuddy::status( 'error', __( 'Error #4001: Unable to successfully generate ZIP archive. Backup FAILED. See logs above for more information.', 'it-l10n-backupbuddy' ) );
				pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
				return false;

			}

			// We need to check that the initialized zip archive file is "visible" to the functionality that is
			// going to grow it with additional files. It seems on some hosting processing gets "distributed" so
			// a spawned process may not be running on the same server as the spawning process (according to one
			// host where this issue was apparent) and it may take some moments for the created file to be "visible"
			// to a process on another server. The problem manifests as the command line zip utility not finding the
			// zip archive that has been created and so it then creates a new one that has neither the comment with
			// the metadata nor the initial virtual file.
			// Note that the zip archive at this point should be in the temp directory so that's where we look.
			pb_backupbuddy::status( 'details', __( 'Verifying initialized zip archive file exists.', 'it-l10n-backupbuddy' ) );

			$retry_count = 3;
			$retry_delay = 2;

			$zip_response = pb_backupbuddy::$classes['zipbuddy']->file_exists( $working_archive_file, '.itbub' );

			// Either exit from this loop with $zip_response true because we found the zip and file or false
			// because we failed to find the zip or file
			while ( ( false === $zip_response ) && ( 0 < $retry_count-- ) ) {

				// Short delay and try again...
				pb_backupbuddy::status( 'details', __( 'Not verified, trying again...', 'it-l10n-backupbuddy' ) );
				sleep( $retry_delay );

				$zip_response = pb_backupbuddy::$classes['zipbuddy']->file_exists( $working_archive_file, '.itbub' );

			}

			// Zip results.
			if ( true === $zip_response ) { // Appears to exist...

				pb_backupbuddy::status( 'details', __( 'Verified initialized zip archive file exists.', 'it-l10n-backupbuddy' ) );

			} else { // Hmm, we couldn't find the zip, or it didn't contain the initial file

				pb_backupbuddy::status( 'details', __( 'Could not verify existence of initialized zip archive file.', 'it-l10n-backupbuddy' ) );

			}

			if ( false === $zip_response ) {

				// Delete temporary data directory.
				if ( file_exists( $this->_backup['temp_directory'] ) ) {
					pb_backupbuddy::status( 'details', __( 'Removing temp data directory.', 'it-l10n-backupbuddy' ) );
					pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temp_directory'] );
				}

				// Delete temporary ZIP directory.
				if ( file_exists( $this->_backup['temporary_zip_directory'] ) ) {
					pb_backupbuddy::status( 'details', __( 'Removing temp zip directory.', 'it-l10n-backupbuddy' ) );
					pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temporary_zip_directory'] );
				}

				pb_backupbuddy::status( 'error', __( 'Error #4001: Unable to successfully generate ZIP archive. Backup FAILED. See logs above for more information.', 'it-l10n-backupbuddy' ) );
				pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
				return false;

			} // end zip failure.

			// Create zip file!
			$zip_response = pb_backupbuddy::$classes['zipbuddy']->add_directory_to_zip(
				$this->_backup['archive_file'],                                 // string	Zip file to create.
				$this->_backup['backup_root'],                                  // string	Directory to zip up (root).
				$this->_backup['directory_exclusions'],                         // array	Files/directories to exclude. (array of strings).
				$this->_backup['temporary_zip_directory']                       // string	Temp directory location to store zip file in.
			);

			// Zip results.
			if ( true === $zip_response ) { // Zip success.

				// We got a valid zip archive file created in the working directory so now try and move
				// to the final storage location.
				pb_backupbuddy::status( 'details', __( 'Moving Zip Archive file to local archive directory.', 'it-l10n-backupbuddy' ) );

				// Make sure no stale file information
				clearstatcache();

				// Relocate the temporary zip file to final location
				@rename( $working_archive_file, $this->_backup['archive_file'] );

				// Check that we moved the file ok
				if ( @file_exists( $this->_backup['archive_file'] ) ) {

					// Managed to move the archive to the local archive storage, so basically we've done it
					pb_backupbuddy::status( 'details', __( 'Zip Archive file moved to local archive directory.', 'it-l10n-backupbuddy' ) );

					// $this->log_archive_file_stats( $zip );

					// We made it...try and set standard permissions and whatever happens drop through as a success
					pb_backupbuddy::status( 'message', __( 'Backup ZIP file successfully created.', 'it-l10n-backupbuddy' ) );
					if ( chmod( $this->_backup['archive_file'], 0644 ) ) {
						pb_backupbuddy::status( 'details', __( 'Chmod of ZIP file to 0644 succeeded.', 'it-l10n-backupbuddy' ) );
					} else {
						pb_backupbuddy::status( 'details', __( 'Chmod of ZIP file to 0644 failed.', 'it-l10n-backupbuddy' ) );
					}

					// We'll let the temp_directory and temporary_zip_directory stay in existence as they will be cleaned
					// up in teh post backup step - in theory we could at least remove the temporary_zip_directory as only
					// we should be using it but for now we'll let it be.

				} else {

					// For whatever reason we couldn't move the file to the local archive storage, so we have to bail out
					pb_backupbuddy::status( 'details', __( 'Zip Archive file could not be moved to local archive directory.', 'it-l10n-backupbuddy' ) );

					// Delete temporary data directory.
					if ( file_exists( $this->_backup['temp_directory'] ) ) {
						pb_backupbuddy::status( 'details', __( 'Removing temp data directory.', 'it-l10n-backupbuddy' ) );
						pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temp_directory'] );
					}

					// Delete temporary ZIP directory.
					if ( file_exists( $this->_backup['temporary_zip_directory'] ) ) {
						pb_backupbuddy::status( 'details', __( 'Removing temp zip directory.', 'it-l10n-backupbuddy' ) );
						pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temporary_zip_directory'] );
					}

					pb_backupbuddy::status( 'error', __( 'Error #4001: Unable to successfully generate ZIP archive. Backup FAILED. See logs above for more information.', 'it-l10n-backupbuddy' ) );
					pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
					return false;

				}
			} elseif ( is_array( $zip_response ) ) {

				// First step returned a continuation state, so we only do some stuff and then queue
				// a continuation step

				// Now recover the returned state and queue the next step.
				$newStep = array(
					'function'    => 'backup_zip_files',
					'args'        => array( $zip_response ),
					'start_time'  => 0,
					'finish_time' => 0,
					'attempts'    => 0,
				);
				array_splice( $this->_backup_options->options['steps'], $this->_currentStepIndex + 1, 0, array( $newStep ) );
				$this->_backup_options->save();
				pb_backupbuddy::status( 'details', 'Inserted additional zip grow step at `' . ( $this->_currentStepIndex + 1 ) . '` to resume at index `' . $zip_response['zipper']['fp'] . '`. The next chunk will proceed shortly.' );

			} else { // Zip failure.

				// Failed to get a zip for some reason that the creation function will have logged
				// Nothing more to do but clean up and bail out

				// Delete temporary data directory.
				if ( file_exists( $this->_backup['temp_directory'] ) ) {
					pb_backupbuddy::status( 'details', __( 'Removing temp data directory.', 'it-l10n-backupbuddy' ) );
					pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temp_directory'] );
				}

				// Delete temporary ZIP directory.
				if ( file_exists( $this->_backup['temporary_zip_directory'] ) ) {
					pb_backupbuddy::status( 'details', __( 'Removing temp zip directory.', 'it-l10n-backupbuddy' ) );
					pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temporary_zip_directory'] );
				}

				pb_backupbuddy::status( 'error', __( 'Error #4001: Unable to successfully generate ZIP archive. Backup FAILED. See logs above for more information.', 'it-l10n-backupbuddy' ) );
				pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
				return false;

			} // end zip failure.
		} else {

			// Continuation step with a state

			// The zip archive is always created in a temporary working location and only moved to the storage
			// location if successful, so we start by defining the working archive file to use
			$working_archive_file = $this->_backup['temporary_zip_directory'] . basename( $this->_backup['archive_file'] );

			$zip_response = pb_backupbuddy::$classes['zipbuddy']->grow_zip(
				$this->_backup['archive_file'],                                 // string	Zip file to create.
				$this->_backup['temporary_zip_directory'],                      // string	Temp directory location to store zip file in.
				$state
			);

			if ( true === $zip_response ) { // Zip success.

				// We got a valid zip archive file created in the working directory so now try and move
				// to the final storage location.
				pb_backupbuddy::status( 'details', __( 'Moving Zip Archive file to local archive directory.', 'it-l10n-backupbuddy' ) );

				// Make sure no stale file information
				clearstatcache();

				// Relocate the temporary zip file to final location
				@rename( $working_archive_file, $this->_backup['archive_file'] );

				// Check that we moved the file ok
				if ( @file_exists( $this->_backup['archive_file'] ) ) {

					// Managed to move the archive to the local archive storage, so basically we've done it
					pb_backupbuddy::status( 'details', __( 'Zip Archive file moved to local archive directory.', 'it-l10n-backupbuddy' ) );

					// $this->log_archive_file_stats( $zip );

					// We made it...try and set standard permissions and whatever happens drop through as a success
					pb_backupbuddy::status( 'message', __( 'Backup ZIP file successfully created.', 'it-l10n-backupbuddy' ) );
					if ( chmod( $this->_backup['archive_file'], 0644 ) ) {
						pb_backupbuddy::status( 'details', __( 'Chmod of ZIP file to 0644 succeeded.', 'it-l10n-backupbuddy' ) );
					} else {
						pb_backupbuddy::status( 'details', __( 'Chmod of ZIP file to 0644 failed.', 'it-l10n-backupbuddy' ) );
					}

					// We'll let the temp_directory and temporary_zip_directory stay in existence as they will be cleaned
					// up in teh post backup step - in theory we could at least remove the temporary_zip_directory as only
					// we should be using it but for now we'll let it be.

				} else {

					// For whatever reason we couldn't move the file to the local archive storage, so we have to bail out
					pb_backupbuddy::status( 'details', __( 'Zip Archive file could not be moved to local archive directory.', 'it-l10n-backupbuddy' ) );

					// Delete temporary data directory.
					if ( file_exists( $this->_backup['temp_directory'] ) ) {
						pb_backupbuddy::status( 'details', __( 'Removing temp data directory.', 'it-l10n-backupbuddy' ) );
						pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temp_directory'] );
					}

					// Delete temporary ZIP directory.
					if ( file_exists( $this->_backup['temporary_zip_directory'] ) ) {
						pb_backupbuddy::status( 'details', __( 'Removing temp zip directory.', 'it-l10n-backupbuddy' ) );
						pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temporary_zip_directory'] );
					}

					pb_backupbuddy::status( 'error', __( 'Error #4001: Unable to successfully generate ZIP archive. Backup FAILED. See logs above for more information.', 'it-l10n-backupbuddy' ) );
					pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
					return false;

				}
			} elseif ( is_array( $zip_response ) ) {

				// First step returned a continuation state, so we only do some stuff and then queue
				// a continuation step

				// Now recover the returned state and queue the next step.
				$newStep = array(
					'function'    => 'backup_zip_files',
					'args'        => array( $zip_response ),
					'start_time'  => 0,
					'finish_time' => 0,
					'attempts'    => 0,
				);
				array_splice( $this->_backup_options->options['steps'], $this->_currentStepIndex + 1, 0, array( $newStep ) );
				$this->_backup_options->save();
				pb_backupbuddy::status( 'details', 'Inserted additional zip grow step at `' . ( $this->_currentStepIndex + 1 ) . '` to resume at index `' . $zip_response['zipper']['fp'] . '`. The next chunk will proceed shortly.' );

			} else { // Zip failure.

				// Failed to get a zip for some reason that the creation function will have logged
				// Nothing more to do but clean up and bail out

				// Delete temporary data directory.
				if ( file_exists( $this->_backup['temp_directory'] ) ) {
					pb_backupbuddy::status( 'details', __( 'Removing temp data directory.', 'it-l10n-backupbuddy' ) );
					pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temp_directory'] );
				}

				// Delete temporary ZIP directory.
				if ( file_exists( $this->_backup['temporary_zip_directory'] ) ) {
					pb_backupbuddy::status( 'details', __( 'Removing temp zip directory.', 'it-l10n-backupbuddy' ) );
					pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temporary_zip_directory'] );
				}

				pb_backupbuddy::status( 'error', __( 'Error #4001: Unable to successfully generate ZIP archive. Backup FAILED. See logs above for more information.', 'it-l10n-backupbuddy' ) );
				pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
				return false;

			} // end zip failure.
		}

		// Need to make sure the database connection is active. Sometimes it goes away during long bouts doing other things -- sigh.
		// This is not essential so use include and not require (suppress any warning)
		@include_once pb_backupbuddy::plugin_path() . '/lib/wpdbutils/wpdbutils.php';
		if ( class_exists( 'pluginbuddy_wpdbutils' ) ) {
			// This is the database object we want to use
			global $wpdb;

			// Get our helper object and let it use us to output status messages
			$dbhelper = new pluginbuddy_wpdbutils( $wpdb );

			// If we cannot kick the database into life then signal the error and return false which will stop the backup
			// Otherwise all is ok, and we can just fall through and let the function return true
			if ( ! $dbhelper->kick() ) {
				pb_backupbuddy::status( 'error', __( 'Backup FAILED. Backup file produced but Database Server has gone away, unable to schedule next backup step', 'it-l10n-backupbuddy' ) );
				return false;
			} else {
				// pb_backupbuddy::status( 'details', 'Database seems to still be connected.' );
			}
		} else {
			// Utils not available so cannot verify database connection status - just notify
			pb_backupbuddy::status( 'details', __( 'Database Server connection status unverified.', 'it-l10n-backupbuddy' ) );
		}

		pb_backupbuddy::status( 'milestone', 'end_files_alternate' );

		return $zip_response;

	}

	/**
	 * Get rid of excess archives based on user-defined parameters.
	 *
	 * !!!!! IMPORTANT: !!!!!
	 * The order of application of archives limiting is important.
	 * Users have configured their settings based on the defined order on the Settings page.
	 *
	 * @return bool  Always returns true, no matter what.
	 */
	public function trim_old_archives() {
		pb_backupbuddy::status( 'milestone', 'start_trim_archives' );
		pb_backupbuddy::status( 'details', __( 'Trimming old archives (if needed).', 'it-l10n-backupbuddy' ) );

		$summed_size = 0;
		$trim_count  = 0;
		$files       = array();
		$file_list   = glob( backupbuddy_core::getBackupDirectory() . 'backup*.zip' );

		if ( is_array( $file_list ) && ! empty( $file_list ) ) {
			foreach ( (array) $file_list as $file ) {
				$size      = filesize( $file );
				$timestamp = backupbuddy_core::parse_file( $file, 'timestamp' );

				while ( array_key_exists( $timestamp, $files ) ) {
					$timestamp++; // Increase by 1 second.
				}

				$files[ $timestamp ] = array(
					'filename' => basename( $file ),
					'size'     => $size,
					'modified' => $timestamp,
					'type'     => backupbuddy_core::parse_file( $file, 'type' ),
				);

				$summed_size = $size / 1048576; // Convert to MB.
			}
		}

		unset( $file_list );

		if ( empty( $files ) ) { // return if no archives (nothing else to do).
			pb_backupbuddy::status( 'details', __( 'No old archive trimming needed.', 'it-l10n-backupbuddy' ) );
			return true;
		}

		// Reverse sort by array key (timestamp).
		krsort( $files );

		// Limit by age if set.
		if ( (int) pb_backupbuddy::$options['archive_limit_age'] > 0 ) {
			foreach ( $files as $timestamp => $file ) {
				// Could not get age so skipping.
				if ( ! is_numeric( $file['modified'] ) ) {
					continue;
				}

				$backup_age = (int) ( time() - $file['modified'] ) / 60 / 60 / 24;

				// Not old enough, skip.
				if ( $backup_age <= pb_backupbuddy::$options['archive_limit_age'] ) {
					continue;
				}
				// translators: %1$s represents archive filename, %2$s represents archive limit age, %3$s represents backup age.
				pb_backupbuddy::status( 'details', sprintf( __( 'Deleting old archive `%1$s` as it exceeds the maximum age limit `%2$s` allowed at `%3$s` days.', 'it-l10n-backupbuddy' ), $file['filename'], pb_backupbuddy::$options['archive_limit_age'], $backup_age ) );

				if ( backupbuddy_backups()->delete( $file['filename'] ) ) {
					unset( $files[ $timestamp ] );
					$trim_count++;
				}
			}
		} // end age limit.

		$backup_types = array( 'full', 'db', 'files' );

		if ( ! empty( $this->_backup['archive_file'] ) ) {
			$backup_type = backupbuddy_core::parse_file( $this->_backup['archive_file'], 'type' );
			if ( $backup_type ) {
				$backup_types  = array( $backup_type );
				$archive_limit = ! empty( pb_backupbuddy::$options[ 'archive_limit_' . $backup_type ] ) ? pb_backupbuddy::$options[ 'archive_limit_' . $backup_type ] : 0;
			}
		}

		foreach ( $backup_types as $backup_type ) {
			$trim_count += $this->archive_limit_trim( $backup_type, $archive_limit, $files );
		}

		// Limit by number of archives if set. Deletes oldest archives over this limit.
		if ( pb_backupbuddy::$options['archive_limit'] > 0 && count( $files ) > pb_backupbuddy::$options['archive_limit'] ) {
			// Need to trim.
			$i = 0;
			foreach ( $files as $timestamp => $file ) {
				$i++;

				// Not yet reached the limit.
				if ( $i <= pb_backupbuddy::$options['archive_limit'] ) {
					continue;
				}

				if ( ! empty( $this->_backup['archive_file'] ) && basename( $this->_backup['archive_file'] ) === $file['filename'] ) {
					$message = __( 'ERROR #202005120954: Based on your backup archive limits (total limit) the backup that was just created would be deleted. Skipped deleting this backup. Please update your archive limits.' );
					pb_backupbuddy::status( 'message', $message );
					backupbuddy_core::mail_error( $message );
					continue;
				}

				// translators: %s represents archive filename.
				pb_backupbuddy::status( 'details', sprintf( __( 'Deleting old archive `%s` as it causes archives to exceed total number allowed.', 'it-l10n-backupbuddy' ), $file['filename'] ) );

				if ( backupbuddy_backups()->delete( $file['filename'] ) ) {
					unset( $files[ $timestamp ] );
					$trim_count++;
				}
			}
		} // end number of archives limit.

		// Limit by size of archives, oldest first if set.
		// Reversed so we delete oldest files first as long as size limit still is surpassed; true = preserve keys.
		$files = array_reverse( $files, true );

		if ( 0 == pb_backupbuddy::$options['archive_limit_size'] ) { // Limit of 0. Use BIG limit.
			$lesser_limit = pb_backupbuddy::$options['archive_limit_size_big'];
		} elseif ( 0 == pb_backupbuddy::$options['archive_limit_size_big'] ) { // Big is zero. Use normal limit.
			$lesser_limit = pb_backupbuddy::$options['archive_limit_size'];
		} else { // Big is set so decide which is smaller.
			$lesser_limit = min( pb_backupbuddy::$options['archive_limit_size'], pb_backupbuddy::$options['archive_limit_size_big'] );
		}

		// A limit was found and we exceed it.
		if ( $lesser_limit > 0 && $summed_size > $lesser_limit ) {
			// Need to trim.
			foreach ( $files as $timestamp => $file ) {
				// If summed size reaches lesser limit, we're done.
				if ( $summed_size <= $lesser_limit ) {
					break;
				}

				$summed_size -= $file['size'] / 1048576;
				// translators: %s represents archive filename.
				pb_backupbuddy::status( 'details', sprintf( __( 'Deleting old archive `%s` due as it causes archives to exceed total size allowed.', 'it-l10n-backupbuddy' ), $file['filename'] ) );

				// Delete excess archives as long as it is not the just-made backup.
				if ( ! empty( $this->_backup['archive_file'] ) && basename( $this->_backup['archive_file'] ) === $file['filename'] ) {
					$message = __( 'ERROR #9028: Based on your backup archive limits (size limit) the backup that was just created would be deleted. Skipped deleting this backup. Please update your archive limits.' );
					pb_backupbuddy::status( 'message', $message );
					backupbuddy_core::mail_error( $message );
				} else {
					if ( backupbuddy_backups()->delete( $file['filename'] ) ) {
						unset( $files[ $timestamp ] );
						$trim_count++;
					}
				}
			}
		} // end combined file size limit.

		// translators: %s represents number of archives trimmed.
		pb_backupbuddy::status( 'details', sprintf( __( 'Trimmed %s old archives based on settings archive limits.', 'it-l10n-backupbuddy' ), $trim_count ) );
		pb_backupbuddy::status( 'milestone', 'end_trim_archives' );
		return true;
	}

	/**
	 * Limit by number of archives by backup type.
	 *
	 * @param string $backup_type    Type of backup.
	 * @param int    $archive_limit  Archive limit.
	 * @param array  $files          Files array, passed by reference.
	 *
	 * @return int  Number of archives trimmed.
	 */
	public function archive_limit_trim( $backup_type, $archive_limit, &$files ) {
		$trim_count = 0;

		if ( $archive_limit <= 0 ) {
			return $trim_count;
		}

		// MAY need to trim.
		$i = 0;
		foreach ( $files as $timestamp => $file ) {
			// Only looking for specific backup type here.
			if ( $backup_type !== $file['type'] ) {
				continue;
			}

			$i++;

			// Not yet reached the limit.
			if ( $i <= $archive_limit ) {
				continue;
			}

			$limit_type = strtoupper( $backup_type );

			if ( ! empty( $this->_backup['archive_file'] ) && basename( $this->_backup['archive_file'] ) === $file['filename'] ) {
				// translators: %s represents limit type.
				$message = sprintf( __( 'ERROR #202005120953: Based on your backup archive limits (%s limit) the backup that was just created would be deleted. Skipped deleting this backup. Please update your archive limits.', 'it-l10n-backupbuddy' ), $limit_type );
				pb_backupbuddy::status( 'message', $message );
				backupbuddy_core::mail_error( $message );
				continue;
			}

			// translators: %1%s represents archive filename, %2$s represents limit type.
			pb_backupbuddy::status( 'details', sprintf( __( 'Deleting old archive `%1$s` as it causes archives to exceed total number of %2$s backups allowed.', 'it-l10n-backupbuddy' ), $file['filename'], $limit_type ) );

			if ( backupbuddy_backups()->delete( $file['filename'] ) ) {
				unset( $files[ $timestamp ] );
				$trim_count++;
			}
		} // end archives of type limit loop.

		return $trim_count;
	}

	/**
	 * Perform integrity check on backup file to confirm backup.
	 *
	 * @return bool  If integrity check is successful.
	 */
	public function integrity_check() {
		pb_backupbuddy::status( 'milestone', 'start_integrity' );
		pb_backupbuddy::status( 'message', __( 'Scanning and verifying backup file integrity.', 'it-l10n-backupbuddy' ) );
		if ( ( $this->_backup['profile']['type'] != 'files' ) && ( $this->_backup['profile']['skip_database_dump'] == '1' ) ) {
			pb_backupbuddy::status( 'warning', 'WARNING: Database .SQL file does NOT exist because the database dump has been set to be SKIPPED based on settings. Use with caution!' );
		}

		$options = array(
			'skip_database_dump' => $this->_backup['profile']['skip_database_dump'],
		);

		pb_backupbuddy::status( 'details', 'Starting integrity check on `' . $this->_backup['archive_file'] . '`.' );
		$result = backupbuddy_core::backup_integrity_check( $this->_backup['archive_file'], $this->_backup_options, $options, true );
		if ( false === $result['is_ok'] ) {
			$message = __( 'Backup failed to pass integrity check. The backup may have failed OR the backup may be valid but the integrity check could not verify it. This could be due to permissions, large file size, running out of memory, or other error. Verify you have not excluded one or more required files, paths, or database tables; check for warnings above in the status log.  You may wish to manually verify the backup file is functional or re-scan.', 'it-l10n-backupbuddy' );
			pb_backupbuddy::status( 'error', $message );

			pb_backupbuddy::status( 'details', 'Running cleanup procedure now in current step as backup procedure is halting.' );
			$this->post_backup( true ); // Post backup cleanup in fail mode.
			// pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.

			pb_backupbuddy::status( 'details', __( 'Sending integrity check failure email.', 'it-l10n-backupbuddy' ) );
			backupbuddy_core::mail_error( $message );

			return false;
		}

		pb_backupbuddy::status( 'milestone', 'finish_integrity' );
		return true;
	}

	/**
	 * Post-backup procedure. Clean up, send notifications, etc.
	 *
	 * @param bool $fail_mode      If post_backup should be run with fail mode.
	 * @param bool $cancel_backup  If backup was cancelled.
	 *
	 * @return bool  True if successful, false if cancelled.
	 */
	public function post_backup( $fail_mode = false, $cancel_backup = false ) {
		pb_backupbuddy::status( 'milestone', 'start_post_backup_procedures' );
		pb_backupbuddy::status( 'message', __( 'Cleaning up after backup.', 'it-l10n-backupbuddy' ) );

		// Delete temporary data directory.
		if ( file_exists( $this->_backup['temp_directory'] ) ) {
			pb_backupbuddy::status( 'details', __( 'Removing temp data directory.', 'it-l10n-backupbuddy' ) );
			pb_backupbuddy::$filesystem->unlink_recursive( $this->_backup['temp_directory'] );
		}
		// Delete temporary ZIP directory.
		if ( file_exists( backupbuddy_core::getBackupDirectory() . 'temp_zip_' . $this->_backup['serial'] . '/' ) ) {
			pb_backupbuddy::status( 'details', __( 'Removing temp zip directory.', 'it-l10n-backupbuddy' ) );
			pb_backupbuddy::$filesystem->unlink_recursive( backupbuddy_core::getBackupDirectory() . 'temp_zip_' . $this->_backup['serial'] . '/' );
		}

		if ( true === $fail_mode ) {
			pb_backupbuddy::status( 'warning', 'Backup archive limiting has been skipped since there was an error to avoid deleting potentially good backups to make room for a potentially bad backup.' );
		} else {
			$this->trim_old_archives(); // Clean up any old excess archives pushing us over defined limits in settings.
		}

		// Generate data file.
		$checksum = backupbuddy_data_file()->create( $this->_backup );
		if ( false !== $checksum ) {
			pb_backupbuddy::status( 'details', __( 'Backup data file created successfully.', 'it-l10n-backupbuddy' ) );
			$this->_backup['data_checksum'] = $checksum;
			$this->_backup_options->save();
		}

		if ( true === $cancel_backup ) {
			pb_backupbuddy::status( 'details', 'Backup stopped so deleting backup ZIP file.' );
			if ( true === backupbuddy_backups()->delete( basename( $this->_backup['archive_file'] ) ) ) {
				pb_backupbuddy::status( 'details', 'Deleted stopped backup file.' );
			} else {
				pb_backupbuddy::status( 'error', 'Unable to delete stopped backup file. You should delete it manually as it may be damaged from stopping mid-backup. File to delete: `' . $this->_backup['archive_file'] . '`.' );
			}

			$this->_backup['finish_time'] = -1;
			// pb_backupbuddy::save();
			$this->_backup_options->save();

		} else { // Not cancelled.
			$this->_backup['archive_size'] = @filesize( $this->_backup['archive_file'] );
			pb_backupbuddy::status( 'details', __( 'Final ZIP file size', 'it-l10n-backupbuddy' ) . ': ' . pb_backupbuddy::$format->file_size( $this->_backup['archive_size'] ) );
			pb_backupbuddy::status( 'archiveSize', pb_backupbuddy::$format->file_size( $this->_backup['archive_size'] ) );

			if ( false === $fail_mode ) { // Not cancelled and did not fail so mark finish time.

				$archiveFile = basename( $this->_backup_options->options['archive_file'] );

				// Calculate backup download URL, if any.
				// $downloadURL = pb_backupbuddy::ajax_url( 'download_archive' ) . '&backupbuddy_backup=' . $archiveFile;
				$downloadURL = '';
				$abspath     = str_replace( '\\', '/', ABSPATH ); // Change slashes to handle Windows as we store backup_directory with Linux-style slashes even on Windows.
				$backup_dir  = str_replace( '\\', '/', backupbuddy_core::getBackupDirectory() );
				if ( false !== stristr( $backup_dir, $abspath ) ) { // Make sure file to download is in a publicly accessible location (beneath WP web root technically).
					// pb_backupbuddy::status( 'details', 'mydir: `' . esc_attr( $backup_dir ) . '`, abs: `' . $abspath . '`.');
					$sitepath    = str_replace( $abspath, '', $backup_dir );
					$downloadURL = rtrim( site_url(), '/\\' ) . '/' . trim( $sitepath, '/\\' ) . '/' . $archiveFile;
				}

				$integrityIsOK = '-1';
				if ( isset( $this->_backup_options->options['integrity']['is_ok'] ) ) {
					$integrityIsOK = $this->_backup_options->options['integrity']['is_ok'];
				}

				$destinations = array();
				foreach ( $this->_backup_options->options['steps'] as $step ) {
					if ( 'send_remote_destination' === $step['function'] ) {
						$destinations[] = array(
							'id'    => $step['args'][0],
							'title' => pb_backupbuddy::$options['remote_destinations'][ $step['args'][0] ]['title'],
							'type'  => pb_backupbuddy::$options['remote_destinations'][ $step['args'][0] ]['type'],
						);
					}
				}

				pb_backupbuddy::status( 'details', 'Updating statistics for last backup completed and number of edits since last backup.' );

				$finishTime = microtime( true );

				pb_backupbuddy::$options['last_backup_finish'] = $finishTime;
				pb_backupbuddy::$options['last_backup_stats']  = array(
					// 'serial'          => $this->_backup['serial'],
					'archiveFile'     => $archiveFile,
					'archiveURL'      => $downloadURL,
					'archiveSize'     => $this->_backup['archive_size'],
					'start'           => pb_backupbuddy::$options['last_backup_start'],
					'finish'          => $finishTime,
					'type'            => $this->_backup_options->options['profile']['type'],
					'profileTitle'    => htmlentities( $this->_backup_options->options['profile']['title'] ),
					'scheduleTitle'   => $this->_backup_options->options['schedule_title'], // Empty string is no schedule.
					'integrityStatus' => $integrityIsOK, // 1, 0, -1 (unknown)
					'destinations'    => $destinations, // Index is destination ID. Empty array if none.
				);

				pb_backupbuddy::$options['edits_since_last'] = array( // Reset edit stats for notifying user of how many recent edits since last backup happened.
					'all'    => 0,
					'post'   => 0,
					'plugin' => 0,
					'option' => 0,
				);
				pb_backupbuddy::$options['recent_edits']     = array(); // Reset recent edits.
				pb_backupbuddy::save();
			}
		}

		require_once pb_backupbuddy::plugin_path() . '/classes/housekeeping.php';
		backupbuddy_housekeeping::cleanup_temp_dir();

		if ( $this->_backup['trigger'] == 'manual' ) {
			// Do nothing. No notifications as of pre-3.0 2012.
		} elseif ( $this->_backup['trigger'] == 'deployment' ) {
			// Do nothing. No notifications.
		} elseif ( $this->_backup['trigger'] == 'deployment_pulling' ) {
			// Do nothing.
		} elseif ( $this->_backup['trigger'] == 'scheduled' ) {
			if ( ( false === $fail_mode ) && ( false === $cancel_backup ) ) {
				pb_backupbuddy::status( 'details', __( 'Sending scheduled backup complete email notification.', 'it-l10n-backupbuddy' ) );
				$message = 'completed successfully in ' . pb_backupbuddy::$format->time_duration( time() - $this->_backup['start_time'] ) . ".\n";
				backupbuddy_core::mail_notify_scheduled( $this->_backup['serial'], 'complete', __( 'Scheduled backup', 'it-l10n-backupbuddy' ) . ' "' . $this->_backup['schedule_title'] . '" ' . $message );
			}
		} else {
			pb_backupbuddy::status( 'warning', 'Warning #4343434. Unknown backup trigger `' . $this->_backup['trigger'] . '`. This may be okay if triggered via an external source such as Solid Central.' );
		}

		pb_backupbuddy::status( 'message', __( 'Finished cleaning up.', 'it-l10n-backupbuddy' ) );

		if ( true === $cancel_backup ) {
			pb_backupbuddy::status( 'details', 'Backup cancellation complete.' );
			return false;
		} else {
			if ( true === $fail_mode ) {
				pb_backupbuddy::status( 'details', __( 'As this backup did not pass the integrity check you should verify it manually or re-scan. Integrity checks can fail on good backups due to permissions, large file size exceeding memory limits, etc. You may manually disable integrity check on the Settings page but you will no longer be notified of potentially bad backups.', 'it-l10n-backupbuddy' ) );
			} else {
				if ( ( $this->_backup['trigger'] != 'deployment' ) && ( $this->_backup['trigger'] != 'deployment_pulling' ) ) {
					// $stats = stat( $this->_backup['archive_file'] );
					// $sizeFormatted = pb_backupbuddy::$format->file_size( $stats['size'] );
					pb_backupbuddy::status(
						'archiveInfo',
						json_encode(
							array(
								'file' => basename( $this->_backup['archive_file'] ),
								'url'  => pb_backupbuddy::ajax_url( 'download_archive' ) . '&backupbuddy_backup=' . basename( $this->_backup['archive_file'] ),
							// 'sizeBytes' => $stats,
							// 'sizeFormatted' => $sizeFormatted,
							)
						)
					);
				}
			}
		}

		// Clear Backups Cache.
		backupbuddy_backups()->clear_cache();
		pb_backupbuddy::status( 'milestone', 'end_post_backup_procedures' );
		return true;
	}

	/**
	 * Send the current backup to a remote destination such as S3, Dropbox, FTP, etc.
	 * Scheduled remote sends end up coming through here before passing to core.
	 *
	 * @param int  $destination_id  Destination ID (remote destination array index) to send to.
	 * @param bool $delete_after    Whether to delete backup file after THIS successful remote transfer.
	 */
	public function send_remote_destination( $destination_id, $delete_after = false ) {
		pb_backupbuddy::status( 'details', 'Sending file to remote destination ID: `' . $destination_id . '`. Delete after: `' . $delete_after . '`.' );
		pb_backupbuddy::status( 'details', 'IMPORTANT: If the transfer is set to be chunked then only the first chunk status will be displayed during this process. Subsequent chunks will happen after this has finished.' );
		$response = backupbuddy_core::send_remote_destination( $destination_id, $this->_backup['archive_file'], '', false, $delete_after );

		if ( false === $response ) { // Send failure.
			$error_message = 'Solid Backups failed sending a backup to the remote destination "' . pb_backupbuddy::$options['remote_destinations'][ $destination_id ]['title'] . '" (id: ' . $destination_id . '). Please verify and test destination settings and permissions. Check the error log for further details.';
			pb_backupbuddy::status( 'error', 'Failure sending to remote destination. Details: ' . $error_message );
			backupbuddy_core::mail_error( $error_message );
		}
	}

	/**
	 * Deletes backup archive. Used to delete the backup after sending to a remote destination for scheduled backups.
	 *
	 * @deprecated Mar 5, 2013. - Dustin
	 *
	 * @return bool  True on deletion success; else false.
	 */
	private function post_remote_delete() {
		// DEPRECATED FUNCTION. DO NOT USE.
		pb_backupbuddy::status( 'error', 'CALL TO DEPRECATED FUNCTION post_remote_delete().' );
		pb_backupbuddy::status( 'details', 'Deleting local copy of file sent remote.' );
		if ( file_exists( $this->_backup['archive_file'] ) ) {
			backupbuddy_backups()->delete( basename( $this->_backup['archive_file'] ) );
		}

		if ( file_exists( $this->_backup['archive_file'] ) ) {
			pb_backupbuddy::status( 'details', __( 'Error. Unable to delete local archive as requested.', 'it-l10n-backupbuddy' ) );
			return false; // Didnt delete.
		}
		pb_backupbuddy::status( 'details', __( 'Deleted local archive as requested.', 'it-l10n-backupbuddy' ) );
		return true; // Deleted.
	}

	/**
	 * Determine if backup is of a certain type.
	 *
	 * @param string $type  Type to check against.
	 *
	 * @return bool  If backup is of type.
	 */
	public function is_backup_type( $type ) {
		return  ( $type === $this->_backup['type'] );
	}

	/**
	 * Determine if backup is a `full` backup type.
	 *
	 * @return bool  If backup is a `full` backup type.
	 */
	public function is_full_backup() {
		return $this->is_backup_type( 'full' );
	}


	/**
	 * Determine if backup is a `files` backup type.
	 *
	 * @return bool  If backup is a `files` backup type.
	 */
	public function is_files_backup() {
		return $this->is_backup_type( 'files' );
	}

	/**
	 * Determine if backup is a `db` backup type.
	 *
	 * @return bool  If backup is a `db` backup type.
	 */
	public function is_db_backup() {
		return $this->is_backup_type( 'db' );
	}

	/**
	 * Determine if backup is a `media` backup type.
	 *
	 * @return bool  If backup is a `media` backup type.
	 */
	public function is_media_backup() {
		return $this->is_backup_type( 'media' );
	}

	/**
	 * Determine if backup is a `themes` backup type.
	 *
	 * @return bool  If backup is a `themes` backup type.
	 */
	public function is_themes_backup() {
		return $this->is_backup_type( 'themes' );
	}

	/**
	 * Determine if backup is a `plugins` backup type.
	 *
	 * @return bool  If backup is a `plugins` backup type.
	 */
	public function is_plugins_backup() {
		return $this->is_backup_type( 'plugins' );
	}

	/**
	 * Determine if backup is a `export` backup type.
	 *
	 * @return bool  If backup is a `export` backup type.
	 */
	public function is_export_backup() {
		return $this->is_backup_type( 'export' );
	}

	/**
	 * Determine if backup is a file-based backup type.
	 *
	 * @return bool  If backup is not a file-based backup type.
	 */
	public function is_fileish_backup() {
		return ( $this->is_files_backup() || $this->is_media_backup() || $this->is_themes_backup() || $this->is_plugins_backup() );
	}

	/**
	 * Send file with Deploy Push.
	 *
	 * @param [type] $state [description]
	 * @param [type] $sendFile  May be an array of files BUT they must all be in the same $sendPath.
	 * @param [type] $sendPath [description]
	 * @param [type] $sendType [description]
	 * @param [type] $nextStep [description]
	 * @param bool   $delete_after  If file should be deleted afterward.
	 *
	 * @return bool  Always returns true;
	 */
	public function deploy_push_sendFile( $state, $sendFile, $sendPath, $sendType, $nextStep, $delete_after = false ) {
		$destination_settings = pb_backupbuddy_destinations::get_normalized_settings( $state['destination_id'] );

		$destination_settings['sendType']     = $sendType;
		$destination_settings['sendFilePath'] = $sendPath;
		$destination_settings['max_time']     = $state['minimumExecutionTime'];

		$identifier = $this->_backup['serial'] . '_' . md5( serialize( $sendFile ) . $sendType );
		if ( false === backupbuddy_core::send_remote_destination( $state['destination_id'], $sendFile, 'Deployment', false, $delete_after, $identifier, $destination_settings ) ) {
			$sendFile = ''; // Since failed just set file to blank, so we can proceed to next without waiting.
		}

		$this->deploy_sendWait( $state, $sendFile, $sendPath, $sendType, $nextStep );
		return true;
	}

	/**
	 * [deploy_sendWait description]
	 *
	 * @param [type] $state [description]
	 * @param [type] $sendFile [description]
	 * @param [type] $sendPath [description]
	 * @param [type] $sendType [description]
	 * @param [type] $nextStep [description]
	 *
	 * @return bool [description]
	 */
	public function deploy_sendWait( $state, $sendFile, $sendPath, $sendType, $nextStep ) {
		$identifier = $this->_backup['serial'] . '_' . md5( serialize( $sendFile ) . $sendType );
		if ( is_array( $sendFile ) ) {
			pb_backupbuddy::status( 'details', 'Waiting for send to finish for multiple files pass with ID `' . $identifier . '`.' );
		} else {
			pb_backupbuddy::status( 'details', 'Waiting for send to finish for file `' . $sendFile . '` with ID `' . $identifier . '`.' );
		}

		$maxSendTime = 60 * 5;

		if ( '' == $sendFile ) { // File failed. Proceed to next.
			$this->insert_next_step( $nextStep );
			return true;
		}

		pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #38...' );
		require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
		$fileoptions_obj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $identifier . '.txt', $read_only = false, $ignore_lock = true, $create_file = false );
		if ( true !== ( $result = $fileoptions_obj->is_ok() ) ) {
			pb_backupbuddy::status( 'error', __( 'Fatal Error #9034 E. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
			pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
			return false;
		}
		pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );
		$fileoptions = &$fileoptions_obj->options; // Set reference.
		if ( '0' == $fileoptions['finish_time'] ) { // Not finished yet. Insert next chunk to wait.
			$timeAgo = ( time() - $fileoptions['start_time'] );
			if ( $timeAgo > $maxSendTime ) {
				pb_backupbuddy::status( 'error', 'Error #4948348: Maximum allowed file send time of `' . $maxSendTime . '` seconds passed. Halting.' );
				pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
			}
			pb_backupbuddy::status( 'details', 'File send not yet finished. Started `' . $timeAgo . '` seconds ago. Inserting wait.' );

			$newStep = array(
				'function'    => 'deploy_sendWait',
				'args'        => array( $state, $sendFile, $sendPath, $sendType, $nextStep ),
				'start_time'  => 0,
				'finish_time' => 0,
				'attempts'    => 0,
			);
			$this->insert_next_step( $newStep );
		} else { // Finished. Go to next step.
			if ( is_array( $sendFile ) && count( $sendFile ) > 0 ) {
				pb_backupbuddy::status( 'details', 'Send finished for multiple file (' . count( $sendFile ) . ') pass.' );
			} else {
				pb_backupbuddy::status( 'details', 'Send finished: `' . basename( $sendFile ) . '`.' );
			}
			$this->insert_next_step( $nextStep );
		}

		return true;
	}

	/**
	 * Start a deployment of this site to a remote site.
	 *
	 * @param array $state State array.
	 *
	 * @return bool  Always returns true.
	 */
	public function deploy_push_start( $state ) {
		pb_backupbuddy::status( 'details', 'Starting PUSH deployment process. Incoming state: `' . print_r( $state, true ) . '`.' );
		pb_backupbuddy::status(
			'startSubFunction',
			json_encode(
				array(
					'function' => 'deploy_push_start',
					'title'    => 'Found deployment.',
				)
			)
		);

		// Send backup zip. It will schedule its next chunks as needed. When done it calls the nextstep.
		$nextStep = array(
			'function'    => 'deploy_push_sendContent',
			'args'        => array( $state ),
			'start_time'  => 0,
			'finish_time' => 0,
			'attempts'    => 0,
		);
		$this->deploy_push_sendFile( $state, $this->_backup['archive_file'], '', 'backup', $nextStep, $delete_after = true );

		return true;
	}

	/**
	 * Start a deployment of this site to a remote site.
	 *
	 * @param array $state State array.
	 *
	 * @return bool
	 */
	public function deploy_pull_start( $state ) {
		pb_backupbuddy::status( 'details', 'Starting PULL deployment process. Incoming state: `' . print_r( $state, true ) . '`.' );
		pb_backupbuddy::status(
			'startSubFunction',
			json_encode(
				array(
					'function' => 'deploy_pull_start',
					'title'    => 'Found deployment.',
				)
			)
		);

		require_once pb_backupbuddy::plugin_path() . '/classes/deploy.php';
		if ( empty( $state['destinationSettings'] ) ) {
			$state['destinationSettings'] = array();
		}
		$deploy = new backupbuddy_deploy( $state['destinationSettings'], $state );

		// If not pulling any DB contents then skip making remote backup file.
		if ( '1' == $this->_backup['profile']['skip_database_dump'] ) {
			pb_backupbuddy::status( 'details', 'No database tables selected for pulling.  Skipping remote database snapshot (backup) zip creation and inserting file pull step.' );
			$newStep = array(
				'function'    => 'deploy_pull_files',
				'args'        => array( $state ),
				'start_time'  => 0,
				'finish_time' => 0,
				'attempts'    => 0,
			);
			$this->insert_next_step( $newStep );
			return true;
		} else {
			pb_backupbuddy::status( 'details', 'Database tables selected for pulling. Proceeding towards remote snapshot.' );
		}

		// Get session token to restore, so we won't be logged out. Place them in the remote backup profile array.
		global $wpdb;
		$sql = 'SELECT meta_value FROM `' . DB_NAME . '`.`' . $wpdb->prefix . "usermeta` WHERE `user_id` = '" . $this->_backup['runnerUID'] . "' AND `meta_key` = 'session_tokens'";
		pb_backupbuddy::status( 'details', 'TokenSQL: ' . $sql );
		$results                                   = $wpdb->get_var( $sql );
		$this->_backup['profile']['sessionTokens'] = unserialize( $results );
		$this->_backup['profile']['sessionID']     = $this->_backup['runnerUID'];
		pb_backupbuddy::status( 'details', 'Session tokens calculated.' );

		pb_backupbuddy::status( 'details', 'About to remote call.' );
		if ( false === ( $response = backupbuddy_remote_api::remoteCall( $state['destination'], 'runBackup', array( 'profile' => base64_encode( json_encode( $this->_backup['profile'] ) ) ), $state['minimumExecutionTime'] ) ) ) {
			pb_backupbuddy::status( 'error', 'Error #44548985: Unable to start remote backup via remote API.' );
			return false;
		}
		pb_backupbuddy::status( 'details', 'Server response: `' . print_r( $response, true ) . '`.' );
		$remoteBackupSerial                                   = $response['backupSerial'];
		$remoteBackupFile                                     = $response['backupFile'];
		$this->_backup_options->options['remote_backup_file'] = $remoteBackupFile;
		pb_backupbuddy::status( 'details', 'Remote backup file: `' . $this->_backup_options->options['remote_backup_file'] . '`.' );
		$this->_backup_options->options['deployment_log'] = $remoteBackupSerial; // _getBackupStatus.php uses this serial for remote call retrieval of the status log during the backup.
		pb_backupbuddy::status( 'details', 'Remote backup log: `' . $this->_backup_options->options['deployment_log'] . '`.' );
		$this->_backup_options->save();

		pb_backupbuddy::status( 'details', 'Inserting deploy step to wait for backup to finish on remote server.' );
		$newStep = array(
			'function'    => 'deploy_pull_runningBackup',
			'args'        => array( $state ),
			'start_time'  => 0,
			'finish_time' => 0,
			'attempts'    => 0,
		);
		$this->insert_next_step( $newStep );

		return true;
	}

	/**
	 * Deploy pull files
	 *
	 * @param array $state State array.
	 * @param string $pullBackupArchive  Backup archive file to pull from remote site.
	 *
	 * @return bool  Always returns true.
	 */
	public function deploy_pull_files( $state, $pullBackupArchive = '' ) {
		pb_backupbuddy::status( 'details', 'Starting retrieval of files from source remote site.' );

		// Next step will be back here after any send file waiting finishes. Keep coming back here until there is nothing more to send.
		$nextStep = array(
			'function'    => 'deploy_pull_files',
			'args'        => array(), // MUST populate state before passing off this next step.
			'start_time'  => 0,
			'finish_time' => 0,
			'attempts'    => 0,
		);

		// Count up number of files remaining.
		$mediaFileCount      = 0;
		$pluginFileCount     = 0;
		$themeFileCount      = 0;
		$childThemeFileCount = 0;
		$extraFileCount      = 0;
		if ( true === $state['sendMedia'] ) {
			$mediaFileCount = count( $state['pullMediaFiles'] );
		}
		if ( is_array( $state['sendPlugins'] ) && count( $state['sendPlugins'] ) > 0 ) {
			$pluginFileCount = count( $state['pullPluginFiles'] );
		}
		if ( true === $state['sendTheme'] ) {
			$themeFileCount = count( $state['pullThemeFiles'] );
		}
		if ( true === $state['sendChildTheme'] ) {
			$childThemeFileCount = count( $state['pullChildThemeFiles'] );
		}

		$filesRemaining = $mediaFileCount + $pluginFileCount + $themeFileCount + $childThemeFileCount + $extraFileCount;
		if ( '' != $pullBackupArchive ) { // add in backup archive if not yet sent.
			$filesRemaining++;
		}
		pb_backupbuddy::status( 'deployFilesRemaining', $filesRemaining );
		pb_backupbuddy::status( 'details', 'Files remaining to retrieve: ' . $filesRemaining );

		if ( '' != $pullBackupArchive ) {
			$nextStep['args']              = array( $state );
			$state['pullLocalArchiveFile'] = ABSPATH . $pullBackupArchive;
			$nextStep['args']              = array( $state );
			return $this->deploy_getFile( $state, dirname( $state['pullLocalArchiveFile'] ) . '/', $pullBackupArchive, 'backup', $nextStep );
		}

		if ( true !== $state['sendMedia'] ) {
			pb_backupbuddy::status( 'details', 'SKIPPING pull of media files.' );
		} else {
			if ( $mediaFileCount > 0 ) { // Media files remain to send.
				$getFile          = array_pop( $state['pullMediaFiles'] ); // Pop off last item in array. Faster than shift.
				$wp_upload_dir    = wp_upload_dir();
				$nextStep['args'] = array( $state );
				pb_backupbuddy::status( 'details', 'About to send media file.' );
				return $this->deploy_getFile( $state, $wp_upload_dir['basedir'] . '/', $getFile, 'media', $nextStep );
			}
		}

		if ( 0 == count( $state['sendPlugins'] ) ) {
			pb_backupbuddy::status( 'details', 'No plugin files selected for transfer. Skipping send.' );
		} else {
			if ( $pluginFileCount > 0 ) { // Plugin files remain to send.
				pb_backupbuddy::status( 'details', 'Plugins files remaining to send: ' . count( $state['pullPluginFiles'] ) );
				$getFile          = array_pop( $state['pullPluginFiles'] ); // Pop off last item in array. Faster than shift.
				$pluginPath       = wp_normalize_path( WP_PLUGIN_DIR ) . '/';
				$nextStep['args'] = array( $state );
				return $this->deploy_getFile( $state, $pluginPath, $getFile, 'plugin', $nextStep );
			}
		}

		if ( true !== $state['sendTheme'] ) {
			pb_backupbuddy::status( 'details', 'SKIPPING pull of theme files.' );
		} else {
			if ( $themeFileCount > 0 ) { // Plugin files remain to send.
				$getFile          = array_pop( $state['pullThemeFiles'] ); // Pop off last item in array. Faster than shift.
				$themePath        = get_template_directory(); // contains trailing slash.
				$nextStep['args'] = array( $state );
				return $this->deploy_getFile( $state, $themePath, $getFile, 'theme', $nextStep );
			}
		}

		if ( true !== $state['sendChildTheme'] ) {
			pb_backupbuddy::status( 'details', 'SKIPPING pull of child theme files.' );
		} else {
			if ( $childThemeFileCount > 0 ) { // Plugin files remain to send.
				$getFile          = array_pop( $state['pullChildThemeFiles'] ); // Pop off last item in array. Faster than shift.
				$childThemePath   = get_stylesheet_directory(); // contains trailing slash.
				$nextStep['args'] = array( $state );
				return $this->deploy_getFile( $state, $childThemePath, $getFile, 'childTheme', $nextStep );
			}
		}

		if ( 0 == count( $state['sendExtras'] ) ) {
			pb_backupbuddy::status( 'details', 'No extra files selected for transfer. Skipping send.' );
		} else {
			if ( $extraFileCount > 0 ) { // Plugin files remain to send.
				pb_backupbuddy::status( 'details', 'Extra files remaining to send: ' . count( $state['pullExtraFiles'] ) );
				$getFile          = array_pop( $state['pullExtraFiles'] ); // Pop off last item in array. Faster than shift.
				$nextStep['args'] = array( $state );
				return $this->deploy_getFile( $state, ABSPATH, $getFile, 'extra', $nextStep );
			}
		}

		// If we made it here then all file sending is finished. Move on to next step.
		$nextStep = array(
			'function'    => 'deploy_pull_renderImportBuddy',
			'args'        => array( $state ),
			'start_time'  => 0,
			'finish_time' => 0,
			'attempts'    => 0,
		);
		$this->insert_next_step( $nextStep );

		return true;
	}

	/**
	 * Supports chunked retrieval.
	 *
	 * @param string  $destinationPath  Path to store file in locally. Include trailing slash.
	 *
	 * @return bool
	 */
	public function deploy_getFile( $state, $destinationPath, $sendFile, $sendType, $finalNextStep, $seekTo = 0 ) {
		$TIME_WIGGLE_ROOM = 5; // seconds of wiggle room around sending.
		$numSentThisRound = 0;

		require_once pb_backupbuddy::plugin_path() . '/classes/remote_api.php';

		$timeStart   = microtime( true );
		$maxPayload  = $state['destinationSettings']['max_payload']; // in MB. convert to bytes.
		$saveFile    = $destinationPath . $sendFile;
		$keepSending = true;

		while ( true == $keepSending ) {
			if ( false === ( $response = backupbuddy_remote_api::remoteCall(
				$state['destination'],
				'getFile_' . $sendType,
				array(
					'filename'   => $sendFile,
					'seekto'     => $seekTo,
					'maxPayload' => $maxPayload,
				),
				$state['minimumExecutionTime']
			) ) ) {
				pb_backupbuddy::status( 'error', 'Error #2373273: Unable to initiate file get via remote API.' );
				return false;
			}

			if ( ! is_array( $response ) ) {
				pb_backupbuddy::status( 'error', 'Error #38937324: Expected return array. Response: `' . htmlentities( $response ) . '`.' );
				return false;
			}

			/*
			 * $response now contains an array from the server.
			 * data         string  Binary file contents.
			 * datalen      int     Length of the filedata param.
			 * done         bool    True if this is the last of the file.
			 * size         int     Total file size in bytes.
			 * resumepoint  int     Tell resume point to pass to next fseek. 0 if done = true.
			 * encoded      bool    True if filename needs to be utf8_decoded
			 *
			 * each files => array(
			 *   'file'    => '',
			 *   'data'    => '',
			 *   'datalen' => 0,
			 *   'size'    => '',
			 *   'done'    => false,
			 *   'test'    => false,
			 *   'encoded' => false,
			 * )
			 */

			$numSentThisRound++;

			if ( ( 0 == $seekTo ) && ( is_file( $saveFile ) ) ) { // First or only part so delete if already exists & is a file (not directory &; not a resumed chunk).
				if ( true !== unlink( $saveFile ) ) {
					$message = 'Error #83832983: Unable to deleting existing local file to overwrite. Check permissions on file/directory `' . $saveFile . '`.';
					pb_backupbuddy::status( 'error', $message );
					return false;
				}
			}

			// Make sure containing directory exists.
			$saveDir = dirname( $saveFile );
			if ( ! is_dir( $saveDir ) ) {
				if ( true !== pb_backupbuddy::$filesystem->mkdir( $saveDir ) ) {
					$message = 'Error #327832: Unable to create directory `' . $saveDir . '`. Check permissions or manually create. Halting to preserve deployment integrity';
					pb_backupbuddy::status( 'error', $message );
					return false;
				}
			}

			// Open/create file for write/append.
			if ( false === ( $fs = fopen( $saveFile, 'a' ) ) ) {
				$message = 'Error #43834984: Unable to fopen file `' . $sendFile . '` in directory `' . $$destinationPath . '`.';
				pb_backupbuddy::status( 'error', $message );
				return false;
			}

			// Seek to specific location. fseeking rather than direct append in case there's some sort of race condition and this gets out of order in some way. Best to be specific.
			if ( 0 != fseek( $fs, $seekTo ) ) {
				@fclose( $fs );
				$message = 'Error #2373792: Unable to fseek file.';
				pb_backupbuddy::status( 'error', $message );
				return false;
			}

			// Write to file.
			if ( false === ( $bytesWritten = fwrite( $fs, $response['data'] ) ) ) { // Failed writing.
				@fclose( $fs );
				@unlink( $saveFile );
				$message = 'Error #4873474: Error writing to file `' . $saveFile . '`.';
				pb_backupbuddy::status( 'error', $message );
				return false;
			} else { // Success writing.
				@fclose( $fs );

				$message = 'Wrote `' . $bytesWritten . '` bytes to `' . $saveFile . '`.';
				pb_backupbuddy::status( 'details', $message );
			}

			$elapsed = microtime( true ) - $timeStart;

			// Handle finishing up or chunking if needed.
			if ( '1' == $response['done'] ) { // Transfer finished.
				pb_backupbuddy::status( 'deployFileSent', 'File sent.' );
				pb_backupbuddy::status( 'details', 'Retrieval of complete file + writing took `' . $elapsed . '` secs.' );
				$keepSending = false;

				$this->insert_next_step( $finalNextStep );
				return true;
			} else { // More chunks remain.
				pb_backupbuddy::status( 'details', 'Retrieval of chunk + writing took `' . $elapsed . '` seconds. Wrote `' . pb_backupbuddy::$format->file_size( $bytesWritten ) . '` of max limit of `' . $maxPayload . '` MB per chunk after seeking to `' . $seekTo . '`. Encoded size received: `' . strlen( $response['data'] ) . '` bytes.' );
				$seekTo = $response['resumepoint']; // Next chunk fseek to this point (got by ftell).

				if ( ( ( $elapsed + $TIME_WIGGLE_ROOM ) * ( $numSentThisRound + 1 ) ) >= $state['minimumExecutionTime'] ) { // Could we have time to send another piece based on average send time?
					pb_backupbuddy::status( 'details', 'Not enough time to request more data this pass. Chunking.' );
					$keepSending = false;

					$nextStep = array(
						'function'    => 'deploy_getFile',
						'args'        => array( $state, $destinationPath, $sendFile, $sendType, $finalNextStep, $seekTo ),
						'start_time'  => 0,
						'finish_time' => 0,
						'attempts'    => 0,
					);

					$this->insert_next_step( $nextStep );
					return true;
				} else {
					pb_backupbuddy::status( 'details', 'There appears to be enough time to get more data this pass.' );
					$keepSending = true;
				}
			}
		} // end while( true == $keepSending ).

	}

	/**
	 * [deploy_pull_renderImportBuddy description]
	 *
	 * @param [type] $state [description]
	 *
	 * @return [type] [description]
	 */
	public function deploy_pull_renderImportBuddy( $state ) {

		if ( '' == $state['pullLocalArchiveFile'] ) {
			pb_backupbuddy::status( 'details', 'Skipping rendering of Importer step because there is no archive file. This is usually due to selecting no database tables to be pulled, therefore no import/migration needed.' );
			pb_backupbuddy::status( 'deployFinished', 'Finished.' );
			return true;
		}

		if ( ! file_exists( $state['pullLocalArchiveFile'] ) ) {
			pb_backupbuddy::status( 'error', 'Error #32783732: Backup file `' . $state['pullLocalArchiveFile'] . '` not found.' );
			return false;
		}
		$backupSerial        = backupbuddy_core::get_serial_from_file( $state['pullLocalArchiveFile'] );
		$importbuddyPassword = md5( md5( $state['destination']['key_public'] ) );
		$siteurl             = site_url();

		$additionalStateInfo = array(
			'maxExecutionTime' => $state['minimumExecutionTime'],
			'doImportCleanup'  => $state['doImportCleanup'],
			'cleanup'          => array(
				'set_blog_public' => $state['setBlogPublic'],
			),
		);

		$importFileSerial = backupbuddy_core::deploymentImportBuddy( $importbuddyPassword, $state['pullLocalArchiveFile'], $additionalStateInfo, $state['doImportCleanup'] );
		if ( is_array( $importFileSerial ) ) { // Could not generate importbuddy file.
			return false;
		}

		// Store this serial in settings to clean up any temp db tables in the future with this serial with periodic cleanup.
		pb_backupbuddy::$options['rollback_cleanups'][ $backupSerial ] = time();
		pb_backupbuddy::save();

		// Create undo file.
		$undoFile = 'backupbuddy_deploy_undo-' . $backupSerial . '.php';
		if ( false === copy( pb_backupbuddy::plugin_path() . '/classes/_rollback_undo.php', ABSPATH . $undoFile ) ) {
			$error = 'Error #3289447: Unable to write undo file `' . ABSPATH . $undoFile . '`. Check permissions on directory.';
			pb_backupbuddy::status( 'error', $error );
			return false;
		}

		// Start pulling importbuddy log.
		$importbuddyURLRoot = $siteurl . '/importbuddy-' . $importFileSerial . '.php';
		$importbuddyLogURL  = $importbuddyURLRoot . '?ajax=getDeployLog&v=' . $importbuddyPassword . '&deploy=true'; // $state['destination']['siteurl'] . '/importbuddy/'?ajax=2&v=' . $importbuddyPassword . '&deploy=true; //status-' . $response['importFileSerial'] . '.txt';
		$importbuddyURL     = $importbuddyURLRoot . '?ajax=2&v=' . $importbuddyPassword . '&deploy=true&direction=pull&file=' . basename( $state['pullLocalArchiveFile'] );
		pb_backupbuddy::status( 'details', 'Load importbuddy at `' . $importbuddyURLRoot . '` with verifier `' . $importbuddyPassword . '`.' );
		pb_backupbuddy::status(
			'loadImportBuddy',
			json_encode(
				array(
					'url'    => $importbuddyURL,
					'logurl' => $importbuddyLogURL,
				)
			)
		);

		// Calculate undo URL.
		$undoDeployURL = $siteurl . '/backupbuddy_deploy_undo-' . $this->_backup['serial'] . '.php';
		pb_backupbuddy::status( 'details', 'To undo deployment of database contents go to the URL: ' . $undoDeployURL );
		pb_backupbuddy::status( 'undoDeployURL', $undoDeployURL );

		// Pull importbuddy log instead of remote backup log. Nothing else is going to be done on remote server.
		$this->_backup_options->options['deployment_log'] = $importbuddyLogURL;
		$this->_backup_options->save();

		// Next step.
		pb_backupbuddy::status( 'details', 'Inserting deploy step to run importbuddy steps on remote server.' );
		$newStep = array(
			'function'    => 'deploy_runningImportBuddy',
			'args'        => array( $state ),
			'start_time'  => 0,
			'finish_time' => 0,
			'attempts'    => 0,
		);
		$this->insert_next_step( $newStep );

		return true;
	}

	/**
	 * Wait for remote backup to complete.
	 *
	 * Log will be pulled in via AJAX waiting for completion.
	 * AJAX will need to tell us once it looks finished, so we may proceed.
	 *
	 * @param array $state State array.
	 *
	 * @return bool  Always returns true.
	 */
	public function deploy_pull_runningBackup( $state ) {

		if ( $this->_backup_options->options['deployment_log'] == get_transient( 'backupbuddy_deployPullBackup_finished' ) ) {
			pb_backupbuddy::status( 'details', 'Remote destination backup completed.' );

			$this->_backup_options->options['deployment_log'] = ''; // Clear out deployment log so we will not keep hitting the remote server for the backup log, eg while retreiving remote files.
			$this->_backup_options->save();

			pb_backupbuddy::status( 'details', 'Inserting deploy step to retrieve files including remote backup archive.' );
			$newStep = array(
				'function'    => 'deploy_pull_files',
				'args'        => array( $state, $pullBackupArchive = $this->_backup_options->options['remote_backup_file'] ),
				'start_time'  => 0,
				'finish_time' => 0,
				'attempts'    => 0,
			);
			$this->insert_next_step( $newStep );

			delete_transient( 'backupbuddy_deployPullBackup_finished' );
			return true;
		}

		pb_backupbuddy::status( 'details', 'Inserting deploy step to wait for backup to finish on remote server.' );
		$newStep = array(
			'function'    => 'deploy_pull_runningBackup',
			'args'        => array( $state ),
			'start_time'  => 0,
			'finish_time' => 0,
			'attempts'    => 0,
		);
		$this->insert_next_step( $newStep );

		return true;
	}

	/**
	 * [deploy_push_sendContent description]
	 *
	 * Used PUSHING.
	 *
	 * @param array $state State array.
	 *
	 * @return bool
	 */
	public function deploy_push_sendContent( $state ) {
		// Next step will be back here after any send file waiting finishes. Keep coming back here until there is nothing more to send.
		$nextStep = array(
			'function'    => 'deploy_push_sendContent',
			'args'        => array(), // MUST populate state before passing off this next step.
			'start_time'  => 0,
			'finish_time' => 0,
			'attempts'    => 0,
		);

		// Count up files to send.
		$mediaFileCount      = 0;
		$pluginFileCount     = 0;
		$themeFileCount      = 0;
		$childThemeFileCount = 0;
		$extraFileCount      = 0;
		if ( true === $state['sendMedia'] ) {
			$mediaFileCount = count( $state['pushMediaFiles'] );
		}
		if ( count( $state['sendPlugins'] ) > 0 ) {
			$pluginFileCount = count( $state['pushPluginFiles'] );
		}
		if ( true === $state['sendTheme'] ) {
			$themeFileCount = count( $state['pushThemeFiles'] );
		}
		if ( true === $state['sendChildTheme'] ) {
			$childThemeFileCount = count( $state['pushChildThemeFiles'] );
		}
		if ( true === $state['sendExtras'] ) {
			$extraFileCount = count( $state['pushExtraFiles'] );
		}
		$filesRemaining = $mediaFileCount + $pluginFileCount + $themeFileCount + $childThemeFileCount + $extraFileCount;
		pb_backupbuddy::status( 'deployFilesRemaining', $filesRemaining );
		pb_backupbuddy::status( 'details', 'Files remaining to send: ' . $filesRemaining );

		if ( true !== $state['sendMedia'] ) {
			pb_backupbuddy::status( 'details', 'SKIPPING push of media files.' );
		} else {
			if ( $mediaFileCount > 0 ) { // Media files remain to send.
				$wp_upload_dir = wp_upload_dir();

				return $this->deploy_queue_push_files( $state, 'pushMediaFiles', $wp_upload_dir['basedir'], 'media', $nextStep );
			}
		}

		if ( 0 == count( $state['sendPlugins'] ) ) {
			pb_backupbuddy::status( 'details', 'No plugin files selected for transfer. Skipping send.' );
		} else {
			if ( $pluginFileCount > 0 ) { // Plugin files remain to send.
				pb_backupbuddy::status( 'details', 'Plugins files remaining to send: ' . count( $state['pushPluginFiles'] ) );

				$pluginPath = wp_normalize_path( WP_PLUGIN_DIR ) . '/';

				return $this->deploy_queue_push_files( $state, 'pushPluginFiles', $pluginPath, 'plugin', $nextStep );
			}
		}

		if ( true !== $state['sendTheme'] ) {
			pb_backupbuddy::status( 'details', 'SKIPPING push of theme files.' );
		} else {
			if ( $themeFileCount > 0 ) { // Plugin files remain to send.
				$themePath = get_template_directory(); // contains trailing slash.

				return $this->deploy_queue_push_files( $state, 'pushThemeFiles', $themePath, 'theme', $nextStep );
			}
		}

		if ( true !== $state['sendChildTheme'] ) {
			pb_backupbuddy::status( 'details', 'SKIPPING push of child theme files.' );
		} else {
			if ( $childThemeFileCount > 0 ) { // Plugin files remain to send.
				$childThemePath = get_stylesheet_directory(); // contains trailing slash.

				return $this->deploy_queue_push_files( $state, 'pushChildThemeFiles', $childThemePath, 'childTheme', $nextStep );
			}
		}

		if ( ! is_array( $state['sendExtras'] ) || 0 == count( $state['sendExtras'] ) ) {
			pb_backupbuddy::status( 'details', 'No extra files selected for transfer. Skipping send.' );
		} else {
			if ( $extraFileCount > 0 ) { // Plugin files remain to send.
				pb_backupbuddy::status( 'details', 'Extra files remaining to send: ' . count( $state['pushExtraFiles'] ) );

				return $this->deploy_queue_push_files( $state, 'pushExtraFiles', ABSPATH, 'extra', $nextStep );
			}
		}

		// If we made it here then all file sending is finished. Move on to next step.
		$nextStep = array(
			'function'    => 'deploy_push_renderImportBuddy',
			'args'        => array( $state ),
			'start_time'  => 0,
			'finish_time' => 0,
			'attempts'    => 0,
		);
		$this->insert_next_step( $nextStep );

		return true;
	}

	/**
	 * [deploy_queue_push_files description]
	 *
	 * @param array  $state        State array.
	 * @param string $stateSendKey State array key for files to send.
	 * @param string $path         Path to prepend to files.
	 * @param string $type         Type of files being sent.
	 * @param array  $nextStep     Next step to insert after sending files.
	 *
	 * @return bool
	 */
	public function deploy_queue_push_files( $state, $stateSendKey, $path, $type, $nextStep ) {
		$sendFile = array_pop( $state[ $stateSendKey ] ); // Pop off last item in array. Faster than shift.

		pb_backupbuddy::status( 'details', 'Queue file (A): `' . $path . $sendFile . '`.' );
		$files        = array( $path . $sendFile ); // First file.
		$filesSize    = @filesize( $path . $sendFile );
		$relativePath = str_replace( $path, '', dirname( $sendFile ) );

		$destination_settings = pb_backupbuddy_destinations::get_normalized_settings( $state['destination_id'] );

		// See if we want to send multiple files in this single pass.
		$findNext   = true;
		$maxPayload = $destination_settings['max_payload']; // Max payload size in mb.

		// If remote site's max upload is less than defined upload use it. Ignore if remote reports 0 upload or negative.
		if ( ( (int) $state['remoteInfo']['php']['upload_max_filesize'] > 0 ) && ( (int) $state['remoteInfo']['php']['upload_max_filesize'] < $maxPayload ) ) {
			$maxPayload = (int) $state['remoteInfo']['php']['upload_max_filesize'];
			pb_backupbuddy::status( 'details', 'Decreased max payload/chunk size to `' . $maxPayload . '` MB because remote server reported lesser limit than destination is configured for (`' . $destination_settings['max_payload'] . '` MB).' );
		}

		$maxPayload = ( $maxPayload - $maxPayload * .05 ) * 1024 * 1024; // Reduce by 5% overhead. Convert to bytes.
		while ( true === $findNext ) {
			if ( count( $files ) >= $destination_settings['max_files_per_pass'] ) { // Limit number of files per payload.
				pb_backupbuddy::status( 'details', 'Max number of files per pass met at `' . count( $files ) . '` files.' );
				break;
			}
			if ( false === $upcomingFile = end( $state[ $stateSendKey ] ) ) { // Peek end of array. Break if at end.
				pb_backupbuddy::status( 'details', 'No more of files of this type for this pass.' );
				$findNext = false;
				break;
			}
			if ( dirname( $upcomingFile ) != dirname( $sendFile ) ) { // Break if next file not in the exact same directory. We require same path per api call.
				pb_backupbuddy::status( 'details', 'Next file in different directory `' . dirname( $upcomingFile ) . '` from current: `' . dirname( $sendFile ) . '`.' );
				$findNext = false;
				break;
			}
			$upcomingFileSize = @filesize( $upcomingFile );
			if ( ( $filesSize + $upcomingFileSize ) > $maxPayload ) { // Too big to fit upcoming next file. Both vars in bytes. Break if too big.
				pb_backupbuddy::status( 'details', 'Approaching size limit for this send pass.' );
				$findNext = false;
				break;
			}
			// Made it here so pop file and add file into queue.
			$filesSize += $upcomingFileSize;
			$addFile    = $path . array_pop( $state[ $stateSendKey ] );
			$files[]    = $addFile;
			pb_backupbuddy::status( 'details', 'Queue file (B): `' . $addFile . '`.' );
		}

		$nextStep['args'] = array( $state );

		pb_backupbuddy::status( 'details', 'Queued `' . count( $files ) . '` files. Total: `' . $filesSize . '` bytes (' . pb_backupbuddy::$format->file_size( $filesSize ) . ') to send this pass.' );
		return $this->deploy_push_sendFile( $state, $files, $relativePath, $type, $nextStep );
	}

	/**
	 * [deploy_push_renderImportBuddy description]
	 *
	 * @param [type] $state [description]
	 *
	 * @return [type] [description]
	 */
	public function deploy_push_renderImportBuddy( $state ) {

		$timeout = 10;
		pb_backupbuddy::status( 'details', 'Tell remote server to render importbuddy & place our settings file.' );

		require_once pb_backupbuddy::plugin_path() . '/classes/deploy.php';
		$deploy = new backupbuddy_deploy( $state['destinationSettings'], $state );

		if ( true === $state['doImportCleanup'] ) {
			$cleanupStringBool = 'true';
		} else {
			$cleanupStringBool = 'false';
		}

		$payloadSettings = array(
			'backupFile'         => basename( $this->_backup['archive_file'] ),
			'max_execution_time' => $state['minimumExecutionTime'],
			'doImportCleanup'    => $cleanupStringBool,
			'setBlogPublic'      => $state['setBlogPublic'],
		);
		pb_backupbuddy::status( 'details', 'Remote call to render importbuddy with payload settings: `' . print_r( $payloadSettings, true ) . '`.' );

		if ( false === ( $response = backupbuddy_remote_api::remoteCall(
			$state['destination'],
			'renderImportBuddy',
			$payloadSettings,
			$state['minimumExecutionTime']
		) ) ) {
			pb_backupbuddy::status( 'error', 'Error #4448985: Unable to render importbuddy via remote API.' );
			return false;

		}
		pb_backupbuddy::status( 'details', 'Render importbuddy result: `' . print_r( $response, true ) . '`.' );

		// Calculate importbuddy URL.
		$importbuddyPassword = md5( md5( $state['destination']['key_public'] ) ); // Double md5 like a rainbow.
		$importbuddyURLRoot  = $state['destination']['siteurl'] . '/importbuddy-' . $response['importFileSerial'] . '.php';
		$importbuddyLogURL   = $importbuddyURLRoot . '?ajax=getDeployLog&v=' . $importbuddyPassword . '&deploy=true'; // $state['destination']['siteurl'] . '/importbuddy/'?ajax=2&v=' . $importbuddyPassword . '&deploy=true; //status-' . $response['importFileSerial'] . '.txt';
		$importbuddyURL      = $importbuddyURLRoot . '?ajax=2&v=' . $importbuddyPassword . '&deploy=true&direction=push&file=' . basename( $this->_backup['archive_file'] );
		pb_backupbuddy::status( 'details', 'Load importbuddy at `' . $importbuddyURLRoot . '` with verifier `' . $importbuddyPassword . '`.' );
		pb_backupbuddy::status(
			'loadImportBuddy',
			json_encode(
				array(
					'url'    => $importbuddyURL,
					'logurl' => $importbuddyLogURL,
				)
			)
		);

		// Calculate undo URL.
		$undoDeployURL = $state['destination']['siteurl'] . '/backupbuddy_deploy_undo-' . $this->_backup['serial'] . '.php';
		pb_backupbuddy::status( 'details', 'To undo deployment of database contents go to the URL: ' . $undoDeployURL );
		pb_backupbuddy::status( 'undoDeployURL', $undoDeployURL );

		$this->_backup_options->options['deployment_log'] = $importbuddyLogURL;
		$this->_backup_options->save();

		pb_backupbuddy::status( 'details', 'Inserting deploy step to run importbuddy steps on remote server.' );
		$newStep = array(
			'function'    => 'deploy_runningImportBuddy',
			'args'        => array( $state ),
			'start_time'  => 0,
			'finish_time' => 0,
			'attempts'    => 0,
		);
		$this->insert_next_step( $newStep );

		return true;

	}

	/**
	 * [deploy_runningImportBuddy description]
	 *
	 * @param [type] $state [description]
	 *
	 * @return [type] [description]
	 */
	public function deploy_runningImportBuddy( $state ) {

		$maxImportBuddyWaitTime = 60 * 60 * 48; // 48 hrs.

		// Safety net just in case a loop forms.
		if ( ( time() - $state['startTime'] ) > $maxImportBuddyWaitTime ) {
			pb_backupbuddy::status( 'error', 'Error #8349484: Fatal error. Importer is taking too long to complete. Aborting deployment.' );
			return false;
		}

		pb_backupbuddy::status( 'details', 'Inserting deploy step to run importbuddy steps on remote server.' );
		$newStep = array(
			'function'    => 'deploy_runningImportBuddy',
			'args'        => array( $state ),
			'start_time'  => 0,
			'finish_time' => 0,
			'attempts'    => 0,
		);
		$this->insert_next_step( $newStep );
		sleep( 1 ); // Sleep to insure at least a minimum pause between running importbuddy steps.
		return true;

	}


	/**
	 * Inserts a step to run next and saves it to fileoptions.
	 *
	 * $nextStep = step array.
	 */
	private function insert_next_step( $newStep ) {
		array_splice( $this->_backup_options->options['steps'], $this->_currentStepIndex + 1, 0, array( $newStep ) );
		$this->_backup_options->save();
	}

	/********* BEGIN MULTISITE (Exporting subsite; creates a standalone backup) *********/

	/**
	 * Used by Multisite Exporting.
	 * Downloads and extracts the latest WordPress for making a standalone backup of a subsite.
	 * Authored by Ron H. Modified by Dustin B.
	 *
	 * @return bool True on success, else false.
	 */
	public function ms_download_extract_wordpress() {

		// Step 1 - Download a copy of WordPress.
		if ( ! function_exists( 'download_url' ) ) {
			pb_backupbuddy::status( 'details', 'download_url() function not available by default. Loading `/wp-admin/includes/file.php`.' );
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$wp_url = 'http://wordpress.org/latest.zip';
		pb_backupbuddy::status( 'details', 'Downloading latest WordPress ZIP file from `' . $wp_url . '`.' );
		$wp_file = download_url( $wp_url );
		if ( is_wp_error( $wp_file ) ) { // Grabbing WordPress ZIP failed.
			pb_backupbuddy::status( 'error', 'Error getting latest WordPress ZIP file: `' . $wp_file->get_error_message() . '`.' );
			return false;
		} else { // Grabbing WordPress ZIP succeeded.
			pb_backupbuddy::status( 'details', 'Latest WordPress ZIP file successfully downloaded to `' . $wp_file . '`.' );
		}

		// Step 2 - Extract WP into a separate directory.
		if ( ! isset( pb_backupbuddy::$classes['zipbuddy'] ) ) {
			pb_backupbuddy::$classes['zipbuddy'] = new pluginbuddy_zipbuddy( backupbuddy_core::getBackupDirectory() );
		}
		pb_backupbuddy::status( 'details', 'About to unzip file `' . $wp_file . '` into `' . $this->_backup['backup_root'] . '`.' );
		ob_start();
		pb_backupbuddy::$classes['zipbuddy']->unzip( $wp_file, dirname( $this->_backup['backup_root'] ) );
		pb_backupbuddy::status( 'details', 'Unzip complete.' );
		pb_backupbuddy::status( 'details', 'Debugging information: `' . ob_get_clean() . '`' );

		@unlink( $wp_file );
		if ( file_exists( $wp_file ) ) { // Check to see if unlink() worked.
			pb_backupbuddy::status( 'warning', 'Unable to delete temporary WordPress file `' . $wp_file . '`. You may want to delete this after the backup / export completed.' );
		}

		return true;

	}

	/**
	 *
	 * Used by Multisite Exporting.
	 * Creates a standalone wp-config.php file for making a standalone backup from a subsite.
	 * Authored by Ron H. Modified by Dustin B.
	 *
	 * @return  bool  Currently only returns true.
	 */
	public function ms_create_wp_config() {

		pb_backupbuddy::status( 'message', 'Creating new wp-config.php file for temporary WordPress installation.' );

		global $current_blog;
		$blog_id = absint( $current_blog->blog_id );

		// Step 3 - Create new WP-Config File
		$to_file  = "<?php\n";
		$to_file .= sprintf( "define( 'DB_NAME', '%s' );\n", '' );
		$to_file .= sprintf( "define( 'DB_USER', '%s' );\n", '' );
		$to_file .= sprintf( "define( 'DB_PASSWORD', '%s' );\n", '' );
		$to_file .= sprintf( "define( 'DB_HOST', '%s' );\n", '' );
		$charset  = defined( 'DB_CHARSET' ) ? DB_CHARSET : '';
		$collate  = defined( 'DB_COLLATE' ) ? DB_COLLATE : '';
		$to_file .= sprintf( "define( 'DB_CHARSET', '%s' );\n", $charset );
		$to_file .= sprintf( "define( 'DB_COLLATE', '%s' );\n", $collate );

		// Attempt to remotely retrieve salts
		$salts = wp_remote_get( 'https://api.wordpress.org/secret-key/1.1/salt/' );
		if ( ! is_wp_error( $salts ) ) { // Success.
			$to_file .= wp_remote_retrieve_body( $salts ) . "\n";
		} else { // Failed.
			pb_backupbuddy::status( 'warning', 'Error getting salts from WordPress.org for wp-config.php. You may need to manually edit your wp-config on restore. Error: `' . $salts->get_error_message() . '`.' );
		}
		$to_file .= sprintf( "define( 'WPLANG', '%s' );\n", WPLANG );
		$to_file .= sprintf( '$table_prefix = \'%s\';' . "\n", 'bbms' . $blog_id . '_' );

		$to_file .= "if ( !defined('ABSPATH') ) { \n\tdefine('ABSPATH', dirname(__FILE__) . '/'); }";
		$to_file .= "/** Sets up WordPress vars and included files. */\n
		require_once(ABSPATH . 'wp-settings.php');";
		$to_file .= "\n?>";

		// Create the file, save, and close
		$configFile  = $this->_backup['backup_root'] . 'wp-config.php';
		$file_handle = fopen( $configFile, 'w' );
		fwrite( $file_handle, $to_file );
		fclose( $file_handle );

		pb_backupbuddy::status( 'message', 'Temporary WordPress wp-config.php file created at `' . $configFile . '`.' );

		return true;
	}

	/**
	 * Used by Multisite Exporting.
	 * Copies over the selected plugins for inclusion into the backup for creating a standalone backup from a subsite.
	 * Authored by Ron H. Modified by Dustin B.
	 *
	 * @return  bool  True on success, else false.
	 */
	public function ms_copy_plugins() {

		pb_backupbuddy::status( 'message', 'Copying selected plugins into temporary WordPress installation.' );

		// Step 4 - Copy over plugins.
		// Move over plugins.
		$plugin_items = $this->_backup['export_plugins'];

		// Get plugins for site.
		$site_plugins = get_option( 'active_plugins' );
		if ( ! empty( $site_plugins ) ) {
			$plugin_items['site'] = $site_plugins;
		}

		// Populate $items_to_copy for all plugins to copy over
		if ( is_array( $plugin_items ) ) {
			$items_to_copy = array();
			// Get content directories by using this plugin as a base
			$content_dir    = $dropin_plugins_dir = dirname( dirname( dirname( rtrim( plugin_dir_path( __FILE__ ), '/' ) ) ) );
			$mu_plugins_dir = $content_dir . '/mu-plugins';
			$plugins_dir    = $content_dir . '/plugins';

			// Get the special plugins (mu, dropins, network activated)
			foreach ( $plugin_items as $type => $plugins ) {
				foreach ( $plugins as $plugin ) {
					if ( $type == 'mu' ) {
						$items_to_copy[ $plugin ] = $mu_plugins_dir . '/' . $plugin;
					} elseif ( $type == 'dropin' ) {
						$items_to_copy[ $plugin ] = $dropin_plugins_dir . '/' . $plugin;
					} elseif ( $type == 'network' || $type == 'site' ) {
						// Determine if we're a folder-based plugin, or a file-based plugin (such as hello.php)
						$plugin_path = dirname( $plugins_dir . '/' . $plugin );
						if ( basename( $plugin_path ) == 'plugins' ) {
							$plugin_path = $plugins_dir . '/' . $plugin;
						}
						$items_to_copy[ basename( $plugin_path ) ] = $plugin_path;
					}
				} //end foreach $plugins.
			} //end foreach special plugins.

			// Copy the files over
			$wp_dir = '';
			if ( count( $items_to_copy ) > 0 ) {
				$wp_dir        = $this->_backup['backup_root'];
				$wp_plugin_dir = $wp_dir . 'wp-content/plugins/';
				foreach ( $items_to_copy as $file => $original_destination ) {
					if ( file_exists( $original_destination ) && file_exists( $wp_plugin_dir ) ) {
						// $this->copy( $original_destination, $wp_plugin_dir . $file );
						$result = pb_backupbuddy::$filesystem->recursive_copy( $original_destination, $wp_plugin_dir . $file );

						if ( $result === false ) {
							pb_backupbuddy::status( 'error', 'Unable to copy plugin from `' . $original_destination . '` to `' . $wp_plugin_dir . $file . '`. Verify permissions.' );
							return false;
						} else {
							pb_backupbuddy::status( 'details', 'Copied plugin from `' . $original_destination . '` to `' . $wp_plugin_dir . $file . '`.' );
						}
					}
				}
			}

			// Finished

			pb_backupbuddy::status( 'message', 'Copied selected plugins into temporary WordPress installation.' );
			return true;

		} else {
			// Nothing has technically failed at this point - There just aren't any plugins to copy over.

			pb_backupbuddy::status( 'message', 'No plugins were selected for backup. Skipping plugin copying.' );
			return true;
		}

		return true; // Shouldn't get here.

	}

	/**
	 * Used by Multisite Exporting.
	 * Copies over the selected themes for inclusion into the backup for creating a standalone backup from a subsite.
	 * Authored by Ron H. Modified by Dustin B.
	 *
	 * @return bool  True on success, else false.
	 */
	public function ms_copy_themes() {

		pb_backupbuddy::status( 'message', 'Copying theme(s) into temporary WordPress installation.' );

		if ( ! function_exists( 'wp_get_theme' ) ) {
			pb_backupbuddy::status( 'details', 'wp_get_theme() function not found. Loading `/wp-admin/includes/theme.php`.' );
			require_once ABSPATH . 'wp-admin/includes/theme.php';
			pb_backupbuddy::status( 'details', 'Loaded `/wp-admin/includes/theme.php`.' );
		}

		// Use new wp_get_theme() if available.
		if ( function_exists( 'wp_get_theme' ) ) { // WordPress v3.4 or newer.
			pb_backupbuddy::status( 'details', 'wp_get_theme() available. Using it.' );
			$current_theme = wp_get_theme();
		} else { // WordPress pre-v3.4
			pb_backupbuddy::status( 'details', 'wp_get_theme() still unavailable (pre WordPress v3.4?). Attempting to use older current_theme_info() fallback.' );
			$current_theme = current_theme_info();
		}

		// Step 5 - Copy over themes
		$template_dir   = $current_theme->template_dir;
		$stylesheet_dir = $current_theme->stylesheet_dir;

		pb_backupbuddy::status( 'details', 'Got current theme information.' );

		// If $template_dir and $stylesheet_dir don't match, that means we have a child theme and need to copy over the parent also
		$items_to_copy                              = array();
		$items_to_copy[ basename( $template_dir ) ] = $template_dir;
		if ( $template_dir != $stylesheet_dir ) {
			$items_to_copy[ basename( $stylesheet_dir ) ] = $stylesheet_dir;
		}

		pb_backupbuddy::status( 'details', 'About to begin copying theme files...' );

		// Copy the files over
		if ( count( $items_to_copy ) > 0 ) {
			$wp_dir       = $this->_backup['backup_root'];
			$wp_theme_dir = $wp_dir . 'wp-content/themes/';
			foreach ( $items_to_copy as $file => $original_destination ) {
				if ( file_exists( $original_destination ) && file_exists( $wp_theme_dir ) ) {

					$result = pb_backupbuddy::$filesystem->recursive_copy( $original_destination, $wp_theme_dir . $file );

					if ( $result === false ) {
						pb_backupbuddy::status( 'error', 'Unable to copy theme from `' . $original_destination . '` to `' . $wp_theme_dir . $file . '`. Verify permissions.' );
						return false;
					} else {
						pb_backupbuddy::status( 'details', 'Copied theme from `' . $original_destination . '` to `' . $wp_theme_dir . $file . '`.' );
					}
				} // end if file exists.
			} // end foreach $items_to_copy.
		} // end if.

		pb_backupbuddy::status( 'message', 'Copied theme into temporary WordPress installation.' );
		return true;

	}

	/**
	 * Used by Multisite Exporting.
	 * Copies over media (wp-content/uploads) for this site for inclusion into the backup for creating a standalone backup from a subsite.
	 * Authored by Ron H. Modified by Dustin B.
	 *
	 * @return bool  True on success, else false.
	 */
	public function ms_copy_media() {

		pb_backupbuddy::status( 'message', 'Copying media into temporary WordPress installation.' );

		// Step 6 - Copy over media/upload files
		$upload_dir                  = wp_upload_dir();
		$original_upload_base_dir    = $upload_dir['basedir'];
		$destination_upload_base_dir = $this->_backup['backup_root'] . 'wp-content/uploads';
		// $result = pb_backupbuddy::$filesystem->custom_copy( $original_upload_base_dir, $destination_upload_base_dir, array( 'ignore_files' => array( $this->_backup['serial'] ) ) );

		// Grab directory upload contents, so we can exclude backupbuddy directories.
		$upload_contents = glob( $original_upload_base_dir . '/*' );
		if ( ! is_array( $upload_contents ) ) {
			$upload_contents = array();
		}

		foreach ( $upload_contents as $upload_content ) {
			if ( strpos( $upload_content, 'backupbuddy_' ) === false ) { // Don't copy over any backupbuddy-prefixed uploads directories.
				$result = pb_backupbuddy::$filesystem->recursive_copy( $upload_content, $destination_upload_base_dir . '/' . basename( $upload_content ) );
			}
		}

		if ( $result === false ) {
			pb_backupbuddy::status( 'error', 'Unable to copy media from `' . $original_upload_base_dir . '` to `' . $destination_upload_base_dir . '`. Verify permissions.' );
			return false;
		} else {
			pb_backupbuddy::status( 'details', 'Copied media from `' . $original_upload_base_dir . '` to `' . $destination_upload_base_dir . '`.' );
			return true;
		}

	}

	/**
	 * Step 7
	 * Used by Multisite Exporting.
	 * Copies over users to a temp table for this site for inclusion into the backup for creating a standalone backup from a subsite.
	 * Authored by Ron H. Modified by Dustin B.
	 *
	 * @return bool  Currently only returns true.
	 */
	public function ms_copy_users_table() {

		pb_backupbuddy::status( 'message', 'Copying temporary users table for users in this blog.' );

		global $wpdb, $current_blog;

		$new_user_tablename     = $wpdb->prefix . 'users';
		$new_usermeta_tablename = $wpdb->prefix . 'usermeta';

		if ( $new_user_tablename == $wpdb->users ) {
			pb_backupbuddy::status( 'message', 'Temporary users table would match existing users table. Skipping creation of this temporary users & usermeta tables.' );
			return true;
		}

		// Copy over users table to temporary table.
		pb_backupbuddy::status( 'message', 'Created new table `' . $new_user_tablename . '` like `' . $wpdb->users . '`.' );
		$wpdb->query( "CREATE TABLE `{$new_user_tablename}` LIKE `{$wpdb->users}`" );
		$wpdb->query( "INSERT `{$new_user_tablename}` SELECT * FROM `{$wpdb->users}`" );

		// Copy over usermeta table to temporary table.
		pb_backupbuddy::status( 'message', 'Created new table `' . $new_usermeta_tablename . '` like `' . $wpdb->usermeta . '`.' );
		$wpdb->query( "CREATE TABLE `{$new_usermeta_tablename}` LIKE `{$wpdb->usermeta}`" );
		$wpdb->query( "INSERT `{$new_usermeta_tablename}` SELECT * FROM `{$wpdb->usermeta}`" );

		// Get list of users associated with this site.
		$users_to_capture = array();
		$user_args        = array(
			'blog_id' => $current_blog->blog_id,
		);
		$users            = get_users( $user_args );
		if ( $users ) {
			foreach ( $users as $user ) {
				array_push( $users_to_capture, $user->ID );
			}
		}
		$users_to_capture = implode( ',', $users_to_capture );
		pb_backupbuddy::status( 'details', 'User IDs to capture (' . count( $users_to_capture ) . ' total): ' . print_r( $users_to_capture, true ) );

		// Remove users from temporary table that aren't associated with this site.
		$wpdb->query( "DELETE from `{$new_user_tablename}` WHERE ID NOT IN( {$users_to_capture} )" );
		$wpdb->query( "DELETE from `{$new_usermeta_tablename}` WHERE user_id NOT IN( {$users_to_capture} )" );

		pb_backupbuddy::status( 'message', 'Copied temporary users table for users in this blog.' );
		return true;

	}

	/**
	 * Cleanup after Multisize Exporting.
	 */
	public function ms_cleanup() {
		pb_backupbuddy::status( 'details', 'Beginning Multisite-export specific cleanup.' );

		global $wpdb;
		$new_user_tablename     = $wpdb->prefix . 'users';
		$new_usermeta_tablename = $wpdb->prefix . 'usermeta';

		if ( ( $new_user_tablename == $wpdb->users ) || ( $new_usermeta_tablename == $wpdb->usermeta ) ) {
			pb_backupbuddy::status( 'error', 'Unable to clean up temporary user tables as they match main tables. Skipping to prevent data loss.' );
			return;
		}

		pb_backupbuddy::status( 'details', 'Dropping temporary table `' . $new_user_tablename . '`.' );
		$wpdb->query( "DROP TABLE `{$new_user_tablename}`" );
		pb_backupbuddy::status( 'details', 'Dropping temporary table `' . $new_usermeta_tablename . '`.' );
		$wpdb->query( "DROP TABLE `{$new_usermeta_tablename}`" );

		pb_backupbuddy::status( 'details', 'Done Multisite-export specific cleanup.' );
	}

	/********* END MULTISITE */
}
