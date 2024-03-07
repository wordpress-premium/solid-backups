<?php
/**
 * Schedules class.
 *
 * This file handles the scheduling of recurring backups.
 *
 * @package BackupBuddy
 * @since 9.1.2
 */

class BackupBuddy_Schedules {

	const CRON_GROUP = 'solid-backups-schedules';

	/**
	 * Schedule a recurring Backup.
	 *
	 * Note that we cannot use the unique parameter to schedule this, as
	 * Action Scheduler does not take the unique parameter into account,
	 * this means that anything using the backupbuddy_cron action with the same
	 * group will show up as already existing and cause an error.
	 *
	 * @since 9.1.2
	 *
	 * @param string $time         Time to schedule the backup.
	 * @param string $interval     Recurrence interval.
	 * @param string $method       Method to call when the backup is run.
	 * @param array  $action_args  Arguments to pass to the method.
	 *
	 * @return int|bool  Timestamp of the next scheduled backup, else false.
	 */
	public static function schedule_recurring_backup( $time, $interval, $method, $action_args = array( true ) ) {
		$interval = backupbuddy_core::schedule_name_to_interval( $interval );

		$is_scheduled = as_has_scheduled_action(
			backupbuddy_constants::CRON_HOOK,
			array(
				$method,
				$action_args
			),
			self::CRON_GROUP
		);

		// Already scheduled.
		if ( $is_scheduled ) {
			return as_next_scheduled_action(
				backupbuddy_constants::CRON_HOOK,
				array(
					$method,
					$action_args
				),
				self::CRON_GROUP
			);
		}

		// Attempt to schedule.
		$action_id = as_schedule_recurring_action(
			$time,
			$interval,
			backupbuddy_constants::CRON_HOOK,
			array(
				$method,
				$action_args
			),
			self::CRON_GROUP
		);

		// Success.
		if ( ! empty( $action_id ) ) {
			return as_next_scheduled_action(
				backupbuddy_constants::CRON_HOOK,
				array(
					$method,
					$action_args
				),
				self::CRON_GROUP
			);
		}

		// Wait and try again to schedule.
		sleep( backupbuddy_constants::SCHEDULE_RETRY_WAIT );

		pb_backupbuddy::status(
			'details',
			sprintf(
				__( 'Confirming cron scheduled for `%1$s` with args `%2$s`...', 'it-l10n-backupbuddy' ),
				$method,
				print_r( $action_args, true )
			)
		);

		$action_id = as_schedule_recurring_action(
			$time,
			$interval,
			backupbuddy_constants::CRON_HOOK,
			array(
				$method,
				$action_args
			),
			self::CRON_GROUP
		);

		// Success.
		if ( ! empty( $action_id ) ) {
			return as_next_scheduled_action(
				backupbuddy_constants::CRON_HOOK,
				array(
					$method,
					$action_args
				),
				self::CRON_GROUP
			);
		}

		// After retry, still failed to schedule.
		pb_backupbuddy::status(
			'error',
			sprintf(
				__( 'Error %1$s: This may not be a fatal error. Ignore if backup proceeds without other errors. WordPress reported success scheduling BUT as_has_scheduled_action() could NOT confirm schedule existence of method `%2$s` with args `%3$s.', 'it-l10n-backupbuddy' ),
				'#82938329a',
				$method,
				print_r( $action_args, true )
			)
		);

		return false;
	}

	/**
	 * Unschedule a Scheduled recurring backup.
	 *
	 * @param int $id  ID of the Scheduled Backup to unschedule.
	 *
	 * @return void
	 */
	public static function unschedule_recurring_backup( $id ) {
		$hook  = backupbuddy_constants::CRON_HOOK;
		$args  = array(
			'run_scheduled_backup',
			array( (int) $id )
		);
		$group = self::CRON_GROUP;

		$is_scheduled = as_has_scheduled_action( $hook, $args, $group );

		if ( empty( $is_scheduled ) ) {
			return;
		}

		// This returns void, so no need to return anything.
		as_unschedule_all_actions( $hook, $args, $group );
	}

