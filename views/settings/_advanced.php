<?php
/**
 * Advanced Settings Screen
 *
 * @package BackupBuddy
 */

if ( ! is_admin() ) {
	die( 'Access Denied.' );
}
?>

<script>
	function bb_checkZipSystem() {
		if ( jQuery( 'input#pb_backupbuddy_alternative_zip_2' ).is( ':checked' ) ) {
			jQuery( '.bb-alternate-zip-options' ).show();
		} else {
			jQuery( '.bb-alternate-zip-options' ).hide();
		}
	}
	jQuery(function() {

		jQuery( 'input#pb_backupbuddy_alternative_zip_2' ).change( function(){
			bb_checkZipSystem();
		});

		jQuery( 'input#pb_backupbuddy_profiles__0__integrity_check' ).change( function(){
			if ( ! jQuery(this).is( ':checked' ) ) {
				alert( "<?php esc_html_e( 'WARNING: Use with caution as this could result in BAD or incomplete backups going undetected, resulting in future data loss if you rely on these potentially bad backups. Without the integrity scan your backups cannot be guaranteed as valid and complete.', 'it-l10n-backupbuddy' ); ?>" );
			}
		});

		bb_checkZipSystem(); // Run first time.

	});
</script>

<style>
	.bb-alternate-zip-options {
		display: none;
	}
</style>

<?php
$settings_form = new pb_backupbuddy_settings( 'advanced_settings', '', 'tab=advanced', 302 );

$settings_form->add_setting(
	array(
		'type'  => 'title',
		'name'  => 'title_basic',
		'title' => __( 'Basic Operation', 'it-l10n-backupbuddy' ),
	)
);

