<?php
/* BackupBuddy Stash Live Remote Tables Viewer (table format; not file format)
 *
 * @author Dustin Bolton
 * @since 7.0
 *
 */

require_once( pb_backupbuddy::plugin_path() . '/destinations/live/live_periodic.php' );
$tables = backupbuddy_live_periodic::get_tables();




$tables_list = array();
foreach( $tables as $table_name => $table ) {
	$pendingDelete = __( 'No', 'it-l10n-backupbuddy' );
	if ( true === $table['d'] ) {
		$pendingDelete = __( 'Yes', 'it-l10n-backupbuddy' );
	}
	$sent = __( 'Unsent', 'it-l10n-backupbuddy' );
	if ( $table['b'] > 0 ) {
		$sent = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $table['b'] ) ) . '<br><span class="description">(' . pb_backupbuddy::$format->time_ago( $table['b'] ) . ' ago)</span>';
	}
	$tables_list[] = array(
		$table_name,
		pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $table['m'] ) ) . '<br><span class="description">(' . pb_backupbuddy::$format->time_ago( $table['m'] ) . ' ago)</span>',
		$sent,
		pb_backupbuddy::$format->file_size( $table['s'] ),
		(string)$table['t'],
		$pendingDelete
	);
}

pb_backupbuddy::$ui->list_table(
	$tables_list,
	array(
		'action'		=>	pb_backupbuddy::page_url(),
		'columns'		=>	array(
								__( 'Database Tables', 'it-l10n-backupbuddy' ) . ' <img src="' . pb_backupbuddy::plugin_url() . '/images/sort_down.png" style="vertical-align: 0px;" title="Sorted alphabetically">',
								__( 'Modified', 'it-l10n-backupbuddy' ),
								__( 'Transferred', 'it-l10n-backupbuddy' ),
								__( 'Size', 'it-l10n-backupbuddy' ),
								__( 'Send Retries', 'it-l10n-backupbuddy' ),
								__( 'Pending Delete?', 'it-l10n-backupbuddy' ),
							),
		'css'			=>		'width: 100%',
	)
);
