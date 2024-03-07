<?php
/**
 * Destinations page
 *
 * @package BackupBuddy
 */

wp_enqueue_script( 'thickbox' );
wp_print_scripts( 'thickbox' );
wp_print_styles( 'thickbox' );

wp_enqueue_script( 'jquery-ui-core' );
wp_print_styles( 'jquery-ui-core' );

wp_enqueue_script( 'jquery-ui-progressbar' );
wp_print_styles( 'jquery-ui-progressbar' );

$deployment_enabled_status = __( 'Disabled', 'it-l10n-backupbuddy' );
if ( defined( 'BACKUPBUDDY_API_ENABLE' ) && true == BACKUPBUDDY_API_ENABLE ) {
	$deployment_enabled_status = __( 'Enabled', 'it-l10n-backupbuddy' );
}
?>

<?php pb_backupbuddy::$ui->title( esc_html__( 'Destinations', 'it-l10n-backupbuddy' ), true, false ); ?>

<script type="text/javascript">
	function pb_backupbuddy_selectdestination( destination_id, destination_title, callback_data, delete_after, mode ) {
		if ( callback_data != '' ) {
			jQuery.post( '<?php echo pb_backupbuddy::ajax_url( 'remote_send' ); ?>', { destination_id: destination_id, destination_title: destination_title, file: callback_data, trigger: 'manual' },
				function(data) {
					data = jQuery.trim( data );
					if ( data.charAt(0) != '1' ) {
						alert( "<?php esc_html_e( 'Error starting remote send', 'it-l10n-backupbuddy' ); ?>:" + "\n\n" + data );
					} else {
						alert( "<?php esc_html_e( 'Your file has been scheduled to be sent now. It should arrive shortly.', 'it-l10n-backupbuddy' ); ?> <?php esc_html_e( 'You will be notified by email if any problems are encountered.', 'it-l10n-backupbuddy' ); ?>" + "\n\n" + data.slice(1) );
					}
				}
			);

			/* Try to ping server to nudge cron along since sometimes it doesnt trigger as expected. */
			jQuery.post( '<?php echo admin_url( 'admin-ajax.php' ); ?>' );

		} else {
			<?php $admin_url = is_network_admin() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' ); ?>
			window.location.href = '<?php echo esc_url( $admin_url ); ?>?page=pb_backupbuddy_backup&custom=remoteclient&destination_id=' + parseInt( destination_id );
		}
	}

	jQuery(function( $ ) {

		$('#screen-meta-links').append(
			'<div class="backupbuddy-meta-link-wrap hide-if-no-js screen-meta-toggle">' +
				'<a href="javascript:void(0)" class="show-settings" onClick="jQuery(\'.backupbuddy_api_key-hide\').slideToggle(); jQuery(this).toggleClass(\'screen-meta-active\'); return false; return false;"><?php esc_html_e( 'Deployment Key', 'it-l10n-backupbuddy' );
					echo ' (' . esc_html( $deployment_enabled_status ) . ')'; ?></a>' +
			'</div>'
		);

		$( '.backupbuddy-progressbar' ).each( function(){
			percentDone = $(this).attr( 'data-percent' );
			$(this).progressbar( { value: parseInt( percentDone, 10 ) } );
			$(this).find( '.backupbuddy-progressbar-label' ).text( percentDone + ' %' );
		});


		$( '#backupbuddy-deployment-regenerateKey' ).click( function(e){
			e.preventDefault();

			if ( false === confirm( '<?php esc_html_e( 'Are you sure you want to generate a new key? This will render any existing keys invalid.', 'it-l10n-backupbuddy' ); ?>' ) ) {
				return false;
			}

			$( '.pb_backupbuddy_loading-regenerateKey' ).show();

			$.post( '<?php echo pb_backupbuddy::ajax_url( 'deployment_regenerateKey' ); ?>', { },
				function(data) {
					$( '.pb_backupbuddy_loading-regenerateKey' ).hide();
					data = $.trim( data );
					try {
						var data = $.parseJSON( data );
					} catch(e) {
						alert( 'Error #3899833: Unexpected non-json response from server: `' + data + '`.' );
						return;
					}
					if ( true !== data.success ) {
						alert( 'Error #32983: Unable to generate new key. Details: `' + data.message + '`.' );
						return;
					}

					$( '#backupbuddy-deployment-regenerateKey-textarea' ).val( data.key );
				}
			);
		}); // End $( '#backupbuddy-deployment-regenerateKey' ).click().

		$( '#wpbody-content > .wrap > h1:first' ).append( '<div class="backupbuddy_destinations_iframe_load"><span class="spinner"></span> <?php esc_html_e( 'Loading remote destinations...', 'it-l10n-backupbuddy' ); ?></div>' );
	});
