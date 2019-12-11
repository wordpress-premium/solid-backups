<?php
/**
 * Perform Backup Page
 *
 * @package BackupBuddy
 */

pb_backupbuddy::load_style( 'backupProcess.css' );
pb_backupbuddy::load_style( 'backupProcess2.css' );

wp_enqueue_script( 'thickbox' );
wp_print_scripts( 'thickbox' );
wp_print_styles( 'thickbox' );
// Handles thickbox auto-resizing. Keep at bottom of page to avoid issues.
if ( ! wp_script_is( 'media-upload' ) ) {
	wp_enqueue_script( 'media-upload' );
	wp_print_scripts( 'media-upload' );
}

require_once pb_backupbuddy::plugin_path() . '/classes/backup.php';
$new_backup      = new pb_backupbuddy_backup();
$serial_override = pb_backupbuddy::random_string( 10 ); // Set serial ahead of time so can be used by AJAX before backup procedure actually begins.

// Deploy direction.
if ( 'push' === pb_backupbuddy::_GET( 'direction' ) ) {
	$direction      = 'push';
	$direction_text = ' - ' . __( 'Push', 'it-l10n-backupbuddy' );
} elseif ( 'pull' === pb_backupbuddy::_GET( 'direction' ) ) {
	$direction      = 'pull';
	$direction_text = ' - ' . __( 'Pull', 'it-l10n-backupbuddy' );
} else {
	$direction      = '';
	$direction_text = '';
}

// Title for page.
if ( 'deploy' == pb_backupbuddy::_GET( 'backupbuddy_backup' ) ) {
	pb_backupbuddy::$ui->title( 'Deploy Site' . $direction_text );
} else {
	pb_backupbuddy::$ui->title( 'Create Backup' );
}

if ( 'true' == pb_backupbuddy::_GET( 'quickstart_wizard' ) && ( true !== apply_filters( 'itbub_hide_quickwizard') ) ) {
	pb_backupbuddy::alert( 'Your Quick Setup Settings have been saved. Now performing your first backup...' );
}

$requested_profile = pb_backupbuddy::_GET( 'backupbuddy_backup' );
if ( 'deploy' == $requested_profile ) { // Grab profile number from post if deployment.
	$requested_profile = pb_backupbuddy::_POST( 'backup_profile' );
}
if ( 'db' == $requested_profile ) { // db profile is always index 1.
	$requested_profile = '1';
} elseif ( 'full' == $requested_profile ) { // full profile is always index 2.
	$requested_profile = '2';
}

$export_plugins = array(); // Default of no exported plugins. Used by MS export.
if ( pb_backupbuddy::_GET( 'backupbuddy_backup' ) == 'export' ) { // EXPORT.
	$export_plugins        = pb_backupbuddy::_POST( 'items' );
	$profile_array         = pb_backupbuddy::$options['profiles']['0']; // Run exports on default profile.
	$profile_array['type'] = 'export'; // Pass array with export type set.
} else { // NOT MULTISITE EXPORT.
	if ( is_numeric( $requested_profile ) ) {
		if ( isset( pb_backupbuddy::$options['profiles'][ $requested_profile ] ) ) {
			$profile_array = pb_backupbuddy::$options['profiles'][ $requested_profile ];
		} else {
			die( 'Error #84537483: Invalid profile ID `' . htmlentities( $requested_profile ) . '`. Profile with this number was not found. Try deactivating then reactivating the plugin. If this fails please reset the plugin Settings back to Defaults from the Settings page.' );
		}
	} else {
		die( 'Error #85489548955b. You cannot refresh this page to re-run it to prevent accidents. You will need to go back and try again. (Invalid profile ID not numeric: `' . htmlentities( $requested_profile ) . '`).' );
	}
}

$profile_array = array_merge( pb_backupbuddy::$options['profiles'][0], $profile_array ); // Merge defaults.


// Set up $deploy_data if deployment.
if ( 'deploy' == pb_backupbuddy::_GET( 'backupbuddy_backup' ) ) {
	$deploy_data_raw = base64_decode( pb_backupbuddy::_POST( 'deployData' ) );
	if ( false === $deploy_data_raw ) {
		pb_backupbuddy::alert( 'Error #984854784: Unable to decode input. Data: `' . htmlentities( pb_backupbuddy::_POST( 'deployData' ) ) . '`.', true );
		return false;
	}
	$deploy_data = json_decode( $deploy_data_raw, true );
	if ( false === $deploy_data ) {
		pb_backupbuddy::alert( 'Error #382954735: Unable to unserialize input. Data: `' . htmlentities( $deploy_data_raw ) . '`.', true );
		return false;
	}
	unset( $deploy_data_raw );

	$profile_array['backup_nonwp_tables']    = '2';
	$profile_array['profile_globaltables']   = '0';
	$profile_array['profile_globalexcludes'] = '0';

	$profile_array['mysqldump_additional_includes'] = implode( "\n", (array) pb_backupbuddy::_POST( 'tables' ) );
	$tables = (array) pb_backupbuddy::_POST( 'tables' );
	$tables = array_filter( $tables );
	if ( 0 === count( $tables ) ) {
		$profile_array['skip_database_dump'] = '1';
	}
}

?>
<style>
	#backupbuddy_messages {
		background: #fff;
	}
	.backupbuddy_log_error {
		color: red;
		font-weight: bold;
	}
	.backupbuddy_log_warning {
		color: orange;
		font-weight: bold;
	}
	.backupbuddy_log_notice {
		color: blue;
		font-weight: bold;
	}
