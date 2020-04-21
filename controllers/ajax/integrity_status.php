<?php
/**
 * Display backup integrity status.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$serial = pb_backupbuddy::_GET( 'serial' );
$serial = str_replace( '/\\', '', $serial );
pb_backupbuddy::load();
pb_backupbuddy::$ui->ajax_header();

require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
pb_backupbuddy::status( 'details', 'Fileoptions instance #27.' );
$options_file   = backupbuddy_core::getLogDirectory() . 'fileoptions/' . $serial . '.txt';
$backup_options = new pb_backupbuddy_fileoptions( $options_file, true );
$result         = $backup_options->is_ok();
if ( true !== $result ) {
	pb_backupbuddy::alert( __( 'Unable to access fileoptions data file.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
	die();
}

$integrity = $backup_options->options['integrity'];

$start_time  = 'Unknown';
$finish_time = 'Unknown';
if ( isset( $backup_options->options['start_time'] ) ) {
	$start_time = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $backup_options->options['start_time'] ) ) . ' <span class="description">(' . pb_backupbuddy::$format->time_ago( $backup_options->options['start_time'] ) . ' ago)</span>';
	if ( $backup_options->options['finish_time'] > 0 ) {
		$finish_time = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $backup_options->options['finish_time'] ) ) . ' <span class="description">(' . pb_backupbuddy::$format->time_ago( $backup_options->options['finish_time'] ) . ' ago)</span>';
	} else { // unfinished.
		$finish_time = '<i>Unfinished</i>';
	}
}


// ***** BEGIN TESTS AND RESULTS.
if ( isset( $integrity['status_details'] ) ) { // $integrity['status_details'] is NOT array (old, pre-3.1.9).
	echo '<h3>Integrity Technical Details</h3>';
	echo '<textarea style="width: 100%; height: 175px;" wrap="off">';
	foreach ( $integrity as $item_name => $item_value ) {
		$item_value = str_replace( '<br />', '<br>', $item_value );
		$item_value = str_replace( '<br><br>', '<br>', $item_value );
		$item_value = str_replace( '<br>', "\n     ", $item_value );
		echo $item_name . ' => ' . $item_value . "\n";
	}
	echo '</textarea><br><br><b>Note:</b> It is normal to see several "file not found" entries as BackupBuddy checks for expected files in multiple locations, expecting to only find each file once in one of those locations.';
} else { // $integrity['status_details'] is array.

	echo '<br>';

	if ( isset( $integrity['status_details'] ) ) { // PRE-v4.0 Tests.
		/**
		 * Pretty Results
		 *
		 * @param bool $value  Pass/Fail boolean value.
		 *
		 * @return string  Pass/Fail HTML string.
		 */
		function pb_pretty_results( $value ) {
			if ( true === $value ) {
				return '<span class="pb_label pb_label-success">Pass</span>';
			}
			return '<span class="pb_label pb_label-important">Fail</span>';
		}

		// The tests & their status..
		$tests   = array();
		$tests[] = array( 'BackupBackup data file exists', pb_pretty_results( $integrity['status_details']['found_dat'] ) );
		$tests[] = array( 'Database SQL file exists', pb_pretty_results( $integrity['status_details']['found_sql'] ) );
		if ( 'full' == $integrity['detected_type'] ) { // Full backup.
			$tests[] = array( 'WordPress wp-config.php exists (full/files backups only)', pb_pretty_results( $integrity['status_details']['found_wpconfig'] ) );
		} elseif ( 'files' == $integrity['detected_type'] ) { // Files only backup.
			$tests[] = array( 'WordPress wp-config.php exists (full/files backups only)', pb_pretty_results( $integrity['status_details']['found_wpconfig'] ) );
		} else { // DB only.
			$tests[] = array( 'WordPress wp-config.php exists (full/files backups only)', '<span class="pb_label pb_label-success">N/A</span>' );
		}
	} else { // 4.0+ Tests.
		$tests = array();
		if ( isset( $integrity['tests'] ) ) {
			foreach ( (array) $integrity['tests'] as $test ) {
				if ( true === $test['pass'] ) {
					$status_text = '<span class="pb_label pb_label-success">Pass</span>';
				} else {
					$status_text = '<span class="pb_label pb_label-important">Fail</span>';
				}
				$tests[] = array( $test['test'], $status_text );
			}
		}
	}

	$columns = array(
		__( 'Integrity Test', 'it-l10n-backupbuddy' ),
		__( 'Status', 'it-l10n-backupbuddy' ),
	);

	pb_backupbuddy::$ui->list_table(
		$tests,
		array(
			'columns' => $columns,
			'css'     => 'width: 100%; min-width: 200px;',
		)
	);

} // end $integrity['status_details'] is an array.

