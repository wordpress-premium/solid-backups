<?php
/**
 * Site Size Info on Server Tools Page
 *
 * @package BackupBuddy
 */

pb_backupbuddy::load_script( 'icicle.js' );
pb_backupbuddy::load_script( 'icicle_setup.js' );
pb_backupbuddy::load_style( 'jit_base.css' );
pb_backupbuddy::load_style( 'jit_icicle.css' );
?>
<script type="text/javascript">
	jQuery(function() {
		jQuery('#pb_iciclelaunch').on( 'click', function(e) {
			jQuery('#pb_infovis_container').slideToggle();
			jQuery.post( '<?php echo pb_backupbuddy::ajax_url( 'icicle' ); ?>',
				function( data ) {
					jQuery('#infovis').html('');
					icicle_init( data );
				}
			);
		});

		jQuery( '.pb_backupbuddy_site_size_listing_button' ).on( 'click', function() {
			jQuery( '#pb_backupbuddy_site_size_listing_intro > .pb_backupbuddy_loading' ).show();
			jQuery.post( '<?php echo pb_backupbuddy::ajax_url( 'site_size_listing' ); ?>&profile=' + jQuery( '#pb_backupbuddy_filelistingprofile' ).val(),
				function( data ) {
					jQuery( '#pb_backupbuddy_site_size_listing_content' ).html( data );
					jQuery( '#pb_backupbuddy_site_size_listing_intro > .pb_backupbuddy_loading' ).hide();
					jQuery( '#pb_backupbuddy_site_size_listing_content' ).slideDown();
				}
			);
			jQuery( 'pb_backupbuddy_loading' ).hide();
		} );

	});
</script>
<style type="text/css">
	#pb_backupbuddy_serverinfo_exclusions::-webkit-scrollbar {
		-webkit-appearance: none;
		width: 11px;
		height: 11px;
	}
	#pb_backupbuddy_serverinfo_exclusions::-webkit-scrollbar-thumb {
		border-radius: 8px;
		border: 2px solid white; /* should match background, can't be transparent */
		background-color: rgba(0, 0, 0, .5);
	}​
</style>

<br>
<h3 class="pb_htitle"><?php esc_html_e( 'Directory Size Listing', 'it-l10n-backupbuddy' ); ?></h3>
<a name="pb_backupbuddy_dir_size_listing">&nbsp;</a>

<div id="pb_backupbuddy_site_size_listing_intro">
	<?php esc_html_e( 'Display a comprehensive listing of directories and the corresponding size of all contents within, including subdirectories.  This is useful for finding where space is being used. Note that this is a CPU intensive process and may take a while to load and even time out on some servers.', 'it-l10n-backupbuddy' ); ?>
	<br /><br />

	<b>Backup profile for calculating exclusions:</b>
	<select id="pb_backupbuddy_filelistingprofile">
		<?php
		foreach ( pb_backupbuddy::$options['profiles'] as $this_profile_id => $profile ) {
			if ( 'db' == $profile['type'] ) {
				continue;
			}
			?>
			<option value="<?php echo esc_attr( $this_profile_id ); ?>" <?php selected( $profile_id, $this_profile_id ); ?>>
				<?php echo esc_html( $profile['title'] ); ?> (<?php echo esc_html( $profile['type'] ); ?>)
			</option>
		<?php } ?>
	</select><br>


	<br>
	<a class="pb_backupbuddy_site_size_listing_button button button-primary" style="margin-top: 3px;"><?php esc_html_e( 'Display Directory Size Listing', 'it-l10n-backupbuddy' ); ?></a>
	<span class="pb_backupbuddy_loading" style="display: none; margin-left: 10px;"><img src="<?php echo pb_backupbuddy::plugin_url(); ?>/images/loading.gif" alt="<?php esc_attr_e( 'Loading...', 'it-l10n-backupbuddy' ); ?>" title="<?php esc_attr_e( 'Loading...', 'it-l10n-backupbuddy' ); ?>" width="16" height="16" style="vertical-align: -3px;" /></span>
</div>
<br>
<div id="pb_backupbuddy_site_size_listing_content" style="display: none;"></div>
<br><br>
<h3 class="pb_htitle"><?php esc_html_e( 'Interactive Graphical Directory Size Map', 'it-l10n-backupbuddy' ); ?></h3>
<br>
<?php
esc_html_e( 'Displays an interactive graphical representation of directories and the corresponding size of all contents within, including subdirectories.
This is useful for finding where space is being used. Directory boxes are scaled based on size. Click on a directory box to move around. Note that this
is a CPU intensive process and may take a while to load and even time out on some servers. Slower computers may have trouble navigating the interactive map.', 'it-l10n-backupbuddy' );
?>
<br>
<p><a id="pb_iciclelaunch" class="button button-primary" style="margin-top: 3px;"><?php esc_html_e( 'Display Interactive Graphical Directory Size Map', 'it-l10n-backupbuddy' ); ?></a></p>

<link type="text/css" href="<?php echo pb_backupbuddy::plugin_url(); ?>/css/jit_base.css" rel="stylesheet" />
<link type="text/css" href="<?php echo pb_backupbuddy::plugin_url(); ?>/css/jit_icicle.css" rel="stylesheet" />

<div style="display: none;" id="pb_infovis_container">
	<div style="background: #1A1A1A;">
		<div id="infovis">
			<br /><br />
			<div style="margin: 30px;">
				<h4 style="color: #FFFFFF;"><img src="<?php echo pb_backupbuddy::plugin_url(); ?>/images/loading_large_darkbg.gif" style="vertical-align: -9px;" /> <?php esc_html_e( 'Loading ... Please wait ...', 'it-l10n-backupbuddy' ); ?></h4>
			</div>
		</div>
	</div>

	<label for="s-orientation"><?php esc_html_e( 'Orientation', 'it-l10n-backupbuddy' ); ?>: </label>
	<select name="s-orientation" id="s-orientation">
		<option value="h" selected><?php esc_html_e( 'horizontal', 'it-l10n-backupbuddy' ); ?></option>
		<option value="v"><?php esc_html_e( 'vertical', 'it-l10n-backupbuddy' ); ?></option>
	</select>

	<label for="i-levels-to-show"><?php esc_html_e( 'Max levels', 'it-l10n-backupbuddy' ); ?>: </label>
	<select  id="i-levels-to-show" name="i-levels-to-show" style="width: 50px">
		<option>all</option>
		<option>1</option>
		<option>2</option>
		<option selected="selected">3</option>
		<option>4</option>
		<option>5</option>
	</select>

	<a id="update" class="theme button white"><?php esc_html_e( 'Go Up', 'it-l10n-backupbuddy' ); ?></a>
</div>
<br><br>

<?php
$dir_array    = array();
$icicle_array = array();
$time_start   = microtime( true );
