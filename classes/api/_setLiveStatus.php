<?php
// Incoming vars: $pause_continuous, $pause_periodic, $start_run.

require_once( pb_backupbuddy::plugin_path() . '/destinations/live/live.php' );
if ( false === ( $destination_id = backupbuddy_live::getLiveID() ) ) { // $destination_id used by _stats.php.
	return false;
}

$saving = false;


/***** BEGIN CONTINOUS *****/
if ( false === $pause_continuous ) { // Unpause.
	pb_backupbuddy::$options['remote_destinations'][$destination_id]['pause_continuous'] = '0';
	$saving = true;
} elseif ( true === $pause_continuous ) { // Pause.
	pb_backupbuddy::$options['remote_destinations'][$destination_id]['pause_continuous'] = '1';
	$saving = true;
}
/***** END CONTINOUS *****/


/***** BEGIN PERIODIC *****/
if ( false === $pause_periodic ) { // Unpause.
	$prior_periodic_status = pb_backupbuddy::$options['remote_destinations'][$destination_id]['pause_periodic'];
	pb_backupbuddy::$options['remote_destinations'][$destination_id]['pause_periodic'] = '0';
	if ( '1' == $prior_periodic_status ) { // Was paused, now unpaused, so check if we need to run now.
		if ( true === $start_run ) {
			pb_backupbuddy::save(); // Must save prior to spawning.
			backupbuddy_live_periodic::schedule_next_event();
		}
	}
	$saving = true;
} elseif ( true === $pause_periodic ) { // Pause.
	pb_backupbuddy::$options['remote_destinations'][ $destination_id ]['pause_periodic'] = '1';
	$saving = true;
}
/***** END PERIODIC *****/


// Save settings.
if ( true === $saving ) {
	pb_backupbuddy::save();
}


// Return status stats.
if ( '1' == pb_backupbuddy::$options['remote_destinations'][ $destination_id ]['pause_continuous'] ) {
	$continuous_status = '0';
} else {
	$continuous_status = '1';
}

if ( '1' == pb_backupbuddy::$options['remote_destinations'][$destination_id]['pause_periodic'] ) {
	$periodic_status = '0';
} else {
	$periodic_status = '1';
}


return array(
	'continuous_status' => $continuous_status,
	'periodic_status' => $periodic_status
);

