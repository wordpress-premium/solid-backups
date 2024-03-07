<?php
/**
 * General Settings View file.
 *
 * @package BackupBuddy
 */

if ( ! is_admin() ) {
	die( 'Access Denied.' );
}
?>
<script type="text/javascript">
	function pb_backupbuddy_selectdestination( destination_id, destination_title, callback_data, delete_after, mode ) {
		window.location.href = '<?php echo esc_attr( pb_backupbuddy::page_url() ); ?>&custom=remoteclient&destination_id=' + parseInt( destination_id );
	}
</script>
<?php
/* BEGIN CONFIGURING PLUGIN SETTINGS FORM */

$settings_form = new pb_backupbuddy_settings( 'settings', '', 'tab=general', 350 );

$settings_form->add_setting(
	array(
		'type'  => 'title',
		'name'  => 'title_general',
		'title' => __( 'General', 'it-l10n-backupbuddy' ),
	)
);
$settings_form->add_setting(
	array(
		'type'  => 'password',
		'name'  => 'importbuddy_pass_hash',
		'title' => __( 'Importer password', 'it-l10n-backupbuddy' ),
		'tip'   => esc_html__( '[Example: ', 'it-l10n-backupbuddy' ) . wp_generate_password( 8, false, false ) . '] - ' . esc_html__( 'Required password for running the Importer import/migration script. This prevents unauthorized access when using this tool. You should not use your WordPress password here.', 'it-l10n-backupbuddy' ),
		'value' => $importbuddy_pass_dummy_text,
		'css'   => 'width: 120px;',
		'after' => '&nbsp;&nbsp; <span style="white-space: nowrap;">' . esc_html__( 'Confirm', 'it-l10n-backupbuddy' ) . ': <input style="width: 120px;" type="password" name="pb_backupbuddy_importbuddy_pass_hash_confirm" value="' . esc_attr( $importbuddy_pass_dummy_text ) . '"></span>',
	)
);

if ( true !== apply_filters( 'itbub_hide_custom_backup_directory_option', false ) ) {
	$settings_form->add_setting(
		array(
			'type'   => 'text',
			'name'   => 'backup_directory',
			'title'  => __( 'Custom local storage directory', 'it-l10n-backupbuddy' ),
			'tip'    => __( 'Leave blank for default. To customize, enter a full local path where all backup ZIP files will be saved to. This directory must have proper write and read permissions. Upon changing, any backups in the existing directory will be moved to the new directory. Note: This is only where local backups will be, not remotely stored backups. Remote storage is configured on the Remote Destinations page.', 'it-l10n-backupbuddy' ),
			'rules'  => '',
			'css'    => 'width: 250px;',
			'before' => '<span style="white-space: nowrap;">',
			'after'  => ' <p class="description">' . __( 'Blank for default', 'it-l10n-backupbuddy' ) . ':</p><span class="code" style="background: #EAEAEA; white-space: normal;">' . esc_html( backupbuddy_core::_getBackupDirectoryDefault() ) . '</span></span>',
		)
	);
}

$roles = get_editable_roles();
unset( $roles['administrator'] );
unset( $roles['editor'] );
unset( $roles['author'] );
unset( $roles['contributor'] );
unset( $roles['subscriber'] );

$roles_arr = array(
	'activate_plugins'     => __( 'Administrator (default)', 'it-l10n-backupbuddy' ),
	'moderate_comments'    => __( 'Editor [moderate_comments]', 'it-l10n-backupbuddy' ),
	'edit_published_posts' => __( 'Author [edit_published_posts]', 'it-l10n-backupbuddy' ),
	'edit_posts'           => __( 'Contributor [edit_posts]', 'it-l10n-backupbuddy' ),
);
if ( count( $roles ) > 0 ) {
	$roles_arr[''] = '----- Custom Roles: -----';
}
foreach ( $roles as $role_slug => $role ) {
	$roles_arr[ $role_slug ] = $role['name'];
}