</script>

<div class="backupbuddy_api_key-hide" style="display: none;">
	<?php
	if ( defined( 'BACKUPBUDDY_API_ENABLE' ) && true == BACKUPBUDDY_API_ENABLE ) {
		require_once pb_backupbuddy::plugin_path() . '/classes/remote_api.php';

		if ( ! isset( pb_backupbuddy::$options['remote_api']['keys'][0] ) || '' == pb_backupbuddy::$options['remote_api']['keys'][0] ) {
			pb_backupbuddy::$options['remote_api']['keys'][0] = backupbuddy_remote_api::generate_key();
			pb_backupbuddy::save();
		}
		?>
		<form method="post">
			<?php pb_backupbuddy::nonce(); ?>
			<input type="hidden" name="regenerate_api_key" value="1">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'Deployment Key', 'it-l10n-backupbuddy' ); ?></h3>
				<?php
					echo wp_kses_post( __( '<p>Copy this Deployment Key into the other Solid Backups Site you wish to have access to Push to or Pull from this site.</p>
					<p>If this site\'s URL changes you will need to generate a new key and update other sites\' destination settings using the old key.</p>', 'it-l10n-backupbuddy' ) );
				?>
				<textarea id="backupbuddy-deployment-regenerateKey-textarea" class="backupbuddy_api_key-hide_textarea backupbuddy_api_key-hide_textarea_large" readonly="readonly" onClick="this.focus();this.select();"><?php echo esc_textarea( pb_backupbuddy::$options['remote_api']['keys'][0] ); ?></textarea>
				<input id="backupbuddy-deployment-regenerateKey" type="submit" name="submit" value="<?php esc_html_e( 'Generate New Deployment Key', 'it-l10n-backupbuddy' ); ?>" class="button button-primary">
				&nbsp;<span class="pb_backupbuddy_loading-regenerateKey" style="display: none; margin-left: 10px;"><img src="<?php echo pb_backupbuddy::plugin_url(); ?>/assets/dist/images/loading.gif" alt="<?php esc_html_e( 'Loading...', 'it-l10n-backupbuddy' ); ?>" title="<?php esc_html_e( 'Loading...', 'it-l10n-backupbuddy' ); ?>" width="16" height="16" style="vertical-align: -3px;" /></span>
		</form>

		<?php
	} else {
		?>
		<h3><?php esc_html_e( 'Deployment', 'it-l10n-backupbuddy' ); ?></h3>

		<?php
			echo wp_kses_post( __( '<p>To enable Solid Backups Deployment, add the code below to your <span style="white-space: nowrap">wp-config.php</a> file.</p><p>Remote API Access allows other sites with your API access key to push to or pull data from this site.</p>
			<p><strong>To enable this feature, add the code below to your <span style="white-space: nowrap">wp-config.php</a> file.</strong></p>', 'it-l10n-backupbuddy' ) );
		?>
		<?php // Don't mess with the formatting here. ?>
		<textarea class="backupbuddy_api_key-hide_textarea" readonly="readonly" onClick="this.focus();this.select();">// Enable Solid Backups Deployment access.
define( 'BACKUPBUDDY_API_ENABLE', true );</textarea>
		<?php
		echo wp_kses_post( __('<p>Be sure to place it <strong class="underline">above</strong> the line that says <code class="wpconfig">/* That\'s all, stop editing! Happy publishing. */</code></p>
		<p>...then refresh this page.</p>', 'it-l10n-backupbuddy' ) );
	}
	?>
</div>

<?php
$destination_tabs_url = pb_backupbuddy::ajax_url( 'destinationTabs' );
if ( pb_backupbuddy::_GET( 'tab' ) ) {
	$destination_tabs_url .= '&tab=' . rawurlencode( pb_backupbuddy::_GET( 'tab' ) );
}

echo '<iframe id="pb_backupbuddy_iframe-dest-wrap" src="' . $destination_tabs_url . '&action_verb=to%20manage%20files" width="100%" frameBorder="0" onLoad="BackupBuddy.Destinations.tabsIframeLoad();">Error #4584594579. Browser not compatible with iframes.</iframe>';

// Handles thickbox auto-resizing. Keep at bottom of page to avoid issues.
if ( ! wp_script_is( 'media-upload' ) ) {
	wp_enqueue_script( 'media-upload' );
	wp_print_scripts( 'media-upload' );
}
