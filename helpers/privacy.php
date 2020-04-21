<?php
/**
 * Privacy and personal information related functions
 *
 * @package BackupBuddy
 * @since 8.2.5.5
 */

// Register BackupBuddy Data Exporter.
add_filter( 'wp_privacy_personal_data_exporters', 'itbub_register_personal_data_exporter', 10 );

/**
 * Registers BackupBuddy Personal Data Exporter
 *
 * @param array $exporters  Existing Data Exporters.
 *
 * @return array  Modified exporters, with ours added in.
 */
function itbub_register_personal_data_exporter( $exporters ) {
	$exporters['backupbuddy'] = array(
		'exporter_friendly_name' => esc_html__( 'BackupBuddy', 'it-l10n-backupbuddy' ),
		'callback'               => 'itbub_personal_data_exporter',
	);
	return $exporters;
}

/**
 * Exports BackupBuddy Specific Data
 *
 * @todo  Break into multiple functions to avoid repetition.
 *
 * @param string $email_address  Email address to request data.
 * @param int    $page           Page Number for multiple passes.
 *
 * @return array  Including exported data items.
 */
function itbub_personal_data_exporter( $email_address, $page = 1 ) {
	$export_items = array();

	if ( ! class_exists( 'pb_backupbuddy' ) ) {
		return array(
			'data' => $export_items,
			'done' => true,
		);
	}

	$settings_data   = array();
	$settings_id     = false;
	$settings_fields = array(
		'email_notify_error'              => esc_html__( 'Error notification recipient(s)', 'it-l10n-backupbuddy' ),
		'email_notify_scheduled_start'    => esc_html__( 'Scheduled backup started email recipient(s)', 'it-l10n-backupbuddy' ),
		'email_notify_scheduled_complete' => esc_html__( 'Scheduled backup completed email recipient(s)', 'it-l10n-backupbuddy' ),
		'email_notify_send_finish'        => esc_html__( 'File destination send finished email recipient(s)', 'it-l10n-backupbuddy' ),
		'email_return'                    => esc_html__( 'Email return address', 'it-l10n-backupbuddy' ),
	);

	foreach ( $settings_fields as $setting => $label ) {
		$value = isset( pb_backupbuddy::$options[ $setting ] ) ? pb_backupbuddy::$options[ $setting ] : '';
		if ( stripos( $value, $email_address ) !== false ) {
			$settings_data[] = array(
				'name'  => $label,
				'value' => $email_address,
			);
			$settings_id     = 'backupbuddy_settings'; // TODO: Is there a better identifier value?
		}
	}

	$export_items[] = array(
		'group_id'    => 'backupbuddy_settings',
		'group_label' => __( 'BackupBuddy Settings', 'it-l10n-backupbuddy' ),
		'item_id'     => $settings_id,
		'data'        => $settings_data,
	);

	/**
	 * Ideally, this could be split into 2 or more separate hooks.
	 */

	$destinations_data   = array();
	$destinations_id     = false;
	$destinations_fields = array(
		'email/address'                => esc_html__( 'Email Destination Email Address', 'it-l10n-backupbuddy' ),
		'gdrive/service_account_email' => esc_html__( 'Google Drive Destination Service Account Email Address', 'it-l10n-backupbuddy' ),
		'ftp/username'                 => esc_html__( 'FTP Username', 'it-l10n-backupbuddy' ),
		'sftp/username'                => esc_html__( 'sFTP Username', 'it-l10n-backupbuddy' ),
	);

	foreach ( $destinations_fields as $id => $label ) {
		foreach ( pb_backupbuddy::$options['remote_destinations'] as $destination ) {
			list( $destination_type, $field_key ) = explode( '/', $id );
			if ( isset( $destination['type'] ) && $destination_type === $destination['type'] ) {
				if ( isset( $destination[ $field_key ] ) && stripos( $destination[ $field_key ], $email_address ) !== false ) {
					$destinations_data[] = array(
						'name'  => $label,
						'value' => $email_address,
					);
					$destinations_id     = 'backupbuddy_destinations'; // TODO: Is there a better identifier value?
				}
			}
		}
	}

	return array(
		'data' => $export_items,
		'done' => true,
	);
}

// Register BackupBuddy Data Eraser.
add_filter( 'wp_privacy_personal_data_erasers', 'itbub_register_personal_data_eraser', 10 );

/**
 * Registers BackupBuddy Data Eraser
 *
 * @param array $erasers  Existing Data Erasers.
 *
 * @return array  Modified erasers, with ours added in.
 */
function itbub_register_personal_data_eraser( $erasers ) {
	$erasers['backupbuddy'] = array(
		'eraser_friendly_name' => esc_html__( 'BackupBuddy', 'it-l10n-backupbuddy' ),
		'callback'             => 'itbub_personal_data_eraser',
	);

	return $erasers;
}

/**
 * Erase personal data from BackupBuddy based on Email Address
 *
 * @param string $email_address  Email address to remove data.
 * @param int    $page           Page Number for multiple passes.
 *
 * @return array  Formatted array with removal results.
 */
