<?php
/**
 * Scheduling Page
 *
 * @package BackupBuddy
 */

require_once pb_backupbuddy::plugin_path() . '/classes/class-backupbuddy-schedules.php';

pb_backupbuddy::load_script( 'jquery-ui-datepicker', true ); // WP core script.
pb_backupbuddy::load_script( 'jquery-ui-slider', true ); // WP core script.
pb_backupbuddy::load_script( 'vendor-scripts/jquery-ui-timepicker-addon.min.js' );
wp_enqueue_style( 'backupbuddy-core' );
wp_enqueue_style( 'solid-jquery-smoothness' );
?>

<script type="text/javascript">
jQuery(function(){

	// @todo revisit this. JR3 03/06/2023
	// WP 3.2.1 does not like the datepicker and breaks other JS so we omit it if it failed to load properly.
	if ( jQuery.isFunction( jQuery.fn.datepicker ) ) {
		jQuery( '#pb_backupbuddy_first_run' ).datetimepicker({
			amNames: ['am', 'a'],
			pmNames: ['pm', 'p'],
			timeFormat: "hh:mm tt"
		});

		// Force datepicker date format. Some themes change the format on BB's page unfortunately.
		jQuery.datepicker.setDefaults({"dateFormat":"mm\/dd\/yy"});

		// Hide datepicker initially.
		jQuery( '.ui-datepicker' ).hide();
	}

});
</script>

<?php
backupbuddy_core::versions_confirm();
$date_format_example = 'mm/dd/yyyy hh:mm [am/pm]'; // Example date format for displaying to user.

/***** BEGIN SCHEDULE DELETION */
if ( 'delete_schedule' == pb_backupbuddy::_POST( 'bulk_action' ) ) {
	pb_backupbuddy::verify_nonce( pb_backupbuddy::_POST( '_wpnonce' ) ); // Security check to prevent unauthorized deletions by posting from a remote place.

	$deleted_schedules = array();
	$schedule_deletion_errors = array();
	$items = pb_backupbuddy::_POST( 'items' );
	if ( ! empty( $items ) && is_array( $items ) ) {
		foreach ( pb_backupbuddy::_POST( 'items' ) as $id ) {
			$schedule_title = pb_backupbuddy::$options['schedules'][ $id ]['title'];
			if ( backupbuddy_api::deleteSchedule( $id, true ) ) {
				$deleted_schedules[] = htmlentities( $schedule_title );
			} else {
				$schedule_deletion_errors[] = htmlentities( $schedule_title );
			}
		} // end foreach.

		if ( count( $deleted_schedules ) ) {
			pb_backupbuddy::alert( __( 'Deleted schedule(s):', 'it-l10n-backupbuddy' ) . ' ' . implode( ', ', $deleted_schedules ), false, '', '', '', array( 'class' => 'below-h2' ) );
		} elseif ( count( $schedule_deletion_errors ) ) {
			pb_backupbuddy::alert( __( 'There were problems deleting the following schedule(s):', 'it-l10n-backupbuddy' ) . ' ' . implode( ', ', $schedule_deletion_errors ), true );
		}
	}
}
/***** END SCHEDULE DELETION */

// Derive mode (whether editing existing schedule or adding a new schedule) here for use in various conditionals
// Note: 'edit' parameter if non-empty will be the integer identifier (as a string) of the schedule being edited.
$mode = '' == pb_backupbuddy::_GET( 'edit' ) ? 'add' : 'edit';

// Define the tag prefix being used for private tags.
// Note: Probably should be a constant but we'll define here for now.
// Note: Has to be a global when done like this as we need the value in a callable.
global $private_interval_tag_prefix;
$private_interval_tag_prefix = 'itbub-';

