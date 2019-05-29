<?php

final class BackupBuddy_Stash_Util {
	const API_URL = 'https://stash-api-2.ithemes.com';
	const LEGACY_API_URL = 'https://stash-api.ithemes.com';

	// These variables are used by the send_file() function to reliably send error details back to the stash-api server.
	private static $username;
	private static $token;
	private static $upload_id;
	private static $start_time;
	private static $start_memory;


	public static function send_file( $username, $token, $file ) {
		$credentials = self::get_upload_credentials( $username, $token, $file );

		if ( false === $credentials ) {
			return false;
		}


		self::$username = $username;
		self::$token = $token;
		self::$upload_id = $credentials['id'];
		self::$start_time = microtime( true );
		self::$start_memory = memory_get_peak_usage( true );
		$error = 'unknown';

		try {
			if ( function_exists( 'error_get_last' ) ) {
				// This is used to trap fatal errors and send their details to the stash-api server.
				register_shutdown_function( 'backupbuddy_stash_util_handle_shutdown' );
			}

			if ( 'aws2' === $credentials['method'] ) {
				$result = self::send_file_with_v2( $file, $credentials );
			} else if ( 'aws3' === $credentials['method'] ) {
				$result = self::send_file_with_v3( $file, $credentials );
			}
		} catch ( Throwable $e ) {
			$error = self::get_error_string_from_exception( $e );
			$result = false;
		} catch ( Exception $e ) {
			$error = self::get_error_string_from_exception( $e );
			$result = false;
		}

		$time = microtime( true ) - self::$start_time;
		$memory = memory_get_peak_usage( true ) - self::$start_memory;

		self::$upload_id = 0;

		if ( $result ) {
			$error = '';
		}

		self::send_upload_results( $username, $token, $credentials['id'], $time, $memory, $error );
		self::trim( $username, $token );

		return $result;
	}

	private static function trim( $username, $token ) {
		if ( ! isset( pb_backupbuddy::$options['remote_destinations'] ) ) {
			// Cannot load settings for the remote destinations. Skip trimming.
			return;
		}

		$source = self::get_call_source();

		foreach ( pb_backupbuddy::$options['remote_destinations'] as $settings ) {
			if ( $settings['type'] === $source ) {
				break;
			}

			unset( $settings );
		}

		if ( ! isset( $settings ) ) {
			// Unable to find settings for the current source. Skip trimming.
			return;
		}

		$default_types = array(
			'db_archive_limit'      => 0,
			'full_archive_limit'    => 0,
			'themes_archive_limit'  => 0,
			'plugins_archive_limit' => 0,
			'media_archive_limit'   => 0,
			'files_archive_limit'   => 0,
		);

		$types = array_intersect_key( $settings, $default_types );

		if ( count( $types ) < count( $default_types ) ) {
			// Missing all types settings. Skip trimming.
			return;
		}

		foreach ( $types as $key => $val ) {
			$types[substr( $key, 0, -14 )] = $val;
			unset( $types[$key] );
		}

		$params = array(
			'types'  => $types,
			'delete' => true,
		);


		if ( '3' == pb_backupbuddy::$options['log_level'] ) { // Full logging enabled.
			pb_backupbuddy::status( 'details', 'Trim params based on settings: `' . print_r( $params, true ) . '`.' );
		}

		$settings = compact( 'username', 'token' );
		$response = self::request( 'trim', $settings, $params );

		if ( is_string( $response ) ) {
			if ( 'stash2' === $source ) {
				$error = 'Error #83279768543973: Unable to trim Stash (v2) upload. Details: `' . print_r( $response, true ) . '`.';
			} else {
				$error = 'Error #8329545445573: Unable to trim Stash (v3) upload. Details: `' . print_r( $response, true ) . '`.';
			}

			self::record_error( $error );
		} else {
			pb_backupbuddy::status( 'details', 'Trimmed remote archives. Results: `' . print_r( $response, true ) . '`.' );
		}
	}

	private static function send_upload_results( $username, $token, $id, $time, $memory, $error ) {
		$settings = compact( 'username', 'token' );
		$params = compact( 'id', 'time', 'memory', 'error' );

		self::request( 'send-upload-results', $settings, $params, false );
	}

	private static function send_file_with_v2( $file, $credentials ) {
		require_once( dirname( dirname( dirname( __FILE__ ) ) ) . '/destinations/_s3lib2/aws-autoloader.php' );

		$s3 = \Aws\S3\S3Client::factory( $credentials['client_settings'] );

		$uploader = \Aws\S3\Model\MultipartUpload\UploadBuilder::newInstance();
		$uploader->setClient( $s3 );
		$uploader->setSource( $file );
		$uploader->setBucket( $credentials['bucket'] );
		$uploader->setKey( $credentials['path'] );

		if ( ! empty( $credentials['upload_builder_options'] ) ) {
			if ( ! empty( $credentials['upload_builder_options']['concurrency'] ) ) {
				$uploader->setConcurrency( $credentials['upload_builder_options']['concurrency'] );
			}
		}

		$result = $uploader->build()->upload();

		if ( $result ) {
			return true;
		}

		return false;
	}

