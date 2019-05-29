<?php
require_once( pb_backupbuddy::plugin_path() . '/classes/core.php' );


class backupbuddy_remote_api {
	
	private static $_errors = array();		// Hold error strings to retrieve with getErrors().
	private static $_incomingPayload = '';
	
	public static function localCall( $keySet = false, $importbuddy = false ) {
		if ( true !== $keySet ) {
			die( '<html>403 Access Denied</html>' );
		}
		
		register_shutdown_function( array( 'backupbuddy_remote_api', 'shutdown_function' ) );
		
		header( 'Content-Type: application/octet-stream' );
		
		if ( true !== self::init_incoming_call() ) {
			$message = 'Error #8002: Error validating API call authenticity. Verify you are using the correct active API key.';
			pb_backupbuddy::status( 'error', $message );
			self::_reply( array( 'success' => false, 'error' => $message ) );
		}
		
		// If here then validation was all good. API call is authorized.
		
		if ( true !== $importbuddy ) {
			$functionName = '_verb_' . backupbuddy_core::getHttpHeader( 'backupbuddy-verb' );
		} else {
			$functionName = '_verb_importbuddy_' . backupbuddy_core::getHttpHeader( 'backupbuddy-verb' );
		}
		
		// Does verb exist?
		if ( false === method_exists( 'backupbuddy_remote_api', $functionName ) ) {
			$message = 'Error #843489974: Unknown verb `' . backupbuddy_core::getHttpHeader( 'backupbuddy-verb' ) . '`.';
			pb_backupbuddy::status( 'error', $message );
			self::_reply( array( 'success' => false, 'error' => $message ) );
		} else {
			pb_backupbuddy::status( 'details', 'Calling incoming API function `' . $functionName . '`.' );
			call_user_func_array( 'backupbuddy_remote_api::' . $functionName, array() );
		}
		
		// Cleanup
		self::$_incomingPayload = '';

		// function: verb_[VERBHERE]
	}
	
	
	
	/*	shutdown_function()
	 *	
	 *	Used for catching fatal PHP errors during backup to write to log for debugging.
	 *	
	 *	@return		null
	 */
	public static function shutdown_function() {
		
		
		// Get error message.
		// Error types: http://php.net/manual/en/errorfunc.constants.php
		$e = error_get_last();
		if ( $e === NULL ) { // No error of any kind.
			return;
		} else { // Some type of error.
			if ( !is_array( $e ) || ( $e['type'] != E_ERROR ) && ( $e['type'] != E_USER_ERROR ) ) { // Return if not a fatal error.
				return;
			}
		}
		
		
		// Calculate log directory.
		$log_directory = backupbuddy_core::getLogDirectory();
		$main_file = $log_directory . 'log-' . pb_backupbuddy::$options['log_serial'] . '.txt';
		
		
		// Determine if writing to a serial log.
		if ( pb_backupbuddy::$_status_serial != '' ) {
			$serial = pb_backupbuddy::$_status_serial;
			$serial_file = $log_directory . 'status-' . $serial . '_' . pb_backupbuddy::$options['log_serial'] . '.txt';
			$write_serial = true;
		} else {
			$write_serial = false;
		}
		
		
		// Format error message.
		$e_string = 'PHP_ERROR ' . __( 'Error #32893. Fatal PHP error encountered:', 'it-l10n-backupbuddy' );
		foreach( (array)$e as $e_line_title => $e_line ) {
			$e_string .= $e_line_title . ' => ' . $e_line . "; ";
		}
		$e_string .= ".\n";
		
		
		// Write to log.
		@file_put_contents( $main_file, $e_string, FILE_APPEND );
		if ( $write_serial === true ) {
			@file_put_contents( $serial_file, $e_string, FILE_APPEND );
		}
		
		
	} // End shutdown_function.
	
	
	
