<?php
/**
 * Generate .dat file and redirect to new restore tool.
 *
 * Incoming var:
 *     $file - from _zip_viewer.php
 *
 * @package BackupBuddy
 */

?>
<div class="backupbuddy-generate-dat" data-zip="<?php echo esc_attr( $file ); ?>">
	<div class="inner">
		<div class="glow"></div>
		<h2><?php esc_html_e( 'Working', 'it-l10n-backupbuddy' ); ?>...</h2>
		<p><?php esc_html_e( 'Preparing backup for file restore', 'it-l10n-backupbuddy' ); ?>...</p>
	</div>
</div>