$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'backup_reminders',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Enable backup reminders', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: enabled] - When enabled links will be displayed upon post or page edits and during WordPress upgrades to remind and allow rapid backing up after modifications or before upgrading.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '',
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'select',
		'name'    => 'archive_name_format',
		'options' => array(
			'datetime'   => 'Date + time (12hr format) [default]',
			'datetime24' => 'Date + time (24hr format)',
			'timestamp'  => 'Unix Timestamp',
			'date'       => 'Date only (Not recommended)',
		),
		'title'   => __( 'Backup file name date/time', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: Date + time (12hr format)] - Your backup filename will display the date and/or time (or timestamp) the backup was created. If you make multiple backups in a one day, it is HIGHLY recommended not to use the Date Only setting.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'archive_name_profile',
		'options' => array(
			'unchecked' => 0,
			'checked'   => 1,
		),
		'title'   => __( 'Add the backup profile<br>to backup file name', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: disabled] - When enabled your backup filename will display the backup profile used to initiate the backup. This is useful when making multiple backups from different profiles.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'lock_archives_directory',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Lock archive directory (high security)', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: disabled] - When enabled all downloads of archives via the web will be prevented under all circumstances via .htaccess file. If your server permits it, they will only be unlocked temporarily on click to download. If your server does not support this unlocking then you will have to access the archives via the server (such as by FTP).', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '<span class="description"> ' . __( 'May prevent downloading backups within WordPress on incompatible servers', 'it-l10n-backupbuddy' ),
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'include_importbuddy',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Include ImportBuddy<br>in full backup archive', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: enabled] - When enabled, the importbuddy.php (restoration tool) file will be included within the backup archive ZIP file in the location `/' . str_replace( ABSPATH, '', backupbuddy_core::getTempDirectory() ) . ' xxxxxxxxxx/ importbuddy.php` where the x\'s match the unique random string in the backup ZIP filename.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => ' <p class="description">' . __( 'Located in backup', 'it-l10n-backupbuddy' ) . ':<br>&nbsp; <code>/' . str_replace( ABSPATH, '', backupbuddy_core::getTempDirectory() ) . 'xxxxxxxxxx/importbuddy.php</code></p>',
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'select',
		'name'    => 'default_backup_tab',
		'title'   => __( 'Default backup tab', 'it-l10n-backupbuddy' ),
		'options' => array(
			'0' => __( 'Overview', 'it-l10n-backupbuddy' ),
			'1' => __( 'Status Log', 'it-l10n-backupbuddy' ),
		),
		'tip'     => sprintf( __( '[Default: Overview] - The default tab open during a backup is the overview tab. A more technical view is available in the Status tab.', 'it-l10n-backupbuddy' ) ),
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'disable_localization',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Disable language localization', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: Unchecked] When checked language localization support will be disabled. Solid Backups will revert to full English language mode. Use this to display logs in English for support.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '<span class="description"> ' . __( 'Check to run Solid Backups in English. This is useful for support.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'limit_single_cron_per_pass',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Limit to one action per cron pass', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: Checked] When checked only one Solid Backups cron action may run per PHP page load. Subsequent actions will be rescheduled for the next page load. This only impacts Solid Backups cron actions.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'cron_request_timeout_override',
		'title' => __( 'Cron loopback timeout override', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Default: *blank* (0.01sec)] - If set this will override WordPress\' cron loopback timeout of 0.01 seconds to a higher value. Some servers are unable to respond in such a short time resulting in cron failure. The lowest working value is recommended to prevent potential page-load slow-downs.', 'it-l10n-backupbuddy' ),
		'css'   => 'width: 50px;',
		'after' => ' sec. <span class="description"> ' . __( 'Blank for WordPress default:', 'it-l10n-backupbuddy' ) . ' 0.01 sec</span>',
		'rules' => 'number',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'remote_send_timeout_retries',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Retry timed out remote sends', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: Checked] When checked Solid Backups will attempt ONCE at resending a timed out remote destination send.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '<span class="description"> ' . __( 'Check to re-attempt timed out sends once.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'   => 'required',
	)
);
if ( true !== apply_filters( 'itbub_hide_stash_live', false ) ) {
	$settings_form->add_setting(
		array(
			'type'    => 'checkbox',
			'name'    => 'hide_live',
			'options' => array(
				'unchecked' => '0',
				'checked'   => '1',
			),
			'title'   => __( 'Hide "Stash Live" from menu', 'it-l10n-backupbuddy' ),
			'tip'     => __( '[Default: Unchecked] When checked the `Stash Live` item will be removed from the left menu. Useful for developers with clients not using this feature.', 'it-l10n-backupbuddy' ),
			'css'     => '',
			'after'   => '<span class="description"> ' . __( 'Check to hide.', 'it-l10n-backupbuddy' ) . '</span>',
			'rules'   => 'required',
		)
	);
}
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'hide_dashboard_widget',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Hide widget & option from dashboard', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: Unchecked] When checked the Solid Backups widget option will be completely removed from the dashboard for all users.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '<span class="description"> ' . __( 'Check to hide.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'set_greedy_execution_time',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Attempt to override<br>PHP max execution time', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: Unchecked] When checked Solid Backups will attempt to override the default PHP maximum execution time to 7200 seconds.  Note that almost all shared hosting providers block this attempt.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '<span class="description"> ' . __( 'Check to force execution time override attempt (most hosts block this).', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'archive_limit_size_big',
		'title' => __( 'Maximum local storage usage', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Example: 50000] - Maximum size (in MB) to allow Solid Backups to use. This is a safeguard limit which should be set HIGHER than any other local archive size limits.', 'it-l10n-backupbuddy' ),
		'rules' => 'required|int|int',
		'css'   => 'width: 75px;',
		'after' => ' MB. <span class="description">0 for no limit.</span>',
	)
);
$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'max_execution_time',
		'title' => '<b>' . __( 'Maximum time per chunk', 'it-l10n-backupbuddy' ) . '</b>',
		'tip'   => __( '[Default: *blank*] - The maximum amount of time Solid Backups should allow chunked proccesses to run, including database backups, Solid Backups Stash Live, and any other chunked proccesses UNLESS that feature allows its own specific max execution time setting in its settings.', 'it-l10n-backupbuddy' ),
		'css'   => 'width: 50px;',
		'after' => ' sec. <span class="description"> ' . __( 'Blank for detected default:', 'it-l10n-backupbuddy' ) . ' ' . backupbuddy_core::detectMaxExecutionTime() . ' sec</span>',
		'rules' => 'int',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'show_all_cron_schedules',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Show all defined cron schedules', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: Unchecked] When checked Solid Backups will show for selection cron schedules defined by all plugins/theme/core when adding or editing a Backup Schedule. When unchecked Solid Backups will only show cron schedules defined by Solid Backups unless prevailing conditions dictate otherwise.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '<span class="description"> ' . __( 'Check to show all defined cron schedules on Schedules page when adding or editing a Backup Schedule.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'   => 'required',
	)
);

