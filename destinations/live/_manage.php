<?php
/**
 * Solid Backups Stash Live Main Page
 *
 * @author Dustin Bolton
 * @since 7.0
 * @package BackupBuddy
 */

pb_backupbuddy::load_script( 'underscore' );

if ( is_network_admin() ) {
	$admin_url = network_admin_url( 'admin.php' );
} else {
	$admin_url = admin_url( 'admin.php' );
}

pb_backupbuddy::alert( '{error placeholder}', false, $error_code = '', $rel_tag = 'stash_live_error_alert' );
?>
<script>
	var loadingIndicator = '';
	jQuery(function( $ ) {
		loadingIndicator = $( '.pb_backupbuddy_loading' );

		$( '.backupbuddy-live-stats-container' ).on( 'click', '.backupbuddy-stats-help-link', function(){
			tb_show( 'Stash Live Snapshot Details', '<?php echo pb_backupbuddy::ajax_url( 'live_last_snapshot_details' ); ?>&TB_iframe=1&width=640&height=455', null );
			return false;
		});

		$( document ).ready( function() {
			// Remove 'live_action' from URL to prevent accidental re-runs if page is refreshed.
			var url = window.location.href;
			var urlparts = url.split( '?' );
			if ( urlparts.length >= 2 ) {
				var prefix = encodeURIComponent( 'live_action' ) + '=';
				var pars = urlparts[1].split( /[&;]/g );
				for ( var i = pars.length; i-- > 0; ) {
					if ( pars[i].lastIndexOf( prefix, 0 ) !== -1 ) {
						pars.splice( i, 1 );
					}
				}
				url = urlparts[0] + ( ( pars.length > 0 ) ? '?' + pars.join( '&' ) : '' );
				window.history.pushState( '', document.title, url );
			}
		});
	});

	function backupbuddy_resizeIframe(obj) {
		newHeight = obj.contentWindow.document.body.scrollHeight;
		obj.style.height = newHeight + 'px';
	}
</script>
<?php
// Incoming vars: $destination, $destination_id
if ( isset( $destination['disabled'] ) && ( '1' == $destination['disabled'] ) ) {
	die( __( '<span class="description">This destination is currently disabled based on its settings. Re-enable it under its Advanced Settings.</span>', 'it-l10n-backupbuddy' ) );
}

wp_enqueue_script( 'thickbox' );
wp_print_scripts( 'thickbox' );
wp_print_styles( 'thickbox' );


