<?php
/**
 * Google Drive Configuration screen.
 *
 * Incoming vars:
 *   array  $destination_settings  Destination settings array.
 *   string $mode                  Configuration mode.
 *   int    $destination_id        ID of the Destination.
 *
 * @package BackupBuddy
 */

if ( pb_backupbuddy_destinations::is_using_destination_type( 'gdrive' ) ) {
	// This destination cannot be added (and therefore edited) if the deprecated Google Drive (v1) destination is in use.
	?>
	<p style="margin-bottom: 1em">
		<?php esc_html_e( 'You are attempting to add a destination (Google Drive) that is incompatible with the deprecated Google Drive (v1). You must delete that Google Drive (v1) destination to proceed.', 'it-l10n-backupbuddy' ); ?>
	</p>
	<?php
	return;
}

pb_backupbuddy_destination_gdrive2::add_settings( $destination_settings );

global $pb_hide_save;
global $pb_hide_test;
$pb_hide_save = true;
$pb_hide_test = true;

$auth_type = pb_backupbuddy::_GET( 'account' );
if ( ! $auth_type ) {
	$auth_type = 'normal';
}

$default_name = null;
$token        = false;

$service_account_file  = '';
$service_account_email = '';
$client_id             = pb_backupbuddy_destination_gdrive2::$settings['client_id'];

$g_assets = array(
	'normal' => pb_backupbuddy::plugin_url() . '/destinations/gdrive2/assets/btn_google_signin_light_normal_web@2x.png',
	'hover'  => pb_backupbuddy::plugin_url() . '/destinations/gdrive2/assets/btn_google_signin_light_focus_web@2x.png',
	'active' => pb_backupbuddy::plugin_url() . '/destinations/gdrive2/assets/btn_google_signin_light_pressed_web@2x.png',
);

