<?php
// Incoming vars:
// $serial, $specialAction = '', $initRetryCount = 0, $sqlFile = ''

require_once pb_backupbuddy::plugin_path() . '/classes/backup.php';

$init_wait_retry_count = $initRetryCount;
$echoNotWrite = $echo;

// Forward all logging to this serial file.
pb_backupbuddy::set_status_serial( $serial );

if ( true == get_transient( 'pb_backupbuddy_stop_backup-' . $serial ) ) {
	pb_backupbuddy::status(
		'message',
		__( 'Backup STOPPED by user. Post backup cleanup step has been scheduled to clean up any temporary files.', 'it-l10n-backupbuddy' ),
		$serial
	);

	pb_backupbuddy::status( 'details', __( 'Loading fileoptions data instance #30...', 'it-l10n-backupbuddy' ) );
	require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
	$fileoptions_file = backupbuddy_core::getLogDirectory() . 'fileoptions/' . $serial . '.txt';
	$backup_options = new pb_backupbuddy_fileoptions( $fileoptions_file, false, $ignore_lock = true );

	if ( true !== ( $result = $backup_options->is_ok() ) ) {
		pb_backupbuddy::status(
			'error',
			sprintf(
				__( 'Unable to access fileoptions file `%s`.', 'it-l10n-backupbuddy' ),
				$fileoptions_file
			),
			$serial
		);
	}

	// Wipe backup file.
	if ( isset( $backup_options->options['archive_file'] ) && file_exists( $backup_options->options['archive_file'] ) ) { // Final zip file.
		$unlink_result = @unlink( $backup_options->options['archive_file'] );
		if ( true === $unlink_result ) {
			pb_backupbuddy::status( 'details', __( 'Deleted stopped backup ZIP file.', 'it-l10n-backupbuddy' ), $serial );
		} else {
			pb_backupbuddy::status(
				'error',
				sprintf(
					__( 'Unable to delete stopped backup file. You should delete it manually as it may be damaged from stopping mid-backup. File to delete: `%s`.', 'it-l10n-backupbuddy' ),
					$backup_options->options['archive_file']
				),
				$serial
			);
		}
	} else {
		pb_backupbuddy::status( 'details', __( 'Archive file not found. Not deleting.', 'it-l10n-backupbuddy' ), $serial );
	}

	// NOTE: fileoptions file will be wiped by periodic cleanup. We need to keep this for now...

	delete_transient( 'pb_backupbuddy_stop_backup-' . $serial );
	pb_backupbuddy::status(
		'details',
		__( 'Backup stopped by user. Any remaining processes or files will time out and be cleaned up by scheduled housekeeping functionality.', 'it-l10n-backupbuddy' ),
		$serial
	);
	pb_backupbuddy::status( 'haltScript', '', $serial ); // Halt JS on page.
}

