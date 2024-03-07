<?php
/**
 * Diagnostics Page
 *
 * @package BackupBuddy
 */

if ( defined( 'PB_IMPORTBUDDY' ) ) { // INSIDE IMPORTBUDDY.
	if ( '' == pb_backupbuddy::_GET( 'skip_serverinfo' ) ) { // Give a workaround to skip this.
		require_once 'server_info/server.php';
	} else {
		echo '{Skipping Server Info. section based on querystring.}';
	}
	return;
}

wp_enqueue_script( 'thickbox' );
wp_print_scripts( 'thickbox' );
wp_print_styles( 'thickbox' );

pb_backupbuddy::$ui->title( __( 'Diagnostics', 'it-l10n-backupbuddy' ), true, false );
backupbuddy_core::versions_confirm();

if ( ! class_exists( 'BackupBuddy_Tabs' ) ) {
	require_once pb_backupbuddy::plugin_path() . '/classes/class-backupbuddy-tabs.php';
}

$tabs = new BackupBuddy_Tabs(
	array(
		array(
			'id'       => 'server',
			'label'    => esc_html__( 'Server', 'it-l10n-backupbuddy' ),
			'callback' => function() {

				require_once pb_backupbuddy::plugin_path() . '/controllers/pages/server_info/server.php';
				require_once pb_backupbuddy::plugin_path() . '/controllers/pages/server_info/permissions.php';

				$wp_upload_dir = wp_upload_dir();
				$wp_settings   = array();

				if ( isset( $wp_upload_dir['path'] ) ) {
					$wp_settings[] = array( 'Upload File Path', $wp_upload_dir['path'], 'wp_upload_dir()' );
				}
				if ( isset( $wp_upload_dir['url'] ) ) {
					$wp_settings[] = array( 'Upload URL', $wp_upload_dir['url'], 'wp_upload_dir()' );
				}
				if ( isset( $wp_upload_dir['subdir'] ) ) {
					$wp_settings[] = array( 'Upload Subdirectory', $wp_upload_dir['subdir'], 'wp_upload_dir()' );
				}
				if ( isset( $wp_upload_dir['baseurl'] ) ) {
					$wp_settings[] = array( 'Upload Base URL', $wp_upload_dir['baseurl'], 'wp_upload_dir()' );
				}
				if ( isset( $wp_upload_dir['basedir'] ) ) {
					$wp_settings[] = array( 'Upload Base Directory', $wp_upload_dir['basedir'], 'wp_upload_dir()' );
				}
				$wp_settings[] = array( 'Site URL', site_url(), 'site_url()' );
				$wp_settings[] = array( 'Home URL', home_url(), 'home_url()' );
				$wp_settings[] = array( 'WordPress Root Path', ABSPATH, 'ABSPATH' );

				// Multisite extras.
				$wp_settings_multisite = array();
				if ( is_multisite() ) {
					$wp_settings[] = array( 'Network Site URL', network_site_url(), 'network_site_url()' );
					$wp_settings[] = array( 'Network Home URL', network_home_url(), 'network_home_url()' );
				}

				$wp_settings[] = array( 'Solid Backups local storage', esc_html( backupbuddy_core::getBackupDirectory() ), 'Solid Backups Settings' );
				$wp_settings[] = array( 'Solid Backups temporary files', backupbuddy_core::getTempDirectory(), 'ABSPATH + Hardcoded location' );
				$wp_settings[] = array( 'Solid Backups logs', backupbuddy_core::getLogDirectory(), 'Upload Base + Solid Backups' );

				$wp_settings[] = array( 'Themes root', backupbuddy_core::get_themes_root(), 'backupbuddy_core::get_themes_root()' );
				$wp_settings[] = array( 'Plugins root', backupbuddy_core::get_plugins_root(), 'backupbuddy_core::get_plugins_root()' );
				$wp_settings[] = array( 'Media root', backupbuddy_core::get_media_root(), 'backupbuddy_core::get_media_root()' );

				// Display WP settings.
				pb_backupbuddy::$ui->list_table(
					$wp_settings,
					array(
						'action'  => pb_backupbuddy::page_url(),
						'columns' => array(
							__( 'URLs & Paths', 'it-l10n-backupbuddy' ),
							__( 'Value', 'it-l10n-backupbuddy' ),
							__( 'Obtained via', 'it-l10n-backupbuddy' ),
						),
						'css'     => 'width: 100%;',
					)
				);
			},
			'after'    => function() {
				// This page can take a bit to run.
				// Runs AFTER server information is displayed so we can view the default limits for the server.
				pb_backupbuddy::set_greedy_script_limits();
			},
		),
		array(
			'id'       => 'database',
			'label'    => esc_html__( 'Database', 'it-l10n-backupbuddy' ),
			'callback' => function() {
				require_once 'server_info/database.php';
				echo '<br><br><a name="database_replace"></a>';
				echo '<div class="pb_htitle">Advanced: ' . esc_html__( 'Database Mass Text Replacement', 'it-l10n-backupbuddy' ) . '</div><br>';
				pb_backupbuddy::load_view( '_diagnostics-database_replace' );
			},
		),
		array(
			'id'       => 'files',
			'label'    => esc_html__( 'Size Maps', 'it-l10n-backupbuddy' ),
			'callback' => function() {
				require_once pb_backupbuddy::plugin_path() . '/controllers/pages/server_info/site_size.php';
			},
		),
		array(
			'id'       => 'cron',
			'label'    => esc_html__( 'Cron', 'it-l10n-backupbuddy' ),
			'callback' => function() {
				require_once pb_backupbuddy::plugin_path() . '/controllers/pages/server_info/cron.php';
			},
		),
		array(
			'id'       => 'recent',
			'label'    => esc_html__( 'Recent Actions', 'it-l10n-backupbuddy' ),
			'callback' => function() {
				require_once pb_backupbuddy::plugin_path() . '/views/recent-backups.php';
				require pb_backupbuddy::plugin_path() . '/views/restore/restore-queue.php';
				require_once pb_backupbuddy::plugin_path() . '/controllers/pages/server_info/remote_sends.php';
			},
		),
		array(
			'id'       => 'activity',
			'label'    => esc_html__( 'Activity History', 'it-l10n-backupbuddy' ),
			'callback' => function() {
				require_once pb_backupbuddy::plugin_path() . '/views/diagnostics/_activity.php';
			},
		),
		array(
			'id'       => 'other',
			'label'    => esc_html__( 'Troubleshooting', 'it-l10n-backupbuddy' ),
			'callback' => function() {
				pb_backupbuddy::flush(); // Flush before we start loading in the log.
				require_once pb_backupbuddy::plugin_path() . '/views/diagnostics/_other.php';
			},
		),
	)
);

$tabs->render();

if ( 'other' === $tabs->get_active_tab() ) {
	// Trigger log download since tab switch event isn't fired.
	echo '<script>jQuery( function(){ BackupBuddy.Diagnostics.load_logs(); } );</script>';
}

// Handles thickbox auto-resizing. Keep at bottom of page to avoid issues.
if ( ! wp_script_is( 'media-upload' ) ) {
	wp_enqueue_script( 'media-upload' );
	wp_print_scripts( 'media-upload' );
}
