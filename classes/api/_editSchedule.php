<?php
/**
 * Edit Schedule API Callback
 *
 * @package BackupBuddy
 * @author Glenn Ansley
 */

if ( empty( $schedule_id ) || ! is_numeric( $schedule_id ) ) {
	backupbuddy_api::$lastError = 'Error: Schedule ID must be numeric';
	return false;
}

if ( empty( pb_backupbuddy::$options['schedules'][ $schedule_id ] ) ) {
	backupbuddy_api::$lastError = 'Error: Schedule ID not found.';
	return false;
}

if ( ! is_numeric( $first_run ) ) {
	backupbuddy_api::$lastError = 'First run time must be numeric.';
	return false;
}

if ( empty( $title ) ) {
	backupbuddy_api::$lastError = '`title` is a required parameter.';
	return false;
}

if ( ! is_array( $remote_destinations ) ) {
	$remote_destinations = array();
}

// Force enabled to false boolean.
if ( ! (bool) $enabled ) {
	$enabled = false;
}

if ( ! is_bool( $enabled ) ) {
	backupbuddy_api::$lastError = '`enabled` must be a boolean';
	return false;
}

if ( ! isset( pb_backupbuddy::$options['profiles'][ $profile ] ) ) {
	backupbuddy_api::$lastError = 'Invalid profile ID.';
	return false;
}

if ( 0 == $first_run || 18000 == $first_run ) {
	backupbuddy_api::$lastError = 'Invalid value for `firstrun`. Must in in unixtime.';
	return false;
}

// Update title.
pb_backupbuddy::$options['schedules'][ $schedule_id ]['title'] = $title;

// Update profile.
pb_backupbuddy::$options['schedules'][ $schedule_id ]['profile'] = $profile;

// Update destinations.
pb_backupbuddy::$options['schedules'][ $schedule_id ]['remote_destinations'] = implode( $remote_destinations, '|' );

// Update interval.
pb_backupbuddy::$options['schedules'][ $schedule_id ]['interval'] = $interval;

// Update delete_after.
pb_backupbuddy::$options['schedules'][ $schedule_id ]['delete_after'] = $delete_after;

// Update Schedudle if needed.
if ( pb_backupbuddy::$options['schedules'][ $schedule_id ]['first_run'] != $first_run || pb_backupbuddy::$options['schedules'][ $schedule_id ]['interval'] != $interval ) {
	pb_backupbuddy::$options['schedules'][ $schedule_id ]['first_run'] = $first_run;

	// Remove old schedule.
	$next_scheduled_time = wp_next_scheduled( 'backupbuddy_cron', array( 'run_scheduled_backup', array( (int) $schedule_id ) ) );
	$result              = backupbuddy_core::unschedule_event(
		$next_scheduled_time, 'backupbuddy_cron', array(
			'run_scheduled_backup',
			array( (int) $schedule_id ),
		)
	);

	if ( false === $result ) {
		return false;
	}

	// Add new schedule.
	$result = backupbuddy_core::schedule_event( $first_run, $interval, 'run_scheduled_backup', array( (int) $schedule_id ) );
	if ( false === $result ) {
		return false;
	}
}

pb_backupbuddy::save();
require_once pb_backupbuddy::plugin_path() . '/classes/housekeeping.php';
backupbuddy_housekeeping::validate_bb_schedules_in_wp();
return true;
