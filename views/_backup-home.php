<?php
/**
 * Backup Home View file
 *
 * Incoming variables: $backup from controllers/pages/_backup_home.php
 *
 * @package BackupBuddy
 */

if ( '1' == pb_backupbuddy::_GET( 'skip_quicksetup' ) ) {
	pb_backupbuddy::$options['skip_quicksetup'] = '1';
	pb_backupbuddy::save();
}

if ( '' != pb_backupbuddy::_GET( 'rollback' ) ) {
	require_once pb_backupbuddy::plugin_path() . '/controllers/pages/_rollback.php';
	return;
}

if ( true !== apply_filters( 'itbub_hide_quickwizard', false ) ): ?>
	<script type="text/javascript">
	jQuery( function() {
		jQuery('#screen-meta-links').append(
			'<div id="backupbuddy-meta-link-wrap" class="hide-if-no-js screen-meta-toggle">' +
				'<a href="?page=pb_backupbuddy_backup&wizard=1" class="show-settings no-dropdown"><?php esc_html_e( 'Quick Setup Wizard', 'it-l10n-backupbuddy' ); ?></a>' +
			'</div>'
		);
	});
	</script>
<?php endif;


// Popup Quickstart modal if appears to be new install & quickstart not skip.
if (
	true !== apply_filters( 'itbub_hide_quickwizard', false )
	&&

	( ( '1' == pb_backupbuddy::_GET( 'wizard' ) )
	||
	(
		( '0' == pb_backupbuddy::$options['skip_quicksetup'] )
			&&
		( 0 == count( pb_backupbuddy::$options['schedules'] ) )
			&&
			( '' == pb_backupbuddy::$options['importbuddy_pass_hash'] )
		)
	) ) {
	pb_backupbuddy::$ui->title( 'BackupBuddy Quick Setup Wizard' );
	pb_backupbuddy::load_view( '_quicksetup', array() );
	return;
} else {
	pb_backupbuddy::$ui->title( __( 'Backup', 'it-l10n-backupbuddy' ) );
	?>
	<script type="text/javascript">
	jQuery( function() {
		jQuery('#screen-meta-links').append(
			'<div id="backupbuddy-meta-link-wrap" class="hide-if-no-js screen-meta-toggle">' +
				'<a href="javascript:void(0)" class="show-settings" onClick="jQuery(\'.backupbuddy-recent-backups\').toggle(); jQuery(this).toggleClass(\'screen-meta-active\'); return false;"><?php esc_html_e( 'Recently Made Backups', 'it-l10n-backupbuddy' ); ?></a>' +
			'</div>'
		);
	});
	</script>
	<?php
}

wp_enqueue_script( 'thickbox' );
wp_print_scripts( 'thickbox' );
wp_print_styles( 'thickbox' );

// Handle deleting profile.
if ( '' != pb_backupbuddy::_GET( 'delete_profile' ) && is_numeric( pb_backupbuddy::_GET( 'delete_profile' ) ) ) {
	if ( pb_backupbuddy::_GET( 'delete_profile' ) > 2 ) {
		if ( isset( pb_backupbuddy::$options['profiles'][ pb_backupbuddy::_GET( 'delete_profile' ) ] ) ) {
			$profile_title = pb_backupbuddy::$options['profiles'][ pb_backupbuddy::_GET( 'delete_profile' ) ]['title'];
			unset( pb_backupbuddy::$options['profiles'][ pb_backupbuddy::_GET( 'delete_profile' ) ] );
			pb_backupbuddy::save();
			pb_backupbuddy::alert( 'Deleted profile "' . htmlentities( $profile_title ) . '".' );
		}
	} else {
		pb_backupbuddy::alert( 'Invalid profile ID. Cannot delete base profiles.' );
	}
}