echo '<br><br>';
// ***** END TESTS AND RESULTS.
// Output meta info table (if any).
$meta_info = isset( $integrity['file'] ) ? backupbuddy_core::getZipMeta( backupbuddy_core::getBackupDirectory() . $integrity['file'] ) : array();
if ( false === $meta_info ) {
	echo '<i>No meta data found in zip comment. Skipping meta information display.</i>';
} else {
	pb_backupbuddy::$ui->list_table(
		$meta_info,
		array(
			'columns' => array( 'Backup Details', 'Value' ),
			'css'     => 'width: 100%; min-width: 200px;',
		)
	);
}
echo '<br><br>';


// ***** BEGIN STEPS.
$steps   = array();
$steps[] = array( 'Start Time', $start_time, '' );
if ( isset( $backup_options->options['steps'] ) ) {
	foreach ( $backup_options->options['steps'] as $step ) {
		if ( isset( $step['finish_time'] ) && 0 != $step['finish_time'] ) {

			// Step name.
			if ( 'backup_create_database_dump' == $step['function'] ) {
				if ( count( $step['args'][0] ) === 1 ) {
					$step_name = 'Database dump (breakout: ' . $step['args'][0][0] . ')';
				} else {
					$step_name = 'Database dump';
				}
			} elseif ( 'backup_zip_files' == $step['function'] ) {
				$zip_time = 0;
				if ( isset( $backup_options->options['steps']['backup_zip_files'] ) ) {
					$zip_time = $backup_options->options['steps']['backup_zip_files'];
				}

				// Calculate write speed in MB/sec for this backup.
				if ( '0' == $zip_time ) { // Took approx 0 seconds to backup so report this speed.
					$write_speed = '> ' . pb_backupbuddy::$format->file_size( $backup_options->options['integrity']['size'] );
				} else {
					$write_speed = '';
					if ( 0 != $zip_time ) { // This should have passed in the first condition.
						$write_speed = pb_backupbuddy::$format->file_size( $backup_options->options['integrity']['size'] / $zip_time ) . '/sec';
					}
				}
				$step_name = 'Zip archive creation (Write speed: ' . $write_speed . ')';
			} elseif ( 'post_backup' == $step['function'] ) {
				$step_name = 'Post-backup cleanup';
			} elseif ( 'integrity_check' == $step['function'] ) {
				$step_name = 'Integrity Check';
			} else {
				$step_name = $step['function'];
			}

			// Step time taken.
			$seconds = (int) ( $step['finish_time'] - $step['start_time'] );
			if ( $seconds < 1 ) {
				$step_time = '< 1 second';
			} else {
				$step_time = $seconds . ' seconds';
			}

			// Compile details for this step into array.
			$steps[] = array(
				$step_name,
				$step_time,
				$step['attempts'],
			);

		}
	} // End foreach.
} else { // End if serial in array is set.
	$step_times[] = 'unknown';
} // End if serial in array is NOT set.

// Total overall time from initiation to end.
if ( isset( $backup_options->options['finish_time'] ) && isset( $backup_options->options['start_time'] ) && 0 != $backup_options->options['finish_time'] && 0 != $backup_options->options['start_time'] ) {
	$seconds = ( $backup_options->options['finish_time'] - $backup_options->options['start_time'] );
	if ( $seconds < 1 ) {
		$total_time = '< 1 second';
	} else {
		$total_time = $seconds . ' seconds';
	}
} else {
	$total_time = '<i>Unknown</i>';
}
$steps[] = array( 'Finish Time', $finish_time, '' );
$steps[] = array(
	'<b>Total Overall Time</b>',
	$total_time,
	'',
);

$columns = array(
	__( 'Backup Steps', 'it-l10n-backupbuddy' ),
	__( 'Time', 'it-l10n-backupbuddy' ),
	__( 'Attempts', 'it-l10n-backupbuddy' ),
);

if ( count( $steps ) === 0 ) {
	esc_html_e( 'No step statistics were found for this backup.', 'it-l10n-backupbuddy' );
} else {
	pb_backupbuddy::$ui->list_table(
		$steps,
		array(
			'columns' => $columns,
			'css'     => 'width: 100%; min-width: 200px;',
		)
	);
}
echo '<br><br>';
// ***** END STEPS.
$trigger = 'Unknown trigger';
if ( isset( $backup_options->options['trigger'] ) ) {
	$trigger = $backup_options->options['trigger'];
}
if ( isset( $integrity['scan_time'] ) ) {
	$scanned = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $integrity['scan_time'] ) );
	echo ucfirst( $trigger ) . " backup {$integrity['file']} last scanned {$scanned}.";
}
echo '<br><br><br>';

echo '<a class="button secondary-button" onclick="jQuery(\'#pb_backupbuddy_advanced_debug\').slideToggle();">Display Advanced Debugging</a>';
echo '<div id="pb_backupbuddy_advanced_debug" style="display: none;">From options file: `' . $options_file . '`.<br>';
echo '<textarea style="width: 100%; height: 400px;" wrap="on">';
echo print_r( $backup_options->options, true );
echo '</textarea><br><br>';
echo '</div><br><br>';


pb_backupbuddy::$ui->ajax_footer();
die();
