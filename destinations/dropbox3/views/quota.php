<?php
/**
 * Dropbox (v3) Quote view.
 *
 * Incoming Vars:
 *     array $quota  Array of space usage.
 *
 * @package BackupBuddy
 */

$total_space    = $quota['allocation']['allocated'];
$used_space     = $quota['used'];
$avail_space    = $total_space - $used_space;
$pct_used       = round( ( $used_space / $total_space ) * 100, 2 );
$disk_available = pb_backupbuddy::$format->file_size( $avail_space );
?>
<div class="dropbox-quota-wrapper quota-wrap">
	<div class="percentage-bar" title="Total Available: <?php echo esc_attr( $disk_available ); ?>">
		<div class="used" style="width: <?php echo esc_attr( $pct_used ); ?>%;">
			<?php if ( $pct_used >= 20 ) : ?>
				<div class="disk-used"><?php echo esc_html( pb_backupbuddy::$format->file_size( $used_space ) ); ?></div>
			<?php endif; ?>
		</div>
		<?php if ( $pct_used < 20 ) : ?>
			<div class="disk-used">Used: <?php echo esc_html( pb_backupbuddy::$format->file_size( $used_space ) ); ?></div>
		<?php endif; ?>
		<div class="total-space">Total: <?php echo esc_html( pb_backupbuddy::$format->file_size( $total_space ) ); ?></div>
	</div>
	<!--
	<p>Used: <?php echo esc_html( pb_backupbuddy::$format->file_size( $used_space ) ); ?></p>
	<p>Available: <?php echo esc_html( pb_backupbuddy::$format->file_size( $avail_space ) ); ?></p>
	<p>Total: <?php echo esc_html( pb_backupbuddy::$format->file_size( $total_space ) ); ?></p>
	-->
</div>
