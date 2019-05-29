<?php
/* BackupBuddy Stash Live Manual Snapshot GUI
 *
 * @author Dustin Bolton
 * @since 7.0
 *
 */
?>

<script>
	jQuery(document).ready( function(){
		window.backupbuddy_live_snapshot_request_time = <?php echo microtime( true ); ?>;
		
		jQuery( '.backupbuddy-live-snapshot-complete-downloads a' ).click( function(){
			if ( snapshot_infected_warning ) {
				if ( ! confirm( '<?php _e( 'The snapshot was detected to contain one or more forms of malware or virus. Are you sure you wish to proceed to download?', 'it-l10n-backupbuddy' ); ?>' ) ) {
					return false;
				}
			}
			return true;
		});
	});
</script>




<div id="message" style="display: none;" rel="backupbuddy_live_snapshot_initiating" class="pb_backupbuddy_alert updated fade">
	<h3>
		<?php _e( 'Initiating Snapshot', 'it-l10n-backupbuddy' ); ?>
	</h3>
	<span class="description" style="max-width: 700px; display: inline-block;">
		<?php _e( 'Please wait a moment for the Snapshot to begin...', 'it-l10n-backupbuddy' ); ?>
	</span>
	<br><br>
</div>






<div class="pb_backupbuddy_alert updated pb_backupbuddy_alert_snapshot" id="backupbuddy_live_snapshot-working" style="display: none; padding: 9px;">
	<?php echo '<h3>' . __( 'Snapshot in Progress', 'it-l10n-backupbuddy' ) . '</h3><span class="description" style="max-width: 700px; display: inline-block;">' . __( 'This may take a few minutes. The status below will update as the snapshot progresses.', 'it-l10n-backupbuddy' ) . '</span>' ?>
	<br><br>
	
	<b>Current Status:</b><br>
	<span id="backupbuddy_live_snapshot-working-status"><!-- XXX --></span>
</div>

<div class="pb_backupbuddy_alert postbox backupbuddy-live-snapshot-complete-container" id="backupbuddy_live_snapshot-success" style="padding:0!important">
	<div class="backupbuddy-live-snapshot-complete-header">
		<div class="pb_backupbuddy_disalert">
			<a class="pb_backupbuddy_disalert" href="#" title="<?php _e( 'Dismiss', 'it-l10n-backupbuddy' ); ?>"><strong><?php _e( 'Dismiss', 'it-l10n-backupbuddy' ); ?></strong></a>
		</div>
		<h3><span class="dashicons dashicons-yes"></span><?php _e( 'Snapshot Complete', 'it-l10n-backupbuddy' ); ?></h3>
	</div>
	
	<p class="backupbuddy-live-snapshot-complete-message">
		<span id="backupbuddy_live_snapshot-stashed" style="display: none;">
			<?php _e( 'This snapshot has also been copied to your BackupBuddy Stash storage based on your settings for long-term storage.', 'it-l10n-backupbuddy' ); ?>
		</span>
		<?php _e( 'You may download the snapshot files below (links valid for 24 hours) which may be used just like a normal BackupBuddy backup for restoring with the ImportBuddy Restore Tool.', 'it-l10n-backupbuddy' ); ?>
	</p>
	
	<div class="backupbuddy-live-snapshot-complete-downloads">
		<h4><?php _e( 'Downloads', 'it-l10n-backupbuddy' ); ?></h4>
		<ul>
			<li id="backupbuddy_live_snapshot-success-backup_full"><a href="" class="backupbuddy-live-button primary"><?php _e( 'Full Backup', 'it-l10n-backupbuddy' ); ?></a></li>
			<li id="backupbuddy_live_snapshot-success-backup_db"><a href="" class="backupbuddy-live-button primary"><?php _e( 'Database Backup', 'it-l10n-backupbuddy' ); ?></a></li>
			<li id="backupbuddy_live_snapshot-success-backup_themes"><a href="" class="backupbuddy-live-button secondary"><?php _e( 'Themes Only', 'it-l10n-backupbuddy' ); ?></a></li>
			<li id="backupbuddy_live_snapshot-success-backup_plugins"><a href="" class="backupbuddy-live-button secondary"><?php _e( 'Plugins Only', 'it-l10n-backupbuddy' ); ?></a></li>
		</ul>
		<ul>
			<li id="backupbuddy_live_snapshot-success-backup_importbuddy"><a href="" class="backupbuddy-live-button primary blue"><?php _e( 'ImportBuddy Restore Tool', 'it-l10n-backupbuddy' ); ?></a></li>
		</ul>
	</div>
	
	<div class="backupbuddy-live-snapshot-complete-malware-results">
		<h4><?php _e( 'Malware & Virus Scan Results', 'it-l10n-backupbuddy' ); ?></h4>
		<ul id="backupbuddy_live_snapshot-success-malware">
			<li><span class="backupbuddy_live_malware_label">Scanned Directories</span><span class="backupbuddy_live_malware_result" data-result="scanned_directories"></span></li>
			<li><span class="backupbuddy_live_malware_label">Scanned Files</span><span class="backupbuddy_live_malware_result" data-result="scanned_files"></span></li>
			<li><span class="backupbuddy_live_malware_label">Infected Files</span><span class="backupbuddy_live_malware_result" data-result="infected_files"></span></li>
		</ul>
		<div id="backupbuddy_live_snapshot-success-malware-files" style="display: none; margin-top: 15px;">
			<h4 style="color: red;"><?php _e( 'Infected Files', 'it-l10n-backupbuddy' ); ?></h4>
			<ul></ul>
		</div>
	</div>
</div>