// EDIT existing schedule.
if ( 'edit' === $mode ) {
	$savepoint = 'schedules#' . pb_backupbuddy::_GET( 'edit' );

	// We need to know this on an edit in case we are showing only private schedule periods for selection but the
	// schedule we are editing is using a non-private schedule period - we need to include this in the list otherwise
	// we end up with defaulting to a value like 'yearly' which the user may not notice... Note in teh backup schedule
	// definition this is called the 'interval' but actually it is the tag value.
	$this_schedule_tag = pb_backupbuddy::$options['schedules'][ pb_backupbuddy::_GET( 'edit' ) ]['interval'];

	$next_run = backupbuddy_core::next_scheduled( 'run_scheduled_backup', array( (int) pb_backupbuddy::_GET( 'edit' ) ), BackupBuddy_Schedules::CRON_GROUP );
	if ( ! is_numeric( $next_run ) || 0 == $next_run ) { // Unable to determine next runtime so just set to now.
		$next_run = time();
	}

	$first_run_value = date( 'm/d/Y h:i a', $next_run + ( get_option( 'gmt_offset' ) * 3600 ) );

	if ( '' != pb_backupbuddy::_POST( 'pb_backupbuddy_remote_destinations' ) ) {
		$destination_list = pb_backupbuddy::_POST( 'pb_backupbuddy_remote_destinations' );
	} else {
		$destination_list = pb_backupbuddy::$options['schedules'][ pb_backupbuddy::_GET( 'edit' ) ]['remote_destinations'];
	}

	$remote_destinations = BackupBuddy_Schedules::build_remote_destinations( $destination_list );
} else { // ADD new schedule.
	$savepoint = false;

	// We don't actually have a schedule tag but this represents what the default is for
	// when we are adding a new bsckup schedule.
	// Note: teh value could probably be defined as a constant.
	$this_schedule_tag = $private_interval_tag_prefix . 'daily';

	$first_run_value     = date( 'm/d/Y h:i a', time() + ( ( get_option( 'gmt_offset' ) * 3600 ) + 86400 ) );
	$remote_destinations = '<ul id="pb_backupbuddy_remotedestinations_list"></ul>';
}

$schedule_form = new pb_backupbuddy_settings( 'scheduling', $savepoint, 'edit=' . pb_backupbuddy::_GET( 'edit' ) . '&tab=edit-schedule', 250 );

$schedule_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'title',
		'title' => 'Schedule name',
		'tip'   => __( 'This is a name for your reference only.', 'it-l10n-backupbuddy' ),
		'rules' => 'required',
	)
);

$profile_list    = array();
$backup_profiles = array();
// Rearrange backup profiles, move Full and DB up to the top.
if ( isset( pb_backupbuddy::$options['profiles'][2] ) ) {
	$backup_profiles[2] = pb_backupbuddy::$options['profiles'][2]; // Full (Complete Backup).
}
if ( isset( pb_backupbuddy::$options['profiles'][1] ) ) {
	$backup_profiles[1] = pb_backupbuddy::$options['profiles'][1]; // DB (Database Only).
}
foreach ( pb_backupbuddy::$options['profiles'] as $profile_id => $profile ) {
	if ( isset( $backup_profiles[ $profile_id ] ) ) {
		continue;
	}
	$backup_profiles[ $profile_id ] = $profile;
}
foreach ( $backup_profiles as $profile_id => $profile ) {
	if ( 0 == $profile_id ) {
		continue;
	} // default profile.

	if ( 'full' == $profile['type'] ) {
		$pretty_type = __( 'Full', 'it-l10n-backupbuddy' );
	} elseif ( 'db' == $profile['type'] ) {
		$pretty_type = __( 'Database Only', 'it-l10n-backupbuddy' );
	} elseif ( 'files' == $profile['type'] ) {
		$pretty_type = __( 'Files Only', 'it-l10n-backupbuddy' );
	} elseif ( 'plugins' == $profile['type'] ) {
		$pretty_type = __( 'Plugins Only', 'it-l10n-backupbuddy' );
	} elseif ( 'themes' == $profile['type'] ) {
		$pretty_type = __( 'Themes Only', 'it-l10n-backupbuddy' );
	} elseif ( 'media' == $profile['type'] ) {
		$pretty_type = __( 'Media Only', 'it-l10n-backupbuddy' );
	} else {
		$pretty_type = __( 'Unknown', 'it-l10n-backupbuddy' );
	}
	$profile_list[ $profile_id ] = htmlentities( $profile['title'] ) . ' (' . $pretty_type . ')';
}
$schedule_form->add_setting(
	array(
		'type'    => 'select',
		'name'    => 'profile',
		'title'   => __( 'Backup profile', 'it-l10n-backupbuddy' ),
		'options' => $profile_list,
		'tip'     => __( 'Full backups contain all files (except exclusions) and your database. Database only backups consist of an export of your mysql database; no WordPress files or media. Database backups are typically much smaller and faster to perform and are typically the most quickly changing part of a site.', 'it-l10n-backupbuddy' ),
		'rules'   => 'required',
	)
);

