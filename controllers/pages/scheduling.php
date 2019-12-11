<?php
/**
 * Scheduling Page
 *
 * @package BackupBuddy
 */

pb_backupbuddy::load_script( 'jquery-ui-datepicker', true ); // WP core script.
pb_backupbuddy::load_script( 'jquery-ui-slider', true ); // WP core script.
pb_backupbuddy::load_script( 'jquery-ui-timepicker-addon.min.js' );

pb_backupbuddy::load_style( 'admin.css', false ); // Plugin-specific file.
pb_backupbuddy::load_style( 'jquery_smoothness.css', false ); // Plugin-specific file.
?>

<script type="text/javascript">
jQuery(function(){

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
	foreach ( pb_backupbuddy::_POST( 'items' ) as $id ) {
		$schedule_title = pb_backupbuddy::$options['schedules'][ $id ]['title'];
		if ( backupbuddy_api::deleteSchedule( $id, true ) ) {
			$deleted_schedules[] = htmlentities( $schedule_title );
		} else {
			$schedule_deletion_errors[] = htmlentities( $schedule_title );
		}
	} // end foreach.

	if ( count( $deleted_schedules ) ) {
		pb_backupbuddy::alert( __( 'Deleted schedule(s):', 'it-l10n-backupbuddy' ) . ' ' . implode( ', ', $deleted_schedules ) );
	} elseif ( count( $schedule_deletion_errors ) ) {
		pb_backupbuddy::alert( __( 'There were problems deleting the following schedule(s):', 'it-l10n-backupbuddy' ) . ' ' . implode( ', ', $schedule_deletion_errors ), true );
	}
}
/***** END SCHEDULE DELETION */


/***** BEGIN MANUALLY RUNNING SCHEDULE */
if ( '' != pb_backupbuddy::_GET( 'run' ) ) {
	$alert_text  = __( 'Manually running scheduled backup', 'it-l10n-backupbuddy' );
	$alert_text .= ' "' . pb_backupbuddy::$options['schedules'][ pb_backupbuddy::_GET( 'run' ) ]['title'] . '" ';
	$alert_text .= __( 'in the background.', 'it-l10n-backupbuddy' ) . '<br>';
	$alert_text .= __( 'Note: If there is no site activity there may be delays between steps in the backup. Access the site or use a 3rd party service, such as a free pinging service, to generate site activity.', 'it-l10n-backupbuddy' );
	pb_backupbuddy::alert( $alert_text );
	pb_backupbuddy_cron::_run_scheduled_backup( (int) pb_backupbuddy::_GET( 'run' ) );
}
/***** END MANUALLY RUNNING SCHEDULE */

/**
 * Build Remote Destinations
 *
 * @param array $destinations_list  Array of destinations.
 *
 * @return string  HTML list of remote destinations.
 */
function bb_build_remote_destinations( $destinations_list ) {
	$remote_destinations      = explode( '|', $destinations_list );
	$remote_destinations_html = '';
	foreach ( $remote_destinations as $destination ) {
		if ( isset( $destination ) && '' != $destination ) {
			$remote_destinations_html .= '<li id="pb_remotedestination_' . $destination . '">';

			if ( ! isset( pb_backupbuddy::$options['remote_destinations'][ $destination ] ) ) {
				$remote_destinations_html .= '{destination no longer exists}';
			} else {
				$remote_destinations_html .= pb_backupbuddy::$options['remote_destinations'][ $destination ]['title'];
				$remote_destinations_html .= ' (' . backupbuddy_core::pretty_destination_type( pb_backupbuddy::$options['remote_destinations'][ $destination ]['type'] ) . ') ';
			}
			$remote_destinations_html .= '<img class="pb_remotedestionation_delete" src="' . pb_backupbuddy::plugin_url() . '/images/redminus.png" style="vertical-align: -3px; cursor: pointer;" title="' . __( 'Remove remote destination from this schedule.', 'it-l10n-backupbuddy' ) . '" />';
			$remote_destinations_html .= '</li>';
		}
	}
	$remote_destinations = '<ul id="pb_backupbuddy_remotedestinations_list" style="margin: 0;">' . $remote_destinations_html . '</ul>';
	return $remote_destinations;
}

// Derive mode (whether editing existing schedule or adding a new schedule) here for use in various conditionals
// Note: 'edit' parameter if non-empty will be the integer identifier (as a string) of the schedule being edited.
$mode = '' == pb_backupbuddy::_GET( 'edit' ) ? 'add' : 'edit';

// Define the tag prefix being used for private tags.
// Note: Probably should be a constant but we'll define here for now.
// Note: Has to be a global when done like this as we need the value in a callable.
global $private_interval_tag_prefix;
$private_interval_tag_prefix = 'itbub-';

// EDIT existing schedule.
if ( 'edit' == $mode ) {
	$data['mode_title'] = __( 'Edit Schedule', 'it-l10n-backupbuddy' );
	$savepoint          = 'schedules#' . pb_backupbuddy::_GET( 'edit' );

	// We need to know this on an edit in case we are showing only private schedule periods for selection but the
	// schedule we are editing is using a non-private schedule period - we need to include this in the list otherwise
	// we end up with defaulting to a value like 'yearly' which the user may not notice... Note in teh backup schedule
	// definition this is called the 'interval' but actually it is the tag value.
	$this_schedule_tag = pb_backupbuddy::$options['schedules'][ pb_backupbuddy::_GET( 'edit' ) ]['interval'];

	$next_run = wp_next_scheduled( 'backupbuddy_cron', array( 'run_scheduled_backup', array( (int) pb_backupbuddy::_GET( 'edit' ) ) ) );
	if ( ! is_numeric( $next_run ) || 0 == $next_run ) { // Unable to determine next runtime so just set to now.
		$next_run = time();
	}

	$first_run_value = date( 'm/d/Y h:i a', $next_run + ( get_option( 'gmt_offset' ) * 3600 ) );

	if ( '' != pb_backupbuddy::_POST( 'pb_backupbuddy_remote_destinations' ) ) {
		$destination_list = pb_backupbuddy::_POST( 'pb_backupbuddy_remote_destinations' );
	} else {
		$destination_list = pb_backupbuddy::$options['schedules'][ pb_backupbuddy::_GET( 'edit' ) ]['remote_destinations'];
	}
	$remote_destinations = bb_build_remote_destinations( $destination_list );
} else { // ADD new schedule.
	$data['mode_title'] = __( 'Add New Schedule', 'it-l10n-backupbuddy' );
	$savepoint          = false;

	// We don't actually have a schedule tag but this represents what the default is for
	// when we are adding a new bsckup schedule.
	// Note: teh value could probably be defined as a constant.
	$this_schedule_tag = $private_interval_tag_prefix . 'daily';

	$first_run_value     = date( 'm/d/Y h:i a', time() + ( ( get_option( 'gmt_offset' ) * 3600 ) + 86400 ) );
	$remote_destinations = '<ul id="pb_backupbuddy_remotedestinations_list" style="margin: 0;"></ul>';
}

$schedule_form = new pb_backupbuddy_settings( 'scheduling', $savepoint, 'edit=' . pb_backupbuddy::_GET( 'edit' ), 250 );

$schedule_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'title',
		'title' => 'Schedule name',
		'tip'   => __( 'This is a name for your reference only.', 'it-l10n-backupbuddy' ),
		'rules' => 'required',
	)
);

