<?php
/**
 * Backup Listing View
 *
 * @package BackupBuddy
 */

?>
<script type="text/javascript">
	jQuery(function() {
		jQuery( '.pb_backupbuddy_hoveraction_sendimportbuddy' ).click( function(e) {
			<?php if ( '' == pb_backupbuddy::$options['importbuddy_pass_hash'] ) { ?>
				alert( 'You must set an ImportBuddy password via the BackupBuddy settings page before you can send this file.' );
				return false;
			<?php } ?>
			tb_show( 'BackupBuddy', '<?php echo pb_backupbuddy::ajax_url( 'destination_picker' ); ?>&callback_data=' + jQuery(this).attr('rel') + '&sending=1&TB_iframe=1&width=640&height=455', null );
			return false;
		});

		jQuery( '.pb_backupbuddy_get_importbuddy' ).click( function(e) {
			<?php
			if ( '' == pb_backupbuddy::$options['importbuddy_pass_hash'] ) {
				?>

				var password = prompt( '<?php esc_html_e( 'To download, enter a password to lock the ImportBuddy script from unauthorized access. You will be prompted for this password when you go to importbuddy.php in your browser. Since you have not defined a default password yet this will be used as your default and can be changed later from the Settings page.', 'it-l10n-backupbuddy' ); ?>' );
				if ( null != password && '' != password ) {
					window.location.href = '<?php echo pb_backupbuddy::ajax_url( 'importbuddy' ); ?>&p=' + encodeURIComponent( password );
				}
				if ( '' == password ) {
					alert( 'You have not set a default password on the Settings page so you must provide a password here to download ImportBuddy.' );
				}

				return false;
				<?php
			} else {
				?>
				var password = prompt( '<?php esc_html_e( 'To download, either enter a new password for just this download OR LEAVE BLANK to use your default ImportBuddy password (set on the Settings page) to lock the ImportBuddy script from unauthorized access.', 'it-l10n-backupbuddy' ); ?>' );
				if ( null != password ) {
					window.location.href = '<?php echo pb_backupbuddy::ajax_url( 'importbuddy' ); ?>&p=' + encodeURIComponent( password );
				}
				return false;
				<?php
			}
			?>
			return false;
		});


		// Click meta option in backup list to send a backup to a remote destination.
		jQuery( '.pb_backupbuddy_hoveraction_send' ).click( function(e) {
			tb_show( 'BackupBuddy', '<?php echo pb_backupbuddy::ajax_url( 'destination_picker' ); ?>&callback_data=' + jQuery(this).attr('rel') + '&sending=1&action_verb=to%20send%20to&TB_iframe=1&width=640&height=455', null );
			return false;
		});


		// Backup listing View Hash meta clicked.
		jQuery( '.pb_backupbuddy_hoveraction_hash' ).click( function(e) {
			tb_show( 'BackupBuddy', '<?php echo pb_backupbuddy::ajax_url( 'hash' ); ?>&callback_data=' + jQuery(this).attr('rel') + '&TB_iframe=1&width=640&height=455', null );
			return false;
		});


		// Click the meta option in the backup list to apply a note to a backup.
		jQuery( '.pb_backupbuddy_hoveraction_note' ).click( function(e) {

			var existing_note = jQuery(this).parents( 'td' ).find('.pb_backupbuddy_notetext').text();
			if ( existing_note == '' ) {
				existing_note = 'My first backup';
			}

			var note_text = prompt( '<?php esc_html_e( 'Enter a short descriptive note to apply to this archive for your reference. (175 characters max)', 'it-l10n-backupbuddy' ); ?>', existing_note );
			if ( null == note_text || '' == note_text ) {
				// User cancelled.
			} else {
				jQuery( '.pb_backupbuddy_backuplist_loading' ).show();
				jQuery.post( '<?php echo pb_backupbuddy::ajax_url( 'set_backup_note' ); ?>', { backup_file: jQuery(this).attr('rel'), note: note_text },
					function(data) {
						data = jQuery.trim( data );
						jQuery( '.pb_backupbuddy_backuplist_loading' ).hide();
						if ( data != '1' ) {
							alert( "<?php esc_html_e( 'Error', 'it-l10n-backupbuddy' ); ?>: " + data );
						}
						javascript:location.reload(true);
					}
				);
			}
			return false;
		});

	});

	function pb_backupbuddy_selectdestination( destination_id, destination_title, callback_data, delete_after, mode ) {

		if ( ( callback_data != '' ) && ( callback_data != 'delayed_send' ) ) {
			if ( callback_data == 'importbuddy.php' ) {
				window.location.href = '<?php echo pb_backupbuddy::page_url(); ?>&destination=' + destination_id + '&destination_title=' + destination_title + '&callback_data=' + callback_data;
				return false;
			}
			jQuery.post( '<?php echo pb_backupbuddy::ajax_url( 'remote_send' ); ?>', { destination_id: destination_id, destination_title: destination_title, file: callback_data, trigger: 'manual', delete_after: delete_after },
				function(data) {
					data = jQuery.trim( data );
					if ( data.charAt(0) != '1' ) {
						alert( "<?php _e( 'Error starting remote send', 'it-l10n-backupbuddy' ); ?>:" + "\n\n" + data );
					} else {
						if ( delete_after == true ) {
							var delete_alert = "<?php _e( 'The local backup will be deleted upon successful transfer as selected.', 'it-l10n-backupbuddy' ); ?>";
						} else {
							var delete_alert = '';
						}
						alert( "<?php _e( 'Your file has been scheduled to be sent now. It should arrive shortly.', 'it-l10n-backupbuddy' ); ?> <?php _e( 'You will be notified by email if any problems are encountered.', 'it-l10n-backupbuddy' ); ?>" + " " + delete_alert + "\n\n" + data.slice(1) );
						/* Try to ping server to nudge cron along since sometimes it doesnt trigger as expected. */
						jQuery.post( '<?php echo admin_url( 'admin-ajax.php' ); ?>',
							function(data) {
							}
						);
					}
				}
			);
		} else if ( callback_data == 'delayed_send' ) { // Specified a destination to send to later.
			jQuery( '#pb_backupbuddy_backup_remotedestination' ).val( destination_id );
			jQuery( '#pb_backupbuddy_backup_deleteafter' ).val( delete_after );
			jQuery( '#pb_backupbuddy_backup_remotetitle' ).html( 'Destination: "' + destination_title + '".' );
			jQuery( '#pb_backupbuddy_backup_remotetitle' ).slideDown();
		} else {
			<?php $admin_url = is_network_admin() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' ); ?>
			window.location.href = '<?php echo $admin_url; ?>?page=pb_backupbuddy_backup&custom=remoteclient&destination_id=' + destination_id;
		}
	} // end pb_backupbuddy_selectdestination().
