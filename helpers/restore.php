<?php
/**
 * Restore helper functions.
 *
 * @package BackupBuddy
 */

/**
 * Checks BACKUPBUDDY_IS_RESTORING constant to see if restoring.
 *
 * This is only when the files/database are being changed, not when an actual restore is in progress.
 *
 * @return bool  If currently restoring files/database.
 */
function backupbuddy_is_restoring() {
	if ( ! defined( 'BACKUPBUDDY_IS_RESTORING' ) ) {
		return false;
	}
	return BACKUPBUDDY_IS_RESTORING;
}

if ( ! function_exists( 'in_assoc_array' ) ) {
	/**
	 * Check associative array for value under key.
	 *
	 * @param string $key       Array key to check.
	 * @param mixed  $needle    What to search for.
	 * @param array  $haystack  Array to search.
	 * @param bool   $strict    Use strict comparison.
	 *
	 * @return bool  If needle is found in haystack.
	 */
	function in_assoc_array( $key, $needle, $haystack, $strict = false ) {
		if ( ! is_array( $haystack ) ) {
			return false;
		}
		foreach ( $haystack as $item ) {
			if ( empty( $item[ $key ] ) ) {
				continue;
			}
			if ( $strict ) {
				if ( $item[ $key ] === $needle ) {
					return true;
				}
			} else {
				if ( $item[ $key ] == $needle ) {
					return true;
				}
			}
		}
		return false;
	}
}