$profile_list = array();
foreach ( pb_backupbuddy::$options['profiles'] as $profile_id => $profile ) {
	if ( 0 == $profile_id ) {
		continue;
	} // default profile.

	if ( 'full' == $profile['type'] ) {
		$pretty_type = 'Full';
	} elseif ( 'db' == $profile['type'] ) {
		$pretty_type = 'Database Only';
	} elseif ( 'files' == $profile['type'] ) {
		$pretty_type = 'Files Only';
	} elseif ( 'plugins' == $profile['type'] ) {
		$pretty_type = 'Plugins Only';
	} elseif ( 'themes' == $profile['type'] ) {
		$pretty_type = 'Themes Only';
	} elseif ( 'media' == $profile['type'] ) {
		$pretty_type = 'Media Only';
	} else {
		$pretty_type = 'Unknown';
	}
	$profile_list[ $profile_id ] = htmlentities( $profile['title'] ) . ' (' . $pretty_type . ')';
}
$schedule_form->add_setting(
	array(
		'type'    => 'select',
		'name'    => 'profile',
		'title'   => 'Backup profile',
		'options' => $profile_list,
		'tip'     => __( 'Full backups contain all files (except exclusions) and your database. Database only backups consist of an export of your mysql database; no WordPress files or media. Database backups are typically much smaller and faster to perform and are typically the most quickly changing part of a site.', 'it-l10n-backupbuddy' ),
		'rules'   => 'required',
	)
);

