<?php
/**
 * Remote Sends Server Info Page
 *
 * @package BackupBuddy
 */

?>
<script type="text/javascript">
	jQuery(function() {
		jQuery( '.pb_backupbuddy_remotesend_abort' ).on( 'click', function(){
			jQuery.ajax({
				type: 'POST',
				url: jQuery(this).attr( 'href' ),
				success: function(data){
					data = jQuery.trim( data );
					if ( '1' == data ) {
						alert( 'Remote transfer aborted. This may take a moment to take effect.' );
					} else {
						alert( 'Error #85448949. Unexpected server response. Details: `' + data + '`.' );
					}
				}
			});
			return false;
		});
	});
</script>
<?php

require_once pb_backupbuddy::plugin_path() . '/classes/housekeeping.php';
backupbuddy_housekeeping::trim_remote_send_stats();

require '_remote_sends.php'; // Sets $sends.

if ( count( $sends ) === 0 ) {
	echo '<br><span class="description">' . esc_html__( 'There have been no recent file transfers.', 'it-l10n-backupbuddy' ) . '</span><br>';
} else {
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
	echo '<div class="alignright actions">';
	pb_backupbuddy::$ui->note( 'Hover over items above for additional options.' );
	echo '</div>';
}

echo '<br>';
