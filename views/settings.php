<?php
/**
 * Settings Page View
 *
 * @package BackupBuddy
 */

is_admin() || die( 'Access Denied.' );
?>
<script type="text/javascript">
	function pb_backupbuddy_selectdestination( destination_id, destination_title, callback_data, delete_after, mode ) {
		window.location.href = '<?php echo pb_backupbuddy::page_url(); ?>&custom=remoteclient&destination_id=' + destination_id;
	}
</script>

<?php
// This should be a public method in Ithemes_Updater_Admin.
$license_url = admin_url( 'options-general.php' );
if ( is_network_admin() ) {
	$license_url = network_admin_url( 'settings.php' );
}
$license_url .= '?page=ithemes-licensing';

if ( ! class_exists( 'BackupBuddy_Tabs' ) ) {
	require_once pb_backupbuddy::plugin_path() . '/classes/class-backupbuddy-tabs.php';
}

$tabs = new BackupBuddy_Tabs(
	array(
		array(
			'id'       => 'general',
			'label'    => esc_html__( 'General Settings', 'it-l10n-backupbuddy' ),
			'callback' => function() use ( $importbuddy_pass_dummy_text ) {
				require pb_backupbuddy::plugin_path() . '/views/settings/_general.php';
			},
		),
		array(
			'id'       => 'advanced',
			'label'    => esc_html__( 'Advanced Settings / Troubleshooting', 'it-l10n-backupbuddy' ),
			'callback' => function() {
				require pb_backupbuddy::plugin_path() . '/views/settings/_advanced.php';
			},
		),
		array(
			'id'    => 'licensing',
			'label' => esc_html__( 'Licensing', 'it-l10n-backupbuddy' ),
			'href'  => $license_url,
		),
	)
);
$tabs->render();

$admin_url = is_network_admin() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' );
?>
<script type="text/javascript">
	function pb_backupbuddy_selectdestination( destination_id, destination_title, callback_data ) {
		window.location.href = '<?php echo $admin_url; ?>?page=pb_backupbuddy_backup&custom=remoteclient&destination_id=' + destination_id;
	}
</script>
<?php
// Handles thickbox auto-resizing. Keep at bottom of page to avoid issues.
if ( ! wp_script_is( 'media-upload' ) ) {
	wp_enqueue_script( 'media-upload' );
	wp_print_scripts( 'media-upload' );
}