global $before_backupbuddy_in_list;
$before_backupbuddy_in_list = true;
global $innocent_cron_schedules_function_owners;
$innocent_cron_schedules_function_owners = array( 'backupbuddy', 'ithemes-security-pro', 'better-wp-security' );

/**
 * Get the list of defined schedule periods - this is an array like with entries like:
 *     ['weekly'] => ( ['interval'] => 604800, ['display'] => __( 'Once Weekely' ) ).
 */
$schedule_definitions = wp_get_schedules();

// Include the tag (key) in the value to help with handling.
array_walk( $schedule_definitions, array( 'BackupBuddy_Schedules', 'walk_add_tag_to_schedule_definition' ) );

// Derive a lists of private and non-private schedules for convenience.
$private_schedule_definitions     = array_filter( $schedule_definitions, array( 'BackupBuddy_Schedules', 'filter_non_private_schedules' ) );
$non_private_schedule_definitions = array_filter( $schedule_definitions, array( 'BackupBuddy_Schedules', 'filter_private_schedules' ) );

// Represent the current schedule definition as an array for generlised aray handling
// If the schedule tag is no longer known (because it was defined by a plugin/theme that is no longer
// active) the we'll create a "dummy" definition to make it obvious to the user when they try and
// edit.
// Note that the Schedules page should already be showing error messages about the schedule
// being undefined anyway so this is just reinforcing it.
$a = $schedule_definitions[ $this_schedule_tag ];
$this_schedule_definition[ $this_schedule_tag ] = empty( $a ) ? array(
	'interval' => PHP_INT_MAX,
	'display'  => __( 'Invalid Schedule Interval', 'it-l10n-backupbuddy' ),
	'tag'      => $this_schedule_tag,
) : $a;

// If the current schedule tag is non-private can we find an equivalent private schedule based on interval?
if ( array_key_exists( $this_schedule_tag, $non_private_schedule_definitions ) ) {
	// Either we find a private schedule that has equivalent interval or we keep the non-private schedule.
	$a                        = array_uintersect( $private_schedule_definitions, $this_schedule_definition, array( 'BackupBuddy_Schedules', 'compare_schedule_definitions_by_interval' ) );
	$this_schedule_definition = empty( $a ) ? $this_schedule_definition : $a;
	// The schedule definition may have changed to a private one so we update the tag in case.
	$this_schedule_tag = key( $this_schedule_definition );
}

// Now if we are only showing private schedules we'll use that as the base set and merge
// in what may be a non-private schedule if we are editing such and there was no private schedule
// with same interval. If we found a private schedule equivalent then we will be merging that
// into the existing private schedule definitions so effectively a noop
// If showing all schedules then no need to do anything as the existing list is all inclusive.
// Note that we may be forcing all shedule definitions to be shown based on the flag
// set earlier.
if ( false === pb_backupbuddy::$options['show_all_cron_schedules'] ) {
	$schedule_definitions = array_merge( $private_schedule_definitions, $this_schedule_definition );
}

// Now we have the final list of schedules we are going to show so let's sort by
// interval, then reverse to go from longest to shortest interval.
uasort( $schedule_definitions, array( 'BackupBuddy_Schedules', 'sort_compare_schedules_by_interval' ) );
$schedule_definitions = array_reverse( $schedule_definitions );

