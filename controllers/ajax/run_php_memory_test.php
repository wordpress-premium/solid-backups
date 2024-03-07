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
$schedule_result = backupbuddy_core::trigger_async_event( 'php_memory_test', $cron_args );

if ( true === $schedule_result ) {
	pb_backupbuddy::status( 'details', 'PHP memory test cron event scheduled.' );
} else {
	pb_backupbuddy::status( 'error', 'PHP memory test cron event FAILED to be scheduled.' );
}

backupbuddy_core::maybe_spawn_cron();

die( esc_html__( 'This may take a few minutes...', 'it-l10n-backupbuddy' ) );
