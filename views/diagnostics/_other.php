<?php
/**
 * Other Settings View
 *
 * @package BackupBuddy
 */

// User forced cleanup.
if ( '' != pb_backupbuddy::_GET( 'cleanup_now' ) ) {
	printf( '<h3>%s</h3>', esc_html__( 'Cleanup Procedure Status Log', 'it-l10n-backupbuddy' ) );
	global $pb_backupbuddy_js_status;
	$pb_backupbuddy_js_status = true;
	echo pb_backupbuddy::status_box( 'Performing cleanup procedures...' );
	echo '<script>jQuery("#pb_backupbuddy_status_wrap").show();</script>';
	echo '<div id="pb_backupbuddy_cleanup_working"><img src="' . pb_backupbuddy::plugin_url() . '/assets/dist/images/loading_large.gif" title="Working... Please wait as this may take a moment..."></div>';

	pb_backupbuddy::flush();

	require_once pb_backupbuddy::plugin_path() . '/classes/housekeeping.php';

	if ( 'true' == pb_backupbuddy::_GET( 'transients_only' ) ) {
		backupbuddy_housekeeping::cleanup_transients( true ); // true deletes unexpiring, expired, or corrupt.
	} elseif ( 'true' == pb_backupbuddy::_GET( 'transients_only_expired' ) ) {
		backupbuddy_housekeeping::cleanup_transients( false ); // false only cleans expired or corrupt.
	} else {
		backupbuddy_housekeeping::run_periodic( 0 ); // 0 cleans up everything even if not very old.
	}

	echo '<script>jQuery( "#pb_backupbuddy_cleanup_working" ).hide();';
	echo 'jQuery( function(){
			backupbuddy_log( "Cleanup Completed!" );
		});';
	echo '</script>';
}

if ( 'true' === pb_backupbuddy::_GET( 'delete_dat_files' ) ) {
	$result = backupbuddy_data_file()->delete_local_dats();
	if ( is_array( $result ) ) {
		$error_count = count( $result );
		foreach( $result as $file ) {
			$text = 'An error occurred when attempting to delete this following .dat file: ' . $file;
			pb_backupbuddy::log( $text );
		}

		pb_backupbuddy::alert(
			'An error occurred when attempting to delete ' . $error_count . ' file(s). These files have been added to the Backups log. Please confirm whether these files exist or reach out to support.',
		true
		);
	} else {
		pb_backupbuddy::alert( 'All .dat files have been deleted. ' . $result . ' file(s) were removed.' );
	}
}

// Delete temporary files directory.
if ( '' != pb_backupbuddy::_GET( 'delete_tempfiles_now' ) ) {
	backupbuddy_core::deleteAllDataFiles();
}

// Reset disalerts.
if ( '' != pb_backupbuddy::_GET( 'reset_disalerts' ) ) {
	pb_backupbuddy::$options['disalerts'] = array();
	pb_backupbuddy::save();

	pb_backupbuddy::alert( 'Dismissed alerts have been reset. They may now be visible again.' );
}

