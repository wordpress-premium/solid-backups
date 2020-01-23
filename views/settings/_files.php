<?php
/**
 * Files Settings View
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

?>
<script type="text/javascript">
	jQuery(function() {

		jQuery( '.pb_backupbuddy_filetree_exclude' ).click( function() {
			alert( 'Error #3484578347843873. Not implemented here. Deprecated.' );
		} );

		/* Begin Directory / File Selector */
		jQuery(document).on( 'click', '.pb_backupbuddy_filetree_exclude', function(){
			text = jQuery(this).parent().parent().find( 'a' ).attr( 'rel' );
			if ( ( text == '/wp-config.php' ) || ( text == '/backupbuddy_dat.php' ) || ( text == '/wp-content/' ) || ( text == '/wp-content/uploads/' ) || ( text == '<?php echo '/' . str_replace( ABSPATH, '', backupbuddy_core::getBackupDirectory() ); ?>' ) || ( text == '<?php echo '/' . str_replace( ABSPATH, '', backupbuddy_core::getTempDirectory() ); ?>' ) ) {
				alert( "<?php esc_html_e( 'You cannot exclude the selected file or directory.  However, you may exclude subdirectories within many directories restricted from exclusion. BackupBuddy directories such as backupbuddy_backups are automatically excluded, preventing backing up backups, and cannot be added to exclusion list.', 'it-l10n-backupbuddy' ); ?>" );
			} else {
				jQuery('#pb_backupbuddy_excludes').val( text + "\n" + jQuery('#pb_backupbuddy_excludes').val() );
			}
			return false;
		});

		jQuery( '.custom_root_input' ).keyup( function(){
			jQuery( '#custom_root-changed_notice' ).show();
		});

		if ( jQuery( 'input[name$="#exclude_plugins"]' ).is( ':checked' ) ) {
			jQuery( 'input[name$="#active_plugins_only"]' ).parents( 'tr:first' ).hide();
		}

		jQuery( 'input[name$="#exclude_plugins"]' ).on( 'click', function() {
			var $active_plugins_only = jQuery( 'input[name$="#active_plugins_only"]' );
			if ( ! $active_plugins_only.length ) {
				return true;
			}
			if ( jQuery( this ).is( ':checked' ) ) {
				$active_plugins_only.parents( 'tr:first' ).hide();
			} else {
				$active_plugins_only.parents('tr:first').show();
			}
		});

	});
</script>

<?php
global $filetree_root;
$abslen = strlen( ABSPATH );
if ( 'files' == $profile_array['type'] ) {
	if ( '' != $profile_array['custom_root'] ) {
		$filetree_root = rtrim( $profile_array['custom_root'], '\\/' ) . '/';
	} else {
		$filetree_root = '/';
	}
} elseif ( 'media' == $profile_array['type'] ) {
	$filetree_root = substr( backupbuddy_core::get_media_root(), $abslen - 1 ) . '/';
} elseif ( 'plugins' == $profile_array['type'] ) {
	$filetree_root = substr( backupbuddy_core::get_plugins_root(), $abslen - 1 ) . '/';
} elseif ( 'themes' == $profile_array['type'] ) {
	$filetree_root = substr( backupbuddy_core::get_themes_root(), $abslen - 1 ) . '/';
} else {
	$filetree_root = '/';
}
require_once '_filetree.php';

$before_text = __( 'Excluded files & directories for this profile', 'it-l10n-backupbuddy' );
if ( 'defaults' == $profile_array['type'] ) {
	$before_text = __( '<strong>Default</strong> excluded files & directories (relative to WordPress root)', 'it-l10n-backupbuddy' );
}

