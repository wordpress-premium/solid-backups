<?php
/**
 * Database Check AJAX Controller
 *
 * Check database integrity on a specific table. Used on server info page.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$table       = base64_decode( pb_backupbuddy::_GET( 'table' ) );
$check_level = 'MEDIUM';

global $wpdb;

pb_backupbuddy::$ui->ajax_header( true, true, 'backupbuddy-admin-iframe-white diagnostics-iframe');

?>
<div class="diagnostics-iframe-inner">
<?php

printf( '<h2>%s</h2>', esc_html__( 'Database Table Check', 'it-l10n-backupbuddy' ) );

echo esc_html__( 'Checking table', 'it-l10n-backupbuddy' ) . ' `' . esc_html( $table ) . '` ' . esc_html__( 'using', 'it-l10n-backupbuddy' ) . ' ' . esc_html( $check_level ) . ' ' . esc_html__( 'scan', 'it-l10n-backupbuddy' ) . '...<br><br>';

$table = backupbuddy_core::dbEscape( $table );
$rows  = $wpdb->get_results( "CHECK TABLE `$table` $check_level", ARRAY_A );

printf( '<strong>%s:</strong><br><br>', esc_html__( 'Results', 'it-l10n-backupbuddy' ) );

echo '<table class="widefat">';
foreach ( $rows as $row ) {
	echo '<tr>';
	printf( '<td>%s</td>', esc_html( $row['Msg_type'] ) );
	printf( '<td>%s</td>', esc_html( $row['Msg_text'] ) );
	echo '</tr>';
}
	unset( $rows );
echo '</table>';

?>
</div>
<?php
pb_backupbuddy::$ui->ajax_footer();

die();