// Quickwizard just completed.
if ( isset( $_GET['quickstart_wizard'] ) && '' != $_GET['quickstart_wizard'] ) {
	pb_backupbuddy::disalert( 'quickstart_wizard_finished', __( 'Quick Setup Wizard complete. Select a backup profile below to start backing up. See the <a href="admin.php?page=pb_backupbuddy_settings" target="_blank">Settings</a> page for all configuration options.', 'it-l10n-backupbuddy' ) );
}


// Add new profile.
if ( 'true' == pb_backupbuddy::_POST( 'add_profile' ) ) {
	pb_backupbuddy::verify_nonce();
	$error = false;
	if ( '' == pb_backupbuddy::_POST( 'title' ) ) {
		pb_backupbuddy::alert( 'Error: You must provide a new profile title.', true );
		$error = true;
	}
	if ( false === $error ) {
		$profile                               = array(
			'title' => htmlentities( pb_backupbuddy::_POST( 'title' ) ),
			'type'  => pb_backupbuddy::_POST( 'type' ),
		);
		$profile                               = array_merge( pb_backupbuddy::settings( 'profile_defaults' ), $profile );
		pb_backupbuddy::$options['profiles'][] = $profile;
		pb_backupbuddy::save();
		pb_backupbuddy::alert( 'New profile "' . htmlentities( pb_backupbuddy::_POST( 'title' ) ) . '" added. Select it from the list below to customize its settings and override global defaults.' );
	}
} // end if add profile.
?>

<br>
<script type="text/javascript">
	jQuery( function() {

		jQuery( '.profile_item_select' ).click( function() {
			var url = jQuery(this).attr( 'href' );
			url = url + '&after_destination=' + jQuery( '#pb_backupbuddy_backup_remotedestination' ).val();
			url = url + '&delete_after=' + jQuery( '#pb_backupbuddy_backup_deleteafter' ).val();
			window.location.href = url;
			return false;
		});

		// Click label for after backup remote send.
		jQuery( '#pb_backupbuddy_afterbackupremote' ).click( function(e) {
			jQuery('#pb_backupbuddy_backup_remotetitle').html('');
			var checkbox = jQuery( '#pb_backupbuddy_afterbackupremote_box' );
			checkbox.prop('checked', !checkbox[0].checked);

			if ( checkbox[0].checked ) { // Only show if just checked.
				afterbackupremote();
			} else {
				// If unchecked, remove value
				jQuery('#pb_backupbuddy_backup_remotedestination').removeAttr('value');
				jQuery('#pb_backupbuddy_backup_deleteafter').removeAttr('value');
			}
			return false;
		});

		// Click checkbox for after backup remote send.
		jQuery( '#pb_backupbuddy_afterbackupremote_box' ).click( function(e) {
			jQuery('#pb_backupbuddy_backup_remotetitle').html('');
			var checkbox = jQuery( '#pb_backupbuddy_afterbackupremote_box' );
			if ( checkbox[0].checked ) { // Only show if just checked.
				afterbackupremote();
			} else {
				// If unchecked, remove value
				jQuery('#pb_backupbuddy_backup_remotedestination').removeAttr('value');
				jQuery('#pb_backupbuddy_backup_deleteafter').removeAttr('value');
			}
		});

		jQuery( 'body' ).on( 'thickbox:removed', function() {
			if ( '' === jQuery( '#pb_backupbuddy_backup_remotedestination' ).val() ) {
				jQuery( '#pb_backupbuddy_afterbackupremote_box' ).removeAttr( 'checked' );
			}
		});

		// Click profile config gear next to a profile to pop up modal for editing its settings.
		jQuery( '.profile_settings' ).click( function(e) {
			tb_show( 'BackupBuddy', '<?php echo pb_backupbuddy::ajax_url( 'profile_settings' ); ?>&profile=' + jQuery(this).attr( 'rel' ) + '&callback_data=' + jQuery(this).attr('rel') + '&TB_iframe=1&width=640&height=455', null );
			return false;
		});

		// Clicked + sign to add a new profile.
		jQuery( '#pb_backupbuddy_profileadd_plusbutton' ).click( function() {
			jQuery(this).hide();
			jQuery( '#pb_backupbuddy_profileadd' ).show();
			return false;
		});


	}); // end jquery document ready.

	function pb_backupbuddy_profile_updated( profileID, profileTitle ) {
		jQuery( '#profile_title_' + profileID ).text( profileTitle );
	}

	function afterbackupremote() {

		tb_show( 'BackupBuddy', '<?php echo pb_backupbuddy::ajax_url( 'destination_picker' ); ?>&callback_data=delayed_send&sending=1&action_verb=to%20send%20to&TB_iframe=1&width=640&height=455', null );
	} // end afterbackupremote().

