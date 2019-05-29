<?php
/**
 * FTP destination path picker.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

/**
 * FTP Detailed List.
 *
 * @param object $resource   Resource object.
 * @param string $directory  Directory path.
 *
 * @return array|false  Array of files or false.
 */
function pb_backupbuddy_ftp_listDetailed( $resource, $directory = '.' ) {
	if ( is_array( $children = @ftp_rawlist( $resource, $directory ) ) ) {
		$items = array();

		foreach ( $children as $child ) {
			$chunks = preg_split( '/\s+/', $child );
			list($item['rights'], $item['number'], $item['user'], $item['group'], $item['size'], $item['month'], $item['day'], $item['time']) = $chunks;
			$item['type'] = $chunks[0]{0} === 'd' ? 'directory' : 'file';
			array_splice( $chunks, 0, 8 );
			$items[ implode( ' ', $chunks ) ] = $item;
		}

		return $items;
	}
	return false;
} // end listDetailed subfunction.

$settings = array(
	'address'     => pb_backupbuddy::_GET( 'pb_backupbuddy_address' ),
	'username'    => pb_backupbuddy::_GET( 'pb_backupbuddy_username' ),
	'password'    => pb_backupbuddy::_GET( 'pb_backupbuddy_password' ),
	'ftps'        => pb_backupbuddy::_GET( 'pb_backupbuddy_ftps' ),
	'active_mode' => pb_backupbuddy::_GET( 'pb_backupbuddy_active_mode' ),
);

if ( '' == $settings['address'] || '' == $settings['username'] || '' == $settings['password'] ) {
	die( esc_html__( 'Missing required FTP server inputs.', 'it-l10n-backupbuddy' ) );
}

// Settings.
$active_mode = true;
if ( '0' == $settings['active_mode'] ) {
	$active_mode = false;
}

$server = $settings['address'];
$port   = '21';
if ( strstr( $server, ':' ) ) {
	$server_params = explode( ':', $server );

	$server = $server_params[0];
	$port   = $server_params[1];
}

// Connect.
if ( '0' == $settings['ftps'] ) {
	$conn_id = @ftp_connect( $server, $port, 10 ); // timeout of 10 seconds.
	if ( false === $conn_id ) {
		$error  = esc_html__( 'Unable to connect to FTP address `' . $server . '` on port `' . $port . '`.', 'it-l10n-backupbuddy' );
		$error .= "\n" . esc_html__( 'Verify the server address and port (default 21). Verify your host allows outgoing FTP connections.', 'it-l10n-backupbuddy' );
		die( $error );
	}
} else {
	if ( function_exists( 'ftp_ssl_connect' ) ) {
		$conn_id = @ftp_ssl_connect( $server, $port );
		if ( false === $conn_id ) {
			die( esc_html__( 'Destination server does not support FTPS?', 'it-l10n-backupbuddy' ) );
		}
	} else {
		die( esc_html__( 'Your web server doesnt support FTPS.', 'it-l10n-backupbuddy' ) );
	}
}

// Authenticate.
$login_result = @ftp_login( $conn_id, $settings['username'], $settings['password'] );
if ( ! $conn_id || ! $login_result ) {
	pb_backupbuddy::status( 'details', 'FTP test: Invalid user/pass.' );
	$response = esc_html__( 'Unable to login to FTP server. Bad user/pass.', 'it-l10n-backupbuddy' );
	if ( '0' != $settings['ftps'] ) {
		$response .= "\n\nNote: You have FTPs enabled. You may get this error if your host does not support encryption at this address/port.";
	}
	die( $response );
}

pb_backupbuddy::status( 'details', 'FTP test: Success logging in.' );

// Handle active/pasive mode.
if ( true === $active_mode ) { // do nothing, active is default.
	pb_backupbuddy::status( 'details', 'Active FTP mode based on settings.' );
} elseif ( false === $active_mode ) { // Turn passive mode on.
	pb_backupbuddy::status( 'details', 'Passive FTP mode based on settings.' );
	ftp_pasv( $conn_id, true );
} else {
	pb_backupbuddy::status( 'error', 'Unknown FTP active/passive mode: `' . $active_mode . '`.' );
}

// Calculate root.
$ftp_root = urldecode( pb_backupbuddy::_POST( 'dir' ) );
if ( '' == $ftp_root ) { // No root passed so figure out root from FTP server itself.
	$ftp_root = ftp_pwd( $conn_id );
}

$ftp_list = pb_backupbuddy_ftp_listDetailed( $conn_id, $ftp_root );

echo '<ul class="jqueryFileTree pb_backupbuddy_ftpdestination_pathpickerboxtree">';
if ( count( $ftp_list ) > 2 ) {
	foreach ( $ftp_list as $file_name => $file ) {
		if ( '.' == $file_name || '..' == $file_name ) {
			continue;
		}
		if ( 'directory' == $file['type'] ) { // Directory.
			echo '<li class="directory collapsed">';
			$return  = '<div class="pb_backupbuddy_treeselect_control">';
			$return .= '<img src="' . pb_backupbuddy::plugin_url() . '/images/greenplus.png" style="vertical-align: -3px;" title="Select this path..." class="pb_backupbuddy_filetree_select">';
			$return .= '</div>';
			echo '<a href="#" rel="' . esc_attr( $ftp_root . $file_name ) . '/" title="Toggle expand...">' . esc_html( $file_name ) . $return . '</a>';
			echo '</li>';
		}
	}
} else {
	echo '<ul class="jqueryFileTree">';
	echo '<li><a href="#" rel="' . esc_attr( pb_backupbuddy::_POST( 'dir' ) . 'NONE' ) . '"><i>Empty Directory ...</i></a></li>';
	echo '</ul>';
}
echo '</ul>';

die();
