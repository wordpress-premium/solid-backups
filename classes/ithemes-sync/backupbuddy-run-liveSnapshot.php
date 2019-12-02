<?php
class Ithemes_Sync_Verb_Backupbuddy_Run_LiveSnapshot extends Ithemes_Sync_Verb {
	public static $name = 'backupbuddy-run-liveSnapshot';
	public static $description = 'Run a Live Snapshot, including rescan prior. Optional force if scan is in progress.';
	
	private $default_arguments = array(
	);
	
	public function run( $arguments = array() ) {
		$arguments = Ithemes_Sync_Functions::merge_defaults( $arguments, $this->default_arguments );
		
		if ( true === backupbuddy_api::runLiveSnapshot() ) {
			$status = 'ok';
			$message = 'Snapshot initiated. Scanning for changes first.';
		} else {
			$status = 'error';
			$message = 'Snapshot failed to initiate. A scan is currently in progress.';
		}
		
		return array(
			'version' => '1',
			'status' => $status,
			'message' => $message,
		);
		
	} // End run().
	
	
} // End class.
