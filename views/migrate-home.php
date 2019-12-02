<?php
/**
 * Migrate Home View File
 *
 * @package BackupBuddy
 */

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


The best way to Restore or Migrate your site is by using a standalone PHP script named <b>importbuddy.php</b>. This file is run without first
installing WordPress, in combination with your backup ZIP file will allow you to restore this server or to a new server entirely. Sites may be
restored to a new site URL or domain.
You should keep a copy of importbuddy.php for future restores.  It is also stored within backup ZIP files for your convenience. importbuddy.php files are not
site/backup specific.
<br><br>
<ol>
	<li>
		<a id="pb_backupbuddy_downloadimportbuddy" href="<?php echo pb_backupbuddy::ajax_url( 'importbuddy' ); ?>" class="button button-primary pb_backupbuddy_get_importbuddy">Download importbuddy.php</a> or
		<a id="pb_backupbuddy_sendimportbuddy" href="" rel="importbuddy.php" class="button button-primary pb_backupbuddy_hoveraction_sendimportbuddy">Send importbuddy.php to a Destination</a>
	</li>
	<li>
		Download a backup zip file from the list below or send it directly to a destination by selecting "Send file" when hovering over a backup below.
	</li>
	<li>
		Upload importbuddy.php & the downloaded backup zip file to the destination server directory where you want your site restored.
		<ul style="list-style-type: circle; margin-left: 20px; margin-top: 8px;">
			<li>
				Upload these into the FTP directory for your site's web root such as /home/buddy/public_html/.
				If you want to restore into a subdirectory, put these files in it.
			</li>
			<li>
				WordPress should not be installed prior to the restore. You should delete it if it already exists.
			<li>
				Full backups should be restored before restoring database only backups.
			</li>
		</ul>
	</li>
	<li>Navigate to the uploaded importbuddy.php URL in your web browser (ie http://your.com/importbuddy.php).</li>
	<li>Follow the on-screen directions until the restore / migration is complete.</li>
</ol>

<br><br>

<h3 id="pb_backupbuddy_restoremigratelisttitle">Hover Backup for Additional Options</h3>
<?php
$listing_mode = 'restore_migrate';
require_once '_backup_listing.php';

echo '<br><br>';

// Handles thickbox auto-resizing. Keep at bottom of page to avoid issues.
if ( ! wp_script_is( 'media-upload' ) ) {
	wp_enqueue_script( 'media-upload' );
	wp_print_scripts( 'media-upload' );
}
