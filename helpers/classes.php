<?php
/**
 * Class helper functions.
 *
 * @package BackupBuddy
 */

/**
 * Instance generator for Data File class with class autoloader.
 *
 * @return object  BackupBuddy_Data_File instance.
 */
function backupbuddy_data_file() {
	if ( ! class_exists( 'BackupBuddy_Data_File' ) ) {
		require_once pb_backupbuddy::plugin_path() . '/classes/class-backupbuddy-data-file.php';
	}

	return BackupBuddy_Data_File::get_instance();
}

/**
 * Instance generator for Restore class.
 *
 * @return object  BackupBuddy_Restore instance.
 */
function backupbuddy_restore() {
	if ( ! class_exists( 'BackupBuddy_Restore' ) ) {
		require_once pb_backupbuddy::plugin_path() . '/classes/class-backupbuddy-restore.php';
	}

	return BackupBuddy_Restore::get_instance();
}

/**
 * Instance generator for Backups class.
 *
 * @return object  BackupBuddy_Backups instance.
 */
function backupbuddy_backups() {
	if ( ! class_exists( 'BackupBuddy_Backups' ) ) {
		require_once pb_backupbuddy::plugin_path() . '/classes/class-backupbuddy-backups.php';
	}

	return BackupBuddy_Backups::get_instance();
}
