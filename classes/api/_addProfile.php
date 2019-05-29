<?php
// Confirm 'type' is supported by the API
if ( ! in_array( $type, array( 'db', 'full', 'themes', 'plugins', 'media' ) ) ) {
	backupbuddy_api::$lastError = 'Error #2018121701: Profile type not supported.';
	return false;
}

// Confirm we have a title
if ( empty( $title ) ) {
	backupbuddy_api::$lastError = 'Error #2018121702: Title is a required parameter.';
	return false;
}

// Add Profile
$newProfile = array( 
	'type'  => $type,
	'title' => htmlentities( $title ),
);

$profile = array_merge( pb_backupbuddy::settings( 'profile_defaults' ), $newProfile );
pb_backupbuddy::$options['profiles'][] = $profile;
pb_backupbuddy::save();
return true; 
