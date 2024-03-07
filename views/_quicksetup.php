<?php
/**
 * Quick Setup View
 *
 * @package BackupBuddy
 */

if ( ! current_user_can( pb_backupbuddy::$options['role_access'] ) ) {
	die( 'Access Denied. Error 445543454754.' );
}
wp_enqueue_script( 'thickbox' );
wp_print_scripts( 'thickbox' );
wp_print_styles( 'thickbox' );
// Handles thickbox auto-resizing. Keep at bottom of page to avoid issues.
if ( ! wp_script_is( 'media-upload' ) ) {
	wp_enqueue_script( 'media-upload' );
	wp_print_scripts( 'media-upload' );
}
?>
<script type="text/javascript">
	(function ($) {
		$(document).ready( function() {

			// If Live pre-selected, show checkmark.
			if ( 'live' == $( '#pb_backupbuddy_quickstart_destination' ).val() ) {
				$( '#pb_backupbuddy_quickstart_destination_check' ).show();
			}

			$( '#pb_backupbuddy_quickstart_password, #pb_backupbuddy_quickstart_passwordconfirm' ).keyup( function() {
				if ( ( $( '#pb_backupbuddy_quickstart_password' ).val() != '' ) && ( $( '#pb_backupbuddy_quickstart_password' ).val() == $( '#pb_backupbuddy_quickstart_passwordconfirm' ).val() ) ) {
					$( '#pb_backupbuddy_quickstart_password_check_fail,#pb_backupbuddy_quickstart_password_check_fail > img' ).hide();
					$( '#pb_backupbuddy_quickstart_password_check' ).show();
				} else {
					$( '#pb_backupbuddy_quickstart_password_check' ).hide();
					if ( ( $( '#pb_backupbuddy_quickstart_password' ).val() != '' ) || ( $( '#pb_backupbuddy_quickstart_passwordconfirm' ).val() != '' ) ) { // Mismatch non-blank.
						$( '#pb_backupbuddy_quickstart_password_check_fail,#pb_backupbuddy_quickstart_password_check_fail > img' ).show();
					} else if ( ( $( '#pb_backupbuddy_quickstart_password' ).val() == '' ) && ( $( '#pb_backupbuddy_quickstart_passwordconfirm' ).val() == '' ) ) { // both blank
						$( '#pb_backupbuddy_quickstart_password_check_fail,#pb_backupbuddy_quickstart_password_check_fail > img' ).hide();
					}
				}
			} );

			$( '#pb_backupbuddy_quickstart_email' ).change( function() {
				if ( ( $(this).val() != '' ) && ( $(this).val().indexOf( '@' ) >= 0 ) ) {
					$( '#pb_backupbuddy_quickstart_email_check' ).show();
				} else {
					$( '#pb_backupbuddy_quickstart_email_check' ).hide();
				}
			});

			/* Show success checkmark if pre-filled email looks valid. */
			quickstart_email = $( '#pb_backupbuddy_quickstart_email' ).val();
			if ( ( quickstart_email != '' ) && ( quickstart_email.indexOf( '@' ) >= 0 ) ) {
				$( '#pb_backupbuddy_quickstart_email_check' ).show();
			}

			$( '#pb_backupbuddy_quickstart_destination' ).change( function() {
				if ( $(this).val() == 'stash3' ) { // Stash (v3).
					$( '.stash-fields' ).slideDown();
					$( '#pb_backupbuddy_quickstart_form .schedule' ).slideDown();
					return; // Skip destination picker for Stash (v3).
				} else if (  $(this).val() == 'live' ) { // Stash Live (as of v7).
					$( '.stash-fields' ).slideUp();
					$( '#pb_backupbuddy_quickstart_form .schedule' ).slideUp();
					return; // Skip destination picker for Stash Live (redirected after submission).
				} else { // Other destination.
					$( '.stash-fields' ).slideUp();
					$( '#pb_backupbuddy_quickstart_form .schedule' ).slideDown();
				}
				if ( $(this).val() != '' ) {
					tb_show( 'Solid Backups', '<?php echo pb_backupbuddy::ajax_url( 'destination_picker' ); ?>&quickstart=true&add=' + $(this).val() + '&filter=' + $(this).val() + '&callback_data=&sending=0&TB_iframe=1&width=640&height=455', null );
				}
			});

			$( '#pb_backupbuddy_quickstart_stashuser' ).change( function() {
				if ( ( $(this).val() != '' ) && ( $( '#pb_backupbuddy_quickstart_stashpass' ).val() != '' ) ) {
					pb_backupbuddy_stashtest();
				}
			});
			$( '#pb_backupbuddy_quickstart_stashpass' ).change( function() {
				if ( ( $(this).val() != '' ) && ( $( '#pb_backupbuddy_quickstart_stashuser' ).val() != '' ) ) {
					pb_backupbuddy_stashtest();
				}
			});

			$( '#pb_backupbuddy_quickstart_destination' ).change( function() {
				if ( $(this).val() == '' ) {
					$( '#pb_backupbuddy_quickstart_destination_check' ).hide();
				}
			});

			$( '#pb_backupbuddy_quickstart_schedule' ).change( function() {
				if ( $(this).val() != '' ) {
					$( '#pb_backupbuddy_quickstart_schedule_check' ).show();
				} else {
					$( '#pb_backupbuddy_quickstart_schedule_check' ).hide();
				}
			});

			$( '#pb_backupbuddy_quickstart_form' ).submit( function() {
				$( '#pb_backupbuddy_quickstart_saveloading' ).show();
				console.log( $(this).serialize() );
				$.post( '<?php echo pb_backupbuddy::ajax_url( 'quickstart_form' ); ?>', $(this).serialize(),
					function(data) {
						$( '#pb_backupbuddy_quickstart_saveloading' ).hide();
						data = $.trim( data );

						if ( data == 'Success.' ) {
							if ( 'live' == $( '#pb_backupbuddy_quickstart_destination' ).val() ) {
								<?php
								if ( is_network_admin() ) {
									?>
									window.top.location.href = '<?php echo network_admin_url( 'admin.php' ); ?>?page=pb_backupbuddy_live&quickstart_wizard=true';
									<?php
								} else {
									?>
									window.top.location.href = '<?php echo admin_url( 'admin.php' ); ?>?page=pb_backupbuddy_live&quickstart_wizard=true';
									<?php
								}
								?>
							} else {
								<?php
								if ( is_network_admin() ) {
									?>
									window.top.location.href = '<?php echo network_admin_url( 'admin.php' ); ?>?page=pb_backupbuddy_backup&quickstart_wizard=true';
									<?php
								} else {
									?>
									window.top.location.href = '<?php echo admin_url( 'admin.php' ); ?>?page=pb_backupbuddy_backup&quickstart_wizard=true';
									<?php
								}
								?>
							}
							return false;
						} else {
							alert( "Error: \n\n" + data );
						}

					}
				);

				return false;
			});
		});

		function pb_backupbuddy_quickstart_destinationselected( dest_id ) {
			alert( __( 'Destination added successfully! Close this dialog to return to Quick Start Setup.', 'it-l10n-backupbuddy' ) );
			if ( $( '#pb_backupbuddy_quickstart_destination' ).val() != '' ) {
				$( '#pb_backupbuddy_quickstart_destination_check' ).show();
				$( '#pb_backupbuddy_quickstart_destinationid' ).val( dest_id );
			} else {
				$( '#pb_backupbuddy_quickstart_destination_check' ).hide();
			}
		}

		function pb_backupbuddy_stashtest() {
			$( '#pb_backupbuddy_quickstart_stashloading' ).show();
			$.post( '<?php echo pb_backupbuddy::ajax_url( 'quickstart_stash_test' ); ?>', {
					user: $( '#pb_backupbuddy_quickstart_stashuser' ).val(),
					pass: $( '#pb_backupbuddy_quickstart_stashpass' ).val()
				},
				function(data) {
					$( '#pb_backupbuddy_quickstart_stashloading' ).hide();
					data = $.trim( data );
					alert( data );
				}
			);
		}

	})(jQuery);


