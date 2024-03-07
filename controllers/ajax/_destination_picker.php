<?php
/**
 * Destination Picker.
 *
 * This appears as a modal on the Backup Profiles page.
 *
 * @package BackupBuddy
 */

// Make sure ajax cam be accessed.
backupbuddy_core::verifyAjaxAccess();

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
	if ( pb_backupbuddy::_GET( 'tab' ) ) {
		$picker_url .= esc_attr( pb_backupbuddy::_GET( 'tab' ) );
	}
}

$action_verb = '';
if ( '' != pb_backupbuddy::_GET( 'action_verb' ) ) {
	$action_verb = ' ' . htmlentities( pb_backupbuddy::_GET( 'action_verb' ) );
}

pb_backupbuddy::load_script( 'jquery' );
pb_backupbuddy::load_script( 'jquery-ui-core' );
pb_backupbuddy::load_script( 'jquery-ui-widget' );
pb_backupbuddy::load_script( 'jquery-ui-accordion' );

// Destinations may hide the add and test buttons by altering these variables.
global $pb_hide_save, $pb_hide_test;
$pb_hide_save = false;
$pb_hide_test = false;

// Load destinations class.
require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';

pb_backupbuddy::load_script( 'filetree.js' );
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
			jQuery(this).closest('form').find( '.pb_backupbuddy_destpicker_saveload' ).removeClass( 'hidden' );
			jQuery.post( '<?php echo pb_backupbuddy::ajax_url( 'remote_save' ); ?>&pb_backupbuddy_destinationid=' + pb_remote_id, jQuery(this).closest( 'form' ).serialize(),
				function(data) {

					if ( data.success ) {
						if ( 'added' === data.status ) {
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
							window.location.href = '<?php echo $picker_url . '&callback_data=' . esc_attr( pb_backupbuddy::_GET( 'callback_data' ) ); ?>&tab='+data.new_tab+'&sending=<?php echo esc_attr( pb_backupbuddy::_GET( 'sending' ) ); ?>&selecting=<?php esc_attr_e( pb_backupbuddy::_GET( 'selecting' ) ); ?>&alert_notice=' + encodeURIComponent( 'New destination successfully added.' );
							win.scrollTo(0,0);
						} else if ( 'saved' === data.status ) {
							jQuery( '.pb_backupbuddy_destpicker_saveload' ).addClass( 'hidden');
							jQuery( '.nav-tab-active' ).find( '.destination_title' ).text( new_title );
						}
					} else {
						jQuery( '.pb_backupbuddy_destpicker_saveload' ).addClass( 'hidden' );
						alert( "Error: \n\n" + data.error );
					}

				}
			);

			return false;
		} );

		// Select a destionation to return to parent page.
		jQuery( '.bb_destinations-existing .bb_destination-item a' ).on( 'click', function(e) {
			e.preventDefault();
			if ( jQuery( this ).parent().hasClass( 'bb_destination-item-disabled' ) ) {
				alert( 'This remote destination is unavailable. It is either disabled in its Advanced Settings or not compatible with this server.' );
				return false;
			}

			<?php
			if ( 'migration' === $mode ) {
				?>
				destination_url = jQuery( this ).nextAll('.settings').find('.migration_url').val();
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
			// console.log( 'Send to destinationID: `' + destinationID + '`.' );

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

			var win = window.dialogArguments || parent || opener || top;
			win.pb_backupbuddy_selectdestination( destinationID, jQuery(this).attr( 'title' ), '<?php esc_attr_e( pb_backupbuddy::_GET( 'callback_data' ) ); ?>', jQuery('#pb_backupbuddy_remote_delete').is(':checked'), '<?php echo $mode; ?>' );
			win.tb_remove();
			return false;
		});

		// Test a remote destination.
		jQuery( '.pb_backupbuddy_destpicker_test' ).click( function() {

			jQuery(this).children( '.pb_backupbuddy_destpicker_testload' ).removeClass( 'hidden' );
			jQuery.post( '<?php echo pb_backupbuddy::ajax_url( 'remote_test' ); ?>', jQuery(this).closest( 'form' ).serialize(),
				function(data) {
					jQuery( '.pb_backupbuddy_destpicker_testload' ).addClass( 'hidden' );
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
		background: url('<?php echo pb_backupbuddy::plugin_url(); ?>/assets/dist/images/dest_arrow.jpg') top right no-repeat;
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
	.pb_backupbuddy_destpicker_save {
		/*width: 90px;*/
		text-align: center;
		display: inline-block;
		margin-right: 15px;
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
		' . __( 'If you encounter difficulty try the Importer tool. Verify the destination URL by entering the "Migration URL", and clicking "Test Settings" before proceeding.', 'it-l10n-backupbuddy' ) .
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
	echo '<h2 class="solid-backups-form-heading">' . $destination_info['name'] . '</h2>';
	echo '<div class="pb_backupbuddy_destpicker_id bb-dest-option" rel="NEW">';
	$settings = pb_backupbuddy_destinations::configure( array( 'type' => $destination_type ), 'add' );

	if ( false === $settings ) {
		echo 'Error #556656a. Unable to display configuration.';
	} else {
		if ( true !== $pb_hide_test ) {
			$test_button = '<button class="button button-secondary button-spinner secondary-button pb_backupbuddy_destpicker_test" title="Test destination settings.">Test Settings<span class="pb_backupbuddy_destpicker_testload hidden">&nbsp;</span></button>&nbsp;&nbsp;';
		} else {
			$test_button = '';
		}
		if ( true !== $pb_hide_save ) {
			$save_button = '<span class="pb_backupbuddy_destpicker_saveload hidden">&nbsp;</span>';
			echo $settings->display_settings(
				__( '+ Add Destination', 'it-l10n-backupbuddy' ),
				'<div class="solid-backups-form-buttons form-buttons form-buttons-no-margin-x">' . $test_button, $save_button . '</div>',
				'pb_backupbuddy_destpicker_save'
			); // title, before, after, class
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
		<h3 class="solid-title-medium">What kind of destination would you like to add?</h3>
		<ul class="bb_destination-list bb_destination-list-iframe">
			<?php
			$i = 0;
			$list = '';
			$lower_priority_list = '';
			foreach ( pb_backupbuddy_destinations::get_destinations_list() as $destination_name => $destination ) {
				if ( ! empty( $destination['deprecated'] ) ) {
					continue;
				}

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

				if ( ! empty( $destination['name'] ) ) {
					$i++;

					$category = isset( $destination['category'] ) ? $destination['category'] : false;

					$item = sprintf(
						'<li class="bb_destination-item bb_destination-%s bb_destination-new-item"><a href="%s&add=%s&callback_data=%s&sending=%s&selecting=%s" rel="%s">%s</a></li>',
						esc_attr( $destination_name ),
						$picker_url,
						esc_attr( $destination_name ),
						esc_attr( pb_backupbuddy::_GET( 'callback_data' ) ),
						esc_attr( pb_backupbuddy::_GET( 'sending' ) ),
						esc_attr( pb_backupbuddy::_GET( 'selecting' ) ),
						esc_attr( $destination_name ),
						esc_html( $destination['name'] )
					);

					if ( 'best' == $category ) {
						$list .= $item;
					} else {
						$lower_priority_list .= $item;
					}
				}
			}
			echo wp_kses_post( $list . $lower_priority_list );
			?>
		</ul>
		<?php if ( false === $hide_back ) : ?>
			<a href="javascript:void(0)" class="button button-secondary tn btn-small btn-white btn-with-icon btn-back btn-back-add"  onClick="jQuery('.bb_destinations-new').hide(); jQuery('.bb_destinations-existing').show();"><span class="btn-icon"></span>Back to existing destinations</a>
		<?php endif; ?>
	</div>
	<?php
}


$i = 0;
if ( 'true' != pb_backupbuddy::_GET( 'show_add' ) && $destination_list_count > 0 ) {

	if ( '' != pb_backupbuddy::_GET( 'alert_notice' ) ) {
		pb_backupbuddy::alert( htmlentities( stripslashes( pb_backupbuddy::_GET( 'alert_notice' ) ) ) );
	}

	$is_importbuddy = isset( $_GET['callback_data'] ) && 'importbuddy.php' === sanitize_text_field( wp_unslash( $_GET['callback_data'] ) );
	?>

	<div class="bb_actions bb_actions_after slidedown">
		<?php require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php'; ?>
		<div class="bb_destinations bb_destinations-backup-perform" style="display: block;">
			<div class="bb_destinations-group bb_destinations-existing">
				<h3 class="solid-title-medium"><?php esc_html_e( 'Send to one of your existing destinations?', 'it-l10n-backupbuddy' ); ?></h3>
				<?php if ( '' != pb_backupbuddy::_GET( 'sending' ) && ! $is_importbuddy ) { ?>
					<label><input type="checkbox" name="delete_after" id="pb_backupbuddy_remote_delete" value="1">Delete local backup after successful delivery?</label>
				<?php } ?>
				<ul class="bb_destination-list bb_destination-list-iframe">
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

						// Never show Deployment ("site") or Solid Backups Stash Live destination here.
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

						printf(
							'<li class="bb_destination-item bb_destination-%s %s"><a href="#send-to-%s" title="%s" rel="%s">%s</a></li>',
							esc_attr( $destination['type'] ),
							esc_attr( $disabled_class ),
							esc_attr( $destination['type'] ),
							esc_attr( $destination['title'] ),
							esc_attr( $destination_id ),
							esc_html( $destination['title'] )
						);
					}
					?>
				</ul>
				<?php if ( false === apply_filters( 'itbub_disable_add_destination_tab', false ) ) : ?>
						<a href="javascript:void(0)" class="button button-primary btn-addnew" onClick="jQuery('.bb_destinations-existing').hide(); jQuery('.bb_destinations-new').show();"><?php esc_html_e( 'Add New Destination +', 'it-l10n-backupbuddy' ); ?></a>
					<?php endif; ?>
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

itbub_file_icon_styles( '6px 6px', true );
