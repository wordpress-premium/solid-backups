<?php
/**
 * Filetree View for Settings Page
 *
 * Optional incoming variable: $filetree_root
 *
 * @package BackupBuddy
 */

pb_backupbuddy::load_script( 'filetree.js' );
pb_backupbuddy::load_style( 'filetree.css' );

global $filetree_root;
if ( ! isset( $filetree_root ) ) {
	$filetree_root = '/';
}
$filetree_root = '/' . trim( $filetree_root, '/\\' ) . '/'; // Enforce leading and trailing slash.
$filetree_root = str_replace( '//', '/', $filetree_root );
?>

<script type="text/javascript">
	jQuery(function() {

		// Show options on hover.
		jQuery(document).on('mouseover mouseout', '.jqueryFileTree > li a', function(event) {
			if ( event.type == 'mouseover' ) {
				jQuery(this).children( '.pb_backupbuddy_treeselect_control' ).css( 'visibility', 'visible' );
			} else {
				jQuery(this).children( '.pb_backupbuddy_treeselect_control' ).css( 'visibility', 'hidden' );
			}
		});

		jQuery('#exlude_dirs').fileTree(
			{
				root: '<?php echo $filetree_root; ?>',
				multiFolder: false,
				script: '<?php echo pb_backupbuddy::ajax_url( 'exclude_tree' ); ?>'
			},
			function(text) {
				text = ( '/' + text.substring( <?php echo strlen( $filetree_root ) - 2; ?> ) ).replace(/\/+/g, '\/');
				if ( ( text == '/wp-config.php' ) || ( text == '/backupbuddy_dat.php' ) || ( text == '/wp-content/' ) || ( text == '/wp-content/uploads/' ) || ( text == '<?php echo '/' . str_replace( ABSPATH, '', backupbuddy_core::getBackupDirectory() ); ?>' ) || ( text == '<?php echo '/' . str_replace( ABSPATH, '', backupbuddy_core::getTempDirectory() ); ?>' ) ) {
					alert( "<?php esc_html_e( 'You cannot exclude the selected text or directory.  However, you may exclude subdirectories within many directories restricted from exclusion. BackupBuddy directories such as backupbuddy_backups are automatically excluded, preventing backing up backups, and cannot be added to exclusion list.', 'it-l10n-backupbuddy' ); ?>" );
				} else {
					jQuery( '#pb_backupbuddy_profiles__<?php echo esc_html( $profile_id ); ?>__excludes' ).val( text + "\n" + jQuery( '#pb_backupbuddy_profiles__<?php echo $profile_id; ?>__excludes' ).val() );
				}
			},
			function(text) {
				text = ( '/' + text.substring( <?php echo strlen( $filetree_root ) - 2; ?> ) ).replace(/\/+/g, '\/');
				if ( ( text == 'wp-config.php' ) || ( text == 'backupbuddy_dat.php' ) || ( text == '/wp-content/' ) || ( text == '/wp-content/uploads/' ) || ( text == '<?php echo '/' . str_replace( ABSPATH, '', backupbuddy_core::getBackupDirectory() ); ?>' ) || ( text == '<?php echo '/' . str_replace( ABSPATH, '', backupbuddy_core::getTempDirectory() ); ?>' ) ) {
					alert( "<?php esc_html_e( 'You cannot exclude the selected file or directory.  However, you may exclude subdirectories within many directories restricted from exclusion. BackupBuddy directories such as backupbuddy_backups are automatically excluded, preventing backing up backups, and cannot be added to exclusion list.', 'it-l10n-backupbuddy' ); ?>" );
				} else {
					jQuery( '#pb_backupbuddy_profiles__<?php echo esc_html( $profile_id ); ?>__excludes' ).val( text + "\n" + jQuery( '#pb_backupbuddy_profiles__<?php echo $profile_id; ?>__excludes' ).val() );
				}
			}
		);

	});
</script>

<?php itbub_file_icon_styles( '6px 6px', true ); ?>
