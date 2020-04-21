<?php
/* BackupBuddy Stash Live Periodic Class
 *
 * @author Dustin Bolton
 * @since 7.0
 *
 * NOTE: Incoming vars: $destination, $destination_id, $state
 */


// Reload.
$destination = pb_backupbuddy::$options['remote_destinations'][$destination_id];

// Get WP schedules.
$schedule_intervals = wp_get_schedules();

$stats = array(
	'data_version' => 2,
	'current_time' => microtime( true ),
	'current_function' => __( 'Unknown', 'it-l10n-backupbuddy' ),
	'current_function_pretty' => __( 'Unknown', 'it-l10n-backupbuddy' ),
	'live_username' => $destination['itxapi_username'],
	'last_function_status_pretty' => __( 'Unknown', 'it-l10n-backupbuddy' ),
	'error_alert' => '', // Only has a non-empty value if last_status in state contains the word "error" (not case sensitive).
	'continuous_status' => '-1',
	'continuous_status_pretty' => __( 'Unknown', 'it-l10n-backupbuddy' ),
	'overall_percent' => '-1',
	'files_to_catalog_percentage'  => '0',
	
	'periodic_status' => '-1',
	'periodic_status_pretty' => __( 'Unknown', 'it-l10n-backupbuddy' ),
	'periodic_interval' => '-1',
	'periodic_interval_pretty' => __( 'Unknown', 'it-l10n-backupbuddy' ),
	
	'database_size_bytes' => '-1',
	'database_size_pretty' => __( 'Pending...', 'it-l10n-backupbuddy' ),
	'database_tables_total' => '-1',
	'database_tables_pending_delete' => '-1',
	'database_tables_sent' => '-1',
	'database_tables_pending_send' => '-1',
	'database_tables_sent_percent' => '-1',
	
	'files_size_bytes' => '-1',
	'files_size_pretty' => 'Unknown',
	'files_total' => '-1',
	'files_sent' => '-1',
	'files_sent_percent' => '-1',
	'files_pending_delete' => '-1',
	
	'last_remote_snapshot_id' => '-1',
	'last_remote_snapshot' => '-1',
	'last_remote_snapshot_pretty' => __( 'Pending...', 'it-l10n-backupbuddy' ),
	'last_remote_snapshot_ago' => __( 'Pending...', 'it-l10n-backupbuddy' ),
	'last_remote_snapshot_response' => __( 'Unknown', 'it-l10n-backupbuddy' ),
	'last_remote_snapshot_response_time' => '-1',
	'last_remote_snapshot_trigger' => '',
	'last_db_snapshot' => '-1',
	'last_db_snapshot_pretty' => __( 'Pending...', 'it-l10n-backupbuddy' ),
	'last_db_snapshot_ago' => __( 'Pending...', 'it-l10n-backupbuddy' ),
	
	'last_database_live_activity' => '-1',
	'last_database_live_activity_pretty' => __( 'Unknown', 'it-l10n-backupbuddy' ),
	'last_database_live_activity_ago' => __( 'Pending...', 'it-l10n-backupbuddy' ),
	
	'last_periodic_activity' => '-1',
	'last_periodic_activity_pretty' => __( 'Pending...', 'it-l10n-backupbuddy' ),
	'last_periodic_activity_ago' => __( 'Pending...', 'it-l10n-backupbuddy' ),
	'last_file_audit_start' => '-1',
	'last_file_audit_finish' => '-1',
	
	'next_db_snapshot' => '-1',
	'next_db_snapshot_pretty' => __( 'Unknown', 'it-l10n-backupbuddy' ),
	'next_periodic_restart' => '-1',
	'next_periodic_restart_pretty' => __( 'Unknown', 'it-l10n-backupbuddy' ),
	'next_remote_snapshot' => '-1',
	'next_remote_snapshot_pretty' => __( 'Unknown', 'it-l10n-backupbuddy' ),
	
	'remote_snapshot_interval' => '-1',
	'remote_snapshot_interval_pretty' => __( 'Unknown', 'it-l10n-backupbuddy' ),
	'first_completion' => '-1',
	'first_completion_pretty' =>  __( 'Pending...', 'it-l10n-backupbuddy' ),
	'daily_stats' => array(),
);


