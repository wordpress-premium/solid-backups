<?php
/**
 * Cron Server Info Page
 *
 * @package BackupBuddy
 */

?>
All scheduled WordPress tasks (CRON jobs) are listed below. Use caution when manually running or deleting scheduled CRON
jobs as plugins, themes, or WordPress itself may expect these to remain in place. WordPress will recreate any mandatory
internal CRON jobs automatically if they are removed.<br><br>
<?php
if ( is_numeric( get_option( '_transient_doing_cron' ) ) && ( get_option( '_transient_doing_cron' ) > 0 ) ) {
	$last_cron_run = pb_backupbuddy::$format->time_ago( get_option( '_transient_doing_cron' ) ) . ' ago (' . round( get_option( '_transient_doing_cron' ) ) . ')';
} else {
	$last_cron_run = __( 'Not running (completed)', 'it-l10n-backupbuddy' );
}

$date_time_formatted = current_time( pb_backupbuddy::$format->get_date_format() );
$timestamp           = current_time( 'timestamp' );
echo '<center>' . esc_html__( 'Current Time', 'it-l10n-backupbuddy' ) . ': ' . esc_html( $date_time_formatted ) . ' (' . esc_html( $timestamp ) . ')</center>';
echo '<center>' . esc_html__( 'Currently running last cron', 'it-l10n-backupbuddy' ) . ': ' . esc_html( $last_cron_run ) . '</center>';
?>
<div style="float: right; margin-bottom: 1px;">
	<a style="background-color: #ffebc6; !important;" href="<?php echo pb_backupbuddy::page_url(); ?>&clear_cron=yes&tab=3" class="button secondary-button" onClick="return confirm( '<?php esc_html_e( 'Are you sure you want to clear all cron entries? WordPress will automatically regenerate crons but some 3rd party plugins may not.', 'it-l10n-backupbuddy' ); ?>' );"><?php esc_html_e( 'Delete All Cron Entries', 'it-l10n-backupbuddy' ); ?></a>
</div>
<?php

if ( 'yes' == pb_backupbuddy::_GET( 'clear_cron' ) ) {
	delete_option( 'cron' );
	pb_backupbuddy::alert( __( 'All cron entries have been deleted.' ) );
}

$cron = get_option( 'cron' );

// Handle CRON deletions.
if ( pb_backupbuddy::_POST( 'bulk_action' ) == 'delete_cron' ) {
	if ( defined( 'PB_DEMO_MODE' ) ) {
		pb_backupbuddy::alert( 'Access denied in demo mode.', true );
	} else {
		$delete_items = pb_backupbuddy::_POST( 'items' );

		$deleted_crons = array(); // For listing in alert.
		foreach ( $delete_items as $delete_item ) {
			$cron_parts = explode( '|', $delete_item );
			$timestamp  = $cron_parts[0];
			$cron_hook  = $cron_parts[1];
			$cron_key   = $cron_parts[2];

			if ( isset( $cron[ $timestamp ][ $cron_hook ][ $cron_key ] ) ) { // Run cron.

				$cron_array = $cron[ $timestamp ][ $cron_hook ][ $cron_key ]; // Get cron array based on passed values.
				$result     = backupbuddy_core::unschedule_event( $timestamp, $cron_hook, $cron_array['args'] ); // Delete the scheduled cron.
				if ( false === $result ) {
					pb_backupbuddy::alert( 'Error #5657667675. Unable to delete CRON job. Please see your BackupBuddy error log for details.' );
				}
				$deleted_crons[] = $cron_hook . ' / ' . $cron_key; // Add deleted cron to list of deletions for display.

			} else { // Cron not found, error.
				pb_backupbuddy::alert( 'Invalid CRON job. Not found.', true );
			}
		}

		pb_backupbuddy::alert( __( 'Deleted scheduled CRON event(s):', 'it-l10n-backupbuddy' ) . '<br>' . implode( '<br>', $deleted_crons ) );
		$cron = get_option( 'cron' ); // Reset to most up to date status for cron listing below. Takes into account deletions.
	}
}

// Handle RUNNING cron jobs manually.
if ( ! empty( $_GET['run_cron'] ) ) {
	if ( defined( 'PB_DEMO_MODE' ) ) {
		pb_backupbuddy::alert( 'Access denied in demo mode.', true );
	} else {
		$cron_parts = explode( '|', pb_backupbuddy::_GET( 'run_cron' ) );
		$timestamp  = $cron_parts[0];
		$cron_hook  = $cron_parts[1];
		$cron_key   = $cron_parts[2];

		if ( isset( $cron[ $timestamp ][ $cron_hook ][ $cron_key ] ) ) { // Run cron.
			$cron_array = $cron[ $timestamp ][ $cron_hook ][ $cron_key ]; // Get cron array based on passed values.

			do_action_ref_array( $cron_hook, $cron_array['args'] ); // Run the cron job!

			pb_backupbuddy::alert( 'Ran CRON event `' . $cron_hook . ' / ' . $cron_key . '`. Its schedule was not modified.' );
		} else { // Cron not found, error.
			pb_backupbuddy::alert( 'Invalid CRON job. Not found.', true );
		}
	}
}

