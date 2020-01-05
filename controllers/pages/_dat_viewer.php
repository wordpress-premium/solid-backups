<?php
/**
 * Dat File Viewer
 *
 * Incoming vars:
 *     $dat_zip_file
 *
 * @package BackupBuddy
 */

if ( ! current_user_can( pb_backupbuddy::$options['role_access'] ) ) {
	pb_backupbuddy::alert( 'Access Denied. Error 445543454754.', true );
	return;
}

if ( ! pb_backupbuddy::_GET( 'dat_viewer' ) ) {
	pb_backupbuddy::alert( 'Error #201906260922: Missing required URL parameter.', true );
	return;
}

if ( empty( $dat_zip_file ) ) {
	pb_backupbuddy::alert( 'Error #201907090824: Missing required zip file variable.', true );
	return;
}

pb_backupbuddy::disalert( 'restore_caution', __( 'Caution: Files will be restored relative to the site WordPress installation directory, NOT necessarily their original location. Restored files may overwrite existing files of the same name. Use caution when restoring, especially when restoring large numbers of files to avoid breaking the site.', 'it-l10n-backupbuddy' ), false, '', array( 'class' => 'below-h2' ) );

$backup_timestamp = backupbuddy_core::parse_file( $dat_zip_file, 'timestamp' );
$backup_serial    = backupbuddy_core::parse_file( $dat_zip_file, 'serial' );
$backup_local     = pb_backupbuddy::$format->localize_time( $backup_timestamp );
$backup_date      = pb_backupbuddy::$format->date( $backup_timestamp, 'l, F j, Y g:i a' );
$backup_time_ago  = pb_backupbuddy::$format->time_ago( $backup_timestamp ) . ' ago';
$destination_id   = pb_backupbuddy::_GET( 'destination' );
?>

<div class="backupbuddy-restore-header">
	<h3><?php echo esc_html( $backup_date ); ?> (<?php echo esc_html( $backup_time_ago ); ?>)</h3>
	<div class="backup-size"></div>
	<div class="backup-wp-version"></div>
</div>

<div id="backupbuddy-data-browser">
	<form method="post" action="" data-zip="<?php echo esc_attr( $dat_zip_file ); ?>" data-serial="<?php echo esc_attr( $backup_serial ); ?>">
		<div id="backupbuddy-file-tree" class="backupbuddy-file-tree panels loading">
			Loading...
		</div>
		<div class="backupbuddy-restore-list">
			<div class="confirm hidden">
				<p><strong><?php esc_html_e( 'WARNING', 'it-l10n-backupbuddy' ); ?></strong> - <?php esc_html_e( 'Any existing files will be overwritten. This cannot be undone. Are you sure you want to restore selected files/folders?', 'it-l10n-backupbuddy' ); ?></p>
				<input type="submit" value="Yes, Proceed">
				<button class="cancel-button">Cancel</button>
			</div>
			<h3 class="restore-heading">Restore Files/Folders</h3>
			<p class="instructions">Select files from the left to add them to the restore list.</p>
			<ul id="backupbuddy-restore-ui"></ul>
			<input type="submit" value="Restore" class="button button-primary button-restore" style="display:none;">
		</div>
		<input type="hidden" name="backupbuddy_restore_destination" value="<?php echo esc_attr( $destination_id ); ?>">
	</form>
</div>
