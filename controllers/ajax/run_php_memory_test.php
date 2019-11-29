<?php
/**
 * Run PHP Memory Test AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::load();

// Schedule to run.
$cron_args       = array( false, true );
$schedule_result = backupbuddy_core::schedule_single_event( time(), 'php_memory_test', $cron_args );

if ( true === $schedule_result ) {
	pb_backupbuddy::status( 'details', 'PHP memory test cron event scheduled.' );
} else {
	pb_backupbuddy::status( 'error', 'PHP memory test cron event FAILED to be scheduled.' );
}

if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
	pb_backupbuddy::status( 'details', 'Spawning cron now.' );
	update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
	spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
}

die( esc_html__( 'This may take a few minutes...', 'it-l10n-backupbuddy' ) );
