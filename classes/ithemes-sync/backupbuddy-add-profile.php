<?php
class Ithemes_Sync_Verb_Backupbuddy_Add_Profile extends Ithemes_Sync_Verb {
	public static $name = 'backupbuddy-add-profile';
	public static $description = 'Add a new backup profile.';
	
	private $default_arguments = array(
		'title' => '', // User-friendly string title for convenience.
		'type'  => '', // Supported types: db, full, themes, plugins, media
	);
	
	public function run( $arguments ) {
		$arguments = Ithemes_Sync_Functions::merge_defaults( $arguments['settings'], $this->default_arguments );
		
		if ( true !== backupbuddy_api::addProfile( $arguments['title'], $arguments['type'] ) ) {
			return array(
				'api'     => '0',
				'status'  => 'error',
				'message' => 'Error #2018121803: Creating profile failed. A plugin may have blocked scheduling with WordPress. Details: ' . backupbuddy_api::$lastError,
			);
			
		} else {
			$profile_id = end( array_keys( pb_backupbuddy::$options['profiles'] ) );
			return array(
				'api'        => '0',
				'status'     => 'ok',
				'message'    => 'Profile added successfully.',
				'profile_id' => $profile_id,
			);
		}
		
	} // End run().
	
	
} // End class.
