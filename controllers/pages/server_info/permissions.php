<?php
/**
 * Permissions Server Info
 *
 * @package BackupBuddy
 */

echo '<br>';

$tests = array();

$uploads_dirs = wp_upload_dir();
$directories  = array(
	ABSPATH . '',
	ABSPATH . 'wp-includes/',
	ABSPATH . 'wp-admin/',
	WP_CONTENT_DIR . '/themes/',
	WP_PLUGIN_DIR . '/',
	WP_CONTENT_DIR . '/',
	rtrim( $uploads_dirs['basedir'], '\\/' ) . '/',
	ABSPATH . 'wp-includes/',
	backupbuddy_core::getBackupDirectory(),
	backupbuddy_core::getLogDirectory(),
);
if ( @file_exists( backupbuddy_core::getTempDirectory() ) ) { // This dir is usually transient so may not exist.
	$directories[] = backupbuddy_core::getTempDirectory();
}

foreach ( $directories as $directory ) {

	$mode_octal_four = '<i>' . __( 'Unknown', 'it-l10n-backupbuddy' ) . '</i>';
	$owner           = '<i>' . __( 'Unknown', 'it-l10n-backupbuddy' ) . '</i>';

	if ( ! file_exists( $directory ) ) {
		$mode_octal_four = 'Directory does\'t exist';
		$owner           = 'n/a';
	}
	$stats = pluginbuddy_stat::stat( $directory );
	if ( false !== $stats ) {
		$mode_octal_four = $stats['mode_octal_four'];
		$owner           = $stats['uid'] . ':' . $stats['gid'];
	}
	$this_test = array(
		'title'      => '/' . str_replace( ABSPATH, '', $directory ),
		'suggestion' => '<= 755',
		'value'      => $mode_octal_four,
		'owner'      => $owner,
	);
	if ( false === $stats || $mode_octal_four > 755 ) {
		$this_test['status'] = __( 'WARNING', 'it-l10n-backupbuddy' );
	} else {
		$this_test['status'] = __( 'OK', 'it-l10n-backupbuddy' );
	}
	array_push( $tests, $this_test );

} // end foreach.

?>

<table class="widefat">
	<thead>
		<tr class="thead">
			<th><?php esc_html_e( 'Relative Path', 'it-l10n-backupbuddy' ); ?></th>
			<th><?php esc_html_e( 'Suggestion', 'it-l10n-backupbuddy' ); ?></th>
			<th><?php esc_html_e( 'Value', 'it-l10n-backupbuddy' ); ?></th>
			<th><?php esc_html_e( 'Owner (UID:GID)', 'it-l10n-backupbuddy' ); ?></th>
			<th style="width: 60px;"><?php esc_html_e( 'Status', 'it-l10n-backupbuddy' ); ?></th>
		</tr>
	</thead>
	<tfoot>
		<tr class="thead">
			<th><?php esc_html_e( 'Relative Path', 'it-l10n-backupbuddy' ); ?></th>
			<th><?php esc_html_e( 'Suggestion', 'it-l10n-backupbuddy' ); ?></th>
			<th><?php esc_html_e( 'Value', 'it-l10n-backupbuddy' ); ?></th>
			<th><?php esc_html_e( 'Owner (UID:GID)', 'it-l10n-backupbuddy' ); ?></th>
			<th style="width: 60px;"><?php esc_html_e( 'Status', 'it-l10n-backupbuddy' ); ?></th>
		</tr>
	</tfoot>
	<tbody>
		<?php foreach ( $tests as $this_test ) { ?>
			<tr class="entry-row alternate">
				<td><?php echo esc_html( $this_test['title'] ); ?></td>
				<td><?php echo esc_html( $this_test['suggestion'] ); ?></td>
				<td><?php echo esc_html( $this_test['value'] ); ?></td>
				<td><?php echo esc_html( $this_test['owner'] ); ?></td>
				<td>
					<?php if ( 'OK' == $this_test['status'] ) { ?>
						<span class="pb_label pb_label-success"><?php esc_html_e( 'Pass', 'it-l10n-backupbuddy' ); ?></span>
					<?php } elseif ( 'FAIL' == $this_test['status'] ) { ?>
						<span class="pb_label pb_label-important"><?php esc_html_e( 'Fail', 'it-l10n-backupbuddy' ); ?></span>
					<?php } elseif ( 'WARNING' == $this_test['status'] ) { ?>
						<span class="pb_label pb_label-warning"><?php esc_html_e( 'Warning', 'it-l10n-backupbuddy' ); ?></span>
					<?php } else { ?>
						unknown
					<?php } ?>
				</td>
			</tr>
		<?php } ?>
	</tbody>
</table>

<br><br>
