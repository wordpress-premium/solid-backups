<?php
/**
 * Create New Backup
 *
 * @package BackupBuddy
 */

// Handle deleting profile.
if ( '' != pb_backupbuddy::_GET( 'delete_profile' ) && is_numeric( pb_backupbuddy::_GET( 'delete_profile' ) ) ) {
	if ( pb_backupbuddy::_GET( 'delete_profile' ) > 2 ) {
		if ( isset( pb_backupbuddy::$options['profiles'][ pb_backupbuddy::_GET( 'delete_profile' ) ] ) ) {
			$profile_title = pb_backupbuddy::$options['profiles'][ pb_backupbuddy::_GET( 'delete_profile' ) ]['title'];
			unset( pb_backupbuddy::$options['profiles'][ pb_backupbuddy::_GET( 'delete_profile' ) ] );
			pb_backupbuddy::save();
			pb_backupbuddy::alert( 'Deleted profile "' . htmlentities( $profile_title ) . '".', false, '', '', array( 'class' => 'below-h2' ) );
		}
	} else {
		pb_backupbuddy::alert( 'Invalid profile ID. Cannot delete base profiles.', true, '', '', array( 'class' => 'below-h2' ) );
	}
}

// Add new profile.
if ( 'true' == pb_backupbuddy::_POST( 'add_profile' ) ) {
	pb_backupbuddy::verify_nonce();
	$error = false;
	if ( '' == pb_backupbuddy::_POST( 'title' ) ) {
		pb_backupbuddy::alert( 'Error: You must provide a new profile title.', true, '', '', '', array( 'class' => 'below-h2' ) );
		$error = true;
	}
	if ( false === $error ) {
		$profile = array_merge( pb_backupbuddy::settings( 'profile_defaults' ), array(
			'title' => htmlentities( pb_backupbuddy::_POST( 'title' ) ),
			'type'  => pb_backupbuddy::_POST( 'type' ),
		));

		pb_backupbuddy::$options['profiles'][] = $profile;
		pb_backupbuddy::save();
		pb_backupbuddy::alert( 'New profile "' . htmlentities( pb_backupbuddy::_POST( 'title' ) ) . '" added. Select it from the list below to customize its settings and override global defaults.', false, '', '', '', array( 'class' => 'below-h2' ) );
	}
} // end if add profile.

//$alert_message = array();
$preflight_checks = backupbuddy_core::preflight_check();
//echo '<!-- BB-preflight_check done -->';
$disableBackingUp = false;
foreach( $preflight_checks as $preflight_check ) {
	if ( $preflight_check['success'] !== true ) {
		//$alert_message[] = $preflight_check['message'];
		pb_backupbuddy::disalert( $preflight_check['test'], $preflight_check['message'] );
		if ( 'backup_dir_permissions' == $preflight_check['test'] ) {
			$disableBackingUp = true;
		} elseif ( 'temp_dir_permissions' == $preflight_check['test'] ) {
			$disableBackingUp = true;
		}
	}
}

// if ( count( $alert_message ) > 0 ) {
//	pb_backupbuddy::alert( implode( '<hr style="border: 1px dashed #E6DB55; border-bottom: 0;">', $alert_message ) );
// }

// echo '<div class="bb_show_preflight" style="display: none;"><h3>Preflight Check Results</h3><pre>';
// print_r( $preflight_checks );
// echo '</pre></div>';

