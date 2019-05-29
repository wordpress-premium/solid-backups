<?php
/**
 * WordPress Dashboard Widget for BackupBuddy
 *
 * @package BackupBuddy
 */

?>
<?php if ( false !== backupbuddy_live::getLiveID() ) : ?>
	<div class="tabs clearfix">
		<button class="tab-toggle stash-live selected">Stash Live</button>
		<button class="tab-toggle traditional">Traditional</button>
	</div>
	<div class="stash-live-wrapper"><div class="spinner is-active"></div></div>
	<script type="text/template" class="backupbuddy-stash-live-dashboard-widget-tmpl">
		<div class="backupbuddy-live-stats-currently">
			<span class="backupbuddy-pulsing-orb"></span>
			<div class="backupbuddy-currently-message">
				<span class="backupbuddy-inline-label"><?php esc_html_e( 'Currently', 'it-l10n-backupbuddy' ); ?></span>: {{ stats.current_function_pretty }}
			</div>
		</div>
		<div class="backupbuddy-live-stats-overview">
			<h3><?php esc_html_e( 'BackupBuddy Stash Live requested new snapshot files', 'it-l10n-backupbuddy' ); ?>:</h3>
			<div class="backupbuddy-stats-time-ago">{{ stats.last_remote_snapshot_ago }}</div>

			<div class="backupbuddy-stats-overview-manage-live backup-now">
				<a href="<?php echo esc_url( $stashlive_url ); ?>" class="backupbuddy-live-button secondary"><?php esc_html_e( 'Manage Stash Live', 'it-l10n-backupbuddy' ); ?></a>
			</div>
		</div>
	</script>
<?php endif; ?>

<div class="traditional-backup-wrapper hidden mode-<?php echo esc_attr( $edits_widget_mode ); ?>">
	<?php if ( 'advanced' === $get_overview['editsTrackingMode'] && is_array( $get_overview['advancedEditsSinceLastBackup'] ) ) : ?>
		<div class="recent-edits-dashboard-toggle">
			<input type="radio" name="recent-edit-dashboard-widget" value="basic" id="recent-edit-dashboard-widget-basic" <?php checked( 'basic', $edits_widget_mode ); ?>>
			<label for="recent-edit-dashboard-widget-basic"><?php esc_html_e( 'Basic', 'it-l10n-backupbuddy' ); ?></label>
			<input type="radio" name="recent-edit-dashboard-widget" value="advanced" id="recent-edit-dashboard-widget-advanced" <?php checked( 'advanced', $edits_widget_mode ); ?>>
			<label for="recent-edit-dashboard-widget-advanced"><?php esc_html_e( 'Advanced', 'it-l10n-backupbuddy' ); ?></label>
			<span class="toggle-outside"><span class="toggle-inside"></span></span>
		</div>
	<?php endif; ?>

	<div class="edits-since-wrapper basic-mode">
		<p class="edits-since <?php echo esc_attr( $basic_status ); ?>">
			<?php echo esc_html( $get_overview['editsSinceLastBackup'] ); ?>
		</p>

		<h4 class="number-heading">
			<?php if ( 0 == $get_overview['editsSinceLastBackup'] ) : ?>
				<?php echo $edits_number_caption; ?>
			<?php else : ?>
				<a href="#backupbuddy-recent-edits"><?php echo $edits_number_caption; ?></a>
			<?php endif; ?>
		</h4>
	</div>

	<?php if ( 'advanced' === $get_overview['editsTrackingMode'] && is_array( $get_overview['advancedEditsSinceLastBackup'] ) ) : ?>
		<div class="edits-since-wrapper advanced-mode">
			<div class="advanced-edits-total all-edits">
				<p class="total-edits-since <?php echo esc_attr( $status_all ); ?>">
					<?php echo esc_html( $get_overview['advancedEditsSinceLastBackup']['all_edits'] ); ?>
				</p>
				<h5 class="number-heading">
					<?php if ( 0 == $get_overview['advancedEditsSinceLastBackup']['all_edits'] ) : ?>
						Total File/Database Changes<br>Since Last Backup
					<?php else : ?>
						<a href="#backupbuddy-recent-edits">Total File/Database Changes<br>Since Last Backup</a>
					<?php endif; ?>
				</h5>
			</div>
			<div class="advanced-edits-total post-changes">
				<p class="total-edits-since <?php echo esc_attr( $status_posts ); ?>">
					<?php echo esc_html( $get_overview['advancedEditsSinceLastBackup']['post_edits'] ); ?>
				</p>
				<h5 class="number-heading">
					<?php if ( 0 == $get_overview['advancedEditsSinceLastBackup']['post_edits'] ) : ?>
						Post Changes<br>Since Last Backup
					<?php else : ?>
						<a href="#backupbuddy-recent-edits" rel="post">Post Changes<br>Since Last Backup</a>
					<?php endif; ?>
				</h5>
			</div>
			<div class="advanced-edits-total plugin-changes">
				<p class="total-edits-since <?php echo esc_attr( $status_plugins ); ?>">
					<?php echo esc_html( $get_overview['advancedEditsSinceLastBackup']['plugin_edits'] ); ?>
				</p>
				<h5 class="number-heading">
					<?php if ( 0 == $get_overview['advancedEditsSinceLastBackup']['plugin_edits'] ) : ?>
						Plugin Changes<br>Since Last Backup
					<?php else : ?>
						<a href="#backupbuddy-recent-edits" rel="plugin">Plugin Changes<br>Since Last Backup</a>
					<?php endif; ?>
				</h5>
			</div>
			<div class="advanced-edits-total option-changes">
				<p class="total-edits-since <?php echo esc_attr( $status_options ); ?>">
					<?php echo esc_html( $get_overview['advancedEditsSinceLastBackup']['option_edits'] ); ?>
				</p>
				<h5 class="number-heading">
					<?php if ( 0 == $get_overview['advancedEditsSinceLastBackup']['option_edits'] ) : ?>
						Options/Settings Changes<br>Since Last Backup
					<?php else : ?>
						<a href="#backupbuddy-recent-edits" rel="option">Options/Settings Changes<br>Since Last Backup</a>
					<?php endif; ?>
				</h5>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( isset( $get_overview['lastBackupStats']['finish'] ) ) { // only show if a last backup exists. ?>
		<div class="info-group">
			<h3>Latest Backup</h3>
			<ul class="backup-list">
				<li>
					<div class="list-wrapper">
						<div class="list-title">
							<?php echo $last_backup_title; // @codingStandardsIgnoreLine: ok. ?>
						</div>
						<div class="list-description">
							<div class="backup-type description-item">
								<span>Type</span><br>
								<?php echo esc_html( $backup_type ); ?>
							</div>
							<div class="backup-size description-item">
								<span>Size</span><br>
								<?php echo esc_html( $archive_size ); ?>
							</div>
							<div class="backup-time description-item">
								<span>Time</span><br>
								<?php echo esc_html( $time_nice ); ?>
							</div>
						</div>
					</div>
				</li>
			</ul>
		</div>
	<?php } ?>

	<div class="backup-now">
		<a href="<?php echo esc_url( $backup_url ); ?>"><?php esc_html_e( 'Backup Now', 'it-l10n-backupbuddy' ); ?></a>
	</div>