</script>
<style>
	.profile_box {
		background: #F8F8F8;
		margin: 0;
		display: block;
		border-radius: 5px;
		padding: 10px 10px 0px 10px;
		margin-bottom: 40px;
		border-radius: 5px;
		border: 1px solid #d6d6d6;
		border-top: 1px solid #ebebeb;
		box-shadow: 0px 3px 0px 0px #aaaaaa;
		box-shadow: 0px 3px 0px 0px #CFCFCF;
		font-size: auto;
	}
	.profile_text {
		display: block;
		float: left;
		line-height: 26px;
		padding-right: 8px;
	}
	.profile_type {
		display: inline-block;
		float: left;
		line-height: 26px;
		margin-right: 10px;
		//width: 68px;
		color: #aaa;

		padding-right: 10px;
		border-right: 1px solid #EBEBEB;
	}

	.profile_item_select,.profile_item_noselect {
		display: inline-block;
		background: #fff;
		border: 1px solid #e7e7e7;
		border-top: 1px solid #ebebeb;
		border-bottom: 1px solid #c9c9c9;
		border-radius: 4px 0 0 4px;
		padding: 10px 15px;
		margin-bottom: 10px;
		text-decoration: none;
		color: #252525;
		//width: 90%;
		line-height: 2;
		font-size: medium;
	}
	.bb-dest-option .info.add-new {
		width: 95%;
		padding-right: 3%;
		border-radius: 4px;
	}

	.profile_item_select:hover,.profile_item_noselect:hover {
		color: #da2828;
	}
	.profile_item_select:active, .profile_item_select:focus,.profile_item_noselect:active, .profile_item_noselect:focus {
		box-shadow: inset 0 0 5px #da2828;
	}

	.profile_item {
		margin-right: 0;
		display: inline-block;
		white-space: nowrap;
		float: left;
	}
	.profile_item:hover {
		color: #da2828;
		cursor: pointer;
	}

	.profile_item_add_select {
		border-radius: 4px 4px 4px 4px;
		padding: 7px;
	}

	.profile_item_selected {
		border-bottom: 3px solid #da2828;
		margin-bottom: 10px;
	}

	.profile_choose {
		font-size: 20px;
		font-family: "HelveticaNeue-Light","Helvetica Neue Light","Helvetica Neue",sans-serif;
		padding: 5px 0 15px 5px;
		color: #464646;
	}
	.backupbuddyFileTitle {
		//color: #0084CB;
		color: #000;
		font-size: 1.2em;
	}

	.profile_settings {
		display: inline-block;
		height: 34px;
		color: #cdcdcd;
		/*
		width: 20px;
		padding: 11px;
		*/
		padding: 12px 12px 0 11px;
		width: 20px;
		margin-top: 0;
		margin-right: 12px;
		margin-bottom: 10px;
		background-size: 20px 20px;
		border-radius: 0 4px 4px 0;
		border-right: 1px solid #e7e7e7;
		border-top: 1px solid #ebebeb;
		border-bottom: 1px solid #c9c9c9;

		background-position: center;
		background-repeat:no-repeat;
		background-color: #fff;
		background-size: 20px 20px;

		font-size: 1.7em;
	}
	.profile_settings:hover {
		background-color: #a8a8a8;
		background-size: 20px 20px;
		box-shadow: inset 0 0 8px #666;
		color: #fff;
	}
	.profile_add {
		display: block;
		width: 32px;
		height: 32px;
		background: transparent url('<?php echo pb_backupbuddy::plugin_url(); ?>/images/dest_plus.png') top left no-repeat;
		vertical-align: -3px;
	}
	.profile_add:hover {
		background: transparent url('<?php echo pb_backupbuddy::plugin_url(); ?>/images/dest_plus.png') bottom left no-repeat;
	}

	.bb_profile_divider {
		display: inline-block;
		border-right: 1px dashed #d8d8d8;
		width: 1px;
		margin-right: 13px;
		height: 35px;
		margin-bottom: 15px;
	}
