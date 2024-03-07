<?php
/**
 * Settings Page Controller
 *
 * @package BackupBuddy
 */

?>

<style type="text/css">
	.backupbuddy-meta-link-wrap a.show-settings {
		float: right;
		margin: 0 0 0 6px;
	}
	#screen-meta-links .backupbuddy-meta-link-wrap a {
		background: none;
	}
	#screen-meta-links .backupbuddy-meta-link-wrap a::after {
		content: '';
		margin-right: 5px;
	}
</style>

<?php
pb_backupbuddy::$ui->title( __( 'Settings', 'it-l10n-backupbuddy' ), true, false );
backupbuddy_core::versions_confirm();

$data = array(); // To pass to view.

// Reset settings to defaults EXCEPT log serial an optionally except remote destinations.
if ( pb_backupbuddy::_POST( 'reset_defaults' ) != '' ) {
	pb_backupbuddy::verify_nonce();

	// Keep log serial.
	$old_log_serial = pb_backupbuddy::$options['log_serial'];

	$keep_dest_note          = '';
	$remote_destinations     = pb_backupbuddy::$options['remote_destinations'];
	pb_backupbuddy::$options = pb_backupbuddy::settings( 'default_options' );
	if ( '1' == pb_backupbuddy::_POST( 'keep_destinations' ) ) {
		pb_backupbuddy::$options['remote_destinations'] = $remote_destinations;
		$keep_dest_note                                 = ' ' . __( 'Remote destination settings were not reset.', 'it-l10n-backupbuddy' );
	}

	// Replace log serial.
	pb_backupbuddy::$options['log_serial'] = $old_log_serial;

	pb_backupbuddy::save();

	$skip_temp_generation = true;
	backupbuddy_core::verify_directories( $skip_temp_generation ); // Re-verify directories such as backup dir, temp, etc.
	$reset_note = __( 'Plugin settings have been reset to defaults.', 'it-l10n-backupbuddy' );
	pb_backupbuddy::alert( $reset_note . $keep_dest_note );
	backupbuddy_core::addNotification( 'settings_reset', 'Plugin settings reset', $reset_note . $keep_dest_note );
}