</style>
<script type="text/javascript">
	window.onerror = function( errorMsg, url, lineNumber, colNumber, error ){
		alert( "Error #82389: <?php _e( 'A javascript error occurred which may prevent the backup from continuing. Check your browser error console for details. This is most often caused by another plugin or theme containing broken javascript. See details below for clues or try temporarily disabling all other plugins.', 'it-l10n-backupbuddy' ); ?>\n\nDetails: `" + errorMsg + "`.\n\nURL: `" + url + "`.\n\nLine: `" + lineNumber + "`." );
		backupbuddy_log( 'Javascript Error. Message: `' + errorMsg + '`, URL: `' + url + '`, Line: `' + lineNumber + '`, Col: `' + colNumber + '`, Trace: `' + error.stack + '`.' ); // Attempt to log.
	}

	var statusBox, // #backupbuddy_messages
		statusBoxQueueEnabled = true, // When true status box updates will queue to prevent DOM from being flooded and freezing. Set false when backup is ending to prevent anything from not being shown.
		statusBoxQueue = '', // Queue of text to append into message box.
		statusBoxLastAppendTime = 0, // Store timestamp of last append to prevent updating the DOM too often. Cache in memory until 1 second (or some reasonable period) passes between appends.
		statusBoxAutoScroll = true,
		statusBoxLimit = false,  // False for no limit.  Integer for limiting to latest X lines. Number of lines set in backupbuddy_constants::BACKUP_STATUS_BOX_LIMIT_OPTION_LINES.

		stale_archive_time_trigger = 30, // If this time ellapses without archive size increasing warn user that something may have gone wrong.
		stale_sql_time_trigger = 30, // If this time ellapses without archive size increasing warn user that something may have gone wrong.
		stale_archive_time_trigger_increment = 1, // Number of times the popup has been shown.
		stale_sql_time_trigger_increment = 1, // Number of times the popup has been shown.
		backupbuddy_errors_encountered = 0, // number of errors sent via log.
		last_archive_size = 0, // makes in scope later.
		last_sql_size = 0, // makes in scope later.
		current_sql_file = '', // current sql filename. ex tablename.sql. Used when querying for current status so we can get its file size.
		keep_polling = 1,
		last_archive_change = 0, // Time where archive size last changed.
		last_sql_change = 0, // Time where sql file size last changed.
		backup_init_complete_poll_retry_count = 8, // How many polls to wait for backup init to complete
		seconds_before_verifying_cron_schedule = 15, // How many seconds must elapse while in the cronPass action before polling WP to check and see if the schedule exists.
		status_503_retry_limit = 250, // How many times should we retry when we recieve a 503 (probably because of .maintenance) before giving up?
		status_503_retry_count = 0, // The number of times we have retried the poll because of a 503 error.

		// Vars used by events.
		backupbuddy_currentFunction = '',
		backupbuddy_currentAction = '',
		backupbuddy_currentActionStart = 0,
		backupbuddy_currentActionLastWarn = 0,
		suggestions = [],
		backupbuddy_currentDatabaseSize = 0,
		backupbuddy_cancelClicked = false,
		backupbuddy_serial = '<?php echo esc_html( $serial_override ); ?>',
		isInDeployLog = false,

		// Misc
		statusURL = '<?php echo pb_backupbuddy::ajax_url( 'backup_status' ); ?>', // AJAX status check URL. Gets log from server for this backup / process.
		loadingIndicator = ''; // jQuery('.pb_backupbuddy_loading') for speed.

	// Tells BackupBuddy to stop the running backup.
	function backupbuddy_ajax_call_stop( callback ) {
		jQuery.post( '<?php echo pb_backupbuddy::ajax_url( 'stop_backup' ); ?>', { serial: '<?php echo esc_html( $serial_override ); ?>' },
			function(data) {
				data = jQuery.trim( data );
				if ( data.charAt(0) != '1' ) {
					jQuery('#pb_backupbuddy_status').trigger( 'backupbuddy_haltScript' );
					keep_polling = 0;
					alert( "<?php _e( 'Error stopping backup.', 'it-l10n-backupbuddy' ); ?> Server responded: " + "\n\n" + '`' + data + '`.' );
				} else {
					jQuery( '#pb_backupbuddy_stop' ).html( 'Backup Cancelled' );
					jQuery( '#pb_backupbuddy_stop' ).attr( 'disabled', 'disabled' );
					if ( 'undefined' != typeof callback ) {
						callback();
					}
				}
			}
		);
	}

	jQuery(function() {

		loadingIndicator = jQuery( '.pb_backupbuddy_loading' );

		// Scroll to top on clicking Status tab.
		jQuery( '.nav-tab-1' ).click( function(){
			statusBox = jQuery( '#backupbuddy_messages' );
			if ( ( statusBox.length == 0 ) || ( 'undefined' == typeof statusBox[0] ) ) { // No status box yet so don't scroll.
				return;
			} else {
				statusBox.scrollTop( statusBox[0].scrollHeight - statusBox.height() );
			}
		});

		<?php
		// For MODERN mode we will wait until the DOM fully loads before beginning polling the server status.
		if ( '1' != $profile_array['backup_mode'] ) { // NOT classic mode. Run once doc ready.
			echo "setTimeout( 'backupbuddy_poll()', 500 );";
		}
		?>

		jQuery( '#pb_backupbuddy_archive_send' ).click( function(e) {
			e.preventDefault();
			jQuery( '.bb_actions_remotesent' ).hide();
			jQuery('.bb_destinations').toggle();
		});

		jQuery('.bb_destinations-existing .bb_destination-item a').click( function(e){
			e.preventDefault();

			if ( jQuery(this).parent().hasClass( 'bb_destination-item-disabled' ) ) {
				alert( 'This remote destination is unavailable.  It is either disabled in its Advanced Settings or not compatible with this server.' );
				return false;
			}

			destinationID = jQuery(this).attr( 'rel' );
			console.log( 'Send to destinationID: `' + destinationID + '`.' );
			pb_backupbuddy_selectdestination( destinationID, jQuery(this).attr( 'title' ), jQuery('#pb_backupbuddy_archive_send').attr('rel'), jQuery('#pb_backupbuddy_remote_delete').is(':checked') );
		});

		jQuery( '.bb_destination-new-item a' ).click( function(e){
			e.preventDefault();

			if ( jQuery(this).parent('.bb_destination-item').hasClass('bb_destination-item-disabled') ) {
				alert( 'Error #848448: This destination is not available on your server.' );
				return false;
			}

			tb_show( 'BackupBuddy', '<?php echo pb_backupbuddy::ajax_url( 'destination_picker' ); ?>&add=' + jQuery(this).attr('rel') + '&filter=' + jQuery(this).attr('rel') + '&callback_data=' + jQuery('#pb_backupbuddy_archive_send').attr('rel') + '&sending=1&TB_iframe=1&width=640&height=455', null );
		});

		jQuery( '#pb_backupbuddy_stop' ).click( function(e) {
			e.preventDefault();

			statusBoxQueueEnabled = false;
			backupbuddy_cancelClicked = true;

			setTimeout(function(){
				jQuery( '.backup-step-active').removeClass('backup-step-active');
				jQuery( '.bb_progress-step-active').removeClass('bb_progress-step-active');
			},2200);

			//jQuery( '.backup-step-active').addClass('backup-step-error').removeClass('backup-step-active');
			jQuery( '.backup-step-active').removeClass('backup-step-active');
			jQuery( '.bb_progress-step-active').removeClass('bb_progress-step-active');
			jQuery( '.bb_progress-step-unfinished').addClass( 'bb_progress-step-completed' );
			jQuery( '.bb_progress-step-unfinished').addClass( 'bb_progress-step-error' );
			jQuery( '.bb_progress-step-unfinished').find( '.bb_progress-step-title').text( '<?php _e( 'Cancelled', 'it-l10n-backupbuddy' ); ?>' );
			jQuery( '.bb_progress-error-bar' ).text( '<?php _e( 'You cancelled the backup process.', 'it-l10n-backupbuddy' ); ?>').show();
			jQuery( '.bb_actions').hide();
			jQuery( '.pb_actions_cancelled').show();
			jQuery( '.bb_progress-step-unfinished').removeClass( 'bb_progress-step-unfinished' );

			jQuery(this).html( 'Cancelling ...' );
			backupbuddy_log( '' );
			backupbuddy_log( "***** BACKUP CANCELLED - Forcing backup to skip to cleanup step as soon as possible. *****" );
			backupbuddy_log( '' );

			backupbuddy_ajax_call_stop();

			return false;
		});

		jQuery( '.pb_backupbuddy_deployUndo' ).click( function(){
			backupbuddy_ajax_call_stop();
			return true;
		});

		// Toggle auto scrolling on/off. Set as var for faster checking by rapidly updating status box.
		jQuery( '#backupbuddy-status-autoscroll' ).click( function(){
			if ( jQuery(this).is(':checked') ) {
				statusBoxAutoScroll = true;
			} else {
				statusBoxAutoScroll = false;
			}
		});

		jQuery( '#backupbuddy-status-limit' ).click( function(){
			if ( jQuery(this).is(':checked') ) {
				statusBoxLimit = <?php echo backupbuddy_constants::BACKUP_STATUS_BOX_LIMIT_OPTION_LINES; ?>;
			} else {
				statusBoxLimit = false;
			}
		});

		<?php if ( isset( $deploy_data ) ) { ?>
			jQuery( '.btn-confirm-deploy' ).click( function(){
				confirmDeployButton = jQuery(this);
				jQuery.post( '<?php echo pb_backupbuddy::ajax_url( 'deploy_confirm' ); ?>', { serial: '<?php echo esc_html( $serial_override ); ?>', direction: '<?php echo $direction; ?>', destinationID: '<?php echo esc_html( $deploy_data['destination_id'] ); ?>' },
					function(data) {
						data = jQuery.trim( data );
						if ( data.charAt(0) != '1' ) {
							alert( "<?php _e( 'Error confirming deployment.', 'it-l10n-backupbuddy' ); ?> Server responded: " + "\n\n" + '`' + data + '`.' + "\n\n" + 'Charcode at 0: `' + data.charCodeAt(0) + '`. Expected charcode: `' + ('1').charCodeAt(0) + '`.' );
						} else { // hide confirm button.
							backupbuddy_log( '*** Deployment changes confirmed by user.' );
							confirmDeployButton.css( 'visibility', 'hidden' );
						}
					}
				);

				return true;
			});
		<?php } ?>

	}); // end on jquery ready.

	<?php
	if ( '1' == $profile_array['backup_mode'] ) { // CLASSIC mode. Run right away so we can show output before page finishes loading (backup fully finishes).
		echo "setTimeout( 'backupbuddy_poll()', 2000 );";
	}
	?>

	function backupbuddy_showSuggestions( suggestionList ) {
		for ( var k in suggestionList ){
			backupbuddy_log( '*** POSSIBLE ISSUE ***' );
			backupbuddy_log( '* ABOUT: ' + suggestionList[k].description );
			backupbuddy_log( '* POSSIBLE FIX: ' + suggestionList[k].quickFix );
			backupbuddy_log( '* MORE INFORMATION: ' + suggestionList[k].solution );
			backupbuddy_log( '***' );
		}
	}

	function backupbuddy_bytesToSize( bytes ) {
		var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
		if ( bytes == 0 ) {
			return '0 Byte';
		}
		var i = parseInt( Math.floor( Math.log( bytes ) / Math.log( 1024 ) ) );
		return ( bytes / Math.pow( 1024, i ) ).toFixed( 2 ) + ' ' + sizes[i];
	};


	function pb_backupbuddy_selectdestination( destination_id, destination_title, callback_data, delete_after, mode ) {
		if ( '' != callback_data ) {
			jQuery.post( '<?php echo pb_backupbuddy::ajax_url( 'remote_send' ); ?>', { destination_id: destination_id, destination_title: destination_title, file: callback_data, trigger: 'manual', delete_after: delete_after },
				function( data ) {
					data = jQuery.trim( data );
					if ( data.charAt(0) != '1' ) {
						alert( "<?php _e( 'Error starting remote send', 'it-l10n-backupbuddy' ); ?>:" + "\n\n" + data );
					} else {
						jQuery( '.bb_actions_remotesent' ).text( "<?php _e( 'Your file has been scheduled to be sent now. It should arrive shortly.', 'it-l10n-backupbuddy' ); ?> <?php _e( 'You will be notified by email if any problems are encountered.', 'it-l10n-backupbuddy' ); ?>" + "\n\n" + data.slice(1) ).show();
						jQuery('.bb_destinations').hide();
					}
				}
			);

			/* Try to ping server to nudge cron along since sometimes it doesnt trigger as expected. */
			jQuery.post( '<?php echo admin_url( 'admin-ajax.php' ); ?>' );
		} else {
			<?php
			$admin_url = admin_url( 'admin.php' );
			if ( is_network_admin() ) {
				$admin_url = network_admin_url( 'admin.php' );
			}
			?>
			window.location.href = '<?php echo $admin_url; ?>?page=pb_backupbuddy_backup&custom=remoteclient&destination_id=' + destination_id;
		}
	}

	/***** LOOK & FEEL *****/
	function backupbuddy_redstatus() {
		jQuery( '#ui-id-2' ).css( 'background', '#FF8989' );
		jQuery( '#ui-id-2' ).css( 'color', '#000000' );
	}

	/***** HELPER FUNCTIONS *****/
	function unix_timestamp() {
		return Math.round( ( new Date() ).getTime() / 1000 );
	}

	function backupbuddy_poll_altcron() {
		if ( keep_polling != 1 ) {
			return;
		}

		jQuery.get( '<?php echo admin_url( 'admin.php' ) . '?pb_backupbuddy_alt_cron=true'; ?>' );
	}

	/***** BACKUP STATUS *****/
	/**
	 * Note: Used in BackupBuddy _backup-perform.php and ImportBuddy _header.php, & maybe more.
	 *
	 * @param {object} json       json of status to log OR plaintext string.
	 * @param {string} classType  Applies a specific class for coloring message.
	 */
	function backupbuddy_log( json, classType ) {

		if( 'undefined' === typeof statusBox ) { // No status box yet so may need to create it.
			statusBox = jQuery( '#backupbuddy_messages' );
			if( statusBox.length == 0 ) { // No status box yet so suppress.
				return;
			}
		}

		maybeDeployPrefix = '';
		if ( true === isInDeployLog ) {
			maybeDeployPrefix = '* ';
		}

		message = '';
		if ( 'string' == ( typeof json ) ) {
			if ( '' !== classType ) {
				json = '<span class="backupbuddy_log_' + classType + '">' + json + '</span>';
			}
			message = "-----------\t\t-------\t-------\t" + maybeDeployPrefix + json;
		} else {
			if ( '' !== classType ) {
				json.data = '<span class="backupbuddy_log_' + classType + '">' + json.data + '</span>';
			}
			message = json.date + '.' + json.u + " \t" + json.run + "sec \t" + json.mem + "MB\t" + maybeDeployPrefix + json.data;
		}

		statusBoxQueue = statusBoxQueue + "\r\n" + message;

		if ( false === statusBoxQueueEnabled || ( ( new Date().getTime() ) - statusBoxLastAppendTime ) > 1000 ) { // Queue up any updates that happens faster than once per second and append all at once.

			if ( false !== statusBoxLimit ) { // If limiting status box length, check new length and slice it as needed.

				var tempStatusContents = ( statusBox.html() + statusBoxQueue ).split( "\n" ); // Get existing contents (if any) + new contents & split on newlines.
				if ( tempStatusContents.length > statusBoxLimit ) { // If over limit then slice off the beginning lines.
					tempStatusContents = tempStatusContents.slice( tempStatusContents.length - statusBoxLimit );
				}
				statusBox.html( tempStatusContents.join( "\n" ) ); // Replace current content with new shortened version.
				tempStatusContents = ''; // Clear temp var.

			} else { // Normal, unlimited length status box.

				statusBox.append( statusBoxQueue ); // Append to existing content.

			}

			if ( false !== statusBoxAutoScroll ) { // Scroll to bottom of status box unless disabled.
				if ( ( statusBox.length == 0 ) || ( 'undefined' == typeof statusBox[0] ) ) { // No status box yet so don't scroll.
					return;
				} else {
					statusBox.scrollTop( statusBox[0].scrollHeight - statusBox.height() );
				}
			}

			statusBoxLastAppendTime = new Date().getTime(); // Mark last time we updated status box.
			statusBoxQueue = ''; // Clear out queue.
		}
	}

	// left hour pad with zeros
	function backupbuddy_hourpad( n ) {
		return ( "0" + n ).slice( -2 );
	}

	backup_maybe_timed_out = 'Creating the backup archive may have timed out';
	backup_db_maybe_timed_out = 'Creating the database backup archive may have timed out';

	function backupbuddy_poll() {
		if ( keep_polling != 1 ) {
			if ( '' != statusBoxQueue ) { // If something left in queue then push it out. Should be checked elsewhere but just in case...
				statusBoxQueueEnabled = false;
				setTimeout(function(){
					backupbuddy_log( '* Backup finished.' );
				}, 500 );
			}
			return;
		}

		// Check to make sure archive size is increasing. Warn if it seems to hang.
		if ( 0 != last_archive_change && ( unix_timestamp() - last_archive_change ) > stale_archive_time_trigger ) {
			thisMessage = 'Warning: The backup archive file size has not increased in ' + stale_archive_time_trigger + ' seconds. If it does not increase in the next few minutes it most likely timed out. If the backup proceeds ignore this warning.';
			//alert( thisMessage + "Subsequent warnings will be displayed in the Status Log which contains more details." );
			backupbuddy_log( '***', 'notice' );
			backupbuddy_log( thisMessage, 'notice' );
			backupbuddy_log( '***', 'notice' );
			errorHelp( backup_maybe_timed_out, thisMessage );

			stale_archive_time_trigger = 60 * 5 * stale_archive_time_trigger_increment;
			stale_archive_time_trigger_increment++;
		} else {
			jQuery( '.backup-step-error-message_' + bb_hashCode( backup_maybe_timed_out ) ).slideUp();
		}

		// Check to make sure sql dump size is increasing. Warn if it seems to hang.
		if ( 0 != last_sql_change && ( unix_timestamp() - last_sql_change ) > stale_sql_time_trigger ) {
			thisMessage = 'Warning: The SQL database dump file size has not increased in ' + stale_sql_time_trigger + ' seconds. If it does not increase in the next few minutes it most likely timed out. If the backup proceeds ignore this warning.';
			backupbuddy_log( '***', 'notice' );
			backupbuddy_log( thisMessage, 'notice' );
			backupbuddy_log( '***', 'notice' );
			errorHelp( 'Creating the database backup may have timed out', thisMessage );

			stale_sql_time_trigger = 60 * 5 * stale_sql_time_trigger_increment;
			stale_sql_time_trigger_increment++;
		} else {
			jQuery( '.backup-step-error-message_' + bb_hashCode( backup_db_maybe_timed_out ) ).slideUp();
		}

		specialAction = '';
		if ( 0 != loadingIndicator.length ) {
			loadingIndicator.show();
		}
		backupbuddy_log( 'Ping? Waiting for server . . .' );
		if ( 'cronPass' == backupbuddy_currentAction ) { // In cronPass action...
			if ( ( unix_timestamp() - backupbuddy_currentActionStart ) > seconds_before_verifying_cron_schedule ) {
				backupbuddy_log( 'It has been ' + ( unix_timestamp() - backupbuddy_currentActionStart ) + ' seconds since the next step was scheduled. Checking cron schedule.' );
				specialAction = 'checkSchedule';
			}
		}

		jQuery.ajax({
			url:	statusURL,
			type:	'post',
			data:	{ serial: '<?php echo esc_html( $serial_override ); ?>', initwaitretrycount: backup_init_complete_poll_retry_count, specialAction: specialAction, sqlFile: current_sql_file },
			context: document.body,
			success: function( data ) {

				if ( 0 != loadingIndicator.length ) {
					loadingIndicator.hide();
				}

				data = data.split( "\n" );
				for( var i = 0; i < data.length; i++ ) {

					isJSON = false;
					try {
						var json = jQuery.parseJSON( data[i] );
						isJSON = true;
					} catch(e) { // NOT json.
						if ( data[i].indexOf( 'Fatal PHP error' ) > -1 ) {
							backupbuddyError( data[i], 'PHP Error' );
							backupbuddy_log( 'Fatal PHP Error: ' + data[i] );
						} else if ( data[i].indexOf( 'Error' ) > -1 ) {
							backupbuddyError( data[i], 'Direct Error' );
							backupbuddy_log( 'Error (direct): ' + data[i], 'error' );
						} else if ( data[i].indexOf( 'Warning' ) > -1 ) {
							backupbuddy_log( 'Warning (direct): ' + data[i], 'warning' );
						} else {
							<?php if ( '3' == pb_backupbuddy::$options['log_level'] ) { ?>
								console.log( 'BackupBuddy non-json:' + data[i] );
							<?php } ?>
						}
						isJSON = false;
					}

					// Used in BackupBuddy _backup-perform.php and ImportBuddy _header.php
					if ( ( true === isJSON ) && ( 'object' === typeof json ) && ( null !== json ) ) { // non-empty json
						json.date = new Date();
						json.date = new Date(  ( json.time * 1000 ) + json.date.getTimezoneOffset() * 60000 );
						var seconds = json.date.getSeconds();
						if ( seconds < 10 ) {
							seconds = '0' + seconds;
						}
						json.date = backupbuddy_hourpad( json.date.getHours() ) + ':' + json.date.getMinutes() + ':' + seconds;

						triggerEvent = 'backupbuddy_' + json.event;

						// Log non-text events.
						if ( ( 'details' !== json.event ) && ( 'message' !== json.event ) && ( 'error' !== json.event ) ) {
							//console.log( 'Non-text event `' + triggerEvent + '`.' );
							//console.log( json.data );
						} else {
							//console.log( json.data );
						}

						if( 'undefined' === typeof statusBox ) { // No status box yet so may need to create it.
							statusBox = jQuery( '#backupbuddy_messages' );
							if( statusBox.length == 0 ) { // No status box yet so suppress.
								continue;
							}
						}
						statusBox.trigger( triggerEvent, [json] );
					} else if ( ( false === isJSON ) && ( '' !== data[i] ) ) { // non-empty string
						data[i] = data[i].trim();
						if ( 'PHP_ERROR' == data[i].substr( 0, 9 ) ) { // Directly display fatal PHP errors output by shutdown function.
							backupbuddy_log( backupbuddyError( data[i].substr( 9 ) ) );
						} else if ( 'START_DEPLOY' == data[i] ) {
							isInDeployLog = true;
						} else if ( 'END_DEPLOY' == data[i] ) {
							backupbuddy_log( '*** End External Log (ImportBuddy)' );
							isInDeployLog = false;
						} else {
							//backupbuddy_log( '~~~ (direct): ' + data[i] );
						}
					} else if ( 0 == json ) {
						message = 'Error #9999383: Server responded with 0 which usually means your session has expired and you have been logged out or wp-ajax failed.';
						//alert( message );
						backupbuddy_log( message );
					}

					continue;

				} // end for.

				// Set the next server poll if applicable to happen in 2 seconds.
				setTimeout( 'backupbuddy_poll()' , 2000 );
				<?php
				// Handles alternate WP cron forcing.
				if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) {
					echo '	setTimeout( \'backupbuddy_poll_altcron()\', 2000 );';
				}
				?>

			}, // end success.

			error : function( xhr ) {
				if ( xhr.status == 503 ) {
					status_503_retry_count++;
					if ( status_503_retry_count <= status_503_retry_limit ) {
						backupbuddy_log( 'Recieved 503 error on AJAX request. About to attempt retry ' + status_503_retry_count + ' of ' + status_503_retry_limit + ' retries.' );
						setTimeout( 'backupbuddy_poll()' , 6000 );
						return;
					} else {
						keep_polling = 0;
						backupbuddy_log( 'Error: Recieved 503 error on AJAX request. Reached retry attempt limit. Aborting.' );
						setTimeout( function() {
							backupbuddy_poll();
						}, 2000 );
						backupbuddy_finishbackup()
						jQuery('.pb_backupbuddy_deployUndo').hide();
						return;
					}
				}
			}, // end error

			error: function( xhr ) {
				if ( 503 == xhr.status ) {
					status_503_retry_count++;
					if ( status_503_retry_count <= status_503_retry_limit ) {
						backupbuddy_log( 'Recieved 503 error on AJAX request. About to attempt retry ' + status_503_retry_count + ' of ' + status_503_retry_limit + ' retries.' );
						setTimeout( 'backupbuddy_poll()' , 6000 );
						return;
					} else {
						keep_polling = 0;
						backupbuddy_log( 'Error: Recieved 503 error on AJAX request. Reached retry attempt limit. Aborting.' );
						setTimeout( function() {
							backupbuddy_poll();
						}, 2000 );
						backupbuddy_finishbackup()
						jQuery('.pb_backupbuddy_deployUndo').hide();
						return;
					}
				}
			}, // end error

			complete: function( jqXHR, status ) {
				if ( ( status != 'success' ) && ( status != 'notmodified' ) ) {
					if ( 0 != loadingIndicator.length ) {
						loadingIndicator.hide();
					}
				}
			} // end complete.

		}); // end ajax.

		// Check runtime of current action...
		if ( '' !== backupbuddy_currentAction ) {
			actionRunTime = unix_timestamp() - backupbuddy_currentActionStart;
			sinceLastWarn = ( unix_timestamp() - backupbuddy_currentActionLastWarn );


			if ( 'cronPass' == backupbuddy_currentAction ) {
				if ( ( actionRunTime > 20 ) && ( sinceLastWarn > 45 ) ) { // sinceLastWarn is large number (timestamp) until set first time. so triggers off actionRunTime solely first.
					backupbuddy_currentActionLastWarn = unix_timestamp();
					thisSuggestion = {
						description: 'BackupBuddy uses WordPress\' scheduling system (cron) for running each backup step. Sometimes something interferes with this scheduling preventing the next step from running.',
						quickFix: 'If there are delays but the backup proceeds anyway then you can ignore this. If not, you will need to narrow down the problem first.',
						solution: 'Narrow down the problem: Check "Server Tools --> wp-cron.php Loopbacks" for more info. Run BackupBuddy in classic mode which bypasses the cron. Navigate to Settings: Advanced Settings / Troubleshooting tab: Change "Default global backup method" to Classic Mode (v1.x). If either of these fixes it, another plugin is most likely the cause is a malfunctioning plugin or a server problem. Disable all other plugins to see if this solves the problem. If it does then it is a problem plugin. Enable one by one until the problem returns to determine the culprit.'
					};
					suggestions['cronPass'] = thisSuggestion;
					backupbuddy_showSuggestions( [thisSuggestion] );
				}
			}


			if ( 'importbuddyCreation' == backupbuddy_currentAction ) {
				if ( ( actionRunTime > 10 ) && ( sinceLastWarn > 30 ) ) { // sinceLastWarn is large number (timestamp) until set first time. so triggers off actionRunTime solely first.
					backupbuddy_currentActionLastWarn = unix_timestamp();
					thisSuggestion = {
						description: 'BackupBuddy by default includes a copy of the restore tool, importbuddy.php, inside the backup ZIP file for retrieval if needed in the future.',
						quickFix: 'Turn off inclusion of ImportBuddy. Navigate to Settings: Advanced Settings / Troubleshooting tab: Uncheck "Include ImportBuddy in full backup archive".',
						solution: 'Increase available PHP memory.'
					};
					suggestions['importbuddyCreation'] = thisSuggestion;
					backupbuddy_showSuggestions( [thisSuggestion] );
				}
			}


			if ( 'zipCommentMeta' == backupbuddy_currentAction ) {
				if ( ( actionRunTime > 10 ) && ( sinceLastWarn > 30 ) ) { // sinceLastWarn is large number (timestamp) until set first time. so triggers off actionRunTime solely first.
					backupbuddy_currentActionLastWarn = unix_timestamp();
					thisSuggestion = {
						description: 'Some servers have trouble adding in a zip comment to files after they are created. Disabling this option skips this step. This meta data is not required so disabling it is not a problem.',
						quickFix: 'Turn off zip saving meta data in comments. Navigate to Settings: Advanced Settings / Troubleshooting tab: Uncheck "Save meta data in comment" to disable saving it.',
						solution: 'Increasing overall resources may help if you wish to keep this enabled.'
					};
					suggestions['zipCommentMeta'] = thisSuggestion;
					backupbuddy_showSuggestions( [thisSuggestion] );
				}
			}
		} // end if an action is running.
	} // end backupbuddy_poll().

	function pb_status_append( status_string ) {
		target_id = 'backupbuddy_messages'; // importbuddy_status or pb_backupbuddy_status
		if( jQuery( '#' + target_id ).length == 0 ) { // No status box yet so suppress.
			return;
		}
		jQuery( '#' + target_id ).append( "\n" + status_string );
		textareaelem = document.getElementById( target_id );
		textareaelem.scrollTop = textareaelem.scrollHeight;
	}

	// Trigger an error to be logged, displayed, etc.
	// Returns updated message with trouble URL, etc.
	// Used in BackupBuddy _backup-perform.php and ImportBuddy _header.php
	function backupbuddyError( message, title ) {

		// Get start of any error numbers.
		troubleURL = '';
		error_number_begin = message.toLowerCase().indexOf( 'error #' );

		if ( error_number_begin >= 0 ) {
			error_number_begin += 7; // Shift over index to after 'error #'.
			error_number_end = message.toLowerCase().indexOf( ':', error_number_begin );
			if ( error_number_end < 0 ) { // End still not found.
				error_number_end = message.toLowerCase().indexOf( '.', error_number_begin );
			}
			if ( error_number_end < 0 ) { // End still not found.
				error_number_end = message.toLowerCase().indexOf( ' ', error_number_begin );
			}
			error_number = message.slice( error_number_begin, error_number_end );
			rawMessage = message.slice( error_number_end + 2 );
			troubleURL = 'https://ithemeshelp.zendesk.com/hc/en-us/articles/211132377-Error-Codes-#4001' + error_number;
			if ( 'undefined' === typeof title ) {
				title = 'Error #' + error_number;
			}
		} else {
			rawMessage = message;
		}
		if ( 'undefined' === typeof title ) {
			title = 'Alert';
		}

		if ( '' !== troubleURL ) {
			errorHelp( 'Alert', '<a href="' + troubleURL + '" target="_blank">' + title + '</a>', rawMessage + ' <a href="' + troubleURL + '" target="_blank">Click to <b>view error details</b> in the Knowledge Base</a>' );
		} else {
			errorHelp( title, rawMessage );
		}

		// Display error box to make it clear errors were encountered.
		backupbuddy_errors_encountered++;
		jQuery( '#backupbuddy_errors_notice_count' ).text( backupbuddy_errors_encountered );
		jQuery( '#backupbuddy_errors_notice' ).slideDown();

		// Make Status tab red.
		jQuery( '.nav-tab-1' ).addClass( 'bb-nav-status-tab-error' );

		// If the word error is nowhere in the error message then add in error prefix.
		if ( message.toLowerCase().indexOf( 'error' ) < 0 ) {
			message = 'ERROR: ' + message;
		}

		return message; // Return updated error message with trouble URL.
	} // end backupbuddyError().

	// Used in BackupBuddy _backup-perform.php and ImportBuddy _header.php
	function backupbuddyWarning( message ) {
		return 'Warning: ' + message;
	} // end backupbuddyWarning().

	var shownErrorHelps = [];
	function errorHelp( title, message ) {
		if ( shownErrorHelps.indexOf( title ) > -1 ) {
			return; // Already been shown on page.
		}
		shownErrorHelps.push( title ); // Add to list of shown errors so it will not be shown multiple times.
		errorHTML = '<div class="backup-step-error-message backup-step-error-message_' + bb_hashCode( title ) + '"><h3>' + title + '</h3>' + message + '</div>';

		if ( jQuery('.backup-step-active').length > 0 ) { // Target active function if currently is one, else target one after last to finish.
			targetObj = jQuery('.backup-step-active');
		} else {
			targetObj = jQuery('.bb_overview .backup-step-finished:last').next('.backup-step');
		}
		jQuery(targetObj).append( errorHTML ).addClass('backup-step-error');

		// Make Status tab red.
		jQuery( '.nav-tab-1' ).addClass( 'bb-nav-status-tab-error' );
	}

	bb_hashCode = function( text ) {
		var hash = 0, i, chr;
		if ( 0 === text.length ) {
			return hash;
		}
		for ( i = 0; i < text.length; i++ ) {
			chr   = text.charCodeAt(i);
			hash  = ( ( hash << 5 ) - hash ) + chr;
			hash |= 0; // Convert to 32bit integer
		}
		return hash;
	};