$settings_form->add_setting(
	array(
		'type'    => 'select',
		'name'    => 'role_access',
		'title'   => __( 'Solid Backups access permission', 'it-l10n-backupbuddy' ),
		'options' => $roles_arr,
		'tip'     => __( '[Default: Administrator] - Allow other user levels to access Solid Backups. Use extreme caution as users granted access will have FULL access to Solid Backups and your backups, including remote destinations. This is a potential security hole if used improperly. Use caution when selecting any other user roles or giving users in such roles access. Not applicable to Multisite installations.', 'it-l10n-backupbuddy' ),
		'after'   => ' <p class="description">Use caution changing from "administrator".</p>',
		'rules'   => 'required',
	)
);

require_once '_email.php';

$settings_form->add_setting(
	array(
		'type'  => 'title',
		'name'  => 'title_archivestoragelimits',
		'title' => __( 'Local Archive Storage Limits', 'it-l10n-backupbuddy' ) . ' ' . pb_backupbuddy::tip( 'Prevent too many backups from piling up on your local server by setting limits.  Limits are applied in the order listed below.', '', false ),
	)
);

if ( 'settings' === pb_backupbuddy::_POST( 'pb_backupbuddy_' ) ) {
	if ( ! is_numeric( pb_backupbuddy::_POST( 'pb_backupbuddy_archive_limit_db' ) ) ) {
		pb_backupbuddy::alert( __( 'Archive limiting by database type must be a numerical value. Reset to zero.', 'it-l10n-backupbuddy' ) );
		$_POST['pb_backupbuddy_archive_limit_db'] = 0;
	} else {
		pb_backupbuddy::$options['archive_limit_db'] = pb_backupbuddy::_POST( 'pb_backupbuddy_archive_limit_db' );
	}
	if ( ! is_numeric( pb_backupbuddy::_POST( 'pb_backupbuddy_archive_limit_files' ) ) ) {
		pb_backupbuddy::alert( __( 'Archive limiting by files only type must be a numerical value. Reset to zero.', 'it-l10n-backupbuddy' ) );
		$_POST['pb_backupbuddy_archive_limit_files'] = 0;
	} else {
		pb_backupbuddy::$options['archive_limit_files'] = pb_backupbuddy::_POST( 'pb_backupbuddy_archive_limit_files' );
	}
}

$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'archive_limit_age',
		'title' => __( 'Age limit of local backups', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Example: 90] - Maximum age (in days) to allow your local archives to reach (remote archive limits are configured per destination on their respective settings pages). Any backups exceeding this age will be deleted as new backups are created. Changes to this setting take place once a new backup is made. Set to zero (0) for no limit.', 'it-l10n-backupbuddy' ),
		'rules' => 'required|int|int',
		'css'   => 'width: 50px;',
		'after' => ' days. <p class="description">0 for no limit.</p>',
	)
);
$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'archive_limit_full',
		'title' => __( 'Limit number of backups to keep by type', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Example: 10] - Maximum number of local archived backups to store (remote archive limits are configured per destination on their respective settings pages). Any new backups created after this limit is met will result in your oldest backup(s) being deleted to make room for the newer ones. Changes to this setting take place once a new backup is made. Set to zero (0) for no limit.', 'it-l10n-backupbuddy' ),
		'rules' => 'required|int|int',
		'css'   => 'width: 50px; margin-bottom: 10px;',
		'after' => ' Full backups<br><input type="text" name="pb_backupbuddy_archive_limit_db" value="' . pb_backupbuddy::$options['archive_limit_db'] . '" id="pb_backupbuddy_archive_limit_db" style="width: 50px; margin-bottom: 10px;"> Database backups<br><input type="text" class="" name="pb_backupbuddy_archive_limit_files" value="' . pb_backupbuddy::$options['archive_limit_files'] . '" id="pb_backupbuddy_archive_limit_files" style="width: 50px;"> Files only backups. <p class="description">0 for no limit.</p>',
	)
);
$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'archive_limit',
		'title' => __( 'Limit total number of local backups to keep', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Example: 10] - Maximum number of local archived backups to store (remote archive limits are configured per destination on their respective settings pages). Any new backups created after this limit is met will result in your oldest backup(s) being deleted to make room for the newer ones. Changes to this setting take place once a new backup is made. Set to zero (0) for no limit.', 'it-l10n-backupbuddy' ),
		'rules' => 'required|int|int',
		'css'   => 'width: 50px;',
		'after' => ' backups. <p class="description">0 for no limit.</p>',
	)
);
$settings_form->add_setting(
	array(
		'type'  => 'text',
		'name'  => 'archive_limit_size',
		'title' => __( 'Size limit of all local backups combined', 'it-l10n-backupbuddy' ),
		'tip'   => __( '[Example: 350] - Maximum size (in MB) to allow your total local archives to reach (remote archive limits are configured per destination on their respective settings pages). Any new backups created after this limit is met will result in your oldest backup(s) being deleted to make room for the newer ones. Changes to this setting take place once a new backup is made. Set to zero (0) for no limit. IMPORTANT: There is an additional safeguard limit for maximum local storage size which may be configured under Advanced Settings.', 'it-l10n-backupbuddy' ),
		'rules' => 'required|int|int',
		'css'   => 'width: 50px;',
		'after' => ' MB. <p class="description">0 for no limit.</p>',
	)
);

