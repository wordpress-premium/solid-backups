<?php
/**
 * Solid Backups Stash Live Configuration. Shown for Settings screen.
 *
 * Pre-populated variables coming into this script:
 *   $destination_id
 *   $destination_settings
 *   $mode
 *
 * @author Dustin Bolton
 * @since 7.0
 */

require_once pb_backupbuddy::plugin_path() . '/classes/class-backupbuddy-schedules.php';

$archive_types = array(
	'db'      => __( 'Database Backups', 'it-l10n-backupbuddy' ),
	'full'    => __( 'Full Backups', 'it-l10n-backupbuddy' ),
	'plugins' => __( 'Plugins Backups', 'it-l10n-backupbuddy' ),
	'themes'  => __( 'Themes Backups', 'it-l10n-backupbuddy' ),
);

$archive_periods = array(
	'daily',
	'weekly',
	'monthly',
	'yearly',
);

// Handle saving archive limits.
if ( 'settings' == pb_backupbuddy::_POST( 'pb_backupbuddy_' ) ) {

	$save = true;
	foreach( $archive_types as $archive_type => $archive_type_name ) {
		foreach( $archive_periods as $archive_period ) {
			if ( '' == pb_backupbuddy::_POST( 'pb_backupbuddy_limit_' . $archive_type . '_' . $archive_period ) ) { // No limit.
				$archive_value = '';
			} else { // Numerical limit (if not numerical, error).
				$archive_value = (int)pb_backupbuddy::_POST( 'pb_backupbuddy_limit_' . $archive_type . '_' . $archive_period );
				if ( ! is_numeric( $archive_value ) ) {
					pb_backupbuddy::alert( 'Invalid non-numeric value for archive limit `' . htmlentities( $archive_value ) . '` for type `' . $archive_type_name . '`.' );
					$save = false;
					break 2;
				}
			}
			pb_backupbuddy::$options['remote_destinations'][$destination_id]['limit_' . $archive_type . '_' . $archive_period ] = $archive_value;
		}
	}

	if ( true === $save ) {
		pb_backupbuddy::save();
		$destination_settings = pb_backupbuddy::$options['remote_destinations'][$destination_id];
	}
}


$archive_limits_html = '<tr class="">
	<th scope="row" class="" style="">' . __( 'Snapshot Archive Limits', 'it-l10n-backupbuddy' ) . pb_backupbuddy::tip( 'Leave empty for unlimited backups of a type or 0 (zero) to limit to none. WARNING: Use caution when entering 0 (zero) for a type of backup as it could result in the loss of many backups.', '', $echo_tip = false ) . '</th>
	<td class="" style="padding: 0;">

		<table>

			<tr>
				<td>
					&nbsp;
				</td>
				<td>
					' . __( 'Daily', 'it-l10n-backupbuddy' ) . '
				</td>
				<td>
					' . __( 'Weekly', 'it-l10n-backupbuddy' ) . '
				</td>
				<td>
					' . __( 'Monthly', 'it-l10n-backupbuddy' ) . '
				</td>
				<td>
					' . __( 'Yearly', 'it-l10n-backupbuddy' ) . '
				</td>
			</tr>

			';

foreach( $archive_types as $archive_type => $archive_type_name ) {
	$archive_limits_html .= '<tr>';
	$archive_limits_html .= '<td class="label">' . $archive_type_name . '</td>';
	foreach( $archive_periods as $archive_period ) {
		$settings_name = 'limit_' . $archive_type . '_' . $archive_period;
		$archive_limits_html .= '<td><input size="4" type="text" class="small" name="pb_backupbuddy_' . 'limit_' . $archive_type . '_' . $archive_period . '" value="' . $destination_settings[ $settings_name ] . '" /></td>';
	}
	$archive_limits_html .= '</tr>';
}

