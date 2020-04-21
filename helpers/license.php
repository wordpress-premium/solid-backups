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
