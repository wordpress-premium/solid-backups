<?php
/**
 * Plugin Name: Solid Backups
 * Plugin URI: https://go.solidwp.com/solid-backups-home
 * Description: Safely store your website with automated backups and one click restore.
 * Version: 9.1.10
 * Author: SolidWP
 * Author URI: https://go.solidwp.com/solid-backups-home
 *
 * // This ensures the SolidWP Updater loads. Don't remove it.
 * iThemes Package: backupbuddy
 *
 * INSTALLATION:
 *
 *      1. Download and unzip the latest release zip file.
 *      2. If you use the WordPress plugin uploader to install this plugin skip to step 4.
 *      3. Upload the entire plugin directory to your `/wp-content/plugins/` directory.
 *      4. Activate the plugin through the 'Plugins' menu in WordPress Administration.
 *
 * CONTRIBUTORS (since BackupBuddy v1.0; launched March 4, 2010):
 *
 *      Dustin Bolton (creation, lead BB dev, anything & everything 2010-2018), Chris Jean (early zip), Josh Benham (misc code, support, testing),
 *      Skyler Moore (ftp, misc code, support, testing), Jeremy Trask (xzip, misc code, support), Ronald Huereca (early multisite)
 *      Dustin Akers (lead BB support, testing), Daniel Harzheim (testing, settings form verification, PB framework contributions),
 *      Bradford Ulrich (UI, graphics), Glenn Ansley (misc code, support), Thomas Oliver (support), Ty Carlson (Stash UI, graphics),
 *      Brian DiChiara (lead BackupBuddy Dev 2018), Tyler Gilbet (support, testing), Yobani Mendoza (support, testing), Matthew Cortinas (support, testing).
 */

/**
 * Plugin defaults.
 *
 * Settings stored in wp_options under "pb_backupbuddy".
 * Auditing notifications stored in "pb_backupbuddy_notifications" as of 6.1.0.0.
 */