$archive_limits_html .= '<tr>
			<td colspan="5">
				<span class="description">Set blank to keep unlimited backups of a type or 0 (zero) to limit to none.</span>
			</td>
			</tr>
			<tr style="display: none;">
			<td colspan="5">
				<h4 style="margin: 0;">Stash Usage Estimate:</h4>
				With your above archive limits <i><b>after one year</b></i> of backing up you will have <i><b>at most X snapshots</b></i> (zip archives) for this site stored in your Stash storage, estimated to use up to <i><b>X GB</b></i> of storage (rough estimate based on current site size estimate only).
			</td>
			</tr>

		</table>

	</td>
</tr>';


//echo 'mode:' . $mode;

$default_name = NULL;
if ( 'add' == $mode ) {
	$default_name = 'Solid Backups Stash Live';
}
$settings_form->add_setting( array(
	'type'    => 'hidden',
	'name'    => 'title',
	'title'   => __( 'Destination name', 'it-l10n-backupbuddy' ),
	'tip'     => __( 'Name of the new destination to create. This is for your convenience only.', 'it-l10n-backupbuddy' ),
	'rules'   => 'required|string[1-45]',
	'default' => $default_name,
) );

// Define the tag prefix being used for private tags
// Note: Probably should be a constant but we'll define here for now
// Note: Has to be a global when done like this as we need the value in a callable
global $private_interval_tag_prefix;
$private_interval_tag_prefix = 'itbub-';

// The following definitions are for being able to filter schedule periods from an
// array based on the interval value.
global $filter_schedules_by_interval_comparison_operator;
$filter_schedules_by_interval_comparison_operator = 'lte';

global $filter_schedules_by_interval_operand;
$filter_schedules_by_interval_operand = 0;

// We need to know this on an edit in case we are showing only private schedule periods for selection but the
// schedule we are editing is using a non-private schedule period - we need to include this in the list otherwise
// we end up with defaulting to a value like 'yearly' which the user may not notice...
// For initial adding this will just have been set to the default which should be a provate schedule period tag anyway
$this_schedule_tag = $destination_settings['periodic_process_period'];
$this_schedule_definition = array();

// Get the list of defined schedule periods - this is an array like with entries like:
// ['weekly']=>(['interval']=>604800, ['display']=>__( 'Once Weekely' ))
$schedule_definitions = wp_get_schedules();

// Filter out any schedule period with interval < 1 hour (magic number...wince)
$filter_schedules_by_interval_operand = 60*60;
$schedule_definitions = array_filter( $schedule_definitions, array( 'BackupBuddy_Schedules', 'filter_schedules_by_interval' ) );

// Include the tag (key) in the value to help with handling
array_walk( $schedule_definitions, array( 'BackupBuddy_Schedules', 'walk_add_tag_to_schedule_definition' ) );

// Derive a lists of private and non-private schedules for convenience
$private_schedule_definitions = array_filter( $schedule_definitions, array( 'BackupBuddy_Schedules', 'filter_non_private_schedules' ) );
$non_private_schedule_definitions = array_filter( $schedule_definitions, array( 'BackupBuddy_Schedules', 'filter_private_schedules' ) );

// Note: Unlike on the Schedules page we are not trying to detect and diagnose interfernce
// with schedule period definitions - the same problems could arise here but currently we'll
// leave handling that to the Schedules page. Functionality _could_ be added here later but
// let's not complicaet things further.
$force_show_all_schedule_definitions = false;

// Represent the current schedule definition as an array for generlised aray handling
// If the schedule tag is no longer known (because it was defined by a plugin/theme that is no longer
// active) the we'll create a "dummy" definition to make it obvious to the user when they try and
// edit.
$a = $schedule_definitions[ $this_schedule_tag ];
$this_schedule_definition[ $this_schedule_tag ] = empty( $a ) ? array( 'interval'=> PHP_INT_MAX, 'display' => __( 'Invalid Schedule Interval', 'it-l10n-backupbuddy' ), 'tag' => $this_schedule_tag ) : $a ;

// If the current schedule tag is non-private can we find an equivalent private schedule based on interval?
if ( array_key_exists( $this_schedule_tag, $non_private_schedule_definitions ) ) {
	// Either we find a private schedule that has equivalent interval or we keep the non-private schedule
	$a = array_uintersect( $private_schedule_definitions, $this_schedule_definition, array( 'BackupBuddy_Schedules', 'compare_schedule_definitions_by_interval' ) );
	$this_schedule_definition = empty( $a ) ? $this_schedule_definition : $a;
	// The schedule definition may have changed to a private one so we update the tag in case
	$this_schedule_tag = key( $this_schedule_definition );
}

