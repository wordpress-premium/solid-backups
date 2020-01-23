<?php
// Incoming vars: $destination, $destination_id
if ( isset( $destination['disabled'] ) && ( '1' == $destination['disabled'] ) ) {
	die( __( '<span class="description">This destination is currently disabled based on its settings. Re-enable it under its Advanced Settings.</span>', 'it-l10n-backupbuddy' ) );
}

//pb_backupbuddy::$ui->title( 'Deployment' );
include( pb_backupbuddy::plugin_path() . '/classes/remote_api.php' );

wp_enqueue_script( 'thickbox' );
wp_print_scripts( 'thickbox' );
wp_print_styles( 'thickbox' );
?>

<style>
	.database_restore_table_select {
		background: #FFF;
		padding: 8px;
		max-height: 200px;
		overflow: scroll;
		border: 1px solid #ececec;
	}
	.database_restore_table_select::-webkit-scrollbar {
		-webkit-appearance: none;
		width: 11px;
		height: 11px;
	}
	.database_restore_table_select::-webkit-scrollbar-thumb {
		border-radius: 8px;
		border: 2px solid white; /* should match background, can't be transparent */
		background-color: rgba(0, 0, 0, .1);
	}
</style>




<?php
$deployment = backupbuddy_remote_api::key_to_array( $destination['api_key'] );



require_once( pb_backupbuddy::plugin_path() . '/classes/deploy.php' );
$deploy = new backupbuddy_deploy( $destination, '', $destination_id );
?>


<style>
	.deploy-push-text, .deploy-pull-text {
		padding: 7px;
		
		text-align: center;
		display: inline-block;
		line-height: 1.3em;
		padding-right: 30px;
		padding-left: 30px;
		
		-webkit-border-radius: 3px;
		-moz-border-radius: 3px;
		border-radius: 3px;
	}
	.deploy-pull-text {
		border-left: 2px solid #d4d1d1;
		border-radius: 0;
	}
	.deploy-push-text-sub, .deploy-pull-text-sub {
		font-size: 0.55em;
		font-style: italic;
	}
	.deploy-status {
		display: inline-block;
		font-size: 1.4em;
		padding: 14px;
		color: #FFF;
		text-align: center;
		opacity: 0.5;
		
		-webkit-border-radius: 3px;
		-moz-border-radius: 3px;
		border-radius: 3px;
	}
	.deploy-status-up {
		background: #0074a2;
	}
	.deploy-status-down {
		background: #FF0000;
	}
	.deploy-pushpull-wrap {
		font-size: 1.4em !important;
		white-space: nowrap;
	}
	.deploy-sites-table td {
		padding: 30px;
		vertical-align: middle;
	}
	.deploy-type-selected {
		font-weight: bold;
		background: #efefef;
	}
	.tdhead {
		font-weight: bold;
	}
	.deploy-unmatched-plugin {
		color: red;
	}
	.deploy-unmatched-plugin-version {
		color: blue;
	}
	.deploy-files-list-box {
		display: block;
		width: 100%;
		height: 200px;
	}
</style>


<script>
	jQuery(document).ready(function() {
		jQuery( '#deploy_profile_settings' ).click( function(e){
			e.preventDefault();
			tb_show( 'BackupBuddy', '<?php echo pb_backupbuddy::ajax_url( 'profile_settings' ); ?>&profile=' + jQuery( '#deploy_profile_selected' ).val() + '&callback_data=&TB_iframe=1&width=640&height=455', null );
		});
		
		/*
		jQuery( '#pb_backupbuddy_deploy_form' ).submit( function(e){
			e.preventDefault();
			window.location.href = '<?php echo pb_backupbuddy::ajax_url( 'deploy' ); ?>&step=run&deployment=<?php echo $destination_id; ?>&backup_profile=' + jQuery( '#deploy_profile_selected' ).val();
			return false;
		});
		*/

	});
</script>

