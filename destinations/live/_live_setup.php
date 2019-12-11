<?php
/* BackupBuddy Stash Live Setup & Configuration -- shown on first run
 *
 * @author Dustin Bolton
 * @since 7.0
 *
 */
 
pb_backupbuddy::$ui->title( __( 'BackupBuddy Stash Live', 'it-l10n-backupbuddy' ) );
pb_backupbuddy::load_style( 'backupbuddy_live.css' );

require_once( pb_backupbuddy::plugin_path() . '/destinations/live/init.php' );


if ( '' != pb_backupbuddy::$options['email_notify_scheduled_complete'] ) {
	$email = pb_backupbuddy::$options['email_notify_scheduled_complete'];
} elseif ( '' != pb_backupbuddy::$options['email_notify_error'] ) {
	$email = pb_backupbuddy::$options['email_notify_error'];
} elseif ( '' != get_option('admin_email') ) {
	$email = get_option('admin_email');
} else {
	$email = '';
}
?>

<script>
	jQuery(document).ready(function() {
		jQuery( '.backupbuddy_live_setup_form' ).submit( function(e) {
			e.preventDefault();
			
			backupbuddy_loading_spinner = setTimeout( function(){ jQuery( '.pb_backupbuddy_destpicker_saveload' ).show() }, 500 );
			jQuery.post( '<?php echo pb_backupbuddy::ajax_url( 'live_setup' ); ?>', jQuery(this).serialize(), 
				function(data) {
					clearTimeout( backupbuddy_loading_spinner );
					jQuery( '.pb_backupbuddy_destpicker_saveload' ).hide();
					data = jQuery.trim( data );
					
					if ( data == 'Success.' ) {
						<?php
						if ( is_network_admin() ) {
							?>
							window.top.location.href = '<?php echo network_admin_url( 'admin.php' ); ?>?page=pb_backupbuddy_live';
							<?php
						} else {
							?>
							window.top.location.href = '<?php echo admin_url( 'admin.php' ); ?>?page=pb_backupbuddy_live';
							<?php
						}
						?>
						return false;
					} else {
						alert( "Error #5001: \n\n" + data );
					}
					
				}
			);
		});
	});
</script>


