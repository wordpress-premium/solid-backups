<?php
/* Incoming vars from _manage.php:
 *
 *	$activePluginsA, $activePluginsB, $activeThemeBInfo
 *
 */


_e( "This will <b>pull</b> data from the other site to make this site match the source site's database, media, etc. Contents of this site will be overwritten as needed. Verify the details below to make sure this is the correct deployment you wish to commence. You will be given the opportunity to test the changes and undo the database changes before making them permanent.", 'it-l10n-backupbuddy' );

echo ' <span style="position: relative; top: -0.5em; font-size: 0.7em;">&dagger;</span> ';
_e( 'WordPress core files will not be transferred between sites.', 'it-l10n-backupbuddy' );

echo ' <span style="position: relative; top: -0.5em; font-size: 0.7em;">&Dagger;</span> ';
_e( 'Solid Backups plugin files will not be transferred between sites.', 'it-l10n-backupbuddy' );


if ( $deployData['remoteInfo']['activeTheme'] == $localInfo['activeTheme'] ) {
	$activeThemeBInfo = ' <a href="javascript:void(0);" class="deploy-show-files" rel="pullThemeFiles" title="Click to list files.">(' . count( $deployData['pullThemeFiles'] ) . ' files to pull)</a>';
} else {
	$activeThemeBInfo = ' (' . __( 'Active theme differs so not updating.', 'it-l10n-backupbuddy' ) . ')';
}

if ( isset( $deployData['remoteInfo']['activeChildTheme'] ) ) {
	if ( $deployData['remoteInfo']['activeChildTheme'] == $localInfo['activeTheme'] ) { // theme & child theme are same (aka not using a child theme)
		$activeChildThemeBSame = '; same as theme so not re-sending';
	} else {
		$activeChildThemeBSame = '';
	}
	
	if ( $deployData['remoteInfo']['activeChildTheme'] == $localInfo['activeChildTheme'] ) {
		$activeChildThemeBInfo = ' <a href="javascript:void(0);" class="deploy-show-files" rel="pullChildThemeFiles" title="Click to list files.">(' . count( $deployData['pullChildThemeFiles'] ) . ' files to pull' . $activeChildThemeBSame . ')</a>';
	} else {
		$activeChildThemeBInfo = ' (' . __( 'Active child theme differs so not updating.', 'it-l10n-backupbuddy' ) . ')';
	}
	$remoteActiveChildTheme = $deployData['remoteInfo']['activeChildTheme'];
} else {
	$activeChildThemeBInfo = '';
	$remoteActiveChildTheme = __( 'Unknown [NOTE: Remote site does not support detecting child theme. Update remote Solid Backups]', 'it-l10n-backupbuddy' );
}




$activePluginsAInfo = ' <a href="javascript:void(0);" class="deploy-show-files" rel="pullPluginFiles" title="Click to list files.">(' . count( $deployData['pullPluginFiles'] ) . ' files to pull)</a>';


$headFoot = array( __( '<b>Pulling</b> from (source)', 'it-l10n-backupbuddy' ), __( 'To this site (destination)', 'it-l10n-backupbuddy' ) );
$pushRows = array(
	'Site URL' => array( $deployData['remoteInfo']['siteurl'], $localInfo['siteurl'] ),
	'Home URL' => array( $deployData['remoteInfo']['homeurl'], $localInfo['homeurl'] ),
	'Max Execution Time' => array( $deployData['remoteInfo']['php']['max_execution_time'] . ' sec', $localInfo['php']['max_execution_time'] . ' sec' ),
	'Max Upload File Size' => array( $deployData['remoteInfo']['php']['upload_max_filesize'] . ' MB', $localInfo['php']['upload_max_filesize'] . ' MB' ),
	'Memory Limit' => array( $deployData['remoteInfo']['php']['memory_limit'] . ' MB', $localInfo['php']['memory_limit'] . ' MB' ),
	//'PHP Upload Limit' => array( $localInfo['php']['upload_max_filesize'], $deployData['remoteInfo']['php']['upload_max_filesize'] ),
	'WordPress Version <span style="position: relative; top: -0.5em; font-size: 0.7em;">&dagger;</span>' => array( $deployData['remoteInfo']['wordpressVersion'], $localInfo['wordpressVersion'] ),
	'Solid Backups Version <span style="position: relative; top: -0.5em; font-size: 0.7em;">&Dagger;</span>' => array( $deployData['remoteInfo']['backupbuddyVersion'], $localInfo['backupbuddyVersion'] ),
	'Active Plugins' => array( $activePluginsB, $activePluginsA . $activePluginsAInfo ),
	'Active Theme' => array( $deployData['remoteInfo']['activeTheme'], $localInfo['activeTheme'] . ' ' . $activeThemeBInfo ),
	'Active Child Theme' => array( $remoteActiveChildTheme, $localInfo['activeChildTheme'] . ' ' . $activeChildThemeBInfo ),
	'Media / Attachments' => array( $deployData['remoteInfo']['mediaCount'], $localInfo['mediaCount'] . ' <a href="javascript:void(0);" class="deploy-show-files" rel="pullMediaFiles" title="Click to list files.">(' . count( $deployData['pullMediaFiles'] ) . ' files to pull)</a>' ),
	'Additional Inclusions' => array( $deployData['remoteInfo']['extraCount'], $localInfo['extraCount'] . ' <a href="javascript:void(0);" class="deploy-show-files" rel="pullExtraFiles" title="Click to list files.">(' . count( $deployData['pullExtraFiles'] ) . ' files to pull)</a>' ),
);


$deployDirection = 'pull';
require( '_pushpull_foot.php' );