	/* remoteCall()
	 *
	 * Send an API call to a remote server.
	 * @param	array 	$remoteAPI		Remote API state array including URL, etc. Stored in destination settings.
	 * @param	string	$verb			API verb to call on remote server.
	 * @param	array 	$moreParams		Optional: Additional parameters to append to those sent.  If needing to send a non-string this should be encoded in some manner and decoded on remote.
	 * @param	int		$timeout		Optional: How long we expect this can last before a server times out.  Typically the minimum of the local and remote timeouts.
	 * @param	string	$file			Optional: File we are sending. This is passed so that various CRC data can be calculated.
	 * @param	string	$fileData		Optional: Raw file contents to send (for this chunk if using chunking). D ustin B olton
	 * @param	int		$seekTo			Optional: Location to fseek to in the file for writing.
	 * @param	bool	$isFileTest		Optional: When true the destination will auto-delete the file after testing.
	 * @param	bool	$isFileDone		Optional: Pass true when the last chunk (or only chunk) of the file is being sent so destination knows not to expect any other pieces.
	 * @param	int		$fileSize		Optional: Size of the file sending.
	 * @param	string	$filePath		Optional: Remote file path in relation to the root location where the file is being stored, based on file type (based on verb).
	 * @param	bool	$returnRaw		When true returns body raw text/data rather than decoding encoded data first.
	 *
	 */
	public static function remoteCall( $remoteAPI, $verb, $moreParams = array(), $timeout, $files = array(), $returnRaw = false ) {
		pb_backupbuddy::status( 'details', 'Preparing remote API call verb `' . $verb . '`.' );
		$now = time();
		
		$body = array();
		
		if ( ! is_numeric( $timeout ) ) {
			$timeout = backupbuddy_constants::DEPLOYMENT_REMOTE_API_DEFAULT_TIMEOUT;
		}
		pb_backupbuddy::status( 'details', 'remoteCall() HTTP wait timeout: `' . $timeout . '` seconds.' );
		
		$defaultFile = array(
			'file'    => '',
			'size'    => '',
			'seekto'  => '',
			'done'    => false,
			'test'    => false,
			'encoded' => false,
			'datalen' => 0,
			'data'    => '',
		);
		
		// Apply defaults for each file.
		foreach( $files as &$file ) {
			$file = array_merge( $defaultFile, $file );
		}
		
		$body['files'] = $files;

		if ( ! is_array( $moreParams ) ) {
			error_log( 'BackupBuddy Error #4893783447 remote_api.php; $moreParams must be passed as array.' );
		}
		$body = serialize( array_merge( $body, $moreParams ) );
		
		//print_r( $apiKey );
		$signature = md5( $now . $verb . $remoteAPI['key_public'] . $remoteAPI['key_secret'] . $body );
		
		if ( defined( 'BACKUPBUDDY_DEV' ) && ( true === BACKUPBUDDY_DEV ) ) {
			error_log( 'BACKUPBUDDY_DEV-remote api http body SEND- ' . print_r( $body, true ) );
		}
		
		$sslverify = true;
		if ( '0' == pb_backupbuddy::$options['deploy_sslverify'] ) {
			$sslverify = false;
			pb_backupbuddy::status( 'details', 'Skipping SSL cert verification based on advanced settings.' );
		}
		
		//error_log( 'connectTo: ' . $remoteAPI['siteurl'] );
		$response = wp_remote_post( rtrim( $remoteAPI['siteurl'], '/' ) . '/', array(
				'method' => 'POST',
				'timeout' => ( $timeout - 2 ),
				'redirection' => 0, // Redirect will not work since we are passing headers.
				'httpversion' => '1.0',
				'blocking' => true,
				'sslverify' => $sslverify,
				'headers' => array(
						'Referer' => $remoteAPI['siteurl'] . '/TACOS',
						'Content-Type' => 'application/octet-stream/MONKEYS', // binary
						'backupbuddy-api-key' => $remoteAPI['key_public'],
						'backupbuddy-version' => pb_backupbuddy::settings( 'version' ),
						'backupbuddy-verb' => $verb,
						'backupbuddy-now' => $now,
						'backupbuddy-signature' => $signature,
					), // Sending referer header helps prevent security blocks.
				'body' => $body, // IMPORTANT: ALWAYS for security verify signature prior to ever unserializing any incoming data.
				'cookies' => array()
			)
		);
		
		if ( is_wp_error( $response ) ) {
			return self::_error( 'Error #9037: Unable to connect to remote server or unexpected response. Details: `' . $response->get_error_message() . '` - Site URL: `' . $remoteAPI['siteurl'] . '`, Home URL: `' . $remoteAPI['homeurl'] . '`.' );
		} else {
			if ( true === $returnRaw ) {
				return $response['body'];
			}
			//error_log( '3333Response: ' . $response['body'] );
			
			if ( false !== stripos( $response['body'], 'Request Entity Too Large' ) ) {
				return self::_error( 'Error #8001b: Request Entity Too Large. The destination server says we sent too much data. Either change the Deployment Advanced Setting "Max Chunk Size" to a lower value or change the server configuration to accept a larger value. See the following webpage for the server solution for Apache, nginx, or IIS: https://craftcms.stackexchange.com/questions/2328/413-request-entity-too-large-error-with-uploading-a-file  ... Return data: `' . htmlentities( $response['body'] ) . '`.' );
			}
			
			if ( false === ( $return = @unserialize( $response['body'] ) ) ) {
				$error = "Error #8001: Unable to decode Deployment response. Things to check: 1) Verify both sites are running the latest BackupBuddy version (v8.1.1.1 introduced non-backward-compatible changes). 2) Check the remote site API URL is correct: " . $remoteAPI['siteurl'] . ". 3) If you changed the remote site API key you must update it into this site. 4) Make sure the remote site has the API enabled in its wp-config.php by adding define( 'BACKUPBUDDY_API_ENABLE', true ); somewhere ABOVE the line `That's all, stop editing!`. Verb: `" . $verb . "`. Troubleshooting: `<textarea style='width: 100%; height: 500px;' wrap='off'>" . htmlentities( print_r( $response, true ) ) . "</textarea>`.";
				
				/*
				pb_backupbuddy::add_status_serial( 'remote_api' ); // Also log all incoming remote API calls.
				pb_backupbuddy::status( 'error', 'REMOTE ERROR: ' . $error );
				pb_backupbuddy::remove_status_serial( 'remote_api' );
				*/
				
				return self::_error( $error );
			} else {
				if ( isset( $return['logs'] ) ) {
					//pb_backupbuddy::add_status_serial( 'remote_api' ); // Also log all incoming remote API calls.
					pb_backupbuddy::status( 'details', '*** Begin External Log (Remote API Call Response)' );
					foreach( $return['logs'] as $log ) {
						
						pb_backupbuddy::status( 'details', '* ' . print_r( $log, true ) );
					}
					pb_backupbuddy::status( 'details', '*** End External Log (Remote API Call Response)' );
					//pb_backupbuddy::remove_status_serial( 'remote_api' );
				}
				
				if ( ! isset( $return['success'] ) || ( true !== $return['success'] ) ) { // Fail.
					$error = '';
					if ( isset( $return['error'] ) ) {
						$error = $return['error'];
					} else {
						$error = 'Error #838438734: No error given. Full response: "' . $return . '".';
					}
					return self::_error( "Error #3289379: API did not report success. Error details: `" . $error . "`. Troubleshooting: `<textarea style='width: 100%; height: 500px;' wrap='off'>" . htmlentities( print_r( $response, true ) ) . "</textarea>`." );
				} else { // Success.
					if ( isset( $return['message'] ) ) {
						pb_backupbuddy::status( 'details', 'Response message from API: ' . $return['message'] . '".' );
					}
					return $return;
				}
			}
		}
	} // End remoteCall().
	
	
	private static function _reply( $response_arr ) {
		$response_arr['logs'] = pb_backupbuddy::get_status( 'remote_api', true, true, true ); // Array of status logs.
		
		//error_log( 'RESPONSE:' );
		//error_log( print_r( $response_arr, true ) );
		
		die( serialize( $response_arr ) );
	}
	
	
	/* _verb_runBackup()
	 *
	 * Run a backup with a specified custom profile; eg a db backup for pulling deployment.
	 * Params: POST "profile" - Base64 encoded json encoded profile array.
	 *
	 */
	private static function _verb_runBackup() {
		$backupSerial = pb_backupbuddy::random_string( 10 );
		$profileArray = self::$_incomingPayload[ 'profile' ];
		if ( false === ( $profileArray = base64_decode( $profileArray ) ) ) {
			$message = 'Error #8343728: Unable to base64 decode profile data.';
			pb_backupbuddy::status( 'error', $message, $backupSerial );
			self::_reply( array( 'success' => false, 'error' => $message ) );
		}
		if ( NULL === ( $profileArray = json_decode( $profileArray, true ) ) ) {
			$message = 'Error #3272383: Unable to json decode profile data.';
			pb_backupbuddy::status( 'error', $message, $backupSerial );
			self::_reply( array( 'success' => false, 'error' => $message ) ) ;
		}
		
		// Appends session tokens from the pulling site so they wont get logged out when this database is restored there.
		if ( isset( $profileArray['sessionTokens'] ) && ( is_array( $profileArray['sessionTokens'] ) ) ) {
			pb_backupbuddy::status( 'details', 'Remote session tokens need updated.', $backupSerial );
			//error_log( 'needtoken' );
			
			if ( ! is_numeric( $profileArray['sessionID'] ) ) {
				$message = 'Error #328989893. Invalid session ID. Must be numeric.';
				pb_backupbuddy::status( 'error', $message );
				self::_reply( array( 'success' => false, 'error' => $message ) );
			}
			
			// Get current session tokens.
			global $wpdb;
			$sql = "SELECT meta_value FROM `" . DB_NAME . "`.`" . $wpdb->prefix . "usermeta` WHERE `user_id` = '" . $profileArray['sessionID'] . "' AND `meta_key` = 'session_tokens';";
			$results = $wpdb->get_var( $sql );
			$oldSessionTokens = @unserialize( $results );
			
			// Add remote tokens.
			if ( ! is_array( $oldSessionTokens ) ) {
				$oldSessionTokens = array();
			}
			$newSessionTokens = array_merge( $oldSessionTokens, $profileArray['sessionTokens'] );
			
			// Re-serialize.
			$newSessionTokens = serialize( $newSessionTokens );
			
			// Save merged tokens here.
			$sql = "UPDATE `" . DB_NAME . "`.`" . $wpdb->prefix . "usermeta` SET meta_value= %s WHERE `user_id` = '" . $profileArray['sessionID'] . "' AND `meta_key` = 'session_tokens';";
			$stringedSessionTokens = serialize( $profileArray['sessionTokens'] );
			
			if ( false === $wpdb->query( $wpdb->prepare( $sql, $stringedSessionTokens ) ) ) {
				$message = 'Error #43734784: Unable to update remote session token.';
				pb_backupbuddy::status( 'error', $message, $backupSerial );
				self::_reply( array( 'success' => false, 'error' => $message ) );
			}
			
			pb_backupbuddy::status( 'details', 'Updated remote session tokens.', $backupSerial );
		}
		
		$maybeMessage = backupbuddy_api::runBackup( $profileArray, $triggerTitle = 'deployment_pulling', $backupMode = '', $backupSerial );
		if ( empty( $maybeMessage['success'] ) ) {
			$message = 'Error #48394873: Unable to launch backup at source. Details: `' . $maybeMessage . '`.';
			pb_backupbuddy::status( 'error', $message, $backupSerial );
			self::_reply( array( 'success' => false, 'error' => $message ) );
		} else {
			$archiveFilename = basename( backupbuddy_core::calculateArchiveFilename( $backupSerial, $profileArray['type'], $profileArray ) );
			self::_reply( array( 'success' => true, 'backupSerial' => $backupSerial, 'backupFile' => $archiveFilename ) );
		}
	} // End _verb_runBackup().
	
	
	