if ( is_multisite() ) {
	$settings_form->add_setting(
		array(
			'type'  => 'title',
			'name'  => 'title_multisite',
			'title' => __( 'Multisite', 'it-l10n-backupbuddy' ),
		)
	);
	$settings_form->add_setting(
		array(
			'type'    => 'checkbox',
			'name'    => 'multisite_export',
			'title'   => __( 'Allow individual site exports by administrators?', 'it-l10n-backupbuddy' ) . ' ' . pb_backupbuddy::video( '_oKGIzzuVzw', __( 'Multisite export', 'it-l10n-backupbuddy' ), false ),
			'options' => array(
				'unchecked' => '0',
				'checked'   => '1',
			),
			'tip'     => __( '[Default: disabled] - When enabled individual sites may be exported by Administrators of the individual site. Network Administrators always see this menu (notes with the words SuperAdmin in parentheses in the menu when only SuperAdmins have access to the feature).', 'it-l10n-backupbuddy' ),
			'rules'   => 'required',
			'after'   => '<span class="description"> ' . __( 'Check to extend Site Exporting functionality to subsite Administrators.', 'it-l10n-backupbuddy' ) . '</span>',
		)
	);
}

$profile = 0; // Defaults index.
$settings_form->add_setting(
	array(
		'type'  => 'title',
		'name'  => 'title_database',
		'title' => __( 'Database Defaults', 'it-l10n-backupbuddy' ),
	)
);
require_once pb_backupbuddy::plugin_path() . '/views/settings/_database.php';
$settings_form->add_setting(
	array(
		'type'  => 'title',
		'name'  => 'title_files',
		'title' => __( 'File & Directory Defaults', 'it-l10n-backupbuddy' ),
	)
);

require_once pb_backupbuddy::plugin_path() . '/views/settings/_files.php';