require_once( pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php' );
$state = backupbuddy_live_periodic::get_stats();
include( '_stats.php' ); // Recalculate stats. Populates $stats var.

// First backup in progress alert.
if ( 0 == $state['stats']['first_completion'] ) :
	ob_start()
	?>
	<div>
		<h3 id="backupbuddy_live_first_completion_pending"><?php esc_html_e( 'Your first Solid Backups Stash Live Backup has started!', 'it-l10n-backupbuddy' ); ?></h3>
		<div class="col-wrap">
			<div class="col col-1-3"><?php pb_backupbuddy::$ui->render_icon( 'backup' ); ?>
				<p><?php echo wp_kses_post(
						__( '<strong>This first backup may take a while</strong> depending on your site size and hosting.', 'it-l10n-backupbuddy' )
					); ?>
				</p>
			</div>
			<div class="col col-1-3"><?php pb_backupbuddy::$ui->render_icon( 'atsymbol' ); ?>
				<?php echo sprintf( '<p><strong>%s</strong> %s</p>', __('Keep an eye on your inbox.', 'it-l10n-backupbuddy' ), __( 'You\'ll receive an email notification once your first Stash Live backup completes. You can configure your Stash Live email settings from the Live Settings link above.', 'it-l10n-backupbuddy' ) ); ?>
			</div>
			<div class="col col-1-3"><?php pb_backupbuddy::$ui->render_icon( 'lifesaver' ); ?>
				<?php echo sprintf( '<p><strong>%s</strong> %s</p>', __('Have questions or need help?', 'it-l10n-backupbuddy' ), sprintf( __('Check out the <a href="%1$s" target="_blank">%2$s</a> and <a href="%3$s" target="_blank">%4$s</a>.', 'it-l10n-backupbuddy' ), 'https://go.solidwp.com/stash-live-faqs', __( 'Stash Live FAQs', 'it-l10n-backupbuddy' ), 'https://go.solidwp.com/backups-stash-live', __('tutorial video library', 'it-l10n-backupbuddy' ) ) ); ?>
			</div>
		</div>
	</div>

	<?php
	pb_backupbuddy::disalert( 'live_first_completion', ob_get_clean(), false, '', array( 'class' => ' notice-success ' ) );
	backupbuddy_live_periodic::start_live_pulse();
endif;

// First backup complete alert. Not output if older than 48 hours. Output but hidden if =0. Output and visible is non-zero > 48hrs.
if ( 0 == $state['stats']['first_completion'] ) { // Output but hidden if not finished.
	$completion_css = 'display: none;';
} elseif ( ( time() - $state['stats']['first_completion'] ) < 60*60*48 ) { // < 48hrs.
	$completion_css = '';
}
if ( ! empty( $completion_css ) ) {
	pb_backupbuddy::disalert( 'live_first_completion_done', '<div class="pb_backupbuddy_alert_inner"><h3>' . __( 'Your first Live Backup is complete!', 'it-l10n-backupbuddy' ) . '</h3><p class="description" style="max-width: 700px; display: inline-block;">' . __( 'Your first Solid Backups Stash Live backup process has completed and your first Snapshot will arrive in your Stash storage shortly. We\'ll automatically backup any changes you make to your site from here on out and regularly create Snapshots in time of your data. Your site is well on its way to a secure future in the safe hands of Solid Backups Stash Live.', 'it-l10n-backupbuddy' ) . '</p></div>', 'success', $completion_css );
}

function bb_show_unsent_files( $show_permissions = true ) {
	// Set up ZipBuddy when within BackupBuddy
	require_once( pb_backupbuddy::plugin_path() . '/lib/zipbuddy/zipbuddy.php' );
	pb_backupbuddy::$classes['zipbuddy'] = new pluginbuddy_zipbuddy( backupbuddy_core::getBackupDirectory() );

	echo '<h3>Unsent Files Listing (except deleted):</h3>';
	$catalog = backupbuddy_live_periodic::get_catalog();
	$unsent = array();
	foreach( $catalog as $file => $catalogItem ) {
		if ( !isset( $catalogItem['s'] ) || ( 0 == $catalogItem['s'] ) ) {
			if ( isset( $catalogItem['d'] ) && ( true === $catalogItem['d'] ) ) { // Don't list items pending delete since we will not send this anyways.
				continue;
			}

			$details = '';
			if ( true === $show_permissions ) {
				$stats = pluginbuddy_stat::stat( ABSPATH . $file );
				if ( false !== $stats ) {
					$mode_octal_four = $stats['mode_octal_four'];
					$owner = $stats['uid'] . ':' . $stats['gid'];
					$details = ' - Perm: ' . $mode_octal_four . ' - Owner: ' . $owner;
				} else {
					$details = ' - [UNKNOWN PERMISSIONS]';
				}
			}

			$unsent[] = $file . $details;
		}
	}
	echo '<textarea readonly="readonly" style="width: 100%;" wrap="off" cols="65" rows="20">--- TOTAL PENDING SEND: ' . count( $unsent ) . " ---\n" . implode( "\n", $unsent ) . '</textarea>';
	echo '<br><br><br>';
}

// RUN ACTIONS FROM BUTTON PRESS.
if ( '' != pb_backupbuddy::_GET( 'live_action' ) ) {
	pb_backupbuddy::verify_nonce();

	$action = pb_backupbuddy::_GET( 'live_action' );
	if ( 'clear_log' == $action ) {

		$sumLogFile = backupbuddy_core::getLogDirectory() . 'status-live_periodic_' . pb_backupbuddy::$options['log_serial'] . '.txt';
		@unlink( $sumLogFile );
		if ( file_exists( $sumLogFile ) ) {
			pb_backupbuddy::alert( '<p>' . sprintf(
				__( 'Error #893489322: Unable to clear log file `%s`. Check permissions or manually delete.', 'it-l10n-backupbuddy' ),
				$sumLogFile
			) .'</p>' );
		} else {
			pb_backupbuddy::alert( '<p>' . __( 'Log file cleared.', 'it-l10n-backupbuddy' ) . '</p>' );
		}

	} elseif ( 'create_snapshot' == $action ) { // < 100% backed up _OR_ ( we are on a step other than daily_init and the last_activity is more recent than the php runtime )

		if ( true === backupbuddy_api::runLiveSnapshot() ) {
			pb_backupbuddy::alert( '<div><p><strong>' . __( 'Verifying everything is up to date before Snapshot', 'it-l10n-backupbuddy' ) . '</strong><br><br>' . __( 'Please wait while we verify your backup is completely up to date before we create the Snapshot. This may take a few minutes...', 'it-l10n-backupbuddy' ) . '</p></div>', false, '', 'backupbuddy_live_snapshot_verify_uptodate' );
			require( '_manual_snapshot.php' );
		}

	} elseif ( 'resume_periodic_step' == $action ) {

		pb_backupbuddy::alert( '<p>' . __( 'Launching Solid Backups Stash Live Periodic Process to run now where it left off.', 'it-l10n-backupbuddy' ) . '</p>' );
		backupbuddy_live_periodic::start_live_pulse();
		backupbuddy_live_periodic::run_periodic_process();

	} elseif ( 'uncache_credentials' == $action ) {

		require_once( pb_backupbuddy::plugin_path() . '/destinations/live/init.php' );
		pb_backupbuddy_destination_live::clear_cached_credentials();

	} elseif ( 'restart_periodic' == $action ) {

		backupbuddy_live_periodic::restart_periodic();
		$destination = pb_backupbuddy::$options['remote_destinations'][$destination_id]; // Update local var.

	} elseif ( 'restart_periodic_force' == $action ) {

		backupbuddy_live_periodic::restart_periodic( true );
		$destination = pb_backupbuddy::$options['remote_destinations'][$destination_id]; // Update local var.

	} elseif( 'restart_at_step' == $action ) {

		$step = pb_backupbuddy::_POST( 'live_step' );
		require_once( pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php' );
		pb_backupbuddy::alert(
			sprintf(
				__( 'Reset Periodic Process to run at step `%s` (FORCED). This may take a few minutes for the current process to complete.', 'it-l10n-backupbuddy' ),
				esc_attr( $step )
			)
		);

		backupbuddy_live_periodic::queue_step( $step, $args = array(), $skip_run_now = false, $force_run_now  = true );

	} elseif ( 'view_files' == $action ) {

		if ( '1' == $destination['disable_file_management'] ) {
			pb_backupbuddy::alert( __( 'Remote file management has been disabled for Stash Live. Its files cannot be viewed & managed from within Solid Backups. To re-enable you must Disconnect and Re-connect Stash Live. You may also manage your files at <a href="https://go.solidwp.com/solid-central-login" target="_new">https://go.solidwp.com/solid-central-login</a>.', 'it-l10n-backupbuddy' ) );
		} else {
			require( '_viewfiles.php' );
			echo '<br><hr><br><br>';
		}
	} elseif ( 'view_files_tables' == $action ) {

		if ( '1' == $destination['disable_file_management'] ) {
			pb_backupbuddy::alert(
				sprintf(
					__( 'Remote file management has been disabled for Stash Live. Its files cannot be viewed & managed from within Solid Backups. To re-enable you must Disconnect and Re-connect Stash Live. You may also manage your files at <a href="%1$s" target="_new">%2$s</a>.', 'it-l10n-backupbuddy' ),
					'https://go.solidwp.com/solid-central-login-',
					'https://go.solidwp.com/solid-central-login-'
				)
			);
		} else {
			require( '_viewfiles_tables.php' );
			echo '<br><hr><br><br>';
		}

	} elseif ( 'view_tables' == $action ) {

		require( '_viewtables.php' );
		echo '<br><hr><br><br>';

	} elseif ( 'view_catalog_raw' == $action ) {

		echo '<h3>' . esc_html__( 'Local Catalog File Signatures (raw):', 'it-l10n-backupbuddy' ) . '</h3>';
		$catalog = backupbuddy_live_periodic::get_catalog();
		echo '<textarea readonly="readonly" style="width: 100%;" wrap="off" cols="65" rows="20">' . print_r( $catalog, true ) . '</textarea>';
		echo '<br><br><br>';

	} elseif ( 'view_unsent_files' == $action ) {

		bb_show_unsent_files( true );

	} elseif ( 'view_unsent_files_noperms' == $action ) {

		bb_show_unsent_files( false );

	} elseif ( 'view_signatures_raw' == $action ) {

		echo '<h3>' . esc_html__( 'Local Catalog File Signatures (raw - contents and file count may fluctuate if periodic files process is not pasued):', 'it-l10n-backupbuddy' ) . '</h3>';
		$catalog = backupbuddy_live_periodic::get_catalog();
		echo '<textarea readonly="readonly" style="width: 100%;" wrap="off" cols="65" rows="20">' . print_r( $catalog, true ) . '</textarea>';
		echo '<br><br><br>';

	} elseif ( 'troubleshooting' == $action ) {

		echo '<div class="backupbuddy-stashlive-troubleshooting-scan">';
			echo '<h3>' . esc_html__( 'Stash Live Troubleshooting Scan Details', 'it-l10n-backupbuddy' ) . '</h3>';

			require( '_troubleshooting.php' );
			backupbuddy_live_troubleshooting::run();
			$results = backupbuddy_live_troubleshooting::get_raw_results();

			echo '<button style="margin-bottom: 10px;" class="button button-primary" onClick="backupbuddy_save_textarea_as_file(\'#backupbuddy_live_troubleshooting_results\', \'stash_live_troubleshooting\' );">' . esc_html__( 'Download Troubleshooting Log (.txt)', 'it-l10n-backupbuddy' ) . '</button>';
			echo '<textarea readonly="readonly" style="width: 100%;" wrap="off" cols="65" rows="10" id="backupbuddy_live_troubleshooting_results">' . print_r( $results, true ) . '</textarea><br>';
			echo '<button style="margin-top: 10px;" class="button button-primary" onClick="backupbuddy_save_textarea_as_file(\'#backupbuddy_live_troubleshooting_results\', \'stash_live_troubleshooting\' );">' . esc_html__( 'Download Troubleshooting Log (.txt)', 'it-l10n-backupbuddy' ) . '</button>';

			echo '<br><br><br><br>';
		echo '</div>';

	} elseif ( 'last_snapshot_details' == $action ) {
		if ( '' == $state['stats']['last_remote_snapshot_id'] ) {
			pb_backupbuddy::alert( __( 'No Snapshot creations have been requested yet.', 'it-l10n-backupbuddy' ) );
		} else {
			$destination_settings = backupbuddy_live_periodic::get_destination_settings();
			$additionalParams = array(
				'snapshot' => $state['stats']['last_remote_snapshot_id'],
			);

			echo '<h3>' . esc_html__( 'Last Snapshot Details', 'it-l10n-backupbuddy' ) . '</h3>';
			echo '<strong>' . esc_html__( 'Status as reported from server as of just now:', 'it-l10n-backupbuddy' ) . '</strong><br>';
			$response = pb_backupbuddy_destination_live::stashAPI( $destination_settings, 'live-snapshot-status', $additionalParams );
			if ( ! is_array( $response ) ) {
				pb_backupbuddy::alert(
					sprintf(
						__( 'Error %1$s: Unable to get Live snapshot status. Details: `%2$s`.', 'it-l10n-backupbuddy' ),
						'#349793b',
						$response
					)
				);
			} else {
				echo '<textarea readonly="readonly" style="width: 100%;" wrap="off" cols="65" rows="15">' . print_r( $response, true ) . '</textarea>';
				echo '<br><br>';
			}

			echo '<strong>' . esc_html(
				sprintf(
					__( 'Server response to request to initiate ( %1$s %2$s ago):', 'it-l10n-backupbuddy' ),
					pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $state['stats']['last_remote_snapshot_response_time'] ) ),
					pb_backupbuddy::$format->time_ago( $state['stats']['last_remote_snapshot_response_time'] )
				)
			) . '</strong><br>';
			echo '<textarea readonly="readonly" style="width: 100%;" wrap="off" cols="65" rows="15">' . print_r( $state['stats']['last_remote_snapshot_response'], true ) . '</textarea>';
			echo '<br><br><br>';
		}
	} elseif ( 'view_tables_raw' == $action ) {

		echo '<h3>' . esc_html__( 'Local Catalog Tables (raw):', 'it-l10n-backupbuddy' ) . '</h3>';
		$tables = backupbuddy_live_periodic::get_tables();
		echo '<textarea readonly="readonly" style="width: 100%;" wrap="off" cols="65" rows="20">' . print_r( $tables, true ) . '</textarea>';
		echo '<br><br><br>';

	} elseif ( 'view_signatures' == $action ) {

		echo '<h3>' . esc_html__( 'Local Files Catalog (contents and file count may fluctuate if periodic files process is not pasued):', 'it-l10n-backupbuddy' ) . '</h3>';
		$siteSize = 0;
		$catalog = backupbuddy_live_periodic::get_catalog();
		$catalogTable = array();
		foreach( $catalog as $filename => $signature ) {
			if ( 0 != $signature['b'] ) {
				$sent = $signature['b'];
			} else {
				$sent = __( 'Pending', 'it-l10n-backupbuddy' );
			}
			if ( isset( $signature['d'] ) && ( true === $signature['d'] ) ) {
				$delete = __( 'Yes', 'it-l10n-backupbuddy' );
			} else {
				$delete = __( 'No', 'it-l10n-backupbuddy' );
				$siteSize += $signature['s']; // File not marked for deletion.
			}
			if ( ! isset( $signature['h'] ) || ( '' == $signature['h'] ) ) {
				$hash = 'n/a';
			} else {
				$hash = $signature['h'];
			}
			if ( 0 == $signature['m'] ) {
				$modified = '<span class="description">' . __( 'Pending...', 'it-l10n-backupbuddy' ) . '</span>';
			} else {
				$modified = $signature['m'];
			}
			if ( 0 == $signature['v'] ) {
				$audited = '<span class="description">' . __( 'Pending...', 'it-l10n-backupbuddy' ) . '</span>';
			} else {
				$audited = $signature['v'];
			}
			$tries = '0';
			if ( isset( $signature['t'] ) ) {
				$tries = (string)$signature['t'];
			}
			$catalogTable[ $filename ] = array(
				$filename,
				(string)$signature['s'],
				$modified,
				$sent,
				$audited,
				$tries,
				$delete,
				$hash,
			);
		}
		unset( $catalog );

		$catalogInfo = sprintf(
			__( 'Total catalog site size: `%1$s`. Total files in catalog: `%2$s`. Catalog file size: `%3$s`.', 'it-l10n-backupbuddy' ),
			pb_backupbuddy::$format->file_size( $siteSize ),
			count( $catalogTable ),
			pb_backupbuddy::$format->file_size( filesize( backupbuddy_core::getLogDirectory() . 'live/catalog-' . pb_backupbuddy::$options['log_serial'] . '.txt' ) )
		);
		echo $catalogInfo;

		pb_backupbuddy::$ui->list_table(
			$catalogTable, // Array of cron items set in code section above.
			array(
				'live_action' => pb_backupbuddy::page_url() . '#pb_backupbuddy_getting_started_tab_tools',
				'columns' => array(
					__( 'Files', 'it-l10n-backupbuddy' ),
					__( 'Size', 'it-l10n-backupbuddy' ),
					__( 'Modified', 'it-l10n-backupbuddy' ),
					__( 'Sent', 'it-l10n-backupbuddy' ),
					__( 'Audited', 'it-l10n-backupbuddy' ),
					__( 'Send Tries', 'it-l10n-backupbuddy' ),
					__( 'Delete?', 'it-l10n-backupbuddy' ),
					__( 'Hash', 'it-l10n-backupbuddy' ),
				),
				'css' => 'width: 100%;',
			)
		); // end list_table.
		unset( $catalogTable );

		echo $catalogInfo;
		echo '<br><br><br>';

	} elseif ( 'reset_send_attempts' == $action ) {

		if ( false === backupbuddy_live_periodic::reset_send_attempts() ) {
			pb_backupbuddy::alert( __( 'Error attempting to reset send attempt counts. The catalog may be busy. Try again in a moment or see the Status Log for details.', 'it-l10n-backupbuddy' ) );
		} else {
			pb_backupbuddy::alert( __( 'Success resetting send attempt counts back to zero for all files and global recent send fail counter.', 'it-l10n-backupbuddy' ) );
		}


	} elseif ( 'reset_last_activity' == $action ) {
		pb_backupbuddy::alert( __( 'Reset last activity timestamp.', 'it-l10n-backupbuddy' ) );
		backupbuddy_live_periodic::reset_last_activity();
	} elseif ( 'reset_file_audit_times' == $action ) {
		pb_backupbuddy::alert( __( 'Reset file audit timestamps.', 'it-l10n-backupbuddy' ) );
		backupbuddy_live_periodic::reset_file_audit_times();
	} elseif ( 'reset_first_completion' == $action ) {
		pb_backupbuddy::alert( __( 'Reset first completion timestamp.', 'it-l10n-backupbuddy' ) );
		backupbuddy_live_periodic::reset_first_completion();
	} elseif ( 'reset_last_remote_snapshot' == $action ) {
		pb_backupbuddy::alert( __( 'Reset last remote snapshot timestamp.', 'it-l10n-backupbuddy' ) );
		backupbuddy_live_periodic::reset_last_remote_snapshot();
	} elseif ( 'view_state' == $action ) {

		echo '<h3>' . esc_html__( 'State Data:', 'it-l10n-backupbuddy' ) . '</h3>';
		echo '<textarea readonly="readonly" style="width: 100%;" wrap="off" cols="65" rows="20">' . print_r( $state, true ) . '</textarea>';
		echo '<br><br><br>';

	} elseif ( 'view_log' == $action ) {

		$sumLogFile = backupbuddy_core::getLogDirectory() . 'status-live_periodic_' . pb_backupbuddy::$options['log_serial'] . '.txt';
		?>
		<div style="padding: 4px;">
			<strong><?php esc_html_e( 'Status Log', 'it-l10n-backupbuddy' ); ?></strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			<?php echo esc_html(
				sprintf(
					__( 'File size: %1$s', 'it-l10n-backupbuddy' ),
					pb_backupbuddy::$format->file_size( @filesize( $sumLogFile ) )
				)
			); ?>
			<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=clear_log' ); ?>" class="button button-secondary button-tertiary" style="margin-left: 11px; vertical-align: 1px;"><?php esc_html_e( 'Clear Status Log', 'it-l10n-backupbuddy' ); ?></a>
		</div>
		<script>
			jQuery(document).ready( function(){
				jQuery('#backupbuddy_live_log' ).scrollTop( jQuery('#backupbuddy_live_log')[0].scrollHeight );
			});
		</script>
		<?php
		echo '<textarea readonly="readonly" style="width: 100%;" wrap="off" cols="65" rows="20" id="backupbuddy_live_log">';

		// Is Live Logging Enabled?
		$liveID = backupbuddy_live::getLiveID();
		$logging_disabled = ( isset( pb_backupbuddy::$options['remote_destinations'][ $liveID ]['disable_logging'] ) && ( '1' == pb_backupbuddy::$options['remote_destinations'][ $liveID ]['disable_logging'] ) );

		if ( ! file_exists( $sumLogFile ) ) {
			if ( $logging_disabled ) {
				echo __( 'Live Logging has been disabled in Stash Live &#8594; Settings &#8594; Advanced.', 'it-l10n-backupbuddy' );
			} else {
				echo __( 'Nothing has been logged.', 'it-l10n-backupbuddy' );
			}
		} else {
			$mtime = @filemtime( $sumLogFile );
			$time_ago = pb_backupbuddy::$format->time_ago( $mtime );
			$lines = file_get_contents( $sumLogFile );
			if ( false === $lines ) {
				echo sprintf(
					__( 'Error %1$s: Unable to read log file `%2$s`.', 'it-l10n-backupbuddy' ),
					'#49834839',
					$sumLogFile
				);
			} else {
				$lines = explode( "\n", $lines );
				foreach( (array)$lines as $rawline ) {
					$line = json_decode( $rawline, true );
					//print_r( $line );
					if ( is_array( $line ) ) {
						$u = '';
						if ( isset( $line['u'] ) ) { // As off v4.2.15.6. TODO: Remove this in a couple of versions once old logs without this will have cycled out.
							$u = '.' . $line['u'];
						}
						echo pb_backupbuddy::$format->date( $line['time'], 'G:i:s' ) . $u . "\t\t";
						echo $line['run'] . "sec\t";
						echo $line['mem'] . "MB\t";
						echo $line['event'] . "\t";
						echo $line['data'] . "\n";
					} else {
						echo $rawline . "\n";
					}
				}
			}
		}
		?></textarea>
		<?php if ( ( ! $logging_disabled ) && ( isset( $time_ago ) ) ) { ?>
			<div style="display: inline-block; margin-left: 8px; margin-top: 5px;"><span class="description">Current Time: <?php echo pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( microtime( true ) ), 'G:i:s' ); ?>. &nbsp; &nbsp; &nbsp; Modified: <?php echo $time_ago; ?> ago.</span></div>
		<?php } ?>
		<br><br>
		<?php
	} elseif ( 'delete_catalog' == $action ) {
		backupbuddy_live_periodic::delete_catalog_files();
	} elseif ( 'pause_periodic' == $action ) {
		backupbuddy_api::setLiveStatus( $pause_continuous = '', $pause_periodic = true );
		$destination = pb_backupbuddy::$options['remote_destinations'][$destination_id]; // Update local var.
		pb_backupbuddy::disalert( '', __( 'Live File Backup paused. It may take a moment for current processes to finish.', 'it-l10n-backupbuddy' ) );
		include( '_stats.php' ); // Recalculate stats.
	} elseif ( 'resume_periodic' == $action ) {
		$launchNowText = ' ' . __( 'Unpaused but not running now.', 'it-l10n-backupbuddy' );
		$start_run = false;
		if ( '1' != pb_backupbuddy::_GET( 'skip_run_live_now' ) ) {
			$launchNowText = '';
			$start_run = true;
		}

		backupbuddy_api::setLiveStatus( $pause_continuous = '', $pause_periodic = false, $start_run );
		pb_backupbuddy::disalert( '', '<div class="pb_backupbuddy_alert_inner"><p>' . __( 'Live File Backup has resumed.', 'it-l10n-backupbuddy' ) . $launchNowText . '</p></div>' );

		include( '_stats.php' ); // Recalculate stats.
	} elseif ( 'pause_continuous' == $action ) {
		backupbuddy_api::setLiveStatus( $pause_continuous = true, $pause_periodic = '' );
		$destination = pb_backupbuddy::$options['remote_destinations'][$destination_id]; // Update local var.
		include( '_stats.php' ); // Recalculate stats.
		pb_backupbuddy::disalert( '', '<div class="pb_backupbuddy_alert_inner"><p>' . __( 'Live Database Backup paused.', 'it-l10n-backupbuddy' ) . '</p></div>' );
	} elseif ( 'resume_continuous' == $action ) {
		backupbuddy_api::setLiveStatus( $pause_continuous = false, $pause_periodic = '' );
		$destination = pb_backupbuddy::$options['remote_destinations'][$destination_id]; // Update local var.
		include( '_stats.php' ); // Recalculate stats.
		pb_backupbuddy::disalert( '', '<div class="pb_backupbuddy_alert_inner"><p>' . __( 'Live Database Backup resumed.', 'it-l10n-backupbuddy' ) . '</p></div>' );
	} elseif ( 'view_raw_settings' == $action ) {
		echo '<h3>' . __( 'Raw Settings:', 'it-l10n-backupbuddy' ) . '</h3>';
		echo '<textarea readonly="readonly" style="width: 100%;" wrap="off" cols="65" rows="20">' . print_r( pb_backupbuddy::$options['remote_destinations'][$destination_id], true ) . '</textarea>';
		echo '<br><br><br>';
	} elseif ( 'view_stats' == $action ) {
		echo '<h3>' . __( 'Raw Stats:', 'it-l10n-backupbuddy' ) . '</h3>';
		echo '<textarea readonly="readonly" style="width: 100%;" wrap="off" cols="65" rows="20">' . print_r( $stats, true ) . '</textarea>';
		echo '<br><br><br>';
	} elseif ( 'view_exclusions' == $action ) {
		echo '<h3>' . __( 'Raw Calculated Exclusions for Stash Live:', 'it-l10n-backupbuddy' ) . '</h3>';
		echo '<textarea readonly="readonly" style="width: 100%;" wrap="off" cols="65" rows="20">' . print_r( backupbuddy_live_periodic::getFullExcludes(), true ) . '</textarea>';
		echo '<br><br><br>';
	}

} // end if action.