$pluginbuddy_settings = array(
	'slug'                       => 'backupbuddy',
	'series'                     => '',
	'default_options'            => array(
		'data_version'                            => '18',     // Data structure version. Added BB 2.0 to ease updating.
		'importbuddy_pass_hash'                   => '',       // Importer password hash.
		'importbuddy_pass_length'                 => 0,        // Length of the Importer password before it was hashed.
		'edits_since_last'                        => array(    // Number of recent edits since the last backup began.
			'all'    => 0, // All recent edits total.
			'post'   => 0, // Post edits total.
			'plugin' => 0, // Plugin edits total.
			'option' => 0, // Option edits total.
		),
		'recent_edits'                            => array(),  // Track recent edits.
		'edits_tracking_mode'                     => 'basic',   // Mode for tracking recent edits (basic/advanced).
		'last_backup_start'                       => 0,        // Timestamp of when last backup started.
		'last_backup_finish'                      => 0,        // Timestamp of when the last backup finished.
		'last_backup_serial'                      => '',       // Serial of last backup zip.
		'last_backup_stats'                       => array(),  // Some misc stats about the last backup which completed. Also used by Solid Central.
		'last_error_email_time'                   => 0,        // Timestamp last error email sent. Tracked to prevent flooding.
		'force_compatibility'                     => 0,        // Force compatibility mode even if normal is detected.
		'force_mysqldump_compatibility'           => 0,        // Force compatibility mode for mysql db dumping. Uses PHP-based rather than command line mysqldump.
		'schedules'                               => array(),  // Array of scheduled schedules.
		'log_level'                               => '1',      // Valid options: 0 = none, 1 = errors only, 2 = errors + warnings, 3 = debugging (all kinds of actions).
		'backup_reminders'                        => 1,        // Whether to show reminders to back up on post/page edits & on the WP upgrade page.
		'high_security'                           => 0,        // TODO: Future feature. Strip mysql password & admin user password. Prompt on import.
		'next_schedule_index'                     => 100,      // Next schedule index. Prevent any risk of hanging scheduled crons from having the same ID as a new schedule.
		'archive_limit'                           => 0,        // Maximum number of archives to storage. Deletes oldest if exceeded.
		'archive_limit_full'                      => 0,
		'archive_limit_db'                        => 0,
		'archive_limit_files'                     => 0,
		'archive_limit_size'                      => 0,        // Maximum size of all archives to store. Deletes oldest if exceeded.
		'archive_limit_size_big'                  => 50000,    // Secondary over-arching archive size limit. More buried away on Advanced Settings page.
		'archive_limit_age'                       => 0,        // Maximum age (in days) backup files can be before being deleted. Any exceeding age deleted on backup.
		'delete_archives_pre_backup'              => 0,        // Whether or not to delete all backups prior to backing up.
		'lock_archives_directory'                 => '0',      // Whether or not to lock archives directory via htaccess and lift lock temporarily for download.
		'set_greedy_execution_time'               => 0,        // Whether or not to try and override PHP max execution time to a higher value. Most hosts block this.

		'email_notify_scheduled_start'            => '',  // Email address(es) to send to when a scheduled backup begins.
		'email_notify_scheduled_start_subject'    => 'Solid Backups Scheduled Backup Started - {home_url}',
		'email_notify_scheduled_start_body'       => "A scheduled backup has started with Solid Backups v{backupbuddy_version} on {current_datetime} for the site {home_url}.\n\nDetails:\r\n\r\n{message}",
		'email_notify_scheduled_complete'         => '',  // Email address(es) to send to when a scheduled backup completes.
		'email_notify_scheduled_complete_subject' => 'Solid Backups Scheduled Backup Complete - {home_url}',
		'email_notify_scheduled_complete_body'    => "A scheduled backup has completed with Solid Backups v{backupbuddy_version} on {current_datetime} for the site {home_url}.\n\nDetails:\r\n\r\n{message}",
		'email_notify_send_finish'                => '',  // Email address(es) to send to when a send finishes.
		'email_notify_send_finish_banner'         => 'Your backup is complete for: ',
		'email_notify_send_finish_subject'        => 'Solid Backups File Send Finished - {home_url}',
		'email_notify_send_finish_body'           => "A destination file send of file {backup_file} has finished with Solid Backups v{backupbuddy_version} on {current_datetime} for the site {home_url}.\n\nDetails:\r\n\r\n{message}",
		'email_notify_error'                      => '',  // Email address(es) to send to when an error is encountered.
		'email_notify_error_subject'              => 'Solid Backups Server Error - {home_url}',
		'email_notify_error_body'                 => "Solid Backups v{backupbuddy_version} encountered a server error on {current_datetime} for the site {home_url}. Error details:\r\n\r\n{message}",
		'email_notify_error_banner'               => "An error occurred for site: ",
		'email_return'                            => '',  // Return email address for emails sent. Defaults to admin email if none specified.
		'built_email_logo'                        => plugin_dir_url( __FILE__ ) . 'assets/dist/images/solid_backups_email_logo.png',

		'remote_destinations'                     => array(),             // Array of remote destinations (S3, Rackspace, email, ftp, etc).
		'remote_send_timeout_retries'             => '1',                 // Number of times to attempt to resend timed out remote destination. IMPORTANT: Currently only permits values or 1 or 0. 1 max tries.
		'role_access'                             => 'activate_plugins',  // Default role access to the plugin.
		'dropboxtemptoken'                        => '',                  // Temporary Dropbox token for oauth.
		'multisite_export'                        => '0',                 // Allow individual sites to be exported by admins of said subsite? (Network Admins can always export individual sites).
		'backup_directory'                        => '',                  // Custom backup directory to store all archives in. BLANK for default.
		'temp_directory'                          => '',                  // Custom temporary directory to use for writing into. BLANK for default.
		'log_directory'                           => '',                  // Custom log directory. Also holds fileoptions. BLANK for default.
		'log_serial'                              => '',                  // Current log serial to send all output to. Used during backups.
		'notifications'                           => array(),             // TODO: currently not used.
		'zip_method_strategy'                     => '1',                 // 0 = Not Set, 1 = Best Available, 2 = All Available, 3 = Force Compatibility.
		'database_method_strategy'                => 'php',               // php, mysqldump, all.
		'alternative_zip_2'                       => '0',                 // Alternative zip system (Jeremy).
		'ignore_zip_warnings'                     => '1',                 // Ignore non-fatal zip warnings during the zip process (ie symlink, cant read file, etc).
		'ignore_zip_symlinks'                     => '1',                 // When enabled (1) zip will not-follow (zip utility) or ignore (pclzip) any symbolic links.
		'zip_build_strategy'                      => '3',                 // 0 = Not Set, 1 = Reserved, 2 = Single-Burst/Single-Step, 3 = Multi-Burst/Single-Step (Default), 4 = Multi-Burst/Multi-Step.
		'zip_step_period'                         => '30',                // Zip build threshold period, at expiry will start new step. Empty for default of 30s. 0 for infinite.
		'zip_burst_gap'                           => '2',                 // Zip build interburst gap. Empty for default of 2s.
		'zip_min_burst_content'                   => '10',                // Zip build minimum burst content size (MB). Empty for 10MB default. 0 for unlimited.
		'zip_max_burst_content'                   => '100',               // Zip build maximum burst content size (MB). Empty for 100MB default. 0 for unlimited.
		'disable_zipmethod_caching'               => '0',                 // When enabled the available zip methods are not cached. Useful for always showing the test for debugging or customer logging purposes for support.
		'archive_name_format'                     => 'datetime',          // Valid options: date, datetime.
		'archive_name_profile'                    => '0',                 // Valid options: 0, 1. Displays profile name in backup archive filename.
		'disable_https_local_ssl_verify'          => '0',                 // When enabled (1) disabled WordPress from verifying SSL certificates for loopbacks, etc.
		'save_comment_meta'                       => '1',                 // When enabled (1) meta data will not be stored in backups during creation.
		'ignore_command_length_check'             => '0',                 // When enabled, the command line length result provided by the OS will be ignored. Sometimes we cannot reliably get it.
		'default_backup_tab'                      => '0',                 // Default tab to have open on backup page. Useful for advanced used to change.
		'deployment_allowed'                      => '0',                 // Whether or not this site accepts pushing/pulling of site data via Stash. 0 = disabled, 1 = enabled.
		'hide_live'                               => '0',                 // Hide Stash Live from menu when set to 1.
		'hide_dashboard_widget'                   => '0',                 // Hide dashboard widget from even being an option in admin when set to 1.
		'deploy_sslverify'                        => '1',                 // Whether or not to verify ssl cert for outgoing remote connections.
		'remote_api'                              => array(
			'keys' => array(),  // API key for allowing other BB installations to manage this BB, or use deployments.
			'ips'  => array(),  // Array of IP addresses allowed to access the remote API. If empty, any ip can connect when enabled.
		),
		'skip_spawn_cron_call'                    => '0',             // If enabled then we will not call spawn_cron() during backups and attempt to chain runs.
		'stats'                                   => array(
			'site_size'          => 0,
			'site_size_excluded' => 0,
			'site_size_updated'  => 0,
			'db_size'            => 0,
			'db_size_excluded'   => 0,
			'db_size_updated'    => 0,
		),
		'disalerts'                               => array(),        // Array of alerts that have been dismissed/hidden.
		'breakout_tables'                         => '1',            // Whether or not to breakout some tables into individual steps (for sites with larger dbs). DEFAULT: enabled as of v5.0.
		'include_importbuddy'                     => '1',            // Whether or not to include importbuddy.php script inside backup ZIP file.
		'max_site_log_size'                       => '3',            // Size in MB to clear the log file if it is exceeded.
		'compression'                             => '1',            // Zip compression.
		'no_new_backups_error_days'               => '10',           // Send an error email notification if no new backups have been created in X number of days.
		'skip_quicksetup'                         => '0',            // When 1 the quick setup will not pop up on Getting Started page.
		'prevent_flush'                           => '0',            // When 1 pb_backupbuddy::flush() will return instead of flushing to workaround some odd server issues on some servers.
		'rollback_cleanups'                       => array(),        // Array of rollback serial => time() pairs to run cleanups on, such as dropping temporary undo tables. Run X hours after the timestamp.
		'phpmysqldump_maxrows'                    => '',             // When in mysqldump compatibility mode, maximum number of rows to dump per select. Blank uses default.
		'disable_localization'                    => '0',            // 0=localization enabled, 1=disabled. Useful when troubleshooting and unable to read localized log.
		'max_execution_time'                      => '',             // Maximum amount of time allowed per PHP process when chunking is enabled.
		'backup_cron_rescheduling'                => '0',            // When enabled BB will attempt to reschedule missing cronjobs for proceeding during a manual backup. Possibly useful if the cron for the next step is going missing.
		'backup_cron_passed_force_time'           => '',             // If numeric and non-zero, if during a backup the time passed since a cron should have run surpasses this number then BB will make an ajax call to force the cron to run. Or at least attempt to by clearing the cron transient and calling spawn_cron() with a future time.
		'force_single_db_file'                    => '0',            // When '1' all SQL dumps will go into db_1.sql rather than potentially being broken up into indidivudal table SQL files dependant on methods available, etc.
		'deployments'                             => array(),
		'max_send_stats_days'                     => '7',            // Max days to hold onto recent send fileoptions stats files to keep. Default 604800 = 1 week. Configurable in settings.
		'max_send_stats_count'                    => '6',            // Maxn numeric amount of most recent send fileoptions stats files to keep. Configurable in settings.
		'max_notifications_age_days'              => '21',           // Max days to keep notifications.
		'save_backup_sum_log'                     => '1',            // 1 or 0.  When 1 the full backup status log will be saved in a log file with _sum_ in it. This allows viewing the full status log regardless of Log Level setting.
		'limit_single_cron_per_pass'              => '0',            // Prevents multiple Solid Backups crons from running per PHP page load by re-scheduling and delaying them to the next load.
		'tested_php_runtime'                      => ini_get( 'max_execution_time' ), // Actual tested max PHP runtime based on backupbuddy_core::php_runtime_test() results. Default set to aid in plugin activation.
		'tested_php_memory'                       => solid_backups_get_initial_tested_memory(), // Actual tested max PHP memory based on backupbuddy_core::php_memory_test() results. Default set to aid in plugin activation.
		'last_tested_php_runtime'                 => time(),         // Timestamp PHP runtime was last tested. Default set to aid in plugin activation.
		'last_tested_php_memory'                  => time(),         // Timestamp PHP memory was last tested. Default set to aid in plugin activation.
		'use_internal_cron'                       => '0',            // Deprecated in v9.1.5. Remove in 9.2.0.
		'umask_check'                             => false,          // Stores umask server tests.
		'default_restores_permissions'            => 'standard',     // Permission set to be used during backup restores.
		'disable_dat_file_creation'               => 0,              // Allows support/users to turn of dat file creation for debugging.

		'php_runtime_test_minimum_interval'       => '604800',     // How often to perform the automated test via the housekeeping function. This must elapse before automated test will run. Zero (0) to disable.
		'php_memory_test_minimum_interval'        => '604800',     // How often to perform the automated test via the housekeeping function. This must elapse before automated test will run. Zero (0) to disable.
		'cron_request_timeout_override'           => '',           // Overrides cron loopback timeout time. 0 for no override. Useful if server too slow to respond in WP's default 0.01sec to get cron working.

		'profiles'                                => array( // TODO: Add comments to show what each of these indexes mean.
			2  => array(
				'type'  => 'full',
				'title' => 'Complete Backup',
			),
			1  => array(
				'type'  => 'db',
				'title' => 'Database Only',
				'tip'   => 'Just your database. I like your minimalist style.',
			),
			-3  => array( // @codingStandardsIgnoreLine: ok.
				'type'  => 'themes',
				'title' => 'Themes Only',
				'tip'   => 'Just your themes.',
			),
			-2 => array(
				'type'  => 'plugins',
				'title' => 'Plugins Only',
				'tip'   => 'Just your plugins.',
			),
			-1 => array(
				'type'  => 'media',
				'title' => 'Media Only',
				'tip'   => 'WordPress Media.',
			),
			0  => array(
				'type'                          => 'defaults',
				'title'                         => 'Global Defaults',
				'skip_database_dump'            => '0',                   // When enabled the database dump step will be skipped.
				'backup_nonwp_tables'           => '0',                   // Backup tables not prefixed with the WP prefix.
				'integrity_check'               => '1',                   // Zip file integrity check on the backup listing.
				'mysqldump_additional_includes' => '',
				'mysqldump_additional_excludes' => '',
				'excludes'                      => '',
				'custom_root'                   => '',                    // Overrides default custom root of ABSPATH with a hard-coded path. Currently only available with FILES backup type.
				'backup_mode'                   => '2',                   // 1 = 1.x, 2 = 2.x mode
				'exclude_media'                 => '0',
				'exclude_themes'                => '0',
				'exclude_plugins'               => '0',
				'active_plugins_only'           => '0',                   // Only backup active plugins.
			),
		),
		'show_all_cron_schedules'                 => 0,             // Show cron schedules defined by all plugins/theme or only private schedules defiend by Solid Backups (prefix itbub-) (default).
	),
	'deployment_defaults'        => array(
		'siteurl' => '',
		'api_key' => '',
	),
	'profile_defaults'           => array(
		'type'                          => '',             // defaults, db, or full.
		'title'                         => '',             // Friendly title/name.
		'skip_database_dump'            => '-1',           // When enabled the database dump step will be skipped.
		'mysqldump_additional_includes' => '-1',           // Additional db tables to backup in addition to those calculated by mysql_dumpmode. Newslines between tables.
		'mysqldump_additional_excludes' => '-1',           // Additional db tables to EXCLUDE. This is taken into account last, after tables are calculated by mysql_dumpmode AND additional includes calculated.
		'backup_nonwp_tables'           => '-1',           // Backup tables not prefixed with the WP prefix.
		// 'compression'                => '-1',           // Zip compression.
		'excludes'                      => '-1',           // Newline deliminated list of directories to exclude from the backup.
		'active_plugins_only'           => '-1',           // Skips inactive plugin folders when enabled ('1').
		'integrity_check'               => '-1',           // Zip file integrity check on the backup listing.
		'profile_globaltables'          => '1',            // Whether or not custom table inclusions/exclusions enabled for this profile.
		'profile_globalexcludes'        => '1',            // Whether or not custom file excludes enabled for this profile.
		'backup_mode'                   => '-1',           // -1= use global default, 1=classic (single page load), 2=modern (crons)
		'custom_root'                   => '',             // Custom backp root path.
	),
	'migration_defaults'         => array(
		'web_address'  => '',
		'ftp_server'   => '',
		'ftp_username' => '',
		'ftp_password' => '',
		'ftp_path'     => '',
		'ftps'         => '0',
	),
	'backups_integrity_defaults' => array(  // key is serial.
		'is_ok'         => false,
		'tests'         => array(),
		'scan_time'     => 0,
		'scan_log'      => array(),
		'size'          => 0,
		'modified'      => 0,
		'detected_type' => '',
		'file'          => '',
	),
	'schedule_defaults'          => array(
		'title'               => '',
		'profile'             => '',
		'interval'            => 'monthly',
		'first_run'           => '',
		'delete_after'        => '0',
		'remote_destinations' => '',
		'last_run'            => 0,
		'on_off'              => '1',
	),
	'notification_defaults'      => array(  // Array stored in wp_options "pb_backupbuddy_notifications" rather than Settings array.
		'time'     => 0,
		'slug'     => '',
		'title'    => '',
		'message'  => '',
		'data'     => array(),
		'urgent'   => false,
		'syncSent' => false,          // Whether or not this notification has been sent to Solid Sync yet.
	),
	'wp_minimum'                 => '3.5.0',
	'php_minimum'                => '7.2',
	'modules'                    => array(
		'filesystem' => true,
		'format'     => true,
	),
);

