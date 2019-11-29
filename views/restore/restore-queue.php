<?php
/**
 * Restore Queue Table
 *
 * @package BackupBuddy
 */

$restore_queue = backupbuddy_restore()->get_queue();
$striped       = false;
?>
<div id="backupbuddy-restore-history">
	<h3>Recent Restores</h3>

	<?php if ( ! count( $restore_queue ) ) : ?>
		<p>No recent restores to display.</p>
	<?php else : ?>
		<table class="widefat backupbuddy-restore-queue">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Backup Date', 'it-l10n-backupbuddy' ); ?></th>
					<th><?php esc_html_e( 'Restored Date', 'it-l10n-backupbuddy' ); ?></th>
					<th><?php esc_html_e( 'Data Restored', 'it-l10n-backupbuddy' ); ?></th>
					<th><?php esc_html_e( 'Status', 'it-l10n-backupbuddy' ); ?></th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $restore_queue as $restore_json ) :
					$restore = json_decode( $restore_json, true );
					$striped = $striped ? '' : ' striped';
					if ( $restore['completed'] ) {
						$restore_date = pb_backupbuddy::$format->date( $restore['completed'], 'l, F j, Y g:ia' );
					} else {
						$restore_date = sprintf( 'N/A (Init: %s)', pb_backupbuddy::$format->date( $restore['initialized'], 'l, F j, Y g:ia' ) );
					}
					?>
					<tr class="<?php echo esc_attr( trim( $striped ) ); ?>" data-restore-id="<?php echo esc_attr( $restore['id'] ); ?>">
						<td><?php echo esc_html( backupbuddy_core::parse_file( $restore['backup_file'], 'nicename' ) ); ?></td>
						<td><?php echo esc_html( $restore_date ); ?></td>
						<td><?php echo esc_html( backupbuddy_restore()->get_summary( $restore ) ); ?></td>
						<?php if ( in_array( $restore['status'], backupbuddy_restore()->get_completed_statuses(), true ) ) : ?>
							<td><?php backupbuddy_restore()->get_status_text( $restore['status'], true ); ?></td>
							<td><?php backupbuddy_restore()->get_status_html( $restore, __( 'Details', 'it-l10n-backupbuddy' ), true ); ?></td>
						<?php else : ?>
							<td><?php backupbuddy_restore()->get_status_html( $restore, false, true ); ?></td>
							<td>
								<span class="spinner" style="visibility:visible;margin:0;"></span>
								<a href="#abort-restore" data-restore-id="<?php echo esc_attr( $restore['id'] ); ?>"><?php esc_html_e( 'Abort', 'it-l10n-backupbuddy' ); ?></a>
								<input type="hidden" name="abort-<?php echo esc_attr( $restore['id'] ); ?>" value="0">
							</td>
						<?php endif; ?>
					</tr>
					<tr id="restore-details-<?php echo esc_attr( $restore['id'] ); ?>" class="restore-details<?php echo esc_attr( $striped ); ?>">
						<td colspan="5"><?php include pb_backupbuddy::plugin_path() . '/views/restore/restore-detail.php'; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