// Cancel all running backups.
if ( '1' == pb_backupbuddy::_GET( 'cancel_running_backups' ) ) {
	pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #383...' );
	require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';

	$fileoptions_directory = backupbuddy_core::getLogDirectory() . 'fileoptions/';
	$files                 = glob( $fileoptions_directory . '*.txt' );
	if ( ! is_array( $files ) ) {
		$files = array();
	}
	$cancelled = array();
	for ( $x = 0; $x <= 3; $x++ ) { // Try this a few times since there may be race conditions on an open file.
		foreach ( $files as $file ) {
			$backup_options = new pb_backupbuddy_fileoptions( $file, false );
			$result         = $backup_options->is_ok();
			if ( true !== $result ) {
				pb_backupbuddy::status( 'error', 'Error retrieving fileoptions file `' . $file . '`. Err 335353266.' );
			} else {
				if ( empty( $backup_options->options['serial'] ) || is_null( $backup_options->options['serial'] ) ) {
					continue; // txt file is old or invalid.
				}
				if ( in_array( $backup_options->options['serial'], $cancelled, true ) ) {
					continue; // Already cancelled this pass.
				}
				if ( ! empty( $backup_options->options['finish_time'] ) && $backup_options->options['finish_time'] > 0 ) {
					continue; // Backup is already done.
				}
				if ( ! empty( $backup_options->options['finish_time'] ) && '-1' == $backup_options->options['finish_time'] ) {
					continue; // Backup already cancelled.
				}

				$backup_options->options['finish_time'] = -1; // Force marked as cancelled by user.
				$backup_options->save();
				$cancelled[] = $backup_options->options['serial'];
			}
		}
		sleep( 1 );
	}

	require_once pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php';
	$live_cancelled = backupbuddy_live_periodic::remove_pending_events();
	$cancelled      = count( $cancelled );
	$cancelled      += $live_cancelled;

	if ( ! empty( $cancelled ) ) {
		pb_backupbuddy::alert( 'Marked all timed out or running backups & transfers as officially cancelled (' . $cancelled . ' total found).', false, '', '', '', array( 'class' => 'below-h2' ) );
	} else {
		pb_backupbuddy::alert( 'No timed out or running backups found to cancel.', false, '', '', '', array( 'class' => 'below-h2' ) );
	}

	// @todo is line this still necessary?
	require_once pb_backupbuddy::plugin_path() . '/classes/housekeeping.php';

	backupbuddy_core::delete_events( array( 'status' => ActionScheduler_Store::STATUS_PENDING ) );
	pb_backupbuddy_schedule_housekeeping();
}
?>

<div class="backupbuddy-help-troubleshooting">
	<h1><?php esc_html_e( 'Troubleshooting', 'it-l10n-backupbuddy' ); ?></h1>

	<p><?php esc_html_e( 'Solid Backups automatically cleans up after itself on a regular basis. On this screen you can trigger various cleanup procedures or troubleshoot using the tools below.', 'it-l10n-backupbuddy' ); ?></p>

<?php
$cleanup_rows = [
	[
		'title'    => __( 'Regular Housekeeping', 'it-l10n-backupbuddy' ),
		'info'     => __( 'Housekeeping cleans up unneeded temporary data files and confirms all settings are up-to-date.<br>This happens regularly on its own, but you can force it to run now.', 'it-l10n-backupbuddy' ),
		'button'   => __( 'Perform Housekeeping', 'it-l10n-backupbuddy' ),
		'url_args' => [
			'cleanup_now' => 'true',
		],
	],
	[
		'title'    => __( 'Delete All Temporary Data Files', 'it-l10n-backupbuddy' ),
		'info'     => __( 'Deletes all temporary data files.<br>These are used to track the progress of Solid Backups processes.<br>Deleting these will also force Stash Live to start over.', 'it-l10n-backupbuddy' ),
		'button'   => __( 'Delete All Temp Files', 'it-l10n-backupbuddy' ),
		'url_args' => [
			'delete_tempfiles_now' => 'true',
		],
	],
];

$transients_rows = [
	[
		'title'    => __( 'Clean Up Transients (Basic)', 'it-l10n-backupbuddy' ),
		'info'     => __( 'Deletes all WordPress transients (corrupt, and expiring only)', 'it-l10n-backupbuddy' ),
		'button'   => __( 'Cleanup Transients (Basic)', 'it-l10n-backupbuddy' ),
		'url_args' => [
			'cleanup_now'             => 'true',
			'transients_only_expired' => 'true',
		],

	],
	[
		'title'    => esc_html__( 'Clean Up Transients (Advanced)', 'it-l10n-backupbuddy' ),
		'info'     => __( 'Deletes all WordPress transients (corrupt, expring, and non-expiring)', 'it-l10n-backupbuddy' ),
		'button'   => __( 'Cleanup Transients (Advanced)', 'it-l10n-backupbuddy' ),
		'url_args' => [
			'cleanup_now'     => 'true',
			'transients_only' => 'true',
		],

	],
];