// If the process needs bumping...

$troubleshooting_alerts_file = backupbuddy_core::getLogDirectory() . 'live/troubleshooting_alerts-' . pb_backupbuddy::$options['log_serial'] . '.txt';
if ( file_exists( $troubleshooting_alerts_file ) ) {
	if ( false !== ( $troubleshooting_alerts = @file_get_contents( $troubleshooting_alerts_file ) ) ) {
		$message = '<div><h3>' . __( 'Possible Problem Detected', 'it-l10n-backupbuddy' ) . '</h3><p class="description">' . __( 'One or more potential problems may have been detected. If you are experiencing problems with Stash Live the following information may help address your issue. If you are not experiencing issues you may dismiss this instance of this notice with the "Dismiss" link to the right. It will also automatically go away after a successful snapshot after the potential problem is corrected. Your host may be able to assist in correcting this issue.', 'it-l10n-backupbuddy' ) . '</p></div>';
		$message .= '<div><h3>Details</h3><p>' . $troubleshooting_alerts . '</p></div>';
		pb_backupbuddy::disalert( 'troubleshooting_' . md5( $troubleshooting_alerts ), $message, $error = true );
	}
}

if ( backupbuddy_core::detectMaxExecutionTime() < 28 ) {
	pb_backupbuddy::disalert( 'troubleshooting_max_execution', '<div class="pb_backupbuddy_alert_inner"><h3>' . __( 'Possible Problem Detected', 'it-l10n-backupbuddy' ) . '</h3><p class="description" style="max-width: 700px; display: inline-block;">' . __( 'Your PHP maximum execution time appears to be below 30 seconds, the default PHP runtime limit and industry standard. Please contact your host to have them increase your PHP maximum execution time to 30 seconds or greater.', 'it-l10n-backupbuddy' ) . '</p><br><br><strong>Details:</strong><br><br>' . __( 'Current detected value PHP execution time limit:', 'it-l10n-backupbuddy' ) . ' `' . backupbuddy_core::detectMaxExecutionTime() . '` seconds.</div>', $error = true );
}

