<?php
/**
 * sFTP destination path picker.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

if ( ! class_exists( 'pb_backupbuddy_destination_sftp' ) ) {
	require_once pb_backupbuddy::plugin_path() . '/destinations/sftp/init.php';
}

/**
 * SFTP Detailed List.
 *
 * @param object $sftp          Resource object.
 * @param string $directory     Directory path.
 * @param bool   $folders_only  Only return folders.
 *
 * @return array|false  Array of files or false.
 */
function pb_backupbuddy_sftp_list_detailed( $sftp, $directory = '.', $folders_only = true ) {
	$items    = array();
	$children = $sftp->rawlist( $directory );
	if ( is_array( $children ) ) {
		foreach ( $children as $filename => $child ) {
			if ( in_array( $filename, array( '.', '..' ), true ) ) {
				continue;
			}

			$item = array(
				'type' => in_array( $child['type'], array( NET_SFTP_TYPE_DIRECTORY, NET_SFTP_TYPE_SYMLINK ), true ) ? 'directory' : 'file',
			);

			if ( true === $folders_only && 'directory' !== $item['type'] ) {
				continue;
			}

			$items[ $filename ] = $item;
		}

		ksort( $items );
	}
	return $items;
} // end listDetailed subfunction.

$settings = array(
	'address'  => pb_backupbuddy::_GET( 'pb_backupbuddy_address' ),
	'username' => pb_backupbuddy::_GET( 'pb_backupbuddy_username' ),
	'password' => pb_backupbuddy::_GET( 'pb_backupbuddy_password' ),
	'disabled' => pb_backupbuddy::_GET( 'pb_backupbuddy_disabled' ),
	'path'     => '',
);

if ( ! $settings['address'] || ! $settings['username'] || ! $settings['password'] ) {
	die( esc_html__( 'Missing required sFTP server inputs.', 'it-l10n-backupbuddy' ) );
}

$sftp = pb_backupbuddy_destination_sftp::connect( $settings );

if ( ! $sftp ) {
	die( esc_html__( 'Could not connect to sFTP server.', 'it-l10n-backupbuddy' ) );
}

// Calculate root.
$sftp_root = urldecode( pb_backupbuddy::_POST( 'dir' ) );
if ( ! $sftp_root ) { // No root passed so figure out root from FTP server itself.
	$sftp_root = $sftp->pwd();
}

$sftp_list = pb_backupbuddy_sftp_list_detailed( $sftp, $sftp_root );

echo '<ul class="jqueryFileTree pb_backupbuddy_sftpdestination_pathpickerboxtree">';
if ( count( $sftp_list ) ) {
	foreach ( $sftp_list as $filename => $file ) {
		echo '<li class="directory collapsed">';
		echo '<a href="#" rel="' . esc_attr( $sftp_root . $filename ) . '/" title="Toggle expand...">';
		echo esc_html( $filename );
		echo '<div class="pb_backupbuddy_treeselect_control">';
		echo '<img src="' . esc_attr( pb_backupbuddy::plugin_url() ) . '/assets/dist/images/greenplus.png" style="vertical-align: -3px;" title="Select this path..." class="pb_backupbuddy_filetree_select">';
		echo '</div>';
		echo '</a>';
		echo '</li>';
	}
} else {
	echo '<ul class="jqueryFileTree" style="margin-left: 0;">';
	echo '<li><a href="#" rel="' . esc_attr( pb_backupbuddy::_POST( 'dir' ) ) . '" style="padding-left: 0;"><i>No subdirectories found.</i></a></li>';
	echo '</ul>';
}
echo '</ul>';

die();
