<?php
/**
 * NOTE: DO NOT PUT PAGES HERE FOR NOW -- PUT THEM IN A PAGENAME.PHP FILE IN THE CONTROLLERS/PAGES SUBDIRECTORY.
 *
 * @package BackupBuddy
 */

/**
 * See note above.
 */
class pb_backupbuddy_pages extends pb_backupbuddy_pagescore {
	/**
	 * Only runs after init and such have passed.
	 */
	public function __construct() {
		if ( false !== stristr( pb_backupbuddy::_GET( 'page' ), 'backupbuddy' ) || 'true' == pb_backupbuddy::_GET( 'activate' ) ) {
			pb_backupbuddy::add_action( array( 'admin_notices', 'admin_notices' ) );
		}
	}
}
