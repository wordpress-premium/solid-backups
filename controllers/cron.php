<?php
/**
 * Cron Controller
 *
 * @package BackupBuddy
 */

/**
 * Solid Backups Cron Controller class
 */
class pb_backupbuddy_cron extends pb_backupbuddy_croncore {

	/**
	 * Number of methods ran.
	 *
	 * @var int
	 */
	public $_methods_ran = 0;

	/**
	 * Previous method ran.
	 *
	 * @var string
	 */
	public $_prev_method = '';

	/**
	 * Master cron handling function as of v6.4.0.9. Wraps all cron functions, so we can limit to one cron run per PHP load.
	 * Method to call such as "process_backup" will call the method in this function prefixed with an underscore
	 * "_process_backup" (to prevent accidentally directly calling).
	 *
	 * @param string $method Name of method.
	 * @param array  $args   Array of arguments.
	 *
	 * @return bool  If successful.
	 */
	public function cron( $method, $args = array() ) {

		// This may need to be expanded to include 'periodic_process' - JR3 09-21-2023.
		$skip_auto_schedule = array(
			'process_backup'
		);
		// If limiting to one cron method per PHP load AND we have already ran one method this PHP process then chunk to next run.
		if (
			! in_array( $method, $skip_auto_schedule )
			&& 1 === intval( pb_backupbuddy::$options['limit_single_cron_per_pass'] )
			&& $this->_methods_ran > 0
			) {

			$group = backupbuddy_constants::CRON_GROUP;

			// @todo add autoloader.
			require_once pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php';
			if ( backupbuddy_live_periodic::TRIGGER === $method ) {
				$group = backupbuddy_live_periodic::CRON_GROUP;
			}

			// Use reschedule_event for both creating and restoring backups.
			$success = backupbuddy_core::schedule_next_event( $method, $args, $group );
			if ( empty( $success ) ) {
				$message = sprintf(
					__( 'Error %1$s: Max cron single pass reschedule limit of `%2$s` hit. Method: `%3$s`. Args: `%4$s`. Previous method: `%5$s`.', 'it-l10n-backupbuddy' ),
					'#8399823',
					backupbuddy_constants::CRON_SINGLE_PASS_RESCHEDULE_LIMIT,
					$method,
					print_r( $args, true ),
					$this->_prev_method
				);
				pb_backupbuddy::status( 'error', $message );

				// If backup process then log to serial log file.
				if ( 'process_backup' === $method ) {
					pb_backupbuddy::status( 'error', $message, $args[0] );
				}
				return false;
			}

			$message = sprintf(
				__( 'Rescheduled cron for method `%1$s` as setting to limit single cron per pass enabled. Details: `%2$s`. .Previous method: `%3$s`.', 'it-l10n-backupbuddy' ),
				$method,
				print_r( $args, true ),
				$this->_prev_method
			);

			pb_backupbuddy::status( 'details', $message );
			if ( 'process_backup' === $method ) { // If backup process then log to serial log file.
				pb_backupbuddy::status( 'details', $message, $args[0] );
			}

			// If user has Solid Backups Stash Live then call cron kicker.
			require_once pb_backupbuddy::plugin_path() . '/destinations/live/live.php';
			if ( false !== backupbuddy_live::getLiveID() ) {
				require_once pb_backupbuddy::plugin_path() . '/destinations/stash3/init.php';
				pb_backupbuddy_destination_stash3::cron_kick_api( pb_backupbuddy::$options['remote_destinations'][ backupbuddy_live::getLiveID() ] );
			}

			return true;
		}

		$this->_methods_ran++;
		call_user_func_array( array( $this, '_' . $method ), $args );
		$this->_prev_method = $method;

		return true;
	}

	/**
	 * Runs backup process behind the scenes. Used by both scheduled AND manual backups to actually do the bulk of the work.
	 *
	 * @param string $serial  Backup Serial string.
	 */
	public function _process_backup( $serial = 'blank' ) {
		pb_backupbuddy::set_status_serial( $serial );
		pb_backupbuddy::status( 'details', '--- New PHP process.' );
		pb_backupbuddy::set_greedy_script_limits();
		pb_backupbuddy::status( 'message', 'Running process for serial `' . $serial . '`...' );

		require_once pb_backupbuddy::plugin_path() . '/classes/backup.php';
		$new_backup = new pb_backupbuddy_backup();
		$new_backup->process_backup( $serial );
	}

