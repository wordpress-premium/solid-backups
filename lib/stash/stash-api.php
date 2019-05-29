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

	public static function delete_files( $username, $token, $files ) {
		$settings = compact( 'username', 'token' );
		$params = compact( 'files' );

		$result = self::request( 'delete-files', $settings, $params );

		if ( is_array( $result ) && isset( $result['success'] ) && $result['success'] ) {
			return true;
		}

		return false;
	}

	public static function list_files( $username, $token ) {
		$settings = compact( 'username', 'token' );

		return self::request( 'files', $settings, array(), true, false, 600 );
	}

	public static function list_site_files( $username, $token ) {
		$settings = compact( 'username', 'token' );

		return self::request( 'site-files', $settings, array(), true, false, 600 );
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