/* BEGIN VERIFYING BACKUP DIRECTORY */
if ( isset( $_POST['pb_backupbuddy_backup_directory'] ) ) {
	$backup_directory = pb_backupbuddy::_POST( 'pb_backupbuddy_backup_directory' );
	if ( '' == $backup_directory ) { // blank so set to default.
		$backup_directory = backupbuddy_core::_getBackupDirectoryDefault();
	}
	$backup_directory          = str_replace( '\\', '/', $backup_directory );
	$backup_directory          = rtrim( $backup_directory, '/\\' ) . '/'; // Enforce single trailing slash.
	$prevent_backup_dir_change = false;

	if ( '/' !== substr( $backup_directory, 0, 1 ) && '\\\\' !== substr( $backup_directory, 0, 2 ) && ':' !== substr( $backup_directory, 1, 1 ) ) {
		pb_backupbuddy::alert( 'Error #3893983: Invalid custom path format. Must be a valid Linux of Windows path beginning with either /, \\\\, or X: (where X is drive letter). Resetting back to prevoius setting.', true );
		$prevent_backup_dir_change                = true;
		$_POST['pb_backupbuddy_backup_directory'] = backupbuddy_core::getBackupDirectory(); // Set back to previous value (aka unchanged).
		if ( backupbuddy_core::getBackupDirectory() === backupbuddy_core::_getBackupDirectoryDefault() ) {
			$_POST['pb_backupbuddy_backup_directory'] = '';
		}
	}

	if ( false === $prevent_backup_dir_change ) {
		$die = false;
		pb_backupbuddy::anti_directory_browsing( $backup_directory, $die );
		if ( ! file_exists( $backup_directory ) ) {
			pb_backupbuddy::alert( 'Error #4838594589: Selected backup directory does not exist and it could not be created. Verify the path is correct or manually create the directory and set proper permissions. Reset to previous path.', true );
			$_POST['pb_backupbuddy_backup_directory'] = backupbuddy_core::getBackupDirectory(); // Set back to previous value (aka unchanged).
			$prevent_backup_dir_change                = true;
		}
	}

	if ( backupbuddy_core::getBackupDirectory() !== $backup_directory && true !== $prevent_backup_dir_change ) { // Directory differs. Needs updated in post var. Give messages here as this value is going to end up being saved.
		$old_backup_dir = backupbuddy_core::getBackupDirectory();
		$new_backup_dir = $backup_directory;

		// Move all files from old backup to new.
		$old_backups_moved = 0;
		$old_backups       = glob( $old_backup_dir . 'backup*.zip' );
		if ( ! is_array( $old_backups ) || empty( $old_backups ) ) { // On failure glob() returns false or an empty array depending on server settings so normalize here.
			$old_backups = array();
		}
		foreach ( $old_backups as $old_backup ) {
			if ( false === rename( $old_backup, $new_backup_dir . basename( $old_backup ) ) ) {
				pb_backupbuddy::alert( 'ERROR: Unable to move backup "' . basename( $old_backup ) . '" to new storage directory. Manually move it or delete it for security and to prevent it from being backed up within backups.', true );
			} else { // rename success.
				$old_backups_moved++;
				$serial = backupbuddy_core::get_serial_from_file( basename( $old_backup ) );

				// Move dat file too.
				$old_dat = substr( $old_backup, 0, -4 ) . '.dat'; // Swap .zip with .dat.
				if ( file_exists( $old_dat ) ) {
					rename( $old_dat, $new_backup_dir . basename( $old_dat ) );
				}

				pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #21...' );
				require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
				$fileoptions_files = glob( backupbuddy_core::getLogDirectory() . 'fileoptions/*.txt' );
				if ( ! is_array( $fileoptions_files ) ) {
					$fileoptions_files = array();
				}
				foreach ( $fileoptions_files as $fileoptions_file ) {
					$backup_options = new pb_backupbuddy_fileoptions( $fileoptions_file );
					$result         = $backup_options->is_ok();
					if ( true !== $result ) {
						pb_backupbuddy::status( 'error', __( 'Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
						continue;
					}

					if ( isset( $backup_options->options[ $serial ] ) ) {
						if ( isset( $backup_options->options['archive_file'] ) ) {
							$backup_options->options['archive_file'] = str_replace( $old_backup_dir, $new_backup_dir, $backup_options->options['archive_file'] );
						}
					}
					$backup_options->save();
					unset( $backup_options );
				}
			}
		}

		if ( '' == pb_backupbuddy::_POST( 'pb_backupbuddy_backup_directory' ) ) { // Blank default.
			$_POST['pb_backupbuddy_backup_directory'] = '';
		} else {
			$_POST['pb_backupbuddy_backup_directory'] = $backup_directory;
		}
		pb_backupbuddy::alert( 'Your backup storage directory has been updated from "' . esc_attr( $old_backup_dir ) . '" to "' . esc_attr( $new_backup_dir ) . '". ' . $old_backups_moved . ' backup(s) have been moved to the new location. You should perform a manual backup to verify that your backup storage directory changes perform as expected.' );
	}
}
/* END VERIFYING BACKUP DIRECTORY */


/* BEGIN DISALLOWING DEFAULT IMPORT/REPAIR PASSWORD */
if ( 'myp@ssw0rd' === strtolower( pb_backupbuddy::_POST( 'pb_backupbuddy_importbuddy_pass_hash' ) ) ) {
	pb_backupbuddy::alert( 'Warning: The example password is not allowed for security reasons for ImportBuddy. Please choose another password.' );
	$_POST['pb_backupbuddy_importbuddy_pass_hash'] = '';
}
/* END DISALLOWING DEFAULT IMPORT/REPAIR PASSWORD */


/* BEGIN VERIFYING PASSWORD CONFIRMATIONS MATCH */
$importbuddy_pass_match_fail = false;
if ( pb_backupbuddy::_POST( 'pb_backupbuddy_importbuddy_pass_hash' ) !== pb_backupbuddy::_POST( 'pb_backupbuddy_importbuddy_pass_hash_confirm' ) ) {
	pb_backupbuddy::alert( 'Error: The provided Importer password and confirmation do not match. Please make sure you type the password and re-type it correctly.' );
	$_POST['pb_backupbuddy_importbuddy_pass_hash']         = '';
	$_POST['pb_backupbuddy_importbuddy_pass_hash_confirm'] = '';
	$importbuddy_pass_match_fail                           = true;
}
/* END VERIFYING PASSWORD CONFIRMATIONS MATCH */


/* BEGIN REPLACING IMPORTBUDDY WITH VALUE OF ACTUAL HASH */
if ( isset( $_POST['pb_backupbuddy_importbuddy_pass_hash'] ) && '' == $_POST['pb_backupbuddy_importbuddy_pass_hash'] ) { // Clear out length if setting to blank.
	pb_backupbuddy::$options['importbuddy_pass_length'] = 0;
	pb_backupbuddy::$options['importbuddy_pass_hash']   = ''; // Clear out hash when emptying.
}
if ( '' != str_replace( '_', '', pb_backupbuddy::_POST( 'pb_backupbuddy_importbuddy_pass_hash' ) ) && md5( pb_backupbuddy::_POST( 'pb_backupbuddy_importbuddy_pass_hash' ) ) !== pb_backupbuddy::$options['importbuddy_pass_hash'] ) {
	pb_backupbuddy::$options['importbuddy_pass_length']    = strlen( pb_backupbuddy::_POST( 'pb_backupbuddy_importbuddy_pass_hash' ) );
	$_POST['pb_backupbuddy_importbuddy_pass_hash']         = md5( pb_backupbuddy::_POST( 'pb_backupbuddy_importbuddy_pass_hash' ) );
	$_POST['pb_backupbuddy_importbuddy_pass_hash_confirm'] = '';
} else { // Keep the same.
	if ( true !== $importbuddy_pass_match_fail ) { // keep the same.
		$_POST['pb_backupbuddy_importbuddy_pass_hash']         = pb_backupbuddy::$options['importbuddy_pass_hash'];
		$_POST['pb_backupbuddy_importbuddy_pass_hash_confirm'] = '';
	}
}
// Set importbuddy dummy text to display in form box. Equal length to the provided password.
$data['importbuddy_pass_dummy_text']                   = str_pad( '', pb_backupbuddy::$options['importbuddy_pass_length'], '_' );
$_POST['pb_backupbuddy_importbuddy_pass_hash_confirm'] = ''; // Always clear confirmation after processing it.

// Run periodic cleanup to make sure high security mode changes are applied.
$lock_mode = pb_backupbuddy::$options['lock_archives_directory'];
if ( isset( $_POST['pb_backupbuddy_lock_archives_directory'] ) ) {
	$lock_mode = $_POST['pb_backupbuddy_lock_archives_directory'];
}
if ( pb_backupbuddy::$options['lock_archives_directory'] !== $lock_mode ) { // Setting changed.
	pb_backupbuddy::status( 'details', '`lock_archives_directory` Settting changed.' );
	require_once pb_backupbuddy::plugin_path() . '/classes/housekeeping.php';
	if ( '1' === $lock_mode ) {
		backupbuddy_housekeeping::enable_high_security_mode();
	} else {
		backupbuddy_housekeeping::disable_high_security_mode( true );
	}
}

/* BEGIN SAVE MULTISITE SPECIFIC SETTINGS IN SET OPTIONS SO THEY ARE AVAILABLE GLOBALLY */
if ( is_multisite() ) {
	// Save multisite export option to the global site/network options for global retrieval.
	$options                     = get_site_option( 'pb_' . pb_backupbuddy::settings( 'slug' ) );
	$options['multisite_export'] = pb_backupbuddy::_POST( 'pb_backupbuddy_multisite_export' );
	update_site_option( 'pb_' . pb_backupbuddy::settings( 'slug' ), $options );
	unset( $options );
}
/* END SAVE MULTISITE SPECIFIC SETTINGS IN SET OPTIONS SO THEY ARE AVAILABLE GLOBALLY */

// Load settings view.
pb_backupbuddy::load_view( 'settings', $data );
