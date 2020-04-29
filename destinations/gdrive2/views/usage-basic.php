<?php
/**
 * Google Drive (v2) Quota
 *
 * Incoming vars:
 *   object $info      Google Drive About object.
 *
 * @package BackupBuddy
 */

?>
<p class="gdrive-usage">
	Used <?php echo esc_html( pb_backupbuddy::$format->file_size( $info->storageQuota->usage ) ); ?> of <?php echo esc_html( pb_backupbuddy::$format->file_size( $info->storageQuota->limit ) ); ?> available space in <?php echo esc_html( $info->user->displayName ); ?>'s Google Drive.
</p>
