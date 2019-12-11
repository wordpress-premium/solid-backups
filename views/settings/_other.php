<?php
/**
 * Other Settings View
 *
 * @package BackupBuddy
 */

?>
<script type="text/javascript">
	function pb_status_append( json ) {
		if( 'undefined' === typeof statusBox ) { // No status box yet so may need to create it.
			statusBox = jQuery( '#pb_backupbuddy_status' );
			if( statusBox.length == 0 ) { // No status box yet so suppress.
				return;
			}
		}

		if ( 'string' == ( typeof json ) ) {
			backupbuddy_log( json );
			console.log( 'Status log received string: ' + json );
			return;
		}

		// Used in BackupBuddy _backup-perform.php and ImportBuddy _header.php
		json.date = new Date();
		json.date = new Date(  ( json.time * 1000 ) + json.date.getTimezoneOffset() * 60000 );
		var seconds = json.date.getSeconds();
		if ( seconds < 10 ) {
			seconds = '0' + seconds;
		}
		json.date = backupbuddy_hourpad( json.date.getHours() ) + ':' + json.date.getMinutes() + ':' + seconds;

		triggerEvent = 'backupbuddy_' + json.event;


		// Log non-text events.
		if ( ( 'details' !== json.event ) && ( 'message' !== json.event ) && ( 'error' !== json.event ) ) {
			//console.log( 'Non-text event `' + triggerEvent + '`.' );
		} else {
			//console.log( json.data );
		}
		//console.log( 'trigger: ' + triggerEvent );

		backupbuddy_log( json );


	} // End function pb_status_append().

	// left hour pad with zeros
	function backupbuddy_hourpad(n) {
		return ("0" + n).slice(-2);
	}

	// Used in BackupBuddy _backup-perform.php and ImportBuddy _header.php and _rollback.php
	function backupbuddy_log( json ) {

		message = '';

		if ( 'string' == ( typeof json ) ) {
			message = "-----------\t\t-------\t-------\t" + json;
		} else {
			message = json.date + '.' + json.u + " \t" + json.run + "sec \t" + json.mem + "MB\t" + json.data;
		}

		statusBox.append( "\r\n" + message );
		statusBox.scrollTop( statusBox[0].scrollHeight - statusBox.height() );

	}
</script>

<?php
/**
 * Plugin Information Display
 *
 * @param string $plugin_slug  Slug of plugin.
 * @param array  $data         Array of Data.
 */
function plugin_information( $plugin_slug, $data ) {
	$plugin_path = $data['path'];
	?>

	<textarea readonly="readonly" rows="7" cols="65" wrap="off" style="width: 100%;">
	<?php
		readfile( $plugin_path . '/history.txt' );
	?>
	</textarea>
	<script type="text/javascript">
		jQuery(function() {
			jQuery("#pluginbuddy_<?php echo esc_html( $plugin_slug ); ?>_debugtoggle").click(function() {
				jQuery("#pluginbuddy_<?php echo esc_html( $plugin_slug ); ?>_debugtoggle_div").slideToggle();
			});
		});
	</script>
	<?php
} // end plugin_information().

// User forced cleanup.
if ( '' != pb_backupbuddy::_GET( 'cleanup_now' ) ) {

	printf( '<h3>%s</h3>', esc_html__( 'Cleanup Procedure Status Log', 'it-l10n-backupbuddy' ) );
	global $pb_backupbuddy_js_status;
	$pb_backupbuddy_js_status = true;
	echo pb_backupbuddy::status_box( 'Performing cleanup procedures...' );
	echo '<script>jQuery("#pb_backupbuddy_status_wrap").show();</script>';
	echo '<div id="pb_backupbuddy_cleanup_working"><img src="' . pb_backupbuddy::plugin_url() . '/images/loading_large.gif" title="Working... Please wait as this may take a moment..."></div>';

	pb_backupbuddy::flush();

	require_once pb_backupbuddy::plugin_path() . '/classes/housekeeping.php';

	if ( 'true' == pb_backupbuddy::_GET( 'transients_only' ) ) {
		backupbuddy_housekeeping::cleanup_transients( true ); // true deletes unexpiring, expired, or corrupt.
	} elseif ( 'true' == pb_backupbuddy::_GET( 'transients_only_expired' ) ) {
		backupbuddy_housekeeping::cleanup_transients( false ); // false only cleans expired or corrupt.
	} else {
		backupbuddy_housekeeping::run_periodic( 0 ); // 0 cleans up everything even if not very old.
	}

	echo '<script>jQuery("#pb_backupbuddy_cleanup_working").hide();';
	echo 'jQuery( function(){
			backupbuddy_log( "Cleanup Completed!" );
		});';
	echo '</script>';
}

