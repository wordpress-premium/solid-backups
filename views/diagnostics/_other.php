<?php
/**
 * Other Settings View
 *
 * @package BackupBuddy
 */

/**
 * Plugin Information Display
 *
 * @param string $plugin_slug  Slug of plugin.
 * @param array  $data         Array of Data.
 */
function backupbuddy_plugin_information( $plugin_slug, $data ) {
	$plugin_path = $data['path'];
	?>

	<textarea readonly="readonly" rows="7" cols="65" wrap="off" style="width: 100%;">
	<?php
		readfile( $plugin_path . '/history.txt' );
	?>
	</textarea>
	<?php
} // end backupbuddy_plugin_information().

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

	echo '<script>jQuery( "#pb_backupbuddy_cleanup_working" ).hide();';
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
	$log_file = backupbuddy_core::getLogDirectory() . 'log-' . pb_backupbuddy::$options['log_serial'] . '.txt';
	if ( file_exists( $log_file ) ) {
		@unlink( $log_file );
	}
	if ( file_exists( $log_file ) ) { // Didnt unlink.
		pb_backupbuddy::alert( 'Unable to clear log file. Please verify permissions on file `' . $log_file . '`.', true, '', '', '', array( 'class' => 'below-h2' ) );
	} else { // Unlinked.
		pb_backupbuddy::alert( 'Cleared log file.', false, '', '', '', array( 'class' => 'below-h2' ) );
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
	$cancelled = array();
	for ( $x = 0; $x <= 3; $x++ ) { // Try this a few times since there may be race conditions on an open file.
		foreach ( $files as $file ) {
			pb_backupbuddy::status( 'details', 'Fileoptions instance #383.' );

			$backup_options = new pb_backupbuddy_fileoptions( $file, false );
			$result         = $backup_options->is_ok();
			if ( true !== $result ) {
				pb_backupbuddy::status( 'error', 'Error retrieving fileoptions file `' . $file . '`. Err 335353266.' );
			} else {
				if ( empty( $backup_options->options['serial'] ) || is_null( $backup_options->options['serial'] ) ) {
					continue; // txt file is old or invalid.
				}
				if ( in_array( $backup_options->options['serial'], $cancelled, true ) ) {
					continue; // Already cancelled this pass.
				}
				if ( ! empty( $backup_options->options['finish_time'] ) && $backup_options->options['finish_time'] > 0 ) {
					continue; // Backup is already done.
				}
				if ( ! empty( $backup_options->options['finish_time'] ) && '-1' == $backup_options->options['finish_time'] ) {
					continue; // Backup already cancelled.
				}

				$backup_options->options['finish_time'] = -1; // Force marked as cancelled by user.
				$backup_options->save();
				$cancelled[] = $backup_options->options['serial'];
			}
		}
		sleep( 1 );
	}

	if ( count( $cancelled ) ) {
		pb_backupbuddy::alert( 'Marked all timed out or running backups & transfers as officially cancelled (' . count( $cancelled ) . ' total found).', false, '', '', '', array( 'class' => 'below-h2' ) );
	} else {
		pb_backupbuddy::alert( 'No timed out or running backups found to cancel.', false, '', '', '', array( 'class' => 'below-h2' ) );
	}
}
?>

