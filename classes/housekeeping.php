<?php
/**
 * Various housekeeping and cleanup methods for keeping things tidy.
 * Periodic housekeeping can be ran via run() function.
 *
 * @package BackupBuddy
 */

/**
 * Housekeeping Class
 *
 * @author Dustin Bolton
 * @since 6.4.0.12
 * @date Nov 2, 2015
 */
class backupbuddy_housekeeping {

	/**
	 * Periodic housekeeping cleanup function to clean up after BackupBuddy. Clean up orphaned files, data structure,
	 * old log files, etc. Also verifies anti-directory browsing files exist in expected locations and any potential
	 * problems are handled.
	 *
	 * @param int  $backup_age_limit  Maximum age (in seconds) to allow logs or other transient files/structures to exist after no longer needed. Default: 172800 (48 hours).
	 * @param bool $die_on_fail       Whether or not to die fatally if something goes wrong (such as unable to make anti-directory browsing files).
	 */
	public static function run_periodic( $backup_age_limit = 172800, $die_on_fail = true ) {
		if ( is_multisite() ) { // For Multisite only run on main Network site.
			if ( ! is_main_site() ) {
				return;
			}
		}

		pb_backupbuddy::status( 'message', 'Starting periodic housekeeeping procedure for BackupBuddy v' . pb_backupbuddy::settings( 'version' ) . '.' );
		require_once pb_backupbuddy::plugin_path() . '/classes/core.php';
		require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
		if ( ! isset( pb_backupbuddy::$options ) ) {
			pb_backupbuddy::load();
		}

		// Top priority. Security related or otherwise crucial to run first.
		self::no_recent_backup_reminder();
		$skip_temp_generation = true;
		backupbuddy_core::verify_directories( $skip_temp_generation ); // Verify directory existance and anti-directory browsing is in place everywhere.
		self::check_high_security_mode( $die_on_fail );
		self::remove_importbuddy_file();
		self::remove_importbuddy_dir();
		self::remove_dat_file_from_root();
		self::remove_rollback_files();
		self::remove_old_fileoptions_locks();

		// Potential large file/dir cleanup. May bog down site.
		self::cleanup_temp_dir( $backup_age_limit );
		self::remove_temp_zip_dirs( $backup_age_limit );

		// Robustness -- handle re-processing timeouts, verifying schedules, etc.
		self::process_timed_out_backups();
		self::process_timed_out_sends();
		self::validate_bb_schedules_in_wp();

		// Backup settings to file to help prevent loss of settings due to database problems. pb_backupbuddy::load() will look for this file if default settings are faulty/missing.
		self::backup_bb_settings();

		// More minor cleanup.
		self::remove_temp_tables( '', $backup_age_limit );
		self::remove_wp_schedules_with_no_bb_schedule();
		self::trim_old_notifications();
		self::trim_remote_send_stats();
		self::s3_cancel_multipart_pieces();
		self::s32_cancel_multipart_pieces();
		self::cleanup_local_destination_temp();
		self::purge_logs();
		self::purge_large_logs();
		self::clear_cron_send();
		self::remove_archive_primer_file();

		self::cleanup_transients( false ); // Don't purge unexpiring transients.

		// PHP tests.
		self::schedule_php_runtime_tests();
		self::schedule_php_memory_tests();

		@clearstatcache(); // Clears file info stat cache.
		pb_backupbuddy::status( 'message', 'Finished periodic housekeeping cleanup procedure.' );
	} // End run_periodic().

	/**
	 * No Recent Backup Reminder
	 *
	 * @return mixed  Returns true if destination type is 'live', otherwise returns null.
	 */
	public static function no_recent_backup_reminder() {

		// If Live enabled, see if too long since the last Snapshot.
		foreach ( pb_backupbuddy::$options['remote_destinations'] as $destination ) { // Look for Live destination.
			if ( 'live' === $destination['type'] ) {

				require_once pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php';
				$state = backupbuddy_live_periodic::get_stats();

				if ( $state['stats']['last_db_snapshot'] > 0 ) { // At least one Snapshot has been made.
					$time_since_last = time() - $state['stats']['last_db_snapshot'];
					$days_since_last = round( $time_since_last / 60 / 60 / 24 );

					// Run troubleshooting test if no snapshot in X days.
					if ( $days_since_last > backupbuddy_constants::DAYS_BEFORE_RUNNING_TROUBLESHOOTING_TEST ) {
						if ( false === wp_next_scheduled( 'backupbuddy_cron', array( 'live_troubleshooting_check', array() ) ) ) { // if schedule does not exist...
							backupbuddy_core::schedule_single_event( time(), 'live_troubleshooting_check', array() ); // Add schedule.
						}
					}

					if ( ( (int) $destination['no_new_snapshots_error_days'] > 0 ) && ( $days_since_last > (int) $destination['no_new_snapshots_error_days'] ) ) {
						$message = 'Warning! BackupBuddy is configured to notify you if no new BackupBuddy Stash Live Snapshots have been made in `' . (int) $destination['no_new_snapshots_error_days'] . '` days. It has been `' . $days_since_last . '` days since your last Snapshot. There may be a problem with your site\'s Stash Live setup requiring your attention.';
						pb_backupbuddy::status( 'warning', $message );
						if ( (int) $destination['no_new_snapshots_error_days'] > 0 ) { // Live destination and notifications are enabled.
							backupbuddy_core::mail_error( $message );
						}
					}
				} elseif ( $state['stats']['first_activity'] > 0 ) { // Live was set up but never even made a first Snapshot. Activate if DOUBLE the no_new_snapshot_error_days is surpassed (to give time for first backup to fully upload).
					$time_since_last = time() - $state['stats']['first_activity'];
					$days_since_last = round( $time_since_last / 60 / 60 / 24 );

					// Run troubleshooting test if no snapshot in X days.
					if ( $days_since_last > backupbuddy_constants::DAYS_BEFORE_RUNNING_TROUBLESHOOTING_TEST ) {
						if ( false === wp_next_scheduled( 'backupbuddy_cron', array( 'live_troubleshooting_check', array() ) ) ) { // if schedule does not exist...
							backupbuddy_core::schedule_single_event( time(), 'live_troubleshooting_check', array() ); // Add schedule.
						}
					}

					if ( ( (int) $destination['no_new_snapshots_error_days'] > 0 ) && ( $days_since_last > ( (int) $destination['no_new_snapshots_error_days'] * 2 ) ) ) {
						$message = 'Warning! BackupBuddy is configured to notify you if no new BackupBuddy Stash Live Snapshots have been made in `' . (int) $destination['no_new_snapshots_error_days'] . '` days. It has been at least twice this (`' . $days_since_last . '` days) since you set up BackupBuddy Stash Live but the first Snapshot has not been made yet. There may be a problem with your site\'s Stash Live setup requiring your attention.';
						pb_backupbuddy::status( 'warning', $message );
						if ( (int) $destination['no_new_snapshots_error_days'] > 0 ) { // Live destination and notifications are enabled.
							backupbuddy_core::mail_error( $message );
						}
					}
				}

				return true; // Don't send any traditional notifications when Live enabled.

			} // end if live.
		}

		// Alert user if no new backups FINISHED within X number of days if enabled. Max 1 email notification per 24 hours period.
		if ( is_main_site() ) { // Only run for main site or standalone.
			if ( pb_backupbuddy::$options['no_new_backups_error_days'] > 0 ) {
				if ( pb_backupbuddy::$options['last_backup_finish'] > 0 ) {
					$time_since_last = time() - pb_backupbuddy::$options['last_backup_finish'];
					$days_since_last = round( $time_since_last / 60 / 60 / 24 );
					if ( $days_since_last > pb_backupbuddy::$options['no_new_backups_error_days'] ) {

						$last_sent = get_transient( 'pb_backupbuddy_no_new_backup_error' );
						if ( false === $last_sent ) {
							$last_sent = time();
							set_transient( 'pb_backupbuddy_no_new_backup_error', $last_sent, ( 60 * 60 * 24 ) );
						}
						if ( ( time() - $last_sent ) > ( 60 * 60 * 24 ) ) { // 24hrs+ elapsed since last email sent.
							$message = 'Warning! BackupBuddy is configured to notify you if no new backups have completed in `' . pb_backupbuddy::$options['no_new_backups_error_days'] . '` days. It has been `' . $days_since_last . '` days since your last completed backup.';
							pb_backupbuddy::status( 'warning', $message );
							backupbuddy_core::mail_error( $message );
						}
					}
				}
			}
		}
	}

	/**
	 * Remove Rollback Files
	 */
	public static function remove_rollback_files() {
		// Clean up any old rollback undo files hanging around.
		$files = (array) glob( ABSPATH . 'backupbuddy_rollback*' );
		foreach ( $files as $file ) {
			$file_stats = stat( $file );
			if ( ( time() - $file_stats['mtime'] ) > backupbuddy_constants::CLEANUP_MAX_STATUS_LOG_AGE ) {
				@unlink( $file );
			}
		}
	}

