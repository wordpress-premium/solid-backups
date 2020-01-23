<?php
/**
 * Tests ACTUAL PHP maximum runtime.
 *
 * Tests the ACTUAL PHP maximum runtime of the server by echoing and logging to the status log the seconds elapsed.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$stop_time_limit = 240; // Time after which the test will stop if it is still running.
pb_backupbuddy::set_greedy_script_limits(); // Crank it up for the test!

$message = "# Starting BackupBuddy PHP Max Execution Time Tester. Determines what your ACTUAL limit is (usually shorter than the server reports so now you can find out the truth!). Stopping test if it gets to `{$stop_time_limit}` seconds. When your browser stops loading this page then the script has most likely timed out at your actual PHP limit.";
pb_backupbuddy::status( 'details', $message );
echo $message . "<br>\n";

$time = 0;
while ( $t < $stop_time_limit ) {
	pb_backupbuddy::status( 'details', 'Max PHP Execution Time Test status: ' . $time );
	echo $time . "<br>\n";
	$now = time();
	while ( time() < ( $now + 1 ) ) {
		true;
	}
	flush();
	$time++;
}

$message = '# Ending BackupBuddy PHP Max Execution Time The test was stopped as the test time limit of ' . $stop_time_limit . ' seconds.';
pb_backupbuddy::status( 'details', $message );
echo $message . "<br>\n";
die();