	/**
	 * Live Destinations
	 */
	public function _live() {
		$wiggle_room = 15 * 60; // Seconds process is allowed to run early.

		require_once pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php';
		$state = backupbuddy_live_periodic::get_stats();

		$time_since_last_activity = microtime( true ) - $state['stats']['last_activity'];

		// If not much time has passed since last periodic activity, assume still running and relay from running.
		if ( $time_since_last_activity < backupbuddy_constants::MINIMUM_TIME_BETWEEN_ACTIVITY_AND_PERIODIC_CRON_RUN ) { // 30min.
			$this->_methods_ran--; // Did not waste any time so reset method ran count to allow other BB crons to run.
			pb_backupbuddy::status( 'details', 'Not enough time elapsed since last activity to run Live based on MINIMUM_TIME_BETWEEN_ACTIVITY_AND_PERIODIC_CRON_RUN constant.' );
			return;
		}

		require_once pb_backupbuddy::plugin_path() . '/destinations/live/live.php';
		$live_id = backupbuddy_live::getLiveID();
		if ( false === $live_id ) {
			pb_backupbuddy::status( 'error', 'Error #483948934834: getLiveID() returned false. Live disabled?' );
			return;
		}
		$periodic_period = pb_backupbuddy::$options['remote_destinations'][ $live_id ]['periodic_process_period'];
		$schedule_times  = wp_get_schedules();
		if ( ! isset( $schedule_times[ $periodic_period ] ) ) {
			pb_backupbuddy::status( 'error', 'Error #38939844: Invalid schedule interval/period `' . $periodic_period . '`. Not found in wp_get_schedules().' );
			$this->_methods_ran--; // Did not waste any time so reset method ran count to allow other BB crons to run.
			return;
		}
		$periodic_interval = $schedule_times[ $periodic_period ]['interval'];

		// Are we trying to run before the time elapsed has exceeded the periodic interval? (Gives 15minute wiggle room buffer).
		if ( $time_since_last_activity < ( $periodic_interval - $wiggle_room ) ) {
			$this->_methods_ran--; // Did not waste any time so reset method ran count to allow other BB crons to run.
			pb_backupbuddy::status( 'details', 'Not enough time elapsed since last activity to run Live based on settings. Last activity: `' . $state['stats']['last_activity'] . '`. Elapsed since activity: `' . $time_since_last_activity . '`. Interval: `' . $periodic_interval . '`.' );
			return;
		}

		require_once pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php';
		backupbuddy_live_periodic::run_periodic_process(); // Run periodic process at whichever point it chooses.
	}

	/**
	 * Wrapper method for _live_periodic() to allow for a more descriptive method name.
	 *
	 * This is triggered specifically by a recurring action, and we want
	 * to be able to track that in the Action Scheduler logs.
	 */
	public function _live_pulse() {
		$this->_live_periodic();
	}

	/**
	 * Run Periodic Process
	 *
	 * @param string $preferred_step       Preferred step.
	 * @param array  $preferred_step_args  Array of args.
	 */
	public function _live_periodic( $preferred_step = '', $preferred_step_args = array() ) {
		require_once pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php';
		backupbuddy_live_periodic::run_periodic_process( $preferred_step, $preferred_step_args );
	}

	/**
	 * Handles trim.
	 *
	 * @uses trim_remote_archives
	 *
	 * @return bool  Result of trim_remote_archives()
	 */
	public function _live_after_snapshot() {
		require_once pb_backupbuddy::plugin_path() . '/destinations/live/live.php';
		return backupbuddy_live::trim_remote_archives();
	}

	/**
	 * Run the PHP Runtime and Memory tests.
	 *
	 * This is scheduled so that the plugin will not be
	 * stalled immediately upon activation.
	 */
	public function _run_initial_php_tests() {
		require_once pb_backupbuddy::plugin_path() . '/classes/housekeeping.php';

		// These are forced to run immediately.
		backupbuddy_housekeeping::schedule_php_runtime_tests( true );
		backupbuddy_housekeeping::schedule_php_memory_tests( true );
	}

	/**
	 * Test PHP max execution time. More reliable than reported.
	 *
	 * @uses php_runtime_test
	 *
	 * @param bool $schedule_results  Passed into php_runtime_test().
	 * @param bool $force_run         Passed into php_runtime_test().
	 */
	public function _php_runtime_test( $schedule_results = false, $force_run = false ) {
		require_once pb_backupbuddy::plugin_path() . '/classes/core.php';

		// Unschedule the runtime test since it will never complete in time.
		backupbuddy_core::unschedule_event( backupbuddy_constants::CRON_HOOK, array( 'php_runtime_test', array(), 0 ) );

		backupbuddy_core::php_runtime_test( $schedule_results, $force_run );
	}

