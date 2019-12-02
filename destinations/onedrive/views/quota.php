<?php
/**
 * OneDrive Quote partial.
 *
 * Incoming Vars:
 *     $quota
 *
 * @package BackupBuddy
 */

$total_space    = $quota->quota;
$avail_space    = $quota->available;
$used_space     = $total_space - $avail_space;
$pct_used       = round( ( $used_space / $total_space ) * 100, 2 );
$disk_available = pb_backupbuddy::$format->file_size( $avail_space );
?>

<style type="text/css">
	.onedrive-quota-wrapper {
		text-align: center;
	}
	.onedrive-quota-wrapper::after {
		clear: both;
		content: '';
		display: table;
	}
	.percentage-bar {
		max-width: 700px;
		margin: 0 auto;
		background-color: #8cc63f;
		height: 30px;
		border-radius: 3px;
		overflow: hidden;
	}
	.percentage-bar .used {
		background-color: #28A8EA;
		height: 100%;
		float: left;
		position: relative;
	}
	.percentage-bar .used::after {
		content: '';
		position: absolute;
		width: 2px;
		background-color: rgba( 0, 0, 0, 0.2 );
		height: 100%;
		top: 0;
		right: -2px;
	}
	.disk-used {
		float: left;
		margin-left: 10px;
		line-height: 28px;
		color: #fff;
	}
	.used .disk-used {
		width: 100%;
		text-align: center;
		margin-left: 0;
		float: none;
	}
	.total-space {
		float: right;
		margin-right: 10px;
		line-height: 30px;
		color: #fff;
	}
</style>
<div class="onedrive-quota-wrapper">
	<div class="percentage-bar" title="Total Available: <?php echo esc_attr( $disk_available ); ?>?>">
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
