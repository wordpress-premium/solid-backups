<?php
/**
 * OUTPUT: populates $tests.
 *
 * IMPORTANT NOTE:
 *
 * This file is shared between multiple projects / purposes:
 *   + BackupBuddy (this plugin) Server Info page.
 *   + ImportBuddy.php (BackupBuddy importer) Server Information button dropdown display.
 *   + ServerBuddy (plugin)
 *
 * Use caution when updated to prevent breaking other projects.
 *
 * @package  BackupBuddy
 */


/**
 * Gets PHP Info as an Array
 *
 * @param int $mode  Mode, passed into phpinfo().
 *
 * @return array  PHP Info contents, array( local, master ).
 */
function phpinfo_array( $mode = -1 ) {
	ob_start();
	phpinfo( $mode );
	$s = ob_get_contents();
	ob_end_clean();
	$a = $mtc = array();
	if ( preg_match_all( '/<tr><td class="e">(.*?)<\/td><td class="v">(.*?)<\/td>(:?<td class="v">(.*?)<\/td>)?<\/tr>/', $s, $mtc, PREG_SET_ORDER ) ) {
		foreach ( $mtc as $v ) {
			if ( '<i>no value</i>' == $v[2] ) {
				continue;
			}
			$master = '';
			if ( isset( $v[3] ) ) {
				$master = strip_tags( $v[3] );
			}
			$a[ $v[1] ] = array( $v[2], $master );
		}
	}
	return $a;
}


/**
 * Get normalized boolean value from ini_get()
 *
 * @author nicolas dot grekas+php at gmail dot com
 *
 * @param string $a  ini_get Parameter.
 *
 * @return bool  Boolean value.
 */
function ini_get_bool( $a ) {
	$b = ini_get( $a );
	switch ( strtolower( $b ) ) {
		case 'on':
		case 'yes':
		case 'true':
			return 'assert.active' !== $a;

		case 'stdout':
		case 'stderr':
			return 'display_errors' === $a;

		default:
			return (bool) (int) $b;
	}
}

/**
 * Gets Load average
 *
 * @return array  Load Average array.
 */
function pb_backupbuddy_get_loadavg() {
	$result = array_fill( 0, 3, 'n/a' );
	if ( function_exists( 'sys_getloadavg' ) ) {
		$load = @sys_getloadavg();
		if ( is_array( $load ) ) {
			$count = count( $load );
			if ( 3 === $count ) {
				return $load;
			} else {
				for ( $i = 0; $i < $count; $i++ ) {
					$result[ $i ] = $load[ $i ];
				}
			}
		}
	}
	if ( substr( PHP_OS, 0, 3 ) == 'WIN' ) { // WINDOWS.
		ob_start();
		$status = null;
		@passthru( 'typeperf -sc 1 "\processor(_total)\% processor time"', $status );
		$content = ob_get_contents();
		ob_end_clean();
		if ( 0 === $status ) {
			if ( preg_match( '/\,"([0-9]+\.[0-9]+)"/', $content, $load ) ) {
				$result[0] = number_format_i18n( $load[1], 2 ) . ' %';
				$result[1] = 'n/a';
				$result[2] = 'n/a';
				return $result;
			}
		}
	} else {
		if ( function_exists( 'file_get_contents' ) && @file_exists( '/proc/loadavg' ) ) {
			$load = explode( chr( 32 ), @file_get_contents( '/proc/loadavg' ) );
			if ( is_array( $load ) && ( count( $load ) >= 3 ) ) {
				$result = array_slice( $load, 0, 3 );
				return $result;
			}
		}
		if ( function_exists( 'shell_exec' ) ) {
			$str = substr( strrchr( @shell_exec( 'uptime' ), ':' ), 1 );
			return array_map( 'trim', explode( ',', $str ) );
		}
	}
	return $result;
}


$tests = array();


// Skip these tests in importbuddy.
if ( ! defined( 'PB_IMPORTBUDDY' ) ) {

	// BACKUPBUDDY VERSION.
	$latest_version = backupbuddy_core::determineLatestVersion( true );
	if ( false === $latest_version ) {
		$suggestion_text                     = '[information unavailable]';
		$latest_backupbuddy_nonminor_version = 0;
	} else {
		$latest_backupbuddy_version          = $latest_version[0];
		$latest_backupbuddy_nonminor_version = $latest_version[1];

		$suggestion_text = $latest_backupbuddy_nonminor_version;
		if ( pb_backupbuddy::settings( 'version' ) == $latest_backupbuddy_version ) { // At absolute latest including minor.
			$suggestion_text .= ' (major version) or ' . $latest_backupbuddy_version . ' (<a href="options-general.php?page=ithemes-licensing" title="You may enable upgrading to the quick release version on the iThemes Licensing page.">quick release</a>)';
		} elseif ( $latest_backupbuddy_nonminor_version != $latest_backupbuddy_version ) { // Minor version available that is newer than latest major.
			$suggestion_text .= ' (major version) or ' . $latest_backupbuddy_version . ' (<a href="plugins.php?ithemes-updater-force-minor-update=1" title="You may enable upgrading to the quick release version on the iThemes Licensing page.">quick release version</a>; <a href="options-general.php?page=ithemes-licensing" title="Once you have licensed BackupBuddy you may select this to go to the Plugins page to upgrade to the latest quick release version. Typically only the main major versions are available for automatic updates but this option instructs the updater to display minor version updates for approximately one hour. If it does not immediately become available on the Plugins page, try refreshing a couple of times.">quick release settings</a>)';
		} else {
			$suggestion_text .= ' (latest)';
		}
	}

	$version_string = pb_backupbuddy::settings( 'version' );
	// If on DEV system (.git dir exists) then append some details on current.
	if ( @file_exists( pb_backupbuddy::plugin_path() . '/.git/logs/HEAD' ) ) {
		$commit_log      = escapeshellarg( pb_backupbuddy::plugin_path() . '/.git/logs/HEAD' );
		$commit_line     = str_replace( '\'', '`', exec( "tail -n 1 {$commit_log}" ) );
		$version_string .= ' <span style="display: block; max-width: 250px; font-size: 8px; line-height:1.25;">[DEV: ' . $commit_line . ']</span>';
	}
	$parent_class_test = array(
		'title'      => 'BackupBuddy Version',
		'suggestion' => $suggestion_text,
		'value'      => $version_string,
		'tip'        => __( 'Version of BackupBuddy currently running on this site.', 'it-l10n-backupbuddy' ),
	);
	if ( version_compare( pb_backupbuddy::settings( 'version' ), $latest_backupbuddy_nonminor_version, '<' ) ) {
		$parent_class_test['status'] = 'WARNING';
	} else {
		$parent_class_test['status'] = 'OK';
	}
	array_push( $tests, $parent_class_test );

	// WordPress VERSION.
	global $wp_version;
	$parent_class_test = array(
		'title'      => 'WordPress Version',
		'suggestion' => '>= ' . pb_backupbuddy::settings( 'wp_minimum' ) . ' (latest best)',
		'value'      => $wp_version,
		'tip'        => __( 'Version of WordPress currently running. It is important to keep your WordPress up to date for security & features.', 'it-l10n-backupbuddy' ),
	);
	if ( version_compare( $wp_version, pb_backupbuddy::settings( 'wp_minimum' ), '<=' ) ) {
		$parent_class_test['status'] = 'FAIL';
	} else {
		$parent_class_test['status'] = 'OK';
	}
	array_push( $tests, $parent_class_test );

	// MySQL VERSION.
	global $wpdb;

	$mysql_version     = $wpdb->db_version();
	$parent_class_test = array(
		'title'      => __( 'MySQL Version', 'it-l10n-backupbuddy' ),
		'suggestion' => __( '>= 5.6.0 (WordPress recommends 5.6+)', 'it-l10n-backupbuddy' ),
		'value'      => PB_Backupbuddy_DB_Helpers::is_maria_db() ? 'n/a' : $mysql_version,
		'tip'        => __( 'Version of your database server (mysql) as reported to this script by WordPress.', 'it-l10n-backupbuddy' ),
	);

	$parent_class_test['status'] = 'OK';

	if ( false === PB_Backupbuddy_DB_Helpers::is_maria_db() ) {
		if ( version_compare( $mysql_version, '5.0.15', '<=' ) ) {
			$parent_class_test['status'] = 'FAIL';
		} elseif ( version_compare( $mysql_version, '5.6.0', '<=' ) ) {
			$parent_class_test['status'] = 'WARNING';
		}
	}

	array_push( $tests, $parent_class_test );

	// MariaDB Version.
	$maria_db_version  = PB_Backupbuddy_DB_Helpers::get_db_version();
	$parent_class_test = array(
		'title'      => __( 'MariaDB Version', 'it-l10n-backupbuddy' ),
		'suggestion' => __( '>= 10.0.10 (WordPress recommends 10.0+)', 'it-l10n-backupbuddy' ),
		'value'      => PB_Backupbuddy_DB_Helpers::is_maria_db() ? $maria_db_version : 'n/a',
		'tip'        => __( 'Version of your database server (mariadb) as reported to this script by WordPress.', 'it-l10n-backupbuddy' ),
	);

	$parent_class_test['status'] = 'OK';

	if ( true === PB_Backupbuddy_DB_Helpers::is_maria_db() ) {
		if ( version_compare( $maria_db_version, '10.0.0', '<' ) ) {
			$parent_class_test['status'] = 'FAIL';
		} elseif ( version_compare( $maria_db_version, '10.0.10', '<' ) ) {
			$parent_class_test['status'] = 'WARNING';
		}
	}

	array_push( $tests, $parent_class_test );

	// ADDHANDLER HTACCESS CHECK.
	$parent_class_test = array(
		'title'      => 'AddHandler in .htaccess',
		'suggestion' => 'host dependant (none best unless required)',
		'tip'        => __( 'If detected then you may have difficulty migrating your site to some hosts without first removing the AddHandler line. Some hosts will malfunction with this line in the .htaccess file.', 'it-l10n-backupbuddy' ),
	);
	if ( file_exists( ABSPATH . '.htaccess' ) ) {
		$addhandler_note = '';
		$htaccess_lines  = file( ABSPATH . '.htaccess' );
		foreach ( $htaccess_lines as $htaccess_line ) {
			if ( preg_match( '/^(\s*)AddHandler(.*)/i', $htaccess_line, $matches ) > 0 ) {
				$addhandler_note = pb_backupbuddy::tip( htmlentities( $matches[0] ), __( 'AddHandler Value', 'it-l10n-backupbuddy' ), false );
			}
		}
		unset( $htaccess_lines );

		if ( '' == $addhandler_note ) {
			$parent_class_test['status'] = 'OK';
			$parent_class_test['value']  = __( 'none, n/a', 'it-l10n-backupbuddy' );
		} else {
			$parent_class_test['status'] = 'WARNING';
			$parent_class_test['value']  = __( 'exists', 'it-l10n-backupbuddy' ) . $addhandler_note;
		}
		unset( $htaccess_contents );
	} else {
		$parent_class_test['status'] = 'OK';
		$parent_class_test['value']  = __( 'n/a', 'it-l10n-backupbuddy' );
	}
	array_push( $tests, $parent_class_test );


	// Set up ZipBuddy when within BackupBuddy.
	require_once pb_backupbuddy::plugin_path() . '/lib/zipbuddy/zipbuddy.php';
	pb_backupbuddy::$classes['zipbuddy'] = new pluginbuddy_zipbuddy( backupbuddy_core::getBackupDirectory() );

	require_once pb_backupbuddy::plugin_path() . '/lib/mysqlbuddy/mysqlbuddy.php';
	global $wpdb;
	pb_backupbuddy::$classes['mysqlbuddy'] = new pb_backupbuddy_mysqlbuddy( DB_HOST, DB_NAME, DB_USER, DB_PASSWORD, $wpdb->prefix );
}


