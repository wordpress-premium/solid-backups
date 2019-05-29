<?php
/**
 * Obtain MD5 hash of a backup file.
 * Generate a hash/CRC for a file at user request.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::load();

pb_backupbuddy::$ui->ajax_header();
?>
<h1><?php esc_html_e( 'MD5 Checksum Hash', 'it-l10n-backupbuddy' ); ?></h1>
<?php
esc_html_e( 'This is a string of characters that uniquely represents this file. If this file is in any way manipulated then this string of characters will change. This allows you to later verify that the file is intact and uncorrupted. For instance you may verify the file after uploading it to a new location by making sure the MD5 checksum matches.', 'it-l10n-backupbuddy' );

$hash = md5_file( backupbuddy_core::getBackupDirectory() . pb_backupbuddy::_GET( 'callback_data' ) );

echo '<br><br><br>';
echo '<strong>Hash:</strong> &nbsp;&nbsp;&nbsp; <input type="text" size="40" value="' . esc_attr( $hash ) . '" readonly="readonly">';

pb_backupbuddy::$ui->ajax_footer();
die();
