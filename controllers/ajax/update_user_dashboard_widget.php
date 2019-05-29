<?php
/**
 * Update User Dashboard Widget Mode
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$mode = ! empty( $_POST['mode'] ) ? trim( strtolower( (string) $_POST['mode'] ) ) : '';
$user = get_current_user_id();

if ( $user && $mode ) {
	update_user_meta( $user, 'backupbuddy_dashboard_widget_mode', $mode );
}

die();
