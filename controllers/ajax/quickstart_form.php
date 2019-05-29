<?php
/**
 * Quick Start form saving.
 * Saving Quickstart form.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();
pb_backupbuddy::verify_nonce();


$errors = array();
$form   = pb_backupbuddy::_POST();

// Try using filter_var() with FILTER_VALIDATE_EMAIL here?
if ( '' != $form['email'] && false !== stristr( $form['email'], '@' ) ) {
	pb_backupbuddy::$options['email_notify_error'] = strip_tags( $form['email'] );
} else {
	$errors[] = 'Invalid email address.';
}

if ( '' != $form['password'] && $form['password'] === $form['password_confirm'] ) {
	pb_backupbuddy::$options['importbuddy_pass_hash']   = md5( $form['password'] );
	pb_backupbuddy::$options['importbuddy_pass_length'] = strlen( $form['password'] );
} elseif ( '' == $form['password'] ) {
	$errors[] = 'Please enter a password for restoring / migrating.';
} else {
	$errors[] = 'Passwords do not match.';
}


/***** BEGIN STASH v2 SETUP */

// Note: If existing Stash2 exists with this username then use that instead of making a new stash2 destination.
if ( 'stash2' == pb_backupbuddy::_POST( 'destination' ) ) {
	if ( ( '' == pb_backupbuddy::_POST( 'stash2_username' ) ) || ( '' == pb_backupbuddy::_POST( 'stash2_password' ) ) ) { // A field is blank.
		$errors[] = 'You must enter your iThemes username & password to log in to the remote destination BackupBuddy Stash (v2).';
	} else { // Username and password provided.

		require_once pb_backupbuddy::plugin_path() . '/destinations/stash2/class.itx_helper2.php';
		require_once pb_backupbuddy::plugin_path() . '/destinations/stash2/init.php';
		global $wp_version;

		$itxapi_username = strtolower( pb_backupbuddy::_POST( 'stash2_username' ) );

		// See if this user already exists.
		foreach ( pb_backupbuddy::$options['remote_destinations'] as $destination_index => $destination ) { // Loop through ending with the last created destination of this type.
			if ( 'stash2' == $destination['type'] ) {
				if ( $itxapi_username == $destination['itxapi_username'] ) { // Existing destination match.
					$destination_id = $destination_index;
				}
			}
		}

		if ( ! isset( $destination_id ) ) { // Did not already find the same Stash destination.
			$password_hash = iThemes_Credentials::get_password_hash( $itxapi_username, pb_backupbuddy::_POST( 'stash2_password' ) );
			$access_token  = ITXAPI_Helper2::get_access_token( $itxapi_username, $password_hash, site_url(), $wp_version );

			$settings = array(
				'itxapi_username' => $itxapi_username,
				'itxapi_password' => $access_token,
			);
			$response = pb_backupbuddy_destination_stash2::stashAPI( $settings, 'connect' );

			if ( ! is_array( $response ) ) { // Error message.
				$errors[] = 'Error #32898973: Unexpected server response. Check your Stash login and try again. Detailed response: `' . print_r( $response, true ) . '`.';
			} else {
				if ( isset( $response['error'] ) ) {
					$errors[] = $response['error']['message'];
				} else {
					if ( isset( $response['token'] ) ) {
						$itxapi_token = $response['token'];
					} else {
						$errors[] = 'Error #32977932: Unexpected server response. Token missing. Check your Stash login and try again. Detailed response: `' . print_r( $response, true ) . '`.';
					}
				}
			}

			// If we have the token then create the Stash2 destination.
			if ( isset( $itxapi_token ) ) {
				$next_dest_key = 0; // no destinations yet. first index.
				if ( count( pb_backupbuddy::$options['remote_destinations'] ) > 0 ) {
					$next_dest_key = max( array_keys( pb_backupbuddy::$options['remote_destinations'] ) ) + 1;
				}
				pb_backupbuddy::$options['remote_destinations'][ $next_dest_key ]                    = pb_backupbuddy_destination_stash2::$default_settings;
				pb_backupbuddy::$options['remote_destinations'][ $next_dest_key ]['itxapi_username'] = pb_backupbuddy::_POST( 'stash2_username' );
				pb_backupbuddy::$options['remote_destinations'][ $next_dest_key ]['itxapi_token']    = $itxapi_token;
				pb_backupbuddy::$options['remote_destinations'][ $next_dest_key ]['title']           = 'My Stash (v2)';
				pb_backupbuddy::save();
				$destination_id = $next_dest_key;
			}
		} // end $destination_id not set.
	} // end if user and pass set.
} // end stash setup.