/**
 * Helper functions for the schedules array manipulation and creation of the settings array
 *
 * @param array $a  Array A to compare.
 * @param array $b  Array B to compare.
 *
 * @return int  Comparison result.
 */
function itbub_sort_compare_schedules_by_interval( $a, $b ) {
	if ( $a['interval'] == $b['interval'] ) {
		return 0;
	}
	return ( $a['interval'] < $b['interval'] ) ? -1 : 1;
}

/**
 * Add tag to passed array.
 *
 * @param array  $val  Array to add tag (passed by reference).
 * @param string $key  Tag to add to array.
 */
function itbub_walk_add_tag_to_schedule_definition( &$val, $key ) {
	$val['tag'] = $key;
}

/**
 * Filter non-private schedules
 *
 * @param array $val  Schedule definition.
 *
 * @return bool  If tag matches private interval tag.
 */
function itbub_filter_non_private_schedules( $val ) {
	global $private_interval_tag_prefix;
	// If $key has $private_interval_tag_prefix return true.
	return ( 0 === strpos( $val['tag'], $private_interval_tag_prefix ) ) ? true : false;
}

/**
 * Filter private schedules
 *
 * @param array $val  Schedule definition.
 *
 * @return bool  If tag does not match private interval tag.
 */
function itbub_filter_private_schedules( $val ) {
	global $private_interval_tag_prefix;
	// If $key does not have $private_interval_tag_prefix return true.
	return ( false === strpos( $val['tag'], $private_interval_tag_prefix ) ) ? true : false;
}

/**
 * Map Schedules to settings list.
 *
 * @param array $a  Schedule definition.
 *
 * @return string  Replace placeholders with tag.
 */
function itbub_map_schedules_to_settings_list( $a ) {
	return str_replace( '%', '&nbsp;', str_pad( $a['display'], 30, '%' ) ) . '&nbsp;&nbsp;(' . $a['tag'] . ')';
}

/**
 * Map schedules to settings list without a tag.
 *
 * @param array $a  Schedule definition.
 *
 * @return string  Removes placeholders.
 */
function itbub_map_schedules_to_settings_list_without_tag( $a ) {
	return str_replace( '%', '&nbsp;', str_pad( $a['display'], 30, '%' ) );
}

/**
 * Compares schedule definitions by interval
 *
 * @param array $a  Schedule A definition to compare.
 * @param array $b  Schedule B definition to compare.
 *
 * @return int  Comparison result.
 */
function itbub_compare_schedule_definitions_by_interval( $a, $b ) {
	if ( $a['interval'] === $b['interval'] ) {
		return 0;
	}
	if ( $a['interval'] > $b['interval'] ) {
		return 1;
	}
	return -1;
}

global $before_backupbuddy_in_list;
$before_backupbuddy_in_list = true;
global $innocent_cron_schedules_function_owners;
$innocent_cron_schedules_function_owners = array( 'backupbuddy', 'ithemes-security-pro', 'better-wp-security' );

/**
 * Filter innocent cron schedules.
 *
 * @param array $val  Schedule definition.
 *
 * @return bool  Result of filter.
 */
function itbub_filter_innocent_cron_schedules_functions_owners( $val ) {
	global $before_backupbuddy_in_list;
	global $innocent_cron_schedules_function_owners;
	if ( 0 === strcmp( 'backupbuddy', $val['owner'] ) ) {
		$before_backupbuddy_in_list = false;
		return false;
	} elseif ( true === $before_backupbuddy_in_list ) {
		return false;
	} elseif ( in_array( $val['owner'], $innocent_cron_schedules_function_owners ) ) {
		return false;
	} else {
		return true;
	}
}

/**
 * Get the list of defined schedule periods - this is an array like with entries like:
 *     ['weekly'] => ( ['interval'] => 604800, ['display'] => __( 'Once Weekely' ) ).
 */
$schedule_definitions = wp_get_schedules();

// Include the tag (key) in the value to help with handling.
array_walk( $schedule_definitions, 'itbub_walk_add_tag_to_schedule_definition' );

