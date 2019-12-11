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
pb_backupbuddy::load_style( 'quicksetup.css' );
?>
<script type="text/javascript">
	jQuery(function() {

		// If Live pre-selected, show checkmark.
		if ( 'live' == jQuery( '#pb_backupbuddy_quickstart_destination' ).val() ) {
			jQuery( '#pb_backupbuddy_quickstart_destination_check' ).show();
		}

		jQuery( '#pb_backupbuddy_quickstart_password, #pb_backupbuddy_quickstart_passwordconfirm' ).keyup( function() {
			if ( ( jQuery( '#pb_backupbuddy_quickstart_password' ).val() != '' ) && ( jQuery( '#pb_backupbuddy_quickstart_password' ).val() == jQuery( '#pb_backupbuddy_quickstart_passwordconfirm' ).val() ) ) {
				jQuery( '#pb_backupbuddy_quickstart_password_check_fail,#pb_backupbuddy_quickstart_password_check_fail > img' ).hide();
				jQuery( '#pb_backupbuddy_quickstart_password_check' ).show();
			} else {
				jQuery( '#pb_backupbuddy_quickstart_password_check' ).hide();
				if ( ( jQuery( '#pb_backupbuddy_quickstart_password' ).val() != '' ) || ( jQuery( '#pb_backupbuddy_quickstart_passwordconfirm' ).val() != '' ) ) { // Mismatch non-blank.
					jQuery( '#pb_backupbuddy_quickstart_password_check_fail,#pb_backupbuddy_quickstart_password_check_fail > img' ).show();
				} else if ( ( jQuery( '#pb_backupbuddy_quickstart_password' ).val() == '' ) && ( jQuery( '#pb_backupbuddy_quickstart_passwordconfirm' ).val() == '' ) ) { // both blank
					jQuery( '#pb_backupbuddy_quickstart_password_check_fail,#pb_backupbuddy_quickstart_password_check_fail > img' ).hide();
				}
			}
		} );

		jQuery( '#pb_backupbuddy_quickstart_email' ).change( function() {
			if ( ( jQuery(this).val() != '' ) && ( jQuery(this).val().indexOf( '@' ) >= 0 ) ) {
				jQuery( '#pb_backupbuddy_quickstart_email_check' ).show();
			} else {
				jQuery( '#pb_backupbuddy_quickstart_email_check' ).hide();
			}
		});

		/* Show success checkmark if pre-filled email looks valid. */
		quickstart_email = jQuery( '#pb_backupbuddy_quickstart_email' ).val();
		if ( ( quickstart_email != '' ) && ( quickstart_email.indexOf( '@' ) >= 0 ) ) {
			jQuery( '#pb_backupbuddy_quickstart_email_check' ).show();
		}

		jQuery( '#pb_backupbuddy_quickstart_destination' ).change( function() {
			if ( jQuery(this).val() == 'stash2' ) { // Stash (v2).
				jQuery( '.stash2-fields' ).slideDown();
				jQuery( '#pb_backupbuddy_quickstart_form .schedule' ).slideDown();
				return; // Skip destination picker for Stash (v2).
			} else if (  jQuery(this).val() == 'live' ) { // Stash Live (as of v7).
				jQuery( '.stash2-fields' ).slideUp();
				jQuery( '#pb_backupbuddy_quickstart_form .schedule' ).slideUp();
				return;
			} else { // Other destination.
				jQuery( '.stash2-fields' ).slideUp();
				jQuery( '#pb_backupbuddy_quickstart_form .schedule' ).slideDown();
			}
			if ( jQuery(this).val() != '' ) {
				tb_show( 'BackupBuddy', '<?php echo pb_backupbuddy::ajax_url( 'destination_picker' ); ?>&quickstart=true&add=' + jQuery(this).val() + '&filter=' + jQuery(this).val() + '&callback_data=&sending=0&TB_iframe=1&width=640&height=455', null );
			}
		});

		jQuery( '#pb_backupbuddy_quickstart_stashuser' ).change( function() {
			if ( ( jQuery(this).val() != '' ) && ( jQuery( '#pb_backupbuddy_quickstart_stashpass' ).val() != '' ) ) {
				pb_backupbuddy_stashtest();
			}
		});
		jQuery( '#pb_backupbuddy_quickstart_stashpass' ).change( function() {
			if ( ( jQuery(this).val() != '' ) && ( jQuery( '#pb_backupbuddy_quickstart_stashuser' ).val() != '' ) ) {
				pb_backupbuddy_stashtest();
			}
		});

		jQuery( '#pb_backupbuddy_quickstart_destination' ).change( function() {
			if ( jQuery(this).val() == '' ) {
				jQuery( '#pb_backupbuddy_quickstart_destination_check' ).hide();
			}
		});

		jQuery( '#pb_backupbuddy_quickstart_schedule' ).change( function() {
			if ( jQuery(this).val() != '' ) {
				jQuery( '#pb_backupbuddy_quickstart_schedule_check' ).show();
			} else {
				jQuery( '#pb_backupbuddy_quickstart_schedule_check' ).hide();
			}
		});

		jQuery( '#pb_backupbuddy_quickstart_form' ).submit( function() {
			jQuery( '#pb_backupbuddy_quickstart_saveloading' ).show();
			jQuery.post( '<?php echo pb_backupbuddy::ajax_url( 'quickstart_form' ); ?>', jQuery(this).serialize(),
				function(data) {
					jQuery( '#pb_backupbuddy_quickstart_saveloading' ).hide();
					data = jQuery.trim( data );

					if ( data == 'Success.' ) {
						if ( 'live' == jQuery( '#pb_backupbuddy_quickstart_destination' ).val() ) {
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
		alert( 'Destination added successfully! Close this dialog to return to Quick Start Setup.' ); // TODO: i18n this string.
		if ( jQuery( '#pb_backupbuddy_quickstart_destination' ).val() != '' ) {
			jQuery( '#pb_backupbuddy_quickstart_destination_check' ).show();
			jQuery( '#pb_backupbuddy_quickstart_destinationid' ).val( dest_id );
		} else {
			jQuery( '#pb_backupbuddy_quickstart_destination_check' ).hide();
		}
	}

	function pb_backupbuddy_stashtest() {
		jQuery( '#pb_backupbuddy_quickstart_stashloading' ).show();
		jQuery.post( '<?php echo pb_backupbuddy::ajax_url( 'quickstart_stash_test' ); ?>', {
				user: jQuery( '#pb_backupbuddy_quickstart_stashuser' ).val(),
				pass: jQuery( '#pb_backupbuddy_quickstart_stashpass' ).val()
			},
			function(data) {
				jQuery( '#pb_backupbuddy_quickstart_stashloading' ).hide();
				data = jQuery.trim( data );
				alert( data );
			}
		);
	}

	function pb_backupbuddy_stash2test() {
		jQuery( '#pb_backupbuddy_quickstart_stash2loading' ).show();
		jQuery.post( '<?php echo pb_backupbuddy::ajax_url( 'quickstart_stash2_test' ); ?>', {
				user: jQuery( '#pb_backupbuddy_quickstart_stash2user' ).val(),
				pass: jQuery( '#pb_backupbuddy_quickstart_stash2pass' ).val()
			},
			function(data) {
				jQuery( '#pb_backupbuddy_quickstart_stash2loading' ).hide();
				data = jQuery.trim( data );
				alert( data );
			}
		);
	}
</script>

<p>
	Complete this optional wizard to start using BackupBuddy right away. See the <a href="admin.php?page=pb_backupbuddy_settings" target="_blank">Settings</a> page for all configuration options.
</p>

<form id="pb_backupbuddy_quickstart_form" method="post">
	<?php pb_backupbuddy::nonce( true ); ?>
	<input type="hidden" name="quicksetup" value="true">
	<div class="setup">
		<div class="step email">
			<h4><span class="number">1.</span> Enter your e-mail address to get backup and error notifications.</h4>
<!--			<p>You'll get emails when backups are completed or if there is an error with a backup.</p> -->
			<div class="backupbuddy-quickstart-indent">
				<label>E-mail Address</label>
				<input type="email" id="pb_backupbuddy_quickstart_email" name="email" value="<?php echo pb_backupbuddy::$options['email_notify_error']; ?>">
				<img src="<?php echo pb_backupbuddy::plugin_url(); ?>/images/check.png" class="check" id="pb_backupbuddy_quickstart_email_check">
			</div>
		</div>
		<div class="step password">
			<h4><span class="number">2.</span> Create a password for restoring or migrating your backups.</h4>
			<div class="backupbuddy-quickstart-indent">
				<div class="input-float">
					<label>Password</label>
					<input type="password" id="pb_backupbuddy_quickstart_password" name="password">
				</div>
				<div class="input-float">
					<label>Confirm Password</label>
					<input class="checkfield" type="password" id="pb_backupbuddy_quickstart_passwordconfirm" name="password_confirm">
				</div>
				<div class="input-float">
					<img src="<?php echo pb_backupbuddy::plugin_url(); ?>/images/check.png" class="check" id="pb_backupbuddy_quickstart_password_check" style="margin-top: 26px; margin-left: 0;">
					<div id="pb_backupbuddy_quickstart_password_check_fail" style="color: #E38282; display: none;">
						<img src="<?php echo pb_backupbuddy::plugin_url(); ?>/images/nomatch-x.png" class="check" style="margin-top: 26px; margin-left: 0;">
						<div style="display: inline-block; margin-left: 35px; margin-top: 32px;">
							<?php // _e( "These don't match", 'it-l10n-backupbuddy' ); ?>
						</div>
					</div>
				</div>
			</div>
			<div class="clearfix"></div>
		</div><br style="clear: both;"><br><br>

		<!--
		<div class="step limit">
			<h4><span class="number">3.</span> How many backups should be ket locally before deleting the oldest?</h4>
			<label>Number of backups to keep</label>
			<input type="email" id="pb_backupbuddy_quickstart_archive_limit name="archive_limit" size="7" style="width: 180px;" value="12">
			<img src="<?php echo pb_backupbuddy::plugin_url(); ?>/images/check.png" class="check" id="pb_backupbuddy_quickstart_archive_limit_check">
		</div>
		-->


	<?php
	require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
	$destinations = pb_backupbuddy_destinations::get_destinations_list();
	?>

		<div class="step destination">
			<h4><span class="number">3.</span> Where do you want to send your backups (scheduled or manually sent)?</h4>
			<div class="backupbuddy-quickstart-indent">
				<div id="dest" class="box-options">

					<input type="hidden" id="pb_backupbuddy_quickstart_destinationid" name="destination_id" value="">
					<select id="pb_backupbuddy_quickstart_destination" name="destination" class="change">

						<?php
						// For each v2 destination available remove its v1.
						if ( isset( $destinations['s32'] ) ) :
							unset( $destinations['s3'] );
						endif;

						$stash_v2 = '';

						foreach ( $destinations as $destination_slug => $destination ) :
							if ( 'site' === $destination_slug || 'live' === $destination_slug ) : // Don't show Deployment or Live.
								continue;
							endif;

							if ( 'stash2' === $destination_slug ) :
								$stash_v2             = '2';
								$destination['name'] .= ' - ' . esc_html__( 'Recommended', 'it-l10n-backupbuddy' );

								if ( isset( $destinations['live'] ) ) :
									printf( '<option value="live">%s - %s</option>',
										esc_html__( 'BackupBuddy Stash Live', 'it-l10n-backupbuddy' ),
										esc_html__( 'Real-time backups new in v7.0', 'it-l10n-backupbuddy' )
									);
								endif;
							endif;

							printf( '<option value="%s" %s>%s</option>',
								esc_attr( $destination_slug ),
								selected( 'stash2', $destination_slug, false ),
								esc_html( $destination['name'] )
							) . "\r\n";
						endforeach;
						unset( $destinations );
						?>

						<option value=""><?php esc_html_e( 'Local Storage Only - Not Recommended', 'it-l10n-backupbuddy' ); ?></option>
					</select>

					<img src="<?php echo esc_url( pb_backupbuddy::plugin_url() ); ?>/images/check.png" class="check" id="pb_backupbuddy_quickstart_destination_check" style="margin-top: 2px;" />

					<div id="dest" class="stash<?php echo esc_attr( $stash_v2 ); ?>-fields">
						<p style="margin-bottom: 0;">You get <strong>1GB</strong> of free storage on BackupBuddy Stash, our managed backup storage. <a href="https://ithemes.com/backupbuddy-stash/">Learn more about BackupBuddy Stash</a></p>
						<div class="clearfix"></div>
						<div class="input-float">
							<label>iThemes Username</label>
							<input type="text" name="stash<?php echo esc_attr( $stash_v2 ); ?>_username">
						</div>
						<div>
							<label>Password</label>
							<input class="checkfield" type="password" name="stash<?php echo esc_attr( $stash_v2 ); ?>_password">
							<img src="<?php echo esc_url( pb_backupbuddy::plugin_url() ); ?>/images/check.png" class="check" />
						</div>
						<div></div><!-- does this do something? -->
						<div class="clearfix"></div>
					</div>

				</div>
			</div>
		</div>
		<div class="step schedule">
			<h4><span class="number">4.</span> How often do you want to schedule backups of your site?</h4>
			<div class="backupbuddy-quickstart-indent">
				<div id="schedule" class="box-options clearfix">
					<select id="pb_backupbuddy_quickstart_schedule" name="schedule">
						<option value="">No Schedule (manual only)</option>
						<option value="starter">Starter [Recommended] (Monthly complete backup + weekly database backup)</option>
						<option value="blogger">Active Blogger (Weekly complete backup + daily database backup)</option>
						<!-- <option value="custom">Custom</option> -->
					</select>
					<img src="<?php echo pb_backupbuddy::plugin_url(); ?>/images/check.png" class="check" id="pb_backupbuddy_quickstart_schedule_check" />
				</div>
			</div>
		</div>

		<div class="save">
			<button>Save Settings</button>
		</div>
	</div>

</form>
<div class="save skipsetup">
	<a href="?page=pb_backupbuddy_backup&skip_quicksetup=1"><button>Skip Setup Wizard for Now</button></a>
</div>
<span id="pb_backupbuddy_quickstart_saveloading" style="display: inline-block; display: none; float: left; margin-left: 40px;"><img src="<?php echo pb_backupbuddy::plugin_url(); ?>/images/loading_large.gif" <?php echo 'alt="', __( 'Loading...', 'it-l10n-backupbuddy' ), '" title="', __( 'Loading...', 'it-l10n-backupbuddy' ), '"'; ?> style="vertical-align: -3px;" /></span>

<br style="clear: both;">
