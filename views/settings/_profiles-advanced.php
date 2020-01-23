<?php
/**
 * Profiles - Advanced Settings View
 *
 * IMPORTANT INCOMING VARIABLES (expected to be set before this file is loaded):
 *
 *   $profile  Index number of profile.
 *
 * @package BackupBuddy
 */

is_admin() || die( 'Access Denied.' );

if ( ! isset( pb_backupbuddy::$options['profiles'][ $profile ] ) ) {
	die( 'Error #565676756. Invalid profile ID index.' );
}

$profile_id    = $profile;
$profile_array = &pb_backupbuddy::$options['profiles'][ $profile ];
$profile_array = array_merge( pb_backupbuddy::settings( 'profile_defaults' ), $profile_array );

$settings_form->add_setting(
	array(
		'type'  => 'title',
		'name'  => 'title_advanced',
		'title' => __( 'Advanced', 'it-l10n-backupbuddy' ),
	)
);

if ( 'defaults' != $profile_array['type'] ) {
	$exclude_database_types = array(
		'files',
		'themes',
		'plugins',
		'media',
	);
	if ( ! in_array( pb_backupbuddy::$options['profiles'][ $profile ]['type'], $exclude_database_types ) ) {
		$settings_form->add_setting(
			array(
				'type'        => 'radio',
				'name'        => 'profiles#' . $profile_id . '#skip_database_dump',
				'options'     => array(
					'-1' => 'Use global default',
					'1'  => 'Skip',
					'0'  => 'Do not skip',
				),
				'title'       => __( 'Skip database dump on backup', 'it-l10n-backupbuddy' ),
				'tip'         => __( '[Default: disabled] - (WARNING: This prevents BackupBuddy from backing up the database during any kind of backup. This is for troubleshooting / advanced usage only to work around being unable to backup the database.', 'it-l10n-backupbuddy' ),
				'css'         => '',
				'rules'       => 'required',
				'orientation' => 'vertical',
			)
		);
	}

	$settings_form->add_setting(
		array(
			'type'        => 'radio',
			'name'        => 'profiles#' . $profile_id . '#integrity_check',
			'options'     => array(
				'-1' => 'Use global default',
				'0'  => 'Disable check (' . __( 'Disable if directed by support', 'it-l10n-backupbuddy' ) . ')',
				'1'  => 'Enable check',
			),
			'title'       => __( 'Perform integrity check on backup files', 'it-l10n-backupbuddy' ),
			'tip'         => __( '[Default: enabled] - WARNING: USE WITH CAUTION! By default each backup file is checked for integrity and completion the first time it is viewed on the Backup page.  On some server configurations this may cause memory problems as the integrity checking process is intensive.  This may also be useful if the backup page will not load.', 'it-l10n-backupbuddy' ),
			'css'         => '',
			'rules'       => 'required',
			'orientation' => 'vertical',
		)
	);

	$settings_form->add_setting(
		array(
			'type'    => 'select',
			'name'    => 'profiles#' . $profile_id . '#backup_mode',
			'title'   => __( 'Backup mode', 'it-l10n-backupbuddy' ),
			'options' => array(
				'-1' => __( 'Use global default', 'it-l10n-backupbuddy' ),
				'1'  => __( 'Classic (v1.x) - Entire backup in single PHP page load', 'it-l10n-backupbuddy' ),
				'2'  => __( 'Modern (v2.x+) - Split across page loads via WP cron', 'it-l10n-backupbuddy' ),
			),
			'tip'     => __( '[Default: Modern] - If you are encountering difficulty backing up due to WordPress cron, HTTP Loopbacks, or other features specific to version 2.x you can try classic mode which runs like BackupBuddy v1.x did.', 'it-l10n-backupbuddy' ),
			'rules'   => 'required',
		)
	);
}