// Derive a lists of private and non-private schedules for convenience.
$private_schedule_definitions     = array_filter( $schedule_definitions, 'itbub_filter_non_private_schedules' );
$non_private_schedule_definitions = array_filter( $schedule_definitions, 'itbub_filter_private_schedules' );

// Check if we aer seeing the expected private schedule definitions - if not then this is indicative
// of a buggy plugin tramping on defined schedules because of a bad filter definition when adding
// its own schedule defintions to the list. We will warn of this and set a flag to force the display
// of all defined schedule periods in selection list so at least sometning is available. Otherwise the
// flag wil be false and no warning will be shown.
// Note: We'll only do this if WordPress is 4.7+ because of differences in filter handling before that.
$force_show_all_schedule_definitions = empty( $private_schedule_definitions );
if ( true === $force_show_all_schedule_definitions ) {

	// Define defaults here as we may not be populating them and the empty string condition is used to
	// test whether to add additional information to the error message or not.
	$filter_functions_owners_plugin_string   = '';
	$filter_functions_owners_muplugin_string = '';
	$filter_functions_owners_theme_string    = '';

	// We'll only do this additional problem analysis in more recent versions of WordPress since
	// handling of filtering was updated (the WP_Hook class was added).
	// Note: Whilst it is not recommended to iterate over callbacks it is not prohibited and in
	// this kind of usage case is essential because we need to see and use the details.
	if ( class_exists( 'WP_Hook' ) ) {

		// Try and find all the functions that are called as filters for the wp_get_schedules() function
		// which uses the cron_schedules() filter.
		global $wp_filter;
		$filter_functions        = array();
		$filter_functions_owners = array();

		// Something about "cron_schedules" in the same string is breaking phpcs.
		$index_key  = 'cron_';
		$index_key .= 'schedules';

		if ( isset( $wp_filter[ $index_key ] ) ) {

			$cron_schedules_filters = $wp_filter[ $index_key ];
			$cron_schedules_filters->rewind();
			while ( $cron_schedules_filters->valid() ) {
				// Get array of functions at current priority level.
				$filter_functions_list = $cron_schedules_filters->current();

				// Add to "flat" list as we are not concerned with priority as such
				// although order of application is important. Filters will be applied
				// by high->low priority and by addition order at same priority. As we
				// iterate through in the order of application this means we can
				// disregard any filters applied _before_ the BackupBuddy filter.
				foreach ( $filter_functions_list as $filter_function ) {
					$filter_functions[] = $filter_function;
				}
				$cron_schedules_filters->next();
			}

			// Now the tricky bit - is function an object->method or a simple
			// function. We'll get the filename of the object or simple function
			// and then determine the owning plugin.
			foreach ( $filter_functions as $function ) {
				$func = '';
				$fn   = '';
				$func = $function['function'];
				if ( is_array( $func ) ) {
					// The filter callable should be defined as a object->method.
					$obj = $func[0];
					if ( is_object( $obj ) ) {
						$robj = new ReflectionObject( $obj );
						$fn   = $robj->getFileName();
					}
				} elseif ( is_callable( $func ) ) {
					// The filter callable is defined as a simple function.
					$rfunc = new ReflectionFunction( $func );
					$fn    = $rfunc->getFileName();
				}

				// Figure out the "owner" of a function and whether it is a theme or
				// plugin for the purposes reporting to user.
				if ( 0 === strpos( $fn, get_stylesheet_directory() ) ) {
					$owner                             = strtolower( array_pop( explode( '/', get_stylesheet_directory() ) ) );
					$filter_functions_owners[ $owner ] = array(
						'owner'    => $owner,
						'function' => $func,
						'type'     => 'theme',
						'suspect'  => false,
					);
				} elseif ( 0 === strpos( $fn, str_replace( plugin_basename( __FILE__ ), '', __FILE__ ) ) ) {
					$owner                             = strtolower( array_shift( explode( '/', plugin_basename( $fn ) ) ) );
					$filter_functions_owners[ $owner ] = array(
						'owner'    => $owner,
						'function' => $func,
						'type'     => 'plugin',
						'suspect'  => false,
					);
				} else {
					// This is a bit of a kludge - if not a theme or plugin we assume it must
					// be an mu-plugin. Note that we have no easy way to test against the mu-plugin
					// directory path as we haev no easy way to determine what that it. However
					// plugin_basename() works for both plugins and mu-plugins so we can use it
					// to determine the "owner".
					$owner                             = strtolower( array_shift( explode( '/', plugin_basename( $fn ) ) ) );
					$filter_functions_owners[ $owner ] = array(
						'owner'    => $owner,
						'function' => $func,
						'type'     => 'muplugin',
						'suspect'  => false,
					);
				}
			}

			// As the list is in filter application order based on priority we can remove all
			// plugins up to an including BackupBuddy from the list as the schedule definitions
			// must have been tramped on by a plugin filter applied after they were added to
			// the schedule array by BackupBuddy. Also remove some other plugins that we know
			// to be innocent.
			$filtered_filter_functions_owners = array_filter( $filter_functions_owners, 'itbub_filter_innocent_cron_schedules_functions_owners' );

			// Now actively test each possible suspect function
			// Note: we have already determined that the funciton is either object->method
			// or simple function - the cal_user_func() function will figure it out from
			// whether the function it gets passed is as an array or simple function name.
			foreach ( $filtered_filter_functions_owners as $owner => &$params ) {
				$a = call_user_func(
					$params['function'], array(
						'itbub_test_tag' => array(),
					)
				);
				if ( ! key_exists( 'itbub_test_tag', $a ) ) {
					$params['suspect'] = true;
				}
			}
			unset( $params );

			// Now turn remaining aray into nice list for display in error message.
			foreach ( $filtered_filter_functions_owners as $owner => $params ) {
				if ( 'plugin' === $params['type'] ) {
					$filter_functions_owners_plugin_string .= $params['owner'];
					if ( true === $params['suspect'] ) {
						$filter_functions_owners_plugin_string .= ' (most likely), ';
					} else {
						$filter_functions_owners_plugin_string .= ', ';
					}
				} elseif ( 'theme' === $params['type'] ) {
					$filter_functions_owners_theme_string .= $params['owner'];
					if ( true === $params['suspect'] ) {
						$filter_functions_owners_theme_string .= ' (most likely), ';
					} else {
						$filter_functions_owners_theme_string .= ', ';
					}
				} elseif ( 'muplugin' === $params['type'] ) {
					$filter_functions_owners_muplugin_string .= $params['owner'];
					if ( true === $params['suspect'] ) {
						$filter_functions_owners_muplugin_string .= ' (most likely), ';
					} else {
						$filter_functions_owners_muplugin_string .= ', ';
					}
				}
			}

			// Trim off any extra stuff from the final entry in each of the lists.
			$filter_functions_owners_plugin_string   = rtrim( $filter_functions_owners_plugin_string, ', ' );
			$filter_functions_owners_muplugin_string = rtrim( $filter_functions_owners_muplugin_string, ', ' );
			$filter_functions_owners_theme_string    = rtrim( $filter_functions_owners_theme_string, ', ' );
		}
	}

	// Display an error indicating the detected problem and advice on how to investigate.
	// Note: for more recent WordPress versions we will also have actively tested relevant callback
	// functions and may have generated a suspect list. In the list is not empty then that text
	// will be added at the end of the error message as additional diagnostic information.
	$warning  = __( 'Error: The expected BackupBuddy defined schedule intervals are not present in the list provided by WordPress.', 'it-l10n-backupbuddy' );
	$warning .= __( 'This is indicative of another plugin or the theme having a bug that causes schedule intervals defined ', 'it-l10n-backupbuddy' );
	$warning .= __( 'by other plugin(s) to be handled incorrectly and discarded. The problem plugin can be identified by ', 'it-l10n-backupbuddy' );
	$warning .= __( 'selectively deactivating suspected plugins until the expected schedule interval definitions appear (and ', 'it-l10n-backupbuddy' );
	$warning .= __( 'this error message disappears) and the developers of the problem plugin can be contacted to fix the ', 'it-l10n-backupbuddy' );
	$warning .= __( 'issue. Sometimes the bad functionality can be part of theme functionality and it may be necessary to ', 'it-l10n-backupbuddy' );
	$warning .= __( 'temporarily switch to a default theme to identify this if that is possible. Whilst this site issue ', 'it-l10n-backupbuddy' );
	$warning .= __( 'exists the schedule interval selection list will show all available schedule intervals regardless of ', 'it-l10n-backupbuddy' );
	$warning .= __( 'the configuration setting but will revert to only showing the BackupBuddy defined schedule intervals ', 'it-l10n-backupbuddy' );
	$warning .= __( 'when the issue is fixed (dependent on the configuration setting). ', 'it-l10n-backupbuddy' );
	$warning .= empty( $filter_functions_owners_plugin_string ) ? '' : '<br>' . __( 'Check the following plugin(s) as possible cause of problem: ', 'it-l10n-backupbuddy' ) . $filter_functions_owners_plugin_string . '. ';
	$warning .= empty( $filter_functions_owners_muplugin_string ) ? '' : '<br>' . __( 'Check the following mu-plugin(s) as possible cause of problem: ', 'it-l10n-backupbuddy' ) . $filter_functions_owners_muplugin_string . '. ';
	$warning .= empty( $filter_functions_owners_theme_string ) ? '' : '<br>' . __( 'Check the following theme as possible cause of problem: ', 'it-l10n-backupbuddy' ) . $filter_functions_owners_theme_string . '. ';
	pb_backupbuddy::alert( $warning, true );
}

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
	$a                        = array_uintersect( $private_schedule_definitions, $this_schedule_definition, 'itbub_compare_schedule_definitions_by_interval' );
	$this_schedule_definition = empty( $a ) ? $this_schedule_definition : $a;
	// The schedule definition may have changed to a private one so we update the tag in case.
	$this_schedule_tag = key( $this_schedule_definition );
}

