<?php
/**
 * BackupBuddy Stash Live Parent Class
 *
 * @package BackupBuddy
 * @author Dustin Bolton
 * @since 7.0
 */

/**
 * BackupBuddy Live Class
 */
class backupbuddy_live {

	/**
	 * Transient Name constant
	 *
	 * @var string
	 */
	const STASH_QUOTA_TRANSIENT_NAME = 'backupbuddy_live_stash_quota';

	/**
	 * Transient expiration time
	 *
	 * @var int
	 */
	const STASH_QUOTA_TRANSIENT_EXPIRE = 300;

	/**
	 * Live Destination ID
	 *
	 * @var int
	 */
	private static $_liveDestinationID = '';

	/**
	 * Retrieves quota information for associated Stash account.
	 *
	 * @param bool $bust_cache  Ignore transient when getting quota.
	 *
	 * @return string  Stash Quota
	 */
	public static function getStashQuota( $bust_cache = false ) {
		$quota = get_transient( self::STASH_QUOTA_TRANSIENT_NAME );
		if ( false === $bust_cache && false !== $quota ) {
			return $quota;
		} else {
			$settings = backupbuddy_live_periodic::get_destination_settings();

			require_once( pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php' );
			require_once( pb_backupbuddy::plugin_path() . '/destinations/stash' . $settings['destination_version'] . '/init.php' );

			$quota = call_user_func_array( array( 'pb_backupbuddy_destination_stash' . $settings['destination_version'], 'get_quota' ), array( $settings ) );
			if ( false === $quota ) {
				pb_backupbuddy::status( 'error', 'Error #3489348944: Could not get quota for Stash Live.' );
			}

			set_transient( self::STASH_QUOTA_TRANSIENT_NAME, $quota, self::STASH_QUOTA_TRANSIENT_EXPIRE );

			return $quota;
		}
	}


	/* queue_manual_scan()
	 *
	 * Queues a directory for file and signature scanning. eg: Used by media upload to look for new files (including thumbnails, etc) for an uploaded image.
	 *
	 * @param	string	$directory	Directory to scan. Trailing slash optional. Important: MUST be below/within the ABSPATH or we return false.
	 */
	public static function queue_manual_file_scan( $directory ) {
		require_once( 'live_periodic.php' );

		// If directory within abspath?
		if ( ABSPATH != substr( $directory, 0, strlen( ABSPATH ) ) ) {
			pb_backupbuddy::status( 'warning', 'Warning #438943834: Queued filescan directory `' . $directory . '` not found within ABSPATH. Skipping.' );
			return false;
		}

		self::queue_step( 'update_files_list', array( $directory ) );

	} // End queue_manual_file_scan().



	// $force_use_jump_transient forces use of transient method instead of running now to prevent issues queing from within a periodic function step.
	public static function queue_step( $step, $args = array(), $skip_run_now = false, $force_run_now = false ) {

		$run_now = false;
		$state = backupbuddy_live_periodic::get_stats();
		$assume_timed_out_after = backupbuddy_core::adjustedMaxExecutionTime() + backupbuddy_constants::TIMED_OUT_PROCESS_RESUME_WIGGLE_ROOM;
		if ( 'daily_init' == $state['step']['function'] ) { // Currently idling so okay to run now.
			$run_now = true;
		} elseif ( time() - $state['stats']['last_activity'] > $assume_timed_out_after ) { // Probably timed out so okay to run now.
			$run_now = true;
		}

		set_transient( 'backupbuddy_live_jump', array( $step, $args ), 60*60*48 ); // Tells Live process to restart from the beginning (if mid-process) so new settigns apply.

		if ( ( true === $force_run_now ) || ( ( true === $run_now ) && ( false === $skip_run_now ) ) ) {
			backupbuddy_live_periodic::_set_next_step( $step, $args, $save_now_and_unlock = true );

			$schedule_result = backupbuddy_core::schedule_single_event( time(), 'live_periodic', $cronArgs = array() );
			if ( true === $schedule_result ) {
				pb_backupbuddy::status( 'details', 'Live Periodic chunk step cron event for step `' . $step . '` scheduled.' );
			} else {
				pb_backupbuddy::status( 'error', 'Next Live Periodic chunk step cron event for step `' . $step . '` FAILED to be scheduled.' );
			}
			if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
				pb_backupbuddy::status( 'details', 'Spawning cron now.' );
				update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
				spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
			}
		} else { // Already running. Trigger to restart at beginning.
			//set_transient( 'backupbuddy_live_jump', array( $step, $args ), 60*60*48 ); // Tells Live process to restart from the beginning (if mid-process) so new settigns apply.
		}

	} // End queue_step().