function itbub_personal_data_eraser( $email_address, $page = 1 ) {
	$items_removed  = 0;
	$items_retained = 0;
	$messages       = array();

	if ( ! class_exists( 'pb_backupbuddy' ) ) {
		return array(
			'items_removed'  => $items_removed,
			'items_retained' => false,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	$settings_need_update = false;

	$replacements = array(
		',' . $email_address,
		', ' . $email_address,
		' ' . $email_address,
		$email_address,
	);

	$settings_fields = array(
		'email_notify_error'              => esc_html__( 'Error notification recipient(s)', 'it-l10n-backupbuddy' ),
		'email_notify_scheduled_start'    => esc_html__( 'Scheduled backup started email recipient(s)', 'it-l10n-backupbuddy' ),
		'email_notify_scheduled_complete' => esc_html__( 'Scheduled backup completed email recipient(s)', 'it-l10n-backupbuddy' ),
		'email_notify_send_finish'        => esc_html__( 'File destination send finished email recipient(s)', 'it-l10n-backupbuddy' ),
		'email_return'                    => esc_html__( 'Email return address', 'it-l10n-backupbuddy' ),
	);

	foreach ( $settings_fields as $setting => $label ) {
		$value = isset( pb_backupbuddy::$options[ $setting ] ) ? pb_backupbuddy::$options[ $setting ] : '';
		if ( stripos( $value, $email_address ) !== false ) {
			pb_backupbuddy::$options[ $setting ] = str_ireplace( $replacements, '', pb_backupbuddy::$options[ $setting ] );
			$messages[]                          = esc_html__( 'BackupBuddy Setting value updated/cleared. Reason: Matched email address with ', 'it-l10n-backupbuddy' ) . $label;
			$items_removed++;
			$settings_need_update = true;
		}
	}

	if ( $settings_need_update ) {
		pb_backupbuddy::save();
	}

	if ( ! class_exists( 'pb_backupbuddy_destinations' ) ) {
		require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
	}

	$destinations_fields = array(
		'email/address'                => esc_html__( 'Email Destination Email Address', 'it-l10n-backupbuddy' ),
		'gdrive/service_account_email' => esc_html__( 'Google Drive Destination Service Account Email Address', 'it-l10n-backupbuddy' ),
		'ftp/username'                 => esc_html__( 'FTP Username', 'it-l10n-backupbuddy' ),
		'sftp/username'                => esc_html__( 'sFTP Username', 'it-l10n-backupbuddy' ),
	);

	foreach ( $destinations_fields as $id => $label ) {
		foreach ( pb_backupbuddy::$options['remote_destinations'] as $destination_id => $destination ) {
			list( $destination_type, $field_key ) = explode( '/', $id );
			if ( isset( $destination['type'] ) && $destination_type === $destination['type'] ) {
				if ( isset( $destination[ $field_key ] ) && stripos( $destination[ $field_key ], $email_address ) !== false ) {
					$destination_title = ! empty( $destination['title'] ) ? $destination['title'] . ' (' . esc_html__( 'BackupBuddy Destination', 'it-l10n-backupbuddy' ) . ')' : esc_html__( 'BackupBuddy Destination', 'it-l10n-backupbuddy' ) . ' (' . $destination['type'] . ')';
					if ( pb_backupbuddy_destinations::delete_destination( $destination_id, true ) ) {
						$messages[] = $destination_title . ' ' . esc_html__( 'has been removed. Reason: Matched email address with ', 'it-l10n-backupbuddy' ) . $label;
						$items_removed++;
					} else {
						$messages[] = esc_html__( 'An error occurred removing ', 'it-l10n-backupbuddy' ) . $destination_title;
						$items_retained++;
					}
				}
			}
		}
	}

	return array(
		'items_removed'  => $items_removed,
		'items_retained' => $items_retained,
		'messages'       => $messages,
		'done'           => true,
	);
}

// Add BackupBuddy Privacy Policy content.
add_action( 'admin_init', 'itbub_add_privacy_policy_content' );

/**
 * Insert BackupBuddy Privacy Policy Content.
 *
 * @todo  Stop this from loading unless on tools.php?wp-privacy-policy-guide.
 */
function itbub_add_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}

	$third_parties = array(
		array(
			'name'        => __( 'BackupBuddy Stash', 'it-l10n-backupbuddy' ),
			'privacy_url' => 'https://ithemes.com/privacy-policy/',
		),
		array(
			'name'        => __( 'BackupBuddy Deployment', 'it-l10n-backupbuddy' ),
			'privacy_url' => 'https://ithemes.com/privacy-policy/',
		),
		array(
			'name'        => __( 'Amazon S3', 'it-l10n-backupbuddy' ),
			'privacy_url' => 'https://aws.amazon.com/privacy/',
		),
		array(
			'name'        => __( 'Dropbox', 'it-l10n-backupbuddy' ),
			'privacy_url' => 'https://www.dropbox.com/en/privacy#privacy',
		),
		array(
			'name'        => __( 'Google Drive', 'it-l10n-backupbuddy' ),
			'privacy_url' => 'https://policies.google.com/privacy',
		),
		array(
			'name'        => __( 'Rackspace Cloud', 'it-l10n-backupbuddy' ),
			'privacy_url' => 'https://www.rackspace.com/en-us/information/legal/privacycenter',
		),
	);

	ob_start();
	include pb_backupbuddy::plugin_path() . '/views/privacy.php';
	$content = ob_get_clean();

	wp_add_privacy_policy_content(
		__( 'BackupBuddy', 'it-l10n-backupbuddy' ),
		wp_kses_post( $content )
	);
}
