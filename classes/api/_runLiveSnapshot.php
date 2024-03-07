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
		( ( 'daily_init' !== $state['step']['function'] ) && ( ( time() - $state['stats']['last_activity'] ) < backupbuddy_core::adjustedMaxExecutionTime() ) )
	) {
	set_transient( 'backupbuddy_live_snapshot', true, 60 * 60 * 48 ); // Request transient to run.
	pb_backupbuddy::status( 'details', 'Snapshot requested to be ran.' );

	backupbuddy_core::maybe_spawn_cron();
} else {
	backupbuddy_live_periodic::_set_next_step( 'daily_init', array( $manual_snapshot = true ), $save_now_and_unlock = true );
	backupbuddy_live_periodic::schedule_next_event();
} // end backed up 100%.


return true;