<div class="backupbuddy-live-setup-main postbox">
	<div class="backupbuddy-live-setup-header">
		<img src="<?php echo pb_backupbuddy::plugin_url(); ?>/images/stash-live-logo.png" <?php echo 'alt="', __('BackupBuddy Stash Live', 'it-l10n-backupbuddy' ),'" title="',__('BackupBuddy Stash Live', 'it-l10n-backupbuddy' ),'"';?> />
		<h4><?php _e( 'Real-time, cloud-based backups directly to your BackupBuddy Stash Storage.', 'it-l10n-backupbuddy' ); ?></h4>
		<a href="https://ithemes.com/stash-live" class="backupbuddy-live-button blue" target="_new"><?php _e( 'Learn More', 'it-l10n-backupbuddy' ); ?></a>
	</div>
	
	<form class="backupbuddy_live_setup_form">
		<?php pb_backupbuddy::nonce( true ); ?>
		
		<div class="backupbuddy-live-setup-login backupbuddy-live-setup-fieldset">
			<div class="backupbuddy-live-setup-login-field">
				<label><?php _e( 'iThemes Username', 'it-l10n-backupbuddy' ); ?><?php pb_backupbuddy::tip( __( 'This is the same username and password you used to purchase your iThemes products and to log in to the iThemes Member Panel.', 'it-l10n-backupbuddy' ) ); ?></label>
				<input type="text" name="live_username" />
			</div>
			<div class="backupbuddy-live-setup-login-field">
				<label><?php _e( 'Password', 'it-l10n-backupbuddy' ); ?></label>
				<input type="password" name="live_password" />
			</div>
		</div>
		
		<div class="backupbuddy-live-setup-storage-settings backupbuddy-live-setup-fieldset">
			<h4>
				<?php _e( 'Backup Storage Settings', 'it-l10n-backupbuddy' ); ?>
			</h4>
			
			<p>
				<?php _e( 'Stash Live will create Backup files in ZIP format and store them in your BackupBuddy Stash Account. By default we are storing <strong>5 daily, 2 weekly, and 1 monthly</strong> database backups and <strong>1 daily, 1 weekly, and 1 monthly</strong> for full backups.', 'it-l10n-backupbuddy' ); ?>
				<a href="#" class="backupbuddy-live-setup-toggle-storage-settings"><?php _e( 'Modify Limits', 'it-l10n-backupbuddy' ); ?></a>
			</p>
			
			<div class="backupbuddy-live-setup-storage-settings-details">
				<table>
					<tbody>
						<tr>
							<th class="label"><?php _e( 'Type', 'it-l10n-backupbuddy' ); ?></th>
							<th><?php _e( 'Daily', 'it-l10n-backupbuddy' ); ?></th>
							<th><?php _e( 'Weekly', 'it-l10n-backupbuddy' ); ?></th>
							<th><?php _e( 'Monthly', 'it-l10n-backupbuddy' ); ?></th>
							<th><?php _e( 'Yearly', 'it-l10n-backupbuddy' ); ?></th>
						</tr>
						
						<?php
						$archive_types = array(
							'db' => __( 'Database Backups', 'it-l10n-backupbuddy' ),
							'full' => __( 'Full Backups', 'it-l10n-backupbuddy' ),
							'plugins' => __( 'Plugins Backups', 'it-l10n-backupbuddy' ),
							'themes' => __( 'Themes Backups', 'it-l10n-backupbuddy' ),
						);
						
						$archive_periods = array(
							'daily',
							'weekly',
							'monthly',
							'yearly',
						);
						
						foreach( $archive_types as $archive_type => $archive_type_name ) {
							echo '<tr>';
							echo '<td class="label">' . $archive_type_name . '</td>';
							foreach( $archive_periods as $archive_period ) {
								$settings_name = 'limit_' . $archive_type . '_' . $archive_period;
								echo '<td><input type="text" class="small backupbuddy_live_archive_limit" name="' . 'limit_' . $archive_type . '_' . $archive_period . '" value="' . pb_backupbuddy_destination_live::$default_settings[ $settings_name ] . '" data-default="' . pb_backupbuddy_destination_live::$default_settings[ $settings_name ] . '" /></td>';
							}
							echo '</tr>';
						}
						?>
						
					</tbody>
				</table>
				<span class="description" style="display: inline-block; margin-top: 11px; margin-left: 3px;">Set blank for unlimited backups of a type or 0 (zero) to limit to none.</span>
				<button type="button" id="backupbuddy-live-storage-settings-restore-defaults" class="backupbuddy-live-button secondary"><?php _e( 'Restore Defaults', 'it-l10n-backupbuddy' ); ?></button>
				<script>
					jQuery(document).ready( function() {
						jQuery('#backupbuddy-live-storage-settings-restore-defaults').on( 'click', function(e){
							e.preventDefault();
							
							jQuery( '.backupbuddy_live_archive_limit' ).each(
								function(){
									jQuery(this).val( jQuery(this).attr( 'data-default' ) );
								}
							);
						});
					});
				</script>
			</div>
		</div>
		
		<div class="backupbuddy-live-setup-email-settings backupbuddy-live-setup-fieldset">
			<h4>
				<?php _e( 'Email Settings', 'it-l10n-backupbuddy' ); ?>
			</h4>
			<p>
				<label>
					<input type="checkbox" name="send_snapshot_notification" id="backupbuddy_live_setup_email_setting" value="1" <?php if ( '1' == pb_backupbuddy_destination_live::$default_settings['send_snapshot_notification'] ) { echo 'checked="checked"'; }; ?> />
					<?php _e( 'Send me an email when new Backup downloads are available to:', 'it-l10n-backupbuddy' ); ?>
				</label>
				<label>
					<span class="screen-reader-text"><?php _e('Stash Live Email Address', 'it-l10n-backupbuddy' ); ?></span>
					<input type="email" id="backupbuddy-live-setup-email-address" name="email" value="<?php echo $email; ?>" placeholder="Use iThemes Account Email" size="23"/>
				</label>
				<p class="description"><?php _e ( 'We’ll automatically send you an email when the first Snapshot completes, but you may choose to continue receiving emails every time new ZIPs are created. ', 'it-l10n-backupbuddy' ); ?></p>
				<p class="backupbuddy-live-setup-email-warning"><?php _e( 'Note: by turning off Snapshot notification emails, you lose access to your Stash Live Snapshot downloads via email in the event you go over your Stash storage quota.', 'it-l10n-backupbuddy' ); ?></p>
			</p>
		</div>
		
		<div class="backupbuddy-live-setup-submit backupbuddy-live-setup-fieldset">
			<button class="backupbuddy_live_setup_button backupbuddy-live-button red"><?php _e( 'Save Settings & Start Backup', 'it-l10n-backupbuddy' ); ?></button>
			<img class="pb_backupbuddy_destpicker_saveload" src="<?php echo pb_backupbuddy::plugin_url(); ?>/images/loading.gif" title="Saving... This may take a few seconds..." style="display: none; margin-left: 12px; vertical-align: -3px;">
		</div>
	</form>
	<script>
		(function($){
			$(document).ready( function() {
				// toggles open/close the storage settings
				$( '.backupbuddy-live-setup-toggle-storage-settings' ).on( 'click', function(e) {
					e.preventDefault();
					$( '.backupbuddy-live-setup-storage-settings-details' ).toggle();
				});

				// toggles open/close the email warning
				$( '#backupbuddy_live_setup_email_setting' ).on( 'change' , function() {
					if ( $(this).prop('checked') ) {
						$('.backupbuddy-live-setup-email-warning').hide();
					} else {
						$('.backupbuddy-live-setup-email-warning').show();
					}
				});
			});
		})(jQuery);
	</script>
