<?php
/* BackupBuddy Stash Live Remote Files Viewer
 *
 * @author Dustin Bolton
 * @since 7.0
 *
 * NOTE: Incoming variables: $destination, $destination_id
 */
$settings = $destination;
if ( ! isset( $settings['destination_version'] ) ) {
	$settings['destination_version'] = '2';
}

echo '<center><h1>' . __( 'Stash Files', 'it-l10n-backupbuddy' ) . '</h1></center><br>';

$hide_quota = false;
$live_mode = true;
require_once( pb_backupbuddy::plugin_path() . '/destinations/stash' . $settings['destination_version'] . '/init.php' );
require_once( pb_backupbuddy::plugin_path() . '/destinations/stash' . $settings['destination_version'] . '/_manage.php' );