</script>
<?php
/**
 * Incoming variables:
 *   $backups       Generated via core.php backups_list() function.
 *   $listing_mode  Should be either: default, migrate.
 */
$hover_actions = array();

// If download URL is within site root then allow downloading via web.
$backup_directory = backupbuddy_core::getBackupDirectory(); // Normalize for Windows paths.
$backup_directory = str_replace( '\\', '/', $backup_directory );
$backup_directory = rtrim( $backup_directory, '/\\' ) . '/'; // Enforce single trailing slash.

$hover_actions[ pb_backupbuddy::ajax_url( 'download_archive' ) . '&backupbuddy_backup=' ] = __( 'Download', 'it-l10n-backupbuddy' );
$hover_actions['send']       = __( 'Send', 'it-l10n-backupbuddy' );
$hover_actions['note']       = __( 'Note', 'it-l10n-backupbuddy' );
$hover_actions['zip_viewer'] = '' . __( 'Browse & Restore Files', 'it-l10n-backupbuddy' );
$hover_actions['rollback']   = __( 'Database Rollback', 'it-l10n-backupbuddy' );

$bulk_actions = array( 'delete_backup' => __( 'Delete', 'it-l10n-backupbuddy' ) );

if ( count( $backups ) === 0 ) {
	esc_html_e( 'No backups have been created yet.', 'it-l10n-backupbuddy' );
	echo '<br>';
} else {

	$columns = array(
		__( 'Local Backups', 'it-l10n-backupbuddy' ) . ' <img src="' . pb_backupbuddy::plugin_url() . '/images/sort_down.png" style="vertical-align: 0px;" title="Sorted most recent first">',
		__( 'Type', 'it-l10n-backupbuddy' ) . ' | ' . __( 'Profile', 'it-l10n-backupbuddy' ),
		__( 'File Size', 'it-l10n-backupbuddy' ),
		__( 'Status', 'it-l10n-backupbuddy' ) . pb_backupbuddy::tip( __( 'Backups are checked to verify that they are valid BackupBuddy backups and contain all of the key backup components needed to restore. Backups may display as invalid until they are completed. Click the refresh icon to re-verify the archive.', 'it-l10n-backupbuddy' ), '', false ),
	);

	pb_backupbuddy::$ui->list_table(
		$backups,
		array(
			'action'                  => pb_backupbuddy::page_url(),
			'columns'                 => $columns,
			'hover_actions'           => $hover_actions,
			'hover_action_column_key' => '0',
			'bulk_actions'            => $bulk_actions,
			'css'                     => 'width: 100%;',
		)
	);
}
?>