// Make sure the serial exists.
if ( $serial != '' ) {
	pb_backupbuddy::status( 'details', __( 'Loading fileoptions data instance #29...', 'it-l10n-backupbuddy' ) );
	require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
	$fileoptions_file = backupbuddy_core::getLogDirectory() . 'fileoptions/' . $serial . '.txt';
	$waitingFileoptions = true;
	$waitingFileoptionsCount = 0;

	// In case fileoptions is not ready for proper reading then keep waiting for a bit to see if it becomes usable.
	while ( true === $waitingFileoptions ) {
		$backup_options = new pb_backupbuddy_fileoptions( $fileoptions_file, $read_only = true, $ignore_lock = true );
		$backup = &$backup_options->options;

		if ( true !== ( $result = $backup_options->is_ok() ) ) { // File does not exists or unreadable.
			if ( 0 >= $init_wait_retry_count ) {
				// Waited too long for init to complete, must be something wrong
				pb_backupbuddy::status(
					'error',
					sprintf(
						__( 'Error #8329754.  Error retrieving fileoptions file `%1$s`. Error details `%2$s`.', 'it-l10n-backupbuddy' ),
						$fileoptions_file,
						$result
					),
					$serial
				);
				pb_backupbuddy::status( 'haltScript', '', $serial );
				$waitingFileoptions = false;
				die();
			} else {
				pb_backupbuddy::status(
					'details',
					sprintf(
						__( 'Waiting for the fileoptions initialization for serial `%1$s` to complete: %2$s', 'it-l10n-backupbuddy' ),
						$serial,
						$init_wait_retry_count
					),
					$serial
				);
				pb_backupbuddy::status( 'wait_init', '', $serial );
				$waitingFileoptions = false;
			}
		} else { // File existed and was read. Make sure the data is valid.
			if ( ! is_array( $backup ) ) {
				pb_backupbuddy::status(
					'warning',
					sprintf(
						__( 'Fileoptions file did not contain an array. Waiting 1 second before trying again. Attempt %1$s of %2$s times this round.', 'it-l10n-backupbuddy' ),
						$waitingFileoptionsCount,
						backupbuddy_constants::BACKUP_STATUS_FILEOPTIONS_WAIT_COUNT_LIMIT
					),
					$serial
				);

				// If we do not output this here then status may never be shown.
				$status_lines = pb_backupbuddy::get_status( $serial, true, false, true ); // Clear file, dont unlink file, supress status retrieval msg.
				echo implode( '', $status_lines );

				sleep( 1 );
				$waitingFileoptionsCount++;
				if ( $waitingFileoptionsCount > ( backupbuddy_constants::BACKUP_STATUS_FILEOPTIONS_WAIT_COUNT_LIMIT ) ) {
					pb_backupbuddy::status(
						'error',
						sprintf(
							__( 'Error #9031b. Fileoptions file `%1$s` did not contain an array and waiting did not produce results. Contents: `%2$s`.', 'it-l10n-backupbuddy' ),
							$fileoptions_file,
							$backup
						),
						$serial
					);
					$waitingFileoptions = false;

					// If we do not output this here then status may never be shown.
					$status_lines = pb_backupbuddy::get_status( $serial, true, false, true ); // Clear file, dont unlink file, supress status retrieval msg.
					echo implode( '', $status_lines );

					die();
				}
			} else { // File is array so stop waiting.
				$waitingFileoptions = false;
			}
		}
	}

}
if ( ( $serial == '' ) || ( ! is_array( $backup ) ) ) {
	pb_backupbuddy::status(
		'error',
		sprintf(
			__( 'Error #9031. Invalid backup serial (%1$s). Please check directory permissions for your wp-content/uploads/ directory recursively, your PHP error_log for any errors, and that you have enough free disk space. If seeking support please provide this full status log and PHP error log. Fatal error. Verify this fileoptions file exists `%2$s`', 'it-l10n-backupbuddy' ),
			htmlentities( $serial ),
			$fileoptions_file
		),
		$serial
	);
	pb_backupbuddy::status( 'haltScript', '', $serial );
	die();
} else {

	// Verify init completed.
	if ( false === $backup['init_complete'] ) {
		if ( 0 >= $init_wait_retry_count ) {
			// Waited too long for init to complete, must be something wrong
			pb_backupbuddy::status(
				'error',
				sprintf(
					__( 'Error #9033: The pre-backup initialization for serial `%s` was unable save pre-backup initialization options (init_complete===false) possibly because the pre-backup initialization step did not complete. If the log indicates the pre-backup procedure did indeed complete then something prevented Solid Backups from updating the database such as an misconfigured caching plugin. Check for any errors above or in logs. Verify permissions & that there is enough server memory. See the Solid Backups "Server Information" page to help assess your server.', 'it-l10n-backupbuddy' ),
					$serial
				),
				$serial
			);
			pb_backupbuddy::status( 'haltScript', '', $serial );
		} else {
			pb_backupbuddy::status(
				'details',
				sprintf(
					__( 'Waiting for the pre-backup initialization for serial `%1$s` to complete: %2$s', 'it-l10n-backupbuddy' ),
					$serial,
					$init_wait_retry_count
				),
				$serial
			);
			pb_backupbuddy::status( 'wait_init', '', $serial );
		}
	}

	//***** Process any specialAction methods.
	if ( 'checkSchedule' == $specialAction ) {
		$has_next_scheduled = false;
		foreach( $backup['steps'] as $step ) {
			$next_scheduled = backupbuddy_core::next_scheduled(
				'process_backup',
				array(
					$serial,
					$step['function']
				),
				pb_backupbuddy_backup::CRON_GROUP
			);
			if ( ! empty( $next_scheduled ) ) {
				$has_next_scheduled = true;
				break;
			}
		}

		if ( empty( $has_next_scheduled ) ) {
			//pb_backupbuddy::status( 'details', print_r( pb_backupbuddy::_POST(), true ), $serial );
			pb_backupbuddy::status(
				'details',
				__( 'WordPress reports the next step is not currently scheduled. It is either in the process of running or went missing. If this persists, consider enabling the Advanced Setting `Reschedule Missing Crons`.', 'it-l10n-backupbuddy' ),
				$serial,
				null,
				true
			);

			if ( '1' == pb_backupbuddy::$options['backup_cron_rescheduling'] ) {
				pb_backupbuddy::status(
					'details',
					__( 'Missing cron rescheduling enabled. Attempting to add the missing schedule back in.', 'it-l10n-backupbuddy' ),
					$serial
				);

				// Schedule event.
				$cron_time = time() - 30; // Back-schedule to force to top.
				$cron_args = array( $serial );
				pb_backupbuddy::status(
					'details',
					sprintf(
						__( 'Scheduling next step to run at `%1$s` with cron hook `backupbuddy_cron` to run method `process_backup` and serial arguments `%2$s`.', 'it-l10n-backupbuddy' ),
						pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $cron_time ) ),
						implode( ',', $cron_args )
					),
					$serial
				);

				// Attempt to retry.
				$result = backupbuddy_core::trigger_async_event(
					'process_backup',
					$cron_args,
					pb_backupbuddy_backup::CRON_GROUP
				);

				if ( false === $result ) {
					pb_backupbuddy::status(
						'error',
						__( 'Unable to reschedule missing cron step. Verify that another plugin is not preventing / conflicting.', 'it-l10n-backupbuddy' ),
						$serial
					);
				} else {
					pb_backupbuddy::status( 'details', __( 'Next step rescheduled.', 'it-l10n-backupbuddy' ), $serial );
					pb_backupbuddy::status( 'startAction', 'cronPass', $serial ); // Resets the time this action began so we will not attempt re-scheduling a second time for a bit.
					pb_backupbuddy::status( 'cronParams', base64_encode( json_encode( array( 'time' => $cron_time, 'tag' => backupbuddy_constants::CRON_HOOK, 'method' => 'process_backup', 'args' => $cron_args ) ) ), $serial );
				}
			}

		} elseif ( true === $next_scheduled ) {
			pb_backupbuddy::status(
				'details',
				__( 'Checked cron schedule. Process is currently running.', 'it-l10n-backupbuddy' ),
				$serial,
				null,
				true
			);
		} else {
			$timeFromNow = $next_scheduled - time();
			pb_backupbuddy::status(
				'details',
				sprintf(
					__( 'Checked cron schedule. Next run: `%1$s`. %2$s seconds from now.', 'it-l10n-backupbuddy' ),
					$next_scheduled,
					$timeFromNow
				),
				$serial,
				null,
				true
			);
			if ( $timeFromNow < 0 ) { // Time cron was to run is in the past.
				$missedTime = abs( $timeFromNow );
				if ( ( '' != pb_backupbuddy::$options['backup_cron_passed_force_time'] ) && ( is_numeric( pb_backupbuddy::$options['backup_cron_passed_force_time'] ) ) && ( $missedTime > pb_backupbuddy::$options['backup_cron_passed_force_time'] ) ) {
					pb_backupbuddy::status(
						'details',
						sprintf(
							__( 'Cron has passed the force time of `%1$s` seconds. Currently `%2$s` seconds overdue. Forcing cron to spawn now. If the problem persists the host may be blocking the cron loopback, possibly due to mod_security settings.', 'it-l10n-backupbuddy' ),
							pb_backupbuddy::$options['backup_cron_passed_force_time'],
							$missedTime
						),
						$serial
					);

					backupbuddy_core::maybe_spawn_cron();
				} else { // No forcing enabled in settings.
					if ( $missedTime > 8 ) {
						pb_backupbuddy::status(
							'details',
							__( 'If the backup hangs at this point try enabling the advanced setting "Force cron if behind by X seconds". If it still persists the host may be blocking the cron loopback, possibly due to mod_security settings.', 'it-l10n-backupbuddy' ),
							$serial
						);
					}
				}
			}
		}

	}
	//***** End processing any specialAction methods.


	//***** Begin outputting status of the current step.
	$zipRunTime = 0;
	//error_log( print_r( $backup['steps'], true ) );
	foreach( $backup['steps'] as $step ) {
		if ( ( $step['start_time'] != -1 ) && ( $step['start_time'] != 0 ) && ( $step['finish_time'] == 0 ) ) { // A step isnt mark to skip, has begun but has not finished. This should not happen but the WP cron is funky. Wait a while before continuing.
			$thisRunTime = ( time() - $step['start_time'] );

			// For database dump step output the SQL file current size.
			if ( 'backup_create_database_dump' == $step['function'] ) {
				if ( '' != $sqlFile ) {
					$sqlFilename = $sqlFile;
					/* else {
						$sqlFilename = 'db_1.sql';
					} */
					$sql_file = $backup['temp_directory'] . $sqlFilename;

					if ( file_exists( $sql_file ) ) {
						@clearstatcache( true, $sql_file );
						$sql_filesize = filesize( $sql_file );
					} else { // No SQL file yet.
						$sql_filesize = 0;
					}

					$writeSpeedText = '';
					if ( $thisRunTime > 0 ) {
						$writeSpeed = $sql_filesize / $thisRunTime;
						$writeSpeedText = sprintf(
							__('. Approximate creation speed: %1$s/sec [%2$s]', 'it-l10n-backupbuddy' ),
							pb_backupbuddy::$format->file_size( $writeSpeed ),
							$writeSpeed
						);
					}

					pb_backupbuddy::status(
						'details',
						sprintf(
							__( 'Current database dump file (%1$s) size: %2$s [%3$s]. %4$s', 'it-l10n-backupbuddy' ),
							basename( $sql_file ),
							pb_backupbuddy::$format->file_size( $sql_filesize ),
							$sql_filesize,
							$writeSpeedText
						),
						$serial
					);
				}
			}

			if ( 'backup_zip_files' == $step['function'] ) {
				$zipRunTime = $thisRunTime;
			}

			if ( 'backup_zip_files' != $step['function'] ) {
				pb_backupbuddy::status(
					'details',
					sprintf(
						__( 'Waiting for function `%1$s` to complete. Started %2$s seconds ago.', 'it-l10n-backupbuddy' ),
						$step['function'],
						round( $thisRunTime, 2 )
					),
					$serial
				);
				if ( ( time() - $step['start_time'] ) > 300 ) {
					pb_backupbuddy::status(
						'warning',
						sprintf(
							__( 'The function %1$s` is taking an abnormally long time to complete (%2$s seconds). The backup may have failed. If it does not increase in the next few minutes it most likely timed out. See the Status Log for details.', 'it-l10n-backupbuddy' ),
							$step['function'],
							round( $thisRunTime, 2 )
						),
						$serial
					);
				}
			}

		} elseif ( $step['start_time'] == 0 ) { // Step that has not started yet.
			// Do nothing.
		} elseif ( $step['start_time'] == -1 ) { // Step marked for skipping (backup stop button hit).
			// Do nothing.
		} else { // Last case: Finished. Skip.
			// Do nothing.
		}
	}
	//***** End outputting status of the current step.


	//***** Begin output of temp zip file size.
	$temporary_zip_directory = backupbuddy_core::getBackupDirectory() . 'temp_zip_' . $serial . '/';
	if ( file_exists( $temporary_zip_directory ) ) { // Temp zip file.
		if ( false === ( $directory = opendir( $temporary_zip_directory ) ) ) {
			pb_backupbuddy::status(
				'warning',
				__( 'Warning #98328923: Unable to open temporary zip directory. This may be a temporary timing delay until the directory is ready.', 'it-l10n-backupbuddy' )
			);
		} else {
			while( $file = readdir( $directory ) ) {
				if ( ( $file != '.' ) && ( $file != '..' ) && ( $file != 'exclusions.txt' ) && ( !preg_match( '/.*\.txt/', $file ) ) && ( !preg_match( '/pclzip.*\.gz/', $file) ) ) {
					$stats = stat( $temporary_zip_directory . $file );

					$writeSpeedText = '';
					if ( $zipRunTime > 0 ) {
						$writeSpeed = $stats['size'] / $zipRunTime;
						$writeSpeedText = sprintf(
							__('. Approximate speed: %1$s/sec. Elapsed : %2$s secs.', 'it-l10n-backupbuddy' ),
							pb_backupbuddy::$format->file_size( $writeSpeed ),
							round( $zipRunTime, 2 )
						);
					}
					pb_backupbuddy::status(
						'details',
						sprintf(
							__( 'Temporary ZIP file size: %1$s %2$s', 'it-l10n-backupbuddy' ),
							pb_backupbuddy::$format->file_size( $stats['size'] ),
							$writeSpeedText
						),
						$serial
					);
					pb_backupbuddy::status( 'archiveSize', pb_backupbuddy::$format->file_size( $stats['size'] ), $serial );
				}
			}
			closedir( $directory );
		}
		unset( $directory );
	}
	//***** End output of temp zip file size.


	// Output different stuff to the browser depending on whether backup is finished or not.
	if ( $backup['finish_time'] > 0 ) { // BACKUP FINISHED.
		// OUTPUT COMPLETED ZIP FINAL SIZE.
		if ( 'pull' != $backup['deployment_direction'] ) { // not a pull type deployment.
			if( file_exists( $backup['archive_file'] ) ) { // Final zip file.
				$stats = stat( $backup['archive_file'] );
				pb_backupbuddy::status( 'details', '--- ' . __( 'New PHP process.', 'it-l10n-backupbuddy' ), $serial );
				pb_backupbuddy::status(
					'details',
					sprintf(
						__( 'Completed backup final ZIP file size: %s', 'it-l10n-backupbuddy' ),
						pb_backupbuddy::$format->file_size( $stats['size'] )
					),
					$serial
				);
				pb_backupbuddy::status( 'archiveSize', pb_backupbuddy::$format->file_size( $stats['size'] ), $serial );
				$backup_finished = true;
			} else {
				$purposeful_deletion = false;
				foreach( $backup['steps'] as $step ) {
					if ( $step['function'] == 'send_remote_destination' ) {
						if ( $step['args'][1] == true ) {
							pb_backupbuddy::status( 'details', __( 'Option to delete local backup after successful send enabled so local file deleted.', 'it-l10n-backupbuddy' ) );
							$purposeful_deletion = true;
							break;
						}
					}
				}
				if ( $purposeful_deletion !== true ) {
					pb_backupbuddy::status(
						'error',
						__( 'Backup reports success but unable to access final ZIP file. Verify permissions and ownership. If the error persists insure that server is properly configured with suphp and proper ownership & permissions.', 'it-l10n-backupbuddy' ),
						$serial
					);
				}
			}
		}

		if ( 'deployment_pulling' == $backup['trigger'] ) {
			pb_backupbuddy::status(
				'message',
				sprintf(
					__( 'Remote backup snapshot successfully completed in %1$s with Solid Backups %2$s. Trigger: `%3$s`.', 'it-l10n-backupbuddy' ),
					pb_backupbuddy::$format->time_duration( $backup['finish_time'] - $backup['start_time'] ),
					pb_backupbuddy::settings( 'version' ),
					$backup['trigger']
				),
				$serial
			);
			pb_backupbuddy::status( 'milestone', 'finish_deploymentPullBackup', $serial );
		} else {
			pb_backupbuddy::status(
				'message',
				sprintf(
					__( 'Backup successfully completed in %1$s. with Solid Backups v%2$s. Trigger: `%3$s`.', 'it-l10n-backupbuddy' ),
					pb_backupbuddy::$format->time_duration( $backup['finish_time'] - $backup['start_time'] ),
					pb_backupbuddy::settings( 'version' ),
					$backup['trigger']
				),
				$serial
			);
			pb_backupbuddy::status( 'milestone', 'finish_backup', $serial );
		}
	} else { // NOT FINISHED
		//$return_status .= '!' . pb_backupbuddy::$format->localize_time( time() ) . "|~|0|~|0|~|ping\n";
	}


	//***** Begin getting status log information.
	if ( '' != $backup['deployment_log'] ) {
		//error_log( print_r( $backup, true ) );
		if ( 'push' == $backup['deployment_direction'] ) {
			pb_backupbuddy::status(
				'details',
				sprintf(
					__( 'About to retrieve push deployment status log from `%s`...', 'it-l10n-backupbuddy' ),
					$backup['deployment_log']
				),
				$serial
			);
			pb_backupbuddy::status(
				'details',
				__( '*** Begin External Log Section (ImportBuddy)', 'it-l10n-backupbuddy' ),
				$serial
			);
		} elseif ( 'pull' == $backup['deployment_direction'] ) {
			pb_backupbuddy::status(
				'details',
				sprintf(
					__( 'About to retrieve pull deployment status log from `%s`...', 'it-l10n-backupbuddy' ),
					$backup['deployment_log']
				),
				$serial
			);
			pb_backupbuddy::status(
				'details',
				__( '*** Begin External Log Section (Remote backup or ImportBuddy)', 'it-l10n-backupbuddy' ),
				$serial
			);
		} else {
			pb_backupbuddy::status(
				'error',
				__( 'Error #84377834: Deployment log set but direction missing.', 'it-l10n-backupbuddy' ),
				$serial
			);
		}
	}

	// Get local status log and output it.
	//echo "\nEND_DEPLOY\n"; // In case prior end deploy signal did not go out.
	$status_lines = pb_backupbuddy::get_status( $serial, true, false, true ); // Clear file, dont unlink file, supress status retrieval msg.
	echo implode( '', $status_lines );

	// DEPLOYMENT OUTPUT.
	if ( '' != $backup['deployment_log'] ) {

        $sslverify = true;
        if ( '0' == pb_backupbuddy::$options['deploy_sslverify'] ) {
            $sslverify = false;
            pb_backupbuddy::status( 'details', __( 'Skipping SSL cert verification based on advanced settings.', 'it-l10n-backupbuddy' ) );
        }

		echo "\nSTART_DEPLOY\n";
		if ( 'push' == $backup['deployment_direction'] ) { // *** PUSH
			$response = wp_remote_get(
				$backup['deployment_log'],
				array(
					'method'      => 'GET',
					'timeout'     => 20, // X second delay. Should not take long to get a plain txt log file.
					'redirection' => 5,
					'httpversion' => '1.0',
					'blocking'    => true,
                    'sslverify'   => $sslverify,
					'headers'     => array(),
					'body'        => null,
					'cookies'     => array()
				)
			);
			if( is_wp_error( $response ) ) { // Retrieval failed. Some kind of error.
				$error = $response->get_error_message();
				pb_backupbuddy::status(
					'error',
					sprintf(
						__( 'Error #4389894. Could not retrieve remote deployment log. Details: `%1$s`. Full response: %2$s', 'it-l10n-backupbuddy' ),
						$error,
						print_r( $response, true )
					),
					$serial
				);
			} else {
				if ( '200' == $response['response']['code'] ) {
					//error_log( print_r( $response, true ) );
					echo $response['body'];
					if ( '' == $response['body'] ) {
						esc_html_e( 'Warning #8003e: Nothing returned. This may be normal.', 'it-l10n-backupbuddy' );
					}
				} elseif ( '404' == $response['response']['code'] ) {
					// do nothing
					$message = sprintf(
						__( 'Warning #8003b: 404 retrieving remote log (PUSH) `%s`. This may or may not be normal.', 'it-l10n-backupbuddy' ),
						$backup['deployment_log']
					);
					pb_backupbuddy::status( 'warning', $message, $serial );
					echo $message;
				} else {
					$message = sprintf(
						__( 'Error #58947954. Could not retrieve remote deployment log. Response code: `%s`.', 'it-l10n-backupbuddy' ),
						$response['response']['code']
					);
					pb_backupbuddy::status( 'error', $message, $serial );
					echo $message;
				}
			}

		} elseif ( 'pull' == $backup['deployment_direction'] ) { // *** PULL
			if ( false === strpos( $backup['deployment_log'], 'http' ) ) { // Get log via API using serial.

				require_once( pb_backupbuddy::plugin_path() . '/classes/remote_api.php' );

				if ( false === ( $response = backupbuddy_remote_api::remoteCall( $backup['deployment_destination'], 'getBackupStatus', array( 'serial' => $backup['deployment_log'] ), 30, array(), true ) ) ) {
					$message = sprintf(
						__( 'Error #783283378. Unable to get remove backup status log with serial `%s` via remote API.', 'it-l10n-backupbuddy' ),
						$backup['deployment_log']
					);
					pb_backupbuddy::status( 'error', $message, $serial );
					echo $message;
				} else {
					if ( '' == $response ) {
						esc_html_e( 'Warning #8003c: Nothing returned. This may be normal.', 'it-l10n-backupbuddy' );
					} else {
						echo $response;
					}

					// Check if remote backup has reported it finished. If so then we need to set it locally that it is done.
					if ( false !== strpos( $response, 'finish_deploymentPullBackup' ) ) {
						set_transient( 'backupbuddy_deployPullBackup_finished', $backup['deployment_log'], time() + 120 ); // Set transient storing this completion so backup process can check for this in subsequent run. Touching fileoptions would be not recommended at this point due to possible collissions.
					}
				}

			} else { // get log from URL

				$sslverify = true;
				if ( '0' == pb_backupbuddy::$options['deploy_sslverify'] ) {
					$sslverify = false;
					pb_backupbuddy::status(
						'details',
						__( 'Skipping SSL cert verification based on advanced settings.', 'it-l10n-backupbuddy' )
					 );
				}

				$response = wp_remote_get(
					$backup['deployment_log'],
					array(
						'method'      => 'GET',
						'timeout'     => 10, // X second delay. Should not take long to get a plain txt log file.
						'redirection' => 5,
						'httpversion' => '1.0',
						'blocking'    => true,
                        'sslverify'   => $sslverify,
						'headers'     => array(),
						'body'        => null,
						'cookies'     => array()
					)
				);
				error_log( 'remote_got' . print_r( $response, true ) );
				if( is_wp_error( $response ) ) { // Loopback failed. Some kind of error.
					$error = $response->get_error_message();
					$message = sprintf(
						__( 'Error retrieving remote deployment log. Details: `%s`.', 'it-l10n-backupbuddy' ),
						$error
					);
					pb_backupbuddy::status( 'error', $message, $serial );
					echo $message;
				} else {
					if ( '200' == $response['response']['code'] ) {
						echo $response['body'];
						if ( '' == $response['body'] ) {
							esc_html_e( 'Warning #8003d: Nothing returned. This may be normal.', 'it-l10n-backupbuddy' );
						}
					} elseif ( '404' == $response['response']['code'] ) {
						// do nothing
						$message = sprintf(
							__( 'Warning #8003a: 404 retrieving remote log (PULL) `%s`. This may or may not be normal.', 'it-l10n-backupbuddy' ),
							$backup['deployment_log']
						);
						pb_backupbuddy::status( 'warning', $message, $serial );
						echo $message;
					} else {
						$message = sprintf(
							__( 'Error retrieving remote deployment log. Response code: %s`.', 'it-l10n-backupbuddy' ),
							$response['response']['code']
						);
						pb_backupbuddy::status( 'error', $message, $serial );
						echo $message;
					}
				}

			} // end get lof from URL.

		} else { // *** UNKNOWN

			$message = __( 'Error #272823443: Deployment log set but direction missing.', 'it-l10n-backupbuddy' );
			pb_backupbuddy::status( 'error', $message, $serial );
			echo $message;

		}
		echo "\nEND_DEPLOY\n";
	}

	// Queue up a pong for the next response.
	pb_backupbuddy::status( 'message', __( 'Pong! Server replied.', 'it-l10n-backupbuddy' ), $serial );
}

return;