if ( 'add' === $mode ) {

	if ( 'normal' === $auth_type ) {

		$auth_code = trim( pb_backupbuddy::_POST( 'auth_code' ) );

		if ( $auth_code ) {
			$client = pb_backupbuddy_destination_gdrive2::get_client();

			try {
				$token = $client->fetchAccessTokenWithAuthCode( $auth_code );
				if ( array_key_exists( 'error', $token ) ) {
					throw new Exception( join( ', ', $token ) );
				}
			} catch ( Exception $e ) {
				$err = pb_backupbuddy_destination_gdrive2::get_gdrive_exception_error( $e );
				pb_backupbuddy::alert( 'Error Authenticating. Make sure you entered the code exactly. Details: `' . $err . '`. Please check codes and try again.' );
			}

			if ( empty( $token['refresh_token'] ) ) {
				$help_link = '<br><a href="https://go.solidwp.com/google-drive-missing-refresh-token" target="_blank" rel="noopener">https://go.solidwp.com/google-drive-missing-refresh-token</a>';
				pb_backupbuddy::alert( __( 'Unable to retrieve refresh token from Google Drive authorization. Please follow the instructions on the link below to reset Solid Backups\'s access to Google Drive:', 'it-l10n-backupbuddy' ) . $help_link, true );
			} else {
				$token     = json_encode( $token );
				$client_id = pb_backupbuddy_destination_gdrive2::$settings['client_id'];

				pb_backupbuddy_destination_gdrive2::$settings['token'] = $token;
			}
		}

		if ( ! pb_backupbuddy_destination_gdrive2::$settings['token'] ) {
			$auth_url = pb_backupbuddy_destination_gdrive2::get_oauth_url();
			?>
			<script type="text/javascript">
				function backupbuddy_gdrive2_preload_image( url ){
					var img = new Image();
					img.src = url;
				}

				( function( $ ) {
					'use strict';

					function backupbuddy_gdrive2_init() {
						$( '.gdrive2-authorize' ).on( 'click', function( e ) {
							e.preventDefault();
							var win = backupbuddy_oauth_window( $( this ).attr( 'href' ), 'Google Drive Authorization', 400, 500 ),
								$btn = $( this ),
								$input = $( '.gdrive2-auth-code' ),
								$footer = $( '.form-footer' );

							if ( ! $input.length ) {
								return;
							}

							$( '.gdrive2-initial-actions' ).addClass( 'hidden' );
							$input.removeClass( 'hidden' );
							$footer.removeClass( 'hidden' );
						});
					}

					$( function() {
						backupbuddy_gdrive2_init();
					});
				})( jQuery );

				<?php foreach ( $g_assets as $asset ) : ?>
					backupbuddy_gdrive2_preload_image( '<?php echo esc_html( $asset ); ?>' );
				<?php endforeach; ?>
			</script>
			<style type="text/css">
				.gdrive2-auth {
					padding-top: 15px;
				}
				.gdrive2-auth label {
					display: block;
				}
				.gdrive2-auth input.large {
					width: 100%;
					max-width: 700px;
				}
				.gdrive2-auth .form-footer {
					padding: 15px 0;
				}
				.gdrive2-authorize {
					display: inline-block;
					font-size: 0;
					width: 191px;
					height: 46px;
					background-repeat: no-repeat;
					background-size: contain;
					background-position: 0 0;
					background-image: url( '<?php echo esc_html( $g_assets['normal'] ); ?>' );
				}
				.gdrive2-authorize:hover,
				.gdrive2-authorize:focus {
					background-image: url( '<?php echo esc_html( $g_assets['hover'] ); ?>' );
				}
				.gdrive2-authorize:active {
					background-image: url( '<?php echo esc_html( $g_assets['active'] ); ?>' );
				}
			</style>
			<form method="post" action="<?php echo esc_attr( pb_backupbuddy::ajax_url( 'destination_picker' ) ) . '&add=gdrive2&account=normal&callback_data=' . esc_attr( pb_backupbuddy::_GET( 'callback_data' ) ); ?>" class="gdrive2-auth">

				<p class="gdrive2-initial-actions">
					<?php
					printf(
						'<a href="%s" target="_blank" class="gdrive2-authorize">%s</a>',
						esc_attr( $auth_url ),
						esc_html__( 'Click here to log into Google Drive', 'it-l10n-backupbuddy' )
					);
					?>
				</p>

				<p class="gdrive2-initial-actions"><?php
				echo wp_kses_post(
					sprintf(
						// translators: %s is a link to the Google API Services User Data Policy.
						__( 'Solid Backup\'s use and transfer to any other app of information received from Google APIs will adhere to <a href="%s" target="_blank">Google API Services User Data Policy</a>, including the Limited Use requirements.', 'it-l10n-backupbuddy' ),
						'https://developers.google.com/terms/api-services-user-data-policy#additional_requirements_for_specific_api_scopes'
					)
				); ?></p>

				<p class="gdrive2-initial-actions"><?php esc_html_e( 'Looking for Service Account File authentication?' ); ?> <a href="<?php echo esc_attr( pb_backupbuddy::ajax_url( 'destination_picker' ) ) . '&add=gdrive2&account=service&callback_data=' . esc_attr( pb_backupbuddy::_GET( 'callback_data' ) ); ?>"><?php esc_html_e( 'Click Here', 'it-l10n-backupbuddy' ); ?></a></p>

				<p class="gdrive2-auth-code hidden">
					<label>
						<span class="label"><?php esc_html_e( 'Paste Your Code Here:', 'it-l10n-backupbuddy' ); ?></span>
						<span class="field"><input type="text" name="auth_code" class="large" /></span>
					</label>
				</p>

				<footer class="form-footer hidden">
					<input type="submit" class="button button-primary" value="Link Account">
				</footer>

				<input type="hidden" name="gaction" value="auth_gdrive">

			</form>

			<?php
			return;
		}
	} elseif ( 'service' === $auth_type ) { // service account.
		$service_form_ok = true;
		if ( 'auth_gdrive' === pb_backupbuddy::_POST( 'gaction' ) ) {
			$service_account_file  = trim( pb_backupbuddy::_POST( 'service_account_file' ) );
			$service_account_email = trim( pb_backupbuddy::_POST( 'service_account_email' ) );
			if ( ! $service_account_file || ! $service_account_email ) {
				pb_backupbuddy::alert( 'Error #433443: Missing fields. All fields required. Please try again.', true );
				$service_form_ok      = false;
				$service_account_file = '';
			} else { // All fields entered.
				if ( ! file_exists( $service_account_file ) ) {
					pb_backupbuddy::alert( 'Error #83493844: Unable to find JSON file at entered path `' . htmlentities( $service_account_file ) . '`. Verify file path is correct and has readable permissions.', true );
					$service_form_ok      = false;
					$service_account_file = '';
				}
			}

			if ( true === $service_form_ok ) { // Test credentials.
				pb_backupbuddy_destination_gdrive2::$settings['service_account_file']  = $service_account_file;
				pb_backupbuddy_destination_gdrive2::$settings['service_account_email'] = $service_account_email;

				$info = pb_backupbuddy_destination_gdrive2::getDriveInfo();

				if ( false === $info ) {
					pb_backupbuddy::alert( 'Error #84934834: Unable to authenticate to Google Drive Service Account for Storage Access with supplied credentials. NOTE: You must use the JSON file format and NOT p12.<br>Account Name: ' . esc_html( $service_account_email ) . '<br>JSON File Path: ' . esc_html( $service_account_file ), true );
					$service_form_ok = false;
				}
			}
		} else {
			$service_form_ok = false;
		}

		if ( false === $service_form_ok ) {
			?>
			<p><b>&nbsp;&nbsp;Service Account Setup:</b></p>
			<ol>
				<li><a href="https://go.solidwp.com/google-account-service-accounts" target="_blank" class="button button-secondary secondary-button" style="vertical-align: 0;">Click here to launch the Create Service Account page</a></li>
				<li>Click "Select a Project", select your project, and click "Open"</li>
				<li>Click "+ Create Service Account" at the top of the page</li>
				<li>Enter a descriptive name and a role of "Storage -> Storage Admin" (our default recommendation)</li>
				<li>Click "+ Create Key" and select Key Type of "<strong>JSON</strong>"</li>
				<li>Click "Create". Your .json file should download automatically.</li>
				<li>Click "Done".</li>
			</ol>
			<p>Then:</p>
			<ol>
				<li>Copy the "Service Account ID" which looks like an email to the box below:</li>
				<li>Upload the <strong>JSON</strong> key file onto your server, preferably outside a web-accessible directory and enter its path below:</li>
			</ol>
			<form method="post" action="<?php echo esc_attr( pb_backupbuddy::ajax_url( 'destination_picker' ) ) . '&add=gdrive2&account=service&callback_data=' . esc_attr( pb_backupbuddy::_GET( 'callback_data' ) ); ?>" >
				<input type="hidden" name="gaction" value="auth_gdrive">

				<table class="form-table">
					<tr>
						<th scope="row">Service Account ID</th>
						<td><input type="text" name="service_account_email" placeholder="Typical format: description@*.iam.gserviceaccount.com" value="<?php echo esc_attr( $service_account_email ); ?>" style="width: 100%; max-width: 720px;"></td>
					</tr>
					<tr>
						<th scope="row">Full path to .json key file</th>
						<td><input type="text" name="service_account_file" style="width: 100%; max-width: 720px;" value="<?php echo esc_attr( $service_account_file ); ?>"><br><span class="description" style="display: inline-block; overflow: scroll;">Web root: <?php echo esc_html( ABSPATH ); ?></span></td>
					</tr>
					<tr>
						<th scope="row">&nbsp;</th>
						<td><input class="button-primary" type="submit" value="Continue"></td>
					</tr>
				</table>
			</form>
			<?php
			return;
			// End service account form submission needing entered/re-entered.
		}
	} else {
		pb_backupbuddy::alert( __( 'Unsupported authorization mode.', 'it-l10n-backupbuddy' ), true );
		return;
	}
}

