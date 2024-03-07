<?php
if ( '' == $backupMode ) {
	$backupMode = pb_backupbuddy::$options['profiles'][0]['backup_mode']; // Use user-defined setting.
}
if ( ! isset( $triggerTitle ) || ( '' == $triggerTitle ) ) {
	$triggerTitle = 'manual';
}

if ( is_array( $generic_type_or_profile_id_or_array ) ) { // is a profile array
	$profileArray = $generic_type_or_profile_id_or_array;
} else {
	$profile = $generic_type_or_profile_id_or_array;
	if ( 'db' == $profile ) { // db profile is always index 1.
		$profile = '1';
	} elseif ( 'full' == $profile ) { // full profile is always index 2.
		$profile = '2';
	}

	if ( is_numeric( $profile ) ) {
		if ( isset( pb_backupbuddy::$options['profiles'][ $profile ] ) ) {
			$profileArray = pb_backupbuddy::$options['profiles'][ $profile ];
		} else {
			return 'Error #2332904: Invalid profile ID `' . htmlentities( $profile ) . '`. Profile with this number was not found. Try deactivating then reactivating the plugin. If this fails please reset the plugin Settings back to Defaults from the Settings page.';
		}
	} else {
		return 'Error #85489548955. You cannot refresh this page to re-run it to prevent accidents. You will need to go back and try again. (Invalid profile ID not numeric: `' . htmlentities( $profile ) . '`).';
	}
	
}

// Validate destination ids
$post_backup = array();
if ( ! empty( $destinations ) ) {
	foreach( (array) $destinations as $key => $destination_id ) {
		if ( ! isset( pb_backupbuddy::$options['remote_destinations'][$destination_id] ) ) {
			unset( $destinations[$key] );
		} else {
			$post_backup[] = array(
				'function'    => 'send_remote_destination',
				'args'        => array( $destination_id, $delete_after ),
				'start_time'  => 0,
				'finish_time' => 0,
				'attempts'    => 0,
			);
		}
	}
}


$profileArray = array_merge( pb_backupbuddy::$options['profiles'][0], $profileArray ); // Merge defaults.
$profileArray['backup_mode'] = $backupMode; // Force modern mode when running under API. 1=classic (single page load), 2=modern (cron)

if ( '' == $backupSerial ) {
	$backupSerial = pb_backupbuddy::random_string( 10 );
}


require_once( pb_backupbuddy::plugin_path() . '/classes/backup.php' );
$newBackup = new pb_backupbuddy_backup();

// Run the backup!
if ( $newBackup->start_backup_process(
	$profileArray,											// Profile array.
	$triggerTitle,											// Backup trigger. manual, scheduled
	array(),												// pre-backup array of steps.
	$post_backup,											// post-backup array of steps.
	$triggerTitle,											// friendly title of schedule that ran this (if applicable).
	$backupSerial,											// if passed then this serial is used for the backup insteasd of generating one.
	array()													// Multisite export only: array of plugins to export.
  ) !== true ) {
	return 'Error #435832: Backup failed. See Solid Backups log for details.';
}

return array( 'success' => true, 'serial' => $backupSerial );