// This wil come from an option on the Settings page.
$show_all_schedule_definitions = pb_backupbuddy::$options['show_all_cron_schedules'];

// Now if we are only showing private schedules we'll use that as the base set and merge
// in what may be a non-private schedule if we are editing such and there was no private schedule
// with same interval. If we found a private schedule equivalent then we will be merging that
// into the existing private schedule definitions so effectively a noop
// If showing all schedules then no need to do anything as the existing list is all inclusive.
// Note that we may be forcing all shedule definitions to be shown based on the flag
// set earlier.
if ( false === ( $show_all_schedule_definitions || $force_show_all_schedule_definitions ) ) {
	$schedule_definitions = array_merge( $private_schedule_definitions, $this_schedule_definition );
}

// Now we have the final list of schedules we are going to show so let's sort by
// interval, then reverse to go from longest to shortest interval.
uasort( $schedule_definitions, 'itbub_sort_compare_schedules_by_interval' );
$schedule_definitions = array_reverse( $schedule_definitions );

// Now we create the actual array to pass to the selection list which will be an array
// of tag=>display where tag is the key from the original schedules aray.
// If there are non-private schedules in the list then the dispay is a string made up of
// the display value from the original schedules array and the tag string which can go some
// way to determining what may have defined the schedule period.
// If the list contains only private tags then we'll only show the schedule display value.
$a = array_filter( $schedule_definitions, 'itbub_filter_private_schedules' );
if ( empty( $a ) ) {
	// No non-private schedules in the list - no need to show tag.
	$intervals = array_map( 'itbub_map_schedules_to_settings_list_without_tag', $schedule_definitions );
} else {
	// One or more non-private schedules in the list - show tag to try and avoid confusion.
	$intervals = array_map( 'itbub_map_schedules_to_settings_list', $schedule_definitions );
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
		'title'   => 'Backup interval',
		'options' => $intervals,
		'default' => $default_interval,
		'tip'     => __( 'Time period between backups.', 'it-l10n-backupbuddy' ),
		'rules'   => 'required',
		'after'   => '&nbsp;&nbsp;&nbsp;<span class="pb_label">Tip</span> Unsure how often to schedule? Try starting with daily database-only and weekly full backups.',
	)
);

