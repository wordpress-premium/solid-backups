<?php
/**
 * Live Last Snapshot Details AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();
pb_backupbuddy::$ui->ajax_header();
?>
<span class="pb_backupbuddy_live_last_snapshot_loading">
	<h3>Loading... <img src="<?php echo pb_backupbuddy::plugin_url(); ?>/images/loading.gif" title="Loading... This may take a few seconds..."></h3>
</span>
<?php
pb_backupbuddy::flush( true );

require_once pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php';
$state = backupbuddy_live_periodic::get_stats();

$destination_settings = backupbuddy_live_periodic::get_destination_settings();
$additional_params    = array(
	'snapshot' => $state['stats']['last_remote_snapshot_id'],
);

$response = pb_backupbuddy_destination_live::stashAPI( $destination_settings, 'live-snapshot-status', $additional_params );
?>
<script>
	jQuery( '.pb_backupbuddy_live_last_snapshot_loading' ).hide();
</script>
<?php
if ( ! is_array( $response ) ) {
	pb_backupbuddy::alert( 'Error #3497943: Unable to get Live snapshot status. Details: `' . $response . '`.' );
} else {
	echo '<h3>' . esc_html__( 'Overall', 'it-l10n-backupbuddy' ) . '</h3>';
	if ( true == $response['complete'] ) {
		$status_color = 'green';
	} else {
		$status_color = 'red';
	}
	$stats = array(
		array( 'ID', (string) $response['id'] ),
		array( 'Status', '<font color="' . $status_color . '">' . ucfirst( (string) $response['status'] ) . '</font> (' . (string) $response['message'] . ')' ),
		array( 'Started', (string) $response['timestamp_start'] ),
		array( 'Finished', (string) $response['timestamp_finish'] ),
		array( 'Duration', (string) $response['duration'] . ' secs' ),
		array( 'id', (string) $response['id'] ),
	);
	pb_backupbuddy::$ui->list_table(
		$stats,
		array(
			'columns' => array(
				__( 'Property', 'it-l10n-backupbuddy' ),
				__( 'Details', 'it-l10n-backupbuddy' ),
			),
			'css'     => 'width: 100%; min-width: 200px;',
		)
	);

	if ( isset( $response['snapshot'] ) && isset( $response['snapshot']['zips'] ) ) {
		echo '<br><h3>' . esc_html__( 'Downloads (links valid for 24 hours after Snapshot)', 'it-l10n-backupbuddy' ) . '</h3>';
		$stats = array(
			array( 'Full Snapshot', '<a target="_new" href="' . $response['snapshot']['zips']['full'] . '">Download</a>' ),
			array( 'Database Snapshot', '<a target="_new" href="' . $response['snapshot']['zips']['db'] . '">Download</a>' ),
			array( 'Themes Snapshot', '<a target="_new" href="' . $response['snapshot']['zips']['themes'] . '">Download</a>' ),
			array( 'Plugins Snapshot', '<a target="_new" href="' . $response['snapshot']['zips']['plugins'] . '">Download</a>' ),
			array( 'importbuddy.php', '<a target="_new" href="' . (string) $response['snapshot']['importbuddy']['url'] . '">Download</a> - Password: ' . (string) $response['snapshot']['importbuddy']['password'] ),
		);
		pb_backupbuddy::$ui->list_table(
			$stats,
			array(
				'columns' => array(
					__( 'Snapshot / File', 'it-l10n-backupbuddy' ),
					__( 'Download Link', 'it-l10n-backupbuddy' ),
				),
				'css'     => 'width: 100%; min-width: 200px;',
			)
		);
	}

	if ( isset( $response['snapshot'] ) && isset( $response['snapshot']['malware'] ) ) {
		echo '<br><br><h3>' . esc_html__( 'Malware Scan', 'it-l10n-backupbuddy' ) . '</h3>';
		if ( $response['snapshot']['malware']['stats']['scanned_files'] > 0 ) {
			$infected_color = 'red';
		} else {
			$infected_color = 'green';
		}
		$infected = '';
		foreach ( $response['snapshot']['malware']['files'] as $file => $infection ) {
			$infected .= $file . ' <span class="description">(' . $infection . ')</span><br>';
		}
		$stats = array(
			array( 'Scanned Directories', $response['snapshot']['malware']['stats']['scanned_directories'] ),
			array( 'Scanned Files', $response['snapshot']['malware']['stats']['scanned_files'] ),
			array( 'Number of Infected Files', '<font color=' . $infected_color . '>' . $response['snapshot']['malware']['stats']['infected_files'] . '</font>' ),
			array( 'Infected Files', $infected ),
		);
		pb_backupbuddy::$ui->list_table(
			$stats,
			array(
				'columns' => array(
					__( 'Property', 'it-l10n-backupbuddy' ),
					__( 'Details', 'it-l10n-backupbuddy' ),
				),
				'css'     => 'width: 100%; min-width: 200px;',
			)
		);
	}

	if ( isset( $response['snapshot'] ) && isset( $response['snapshot']['wordpress'] ) && isset( $response['snapshot']['wordpress']['plugins'] ) ) {
		echo '<br><br><h3>' . esc_html__( 'Plugins', 'it-l10n-backupbuddy' ) . '</h3>';
		$plugins = array();
		foreach ( $response['snapshot']['wordpress']['plugins'] as $plugin_slug => $details ) {
			$plugins[] = array( $plugin_slug, $details['version'] );
		}
		pb_backupbuddy::$ui->list_table(
			$plugins,
			array(
				'columns' => array(
					__( 'Plugin File', 'it-l10n-backupbuddy' ),
					__( 'Version', 'it-l10n-backupbuddy' ),
				),
				'css'     => 'width: 100%; min-width: 200px;',
			)
		);
	}

	if ( isset( $response['snapshot'] ) && isset( $response['snapshot']['wordpress'] ) && isset( $response['snapshot']['wordpress']['themes'] ) ) {
		echo '<br><br><h3>' . esc_html__( 'Themes', 'it-l10n-backupbuddy' ) . '</h3>';
		$themes = array();
		foreach ( $response['snapshot']['wordpress']['themes'] as $theme_slug => $details ) {
			$themes[] = array( $theme_slug, $details['version'] );
		}
		pb_backupbuddy::$ui->list_table(
			$themes,
			array(
				'columns' => array(
					__( 'Theme File', 'it-l10n-backupbuddy' ),
					__( 'Version', 'it-l10n-backupbuddy' ),
				),
				'css'     => 'width: 100%; min-width: 200px;',
			)
		);
	}

	if ( isset( $response['snapshot'] ) ) {
		echo '<br><br><h3>' . esc_html__( 'Stash Live Account', 'it-l10n-backupbuddy' ) . '</h3>';
		$stats = array(
			array( 'Serial', $response['snapshot']['username'] ),
			array( 'Username', $response['snapshot']['username'] ),
			array( 'Notification email', $response['snapshot']['notify_email'] ),
			array( 'Subkey', $response['snapshot']['subkey'] ),
		);
		pb_backupbuddy::$ui->list_table(
			$stats,
			array(
				'columns' => array(
					__( 'Property', 'it-l10n-backupbuddy' ),
					__( 'Details', 'it-l10n-backupbuddy' ),
				),
				'css'     => 'width: 100%; min-width: 200px;',
			)
		);
	}

	echo '<br><br><h3>';
	esc_html_e( 'Advanced Data (for support)', 'it-l10n-backupbuddy' );
	echo '</h3>';
	echo '<textarea readonly="readonly" style="width: 100%;" wrap="off" cols="65" rows="5">' . print_r( $response, true ) . '</textarea>';
	echo '<br><br>';
}

pb_backupbuddy::$ui->ajax_footer();
die();