	/**
	 * Test PHP memory. More reliable than reported.
	 *
	 * @uses php_memory_test
	 *
	 * @param bool $schedule_results  Passed into php_memory_test().
	 * @param bool $force_run         Passed into php_memory_test().
	 */
	public function _php_memory_test( $schedule_results = false, $force_run = false ) {
		require_once pb_backupbuddy::plugin_path() . '/classes/core.php';

		// Unschedule the memory test since it will never complete in time.
		backupbuddy_core::unschedule_event( backupbuddy_constants::CRON_HOOK, array( 'php_memory_test', array(), 0 ) );

		backupbuddy_core::php_memory_test( $schedule_results, $force_run );
	}

	/**
	 * Calculate the results of the PHP runtime test, if available.
	 *
	 * @uses php_runtime_test_results
	 */
	public function _php_runtime_test_results() {
		require_once pb_backupbuddy::plugin_path() . '/classes/core.php';
		backupbuddy_core::php_runtime_test_results();
	}

	/**
	 * Calculate the results of the PHP memory test, if available.
	 *
	 * @uses php_memory_test_results
	 */
	public function _php_memory_test_results() {
		require_once pb_backupbuddy::plugin_path() . '/classes/core.php';
		backupbuddy_core::php_memory_test_results();
	}

	/**
	 * Advanced cron-based remote file sending.
	 *
	 * @uses send_remote_destination
	 *
	 * @param int    $destination_id    Numeric array key for remote destination to send to.
	 * @param string $backup_file       Full file path to file to send.
	 * @param string $trigger           Trigger of this cron event. Valid values: scheduled, manual.
	 * @param bool   $send_importbuddy  Send copy of importbuddy.
	 * @param bool   $delete_after      Delete after.
	 */
	public function _remote_send( $destination_id, $backup_file, $trigger, $send_importbuddy = false, $delete_after = false ) {
		pb_backupbuddy::set_greedy_script_limits();

		if ( '' == $backup_file && $send_importbuddy ) {
			pb_backupbuddy::status( 'message', 'Only sending Importer to remote destination `' . $destination_id . '`.' );
		} else {
			$is_importbuddy  = $send_importbuddy ? 'Yes' : 'No';
			$do_delete_after = $delete_after ? 'Yes' : 'No';
			pb_backupbuddy::status( 'message', 'Sending `' . $backup_file . '` to remote destination `' . $destination_id . '`. Importbuddy?: `' . $is_importbuddy . '`. Delete after?: `' . $do_delete_after . '`.' );
		}

		if ( ! isset( pb_backupbuddy::$options ) ) {
			pb_backupbuddy::load();
		}

		if ( ! class_exists( 'backupbuddy_core' ) ) {
			require_once pb_backupbuddy::plugin_path() . '/classes/core.php';
		}

		backupbuddy_core::send_remote_destination( $destination_id, $backup_file, $trigger, $send_importbuddy, $delete_after );
	}

	/**
	 * Straight-forward send file(s) to a destination. Pass full array of destination settings. Called by chunking destination init.php's.
	 *
	 * NOTE: DOES NOT SUPPORT MULTIPART. SEE remote_send() ABOVE!
	 *
	 * @param array  $destination_settings  All settings for this destination for this action.
	 * @param array  $files                 Array of files to send (full path).
	 * @param string $send_id               Index ID of remote_sends associated with this send (if any).
	 * @param bool   $delete_after          Delete after.
	 * @param string $identifier            Identifier.
	 * @param bool   $is_retry              Is a retry.
	 */
	public function _destination_send( $destination_settings, $files, $send_id = '', $delete_after = false, $identifier = '', $is_retry = false ) {
		pb_backupbuddy::status( 'details', 'Beginning cron destination_send. Unique ID: `' . $identifier . '`.' );
		if ( '' != $identifier ) {
			$lock_file = backupbuddy_core::getLogDirectory() . 'cronSend-' . $identifier . '.lock';
			pb_backupbuddy::anti_directory_browsing( backupbuddy_core::getTempDirectory(), false );

			if ( @file_exists( $lock_file ) ) { // Lock exists already. Duplicate run?
				$attempts = @file_get_contents( $lock_file );
				$attempts++;
				pb_backupbuddy::status( 'warning', 'Lock file exists and now shows ' . $attempts . ' attempts.' );
				$attempts = @file_get_contents( $lock_file, $attempts );
				return;
			} else { // No lock yet.
				if ( false === @file_put_contents( $lock_file, '1' ) ) {
					pb_backupbuddy::status( 'warning', 'Unable to create destination send lock file `' . $lock_file . '`.' );
				} else {
					pb_backupbuddy::status( 'details', 'Create destination send lock file `' . $lock_file . '`.' );
				}
			}
		}

		pb_backupbuddy::status( 'details', 'Launching destination send via cron.' );
		if ( ! class_exists( 'backupbuddy_core' ) ) {
			require_once pb_backupbuddy::plugin_path() . '/classes/core.php';
		}

		backupbuddy_core::destination_send( $destination_settings, $files, $send_id, $delete_after, $is_retry );

	}

