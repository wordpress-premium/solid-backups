<?php
/**
 * Schedules Listing
 *
 * @package BackupBuddy
 */

/***** BEGIN MANUALLY RUNNING SCHEDULE */
if ( '' != pb_backupbuddy::_GET( 'run' ) ) {
	$alert_text  = __( 'Manually running scheduled backup', 'it-l10n-backupbuddy' );
	$alert_text .= ' "' . pb_backupbuddy::$options['schedules'][ pb_backupbuddy::_GET( 'run' ) ]['title'] . '" ';
	$alert_text .= __( 'in the background.', 'it-l10n-backupbuddy' ) . '<br>';
	$alert_text .= __( 'Note: If there is no site activity there may be delays between steps in the backup. Access the site or use a 3rd party service, such as a free pinging service, to generate site activity.', 'it-l10n-backupbuddy' );
	pb_backupbuddy::alert( $alert_text, false, '', '', '', array( 'class' => 'below-h2' ) );
	pb_backupbuddy_cron::_run_scheduled_backup( (int) pb_backupbuddy::_GET( 'run' ) );
}
/***** END MANUALLY RUNNING SCHEDULE */

pb_backupbuddy::disalert( 'schedule_limit_reminder', '<span class="pb_label">Tip</span> ' . __( 'Keep old backups from piling up by configuring "Local Archive Storage Limits" on the Settings page.', 'it-l10n-backupbuddy' ) );

if ( count( $schedules ) > 0 && '' == pb_backupbuddy::_GET( 'edit' ) ) {
	pb_backupbuddy::$ui->list_table(
		$schedules,
		array(
			'action'        => pb_backupbuddy::page_url() . '&tab=edit-schedule',
			'columns'       => array(
				__( 'Title', 'it-l10n-backupbuddy' ),
				__( 'Profile', 'it-l10n-backupbuddy' ),
				__( 'Interval', 'it-l10n-backupbuddy' ),
				__( 'Destinations', 'it-l10n-backupbuddy' ),
				__( 'Run Time', 'it-l10n-backupbuddy' ) . pb_backupbuddy::tip( __( 'First run indicates the first time thie schedule ran or will run.  Last run time is the last time that this scheduled backup started. This does not imply that the backup completed, only that it began at this time. The last run time is reset if the schedule is edited. Next run indicates when it is next scheduled to run. If there is no server activity during this time the schedule will be delayed.', 'it-l10n-backupbuddy' ), '', false ),
				__( 'Status', 'it-l10n-backupbuddy' ),
			),
			'hover_actions' => array(
				'edit' => 'Edit Schedule',
				'run'  => 'Run Now',
			),
			'bulk_actions'  => array(
				'delete_schedule' => 'Delete'
			),
			'css'           => 'width: 100%;',
			'hide_edit_for_first_schedule' => apply_filters( 'itbub_disable_first_edit_schedule_link', false ),
		)
	);
	echo '<br>';
	?>
	<div class="description">
		<strong>Note</strong>: Due to the way schedules are triggered in WordPress your site must be accessed (frontend or admin area) for scheduled backups to occur.
		WordPress scheduled events ("crons") may be viewed or run manually for testing from the <a href="?page=pb_backupbuddy_diagnostics">Diagnostics page</a>.
		A <a href="https://www.google.com/search?q=free+website+uptime&oq=free+website+uptime" target="_blank">free website uptime</a> service or <a href="https://ithemes.com/sync-pro/uptime-monitoring/" target="_blank">iThemes Sync Pro's Uptime Monitoring</a> can be used to automatically access your site regularly to help trigger scheduled actions ("crons") in cases of low site activity, with the added perk of keeping track of your site uptime.
	</div>
	<?php
} elseif ( pb_backupbuddy::_GET( 'edit' ) ) {
	printf( '<h1>%s</h1>', esc_html__( 'Edit Schedule', 'it-l10n-backupbuddy' ) );
	$schedule_form->display_settings( 'Save Schedule' );
	echo '<span style="display: inline-block; position: relative; margin-left: 130px; top: -28px;"><a href="' . pb_backupbuddy::page_url() . '" class="button secondary-button">&larr; ' . esc_html__( 'back', 'it-l10n-backupbuddy' ) . '</a>';
}
