<?php
/**
 * BackupBuddy API Class
 *
 * TIP: Check if a function is callable before running if using these methods from a 3rd party software.
 * Eg. if ( is_callable( array( 'backupbuddy_api', 'runBackup' ) ) ) { ...
 *
 * @package BackupBuddy
 */

/**
 * Main BackupBuddy API Class
 */
class backupbuddy_api {

	public static $apiVersion = 2;

	public static $lastError = '';


	/* getLastError()
	 *
	 * Retrieve the last error the API encountered. Use if a method returned bool FALSE to get message.
	 *
	 */
	public static function getLastError() {
		return $lastError;
	}


	/* runBackup()
	 *
	 * @param	string|int	$generic_type_or_profile_id		Valid options are: full, db, OR the numeric profile ID number of the profile to run.
	 * @param	int			$backupMode						1 = classic (single PHP page load), 2 = modern (uses cron). Default: BLANK which uses mode based on settings.
	 * @param	string		$backupSerial					If set then forces backup to use the specificed serial.
	 * @return	true|string									Returns true on success running the backup, else a string error message.
	 *
	 */
	public static function runBackup( $generic_type_or_profile_id_or_array = '', $triggerTitle = 'BB API', $backupMode = '', $backupSerial = '', $destinations = array(), $delete_after = 0 ) {
		self::_before();
		return require( dirname(__FILE__) . '/api/_runBackup.php' );
	}


	/* getLatestBackupStats()
	 *
	 * Get an array of useful information about the latest backup that has started, including its progress. Returns false if no backup has run or unable to retrieve the information.
	 * @return			Returns an array of various data.
	 */
	public static function getLatestBackupStats() {
		self::_before();
		return require( dirname(__FILE__) . '/api/_getLatestBackupStats.php' );
	}



	public static function getLiveStats() {
		self::_before();
		return require( dirname(__FILE__) . '/api/_getLiveStats.php' );
	}



	/* setLiveStatus()
	 *
	 * Pause/Resume BackupBuddy Stash Live status for continuous database and/or periodic scans (files).
	 *
	 * @param bool|string	$continuous_enabled		Continuous activity (Live DB updating). bool to pause (true) / resume (false) OR empty string '' to leave the same.
	 * @param bool|string	$periodic_enabled		Periodic activity (file scan, db dump, cleanups, etc). bool to pause (true) / resume (false) OR empty string '' to leave the same.
	 * @param bool			$start_run				Whether or not to start running now _IF_ unpausing from a paused state.
	 * @return array        						Returns status array( 'continuous_status' => $continuous_status, 'periodic_status' => $periodic_status ).  1=enabled, 0=paused.
	 */
	public static function setLiveStatus( $pause_continuous = '', $pause_periodic = '', $start_run = true ) {
		self::_before();
		return require( dirname(__FILE__) . '/api/_setLiveStatus.php' );
	} // End setLiveStatus().



	/* runLiveSnapshot()
	 *
	 * Run a Live Snapshot, including rescan prior.
	 *
	 * @return		bool		true on success beginning scan to snapshot or false if a scan is currently in progress and cannot be interupted.
	 */
	public static function runLiveSnapshot() {
		self::_before();
		return require( dirname(__FILE__) . '/api/_runLiveSnapshot.php' );
	} // End runLiveSnapshot(().



	// backupbuddy_api::getOverview()
	public static function getOverview() {
		self::_before();
		return require( dirname(__FILE__) . '/api/_getOverview.php' );
	}


	// backupbuddy_api::getSchedules()
	public static function getSchedules() {
		self::_before();
		return require( dirname(__FILE__) . '/api/_getSchedules.php' );
	}

	// NOTE: Currently only support $echo === true.
	public static function getBackupStatus( $serial, $specialAction = '', $initRetryCount = 0, $sqlFile = '', $echo = true ) {
		self::_before();
		return require( dirname(__FILE__) . '/api/_getBackupStatus.php' );
	}

	/**
	 * Adds a new backup profile
	 *
	 * @param string $title  The Backup Profile title
	 * @param string $type  The Backup Profile Type
	 *
	 * @return bool
	 */
	public static function addProfile( $title, $type ) {
		self::_before();
		return require dirname( __FILE__ ) . '/api/_addProfile.php';
	}

	/**
	 * Adds a new schedule for backing up.
	 *
	 * @param string $title                Schedule title (user-friendly name).
	 * @param int    $profile              Profile ID.
	 * @param string $interval             WordPress schedule interval for cron (ie weekly, daily, hourly, etc).
	 * @param int    $first_run            Timestamp of when to run the first in this scheduled cron series.
	 * @param array  $remote_destinations  Array of remote destination IDs to send to.
	 * @param bool   $delete_after         Whether or not to delete local backup file after success sending to all remote destinations (if any). Does not delete if no destinations defined.
	 * @param bool   $enabled              true if enabled, else false.
	 *
	 * @return true|string  true on success, else error message string.
	 */
	public static function addSchedule( $title, $profile, $interval, $first_run, $remote_destinations = array(), $delete_after = false, $enabled = true ) {
		self::_before();
		return require dirname( __FILE__ ) . '/api/_addSchedule.php';
	}

	/**
	 * Edits an existing schedule for backing up.
	 *
	 * @param int    $schedule_id         Schedule ID to edit.
	 * @param string $title               Schedule title (user-friendly name).
	 * @param int    $profile             Profile ID.
	 * @param string $interval            WordPress schedule interval for cron (ie weekly, daily, hourly, etc).
	 * @param int    $first_run           Timestamp of when to run the first in this scheduled cron series.
	 * @param array  $remote_destinations Array of remote destination IDs to send to.
	 * @param bool   $delete_after        Whether or not to delete local backup file after success sending to all remote destinations (if any). Does not delete if no destinations defined.
	 * @param bool   $enabled             true if enabled, else false.
	 *
	 * @return true|string                      true on success, else error message string.
	 */
	public static function editSchedule( $schedule_id, $title, $profile, $interval, $first_run, $remote_destinations = array(), $delete_after = false, $enabled = true ) {
		self::_before();
		return require dirname( __FILE__ ) . '/api/_editSchedule.php';
	}


	public static function deleteSchedule( $scheduleID, $confirm = false ) {
		self::_before();
		return require( dirname(__FILE__) . '/api/_deleteSchedule.php' );
	}


	// $sha1 = whether or not to use sha1 for file comparison. performance hit. bool or '1'/'0'. $destinationSettings = array of dest settings.
	public static function getPreDeployInfo( $sha1 = false, $destinationSettings ) {
		self::_before();

		if ( '1' == $sha1 ) {
			$sha1 = true;
		} elseif ( '0' == $sha1 ) {
			$sha1 = false;
		}

		return require( dirname(__FILE__) . '/api/_getPreDeployInfo.php' );
	}


	public static function getActivePlugins() {
		self::_before();
		return require( dirname(__FILE__) . '/api/_getActivePlugins.php' );
	}


	private static function _before() {
		// Load backupbuddy class with helper functions.
		if ( ! class_exists( 'backupbuddy_core' ) ) {
			require_once( pb_backupbuddy::plugin_path() . '/classes/core.php' );
		}
	}

} // end class.