</div>

<?php if ( false !== backupbuddy_live::getLiveID() ) : ?>
	<script>
		function backupbuddy_live_dashboard_stats( stats ) {
			_.templateSettings.variable    = 'stats';
			_.templateSettings.evaluate    = /<#([\s\S]+?)#>/g;
			_.templateSettings.interpolate = /\{\{\{([\s\S]+?)\}\}\}/g;
			_.templateSettings.escape      = /\{\{([^\}]+?)\}\}(?!\})/g;
			var liveTemplate = _.template( jQuery( '#pb_backupbuddy_stats .backupbuddy-stash-live-dashboard-widget-tmpl' ).html() );
			jQuery('#pb_backupbuddy_stats .stash-live-wrapper' ).html( liveTemplate( stats ) );
		}

		jQuery(document).ready( function() {
			backupbuddy_live_dashboard_stats( jQuery.parseJSON( '<?php echo json_encode( backupbuddy_api::getLiveStats() ); ?>' ) ); // Initial stats to prevent loading from showing.
		});
	</script>
	<?php require_once pb_backupbuddy::plugin_path() . '/destinations/live/_statsPoll.php'; ?>
<?php endif; ?>

<script>
	jQuery(document).ready( function() {
		// UI for toggling the tabs
		jQuery( '#pb_backupbuddy_stats .tab-toggle' ).on( 'click', function( e ) {
			e.preventDefault();
			if ( jQuery(this).hasClass( 'stash-live' ) ) {
				jQuery(this).addClass('selected').siblings().removeClass('selected');
				jQuery( '#pb_backupbuddy_stats .stash-live-wrapper').removeClass('hidden');
				jQuery( '#pb_backupbuddy_stats .traditional-backup-wrapper').addClass('hidden');
			} else if ( jQuery(this).hasClass( 'traditional' ) ) {
				jQuery(this).addClass('selected').siblings().removeClass('selected');
				jQuery( '#pb_backupbuddy_stats .traditional-backup-wrapper').removeClass('hidden');
				jQuery( '#pb_backupbuddy_stats .stash-live-wrapper').addClass('hidden');
			}
		});

		// Click edit count to see recent edits.
		jQuery( 'a[href$="#backupbuddy-recent-edits"]' ).on( 'click', function(e) {
			e.preventDefault();
			var rel = jQuery( this ).attr( 'rel' ) ? '&rel=' + jQuery( this ).attr( 'rel' ) : '';
			tb_show( 'BackupBuddy', '<?php echo pb_backupbuddy::ajax_url( 'recent_edits' ); ?>' + rel + '&TB_iframe=1&width=640&height=455', null );
			return false;
		});

		jQuery( '.recent-edits-dashboard-toggle' ).on( 'click', function(){
			var val = jQuery('input[name="recent-edit-dashboard-widget"]:checked').val();
			jQuery.ajax({
				url: '<?php echo pb_backupbuddy::ajax_url( 'update_user_dashboard_widget' ); ?>',
				method: 'post',
				data: { mode: val }
			});
			if ( 'advanced' === val ) {
				jQuery( '.traditional-backup-wrapper' ).removeClass( 'mode-basic' ).addClass( 'mode-advanced' );
			} else {
				jQuery( '.traditional-backup-wrapper' ).removeClass( 'mode-advanced' ).addClass( 'mode-basic' );
			}
		});

		<?php if ( false === backupbuddy_live::getLiveID() ) : ?>
			jQuery( '#pb_backupbuddy_stats .traditional-backup-wrapper').removeClass('hidden');
		<?php endif; ?>
	});
</script>
