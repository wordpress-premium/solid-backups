<?php
/**
 * Server info page extended PHPinfo thickbox.
 * Server info page phpinfo button.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

if ( ! backupbuddy_core::is_func_allowed( 'phpinfo' ) ) {
	printf( __( 'The %s function is not allowed on your system.', 'it-l10n-backupbuddy' ), '<code>phpinfo()</code>' );
	die;
}

phpinfo();

die();