// Continuous Status.
if ( '1' == $destination['pause_continuous'] ) {
	$stats['continuous_status'] = '0';
	$stats['continuous_status_pretty'] = __( 'Paused', 'it-l10n-backupbuddy' );
} else {
	$stats['continuous_status'] = '1';
	$stats['continuous_status_pretty'] = __( 'Enabled', 'it-l10n-backupbuddy' );
}


// Periodic status.
if ( '1' == $destination['pause_periodic'] ) {
	$stats['periodic_status'] = '0';
	$stats['periodic_status_pretty'] = __( 'Paused', 'it-l10n-backupbuddy' );
} else {
	$stats['periodic_status'] = '1';
	$stats['periodic_status_pretty'] = __( 'Enabled', 'it-l10n-backupbuddy' );
}


// Last function status.
if ( '' != $state['step']['last_status'] ) {
	$stats['last_function_status_pretty'] = str_replace( "'", '', $state['step']['last_status'] );
}
if ( ( '' != $state['step']['last_status'] ) && ( FALSE !== stristr( $state['step']['last_status'], 'Error' ) ) ) {
	$stats['error_alert'] = str_replace( "'", '', $state['step']['last_status'] );
}


// Database size.
if ( 0 == $state['stats']['tables_total_size'] ) {
	$stats['database_size_pretty'] = __( 'Calculating size...', 'it-l10n-backupbuddy' );
} else {
	$stats['database_size_bytes'] = $state['stats']['tables_total_size'];
	$stats['database_size_pretty'] = pb_backupbuddy::$format->file_size( $state['stats']['tables_total_size'] );
}


// Calculate tables pending deletion.
$stats['database_tables_pending_delete'] = $state['stats']['tables_pending_delete'];


// Database tables sent.
$stats['database_tables_sent'] = ( $state['stats']['tables_total_count'] - $state['stats']['tables_pending_send'] );
$stats['database_tables_pending_send'] = $state['stats']['tables_pending_send'];


// Database tables total.
$stats['database_tables_total'] = $state['stats']['tables_total_count'];


// DB Live activity.
$db_live_activity_time = backupbuddy_live::get_db_live_activity_time();
if ( $db_live_activity_time < $state['stats']['last_db_snapshot'] ) {
	$db_live_activity_time = $state['stats']['last_db_snapshot'];
}
if ( -1 != $db_live_activity_time ) {
	$stats['last_database_live_activity'] = $db_live_activity_time;
	$stats['last_database_live_activity_pretty'] = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $db_live_activity_time ) );
	$stats['last_database_live_activity_ago'] = pb_backupbuddy::$format->time_ago( $db_live_activity_time ) . ' ' . __( 'ago', 'it-l10n-backupbuddy' );
}


// Tables percent sent (by count).
if ( $state['stats']['tables_total_count'] > 0 ) {
	$stats['database_tables_sent_percent'] = ceil( ( $stats['database_tables_sent'] / $state['stats']['tables_total_count'] ) * 100 );
	if ( ( 100 == $stats['database_tables_sent_percent'] ) && ( $stats['database_tables_sent'] < $state['stats']['tables_total_count'] ) ) { // If we were to display 100% sent but files still remain, convert to 99.9% to help indicate the gap.
		$stats['database_tables_sent_percent'] = 99.9;
	}
} else {
	$stats['database_tables_sent_percent'] = 0;
}


// Files total size.
if ( 0 == $state['stats']['files_total_size'] ) {
	$stats['files_size_pretty'] = 'Calculating size...';
	if ( ! empty( $state['stats']['files_to_catalog_percentage'] ) ) {
		$stats['files_size_pretty'] .= ' ' . $state['stats']['files_to_catalog_percentage'] . '%';
	}
} else {
	$stats['files_size_bytes'] = $state['stats']['files_total_size'];
	$stats['files_size_pretty'] = pb_backupbuddy::$format->file_size( $state['stats']['files_total_size'] );
}


