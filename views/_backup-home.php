<?php
/**
 * Backup Home View file
 *
 * Incoming variables: $backup from controllers/pages/_backup_home.php
 *
 * @package BackupBuddy
 */

if ( '1' == pb_backupbuddy::_GET( 'skip_quicksetup' ) ) {
	pb_backupbuddy::$options['skip_quicksetup'] = '1';
	pb_backupbuddy::save();
}

if ( '' != pb_backupbuddy::_GET( 'rollback' ) ) {
	require_once pb_backupbuddy::plugin_path() . '/controllers/pages/_rollback.php';
	return;
}

// Popup Quickstart modal if appears to be new install & quickstart not skip.
if (
	true !== apply_filters( 'itbub_hide_quickwizard', false )
	&&

	( ( '1' == pb_backupbuddy::_GET( 'wizard' ) )
	||
	(
		( '0' == pb_backupbuddy::$options['skip_quicksetup'] )
			&&
		( 0 == count( pb_backupbuddy::$options['schedules'] ) )
			&&
			( '' == pb_backupbuddy::$options['importbuddy_pass_hash'] )
		)
	) ) {
	pb_backupbuddy::$ui->title( 'BackupBuddy Quick Setup Wizard' );
	pb_backupbuddy::load_view( '_quicksetup', array() );
	return;
}

$restore_url  = '#restore-backup';
$dat_zip_file = str_replace( '\\', '', pb_backupbuddy::_GET( 'dat_viewer' ) );
$dat_zip_file = str_replace( '/', '', $dat_zip_file );

wp_enqueue_code_editor( array(
	'type'       => 'application/x-httpd-php',
	'codemirror' => array(
		'theme' => 'tomorrow-night-eighties',
	),
) );

$v = date( 'Ymdhis' ); // pb_backupbuddy::settings( 'version' ).
wp_register_style( 'backupbuddy-restore', pb_backupbuddy::plugin_url() . '/css/backupbuddy-restore.css', array(), $v );
pb_backupbuddy::load_style( 'backupbuddy-restore' );

if ( $dat_zip_file ) {
	$restore_url = admin_url( 'admin.php?page=pb_backupbuddy_backup' ) . $restore_url;
}

/**
 * Alerts and UI Tweaks.
 */
wp_enqueue_script( 'thickbox' );
wp_print_scripts( 'thickbox' );
wp_print_styles( 'thickbox' );


pb_backupbuddy::$ui->title( __( 'Backups', 'it-l10n-backupbuddy' ), true, false );

$adding_profile      = 'true' == pb_backupbuddy::_POST( 'add_profile' ); // Add new profile.
$total_backups       = count( backupbuddy_backups()->get_backups() );
$restore_in_progress = backupbuddy_restore()->in_progress( pb_backupbuddy::_GET( 'restore' ) );

if ( ! class_exists( 'BackupBuddy_Tabs' ) ) {
	require_once pb_backupbuddy::plugin_path() . '/classes/class-backupbuddy-tabs.php';
}

$restore_tab_class = 'restore-backup';
if ( $restore_in_progress ) {
	$restore_tab_class .= ' processing';
}

$tabs = new BackupBuddy_Tabs(
	array(
		array(
			'id'       => 'backups-main',
			'label'    => esc_html__( 'Local Backups', 'it-l10n-backupbuddy' ),
			'class'    => 'backups-main',
			'callback' => function() {
				backupbuddy_backups()->bulk_actions();
				backupbuddy_backups()->table();
			},
		),
		array(
			'id'       => 'create-backup',
			'label'    => esc_html__( 'Create Backup', 'it-l10n-backupbuddy' ),
			'class'    => 'create-backup',
			'callback' => function() use ( $adding_profile ) {
				require pb_backupbuddy::plugin_path() . '/views/backups/create-backup.php';
			},
		),
		array(
			'id'       => 'restore-backup',
			'label'    => esc_html__( 'Restore Backup', 'it-l10n-backupbuddy' ),
			'class'    => $restore_tab_class,
			'href'     => $restore_url,
			'callback' => function() use ( $restore_in_progress, $dat_zip_file ) {
				require pb_backupbuddy::plugin_path() . '/views/restore/restore-status.php';

				if ( $restore_in_progress ) {
					return;
				}

				if ( $dat_zip_file ) {
					require pb_backupbuddy::plugin_path() . '/controllers/pages/_dat_viewer.php';
					return;
				}

				backupbuddy_backups()->table( 'restore', false, array(
					'disable_wrapper' => false,
					'wrapper_class'   => 'restore-backups-table',
				) );

				require pb_backupbuddy::plugin_path() . '/views/restore/select-restore-type-modal.php';
			},
		),
	),
	array(
		'class'   => 'large',
		'between' => function() {
			// Quickwizard just completed.
			if ( '' != pb_backupbuddy::_GET( 'quickstart_wizard' ) ) {
				pb_backupbuddy::disalert( 'quickstart_wizard_finished', __( 'Quick Setup Wizard complete. Select a backup profile below to start backing up. See the <a href="admin.php?page=pb_backupbuddy_settings" target="_blank">Settings</a> page for all configuration options.', 'it-l10n-backupbuddy' ), false, '', array( 'class' => 'below-h2' ) );
			}
		},
	)
);

if ( ! $tabs->get_active_tab() ) {
	if ( $dat_zip_file ) {
		$tabs->set_active_tab( 'restore-backup' );
	} elseif ( ! $total_backups || $adding_profile ) {
		$tabs->set_active_tab( 'create-backup' );
	}
}

$tabs->render();

// Handles thickbox auto-resizing. Keep at bottom of page to avoid issues.
if ( ! wp_script_is( 'media-upload' ) ) {
	wp_enqueue_script( 'media-upload' );
	wp_print_scripts( 'media-upload' );
}