	private static function send_file_with_v3( $file, $credentials ) {
		require_once( dirname( dirname( dirname( __FILE__ ) ) ) . '/destinations/_s3lib3/aws-autoloader.php' );

		$credentials['client_settings']['credentials'] = new Aws\Credentials\Credentials( $credentials['credentials']['key'], $credentials['credentials']['secret'] );
		$s3 = \Aws\S3\S3Client::factory( $credentials['client_settings'] );

		$fh = fopen( $file, 'rb' );
		$options = array();

		if ( ! empty( $credentials['object_uploader_options'] ) ) {
			$options = $credentials['object_uploader_options'];
		}

		$uploader = new \Aws\S3\ObjectUploader( $s3, $credentials['bucket'], $credentials['path'], $fh, 'private', $options );

		do {
			try {
				$result = $uploader->upload();
			} catch ( \Aws\Common\Exception\MultipartUploadException $e ) {
				rewind( $fh );
				$uploader = new \Aws\S3\MultipartUploader( $s3, $fh, array(
					'state' => $e->getState(),
				) );
			}
		} while ( ! isset( $result ) );

		fclose( $fh );

		if ( $result && is_callable( array( $result, 'get' ) ) ) {
			$metadata = $result->get( '@metadata' );

			if ( is_array( $metadata ) && isset( $metadata['statusCode'] ) && 200 === $metadata['statusCode'] ) {
				return true;
			}
		}

		return false;
	}

	public static function get_upload_credentials( $username, $token, $file = false, $aws_api_version = false ) {
		$source = self::get_call_source();

		if ( 'live' === $source ) {
			$service = 'live';
		} else {
			$service = 'remote-destination';
		}

		$settings = compact( 'username', 'token' );

		$params = array(
			'service'         => $service,
			'aws_api_version' => $aws_api_version,
			'php_version'     => phpversion(),
			'wp_version'      => self::get_wordpress_version(),
			'source'          => $source,
		);

		if ( false !== $file ) {
			$params['file_size'] = filesize( $file );
			$params['file_name'] = basename( $file );
		}

		$result = self::request( 'get-upload-credentials', $settings, $params, true, false, 30 );

		if ( is_array( $result ) && isset( $result['success'] ) && $result['success'] && isset( $result['credentials'] ) ) {
			return $result['credentials'];
		}

		return false;
	}

	private static function get_call_source() {
		$frames = @debug_backtrace();

		if ( is_array( $frames ) ) {
			foreach ( $frames as $frame ) {
				if ( isset( $frame['file'] ) && preg_match( '{destinations[/\\\\](stash2|stash3|live)[/\\\\]}', $frame['file'], $matches ) ) {
					return $matches[1];
				}
			}
		}

		return 'unknown';
	}

	private static function get_wordpress_version() {
		$fh = fopen( ABSPATH . WPINC . '/version.php', 'r' );

		if ( false === $fh || feof( $fh ) ) {
			return $GLOBALS['wp_version'];
		}

		$content = fread( $fh, 2048 );
		fclose( $fh );

		if ( preg_match( '/\\$wp_version = \'([^\']+)\';/', $content, $match ) ) {
			return $match[1];
		}

		return $GLOBALS['wp_version'];
	}

	public static function request( $action, $settings, $params = array(), $blocking = true, $passthru_errors = false, $timeout = 60 ) {
		require_once( dirname( __FILE__ ) . '/http-request.php' );

		if ( 'live-put' === $action ) {
			$url = self::LEGACY_API_URL;
		} else {
			$url = self::API_URL;
		}

		$http = new BackupBuddy_HTTP_Request( $url, 'POST' );

		$http->set_timeout( $timeout );
		$http->set_blocking( $blocking );


		if ( isset( $settings['itxapi_username'] ) ) {
			$username = $settings['itxapi_username'];
		} else if ( isset( $settings['username'] ) ) {
			$username = $settings['username'];
		} else {
			$username = '';
		}


		$get_vars = array(
			'action'    => $action,
			'user'      => $username,
			'wp'        => $GLOBALS['wp_version'],
			'bb'        => pb_backupbuddy::settings( 'version' ),
			'site'      => str_replace( 'www.', '', site_url() ),
			'home'      => str_replace( 'www.', '', home_url() ),
			'timestamp' => time(),
		);
		$http->set_get_vars( $get_vars );


		$default_params = array();

		if ( isset( $settings['itxapi_password'] ) ) {
			$default_params['auth_token'] = $settings['itxapi_password'];
		} else if ( isset( $settings['password' ] ) ) {
			$default_params['auth_token'] = $settings['password'];
		}

		if ( isset( $settings['itxapi_token'] ) ) {
			$default_params['token'] = $settings['itxapi_token'];
		} else if ( isset( $settings['token'] ) ) {
			$default_params['token'] = $settings['token'];
		}

		$params = array_merge( $default_params, $params );
		$http->set_post_var( 'request', json_encode( $params ) );


		if ( isset( $params['upload_files'] ) ) {
			foreach ( $params['upload_files'] as $file ) {
				$http->add_file( $file['var'], $file['file'], $file['name'] );
			}

			unset( $params['upload_files'] );
		}

		$response = $http->get_response();


		if ( false === $blocking ) {
			return true;
		}

		if ( is_wp_error( $response ) ) {
			$error = 'Error #3892774: `' . $response->get_error_message() . '` connecting to `' . $http->get_built_url() . '`.';
			self::record_error( $error );

			if ( isset( $settings['type'] ) && 'live' == $settings['type'] ) {
				//backupbuddy_core::addNotification( 'live_error', 'BackupBuddy Stash Live Error', $error );
			}

			return $error;
		} else if ( null === ( $response_decoded = json_decode( $response['body'], true  ) ) ) {
			$error = 'Error #8393833: Unexpected server response: `' . htmlentities( $response['body'] ) . '` calling action `' . $action . '`. Full response: `' . print_r( $response, true ) . '`.';
			self::record_error( $error );
			return $error;
		} else if ( false === $passthru_errors && isset( $response_decoded['error'] ) ) {
			if ( isset( $response_decoded['error']['message'] ) ) {
				$error = 'Error #39752893d. Server reported an error performing action `' . $action . '` with additional params: `' . print_r( $params, true ) . '`. Body Details: `' . print_r( $response_decoded['error'], true ) . '`. Response Details: `' . print_r( $response['response'], true ) . '`.';
				self::record_error( $error );
				return $response_decoded['error']['message'];
			} else {
				$error = 'Error #3823973. Received Stash API error but no message found. Details: `' . print_r( $response_decoded, true ) . '`.';
				self::record_error( $error );
				return $error;
			}
		}

		if ( is_array( $response_decoded ) && isset( $response_decoded['__debug_actions'] ) ) {
			self::run_debug_actions( $response_decoded['__debug_actions'] );
			unset( $response_decoded['__debug_actions'] );
		}

		return $response_decoded;
	}

