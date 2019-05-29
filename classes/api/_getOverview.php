<?php
/**
 * Overview for WordPress Dashboard Widget.
 *
 * @package BackupBuddy
 */

$overview = array(
	'backupbuddyVersion'           => pb_backupbuddy::settings( 'version' ),
	'localTime'                    => time(),
	'lastBackupStart'              => pb_backupbuddy::$options['last_backup_start'],
	'lastBackupSerial'             => pb_backupbuddy::$options['last_backup_serial'],
	'lastBackupStats'              => pb_backupbuddy::$options['last_backup_stats'],
	'editsSinceLastBackup'         => is_array( pb_backupbuddy::$options['edits_since_last'] ) ? pb_backupbuddy::$options['edits_since_last']['all'] : pb_backupbuddy::$options['edits_since_last'],
	'recentEdits'                  => pb_backupbuddy::$options['recent_edits'],
	'advancedEditsSinceLastBackup' => false,
	'editsTrackingMode'            => pb_backupbuddy::$options['edits_tracking_mode'],
	'scheduleCount'                => count( pb_backupbuddy::$options['schedules'] ),
	'profileCount'                 => count( pb_backupbuddy::$options['profiles'] ),
	'destinationCount'             => count( pb_backupbuddy::$options['remote_destinations'] ),
	'gmtOffset'                    => get_option( 'gmt_offset' ),
	'php'                          => array(
		'upload_max_filesize' => @ini_get( 'upload_max_filesize' ),
		'max_execution_time'  => @ini_get( 'max_execution_time' ),
	),
	'notifications'                => array(), // Array of string notification messages.
);

if ( 'advanced' === $overview['editsTrackingMode'] && is_array( pb_backupbuddy::$options['edits_since_last'] ) ) {
	$overview['advancedEditsSinceLastBackup'] = array(
		'all_edits'    => pb_backupbuddy::$options['edits_since_last']['all'],
		'post_edits'   => pb_backupbuddy::$options['edits_since_last']['post'],
		'plugin_edits' => pb_backupbuddy::$options['edits_since_last']['plugin'],
		'option_edits' => pb_backupbuddy::$options['edits_since_last']['option'],
	);
}

return $overview;