// This wil come from an option on the Settings page
$show_all_schedule_definitions = pb_backupbuddy::$options['show_all_cron_schedules'];

// Now if we are only showing private schedules we'll use that as the base set and merge
// in what may be a non-private schedule if we are editing such and there was no private schedule
// with same interval. If we found a privaet schedule equivalent then we will be merging that
// into the excisting provate schedule definitions so effectively a noop
// If showing all schedules then no need to do anything as the existing list is all inclusive.
// Note that we may be forcing all shedule definitions to be shown based on the flag
// set earlier.
( false === ( $show_all_schedule_definitions || $force_show_all_schedule_definitions ) ) ? $schedule_definitions = array_merge( $private_schedule_definitions, $this_schedule_definition ) : false ;

// Now we have the final list of schedules we are going to show so let's sort by
// interval, then reverse to go from longest to shortest interval
uasort( $schedule_definitions, array( 'BackupBuddy_Schedules', 'sort_compare_schedules_by_interval' ) );
$schedule_definitions = array_reverse( $schedule_definitions );

// Now we create the actual array to pass to the selection list which will be an array
// of tag=>display where tag is the key from the original schedules aray.
// If there are non-private schedules in the list then the dispay is a string made up of
// the display value from the original schedules array and the tag string which can go some
// way to determining what may have defined the schedule period.
// If the list contains only private tags then we'll only show the schedule display value
$a = array_filter( $schedule_definitions, array( 'BackupBuddy_Schedules', 'filter_private_schedules' ) );
if ( empty( $a ) ) {
	// No non-private schedules in the list - no need to show tag
	$intervals = array_map( array( 'BackupBuddy_Schedules', 'map_schedules_to_settings_list_without_tag' ), $schedule_definitions );
} else {
	// One or more non-private schedules in the list - show tag to try and avoid confusion
	$intervals = array_map( array( 'BackupBuddy_Schedules', 'map_schedules_to_settings_list' ), $schedule_definitions );
}

// If we are editing we will have set this to an appropriate value based on the currently set
// value or an "equivalent" if we have a private schedule with period matching a non-private
// schedule that the setting was originally defined with.
// If we are adding then the value is just the default that we defined earlier.
$default_interval = $this_schedule_tag;