	/**
	 * Remove Old Fileoptions Locks
	 *
	 * @param int $max_age  Defaults to CLEANUP_MAX_FILEOPTIONS_LOCK_AGE.
	 */
	public static function remove_old_fileoptions_locks( $max_age = '' ) {

		$removed_count   = 0;
		$candidate_files = array();

		// Make sure we have a valid maximum age otherwise use default.
		if ( '' === $max_age || ! is_int( $max_age ) ) {
			$max_age = backupbuddy_constants::CLEANUP_MAX_FILEOPTIONS_LOCK_AGE;
		}
		pb_backupbuddy::status( 'details', 'Cleaning up stale fileoptions lock files.' );

		// Get a list of lock files we can check for staleness.
		$files = (array) glob( backupbuddy_core::getLogDirectory() . 'fileoptions/*.lock' );

		// If a list of files then process otherwise if empty list (no files) or false (error) do nothing.
		if ( ! empty( $files ) ) {

			// We have some lock files, find candidates for removing.
			pb_backupbuddy::status( 'details', 'Cleaning up stale fileoptions lock files - found ' . count( $files ) . ' lock files.' );
			foreach ( $files as $file ) {

				$file_stats = stat( $file );
				if ( ( time() - $file_stats['mtime'] ) > $max_age ) {

					$candidate_files[] = $file;

				}
			}

			if ( ! empty( $candidate_files ) ) {

				pb_backupbuddy::status( 'details', 'Cleaning up stale fileoptions lock files - found ' . count( $candidate_files ) . ' candidate lock files older than ' . $max_age . ' seconds.' );

				foreach ( $candidate_files as $file ) {

					if ( @unlink( $file ) ) {

						++$removed_count;

					}
				}

				pb_backupbuddy::status( 'details', 'Cleaning up stale fileoptions lock files - cleaned up ' . $removed_count . ' of ' . count( $candidate_files ) . ' candidate lock files.' );

			} else {

				pb_backupbuddy::status( 'details', 'Cleaning up stale fileoptions lock files - found no candidate stale lock files older than ' . $max_age . ' seconds.' );

			}
		} else {

			pb_backupbuddy::status( 'details', 'Cleaning up stale fileoptions lock files - found no lock files.' );

		}

	}


	/**
	 * Clear cronSend
	 */
	public static function clear_cron_send() {
		// Clean up any old cron file transfer locks.
		$files = (array) glob( backupbuddy_core::getLogDirectory() . 'cronSend-*' );
		foreach ( $files as $file ) {
			$file_stats = stat( $file );
			if ( ( time() - $file_stats['mtime'] ) > backupbuddy_constants::CLEANUP_MAX_STATUS_LOG_AGE ) {
				@unlink( $file );
			}
		}
	}

	/**
	 * Purse Logs
	 *
	 * @param string $file_prefix  Log Prefix.
	 * @param string $limit        Defaults to CLEANUP_MAX_STATUS_LOG_COUNT.
	 * @param string $max_age      Defaults to CLEANUP_MAX_STATUS_LOG_AGE.
	 *
	 * @return mixed  Null if limit not yet met.
	 */
	public static function purge_logs( $file_prefix = 'status-', $limit = '', $max_age = '' ) {
		pb_backupbuddy::status( 'details', 'Cleaning up old logs.' );
		if ( '' === $limit ) {
			$limit = backupbuddy_constants::CLEANUP_MAX_STATUS_LOG_COUNT;
		}
		if ( '' === $max_age ) {
			$max_age = backupbuddy_constants::CLEANUP_MAX_STATUS_LOG_AGE;
		}

		// Gets files, newest first.
		$mode  = 'mtime';
		$files = pb_backupbuddy::$filesystem->glob_by_date( backupbuddy_core::getLogDirectory() . $file_prefix . '*.txt', $mode );
		if ( ! is_array( $files ) ) {
			$files = array();
		}
		// Return if limit not yet met.
		if ( count( $files ) <= $limit ) {
			return;
		}

		$i = 0;
		foreach ( $files as $time => $file ) {
			$i++;
			if ( $i > $limit ) { // Outside limit. Delete.
				if ( false === @unlink( $file ) ) {
					pb_backupbuddy::status( 'error', 'Unable to delete old log file `' . $file . '`. You may manually delete it. Check directory permissions for future cleanup.' );
				}
			} else { // Within limit. See if too old.
				if ( ( time() - $time ) > $max_age ) {
					if ( false === @unlink( $file ) ) {
						pb_backupbuddy::status( 'error', 'Unable to delete old log file `' . $file . '`. You may manually delete it. Check directory permissions for future cleanup.' );
					}
				}
			}
		}
	} // End purge_logs().

	/**
	 * Purge Large Logs
	 */
	public static function purge_large_logs() {
		$max_site_log_size          = pb_backupbuddy::$options['max_site_log_size'] * 1024 * 1024; // in bytes.
		$max_log_size_skip_truncate = 10; // If log file exceeds this site then simply unlink since it may be too large to trunace. In MB.

		// Purge site-wide log if over certain size.
		$files = glob( backupbuddy_core::getLogDirectory() . '*.txt' );
		if ( ! is_array( $files ) ) {
			$files = array();
		}

		foreach ( $files as $file ) {
			if ( ( $size = filesize( $file ) ) > $max_log_size_skip_truncate * 1024 * 1024 ) {
				pb_backupbuddy::status( 'warning', 'Warning #389349843: Log file `' . $file . '` was too large `' . $size . ' bytes` to truncate (max: `' . $max_log_size_skip_truncate . '`MB) so it was unlinked.' );
				if ( false === @unlink( $file ) ) {
					pb_backupbuddy::status( 'error', 'Error #438934843: Log file `' . $file . '` was too large `' . $size . ' bytes` to truncate (max: `' . $max_log_size_skip_truncate . '`MB) BUT it could not be unlinked/deleted! Manually delete this file.' );
				}
			} else {
				backupbuddy_core::truncate_file_beginning( $file, $max_site_log_size );
			}
		}
	} // End purge_large_logs().

	/**
	 * Handles trimming the number of remote sends to the most recent ones.
	 * Recent transfer logs are kept for TRIPLE the max age as they are important for troubleshooting.
	 *
	 * NOTE: Checks age first. Next max limit. Only deletes based on limit count if send is finished or failed.
	 *
	 * @param string $file_prefix  File Prefix.
	 * @param string $limit        Defaults to option:max_send_stats_count.
	 * @param string $max_age      Max age in days. Defaults to option:max_send_stats_days.
	 * @param bool   $purge_log    Purge Log.
	 *
	 * @return null  Returns if limit not yet met.
	 */
	public static function trim_remote_send_stats( $file_prefix = 'send-', $limit = '', $max_age = '', $purge_log = false ) {
		require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';

		pb_backupbuddy::status( 'details', 'Cleaning up remote send stats.' );
		if ( '' == $limit ) {
			$limit = (int) pb_backupbuddy::$options['max_send_stats_count']; // Maximum number of remote sends to keep track of.
		}
		if ( '' == $max_age ) {
			$max_age = (int) pb_backupbuddy::$options['max_send_stats_days'] * 60 * 60 * 24;
		}

		// Gets files, newest first.
		$mode             = 'mtime';
		$send_fileoptions = pb_backupbuddy::$filesystem->glob_by_date( backupbuddy_core::getLogDirectory() . 'fileoptions/' . $file_prefix . '*.txt', $mode );
		if ( ! is_array( $send_fileoptions ) ) {
			$send_fileoptions = array();
		}
		// Return if limit not yet met.
		if ( count( $send_fileoptions ) <= $limit ) {
			return;
		}

		$i = 0;
		foreach ( $send_fileoptions as $time => $send_fileoption ) {
			$i++;

			if ( ( time() - $time ) > $max_age ) {
				if ( false === @unlink( $send_fileoption ) ) {
					pb_backupbuddy::status( 'warning', 'Unable to delete old remote send fileoptions file `' . $send_fileoption . '`. You may manually delete it. Check directory permissions for future cleanup.' );
				} else { // Deleted.
					@unlink( str_replace( '.txt', '.lock', $send_fileoption ) ); // Remove lock file if exists.
				}
				$i--; // Deleted so remove from limit counter.
				continue;
			}

			if ( $i > $limit ) { // Outside limit. Delete only if finished OR failed.
				// Make sure file still exists before processing it.
				if ( ! file_exists( $send_fileoption ) ) {
					continue;
				}

				// Don't delete if unfinished and not failed.
				$read_only           = true;
				$send_fileoption_obj = new pb_backupbuddy_fileoptions( $send_fileoption, $read_only );
				if ( true !== ( $result = $send_fileoption_obj->is_ok() ) ) { // Could not open. Skip since we can't verify status.
					unset( $send_fileoption_obj );
					continue;
				} else {
					// Keep unfinished. Keep non-fails that are not too old.
					if ( ! isset( $send_fileoption_obj->options['finish_time'] ) || 0 == $send_fileoption_obj->options['finish_time'] || ( -1 != $send_fileoption_obj->options['finish_time'] && ( ( time() - $send_fileoption_obj->options['finish_time'] ) < backupbuddy_constants::CLEANUP_FINISHED_FILEOPTIONS_AGE_DELAY ) ) ) { // Still unfinished OR ( NOT Failed AND finished too recently to delete ).

						// TODO: (maybe).. If 0==finish_time then check the filemtime of the fileoptions file. If no progress in a certain amount of time, consider timed out?
						unset( $send_fileoption_obj );
						continue;
					}
				}

				// Made it here so must be finished or failed.
				if ( false === @unlink( $send_fileoption ) ) {
					pb_backupbuddy::status( 'warning', 'Unable to delete old remote send fileoptions file `' . $send_fileoption . '`. You may manually delete it. Check directory permissions for future cleanup.' );
				} else { // Deleted.
					@unlink( str_replace( '.txt', '.lock', $send_fileoption ) ); // Remove lock file if exists.
				}

				if ( true === $purge_log ) {
					$log_file = backupbuddy_core::getLogDirectory() . 'status-remote_send-' . $send_fileoption_obj->options['sendID'] . '_' . pb_backupbuddy::$options['log_serial'] . '.txt';
					@unlink( $log_file );
				}

				unset( $send_fileoption_obj );
			}
		}

		return;

	} // End trim_remote_send_stats().