$misc_rows = [
	[
		'title'    => sprintf(
			// translators: %s: number of dismissed alerts.
			__( 'Reset Dismissed Alerts (%s)', 'it-l10n-backupbuddy' ),
			count( pb_backupbuddy::$options['disalerts'] )
		),
		'info'     => __( 'Resets dismissed Solid Backups notifications and alerts', 'it-l10n-backupbuddy' ),
		'button'   => __( 'Reset Alerts', 'it-l10n-backupbuddy' ),
		'url_args' => [
			'reset_disalerts' => 'true',
		],

	],
];

$dat_file_row = [
	'title'  => __( 'Delete Orphan .dat Files', 'it-l10n-backupbuddy' ),
	'info'   => sprintf(
		// translators: %s: URL to advanced settings.
		__( 'Cleans up .dat files that do not have an associated backup .zip file.<br>Solid Backups uses .dat files for easy access to information about a backup .zip file.<br><strong>These are very important</strong>, but not essential.<br>You can disable future .dat file creation in the <a href="%s">Advanced Settings</a>.', 'it-l10n-backupbuddy' ),
		admin_url( 'admin.php?page=pb_backupbuddy_settings&tab=advanced' )
	),
	'icon'   => 'warning',
	'button' => __( 'Delete Orphan .dat Files', 'it-l10n-backupbuddy' ),
	'url_args' => [
		'delete_dat_files' => 'true',
	],

];

// Present different messaging if .dat file creation is disabled.
if ( backupbuddy_data_file()::creation_is_disabled() ) {
	$dat_file_row['title']  = __( 'Delete All Local .dat Files', 'it-l10n-backupbuddy' );
	$dat_file_row['info']   = __( 'Deletes all local .dat files.<br><strong>These are very important</strong>, but not essential.<br />Without them, Solid Backups cannot detect the backup .zip files, but the .zips will still be created.', 'it-l10n-backupbuddy' );
	$dat_file_row['button'] = __( 'Delete All .dat Files', 'it-l10n-backupbuddy' );
}

$caution_rows = [
	$dat_file_row,
	[
		'title'    => __( 'Force Cancel All Backups & Transfers', 'it-l10n-backupbuddy' ),
		'info'     => __( 'Cancels all runnning Backups and Transfers', 'it-l10n-backupbuddy' ),
		'icon'     => 'warning',
		'button'   => __( 'Cancel Backups/Transfers', 'it-l10n-backupbuddy' ),
		'url_args' => [
			'cancel_running_backups' => '1',
		],

	],
];

// @todo Move this.
function solid_backups_render_troubleshooting_table( array $items ) {
	$rows = array_map( function( $item ) {
		$icon = '';
		if ( isset( $item['icon'] ) ) {
			$icon = pb_backupbuddy::$ui->get_icon( $item['icon'] );
		}
		$url_args = ! empty( $item['url_args'] ) ? $item['url_args'] : [];
		$url_args = array_merge(
			$url_args,
			[
				'tab' => 'other',
			]
		);
		$url = add_query_arg( $url_args, pb_backupbuddy::page_url() );
		return [
			esc_html( $item['title'] ),
			wp_kses_post( $item['info'] ),
			'<a href="' . esc_url( $url ) . '" class="button button_scondary button-no-ml button-no-mr secondary-button">' . $icon . ' ' . esc_html( $item['button'] ) . '</a>',
		];
	}, $items );

	pb_backupbuddy::$ui->list_table(
		$rows,
		array(
			'css'           => 'width: 100%; max-width: 1200px;',
			'disable_tfoot' => true,
			'columns'       => array(
				__( 'Title', 'it-l10n-backupbuddy' ),
				__( 'Info', 'it-l10n-backupbuddy' ),
				__( 'Action', 'it-l10n-backupbuddy' ),
			),
		)
	);
}
?>
<div class="backupbuddy-cleanup-controls">
		<h3><?php esc_html_e( 'Cleanup', 'it-l10n-backupbuddy' ); ?></h3>
		<?php solid_backups_render_troubleshooting_table( $cleanup_rows ) ?>
