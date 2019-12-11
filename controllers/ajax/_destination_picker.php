<?php
/**
 * Destination Picker
 *
 * @package BackupBuddy
 */

// Make sure ajax cam be accessed.
backupbuddy_core::verifyAjaxAccess();

// Can we combine these 2 files?
pb_backupbuddy::load_style( 'backupProcess.css' );
pb_backupbuddy::load_style( 'backupProcess2.css' );

$default_tab = 0;
if ( is_numeric( pb_backupbuddy::_GET( 'tab' ) ) ) {
	$default_tab = pb_backupbuddy::_GET( 'tab' );
}

// $mode is defined prior to this file load as either destination or migration.
if ( 'migration' === $mode ) {
	$picker_url = pb_backupbuddy::ajax_url( 'migration_picker' );
} else {
	if ( '1' == pb_backupbuddy::_GET( 'sending' ) || '1' == pb_backupbuddy::_GET( 'selecting' ) ) {
		$picker_url = pb_backupbuddy::ajax_url( 'destination_picker' );
	} else {
		$picker_url = pb_backupbuddy::ajax_url( 'destinationTabs' );
	}
}

$action_verb = '';
if ( '' != pb_backupbuddy::_GET( 'action_verb' ) ) {
	$action_verb = ' ' . htmlentities( pb_backupbuddy::_GET( 'action_verb' ) );
}

pb_backupbuddy::load_style( 'admin' );
pb_backupbuddy::load_style( 'destination_picker.css' );
pb_backupbuddy::load_script( 'jquery' );
pb_backupbuddy::load_script( 'jquery-ui-core' );
pb_backupbuddy::load_script( 'jquery-ui-widget' );

// Load accordion JS. Pre WP v3.3 we need to load our own JS file.
global $wp_version;
pb_backupbuddy::load_script( version_compare( $wp_version, '3.3', '<' ) ? 'jquery.ui.accordion.min.js' : 'jquery-ui-accordion' );

// Destinations may hide the add and test buttons by altering these variables.
global $pb_hide_save, $pb_hide_test;
$pb_hide_save = false;
$pb_hide_test = false;

// Load destinations class.
require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';

pb_backupbuddy::load_script( 'filetree.js' );
pb_backupbuddy::load_style( 'filetree.css' );
?>
<script>
	jQuery(function() {

		// Open settings for destination.
		jQuery( '.dest_select_open' ).click( function() {
			jQuery(this).next('.settings').stop(true, true).slideToggle(200);
		} );

		// Save a remote destination settings.
		jQuery( '.pb_backupbuddy_destpicker_save' ).click( function(e) {
			e.preventDefault();

			var pb_remote_id = 'NEW'; //jQuery(this).closest('.backupbuddy-destination-wrap').attr('data-destination_id');
			var new_title = jQuery(this).closest('form').find( '#pb_backupbuddy_title' ).val();
			jQuery(this).closest('form').find( '.pb_backupbuddy_destpicker_saveload' ).show();
			jQuery.post( '<?php echo pb_backupbuddy::ajax_url( 'remote_save' ); ?>&pb_backupbuddy_destinationid=' + pb_remote_id, jQuery(this).parent( 'form' ).serialize(),
				function(data) {
					data = jQuery.trim( data );

					if ( data == 'Destination Added.' ) {
						<?php
						if ( pb_backupbuddy::_GET( 'quickstart' ) != '' ) {
						?>
						var win = window.dialogArguments || opener || parent || top;
						win.pb_backupbuddy_quickstart_destinationselected();
						win.tb_remove();
						return false;
						<?php
						}
						?>
						destinationCount = jQuery('.destination_title').length; // Count is 0 based but includes add-new tab so int will match our new tab on reload.
						window.location.href = '<?php echo $picker_url . '&callback_data=' . pb_backupbuddy::_GET( 'callback_data' ); ?>&tab='+destinationCount+'&sending=<?php echo pb_backupbuddy::_GET( 'sending' ); ?>&selecting=<?php echo pb_backupbuddy::_GET( 'selecting' ); ?>&alert_notice=' + encodeURIComponent( 'New destination successfully added.' );
						win.scrollTo(0,0);
					} else if ( data == 'Settings saved.' ) {
						jQuery( '.pb_backupbuddy_destpicker_saveload' ).hide();
						jQuery( '.nav-tab-active' ).find( '.destination_title' ).text( new_title );
					} else {
						jQuery( '.pb_backupbuddy_destpicker_saveload' ).hide();
						alert( "Error: \n\n" + data );
					}

				}
			);

			return false;
		} );

		// Select a destionation to return to parent page.
		jQuery('.bb_destinations-existing .bb_destination-item a').click(function(e) {
			e.preventDefault();
			if ( jQuery(this).parent().hasClass( 'bb_destination-item-disabled' ) ) {
				alert( 'This remote destination is unavailable.  It is either disabled in its Advanced Settings or not compatible with this server.' );
				return false;
			}

			<?php
			if ( 'migration' === $mode ) {
				?>
				destination_url = jQuery(this).nextAll('.settings').find('.migration_url').val();
				if ( destination_url == '' ) {
					alert( 'Please enter a destination URL in the settings for the destination, test it, then save before selecting this destination.' );
					jQuery(this).nextAll('.settings').find('.migration_url').css( 'background', '#ffffe0' );
					jQuery(this).nextAll('.settings').first().stop(true, true).slideDown(200);
					return false;
				}
				<?php
			}
			?>

			destinationID = jQuery(this).attr( 'rel' );
			console.log( 'Send to destinationID: `' + destinationID + '`.' );

			<?php
			if ( '' != pb_backupbuddy::_GET( 'quickstart' ) ) {
				?>
				var win = window.dialogArguments || opener || parent || top;
				win.pb_backupbuddy_quickstart_destinationselected( destinationID );
				win.tb_remove();
				return false;
				<?php
			}
			?>

			var delete_after = jQuery( '#pb_backupbuddy_remote_delete' ).is( ':checked' );

			var win = window.dialogArguments || opener || parent || top;
			win.pb_backupbuddy_selectdestination( destinationID, jQuery(this).attr( 'title' ), '<?php echo pb_backupbuddy::_GET( 'callback_data' ); ?>', jQuery('#pb_backupbuddy_remote_delete').is(':checked'), '<?php echo $mode; ?>' );
			win.tb_remove();
			return false;
		});

		// Test a remote destination.
		jQuery( '.pb_backupbuddy_destpicker_test' ).click( function() {

			jQuery(this).children( '.pb_backupbuddy_destpicker_testload' ).show();
			jQuery.post( '<?php echo pb_backupbuddy::ajax_url( 'remote_test' ); ?>', jQuery(this).parent( 'form' ).serialize(),
				function(data) {
					jQuery( '.pb_backupbuddy_destpicker_testload' ).hide();
					data = jQuery.trim( data );
					alert( data );
				}
			);

			return false;
		} );

	});