	public static function update_db_live_activity_time() {
		$activity_time_file = backupbuddy_core::getLogDirectory() . 'live/db_activity-' . pb_backupbuddy::$options['log_serial'] . '.txt';
		@touch( $activity_time_file );
	} // End update_db_live_activity_time().

	public static function get_db_live_activity_time() {
		$activity_time_file = backupbuddy_core::getLogDirectory() . 'live/db_activity-' . pb_backupbuddy::$options['log_serial'] . '.txt';
		if ( ! file_exists( $activity_time_file ) ) {
			return -1;
		}
		if ( false === ( $mtime = @filemtime( $activity_time_file ) ) ) {
			return -1;
		}
		return $mtime;
	}


	/* calculateTables()
	 *
	 * Calculates array of tables Live should back up based on Live additional inclusions/exclusions and global defaults.
	 *
	 */
	public static function calculateTables() {

		$results = self::_calculate_table_includes_excludes_basedump();

		// Calculate overall tables which is based on base mode, additional global excludes, additional global includes, and Live-specific excludes.
		$tables = backupbuddy_core::calculate_tables( $results[2], $results[0], $results[1] );

		return $tables;
	} // End calculateTables().


	public static function _calculate_table_includes_excludes_basedump() {

		$profile = pb_backupbuddy::$options['profiles'][0];
		global $wpdb;
		$prefix = $wpdb->prefix;

		if ( '1' == $profile['backup_nonwp_tables'] ) { // Backup all tables.
			$base_dump_mode = 'all';
		} elseif ( '2' == $profile['backup_nonwp_tables'] ) { // Backup no tables by default. Relies on listed additional tables.
			$base_dump_mode = 'none';
		} else { // Only backup matching prefix.
			$base_dump_mode = 'prefix';
		}

		// Calculate Live-specific excludes.
		$live_excludes = self::getOption( 'table_excludes', $makeArray = true );
		foreach( $live_excludes as &$live_exclude ) {
			$live_exclude = str_replace( '{prefix}', $prefix, $live_exclude ); // Populate prefix variable.
		}
		pb_backupbuddy::status( 'details', 'Live-specific tables to exclude: `' . implode( ', ', $live_excludes ) . '`.' );

		// Merge Live-specific excludes with BB global default excludes.
		$excludes = array_merge( $live_excludes, backupbuddy_core::get_mysqldump_additional( 'excludes', pb_backupbuddy::$options['profiles'][0] ) );

		$includes = backupbuddy_core::get_mysqldump_additional( 'includes', pb_backupbuddy::$options['profiles'][0] );

		return array( $includes, $excludes, $base_dump_mode );

	} // End _calculate_table_includes_excludes().


	/* getLiveDatabaseSnapshotDir()
	 *
	 * Has trailing slash.
	 *
	 */
	public static function getLiveDatabaseSnapshotDir() {
		return backupbuddy_core::getTempDirectory() . pb_backupbuddy::$options['log_serial'] . '/live_db_snapshot/';
	}



