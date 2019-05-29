<?php
if ( ! defined( 'PB_IMPORTBUDDY' ) || ( true !== PB_IMPORTBUDDY ) ) {
	die( '<html></html>' );
}
Auth::require_authentication(); // Die if not logged in.
pb_backupbuddy::load_view( '_iframe_header');
pb_backupbuddy::set_greedy_script_limits();
echo "<script>pageTitle( 'Step 6: Cleanup & Completion' );</script>";
echo "<script>bb_showStep( 'cleanupSettings' );</script>";
pb_backupbuddy::flush();


if ( 'true' != pb_backupbuddy::_GET( 'deploy' ) ) { // deployment mode pre-loads state data in a file instead of passing via post.
	// Parse submitted restoreData restore state from previous step.
	$restoreData = pb_backupbuddy::_POST( 'restoreData' );
	
	
	// Decode submitted data, reporting details on failure.
	$decodeFailReason = '';
	if ( false === ( $restoreData = base64_decode( $restoreData ) ) ) { // false if failed
		$decodeFailReason = 'ERROR #83893b: Restore halted. Unable to base64_decode() submitted form data `' . htmlentities( pb_backupbuddy::_POST( 'restoreData' ) ) . '`.';
	} else { // Success.
		$restoreData = urldecode( $restoreData );
		if ( null === ( $restoreData = json_decode( $restoreData, true ) ) ) { // null if failed
			$message = 'ERROR #83893c: Restore halted. Unable to decode JSON restore base64 decoded data `' . htmlentities( base64_decode( pb_backupbuddy::_POST( 'restoreData' ) ) ) . '`. Before base64 decode: `' . htmlentities( pb_backupbuddy::_POST( 'restoreData' ) ) . '`.';
			if ( function_exists( 'json_last_error' ) ) {
		 		$message .= ' json_last_error: `' . json_last_error() . '`.';
		 	}
		 	$decodeFailReason = $message;
		} else { // Success.
			pb_backupbuddy::status( 'details', 'Success decoding submitted encoded data.' );
		}
	}
	// Report failure and fatally halt.
	if ( '' !== $decodeFailReason ) {
		pb_backupbuddy::alert( $message );
		pb_backupbuddy::status( 'error', $message );
		die();
	}
	
	
} else {
	if ( isset( pb_backupbuddy::$options['default_state_overrides'] ) && ( count( pb_backupbuddy::$options['default_state_overrides'] ) > 0 ) ) { // Default state overrides exist. Apply them.
		$restoreData = pb_backupbuddy::$options['default_state_overrides'];
		
		/*
		echo '<pre>';
		print_r( $restoreData );
		echo '</pre>';
		*/
	} else {
		die( 'Error #3278321: Missing expected default state override.' );
	}
}


// Instantiate restore class.
require_once( pb_backupbuddy::plugin_path() . '/classes/restore.php' );
$restore = new backupbuddy_restore( 'restore', $restoreData );
unset( $restoreData ); // Access via $restore->_state to make sure it is always up to date.
if ( 'true' != pb_backupbuddy::_GET( 'deploy' ) ) { // We dont accept submitted form options during deploy.
	$restore->_state = parse_options( $restore->_state );
} else { // Deployment should sleep to help give time for the source site to grab the last status log.
	sleep( 5 );
}

$footer = file_get_contents( pb_backupbuddy::$_plugin_path . '/views/_iframe_footer.php' );

if ( 'true' == pb_backupbuddy::_GET( 'deploy' ) ) {
	echo '<h5>Finished deploying pushed data & temporary file cleanup.</h5>';
}
echo "<script>bb_showStep( 'finished', " . json_encode( $restore->_state ) . " );</script>";


// Replaces sleeping 6 seconds. More reliable but uses lots of CPU.
$stop_time_limit = 6;
pb_backupbuddy::status( 'details', 'Beggining `' . $stop_time_limit . '` second sleep while files delete.' );
pb_backupbuddy::flush( true );
$t = 0; // Time = 0;
while( $t < $stop_time_limit ) {
	$now = time();
	while ( time() < ( $now + 1 ) ) { true; }
	$t++;
}