$schedule_form->add_setting(
	array(
		'type'    => 'text',
		'name'    => 'first_run',
		'title'   => 'Date/time of next run',
		'tip'     => __( 'IMPORTANT: For scheduled events to occur someone (or you) must visit this site on or after the scheduled time. If no one visits your site for a long period of time some backup events may not be triggered.', 'it-l10n-backupbuddy' ),
		'rules'   => 'required',
		'default' => $first_run_value,
		'after'   => ' ' . __( 'Currently', 'it-l10n-backupbuddy' ) . ' <code>' . date( 'm/d/Y h:i a ' . get_option( 'gmt_offset' ), time() + ( get_option( 'gmt_offset' ) * 3600 ) ) . ' UTC</code> ' . __( 'based on', 'it-l10n-backupbuddy' ) . ' <a href="' . admin_url( 'options-general.php' ) . '">' . __( 'WordPress settings', 'it-l10n-backupbuddy' ) . '</a>.',
	)
);

$schedule_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'remote_destinations',
		'title' => 'Remote backup destination(s)',
		'rules' => '',
		'css'   => 'display: none;',
		'after' => $remote_destinations . '<a href="' . pb_backupbuddy::ajax_url( 'destination_picker' ) . '&selecting=1&#038;TB_iframe=1&#038;width=640&#038;height=600" class="thickbox button secondary-button" style="margin-top: 3px;" title="' . __( 'Select a Destination', 'it-l10n-backupbuddy' ) . '">' . __( '+ Add Remote Destination', 'it-l10n-backupbuddy' ) . '</a>',
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
		if ( ! isset( $submitted_schedule['data']['on_off'] ) || '1' == $submitted_schedule['data']['on_off'] ) {
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
				$schedule_form->set_value( 'on_off', 1 );
				pb_backupbuddy::alert( 'Added new schedule `' . htmlentities( $submitted_schedule['data']['title'] ) . '`.' );
			}
		}
	} else { // EDIT SCHEDULE. Form handles saving; just need to update timestamp.
		$first_run = pb_backupbuddy::$format->unlocalize_time( strtotime( $submitted_schedule['data']['first_run'] ) );
		if ( 0 == $first_run || 18000 == $first_run ) {
			pb_backupbuddy::alert( sprintf( __( 'Invalid time format. Please use the specified format / example %s', 'it-l10n-backupbuddy' ), $date_format_example ) );
			$error = true;
		}

		pb_backupbuddy::$options['schedules'][ pb_backupbuddy::_GET( 'edit' ) ]['first_run'] = $first_run;

		$next_scheduled_time = wp_next_scheduled( 'backupbuddy_cron', array( 'run_scheduled_backup', array( (int) $_GET['edit'] ) ) );
		$result              = backupbuddy_core::unschedule_event(
			$next_scheduled_time, 'backupbuddy_cron', array(
				'run_scheduled_backup',
				array( (int) $_GET['edit'] ),
			)
		); // Remove old schedule.
		if ( false === $result ) {
			pb_backupbuddy::alert( 'Error #589689. Unable to unschedule scheduled cron job with WordPress. Please see your BackupBuddy error log for details.' );
		}
		pb_backupbuddy::log( 'details', 'Attempting to scheudle with the following parameters: ' . print_r( $first_run, true ) . print_r( $submitted_schedule['data']['interval'], true ). print_r( $_GET['edit'], true ) );
		$result = backupbuddy_core::schedule_event( $first_run, $submitted_schedule['data']['interval'], 'run_scheduled_backup', array( (int) $_GET['edit'] ) ); // Add new schedule.
		if ( false === $result ) {
			pb_backupbuddy::alert( 'Error scheduling event with WordPress. Your schedule may not work properly. Please try again. Error #3488439. Check your BackupBuddy error log for details.', true );
		}
		pb_backupbuddy::save();

		$edited_schedule = $submitted_schedule['data'];
		backupbuddy_core::addNotification( 'schedule_updated', 'Backup schedule updated', 'An existing schedule "' . $edited_schedule['title'] . '" has been updated.', $edited_schedule );
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
	if ( isset( $schedule['on_off'] ) && '0' == $schedule['on_off'] ) {
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
	$next_run = wp_next_scheduled( 'backupbuddy_cron', array( 'run_scheduled_backup', array( (int) $schedule_id ) ) );
	if ( false === $next_run ) {
		$next_run = '<font color=red>Error: Cron event not found</font>';
		pb_backupbuddy::alert( 'Error #874784. WordPress scheduled cron event not found. See "Next Run" time in the schedules list below for problem schedule. This may be caused by a conflicting plugin deleting the schedule or manual deletion. Try editing or deleting and re-creating the schedule.', true );
	} else {
		$next_run = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $next_run ) );
	}

	$run_time = 'First run: ' . $first_run . '<br>' .
		'Last run: ' . $last_run . '<br>' .
		'Next run: ' . $next_run;

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