	/**
	 * If the backup is scheduled.
	 *
	 * Wrapper function using Schedule-specifc data.
	 *
	 * @since 9.1.2
	 *
	 * @param int $id  ID of the Scheduled Backup to check.
	 *
	 * @return bool  True if scheduled.
	 */
	public static function backup_is_scheduled( $id ) {
		return backupbuddy_core::has_scheduled_event(
			'run_scheduled_backup',
			array( (int) $id ),
			BackupBuddy_Schedules::CRON_GROUP
		);
	}

	/**
	 * If a Scheduled backup is disabled.
	 *
	 * @param string $schedule  Schedule to check.
	 *
	 * @return bool  True if disabled.
	 */
	public static function schedule_is_disabled( $schedule_id ) {
		$schedules = pb_backupbuddy::$options['schedules'];
		if ( ! isset( $schedules[ $schedule_id ] ) ) {
			return true;
		}
		if ( ! isset( $schedules[ $schedule_id ]['on_off'] ) ) {
			return true;
		}
		return  '1' !== (string) $schedules[ $schedule_id ]['on_off'];
	}

	/**
	 * Get markup of an unordered list of schedules.
	 *
	 * @param string $schedules  Array of schedules.
	 *
	 * @return string  HTML unordered list of schedules.
	 */
	public static function build_remote_destinations( $destinations_list ) {
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
				$remote_destinations_html .= '<a class="pb_remotedestination_delete" href="#">' . esc_html__( 'Remove', 'it-l10n-backupbuddy' ) . '</a>';
				$remote_destinations_html .= '</li>';
			}
		}

		return '<ul id="pb_backupbuddy_remotedestinations_list" style="margin: 0;">' . $remote_destinations_html . '</ul>';
	}

	/**
	 * Helper functions for the schedules array manipulation and creation of the settings array
	 *
	 * @param array $a  Array A to compare.
	 * @param array $b  Array B to compare.
	 *
	 * @return int  Comparison result.
	 */
	public static function sort_compare_schedules_by_interval( $a, $b ) {
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
	public static function walk_add_tag_to_schedule_definition( &$val, $key ) {
		$val['tag'] = $key;
	}

	/**
	 * Filter private schedules
	 *
	 * @param array $val  Schedule definition.
	 *
	 * @return bool  If tag does not match private interval tag.
	 */
	public static function filter_private_schedules( $val ) {
		global $private_interval_tag_prefix;
		// If $key does not have $private_interval_tag_prefix return true.
		return ( false === strpos( $val['tag'], $private_interval_tag_prefix ) ) ? true : false;
	}

	/**
	 * Filter non-private schedules
	 *
	 * @param array $val  Schedule definition.
	 *
	 * @return bool  If tag matches private interval tag.
	 */
	public static function filter_non_private_schedules( $val ) {
		global $private_interval_tag_prefix;
		// If $key has $private_interval_tag_prefix return true.
		return ( 0 === strpos( $val['tag'], $private_interval_tag_prefix ) ) ? true : false;
	}

	/**
	 * Map Schedules to settings list.
	 *
	 * @param array $a  Schedule definition.
	 *
	 * @return string  Replace placeholders with tag.
	 */
	public static function map_schedules_to_settings_list( $a ) {
		return str_replace( '%', '&nbsp;', str_pad( $a['display'], 30, '%' ) ) . '&nbsp;&nbsp;(' . $a['tag'] . ')';
	}

	/**
	 * Map schedules to settings list without a tag.
	 *
	 * @param array $a  Schedule definition.
	 *
	 * @return string  Removes placeholders.
	 */
	public static function map_schedules_to_settings_list_without_tag( $a ) {
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
	public static function compare_schedule_definitions_by_interval( $a, $b ) {
		if ( $a['interval'] === $b['interval'] ) {
			return 0;
		}
		if ( $a['interval'] > $b['interval'] ) {
			return 1;
		}
		return -1;
	}

	/**
	 * Filter schedules by interval.
	 *
	 * @param array $val  Schedule definition.
	 *
	 * @return bool  If interval is less than or equal to the operand.
	 */
	public static function filter_schedules_by_interval( $val ) {
		global $filter_schedules_by_interval_comparison_operator;
		global $filter_schedules_by_interval_operand;
		switch( $filter_schedules_by_interval_comparison_operator ) {
			case 'lte':
				return ( $filter_schedules_by_interval_operand <= $val['interval'] );
		}
		return true;
	}
}
