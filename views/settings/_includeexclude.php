<?php
/**
 * Include/Exclude Settings View
 *
 * @package BackupBuddy
 */

is_admin() || die( 'Access Denied.' );

if ( ! is_numeric( pb_backupbuddy::_GET( 'profile' ) ) ) {
	die( 'Error #57434. Invalid profile ID index. Not numeric.' );
}

$profile = pb_backupbuddy::_GET( 'profile' );
if ( ! isset( pb_backupbuddy::$options['profiles'][ $profile ] ) ) {
	die( 'Error #565676756b. Invalid profile ID index.' );
}

// Defaults.
pb_backupbuddy::$options['profiles'][ $profile ] = array_merge( pb_backupbuddy::settings( 'profile_defaults' ), pb_backupbuddy::$options['profiles'][ $profile ] );
?>

<script type="text/javascript">
	var pb_settings_changed = false;

	jQuery(function() {

		jQuery( '.pb_form' ).change( function() {
			var win = window.dialogArguments || opener || parent || top;
			win.pb_settings_changed = true;
		});

		jQuery( '#pb_backupbuddy_profiles__<?php echo esc_html( $profile ); ?>__profile_globaltables' ).click( function() {
			if ( jQuery(this).is(':checked') ) {
				hide_tables();
			} else {
				jQuery(this).closest('tr').next('tr').show();
				jQuery(this).closest('tr').next('tr').next('tr').show();
				if ( jQuery( '#pb_backupbuddy_profiles__<?php echo esc_html( $profile ); ?>__mysqldump_additional_includes' ).val() == '-1' ) {
					jQuery( '#pb_backupbuddy_profiles__<?php echo esc_html( $profile ); ?>__mysqldump_additional_includes' ).val( '' );
				}
				if ( jQuery( '#pb_backupbuddy_profiles__<?php echo esc_html( $profile ); ?>__mysqldump_additional_excludes' ).val() == '-1' ) {
					jQuery( '#pb_backupbuddy_profiles__<?php echo esc_html( $profile ); ?>__mysqldump_additional_excludes' ).val( '' );
				}
			}
		});

		jQuery( '#pb_backupbuddy_profiles__<?php echo esc_html( $profile ); ?>__profile_globalexcludes' ).click( function() {
			if ( jQuery(this).is(':checked') ) {
				hide_excludes();
			} else {
				jQuery(this).closest('tr').next('tr').show();
				jQuery(this).closest('tr').next('tr').next('tr').show();
				if ( jQuery( '#pb_backupbuddy_profiles__<?php echo esc_html( $profile ); ?>__excludes' ).val() == '-1' ) {
					jQuery( '#pb_backupbuddy_profiles__<?php echo esc_html( $profile ); ?>__excludes' ).val( '' );
				}
			}
		});

	});

	function hide_tables() {
		jQuery( '#pb_backupbuddy_profiles__<?php echo esc_html( $profile ); ?>__profile_globaltables' ).closest('tr').next('tr').hide();
		jQuery( '#pb_backupbuddy_profiles__<?php echo esc_html( $profile ); ?>__profile_globaltables' ).closest('tr').next('tr').next('tr').hide();
	}
	function hide_excludes() {
		jQuery( '#pb_backupbuddy_profiles__<?php echo esc_html( $profile ); ?>__profile_globalexcludes' ).closest('tr').next('tr').hide();
	}

</script>

<style>
	table {
		font-size: 12px;
		line-height: 1.6em;
	}
	tr {
		margin: 0 !important;
		padding: 0 !important;
	}
</style>

<?php

$settings_form = new pb_backupbuddy_settings( 'profile_settings', '', 'action=pb_backupbuddy_backupbuddy&function=profile_settings&profile=' . $profile, 320 );

$settings_form->add_setting(
	array(
		'type'  => 'title',
		'name'  => 'title_type',
		'title' => backupbuddy_core::pretty_backup_type( pb_backupbuddy::$options['profiles'][ $profile ]['type'] ) . ' Profile',
	)
);

$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'profiles#' . $profile . '#title',
		'title' => __( 'Profile Name', 'it-l10n-backupbuddy' ),
		'tip'   => __( 'Enter a descriptive profile name for this profile for your use.', 'it-l10n-backupbuddy' ),
		'rules' => 'required|string[0-75]',
	)
);