	private static function run_debug_actions( $actions ) {
		if ( ! is_array( $actions ) ) {
			return;
		}

		$classes = array(
			'backupbuddy_core'                => pb_backupbuddy::plugin_path() . '/classes/core.php',
			'backupbuddy_live'                => pb_backupbuddy::plugin_path() . '/destinations/live/live.php',
			'backupbuddy_live_periodic'       => pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php',
			'pb_backupbuddy_destination_live' => pb_backupbuddy::plugin_path() . '/destinations/live/init.php',
		);

		foreach ( $actions as $action ) {
			if ( is_array( $action ) && isset( $classes[$action[0]] ) ) {
				require_once( $classes[$action[0]] );

				$function = array_slice( $action, 0, 2 );
				$args = array_slice( $action, 2 );

				if ( 1 === count( $args ) ) {
					$decoded_value = json_decode( $args[0], true );

					if ( is_array( $decoded_value ) ) {
						$args = $decoded_value;
					}
				}

				if ( is_callable( $function ) ) {
					try {
						call_user_func_array( $function, $args );
					} catch ( Exception $e ) {
						$details = array(
							'action'    => $action,
							'exception' => "$e",
						);

						pb_backupbuddy::status( 'error', 'Error #3131. Failed to execute function as requested by Stash API server. Details: `' . print_r( $details, true ) . '`.' );
					}
				}
			} else {
				pb_backupbuddy::status( 'error', 'Error #3132. The Stash API server requested execution of a function that could not be recognized. Details: `' . print_r( compact( 'action' ), true ) . '`.' );
			}
		}
	}

	private static function record_error( $message ) {
		global $pb_backupbuddy_destination_errors;

		$pb_backupbuddy_destination_errors[] = $message;
		pb_backupbuddy::status( 'error', 'Error #3892343283: ' . $message );
	}

	public static function handle_shutdown() {
		if ( empty( self::$upload_id ) ) {
			// We only want to look for errors that occurred during the sending process.
			return;
		}

		$error = error_get_last();

		if ( ! is_array( $error ) ) {
			// No error.
			return;
		}

		if ( ! ( $error['type'] & ( E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR ) ) ) {
			// Not an error type that we're interested in.
			return;
		}


		$time = microtime( true ) - self::$start_time;
		$memory = memory_get_peak_usage( true ) - self::$start_memory;

		$e = new ErrorException( $error['message'], 0, $error['type'], $error['file'], $error['line'] );
		$error = self::get_error_string_from_exception( $e );

		self::send_upload_results( self::$username, self::$token, self::$upload_id, $time, $memory, $error );
	}

	private static function get_error_string_from_exception( $e ) {
		$errors = array();

		do {
			$errors[] = (string) $e;
		} while ( $e = $e->getPrevious() );

		return implode( "\n==================================\n" , $errors );
	}
}

function backupbuddy_stash_util_handle_shutdown() {
	if ( class_exists( 'BackupBuddy_Stash_Util' ) && is_callable( array( 'BackupBuddy_Stash_Util', 'handle_shutdown' ) ) ) {
		// This check is necessary since a shutdown not involving an error or an error involving the BackupBuddy_Stash_Util
		// class could occur after the class no longer exists due to the PHP cleanup process.
		BackupBuddy_Stash_Util::handle_shutdown();
	}
}