$settings_form->add_setting(
	array(
		'type'  => 'title',
		'name'  => 'title_logging',
		'title' => __( 'Logging', 'it-l10n-backupbuddy' ),
	)
);

$log_file = backupbuddy_core::getLogDirectory() . 'log-' . pb_backupbuddy::$options['log_serial'] . '.txt';
$settings_form->add_setting(
	array(
		'type'    => 'select',
		'name'    => 'log_level',
		'title'   => '<b>' . __( 'Logging Level', 'it-l10n-backupbuddy' ) . '</b>',
		'options' => array(
			'0' => __( 'None', 'it-l10n-backupbuddy' ),
			'1' => __( 'Errors Only (default)', 'it-l10n-backupbuddy' ),
			'2' => __( 'Errors & Warnings', 'it-l10n-backupbuddy' ),
			'3' => __( 'Everything (troubleshooting mode)', 'it-l10n-backupbuddy' ),
		),
		'tip'     => sprintf( __( '[Default: Errors Only] - This option controls how much activity is logged for records or troubleshooting. Logs may be viewed from the Logs/Other tab on the Diagnostics page. Additionally when in Everything / Troubleshooting mode error emails will contain encrypted troubleshooting data for support. Log file: %s', 'it-l10n-backupbuddy' ), $log_file ),
		'rules'   => 'required',
	)
);

$settings_form->add_setting(
	array(
		'type'    => 'select',
		'name'    => 'edits_tracking_mode',
		'title'   => __( 'Recent Edits Tracking Mode', 'it-l10n-backupbuddy' ),
		'options' => array(
			'basic'    => __( 'Basic', 'it-l10n-backupbuddy' ),
			'advanced' => __( 'Advanced', 'it-l10n-backupbuddy' ),
		),
		'tip'     => __( '[Default: Basic] - Adjusts levels of recent edits tracking. Basic tracks posts, pages, media, and plugin changes. Advanced adds tracking for settings/options along with a more detailed dashboard widget.', 'it-l10n-backupbuddy' ),
	)
);

$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'save_backup_sum_log',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Temporarily save full backup status logs', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: Checked] When checked Solid Backups will temporarily (~10 days) save the complete full backup status log, regardless of the Logging Level setting.  This is useful for troubleshooting passed backups. View logs by hovering a backup on the Backups page and clicking "View Log".', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '<span class="description"> ' . __( 'Temporarily save full backup status logs.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'max_site_log_size',
		'title' => __( 'Maximum main log file size', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Default: 3 MB] - If the log file exceeds this size then it will be cleared to prevent it from using too much space.' ),
		'rules' => 'required|int',
		'css'   => 'width: 50px;',
		'after' => ' MB',
	)
);
$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'max_send_stats_days',
		'title' => __( 'Recent remote send stats max age', 'it-l10n-backupbuddy' ),
		'tip'   => sprintf( __( '[Default: Errors Only] - This option controls how much activity is logged for records or troubleshooting. Logs may be viewed from the Logs/Other tab on the Diagnostics page. Additionally when in Everything / Troubleshooting mode error emails will contain encrypted troubleshooting data for support. Log file: %s', 'it-l10n-backupbuddy' ), $log_file ),
		'tip'   => __( '[Default: 7 days] - Number of days to store recently sent file statistics & logs. Valid options are 1 to 90 days.' ),
		'css'   => 'width: 50px;',
		'rules' => 'required|int[1-90]',
		'after' => ' days',
	)
);
$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'max_send_stats_count',
		'title' => __( 'Recent remote send stats max number', 'it-l10n-backupbuddy' ),
		'tip'   => sprintf( __( '[Default: Errors Only] - This option controls how much activity is logged for records or troubleshooting. Logs may be viewed from the Logs/Other tab on the Diagnostics page. Additionally when in Everything / Troubleshooting mode error emails will contain encrypted troubleshooting data for support. Log file: %s', 'it-l10n-backupbuddy' ), $log_file ),
		'tip'   => __( '[Default: 6 sends] - Maximum number of recently sent file statistics & logs to store. Valid options are 1 to 25 sends.' ),
		'css'   => 'width: 50px;',
		'rules' => 'required|int[1-25]',
		'after' => ' sends',
	)
);
$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'max_notifications_age_days',
		'title' => __( 'Maximum days to keep recent activity', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Default: 21 days] - Number of days to store recent activity notifications / audits.' ),
		'rules' => 'required|int',
		'css'   => 'width: 50px;',
		'after' => ' days',
	)
);