// Backup types' Status.
$database_status = '<span class="backpbuddy-live-stats-enabled">' .__( 'Enabled', 'it-l10n-backupbuddy' ) . '</span>';
$database_status_row = '';
if ( '1' == $destination['pause_continuous'] ) {
	$database_status = '<span class="backpbuddy-live-stats-paused">' . __( 'Paused', 'it-l10n-backupbuddy' ) . '</span>';
	$database_status_row = 'backupbuddy-live-stats-paused-row';
}


$files_status = '<span class="backpbuddy-live-stats-enabled">' . __( 'Enabled', 'it-l10n-backupbuddy' ) . '</span>';
$files_status_row = '';
if ( '1' == $destination['pause_periodic'] ) {
	$files_status = '<span class="backpbuddy-live-stats-paused">' . __( 'Paused', 'it-l10n-backupbuddy' ) . '</span>';
	$files_status_row = 'backupbuddy-live-stats-paused-row';
}


// Database total size.
if ( 0 == $state['stats']['files_total_size'] ) {
	$totalDatabaseSizeDisplay = '<span class="description">' . __( 'Calculating...', 'it-l10n-backupbuddy' ) . '</span>';
} else {
	$totalDatabaseSizeDisplay = pb_backupbuddy::$format->file_size( $state['stats']['tables_total_size'] );
}

// Calculate tables pending deletion.
$tablesPendingDelete = '';
if ( 0 != $state['stats']['tables_pending_delete'] ) {
	$tablesPendingDelete = ', ' . $state['stats']['tables_pending_delete'] . ' ' . __( 'pending deletion', 'it-l10n-backupbuddy' );
}

// Database tables sent.
$tablesSent = ( $state['stats']['tables_total_count'] - $state['stats']['tables_pending_send'] );

// Tables percent sent (by count).
if ( $state['stats']['tables_total_count'] > 0 ) {
	$tablesSentPercent = ceil( ( $tablesSent / $state['stats']['tables_total_count'] ) * 100 );
	if ( ( 100 == $tablesSentPercent ) && ( $tablesSent < $state['stats']['tables_total_count'] ) ) { // If we were to display 100% sent but files still remain, convert to 99.9% to help indicate the gap.
		$tablesSentPercent = 99.9;
	}
} else {
	$tablesSentPercent = 0;
}

// Files total size.
if ( 0 == $state['stats']['files_total_size'] ) {
	$totalFilesSizeDisplay = '<span class="description">' . __( 'Calculating...', 'it-l10n-backupbuddy' ) . '</span>';
} else {
	$totalFilesSizeDisplay = '<span class="description">' . pb_backupbuddy::$format->file_size( $state['stats']['files_total_size'] ) . '</span>';
}

// Files sent.
$filesSent = ( $state['stats']['files_total_count'] - $state['stats']['files_pending_send'] );

// Files percent sent (by count).
if ( $state['stats']['files_total_count'] > 0 ) {
	$filesSentPercent = ceil( ( $filesSent / $state['stats']['files_total_count'] ) * 100 );
	if ( ( 100 == $filesSentPercent ) && ( $filesSent < $state['stats']['files_total_count'] ) ) { // If we were to display 100% sent but files still remain, convert to 99.9% to help indicate the gap.
		$filesSentPercent = 99.9;
	}
} else {
	$filesSentPercent = 0;
}

// Calculate files pending deletion.
$filesPendingDelete = '';
if ( 0 != $state['stats']['files_pending_delete'] ) {
	$filePendingDelete = ', ' . $state['stats']['files_pending_delete'] . ' ' . __( 'pending deletion', 'it-l10n-backupbuddy' );
}


$cron_warnings = array();
require pb_backupbuddy::plugin_path() . '/controllers/pages/server_info/_cron.php';
if ( count( $cron_warnings ) > 2 ) {
	$cronText = '';
	$i        = 0;
	foreach( $crons as $time => $cron ) {
		if ( ! is_int( $time ) ) {
			continue;
		}

		if ( ($time+60) > time() ) { // Not past due. 60sec wiggle room.
			continue;
		}
		$i++;
		if ( $i > 50 ) {
			$cronText .= __( 'More than 50 stuck crons found. Hiding remaining as to not flood screen...', 'it-l10n-backupbuddy' ) . '<br>';
			break;
		}
		$cronText .= sprintf(
			__( 'Cron `%1$s` running `%2$s` should have ran `%3$s` ago.', 'it-l10n-backupbuddy' ),
			$cron[0],
			$cron[2],
			pb_backupbuddy::$format->time_ago( $time )
			) . '<br>';
	}
	if ( ! empty( $cronText ) ) {
		$cronText = __( 'Potentially stuck crons:', 'it-l10n-backupbuddy' ) . '<br>' . $cronText;
	}
	pb_backupbuddy::alert(
		'<p>' . sprintf(
			__( 'Warning %1$s: %2$s cron(s) warnings were found (such as past due). _IF_ you encounter problems AND this persists there may be a problem with your WordPress cron (such as caused by a caching plugin). This can also be caused if there is very little site activity (not enough visitors). WordPress requires visitors to access the site to trigger scheduled activity. Use an uptime checker to help push this along if this is the case. This could be due to these files being large or a temporary transfer error. %3$s', 'it-l10n-backupbuddy' ),
			'#839984343',
			count( $cron_warnings ),
			$cronText
		) . '</p>',
		false,
		'',
		'',
		'',
		array( 'class' => ' notice-warning ' )
	);
}

// BUB Schedule with Stash Destination Warning
foreach ( pb_backupbuddy::$options['schedules'] as $schedule_id => $schedule ) {
	$remote_destinations = empty( $schedule['remote_destinations'] ) ? array() : explode( '|', $schedule['remote_destinations'] );
	foreach( $remote_destinations as $dest_id ) {
		$type = empty( pb_backupbuddy::$options['remote_destinations'][$dest_id]['type'] ) ? false : pb_backupbuddy::$options['remote_destinations'][$dest_id]['type'];
		if ( substr( $type, 0, 5 ) == 'stash' ) {
			pb_backupbuddy::alert( '<span class="pb_label">Tip</span><span>It looks like you are sending&nbsp;<a href="' . get_admin_url() . 'admin.php?page=pb_backupbuddy_scheduling">scheduled backups</a>&nbsp;to your Stash destination in addition to the use of Stash Live. Please consider removing the automated backups to your Stash destination while using Stash Live since both count against the same quota.</span>');
			break 2;
		}
	}
}
?>


