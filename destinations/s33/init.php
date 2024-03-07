<?php
/**
 * Amazon S3v3 Remote Destination Base Class
 *
 * @package BackupBuddy
 */

use Solid_Backups\Strauss\Aws\S3\S3Client;

/**
 * DO NOT CALL THIS CLASS DIRECTLY. CALL VIA: pb_backupbuddy_destination in bootstrap.php.
 */
class pb_backupbuddy_destination_s33 {

	/**
	 * Number of seconds to fudge up the time elapsed to give a little wiggle room so we don't accidently hit the edge and time out.
	 */
	const TIME_WIGGLE_ROOM = 6;

	/**
	 * Additional bytes needed to avoid maxing out memory.
	 */
	const MEMORY_WIGGLE_ROOM = 512000;

	/**
	 * Minimum size, in MB to allow chunks to be. Anything less will not be chunked even if requested.
	 */
	const MINIMUM_CHUNK_SIZE = 5;

	/**
	 * Used for matching during backup limits, etc to prevent processing non-BackupBuddy files.
	 */
	const BACKUP_FILENAME_PATTERN = '/^backup-.*\.zip/i';

	/**
	 * Seconds of max age to allow a stalled multipart upload. (72 hours).
	 */
	const MAX_AGE_MULTIPART_UPLOADS = 259200;

	/**
	 * Seconds to wait before retrying the check to confirm a Stash send failed if the initial confirmation failed without an explicit failure reason.
	 */
	const STASH_CONFIRM_RETRY_DELAY = 90;

	/**
	 * Destination Properties.
	 *
	 * @var array
	 */
	public static $destination_info = array(
		'name'        => 'Amazon S3',
		'description' => 'Amazon S3 is a well known cloud storage provider. This destination is known to be reliable and works well with Solid Backups. Supports both bursting and chunking. <a href="https://go.solidwp.com/amazons3" target="_blank">Learn more here.</a>',
		'category'    => 'best', // best, normal, or legacy.
	);

	/**
	 * Client object storage.
	 *
	 * @var object
	 */
	private static $_client = '';

	/**
	 * Client Signature string.
	 *
	 * @var string
	 */
	private static $_client_signature = '';

	/**
	 * Time Started.
	 *
	 * @var int
	 */
	private static $_timeStart = 0;

	/**
	 * Chunks sent this time around.
	 *
	 * @var int
	 */
	private static $_chunksSentThisRound = 0;

	/**
	 * Default settings. Should be public static for auto-merging.
	 *
	 * @var array
	 */
	public static $default_settings = array(
		'type'                          => 's33', // MUST MATCH your destination slug. Required destination field.
		'title'                         => '', // Required destination field.
		'accesskey'                     => '', // Amazon access key.
		'secretkey'                     => '', // Amazon secret key.
		'bucket'                        => '', // Amazon bucket to put into.
		'directory'                     => '', // Subdirectory to put into in addition to the site url directory.
		'ssl'                           => '1', // Whether or not to use SSL encryption for connecting.
		'server_encryption'             => 'AES256', // Encryption (if any) to have the destination enact. Empty string for none.
		'max_time'                      => '', // Default max time in seconds to allow a send to run for. Set to 0 for no time limit. Aka no chunking.
		'max_burst'                     => '10', // Max size in mb of each burst within the same page load.
		'db_archive_limit'              => '10', // Maximum number of db backups for this site in this directory for this account. No limit if zero 0.
		'full_archive_limit'            => '4', // Maximum number of full backups for this site in this directory for this account. No limit if zero 0.
		'files_archive_limit'           => '4', // Maximum number of files only backups for this site in this directory for this account. No limit if zero 0.
		'manage_all_files'              => '1', // Allow user to manage all files in S3? If enabled then user can view all files after entering their password. If disabled the link to view all is hidden.
		'region'                        => 's3.amazonaws.com', // Endpoint. Incorrectly named region here.
		'storage'                       => 'STANDARD', // Whether to use standard or reduced redundancy storage. Allowed values: STANDARD, REDUCED_REDUNDANCY.
		'use_server_cert'               => '0', // When 1, do not use the packaged cacert.pem file included with the AWS SDK and instead just use curl default.
		'disable_file_management'       => '0', // When 1, _manage.php will not load which renders remote file management DISABLED.
		//'skip_bucket_prepare'           => '0', // when 1, we will skip creating the bucket and making sure it exists before trying to place files.
		'max_filelist_keys'             => '250', // Maximum number of files to list from server via listObjects calls.
		'disabled'                      => '0', // When 1, disable this destination.
		'stash_mode'                    => '0', // When 1, this destination is wrapped with Stash.
		'live_mode'                     => '0', // When 1, this destination is wrapped in Live.
		'max_filelist_keys'             => '250', // Maximum number of files to list from server via listObjects calls.
		'disable_hostpeer_verficiation'	=> '0', // Disables SSL host/peer verification.
		'accelerate'                    => '0', // Whether or not to use endpoint acceleration. Must be enabled for bucket by user first.
		'debug_mode'                    => '0',

		// Do not store these for destination settings. Only used to pass to functions in this file.
		'_multipart_id'                 => '', // Instance var. Internal use only for continuing a chunked upload.
		'_multipart_partnumber'         => 0, // Instance var. Part number to upload next.
		'_multipart_etag_parts'         => array(), // Instance var. Etags for sent parts.
		'_multipart_file'               => '', // Instance var. Internal use only to store the file that is currently set to be multipart chunked.
		'_multipart_remotefile'         => '', // Instance var. Internal use only to store the remote filepath & file.
		'_multipart_counts'             => array(), // Instance var. Multipart chunks to send. Generated by S3's get_multipart_counts().
		'_multipart_transferspeeds'     => array(), // Instance var.
		'_multipart_backup_type'        => '', // Instance var. Type: full, db, files.
		'_multipart_backup_size'        => '', // Instance var. Total file size in bytes.
		'_retry_stash_confirm'          => false, // Whether or not we need to retry confirming the file has made it to Stash.
	);

	/**
	 * Load SDK, create S3 client, prepare bucket, format $settings & return settings.
	 *
	 * @param array $settings  Destination Settings.
	 *
	 * @return array  Array of formatted and sanitized settings.
	 */
	private static function _init( $settings ) {
		$settings = self::_formatSettings( $settings ); // Format all settings.

		// If not connected with these exact settings (by comparing signatue of $settings ) then connect & prepare bucket.
		//if ( ! isset( self::$_client ) ) {
		$newSignature = md5( serialize( $settings ) );
		if ( $newSignature != self::$_client_signature ) {
			self::$_client_signature = md5( serialize( $settings ) );

			$s3config = array();

			// Base credentials.
			$s3config['credentials'] = self::getCredentials( $settings );

			// SSL option.
			if ( '0' == $settings['ssl'] ) {
				$s3config['scheme'] = 'http';
				pb_backupbuddy::status( 'details', 'SSL disabled.' );
			}

			// Proxy (if applicable)
			if ( defined( 'WP_PROXY_HOST' ) ) {
				pb_backupbuddy::status( 'details', 'WordPress proxy setting detecred since WP_PROXY_HOST defined.' );
				if ( ! is_array( $s3config['request.options'] ) ) {
					$s3config['request.options'] = array();
				}
				$s3config['request.options']['proxy'] = WP_PROXY_HOST;
				if ( defined( 'WP_PROXY_PORT' ) ) {
					$s3config['request.options']['proxy'] .= ':' . WP_PROXY_PORT;
				}
				pb_backupbuddy::status( 'details', 'Calculated proxy URL (before user/pass added): `' . $s3config['request.options']['proxy'] . '`.' );
				if ( defined( 'WP_PROXY_USERNAME' ) ) {
					$s3config['request.options']['proxy'] = WP_PROXY_USERNAME . ':' . WP_PROXY_PASSWORD . '@' . $s3config['request.options']['proxy'];
				}
			}

			$s3config['signature_version'] = 'v4';
			$s3config['region'] = str_replace( array( '.amazonaws.com.cn', '.amazonaws.com', 's3-' ), '', $settings['region'] );
			if ( 's3' == $s3config['region'] ) {
				$s3config['region'] = 'us-east-1';
			}
			$s3config['version'] = '2006-03-01'; // Some regions now requiring this.

			if ( ! empty( $settings['client_settings'] ) ) {
				foreach ( $settings['client_settings'] as $setting => $value ) {
					$s3config[$setting] = $value;
				}
			}
			if ( ! empty( $settings['settings_override'] ) ) {
				foreach ( $settings['settings_override'] as $setting => $value ) {
					$settings[$setting] = $value;
				}
			}

			if ( pb_backupbuddy::full_logging() ) {
				//error_log( '$s3config: ' . print_r( $s3config, true ) );
			}

			if ( '1' == $settings['debug_mode'] ) {
				$s3config['debug'] = array(
					'logfn'       => function ( $msg ) {
						pb_backupbuddy::status( 'details', 'S3(v3)debug: ' . $msg );
					},
					'stream_size' => 0,
					'scrub_auth'  => true,
					'http'        => true,
				);
			}

			if ( isset( $settings['accelerate'] ) && ( '1' == $settings['accelerate'] ) ) {
				$s3config['use_accelerate_endpoint'] = true;
			}

			self::$_client = new S3Client( $s3config );

		}

		return $settings; // Formatted & updated settings.

	} // End _init().