// Files sent.
$stats['files_total'] = $state['stats']['files_total_count'];
$stats['files_sent'] = ( $state['stats']['files_total_count'] - $state['stats']['files_pending_send'] );


// Files percent sent (by count).
if ( $state['stats']['files_total_count'] > 0 ) {
	$stats['files_sent_percent'] = ceil( ( $stats['files_sent'] / $state['stats']['files_total_count'] ) * 100 );
	if ( ( 100 == $stats['files_sent_percent'] ) && ( $stats['files_sent'] < $state['stats']['files_total_count'] ) ) { // If we were to display 100% sent but files still remain, convert to 99.9% to help indicate the gap.
		$stats['files_sent_percent'] = 99.9;
	}
} else {
	$stats['files_sent_percent'] = 0;
}
$stats['overall_percent'] = round( ( $stats['files_sent_percent'] + $stats['database_tables_sent_percent'] ) / 2, 1 );


// Calculate files pending deletion.
$stats['files_pending_delete'] = $state['stats']['files_pending_delete'];


// Last periodic activity.
$stats['last_periodic_activity'] = $state['stats']['last_activity'];
if ( 0 != $state['stats']['last_activity'] ) {
	$stats['last_periodic_activity_pretty'] = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $state['stats']['last_activity'] ) );
	$stats['last_periodic_activity_ago'] =  pb_backupbuddy::$format->time_ago( $state['stats']['last_activity'] ) . ' ' . __( 'ago', 'it-l10n-backupbuddy' );
}


// Last file audit.
$stats['last_file_audit_start'] = $state['stats']['last_file_audit_start'];
$stats['last_file_audit_finish'] = $state['stats']['last_file_audit_finish'];


// Last db snapshot.
$stats['last_db_snapshot'] = $state['stats']['last_db_snapshot'];
if ( 0 != $state['stats']['last_db_snapshot'] ) {
	$stats['last_db_snapshot_pretty'] = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $state['stats']['last_db_snapshot'] ) );
	$stats['last_db_snapshot_ago'] = pb_backupbuddy::$format->time_ago( $state['stats']['last_db_snapshot'] ) . ' ' . __( 'ago', 'it-l10n-backupbuddy' );
}


// Last remote snapshot.
$stats['last_remote_snapshot_id'] = $state['stats']['last_remote_snapshot_id'];
$stats['last_remote_snapshot'] = $state['stats']['last_remote_snapshot'];
if ( 0 != $state['stats']['last_remote_snapshot'] ) {
	$stats['last_remote_snapshot_pretty'] = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $state['stats']['last_remote_snapshot'] ) );
	$stats['last_remote_snapshot_ago'] = pb_backupbuddy::$format->time_ago( $state['stats']['last_remote_snapshot'] ) . ' ' . __( 'ago', 'it-l10n-backupbuddy' );
}
$stats['last_remote_snapshot_trigger'] = $state['stats']['last_remote_snapshot_trigger'];
$stats['last_remote_snapshot_response'] = $state['stats']['last_remote_snapshot_response'];
$stats['last_remote_snapshot_response_time'] = $state['stats']['last_remote_snapshot_response_time'];


// Remote snapshot interval.
if ( isset( $schedule_intervals[ $destination['remote_snapshot_period'] ] ) ) {
	$stats['remote_snapshot_interval'] = $schedule_intervals[ $destination['remote_snapshot_period'] ]['interval'];
	$stats['remote_snapshot_interval_pretty'] = $schedule_intervals[ $destination['remote_snapshot_period'] ]['display'];
}