$settings_form->add_setting( array(
	'type'    => 'select',
	'name'    => 'periodic_process_period',
	'title'   => __( 'Full Scan Interval', 'it-l10n-backupbuddy' ),
	'options' => $intervals,
	'default' => $default_interval,
	'tip'     => __('[Default: Twice Daily] - How often the local periodic site scan should run.  This process scans and uploads the current snapshot of the database and any local file changes found. It also audits and verifies remotely stored files. If a remote snapshot is due it will also be triggered. This period must be equal to or more often than the remote Snapshot period.', 'it-l10n-backupbuddy' ),
	'rules'   => 'required',
) );
$settings_form->add_setting( array(
	'type'  => 'text',
	'name'  => 'email',
	'title' => __('Notification email', 'it-l10n-backupbuddy' ),
	'tip'   => __('Email address to send notifications to upon successful Snapshot creation. If left blank your SolidWP Member Account email address will be used.', 'it-l10n-backupbuddy' ),
	'css'   => 'width: 300px;',
) );
$settings_form->add_setting( array(
	'type'  => 'text',
	'name'  => 'no_new_snapshots_error_days',
	'title' => __('Send notification after period of no Snapshots', 'it-l10n-backupbuddy' ),
	'tip'   => __('[Example: 30] - Maximum number of days (set to 0 to disable) that may pass with no new Snapshots created before sending an error notifcation email.', 'it-l10n-backupbuddy' ),
	'rules' => 'required|string[0-99999]',
	'css'   => 'width: 50px;',
	'after' => ' days',
	'rules' => 'int',
) );
$settings_form->add_setting( array(
	'type'      => 'checkbox',
	'name'      => 'send_snapshot_notification',
	'options'   => array( 'unchecked' => '0', 'checked' => '1' ),
	'title'     => __( 'Snapshot success email', 'it-l10n-backupbuddy' ),
	'tip'       => __( '[Default: enabled] - When enabled, an email will be sent to the administrator email address for this site for each snapshot successfully created.', 'it-l10n-backupbuddy' ),
	'after'     => '&nbsp;' . __( 'Yes, send email.' ),
	'css'       => '',
	'rules'     => '',
	'row_class' => '',
) );
$settings_form->add_setting( array(
	'type'      => 'checkbox',
	'name'      => 'show_admin_bar',
	'options'   => array( 'unchecked' => '0', 'checked' => '1' ),
	'title'     => __( 'Show admin bar stats', 'it-l10n-backupbuddy' ),
	'tip'       => __( '[Default: disabled] - When enabled, a brief Live status will be added to the admin bar at the top of the WordPress dashboard for admins.', 'it-l10n-backupbuddy' ),
	'after'     => '&nbsp;' . __( 'Yes, show stats in bar. (Resource intensive on low I/O servers)' ),
	'css'       => '',
	'rules'     => '',
	'row_class' => '',
) );
$settings_form->add_setting( array(
	'type' => 'html',
	'html' => $archive_limits_html,
) );
$settings_form->add_setting( array(
	'type'  => 'textarea',
	'name'  => 'file_excludes',
	'title' => __( 'Additional File Exclusions*', 'it-l10n-backupbuddy' ),
	'tip'   => __( 'Additional files/directories to excludes BEYOND Solid Backups\'s global default file exclusions.', 'it-l10n-backupbuddy' ),
	'css'   => 'width: 100%;',
	'after' => '<span class="description">* ' . __( 'Exclusions beyond global Solid Backups settings.', 'it-l10n-backupbuddy' ) . '</span>',
) );
global $wpdb;
$settings_form->add_setting( array(
	'type'  => 'textarea',
	'name'  => 'table_excludes',
	'title' => __( 'Additional Table Exclusions*', 'it-l10n-backupbuddy' ),
	'tip'   => __( 'Additional database tables to excludes BEYOND Solid Backups\'s global default database table exclusions. You may use {prefix} in place of the current database prefix.', 'it-l10n-backupbuddy' ) . ' Current prefix: `' . $wpdb->prefix . '`.',
	'css'   => 'width: 100%;',
	'after' => '<span class="description">* ' . __( 'Exclusions beyond global Solid Backups settings.', 'it-l10n-backupbuddy' ) . '</span>',
) );
$settings_form->add_setting( array(
	'type'      => 'title',
	'name'      => 'advanced_begin',
	'title'     => '<span class="advanced-toggle-title-icon">' . pb_backupbuddy::$ui->get_icon( 'chevronleft' ) . '</span> ' . __( 'Advanced Options', 'it-l10n-backupbuddy' ),
	'row_class' => 'advanced-toggle-title',
	) );