$process_result = $settings_form->process();// Handles processing the submitted form (if applicable).
$process_errors = isset( $process_result['errors'] ) ? (array) $process_result['errors'] : array();
if ( isset( $process_result['data'] ) ) { // This form was saved.
	require_once pb_backupbuddy::plugin_path() . '/destinations/live/live.php';
	if ( false !== backupbuddy_live::getLiveID() ) { // Only jump if Live is enabled.
		set_transient( 'backupbuddy_live_jump', array( 'daily_init', array() ), 60 * 60 * 48 ); // Tells Live process to restart from the beginning (if mid-process) so new settings apply.
	}
}
if ( 0 === count( $process_errors ) ) {
	$table_excludes = pb_backupbuddy::_POST( 'pb_backupbuddy_profiles#0#mysqldump_additional_excludes' );
	$table_excludes = backupbuddy_core::alert_core_table_excludes( explode( "\n", trim( $table_excludes ) ) );
	foreach ( $table_excludes as $table_exclude_id => $table_exclude ) {
		pb_backupbuddy::disalert( $table_exclude_id, '<span class="pb_label pb_label-important">' . esc_html__( 'Warning', 'it-l10n-backupbuddy' ) . '</span> ' . $table_exclude );
	}

	$file_excludes = pb_backupbuddy::_POST( 'pb_backupbuddy_profiles#0#excludes' );
	$file_excludes = backupbuddy_core::alert_core_file_excludes( explode( "\n", trim( $file_excludes ) ) );
	foreach ( $file_excludes as $file_exclude_id => $file_exclude ) {
		pb_backupbuddy::disalert( $file_exclude_id, '<span class="pb_label pb_label-important">' . esc_html__( 'Warning', 'it-l10n-backupbuddy' ) . '</span> ' . $file_exclude );
	}
}
$settings_form->set_value( 'importbuddy_pass_hash', $importbuddy_pass_dummy_text );

/* END CONFIGURING PLUGIN SETTINGS FORM */

$settings_form->display_settings( __( 'Save General Settings', 'it-l10n-backupbuddy' ), '<div class="solid-backups-form-buttons">', '</div>', 'button-no-ml' );
?>
<div class="buttons-right">
	<div>
		<?php pb_backupbuddy::enqueue_thickbox(); ?>
		<a title="<?php echo esc_attr( __( 'Import/Export Settings', 'it-l10n-backupbuddy' ) ); ?>" href="<?php echo pb_backupbuddy::ajax_url( 'importexport_settings' ); ?>&#038;TB_iframe=1&#038;width=640&#038;height=600" class="thickbox button button-secondary secondary-button">Import/Export Plugin Settings</a>
		<input type="button" id="reset-defaults-button" name="reset-default-button" value="<?php echo esc_attr( __( 'Reset Plugin Settings to Defaults', 'it-l10n-backupbuddy' ) ); ?>" class="button button-secondary secondary-button" />

		<?php // Reset Plugin Settings Modal. ?>
		<?php ob_start(); ?>
			<form method="post" action="<?php echo esc_attr( pb_backupbuddy::page_url() ); ?>">
				<?php pb_backupbuddy::nonce(); ?>
				<input type="hidden" name="reset_defaults" id="keep_destinations" value="<?php echo esc_attr( pb_backupbuddy::settings( 'slug' ) ); ?>" />
				<input type="submit" name="submit" value="<?php echo esc_attr( __( 'Reset Plugin Settings to Defaults', 'it-l10n-backupbuddy' ) ); ?>" id="reset_defaults" class="button button-secondary secondary-button" />
				<br>
				<label>
					<input type="checkbox" name="keep_destinations" value="1" checked> <?php esc_html_e( 'Keep remote destination settings', 'it-l10n-backupbuddy' ); ?>
				</label>
			</form>
		<?php $form = ob_get_clean(); ?>
		<?php $form = str_replace(array("\r", "\n"), '', $form); ?>
		<?php // End Reset Plugin Settings Modal. ?>
	</div>
	<script type="text/javascript">
		(function($) {
			$( document ).ready(function() {
				$('#reset-defaults-button').off('click').on('click', function (e) {
					e.preventDefault();
					$details = $('<div />').addClass('solid-settings-reset-plugin-settings').html( '<?php echo $form; ?>' ),
					BackupBuddy.Modal.open({
						title: '<?php echo esc_js( __('Reset Plugin Settings', 'it-l10n-backupbuddy') ); ?>',
						content: $details
					});
				});
			})
		}(jQuery));
	</script>
</div>