	/* getOption()
	 *
	 * description
	 *
	 */
	public static function getOption( $option, $makeArray = false ) {
		if ( true !== self::_setLiveID() ) {
			if ( true == $makeArray ) {
				return array();
			} else {
				return '';
			}
		}

		if ( ! isset( pb_backupbuddy::$options['remote_destinations'][ self::$_liveDestinationID ][ $option ] ) ) {
			if ( true == $makeArray ) {
				return array();
			} else {
				return '';
			}
		}

		$optionValue = pb_backupbuddy::$options['remote_destinations'][ self::$_liveDestinationID ][ $option ];

		if ( true === $makeArray ) {
			$optionValue = explode( "\n", $optionValue );
			$optionValue = array_map( 'trim', $optionValue );
			return array_filter( $optionValue ); // Removes empty lines.
		} else {
			return $optionValue;
		}
	} // End getOption().



	public static function pretty_function( $function ) {
		$user_label = '';
		if ( ! empty( wp_get_current_user()->user_firstname ) ) {
			$user_label = wp_get_current_user()->user_firstname;
		} else {
			$user_label = wp_get_current_user()->user_login;
		}
		$functions = array(
			'daily_init' => array(
				__( 'Up to date. Watching for changes...', 'it-l10n-backupbuddy' ),
				__( 'Up to date. Keeping an eye on that awesome new blog post...', 'it-l10n-backupbuddy' ),
				__( 'Up to date. We are all ears, waiting for your next move...', 'it-l10n-backupbuddy' ),
				__( 'Up to date. Eating popcorn and keeping movies safe from your Media Gallery...', 'it-l10n-backupbuddy' ),
				sprintf( __( 'Up to date. Your move, %s...', 'it-l10n-backupbuddy' ), $user_label )
			),
			'database_snapshot' => __( 'Capturing entire database', 'it-l10n-backupbuddy' ),
			'send_pending_db_snapshots' => __( 'Sending captured database files', 'it-l10n-backupbuddy' ),
			'process_table_deletions' => __( 'Processing deleted tables', 'it-l10n-backupbuddy' ),
			'update_files_list' => __( 'Scanning for new or deleted files', 'it-l10n-backupbuddy' ),
			'update_files_signatures' => __( 'Scanning for file changes', 'it-l10n-backupbuddy' ),
			'process_file_deletions' => __( 'Processing deleted files', 'it-l10n-backupbuddy' ),
			'send_pending_files' => __( 'Sending new & modified files... This may take a while', 'it-l10n-backupbuddy' ),
			'audit_remote_files' => __( 'Auditing backed up files for integrity', 'it-l10n-backupbuddy' ),
			'run_remote_snapshot' => __( 'Creating snapshot (if due)', 'it-l10n-backupbuddy' ),
			'wait_on_transfers' => __( 'Waiting for pending file transfers to finish', 'it-l10n-backupbuddy' ),
		);
		if ( isset( $functions[ $function ] ) ) {
			if ( is_array( $functions [ $function ] ) ) {
				return $functions[ $function][ array_rand( $functions[ $function ] )];
			} else {
				return $functions[ $function ];
			}
		} else {
			return __( 'Unknown', 'it-l10n-backupbuddy' );
		}
	} // End pretty_function().



	/* _setLiveID()
	 *
	 * description
	 *
	 */
	private static function _setLiveID() {
		if ( '' == self::$_liveDestinationID ) {
			foreach( pb_backupbuddy::$options['remote_destinations'] as $destination_id => $destination ) {
				if ( 'live' == $destination['type'] ) {
					self::$_liveDestinationID = $destination_id;
					return true;
				}
			}
			if ( '' == self::$_liveDestinationID ) {
				//pb_backupbuddy::status( 'error', 'Warning #382938932: No Live destination was found configured. Set up BackupBuddy Stash Live.' );
				return false;
			}
		}
		return true;
	} // End _setLiveID().



	/* getLiveID()
	 *
	 * Returns ID of remote destination or FALSE if not found.
	 *
	 */
	public static function getLiveID() {
		if ( '' == self::$_liveDestinationID ) {
			if ( false === self::_setLiveID() ) {
				return false;
			}
		}

		return self::$_liveDestinationID;
	}