// Delete temporary files directory.
if ( '' != pb_backupbuddy::_GET( 'delete_tempfiles_now' ) ) {
	backupbuddy_core::deleteAllDataFiles();
}

// Reset log.
if ( '' != pb_backupbuddy::_GET( 'reset_log' ) ) {
	if ( file_exists( $log_file ) ) {
		@unlink( $log_file );
	}
	if ( file_exists( $log_file ) ) { // Didnt unlink.
		pb_backupbuddy::alert( 'Unable to clear log file. Please verify permissions on file `' . $log_file . '`.' );
	} else { // Unlinked.
		pb_backupbuddy::alert( 'Cleared log file.' );
	}
}

// Reset disalerts.
if ( '' != pb_backupbuddy::_GET( 'reset_disalerts' ) ) {
	pb_backupbuddy::$options['disalerts'] = array();
	pb_backupbuddy::save();

	pb_backupbuddy::alert( 'Dismissed alerts have been reset. They may now be visible again.' );
}

// Cancel all running backups.
if ( '1' == pb_backupbuddy::_GET( 'cancel_running_backups' ) ) {
	require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';

	$fileoptions_directory = backupbuddy_core::getLogDirectory() . 'fileoptions/';
	$files                 = glob( $fileoptions_directory . '*.txt' );
	if ( ! is_array( $files ) ) {
		$files = array();
	}
	$cancel_count = 0;
	for ( $x = 0; $x <= 3; $x++ ) { // Try this a few times since there may be race conditions on an open file.
		foreach ( $files as $file ) {
			pb_backupbuddy::status( 'details', 'Fileoptions instance #383.' );

			$backup_options = new pb_backupbuddy_fileoptions( $file, false );
			$result         = $backup_options->is_ok();
			if ( true !== $result ) {
				pb_backupbuddy::status( 'error', 'Error retrieving fileoptions file `' . $file . '`. Err 335353266.' );
			} else {
				if ( empty( $backup_options->options['finish_time'] ) || ( false !== $backup_options->options['finish_time'] && '-1' != $backup_options->options['finish_time'] ) ) {
					$backup_options->options['finish_time'] = -1; // Force marked as cancelled by user.
					$backup_options->save();
					$cancel_count++;
				}
			}
		}
		sleep( 1 );
	}

	pb_backupbuddy::alert( 'Marked all timed out or running backups & transfers as officially cancelled (`' . $cancel_count . '` total found).' );
}
?>

<h1><?php esc_html_e( 'Version History', 'it-l10n-backupbuddy' ); ?></h1>
<?php
plugin_information(
	pb_backupbuddy::settings( 'slug' ), array(
		'name' => pb_backupbuddy::settings( 'name' ),
		'path' => pb_backupbuddy::plugin_path(),
	)
);
?>