	/**
	 * Cleans up expired or corrupted transients.
	 *
	 * @param bool $remove_unexpiring  When true unexpiring transients (value row exists but expiration row does NOT exist) will be purged. Useful if expiration row is missing resulting in infinite lasting transient(s). Default: false.
	 */
	public static function cleanup_transients( $remove_unexpiring = false ) {
		pb_backupbuddy::status( 'details', 'Cleaning up expired or corrupt transients.' );

		if ( true === $remove_unexpiring ) {
			pb_backupbuddy::status( 'details', 'Option selected to purge unexpiring transients.' );
		} else {
			pb_backupbuddy::status( 'details', 'Option NOT selected to purge unexpiring transients.' );
		}

		global $wpdb;
		$sql = 'SELECT `option_name` AS `name`, `option_value` AS `value`
			  FROM  `' . $wpdb->options . "`
			  WHERE `option_name` LIKE '%transient_%'
			  ORDER BY `option_name` LIMIT 1000"; // Currently capping at 1000 per cleanup to prevent using too much memory.

		$results    = $wpdb->get_results( $sql );
		$transients = array();
		foreach ( $results as $result ) {
			if ( 0 === strpos( $result->name, '_site_transient_' ) ) {
				if ( 0 === strpos( $result->name, '_site_transient_timeout_' ) ) {
					$transients[ str_replace( '_site_transient_timeout_', '', $result->name ) ]['expires'] = $result->value;
					$transients[ str_replace( '_site_transient_timeout_', '', $result->name ) ]['type']    = 'site';
				} else {
					$transients[ str_replace( '_site_transient_', '', $result->name ) ]['found'] = true;
					$transients[ str_replace( '_site_transient_', '', $result->name ) ]['type']  = 'site';
				}
			} else {
				if ( 0 === strpos( $result->name, '_transient_timeout_' ) ) {
					$transients[ str_replace( '_transient_timeout_', '', $result->name ) ]['expires'] = $result->value;
					$transients[ str_replace( '_transient_timeout_', '', $result->name ) ]['type']    = 'normal';
				} else {
					$transients[ str_replace( '_transient_', '', $result->name ) ]['found'] = true;
					$transients[ str_replace( '_transient_', '', $result->name ) ]['type']  = 'normal';
				}
			}
		}
		pb_backupbuddy::status( 'details', 'Found `' . count( $transients ) . '` transients.' );

		foreach ( $transients as $transient_name => $transient ) {
			// Check if we found a value.
			if ( isset( $transient['found'] ) && ( true === $transient['found'] ) ) { // Value was found.
				// Check if we found a paired expiration time. (NORMALLY we should find this. if not then something is wrong!).
				if ( ! isset( $transient['expires'] ) ) { // Either never expires or glitched missing expiration date.

					if ( true === $remove_unexpiring ) { // Only remove if specified. It's valid for a transient to be set to never expire so this should not be used regularly.

						// Delete transient. NOTE: No expiration entry exists so only need to remove value row here.
						if ( 'site' === $transient['type'] ) { // site.
							$sql = 'DELETE FROM `' . $wpdb->options . "` WHERE option_name='" . '_site_transient_' . $transient_name . "' LIMIT 1";
							$wpdb->query( $sql );

							pb_backupbuddy::status( 'details', 'SQL: `' . $sql . '`. Rows affected: `' . $wpdb->rows_affected . '`. (value found; no expire)' );
						} else { // normal.
							$sql = 'DELETE FROM `' . $wpdb->options . "` WHERE option_name='" . '_transient_' . $transient_name . "' LIMIT 1";
							$wpdb->query( $sql );

							pb_backupbuddy::status( 'details', 'SQL: `' . $sql . '`. Rows affected: `' . $wpdb->rows_affected . '`. (value found; no expire)' );
						}
					}
				} else { // Expiration found. See if it already expired. If so, delete it to clean up options table.

					if ( $transient['expires'] > time() ) { // Transient expired. Remove from WP to clean up table.
						// Delete transient. !!!!!! IMPORTANT: Expiration exists so we MUST delete both value row AND expiration row !!!!!!
						if ( 'site' === $transient['type'] ) { // site.
							$sql = 'DELETE FROM `' . $wpdb->options . "` WHERE option_name='" . '_site_transient_' . $transient_name . "' LIMIT 1";
							$wpdb->query( $sql );

							pb_backupbuddy::status( 'details', 'SQL: `' . $sql . '`. Rows affected: `' . $wpdb->rows_affected . '`. (value found; expires; PAST expiration)' );

							$sql = 'DELETE FROM `' . $wpdb->options . "` WHERE option_name='" . '_site_transient_timeout_' . $transient_name . "' LIMIT 1";
							$wpdb->query( $sql );

							pb_backupbuddy::status( 'details', 'SQL: `' . $sql . '`. Rows affected: `' . $wpdb->rows_affected . '`. (value found; expires; PAST expiration)' );

						} else { // normal.
							$sql = 'DELETE FROM `' . $wpdb->options . "` WHERE option_name='" . '_transient_' . $transient_name . "' LIMIT 1";
							$wpdb->query( $sql );

							pb_backupbuddy::status( 'details', 'SQL: `' . $sql . '`. Rows affected: `' . $wpdb->rows_affected . '`. (value found; expires; PAST expiration)' );

							$sql = 'DELETE FROM `' . $wpdb->options . "` WHERE option_name='" . '_transient_timeout_' . $transient_name . "' LIMIT 1";
							$wpdb->query( $sql );

							pb_backupbuddy::status( 'details', 'SQL: `' . $sql . '`. Rows affected: `' . $wpdb->rows_affected . '`. (value found; expires; PAST expiration)' );
						}
					}
				}
			} else { // No value found but expiration was. Should never happen. Delete expiration entry.

				// Delete transient. NOTE: No value entry exists so only need to remove expiration row here.
				if ( 'site' === $transient['type'] ) { // site.
					$sql = 'DELETE FROM `' . $wpdb->options . "` WHERE option_name='" . '_site_transient_timeout_' . $transient_name . "' LIMIT 1";
					$wpdb->query( $sql );

					pb_backupbuddy::status( 'details', 'SQL: `' . $sql . '`. Rows affected: `' . $wpdb->rows_affected . '`. (no value found)' );
				} else { // normal.
					$sql = 'DELETE FROM `' . $wpdb->options . "` WHERE option_name='" . '_transient_timeout_' . $transient_name . "' LIMIT 1";
					$wpdb->query( $sql );

					pb_backupbuddy::status( 'details', 'SQL: `' . $sql . '`. Rows affected: `' . $wpdb->rows_affected . '`. (no value found)' );
				}
			}
		}

		pb_backupbuddy::status( 'details', 'Completed transient cleanup.' );
	} // End cleanup_transients().