// PHP VERSION.
if ( ! defined( 'pluginbuddy_importbuddy' ) ) {
	$php_minimum = '5.3';
} else { // importbuddy value.
	$php_minimum = pb_backupbuddy::settings( 'php_minimum' );
}
$parent_class_test = array(
	'title'      => 'PHP Version',
	'suggestion' => '>= ' . $php_minimum . ' (WordPress recommends 7.0+)',
	'value'      => phpversion(),
	'tip'        => __( 'Version of PHP currently running on this site.', 'it-l10n-backupbuddy' ),
);
if ( version_compare( PHP_VERSION, $php_minimum, '<=' ) ) {
	$parent_class_test['status'] = 'FAIL';
} elseif ( version_compare( PHP_VERSION, '5.6', '<=' ) ) {
	$parent_class_test['status'] = 'WARNING';
} else {
	$parent_class_test['status'] = 'OK';
}
array_push( $tests, $parent_class_test );


// PHP max_execution_time.
$parent_class_test = array(
	'title'      => 'PHP max_execution_time (server-reported)',
	'suggestion' => '>= 30 seconds (30+ best)',
	'value'      => ini_get( 'max_execution_time' ),
	'tip'        => __( 'Maximum amount of time that PHP allows scripts to run. After this limit is reached the script is killed. The more time available the better. 30 seconds is most common though 60 seconds is ideal.', 'it-l10n-backupbuddy' ),
);
if ( str_ireplace( 's', '', ini_get( 'max_execution_time' ) ) < 30 ) {
	$parent_class_test['status'] = 'WARNING';
} else {
	$parent_class_test['status'] = 'OK';
}
array_push( $tests, $parent_class_test );



// Maximum PHP Runtime (ACTUAL TESTED!).
if ( ! defined( 'PB_IMPORTBUDDY' ) ) {
	if ( pb_backupbuddy::$options['tested_php_runtime'] > 0 ) {
		$last_tested          = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( pb_backupbuddy::$options['last_tested_php_runtime'] ) ) . ' (' . pb_backupbuddy::$format->time_ago( pb_backupbuddy::$options['last_tested_php_runtime'] ) . ' ' . __( 'ago', 'it-l10n-backupbuddy' ) . ')';
		$tested_runtime_value = '<span id="pb_stats_run_php_runtime_test" title="Last tested: `' . $last_tested . '`">' . pb_backupbuddy::$options['tested_php_runtime'] . ' ' . __( 'secs', 'it-l10n-backupbuddy' ) . '</span>';
	} else {
		$tested_runtime_value = '<span class="description" id="pb_stats_run_php_runtime_test">' . __( 'Pending...', 'it-l10n-backupbuddy' ) . '</span>';
	}
	$disabled = '';
	if ( 0 == pb_backupbuddy::$options['php_runtime_test_minimum_interval'] ) {
		$disabled = '<span title="' . __( 'Disabled based on Advanced Settings.', 'it-l10n-backupbuddy' ) . '">' . __( 'Disabled', 'it-l10n-backupbuddy' ) . '</span>';
	}
	$parent_class_test = array(
		'title'      => 'Tested PHP Max Execution Time',
		'suggestion' => '>= 30 seconds (30+ best)',
		'value'      => $tested_runtime_value . $disabled . ' <a class="pb_backupbuddy_refresh_stats pb_backupbuddy_testPHPRuntime" rel="run_php_runtime_test" alt="' . pb_backupbuddy::ajax_url( 'run_php_runtime_test' ) . '" title="' . __( 'Run Test (may take several minutes)', 'it-l10n-backupbuddy' ) . '"><img src="' . pb_backupbuddy::plugin_url() . '/images/refresh_gray.gif" style="vertical-align: -1px;"> <span class="pb_backupbuddy_loading" style="display: none; margin-left: 10px;"><img src="' . pb_backupbuddy::plugin_url() . '/images/loading.gif" alt="' . __( 'Loading...', 'it-l10n-backupbuddy' ) . '" title="' . __( 'Loading...', 'it-l10n-backupbuddy' ) . '" width="16" height="16" style="vertical-align: -3px;" /></span></a>',
		'tip'        => __( 'This is the TESTED amount of time that PHP allows scripts to run. The test was performed by outputting / logging the script time elapsed once per second until PHP timed out and thus the time reported stopped. This gives a fairly accurate number compared to the reported number which is most often overriden at the server with a limit.', 'it-l10n-backupbuddy' ) . ' ' . 'This test is limited to `' . pb_backupbuddy::$options['php_runtime_test_minimum_interval'] . '` seconds based on your advanced settings (0 = disabled). Automatically rescans during housekeeping after `' . pb_backupbuddy::$options['php_runtime_test_minimum_interval'] . '` seconds elapse between tests as well always on plugin activation.',
	);
	if ( is_numeric( pb_backupbuddy::$options['tested_php_runtime'] ) && ( pb_backupbuddy::$options['tested_php_runtime'] < 29 ) ) {
		$parent_class_test['status'] = 'FAIL';
	} else {
		$parent_class_test['status'] = 'OK';
	}
	array_push( $tests, $parent_class_test );
}