</script>

<?php if ( 'deploy' != pb_backupbuddy::_GET( 'backupbuddy_backup' ) ) { ?>
	<div class="bb_progress-bar clearfix">
		<div class="bb_progress-step bb_progress-step-settings bb_progress-step-active">
			<div class="bb_progress-step-icon"></div>
			<div class="bb_progress-step-title"><?php _e( 'Settings', 'it-l10n-backupbuddy' ); ?></div>
			<span class="bb_progress-loading"></span>
		</div>
		<?php if ( 'files' !== $profile_array['type'] ) { ?>
		<div class="bb_progress-step bb_progress-step-database">
			<div class="bb_progress-step-icon"></div>
			<div class="bb_progress-step-title"><?php _e( 'Database', 'it-l10n-backupbuddy' ); ?></div>
			<span class="bb_progress-loading"></span>
		</div>
		<?php } ?>
		<div class="bb_progress-step bb_progress-step-files">
			<div class="bb_progress-step-icon"></div>
			<div class="bb_progress-step-title"><?php _e( 'Files', 'it-l10n-backupbuddy' ); ?></div>
			<span class="bb_progress-loading"></span>
		</div>
		<div class="bb_progress-step bb_progress-step-unfinished">
			<div class="bb_progress-step-icon"></div>
			<div class="bb_progress-step-title"><?php _e( 'Finished!', 'it-l10n-backupbuddy' ); ?></div>
			<span class="bb_progress-loading"></span>
		</div>
	</div>
<?php } else { ?>
	<div class="bb_progress-bar clearfix">
		<div class="bb_progress-step bb_progress-step-deploySnapshot bb_progress-step-active">
			<div class="bb_progress-step-icon"></div>
			<div class="bb_progress-step-title"><?php _e( 'Creating Snapshot', 'it-l10n-backupbuddy' ); ?></div>
			<span class="bb_progress-loading"></span>
		</div>
		<div class="bb_progress-step bb_progress-step-deployTransfer">
			<div class="bb_progress-step-icon"></div>
			<div class="bb_progress-step-title">
			<?php
			if ( 'push' == $direction ) {
				_e( 'Pushing Data', 'it-l10n-backupbuddy' );
			} elseif ( 'pull' == $direction ) {
				_e( 'Pulling Data', 'it-l10n-backupbuddy' );
			} else {
				echo '{Error#438478494:Unknown direction.}';
			}
			?>
			</div>
			<span class="bb_progress-loading"></span>
		</div>
		<div class="bb_progress-step bb_progress-step-deployRestore">
			<div class="bb_progress-step-icon"></div>
			<div class="bb_progress-step-title"><?php _e( 'Deploying Data', 'it-l10n-backupbuddy' ); ?></div>
			<span class="bb_progress-loading"></span>
		</div>
		<div class="bb_progress-step bb_progress-step-unfinished">
			<div class="bb_progress-step-icon"></div>
			<div class="bb_progress-step-title"><?php _e( 'Finished!', 'it-l10n-backupbuddy' ); ?></div>
			<span class="bb_progress-loading"></span>
		</div>
	</div>
<?php } ?>