	/**
	 * Copy a file from a remote destination down to local.
	 *
	 * @param string $destination_type  Slug of destination type.
	 * @param string $file              Remote file to copy down.
	 * @param array  $settings          Remote destination settings.
	 *
	 * @return bool True on success, else false.
	 */
	public function _process_remote_copy( $destination_type, $file, $settings ) {
		if ( false !== strstr( $file, '?' ) ) {
			$url  = $file;
			$file = basename( $file );
			$file = substr( $file, 0, strpos( $file, '?' ) );
		}

		pb_backupbuddy::status( 'details', 'Copying remote `' . $destination_type . '` file `' . $file . '` down to local.' );
		pb_backupbuddy::set_greedy_script_limits();

		if ( ! class_exists( 'backupbuddy_core' ) ) {
			require_once pb_backupbuddy::plugin_path() . '/classes/core.php';
		}

		// Determine destination filename.
		$destination_file = backupbuddy_core::getBackupDirectory() . basename( $file );
		if ( file_exists( $destination_file ) ) {
			$destination_file = str_replace( 'backup-', 'backup_copy_' . pb_backupbuddy::random_string( 5 ) . '-', $destination_file );
		}
		pb_backupbuddy::status( 'details', 'Filename of resulting local copy: `' . esc_attr( $destination_file ) . '`.' );

		pb_backupbuddy::status(
			'details',
			'_process_remote_copy with `' . print_r(
				array(
					'destination_type' => $destination_type,
					'settings'         => $settings,
					'file'             => $file,
					'destination_file' => $destination_file,
				),
				true
			) . '`'
		);

		if ( in_array( $destination_type, ['stash2', 'stash3'], true ) ) {
			require_once pb_backupbuddy::plugin_path() . '/destinations/stash3/init.php';
			return pb_backupbuddy_destination_stash3::getFile( $settings, $file, $destination_file );
		} elseif ( in_array( $destination_type, ['s32', 's33'], true ) ) {
			require_once pb_backupbuddy::plugin_path() . '/destinations/s33/init.php';
			if ( true === pb_backupbuddy_destination_s33::getFile( $settings, $file, $destination_file ) ) { // success.
				pb_backupbuddy::status( 'details', 'S3 copy to local success.' );
				return true;
			} else { // fail.
				pb_backupbuddy::status( 'details', 'Error #328932789345. S3 copy to local FAILURE.' );
				return false;
			}
		} else {
			pb_backupbuddy::status( 'error', 'Error #859485. Unknown destination type for remote copy `' . $destination_type . '`. Did you mean to use `process_destination_copy`?' );
			return false;
		}

	}