// Maximum PHP Runtime (ACTUAL TESTED!).
$bb_php_max_execution        = backupbuddy_core::detectMaxExecutionTime(); // Lesser of PHP reported and tested. Float.
$bb_php_max_execution_string = $bb_php_max_execution . ' ' . __( 'secs', 'it-l10n-backupbuddy' ); // String.

if ( backupbuddy_core::adjustedMaxExecutionTime() != $bb_php_max_execution ) { // Takes into account user override.
	$bb_php_max_execution_string = '<strike>' . $bb_php_max_execution_string . '</strike> ' . __( 'Overridden in settings to:', 'it-l10n-backupbuddy' ) . ' ' . backupbuddy_core::adjustedMaxExecutionTime() . ' ' . __( 'secs', 'it-l10n-backupbuddy' );
}
if ( ! defined( 'PB_IMPORTBUDDY' ) ) {
	$parent_class_test = array(
		'title'      => 'BackupBuddy PHP Max Execution Time',
		'suggestion' => '>= 30 seconds (30+ best)',
		'value'      => $bb_php_max_execution_string,
		'tip'        => esc_attr__( 'This is the max execution time BackupBuddy is using for chunking. It is the lesser of the values of the reported PHP execution time and actual tested execution time. If the BackupBuddy "Max time per chunk" Advanced Setting is set then that value is used instead.', 'it-l10n-backupbuddy' ),
	);
	if ( $bb_php_max_execution < 28 ) { // Has a little wiggle room.
		$parent_class_test['status'] = 'FAIL';
	} else {
		$parent_class_test['status'] = 'OK';
	}
	array_push( $tests, $parent_class_test );
}

$phpinfo_array = phpinfo_array( 4 );

// MEMORY LIMIT.
/**
 * Convert string size to bytes.
 *
 * @param string $val  Byte string.
 *
 * @return int  Value in bytes.
 */
function bb_return_bytes( $val ) {
	$val  = trim( $val );
	$last = strtolower( $val[ strlen( $val ) - 1 ] );
	$val  = (int) $val; // This dumps any trailing modifier - avoids PHP Notice.
	switch ( $last ) {
		// The 'G' modifier is available since PHP 5.1.0.
		case 'g':
			$val *= 1024;
			// no break.
		case 'm':
			$val *= 1024;
			// no break.
		case 'k':
			$val *= 1024;
			// no break.
	}

	return $val;
}

$mem_limits = array();
if ( ! isset( $phpinfo_array['memory_limit'] ) ) {
	$parent_class_val = 'unknown';
} else {
	global $bb_local_mem, $bb_master_mem;
	$bb_local_mem       = bb_return_bytes( $phpinfo_array['memory_limit'][0] ) / 1024 / 1024;
	$local_mem_as_bytes = bb_return_bytes( $phpinfo_array['memory_limit'][0] );
	if ( $local_mem_as_bytes == (int) $phpinfo_array['memory_limit'][0] ) {
		// Local value is defined in bytes so we need to "convert" to M as our default unit.
		$mem_limits[] = ( $local_mem_as_bytes / 1024 / 1024 ) . 'M';
	} else {
		$mem_limits[] = $phpinfo_array['memory_limit'][0];
	}
	$parent_class_val = $phpinfo_array['memory_limit'][0];
	if ( isset( $phpinfo_array['memory_limit'][1] ) ) {
		$bb_master_mem       = bb_return_bytes( $phpinfo_array['memory_limit'][1] ) / 1024 / 1024;
		$master_mem_as_bytes = bb_return_bytes( $phpinfo_array['memory_limit'][1] );
		if ( $master_mem_as_bytes == (int) $phpinfo_array['memory_limit'][1] ) {
			// Master value is defined in bytes so we need to "convert" to M as our default unit.
			$mem_limits[] = ( $master_mem_as_bytes / 1024 / 1024 ) . 'M';
		} else {
			$mem_limits[] = $phpinfo_array['memory_limit'][1];
		}
		$parent_class_val .= ' (local) / ' . $phpinfo_array['memory_limit'][1] . ' (master)';
	}
}

$parent_class_test = array(
	'title'      => 'Reported PHP Memory Limit',
	'suggestion' => '>= 256 MB',
	'value'      => $parent_class_val,
	'tip'        => __( 'The amount of memory this site is allowed to consume. Note that some host\'s master value may override the local setting, capping it at a lower value.', 'it-l10n-backupbuddy' ),
);
foreach ( $mem_limits as $mem_limit ) {
	if ( preg_match( '/(\d+)(\w*)/', $mem_limit, $matches ) ) {
		$parent_class_val = $matches[1];
		$unit             = $matches[2];
		// Up memory limit if currently lower than 256M.
		if ( 'g' !== strtolower( $unit ) ) {
			if ( 'm' !== strtolower( $unit ) ) {
				$parent_class_test['status'] = 'WARNING';
			} elseif ( $parent_class_val < 125 ) {
				$parent_class_test['status'] = 'FAIL';
			} elseif ( $parent_class_val < 250 ) {
				$parent_class_test['status'] = 'WARNING';
			} else {
				$parent_class_test['status'] = 'OK';
			}
		}
	} else {
		$parent_class_test['status'] = 'WARNING';
	}

	// Once set to warning, don't process any more.
	if ( 'WARNING' == $parent_class_test['status'] ) {
		break;
	}
}
array_push( $tests, $parent_class_test );


// Tested PHP Memory Limit (ACTUAL TESTED!).
if ( ! defined( 'PB_IMPORTBUDDY' ) ) {
	if ( pb_backupbuddy::$options['tested_php_memory'] > 0 ) {
		$last_tested         = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( pb_backupbuddy::$options['last_tested_php_memory'] ) ) . ' (' . pb_backupbuddy::$format->time_ago( pb_backupbuddy::$options['last_tested_php_memory'] ) . ' ' . __( 'ago', 'it-l10n-backupbuddy' ) . ')';
		$tested_memory_value = '<span id="pb_stats_run_php_memory_test" title="Last tested: `' . $last_tested . '`">' . pb_backupbuddy::$options['tested_php_memory'] . ' ' . __( 'MB', 'it-l10n-backupbuddy' ) . '</span>';
	} else {
		$tested_memory_value = '<span class="description" id="pb_stats_run_php_memory_test">' . __( 'Pending...', 'it-l10n-backupbuddy' ) . '</span>';
	}
	global $bb_tested_mem, $bb_tested_mem_ago;

	$disabled = '';
	if ( 0 == pb_backupbuddy::$options['php_memory_test_minimum_interval'] ) {
		$disabled          = '<span title="' . __( 'Disabled based on Advanced Settings.', 'it-l10n-backupbuddy' ) . '">' . __( 'Disabled', 'it-l10n-backupbuddy' ) . '</span>';
		$bb_tested_mem     = 0;
		$bb_tested_mem_ago = -1;
	} else {
		$bb_tested_mem     = pb_backupbuddy::$options['tested_php_memory'];
		$bb_tested_mem_ago = time() - pb_backupbuddy::$options['last_tested_php_memory'];
	}

	$parent_class_test = array(
		'title'      => 'Tested PHP Memory Limit',
		'suggestion' => '>= 256 MB',
		'value'      => $tested_memory_value . $disabled . ' <a class="pb_backupbuddy_refresh_stats pb_backupbuddy_testPHPMemory" rel="run_php_memory_test" alt="' . pb_backupbuddy::ajax_url( 'run_php_memory_test' ) . '" title="' . __( 'Run Test (may take several minutes)', 'it-l10n-backupbuddy' ) . '"><img src="' . pb_backupbuddy::plugin_url() . '/images/refresh_gray.gif" style="vertical-align: -1px;"> <span class="pb_backupbuddy_loading" style="display: none; margin-left: 10px;"><img src="' . pb_backupbuddy::plugin_url() . '/images/loading.gif" alt="' . __( 'Loading...', 'it-l10n-backupbuddy' ) . '" title="' . __( 'Loading...', 'it-l10n-backupbuddy' ) . '" width="16" height="16" style="vertical-align: -3px;" /></span></a>',
		'tip'        => __( 'This is the TESTED amount of memory allowed to PHP scripts. The test was performed by outputting / logging the script memory usage while memory usage was increased. This gives a fairly accurate number compared to the reported number which is commonly overriden at the server with a limit, making it difficult to ascertain.', 'it-l10n-backupbuddy' ) . ' This test is limited to running no more often than every `' . pb_backupbuddy::$options['php_memory_test_minimum_interval'] . '` seconds based on your advanced settings (0 = disabled). Runs during housekeeping as well always on plugin activation.',
	);
	if ( is_numeric( pb_backupbuddy::$options['tested_php_memory'] ) ) {
		if ( 0 == pb_backupbuddy::$options['tested_php_memory'] ) {
			$parent_class_test['status'] = 'OK';
		} elseif ( pb_backupbuddy::$options['tested_php_memory'] < 123 ) {
			$parent_class_test['status'] = 'FAIL';
		} elseif ( pb_backupbuddy::$options['tested_php_memory'] < 245 ) { // 245 instead of 256 to give the test some wiggle room.
			$parent_class_test['status'] = 'WARNING';
		} else {
			$parent_class_test['status'] = 'OK';
		}
	} else {
		$parent_class_test['status'] = 'WARNING';
	}
	array_push( $tests, $parent_class_test );
}