if ( 'files' == $profile_array['type'] ) {
	$settings_form->add_setting(
		array(
			'type'    => 'text',
			'name'    => 'profiles#' . $profile_id . '#custom_root',
			'classes' => 'custom_root_input',
			'title'   => __( 'Custom backup root path', 'it-l10n-backupbuddy' ),
			'tip'     => __( '[Default: blank] - If set then data will be backed up starting at the specified path, RELATIVE to your site\'s ABSPATH (WordPress root). You may use /../ to enter parent directories ABOVE the site\'s ABSPATH (WordPress root), eg: /../aparentdir/. IMPORTANT: If entering profile-specific exclusions these exclusions should also be entered relative to the custom root. Global exclusions are still relative to the ABSPATH and will automatically be translated to the relative path and applied.' ),
			'rules'   => '',
			'css'     => 'width: 100%;',
			'before'  => '<span style="white-space: nowrap;">',
			'after'   => '<br>Enter RELATIVE to you site ABSPATH:<br><div class="code" style="background: #EAEAEA; white-space: normal; width: 100%; overflow: auto;">' . ABSPATH . '</div><span id="custom_root-changed_notice" style="display: none; color: red;">Save Profile Settings to update directory exclusion picker.</span>',
		)
	);
}

if ( 'media' == $profile_array['type'] ) {
	$settings_form->add_setting(
		array(
			'type'   => 'plaintext',
			'name'   => 'profiles#' . $profile_id . '#mediadirtext',
			'title'  => __( 'Media Directory (profile backup root)', 'it-l10n-backupbuddy' ),
			'tip'    => __( 'The Media backup type is a "smart" profile which automatically selects your WordPress media directory root as its backup root to simplify backing up just your media.' ),
			'rules'  => '',
			'css'    => 'width: 100%;',
			'before' => '<span style="white-space: nowrap;">',
			'after'  => '<div class="code" style="background: #EAEAEA; white-space: normal; width: 100%; overflow: auto;">' . backupbuddy_core::get_media_root() . '</div>',
		)
	);
}

if ( 'plugins' == $profile_array['type'] ) {
	$settings_form->add_setting(
		array(
			'type'   => 'plaintext',
			'name'   => 'profiles#' . $profile_id . '#pluginsdirtext',
			'title'  => __( 'Plugins Directory (profile backup root)', 'it-l10n-backupbuddy' ),
			'tip'    => __( 'The Plugins backup type is a "smart" profile which automatically selects your WordPress plugins directory root as its backup root to simplify backing up just your media.' ),
			'rules'  => '',
			'css'    => 'width: 100%;',
			'before' => '<span style="white-space: nowrap;">',
			'after'  => '<div class="code" style="background: #EAEAEA; white-space: normal; width: 100%; overflow: auto;">' . backupbuddy_core::get_plugins_root() . '</div>',
		)
	);
}

if ( 'themes' == $profile_array['type'] ) {
	$settings_form->add_setting(
		array(
			'type'   => 'plaintext',
			'name'   => 'profiles#' . $profile_id . '#themesdirtext',
			'title'  => __( 'Themes Directory (profile backup root)', 'it-l10n-backupbuddy' ),
			'tip'    => __( 'The Themes backup type is a "smart" profile which automatically selects your WordPress themes directory root as its backup root to simplify backing up just your media.' ),
			'rules'  => '',
			'css'    => 'width: 100%;',
			'before' => '<span style="white-space: nowrap;">',
			'after'  => '<div class="code" style="background: #EAEAEA; white-space: normal; width: 100%; overflow: auto;">' . backupbuddy_core::get_themes_root() . '</div>',
		)
	);
}

if ( 'defaults' != $profile_array['type'] ) {
	$settings_form->add_setting(
		array(
			'type'    => 'checkbox',
			'name'    => 'profiles#' . $profile_id . '#profile_globalexcludes',
			'options' => array(
				'unchecked' => '0',
				'checked'   => '1',
			),
			'title'   => 'Use global exclusion defaults?',
			'after'   => ' Use global defaults<br><span class="description" style="padding-left: 25px;">Uncheck to customize exclusions.</span>',
			'css'     => '',
		)
	);
}