	/**
	 * Downloads a remote backup and copies it to local server.
	 *
	 * @param array  $destination_settings  Array of destination settings.
	 * @param string $remote_file           Filename of file to get. Basename only.  Remote directory / paths / buckets / etc. should be passed in $destination_settings info.
	 * @param string $file_id               If destination uses a special file ID (e.g. GDrive) then pass that to destination file function instead of $remote_file. $remote_file used for calculating local filename.
	 *
	 * @return bool  True success, else false.
	 */
	public function _process_destination_copy( $destination_settings, $remote_file, $file_id = '' ) {
		if ( false !== strstr( $remote_file, '?' ) ) {
			$remote_file = basename( $remote_file );
			$remote_file = substr( $remote_file, 0, strpos( $remote_file, '?' ) );
		}

		pb_backupbuddy::status( 'details', 'Copying destination `' . $destination_settings['type'] . '` file `' . $remote_file . '` down to local.' );
		pb_backupbuddy::set_greedy_script_limits();

		if ( ! class_exists( 'backupbuddy_core' ) ) {
			require_once pb_backupbuddy::plugin_path() . '/classes/core.php';
		}

		require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';

		$local_file = backupbuddy_core::getBackupDirectory() . basename( $remote_file );
		if ( file_exists( basename( $local_file ) ) ) {
			$local_file = str_replace( 'backup-', 'backup_copy_' . pb_backupbuddy::random_string( 5 ) . '-', $local_file );
			$local_file = str_replace( 'snapshot-', 'snapshot_copy_' . pb_backupbuddy::random_string( 5 ) . '-', $local_file );
		}

		if ( '' != $file_id ) {
			$remote_file = $file_id;
		}
		if ( true === pb_backupbuddy_destinations::getFile( $destination_settings, $remote_file, $local_file ) ) {
			pb_backupbuddy::status( 'message', 'Success copying remote file to local.' );
			return true;
		}
		pb_backupbuddy::status( 'error', 'Failure copying remote file to local.' );
		return false;
	} // _process_destination_copy.

	/**
	 * Copy Rackspace backup to local backup directory
	 *
	 * @todo Merge into v3.1 destinations system in destinations directory.
	 *
	 * @param string $rs_backup     Rackspace Backup File.
	 * @param string $rs_username   Rackspace username.
	 * @param string $rs_api_key    Rackspace API key.
	 * @param string $rs_container  Rackspace container.
	 * @param string $rs_server     Rackspace server.
	 */
	public function _process_rackspace_copy( $rs_backup, $rs_username, $rs_api_key, $rs_container, $rs_server ) {
		pb_backupbuddy::set_greedy_script_limits();

		if ( ! class_exists( 'backupbuddy_core' ) ) {
			require_once pb_backupbuddy::plugin_path() . '/classes/core.php';
		}

		require_once pb_backupbuddy::plugin_path() . '/destinations/rackspace/lib/rackspace/cloudfiles.php';
		$auth = new CF_Authentication( $rs_username, $rs_api_key, null, $rs_server );
		$auth->authenticate();
		$conn = new CF_Connection( $auth );

		// Set container.
		$container = $conn->get_container( $rs_container );

		// Get file from Rackspace.
		$rsfile = $container->get_object( $rs_backup );

		$destination_file = backupbuddy_core::getBackupDirectory() . $rs_backup;
		if ( file_exists( $destination_file ) ) {
			$destination_file = str_replace( 'backup-', 'backup_copy_' . pb_backupbuddy::random_string( 5 ) . '-', $destination_file );
		}

		$fso = fopen( backupbuddy_core::getBackupDirectory() . $rs_backup, 'w' );
		$rsfile->stream( $fso );
		fclose( $fso );
	}

	/**
	 * Process Restore Queue.
	 *
	 * @return bool  If processed or not.
	 */
	public function _process_restore_queue() {
		return backupbuddy_restore()->process();
	}

	/**
	 * Specifically restore files.
	 *
	 * @param int $itertion Iteration number.
	 *
	 * @return bool  If processed or not.
	 */
	public function _restore_files( $id = 0, $iteration = 0 ) {
		return backupbuddy_restore()->restore_files( $id, $iteration );
	}

	/**
	 * Run Solid Backups Housekeeping
	 *
	 * @uses run_periodic
	 */
	public function _housekeeping() {
		require_once pb_backupbuddy::plugin_path() . '/classes/housekeeping.php';
		backupbuddy_housekeeping::run_periodic();
	}


	/**
	 * Runs Live Troubleshooting
	 *
	 * @uses backupbuddy_live_troubleshooting::run
	 */
	public function _live_troubleshooting_check() {
		require pb_backupbuddy::plugin_path() . '/destinations/live/_troubleshooting.php';
		backupbuddy_live_troubleshooting::run();
	}