if ( '' !== $restore->_state['cleanup']['set_blog_public'] ) {
	pb_backupbuddy::status( 'details', 'Changing blog_public search engine visibility based on selected setting with value `' . $restore->_state['cleanup']['set_blog_public'] . '`.' );
	$restore->setBlogPublic( $restore->_state['cleanup']['set_blog_public'] );
} else {
	pb_backupbuddy::status( 'details', 'No change to blog_public search engine visibility based on selected setting.' );
}

if ( 'true' == pb_backupbuddy::_GET( 'deploy' ) ) { // Deployment
	pb_backupbuddy::status( 'details', 'Deployment so skipping cleanup procedures.' );
} else {
	cleanup( $restore->_state, $restore );
}
echo $footer; // We must preload the footer file contents since we are about to delete it.


if ( 'true' == pb_backupbuddy::_GET( 'deploy' ) ) { // Deployment
	pb_backupbuddy::status( 'message', 'Deployment finished (importbuddy).' );
	pb_backupbuddy::status( 'deployFinished', 'Finished.' );
} else { // Standard restore.
	pb_backupbuddy::status( 'backupbuddy_milestone', 'finish_importbuddy' );
}


// Parse submitted options/settings.
function parse_options( $restoreData ) {
	
	if ( '1' == pb_backupbuddy::_POST( 'delete_backup' ) ) {
		$restoreData['cleanup']['deleteArchive'] = true;
	} else {
		$restoreData['cleanup']['deleteArchive'] = false;
	}
	
	if ( '1' == pb_backupbuddy::_POST( 'delete_temp' ) ) {
		$restoreData['cleanup']['deleteTempFiles'] = true;
	} else {
		$restoreData['cleanup']['deleteTempFiles'] = false;
	}
	
	if ( '1' == pb_backupbuddy::_POST( 'delete_importbuddy' ) ) {
		$restoreData['cleanup']['deleteImportBuddy'] = true;
	} else {
		$restoreData['cleanup']['deleteImportBuddy'] = false;
	}
	
	if ( '1' == pb_backupbuddy::_POST( 'delete_importbuddy_directory' ) ) {
		$restoreData['cleanup']['deleteImportBuddyDirectory'] = true;
	} else {
		$restoreData['cleanup']['deleteImportBuddyDirectory'] = false;
	}
	
	if ( '1' == pb_backupbuddy::_POST( 'delete_importbuddylog' ) ) {
		$restoreData['cleanup']['deleteImportLog'] = true;
	} else {
		$restoreData['cleanup']['deleteImportLog'] = false;
	}
	
	// Search engine visibility (set_blog_public wp_options).
	if ( '1' == pb_backupbuddy::_POST( 'set_blog_public' ) ) {
		$restoreData['cleanup']['set_blog_public'] = true;
	} elseif ( '0' == pb_backupbuddy::_POST( 'set_blog_public' ) ) {
		$restoreData['cleanup']['set_blog_public'] = false;
	}
	
	return $restoreData;
}


/*	cleanup()
 *	
 *	Cleans up any temporary files left by importbuddy.
 *	
 *	@return		null
 */
