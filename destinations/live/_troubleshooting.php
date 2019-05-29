<?php
// @since 7.0.3.12
// @author Dustin Bolton

/*
$recent_send_fails = array(
	'file' => '',
	'log_tail' => '',
	'error' => '',
);
*/




// backupbuddy_live_troubleshooting::run();
// $results = backupbuddy_live_troubleshooting::get_raw_results();


class backupbuddy_live_troubleshooting {
	
	const WARN_IF_LAST_SNAPSHOT_AGO_EXCEEDS_SECONDS_PAST_INTERVAL = 604800; // Warns if time since last snapshot exceeds twice the expected interval or _7_ days past the expected interval.
	private static $_finished = false;
	
	private static $_settings = array(
		'max_notifications' => 10,								// Max number of most recent sync notifications to return.
		'status_log_recent_lines' => 75,						// Max number of most recent lines to get from status log.
		'extraneous_log_recent_lines' => 30,					// Max number of most recent lines to get from extraneous overall status log.
		'send_fail_status_log_recent_lines' => 20,				// Max number of most recent lines to get from a REMOTE TRANSFER status log for Stash Live sends that FAILED.
	);
	
	
	private static $_results = array(
		'bb_version' => '',
		'wp_version' => '',
		'site_url' => '',
		'home_url' => '',
		'start_troubleshooting' => 0,
		'finish_troubleshooting' => 0,
		'gmt_offset' => 0,
		'current_time' => 0,
		'localized_time' => 0,
		'current_timestamp' => 0,
		
		'memory' => array(
			'estimated_base_usage' => 0,
			'estimated_catalog_usage' => 0,							// Estimate of max free memory that will be needed. In MB.
			'estimated_total_needed' => 0,
			'memory_tested' => 0,
			'memory_tested_hours_ago' => -1,
			'memory_reported_local' => 0,
			'memory_reported_master' => 0,
			'memory_max_usage_logged' => 0,
			'memory_status' => 'OK',
		),
		'file_info' => array(),									// File sizes, mtime, etc of various logs.
		
		'alerts' => array(),
		'highlights' => array(),
		
		'recent_waiting_on_files' => array(),					// Recent list of files waiting on as per $files_pending_send_file file contents.
		'recent_waiting_on_files_time' => 0,					// File modified time.
		'recent_waiting_on_files_time_ago' => 0,				// File modified time.
		'recent_waiting_on_tables' => array(),					
		'recent_waiting_on_tables_time' => 0,					// File modified time.
		'recent_waiting_on_tables_time_ago' => 0,				// File modified time.
		'recent_live_send_fails' => array(),					// Remote destination send failures to Live.
		'recent_sync_notifications' => array(),					// Sync Notification errors (live_error).
		
		'php_notices' => array(),							// Any PHP errors, warnings, notices found in any of the log searched.
		'bb_notices' => array(),								// Any BackupBuddy errors or warnings logged.
		
		'live_status_log_tail' => '',							// Recent Stash Live Status Log.
		'live_stats' => array(),
		'server_stats' => array(),
		'crons' => array(),										// Listing of crons for troubleshooting.
		'extraneous_log_tail' => '',							// Tail end of extraneous log file.
	);
	
	
	
	public static function run() {
		self::$_results['start_troubleshooting'] = microtime( true );
		self::$_results['gmt_offset'] = get_option( 'gmt_offset' );
		self::$_results['current_time'] = pb_backupbuddy::$format->date( time() );
		self::$_results['localized_time'] = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( time() ) );
		self::$_results['current_timestamp'] = time();
		
		// Recent send fails (remote destination failures).
		// HIGHLIGHTS: PHP errors, send errors, NUMBER of sends failed in X hours
		
		// Populates tail of status log and looks for PHP and BB notices.
		self::_test_site_home_url();
		self::_test_versions();
		self::_test_file_info();
		self::_test_status_log();
		self::_test_recent_sync_notifications();
		self::_test_live_state();
		self::_test_server_stats();
		self::_test_memory();
		self::_test_cron_scheduled();
		self::_test_recent_live_send_fails();
		self::_test_extraneous_log();
		self::_recent_waiting_on_files_tables();
		self::_record_alerts();
		self::_test_php_runtime();
		self::_test_godaddy_managed_wp();
		self::_test_last_snapshot();
		