$settings_form->add_setting(
	array(
		'type'  => 'title',
		'name'  => 'title_advanced',
		'title' => __( 'Technical & Server Compatibility', 'it-l10n-backupbuddy' ),
	)
);

$settings_form->add_setting(
	array(
		'type'    => 'select',
		'name'    => 'profiles#0#backup_mode',
		'title'   => '<b>' . __( 'Default global backup mode', 'it-l10n-backupbuddy' ) . '</b>',
		'options' => array(
			'1' => __( 'Classic (v1.x) - Entire backup in single PHP page load', 'it-l10n-backupbuddy' ),
			'2' => __( 'Modern (v2.x+) - Split across page loads via WP cron', 'it-l10n-backupbuddy' ),
		),
		'tip'     => __( '[Default: Modern] - If you are encountering difficulty backing up due to WordPress cron, HTTP Loopbacks, or other features specific to version 2.x you can try classic mode which runs like Solid Backups v1.x did.', 'it-l10n-backupbuddy' ),
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'delete_archives_pre_backup',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Delete all backup archives<br>prior to backups', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: disabled] - When enabled all local backup archives will be deleted prior to each backup. This is useful if in compatibilty mode to prevent backing up existing files.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '<span class="description"> ' . __( 'Use if exclusions are malfunctioning or for special purposes.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'   => 'required',
	)
);
if ( version_compare( $GLOBALS['wp_version'], '4.6', '<' ) ) {
	$settings_form->add_setting(
		array(
			'type'    => 'checkbox',
			'name'    => 'disable_https_local_ssl_verify',
			'options' => array(
				'unchecked' => '0',
				'checked'   => '1',
			),
			'title'   => __( 'Disable local SSL certificate verification', 'it-l10n-backupbuddy' ),
			'tip'     => __( '[Default: Disabled] When checked, WordPress will skip local https SSL verification.', 'it-l10n-backupbuddy' ) . '</span>',
			'css'     => '',
			'after'   => '<span class="description"> ' . __( 'Workaround if local SSL verification fails (ie. for loopback & local CA cert issues).', 'it-l10n-backupbuddy' ) . '</span>',
			'rules'   => 'required',
		)
	);
}
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'prevent_flush',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Prevent Flushing', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: not prevented (unchecked)] - Rarely some servers die unexpectedly when flush() or ob_flush() are called multiple times during the same PHP process. Checking this prevents these from ever being called during backups.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '<span class="description"> ' . __( 'Check if directed by support.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'save_comment_meta',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Save meta data in comment', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: Enabled] When enabled, Solid Backups will store general backup information in the ZIP comment header such as Site URL, backup type & time, serial, etc. during backup creation.', 'it-l10n-backupbuddy' ) . '</span>',
		'css'     => '',
		'after'   => '<span class="description"> ' . __( 'If backups hang when saving meta data disabling skips this process.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'   => 'required',
	)
);

$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'profiles#0#integrity_check',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Perform integrity check on backup files', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: enabled] - WARNING: USE WITH CAUTION! By default each backup file is checked for integrity and completion the first time it is viewed on the Backup page.  On some server configurations this may cause memory problems as the integrity checking process is intensive.  This may also be useful if the backup page will not load.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '<span class="description"> ' . __( 'Uncheck if directed by support.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'   => 'required',
	)
);

