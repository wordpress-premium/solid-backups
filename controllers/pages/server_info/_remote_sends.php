<?php
/**
 * Remote Sends Server Info Content
 * INPUT:  $troubleshooting  optional (true) to put in Live troubleshooting text mode.
 * OUTPUT: $sends            populated
 *
 * @package BackupBuddy
 */

if ( ! isset( $troubleshooting ) ) {
	$troubleshooting = false;
}

$remote_sends     = array();
$send_fileoptions = pb_backupbuddy::$filesystem->glob_by_date( backupbuddy_core::getLogDirectory() . 'fileoptions/send-*.txt' );
if ( ! is_array( $send_fileoptions ) ) {
	$send_fileoptions = array();
}

foreach ( $send_fileoptions as $send_fileoption ) {

	$send_id = str_replace( '.txt', '', str_replace( 'send-', '', basename( $send_fileoption ) ) );

	pb_backupbuddy::status( 'details', 'About to load fileoptions data.' );
	require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
	pb_backupbuddy::status( 'details', 'Fileoptions instance #233.' );
	$fileoptions_obj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt', $read_only = true, $ignore_lock = true, $create_file = false );
	if ( true !== ( $result = $fileoptions_obj->is_ok() ) ) {
		pb_backupbuddy::status( 'error', __( 'Fatal Error #9034.233239333. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
		return false;
	}
	pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );

	$remote_sends[ $send_id ] = $fileoptions_obj->options;
	unset( $fileoptions_obj );

}

$sends = array();
foreach ( $remote_sends as $send_id => $remote_send ) {
	// Corrupt fileoptions file. Skip.
	if ( ! isset( $remote_send['start_time'] ) ) {
		continue;
	}

	// Set up some variables based on whether file finished sending yet or not.
	if ( $remote_send['finish_time'] > 0 ) { // Finished sending.
		$time_ago    = pb_backupbuddy::$format->time_ago( $remote_send['finish_time'] ) . ' ago; <b>took ';
		$duration    = pb_backupbuddy::$format->time_duration( $remote_send['finish_time'] - $remote_send['start_time'] ) . '</b>';
		$finish_time = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $remote_send['finish_time'] ) );
	} else { // Did not finish (yet?).
		$time_ago    = pb_backupbuddy::$format->time_ago( $remote_send['start_time'] ) . ' ago; <b>unfinished</b>';
		$duration    = '';
		$finish_time = '<span class="description">Unknown</span> (' . $remote_send['finish_time'] . ')';
	}

	// Handle showing sent ImportBuddy (if sent).
	if ( isset( $remote_send['send_importbuddy'] ) && true === $remote_send['send_importbuddy'] ) {
		$send_importbuddy = '<br><span class="description" style="margin-left: 10px;">+ importbuddy.php</span>';
	} else {
		$send_importbuddy = '';
	}

	// Show file size (if available).
	$file_size = '';
	if ( isset( $remote_send['file_size'] ) && is_numeric( $remote_send['file_size'] ) && $remote_send['file_size'] >= 0 ) {
		$file_size = '<br><span class="description" style="margin-left: 10px;">Size: ' . pb_backupbuddy::$format->file_size( $remote_send['file_size'] ) . '</span>';
	}

	$error_details = '';
	if ( isset( $remote_send['error'] ) ) {
		$error_details = '<br><span class="description" style="
		  margin-left: 10px;
		  display: block;
		  max-width: 500px;
		  max-height: 250px;
		  overflow: scroll;
		  background: #fff;
		  padding: 10px;">' . $remote_send['error'] . '</span>';
	}

	// Status verbage & styling based on send status.
	$failed = false;
	if ( 'success' == $remote_send['status'] ) {
		$status = '<span class="pb_label pb_label-success">Success</span>';
	} elseif ( 'running' == $remote_send['status'] ) {
		$status = '<span class="pb_label pb_label-info">In progress or timed out</span>';
	} elseif ( 'timeout' == $remote_send['status'] ) {
		$failed = true;
		$status = '<span class="pb_label pb_label-error">Failed (likely timeout)</span>';
	} elseif ( 'failed' == $remote_send['status'] ) {
		$failed = true;
		$status = '<span class="pb_label pb_label-error">Failed</span>';
	} elseif ( 'aborted' == $remote_send['status'] ) {
		$failed = true;
		$status = '<span class="pb_label pb_label-warning">Aborted by user</span>';
	} elseif ( 'multipart' == $remote_send['status'] ) {
		$status = '<span class="pb_label pb_label-info">Multipart transfer</span>';
	} else {
		$status = '<span class="pb_label pb_label-important">' . ucfirst( $remote_send['status'] ) . '</span>';
	}
	if ( isset( $remote_send['_multipart_status'] ) ) {
		$status .= '<br>' . $remote_send['_multipart_status'];
	}

	// Display 'View Log' link if log available for this send.
	$log_file = backupbuddy_core::getLogDirectory() . 'status-remote_send-' . $send_id . '_' . pb_backupbuddy::$options['log_serial'] . '.txt';
	if ( false === $troubleshooting ) {
		$status .= '<div class="row-actions">';

		if ( file_exists( $log_file ) ) {
			$status .= '<a title="' . __( 'View Remote Send Log', 'it-l10n-backupbuddy' ) . '" href="' . pb_backupbuddy::ajax_url( 'remotesend_details' ) . '&send_id=' . $send_id . '&#038;TB_iframe=1&#038;width=640&#038;height=600" class="thickbox">View Log</a>';
		}

		$status .= ' | <a title="' . __( 'Remote Send Technical Details', 'it-l10n-backupbuddy' ) . '" href="' . pb_backupbuddy::ajax_url( 'send_status' ) . '&send_id=' . $send_id . '&#038;TB_iframe=1&#038;width=640&#038;height=600" class="thickbox">View Details</a>';

		if ( 'success' != $remote_send['status'] ) {
			$status .= ' | <a title="' . __( 'Force resend attempt', 'it-l10n-backupbuddy' ) . '" href="' . pb_backupbuddy::ajax_url( 'remotesend_retry' ) . '&send_id=' . $send_id . '&#038;TB_iframe=1&#038;width=640&#038;height=600" class="thickbox">Retry Send Now</a>';
		}

		$status .= '</div>';
	}

	// Determine destination.
	$destination_type = '';
	if ( isset( pb_backupbuddy::$options['remote_destinations'][ $remote_send['destination'] ] ) ) { // Valid destination.
		$destination      = pb_backupbuddy::$options['remote_destinations'][ $remote_send['destination'] ]['title'] . ' (' . pb_backupbuddy::$options['remote_destinations'][ $remote_send['destination'] ]['type'] . ')';
		$destination_type = pb_backupbuddy::$options['remote_destinations'][ $remote_send['destination'] ]['type'];
	} else { // Invalid destination - been deleted since send?
		$destination = '<span class="description">Unknown</span>';
	}

	$write_speed = '';
	if ( isset( $remote_send['write_speed'] ) && ( '' != $remote_send['write_speed'] ) ) {
		$write_speed = 'Transfer Speed: &gt; ' . pb_backupbuddy::$format->file_size( $remote_send['write_speed'] ) . '/sec<br>';
	}

	$trigger = ucfirst( $remote_send['trigger'] );
	if ( is_array( $remote_send['file'] ) ) {
		$base_file = '-' . __( 'Multiple files', 'it-l10n-backupbuddy' ) . '-';
	} else {
		$base_file = basename( $remote_send['file'] );
	}
	if ( 'remote-send-test.php' == $base_file ) {
		$base_file   = __( 'Remote destination test', 'it-l10n-backupbuddy' ) . '<br><span class="description" style="margin-left: 10px;">(Send & delete test file remote-send-test.php)</span>';
		$file_size   = '';
		$trigger     = __( 'Manual settings test', 'it-l10n-backupbuddy' );
		$destination = '<span class="description">Test settings</span>';
	}

	// Push into array.
	if ( false === $troubleshooting ) {
		$sends[] = array(
			$base_file . $file_size . $send_importbuddy . $error_details,
			$destination,
			$trigger,
			$write_speed .
				'Start: ' . pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $remote_send['start_time'] ) ) . '<br>' .
				'Finish: ' . $finish_time . '<br>' .
				'<span class="description">' . $time_ago . $duration . '</span>',
			$status,
		);
	} else { // Troubleshooting mode.
		$error = '';
		if ( isset( $remote_send['error'] ) ) {
			$error = $remote_send['error'];
		}
		$sends[] = array(
			'type'       => $destination_type,
			'file'       => $remote_send['file'],
			'failed'     => $failed, // bool if for sure failed.
			'send_speed' => $write_speed,
			'start'      => $remote_send['start_time'],
			'finish'     => $remote_send['finish_time'],
			'duration'   => $duration,
			'log_file'   => $log_file,
			'error'      => $error,
		);
	}
} // End foreach.
