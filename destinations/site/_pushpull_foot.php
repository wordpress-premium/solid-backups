<?php
// Incoming vars:
// $deployDirection		push or pull
?>
&nbsp;
<span class="deploy-unmatched-plugin"><?php _e( 'Red indicates a plugin that does not exist on both sites.', 'it-l10n-backupbuddy' ); ?></span> <span class="deploy-unmatched-plugin-version"><?php _e( 'Blue indicates different versions.', 'it-l10n-backupbuddy' ); ?></span>

<br><br>
<table class="widefat">
	<thead>
		<tr class="thead">
			<th>&nbsp;</th><th style="white-space: nowrap;"><?php echo $headFoot[0]; ?></th><th><span class="dashicons dashicons-arrow-right-alt"></span></th><th style="white-space: nowrap;"><?php echo $headFoot[1]; ?></th>
		</tr>
	</thead>
	<tfoot>
		<tr class="thead">
			<th>&nbsp;</th><th><?php echo $headFoot[0]; ?></th><th><span class="dashicons dashicons-arrow-right-alt"></span></th><th><?php echo $headFoot[1]; ?></th>
		</tr>
	</tfoot>
	<tbody>
		<?php
		$i = 0;
		foreach( $pushRows as $pushTitle => $pushRow ) { ?>
			<tr class="entry-row alternate">
				<td class="tdhead" style="white-space: nowrap;"><?php echo $pushTitle; ?></td>
				<td><?php echo $pushRow[0]; ?></td>
				<td>&nbsp;</td>
				<td><?php echo $pushRow[1]?></td>
			</tr>
		<?php }
		?>
	</tbody>
</table>



<?php
if ( is_network_admin() ) {
	$backup_url = network_admin_url( 'admin.php' );
} else {
	$backup_url = admin_url( 'admin.php' );
}
$backup_url .= '?page=pb_backupbuddy_backup';
?>
<br>

