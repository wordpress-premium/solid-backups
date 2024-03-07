<?php
/**
 * Solid Backups Stash Live Periodic Class.
 *
 * This handles all functionality on the Stash Live WP admin page.
 *
 * All actions are processed first by run_periodic_process(), which
 * determines the next step to take. If the step completes
 * successfully, the next step is scheduled.
 *
 * The Database steps create sql files located within
 * uploads/backupbuddy_temp/{serial}/live_db_snapshot/.
 *
 * Then a file is created in
 * uploads/pb_backupbuddy/live/tables-{serial}.txt which
 * is a list of those .sql files. This file is
 * used for tracking data about those .sql files (eg, size, send time, etc.).
 *
 * The Files steps create a list of files ("Catalog") to send to the Stash Live
 * server. This list is located in
 * /uploads/pb_backupbuddy/live/catalog-{serial}.txt.
 *
 * The "State" is the overall progress of the entire process.
 * It is stored in /uploads/pb_backupbuddy/live/state-{serial}.txt.
 *
 * The Jump Step transient is used to interrupt the process and jump to a
 * specific step. During processing, if the transient has been set, it will
 * jump to that step and then clear the transient.
 *
 * Updated by John Regan in Sep/Oct 2023 for version 9.1.0.
 *
 * @author Dustin Bolton
 * @since 7.0
 *
 * @package BackupBuddy
 */

/**
 * Class backupbuddy_live_periodic
 */
class backupbuddy_live_periodic {

	const TRIGGER = 'live_periodic';

	// Number of seconds to fudge up the time elapsed to give a little wiggle room, so we don't accidentally hit the edge and time out.
	const TIME_WIGGLE_ROOM = 6;

	// [1min] Number of seconds to wait between rescanning a file. Don't get new stat or hash if this has not passed.
	const TIME_BETWEEN_FILE_RESCAN = 60;

	// [30min] Minimum seconds between the file scan step running. Audit takes a while due to all the API activity.
	const TIME_BETWEEN_FILE_AUDIT = 1800;

	// [4hrs] Number of seconds we will allow periodic remote snapshot to run early.
	const REMOTE_SNAPSHOT_PERIOD_WIGGLE_ROOM = 14400;

	// After this many seconds, re-save signature file.
	const SAVE_SIGNATURES_EVERY_X_SECONDS = 5;

	// Maximum number of times we will try to resend a file before skipping it.
	const MAX_SEND_ATTEMPTS = 4;

	// Minimum number of seconds between requests for kicking.
	const KICK_REQUEST_MINIMUM_PERIOD = 900;

	// If sending a file of this size or greater than this amount we will close out the catalog (and unlock) to prevent locking too long.
	const CLOSE_CATALOG_WHEN_SENDING_FILESIZE = 1048576;

	// When calculating send speed, if a file is smaller than this amount then we assume this minimum size, so we can calculate a better estimate.
	const MINIMUM_SIZE_THRESHOLD_FOR_SPEED_CALC = 512000;

	// Number of days to keep daily transfer stats for.
	const MAX_DAILY_STATS_DAYS = 14;

	// If adjusted max runtime is less than x seconds then log a WARNING.
	const MIN_ADJUSTED_MAX_RUNTIME_BEFORE_WARNING = 28;

	// If adjusted max runtime is less than x seconds then log an ERROR.
	const MIN_ADJUSTED_MAX_RUNTIME_BEFORE_ERROR = 18;

	// Cron group name.
	const CRON_GROUP = 'solid-backups-live-periodic';

	private static $_stateObj;
	private static $_state;
	private static $_catalogObj;
	private static $_catalog;
	private static $_tablesObj;
	private static $_tables;

	private static $_step_defaults = array(
		'data_version'            => 1,
		'function'                => 'daily_init',
		'args'                    => array(),
		'start_time'              => 0, // Time this function first ran.
		'last_run_start'          => 0, // Time that this function last began.
		'last_run_finish'         => 0, // Time that this function last finished (e.g. from chunking).
		'last_status'             => '', // User-friendly status message.
		'attempts'                => 0, // Number of times this step has been attempted to run.
		'chunks'                  => 0, // Number of times this step has chunked to continue so far.
	);

	private static $_stats_defaults = array(
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
		'last_file_audit_finish'             => 0, // Timestamp of the last completion of file audit (checks for remote files that should not exist + updates 'v' key timestamp when remote file was verified to exist).
		'last_file_audit_start'   => 0, // Timestamp of the last start of file audit.
		'last_filesend_startat'   => 0, // Position to pick up sending files at to prevent duplicate from race conditions.
		'last_kick_request'       => 0, // Last time the cron kicker was contacted.
		'recent_send_fails'       => 0, // Number of recent remote send failures. If this gets too high we give up sending until next restart of the periodic process.
		'last_send_fail'          => 0, // Timestamp of last send failure.
		'last_send_fail_file'     => '', // Filename of last send failure.
		'wait_on_transfers_start' => 0, // Timestamp we began waiting on transfers to finish before snapshot.
		'first_activity'          => 0, // Timestamp of very first Live activity.
		'last_activity'           => 0, // Timestamp of last periodic activity.
		'first_completion'        => 0, // Timestamp of first 100% completion.
		'manual_snapshot'         => false, // Whether a manual snapshot is requested/pending.
		'completed_this_run'      => array() // Array of steps completed this run.
	);

	private static $_stats_daily_defaults = array(
		'd_t' => 0, // Database total number of .sql files sent.
		'd_s' => 0, // Database bytes sent.
		'f_t' => 0, // Files total number sent (excluding .sql db files).
		'f_s' => 0, // Files total bytes send (excluding .sql db files).
	);

	// Default signatures in the catalog.
	private static $_signature_defaults = array(
		'r' => 0, // Rescan/refresh timestamp (int).
		'm' => 0, // Modified timestamp based on file mtime, NOT timestamp when signature was updated. (int)
		's' => 0, // Size in bytes. (int)
		'b' => 0, // Backed up to Live server time. 0 if NOT backed up to server yet.
		'v' => 0, // Verified via audit timestamp.
	);

	// Default table entries in the catalog.
	private static $_table_defaults = array(
		'a' => 0,     // Added timestamp. (int)
		'm' => 0,     // Modified timestamp. (int)
		'b' => 0,     // Backed up to Live server time. 0 if NOT backed up to server yet.
		's' => 0,     // Size in bytes. (int)
		't' => 0,     // Tries sending. AKA Transfer attempts.
		'd' => false, // Pending deletion.
	);