// Editing or add mode authed. Show settings.
$pb_hide_test = false;
$pb_hide_save = false;


if ( 'save' !== $mode ) {
	$info = pb_backupbuddy_destination_gdrive2::getDriveInfo();
	if ( false === $info ) {
		pb_backupbuddy::alert( 'Error #84934834: Unable to authenticate to Google Drive.', true );
		return;
	}

	require pb_backupbuddy::plugin_path() . '/destinations/gdrive2/views/usage-basic.php';
}

if ( 'add' === $mode ) {
	$default_name = 'My Google Drive';
}

$settings_form->add_setting(
	array(
		'type'    => 'text',
		'name'    => 'title',
		'title'   => __( 'Destination name', 'it-l10n-backupbuddy' ),
		'tip'     => __( 'Name of the new destination to create. This is for your convenience only.', 'it-l10n-backupbuddy' ),
		'rules'   => 'required|string[1-45]',
		'default' => $default_name,
	)
);

$settings_form->add_setting(
	array(
		'type'    => 'hidden',
		'name'    => 'token',
		'default' => $token,
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'hidden',
		'name'    => 'client_id',
		'default' => $client_id,
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'hidden',
		'name'    => 'service_account_file',
		'default' => $service_account_file,
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'hidden',
		'name'    => 'service_account_email',
		'default' => $service_account_email,
	)
);