<?php
if ( isset( $destination['sha1'] ) ) {
	$sha1 = $destination['sha1'];
} else {
	$sha1 = false;
}
$localInfo = backupbuddy_api::getPreDeployInfo( $sha1, $destination );
$status = $deploy->start( $localInfo );
$errorMessage = '';
if ( false === $status ) {
	$errors = $deploy->getErrors();
	if ( count( $errors ) > 0 ) {
		$errorMessage = 'Errors were encountered: ' . implode( ', ', $errors ) . ' <b><i>If seeking support please provide the details above.</i></b>';
	}
	$siteUp = false;
} else {
	$siteUp = true;
}
?>

<table class="widefat deploy-sites-table">
	<tr>
		<td>
			<?php
			if ( true === $siteUp ) {
				echo '<span class="deploy-status deploy-status-up">';
				_e( 'Site UP', 'it-l10n-backupbuddy' );
			} else {
				echo '<span class="deploy-status deploy-status-down">';
				_e( 'Site DOWN', 'it-l10n-backupbuddy' );
			}
			echo '</span>';
			?>
		</td>
		<td><?php echo $deploy->_state['destination']['siteurl']; ?></td>
		<?php if ( true === $siteUp ) { ?>
			<td class="deploy-pushpull-wrap">
				<?php if ( isset( $destination['disable_push'] ) && ( '1' == $destination['disable_push'] ) ) { ?>
					<s><a href="javascript:void(0);" class="deploy-push-text" onClick="alert( 'This option is disabled in this destination\'s settings.' );">Push to<br><span class="deploy-push-text-sub">faster</span></a></s>
				<?php } else { ?>
					<a href="javascript:void(0);" class="deploy-push-text" onClick="jQuery( '.deploy-type-selected' ).removeClass( 'deploy-type-selected' ); jQuery(this).addClass( 'deploy-type-selected' ); jQuery('#deploy-pull-wrap').hide(); jQuery('#deploy-push-wrap').slideDown(); jQuery('#backupbuddy_deploy_direction').attr('data-direction','push' ); jQuery( '.database_contents_shortcuts-prefix' ).click(); jQuery( '.plugins_shortcuts-none' ).click();">Push to<br><span class="deploy-push-text-sub">faster</span></a>
				<?php } ?>
				<?php if ( isset( $destination['disable_pull'] ) && ( '1' == $destination['disable_pull'] ) ) { ?>
					<s><a href="javascript:void(0);" class="deploy-pull-text" onClick="alert( 'This option is disabled in this destination\'s settings.' );">Pull from<br><span class="deploy-pull-text-sub">slower</span></a></s>
				<?php } else { ?>
					<a href="javascript:void(0);" class="deploy-pull-text" onClick="jQuery( '.deploy-type-selected' ).removeClass( 'deploy-type-selected' ); jQuery(this).addClass( 'deploy-type-selected' ); jQuery('#deploy-push-wrap').hide(); jQuery('#deploy-pull-wrap').slideDown(); jQuery('#backupbuddy_deploy_direction').attr('data-direction','pull' ); jQuery( '.database_contents_shortcuts-prefix' ).click(); jQuery( '.plugins_shortcuts-none' ).click();">Pull from<br><span class="deploy-push-text-sub">slower</span></a>
				<?php } ?>
			</td>
		<?php } ?>
	</tr>
</table>




<?php



$deployData = $deploy->getState();
$deployDataJson = json_encode( $deployData );
echo '<script>window.deployData = ' . $deployDataJson . '; console.log("deployData (len: ' . strlen( $deployDataJson ) . '):"); console.dir( window.deployData );</script>';

$localInfoJson = json_encode( $localInfo );
echo '<script>console.log("localInfo (len: ' . strlen( $localInfoJson ) . '; hint: find remoteInfo in deployData[\'remoteInfo\']):"); console.dir( ' . $localInfoJson . ' );</script>';
unset( $localInfoJson );


if ( '' != $errorMessage ) {
	pb_backupbuddy::alert( $errorMessage, true );
}



$unmatchedPlugins = array();
$wrapBefore = '';
$wrapAfter = '';

