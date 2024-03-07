<?php
/**
 * Configuration for sFTP
 *
 * @package BackupBuddy
 */

if ( 'save' != $mode ) {
	// Load filetree sources if not already loaded.
	pb_backupbuddy::load_script( 'filetree.js' );
	itbub_file_icon_styles( '6px 6px', true );
	?>
	<script>
		jQuery(function( $ ) {
			// Test a remote destination.
			$( '.pb_backupbuddy_sftpdestination_pathpicker' ).on( 'click', function() {

				$( '.pb_backupbuddy_sftpdestination_pathpickerboxtree' ).remove(); // Remove any current trees.
				$( '.pb_backupbuddy_sftppicker_load' ).show();

				var thisPickObj = $(this),
					pathPickerBox = thisPickObj.closest( 'form' ).find( '.pb_backupbuddy_sftpdestination_pathpickerbox' ),
					serializedFormData = thisPickObj.closest( 'form' ).serialize();

				// Get root FTP path.
				$.get( '<?php echo pb_backupbuddy::ajax_url( 'destination_sftp_pathpicker' ); ?>&' + serializedFormData,
					function( data ) {
						data = $.trim( data );
						pathPickerBox.html( '<div class="jQueryOuterTree" style="width: 100%;">' + data + '</div>' );
						pathPickerBox.slideDown();

						// File picker.
						$('.pb_backupbuddy_sftpdestination_pathpickerboxtree').fileTree(
							{
								root: '/',
								multiFolder: false,
								script: '<?php echo pb_backupbuddy::ajax_url( 'destination_sftp_pathpicker' ); ?>&' + serializedFormData
							},
							function(file) {
								alert( file );
							},
							function(directory) {
								thisPickObj.closest( 'form' ).find( '#pb_backupbuddy_path' ).val( directory );
							}
						);

						$( '.pb_backupbuddy_sftppicker_load' ).hide();
					}
				);

				return false;
			} );

			$(document).on('mouseover mouseout', '.pb_backupbuddy_sftpdestination_pathpickerboxtree > li a', function(event) {
				if ( event.type == 'mouseover' ) {
					$(this).children( '.pb_backupbuddy_treeselect_control' ).css( 'visibility', 'visible' );
				} else {
					$(this).children( '.pb_backupbuddy_treeselect_control' ).css( 'visibility', 'hidden' );
				}
			});

		});
	</script>
	<?php
}

$default_name = null;
if ( 'add' === $mode ) {
	$default_name = 'My sFTP';
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
		'type'  => 'text',
		'name'  => 'address',
		'title' => __( 'Server address', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Example: ftp.foo.com] - sFTP server address. Do not include http://, sftp://, ftp:// or any other prefixes. You may specify an alternate port in the format of ftp_address:port such as yourftp.com:22', 'it-l10n-backupbuddy' ),
		'rules' => 'required|string[0-500]',
		'css'   => 'width: 50%; max-width: 700px;',
	)
);

$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'username',
		'title' => __( 'Username', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Example: foo] - Username to use when connecting to the FTP server.', 'it-l10n-backupbuddy' ),
		'rules' => 'required|string[0-250]',
		'css'   => 'width: 250px;',
	)
);

ob_start();
require pb_backupbuddy::plugin_path() . '/destinations/sftp/views/key-upload.php';
$sftp_key_file = ob_get_clean();

$settings_form->add_setting(
	array(
		'type'  => 'password',
		'name'  => 'password',
		'title' => __( 'Password', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Example: 1234xyz] - Password to use when connecting to the FTP server. If using sFTP key file then this setting is for unlocking a password-protected key file.', 'it-l10n-backupbuddy' ),
		'rules' => 'string[0-250]',
		'after' => $sftp_key_file,
		'css'   => 'width: 250px;',
	)
);

// Don't give ability to browse to path if file management is disabled.
if ( ! empty( $destination_settings['disable_file_management'] ) ) {
	$browse_and_select = ' <span class="description">File Management Disabled.</span>';
} else {
	$browse_and_select = ' <span class="pb_backupbuddy_sftpdestination_pathpicker">
                                <a href="#" class="button button-secondary secondary-button" title="Browse sFTP Folders">Browse & Select sFTP Path</a>
                                <img class="pb_backupbuddy_sftppicker_load" style="vertical-align: -3px; margin-left: 5px; display: none;" src="' . pb_backupbuddy::plugin_url() . '/assets/dist/images/loading.gif" title="Loading... This may take a few seconds...">
                            </span>
                            <div class="pb_backupbuddy_sftpdestination_pathpickerbox" style="margin-top: 10px; display: none;">Loading...</div>';
}

$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'path',
		'title' => __( 'Remote path (optional)', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Example: /public_html/backups] - Remote path to place uploaded files into on the destination FTP server. Make sure this path is correct; if it does not exist Solid Backups will attempt to create it. No trailing slash is needed.', 'it-l10n-backupbuddy' ),
		'rules' => 'string[0-500]',
		'css'   => 'width: 300px;',
		'after' => $browse_and_select,
	)
);

$default_url = '';
/*
if ( pb_backupbuddy::_GET('add') != '' ) { // set default only when adding.
	$default_url = rtrim( site_url(), '/\\' ) . '/';
} else {
	$default_url = '';
}
*/

$settings_form->add_setting(
	array(
		'type'    => 'text',
		'name'    => 'url',
		'title'   => __( 'Migration URL', 'it-l10n-backupbuddy' ),
		'tip'     => __( 'Enter the URL corresponding to the FTP destination path. This URL must lead to the location where files uploaded to this remote destination would end up. If the destination is in a subdirectory make sure to match it in the corresponding URL.', 'it-l10n-backupbuddy' ),
		'css'     => 'width: 50%; max-width: 700px;',
		'default' => $default_url,
		'rules'   => 'string[0-100]',
		'after'   =>  '<br><span class="description">' . __( 'Optional, for migrations', 'it-l10n-backupbuddy' ) . '</span>',
	)
);

$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'archive_limit',
		'title' => __( 'Archive limit', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Example: 5] - Enter 0 for no limit. This is the maximum number of archives to be stored in this specific destination. If this limit is met the oldest backups will be deleted.', 'it-l10n-backupbuddy' ),
		'rules' => 'required|int[0-9999999]',
		'css'   => 'width: 50px;',
		'after' => ' backups',
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

if ( 'edit' !== $mode || '0' == $destination_settings['disable_file_management'] ) {
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