// Database Settings.
$exclude_database = array(
	'files',
	'themes',
	'plugins',
	'media',
);
if ( ! in_array( pb_backupbuddy::$options['profiles'][ $profile ]['type'], $exclude_database ) ) {
	$settings_form->add_setting(
		array(
			'type'  => 'title',
			'name'  => 'title_database',
			'title' => __( 'Database', 'it-l10n-backupbuddy' ),
		)
	);
	require_once pb_backupbuddy::plugin_path() . '/views/settings/_database.php';
}

// Full / Files Settings.
if ( 'db' != pb_backupbuddy::$options['profiles'][ $profile ]['type'] ) {
	$settings_form->add_setting(
		array(
			'type'  => 'title',
			'name'  => 'title_files',
			'title' => __( 'Files & Directories', 'it-l10n-backupbuddy' ),
		)
	);
	require_once pb_backupbuddy::plugin_path() . '/views/settings/_files.php';
}

require_once pb_backupbuddy::plugin_path() . '/views/settings/_profiles-advanced.php';

// If global tables then set table includes & excludes to -1.
$field = 'pb_backupbuddy_profiles#' . $profile . '#profile_globaltables';
if ( isset( $_POST[ $field ] ) && '1' == $_POST[ $field ] ) {
	$_POST[ 'pb_backupbuddy_profiles#' . $profile . '#mysqldump_additional_includes' ] = '-1';
	$_POST[ 'pb_backupbuddy_profiles#' . $profile . '#mysqldump_additional_excludes' ] = '-1';
}

// If global excludes then set excludes to -1.
$field = 'pb_backupbuddy_profiles#' . $profile . '#profile_globalexcludes';
if ( isset( $_POST[ $field ] ) && '1' == $_POST[ $field ] ) {
	$_POST[ 'pb_backupbuddy_profiles#' . $profile . '#excludes' ] = '-1';
}

$process_result = $settings_form->process(); // Handles processing the submitted form (if applicable).
$process_errors = isset( $process_result['errors'] ) ? count( (array) $process_result['errors'] ) : 0;

if ( 0 === $process_errors && isset( $process_result['data'] ) && count( (array) $process_result['data'] ) > 0 ) {
	$excludes      = pb_backupbuddy::_POST( 'pb_backupbuddy_profiles#' . $profile . '#mysqldump_additional_excludes' );
	$file_excludes = backupbuddy_core::alert_core_file_excludes( explode( "\n", trim( $excludes ) ) );
	foreach ( $file_excludes as $file_exclude_id => $file_exclude ) {
		pb_backupbuddy::disalert( $file_exclude_id, '<span class="pb_label pb_label-important">Warning</span> ' . $file_exclude );
	}

	if ( count( $file_excludes ) === 0 ) {
		?>
		<script type="text/javascript">
			jQuery(document).ready(function() {
				var win = window.dialogArguments || opener || parent || top;
				win.pb_backupbuddy_profile_updated( '<?php echo esc_html( $profile ); ?>', '<?php echo htmlentities( pb_backupbuddy::$options['profiles'][ $profile ]['title'] ); ?>' );
				win.tb_remove();
			});
		</script>
		<?php
	}
}

$settings_form->display_settings( 'Save Profile Settings' );


if ( $profile > 2 ) {
?>
	<a style="float: right; margin-top: -35px; margin-right: 10px;" class="button secondary-button" title="Delete this Profile" href="admin.php?page=pb_backupbuddy_backup&delete_profile=<?php echo esc_attr( $profile ); ?>" target="_top" onclick="return confirm( 'Are you sure you want to delete this profile?' );">Delete Profile</a>
<?php } ?>


<script type="text/javascript">
	<?php
	if ( '1' == pb_backupbuddy::$options['profiles'][ $profile ]['profile_globaltables'] ) {
		echo "hide_tables();\n";
	}
	if ( '1' == pb_backupbuddy::$options['profiles'][ $profile ]['profile_globalexcludes'] ) {
		echo "hide_excludes();\n";
	}
	?>
</script>