</div>

<div class="backupbuddy-transient-controls">
		<h3><?php esc_html_e( 'Transients', 'it-l10n-backupbuddy' ); ?></h3>
		<?php solid_backups_render_troubleshooting_table( $transients_rows ) ?>
</div>

<div class="backupbuddy-misc-controls">
		<h3><?php esc_html_e( 'Miscellaneous', 'it-l10n-backupbuddy' ); ?></h3>
		<?php solid_backups_render_troubleshooting_table( $misc_rows ) ?>
</div>
<div class="backupbuddy-caution-controls">
		<h3><?php esc_html_e( 'Use Caution', 'it-l10n-backupbuddy' ); ?></h3>
		<p><?php esc_html_e( 'Use these actions with caution, and generally only if directed by Support.', 'it-l10n-backupbuddy' ); ?></p>
		<?php solid_backups_render_troubleshooting_table( $caution_rows ) ?>
</div>
	<div class="backupbuddy-misc-controls">
		<h3><?php esc_html_e( 'Logs', 'it-l10n-backupbuddy' ); ?></h3>
		<a href="#extraneous-log" class="button button-secondary button-no-ml secondary-button"><?php esc_html_e( 'Show Extraneous Log', 'it-l10n-backupbuddy' ); ?></a>
		&nbsp;
		<a href="#remoteapi-log" class="button button-secondary button-no-ml secondary-button"><?php esc_html_e( 'Show Remote API Log (incoming calls)', 'it-l10n-backupbuddy' ); ?></a>
	</div>

	<div id="backupbuddy-extra-log" style="display: none;">
		<h3><?php esc_html_e( 'Extraneous Log - Do not send to support unless asked', 'it-l10n-backupbuddy' ); ?></h3>
		<?php echo wp_kses_post(
			sprintf(
				__( '<strong>Anything logged here is typically not important. Only provide to tech support if specifically requested.</strong> By default only errors are logged. Enable Full Logging on the <a href="%1$s">Advanced Settings</a> tab.', 'it-l10n-backupbuddy' ),
				'?page=pb_backupbuddy_settings&tab=advanced'
			)
		); ?>
		<textarea class="solid-textarea" readonly="readonly" data-log-file="main" style="width: 100%;" wrap="off" cols="65" rows="7" id="backupbuddy_logFile">'<?php esc_html_e( '*** Loading log file. Please wait ..', 'it-l10n-backupbuddy' ); ?></textarea>
		<a href="#reset-log" class="button button-secondary button-no-ml secondary-button" data-log="main"><?php esc_html_e( 'Clear Log', 'it-l10n-backupbuddy' ); ?></a>
		<a href="#hide" class="button button-secondary secondary-button" data-log="main"><?php esc_html_e( 'Hide', 'it-l10n-backupbuddy' ); ?></a>
	</div>

	<div id="backupbuddy-remoteapi-log" style="display: none;">
		<h3><?php esc_html_e( 'Remote API Log (incoming calls)', 'it-l10n-backupbuddy' ); ?></h3>
		<textarea class="solid-textarea" readonly="readonly" data-log-file="remote" style="width: 100%;" wrap="off" cols="65" rows="7" id="backupbuddy_remoteapi_logFile"><?php esc_html_e( '*** Loading log file. Please wait...', 'it-l10n-backupbuddy' ); ?></textarea>
		<a href="#reset-log" data-log="remote" class="button button-secondary button-no-ml secondary-button"><?php esc_html_e( 'Clear Log', 'it-l10n-backupbuddy' ); ?></a>
		<a href="#hide" class="button button-secondary secondary-button" data-log="remote"><?php esc_html_e( 'Hide', 'it-l10n-backupbuddy' ); ?></a>
	</div>
</div>