$settings_form->add_setting( array(
	'type'      => 'checkbox',
	'name'      => 'disable_logging',
	'options'   => array( 'unchecked' => '0', 'checked' => '1' ),
	'title'     => __( 'Disable Live Log', 'it-l10n-backupbuddy' ),
	'tip'       => __( 'When enabled no logging details will be written to the Stash Live log during Stash Live periodic operations. Logs will still be written to the Extraneous Global Log file based on your traditional Solid Backups Advanced Logging Settings. This reduces overhead and server resource usage.' ),
	'row_class' => 'advanced-toggle advanced-toggle-hidden',
) );
$settings_form->add_setting( array(
	'type'      => 'textarea',
	'name'      => 'postmeta_key_excludes',
	'title'     => __( 'Additional Postmeta Key Exclusions', 'it-l10n-backupbuddy' ),
	'tip'       => __( 'Excludes certain postmeta updates to the postmeta keys database (beyond hard-coded defaults) from being immediately backed up upon change and instead only backed up during the periodic (typically daily) database snapshot. This is useful for options which are updates very often. Supports regex using preg_match(), wrapped with forward slashes /. Ex: To exclude postmeta entries that look like someplugin_oranges, someplugin_apples, someplugin_bananas, etc use: /someplugin_.+/', 'it-l10n-backupbuddy' ),
	'row_class' => 'advanced-toggle advanced-toggle-hidden',
	'css'       => 'width: 100%;',
) );
$settings_form->add_setting( array(
	'type'      => 'textarea',
	'name'      => 'options_excludes',
	'title'     => __( 'Additional Options Exclusions', 'it-l10n-backupbuddy' ),
	'tip'       => __( 'Excludes certain options updates to the wp_options table (beyond hard-coded defaults) from being immediately backed up upon change and instead only backed up during the periodic (typically daily) database snapshot. This is useful for options which are updates very often. Supports regex using preg_match(), wrapped with forward slashes /. Ex: To exclude options that look like someplugin_oranges, someplugin_apples, someplugin_bananas, etc use: /someplugin_.+/', 'it-l10n-backupbuddy' ),
	'row_class' => 'advanced-toggle advanced-toggle-hidden',
	'css'       => 'width: 100%;',
) );
$settings_form->add_setting( array(
	'type'      => 'text',
	'name'      => 'max_burst',
	'title'     => __( 'Send per burst', 'it-l10n-backupbuddy' ),
	'tip'       => __( '[Example: 10] - This is the amount of data that will be sent per burst within a single PHP page load/chunk. Bursts happen within a single page load. Chunks occur when broken up between page loads/PHP instances. Reduce if hitting PHP memory limits. Chunking time limits will only be checked between bursts. Lower burst size if timeouts occur before chunking checks trigger.', 'it-l10n-backupbuddy' ),
	'rules'     => 'required|int[5-9999999]',
	'css'       => 'width: 50px;',
	'after'     => ' MB',
	'row_class' => 'advanced-toggle advanced-toggle-hidden',
) );
$settings_form->add_setting( array(
	'type'      => 'text',
	'name'      => 'max_delete_burst',
	'title'     => __( 'Delete per burst', 'it-l10n-backupbuddy' ),
	'tip'       => __( '[Example: 100] - This is the maximum number of files which can be deleted per API call at a time. This helps reduce outgoing connections and improve performance.', 'it-l10n-backupbuddy' ),
	'rules'     => 'required|int[5-9999999]',
	'css'       => 'width: 50px;',
	'after'     => ' files',
	'row_class' => 'advanced-toggle advanced-toggle-hidden',
) );
$settings_form->add_setting( array(
	'type'      => 'text',
	'name'      => 'max_filelist_keys',
	'title'     => __( 'Max number of files to list', 'it-l10n-backupbuddy' ),
	'tip'       => __( '[Example: 250] - This is the maximum number of files to return in a given file listing request from the Live servers.', 'it-l10n-backupbuddy' ),
	'rules'     => 'required|int[0-9999999]',
	'css'       => 'width: 50px;',
	'after'     => ' files',
	'row_class' => 'advanced-toggle advanced-toggle-hidden',
) );
$settings_form->add_setting( array(
	'type'      => 'text',
	'name'      => 'max_daily_failures',
	'title'     => __( 'Max send fails per periodic process run', 'it-l10n-backupbuddy' ),
	'tip'       => __( '[Example: 50] - This is the maximum number of send failures which may occur per day before further sends are halted. This counter is reset at the beginning of the periodic process during daily initialization.', 'it-l10n-backupbuddy' ),
	'rules'     => 'required|int[0-9999999]',
	'css'       => 'width: 50px;',
	'after'     => ' failures',
	'row_class' => 'advanced-toggle advanced-toggle-hidden',
) );
$settings_form->add_setting( array(
	'type'      => 'text',
	'name'      => 'max_wait_on_transfers_time',
	'title'     => __( 'Max time to wait on transfers before Snapshotting', 'it-l10n-backupbuddy' ),
	'tip'       => __( '[Example: 20] - Snapshots cannot be created until all files are uploaded. If when it is time to create Snapshot one or more files (including database tables) remain to send, we will wait for transfers to finish before Snapshotting.', 'it-l10n-backupbuddy' ),
	'rules'     => 'required|int[0-9999999]',
	'css'       => 'width: 50px;',
	'after'     => ' minutes',
	'row_class' => 'advanced-toggle advanced-toggle-hidden',
) );
$settings_form->add_setting( array(
	'type'      => 'text',
	'name'      => 'max_time',
	'title'     => __( 'Max time per operation', 'it-l10n-backupbuddy' ),
	'tip'       => __( '[Example: 30] - Enter 0 for no limit (aka no chunking; bursts may still occur based on burst size setting). This is the maximum number of seconds per page load that operations will run for. If this time is exceeded when a burst finishes then the next burst will be chunked and ran on a new page load. Multiple bursts may be sent within each chunk. NOTE: This ONLY applies to file sends and some Stash Live procedures. Chunking of file and signature calculations may use global Solid Backups setting.', 'it-l10n-backupbuddy' ),
	'rules'     => '',
	'css'       => 'width: 50px;',
	'after'     => ' secs. <span class="description">' . __( 'Blank for detected default:', 'it-l10n-backupbuddy' )  . ' ' . backupbuddy_core::detectMaxExecutionTime() . ' sec. Change only if directed.</span>',
	'row_class' => 'advanced-toggle advanced-toggle-hidden',
) );
$settings_form->add_setting( array(
	'type'      => 'text',
	'name'      => 'max_send_details_limit',
	'title'     => __( 'Max number of transfers to log', 'it-l10n-backupbuddy' ),
	'tip'       => __( '[Example: 5] - Solid Backups will keep the details including logging of the last X number of remote transfers to the Live servers. Trimming these prevents large numbers of files from building up.', 'it-l10n-backupbuddy' ),
	'rules'     => '',
	'css'       => 'width: 50px;',
	'after'     => ' transfers.',
	'row_class' => 'advanced-toggle advanced-toggle-hidden',
) );
$settings_form->add_setting( array(
	'type'      => 'checkbox',
	'name'      => 'use_server_cert',
	'options'   => array( 'unchecked' => '0', 'checked' => '1' ),
	'title'     => __( 'Use system CA bundle', 'it-l10n-backupbuddy' ),
	'tip'       => __( '[Default: disabled] - When enabled, Solid Backups will use your web server\'s certificate bundle for connecting to the server instead of Solid Backups bundle. Use this if SSL fails due to SSL certificate issues.', 'it-l10n-backupbuddy' ),
	'css'       => '',
	'after'     => '<span class="description"> ' . __('Use webserver certificate bundle instead of Solid Backups\'s.', 'it-l10n-backupbuddy' ) . '</span>',
	'rules'     => '',
	'row_class' => 'advanced-toggle advanced-toggle-hidden',
) );
$settings_form->add_setting( array(
	'type'      => 'checkbox',
	'name'      => 'disable_hostpeer_verficiation',
	'options'   => array( 'unchecked' => '0', 'checked' => '1' ),
	'title'     => __( 'Disable SSL Verifications', 'it-l10n-backupbuddy' ),
	'tip'       => __( '[Default: disabled] - When enabled, the SSL host and peer information will not be verified. While the connection will still be encrypted SSL\'s man-in-the-middle protection will be voided. Disable only if you understand and if directed by support to work around host issues.', 'it-l10n-backupbuddy' ),
	'css'       => '',
	'after'     => '<span class="description"> ' . __('Check only if directed by support. Use with caution.', 'it-l10n-backupbuddy' ) . '</span>',
	'rules'     => '',
	'row_class' => 'advanced-toggle advanced-toggle-hidden',
) );
$settings_form->add_setting( array(
	'type'      => 'checkbox',
	'name'      => 'ssl',
	'options'   => array( 'unchecked' => '0', 'checked' => '1' ),
	'title'     => __( 'Encrypt connection', 'it-l10n-backupbuddy' ) . '*',
	'tip'       => __( '[Default: enabled] - When enabled, all transfers will be encrypted with SSL encryption. Disabling this may aid in connection troubles but results in lessened security. Note: Once your files arrive on our server they are encrypted using AES256 encryption. They are automatically decrypted upon download as needed.', 'it-l10n-backupbuddy' ),
	'css'       => '',
	'after'     => '<span class="description"> ' . __('Enable connecting over SSL.', 'it-l10n-backupbuddy' ) . '<br>* Files are always encrypted with AES256 upon arrival.</span>',
	'rules'     => '',
	'row_class' => 'advanced-toggle advanced-toggle-hidden',
) );

