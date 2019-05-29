<?php
/**
 * Repair db integrity of a table.
 *
 * Repair specific table. Used on server info page.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$table = base64_decode( pb_backupbuddy::_GET( 'table' ) );

global $wpdb;

pb_backupbuddy::$ui->ajax_header();
printf( '<h2>%s</h2>', esc_html__( 'Database Table Repair', 'it-l10n-backupbuddy' ) );

echo esc_html__( 'Repairing table', 'it-l10n-backupbuddy' ) . ' `' . esc_html( $table ) . '`...<br><br>';

$table = backupbuddy_core::dbEscape( $table );
$rows  = $wpdb->get_results( "REPAIR TABLE `$table`", ARRAY_A );

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

pb_backupbuddy::$ui->ajax_footer();

die();