<!-- <form id="pb_backupbuddy_deploy_form" method="post" action="<?php echo pb_backupbuddy::ajax_url( 'deploy' ); ?>?action=pb_backupbuddy_backupbuddy&function=deploy&step=run"> -->
<form target="_top" id="pb_backupbuddy_deploy_form" method="post" action="<?php echo $backup_url; ?>&backupbuddy_backup=deploy&direction=<?php echo $deployDirection; ?>">
	<input type="hidden" name="destination_id" value="<?php echo $destination_id; ?>">

	<style>
		.database_contents_select, .plugins_select {
			padding: 5px;
			line-height: 1.7em;
			overflow: scroll;
			border: 1px solid #ddd;
			background: #f9f9f9;
			resize: both;
			height: 150px;
			width: 400px;
		}
		.database_contents_select::-webkit-scrollbar, .plugins_select::-webkit-scrollbar {
			-webkit-appearance: none;
			width: 11px;
			height: 11px;
		}
		.database_contents_select::-webkit-scrollbar-thumb, .plugins_select::-webkit-scrollbar-thumb {
			border-radius: 8px;
			border: 2px solid white; /* should match background, can't be transparent */
			background-color: rgba(0, 0, 0, .1);
		}
		.database_contents_shortcuts, .plugins_shortcuts {
			color: #ADADAD;
			margin-bottom: 3px;
		}
		.database_contents_shortcuts a, .plugins_shortcuts a {
			text-decoration: none;
			cursor: pointer;
		}
	</style>
	
	
	<h3>Database Find & Replace</h3>
	The site URL (www and domain) and paths will be updated. Serialized data will be accounted for.<br>
	<input type="text" value="<?php echo $localInfo['siteurl']; ?>" disabled> &rarr; <input type="text" value="<?php echo $deployData['remoteInfo']['siteurl']; ?>" disabled><br>
	<input type="text" value="<?php echo $localInfo['abspath']; ?>" disabled> &rarr; <input type="text" value="<?php echo $deployData['remoteInfo']['abspath']; ?>" disabled><br>
	<!-- <input type="text"> -&gt; <input type="text"> - +<br> -->
	
	<h3><?php _e( 'Directory Exclusions', 'LIONS' ); ?></h3>
	<textarea readonly="readonly" style="resize: auto; height: 75px; width: 400px;"><?php print_r( $deployData['destinationSettings']['excludes'] ); ?></textarea>
	<br>
	
	<h3><?php _e( 'Additional Inclusions', 'LIONS' ); ?></h3>
	<textarea readonly="readonly" style="resize: auto; height: 75px; width: 400px;"><?php print_r( $deployData['destinationSettings']['extras'] ); ?></textarea>
	<br><br>

	<h3><?php
		if ( 'pull' == $deployDirection ) {
			_e( 'Pull', 'it-l10n-backupbuddy' );
		} else { // push
			_e( 'Push', 'it-l10n-backupbuddy' );
		}
		echo ' ';
		_e( 'Database Contents', 'it-l10n-backupbuddy' );
	?></h3>
	<input type="hidden" name="backup_profile" value="1">
	<div class="database_contents_shortcuts">
		<a class="database_contents_shortcuts-all" title="Select all database tables.">Select All</a> | <a class="database_contents_shortcuts-none" title="Unselect all database tables.">Unselect All</a> | <a class="database_contents_shortcuts-prefix" title="Select database tables matching the WordPress table prefix of the source site.">WordPress Table Prefix</a>
	</div>
	<div class="database_contents_select">
		<?php
		if ( 'pull' == $deployDirection ) {
			$tables = $deployData['remoteInfo']['tables'];
		} else { // push
			$tables = $localInfo['tables'];
		}
		foreach( (array)$tables as $table ) {
			echo '<label><input type="checkbox" name="tables[]" value="' . $table . '"> ' . $table . '</label><br>';
		}
		?>
	</div>
	<span class="description">
		(<span class="database_contents_select_count"></span> tables selected)
	</span>
	<br><br>
	
	<h3><?php 
		if ( 'pull' == $deployDirection ) {
			_e( 'Pull', 'it-l10n-backupbuddy' );
		} else { // push
			_e( 'Push', 'it-l10n-backupbuddy' );
		}
		echo ' ';
		_e( 'Differing Active Plugins', 'LIONS' );
	?></h3>
	
	
	
	<div class="plugins_shortcuts">
		<a class="plugins_shortcuts-all" title="Select all database tables.">Select All</a> | <a class="plugins_shortcuts-none" title="Unselect all database tables.">Unselect All</a>
	</div>
	<div class="plugins_select">
		<?php
		if ( 'pull' == $deployDirection ) {
			foreach( (array)$deployData['remoteInfo']['activePlugins'] as $pluginFile => $plugin ) {
				if ( ! in_array( $pluginFile, $unmatchedPlugins ) ) { // Plugin same on both sides. Skip asking to send this.
					continue;
				}
				echo '<label><input type="checkbox" name="sendPlugins[]" value="' . $pluginFile . '"> ' . $plugin['name'] . ' v' . $plugin['version'] . '</label><br>';
			}
		} else { // push
			foreach( (array)$localInfo['activePlugins'] as $pluginFile => $plugin ) {
				if ( ! in_array( $pluginFile, $unmatchedPlugins ) ) { // Plugin same on both sides. Skip asking to send this.
					continue;
				}
				echo '<label><input type="checkbox" name="sendPlugins[]" value="' . $pluginFile . '"> ' . $plugin['name'] . ' v' . $plugin['version'] . '</label><br>';
			}
		}
		?>
	</div>
	<span class="description">
		(<span class="plugins_select_count"></span> plugins selected)
	</span>
	
	
	
	<br><br>
	
	<h3><?php
		if ( 'pull' == $deployDirection ) {
			_e( 'Pull', 'it-l10n-backupbuddy' );
		} else { // push
			_e( 'Push', 'it-l10n-backupbuddy' );
		}
		echo ' ';
		_e( 'Files (media, theme, additional)', 'LIONS' ); ?></h3>
	<?php
	// Main theme
	if ( $deployData['remoteInfo']['activeTheme'] == $localInfo['activeTheme'] ) {
		echo '<label><input type="checkbox" name="sendTheme" value="true"> Update <b>ACTIVE THEME files</b> (template) with newer or missing files to match.</label>';
	} else {
		echo '<span class="description">' . __( 'Active theme differs so theme deployment is disabled.', 'it-l10n-backupbuddy' ) . '</span>';
	}
	
	// Child theme.
	if ( isset( $deployData['remoteInfo']['activeChildTheme'] ) ) {
		if ( $deployData['remoteInfo']['activeChildTheme'] == $localInfo['activeChildTheme'] ) { // Remote and local child theme are the same.
			if ( $localInfo['activeChildTheme'] != $localInfo['activeTheme'] ) { // Theme and childtheme differ so files will need sent.
				echo '<br><label><input type="checkbox" name="sendChildTheme" value="true"> Update <b>CHILD THEME files</b> (stylesheet) with newer or missing files to match.';
			} else { // Theme and childtheme are same directory so only theme will be needed to pull/push.
				echo ' <span class="description">' . __( 'Child theme & theme are the same so files will not be re-sent.', 'it-l10n-backupbuddy' ) . '</span>';
			}
		} else {
			echo ' <span class="description">' . __( 'Active child theme differs so theme deployment is disabled.', 'it-l10n-backupbuddy' ) . '</span>';
		}
	} else {
		echo ' <span class="description">' . __( 'Remote site does not support deploying child theme. Update remote BackupBuddy.', 'it-l10n-backupbuddy' ) . '</span>';
	}
	echo '</label>';
	echo '<br><label><input type="checkbox" name="sendMedia" value="true"> Update <b>MEDIA files</b> with newer or missing files to match.</label>';
	echo '<br><label><input type="checkbox" name="sendExtras" value="true"> Update <b>ADDITIONAL files</b> as defined in destination settings with newer or missing files to match.</label>';
	?>
	<br><br>
	
	<h3>Site Search Engine Visibility</h3>
	<div style="display: inline-block; text-align: left;">
		<?php if ( '' == pb_backupbuddy::$options['remote_destinations'][$destination_id]['set_blog_public'] ) { pb_backupbuddy::$options['remote_destinations'][$destination_id]['set_blog_public'] = ''; } // DEFAULT. ?>
		<label for="set_blog_public-keep" style="font-size: 12px;"><input type="radio" name="setBlogPublic" id="set_blog_public-keep" value="" <?php if ( '' == pb_backupbuddy::$options['remote_destinations'][$destination_id]['set_blog_public'] ) { echo 'checked="checked"'; } ?>><?php _e(' No change from source site value', 'it-l10n-backupbuddy' ); ?></label><br>
		<label for="set_blog_public-public" style="font-size: 12px;"><input type="radio" name="setBlogPublic" id="set_blog_public-public" value="1" <?php if ( '1' == pb_backupbuddy::$options['remote_destinations'][$destination_id]['set_blog_public'] ) { echo 'checked="checked"'; } ?>><?php _e(' Public - Do not discourage search engines from indexing this site', 'it-l10n-backupbuddy' ); ?></label><br>
		<label for="set_blog_public-private" style="font-size: 12px;"><input type="radio" name="setBlogPublic" id="set_blog_public-private" value="0" <?php if ( '0' == pb_backupbuddy::$options['remote_destinations'][$destination_id]['set_blog_public'] ) { echo 'checked="checked"'; } ?>><?php _e(' Private - Discourage search engines from indexing this site', 'it-l10n-backupbuddy' ); ?></label><br>
	</div>
	<br><br>
	
	
	
	
	
	<br>
	<?php pb_backupbuddy::nonce(); ?>
	<input type="hidden" name="destination" value="<?php echo $destination_id; ?>">
	<input type="hidden" name="deployData" value="<?php echo base64_encode( json_encode( $deployData ) ); ?>">
	
	
	<a class="button button-secondary" onclick="jQuery('.pb_backupbuddy_advanced').slideToggle();">Advanced Options</a>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<div class="pb_backupbuddy_advanced" style="display: none; margin-bottom: 15px; clear: both; background: rgb(229, 229, 229); padding: 15px; margin-top: 15px; border-radius: 10px;">
		<label>Source chunk time limit: <input size="5" maxlength="5" type="text" name="sourceMaxExecutionTime" value="<?php echo $localInfo['php']['max_execution_time']; ?>"> sec</label>
		<br>
		<label>Destination chunk time limit: <input size="5" maxlength="5" type="text" name="destinationMaxExecutionTime" value="<?php echo $deployData['remoteInfo']['php']['max_execution_time']; ?>"> sec</label>
		<br>
		<label><input type="checkbox" name="doImportCleanup" value="true" checked="checked"> Cleanup importbuddy & log at end of Deployment</label>
		<br>
	</div>
	
	<input type="submit" name="submitForm" class="button button-primary" value="<?php
	if ( 'pull' == $deployDirection ) {
		_e('Begin Pull');
	} elseif( 'push' == $deployDirection ) {
		_e('Begin Push');
	} else {
		echo '{Err3849374:UnknownDirection}';
	}
	?> &raquo;">
	
</form>