	/**
	 * Process Timed Out Backups
	 */
	public static function process_timed_out_backups() {
		require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';

		// Mark any backups noted as in progress to timed out if taking too long. Send error email is scheduled and failed or timed out.
		// Also, Purge fileoptions files without matching backup file in existance that are older than 30 days.
		pb_backupbuddy::status( 'details', 'Cleaning up old backup fileoptions option files.' );
		$fileoptions_directory = backupbuddy_core::getLogDirectory() . 'fileoptions/';
		$files                 = glob( $fileoptions_directory . '*.txt' );
		if ( ! is_array( $files ) ) {
			$files = array();
		}
		foreach ( $files as $file ) {
			pb_backupbuddy::status( 'details', 'Fileoptions instance #43.' );
			$read_only      = false;
			$backup_options = new pb_backupbuddy_fileoptions( $file, $read_only );
			$result         = $backup_options->is_ok();
			if ( true !== $result ) {
				pb_backupbuddy::status( 'error', 'Error retrieving fileoptions file `' . $file . '`. Err 335353266.' );
			} else {
				if ( isset( $backup_options->options['archive_file'] ) ) {
					if ( false === $backup_options->options['finish_time'] ) {
						// Failed & already handled sending notification.
					} elseif ( -1 == $backup_options->options['finish_time'] ) {
						// Cancelled manually.
					} elseif ( $backup_options->options['finish_time'] >= $backup_options->options['start_time'] && 0 != $backup_options->options['start_time'] ) {
						// Completed.
					} else { // Timed out or in progress.
						// TODO: Set Flag in $backup_options->options to track if timed out or in progress.
						$seconds_ago = time() - $backup_options->options['updated_time'];
						if ( $seconds_ago > backupbuddy_constants::TIME_BEFORE_CONSIDERED_TIMEOUT ) { // If 24hrs passed since last update to backup then mark this timeout as failed.

							if ( 'scheduled' === $backup_options->options['trigger'] ) { // SCHEDULED BACKUP TIMED OUT.
								// Determine the first step to not finish.
								$timeout_step = '';
								foreach ( $backup_options->options['steps'] as $step ) {
									if ( 0 == $step['finish_time'] ) {
										$timeout_step = $step['function'];
										break;
									}
								}

								$backup_check_spot = __( 'Select "View recently made backups" from the BackupBuddy Backups page to find this backup and view its log details and/or manually create a backup to test for problems.', 'it-l10n-backupbuddy' );
								$send_check_spot   = __( 'Select "View recently sent files" on the Destinations page to find this backup and view its log details and/or manually create a backup to test for problems.', 'it-l10n-backupbuddy' );

								$timeout_message = '';
								if ( '' != $timeout_step ) {
									if ( 'backup_create_database_dump' === $timeout_step ) {
										$timeout_message = 'The database dump step appears to have timed out. Make sure your database is not full of unwanted logs or clutter. ' . $backup_check_spot;
									} elseif ( 'backup_zip_files' === $timeout_step ) {
										$timeout_message = 'The zip archive creation step appears to have timed out. Try turning off zip compression to significantly speed up the process or exclude large files. ' . $backup_check_spot;
									} elseif ( 'send_remote_destination' === $timeout_step ) {
										$timeout_message = 'The remote transfer step appears to have timed out. Try turning on chunking in the destination settings to break up the file transfer into multiple steps. ' . $send_check_spot;
									} else {
										$timeout_message = 'The step function `' . $timeout_step . '` appears to have timed out. ' . $backup_check_spot;
									}
								}
								$error_message = 'Scheduled BackupBuddy backup `' . $backup_options->options['archive_file'] . '` started `' . pb_backupbuddy::$format->time_ago( $backup_options->options['start_time'] ) . '` ago likely timed out. ' . $timeout_message;

								pb_backupbuddy::status( 'error', $error_message );

								if ( $seconds_ago < backupbuddy_constants::CLEANUP_MAX_AGE_TO_NOTIFY_TIMEOUT ) { // Prevents very old timed out backups from triggering email send.
									backupbuddy_core::mail_error( $error_message );
								}
							} // end scheduled.

							$backup_options->options['finish_time'] = false; // Consider officially timed out.  If scheduled backup then an error notification will have been sent.
							$backup_options->save();
						}
					}

					// Remove fileoptions files which do not have a corresponding local backup. NOTE: This only handles fileoptions files containing the 'archive_file' key in their array. Handle those without this elsewhere.
					// Cached integrity scans performed when there was not an existing fileoptions file will be missing the archive_file key.
					$backup_dir = backupbuddy_core::getBackupDirectory();
					if ( ! file_exists( $backup_options->options['archive_file'] ) && ! file_exists( $backup_dir . basename( $backup_options->options['archive_file'] ) ) ) { // No corresponding backup ZIP file.
						$modified = filemtime( $file );
						if ( ( time() - $modified ) > backupbuddy_constants::MAX_SECONDS_TO_KEEP_ORPHANED_FILEOPTIONS_FILES ) { // Too many days old so delete.
							if ( false === unlink( $file ) ) {
								pb_backupbuddy::status( 'error', 'Unable to delete orphaned fileoptions file `' . $file . '`.' );
							}
							if ( file_exists( $file . '.lock' ) ) {
								@unlink( $file . '.lock' );
							}
						}
						// Do not delete orphaned fileoptions because it is not old enough. Recent backups page needs these to list the backup.
					}
					// Backup ZIP file exists.
				} else { // No archive_file key in fileoptions array.

					// Trim any fileoptions files that are orphaned (no matching backup zip file). Note that above we trim these files that have the archive_file key in them.
					$backup_dir   = backupbuddy_core::getBackupDirectory();
					$serial       = backupbuddy_core::get_serial_from_file( '-' . basename( $file ) ); // Dash needed to trick get_serial_from_file() into thinking this is a valid backup filename.
					$backup_files = glob( $backup_dir . '*' . $serial . '*.zip' ); // Try to find a matching backup zip with this serial in its filename.
					if ( ! is_array( $backup_files ) ) {
						$backup_files = array();
					}
					if ( 0 === count( $backup_files ) ) { // No corresponding backup. Delete file.
						$modified = filemtime( $file );
						if ( ( time() - $modified ) > backupbuddy_constants::MAX_SECONDS_TO_KEEP_ORPHANED_FILEOPTIONS_FILES ) { // Too many days old so delete.
							if ( false === unlink( $file ) ) {
								pb_backupbuddy::status( 'error', 'Unable to delete orphaned fileoptions file `' . $file . '`.' );
							}
							if ( file_exists( $file . '.lock' ) ) {
								@unlink( $file . '.lock' );
							}
						}
						// Do not delete orphaned fileoptions because it is not old enough. Recent backups page needs these to list the backup.
					}
				}
			}
		} // end foreach.
	}