<script>

	var backupbuddy_live_snapshot_status_pretty = '';
	var backupbuddy_live_snapshot_step = 1;
	function backupbuddy_live_stats( stats ) {
		var stepDB         = '.bb_progress-step-database',
			stepFiles      = '.bb_progress-step-files',
			stepSnapshot   = '.bb_progress-step-snapshot',
			activeClass    = 'bb_progress-step-active',
			completedClass = 'bb_progress-step-completed';

		if ( stats.error_alert.length > 0 ) {
			jQuery( '.pb_backupbuddy_alert[rel="stash_live_error_alert"]' ).html( '<div><strong><?php esc_html_e( 'There was a problem syncing with Stash. Please try again. If the issue persists, please reach out to our support team for further assistance.', 'it-l10n-backupbuddy' ); ?></strong><br>' + stats.error_alert + '</div>' ).show();
		} else {
			jQuery( '.pb_backupbuddy_alert[rel="stash_live_error_alert"]' ).text( '' ).remove();
		}

		// Display progress bar if needed. When not on daily_init AND ( during first completion OR during manual snapshot )
		if ( ( ( 'daily_init' != stats.current_function ) && ( '0' == stats.first_completion || 2 === backupbuddy_live_snapshot_step ) ) || ( ( 'undefined' != typeof window.backupbuddy_live_snapshot_request_time ) && ( '' != window.backupbuddy_live_snapshot_request_time ) ) ) {
			//jQuery( '.pb_backupbuddy_alert' ).hide();
			//jQuery( '.pb_backupbuddy_alert[rel="live_first_completion"]' ).show();
			jQuery( '.backupbuddy-live-snapshot-progress-bar' ).show();

			// if we're on db step, update UI
			if ( ( 'database_snapshot' == stats.current_function ) || ( 'send_pending_db_snapshots' == stats.current_function ) || ( 'process_table_deletions' == stats.current_function ) ) {
				jQuery( '.' + completedClass ).removeClass( completedClass ); // Reset.
				jQuery( '.' + activeClass ).removeClass( activeClass ); // Reset.
				jQuery( stepDB ).addClass( activeClass ); // DB active.
			} else if ( ( 'update_files_list' == stats.current_function ) || ( 'update_files_signatures' == stats.current_function ) || ( 'process_file_deletions' == stats.current_function ) || ( 'send_pending_files' == stats.current_function ) || ( 'audit_remote_files' == stats.current_function ) ) { // if we're on files step, update UI
				jQuery( stepDB ).removeClass( activeClass ).addClass( completedClass ); // DB done.
				jQuery( stepFiles ).addClass( activeClass ); // FILES active.
			} else if ( ( 'run_remote_snapshot' == stats.current_function ) || ( stats.last_remote_snapshot_response_time > window.backupbuddy_live_snapshot_request_time ) ) { // or last snapshot trigger time is since this snapshot began. in case we miss the run_remote_snapshot step.
				jQuery( stepDB ).removeClass( activeClass ).addClass( completedClass ); // DB done.
				jQuery( stepFiles ).removeClass( activeClass ).addClass( completedClass ); // FILES done.
				jQuery( stepSnapshot ).addClass( activeClass ); // SNAPSHOT active.
			}

			// While 'SnapShot' portion of progress bar is active, replace current function details to reflect this.
			if ( jQuery(stepSnapshot ).hasClass( activeClass  ) ) {
				stats.current_function_pretty = '<?php _e( 'Building Snapshot', 'it-l10n-backupbuddy' ); ?>' + backupbuddy_live_snapshot_status_pretty; // backupbuddy_live_snapshot_status_pretty contains snapshot details returned from Stash server during snapshot building.
			}
		} else {
			// If first backup and on daily_init, reset status bar in case things got reset.
			if ( ( '0' == stats.first_completion ) && ( 'daily_init' == stats.current_function ) ) {
				jQuery( stepDB ).removeClass( activeClass ).removeClass( completedClass );
				jQuery( stepFiles ).removeClass( activeClass ).removeClass( completedClass );
				jQuery( stepSnapshot ).removeClass( activeClass ).removeClass( completedClass );
			}
		}


		// Is manual snapshot pending?
		if ( 'undefined' != typeof window.backupbuddy_live_snapshot_request_time ) {

			// On remote snapshot function?
			if ( ( 1 == backupbuddy_live_snapshot_step ) && ( ( 'run_remote_snapshot' == stats.current_function ) || ( stats.last_remote_snapshot_response_time > window.backupbuddy_live_snapshot_request_time ) ) ) {
				jQuery( '.pb_backupbuddy_alert' ).hide();

				jQuery( stepFiles ).removeClass( activeClass ).addClass( completedClass );
				jQuery( stepSnapshot ).addClass( activeClass );
				backupbuddy_live_snapshot_step = 2;
			}
			if ( ( 2 == backupbuddy_live_snapshot_step ) && ( stats.last_remote_snapshot_response_time > window.backupbuddy_live_snapshot_request_time ) && ( 'object' == typeof stats.last_remote_snapshot_response ) ) {
				<?php if ( pb_backupbuddy::full_logging() ) { ?>
					console.log( 'Last snapshot response:' );
					console.dir( stats.last_remote_snapshot_response );
				<?php } ?>
				setTimeout( 'backupbuddy_live_snapshot_status_check( "' + stats.last_remote_snapshot_response.snapshot + '" )', 3000 );

				jQuery( stepFiles ).removeClass( activeClass ).addClass( completedClass );
				backupbuddy_live_snapshot_step = 3;
			}
		}

		// If new snapshot has begun since loading the page (and it's automatic triggered), then start watching for the status to update.
		stash_iframe = jQuery( '#backupbuddy_live-stash_iframe' );
		if ( ( ( parseInt( stash_iframe.attr( 'data-refreshed' ) ) < ( stats.last_remote_snapshot ) ) ) && ( 'automatic' == stats.last_remote_snapshot_trigger ) ) {
			setTimeout( 'backupbuddy_live_snapshot_status_check( "' + stats.last_remote_snapshot_response.snapshot + '" )', 3000 );
		}

		// This grabs the template, and inserts HTML ( with appropriate data ) into the DOM
		_.templateSettings.variable    = 'stats';
		_.templateSettings.evaluate    = /<#([\s\S]+?)#>/g;
		_.templateSettings.interpolate = /\{\{\{([\s\S]+?)\}\}\}/g;
		_.templateSettings.escape      = /\{\{([^\}]+?)\}\}(?!\})/g;
		var template = _.template( jQuery( '#backupbuddy-live-stats-tmpl' ).html() );

		jQuery( '.backupbuddy-live-stats-container' ).html( template(stats) );

		// First backup completion.
		if ( stats.first_completion > 0 ) {
			if ( jQuery( '.pb_backupbuddy_alert[rel="live_first_completion"]' ).is( ':visible' ) ) {
				jQuery( '.pb_backupbuddy_alert[rel="live_first_completion_done"]' ).show();
			}
			jQuery( '.pb_backupbuddy_alert[rel="live_first_completion"]' ).remove();
		}

	} // End backupbuddy_live_stats().



	var snapshot_infected_warning = false; // Set true if malware is found to warn on download attempt.
	function backupbuddy_live_snapshot_status_check( snapshot_id ) {
		if ( '' == snapshot_id ) {
			return false;
		}
		loadingIndicator.show();

		console.log( 'live_snapshot_status_check' );

		jQuery.ajax({

			url:	snapshotStatusURL,
			type:	'post',
			data:	{ snapshot_id: snapshot_id },
			context: document.body,

			success: function( data ) {
				if ( 0 != loadingIndicator.length ) {
					loadingIndicator.hide();
				}
				jQuery( '.pb_backupbuddy_alert' ).hide();
				jQuery( '.pb_backupbuddy_alert[rel="live_first_completion"]' ).show();

				try {
					snapshotStatus = jQuery.parseJSON( data );
				} catch(e) { // NOT json or some error.
					//alert( 'Unable to get snapshot status. Details here and in console: `' + data + '`.' );
					console.log( 'Live Stats Response (ERROR #48943745):' );
					console.dir( data );
					return false;
				}

				<?php if ( pb_backupbuddy::full_logging() ) { ?>
					console.log( 'Solid Backups Snapshot Status:' );
					console.dir( snapshotStatus );
				<?php } ?>

				if ( '1' == snapshotStatus.complete ) { // Snapshot FINISHED.
					window.backupbuddy_live_snapshot_request_time = ''; // Unset.

					// Error encountered.
					if ( 'error' == snapshotStatus.status ) {
						alert( 'An unexpected error was encountered while creating your Snapshot: `' + snapshotStatus.message + '`. Check your Snapshot listing below to check whether the Snapshot was created or not.' );
					}

					// Snapshot info is missing from response.
					if ( 'undefined' == typeof snapshotStatus.snapshot ) {
						jQuery( '.backupbuddy-live-snapshot-progress-bar' ).hide(); // No download info available so hide bar.

						// Refresh Stash iFrame to show new file IF it happened to work...
						jQuery( '.backupbuddy_live_iframe_load' ).show();
						jQuery( '#backupbuddy_live-stash_iframe' ).attr( 'src', jQuery( '#backupbuddy_live-stash_iframe' ).attr( 'src' ) );
						jQuery( '#backupbuddy_live-stash_iframe' ).attr( 'data-refreshed', snapshotStatus.current_time ); // Update refresh timestamp.

						return false;
					}

					jQuery( '.bb_progress-step-snapshot, .bb_progress-step-files' ).removeClass('bb_progress-step-active').addClass( 'bb_progress-step-completed' );
					jQuery( '.bb_progress-step-finished' ).addClass('bb_progress-step-completed');

					jQuery( '#backupbuddy_live_snapshot-working' ).hide();
					jQuery( '#backupbuddy_live_snapshot-success' ).show();

					// Refresh Stash iFrame to show new file.
					jQuery( '.backupbuddy_live_iframe_load' ).show();
					jQuery( '#backupbuddy_live-stash_iframe' ).attr( 'src', jQuery( '#backupbuddy_live-stash_iframe' ).attr( 'src' ) );
					jQuery( '#backupbuddy_live-stash_iframe' ).attr( 'data-refreshed', snapshotStatus.current_time ); // Update refresh timestamp.

					// Malware scanning.
					jQuery.each( snapshotStatus.snapshot.malware.stats, function(index,value){
						jQuery( '#backupbuddy_live_snapshot-success-malware .backupbuddy_live_malware_result[data-result="' + index + '"]' ).text( value );
					});

					if ( jQuery( snapshotStatus.snapshot.malware.files ).size() > 0 ) {
						snapshot_infected_warning = true;
						jQuery( '.backupbuddy_live_malware_result[data-result="infected_files"]' ).css( 'color', 'red' );
						jQuery( '#backupbuddy_live_snapshot-success-malware-files' ).show();
						jQuery.each( snapshotStatus.snapshot.malware.files, function(index,value){
							jQuery( '#backupbuddy_live_snapshot-success-malware-files ul' ).append( '<li>' + index + '<ul><li style="list-style-type: circle;">' + value + '</li></ul></li>' );
						});
					} else {
						snapshot_infected_warning = false;
					}

					jQuery( '#backupbuddy_live_snapshot-success-duration' ).text( snapshotStatus.duration ).attr( 'title', 'Start: `' + snapshotStatus.timestamp_start + '`. Finish: `' + snapshotStatus.timestamp_finish + '`.' );;

					if ( true === snapshotStatus.snapshot.stash_copy ) {
						jQuery( '#backupbuddy_live_snapshot-stashed' ).show();
					}

					if ( 'undefined' != typeof snapshotStatus.snapshot.zips.full ) {
						jQuery( '#backupbuddy_live_snapshot-success-backup_full' ).show().find('a').attr( 'href', snapshotStatus.snapshot.zips.full );
					}
					if ( 'undefined' != typeof snapshotStatus.snapshot.zips.db ) {
						jQuery( '#backupbuddy_live_snapshot-success-backup_db' ).show().find('a').attr( 'href', snapshotStatus.snapshot.zips.db );
					}
					if ( 'undefined' != typeof snapshotStatus.snapshot.zips.plugins ) {
						jQuery( '#backupbuddy_live_snapshot-success-backup_plugins' ).show().find('a').attr( 'href', snapshotStatus.snapshot.zips.plugins );
					}
					if ( 'undefined' != typeof snapshotStatus.snapshot.zips.themes ) {
						jQuery( '#backupbuddy_live_snapshot-success-backup_themes' ).show().find('a').attr( 'href', snapshotStatus.snapshot.zips.themes );
					}
					if ( ( false === snapshotStatus.snapshot.missing_ib ) && ( 'undefined' != typeof snapshotStatus.snapshot.importbuddy ) ) {
						jQuery( '#backupbuddy_live_snapshot-success-backup_importbuddy' ).show().find('a').attr( 'href', snapshotStatus.snapshot.importbuddy.url );
					}
				} else { // Snapshot IN PROGRESS.
					jQuery( '#backupbuddy_live_snapshot-success' ).hide();
					//jQuery( '#backupbuddy_live_snapshot-working' ).show();

					backupbuddy_live_snapshot_status_pretty = ' - ' + snapshotStatus.message;
					//jQuery( '#backupbuddy_live_snapshot-working-status' ).text( snapshotStatus.message );
					//jQuery( '#backupbuddy_live_snapshot-working-duration' ).text( snapshotStatus.duration );

					setTimeout( 'backupbuddy_live_snapshot_status_check( "' + snapshot_id + '" )', 5000 );
				}
			}

		});
	} // End backupbuddy_live_snapshot_status_check().
