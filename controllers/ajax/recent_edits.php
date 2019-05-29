<?php
/**
 * Recent edits modal window via WordPress Dashboard
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$descriptions = array(
	'trash_post'        => array(
		'singular' => __( 'Trashed Post', 'it-l10n-backupbuddy' ),
		'plural'   => __( 'Trashed Posts', 'it-l10n-backupbuddy' ),
	),
	'post_updated'      => array(
		'singular' => __( 'Modified Post', 'it-l10n-backupbuddy' ),
		'plural'   => __( 'Modified Posts', 'it-l10n-backupbuddy' ),
	),
	'insert_post'       => array(
		'singular' => __( 'New Post', 'it-l10n-backupbuddy' ),
		'plural'   => __( 'New Posts', 'it-l10n-backupbuddy' ),
	),
	'update_option'     => array(
		'singular' => __( 'Modified Setting', 'it-l10n-backupbuddy' ),
		'plural'   => __( 'Modified Settings', 'it-l10n-backupbuddy' ),
	),
	'delete_option'     => array(
		'singular' => __( 'Deleted Setting', 'it-l10n-backupbuddy' ),
		'plural'   => __( 'Deleted Settings', 'it-l10n-backupbuddy' ),
	),
	'update_plugin'     => array(
		'singular' => __( 'Updated Plugin', 'it-l10n-backupbuddy' ),
		'plural'   => __( 'Updated Plugins', 'it-l10n-backupbuddy' ),
	),
	'activate_plugin'   => array(
		'singular' => __( 'Activated Plugin', 'it-l10n-backupbuddy' ),
		'plural'   => __( 'Activated Plugins', 'it-l10n-backupbuddy' ),
	),
	'deactivate_plugin' => array(
		'singular' => __( 'Deactivated Plugin', 'it-l10n-backupbuddy' ),
		'plural'   => __( 'Deactivated Plugins', 'it-l10n-backupbuddy' ),
	),
);

// Organize edits into groups.
$recent_edits  = pb_backupbuddy::$options['recent_edits'];
$tracking_mode = pb_backupbuddy::$options['edits_tracking_mode'];
$edit_groups   = array();

foreach ( $recent_edits as $edit ) {
	if ( ! isset( $edit_groups[ $edit['action'] ] ) ) {
		$edit_groups[ $edit['action'] ] = array();
	}
	$edit_groups[ $edit['action'] ][] = $edit;
}

pb_backupbuddy::$ui->ajax_header();
require_once pb_backupbuddy::plugin_path() . '/views/widgets/recent-edits.php';
pb_backupbuddy::$ui->ajax_footer();

die();