// Max upload file size limit.
$max_upload        = backupbuddy_core::file_upload_max_size();
$parent_class_test = array(
	'title'      => 'PHP Maximum File Upload Size (server-reported)',
	'suggestion' => '>= 10 MB',
	'value'      => pb_backupbuddy::$format->file_size( $max_upload ),
	'tip'        => __( 'Maximum size of a file/data that this server allows to be uploaded/sent to it. Deployment uses this information to determine how much data can be sent per chunk, maximum.', 'it-l10n-backupbuddy' ),
);
if ( $max_upload / 1024 / 1024 < 10 ) { // < 10 MB warning
	$parent_class_test['status'] = 'WARNING';
} else {
	$parent_class_test['status'] = 'OK';
}
array_push( $tests, $parent_class_test );

// ERROR LOGGING ENABLED/DISABLED.
if ( true == ini_get( 'log_errors' ) ) {
	$parent_class_val = 'enabled';
} else {
	$parent_class_val = 'disabled';
}
$parent_class_test           = array(
	'title'      => 'PHP Error Logging (log_errors)',
	'suggestion' => 'enabled',
	'value'      => $parent_class_val . ' [<a href="javascript:void(0)" class="pb_backupbuddy_testErrorLog" rel="' . pb_backupbuddy::ajax_url( 'testErrorLog' ) . '" title="' . __( 'Testing this will trigger an error_log() event with the content "BackupBuddy Test - This is only a test. A user triggered BackupBuddy to determine if writing to the PHP error log is working as expected."', 'it-l10n-backupbuddy' ) . '">Test</a>]',
	'tip'        => __( 'Whether or not PHP errors are logged to a file or not. Set by php.ini log_errors', 'it-l10n-backupbuddy' ),
);
$parent_class_test['status'] = 'OK';
array_push( $tests, $parent_class_test );


// ERROR LOG FILE.
if ( ! ini_get( 'error_log' ) ) {
	$parent_class_val = 'unknown';
} else {
	$parent_class_val = ini_get( 'error_log' );
}
$parent_class_test           = array(
	'title'      => 'PHP Error Log File (error_log)',
	'suggestion' => 'n/a',
	'value'      => '<span style="display: inline-block; max-width: 250px;">' . $parent_class_val . '</span>',
	'tip'        => __( 'File where PHP errors are logged to if PHP Error Logging is enabled (recommended). Set by php.ini error_log', 'it-l10n-backupbuddy' ),
);
$parent_class_test['status'] = 'OK';
array_push( $tests, $parent_class_test );


// DISPLAY_ERRORS SETTING.
if ( true == ini_get( 'display_errors' ) ) {
	$parent_class_val = 'enabled';
} else {
	$parent_class_val = 'disabled';
}
$parent_class_test           = array(
	'title'      => 'PHP Display Errors to Screen (display_errors)',
	'suggestion' => 'disabled',
	'value'      => $parent_class_val,
	'tip'        => __( 'Whether or not PHP errors are displayed on screen to the user. This is useful for troubleshooting PHP problems but disabling by default is more secure for production. Set by php.ini display_errors', 'it-l10n-backupbuddy' ),
);
$parent_class_test['status'] = 'OK';
array_push( $tests, $parent_class_test );


if ( defined( 'PB_IMPORTBUDDY' ) ) {
	if ( ! isset( pb_backupbuddy::$classes['zipbuddy'] ) ) {
		require_once pb_backupbuddy::plugin_path() . '/lib/zipbuddy/zipbuddy.php';
		pb_backupbuddy::$classes['zipbuddy'] = new pluginbuddy_zipbuddy( ABSPATH );
	}
}
$zip_methods = implode( ', ', pb_backupbuddy::$classes['zipbuddy']->_zip_methods );

if ( ! defined( 'PB_IMPORTBUDDY' ) ) {
	$zipmethod_refresh = '<a class="pb_backupbuddy_refresh_stats" rel="refresh_zip_methods" alt="' . pb_backupbuddy::ajax_url( 'refresh_zip_methods' ) . '" title="' . __( 'Refresh', 'it-l10n-backupbuddy' ) . '"><img src="' . pb_backupbuddy::plugin_url() . '/images/refresh_gray.gif" style="vertical-align: -1px;"> <span class="pb_backupbuddy_loading" style="display: none; margin-left: 10px;"><img src="' . pb_backupbuddy::plugin_url() . '/images/loading.gif" alt="' . __( 'Loading...', 'it-l10n-backupbuddy' ) . '" title="' . __( 'Loading...', 'it-l10n-backupbuddy' ) . '" width="16" height="16" style="vertical-align: -3px;" /></span></a>';
} else {
	$zipmethod_refresh = '';
}
$parent_class_test = array(
	'title'      => 'Zip Methods',
	'suggestion' => 'Command line [fastest] > ziparchive > PHP-based (pclzip) [slowest]',
	'value'      => '<span id="pb_stats_refresh_zip_methods">' . $zip_methods . '</span> ' . $zipmethod_refresh,
	'tip'        => __( 'Methods your server supports for creating ZIP files. These were tested & verified to operate. Command line is magnitudes better than other methods and operates via exec() or other execution functions. ZipArchive is a PHP extension. PHP-based ZIP compression/extraction is performed via a PHP script called pclzip but it is slower and can be memory intensive.', 'it-l10n-backupbuddy' ),
);
if ( in_array( 'exec', pb_backupbuddy::$classes['zipbuddy']->_zip_methods ) ) {
	$parent_class_test['status'] = 'OK';
} else {
	$parent_class_test['status'] = 'WARNING';
}
array_push( $tests, $parent_class_test );