	/**
	 * Process Timed Out Sends
	 */
	public static function process_timed_out_sends() {
		require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';

		// Mark any timed out remote sends as timed out. Attempt resend once.
		$remote_sends     = array();
		$send_fileoptions = pb_backupbuddy::$filesystem->glob_by_date( backupbuddy_core::getLogDirectory() . 'fileoptions/send-*.txt' );
		if ( ! is_array( $send_fileoptions ) ) {
			$send_fileoptions = array();
		}
		foreach ( $send_fileoptions as $send_fileoption ) {
			$send_id = str_replace( '.txt', '', str_replace( 'send-', '', basename( $send_fileoption ) ) );
			pb_backupbuddy::status( 'details', 'About to load fileoptions data.' );
			require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
			pb_backupbuddy::status( 'details', 'Fileoptions instance #23.' );
			$fileoptions_file = backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt';
			$read_only        = false;
			$ignore_lock      = false;
			$create_file      = false;
			$fileoptions_obj  = new pb_backupbuddy_fileoptions( $fileoptions_file, $read_only, $ignore_lock, $create_file );
			if ( true !== ( $result = $fileoptions_obj->is_ok() ) ) {
				pb_backupbuddy::status( 'error', __( 'Fatal Error #9034.3224442393. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
			}

			// Corrupt fileoptions file. Remove.
			if ( ! isset( $fileoptions_obj->options['start_time'] ) ) {
				unset( $fileoptions_obj );
				@unlink( $fileoptions_file );
				continue;
			}

			// Finish time not set. Shouldn't happen buuuuut.... skip.
			if ( ! isset( $fileoptions_obj->options['finish_time'] ) ) {
				continue;
			}

			// Don't do anything for success, failure, or already-marked as -1 finish time.
			if ( ( 'success' == $fileoptions_obj->options['status'] ) || ( 'failure' == $fileoptions_obj->options['status'] ) || ( -1 == $fileoptions_obj->options['finish_time'] ) ) {
				continue;
			}

			// Older format did not include updated_time.
			if ( ! isset( $fileoptions_obj->options['update_time'] ) ) {
				continue;
			}

			$seconds_ago = time() - $fileoptions_obj->options['update_time'];
			if ( $seconds_ago > backupbuddy_constants::TIME_BEFORE_CONSIDERED_TIMEOUT ) { // If 24hrs passed since last update to backup then mark this timeout as failed.

				// Potentially try to resend if not a live_periodic transfer.
				if ( 'live_periodic' != $fileoptions_obj->options['trigger'] ) {
					$is_resending = backupbuddy_core::remoteSendRetry( $fileoptions_obj, $send_id, pb_backupbuddy::$options['remote_send_timeout_retries'] );
					if ( true === $is_resending ) { // If resending then skip sending any error email just yet...
						continue;
					}

					if ( 'timeout' != $fileoptions_obj->options['status'] ) { // Do not send email if status is 'timeout' since either already sent or old-style status marking (pre-v6.0).
						// Calculate destination title and type for error email.
						$destination_title = '';
						$destination_type  = '';
						if ( isset( pb_backupbuddy::$options['remote_destinations'][ $fileoptions_obj->options['destination'] ] ) ) {
							$destination_title = pb_backupbuddy::$options['remote_destinations'][ $fileoptions_obj->options['destination'] ]['title'];
							$destination_type  = backupbuddy_core::pretty_destination_type( pb_backupbuddy::$options['remote_destinations'][ $fileoptions_obj->options['destination'] ]['type'] );
						}

						if ( is_array( $fileoptions_obj->options['file'] ) ) {
							$filename = '';
							foreach ( $fileoptions_obj->options['file'] as $file ) {
								$filename .= '; ' . basename( $file );
							}
						} else {
							$filename = basename( $fileoptions_obj->options['file'] );
						}
						$error_message = 'A remote destination send of file `' . $filename . '` started `' . pb_backupbuddy::$format->time_ago( $fileoptions_obj->options['start_time'] ) . '` ago sending to the destination titled `' . $destination_title . '` of type `' . $destination_type . '` likely timed out. BackupBuddy will attempt to retry this failed transfer ONCE. If the second attempt succeeds the failed attempt will be replaced in the recent sends list. Check the error log for further details and/or manually send a backup to test for problems.';
						pb_backupbuddy::status( 'error', $error_message );
						if ( $seconds_ago < backupbuddy_constants::CLEANUP_MAX_AGE_TO_NOTIFY_TIMEOUT ) { // Prevents very old timed out backups from triggering email send.
							backupbuddy_core::mail_error( $error_message );
						}
					}
				}

				// Save as timed out.
				$fileoptions_obj->options['status']      = 'timeout';
				$fileoptions_obj->options['finish_time'] = -1;
				$fileoptions_obj->save();

				// If live_periofic then just try to delete the file at this point.
				if ( 'live_periodic' == $fileoptions_obj->options['trigger'] ) {
					unset( $fileoptions_obj );
					@unlink( $fileoptions_file );
					continue;
				}
			}

			unset( $fileoptions_obj );
		}
	}

	/**
	 * Check High Security Mode
	 *
	 * @param bool $die_on_fail  Die on Fail. Default is false.
	 */
	public static function check_high_security_mode( $die_on_fail = false ) {
		// Handle high security mode archives directory .htaccess system. If high security backup directory mode: Make sure backup archives are NOT downloadable by default publicly. This is only lifted for ~8 seconds during a backup download for security. Overwrites any existing .htaccess in this location.
		if ( '1' === pb_backupbuddy::$options['lock_archives_directory'] ) { // High security mode. Make sure high security .htaccess in place.
			self::enable_high_security_mode();
		} else { // Normal security mode. Put normal .htaccess.
			self::disable_high_security_mode( $die_on_fail );
		}
	}

	/**
	 * Enable high security .htaccess file
	 */
	public static function enable_high_security_mode() {
		pb_backupbuddy::status( 'details', 'Adding .htaccess high security mode for backups directory.' );
		$server_ip = isset( $_SERVER['SERVER_ADDR'] ) ? filter_var( $_SERVER['SERVER_ADDR'], FILTER_VALIDATE_IP ) : '';
		$ips       = apply_filters( 'itbub_server_ip_address', $server_ip ? $server_ip : '' );
		if ( '127.0.0.1' !== $server_ip || '::1' !== $server_ip ) {
			$ips = trim( '127.0.0.1 ::1 ' . $ips );
		}
		$deny_external            = <<<DENY_EXTERNAL
<RequireAll>
Require ip {$ips}
</RequireAll>
DENY_EXTERNAL;
		$htaccess_creation_status = @file_put_contents( backupbuddy_core::getBackupDirectory() . '.htaccess', $deny_external );
		if ( false === $htaccess_creation_status ) {
			pb_backupbuddy::alert( 'Error #344894545. Security Warning! Unable to create security file (.htaccess) in backups archive directory. This file prevents unauthorized downloading of backups should someone be able to guess the backup location and filenames. This is unlikely but for best security should be in place. Please verify permissions on the backups directory.' );
		}
	}

	/**
	 * Disable High Security Mode, restore anti-directory browsing .htaccess
	 *
	 * @param bool $die_on_fail  Die on Fail. Default is false.
	 */
	public static function disable_high_security_mode( $die_on_fail = false ) {
		pb_backupbuddy::status( 'details', 'Removing .htaccess high security mode for backups directory. Normal mode .htaccess to be added next.' );
		// Remove high security .htaccess.
		if ( file_exists( backupbuddy_core::getBackupDirectory() . '.htaccess' ) ) {
			$unlink_status = @unlink( backupbuddy_core::getBackupDirectory() . '.htaccess' );
			if ( false === $unlink_status ) {
				pb_backupbuddy::alert( 'Error #844594. Unable to temporarily remove .htaccess security protection on archives directory to allow downloading. Please verify permissions of the BackupBuddy archives directory or manually download via FTP.' );
			}
		}

		// Place normal .htaccess.
		pb_backupbuddy::anti_directory_browsing( backupbuddy_core::getBackupDirectory(), $die_on_fail );
	}

	/**
	 * Get User's IP address.
	 *
	 * Pulled from ithemes-security-pro/core/lib.php ITSEC_Lib::get_ip()
	 *
	 * @return string $ip  IP address of user.
	 */
	public static function get_remote_ip() {
		$headers = array(
			'HTTP_CF_CONNECTING_IP', // CloudFlare.
			'HTTP_X_FORWARDED_FOR',  // Squid and most other forward and reverse proxies.
			'REMOTE_ADDR',           // Default source of remote IP.
		);

		$headers = apply_filters( 'itsec_filter_remote_addr_headers', apply_filters( 'itbub_filter_remote_addr_headers', $headers ) );

		$headers = (array) $headers;

		if ( ! in_array( 'REMOTE_ADDR', $headers ) ) {
			$headers[] = 'REMOTE_ADDR';
		}

		$ip = false;

		// Loop through twice. The first run won't accept a reserved or private range IP. If an acceptable IP is not
		// found, try again while accepting reserved or private range IPs.
		for ( $x = 0; $x < 2; $x++ ) {
			foreach ( $headers as $header ) {
				if ( empty( $_SERVER[ $header ] ) ) {
					continue;
				}

				$ip = trim( $_SERVER[ $header ] );

				if ( empty( $ip ) ) {
					continue;
				}

				$comma_index = strpos( $_SERVER[ $header ], ',' );
				if ( false !== $comma_index ) {
					$ip = substr( $ip, 0, $comma_index );
				}

				if ( 0 === $x ) {
					// First run through. Only accept an IP not in the reserved or private range.
					$ip = filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_NO_PRIV_RANGE );
				} else {
					$ip = filter_var( $ip, FILTER_VALIDATE_IP );
				}

				if ( ! empty( $ip ) ) {
					break;
				}
			}

			if ( ! empty( $ip ) ) {
				break;
			}
		}

		if ( empty( $ip ) ) {
			// If an IP is not found, force it to a localhost IP that would not be blacklisted as this typically
			// indicates a local request that does not provide the localhost IP.
			$ip = '127.0.0.1 ::1'; // IPv4 and IPv6 versions.
		}

		return $ip;
	}

	/**
	 * Open Backup Directory to current remote connection IP address(es).
	 */
	public static function allow_remote_connection() {
		$htaccess_path = backupbuddy_core::getBackupDirectory() . '.htaccess';

		if ( ! file_exists( $htaccess_path ) ) {
			return false;
		}

		$remote_ip = self::get_remote_ip();

		if ( ! empty( $remote_ip ) ) {
			$contents = @file( $htaccess_path );
			$new_htaccess = '';
			foreach ( $contents as $line ) {
				if ( ! preg_match( '/(\h*)(require(\h*)ip)\b(\h*)/i', $line ) ) {
					$new_htaccess .= $line . "\r\n";
					continue;
				}
				$new_line = $line;
				if ( false === strpos( $new_line, $remote_ip ) ) {
					$new_line .= ' ' . $remote_ip;
				}
				$new_htaccess .= $new_line . "\r\n";
			}
			$htaccess_modification_status = @file_put_contents( $htaccess_path, $new_htaccess );
			if ( false === $htaccess_modification_status ) {
				die( 'Error #344894545. Security Warning! Unable to create security file (.htaccess) in backups archive directory. This file prevents unauthorized downloading of backups should someone be able to guess the backup location and filenames. This is unlikely but for best security should be in place. Please verify permissions on the backups directory.' );
			}
			return true;
		} else {
			// Make directory less secure.
			self::disable_high_security_mode( true );
		}
	}

	/**
	 * Remove importbuddy.php file
	 */
	public static function remove_importbuddy_file() {
		// Remove any copy of importbuddy.php in root.
		$temp_dir = backupbuddy_core::getTempDirectory();
		pb_backupbuddy::status( 'details', 'Cleaning up any importbuddy scripts in site root if it exists & is not very recent.' );
		$importbuddy_files = glob( $temp_dir . 'importbuddy*.php' );
		if ( ! is_array( $importbuddy_files ) ) {
			$importbuddy_files = array();
		}
		foreach ( $importbuddy_files as $importbuddy_file ) {
			$modified = filemtime( $importbuddy_file );
			if ( ( false === $modified ) || ( time() > ( $modified + backupbuddy_constants::CLEANUP_MAX_IMPORTBUDDY_AGE ) ) ) { // If time modified unknown OR was modified long enough ago.
				pb_backupbuddy::status( 'details', 'Unlinked `' . basename( $importbuddy_file ) . '` in root of site.' );
				unlink( $importbuddy_file );
			} else {
				pb_backupbuddy::status( 'details', 'SKIPPED unlinking `' . basename( $importbuddy_file ) . '` in root of site as it is fresh and may still be in use.' );
			}
		}
	}

	/**
	 * Remove ImportBuddy Directory
	 */
	public static function remove_importbuddy_dir() {
		// Remove any copy of importbuddy directory in root.
		pb_backupbuddy::status( 'details', 'Cleaning up importbuddy directory in site root if it exists & is not very recent.' );
		if ( file_exists( ABSPATH . 'importbuddy/' ) ) {
			$modified = filemtime( ABSPATH . 'importbuddy/' );
			if ( false === $modified || time() > ( $modified + backupbuddy_constants::CLEANUP_MAX_IMPORTBUDDY_AGE ) ) { // If time modified unknown OR was modified long enough ago.
				pb_backupbuddy::status( 'details', 'Unlinked importbuddy directory recursively in root of site.' );
				pb_backupbuddy::$filesystem->unlink_recursive( ABSPATH . 'importbuddy/' );
			} else {
				pb_backupbuddy::status( 'details', 'SKIPPED unlinked importbuddy directory recursively in root of site as it is fresh and may still be in use.' );
			}
		}
	}

	/**
	 * Remove _dat File from Root directory.
	 */
	public static function remove_dat_file_from_root() {
		// Remove any copy of dat files in the site root.
		pb_backupbuddy::status( 'details', 'Cleaning up dat file if it exists in site root.' );
		if ( file_exists( ABSPATH . 'backupbuddy_dat.php' ) ) {
			if ( unlink( ABSPATH . 'backupbuddy_dat.php' ) ) {
				pb_backupbuddy::status( 'details', 'Unlinked backupbuddy_dat.php in root of site.' );
			} else {
				pb_backupbuddy::status( 'details', 'Unable to delete backupbuddy_dat.php in root of site. This file needs to be manually deleted' );
			}
		}
	}

	/**
	 * Remove Archiver Primer File
	 */
	public static function remove_archive_primer_file() {
		// Remove instance of .itbub archive primer file from root if not already cleaned after restore/migration.
		pb_backupbuddy::status( 'details', 'Cleaning up archive primer file if it exists in site root.' );
		if ( file_exists( ABSPATH . '.itbub' ) ) {
			if ( unlink( ABSPATH . '.itbub' ) ) {
				pb_backupbuddy::status( 'details', 'Unlinked archive primer file in root of site.' );
			} else {
				pb_backupbuddy::status( 'details', 'Unable to delete archive primer file (.itbub) in root of site. This file needs to be manually deleted' );
			}
		}
	}

	/**
	 * Cleans out any old temp files. Called by periodic cleanup function and post_backup in backup.php.
	 *
	 * @param int $backup_age_limit  Backup Age Limit in seconds. Default is 2 days.
	 */
	public static function cleanup_temp_dir( $backup_age_limit = 172800 ) {
		// Remove any old temporary directories in wp-content/uploads/backupbuddy_temp/. Logs any directories it cannot delete.
		$temp_directory = backupbuddy_core::getTempDirectory();
		pb_backupbuddy::status( 'details', 'Cleaning up any old temporary zip directories in: `' . $temp_directory . '`. If no recent backups then the temp directory will also be purged.' );
		$recent_backup_found = false;
		$files               = glob( $temp_directory . '*' );
		if ( is_array( $files ) && ! empty( $files ) ) { // For robustness. Without open_basedir the glob() function returns an empty array for no match. With open_basedir in effect the glob() function returns a boolean false for no match.
			foreach ( $files as $file ) {
				if ( ( strpos( $file, 'index.' ) !== false ) || ( strpos( $file, '.htaccess' ) !== false ) ) { // Index file or htaccess dont get deleted so go to next file.
					continue;
				}
				$file_stats = stat( $file );
				if ( ( 0 == $backup_age_limit ) || ( ( time() - $file_stats['mtime'] ) > $backup_age_limit ) ) { // If older than 12 hours, delete the log.
					if ( @pb_backupbuddy::$filesystem->unlink_recursive( $file ) === false ) {
						pb_backupbuddy::status( 'error', 'Unable to clean up (delete) temporary directory/file: `' . $file . '`. You should manually delete it or check permissions.' );
					}
				} else { // Not very old.
					$recent_backup_found = true;
				}
			}
			if ( false === $recent_backup_found ) {
				pb_backupbuddy::$filesystem->unlink_recursive( $temp_directory ); // Delete temp directory (as of BB v5.0). This is not critical but nice. The backup cleanup step should purge these so if all is going well this probably will not find anything.
			}
		}
		unset( $recent_backup_found );
	} // End cleanup_temp_dir().

	/**
	 * Remove Temp Zip Directories
	 *
	 * @param int $backup_age_limit  Backup Age Limit in seconds. Defaults to 2 days.
	 */
	public static function remove_temp_zip_dirs( $backup_age_limit = 172800 ) {
		// Remove any old temporary zip directories: wp-content/uploads/backupbuddy_backups/temp_zip_XXXX/. Logs any directories it cannot delete.
		pb_backupbuddy::status( 'details', 'Cleaning up any old temporary zip directories in backup directory temp location `' . backupbuddy_core::getBackupDirectory() . 'temp_zip_XXXX/`.' );
		$temp_directory = backupbuddy_core::getBackupDirectory() . 'temp_zip_*';
		$files          = glob( $temp_directory . '*' );
		if ( is_array( $files ) && ! empty( $files ) ) { // For robustness. Without open_basedir the glob() function returns an empty array for no match. With open_basedir in effect the glob() function returns a boolean false for no match.
			foreach ( $files as $file ) {
				if ( ( strpos( $file, 'index.' ) !== false ) || ( strpos( $file, '.htaccess' ) !== false ) ) { // Index file or htaccess dont get deleted so go to next file.
					continue;
				}
				$file_stats = stat( $file );
				if ( ( time() - $file_stats['mtime'] ) > $backup_age_limit ) { // If older than 12 hours, delete the log.
					if ( @pb_backupbuddy::$filesystem->unlink_recursive( $file ) === false ) {
						$message = 'BackupBuddy was unable to clean up (delete) temporary directory/file: `' . $file . '`. You should manually delete it and/or verify proper file permissions to allow BackupBuddy to clean up for you.';
						pb_backupbuddy::status( 'error', $message );
						backupbuddy_core::mail_error( $message );
					}
				}
			}
		}
	} // End remove_temp_zip_dirs().


	/**
	 * Deletes any temporary BackupBuddy tables used by deployment or rollback functionality. Tables prefixed with bbold- or bbnew-.
	 *
	 * @param string $force_serial      Optional. If provided then this only this serial will be cleaned up AND it will be cleaned up now regardless of its age.
	 * @param int    $backup_age_limit  Backup Age limit.
	 *
	 * @return null
	 */
	public static function remove_temp_tables( $force_serial = '', $backup_age_limit = 0 ) {
		global $wpdb;

		if ( '' != $force_serial ) {
			$cleanups = array( $force_serial => 0 );
		} else {
			$cleanups = pb_backupbuddy::$options['rollback_cleanups'];
		}

		// Check for orphaned tables.
		$possible_orphans = $wpdb->get_results( 'SELECT table_name AS `table_name`, create_time AS `create_time` FROM information_schema.tables WHERE table_name LIKE "bbold%" OR table_name LIKE "bbnew%"' );
		foreach ( (array) $possible_orphans as $possible_orphan ) {
			$serial = substr( $possible_orphan->table_name, 6, 4 );
			if ( empty( $force_serial ) && ! isset( $cleanups[ $serial ] ) ) {
				$create_time = mysql2date( 'U', $possible_orphan->create_time );
				if ( ( time() - $create_time ) > $backup_age_limit ) {
					$cleanups[ $serial ] = $create_time;
				}
			}
		}

		foreach ( $cleanups as $cleanup_serial => $start_time ) {

			$results = $wpdb->get_results( "SELECT table_name AS `table_name` FROM information_schema.tables WHERE ( ( table_name LIKE 'bbnew-" . substr( $cleanup_serial, 0, 4 ) . "\_%' ) OR ( table_name LIKE 'bbold-" . substr( $cleanup_serial, 0, 4 ) . "\_%' ) ) AND table_schema = DATABASE()", ARRAY_A );
			if ( count( $results ) > 0 ) {
				foreach ( $results as $result ) {
					if ( false === $wpdb->query( 'DROP TABLE `' . backupbuddy_core::dbEscape( $result['table_name'] ) . '`' ) ) {
						pb_backupbuddy::status( 'error', 'Error #343344683: Unable to remove stale old temp table `' . $result['table_name'] . '`. Details: `' . $wpdb->last_error . '`.' );
						return false;
					}
				}
			}

			unset( pb_backupbuddy::$options['rollback_cleanups'][ $cleanup_serial ] );
			pb_backupbuddy::save();

		} // end foreach.

		// Delete any undo PHP files.
		$undo_files = glob( ABSPATH . 'backupbuddy_deploy_undo-*.php' );
		if ( ! is_array( $undo_files ) ) {
			$undo_files = array();
		}
		foreach ( $undo_files as $undo_file ) {
			@unlink( $undo_file );
		}

		return;
	} // End remove_temp_tables().


	/**
	 * Validate BackupBuddy Schedules in WordPress
	 *
	 * Verifies schedules all exist and are set up as expected with proper interverals, etc.
	 */
	public static function validate_bb_schedules_in_wp() {

		// Loop through each BB schedule and create WP schedule to match.
		foreach ( pb_backupbuddy::$options['schedules'] as $schedule_id => $schedule ) {

			// Remove invalid schedule arrays.
			if ( ! isset( $schedule['interval'] ) ) {
				unset( pb_backupbuddy::$options['schedules'][ $schedule_id ] );
				continue;
			}

			// Retrieve current interval WordPress cron thinks the schedule is at.
			$cron_inverval = wp_get_schedule( 'backupbuddy_cron', array( 'run_scheduled_backup', array( (int) $schedule_id ) ) );
			$intervals     = wp_get_schedules();

			if ( false === $cron_inverval ) { // Schedule MISSING. Re-schedule.
				$result = backupbuddy_core::schedule_event( $schedule['first_run'], $schedule['interval'], 'run_scheduled_backup', array( (int) $schedule_id ) ); // Add new schedule.
				if ( false === $result ) {
					$message = 'Error #83443784: A missing schedule was identified but unable to be re-created. Your schedule may not work properly & need manual attention.';
					pb_backupbuddy::alert( $message, true );
				} else {
					pb_backupbuddy::alert( 'Warning #2389373: A missing schedule was identified and re-created. This should have corrected any problem with this schedule.', true );
				}
				continue;
			}

			if ( $cron_inverval != $schedule['interval'] ) { // Schedule exists BUT interval is WRONG. Fix it.
				$cron_run = wp_next_scheduled( 'backupbuddy_cron', array( 'run_scheduled_backup', array( (int) $schedule_id ) ) );

				$result = backupbuddy_core::unschedule_event( $cron_run, 'backupbuddy_cron', array( 'run_scheduled_backup', array( (int) $schedule_id ) ) ); // Delete existing schedule.
				if ( false === $result ) {
					$message = 'Error removing invalid event from WordPress. Your schedule may not work properly. Please try again. Error #38279343. Check your BackupBuddy error log for details.';
					pb_backupbuddy::alert( $message, true );
					continue;
				}

				// Determine when the next run time SHOULD be.
				if ( 0 == $schedule['last_run'] ) {
					$next_run = $schedule['first_run'];
				} else {
					$next_run = (int) $schedule['last_run'] + (int) $intervals[ $schedule['interval'] ]['interval'];
				}

				$result = backupbuddy_core::schedule_event( $next_run, $schedule['interval'], 'run_scheduled_backup', array( (int) $schedule_id ) ); // Add new schedule.
				if ( false === $result ) {
					$message = 'Error #237836464: An invalid schedule with the incorrect interval was identified & deleted but unable to be re-created. Your schedule may not work properly & need manual attention.';
					pb_backupbuddy::alert( $message, true );
					continue;
				} else {
					pb_backupbuddy::alert( 'Warning #2423484: An invalid schedule with the incorrect interval was identified and updated. This should have corrected any problem with this schedule.', true );
				}
			}
		} // end foreach.

	} // End validate_bb_schedules_in_wp().

	/**
	 * Backup BackupBuddy Settings
	 */
	public static function backup_bb_settings() {
		if ( empty( pb_backupbuddy::$options ) || ( ! isset( pb_backupbuddy::$options['data_version'] ) ) ) { // Don't backup missing/corrupt settings.
			return false;
		}
		require_once pb_backupbuddy::plugin_path() . '/classes/core.php';

		// TODO: Added Aug 8, 2016. Remove this section after a while.
		// Begin removal of botches storage location.
		$existing_backups = glob( ABSPATH . 'settings_backup-*.php' );
		if ( ! is_array( $existing_backups ) ) {
			$existing_backups = array();
		}
		foreach ( $existing_backups as $existing_backup ) {
			@unlink( $existing_backup );
		}
		// End removal.
		$backup_dir       = backupbuddy_core::getLogDirectory();
		$existing_backups = glob( $backup_dir . 'settings_backup-*.php' );
		if ( ! is_array( $existing_backups ) ) {
			$existing_backups = array();
		}

		// Make sure backup dir protected.
		$die = false;
		pb_backupbuddy::anti_directory_browsing( $backup_dir, $die );

		// If too many backups exist (should be max of 1), remove them all and just create a single one.
		if ( count( $existing_backups ) > 1 ) {
			foreach ( $existing_backups as $i => $existing_backup ) {
				if ( true === @unlink( $existing_backup ) ) {
					unset( $existing_backups[ $i ] );
				}
			}
		}

		// Calculate filename to backup into.
		if ( count( $existing_backups ) >= 1 ) {
			$backup_file = $backup_dir . basename( $existing_backups[0] ); // Use existing backup filename. If more than one just use the top one found.
		} else {
			$backup_file = $backup_dir . 'settings_backup-' . pb_backupbuddy::random_string( 32 ) . '.php'; // 32chars of randomness for security.
		}

		if ( false === ( $file_handle = @fopen( $backup_file, 'w' ) ) ) {
			return false;
		}
		if ( false === fwrite( $file_handle, "<?php die('Access Denied.'); // <!-- ?>\n" . base64_encode( serialize( pb_backupbuddy::$options ) ) ) ) {
			return false;
		} else {
			pb_backupbuddy::status( 'details', 'BackupBuddy plugin options backed up.' );
		}
		@fclose( $file_handle );

		return true;
	} // End backup_bb_settings().

	/**
	 * Remove WP Schedules w/no BackupBuddy Schedule
	 *
	 * Loop through each WP schedule and delete any schedules without corresponding BB schedules or corrupt entries. Also handles migration from old to new tag backupbuddy_cron.
	 * NOTE: Also upgrades to new backupbuddy_cron tag from pb_backupbuddy-cron_scheduled_backup AND migrationg of housekeeping tag AND removal of faulty tags.
	 */
	public static function remove_wp_schedules_with_no_bb_schedule() {
		$cron = get_option( 'cron' );

		foreach ( (array) $cron as $time => $cron_item ) { // Times.
			if ( is_numeric( $time ) ) {
				// Loop through each schedule for this time.
				foreach ( (array) $cron_item as $hook_name => $event ) { // Methods.
					foreach ( (array) $event as $item_name => $item ) { // Full args for method.

						if ( 'backupbuddy_cron' === $hook_name && 'run_scheduled_backup' === $item['args'][0] ) { // scheduled backup.
							if ( ! empty( $item['args'] ) ) {
								if ( ! isset( pb_backupbuddy::$options['schedules'][ $item['args'][1][0] ] ) ) { // BB schedule does not exist so delete this cron item.
									if ( false === backupbuddy_core::unschedule_event( $time, $hook_name, $item['args'] ) ) { // Delete the scheduled cron.
										pb_backupbuddy::status( 'error', 'Error #5657667675b. Unable to delete CRON job. Please see your BackupBuddy error log for details.' );
									} else {
										pb_backupbuddy::status( 'warning', 'Removed stale cron scheduled backup.' );
									}
								}
							} else { // No args, something wrong so delete it.

								if ( false === backupbuddy_core::unschedule_event( $time, $hook_name, $item['args'] ) ) { // Delete the scheduled cron.
									pb_backupbuddy::status( 'error', 'Error #5657667675c. Unable to delete CRON job. Please see your BackupBuddy error log for details.' );
								} else {
									pb_backupbuddy::status( 'warning', 'Removed stale cron scheduled backup which had no arguments.' );
								}
							}
						} elseif ( 'pb_backupbuddy-cron_scheduled_backup' === $hook_name ) { // Upgrade hook name to 'backupbuddy_cron'.
							if ( false === wp_unschedule_event( $time, $hook_name, $item['args'] ) ) { // Delete the scheduled cron.
								pb_backupbuddy::status( 'error', 'Error #327237. Unable to delete CRON job for migration to new tag. Please see your BackupBuddy error log for details.' );
							} else {
								pb_backupbuddy::status( 'details', 'Removed cron with old tag format.' );
							}
							if ( isset( pb_backupbuddy::$options['schedules'][ $item['args'][0] ] ) ) { // BB schedule exists so recreate.
								$result = backupbuddy_core::schedule_event( $time, pb_backupbuddy::$options['schedules'][ $item['args'][0] ]['interval'], 'run_scheduled_backup', $item['args'] );
								if ( false === $result ) {
									pb_backupbuddy::status( 'error', 'Error #8923832: Unable to reschedule with new cron tag.' );
								} else {
									pb_backupbuddy::status( 'details', 'Replaced cron with old tag format with new format.' );
								}
							} else {
								pb_backupbuddy::status( 'warning', 'Stale schedule found with WordPress without corresponding BackupBuddy schedule. Not keeping when migrating to new cron tag.' );
							}
						} elseif ( 'pb_backupbuddy_cron' === $hook_name ) { // Remove.
							wp_unschedule_event( $time, $hook_name, $item['args'] );
						} elseif ( 'pb_backupbuddy_corn' === $hook_name ) { // Remove.
							wp_unschedule_event( $time, $hook_name, $item['args'] );
						} elseif ( 'pb_backupbuddy_housekeeping' === $hook_name ) { // Remove.
							wp_unschedule_event( $time, $hook_name, $item['args'] );
						}
					} // End foreach.
					unset( $item );
					unset( $item_name );
				} // End foreach.
				unset( $event );
				unset( $hook_name );
			} // End if is_numeric.
		} // End foreach.
		unset( $cron_item );
		unset( $time );

	} // End remove_wp_schedules_with_no_bb_schedule().

	/**
	 * Trim Old Notifications
	 */
	public static function trim_old_notifications() {
		$notifications        = backupbuddy_core::getNotifications(); // Load notifications.
		$update_notifications = false;
		$max_time_diff        = pb_backupbuddy::$options['max_notifications_age_days'] * 24 * 60 * 60;
		foreach ( $notifications as $i => $notification ) {
			if ( ( time() - $notification['time'] ) > $max_time_diff ) {
				unset( $notifications[ $i ] );
				$update_notifications = true;
			}
		}
		if ( true === $update_notifications ) {
			pb_backupbuddy::status( 'details', 'Periodic cleanup: Replacing notifications.' );
			backupbuddy_core::replaceNotifications( $notifications );
		}
	} // End trim_old_notifications().

	/**
	 * S3 Cancel Multipart Pieces.
	 */
	public static function s3_cancel_multipart_pieces() {
		foreach ( pb_backupbuddy::$options['remote_destinations'] as $destination ) {
			if ( 's3' !== $destination['type'] ) {
				continue;
			}
			if ( isset( $destination['max_chunk_size'] ) && '0' == $destination['max_chunk_size'] ) {
				continue;
			}

			pb_backupbuddy::status( 'details', 'Found S3 Multipart Chunking Destinations to cleanup.' );
			require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
			$cleanup_result = pb_backupbuddy_destinations::multipart_cleanup( $destination );
			if ( true === $cleanup_result ) {
				pb_backupbuddy::status( 'details', 'S3 Multipart Chunking Cleanup Success.' );
			} else {
				pb_backupbuddy::status( 'warning', 'Warning #349742389383: S3 Multipart Chunking Cleanup FAILURE. Manually cleanup stalled multipart send via S3 or try again later.' );
			}
		}
	} // End s3_cancel_multipart_pieces().

	/**
	 * S3 v2 Cancel Multipart Pieces.
	 *
	 * Note: Cannot cleanup both s32 & s33 type desinations togetehr because of Amazon library
	 * conflicts. First check whether we have both any s32 or s33 type destinations  - if not then
	 * there is no problem as we have nothing to do anyway. if we do then as this is a periodic
	 * process we randomy choose which type of destination to clean up this invocation.
	 */
	public static function s32_cancel_multipart_pieces() {
		$s3x_destination = array(
			'types'   => array( 's32', 's33' ),
			'present' => array(),
		);

		// Derive which s3x types destinations are present (only record each type once).
		foreach ( pb_backupbuddy::$options['remote_destinations'] as $destination ) {
			if ( in_array( $destination['type'], $s3x_destination['types'], true ) && ! in_array( $destination['type'], $s3x_destination['present'], true ) ) {
				$s3x_destination['present'][] = $destination['type'];
			}
		}

		// If we have no s3x type destinations then nothing to do.
		if ( ! empty( $s3x_destination['present'] ) ) {

			// We have s3x types destinations - decide which to process this time
			// Randomly choose a destination type from those present to process by
			// generating a random aray index to select which type from those present.
			$s3x_destination_type = $s3x_destination['present'][ rand( 0, ( count( $s3x_destination['present'] ) - 1 ) ) ];

			foreach ( pb_backupbuddy::$options['remote_destinations'] as $destination ) {
				if ( $destination['type'] != $s3x_destination_type ) {
					continue;
				}

				pb_backupbuddy::status( 'details', 'Found ' . $destination['title'] . ' (' . $s3x_destination_type . ') Multipart Chunking Destination to cleanup.' );
				require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
				$cleanup_result = pb_backupbuddy_destinations::multipart_cleanup( $destination );
				if ( true === $cleanup_result ) {
					pb_backupbuddy::status( 'details', $destination['title'] . ' (' . $s3x_destination_type . ') Multipart Chunking Destination Cleanup Success.' );
				} else {
					pb_backupbuddy::status( 'warning', 'Warning #2389328: ' . $destination['title'] . ' (' . $s3x_destination_type . ') Multipart Chunking Destination Cleanup FAILURE. Manually cleanup stalled multipart send via S3 or try again later.' );
				}
			}
		}
	} // End s32_cancel_multipart_pieces().

	/**
	 * Cleanup Local Destination Temp
	 */
	public static function cleanup_local_destination_temp() {
		// Cleanup any temporary local destinations.
		pb_backupbuddy::status( 'details', 'Cleaning up any temporary local destinations.' );
		foreach ( pb_backupbuddy::$options['remote_destinations'] as $destination_id => $destination ) {
			if ( 'local' === $destination['type'] && isset( $destination['temporary'] ) && true === $destination['temporary'] ) { // If local and temporary.
				if ( ( time() - $destination['created'] ) > $backup_age_limit ) { // Older than 12 hours; clear out!
					pb_backupbuddy::status( 'details', 'Cleaned up stale local destination `' . $destination_id . '`.' );
					unset( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] );
					pb_backupbuddy::save();
				}
			}
		}
	} // End cleanup_local_destination_temp().

