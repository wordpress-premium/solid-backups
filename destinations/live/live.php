<?php
/**
 * Solid Backups Stash Live Parent Class
 *
 * @package BackupBuddy
 * @author Dustin Bolton
 * @since 7.0
 */

/**
 * Solid Backups Live Class
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
	 * Action Scheduler Group.
	 *
	 * @var string
	 */
	const CRON_GROUP = 'solid-backups-live';

	/**
	 * Live Destination ID
	 *
	 * @var int
	 */
	private static $_liveDestinationID = '';

	/**
	 * Retrieve quota information for associated Stash account.
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
			require_once( pb_backupbuddy::plugin_path() . '/destinations/stash3/init.php' );

			$quota = call_user_func_array( array( 'pb_backupbuddy_destination_stash3', 'get_quota' ), array( $settings ) );
			if ( false === $quota ) {
				pb_backupbuddy::status( 'error', 'Error #3489348944: Could not get quota for Stash Live.' );
			}

			set_transient( self::STASH_QUOTA_TRANSIENT_NAME, $quota, self::STASH_QUOTA_TRANSIENT_EXPIRE );

			return $quota;
		}
	}

	/**
	 * Queues a directory for file and signature scanning. eg: Used by media upload to look
	 * for new files (including thumbnails, etc) for an uploaded image.
	 *
	 * @param string  $directory  Directory to scan.
	 *                Trailing slash optional. Important: MUST be below/within the ABSPATH or we return false.
	 */
	public static function queue_manual_file_scan( $directory ) {
		require_once( 'live_periodic.php' );

		// If directory within abspath?
		if ( ABSPATH != substr( $directory, 0, strlen( ABSPATH ) ) ) {
			pb_backupbuddy::status( 'warning', 'Warning #438943834: Queued filescan directory `' . $directory . '` not found within ABSPATH. Skipping.' );
			return false;
		}

		backupbuddy_live_periodic::queue_step( 'update_files_list', array( $directory ) );
	}



	/**
	 * Update the Live Activity Time for the DB.
	 */
	public static function update_db_live_activity_time() {
		$activity_time_file = backupbuddy_core::getLogDirectory() . 'live/db_activity-' . pb_backupbuddy::$options['log_serial'] . '.txt';
		@touch( $activity_time_file );
	}

	/**
	 * Get the Live Activity Time for the DB.
	 *
	 * @return int  Last activity time.
	 */
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

	/**
	 * Calculate array of tables Live should back up based on
	 * Live additional inclusions/exclusions and global defaults.
	 *
	 * @return array  Array of tables to back up.
	 */
	public static function calculateTables() {

		$results = self::_calculate_table_includes_excludes_basedump();

		// Calculate overall tables which is based on base mode, additional global excludes, additional global includes, and Live-specific excludes.
		$tables = backupbuddy_core::calculate_tables( $results[2], $results[0], $results[1] );

		return $tables;
	}

	/**
	 * Caclulate table includes/excludes based on Live-specific settings.
	 *
	 * @return array  Array of tables
	 */
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

	}

	/**
	 * Get the Live database snapshot directory.
	 *
	 * Has trailing slash.
	 *
	 * @return string  Live database snapshot directory.
	 */
	public static function getLiveDatabaseSnapshotDir() {
		return backupbuddy_core::getTempDirectory() . pb_backupbuddy::$options['log_serial'] . '/live_db_snapshot/';
	}

	/* Get a Live Option.
	 *
	 * @param string  $option    Option to get.
	 * @param bool    $makeArray  Whether to return as array (true) or string (false).
	 *
	 * @return mixed  Option value.
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
	}

	/**
	 * Get the human-redable name of a Live function.
	 *
	 * @param string  $function  Function name.
	 *
	 * @return string  Human-readable function name.
	 */
	public static function pretty_function( $function ) {
		$functions = array(
			'daily_init'                => __( 'Up to date. Watching for changes...', 'it-l10n-backupbuddy' ),
			'database_snapshot'         => __( 'Capturing entire database', 'it-l10n-backupbuddy' ),
			'send_pending_db_snapshots' => __( 'Sending captured database files', 'it-l10n-backupbuddy' ),
			'process_table_deletions'   => __( 'Processing deleted tables', 'it-l10n-backupbuddy' ),
			'update_files_list'         => __( 'Scanning for new or deleted files', 'it-l10n-backupbuddy' ),
			'update_files_signatures'   => __( 'Scanning for file changes', 'it-l10n-backupbuddy' ),
			'process_file_deletions'    => __( 'Processing deleted files', 'it-l10n-backupbuddy' ),
			'send_pending_files'        => __( 'Sending new & modified files...', 'it-l10n-backupbuddy' ),
			'audit_remote_files'        => __( 'Auditing backed up files for integrity', 'it-l10n-backupbuddy' ),
			'run_remote_snapshot'       => __( 'Creating snapshot (if due)', 'it-l10n-backupbuddy' ),
			'wait_on_transfers'         => __( 'Waiting for pending file transfers to finish', 'it-l10n-backupbuddy' ),
		);

		if ( isset( $functions[ $function ] ) ) {
			return $functions[ $function ];
		}

		return __( 'Unknown', 'it-l10n-backupbuddy' );
	}

	/* *
	 * Set teh Live Destination ID.
	 *
	 * @return bool  Whether Live ID was set.
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
				return false;
			}
		}
		return true;
	}

	/**
	 * Get the Live destination ID.
	 *
	 * Returns ID of remote destination or FALSE if not found.
	 *
	 * @return mixed  Live destination ID or false if not found.
	 */
	public static function getLiveID() {
		if ( '' == self::$_liveDestinationID ) {
			if ( false === self::_setLiveID() ) {
				return false;
			}
		}

		return self::$_liveDestinationID;
	}

	/**
	 * Get the archive limit settings array.
	 *
	 * @todo $delete param only temporarily needed for server-side transition to new
	 *       api server based archive trimming.
	 *
	 * @param bool $delete  Whether to actually delete or just dry-run.
	 *
	 * @return array  Array of archive limit settings.
	 */
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


	/**
	 * Send archive limit settings to remote server.
	 *
	 * @return bool  Whether settings were sent.
	 */
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

	}

	/**
	 * Trim remote archives.
	 *
	 * Deprecated as of 7.0.5.5 pending verified of new system.
	 *
	 * @deprecated
	 *
	 * @param bool $echo  Whether to echo results.
	 *
	 * @return bool  Whether archives were trimmed.
	 */
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
	}

	/**
	 * Using a DB query, remove all live-related actions from the action scheduler table.
	 *
	 * This is separate from live_periodic. It is only used on actions in the 'live' group.
	 *
	 * This removes *everything* (completed, failed, pending, etc).
	 */
	public static function remove_all_live_actions() {
		backupbuddy_core::delete_all_events_by_group( self::CRON_GROUP );
	}
}