$folder_text = '';
if ( 'save' !== $mode ) {
	$folder_id   = pb_backupbuddy_destination_gdrive2::get_root_folder();
	$folder_meta = pb_backupbuddy_destination_gdrive2::get_file_meta( false, $folder_id );
	if ( is_object( $folder_meta ) ) {
		$folder_text = 'Folder name: &quot;' . esc_html( $folder_meta->name ) . '&quot;';
	} else {
		$folder_text = 'n/a';
	}
}


$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'folder_id',
		'title'     => __( 'Storage Folder Identifier', 'it-l10n-backupbuddy' ),
		'tip'       => __( 'Folder to store files within. Leave blank to store in the root or use the unique identifier ID. Use the folder picker or get the path ID from the folder URL in your web browser. Renaming the folder in Google Drive will not change the ID or impact backups going into it.', 'it-l10n-backupbuddy' ),
		'rules'     => '',
		'css'       => 'width: 300px;',
		'after'     => ' <span class="description">This is NOT the folder name but its ID. Leave blank to store in root.</span>&nbsp;<span class="description"><span class="backupbuddy-gdrive2-folder-name-text">' . $folder_text . '</span></span><br><br>',
		'row_class' => 'backupbuddy-gdrive2-folder-row',
	)
);
$settings_form->add_setting(
	array(
		'type'    => 'hidden',
		'name'    => 'folder_name',
		'default' => '',
	)
);

// Hide when saving or if file management is disabled.
if ( 'save' !== $mode && ( empty( pb_backupbuddy_destination_gdrive2::$settings['disable_file_management'] ) || '1' != pb_backupbuddy_destination_gdrive2::$settings['disable_file_management'] ) ) {
	pb_backupbuddy_destination_gdrive2::folder_selector( $destination_id );
}


$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'full_archive_limit',
		'title' => __( 'Full backup limit', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Example: 5] - Enter 0 for no limit. This is the maximum number of Full (complete) backup archives to be stored in this specific destination. If this limit is met the oldest backup of this type will be deleted.', 'it-l10n-backupbuddy' ),
		'rules' => 'required|int[0-9999999]',
		'css'   => 'width: 50px;',
		'after' => ' backups',
	)
);
$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'db_archive_limit',
		'title' => __( 'Database only limit', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Example: 5] - Enter 0 for no limit. This is the maximum number of Database Only backup archives to be stored in this specific destination. If this limit is met the oldest backup of this type will be deleted.', 'it-l10n-backupbuddy' ),
		'rules' => 'required|int[0-9999999]',
		'css'   => 'width: 50px;',
		'after' => ' backups',
	)
);

$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'themes_archive_limit',
		'title' => __( 'Themes only limit', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Example: 5] - Enter 0 for no limit. This is the maximum number of this type of archive to be stored in this specific destination. If this limit is met the oldest backup of this type will be deleted.', 'it-l10n-backupbuddy' ),
		'rules' => 'int[0-9999999]',
		'css'   => 'width: 50px;',
		'after' => ' backups. <p class="description">0 or blank for no limit.</p>',
	)
);
$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'plugins_archive_limit',
		'title' => __( 'Plugins only limit', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Example: 5] - Enter 0 for no limit. This is the maximum number of this type of archive to be stored in this specific destination. If this limit is met the oldest backup of this type will be deleted.', 'it-l10n-backupbuddy' ),
		'rules' => 'int[0-9999999]',
		'css'   => 'width: 50px;',
		'after' => ' backups. <p class="description">0 or blank for no limit.</p>',
	)
);
$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'media_archive_limit',
		'title' => __( 'Media only limit', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Example: 5] - Enter 0 for no limit. This is the maximum number of this type of archive to be stored in this specific destination. If this limit is met the oldest backup of this type will be deleted.', 'it-l10n-backupbuddy' ),
		'rules' => 'int[0-9999999]',
		'css'   => 'width: 50px;',
		'after' => ' backups. <p class="description">0 or blank for no limit.</p>',
	)
);
$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'files_archive_limit',
		'title' => __( 'Files only limit', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Example: 5] - Enter 0 for no limit. This is the maximum number of this type of archive to be stored in this specific destination. If this limit is met the oldest backup of this type will be deleted.', 'it-l10n-backupbuddy' ),
		'rules' => 'int[0-9999999]',
		'css'   => 'width: 50px;',
		'after' => ' backups. <p class="description">0 or blank for no limit.</p>',
	)
);


