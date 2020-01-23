<?php
/**
 * This is a poor man's implementation of WP's hooks.
 *
 * It doesn't allow you to add multiple hooks to the action. Only one.
 * Not officially supported yet.
 */

/**
 * Much like WP Actions. Passes to filter. Doesn't return data.
 *
 * @param  string  $action  Slug. Int this implementation - it wil eventually be appended to a function name
 * @param  array   $params  Optional data to work with
 */
function it_bub_importbuddy_do_action( $action, $params = '' ) {
	it_bub_importbuddy_apply_filters( $action, $params );
}

/**
 * Much like WP Filters.
 *
 * Our implementation doesn't actually support adding filters. They're currently all hard coded.
 *
 * @param  string  $action  Slug. Int this implementation - it wil eventually be appended to a function name
 * @param  array   $params  Optional data to work with
 *
 * @return mixed
 */
function it_bub_importbuddy_apply_filters( $action, $params = array() ) {
	// If dropin doesn't exist, abort
	if ( ! it_bub_importbuddy_load_dropin() ) {
		return $params;
	}

	// If action exists, do it. If not, abort
	if ( is_callable( 'it_bub_importbuddy_hook_' . $action ) ) {
		return call_user_func( 'it_bub_importbuddy_hook_' . $action, $params );
	}

	return $params;
}

/**
 * Loads the dropin file if it exists one directory up from root of import
 *
 * @return null
 */
function it_bub_importbuddy_load_dropin() {
	// Possible locations.
	$locations = array(
		dirname( ABSPATH ),
		dirname( dirname( ABSPATH ) ),
	);

	foreach ( $locations as $location ) {
		if ( ! @is_readable( $location . '/it-bub-importbuddy-hooks.php' ) ) {
			continue;
		} else {
			require_once $location . '/it-bub-importbuddy-hooks.php';
			return true;
		}
	}
	return false;
}