?>
<div class="bb-profilebox-wrap profile_box">
	<div class="profile_choose">
		<?php _e( 'Choose a backup profile to run:', 'it-l10n-backupbuddy' ); ?>
	</div>

	<?php
	if ( true === $disableBackingUp ) {
		echo '&nbsp;&nbsp;<span class="description">' . __( 'Backing up disabled due to errors listed above. This often caused by permission problems on files/directories. Please correct the errors above and refresh to try again.', 'it-l10n-backupbuddy' ) . '</span><br>';
	} else {
		function bb_home_show_profiles( $profile_id, $profile ) {
			?>
			<div class="profile_item">
				<a class="profile_item_select" href="<?php echo pb_backupbuddy::page_url(); ?>&backupbuddy_backup=<?php echo $profile_id; ?>" title="Create this <?php echo $profile['type']; ?> backup.">
					<span class="profile_type profile_type-<?php echo $profile['type']; ?>" title="<?php esc_attr_e( backupbuddy_core::pretty_backup_type( $profile['type'] ), 'it-l10n-backupbuddy' ); ?>"></span>
					<span<?php if ( 1 == $profile_id || 2 == $profile_id ) { echo 'style="font-weight: 600 !important;"'; } ?> class="profile_text" id="profile_title_<?php echo esc_attr( $profile_id ); ?>"><?php echo esc_html( $profile['title'] ); ?></span>
				</a><a href="#settings" rel="<?php echo $profile_id; ?>" class="profile_settings dashicons dashicons-admin-generic" title="<?php esc_attr_e( "Configure this profile's settings.", 'it-l10n-backupbuddy' ); ?>"></a>
			</div>
			<?php
		}
		// Show full and db profiles first.
		bb_home_show_profiles( 2, pb_backupbuddy::$options['profiles'][2] );
		bb_home_show_profiles( 1, pb_backupbuddy::$options['profiles'][1] );
		echo '<div class="bb_profile_divider"></div>';
		foreach ( pb_backupbuddy::$options['profiles'] as $profile_id => $profile ) {
			if ( ( 1 == $profile_id ) || ( 2 == $profile_id ) ) { // skip full and db since shown above.
				continue;
			}
			if ( 0 == $profile_id ) { // Skip showing defaults here...
				if ( isset( pb_backupbuddy::$options['profiles'][3] ) ) {
					// echo '<hr style="clear: both;">';
					echo '<div class="bb_profile_divider"></div>';
				}
				continue;
			}
			bb_home_show_profiles( $profile_id, $profile );

		}

		$profile_style = $adding_profile ? '' : ' style="display: none;"';
		$selected = pb_backupbuddy::_POST( 'type' );
		?>

		<div class="profile_item" id="pb_backupbuddy_profileadd"<?php echo $profile_style; ?> href="<?php echo pb_backupbuddy::ajax_url( 'backup_profile_settings' ); ?>&profile=<?php echo esc_attr( $profile_id ); ?>">
			<div class="profile_item_noselect" style="padding: 6px;">
				<form method="post" action="?page=pb_backupbuddy_backup" style="white-space:nowrap;">
					<?php pb_backupbuddy::nonce(); ?>
					<input type="hidden" name="add_profile" value="true">
					<span class="profile_text"><input type="text" style="padding: 6px;" name="title" maxlength="20" placeholder="<?php _e( 'New profile title...', 'it-l10n-backupbuddy' ); ?>" value="<?php echo esc_attr( sanitize_text_field( pb_backupbuddy::_POST( 'title' ) ) ); ?>"></span>
					<span class="profile_type" style="margin-right: 0;">
						<select name="type">
							<option value="full"<?php selected( 'full', $selected ); ?>><?php esc_html_e( 'Full (Files & Database)', 'it-l10n-backupbuddy' ); ?></option>
							<option value="db" <?php selected( 'db', $selected ); ?>><?php esc_html_e( 'Database Only', 'it-l10n-backupbuddy' ); ?></option>
							<option disabled="disabled">---------------</option>
							<option value="themes" <?php selected( 'themes', $selected ); ?>><?php esc_html_e( 'Themes Only', 'it-l10n-backupbuddy' ); ?></option>
							<option value="plugins" <?php selected( 'plugins', $selected ); ?>><?php esc_html_e( 'Plugins Only', 'it-l10n-backupbuddy' ); ?></option>
							<option value="media" <?php selected( 'media', $selected ); ?>><?php esc_html_e( 'Media Only', 'it-l10n-backupbuddy' ); ?></option>
							<option disabled="disabled">---------------</option>
							<option value="files" <?php selected( 'files', $selected ); ?>><?php esc_html_e( 'Custom Files', 'it-l10n-backupbuddy' ); ?></option>
						</select>
					</span>
					<input type="submit" name="submit" value="+ <?php esc_html_e( 'Add', 'it-l10n-backupbuddy' ); ?>" class="button button-primary" style="vertical-align: 0;">
					<a class="profile_item_cancel_add_select" title="<?php esc_html_e( 'Cancel new profile.', 'it-l10n-backupbuddy' ); ?>">
						<span class="profile_add_cancel">&times;</span>
					</a>
				</form>
			</div>
		</div>

		<br style="clear: both;">

		<!-- Remote send after successful backup? -->
		<div class="bb-remote-checkbox">
			<label id="pb_backupbuddy_afterbackupremote">
				<input type="checkbox" name="pb_backupbuddy_afterbackupremote" id="pb_backupbuddy_afterbackupremote_box">
				Send to remote destination as part of backup process. <span id="pb_backupbuddy_backup_remotetitle"></span>
			</label>

			<input type="hidden" name="remote_destination" id="pb_backupbuddy_backup_remotedestination">
			<input type="hidden" name="delete_after" id="pb_backupbuddy_backup_deleteafter">

			<?php $button_style = $adding_profile ? ' style="display: none;"' : ''; ?>
			<div class="profile_item" id="pb_backupbuddy_profileadd_plusbutton"<?php echo $button_style; ?>>
				<a class="profile_item_noselect profile_item_add_select">
					<?php esc_html_e( 'Create New Profile', 'it-l10n-backupbuddy' ); ?>
				</a>
			</div>

		</div>
	<?php } // end disabling backups ?>

</div>
