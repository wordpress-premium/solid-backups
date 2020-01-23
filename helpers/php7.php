<?php
/**
 * PHP 7 functions for older versions of PHP.
 *
 * @package BackupBuddy
 */

if ( ! function_exists( 'array_key_first' ) ) {
	/**
	 * PHP 7 function replacement to get first array key.
	 *
	 * @param array $arr  Array.
	 *
	 * @return string|int  First array key.
	 */
	function array_key_first( array $arr ) {
		foreach ( $arr as $key => $unused ) {
			return $key;
		}
		return null;
	}
}
if ( ! function_exists( 'array_key_last' ) ) {
	/**
	 * PHP 7 function replacement to get last array key.
	 *
	 * @param array $arr  Array.
	 *
	 * @return string|int  Last array key.
	 */
	function array_key_last( array $arr ) {
		if ( ! is_array( $arr ) || empty( $arr ) ) {
			return null;
		}

		$keys = array_keys( $arr );
		return $keys[ count( $arr ) - 1 ];
	}
}