// Note: This is basically a repeat of what we did earlier for the periodic_process_period but now for the
// remote_snapshot_period. This could be refactored to avoid repeating some aspects but for now it is safer
// to just repeat the whole process and there is very little actual overhead impact.

// We need to know this on an edit in case we are showing only private schedule periods for selection but the
// schedule we are editing is using a non-private schedule period - we need to include this in the list otherwise
// we end up with defaulting to a value like 'yearly' which the user may not notice...
// For initial adding this will just have been set to the default which should be a private schedule period tag anyway
$this_schedule_tag = $destination_settings['remote_snapshot_period'];
$this_schedule_definition = array();

// Get the list of defined schedule periods - this is an array like with entries like:
// ['weekly']=>(['interval']=>604800, ['display']=>__( 'Once Weekely' ))
$schedule_definitions = wp_get_schedules();

// Filter out any schedule period with interval < 1 hour (magic number...wince)
$filter_schedules_by_interval_operand = 60*60;
$schedule_definitions = array_filter( $schedule_definitions, array( 'BackupBuddy_Schedules', 'filter_schedules_by_interval' ) );

// Include the tag (key) in the value to help with handling
array_walk( $schedule_definitions, array( 'BackupBuddy_Schedules', 'walk_add_tag_to_schedule_definition' ) );

