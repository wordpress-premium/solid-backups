<?php
/**
 * License/Package Helpers
 *
 * @package BackupBuddy
 */

/**
 * Retrieve the plugin license info.
 *
 * @return false|array  Array of license info or false on error.
 */
function backupbuddy_get_package_license() {
	if ( empty( $GLOBALS['ithemes_updater_path'] ) ) {
		pb_backupbuddy::status( 'error', __( 'Unable to locate valid plugin license information.', 'it-l10n-backupbuddy' ) );
		return false;
	}

	require_once $GLOBALS['ithemes_updater_path'] . '/keys.php';
	require_once $GLOBALS['ithemes_updater_path'] . '/packages.php';

	$details     = Ithemes_Updater_Packages::get_full_details();
	$packages    = $details['packages'];
	$plugin_file = basename( dirname( BACKUPBUDDY_PLUGIN_FILE ) ) . '/' . basename( BACKUPBUDDY_PLUGIN_FILE );

	$contact_support = __( 'Please contact support for assistance.', 'it-l10n-backupbuddy' );

	if ( empty( $packages[ $plugin_file ] ) ) {
		pb_backupbuddy::status( 'error', __( 'Unable to locate valid plugin license information for ', 'it-l10n-backupbuddy' ) . $plugin_file . '. ' . $contact_support );
		return false;
	}
	if ( empty( $packages[ $plugin_file ]['key'] ) || empty( $packages[ $plugin_file ]['user'] ) ) {
		pb_backupbuddy::status( 'error', __( 'Unable to locate license information for ', 'it-l10n-backupbuddy' ) . $plugin_file . __( ' on this site', 'it-l10n-backupbuddy' ) . '. ' . $contact_support );
		return false;
	}

	return $packages[ $plugin_file ];
}

/**
 * Create Source (state) URL for OAuth requests.
 *
 * @param string $destination  Destination slug, used to identify which auth.
 *
 * @return string  URL with Payload, Signature and Site URL.
 */
function backupbuddy_get_oauth_source_url( $destination ) {
	$package = backupbuddy_get_package_license();

	// Create the payload for validation.
	$key     = $package['key'];
	$user    = $package['user'];
	$payload = array(
		'time'   => current_time( 'timestamp' ),
		'action' => 'backupbuddy-' . $destination . '-oauth-connect',
	);
	$payload = wp_json_encode( $payload );

	// Build the value for the "state" parameter passed to the OAuth API.
	$source = admin_url( 'admin.php?page=pb_backupbuddy_destinations&' . $destination . '-oauth=1' );
	$source = add_query_arg( 'username', $user, $source );
	$source = add_query_arg( 'site', network_home_url(), $source );
	$source = add_query_arg( 'payload', $payload, $source );
	$source = add_query_arg( 'signature', hash_hmac( 'sha1', $payload, $key ), $source );

	return $source;
}