// Now we create the actual array to pass to the selection list which will be an array
// of tag=>display where tag is the key from the original schedules aray.
// If there are non-private schedules in the list then the dispay is a string made up of
// the display value from the original schedules array and the tag string which can go some
// way to determining what may have defined the schedule period.
// If the list contains only private tags then we'll only show the schedule display value.
$a = array_filter( $schedule_definitions, array( 'BackupBuddy_Schedules', 'filter_private_schedules' ) );
if ( empty( $a ) ) {
	// No non-private schedules in the list - no need to show tag.
	$intervals = array_map( array( 'BackupBuddy_Schedules', 'map_schedules_to_settings_list_without_tag' ), $schedule_definitions );
} else {
	// One or more non-private schedules in the list - show tag to try and avoid confusion.
	$intervals = array_map( array( 'BackupBuddy_Schedules', 'map_schedules_to_settings_list' ), $schedule_definitions );
}

// Warn if no intervals are available.
if ( ! count( $intervals ) ) {
	$interval_warning = esc_html__( 'No cron intervals are available. Schedule may not work properly.', 'it-l10n-backupbuddy' );
	if ( ! empty( pb_backupbuddy::$options['show_all_cron_schedules'] ) ) {
		$interval_warning .= ' ' . esc_html__( 'You may need to disable `Show all defined cron schedules` under Settings > Advanced Settings/Troubleshooting', 'it-l10n-backupbuddy' );
	}
	pb_backupbuddy::alert( $interval_warning, true );
}

// If we are editing we will have set this to an appropriate value based on the backup schedule
// being edited or an "equivalent" if we have a private schedule with period matching a non-private
// schedule that the backup schedule was originally defined with.
// If we are adding then the value is just the default for a new schedule that we defined earlier.
$default_interval = $this_schedule_tag;

$schedule_form->add_setting(
	array(
		'type'    => 'select',
		'name'    => 'interval',
		'title'   => __( 'Backup interval', 'it-l10n-backupbuddy' ),
		'options' => $intervals,
		'default' => $default_interval,
		'tip'     => __( 'Time period between backups.', 'it-l10n-backupbuddy' ),
		'rules'   => 'required',
		'after'   => '&nbsp;&nbsp;&nbsp;<span class="description description-block">Unsure how often to schedule? Try starting with daily database-only and weekly full backups.</span>',
	)
);

$schedule_form->add_setting(
	array(
		'type'    => 'text',
		'name'    => 'first_run',
		'title'   => __( 'Date/time of next run', 'it-l10n-backupbuddy' ),
		'tip'     => __( 'IMPORTANT: For scheduled events to occur someone (or you) must visit this site on or after the scheduled time. If no one visits your site for a long period of time some backup events may not be triggered.', 'it-l10n-backupbuddy' ),
		'rules'   => 'required',
		'default' => $first_run_value,
		'after'   => '<span class="description description-block">' .
			/* translators: 1: date/time, 2: URL to WordPress settings */
			sprintf( __( 'Currently <code>%1$s UTC</code> based on <a href="%2$s">WordPress settings</a>.', 'it-l10n-backupbuddy' ),
				date( 'm/d/Y h:i a ' . get_option( 'gmt_offset' ), time() + ( get_option( 'gmt_offset' ) * 3600 ) ),
				admin_url( 'options-general.php' )
			)
			. '</span>',
	)
);

$schedule_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'remote_destinations',
		'title' => __( 'Remote backup destination(s)', 'it-l10n-backupbuddy' ),
		'rules' => '',
		'css'   => 'display: none;',
		'after' => $remote_destinations . '<a href="' . pb_backupbuddy::ajax_url( 'destination_picker' ) . '&selecting=1&#038;TB_iframe=1&#038;width=640&#038;height=600" class="thickbox button button-secondary secondary-button" style="margin-top: 3px;" title="' . __( 'Select a Destination', 'it-l10n-backupbuddy' ) . '">' . __( '+ Add Remote Destination', 'it-l10n-backupbuddy' ) . '</a>',
	)
);

