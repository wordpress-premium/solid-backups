<?php
/**
 * Live Setup AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();
pb_backupbuddy::verify_nonce();

$errors = array();
$form   = pb_backupbuddy::_POST();

$archive_types = array(
	'db'      => __( 'Database Backup', 'it-l10n-backupbuddy' ),
	'full'    => __( 'Full Backup', 'it-l10n-backupbuddy' ),
	'plugins' => __( 'Plugins Backup', 'it-l10n-backupbuddy' ),
	'themes'  => __( 'Themes Backup', 'it-l10n-backupbuddy' ),
);

$archive_periods = array(
	'daily',
	'weekly',
	'monthly',
	'yearly',
);

if ( '' == pb_backupbuddy::_POST( 'live_username' ) || '' == pb_backupbuddy::_POST( 'live_password' ) ) { // A field is blank.
	$errors[] = 'You must enter your iThemes username & password to log in to BackupBuddy Stash Live.';
} else { // Username and password provided.

	require_once pb_backupbuddy::plugin_path() . '/lib/stash/stash-api.php';
	require_once pb_backupbuddy::plugin_path() . '/destinations/stash2/class.itx_helper2.php';
	require_once pb_backupbuddy::plugin_path() . '/destinations/stash2/init.php';
	require_once pb_backupbuddy::plugin_path() . '/destinations/live/init.php';

	global $wp_version;

	$itxapi_username = strtolower( pb_backupbuddy::_POST( 'live_username' ) );
	$password_hash   = iThemes_Credentials::get_password_hash( $itxapi_username, pb_backupbuddy::_POST( 'live_password' ) );
	$access_token    = ITXAPI_Helper2::get_access_token( $itxapi_username, $password_hash, site_url(), $wp_version );

	$response = BackupBuddy_Stash_API::connect( $itxapi_username, $access_token );

	if ( ! is_array( $response ) ) { // Error message.
		$errors[] = print_r( $response, true );
	} else {
		if ( isset( $response['error'] ) ) {
			$errors[] = $response['error']['message'];
		} else {
			if ( isset( $response['token'] ) ) {
				$itxapi_token = $response['token'];
			} else {
				$errors[] = 'Error #2308832: Unexpected server response. Token missing. Check your BackupBuddy Stash Live login and try again. Detailed response: `' . print_r( $response, true ) . '`.';
			}
		}
	}

	// If we have the token then create the Live destination.
	if ( isset( $itxapi_token ) ) {
		$next_dest_key = 0;
		if ( count( pb_backupbuddy::$options['remote_destinations'] ) > 0 ) {
			$next_dest_key = max( array_keys( pb_backupbuddy::$options['remote_destinations'] ) ) + 1;
		}

		pb_backupbuddy::$options['remote_destinations'][ $next_dest_key ]                    = pb_backupbuddy_destination_live::$default_settings;
		pb_backupbuddy::$options['remote_destinations'][ $next_dest_key ]['itxapi_username'] = pb_backupbuddy::_POST( 'live_username' );
		pb_backupbuddy::$options['remote_destinations'][ $next_dest_key ]['itxapi_token']    = $itxapi_token;
		pb_backupbuddy::$options['remote_destinations'][ $next_dest_key ]['title']           = 'My BackupBuddy Stash Live';

		// Notification email.
		pb_backupbuddy::$options['remote_destinations'][ $next_dest_key ]['email'] = pb_backupbuddy::_POST( 'email' );

		// Archive limits.
		foreach ( $archive_types as $archive_type => $archive_type_name ) {
			foreach ( $archive_periods as $archive_period ) {
				$settings_name = 'limit_' . $archive_type . '_' . $archive_period;
				pb_backupbuddy::$options['remote_destinations'][ $next_dest_key ][ $settings_name ] = pb_backupbuddy::_POST( $settings_name );
			}
		}

		if ( '1' == pb_backupbuddy::_POST( 'send_snapshot_notification' ) ) {
			pb_backupbuddy::$options['remote_destinations'][ $next_dest_key ]['send_snapshot_notification'] = pb_backupbuddy::_POST( 'send_snapshot_notification' );
		} else {
			pb_backupbuddy::$options['remote_destinations'][ $next_dest_key ]['send_snapshot_notification'] = '0';
		}

		pb_backupbuddy::save();
		$destination_id = $next_dest_key;

		// Send new settings for archive limiting to Stash API.
		backupbuddy_live::send_trim_settings();

		// Set first run of BackupBuddy Stash Live so it begins immediately.
		$cron_args       = array();
		$schedule_result = backupbuddy_core::schedule_single_event( time(), 'live_periodic', $cron_args );
		if ( true === $schedule_result ) {
			pb_backupbuddy::status( 'details', 'Next Live Periodic chunk step cron event scheduled.' );
		} else {
			pb_backupbuddy::status( 'error', 'Next Live Periodic chunk step cron event FAILED to be scheduled.' );
		}
		if ( '1' != pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
			pb_backupbuddy::status( 'details', 'Spawning cron now.' );
			update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
			spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
		}
	}
} // end if user and pass set.

if ( count( $errors ) === 0 ) {
	pb_backupbuddy::save();
	die( 'Success.' );
}

die( '* ' . implode( "\n* ", $errors ) );
