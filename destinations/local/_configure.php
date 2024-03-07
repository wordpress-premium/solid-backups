<?php // Settings to display in a form for a user to configure.
$default_name = NULL;
if ( 'add' == $mode ) {
	$default_name = 'My Local';
}
$settings_form->add_setting( array(
	'type'		=>		'text',
	'name'		=>		'title',
	'title'		=>		__( 'Destination name', 'it-l10n-backupbuddy' ),
	'tip'		=>		__( 'Name of the new destination to create. This is for your convenience only.', 'it-l10n-backupbuddy' ),
	'rules'		=>		'required|string[1-45]',
	'default'	=>		$default_name,
) );



if ( pb_backupbuddy::_GET('add') != '' ) { // set default only when adding.
	$default_path = ABSPATH;
	pb_backupbuddy::alert( __( 'Note: Solid Backups by default stores all backups locally. This destination allows you to store an additional copy in a second location. To change the primary backup storage location on this server, navigate to Solid Backups -> Settings.', 'it-l10n-backupbuddy' ) );
} else {
	$default_path = '';
}
$settings_form->add_setting( array(
	'type'		=>		'text',
	'name'		=>		'path',
	'title'		=>		__( 'Local file path', 'it-l10n-backupbuddy' ),
	'tip'		=>		__( 'Provide the full path to the location\'s directory. This must map to the web location for the destination URL.', 'it-l10n-backupbuddy' ),
	'default'	=>		$default_path,
	'css'		=>		'width: 100%;',
	'rules'		=>		'required|string[1-500]',
) );


if ( pb_backupbuddy::_GET('add') != '' ) { // set default only when adding.
	$default_url = rtrim( site_url(), '/\\' ) . '/';
} else {
	$default_url = '';
}
$settings_form->add_setting( array(
	'type'		=>		'text',
	'name'		=>		'url',
	'title'		=>		__( 'Migration URL', 'it-l10n-backupbuddy' ),
	'tip'		=>		__( 'Enter the URL corresponding to the local destination path. This URL must lead to the location where files uploaded to this remote destination would end up. If the destination is in a subdirectory make sure to match it in the corresponding URL.', 'it-l10n-backupbuddy' ),
	'css'		=>		'width: 100%;',
	'default'	=>		$default_url,
	'classes'	=>		'migration_url',
	'rules'		=>		'string[0-500]',
	'after'     =>  '<br><span class="description">' . __( 'Optional, for migrations', 'it-l10n-backupbuddy' ) . '</span>',
) );

$settings_form->add_setting( array(
	'type'		=>		'text',
	'name'		=>		'archive_limit',
	'title'		=>		__( 'Archive limit', 'it-l10n-backupbuddy' ),
	'tip'		=>		__( '[Example: 5] - Enter 0 for no limit. This is the maximum number of archives to be stored in this specific destination. If this limit is met the oldest backups will be deleted.', 'it-l10n-backupbuddy' ),
	'rules'		=>		'required|int[0-9999999]',
	'css'		=>		'width: 50px;',
	'after'		=>		' backups',
) );



$settings_form->add_setting( array(
	'type'		=>		'title',
	'name'		=>		'advanced_begin',
	'title'		=>		'<span class="advanced-toggle-title-icon">' . pb_backupbuddy::$ui->get_icon( 'chevronleft' ) . '</span> ' . __( 'Advanced Options', 'it-l10n-backupbuddy' ),
	'row_class'	=>		'advanced-toggle-title',
) );



if ( ( $mode !== 'edit' ) || ( '0' == $destination_settings['disable_file_management'] ) ) {
	$settings_form->add_setting( array(
		'type'		=>		'checkbox',
		'name'		=>		'disable_file_management',
		'options'	=>		array( 'unchecked' => '0', 'checked' => '1' ),
		'title'		=>		__( 'Disable file management', 'it-l10n-backupbuddy' ),
		'tip'		=>		__( '[[Default: unchecked] - When checked, selecting this destination disables browsing or accessing files stored at this destination from within Solid Backups. NOTE: Once enabled this cannot be disabled without deleting and re-creating this destination. NOTE: Once enabled this cannot be disabled without deleting and re-creating this destination.', 'it-l10n-backupbuddy' ),
		'css'		=>		'',
		'rules'		=>		'',
		'after'		=>		__( 'Once disabled you must recreate the destination to re-enable.', 'it-l10n-backupbuddy' ),
		'row_class'	=>		'advanced-toggle advanced-toggle-hidden',
	) );
}
$settings_form->add_setting( array(
	'type'		=>		'checkbox',
	'name'		=>		'disabled',
	'options'	=>		array( 'unchecked' => '0', 'checked' => '1' ),
	'title'		=>		__( 'Disable destination', 'it-l10n-backupbuddy' ),
	'tip'		=>		__( '[Default: unchecked] - When checked, this destination will be disabled and unusable until re-enabled. Use this if you need to temporary turn a destination off but don\t want to delete it.', 'it-l10n-backupbuddy' ),
	'css'		=>		'',
	'after'		=>		'<span class="description"> ' . __('Check to disable this destination until re-enabled.', 'it-l10n-backupbuddy' ) . '</span>',
	'rules'		=>		'',
	'row_class'	=>		'advanced-toggle advanced-toggle-hidden',
) );
