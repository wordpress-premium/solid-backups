<?php
require_once pb_backupbuddy::plugin_path() . '/classes/class-backupbuddy-schedules.php';

if ( ! is_numeric( $first_run ) ) {
	backupbuddy_api::$lastError = 'Error #238983: First run time must be numeric.';
	return false;
}

if ( ! is_array( $remote_destinations ) ) {
	$remote_destinations = array();
}

if ( ! (bool) $delete_after ) {
	$delete_after = false;
}

if ( ! (bool) $enabled ) {
	$enabled = false;
}

if ( ! isset( pb_backupbuddy::$options['profiles'][ $profile ] ) ) {
	backupbuddy_api::$lastError = 'Error #378646: Invalid profile ID.';
	return false;
}

$schedule = pb_backupbuddy::settings( 'schedule_defaults' );
$schedule['title']               = $title;
$schedule['profile']             = (int)$profile;
$schedule['interval']            = $interval;
$schedule['first_run']           = $first_run;
$schedule['remote_destinations'] = implode( '|', $remote_destinations );
$schedule['delete_after']        = $delete_after ? '1' : '0';
$schedule['on_off']              = $enabled ? '1' : '0';

$next_index = pb_backupbuddy::$options['next_schedule_index']; // v2.1.3: $next_index = end( array_keys( pb_backupbuddy::$options['schedules'] ) ) + 1;
pb_backupbuddy::$options['next_schedule_index']++; // This change will be saved in savesettings function below.
pb_backupbuddy::$options['schedules'][ $next_index ] = $schedule;

pb_backupbuddy::log( 'details', 'Attempting to schedule with the following parameters: ' . print_r( $schedule['first_run'], true ) . print_r( $schedule['interval'], true ) . print_r( $next_index, true ) );

if ( $enabled ) {
	$result = BackupBuddy_Schedules::schedule_recurring_backup( $schedule['first_run'], $schedule['interval'], 'run_scheduled_backup', array( $next_index ) );
}

if ( $enabled && false === $result ) {
	return 'Error scheduling event with WordPress. Your schedule may not work properly. Please try again. Error #3488439b. Check your Solid Backups error log for details.';
} else {
	pb_backupbuddy::save();
	backupbuddy_core::addNotification( 'schedule_created', 'Backup schedule created', 'A new backup schedule "' . $schedule['title'] . '" has been created.', $schedule );

	return true;
}
