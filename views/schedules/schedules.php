<?php
/**
 * Schedules Listing
 *
 * @package BackupBuddy
 */

require_once pb_backupbuddy::plugin_path() . '/classes/class-backupbuddy-schedules.php';

// Manually Run Schedule.
if ( ! empty( pb_backupbuddy::_GET( 'run' ) ) ) {
	$id = intval( pb_backupbuddy::_GET( 'run' ) );

	if ( isset( pb_backupbuddy::$options['schedules'][ $id ] ) ) {
		$schedule = pb_backupbuddy::$options['schedules'][ $id ];

		// If not disabled.
		if ( ! BackupBuddy_Schedules::schedule_is_disabled( $id ) ) {
			$alert_text = sprintf(
				__( 'Manually running scheduled backup "%s" in the background.', 'it-l10n-backupbuddy' ),
				$schedule['title']
			);
			pb_backupbuddy::alert( wpautop( $alert_text ), false, '', '', '', array( 'class' => 'below-h2' ) );
			pb_backupbuddy_cron::_run_scheduled_backup( $id );
		}
	}
}

pb_backupbuddy::disalert( 'schedule_limit_reminder', '<p><span class="pb_label">Tip</span> ' . __( 'Keep old backups from piling up by configuring "Local Archive Storage Limits" on the Settings page.', 'it-l10n-backupbuddy' ) ) . '</p>';

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
				__( 'Run Time', 'it-l10n-backupbuddy' ) . pb_backupbuddy::tip( __( 'First run indicates the first time this schedule ran or will run.  Last run time is the last time that this scheduled backup started. This does not imply that the backup completed, only that it began at this time. The last run time is reset if the schedule is edited. Next run indicates when it is next scheduled to run. If there is no server activity during this time the schedule will be delayed.', 'it-l10n-backupbuddy' ), '', false ),
				__( 'Status', 'it-l10n-backupbuddy' ),
			),
			'hover_actions' => array(
				'edit' => 'Edit Schedule',
				'run'  => 'Run Now',
			),
			'bulk_actions'  => array(
				'delete_schedule' => 'Delete'
			),
			'css'                      => 'width: 100%;',
			'disable_top_bulk_actions' => true,
			'hide_edit_for_first_schedule' => apply_filters( 'itbub_disable_first_edit_schedule_link', false ),
		)
	);
	echo '<br>';

	// May need to delete the below code. -John Regan, 2023-02-20.
	?>
	<div class="table-description description screen-reader-text">
		Note: Due to the way schedules are triggered in WordPress your site must be accessed (frontend or admin area) for scheduled backups to occur.
		WordPress scheduled events ("crons") may be viewed or run manually for testing from the <a href="?page=pb_backupbuddy_diagnostics">Diagnostics page</a>.
		A <a href="https://go.solidwp.com/search-free-website-uptime" target="_blank">free website uptime</a> service or <a href="https://go.solidwp.com/search-free-website-uptime" target="_blank">Solid Central Pro's Uptime Monitoring</a> can be used to automatically access your site regularly to help trigger scheduled actions ("crons") in cases of low site activity, with the added perk of keeping track of your site uptime.
	</div>
	<?php
} elseif ( pb_backupbuddy::_GET( 'edit' ) ) {
	$back_button = '<a href="' . esc_attr( pb_backupbuddy::page_url() ) . '" class="button button-secondary">&larr; ' . esc_html__( 'Back', 'it-l10n-backupbuddy' ) . '</a>';
	$schedule_form->display_settings( __( 'Save Schedule', 'it-l10n-backupbuddy' ), '<div class="solid-backups-form-buttons form-buttons">' .  $back_button, '</div>' );

} else {
	echo '<p>' . esc_html__( 'No backup schedules setup yet.', 'it-l10n-backupbuddy' ) . ' <a href="' . esc_attr( pb_backupbuddy::page_url() ) . '&tab=add">' . esc_html__( 'Click here to set one up!', 'it-l10n-backupbuddy' ) . '</a></p>';
}