</script>

<style type="text/css">
	.bb-dest-option .settings:before {
		background: url('<?php echo pb_backupbuddy::plugin_url(); ?>/images/dest_arrow.jpg') top right no-repeat;
		display: block;
		content: '';
		height: 9px;
		width: 17px;
		margin: 0 0 0 94.5%; /* 556px; */
	}

	#pb_backupbuddy_destpicker {
		margin: 10px;
		-webkit-border-radius: 5px;-moz-border-radius: 5px;border-radius: 5px;
		border:1px solid #C9C9C9;
		background-color:#EEEEEE;
		padding: 6px;
	}
	.pb_backupbuddy_destpicker_rowtable {
		width: 100%;
		border-collapse: collapse;
		border-top: 1px solid #C9C9C9;
	}
	.pb_backupbuddy_destpicker_rowtable tr:hover {
		/*background: #E8E8E8;*/
		cursor: pointer;

		background: #dbdbdb; /* Old browsers */
		background: -moz-radial-gradient(center, ellipse cover, #dbdbdb 0%, #eeeeee 79%); /* FF3.6+ */
		background: -webkit-gradient(radial, center center, 0px, center center, 100%, color-stop(0%,#dbdbdb), color-stop(79%,#eeeeee)); /* Chrome,Safari4+ */
		background: -webkit-radial-gradient(center, ellipse cover, #dbdbdb 0%,#eeeeee 79%); /* Chrome10+,Safari5.1+ */
		background: -o-radial-gradient(center, ellipse cover, #dbdbdb 0%,#eeeeee 79%); /* Opera 12+ */
		background: -ms-radial-gradient(center, ellipse cover, #dbdbdb 0%,#eeeeee 79%); /* IE10+ */
		background: radial-gradient(ellipse at center, #dbdbdb 0%,#eeeeee 79%); /* W3C */
		filter: progid:DXImageTransform.Microsoft.gradient( startColorstr='#dbdbdb', endColorstr='#eeeeee',GradientType=1 ); /* IE6-9 fallback on horizontal gradient */
	}
	.pb_backupbuddy_destpicker_rowtable td {
		padding: 8px;
		padding-top: 12px;
		padding-bottom: 12px;
	}
	#pb_backupbuddy_destpicker h3:focus {
		outline: 0;
	}
	.pb_backupbuddy_destpicker_type {
		width: 80px;
	}
	.pb_backupbuddy_destpicker_config {
		width: 40px;
		text-align: right;
	}
	.pb_backupbuddy_destpicker_test {
		text-align: center;
		display: inline-block;
		margin-right: 15px;
	}
	.pb_backupbuddy_destpicker_testload {
		display: none;
		vertical-align: -2px;
		margin-left: 10px;
		width: 12px;
		height: 12px;
	}
	.pb_backupbuddy_destpicker_save {
		/*width: 90px;*/
		text-align: center;
		display: inline-block;
		margin-right: 15px;
	}
	.pb_backupbuddy_destpicker_saveload,.pb_backupbuddy_destpicker_deleteload {
		display: none;
		vertical-align: -4px;
		margin-left: 5px;
		width: 16px;
		height: 16px;
	}


	.pb_backupbuddy_destpicker_newdest {
		background-color:#EEEEEE;
		width: 90%;
		padding: 10px;
		margin-left: auto;
		margin-right: auto;
		margin-bottom: 10px;
		-webkit-border-radius: 5px;-moz-border-radius: 5px;border-radius: 5px;
	}
	.pb_backupbuddy_destpicker_newdest_select {
		float: right;
		padding-top: 10px;
	}

	.form-table tbody tr th {
		/*font-size: 12px;*/
	}

	.button-primary:hover {
		color: #FFFFFF;
	}

	.bb-dest-view-files {
		display: none;
		float: right;
		margin-right: 10px;
		margin-top: 5px;
		font-style: italic;
	}
</style>
<?php
if ( 'migration' === $mode ) {
	pb_backupbuddy::alert(
		'
		<b>' . __( 'Tip', 'it-l10n-backupbuddy' ) . ':</b>
		' . __( 'If you encounter difficulty try the ImportBuddy tool. Verify the destination URL by entering the "Migration URL", and clicking "Test Settings" before proceeding.', 'it-l10n-backupbuddy' ) .
		' ' . __( 'Only Local & FTP destinations may be used for automated migrations.', 'it-l10n-backupbuddy' ) . '
	'
	);
	echo '<br>';
}

global $pb_hide_save;
if ( '' != pb_backupbuddy::_GET( 'add' ) ) {

	$destination_type = pb_backupbuddy::_GET( 'add' );

	// the following scrollTo is so that once scrolling page down to look at long list of destinations to add, coming here bumps them back to the proper place up top.
	?>
	<script>
		var win = window.dialogArguments || opener || parent || top;
		win.window.scrollTo(0,0);
	</script>
	<?php
	$destination_info = pb_backupbuddy_destinations::get_info( $destination_type );
	echo '<h3>' . $destination_info['name'] . '</h3>';
	echo '<div class="pb_backupbuddy_destpicker_id bb-dest-option" rel="NEW">';
	$settings = pb_backupbuddy_destinations::configure( array( 'type' => $destination_type ), 'add' );

	if ( false === $settings ) {
		echo 'Error #556656a. Unable to display configuration.';
	} else {
		if ( true !== $pb_hide_test ) {
			$test_button = '<a href="#" class="button secondary-button pb_backupbuddy_destpicker_test" href="#" title="Test destination settings.">Test Settings<img class="pb_backupbuddy_destpicker_testload" src="' . pb_backupbuddy::plugin_url() . '/images/loading.gif" title="Testing... This may take several seconds..."></a>&nbsp;&nbsp;';
		} else {
			$test_button = '';
		}
		if ( true !== $pb_hide_save ) {
			$save_button = '<img class="pb_backupbuddy_destpicker_saveload" src="' . pb_backupbuddy::plugin_url() . '/images/loading.gif" title="Saving... This may take a few seconds...">';
			echo $settings->display_settings( '+ Add Destination', $test_button, $save_button, 'pb_backupbuddy_destpicker_save' ); // title, before, after, class
		}
	}
	echo '</div>';
	return;
}

// Determine how many destinations we will be listing.
if ( $mode == 'migration' ) {
	$destination_list_count = 0;
	foreach ( pb_backupbuddy::$options['remote_destinations'] as $destination ) {
		if ( ( $destination['type'] != 'local' ) && ( $destination['type'] != 'ftp' ) ) { // if not local or ftp when in migration mode then skip.
			continue;
		} else {
			$destination_list_count++;
		}
	}
} else {
	$destination_list_count = count( pb_backupbuddy::$options['remote_destinations'] );
}

function pb_bb_add_box( $mode, $picker_url, $hide_back = false ) {
	?>
	<div class="bb_destinations-group bb_destinations-new">
		<h3>What kind of destination would you like to add?</h3>
		<ul>
			<?php
			$i = 0;
			foreach ( pb_backupbuddy_destinations::get_destinations_list() as $destination_name => $destination ) {
				// Never show Deployment ("site") destination here.
				if ( ( 'site' == $destination['type'] ) || ( 'live' == $destination['type'] ) ) {
					continue;
				}

				if ( $mode == 'migration' ) {
					if ( ( $destination_name != 'local' ) && ( $destination_name != 'ftp' ) && ( $destination_name != 'sftp' ) ) { // if not local or ftp when in migration mode then skip.
						continue;
					}
				}

				// Filter only showing certain destination type.
				if ( '' != pb_backupbuddy::_GET( 'filter' ) ) {
					if ( $destination_name != pb_backupbuddy::_GET( 'filter' ) ) {
						continue; // Move along to next destination.
					}
				}

				$i++;

				echo '<li class="bb_destination-item bb_destination-' . $destination_name . ' bb_destination-new-item"><a href="' . $picker_url . '&add=' . $destination_name . '&callback_data=' . pb_backupbuddy::_GET( 'callback_data' ) . '&sending=' . pb_backupbuddy::_GET( 'sending' ) . '&selecting=' . pb_backupbuddy::_GET( 'selecting' ) . '" rel="' . $destination_name . '">' . $destination['name'] . '</a></li>';
				if ( $i >= 5 ) {
					echo '<span class="bb_destination-break"></span>';
					$i = 0;
				}
			}

			if ( false === $hide_back ) {
			?>
				<br><br>
				<a href="javascript:void(0)" class="btn btn-small btn-white btn-with-icon btn-back btn-back-add"  onClick="jQuery('.bb_destinations-new').hide(); jQuery('.bb_destinations-existing').show();"><span class="btn-icon"></span>Back to existing destinations</a>
			<?php } ?>
		</ul>
	</div>
	<?php
}


$i = 0;
if ( 'true' != pb_backupbuddy::_GET( 'show_add' ) && $destination_list_count > 0 ) {

	if ( '' != pb_backupbuddy::_GET( 'alert_notice' ) ) {
		pb_backupbuddy::alert( htmlentities( stripslashes( pb_backupbuddy::_GET( 'alert_notice' ) ) ) );
	}
	?>

	<div class="bb_actions bb_actions_after slidedown">
		<?php require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php'; ?>
		<div class="bb_destinations" style="display: block;">
			<div class="bb_destinations-group bb_destinations-existing">
				<h3>Send to one of your existing destinations?</h3><br>
				<?php if ( '' != pb_backupbuddy::_GET( 'sending' ) ) { ?>
					<label><input type="checkbox" name="delete_after" id="pb_backupbuddy_remote_delete" value="1">Delete local backup after successful delivery?</label>
					<br><br>
				<?php } ?>
				<ul>
					<?php
					foreach ( pb_backupbuddy::$options['remote_destinations'] as $destination_id => $destination ) {

						// Only show local, ftp, and sftp for migrations.
						if ( 'migration' === $mode ) {
							$hide_destinations = array(
								'local',
								'ftp',
								'sftp',
							);
							if ( ! in_array( $destination['type'], $hide_destinations, true ) ) {
								continue;
							}
						}

						// Never show Deployment ("site") or BackupBuddy Stash Live destination here.
						$hide_types = array(
							'site',
							'live',
						);
						if ( in_array( $destination['type'], $hide_types, true ) ) {
							continue;
						}

						$disabled_class = '';
						if ( isset( $destination['disabled'] ) && '1' == $destination['disabled'] ) {
							$disabled_class = 'bb_destination-item-disabled';
						}

						echo '<li class="bb_destination-item bb_destination-' . esc_attr( $destination['type'] ) . ' ' . esc_attr( $disabled_class ) . '"><a href="javascript:void(0)" title="' . esc_attr( $destination['title'] ) . '" rel="' . esc_attr( $destination_id ) . '">' . esc_html( $destination['title'] ) . '</a></li>';
					}
					?>
					<br><br><br>
					<?php if ( false === apply_filters( 'itbub_disable_add_destination_tab', false ) ) : ?>
						<a href="javascript:void(0)" class="btn btn-small btn-addnew" onClick="jQuery('.bb_destinations-existing').hide(); jQuery('.bb_destinations-new').show();">Add New Destination +</a>
					<?php endif; ?>
				</ul>
			</div>
			<?php pb_bb_add_box( $mode, $picker_url ); ?>
		</div>
	</div>
	<?php
} else { // Add Mode.
	?>
	<div style="text-align: center;">
		<?php pb_bb_add_box( $mode, $picker_url, true ); ?>
	</div>
	<br><br>
	<?php
}
?>

<?php itbub_file_icon_styles( '6px 6px', true ); ?>
