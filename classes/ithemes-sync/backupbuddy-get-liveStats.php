<?php
class Ithemes_Sync_Verb_Backupbuddy_Get_LiveStats extends Ithemes_Sync_Verb {
	public static $name = 'backupbuddy-get-liveStats';
	public static $description = 'Retrieve BackupBuddy Stash Live stats.';
	
	
	public function run( $arguments = array() ) {
		$stats = backupbuddy_api::getLiveStats();
		
		return array(
			'version' => '1',
			'status' => 'ok',
			'message' => 'Live stats retrieved successfully.',
			'stats' => $stats,
		);
		
	} // End run().
	
	
} // End class.
