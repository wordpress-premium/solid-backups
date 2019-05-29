<?php
/**
 * Performs and stores the results of an integrity check.
 *
 * @since 8.2.3.0
 * @package BackupBuddy
 */

/**
 * Integrity Check Main Class
 */
class backupbuddy_integrity_check {

	/**
	 * Full Path to the zip being checked
	 *
	 * @var string
	 */
	protected $file = false;

	/**
	 * Options set when this specific zip was created
	 *
	 * - When called directly after initizal backup process, this comes from backup options loaded in memory
	 * - When called on old backup zip (like in list backups or manual rescan), this is populated from backupbuddy_temp/fileoptions/[backup_serial].txt
	 *
	 * @var object
	 */
	protected $backup_options = '';

	/**
	 * Array of additional options to help integrity check.
	 *
	 * @var array
	 */
	protected $additional_options = '';

	/**
	 * Skip Log Redirect
	 *
	 * @var bool
	 */
	protected $skip_log_redirect = false;

	/**
	 * The backup's serial
	 *
	 * @var string
	 */
	protected $serial = false;

	/**
	 * Stored for when redirecting logs
	 *
	 * @var string
	 */
	protected $previous_status_serial = '';

	/**
	 * Is this a rescan?
	 *
	 * @var boolean
	 */
	protected $is_rescan = false;

	/**
	 * The type of backup
	 *
	 * @var string
	 */
	protected $backup_type = false;

	/**
	 * How did we determine the backup type?
	 *
	 * @var string
	 */
	protected $backup_typed_by = false;

	/**
	 * Scan Notes array
	 *
	 * @var array
	 */
	protected $scan_notes = array();

	/**
	 * Scan Log array
	 *
	 * @var array
	 */
	protected $scan_log = array();

	/**
	 * Holds all the data we'll add to the integrity test and return to the backup process
	 *
	 * @var array
	 */
	public $integrity_array = array();

	/**
	 * Holds the zip commnet
	 *
	 * @var string
	 */
	protected $comment = '';

	/**
	 * The tests we're going to run for this specific backup
	 *
	 * @var array
	 */
	protected $tests = array();

	/**
	 * Construct.
	 *
	 * @
	 */
	/**
	 * Constructor: Loads up initial parameters.
	 *
	 * @param string $file                Path to backup file.
	 * @param string $backup_options      Array of Options.
	 * @param array  $additional_options  Array of more options.
	 * @param bool   $skip_log_redirect   Skip log redirect.
	 */
	public function __construct( $file, $backup_options = '', $additional_options = array(), $skip_log_redirect = false ) {
		// Set incoming properties.
		$this->file               = $file;
		$this->backup_options     = $backup_options;
		$this->additional_options = $additional_options;
		$this->skip_log_redirect  = $skip_log_redirect;

		// Set calculated properties.
		$this->set_serial();
		$this->set_is_rescan();
		$this->determine_backup_type();
		$this->set_integrity_array();

		// Parse Data for tests.
		// Bail if we have cached test results or not enough data to run test.
		$result = $this->determine_backup_options();
		if ( false === $result ) {
			return $this->integrity_array;
		}

		// Bail if integrity check is disabled for this backup.
		$integrity_disabled = $this->bail_if_disabled();
		if ( false !== $integrity_disabled ) {
			return $integrity_disabled;
		}

		// Run the tests.
		$this->require_zipbuddy();
		$this->maybe_redirect_logging();
		$this->pre_test_events();
		$this->run_tests();
		$this->set_scan_log();
		$this->maybe_stop_log_redirect();
		$this->calculate_test_results();
	}

	/**
	 * Sets the serial value for the backup we're checking
	 */
	public function set_serial() {
		$this->serial = backupbuddy_core::get_serial_from_file( $this->file );
		pb_backupbuddy::status( 'details', 'Started backup_integrity_check() function for `' . $this->serial . '` for file `' . $this->file . '`.' );
	}

	/**
	 * Sets the rescan property
	 */
	public function set_is_rescan() {
		if ( pb_backupbuddy::_GET( 'reset_integrity' ) == $this->serial ) {
			$this->is_rescan = true;
			pb_backupbuddy::alert( 'Rescanning backup integrity for backup file `' . basename( $this->file ) . '`' );
			pb_backupbuddy::flush();
		}
	}