<h1><?php esc_html_e( 'Version History', 'it-l10n-backupbuddy' ); ?></h1>
<?php
backupbuddy_plugin_information(
	pb_backupbuddy::settings( 'slug' ), array(
		'name' => pb_backupbuddy::settings( 'name' ),
		'path' => pb_backupbuddy::plugin_path(),
	)
);
?>
<div class="backupbuddy-help-troubleshooting">
	<h1><?php esc_html_e( 'Housekeeping & Troubleshooting', 'it-l10n-backupbuddy' ); ?></h1>

	<?php esc_html_e( 'BackupBuddy automatically cleans up after itself on a regular basis. You may force various cleanup procedures to happen sooner or troubleshoot some uncommon issues using the tools below.', 'it-l10n-backupbuddy' ); ?>

	<div class="backupbuddy-cleanup-controls">
		<h3><?php esc_html_e( 'Cleanup', 'it-l10n-backupbuddy' ); ?></h3>
		<a href="<?php echo esc_attr( pb_backupbuddy::page_url() ); ?>&cleanup_now=true&tab=other" class="button secondary-button"><?php esc_html_e( 'Cleanup old/temp data & perform daily housekeeping now', 'it-l10n-backupbuddy' ); ?>*</a>
		&nbsp;
		<a href="<?php echo esc_attr( pb_backupbuddy::page_url() ); ?>&delete_tempfiles_now=true&tab=other" class="button secondary-button"><?php esc_html_e( 'Delete ALL data files (including resetting Stash Live)', 'it-l10n-backupbuddy' ); ?>*</a>
		&nbsp;
	</div>

	<div class="backupbuddy-transient-controls">
		<h3><?php esc_html_e( 'Transients', 'it-l10n-backupbuddy' ); ?></h3>
		<a href="<?php echo esc_attr( pb_backupbuddy::page_url() ); ?>&cleanup_now=true&transients_only_expired=true&tab=other" class="button secondary-button"><?php esc_html_e( 'Cleanup Transients (expiring & corrupt only)', 'it-l10n-backupbuddy' ); ?>*</a>
		&nbsp;
		<a href="<?php echo esc_attr( pb_backupbuddy::page_url() ); ?>&cleanup_now=true&transients_only=true&tab=other" class="button secondary-button"><?php esc_html_e( 'Cleanup Transients (expiring, corrupt, & purge non-expiring)', 'it-l10n-backupbuddy' ); ?>*</a>
	</div>

	<div class="backupbuddy-misc-controls">
		<h3><?php esc_html_e( 'Misc.', 'it-l10n-backupbuddy' ); ?></h3>
		<a href="<?php echo esc_attr( pb_backupbuddy::page_url() ); ?>&reset_disalerts=true&tab=other" class="button secondary-button"><?php esc_html_e( 'Reset dismissed alerts (' . count( pb_backupbuddy::$options['disalerts'] ) . ')', 'it-l10n-backupbuddy' ); ?></a>
		&nbsp;
		<a href="<?php echo esc_attr( pb_backupbuddy::page_url() ); ?>&cancel_running_backups=1&tab=other" class="button secondary-button"><?php esc_html_e( 'Force Cancel of all backups & transfers', 'it-l10n-backupbuddy' ); ?></a>
		&nbsp;
		<a href="#extraneous-log" class="button secondary-button"><?php esc_html_e( 'Show Extraneous Log (do NOT send to support)', 'it-l10n-backupbuddy' ); ?></a>
		&nbsp;
		<a href="#remoteapi-log" class="button secondary-button"><?php esc_html_e( 'Show Remote API Log (incoming calls)', 'it-l10n-backupbuddy' ); ?></a>
	</div>

	<div id="backupbuddy-extra-log" style="display: none;">
		<h3><?php esc_html_e( 'Extraneous Log - Do not send to support unless asked', 'it-l10n-backupbuddy' ); ?></h3>

		<b>Anything logged here is typically not important. Only provide to tech support if specifically requested.</b> By default only errors are logged. Enable Full Logging on the <a href="?page=pb_backupbuddy_settings&tab=advanced">Advanced Settings</a> tab.
		<?php
		echo '<textarea readonly="readonly" style="width: 100%;" wrap="off" cols="65" rows="7" id="backupbuddy_logFile">';
		echo '*** Loading log file. Please wait ...';
		echo '</textarea>';
		?>
		<a href="<?php echo esc_attr( pb_backupbuddy::page_url() ); ?>&reset_log=true&tab=other" class="button secondary-button"><?php esc_html_e( 'Clear Log', 'it-l10n-backupbuddy' ); ?></a>
	</div>

	<div id="backupbuddy-remoteapi-log" style="display: none;">
		<h3><?php esc_html_e( 'Remote API Log (incoming calls)', 'it-l10n-backupbuddy' ); ?></h3>
		<?php
		echo '<textarea readonly="readonly" style="width: 100%;" wrap="off" cols="65" rows="7" id="backupbuddy_remoteapi_logFile">';
		echo '*** Loading log file. Please wait ...';
		echo '</textarea>';
		?>
		<a href="<?php echo esc_attr( pb_backupbuddy::page_url() ); ?>&reset_log=true&tab=other" class="button secondary-button"><?php esc_html_e( 'Clear Log', 'it-l10n-backupbuddy' ); ?></a>
	</div>
</div>
