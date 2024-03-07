<?php
/**
 * Run PHP Runtime Test
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::load();

// Schedule to run.
$cron_args       = array( false, true );
$schedule_result = backupbuddy_core::schedule_single_event( time() + 60, 'php_runtime_test', $cron_args );
if ( ! empty( $schedule_result ) ) {
	pb_backupbuddy::status( 'details', 'PHP runtime test cron event scheduled.' );
} else {
	pb_backupbuddy::status( 'error', 'PHP runtime test cron event FAILED to be scheduled.' );
}

backupbuddy_core::maybe_spawn_cron();

die( esc_html__( 'This may take a few minutes...', 'it-l10n-backupbuddy' ) );
