<?php
/**
 * Google Drive (v2) Main Destination Class
 *
 * DO NOT CALL THIS CLASS DIRECTLY. CALL VIA: pb_backupbuddy_destination in bootstrap.php.
 *
 * @package BackupBuddy
 */

/**
 * GDrive Destination class.
 *
 * @since 8.5.6
 * @author Brian DiChiara
 */
class pb_backupbuddy_destination_gdrive2 {

	/**
	 * Number of seconds to fudge up the time elapsed to give a little wiggle room so we don't accidently hit the edge and time out.
	 */
	const TIME_WIGGLE_ROOM = 5;

	/**
	 * Destination info array.
	 *
	 * @var array
	 */
	public static $destination_info = array(
		'name'        => 'Google Drive (v2)',
		'description' => 'Send files to Google Drive using OAuth2. <a href="https://drive.google.com" target="_blank">Learn more here.</a>',
		'category'    => 'best', // best, normal, legacy.
	);

	/**
	 * Default settings. Should be public static for auto-merging.
	 *
	 * @link https://developers.google.com/api-client-library/php/auth/service-accounts
	 *
	 * @var array
	 */
	public static $default_settings = array(
		'type'                  => 'gdrive2', // MUST MATCH your destination slug.
		'title'                 => '', // Required destination field.
		'client_id'             => '',
		'token'                 => '', // Empty string if not yet authed. JSON encoded string of token once authed. Google stores tokens in a json encoded string.
		'folder_id'             => '',
		'folder_name'           => '', // Friend title of the folder at the time of creation.
		'service_account_file'  => '', // If specified then load this file as a Google Service Account instead of normal api key pair. (See Link).
		'service_account_email' => '', // Account ID/name.

		'db_archive_limit'      => '10', // Maximum number of db backups for this site in this directory for this account. No limit if zero 0.
		'full_archive_limit'    => '4', // Maximum number of full backups for this site in this directory for this account. No limit if zero 0.
		'files_archive_limit'   => '4', // Maximum number of files only backups for this site in this directory for this account. No limit if zero 0.

		'max_time'              => '', // Default max time in seconds to allow a send to run for. Set to 0 for no time limit. Aka no chunking.
		'max_burst'             => '25', // Max size in mb of each burst within the same page load.
		'disable_gzip'          => 0, // Setting to 1 will disable gzip compression.
		'disabled'              => '0', // When 1, disable this destination.

		'_chunks_sent'          => 0, // Internal chunk counting.
		'_chunks_total'         => 0, // Internal chunk counting.
		'_media_resumeUri'      => '',
		'_media_progress'       => 0, // fseek to here.
	);

	/**
	 * Destination Settings.
	 *
	 * @var array
	 */
	public static $settings = array();

	/**
	 * Instance of GDrive Client
	 *
	 * @var \Google_Client
	 */
	private static $client = '';

	/**
	 * Instance of Google Service Drive.
	 *
	 * @var \Google_Service_Drive
	 */
	private static $api = false;

	/**
	 * Client ID that is currently connected.
	 *
	 * @var bool
	 */
	private static $current_client_id = false;

	/**
	 * Start time of Send.
	 *
	 * @var int
	 */
	private static $time_start = 0;

	/**
	 * Number of chunks sent this round.
	 *
	 * @var int
	 */
	private static $chunks_this_round = 0;

	/**
	 * Size in MB for maximum file size to use simple upload, otherwise resumable upload will be used.
	 *
	 * @var int
	 */
	private static $max_simple_upload = 5;

