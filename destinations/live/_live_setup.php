<?php
/**
 * Solid Backups Stash Live Setup & Configuration -- shown on first run
 *
 * @author Dustin Bolton
 * @since 7.0
 * @package BackupBuddy
 */

pb_backupbuddy::$ui->title( __( 'Solid Backups Stash Live', 'it-l10n-backupbuddy' ), true, false, 'backupbuddy-stash-live-icon' );
wp_print_styles( 'backupbuddy-core' );

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

			backupbuddy_loading_spinner = setTimeout( function(){ jQuery( '.pb_backupbuddy_destpicker_saveload' ).removeClass( 'hidden' ) }, 500 );
			jQuery.post( '<?php echo pb_backupbuddy::ajax_url( 'live_setup' ); ?>', jQuery(this).serialize(),
				function(data) {
					clearTimeout( backupbuddy_loading_spinner );
					jQuery( '.pb_backupbuddy_destpicker_saveload' ).addClass( 'hidden' );
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

<div class="backupbuddy-live-setup-container">
	<div class="backupbuddy-live-setup-main postbox">
		<div class="backupbuddy-live-setup-header">
			<div class="backupbuddy-live-setup-header-section" >
				<img src="<?php echo pb_backupbuddy::plugin_url(); ?>/assets/dist/images/stash-live-logo.svg" <?php echo 'alt="', __( 'Solid Backups Stash Live', 'it-l10n-backupbuddy' ), '" title="', __( 'Solid Backups Stash Live', 'it-l10n-backupbuddy' ), '"'; ?> alt=""/>
				<h4><?php _e( 'Real-time, cloud-based backups directly to your Backups Stash Storage.', 'it-l10n-backupbuddy' ); ?></h4>
			</div>
			<a href="https://go.solidwp.com/solid-backups-stash-live" class="button button-primary backupbuddy-live-button blue" target="_new"><?php _e( 'Learn More', 'it-l10n-backupbuddy' ); ?></a>
		</div>

		<form class="backupbuddy_live_setup_form solid-backups-form">
			<?php pb_backupbuddy::nonce( true ); ?>

			<div class="backupbuddy-live-setup-login backupbuddy-live-setup-fieldset">
				<div class="backupbuddy-live-setup-login-field">
					<label><?php _e( 'SolidWP Username', 'it-l10n-backupbuddy' ); ?><?php pb_backupbuddy::tip( __( 'This is the same username and password you used to purchase your SolidWP products and to log in to the SolidWP Member Panel.', 'it-l10n-backupbuddy' ) ); ?></label>
					<input type="text" name="live_username"
						   placeholder="<?php echo esc_attr( __( 'Username', 'it-l10n-backupbuddy' ) ); ?>"/>
				</div>
				<div class="backupbuddy-live-setup-login-field">
					<label><?php _e( 'SolidWP Password', 'it-l10n-backupbuddy' ); ?></label>
					<input type="password" name="live_password"
						   placeholder="<?php echo esc_attr( __( 'Password', 'it-l10n-backupbuddy' ) ); ?>"/>
				</div>
			</div>

			<div class="backupbuddy-live-setup-storage-settings backupbuddy-live-setup-fieldset">
				<h4 class="solid-subtitle-small">
					<?php _e( 'Backup Storage Settings', 'it-l10n-backupbuddy' ); ?>
				</h4>

				<p>
					<?php _e( 'Stash Live will create Backup files in ZIP format and store them in your Solid Backups Stash Account. By default we are storing <strong>5 daily, 2 weekly, and 1 monthly</strong> database backups and <strong>1 daily, 1 weekly, and 1 monthly</strong> for full backups.', 'it-l10n-backupbuddy' ); ?>
					<a href="#"
					   class="backupbuddy-live-setup-toggle-storage-settings"><?php _e( 'Modify Limits', 'it-l10n-backupbuddy' ); ?></a>
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
								'db'      => __( 'Database Backups', 'it-l10n-backupbuddy' ),
								'full'    => __( 'Full Backups', 'it-l10n-backupbuddy' ),
								'plugins' => __( 'Plugins Backups', 'it-l10n-backupbuddy' ),
								'themes'  => __( 'Themes Backups', 'it-l10n-backupbuddy' ),
							);

							$archive_periods = array(
								'daily',
								'weekly',
								'monthly',
								'yearly',
							);

							foreach ( $archive_types as $archive_type => $archive_type_name ) {
								echo '<tr>';
								echo '<td class="label">' . $archive_type_name . '</td>';
								foreach ( $archive_periods as $archive_period ) {
									$settings_name = 'limit_' . $archive_type . '_' . $archive_period;
									echo '<td><input type="text" class="small backupbuddy_live_archive_limit" name="' . 'limit_' . $archive_type . '_' . $archive_period . '" value="' . pb_backupbuddy_destination_live::$default_settings[ $settings_name ] . '" data-default="' . pb_backupbuddy_destination_live::$default_settings[ $settings_name ] . '" /></td>';
								}
								echo '</tr>';
							}
						?>

						</tbody>
					</table>
					<div class="backupbuddy-live-setup-storage-settings-details-footer">
						<span class="description"><?php esc_html_e( 'Set blank for unlimited backups of a type or 0 (zero) to limit to none.', 'it-l10n-backupbuddy' ); ?></span>
						<button type="button" id="backupbuddy-live-storage-settings-restore-defaults"
								class="button button-secondary backupbuddy-live-button secondary"><?php _e( 'Restore Defaults', 'it-l10n-backupbuddy' ); ?></button>
					</div>
					<script>
						jQuery(document).ready(function () {
							jQuery('#backupbuddy-live-storage-settings-restore-defaults').on('click', function (e) {
								e.preventDefault();

								jQuery('.backupbuddy_live_archive_limit').each(
									function () {
										jQuery(this).val(jQuery(this).attr('data-default'));
									}
								);
							});
						});
					</script>
				</div>
			</div>

			<div class="backupbuddy-live-setup-email-settings backupbuddy-live-setup-fieldset">
				<p>
				<div class="backupbuddy-live-setup-email-settings-details-checkbox">
					<label>
						<input type="checkbox" name="send_snapshot_notification"
							   id="backupbuddy_live_setup_email_setting"
							   value="1" <?php if ( '1' == pb_backupbuddy_destination_live::$default_settings['send_snapshot_notification'] ) {
							echo 'checked="checked"';
						}; ?> />
						<?php esc_html_e( 'Send me an email when new Backup downloads are available to:', 'it-l10n-backupbuddy' ); ?>
					</label>
				</div>
				<div class="backupbuddy-live-setup-email-settings-details">
					<label>
						<span class="screen-reader-text"><?php _e( 'Stash Live Email Address', 'it-l10n-backupbuddy' ); ?></span>
						<input type="email" id="backupbuddy-live-setup-email-address" name="email"
							   value="<?php echo $email; ?>" placeholder="Use SolidWP Account Email" size="23"/>
					</label>
				</div>
				<p class="description"><?php _e( 'We’ll automatically send you an email when the first Snapshot completes, but you may choose to continue receiving emails every time new ZIPs are created. ', 'it-l10n-backupbuddy' ); ?></p>
				<p class="backupbuddy-live-setup-email-warning"><?php _e( 'Note: by turning off Snapshot notification emails, you lose access to your Stash Live Snapshot downloads via email in the event you go over your Stash storage quota.', 'it-l10n-backupbuddy' ); ?></p>
				</p>
			</div>

			<div class="backupbuddy-live-setup-submit backupbuddy-live-setup-fieldset">
				<button class="backupbuddy_live_setup_button button button-primary button-no-ml"><?php _e( 'Save Settings and Start a Backup', 'it-l10n-backupbuddy' ); ?></button>
				<div class="pb_backupbuddy_destpicker_saveload hidden"></div>
			</div>
		</form>
		<script>
			(function ($) {
				$(document).ready(function () {
					// toggles open/close the storage settings
					$('.backupbuddy-live-setup-toggle-storage-settings').on('click', function (e) {
						e.preventDefault();
						$('.backupbuddy-live-setup-storage-settings-details').toggle();
					});

					// toggles open/close the email warning
					$('#backupbuddy_live_setup_email_setting').on('change', function () {
						if ($(this).prop('checked')) {
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
		<div class="backupbuddy-live-setup-sidebar-section">
			<h3><?php _e( 'Getting Started with Stash Live', 'it-l10n-backupbuddy' ); ?></h3>
			<p><?php _e( 'Stash Live is a whole new way to backup your WordPress sites. You might have questions like:', 'it-l10n-backupbuddy' ); ?></p>

			<ul>
				<li><a href="https://go.solidwp.com/stash-live-faqs-live-work/faqs/#live-work"
					   target="_blank"><?php _e( 'How does Stash Live work?', 'it-l10n-backupbuddy' ); ?></a></li>
				<li><a href="https://go.solidwp.com/stash-live-faqs-traditional-vs-live"
					   target="_blank"><?php _e( 'How is this different than traditional Solid Backups backups?', 'it-l10n-backupbuddy' ); ?></a>
				</li>
				<li><a href="https://go.solidwp.com/stash-live-faqs-backed-up"
					   target="_blank"><?php _e( 'What gets backed up?', 'it-l10n-backupbuddy' ); ?></a></li>
				<li><a href="https://go.solidwp.com/stash-live-faqs-download-live-backups"
					   target="_blank"><?php _e( 'How do I download my backups?', 'it-l10n-backupbuddy' ); ?></a></li>
			</ul>
			<a href="https://go.solidwp.com/solid-stash-live-faqs/" target="_new"
			   class="button button-secondary button-no-ml"><?php _e( 'Learn more', 'it-l10n-backupbuddy' ); ?></a>
		</div>
		<div class="backupbuddy-live-setup-sidebar-section">
			<h3><?php _e( 'Where will I manage my Stash Storage?', 'it-l10n-backupbuddy' ); ?></h3>
			<p><?php _e( 'Once Stash Live is enabled you can view, download, and delete your Solid Backups Stash backups from the Solid Central dashboard. From Solid Central, you can also get a look at your current Stash storage usage and upgrade your plan.', 'it-l10n-backupbuddy' ); ?></p>
			<a href="https://go.solidwp.com/solid-stash-central-account" target="_new"
			   class="button button-secondary button-no-ml"><?php _e( 'Login to Solid Central', 'it-l10n-backupbuddy' ); ?></a>
		</div>
	</div>
</div>
