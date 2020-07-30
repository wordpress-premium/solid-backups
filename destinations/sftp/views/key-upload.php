<?php
/**
 * SFTP Key Upload
 *
 * @package BackupBuddy
 */

$upload_dir = wp_upload_dir();
$log_serial = pb_backupbuddy::$options['log_serial'];
$key_file   = 'backupbuddy-sftp-key-' . $log_serial . '.txt';
$key_path   = $upload_dir['basedir'] . '/' . $key_file;
$key_dir    = str_replace( ABSPATH, '/', $key_path );

$ajax_sftp_key_upload_url = pb_backupbuddy::ajax_url( 'sftp-key-upload' );
$ajax_sftp_key_clear_url  = pb_backupbuddy::ajax_url( 'sftp-key-clear' );
?>
<style type="text/css">
	.hidden {
		display: none;
	}
	.loading {
		position: relative;
		pointer-events: none;
		opacity: 0.5;
	}
	.sftp-key-exists-note,
	.sftp-key-missing-note,
	.sftp-key-needs-test {
		padding-top: 6px;
	}
	.sftp-key-needs-test {
		font-weight: bold;
	}
	span.success {
		font-weight: bold;
		color: #468847;
	}
</style>
<script type="text/javascript">
	jQuery( function( $ ) {

		// Display Upload Form.
		$( 'a[href="#upload-sftp-key"]' ).on( 'click', function( e ) {
			e.preventDefault();
			$( this ).addClass( 'hidden' );
			$( '.sftp-key-upload' ).removeClass( 'hidden' );
			return false;
		});

		// Cancel Upload, hide form and show button again.
		$( '.sftp-key-upload button' ).on( 'click', function( e ) {
			e.preventDefault();
			$( '.sftp-key-upload' ).addClass( 'hidden' );
			$( 'a[href="#upload-sftp-key"]' ).removeClass( 'hidden' );
			return false;
		});

		// Clear sFTP key.
		$( 'a[href="#clear-sftp-key"]' ).on( 'click', function( e ) {
			e.preventDefault();

			$( '.sftp-key-uploaded, .sftp-key-exists' ).addClass( 'loading' );

			var logSerial = $( this ).attr( 'data-log-serial' );

			$.ajax({
				url: '<?php echo $ajax_sftp_key_clear_url; ?>',
				data: { log_serial: logSerial },
				type: 'post',
				dataType: 'json',
				success: function( response ) {
					if ( response.success ) {
						$( '.sftp-key-exists' ).addClass( 'hidden' );
						$( '.sftp-key-uploaded' ).addClass( 'hidden' );
						$( '.sftp-key-needs-test' ).addClass( 'hidden' );
						$( '.sftp-key-exists-note' ).addClass( 'hidden' );

						$( 'a[href="#upload-sftp-key"]' ).removeClass( 'hidden' );
						$( '.sftp-key-missing-note' ).removeClass( 'hidden' );
					} else {
						alert( response.error );
					}
					$( '.sftp-key-uploaded, .sftp-key-exists' ).removeClass( 'loading' );
				}
			});

			return false;
		});

		// Handle Upload.
		$( '.sftp-key-file' ).on( 'change', function() {
			var $input = $( this ),
				name = $input.attr( 'name' ),
				fileData = $input.prop( 'files' )[0],
				formData = new FormData(),
				logSerial = $( 'input[name="log_serial"]' ).val();

			if ( ! fileData ){
				return;
			}

			$( '.sftp-key-upload' ).addClass( 'loading' );

			formData.append( name, fileData );
			formData.append( 'log_serial', logSerial );

			$.ajax({
				url: '<?php echo $ajax_sftp_key_upload_url; ?>',
				dataType: 'text',
				cache: false,
				contentType: false,
				processData: false,
				type: 'post',
				data: formData,
				success: function( response ) {
					$input.val( '' );

					try {
						response = JSON.parse( response );
					} catch ( err ) {
						alert( err.message );
						$( '.sftp-key-upload' ).removeClass( 'loading' );
						return false;
					}

					if ( response.success ) {
						$( '.sftp-key-upload' ).addClass( 'hidden' );
						$( '.sftp-key-uploaded' ).removeClass( 'hidden' );
						$( '.sftp-key-exists-note' ).removeClass( 'hidden' );
						$( '.sftp-key-needs-test' ).removeClass( 'hidden' );
						$( '.sftp-key-missing-note' ).addClass( 'hidden' );
					} else {
						alert( response.error );
					}
					$( '.sftp-key-upload' ).removeClass( 'loading' );
				}
			});
		});
	});
</script>
<?php if ( file_exists( $key_path ) ) : ?>
	<span class="sftp-key-exists">
		<strong><?php esc_html_e( 'Key file found!', 'it-l10n-backupbuddy' ); ?></strong>
		<?php esc_html_e( 'Password will be used to unlock key file (if set).', 'it-l10n-backupbuddy' ); ?>
		<a href="#clear-sftp-key" data-log-serial="<?php echo esc_attr( $log_serial ); ?>"><?php esc_html_e( 'Clear sFTP Key File', 'it-l10n-backupbuddy' ); ?></a>
	</span>
<?php endif; ?>

<a href="#upload-sftp-key" class="button<?php if ( file_exists( $key_path ) ) echo ' hidden'; ?>"><?php esc_html_e( 'Upload an sFTP Key File (Optional)', 'it-l10n-backupbuddy' ); ?></a>

<span class="sftp-key-upload hidden">
	<input type="file" name="sftp_key" class="sftp-key-file">
	<input type="hidden" name="log_serial" value="<?php echo esc_attr( $log_serial ); ?>">
	<button class="button"><?php esc_html_e( 'Cancel', 'it-l10n-backupbuddy' ); ?></button>
</span>
<span class="sftp-key-uploaded hidden">
	<span class="success"><?php esc_html_e( 'Key file uploaded successfully!', 'it-l10n-backupbuddy' ); ?></span>
	<?php esc_html_e( 'Password will be used to unlock key file (if set).', 'it-l10n-backupbuddy' ); ?>
	<a href="#clear-sftp-key" data-log-serial="<?php echo esc_attr( $log_serial ); ?>"><?php esc_html_e( 'Clear sFTP Key File', 'it-l10n-backupbuddy' ); ?></a>
</span>

<div class="sftp-key-exists-note<?php if ( ! file_exists( $key_path ) ) echo ' hidden'; ?>">
	<?php esc_html_e( 'sFTP key file stored at', 'it-l10n-backupbuddy' ); ?>: <code><?php echo esc_html( $key_dir ); ?></code>
</div>

<div class="sftp-key-missing-note<?php if ( file_exists( $key_path ) ) echo ' hidden'; ?>">
	<?php esc_html_e( 'Or manually place your sFTP key file at', 'it-l10n-backupbuddy' ); ?>: <code><?php echo esc_html( $key_dir ); ?></code>
</div>

<div class="sftp-key-needs-test hidden">
	<?php esc_html_e( 'Be sure to click Test Settings to ensure your key works!', 'it-l10n-backupbuddy' ); ?>
</div>