</script>

<?php require_once( pb_backupbuddy::plugin_path() . '/destinations/live/_statsPoll.php' ); ?>


<div class="bb_progress-bar backupbuddy-live-snapshot-progress-bar clearfix">
	<div class="bb_progress-step bb_progress-step-database">
		<div class="bb_progress-step-icon"><?php pb_backupbuddy::$ui->render_icon( 'table' ); ?></div>
		<div class="bb_progress-step-title"><?php esc_html_e( 'Database', 'it-l10n-backupbuddy' ); ?></div>
		<span class="bb_progress-loading"></span>
	</div>
		<div class="bb_progress-step bb_progress-step-files">
		<div class="bb_progress-step-icon"><?php pb_backupbuddy::$ui->render_icon( 'file' ); ?></div>
		<div class="bb_progress-step-title"><?php esc_html_e( 'Files', 'it-l10n-backupbuddy' ); ?></div>
		<span class="bb_progress-loading"></span>
	</div>
		<div class="bb_progress-step bb_progress-step-snapshot">
		<div class="bb_progress-step-icon"><?php pb_backupbuddy::$ui->render_icon( 'capturePhoto' ); ?></div>
		<div class="bb_progress-step-title"><?php esc_html_e( 'Remote Snapshot', 'it-l10n-backupbuddy' ); ?></div>
		<span class="bb_progress-loading"></span>
	</div>
	<div class="bb_progress-step bb_progress-step-finished">
		<div class="bb_progress-step-icon"><?php pb_backupbuddy::$ui->render_icon( 'check' ); ?></div>
		<div class="bb_progress-step-title"><?php esc_html_e( 'Finished!', 'it-l10n-backupbuddy' ); ?></div>
		<span class="bb_progress-loading"></span>
	</div>
</div>