if ( ! defined( 'PB_IMPORTBUDDY' ) ) {

	$parent_class_test = array(
		'title'      => 'Database Dump Methods',
		'suggestion' => 'Command line and/or PHP-based',
		'value'      => implode( ', ', pb_backupbuddy::$classes['mysqlbuddy']->get_methods() ),
		'tip'        => __( 'Methods your server supports for dumping (backing up) your mysql database. These were tested values unless compatibility / troubleshooting settings override.', 'it-l10n-backupbuddy' ),
	);
	$db_methods        = pb_backupbuddy::$classes['mysqlbuddy']->get_methods();
	if ( in_array( 'commandline', $db_methods ) || in_array( 'php', $db_methods ) ) { // PHP is considered just as good as of BB v5.0.
		$parent_class_test['status'] = 'OK';
	} else {
		$parent_class_test['status'] = 'WARNING';
	}
	array_push( $tests, $parent_class_test );


	// Site Size.
	if ( pb_backupbuddy::$options['stats']['site_size'] > 0 ) {
		$site_size = pb_backupbuddy::$format->file_size( pb_backupbuddy::$options['stats']['site_size'] );
	} else {
		$site_size = '<i>Unknown</i>';
	}
	$parent_class_test           = array(
		'title'      => 'Site Size',
		'suggestion' => 'n/a',
		'value'      => '<span id="pb_stats_refresh_site_size">' . $site_size . '</span> <a class="pb_backupbuddy_refresh_stats" rel="refresh_site_size" alt="' . pb_backupbuddy::ajax_url( 'refresh_site_size' ) . '" title="' . __( 'Refresh', 'it-l10n-backupbuddy' ) . '"><img src="' . pb_backupbuddy::plugin_url() . '/images/refresh_gray.gif" style="vertical-align: -1px;"> <span class="pb_backupbuddy_loading" style="display: none; margin-left: 10px;"><img src="' . pb_backupbuddy::plugin_url() . '/images/loading.gif" alt="' . __( 'Loading...', 'it-l10n-backupbuddy' ) . '" title="' . __( 'Loading...', 'it-l10n-backupbuddy' ) . '" width="16" height="16" style="vertical-align: -3px;" /></span></a>',
		'tip'        => __( 'Total size of your site (starting in your WordPress main directory) INCLUDING any excluded directories / files.', 'it-l10n-backupbuddy' ),
	);
	$parent_class_test['status'] = 'OK';
	array_push( $tests, $parent_class_test );


	// Site size WITH EXCLUSIONS accounted for.
	if ( pb_backupbuddy::$options['stats']['site_size_excluded'] > 0 ) {
		$site_size_excluded = pb_backupbuddy::$format->file_size( pb_backupbuddy::$options['stats']['site_size_excluded'] );
	} else {
		$site_size_excluded = '<i>Unknown</i>';
	}
	$parent_class_test           = array(
		'title'      => 'Site Size (Default Exclusions applied)',
		'suggestion' => 'n/a',
		'value'      => '<span id="pb_stats_refresh_site_size_excluded">' . $site_size_excluded . '</span> <a class="pb_backupbuddy_refresh_stats" rel="refresh_site_size_excluded" alt="' . pb_backupbuddy::ajax_url( 'refresh_site_size_excluded' ) . '" title="' . __( 'Refresh', 'it-l10n-backupbuddy' ) . '"><img src="' . pb_backupbuddy::plugin_url() . '/images/refresh_gray.gif" style="vertical-align: -1px;"> <span class="pb_backupbuddy_loading" style="display: none; margin-left: 10px;"><img src="' . pb_backupbuddy::plugin_url() . '/images/loading.gif" alt="' . __( 'Loading...', 'it-l10n-backupbuddy' ) . '" title="' . __( 'Loading...', 'it-l10n-backupbuddy' ) . '" width="16" height="16" style="vertical-align: -3px;" /></span></a>',
		'tip'        => __( 'Total size of your site (starting in your WordPress main directory) EXCLUDING any directories / files you have marked for exclusion.', 'it-l10n-backupbuddy' ),
	);
	$parent_class_test['status'] = 'OK';
	array_push( $tests, $parent_class_test );


	// Site Objects.
	if ( isset( pb_backupbuddy::$options['stats']['site_objects'] ) && ( pb_backupbuddy::$options['stats']['site_objects'] > 0 ) ) {
		$site_objects = pb_backupbuddy::$options['stats']['site_objects'];
	} else {
		$site_objects = '<i>Unknown</i>';
	}
	$parent_class_test           = array(
		'title'      => 'Site number of files',
		'suggestion' => 'n/a',
		'value'      => '<span id="pb_stats_refresh_objects">' . $site_objects . '</span> <a class="pb_backupbuddy_refresh_stats" rel="refresh_objects" alt="' . pb_backupbuddy::ajax_url( 'refresh_site_objects' ) . '" title="' . __( 'Refresh', 'it-l10n-backupbuddy' ) . '"><img src="' . pb_backupbuddy::plugin_url() . '/images/refresh_gray.gif" style="vertical-align: -1px;"> <span class="pb_backupbuddy_loading" style="display: none; margin-left: 10px;"><img src="' . pb_backupbuddy::plugin_url() . '/images/loading.gif" alt="' . __( 'Loading...', 'it-l10n-backupbuddy' ) . '" title="' . __( 'Loading...', 'it-l10n-backupbuddy' ) . '" width="16" height="16" style="vertical-align: -3px;" /></span></a>',
		'tip'        => __( 'Total number of files/folders in your site (starting in your WordPress main directory) INCLUDING any excluded directories / files.', 'it-l10n-backupbuddy' ),
	);
	$parent_class_test['status'] = 'OK';
	array_push( $tests, $parent_class_test );


	// Site objects WITH EXCLUSIONS accounted for.
	$site_objects_excluded = '<i>Unknown</i>';
	if ( isset( pb_backupbuddy::$options['stats']['site_objects_excluded'] ) && pb_backupbuddy::$options['stats']['site_objects_excluded'] > 0 ) {
		$site_objects_excluded = pb_backupbuddy::$options['stats']['site_objects_excluded'];
	}
	$parent_class_test           = array(
		'title'      => 'Site number of files (Default Exclusions applied)',
		'suggestion' => 'n/a',
		'value'      => '<span id="pb_stats_refresh_objects_excluded">' . $site_objects_excluded . '</span> <a class="pb_backupbuddy_refresh_stats" rel="refresh_objects_excluded" alt="' . pb_backupbuddy::ajax_url( 'refresh_site_objects_excluded' ) . '" title="' . __( 'Refresh', 'it-l10n-backupbuddy' ) . '"><img src="' . pb_backupbuddy::plugin_url() . '/images/refresh_gray.gif" style="vertical-align: -1px;"> <span class="pb_backupbuddy_loading" style="display: none; margin-left: 10px;"><img src="' . pb_backupbuddy::plugin_url() . '/images/loading.gif" alt="' . __( 'Loading...', 'it-l10n-backupbuddy' ) . '" title="' . __( 'Loading...', 'it-l10n-backupbuddy' ) . '" width="16" height="16" style="vertical-align: -3px;" /></span></a>',
		'tip'        => __( 'Total number of files/folders site (starting in your WordPress main directory) EXCLUDING any directories / files you have marked for exclusion.', 'it-l10n-backupbuddy' ),
	);
	$parent_class_test['status'] = 'OK';
	array_push( $tests, $parent_class_test );


	// Database Size.
	$parent_class_test           = array(
		'title'      => 'Database Size',
		'suggestion' => 'n/a',
		'value'      => '<span id="pb_stats_refresh_database_size">' . pb_backupbuddy::$format->file_size( pb_backupbuddy::$options['stats']['db_size'] ) . '</span> <a class="pb_backupbuddy_refresh_stats" rel="refresh_database_size" alt="' . pb_backupbuddy::ajax_url( 'refresh_database_size' ) . '" title="' . __( 'Refresh', 'it-l10n-backupbuddy' ) . '"><img src="' . pb_backupbuddy::plugin_url() . '/images/refresh_gray.gif" style="vertical-align: -1px;"> <span class="pb_backupbuddy_loading" style="display: none; margin-left: 10px;"><img src="' . pb_backupbuddy::plugin_url() . '/images/loading.gif" alt="' . __( 'Loading...', 'it-l10n-backupbuddy' ) . '" title="' . __( 'Loading...', 'it-l10n-backupbuddy' ) . '" width="16" height="16" style="vertical-align: -3px;" /></span></a>',
		'tip'        => __( 'Total size of your database INCLUDING any excluded tables.', 'it-l10n-backupbuddy' ),
	);
	$parent_class_test['status'] = 'OK';
	array_push( $tests, $parent_class_test );


	// Database size WITH EXCLUSIONS accounted for.
	$parent_class_test           = array(
		'title'      => 'Database Size (Default Exclusions applied)',
		'suggestion' => 'n/a',
		'value'      => '<span id="pb_stats_refresh_database_size_excluded">' . pb_backupbuddy::$format->file_size( pb_backupbuddy::$options['stats']['db_size_excluded'] ) . '</span> <a class="pb_backupbuddy_refresh_stats" rel="refresh_database_size_excluded" alt="' . pb_backupbuddy::ajax_url( 'refresh_database_size_excluded' ) . '" title="' . __( 'Refresh', 'it-l10n-backupbuddy' ) . '"><img src="' . pb_backupbuddy::plugin_url() . '/images/refresh_gray.gif" style="vertical-align: -1px;"> <span class="pb_backupbuddy_loading" style="display: none; margin-left: 10px;"><img src="' . pb_backupbuddy::plugin_url() . '/images/loading.gif" alt="' . __( 'Loading...', 'it-l10n-backupbuddy' ) . '" title="' . __( 'Loading...', 'it-l10n-backupbuddy' ) . '" width="16" height="16" style="vertical-align: -3px;" /></span></a>',
		'tip'        => __( 'Total size of your database EXCLUDING any tables you have marked for exclusion.', 'it-l10n-backupbuddy' ),
	);
	$parent_class_test['status'] = 'OK';
	array_push( $tests, $parent_class_test );


	/***** BEGIN AVERAGE WRITE SPEED */
	require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';

	$write_speed_samples = 0;
	$write_speed_sum     = 0;
	$backups             = glob( backupbuddy_core::getBackupDirectory() . '*.zip' );
	if ( ! is_array( $backups ) ) {
		$backups = array();
	}
	foreach ( $backups as $backup ) {

		$serial = backupbuddy_core::get_serial_from_file( $backup );
		pb_backupbuddy::status( 'details', 'Fileoptions instance #22.' );
		$backup_options = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/' . $serial . '.txt', true );
		$result         = $backup_options->is_ok();
		if ( true !== $result ) {
			pb_backupbuddy::status( 'warning', 'Unable to open fileoptions file `' . backupbuddy_core::getLogDirectory() . 'fileoptions/' . $serial . '.txt`. Details: `' . $result . '`.' );
		}


		if ( isset( $backup_options->options['integrity'] ) && isset( $backup_options->options['integrity']['size'] ) ) {
			$write_speed_samples++;

			$size       = $backup_options->options['integrity']['size'];
			$time_taken = 0;
			if ( isset( $backup_options->options['steps'] ) ) {
				foreach ( $backup_options->options['steps'] as $step ) {
					if ( 'backup_zip_files' == $step['function'] ) {
						$time_taken = $step['finish_time'] - $step['start_time'];
						break;
					}
				} // End foreach.
			} // End if steps isset.

			if ( 0 == $time_taken ) {
				$write_speed_samples = $write_speed_samples--; // Ignore this sample since it's too small to count.
			} else {
				$write_speed_sum += $size / $time_taken; // Sum up write speeds.
			}
		}
	}

	if ( $write_speed_sum > 0 ) {
		$final_write_speed       = pb_backupbuddy::$format->file_size( $write_speed_sum / $write_speed_samples ) . '/sec';
		$final_write_speed_guess = pb_backupbuddy::$format->file_size( ( $write_speed_sum / $write_speed_samples ) * ini_get( 'max_execution_time' ) );
	} else {
		$final_write_speed       = '<i>Unknown</i>';
		$final_write_speed_guess = '<i>Unknown</i>';
	}

	$parent_class_test           = array(
		'title'      => 'Average Write Speed',
		'suggestion' => 'n/a',
		'value'      => $final_write_speed,
		'tip'        => __( 'Average ZIP file creation write speed. Backup file sizes divided by the time taken to create each. Samples', 'it-l10n-backupbuddy' ) . ': `' . $write_speed_samples . '`.',
	);
	$parent_class_test['status'] = 'OK';
	array_push( $tests, $parent_class_test );
	/***** END AVERAGE WRITE SPEED */


	// Guess max site size to be able to backup.
	$parent_class_test           = array(
		'title'      => 'Guesstimate of max ZIP size',
		'suggestion' => 'n/a',
		'value'      => $final_write_speed_guess,
		'tip'        => __( 'Calculated estimate of the largest .ZIP backup file that may be created. As ZIP files are compressed the site size that may be backed up should be larger than this.', 'it-l10n-backupbuddy' ),
	);
	$parent_class_test['status'] = 'OK';
	array_push( $tests, $parent_class_test );



	// Http loopbacks.
	$loopback_response = backupbuddy_core::loopback_test();
	if ( true === $loopback_response ) {
		$loopback_status = 'enabled';
		$status          = __( 'OK', 'it-l10n-backupbuddy' );
	} else {
		$loopback_status = 'disabled (enable alternate cron)';
		$status          = __( 'WARNING', 'it-l10n-backupbuddy' );
	}
	global $backupbuddy_loopback_details;
	$parent_class_test           = array(
		'title'      => 'Http Loopbacks',
		'suggestion' => 'enabled',
		'value'      => $loopback_status . '<br><textarea style="width: 100%; max-height: 200px;" readonly="true">' . $backupbuddy_loopback_details . '</textarea>',
		'tip'        => __( 'Some servers do are not configured properly to allow WordPress to connect back to itself via the site URL (ie: http://your.com connects back to itself on the same server at http://your.com/ to trigger a simulated cron step). If this is the case you must either ask your hosting provider to fix this or enable WordPres Alternate Cron mode in your wp-config.php file.', 'it-l10n-backupbuddy' ),
	);
	$parent_class_test['status'] = $status;
	array_push( $tests, $parent_class_test );

	// wp-cron.php http loopbacks.
	global $backupbuddy_cronback_details;
	$cronback_response = backupbuddy_core::cronback_test( true );
	if ( true === $cronback_response ) {
		$cronback_status = 'enabled';
		$status          = __( 'OK', 'it-l10n-backupbuddy' );
	} else {
		$cronback_status = ( false !== stristr( $backupbuddy_cronback_details, 'is reachable' ) ) ? 'broken (theme or plugin conflicts)' : 'disabled (enable alternate cron)';
		$status          = __( 'WARNING', 'it-l10n-backupbuddy' );
	}
	$parent_class_test           = array(
		'title'      => 'wp-cron.php Loopbacks',
		'suggestion' => 'enabled',
		'value'      => $cronback_status . '<br><textarea style="width: 100%; max-height: 200px;" readonly="true">' . $backupbuddy_cronback_details . '</textarea>',
		'tip'        => __( 'Some servers do are not configured properly to allow WordPress to connect back to itself via the site URL (ie: http://your.com connects back to itself on the same server at http://your.com/ to trigger a simulated cron step). If this is the case you must either ask your hosting provider to fix this or enable WordPres Alternate Cron mode in your wp-config.php file.', 'it-l10n-backupbuddy' ),
	);
	$parent_class_test['status'] = $status;
	array_push( $tests, $parent_class_test );


	// Http loopback URL & IP.
	$status                      = __( 'OK', 'it-l10n-backupbuddy' );
	$parsed_url                  = parse_url( site_url() );
	$ip                          = gethostbyname( $parsed_url['host'] );
	$parent_class_test           = array(
		'title'      => 'Loopback Domain & IP',
		'suggestion' => 'n/a',
		'value'      => $parsed_url['host'] . ' =&gt; ' . $ip,
		'tip'        => __( 'Sometimes due to DNS delays the server may detect the old IP address as being associated with your site domain used in the loopback URL. This can result in loopback problems even though they may be enabled. Contact your host or wait longer if the IP address this server reports is incorrect.', 'it-l10n-backupbuddy' ),
	);
	$parent_class_test['status'] = $status;
	array_push( $tests, $parent_class_test );



	// CRON disabled?
	if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
		$cron_status = 'disabled';
		$status      = __( 'FAIL', 'it-l10n-backupbuddy' );
	} else {
		$cron_status = 'enabled';
		$status      = __( 'OK', 'it-l10n-backupbuddy' );
	}
	$parent_class_test           = array(
		'title'      => __( 'WordPress Cron', 'it-l10n-backupbuddy' ),
		'suggestion' => 'enabled',
		'value'      => $cron_status,
		'tip'        => __( 'This check verifies that the cron system has not been disabled by the DISABLE_WP_CRON constant. This may be defined by a plugin or other method to disable the cron system which may result in automated functionality not being available.', 'it-l10n-backupbuddy' ),
	);
	$parent_class_test['status'] = $status;
	array_push( $tests, $parent_class_test );


	// Alternate cron on?
	if ( defined( 'ALTERNATE_WP_CRON' ) && true === ALTERNATE_WP_CRON ) {
		$alternate_cron_status = 'enabled';
	} else {
		$alternate_cron_status = 'disabled (default)';
	}
	$parent_class_test           = array(
		'title'      => 'WordPress Alternate Cron',
		'suggestion' => 'Varies (server-dependent)',
		'value'      => $alternate_cron_status,
		'tip'        => __( 'Some servers do not allow sites to connect back to themselves at their own URL.  WordPress and BackupBuddy make use of these "Http Loopbacks" for several things.  Without them you may encounter issues. If your server needs it or you are directed by support you may enable Alternate Cron in your wp-config.php file.  When enabled this setting will display "Enabled" to remind you.', 'it-l10n-backupbuddy' ),
	);
	$parent_class_test['status'] = __( 'OK', 'it-l10n-backupbuddy' );
	array_push( $tests, $parent_class_test );

} // End non-importbuddy tests.