</style>

<br>
<div class="backupbuddy-recent-backups" style="display: none;">

	<?php
	$recent_backups_list = glob( backupbuddy_core::getLogDirectory() . 'fileoptions/*.txt' );
	if ( ! is_array( $recent_backups_list ) ) {
		$recent_backups_list = array();
	}

	if ( 0 == count( $recent_backups_list ) ) {
		esc_html_e( 'No backups have been created recently.', 'it-l10n-backupbuddy' );
	} else {

		// Backup type.
		$pretty_type = array(
			'full'  => 'Full',
			'db'    => 'Database',
			'files' => 'Files',
		);

		// Read in list of backups.
		$recent_backup_count_cap = 5; // Max number of recent backups to list.
		$recent_backups           = array();
		foreach ( $recent_backups_list as $backup_fileoptions ) {

			require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
			pb_backupbuddy::status( 'details', 'Fileoptions instance #1.' );
			$read_only = true;
			$backup = new pb_backupbuddy_fileoptions( $backup_fileoptions, $read_only );
			$result = $backup->is_ok();
			if ( true !== $result ) {
				pb_backupbuddy::status( 'error', __( 'Unable to access fileoptions data file.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
				continue;
			}
			$backup = &$backup->options;

			if ( empty( $backup['serial'] ) || ( ! empty( $backup['fileoptions_rebuilt'] ) ) ) {
				continue;
			}

			if ( ( $backup['finish_time'] >= $backup['start_time'] ) && ( 0 != $backup['start_time'] ) ) {
				$status = '<span class="pb_label pb_label-success">Completed</span>';
			} elseif ( $backup['finish_time'] == -1 ) {
				$status = '<span class="pb_label pb_label-warning">Cancelled</span>';
			} elseif ( false === $backup['finish_time'] ) {
				$status = '<span class="pb_label pb_label-error">Failed (timeout?)</span>';
			} elseif ( ( time() - $backup['updated_time'] ) > backupbuddy_constants::TIME_BEFORE_CONSIDERED_TIMEOUT ) {
				$status = '<span class="pb_label pb_label-error">Failed (likely timeout)</span>';
			} else {
				$status = '<span class="pb_label pb_label-warning">In progress or timed out</span>';
			}
			$status .= '<br>';


			// Technical details link.
			$status .= '<div class="row-actions">';
			$status .= '<a title="' . __( 'Backup Process Technical Details', 'it-l10n-backupbuddy' ) . '" href="' . pb_backupbuddy::ajax_url( 'integrity_status' ) . '&serial=' . $backup['serial'] . '&#038;TB_iframe=1&#038;width=640&#038;height=600" class="thickbox">View Details</a>';

			$sum_log_file = backupbuddy_core::getLogDirectory() . 'status-' . $backup['serial'] . '_' . pb_backupbuddy::$options['log_serial'] . '.txt';
			if ( file_exists( $sum_log_file ) ) {
				$status .= '<div class="row-actions"><a title="' . __( 'View Backup Log', 'it-l10n-backupbuddy' ) . '" href="' . pb_backupbuddy::ajax_url( 'view_log' ) . '&serial=' . $backup['serial'] . '&#038;TB_iframe=1&#038;width=640&#038;height=600" class="thickbox">' . __( 'View Log', 'it-l10n-backupbuddy' ) . '</a></div>';
			}

			$status .= '</div>';

			// Calculate finish time (if finished).
			if ( $backup['finish_time'] > 0 ) {
				$finish_time = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $backup['finish_time'] ) ) . '<br><span class="description">' . pb_backupbuddy::$format->time_ago( $backup['finish_time'] ) . ' ago</span>';
			} else { // unfinished.
				$finish_time = '<i>Unfinished</i>';
			}

			$backup_title = '<span class="backupbuddyFileTitle" style="color: #000;" title="' . basename( $backup['archive_file'] ) . '">' . pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $backup['start_time'] ), 'l, F j, Y - g:i:s a' ) . ' (' . pb_backupbuddy::$format->time_ago( $backup['start_time'] ) . ' ago)</span><br><span class="description">' . basename( $backup['archive_file'] ) . '</span>';

			if ( isset( $backup['profile'] ) && isset( $backup['profile']['type'] ) ) {
				$backup_type = '<div>
					<span style="color: #AAA; float: left;">' . pb_backupbuddy::$format->prettify( $backup['profile']['type'], $pretty_type ) . '</span>
					<span style="display: inline-block; float: left; height: 15px; border-right: 1px solid #EBEBEB; margin-left: 6px; margin-right: 6px;"></span>'
					. $backup['profile']['title'] .
				'</div>';
			} else {
				$backup_type = backupbuddy_core::pretty_backup_type( backupbuddy_core::getBackupTypeFromFile( $backup['archive_file'] ) );
				if ( '' == $backup_type ) {
					$backup_type = '<span class="description">Unknown</span>';
				}
			}

			if ( isset( $backup['archive_size'] ) && ( $backup['archive_size'] > 0 ) ) {
				$archive_size = pb_backupbuddy::$format->file_size( $backup['archive_size'] );
			} else {
				$archive_size = 'n/a';
			}

			// No integrity check for themes or plugins types.
			$raw_type = backupbuddy_core::getBackupTypeFromFile( $backup['archive_file'] );
			if ( ( 'themes' == $raw_type ) || ( 'plugins' == $raw_type ) ) {
				$status = 'n/a';
			}

			// Append to list.
			$recent_backups[ $backup['serial'] ] = array(
				array( basename( $backup['archive_file'] ), $backup_title ),
				$backup_type,
				$archive_size,
				ucfirst( $backup['trigger'] ),
				$status,
				'start_timestamp' => $backup['start_time'], // Used by array sorter later to put backups in proper order.
			);

		}

		$columns = array(
			__( 'Recently Made Backups (Start Time)', 'it-l10n-backupbuddy' ),
			__( 'Type | Profile', 'it-l10n-backupbuddy' ),
			__( 'File Size', 'it-l10n-backupbuddy' ),
			__( 'Trigger', 'it-l10n-backupbuddy' ),
			__( 'Status', 'it-l10n-backupbuddy' ) . ' <span class="description">(hover for options)</span>',
		);

		function pb_backupbuddy_aasort( &$array, $key ) {
			$sorter = array();
			$ret    = array();
			reset( $array );
			foreach ( $array as $ii => $va ) {
				$sorter[ $ii ] = $va[ $key ];
			}
			asort( $sorter );
			foreach ( $sorter as $ii => $va ) {
				$ret[ $ii ] = $array[ $ii ];
			}
			$array = $ret;
		}

		pb_backupbuddy_aasort( $recent_backups, 'start_timestamp' ); // Sort by multidimensional array with key start_timestamp.
		$recent_backups = array_reverse( $recent_backups ); // Reverse array order to show newest first.

		$recent_backups = array_slice( $recent_backups, 0, $recent_backup_count_cap ); // Only display most recent X number of backups in list.

		pb_backupbuddy::$ui->list_table(
			$recent_backups,
			array(
				'action'  => pb_backupbuddy::page_url(),
				'columns' => $columns,
				'css'     => 'width: 100%;',
			)
		);

		echo '<div class="alignright actions">';
		pb_backupbuddy::$ui->note( 'Hover over items above for additional options.' );
		echo '</div>';

	} // end if recent backups exist.
	?>

	<br><br><br>