<div class="backupbuddy-live-stats-container postbox"></div>
<script type="text/template" id="backupbuddy-live-stats-tmpl">
	<div class="backupbuddy-live-stats-grid">
		<div class="col col-3-3">
			<div class="backupbuddy-live-stats-currently">
				<span class="backupbuddy-pulsing-orb"></span>
				<span class="backupbuddy-live-stats-currently-text">
					<span class="backupbuddy-inline-label"><?php _e( 'Currently', 'it-l10n-backupbuddy' ); ?></span>: {{ stats.current_function_pretty }} &nbsp; <div style="display:{{{ stats.actively_sending }}}"> <span class="pb_backupbuddy_loading" style="visibility: visible; float: left; margin-top: 0;" title="<?php esc_attr( _e( 'Files in transit to the Stash Server', 'it-l10n-backupbuddy' ) ); ?>"></span></div>
				</span>
				<span style="float: right; padding: 5px; display:{{{ stats.show_resume_link }}}""><a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=resume_periodic_step' ); ?>" title="<?php echo esc_attr( __( 'Click here if the process appears stalled', 'it-l10n-backupbuddy' ) ); ?>"><?php esc_html_e( 'Resume Process', 'it-l10n-backupbuddy' ); ?></a></span>
			</div>
		</div>
		<div class="col-wrap">
			<div class="col col-2-3">
				<div class="col col-1-3">
					<div class="backupbuddy-live-stats-database backupbuddy-live-stats-column <# if ( stats.continuous_status == '0' ) { #>paused <# } #>">
						<div class="backupbudy-live-stats-data-container">
							<div class="backupbuddy-live-stats-meta-overlay">
								<div class="backupbuddy-live-stats-meta-section">
									<span><?php _e( 'Last Activity', 'it-l10n-backupbuddy' ); ?></span>
									{{ stats.last_database_live_activity_ago }}
								</div>
								<div class="backupbuddy-live-stats-meta-section">
									<span><?php _e( 'Next Full Scan', 'it-l10n-backupbuddy' ); ?></span>
									{{ stats.next_db_snapshot_pretty }}
								</div>
							</div>
							<div class="backupbuddy-live-stats-column-heading">
								<?php _e( 'Database', 'it-l10n-backupbuddy' ); ?>
							</div>
							<div class="backupbuddy-live-stats-big-number">
								{{ stats.database_tables_sent }}
							</div>
							<div class="backupbuddy-live-stats-sub-numbers">
								<?php _e( 'of', 'it-l10n-backupbuddy' ); ?> {{ stats.database_tables_total }} <?php _e( 'Tables', 'it-l10n-backupbuddy' ); ?>
							</div>
							<div class="backupbuddy-live-stats-progress-bar-container">
								<div class="backupbuddy-live-stats-progress-bar">
									<div class="backupbuddy-live-stats-progress-bar-highlight <# if ( '100' != stats.database_tables_sent_percent ) { #>animate<# } #>" title="{{stats.database_tables_sent_percent }}%" style="width:{{ stats.database_tables_sent_percent }}%"></div>
								</div>
								<div class="backupbuddy-live-stats-progress-bar-percentage">
									{{{ Math.floor(stats.database_tables_sent_percent) }}}%
								</div>
							</div>
							<div class="backupbuddy-live-stats-total">
								{{ stats.database_size_pretty }}
							</div>
						</div>
						<div class="backupbuddy-live-stats-action">
							<# if ( stats.continuous_status == '0' ) { #>
								<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=resume_continuous' ); ?>" class="backupbuddy-live-button primary button button-primary"><span class="dashicons dashicons-controls-play"></span><?php _e( 'Resume', 'it-l10n-backupbuddy' ); ?></a>
							<# } else { #>
								<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=pause_continuous' ); ?>" class="backupbuddy-live-button secondary button button-primary"><span class="dashicons dashicons-controls-pause"></span><?php _e( 'Pause', 'it-l10n-backupbuddy' ); ?></a>
							<# } #>
						</div>
					</div>
				</div>
				<div class="col col-1-3">
					<div class="backupbuddy-live-stats-files backupbuddy-live-stats-column <# if ( stats.periodic_status == '0' ) { #>paused <# } #>">
						<div class="backupbudy-live-stats-data-container">
							<div class="backupbuddy-live-stats-meta-overlay">
								<div class="backupbuddy-live-stats-meta-section">
									<span><?php _e( 'Last Activity', 'it-l10n-backupbuddy' ); ?></span>
									{{ stats.last_periodic_activity_ago }}
								</div>
								<div class="backupbuddy-live-stats-meta-section">
									<span><?php _e( 'Next Full Scan', 'it-l10n-backupbuddy' ); ?></span>
									{{ stats.next_periodic_restart_pretty }}
								</div>
							</div>
							<div class="backupbuddy-live-stats-column-heading">
								<?php _e( 'Files', 'it-l10n-backupbuddy' ); ?>
							</div>
							<div class="backupbuddy-live-stats-big-number">
								{{ stats.files_sent }}
							</div>
							<div class="backupbuddy-live-stats-sub-numbers">
								<?php _e( 'of', 'it-l10n-backupbuddy' ); ?> {{ stats.files_total }} <?php _e( 'Files', 'it-l10n-backupbuddy' ); ?>
							</div>
							<div class="backupbuddy-live-stats-progress-bar-container">
								<div class="backupbuddy-live-stats-progress-bar">
									<div class="backupbuddy-live-stats-progress-bar-highlight <# if ( '100' != stats.files_sent_percent ) { #>animate<# } #>" title="{{ stats.files_sent_percent }}%"style="width:{{ stats.files_sent_percent }}%"></div>
								</div>
								<div class="backupbuddy-live-stats-progress-bar-percentage">
									{{{ Math.floor(stats.files_sent_percent) }}}%
								</div>
							</div>
							<div class="backupbuddy-live-stats-total">
								{{ stats.files_size_pretty }}
							</div>
						</div>
						<div class="backupbuddy-live-stats-action">
							<# if ( stats.periodic_status == '0' ) { #>
								<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=resume_periodic' ); ?>" class="backupbuddy-live-button primary button button-primary"><span class="dashicons dashicons-controls-play"></span><?php _e( 'Resume', 'it-l10n-backupbuddy' ); ?></a>
							<# } else { #>
								<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=pause_periodic' ); ?>" class="backupbuddy-live-button secondary button button-primary"><span class="dashicons dashicons-controls-pause"></span><?php _e( 'Pause', 'it-l10n-backupbuddy' ); ?></a>
							<# } #>
						</div>

					</div>
				</div>
				<div class="col col-1-3">
					<div class="backupbuddy-live-stats-storage backupbuddy-live-stats-column last">
						<div class="backupbuddy-live-stats-column-heading">
							<?php _e( 'Storage', 'it-l10n-backupbuddy' ); ?>
						</div>
						<div class="backupbuddy-live-stats-big-number">
							<# if ( stats.stash ) { #>
								{{ stats.stash.quota_used_nice }}
							<# } else { #>
								<?php _e( 'Pending', 'it-l10n-backupbuddy' ); ?>
							<# } #>
						</div>
						<div class="backupbuddy-live-stats-sub-numbers">
							<# if ( stats.stash ) { #>
								<?php _e( 'of', 'it-l10n-backupbuddy' ); ?> {{ stats.stash.quota_total_nice }} <?php _e( 'Used', 'it-l10n-backupbuddy' ); ?>
							<# } else { #>
								<?php echo __( 'Storage Amount Currently Unavailable', 'it-l10n-backupbuddy' ); ?>
							<# } #>
						</div>
						<div class="backupbuddy-live-stats-progress-bar-container">
							<# if ( stats.stash ) { #>
							<div class="backupbuddy-live-stats-progress-bar">
								<div class="backupbuddy-live-stats-progress-bar-highlight" title="{{ stats.stash.quota_used_percent }}%" style="width:{{ stats.stash.quota_used_percent }}%"></div>
							</div>
							<# } #>
							<div class="backupbuddy-live-stats-progress-bar-percentage">
								<# if ( stats.stash ) { #>
									{{{ Math.floor(stats.stash.quota_used_percent) }}}%
								<# } #>
							</div>
						</div>
						<div class="backupbuddy-live-stats-total"></div>
						<div class="backupbuddy-live-stats-action">
							<a href="https://go.solidwp.com/solid-backups-stash" target="_blank" class="backupbuddy-live-button secondary need-more"><?php _e( 'Need More Storage?', 'it-l10n-backupbuddy' ); ?></a><br>
							<a href="https://go.solidwp.com/solid-stash-central-account-" target="_blank" class="backupbuddy-live-button primary button button-primary"><?php _e( 'Manage Remote Files', 'it-l10n-backupbuddy' ); ?></a>
						</div>
					</div>
				</div>
			</div>
			<div class="col col-1-3">
				<div class="backupbuddy-live-stats-overview">
					<h3><?php _e( 'Solid Backups' ); ?> v<?php echo pb_backupbuddy::settings( 'version' ) ?> <?php _e( 'requested new Stash Live snapshot files', 'it-l10n-backupbuddy' ); ?>:</h3>
					<div class="backupbuddy-stats-time-ago">{{ stats.last_remote_snapshot_ago }}</div>
					<a href="javascript:void(0);" class="backupbuddy-stats-help-link" title="<?php esc_html_e( 'Stash Live Snapshot Details', 'it-l10n-backupbuddy' ); ?>">
						<?php _e( 'View Last Snapshot Details', 'it-l10n-backupbuddy' ); ?>
					</a>

					<div class="backupbuddy-stats-overview-create-snapshot">
						<p><?php _e( 'Need a more recent snapshot?', 'it-l10n-backupbuddy' ); ?></p>
						<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=create_snapshot' ); ?>" class="backupbuddy-live-button primary button button-primary"><?php _e( 'Create Manual Snapshot', 'it-l10n-backupbuddy' ); ?></a>
					</div>
				</div>
			</div>
		</div>
	</div>
</script>



<script>
	var snapshotStatusURL = '<?php echo pb_backupbuddy::ajax_url( 'live_snapshot_status' ); ?>'; // AJAX status check URL.

	jQuery(document).ready(function() {
		backupbuddy_live_stats( jQuery.parseJSON( '<?php echo json_encode( $stats ); ?>' ) );
		loadingIndicator = jQuery( '.pb_backupbuddy_loading' );

		jQuery(document).on( 'click', '.backupbuddy-stats-help-link', function(e) {
			e.preventDefault();
			jQuery( '.backupbuddy-stats-time-ago-explanation' ).addClass('visible');
		});

		jQuery(document).on( 'mouseleave', '.backupbuddy-stats-time-ago-explanation', function() {
			jQuery( '.backupbuddy-stats-time-ago-explanation' ).removeClass('visible');
		});
	});
</script>

<?php // Display remote files stored in Stash. ?>
<div class="backupbuddy_live_iframe_load">
	<span class="bb_progress-loading"></span> <?php _e( 'Loading remote Snapshot files list...', 'it-l10n-backupbuddy' ); ?>
</div>
<iframe id="backupbuddy_live-stash_iframe" data-refreshed="<?php echo time(); ?>" src="<?php echo pb_backupbuddy::ajax_url( 'live_stash_files' ); ?>" frameBorder="0" style="width: 100%;" onLoad="backupbuddy_resizeIframe(this); jQuery( '.backupbuddy_live_iframe_load' ).hide();">Error #433894473. Browser not compatible with iframes.</iframe>

<div class="backupbuddy-live-advanced-troubleshooting-button-container">
	<a href="javascript:void(0);" class="button button-secondary button-tertiary" onClick="jQuery('.backupbuddy-live-advanced-troubleshooting-wrap').slideToggle();">Advanced Troubleshooting Options</a>
	<a href="<?php echo pb_backupbuddy::ajax_url( 'live_troubleshooting_download' ); ?>" class="button button-secondary button-tertiary">Download Troubleshooting Data (.txt)</a>
</div>

