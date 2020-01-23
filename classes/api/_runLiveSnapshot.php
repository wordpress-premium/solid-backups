<?php
// Incoming vars: -NONE-

require_once( pb_backupbuddy::plugin_path() . '/destinations/live/live.php' );
if ( false === ( $destination_id = backupbuddy_live::getLiveID() ) ) { // $destination_id used by _stats.php.
	return false;
}


require_once( pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php' );
$state = backupbuddy_live_periodic::get_stats();


// If currently running.
if (
		( ( 'daily_init' != $state['step']['function'] ) && ( ( time()-$state['stats']['last_activity'] ) < backupbuddy_core::adjustedMaxExecutionTime() ) )
  ) {
	set_transient( 'backupbuddy_live_snapshot', true, 60*60*48 ); // Request transient to run.
	pb_backupbuddy::status( 'details', 'Snapshot requested to be ran.' );
	
	if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
		pb_backupbuddy::status( 'details', 'Spawning cron now.' );
		update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
		spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
	}
} else {
	backupbuddy_live_periodic::_set_next_step( 'daily_init', array( $manual_snapshot = true ), $save_now_and_unlock = true );
	$schedule_result = backupbuddy_core::schedule_single_event( time(), 'live_periodic', $cronArgs = array() );
	if ( true === $schedule_result ) {
		pb_backupbuddy::status( 'details', 'Next Live Periodic chunk step cron event scheduled.' );
	} else {
		pb_backupbuddy::status( 'error', 'Next Live Periodic chunk step cron event FAILED to be scheduled.' );
	}
	if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
		pb_backupbuddy::status( 'details', 'Spawning cron now.' );
		update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
		spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
	}
} // end backed up 100%.


return true;

