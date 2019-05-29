<?php
/* BackupBuddy Stash Live Periodic Class
 *
 * @author Dustin Bolton
 * @since 7.0
 *
 * Data files:
 *		LOGDIR/live/catalog-XXXXX.txt		File catalog with signatures.
 *		LOGDIR/live/state-XXXXX.txt			Overview of current state/progress including files pending, etc. Used to check if files need sending without loading entire signature.
 *
 *
 * Interesting data to display on Live page:
 *		Catalog filesize
 *		File stats: Number of files sent ouf ot total, size of all files sent out of total.
 *		Last file send time.
 *
 *
 * STEPS
 *		1. Generate list of files, leaving stats blank.
 *		2. Loop through list. Skip anything set to delete. If scantime is too old, calculate filesize, mtime, and optional sha1. Compare with existing values & update them.  If they changed then mark sent to false.
 *
 *
 * Catalog data:
 * 		array[filename] => {
 *			size
 *			mtime
 *			sha1
 *			scantime
 *			[sent]
 *			[delete]
 * 		}
 *
 */
class backupbuddy_live_periodic {

	const TIME_WIGGLE_ROOM = 6;	// Number of seconds to fudge up the time elapsed to give a little wiggle room so we don't accidently hit the edge and time out.
	const TIME_BETWEEN_FILE_RESCAN = 60; // [1min] Number of seconds to wait between rescanning a file. Don't get new stat or hash if this has not passed.
	const TIME_BETWEEN_FILE_AUDIT = 1800; // [30min] Minimum seconds between the file scan step running. Audit takes a while due to all the API activity.
	const REMOTE_SNAPSHOT_PERIOD_WIGGLE_ROOM = 14400; // [4hrs] Number of seconds we will allow periodic remote snapshot to run early.
	//const SAVE_SIGNATURES_EVERY_X_CHANGES = 300; // After this many files have had signature updates, save the signature catalog file. Don't do this too often since it uses I/O.
	const SAVE_SIGNATURES_EVERY_X_SECONDS = 5; // After this many seconds, re-save signature file.
	const MAX_SEND_ATTEMPTS = 4; // Maximum number of times we will try to resend a file before skipping it.
	const KICK_REQUEST_MINIMUM_PERIOD = 900; // Minimum number of seconds between requests for kicking.
	const CLOSE_CATALOG_WHEN_SENDING_FILESIZE = 1048576; // If sending a file of this size or greater then we will close out the catalog (and unlock) to prevent locking too long.
	const MINIMUM_SIZE_THRESHOLD_FOR_SPEED_CALC = 512000; // When calculating send speed, if a file is smaller then this amount then we assume this mimimum size so we can calculate a better estimate.
	const MAX_DAILY_STATS_DAYS = 14; // Number of days to keep daily transfer stats for.
	const MIN_ADJUSTED_MAX_RUNTIME_BEFORE_WARNING = 28; // If adjusted max runtime is less than x seconds then log a WARNING.
	const MIN_ADJUSTED_MAX_RUNTIME_BEFORE_ERROR = 18; // If adjusted max runtime is less than x seconds then log an ERROR.

	private static $_stateObj;
	private static $_state;
	private static $_catalogObj;
	private static $_catalog;
	private static $_tablesObj;
	private static $_tables;

	private static $_stepDefaults = array(
		'data_version'            => 1,
		'function'                => 'daily_init',
		'args'                    => array(),
		'start_time'              => 0, // Time this function first ran.
		'last_run_start'          => 0, // Time that this function last began.
		'last_run_finish'         => 0, // Time that this function last finished (eg from chunking).
		'last_status'             => '', // User-friendly status message.
		'attempts'                => 0, // Number of times this step has been attempted to run.
		'chunks'                  => 0, // Number of times this step has chunked to continue so far.
	);

	private static $_statsDefaults = array(
		'tables_total_count'      => 0,
		'tables_total_size'       => 0,
		'tables_pending_send'     => 0,
		'tables_pending_delete'   => 0,

		'files_total_count'       => 0,
		'files_total_size'        => 0,
		'files_pending_send'      => 0,
		'files_pending_delete'    => 0,
		'daily'                   => array(),

		'last_remote_snapshot'               => 0, // Timestamp of the last time a remote snapshot was triggered to begin.
		'last_remote_snapshot_id'            => '', // Snapshot ID of last remote snapshot triggered.
		'last_remote_snapshot_response'      => '', // Server response from last remote snapshot ran.
		'last_remote_snapshot_response_time' => 0, // Time of last server response.
		'last_remote_snapshot_trigger'       => '', // automatic, manual, unknown, or blank for none so far.
		'last_db_snapshot'                   => 0, // Timestamp of last db snapshot that completed db dump. NOT when files actually finished sending.
		'last_file_audit_finish'             => 0, // Timestamp of the last completition of file audit (checks for remote files that should not exist + updates 'v' key timestamp when remote file was verified to exist).
		'last_file_audit_start'   => 0, // Timestamp of the last start of file audit.
		'last_filesend_startat'   => 0, // Position to pick up sending files at to prevent duplicate from race conditions.
		'last_kick_request'       => 0, // Last time the cron kicker was contacted.
		'recent_send_fails'       => 0, // Number of recent remote send failures. If this gets too high we give up sending until next restart of the periodic process.
		'last_send_fail'          => 0, // Timestamp of last send failure.
		'last_send_fail_file'     => '', // Filename of last send failure.
		'wait_on_transfers_start' => 0, // Timestamp we began waiting on transfers to finish before snapshot.
		'first_activity'          => 0, // Timestamp of very first Live activity.
		'last_activity'           => 0,	// Timestamp of last periodic activity.
		'first_completion'        => 0,	// Timestamp of first 100% completion.
		'manual_snapshot'         => false, // Whether or not a manual snapshot is requested/pending.
	);

	private static $_statsDailyDefaults = array(
		'd_t'                    => 0, // Database total number of .sql files sent.
		'd_s'                    => 0, // Database bytes sent.
		'f_t'                    => 0, // Files total number sent (excluding .sql db files).
		'f_s'                    => 0, // Files total bytes send (excluding .sql db files).
	);
	// Default catalog array.
	/*private static $_catalogDefaults = array(
		'data_version' => 1,
		'signatures' => array(),
		'tables' => array(),
	);
	*/

	// Default signatures in the catalog.
	private static $_signatureDefaults = array(
		'r' => 0,	// Rescan/refresh timestamp (int).
		'm' => 0,	// Modified timestamp based on file mtime, NOT timestamp when signature was updated. (int)
		's' => 0,	// Size in bytes. (int)
		//'h' => '',	// Hash. (string). NOTE: As of v8 omitted unless being used to save catalog mem.
		'b' => 0,	// Backed up to Live server time. 0 if NOT backed up to server yet.
		'v' => 0,	// Verified via audit timestamp.
		//'t' => 0,	// Tries sending. AKA Transfer attempts. NOTE: As of v8 omitted unless being used to save catalog mem.
		//'d' => false, // Pending deletion. NOTE: As of v8 omitted unless being used to save catalog mem.
	);

	// Default table entries in the catalog.
	private static $_tableDefaults = array(
		'a' => 0,	// Added timestamp. (int)
		'm' => 0,	// Modified timestamp. (int)
		'b' => 0, // Backed up to Live server time. 0 if NOT backed up to server yet.
		's' => 0,	// Size in bytes. (int)
		't' => 0,	// Tries sending. AKA Transfer attempts.
		'd' => false, // Pending deletion.
	);

	// Function and the next function to run after it.
	private static $_nextFunction = array(
		'daily_init' => 'database_snapshot',
		'database_snapshot' => 'send_pending_db_snapshots',
		'send_pending_db_snapshots' => 'process_table_deletions',
		'process_table_deletions' => 'update_files_list',
		'update_files_list' => 'update_files_signatures',
		'update_files_signatures' => 'process_file_deletions',
		'process_file_deletions' => 'send_pending_files',
		'send_pending_files' => 'audit_remote_files',
		'audit_remote_files' => 'run_remote_snapshot',
		'wait_on_transfers' => 'run_remote_snapshot', // Only jumped to via queue if files remain prior to snapshot creation.
	);


	// Default directories to ALWAYS exclude. No trailing slashes.
	private static $_default_excludes = array(
		'/wp-content/cache/', // W3TC cache.
		'/wp-content/uploads/backupbuddy_temp/',
		'/wp-content/backup-db/',
		'/error_log',
		'/db_sucuri',
		'/wp-content/uploads/headway/cache/',
		'/wp-content/wflogs',

	);


	private static $_fileVars = null;

	/* run_periodic_process()
	 *
	 * Scan all files to find new, deleted, or modified files compared to what has been sent to Live.
	 *
	 * @param string	$preferredStep		Preferred step that we want to run.  Note that the preferredStep will only run if a step higher in the chain is not already running. Eg: Use to continue sending files UNLESS we have already looped back to starting over the periodic steps.
	 * @param array 	$preferredStepArgs	Argumenrts to pass to preferred step (if it runs).
	 *
	 */
	public static function run_periodic_process( $preferredStep = '', $preferredStepArgs = array() ) {

		require_once( pb_backupbuddy::plugin_path() . '/destinations/live/live.php' );
		$previous_status_serial = pb_backupbuddy::get_status_serial(); // Hold current serial.
		pb_backupbuddy::set_status_serial( 'live_periodic' ); // Redirect logging output to a certain log file.
		global $wp_version;
		$liveID = backupbuddy_live::getLiveID();
		$logging_disabled = ( isset( pb_backupbuddy::$options['remote_destinations'][ $liveID ]['disable_logging'] ) && ( '1' == pb_backupbuddy::$options['remote_destinations'][ $liveID ]['disable_logging'] ) );

		if ( ! $logging_disabled ) {
			pb_backupbuddy::status( 'details', '-----' );
			pb_backupbuddy::status( 'details', 'Live periodic process starting with BackupBuddy v' . pb_backupbuddy::settings( 'version' ) . ' with WordPress v' . $wp_version . '.' );
		}

		// Make sure we are not PAUSED.
		if ( '1' == pb_backupbuddy::$options['remote_destinations'][ $liveID ]['pause_periodic'] ) {
			pb_backupbuddy::status( 'details', 'Aborting periodic process as it is currently PAUSED based on settings.' );
			// Undo log redirect.
			pb_backupbuddy::set_status_serial( $previous_status_serial );
			return false;
		}

		// Logging disabled.
		if ( $logging_disabled ) {
			pb_backupbuddy::set_status_serial( $previous_status_serial );
		}

		require_once( pb_backupbuddy::plugin_path() . '/classes/core.php' );
		require_once( pb_backupbuddy::plugin_path() . '/classes/fileoptions.php' );

		// Register a shutdown function to catch PHP errors and log them.
		register_shutdown_function( 'backupbuddy_live_periodic::shutdown_function' );

		// Load state into self::$_state & fileoptions object into self::$_stateObj.
		if ( false === self::_load_state() ) {
			return false;
		}

		// No PHP runtime calculated yet. Try to see if test is finished.
		if ( 0 == pb_backupbuddy::$options['tested_php_runtime'] ) {
			backupbuddy_core::php_runtime_test_results();
		}

		// Update stats and save.
		if ( 0 === self::$_state['step']['start_time'] ) {
			self::$_state['step']['start_time'] = microtime( true );
		}
		self::$_state['step']['last_run_start'] = microtime( true );

		// Load destination settings.
		$destination_settings = self::get_destination_settings();

		// If wait_on_transfers was the last step running and time limit has passed then we can start from the beginning.
		if ( ( 'wait_on_transfers' == self::$_state['step']['function'] ) && ( self::$_state['stats']['wait_on_transfers_start'] > 0 ) && ( ( time() - self::$_state['stats']['wait_on_transfers_start'] ) > ( $destination_settings['max_wait_on_transfers_time'] * 60 ) ) ) {
			pb_backupbuddy::status( 'warning', 'Ran out of max time (`' . round( ( ( time() - self::$_state['stats']['wait_on_transfers_start'] ) / 60 ) ) . '` of `' . $destination_settings['max_wait_on_transfers_time'] . '` max mins) waiting for pending transfers to finish. Resetting back to beginning of periodic process.' );
			self::$_state['step'] = self::$_stepDefaults; // Clear step state.
		}

		// Increment attempts if running the same function exactly as before. Set preferredStep args if we are indeed on this step.
		//sort( self::$_state['step']['args'] ); // Make sure order is same.
		//sort( $preferredStepArgs ); // Make sure order is same.
		if ( ( '' == $preferredStep ) || ( ( self::$_state['step']['function'] == $preferredStep ) && ( self::$_state['step']['args'] == $preferredStepArgs ) ) ) { // If preferredStep is blank OR ( preferredStep matches next step AND arguments are the same ).
			self::$_state['step']['attempts']++;
		}
		if ( '' != $preferredStep ) {
			self::_set_next_step( $preferredStep, $preferredStepArgs );
		}

		// If restart transient is set then restart the Live process all the way back to daily_init. This is done when settings are saved so they will take effect immediately.
		if ( false !== ( $jump_step = get_transient( 'backupbuddy_live_jump' ) ) ) {
			pb_backupbuddy::status( 'details', 'Restart transient exists. Clearing.' );
			delete_transient( 'backupbuddy_live_jump' );
			$jump_step_name = $jump_step[0];
			$jump_step_args = array();
			if ( isset( $jump_step[1] ) && is_array( $jump_step[1] ) ) {
				$jump_step_args = $jump_step[1];
			}
			self::_set_next_step( $jump_step_name );
			pb_backupbuddy::status( 'details', 'Reset next step to `' . $jump_step_name . '` with args `' . print_r( $jump_step_args, true ) . '` due to backupbuddy_live_jump transient.' );
		}

		// Check if a manual snapshot is requested.
		if ( false !== get_transient( 'backupbuddy_live_snapshot' ) ) {
			pb_backupbuddy::status( 'details', 'Manual Live Snapshot requested.' );
			delete_transient( 'backupbuddy_live_snapshot' );
			self::_request_manual_snapshot();
		}

		// Set first activity (creation of Live basically).
		if ( 0 == self::$_state['stats']['first_activity'] ) {
			self::$_state['stats']['first_activity'] = time();
		}

		// Save attempt.
		self::$_stateObj->save();


		// Run step function and process results.
		$schedule_next_step = false;
		$start_time = microtime( true );
		$run_function = self::$_state['step']['function'];
		pb_backupbuddy::status( 'details', 'Starting Live periodic function `' . $run_function . '`.' );
		if ( ! is_callable( 'self::_step_' . $run_function ) ) {
			pb_backupbuddy::status( 'error', 'Error #439347494: Invalid step called: `' . $run_function . '` Unknown function: `self::_step_' . $run_function . '`.' );
		}
		$function_response = call_user_func_array(  'self::_step_' . $run_function, self::$_state['step']['args'] ); // Run step function. Returns true on success, string error message on fatal failure, and array( 'status message', array( ARGS ) ) when chunking back to same step.
		self::$_state['step']['last_run_finish'] = microtime( true );
		self::$_state['stats']['last_activity'] = microtime( true );
		pb_backupbuddy::status( 'details', 'Ended Live periodic function `' . $run_function . '`.' );


		// Process stepfunction results.
		if ( is_array( $function_response ) ) { // Chunking back to same step since we got an array. Index 0 = last_status, index 1 = args. Keeps same step function.

			$schedule_next_step = true;
			self::$_state['step']['chunks']++;
			self::$_state['step']['last_status'] = $function_response[ 0 ];
			self::$_state['step']['args'] = $function_response[ 1 ];
			pb_backupbuddy::status( 'details', 'Function needs chunked.' );
			if ( ( 'update_files_list' != $run_function ) && ( pb_backupbuddy::$options['log_level'] == '3' ) ) { // Full logging enabled. Hide for update_files_list function due to its huge size.
				pb_backupbuddy::status( 'details', 'Response args due to logging level: `' . print_r( $function_response, true ) . '`.' );
			}

		} elseif ( is_string( $function_response ) ) { // Fatal error.

			pb_backupbuddy::status( 'error', 'Error #32893283: One or more errors encountered running Live step function. Details: `' . $function_response . '`. See log above for more details.' );
			backupbuddy_core::addNotification( 'live_error', 'BackupBuddy Stash Live Error', $function_response );

			if ( FALSE === stristr( $function_response, 'Error' ) ) { // Make sure error-prefixed if not.
				$function_response = 'Error #489348: ' . $function_response;
			}
			self::$_state['step']['last_status'] = $function_response;

		} elseif ( true === $function_response ) { // Success finishing this step.

			// Interupted by a jump for the next step.
			if ( false !== ( $jump_step = get_transient( 'backupbuddy_live_jump' ) ) ) {
				pb_backupbuddy::status( 'details', 'Restart transient exists. Clearing.' );
				delete_transient( 'backupbuddy_live_jump' );
				$jump_step_name = $jump_step[0];
				$jump_step_args = array();
				if ( isset( $jump_step[1] ) && is_array( $jump_step[1] ) ) {
					$jump_step_args = $jump_step[1];
				}
				self::_set_next_step( $jump_step_name );
				pb_backupbuddy::status( 'details', 'Reset next step to `' . $jump_step_name . '` with args `' . print_r( $jump_step_args, true ) . '` due to backupbuddy_live_jump transient.' );

				$schedule_next_step = true;
			} else { // Normal next step running (if any).
				if ( ! isset( self::$_nextFunction[ $run_function ] ) ) {
					$schedule_next_step = false;
					pb_backupbuddy::status( 'details', 'Function reported success. No more Live steps to directly run. Finishing until next periodic restart.' );
					self::$_state['step'] = self::$_stepDefaults; // Clear step state.
				} else {
					$schedule_next_step = true;
					$nextFunction = self::$_nextFunction[ $run_function ];
					self::_set_next_step( $nextFunction );
					pb_backupbuddy::status( 'details', 'Function reported success. Scheduled next function to run, `' . $nextFunction . '`.' );
				}
			}

		} elseif ( false === $function_response ) {

			pb_backupbuddy::status( 'error', 'Error #3298338: Live (periodic) function `' . $run_function . '` failed without error message. Ending Live periodic process for this run without running more steps. See log above for details.' );
			$schedule_next_step = false;

		} else { // Unknown response.

			pb_backupbuddy::status( 'error', 'Error #98238392: Unknown periodic Live step function response `' . print_r( $function_response, true ) . '` for function `' . $run_function . '`. Fatal error.' );
			backupbuddy_core::addNotification( 'live_error', 'BackupBuddy Stash Live Error', $function_response );
			self::$_state['step']['last_status'] = 'Error: ' . $function_response;
			$schedule_next_step = false;

		}


		// Save state.
		if ( ! is_object( self::$_stateObj ) ) {
			pb_backupbuddy::status( 'warning', 'State object not expected type. Type: `' . gettype( self::$_stateObj ) . '`. Printed: `' . print_r( self::$_stateObj, true ) . '`.' );
		} else {
			self::$_stateObj->save();
		}


		// Unlock fileoptions files if any remain locked.
		if ( is_object( self::$_stateObj ) ) {
			self::$_stateObj->unlock();
		}
		if ( is_object( self::$_catalogObj ) ) {
			self::$_catalogObj->unlock();
		}
		if ( is_object( self::$_tablesObj ) ) {
			self::$_tablesObj->unlock();
		}


		// Schedule the next step in the WP cron to run whichever step has been set in the state.
		if ( true === $schedule_next_step ) {
			pb_backupbuddy::status( 'details', 'Scheduling next step.' );

			// Schedule to run Live one more time for next chunk.
			$cronArgs = array();
			$schedule_result = backupbuddy_core::schedule_single_event( time() - 60, 'live_periodic', $cronArgs ); // Schedules 60sec in the past to push near the top. Traditional backup process is 155sec in the past for first priority.
			if ( true === $schedule_result ) {
				pb_backupbuddy::status( 'details', 'Next Live Periodic chunk step cron event scheduled.' );
			} else {
				pb_backupbuddy::status( 'error', 'Next Live Periodic chunk step cron event FAILED to be scheduled.' );
			}

			// Only chains the first cron.
			if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
				pb_backupbuddy::status( 'details', 'Spawning cron now.' );
				update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
				spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
			}

			// Schedule cron kicker (detects if it has not been too soon so we can call this judicously).
			self::_request_kick_cron();

		} else {
			// Nothing left to do for now. Take a nap and wait until the next time that the periodic functionality launches and starts the process all over again.
			pb_backupbuddy::status( 'details', 'No more steps remain for this run. Not scheduling next step.' );
		}

