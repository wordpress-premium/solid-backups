<?php
/**
 * FTP destination path picker.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

if ( ! class_exists( 'pb_backupbuddy_destination_ftp' ) ) {
	require_once pb_backupbuddy::plugin_path() . '/destinations/ftp/init.php';
}

/**
 * FTP Detailed List.
 *
 * @param object $resource      Resource object.
 * @param string $directory     Directory path.
 * @param bool   $folders_only  Only return folders.
 *
 * @return array|false  Array of files or false.
 */
function pb_backupbuddy_ftp_listDetailed( $resource, $directory = '.', $folders_only = true ) {
	$items    = array();
	$children = @ftp_rawlist( $resource, $directory );
	if ( is_array( $children ) ) {
		foreach ( $children as $child ) {
			$item   = array(
				'rights',
				'number',
				'user',
				'group',
				'size',
				'month',
				'day',
				'time',
			);
			$chunks = preg_split( '/\s+/', $child );

			list( $item['rights'], $item['number'], $item['user'], $item['group'], $item['size'], $item['month'], $item['day'], $item['time'] ) = $chunks;

			$item['type'] = 'd' === $chunks[0][0] ? 'directory' : 'file';

			if ( true === $folders_only && 'directory' !== $item['type'] ) {
				continue;
			}

			array_splice( $chunks, 0, 8 );
			$filename = implode( ' ', $chunks );
			if ( in_array( $filename, array( '.', '..' ), true ) ) {
				continue;
			}
			$items[ $filename ] = $item;
		}
		ksort( $items );
	}
	return $items;
} // end listDetailed subfunction.

$settings = array(
	'address'     => pb_backupbuddy::_GET( 'pb_backupbuddy_address' ),
	'username'    => pb_backupbuddy::_GET( 'pb_backupbuddy_username' ),
	'password'    => pb_backupbuddy::_GET( 'pb_backupbuddy_password' ),
	'ftps'        => pb_backupbuddy::_GET( 'pb_backupbuddy_ftps' ),
	'active_mode' => pb_backupbuddy::_GET( 'pb_backupbuddy_active_mode' ),
	'disabled'    => pb_backupbuddy::_GET( 'pb_backupbuddy_disabled' ),
	'path'        => '',
);

if ( ! $settings['address'] || ! $settings['username'] || ! $settings['password'] ) {
	die( esc_html__( 'Missing required FTP server inputs.', 'it-l10n-backupbuddy' ) );
}

$conn_id = pb_backupbuddy_destination_ftp::connect( $settings );

if ( ! $conn_id ) {
	die( esc_html__( 'Could not connect to FTP server.', 'it-l10n-backupbuddy' ) );
}

// Calculate root.
$ftp_root = urldecode( pb_backupbuddy::_POST( 'dir' ) );
if ( ! $ftp_root ) { // No root passed so figure out root from FTP server itself.
	$ftp_root = ftp_pwd( $conn_id );
}

$ftp_list = pb_backupbuddy_ftp_listDetailed( $conn_id, $ftp_root );

echo '<ul class="jqueryFileTree pb_backupbuddy_ftpdestination_pathpickerboxtree">';
if ( count( $ftp_list ) ) {
	foreach ( $ftp_list as $file_name => $file ) {
		echo '<li class="directory collapsed">';
		$return  = '<div class="pb_backupbuddy_treeselect_control">';
		$return .= '<img src="' . pb_backupbuddy::plugin_url() . '/assets/dist/images/greenplus.png" style="vertical-align: -3px;" title="Select this path..." class="pb_backupbuddy_filetree_select">';
		$return .= '</div>';
		echo '<a href="#" rel="' . esc_attr( $ftp_root . $file_name ) . '/" title="Toggle expand...">' . esc_html( $file_name ) . $return . '</a>';
		echo '</li>';
	}
} else {
	echo '<ul class="jqueryFileTree" style="margin-left: 0;">';
	echo '<li><a href="#" rel="' . esc_attr( pb_backupbuddy::_POST( 'dir' ) ) . '" style="padding-left: 0;"><i>No subdirectories found.</i></a></li>';
	echo '</ul>';
}
echo '</ul>';

die();
