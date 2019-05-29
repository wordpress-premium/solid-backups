<?php
/**
 * Destinations page
 *
 * @package BackupBuddy
 */

$default_tab = 0;
if ( is_numeric( pb_backupbuddy::_GET( 'tab' ) ) ) {
	$default_tab = pb_backupbuddy::_GET( 'tab' );
}

wp_enqueue_script( 'thickbox' );
wp_print_scripts( 'thickbox' );
wp_print_styles( 'thickbox' );

wp_enqueue_script( 'jquery-ui-core' );
wp_print_styles( 'jquery-ui-core' );

wp_enqueue_script( 'jquery-ui-progressbar' );
wp_print_styles( 'jquery-ui-progressbar' );

$deployment_enabled_status = __( 'disabled', 'it-l10n-backupbuddy' );
if ( defined( 'BACKUPBUDDY_API_ENABLE' ) && true == BACKUPBUDDY_API_ENABLE ) {
	$deployment_enabled_status = __( 'enabled', 'it-l10n-backupbuddy' );
}
?>

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
			window.location.href = '<?php echo $admin_url; ?>?page=pb_backupbuddy_backup&custom=remoteclient&destination_id=' + destination_id;
		}
	}

	jQuery(document).ready(function() {

		jQuery('#screen-meta-links').append(
			'<div id="backupbuddy-meta-link-wrap" class="hide-if-no-js screen-meta-toggle">' +
				'<a href="javascript:void(0)" class="show-settings" onClick="jQuery(\'.backupbuddy_api_key-hide\').slideToggle(); jQuery(this).toggleClass(\'screen-meta-active\'); return false; return false;"><?php esc_html_e( 'Deployment Key', 'it-l10n-backupbuddy' );
					echo ' (' . esc_html( $deployment_enabled_status ) . ')'; ?></a>' +
			'</div>'
		);

		jQuery('#screen-meta-links').append(
			'<div id="backupbuddy-meta-link-wrap" class="hide-if-no-js screen-meta-toggle">' +
				'<a href="javascript:void(0)" class="show-settings bb-toggle-meta" onClick="jQuery(\'.backupbuddy-destination-sends\').slideToggle(); jQuery(this).toggleClass(\'screen-meta-active\'); return false;"><?php esc_html_e( 'Recently Sent Files', 'it-l10n-backupbuddy' ); ?></a>' +
			'</div>'
		);


		jQuery( '.backupbuddy-progressbar' ).each( function(){
			percentDone = jQuery(this).attr( 'data-percent' );
			jQuery(this).progressbar( { value: parseInt( percentDone, 10 ) } );
			jQuery(this).find( '.backupbuddy-progressbar-label' ).text( percentDone + ' %' );
		});


		jQuery( '#backupbuddy-deployment-regenerateKey' ).click( function(e){
			e.preventDefault();

			if ( false === confirm( '<?php esc_html_e( 'Are you sure you want to generate a new key? This will render any existing keys invalid.', 'it-l10n-backupbuddy' ); ?>' ) ) {
				return false;
			}

			jQuery( '.pb_backupbuddy_loading-regenerateKey' ).show();
			jQuery.post( '<?php echo pb_backupbuddy::ajax_url( 'deployment_regenerateKey' ); ?>', { },
				function(data) {
					jQuery( '.pb_backupbuddy_loading-regenerateKey' ).hide();
					data = jQuery.trim( data );
					try {
						var data = jQuery.parseJSON( data );
					} catch(e) {
						alert( 'Error #3899833: Unexpected non-json response from server: `' + data + '`.' );
						return;
					}
					if ( true !== data.success ) {
						alert( 'Error #32983: Unable to generate new key. Details: `' + data.message + '`.' );
						return;
					}

					jQuery( '#backupbuddy-deployment-regenerateKey-textarea' ).val( data.key );
				}
			);
		}); // End jQuery( '#backupbuddy-deployment-regenerateKey' ).click().
	});
</script>

<?php pb_backupbuddy::$ui->title( esc_html__( 'Destinations', 'it-l10n-backupbuddy' ), true, false ); ?>