</div>



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
		bb_home_show_profiles( 1, pb_backupbuddy::$options['profiles'][1] );
		bb_home_show_profiles( 2, pb_backupbuddy::$options['profiles'][2] );
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
		?>

		<div class="profile_item" id="pb_backupbuddy_profileadd_plusbutton">
			<a class="profile_item_noselect profile_item_add_select" title="<?php _e( 'Create new profile.', 'it-l10n-backupbuddy' ); ?>">
				<span class="profile_add"></span>
			</a>
		</div>

		<div class="profile_item" id="pb_backupbuddy_profileadd" style="display: none;" href="<?php echo pb_backupbuddy::ajax_url( 'backup_profile_settings' ); ?>&profile=<?php echo $profile_id; ?>">
			<div class="profile_item_noselect" style="padding: 6px;">
				<form method="post" action="?page=pb_backupbuddy_backup" style="white-space:nowrap;">
					<?php pb_backupbuddy::nonce(); ?>
					<input type="hidden" name="add_profile" value="true">
					<span class="profile_text"><input type="text" style="padding: 6px;" name="title" maxlength="20" placeholder="<?php _e( 'New profile title...', 'it-l10n-backupbuddy' ); ?>"></span>
					<span class="profile_type" style="margin-right: 0;">
						<select name="type">
							<option value="db"><?php _e( 'Database Only', 'it-l10n-backupbuddy' ); ?></option>
							<option value="full"><?php _e( 'Full (Files & Database)', 'it-l10n-backupbuddy' ); ?></option>
							<option disabled="disabled">---------------</option>
							<option value="themes"><?php _e( 'Themes Only', 'it-l10n-backupbuddy' ); ?></option>
							<option value="plugins"><?php _e( 'Plugins Only', 'it-l10n-backupbuddy' ); ?></option>
							<option value="media"><?php _e( 'Media Only', 'it-l10n-backupbuddy' ); ?></option>
							<option disabled="disabled">---------------</option>
							<option value="files"><?php _e( 'Custom Files', 'it-l10n-backupbuddy' ); ?></option>
						</select>
					</span>
					<input type="submit" name="submit" value="+ <?php _e( 'Add', 'it-l10n-backupbuddy' ); ?>" class="button button-primary" style="vertical-align: 0;">
				</form>
			</div>
		</div>

		<br style="clear: both;">

		<!-- Remote send after successful backup? -->
		<div class="bb-remote-checkbox" style="clear: both; padding-left: 4px;">
			<input type="checkbox" name="pb_backupbuddy_afterbackupremote" id="pb_backupbuddy_afterbackupremote_box"> <label id="pb_backupbuddy_afterbackupremote" for="pb_backupbuddy_afterbackupremote">Send to remote destination as part of backup process. <span id="pb_backupbuddy_backup_remotetitle"></span></label>

			<input type="hidden" name="remote_destination" id="pb_backupbuddy_backup_remotedestination">
			<input type="hidden" name="delete_after" id="pb_backupbuddy_backup_deleteafter">

		</div>
	<?php } // end disabling backups ?>
	<br style="clear: both;">

</div>

<?php
pb_backupbuddy::flush();

$listing_mode = 'default';
require_once '_backup_listing.php';

echo '<br style="clear: both;"><br><br><br>';

// Handles thickbox auto-resizing. Keep at bottom of page to avoid issues.
if ( ! wp_script_is( 'media-upload' ) ) {
	wp_enqueue_script( 'media-upload' );
	wp_print_scripts( 'media-upload' );
}