$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'disable_dat_file_creation',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Disable .dat file creation', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: unchecked] - Dat files enable additional restore features and are used to quickly get information about local and remote backup files.', 'it-l10n-backupbuddy' ),
		'after'   => '<span class="description"> ' . __( 'Check if directed by support.', 'it-l10n-backupbuddy' ) . '</span>',
	)
);

$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'backup_cron_rescheduling',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Reschedule missing crons<br>in manual backups', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: disabled] - To proceed to subsequent steps during backups Solid Backups schedules the next step with the WordPress cron system.  If this cron goes missing the backup cannot proceed. This feature instructs Solid Backups to attempt to re-schedule this cron as it occurs.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '<span class="description"> ' . __( 'Check if directed by support.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'skip_spawn_cron_call',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Skip chained spawn of cron', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: disabled] - When skipping is enabled Solid Backups will not call spawn_cron() in an attempt to force a single chaining of the cron process when called from a page initiated by a web user/client. Note that Solid Backups will only chain when called by a user-accessed page, not within cron runs themselves. Chains are halted if DOING_CRON is defined to prevent potential infinite chaining loops.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '<span class="description"> ' . __( 'Check to skip chaining from web-based page loads (as applicable).', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'deploy_sslverify',
		'options' => array(
			'unchecked' => 0,
			'checked'   => 1,
		),
		'title'   => __( 'Deployment: Verify SSL Cert', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: enabled] - When disabled Solid Backups will not verify the SSL certificate authenticity of the remote end (outgoing connections). The connection will still be encrypted.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'backup_cron_passed_force_time',
		'title' => __( 'Force cron if behind by X seconds', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Default: blank] - When in the default modern mode Solid Backups schedules each backup step with the WordPress simulated cron. If cron steps are not running when they should and the Status Log reports steps should have run many seconds ago, this may help to force Solid Backups to demand WordPress run the cron step now. Manual backups only; not scheduled.', 'it-l10n-backupbuddy' ),
		'css'   => 'width: 80px;',
		'after' => ' secs. <p class="description"> ' . __( 'Leave blank for default of no forcing.', 'it-l10n-backupbuddy' ) . '</p>',
		'rules' => '',
	)
);
$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'php_runtime_test_minimum_interval',
		'title' => __( 'PHP runtime test interval', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Default: 604800 (weekly)] - By default Solid Backups will regularly perform an actual test of your PHP maximum execution time limit. Many hosts misreport this value so it is tested via a real test to confirm. The lesser of reported or tested values is used for most Solid Backups operations. Set to 0 (zero) to disable this test.', 'it-l10n-backupbuddy' ),
		'css'   => 'width: 80px;',
		'after' => ' secs. <p class="description"> ' . __( 'Set to zero (0) to disable.', 'it-l10n-backupbuddy' ) . '</p>',
		'rules' => '',
	)
);
$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'php_memory_test_minimum_interval',
		'title' => __( 'PHP memory test interval', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Default: 604800 (weekly)] - By default Solid Backups will regularly perform an actual test of your PHP maximum memory limit. Many hosts misreport this value or have other global limits in place so it is tested via a real test to confirm. Set to 0 (zero) to disable this test.', 'it-l10n-backupbuddy' ),
		'css'   => 'width: 80px;',
		'after' => ' secs. <p class="description"> ' . __( 'Set to zero (0) to disable.', 'it-l10n-backupbuddy' ) . '</p>',
		'rules' => '',
	)
);

ob_start();
pb_backupbuddy::load_view( 'settings/permission-modes' );
$permission_modes = ob_get_clean();
$settings_form->add_setting(
	array(
		'type'    => 'select',
		'name'    => 'default_restores_permissions',
		'title'   => __( 'Default Restore Permissions', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: standard] - When using the restore feature, if a file or folder is restored with incorrect or unsafe permissions, the mode of the directory or file will be automatically corrected based on a set of permissions (see below).', 'it-l10n-backupbuddy' ),
		'options' => array(
			'standard' => __( 'Standard (default) - Used by most hosts.', 'it-l10n-backupbuddy' ),
			'loose'    => __( 'Loose', 'it-l10n-backupbuddy' ),
			'strict'   => __( 'Strict', 'it-l10n-backupbuddy' ),
		),
		'after'   => $permission_modes,
	)
);