$wrapBefore = '';
$wrapAfter = '';
if ( $localInfo['backupbuddyVersion'] != $deployData['remoteInfo']['backupbuddyVersion'] ) {
	$wrapBefore = '<span class="deploy-unmatched-plugin-version">';
	$wrapAfter = '</span>';
}

$activePluginsA = $wrapBefore . 'BackupBuddy v' . $localInfo['backupbuddyVersion'] . $wrapAfter . '<span style="position: relative; top: -0.5em; font-size: 0.7em;">&Dagger;</span>'; // Start with BB. Is only in the visual list. Will not be deployed.
$i = 0; $x = count( $localInfo['activePlugins'] );
foreach( (array)$localInfo['activePlugins'] as $index => $localPlugin ) {
	if ( 0 == $i ) {
		$activePluginsA .= ', ';
	}
	$i++;
	
	$wrapBefore = '';
	$wrapAfter = '';
	if ( ! isset( $deployData['remoteInfo']['activePlugins'][ $index ] ) ) { // Plugin is not on remote site.
		$unmatchedPlugins[] = $index;
		$wrapBefore = '<span class="deploy-unmatched-plugin">';
		$wrapAfter = '</span>';
	} else { // Plugin is on remote site. Check if version differs.
		if ( $deployData['remoteInfo']['activePlugins'][ $index ]['version'] != $localPlugin['version'] ) {
			$unmatchedPlugins[] = $index;
			$wrapBefore = '<span class="deploy-unmatched-plugin-version">';
			$wrapAfter = '</span>';
		}
	}
	
	$activePluginsA .= $wrapBefore . $localPlugin['name'] . ' v' . $localPlugin['version'] . $wrapAfter;
	if ( $x > $i ) {
		$activePluginsA .= ', ';
	}
	
	/*
	if ( false !== strpos( $localPlugin['name'], 'BackupBuddy' ) ) {
		unset( $localInfo['activePlugins'][ $index ] );
	}
	*/
}


if ( $localInfo['backupbuddyVersion'] != $deployData['remoteInfo']['backupbuddyVersion'] ) {
	$wrapBefore = '<span class="deploy-unmatched-plugin-version">';
	$wrapAfter = '</span>';
}
$activePluginsB = $wrapBefore . 'BackupBuddy v' . $deployData['remoteInfo']['backupbuddyVersion'] . $wrapAfter . '<span style="position: relative; top: -0.5em; font-size: 0.7em;">&Dagger;</span>'; // Start with BB. Is only in the visual list. Will not be deployed.
$i = 0; $x = count( $deployData['remoteInfo']['activePlugins'] );
foreach( (array)$deployData['remoteInfo']['activePlugins'] as $index => $remotePlugin ) {
	if ( 0 == $i ) {
		$activePluginsB .= ', ';
	}
	$i++;
	
	$wrapBefore = '';
	$wrapAfter = '';
	if ( ! isset( $localInfo['activePlugins'][ $index ] ) ) { // Plugin is not on local site.
		$unmatchedPlugins[] = $index;
		$wrapBefore = '<span class="deploy-unmatched-plugin">';
		$wrapAfter = '</span>';
	} else { // Plugin is on local site. Check if version differs.
		if ( $localInfo['activePlugins'][ $index ]['version'] != $remotePlugin['version'] ) {
			$unmatchedPlugins[] = $index;
			$wrapBefore = '<span class="deploy-unmatched-plugin-version">';
			$wrapAfter = '</span>';
		}
	}
	
	$activePluginsB .= $wrapBefore . $remotePlugin['name'] . ' v' . $remotePlugin['version'] . $wrapAfter;
	if ( $x > $i ) {
		$activePluginsB .= ', ';
	}
	
	/*
	if ( false !== strpos( $localPlugin['name'], 'BackupBuddy' ) ) {
		unset( $localInfo['activePlugins'][ $index ] );
	}
	*/
}
?>
<br><br>