</script>

<p class="solid-backups-quickstart-intro">
	<?php echo wp_kses_post(
		sprintf(
			__( 'Complete this optional wizard to start using Solid Backups right away. See the <a href="%s">Settings</a> page for all configuration options.', 'it-l10n-backupbuddy' ),
			'admin.php?page=pb_backupbuddy_settings'
		)
	); ?>
</p>

<form id="pb_backupbuddy_quickstart_form" class="solid-backups-quickstart-form solid-backups-form" method="post">
	<?php pb_backupbuddy::nonce( true ); ?>
	<input type="hidden" name="quicksetup" value="true">
	<div class="setup">
		<div class="step email">
			<h4><span class="number">1.</span> <?php esc_html_e( 'Enter your e-mail address to get backup and error notifications.', 'it-l10n-backupbuddy' ); ?></h4>
			<div class="quickstart-input-row">
				<div>
					<label class="screen-reader-text"><?php esc_html_e( 'E-mail Address', 'it-l10n-backupbuddy' ); ?></label>
					<input type="email" id="pb_backupbuddy_quickstart_email" name="email" value="<?php echo pb_backupbuddy::$options['email_notify_error']; ?>">
				</div>
				<div id="pb_backupbuddy_quickstart_email_check" class="check quickstart-icon quickstart-icon-check">
					<?php pb_backupbuddy::$ui->render_icon( 'solidwp-check-with-base' ); ?>
				</div>
			</div>
		</div>
		<div class="step password">
			<?php
			$text = __( 'Create a password for restoring or migrating your backups.', 'it-l10n-backupbuddy' );
			if ( ! empty( pb_backupbuddy::$options['importbuddy_pass_hash'] ) ) {
				$text = __( 'Optionally update your password for restoring or migrating your backups.', 'it-l10n-backupbuddy' );
			}
			?>
			<h4><span class="number">2.</span> <?php echo esc_html( $text ); ?></h4>
			<div class="quickstart-input-row">
				<div>
					<label class="screen-reader-text"><?php esc_html_e( 'Password', 'it-l10n-backupbuddy' ); ?></label>
					<input type="password" id="pb_backupbuddy_quickstart_password" name="password" placeholder="<?php echo esc_attr( __( 'Optional Password', 'it-l10n-backupbuddy' ) ); ?>">
				</div>
				<div>
					<label class="screen-reader-text"><?php esc_html_e( 'Confirm Password', 'it-l10n-backupbuddy' ); ?></label>
					<input class="checkfield" type="password" id="pb_backupbuddy_quickstart_passwordconfirm" name="password_confirm" placeholder="<?php echo esc_attr( __( 'Confirm Optional Password', 'it-l10n-backupbuddy' ) ); ?>">
				</div>
				<div>
					<?php $display = pb_backupbuddy::$options['importbuddy_pass_hash'] ? 'display:inline;' : ''; ?>
					<div id="pb_backupbuddy_quickstart_password_check"  class="check quickstart-icon quickstart-icon-check" style="<?php echo esc_attr( $display ); ?>">
						<?php pb_backupbuddy::$ui->render_icon( 'solidwp-check-with-base' ); ?>
					</div>
					<div id="pb_backupbuddy_quickstart_password_check_fail" class="check quickstart-icon quickstart-icon-fail">
						<?php pb_backupbuddy::$ui->render_icon( 'solidwp-close-with-base' ); ?>
					</div>
				</div>
			</div>
		</div>


	<?php
	require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
	$destinations = pb_backupbuddy_destinations::get_destinations_list();
	?>

		<div class="step destination">
			<h4><span class="number">3.</span> <?php esc_html_e( 'Where do you want to send your backups (scheduled or manually sent)?', 'it-l10n-backupbuddy' ); ?></h4>
			<div class="quickstart-input-row">
				<div id="dest" class="box-options">

					<input type="hidden" id="pb_backupbuddy_quickstart_destinationid" name="destination_id" value="">
					<select id="pb_backupbuddy_quickstart_destination" name="destination" class="change">

						<?php
						foreach ( $destinations as $destination_slug => $destination ) :
							if ( in_array( $destination_slug, [ 'site', 'live', 's3', 's32', 'stash2', ], true ) ) :
								continue;
							endif;

							if ( 'stash3' === $destination_slug ) :
								$destination['name'] .= ' - ' . esc_html__( 'Recommended', 'it-l10n-backupbuddy' );
							endif;

							if ( ! empty( $destination['name'] ) ) {
								printf( '<option value="%s" %s>%s</option>',
									esc_attr( $destination_slug ),
									selected( 'stash3', $destination_slug, false ),
									esc_html( $destination['name'] )
								) . "\r\n";
							}

							if ( 'stash3' === $destination_slug && isset( $destinations['live'] ) ) :
								printf(
									'<option value="live">%s</option>',
									esc_html__( 'Solid Backups Stash Live', 'it-l10n-backupbuddy' )
								);
							endif;
						endforeach;
						unset( $destinations );
						?>

						<option value=""><?php esc_html_e( 'Local Storage Only - Not Recommended', 'it-l10n-backupbuddy' ); ?></option>
					</select>
					<div id="dest" class="stash-fields">

						<p class="quickstart-alert">
							<?php
							echo wp_kses_post(
								sprintf(
									/* translators: the Solid Backups Stash URL */
									__( 'You get <strong>1GB</strong> of free storage on Solid Backups Stash, our managed backup storage. <a href="%s">Learn more about Solid Backups Stash.</a>', 'it-l10n-backupbuddy' ),
									esc_url( 'https://go.solidwp.com/solid-backups-stash' )
								)
							);
							?>
						</p>
						<div class="quickstart-input-row">
							<div>
								<label class="screen-reader-text"><?php esc_html_e( 'SolidWP Username', 'it-l10n-backupbuddy' ); ?></label>
								<input type="text" name="stash_username" placeholder="<?php echo esc_attr( 'SolidWP Username', 'it-l10n-backupbuddy' ); ?>">
							</div>
							<div>
								<label class="screen-reader-text"><?php esc_html_e( 'Password', 'it-l10n-backupbuddy' ); ?></label>
								<input class="checkfield" type="password" name="stash_password" placeholder="<?php echo esc_attr( 'Password', 'it-l10n-backupbuddy' ); ?>">
								<div class="check quickstart-icon quickstart-icon-check">
									<?php pb_backupbuddy::$ui->render_icon( 'solidwp-check-with-base' ); ?>
								</div>
							</div>
						</div>
					</div>
					<div d="pb_backupbuddy_quickstart_destination_check" class="check quickstart-icon quickstart-icon-check">
						<?php pb_backupbuddy::$ui->render_icon( 'solidwp-check-with-base' ); ?>
					</div>

				</div>
			</div>
		</div>
		<div class="step schedule">
			<h4><span class="number">4.</span> <?php esc_html_e( 'How often do you want to schedule backups of your site?', 'it-l10n-backupbuddy' ); ?></h4>
			<div class="quickstart-input-row">
				<div id="schedule" class="box-options clearfix">
					<select id="pb_backupbuddy_quickstart_schedule" name="schedule">
						<option value=""><?php esc_html_e( 'No Schedule (manual only)', 'it-l10n-backupbuddy' ); ?></option>
						<option value="starter"><?php esc_html_e( 'Starter [Recommended] (Monthly complete backup + weekly database backup)', 'it-l10n-backupbuddy' ); ?></option>
						<option value="blogger"><?php esc_html_e( 'Active Blogger (Weekly complete backup + daily database backup)', 'it-l10n-backupbuddy' ); ?></option>
						<!-- <option value="custom">Custom</option> -->
					</select>
				</div>
				<div id="pb_backupbuddy_quickstart_schedule_check" class="check quickstart-icon quickstart-icon-check">
						<?php pb_backupbuddy::$ui->render_icon( 'solidwp-check-with-base' ); ?>
					</div>
			</div>
		</div>

		<div class="save">
			<button class="button button-primary"><?php esc_html_e( 'Save Settings', 'it-l10n-backupbuddy' ); ?></button>
		</div>
	</div>

</form>
<div class="save skipsetup">
	<a href="?page=pb_backupbuddy_backup&skip_quicksetup=1"><button class="button button-secondary button-no-ml"><?php esc_html_e( 'Skip Setup Wizard for Now', 'it-l10n-backupbuddy' ); ?></button></a>
</div>
<span id="pb_backupbuddy_quickstart_saveloading" style="display: inline-block; display: none; float: left; margin-left: 40px;"><img src="<?php echo pb_backupbuddy::plugin_url(); ?>/assets/dist/images/loading_large.gif" <?php echo 'alt="', __( 'Loading...', 'it-l10n-backupbuddy' ), '" title="', __( 'Loading...', 'it-l10n-backupbuddy' ), '"'; ?> style="vertical-align: -3px;" /></span>

<br style="clear: both;">