$settings_form->add_setting(
	array(
		'type'  => 'title',
		'name'  => 'title_database',
		'title' => __( 'Database', 'it-l10n-backupbuddy' ),
	)
);

$settings_form->add_setting(
	array(
		'type'    => 'select',
		'name'    => 'database_method_strategy',
		'title'   => '<b>' . __( 'Database method strategy', 'it-l10n-backupbuddy' ) . '</b>',
		'options' => array(
			'php'         => __( 'PHP-based: Supports automated chunked resuming - default', 'it-l10n-backupbuddy' ),
			'commandline' => __( 'Commandline: Fast but does not support resuming', 'it-l10n-backupbuddy' ),
			'all'         => __( 'All Available: ( PHP [chunking] > Commandline via exec()  )', 'it-l10n-backupbuddy' ),
		),
		'tip'     => __( '[Default: PHP-based] - Normally use PHP-based which supports chunking (as of Solid Backups v5) to support larger databases. Commandline-based database dumps use mysqldump which is very fast and efficient but cannot be broken up into smaller steps if it is too large which could result in timeouts on larger servers.', 'it-l10n-backupbuddy' ),
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'        => 'checkbox',
		'name'        => 'profiles#0#skip_database_dump',
		'options'     => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'       => __( 'Skip database dump on backup', 'it-l10n-backupbuddy' ),
		'tip'         => __( '[Default: disabled] - (WARNING: This prevents Solid Backups from backing up the database during any kind of backup. This is for troubleshooting / advanced usage only to work around being unable to backup the database.', 'it-l10n-backupbuddy' ),
		'css'         => '',
		'after'       => '<span class="description"> ' . __( 'Completely bypass backing up database for all database types. Use caution.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'       => 'required',
		'orientation' => 'vertical',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'breakout_tables',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Break out big table dumps into steps', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: enabled] When enabled, Solid Backups will dump some of the commonly larger tables in separate steps. Note this only applies to command-line based dumps as PHP-based dumps automatically support chunking with resume on table and/or row as needed.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '<span class="description"> ' . __( 'Commandline method: Break up dumping of big tables (chunking)', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'force_single_db_file',
		'options' => array(
			'unchecked' => '1',
			'checked'   => '0',
		),
		'title'   => __( 'Use separate files<br>per table (when possible)', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: enabled] When enabled, Solid Backups will dump individual tables to their own database file (eg wp_options.sql, wp_posts.sql, etc) when possible based on other criteria such as the dump method and whether breaking out big tables is enabled.', 'it-l10n-backupbuddy' ) . '</span>',
		'css'     => '',
		'after'   => '<span class="description"> ' . __( 'Uncheck to force dumping all tables into a single db_1.sql file.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'phpmysqldump_maxrows',
		'title' => __( 'Compatibility mode max rows per select', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Default: *blank*] - When Solid Backups is using compatibility mode mysql dumping (via PHP), Solid Backups selects data from the database. Reducing this number has Solid Backups grab smaller portions from the database at a time. Leave blank to use built in default (around 2000 rows per select).', 'it-l10n-backupbuddy' ),
		'css'   => 'width: 50px;',
		'after' => ' rows. <p class="description"> ' . __( 'Blank for default.', 'it-l10n-backupbuddy' ) . ' (~1000 rows/select)</p>',
		'rules' => 'int',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'ignore_command_length_check',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Skip max command line length check ', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: disabled] - WARNING: Solid Backups attempts to determine your system\'s maximum command line length to insure that database operation commands do not get inadvertantly cut off. On some systems it is not possible to reliably detect this information which could result in falling back into compatibility mode even though the system is capable of running in normal operational modes. This option instructs Solid Backups to skip the command line length check.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '<span class="description"> ' . __( 'Check if directed by support.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'   => 'required',
	)
);

$settings_form->add_setting(
	array(
		'type'  => 'title',
		'name'  => 'title_zip',
		'title' => __( 'Zip', 'it-l10n-backupbuddy' ),
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'compression',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => '<b>' . __( 'Enable zip compression', 'it-l10n-backupbuddy' ) . '</b>',
		'tip'     => __( '[Default: enabled] - ZIP compression decreases file sizes of stored backups. If you are encountering timeouts due to the script running too long, disabling compression may allow the process to complete faster.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '<span class="description"> ' . __( 'Unchecking typically DOUBLES the amount of data which may be zipped up before timeouts.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'select',
		'name'    => 'zip_method_strategy',
		'title'   => '<b>' . __( 'Zip method strategy', 'it-l10n-backupbuddy' ) . '</b>',
		'options' => array(
			'1' => __( 'Best Available', 'it-l10n-backupbuddy' ),
			'2' => __( 'All Available', 'it-l10n-backupbuddy' ),
			'3' => __( 'Force Compatibility', 'it-l10n-backupbuddy' ),
		),
		'tip'     => __( '[Default: Best Only] - Normally use Best Available but if the server is unreliable in this mode can try All Available or Force Compatibility', 'it-l10n-backupbuddy' ),
		'after'   => '<span class="description"> ' . __( 'Select Force Compatibility if absolutely necessary.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'alternative_zip_2',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => '<b>' . __( 'Alternative zip system (BETA)', 'it-l10n-backupbuddy' ) . '</b>',
		'tip'     => __( '[Default: Disabled] Use if directed by support.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '<span class="description"> Check if directed by support.</span>',
		'rules'   => 'required',
	)
);

$settings_form->add_setting(
	array(
		'type'      => 'select',
		'name'      => 'zip_build_strategy',
		'title'     => '<b>' . __( 'Zip build strategy', 'it-l10n-backupbuddy' ) . '</b>',
		'options'   => array(
			'2' => __( 'Single-Burst/Single-Step', 'it-l10n-backupbuddy' ),
			'3' => __( 'Multi-Burst/Single-Step', 'it-l10n-backupbuddy' ),
			'4' => __( 'Multi-Burst/Multi-Step', 'it-l10n-backupbuddy' ),
		),
		'tip'       => __( '[Default: Multi-Burst/Single-Step] - Most backups can complete the zip build with the multi-burst/single-step strategy. Single-Burst/Single-Step can give a faster build on good servers. Multi-Burst/Multi-Step is required for slow servers that timeout during the zip build.', 'it-l10n-backupbuddy' ),
		'after'     => '<span class="description"> ' . __( 'Select Multi-Burst/Multi-Step if server timing out during zip build', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'     => 'required',
		'row_class' => 'bb-alternate-zip-options',
	)
);
$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'zip_step_period',
		'title'     => '<b>' . __( 'Maximum time per chunk', 'it-l10n-backupbuddy' ) . '</b>',
		'tip'       => __( '[Default: *blank* - 30s] - The maximum amount of time Solid Backups should allow a zip archive build to run before pausing and scheduling a continuation step. Solid Backups by default will allow the zip archive build to run for an indefinite period until completion but some servers will prematurely timeout without notice and this can cause the zip archive build to stall. This option allows Solid Backups to pause after the specified period and schedule a continuation step. If your zip archive build is timing out then setting a value here that is comfortably within your server timeout constraints will help your backup progress.', 'it-l10n-backupbuddy' ),
		'css'       => 'width: 50px;',
		'after'     => ' sec. <span class="description"> ' . __( 'Blank for default (30s), 0 for infinite', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'     => 'int',
		'row_class' => 'bb-alternate-zip-options',
	)
);
$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'zip_burst_gap',
		'title'     => '<b>' . __( 'Gap between zip build bursts', 'it-l10n-backupbuddy' ) . '</b>',
		'tip'       => __( '[Default: *blank* - 2s] - The time gap Solid Backups will apply between each zip archive build burst. Some servers/hosting may benefit from having a small period of time between bursts to allow the server to catch up with file based operations and/or allowing the average load over time to be reduced by spreading out cpu and disk usage. Warning - if the value is set too high some servers may prematurely timeout without notice.', 'it-l10n-backupbuddy' ),
		'css'       => 'width: 50px;',
		'after'     => ' sec. <span class="description"> ' . __( 'Blank for default (2s)', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'     => 'int',
		'row_class' => 'bb-alternate-zip-options',
	)
);
$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'zip_min_burst_content',
		'title'     => '<b>' . __( 'Minimum content size for a single burst (MB)', 'it-l10n-backupbuddy' ) . '</b>',
		'tip'       => __( '[Default: 10] - The minimum content size that Solid Backups will try for in a zip build burst. If a zip build requires multiple bursts then the actual content size for continuation burst is adaptively varied up to the limit imposd by the maximum burst content size setting.', 'it-l10n-backupbuddy' ),
		'css'       => 'width: 50px;',
		'after'     => ' MB <span class="description"> ' . __( 'Blank for default (10MB), 0 for no minimum', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'     => 'int',
		'row_class' => 'bb-alternate-zip-options',
	)
);
$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'zip_max_burst_content',
		'title'     => '<b>' . __( 'Maximum content size for a single burst (MB)', 'it-l10n-backupbuddy' ) . '</b>',
		'tip'       => __( '[Default: 100] - The maximum content size that Solid Backups will try for in a zip build burst. If a zip build requires multiple bursts then the actual content size for continuation burst is adaptively varied up to the limit imposd by the maximum burst content size setting.', 'it-l10n-backupbuddy' ),
		'css'       => 'width: 50px;',
		'after'     => ' MB <span class="description"> ' . __( 'Blank for default (100MB), 0 for no maximum', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'     => 'int',
		'row_class' => 'bb-alternate-zip-options',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'disable_zipmethod_caching',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Disable zip method caching', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: Disabled] Use if directed by support. Bypasses caching available zip methods so they are always displayed in logs. When unchecked Solid Backups will cache command line zip testing for a few minutes so it does not run too often. This means that your backup status log may not always show the test results unless you disable caching.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '<span class="description"> Check if directed by support to always log zip detection.</span>',
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'ignore_zip_warnings',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Ignore zip archive warnings', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: Enabled] When enabled Solid Backups will ignore non-fatal warnings encountered during the backup process such as inability to read or access a file, symlink problems, etc. These non-fatal warnings will still be logged.', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '<span class="description"> Check to ignore non-fatal warnings when zipping files.</span>',
		'rules'   => 'required',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'ignore_zip_symlinks',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'   => __( 'Ignore/do-not-follow symbolic links', 'it-l10n-backupbuddy' ),
		'tip'     => __( '[Default: Enabled] When enabled Solid Backups will ignore/not-follow symbolic links encountered during the backup process', 'it-l10n-backupbuddy' ),
		'css'     => '',
		'after'   => '<span class="description"> Symbolic links are followed by default. Unfollowable links may cause failures.</span>',
		'rules'   => 'required',
	)
);

$settings_form->add_setting(
	array(
		'type'  => 'title',
		'name'  => 'title_other',
		'title' => __( 'Other', 'it-l10n-backupbuddy' ),
	)
);

$settings_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'telemetry_opt_in_status',
		'options' => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		// Get the value directly from Telemetry.
		'default' => Solid_Backups_Telemetry::get_opt_in_status_value(),
		'title'   => __( 'StellarWP Telemetry', 'it-l10n-backupbuddy' ),
		'tip'     => __( 'Telemetry helps us make Solid Backups better!', 'it-l10n-backupbuddy' ),
		'after'   => '<span class="description"> Check to opt-in to <a href="https://go.solidwp.com/solid-backups-opt-in-usage-sharing" target="_blank">Telemetry</a>.</span>',
		'rules'   => 'required',
	)
);

$settings_form->process(); // Handles processing the submitted form (if applicable).
$settings_form->display_settings( __( 'Save Advanced Settings', 'it-l10n-backupbuddy' ), '<div class="solid-backups-form-buttons">', '</div>', 'button-no-ml' );