<div class="bb_progress-error-bar" style="display: none;"></div>

<div style="clear: both;"></div>

<div class="bb_actions bb_actions_during">
	<a class="btn btn-with-icon btn-white btn-cancel" href="javascript:void(0);" id="pb_backupbuddy_stop"><span class="btn-icon"></span> Cancel Backup</a>
	<?php if ( 'deploy' == pb_backupbuddy::_GET( 'backupbuddy_backup' ) ) { ?>
		<a class="btn btn-with-icon btn-white btn-cancel pb_backupbuddy_deployUndo" href="" target="_blank" style="display: none;"><span class="btn-icon"></span> Undo Destination Database Changes</a>
	<?php } ?>
</div>

<?php if ( 'deploy' == pb_backupbuddy::_GET( 'backupbuddy_backup' ) ) { ?>
	<div class="bb_actions bb_actions_after-deploy slidedown" style="display: none;">
		<a class="btn btn-with-icon btn-white btn-back" href="<?php echo pb_backupbuddy::page_url(); ?>">Back to backups<span class="btn-icon"></span></a>
		<a class="btn btn-with-icon btn-white btn-cancel pb_backupbuddy_deployUndo" href="" target="_blank" style="display: none;"><span class="btn-icon"></span> Undo Destination Database Changes</a>
		<?php
		if ( 'push' == $direction ) {
			$destination_url = $deploy_data['destination']['siteurl'];
		} elseif ( 'pull' == $direction ) {
			$destination_url = site_url(); // $deploy_data['remoteInfo']['siteurl'];
		} else {
			$destination_url = '#UNKNOWN_DESTINATION_TYPE';
		}
		?>
		<a class="btn btn-with-icon btn-visit" href="<?php echo esc_url( $destination_url ); ?>" target="_blank"><span class="btn-icon"></span> Visit Deployed Site</a>
		<a class="btn btn-with-icon btn-confirm btn-confirm-deploy" href="javascript:void(0);" target="_blank"><span class="btn-icon" style="font-size: 1.5em; top: 24%; color: #8CFF9B;"></span>Confirm Changes</a>
	</div>
<?php } ?>

