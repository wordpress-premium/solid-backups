<?php
/**
 * Diagnostics > Cron tab Action Scheduler functions.
 *
 * @package BackupBuddy
 */

/**
 * Get the Rows for the table.
 *
 * @return array
 */
function bb_diagnostics_get_actions_rows() {
	$store   = ActionScheduler::store();
	$logger  = ActionScheduler::logger();
	$actions = bb_diagnostics_get_actions();

	return array_map( function( $id, $action ) use ( $store, $logger ) {
		return array(
			bb_diagnostics_get_action_args( $action ),
			ucwords( $store->get_status( $id ) ),
			bb_diagnostics_get_action_recurrence( $action ),
			bb_diagnostics_get_action_schedule( $action ),
			bb_diagnostics_get_action_logs( $id, $logger ),

		);
	}, array_keys( $actions ), $actions );
}

/**
 * Query for the Actions to display.
 *
 * @return array of ActionScheduler_Action objects.
 */
function bb_diagnostics_get_actions() {
	return as_get_scheduled_actions(
		array(
			'hook'   => backupbuddy_constants::CRON_HOOK,
			'status' => ActionScheduler_Store::STATUS_PENDING,
		)
	);
}

/**
 * Format the recurrence for display.
 *
 * @see ActionScheduler_ListTable::human_interval()
 *
 * @param int $interval A interval in seconds.
 * @param int $periods_to_include Depth of time periods to include, e.g. for an interval of 70, and $periods_to_include of 2, both minutes and seconds would be included. With a value of 1, only minutes would be included.
 *
 * @return string A human friendly string representation of the interval.
 */
function bb_diagnostics_human_interval( $interval, $periods_to_include = 2 ) {
	if ( $interval <= 0 ) {
		return __( 'Now!', 'it-l10n-backupbuddy' );
	}

	$time_periods = array(
		array(
			'seconds' => YEAR_IN_SECONDS,
			/* translators: %s: amount of time */
			'names'   => _n_noop( '%s year', '%s years', 'it-l10n-backupbuddy' ),
		),
		array(
			'seconds' => MONTH_IN_SECONDS,
			/* translators: %s: amount of time */
			'names'   => _n_noop( '%s month', '%s months', 'it-l10n-backupbuddy' ),
		),
		array(
			'seconds' => WEEK_IN_SECONDS,
			/* translators: %s: amount of time */
			'names'   => _n_noop( '%s week', '%s weeks', 'it-l10n-backupbuddy' ),
		),
		array(
			'seconds' => DAY_IN_SECONDS,
			/* translators: %s: amount of time */
			'names'   => _n_noop( '%s day', '%s days', 'it-l10n-backupbuddy' ),
		),
		array(
			'seconds' => HOUR_IN_SECONDS,
			/* translators: %s: amount of time */
			'names'   => _n_noop( '%s hour', '%s hours', 'it-l10n-backupbuddy' ),
		),
		array(
			'seconds' => MINUTE_IN_SECONDS,
			/* translators: %s: amount of time */
			'names'   => _n_noop( '%s minute', '%s minutes', 'it-l10n-backupbuddy' ),
		),
		array(
			'seconds' => 1,
			/* translators: %s: amount of time */
			'names'   => _n_noop( '%s second', '%s seconds', 'it-l10n-backupbuddy' ),
		),
	);

	$output = '';

	for ( $time_period_index = 0, $periods_included = 0, $seconds_remaining = $interval; $time_period_index < count( $time_periods ) && $seconds_remaining > 0 && $periods_included < $periods_to_include; $time_period_index++ ) {

		$periods_in_interval = floor( $seconds_remaining / $time_periods[ $time_period_index ]['seconds'] );

		if ( $periods_in_interval > 0 ) {
			if ( ! empty( $output ) ) {
				$output .= ' ';
			}
			$output .= sprintf( _n( $time_periods[ $time_period_index ]['names'][0], $time_periods[ $time_period_index ]['names'][1], $periods_in_interval, 'it-l10n-backupbuddy' ), $periods_in_interval );
			$seconds_remaining -= $periods_in_interval * $time_periods[ $time_period_index ]['seconds'];
			$periods_included++;
		}
	}

	return $output;
}

