<?php
/**
 * Manage som ecpanel settings.
 *
 * @package BackupBuddy/cPanel
 */

/*
EXAMPLE:

require_once( pb_backupbuddy::plugin_path() . '/lib/cpanel/cpanel.php' );

$cpanel_user = pb_backupbuddy::_GET( 'user' );
$cpanel_password = pb_backupbuddy::_GET( 'pass' );
$cpanel_host = "foo.com";
$db_name = 'apples';
$db_user = 'oranges';
$db_pass = 'bananas';
$create_db_result = pb_backupbuddy_cpanel::create_db( $cpanel_user, $cpanel_password, $cpanel_host, $db_name, $db_user, $db_pass );

if ( $create_db_result === true ) {
	echo 'Success! Created database, user, and assigned used to database.';
} else {
	echo 'Error(s):<br><pre>' . print_r( $create_db_result, true ) . '</pre>';
}

*/

/**
 * Cpanel Class
 *
 * Manage some cpanel settings.
 */
class pb_backupbuddy_cpanel {

	/**
	 * Create a database and assign a user to it with all privilages.
	 *
	 * TODO: Use more robust than file_get_contents().
	 *
	 * @param string $cpanel_user      cPanel username.
	 * @param string $cpanel_password  cPanel password.
	 * @param string $cpanel_host      cPanel hostname.
	 * @param string $db_name          Database name.
	 * @param string $db_user          Database username.
	 * @param string $db_userpass      Database password.
	 * @param int    $cpanel_port      cPanel port number.
	 * @param string $cpanel_protocol  cPanel protocol.
	 *
	 * @return true|array  Boolean true on success, else an array of errors.
	 */
	public static function create_db( $cpanel_user, $cpanel_password, $cpanel_host, $db_name, $db_user, $db_userpass, $cpanel_port = '2082', $cpanel_protocol = 'http://' ) {
		$cpanel_skin = 'x3';
		$errors      = array();

		$cpanel_password = urlencode( $cpanel_password ); // Pass often has special chars so encode.

		// Calculate base URL.
		$base_url = "{$cpanel_protocol}{$cpanel_user}:{$cpanel_password}@{$cpanel_host}:{$cpanel_port}/execute/Mysql";

		// Generate create database URL.
		$create_database_url = $base_url . "/create_database?name={$cpanel_user}_{$db_name}";

		// Create request core obj for connecting to HTTP.
		$request = new RequestCore( $create_database_url );
		try {
			$result = $request->send_request( true );
		} catch ( Exception $e ) {
			if ( stristr( $e->getMessage(), 'couldn\'t connect to host' ) !== false ) {
				$errors[] = esc_html__( 'Unable to connect to host', 'it-l10n-backupbuddy' ) . ' `' . $cpanel_host . '` ' . esc_html__( 'on port', 'it-l10n-backupbuddy' ) . ' `' . $cpanel_port . '`. ' . esc_html__( 'Verify the cPanel domain/URL and make sure the server is able to initiate outgoing http connections on port ', 'it-l10n-backupbuddy' ) . $cpanel_port . '. ' . esc_html__( 'Some hosts block this.', 'it-l10n-backupbuddy' );
				return $errors;
			}
			$errors[] = esc_html__( 'Caught exception: ', 'it-l10n-backupbuddy' ) . $e->getMessage() . '. ' . esc_html__( 'Full URL: ', 'it-l10n-backupbuddy' ) . $create_database_url;
			return $errors;
		}

		// Generate create database user URL.
		$create_user_url = $base_url . "/create_user?name={$cpanel_user}_{$db_user}&password={$db_userpass}";

		// Generate assign user database access URL.
		$assign_user_url = $base_url . "/set_privileges_on_database?user={$cpanel_user}_{$db_user}&database={$cpanel_user}_{$db_name}&privileges=ALL";

		if ( false === $result->isOK() ) {
			$errors[] = esc_html__( 'Unable to create database - response status code: ', 'it-l10n-backupbuddy' ) . $result->status;
		} else {
			$result_array = json_decode( $result->body, true );
			if ( isset( $result_array['status'] ) && 0 == $result_array['status'] ) {
				// status = 0 means a failure.
				$errors[] = esc_html__( 'Unable to create database:', 'it-l10n-backupbuddy' );
				if ( isset( $result_array['errors'] ) && ( is_array( $result_array['errors'] ) ) ) {
					foreach ( $result_array['errors'] as $error ) {
						$errors[] = $error;
					}
				}
			}
		}

		// Run create database user.
		if ( count( $errors ) === 0 ) {
			$request = new RequestCore( $create_user_url );
			try {
				$result = $request->send_request( true );
			} catch ( Exception $e ) {
				$errors[] = esc_html__( 'Caught exception: ', 'it-l10n-backupbuddy' ) . $e->getMessage();
				return $errors;
			}

			if ( false === $result->isOK() ) {
				$errors[] = esc_html__( 'Unable to creaet user - response status code: ', 'it-l10n-backupbuddy' ) . $result->status;
			} else {
				$result_array = json_decode( $result->body, true );
				if ( isset( $result_array['status'] ) && 0 == $result_array['status'] ) {
					// status = 0 means a failure.
					$errors[] = esc_html__( 'Unable to create user:', 'it-l10n-backupbuddy' );
					if ( isset( $result_array['errors'] ) && ( is_array( $result_array['errors'] ) ) ) {
						foreach ( $result_array['errors'] as $error ) {
							$errors[] = $error;
						}
					}
				}
			}
		}

		// Run assign user to database.
		if ( count( $errors ) === 0 ) {
			$request = new RequestCore( $assign_user_url );
			try {
				$result = $request->send_request( true );
			} catch ( Exception $e ) {
				$errors[] = esc_html__( 'Caught exception: ', 'it-l10n-backupbuddy' ) . $e->getMessage();
				return $errors;
			}

			if ( false === $result->isOK() ) {
				$errors[] = esc_html__( 'Unable to set privileges for user - response status code: ', 'it-l10n-backupbuddy' ) . $result->status;
			} else {
				$result_array = json_decode( $result->body, true );
				if ( isset( $result_array['status'] ) && ( 0 == $result_array['status'] ) ) {
					// status = 0 means a failure.
					$errors[] = esc_html__( 'Unable to set privileges for user:', 'it-l10n-backupbuddy' );
					if ( isset( $result_array['errors'] ) && ( is_array( $result_array['errors'] ) ) ) {
						foreach ( $result_array['errors'] as $error ) {
							$errors[] = $error;
						}
					}
				}
			}
		}

		if ( count( $errors ) > 0 ) { // One or more errors.
			return $errors;
		} else {
			return true; // Success!
		}

	}

} // end class.
