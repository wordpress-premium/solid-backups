<?php

final class BackupBuddy_Stash_API {
	public static function send_file( $username, $token, $file ) {
		if ( defined( 'BACKUPBUDDY_STASH_USE_LEGACY_UPLOAD' ) && BACKUPBUDDY_STASH_USE_LEGACY_UPLOAD ) {
			return false;
		}

		require_once( dirname( __FILE__ ) . '/stash-util.php' );

		return BackupBuddy_Stash_Util::send_file( $username, $token, $file );
	}

	public static function get_upload_credentials( $username, $token, $file = false, $aws_api_version = false ) {
		require_once( dirname( __FILE__ ) . '/stash-util.php' );

		return BackupBuddy_Stash_Util::get_upload_credentials( $username, $token, $file, $aws_api_version );
	}

	public static function send_fallback_upload_results( $settings, $error = '' ) {
		if ( ! isset( $settings['stash_mode'] ) || '1' != $settings['stash_mode'] || ! isset( $settings['_start_time'] ) || ! isset( $settings['_start_memory'] ) ) {
			return false;
		}

		if ( empty( $error ) ) {
			pb_backupbuddy::status( 'details', 'Notifying Stash of upload completion.' );
		} else {
			pb_backupbuddy::status( 'details', 'Notifying Stash of upload failure.' );
		}

		$request_settings = array(
			'username' => $settings['_username'],
			'token'    => $settings['token'],
		);

		$params = array(
			'id'     => $settings['_stash_upload_id'],
			'time'   => microtime( true ) - $settings['_start_time'],
			'memory' => memory_get_peak_usage( true ) - $settings['_start_memory'],
			'error'  => $error,
		);

		$response = self::request( 'send-upload-results', $request_settings, $params );

		if ( ( ! is_array( $response ) || ! isset( $response['success'] ) || true !== $response['success'] ) ) {
			if ( empty( $error ) ) {
				pb_backupbuddy::status( 'error', 'Error #83298932: Error notifying Stash of upload success. Details: `' . print_r( $response, true ) . '`.' );
			} else {
				pb_backupbuddy::status( 'error', 'Warning #32736326: Error notifying Stash of upload failure. Details: `' . print_r( $response, true ) . '`.' );

			}
			return false;
		} else {
			if ( empty( $error ) ) {
				pb_backupbuddy::status( 'details', 'Stash notified of upload completition.' );
			} else {
				pb_backupbuddy::status( 'details', 'Stash notified of upload fail.' );
			}
			return true;
		}
	}

	public static function get_fallback_upload_action_response( $username, $token, $file ) {
		$credentials = self::get_upload_credentials( $username, $token, $file );

		if ( false === $credentials ) {
			return false;
		}

		$credentials['client_settings']['bucket'] = $credentials['bucket'];

		$response = array(
			'client_settings'   => $credentials['client_settings'],
			'settings_override' => array(
				'bucket' => $credentials['bucket'],
			),
			'credentials'       => $credentials['credentials'],
			'bucket'            => $credentials['bucket'],
			'_stash_object'     => $credentials['path'],
			'_stash_upload_id'  => $credentials['id'],
			'_username'         => $username,
			'_token'            => $token,
			'_start_time'       => microtime( true ),
			'_start_memory'     => memory_get_peak_usage( true ),
		);

		return $response;
	}

	public static function delete_files( $username, $token, $files ) {
		$settings = compact( 'username', 'token' );
		$params = compact( 'files' );

		$result = self::request( 'delete-files', $settings, $params );

		if ( is_array( $result ) && isset( $result['success'] ) && $result['success'] ) {
			return true;
		}

		return false;
	}

	public static function list_files( $username, $token, $extensions = array( '.zip' ) ) {
		if ( is_array( $extensions ) ) {
			$extensions = implode( ',', $extensions );
		}

		$settings = compact( 'username', 'token' );
		$params = compact( 'extensions' );

		return self::request( 'files', $settings, $params, true, false, 600 );
	}

	public static function list_site_files( $username, $token, $extensions = array( '.zip' ) ) {
		if ( is_array( $extensions ) ) {
			$extensions = implode( ',', $extensions );
		}

		$settings = compact( 'username', 'token' );
		$params = compact( 'extensions' );

		return self::request( 'site-files', $settings, $params, true, false, 600 );
	}

	public static function connect( $username, $password ) {
		$settings = compact( 'username', 'password' );

		return self::request( 'connect', $settings );
	}

	public static function disconnect( $username, $token, $password ) {
		$settings = compact( 'username', 'token', 'password' );

		return self::request( 'disconnect', $settings );
	}

	public static function request( $action, $settings, $params = array(), $blocking = true, $passthru_errors = false, $timeout = 60 ) {
		require_once( dirname( __FILE__ ) . '/stash-util.php' );

		return BackupBuddy_Stash_Util::request( $action, $settings, $params, $blocking, $passthru_errors, $timeout );
	}
}
