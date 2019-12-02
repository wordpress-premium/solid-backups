<?php
/**
 * Restore Status view
 *
 * @package BackupBuddy
 */

$restores_url = admin_url( 'admin.php?page=pb_backupbuddy_diagnostics&tab=recent' ) . '#backupbuddy-restore-history';
$dismiss_url  = admin_url( 'admin.php?page=pb_backupbuddy_backup&tab=restore-backup&bub_rand=' . rand( 100, 999 ) ) . '#restore-backup';
$site_url     = site_url();
$status_attr  = '';
if ( isset( $restore_in_progress ) && false !== $restore_in_progress ) {
	$status_attr .= ' class="open in-progress"';
	$status_attr .= sprintf( ' data-restore-id="%s"', esc_attr( $restore_in_progress ) );
}
?>
<div id="backupbuddy-restore-status"<?php echo $status_attr; ?>>
	<div class="restore-status-container">
		<div class="restore-active">
			<h2><?php esc_html_e( 'Backup Restore in progress', 'it-l10n-backupbuddy' ); ?></h2>
			<p>
				Restoring may take a few minutes.<br>
				Feel free to close or navigate away from this screen.
			</p>
			<!--<p class="em">We will email you when restoration is complete.</p>-->

			<div class="restore-progress">
				<ul class="progress-dots">
					<li class="started" title="<?php echo esc_attr( __( 'Start', 'it-l10n-backupbuddy' ) ); ?>"></li>
					<li class="downloading" title="<?php echo esc_attr( __( 'Download', 'it-l10n-backupbuddy' ) ); ?>"></li>
					<li class="unzipping" title="<?php echo esc_attr( __( 'Unzip', 'it-l10n-backupbuddy' ) ); ?>"></li>
					<li class="database" title="<?php echo esc_attr( __( 'Database', 'it-l10n-backupbuddy' ) ); ?>"></li>
					<li class="restoring" title="<?php echo esc_attr( __( 'Restore', 'it-l10n-backupbuddy' ) ); ?>"></li>
					<li class="complete" title="<?php echo esc_attr( __( 'Finish', 'it-l10n-backupbuddy' ) ); ?>"></li>
				</ul>
			</div>
			<div class="restore-current-step">Getting Status...</div>
			<p><a href="#abort-restore" class="abort-link"><?php esc_html_e( 'Abort' ); ?></a></p>
		</div>
		<div class="restore-done" style="display: none;">
			<h2 class="status-complete"><?php esc_html_e( 'Site Restored', 'it-l10n-backupbuddy' ); ?></h2>
			<h2 class="status-aborted" style="display:none;"><?php esc_html_e( 'Restore Aborted', 'it-l10n-backupbuddy' ); ?></h2>
			<h2 class="status-failed" style="display:none;"><?php esc_html_e( 'Restore Failed', 'it-l10n-backupbuddy' ); ?></h2>

			<p><a href="<?php echo esc_attr( $site_url ); ?>" class="button button-primary status-complete" target="_blank"><?php esc_html_e( 'Visit Site' ); ?></a>
				<a href="<?php echo esc_attr( $restores_url ); ?>" class="button button-secondary"><?php esc_html_e( 'View Restores' ); ?></a></p>

			<p><a href="<?php echo esc_attr( $dismiss_url ); ?>" class="dismiss-link"><?php esc_html_e( 'Dismiss' ); ?></a></p>
		</div>
	</div>
</div>