<span id="backupbuddy_deploy_direction" data-direction=""></span>
<span id="backupbuddy_deploy_prefixA" data-prefix="<?php echo $localInfo['dbPrefix']; ?>"></span>
<span id="backupbuddy_deploy_prefixB" data-prefix="<?php echo $deployData['remoteInfo']['dbPrefix']; ?>"></span>



<div id="deploy-push-wrap" style="display: none;">
	<?php require_once( '_push.php' ); ?>
</div>


<div id="deploy-pull-wrap" style="display: none;">
	<?php require_once( '_pull.php' ); ?>
</div>




<script>
		jQuery(document).ready(function() {
			
			jQuery( '.deploy-show-files' ).click( function(){
				if ( jQuery('.deploy-files-list-box:visible').length > 0 ) {
					jQuery('.deploy-files-list-box:visible').remove();
					return;
				}
				jQuery(this).after( jQuery('#bb_deploy_files').html() );
				jQuery(this).next( '.deploy-files-list-box' ).val( window.deployData[ jQuery(this).attr('rel') ].join( "\n" ) );
			});
			
			/* Begin database contents selecting */
			jQuery( '.database_contents_shortcuts-all' ).click( function(e){
				e.preventDefault();
				jQuery( '.database_contents_select' ).find( 'input' ).prop( 'checked', true );
				bb_count_selected_tables();
			});
			
			jQuery( '.database_contents_shortcuts-none' ).click( function(e){
				e.preventDefault();
				jQuery( '.database_contents_select' ).find( 'input' ).prop( 'checked', false );
				bb_count_selected_tables();
			});
			
			jQuery( '.database_contents_select input' ).click( function(){
				bb_count_selected_tables();
			});
			
			jQuery( '.database_contents_shortcuts-prefix' ).click( function(e){
				e.preventDefault();
				
				if ( 'push' == jQuery('#backupbuddy_deploy_direction').attr( 'data-direction' ) ) {
					prefix = jQuery( '#backupbuddy_deploy_prefixA' ).attr( 'data-prefix' );
				} else {
					prefix = jQuery( '#backupbuddy_deploy_prefixB' ).attr( 'data-prefix' );
				}
				
				jQuery( '.database_contents_select' ).find( 'input' ).each( function(index){
					if ( jQuery(this).val().indexOf( prefix ) == 0 ) {
						jQuery(this).prop( 'checked', true );
					} else {
						jQuery(this).prop( 'checked', false );
					}
				});
				
				bb_count_selected_tables();
			});
			/* End database contents selecting. */
			
			
			
			/* Begin plugins selecting. */
			jQuery( '.plugins_shortcuts-all' ).click( function(e){
				e.preventDefault();
				jQuery( '.plugins_select' ).find( 'input' ).prop( 'checked', true );
				bb_count_selected_plugins();
			});
			
			jQuery( '.plugins_shortcuts-none' ).click( function(e){
				e.preventDefault();
				jQuery( '.plugins_select' ).find( 'input' ).prop( 'checked', false );
				bb_count_selected_plugins();
			});
			
			jQuery( '.plugins_select input' ).click( function(){
				bb_count_selected_plugins();
			});
			/* End plugins selecting. */
			
			
			
		});

		function bb_count_selected_tables() {
			tableCount = jQuery( '.database_contents_select:visible' ).find( 'input:checked' ).length;
			jQuery( '.database_contents_select_count' ).text( tableCount );
		}
		
		function bb_count_selected_plugins() {
			pluginCount = jQuery( '.plugins_select:visible' ).find( 'input:checked' ).length;
			jQuery( '.plugins_select_count' ).text( pluginCount );
		}
		
		bb_count_selected_tables();
		bb_count_selected_plugins();
	</script>

<div id="bb_deploy_files" style="display: none;">
	<textarea readonly="readonly" class="deploy-files-list-box"></textarea>
</div>


<?php
// Handles thickbox auto-resizing. Keep at bottom of page to avoid issues.
if ( !wp_script_is( 'media-upload' ) ) {
	wp_enqueue_script( 'media-upload' );
	wp_print_scripts( 'media-upload' );
}
