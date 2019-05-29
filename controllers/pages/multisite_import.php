<?php
/**
 * Multisite Import Page
 *
 * @package BackupBuddy
 */

if ( ! class_exists( 'pluginbuddy_ms_import' ) ) {
	require_once pb_backupbuddy::plugin_path() . '/classes/class-pluginbuddy-ms-import.php';
}

pb_backupbuddy::$ui->title( 'Multisite Import Site (EXPERIMENTAL)' . ' ' . pb_backupbuddy::video( '4RmC5nLmabE', __( 'Multisite import', 'it-l10n-backupbuddy' ), false ) );

backupbuddy_core::versions_confirm();

pb_backupbuddy::set_status_serial( 'ms_import' );

$action = isset( $_GET['action'] ) ? $_GET['action'] : false;
?>
<div class="wrap">
	<p>For BackupBuddy Multisite documentation, please visit the <a href="https://ithemeshelp.zendesk.com/hc/en-us/articles/115004532967-Backup-Restore-and-Migrate-with-BackupBuddy-Multisite-Experimental-">BackupBuddy Multisite Codex</a>.</p>

	<?php
	if ( ! current_user_can( 'manage_sites' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'it-l10n-backupbuddy' ) );
	}
	$errors = false;
	$blog   = '';
	$domain = '';
	$path   = '';

	// ********** BEGIN IMPORT OPTIONS **********
	$import_options = array(
		'zip_id'                     => '',
		'extract_to'                 => '',
		'show_php_warnings'          => false,
		'type'                       => 'zip',
		'skip_files'                 => false,
		'force_compatibility_slow'   => false,
		'force_compatibility_medium' => true,
		'skip_database_import'       => false,
	);

	// Set backup file.
	if ( isset( $_POST['backup_file'] ) ) {
		$import_options['file'] = $_POST['backup_file'];
	}
	// ********** END IMPORT OPTIONS **********

	$pluginbuddy_ms_import = new pluginbuddy_ms_import( $action, $import_options );
	?>
</div><!-- .wrap-->