// Derive a lists of private and non-private schedules for convenience
$private_schedule_definitions = array_filter( $schedule_definitions, array( 'BackupBuddy_Schedules', 'filter_non_private_schedules' ) );
$non_private_schedule_definitions = array_filter( $schedule_definitions, array( 'BackupBuddy_Schedules', 'filter_private_schedules' ) );

// Note: Unlike on the Schedules page we are not trying to detect and diagnose interfernce
// with schedule period definitions - the same problems could arise here but currently we'll
// leave handling that to the Schedules page. Functionality _could_ be added here later but
// let's not complicaet things further.
$force_show_all_schedule_definitions = false;

// Represent the current schedule definition as an array for generlised aray handling
// If the schedule tag is no longer known (because it was defined by a plugin/theme that is no longer
// active) the we'll create a "dummy" definition to make it obvious to the user when they try and
// edit.
$a = $schedule_definitions[ $this_schedule_tag ];
$this_schedule_definition[ $this_schedule_tag ] = empty( $a ) ? array( 'interval'=> PHP_INT_MAX, 'display' => __( 'Invalid Schedule Interval', 'it-l10n-backupbuddy' ), 'tag' => $this_schedule_tag ) : $a ;

// If the current schedule tag is non-private can we find an equivalent private schedule based on interval?
if ( array_key_exists( $this_schedule_tag, $non_private_schedule_definitions ) ) {
	// Either we find a private schedule that has equivalent interval or we keep the non-private schedule
	$a = array_uintersect( $private_schedule_definitions, $this_schedule_definition, array( 'BackupBuddy_Schedules', 'compare_schedule_definitions_by_interval' ) );
	$this_schedule_definition = empty( $a ) ? $this_schedule_definition : $a;
	// The schedule definition may have changed to a private one so we update the tag in case
	$this_schedule_tag = key( $this_schedule_definition );
}

// This wil come from an option on the Settings page
$show_all_schedule_definitions = pb_backupbuddy::$options['show_all_cron_schedules'];

// Now if we are only showing private schedules we'll use that as the base set and merge
// in what may be a non-private schedule if we are editing such and there was no private schedule
// with same interval. If we found a privaet schedule equivalent then we will be merging that
// into the excisting provate schedule definitions so effectively a noop
// If showing all schedules then no need to do anything as the existing list is all inclusive.
// Note that we may be forcing all shedule definitions to be shown based on the flag
// set earlier.
( false === ( $show_all_schedule_definitions || $force_show_all_schedule_definitions ) ) ? $schedule_definitions = array_merge( $private_schedule_definitions, $this_schedule_definition ) : false ;

