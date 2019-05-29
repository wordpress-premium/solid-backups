<?php
/**
 * Various BackupBuddy Filters
 *
 * @package BackupBuddy
 */

/**
 * Class to house a few different filters.
 *
 * @todo Separate these into functions.
 */
class pb_backupbuddy_filters extends pb_backupbuddy_filterscore {

	/**
	 * Adds in additional scheduling intervals into WordPress such as weekly, twice monthly, monthly, etc.
	 *
	 * @param array $schedules  Array of existing schedule intervals already registered with WordPress. Handles missing param or not being an array.
	 *
	 * @return array  Array containing old and new schedule intervals.
	 */
	public function cron_schedules( $schedules = array() ) {

		$itbub_legacy_schedules = array(
			'quarterdaily'  => array(
				'interval' => 21600,
				'display'  => __( 'Every Six Hours', 'it-l10n-backupbuddy' ),
			),
			'twicedaily'    => array(
				'interval' => 43200,
				'display'  => __( 'Twice Daily', 'it-l10n-backupbuddy' ),
			),
			'everyotherday' => array(
				'interval' => 172800,
				'display'  => __( 'Every Other Day', 'it-l10n-backupbuddy' ),
			),
			'twiceweekly'   => array(
				'interval' => 302400,
				'display'  => __( 'Twice Weekly', 'it-l10n-backupbuddy' ),
			),
			'weekly'        => array(
				'interval' => 604800,
				'display'  => __( 'Once Weekly', 'it-l10n-backupbuddy' ),
			),
			'twicemonthly'  => array(
				'interval' => 1296000,
				'display'  => __( 'Twice Monthly', 'it-l10n-backupbuddy' ),
			),
			'monthly'       => array(
				'interval' => 2592000,
				'display'  => __( 'Once Monthly', 'it-l10n-backupbuddy' ),
			),
			'quarterly'     => array(
				'interval' => 7889225,
				'display'  => __( 'Every Three Months', 'it-l10n-backupbuddy' ),
			),
			'twiceyearly'   => array(
				'interval' => 15778450,
				'display'  => __( 'Twice Yearly', 'it-l10n-backupbuddy' ),
			),
			'yearly'        => array(
				'interval' => 31556900,
				'display'  => __( 'Once Yearly', 'it-l10n-backupbuddy' ),
			),
		);

		$itbub_private_schedules = array(
			'itbub-hourly'        => array(
				'interval' => 3600,
				'display'  => __( 'Once Hourly', 'it-l10n-backupbuddy' ),
			),
			'itbub-quarterdaily'  => array(
				'interval' => 21600,
				'display'  => __( 'Every Six Hours', 'it-l10n-backupbuddy' ),
			),
			'itbub-twicedaily'    => array(
				'interval' => 43200,
				'display'  => __( 'Twice Daily', 'it-l10n-backupbuddy' ),
			),
			'itbub-daily'         => array(
				'interval' => 86400,
				'display'  => __( 'Once Daily', 'it-l10n-backupbuddy' ),
			),
			'itbub-everyotherday' => array(
				'interval' => 172800,
				'display'  => __( 'Every Other Day', 'it-l10n-backupbuddy' ),
			),
			'itbub-twiceweekly'   => array(
				'interval' => 302400,
				'display'  => __( 'Twice Weekly', 'it-l10n-backupbuddy' ),
			),
			'itbub-weekly'        => array(
				'interval' => 604800,
				'display'  => __( 'Once Weekly', 'it-l10n-backupbuddy' ),
			),
			'itbub-twicemonthly'  => array(
				'interval' => 1296000,
				'display'  => __( 'Twice Monthly', 'it-l10n-backupbuddy' ),
			),
			'itbub-monthly'       => array(
				'interval' => 2592000,
				'display'  => __( 'Once Monthly', 'it-l10n-backupbuddy' ),
			),
			'itbub-quarterly'     => array(
				'interval' => 7889225,
				'display'  => __( 'Every Three Months', 'it-l10n-backupbuddy' ),
			),
			'itbub-twiceyearly'   => array(
				'interval' => 15778450,
				'display'  => __( 'Twice Yearly', 'it-l10n-backupbuddy' ),
			),
			'itbub-yearly'        => array(
				'interval' => 31556900,
				'display'  => __( 'Once Yearly', 'it-l10n-backupbuddy' ),
			),
		);

		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}

		return array_merge( $schedules, $itbub_private_schedules, $itbub_legacy_schedules );
	} // End cron_schedules().

	/**
	 * Adds Support & Documentation links to Plugin hover actions.
	 *
	 * @param array  $plugin_meta  Array of plugin meta.
	 * @param string $plugin_file  Path to plugin file.
	 *
	 * @return array $plugin_meta Now modified.
	 */
	public function plugin_row_meta( $plugin_meta, $plugin_file ) {
		if ( isset( $plugin_meta[2] ) && strstr( $plugin_meta[2], 'backupbuddy' ) ) {
			$plugin_meta[] = '<a href="https://ithemeshelp.zendesk.com/hc/en-us/categories/200062300-BackupBuddy/" target="_blank">' . __( 'Documentation', 'it-l10n-backupbuddy' ) . '</a>';
			$plugin_meta[] = '<a href="http://ithemes.com/support/" target="_blank">' . __( 'Support', 'it-l10n-backupbuddy' ) . '</a>';

			return $plugin_meta;
		} else {
			return $plugin_meta;
		}
	} // End plugin_row_meta().

} // End class pb_backupbuddy_filters.
