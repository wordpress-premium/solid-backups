<?php
/**
 * Restore Queue Table
 *
 * @package BackupBuddy
 */

$restore_queue = backupbuddy_restore()->get_queue();
$striped       = false;
$displayed     = array();
?>
<div id="backupbuddy-restore-history">
	<h3><?php esc_html_e( 'Recent Restores', 'it-l10n-backupbuddy' ); ?></h3>

	<?php if ( ! count( $restore_queue ) ) : ?>
		<p><?php esc_html_e( 'No recent restores to display.', 'it-l10n-backupbuddy' ); ?></p>
	<?php else : ?>
		<table class="widefat striped backupbuddy-restore-queue">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Restored Date', 'it-l10n-backupbuddy' ); ?></th>
					<th><?php esc_html_e( 'Backup Date', 'it-l10n-backupbuddy' ); ?></th>
					<th><?php esc_html_e( 'Data Restored', 'it-l10n-backupbuddy' ); ?></th>
					<th><?php esc_html_e( 'Status', 'it-l10n-backupbuddy' ); ?></th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $restore_queue as $restore_json ) :
					$striped = $striped ? '' : ' striped';
					$json    = json_decode( $restore_json, true );
					if ( $json ) :
						$restore     = backupbuddy_restore()->load_restore( $json );
						$displayed[] = $restore['id'];
						if ( $restore['completed'] ) {
							$restore_date = pb_backupbuddy::$format->date( $restore['completed'], 'l, F j, Y g:ia' );
						} else {
							$restore_date = sprintf(
								__( 'N/A (Init: %s)', 'it-l10n-backupbuddy' ),
								pb_backupbuddy::$format->date( $restore['initialized'], 'l, F j, Y g:ia' )
							);
						}
						?>
						<tr class="<?php echo esc_attr( trim( $striped ) ); ?>" data-restore-id="<?php echo esc_attr( $restore['id'] ); ?>">
							<td><?php echo esc_html( $restore_date ); ?></td>
							<td><?php echo esc_html( backupbuddy_core::parse_file( $restore['backup_file'], 'nicename' ) ); ?></td>
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
					<?php else : ?>
						<?php $temp_id = uniqid(); ?>
						<tr class="<?php echo esc_attr( trim( $striped ) ); ?>">
							<td colspan="3"><?php esc_html_e( 'A corrupt restore log was found.', 'it-l10n-backupbuddy' ); ?></td>
							<td><?php esc_html_e( 'Corrupt', 'it-l10n-backupbuddy' ); ?></td>
							<td><a href="#restore-details-<?php echo esc_attr( $temp_id ); ?>"><?php esc_html_e( 'Details', 'it-l10n-backupbuddy' ); ?></a></td>
						</tr>
						<tr id="restore-details-<?php echo esc_attr( $temp_id ); ?>" class="restore-details<?php echo esc_attr( $striped ); ?>">
							<td colspan="5"><strong><?php
								esc_html_e( 'Could not parse log file. JSON Error: ', 'it-l10n-backupbuddy' );
								echo function_exists( 'json_last_error_msg' ) ? esc_html( json_last_error_msg() ) : esc_html( json_last_error() );
							?></strong></td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php $restore_archive = backupbuddy_restore()->get_archive( $displayed ); ?>

	<?php if ( $restore_archive ) : ?>
		<h4>
		<?php echo wp_kses_post(
				sprintf(
					__( 'Restore Archive <a href="%s" class="toggle-restore-archive">Show</a>', 'it-l10n-backupbuddy' ),
					'#restore-archive'
				)
			); ?>
		</h4>

		<table class="widefat striped backupbuddy-restore-archive hidden">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Restored Date', 'it-l10n-backupbuddy' ); ?></th>
					<th><?php esc_html_e( 'Backup Date', 'it-l10n-backupbuddy' ); ?></th>
					<th><?php esc_html_e( 'Data Restored', 'it-l10n-backupbuddy' ); ?></th>
					<th><?php esc_html_e( 'Status', 'it-l10n-backupbuddy' ); ?></th>
					<th>&nbsp;</th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>
				<?php
				$striped = false;
				foreach ( $restore_archive as $restore ) :
					$striped = $striped ? '' : ' striped';
					if ( is_array( $restore ) ) :
						if ( $restore['completed'] ) {
							$restore_date = pb_backupbuddy::$format->date( $restore['completed'], 'l, F j, Y g:ia' );
						} else {
							$restore_date = sprintf(
								__( 'N/A (Init: %s)', 'it-l10n-backupbuddy' ),
								pb_backupbuddy::$format->date( $restore['initialized'], 'l, F j, Y g:ia' )
							);
						}
						?>
						<tr class="<?php echo esc_attr( trim( $striped ) ); ?>" data-restore-id="<?php echo esc_attr( $restore['id'] ); ?>">
							<td><?php echo esc_html( $restore_date ); ?></td>
							<td><?php echo esc_html( backupbuddy_core::parse_file( $restore['backup_file'], 'nicename' ) ); ?></td>
							<td><?php echo esc_html( backupbuddy_restore()->get_summary( $restore ) ); ?></td>
							<td><?php backupbuddy_restore()->get_status_text( $restore['status'], true ); ?></td>
							<td><?php backupbuddy_restore()->get_status_html( $restore, __( 'Details', 'it-l10n-backupbuddy' ), true, true ); ?></td>
							<td><?php backupbuddy_restore()->get_delete_link( $restore, true ); ?></td>
						</tr>
						<tr id="restore-details-<?php echo esc_attr( $restore['id'] ); ?>" class="restore-details<?php echo esc_attr( $striped ); ?>">
							<td colspan="6"><?php include pb_backupbuddy::plugin_path() . '/views/restore/restore-detail.php'; ?></td>
						</tr>
					<?php else : ?>
						<?php $temp_id = uniqid(); ?>
						<tr class="<?php echo esc_attr( trim( $striped ) ); ?>">
							<td colspan="3"><?php esc_html_e( 'A corrupt restore log was found at: ', 'it-l10n-backupbuddy' ); ?> <?php echo esc_html( basename( $restore ) ); ?></td>
							<td><?php esc_html_e( 'Corrupt', 'it-l10n-backupbuddy' ); ?></td>
							<td><a href="#restore-details-<?php echo esc_attr( $temp_id ); ?>"><?php esc_html_e( 'Details', 'it-l10n-backupbuddy' ); ?></a></td>
							<td><?php backupbuddy_restore()->get_delete_link( $restore, true ); ?></td>
						</tr>
						<tr id="restore-details-<?php echo esc_attr( $temp_id ); ?>" class="restore-details<?php echo esc_attr( $striped ); ?>">
							<td colspan="6"><strong><?php
								$contents = file_get_contents( $restore );
								$json     = json_decode( $contents, true );
								esc_html_e( 'Could not parse log file. JSON Error: ', 'it-l10n-backupbuddy' );
								echo function_exists( 'json_last_error_msg' ) ? esc_html( json_last_error_msg() ) : esc_html( json_last_error() );
							?></strong></td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