	// Function and the next function to run after it.
	private static $_next_function = array(
		'daily_init'                => 'database_snapshot',
		'database_snapshot'         => 'send_pending_db_snapshots',
		'send_pending_db_snapshots' => 'process_table_deletions',
		'process_table_deletions'   => 'update_files_list',
		'update_files_list'         => 'update_files_signatures',
		'update_files_signatures'   => 'process_file_deletions',
		'process_file_deletions'    => 'send_pending_files',
		'send_pending_files'        => 'audit_remote_files',
		'audit_remote_files'        => 'run_remote_snapshot',
		'wait_on_transfers'         => 'run_remote_snapshot', // Only jumped to via queue if files remain prior to snapshot creation.
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

	private static $_file_vars = null;

	/**
	 * Scan all files to find new, deleted, or modified files compared to what has been sent to Live.
	 *
	 * Note that the preferredStep will only run if a step higher in the chain is not already running.
	 * Eg: Use to continue sending files UNLESS we have already looped back to starting over the periodic steps.
	 *
	 * @param string $preferred_step      Preferred step that we want to run.
	 * @param array  $preferred_step_args Arguments to pass to preferred step (if it runs).
	 *
	 * @return bool True on success, string error message on fatal failure, and array( 'status message', array( ARGS ) ) when chunking back to same step.
	 */
	public static function run_periodic_process( $preferred_step = '', $preferred_step_args = array() ) {
		global $wp_version;
		require_once( pb_backupbuddy::plugin_path() . '/destinations/live/live.php' );

		if ( ! class_exists( 'pb_backupbuddy_destinations' ) ) {
			// This can happen while live_pulse is running.
			require_once( pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php' );
		}

		$previous_status_serial = pb_backupbuddy::get_status_serial(); // Hold current serial.
		pb_backupbuddy::set_status_serial( self::TRIGGER ); // Redirect logging output to a certain log file.

		$liveID = backupbuddy_live::getLiveID();
		$logging_disabled = ( isset( pb_backupbuddy::$options['remote_destinations'][ $liveID ]['disable_logging'] ) && ( '1' == pb_backupbuddy::$options['remote_destinations'][ $liveID ]['disable_logging'] ) );

		if ( ! $logging_disabled ) {
			pb_backupbuddy::status( 'details', '-----' );
			pb_backupbuddy::status( 'details', 'Live periodic process starting with Solid Backups v' . pb_backupbuddy::settings( 'version' ) . ' with WordPress v' . $wp_version . '.' );
		}

		// Make sure we are not PAUSED.
		if ( '1' == pb_backupbuddy::$options['remote_destinations'][ $liveID ]['pause_periodic'] ) {
			pb_backupbuddy::status( 'details', 'Pausing periodic process.' );
			self::stop_live_pulse();
			self::remove_pending_events();
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

		// May try to trigger something already handled by the Pulse. If so, don't re-run it.
		if ( ! empty( $preferred_step ) && self::step_already_completed( $preferred_step ) ) {
			// Remove any events related to completed steps, as they are no longer needed.
			self::remove_completed_step_events();

			// This may be a remnant of a previous run (e.g., 'send_pending_files' with a loop count).
			// Remove this specific scheduled action with its specific args.
			backupbuddy_core::unschedule_event(
				backupbuddy_constants::CRON_HOOK,
				array(
					self::TRIGGER,
					array(
						$preferred_step,
						$preferred_step_args,
					)
				),
				self::CRON_GROUP
			);


			pb_backupbuddy::status( 'details', 'Step already completed. Skipping.' );
			return true;
		}

		// No PHP runtime calculated yet. Try to see if test is finished.
		if ( empty( pb_backupbuddy::$options['tested_php_runtime'] ) ) {
			backupbuddy_core::php_runtime_test_results();
		}

		// Update stats and save.
		if ( empty( self::$_state['step']['start_time'] ) ) {
			self::$_state['step']['start_time'] = microtime( true );
		}
		self::$_state['step']['last_run_start'] = microtime( true );

		// Load destination settings.
		$destination_settings = self::get_destination_settings();

		// If wait_on_transfers was the last step running and time limit has passed then we can start from Updating Files List.
		$transfers_start = self::$_state['stats']['wait_on_transfers_start'];
		$max_wait        = $destination_settings['max_wait_on_transfers_time'] * 60;
		if (
			( 'wait_on_transfers' === self::$_state['step']['function'] )
			&& ( $transfers_start > 0 )
			&& ( ( time() - $transfers_start ) > $max_wait )
			) {

			pb_backupbuddy::status(
				'warning',
				sprintf(
					__( 'Ran out of max time (`%1$s` of `%2$s` max mins) waiting for pending transfers to finish. Resetting back to Scanning Files.', 'it-l10n-backupbuddy' ),
					round( ( ( time() - $transfers_start ) / 60 ) ),
					$destination_settings['max_wait_on_transfers_time']
				)
			);
			self::$_state['step'] = self::$_step_defaults; // Clear step state.
			self::$_state['step']['function'] = 'update_files_list';
		}

		// Increment attempts if running the same function exactly as before. Set preferred_step args if we are indeed on this step.
		if (
			empty( $preferred_step )
			|| (
				( self::$_state['step']['function'] === $preferred_step )
				&& ( self::$_state['step']['args'] === $preferred_step_args )
			)
		) {
			self::$_state['step']['attempts']++;
		}

		if ( ! empty( $preferred_step ) ) {
			self::_set_next_step( $preferred_step, $preferred_step_args );
		}
		// Ensure the pulse is running.
		self::start_live_pulse();

		// If restart transient is set then restart the Live process all the way back to daily_init.
		// This is done when settings are saved, so they will take effect immediately.
		if ( false !== ( $jump_step = get_transient( 'backupbuddy_live_jump' ) ) ) {
			self::prep_for_jump_step( $jump_step );
		}

		// Check if a manual snapshot is requested.
		if ( false !== get_transient( 'backupbuddy_live_snapshot' ) ) {
			pb_backupbuddy::status( 'details', 'Manual Live Snapshot requested.' );
			delete_transient( 'backupbuddy_live_snapshot' );
			self::_request_manual_snapshot();
		}

		// Set first activity (creation of Live basically).
		if ( empty( self::$_state['stats']['first_activity'] ) ) {
			self::$_state['stats']['first_activity'] = time();
		}

		// Save attempt.
		self::$_stateObj->save();

		// Run step function and process results.
		$schedule_next_step = false;
		$step_function      = self::$_state['step']['function'];
		$run_function       = is_string( $step_function ) ? $step_function : '';
		$function_response  = false;

		pb_backupbuddy::status( 'details', 'Starting Live periodic function `' . $run_function . '`.' );
		if ( ! is_callable( 'backupbuddy_live_periodic::_step_' . $run_function ) ) {
			pb_backupbuddy::status( 'error', 'Error #439347494: Invalid step called: `' . $run_function . '` Unknown function: `self::_step_' . $run_function . '`.' );
		} else {

			// Run step function. Returns true on success, string error message on fatal failure, and array( 'status message', array( ARGS ) ) when chunking back to same step.
			$function_response = call_user_func_array( 'backupbuddy_live_periodic::_step_' . $run_function, self::$_state['step']['args'] );
		}

		self::$_state['step']['last_run_finish'] = microtime( true );
		self::$_state['stats']['last_activity']  = microtime( true );
		pb_backupbuddy::status( 'details', 'Ended Live periodic function `' . $run_function . '`.' );

		// Process step-function results.
		if ( is_array( $function_response ) ) {
			// Chunking back to same step since we got an array. Index 0 = last_status, index 1 = args. Keeps same step function.
			$schedule_next_step = true;
			self::$_state['step']['chunks']++;
			self::$_state['step']['last_status'] = $function_response[0];
			self::$_state['step']['args'] = $function_response[1];
			pb_backupbuddy::status( 'details', '`' . $run_function . '` function will be chunked.' );

			if ( ( 'update_files_list' !== $run_function ) && ( pb_backupbuddy::full_logging() ) ) { // Hide for update_files_list function due to its huge size.
				pb_backupbuddy::status( 'details', 'Response args due to logging level: `' . print_r( $function_response, true ) . '`.' );
			}

		} elseif ( is_string( $function_response ) ) { // Fatal error.
			$error = 'Error #32893283: One or more errors encountered running Live step function `' . $run_function . '` . Details: `' . $function_response . '`. See log above for more details.';
			self::write_error_messages( $error );

			if ( false === stristr( $function_response, 'Error' ) ) { // Make sure error-prefixed if not.
				$function_response = 'Error #489348: ' . $function_response;
			}
			self::$_state['step']['last_status'] = $function_response;

		} elseif ( true === $function_response ) { // Success finishing this step.
			pb_backupbuddy::status( 'details', 'Finished `' . $run_function . '` successfully.' );

			if ( ! self::step_already_completed( $run_function ) ) {
				self::$_state['stats']['completed_this_run'][] = $run_function;
			}

			// Interrupted by a jump for the next step.
			if ( false !== ( $jump_step = get_transient( 'backupbuddy_live_jump' ) ) ) {
				self::prep_for_jump_step( $jump_step );
				$schedule_next_step = true;
			} else { // Normal next step running (if any).
				if ( ! isset( self::$_next_function[ $run_function ] ) ) {
					$schedule_next_step = false;
					pb_backupbuddy::status( 'details', 'Function reported success. No more Live steps to directly run. Finishing until next periodic restart.' );
					self::$_state['step'] = self::$_step_defaults; // Clear step state.
				} else {
					$schedule_next_step = true;
					$next_function = self::$_next_function[ $run_function ];
					self::_set_next_step( $next_function );
					pb_backupbuddy::status( 'details', 'Function reported success. Scheduled next function to run, `' . $next_function . '`.' );
				}
			}

		} elseif ( false === $function_response ) {
			pb_backupbuddy::status(
				'error',
				sprintf(
					'Error #3298338: Live (periodic) function `%s` failed without error message. Ending Live periodic process for this run without running more steps. See log above for details.',
					$run_function
				)
			);
			$schedule_next_step = false;
		} else { // Unknown response.
			$error = sprintf(
				'Error #98238392: Unknown periodic Live step function response `%1$s` for function `%2$s`. Fatal error.',
				print_r( $function_response, true ),
				$run_function
			);
			self::write_error_messages( $error );
			self::$_state['step']['last_status'] = 'Error: ' . $function_response;
			$schedule_next_step = false;
		}

		// Save state.
		if ( is_object( self::$_stateObj ) ) {
			self::$_stateObj->save();
		} else {
			pb_backupbuddy::status(
				'warning',
				sprintf(
					'State object not expected type. Type: `%1$s`. Printed: `%2$s`.',
					gettype( self::$_stateObj ),
					print_r( self::$_stateObj, true )
				)
			);
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

		// Schedule the next step to run.
		if ( $schedule_next_step ) {

			// Schedule to run next chunk.
			$next_step      = ! empty( self::$_state['step']['function'] ) ? self::$_state['step']['function'] : '';
			$next_step_args = ! empty( self::$_state['step']['args'] )     ? self::$_state['step']['args']     : array( '' );

			pb_backupbuddy::status( 'details', 'Finished `' . $run_function . '`. Scheduling next step: ' . $next_step );
			self::schedule_next_event( $next_step, $next_step_args );

			// Schedule cron kicker (detects if it has not been too soon, so we can call this judiciously).
			self::_request_kick_cron();

		} else {
			// Nothing left to do for now.
			// Wait until the next time that the periodic functionality launches and restarts the process.
			pb_backupbuddy::status( 'details', 'No more steps remain for this run. Not scheduling next step.' );
			self::stop_live_pulse();
			self::remove_pending_events();
		}

		// Undo log redirect.
		pb_backupbuddy::set_status_serial( $previous_status_serial );
		return true;

	}

	/**
	 * Prepare for a jump step.
	 *
	 * @param array $jump_step Jump step array.
	 */
	private static function prep_for_jump_step( $jump_step ) {
		pb_backupbuddy::status( 'details', 'The Restart transient exists. Clearing.' );
		delete_transient( 'backupbuddy_live_jump' );

		$jump_step_name = $jump_step[0];
		$jump_step_args = array();

		if ( isset( $jump_step[1] ) && is_array( $jump_step[1] ) ) {
			$jump_step_args = $jump_step[1];
		}

		self::_set_next_step( $jump_step_name );
		pb_backupbuddy::status(
			'details',
			sprintf(
				'Reset next step to `%1$s` with args `%2$s` due to backupbuddy_live_jump transient.',
				$jump_step_name,
				print_r( $jump_step_args, true )
			)
		);
	}

	/**
	 * Restart the periodic process.
	 *
	 * Used in the UI for debugging.
	 *
	 * @param bool $force Whether to force the restart.
	 */
	public static function restart_periodic( $force = false ) {
		require_once( pb_backupbuddy::plugin_path() . '/destinations/live/live.php' );

		if ( false !== $destination_id = backupbuddy_live::getLiveID() ) {
			pb_backupbuddy::$options['remote_destinations'][ $destination_id ]['pause_periodic'] = '0';
			pb_backupbuddy::save();
		}

		delete_transient( 'backupbuddy_live_jump' );
		self::delete_all_events();
		self::remove_pending_events();
		self::start_live_pulse();


		if ( $force ) {
			self::queue_step( 'daily_init', array(), false, true );
			pb_backupbuddy::alert( __( 'Enabled Live Files Backup and Restarted Periodic Process (forced to run now).', 'it-l10n-backupbuddy' ) );
		} else {
			self::queue_step( 'daily_init', array() );
			pb_backupbuddy::alert( __( 'Enabled Live Files Backup and Restarted Periodic Process (only running if between steps or timed out).', 'it-l10n-backupbuddy' ) );
		}
	}

	/**
	 * Request that the Stash API kick our cron along for a certain amount of time.
	 *
	 * @return bool True on success, false on failure.
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
			$settings['destination_version'] = '3';

			// Request the kicks.
			require_once( pb_backupbuddy::plugin_path() . '/destinations/stash3/init.php' );
			call_user_func_array( array( 'pb_backupbuddy_destination_stash3', 'cron_kick_api' ), array( $settings ) );

			return true;
		}

		return false;
	}

	/**
	 * Set the next step to run, copying the current step into the state's prev_step key for reference/debugging.
	 *
	 * @param string $step_name            Name of the step to run.
	 * @param array  $args                 Arguments to pass to the step function.
	 * @param bool   $save_now_and_unload  Whether to save the state and unlock the fileoptions files.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function _set_next_step( $step_name, $args = array(), $save_now_and_unload = false ) {

		if ( false === self::_load_state() ) {
			pb_backupbuddy::status( 'error', 'Error #89383494: Unable to load state.' );
			return false;
		}

		if ( empty( $step_name ) || ! is_string( $step_name ) ) {
			pb_backupbuddy::status(
				'error',
				'Error #348934894: Invalid next step. Not scheduling step `' . print_r( $step_name, true ) . '`.'
			);
			return false;
		}

		self::$_state['prev_step']        = self::$_state['step']; // Hold the previous step for reference.
		self::$_state['step']             = self::$_step_defaults;
		self::$_state['step']['function'] = $step_name;
		self::$_state['step']['args']     = $args;

		if ( $save_now_and_unload ) {
			self::$_stateObj->save();
			self::$_stateObj->unlock();
		}

		return true;
	}

	/**
	 * Daily housekeeping at the beginning of the daily Live periodic process.
	 *
	 * @param bool $manual_snapshot Whether this is a manual snapshot.
	 *
	 * @return bool True on success, false on failure.
	 */
	private static function _step_daily_init( $manual_snapshot = false ) {
		$manual_snapshot = is_array( $manual_snapshot ) ? false : true;

		// Basic Event cleanup.
		self::delete_all_events();
		self::remove_pending_events();

		// remove_pending_events kills the pulse, so restart it.
		self::start_live_pulse();

		// Remove any jump step.
		delete_transient( 'backupbuddy_live_jump' );

		pb_backupbuddy::status(
			'details',
			sprintf(
				'Solid Backups v%1$s Live Daily Initialization -- %2$s.',
				pb_backupbuddy::settings( 'version' ),
				pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( time() ) )
			)
		);

		// Reset fail counts.
		self::$_state['stats']['recent_send_fails']   = 0;
		self::$_state['stats']['last_send_fail']      = 0;
		self::$_state['stats']['last_send_fail_file'] = '';
		self::$_state['stats']['completed_this_run']  = array();

		// Reset 't' key for all items in catalog, so we can try again. NOTE: Also saves state and catalog.
		self::reset_send_attempts();

		// Reset daily time we started waiting on unfinished transfers.
		self::$_state['stats']['wait_on_transfers_start'] = 0;

		self::$_stateObj->save();

		self::_request_kick_cron();

		// Truncate log if it is getting too large. Keeps newest half.
		self::_truncate_log();

		// Backup catalog file.
		self::backup_catalog();

		self::start_live_pulse();

		self::_request_manual_snapshot( $manual_snapshot );

		return true;
	}

	/**
	 * Request a manual snapshot.
	 *
	 * @param bool $cancel_running Whether to cancel a running snapshot.
	 *
	 * @return bool True on success, false on failure.
	 */
	private static function _request_manual_snapshot( $cancel_running = false ) {
		if ( false === self::_load_state() ) {
			return false;
		}

		pb_backupbuddy::status( 'details', 'Manual snapshot set for end of this pass.' );

		// It's possible an array is passed to $cancel_running.
		if ( true === $cancel_running ) {
			// Cancel snapshot.
			self::$_state['stats']['manual_snapshot'] = false;
		} else {
			// Request snapshot.
			self::$_state['stats']['manual_snapshot'] = microtime( true );
		}

		return true;
	}

	/**
	 * Find the next pending database snapshot file and tries to send it.
	 *
	 * @param int $start_at Index of table to start at.
	 *
	 * @return array|bool
	 */
	private static function _step_send_pending_db_snapshots( $start_at = 0 ) {

		if ( ( false === self::_load_state() ) || ( false === self::_load_tables() ) ) {
			return false;
		}

		if ( ! empty( $start_at ) ) {
			pb_backupbuddy::status( 'details', 'Resuming snapshot send at point `' . $start_at . '`.' );
		}

		require_once( pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php' );
		backupbuddy_live::update_db_live_activity_time();

		// On first pass create and send backupbuddy_dat.php and importbuddy.php.
		if ( empty( $start_at ) ) {
			self::create_dat_file();
		}

		$result = self::send_tables( self::$_tables, $start_at );

		// Schedule next run if we still have more files to potentially send.
		if ( $result['more_remain'] ) {
			return array( __( 'Sending queued tables', 'it-l10n-backupbuddy' ), array( $result['loop_count'] ) );
		} else { // No more files.
			return true;
		}

	}

	/**
	 * Send function for Tables.
	 *
	 * Although this is similar to send_files, there is enough
	 * difference to merit its own method.
	 *
	 * @param array $tables_array Array of tables to send.
	 * @param int   $start_at     Index of table to start at.
	 *
	 * @return array|bool Array of results.
	 */
	private static function send_tables( $tables_array, $start_at = 0 ) {

		// Loop through tables in the catalog.
		$already_backed_up   = 0;
		$check_count         = 0;
		$last_send_this_pass = false;
		$loop_count          = 0;
		$more_remain         = false;
		$sends_failed        = 0;
		$sends_multiparted   = 0;
		$send_size_sum       = 0;
		$sends_started       = 0;
		$sends_succeeded     = 0;
		$send_time_sum       = 0;
		$too_many_send_fails = 0;

		self::$_state['stats']['actively_sending'] = true;

		foreach( $tables_array as $table_name => &$details ) {
			$loop_count++;

			if ( ( 0 !== $start_at ) && ( $loop_count < $start_at ) ) {
				continue;
			}

			// If Live is disabled, we need to break this loop. Query the DB every 10 ints to make sure live still exists.
			if ( ( 0 === $loop_count % 10 ) && ( empty( self::live_is_enabled() ) ) ) {
				backupbuddy_core::clearLiveLogs( pb_backupbuddy::$options['log_serial'] );
				self::$_state['stats']['actively_sending'] = false;
				return false;
			}

			$check_count++;

			// If backed up after modified time then it's up-to-date. Skip.
			if ( $details['b'] > $details['m'] ) {
				pb_backupbuddy::status(
					'details',
					'Skipping send of table `' . $table_name . '` because it has already been sent since SQL file was made.'
				);
				$already_backed_up++;
				continue;
			}

			// Calculate table file.
			$table_file = backupbuddy_live::getLiveDatabaseSnapshotDir() . $table_name . '.sql';

			// If too many attempts have passed then skip.
			if ( isset( $details['t'] ) && ( $details['t'] >= self::MAX_SEND_ATTEMPTS ) ) {
				pb_backupbuddy::status(
					'error',
					sprintf(
						'Error #389328: This database file has failed transfer too many times. Skipping until next restart of periodic process. File: `%1$s`. Size: `%2$s`.',
						$table_file,
						pb_backupbuddy::$format->file_size( filesize( $table_file ) )
					)
				);
				$too_many_send_fails++;
				continue;
			}

			/*
			 * If too many remote sends have failed today
			 * give up for now, as something is likely wrong.
			 */
			if ( self::too_many_daily_send_fails() ) {
				return false;
			}

			// Load destination settings.
			$destination_settings = self::get_destination_settings();

			$can_send_more = self::can_send_more( $details, $send_size_sum, $send_time_sum );
			if ( ! $can_send_more ) {
				$more_remain = true;
				break;
			} else {
				// Run cleanup on send files.
				require_once( pb_backupbuddy::plugin_path() . '/classes/housekeeping.php' );
				backupbuddy_housekeeping::trim_remote_send_stats( 'send-live_', $destination_settings['max_send_details_limit'], '', true );

				// Increment try count for transfer attempts and save.
				$details['t']++;

				if ( ! is_object( self::$_tablesObj ) ) {
					self::_load_tables();
				}
				self::$_tablesObj->save();

				/*
				 * Send the file.
				 *
				 * After success sending, this Stash3 destination will automatically
				 * trigger the live_periodic processing if it is a multipart send.
				 *
				 * Regardless of success or failure we come back here to potentially
				 * send more files in the same PHP pass so small files don't each need
				 * their own PHP page run.
				 *
				 * Unless the process has restarted, in which case this will still be
				 * the 'next' function to run.
				 */
				$send_id = 'live_' . md5( $table_file ) . '-' . pb_backupbuddy::random_string( 6 );
				pb_backupbuddy::status( 'details', 'Live starting send function.' );
				$send_time_start = microtime( true );

				// Mark table as unsent just before sending new version.
				$details['b'] = 0;

				// Close catalog & state while sending if > X size to prevent collisions.
				if ( $details['s'] > self::CLOSE_CATALOG_WHEN_SENDING_FILESIZE ) {
					self::$_tablesObj = '';
					self::$_stateObj  = '';
				}

				// Set database table name into settings so send confirmation knows where to update sent timestamp.
				$destination_settings['_database_table'] = $table_name;

				// Send file to remote.
				$sends_started++;
				$result = pb_backupbuddy_destinations::send(
					$destination_settings,
					$table_file,
					$send_id,
					true,
					false,
					self::TRIGGER,
					backupbuddy_live::getLiveID()
				);

				// Re-open catalog (if closed).
				self::_load_tables();
				self::_load_state();

				$send_time_finish = microtime( true );
				if ( true === $result ) {
					$sends_succeeded++;
					$result_status = 'Success sending in single pass';
					$send_time_sum += ( $send_time_finish - $send_time_start );
					$send_size_sum += self::get_send_size( $details );
				} elseif ( false === $result ) {
					$sends_failed++;
					self::record_send_failure( $table_file );
					$result_status = 'Failure sending in single/first pass. See log above for error details. Failed sends today: `' . self::$_state['stats']['recent_send_fails'] . '`';
				} elseif ( is_array( $result ) ) {
					$sends_multiparted++;
					$result_status = 'Chunking commenced. Ending sends for this pass';
				}
				pb_backupbuddy::status( 'details', 'Live ended send database function. Status: ' . $result_status );
			}

		} // End foreach signatures.
		self::$_state['stats']['actively_sending'] = false;

		pb_backupbuddy::status(
			'details',
			sprintf(
				'Snapshot send details for this round: Checked `%1$s` items, transfers started: `%2$s`, transfers succeeded: `%3$s`, transfers multiparted: `%4$s`, transfers failed: `%5$s`, skipped because already backed up: `%6$s`, skipped because too many send failures: `%7$s`.',
				$loop_count,
				$sends_started,
				$sends_succeeded,
				$sends_multiparted,
				$sends_failed,
				$already_backed_up,
				$too_many_send_fails
			)
		);

		return array(
			'more_remain' => $more_remain,
			'loop_count'  => $loop_count,
		);
	}

	/**
	 * Create the .dat file and importbuddy.php
	 *
	 * @return bool True on success, false on failure.
	 */
	private static function create_dat_file() {
		if ( ( false === self::_load_state() ) || ( false === self::_load_tables() ) ) {
			return false;
		}

		// Render backupbuddy_dat.php
		$dat_file = backupbuddy_live::getLiveDatabaseSnapshotDir() . 'backupbuddy_dat.php';

		// Make sure directory exists.
		if ( ! file_exists( dirname( $dat_file ) ) ) {
			if ( false === pb_backupbuddy_filesystem::mkdir( dirname( $dat_file ) ) ) {
				pb_backupbuddy::status( 'warning', 'Warning #34893498434: Unable to mkdir("' . $dat_file . '").' );
			}
		}

		$table_sizes = array();
		foreach( self::$_tables as $table_name => $table ) {
			$table_sizes[ $table_name ] = $table['s'];
		}

		$table_results = backupbuddy_live::_calculate_table_includes_excludes_basedump();

		$dat_settings = array(
			'backup_type'          => 'live',
			'profile'              => array(),
			'serial'               => '',
			'breakout_tables'      => backupbuddy_live::calculateTables(),
			'table_sizes'          => $table_sizes,
			'force_single_db_file' => false,
			'trigger'              => 'live',
			'db_excludes'          => $table_results[1],
			'db_includes'          => $table_results[0],
		);

		pb_backupbuddy::status( 'details', 'Rendering DAT file to `' . $dat_file . '`.' );
		if ( ! is_array( backupbuddy_data_file()->render_dat_contents( $dat_settings, $dat_file ) ) ) {
			$error = 'Error #47949743: Since DAT file could not be written aborting. Check permissions writing to `' . $dat_file . '`.';
			pb_backupbuddy::status( 'error', $error );
			return $error;
		}

		// Render importbuddy.php
		$importbuddy_file = backupbuddy_live::getLiveDatabaseSnapshotDir() . 'importbuddy.php';
		pb_backupbuddy::status( 'details', 'Rendering importbuddy file to `' . $importbuddy_file . '`.' );
		if ( false === backupbuddy_core::importbuddy( $importbuddy_file, null ) ) { // NULL pass leaves #PASSWORD# placeholder in place.
			pb_backupbuddy::status( 'warning', 'Warning #348438345: Unable to render importbuddy. Not backing up importbuddy.php.' );
		}

		// Load destination settings.
		$destination_settings = self::get_destination_settings();

		// Send DAT file.
		$send_id = 'live_' . md5( $dat_file ) . '-' . pb_backupbuddy::random_string( 6 );
		$destination_settings['_database_table'] = 'backupbuddy_dat.php';

		if ( false === pb_backupbuddy_destinations::send(
			$destination_settings,
			$dat_file,
			$send_id,
			true,
			false,
			self::TRIGGER,
			backupbuddy_live::getLiveID()
			)
		) {
			$error = 'Error #389398: Unable to send DAT file to Live servers. See error log above for details.';
			self::write_error_messages( $error );
		}

		// Send importbuddy.
		$send_id = 'live_' . md5( $importbuddy_file ) . '-' . pb_backupbuddy::random_string( 6 );
		$destination_settings['_database_table'] = 'importbuddy.php';

		if ( false === pb_backupbuddy_destinations::send(
			$destination_settings,
			$importbuddy_file,
			$send_id,
			true,
			false,
			self::TRIGGER,
			backupbuddy_live::getLiveID()
			)
		) {
			pb_backupbuddy::status( 'error', 'Error #329327: Unable to send importbuddy file to Live servers. See error log above for details.' );
		}

		return true;
	}

	/**
	 * Check if Live is enabled.
	 *
	 * @return bool True if enabled, false if not.
	 */
	private static function live_is_enabled() {
		wp_cache_delete( 'pb_backupbuddy', 'options' );
		$options = get_option('pb_backupbuddy');

		if ( ! empty( $options['remote_destinations'] ) && ( is_array( $options['remote_destinations'] ) ) ) {
			foreach( $options['remote_destinations'] as $dest ) {
				if ( 'live' === $dest['type'] ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Clean up database table deletions.
	 *
	 * @param int $start_at Table to start at.
	 *
	 * @return bool|array True on success, false on failure. Array if we don't have time to continue and need to chunk.
	 */
	private static function _step_process_table_deletions( $start_at = 0 ) {
		$start_time = microtime( true );

		if ( ( false === self::_load_state() ) || ( false === self::_load_tables() ) ) {
			return false;
		}

		pb_backupbuddy::status( 'details', 'Starting table deletions at point: `' . $start_at . '`.' );
		backupbuddy_live::update_db_live_activity_time();

		/*
		 * Instruct Live server to delete all timestamped
		 * SQL files (in wp-content/uploads/backupbuddy_temp/SERIAL/_XXXXXXX.XX-tablename) with
		 * timestamps in the filename older than the one passed.
		 */
		if ( empty( $start_at ) ) {
			$timestamp = self::$_state['stats']['last_db_snapshot'];
			$destination_settings = self::get_destination_settings();
			$additional_params = array(
				'timestamp' => $timestamp,
				'test'      => false,
			);
			require_once( pb_backupbuddy::plugin_path() . '/destinations/live/init.php' );
			$response = pb_backupbuddy_destination_live::stashAPI( $destination_settings, 'live-cleanup', $additional_params );
			if ( ! is_array( $response ) ) {
				$error = 'Error #34387595: Unable to initiate Live timestamped database cleanup prior to timestamp `' . $timestamp . '`. Details: `' . $response . '`. Continuing anyway...';
				pb_backupbuddy::status( 'error', $error );
				//return false;
			} else {
				pb_backupbuddy::status(
					'details',
					'Deleted `' . count( $response['files'] ) . '` total timestamped live database files older than timestamp `' . $timestamp . '`.'
				);
				if ( pb_backupbuddy::full_logging() ) {
					pb_backupbuddy::status(
						'details',
						sprintf(
							'live-cleanup response due to logging level: `%1$s`. Call params: `%2$s`.',
							print_r( $response, true ),
							print_r( $additional_params, true )
						)
					);
				}
			}
		}

		// Loop through tables in the catalog.
		$loop_count     = 0;
		$check_count    = 0;
		$tables_deleted = 0;
		$last_save     = microtime( true );

		foreach( self::$_tables as $table => &$table_details ) {
			$loop_count++;

			if ( 0 !== $start_at && ( $loop_count < $start_at ) ) {
				continue;
			}

			$check_count++;

			// Skip non-deleted files.
			if ( ! self::is_pending_delete( $table_details ) ) {
				continue;
			}

			// Made it here then we will be deleting a table file.
			// Cancel any in-progress remote sends of deleted files.
			self::cancel_sending_deleted_files( $table, '#9034.328237', false );

			// If file has been backed up to server then we need to delete the remote file.
			if ( self::is_backed_up( $table ) ) {
				$destination_settings = self::get_destination_settings();
				$delete_file          = 'wp-content/uploads/backupbuddy_temp/SERIAL/' . $table . '.sql';
				$result               = pb_backupbuddy_destination_live::deleteFile( $destination_settings, $delete_file );
				if ( $result ) {
					pb_backupbuddy::status( 'details', 'Deleted remote table `' . $table . '`.' );
				} else {
					pb_backupbuddy::status( 'error', 'Error #8239833: Unable to delete remote table file `' . $delete_file . '`' );
				}
			}

			// Remove file from catalog and update state stats.
			unset( self::$_tables[ $table ] );
			self::$_state['stats']['tables_pending_delete'] = self::decrement( self::$_state['stats']['tables_pending_delete'] );
			$tables_deleted++;

			// See if it's time to save our progress so far.
			if ( self::is_time_to_save( $last_save ) ) {
				self::$_stateObj->save();
				self::$_tablesObj->save();
				$last_save = microtime( true );
			}

			// Do we have enough time to continue or do we need to chunk?
			if ( ! self::has_time_to_continue( $start_time, $destination_settings ) ) { // Running out of time! Chunk.
				self::$_tablesObj->save();
				pb_backupbuddy::status( 'details', 'Running out of time processing table deletions. Took `' . ( microtime( true ) - $start_time ) . '` seconds.' );
				return array( __( 'Processing deletions', 'it-l10n-backupbuddy' ), array( $loop_count - $tables_deleted ) );
			}

		} // end foreach.

		// Save and finish.
		self::$_stateObj->save();
		self::$_tablesObj->save();
		pb_backupbuddy::status(
			'details',
			sprintf(
				'Database table deletions processed. Checked `%1$s` files. Deleted `%2$s` files. Took `%3$s` seconds.',
				$check_count,
				$tables_deleted,
				( microtime( true ) - $start_time )
			)
		);
		return true;
	}

	/**
	 * Cancel any in-progress remote sends of deleted files.
	 *
	 * @param  string  $file        File to cancel sends of.
	 * @param  int     $error_code  Error code to use in status message.
	 * @param  bool    $ignore_lock Whether to ignore the lock on the fileoptions object.
	 */
	private static function cancel_sending_deleted_files( $file, $error_code, $ignore_lock = true ) {
		$fileoptions = glob( backupbuddy_core::getLogDirectory() . 'fileoptions/send-live_' . md5( $file ) . '-*.txt' );
		if ( empty( $fileoptions ) || ! is_array( $fileoptions ) ) {
			return;
		}

		foreach( $fileoptions as $file_name ) {
			$fileoption = new pb_backupbuddy_fileoptions( $file_name, false, $ignore_lock, false );
			if ( true !== ( $result = $fileoption->is_ok() ) ) {
				pb_backupbuddy::status(
					'error',
					sprintf(
						'Error #%1$s. Unable to access fileoptions data related to file `%2$s`. Skipping cleanup of file send. Error:  %3$s',
						$error_code,
						$file,
						$result
					)
				);
				continue;
			}

			// Something wrong with fileoptions. Let cleanup handle it later.
			if ( ! isset( $fileoption->options['status'] ) ) {
				continue;
			}

			// Don't do anything for success, failure, or already-marked as -1 finish time.
			if (
				( 'success' === $fileoption->options['status'] )
				|| ( 'failure' === $fileoption->options['status'] )
				|| ( -1 == $fileoption->options['finish_time'] )
				) {
				continue;
			}
			// Cancel this send.
			$fileoption->options['finish_time'] = -1;
			$fileoption->save();
			pb_backupbuddy::status( 'details', 'Cancelled in-progress send of deleted item `' . $file . '`.' );
			unset( $fileoption );
		}
	}

	/**
	 * Create a snapshot of the database based on global database setting defaults.
	 *
	 * @param  array  $tables     Array of tables to back up.
	 *                            When chunking dumped tables will be removed from this list (from the front).
	 * @param  int    $rows_start Row number to resume at for the first table in $tables.
	 *
	 * @return array|bool         Array of status message and array of arguments to pass to next function.
	 */
	private static function _step_database_snapshot( $tables = array(), $chunk_tables = array(), $rows_start = 0 ) {
		global $wpdb;

		if ( ( false === self::_load_state() ) || ( false === self::_load_tables() ) ) {
			return false;
		}

		backupbuddy_live::update_db_live_activity_time();

		// Database snapshot storage directory. Includes trailing slash.
		$directory = backupbuddy_live::getLiveDatabaseSnapshotDir();

		pb_backupbuddy::status( 'message', __('Starting database snapshot procedure.', 'it-l10n-backupbuddy' ) );

		if ( 0 === count( $chunk_tables ) ) { // First pass.
			// Delete any existing db snapshots stored locally.
			$snapshots = glob( $directory . '*.sql' );
			pb_backupbuddy::status(
				'details',
				sprintf(
					'Found `%1$s` total existing local SQL files to delete from temporary dump directory `%2$s`.',
					count( $snapshots ),
					$directory
				)
			);
			foreach( $snapshots as $snapshot ) {
				@unlink( $snapshot );
			}

			$tables        = backupbuddy_live::calculateTables();
			$chunk_tables = $tables;
		} else { // Resuming chunking.
			pb_backupbuddy::status( 'details', '`' . count( $chunk_tables ) . '` tables left to dump.' );
		}

		pb_backupbuddy::status( 'details', 'Tables: `' . print_r( $tables, true ) . '`, chunkTables: `' . print_r( $chunk_tables, true ) . '`, Rows_Start: `' . print_r( $rows_start, true ) . '`.' );

		if ( 'php' === pb_backupbuddy::$options['database_method_strategy'] ) {
			$force_methods = array( 'php' );
		} elseif ( 'commandline' === pb_backupbuddy::$options['database_method_strategy'] ) {
			$force_methods = array( 'commandline' );
		} elseif ( 'all' === pb_backupbuddy::$options['database_method_strategy'] ) {
			$force_methods = array( 'php', 'commandline' );
		} else {
			pb_backupbuddy::status( 'error', 'Error #95432: Invalid forced database dump method setting: `' . pb_backupbuddy::$options['database_method_strategy'] . '`.' );
			return false;
		}

		$destination_settings = self::get_destination_settings();
		$max_execution        = $destination_settings['_max_time'];

		// Load mysqlbuddy and perform dump.
		pb_backupbuddy::status( 'details', 'Loading mysqlbuddy.' );
		require_once( pb_backupbuddy::plugin_path() . '/lib/mysqlbuddy/mysqlbuddy.php' );
		pb_backupbuddy::$classes['mysqlbuddy'] = new pb_backupbuddy_mysqlbuddy( DB_HOST, DB_NAME, DB_USER, DB_PASSWORD, $wpdb->prefix, $force_methods, $max_execution );

		// Prepare destination snapshot sql files directory.
		pb_backupbuddy::status( 'details', 'Creating dump directory.' );
		$mode = apply_filters( 'itbub-default-file-mode', 0755 );
		if ( false === pb_backupbuddy::$filesystem->mkdir( $directory, $mode, true ) ) {
			$error = 'Error #387974: Solid Backups unable to create directory `' . $directory . '`. Please verify write permissions for the parent directory `' . dirname( $directory ) . '` or manually create the specified directory & set permissions.';
		}

		// Do the database dump.
		$result = pb_backupbuddy::$classes['mysqlbuddy']->dump( $directory, $chunk_tables, $rows_start ); // if result is an array, returns tables, row start

		// Process dump result.
		if ( is_array( $result ) ) { // Chunking.
			return array( __( 'Creating database snapshot', 'it-l10n-backupbuddy' ), array( $tables, $result[0], $result[1] ) ); // Full table list, remaining tables, row to resume at.

		} elseif ( false === $result ) {
			$error = 'Error #8349434: Live unable to dump database. See log for details.';
			self::write_error_messages( $error );
			self::$_state['step']['last_status'] = $error;
			return false;
		} elseif ( true === $result ) { // Success.
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
					self::$_tables[ $table ] = self::$_table_defaults; // Apply defaults.
					self::$_tables[ $table ]['m'] = self::$_state['stats']['last_db_snapshot'];
				} else { // Table already in catalog. Update it.
					self::$_tables[ $table ] = array_merge( self::$_table_defaults, self::$_tables[ $table ] ); // Apply defaults to existing data.
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
				foreach( self::$_tables as $table_name => $details ) { // Iterate through stored tables in catalog.
					if ( ( ! isset( $table_sizes[ $table_name ] ) ) || ( ! in_array( $table_name, $tables ) ) ) {
						// Backed up table is no longer in mysql db OR was not in list of tables to back up (e.g. is now excluded).

						// If table was already sent, mark for deletion. Else just remove entirely here.
						if ( self::is_backed_up( self::$_tables[ $table_name ] ) ) {
							self::$_tables[ $table_name ]['d'] = true; // Mark for deletion.
							self::$_state['stats']['tables_pending_delete']++;
						} else { // Remove outright here.
							unset( self::$_tables[ $table_name ] );
						}
					}
				}
			}

			self::$_state['stats']['tables_total_size']   = $database_size;
			self::$_state['stats']['tables_total_count']  = count( $tables );
			self::$_state['stats']['tables_pending_send'] = count( $tables );

			// Save catalog.
			self::$_stateObj->save();
			self::$_tablesObj->save();

			return true;

		} else {
			$error = 'Error #398349734: Live unexpected database dump response. See log for details.';
			self::write_error_messages( $error );
			self::$_state['step']['last_status'] = $error;
			return false;
		}
	}

	/**
	 * Get the full list of exclusions, including all defaults, global, Stash Live, etc. all combines and unique.
	 *
	 * @return array  Array of exclusions.
	 */
	public static function getFullExcludes() {
		// Get Live-specific excludes.
		$excludes = backupbuddy_live::getOption( 'file_excludes', true );

		// Add standard BB excludes we always apply.
		$excludes = array_unique( array_merge(
			self::$_default_excludes,
			backupbuddy_core::get_directory_exclusions( pb_backupbuddy::$options['profiles'][0], false, '' ),
			backupbuddy_core::get_directory_exclusions( array( 'excludes' => $excludes ), false, '' )
		) );

		return $excludes;
	}

	/**
	 * Generate list of files to add/update/delete.
	 *
	 * @param  string  $custom_root  Custom root to start scan from.
	 *                               IMPORTANT: This MUST be WITHIN(below) the ABSPATH directory.
	 *                               It cannot be higher (e.g. parent of ABSPATH). Trailing slash optional.
	 *
	 * @return array|bool            Array of files to add/update/delete or false on error.
	 */
	private static function _step_update_files_list( $custom_root = '', $start_at = 0, $items = array() ) {

		$start_time = microtime( true );
		pb_backupbuddy::status( 'details', 'Starting to process files; updating files list.' );

		if ( false === self::_load_catalog() ) {
			return false;
		}

		if ( ! empty( $custom_root ) ) {
			pb_backupbuddy::status( 'details', 'Scanning custom directory: `' . $custom_root . '`.' );
			sleep( 3 ); // Give WordPress time to make thumbnails, etc.
		}

		// Reset stats when starting from the beginning of a full file scan (not for custom roots).
		if ( 0 === $start_at && empty( $custom_root ) ) {
			self::$_state['stats']['files_pending_delete'] = 0;
			self::$_state['stats']['files_pending_send']   = 0;
			self::$_state['stats']['files_total_count']    = 0;
			self::$_state['stats']['files_total_size']     = 0;
		}

		$excludes = self::getFullExcludes();
		pb_backupbuddy::status( 'details', 'Excluding directories: `' . implode( ', ', $excludes ) . '`.' );

		// Generate list of files.

		$root     = ! empty( $custom_root ) ? $custom_root : ABSPATH;
		$root     = rtrim( $root, '/\\' ); // Make sure no trailing slash.
		$root_len = strlen( $root );

		$custom_root_diff     = ! empty( $custom_root ) ? substr( $root, strlen( ABSPATH )-1 ) : '';
		$destination_settings = self::get_destination_settings();

		pb_backupbuddy::status( 'details', 'Starting deep file scan.' );

		$max_time          = $destination_settings['_max_time'] - self::TIME_WIGGLE_ROOM;
		$adjusted_max_time = ( $max_time - 8 ); // Additional 5 seconds so that we can add files into catalog after this completes.

		if ( $adjusted_max_time < 5 ) {
			pb_backupbuddy::status(
				'error',
				'Error #3893983: Adjusted max execution time minus wiggle room fell below 5 second threshold. Bumped to 5 seconds. Stash Live max execution time limit is either too low or host PHP max execution time limit is far too low. Suggest 30 second minimum. Final adjusted value: `' . $adjusted_max_time . '`.'
			);
			$adjusted_max_time = 5;
		}

		$files = pb_backupbuddy::$filesystem->deepscandir( $root, $excludes, $start_at, $items, $start_time, $adjusted_max_time );

		if ( ! is_array( $files ) ) {
			backupbuddy_core::addNotification( 'live_error', 'Solid Backups Stash Live Error', $files );
			pb_backupbuddy::status( 'error', 'Error #84393434: Halting Stash Live due to error returned by deepscandir: `' . $files . '`.' );
			return $files;
		}

		if ( empty( $files ) || false === $files[0] ) {
			pb_backupbuddy::status( 'details', 'Deep file scan requires chunking.' );
			$next_start_at = 0;
			if ( isset( $files[1][0] ) ) {
				$next_start_at = $files[1][0];
			}
			return array( __( 'File scanning', 'it-l10n-backupbuddy' ), array( $custom_root, $next_start_at, $files[1][1] ) );
		} else {
			pb_backupbuddy::status( 'details', 'Deep file scan complete.' );
		}

		// Remove root from path AND remote directories...
		foreach( $files as $i => &$file ) {
			if ( is_dir( $file ) ) { // Don't track directories, only actual files.
				unset( $files[ $i ] );
				continue 1;
			}
			$file = substr( $file, $root_len );
		}

		// Flip array.
		$files = array_flip( $files );

		// Check if this file is already in the list or not.
		$files_added = 0;

		foreach( $files as $file => $ignore_id ) {
			if ( empty( $custom_root ) ) { // Only increment existing files if scanning from root (because stats were reset for fresh count).
				self::$_state['stats']['files_total_count']++;
			}

			$pathed_file = $custom_root_diff . $file; // Applies custom root portion if applicable.

			if ( ! isset( self::$_catalog[ $pathed_file ] ) ) { // File not already in signature list. Add it in with initial values.
				if ( ! empty( $custom_root ) ) { // Was not added earlier yet.
					self::$_state['stats']['files_total_count']++;
				}
				$files_added++;
				self::$_catalog[ $pathed_file ] = self::$_signature_defaults;
				self::$_state['stats']['files_pending_send']++;

				if ( 0 === ( $files_added % 2000 ) ) {
					// Update visible stats every 2000 files.
					self::$_state['stats']['files_to_catalog_percentage'] = round( number_format( ( $files_added / count( $files ) ) * 100, 2 ) );
					self::$_stateObj->save();
				}

				if ( pb_backupbuddy::full_logging() ) {
					pb_backupbuddy::status( 'details', 'Add to catalog: `' . $pathed_file . '`.' );
				}
			} else { // Already exists in catalog.

				if ( ! self::is_backed_up( self::$_catalog[ $pathed_file ] ) ) {
					if ( ! self::is_pending_delete( self::$_catalog[ $pathed_file ] ) ) {
						if ( empty( $custom_root ) ) {
							// Not pending deletion already.
							// File not backed up to server yet (pending send) and not pending deletion already.
							// Only increment existing files if scanning from root (because stats were reset for fresh count).
							self::$_state['stats']['files_pending_send']++;
						}
					}
				} else {
					// Local file already exists in catalog and on server. Make sure not marked for deletion.
					if ( self::is_pending_delete( self::$_catalog[ $pathed_file ] ) ) {
						// Was marked to delete. Remove deletion mark BUT do rescan in case this is a new version
						// of the file since it was for some reason marked to delete.
						unset( self::$_catalog[ $pathed_file ]['d'] ); // Don't immediately delete. (unset to save mem).

						// Reset last scan time so it gets re-checked.
						self::$_catalog[ $pathed_file ]['r'] = 0;
					}
				}
			}
		}

		// Checking existing catalog files with new scan to see if anything needs deletion.
		$files_deleted = 0;

		foreach( self::$_catalog as $signature_file => &$signature_details ) {
			if ( self::is_pending_delete( $signature_details ) ) { // Already marked for deletion.
				continue;
			}
			if ( ! empty( $custom_root ) && ( $root !== substr( $signature_file, 0, $root_len ) ) ) {
				// Custom root. Ignore removing any files not within the custom root since we did not scan those, so they are not in the $files array.
				// Beginning of filename does not match root so not applicable for this scan. Skip
				continue;
			}
			if ( ! isset( $files[ $signature_file ] ) ) { // File no longer exists in new scan. Mark for deletion.
				//$sinceLogTrim++;
				$files_deleted++;
				$signature_details['d'] = true;
				self::$_state['stats']['files_pending_delete']++;
				// If it was not yet backed up, decrease pending count.
				if ( ! self::is_backed_up( $signature_details ) ) {
					self::$_state['stats']['files_pending_send'] = self::decrement( self::$_state['stats']['files_pending_send'] );
				}

				self::$_state['stats']['files_total_count'] = self::decrement( self::$_state['stats']['files_total_count'] );
				pb_backupbuddy::status( 'details', 'Remove file that no longer exists locally. Flagging `' . $signature_file . '` for deletion.' );
			}
		}

		self::$_catalogObj->save();
		pb_backupbuddy::status( 'details', 'Signatures saved. Added `' . $files_added++ . '` files to local catalog. Marked `' . $files_deleted . '` files deleted. Took `' . ( microtime( true ) - $start_time ) . '` seconds.' );

		return true;
	}

	/**
	 * Step through all the files in the catalog, calculating signatures such as modified time, size, etc.
	 *
	 * @param  int  $start_at  File number to resume at.
	 *
	 * @return bool|array  False on error, array of data on chunking.
	 */
	private static function _step_update_files_signatures( $start_at = 0 ) {

		if ( false === self::_load_catalog() ) {
			return false;
		}

		$start_time = microtime( true );

		if ( 0 === $start_at ) {
			self::$_state['stats']['files_total_size'] = 0; // Reset total sum for upcoming scan.
		}

		// Clear stale stat cache so file modified/size/etc are very up-to-date.
		pb_backupbuddy::status( 'details', 'Cleaning stat cache for signature scan.' );
		clearstatcache();

		// Loop through files in the catalog.
		$files_updated                        = 0;
		$files_deleted                        = 0;
		$already_sent_files_detected_changed  = 0;
		$files_needing_resend_from_iffy_audit = 0;
		$files_detected_changed               = 0;
		$loop_count                           = 0;
		$last_save                            = microtime( true );

		foreach( self::$_catalog as $signature_file => &$signature_details ) {
			if ( ( 0 !== $start_at ) && ( $loop_count < $start_at ) ) {
				continue;
			}

			$loop_count++;

			// Check if file is already set to delete.
			if ( self::is_pending_delete( $signature_details ) ) {
				continue;
			}

			// Sum sizes for any file that is not marked for deletion.
			// Files that were just deleted will be subtracted below. If zero then we will apply size below.
			if ( 0 !== ( $signature_details['s'] ) ) {
				self::$_state['stats']['files_total_size'] += $signature_details['s'];
			}

			// Check if enough time has passed since last rescan.
			if ( ( time() - $signature_details['r'] ) < self::TIME_BETWEEN_FILE_RESCAN ) {
				continue;
			}

			// If file not marked for deletion, check if it still exists.
			if ( ! file_exists( ABSPATH . $signature_file ) ) { // File has been deleted.
				$files_deleted++;
				$signature_details['d'] = true;
				self::$_state['stats']['files_pending_delete']++;

				if ( ! self::is_backed_up( $signature_details ) ) { // If NOT already sent to server.
					self::$_state['stats']['files_pending_send'] = self::decrement( self::$_state['stats']['files_pending_send'] );
				}

				self::$_state['stats']['files_total_count'] = self::decrement( self::$_state['stats']['files_total_count'] );
				self::$_state['stats']['files_total_size']  = self::$_state['stats']['files_total_size'] - $signature_details['s']; // We already added all files not marked for deletion so remove this filesize from the sum.
				pb_backupbuddy::status( 'details', 'Remove file that no longer exists locally during signature calculation. Flagging `' . $signature_file . '` for deletion.' );
				continue; // Skip to next file.
			}

			// Made it this far then calculate (or re-calculate) signature.
			$stat = @stat( ABSPATH . $signature_file );
			if ( false === $stat ) {
				$error = 'Unable to retrieve stat() for file `' . $signature_file . '`. Check file permissions.';
				self::write_error_messages( $error );
				continue; // Skip to next file.
			}

			// Update rescan time.
			$files_updated++;
			$signature_details['r'] = time(); // Update time last rescanned.

			if ( isset( $signature_details['a'] ) ) { // Remove entirely.
				unset( $signature_details['a'] );
			}

			// If the file changed then set as NOT uploaded, so we will send a new copy.
			if ( ( $signature_details['m'] !== $stat['mtime'] ) || ( $signature_details['s'] !== $stat['size'] ) ) {
				if ( self::is_backed_up( $signature_details ) ) {
					self::$_state['stats']['files_pending_send']++;
					$already_sent_files_detected_changed++;
				}

				$files_detected_changed++;
				$signature_details['b'] = 0; // Current version is NOT backed up.
				unset( $signature_details['t'] ); // Reset try (send attempt) counter back to zero by clearing (to save mem) since this version has not been attempted.

				// If we made it here then the filesize number changed.
				// If the stored previous size was non-zero then this means we need to update the total file size sum stats for the difference.
				if ( 0 !== $signature_details['s'] ) {
					// Don't subtract if it was never added in before.
					self::$_state['stats']['files_total_size'] = self::$_state['stats']['files_total_size'] - $signature_details['s']; // Subtract old size. New size was already summed above.
				}
			}

			if ( 0 === $signature_details['s'] ) { // Size was zero so it was not added yet. Add now that we have stat size data.
				self::$_state['stats']['files_total_size'] += $stat['size'];
			}

			// Update modified time.
			$signature_details['m'] = $stat['mtime'];
			if ( 0 === $signature_details['m'] ) {
				pb_backupbuddy::status( 'error', 'Error #8438934: File modified time unexpectedly zero (0). File permissions may be blocking proper modification detection on file `' . $signature_file  . '`.' );
			}

			$signature_details['s'] = $stat['size']; // Update size.

			if ( self::file_needs_resending( $signature_details ) ) {
				// File needs re-sent since missing on remote server.
				$signature_details['v'] = 0; // Reset audit timestamp.
				$signature_details['b'] = 0; // Reset backup timestamp since we know it's not backed up.
				unset( $signature_details['t'] ); // Reset try (send attempt) counter.
				self::$_state['stats']['files_pending_send']++; // Update files pending send counter.

				$files_needing_resend_from_iffy_audit++;
			}

			// See if it's time to save our progress.
			if ( self::is_time_to_save( $last_save, true ) ) {
				self::$_catalogObj->save();
				self::$_stateObj->save();
				$last_save = microtime( true );
			}

			$destination_settings = self::get_destination_settings();

			// Do we have enough time to continue or do we need to chunk?
			if ( ! self::has_time_to_continue( $start_time, $destination_settings ) ) { // Running out of time! Chunk.
				self::$_catalogObj->save();
				pb_backupbuddy::status(
					'details',
					sprintf(
						'Running out of time calculating signatures. Updated `%1$s` signatures. Deleted `%2$s` files. Already-sent files detected as changed since sending: `%3$s`. Files detected changed total: `%4$s`. Resend due to audit: `%5$s`. Next start: `%6$s`. Took `%7$s` seconds.',
						$files_updated,
						$files_deleted,
						$already_sent_files_detected_changed,
						$files_detected_changed,
						$files_needing_resend_from_iffy_audit,
						$loop_count,
						( microtime( true ) - $start_time )
					)
				);
				return array( __( 'Updating file signatures', 'it-l10n-backupbuddy' ), array( $loop_count ) );
			}

		} // end foreach signature.

		self::$_catalogObj->save();
		pb_backupbuddy::status(
			'details',
			sprintf(
				'Signatures updated and saved. Updated `%1$s` signatures. Deleted `%2$s` files. Already-sent files detected as changed since sending: `%3$s`. Files detected changed total: `%4$s`. Resend due to audit: `%5$s`. Total size: `%6$s`. Took `%7$s` seconds.',
				$files_updated,
				$files_deleted,
				$already_sent_files_detected_changed,
				$files_detected_changed,
				$files_needing_resend_from_iffy_audit,
				self::$_state['stats']['files_total_size'],
				( microtime( true ) - $start_time )
			)
		);

		return true;
	}

	/**
	 * Check if the file should be re-sent.
	 *
	 * @param  array $details  File details from catalog.
	 *
	 * @return  bool True if file needs to be re-sent.
	 */
	private static function file_needs_resending( $details ) {
		if ( false === self::_load_state() ) {
			return false;
		}

		// If the audit has not run at least once.
		if ( empty( self::$_state['stats']['last_file_audit_start'] ) ) {
			return false;
		}

		// Validate/relabel for clarity.
		$last_audit  = self::$_state['stats']['last_file_audit_start'];
		$last_finish = ! empty( self::$_state['stats']['last_file_audit_finish'] ) ? self::$_state['stats']['last_file_audit_finish'] : 0;

		/*
		 * If not pending send already AND last audit that began has finished.
		 *
		 * And...
		 * File was marked sent before the audit began, so it should exist remotely.
		 *
		 * And...
		 * Not verified since the last audit.
		 */
		return (
			( self::is_backed_up( $details ) && ( $last_finish > $last_audit ) )
			&& ( $details['b'] < $last_audit )
			&& ( $details['v'] < $last_audit )
		);
	}

	/**
	 * Handle cleanup of any locally deleted files that need removed from the Live servers.
	 *
	 * @param int  $start_at  Optional. Starting point in catalog to begin processing. Default 0.
	 *
	 * @return  array|bool  Array of status details.
	 */
	private static function _step_process_file_deletions( $start_at = 0 ) {
		if ( $start_at < 0 ) {
			$start_at = 0;
		}

		$start_time = microtime( true );
		if ( false === self::_load_catalog() ) {
			return false;
		}

		// Build Stash3 destination settings based on Live settings.
		$destination_settings = self::get_destination_settings();

		pb_backupbuddy::status( 'details', 'Starting deletions at point: `' . $start_at . '`.' );

		// Loop through files in the catalog.
		$loop_count    = 0;
		$check_count   = 0;
		$files_deleted = 0;
		$delete_queue  = array(); // Files queued to delete.
		$last_save     = microtime( true );

		foreach( self::$_catalog as $signature_file => &$signature_details ) {
			$loop_count++;
			if ( 0 !== $start_at && ( $loop_count < $start_at ) ) {
				continue;
			}
			$check_count++;

			// NOTE: BB v8 cleanup removes default false to conserve memory. TODO: Remove at BB v9.
			if ( isset( $signature_details['d'] ) && ( false === $signature_details['d'] ) ) {
				unset( $signature_details['d'] );
			}

			// Skip non-deleted files.
			if ( ! self::is_pending_delete( $signature_details ) ) {
				continue;
			}

			// Made it here then we will be deleting a file.

			// Cancel any in-progress remote sends pending deletion.
			self::cancel_sending_deleted_files( $signature_file, $signature_details, '#9034.32393' );

			// If file has been backed up to server then we need to QUEUE the file for deletion.
			if ( self::is_backed_up( $signature_details ) ) {
				$delete_queue[] = $signature_file;
			} else {
				// Remove item from queue immediately since not sent.
				self::$_state['stats']['files_pending_delete'] = self::decrement( self::$_state['stats']['files_pending_delete'] );
				self::$_state['stats']['files_pending_send']   = self::decrement( self::$_state['stats']['files_pending_send'] );
				unset( self::$_catalog[ $signature_file ] );
			}

			// Is the queue full to the delete burst limit? Process queue.
			if ( count( $delete_queue ) >= $destination_settings['max_delete_burst'] ) {
				pb_backupbuddy::status( 'details', 'File deletion queue full to burst limit. Triggering deletions call.' );
				self::_process_internal_delete_queue( $destination_settings, $delete_queue, $files_deleted ); // Process any items in the deletion queue. $delete_queue and $files_deleted are references.
			}

			// See if it's time to save our progress so far.
			if ( self::is_time_to_save( $last_save ) ) {
				pb_backupbuddy::status( 'details', 'Time to save progress so far. Triggering deletions call then saving.' );
				self::_process_internal_delete_queue( $destination_settings, $delete_queue, $files_deleted ); // Process any items in the deletion queue. $delete_queue and $files_deleted are references.

				self::$_stateObj->save();
				self::$_catalogObj->save();
				$last_save = microtime( true );
			}

			// Do we have enough time to continue or do we need to chunk?
			if ( ! self::has_time_to_continue( $start_time, $destination_settings ) ) { // Running out of time! Chunk.
				pb_backupbuddy::status( 'details', 'Running out of time processing deletions. Took `' . ( microtime( true ) - $start_time ) . '` seconds. Triggering deletions call then saving.' );
				//self::_process_internal_delete_queue( $destination_settings, $delete_queue, $files_deleted ); // Process any items in the deletion queue. $delete_queue and $files_deleted are references.
				// Do not process queue here. Anything in the queue can be handled during the next chunk.

				self::$_stateObj->save();
				self::$_catalogObj->save();
				return array( __( 'Processing deletions', 'it-l10n-backupbuddy' ), array() );
			}

		} // end foreach.

		// Wrap up any lingering deletions.
		pb_backupbuddy::status( 'details', 'Processing any lingering deletions in queue (' . count( $delete_queue ) . ') at finish then saving.' );
		self::_process_internal_delete_queue( $destination_settings, $delete_queue, $files_deleted ); // Process any items in the deletion queue. $delete_queue and $files_deleted are references.

		// Save and finish.
		self::$_stateObj->save();
		self::$_catalogObj->save();
		pb_backupbuddy::status( 'details', 'Deletions processed. Checked `' . $check_count . '` files. Deleted `' . $files_deleted . '` files. Took `' . ( microtime( true ) - $start_time ) . '` seconds.' );
		return true;

	}

	/**
	 * API call to actually delete files queued for deletion.
	 *
	 * Updates catalog and stats variables.
	 *
	 * @param array  $destination_settings  Destination settings array.
	 * @param array  $delete_queue          Array of files to delete.
	 * @param int    $files_deleted         Reference to number of files deleted. Updated by this function.
	 *
	 * @return bool  True on success. False on error.
	 */
	private static function _process_internal_delete_queue( $destination_settings, &$delete_queue, &$files_deleted ) {
		// Return true on empty queue.
		if ( count( $delete_queue ) <= 0 ) {
			return true;
		}

		pb_backupbuddy::status( 'details', 'Processing internal deletion queue on `' . count( $delete_queue ) . '` files.' );
		$delete_result = pb_backupbuddy_destination_live::deleteFiles( $destination_settings, $delete_queue );
		if ( true !== $delete_result ) {
			pb_backupbuddy::status( 'error', 'Error #8239833: Unable to delete remote file(s) `' . implode( '; ', $delete_queue ) . '`' );
			return false;
		}

		// Remove each deleted file from catalog and update stats.
		foreach( $delete_queue as $signature_file ) {
			// Remove file from catalog and update state stats. IMPORTANT: DO NOT SAVE CATALOG UNTIL QUEUE PROCESSED.
			unset( self::$_catalog[ $signature_file ] );
		}

		// Calculate some local stats on what just happened for log.
		$deleted_count = count( $delete_queue );
		$remaining = ( self::$_state['stats']['files_pending_delete'] - $deleted_count );
		if ( $remaining < 0 ) {
			$remaining = 0;
		}
		$last_deleted = end( $delete_queue ); // Move array pointer to end of queue.

		// Update state stats.
		$pending_delete = self::$_state['stats']['files_pending_delete'] - $deleted_count;
		self::$_state['stats']['files_pending_delete'] = ( $pending_delete < 0 ) ? 0 : $pending_delete;

		// Clear $delete_queue reference.
		$delete_queue = array(); // Clear out queue.

		// Update $files_deleted reference.
		$files_deleted += $deleted_count;

		pb_backupbuddy::status( 'details', 'Deleted `' . $deleted_count . '` remote files (' . $remaining . ' remain). Last file deleted in current queue: `' . $last_deleted . '`.' );
		return true;
	}

	/**
	 * Find the next pending file and tries to send it.
	 *
	 * @param  array  $signatures  If provided then we will use these signatures for sending instead of the normal catalog signatures.
	 *                             Used to send database SQL files.
	 * @param  int    $startAt     Location to start sending from. Used by chunking.
	 *                             Skips X signatures to get to this point.
	 */
	private static function _step_send_pending_files( $start_at = 0 ) {

		// Load state into self::$_state & fileoptions object into self::$_stateObj.
		if ( false === self::_load_state() ) {
			return false;
		}

		if ( empty( $start_at ) ) {
			$start_at = self::$_state['stats']['last_filesend_startat'];
			pb_backupbuddy::status( 'details', 'Starting to send pending files at position `' . $start_at . '` based on stored stats position.' );
		} else {
			pb_backupbuddy::status( 'details', 'Starting to send pending files at position `' . $start_at . '` based on passed value.' );
		}

		if ( false === self::_load_catalog() ) {
			return false;
		}

		// If catalog backup is older than X seconds then backup.
		$catalog_file = backupbuddy_core::getLogDirectory() . 'live/catalog-' . pb_backupbuddy::$options['log_serial'] . '.txt';
		if (
			false !== ( $file_stats = @stat( $catalog_file . '.bak' ) )
			&& ( time() - $file_stats['mtime'] ) > backupbuddy_constants::MAX_TIME_BETWEEN_CATALOG_BACKUP
			) {

			self::backup_catalog();
		}

		require_once( pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php' );

		// Truncate log if it is getting too large. Keeps newest half.
		self::_truncate_log();

		$results = self::send_files( self::$_catalog, $start_at );

		// Schedule next run if we still have more files to potentially send.
		if ( $results['more_remain'] ) {
			return array( __( 'Sending queued files', 'it-l10n-backupbuddy' ), array( $results['loop_count'] ) );
		}

		self::$_state['stats']['last_filesend_startat'] = 0; // Reset the startat location.
		pb_backupbuddy::status( 'details', 'No more files remain. Reset filesend startat position back to 0.' );

		return true;
	}

	/**
	 * Send an array of files to the Stash server.
	 *
	 * @param  array  $file_array  Array of files to send. Each key is the file path relative to ABSPATH.
	 *                             Each value is an array of file details.
	 * @param  int    $start_at    Optional. Location to start sending from. Used by chunking.
	 *                             Skips X signatures to get to this point.
	 *
	 * @return array|false  Array of results. 'loop_count' is the number of files looped through. 'more_remain' is true if there are more files to send. False if issues are occurring.
	 */
	private static function send_files( $file_array, $start_at = 0 ) {

		// Loop through files in the catalog.
		$check_count         = 0;
		$lack_signature_data = 0;
		$loop_count          = 0;
		$more_remain         = 0;
		$send_attempt_count  = 0;
		$send_size_sum       = 0;
		$send_time_sum       = 0;
		$too_many_attempts   = 0;

		self::$_state['stats']['actively_sending'] = true;

		foreach( $file_array as $file => &$details ) {
			$loop_count++;

			/*
			 * Don't use array_slice() for this, as we must track our position
			 * in the full array each time this method is called.
			 */
			if ( ( 0 !== $start_at ) && ( $loop_count < $start_at ) ) {
				continue;
			}

			// If Live is disabled, we need to break this loop. Query the DB every 10 ints to make sure live still exists
			if ( ( 0 === $loop_count % 10 ) && ( empty( self::live_is_enabled() ) ) ) {
				backupbuddy_core::clearLiveLogs( pb_backupbuddy::$options['log_serial'] );
				self::$_state['stats']['actively_sending'] = false;
				return false;
			}

			$check_count++;

			// Every X files that get sent, make sure log file is not getting too big AND back up catalog.
			if ( 0 === ( ( $send_attempt_count + 1 ) % 150 ) ) {
				// Backup catalog.
				self::backup_catalog();
			}

			// If already backed up OR we do not have file details data yet then skip for now.
			if ( self::is_backed_up( $details ) ) {
				continue;
			}

			if ( empty( $details['m'] ) ) {
				$lack_signature_data++;
				continue;
			}

			// If too many attempts have passed then skip.
			if ( isset( $details['t'] ) && ( $details['t'] >= self::MAX_SEND_ATTEMPTS ) ) {
				$too_many_attempts++;
				continue;
			}

			// If too many remote sends have failed today then give up for now since something is likely wrong.
			if ( self::too_many_daily_send_fails() ) {
				return false;
			}

			// Load destination settings.
			$destination_settings = self::get_destination_settings();

			// If this is not the first file we've sent this pass, see if we have enough time for more.
			$can_send_more = self::can_send_more( $details, $send_size_sum, $send_time_sum );
			if ( ! $can_send_more ) {
				$more_remain = true;
				break;
			} else {
				// Time remaining, so continue sending.

				// Run cleanup on send files.
				require_once( pb_backupbuddy::plugin_path() . '/classes/housekeeping.php' );
				backupbuddy_housekeeping::trim_remote_send_stats( 'send-live_', $destination_settings['max_send_details_limit'], '', true );

				// Increment try count for transfer attempts and save.
				$details['t'] = ( isset( $details['t'] ) ) ? $details['t'] + 1 : 1;
				self::$_catalogObj->save();

				// Save position in case process starts over to prevent race conditions resulting in double send of files.
				$destination_settings['_live_next_step'] = array( 'send_pending_files', array() ); // Next function and args to try and run after finishing send of this file.
				self::$_state['stats']['last_filesend_startat'] = $loop_count + 1;
				self::$_stateObj->save();

				$full_file = ABSPATH . substr( $file, 1 );
				if ( ! file_exists( $full_file ) ) {
					pb_backupbuddy::status( 'details', 'File in catalog no longer exists (or permissions block). Skipping send of file `' . $full_file . '`.' );
				} else {
					/*
					* Send the file.
					*
					* After success sending, this Stash3 destination will automatically
					* trigger the live_periodic processing if it is a multipart send.
					*
					* Regardless of success or failure we come back here to potentially
					* send more files in the same PHP pass so small files don't each need
					* their own PHP page run.
					*
					* Unless the process has restarted, in which case this will still be
					* the 'next' function to run.
					*/
					$send_id = 'live_' . md5( $file ) . '-' . pb_backupbuddy::random_string( 6 );
					pb_backupbuddy::status( 'details', 'Live starting send function.' );
					$send_time_start = microtime( true );

					// Close catalog & state while sending if > X size to prevent collisions.
					if ( $details['s'] > self::CLOSE_CATALOG_WHEN_SENDING_FILESIZE ) {
						self::$_catalogObj = '';
						self::$_stateObj = '';
					}

					// Send file to remote.
					$send_attempt_count++;

					$result = pb_backupbuddy_destinations::send(
						$destination_settings,
						$full_file,
						$send_id,
						false,
						false,
						self::TRIGGER,
						backupbuddy_live::getLiveID()
					);

					// Re-open catalog (if closed).
					if ( false === self::_load_state() ) {
						pb_backupbuddy::status( 'error', 'Error #5489458443: Unable to re-open temporarily closed state.' );
						self::$_state['stats']['actively_sending'] = false;
						return false;
					}

					if ( false === self::_load_catalog() ) {
						pb_backupbuddy::status( 'error', 'Error #5489458443: Unable to re-open temporarily closed catalog.' );
						self::$_state['stats']['actively_sending'] = false;
						return false;
					}

					$send_time_finish = microtime( true );
					if ( true === $result ) {
						$result_status = 'Success sending in single pass';
						$send_time_sum += ( $send_time_finish - $send_time_start );
						$send_size_sum += self::get_send_size( $details );
					} elseif ( false === $result ) {
						self::record_send_failure( $full_file );
						$result_status = 'Failure sending in single/first pass. See log above for error details. Failed sends today: `' . self::$_state['stats']['recent_send_fails'] . '`';
					} elseif ( is_array( $result ) ) {
						$result_status = 'Chunking commenced. Ending sends for this pass';
						// TODO: Ideally at this point we would have Live sleep until the large chunked file finished sending.
					}
					pb_backupbuddy::status( 'details', 'Live ended send files function. Status: ' . $result_status . '. send_attempt_count: ' . $send_attempt_count );
				} // end file exists.
			}
		} // End foreach file.

		self::$_state['stats']['actively_sending'] = false;

		pb_backupbuddy::status(
			'details',
			sprintf(
				'Checked `%1$s` items for sending. Sent `%2$s`. Skipped due to too many send attempts: `%3$s`.',
				$check_count,
				$send_attempt_count,
				$too_many_attempts
			)
		);
		pb_backupbuddy::status(
			'warning',
			'Warning: Skipped due to lacking signature data: `' . $lack_signature_data . '`. If this is temporary it is normal. If this persists there may be permissions blocking reading file details.'
		);

		if ( $too_many_attempts > 0 ) {
			// Break up warning to keep this file tidy.
			$message = 'Warning #5003. `' . $too_many_attempts . '` files were skipped due to too many send attempts failing. ';
			$message .= 'Check the Destinations page\'s Recently sent files list to check for errors of failed sends. ';
			$message .= 'To manually reset sends Pause the Files process and wait for it to finish, then select the Advanced Troubleshooting Option to \'Reset Send Attempts\'.';
			self::write_warning_messages( $message );
		}

		return array(
			'more_remain' => $more_remain,
			'loop_count'  => $loop_count,
		);
	}

	/**
	 * Record any send failure.
	 *
	 * @param  string  $file  File that failed to send.
	 */
	private static function record_send_failure( $file ) {
		self::$_state['stats']['recent_send_fails']++;
		self::$_state['stats']['last_send_fail']      = time();
		self::$_state['stats']['last_send_fail_file'] = $file;
	}

	/**
	 * Calculate the size of the file sent.
	 *
	 * Sets a minimum threshold so small files don't make server
	 * appear slower than reality due to overhead.
	 *
	 * @param array  $details  Signature/Table details array for file being sent.
	 *
	 * @return int  Size of file in bytes.
	 */
	private static function get_send_size( $details ) {
		if ( ! isset( $details['s'] ) ) {
			return 0;
		}

		if ( $details['s'] < self::MINIMUM_SIZE_THRESHOLD_FOR_SPEED_CALC ) {
			return self::MINIMUM_SIZE_THRESHOLD_FOR_SPEED_CALC;
		}
		return $details['s'];
	}

	/**
	 * Check if too many send failures have occurred today.
	 *
	 * Handles logging as well.
	 *
	 * @return bool  True if too many failures have occurred. False if not.
	 */
	private static function too_many_daily_send_fails() {
		if ( false === self::_load_state() ) {
			return false;
		}

		$destination_settings = self::get_destination_settings();
		if ( self::$_state['stats']['recent_send_fails'] < $destination_settings['max_daily_failures'] ) {
			return false;
		}

		$last_fail = 'unknown';
		if ( self::$_state['stats']['last_send_fail'] > 0 ) {
			$last_fail = pb_backupbuddy::$format->time_ago( self::$_state['stats']['last_send_fail'] );
		}

		// Break this up to keep this file readable.
		$error = 'Error #5002: Too many file transfer failures have occurred so stopping transfers. We will automatically try again in 12 hours. ';
		$error .= 'Verify there are no remote file transfer problems. Check recently send file logs on Remote Destinations page. Don\'t want to wait? ';
		$error .= 'Pause Files process then select \'Reset Send Attempts\' under \'Advanced Troubleshooting Options\'.  Time since last send file: `' . $last_fail . '`. ';
		$error .= 'File: `' . self::$_state['stats']['last_send_fail_file'] . '`.';
		self::write_error_messages( $error );
		self::$_state['step']['last_status'] = $error;
		self::$_state['stats']['actively_sending'] = false;

		return true;
	}

	/**
	 * Check to see if more files can be sent in this pass.
	 *
	 * @param  array  $signature_details  Signature details array for file being sent.
	 * @param  int    $send_size_sum      Total size of files sent so far this pass.
	 * @param  int    $send_time_sum      Total time spent sending files so far this pass.
	 *
	 * @return bool   True if more files can be sent. False if not.
	 */
	private static function can_send_more( $details, $send_size_sum, $send_time_sum ) {
		$can_send_more = true;

		if ( $send_size_sum <= 0 ) {
			return true;
		}

		$destination_settings = self::get_destination_settings();
		// Check if it appears we have enough time to send at least a full single chunk in this pass or if we need to pass off to a subsequent run.
		$send_speed     = ( $send_size_sum / 1048576 ) / $send_time_sum; // Estimated speed at which we can send files out. Unit: MB / sec.
		$time_elapsed   = ( microtime( true ) - pb_backupbuddy::$start_time );
		$time_remaining = $destination_settings['_max_time'] - ( $time_elapsed + self::TIME_WIGGLE_ROOM ); // Estimated time remaining before PHP times out. Unit: seconds.
		$size_possible_with_remaining_time = $send_speed * $time_remaining; // Size possible to send with remaining time (takes into account wiggle room).

		$size_to_send = ( $details['s'] / 1048576 ); // Size we want to send this pass. Unit: MB.
		if ( $destination_settings['max_burst'] < $size_to_send ) { // If the chunk size is smaller than the full file then cap at sending that much.
			$size_to_send = $destination_settings['max_burst'];
		}

		$can_send_more = ( $size_to_send  < $size_possible_with_remaining_time );

		$conclusion = 'Enough time to send more. Preparing for send.';
		if ( ! $can_send_more ) {
			$conclusion = 'Not enough time to send more. To continue in next live_periodic pass.';
		}

		// Break this message up to keep this file readable.
		$message = sprintf( 'Not the first normal file to send this pass. Send speed: `%s` MB/sec. ', $send_speed );
		$message .= sprintf(
			'Time elapsed: `%1$s` sec. Time remaining (with wiggle): `%2$s` sec based on reported max time of `%3$s` sec. ',
			$time_elapsed,
			$time_remaining,
			$destination_settings['_max_time']
		);
		$message .= sprintf(
			'Size possible with remaining time: `%1$s` MB. Size to chunk (greater of filesize or chunk): `%2$s` MB. Conclusion: `%3$s`.',
			$size_possible_with_remaining_time,
			$size_to_send,
			$conclusion
		);
		pb_backupbuddy::status ( 'details', $message );

		return $can_send_more;
	}

	/**
	 * Audit remove files to make sure remotely stores files match local catalog.
	 *
	 * 1) Lists through all remote files. Any remote files found that are not in the catalog at all are deleted.
	 * 2) Updates the 'v' audit verification key for files found in the catalog, verifying they were found remotely.
	 * 3) The next time the file signature checking step runs, any files that were thought to be backed up but found not to be (missing or old 'v' key) will be set to re-upload.
	 *
	 * @param  string  $marker        AWS file marker for the next loop (if applicable). null for no marker (start at beginning).
	 * @param  int     $running_count  How many files listed so far (excluding table count).
	 *
	 */
	private static function _step_audit_remote_files( $marker = null, $running_count = 0, $found_dat = false ) {
		if ( ( time() - self::$_state['stats']['last_file_audit_finish'] ) < ( self::TIME_BETWEEN_FILE_AUDIT ) ) {
			pb_backupbuddy::status(
				'details',
				sprintf(
					'Not enough time has passed since last file audit. Skipping for now. Minimum time: `%1$s` secs. Last ran ago: `%2$s` secs.',
					self::TIME_BETWEEN_FILE_AUDIT,
					( time() - self::$_state['stats']['last_file_audit_finish'] )
				)
			);
			return true;
		}

		$delete_batch_size = 100; // Delete files in batches of this many files via deleteObjects via deleteFiles().
		$serial_dir        = 'wp-content/uploads/backupbuddy_temp/SERIAL/'; // Include trailing slash.
		$serial_dir_len    = strlen( $serial_dir );

		if ( ( false === self::_load_state() ) || ( false === self::_load_catalog() ) || ( false === self::_load_tables() ) ) {
			return false;
		}

		$destination_settings = self::get_destination_settings();
		require_once( pb_backupbuddy::plugin_path() . '/destinations/live/init.php' );

		if ( null === $marker ) {
			// Only reset if NOT chunking (first pass).
			self::$_state['stats']['last_file_audit_start'] = microtime( true );
		}

		self::$_stateObj->save();

		$found_dat      = false;
		$loop_count     = 0;
		$loop_start     = microtime( true );
		$keep_looping   = true;
		$total_listed   = 0;
		$total_tables   = 0;
		$serial_skips   = 0;
		$files_deleted  = 0;
		$tables_deleted = 0;
		$last_save      = microtime( true );

		while( true === $keep_looping ) {
			$loop_count++;

			pb_backupbuddy::status( 'details', 'Listing files starting at marker `' . $marker . '`.' );
			$files = pb_backupbuddy_destination_live::listFiles( $destination_settings, '', $marker );
			if ( ! is_array( $files ) ) {
				$error = 'Error #3279327: One or more errors encountered attempting to list remote files for auditing. Details: `' . print_r( $files, true ) . '`.';
				self::write_error_messages( $error );
				self::$_state['step']['last_status'] = 'Error: Unable to list remote files for audit.';
				return false;
			}
			pb_backupbuddy::status( 'details', 'Listed `' . count( $files ) . '` files.' );
			$total_listed += count( $files );

			// Iterate through all remote files.
			$pending_delete           = array();
			$files_deleted_this_round = 0;
			foreach( $files as $file ) {

				// Skip all files in the SERIAL directory with underscore. Audit the rest.
				if ( substr( $file['Key'], 0, $serial_dir_len ) === $serial_dir ) {
					$total_tables++;
					$basename = basename( $file['Key'] );

					// Ignore underscore-prefixed live db data. Do not audit these. Skip.
					if ( '_' === substr( $basename, 0, 1 ) ) {
						$serial_skips++;
						continue;
					}

					// Ignore backupbuddy_dat.php metadata file and importbuddy.php files in database folder.
					if ( 'backupbuddy_dat.php' === $basename ) {
						$found_dat = true;
						continue;
					}
					if ( 'importbuddy.php' === $basename ) {
						continue;
					}

					// Verify no unexpected extra .sql files exist.
					if ( pb_backupbuddy::full_logging() ) {
						pb_backupbuddy::status( 'details', 'Auditing remotely found table (shown due to log level): `' . $basename . '`.' );
					}
					$table_name = str_replace( '.sql', '', $basename );
					if ( ! isset ( self::$_tables[ $table_name ] ) ) {
						pb_backupbuddy::status( 'details', 'Deleting unexpectedly remotely found table file: `' . $basename . '`.' );
						if ( true !== ( $delete_result = pb_backupbuddy_destination_live::deleteFile( $destination_settings, array( $file['Key'] ) ) ) ) {
							pb_backupbuddy::status( 'error', 'Error #329030923: Unable to delete remote file. See log above for details. Details: `' . $delete_result . '`.' );
						} else {
							pb_backupbuddy::status( 'details', 'Deleted remote database file `' . $file['Key'] . '`.' );
							$tables_deleted++;
						}
					}

					continue;
				}

				if ( ! isset( self::$_catalog[ '/' . $file['Key'] ] ) ) { // Remotely stored file not found in local catalog. Delete remote.
					$pending_delete[] = $file['Key'];

					// Process deletions.
					if ( count( $pending_delete ) >= $delete_batch_size ) {
						if ( true !== ( $delete_result = pb_backupbuddy_destination_live::deleteFile( $destination_settings, $pending_delete ) ) ) {
							pb_backupbuddy::status( 'error', 'Error #4397347934: Unable to delete one or more remote files. See log above for details. Details: `' . print_r( $delete_result, true ) . '`. Clearing pendingDelete var for next batch.' );
						} else {
							pb_backupbuddy::status( 'details', 'Deleted batch of `' . count( $pending_delete ) . '` remote files. Cleaning pendingDelete var for next batch.' );
							$files_deleted += count( $pending_delete );
							$files_deleted_this_round += count( $pending_delete );
						}
						$pending_delete = array();
					}

				} else { // Remotely stored file found in local catalog. Updated verified audit timestamp.
					// Update 'v' key (for verified) with current timestamp to show it is verified as being on remote server.
					self::$_catalog[ '/' . $file['Key'] ]['v'] = time();
				}
			}

			// Process any remaining deletions.
			if ( count( $pending_delete ) > 0 ) {
				if ( true !== ( $delete_result = pb_backupbuddy_destination_live::deleteFile( $destination_settings, $pending_delete ) ) ) {
					pb_backupbuddy::status( 'error', 'Error #373262793: Unable to delete one or more remote files. See log above for details. Details: `' . $delete_result . '`. Clearing pendingDelete var for next batch.' );
				} else {
					pb_backupbuddy::status( 'details', 'Deleted batch of `' . count( $pending_delete ) . '` remote files.' );
					$files_deleted += count( $pending_delete );
				}
				unset( $pending_delete );
			}

			pb_backupbuddy::status( 'details', 'Deleted `' . $files_deleted_this_round . '` total files this round out of `' . count( $files ) . '` listed. Looped `' . $loop_count . '` times.' );

			// See if it's time to save 'v' key changes so far.
			if ( self::is_time_to_save( $last_save ) ) {
				self::$_catalogObj->save();
				//self::$_stateObj->save();
				$last_save = microtime( true );
			}

			$files_listed_minus_skips = ( $total_listed - $serial_skips );
			$total_files = ( $files_listed_minus_skips - $files_deleted );
			$total_tables_minus_skips = ( $total_tables - $serial_skips );

			// If files retrieves is >= to the list limit then there may be more files. Set marker and chunk.
			if ( count( $files ) < $destination_settings['max_filelist_keys'] ) { // No more files remain.
				$keep_looping = false;

				self::$_catalogObj->save();
				self::$_state['stats']['last_file_audit_finish'] = microtime( true ); // Audit finish time.

				$running_count += $total_files - $total_tables_minus_skips;

				pb_backupbuddy::status(
					'details',
					sprintf(
						'No more files to check. Deleted `%1$s` out of listed `%2$s` (`%3$s` files, Deleted `%4$s` tables out of `%5$s` total tables. `%6$s` skipped database/serial dir). `%7$s` files+tables.sql files after deletions. Files running count: `%8$s`.',
						$files_deleted,
						$total_listed,
						$files_listed_minus_skips,
						$tables_deleted,
						$total_tables_minus_skips,
						$serial_skips,
						$total_files,
						$running_count
					)
				);

				if ( $running_count < self::$_state['stats']['files_total_count'] ) {
					$message = 'Attention! Remote storage lists fewer files (' . $running_count . ') than expected (' . self::$_state['stats']['files_total_count'] . '). More files may be pending transfer. Deleted: `' . $files_deleted_this_round . '`.';
					self::write_error_messages( $message );
				}

				return true;
			} else { // More files MAY remain.
				pb_backupbuddy::status( 'details', 'More files remain to check. Deleted `' . $files_deleted . '` total files this round so far. Files running count: `' . ( $running_count + $total_files - $total_tables_minus_skips ) . '`.' );

				$marker = end( $files );
				$marker = $marker['Key'];
				reset( $files );

				// Do we have enough time to proceed or do we need to chunk?
				$time_elapsed      = ( microtime( true ) - pb_backupbuddy::$start_time );
				$time_remaining    = $destination_settings['_max_time'] - ( $time_elapsed + self::TIME_WIGGLE_ROOM ); // Estimated time remaining before PHP times out. Unit: seconds.
				$avg_time_per_loop = ( microtime( true ) - $loop_start ) / $loop_count;
				pb_backupbuddy::status( 'details', 'Time elapsed: `' . $time_elapsed . '`, estimated remaining: `' . $time_remaining . '`, average time needed per loop: `' . $avg_time_per_loop . '`. Max time setting: `' . $destination_settings['_max_time'] . '`.' );
				if ( $avg_time_per_loop >= $time_remaining ) { // Not enough time for another loop. Chunk.
					$keep_looping = false;
					self::$_catalogObj->save();

					$running_count += $total_files - $total_tables_minus_skips;

					pb_backupbuddy::status(
						'details',
						sprintf(
							'Running out of time processing file audit. Took `%1$s` seconds to delete `%2$s` out of listed `%3$s` (`%4$s` files, Deleted `%5$s` tables out of `%6$s` total tables. `%7$s` skipped database/serial dir). `%8$s` files after deletions. Starting next at `%9$s`.  Files running count: `%10$s`.',
							( microtime( true ) - $loop_start ),
							$files_deleted,
							$total_listed,
							$files_listed_minus_skips,
							$tables_deleted,
							$total_tables_minus_skips,
							$serial_skips,
							( $files_listed_minus_skips - $files_deleted ),
							$marker,
							$running_count
						)
					);

					return array( __( 'Auditing remote files', 'it-l10n-backupbuddy' ), array( $marker, $running_count, $found_dat ) );
				} else {
					// Proceed looping in this PHP page load.
					$keep_looping = true;
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
				self::write_error_messages( $error );
			} else {
				// Send DAT file.
				$send_id = 'live_' . md5( $dat_file ) . '-' . pb_backupbuddy::random_string( 6 );
				$destination_settings['_database_table'] = 'backupbuddy_dat.php';
				if ( false === pb_backupbuddy_destinations::send( $destination_settings, $dat_file, $send_id, true, false, self::TRIGGER, backupbuddy_live::getLiveID() ) ) {
					$error = 'Error #348983434: Unable to send DAT file to Live servers DURING AUDIT. See error log above for details.';
					self::write_error_messages( $error );
				}
			}
		}

		// If not all files have uploaded, skip snapshot for now.
		if ( ( self::$_state['stats']['files_pending_send'] > 0 ) || ( self::$_state['stats']['tables_pending_send'] > 0 ) ) {
			pb_backupbuddy::status( 'details', '`' . self::$_state['stats']['files_pending_send'] . '` files and `' . self::$_state['stats']['tables_pending_send'] . '` database tables are still pending transfer. Waiting for transfers to finish before creating Snapshot.' );
			self::$_state['stats']['wait_on_transfers_start'] = microtime( true );
			self::queue_step( 'wait_on_transfers', array(), true );
			return true;
		}

		return true;
	}

	/**
	 * Get the remote destination settings for Solid Backups Stash Live. Eg advanced settings.
	 *
	 * @return array  Array of settings.
	 */
	public static function get_destination_settings() {
		require_once( pb_backupbuddy::plugin_path() . '/classes/core.php' );
		require_once( pb_backupbuddy::plugin_path() . '/destinations/live/init.php' );

		$settings = pb_backupbuddy_destination_live::_formatSettings( pb_backupbuddy::$options['remote_destinations'][ backupbuddy_live::getLiveID() ] );
		$settings['_max_time'] = backupbuddy_core::adjustedMaxExecutionTime( $settings['max_time'] );

		if ( $settings['_max_time'] < self::MIN_ADJUSTED_MAX_RUNTIME_BEFORE_WARNING ) {
			pb_backupbuddy::status(
				'warning',
				sprintf(
					'Warning #893483984: Adjusted max execution time below warning threshold of `%1$s` seconds. Check destination max runtime setting and/or host PHP max execution time limit. Destination setting: `%2$s`. Adjusted: `%3$s`.',
					self::MIN_ADJUSTED_MAX_RUNTIME_BEFORE_WARNING,
					$settings['max_time'],
					backupbuddy_core::adjustedMaxExecutionTime( $settings['max_time'] )

				)
			);
		}
		if ( $settings['_max_time'] < self::MIN_ADJUSTED_MAX_RUNTIME_BEFORE_ERROR ) {
			pb_backupbuddy::status(
				'warning',
				sprintf(
					'Error #438949843: Adjusted max execution time below error threshold of `%1$s` seconds. Check destination max runtime setting and/or host PHP max execution time limit. Destination setting: `%2$s`. Adjusted: `%3$s`.',
					self::MIN_ADJUSTED_MAX_RUNTIME_BEFORE_ERROR,
					$settings['max_time'],
					backupbuddy_core::adjustedMaxExecutionTime( $settings['max_time'] )
				)
			);
		}

		return $settings;
	}

	/**
	 * Get the stats from the state file. Read only.
	 *
	 * @param string  $type  Type of stats to get. Eg 'files' or 'tables'.
	 *
	 * @return array|bool  Array of stats, else false.
	 */
	public static function get_file_stats( $type ) {

		pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #17...' );
		require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';

		$statsObj = new pb_backupbuddy_fileoptions(
			backupbuddy_core::getLogDirectory() . 'live/' . $type . '-' . pb_backupbuddy::$options['log_serial'] . '.txt',
			true,
			false
		);
		if ( true !== ( $result = $statsObj->is_ok() ) ) {
			pb_backupbuddy::status( 'error', 'Error #3443794. Fatal error. Unable to create or access fileoptions file for media. Details: `' . $result . '`.' );
			die();
		}
		pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );

		if ( isset( $statsObj->options['stats'] ) ) {
			return $statsObj->options['stats'];
		}

		return false;
	}

	/**
	 * Get the ignore_lock timeout value.
	 *
	 * This is the value to use for the ignore_lock parameter when loading the state file.
	 *
	 * @return int  The lock ignore timeout value.
	 */
	private static function _fileoptions_lock_ignore_timeout_value() {
		return backupbuddy_core::detectLikelyHighestExecutionTime() + backupbuddy_constants::TIMED_OUT_PROCESS_RESUME_WIGGLE_ROOM;
	}

	/**
	 * Load the latest state.
	 *
	 * @param  bool  $force_load         Whether to force loading if already loaded.
	 *                                   Defaults to false, do not reload if already loaded.
	 * @param  bool  $get_contents_only  By default, (false) the state will be loaded into self::$_state.
	 *                                   When true instead only the contents are loaded and returned, not touching self::_state.
	 *
	 * @return bool|array  True if loaded, false if not.
	 *                     If $get_contents_only is true then the contents of the state file are returned.
	 */
	private static function _load_state( $force_load = false, $get_contents_only = false ) {
		require_once( pb_backupbuddy::plugin_path() . '/classes/core.php' );

		if ( ( true !== $force_load ) && ( is_object( self::$_stateObj ) ) ) {
			if ( $get_contents_only ) {
				return self::$_stateObj->options;
			} else {
				return true;
			}
		}

		$read_only   = true;
		$ignore_lock = true;

		if ( empty( $get_contents_only ) ) {
			$read_only   = false;
			$ignore_lock = self::_fileoptions_lock_ignore_timeout_value();
		}

		// Load state fileoptions.
		require_once( pb_backupbuddy::plugin_path() . '/classes/fileoptions.php' );
		$stateObj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'live/state-' . pb_backupbuddy::$options['log_serial'] . '.txt', $read_only, $ignore_lock, true );
		if ( true !== ( $result = $stateObj->is_ok() ) ) {
			$caller = backupbuddy_core::getCallingFunctionName();
			// Don't log as error if it's just from stats not being able to load state as this can happen due to state being locked a lot during rapid activity.
			if ( 'backupbuddy_live_periodic::_get_daily_stats_ref()' !== $caller ) {
				pb_backupbuddy::status(
					'error',
					sprintf(
						'Error #3297392B. Fatal error. Unable to create or access SERIAL fileoptions file. Details: `%1$s`. Waiting a moment before ending. Read only: `%2$s`, ignore lock: `%3$s`, contents only: `%4$s`. Caller: `%5$s`.',
						$result,
						$read_only,
						$ignore_lock,
						$get_contents_only,
						$caller
					)
				);
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
			'stats'         => array(),
		), $stateObj->options );

		$stateObj->options['step']  = array_merge( self::$_step_defaults, $stateObj->options['step'] );
		$stateObj->options['stats'] = array_merge( self::$_stats_defaults, $stateObj->options['stats'] );

		// Getting contents only.
		if ( $get_contents_only ) {
			return $stateObj->options;
		}

		// Set class variables with references to object and options within.
		self::$_stateObj = &$stateObj;
		self::$_state    = &self::$_stateObj->options;

		return true;
	}

	/**
	 * Load the catalog into a class variable for usage by functions.
	 *
	 * @param bool  $force_reload       Whether to force loading if already loaded.
	 *                                  Defaults to false, do not reload if already loaded.
	 * @param bool  $get_contents_only  By default, (false) the catalog will be loaded into self::$_catalog.
	 *                                  When true instead only the contents are loaded and returned, not touching self::_catalog.
	 *
	 * @return bool|array  True if loaded, false if not.
	 *                     If $get_contents_only is true then the contents of the catalog file are returned.
	 */
	private static function _load_catalog( $force_reload = false, $get_contents_only = false ) {

		if ( is_object( self::$_catalogObj ) && ( true !== $force_reload ) ) {
			return self::$_catalogObj;
		}

		if ( $force_reload ) {
			unset( self::$_catalogObj );
		}

		$read_only   = false;
		$ignore_lock = self::_fileoptions_lock_ignore_timeout_value();

		if ( $get_contents_only ) {
			$read_only   = true;
			$ignore_lock = true;
		}

		require_once( pb_backupbuddy::plugin_path() . '/classes/fileoptions.php' );
		$catalogObj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'live/catalog-' . pb_backupbuddy::$options['log_serial'] . '.txt', $read_only, $ignore_lock, true, true );
		if ( true !== ( $result = $catalogObj->is_ok() ) ) {
			pb_backupbuddy::status(
				'error',
				sprintf(
					'Error #239239034. Fatal error. Unable to create or access CATALOG fileoptions file. Details: `%1$s`. Waiting a moment before ending. Read only: `%2$s`, ignore lock: `%3$s`, contents only: `%4$s`. Caller: `%5$s`.',
					$result,
					$read_only,
					$ignore_lock,
					$get_contents_only,
					backupbuddy_core::getCallingFunctionName()
				)
			);
			sleep( 3 ); // Wait a moment to give time for temporary issues to resolve.
			return false;
		}

		// Set defaults.
		if ( ! is_array( $catalogObj->options ) ) {
			$catalogObj->options = array();
		}

		// Getting contents only.
		if ( $get_contents_only ) {
			return $catalogObj->options;
		}

		// Set class variables with references to object and options within.
		self::$_catalogObj = &$catalogObj;
		self::$_catalog    = &$catalogObj->options;

		return true;
	}

	/**
	 * Load the tables signatures into a class variable for usage by functions.
	 *
	 * @param bool  $force_reload       Whether to force loading if already loaded.
	 *                                  Defaults to false, do not reload if already loaded.
	 * @param bool  $get_contents_only  By default, (false) the tables will be loaded into self::$_tables.
	 *                                  When true instead only the contents are loaded and returned, not touching self::_tables.
	 *
	 * @return bool|array  True if loaded, false if not. If $get_contents_only is true then the contents of the tables file are returned.
	 */
	private static function _load_tables( $force_reload = false, $get_contents_only = false ) {

		if ( is_object( self::$_tablesObj ) && ( true !== $force_reload ) ) {
			return self::$_tablesObj;
		}

		if ( $force_reload ) {
			unset( self::$_tablesObj );
		}

		$read_only = false;
		$ignore_lock = self::_fileoptions_lock_ignore_timeout_value();
		if ( true === $get_contents_only ) {
			$read_only   = true;
			$ignore_lock = false;
		}

		require_once( pb_backupbuddy::plugin_path() . '/classes/fileoptions.php' );
		$tablesObj = new pb_backupbuddy_fileoptions(
			backupbuddy_core::getLogDirectory() . 'live/tables-' . pb_backupbuddy::$options['log_serial'] . '.txt',
			$read_only,
			$ignore_lock,
			true,
			true
		);
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
	}

	/**
	 * Mark a file as being sent to server after a successful remote file transfer.
	 *
	 * Handles files and database table SQL dump confirmation.
	 *
	 * @param  string  $file           Filename relative to ABSPATH. Should have leading slash.
	 * @param  string  $database_file  Blank for normal file. Database table name if a database file.
	 *
	 * @return bool  True if successful, false if not.
	 */
	public static function set_file_backed_up( $file, $database_tables = '' ) {

		if ( false === self::_load_state() ) {
			pb_backupbuddy::status( 'warning', 'Warning #489348344: set_file_backed_up() could not load state.' );
			return false;
		}

		$normal_file = empty( $database_tables );

		$daily_stats_ref = &self::_get_daily_stats_ref();

		// Update catalog and stats.
		if ( $normal_file ) {

			if ( false === self::_load_catalog() ) {
				return false;
			}

			if ( ! isset( self::$_catalog[ $file ] ) ) {
				pb_backupbuddy::status( 'warning', 'Warning #28393833: Unable to set file `' . $file . '` as backed up. It was not found in the catalog. Was it deleted?' );
				return false;
			}

			pb_backupbuddy::status( 'details', 'Saving catalog that file `' . $file . '` has been backed up.' );

			self::$_catalog[ $file ]['b'] = time(); // Time backed up to server.
			unset( self::$_catalog[ $file ]['t'] ); // Reset try (send attempt) counter back to zero since it succeeded.

			self::$_state['stats']['files_pending_send'] = self::decrement( self::$_state['stats']['files_pending_send'] );

			// Daily total and size updates for stats.
			$daily_stats_ref['f_t']++;
			$daily_stats_ref['f_s'] += self::$_catalog[ $file ]['s'];

			self::$_catalogObj->save();
		} else {
			// Database table; not a normal file.
			if ( false === self::_load_tables() ) {
				return false;
			}

			if ( 'backupbuddy_dat.php' === $database_tables || 'importbuddy.php' === $database_tables ) {
				return true;
			}

			self::$_tables[ $database_tables ]['b']       = time(); // Time backed up to server.
			self::$_tables[ $database_tables ]['t']       = 0; // Reset try (send attempt) counter back to zero since it succeeded.
			self::$_state['stats']['tables_pending_send'] = self::decrement( self::$_state['stats']['tables_pending_send'] );

			// Daily total and size updates for stats.
			$daily_stats_ref['d_t']++;
			$daily_stats_ref['d_s'] += self::$_tables[ $database_tables ]['s'];

			self::$_tablesObj->save();
		}

		self::$_stateObj->save();

		return true;
	}

	/**
	 * Get the daily stats (reference).
	 *
	 * @return array|false  Reference to daily stats array. False if state could not be loaded.
	 */
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
			self::$_state['stats']['daily'][ $stamp ] = self::$_stats_daily_defaults;
		} else {
			self::$_state['stats']['daily'][ $stamp ] = array_merge( self::$_stats_daily_defaults, self::$_state['stats']['daily'][ $stamp ] );
		}

		// Don't let too many days build up in stats.
		if ( count( self::$_state['stats']['daily'] ) > self::MAX_DAILY_STATS_DAYS ) {
			$remove_count = count( self::$_state['stats']['daily'] ) - self::MAX_DAILY_STATS_DAYS;
			$output = array_slice( self::$_state['stats']['daily'], $remove_count );

			self::$_state['stats']['daily'] = $output;
			self::$_stateObj->save();
		}

		return self::$_state['stats']['daily'][ $stamp ];
	}

	/**
	 * Wait on pending file transfers.
	 *
	 * @return bool  True if successful, false if not.
	 */
	public static function _step_wait_on_transfers() {
		$sleep_time = 10;

		if ( false === self::_load_state() ) {
			pb_backupbuddy::status( 'warning', 'Warning #4383434043: _step_wait_on_transfers() could not load state.' );
			return false;
		}

		if ( ( self::$_state['stats']['files_pending_send'] > 0 ) || ( self::$_state['stats']['tables_pending_send'] > 0 ) ) {

			if ( empty( self::$_state['stats']['wait_on_transfers_start'] ) ) {
				self::$_state['stats']['wait_on_transfers_start'] = microtime( true ); // Make sure timestamp set to prevent infinite loop.
				self::$_stateObj->save();
			}

			/*
			 * Start by retrying the pending send.
			 *
			 * Most of the time the issue with unsent files
			 * isn't that they haven't finished sending, it's
			 * that they were passed over for some reason in a
			 * previous step.
			 */
			self::retry_files_pending_send();
			self::retry_tables_pending_send();

			$destination_settings = self::get_destination_settings();
			// Giving up.
			if ( ( time() - self::$_state['stats']['wait_on_transfers_start'] ) > ( $destination_settings['max_wait_on_transfers_time'] * 60 ) ) {
				pb_backupbuddy::status(
					'warning',
					sprintf(
						__( 'Ran out of max time (`%s` max mins) waiting for pending transfers to finish. Giving up until next periodic process is triggered.', 'it-l10n-backupbuddy' ),
						round( ( ( time() - self::$_state['stats']['wait_on_transfers_start'] ) / 60 ) )
					)
				);
				return false;
			}

			pb_backupbuddy::status(
				'details',
				sprintf(
					'Sleeping for `%1$s` secs to wait on `%2$s` file and `%3$s` database table transfers. Closing state.',
					$sleep_time,
					self::$_state['stats']['files_pending_send'],
					self::$_state['stats']['tables_pending_send']
				)
			);
			self::$_stateObj = ''; // Close stateObj so sleeping won't hinder other operations.
			sleep( $sleep_time );

			// Re-open state.
			if ( false === self::_load_state() ) {
				return false;
			}

			if ( ( ! is_numeric( self::$_state['stats']['files_pending_send'] ) ) || ( ! is_numeric( self::$_state['stats']['tables_pending_send'] ) ) ) {
				pb_backupbuddy::status( 'error', 'Error #83989484: files_pending_send or tables_pending_send missing numeric value. State details: `' . print_r( self::$_state, true ) . '`.' );
			}

			pb_backupbuddy::status(
				'details',
				sprintf(
					'`%1$s` files and `%2$s` database tables are still pending transfer after sleeping. Waiting for transfers to finish before creating Snapshot (`%3$s` of `%4$s` max mins elapsed).',
					self::$_state['stats']['files_pending_send'],
					self::$_state['stats']['tables_pending_send'],
					round( ( time() - self::$_state['stats']['wait_on_transfers_start'] ) / 60 ),
					$destination_settings['max_wait_on_transfers_time']
				)
			);
			$waiting_list_limit = 5;

			// Show some of the files pending send for troubleshooting.
			$files_pending_send_file = backupbuddy_core::getLogDirectory() . 'live/files_pending_send-' . pb_backupbuddy::$options['log_serial'] . '.txt';
			if ( self::$_state['stats']['files_pending_send'] > 0 ) {
				$files_pending_count = is_array( self::$_state['stats']['files_pending_send'] ) ? count( self::$_state['stats']['files_pending_send'] ) : 0;
				pb_backupbuddy::status( 'details', 'Files pending send (`' . $files_pending_count . '`).' );
				if ( false !== self::_load_catalog() ) {
					$waiting_file_list = array();
					foreach( self::$_catalog as $catalog_filename => $catalog_file ) {
						if ( ! self::is_backed_up( $catalog_file ) ) { // Not yet transferred.
							$tries = ! empty( $catalog_file['t'] ) ? $catalog_file['t'] : 0;
							$waiting_file_list[] = $catalog_filename . ' (' . $tries . ' send tries)';
						}
						if ( count( $waiting_file_list ) > $waiting_list_limit ) {
							break;
						}
					}

					if ( count( $waiting_file_list ) > 0 ) {
						pb_backupbuddy::status( 'details', 'List of up to `' . $waiting_list_limit . '` of `' . self::$_state['stats']['files_pending_send'] . '` pending file sends: ' . implode( '; ', $waiting_file_list ) );

						if ( false === @file_put_contents( $files_pending_send_file, implode( "\n", $waiting_file_list ) ) ) {
							// Unable to write.
							pb_backupbuddy::status( 'details', 'Unable to write to: ' . $files_pending_send_file );
						}
					}
				} else {
					pb_backupbuddy::status( 'details', 'Catalog not ready for preview of pending file list. Skipping.' );
				}
			} else { // No files pending. Deleting/wiping pending file.
				pb_backupbuddy::status( 'details', 'No files pending send. Wiping the files_pending_send file.' );
				@file_put_contents( $files_pending_send_file, '' );
				@unlink( $files_pending_send_file );
			}

			// Show some of the tables pending send for troubleshooting.
			$tables_pending_send_file = backupbuddy_core::getLogDirectory() . 'live/tables_pending_send-' . pb_backupbuddy::$options['log_serial'] . '.txt';
			if ( self::$_state['stats']['tables_pending_send'] > 0 ) {
				pb_backupbuddy::status( 'details', 'Tables pending send (`' . count( self::$_state['stats']['tables_pending_send'] ) . '`).' );
				if ( false !== self::_load_tables() ) {
					$waiting_table_list = array();
					foreach( self::$_tables as $table_name => $table ) {
						if ( ! self::is_backed_up( $table )  ) { // Not yet transferred.
							$waiting_table_list[] = $table_name . ' (' . $table['t'] . ' send tries)';
						}
						if ( count( $waiting_table_list ) > $waiting_list_limit ) {
							break;
						}
					}
					if ( count( $waiting_table_list ) > 0 ) {
						pb_backupbuddy::status( 'details', 'List of up to `' . $waiting_list_limit . '` of `' . self::$_state['stats']['tables_pending_send'] . '` pending table sends: ' . implode( '; ', $waiting_table_list ) );

						if ( false === @file_put_contents( $tables_pending_send_file, implode( "\n", $waiting_table_list ) ) ) {
							pb_backupbuddy::status( 'details', 'Unable to write to: ' . $files_pending_send_file );
						}
					}
				} else {
					pb_backupbuddy::status( 'details', 'Table catalog not ready for preview of pending table list. Skipping.' );
				}
			} else { // No tables pending send. Deleting/wiping pending file.
				pb_backupbuddy::status( 'details', 'No tables pending send. Wiping the tables_pending_send file.' );
				@file_put_contents( $tables_pending_send_file, '' );
				@unlink( $tables_pending_send_file );
			}

			self::queue_step( 'wait_on_transfers', array(), true );
			return true;
		}

		// No more files are pending. Jumps back to snapshot.
		return true;
	}

	/**
	 * Step to run a remote snapshot if it's approximately time to do so.
	 *
	 * @return bool  True if successful, false if not.
	 */
	public static function _step_run_remote_snapshot() {

		if ( false === self::_load_state() ) {
			return false;
		}

		// If not all files have uploaded, skip snapshot for now.
		if ( ( self::$_state['stats']['files_pending_send'] > 0 ) || ( self::$_state['stats']['tables_pending_send'] > 0 ) ) {
			pb_backupbuddy::status(
				'details',
				sprintf(
					'`%1$s` files and `%2$s` database tables are still pending transfer. Waiting for transfers to finish before creating Snapshot.',
					self::$_state['stats']['files_pending_send'],
					self::$_state['stats']['tables_pending_send']
				)
			);
			self::$_state['stats']['wait_on_transfers_start'] = microtime( true );
			self::queue_step( 'wait_on_transfers', array(), true );
			return true;
		}

		if ( ( empty( self::$_state['stats']['files_total_count'] ) ) || ( empty( self::$_state['stats']['tables_total_count'] ) ) ) {
			$error = sprintf(
				'Error #3489349834: Made it to the snapshot stage but there are zero files and/or tables. Both files and database table counts should be greater than zero. Halting to protect backup integrity. Files: `%1$s`. Tables: `%2$s`.',
				self::$_state['stats']['files_total_count'],
				self::$_state['stats']['tables_total_count']
			);
			backupbuddy_core::addNotification( 'live_error', 'Solid Backups Stash Live Error', $error );
			return $error;
		}

		if ( false !== self::$_state['stats']['manual_snapshot'] ) {
			pb_backupbuddy::status(
				'details',
				sprintf(
					'Manual snapshot requested at `%1$s` (%2$s ago). Triggering remote snapshot now.',
					pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( self::$_state['stats']['manual_snapshot'] ) ),
					pb_backupbuddy::$format->time_ago( self::$_state['stats']['manual_snapshot'] )
			)
			);
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
			pb_backupbuddy::status(
				'details',
				sprintf(
					'Period between remote snapshots: `%1$s` (`%2$s` seconds). Time since last run: `%3$s`. Allowed to run `%4$s` secs early. Adjusted min delay between runs: `%5$s`.',
					$destination_settings['remote_snapshot_period'],
					$delay_between_runs,
					$time_since_last_run,
					self::REMOTE_SNAPSHOT_PERIOD_WIGGLE_ROOM,
					$adjusted_delay_between_runs
				)
			);

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
			self::write_error_messages( $error );
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
				$schedule_result = backupbuddy_core::schedule_single_event( time() + ( 60 * 60 ), 'live_after_snapshot', array( '' ) ); // 1hr
				if ( true === $schedule_result ) {
					pb_backupbuddy::status( 'details', 'Next Live trim cron event scheduled.' );
				} else {
					pb_backupbuddy::status( 'error', 'Next Live trim cron event FAILED to be scheduled.' );
				}

				// Stop live pulse.
				self::stop_live_pulse();
				self::remove_pending_events();

				backupbuddy_core::maybe_spawn_cron();

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

	}

	/**
	 * Trigger a remote snapshot.
	 *
	 * @param  string  $trigger  Trigger type. 'manual' or 'automatic'.
	 *
	 * @return array|bool  Array of response data on success, else false.
	 */
	public static function _run_remote_snapshot( $trigger = 'unknown' ) {

		if ( false === self::_load_state() ) {
			return false;
		}

		$destination_settings = backupbuddy_live_periodic::get_destination_settings();

		// Send email notification?
		if ( ( '1' == $destination_settings['send_snapshot_notification'] ) || ( 0 == self::$_state['stats']['first_completion'] ) ) { // Email notification enabled _OR_ it's the first snapshot for this site.
			if ( ! empty( $destination_settings['email'] ) ) {
				$email = $destination_settings['email'];
			} else {
				pb_backupbuddy::status(
					'details',
					sprintf(
						'Snapshot set to send email notification to account. Send notification?: `%1$s`. First completion: `%2$s`.',
						$destination_settings['send_snapshot_notification'],
						self::$_state['stats']['first_completion']
					)
				);
				$email = 'account';
			}
		} else {
			pb_backupbuddy::status( 'details', 'Snapshot set not to send email notification.' );
			$email = 'none';
		}

		$additional_params = array(
			'ibpass'     => '', // Gets set below.
			'email'      => $email, // Valid options: email@address.com, 'none', 'account'
			'stash_copy' => true,
			'trim'       => backupbuddy_live::get_archive_limit_settings_array( false ),
		);

		if ( ! empty( pb_backupbuddy::$options['importbuddy_pass_hash'] ) ) {
			$additional_params['ibpass'] = pb_backupbuddy::$options['importbuddy_pass_hash'];
		}
		if ( false !== ( $timezone = self::tz_offset_to_name( get_option('gmt_offset') ) ) ) {
			$additional_params['timezone'] = $timezone;
		}
		require_once( pb_backupbuddy::plugin_path() . '/destinations/live/init.php' );

		$response = pb_backupbuddy_destination_live::stashAPI( $destination_settings, 'live-snapshot', $additional_params, true, true );

		self::$_state['stats']['last_remote_snapshot_trigger']       = $trigger;
		self::$_state['stats']['last_remote_snapshot_response']      = $response;
		self::$_state['stats']['last_remote_snapshot_response_time'] = microtime( true );
		self::$_state['stats']['manual_snapshot']                    = false; // Set false no matter what.

		if ( pb_backupbuddy::full_logging() ) {
			pb_backupbuddy::status( 'details', 'live-snapshot response due to logging level: `' . print_r( $response, true ) . '`. Call params: `' . print_r( $additional_params, true ) . ' `.' );
		}

		do_action( 'backupbuddy_run_remote_snapshot_response', $response );

		return $response;
	}

	/**
	 * Get the tables pending send and try to resend them.
	 *
	 * @return int|bool  Number of files remaining to send, false on error.
	 */
	public static function retry_tables_pending_send() {
		// Get the files that are pending send.
		if ( empty( self::_load_tables() ) || empty( self::_load_state() ) ) {
			return false;
		}

		$requeue_tables = array_filter( self::$_tables, function( $details ) {
			return empty( self::is_backed_up( $details ) );
		} );

		// Requeue them.
		if ( empty( $requeue_tables ) ) {

			$tables_pending_send_file = backupbuddy_core::getLogDirectory() . 'live/tables_pending_send-' . pb_backupbuddy::$options['log_serial'] . '.txt';
			@file_put_contents( $tables_pending_send_file, '' );
			@unlink( $tables_pending_send_file );

			self::$_state['stats']['tables_pending_send'] = 0;
			self::$_tablesObj->save();
			self::$_stateObj->save();

			pb_backupbuddy::status( 'details', 'No tables waiting finish.' );
			return 0;
		}

		pb_backupbuddy::status( 'details', 'Requeuing `' . count( $requeue_tables ) . '` database tables that are pending send.' );

		$results = self::send_tables( $requeue_tables );

		self::$_tablesObj->save();
		self::$_stateObj->save();

		return $results['more_remain'];

	}

	/**
	 * Get the files pending send and try to resend them.
	 *
	 * @return int|bool  Number of files remaining to send, false on error.
	 */
	public static function retry_files_pending_send() {
		// Get the files that are pending send.
		if ( empty( self::_load_catalog() ) || empty( self::_load_state() ) ) {
			return false;
		}

		$requeue_files = array_filter( self::$_catalog, function( $details ) {
			return empty( self::is_backed_up( $details ) );
		} );

		// Requeue them.
		if ( empty( $requeue_files ) ) {

			$files_pending_send_file = backupbuddy_core::getLogDirectory() . 'live/files_pending_send-' . pb_backupbuddy::$options['log_serial'] . '.txt';
			@file_put_contents( $files_pending_send_file, '' );
			@unlink( $files_pending_send_file );

			self::$_state['stats']['files_pending_send'] = 0;
			self::$_catalogObj->save();
			self::$_stateObj->save();

			pb_backupbuddy::status( 'details', 'No files waiting finish.' );
			return 0;
		}

		pb_backupbuddy::status( 'details', 'Requeuing `' . count( $requeue_files ) . '` files that are pending send.' );

		$results = self::send_files( $requeue_files );

		self::$_catalogObj->save();
		self::$_stateObj->save();

		return $results['more_remain'];

	}

	/**
	 * Update the timestamp for when the last remote snapshot was triggered to begin.
	 *
	 * @param  string  $snapshot_id  Snapshot ID.
	 * @param  array   $snapshot_response  Snapshot response array.
	 *
	 * @return  bool  True on success, else false.
	 */
	public static function update_last_remote_snapshot_time( $snapshot_id = '', $snapshot_response = array() ) {

		if ( false === self::_load_state() ) {
			return false;
		}

		self::$_state['stats']['last_remote_snapshot']    = microtime( true );
		self::$_state['stats']['last_remote_snapshot_id'] = $snapshot_id;
		self::$_state['stats']['manual_snapshot']         = false; // Set false no matter what.

		// First snapshot?
		if ( empty( self::$_state['stats']['first_completion'] ) ) {
			self::$_state['stats']['first_completion'] = microtime( true );
		}

		// Save state.
		self::$_stateObj->save();

		// Save BB core options to record last successful backup.
		pb_backupbuddy::$options['last_backup_finish'] = time();
		pb_backupbuddy::save();

		pb_backupbuddy::status( 'details', 'Time since remote snapshot ran updated.' );
		return true;
	}

	/**
	 * Reset the last activity timestamp to zero.
	 *
	 * Used in the UI for debugging.
	 *
	 * @return bool  True if successful, else false.
	 */
	public static function reset_last_activity() {

		if ( false === self::_load_state() ) {
			return false;
		}

		self::$_state['stats']['last_activity'] = 0;
		self::$_stateObj->save();

		return true;
	}

	/**
	 * Reset the last file audit finish timestamp to zero.
	 *
	 * Used in the UI for debugging.
	 *
	 * @return bool  True if successful, else false.
	 */
	public static function reset_file_audit_times() {

		if ( false === self::_load_state() ) {
			return false;
		}

		self::$_state['stats']['last_file_audit_start']  = 0;
		self::$_state['stats']['last_file_audit_finish'] = 0;
		self::$_stateObj->save();

		return true;
	}

	/**
	 * Reset the first completion timestamp to zero.
	 *
	 * Used in the UI for debugging.
	 *
	 * @return bool  True if successful, else false.
	 */
	public static function reset_first_completion() {

		if ( false === self::_load_state() ) {
			return false;
		}

		self::$_state['stats']['first_completion'] = 0;
		self::$_stateObj->save();

		return true;
	}

	/**
	 * Reset the last remote snapshot timestamp to zero.
	 *
	 * Used in the UI for debugging.
	 *
	 * @return bool  True if successful, else false.
	 */
	public static function reset_last_remote_snapshot() {

		if ( false === self::_load_state() ) {
			return false;
		}

		self::$_state['stats']['last_remote_snapshot'] = 0;
		self::$_stateObj->save();

		return true;
	}

	/**
	 * Reset the send attempt counter for all files back to zero.
	 *
	 * Used in the UI for debugging.
	 *
	 * @return bool  True if successful, else false.
	 */
	public static function reset_send_attempts() {

		pb_backupbuddy::status( 'details', 'About to reset send attempt counter for all catalog files.' );
		if ( false === self::_load_catalog() ) {
			return false;
		}

		if ( false === self::_load_state() ) {
			return false;
		}

		foreach( self::$_catalog as $signature_file => &$signature_details ) {
			if ( isset( $signature_details['t'] ) ) {
				unset( $signature_details['t'] );
			}
		}

		self::$_state['stats']['recent_send_fails'] = 0;
		self::$_state['stats']['last_send_fail'] = 0;
		self::$_state['stats']['last_send_fail_file'] ='';
		self::$_state['step']['last_status'] = '';

		self::$_catalogObj->save();
		self::$_stateObj->save();
		pb_backupbuddy::status( 'details', 'Finished resetting send attempt counter for all catalog files.' );

		return true;
	}

	/**
	 * Delete the catalog files.
	 *
	 * Used in the UI for debugging.
	 */
	public static function delete_catalog_files() {
		self::delete_catalog_file();
		self::delete_catalog_file( 'tables' );
		self::delete_catalog_file( 'state' );
	}

	/**
	 * Delete the a file storing catalog/table/state data.
	 *
	 * @param  string  $type  Type of file to delete. 'catalog', 'tables', or 'state'.
	 *
	 * @return bool  True if successful, else false.
	 */
	protected static function delete_catalog_file( $type = 'catalog' ) {
		if ( ! is_string( $type ) ) {
			return false;
		}

		$file = backupbuddy_core::getLogDirectory() . 'live/' . $type . '-' . pb_backupbuddy::$options['log_serial'] . '.txt';
		@unlink( $file );
		sleep( 1 );
		@unlink( $file );
		sleep( 1 );
		@unlink( $file );

		if ( file_exists( $file ) ) {
			pb_backupbuddy::alert( 'Error #3927273: Unable to delete the file cataloging `' . $type . '`. Check permissions or manually delete.' );
		} else {
			pb_backupbuddy::alert( strtoupper( $type ) . ' deleted.' );
		}

		return true;

	}

	/**
	 * Return CONTENTS of state. Not a fileoptions object.
	 *
	 * @param  bool|null  $force_reload  Force reload of state.
	 */
	public static function get_stats() {
		return self::_load_state( false, true );
	}

	/**
	 * Return CONTENTS of catalog. Not a fileoptions object.
	 *
	 * @param  bool|null  $force_reload  Force reload of catalog.
	 */
	public static function get_catalog( $force_reload = null ) {
		return self::_load_catalog( $force_reload, true );
	}

	/**
	 * Return CONTENTS of tables catalog. Not a fileoptions object.
	 *
	 * @param  bool|null  $force_reload  Force reload of catalog.
	 */
	public static function get_tables( $force_reload = null ) {
		return self::_load_tables( $force_reload, true );
	}

	/**
	 * Check if a file is already backed up.
	 *
	 * @param  array  $details  File details.
	 *
	 * @return bool  True if backed up, else false.
	 */
	private static function is_backed_up( $details ) {
		return ! empty( $details['b'] );
	}

	/**
	 * Check if a file is pending delete.
	 *
	 * @param  array  $details  File details.
	 *
	 * @return bool  True if pending delete, else false.
	 */
	private static function is_pending_delete( $details ) {
		return ! empty( $details['d'] );
	}

	/**
	 * Truncate the beginning of the log if it is getting too large.
	 */
	private static function _truncate_log() {

		// Truncate large log.
		$sum_log_file = backupbuddy_core::getLogDirectory() . 'status-live_periodic_' . pb_backupbuddy::$options['log_serial'] . '.txt';
		$max_log_size = pb_backupbuddy::$options['max_site_log_size'] * 1024 * 1024;
		backupbuddy_core::truncate_file_beginning( $sum_log_file, $max_log_size, 50 );

	}

	/**
	 * Back up the catalog file for restore.
	 *
	 * This is used if the catalog file
	 * gets corrupted (e.g. due to process being killed mid-write).
	 *
	 * @return bool  True if successful, else false.
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
	}

	/**
	 * Catch PHP fatal errors and provide info for debugging.
	 */
	public static function shutdown_function() {

		// Get error message.
		// Error types: http://php.net/manual/en/errorfunc.constants.php
		$e = error_get_last();
		//error_log( print_r( $e, true ) );

		if ( $e === NULL ) { // No error of any kind.
			return;
		} else { // Some type of error.
			if ( ! is_array( $e ) || ( E_ERROR !== $e['type'] ) && ( E_USER_ERROR !== $e['type'] ) ) {
				// Return if not a fatal error.
				return;
			}
		}

		// Calculate log directory.
		$log_directory = backupbuddy_core::getLogDirectory(); // Also handles when importbuddy.
		$main_file = $log_directory . 'log-' . pb_backupbuddy::$options['log_serial'] . '.txt';

		// Determine if writing to a serial log.
		if ( ! empty( pb_backupbuddy::$_status_serial ) ) {
			$serial_files = array();
			$status_serials = pb_backupbuddy::$_status_serial;
			if ( ! is_array( $status_serials ) ) {
				$status_serials = array( $status_serials );
			}
			foreach( $status_serials as $serial ) {
				$serial_files[] = $log_directory . 'status-' . $serial . '_' . pb_backupbuddy::$options['log_serial'] . '.txt';
			}
			$write_serial = true;
		} else {
			$write_serial = false;
		}

		// Clean up Scheduled Actions.
		self::stop_live_pulse();
		self::remove_pending_events();

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
	}

	public static function _calculateFileVars() {
		self::$_file_vars = array(
			'{t}' => str_replace( ABSPATH, '', backupbuddy_core::get_themes_root() ),
			'{p}' => str_replace( ABSPATH, '', backupbuddy_core::get_plugins_root() ),
			'{m}' => str_replace( ABSPATH, '', backupbuddy_core::get_media_root() ),
		);
	}

	/**
	 * Replace the variables with file paths.
	 *
	 * @param  string  $string  String to replace.
	 *
	 * @return string  String with variables replaced with file paths.
	 */
	public static function _varToFile( $string ) {
		if ( null === self::$_file_vars ) {
			self::_calculateFileVars();
		}

		foreach( self::$_file_vars as $fileVar => $fileValue ) {
			$string = str_replace( $fileVar, $fileValue, $string );
		}

		return $string;
	}

	/**
	 * Replace the file paths with variables.
	 *
	 * @param  string  $string  String to replace.
	 *
	 * @return string  String with file paths replaced with variables.
	 */
	public static function _fileToVar( $string ) {
		if ( null === self::$_file_vars ) {
			self::_calculateFileVars();
		}

		foreach( self::$_file_vars as $fileVar => $fileValue ) {
			$string = str_replace( $fileValue, $fileVar, $string );
		}

		return $string;
	}

	/**
	 * Get the Signature defaults.
	 *
	 * @return array  Signature defaults.
	 */
	public static function get_signature_defaults() {
		return self::$_signature_defaults;
	}

	/**
	 * Decrement a value, but don't let it go below zero.
	 *
	 * @param  int  $value  Value to check.
	 *
	 * @return int  Value or zero.
	 */
	public static function decrement( $value ) {
		$value--;
		if ( $value < 0 ) {
			return 0;
		}
		return $value;
	}

	/**
	 * Check if the provided step has already completed this run.
	 *
	 * @param  string  $step  Step name.
	 *
	 * @return bool  True if already completed, else false.
	 */
	private static function step_already_completed( $step ) {
		if ( false === self::_load_state() ) {
			return false;
		}

		if ( empty( self::$_state['stats']['completed_this_run'] ) ) {
			return false;
		}

		return in_array( $step, self::$_state['stats']['completed_this_run'], true );
	}

	/**
	 * Helper method to handle many error messages.
	 *
	 * @param string  $message  Error message.
	 */
	private static function write_error_messages( $message ) {
		pb_backupbuddy::status( 'error', $message );
		backupbuddy_core::addNotification( 'live_error', 'Solid Backups Stash Live Error', $message );
	}

	/**
	 * Helper method to handle many warning messages.
	 *
	 * @param string  $message  Warning message.
	 */
	private static function write_warning_messages( $message ) {
		pb_backupbuddy::status( 'warning', $message );
		backupbuddy_core::addNotification( 'live_error', 'Solid Backups Stash Live Error', $message );
	}

	/**
	 * If enough time has elapsed to run a save action.
	 *
	 * @param int $last_save The timestamp of the last save.
	 *
	 * @return bool True if it's time to save, false if not.
	 */
	private static function is_time_to_save( $last_save, $use_microtime = false ) {
		$time = $use_microtime ? microtime( true ) : time();
		return ( $time - $last_save ) > self::SAVE_SIGNATURES_EVERY_X_SECONDS;
	}

	/**
	 * Check if enough time remains to continue.
	 *
	 * @param float $start_time The microtime timestamp of the start of the process.
	 *
	 * @return bool True if there is enough time to continue, false if not.
	 */
	private static function has_time_to_continue( $start_time, $destination_settings ) {
		if ( empty( $destination_settings['_max_time'] ) ) {
			return true;
		}
		$time_elapsed = microtime( true ) - $start_time;
		return ( $time_elapsed + self::TIME_WIGGLE_ROOM ) <= $destination_settings['_max_time'];
	}

	/**
	 * Get the timezone name from the offset.
	 *
	 * @param int $offset The timezone offset in hours.
	 *
	 * @return string|bool The timezone name or false if not found.
	 */
	public static function tz_offset_to_name( $offset ) {
		$offset *= 3600; // convert hour offset to seconds.
		$list = timezone_abbreviations_list();

		foreach ( $list as $abbr ) {
			foreach ( $abbr as $city ) {
				if ( $city['offset'] === $offset ) {
					return $city['timezone_id'];
				}
			}
		}

		return false;
	}

	/**
	 * Queue a Live step.
	 *
	 * $force_use_jump_transient forces use of transient method instead of running now to
	 * prevent issues queueing from within a periodic function step.
	 *
	 * @todo fix the confusing arguments.
	 *
	 * @param string $step           Step to queue.
	 * @param array  $args           Arguments to pass to step.
	 * @param bool   $skip_run_now   Whether to skip running now.
	 * @param bool   $force_run_now  Whether to force running now.
	 */
	public static function queue_step( $step, $args = array(), $skip_run_now = false, $force_run_now = false ) {

		if ( ! $force_run_now ) {

			// Check to see if we should use transient method of hijacking the Live process.
			$should_run_now = false;
			$state          = self::get_stats();

			if ( 'daily_init' === $state['step']['function'] ) {
				// Currently idling, so okay to run now.
				$should_run_now = true;
			} else {
				// Place this line inside this conditional to conserve memory.
				$assume_timed_out_after = backupbuddy_core::adjustedMaxExecutionTime() + backupbuddy_constants::TIMED_OUT_PROCESS_RESUME_WIGGLE_ROOM;
				if ( time() - $state['stats']['last_activity'] > $assume_timed_out_after ) {
					// Probably timed out, so okay to run now.
					$should_run_now = true;
				}
			}

			// User requested to skip running now, AND there's no overriding reason to run now.
			if ( ( $skip_run_now ) && ( ! $should_run_now ) ) {
				// Tells Live process to restart from the beginning (if mid-process) so new settings apply.
				set_transient( 'backupbuddy_live_jump', array( $step, $args ), 60*60*48 );
				return;
			}
		}

		self::_set_next_step( $step, $args );
		self::schedule_next_event( $step, $args );
	}

	/**
	 * Schedule the next live_periodic event.
	 *
	 * Note that the default cron_args contain an empty string.
	 * This is important if you want to schedule an unspecified
	 * live_periodic event.
	 *
	 * @param string $preferred_step The name of the step to schedule.
	 * @param array  $step_args      Arguments to pass to the cron event.
	 *
	 * @return bool|int The timestamp of the next event, true if presently running, or false if not scheduled.
	 */
	public static function schedule_next_event( $preferred_step = '', $step_args = array( '' ) ) {
		$action_args = array(
			$preferred_step,
			$step_args
		);

		$step_label      = ! empty( $preferred_step ) ? '(' . $preferred_step . ') ': '';
		$success_message = __( 'Next Live Periodic chunk step cron event %sready.', 'it-l10n-backupbuddy' );
		$fail_message    = __( 'Next Live Periodic chunk step cron event %swas not scheduled. It may already exist.', 'it-l10n-backupbuddy' );

		$scheduled = false;

		/*
		 * This looks illogical, but it's important.
		 *
		 * If the preferred step is empty, this is coming from an unspecified "bump", and if it already
		 * exists, we stop here to prevent duplicates.
		 *
		 * This is important to do before running backupbuddy_core::trigger_async_event(), as if the event
		 * exists, that function will try to schedule the event for later, creating duplicate events.
		 */
		if ( empty( $preferred_step ) && ! backupbuddy_core::has_scheduled_event( self::TRIGGER, $action_args, self::CRON_GROUP ) ) {
			$scheduled = backupbuddy_core::trigger_async_event( self::TRIGGER, $action_args, self::CRON_GROUP );
		} elseif ( ! empty( $preferred_step ) ) {
			$scheduled = backupbuddy_core::schedule_next_event( self::TRIGGER, $action_args, self::CRON_GROUP );
		}

		$message_type = ! empty( $scheduled ) ? 'details' : 'warning';
		$message      = ! empty( $scheduled ) ? $success_message : $fail_message;
		$message      = sprintf( $message, $step_label );

		pb_backupbuddy::status( $message_type, $message );

		backupbuddy_core::maybe_spawn_cron();

		return $scheduled;
	}

	/**
	 * Schedule a recurring action to drive the process along.
	 *
	 * This should be stopped when send_snapshot ends, so it is not
	 * constantly running.
	 */
	public static function start_live_pulse() {
		$has_scheduled_event = backupbuddy_core::has_scheduled_event(
			'live_pulse',
			array( true ),
			self::CRON_GROUP
		);

		if ( $has_scheduled_event ) {
			return;
		}

		backupbuddy_core::schedule_event(
			time(),
			20, // Twenty seconds.
			'live_pulse',
			array( true ),
			self::CRON_GROUP
		);
	}

	/**
	 * Stop the recurring action that kicks the process.
	 *
	 * This does not use backupbuddy_core::unschedule_event()
	 * for the sake of accuracy.
	 */
	public static function stop_live_pulse() {
		as_unschedule_all_actions(
			backupbuddy_constants::CRON_HOOK,
			array(
				'live_pulse',
				array( true )
			),
			self::CRON_GROUP
		);
	}

	/**
	 * Remove already completed steps from Action Scheduler.
	 */
	private static function remove_completed_step_events() {
		$steps = array_keys( self::$_next_function );

		foreach( $steps as $step ) {
			if ( ! self::step_already_completed( $step ) ) {
				continue;
			}

			/*
			 * Note that the preferred step args is intentionally an array
			 * with an empty string so that this will only pick up the unspecified events.
			 */
			backupbuddy_core::unschedule_event(
				backupbuddy_constants::CRON_HOOK,
				array(
					self::TRIGGER,
					array(
						$step,
						array( '' ),
					)
				),
				self::CRON_GROUP
			);
		}
	}

	/**
	 * Delete *all* live_periodic events.
	 *
	 * This includes completed, pending, and failed events.
	 *
	 * @return int The number of events deleted.
	 */
	public static function delete_all_events() {
		return backupbuddy_core::delete_all_events_by_group( self::CRON_GROUP );
	}

	/**
	 * Remove live_periodic events.
	 *
	 * @return int The number of events deleted, or 0 on error.
	 */
	private static function remove_events_by_status( $status = '' ) {
		if ( empty( $status ) ) {
			$status = ActionScheduler_Store::STATUS_COMPLETE;
		}
		$query_args = array(
			'group' => self::CRON_GROUP,
			'status' => $status,
		);

		$deleted = backupbuddy_core::delete_events( $query_args );

		if ( is_numeric( $deleted ) ) {
			return $deleted;
		}

		return 0;
	}

	/**
	 * Remove all pending live-related actions.
	 *
	 * @return int The number of events deleted, or false on error.
	 */
	public static function remove_pending_events() {
		return self::remove_events_by_status( ActionScheduler_Store::STATUS_PENDING );
	}

	/**
	 * Remove Completed & Cancelled live_periodic events.
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
}