require '_cron.php';

// Display CRON table.
pb_backupbuddy::$ui->list_table(
	$crons, // Array of cron items set in code section above.
	array(
		'action'                  => pb_backupbuddy::page_url() . '#pb_backupbuddy_getting_started_tab_tools',
		'columns'                 => array(
			__( 'Scheduled Events', 'it-l10n-backupbuddy' ),
			__( 'Next Run', 'it-l10n-backupbuddy' ),
			__( 'Period', 'it-l10n-backupbuddy' ),
			__( 'Interval', 'it-l10n-backupbuddy' ),
			__( 'Arguments', 'it-l10n-backupbuddy' ),
		),
		'css'                     => 'width: 100%;',
		'hover_actions'           => array(
			'run_cron' => 'Run cron job now',
		),
		'bulk_actions'            => array( 'delete_cron' => 'Delete' ),
		'hover_action_column_key' => '0',
	)
);
?>

<div style="float: right; margin-bottom: 1px;">
	<a style="background-color: #ffebc6; !important;" href="<?php echo pb_backupbuddy::page_url(); ?>&clear_cron=yes&tab=3" class="button secondary-button" onClick="if ( false === confirm( '<?php _e( 'Are you sure you want to clear all cron entries? WordPress will automatically regenerate crons but some 3rd party plugins may not.', 'it-l10n-backupbuddy' ); ?>' ) ) { return false; }"><?php _e( 'Delete All Cron Entries', 'it-l10n-backupbuddy' ); ?></a>
</div>

<br><br><br><br>
<?php

// Display time intervals table.
$pretty_intervals   = array();
$schedule_intervals = wp_get_schedules();
// Need to create a "flat" array where the key is ostensibly the schedule interval
// so we can sort on the interval. Problem is that multiple schedules may have the
// same interval and that obviously doesn't work as we cannot have duplicate keys.
// We'll create a 2-dimensional array which may have multiple
// entries under the same interval and then we'll sort on the interval and then
// flatten the array. There are other solutions but this is functional for the
// basic requirement here.
foreach ( $schedule_intervals as $interval_tag => $schedule_interval ) {
	$pretty_intervals[ $schedule_interval['interval'] ][] = array(
		$schedule_interval['display'],
		$interval_tag,
		$schedule_interval['interval'],
	);
}
ksort( $pretty_intervals );
$pretty_intervals = array_reverse( $pretty_intervals );

// Now flatten the array.
$display_intervals = array();
foreach ( $pretty_intervals as $interval ) {
	if ( is_array( $interval[0] ) ) {
		// Array of arrays of schedule details.
		foreach ( $interval as $interval2 ) {
			$display_intervals[] = $interval2;
		}
	} else {
		// Just array of schedule details.
		$display_intervals[] = $interval;
	}
}

pb_backupbuddy::$ui->list_table(
	$display_intervals, // Array of cron items set in code section above.
	array(
		'columns' => array(
			__( 'Schedule Periods', 'it-l10n-backupbuddy' ),
			__( 'Tag', 'it-l10n-backupbuddy' ),
			__( 'Interval', 'it-l10n-backupbuddy' ),
		),
		'css'     => 'width: 100%;',
	)
);
echo '<br><br>';

if ( empty( $_GET['show_cron_array'] ) ) {
	?>
	<p>
		<center>
			<a href="<?php echo pb_backupbuddy::page_url(); ?>&tab=3&show_cron_array=true#pb_backupbuddy_getting_started_tab_tools" style="text-decoration: none;">
				<?php esc_html_e( 'Display CRON Debugging Array', 'it-l10n-backupbuddy' ); ?>
			</a>
		</center>
	</p>
	<?php
} else {
	echo '<br><textarea readonly="readonly" style="width: 793px;" rows="13" cols="75" wrap="off">';
	print_r( $cron );
	echo '</textarea><br><br>';
}
unset( $cron );
?>

<br>
<div class="description">
	<b>Note</b>: Due to the way schedules are triggered in WordPress your site must be accessed (frontend or admin area) for scheduled backups to occur.
	WordPress scheduled events ("crons") may be viewed or run manually in the table above</a>. A <a href="https://www.google.com/search?q=free+website+uptime&oq=free+website+uptime" target="_blank">free website uptime</a> service or <a href="https://ithemes.com/sync-pro/uptime-monitoring/" target="_blank">iThemes Sync Pro's Uptime Monitoring</a> can be used to automatically access your site regularly to help trigger scheduled actions ("crons") in cases of low site activity, with the added perk of keeping track of your site uptime.
</div>
