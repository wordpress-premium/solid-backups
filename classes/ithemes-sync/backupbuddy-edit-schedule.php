<?php
/**
 * Ithemes Sync Verb to Edit BackupBuddy Schedule
 *
 * @package BackupBuddy
 * @author Glenn Ansley
 */

/**
 * Main Class for Editing BackupBuddy Schedule
 */
class Ithemes_Sync_Verb_Backupbuddy_Edit_Schedule extends Ithemes_Sync_Verb {

	/**
	 * Verb Name
	 *
	 * @var string
	 */
	public static $name = 'backupbuddy-edit-schedule';

	/**
	 * Verb Description
	 *
	 * @var string
	 */
	public static $description = 'Add a new schedule.';

	/**
	 * Default Arguments
	 *
	 * @var array
	 */
	private $default_arguments = array(
		'title'        => '', // User-friendly string title for convenience.
		'profile'      => '', // Profile ID number (numeric).
		'interval'     => '', // Tag interval for schedule for WP cron. ie. hourly, daily, twicedaily, weekly, twiceweekly, monthly, twicemonthly.
		'firstRun'     => 0, // Timestamp of first runtime.
		'destinations' => array(), // Array of destination IDs to send to after the schedule completes running.
		'deleteAfter'  => false, // Whether or not to delete the local copy of the backup after sending to a destination (if applicable).
		'enabled'      => true, // Whether or not this schedule is currently enabled (active) to be able to run.
	);

	/**
	 * Handle Edit Schedule
	 *
	 * @param array $arguments  Array of arguments.
	 *
	 * @return array  'success' => '0' | '1'  and   'status' => 'Status message.'
	 */
	public function run( $arguments ) {
		$arguments = Ithemes_Sync_Functions::merge_defaults( $arguments, $this->default_arguments );

		if ( true !== backupbuddy_api::editSchedule( $arguments['schedule_id'], $arguments['title'], $arguments['profile'], $arguments['interval'], $arguments['firstRun'], $arguments['destinations'], $arguments['deleteAfter'], $arguments['enabled'] ) ) {

			return array(
				'api'     => '0',
				'status'  => 'error',
				'message' => 'Error #378235: Edit schedule failed. A plugin may have blocked scheduling with WordPress. Details: ' . backupbuddy_api::$lastError,
			);

		} else {

			return array(
				'api'        => '0',
				'status'     => 'ok',
				'message'    => 'Schedule edited successfully.',
				'scheduleID' => (int) $arguments['schedule_id'],
			);

		}

	} // End run().

} // End class.