<br style="clear: both;"><br><br>
<h1><?php esc_html_e( 'Housekeeping & Troubleshooting', 'it-l10n-backupbuddy' ); ?></h1>
<?php esc_html_e( 'BackupBuddy automatically cleans up after itself on a regular basis. You may force various cleanup procedures to happen sooner or troubleshoot some uncommon issues using the tools below.', 'it-l10n-backupbuddy' ); ?>
<br><br>
<div>

	<?php echo '<h3>' . esc_html__( 'Cleanup', 'it-l10n-backupbuddy' ) . '</h3>'; ?>
	<a href="<?php echo pb_backupbuddy::page_url(); ?>&cleanup_now=true&tab=3" class="button secondary-button"><?php esc_html_e( 'Cleanup old/temp data & perform daily housekeeping now', 'it-l10n-backupbuddy' ); ?>*</a>
	&nbsp;
	<a href="<?php echo pb_backupbuddy::page_url(); ?>&delete_tempfiles_now=true&tab=3" class="button secondary-button"><?php esc_html_e( 'Delete ALL data files (including resetting Stash Live)', 'it-l10n-backupbuddy' ); ?>*</a>
	&nbsp;
	<br><br>

	<?php echo '<h3>' . esc_html__( 'Transients', 'it-l10n-backupbuddy' ) . '</h3>'; ?>
	<a href="<?php echo pb_backupbuddy::page_url(); ?>&cleanup_now=true&transients_only_expired=true&tab=3" class="button secondary-button"><?php esc_html_e( 'Cleanup Transients (expiring & corrupt only)', 'it-l10n-backupbuddy' ); ?>*</a>
	&nbsp;
	<a href="<?php echo pb_backupbuddy::page_url(); ?>&cleanup_now=true&transients_only=true&tab=3" class="button secondary-button"><?php esc_html_e( 'Cleanup Transients (expiring, corrupt, & purge non-expiring)', 'it-l10n-backupbuddy' ); ?>*</a>
	<br><br>

	<?php echo '<h3>' . esc_html__( 'Misc.', 'it-l10n-backupbuddy' ) . '</h3>'; ?>
	<a href="<?php echo pb_backupbuddy::page_url(); ?>&reset_disalerts=true&tab=3" class="button secondary-button"><?php esc_html_e( 'Reset dismissed alerts (' . count( pb_backupbuddy::$options['disalerts'] ) . ')', 'it-l10n-backupbuddy' ); ?></a>
	&nbsp;
	<a href="<?php echo pb_backupbuddy::page_url(); ?>&cancel_running_backups=1&tab=3" class="button secondary-button"><?php esc_html_e( 'Force Cancel of all backups & transfers', 'it-l10n-backupbuddy' ); ?></a>
	&nbsp;
	<a href="javascript:void(0);" class="button secondary-button" onClick="jQuery( '#backupbuddy-extra-log' ).toggle();"><?php esc_html_e( 'Show Extraneous Log (do NOT send to support)', 'it-l10n-backupbuddy' ); ?></a>
	&nbsp;
	<a href="javascript:void(0);" class="button secondary-button" onClick="jQuery( '#backupbuddy-remoteapi-log' ).toggle();"><?php esc_html_e( 'Show Remote API Log (incoming calls)', 'it-l10n-backupbuddy' ); ?></a>
	<br><br>

</div>
<br style="clear: both;">

<br><br><br>

<div id="backupbuddy-extra-log" style="display: none;">
	<h3><?php esc_html_e( 'Extraneous Log - Do not send to support unless asked', 'it-l10n-backupbuddy' ); ?></h3>

	<b>Anything logged here is typically not important. Only provide to tech support if specifically requested.</b> By default only errors are logged. Enable Full Logging on the <a href="?page=pb_backupbuddy_settings&tab=1">Advanced Settings</a> tab.
	<br><br>
	<?php
	echo '<textarea readonly="readonly" style="width: 100%;" wrap="off" cols="65" rows="7" id="backupbuddy_logFile">';
	echo '*** Loading log file. Please wait ...';
	echo '</textarea>';
	?>
	<a href="<?php echo pb_backupbuddy::page_url(); ?>&reset_log=true&tab=3" class="button secondary-button"><?php esc_html_e( 'Clear Log', 'it-l10n-backupbuddy' ); ?></a>
	<br><br><br>
</div>

<br><br><br>
<div id="backupbuddy-remoteapi-log" style="display: none;">
	<h3><?php esc_html_e( 'Remote API Log (incoming calls)', 'it-l10n-backupbuddy' ); ?></h3>

	<br><br>
	<?php
	echo '<textarea readonly="readonly" style="width: 100%;" wrap="off" cols="65" rows="7" id="backupbuddy_remoteapi_logFile">';
	echo '*** Loading log file. Please wait ...';
	echo '</textarea>';
	?>
	<a href="<?php echo pb_backupbuddy::page_url(); ?>&reset_log=true&tab=3" class="button secondary-button"><?php esc_html_e( 'Clear Log', 'it-l10n-backupbuddy' ); ?></a>
	<br><br><br>
</div>