	// TODO: $delete param only temporarily needed for server-side transition to new api server based archive trimming.
	public static function get_archive_limit_settings_array( $delete = true ) {
		$destination_id = backupbuddy_live::getLiveID();
		$destination_settings = backupbuddy_live_periodic::get_destination_settings();

		$archive_types = array(
			'db',
			'full',
			'plugins',
			'themes',
		);

		$archive_periods = array(
			'daily',
			'weekly',
			'monthly',
			'yearly',
		);

		$limits = array();
		foreach( $archive_types as $archive_type ) {
			$limits[ $archive_type ] = array();
			foreach( $archive_periods as $archive_period ) {
				if ( '' == $destination_settings[ 'limit_' . $archive_type . '_' . $archive_period ] ) { // For blank values, omit key since it is NOT being limited (unlimited of the type/period combo).
					continue;
				}
				$limits[ $archive_type ][ $archive_period ] = $destination_settings[ 'limit_' . $archive_type . '_' . $archive_period ];
			}
		}

		$return = array(
			'limits' => $limits,
		);

		if ( true === $delete ) {
			$return['delete'] = true; // Whether to actually delete or just dry-run.
		}

		return $return;
	}


	public static function send_trim_settings() {
		require_once( pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php' );

		$additionalParams = self::get_archive_limit_settings_array();
		$destination_settings = backupbuddy_live_periodic::get_destination_settings();

		require_once( pb_backupbuddy::plugin_path() . '/destinations/live/init.php' );
		$response = pb_backupbuddy_destination_live::stashAPI( $destination_settings, 'tmtrim-settings', $additionalParams );
		if ( ! is_array( $response ) ) {
			$error = 'Error #96431277: Error sending settings for trimming archives. Details: `' . $response . '`.';
			pb_backupbuddy::status( 'error', $error );
			return false;
		} else {
			pb_backupbuddy::status( 'details', 'Send trimmed remotely stored backup archive settings sent. Results: `' . print_r( $response, true ) . '`.' );
			return true;
		}

	} // End trim_remote_archives().



	// Deprecated as of 7.0.5.5 pending verified of new system.
	public static function trim_remote_archives( $echo = false ) {
		require_once( pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php' );

		$destination_id = backupbuddy_live::getLiveID();
		$destination_settings = backupbuddy_live_periodic::get_destination_settings();

		$archive_types = array(
			'db',
			'full',
			'plugins',
			'themes',
		);

		$archive_periods = array(
			'daily',
			'weekly',
			'monthly',
			'yearly',
		);

		$limits = array();
		foreach( $archive_types as $archive_type ) {
			$limits[ $archive_type ] = array();
			foreach( $archive_periods as $archive_period ) {
				if ( '' == $destination_settings[ 'limit_' . $archive_type . '_' . $archive_period ] ) { // For blank values, omit key since it is NOT being limited (unlimited of the type/period combo).
					continue;
				}
				$limits[ $archive_type ][ $archive_period ] = $destination_settings[ 'limit_' . $archive_type . '_' . $archive_period ];
			}
		}


		$additionalParams = array(
			'delete'  => true, // Whether to actually delete or just dry-run.
			'limits' => $limits,
		);

		require_once( pb_backupbuddy::plugin_path() . '/destinations/live/init.php' );
		$response = pb_backupbuddy_destination_live::stashAPI( $destination_settings, 'tmtrim', $additionalParams );
		if ( ! is_array( $response ) ) {
			$error = 'Error #96431277: Error trimming archives. Details: `' . $response . '`.';
			pb_backupbuddy::status( 'error', $error );

			if ( true === $echo ) {
				echo 'Archive trim error details:<pre>';
				print_r( $response );
				echo '</pre>';
			}

			return false;
		} else {
			pb_backupbuddy::status( 'details', 'Trimmed remotely stored backup archives. Results: `' . print_r( $response, true ) . '`.' );

			if ( true === $echo ) {
				echo 'NOTE: Type/period combinations where the value is left blank indicate no limiting (unlimited backup storage of this type) and are omitted from being sent in the limit list.<br><br>';
				echo 'Archive trim success response:<pre>';
				print_r( $response );
				echo '</pre>';
			}

			return true;
		}

	} // End trim_remote_archives().



} // end class backupbuddy_live.