	private static function _verb_getBackupStatus() {
		$backupSerial = self::$_incomingPayload[ 'serial' ];
		pb_backupbuddy::status( 'details', '*** End Remote Backup Log section', $backupSerial ); // Place at end of log.
		backupbuddy_api::getBackupStatus( $backupSerial ); // echos out. Use $returnRaw = true for remote_api call for this special verb that does not return json.
		
		// Fix missing WP cron constant.
		if ( !defined( 'WP_CRON_LOCK_TIMEOUT' ) ) {
			define('WP_CRON_LOCK_TIMEOUT', 60);  // In seconds
		}
		
		if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
			update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
			spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
		}
	} // end _verb_getBackupStatus().
	
	
	
	/* _verb_confirmDeployment()
	 *
	 * User confirmed the deployment so cleanup any remaining temporary stuff such as temp db tables. Note: importbuddy, backup files, etc should have already been cleaned up by importbuddy itself at this point.
	 *
	 */
	private static function _verb_confirmDeployment() {
		
		// Remove Temp Tables
		$serial = self::$_incomingPayload[ 'serial' ];
		require_once( pb_backupbuddy::plugin_path() . '/classes/housekeeping.php' );
		backupbuddy_housekeeping::remove_temp_tables( $serial );

		// Remove importbudy Directory
		if ( file_exists( ABSPATH . 'importbuddy/' ) ) {
			pb_backupbuddy::$filesystem->unlink_recursive( ABSPATH . 'importbuddy/' );
		}

		// Remove importbuddy files
		$importbuddyFiles = glob( ABSPATH . 'importbuddy*.php' );
		if ( ! is_array( $importbuddyFiles ) ) {
			$importbuddyFiles = array();
		}
		foreach( $importbuddyFiles as $importbuddyFile ) {
			unlink( $importbuddyFile );
		}
		
		self::_reply( array( 'success' => true ) );
		
	} // End _verb_confirmDeployment().
	
	
	// Receive backup archive.
	private static function _verb_sendFile_backup() {
		self::_sendFiles( 'backup' );
	} // End _verb_sendFile_backup().
	
	
	// Receive theme file.
	private static function _verb_sendFile_theme() {
		self::_sendFiles( 'theme' );
	} // End _verb_sendFile_theme().
	
	// Receive child theme file.
	private static function _verb_sendFile_childTheme() {
		self::_sendFiles( 'childTheme' );
	} // End _verb_sendFile_childtheme().
	
	// Receive plugin file.
	private static function _verb_sendFile_plugin() {
		self::_sendFiles( 'plugin' );
	} // End _verb_sendFile_plugin().
	
	// Receive backup archive.
	private static function _verb_sendFile_media() {
		self::_sendFiles( 'media' );
	} // End _verb_sendFile_media().
	
	// Receive additional extra inclusion.
	private static function _verb_sendFile_extra() {
		self::_sendFiles( 'extra' );
	} // End _verb_sendFile_extra().
	
	// Testing file send ability. File is transient; stored in temp dir momentarily.
	private static function _verb_sendFile_test() {
		self::_sendFiles( 'test' );
	} // End _verb_sendFile_test().
	
	
	
	// Get backup archive.
	private static function _verb_getFile_backup() {
		self::_getFile( 'backup' );
	} // End _verb_getFile_backup().
	
	// Get theme file.
	private static function _verb_getFile_theme() {
		self::_getFile( 'theme' );
	} // End _verb_getFile_theme().
	
	// Get child theme file.
	private static function _verb_getFile_childTheme() {
		self::_getFile( 'childTheme' );
	} // End _verb_getFile_childTeme().
	
	// Get plugin file.
	private static function _verb_getFile_plugin() {
		self::_getFile( 'plugin' );
	} // End _verb_getFile_plugin().
	
	// Get media file.
	private static function _verb_getFile_media() {
		self::_getFile( 'media' );
	} // End _verb_getFile_media().
	
	// Get additional extra inclusion.
	private static function _verb_getFile_extra() {
		self::_getFile( 'extra' );
	} // End _verb_getFile_extra().
	
	
	
	/* _getFilePathByType()
	 *
	 * Calculates root directory to store the specified type in. Contains trailing slash. Dies if unknown file type specified in params.
	 *
	 * @param	string		$type		File type/location name to store in. Valid values: backup, media, plugin, theme.
	 *
	 */
	private static function _getFilePathByType( $type ) {
		if ( 'backup' == $type ) {
			$rootDir = backupbuddy_core::getBackupDirectory(); // Include trailing slash.
			pb_backupbuddy::anti_directory_browsing( $rootDir, $die = false );
		} elseif ( 'media' == $type ) {
			$wp_upload_dir = wp_upload_dir();
			$rootDir = $wp_upload_dir['basedir'] . '/';
			unset( $wp_upload_dir );
		} elseif ( 'plugin' == $type ) {
			$rootDir = wp_normalize_path( WP_PLUGIN_DIR ) . '/';
		} elseif ( 'theme' == $type ) {
			$rootDir = get_template_directory() . '/';
		} elseif ( 'childTheme' == $type ) {
			$rootDir = get_stylesheet_directory() . '/';
		} elseif( 'test' == $type ) {
			$rootDir = backupbuddy_core::getTempDirectory();
		} elseif( 'extra' == $type ) {
			$rootDir = ABSPATH; // includes trailing slash.
		} else {
			$error = 'Error #84934984. You must specify a sendfile type: Unknown file type `' . htmlentities( $type ) . '`.';
			pb_backupbuddy::status( 'error', $error );
			error_log( 'BackupBuddy API error: ' . $error );
			self::_reply( array( 'success' => false, 'error' => $message ) );
		}
		//error_log( 'rootDir: ' . $rootDir );
		return $rootDir;
	} // End _getFilePathByType().
	
	
	/* _getFile()
	 *
	 * Calling site is wanting to get a file FROM this site.
	 *
	 */
	private static function _getFile( $type ) {
		$rootDir = self::_getFilePathByType( $type ); // contains trailing slash.
		$filePath = stripslashes_deep( self::$_incomingPayload[ 'filename' ] );
		$fullFilename = $rootDir . $filePath;
		
		$seekTo = self::$_incomingPayload[ 'seekto' ];
		if ( ! is_numeric( $seekTo ) ) {
			$seekTo = 0;
		}
		
		$maxPayload = self::$_incomingPayload[ 'maxPayload' ]; // Max payload in bytes.
		$maxPayloadBytes = $maxPayload * 1024 * 1024;
		
		// File exist? (note: if utf8 then this first check will fail and inside we will check for the file after utf8 decoding.)
		if ( ! file_exists( $fullFilename ) ) {
			// Check if utf8 decoding the filename helps us find it.
			$utf_decoded_filename = utf8_decode( $filePath );
			if ( file_exists( $rootDir . $utf_decoded_filename ) ) {
				$fullFilename = $rootDir . $utf_decoded_filename;
			} else {
				$message = 'Error #83929838: Requested `' . $type . '` file with full path `' . $fullFilename . '` does not exist. Was it just deleted? See log for details.';
				pb_backupbuddy::status( 'error', $message );
				self::_reply( array( 'success' => false, 'error' => $message ) );
			}
		}
		
		$size = filesize( $fullFilename );
		pb_backupbuddy::status( 'details', 'File size of file to get: ' . pb_backupbuddy::$format->file_size( $size ) );
		
		if ( $size > $maxPayloadBytes ) {
			$chunksTotal = ceil( $size / $maxPayloadBytes );
			pb_backupbuddy::status( 'details', 'This file + encoding exceeds the maximum per-chunk payload size so will be read in and sent in chunks of ' . self::$_incomingPayload[ 'maxPayload' ] . 'MB (' . $maxPayloadBytes . ' bytes) totaling approximately ' . $chunksTotal . ' chunks.' );
		} else {
			pb_backupbuddy::status( 'details', 'This file + encoding does not exceed per-chunk payload size of ' . self::$_incomingPayload[ 'maxPayload' ] . 'MB (' . pb_backupbuddy::$format->file_size( $maxPayloadBytes ) . ') so sending in one pass.' );
		}
		$prevPointer = 0;
		
		pb_backupbuddy::status( 'details', 'Reading in `' . $maxPayloadBytes . '` bytes at a time.' );
		
		// Open for reading.
		if ( false === ( $fs = fopen( $fullFilename, 'rb' ) )) {
			$message = 'Error #235532: Unable to fopen file `' . $fullFilename . '`.';
			pb_backupbuddy::status( 'error', $message );
			self::_reply( array( 'success' => false, 'error' => $message ) );
		}
		
		// Seek to position (if applicable).
		if ( 0 != $seekTo ) {
			if ( 0 != fseek( $fs, $seekTo ) ) {
				@fclose( $fs );
				$message = 'Error #6464534229: Unable to fseek file.';
				pb_backupbuddy::status( 'error', $message );
				self::_reply( array( 'success' => false, 'error' => $message ) );
			}
		}
		
		$resumePoint = 0;
		$fileDone = false;
		$fileData = fread( $fs, $maxPayloadBytes );
		if ( feof( $fs ) ) {
			pb_backupbuddy::status( 'details', 'Read to end of file (feof true). No more chunks left after this.' );
			$fileDone = true;
		} else {
			if ( FALSE === ( $resumePoint = ftell( $fs ) ) ) {
				pb_backupbuddy::status( 'error', 'Error #42353212: Unable to get ftell pointer of file handle.' );
				@fclose( $fs );
				return false;
			} else {
				pb_backupbuddy::status( 'details', 'File pointer resume point: `' . $resumePoint . '`.' );
			}
		}
		@fclose( $fs );
		
		// TODO: In future perhaps pass data as the http response body and these items in a http header to prevent possible corruption in the serialized data.
		$file = array(
			'success'      => true,
			'datalen'      => strlen( $fileData ),
			'done'         => $fileDone,
			'size'         => $size,
			'resumepoint'  => $resumePoint,
			'encoded'      => isset( $utf_decoded_filename ), // only isset if utf8 was needed to find this file.
			'data'         => $fileData,
		);
		
		self::_reply( $file );
		
	} // End _getFile().
	
	
	
	/* _sendFiles()
	 *
	 * Calling site is wanting to send file(s) TO this site. Called by various verbs that pass the appropriate $type that determines root path. Valid types: backup, theme, plugin, media
	 *
	 */
	private static function _sendFiles( $type = '' ) {
		//error_log( 'type:' . $type );
		$rootDir = self::_getFilePathByType( $type ); // contains trailing slash.
		//error_log( 'API saving file to dir: `' . $rootDir . '`.' );
		
		$fileReceiveCount = 0;
		$bytesReceived = 0;
		
		foreach( self::$_incomingPayload['files'] as $file ) {
			//error_log( 'file: ' . $file );
			//$file = str_replace( array( '\\', '/' ), '', stripslashes_deep( self::$_incomingPayload[ 'filename' ] ) );
			$filePath = '';
			if ( isset( $file[ 'filepath' ] ) ) {
				$filePath = $file[ 'filepath' ];
			}
			if ( '' != $filePath ) { // Filepath specified so goes in a subdirectory under the rootDir.
				if ( $file != basename( $filePath ) ) {
					// Check if utf8 decoding the filename helps match correctly
					$utf_decoded_filePath = utf8_decode( $filePath );
					if ( $file == basename( $utf_decoded_filePath ) ) {
						$filePath = $subFilePath = $utf_decoded_filePath;
					} else {
						$message = 'Error #493844: The specified filename within the filepath parameter does not match the supplied filename parameter. | cleanfile: ' . $file . ' | filePath: | ' . $filePath;
						pb_backupbuddy::status( 'error', $message );
						self::_reply( array( 'success' => false, 'error' => $message ) );
					}
				} else { // Filename with path.
					$subFilePath = $filePath;
				}
			} else { // Just the filename. No path.
				$subFilePath = $file['file'];
			}
			
			//error_log( 'a:' . $rootDir );
			//error_log( 'b:' . $subFilePath );
			$saveFile = $rootDir . $subFilePath;
			//error_log( 'saveFile: ' . $saveFile );
			//error_log( print_r( $file, true ) );
			
			// Calculate seek position.
			$seekTo = $file[ 'seekto' ];
			if ( ! is_numeric( $seekTo ) ) {
				$seekTo = 0;
			}
			
			// Check if directory exists & create if needed.
			$saveDir = dirname( $saveFile );
			
			
			// Delete existing directory for some types of transfers.
			
			if ( ( 0 == $seekTo ) && ( file_exists( $saveFile ) ) ) { // New file transfer only. Do not delete existing file if chunking.
				if ( true !== @unlink( $saveFile ) ) {
					$message = 'Error #238722: Unable to delete existing file `' . $saveFile . '`.';
					pb_backupbuddy::status( 'error', $message );
					self::_reply( array( 'success' => false, 'error' => $message ) );
				}
			}
			
			if ( ! is_dir( $saveDir ) ) {
				if ( true !== pb_backupbuddy::$filesystem->mkdir( $saveDir ) ) {
					$message = 'Error #327832: Unable to create directory `' . $saveDir . '`. Check permissions or manually create. Halting to preserve deployment integrity';
					pb_backupbuddy::status( 'error', $message );
					self::_reply( array( 'success' => false, 'error' => $message ) );
				}
			}
			
			// Open/create file for write/append.
			if ( false === ( $fs = fopen( $saveFile, 'a' ) )) {
				$message = 'Error #489339848: Unable to fopen file `' . $saveFile . '`.';
				pb_backupbuddy::status( 'error', $message );
				self::_reply( array( 'success' => false, 'error' => $message ) );
			}
			
			// Seek to position (if applicable).
			if ( 0 != fseek( $fs, $seekTo ) ) {
				@fclose( $fs );
				$message = 'Error #8584884: Unable to fseek file.';
				pb_backupbuddy::status( 'error', $message );
				self::_reply( array( 'success' => false, 'error' => $message ) );
			}
			
			// Check data length.
			$gotLength = strlen( $file[ 'data' ] );
			if ( $file['datalen'] != $gotLength ) {
				@fclose( $fs );
				$message = 'Error #4355445: Received data of length `' . $gotLength . '` did not match sent length of `' . $file[ 'datalen' ] . '`. Data may have been truncated.';
				pb_backupbuddy::status( 'error', $message );
				self::_reply( array( 'success' => false, 'error' => $message ) );
			}
			
			// Write to file.
			if ( false === ( $bytesWritten = fwrite( $fs, $file[ 'data' ] ) ) ) {
				@fclose( $fs );
				@unlink( $saveFile );
				$message = 'Error #3984394: Error writing to file `' . $saveFile . '`.';
				pb_backupbuddy::status( 'error', $message );
				self::_reply( array( 'success' => false, 'error' => $message ) );
			} else {
				@fclose( $fs );
				
				$message = 'Wrote `' . $bytesWritten . '` bytes to `' . $saveFile . '`.';
				$bytesReceived += $bytesWritten;
				pb_backupbuddy::status( 'details', $message );
				
				if ( ( '1' == $file[ 'test' ] ) || ( 'test' == $type ) ) {
					@unlink( $saveFile );
				} else {
					if ( true === $file['done'] ) {
						$destFile = ABSPATH . basename( $saveFile );
						/*
						if ( false === @copy( $saveFile, $destFile ) ) {
							pb_backupbuddy::status( 'error', 'Error #948454: Unable to copy temporary file `' . $saveFile . '` to `' . $destFile . '`.' );
						}
						@unlink( $saveFile );
						*/
						
						// Media files need their thumbnails regenerated so get attachment ID.
						/* CANNOT DO THIS HERE ... because item may not be in the DB yet. need to transfer thumbnails?
						if ( 'media' == $type ) {
							global $wpdb;
							$sql = "SELECT post_id FROM `" . DB_NAME . "`.`" . $wpdb->prefix . "postmeta` WHERE `meta_value` = %s AND `meta_key` = '_wp_attached_file'";
							$sql = $wpdb->prepare( $sql, $filePath );
							error_log( $sql );
							$attachment_id = $wpdb->get_var( $sql );
							error_log( 'ID: ' . $attachment_id );
							error_log( 'savefile: ' . $saveFile );
							require ( ABSPATH . 'wp-admin/includes/image.php' );
							$attach_data = wp_generate_attachment_metadata( $attachment_id, $saveFile );
							wp_update_attachment_metadata( $attachment_id,  $attach_data );
						}
						*/
						
						$fileReceiveCount++;
					}
				}
				
				continue;
			}
		}
		
		self::_reply( array( 'success' => true, 'message' => 'Received a total of `' . $fileReceiveCount . ' files, `' . $bytesReceived . '` bytes.' ) );
		
	} // End _sendFile().
	
	
	
	private static function _verb_getPreDeployInfo() {
		$sha1 = false;
		if ( '1' == self::$_incomingPayload[ 'sha1' ] ) {
			$sha1 = true;
		}
		
		self::_reply( array( 'success' => true, 'data' => backupbuddy_api::getPreDeployInfo( $sha1, self::$_incomingPayload[ 'destinationSettings' ] ) ) );
	} // End _verb_getPreDeployInfo().
	
	
	private static function _verb_renderImportBuddy() {
		$backupFile = self::$_incomingPayload[ 'backupFile' ];
		$password = md5( md5( backupbuddy_core::getHttpHeader( 'backupbuddy-api-key' ) ) );
		$max_execution_time = self::$_incomingPayload['max_execution_time'];
		
		$doImportCleanup = true;
		if ( 'true' == self::$_incomingPayload['doImportCleanup'] ) {
			$doImportCleanup = true;
		} elseif ( 'false' == self::$_incomingPayload['doImportCleanup'] ) {
			$doImportCleanup = false;
		}
		
		
		// Store this serial in settings to cleanup any temp db tables in the future with this serial with periodic cleanup.
		$backupSerial = backupbuddy_core::get_serial_from_file( $backupFile );
		pb_backupbuddy::$options['rollback_cleanups'][ $backupSerial ] = time();
		pb_backupbuddy::save();
		
		$setBlogPublic = '';
		if ( 'true' == self::$_incomingPayload['setBlogPublic'] ) {
			$setBlogPublic = true;
		} elseif ( 'false' == self::$_incomingPayload['setBlogPublic'] ) {
			$setBlogPublic = false;
		}
		$additionalStateInfo = array(
			'cleanup' => array(
				'set_blog_public' => $setBlogPublic,
			)
		);
		if ( is_numeric( $max_execution_time ) ) {
			$additionalStateInfo['maxExecutionTime'] = $max_execution_time;
		}
		
		$importFileSerial = backupbuddy_core::deploymentImportBuddy( $password, backupbuddy_core::getBackupDirectory() . $backupFile, $additionalStateInfo, $doImportCleanup );
		if ( is_array( $importFileSerial ) ) {
			self::_reply( array( 'success' => false, 'error' => $importFileSerial[1] ) );
		} else {
			self::_reply( array( 'success' => true, 'importFileSerial' => $importFileSerial ) );
		}
		
	} // End _verb_renderImportBuddy().
	
	
	public static function init_incoming_call() {
		$key_public = backupbuddy_core::getHttpHeader( 'backupbuddy-api-key' );
		$verb = backupbuddy_core::getHttpHeader( 'backupbuddy-verb' );
		$time = backupbuddy_core::getHttpHeader( 'backupbuddy-now' );
		$signature = backupbuddy_core::getHttpHeader( 'backupbuddy-signature' );

		// Temporary hold of incoming payload. Reading php://input clears it so it cannot be re-read. WARNING: Do NOT unserialize until confirmed from valid key.
		if ( false === ( $_incomingPayload = @file_get_contents('php://input') ) ) {
			pb_backupbuddy::status( 'error', 'Error #43893484343: Unable to read php://input (val=false).' );
		}
		
		$maxAge = 60*60; // Time in seconds after which a signed request is deemed too old. Help prevent replays. 1hr.
		if ( 0 == count( pb_backupbuddy::$options['remote_api']['keys'] ) ) {
			pb_backupbuddy::status( 'error', 'Error #34849489343: No API keys found. Should not happen.' );
			return false;
		}
		pb_backupbuddy::status( 'details', 'About to check keys...' );
		foreach( pb_backupbuddy::$options['remote_api']['keys'] as $key ) {
			$keyArr = self::key_to_array( $key );
			if ( false === $keyArr ) {
				pb_backupbuddy::status( 'details', 'Deployment incoming call: API key `' . $key . '` did NOT match. Trying next (if any)...' );
				
				self::_error( 'Warning #834983443: Failure decoding key. See returned log details.' );
				continue;
			}
			if ( $key_public == $keyArr['key_public'] ) { // Incoming public key matches a stored public key.
				pb_backupbuddy::status( 'details', 'Deployment incoming call: Key matches.' );
				
				// Has call expired?
				if ( ( ! is_numeric( $time ) ) || ( ( time() - $time ) > $maxAge ) ) {
					pb_backupbuddy::status( 'details', 'Deployment incoming call: Key timestamp expired. Too old! Currently: `' . time() . '`. Key time: `' . $time . '`.' );
					
					$message = 'Error #4845985: API call timestamp is too old. Verify the realtime clock on each server is relatively in sync.';
					pb_backupbuddy::status( 'error', $message );
					self::_reply( array( 'success' => false, 'error' => $message ) );
					return false;
				}
				// Verify signature.
				$calculatedSignature = md5( $time . $verb . $key_public . $keyArr['key_secret'] . $_incomingPayload );
				if ( $calculatedSignature != $signature ) { // Key matched but signature failed. Data has been tempered with or damaged in transit.
					pb_backupbuddy::status( 'error', 'Deployment incoming call: Key signature match failed.' );
					return false;
				} else { // Signature good.
					pb_backupbuddy::status( 'error', 'Deployment incoming call: Signature good.' );
					
					if ( false === ( self::$_incomingPayload = @unserialize( $_incomingPayload ) ) ) { // Corrupt payload.
						pb_backupbuddy::status( 'error', 'Deployment incoming call: Payload corrupt/undecodable.' );
						
						self::$_incomingPayload = '';
						$message = 'BackupBuddy Error #3893383: Valid key but incoming payload unserializable. Corrupt?';
						pb_backupbuddy::status( 'error', $message );
						error_log( $message );
						return false;
					}
					
					pb_backupbuddy::status( 'details', 'Deployment incoming call: Key auth success. Proceeding...' );
					return true;
				}
			} else {
				pb_backupbuddy::status( 'warning', 'Warning #489349834: Incoming key did not match public key. Old key being used? This site public key: `' . $keyArr['key_public'] . '`. Received: `' . $key_public . '`.' );
			}
		}
		pb_backupbuddy::status( 'error', 'Error #83938494: Made it to end of call init.' );
		return false;
	} // End init_incoming_call().

	
	public static function key_to_array( $key ) {
		$key = trim( $key );
		if ( false === ( $keyB = base64_decode( $key ) ) ) {
			pb_backupbuddy::status( 'error', 'Error #849348749834: Unable to decode key data `' . $key . '`.' );
			return false;
		}
		if ( false === ( $keyC = unserialize( $keyB ) ) ) {
			pb_backupbuddy::status( 'error', 'Error #328937233: Unable to unserialize key data `' . $keyB . '`.' );
			return false;
		}
		return $keyC;
	}
	
	
	public static function validate_api_key( $key ) {
		if ( ! defined( 'BACKUPBUDDY_API_ENABLE' ) || ( TRUE != BACKUPBUDDY_API_ENABLE ) ) {
			return false;
		}
		/*
		if ( ! defined( 'BACKUPBUDDY_API_SALT' ) || ( 'CHANGEME' == BACKUPBUDDY_API_SALT ) || ( strlen( BACKUPBUDDY_API_SALT ) < 5 ) ) {
			return false;
		}
		*/
		if ( '' == pb_backupbuddy::$options['api_key'] ) {
			return false;
		}
		
		
		$key = self::key_to_array( $key );
		if ( $key == pb_backupbuddy::$options['api_key'] ) {
			return true;
		} else {
			return false;
		}
		
	} // End validate_api_key().
	
	
	public static function generate_key() {
		if ( ! defined( 'BACKUPBUDDY_API_ENABLE' ) || ( TRUE != BACKUPBUDDY_API_ENABLE ) ) {
			return false;
		}
		/*
		if ( ! defined( 'BACKUPBUDDY_API_SALT' ) || ( 'CHANGEME' == BACKUPBUDDY_API_SALT ) || ( strlen( BACKUPBUDDY_API_SALT ) < 5 ) ) {
			return false;
		}
		*/
		
		$siteurl = site_url();
		$homeurl = home_url();
		$rand = pb_backupbuddy::random_string( 12 );
		$rand2 = pb_backupbuddy::random_string( 12 );
		
		$key = array(
			'key_version' => 1,
			'key_public' => md5( $rand . pb_backupbuddy::$options['log_serial'] . $siteurl . $homeurl . time() ),
			'key_secret' => md5( $rand2 . pb_backupbuddy::$options['log_serial'] . $siteurl . $homeurl . time() ),
			'key_created' => time(),
			'siteurl' => $siteurl,
			'homeurl' => $homeurl,
		);
		
		
		return base64_encode( serialize( $key ) );
		
	} // End generate_api_key().
	
	
	/* _error()
	 *
	 * Logs error messages for retrieval with getErrors().
	 *
	 * @param	string		$message	Error message to log.
	 * @return	null
	 */
	private static function _error( $message ) {
		//error_log( $message );
		self::$_errors[] = $message;
		pb_backupbuddy::status( 'error', $message );
		return false;
	}
	
	
	
	/* getErrors()
	 *
	 * Get any errors which may have occurred.
	 *
	 * @return	array 		Returns an array of string error messages.
	 */
	public static function getErrors() {
		return self::$_errors;
	} // End getErrors();
	
	
	
} // End class.