/**
 * Get the args markup for the action.
 *
 * @param ActionScheduler_Action $action The action.
 *
 * @return string
 */
function bb_diagnostics_get_action_args( $action ) {
	$args = $action->get_args();
	$output = '<ul>';
	foreach ( $args as $key => $value ) {
		$output .= sprintf( '<li><code>%s => %s</code></li>', esc_html( var_export( $key, true ) ), esc_html( var_export( $value, true ) ) );
	}
	$output .= '</ul>';

	return $output;
}

/**
 * Get the recurrence string for the action.
 *
 * @param ActionScheduler_Action $action The action.
 *
 * @return string
 */
function bb_diagnostics_get_action_recurrence( $action ) {
	$schedule = $action->get_schedule();
	if ( $schedule->is_recurring() && method_exists( $schedule, 'get_recurrence' ) ) {
		$recurrence = $schedule->get_recurrence();

		if ( is_numeric( $recurrence ) ) {
			/* translators: %s: time interval */
			return sprintf( __( 'Every %s', 'it-l10n-backupbuddy' ), bb_diagnostics_human_interval( $recurrence ) );
		} else {
			return $recurrence;
		}
	}

	return __( 'Non-repeating', 'it-l10n-backupbuddy' );
}

/**
 * Get the logs markup for the Action.
 *
 * @param int                    $action_id  The action ID.
 * @param ActionScheduler_Logger $logger     The Action logger.
 *
 * @return string
 */
function bb_diagnostics_get_action_logs( $action_id, $logger ) {

	$items = $logger->get_logs( $action_id );
	$log_entries_html = '<ol>';

	$timezone = new DateTimezone( 'UTC' );

	foreach ( $items as $log_entry ) {
		$log_entries_html .= bb_diagnostics_get_action_log_entry_html( $log_entry, $timezone );
	}

	$log_entries_html .= '</ol>';

	return $log_entries_html;

}

/**
 * Get the logs markup for the Action.
 *
 * @param ActionScheduler_LogEntry $log_entry  The log entry.
 * @param DateTimezone             $timezone   The timezone.
 *
 * @return string
 */
function bb_diagnostics_get_action_log_entry_html( $log_entry, $timezone ) {
	$date = $log_entry->get_date();
	$date->setTimezone( $timezone );
	return sprintf( '<li><strong>%s</strong><br/>%s</li>', esc_html( $date->format( 'Y-m-d H:i:s O' ) ), esc_html( $log_entry->get_message() ) );
}

/**
 * Get the schedule markup for the Action.
 *
 * @param ActionScheduler_Action $item The action.
 *
 * @return string
 */
function bb_diagnostics_get_action_schedule( $item ) {
	$schedule =  $item->get_schedule();

	$schedule_display_string = '';

	if ( is_a( $schedule, 'ActionScheduler_NullSchedule' ) ) {
		return __( 'async', 'it-l10n-backupbuddy' );
	}

	if ( ! method_exists( $schedule, 'get_date' ) || ! $schedule->get_date() ) {
		return '0000-00-00 00:00:00';
	}

	$next_timestamp = $schedule->get_date()->getTimestamp();

	$schedule_display_string .= $schedule->get_date()->format( 'Y-m-d H:i:s O' );
	$schedule_display_string .= '<br/>';

	if ( gmdate( 'U' ) > $next_timestamp ) {
		/* translators: %s: date interval */
		$schedule_display_string .= sprintf( __( ' (%s ago)', 'it-l10n-backupbuddy' ), bb_diagnostics_human_interval( gmdate( 'U' ) - $next_timestamp ) );
	} else {
		/* translators: %s: date interval */
		$schedule_display_string .= sprintf( __( ' (%s)', 'it-l10n-backupbuddy' ), bb_diagnostics_human_interval( $next_timestamp - gmdate( 'U' ) ) );
	}

	return $schedule_display_string;
}