// DISABLED FUNCTIONS.
$disabled_functions = ini_get( 'disable_functions' );
if ( '' == $disabled_functions ) {
	$disabled_functions = '(none)';
}
$parent_class_test           = array(
	'title'      => 'Disabled PHP Functions',
	'suggestion' => 'n/a',
	'value'      => '<textarea style="width: 100%; max-height: 200px;" readonly="true">' . str_replace( ',', ', ', $disabled_functions ) . '</textarea>',
	'tip'        => __( 'Some hosts block certain PHP functions for various reasons. Sometimes hosts block functions that are required for proper functioning of WordPress or plugins.', 'it-l10n-backupbuddy' ),
);
$disabled_functions          = str_replace( ', ', ',', $disabled_functions ); // Normalize spaces or lack of spaces between disabled functions.
$disabled_functions_array    = explode( ',', $disabled_functions );
$parent_class_test['status'] = 'OK';
if ( true === in_array( 'exec', $disabled_functions_array ) || true === in_array( 'ini_set', $disabled_functions_array ) ) {
	$parent_class_test['status'] = __( 'WARNING', 'it-l10n-backupbuddy' );
}
array_push( $tests, $parent_class_test );


// MYSQL_CONNECT.
if ( is_callable( 'mysql_connect' ) ) {
	$parent_class_val = 'enabled';
} else {
	$parent_class_val = 'disabled';
}
$parent_class_test           = array(
	'title'      => 'PHP function: mysql_connect()',
	'suggestion' => 'n/a',
	'value'      => $parent_class_val,
	'tip'        => __( 'Deprecated in PHP 5.5.0 and removed in PHP 7.0.0. Replaced by mysqli_connect or PDO::__construct()', 'it-l10n-backupbuddy' ),
);
$parent_class_test['status'] = __( 'OK', 'it-l10n-backupbuddy' );
array_push( $tests, $parent_class_test );