$settings_form->add_setting(
	array(
		'type'   => 'textarea',
		'name'   => 'profiles#' . $profile_id . '#excludes',
		'title'  => 'Hover & select to navigate, <img src="' . pb_backupbuddy::plugin_url() . '/images/redminus.png" style="vertical-align: -3px;"> to exclude. ' .
			pb_backupbuddy::tip( __( 'Click on a directory name to navigate directories. Click the red minus sign to the right of a directory to place it in the exclusion list. /wp-content/, the uploads directory, and BackupBuddy backup & temporary directories cannot be excluded. BackupBuddy directories are automatically excluded.', 'it-l10n-backupbuddy' ), '', false ) .
			'<br><div id="exlude_dirs" class="jQueryOuterTree"></div>',
		'rules'  => 'string[0-9000]',
		'css'    => 'width: 100%; height: 135px;',
		'before' => $before_text . pb_backupbuddy::tip( __( 'List paths relative to the WordPress installation directory to be excluded from backups.  You may use the directory selector to the left to easily exclude directories by ctrl+clicking them.  Paths are relative to root, for example: /wp-content/uploads/junk/. Three variables are also permitted to exclude common WordPress directories: {media}, {plugins}, {themes}', 'it-l10n-backupbuddy' ), '', false ) . '<br>',
		'after'  => '<span class="description">' . __( 'One exclusion per line. <b>Enter RELATIVE to your backup root.</b>', 'it-l10n-backupbuddy' ) . '</span>',
	)
);

if ( 'full' == $profile_array['type'] || 'files' == $profile_array['type'] ) {
	if ( 'full' != $profile_array['type'] ) {
		$settings_form->add_setting(
			array(
				'type'    => 'checkbox',
				'name'    => 'profiles#' . $profile_id . '#exclude_media',
				'options' => array(
					'unchecked' => '0',
					'checked'   => '1',
				),
				'title'   => 'Auto-exclude Media Directory',
				'css'     => '',
				'tip'     => __( 'When enabled the current WordPress Media directory will be automatically excluded from backups made with this profile, even if the directory changes in the future. Current directory:', 'it-l10n-backupbuddy' ) . ' ' . backupbuddy_core::get_media_root(),
			)
		);
	}
	$settings_form->add_setting(
		array(
			'type'    => 'checkbox',
			'name'    => 'profiles#' . $profile_id . '#exclude_themes',
			'options' => array(
				'unchecked' => '0',
				'checked'   => '1',
			),
			'title'   => 'Auto-exclude Themes Directory',
			'css'     => '',
			'tip'     => __( 'When enabled the current WordPress Themes directory will be automatically excluded from backups made with this profile, even if the directory changes in the future. Current directory:', 'it-l10n-backupbuddy' ) . ' ' . backupbuddy_core::get_themes_root(),
		)
	);
	$settings_form->add_setting(
		array(
			'type'    => 'checkbox',
			'name'    => 'profiles#' . $profile_id . '#exclude_plugins',
			'options' => array(
				'unchecked' => '0',
				'checked'   => '1',
			),
			'title'   => 'Auto-exclude Plugins Directory',
			'css'     => '',
			'tip'     => __( 'When enabled the current WordPress Plugins directory will be automatically excluded from backups made with this profile, even if the directory changes in the future. Current directory:', 'it-l10n-backupbuddy' ) . ' ' . backupbuddy_core::get_plugins_root(),
		)
	);
}

if ( in_array( $profile_array['type'], array( 'media', 'themes' ), true ) === false ) {
	$settings_form->add_setting(
		array(
			'type'    => 'checkbox',
			'name'    => 'profiles#' . $profile_id . '#active_plugins_only',
			'options' => array(
				'unchecked' => '0',
				'checked'   => '1',
			),
			'title'   => __( 'Active Plugins Only', 'it-l10n-backupbuddy' ),
			'tip'     => __( '[Default: disabled] - When enabled, only active plugins in the plugins folder will be backed up.', 'it-l10n-backupbuddy' ),
			'css'     => '',
			'after'   => '',
			'rules'   => '',
		)
	);
}
