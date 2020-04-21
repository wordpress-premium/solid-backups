<?php
/**
 * Regenerate Deployment Key AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

pb_backupbuddy::load();

if ( ! defined( 'BACKUPBUDDY_API_ENABLE' ) || true != BACKUPBUDDY_API_ENABLE ) {
	die(
		json_encode(
			array(
				'success' => false,
				'message' => 'Error #32332993: BackupBuddy API is not enabled in the wp-config.php.',
			)
		)
	);
}

require_once pb_backupbuddy::plugin_path() . '/classes/remote_api.php';
$new_key = backupbuddy_remote_api::generate_key();

pb_backupbuddy::$options['remote_api']['keys'][0] = $new_key;
pb_backupbuddy::save();

die(
	json_encode(
		array(
			'success' => true,
			'key'     => $new_key,
		)
	)
);