	/**
	 * Runs a scheduled backup.
	 *
	 * @param int $schedule_id  Schedule ID.
	 */
	public static function _run_scheduled_backup( $schedule_id ) {

		if ( ! is_main_site() ) { // Only run for main site or standalone. Multisite subsites do not allow schedules.
			return;
		}

		if ( ! class_exists( 'backupbuddy_core' ) ) {
			require_once pb_backupbuddy::plugin_path() . '/classes/core.php';
		}

		if ( ! isset( pb_backupbuddy::$options ) ) {
			pb_backupbuddy::load();
		}

		// Verify directories.
		backupbuddy_core::verify_directories( true );

		pb_backupbuddy::status( 'details', 'cron_process_scheduled_backup: ' . $schedule_id );

		$preflight_message = '';
		$preflight_checks  = backupbuddy_core::preflight_check();
		foreach ( $preflight_checks as $preflight_check ) {
			if ( true !== $preflight_check['success'] ) {
				pb_backupbuddy::status( 'warning', $preflight_check['message'] );
			}
		}

		if ( isset( pb_backupbuddy::$options['schedules'][ $schedule_id ] ) && ( is_array( pb_backupbuddy::$options['schedules'][ $schedule_id ] ) ) ) {

			// If schedule is disabled then just return. Bail out!
			if ( isset( pb_backupbuddy::$options['schedules'][ $schedule_id ]['on_off'] ) && '0' == pb_backupbuddy::$options['schedules'][ $schedule_id ]['on_off'] ) {
				pb_backupbuddy::status( 'message', 'Schedule `' . $schedule_id . '` NOT run due to being disabled based on this schedule\'s settings.' );
				return;
			}

			pb_backupbuddy::$options['schedules'][ $schedule_id ]['last_run'] = time(); // update last run time.
			pb_backupbuddy::save();

			require_once pb_backupbuddy::plugin_path() . '/classes/backup.php';
			$new_backup = new pb_backupbuddy_backup();

			if ( '1' == pb_backupbuddy::$options['schedules'][ $schedule_id ]['delete_after'] ) {
				$delete_after = true;
				pb_backupbuddy::status( 'details', 'Option to delete file after successful transfer enabled.' );
			} else {
				$delete_after = false;
				pb_backupbuddy::status( 'details', 'Option to delete file after successful transfer disabled.' );
			}

			// If any remote destinations are set then add these to the steps to perform after the backup.
			$post_backup_steps       = array();
			$destinations            = explode( '|', pb_backupbuddy::$options['schedules'][ $schedule_id ]['remote_destinations'] );
			$found_valid_destination = false;

			// Remove any invalid destinations from this run.
			foreach ( $destinations as $destination_index => $destination ) {
				if ( '' == $destination ) { // Remove.
					unset( $destinations[ $destination_index ] );
				}
				if ( ! isset( pb_backupbuddy::$options['remote_destinations'][ $destination ] ) ) { // Destination ID is invalid; remove.
					unset( $destinations[ $destination_index ] );
				}
			}
			$destination_count = count( $destinations );

			if ( $destination_count <= 0 ) {
				pb_backupbuddy::status( 'details', 'No valid destinations found.' );
			}

			$i = 0;
			foreach ( $destinations as $destination ) {
				$i++;
				if ( $i >= $destination_count ) { // Last destination. Delete after if enabled.
					$this_delete_after = $delete_after;
					pb_backupbuddy::status( 'details', 'Last destination set to send to so this file deletion will be determined by settings.' );
				} else { // More destinations to send to. Only delete after final send.
					$this_delete_after = false;
					pb_backupbuddy::status( 'details', 'More destinations are set to send to so this file will not be deleted after send.' );
				}
				$args = array( $destination, $this_delete_after );
				pb_backupbuddy::status( 'details', 'Adding send step with args `' . implode( ',', $args ) . '`.' );
				array_push(
					$post_backup_steps, array(
						'function'    => 'send_remote_destination',
						'args'        => $args,
						'start_time'  => 0,
						'finish_time' => 0,
						'attempts'    => 0,
					)
				);

				$found_valid_destination = true;
				pb_backupbuddy::status( 'details', 'Found valid destination.' );
			}

			$profile_array = pb_backupbuddy::$options['profiles'][ pb_backupbuddy::$options['schedules'][ $schedule_id ]['profile'] ];
			$profile_array = array_merge( pb_backupbuddy::$options['profiles'][0], $profile_array ); // Merge defaults.

			$backup_result = $new_backup->start_backup_process(
				$profile_array,
				'scheduled',
				array(),
				$post_backup_steps,
				pb_backupbuddy::$options['schedules'][ $schedule_id ]['title']
			);

			if ( ! $backup_result ) {
				pb_backupbuddy::status( 'error', 'Error #4564658344443: Backup failure. See earlier logging details for more information.' );
			}
		}
		pb_backupbuddy::status( 'details', 'Finished cron_process_scheduled_backup.' );
	}
}