/**
 * Get initial tested memory.
 *
 * @return int  The memory limit in MB.
 */
function solid_backups_get_initial_tested_memory() {
	$memory_limit = ini_get('memory_limit');
	// Convert the memory limit to MB, whether it is in MB or not.
	if ( preg_match( '/^(\d+)(.)$/', $memory_limit, $matches ) ) {
		if ( 'M' === $matches[2] ) {
			$memory_limit = $matches[1];
		} elseif ( 'K' === $matches[2] ) {
			$memory_limit = round( $matches[1] / 1024 );
		} elseif ( 'G' === $matches[2] ) {
			$memory_limit = $matches[1] * 1024;
		}
	}
	return $memory_limit;
}

define( 'BACKUPBUDDY_PLUGIN_FILE', __FILE__ );
define( 'BACKUPBUDDY_PLUGIN_PATH', dirname( BACKUPBUDDY_PLUGIN_FILE ) );

// Main plugin file.
$pluginbuddy_init = basename( BACKUPBUDDY_PLUGIN_FILE );

// Load composer autoload.
require_once BACKUPBUDDY_PLUGIN_PATH . '/vendor/autoload.php';
require_once BACKUPBUDDY_PLUGIN_PATH . '/vendor-prefixed/autoload.php';

// Telemetry.
require_once BACKUPBUDDY_PLUGIN_PATH . '/classes/class-container.php';
require_once BACKUPBUDDY_PLUGIN_PATH . '/classes/class-telemetry.php';
add_action( 'plugins_loaded', function() {
	Solid_Backups_Telemetry::run_hooks();
}, 9 );

