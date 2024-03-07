<?php // Incoming vars: $sha1 (bool), $file_includes (array), $file_excludes (array), $destinationSettings (from the site initiating the transfer, whether this site or remote)
require_once( pb_backupbuddy::plugin_path() . '/destinations/site/init.php' );


if ( ! isset( $sha1 ) ) {
	$sha1 = false; // Whether to calculate sha1 hash for determining file differences.
}


$upload_max_filesize = str_ireplace( 'M', '', @ini_get( 'upload_max_filesize' ) );
if ( ( ! is_numeric( $upload_max_filesize ) ) || ( 0 == $upload_max_filesize ) ) {
	$upload_max_filesize = 1;
}

$max_execution_time = str_ireplace( 's', '', @ini_get( 'max_execution_time' ) );
if ( ( ! is_numeric( $max_execution_time ) ) || ( 0 == $max_execution_time ) ) {
	$max_execution_time = 30;
}

$memory_limit = str_ireplace( 'M', '', @ini_get( 'memory_limit' ) );
if ( ( ! is_numeric( $memory_limit ) ) || ( 0 == $memory_limit ) ) {
	$memory_limit = 32;
}

$max_post_size = str_ireplace( 'M', '', @ini_get( 'post_max_size' ) );
if ( ( ! is_numeric( $max_post_size ) ) || ( 0 == $max_post_size ) ) {
	$max_post_size = 8;
}

$dbTables = array();
global $wpdb;
$rows = $wpdb->get_results( "SHOW TABLE STATUS", ARRAY_A );
foreach( $rows as $row ) {
	
	// Hide Solid Backups temp tables.
	if ( 'bbold-' == substr( $row['Name'], 0, 6 ) ) {
		continue;
	}
	if ( 'bbnew-' == substr( $row['Name'], 0, 6 ) ) {
		continue;
	}
	
	$dbTables[] = $row['Name'];
}






/* backupbuddy_dbMediasince()
 *
 * Generate list of media files with modified times. Optionally include thumbnail media files (default).
 *
 * @return	array 			Array of media files arrays. Eg array( 'filename.jpg' => array( 'modified' => 1111111111
 *
 */
function backupbuddy_dbMediaSince( $includeThumbs = true ) {
	global $wpdb;
	$wpdb->show_errors(); // Turn on error display.
	
	$mediaFiles = array();
	
	// Select all media attachments.
	$sql = "select " . $wpdb->prefix . "postmeta.meta_value as file," . $wpdb->prefix . "posts.post_modified as file_modified," . $wpdb->prefix . "postmeta.meta_key as meta_key from " . $wpdb->prefix . "postmeta," . $wpdb->prefix . "posts WHERE ( meta_key='_wp_attached_file' OR meta_key='_wp_attachment_metadata' ) AND " . $wpdb->prefix . "postmeta.post_id = " . $wpdb->prefix . "posts.id ORDER BY meta_key ASC";
	$results = $wpdb->get_results( $sql, ARRAY_A );
	if ( ( null === $results ) || ( false === $results ) ) {
		pb_backupbuddy::status( 'error', 'Error #238933: Unable to calculate media with query `' . $sql . '`. Check database permissions or contact host.' );
	}
	
	foreach( (array)$results as $result ) {
		
		if ( $result['meta_key'] == '_wp_attached_file' ) {
			$mediaFiles[ $result['file'] ] = array(
				'modified'	=> $result['file_modified']
			);
		}
		
		// Include thumbnail image files.
		if ( true === $includeThumbs ) {
			if ( $result['meta_key'] == '_wp_attachment_metadata' ) {
				$data = unserialize( $result['file'] );
				foreach( $data['sizes'] as $size ) { // Go through each sized thumbnail file.
					$mediaFiles[ $size['file'] ] = array(
						'modified'	=> $mediaFiles[ $data['file'] ]['modified']
					);
				}
			}
		}
		
	} // end foreach $results.
	unset( $results );
	return $mediaFiles;
	
} // End backupbuddy_dbMediaSince().