$schedule_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'delete_after',
		'title'   => '&nbsp;',
		'after'   => ' ' . __( 'Delete local backup file after remote send success', 'it-l10n-backupbuddy' ),
		'options' => array(
			'checked'   => '1',
			'unchecked' => '0',
		),
		'rules'   => '',
	)
);

// Set enabled state of schedule to null if editing existing profile otherwise '1' (enabled) for new schedule
// Setting to null should pick up the value from the existing schedule definition.
$default_on_off = 'edit' == $mode ? null : '1';

$schedule_form->add_setting(
	array(
		'type'    => 'checkbox',
		'name'    => 'on_off',
		'title'   => __( 'Schedule enabled?', 'it-l10n-backupbuddy' ),
		'options' => array(
			'checked'   => '1',
			'unchecked' => '0',
		),
		'default' => $default_on_off,
		'tip'     => __( '[Default: enabled] When disabled this schedule will be effectively turned off. This scheduled backup will not occur when disabled / off. You can re-enable schedules by editing them.', 'it-l10n-backupbuddy' ),
		'after'   => ' ' . __( 'Enable schedule to run', 'it-l10n-backupbuddy' ),
	)
);

/***** BEGIN ADDING (or editing) SCHEDULE AND PROCESSING FORM */
$submitted_schedule = $schedule_form->process(); // Handles processing the submitted form (if applicable).

if ( ! empty( $submitted_schedule ) && count( $submitted_schedule['errors'] ) == 0 ) {

	// ADD SCHEDULE.
	if ( 'add' == $mode ) { // ADD SCHEDULE.
		$error = false;

		// Don't allow 'delete file after send to destination' if no destination picked.
		if ( '1' == $submitted_schedule['data']['delete_after'] && '' == trim( $submitted_schedule['data']['remote_destinations'] ) ) {
			pb_backupbuddy::alert( 'Error: You have selected to delete backups after sending but did not specify any remote destinations. Click "Add Remote Destination" to select a destination.', true );
			$error = true;
		}

		if ( 0 == $submitted_schedule['data']['first_run'] || 18000 == $submitted_schedule['data']['first_run'] ) {
			pb_backupbuddy::alert( sprintf( __( 'Invalid time format. Please use the specified format / example %s', 'it-l10n-backupbuddy' ), $date_format_example ) );
			$error = true;
		}

		$remote_destinations = trim( $submitted_schedule['data']['remote_destinations'], '|' );
		$remote_destinations = explode( '|', $remote_destinations );
		$delete_after        = false;
		if ( '1' == $submitted_schedule['data']['delete_after'] ) {
			$delete_after = true;
		}

		$enabled = false;
		if ( isset( $submitted_schedule['data']['on_off'] ) && '1' === $submitted_schedule['data']['on_off'] ) {
			$enabled = true;
		}

		if ( false === $error ) {
			$add_response  = backupbuddy_api::addSchedule(
				$title     = $submitted_schedule['data']['title'],
				$profile   = $submitted_schedule['data']['profile'],
				$interval  = $submitted_schedule['data']['interval'],
				$first_run = pb_backupbuddy::$format->unlocalize_time( strtotime( $submitted_schedule['data']['first_run'] ) ),
				$remote_destinations,
				$delete_after,
				$enabled
			);

			if ( true !== $add_response ) {
				pb_backupbuddy::alert( 'Error scheduling: ' . $add_response );
			} else { // Success.
				pb_backupbuddy::save();
				$schedule_form->clear_values();
				$schedule_form->set_value( 'on_off', $enabled ? '1' : '0' );
				pb_backupbuddy::alert( 'Added new schedule `' . htmlentities( $submitted_schedule['data']['title'] ) . '`.', false, '', '', '', array( 'class' => 'below-h2' ) );
				Solid_Backups_Telemetry::trackEvent(
					'add_new_schedule',
					[
						'schedule_interval' => $submitted_schedule['data']['interval'],
						'schedule_profile'  => $submitted_schedule['data']['profile'],
						'schedule_title'    => $submitted_schedule['data']['title']
					]
				);
			}
		}
	} else { // EDIT SCHEDULE. Form handles saving; just need to update timestamp.
		$edit_id   = (int) pb_backupbuddy::_GET( 'edit' );

		// Save the First Run value.
		$first_run = pb_backupbuddy::$format->unlocalize_time( strtotime( $submitted_schedule['data']['first_run'] ) );
		if ( 0 == $first_run || 18000 == $first_run ) {
			pb_backupbuddy::alert( sprintf( __( 'Invalid time format. Please use the specified format / example %s', 'it-l10n-backupbuddy' ), $date_format_example ), true, '', '', '', array( 'class' => 'below-h2' ) );
			$error = true;
		}

		pb_backupbuddy::$options['schedules'][ $edit_id ]['first_run'] = $first_run;

		// Maybe update scheduled actions.
		if ( ! BackupBuddy_Schedules::schedule_is_disabled( $edit_id ) ) {
			$interval = pb_backupbuddy::$options['schedules'][ $edit_id ]['interval'];

			pb_backupbuddy::log( 'details', 'Rescheduling with the following parameters: ' . print_r( $first_run, true ) . print_r( $interval, true ). print_r( $edit_id, true ) );

			// Remove old schedule.
			BackupBuddy_Schedules::unschedule_recurring_backup( (int) $edit_id );

			// Add new schedule.
			$result = BackupBuddy_Schedules::schedule_recurring_backup( $first_run, $interval, 'run_scheduled_backup', array( (int) $edit_id ) );

			pb_backupbuddy::$options['schedules'][ $edit_id ]['next_run'] = $result;
		}

		pb_backupbuddy::save();

		$edited_schedule = $submitted_schedule['data'];
		backupbuddy_core::addNotification( 'schedule_updated', 'Backup schedule updated', 'An existing schedule "' . $edited_schedule['title'] . '" has been updated.', $edited_schedule );
		Solid_Backups_Telemetry::trackEvent(
			'edit_schedule',
			[
				'schedule_interval' => $submitted_schedule['data']['interval'],
				'schedule_profile'  => $submitted_schedule['data']['profile'],
				'schedule_title'    => $submitted_schedule['data']['title']
			]
		);
	}
} elseif ( ! empty( $submitted_schedule['errors'] ) && count( $submitted_schedule['errors'] ) > 0 ) {
	foreach ( $submitted_schedule['errors'] as $error ) {
		pb_backupbuddy::alert( $error );
	}
}
$data['schedule_form'] = $schedule_form;
/***** END ADDING (or editing) SCHEDULE AND PROCESSING FORM */