	/**
	 * Schedule PHP Runtime Tests
	 *
	 * @uses php_runtime_test
	 *
	 * @param bool $force_run  Forces run, passed along to php_runtime_test.
	 *
	 * @return null  If Not enough time elapsed since last run.
	 */
	public static function schedule_php_runtime_tests( $force_run = false ) {
		pb_backupbuddy::status( 'details', 'About to schedule PHP runtime tests.' );

		if ( pb_backupbuddy::$options['php_runtime_test_minimum_interval'] <= 0 ) {
			pb_backupbuddy::status( 'warnings', 'PHP runtime test disabled based on advanced settings.' );
			return false;
		}

		// Don't run runtime test too often.
		if ( pb_backupbuddy::$options['last_tested_php_runtime'] > 0 ) { // if it's run at least once...
			$elapsed = time() - pb_backupbuddy::$options['last_tested_php_runtime'];
			if ( $elapsed < pb_backupbuddy::$options['php_runtime_test_minimum_interval'] ) { // Not enough time elapsed since last run.
				pb_backupbuddy::status( 'details', 'Not enough time elapsed since last PHP runtime test interval. Waiting until next housekeeping (or longer). Elapsed: `' . $elapsed . '`. Interval limit: `' . pb_backupbuddy::$options['php_runtime_test_minimum_interval'] . '`.' );
				return;
			}
		}

		// Schedule to run test.
		$args            = array(
			true,        // schedule_results.
			$force_run,  // force_run.
		);
		$schedule_result = backupbuddy_core::schedule_single_event( time(), 'php_runtime_test', $args );
		if ( true === $schedule_result ) {
			pb_backupbuddy::status( 'details', 'PHP runtime test cron event scheduled.' );
		} else {
			pb_backupbuddy::status( 'error', 'PHP runtime test cron event FAILED to be scheduled.' );
		}

		// Spawn now if enabled.
		if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
			pb_backupbuddy::status( 'details', 'Spawning cron now.' );
			update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
			spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
		}
	} // End schedule_php_runtime_tests().

	/**
	 * Schedule PHP Memory Tests
	 *
	 * @uses php_memory_test
	 *
	 * @param bool $force_run  Forces Run, passed along to php_memory_test.
	 *
	 * @return null  If not enough time elapsed since last run.
	 */
	public static function schedule_php_memory_tests( $force_run = false ) {
		pb_backupbuddy::status( 'details', 'About to schedule PHP memory tests.' );

		if ( pb_backupbuddy::$options['php_memory_test_minimum_interval'] <= 0 ) {
			pb_backupbuddy::status( 'warnings', 'PHP memory test disabled based on advanced settings.' );
			return false;
		}

		// Don't run memory test too often.
		if ( pb_backupbuddy::$options['last_tested_php_memory'] > 0 ) { // if it's run at least once...
			$elapsed = time() - pb_backupbuddy::$options['last_tested_php_memory'];
			if ( $elapsed < pb_backupbuddy::$options['php_memory_test_minimum_interval'] ) { // Not enough time elapsed since last run.
				pb_backupbuddy::status( 'details', 'Not enough time elapsed since last PHP memory test interval. Waiting until next housekeeping (or longer). Elapsed: `' . $elapsed . '`. Interval limit: `' . pb_backupbuddy::$options['php_memory_test_minimum_interval'] . '`.' );
				return;
			}
		}

		// Schedule to run test.
		$args            = array(
			true,        // schedule_results.
			$force_run,  // force_run.
		);
		$schedule_result = backupbuddy_core::schedule_single_event( time(), 'php_memory_test', $args );
		if ( true === $schedule_result ) {
			pb_backupbuddy::status( 'details', 'PHP memory test cron event scheduled.' );
		} else {
			pb_backupbuddy::status( 'error', 'PHP memory test cron event FAILED to be scheduled.' );
		}

		// Spawn now if enabled.
		if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
			pb_backupbuddy::status( 'details', 'Spawning cron now.' );
			update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
			spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
		}
	} // End schedule_php_memory_tests().

} // end class.
