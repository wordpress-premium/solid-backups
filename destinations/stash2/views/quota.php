<?php
/**
 * Stash v2 Quota
 *
 * Incoming Vars:
 *     $account_info
 *
 * @package BackupBuddy
 */

?>
<style>
	.outer_progress {
		-moz-border-radius: 4px;
		-webkit-border-radius: 4px;
		-khtml-border-radius: 4px;
		border-radius: 4px;

		border: 1px solid #DDD;
		background: #EEE;

		max-width: 700px;

		margin-left: auto;
		margin-right: auto;

		height: 30px;
	}

	.inner_progress {
		border-right: 1px solid #85bb3c;
		background: #8cc63f url( '<?php echo esc_attr( pb_backupbuddy::plugin_url() ); ?>/destinations/stash2/progress.png' ) 50% 50% repeat-x;
		height: 100%;
	}

	.progress_table {
		color: #5E7078;
		font-family: "Open Sans", Arial, Helvetica, Sans-Serif;
		font-size: 14px;
		line-height: 20px;
		text-align: center;

		margin-left: auto;
		margin-right: auto;
		margin-bottom: 20px;
		max-width: 700px;
	}
</style>
<div class="backupbuddy-stash2-quotawrap">
	<?php /*if ( ! empty( $account_info['quota_warning'] ) ) : ?>
		<div style="color: red; max-width: 700px; margin-left: auto; margin-right: auto;">
			<b>Warning</b>: <?php echo esc_html( $account_info['quota_warning'] ); ?>
		</div><br>
	<?php endif;*/ ?>

	<div class="outer_progress">
		<div class="inner_progress" style="width: <?php echo esc_attr( $account_info['quota_used_percent'] ); ?>%"></div>
	</div>

	<table align="center" class="progress_table">
		<tbody>
			<tr align="center">
				<td style="width: 10%; font-weight: bold; text-align: center">Free Tier</td>
				<td style="width: 10%; font-weight: bold; text-align: center">Paid Tier</td>
				<td style="width: 10%"></td>
				<td style="width: 10%; font-weight: bold; text-align: center">Total</td>
				<td style="width: 10%; font-weight: bold; text-align: center">Used</td>
				<td style="width: 10%; font-weight: bold; text-align: center">Available</td>
			</tr>

			<tr align="center">
				<td style="text-align: center"><?php echo esc_html( $account_info['quota_free_nice'] ); ?></td>
				<td style="text-align: center"><?php
				if ( '0' == $account_info['quota_paid'] ) :
					echo 'none';
				else :
					echo esc_html( $account_info['quota_paid_nice'] );
				endif;
				?></td>
				<td></td>
				<td style="text-align: center"><?php echo esc_html( $account_info['quota_total_nice'] ); ?></td>
				<td style="text-align: center"><?php echo esc_html( $account_info['quota_used_nice'] ); ?> (<?php echo esc_html( $account_info['quota_used_percent'] ); ?>%)</td>
				<td style="text-align: center"><?php echo esc_html( $account_info['quota_available_nice'] ); ?></td>
			</tr>
		</tbody>
	</table>

	<div style="text-align: center;">
		<b><?php esc_html_e( 'Upgrade storage', 'it-l10n-backupbuddy' ); ?>:</b> &nbsp;
		<a href="https://go.solidwp.com/solid-backups-stash" target="_blank" style="text-decoration: none;">+ 5GB</a>, &nbsp;
		<a href="https://go.solidwp.com/solid-backups-stash" target="_blank" style="text-decoration: none;">+ 10GB</a>, &nbsp;
		<a href="https://go.solidwp.com/solid-backups-stash" target="_blank" style="text-decoration: none;">+ 25GB</a>

		&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;<a href="https://go.solidwp.com/solid-stash-central-account-login" target="_blank" style="text-decoration: none;"><b>Manage Stash & Stash Live Files</b></a>&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;<a href="https://go.solidwp.com/solid-backups-stash" target="_blank" style="text-decoration: none;"><b>Manage Account</b></a>
		<br><br>
	</div>
</div>
