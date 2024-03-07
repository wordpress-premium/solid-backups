<?php
/**
 * Google Drive (v1) Destination Configuration
 *
 * This Destination is now deprecated and is
 * excluded from the "Add New" Destination UI.
 *
 * However, this configuration (settings) file needs
 * to exist so users can delete their v1 destination(s)
 * and create a new v2 destination.
 *
 * The Google Drive v1 and v2 SDKs are so incompatible
 * that we cannot migrate v1 to v2, nor can the
 * settings that were once stored on this page
 * be accessed without PHP Errors due to naming
 * conflicts.
 *
 * Incoming vars:
 *     $destination_settings
 *     $mode
 *     $destination_id
 *
 * @package BackupBuddy
 */

?>

<p style="margin-bottom: 1em"><?php esc_html_e( 'You are attempting to access a destination type (Google Drive v1) that is no longer supported.', 'it-l10n-backupbuddy' ); ?></p>

<?php
if ( ! isset( $destination_id ) || ! is_numeric( $destination_id ) ) {
	// If we're not Editing an existing destination, bail here.
	return;
}

// Show the delete button so they can delete this and get access to v2.

// Determine the redirect URL.
$picker_url = pb_backupbuddy::ajax_url( 'destinationTabs' );
if ( pb_backupbuddy::_GET( 'tab' ) ) {
	$picker_url .= '&tab=' . rawurlencode( pb_backupbuddy::_GET( 'tab' ) );
}

?>

<p style="margin-bottom: 1em;"><?php esc_html_e( 'You will need to delete this destination before you can create a new Google Drive destination.', 'it-l10n-backupbuddy' ); ?></p>

<form method="post">
	<?php pb_backupbuddy::nonce(); ?>
	<a id="sb-delete-destination" href="#" class="button button-primary" href="javascript:void(0)" title="<?php echo esc_attr( __( 'Delete this Destination', 'it-l10n-backupbuddy' ) ); ?>"><?php esc_html_e( 'Delete Destination', 'it-l10n-backupbuddy' ); ?></a>
	<script type="text/javascript">
		( function( $ ) {

			$( '#sb-delete-destination' ).off( 'click' ).on( 'click', function(e) {
				e.preventDefault();
				$.post( '<?php echo pb_backupbuddy::ajax_url( 'remote_delete' ); ?>&pb_backupbuddy_destinationid=<?php echo esc_js( (string) $destination_id ); ?>', $(this).closest( 'form' ).serialize(),
					function( data ) {
						data = $.trim( data );

						if ( data == 'Destination deleted.' ) {
							// Display the "Destination deleted" admin message.
							window.location.href = '<?php echo $picker_url; ?>&callback_data=<?php esc_attr_e( pb_backupbuddy::_GET( 'callback_data' ) ); ?>&sending=<?php esc_attr_e( pb_backupbuddy::_GET( 'sending' ) ); ?>&alert_notice=' + encodeURIComponent( 'Destination deleted.' );
						} else { // Show message if not success.
							alert( 'Error #82724. Details: `' + data + '`.' );
						}

					}
				);

				return false;
			} );
		}( jQuery ));
	</script>
</form>

<?php
return;
