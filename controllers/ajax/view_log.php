<?php
/**
 * View Log AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::$ui->ajax_header( true, true, 'backupbuddy-admin-iframe-white diagnostics-iframe');

?>
<div class="diagnostics-iframe-inner">
	<?php

$serial   = pb_backupbuddy::_GET( 'serial' );
$log_file = backupbuddy_core::getLogDirectory() . 'status-' . $serial . '_sum_' . pb_backupbuddy::$options['log_serial'] . '.txt';

if ( ! file_exists( $log_file ) ) {
	die( 'Error #858733: Log file `' . esc_html( $log_file ) . '` not found or access denied.' );
}

$lines = file_get_contents( $log_file );
$lines = explode( "\n", $lines );
?>
<textarea readonly="readonly" id="backupbuddy_messages" classwrap="off" style="min-height: 400px; height: 500px; height: 80%;">
<?php
foreach ( (array) $lines as $rawline ) {
	$line = json_decode( $rawline, true );
	if ( is_array( $line ) ) {
		$u = '';
		if ( isset( $line['u'] ) ) { // As off v4.2.15.6. TODO: Remove this in a couple of versions once old logs without this will have cycled out.
			$u = '.' . $line['u'];
		}
		echo pb_backupbuddy::$format->date( $line['time'], 'G:i:s' ) . $u . "\t\t";
		echo $line['run'] . "sec\t";
		echo $line['mem'] . "MB\t";
		echo $line['event'] . "\t";
		echo $line['data'] . "\n";
	} else {
		echo $rawline . "\n";
	}
}
?>
</textarea>
<p>
<small>Log file: <?php esc_html_e( $log_file ); ?></small>
</p>
<p>
<?php
echo '<small>Last modified: ' . pb_backupbuddy::$format->date( filemtime( $log_file ) ) . ' (' . pb_backupbuddy::$format->time_ago( filemtime( $log_file ) ) . ' ago)';
?>
</p>
</div>
<?php
pb_backupbuddy::$ui->ajax_footer();
die();
