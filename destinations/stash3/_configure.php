<?php
/**
 * Settings to display in a form for a user to configure.
 *
 * Pre-populated variables coming into this script:
 *      $destination_settings
 *      $mode
 *
 * @package BackupBuddy
 */

if ( isset( $destination['disabled'] ) && '1' == $destination['disabled'] ) {
	die( __( '<span class="description">This destination is currently disabled based on its settings. Re-enable it under its Advanced Settings.</span>', 'it-l10n-backupbuddy' ) );
}

global $pb_hide_test, $pb_hide_save;
$pb_hide_test = false;

$itxapi_username = '';
$itxapi_password = '';
$itxapi_token    = '';


if ( 'add' === $mode ) { // ADD MODE.

	$credentials_form = new pb_backupbuddy_settings( 'pre_settings', false, 'action=pb_backupbuddy_backupbuddy&function=destination_picker&quickstart=' . htmlentities( pb_backupbuddy::_GET( 'quickstart' ) ) . '&add=' . htmlentities( pb_backupbuddy::_GET( 'add' ) ) . '&callback_data=' . htmlentities( pb_backupbuddy::_GET( 'callback_data' ) ) . '&sending=' . pb_backupbuddy::_GET( 'sending' ) . '&selecting=' . pb_backupbuddy::_GET( 'selecting' ) ); // name, savepoint|false, additional querystring.

	$credentials_form->add_setting(
		array(
			'type'  => 'text',
			'name'  => 'itxapi_username',
			'title' => esc_html__( 'iThemes username', 'it-l10n-backupbuddy' ),
			'tip'   => esc_html__( '[Example: kerfuffle] - Your iThemes.com membership username.', 'it-l10n-backupbuddy' ), // Is a username example necessary here?
			'rules' => 'required|string[1-45]',
		)
	);
	$credentials_form->add_setting(
		array(
			'type'  => 'password',
			'name'  => 'itxapi_password_raw',
			'title' => esc_html__( 'iThemes password', 'it-l10n-backupbuddy' ),
			'tip'   => esc_html__( '[Example: 48dsds!s08K%x2s] - Your iThemes.com membership password.', 'it-l10n-backupbuddy' ), // Is a password example necessary here?
			'rules' => 'required|string[1-250]',
		)
	);

	$settings_result = $credentials_form->process();

	$login_welcome = esc_html__( 'Log in with your iThemes.com member account to begin.', 'it-l10n-backupbuddy' );

	if ( 0 === count( $settings_result ) ) { // No form submitted.

		echo $login_welcome; // @codingStandardsIgnoreLine: ok.

		$credentials_form->display_settings( 'Submit' );

		$pb_hide_test = true;
		$pb_hide_save = true;
		return;
	} else { // Form submitted.
		if ( count( $settings_result['errors'] ) > 0 ) { // Form errors.
			echo $login_welcome; // @codingStandardsIgnoreLine: ok.
			pb_backupbuddy::alert( implode( '<br>', $settings_result['errors'] ) );
			$credentials_form->display_settings( 'Submit' );

			$pb_hide_test = true;
			$pb_hide_save = true;
			return;
		} else { // No form errors; process!
			$pb_hide_test = true;
			$pb_hide_save = true;

			require_once( pb_backupbuddy::plugin_path() . '/lib/stash/stash-api.php' );
			require_once dirname( __FILE__ ) . '/class.itx_helper2.php';
			global $wp_version;

			$itxapi_username = strtolower( $settings_result['data']['itxapi_username'] );
			$password_hash   = iThemes_Credentials::get_password_hash( $itxapi_username, $settings_result['data']['itxapi_password_raw'] );
			$access_token    = ITXAPI_Helper2::get_access_token( $itxapi_username, $password_hash, site_url(), $wp_version );

			$response = BackupBuddy_Stash_API::connect( $itxapi_username, $access_token );

			if ( ! is_array( $response ) ) { // Error message.
				pb_backupbuddy::alert( 'Error #23333: Unexpected server response. Check your login and try again. Detailed response: `' . print_r( $response, true ) . '`.' );
				$credentials_form->display_settings( 'Submit' );
			} else {
				if ( isset( $response['error'] ) ) {
					pb_backupbuddy::alert( 'Error: ' . $response['error']['message'] );
					$credentials_form->display_settings( 'Submit' );
				} else {
					if ( isset( $response['token'] ) ) {
						$itxapi_token = $response['token'];
					} else {
						pb_backupbuddy::alert( 'Error #382383232: Unexpected server response. Token missing. Check your login and try again. Detailed response: `' . print_r( $response, true ) . '`.' );
						$credentials_form->display_settings( 'Submit' );
					}
				}
			}
		}
	} // end form submitted.
} elseif ( 'edit' === $mode ) { // EDIT MODE.
	$settings        = array(
		'itxapi_username' => $destination_settings['itxapi_username'],
		'itxapi_token'    => $destination_settings['itxapi_token'],
	);
	$account_info    = pb_backupbuddy_destination_stash3::get_quota( $settings );
	$itxapi_username = $destination_settings['itxapi_username'];
}