	/**
	 * Format destination specific settings.
	 *
	 * @param array $settings  Destination Settings.
	 *
	 * @return array  Formatted Settings.
	 */
	public static function _formatSettings( $settings ) {
		// Apply defaults.
		$settings = array_merge( self::$default_settings, $settings );

		// Format bucket.
		$settings['bucket'] = strtolower( $settings['bucket'] );

		// Format directory.
		$settings['directory'] = trim( $settings['directory'], '/\\' );
		if ( '' != $settings['directory'] ) {
			$settings['directory'] .= '/';
		}

		if ( ! empty( $settings['settings_override'] ) ) {
			foreach ( $settings['settings_override'] as $setting => $value ) {
				$settings[ $setting ] = $value;
			}
		}

		if ( empty( $settings['remote_path'] ) ) {
			$settings['remote_path'] = $settings['directory'] . 'backup-' . backupbuddy_core::backup_prefix();
		}

		return $settings;
	} // End _formatSettings().

	/**
	 * Send one or more files.
	 *
	 * @param array  $settings             Destination Settings.
	 * @param array  $file                 Array of one or more files to send.
	 * @param string $send_id              Send ID.
	 * @param bool   $delete_after         If should be deleted after.
	 * @param bool   $delete_remote_after  If remote file should be deleted after send.
	 *
	 * @return bool|array  True on success, false on failure, array if a multipart chunked send so there is no status yet.
	 */
	public static function send( $settings = array(), $file = '', $send_id = '', $delete_after = false, $delete_remote_after = false ) {
		pb_backupbuddy::status( 'details', 'Starting s33 send().' );
		global $pb_backupbuddy_destination_errors;
		if ( '1' == $settings['disabled'] ) {
			$pb_backupbuddy_destination_errors[] = __( 'Error #48933: This destination is currently disabled. Enable it under this destination\'s Advanced Settings.', 'it-l10n-backupbuddy' );
			BackupBuddy_Stash_API::send_fallback_upload_results( $settings, 'Error #48933: This destination is currently disabled. Enable it under this destination\'s Advanced Settings.' );
			return false;
		}

		$settings         = self::_init( $settings ); // Handles formatting & sanitizing settings.
		$chunkSizeBytes   = $settings['max_burst'] * 1024 * 1024; // Send X mb at a time to limit memory usage.
		self::$_timeStart = microtime( true );

		if ( pb_backupbuddy::full_logging() ) {
			pb_backupbuddy::status( 'details', 'Settings due to log level: `' . print_r( $settings, true ) . '`.' );
		}

		// Initiate multipart upload.
		if ( '' == $settings['_multipart_id'] ) { // New transfer. Note: All transfers are handled as presumed multiparts for ease.
			$settings = self::init_multipart( $settings, $file );
			if ( false === $settings ) {
				return false;
			}
		} // end initiating multipart.

		// Send parts.
		$backup_type = str_replace( '/', '', $settings['_multipart_backup_type'] ); // For use later by file limiting.
		$backup_size = $settings['_multipart_backup_size'];

		$maxTime = $settings['max_time'];
		if ( '' == $maxTime || ! is_numeric( $maxTime ) ) {
			pb_backupbuddy::status( 'details', 'Max time not set in settings so detecting server max PHP runtime.' );
			$maxTime = backupbuddy_core::detectMaxExecutionTime();
		}
		pb_backupbuddy::status( 'details', 'Using max runtime: `' . $maxTime . '`.' );

		// Open file for streaming.
		$f = @fopen( $settings['_multipart_file'], 'r' );
		if ( false === $f ) {
			return self::_error( 'Error #437734. Unable to open file `' . $settings['_multipart_file'] . '` to send. Did it get deleted?' );
		}

		$fileDone = false;
		while ( ! $fileDone && ! feof( $f ) ) {
			$sendStart = microtime( true );

			if ( ! isset( $settings[ '_retry_stash_confirm' ] ) || ( true !== $settings[ '_retry_stash_confirm' ] ) ) { // Skip send if only needing to confirm.
				// Made it here so success sending part. Increment for next part to send.
				$settings['_multipart_partnumber']++;
				if ( ! isset( $settings['_multipart_counts'][ ( $settings['_multipart_partnumber'] -1  ) ]['seekTo'] ) ) {
					pb_backupbuddy::status( 'warning', 'Warning #8239933: Missing multipart partnumber to seek to. This is normal if the file is zero bytes. Settings array: `' . print_r( $settings, true ) . '`.' );
					if ( 0 == filesize( $settings['_multipart_file'] ) ) {
						$contentLength = 0;
					} else {
						pb_backupbuddy::status( 'error', 'Error #392383: Missing multipart data and NOT a zero byte file. Aborting.' );
						BackupBuddy_Stash_API::send_fallback_upload_results( $settings, 'Error #392383: Missing multipart data and NOT a zero byte file. Aborting.' );
						return false;
					}
				} else {
					if ( -1 == ( fseek( $f, (integer) $settings['_multipart_counts'][ ( $settings['_multipart_partnumber'] - 1 ) ]['seekTo'] ) ) ) {
						return self::_error( 'Error #833838: Unable to fseek file.' );
					}
					$contentLength = (integer) $settings['_multipart_counts'][ ( $settings['_multipart_partnumber'] - 1 ) ]['length'];
				}

				pb_backupbuddy::status( 'details', 'About to read in part contents of part `' . $settings['_multipart_partnumber'] . '` of `' . count( $settings['_multipart_counts'] ) . '` parts of file `' . $settings['_multipart_file'] . '` to remote location `' . $settings['_multipart_remotefile'] . '` with multipart ID `' . $settings['_multipart_id'] . '`.' );
				$uploadArr = array(
					'Bucket'        => $settings['bucket'],
					'Key'           => $settings['_multipart_remotefile'],
					'UploadId'      => $settings['_multipart_id'],
					'PartNumber'    => $settings['_multipart_partnumber'],
					'ContentLength' => $contentLength,
				);

				if ( $contentLength > 0 ) {
					if ( FALSE === ( $uploadArr['Body'] = fread( $f, $contentLength ) ) ) {
						pb_backupbuddy::status( 'error', 'Error #3498348934: Failed freading file object. Check file permissions (and existance) of file.' );
					}
				} else { // File is 0 bytes. Empty body.
					$uploadArr['Body'] = '';
					pb_backupbuddy::status( 'details', 'NOTE: Usung empty file body due to part content length of zero.' );
				}
				//pb_backupbuddy::status( 'details', 'Send array: `' . print_r( $uploadArr, true ) . '`.' );
				//error_log( print_r( $uploadArr, true ) );

				pb_backupbuddy::status( 'details', 'Beginning upload.' );
				try {
					$response = self::$_client->uploadPart( $uploadArr );
				} catch ( Exception $e ) {
					@fclose( $f );
					return self::_error( 'Error #3897923: Unable to upload file part for multipart upload of ID `' . $settings['_multipart_id'] . '`. Details: `' . "$e" . '`.' );
				}
				$uploadArr = '';
				unset( $uploadArr );

				self::$_chunksSentThisRound++;
				$settings['_multipart_etag_parts'][] = array(
					'PartNumber' => $settings['_multipart_partnumber'],
					'ETag'       => $response['ETag'],
				);

				if ( pb_backupbuddy::full_logging() ) {
					pb_backupbuddy::status( 'details', 'Success sending chunk. Upload details due to log level: `' . print_r( $response, true ) . '`.' );
				} else {
					pb_backupbuddy::status( 'details', 'Success sending chunk. Enable full logging for upload result details.' );
				}

				$uploaded_size = $contentLength;
				$elapseTime    = ( microtime(true) - $sendStart );
				if ( 0 == $elapseTime ) {
					$elapseTime = 1;
				}
				$uploaded_speed = $uploaded_size / $elapseTime;
				pb_backupbuddy::status( 'details', 'Uploaded size this burst: `' .  pb_backupbuddy::$format->file_size( $uploaded_size ) . '`, Start time: `' . $sendStart . '`. Finish time: `' . microtime(true) . '`. Elapsed: `' . (microtime(true) - $sendStart ) . '`. Speed: `' . pb_backupbuddy::$format->file_size( $uploaded_speed ) . '`/sec.' );
			}

			// Load fileoptions to the send.
			if ( isset( $fileoptions_obj ) ) {
				pb_backupbuddy::status( 'details', 'fileoptions already loaded from prior pass.' );
			} else { // load fileoptions.
				pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #5...' );
				require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
				$fileoptions_obj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt', false, false, false );
				$result          = $fileoptions_obj->is_ok();
				if ( true !== $result ) {
					return self::_error( __('Fatal Error #9034.23788723. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
				}
				pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );
				$fileoptions = &$fileoptions_obj->options;
			}

			// $update_status = 'Sent part ' . $settings['_multipart_partnumber'] . ' of ' . count( $settings['_multipart_counts'] ) . '.';

			// No more parts exist for this file. Tell S3 the multipart upload is complete and move on.
			if ( ! isset( $settings['_multipart_counts'][ $settings['_multipart_partnumber'] ] ) ) {
				// Need to retry checking that the file confirm was a success.
				if ( isset( $settings[ '_retry_stash_confirm' ] ) && ( true === $settings[ '_retry_stash_confirm' ] ) ) {

					// Grab array of files from customer's stash directory.
					$files = pb_backupbuddy_destination_stash3::listFiles( $settings, $settings['_multipart_file'] );
					if ( count( $files ) > 0 ) {
						pb_backupbuddy::status( 'details', 'Stash confirmed upload completition was successful.' );
					} else {
						pb_backupbuddy::status( 'error', 'Error #23972793: Error notifying Stash of upload success even after wait. Details: `' . print_r( $response, true ) . '`.' );
						BackupBuddy_Stash_API::send_fallback_upload_results( $settings, 'Error #23972793: Error notifying Stash of upload success even after wait. Details: `' . print_r( $response, true ) . '`.' );
						return false;
					}
				} else { // Normal Stash part send.
					$update_status = 'Sent part ' . $settings['_multipart_partnumber'] . ' of ' . count( $settings['_multipart_counts'] ) . ' parts.';

					pb_backupbuddy::status( 'details', 'Getting etags and notifying of multipart upload completion.' );
					$multipartOptions = array(
						'Bucket'          => $settings['bucket'],
						'UploadId'        => $settings['_multipart_id'],
						'Key'             => $settings['_multipart_remotefile'],
						'MultipartUpload' => array(
							'Parts' => $settings['_multipart_etag_parts'],
						),
					);
					//error_log( print_r( $multipartOptions, true ) );
					try {
						$response = self::$_client->completeMultipartUpload( $multipartOptions );
					} catch ( Exception $e ) {
						return self::_error( 'Error #84397347437: Unable to notify server of completion of all parts for multipart upload `' . $settings['_multipart_id'] . '`. Parts count: `' . count( $settings['_multipart_counts'] ) . '`. Details: `' . "$e" . '`. Multipart options: `' . print_r( $multipartOptions, true ) . '`.' );
					}
					pb_backupbuddy::status( 'details', 'Server notified of multipart completion.' );

					if ( '1' == $settings['stash_mode'] ) { // Stash send confirm.
						$response = BackupBuddy_Stash_API::send_fallback_upload_results( $settings );

						if ( false === $response ) { // May be a timeout or waiting on AWS system to combine multipart still. Check for file later.
							$settings['_retry_stash_confirm'] = true;
							$settings['_multipart_counts']    = array(); // No more parts remain.

							$cronTime   = time() + self::STASH_CONFIRM_RETRY_DELAY;
							$cronArgs   = array( $settings, $file, $send_id, $delete_after );
							$cronHashID = md5( $cronTime . serialize( $cronArgs ) );
							$cronArgs[] = $cronHashID;

							$schedule_result = backupbuddy_core::schedule_single_event( $cronTime, 'destination_send', $cronArgs );
							if ( true === $schedule_result ) {
								pb_backupbuddy::status( 'details', 'Scheduled retry attempt to confirm send in `' . self::STASH_CONFIRM_RETRY_DELAY . '` seconds.' );
							} else {
								pb_backupbuddy::status( 'error', 'Scheduled retry attempt FAILED to be scheduled.' );
							}

							/*
							 *		TODO:	Once PING API is available, request a ping in the future so we make sure this actually runs reasonably soon.
							 *				Because we need a delay we are not firing off the cron here immediately so there will be no chaining of PHP
							 *				which may result in large delays before the next process if there's little site traffic.
							 */

							return array( $settings['_multipart_id'], 'Pending multipart send confirmation.' );
						}
					}
				} // end not a Stash confirm retry.

				pb_backupbuddy::status( 'details', 'No more parts left for this multipart upload. Clearing multipart instance variables.' );
				$settings['_multipart_partnumber'] = 0;
				$settings['_multipart_id'] = '';
				$settings['_multipart_file'] = '';
				$settings['_multipart_remotefile'] = ''; // Multipart completed so safe to prevent housekeeping of incomplete multipart uploads.
				$settings['_multipart_transferspeeds'][] = $uploaded_speed;

				// Overall upload speed average.
				if ( count( $settings['_multipart_counts'] ) > 0 ) { // This can be zero if the filesize was 0 bytes (we only uplaoded an empty string in body if this was the case).
					$uploaded_speed = array_sum( $settings['_multipart_transferspeeds'] ) / count( $settings['_multipart_counts'] );
				} else {
					$uploaded_speed = $uploaded_speed;
				}
				pb_backupbuddy::status( 'details', 'Upload speed average of all chunks: `' . pb_backupbuddy::$format->file_size( $uploaded_speed ) . '`.' );

				$settings['_multipart_counts'] = array();

				// Update stats.
				$fileoptions['_multipart_status'] = $update_status;
				$fileoptions['finish_time']       = microtime( true );
				$fileoptions['status']            = 'success';
				if ( isset( $uploaded_speed ) ) {
					$fileoptions['write_speed'] = $uploaded_speed;
				}
				$fileoptions_obj->save();
				unset( $fileoptions );
				$fileDone = true;
				@fclose( $f );
			} else { // Parts remain. Schedule to continue if anything is left to upload for this multipart of any individual files.
				pb_backupbuddy::status( 'details', 'S3 multipart upload has more parts left.' );

				$update_status = '<br>';
				$totalSent     = 0;
				for( $i = 0; $i < $settings['_multipart_partnumber']; $i++ ) {
					$totalSent += $settings['_multipart_counts'][ $i ]['length'];
				}
				$percentSent    = ceil( ( $totalSent / $settings['_multipart_backup_size'] ) * 100 );
				$update_status .= '<div class="backupbuddy-progressbar" data-percent="' . $percentSent . '"><div class="backupbuddy-progressbar-label"></div></div>';

				if ( '0' != $maxTime ) { // Not unlimited time so see if we can send more bursts this time or if we need to chunk.
					// If we are within X second of reaching maximum PHP runtime then stop here so that it can be picked up in another PHP process...

					$totalSizeSent = self::$_chunksSentThisRound * $chunkSizeBytes; // Total bytes sent this PHP load.
					$bytesPerSec   = $totalSizeSent / ( microtime( true ) - $sendStart );
					$timeRemaining = $maxTime - ( ( microtime( true ) - self::$_timeStart ) + self::TIME_WIGGLE_ROOM );
					if ( $timeRemaining < 0 ) {
						$timeRemaining = 0;
					}
					$bytesWeCouldSendWithTimeLeft = $bytesPerSec * $timeRemaining;

					// Let's make sure we have the available memory to keep sending this run.
					$current_memory_usage = memory_get_usage();
					$memory_limit_string  = ini_get( 'memory_limit' );
					$memory_limit_string  = trim( $memory_limit_string );
					$memory_limit_int     = intval( $memory_limit_string );
					$memory_byte_char     = strtolower( substr( $memory_limit_string, -1 ) );

					if ( 'g' === $memory_byte_char ) {
						$memory_limit_int = $memory_limit_int * 1073741824;
					} elseif ( 'm' === $memory_byte_char ) {
						$memory_limit_int = $memory_limit_int * 1048576;
					} elseif ( 'k' === $memory_byte_char ) {
						$memory_limit_int = $memory_limit_int * 1024;
					}

					$available_memory = $memory_limit_int - $current_memory_usage;
					$needed_memory    = $totalSizeSent + $bytesWeCouldSendWithTimeLeft + self::MEMORY_WIGGLE_ROOM;

					pb_backupbuddy::status( 'details', 'Sent this burst: `' . pb_backupbuddy::$format->file_size( $totalSizeSent ) .'` in `' . (microtime(true) - $sendStart ) . '` secs. Speed: `' . pb_backupbuddy::$format->file_size( $bytesPerSec ) . '`/sec. Time Remaining (w/ wiggle): `' . $timeRemaining . '`. Size that could potentially be sent with remaining time: `' . pb_backupbuddy::$format->file_size( $bytesWeCouldSendWithTimeLeft ) . '` with chunk size of `' . pb_backupbuddy::$format->file_size( $chunkSizeBytes ) . '`. Memory Available: ' . pb_backupbuddy::$format->file_size( $available_memory ) . ' for ' . pb_backupbuddy::$format->file_size( $needed_memory ) );

					if ( $bytesWeCouldSendWithTimeLeft < $chunkSizeBytes || $needed_memory > $available_memory ) { // We can send more than a whole chunk (including wiggle room) so send another bit.
						pb_backupbuddy::status( 'message', 'Not enough time left (~`' . $timeRemaining  . '`) with max time of `' . $maxTime . '` sec to send another chunk at `' . pb_backupbuddy::$format->file_size( $bytesPerSec ) . '` / sec. Ran for ' . round( microtime( true ) - self::$_timeStart, 3 ) . ' sec. Proceeding to use chunking.' );
						@fclose( $f );
						unset( $fileoptions );

						$cronTime   = time();
						$cronArgs   = array( $settings, $file, $send_id, $delete_after );
						$cronHashID = md5( $cronTime . serialize( $cronArgs ) );
						$cronArgs[] = $cronHashID;

						// @TODO need autoloader.
						require_once( pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php' );
						$schedule_result = backupbuddy_core::trigger_async_event( 'destination_send', $cronArgs, backupbuddy_live_periodic::CRON_GROUP );
						if ( true === $schedule_result ) {
							pb_backupbuddy::status( 'details', 'Next S3 chunk step cron event scheduled.' );
						} else {
							pb_backupbuddy::status( 'error', 'Next S3 chunk step cron event FAILED to be scheduled.' );
						}

						backupbuddy_core::maybe_spawn_cron();

						return array( $settings['_multipart_id'], 'Sent part ' . $settings['_multipart_partnumber'] . ' of ' . count( $settings['_multipart_counts'] ) . ' parts.' . $update_status );
					} else { // End if.
						pb_backupbuddy::status( 'details', 'Not approaching limits. Proceeding to next burst this run.' );
					}
				} else {
					pb_backupbuddy::status( 'details', 'Max time of zero (0) so assuming unlimited time.' );
				}

				$fileoptions['_multipart_status'] = 'Sent part ' . $settings['_multipart_partnumber'] . ' of ' . count( $settings['_multipart_counts'] ) . ' parts.' . $update_status;
				$fileoptions_obj->save();
				//unset( $fileoptions );
			} // end no more parts remain.

		} // End while not feof.

		// Make sure the file got closed if we make it this far
		if ( is_resource( $f ) ) {
			@fclose( $f );
		}

		/***** BEGIN FILE ARCHIVE LIMITS *****/

		if ( '1' != $settings['live_mode'] ) {
			if ( '1' == $settings['stash_mode'] ) { // This is being wrapped by the Stash destination. Stash uses a different method of handling archive limiting due to using Stash API.
				pb_backupbuddy_destination_stash3::archiveLimit( $settings, $backup_type );
			} else { // Normal. This is just a s33 destination.
				self::archiveLimit( $settings, $backup_type );
			}
		}

		/***** END FILE ARCHIVE LIMITS *****/

		if ( isset( $fileoptions_obj ) ) {
			unset( $fileoptions_obj );
		}

		// Success if we made it this far.
		return true;
	} // End send().

	/**
	 * Init multipart file upload settings.
	 *
	 * @param array  $settings  Destination Settings.
	 * @param string $file      File to upload.
	 *
	 * @return array  Modified settings array.
	 */
	public static function init_multipart( $settings, $file ) {
		// Handle chunking of file into a multipart upload (if applicable).
		if ( is_array( $file ) ) {
			$file = $file[0];
		}
		$file_size = filesize( $file );
		pb_backupbuddy::status( 'details', 'File size of `' . pb_backupbuddy::$format->file_size( $file_size ) . '`.' );

		if ( ( ! isset( $settings['stash_mode'] ) || ( '1' != $settings['stash_mode'] ) ) && ( ! isset( $settings['live_mode'] ) || ( '1' != $settings['live_mode'] ) ) ) { // Stash handles its own cleanup and Live schedules multipart cleanup separate.
			// About to chunk so cleanup any previous hanging multipart transfers.
			self::multipart_cleanup( $settings );
		}

		// Initiate multipart upload with S3.
		$thisCall = array(
			'Bucket'               => $settings['bucket'],
			'Key'                  => $settings['directory'] . basename( $file ),
			'StorageClass'         => strtoupper( $settings['storage'] ),
			'ServerSideEncryption' => 'AES256',
		);
		if ( '1' == $settings['stash_mode'] && ( ! isset( $settings['live_mode'] ) || '1' != $settings['live_mode'] ) ) { // Stash mode but not live mode.
			$thisCall['Key'] = $settings['_stash_object'];
			unset( $thisCall['StorageClass'] );
		}

		pb_backupbuddy::status( 'details', 'Initiating multipart transfer.' );

		try {
			$response = self::$_client->createMultipartUpload( $thisCall );
		} catch ( Exception $e ) {
			if ( pb_backupbuddy::full_logging() ) {
				pb_backupbuddy::status( 'details', 'Call details due to logging level: `' . print_r( $thisCall, true ) . '`.' );
			}
			$message = $e->getMessage();

			// If token has expired for Stash Live or Stash then clear cached token for next file.
			if ( ( ( '1' == $settings['live_mode'] ) || ( '1' == $settings['stash_mode'] ) ) && ( strpos( $message, 'token has expired' ) !== false ) ) {
				pb_backupbuddy::status( 'details', 'Clearing Live credentials transient because token has expired.' );
				delete_transient( pb_backupbuddy_destination_live::LIVE_ACTION_TRANSIENT_NAME );
			}

			return self::_error( 'Error #389383b: Unable to initiate multipart upload for file `' . $file . '`. Details: `' . "$e" . '`.' );
		}

		// Made it here so SUCCESS initiating multipart!
		$upload_id = (string) $response['UploadId'];
		pb_backupbuddy::status( 'details', 'Initiated multipart upload with ID `' . $upload_id . '`.' );

		$backup_type = backupbuddy_core::getBackupTypeFromFile( $file );

		// Calculate multipart settings.
		$multipart_destination_settings = $settings;
		$multipart_destination_settings['_multipart_id'] = $upload_id;
		$multipart_destination_settings['_multipart_partnumber'] = 0;
		$multipart_destination_settings['_multipart_file'] = $file;
		$multipart_destination_settings['_multipart_remotefile'] = $settings['directory'] . basename( $file );
		if ( '1' == $settings['stash_mode'] ) {
			$multipart_destination_settings['_multipart_remotefile'] = $settings['_stash_object'];
		}
		$multipart_destination_settings['_multipart_counts'] = self::_get_multipart_counts( $file_size, $settings['max_burst'] * 1024 * 1024 ); // Size of chunks expected to be in bytes.
		$multipart_destination_settings['_multipart_backup_type'] = $backup_type;
		$multipart_destination_settings['_multipart_backup_size'] = $file_size;
		$multipart_destination_settings['_multipart_etag_parts'] = array();

		//pb_backupbuddy::status( 'details', 'Multipart settings to pass:' . print_r( $multipart_destination_settings, true ) );
		//$multipart_destination_settings['_multipart_status'] = 'Starting send of ' . count( $multipart_destination_settings['_multipart_counts'] ) . ' parts.';
		pb_backupbuddy::status( 'details', 'Multipart initiated; passing over to send first chunk this run. Burst size: `' . $settings['max_burst'] . ' MB`.' );
		$settings = $multipart_destination_settings; // Copy over settings.
		unset( $multipart_destination_settings );

		return $settings;
	}

	/**
	 * Enforce Archive Limits
	 *
	 * @param array  $settings     Destination Settings.
	 * @param string $backup_type  Type of backup.
	 *
	 * @return bool  If limits enforced.
	 */
	public static function archiveLimit( $settings, $backup_type ) {

		if ( $backup_type == 'full' ) {
			$limit = $settings['full_archive_limit'];
			pb_backupbuddy::status( 'details', 'Full backup archive limit of `' . $limit . '` of type `full` based on destination settings.' );
		} elseif ( $backup_type == 'db' ) {
			$limit = $settings['db_archive_limit'];
			pb_backupbuddy::status( 'details', 'Database backup archive limit of `' . $limit . '` of type `db` based on destination settings.' );
		} elseif ( $backup_type == 'files' ) {
			$limit = $settings['files_archive_limit'];
			pb_backupbuddy::status( 'details', 'Backup archive limit of `' . $limit . '` of type `files` based on destination settings.' );
		} elseif ( $backup_type == 'themes' ) {
			$limit = $settings['themes_archive_limit'];
			pb_backupbuddy::status( 'details', 'Backup archive limit of `' . $limit . '` of type `themes` based on destination settings.' );
		} elseif ( $backup_type == 'plugins' ) {
			$limit = $settings['plugins_archive_limit'];
			pb_backupbuddy::status( 'details', 'Backup archive limit of `' . $limit . '` of type `plugins` based on destination settings.' );
		} elseif ( $backup_type == 'media' ) {
			$limit = $settings['media_archive_limit'];
			pb_backupbuddy::status( 'details', 'Backup archive limit of `' . $limit . '` of type `media` based on destination settings.' );
		} else {
			$limit = 0;
			pb_backupbuddy::status( 'warning', 'Warning #237332. Unable to determine backup type (reported: `' . $backup_type . '`) so archive limits NOT enforced for this backup.' );
		}
		if ( $limit > 0 ) {

			pb_backupbuddy::status( 'details', 'Archive limit enforcement beginning.' );

			// Get file listing.
			try {
				// List all users files in this directory that are a backup for this site (limited by prefix).
				$response_manage = self::$_client->listObjects(
					array(
						'Bucket' => $settings['bucket'],
						'Prefix' => $settings['directory'] . 'backup-' . backupbuddy_core::backup_prefix(),
					)
				);
			} catch ( Exception $e ) {
				return self::_error( 'Error #9338292: Unable to list files for archive limiting. Details: `' . "$e" . '`.' );
			}
			if ( ! is_array( $response_manage['Contents'] ) ) {
				$response_manage['Contents'] = array();
			}

			// List backups associated with this site by date.
			$backups = array();
			foreach ( $response_manage['Contents'] as $object ) {
				$file = str_replace( $settings['directory'], '', $object['Key'] );
				if ( $backup_type != backupbuddy_core::getBackupTypeFromFile( $file, true ) ) {
					continue; // Not of the same backup type.
				}
				$backups[ $file ] = strtotime( $object['LastModified'] );
			}
			arsort( $backups );

			pb_backupbuddy::status( 'details', 'Found `' . count( $backups ) . '` backups of this type when checking archive limits out of `' . count( $response_manage['Contents'] ) . '` total files in this location.' );

			if ( count( $backups ) > $limit ) {
				pb_backupbuddy::status( 'details', 'More archives (' . count( $backups ) . ') than limit (' . $limit . ') allows. Trimming...' );

				$i                 = 0;
				$delete_fail_count = 0;

				if ( ! class_exists( 'pb_backupbuddy_destinations' ) ) {
					require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
				}

				foreach ( $backups as $buname => $butime ) {
					$i++;
					if ( $i > $limit ) {
						pb_backupbuddy::status( 'details', 'Trimming excess file `' . $buname . '`...' );

						if ( true !== pb_backupbuddy_destinations::delete( $settings, $buname ) ) {
							self::_error( 'Unable to delete excess Stash file `' . $buname . '`.' );
							$delete_fail_count++;
						}
					}
				} // end foreach.
				pb_backupbuddy::status( 'details', 'Finished trimming excess backups.' );
				if ( 0 !== $delete_fail_count ) {
					$error_message = 'Stash remote limit could not delete ' . $delete_fail_count . ' backups.';
					pb_backupbuddy::status( 'error', $error_message );
					backupbuddy_core::mail_error( $error_message );
				}
			}

			pb_backupbuddy::status( 'details', 'Stash completed archive limiting.' );

		} else {
			pb_backupbuddy::status( 'details', 'No Stash archive file limit to enforce.' );
		} // End remote backup limit

		return true;
	} // End archiveLimit().

	/**
	 * Get remote files from S3.
	 *
	 * @param array $settings  Destination settings.
	 *
	 * @return array|string  String when error, otherwise array of files.
	 */
	public static function get_files( $settings ) {
		$settings = self::_init( $settings );
		$prefix   = ! empty( $settings['remote_path'] ) ? $settings['remote_path'] : '';
		$options  = array(
			'Bucket' => $settings['bucket'],
			'Prefix' => $prefix,
		);

		if ( isset( $settings['max_filelist_keys'] ) ) {
			$options['MaxKeys'] = $settings['max_filelist_keys'];
		}
		if ( ! empty( $settings['marker'] ) ) {
			$options['Marker'] = $settings['marker'];
		}

		try {
			$response = self::$_client->listObjects( $options ); // list all the files in the subscriber account.
		} catch ( Exception $e ) {
			$error = 'Error #838393: Unable to list files. Details: `' . "$e" . '`. Bucket: `' . $settings['bucket'] . '`. Prefix: `' . $prefix . '`.';
			if ( stristr( $error, 'Please send all future requests to this endpoint' ) ) {
				$error .= ' TO FIX THIS: Navigate to the settings for this destination and change the `Bucket region` to the correct location.';
			}
			self::_error( $error );
			return $error;
		}

		return (array) $response['Contents'];
	}

	/**
	 * List remote destination files.
	 *
	 * @param array  $settings  Destination Settings.
	 * @param string $mode      Output mode.
	 *
	 * @return array  Array of files or string error message.
	 */
	public static function listFiles( $settings, $mode = 'default' ) {
		global $pb_backupbuddy_destination_errors;
		if ( '1' == $settings['disabled'] ) {
			$pb_backupbuddy_destination_errors[] = __( 'Error #48933: This destination is currently disabled. Enable it under this destination\'s Advanced Settings.', 'it-l10n-backupbuddy' );
			return false;
		}

		$prefix  = ! empty( $settings['remote_path'] ) ? $settings['remote_path'] : '';
		$backups = self::get_files( $settings );
		$backups = is_array( $backups ) ? $backups : array();

		$backup_list       = array();
		$backup_sort_dates = array();

		foreach ( $backups as $object ) {
			$backup = str_ireplace( $settings['directory'], '', $object['Key'] );
			$backup = ltrim( $backup, '/' );
			if ( false !== stristr( $backup, '/' ) ) { // Do NOT display any files within a deeper subdirectory.
				continue;
			}
			if ( ! preg_match( pb_backupbuddy_destination_s33::BACKUP_FILENAME_PATTERN, $backup ) && 'importbuddy.php' !== $backup ) { // Do not display any files that do not appear to be a Solid Backups backup file (except importbuddy.php).
				continue;
			}

			$backup_type = backupbuddy_core::getBackupTypeFromFile( $backup );

			if ( ! $backup_type ) {
				continue;
			}

			$uploaded      = strtotime( $object['LastModified'] );
			$backup_date   = backupbuddy_core::parse_file( $backup, 'datetime' );
			$size          = (double) $object['Size'];
			$download_link = self::getFileURL( $settings, $backup );

			$backup_array = array(
				array(
					$backup,
					$backup_date,
					$download_link,
				),
				backupbuddy_core::pretty_backup_type( $backup_type ),
				pb_backupbuddy::$format->file_size( $size ),
			);

			if ( 'default' === $mode ) {
				$copy_link      = '&cpy=' . rawurlencode( $backup ) . '&remote_path=' . rawurlencode( $prefix );
				$actions        = array(
					$download_link => __( 'Download Backup', 'it-l10n-backupbuddy' ),
					$copy_link     => __( 'Copy to Local', 'it-l10n-backupbuddy' ),
				);
				$backup_array[] = backupbuddy_backups()->get_action_menu( $backup, $actions );
			} elseif ( 'restore' === $mode ) {
				$backup_array[] = backupbuddy_backups()->get_details_link( $backup );
				$backup_array[] = backupbuddy_backups()->get_restore_buttons( $backup, $backup_type );
			}

			$backup_list[ $backup ]       = $backup_array;
			$backup_sort_dates[ $backup ] = $backup_date;
		}

		$backup_list = backupbuddy_backups()->sort_backups( $backup_list, $backup_sort_dates );

		return $backup_list;
	} // End listFiles().

	/**
	 * Alias to deleteFiles().
	 *
	 * @param array        $settings  Destination Settings.
	 * @param string|array $file      File to delete.
	 *
	 * @return bool  If file was deleted.
	 */
	public static function deleteFile( $settings, $file ) {
		return self::delete( $settings, $file );
	} // End deleteFile().

	/**
	 * Deletes file(s) from remote destination.
	 *
	 * @param array        $settings  Destination Settings.
	 * @param string|array $files     File(s) to delete.
	 *
	 * @return bool  If file(s) were deleted.
	 */
	public static function deleteFiles( $settings, $files = array() ) {
		return self::delete( $settings, $files );
	} // End deleteFiles().

	/**
	 * Deletes file(s) from remote destination.
	 *
	 * @param array        $settings  Destination Settings.
	 * @param string|array $files     File(s) to delete.
	 *
	 * @return bool  If file(s) were deleted.
	 */
	public static function delete( $settings, $files = array() ) {
		$settings = self::_init( $settings );
		if ( ! is_array( $files ) ) {
			$files = array( $files );
		}

		$fileKeys = array();
		foreach ( (array) $files as $file ) {
			$path       = rtrim( $settings['directory'], '/\\' );
			$path       = ( empty( $path ) ) ? $path : ( $path . '/' );
			$fileKeys[] = array(
				'Key' => ( $path . ltrim( $file, '/\\' ) ),
			);
		}

		try {
			$response = self::$_client->deleteObjects(
				array(
					'Bucket' => $settings['bucket'],
					'Delete' => array(
						'Objects' => $fileKeys,
					),
				)
			);
		} catch ( Exception $e ) {
			$error = 'Error #83823233393b: Unable to delete one or more files. Details: `' . "$e" . '`.';
			self::_error( $error );
			return $error;
		}

		$error = '';
		if ( isset( $response['Errors'] ) ) {
			foreach ( $response['Errors'] as $responseError ) {
				$error .= $responseError['Message'];
			}
			pb_backupbuddy::status( 'error', $error );
			return $error;
		}

		return true;
	}

	/**
	 * Tests ability to write to this remote destination.
	 *
	 * @param array $settings  Destination settings.
	 *
	 * @return bool  True on success, string error message on failure.
	 */
	public static function test( $settings ) {
		$settings = self::_init( $settings );

		$sendOK    = false;
		$deleteOK  = false;
		$send_id   = 'TEST-' . pb_backupbuddy::random_string( 12 );
		$test_file = pb_backupbuddy::plugin_path() . '/destinations/remote-send-test.php';

		// Try sending a file.
		if ( '1' == $settings['stash_mode'] && ( ! isset( $settings['live_mode'] ) || '1' != $settings['live_mode'] ) ) { // Stash mode but not live mode.
			$settings['type'] = 'stash3';
		}

		$send_response = pb_backupbuddy_destinations::send( $settings, $test_file, $send_id, false, true ); // 3rd param true forces clearing of any current uploads.

		if ( true === $send_response ) {
			$send_response = __( 'Success.', 'it-l10n-backupbuddy' );
			$sendOK        = true;
		} else {
			global $pb_backupbuddy_destination_errors;
			$send_response = 'Error sending test file to S3 (v3). Details: `' . implode( ', ', $pb_backupbuddy_destination_errors ) . '`.';
		}

		pb_backupbuddy::add_status_serial( 'remote_send-' . $send_id );

		// Delete sent file if it was sent.
		$delete_response = 'n/a';
		if ( true === $sendOK ) {
			pb_backupbuddy::status( 'details', 'Preparing to delete sent test file.' );

			if ( '1' == $settings['stash_mode'] ) { // Stash mode.
				if ( true === ( $delete_response = pb_backupbuddy_destination_stash3::delete( $settings, basename( $test_file ) ) ) ) { // success
					$delete_response = __( 'Success.', 'it-l10n-backupbuddy' );
					$deleteOK        = true;
				} else { // error
					$error           = 'Unable to delete Stash test file `remote-send-test.php`. Details: `' . $delete_response . '`.';
					$delete_response = $error;
					$deleteOK        = false;
				}
			} else { // S3 mode.
				if ( true === ( $delete_response = self::delete( $settings, basename( $test_file ) ) ) ) {
					$delete_response = __( 'Success.', 'it-l10n-backupbuddy' );
					$deleteOK        = true;
				} else {
					$error = 'Unable to delete test file `remote-send-test.php`. Details: `' . $delete_response . '`.';
					pb_backupbuddy::status( 'details', $error );
					$delete_response = $error;
					$deleteOK        = false;
				}
			}
		} else { // end if $sendOK.
			pb_backupbuddy::status( 'details', 'Skipping test delete due to failed send.' );
		}

		// Load destination fileoptions.
		pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #4...' );
		require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
		$fileoptions_obj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt', false, false, false );
		$result          = $fileoptions_obj->is_ok();
		if ( true !== $result ) {
			return self::_error( __( 'Fatal Error #9034.84838. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
		}
		pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );
		$fileoptions = &$fileoptions_obj->options;

		if ( true !== $sendOK || true !== $deleteOK ) {
			$fileoptions['status'] = 'failure';
			$fileoptions_obj->save();
			unset( $fileoptions_obj );

			return 'Send details: `' . $send_response . '`. Delete details: `' . $delete_response . '`.';
		} else {
			$fileoptions['status']      = 'success';
			$fileoptions['finish_time'] = time();
		}

		$fileoptions_obj->save();
		unset( $fileoptions_obj );

		pb_backupbuddy::status( 'details', 'Finished test function.' );
		return true;
	} // End test().

	/**
	 * Download remote file to local system.
	 *
	 * @param array  $settings              Destination settings.
	 * @param string $remoteFile            Remote filename.
	 * @param string $localDestinationFile  Full path & filename of destination file.
	 *
	 * @return bool|string  True if succesful, string if error.
	 */
	public static function getFile( $settings, $remoteFile, $localDestinationFile ) {
		$settings = self::_init( $settings );

		pb_backupbuddy::status( 'details', 'Downloading remote file `' . $remoteFile . '` from S3 to local file `' . $localDestinationFile . '`.' );

		try {
			$response = self::$_client->getObject( array(
				'Bucket' => $settings['bucket'],
				'Key'    => $settings['directory'] . $remoteFile,
				'SaveAs' => $localDestinationFile,
			) );
		} catch ( Exception $e ) {
			return self::_error( 'Error #382938: Unable to retrieve file. Details: `' . $e . '`.' );
		}

		return true;
	} // end getFile().

	/**
	 * Download all backup dat files.
	 *
	 * @param array $settings  Destination Settings array.
	 *
	 * @return bool  If successful or not.
	 */
	public static function download_dat_files( $settings ) {
		$settings = self::_init( $settings );
		$backups  = self::listFiles( $settings );
		$success  = true;

		if ( ! count( $backups ) ) {
			return false;
		}

		foreach ( $backups as $backup_array ) {
			$backup_file = $backup_array[0][0];
			$dat_file    = str_replace( '.zip', '.dat', $backup_file ); // TODO: Move to backupbuddy_data_file() method.
			$local_file  = backupbuddy_core::getBackupDirectory() . $dat_file;

			if ( true !== self::getFile( $settings, $dat_file, $local_file ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Get a list of dat files not associated with backups.
	 *
	 * @param array $settings  Destination Settings array.
	 *
	 * @return array  Array of dat files.
	 */
	public static function get_dat_orphans( $settings ) {
		$settings = self::_init( $settings );
		$backups_array = self::listFiles( $settings );
		if ( ! is_array( $backups_array ) ) {
			return false;
		}

		$orphans = array();
		$backups = array();
		$files   = self::get_files( $settings );

		if ( ! is_array( $files ) ) {
			return false;
		}

		// Create an array of backup filenames.
		foreach ( $backups_array as $backup_array ) {
			$backups[] = $backup_array[0][0];
		}

		$prefix = backupbuddy_core::backup_prefix();

		if ( $prefix ) {
			$prefix .= '-';
		}

		// Loop through dat files looking for orphans.
		foreach ( $files as $file ) {
			$filename = str_ireplace( $settings['directory'], '', $file['Key'] );

			// Appears to not be a dat file for this site.
			if ( strpos( $filename, 'backup-' . $prefix ) === false ) {
				continue;
			}

			// Skip dat files with backup files.
			$backup_name = str_replace( '.dat', '.zip', $filename ); // TODO: Move to backupbuddy_data_file() method.
			if ( in_array( $backup_name, $backups, true ) ) {
				continue;
			}

			$orphans[] = $filename;
		}

		return $orphans;
	}

	/**
	 * Get download URL for a file.
	 *
	 * @param array  $settings     Destination settings.
	 * @param string $remote_file  Filename of remote file. Does NOT contain path as it is calculated from $settings.
	 *
	 * @return string  URL to file or error.
	 */
	public static function getFileURL( $settings, $remote_file ) {
		$settings = self::_init( $settings );
		pb_backupbuddy::status( 'details', 'Getting download URL.' );

		$command = self::$_client->getCommand(
			'GetObject',
			array(
				'Bucket' => $settings['bucket'],
				'Key'    => $settings['directory'] . $remote_file,
			)
		);

		try {
			$request = self::$_client->createPresignedRequest( $command, '+60 minutes' );
		} catch ( Exception $e ) {
			return self::_error( 'Error #349734634: Unable to retrieve file URL. Details: `' . $e . '`.' );
		}

		$response = (string) $request->getUri();
		return $response;
	} // End getFileURL().

	/**
	 * Get the required credentials and management data for managing user files.
	 *
	 * @param array $settings  Destination settings.
	 *
	 * @return false|array  Boolean false on failure. Array of data on success.
	 */
	public static function getCredentials( $settings ) {
		$settings['bucket'] = strtolower( $settings['bucket'] ); // Buckets must be lowercase.

		if ( isset( $settings['credentials'] ) ) {
			$credentials = $settings['credentials'];
		} else {
			$credentials['key']    = $settings['accesskey'];
			$credentials['secret'] = $settings['secretkey'];
		}

		return $credentials;

	} // End getCredentials().

	/**
	 * Get the bucket region.
	 *
	 * @param string $bucket  Bucket name.
	 *
	 * @return string  Bucket region.
	 */
	public static function get_bucket_region( $bucket ) {
		try {
			$result = self::$_client->getBucketLocation(array(
				'Bucket' => $bucket,
			));
		} catch ( Exception $e ) {
			throw new Exception( $e );
		}

		if ( ! isset( $result->Location ) ) { // Blank means default standard location.
			return 'us-east-1';
		}

		$location = $result->Location;
		if ( 'EU' == $location ) {
			$location = 'eu-west-1';
		}

		return $location;
	}

	/**
	 * S3 does NOT automatically clean up failed or expired multipart chunk files so clean up for them.
	 *
	 * @param array $settings  Destination Settings.
	 *
	 * @return bool  If cleaned up.
	 */
	public static function multipart_cleanup( $settings ) {
		$settings = self::_init( $settings ); // Handles formatting & sanitizing settings.

		pb_backupbuddy::status( 'details', 'AWS Multipart cleanup beginning.' );
		$backupDetectPrefix = 'backup-';
		if ( isset( $settings['live_mode'] ) && '1' == $settings['live_mode'] ) {
			$backupDetectPrefix = '';
		}

		try {
			$response = self::$_client->listMultipartUploads( array(
				'Bucket' => $settings['bucket'],
				'prefix' => $settings['directory'] . $backupDetectPrefix,
			) );
		} catch ( Exception $e ) {
			return self::_error( 'Error #84397849347: Unable to list existing multipart uploads. Details: `' . "$e" . '`' );
		}

		if ( is_object( $response ) ) {
			$uploads = (array) $response->get( 'Uploads' );
		} else {
			$uploads = isset( $response['Uploads'] ) ? count( (array) $response['Uploads'] ) : array();
		}

		$parts = count( $uploads );

		if ( pb_backupbuddy::full_logging()) {
			pb_backupbuddy::status( 'details', 'Multipart upload check retrieved. Found `' . $parts . '` multipart uploads in progress / stalled. Full logging mode details: `' . print_r( $response, true ) . '`' );
		} else {
			pb_backupbuddy::status( 'details', 'Multipart upload check retrieved. Found `' . $parts . '` multipart uploads in progress / stalled. Old Solid Backups parts will be cleaned up (if any found) ...' );
		}

		// Loop through each incomplete multipart upload.
		foreach ( $uploads as $upload ) {
			if ( pb_backupbuddy::full_logging() ) {
				pb_backupbuddy::status( 'details', 'Checking upload (full logging mode): ' . print_r( $upload, true ) );
			}
			//if ( FALSE !== stristr( $upload['Key'], $backupDetectPrefix ) ) { // Solid Backups backup file.
			$initiated = strtotime( $upload['Initiated'] );
			if ( pb_backupbuddy::full_logging() ) {
				pb_backupbuddy::status( 'details', 'Multipart Chunked Upload(s) detected in progress. Full logging age: `' . pb_backupbuddy::$format->time_ago( $initiated ) . '`.' );
			}

			// If too old then cancel it.
			if ( ( $initiated + self::MAX_AGE_MULTIPART_UPLOADS ) < time() ) {
				pb_backupbuddy::status( 'details', 'Aborting stalled Multipart Chunked Upload with ID `' . $upload->UploadId . '` of age `' . pb_backupbuddy::$format->time_ago( $initiated ) . '`.' );
				try {
					self::$_client->abortMultipartUpload( array(
						'Bucket'   => $settings['bucket'],
						'Key'      => $upload['Key'],
						'UploadId' => $upload['UploadId'],
					) );
					pb_backupbuddy::status( 'details', 'Abort success.' );
				} catch ( Exception $e ) {
					pb_backupbuddy::status( 'error', 'Stalled Multipart Chunked abort of file `' . $upload['Key'] . '` with ID `' . $upload['UploadId'] . '` FAILED. Manually abort it. Details: `' . $e . '`.' );
				}
			} else {
				if ( ! pb_backupbuddy::full_logging() ) {
					pb_backupbuddy::status( 'details', 'Multipart Chunked Uploads not aborted as not too old.' );
				}
			}
			//}
		} // end foreach.

		pb_backupbuddy::status( 'details', 'AWS Multipart cleanup finished.' );
		return true;

	} // end multipart_cleanup().

	/**
	 * Validates bucket existance, creating if needed.  Sets region for non-US usage.
	 *
	 * @param array $settings      Destination settings array.
	 * @param bool  $createBucket  Whether or not to create bucket if it does not currently exist.
	 *
	 * @return bool  True on all okay, false otherwise.
	 */
	private static function _prepareBucketAndRegion( $settings, $createBucket = true ) {
		$error = 'Solid Backups Error #32823893: Obsolete function call.';
		error_log( $error );
		echo $error;
		return false;

		$settings = self::_formatSettings( $settings ); // Format all settings.

		if ( '1' == $settings['skip_bucket_prepare'] ) {
			pb_backupbuddy::status( 'details', 'Skipping bucket preparation based on destination settings.' );
			return true;
		}

		// Get bucket region to determine if a bucket already exists.
		// Assume we will not have to try and create a bucket
		$maybe_create_bucket = false;
		pb_backupbuddy::status( 'details', 'Getting region for bucket: `' . $settings['bucket'] . "`." );

		try {
			$detectedRegion = self::get_bucket_region( $settings['bucket'] );
			$result         = self::$_client->getBucketLocation( array(
				'Bucket' => $settings['bucket'],
			));
			pb_backupbuddy::status( 'details', 'Server indicates region: ' . $detectedRegion );
			$settings['region'] = $detectedRegion; // Override passed region.
		} catch ( Exception $e ) {
			$detectedRegion      = '';
			$maybe_create_bucket = true;
			$message             = 'Exception retrieving information for bucket `' . $settings['bucket'] . '`. Assuming region in $settings correct. If using IAM security, verify this resource ALLOWs the action "s3:GetBucketLocation". Details: `' . "$e" . '`. Full result: `' . print_r( $result, true ) . '`.';
			if ( pb_backupbuddy::full_logging() ) {
				pb_backupbuddy::status( 'details', 'Settings used due to log level: `' . print_r( $settings, true ) . '`.' );
			}
			echo $message;
			pb_backupbuddy::status( 'warning', $message );
			//self::_error ( $message );
		}

		try {
			self::$_client->setRegion( $settings['region'] );
		} catch ( Exception $e ) {
			throw new Exception( 'Unable to set region. Details: `' . "$e" . '`.' );
		}

		// In bucket creation mode AND bucket did not already exist.
		if ( true === $createBucket && true === $maybe_create_bucket ) {
			pb_backupbuddy::status( 'details', 'Attempting to create bucket `' . $settings['bucket'] . '` at region endpoint `' . $settings['region'] . '` (detected region: `' . $detectedRegion . '`).' );
			try {
				$response = self::$_client->createBucket( array(
					'ACL' => 'private',
					'Bucket' => $settings['bucket'],
					'LocationConstraint' => $settings['region']
				) );
			} catch ( Exception $e ) {
				return self::_error( 'Error #3892833b: Unable to create bucket. Details: `' . "$e" . '`.' );
			}

		} // end if create bucket.

		return true;

	} // end _prepareBucketAndRegion().


	/**
	 * Taken from v1 s3 SDK.
	 *
	 * @param int $filesize   File size.
	 * @param int $part_size  Part size.
	 *
	 * @return int  Number of parts.
	 */
	public static function _get_multipart_counts( $filesize, $part_size ) {
		$i         = 0;
		$sizecount = $filesize;
		$values    = array();

		while ( $sizecount > 0 ) {
			$sizecount -= $part_size;
			$values[]   = array(
				'seekTo' => $part_size * $i,
				'length' => $sizecount > 0 ? $part_size : $sizecount + $part_size,
			);
			$i++;
		}

		if ( pb_backupbuddy::full_logging() ) {
			pb_backupbuddy::status( 'details', 'Multipart counts due to log level: `' . print_r( $values, true ) . '`.' );
		}
		return $values;
	}

	/**
	 * Handle Error.
	 *
	 * @param string $message  The error message.
	 *
	 * @return bool  False.
	 */
	private static function _error( $message ) {
		global $pb_backupbuddy_destination_errors;
		$pb_backupbuddy_destination_errors[] = $message;
		pb_backupbuddy::status( 'error', $message );
		return false;
	}

} // End class.
