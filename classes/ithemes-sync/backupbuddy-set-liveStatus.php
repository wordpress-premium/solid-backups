<?php
class Ithemes_Sync_Verb_Backupbuddy_Set_LiveStatus extends Ithemes_Sync_Verb {
	public static $name = 'backupbuddy-set-liveStatus';
	public static $description = 'Pause/Resume BackupBuddy Stash Live status for continuous database and/or periodic scans (files).';
	
	private $default_arguments = array(
		'pause_continuous' => '',
		'pause_periodic'   => '',
		'start_run'        => true,
	);
	
	public function run( $arguments = array() ) {
		$arguments = Ithemes_Sync_Functions::merge_defaults( $arguments, $this->default_arguments );
		
		$status = backupbuddy_api::setLiveStatus( $arguments['pause_continuous'], $arguments['pause_periodic'],$arguments['start_run'] );
		
		return array(
			'version' => '1',
			'status' => 'ok',
			'message' => 'Live status updated successfully.',
			'status' => $status,
		);
		
	} // End run().
	
	
} // End class.