$settings_form->add_setting(
	array(
		'type'      => 'title',
		'name'      => 'advanced_begin',
		'title'     => '<span class="advanced-toggle-title-icon">' . pb_backupbuddy::$ui->get_icon( 'chevronleft' ) . '</span> ' . __( 'Advanced Options', 'it-l10n-backupbuddy' ),
		'row_class' => 'advanced-toggle-title',
	)
);

$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'max_burst',
		'title'     => __( 'Send per burst', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Default 25] - This is the amount of data that will be sent per burst within a single PHP page load/chunk. Bursts happen within a single page load. Chunks occur when broken up between page loads/PHP instances. Reduce if hitting PHP memory limits. Chunking time limits will only be checked between bursts. Lower burst size if timeouts occur before chunking checks trigger.', 'it-l10n-backupbuddy' ),
		'rules'     => 'required|int[0-9999999]',
		'css'       => 'width: 50px;',
		'after'     => ' MB',
		'row_class' => 'advanced-toggle advanced-toggle-hidden',
	)
);

$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'max_time',
		'title'     => __( 'Max time per chunk', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Example: 30] - Enter 0 for no limit (aka no chunking; bursts may still occur based on burst size setting). This is the maximum number of seconds per page load that bursts will occur. If this time is exceeded when a burst finishes then the next burst will be chunked and ran on a new page load. Multiple bursts may be sent within each chunk.', 'it-l10n-backupbuddy' ),
		'rules'     => '',
		'css'       => 'width: 50px;',
		'after'     => ' secs. <span class="description">' . esc_html__( 'Blank for detected default:', 'it-l10n-backupbuddy' ) . ' ' . backupbuddy_core::detectMaxExecutionTime() . ' sec</span>',
		'row_class' => 'advanced-toggle advanced-toggle-hidden',
	)
);

$settings_form->add_setting(
	array(
		'type'      => 'checkbox',
		'name'      => 'disable_gzip',
		'options'   => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'     => __( 'Disable Compression', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Default: unchecked] - If you are getting Invalid jSON errors from Google, you can try checking this option.', 'it-l10n-backupbuddy' ),
		'css'       => '',
		'after'     => '<span class="description"> ' . __( 'Check to disable gzip compression.', 'it-l10n-backupbuddy' ) . '</span>',
		'row_class' => 'advanced-toggle advanced-toggle-hidden',
	)
);

if ( 'edit' !== $mode || ( isset( pb_backupbuddy_destination_gdrive2::$settings['disable_file_management'] ) && '0' == pb_backupbuddy_destination_gdrive2::$settings['disable_file_management'] ) ) {
	$settings_form->add_setting(
		array(
			'type'      => 'checkbox',
			'name'      => 'disable_file_management',
			'options'   => array(
				'unchecked' => '0',
				'checked'   => '1',
			),
			'title'     => __( 'Disable file management', 'it-l10n-backupbuddy' ),
			'tip'       => __( '[[Default: unchecked] - When checked, selecting this destination disables browsing or accessing files stored at this destination from within Solid Backups. NOTE: Once enabled this cannot be disabled without deleting and re-creating this destination. NOTE: Once enabled this cannot be disabled without deleting and re-creating this destination.', 'it-l10n-backupbuddy' ),
			'css'       => '',
			'rules'     => '',
			'after'     => __( 'Once disabled you must recreate the destination to re-enable.', 'it-l10n-backupbuddy' ),
			'row_class' => 'advanced-toggle advanced-toggle-hidden',
		)
	);
}
$settings_form->add_setting(
	array(
		'type'      => 'checkbox',
		'name'      => 'disabled',
		'options'   => array(
			'unchecked' => '0',
			'checked'   => '1',
		),
		'title'     => __( 'Disable destination', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Default: unchecked] - When checked, this destination will be disabled and unusable until re-enabled. Use this if you need to temporary turn a destination off but don\t want to delete it.', 'it-l10n-backupbuddy' ),
		'css'       => '',
		'after'     => '<span class="description"> ' . __( 'Check to disable this destination until re-enabled.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'     => '',
		'row_class' => 'advanced-toggle advanced-toggle-hidden',
	)
);