	/**
	 * Determine's the Backup type
	 *
	 * Preference is given to filename. Falls back to best guess
	 */
	public function determine_backup_type() {
		pb_backupbuddy::status( 'details', 'Determining backup type of file `' . basename( $this->file ) . '`' );
		// Third parameter skips option to detect from integrity file... since that's what we're doing here.
		$from_file = backupbuddy_core::getBackupTypeFromFile( $this->file, false, true );
		if ( '' != $from_file ) {

			// Set type.
			$this->set_backup_type( $from_file );

			// Record how we determined the backup type.
			$this->backup_typed_by = 'file-structure';
		} else {
			// If backup_type is unknown, try out best to guess what type it is from the inclusion / location / filename of the DAT file.
			// Make sure ZipBuddy is loaded.
			$this->require_zipbuddy();

			// If DAT file is here, we have a full backup (post 2.0).
			if ( pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, 'wp-content/uploads/backupbuddy_temp/' . $this->serial . '/backupbuddy_dat.php' ) === true ) {
				$this->set_backup_type( 'full' );
			}

			// If DAT file is here, we have a full backup (pre 2.0).
			if ( pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, 'wp-content/uploads/temp_' . $this->serial . '/backupbuddy_dat.php' ) === true ) {
				$this->set_backup_type( 'full' );
			}