// Current function.
$stats['current_function'] = $state['step']['function'];
$stats['current_function_pretty'] = backupbuddy_live::pretty_function( $state['step']['function'] );
if ( 'database_snapshot' == $state['step']['function'] ) { // For db dump include current table count.
	if ( count( $state['step']['args'] ) > 0 ) {
		$total_tables = count( $state['step']['args'][0] );
		$remaining_tables = count( $state['step']['args'][1] );
		$sent_tables = $total_tables - $remaining_tables;
		$stats['current_function_pretty'] .= ' (' . $sent_tables . ' / ' .$total_tables . ' ' . __( 'tables', 'it-l10n-backupbuddy' ) . ')';
	}
}


// Periodic interval.
if ( isset( $schedule_intervals[ $destination['periodic_process_period'] ] ) ) {
	$stats['periodic_interval'] = $schedule_intervals[ $destination['periodic_process_period'] ]['interval'];
	$stats['periodic_interval_pretty'] = $schedule_intervals[ $destination['periodic_process_period'] ]['display'];
	
	$next_periodic = $state['stats']['last_activity'] + $stats['periodic_interval']; // Periodic won't run until at LEAST this timestamp.
	$next_remote_snapshot = $state['stats']['last_remote_snapshot'] + $stats['remote_snapshot_interval']; // Remote snapshot won't run until at LEAST this timestamp.
	
	if ( FALSE !== ( $next_live_scheduled = wp_next_scheduled( 'backupbuddy_cron', array( 'live', array() ) ) ) ) { // Live is scheduled.
		
		// Calculate approx time schedule will run that will be long enough into the future to trigger periodic.
		$next_live_periodic = $next_live_scheduled;
		while ( $next_live_periodic < $next_periodic ) {
			$next_live_periodic += 60*60;; // Keep increasing the schedule by 1hr until it surpasses the minimum periodic time run timestamp.
		}
		
		$stats['next_db_snapshot'] = $next_live_periodic;
		$stats['next_db_snapshot_pretty'] = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $next_live_periodic ) );
		$stats['next_periodic_restart'] = $next_live_periodic;
		$stats['next_periodic_restart_pretty'] = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $next_live_periodic ) );
		
		// Calculate approx time schedule will run that will be long enough into the future to trigger snapshot.
		$next_live_snapshot = $next_live_scheduled;
		while ( $next_live_snapshot < $next_remote_snapshot ) {
			$next_live_snapshot += 60*60; // Keep increasing the schedule by the live cron interval until it surpasses the minimum periodic time run timestamp.
		}
		
		$stats['next_remote_snapshot'] = $next_live_snapshot;
		$stats['next_remote_snapshot_pretty'] = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $next_live_snapshot ) );
	}
	
	// If periodic looks to be running now, say so.
	$last_activity_ago = microtime( true ) - $state['stats']['last_activity'];
	if ( $last_activity_ago < ( backupbuddy_core::adjustedMaxExecutionTime() + 60 ) ) {
		$stats['next_periodic_restart'] = '-1';
		$stats['next_periodic_restart_pretty'] = __( 'In Progress...', 'it-l10n-backupbuddy' );
	}
	
	// If db process running, say so.
	if ( ( 'database_snapshot' == $state['step']['function'] ) || ( 'send_pending_db_snapshots' == $state['step']['function'] ) || ( 'process_table_deletions' == $state['step']['function'] ) ) {
		$stats['next_db_snapshot'] = '-1';
		$stats['next_db_snapshot_pretty'] = __( 'In Progress...', 'it-l10n-backupbuddy' );
	}
}


// First completion.
$stats['first_completion'] = $state['stats']['first_completion'];
if ( 0 != $stats['first_completion'] ) {
	$stats['first_completion_pretty'] = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $state['stats']['first_completion'] ) );
	$stats['first_completion_ago'] = pb_backupbuddy::$format->time_ago( $state['stats']['first_completion'] );
}



// Get daily stats.
$stats['daily_stats'] = $state['stats']['daily'];


// Include Stash stats. Note that these are cached.
$stats['stash'] = backupbuddy_live::getStashQuota( $bust_cache = false );



require_once( pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php' );
$dailyStatsRef = backupbuddy_live_periodic::_get_daily_stats_ref();