if ( 'save' === $mode || 'edit' === $mode || '' != $itxapi_token ) {
	$default_name = null;

	if ( 'save' !== $mode && 'edit' !== $mode ) {
		$settings     = array(
			'itxapi_username' => $itxapi_username,
			'itxapi_token'    => $itxapi_token,
		);
		$account_info = pb_backupbuddy_destination_stash3::get_quota( $settings );

		if ( ! is_array( $account_info ) ) {
			$pb_hide_test = true;
			$pb_hide_save = true;
			return false;
		} else {
			$pb_hide_test = false;
			$pb_hide_save = false;
		}

		$account_details = esc_html__( 'Welcome to your BackupBuddy Stash', 'it-l10n-backupbuddy' ) .
			sprintf( ', <b>%s</b>. ', $itxapi_username ) .
			esc_html__( 'Your account is ', 'it-l10n-backupbuddy' );

		if ( '1' == $account_info['subscriber_locked'] ) {
			$account_details .= esc_html__( 'LOCKED', 'it-l10n-backupbuddy' );
		} elseif ( '1' == $account_info['subscriber_expired'] ) {
			$account_details .= esc_html__( 'EXPIRED', 'it-l10n-backupbuddy' );
		} elseif ( '1' == $account_info['subscriber_active'] ) {
			$account_details .= esc_html__( 'active', 'it-l10n-backupbuddy' );
		} else {
			$account_details .= esc_html__( 'Unknown', 'it-l10n-backupbuddy' );
		}
		$account_details .= '.';

		if ( 'add' === $mode ) {
			$default_name = 'My Stash (v3)';

			echo $account_details; // @codingStandardsIgnoreLine: ok.

			echo ' ' . esc_html__( 'To jump right in using the defaults just hit "Add Destination" below.', 'it-l10n-backupbuddy' );
		} else {
			echo '<div style="text-align: center;">' . $account_details . '</div>'; // @codingStandardsIgnoreLine: ok.
		}

		if ( 'add' === $mode ) {
			// Check to see if user already has a Stash with this username set up for this site. No need for multiple same account Stashes.
			foreach ( (array) pb_backupbuddy::$options['remote_destinations'] as $destination ) {
				if ( 'stash3' === $destination['type'] || 'stash' === $destination['type'] ) {
					if ( isset( $destination['itxapi_username'] ) && strtolower( $destination['itxapi_username'] ) === strtolower( $itxapi_username ) ) {
						echo '<br><br>';
						pb_backupbuddy::alert( 'Note: You already have a Stash destination set up under the provided iThemes account username.  It is unnecessary to create multiple Stash destinations that go to the same user account as they are effectively the same destination and a duplicate.' );
						break;
					}
				}
			}
		}

		echo '<br><br>';
		echo pb_backupbuddy_destination_stash3::get_quota_bar( $account_info );
		echo '<!-- STASH DETAILS: ' . print_r( $account_info, true ) . ' -->'; // TODO: Should this code be in production?

	} // end if NOT in save mode.

	// Form settings.
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
			'type'  => 'text',
			'name'  => 'full_archive_limit',
			'title' => __( 'Full backup limit', 'it-l10n-backupbuddy' ),
			'tip'   => __( '[Example: 5] - Enter 0 for no limit. This is the maximum number of Full (complete) backup archives to be stored in this specific destination. If this limit is met the oldest backup of this type will be deleted.', 'it-l10n-backupbuddy' ),
			'rules' => 'int[0-9999999]',
			'css'   => 'width: 50px;',
			'after' => ' backups. &nbsp;<span class="description">0 or blank for no limit.</span>',
		)
	);
	$settings_form->add_setting(
		array(
			'type'  => 'text',
			'name'  => 'db_archive_limit',
			'title' => __( 'Database only limit', 'it-l10n-backupbuddy' ),
			'tip'   => __( '[Example: 5] - Enter 0 for no limit. This is the maximum number of Database Only backup archives to be stored in this specific destination. If this limit is met the oldest backup of this type will be deleted.', 'it-l10n-backupbuddy' ),
			'rules' => 'int[0-9999999]',
			'css'   => 'width: 50px;',
			'after' => ' backups. &nbsp;<span class="description">0 or blank for no limit.</span>',
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
			'after' => ' backups. &nbsp;<span class="description">0 or blank for no limit.</span>',
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
			'after' => ' backups. &nbsp;<span class="description">0 or blank for no limit.</span>',
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
			'after' => ' backups. &nbsp;<span class="description">0 or blank for no limit.</span>',
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
			'after' => ' backups. &nbsp;<span class="description">0 or blank for no limit.</span>',
		)
	);

	$settings_form->add_setting(
		array(
			'type'      => 'title',
			'name'      => 'advanced_begin',
			'title'     => '<span class="dashicons dashicons-arrow-right"></span> ' . __( 'Advanced Options', 'it-l10n-backupbuddy' ),
			'row_class' => 'advanced-toggle-title',
		)
	);

	$settings_form->add_setting(
		array(
			'type'      => 'text',
			'name'      => 'max_burst',
			'title'     => __( 'Send per burst', 'it-l10n-backupbuddy' ),
			'tip'       => __( '[Example: 10] - This is the amount of data that will be sent per burst within a single PHP page load/chunk. Bursts happen within a single page load. Chunks occur when broken up between page loads/PHP instances. Reduce if hitting PHP memory limits. Chunking time limits will only be checked between bursts. Lower burst size if timeouts occur before chunking checks trigger.', 'it-l10n-backupbuddy' ),
			'rules'     => 'required|int[5-9999999]',
			'css'       => 'width: 50px;',
			'after'     => ' MB',
			'row_class' => 'advanced-toggle',
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
			'after'     => ' secs. <span class="description">' . __( 'Blank for detected default:', 'it-l10n-backupbuddy' ) . ' ' . backupbuddy_core::detectMaxExecutionTime() . ' sec</span>',
			'row_class' => 'advanced-toggle',
		)
	);
	$settings_form->add_setting(
		array(
			'type'      => 'checkbox',
			'name'      => 'ssl',
			'options'   => array(
				'unchecked' => '0',
				'checked'   => '1',
			),
			'title'     => __( 'Encrypt connection', 'it-l10n-backupbuddy' ) . '*',
			'tip'       => __( '[Default: enabled] - When enabled, all transfers will be encrypted with SSL encryption. Disabling this may aid in connection troubles but results in lessened security. Note: Once your files arrive on our server they are encrypted using AES256 encryption. They are automatically decrypted upon download as needed.', 'it-l10n-backupbuddy' ),
			'css'       => '',
			'after'     => '<span class="description"> ' . __( 'Enable connecting over SSL.', 'it-l10n-backupbuddy' ) . '<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;* Files are always encrypted with AES256 upon arrival.</span>',
			'rules'     => '',
			'row_class' => 'advanced-toggle',
		)
	);
	$settings_form->add_setting(
		array(
			'type'      => 'checkbox',
			'name'      => 'use_server_cert',
			'options'   => array(
				'unchecked' => '0',
				'checked'   => '1',
			),
			'title'     => __( 'Use system CA bundle', 'it-l10n-backupbuddy' ),
			'tip'       => __( '[Default: disabled] - When enabled, BackupBuddy will use your web server\'s certificate bundle for connecting to the server instead of BackupBuddy bundle. Use this if SSL fails due to SSL certificate issues.', 'it-l10n-backupbuddy' ),
			'css'       => '',
			'after'     => '<span class="description"> ' . __( 'Use webserver certificate bundle instead of BackupBuddy\'s.', 'it-l10n-backupbuddy' ) . '</span>',
			'rules'     => '',
			'row_class' => 'advanced-toggle',
		)
	);
	$settings_form->add_setting(
		array(
			'type'      => 'checkbox',
			'name'      => 'disable_hostpeer_verficiation',
			'options'   => array(
				'unchecked' => '0',
				'checked'   => '1',
			),
			'title'     => __( 'Disable SSL Verifications', 'it-l10n-backupbuddy' ),
			'tip'       => __( '[Default: disabled] - When enabled, the SSL host and peer information will not be verified. While the connection will still be encrypted SSL\'s man-in-the-middle protection will be voided. Disable only if you understand and if directed by support to work around host issues.', 'it-l10n-backupbuddy' ),
			'css'       => '',
			'after'     => '<span class="description"> ' . __( 'Check only if directed by support. Use with caution.', 'it-l10n-backupbuddy' ) . '</span>',
			'rules'     => '',
			'row_class' => 'advanced-toggle',
		)
	);
	$settings_form->add_setting(
		array(
			'type'      => 'checkbox',
			'name'      => 'debug_mode',
			'options'   => array(
				'unchecked' => '0',
				'checked'   => '1',
			),
			'title'     => __( 'Enable SDK debug mode', 'it-l10n-backupbuddy' ),
			'tip'       => __( '[Default: disabled] - When enabled, additional data will be logged by the SDK for troubleshooting.', 'it-l10n-backupbuddy' ),
			'css'       => '',
			'after'     => '<span class="description"> ' . __( 'Check if directed by support.', 'it-l10n-backupbuddy' ) . '</span>',
			'rules'     => '',
			'row_class' => 'advanced-toggle',
		)
	);
	if ( 'edit' !== $mode ) {
		$settings_form->add_setting(
			array(
				'type'      => 'checkbox',
				'name'      => 'disable_file_management',
				'options'   => array(
					'unchecked' => '0',
					'checked'   => '1',
				),
				'title'     => __( 'Disable file management', 'it-l10n-backupbuddy' ),
				'tip'       => __( '[Default: unchecked] - When checked, selecting this destination disables browsing or accessing files stored at this destination from within BackupBuddy.', 'it-l10n-backupbuddy' ),
				'css'       => '',
				'rules'     => '',
				'row_class' => 'advanced-toggle',
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
			'row_class' => 'advanced-toggle',
		)
	);
	$settings_form->add_setting(
		array(
			'type'    => 'hidden',
			'name'    => 'itxapi_username',
			'default' => $itxapi_username,
		)
	);
	$settings_form->add_setting(
		array(
			'type'    => 'hidden',
			'name'    => 'itxapi_token',
			'default' => $itxapi_token,
		)
	);
}
