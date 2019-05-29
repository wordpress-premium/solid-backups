<?php
/**
 * Cron Server Info Page
 *
 * OUTPUT: $crons populated
 *
 * @package BackupBuddy
 */

if ( ! isset( $cron ) ) {
	$cron = get_option( 'cron' );
}
if ( ! isset( $cron_warnings ) ) {
	$cron_warnings = array();
}

// Loop through each cron time to create $crons array for displaying later.
$crons = array();
foreach ( (array) $cron as $time => $cron_item ) {
	if ( is_numeric( $time ) ) {
		// Loop through each schedule for this time.
		foreach ( (array) $cron_item as $hook_name => $event ) {

			// Don't show the itbub_cron_test task to avoid confusion.
			// It will always be present on visiting this page if we are doing an active
			// cron test and will always appear overdue because we schedule it in the past -
			// so to avoid customer confusion we hide it. It can be viewed by clicking the
			// button to see the raw cron array.
			if ( 'itbub_cron_test' == $hook_name ) {
				continue;
			}

			foreach ( (array) $event as $item_name => $item ) {

				// Determine period.
				if ( ! empty( $item['schedule'] ) ) { // Recurring schedule.
					$period          = '';
					$pretty_interval = backupbuddy_core::prettyCronInterval( $item['interval'] );
					if ( false !== $pretty_interval ) {
						$period .= '<span title="Interval tag: `' . $pretty_interval[0] . '`.">' . $pretty_interval[1] . '</span>';
					} else {
						$period .= '<span title="Interval tag: `' . $item['schedule'] . '`.">' . $item['schedule'] . '</span>';
					}
				} else { // One-time only cron.
					$period = __( 'one time only', 'it-l10n-backupbuddy' );
				}

				// Determine interval.
				if ( ! empty( $item['interval'] ) ) {
					$interval = $item['interval'] . ' seconds';
				} else {
					$interval = __( 'one time only', 'it-l10n-backupbuddy' );
				}

				// Determine arguments.
				if ( ! empty( $item['args'] ) ) {
					$arguments = '';
					foreach ( $item['args'] as $args ) {
						$arguments_inner = array();
						$is_array        = false;
						if ( ! is_array( $args ) ) {
							$arguments_inner[] = $args;
						} else {
							$is_array = true;
							foreach ( $args as $arg ) {
								if ( is_array( $arg ) ) {
									$arguments_inner[] = print_r( $arg, true );
								} else {
									$arguments_inner[] = $arg;
								}
							}
						}
						if ( true === $is_array ) {
							$arguments_inner = 'Array( ' . implode( ', ', $arguments_inner ) . ' )';
						} else {
							$arguments_inner = implode( ', ', $arguments_inner );
						}
						$arguments .= '<textarea wrap="off">' . $arguments_inner . '</textarea>';
					}
				} else {
					$arguments = __( 'none', 'it-l10n-backupbuddy' );
				}

				// If run time is in the past, note this.
				$past_time = '';
				if ( $time < time() ) {
					$warning = 'WARNING: Next run time has passed. It should have run ' . pb_backupbuddy::$format->time_ago( $time ) . ' ago. Cron problem?';
					$msg     = 'Something may be wrong with your WordPress cron such as a malfunctioning caching plugin or webhost problems.';
					if ( isset( pb_backupbuddy::$ui ) && is_object( pb_backupbuddy::$ui ) ) {
						$tip = pb_backupbuddy::$ui->tip( $msg, '', false );
					} else {
						$tip = '(' . $msg . ')';
					}
					$past_time       = '<br><span style="color: red;"> ** ' . $warning . ' ** ' . $tip . '</span>';
					$cron_warnings[] = $warning;
				}

				// Populate crons array for displaying later.
				$crons[ $time . '|' . $hook_name . '|' . $item_name ] = array(
					'<span title=\'Key: ' . $item_name . '\'>' . $hook_name . '</span>',
					pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $time ) ) . '<br><span class="description"> Timestamp: ' . $time . '</span>' . $past_time,
					$period,
					$interval,
					$arguments,
				);

			} // End foreach.
			unset( $item );
			unset( $item_name );
		} // End foreach.
		unset( $event );
		unset( $hook_name );
	} // End if is_numeric.
} // End foreach.
unset( $cron_item );
unset( $time );
