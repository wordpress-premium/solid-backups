<?php
/**
 * Diagnostics > Cron tab Action Scheduler partial.
 *
 * This does not display all Action Scheduler Actions on the site.
 * It only shows the *Pending* Action Scheduler scheduled actions for Solid Backups.
 *
 * @package BackupBuddy
 */

?>
<h3><?php esc_html_e( 'Pending Actions', 'it-l10n-backupbuddy' ); ?></h3>
<?php
echo wp_kses_post(
	wpautop(
		sprintf(
			__( 'These Solid Backups actions are scheduled to run in the future. A full list of completed, failed, or in-process actions on your site is found <a href="%s">here</a>.', 'it-l10n-backupbuddy' ),
			menu_page_url( 'action-scheduler', false )
		)
	)
);

pb_backupbuddy::$ui->list_table(
	bb_diagnostics_get_actions_rows(),
	array(
		'css'           => 'width: 100%;',
		'wrapper_class' => 'backupbuddy-cron-diagnostics-actions',
		'columns'       => array(
			__( 'Arguments', 'it-l10n-backupbuddy' ),
			__( 'Status', 'it-l10n-backupbuddy' ),
			__( 'Recurrence', 'it-l10n-backupbuddy' ),
			__( 'Scheduled Date', 'it-l10n-backupbuddy' ),
			__( 'Log', 'it-l10n-backupbuddy' ),
		),
	)
);
