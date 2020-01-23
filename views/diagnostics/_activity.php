<?php
/**
 * Activity Settings View
 *
 * @package BackupBuddy
 */

is_admin() || die( 'Access Denied.' );

// Load notifications.
$notifications = backupbuddy_core::getNotifications();

// Compile table from notifications.
$notification_table = array();

foreach ( $notifications as $notification ) {

	$slug = $notification['slug'];
	if ( true === $notification['urgent'] ) {
		$slug = '<font color="red">' . $slug . '</font>';
	}

	$additional_data = '<i>None</i>';
	if ( count( $notification['data'] ) > 0 ) {
		$additional_data = '<textarea class="backupbuddy-recent-activity-details" wrap="off">';
		foreach ( $notification['data'] as $this_key => $this_data ) {
			if ( is_array( $this_data ) ) {
				$this_data = print_r( $this_data, true );
			}
			$additional_data .= str_pad( $this_key, 15 ) . $this_data . "\n";
		}
		$additional_data .= '</textarea>';
	}

	$time  = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $notification['time'] ) );
	$time .= '<br><span class="description">' . pb_backupbuddy::$format->time_ago( $notification['time'] ) . ' ago</span>';

	$notification_table[] = array(
		$slug,
		'<b>' . $notification['title'] . '</b><br>' . esc_html( $notification['message'] ),
		$time,
		$additional_data,
	);
}

// Flip newest up top.
$notification_table = array_reverse( $notification_table );

if ( count( $notification_table ) <= 0 ) {
	printf( '<p>%s</p>', esc_html__( 'No recent activity logged yet.', 'it-l10n-backupbuddy' ) );
	return;
}

// Display table.
pb_backupbuddy::$ui->list_table(
	$notification_table, // Array of cron items set in code section above.
	array(
		'columns' => array(
			__( 'Action', 'it-l10n-backupbuddy' ),
			__( 'Title & Message', 'it-l10n-backupbuddy' ),
			__( 'When', 'it-l10n-backupbuddy' ),
			__( 'Additional Data', 'it-l10n-backupbuddy' ),
		),
		'css'     => 'width: 100%;',
	)
);