<div class="bb_actions slidedown pb_actions_cancelled" style="display: none;">
	<a href="<?php echo esc_url( pb_backupbuddy::page_url() ); ?>" class="btn btn-with-icon btn-white btn-back"><span class="btn-icon"></span> Back to backups</a>
	<?php if ( 'deploy' == pb_backupbuddy::_GET( 'backupbuddy_backup' ) ) { ?>
		<a class="btn btn-with-icon btn-white btn-cancel pb_backupbuddy_deployUndo" href="" target="_blank" style="display: none;"><span class="btn-icon"></span> Undo Destination Database Changes</a>
	<?php } ?>
	<?php if ( 'deploy' != pb_backupbuddy::_GET( 'backupbuddy_backup' ) ) { ?>
		<a href="admin.php?<?php echo $_SERVER['QUERY_STRING']; ?>" class="btn btn-with-icon btn-tryagain">Try Again <span class="btn-icon"></span></a>
	<?php } ?>
	<a href="http://ithemes.com/support/" target="_blank" class="btn btn-with-icon btn-support">Contact iThemes Support for help <span class="btn-icon"></span></a>
</div>

<div>
	<div class="bb_actions bb_actions_after slidedown" style="display: none;">
		<a class="btn btn-with-icon btn-white btn-back" href="<?php echo esc_url( pb_backupbuddy::page_url() ); ?>">Back to backups<span class="btn-icon"></span></a>
		<a class="btn btn-with-icon btn-download" href="#" id="pb_backupbuddy_archive_url">Download backup file <span class="btn-file-size backupbuddy_archive_size">?MB</span> <span class="btn-icon"></span></a>
		<a class="btn btn-with-icon btn-send" href="#" id="pb_backupbuddy_archive_send" rel="">Send to an offsite destination <span class="btn-icon"></span></a>

		<?php require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php'; ?>
		<div class="bb_destinations">
			<div class="bb_destinations-group bb_destinations-existing">
				<h3>Send to one of your existing destinations?</h3>
				<label><input type="checkbox" name="delete_after" id="pb_backupbuddy_remote_delete" value="1">Delete local backup after successful delivery?</label>
				<ul>
					<?php
					foreach ( pb_backupbuddy::$options['remote_destinations'] as $destination_id => $destination ) {
						// Never show Deployment ("site") destination here.
						if ( ( 'site' == $destination['type'] ) || ( 'live' == $destination['type'] ) ) {
							continue;
						}

						$disabled_class = '';
						if ( isset( $destination['disabled'] ) && ( '1' == $destination['disabled'] ) ) {
							$disabled_class = 'bb_destination-item-disabled';
						}

						echo '<li class="bb_destination-item bb_destination-' . $destination['type'] . ' ' . $disabled_class . '"><a href="javascript:void(0)" title="' . $destination['title'] . '" rel="' . $destination_id . '">' . $destination['title'] . '</a></li>';
					}
					?>
					<br><br>
					<a href="javascript:void(0)" class="btn btn-small btn-white btn-cancel-send" onClick="jQuery('.bb_destinations').hide();">Nevermind</a>
					<?php if ( false === apply_filters( 'itbub_disable_add_destination_tab', false ) ) : ?>
						<a href="javascript:void(0)" class="btn btn-small btn-addnew" onClick="jQuery('.bb_destinations-existing').hide(); jQuery('.bb_destinations-new').show();">Add New Destination +</a>
					<?php endif; ?>
				</ul>
			</div>
			<div class="bb_destinations-group bb_destinations-new bb_destinations-new" style="display: none;">
				<h2>What kind of destination do you want to add?</h2><br>
				<ul>
					<?php
					$best_count   = 0;
					$normal_count = 0;
					$legacy_count = 0;
					$best         = '';
					$normal       = '';
					$legacy       = '';
					foreach ( pb_backupbuddy_destinations::get_destinations_list( true ) as $destination_name => $destination ) {
						$disable_class = '';
						if ( true !== $destination['compatible'] ) {
							$disable_class = 'bb_destination-item-disabled';
						}
						if ( ! isset( $destination['name'] ) ) { // Messed up destination.
							continue;
						}

						// Hide these destinations from list to add here.
						if ( 'live' == $destination_name || 'site' == $destination_name ) {
							continue;
						}

						$this_dest  = '';
						$this_dest .= '<li class="bb_destination-item bb_destination-' . $destination_name . ' bb_destination-new-item ' . $disable_class . '">';
						if ( 'stash3' == $destination_name ) {
							$this_dest .= '<div class="bb-ribbon"><span>New</span></div>';
						}
						$this_dest .= '<a href="javascript:void(0)" rel="' . $destination_name . '">';
						$this_dest .= $destination['name'];
						if ( true !== $destination['compatible'] ) {
							$this_dest .= ' [Unavailable; ' . $destination['compatibility'] . ']';
						}
						$this_dest .= '</a></li>';

						if ( isset( $destination['category'] ) && 'best' == $destination['category'] ) {
							$best .= $this_dest;
							$best_count++;
							if ( $best_count > 4 ) {
								$best      .= '<span class="bb_destination-break"></span>';
								$best_count = 0;
							}
						} elseif ( isset( $destination['category'] ) && 'legacy' == $destination['category'] ) {
							$legacy .= $this_dest;
							$legacy_count++;
							if ( $legacy_count > 4 ) {
								$legacy      .= '<span class="bb_destination-break"></span>';
								$legacy_count = 0;
							}
						} else {
							$normal .= $this_dest;
							$normal_count++;
							if ( $normal_count > 4 ) {
								$normal      .= '<span class="bb_destination-break"></span>';
								$normal_count = 0;
							}
						}
					}

					echo '<h3>' . __( 'Preferred', 'it-l10n-backupbuddy' ) . '</h3>' . $best;
					echo '<br><br><hr style="max-width: 1200px;"><br>';
					echo '<h3>' . __( 'Normal', 'it-l10n-backupbuddy' ) . '</h3>' . $normal;
					echo '<br><br><hr style="max-width: 1200px;"><br>';
					echo '<h3>' . __( 'Legacy', 'it-l10n-backupbuddy' ) . '</h3>' . $legacy;
					?>
				</ul>
			</div>
		</div>
	</div>