// REGISTER GLOBALS.
if ( ini_get_bool( 'register_globals' ) === true ) {
	$parent_class_val = 'enabled';
} else {
	$parent_class_val = 'disabled';
}
$parent_class_test = array(
	'title'      => 'PHP Register Globals',
	'suggestion' => 'disabled',
	'value'      => $parent_class_val,
	'tip'        => __( 'Automatically registers user input as variables. HIGHLY discouraged. Removed from PHP in PHP 6 for security.', 'it-l10n-backupbuddy' ),
);
if ( 'disabled' != $parent_class_val ) {
	$parent_class_test['status'] = 'FAIL';
} else {
	$parent_class_test['status'] = 'OK';
}
array_push( $tests, $parent_class_test );


// MAGIC QUOTES GPC.
if ( ini_get_bool( 'magic_quotes_gpc' ) === true ) {
	$parent_class_val = 'enabled';
} else {
	$parent_class_val = 'disabled';
}
$parent_class_test = array(
	'title'      => 'PHP Magic Quotes GPC',
	'suggestion' => 'disabled',
	'value'      => $parent_class_val,
	'tip'        => __( 'Automatically escapes user inputted data. Not needed when using properly coded software.', 'it-l10n-backupbuddy' ),
);
if ( 'disabled' != $parent_class_val ) {
	$parent_class_test['status'] = 'WARNING';
} else {
	$parent_class_test['status'] = 'OK';
}
array_push( $tests, $parent_class_test );

// MAGIC QUOTES RUNTIME.
if ( ini_get_bool( 'magic_quotes_runtime' ) === true ) {
	$parent_class_val = 'enabled';
} else {
	$parent_class_val = 'disabled';
}
$parent_class_test = array(
	'title'      => 'PHP Magic Quotes Runtime',
	'suggestion' => 'disabled',
	'value'      => $parent_class_val,
	'tip'        => __( 'Automatically escapes user inputted data. Not needed when using properly coded software.', 'it-l10n-backupbuddy' ),
);
if ( 'disabled' != $parent_class_val ) {
	$parent_class_test['status'] = 'WARNING';
} else {
	$parent_class_test['status'] = 'OK';
}
array_push( $tests, $parent_class_test );


// SAFE MODE.
if ( ini_get_bool( 'safe_mode' ) === true ) {
	$parent_class_val = 'enabled';
} else {
	$parent_class_val = 'disabled';
}
$parent_class_test = array(
	'title'      => 'PHP Safe Mode',
	'suggestion' => 'disabled',
	'value'      => $parent_class_val,
	'tip'        => __( 'This mode is HIGHLY discouraged and is a sign of a poorly configured host.', 'it-l10n-backupbuddy' ),
);
if ( 'disabled' != $parent_class_val ) {
	$parent_class_test['status'] = 'WARNING';
} else {
	$parent_class_test['status'] = 'OK';
}
array_push( $tests, $parent_class_test );


// PHP API.
$php_api = 'Unknown';
if ( is_callable( 'php_sapi_name' ) ) {
	$php_api = php_sapi_name();
}
$parent_class_test           = array(
	'title'      => 'PHP API',
	'suggestion' => 'n/a',
	'value'      => $php_api,
	'tip'        => __( 'API mode PHP is running under.', 'it-l10n-backupbuddy' ),
);
$parent_class_test['status'] = 'OK';
array_push( $tests, $parent_class_test );



// PHP Bits.
$bits              = ( PHP_INT_SIZE * 8 );
$parent_class_test = array(
	'title'      => 'PHP Architecture',
	'suggestion' => '64-bit',
	'value'      => $bits . '-bit',
	'tip'        => __( 'Whether PHP is running in 32 or 64 bit mode. 64-bit is recommended over 32-bit. Note: This only determines PHP status NOT status of other server functionality such as filesystem, command line zip, etc.', 'it-l10n-backupbuddy' ),
);
if ( $bits < 60 ) {
	$parent_class_test['status'] = 'WARNING';
} else {
	$parent_class_test['status'] = 'OK';
}
array_push( $tests, $parent_class_test );



// http Server Software.
if ( isset( $_SERVER['SERVER_SOFTWARE'] ) ) {
	$server_software = $_SERVER['SERVER_SOFTWARE'];
} else {
	$server_software = 'Unknown';
}
$parent_class_test           = array(
	'title'      => 'Http Server Software',
	'suggestion' => 'n/a',
	'value'      => $server_software,
	'tip'        => __( 'Software running this http web server, such as Apache, IIS, or Nginx.', 'it-l10n-backupbuddy' ),
);
$parent_class_test['status'] = 'OK';
array_push( $tests, $parent_class_test );



