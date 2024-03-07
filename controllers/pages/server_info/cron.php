<?php
/**
 * Cron Server Info Page
 *
 * @package BackupBuddy
 */

if ( class_exists( 'ActionScheduler' ) ) {
	require_once( 'action_scheduler.php' );
	require_once( '_action_scheduler.php' );
}

?>
<h3><?php esc_html_e( 'WP Cron', 'it-l10n-backupbuddy' ); ?></h3>
<?php

esc_html_e( 'All scheduled WordPress tasks (Cron jobs) are listed below. Use caution when manually running or deleting scheduled Cron
jobs as plugins, themes, or WordPress itself may expect these to remain in place. WordPress will recreate any mandatory
internal Cron jobs automatically if they are removed.', 'it-l10n-backupbuddy');
?>
<br><br>
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
<div style="float: right; margin-bottom: 1px;" class="delete-all-cron-entries">
	<a href="<?php echo esc_attr( pb_backupbuddy::page_url() ); ?>&clear_cron=yes&tab=cron" class="button secondary-button button-secondary button-flex button-attention" onClick="return confirm( '<?php echo esc_attr( __( 'Are you sure you want to clear all cron entries? WordPress will automatically regenerate crons but some 3rd party plugins may not.', 'it-l10n-backupbuddy' ) ); ?>' );"><?php esc_html_e( 'Delete All Cron Entries', 'it-l10n-backupbuddy' ); ?></a>
</div>
<?php

if ( 'yes' == pb_backupbuddy::_GET( 'clear_cron' ) ) {
	delete_option( 'cron' );
	pb_backupbuddy::alert( __( 'All cron entries have been deleted.' ) );
}

$cron = get_option( 'cron' );

// Handle Cron deletions.
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
				$result     = backupbuddy_core::unschedule_event( $cron_hook, $cron_array['args'] ); // Delete the scheduled cron.
				if ( false === $result ) {
					pb_backupbuddy::alert( 'Error #5657667675. Unable to delete Cron job. Please see your Solid Backups error log for details.' );
				}
				$deleted_crons[] = $cron_hook . ' / ' . $cron_key; // Add deleted cron to list of deletions for display.

			} else { // Cron not found, error.
				pb_backupbuddy::alert( 'Invalid Cron job. Not found.', true );
			}
		}

		pb_backupbuddy::alert( __( 'Deleted scheduled Cron event(s):', 'it-l10n-backupbuddy' ) . '<br>' . implode( '<br>', $deleted_crons ) );
		$cron = get_option( 'cron' ); // Reset to most up-to-date status for cron listing below. Takes into account deletions.
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

			pb_backupbuddy::alert( 'Ran Cron event `' . $cron_hook . ' / ' . $cron_key . '`. Its schedule was not modified.' );
		} else { // Cron not found, error.
			pb_backupbuddy::alert( 'Invalid Cron job. Not found.', true );
		}
	}
}

require '_cron.php';

// Display Cron table.
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
			'run_cron' => __( 'Run cron job now', 'it-l10n-backupbuddy' ),
		),
		'bulk_actions'            => array( 'delete_cron' => 'Delete' ),
		'hover_action_column_key' => '0',
		'wrapper_class'           => 'backupbuddy-cron-diagnostics',
	)
);
?>

<div style="float: right; margin-bottom: 1px;" class="delete-all-cron-entries bottom-button">
	<a href="<?php echo esc_attr( pb_backupbuddy::page_url() ); ?>&clear_cron=yes&tab=cron" class="button secondary-button button-secondary button-flex button-attention" onClick="return confirm( '<?php echo esc_attr( __( 'Are you sure you want to clear all cron entries? WordPress will automatically regenerate crons but some 3rd party plugins may not.', 'it-l10n-backupbuddy' ) ); ?>' );"><?php esc_html_e( 'Delete All Cron Entries', 'it-l10n-backupbuddy' ); ?></a>
</div>

<br><br><br><br>

<h3><?php esc_html_e( 'Scheduling Labels', 'it-l10n-backupbuddy' ); ?></h3>

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
			<a href="<?php echo esc_attr( pb_backupbuddy::page_url() ); ?>&tab=cron&show_cron_array=true#cron_array" style="text-decoration: none;">
				<?php esc_html_e( 'Display Cron Debugging Array', 'it-l10n-backupbuddy' ); ?>
			</a>
		</center>
	</p>
	<?php
} else {
	echo '<br><textarea readonly="readonly" id="cron_array" style="width: 793px;" rows="13" cols="75" wrap="off">';
	print_r( $cron );
	echo '</textarea><br><br>';
}
unset( $cron );