function cleanup( $restoreData, $restore ) {
	pb_backupbuddy::status( 'details', 'Starting importbuddy cleanup procedures.' );
	
	// Always delete any archive primer file (.itbub) as no purpose to retain it
	remove_file( ABSPATH . '.itbub', '.itbub (archive primer file)', false );
	
	if ( true !== $restoreData['cleanup']['deleteArchive'] ) {
		pb_backupbuddy::status( 'details', 'Skipped deleting backup archive.' );
	} else {
		remove_file( $restoreData['archive'], 'backup .ZIP file (' . $restoreData['archive'] . ')', true );
	}
	
	if ( true !== $restoreData['cleanup']['deleteTempFiles'] ) {
		pb_backupbuddy::status( 'details', 'Skipped deleting temporary files.' );
	} else {
		// Full backup .sql file
		remove_file( ABSPATH . 'wp-content/uploads/temp_'. $restoreData['serial'] .'/db.sql', 'db.sql (backup database dump)', false );
		remove_file( ABSPATH . 'wp-content/uploads/temp_'. $restoreData['serial'] .'/db_1.sql', 'db_1.sql (backup database dump)', false );
		remove_file( ABSPATH . 'wp-content/uploads/backupbuddy_temp/'. $restoreData['serial'] .'/db_1.sql', 'db_1.sql (backup database dump)', false );
		// DB only sql file
		remove_file( ABSPATH . 'db.sql', 'db.sql (backup database dump)', false );
		remove_file( ABSPATH . 'db_1.sql', 'db_1.sql (backup database dump)', false );
		
		// Full backup dat file
		remove_file( ABSPATH . 'wp-content/uploads/temp_' . $restoreData['serial'] . '/backupbuddy_dat.php', 'backupbuddy_dat.php (backup data file)', false );
		remove_file( ABSPATH . 'wp-content/uploads/backupbuddy_temp/' . $restoreData['serial'] . '/backupbuddy_dat.php', 'backupbuddy_dat.php (backup data file)', false );
		// DB only dat file
		remove_file( ABSPATH . 'backupbuddy_dat.php', 'backupbuddy_dat.php (backup data file)', false );
		
		remove_file( ABSPATH . 'wp-content/uploads/backupbuddy_temp/' . $restoreData['serial'] . '/', 'Temporary backup directory', false );
		
		// Temp restore dir.
		remove_file( ABSPATH . 'importbuddy/temp_'. $restoreData['serial'] .'/', 'Temporary restore directory', false );
		
		// Remove state file (deployment/default settings).
		global $importbuddy_file;
		$importFileSerial = backupbuddy_core::get_serial_from_file( $importbuddy_file );
		$state_file = ABSPATH . 'importbuddy-' . $importFileSerial . '-state.php';
		remove_file( $state_file, 'Default state data file', false );
	}
	
	global $importbuddy_file;
	if ( true !== $restoreData['cleanup']['deleteImportBuddy'] ) {
		pb_backupbuddy::status( 'details', 'Skipped deleting ' . $importbuddy_file . ' (this script).' );
	} else {
		remove_file( ABSPATH . $importbuddy_file, $importbuddy_file . ' (this script)', true );
	}
	
	if ( true !== $restoreData['cleanup']['deleteImportBuddyDirectory'] ) {
		pb_backupbuddy::status( 'details', 'Skipped deleting importbuddy directory.' );
	} else {
		remove_file( ABSPATH . 'importbuddy/', 'ImportBuddy Directory', true );
		remove_file( ABSPATH . 'importbuddy/_settings_dat.php', '_settings_dat.php (temporary settings file)', false );
	}
	
	// Delete log file last.
	if ( true !== $restoreData['cleanup']['deleteImportLog'] ) {
		pb_backupbuddy::status( 'details', 'Skipped deleting import log (deleteImportBuddyDirectory may override).' );
	} else {
		remove_file( ABSPATH . 'importbuddy-' . pb_backupbuddy::$options['log_serial'] . '.txt', 'importbuddy-' . pb_backupbuddy::$options['log_serial'] . '.txt log file', true );
	}
}





// Used by cleanup() above.
function remove_file( $file, $description, $error_on_missing = false ) {
	pb_backupbuddy::status( 'message', 'Deleting `' . $description . '`...' );

	$mode = apply_filters( 'itbub-default-file-mode', 0755 );
	@chmod( $file, $mode ); // High permissions to delete.
	
	if ( is_dir( $file ) ) { // directory.
		pb_backupbuddy::$filesystem->unlink_recursive( $file );
		if ( file_exists( $file ) ) {
			pb_backupbuddy::status( 'error', 'Unable to delete directory: `' . $description . '`. You should manually delete it.' );
		} else {
			pb_backupbuddy::status( 'message', 'Deleted.', false ); // No logging of this action to prevent recreating log.
		}
	} else { // file
		if ( file_exists( $file ) ) {
			if ( true !== @unlink( $file ) ) {
				pb_backupbuddy::status( 'error', 'Unable to delete file: `' . $description . '`. You should manually delete it.' );
			} else {
				pb_backupbuddy::status( 'message', 'Deleted.', false ); // No logging of this action to prevent recreating log.
			}
		}
	}
} // End remove_file().


die(); // Don't want to accidently go back to any files which may be gone.

