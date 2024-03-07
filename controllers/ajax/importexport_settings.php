<?php
/**
 * Import/Export Settings AJAX Controller
 * Popup thickbox for importing and exporting settings.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::load();
pb_backupbuddy::$ui->ajax_header( true, true, 'backupbuddy-admin-iframe-white' );

if ( '' != pb_backupbuddy::_POST( 'import_settings' ) ) {
	$import = trim( stripslashes( pb_backupbuddy::_POST( 'import_data' ) ) );
	$import = base64_decode( $import );
	if ( false === $import ) { // decode failed.
		pb_backupbuddy::alert( 'Unable to decode settings data. Import aborted. Insure that you fully copied the settings and did not change any of the text.' );
	} else { // decode success.
		$import = maybe_unserialize( $import );
		if ( false === $import ) { // unserialize fail.
			pb_backupbuddy::alert( 'Unable to unserialize settings data. Import aborted. Insure that you fully copied the settings and did not change any of the text.' );
		} else { // unserialize success.
			if ( ! isset( $import['data_version'] ) ) { // missing expected content.
				pb_backupbuddy::alert( 'Unserialized settings data but it did not contain expected data. Import aborted. Insure that you fully copied the settings and did not change any of the text. Debugging data: `<pre>' . print_r( $import, true ) . '</pre>`.' );
			} else { // contains expected content.
				// Delete any existing scheduled hooks so that imported schedules overwrite existing 'next run' settings.
				$schedules = backupbuddy_api::getSchedules();
				if ( $schedules ) {
					foreach ( $schedules as $schedule ) {
						if ( ! empty( $schedule['id'] ) ) {
							wp_clear_scheduled_hook( backupbuddy_constants::CRON_HOOK, array( 'run_scheduled_backup', array( (int) $schedule['id'] ) ) );
						}
					}
				}

				// Delete all Stash destinations as they are URL dependant.
				if ( ! empty( $import['remote_destinations'] ) ) {
					$skipped_destinations = array();
					foreach ( $import['remote_destinations'] as $remote_key => $remote_data ) {
						if ( ! in_array( $remote_data['type'], array( 'stash', 'stash2', 'live' ) ) ) {
							continue;
						}
						$skipped_destinations[] = '&#8227; ' . $remote_data['title'];
						unset( $import['remote_destinations'][ $remote_key ] );
					}
				}

				// Delete Diagnostics data stored in settings.
				$import['tested_php_runtime']      = 0;
				$import['tested_php_memory']       = 0;
				$import['last_tested_php_runtime'] = 0;
				$import['last_tested_php_memory']  = 0;

				// Run Import.
				pb_backupbuddy::$options = $import;
				require_once pb_backupbuddy::plugin_path() . '/controllers/activation.php'; // Run data migration to upgrade if needed.
				pb_backupbuddy::save();
				pb_backupbuddy::alert( 'Provided settings successfully imported. Prior settings overwritten.' );

				// Alert skipped destinations if present.
				if ( ! empty( $skipped_destinations ) ) {
					pb_backupbuddy::alert( 'The following Stash destinations were not imported because they are site specific: <p>' . implode( $skipped_destinations, '<br />' ) . '</p> <a href="' . esc_attr( get_admin_url() ) . 'admin.php?page=pb_backupbuddy_destinations" target="_parent">View Destinations</a>' );
				}
			}
		}
	}
}
?>

<div class="import-export-content">
	<h2><?php esc_html_e( 'Export Solid Backups Settings', 'it-l10n-backupbuddy' ); ?></h2>
	<p><?php esc_html_e( 'Copy the encoded plugin settings below and paste it into the destination Solid Backups Settings Import page.', 'it-l10n-backupbuddy' ); ?></p>
	<div class="solid-backups-form">
		<textarea style="width: 100%; max-width: 100%; height: 100px;" wrap="on"><?php echo esc_html( base64_encode( serialize( pb_backupbuddy::$options ) ) ); ?></textarea>
	</div>
	<h2><?php esc_html_e( 'Import Solid Backups Settings', 'it-l10n-backupbuddy' ); ?></h2>
	<p><?php esc_html_e(' Paste encoded plugin settings below to import & replace current settings.  If importing settings from an older version and errors are encountered please deactivate and reactivate the plugin.', 'it-l10n-backupbuddy' ); ?></p>
	<form method="post" action="<?php echo esc_attr( pb_backupbuddy::ajax_url( 'importexport_settings' ) ); ?>" class="solid-backups-form">
		<textarea style="width: 100%; max-width: 100%; height: 100px;" wrap="on" name="import_data"></textarea><br/>
		<div class="solid-backups-form-buttons"><input type="submit" name="import_settings" value="<?php echo esc_attr( __( 'Import Settings', 'it-l10n-backupbuddy' ) ); ?>" class="button button-primary"></div>
	</form>
</div>
<?php
pb_backupbuddy::$ui->ajax_footer();
die();
