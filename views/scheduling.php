<?php
/**
 * Scheduling Page View
 *
 * @package BackupBuddy
 */

wp_enqueue_script( 'thickbox' );
wp_print_scripts( 'thickbox' );
wp_print_styles( 'thickbox' );
?>
<script type="text/javascript">
	function pb_backupbuddy_selectdestination( destination_id, destination_title, callback_data, delete_after, mode ) {
		jQuery( '#pb_backupbuddy_remotedestinations_list' ).append( '<li id="pb_remotedestination_' + destination_id + '">' + destination_title + ' <img class="pb_remotedestionation_delete" src="<?php echo pb_backupbuddy::plugin_url(); ?>/images/redminus.png" style="vertical-align: -3px; cursor: pointer;" title="<?php esc_html_e( 'Remove remote destination from this schedule.', 'it-l10n-backupbuddy' ); ?>" /></li>' + "\n" );
		jQuery( '#pb_backupbuddy_deleteafter' ).slideDown();
	}

	jQuery(function() {
		/* Generate the remote destination list upon submission. */
		jQuery('#pb_backupbuddy_scheduling_form').submit(function(e) {
			remote_destinations = '';
			jQuery( '#pb_backupbuddy_remotedestinations_list' ).children('li').each(function () {
				remote_destinations = jQuery(this).attr( 'id' ).substr( 21 ) + '|' + remote_destinations ;
			});
			jQuery( '#pb_backupbuddy_remote_destinations' ).val( remote_destinations );
		});


		/* Allow deleting of remote destinations from the list. */
		jQuery(document).on( 'click', '.pb_remotedestionation_delete', function(e) {
			jQuery( '#pb_remotedestination_' + jQuery(this).parent( 'li' ).attr( 'id' ).substr( 21 ) ).remove();
		});


		jQuery('.pluginbuddy_pop').click(function(e) {
			showpopup('#'+jQuery(this).attr('href'),'',e);
			return false;
		});
	});
</script>

<?php
pb_backupbuddy::$ui->title( __( 'Schedules', 'it-l10n-backupbuddy' ), true, false );

if ( ! class_exists( 'BackupBuddy_Tabs' ) ) {
	require_once pb_backupbuddy::plugin_path() . '/classes/class-backupbuddy-tabs.php';
}

$tabs = array(
	'schedules' => array(
		'id'       => 'schedules',
		'label'    => esc_html__( 'Schedules', 'it-l10n-backupbuddy' ),
		'callback' => function() use ( $schedule_form, $schedules ) {
			require_once pb_backupbuddy::plugin_path() . '/views/schedules/schedules.php';
		},
	),

	'add'       => array(
		'id'       => 'add',
		'label'    => esc_html__( 'Add Schedule', 'it-l10n-backupbuddy' ),
		'callback' => function() use ( $schedule_form ) {
			require_once pb_backupbuddy::plugin_path() . '/views/schedules/add-schedule.php';
		},
	),
);

if ( pb_backupbuddy::_GET( 'edit' ) ) {
	$tabs['schedules']['href'] = pb_backupbuddy::page_url();
	unset( $tabs['schedules']['callback'] );

	$tabs['add']['href'] = pb_backupbuddy::page_url() . '&tab=add';
	unset( $tabs['add']['callback'] );

	$tabs['edit-schedule'] = array(
		'id'       => 'edit-schedule',
		'label'    => esc_html__( 'Edit Schedule', 'it-l10n-backupbuddy' ),
		'callback' => function() use ( $schedule_form, $schedules ) {
			require_once pb_backupbuddy::plugin_path() . '/views/schedules/schedules.php';
		},
	);
}

$tabs = new BackupBuddy_Tabs( $tabs );
$tabs->render();

// Handles thickbox auto-resizing. Keep at bottom of page to avoid issues.
if ( ! wp_script_is( 'media-upload' ) ) {
	wp_enqueue_script( 'media-upload' );
	wp_print_scripts( 'media-upload' );
}