			// If DAT file is in root, it's most likely a DB backup.
			if ( pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, 'backupbuddy_dat.php' ) === true ) {
				$this->set_backup_type( 'db' );
			}

			// Record how we determined the backup type.
			$this->backup_typed_by = 'backup-dat-location';
			pb_backupbuddy::status( 'details', 'Backup type of file `' . basename( $this->file ) . '` was determined to be `' . $this->backup_type . '` by `' . $this->backup_typed_by . '`' );
		}
	}

	/**
	 * Determines the correct options for this backup
	 *
	 * In order to perform the integrity check correctly, we need to know as much
	 * about the initial backup's settings as possible. Ideally, this comes from data in
	 * backupbuddy_temp/fileoptions/[backup_serial].txt. Otherwise, look for data inside the
	 * backup zip's DAT file if present. Also allow options to be directly passed to class in
	 * instances where we just performed the backup and are checking its integrity as the final
	 * step of the backup.
	 */
	public function determine_backup_options() {
		/**
		 * First, make sure the $backup_options isn't empty.
		 * If we are checking immediately after a Backup, it should have options from the Backup
		 * If we are checking on Backups Page load, it will load data from the fileoptions/[serial].txt
		 * Empty $backup_options at this point means we found a zip file w/o fileoptions/[serial].txt
		 */
		if ( empty( $this->backup_options ) ) {
			$fileoptions_path = backupbuddy_core::getLogDirectory() . 'fileoptions/' . $this->serial . '.txt';

			// Create the fileoptions/[serial].txt file.
			require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
			pb_backupbuddy::status( 'details', 'Fileoptions instance #44.' );
			$this->backup_options = new pb_backupbuddy_fileoptions( $fileoptions_path, $read_only = false, $ignore_lock = false, $create_file = true );

			// Confirm that the new file was created successfully.
			$result = $this->backup_options->is_ok();
			if ( true !== $result ) {
				pb_backupbuddy::status( 'error', __( 'Fatal Error #9034 C. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error on file `' . $fileoptions_path . '`: ' . $result );
				pb_backupbuddy::status( 'haltScript', '' ); // Halt JS on page.
				return false;
			}

			// Make sure it has an array for $options property.
			if ( ! is_array( $this->backup_options->options ) ) {
				$this->backup_options->options = array();
			}

			// Flag this as Rebuilt.
			$this->backup_options->options['fileoptions_rebuilt'] = true;

			// If we have a DAT file in this zip, let's load that data into the newly created $backup_options.
			$dat = backupbuddy_core::getDatArrayFromZip( $this->file );
			if ( ! is_array( $dat ) ) {
				$dat = array();
			}
			$this->backup_options->options = array_merge( $this->backup_options->options, $dat );
		} elseif ( ! empty( $this->backup_options->options['integrity'] ) && pb_backupbuddy::_GET( 'reset_integrity' ) != $this->serial ) {
			// Already have integrity data and NOT resetting this one.
			pb_backupbuddy::status( 'details', 'Integrity data for backup `' . $this->serial . '` is cached; not scanning again.' );
			$this->integrity_array = $this->backup_options->options['integrity'];
			return false;
		} else {
			pb_backupbuddy::status( 'details', 'Resetting backup integrity stats for backup with serial `' . $this->serial . '`.' );
		}

		// Re-check to make sure options is an array.
		if ( ! is_array( $this->backup_options->options ) ) {
			$this->backup_options->options = array();
		}

		// Set Skip Database options if not populated yet.
		if ( ! isset( $this->backup_options->options['skip_database_dump'] ) ) {
			$this->backup_options->options['skip_database_dump'] = ! empty( $this->additional_options['skip_database_dump'] ) ? 1 : 0;
		}

		// Cleanup some backcompat array names.
		if ( isset( $this->backup_options->options['tables_sizes'] ) ) {
			$this->backup_options->options['table_sizes'] = $this->backup_options->options['tables_sizes'];
			unset( $this->backup_options->options['tables_sizes'] );
		}
		if ( isset( $this->backup_options->options['backup_type'] ) ) {
			$this->backup_options->options['type'] = $this->backup_options->options['backup_type'];
			unset( $this->backup_options->options['backup_type'] );
		}
		return true;
	}

	/**
	 * Sets up the integrity array to be populated later
	 */
	public function set_integrity_array() {
		$this->integrity_array = pb_backupbuddy::settings( 'backups_integrity_defaults' );
	}

	/**
	 * If Integrity checks are turned of globally for for this individual backup, add a 'test' to warn them and bail.
	 */
	public function bail_if_disabled() {

		// Integrity check disabled. Skip.
		if (
			'0' == pb_backupbuddy::$options['profiles'][0]['integrity_check'] &&
			'' == pb_backupbuddy::_GET( 'reset_integrity' ) &&
			isset( $this->backup_options->options['integrity_check'] ) &&
			'0' == $this->backup_options->options['integrity_check'] ) {
				// Integrity checking disabled. Allows run if manually rescanning on backups page.
				pb_backupbuddy::status( 'details', 'Integrity check disabled. Skipping scan.' );
				$file_stats = @stat( $this->file );
			if ( false === $file_stats ) { // stat failure.
				pb_backupbuddy::status( 'error', 'Error #4539774. Unable to get file details ( via stat() ) for file `' . $this->file . '`. The file may be corrupt, too large for the server, or been deleted unexpectedly. Check that the file exists and can be accessed.' );
				$file_size     = 0;
				$file_modified = 0;
			} else { // stat success.
				$file_size     = $file_stats['size'];
				$file_modified = $file_stats['mtime'];
			}

			unset( $file_stats );

			$integrity = array(
				'status'        => 'Unknown (check disabled)',
				'tests'         => array(),
				'scan_time'     => 0,
				'detected_type' => 'unknown',
				'size'          => $file_size,
				'modified'      => $file_modified,
				'file'          => basename( $this->file ),
				'comment'       => false,
			);

			$this->backup_options->options['integrity'] = array_merge( pb_backupbuddy::settings( 'backups_integrity_defaults' ), $integrity );
			$this->backup_options->save();

			return $this->backup_options->options['integrity'];
		}

		return false;
	}

	/**
	 * Make sure the zipbuddy library is loaded
	 */
	public function require_zipbuddy() {
		if ( ! isset( pb_backupbuddy::$classes['zipbuddy'] ) ) {
			require_once pb_backupbuddy::plugin_path() . '/lib/zipbuddy/zipbuddy.php';
			pb_backupbuddy::$classes['zipbuddy'] = new pluginbuddy_zipbuddy( backupbuddy_core::getBackupDirectory() );
		}
	}

	/**
	 * Sets the backup_type property
	 *
	 * @param string $type  Type of backup.
	 */
	public function set_backup_type( $type ) {
		$this->backup_type = $type;
	}

	/**
	 * Redirects logging to zipbuddy_test log if flagged
	 */
	public function maybe_redirect_logging() {
		if ( true !== $this->skip_log_redirect ) {
			// Store current status serial setting to reset back later.
			$this->previous_status_serial = pb_backupbuddy::get_status_serial();
			pb_backupbuddy::status( 'details', 'Redirecting status logging temporarily.' );
			pb_backupbuddy::set_status_serial( 'zipbuddy_test' ); // Redirect logging output to a certain log file.
		}
	}

	/**
	 * Stop redirecting the log if setup earlier
	 */
	public function maybe_stop_log_redirect() {
		if ( true !== $this->skip_log_redirect ) {
			// Stop redirecting log to a specific file & set back to what it was prior.
			pb_backupbuddy::set_status_serial( $this->previous_status_serial );
			pb_backupbuddy::status( 'details', 'Stopped temporary redirection of status logging.' );
		}
	}

	/**
	 * Pre Test setup
	 */
	public function pre_test_events() {
		// Look for comment.
		pb_backupbuddy::status( 'details', 'Verifying comment in zip archive.' );
		$raw_comment   = pb_backupbuddy::$classes['zipbuddy']->get_comment( $this->file );
		$comment       = backupbuddy_core::normalize_comment_data( $raw_comment );
		$this->comment = $comment['note'];
	}

	/**
	 * Registers the appropriate tests for this type of backup
	 */
	public function run_tests() {

		pb_backupbuddy::status( 'details', 'NOTE: It is normal to see several "File not found" messages in the next several log lines.' );

		// Check for DAT file if backuptype requires it.
		if ( in_array( $this->backup_type, array( 'full', 'db' ) ) ) {
			$this->tests[] = $this->_test_dat_file_exists();
		}

		// Basic file list scan.
		if ( in_array( $this->backup_type, array( 'files', 'media', 'themes', 'plugins' ) ) ) {
			$this->tests[] = $this->_test_basic_file_scan();
		}

		// Perform one of the many DB tests depending on backup type and backup options.
		if ( ! in_array( $this->backup_type, array( 'files', 'themes', 'plugins', 'media' ) ) ) {
			$this->tests[] = $this->_test_db_route();
		}

		// Perform wp-config.php test for full backups.
		if ( 'full' == $this->backup_type ) {
			$this->tests[] = $this->_test_wp_config();
		}

	}

	/**
	 * Grabs the scan log from zipbuddy
	 */
	private function set_scan_log() {
		pb_backupbuddy::status( 'details', 'Retrieving zip scan log.' );

		$temp_details = pb_backupbuddy::get_status( 'zipbuddy_test' ); // Get zipbuddy scan log.
		$scan_log     = array();

		foreach ( $temp_details as $temp_detail ) {
			$this->scan_log[] = json_decode( $temp_detail )->{ 'data' };
		}
		unset( $temp_details );

	}

	/**
	 * Calculate the test results
	 */
	private function calculate_test_results() {

		pb_backupbuddy::status( 'details', 'Calculating integrity scan status,' );

		// Check for any failed tests.
		$is_ok                 = true;
		$integrity_description = '';

		foreach ( $this->tests as $test ) {
			if ( true !== $test['pass'] ) {
				$is_ok = false;
				$error = 'Error #389434. Integrity test FAILED. Test: `' . $test['test'] . '`.';
				pb_backupbuddy::status( 'error', $error );
				$integrity_description .= $error;
			}
		}

		if ( true === $is_ok ) {
			$integrity_status = 'Pass';
		} else {
			$integrity_status = 'Fail';
		}

		pb_backupbuddy::status( 'details', 'Status: `' . $integrity_status . '`. Description: `' . $integrity_description . '`.' );

		pb_backupbuddy::status( 'details', 'Getting file details such as size, timestamp, etc.' );
		$file_stats = @stat( $this->file );

		if ( false === $file_stats ) {
			// stat failure.
			pb_backupbuddy::status( 'error', 'Error #4539774b. Unable to get file details ( via stat() ) for file `' . $this->file . '`. The file may be corrupt, too large for the server, or been deleted unexpectedly. Check that the file exists and can be accessed.' );
			$file_size     = 0;
			$file_modified = 0;
		} else {
			// stat success.
			$file_size     = $file_stats['size'];
			$file_modified = $file_stats['mtime']; // Created time.
		}
		unset( $file_stats );

		// Compile array of results for saving into data structure.
		$integrity = array(
			'is_ok'         => $is_ok,                  // bool.
			'tests'         => $this->tests,            // Array of tests.
			'scan_time'     => time(),
			'scan_log'      => $this->scan_log,
			'scan_notes'    => $this->scan_notes,       // Misc text to display next to status.
			'detected_type' => $this->backup_type,
			'size'          => $file_size,
			'modified'      => $file_modified,          // Actually created time now.
			'file'          => basename( $this->file ),
			'comment'       => $this->comment,          // boolean false if no comment. string if comment.
		);

		$integrity = array_merge( pb_backupbuddy::settings( 'backups_integrity_defaults' ), $integrity );

		if ( is_array( $this->backup_options->options ) ) {
			pb_backupbuddy::status( 'details', 'Saving backup file integrity check details.' );
			$this->backup_options->options['integrity'] = $integrity;
			$this->backup_options->save();
		}

		$this->integrity_array = array_merge( $this->integrity_array, $integrity );
	}

	/**
	 * Tests to see if dat file exists.
	 *
	 * @return array  Test name and pass bool.
	 */
	private function _test_dat_file_exists() {

		$pass = false;
		pb_backupbuddy::status( 'details', 'Verifying DAT file in zip archive.' );

		// If DB, only check the root.
		if ( 'db' == $this->backup_type ) {
			// DB Backups.
			if ( ( pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, 'backupbuddy_dat.php' ) === true ) && 'db' == $this->backup_type ) {
				$pass = true;
			}
		} else {
			// Post 2.0 full backup.
			if ( pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, 'wp-content/uploads/backupbuddy_temp/' . $this->serial . '/backupbuddy_dat.php' ) === true ) {
				$pass = true;
			}

			// Pre 2.0 full backup.
			if ( pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, 'wp-content/uploads/temp_' . $this->serial . '/backupbuddy_dat.php' ) === true ) {
				$pass = true;
			}
		}

		// Return pass/fail array.
		return array(
			'test' => 'BackupBuddy data file',
			'pass' => $pass,
		);
	}

	/**
	 * Runs a basic file scan to make sure we added data to the zip
	 */
	private function _test_basic_file_scan() {
		$files = pb_backupbuddy::$classes['zipbuddy']->get_file_list( $this->file );
		$count = 0;
		$pass  = false;
		if ( is_array( $files ) ) {
			$count = count( $files );
			$pass  = $count > 0;
		}
		$href = admin_url( 'admin.php' ) . '?page=pb_backupbuddy_backup&zip_viewer=' . basename( $this->file ) . '&value=' . basename( $this->file ) . '&bub_rand=' . rand( 100, 999 );

		return array(
			'test'      => 'Basic file list scan (' . $count . ' files found inside) - <a target="_top" href="' . $href . '">Browse Files</a>',
			'pass'      => $pass,
			'fileCount' => $count,
		);

	}

	/**
	 * Determines which verion of the DB test needs to be run for the current backup
	 */
	private function _test_db_route() {

		$results = array(
			'test' => 'Database SQL file',
			'pass' => false,
		);

		// Abort if database not set to be backed up... but warn.
		if ( '1' == $this->backup_options->options['skip_database_dump'] ) {
			pb_backupbuddy::status( 'warning', 'WARNING: Database .SQL was NOT verified because database dump was set to be skipped based on settings. Use with caution. The database was NOT backed up.' );
			$results['pass']    = true;
			$results['test']   .= ' <span class="pb_label pb_label-warning">' . __( 'Database skipped', 'it-l10n-backupbuddy' ) . '</span>';
			$this->scan_notes[] = '<span class="pb_label pb_label-warning">' . __( 'Database skipped', 'it-l10n-backupbuddy' ) . '</span>';
			return $results;
		}

		pb_backupbuddy::status( 'details', 'Verifying database SQL file in zip archive.' );

		if ( isset( $this->backup_options->options['table_sizes'] ) && ( count( $this->backup_options->options['table_sizes'] ) > 0 ) ) {
			// DB Test for 5.0+.
			$results = array_merge( $results, $this->_test_db_bub5() );
		} elseif ( pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, 'wp-content/uploads/backupbuddy_temp/' . $this->serial . '/db_1.sql' ) === true ) {
			// DB Test for Full Backups 2.0+.
			$results = array_merge( $results, $this->_test_db_bub2_full() );
		} elseif ( pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, 'db_1.sql' ) === true ) {
			// DB only backup 2.0+.
			// BUB 5.0+ if breaking out tables only partially or forcing to single file.
			$results = array_merge( $results, $this->_test_db_single_file() );
		} elseif ( pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, 'wp-content/uploads/temp_' . $this->serial . '/db.sql' ) === true ) {
			// Pre BUB 2.0 Full.
			$results['pass'] = true;
		} elseif ( pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, 'db.sql' ) === true ) {
			// Pre BUB 2.0 DB only.
			$results['pass'] = true;
		}

		return $results;
	}

	/**
	 * DB Test for 5.0+
	 */
	private function _test_db_bub5() {

		$results = array(
			'test' => 'Database SQL file',
			'pass' => true,
		);
		pb_backupbuddy::status( 'details', 'BackupBuddy v5.0+ format database detected.' );

		// Lets make sure we have a couple flags set.
		if ( ! isset( $this->backup_options->options['force_single_db_file'] ) ) {
			$this->backup_options->options['force_single_db_file'] = false;
		}

		// Report Single DB status.
		if ( true === $this->backup_options->options['force_single_db_file'] ) {
			pb_backupbuddy::status( 'details', 'Forcing to a single db_1.sql file WAS enabled for this backup. Only db_1.sql files will be checked for.' );
		} else {
			pb_backupbuddy::status( 'details', 'Forcing to a single db_1.sql file was NOT enabled for this backup.' );
		}

		if ( 'db' == $this->backup_type ) {
			// DB Only BackupType.
			pb_backupbuddy::status( 'details', 'Database-only type backup.' );

			if ( pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, 'db_1.sql' ) === true ) {
				// This is a commandline based DB dump.
				pb_backupbuddy::status( 'details', 'Command line based database dump type.' );

				if (
					isset( $this->backup_options->options['breakout_tables'] ) &&
					( count( $this->backup_options->options['breakout_tables'] ) > 0 ) &&
					( true !== $this->backup_options->options['force_single_db_file'] )
					) {
						// We need to verify broken out table SQL files exist.
						pb_backupbuddy::status( 'details', 'Some tables were broken out. Checking for them (' . implode( ',', $this->backup_options->options['breakout_tables'] ) . '). (DB type)' );

					foreach ( $this->backup_options->options['breakout_tables'] as $table_name ) {
						$database_file = $table_name . '.sql';
						if ( ! pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, $database_file ) ) {
							pb_backupbuddy::status( 'error', 'Missing database file `' . $database_file . '` in backup. Err 3849474b.' );
							$results['pass'] = false;
							break;
						}
					}
				}
			} else {
				// PHP-based SQL Dump.
				pb_backupbuddy::status( 'details', 'PHP based database dump type.' );

				foreach ( $this->backup_options->options['table_sizes'] as $table_name => $table_size ) {
					$database_file = $table_name . '.sql';
					if ( ! pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, $database_file ) ) {
						pb_backupbuddy::status( 'error', 'Missing database file `' . $database_file . '` in backup. Err 383783.' );
						$results['pass'] = false;
						break;
					}
				}
			}
		} else {
			// Backup type is Full, MS, or Export.
			pb_backupbuddy::status( 'details', 'Not database-only type backup.' );

			if ( pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, 'wp-content/uploads/backupbuddy_temp/' . $this->serial . '/db_1.sql' ) === true ) {
				// Commandline based SQL DUMP.
				pb_backupbuddy::status( 'details', 'Command line based database dump type.' );

				if (
					isset( $this->backup_options->options['breakout_tables'] ) &&
					( count( $this->backup_options->options['breakout_tables'] ) > 0 )
					&& ( true !== $this->backup_options->options['force_single_db_file'] )
				) {
					// We need to verify broken out table SQL files exist.
					pb_backupbuddy::status( 'details', 'Some tables were broken out. Checking for them (' . implode( ',', $this->backup_options->options['breakout_tables'] ) . '). (DB type)' );

					foreach ( $this->backup_options->options['breakout_tables'] as $table_name ) {
						$database_file = 'wp-content/uploads/backupbuddy_temp/' . $this->serial . '/' . $table_name . '.sql';
						if ( ! pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, $database_file ) ) {
							pb_backupbuddy::status( 'error', 'Missing database file `' . $database_file . '` in backup. Err 3849474c.' );
							$results['pass'] = false;
							break;
						}
					}
				}
			} else {
				// PHP-based SQL Dump.
				pb_backupbuddy::status( 'details', 'PHP based database dump type.' );

				foreach ( $this->backup_options->options['table_sizes'] as $table_name => $table_size ) {
					$database_file = 'wp-content/uploads/backupbuddy_temp/' . $this->serial . '/' . $table_name . '.sql';
					if ( ! pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, $database_file ) ) {
						pb_backupbuddy::status( 'error', 'Missing database file `' . $database_file . '` in backup. Backup type: `' . $this->backup_options->options['type'] . '`. Err 358383.' );
						$results['pass'] = false;
						break;
					}
				}
			}
		}

		// Make test name plural and append table count to it.
		$results['test'] .= 's (' . count( $this->backup_options->options['table_sizes'] ) . ' tables)';

		return $results;
	}

	/**
	 * DB Test for Full Backups 2.0+
	 */
	private function _test_db_bub2_full() {
		$results = array(
			'pass' => true,
		);
		if (
			isset( $this->backup_options->options['breakout_tables'] ) &&
			( count( $this->backup_options->options['breakout_tables'] ) > 0 )
		) {
			// We have to verify broken out table SQL files exist.
			pb_backupbuddy::status( 'details', 'Some tables were broken out. Checking for them (' . implode( ',', $this->backup_options->options['breakout_tables'] ) . '). (full type)' );
			foreach ( $this->backup_options->options['breakout_tables'] as $table_name ) {
				$database_file = 'wp-content/uploads/backupbuddy_temp/' . $this->serial . '/' . $table_name . '.sql';
				if ( ! pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, $database_file ) ) {
					pb_backupbuddy::status( 'error', 'Missing database file `' . $database_file . '` in backup. Err 3849474.' );
					$results['pass'] = false;
					break;
				}
			}
		}
		return $results;
	}

	/**
	 * DB only backup 2.0+.
	 * BUB 5.0+ if breaking out tables only partially or forcing to single file
	 */
	private function _test_db_single_file() {
		$results = array(
			'pass' => true,
		);
		if ( isset( $this->backup_options->options['breakout_tables'] ) && ( count( $this->backup_options->options['breakout_tables'] ) > 0 ) ) {
			// Need to verify broken out table SQL files exist.
			pb_backupbuddy::status( 'details', 'Some tables were broken out. Checking for them (' . implode( ',', $this->backup_options->options['breakout_tables'] ) . '). (db type)' );

			foreach ( $this->backup_options->options['breakout_tables'] as $table_name ) {
				$database_file = $table_name . '.sql';
				if ( ! pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, $database_file ) ) {
					pb_backupbuddy::status( 'error', 'Missing database file `' . $database_file . '` in backup. Err 3847583.' );
					$results['pass'] = false;
					break;
				}
			}
		}
		return $results;
	}

	/**
	 * Tests for the presence of the wp-config file if this was a full backup
	 */
	private function _test_wp_config() {
		$results = array(
			'test' => 'WordPress wp-config.php file (full backups only)',
			'pass' => false,
		);

		pb_backupbuddy::status( 'details', 'Verifying WordPress wp-config.php configuration file in zip archive.' );

		if ( pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, 'wp-config.php' ) === true ) {
			$results['pass'] = true;
		} elseif ( pb_backupbuddy::$classes['zipbuddy']->file_exists( $this->file, 'wp-content/uploads/backupbuddy_temp/' . $this->serial . '/wp-config.php' ) === true ) {
			$results['pass'] = true;
		} else {
			if ( isset( $this->backup_options->options['excludes'] ) ) {
				if ( false !== stristr( $this->backup_options->options['excludes'], 'wp-config.' ) ) {
					pb_backupbuddy::status( 'warning', 'Warning: An exclusion containing wp-config.php was found. Exclusions: `' . str_replace( array( "\r", "\r\n", "\n" ), '; ', $this->backup_options->options['excludes'] ) . '`.' );
				}
			}
		}

		return $results;
	}
}