<div class="backupbuddy-live-advanced-troubleshooting-wrap">
	<h1><?php esc_html_e( 'Advanced Troubleshooting Options', 'it-l10n-backupbuddy' ); ?></h1>
	<?php esc_html_e( 'Support may advise you to use these options to help work around a problem or troubleshoot issues. Caution is advised if using these options without guidance.', 'it-l10n-backupbuddy' ); ?>
	<br><br>

	<strong><?php esc_html_e( 'Function running at page load:', 'it-l10n-backupbuddy' ); ?></strong><br>
	<code><?php echo $state['step']['function']; ?> - <?php
	if ( 'update_files_list' == $state['step']['function'] ) {
		esc_html_e( '*Hidden due to size for this function*', 'it-l10n-backupbuddy' );
	} else {
		print_r( $state['step']['args'] );
	}
	?></code>
	<br><br>

	<strong><?php esc_html_e( 'Account:', 'it-l10n-backupbuddy' ); ?></strong><br>
	<?php echo $destination['itxapi_username']; ?> (<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=disconnect' ); ?>"><?php esc_html_e( 'Disconnect', 'it-l10n-backupbuddy' ); ?></a>)
	<br><br>

	<strong><?php esc_html_e( 'Stats by Day:', 'it-l10n-backupbuddy' ); ?></strong><br>
	<table class="widefat">
		<thead>
			<tr class="thead">
				<th>&nbsp;</th>
				<?php
				foreach( $state['stats']['daily'] as $date => $stat ) {
					echo '<th><strong>' . $date . '</strong></th>';
				}
				?>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td class="entry-row alternate"><?php esc_html_e( 'Database Tables Sent (Count)', 'it-l10n-backupbuddy' ); ?></td>
				<?php
				foreach( $state['stats']['daily'] as $stat ) {
					echo '<td class="entry-row alternate">' . $stat['d_t'] . '</td>';
				}
				?>
			</tr>
			<tr>
				<td class="entry-row alternate"><?php esc_html_e( 'Database Data Sent (Size)', 'it-l10n-backupbuddy' ); ?></td>
				<?php
				foreach( $state['stats']['daily'] as $stat ) {
					echo '<td class="entry-row alternate">' . pb_backupbuddy::$format->file_size( $stat['d_s'] ) . '</td>';
				}
				?>
			</tr>
			<tr>
				<td class="entry-row alternate"><?php esc_html_e( 'Files Sent (Count)', 'it-l10n-backupbuddy' ); ?></td>
				<?php
				foreach( $state['stats']['daily'] as $stat ) {
					echo '<td class="entry-row alternate">' . $stat['f_t'] . '</td>';
				}
				?>
			</tr>
			<tr>
				<td class="entry-row alternate"><?php esc_html_e( 'File Data Sent (Size)', 'it-l10n-backupbuddy' ); ?></td>
				<?php
				foreach( $state['stats']['daily'] as $stat ) {
					echo '<td class="entry-row alternate">' . pb_backupbuddy::$format->file_size( $stat['f_s'] ) . '</td>';
				}
				?>
			</tr>
		</tbody>
	</table>
	<br><br>

	<h4><?php esc_html_e( 'Actions:', 'it-l10n-backupbuddy' ); ?></h4>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=resume_periodic&skip_run_live_now=1' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'Unpause Periodic Without Running', 'it-l10n-backupbuddy' ); ?></a>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=reset_send_attempts' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'Reset Send Attempts', 'it-l10n-backupbuddy' ); ?></a>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=reset_file_audit_times' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'Reset File Audit Times', 'it-l10n-backupbuddy' ); ?></a>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=uncache_credentials' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'Uncache Live Credentials', 'it-l10n-backupbuddy' ); ?></a>

	<h4><?php esc_html_e( 'Time Stats:', 'it-l10n-backupbuddy' ); ?></h4>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=reset_last_activity' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'Reset Last Activity Time', 'it-l10n-backupbuddy' ); ?></a>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=reset_first_completion' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'Reset First Completion Time', 'it-l10n-backupbuddy' ); ?></a>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=reset_last_remote_snapshot' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'Reset Last Remote Snapshot Time', 'it-l10n-backupbuddy' ); ?></a>

	<h4><?php esc_html_e( 'Misc:', 'it-l10n-backupbuddy' ); ?></h4>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=last_snapshot_details' ); ?>" class="button button-secondary button-tertiary" style="<?php if ( '' == $state['stats']['last_remote_snapshot_id'] ) { echo 'opacity: 0.4;'; } ?>"><?php esc_html_e( 'Last Snapshot Details', 'it-l10n-backupbuddy' ); ?></a>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=delete_catalog' ); ?>" class="button button-secondary button-tertiary" onclick="if ( ! confirm( 'WARNING: This will erase your local catalog of files. All files may need to be re-uploaded. Are you sure you want to do this?') ) { return false; }"><?php esc_html_e( 'Delete Catalog & State', 'it-l10n-backupbuddy' ); ?></a>

	<h4><?php esc_html_e( 'Steps:', 'it-l10n-backupbuddy' ); ?></h4>
	<div style="margin-bottom: 13px;">
		<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=resume_periodic_step' ); ?>" class="button button-primary"><?php esc_html_e( 'Resume Process', 'it-l10n-backupbuddy' ); ?></a>
		<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=restart_periodic' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'Restart Process', 'it-l10n-backupbuddy' ); ?></a>
		<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=restart_periodic_force' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'Restart Process (force)', 'it-l10n-backupbuddy' ); ?></a>
	</div>
	<div>
		<form method="post" class="solid-backups-form" action="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=restart_at_step' ); ?>">
			<select name="live_step">
				<option value="daily_init"><?php esc_html_e( 'Daily Initialization', 'it-l10n-backupbuddy' ); ?></option>
				<option value="database_snapshot"><?php esc_html_e( 'Database Snapshot', 'it-l10n-backupbuddy' ); ?></option>
				<option value="send_pending_db_snapshots"><?php esc_html_e( 'Send Pending Database Snapshots', 'it-l10n-backupbuddy' ); ?></option>
				<option value="process_table_deletions"><?php esc_html_e( 'Process Table Deletions', 'it-l10n-backupbuddy' ); ?></option>
				<option value="update_files_list"><?php esc_html_e( 'Update Files List', 'it-l10n-backupbuddy' ); ?></option>
				<option value="update_files_signatures"><?php esc_html_e( 'Update Files Signatures', 'it-l10n-backupbuddy' ); ?></option>
				<option value="process_file_deletions"><?php esc_html_e( 'Process File Deletions', 'it-l10n-backupbuddy' ); ?></option>
				<option value="send_pending_files"><?php esc_html_e( 'Send Pending Files', 'it-l10n-backupbuddy' ); ?></option>
				<option value="audit_remote_files"><?php esc_html_e( 'Audit Remote Files', 'it-l10n-backupbuddy' ); ?></option>
				<option value="run_remote_snapshot"><?php esc_html_e( 'Run Remote Snapshot', 'it-l10n-backupbuddy' ); ?></option>
			</select>
			<input type="submit" name="submit" class="button button-secondary button-tertiary" value="Run at Step (Force)">
		</form>
	</div>
	<br style="clear: both;">

	<h4><?php esc_html_e( 'Raw Data:', 'it-l10n-backupbuddy' ); ?></h4>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=view_tables_raw' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'View Catalog Tables', 'it-l10n-backupbuddy' ); ?></a>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=view_signatures_raw' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'View Catalog Files', 'it-l10n-backupbuddy' ); ?></a>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=view_catalog_raw' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'View Entire Catalog', 'it-l10n-backupbuddy' ); ?></a>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=view_state' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'View Entire State', 'it-l10n-backupbuddy' ); ?></a>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=view_raw_settings' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'View Settings', 'it-l10n-backupbuddy' ); ?></a>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=view_stats' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'View Stats', 'it-l10n-backupbuddy' ); ?></a>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=view_exclusions' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'View Calculated Exclusions', 'it-l10n-backupbuddy' ); ?></a>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=view_unsent_files' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'View Unsent Files', 'it-l10n-backupbuddy' ); ?></a>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=view_unsent_files_noperms' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'View Unsent Files (hide permissions info)', 'it-l10n-backupbuddy' ); ?></a>

	<h4><?php esc_html_e( 'Pretty Data:', 'it-l10n-backupbuddy' ); ?></h4>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=view_signatures' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'View Formatted Catalog Files', 'it-l10n-backupbuddy' ); ?></a>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=view_tables' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'View Formatted Catalog Tables', 'it-l10n-backupbuddy' ); ?></a>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=view_files' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'View Remotely Stored Files', 'it-l10n-backupbuddy' ); ?></a>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=view_files_tables' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'View Remotely Stored Tables', 'it-l10n-backupbuddy' ); ?></a>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=troubleshooting' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'View Troubleshooting Data', 'it-l10n-backupbuddy' ); ?></a>

	<h4><?php esc_html_e( 'Logging:', 'it-l10n-backupbuddy' ); ?></h4>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=view_log' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'View Status Log', 'it-l10n-backupbuddy' ); ?></a>
	<a href="<?php echo pb_backupbuddy::nonce_url( $admin_url . '?page=pb_backupbuddy_live&live_action=clear_log' ); ?>" class="button button-secondary button-tertiary"><?php esc_html_e( 'Clear Status Log', 'it-l10n-backupbuddy' ); ?></a>

</div>

<?php
// Handles thickbox auto-resizing. Keep at bottom of page to avoid issues.
if ( ! wp_script_is( 'media-upload' ) ) {
	wp_enqueue_script( 'media-upload' );
	wp_print_scripts( 'media-upload' );
}