		// Undo log redirect.
		pb_backupbuddy::set_status_serial( $previous_status_serial );
		return true;

	} // End run_periodic_process().



	/* retart_periodic()
	 *
	 * Restart the periodic process.
	 *
	 */
	public static function restart_periodic( $force = false ) {
		require_once( pb_backupbuddy::plugin_path() . '/destinations/live/live.php' );

		if ( false !== $destination_id = backupbuddy_live::getLiveID() ) {
			pb_backupbuddy::$options['remote_destinations'][$destination_id]['pause_periodic'] = '0';
			pb_backupbuddy::save();
		}

		if ( $force ) {
			backupbuddy_live::queue_step( 'daily_init', $args = array(), $skip_run_now = false, $force_run_now  = true );
			pb_backupbuddy::alert( __( 'Enabled Live Files Backup and Restarted Periodic Process (forced to run now).', 'it-l10n-backupbuddy' ) );
		} else {
			backupbuddy_live::queue_step( 'daily_init', $args = array() );
			pb_backupbuddy::alert( __( 'Enabled Live Files Backup and Restarted Periodic Process (only running if between steps or timed out).', 'it-l10n-backupbuddy' ) );
		}
	} // End restart_periodic().



	/* _request_kick_cron()
	 *
	 * Requests that the Stash API kick our cron along for a certain amount of time.
	 *
	 */
	private static function _request_kick_cron() {

		if ( false !== self::_load_state() ) {
			if ( ( time() - self::$_state['stats']['last_kick_request'] ) < self::KICK_REQUEST_MINIMUM_PERIOD ) { // Too soon.
				return false;
			}

			// Update last kick request time & save.
			self::$_state['stats']['last_kick_request'] = time();
			self::$_stateObj->save();

			$settings = pb_backupbuddy::$options['remote_destinations'][ backupbuddy_live::getLiveID() ];
			if ( ! isset( $settings['destination_version'] ) ) {
				$settings['destination_version'] = '2';
			}

			// Request the kicks.
			require_once( pb_backupbuddy::plugin_path() . '/destinations/stash' . $settings['destination_version'] . '/init.php' );
			call_user_func_array( array( 'pb_backupbuddy_destination_stash' . $settings['destination_version'], 'cron_kick_api' ), array( $settings ) );

			return true;
		}

		return false;

	} // End _request_kick_cron().



	/* _set_next_step()
	 *
	 * Set the next step to run, copying the current step into the state's prev_step key for reference/debugging.
	 * @return null
	 */
	public static function _set_next_step( $step_name, $args = array(), $save_now_and_unload = false ) {

		if ( false === self::_load_state() ) {
			pb_backupbuddy::status( 'error', 'Error #89383494: Unable to load state.' );
			return false;
		}

		if ( ( '' == $step_name ) || ( is_array( $step_name ) ) ) {
			pb_backupbuddy::status( 'error', 'Error #348934894: Invalid next step. Not scheduling step `' . print_r( $step_name, true ) . '`.' );
			return false;
		}

		self::$_state['prev_step'] = self::$_state['step']; // Hold the previous step for reference.
		self::$_state['step'] = self::$_stepDefaults;
		self::$_state['step']['function'] = $step_name;
		self::$_state['step']['args'] = $args;

		if ( true === $save_now_and_unload ) {
			self::$_stateObj->save();
			self::$_stateObj->unlock();
		}

	} // End _set_next_step().



	/* _step_daily_init()
	 *
	 * Daily housekeeping at the beginning of the daily Live periodic process.
	 *
	 */
	private static function _step_daily_init( $manual_snapshot = false ) {

		pb_backupbuddy::status( 'details', 'BackupBuddy v' . pb_backupbuddy::settings( 'version' ) . ' Live Daily Initialization -- ' . pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( time() ) ) . '.' );

		self::$_state['stats']['recent_send_fails'] = 0; // Reset daily fail count.
		self::$_state['stats']['last_send_fail'] = 0; // Reset daily fail stats,
		self::$_state['stats']['last_send_fail_file'] = ''; // Reset daily fail stats.
		self::reset_send_attempts(); // Reset 't' key for all items in catalog so we can try again. NOTE: Also saves state and catalog.

		self::$_state['stats']['wait_on_transfers_start'] = 0; // Reset daily time we started waiting on unfinished transfers.
		self::_request_kick_cron();

		// Truncate log if it is getting too large. Keeps newest half.
		self::_truncate_log();

		// Backup catalog file.
		self::backup_catalog();

		/* Removed Mar 20, 2017
		if ( false === self::_load_state() ) {
			return false;
		}
		*/

		if ( true === $manual_snapshot ) {
			self::_request_manual_snapshot();
		} else {
			self::_request_manual_snapshot( $cancel = true );
		}

		return true;

	} // End _step_daily_init().


	private static function _request_manual_snapshot( $cancel = false ) {
		if ( false === self::_load_state() ) {
			return false;
		}

		pb_backupbuddy::status( 'details', 'Manual snapshot set for end of this pass.' );

		if ( true === $cancel ) { // Cancel snapshot.
			self::$_state['stats']['manual_snapshot'] = false;
		} else { // Request snapshot.
			self::$_state['stats']['manual_snapshot'] = microtime( true );
		}
	} // End _request_manual_snapshot().


	/* _step_send_pending_db_snapshots()
	 *
	 * Finds the next pending database snapshot file and tries to send it.
	 *
	 */
	private static function _step_send_pending_db_snapshots( $startAt = 0 ) {

		// Load state into self::$_state & fileoptions object into self::$_stateObj.
		if ( false === self::_load_state() ) {
			return false;
		}
		if ( false === self::_load_tables() ) {
			return false;
		}
		if ( 0 != $startAt ) {
			pb_backupbuddy::status( 'details', 'Resuming snapshot send at point `' . $startAt . '`.' );
		}


		require_once( pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php' );
		backupbuddy_live::update_db_live_activity_time();

		// On first pass create and send backupbuddy_dat.php and importbuddy.php.
		if ( 0 == $startAt ) {
			// Render backupbuddy_dat.php
			$dat_file = backupbuddy_live::getLiveDatabaseSnapshotDir() . 'backupbuddy_dat.php';

			// Make sure directory exists.
			if ( ! file_exists( dirname( $dat_file ) ) ) {
				if ( false === pb_backupbuddy_filesystem::mkdir( dirname( $dat_file ) ) ) {
					pb_backupbuddy::status( 'warning', 'Warning #34893498434: Unable to mkdir("' . $dat_file . '").' );
				}
			}

			$tableSizes = array();
			foreach( self::$_tables as $tableName => $table ) {
				$tableSizes[$tableName] = $table['s'];
			}

			$table_results = backupbuddy_live::_calculate_table_includes_excludes_basedump();

			$dat_settings = array(
				'backup_type'			=> 'live',
				'profile'				=> array(),
				'serial'				=> '',
				'breakout_tables'		=> backupbuddy_live::calculateTables(),
				'table_sizes'			=> $tableSizes,
				'force_single_db_file'	=> false,
				'trigger'				=> 'live',
				'db_excludes'			=> $table_results[1],
				'db_includes'			=> $table_results[0],
			);


			pb_backupbuddy::status( 'details', 'Rendering DAT file to `' . $dat_file . '`.' );
			if ( ! is_array( backupbuddy_core::render_dat_contents( $dat_settings, $dat_file ) ) ) {
				$error = 'Error #47949743: Since DAT file could not be written aborting. Check permissions writing to `' . $dat_file . '`.';
				pb_backupbuddy::status( 'error', $error );
				return $error;
			}


			// Render importbuddy.php
			$importbuddy_file = backupbuddy_live::getLiveDatabaseSnapshotDir() . 'importbuddy.php';
			pb_backupbuddy::status( 'details', 'Rendering importbuddy file to `' . $importbuddy_file . '`.' );
			if ( false === backupbuddy_core::importbuddy( $importbuddy_file, $pass = NULL ) ) { // NULL pass leaves #PASSWORD# placeholder in place.
				pb_backupbuddy::status( 'warning', 'Warning #348438345: Unable to render importbuddy. Not backing up importbuddy.php.' );
			}

			// Load destination settings.
			$destination_settings = self::get_destination_settings();

			// Send DAT file.
			$send_id = 'live_' . md5( $dat_file ) . '-' . pb_backupbuddy::random_string( 6 );
			$destination_settings['_database_table'] = 'backupbuddy_dat.php';
			if ( false === pb_backupbuddy_destinations::send( $destination_settings, $dat_file, $send_id, $delete_after = true, $isRetry = false, $trigger = 'live_periodic', $destination_id = backupbuddy_live::getLiveID() ) ) {
				$error = 'Error #389398: Unable to send DAT file to Live servers. See error log above for details.';
				pb_backupbuddy::status( 'error', $error );
				backupbuddy_core::addNotification( 'live_error', 'BackupBuddy Stash Live Error', $error );
			}

			// Send importbuddy.
			$send_id = 'live_' . md5( $importbuddy_file ) . '-' . pb_backupbuddy::random_string( 6 );
			$destination_settings['_database_table'] = 'importbuddy.php';
			if ( false === pb_backupbuddy_destinations::send( $destination_settings, $importbuddy_file, $send_id, $delete_after = true, $isRetry = false, $trigger = 'live_periodic', $destination_id = backupbuddy_live::getLiveID() ) ) {
				pb_backupbuddy::status( 'error', 'Error #329327: Unable to send importbuddy file to Live servers. See error log above for details.' );
			}
		}


		// Loop through tables in the catalog.
		$loopCount = 0;
		$checkCount = 0;
		$sendTimeSum = 0;
		$sendSizeSum = 0;
		$sendsStarted = 0;
		$sendsSucceeded = 0;
		$sendsMultiparted = 0;
		$sendsFailed = 0;
		$alreadyBackedUp = 0;
		$tooManySendFails = 0;
		$lastSendThisPass = false;
		$sendMoreRemain = false;
		foreach( self::$_tables as $table => &$tableDetails ) {
			$loopCount++;
			if ( 0 != $startAt ) { // Resuming...
				if ( $loopCount < $startAt ) {
					continue;
				}
			}
			$checkCount++;

			// If Live is disabled, we need to break this loop. Query the DB every 10 ints to make sure live still exists
			if ( substr( $loopCount, -1 ) == 0 ) {
				wp_cache_delete( 'pb_backupbuddy', 'options' );
				$bub_options      = get_option('pb_backupbuddy');
				$bub_destinations = empty( $bub_options['remote_destinations'] ) ? array() : $bub_options['remote_destinations'];
				$live_still_exists = false;
				foreach( $bub_destinations as $bub_dest ) {
					if ( $bub_dest['type'] == 'live' ) {
						$live_still_exists = true;
					}
				}
				if ( empty( $live_still_exists ) ) {
					backupbuddy_core::clearLiveLogs( pb_backupbuddy::$options['log_serial'] );
					return false;
				}
			}

			// If backed up after modified time then it's up to date. Skip.
			if ( $tableDetails['b'] > $tableDetails['m'] ) {
				pb_backupbuddy::status( 'details', 'Skipping send of table `' . $table . '` because it has already been sent since SQL file was made.' );
				$alreadyBackedUp++;
				continue;
			}

			// Calculate table file.
			$tableFile = backupbuddy_live::getLiveDatabaseSnapshotDir() . $table . '.sql';

			// If too many attempts have passed then skip.
			if ( $tableDetails['t'] >= self::MAX_SEND_ATTEMPTS ) {
				pb_backupbuddy::status( 'error', 'Error #389328: This database file has failed transfer too many times. Skipping until next restart of periodic proces. File: `' . $tableFile . '`. Size: `' . pb_backupbuddy::$format->file_size(  filesize( $tableFile ) ) . '`.' );
				$tooManySendFails++;
				continue;
			}

			// Load destination settings.
			$destination_settings = self::get_destination_settings();

			// If too many remote sends have failed today then give up for now since something is likely wrong.
			if ( self::$_state['stats']['recent_send_fails'] > $destination_settings['max_daily_failures'] ) {
				$error = 'Error #4937743: Too many file transfer failures have occurred. Halting sends for today.';
				backupbuddy_core::addNotification( 'live_error', 'BackupBuddy Stash Live Error', $error );
				self::$_state['step']['last_status'] =  $error;
				pb_backupbuddy::status( 'error', $error );
				return false;
			}

			// If this is not the first file we've sent this pass, see if we have enough time for more.
			if ( $sendSizeSum > 0 ) {
				// Check if it appears we have enough time to send at least a full single chunk in this pass or if we need to pass off to a subsequent run.
				$send_speed = ( $sendSizeSum / 1048576 ) / $sendTimeSum; // Estimated speed at which we can send files out. Unit: MB / sec.
				$time_elapsed = ( microtime( true ) - pb_backupbuddy::$start_time );
				$time_remaining = $destination_settings['_max_time'] - ( $time_elapsed + self::TIME_WIGGLE_ROOM ); // Estimated time remaining before PHP times out. Unit: seconds.
				$size_possible_with_remaining_time = $send_speed * $time_remaining; // Size possible to send with remaining time (takes into account wiggle room).

				$size_to_send = ( $tableDetails['s'] / 1048576 ); // Size we want to send this pass. Unit: MB.
				if ( $destination_settings['max_burst'] < $size_to_send ) { // If the chunksize is smaller than the full file then cap at sending that much.
					$size_to_send = $destination_settings['max_burst'];
				}

				if ( ( $size_possible_with_remaining_time < $size_to_send ) ) { // File (or chunk) is bigger than what we have time to send.
					$lastSendThisPass = true;
					$sendMoreRemain = true;
					$send_speed_status = 'Not enough time to send more. To continue in next live_periodic pass.';
				} else {
					$send_speed_status = 'Enough time to send more. Preparing for send.';
				}

				pb_backupbuddy::status ('details', 'Not the first DB file to send this pass. Send speed: `' . $send_speed . '` MB/sec. Time elapsed: `' . $time_elapsed . '` sec. Time remaining: `' . $time_remaining . '` sec based on adjusted max time of `' . $destination_settings['_max_time'] . '` sec with wiggle room `' . self::TIME_WIGGLE_ROOM . '` sec. Size possible with remaining time: `' . $size_possible_with_remaining_time . '` MB. Size to chunk (greater of filesize or chunk): `' . $size_to_send . '` MB. Conclusion: `' . $send_speed_status . '`.' );
			} // end subsequent send time check.

			// NOT out of time so send this.
			if ( true !== $lastSendThisPass ) {
				// Run cleanup on send files.
				require_once( pb_backupbuddy::plugin_path() . '/classes/housekeeping.php' );
				backupbuddy_housekeeping::trim_remote_send_stats( $file_prefix = 'send-live_', $limit = $destination_settings['max_send_details_limit'], '', $purge_log = true ); // Only keep last X send fileoptions.
				// Moved to trim_remote_send_stats(). backupbuddy_housekeeping::purge_logs( $file_prefix = 'status-remote_send-live_', $limit = $destination_settings['max_send_details_limit'] ); // Only keep last X send logs.

				// Increment try count for transfer attempts and save.
				$tableDetails['t']++;
				if ( is_string( self::$_tablesObj ) ) { // Somehow this is sometimes a string. Try to reload.
					self::_load_tables();
				}
				self::$_tablesObj->save();

				// Send file. AFTER success sending this Stash2/Stash3 destination will automatically trigger the live_periodic processing _IF_ multipart send. If success or fail the we come back here to potentially send more files in the same PHP pass so small files don't each need their own PHP page run.  Unless the process has restarted then this will still be the 'next' function to run.
				$send_id = 'live_' . md5( $tableFile ) . '-' . pb_backupbuddy::random_string( 6 );
				pb_backupbuddy::status( 'details', 'Live starting send function.' );
				$sendTimeStart = microtime( true );

				// Mark table as unsent just before sending new version.
				$tableDetails['b'] = 0;

				// Close catalog & state while sending if > X size to prevent collisions.
				if ( $tableDetails['s'] > self::CLOSE_CATALOG_WHEN_SENDING_FILESIZE ) {
					self::$_tablesObj = '';
					self::$_stateObj = '';
				}

				// Set database table name into settings so send confirmation knows where to update sent timestamp.
				$destination_settings['_database_table'] = $table;

				// Send file to remote.
				$sendsStarted++;
				$result = pb_backupbuddy_destinations::send( $destination_settings, $tableFile, $send_id, $delete_after = true, $isRetry = false, $trigger = 'live_periodic', $destination_id = backupbuddy_live::getLiveID() );

				// Re-open catalog (if closed).
				self::_load_tables();
				self::_load_state();

				$sendTimeFinish = microtime( true );
				if ( true === $result ) {
					$sendsSucceeded++;
					$result_status = 'Success sending in single pass.';
					$sendTimeSum += ( $sendTimeFinish - $sendTimeStart ); // Add to time sent sending.

					// Set a minimum threshold so small files don't make server appear slower than reality due to overhead.
					$minimum_size_threshold = self::MINIMUM_SIZE_THRESHOLD_FOR_SPEED_CALC; // Pretend file is at least 500k each.
					if ( $tableDetails['s'] < $minimum_size_threshold ) {
						$sendSizeSum += $minimum_size_threshold;
					} else {
						$sendSizeSum += $tableDetails['s']; // Add to size of data sent.
					}
				} elseif ( false === $result ) {
					$sendsFailed++;
					self::$_state['stats']['recent_send_fails']++;
					self::$_state['stats']['last_send_fail'] = time();
					self::$_state['stats']['last_send_fail_file'] = $tableFile;
					$result_status = 'Failure sending in single/first pass. See log above for error details. Failed sends today: `' . self::$_state['stats']['recent_send_fails'] . '`.';
				} elseif ( is_array( $result ) ) {
					$sendsMultiparted++;
					$result_status = 'Chunking commenced. Ending sends for this pass.';
					$lastSendThisPass = true;
				}
				pb_backupbuddy::status( 'details', 'Live ended send database function. Status: ' . $result_status . '.' );
			}

			// Check if we are done sending for this PHP pass/run.
			if ( true === $lastSendThisPass ) {
				break;
			}
		} // End foreach signatures.

		pb_backupbuddy::status( 'details', 'Snapshot send details for this round: Checked `' . $loopCount . '` tables, transfers started: `' . $sendsStarted . '`, transfers succeeded: `' . $sendsSucceeded . '`, transfers multiparted: `' . $sendsMultiparted . '`, transfers failed: `' . $sendsFailed . '`, skipped because already backed up: `' . $alreadyBackedUp . '`, skipped because too many send failures: `' . $tooManySendFails . '`.' );

		// Schedule next run if we still have more files to potentially send.
		if ( true === $sendMoreRemain ) {
			return array( 'Sending queued tables', array( $loopCount ) );
		} else { // No more files.
			return true;
		}

	} // End _step_send_pending_db_snapshots().



	/* _step_process_table_deletions()
	 *
	 * Cleans up database table deletions.
	 *
	 */
	private static function _step_process_table_deletions( $startAt = 0 ) {
		$start_time = microtime( true );
		if ( false === self::_load_tables() ) {
			return false;
		}
		if ( false === self::_load_state() ) {
			return false;
		}
		pb_backupbuddy::status( 'details', 'Starting table deletions at point: `' . $startAt . '`.' );
		backupbuddy_live::update_db_live_activity_time();

		// Instruct Live server to delete all timestamped SQL files (in wp-content/uploads/backupbuddy_temp/SERIAL/_XXXXXXX.XX-tablename) with timestamps in the filename older than the one passed.
		if ( 0 == $startAt ) {
			$timestamp = self::$_state['stats']['last_db_snapshot'];
			$destination_settings = self::get_destination_settings();
			$additionalParams = array(
				'timestamp' => $timestamp,
				'test'      => false,
			);
			require_once( pb_backupbuddy::plugin_path() . '/destinations/live/init.php' );
			$response = pb_backupbuddy_destination_live::stashAPI( $destination_settings, 'live-cleanup', $additionalParams );
			if ( ! is_array( $response ) ) {
				$error = 'Error #34387595: Unable to initiate Live timestamped database cleanup prior to timestamp `' . $timestamp . '`. Details: `' . $response . '`. Continuing anyway...';
				pb_backupbuddy::status( 'error', $error );
				//return false;
			} else {
				pb_backupbuddy::status( 'details', 'Deleted `' . count( $response['files'] ) . '` total timestamped live database files older than timestamp `' . $timestamp . '`.' );
				if ( pb_backupbuddy::$options['log_level'] == '3' ) { // Full logging enabled.
					pb_backupbuddy::status( 'details', 'live-cleanup response due to logging level: `' . print_r( $response, true ) . '`. Call params: `' . print_r( $additionalParams, true ) . ' `.' );
				}
			}
		}


		// Loop through tables in the catalog.
		$loopCount = 0;
		$checkCount = 0;
		$tablesDeleted = 0;
		$last_save = microtime( true );
		foreach( self::$_tables as $table => &$tableDetails ) {
			if ( 0 != $startAt ) { // Resuming...
				if ( $loopCount < $startAt ) {
					$loopCount++;
					continue;
				}
			} else {
				$loopCount++;
			}
			$checkCount++;

			// Skip non-deleted files.
			if ( true !== $tableDetails['d'] ) {
				continue;
			}

			// Made it here then we will be deleting a table file.

			// Cancel any in-process remote sends of deleted files.
			$sendFileoptions = glob( backupbuddy_core::getLogDirectory() . 'fileoptions/send-live_' . md5( $table ) . '-*.txt' );
			if ( ! is_array( $sendFileoptions ) ) {
				$sendFileoptions  = array();
			}
			foreach( $sendFileoptions as $sendFileoption ) {
				$fileoptions_obj = new pb_backupbuddy_fileoptions( $sendFileoption, $read_only = false, $ignore_lock = false, $create_file = false );
				if ( true !== ( $result = $fileoptions_obj->is_ok() ) ) {
					pb_backupbuddy::status( 'error', __('Fatal Error #9034.328237. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
					return false;
				}
				// Something wrong with fileoptions. Let cleanup handle it later.
				if ( ! isset( $fileoptions_obj->options['status'] ) ) {
					continue;
				}
				// Don't do anything for success, failure, or already-marked as -1 finish time.
				if ( ( 'success' == $fileoptions_obj->options['status'] ) || ( 'failure' == $fileoptions_obj->options['status'] ) || ( -1 == $fileoptions_obj->options['finish_time'] ) ) {
					continue;
				}
				// Cancel this send.
				$fileoptions_obj->options['finish_time'] = -1;
				$fileoptions_obj->save();
				pb_backupbuddy::status( 'details', 'Cancelled in-progress send of deleted table `' . $table . '`.' );
				unset( $fileoptions_obj );
			}

			// If file has been backed up to server then we need to delete the remote file.
			if ( 0 != $tableDetails['b'] ) {
				$destination_settings = self::get_destination_settings();
				$deleteFile = 'wp-content/uploads/backupbuddy_temp/SERIAL/' . $table . '.sql';
				if ( true !== ( $delete_result = pb_backupbuddy_destination_live::deleteFile( $destination_settings, $deleteFile ) ) ) {
					pb_backupbuddy::status( 'error', 'Error #8239833: Unable to delete remote table file `' . $deleteFile . '`' );
				} elseif ( true === $delete_result ) {
					pb_backupbuddy::status( 'details', 'Deleted remote table `' . $table . '`.' );
				}
			}

			// Remove file from catalog and update state stats.
			unset( self::$_tables[ $table ] );
			self::$_state['stats']['tables_pending_delete']--;
			if ( self::$_state['stats']['tables_pending_delete'] < 0 ) {
				self::$_state['stats']['tables_pending_delete'] = 0;
			}
			$tablesDeleted++;

			// See if it's time to save our progress so far.
			if ( ( time() - $last_save ) > self::SAVE_SIGNATURES_EVERY_X_SECONDS ) {
				self::$_stateObj->save();
				self::$_tablesObj->save();
				$last_save = microtime( true );
			}

			// Do we have enough time to continue or do we need to chunk?
			if ( ( microtime( true ) - $start_time + self::TIME_WIGGLE_ROOM )  > $destination_settings['_max_time'] ) { // Running out of time! Chunk.
				self::$_tablesObj->save();
				pb_backupbuddy::status( 'details', 'Running out of time processing table deletions. Took `' . ( microtime( true ) - $start_time ) . '` seconds.' );
				return array( 'Processing deletions', array( $loopCount-$tablesDeleted ) );
			}

		} // end foreach.

		// Save and finish.
		self::$_stateObj->save();
		self::$_tablesObj->save();
		pb_backupbuddy::status( 'details', 'Database table deletions processed. Checked `' . $checkCount . '` files. Deleted `' . $tablesDeleted . '` files. Took `' . ( microtime( true ) - $start_time ) . '` seconds.' );
		return true;

	} // End _step_process_table_deletions().



	/* _step_database_snapshot()
	 *
	 * Creates a snapshot of the database based on global database setting defaults.
	 *
	 * @param	array 	$tables			Array of tables to back up. When chunking dumped tables will be removed from this list (from the front).
	 * @param	int		$rows_start		Row number to resume at for the first table in $tables.
	 */
	private static function _step_database_snapshot( $tables = array(), $chunkTables = array(), $rows_start = 0 ) {

		if ( false === self::_load_state() ) {
			return false;
		}
		if ( false === self::_load_tables() ) {
			return false;
		}

		backupbuddy_live::update_db_live_activity_time();

		// Databse snapshot storage directory. Includes trailing slash.
		$directory = backupbuddy_live::getLiveDatabaseSnapshotDir();

		pb_backupbuddy::status( 'message', __('Starting database snapshot procedure.', 'it-l10n-backupbuddy' ) );

		if ( 0 == count( $chunkTables ) ) { // First pass.
			// Delete any existing db snapshots stored locally.
			$snapshots = glob( $directory . '*.sql' );
			pb_backupbuddy::status( 'details', 'Found `' . count( $snapshots ) . '` total existing local SQL files to delete from temporary dump directory `' . $directory . '`.' );
			foreach( $snapshots as $snapshot ) {
				@unlink( $snapshot );
			}

			$tables = backupbuddy_live::calculateTables();
			$chunkTables = $tables;
		} else { // Resuming chunking.
			pb_backupbuddy::status( 'details', '`' . count( $chunkTables ) . '` tables left to dump.' );
		}

		pb_backupbuddy::status( 'details', 'Tables: `' . print_r( $tables, true ) . '`, chunkTables: `' . print_r( $chunkTables, true ) . '`, Rows_Start: `' . print_r( $rows_start, true ) . '`.' );


		if ( 'php' == pb_backupbuddy::$options['database_method_strategy'] ) {
			$force_methods = array( 'php' );
		} elseif ( 'commandline' == pb_backupbuddy::$options['database_method_strategy'] ) {
			$force_methods = array( 'commandline' );
		} elseif ( 'all' == pb_backupbuddy::$options['database_method_strategy'] ) {
			$force_methods = array( 'php', 'commandline' );
		} else {
			pb_backupbuddy::status( 'error', 'Error #95432: Invalid forced database dump method setting: `' . pb_backupbuddy::$options['database_method_strategy'] . '`.' );
			return false;
		}

		$destination_settings = self::get_destination_settings();
		$maxExecution = $destination_settings['_max_time'];

		// Load mysqlbuddy and perform dump.
		pb_backupbuddy::status( 'details', 'Loading mysqlbuddy.' );
		require_once( pb_backupbuddy::plugin_path() . '/lib/mysqlbuddy/mysqlbuddy.php' );
		global $wpdb;
		pb_backupbuddy::$classes['mysqlbuddy'] = new pb_backupbuddy_mysqlbuddy( DB_HOST, DB_NAME, DB_USER, DB_PASSWORD, $wpdb->prefix, $force_methods, $maxExecution ); // $database_host, $database_name, $database_user, $database_pass, $old_prefix, $force_method = array()

		// Prepare destination snapshot sql files directory.
		pb_backupbuddy::status( 'details', 'Creating dump directory.' );
		$mode = apply_filters( 'itbub-default-file-mode', 0755 );
		if ( pb_backupbuddy::$filesystem->mkdir( $directory, $mode, $recurse = true ) === false ) {
			$error = 'Error #387974: BackupBuddy unable to create directory `' . $directory . '`. Please verify write permissions for the parent directory `' . dirname( $directory ) . '` or manually create the specified directory & set permissions.';
		}

		// Do the database dump.
		$result = pb_backupbuddy::$classes['mysqlbuddy']->dump( $directory, $chunkTables, $rows_start ); // if array, returns tables,rowstart

		// Process dump result.
		if ( is_array( $result ) ) { // Chunking.

			return array( 'Creating database snapshot', array( $tables, $result[0], $result[1] ) ); // Full table list, remaining tables, row to resume at.

		} else { // Should be either true (success) or false (fail).

			if ( true === $result ) { // Success.
				pb_backupbuddy::status( 'details', 'Database dump fully completed. Calculating database stats.' );

				// Set last snapshot time.
				self::$_state['stats']['last_db_snapshot'] = microtime( true ); // Timestamp snapshot completed. Used to delete live sql updates prior to this timestamp.

				// Get info on tables.
				$table_details = $wpdb->get_results( "SELECT table_name AS `table_name`, data_length AS `data_length`, index_length AS `index_length` FROM information_schema.tables WHERE table_schema = DATABASE()", ARRAY_A );
				$table_sizes = array();
				foreach( $table_details as $table_detail ) {
					$table_sizes[ $table_detail['table_name'] ] = $table_detail['data_length'] + $table_detail['index_length'];
				}
				unset( $table_details );

				// Add any new tables to catalog listing.
				$database_size = 0;
				foreach( $tables as $table ) {

					// Table is not yet in the catalog.
					if ( ! isset( self::$_tables[ $table ] ) ) {
						self::$_tables[ $table ] = self::$_tableDefaults; // Apply defaults.
						self::$_tables[ $table ]['m'] = self::$_state['stats']['last_db_snapshot'];
					} else { // Table already in catalog. Update it.
						self::$_tables[ $table ] = array_merge( self::$_tableDefaults, self::$_tables[ $table ] ); // Apply defaults to existing data.
						self::$_tables[ $table ]['m'] = self::$_state['stats']['last_db_snapshot'];
					}

					// Set size if we have calculated it (if it was available to us).
					if ( isset( $table_sizes[ $table ] ) ) {
						self::$_tables[ $table ]['s'] = $table_sizes[ $table ];
						$database_size += $table_sizes[ $table ];
					}

					// Reset try attempts.
					self::$_tables[ $table ]['t'] = 0;
				} // end foreach.

				// Mark any removed tables as needing deletion in catalog listing. Handles tables that no longer exist or are excluded.
				if ( count( $table_sizes ) > 0 ) { // If we were able to get table listings.
					foreach( self::$_tables as $catalogTableName => $catalogTable ) { // Iterate through stored tables in catalog.
						if ( ( ! isset( $table_sizes[ $catalogTableName ] ) ) || ( ! in_array( $catalogTableName, $tables ) ) ) { // Backed up table is no longer in mysql db OR was not in list of tables to backup (eg is now excluded).
							// If table was already sent, mark for deletion. Else just remove entirely here.
							if ( 0 != self::$_tables[ $catalogTableName ]['b'] ) { // Already backed up to server.
								self::$_tables[ $catalogTableName ]['d'] = true; // Mark for deletion.
								self::$_state['stats']['tables_pending_delete']++;
							} else { // Remove outright here.
								unset( self::$_tables[ $catalogTableName ] );
							}
						}
					}
				}
				self::$_state['stats']['tables_total_size'] = $database_size;
				self::$_state['stats']['tables_total_count'] = count( $tables );
				self::$_state['stats']['tables_pending_send'] = count( $tables );

				// Save catalog.
				self::$_stateObj->save();
				self::$_tablesObj->save();

				return true;

			} elseif ( false === $result ) {
				$error = 'Error #8349434: Live unable to dump database. See log for details.';
				pb_backupbuddy::status( 'error', $error );
				backupbuddy_core::addNotification( 'live_error', 'BackupBuddy Stash Live Error', $error );
				self::$_state['step']['last_status'] = $error;
				return false;
			} else {
				$error = 'Error #398349734: Live unexpected database dump response. See log for details.';
				pb_backupbuddy::status( 'error', $error );
				backupbuddy_core::addNotification( 'live_error', 'BackupBuddy Stash Live Error', $error );
				self::$_state['step']['last_status'] = $error;
				return false;
			}

		} // end if non-chunking.

	} // End _step_database_snapshot().



	/* getFullExcludes()
	 *
	 * Get the full list of exclusions, including all defaults, global, Stash Live, etc all combines and uniqued.
	 *
	 */
	public static function getFullExcludes() {
		// Get Live-specific excludes.
		$excludes = backupbuddy_live::getOption( 'file_excludes', true );

		// Add standard BB excludes we always apply.
		$excludes = array_unique( array_merge(
			self::$_default_excludes,
			backupbuddy_core::get_directory_exclusions( pb_backupbuddy::$options['profiles'][0], $trim_suffix = false, $serial = '' ),
			backupbuddy_core::get_directory_exclusions( array( 'excludes' => $excludes ), $trim_suffix = false, $serial = '' )
		) );

		return $excludes;
	}


	/* _update_files_list()
	 *
	 * Generate list of files to add/update/delete.
	 *
	 * @param	string	$custom_root	Custom root to start scan from. IMPORTANT: This MUST be WITHIN(below) the ABSPATH directory. It cannot be higher (eg parent of ABSPATH). Trailing slash optional.
	 */
	private static function _step_update_files_list( $custom_root = '', $startAt = 0, $items = array() ) {

		$start_time = microtime( true );
		pb_backupbuddy::status( 'details', 'Starting to process files; updating files list.' );

		if ( false === self::_load_catalog() ) {
			return false;
		}

		if ( '' != $custom_root ) {
			pb_backupbuddy::status( 'details', 'Scanning custom directory: `' . $custom_root . '`.' );
			sleep( 3 ); // Give WordPress time to make thumbnails, etc.
		}

		if ( ( 0 == $startAt ) && ( '' == $custom_root ) ) { // Reset stats when starting from the beginning of a full file scan (not for custom roots).
			self::$_state['stats']['files_pending_delete'] = 0;
			self::$_state['stats']['files_pending_send'] = 0;
			self::$_state['stats']['files_total_count'] = 0;
			self::$_state['stats']['files_total_size'] = 0;
		}

		$excludes = self::getFullExcludes();
		pb_backupbuddy::status( 'details', 'Excluding directories: `' . implode( ', ', $excludes ) . '`.' );

		// Generate list of files.
		if ( '' != $custom_root ) {
			$root = $custom_root;
		} else {
			$root = ABSPATH;
		}
		$root = rtrim( $root, '/\\' ); // Make sure no trailing slash.
		$root_len = strlen( $root );

		$custom_root_diff = '';
		if ( '' != $custom_root ) {
			$custom_root_diff = substr( $root, strlen( ABSPATH )-1 );
		}

		$destination_settings = self::get_destination_settings();
		pb_backupbuddy::status( 'details', 'Starting deep file scan.' );
		$max_time = $destination_settings['_max_time'] - self::TIME_WIGGLE_ROOM;
		$adjusted_max_time = ( $max_time - 8 ); // Additional 5 seconds so that we can add files into catalog after this completes.
		if ( $adjusted_max_time < 5 ) {
			pb_backupbuddy::status( 'error', 'Error #3893983: Adjusted max execution time minus wiggle room fell below 5 second thresold. Bumped to 5 seconds. Stash Live max execution time limit is either too low or host PHP max execution time limit is far too low. Suggest 30 second minimum. Final adjusted value: `' . $adjusted_max_time . '`.' );
			$adjusted_max_time = 5;
		}
		$files = pb_backupbuddy::$filesystem->deepscandir( $root, $excludes, $startAt, $items, $start_time, $adjusted_max_time );
		if ( ! is_array( $files ) ) {
			backupbuddy_core::addNotification( 'live_error', 'BackupBuddy Stash Live Error', $files );
			pb_backupbuddy::status( 'error', 'Error #84393434: Halting Stash Live due to error returned by deepscandir: `' . $files . '`.' );
			return $files;
		}
		if ( false === $files[0] ) { // Format when chunking: array( $finished = false, array( $startAt, $items ) )
			pb_backupbuddy::status( 'details', 'Deep file scan requires chunking.' );
			$startAtNext = 0;
			if ( isset( $files[1][0] ) ) {
				$startAtNext = $files[1][0];
			}
			return array( 'File scanning', array( $custom_root, $startAtNext, $files[1][1] ) );
		} else {
			pb_backupbuddy::status( 'details', 'Deep file scan complete.' );
		}

		// Remove root from path AND remote directories..
		foreach( $files as $i => &$file ) {
			if ( is_dir( $file ) ) { // Don't track directories, only actual files.
				unset( $files[$i] );
				continue 1;
			}
			$file = substr( $file, $root_len );
		}

		// Flip array.
		$files = array_flip( $files );

		// Check if this file is already in the list or not.
		$filesAdded = 0;

		//$addedSinceOutput = 0;
		//$outputEvery = 20; // Log every X number of files added into catalog.

		foreach( $files as $file => $ignoreID ) {
			if ( '' == $custom_root ) { // Only increment existing files if scanning from root (because stats were reset for fresh count).
				self::$_state['stats']['files_total_count']++;
			}
			$pathed_file = $custom_root_diff . $file; // Applies custom root portion if applicable.

			if ( ! isset( self::$_catalog[ $pathed_file ] ) ) { // File not already in signature list. Add it in with initial values.
				if ( '' != $custom_root ) { // Was not added earlier yet.
					self::$_state['stats']['files_total_count']++;
				}
				self::$_catalog[ $pathed_file ] = self::$_signatureDefaults;
				$filesAdded++;
				self::$_state['stats']['files_pending_send']++;

				//$addedSinceOutput++;

				/*
				if ( ( pb_backupbuddy::$options['log_level'] == '3' ) || ( $addedSinceOutput > $outputEvery ) ) { // Full logging enabled.
					pb_backupbuddy::status( 'details', 'Added `' . $addedSinceOutput . '` more files. Last file: `' . $pathed_file . '`.' );
					if ( $addedSinceOutput > $outputEvery ) {
						$addedSinceOutput = 0;
					}
				}
				*/
				if ( $filesAdded % 2000 == 0 ) {
					self::$_state['stats']['files_to_catalog_percentage'] = round( number_format( ($filesAdded / count($files) ) * 100, 2) );
					self::$_stateObj->save();
				}
				if ( '3' == pb_backupbuddy::$options['log_level'] ) { // Full logging enabled.
					pb_backupbuddy::status( 'details', 'Add to catalog: `' . $pathed_file . '`.' );
				}
			} else { // Already exists in catalog.
				if ( 0 == self::$_catalog[ $pathed_file ]['b'] ) { // File not backed up to server yet (pending send).
					if ( !isset( self::$_catalog[ $pathed_file ]['d'] ) || ( true !== self::$_catalog[ $pathed_file ]['d'] ) ) { // Not pending deletion already.
						if ( '' == $custom_root ) { // Only increment existing files if scanning from root (because stats were reset for fresh count).
							self::$_state['stats']['files_pending_send']++;
						}
					}
				} else { // Local file already exists in catalog and on server. Make sure not marked for deletion.
					if ( isset( self::$_catalog[ $pathed_file ]['d'] ) && ( true === self::$_catalog[ $pathed_file ]['d'] ) ) { // Was marked to delete. Remove deltion mark BUT do rescan in case this is a new version of the file since it was for some reason marked to delete.
						unset( self::$_catalog[ $pathed_file ]['d'] ); // Don't immediately delete. (unset to save mem)
						self::$_catalog[ $pathed_file ]['r'] = 0; // Reset last scan time so it gets re-checked.
					}
				}
			}
		}


		// Checking existing catalog files with new scan to see if anything needs deletion.
		$filesDeleted = 0;
		//$sinceLogTrim = 0;
		foreach( self::$_catalog as $signatureFile => &$signatureDetails ) {
			if ( isset( $signatureDetails['d'] ) && ( true === $signatureDetails['d'] ) ) { // Already marked for deletion.
				continue;
			}
			if ( '' != $custom_root ) { // Custom root. Ignore removing any files not within the custom root since we did not scan those so they are not in the $files array.
				if ( $root != substr( $signatureFile, 0, $root_len ) ) { // Beginning of filename does not match root so not applicable for this scan. Skip.
					continue;
				}
			}
			if ( ! isset( $files[ $signatureFile ] ) ) { // File no longer exists in new scan. Mark for deletion.
				//$sinceLogTrim++;
				$filesDeleted++;
				$signatureDetails['d'] = true;
				self::$_state['stats']['files_pending_delete']++;
				// If it was not yet backed up, decrease pending count.
				if ( 0 == $signatureDetails['b'] ) {
					self::$_state['stats']['files_pending_send']--;
					if ( self::$_state['stats']['files_pending_send'] < 0 ) {
						self::$_state['stats']['files_pending_send'] = 0;
					}
				}
				self::$_state['stats']['files_total_count']--;
				if ( self::$_state['stats']['files_total_count'] < 0 ) {
					self::$_state['stats']['files_total_count'] = 0;
				}
				pb_backupbuddy::status( 'details', 'Remove file that no longer exists locally. Flagging `' . $signatureFile . '` for deletion.' );
				/*
				if ( $sinceLogTrim > 1000 ) {
					$sinceLogTrim = 0;
					self::$_catalogObj->save(); // In case it dies.
					self::_truncate_log();
				}
				*/
			}
		}

		self::$_catalogObj->save();
		pb_backupbuddy::status( 'details', 'Signatures saved. Added `' . $filesAdded++ . '` files to local catalog. Marked `' . $filesDeleted . '` files deleted. Took `' . ( microtime( true ) - $start_time ) . '` seconds.' );

		return true;
	} // End _process_files_update_files_list().



	/* _step_update_files_signatures()
	 *
	 * Steps through all the files in the catalog, calculating signatures such as modified time, size, etc.
	 *
	 */
	private static function _step_update_files_signatures( $startAt = 0 ) {

		$start_time = microtime( true );
		if ( false === self::_load_catalog() ) {
			return false;
		}
		if ( 0 == $startAt ) {
			self::$_state['stats']['files_total_size'] = 0; // Reset total sum for upcoming scan.
		}

		// Clear stale stat cache so file modified/size/etc are very up to date.
		pb_backupbuddy::status( 'details', 'Cleaning stat cache for signature scan.' );
		clearstatcache();

		// Loop through files in the catalog.
		$filesUpdated = 0;
		$filesDeleted = 0;
		$alreadySentFilesDetectedChanged = 0;
		$filesNeedingResendFromIffyAudit = 0;
		$filesDetectedChanged = 0;
		$loopCount = 0;
		$last_save = microtime( true );
		foreach( self::$_catalog as $signatureFile => &$signatureDetails ) {
			if ( 0 != $startAt ) { // Resuming...
				if ( $loopCount < $startAt ) {
					$loopCount++;
					continue;
				}
			} else {
				$loopCount++;
			}

			// Check if file is already set to delete.
			if ( isset( $signatureDetails['d'] ) && ( true === $signatureDetails['d'] ) ) { // File already set to delete. Skip.
				continue; // Skip to next file.
			}

			// Sum sizes for any file that is not marked for deletion. Files that were just deleted will be substracted below. If zero then we will apply size below.
			if ( 0 != $signatureDetails['s'] ) {
				self::$_state['stats']['files_total_size'] += $signatureDetails['s'];
			}

			// Check if enough time has passed since last rescan.
			if ( ( time() - $signatureDetails['r'] ) < self::TIME_BETWEEN_FILE_RESCAN ) {
				continue;
			}

			// If file not marked for deletion, check if it still exists.
			if ( ! file_exists( ABSPATH . $signatureFile ) ) { // File has been deleted.
				$filesDeleted++;
				$signatureDetails['d'] = true;
				self::$_state['stats']['files_pending_delete']++;
				if ( 0 == $signatureDetails['b'] ) { // If NOT already sent to server.
					self::$_state['stats']['files_pending_send']--;
					if ( self::$_state['stats']['files_pending_send'] < 0 ) {
						self::$_state['stats']['files_pending_send'] = 0;
					}
				}
				self::$_state['stats']['files_total_count']--;
				if ( self::$_state['stats']['files_total_count'] < 0 ) {
					self::$_state['stats']['files_total_count'] = 0;
				}
				self::$_state['stats']['files_total_size'] = self::$_state['stats']['files_total_size'] - $signatureDetails['s']; // We already added all files not marked for deleation so remove this filesize from the sum.
				pb_backupbuddy::status( 'details', 'Remove file that no longer exists locally during signature calculation. Flagging `' . $signatureFile . '` for deletion.' );
				continue; // Skip to next file.
			}

			// Made it this far then calculate (or re-calculate) signature.
			$stat = @stat( ABSPATH . $signatureFile );
			if ( false === $stat ) {
				$error = 'Unable to retrieve stat() for file `' . $signatureFile . '`. Check file permissions.';
				backupbuddy_core::addNotification( 'live_error', 'BackupBuddy Stash Live Error', $error );
				pb_backupbuddy::status( 'details', $error );
				continue; // Skip to next file.
			}

			// Update rescan time.
			$filesUpdated++;
			$signatureDetails['r'] = time(); // Update time last rescanned.

			// NOTE: v8 cleanup of unused $signatureDetails['t'] and 'h' hash when set to default. TODO: In the future remove this cleanup as it will no longer be needed. Eg. v9?
			if ( ( isset( $signatureDetails['t'] ) ) && ( 0 == $signatureDetails['t'] ) ) {
				unset( $signatureDetails['t'] );
			}
			if ( ( isset( $signatureDetails['h'] ) ) && ( '' == $signatureDetails['h'] ) ) {
				unset( $signatureDetails['h'] );
			}
			if ( isset( $signatureDetails['a'] ) ) { // Remove entirely.
				unset( $signatureDetails['a'] );
			}

			// If the file changed then set as NOT uploaded so we will send a new copy.
			if ( ( $signatureDetails['m'] != $stat['mtime'] ) || ( $signatureDetails['s'] != $stat['size'] ) ) {
				if ( 0 != $signatureDetails['b'] ) { // If not already set to send then increase to-send stats.
					self::$_state['stats']['files_pending_send']++;
					$alreadySentFilesDetectedChanged++;
				}
				$filesDetectedChanged++;
				$signatureDetails['b'] = 0; // Current version is NOT backed up.
				unset( $signatureDetails['t'] ); // Reset try (send attempt) counter back to zero by clearing (to save mem) since this version has not been attempted.
				// If we made it here then the filesize number changed.  If the stored previous size was non-zero then this means we need to update the total file size sum stats for the difference.
				if ( 0 != $signatureDetails['s'] ) { // Don't subtract if it was never added in before.
					self::$_state['stats']['files_total_size'] = self::$_state['stats']['files_total_size'] - $signatureDetails['s']; // Subtract old size. New size was already summed above.
				}
			}
			if ( 0 == $signatureDetails['s'] ) { // Size was zero so it was not added yet. Add now that we have stat size data.
				self::$_state['stats']['files_total_size'] += $stat['size'];
			}
			$signatureDetails['m'] = $stat['mtime']; // Update modified time.
			if ( 0 == $signatureDetails['m'] ) {
				pb_backupbuddy::status( 'error', 'Error #8438934: File modified time unexpectly zero (0). File permissions may be blocking proper modification detection on file `' . $signatureFile  . '`.' );
			}
			$signatureDetails['s'] = $stat['size']; // Update size.

			if ( 0 != self::$_state['stats']['last_file_audit_start'] ) { // Only run if audit has ran yet.
				// If not already set to backup then check auditing info to see if the file was missing from the remote server as of the start of the last audit that finished.
				if ( ( 0 != $signatureDetails['b'] ) && ( self::$_state['stats']['last_file_audit_finish'] > self::$_state['stats']['last_file_audit_start'] ) ) { // Not pending send already AND last audit that began has indeed finished.
					// Was the file marked as sent BEFORE auditing began?
					if ( $signatureDetails['b'] < self::$_state['stats']['last_file_audit_start'] ) { // File was marked sent before the audit began so it should exist remotely.
						// Was the file NOT verified since the beginning of the last audit?
						if ( $signatureDetails['v'] < self::$_state['stats']['last_file_audit_start'] ) { // Not verified since the last audit (which we already confirmed has finished).
							// File needs re-sent since missing on remote server. Made it here then the following must apply: file is not already set to backup, the last audit that started has indeed finished, the file was already marked as sent prior to the last audit start, the file is not marked for deletion (would not have made it to this block of code) and the verification key timestamp is before the last audit began (or zero which is the same) so it should have existed during the audit.
							$signatureDetails['v'] = 0; // Reset audit timestamp.
							$signatureDetails['b'] = 0; // Reset backup timestamp since we know it's not backed up.
							unset( $signatureDetails['t'] ); // Reset try (send attempt) counter.
							self::$_state['stats']['files_pending_send']++; // Update files pending send counter.

							$filesNeedingResendFromIffyAudit++;
						}
					}
				}
			}

			// See if it's time to save our progress so far.
			if ( microtime( true ) - $last_save > self::SAVE_SIGNATURES_EVERY_X_SECONDS ) {
				self::$_catalogObj->save();
				self::$_stateObj->save();
				$last_save = microtime( true );
			}

			$destination_settings = self::get_destination_settings();

			// Do we have enough time to continue or do we need to chunk?
			if ( ( microtime( true ) - $start_time + self::TIME_WIGGLE_ROOM )  > $destination_settings['_max_time'] ) { // Running out of time! Chunk.
				self::$_catalogObj->save();
				pb_backupbuddy::status( 'details', 'Running out of time calculating signatures. Updated `' . $filesUpdated . '` signatures. Deleted `' . $filesDeleted . '` files. Already-sent files detected as changed since sending: `' . $alreadySentFilesDetectedChanged . '`. Files detected changed total: `' . $filesDetectedChanged . '`. Resend due to audit: `' . $filesNeedingResendFromIffyAudit . '`. Next start: `' . $loopCount . '`. Took `' . ( microtime( true ) - $start_time ) . '` seconds.' );
				return array( 'Updating file signatures', array( $loopCount ) );
			}

		} // end foreach signature.

		self::$_catalogObj->save();
		pb_backupbuddy::status( 'details', 'Signatures updated and saved. Updated `' . $filesUpdated . '` signatures. Deleted `' . $filesDeleted . '` files. Already-sent files detected as changed since sending: `' . $alreadySentFilesDetectedChanged . '`. Files detected changed total: `' . $filesDetectedChanged . '`. Resend due to audit: `' . $filesNeedingResendFromIffyAudit . '`. Total size: `' . self::$_state['stats']['files_total_size'] . '`. Took `' . ( microtime( true ) - $start_time ) . '` seconds.' );
		return true;

	} // End _step_update_files_signatures().



	/* _step_process_file_deletions()
	 *
	 * Handles cleanup of any locally deletes files that need removed from the Live servers.
	 *
	 */
	private static function _step_process_file_deletions( $startAt = 0 ) {
		if ( $startAt < 0 ) {
			$startAt = 0;
		}

		$start_time = microtime( true );
		if ( false === self::_load_catalog() ) {
			return false;
		}

		// Build Stash2/Stash3 destination settings based on Live settings.
		$destination_settings = self::get_destination_settings();

		pb_backupbuddy::status( 'details', 'Starting deletions at point: `' . $startAt . '`.' );

		// Loop through files in the catalog.
		$loopCount = 0;
		$checkCount = 0;
		$filesDeleted = 0;
		$deleteQueue = array(); // Files queued to delete.
		$last_save = microtime( true );
		foreach( self::$_catalog as $signatureFile => &$signatureDetails ) {
			if ( 0 != $startAt ) { // Resuming...
				if ( $loopCount < $startAt ) {
					$loopCount++;
					continue;
				}
			} else {
				$loopCount++;
			}
			$checkCount++;

			// NOTE: BB v8 cleanup removes default false to conserve memory. TODO: Remove at BB v9.
			if ( isset( $signatureDetails['d'] ) && ( false === $signatureDetails['d'] ) ) {
				unset( $signatureDetails['d'] );
			}

			// Skip non-deleted files.
			if ( !isset( $signatureDetails['d'] ) || ( true !== $signatureDetails['d'] ) ) {
				continue;
			}

			// Made it here then we will be deleting a file.

			// Cancel any in-process remote sends of deleted files.
			$sendFileoptions = glob( backupbuddy_core::getLogDirectory() . 'fileoptions/send-live_' . md5( $signatureFile ) . '-*.txt' );
			if ( ! is_array( $sendFileoptions ) ) {
				$sendFileoptions  = array();
			}
			foreach( $sendFileoptions as $sendFileoption ) {
				$fileoptions_obj = new pb_backupbuddy_fileoptions( $sendFileoption, $read_only = false, $ignore_lock = true, $create_file = false );
				if ( true !== ( $result = $fileoptions_obj->is_ok() ) ) {
					pb_backupbuddy::status( 'error', __('Error #9034.32393. Unable to access fileoptions data related to file `' . $signatureFile  . '`. Skipping cleanup of file send.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
					continue;
				}
				// Something wrong with fileoptions. Let cleanup handle it later.
				if ( ! isset( $fileoptions_obj->options['status'] ) ) {
					continue;
				}
				// Don't do anything for success, failure, or already-marked as -1 finish time.
				if ( ( 'success' == $fileoptions_obj->options['status'] ) || ( 'failure' == $fileoptions_obj->options['status'] ) || ( -1 == $fileoptions_obj->options['finish_time'] ) ) {
					continue;
				}
				// Cancel this send.
				$fileoptions_obj->options['finish_time'] = -1;
				$fileoptions_obj->save();
				pb_backupbuddy::status( 'details', 'Cancelled in-progress send of deleted file `' . $signatureFile . '`.' );
				unset( $fileoptions_obj );
			}

			// If file has been backed up to server then we need to QUEUE the file for deletion.
			if ( 0 != $signatureDetails['b'] ) {
				$deleteQueue[] = $signatureFile;
			} else { // Remove item from queue immediately since not sent.
				self::$_state['stats']['files_pending_delete'] = ( self::$_state['stats']['files_pending_delete'] - 1 );
				if ( self::$_state['stats']['files_pending_delete'] < 0 ) {
					self::$_state['stats']['files_pending_delete'] = 0;
				}

				self::$_state['stats']['files_pending_delete'] = ( self::$_state['stats']['files_pending_send'] - 1 );
				if ( self::$_state['stats']['files_pending_send'] < 0 ) {
					self::$_state['stats']['files_pending_send'] = 0;
				}

				unset( self::$_catalog[ $signatureFile ] );
			}

			// Is the queue full to the delete burst limit? Process queue.
			if ( count( $deleteQueue ) >= $destination_settings['max_delete_burst'] ) {
				pb_backupbuddy::status( 'details', 'File deletion queue full to burst limit. Triggering deletions call.' );
				self::_process_internal_delete_queue( $destination_settings, $deleteQueue, $filesDeleted ); // Process any items in the deletion queue. $deleteQueue and $filesDeleted are references.
			}

			// See if it's time to save our progress so far.
			if ( ( time() - $last_save ) > self::SAVE_SIGNATURES_EVERY_X_SECONDS ) {
				pb_backupbuddy::status( 'details', 'Time to save progress so far. Triggering deletions call then saving.' );
				self::_process_internal_delete_queue( $destination_settings, $deleteQueue, $filesDeleted ); // Process any items in the deletion queue. $deleteQueue and $filesDeleted are references.

				self::$_stateObj->save();
				self::$_catalogObj->save();
				$last_save = microtime( true );
			}

			// Do we have enough time to continue or do we need to chunk?
			if ( ( microtime( true ) - $start_time + self::TIME_WIGGLE_ROOM ) > $destination_settings['_max_time'] ) { // Running out of time! Chunk.
				pb_backupbuddy::status( 'details', 'Running out of time processing deletions. Took `' . ( microtime( true ) - $start_time ) . '` seconds. Triggering deletions call then saving.' );
				//self::_process_internal_delete_queue( $destination_settings, $deleteQueue, $filesDeleted ); // Process any items in the deletion queue. $deleteQueue and $filesDeleted are references.
				// Do not process queue here. Anything in the queuecan be handled during the next chunk.

				self::$_stateObj->save();
				self::$_catalogObj->save();
				return array( 'Processing deletions', array() ); //array( $startAt+$loopCount-$filesDeleted ) );
			}

		} // end foreach.

		// Wrap up any lingering deletions.
		pb_backupbuddy::status( 'details', 'Processing any lingering deletions in queue (' . count( $deleteQueue ) . ') at finish then saving.' );
		self::_process_internal_delete_queue( $destination_settings, $deleteQueue, $filesDeleted ); // Process any items in the deletion queue. $deleteQueue and $filesDeleted are references.

		// Save and finish.
		self::$_stateObj->save();
		self::$_catalogObj->save();
		pb_backupbuddy::status( 'details', 'Deletions processed. Checked `' . $checkCount . '` files. Deleted `' . $filesDeleted . '` files. Took `' . ( microtime( true ) - $start_time ) . '` seconds.' );
		return true;

	} // End _step_process_deletions().



	/* _process_internal_delete_queue()
	 *
	 * API call to actually delete files queued for deletion. Updated catalog and stats variables.
	 *
	 */
	private static function _process_internal_delete_queue( $destination_settings, &$deleteQueue, &$filesDeleted ) {
		// Return true on empty queue.
		if ( count( $deleteQueue ) <= 0 ) {
			return true;
		}

		pb_backupbuddy::status( 'details', 'Processing internal deletion queue on `' . count( $deleteQueue ) . '` files.' );
		if ( true !== ( $delete_result = pb_backupbuddy_destination_live::deleteFiles( $destination_settings, $deleteQueue ) ) ) {
			pb_backupbuddy::status( 'error', 'Error #8239833: Unable to delete remote file(s) `' . implode( '; ', $deleteQueue ) . '`' );
			return false;
		} elseif ( true === $delete_result ) {

			// Remove each deleted file from catalog and update stats.
			foreach( $deleteQueue as $signatureFile ) {
				// Remove file from catalog and update state stats. IMPORTANT: DO NOT SAVE CATALOG UNTIL QUEUE PROCESSED.
				unset( self::$_catalog[ $signatureFile ] );
			}

			// Calculate some local stats on what just happened for log.
			$deleted_count = count( $deleteQueue );
			$remaining = ( self::$_state['stats']['files_pending_delete'] - $deleted_count );
			if ( $remaining < 0 ) {
				$remaining = 0;
			}
			$last_deleted = end( $deleteQueue ); // Move array pointer to end of queue.

			// Update state stats.
			self::$_state['stats']['files_pending_delete'] = self::$_state['stats']['files_pending_delete'] - $deleted_count;
			if ( self::$_state['stats']['files_pending_delete'] < 0 ) {
				self::$_state['stats']['files_pending_delete'] = 0;
			}

			// Clear $deleteQueue reference.
			$deleteQueue = array(); // Clear out queue.

			// Update $filesDeleted reference.
			$filesDeleted += $deleted_count;

			pb_backupbuddy::status( 'details', 'Deleted `' . $deleted_count . '` remote files (' . $remaining . ' remain). Last file deleted in current queue: `' . $last_deleted . '`.' );
			return true;
		}
	} // End _process_internal_delete_queue().



	/* _step_send_pending_files()
	 *
	 * Finds the next pending file and tries to send it.
	 *
	 * @param	array 	$signatures		If provided then we will use these signatures for sending instead of the normal catalog signatures. Used to send database SQL files.
	 * @param	int		$startAt		Location to start sending from. Used by chunking. Skips X signatures to get to this point.
	 */
	private static function _step_send_pending_files( $startAt = 0 ) {

		// Load state into self::$_state & fileoptions object into self::$_stateObj.
		if ( false === self::_load_state() ) {
			return false;
		}

		if ( 0 == $startAt ) {
			$startAt = self::$_state['stats']['last_filesend_startat'];
			pb_backupbuddy::status( 'details', 'Starting to send pending files at position `' . $startAt . '` based on stored stats position.' );
		} else {
			pb_backupbuddy::status( 'details', 'Starting to send pending files at position `' . $startAt . '` based on passed value.' );
		}

		if ( false === self::_load_catalog() ) {
			return false;
		}

		// If catalog backup is older than X seconds then backup.
		$catalog_file = backupbuddy_core::getLogDirectory() . 'live/catalog-' . pb_backupbuddy::$options['log_serial'] . '.txt';
		if ( false !== ( $file_stats = @stat( $catalog_file . '.bak' ) ) ) {
			if ( ( time() - $file_stats['mtime'] ) > backupbuddy_constants::MAX_TIME_BETWEEN_CATALOG_BACKUP ) {
				self::backup_catalog();
			}
		}

		require_once( pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php' );

		// Truncate log if it is getting too large. Keeps newest half.
		self::_truncate_log();

		// Loop through files in the catalog.
		$loopCount = 0;
		$checkCount = 0;
		$sendTimeSum = 0;
		$sendSizeSum = 0;
		$sendAttemptCount = 0;
		$logTruncateCheck = 0;
		$lastSendThisPass = false;
		$sendMoreRemain = false;
		$sendAttemptCount = 0;
		$lackSignatureData = 0;
		$tooManyAttempts = 0;

		foreach( self::$_catalog as $signatureFile => &$signatureDetails ) {
			$loopCount++;

			// If Live is disabled, we need to break this loop. Query the DB every 10 ints to make sure live still exists
			if ( substr( $loopCount, -1 ) == 0 ) {
				wp_cache_delete( 'pb_backupbuddy', 'options' );
				$bub_options      = get_option('pb_backupbuddy');
				$bub_destinations = empty( $bub_options['remote_destinations'] ) ? array() : $bub_options['remote_destinations'];
				$live_still_exists = false;
				foreach( $bub_destinations as $bub_dest ) {
					if ( $bub_dest['type'] == 'live' ) {
						$live_still_exists = true;
					}
				}
				if ( empty( $live_still_exists ) ) {
					backupbuddy_core::clearLiveLogs( pb_backupbuddy::$options['log_serial'] );
					return false;
				}
			}

			if ( 0 != $startAt ) { // Resuming...
				if ( $loopCount < $startAt ) {
					continue;
				}
			}
			$checkCount++;

			// Every X files that get sent, make sure log file is not getting too big AND back up catalog.
			if ( 0 == ( ($sendAttemptCount+1) % 150 ) ) {
				// Backup catalog.
				self::backup_catalog();
			}

			// If already backed up OR we do not have signature data yet then skip for now.
			if ( ( 0 != $signatureDetails['b'] ) || ( 0 == $signatureDetails['m'] ) ) {
				if ( 0 == $signatureDetails['m'] ) {
					$lackSignatureData++;
				}
				continue;
			}

			// If too many attempts have passed then skip.
			if ( isset( $signatureDetails['t'] ) && ( $signatureDetails['t'] >= self::MAX_SEND_ATTEMPTS ) ) {
				$tooManyAttempts++;
				continue;
			}

			// Load destination settings.
			$destination_settings = self::get_destination_settings();

			// If too many remote sends have failed today then give up for now since something is likely wrong.
			if ( self::$_state['stats']['recent_send_fails'] > $destination_settings['max_daily_failures'] ) {
				if ( self::$_state['stats']['last_send_fail'] > 0 ) {
					$last_fail = pb_backupbuddy::$format->time_ago( self::$_state['stats']['last_send_fail'] );
				} else {
					$last_fail = 'unknown';
				}
				$error = 'Error #5002: Too many file transfer failures have occurred so stopping transfers. We will automatically try again in 12 hours. Verify there are no remote file transfer problems. Check recently send file logs on Remote Destinations page. Don\'t want to wait? Pause Files process then select \'Reset Send Attempts\' under \'Advanced Troubleshooting Options\'.  Time since last send file: `' . $last_fail . '`. File: `' . self::$_state['stats']['last_send_fail_file'] . '`.';
				backupbuddy_core::addNotification( 'live_error', 'BackupBuddy Stash Live Error', $error );
				self::$_state['step']['last_status'] =  $error;
				pb_backupbuddy::status( 'error', $error );
				return false;
			}

			// If this is not the first file we've sent this pass, see if we have enough time for more.
			if ( $sendSizeSum > 0 ) {
				// Check if it appears we have enough time to send at least a full single chunk in this pass or if we need to pass off to a subsequent run.
				$send_speed = ( $sendSizeSum / 1048576 ) / $sendTimeSum; // Estimated speed at which we can send files out. Unit: MB / sec.
				$time_elapsed = ( microtime( true ) - pb_backupbuddy::$start_time );
				$time_remaining = $destination_settings['_max_time'] - ( $time_elapsed + self::TIME_WIGGLE_ROOM ); // Estimated time remaining before PHP times out. Unit: seconds.
				$size_possible_with_remaining_time = $send_speed * $time_remaining; // Size possible to send with remaining time (takes into account wiggle room).

				$size_to_send = ( $signatureDetails['s'] / 1048576 ); // Size we want to send this pass. Unit: MB.
				if ( $destination_settings['max_burst'] < $size_to_send ) { // If the chunksize is smaller than the full file then cap at sending that much.
					$size_to_send = $destination_settings['max_burst'];
				}

				if ( $size_possible_with_remaining_time < $size_to_send ) { // File (or chunk) is bigger than what we have time to send.
					$lastSendThisPass = true;
					$sendMoreRemain = true;
					$send_speed_status = 'Not enough time to send more. To continue in next live_periodic pass.';
				} else {
					$send_speed_status = 'Enough time to send more. Preparing for send.';
				}

				pb_backupbuddy::status ('details', 'Not the first normal file to send this pass. Send speed: `' . $send_speed . '` MB/sec. Time elapsed: `' . $time_elapsed . '` sec. Time remaining (with wiggle): `' . $time_remaining . '` sec based on reported max time of `' . $destination_settings['_max_time'] . '` sec. Size possible with remaining time: `' . $size_possible_with_remaining_time . '` MB. Size to chunk (greater of filesize or chunk): `' . $size_to_send . '` MB. Conclusion: `' . $send_speed_status . '`.' );
			} // end subsequent send time check.

			// NOT out of time so send this.
			if ( true !== $lastSendThisPass ) {

				// Run cleanup on send files.
				require_once( pb_backupbuddy::plugin_path() . '/classes/housekeeping.php' );
				backupbuddy_housekeeping::trim_remote_send_stats( $file_prefix = 'send-live_', $limit = $destination_settings['max_send_details_limit'], '', $purge_log = true ); // Only keep last 5 send fileoptions.
				// Moved into trim_remote_send_stats(). backupbuddy_housekeeping::purge_logs( $file_prefix = 'status-remote_send-live_', $limit = $destination_settings['max_send_details_limit'] ); // Only keep last 5 send logs.

				// Increment try count for transfer attempts and save.
				if ( isset( $signatureDetails['t'] ) ) {
					$signatureDetails['t']++;
				} else {
					$signatureDetails['t'] = 1;
				}
				self::$_catalogObj->save();

				// Save position in case process starts over to prevent race conditions resulting in double send of files.
				$destination_settings['_live_next_step'] = array( 'send_pending_files', array() ); // Next function and args to try and run after finishing send of this file.
				self::$_state['stats']['last_filesend_startat'] = $loopCount + 1;
				self::$_stateObj->save();

				$full_file = ABSPATH . substr( $signatureFile, 1 );
				if ( ! file_exists( $full_file ) ) {
					pb_backupbuddy::status( 'details', 'File in catalog no longer exists (or permissions block). Skipping send of file `' . $full_file . '`.' );
				} else {
					// Send file. AFTER success sending this Stash2/Stash3 destination will automatically trigger the live_periodic processing _IF_ multipart send. If success or fail the we come back here to potentially send more files in the same PHP pass so small files don't each need their own PHP page run.  Unless the process has restarted then this will still be the 'next' function to run.
					$send_id = 'live_' . md5( $signatureFile ) . '-' . pb_backupbuddy::random_string( 6 );
					pb_backupbuddy::status( 'details', 'Live starting send function.' );
					$sendTimeStart = microtime( true );

					// Close catalog & state while sending if > X size to prevent collisions.
					if ( $signatureDetails['s'] > self::CLOSE_CATALOG_WHEN_SENDING_FILESIZE ) {
						self::$_catalogObj = '';
						self::$_stateObj = '';
					}

					// Send file to remote.
					$sendAttemptCount++;
					$result = pb_backupbuddy_destinations::send( $destination_settings, $full_file, $send_id, $delete_after = false, $isRetry = false, $trigger = 'live_periodic', $destination_id = backupbuddy_live::getLiveID() );

					// Re-open catalog (if closed).
					if ( false === self::_load_state() ) {
						pb_backupbuddy::status( 'error', 'Error #5489458443: Unable to re-open temporarily closed state.' );
						return false;
					}
					if ( false === self::_load_catalog() ) {
						pb_backupbuddy::status( 'error', 'Error #5489458443: Unable to re-open temporarily closed catalog.' );
						return false;
					}

					$sendTimeFinish = microtime( true );
					if ( true === $result ) {
						$result_status = 'Success sending in single pass.';
						$sendTimeSum += ( $sendTimeFinish - $sendTimeStart ); // Add to time sent sending.

						// Set a minimum threshold so small files don't make server appear slower than reality due to overhead.
						$minimum_size_threshold = self::MINIMUM_SIZE_THRESHOLD_FOR_SPEED_CALC; // Pretend file is at least 500k each.
						if ( $signatureDetails['s'] < $minimum_size_threshold ) {
							$sendSizeSum += $minimum_size_threshold;
						} else {
							$sendSizeSum += $signatureDetails['s']; // Add to size of data sent.
						}
					} elseif ( false === $result ) {
						self::$_state['stats']['recent_send_fails']++;
						self::$_state['stats']['last_send_fail'] = time();
						self::$_state['stats']['last_send_fail_file'] = $full_file;
						$result_status = 'Failure sending in single/first pass. See log above for error details. Failed sends today: `' . self::$_state['stats']['recent_send_fails'] . '`.';
					} elseif ( is_array( $result ) ) {
						$result_status = 'Chunking commenced. Ending sends for this pass.';
						//$lastSendThisPass = true;
						// TODO: Ideally at this point we would have Live sleep until the large chunked file finished sending.
					}
					pb_backupbuddy::status( 'details', 'Live ended send files function. Status: ' . $result_status . '. sendAttemptCount: ' . $sendAttemptCount );
				} // end file exists.
			}

			// Check if we are done sending for this PHP pass/run.
			if ( true === $lastSendThisPass ) {
				break;
			}
		} // End foreach signatures.

		pb_backupbuddy::status( 'details', 'Checked `' . $checkCount . '` items for sending. Sent `' . $sendAttemptCount . '`. Skipped due to too many send attempts: `' . $tooManyAttempts . '`.' );
		pb_backupbuddy::status( 'warning', 'Warning: Skipped due to lacking signature data: `' . $lackSignatureData . '`. If this is temporary it is normal. If this persists there may be permissions blocking reading file details.' );

		if ( $tooManyAttempts > 0 ) {
			$warning = 'Warning #5003. `' . $tooManyAttempts . '` files were skipped due to too many send attempts failing. Check the Destinations page\'s Recently sent files list to check for errors of failed sends. To manually reset sends Pause the Files process and wait for it to finish, then select the Advanced Troubleshooting Option to \'Reset Send Attempts\'.';
			pb_backupbuddy::status( 'warning', $warning );
			backupbuddy_core::addNotification( 'live_error', 'BackupBuddy Stash Live Error', $warning );
		}

		// Schedule next run if we still have more files to potentially send.
		if ( true === $sendMoreRemain ) {
			return array( 'Sending queued files', array( $loopCount ) );
		} else { // No more files.
			self::$_state['stats']['last_filesend_startat'] = 0; // Reset the startat location.
			pb_backupbuddy::status( 'details', 'No more files remain. Reset filesend startat position back to 0.' );

			return true;
		}

	} // end _step_send_pending_files().



	/* _step_audit_remote_files()
	 *
	 * Audits remove files to make sure remotely stores files match local catalog.
	 * 1) Lists through all remote files. Any remote files found that are not in the catalog at all are deleted.
	 * 2) Updates the 'v' audit verification key for files found in the catalog, verifying they were found remotely.
	 * 3) The next time the file signature checking step runs, any files that were thought to be backed up but found not to be (missing or old 'v' key) will be set to re-upload.
	 *
	 * @param	string	$marker		AWS file marker for the next loop (if applicable). null for no marker (start at beginning).
	 * @param	int		$runningCount	How many files listed so far (excluding table count).
	 *
	 */
	private static function _step_audit_remote_files( $marker = null, $runningCount = 0, $found_dat = false ) {
		if ( ( time() - self::$_state['stats']['last_file_audit_finish'] ) < ( self::TIME_BETWEEN_FILE_AUDIT ) ) {
			pb_backupbuddy::status( 'details', 'Not enough time has passed since last file audit. Skipping for now. Minimum time: `' . self::TIME_BETWEEN_FILE_AUDIT . '` secs. Last ran ago: `' . ( time() - self::$_state['stats']['last_file_audit_finish'] ) . '` secs.' );
			return true;
		}


		$deleteBatchSize = 100; // Delete files in batches of this many files via deleteObjects via deleteFiles().
		$serialDir = 'wp-content/uploads/backupbuddy_temp/SERIAL/'; // Include trailing slash.
		$serialDirLen = strlen( $serialDir );

		if ( false === self::_load_state() ) {
			return false;
		}
		if ( false === self::_load_catalog() ) {
			return false;
		}
		if ( false === self::_load_tables() ) {
			return false;
		}

		$destination_settings = self::get_destination_settings();
		require_once( pb_backupbuddy::plugin_path() . '/destinations/live/init.php' );

		if ( null == $marker ) { // Only reset if NOT chunking (first pass). Was cause of a bug first few weeks of release resulting in process restarting after hitting 100% if audit step chunked.
			self::$_state['stats']['last_file_audit_start'] = microtime( true ); // Audit start time.
		}

		self::$_stateObj->save();

		$found_dat = false;

		$loopCount = 0;
		$loopStart = microtime( true );
		$keepLooping = true;
		$totalListed = 0;
		$totalTables = 0;
		$serialSkips = 0;
		$filesDeleted = 0;
		$tablesDeleted = 0;
		$last_save = microtime( true );
		while( true === $keepLooping ) {
			$loopCount++;

			pb_backupbuddy::status( 'details', 'Listing files starting at marker `' . $marker . '`.' );
			$files = pb_backupbuddy_destination_live::listFiles( $destination_settings, $remotePath = '', $marker );
			if ( ! is_array( $files ) ) {
				$error = 'Error #3279327: One or more errors encountered attempting to list remote files for auditing. Details: `' . print_r( $files, true ) . '`.';
				pb_backupbuddy::status( 'error', $error );
				backupbuddy_core::addNotification( 'live_error', 'BackupBuddy Stash Live Error', $error );
				self::$_state['step']['last_status'] = 'Error: Unable to list remote files for audit.';
				return false;
			}
			pb_backupbuddy::status( 'details', 'Listed `' . count( $files ) . '` files.' );
			$totalListed += count( $files );

			// Iterate through all remote files.
			$pendingDelete = array();
			$filesDeletedThisRound = 0;
			foreach( $files as $file ) {

				// Skip all files in the SERIAL directory with underscore. Audit the rest.
				if ( substr( $file['Key'], 0, $serialDirLen ) == $serialDir ) {
					$totalTables++;
					$basename = basename( $file['Key'] );

					// Ignore underscore-prefixed live db data. Do not audit these. Skip.
					if ( '_' == substr( $basename, 0, 1 ) ) {
						$serialSkips++;
						continue;
					}

					// Ignore backupbuddy_dat.php metadata file and importbuddy.php files in database folder.
					if ( 'backupbuddy_dat.php' == $basename ) {
						$found_dat = true;
						continue;
					}
					if ( 'importbuddy.php' == $basename ) {
						continue;
					}

					// Verify no unexpected extra .sql files exist.
					if ( pb_backupbuddy::$options['log_level'] == '3' ) { // Full logging enabled.
						pb_backupbuddy::status( 'details', 'Auditing remotely found table (shown due to log level): `' . $basename . '`.' );
					}
					$table_name = str_replace( '.sql', '', $basename );
					if ( ! isset ( self::$_tables[ $table_name ] ) ) {
						pb_backupbuddy::status( 'details', 'Deleting unexpectedly remotely found table file: `' . $basename . '`.' );
						if ( true !== ( $delete_result = pb_backupbuddy_destination_live::deleteFile( $destination_settings, array( $file['Key'] ) ) ) ) {
							pb_backupbuddy::status( 'error', 'Error #329030923: Unable to delete remote file. See log above for details. Details: `' . $deleteResult . '`.' );
						} else {
							pb_backupbuddy::status( 'details', 'Deleted remote database file `' . $file['Key'] . '`.' );
							$tablesDeleted++;
						}
					}

					continue;
				}

				if ( ! isset( self::$_catalog[ '/' . $file['Key'] ] ) ) { // Remotely stored file not found in local catalog. Delete remote.
					$pendingDelete[] = $file['Key'];

					// Process deletions.
					if ( count( $pendingDelete ) >= $deleteBatchSize ) {
						if ( true !== ( $delete_result = pb_backupbuddy_destination_live::deleteFile( $destination_settings, $pendingDelete ) ) ) {
							pb_backupbuddy::status( 'error', 'Error #4397347934: Unable to delete one or more remote files. See log above for details. Details: `' . print_r( $delete_result, true ) . '`. Clearing pendingDelete var for next batch.' );
						} else {
							pb_backupbuddy::status( 'details', 'Deleted batch of `' . count( $pendingDelete ) . '` remote files. Cleaning pendingDelete var for next batch.' );
							$filesDeleted += count( $pendingDelete );
							$filesDeletedThisRound += count( $pendingDelete );
						}
						$pendingDelete = array();
					}

				} else { // Remotely stored file found in local catalog. Updated verified audit timestamp.
					// Update 'v' key (for verified) with current timestamp to show it is verified as being on remote server.
					self::$_catalog[ '/' . $file['Key'] ]['v'] = time();
				}
			}


			// Process any remaining deletions.
			if ( count( $pendingDelete ) > 0 ) {
				if ( true !== ( $delete_result = pb_backupbuddy_destination_live::deleteFile( $destination_settings, $pendingDelete ) ) ) {
					pb_backupbuddy::status( 'error', 'Error #373262793: Unable to delete one or more remote files. See log above for details. Details: `' . $delete_result . '`. Clearing pendingDelete var for next batch.' );
				} else {
					pb_backupbuddy::status( 'details', 'Deleted batch of `' . count( $pendingDelete ) . '` remote files.' );
					$filesDeleted += count( $pendingDelete );
				}
				unset( $pendingDelete );
			}


			pb_backupbuddy::status( 'details', 'Deleted `' . $filesDeletedThisRound . '` total files this round out of `' . count( $files ) . '` listed. Looped `' . $loopCount . '` times.' );


			// See if it's time to save 'v' key changes so far.
			if ( ( time() - $last_save ) > self::SAVE_SIGNATURES_EVERY_X_SECONDS ) {
				self::$_catalogObj->save();
				//self::$_stateObj->save();
				$last_save = microtime( true );
			}

			$filesListedMinusSkips = ( $totalListed - $serialSkips );
			$total_files = ( $filesListedMinusSkips - $filesDeleted );
			$totalTablesMinusSkips = ( $totalTables - $serialSkips );

			// If files retrieves is >= to the list limit then there may be more files. Set marker and chunk.
			if ( count( $files ) < $destination_settings['max_filelist_keys'] ) { // No more files remain.
				$keepLooping = false;

				self::$_catalogObj->save();
				self::$_state['stats']['last_file_audit_finish'] = microtime( true ); // Audit finish time.

				$runningCount += $total_files - $totalTablesMinusSkips;

				pb_backupbuddy::status( 'details', 'No more files to check. Deleted `' . $filesDeleted . '` out of listed `' . $totalListed . '` (`' . $filesListedMinusSkips . '` files, Deleted `' . $tablesDeleted . '` tables out of `' . $totalTablesMinusSkips . '` total tables. `' . $serialSkips . '` skipped database/serial dir). `' . $total_files .'` files+tables.sql files after deletions. Files running count: `' . $runningCount . '`.' );

				if ( $runningCount < self::$_state['stats']['files_total_count'] ) {
					$message = 'Attention! Remote storage lists fewer files (' . $runningCount . ') than expected (' . self::$_state['stats']['files_total_count'] . '). More files may be pending transfer. Deleted: `' . $filesDeletedThisRound . '`.';
					pb_backupbuddy::status( 'error', $message );
					backupbuddy_core::addNotification( 'live_error', 'BackupBuddy Stash Live Error', $message );
				}

				return true;
			} else { // More files MAY remain.
				pb_backupbuddy::status( 'details', 'More files remain to check. Deleted `' . $filesDeleted . '` total files this round so far. Files running count: `' . ( $runningCount + $total_files - $totalTablesMinusSkips ) . '`.' );

				$marker = end( $files );
				$marker = $marker['Key'];
				reset( $files );

				// Do we have enough time to proceed or do we need to chunk?
				$time_elapsed = ( microtime( true ) - pb_backupbuddy::$start_time );
				$time_remaining = $destination_settings['_max_time'] - ( $time_elapsed + self::TIME_WIGGLE_ROOM ); // Estimated time remaining before PHP times out. Unit: seconds.
				$averageTimePerLoop = ( microtime( true ) - $loopStart ) / $loopCount;
				pb_backupbuddy::status( 'details', 'Time elapsed: `' . $time_elapsed . '`, estimated remaining: `' . $time_remaining . '`, average time needed per loop: `' . $averageTimePerLoop . '`. Max time setting: `' . $destination_settings['_max_time'] . '`.' );
				if ( $averageTimePerLoop >= $time_remaining ) { // Not enough time for another loop. Chunk.
					$keepLooping = false;
					self::$_catalogObj->save();

					$runningCount += $total_files - $totalTablesMinusSkips;

					pb_backupbuddy::status( 'details', 'Running out of time processing file audit. Took `' . ( microtime( true ) - $loopStart ) . '` seconds to delete `' . $filesDeleted . '` out of listed `' . $totalListed . '` (`' . $filesListedMinusSkips . '` files, Deleted `' . $tablesDeleted . '` tables out of `' . $totalTablesMinusSkips . '` total tables. `' . $serialSkips . '` skipped database/serial dir). `' . ( $filesListedMinusSkips - $filesDeleted ) .'` files after deletions. Starting next at `' . $marker . '`.  Files running count: `' . $runningCount . '`.' );

					return array( 'Auditing remote files', array( $marker, $runningCount, $found_dat ) );
				} else {
					// Proceed looping in this PHP page load...
					$keepLooping = true;
				}
			} // end if more files may remain.

		} // End while.


		// Made it here so we finished.
		self::$_catalogObj->save();
		self::$_state['stats']['last_file_audit_finish'] = microtime( true ); // Audit finish time.

		// IF DAT file missing remotely during audit then try to resend it. If it cannot then log error.
		if ( false === $found_dat ) {
			pb_backupbuddy::status( 'error', 'Error #849348343: Audit did not find DAT file. Resending now (if still exists).' );

			$dat_file = backupbuddy_live::getLiveDatabaseSnapshotDir() . 'backupbuddy_dat.php';
			if ( ! file_exists( $dat_file ) ) {
				$error = 'Error #48949834: DAT file already removed prior to audit re-send. Snapshot may fail/hang.';
				pb_backupbuddy::status( 'error', $error );
				backupbuddy_core::addNotification( 'live_error', 'BackupBuddy Stash Live Error', $error );
			} else {
				// Send DAT file.
				$send_id = 'live_' . md5( $dat_file ) . '-' . pb_backupbuddy::random_string( 6 );
				$destination_settings['_database_table'] = 'backupbuddy_dat.php';
				if ( false === pb_backupbuddy_destinations::send( $destination_settings, $dat_file, $send_id, $delete_after = true, $isRetry = false, $trigger = 'live_periodic', $destination_id = backupbuddy_live::getLiveID() ) ) {
					$error = 'Error #348983434: Unable to send DAT file to Live servers DURING AUDIT. See error log above for details.';
					pb_backupbuddy::status( 'error', $error );
					backupbuddy_core::addNotification( 'live_error', 'BackupBuddy Stash Live Error', $error );
				}
			}
		}

		// If not all files have uploaded, skip snapshot for now.
		if ( ( self::$_state['stats']['files_pending_send'] > 0 ) || ( self::$_state['stats']['tables_pending_send'] > 0 ) ) {
			pb_backupbuddy::status( 'details', '`' . self::$_state['stats']['files_pending_send'] . '` files and `' . self::$_state['stats']['tables_pending_send'] . '` database tables are still pending transfer. Waiting for transfers to finish before creating Snapshot.' );
			self::$_state['stats']['wait_on_transfers_start'] = microtime( true );
			backupbuddy_live::queue_step( $step = 'wait_on_transfers', $args = array(), $skip_run_now = true );
			return true;
		}

		return true;

	} // End _step_audit_remote_files().



	/* get_destination_settings()
	 *
	 * Gets the remote destination settings for BackupBuddy Stash Live. Eg advanced settings.
	 *
	 */
	public static function get_destination_settings() {
		require_once( pb_backupbuddy::plugin_path() . '/classes/core.php' );
		require_once( pb_backupbuddy::plugin_path() . '/destinations/live/init.php' );
		$settings = pb_backupbuddy_destination_live::_formatSettings( pb_backupbuddy::$options['remote_destinations'][ backupbuddy_live::getLiveID() ] );

		$settings['_max_time'] = backupbuddy_core::adjustedMaxExecutionTime( $settings['max_time'] );

		if ( $settings['_max_time'] < self::MIN_ADJUSTED_MAX_RUNTIME_BEFORE_WARNING ) {
			pb_backupbuddy::status( 'warning', 'Warning #893483984: Adjusted max execution time below warning threshold of `' . self::MIN_ADJUSTED_MAX_RUNTIME_BEFORE_WARNING . '` seconds. Check destination max runtime setting and/or host PHP max execution time limit. Destination setting: `' . $settings['max_time'] . '`. Adjusted: `' . backupbuddy_core::adjustedMaxExecutionTime( $settings['max_time'] ) . '`.' );
		}
		if ( $settings['_max_time'] < self::MIN_ADJUSTED_MAX_RUNTIME_BEFORE_ERROR ) {
			pb_backupbuddy::status( 'warning', 'Error #438949843: Adjusted max execution time below error threshold of `' . self::MIN_ADJUSTED_MAX_RUNTIME_BEFORE_ERROR . '` seconds. Check destination max runtime setting and/or host PHP max execution time limit. Destination setting: `' . $settings['max_time'] . '`. Adjusted: `' . backupbuddy_core::adjustedMaxExecutionTime( $settings['max_time'] ) . '`.' );
		}

		return $settings;

	} // End get_destination_settings().



	/* get_file_stats()
	 *
	 * Gets the stats from the state file. Read only.
	 *
	 */
	public static function get_file_stats( $type ) {

		require_once( pb_backupbuddy::plugin_path() . '/classes/fileoptions.php' );

		pb_backupbuddy::status( 'details', 'Fileoptions instance #89.' );
		$statsObj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'live/' . $type . '-' . pb_backupbuddy::$options['log_serial'] . '.txt', $read_only = true, $ignore_lock = true, $create_file = false );
		if ( true !== ( $result = $statsObj->is_ok() ) ) {
			pb_backupbuddy::status( 'error', 'Error #3443794. Fatal error. Unable to create or access fileoptions file for media. Details: `' . $result . '`.' );
			die();
		}
		pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );

		if ( isset( $statsObj->options['stats'] ) ) {
			return $statsObj->options['stats'];
		} else {
			return false;
		}

	} // End get_file_stats().



	private static function _fileoptions_lock_ignore_timeout_value() {
		return backupbuddy_core::detectLikelyHighestExecutionTime() + backupbuddy_constants::TIMED_OUT_PROCESS_RESUME_WIGGLE_ROOM;
	}


	/* _load_state()
	 *
	 * @param	$force_load	bool	Whether or not to force loading if already loaded. Defaults to false, do not reload if already loaded.
	 * @param	$get_contents_only	By default (false) the state will be loaded into self::$_state. When true instead only the contents are loaded and returned, not touching self::_state.
	 *
	 */
	private static function _load_state( $force_load = false, $get_contents_only = false ) {
		require_once( pb_backupbuddy::plugin_path() . '/classes/core.php' );

		if ( ( true !== $force_load ) && ( is_object( self::$_stateObj ) ) ) {
			if ( true === $get_contents_only ) {
				return self::$_stateObj->options;
			} else {
				return true;
			}
		}

		if ( true === $get_contents_only ) {
			$read_only = true;
			$ignore_lock = true;
			//error_log ('ignoreLock_readOnly' );
		} else {
			$read_only = false;
			$ignore_lock = self::_fileoptions_lock_ignore_timeout_value();
			//error_log ('lock_writable' );
		}

		// Load state fileoptions.
		require_once( pb_backupbuddy::plugin_path() . '/classes/fileoptions.php' );
		$stateObj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'live/state-' . pb_backupbuddy::$options['log_serial'] . '.txt', $read_only, $ignore_lock, $create_file = true );
		if ( true !== ( $result = $stateObj->is_ok() ) ) {
			$caller = backupbuddy_core::getCallingFunctionName();
			if ( 'backupbuddy_live_periodic::_get_daily_stats_ref()' == $caller ) { // Don't log as error if it's just from stats not being able to load state as this can happen due to state being locked a lot during rapid activity.
				//pb_backupbuddy::status( 'warning', 'Warning #3297392A. This MAY BE NORMAL due to Stash Live being very busy. Unable to create or access SERIAL fileoptions file. Details: `' . $result . '`. Waiting a moment before ending. Read only: `' . $read_only . '`, ignore lock: `' . $ignore_lock . '`, contents only: `' . $get_contents_only . '`. Caller: `' . $caller . '`.' );
			} else {
				pb_backupbuddy::status( 'error', 'Error #3297392B. Fatal error. Unable to create or access SERIAL fileoptions file. Details: `' . $result . '`. Waiting a moment before ending. Read only: `' . $read_only . '`, ignore lock: `' . $ignore_lock . '`, contents only: `' . $get_contents_only . '`. Caller: `' . $caller . '`.' );
			}
			sleep( 3 ); // Wait a moment to give time for temporary issues to resolve.
			return false;
		}

		// Set up initial state / merge defaults.
		if ( ! is_array( $stateObj->options ) ) {
			$stateObj->options = array();
			pb_backupbuddy::status( 'details', 'State array empty. Initializing as new.' );
		}
		$stateObj->options = array_merge( array(
			'data_version'  => 1,
			'step'          => array(),
			'prev_step'     => array(),
			'step'			=> array(),
			'stats'         => array(),
		), $stateObj->options );
		$stateObj->options['step'] = array_merge( self::$_stepDefaults, $stateObj->options['step'] );
		$stateObj->options['stats'] = array_merge( self::$_statsDefaults, $stateObj->options['stats'] );

		// Getting contents only.
		if ( true === $get_contents_only ) {
			return $stateObj->options;
		}

		// Set class variables with references to object and options within.
		self::$_stateObj = &$stateObj;
		self::$_state = &self::$_stateObj->options;

		return true;

	} // End _load_state().



	/* _load_catalog()
	 *
	 * Loads the catalog into a class variable for usage by functions.
	 *
	 */
	private static function _load_catalog( $force_reload = false, $get_contents_only = false ) {

		if ( is_object( self::$_catalogObj ) && ( true !== $force_reload ) ) {
			return self::$_catalogObj;
		}
		if ( true === $force_reload ) {
			unset( self::$_catalogObj );
		}

		$read_only = false;
		$ignore_lock = self::_fileoptions_lock_ignore_timeout_value();
		if ( true === $get_contents_only ) {
			$read_only = true;
			$ignore_lock = true;
		}

		require_once( pb_backupbuddy::plugin_path() . '/classes/fileoptions.php' );
		$catalogObj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'live/catalog-' . pb_backupbuddy::$options['log_serial'] . '.txt', $read_only, $ignore_lock, $create_file = true, $live_mode = true );
		if ( true !== ( $result = $catalogObj->is_ok() ) ) {
			pb_backupbuddy::status( 'error', 'Error #239239034. Fatal error. Unable to create or access CATALOG fileoptions file. Details: `' . $result . '`. Waiting a moment before ending. Read only: `' . $read_only . '`, ignore lock: `' . $ignore_lock . '`, contents only: `' . $get_contents_only . '`. Caller: `' . backupbuddy_core::getCallingFunctionName() . '`.' );
			sleep( 3 ); // Wait a moment to give time for temporary issues to resolve.
			return false;
		}

		// Set defaults.
		if ( ! is_array( $catalogObj->options ) ) {
			$catalogObj->options = array();
		}
		//$catalogObj->options = array_merge( self::$_catalogDefaults, $catalogObj->options );

		// Getting contents only.
		if ( true === $get_contents_only ) {
			return $catalogObj->options;
		}

		// Set class variables with references to object and options within.
		self::$_catalogObj = &$catalogObj;
		self::$_catalog = &$catalogObj->options;

		return true;

	} // End _load_catalog().



	/* _load_tables()
	 *
	 * Loads the tables signatures into a class variable for usage by functions.
	 *
	 */
	private static function _load_tables( $force_reload = false, $get_contents_only = false ) {

		if ( is_object( self::$_tablesObj ) && ( true !== $force_reload ) ) {
			return self::$_tablesObj;
		}
		if ( true === $force_reload ) {
			unset( self::$_tablesObj );
		}

		$read_only = false;
		$ignore_lock = self::_fileoptions_lock_ignore_timeout_value();
		if ( true === $get_contents_only ) {
			$read_only = true;
			$ignore_lock = false;
		}

		require_once( pb_backupbuddy::plugin_path() . '/classes/fileoptions.php' );
		$tablesObj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'live/tables-' . pb_backupbuddy::$options['log_serial'] . '.txt', $read_only, $ignore_lock, $create_file = true, $live_mode = true );
		if ( true !== ( $result = $tablesObj->is_ok() ) ) {
			pb_backupbuddy::status( 'error', 'Error #435554390. Unable to create or access fileoptions file. Details: `' . $result . '`. Waiting a moment before ending.' );
			sleep( 3 ); // Wait a moment to give time for temporary issues to resolve.
			return false;
		}

		// Set defaults.
		if ( ! is_array( $tablesObj->options ) ) {
			$tablesObj->options = array();
		}

		// Getting contents only.
		if ( true === $get_contents_only ) {
			return $tablesObj->options;
		}

		// Set class variables with references to object and options within.
		self::$_tablesObj = &$tablesObj;
		self::$_tables = &$tablesObj->options;

		return true;

	} // End _load_tables().



	/* set_file_backed_up()
	 *
	 * Marks a file as being sent to server after a successful remote file transfer. Handles files and database table SQL dump confirmation.
	 *
	 * @param	string	$file			Filename relative to ABSPATH. Should have leading slash.
	 * @param	string	$database_file	Blank for normal file. Database table name if a database file.
	 *
	 */
	public static function set_file_backed_up( $file, $database_tables = '' ) {

		if ( false === self::_load_state() ) {
			pb_backupbuddy::status( 'warning', 'Warning #489348344: set_file_backed_up() could not load state.' );
			return false;
		}

		if ( '' == $database_tables ) { // Normal file.
			if ( false === self::_load_catalog() ) {
				return false;
			}

			if ( ! isset( self::$_catalog[ $file ] ) ) {
				pb_backupbuddy::status( 'warning', 'Warning #28393833: Unable to set file `' . $file . '` as backed up. It was not found in the catalog. Was it deleted?' );
				return false;
			}
		}

		pb_backupbuddy::status( 'details', 'Saving catalog that file `' . $file . '` has been backed up.' );

		$dailyStatsRef = &self::_get_daily_stats_ref();

		// Update catalog and stats.
		if ( '' != $database_tables ) { // Database table; not a normal file.
			if ( false === self::_load_tables() ) {
				return false;
			}

			if ( 'backupbuddy_dat.php' == $database_tables ) {
				return true;
			} elseif ( 'importbuddy.php' == $database_tables ) {
				return true;
			}
			self::$_tables[ $database_tables ]['b'] = time(); // Time backed up to server.
			self::$_tables[ $database_tables ]['t'] = 0; // Reset try (send attempt) counter back to zero since it succeeded.

			self::$_state['stats']['tables_pending_send']--;
			if ( self::$_state['stats']['tables_pending_send'] < 0 ) { // Dont't go below zero. :)
				self::$_state['stats']['tables_pending_send'] = 0;
				pb_backupbuddy::status( 'details', 'Tables pending send tried to go below zero. Prevented.' );
			}

			// Daily total and size updates for stats.
			$dailyStatsRef['d_t']++;
			$dailyStatsRef['d_s'] += self::$_tables[ $database_tables ]['s'];

			self::$_tablesObj->save();
		} else { // Normal file.
			self::$_catalog[ $file ]['b'] = time(); // Time backed up to server.
			unset( self::$_catalog[ $file ]['t'] ); // Reset try (send attempt) counter back to zero since it succeeded.

			self::$_state['stats']['files_pending_send']--;
			if ( self::$_state['stats']['files_pending_send'] < 0 ) { // Don't go below zero. :)
				self::$_state['stats']['files_pending_send'] = 0;
				pb_backupbuddy::status( 'details', 'Files pending send tried to go below zero. Prevented.' );
			}

			// Daily total and size updates for stats.
			$dailyStatsRef['f_t']++;
			$dailyStatsRef['f_s'] += self::$_catalog[ $file ]['s'];

			self::$_catalogObj->save();
		}

		self::$_stateObj->save();

		return true;

	} // End set_file_backed_up().


	public static function &_get_daily_stats_ref() {
		if ( false === self::_load_state() ) {
			pb_backupbuddy::status( 'warning', 'Warning #3298233298: _grab_daily_stats() could not load state.' );
			$return_var = false; // Only variable references should be returned by reference
			return $return_var;
		}

		if ( ! isset( self::$_state['stats']['daily'] ) ) {
			self::$_state['stats']['daily'] = array();
		}

		// Load defaults for today.
		$stamp = date( 'y-m-d' );
		if ( ! isset( self::$_state['stats']['daily'][ $stamp ] ) ) {
			self::$_state['stats']['daily'][ $stamp ] = self::$_statsDailyDefaults;
		} else {
			self::$_state['stats']['daily'][ $stamp ] = array_merge( self::$_statsDailyDefaults, self::$_state['stats']['daily'][ $stamp ] );
		}

		// Don't let too many days build up in stats.
		if ( count( self::$_state['stats']['daily'] ) > self::MAX_DAILY_STATS_DAYS ) {
			$remove_count = count( self::$_state['stats']['daily'] ) - self::MAX_DAILY_STATS_DAYS;
			$output = array_slice( self::$_state['stats']['daily'], $remove_count );

			self::$_state['stats']['daily'] = $output;
			self::$_stateObj->save();
		}

		return self::$_state['stats']['daily'][ $stamp ];
	} // End _get_daily_stats_ref().


	public static function _step_wait_on_transfers() {
		$sleep_time = 10;

		if ( false === self::_load_state() ) {
			pb_backupbuddy::status( 'warning', 'Warning #4383434043: _step_wait_on_transfers() could not load state.' );
			return false;
		}

		if ( ( self::$_state['stats']['files_pending_send'] > 0 ) || ( self::$_state['stats']['tables_pending_send'] > 0 ) ) {
			if ( 0 == self::$_state['stats']['wait_on_transfers_start'] ) {
				self::$_state['stats']['wait_on_transfers_start'] = microtime( true ); // Make sure timestamp set to prevent infinite loop.
				self::$_stateObj->save();
			}

			$destination_settings = self::get_destination_settings();
			if ( ( time() - self::$_state['stats']['wait_on_transfers_start'] ) > ( $destination_settings['max_wait_on_transfers_time'] * 60 ) ) {
				pb_backupbuddy::status( 'warning', 'Ran out of max time (`' . round( ( ( time() - self::$_state['stats']['wait_on_transfers_start'] ) / 60 ) ) . '` of `' . $destination_settings['max_wait_on_transfers_time'] . '` max mins) waiting for pending transfers to finish. Giving up until next periodic restart.' );
				return false;
			}

			pb_backupbuddy::status( 'details', 'Sleeping for `' . $sleep_time . '` secs to wait on `' . self::$_state['stats']['files_pending_send'] . '` file and `' . self::$_state['stats']['tables_pending_send'] . '` database table transfers. Closing state.' );
			self::$_stateObj = ''; // Close stateObj so sleeping won't hinder other operations.
			sleep( $sleep_time );

			// Re-open state.
			if ( false === self::_load_state() ) {
				return false;
			}

			if ( ( ! is_numeric( self::$_state['stats']['files_pending_send'] ) ) || ( ! is_numeric( self::$_state['stats']['tables_pending_send'] ) ) ) {
				pb_backupbuddy::status( 'error', 'Error #83989484: files_pending_send or tables_pending_send missing numeric value. State details: `' . print_r( self::$_state, true ) . '`.' );
			}

			pb_backupbuddy::status( 'details', '`' . self::$_state['stats']['files_pending_send'] . '` files and `' . self::$_state['stats']['tables_pending_send'] . '` database tables are still pending transfer after sleeping. Waiting for transfers to finish before creating Snapshot (`' . round( ( time() - self::$_state['stats']['wait_on_transfers_start'] ) / 60 ) . '` of `' . $destination_settings['max_wait_on_transfers_time'] . '` max mins elapsed).' );
			$waitingListLimit = 5;

			// Show some of the files pending send for troubleshooting.
			$files_pending_send_file = backupbuddy_core::getLogDirectory() . 'live/files_pending_send-' . pb_backupbuddy::$options['log_serial'] . '.txt';
			if ( self::$_state['stats']['files_pending_send'] > 0 ) {
				pb_backupbuddy::status( 'details', 'Files pending send (`' . count( self::$_state['stats']['tables_pending_send'] ) . '`).' );
				if ( false !== self::_load_catalog() ) {
					$waitingFileList = array();
					foreach( self::$_catalog as $catalogFilename => $catalogFile ) {
						if ( 0 == $catalogFile['b'] ) { // Not yet transferred.
							$tries = 0;
							if ( isset( $catalogFile['t'] ) ) {
								$tries = $catalogFile['t'];
							}
							$waitingFileList[] = $catalogFilename . ' (' . $tries . ' send tries)';
						}
						if ( count( $waitingFileList ) > $waitingListLimit ) {
							break;
						}
					}
					if ( count( $waitingFileList ) > 0 ) {
						pb_backupbuddy::status( 'details', 'List of up to `' . $waitingListLimit . '` of `' . self::$_state['stats']['files_pending_send'] . '` pending file sends: ' . implode( '; ', $waitingFileList ) );

						if ( false === @file_put_contents( $files_pending_send_file, implode( "\n", $waitingFileList ) ) ) {
							// Unable to write.
						}
					}
				} else {
					pb_backupbuddy::status( 'details', 'Catalog not ready for preview of pending file list. Skipping.' );
				}
			} else { // No files pending. Deleting/wiping pending file.
				pb_backupbuddy::status( 'details', 'No files pending send. Wiping.' );
				@file_put_contents( $tables_pending_send_file, '' );
				@unlink( $files_pending_send_file );
			}

			// Show some of the tables pending send for troubleshooting.
			$tables_pending_send_file = backupbuddy_core::getLogDirectory() . 'live/tables_pending_send-' . pb_backupbuddy::$options['log_serial'] . '.txt';
			if ( self::$_state['stats']['tables_pending_send'] > 0 ) {
				pb_backupbuddy::status( 'details', 'Tables pending send (`' . count( self::$_state['stats']['tables_pending_send'] ) . '`).' );
				if ( false !== self::_load_tables() ) {
					$waitingTableList = array();
					foreach( self::$_tables as $tableName => $table ) {
						if ( 0 == $table['b'] ) { // Not yet transferred.
							$waitingTableList[] = $tableName . ' (' . $table['t'] . ' send tries)';
						}
						if ( count( $waitingTableList ) > $waitingListLimit ) {
							break;
						}
					}
					if ( count( $waitingTableList ) > 0 ) {
						pb_backupbuddy::status( 'details', 'List of up to `' . $waitingListLimit . '` of `' . self::$_state['stats']['tables_pending_send'] . '` pending table sends: ' . implode( '; ', $waitingTableList ) );

						if ( false === @file_put_contents( $tables_pending_send_file, implode( "\n", $waitingTableList ) ) ) {
							// Unable to write.
						}
					}
				} else {
					pb_backupbuddy::status( 'details', 'Table catalog not ready for preview of pending table list. Skipping.' );
				}
			} else { // No tables pending send. Deleting/wiping pending file.'
				pb_backupbuddy::status( 'details', 'No tables pending send. Wiping.' );
				@file_put_contents( $tables_pending_send_file, '' );
				@unlink( $tables_pending_send_file );
			}

			backupbuddy_live::queue_step( $step = 'wait_on_transfers', $args = array(), $skip_run_now = true );
			return true;
		}

		// No more files are pending. Jumps back to snapshot.
		return true;
	} // End _step_wait_on_transfers();



	/* _step_run_remote_snapshot()
	 *
	 * Step to run a remote snapshot if it's approximately time to do so.
	 *
	 */
	public static function _step_run_remote_snapshot() {

		if ( false === self::_load_state() ) {
			return false;
		}

		// If not all files have uploaded, skip snapshot for now.
		if ( ( self::$_state['stats']['files_pending_send'] > 0 ) || ( self::$_state['stats']['tables_pending_send'] > 0 ) ) {
			pb_backupbuddy::status( 'details', '`' . self::$_state['stats']['files_pending_send'] . '` files and `' . self::$_state['stats']['tables_pending_send'] . '` database tables are still pending transfer. Waiting for transfers to finish before creating Snapshot.' );
			self::$_state['stats']['wait_on_transfers_start'] = microtime( true );
			backupbuddy_live::queue_step( $step = 'wait_on_transfers', $args = array(), $skip_run_now = true );
			return true;
		}

		if ( ( 0 == self::$_state['stats']['files_total_count'] ) || ( 0 == self::$_state['stats']['tables_total_count'] ) ) {
			$error = 'Error #3489349834: Made it to the snapshot stage but there are zero files and/or tables. Both files and database table counts should be greater than zero. Halting to protect backup integrity. Files: `' . self::$_state['stats']['files_total_count'] . '`. Tables: `' . self::$_state['stats']['tables_total_count'] . '`.';
			backupbuddy_core::addNotification( 'live_error', 'BackupBuddy Stash Live Error', $error );
			return $error;
		}

		if ( false !== self::$_state['stats']['manual_snapshot'] ) {
			pb_backupbuddy::status( 'details', 'Manual snapshot requested at `' . pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( self::$_state['stats']['manual_snapshot'] ) ) . '` (' . pb_backupbuddy::$format->time_ago( self::$_state['stats']['manual_snapshot'] ) . ' ago). Triggering remote snapshot now.' );
			$trigger = 'manual';
		} else {
			$trigger = 'automatic';

			$destination_settings = self::get_destination_settings();
			$schedule_times = wp_get_schedules();
			if ( ! isset( $schedule_times[ $destination_settings['remote_snapshot_period'] ] ) ) {
				pb_backupbuddy::status( 'error', 'Error #383927494: Invalid schedule interval/period `' . $destination_settings['remote_snapshot_period'] . '`. Not found in wp_get_schedules().' );
				return false;
			}
			$delay_between_runs = $schedule_times[ $destination_settings['remote_snapshot_period'] ]['interval'];
			$adjusted_delay_between_runs = ( $delay_between_runs - self::REMOTE_SNAPSHOT_PERIOD_WIGGLE_ROOM );
			$time_since_last_run = microtime( true ) - self::$_state['stats']['last_remote_snapshot'];
			pb_backupbuddy::status( 'details', 'Period between remote snapshots: `' . $destination_settings['remote_snapshot_period'] . '` (`' . $delay_between_runs . '` seconds). Time since last run: `' . $time_since_last_run . '`. Allowed to run `' . self::REMOTE_SNAPSHOT_PERIOD_WIGGLE_ROOM . '` secs early. Adjusted min delay between runs: `' . $adjusted_delay_between_runs . '`.' );

			if ( $time_since_last_run < $adjusted_delay_between_runs ) {
				pb_backupbuddy::status( 'details', 'Not enough time has passed since last remote snapshot. Skipping this pass.' );
				return true;
			}

			// Made it here so trigger remote snapshot.
			pb_backupbuddy::status( 'details', 'Enough time has passed since last remote snapshot. Triggering remote snapshot now.' );
		}

		$response = backupbuddy_live_periodic::_run_remote_snapshot( $trigger );

		if ( ! is_array( $response ) ) {
			$error = 'Error #2397734: Unable to initiate Live snapshot. See log above for details or here: `' . $response . '`.';
			pb_backupbuddy::status( 'error', $error );
			backupbuddy_core::addNotification( 'live_error', 'BackupBuddy Stash Live Error', $error );
			return false;
		} else { // Either triggered snapshot or one already running.
			if ( true === $response['success'] ) { // Triggered new snapshot.

				// Clear last troubleshooting alerts file.
				$troubleshooting_alerts_file = backupbuddy_core::getLogDirectory() . 'live/troubleshooting_alerts-' . pb_backupbuddy::$options['log_serial'] . '.txt';
				if ( @file_exists( $troubleshooting_alerts_file ) ) {
					@unlink( $troubleshooting_alerts_file );
				}

				$snapshot_id = $response['snapshot'];
				backupbuddy_live_periodic::update_last_remote_snapshot_time( $snapshot_id );
				pb_backupbuddy::status( 'details', 'Triggered new remote snapshot with ID `' . $snapshot_id . '`.' );

				// TODO: Keeping in place until new tmtrim-settings and passing tmtrim data with snapshot trigger is verified. Deprecating as of 7.0.5.5.
				// Schedule to run trim cleanup.
				$cronArgs = array();
				$schedule_result = backupbuddy_core::schedule_single_event( time() + ( 60*60 ), 'live_after_snapshot', $cronArgs ); // 1hr
				if ( true === $schedule_result ) {
					pb_backupbuddy::status( 'details', 'Next Live trim cron event scheduled.' );
				} else {
					pb_backupbuddy::status( 'error', 'Next Live trim cron event FAILED to be scheduled.' );
				}

				if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
					pb_backupbuddy::status( 'details', 'Spawning cron now.' );
					update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
					spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
				}


				return true;
			} elseif ( false === $response['success'] ) { // Failed to trigger new snapshot. Most likely one is already in progress.
				if ( isset( $response['snapshot'] ) ) {
					pb_backupbuddy::status( 'details', 'Did NOT trigger a new snapshot. One is already in progress with ID `' . $response['snapshot'] . '`. Details: `' . print_r( $response, true ) . '`.' );
					return true;
				} else {
					pb_backupbuddy::status( 'error', 'Error #2898923: Something went wrong triggering snapshot. Details: `' . print_r( $response ) . '`.' );
					return false;
				}
			} else {
				pb_backupbuddy::status( 'error', 'Error #3832792397: Something went wrong triggering snapshot. Details: `' . print_r( $response ) . '`.' );
				return false;
			}
		}

		pb_backupbuddy::status( 'error', 'Error #8028434. This should never happen. This code should not be reached.' );
		return false;

	} // End _step_run_remote_snapshot().



	/* _run_remote_snapshot()
	 *
	 * Triggers a remote snapshot.
	 *
	 */
	public static function _run_remote_snapshot( $trigger = 'unknown' ) {

		if ( false === self::_load_state() ) {
			return false;
		}

		$destination_settings = backupbuddy_live_periodic::get_destination_settings();

		// Send email notification?
		if ( ( '1' == $destination_settings['send_snapshot_notification'] ) || ( 0 == self::$_state['stats']['first_completion'] ) ) { // Email notification enabled _OR_ it's the first snapshot for this site.
			if ( '' != $destination_settings['email'] ) {
				$email = $destination_settings['email'];
			} else {
				pb_backupbuddy::status( 'details', 'Snapshot set to send email notification to account. Send notification?: `' . $destination_settings['send_snapshot_notification'] . '`. First completion: `' . self::$_state['stats']['first_completion'] . '`.' );
				$email = 'account';
			}
		} else {
			pb_backupbuddy::status( 'details', 'Snapshot set not to send email notification.' );
			$email = 'none';
		}

		$additionalParams = array(
			'ibpass' => '', // Gets set below.
			'email' => $email, // Valid options: email@address.com, 'none', 'account'
			'stash_copy' => true,
			'trim' => backupbuddy_live::get_archive_limit_settings_array( false ),
			//'debug' => true,
		);
		if ( '' != pb_backupbuddy::$options['importbuddy_pass_hash'] ) {
			$additionalParams['ibpass'] = pb_backupbuddy::$options['importbuddy_pass_hash'];
		}
		if ( false !== ( $timezone = self::tz_offset_to_name( get_option('gmt_offset') ) ) ) {
			$additionalParams['timezone'] = $timezone;
		}
		require_once( pb_backupbuddy::plugin_path() . '/destinations/live/init.php' );

		$response = pb_backupbuddy_destination_live::stashAPI( $destination_settings, 'live-snapshot', $additionalParams, $blocking = true, $passthru_errors = true );

		self::$_state['stats']['last_remote_snapshot_trigger'] = $trigger;
		self::$_state['stats']['last_remote_snapshot_response'] = $response;
		self::$_state['stats']['last_remote_snapshot_response_time'] = microtime( true );
		self::$_state['stats']['manual_snapshot'] = false; // Set false no matter what.

		if ( pb_backupbuddy::$options['log_level'] == '3' ) { // Full logging enabled.
			pb_backupbuddy::status( 'details', 'live-snapshot response due to logging level: `' . print_r( $response, true ) . '`. Call params: `' . print_r( $additionalParams, true ) . ' `.' );
		}

		do_action( 'backupbuddy_run_remote_snapshot_response', $response );

		return $response;

	} // End _run_remote_snapshot().



	/* update_last_remote_snapshot_time()
	 *
	 * Updates the timestamp for when the last remote snapshot was triggered to begin.
	 *
	 * @return	bool	True on success, else false.
	 */
	public static function update_last_remote_snapshot_time( $snapshot_id = '', $snapshot_response = '' ) {

		if ( false === self::_load_state() ) {
			return false;
		}

		self::$_state['stats']['last_remote_snapshot'] = microtime( true );
		self::$_state['stats']['last_remote_snapshot_id'] = $snapshot_id;
		self::$_state['stats']['manual_snapshot'] = false; // Set false no matter what.

		// First snapshot?
		if ( 0 == self::$_state['stats']['first_completion'] ) {
			self::$_state['stats']['first_completion'] = microtime( true );

			//$body = "Your first BackupBuddy Stash Live backup process has completed. Your first Snapshot has been placed in your BackupBuddy Stash storage < https://sync.ithemes.com >. From now on, we'll automatically backup any changes you make to your site.\n\nYour site is well on its way to a secure future in the safe hands of BackupBuddy Stash Live.";
			//wp_mail( get_option('admin_email'), __( 'Your first Live Backup is complete!', 'it-l10n-backupbuddy' ), $body, 'From: BackupBuddy <' . get_option('admin_email') . ">\r\n".'Reply-To: '.get_option('admin_email')."\r\n");
		}

		// Save state.
		self::$_stateObj->save();

		// Save BB core options to record last successful backup.
		pb_backupbuddy::$options['last_backup_finish'] = time();
		pb_backupbuddy::save();

		pb_backupbuddy::status( 'details', 'Time since remote snapshot ran updated.' );
		return true;

	} // End update_last_remote_snapshot_time().



	/* reset_last_activity()
	 *
	 * Resets the last activity timestamp to zero. For debugging.
	 *
	 */
	public static function reset_last_activity() {

		if ( false === self::_load_state() ) {
			return false;
		}

		self::$_state['stats']['last_activity'] = 0;
		self::$_stateObj->save();

		return true;

	} // End reset_last_activity().



	/* reset_file_audit_times()
	 *
	 * Resets the last file audit finish timestamp to zero. For debugging.
	 *
	 */
	public static function reset_file_audit_times() {

		if ( false === self::_load_state() ) {
			return false;
		}

		self::$_state['stats']['last_file_audit_start'] = 0;
		self::$_state['stats']['last_file_audit_finish'] = 0;
		self::$_stateObj->save();

		return true;

	} // End reset_file_audit_times().



	/* reset_first_completion()
	 *
	 * Resets the first completion timestamp to zero. For debugging.
	 *
	 */
	public static function reset_first_completion() {

		if ( false === self::_load_state() ) {
			return false;
		}

		self::$_state['stats']['first_completion'] = 0;
		self::$_stateObj->save();

		return true;

	} // End reset_first_completion().



	/* reset_last_remote_snapshot()
	 *
	 * Resets the last remote snapshot timestamp to zero. For debugging.
	 *
	 */
	public static function reset_last_remote_snapshot() {

		if ( false === self::_load_state() ) {
			return false;
		}

		self::$_state['stats']['last_remote_snapshot'] = 0;
		self::$_stateObj->save();

		return true;

	} // End reset_last_activity().



	/* reset_send_attempts()
	 *
	 * Resets the send attempt counter for all files back to zero. For debugging.
	 *
	 */
	public static function reset_send_attempts() {

		pb_backupbuddy::status( 'details', 'About to reset send attempt counter for all catalog files.' );
		if ( false === self::_load_catalog() ) {
			return false;
		}

		if ( false === self::_load_state() ) {
			return false;
		}

		foreach( self::$_catalog as $signatureFile => &$signatureDetails ) {
			if ( isset( $signatureDetails['t'] ) ) {
				unset( $signatureDetails['t'] );
			}
		}

		self::$_state['stats']['recent_send_fails'] = 0;
		self::$_state['stats']['last_send_fail'] = 0;
		self::$_state['stats']['last_send_fail_file'] ='';

		self::$_catalogObj->save();
		self::$_stateObj->save();
		pb_backupbuddy::status( 'details', 'Finished resetting send attempt counter for all catalog files.' );

		return true;

	} // End reset_send_attempts().



	/* delete_catalog()
	 *
	 * Deletes the catalog file. For debugging.
	 *
	 */
	public static function delete_catalog() {

		$catalogFile = backupbuddy_core::getLogDirectory() . 'live/catalog-' . pb_backupbuddy::$options['log_serial'] . '.txt';
		@unlink( $catalogFile );
		sleep( 1 );
		@unlink( $catalogFile );
		sleep( 1 );
		@unlink( $catalogFile );

		if ( file_exists( $catalogFile ) ) {
			pb_backupbuddy::alert( 'Error #3927273: Unable to delete catalog file `' . $catalogFile . '`. Check permissions or manually delete.' );
		} else {
			pb_backupbuddy::alert( 'Catalog deleted.' );
		}

		return true;

	} // End delete_catalog().



	/* delete_state()
	 *
	 * Deletes the state file. For debugging.
	 *
	 */
	public static function delete_state() {

		$stateFile = backupbuddy_core::getLogDirectory() . 'live/state-' . pb_backupbuddy::$options['log_serial'] . '.txt';
		@unlink( $stateFile );
		sleep( 1 );
		@unlink( $stateFile );
		sleep( 1 );
		@unlink( $stateFile );

		if ( file_exists( $stateFile ) ) {
			pb_backupbuddy::alert( 'Error #434554: Unable to delete state file `' . $stateFile . '`. Check permissions or manually delete.' );
		} else {
			pb_backupbuddy::alert( 'State file deleted.' );
		}

		return true;

	} // End delete_state().



	/* get_stats()
	 *
	 * Returns CONTENTS of state. Not a fileoptions object.
	 *
	 */
	public static function get_stats() {

		return self::_load_state( $force_load = false, $get_contents_only = true );

	} // End get_stats();



	/* get_catalog()
	 *
	 * Returns CONTENTS of catalog. Not a fileoptions object.
	 *
	 */
	public static function get_catalog( $force_reload = null ) {

		return self::_load_catalog( $force_reload, $get_contents_only = true );

	} // End get_catalog().



	/* get_tables()
	 *
	 * Returns CONTENTS of tables catalog. Not a fileoptions object.
	 *
	 */
	public static function get_tables( $force_reload = null ) {

		return self::_load_tables( $force_reload, $get_contents_only = true );

	} // End get_tables().



	/* _truncate_log()
	 *
	 * Truncates the beginning of the log if it is getting too large.
	 *
	 */
	private static function _truncate_log() {

		// Truncate large log.
		$sumLogFile = backupbuddy_core::getLogDirectory() . 'status-live_periodic_' . pb_backupbuddy::$options['log_serial'] . '.txt';
		$max_log_size = pb_backupbuddy::$options['max_site_log_size'] * 1024 * 1024;
		backupbuddy_core::truncate_file_beginning( $sumLogFile, $max_log_size, 50 );

	} // End _truncate_log().



	/* backup_catalog()
	 *
	 * Backs up the catalog file for restore if it gets corrupted (eg due to process being killed mid-write).
	 *
	 */
	public static function backup_catalog() {
		pb_backupbuddy::status( 'details', 'About to backup catalog file.' );

		$catalog_file = backupbuddy_core::getLogDirectory() . 'live/catalog-' . pb_backupbuddy::$options['log_serial'] . '.txt';
		if ( ! file_exists( $catalog_file ) ) {
			return false;
		}

		// Create lock file. If this file exists when restoring a backed up catalog then we cannot trust the backup.
		if ( false === @touch( $catalog_file . '.bak.lock' ) ) {
			pb_backupbuddy::status( 'error', 'Error #43849344: Unable to create catalog backup lock file.' );
			return false;
		}

		// Make copy of catalog file.
		if ( false === @copy( $catalog_file, $catalog_file . '.bak' ) ) {
			pb_backupbuddy::status( 'error', 'Error #238932893: Unable to backup catalog file.' );
			return false;
		}

		// Remove lock file since copy succeeded.
		if ( false === @unlink( $catalog_file . '.bak.lock' ) ) {
			pb_backupbuddy::status( 'error', 'Error #43434549: Unable to remove catalog backup lock file.' );
			return false;
		}

		pb_backupbuddy::status( 'details', 'Catalog file backed up.' );
		return true;
	} // End backup_catalog().


	/*	shutdown_function()
	 *
	 *	Used for catching fatal PHP errors during backup to write to log for debugging.
	 *
	 *	@return		null
	 */
	public static function shutdown_function() {

		// Get error message.
		// Error types: http://php.net/manual/en/errorfunc.constants.php
		$e = error_get_last();
		//error_log( print_r( $e, true ) );

		if ( $e === NULL ) { // No error of any kind.
			return;
		} else { // Some type of error.
			if ( !is_array( $e ) || ( $e['type'] != E_ERROR ) && ( $e['type'] != E_USER_ERROR ) ) { // Return if not a fatal error.
				return;
			}
		}

		// Calculate log directory.
		$log_directory = backupbuddy_core::getLogDirectory(); // Also handles when importbuddy.
		$main_file = $log_directory . 'log-' . pb_backupbuddy::$options['log_serial'] . '.txt';

		// Determine if writing to a serial log.
		if ( pb_backupbuddy::$_status_serial != '' ) {
			$serial_files = array();
			$statusSerials = pb_backupbuddy::$_status_serial;
			if ( ! is_array( $statusSerials ) ) {
				$statusSerials = array( $statusSerials );
			}
			foreach( $statusSerials as $serial ) {
				$serial_files[] = $log_directory . 'status-' . $serial . '_' . pb_backupbuddy::$options['log_serial'] . '.txt';
			}
			$write_serial = true;
		} else {
			$write_serial = false;
		}

		// Format error message.
		$e_string = "---\n" . __( 'Fatal PHP error encountered:', 'it-l10n-backupbuddy' ) . "\n";
		foreach( (array)$e as $e_line_title => $e_line ) {
			$e_string .= $e_line_title . ' => ' . $e_line . "\n";
		}
		$e_string .= "---\n";

		// Write to log.
		file_put_contents( $main_file, $e_string, FILE_APPEND );
		if ( $write_serial === true ) {
			foreach( $serial_files as $serial_file ) {
				@file_put_contents( $serial_file, $e_string, FILE_APPEND );
			}
		}

	} // End shutdown_function.



	public static function _calculateFileVars() {
		self::$_fileVars = array(
			'{t}' => str_replace( ABSPATH, '', backupbuddy_core::get_themes_root() ),
			'{p}' => str_replace( ABSPATH, '', backupbuddy_core::get_plugins_root() ),
			'{m}' => str_replace( ABSPATH, '', backupbuddy_core::get_media_root() ),
		);
	}



	// Replaced variables with file paths.
	public static function _varToFile( $string ) {
		if ( null === self::$_fileVars ) {
			self::_calculateFileVars();
		}

		foreach( self::$_fileVars as $fileVar => $fileValue ) {
			$string = str_replace( $fileVar, $fileValue, $string );
		}

		return $string;
	}


	// Replaces file paths with variables.
	public static function _fileToVar( $string ) {
		if ( null === self::$_fileVars ) {
			self::_calculateFileVars();
		}


		foreach( self::$_fileVars as $fileVar => $fileValue ) {
			$string = str_replace( $fileValue, $fileVar, $string );
		}

		return $string;
	}


	public static function get_signature_defaults() {
		return self::$_signatureDefaults;
	}


	public static function tz_offset_to_name($offset) {
        $offset *= 3600; // convert hour offset to seconds
        $abbrarray = timezone_abbreviations_list();
        foreach ($abbrarray as $abbr)
        {
                foreach ($abbr as $city)
                {
                        if ($city['offset'] == $offset)
                        {
                                return $city['timezone_id'];
                        }
                }
        }

        return FALSE;
	}



} // End class.

