<?php
/**
 * Recent Backups Dialog
 *
 * @package BackupBuddy
 */

// Get total recent backups for button.
$recent_backups_list = glob( backupbuddy_core::getLogDirectory() . 'fileoptions/*.txt' );
if ( ! is_array( $recent_backups_list ) ) {
	$recent_backups_list = array();
}
$recent_empty = count( $recent_backups_list ) <= 0 ? ' empty' : '';
?>
<div class="backupbuddy-recent-backups<?php echo esc_attr( $recent_empty ); ?>">
	<h3>Recent Backups</h3>
	<?php
	if ( $recent_empty ) {
		printf( '<p>%s</p>', esc_html__( 'No backups have been created recently.', 'it-l10n-backupbuddy' ) );
	} else {
		// Backup type.
		$pretty_type = array(
			'full'  => 'Full',
			'db'    => 'Database',
			'files' => 'Files',
		);

		// Read in list of backups.
		$recent_backup_count_cap = 5; // Max number of recent backups to list.
		$recent_backups          = array();
		foreach ( $recent_backups_list as $backup_fileoptions ) {
			require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
			pb_backupbuddy::status( 'details', 'Fileoptions instance #1.' );
			$read_only = true;
			$backup    = new pb_backupbuddy_fileoptions( $backup_fileoptions, $read_only );
			$result    = $backup->is_ok();
			if ( true !== $result ) {
				pb_backupbuddy::status( 'error', __( 'Unable to access fileoptions data file.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
				continue;
			}
			$backup = &$backup->options;

			if ( empty( $backup['serial'] ) || ( ! empty( $backup['fileoptions_rebuilt'] ) ) ) {
				continue;
			}

			if ( ( $backup['finish_time'] >= $backup['start_time'] ) && ( 0 != $backup['start_time'] ) ) {
				$status = '<span class="pb_label pb_label-success">Completed</span>';
			} elseif ( $backup['finish_time'] == -1 ) {
				$status = '<span class="pb_label pb_label-warning">Cancelled</span>';
			} elseif ( false === $backup['finish_time'] ) {
				$status = '<span class="pb_label pb_label-error">Failed (timeout?)</span>';
			} elseif ( ( time() - $backup['updated_time'] ) > backupbuddy_constants::TIME_BEFORE_CONSIDERED_TIMEOUT ) {
				$status = '<span class="pb_label pb_label-error">Failed (likely timeout)</span>';
			} else {
				$status = '<span class="pb_label pb_label-warning">In progress or timed out</span>';
			}
			$status .= '<br>';


			// Technical details link.
			$status .= '<div class="row-actions">';
			$status .= '<a title="' . __( 'Backup Process Technical Details', 'it-l10n-backupbuddy' ) . '" href="' . pb_backupbuddy::ajax_url( 'integrity_status' ) . '&serial=' . $backup['serial'] . '&#038;TB_iframe=1&#038;width=640&#038;height=600" class="thickbox">View Details</a>';

			$sum_log_file = backupbuddy_core::getLogDirectory() . 'status-' . $backup['serial'] . '_' . pb_backupbuddy::$options['log_serial'] . '.txt';
			if ( file_exists( $sum_log_file ) ) {
				$status .= '<div class="row-actions"><a title="' . __( 'View Backup Log', 'it-l10n-backupbuddy' ) . '" href="' . pb_backupbuddy::ajax_url( 'view_log' ) . '&serial=' . $backup['serial'] . '&#038;TB_iframe=1&#038;width=640&#038;height=600" class="thickbox">' . __( 'View Log', 'it-l10n-backupbuddy' ) . '</a></div>';
			}

			$status .= '</div>';

			// Calculate finish time (if finished).
			if ( $backup['finish_time'] > 0 ) {
				$finish_time = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $backup['finish_time'] ) ) . '<br><span class="description">' . pb_backupbuddy::$format->time_ago( $backup['finish_time'] ) . ' ago</span>';
			} else { // unfinished.
				$finish_time = '<i>Unfinished</i>';
			}

			$backup_title = '<span class="backupbuddyFileTitle" style="color: #000;" title="' . basename( $backup['archive_file'] ) . '">' . pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $backup['start_time'] ), 'l, F j, Y - g:i:s a' ) . ' (' . pb_backupbuddy::$format->time_ago( $backup['start_time'] ) . ' ago)</span><br><span class="description">' . basename( $backup['archive_file'] ) . '</span>';

			if ( isset( $backup['profile'] ) && isset( $backup['profile']['type'] ) ) {
				$backup_type = '<div>
					<span class="profile_type-' . esc_attr( $backup['profile']['type'] ) . '" style="float: left;" title="' . esc_attr( pb_backupbuddy::$format->prettify( $backup['profile']['type'], $pretty_type ) ) . '"></span>
					<span style="display: inline-block; float: left; height: 15px; border-right: 1px solid #EBEBEB; margin-left: 6px; margin-right: 6px;"></span>'
					. $backup['profile']['title'] .
				'</div>';
			} else {
				$backup_type = backupbuddy_core::pretty_backup_type( backupbuddy_core::getBackupTypeFromFile( $backup['archive_file'] ) );
				if ( '' == $backup_type ) {
					$backup_type = '<span class="description">Unknown</span>';
				}
			}

			if ( isset( $backup['archive_size'] ) && ( $backup['archive_size'] > 0 ) ) {
				$archive_size = pb_backupbuddy::$format->file_size( $backup['archive_size'] );
			} else {
				$archive_size = 'n/a';
			}

			// No integrity check for themes or plugins types.
			$raw_type = backupbuddy_core::getBackupTypeFromFile( $backup['archive_file'] );
			if ( ( 'themes' == $raw_type ) || ( 'plugins' == $raw_type ) ) {
				$status = 'n/a';
			}

			// Append to list.
			$recent_backups[ $backup['serial'] ] = array(
				array( basename( $backup['archive_file'] ), $backup_title ),
				$backup_type,
				$archive_size,
				ucfirst( $backup['trigger'] ),
				$status,
				'start_timestamp' => $backup['start_time'], // Used by array sorter later to put backups in proper order.
			);

		}

		$columns = array(
			__( 'Recently Made Backups (Start Time)', 'it-l10n-backupbuddy' ),
			__( 'Type | Profile', 'it-l10n-backupbuddy' ),
			__( 'File Size', 'it-l10n-backupbuddy' ),
			__( 'Trigger', 'it-l10n-backupbuddy' ),
			__( 'Status', 'it-l10n-backupbuddy' ) . ' <span class="description">(hover for options)</span>',
		);

		if ( ! function_exists( 'pb_backupbuddy_aasort' ) ) {
			function pb_backupbuddy_aasort( &$array, $key ) {
				$sorter = array();
				$ret    = array();
				reset( $array );
				foreach ( $array as $ii => $va ) {
					$sorter[ $ii ] = $va[ $key ];
				}
				asort( $sorter );
				foreach ( $sorter as $ii => $va ) {
					$ret[ $ii ] = $array[ $ii ];
				}
				$array = $ret;
			}
		}

		pb_backupbuddy_aasort( $recent_backups, 'start_timestamp' ); // Sort by multidimensional array with key start_timestamp.
		$recent_backups = array_reverse( $recent_backups ); // Reverse array order to show newest first.

		$recent_backups = array_slice( $recent_backups, 0, $recent_backup_count_cap ); // Only display most recent X number of backups in list.

		pb_backupbuddy::$ui->list_table(
			$recent_backups,
			array(
				'action'  => pb_backupbuddy::page_url(),
				'columns' => $columns,
				'css'     => 'width: 100%;',
			)
		);

		echo '<div class="alignright actions">';
		pb_backupbuddy::$ui->note( 'Hover over items above for additional options.' );
		echo '</div>';

	} // end if recent backups exist.
	?>
</div>
