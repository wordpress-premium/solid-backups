<?php
$schedules = array();
foreach( pb_backupbuddy::$options['schedules'] as $schedule_id => $schedule ) {
	if ( isset( $schedule['on_off'] ) ) {
		$on_off = $schedule['on_off'];
	} else {
		$on_off = '1'; // Default to enabled if old schedule.
	}
	
	$schedules[] = array(
		'title' => strip_tags( $schedule['title'] ),
		'type' => pb_backupbuddy::$options['profiles'][$schedule['profile']]['type'],
		'interval' => $schedule['interval'],
		'lastRun' => $schedule['last_run'],
		'nextRun' => wp_next_scheduled( 'backupbuddy_cron', array( 'run_scheduled_backup', array( (int) $schedule_id ) ) ),
		'enabled' => $on_off,
		'profileID' => $schedule['profile'],
		'profileTitle' => strip_tags( pb_backupbuddy::$options['profiles'][$schedule['profile']]['title'] ),
		'id' => $schedule_id
	);
}
return $schedules;