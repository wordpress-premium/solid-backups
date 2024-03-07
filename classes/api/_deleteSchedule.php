<?php
/**
 * Delete Schedule API
 *
 * @package BackupBuddy
 */

require_once pb_backupbuddy::plugin_path() . '/classes/class-backupbuddy-schedules.php';

if ( true !== $confirm ) {
	return false;
}

BackupBuddy_Schedules::unschedule_recurring_backup( (int) $scheduleID );
$deletedSchedule = pb_backupbuddy::$options['schedules'][ $scheduleID ];
unset( pb_backupbuddy::$options['schedules'][ $scheduleID ] );
pb_backupbuddy::save();

backupbuddy_core::addNotification( 'schedule_deleted', 'Backup schedule deleted', 'An existing backup schedule "' . $deletedSchedule['title'] . '" has been deleted.', $deletedSchedule );

return true;