<br>

<div class="backupbuddy_api_key-hide" style="
	display: none;
	border: 1px solid #e5e5e5;
	-webkit-box-shadow: 0 1px 1px rgba(0,0,0,.04);
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
	padding: 20px;
	background: #fff;
	margin-bottom: 40px;
">
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
				<p>
					<?php esc_html_e( 'Copy this Deployment Key into the other BackupBuddy Site you wish to have access to Push to or Pull from this site.', 'it-l10n-backupbuddy' ); ?>
					<br>
					<?php esc_html_e( 'If this site\'s URL changes you will need to generate a new key and update other sites\' destination settings using the old key.', 'it-l10n-backupbuddy' ); ?>
				</p>
				<textarea id="backupbuddy-deployment-regenerateKey-textarea" cols="90" rows="4" style="padding: 15px; background: #fcfcfc;" readonly="readonly" onClick="this.focus();this.select();"><?php echo pb_backupbuddy::$options['remote_api']['keys'][0]; ?></textarea>
				<br><br>
				<input id="backupbuddy-deployment-regenerateKey" type="submit" name="submit" value="<?php esc_html_e( 'Generate New Deployment Key', 'it-l10n-backupbuddy' ); ?>" class="button button-primary" style="margin-top: -5px;">
				&nbsp;<span class="pb_backupbuddy_loading-regenerateKey" style="display: none; margin-left: 10px;"><img src="<?php echo pb_backupbuddy::plugin_url(); ?>/images/loading.gif" alt="<?php esc_html_e( 'Loading...', 'it-l10n-backupbuddy' ); ?>" title="<?php esc_html_e( 'Loading...', 'it-l10n-backupbuddy' ); ?>" width="16" height="16" style="vertical-align: -3px;" /></span>
		</form>

		<?php
	} else {
		?>
		<h3 style="margin-top: 0;"><?php esc_html_e( 'Deployment', 'it-l10n-backupbuddy' ); ?></h3>
		Remote API Access allows other sites with your API access key entered to push to or pull data from this site.
		<br><br>
		<b>For added security you must manually enable the API via an entry into your wp-config.php file.
		<br><br>
		To do this <i>add the following to your wp-config.php file ABOVE the line commenting "That's all, stop editing!"</i>. <i>Refresh this page after adding</i> the following:</b>
		<br>
<textarea style="width: 100%; padding: 15px;" readonly="readonly" onClick="this.focus();this.select();">
define( 'BACKUPBUDDY_API_ENABLE', true ); // Enable BackupBuddy Deployment access.
</textarea>
		<br>
		<?php
	}
	echo '</div>';

	echo '<div class="backupbuddy-destination-sends" style="display: none;"><br>';
		require_once 'server_info/remote_sends.php';
	echo '<br></div>';
?>

<div class="backupbuddy_destinations_iframe_load">
	<img src="<?php echo pb_backupbuddy::plugin_url(); ?>/images/loading.gif" title="Loading... This may take a few seconds..." style="vertical-align: -3px; margin-left: 5px;"> <?php esc_html_e( 'Loading remote destinations...', 'it-l10n-backupbuddy' ); ?>
</div>

<?php
echo '<iframe id="pb_backupbuddy_iframe-dest-wrap" src="' . pb_backupbuddy::ajax_url( 'destinationTabs' ) . '&tab=' . esc_attr( $default_tab ) . '&action_verb=to%20manage%20files" width="100%" height="4000" frameBorder="0" onLoad="jQuery( \'.backupbuddy_destinations_iframe_load\' ).css(\'visibility\',\'hidden\');">Error #4584594579. Browser not compatible with iframes.</iframe>';
?>

<br style="clear: both;"><br style="clear: both;">

<?php
// Handles thickbox auto-resizing. Keep at bottom of page to avoid issues.
if ( ! wp_script_is( 'media-upload' ) ) {
	wp_enqueue_script( 'media-upload' );
	wp_print_scripts( 'media-upload' );
}