// Validate that all internal schedules are properly registered in the WordPress cron.
require_once pb_backupbuddy::plugin_path() . '/classes/housekeeping.php';
backupbuddy_housekeeping::validate_bb_schedules_in_wp();

$schedules = array();
foreach ( pb_backupbuddy::$options['schedules'] as $schedule_id => $schedule ) {

	$profile = pb_backupbuddy::$options['profiles'][ (int) $schedule['profile'] ];

	$title = esc_html( $schedule['title'] );
	if ( 'full' == $profile['type'] ) {
		$type = 'Full';
	} elseif ( 'files' == $profile['type'] ) {
		$type = 'Files only';
	} elseif ( 'db' == $profile['type'] ) {
		$type = 'Database only';
	} elseif ( 'themes' == $profile['type'] ) {
		$type = 'Themes only';
	} elseif ( 'plugins' == $profile['type'] ) {
		$type = 'Plugins only';
	} elseif ( 'media' == $profile['type'] ) {
		$type = 'Media only';
	} else {
		$type = 'Unknown: ' . $profile['type'];
	}
	$type     = $profile['title'] . ' (' . $type . ')';
	$interval = $schedule['interval'];

	$schedule_intervals = wp_get_schedules();
	if ( ! isset( $schedule_intervals[ $interval ] ) ) { // Detect invalid schedule interval.
		$warning = __( 'Invalid schedule interval! Delete and recreate the schedule with a valid interval for this schedule to work.', 'it-l10n-backupbuddy' ) . ' ' . __( 'Invalid interval tag', 'it-l10n-backupbuddy' ) . ': `' . $interval . '`';
		pb_backupbuddy::alert( $warning, true );
		$interval = '<span class="pb_label pb_label-important">' . __( 'ERROR', 'it-l10n-backupbuddy' ) . '</span> <font color="red">' . $warning . '</font>';
	} else {
		$interval = $schedule_intervals[ $interval ]['display'] . ' (' . $interval . ')';
	}

	$on_off = 'Enabled';
	if ( BackupBuddy_Schedules::schedule_is_disabled( $schedule_id ) ) {
		$on_off = '<font color=red>Disabled</font>';
	}

	$destinations      = explode( '|', $schedule['remote_destinations'] );
	$destination_array = array();
	foreach ( $destinations as &$destination ) {
		if ( isset( $destination ) && '' != $destination ) {
			if ( ! isset( pb_backupbuddy::$options['remote_destinations'][ $destination ] ) ) {
				pb_backupbuddy::alert( 'The schedule `' . $title . '` is set to send to a remote destination which no longer exists. Please edit it and remove the invalid destination.' );
				$destination_array[] = '{destination no longer exists}';
			} else {
				$destination_array[] = pb_backupbuddy::$options['remote_destinations'][ $destination ]['title'] . ' [' . backupbuddy_core::pretty_destination_type( pb_backupbuddy::$options['remote_destinations'][ $destination ]['type'] ) . ']';
			}
		}
	}

	$destinations = implode( ', ', $destination_array );

	if ( count( $destination_array ) > 0 ) {
		if ( '1' == $schedule['delete_after'] ) {
			$destinations .= '<br><span class="description">Delete local backup file after send</span>';
		} else {
			$destinations .= '<br><span class="description">Do not delete local backup file after send</span>';
		}
	} else {
		$destinations = '<span class="description">None</span>';
	}

	// Determine first run.
	$first_run = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $schedule['first_run'] ) );

	// Determine last run.
	if ( isset( $schedule['last_run'] ) ) { // backward compatibility before last run tracking added. Pre v2.2.11. Eventually remove this.
		if ( 0 == $schedule['last_run'] ) {
			$last_run = '<i>' . __( 'Never', 'it-l10n-backupbuddy' ) . '</i>';
		} else {
			$last_run = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $schedule['last_run'] ) );
		}
	} else { // backward compatibility for before last run tracking was added.
		$last_run = '<i> ' . __( 'Unknown', 'it-l10n-backupbuddy' ) . '</i>';
	}

	// Determine next run.
	$next_run = backupbuddy_core::next_scheduled( 'run_scheduled_backup', array( (int) $schedule_id ), BackupBuddy_Schedules::CRON_GROUP );
	if ( false === $next_run && ! BackupBuddy_Schedules::schedule_is_disabled( $schedule_id ) ) {
		$next_run = '<font color=red>Error: Cron event not found</font>';
		pb_backupbuddy::alert( 'Error #874784. WordPress scheduled cron event not found. See "Next Run" time in the schedules list below for problem schedule. This may be caused by a conflicting plugin deleting the schedule or manual deletion. Try editing or deleting and re-creating the schedule.', true );
	} else {
		$next_run = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $next_run ) );
	}

	if ( BackupBuddy_Schedules::schedule_is_disabled( $schedule_id ) ) {
		$next_run = __( 'Disabled', 'it-l10n-backupbuddy' );
	}

	$run_time = wp_kses_post(
		sprintf(
			__( 'First run: %1$s<br>Last run: %2$s<br>Next run: %3$s', 'it-l10n-backupbuddy' ),
			$first_run,
			$last_run,
			$next_run
		)
	);

	$schedules[ $schedule_id ] = array(
		$title,
		$type,
		$interval,
		$destinations,
		$run_time,
		$on_off
	);

} // End foreach.

$data['schedules'] = $schedules;

// Load view.
pb_backupbuddy::load_view( 'scheduling', $data );