// Load Average.
if ( ! defined( 'PB_IMPORTBUDDY' ) ) {
	$load_average = pb_backupbuddy_get_loadavg();
	foreach ( $load_average as &$this_load ) {
		$this_load = round( $this_load, 2 );
	}
	$parent_class_test           = array(
		'title'      => 'Server Load Average',
		'suggestion' => 'n/a',
		'value'      => implode( ', ', $load_average ),
		'tip'        => __( 'Server CPU use in intervals: 1 minute, 5 minutes, 15 minutes. E.g. .45 basically equates to 45% CPU usage.', 'it-l10n-backupbuddy' ),
	);
	$parent_class_test['status'] = 'OK';
	array_push( $tests, $parent_class_test );
}



// SFTP support?
if ( ! defined( 'PB_IMPORTBUDDY' ) ) {
	$connect = 'no';
	$sftp    = 'no';
	if ( is_callable( 'ssh2_connect' ) && false === in_array( 'ssh2_connect', $disabled_functions_array ) ) {
		$connect = 'yes';
	}
	if ( is_callable( 'ssh2_ftp' ) && false === in_array( 'ssh2_ftp', $disabled_functions_array ) ) {
		$connect = 'yes';
	}
	$parent_class_test           = array(
		'title'      => 'PHP SSH2, SFTP Support',
		'suggestion' => 'n/a',
		'value'      => $connect . ', ' . $sftp,
		'tip'        => __( 'Whether or not your server is configured to allow SSH2 connections over PHP or SFTP connections or PHP. Most hosts do not currently provide this feature. Information only; BackupBuddy cannot make use of this functionality at this time.', 'it-l10n-backupbuddy' ),
	);
	$parent_class_test['status'] = 'OK';
	array_push( $tests, $parent_class_test );
}



// ABSPATH.
$parent_class_test = array(
	'title'      => 'WordPress ABSPATH',
	'suggestion' => 'n/a',
	'value'      => '<span style="display: inline-block; max-width: 250px;">' . ABSPATH . '</span>',
	'tip'        => __( 'This is the directory which WordPress reports to BackupBuddy it is installed in.', 'it-l10n-backupbuddy' ),
);
if ( ! @file_exists( ABSPATH ) ) {
	$parent_class_test['status'] = 'WARNING';
} else {
	$parent_class_test['status'] = 'OK';
}
array_push( $tests, $parent_class_test );



// OS.
$php_uname = '';
if ( is_callable( 'php_uname' ) ) {
	$php_uname = ' <span style="display: block; max-width: 250px; font-size: 8px; line-height:1.25;">(' . @php_uname() . ')</span>';
}
$parent_class_test = array(
	'title'      => 'Operating System',
	'suggestion' => 'Linux',
	'value'      => PHP_OS . $php_uname,
	'tip'        => __( 'The server operating system running this site. Linux based systems are encouraged. Windows users may need to perform additional steps to get plugins to perform properly.', 'it-l10n-backupbuddy' ),
);
if ( substr( PHP_OS, 0, 3 ) == 'WIN' ) {
	$parent_class_test['status'] = 'WARNING';
} else {
	$parent_class_test['status'] = 'OK';
}
array_push( $tests, $parent_class_test );



// Is this possibly GoDaddy Managed WordPress Hosting?
if ( defined( 'GD_SYSTEM_PLUGIN_DIR' ) || class_exists( '\\WPaaS\\Plugin' ) ) {
	$parent_class_test           = array(
		'title'      => 'GoDaddy Managed WordPress Hosting Detected',
		'suggestion' => 'n/a',
		'value'      => 'Potentially Detected',
		'tip'        => __( 'GoDaddy\'s Managed WordPress Hosting recently experienced problems resulting in the WordPress cron not working properly resulting in WordPress\' built-in scheduling and automation functionality malfunctioning. GoDaddy has addressed this issue for US-based customers and we believe it to be resolved for those hosted in the USA. Non-US customers should contact GoDaddy support. However, if you still experience issues and require a partial workaround go to BackupBuddy -> Settings page -> Advanced Settings / Troubleshooting tab -> Check the box Force internal cron -> Scroll down and Save the settings.  This may help you be able to make a manual traditional backup though it may be slow and is not guaranteed.', 'it-l10n-backupbuddy' ),
	);
	$parent_class_test['status'] = 'WARNING';
	array_push( $tests, $parent_class_test );
}


// Active plugins list.
if ( ! defined( 'PB_IMPORTBUDDY' ) ) {
	// Active Plugins.
	$success        = true;
	$active_plugins = serialize( get_option( 'active_plugins' ) );
	$found_plugins  = array();
	foreach ( backupbuddy_core::$warn_plugins as $warn_plugin => $warn_plugin_title ) {
		if ( false !== strpos( $active_plugins, $warn_plugin ) ) { // Plugin active.
			$found_plugins[] = $warn_plugin_title;
			$success         = false;
		}
	}
	$parent_class_test = array(
		'title'      => 'Active WordPress Plugins',
		'suggestion' => 'n/a',
		'value'      => '<textarea style="width: 100%; height: 70px;" readonly="true">' . implode( ', ', unserialize( $active_plugins ) ) . '</textarea>',
		'tip'        => __( 'Plugins currently activated for this site. A warning does not guarentee problems with a plugin but indicates that a plugin is activated that at one point may have caused operational issues.  Plugin conflicts can be specific and may only occur under certain circumstances such as certain plugin versions, plugin configurations, and server settings.', 'it-l10n-backupbuddy' ),
	);
	if ( false === $success ) {
		$parent_class_test['status'] = 'WARNING';
	} else {
		$parent_class_test['status'] = 'OK';
	}
	array_push( $tests, $parent_class_test );
}



// PHP Process user/group.
if ( ! defined( 'PB_IMPORTBUDDY' ) ) {
	$success  = true;
	$php_user = '<i>' . __( 'Unknown', 'it-l10n-backupbuddy' ) . '</i>';
	$php_uid  = '<i>' . __( 'Unknown', 'it-l10n-backupbuddy' ) . '</i>';
	$php_gid  = '<i>' . __( 'Unknown', 'it-l10n-backupbuddy' ) . '</i>';

	if ( is_callable( 'posix_geteuid' ) && false === in_array( 'posix_geteuid', $disabled_functions_array ) ) {
		$php_uid = @posix_geteuid();
		if ( is_callable( 'posix_getpwuid' ) && false === in_array( 'posix_getpwuid', $disabled_functions_array ) ) {
			$php_user = @posix_getpwuid( $php_uid );
			$php_user = $php_user['name'];
		}
	}
	if ( is_callable( 'posix_getegid' ) && ( false === in_array( 'posix_getegid', $disabled_functions_array ) ) ) {
		$php_gid = @posix_getegid();
	}
	$parent_class_test = array(
		'title'      => 'PHP Process User (UID:GID)',
		'suggestion' => 'n/a',
		'value'      => $php_user . ' (' . $php_uid . ':' . $php_gid . ')',
		'tip'        => __( 'Current user, user ID, and group ID under which this PHP process is running. This user must have proper access to your files and directories. If the PHP user is not your own then setting up a system such as suphp is encouraged to ensure proper access and security.', 'it-l10n-backupbuddy' ),
	);
	if ( false === $success ) {
		$parent_class_test['status'] = 'WARNING';
	} else {
		$parent_class_test['status'] = 'OK';
	}
	array_push( $tests, $parent_class_test );
}


// Deployment API enabled?
if ( ! defined( 'PB_IMPORTBUDDY' ) ) {
	$deployment_enabled = __( 'Disabled', 'it-l10n-backupbuddy' );
	if ( ! defined( 'BACKUPBUDDY_API_ENABLE' ) ) {
		$deployment_enabled .= ' (' . __( 'Setting Undetected', 'it-l10n-backupbuddy' ) . ')';
	} elseif ( true == BACKUPBUDDY_API_ENABLE ) {
		$deployment_enabled = __( 'Enabled', 'it-l10n-backupbuddy' );
	}

	$parent_class_test           = array(
		'title'      => 'BackupBuddy Deployment API wp-config setting',
		'suggestion' => 'n/a',
		'value'      => $deployment_enabled,
		'tip'        => __( 'Enabling the BackupBuddy Deployment API via the wp-config.php allows other BackupBuddy installations supplied with the authentication key to Push to or Pull from this site\'s data. Useful for development purposes.', 'it-l10n-backupbuddy' ),
	);
	$parent_class_test['status'] = 'OK';
	array_push( $tests, $parent_class_test );
}