// Load PHP7 helpers.
require_once BACKUPBUDDY_PLUGIN_PATH . '/helpers/php7.php';

// Load license helpers.
require_once BACKUPBUDDY_PLUGIN_PATH . '/helpers/license.php';

// Load admin helpers.
require_once BACKUPBUDDY_PLUGIN_PATH . '/helpers/admin.php';

// Load class helpers.
require_once BACKUPBUDDY_PLUGIN_PATH . '/helpers/classes.php';

// Load compatibility functions.
require_once BACKUPBUDDY_PLUGIN_PATH . '/helpers/compat.php';

// Load privacy functions.
require_once BACKUPBUDDY_PLUGIN_PATH . '/helpers/privacy.php';

// Helper functions to style Filetree icons.
require_once BACKUPBUDDY_PLUGIN_PATH . '/helpers/file-icons.php';

// Restore helpers.
require_once BACKUPBUDDY_PLUGIN_PATH . '/helpers/restore.php';

// $settings is expected to be populated prior to including PluginBuddy framework. Do not edit below.
require_once BACKUPBUDDY_PLUGIN_PATH . '/pluginbuddy/_pluginbuddy.php';

/**
 * Cron looback test.
 */
function itbub_cron_test() {
	global $wpdb;
	$option           = 'itbub_doing_cron_test';
	$time             = microtime( true );
	$serialized_value = maybe_serialize( $time );
	$autoload         = 'no';
	$result           = $wpdb->query( $wpdb->prepare( "INSERT INTO `$wpdb->options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE `option_name` = VALUES(`option_name`), `option_value` = VALUES(`option_value`), `autoload` = VALUES(`autoload`)", $option, $serialized_value, $autoload ) );
}

add_action( 'itbub_cron_test', 'itbub_cron_test' );

/**
 * Updater & Licensing System - Aug 23, 2013.
 *
 * @param object $updater  Updater Class instance.
 */
function ithemes_backupbuddy_updater_register( $updater ) {
	$updater->register( 'backupbuddy', BACKUPBUDDY_PLUGIN_FILE );
}
add_action( 'ithemes_updater_register', 'ithemes_backupbuddy_updater_register' );
$updater = BACKUPBUDDY_PLUGIN_PATH . '/lib/updater/load.php';
if ( file_exists( $updater ) ) {
	require $updater;
}