/***** END STASH v2 SETUP */


if ( '' != $form['schedule'] ) {
	if ( ! isset( $destination_id ) ) {
		$destination_id = '';
		if ( '' != $form['destination_id'] ) { // Dest id explicitly set.
			$destination_id = $form['destination_id'];
		} else { // No explicit destination ID; deduce it.
			if ( '' != $form['destination'] ) {
				foreach ( pb_backupbuddy::$options['remote_destinations'] as $destination_index => $destination ) { // Loop through ending with the last created destination of this type.
					if ( $destination['type'] == $form['destination'] ) {
						$destination_id = $destination_index;
					}
				} // end foreach.
			}
		}
	}

	/**
	 * Checks to see if schedule exists based on title.
	 *
	 * @param string $title  Schedule title.
	 *
	 * @return bool  If exists.
	 */
	function pb_backupbuddy_schedule_exist_by_title( $title ) {
		foreach ( pb_backupbuddy::$options['schedules'] as $schedule ) {
			if ( $schedule['title'] == $title ) {
				return true;
			}
		}
		return false;
	}

	// STARTER.
	if ( 'starter' == $form['schedule'] ) {

		$title = 'Weekly Database (Quick Setup - Starter)';
		if ( false === pb_backupbuddy_schedule_exist_by_title( $title ) ) {
			$add_response            = backupbuddy_api::addSchedule(
				$title,
				$profile             = '1',
				$interval            = 'weekly',
				$first_run           = ( time() + ( get_option( 'gmt_offset' ) * 3600 ) + 86400 ),
				$remote_destinations = array( $destination_id )
			);
			if ( true !== $add_response ) {
				$errors[] = $add_response; }
		}

		$title = 'Monthly Full (Quick Setup - Starter)';
		if ( false === pb_backupbuddy_schedule_exist_by_title( $title ) ) {
			$add_response            = backupbuddy_api::addSchedule(
				$title,
				$profile             = '2',
				$interval            = 'monthly',
				$first_run           = ( time() + ( get_option( 'gmt_offset' ) * 3600 ) + 86400 + 18000 ),
				$remote_destinations = array( $destination_id )
			);
			if ( true !== $add_response ) {
				$errors[] = $add_response; }
		}
	}

	// BLOGGER.
	if ( 'blogger' == $form['schedule'] ) {

		$title = 'Daily Database (Quick Setup - Blogger)';
		if ( false === pb_backupbuddy_schedule_exist_by_title( $title ) ) {
			$add_response            = backupbuddy_api::addSchedule(
				$title,
				$profile             = '1',
				$interval            = 'daily',
				$first_run           = ( time() + ( get_option( 'gmt_offset' ) * 3600 ) + 86400 ),
				$remote_destinations = array( $destination_id )
			);
			if ( true !== $add_response ) {
				$errors[] = $add_response; }
		}

		$title = 'Weekly Full (Quick Setup - Blogger)';
		if ( false === pb_backupbuddy_schedule_exist_by_title( $title ) ) {
			$add_response            = backupbuddy_api::addSchedule(
				$title,
				$profile             = '2',
				$interval            = 'weekly',
				$first_run           = ( time() + ( get_option( 'gmt_offset' ) * 3600 ) + 86400 + 18000 ),
				$remote_destinations = array( $destination_id )
			);
			if ( true !== $add_response ) {
				$errors[] = $add_response; }
		}
	}
} // end set schedule.


if ( 0 == count( $errors ) ) {
	pb_backupbuddy::save();
	die( 'Success.' );
}

die( '* ' . implode( "\n* ", $errors ) );