		self::$_results['finish_troubleshooting'] = microtime( true );
		self::$_finished = true;
		
	} // End run().
	
	
	
	public static function _test_site_home_url() {
		self::$_results['site_url'] = site_url();
		self::$_results['home_url'] = home_url();
	}
	
	
	public static function _test_last_snapshot() {
		// Get WP schedules.
		$schedule_intervals = wp_get_schedules();
		$destination = backupbuddy_live_periodic::get_destination_settings();
		if ( ! isset( $schedule_intervals[ $destination['remote_snapshot_period'] ] ) ) {
			$error = 'Error #38939833. Invalid snapshot period. Check snapshot interval settings and re-save for contact support.';
			self::$_results['highlights'][] = $error;
			self::$_results['alerts'][] = $error;
			return false;
		}
		$interval = $schedule_intervals[ $destination['remote_snapshot_period'] ]['interval'];
		$interval_display = $schedule_intervals[ $destination['remote_snapshot_period'] ]['display'];
		$time_passed = ( time() - self::$_results['live_stats']['last_remote_snapshot'] );
		
		if ( ( $time_passed > 2*$interval ) || ( $time_passed > $interval+self::WARN_IF_LAST_SNAPSHOT_AGO_EXCEEDS_SECONDS_PAST_INTERVAL ) ) { // If twice the interval is passed or 7 days past the expected interval.
			
			$last_id = self::$_results['live_stats']['last_remote_snapshot_id'];
			$additionalParams = array(
				'snapshot' => $last_id,
			);
			$response = pb_backupbuddy_destination_live::stashAPI( $destination, 'live-snapshot-status', $additionalParams, true, false, 5 ); // 5 sec timeout to not halt troubleshooting
			if ( ! is_array( $response ) ) {
				$response = 'Error #3489349844: Unable to get last snapshot details for ID `' . $last_id . '`.';
			} else {
				if ( isset( $response['snapshot'] ) ) {
					$response['snapshot'] = '**truncated due to size; if needed, see "Last Snapshot Details" button under Advanced Troubleshooting Options section**';
				}
				$response = print_r( $response, true );
			}
			if ( 0 == self::$_results['live_stats']['last_remote_snapshot'] ) {
				$error = 'It looks like your first Stash Live snapshot still has not completed. This is usually caused by a server problem resulting in a file failing to send (we won\'t count the snapshot as a success unless 100% of your data is backed up! We want your backup to be perfect!). Check your Stash Live page for details.';
			} else {
				$days_since = round( ( time() - self::$_results['live_stats']['last_remote_snapshot'] ) / 60 / 60 / 24, 2 );
				$error = 'It has been `' . $days_since . '` days since the last snapshot. Current interval name: `' . $interval_display . '`. Current interval value: `' . $interval . '`. Last snapshot details (just now checked): `' . $response . '`. Last snapshot response (from last snapshot requested): `' . print_r( self::$_results['live_stats']['last_remote_snapshot_response'], true ) . '`.';
			}
			self::$_results['highlights'][] = $error;
			self::$_results['alerts'][] = $error;
		}
	}
	
	
	public static function _test_versions() {
		global $wp_version;
		self::$_results['bb_version'] = pb_backupbuddy::settings( 'version' );
		self::$_results['wp_version'] = $wp_version;
	}
	
	
	
	public static function _test_php_runtime() {
		if ( backupbuddy_core::detectMaxExecutionTime() < 28 ) {
			$error = 'Detected maximum PHP execution time below 30 seconds. Contact host to increase to at least 30 seconds. Detected: `' . backupbuddy_core::detectMaxExecutionTime() . '` seconds.';
			self::$_results['highlights'][] = $error;
			self::$_results['alerts'][] = $error;
		}
	}
	
	
	public static function _test_godaddy_managed_wp() {
		if ( defined( 'GD_SYSTEM_PLUGIN_DIR' ) || class_exists( '\\WPaaS\\Plugin' ) ) {
			self::$_results['alerts'][] = 'GoDaddy Managed WordPress Hosting detected. This hosting has had known problems with the WordPress cron in the past. This may be resolved at this time.';
		}
	}
	
	
	
	// NOTE: run BEFORE _test_memory().
	public static function _test_file_info() {
		$catalog_file = backupbuddy_core::getLogDirectory() . 'live/catalog-' . pb_backupbuddy::$options['log_serial'] . '.txt';
		$state_file = backupbuddy_core::getLogDirectory() . 'live/state-' . pb_backupbuddy::$options['log_serial'] . '.txt';
		
		if ( false !== ( $catalog_size = @filesize( $catalog_file ) ) ) {
			self::$_results['memory']['estimated_catalog_usage'] = round( ( $catalog_size / 1024 / 1024 ) * 10, 2 );
		}
		
		$log_files = array(
			'files_pending_log' => backupbuddy_core::getLogDirectory() . 'live/files_pending_send-' . pb_backupbuddy::$options['log_serial'] . '.txt',
			'tables_pending_log' => backupbuddy_core::getLogDirectory() . 'live/tables_pending_send-' . pb_backupbuddy::$options['log_serial'] . '.txt',
			'extraneous_log' => backupbuddy_core::getLogDirectory() . 'log-' . pb_backupbuddy::$options['log_serial'] . '.txt',
			'live_log' => backupbuddy_core::getLogDirectory() . 'status-live_periodic_' . pb_backupbuddy::$options['log_serial'] . '.txt',
			'catalog_file' => $catalog_file,
			'state_file' => $state_file,
		);
		
		foreach( $log_files as $title => $log_file ) {
			if ( ! file_exists( $log_file ) ) {
				self::$_results['file_info'][ $title ] = '[does not exist]';
				continue;
			}
			
			$mtime = @filemtime( $log_file );
			
			self::$_results['file_info'][ $title ] = array(
				'size' => pb_backupbuddy::$format->file_size( @filesize( $log_file ) ),
				'modified' => $mtime,
				'modified_pretty' => pb_backupbuddy::$format->date( $mtime ),
				'modified_ago' => pb_backupbuddy::$format->time_ago( $mtime ),
			);
		}
	} // End _test_log_file_sizes().
	
	
	
	// NOTE: run AFTER _test_file_info().
	public static function _test_memory() {
		
		self::$_results['memory']['estimated_base_usage'] = round( memory_get_usage() / 1048576, 2 );
		if ( self::$_results['memory']['estimated_catalog_usage'] > 0 ) {
			self::$_results['memory']['estimated_total_needed'] = self::$_results['memory']['estimated_base_usage'] + self::$_results['memory']['estimated_catalog_usage'];
			self::$_results['memory']['estimated_total_needed'] += ( self::$_results['memory']['estimated_total_needed'] * .15 ); // 15% wiggle room.
			self::$_results['memory']['estimated_total_needed'] = round( self::$_results['memory']['estimated_total_needed'], 2 );
		}
		
		// These globals populated by _server_tests.php.
		global $bb_local_mem, $bb_master_mem, $bb_tested_mem, $bb_tested_mem_ago;
		self::$_results['memory']['memory_reported_local'] = $bb_local_mem;
		self::$_results['memory']['memory_reported_master'] = $bb_master_mem;
		self::$_results['memory']['memory_tested'] = $bb_tested_mem;
		self::$_results['memory']['memory_tested_hours_ago'] = round( $bb_tested_mem_ago / 60 / 60, 2 );
		
		// Use tested value above others.
		if ( self::$_results['memory']['memory_tested'] > 0 ) {
			if ( self::$_results['memory']['estimated_total_needed'] > self::$_results['memory']['memory_tested'] ) {
				self::$_results['memory']['memory_status'] = 'ERROR! Not enough memory. Increase PHP memory limits! `' . self::$_results['memory']['memory_tested'] . 'MB` tested available but `' . self::$_results['memory']['estimated_total_needed'] . 'MB` estimated required.';
				self::$_results['highlights'][] = self::$_results['memory']['memory_status'];
				self::$_results['alerts'][] = self::$_results['memory']['memory_status'] . ' Please contact your host to assist in correcting this issue.';
			} else { // Probably okay but just warn if reported values are below tested.
				
				// TODO: This may be over-sensitive and not needed. In the future possibly remove. Likely too many false hits/warns.
				if ( ( self::$_results['memory']['memory_reported_local'] < self::$_results['memory']['memory_tested'] ) || ( self::$_results['memory']['memory_reported_master'] < self::$_results['memory']['memory_tested'] ) ) {
					self::$_results['memory']['memory_status'] = 'OK';
					// Highlight only.
					self::$_results['highlights'][] = 'OK. Note: Reported PHP memory value(s) below tested value. Re-test PHP memory to verify it is up to date. If up to date then IGNORE this note. Tested: `' . self::$_results['memory']['memory_tested'] . 'MB`, Reported Local: `' . self::$_results['memory']['memory_reported_local'] . 'MB`, Reported Master: `' . self::$_results['memory']['memory_reported_master'] . 'MB`. Estimated needed: `' . self::$_results['memory']['estimated_total_needed'] . 'MB`. Memory tested: `' . pb_backupbuddy::$format->time_ago( time() + $bb_tested_mem_ago ) . '` ago.';
				}
			}
			
			$most_likely_memory = self::$_results['memory']['memory_tested'];
		} else {
			$lowest_memory = self::$_results['memory']['memory_reported_local'];
			$lowest = 'local';
			
			if ( self::$_results['memory']['memory_reported_master'] < self::$_results['memory']['memory_reported_local'] ) {
				$lowest_memory = self::$_results['memory']['memory_reported_master'];
				$lowest = 'master';
			}
			
			if ( self::$_results['memory']['memory_reported_master'] == self::$_results['memory']['memory_reported_local'] ) {
				$lowest = 'equal';
			}
			
			if ( $lowest_memory < self::$_results['memory']['estimated_total_needed'] ) {
				if ( 'equal' == $lowest ) { // Definitely not enough.
					self::$_results['memory']['memory_status'] = 'ERROR! Not enough memory. Increase PHP memory limits for local & master values! `' . $lowest_memory . 'MB` reported available but `' . self::$_results['memory']['estimated_total_needed'] . 'MB` estimated required.';
					self::$_results['highlights'][] = self::$_results['memory']['memory_status'];
					self::$_results['alerts'][] = self::$_results['memory']['memory_status'] . ' Please contact your host to assist in correcting this issue.';
				} else { // One or the other may be overriding. Warn.
					self::$_results['memory']['memory_status'] = 'WARNING! Possibly not enough memory. Increase PHP memory limits for `' . $lowest . '` value! Master or local values can override one another. `' . $lowest_memory . 'MB` reported available for `' . $lowest . '` value but `' . self::$_results['memory']['estimated_total_needed'] . 'MB` estimated required.';
					self::$_results['highlights'][] = self::$_results['memory']['memory_status'];
				}
			}
			
			$most_likely_memory = $lowest_memory;
		}
		
		if ( ( self::$_results['memory']['memory_max_usage_logged'] + ( self::$_results['memory']['memory_max_usage_logged'] * .20 ) ) > $most_likely_memory ) {
			self::$_results['highlights'][] = 'WARNING: Max memory usage detected in log file(s) is getting close to tested limit. Max seen: `' . self::$_results['memory']['memory_max_usage_logged'] . 'MB`, max tested available: `' . self::$_results['memory']['memory_tested'] . 'MB`.';
		}
		
	} // End _test_memory().
	
	
	
	public static function get_raw_results() {
		if ( false === self::$_finished ) {
			return false;
		}
		return self::$_results;
	}
	
	
	
	public static function get_html_results() {
		if ( false === self::$_finished ) {
			return false;
		}
		echo 'TODO #483948434';
	}
	
	
	
	private static function _recent_waiting_on_files_tables() {
		$files_pending_send_file = backupbuddy_core::getLogDirectory() . 'live/files_pending_send-' . pb_backupbuddy::$options['log_serial'] . '.txt';
		$tables_pending_send_file = backupbuddy_core::getLogDirectory() . 'live/tables_pending_send-' . pb_backupbuddy::$options['log_serial'] . '.txt';
		
		if ( file_exists( $files_pending_send_file ) ) {
			if ( false !== ( $files_pending_send = @file_get_contents( $files_pending_send_file ) ) ) {
				self::$_results['recent_waiting_on_files'] = explode( "\n", $files_pending_send );
				self::$_results['recent_waiting_on_files_time'] = @filemtime( $files_pending_send_file );
				self::$_results['recent_waiting_on_files_time_ago'] = pb_backupbuddy::$format->time_ago( self::$_results['recent_waiting_on_files_time'] ) . ' ' . __( 'ago', 'it-l10n-backupbuddy' );
				if ( count( self::$_results['recent_waiting_on_files'] ) > 0 ) {
					self::$_results['highlights'][] = '`' . count( self::$_results['recent_waiting_on_files'] ) . '` total files recently needed waiting on. See "recent_waiting_on_files" section for file listing.';
					self::$_results['alerts'][] = '`' . count( self::$_results['recent_waiting_on_files'] ) . '` total files recently needed waiting on. This could be due to these files being large, a temporary transfer error, or not enough site activity/visitors to trigger the WordPress schedule system (cron). If this lasts longer than 24 hours then there may be a problem preventing these file(s) from sending. Make sure there are no non-ascii characters in the filename. Check the log for the file transfer on the Remote Destinations page by clicking the \'View recently sent files\' button at the top then hovering the file and clicking \'View Log\' on the right. Ignore this warning if all files are at 100% as it is old and should go away soon.<br><br><b>Files possibly being waited on (' . count( self::$_results['recent_waiting_on_files'] ). '):</b><br><div style=\'height: 60px; width: 50%; white-space: nowrap; overflow: scroll; background: #EFEFEF; padding: 6px; border-radius: 5px;\'>' . implode( "<br>", self::$_results['recent_waiting_on_files'] ) . '</div>';
				}
			}
		}
		if ( file_exists( $tables_pending_send_file ) ) {
			if ( false !== ( $tables_pending_send = @file_get_contents( $tables_pending_send_file ) ) ) {
				self::$_results['recent_waiting_on_tables'] = explode( "\n", $tables_pending_send );
				self::$_results['recent_waiting_on_tables_time'] = @filemtime( $tables_pending_send_file );
				self::$_results['recent_waiting_on_tables_time_ago'] = pb_backupbuddy::$format->time_ago( self::$_results['recent_waiting_on_tables_time'] ) . ' ' . __( 'ago', 'it-l10n-backupbuddy' );
				if ( count( self::$_results['recent_waiting_on_tables'] ) > 0 ) {
					self::$_results['highlights'][] = '`' . count( self::$_results['recent_waiting_on_tables'] ) . '` total tables recently needed waiting on.';
					self::$_results['alerts'][] = '`' . count( self::$_results['recent_waiting_on_tables'] ) . '` total database tables recently needed waiting on. This could be due to these table files being large or a temporary transfer error. If this lasts longer than 24 hours then there may be a problem preventing these table(s) from sending. Check the log for the file transfer on the Remote Destinations page by clicking the \'View recently sent files\' button at the top then hovering the database table file and clicking \'View Log\' on the right.  Ignore this warning if all database tables are at 100% as it is old and should go away soon.<br><br><b>Table files possibly being waited on (' . count( self::$_results['recent_waiting_on_tables'] ) . '):</b><br><div style=\'height: 60px; width: 50%; white-space: nowrap; overflow: scroll; background: #EFEFEF; padding: 6px; border-radius: 5px;\'>' . implode( "<br>", self::$_results['recent_waiting_on_tables'] ) . '</div>';
				}
			}
		}
	}
	
	private static function _test_extraneous_log() {
		$status_log_file = backupbuddy_core::getLogDirectory() . 'log-' . pb_backupbuddy::$options['log_serial'] . '.txt';
		$status_log_file_contents = file_get_contents( $status_log_file );
		self::_find_notices( $status_log_file_contents, $status_log_file );
		
		// Get tail of status log.
		if ( file_exists( $status_log_file ) ) {
			self::$_results['extraneous_log_tail'] = backupbuddy_core::read_backward_line( $status_log_file, self::$_settings['extraneous_log_recent_lines'] );
		} else {
			self::$_results['extraneous_log_tail'] = '**Log file `' . $status_log_file . '` not found.**';
		}
	}
	
	private static function _test_status_log() {
		$status_log_file = backupbuddy_core::getLogDirectory() . 'status-live_periodic_' . pb_backupbuddy::$options['log_serial'] . '.txt';
		
		// Get tail of status log.
		if ( file_exists( $status_log_file ) ) {
			$status_log_file_contents = @file_get_contents( $status_log_file );
			self::_find_notices( $status_log_file_contents, $status_log_file );
			self::$_results['live_status_log_tail'] = backupbuddy_core::read_backward_line( $status_log_file, self::$_settings['status_log_recent_lines'] );
		} else {
			self::$_results['extraneous_log_tail'] = '**Log file `' . $status_log_file . '` not found.**';
		}
	}
	
	
	
	private static function _test_recent_sync_notifications() {
		$notifications = backupbuddy_core::getNotifications();
		
		// Remove non-Stash Live notifications.
		foreach( $notifications as $key => $notification ) {
			if ( 'live_error' != $notification['slug'] ) {
				unset( $notifications[ $key ] );
				continue;
			}
		}
		
		if ( count( $notifications ) > 0 ) {
			self::$_results['highlights'][] = '`' . count( $notifications ) . '` recent Stash Live Sync error notifications.';
		}
		
		// Limit to X number of most recent notifications.
		self::$_results['recent_sync_notifications'] = array_slice( $notifications, -1 * ( self::$_settings['max_notifications'] ), self::$_settings['max_notifications'], $preserve_key = false );
		
	}
	
	
	
	private static function _test_live_state() {
		self::$_results['live_stats'] = backupbuddy_api::getLiveStats();
	}
	
	
	
	private static function _test_server_stats() {
		require( pb_backupbuddy::plugin_path() . '/controllers/pages/server_info/_server_tests.php' ); // Populates $tests.
		self::$_results['server_stats'] = $tests;
		foreach( self::$_results['server_stats'] as &$stat ) {
			$stat = self::_strip_tags_content( $stat );
			if ( ( 'FAIL' == $stat['status'] ) || ( 'WARNING' == $stat['status'] ) ) {
				self::$_results['highlights'][] = $stat;
			}
		}
	}
	
	
	
	private static function _test_cron_scheduled() {
		$cron_warnings = array();
		require( pb_backupbuddy::plugin_path() . '/controllers/pages/server_info/_cron.php' );
		self::$_results['crons'] = self::_strip_tags_content( $crons );
		if ( count( $cron_warnings ) > 0 ) {
			self::$_results['highlights'][] = count( $cron_warnings ) . ' cron(s) warnings were found (such as past due; see cron section for details): ' . implode( '; ', array_unique( $cron_warnings ) );
		}
	}
	
	
	
	private static function _test_recent_live_send_fails() {
		$troubleshooting = true; // Tell _remote_sends.php to run in troubleshooting/text mode.
		require( pb_backupbuddy::plugin_path() . '/controllers/pages/server_info/_remote_sends.php' ); // Populates $sends.
		self::$_results['recent_live_send_fails'] = $sends;
		foreach( self::$_results['recent_live_send_fails'] as $key => &$send ) {
			
			// Only include Live sends.
			if ( 'live' != $send['type'] ) {
				unset( self::$_results['recent_live_send_fails'][ $key ] );
				continue;
			}
			
			// Only include FAILED sends.
			if ( true !== $send['failed'] ) {
				unset( self::$_results['recent_live_send_fails'][ $key ] );
				continue;
			}
			
			// If error message, set as highlight.
			if ( '' != $send['error'] ) {
				self::$_results['highlights'][] = array(
					'error' => 'File send Error #4389844959:' . $send['error'],
					'send_details' => $send
				);
			}
			
			if ( file_exists( $send['log_file'] ) ) {
				$send['log_tail'] = backupbuddy_core::read_backward_line( $send['log_file'], self::$_settings['send_fail_status_log_recent_lines'] );
			} else {
				self::$_results['extraneous_log_tail'] = '**Log file `' . $send['log_file'] . '` not found.**';
			}
		}
		
		if ( count( self::$_results['recent_live_send_fails'] ) > 0 ) {
			self::$_results['highlights'][] = count( self::$_results['recent_live_send_fails'] ) . ' total files pending send before Snapshot can be made.';
		}
	}
	
	
	
	/* _find_notices()
	 *
	 * Finds any PHP errors, warnings, notices + BackupBuddy errors and warnings in a log file.
	 *
	 * @param	string	$log	Newline-deliminated log file.
	 *
	 */
	private static function _find_notices( $log, $log_file ) {
		$php_notices = array();
		$bb_notices = array();
		
		$newProcessStarting = false;
		$prevLine = '';
		$lastMem = 0;
		$recordNextLines = 0;
		$maxMem = 0;
		
		$separator = "\r\n";
		$line = strtok( $log, $separator ); // Attribution: http://stackoverflow.com/questions/1462720/iterate-over-each-line-in-a-string-in-php
		while ($line !== false) {
			# do something with $line
			$line = strtok( $separator );
			
			if ( $recordNextLines > 0 ) {
				$php_notices[] = $line;
				$recordNextLines--;
			}
			
			if ( true === $newProcessStarting ) {
				if ( false !== stripos( $line, 'possible_timeout' ) ) {
					self::$_results['highlights'][] = 'Possible timeout or memory ceiling (peak before new process: `' . $lastMem . '`) detected in `' . $log_file . '`. Pre-timeout line: `' . $preTimeoutLine . '`. Post-timeout line: `' . $prevLine . '`.';
					$newProcessStarting = false;
				}
			}
			
			if ( false !== strpos( $line, '"-----"' ) ) {
				$newProcessStarting = true;
				// Get memory value from previous line.
				if ( null != ( $line_array = json_decode( trim( $prevLine ), $assoc = true ) ) ) {
					if ( isset( $line_array[ 'mem' ] ) ) {
						$lastMem = $line_array[ 'mem' ];
						if ( $lastMem > $maxMem ) {
							$maxMem = $lastMem;
						}
						$preTimeoutLine = $prevLine;
					}
				}
			
			// BackupBuddy Error #
			} elseif ( false !== stripos( $line, 'Error #' ) ) {
				$bb_notices[] = $line;
			
			// BackupBuddy Warning #
			} elseif ( false !== stripos( $line, 'Warning #' ) ) {
				$bb_notices[] = $line;
			
			} elseif ( false !== stripos( $line, 'Fatal PHP error encountered:' ) ) { // BB-prefix.
				$php_notices[] = $line;
				$recordNextLines = 5; // Record the next 5 lines after this so we get the actual error.
			
			// fatal PHP error
			} elseif ( false !== stripos( $line, 'Fatal error:' ) ) {
				$php_notices[] = $line;
			
			// PHP parse error
			} elseif ( false !== stripos( $line, 'Parse error:' ) ) {
				$php_notices[] = $line;
			
			// out of memory error
			} elseif ( false !== stripos( $line, 'allowed memory size of' ) ) {
				$php_notices[] = $line;
			}
			
			
			$prevLine = $line;
		}
		
		
		if ( ( count( $php_notices ) > 0 ) || ( count( $bb_notices ) > 0 ) ) {
			self::$_results['highlights'][] = 'Detected `' . count( $php_notices ) . '` possible PHP notices and `' . count( $bb_notices ) . '` possible BackupBuddy notices in log `' . $log_file . '`. See php_notices or bb_notices section for details.';
		}
		
		self::$_results['php_notices'] = array_merge( self::$_results['php_notices'], $php_notices );
		self::$_results['bb_notices'] = array_merge( self::$_results['bb_notices'], $bb_notices );
		
		// Log max memory usage seen in this log.
		if ( $maxMem > self::$_results['memory']['memory_max_usage_logged'] ) {
			self::$_results['memory']['memory_max_usage_logged'] = $maxMem;
		}
		
		return array( $php_notices, $bb_notices );
	}
	
	
	
	// Save alerts into file for displaying on Stash Live page.
	public static function _record_alerts() {
		$troubleshooting_alerts_file = backupbuddy_core::getLogDirectory() . 'live/troubleshooting_alerts-' . pb_backupbuddy::$options['log_serial'] . '.txt';
		
		if ( count( self::$_results['alerts'] ) > 0 ) {
			if ( false === @file_put_contents( $troubleshooting_alerts_file, implode( "<br>", self::$_results['alerts'] ) ) ) {
				// Unable to write.
			}
		} else { // Clear away file.
			if ( @file_exists( $troubleshooting_alerts_file ) ) {
				@unlink( $troubleshooting_alerts_file );
			}
		}
		
	} // End _record_alerts().
	
	
	
	public static function _strip_tags_content( $array ) {
		foreach( $array as &$array_contents ) {
			if ( is_array( $array_contents ) ) {
				$array_contents = self::_strip_tags_content( $array_contents );
			} else {
				$array_contents = strip_tags( $array_contents );
			}
		}
		return $array;
	} 
	
	
} // End class.