// Now we have the final list of schedules we are going to show so let's sort by
// interval, then reverse to go from longest to shortest interval
uasort( $schedule_definitions, array( 'BackupBuddy_Schedules', 'sort_compare_schedules_by_interval' ) );
$schedule_definitions = array_reverse( $schedule_definitions );

// Now we create the actual array to pass to the selection list which will be an array
// of tag=>display where tag is the key from the original schedules aray.
// If there are non-private schedules in the list then the dispay is a string made up of
// the display value from the original schedules array and the tag string which can go some
// way to determining what may have defined the schedule period.
// If the list contains only private tags then we'll only show the schedule display value
$a = array_filter( $schedule_definitions, array( 'BackupBuddy_Schedules', 'filter_private_schedules' ) );
if ( empty( $a ) ) {
	// No non-private schedules in the list - no need to show tag
	$intervals = array_map( array( 'BackupBuddy_Schedules', 'map_schedules_to_settings_list_without_tag' ), $schedule_definitions );
} else {
	// One or more non-private schedules in the list - show tag to try and avoid confusion
	$intervals = array_map( array( 'BackupBuddy_Schedules', 'map_schedules_to_settings_list' ), $schedule_definitions );
}

// If we are editing we will have set this to an appropriate value based on the currently set
// value or an "equivalent" if we have a private schedule with period matching a non-private
// schedule that the setting was originally defined with.
// If we are adding then the value is just the default that we defined earlier.
$default_interval = $this_schedule_tag;

$settings_form->add_setting( array(
	'type'      => 'select',
	'name'      => 'remote_snapshot_period',
	'title'     => __( 'Snapshot Interval', 'it-l10n-backupbuddy' ),
	'options'   => $intervals,
	'default'   => $default_interval,
	'tip'       => __('[Default: Daily; Recommended] - How often snapshots should be made of all files, database content, and data stored on the remote Live servers. This period must be equal to or less often than the overall periodic scan period. NOTE: This remote snapshot will not occur until all local periodic process steps are completed. WARNING: Changing this from daily may result in unexpected behavior with archive limits as it expects once daily Snapshots. Change with caution.', 'it-l10n-backupbuddy' ),
	'rules'     => 'required',
	'row_class' => 'advanced-toggle advanced-toggle-hidden',
	'after'     => '<span class="description"> ' . __('Use caution changing. See tip for details.', 'it-l10n-backupbuddy' ),
) );

$available_versions = array();

// Remove Stash v2 as an option if PHP8 or greater.
if ( version_compare( phpversion(), '8', '<' ) ) {
	$available_versions['2']  = 'Stash v2';
}
$stash3_php_minimum = file_get_contents( dirname( __FILE__ ) . '/_phpmin.php' );
if ( version_compare( PHP_VERSION, $stash3_php_minimum, '>=' ) ) { // Server's PHP is insufficient for this option.
	$available_versions['3'] = 'Stash v3';
}

if ( ( $mode !== 'edit' ) || ( '0' == $destination_settings['disable_file_management'] ) ) {
	$settings_form->add_setting( array(
		'type'      => 'checkbox',
		'name'      => 'disable_file_management',
		'options'   => array( 'unchecked' => '0', 'checked' => '1' ),
		'title'     => __( 'Disable file management', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[[Default: unchecked] - When checked, selecting this destination disables browsing or accessing files stored at this destination from within Solid Backups. NOTE: Once enabled this cannot be disabled without deleting and re-creating this destination. NOTE: Once enabled this cannot be disabled without deleting and re-creating this destination.', 'it-l10n-backupbuddy' ),
		'css'       => '',
		'rules'     => '',
		'after'     => __( 'Once disabled you must recreate the destination to re-enable.', 'it-l10n-backupbuddy' ),
		'row_class' => 'advanced-toggle advanced-toggle-hidden',
	) );
}
