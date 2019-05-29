<?php
require_once( pb_backupbuddy::plugin_path() . '/destinations/live/live.php' );
if ( false === ( $destination_id = backupbuddy_live::getLiveID() ) ) { // $destination_id used by _stats.php.
	return false;
}

require_once( pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php' );
$state = backupbuddy_live_periodic::get_stats();


include(  pb_backupbuddy::plugin_path() . '/destinations/live/_stats.php' );


return $stats; // Set in _stats.php.