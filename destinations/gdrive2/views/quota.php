<?php
/**
 * Google Drive (v2) Quote view.
 *
 * Incoming vars:
 *   object $info      Google Drive About object.
 *   array  $settings  Destination Settings.
 *
 * @package BackupBuddy
 */

$total_space    = $info->storageQuota->limit ?? 0;
$used_space     = $info->storageQuota->usage ?? 0;
$avail_space    = $total_space - $used_space;
$disk_available = pb_backupbuddy::$format->file_size( $avail_space );
$pct_used       = 0;
if ( $total_space > 0 ) {
	$pct_used = round( ( $used_space / $total_space ) * 100, 2 );
}

$used_html  = esc_html( pb_backupbuddy::$format->file_size( $used_space ) );
$used_html .= sprintf( ' <span class="pct-used">(%s%%)</span>', esc_html( floor( $pct_used ) ) );
?>
<div class="gdrive-quota-wrapper quota-wrap">
	<div class="quota_outer_progress">
		<div class="quota_inner_progress" style="width: <?php echo esc_attr( floor( $pct_used ) ); ?>%">
			<?php if ( $pct_used >= 20 ) : ?>
				<div class="disk-used"><?php echo $used_html; ?></div>
			<?php endif; ?>
		</div>
		<?php if ( $pct_used < 20 ) : ?>
			<div class="disk-used">Used: <?php echo wp_kses_post( $used_html ); ?></div>
		<?php endif; ?>
		<div class="quota-total-space">Total: <?php echo esc_html( pb_backupbuddy::$format->file_size( $total_space ) ); ?></div>
	</div>
</div>
