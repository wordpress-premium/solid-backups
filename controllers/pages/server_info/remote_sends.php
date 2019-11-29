<?php
/**
 * Remote Sends Server Info Page
 *
 * @package BackupBuddy
 */

require_once pb_backupbuddy::plugin_path() . '/classes/housekeeping.php';
backupbuddy_housekeeping::trim_remote_send_stats();

require '_remote_sends.php'; // Sets $sends.
?>
<div class="backupbuddy-remote-sends">
	<h3>Recent Remote Sends/File Transfers</h3>
	<?php if ( count( $sends ) === 0 ) : ?>
		<div class="no-recent-transfers"><?php esc_html_e( 'There have been no recent file transfers.', 'it-l10n-backupbuddy' ); ?></div>
	<?php else : ?>
		<?php
		$sends = array_slice( $sends, 0, backupbuddy_constants::RECENT_SENDS_MAX_LISTING_COUNT ); // Only display most recent X sends to keep page from being bogged down.
		pb_backupbuddy::$ui->list_table(
			$sends,
			array(
				'action'  => pb_backupbuddy::page_url(),
				'columns' => array(
					__( 'Sent File', 'it-l10n-backupbuddy' ),
					__( 'Destination', 'it-l10n-backupbuddy' ),
					__( 'Trigger', 'it-l10n-backupbuddy' ),
					__( 'Transfer Information', 'it-l10n-backupbuddy' ) . ' <img src="' . pb_backupbuddy::plugin_url() . '/images/sort_down.png" style="vertical-align: 0px;" title="Sorted most recent started first">',
					__( 'Status', 'it-l10n-backupbuddy' ) . ' <span class="description">(hover for options)</span>',
				),
				'css'     => 'width: 100%;',
			)
		);
		?>
		<div class="alignright actions">
			<?php pb_backupbuddy::$ui->note( 'Hover over items above for additional options.' ); ?>
		</div>
	<?php endif; ?>
</div>
