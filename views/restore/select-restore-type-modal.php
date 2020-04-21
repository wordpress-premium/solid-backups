<?php
/**
 * Select Full Restore Type Modal Content
 *
 * @package BackupBuddy
 */

?>
<div id="backupbuddy-select-restore-type" style="display:none;">
	<p><?php esc_html_e( 'Please select what you want restored from this backup.', 'it-l10n-backupbuddy' ); ?></p>
	<ul class="full-restore-options">
		<li>
			<label>
				<input type="radio" name="full-restore-type" value="both" checked="checked">
				<span class="label">
					<strong><?php esc_html_e( 'Entire Backup', 'it-l10n-backupbuddy' ); ?></strong>
					<em><?php esc_html_e( 'Restore this entire backup.' ); ?></em>
				</span>
			</label>
			<label>
				<input type="radio" name="full-restore-type" value="db">
				<span class="label">
					<strong><?php esc_html_e( 'Database Only', 'it-l10n-backupbuddy' ); ?></strong>
					<em><?php esc_html_e( 'Restore only the database from this backup.' ); ?></em>
				</span>
			</label>
			<label>
				<input type="radio" name="full-restore-type" value="files">
				<span class="label">
					<strong><?php esc_html_e( 'Files Only', 'it-l10n-backupbuddy' ); ?></strong>
					<em><?php esc_html_e( 'Restore only the files from this backup.' ); ?></em>
				</span>
			</label>
		</li>
	</ul>
</div>