</div>

<div class="bb_actions bb_actions_remotesent"></div>

<div>
	<span style="float: right; margin-top: 18px;">
		<b><?php _e( 'Archive size', 'it-l10n-backupbuddy' ); ?></b>:&nbsp; <span class="backupbuddy_archive_size">-- MB</span>
	</span>
	<?php
	$active_tab = pb_backupbuddy::$options['default_backup_tab'];
	pb_backupbuddy::$ui->start_tabs(
		'settings',
		array(
			array(
				'title' => __( 'Overview', 'it-l10n-backupbuddy' ),
				'slug'  => 'general',
				'css'   => 'margin-top: -8px;',
			),

			array(
				'title' => __( 'Status Log', 'it-l10n-backupbuddy' ),
				'slug'  => 'advanced',
				'css'   => 'margin-top: -8px;',
			),
		),
		'width: 100%;',
		true,
		$active_tab
	);

	pb_backupbuddy::$ui->start_tab( 'general' );
	?>
	<div class="bb_overview">
		<div class="backup-step backup-step-active" id="backup-function-pre_backup">
			<span class="backup-step-title">
			<?php
			if ( 'deploy' != pb_backupbuddy::_GET( 'backupbuddy_backup' ) ) {
				_e( 'Getting ready to backup', 'it-l10n-backupbuddy' );
			} else {
				_e( 'Getting ready to deploy', 'it-l10n-backupbuddy' ); }
			?>
			</span>
			<span class="backup-step-status"></span>
		</div>
		<?php if ( 'deploy' != pb_backupbuddy::_GET( 'backupbuddy_backup' ) ) { ?>
			<div class="backup-step backup-step-secondary" id="backup-secondary-function-pre_backup" style="display: none;">
			</div>
		<?php } ?>
		<div class="backup-step" id="backup-function-backup_create_database_dump">
			<span class="backup-step-title">
			<?php
			if ( 'deploy' != pb_backupbuddy::_GET( 'backupbuddy_backup' ) ) {
				_e( 'Backing up database', 'it-l10n-backupbuddy' );
			} else {
				if ( 'push' == $direction ) {
					_e( 'Snapshotting this database', 'it-l10n-backupbuddy' );
				} elseif ( 'pull' == $direction ) {
					_e( 'Snapshotting remote database', 'it-l10n-backupbuddy' );
				} else {
					echo '{Error#44339723a:Unknown direction.}';
				}
			}
			?>
			<span id="backup-function-current-table"></span></span>
			<span class="backup-step-zip-size backupbuddy_sql_size"></span>
			<span class="backup-step-status"></span>
		</div>
		<div class="backup-step" id="backup-function-backup_zip_files">
			<span class="backup-step-title">
			<?php
			if ( 'deploy' != pb_backupbuddy::_GET( 'backupbuddy_backup' ) ) {
				_e( 'Zipping files', 'it-l10n-backupbuddy' );
			} else {
				if ( 'push' == $direction ) {
					_e( 'Zipping local files', 'it-l10n-backupbuddy' );
				} elseif ( 'pull' == $direction ) {
					_e( 'Zipping remote files', 'it-l10n-backupbuddy' );
				} else {
					echo '{Error#44339723b:Unknown direction.}';
				}
			}
			?>
			</span>
			<span class="backup-step-zip-size backupbuddy_archive_size"></span>
			<span class="backup-step-status"></span>
		</div>
		<?php if ( 'deploy' != pb_backupbuddy::_GET( 'backupbuddy_backup' ) ) { ?>
			<div class="backup-step" id="backup-function-integrity_check">
				<span class="backup-step-title"><?php _e( 'Verifying backup file integrity', 'it-l10n-backupbuddy' ); ?></span>
				<span class="backup-step-status"></span>
			</div>
			<div class="backup-step" id="backup-function-post_backup">
				<span class="backup-step-title"><?php _e( 'Cleaning up', 'it-l10n-backupbuddy' ); ?></span>
				<span class="backup-step-status"></span>
			</div>
		<?php } else { ?>
			<div class="backup-step" id="backup-function-deploy_sendContent">
				<span class="backup-step-title">
				<?php
				if ( 'push' == $direction ) {
					_e( 'Pushing data & files', 'it-l10n-backupbuddy' );
				} elseif ( 'pull' == $direction ) {
					_e( 'Pulling data & files', 'it-l10n-backupbuddy' );
				} else {
					echo '{Error#44339723c:Unknown direction.}';
				}
				?>
				</span>
				<span class="backup-step-zip-size">
					<span class="backupbuddy_sendContent_progress"></span>
					<span class="backupbuddy_sendContent_sent" id="backupbuddy_sendContent_sent" data-count="0"></span>
				</span>
				<span class="backup-step-status"></span>
			</div>
		<?php } ?>
		<?php if ( 'deploy' == pb_backupbuddy::_GET( 'backupbuddy_backup' ) ) { ?>
			<div class="backup-step" id="backup-function-deploy_runningImportBuddy">
				<span class="backup-step-title">
				<?php
				if ( 'push' == $direction ) {
					_e( 'Deploying pushed data on destination site', 'it-l10n-backupbuddy' );
				} elseif ( 'pull' == $direction ) {
					_e( 'Deploying pulled data to this site', 'it-l10n-backupbuddy' );
				} else {
					echo '{Error#33736332:Unknown direction.}';
				}
				?>
				</span>
				<span class="backup-step-status"></span>
			</div>
			<div class="backup-step backup-step-secondary" style="display: none;" id="backup-function-deploy_runningImportBuddy-secondary">
				<iframe id="backupbuddy_deploy_runningImportBuddy" src="" width="100%" height="30" frameBorder="0">Error #4584594579. Browser not compatible with iframes.</iframe>
			</div>
		<?php } ?>
		<?php
		$step_id = 'backup-function-';
		if ( 'deploy' != pb_backupbuddy::_GET( 'backupbuddy_backup' ) ) {
			$step_id .= 'backup_success';
		} else {
			$step_id .= 'deploy_success';
		}
		?>
		<div class="backup-step" id="<?php echo esc_attr( $step_id ); ?>">
			<span class="backup-step-title">
			<?php
			if ( 'deploy' != pb_backupbuddy::_GET( 'backupbuddy_backup' ) ) {
				_e( 'Backup completed successfully', 'it-l10n-backupbuddy' );
			} else {
				_e( 'Deployment completed successfully. Click the "Confirm Changes" button once satisfied.', 'it-l10n-backupbuddy' );
			}
			?>
			</span>
			<span class="backup-step-status"></span>
			<div class="backup-step-error-message" style="display: none;" id="backupbuddy_errors_notice">
				<h3>Possible errors or warnings may have been encountered</h3>
				See the Status Log in the tab above for details on detected errors.
				<b>Not all errors are fatal and are sometimes normal.</b> Look up error codes & troubleshooting details in the <a href="https://ithemeshelp.zendesk.com/hc/en-us/sections/202110027-Troubleshooting" target="_blank"><b>Knowledge Base</b></a>.
				<b><i>Provide a copy of the Status Log if seeking support.</i></b>
			</div>
		</div>
	</div>
	<br><br>
	<div style="text-align: center;">
		<button class="button button-secondary" onClick="backupbuddy_saveLogAsFile();">Download Status Log (.txt)</button>
	</div>
	<?php
	pb_backupbuddy::$ui->end_tab();
	pb_backupbuddy::$ui->start_tab( 'advanced' );
	?>
	<pre wrap="off" id="backupbuddy_messages" style="width: 100%; font-family: Andale Mono, monospace; tab-size: 3; -moz-tab-size: 3; -o-tab-size: 3;">Time				Elapsed	Memory	Message</pre>
	<div style="float: right; margin-left: 20px;">
		<label>
			<input type="checkbox" id="backupbuddy-status-limit"> <span class="description">Limit to last <?php echo backupbuddy_constants::BACKUP_STATUS_BOX_LIMIT_OPTION_LINES; ?> lines</span>
		</label>
	</div>
	<div style="float: right;">
		<label>
			<input type="checkbox" checked="checked" id="backupbuddy-status-autoscroll"> <span class="description">Auto scroll to bottom</span>
		</label>
	</div>
	<br style="clear: both;">
	<div style="text-align: center;">
		<button class="button button-primary" onClick="backupbuddy_saveLogAsFile();">Download Status Log (.txt)</button>
	</div>
	<br><br>
	<?php
	pb_backupbuddy::$ui->end_tab();
	?>