</div>

<div class="backupbuddy-live-setup-sidebar">
	<div>
		<h3><?php _e( 'Getting Started with Stash Live', 'it-l10n-backupbuddy' ); ?></h3>
		<p><?php _e( 'Stash Live is a whole new way to backup your WordPress sites. You might have questions like:', 'it-l10n-backupbuddy' ); ?></p>
		
		<ul>
			<li><em><a href="https://ithemes.com/stash-live/faqs/#live-work" target="_blank"><?php _e( 'How does Stash Live work?', 'it-l10n-backupbuddy' ); ?></a></em></li>
			<li><em><a href="https://ithemes.com/stash-live/faqs/#traditional-vs-live" target="_blank"><?php _e( 'How is this different than traditional BackupBuddy backups?', 'it-l10n-backupbuddy' ); ?></a></em></li>
			<li><em><a href="https://ithemes.com/stash-live/faqs/#backed-up" target="_blank"><?php _e( 'What gets backed up?', 'it-l10n-backupbuddy' ); ?></a></em></li>
			<li><em><a href="https://ithemes.com/stash-live/faqs/#download-live-backups" target="_blank"><?php _e( 'How do I download my backups?', 'it-l10n-backupbuddy' ); ?></a></em></li>
		</ul>
		<a href="https://ithemes.com/stash-live/faqs/" target="_new" class="backupbuddy-live-button secondary"><?php _e( 'Learn more', 'it-l10n-backupbuddy' ); ?></a>
	</div>
	<div>
		<h3><?php _e( 'Where will I manage my Stash Storage?', 'it-l10n-backupbuddy' ); ?></h3>
		<p><?php _e( 'Once Stash Live is enabled you can view, download, and delete your BackupBuddy Stash backups from the iThemes Sync dashboard. From Sync, you can also get a look at your current Stash storage usage and upgrade your plan.', 'it-l10n-backupbuddy' ); ?></p>
		<a href="https://sync.ithemes.com/stash" target="_new" class="backupbuddy-live-button secondary"><?php _e( 'Login to iThemes Sync', 'it-l10n-backupbuddy' ); ?></a>
	</div>
</div>