// Get list of active plugins and remove Solid Backups from it so we don't update any Solid Backups files when deploying. Could cause issues with the API replacing files mid-deploy.
$activePlugins = backupbuddy_api::getActivePlugins();
foreach( $activePlugins as $activePluginIndex => $activePlugin ) {
	if ( false !== strpos( $activePlugin['name'], 'Solid Backups' ) ) {
		unset( $activePlugins[ $activePluginIndex ] );
	}
}
$activePluginDirs = array();
foreach( $activePlugins as $activePluginDir => $activePlugin ) {
	$activePluginDirs[] = dirname( WP_PLUGIN_DIR . '/' . $activePluginDir );
}
$allPluginDirs = glob( WP_PLUGIN_DIR . '/*', GLOB_ONLYDIR );
$inactivePluginDirs = array_diff( $allPluginDirs, $activePluginDirs ); // Remove active plugins from directories of all plugins to get directories of inactive plugins to exclude later.
$inactivePluginDirs[] = pb_backupbuddy::plugin_path(); // Also exclude Solid Backups directory.
$pluginsExcludes = array_merge( $inactivePluginDirs, pb_backupbuddy_destination_site::get_exclusions( $destinationSettings, 'plugins' ) );
$pluginsExcludes = array_filter( $pluginsExcludes );

$upload_dir = wp_upload_dir();
$mediaSignatures = backupbuddy_core::hashGlob( $upload_dir['basedir'], $sha1, pb_backupbuddy_destination_site::get_exclusions( $destinationSettings, 'media' ), $handle_utf8 = true );


// Calculate child theme file signatures, excluding main theme directory..
if ( get_stylesheet_directory() == get_template_directory() ) { // Theme & childtheme are same so do not send any childtheme files!
	$childThemeSignatures = array();
} else {
	$childThemeSignatures = backupbuddy_core::hashGlob( get_stylesheet_directory(), $sha1, pb_backupbuddy_destination_site::get_exclusions( $destinationSettings, 'childtheme' ) );
}


// CALCULATE EXTRAS
$extrasRaws = $destinationSettings['extras'];
$extrasRaws = explode( "\n", $extrasRaws );
foreach( $extrasRaws as &$extrasRaw ) {
	$extras[] = trim( $extrasRaw );
}
$extras = array_filter( $extras );

$extrasSignatures = array();
foreach( $extras as $extra ) {
	$extrasSignatures = array_merge( $extrasSignatures, backupbuddy_core::hashGlob( ABSPATH . $extra, $sha1, pb_backupbuddy_destination_site::get_exclusions( $destinationSettings, 'customroot', ABSPATH . $extra ), null, rtrim( $extra, '/\\' ) ) );
}
$extrasSignatures = array_unique( $extrasSignatures, SORT_REGULAR ); // SORT_REGULAR keeps arrays inside intact.

/*
error_log( 'pluginFiles:' );
error_log( print_r( backupbuddy_core::hashGlob( WP_PLUGIN_DIR, $sha1, $inactivePluginDirs ), true ) );
*/

global $wp_version;
return array(
	'backupbuddyVersion'		=> pb_backupbuddy::settings( 'version' ),
	'wordpressVersion'			=> $wp_version,
	'localTime'				=> time(),
	'php'					=> array(
								'upload_max_filesize' => $upload_max_filesize,
								'max_execution_time' => $max_execution_time,
								'memory_limit' => $memory_limit,
								'max_post_size' => $max_post_size,
								),
	'abspath'					=> ABSPATH,
	'siteurl'					=> site_url(),
	'homeurl'					=> home_url(),
	'tables'					=> $dbTables,
	'dbPrefix'				=> $wpdb->prefix,
	'activePlugins'			=> $activePlugins,
	'activeTheme'				=> get_template(),
	'activeChildTheme'			=> get_stylesheet(),
	'themeSignatures'			=> backupbuddy_core::hashGlob( get_template_directory(), $sha1, pb_backupbuddy_destination_site::get_exclusions( $destinationSettings, 'theme' ) ),
	'childThemeSignatures'		=> $childThemeSignatures,
	'pluginSignatures'			=> backupbuddy_core::hashGlob( WP_PLUGIN_DIR, $sha1, $pluginsExcludes ),
	'mediaSignatures'			=> $mediaSignatures,
	'mediaCount'				=> count( $mediaSignatures ),
	'extraSignatures'			=> $extrasSignatures,
	'extraCount'				=> count( $extrasSignatures ),
	'notifications'			=> array(), // Array of string notification messages.
);



