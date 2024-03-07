<?php
/**
 * Live Stats AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

// Check if running PHP 5.3+.
if ( version_compare( PHP_VERSION, 5.3, '<' ) ) { // Server's PHP is insufficient.
	die( '-1' );
}

// Prevent this request and future requests during a restore.
if ( backupbuddy_restore()->in_progress() ) {
	die( '-1' );
}

$stats = backupbuddy_api::getLiveStats();
if ( false === $stats ) { // Live is disconnected.
	die( '-1' );
}

echo json_encode( $stats );

// If there is more to do and too long of time has passed since activity then try to jumpstart the process at the beginning.
if ( 'wait_on_transfers' != $stats['current_function'] && ( 0 == $stats['files_total'] || $stats['files_sent'] < $stats['files_total'] ) ) { // ( Files to send not yet calculated OR more remain to send ) AND not on the wait_on_transfers step.
	$time_since_last_activity = microtime( true ) - $stats['last_periodic_activity'];

	// Don't even bother getting max execution time if it's been less than 30 seconds since run.
	if ( $time_since_last_activity >= 30 ) { // More than 30 seconds since last activity.

		// Detect max PHP execution time. If TESTED value is higher than PHP value then go with that since we want to err on not overlapping processes here.
		$detected_execution = backupbuddy_core::detectLikelyHighestExecutionTime();

		if ( $time_since_last_activity > ( $detected_execution + backupbuddy_constants::TIMED_OUT_PROCESS_RESUME_WIGGLE_ROOM ) ) { // Enough time has passed to assume timed out.

			require_once pb_backupbuddy::plugin_path() . '/destinations/live/live.php';
			$live_id = backupbuddy_live::getLiveID();
			if ( false === $live_id ) {
				die( '-1' );
			}
			if ( '1' != pb_backupbuddy::$options['remote_destinations'][ $live_id ]['pause_periodic'] ) { // Only proceed if NOT paused.
				pb_backupbuddy::status( 'warning', 'Stash Live process is either running or timed out while user is viewing Live page. Checking schedule now.' );
			}
		}
	}
}

die();