</div>

<?php
if ( '1' == $profile_array['backup_mode'] ) { // Classic mode (all in one page load).
	?>
	<br><br>
	<div style="width: 100%">
		<div class="description" style="text-align: center;">
			<?php
			pb_backupbuddy::alert( __( 'Running in CLASSIC mode. Leaving this page before the backup completes will likely result in a failed backup.', 'it-l10n-backupbuddy' ) );
			?>
		</div>
	</div>

	<?php // SCRIPT BELOW IS COPIED FROM pb_tabs.js - Why? ?>
	<script>
		// Change tab on click.
		jQuery( '.backupbuddy-tabs-wrap .nav-tab[href^="#"]' ).click( function(e){ /* ignores any non hashtag links since they go direct to a URL... */

			e.preventDefault();

			// Hide all tab blocks.
			thisTabBlock = jQuery(this).closest( '.backupbuddy-tabs-wrap' );
			thisTabBlock.find( '.backupbuddy-tab' ).hide();

			// Update selected tab.
			thisTabBlock.find( '.nav-tab-active' ).removeClass( 'nav-tab-active' );
			jQuery(this).addClass( 'nav-tab-active' );

			// Show the correct tab block.
			thisTabBlock.find( jQuery(this).attr( 'href' ) ).show();
		});
	</script>

<?php
}

