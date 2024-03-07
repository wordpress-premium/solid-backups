<?php
/* Solid Backups Stash Live Remote Files Viewer
 *
 * @author Dustin Bolton
 * @since 7.0
 *
 * NOTE: Incoming variables: $destination, $destination_id
 */
$settings = $destination;
if ( ! isset( $settings['destination_version'] ) ) {

	// @todo jr3 keep this during dev in case it is passed into the files below.
	$settings['destination_version'] = '3';
}

echo '<center><h1>' . __( 'Stash Files', 'it-l10n-backupbuddy' ) . '</h1></center><br>';

$hide_quota = false;
$live_mode = true;
require_once( pb_backupbuddy::plugin_path() . '/destinations/stash3/init.php' );
require_once( pb_backupbuddy::plugin_path() . '/destinations/stash3/_manage.php' );
