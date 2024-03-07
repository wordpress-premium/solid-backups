<?php
echo $this->status_box( 'Cleaning up . . .' );
echo '<div id="pb_importbuddy_working" style="width: 100px;"><center><img src="' . pb_backupbuddy::plugin_url() . '/assets/dist/images/working.gif" title="Working... Please wait as this may take a moment..."></center></div>';
pb_backupbuddy::flush();

$this->load_backup_dat(); // Set up backup data from the backupbuddy_dat.php.

$url = '';
if ( is_subdomain_install() ) {
	$url = 'http://' . $_POST[ 'blog_path' ];
} else {
	global $current_site;
	$url = 'http://' . rtrim( $current_site->domain, '\\/' ) . '/' . ltrim( $_POST[ 'blog_path' ], '\\/' );
}


if ( isset( $_POST['delete_backup'] ) && ( $_POST['delete_backup'] == '1' ) ) {
	$this->status( 'message', 'Deleting backup file.' );
	$this->remove_file( $this->import_options[ 'file' ], 'backup .ZIP file', true );
} else {
	$this->status( 'message', 'Skipping backup file deletion.' );
}

if ( isset( $_POST['delete_temp'] ) && ( $_POST['delete_temp'] == '1' ) ) {
	$this->status( 'message', 'Deleting temporary files.' );
	pb_backupbuddy::$filesystem->unlink_recursive( $this->import_options[ 'extract_to' ] );
} else {
	$this->status( 'message', 'Skipping temporary file deletion.' );
}


$this->status( 'message', 'Cleanup complete.' );
echo '<script type="text/javascript">jQuery("#pb_importbuddy_working").hide();</script>';
pb_backupbuddy::flush();
?>

<h3>Site Import Complete</h3>
Your site has been succesfully imported into the Multisite Network.

<br><br>

<b>Site</b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_url( $url ); ?></a>
