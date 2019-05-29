<?php
/**
 * PluginBuddy Helper functions
 *
 * @package BackupBuddy
 */

/**
 * Determines if PB_STANDALONE is enabled.
 *
 * @return bool  If PB_STANDALONE is defined and set to true.
 */
function pb_is_standalone() {
	return defined( 'PB_STANDALONE' ) && true === PB_STANDALONE;
}