	/**
	 * Init destination and settings.
	 *
	 * @param int $destination_id  ID of destination.
	 *
	 * @return bool  If initialized.
	 */
	public static function init( $destination_id = false ) {
		// add_action( 'backupbuddy_delete_destination_gdrive2', array( 'pb_backupbuddy_destination_gdrive2', 'on_destination_delete' ) );

		if ( false !== $destination_id ) {
			if ( ! empty( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) {
				$settings = pb_backupbuddy::$options['remote_destinations'][ $destination_id ];
				self::add_settings( $settings );
			}
		}
		return true;
	} // init.

	/**
	 * Make sure we're ready to use the API.
	 *
	 * @param array  $settings  Destination settings.
	 * @param string $context   Context for debugging.
	 *
	 * @return bool  If is ready.
	 */
	public static function is_ready( $settings = false, $context = false ) {
		if ( false !== $settings ) {
			self::add_settings( $settings );
		}

		if ( ! empty( self::$settings['disabled'] ) && 1 === (int) self::$settings['disabled'] ) {
			self::error( __( 'Error #48933: This destination is currently disabled. Enable it under this destination\'s Advanced Settings.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		if ( false === self::get_client() ) {
			self::error( __( 'There was a problem connecting Google Drive (v2). Please contact support for assistance.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		if ( false === self::$api ) {
			self::error( __( 'There was a problem initializing the Google Drive API. Please contact support for assistance.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		return true;
	}

	/**
	 * Put the destination settings into the class static property.
	 *
	 * @param array $settings  Destination settings array.
	 */
	public static function add_settings( $settings ) {
		if ( ! is_array( $settings ) || ! count( $settings ) ) {
			return false;
		}
		self::$settings = $settings;
	}

	/**
	 * Save destination settings (only for existing destinations).
	 *
	 * @return bool  If settings were saved.
	 */
	public static function save() {
		$destination_id = pb_backupbuddy::_GET( 'destination_id' );
		if ( ( ! $destination_id && '0' !== $destination_id && 0 !== $destination_id ) || 'NEW' === $destination_id ) {
			return false;
		}

		if ( empty( self::$settings ) ) {
			return false;
		}

		// Compare with existing destination settings.
		if ( ! empty( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) {
			if ( pb_backupbuddy::$options['remote_destinations'][ $destination_id ]['type'] !== self::$settings['type'] ) {
				self::error( __( 'Destination Save Error: Destination type mismatch.', 'it-l10n-backupbuddy' ) );
				return false;
			}
		}

		pb_backupbuddy::$options['remote_destinations'][ $destination_id ] = self::$settings;
		pb_backupbuddy::save();
		return true;
	}

	/**
	 * Get the API credentials.
	 *
	 * @param string $param  Individual setting parameter.
	 *
	 * @return false|array  Array of credentials or false if invalid.
	 */
	public static function get_config( $param = false ) {
		$config_path = pb_backupbuddy::plugin_path() . '/destinations/gdrive2/creds.php';
		if ( ! file_exists( $config_path ) ) {
			self::error( __( 'Google Drive API Credentials Missing.', 'it-l10n-backupbuddy' ) );
			return false;
		}
		$config = include $config_path;
		if ( ! is_array( $config ) || empty( $config['GDRIVE_API_ID'] ) ) {
			self::error( __( 'Invalid Google Drive API Credentials.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		if ( false !== $param ) {
			if ( empty( $config[ $param ] ) ) {
				return false;
			}
			return $config[ $param ];
		}

		return $config;
	}

	/**
	 * Create the Google Drive client.
	 *
	 * @param bool $fresh  If the cached client should be ignored.
	 *
	 * @return \Google_Client|false  Google_Client object if successful.
	 */
	public static function get_client( $fresh = false ) {
		if ( false !== self::$client && false === $fresh ) {
			if ( ! empty( self::$settings['client_id'] ) && self::$settings['client_id'] === self::$current_client_id ) { // Already connected to this account.
				return self::$client;
			}
		}

		pb_backupbuddy::status( 'details', 'Connecting to Google Drive (v2).' );

		self::$client = new Google_Client();
		self::$client->setApplicationName( 'BackupBuddy v' . pb_backupbuddy::settings( 'version' ) );
		self::$client->addScope( 'https://www.googleapis.com/auth/drive' );
		self::$client->setAccessType( 'offline' ); // Required so that Google will return the refresh token.
		self::$client->setApprovalPrompt( 'force' ); // Required if they have already authorized the application on another site.
		// self::$client->getHttpClient()->setDefaultOption( 'headers/disable_gzip', self::$settings['disable_gzip'] );

		if ( self::$settings['service_account_file'] ) { // Service account.

			if ( ! file_exists( self::$settings['service_account_file'] ) ) {
				global $bb_gdrive_error;
				$bb_gdrive_error = 'Error #202003251522: p12 service account file not found `' . self::$settings['service_account_file'] . '`.';
				self::error( $bb_gdrive_error );
				return false;
			}

			$account_file = @file_get_contents( self::$settings['service_account_file'] );

			if ( false === $account_file ) {
				global $bb_gdrive_error;
				$bb_gdrive_error = 'Error #4430439433: Unable to read/access p12 service account file `' . self::$settings['service_account_file'] . '`.';
				self::error( $bb_gdrive_error );
				return false;
			}

			$cred = new Google_Auth_AssertionCredentials(
				self::$settings['service_account_email'], // service account name.
				array( 'https://www.googleapis.com/auth/drive' ),
				$account_file
			);

			self::$client->setAssertionCredentials( $cred );

			if ( self::$client->isAccessTokenExpired() ) {
				try {
					self::$client->refreshTokenWithAssertion();
				} catch ( Exception $e ) {
					global $bb_gdrive_error;
					$error           = self::get_gdrive_exception_error( $e );
					$bb_gdrive_error = 'Error #4349898343843: Unable to set/refresh access token. Access token error details: `' . $error . '`.';
					pb_backupbuddy::status( 'error', $bb_gdrive_error );
					return false;
				}
			}

			self::$settings['token']     = json_encode( self::$client->getAccessToken() );
			self::$settings['client_id'] = self::$settings['service_account_file'];
		} else { // Normal account authentication.
			$config = self::get_config();
			if ( ! $config ) {
				self::error( __( 'Could not retrieve Google Drive Credentials.', 'it-l10n-backupbuddy' ) );
				return false;
			}

			self::$client->setClientId( $config['GDRIVE_API_ID'] );
			self::$client->setClientSecret( $config['GDRIVE_API_SECRET'] );
			self::$client->setRedirectUri( $config['GDRIVE_REDIRECT_URI'] );

			self::$settings['client_id'] = $config['GDRIVE_API_ID'];

			if ( self::$settings['token'] ) {
				// Set token initially.
				pb_backupbuddy::status( 'details', 'Setting Google Drive (v2) Access Token.' );
				pb_backupbuddy::status( 'details', 'TOKEN: ' . self::$settings['token'] );
				try {
					self::$client->setAccessToken( self::$settings['token'] );
				} catch ( Exception $e ) {
					global $bb_gdrive_error;
					$error           = self::get_gdrive_exception_error( $e );
					$bb_gdrive_error = 'Error #4839484984: Unable to set access token. Access token error details: `' . $error . '`.';
					self::error( $bb_gdrive_error );
					return false;
				}

				// Make sure token is up-to-date.
				if ( self::$client->isAccessTokenExpired() ) {
					pb_backupbuddy::status( 'status', 'Google Drive (v2) Access Token expired. Attempting to refresh...' );
					if ( self::$client->getRefreshToken() ) {
						self::$client->fetchAccessTokenWithRefreshToken( self::$client->getRefreshToken() );
						self::$settings['token'] = json_encode( self::$client->getAccessToken() );
						if ( ! self::save() ) {
							pb_backupbuddy::status( 'error', 'Error #202003270842: Could not save refresh token for Google Drive (v2).' );
						} else {
							pb_backupbuddy::status( 'status', 'Google Drive (v2) Access Token refreshed.' );
						}
					}
				}
			}
		}

		// If we have proper credentials, create Drive API and store client ID.
		if ( self::$client->getAccessToken() ) {
			try {
				self::$api = new Google_Service_Drive( self::$client );
			} catch ( Exception $e ) {
				$error = self::get_gdrive_exception_error( $e );
				self::error( 'Google Drive (v2) Error: ' . $error );
				return false;
			}

			self::$current_client_id = self::$settings['client_id'];
		}

		return self::$client;
	}

	/**
	 * Get Authorization URL.
	 *
	 * @return string  Auth URL.
	 */
	public static function get_oauth_url() {
		if ( false === self::get_client() ) {
			return false;
		}

		$package = backupbuddy_get_package_license();

		// Create the payload for validation.
		$key     = $package['key'];
		$user    = $package['user'];
		$payload = array(
			'time'   => current_time( 'timestamp' ),
			'action' => 'backupbuddy-gdrive2-oauth-connect',
		);
		$payload = wp_json_encode( $payload );

		// Build the value for the "state" parameter passed to the OAuth API.
		$source = admin_url( 'admin.php?page=pb_backupbuddy_destinations&gdrive-oauth=1' );
		$source = add_query_arg( 'username', $user, $source );
		$source = add_query_arg( 'payload', $payload, $source );
		$source = add_query_arg( 'signature', hash_hmac( 'sha1', $payload, $key ), $source );

		self::$client->setState( $source );

		return self::$client->createAuthUrl();
	}

	/**
	 * Send one or more files.
	 *
	 * @link https://developers.google.com/api-client-library/php/guide/media_upload
	 *
	 * @param array  $settings             Destination Settings array.
	 * @param array  $files                Array of one or more files to send.
	 * @param string $send_id              ID of the send.
	 * @param bool   $delete_after         Delete local sent file after send.
	 * @param bool   $delete_remote_after  Delete remote file after successful send.
	 *
	 * @return bool|string|array  Bool if send was successful, string error message, or array of multipart send.
	 */
	public static function send( $settings = false, $files = array(), $send_id = '', $delete_after = false, $delete_remote_after = false ) {
		if ( ! self::is_ready( $settings ) ) {
			return self::error( 'Error #38923923: Unable to connect with Google Drive (v2). See log for details.' );
		}

		pb_backupbuddy::status( 'details', 'Google Drive (v2) send() function started. Settings: `' . print_r( self::$settings, true ) . '`.' );
		self::$time_start = microtime( true );

		if ( ! is_array( $files ) ) {
			$files = array( $files );
		}

		$folder_id = self::get_root_folder();

		$chunk_size = self::$settings['max_burst'] * 1024 * 1024; // Send X mb at a time to limit memory usage.

		foreach ( $files as $file ) {
			if ( ! file_exists( $file ) ) {
				return self::error( 'Error #37792: File selected to send not found: `' . $file . '`.' );
			}

			// Gather file information.
			$backup_type = backupbuddy_core::getBackupTypeFromFile( $file );
			$file_size   = filesize( $file );
			$mime_type   = mime_content_type( $file );

			pb_backupbuddy::status( 'details', 'About to upload file `' . $file . '` of size `' . $file_size . '` with mime type `' . $mime_type . '` into folder `' . $folder_id . '`.' );

			if ( empty( self::$settings['_chunks_total'] ) ) {
				self::$settings['_chunks_total'] = 1;
			}

			if ( $file_size / 1024 / 1024 <= self::$max_simple_upload ) {
				pb_backupbuddy::status( 'details', 'File size `' . pb_backupbuddy::$format->file_size( $file_size ) . '` qualifies for simple upload to Google Drive.' );
				$upload_type = 'multipart';
			} else {
				$upload_type = 'resumable';

				// Only log this message first pass.
				if ( empty( self::$settings['_media_resumeUri'] ) ) {
					if ( $file_size > $chunk_size ) {
						pb_backupbuddy::status( 'details', 'File size `' . pb_backupbuddy::$format->file_size( $file_size ) . '` exceeds max burst size `' . self::$settings['max_burst'] . ' MB` so this will be sent in bursts. If time limit nears, then send will be chunked across multiple PHP loads.' );
						self::$settings['_chunks_total'] = ceil( $file_size / $chunk_size );
					} else {
						pb_backupbuddy::status( 'details', 'File size `' . pb_backupbuddy::$format->file_size( $file_size ) . '` exceeds max size for simple upload, but does not exceed max burst size `' . $chunk_size . ' MB` so this file will be sent in a single upload request.' );
					}
				}

				// Defer execution of request.
				self::$client->setDefer( true );
			}

			// Build the File object.
			$drive_file = new Google_Service_Drive_DriveFile(
				array(
					'name'        => basename( $file ),
					'description' => 'BackupBuddy file',
				)
			);

			// Set the parent folder.
			if ( 'root' !== $folder_id ) {
				pb_backupbuddy::status( 'details', 'Setting parent folder ID for upload: `' . $folder_id . '`.' );
				$drive_file->setParents( array( $folder_id ) );
			}

			$file_args = array(
				'mimeType'   => $mime_type,
				'fields'     => '*',
				'uploadType' => $upload_type,
			);

			// Insert the file contents to be uploaded.
			if ( 'multipart' === $upload_type ) {
				pb_backupbuddy::status( 'details', 'Loading file contents for upload.' );
				$file_args['data'] = file_get_contents( $file );
			}

			// Get the Drive File Upload Request. For smaller files, this performs the upload.
			try {
				$create = self::$api->files->create( $drive_file, $file_args );
			} catch ( Exception $e ) {
				$error = self::get_gdrive_exception_error( $e );
				self::error( 'Error #3232783268336: initiating upload to Google Drive (v2). Details: ' . $error, 'alert' );
				return false;
			}

			if ( 'multipart' === $upload_type ) {
				$upload_status = $create;
				pb_backupbuddy::status( 'details', 'Simple upload complete.' );
			} elseif ( 'resumable' === $upload_type ) {
				// See Link in description.
				try {
					$media = new Google_Http_MediaFileUpload(
						self::$client,
						$create,
						$mime_type,
						null,
						true,
						$chunk_size
					);
				} catch ( Exception $e ) {
					$error = self::get_gdrive_exception_error( $e );
					self::error( 'Error #3893273937: initiating upload. Details: ' . $error, 'alert' );
					return false;
				}

				$media->setFileSize( $file_size );
				pb_backupbuddy::status( 'details', 'Resumable upload initialized.' );

				$max_time = self::$settings['max_time'];
				if ( ! $max_time || ! is_numeric( $max_time ) ) {
					pb_backupbuddy::status( 'details', 'Max time not set in settings so detecting server max PHP runtime.' );
					$max_time = backupbuddy_core::detectMaxExecutionTime();
				}
				pb_backupbuddy::status( 'details', 'Max time set to: ' . $max_time );
			}

			if ( 'resumable' === $upload_type ) {
				if ( ! empty( self::$settings['_media_resumeUri'] ) ) {
					pb_backupbuddy::status( 'details', 'Resuming upload with resumeUri: ' . self::$settings['_media_resumeUri'] . ' and progress: ' . self::$settings['_media_progress'] );
					$upload_status = $media->resume( self::$settings['_media_resumeUri'] );
					$status        = false === $upload_status ? 'Still more to send.' : $upload_status;
					pb_backupbuddy::status( 'details', 'Resume Upload Status: ' . print_r( $status, true ) );
				} else {
					// First of multiple resumable requests.
					$upload_status = false;
				}

				pb_backupbuddy::status( 'details', 'Opening file for sending in binary mode.' );

				$prev_pointer = self::$settings['_media_progress'];
				$fs           = fopen( $file, 'rb' );

				if ( $prev_pointer ) {
					if ( 0 !== fseek( $fs, $prev_pointer ) ) { // Go off the resume point as given by Google in case it didn't all make it.
						pb_backupbuddy::status( 'error', 'Error #3872733: Failed to seek file to resume point `' . $prev_pointer . '` via fseek().' );
						return false;
					}
				}

				// Try to send everything in one process, as long as it does not reach script time limit.
				while ( ! $upload_status && ! feof( $fs ) ) {
					// fread will never return more than 8192 bytes if the stream is read buffered and it does not represent a plain file.
					$chunk     = fread( $fs, $chunk_size );
					$chunk_num = self::$settings['_chunks_sent'] + 1;
					pb_backupbuddy::status( 'details', 'Sending chunk ' . $chunk_num . ' out of ' . self::$settings['_chunks_total'] . '.' );

					// Send chunk of data.
					try {
						// The final value of $upload_status will be the data from the API for the object that has been uploaded.
						$upload_status = $media->nextChunk( $chunk );
					} catch ( Exception $e ) {
						$error   = self::get_gdrive_exception_error( $e );
						$message = 'Error #8239832: Error sending burst data. Details: `' . $error . '`.';
						self::error( $message );
						fclose( $fs );
						return false;
					}

					self::$settings['_chunks_sent']++;
					self::$chunks_this_round++;
					$status = false === $upload_status ? 'Still more to send.' : $upload_status;
					pb_backupbuddy::status( 'details', 'Burst file data sent. Upload Status: ' . print_r( $status, true ) );

					if ( ! $upload_status && 0 !== $max_time ) {
						// More data remains so see if we need to consider chunking to a new PHP process.
						if ( ! self::can_send_more( $max_time ) ) {
							// We don't appear to have enough time to send another chunk. Setup a cron to finish the upload.
							@fclose( $fs );
							return self::schedule_upload_continuation( compact( 'media', 'files', 'send_id', 'delete_after', 'delete_remote_after' ) );
						} else {
							pb_backupbuddy::status( 'details', 'Not approaching limits. Sending another chunk.' );
						}
					}
				}

				// Reached end of file.
				if ( $upload_status && $send_id ) {
					require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
					$fileoptions_obj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt', false, false, false );
					$result          = $fileoptions_obj->is_ok();
					if ( true !== $result ) {
						pb_backupbuddy::status( 'error', __( 'Fatal Error #9034.397237. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
						return false;
					}
					pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );
					$fileoptions = &$fileoptions_obj->options;

					$fileoptions['_multipart_status'] = 'Sent part ' . self::$settings['_chunks_sent'] . ' of ' . self::$settings['_chunks_total'] . ' parts.';
					$fileoptions['finish_time']       = microtime( true );
					$fileoptions['status']            = 'success';
					$fileoptions_obj->save();
					unset( $fileoptions_obj );
				}

				fclose( $fs );
			}

			if ( is_a( $upload_status, 'Google_Service_Drive_DriveFile' ) ) {
				pb_backupbuddy::status( 'details', 'Uploaded File ID: ' . $upload_status->getId() );
			} elseif ( false === $upload_status ) {
				self::error( 'Error #84347474 sending. Upload status returned false.' );
				return false;
			}

			// Reset defer back to default.
			self::$client->setDefer( false );

			// Upload Success.
			if ( true === $delete_remote_after ) {
				self::deleteFile( false, $upload_status->getId() );
			}

			// Prune if upload successful (and is a backup).
			if ( $upload_status && $backup_type ) {
				self::prune( false, $backup_type, $folder_id );
			}
		} // end foreach.

		// Made it this far then success.
		return true;
	} // send.

	/**
	 * Check to see if we can send more chunks this round.
	 *
	 * @param int $max_time  Maximum allowed script run time.
	 *
	 * @return bool  If more chunks can be send.
	 */
	public static function can_send_more( $max_time ) {
		$chunk_size = self::$settings['max_burst'] * 1024 * 1024; // Send X mb at a time to limit memory usage.

		// If we are within X second of reaching maximum PHP runtime then stop here so that it can be picked up in another PHP process...
		$total_size_sent = self::$chunks_this_round * $chunk_size; // Total bytes sent this PHP load.
		$bytes_per_sec   = $total_size_sent / ( microtime( true ) - self::$time_start );
		$time_remaining  = $max_time - ( microtime( true ) - self::$time_start + self::TIME_WIGGLE_ROOM );
		if ( $time_remaining < 0 ) {
			$time_remaining = 0;
		}
		$bytes_we_could_send_with_time_left = $bytes_per_sec * $time_remaining;

		pb_backupbuddy::status( 'details', 'Total sent: `' . pb_backupbuddy::$format->file_size( $total_size_sent ) . '`. Speed (per sec): `' . pb_backupbuddy::$format->file_size( $bytes_per_sec ) . '`. Time Remaining (w/ wiggle): `' . $time_remaining . '`. Size that could potentially be sent with remaining time: `' . pb_backupbuddy::$format->file_size( $bytes_we_could_send_with_time_left ) . '` with chunk size of `' . pb_backupbuddy::$format->file_size( $chunk_size ) . '`.' );

		if ( $bytes_we_could_send_with_time_left < $chunk_size ) {
			pb_backupbuddy::status( 'details', 'Not enough time left (~' . round( $time_remaining, 3 ) . 's) with max time of ' . $max_time . 'sec to send another chunk at `' . pb_backupbuddy::$format->file_size( $bytes_per_sec ) . '` / sec. Ran for ' . round( microtime( true ) - self::$time_start, 3 ) . ' sec. Proceeding to use chunking.' );

			return false;
		}

		return true;
	}

	/**
	 * Schedule a cron to send the rest of the upload.
	 *
	 * @param array $resume  Contains vars needed to schedule resume.
	 *
	 * @return array  Pointer and status.
	 */
	public static function schedule_upload_continuation( $resume = array() ) {
		// Grab these vars from the class.  Note that we changed these vars from private to public to make chunked resuming possible.
		self::$settings['_media_resumeUri'] = $resume['media']->getResumeUri();
		self::$settings['_media_progress']  = (int) $resume['media']->getProgress();

		// Schedule cron.
		$cron_time    = time();
		$cron_args    = array( self::$settings, $resume['files'], $resume['send_id'], $resume['delete_after'], $resume['delete_remote_after'] );
		$cron_hash_id = md5( $cron_time . serialize( $cron_args ) );
		$cron_args[]  = $cron_hash_id;

		$schedule_result = backupbuddy_core::schedule_single_event( $cron_time, 'destination_send', $cron_args );
		if ( true === $schedule_result ) {
			pb_backupbuddy::status( 'details', 'Next chunk cron upload event scheduled.' );
		} else {
			pb_backupbuddy::status( 'error', 'Next chunk cron upload event FAILED to be scheduled.' );
		}

		if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
			update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
			spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
		}

		// File pointer location, elapsed time during the import.
		return array( self::$settings['_media_progress'], 'Sent part ' . self::$settings['_chunks_sent'] . ' of ' . self::$settings['_chunks_total'] . ' parts.' );
	}

	/**
	 * Enforce Backup limits.
	 *
	 * @param array  $settings     Destination Settings array.
	 * @param string $backup_type  Backup type.
	 * @param string $folder_id    Folder ID.
	 *
	 * @return bool  If limits were enforced.
	 */
	public static function prune( $settings = false, $backup_type = '', $folder_id = '' ) {
		if ( ! self::is_ready( $settings ) ) {
			return false;
		}

		// BEGIN FILE LIMIT PROCESSING. Enforce archive limits if applicable.
		if ( 'full' === $backup_type ) {
			$limit = self::$settings['full_archive_limit'];
		} elseif ( 'db' === $backup_type ) {
			$limit = self::$settings['db_archive_limit'];
		} elseif ( 'files' === $backup_type ) {
			$limit = self::$settings['files_archive_limit'];
		} elseif ( 'themes' === $backup_type ) {
			$limit = self::$settings['themes_archive_limit'];
		} elseif ( 'plugins' === $backup_type ) {
			$limit = self::$settings['plugins_archive_limit'];
		} elseif ( 'media' === $backup_type ) {
			$limit = self::$settings['media_archive_limit'];
		} else {
			$limit = 0;
			pb_backupbuddy::status( 'warning', 'Warning #34352453244. Google Drive (v2) was unable to determine backup type (reported: `' . $backup_type . '`) so archive limits NOT enforced for this backup.' );
		}
		pb_backupbuddy::status( 'details', 'Google Drive (v2) database backup archive limit of `' . $limit . '` of type `' . $backup_type . '` based on destination settings.' );

		if ( $limit > 0 ) {

			pb_backupbuddy::status( 'details', 'Google Drive (v2) archive limit enforcement beginning.' );

			// Get file listing.
			$search_count = 1;
			$remote_files = array();
			while( count( $remote_files ) == 0 && $search_count < 5 ) {
				pb_backupbuddy::status( 'details', 'Checking archive limits. Attempt ' . $search_count . '.' );
				$file_search  = array(
					'query'      => 'backups_type',
					'query_args' => array(
						$backup_type,
					),
				);
				$remote_files = self::get_folder_contents( $folder_id, $file_search );
				sleep( 1 );
				$search_count++;
			}

			// List backups associated with this site by date.
			$backups = array();
			foreach ( $remote_files as $remote_file ) {
				$backups[ $remote_file->getId() ] = strtotime( backupbuddy_core::parse_file( $remote_file->getName(), 'datetime' ) );
			}

			arsort( $backups );

			pb_backupbuddy::status( 'details', 'Google Drive (v2) found `' . count( $backups ) . '` backups of this type when checking archive limits.' );

			if ( count( $backups ) > $limit ) {
				pb_backupbuddy::status( 'details', 'More archives (' . count( $backups ) . ') than limit (' . $limit . ') allows. Trimming...' );
				$i                 = 0;
				$delete_fail_count = 0;

				foreach ( $backups as $backup_id => $backup_time ) {
					$i++;
					if ( $i > $limit ) {
						pb_backupbuddy::status( 'details', 'Trimming excess file `' . $backup_id . '`...' );

						if ( true !== self::deleteFile( false, $backup_id ) ) {
							global $pb_backupbuddy_destination_errors;
							pb_backupbuddy::status( 'details', 'Unable to delete excess Google Drive (v2) file `' . $backup_id . '`. Details: `' . print_r( $pb_backupbuddy_destination_errors, true ) . '`.' );
							$delete_fail_count++;
						}
					}
				}
				pb_backupbuddy::status( 'details', 'Finished trimming excess backups.' );
				if ( 0 !== $delete_fail_count ) {
					self::error( 'Google Drive (v2) remote limit could not delete ' . $delete_fail_count . ' backups.', 'mail' );
				}
			}

			pb_backupbuddy::status( 'details', 'Google Drive (v2) completed archive limiting.' );
			return true;
		}

		pb_backupbuddy::status( 'details', 'No Google Drive (v2) archive file limit to enforce.' );
		return false;
	} // prune.

	/**
	 * Get the root folder ID.
	 *
	 * @return string|false  Folder ID or false on error.
	 */
	public static function get_root_folder() {
		if ( empty( self::$settings['folder_id'] ) ) {
			return 'root';
		}

		return self::$settings['folder_id'];
	} // get_root_folder.

	/**
	 * Get GDrive Info
	 *
	 * @param array $settings  Destination Settings array.
	 *
	 * @return bool|array  False on error or array of Drive info.
	 */
	public static function getDriveInfo( $settings = false ) {
		if ( ! self::is_ready( $settings ) ) {
			self::error( __( 'Unable to connect with Google Drive (v2). See log for details.', 'it-l10n-backupbuddy' ), 'echo' );
			return false;
		}

		try {
			$about = self::$api->about->get(
				array(
					'fields' => '*',
				)
			);
		} catch ( Exception $e ) {
			self::error( self::get_gdrive_exception_error( $e ), 'echo' );
			return false;
		}

		return $about;
	} // getDriveInfo.


	/**
	 * Sends a test upload to Google Drive.
	 *
	 * @param array $settings  Destination settings array.
	 *
	 * @return bool  If successful.
	 */
	public static function test( $settings = false ) {
		if ( ! self::is_ready( $settings ) ) {
			self::error( 'Unable to connect with Google Drive (v2). See log for details.', 'echo' );
			return false;
		}

		pb_backupbuddy::status( 'details', 'Testing Google Drive (v2) destination.' );
		$files = array( pb_backupbuddy::plugin_path() . '/destinations/remote-send-test.php' );

		$results = self::send( false, $files, '', false, true );

		if ( true === $results ) {
			esc_html_e( 'Success sending test file to Google Drive (v2).', 'it-l10n-backupbuddy' );
			return true;
		}

		esc_html_e( 'Failure sending test file to Google Drive (v2).', 'it-l10n-backupbuddy' );
		return false;
	} // test.

	/**
	 * Retrieve remote folder contents.
	 *
	 * Settings must be added using `add_settings()` prior to calling this method.
	 *
	 * @param string $folder      Folder ID.
	 * @param array  $query_args  Array of parameters to query files. Predefined queries use the 'query' arg.
	 * @param bool   $use_prefix  If backup prefix setting should be used when filtering.
	 *
	 * @return array  Array of contents.
	 */
	public static function get_folder_contents( $folder = false, $query_args = array(), $use_prefix = true ) {
		if ( ! self::is_ready() ) {
			return false;
		}

		if ( ! $folder ) {
			$folder = self::get_root_folder();
		}

		$prefix = '';
		if ( $use_prefix && backupbuddy_core::backup_prefix() ) {
			$prefix = backupbuddy_core::backup_prefix() . '-';
		}

		$queries = array(
			'backups'      => 'name contains \'backup-' . $prefix . '\' AND \'%s\' IN parents AND mimeType = \'application/zip\' AND trashed=false',
			'exact_match'  => 'name = \'%s\' AND \'%s\' IN parents AND trashed=false',
			'backups_type' => 'name contains \'backup-' . $prefix . '\' AND name contains \'-%s-\' AND \'%s\' IN parents AND mimeType = \'application/zip\' AND trashed=false',
			'dat_files'    => 'name contains \'backup-' . $prefix . '\' AND \'%s\' IN parents AND fileExtension = \'dat\' AND trashed=false',
			'folders'      => 'mimeType = \'application/vnd.google-apps.folder\' AND \'%s\' IN parents AND trashed=false',
		);

		$query = '';

		if ( empty( $query_args['query_args'] ) ) {
			$query_args['query_args'] = array();
		}

		if ( ! empty( $query_args['query'] ) ) {
			if ( ! empty( $queries[ $query_args['query'] ] ) ) {
				// Use predefined query.
				$query = $queries[ $query_args['query'] ];
				$args  = substr_count( $query, '%s' );

				// Auto sort by alpha with folders.
				if ( 'folders' === $query_args['query'] ) {
					$query_args['orderBy'] = 'name asc';
				}

				if ( count( $query_args['query_args'] ) < $args ) {
					// Always search in folder.
					$query_args['query_args'][] = $folder;
				}

				// Insert query arguments.
				$query = vsprintf( $query, $query_args['query_args'] );
			}
		}

		// Remove internal parameters.
		unset( $query_args['query'] );
		unset( $query_args['query_args'] );

		$contents   = array();
		$parameters = array_merge(
			// Defaults.
			array(
				'pageSize'  => 20,
				'fields'    => '*',
				'pageToken' => false,
				'orderBy'   => 'createdTime desc,modifiedTime desc',
				'q'         => $query,
			),
			$query_args
		);

		do {
			try {
				$files                   = self::$api->files->listFiles( $parameters );
				$contents                = array_merge( $contents, $files->getFiles() );
				$parameters['pageToken'] = $files->getNextPageToken();
			} catch ( Exception $e ) {
				$error = self::get_gdrive_exception_error( $e );
				self::error( 'Error #202003261434: ' . $error, 'echo' );
				$parameters['pageToken'] = false; // abort on error.
			}
		} while ( $parameters['pageToken'] );

		return $contents;
	}

	/**
	 * List files in destination.
	 *
	 * @link https://developers.google.com/drive/v2/reference/files/list
	 *
	 * @param array  $settings  Destination settings array.
	 * @param string $mode      List files mode.
	 *
	 * @return array  Array of files.
	 */
	public static function listFiles( $settings = false, $mode = 'default' ) {
		if ( ! self::is_ready( $settings ) ) {
			self::error( __( 'Error #233233: Unable to connect with Google Drive (v2). See log for details.', 'it-l10n-backupbuddy' ), 'echo' );
			return false;
		}

		$files = self::get_folder_contents( false, array( 'query' => 'backups' ) );


		if ( ! is_array( $files ) ) {
			self::error( __( 'Unexpected response retrieving Google Drive (v2) folder contents for folder: ', 'it-l10n-backupbuddy' ) . $folder_path );
			return array();
		}

		$backup_list       = array();
		$backup_sort_dates = array();

		foreach ( $files as $index => $file ) {
			$filename = $file->getName();
			$backup   = $filename;

			$backup_type = backupbuddy_core::getBackupTypeFromFile( basename( $backup ) );

			if ( ! $backup_type ) {
				continue;
			}

			$backup_date   = backupbuddy_core::parse_file( $backup, 'datetime' );
			$download_link = admin_url() . sprintf(
				'?gdrive2-destination-id=%s&gdrive2-download=%s',
				backupbuddy_backups()->get_destination_id(),
				rawurlencode( $file->getId() )
			);
			// Alternative download link (maybe prompts for virus scan).
			// $download_link = $file->getWebContentLink();

			$backup_array = array(
				array(
					$backup,
					$backup_date,
					$download_link,
					'_blank',
				),
				backupbuddy_core::pretty_backup_type( $backup_type ),
				pb_backupbuddy::$format->file_size( $file->getSize() ),
			);

			if ( 'default' === $mode ) {
				$copy_link = '&cpy=' . rawurlencode( $file->getId() );
				$actions   = array(
					$download_link => __( 'Download Backup', 'it-l10n-backupbuddy' ),
					$copy_link     => __( 'Copy to Local', 'it-l10n-backupbuddy' ),
				);

				// Make Alternative Download link open in new window.
				add_filter( 'backupbuddy_backups_action_menu', array( 'pb_backupbuddy_destination_gdrive2', 'download_link_target' ), 10, 3 );
				$backup_array[] = backupbuddy_backups()->get_action_menu( $file->getId(), $actions );
				remove_filter( 'backupbuddy_backups_action_menu', array( 'pb_backupbuddy_destination_gdrive2', 'download_link_target' ), 10 );
			} elseif ( 'restore' === $mode ) {
				$backup_array[] = backupbuddy_backups()->get_details_link( $filename, $file->getId() );
				$backup_array[] = backupbuddy_backups()->get_restore_buttons( $filename, $backup_type, $file->getId() );
			}

			if ( 'restore' === $mode ) {
				$key = $file->getName();
			} else {
				$key = $file->getId();
			}

			// Array key is checkbox value.
			$backup_list[ $key ]       = $backup_array;
			$backup_sort_dates[ $key ] = $backup_date;
		}

		$backup_list = backupbuddy_backups()->sort_backups( $backup_list, $backup_sort_dates );

		return $backup_list;
	} // listFiles.

	/**
	 * Set download link target to blank.
	 *
	 * @param array  $action_menu  Action menu array.
	 * @param string $file         Backup File.
	 * @param object $class        BackupBuddy Backups class instance.
	 *
	 * @return array  Modified action menu.
	 */
	public static function download_link_target( $action_menu, $file, $class ) {
		if ( false === strpos( $action_menu['download-backup'], admin_url() ) ) {
			$action_menu['download-backup'] = str_replace( '<a ', '<a target="_blank" ', $action_menu['download-backup'] );
		}
		return $action_menu;
	}

	/**
	 * Force Download Google Drive file.
	 *
	 * @param array  $settings  Destination settings array.
	 * @param string $file_id   Google Drive File ID.
	 */
	public static function force_download( $settings = false, $file_id = '' ) {
		if ( ! self::is_ready( $settings ) ) {
			self::error( __( 'Error #233233: Unable to connect with Google Drive (v2). See log for details.', 'it-l10n-backupbuddy' ), 'echo' );
			return false;
		}

		$file = self::get_file_meta( false, $file_id );

		if ( ! $file ) {
			self::error( __( 'Missing Google Drive File for download.', 'it-l10n-backupbuddy' ), 'echo' );
			return false;
		}

		flush();

		pb_backupbuddy::set_greedy_script_limits();

		try {
			$content = self::$api->files->get( $file->getID(), array( 'alt' => 'media' ) );
		} catch ( Exception $e ) {
			self::error( self::get_gdrive_exception_error( $e ), 'echo' );
			return false;
		}

		flush();
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename=' . $file->getName() );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Accept-Ranges: bytes' );
		header( 'Expires: 0' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . $file->getSize() );

		while ( ! $content->getBody()->eof() ) {
			echo $content->getBody()->read( 1024 );
		}

		exit();
	}

	/**
	 * Get file meta information array.
	 *
	 * @param array  $settings  Destination Settings array.
	 * @param string $file_id   File ID.
	 *
	 * @return array|bool  File meta array or false on failure.
	 */
	public static function get_file_meta( $settings = false, $file_id = '' ) {
		if ( ! self::is_ready( $settings ) ) {
			self::error( __( 'Error #3839483a: Unable to connect with Google Drive (v2). See log for details.', 'it-l10n-backupbuddy' ), 'echo' );
			return false;
		}

		$meta = false;

		try {
			$meta = self::$api->files->get( $file_id );
		} catch ( Exception $e ) {
			self::error( self::get_gdrive_exception_error( $e ), 'echo' );
		}
		return $meta;
	} // get_file_meta;

	/**
	 * Download all backup dat files.
	 *
	 * @param array $settings  Destination Settings array.
	 *
	 * @return bool  If successful or not.
	 */
	public static function download_dat_files( $settings = false ) {
		self::add_settings( $settings );

		$folder = self::get_root_folder();
		$dats   = self::get_folder_contents( $folder, array( 'query' => 'dat_files' ) );

		if ( ! count( $dats ) ) {
			return false;
		}

		$success = true;
		foreach ( $dats as $dat ) {
			$local_file = backupbuddy_core::getBackupDirectory() . '/' . $dat->getName();
			if ( ! self::getFile( $settings, $dat->getId(), $local_file ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Download file from destination to this system.
	 *
	 * @param array  $settings    Destination settings array.
	 * @param string $file_id     File identifier on destination.
	 * @param string $local_file  Full file path & name to store returned file/data into.
	 *
	 * @return bool  True on success, else false.
	 */
	public static function getFile( $settings = false, $file_id = '', $local_file = '' ) {
		if ( ! self::is_ready( $settings ) ) {
			self::error( __( 'Error #3839483b: Unable to connect with Google Drive (v2). See log for details.', 'it-l10n-backupbuddy' ), 'echo' );
			return false;
		}

		try {
			$file = self::$api->files->get( $file_id );
		} catch ( Exception $e ) {
			pb_backupbuddy::status( 'details', 'Unable to retrieve Google Drive (v2) file via `' . $file_id . '`. Attempting to lookup file by name.' );
			pb_backupbuddy::status( 'details', 'Google Drive Responded: ' . self::get_gdrive_exception_error( $e ) );
			$file = self::get_file_by_name( $file_id );
		}

		if ( ! $file ) {
			self::error( 'Error #202003271548: Unable to locate requested Google Drive (v2) file `' . $file_id . '`.', 'echo' );
			return false;
		}

		$file_id = $file->getId();

		if ( ! $local_file ) {
			$local_file = backupbuddy_core::getBackupDirectory() . $file->getName();
		}

		if ( file_exists( $local_file ) ) {
			// Do we just return, assume we're all set?
			// return true;

			// Or should we unlink the original?
			// unlink( $local_file );

			// Or perhaps change the filename?
			/*$increment = 0;
			$pathinfo  = pathinfo( $local_file );
			while ( file_exists( $local_file ) ) {
				$increment++;
				$local_file = backupbuddy_core::getBackupDirectory() . $pathinfo['basename'] . $increment . '.' . $pathinfo['extension'];
			}*/

			// Or just overwrite the existing file by not doing anything?
		}

		pb_backupbuddy::status( 'details', 'About to get Google Drive (v2) file with ID `' . $file_id . '` to store in `' . $local_file . '`.' );

		$opts = array(
			'alt' => 'media', // Instruct that file contents be returned.
		);

		pb_backupbuddy::set_greedy_script_limits();

		try {
			$content = self::$api->files->get( $file_id, $opts );
		} catch ( Exception $e ) {
			self::error( self::get_gdrive_exception_error( $e ) );
			return false;
		}

		$fh = fopen( $local_file, 'w+' );

		while ( ! $content->getBody()->eof() ) {
			fwrite( $fh, $content->getBody()->read( 1024 ) );
		}

		fclose( $fh );

		pb_backupbuddy::status( 'details', 'Google Drive (v2) file download completed successfully.' );

		return true;
	} // getFile.

	/**
	 * Get Google Drive File by filename.
	 *
	 * @param string $file_name  Name of the file.
	 *
	 * @return string|false  The file object or false on failure.
	 */
	public static function get_file_by_name( $file_name ) {
		$search = array(
			'query'      => 'exact_match',
			'query_args' => array(
				$file_name,
			),
		);

		$lookup = self::get_folder_contents( false, $search );

		if ( ! $lookup || ! is_array( $lookup ) || 1 !== count( $lookup ) ) {
			return false;
		}

		foreach ( $lookup as $index => $file ) {
			return $file;
		}

		pb_backupbuddy::status( 'details', 'Google Drive (v2) file lookup failed. An unexpected error has occurred.' );
		return false;
	}

	/**
	 * Deletes a file stored in destination.
	 *
	 * @param array  $settings  Destination Settings array.
	 * @param string $file_id   ID of file.
	 *
	 * @return bool  If delete was successful.
	 */
	public static function deleteFile( $settings = false, $file_id = '' ) {
		pb_backupbuddy::status( 'details', 'Deleting Google Drive file with ID `' . $file_id . '`.' );

		if ( ! self::is_ready( $settings ) ) {
			self::error( 'Error #4839484: Unable to connect with Google Drive (v2). See log for details.', 'echo' );
			return false;
		}

		try {
			$file = self::$api->files->get( $file_id );
		} catch ( Exception $e ) {
			pb_backupbuddy::status( 'details', 'Unable to retrieve Google Drive (v2) file via `' . $file_id . '` for deletion. Attempting to lookup file by name.' );
			pb_backupbuddy::status( 'details', 'Google Drive Responded: ' . self::get_gdrive_exception_error( $e ) );
			$file = self::get_file_by_name( $file_id );
		}

		if ( ! $file ) {
			self::error( 'Error #202004201327: Unable to locate requested Google Drive (v2) file `' . $file_id . '` for deletion.', 'echo' );
			return false;
		}

		$file_id = $file->getId();

		try {
			self::$api->files->delete( $file_id );
		} catch ( Exception $e ) {
			self::error( self::get_gdrive_exception_error( $e ), 'echo' );
			return false;
		}

		pb_backupbuddy::status( 'details', 'Google Drive (v2) file deleted.' );
		return true;
	} // deleteFile.

	/**
	 * Create a new folder in the user's GDrive at specified parent location.
	 *
	 * @param array  $settings     Destination settings array.
	 * @param string $parent_id    Parent Folder ID.
	 * @param string $folder_name  Folder name to create.
	 *
	 * @return array  ID/Title of new folder.
	 */
	public static function createFolder( $settings = false, $parent_id = false, $folder_name = '' ) {
		if ( ! $parent_id ) {
			$parent_id = self::get_root_folder();
		}

		if ( ! self::is_ready( $settings ) ) {
			global $bb_gdrive_error;
			$error = 'Error #2378327: Unable to connect with Google Drive (v2). See log for details. Details: `' . $bb_gdrive_error . '`.';
			self::error( $bb_gdrive_error, 'echo' );
			return false;
		}

		// Insert a folder.
		$drive_file = new Google_Service_Drive_DriveFile();
		$drive_file->setName( $folder_name );
		$drive_file->setMimeType( 'application/vnd.google-apps.folder' );

		// Set the parent folder.
		if ( 'root' !== $parent_id ) {
			$drive_file->setParents( array( $parent_id ) );
		}

		try {
			$create = self::$api->files->create( $drive_file );
			return array( $create->getId(), $create->getName() );
		} catch ( Exception $e ) {
			self::error( self::get_gdrive_exception_error( $e ), 'echo' );
			return false;
		}
	} // createFolder.


	/**
	 * Output the folder selector UI.
	 *
	 * @param string $destination_id  ID of the destination.
	 */
	public static function folder_selector( $destination_id ) {
		$disable_gzip = isset( pb_backupbuddy::$options['remote_destinations'][ $destination_id ]['disable_gzip'] ) ? pb_backupbuddy::$options['remote_destinations'][ $destination_id ]['disable_gzip'] : 0;

		require_once pb_backupbuddy::plugin_path() . '/destinations/gdrive2/views/folder-selector.php';

		if ( ! is_numeric( $destination_id ) ) {
			$destination_id = 'NEW';
		}
		?>
		<script>
			jQuery(function( $ ) {
				destinationWrap = backupbuddy_gdrive_getDestinationWrap( '<?php echo esc_html( $destination_id ); ?>' );

				$( '.backupbuddy-gdrive-folderSelector[data-isTemplate="true"]' ).clone().attr('data-isTemplate','false').show().appendTo( destinationWrap.find( 'td.backupbuddy-gdrive-folder-row' ) ).attr( 'data-destinationID', '<?php echo esc_html( $destination_id ); ?>' );
				backupbuddy_gdrive_folderSelect( '<?php echo esc_html( $destination_id ); ?>' );
			});
		</script>
		<?php
	} // folder_selector.


	/**
	 * Log error and add to global errors.
	 *
	 * @param string $error   Error message.
	 * @param bool   $action  Post logging action (mail, echo or both).
	 */
	public static function error( $error, $action = false ) {
		global $pb_backupbuddy_destination_errors;
		pb_backupbuddy::status( 'error', $error );
		$pb_backupbuddy_destination_errors[] = $error;
		if ( false !== $action ) {
			if ( 'mail' === $action || 'both' === $action ) {
				backupbuddy_core::mail_error( $error );
			}
			if ( 'echo' === $action || 'both' === $action ) {
				echo $error;
			}
			if ( 'alert' === $action ) {
				pb_backupbuddy::alert( $error, true );
			}
		}
	} // error.

	/**
	 * Get the error message from Google Drive JSON Exception.
	 *
	 * @param Exception $e  JSON formatted exception.
	 *
	 * @return string  The actual error.
	 */
	public static function get_gdrive_exception_error( Exception $e ) {
		$error = $e->getMessage();
		// Try to parse JSON data error message.
		if ( false !== json_decode( $e->getMessage() ) ) {
			$json = json_decode( $e->getMessage() );
			if ( isset( $json->error ) ) {
				$error = $json->error->message;
			}
		}
		return $error;
	}

	/**
	 * Callback when a Google Drive (v2) destination is deleted.
	 *
	 * @param array $settings  Destination Settings Array.
	 */
	public static function on_destination_delete( $settings ) {
		if ( ! self::is_ready( $settings ) ) {
			return false;
		}

		pb_backupbuddy::status( 'details', 'Attempting to revoke Google Drive (v2) Auth Token before Destination Delete...' );

		if ( self::$client->revokeToken() ) {
			pb_backupbuddy::status( 'details', 'Google Drive (v2) Auth Token Revoked.' );
		} else {
			pb_backupbuddy::status( 'details', 'Could not revoke Google Drive (v2) Auth Token.' );
		}
	}

} // pb_backupbuddy_destination_gdrive2.
