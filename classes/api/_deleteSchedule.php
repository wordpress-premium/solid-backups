<?php
/**
 * Delete Schedule API
 *
 * @package BackupBuddy
 */

if ( true !== $confirm ) {
	return false;
}

$next_scheduled_time = wp_next_scheduled( 'backupbuddy_cron', array( 'run_scheduled_backup', array( (int) $scheduleID ) ) );
if ( $next_scheduled_time ) {
	// Only attempt to unschedule if schedule exists, otherwise, proceed to remove schedule.
	if ( false === backupbuddy_core::unschedule_event( $next_scheduled_time, 'backupbuddy_cron', array( 'run_scheduled_backup', array( (int) $scheduleID ) ) ) ) {
		$schedule_title = htmlentities( pb_backupbuddy::$options['schedules'][ (int) $scheduleID ]['title'] );
		pb_backupbuddy::alert( __( 'Could not unschedule cron event for:', 'it-l10n-backupbuddy' ) . ' ' . $schedule_title, true );
		return false;
	}
}

$deletedSchedule = pb_backupbuddy::$options['schedules'][ $scheduleID ];
unset( pb_backupbuddy::$options['schedules'][ $scheduleID ] );
pb_backupbuddy::save();

backupbuddy_core::addNotification( 'schedule_deleted', 'Backup schedule deleted', 'An existing backup schedule "' . $deletedSchedule['title'] . '" has been deleted.', $deletedSchedule );

return true;
