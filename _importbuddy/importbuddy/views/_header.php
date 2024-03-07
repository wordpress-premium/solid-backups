<?php
if ( ! defined( 'PB_IMPORTBUDDY' ) || ( true !== PB_IMPORTBUDDY ) ) {
	die( '<html></html>' );
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml"  dir="ltr" lang="en-US">
	<head>
		<meta charset="utf-8">
		<title>Importer v<?php echo pb_backupbuddy::$options['bb_version']; ?> Restore / Migration Tool - Powered by Solid Backups</title>
		<meta name="ROBOTS" content="NOINDEX, NOFOLLOW">

		<?php
		require( '_assets.php' );
		?>

		<link rel="icon" type="image/png" href="importbuddy/assets/dist/images/favicon.png">
		<script type="text/javascript">
			window.onerror=function( errorMsg, url, lineNumber, colNumber, error ){
				alert( "Error #439743: A javascript error occurred which MAY prevent the restore from continuing. _IF_ the process lets you proceed then ignore this message. Check the Status Log or browser error console for trace details.\n\nMessage: `" + errorMsg + "`.\nURL: `" + url + "`.\nLine: `" + lineNumber + "`" );
				backupbuddy_log( 'Javascript Error. Message: `' + errorMsg + '`, URL: `' + url + '`, Line: `' + lineNumber + '`, Col: `' + colNumber + '`, Trace: `' + error.stack + '`.' ); // Attempt to log.
			}

			var statusBox; // Make global.
			var backupbuddy_errors_encountered = 0; // number of errors sent via log.

			function pb_status_append( json ) {
				if ( 'undefined' === typeof statusBox ) { // No status box yet so may need to create it.
					statusBox = jQuery( '#backupbuddy_messages' );
					if( statusBox.length == 0 ) { // No status box yet so suppress.
						return;
					}
				}

				if ( 'string' == ( typeof json ) ) {
					backupbuddy_log( json );
					console.log( 'Status log received string: ' + json );
					return;
				}

				// Used in Solid Backups _backup-perform.php and Importer _header.php
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
				} else {
					//console.log( json.data );
				}
				//console.log( 'trigger: ' + triggerEvent );

				jQuery('#backupbuddy_messages').trigger( triggerEvent, [json] );


			} // End function pb_status_append().


			// Used in Solid Backups _backup-perform.php and Importer _header.php and _rollback.php
			function backupbuddy_log( json, classType ) {
				if ( 'undefined' === typeof statusBox ) { // No status box yet so may need to create it.
					statusBox = jQuery( '#backupbuddy_messages' );
					if( statusBox.length == 0 ) { // No status box yet so suppress.
						return;
					}
				}

				message = '';

				if ( 'string' == ( typeof json ) ) {
					if ( '' !== classType ) {
						json = '<span class="backupbuddy_log_' + classType + '">' + json + '</span>';
					}
					message = "-----------\t\t-------\t-------\t" + json;
				} else {
					if ( '' !== classType ) {
						json.data = '<span class="backupbuddy_log_' + classType + '">' + json.data + '</span>';
					}
					message = json.date + '.' + json.u + " \t" + json.run + "sec \t" + json.mem + "MB\t" + json.data;
				}

				statusBox.append( "\r\n" + message );
				statusBox.scrollTop( statusBox[0].scrollHeight - statusBox.height() );

			}


			// Trigger an error to be logged, displayed, etc.
			// Returns updated message with trouble URL, etc.
			// Used in Solid Backups _backup-perform.php and Importer _header.php
			function backupbuddyError( message ) {

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
					troubleURL = 'https://go.solidwp.com/error-codes-#' + error_number;
				}

				if ( '' !== troubleURL ) {
					// Display error in error div with class error_alert_box.
					message = message + ' <a href="' + troubleURL + '" target="_blank">Click to <b>view error details</b> in the Knowledge Base</a>';
				}
				jQuery( '.backupbuddy_error_list' ).append( '<li>' +  message + '</li>' );
				jQuery( '.error_alert_box' ).show();

				// Display error box to make it clear errors were encountered.
				backupbuddy_errors_encountered++;
				jQuery( '#backupbuddy_errors_notice_count' ).text( backupbuddy_errors_encountered );
				jQuery( '#backupbuddy_errors_notice' ).slideDown();

				// If the word error is nowhere in the error message then add in error prefix.
				if ( message.toLowerCase().indexOf( 'error' ) < 0 ) {
					message = 'ERROR: ' + message;
				}


				return message; // Return updated error message with trouble URL.
			} // end backupbuddyError().


			// Used in Solid Backups _backup-perform.php and Importer _header.php
			function backupbuddyWarning( message ) {
				jQuery( '.backupbuddy_warning_list' ).append( '<li>' +  message + '</li>' );
				//jQuery( '.warning_alert_box' ).show();
				return 'Warning: ' + message;
			} // end backupbuddyWarning().

			// left hour pad with zeros
			function backupbuddy_hourpad(n) { return ("0" + n).slice(-2); }

			function randomString(length, chars) {
				var result = '';
				for (var i = length; i > 0; --i) result += chars[Math.round(Math.random() * (chars.length - 1))];
				return result;
			}


		</script>
		<script type="text/javascript">
			EJS.config({cache: false});

			window.restoreData = {};

			jQuery(document).ready(function() {
				jQuery('.leanModal').leanModal(
					{ top : 45, overlay : 0.4, closeButton: ".modal_close" }
				);

				/* MD5 Hash Button Clicked */
				jQuery( '.view_hash_click' ).click( function() {
					jQuery('#hash_view_loading').show();
					jQuery('#hash_view_response').hide();

					var backupFile = jQuery(this).attr( 'data-file' );
					jQuery.ajax({
						type: 'POST',
						url: 'importbuddy.php',
						data: {
							ajax: 'file_hash',
							file: backupFile
						},
						dataType: 'json'
					}).done( function(data) {
						jQuery('#hash_view_response').html( '<b>Checksum (MD5 hash):</b> <input type="text" readonly="true" value="' + data.hash + '" style="width: 400px;">' );
						jQuery('#hash_view_loading').hide();
						jQuery('#hash_view_response').show();
					}).fail( function( jqXHR, textStatus, errorThrown ){
						jQuery('#hash_view_response').html( 'Error: `' + jqXHR.responseText + '`.' );
						jQuery('#hash_view_loading').hide();
						jQuery('#hash_view_response').show();
					});
				});



				jQuery( '.main_box' ).on( 'submit', 'form', function(e) {
					if ( 'miniFrame' == jQuery(this).attr( 'target' ) ) {
						NProgress.start();
					}
					return true;
				});

				// Pre-load final steps so it can be displayed even though deleted.
				window.stepTemplateCleanupSettings = new EJS({url: 'importbuddy/views/cleanupSettings.htm'});
				window.stepTemplatefinalCleanup = new EJS({url: 'importbuddy/views/finalCleanup.htm'});
				window.stepTemplateFinished = new EJS({url: 'importbuddy/views/finished.htm'});

			});


			function bb_action( action, note ) {
				console.log( 'bb_action: `' + action + '`.' );
				if ( 'unzipSuccess' == action ) {
				} else if ( 'iframeLoaded' == action ) { // Hide iframe loading graphic.
					//NProgress.done();
				} else if ( 'importingTable' == action ) {
					jQuery('#importingDatabase-progressMessage').text( 'Restoring ' + note + ' ...' ); // note contains table name
				} else if ( 'databaseRestoreSuccess' == action ) {
					jQuery('#importingDatabase-progressMessage').text( 'Database Restore Successful' ).addClass( 'animated fadeInDown' );
				} else if ( 'databaseRestoreSkipped' == action ) {
					jQuery('#importingDatabase-progressMessage').text( 'Database Restore Skipped' ).addClass( 'animated swing' );
				} else if ( 'databaseRestoreFailed' == action ) {
					jQuery('#importingDatabase-progressMessage').text( 'Database Restore Failed' ).addClass( 'animated wobble' );
				} else if ( 'databaseMigrationSuccess' == action ) {
					jQuery('#migratingDatabase-progressMessage').text( 'Database Migration Successful' ).addClass( 'animated fadeInDown' );
				} else if ( 'databaseMigrationSkipped' == action ) {
					jQuery('#migratingDatabase-progressMessage').text( 'Database Migration Skipped' ).addClass( 'animated swing' );
				} else if ( 'databaseMigrationFailed' == action ) {
					jQuery('#migratingDatabase-progressMessage').text( 'Database Migration Failed' ).addClass( 'animated wobble' );
				} else if ( 'filesRestoreSuccess' == action ) {
					jQuery('#unzippingFiles-progressMessage').text( 'Completed Restoring Files' ).addClass( 'animated fadeInDown' );
				} else if ( 'filesRestoreSkipped' == action ) {
					jQuery('#unzippingFiles-progressMessage').text( 'Skipped Restoring Files' ).addClass( 'animated swing' );
				} else {
					console.log( 'Unknown JS bb_action `' + action + '` with note `' + note + '`.' );
				}
			}


			function bb_showStep( step, data ) {
				window.restoreData = data;
				jQuery('.step-wrap').hide();
				console.log( 'Show step: `' + step + '`.' );
				console.dir( window.restoreData );
				backupbuddy_log( 'Loading step `' + step + '`.');

				//jQuery('.step-' + step + '-wrap').show();
				if ( 'finished' == step ) { // In case we cannot load final template, at least say finished.
					jQuery('.main_box').html( window.stepTemplateFinished.render(data) );
				}

				if ( 'cleanupSettings' == step ) { // Preloaded template.
					jQuery('.main_box').html( window.stepTemplateCleanupSettings.render(data) );
				} else if ( 'finalCleanup' == step ) {
					jQuery('.main_box').html( window.stepTemplatefinalCleanup.render(data) );
				} else { // Normal step.
					if ( step == 'databaseSettings' ) {
						// Encode double quotes before passing to value=""
						data.dat.db_password = data.dat.db_password.replace(/"/g, '&quot;');
					}
					jQuery('.main_box').html( new EJS({url: 'importbuddy/views/' + step + '.htm'}).render(data) );
				}
			}


			function tip( tip ) {
				return '<a class="pluginbuddy_tip" title="' + tip + '"><span class="screen-reader-text"><?php esc_html__( 'Hover to open tooltip', 'it-l10n-backupbuddy' ); ?></span><img src="importbuddy/assets/dist/icons/help.svg"></a>';
			}

		</script>
		<style>

		</style>
	</head>
	<body <?php echo ( ( pb_backupbuddy::_GET( 'display_mode' ) == 'embed' ) ? 'style="background: #ffffff"' : '' ); ?>>
	<?php if ( 'embed' !== pb_backupbuddy::_GET( 'display_mode' ) ) : ?>
		<div class="topNav">
			<div class="topNav__inner">
				<a href="https://go.solidwp.com/purchase-solid-backups" class="topNav-logo" target="_blank" title="Visit Solid Backups Website in New Window"><strong>Solid</strong> Backups</a>
				<div class="topNav-links">
					<a href="https://go.solidwp.com/backups-help" class="topNav-links-link" target="_blank">Knowledge Base</a>
					<a href="https://go.solidwp.com/support" class="topNav-links-link" target="_blank">Support</a>
				</div>
			</div>
		</div>
	<?php endif; ?>

		<?php // This is used for loading each progress step. ?>
		<div style="position: relative; height: 1px; margin-top: 0; width: 100%; overflow: hidden;">
			<div style="max-width: 1000px; margin-left: auto; margin-right: auto;">
				<!-- <span id="restorebuddy_iframe_placeholder">Welcome step 1</span> -->
				<script>iframePostInit = false;</script>
				<iframe onLoad="if ( true === iframePostInit ) { NProgress.done(); }" name="miniFrame" id="miniFrame" width="100%" height="70px" frameborder="0" padding="0" margin="0">Error #4584594579. Browser not compatible with iframes.</iframe>
				<script>iframePostInit = true;</script>
			</div>
		</div>

		<div style="display: none;" id="pb_importbuddy_blankalert"><?php pb_backupbuddy::alert( '#TITLE# #MESSAGE#', true, '9021' ); ?></div>

		<div class="main_box_wrap">


			<?php if ( Auth::is_authenticated() ) : // Only record logging if authenticated. ?>
				<div class="main_box_head">
					<span id="pageTitle" class="page-title">&nbsp;</span>
					<a href="javascript:void(0)" class="button" onclick="jQuery('#pb_backupbuddy_status_wrap').toggle();">Display Status Log</a>
				</div>
				<?php echo pb_backupbuddy::$classes['import']->status_box( 'Status Log for for the Importer from Solid Backups v' . pb_backupbuddy::$options['bb_version'] . '...' ); ?>

				<script>importbuddy_loadRestoreEvents();</script>
			<?php else : ?>
				<div class="main_box_head">
					<span id="pageTitle" class="page-title">&nbsp;</span>
				</div>

			<?php endif; ?>
			<div class="main_box_head error_alert_box" style="display: none;">
				<span class="error_alert_title">Error(s)</span>
				<ul class="backupbuddy_error_list">
					<!-- <li>Error #123onlyAtest: An error has NOT happened. This is a only test.</li> -->
				</ul>
			</div>
			<div class="main_box_head warning_alert_box" style="display: none;">
				<span class="error_warning_title">Alert(s)</span>
				<ul class="backupbuddy_warning_list">
					<!-- <li>Error #123onlyAtest: A warning has NOT happened. This is a only test.</li> -->
				</ul>
			</div>
			<?php if ( Auth::is_authenticated() ) : // Only display these links if logged in. ?>
			<div class="solid-tabs">
				<ul>
					<li><a href="importbuddy.php" <?php echo ( pb_backupbuddy::_GET( 'page' ) == '' ) ? 'class="solid-tabs--active"' : ''; ?>>Restore / Migrate</a></li>
					<li><a href="?page=serverinfo" <?php echo ( pb_backupbuddy::_GET( 'page' ) == 'serverinfo' ) ? 'class="solid-tabs--active"' : ''; ?>>Server Information</a></li>
					<li><a href="?page=dbreplace" <?php echo ( pb_backupbuddy::_GET( 'page' ) == 'dbreplace' ) ? 'class="solid-tabs--active"' : ''; ?>>Database Text Replace</a></li>

				</ul>
			</div>
			<?php endif; ?>

			<div class="main_box<?php echo ( pb_backupbuddy::_GET( 'page' ) == 'serverinfo' ) ? ' main_box--server-info"' : ''; ?>">