// Sending to remote destination after manual backup completes?
$post_backup_steps = array();
$delete_after      = false;
if ( ( pb_backupbuddy::_GET( 'after_destination' ) != '' ) && ( is_numeric( pb_backupbuddy::_GET( 'after_destination' ) ) ) ) {
	$destination_id = (int) pb_backupbuddy::_GET( 'after_destination' );
	if ( pb_backupbuddy::_GET( 'delete_after' ) == 'true' ) {
		$delete_after = true;
	} else {
		$delete_after = false;
	}
	$post_backup_steps = array(
		array(
			'function'    => 'send_remote_destination',
			'args'        => array( $destination_id, $delete_after ),
			'start_time'  => 0,
			'finish_time' => 0,
			'attempts'    => 0,
		),
	);
	pb_backupbuddy::status( 'details', 'Manual backup set to send to remote destination `' . $destination_id . '`.  Delete after: `' . $delete_after . '`. Added to post backup function steps.' );
}

$trigger = 'manual';

// Handle deployment settings and adding its step.
if ( 'deploy' == pb_backupbuddy::_GET( 'backupbuddy_backup' ) ) {
	pb_backupbuddy::verify_nonce();
	$deploy_data['backupProfile']               = pb_backupbuddy::_POST( 'backup_profile' );
	$deploy_data['sourceMaxExecutionTime']      = pb_backupbuddy::_POST( 'sourceMaxExecutionTime' );
	$deploy_data['destinationMaxExecutionTime'] = pb_backupbuddy::_POST( 'destinationMaxExecutionTime' );
	$trigger                                    = 'deployment';


	// Determine bottleneck execution time.
	$deploy_data['minimumExecutionTime'] = $deploy_data['sourceMaxExecutionTime'];
	if ( $deploy_data['destinationMaxExecutionTime'] < $deploy_data['minimumExecutionTime'] ) {
		$deploy_data['minimumExecutionTime'] = $deploy_data['destinationMaxExecutionTime'];
	}

	if ( 'true' == pb_backupbuddy::_POST( 'sendTheme' ) ) {
		$deploy_data['sendTheme'] = true;
	} else {
		$deploy_data['sendTheme'] = false;
	}

	if ( 'true' == pb_backupbuddy::_POST( 'sendChildTheme' ) ) {
		$deploy_data['sendChildTheme'] = true;
	} else {
		$deploy_data['sendChildTheme'] = false;
	}

	if ( 'true' == pb_backupbuddy::_POST( 'doImportCleanup' ) ) {
		$deploy_data['doImportCleanup'] = true;
	} else {
		$deploy_data['doImportCleanup'] = false;
	}

	// Calculate plugin root directories we want to transfer.
	$send_plugins = pb_backupbuddy::_POST( 'sendPlugins' );
	if ( ! is_array( $send_plugins ) ) {
		$send_plugins = array();
	}
	$send_plugin_dirs = array();
	foreach ( $send_plugins as $send_plugin_file => $send_plugin ) {
		$send_plugin_dirs[] = dirname( '/' . $send_plugin );
	}

	// Remove any unselected plugins from the plugin files to transfer.
	if ( 'push' == pb_backupbuddy::_GET( 'direction' ) ) {
		foreach ( $deploy_data['pushPluginFiles'] as $i => $push_plugin_file ) { // For each push_plugin_file make sure.
			$first_dir_slash = strpos( str_replace( '\\', '/', $push_plugin_file ), '/', 1 );
			$this_dir        = substr( $push_plugin_file, 0, $first_dir_slash );
			if ( ! in_array( $this_dir, $send_plugin_dirs ) ) { // File is in directory we are not sending. Unset.
				unset( $deploy_data['pushPluginFiles'][ $i ] );
			}
		}
	} elseif ( 'pull' == pb_backupbuddy::_GET( 'direction' ) ) {
		foreach ( $deploy_data['pullPluginFiles'] as $i => $pull_plugin_file ) { // For each push_plugin_file make sure.
			$first_dir_slash = strpos( str_replace( '\\', '/', $pull_plugin_file ), '/', 1 );
			$this_dir        = substr( $pull_plugin_file, 0, $first_dir_slash );
			if ( ! in_array( $this_dir, $send_plugin_dirs ) ) { // File is in directory we are not sending. Unset.
				unset( $deploy_data['pullPluginFiles'][ $i ] );
			}
		}
	}

	$deploy_data['sendPlugins']     = $send_plugins;
	$deploy_data['sendMedia']       = 'true' == pb_backupbuddy::_POST( 'sendMedia' );
	$deploy_data['sendExtras']      = 'true' == pb_backupbuddy::_POST( 'sendExtras' );
	$deploy_data['doImportCleanup'] = 'true' == pb_backupbuddy::_POST( 'doImportCleanup' );
	$deploy_data['destination_id']  = pb_backupbuddy::_POST( 'destination_id' );

	if ( '1' == pb_backupbuddy::_POST( 'setBlogPublic' ) ) {
		$deploy_data['setBlogPublic'] = true;
	} elseif ( '0' == pb_backupbuddy::_POST( 'setBlogPublic' ) ) {
		$deploy_data['setBlogPublic'] = false;
	} else {
		$deploy_data['setBlogPublic'] = '';
	}

	if ( 'push' == pb_backupbuddy::_GET( 'direction' ) ) {
		$post_backup_steps = array(
			array(
				'function'    => 'deploy_push_start',
				'args'        => array( $deploy_data ),
				'start_time'  => 0,
				'finish_time' => 0,
				'attempts'    => 0,
			),
		);
		pb_backupbuddy::status( 'details', 'Deployment PUSH set to send to remote destination `' . $deploy_data['destination_id'] . '`. Added to post backup function steps.' );
	} // end if PUSH type deployment.

	if ( 'pull' == pb_backupbuddy::_GET( 'direction' ) ) {
		$post_backup_steps = array(
			array(
				'function'    => 'deploy_pull_start',
				'args'        => array( $deploy_data ),
				'start_time'  => 0,
				'finish_time' => 0,
				'attempts'    => 0,
			),
		);
		pb_backupbuddy::status( 'details', 'Deployment PULL set to send to remote destination `' . $deploy_data['destination_id'] . '`. Added to post backup function steps.' );
	} // end if PULL type deployment.
} // end if deployment.
$deploy_destination = null;
if ( isset( $deploy_data['destination'] ) ) {
	$deploy_destination = $deploy_data['destination'];
}

pb_backupbuddy::load_script( 'backupEvents.js' );
pb_backupbuddy::load_script( 'backupPerform.js' );

// Run the backup!
pb_backupbuddy::flush(); // Flush any buffer to screen just before the backup begins.
if ( $new_backup->start_backup_process(
	$profile_array,                                         // Profile array.
	$trigger,                                               // Backup trigger. manual, scheduled.
	array(),                                                // pre-backup array of steps.
	$post_backup_steps,                                     // post-backup array of steps.
	'',                                                     // friendly title of schedule that ran this (if applicable).
	$serial_override,                                       // if passed then this serial is used for the backup insteasd of generating one.
	$export_plugins,                                        // Multisite export only: array of plugins to export.
	pb_backupbuddy::_GET( 'direction' ),                    // Deployment direction, if any.
	$deploy_destination                                      // Deployment destination settings, if deployment.
) !== true ) {
	pb_backupbuddy::alert( __( 'Fatal Error #4344443: Backup failure. Please see any errors listed in the Status Log for details.', 'it-l10n-backupbuddy' ), true );
}
?>

</div>
