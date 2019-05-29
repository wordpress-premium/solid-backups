<?php
/**
 * Migrate Restore Page
 *
 * @package BackupBuddy
 */

if ( '' != pb_backupbuddy::_GET( 'rollback' ) ) {
	require_once pb_backupbuddy::plugin_path() . '/controllers/pages/_rollback.php';
	return;
}
if ( '' != pb_backupbuddy::_GET( 'zip_viewer' ) ) {
	require_once '_zip_viewer.php';
	return;
}

wp_enqueue_script( 'thickbox' );
wp_print_scripts( 'thickbox' );
wp_print_styles( 'thickbox' );

// Handle remote sending ImportBuddy.
if ( 'importbuddy.php' == pb_backupbuddy::_GET( 'callback_data' ) ) {

	pb_backupbuddy::alert( '<span id="pb_backupbuddy_ib_sent">Sending ImportBuddy file. This may take several seconds. Please wait ...</span>' );
	pb_backupbuddy::flush();

	pb_backupbuddy::anti_directory_browsing( backupbuddy_core::getTempDirectory(), false );

	$importbuddy_file = backupbuddy_core::getTempDirectory() . 'importbuddy.php';

	// Render ImportBuddy to temp location.
	backupbuddy_core::importbuddy( $importbuddy_file );
	if ( file_exists( $importbuddy_file ) ) {
		$response = backupbuddy_core::send_remote_destination( $_GET['destination'], $importbuddy_file, 'manual' );
	} else {
		pb_backupbuddy::alert( 'Error #4589: Local importbuddy.php file not found for sending. Check directory permissions and / or manually migrating by downloading importbuddy.php.' );
		$response = false;
	}


	if ( file_exists( $importbuddy_file ) ) {
		if ( false === unlink( $importbuddy_file ) ) { // Delete temporary ImportBuddy file.
			pb_backupbuddy::alert( 'Unable to delete file. For security please manually delete it: `' . $importbuddy_file . '`.' );
		}
	}

	$ib_text = true === $response ? __( 'ImportBuddy file successfully sent.', 'it-l10n-backupbuddy' ) : __( 'ImportBuddy file send failure. Verify your destination settings & check logs for details.', 'it-l10n-backupbuddy' );
	?>
	<script type="text/javascript">
		jQuery( '#pb_backupbuddy_ib_sent' ).html( '<?php echo esc_html( $ib_text ); ?>' );
	</script>
	<?php
}


pb_backupbuddy::$ui->title( __( 'Restore / Migrate', 'it-l10n-backupbuddy' ) );
echo '<br>';

if ( '' == pb_backupbuddy::$options['importbuddy_pass_hash'] ) { // NO HASH SET.
	$import_alert = '<span class="pb_label pb_label">Important</span> <b>Set an ImportBuddy password on the <a href="';
	if ( is_network_admin() ) {
		$import_alert .= network_admin_url( 'admin.php' );
	} else {
		$import_alert .= admin_url( 'admin.php' );
	}
	$import_alert .= '?page=pb_backupbuddy_settings">Settings</a> page before attempting to Migrate to a new server.</b>';
	pb_backupbuddy::alert( $import_alert, true );
}

pb_backupbuddy::disalert( 'importbuddy_in_backup_note', __( 'TIP: An additional copy of the restore script, importbuddy.php, is also saved inside your backup zip file for your added convenience. It can be found inside the zip file at /wp-content/uploads/backupbuddy_temp/XXXXXX/importbuddy.php, replacing XXXXXX with the random characters in the backup zip filename.', 'it-l10n-backupbuddy' ) );
?>

<ol>
	<h3 style="margin-top: 0;">importbuddy.php</h3>
	<li>
		<a id="pb_backupbuddy_downloadimportbuddy" href="<?php echo pb_backupbuddy::ajax_url( 'importbuddy' ); ?>" class="pb_backupbuddy_get_importbuddy" style="vertical-align: 0;">Download importbuddy.php</a> or
		<a id="pb_backupbuddy_sendimportbuddy" href="" rel="importbuddy.php" class="pb_backupbuddy_hoveraction_sendimportbuddy">send importbuddy.php to a remote destination</a>
	</li>
	<li>
		Download a backup zip file below and upload it to your destination's web root where you want it restored (eg. /home/buddy/public_html/) or select "Send" when hovering below to send to a remote destination.
	</li>
		<ul>
			<li>
				Tip: WordPress should not be installed prior to the restore. You should delete it if it already exists.
			<li>
				Tip: Full backups should be restored before restoring database only backups.
			</li>
		</ul>
	</li>
	<li>Navigate to the uploaded importbuddy.php URL in your web browser (ie http://your.com/importbuddy.php) and follow the instructions.</li>
</ol>
<br><br>

<?php
pb_backupbuddy::flush();

$backups      = backupbuddy_core::backups_list( 'default' );
$listing_mode = 'default';
require_once pb_backupbuddy::plugin_path() . '/views/_backup_listing.php';

// Handles thickbox auto-resizing. Keep at bottom of page to avoid issues.
if ( ! wp_script_is( 'media-upload' ) ) {
	wp_enqueue_script( 'media-upload' );
	wp_print_scripts( 'media-upload' );
}
