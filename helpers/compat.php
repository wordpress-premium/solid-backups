<?php
/**
 * Compatability Functions
 *
 * @package BackupBuddy
 */

/** 4.1 */

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Fall back on standard json_encode() when wp_json_encode is not available.
	 *
	 * @param mixed $value  Resource to be encoded.
	 *
	 * @return string  JSON encoded string.
	 */
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

/** 2.9 */

if ( ! function_exists( 'is_multisite' ) ) {
	/**
	 * Sites running < 3.0.0 do not have multisite.
	 *
	 * @return bool  false
	 */
	function is_multisite() {
		return false;
	}
}

if ( ! function_exists( 'is_network_admin' ) ) {
	/**
	 * Returns false when WP function doesn't exist.
	 *
	 * @return bool  False.
	 */
	function is_network_admin() {
		return false;
	}
}

/** 2.8 */

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * Gets Home URL value.
	 *
	 * @return string  Home URL.
	 */
	function home_url() {
		return get_option( 'home' );
	}
}

/** 2.7 */

if ( ! function_exists( 'add_site_option' ) ) {
	/**
	 * Multisite Replacement function
	 *
	 * @param string $key    Option key.
	 * @param mixed  $value  Option value.
	 */
	function add_site_option( $key, $value ) {
		return add_option( $key, $value );
	}
}

if ( ! function_exists( 'get_site_option' ) ) {
	/**
	 * Get site option replacement function.
	 *
	 * @param string $key        Option key.
	 * @param bool   $default    Default value.
	 * @param bool   $use_cache  Use cache.
	 *
	 * @return mixed  Value of option.
	 */
	function get_site_option( $key, $default = false, $use_cache = true ) {
		return get_option( $key, $default );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Escapes URL
	 *
	 * @param string $url        URL to escape.
	 * @param array  $protocols  Array of protocols.
	 *
	 * @return string  Escaped URL.
	 */
	function esc_url_raw( $url, $protocols = null ) {
		return clean_url( $url, $protocols, 'db' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Escape URL
	 *
	 * @param string $url        URL to escape.
	 * @param array  $protocols  Array of protocols.
	 *
	 * @return string  Escaped URL.
	 */
	function esc_url( $url, $protocols = null ) {
		return clean_url( $url, $protocols, 'display' );
	}
}

if ( ! function_exists( 'wp_script_is' ) ) {
	/**
	 * Checks if Script is declared.
	 *
	 * @param string $handle  Script handle.
	 * @param string $list    Which list to check.
	 *
	 * @return bool|object  Script object or true.
	 */
	function wp_script_is( $handle, $list = 'queue' ) {
		global $wp_scripts;
		if ( ! is_a( $wp_scripts, 'WP_Scripts' ) ) {
			$wp_scripts = new WP_Scripts();
		}

		$query = $wp_scripts->query( $handle, $list );

		if ( is_object( $query ) ) {
			return true;
		}

		return $query;
	}
}

if ( ! function_exists( 'wp_style_is' ) ) {
	/**
	 * Checks if Style is declared.
	 *
	 * @param string $handle  Style handle.
	 * @param string $list    Which list to check.
	 *
	 * @return bool|object  Style object or true.
	 */
	function wp_style_is( $handle, $list = 'queue' ) {
		global $wp_styles;
		if ( ! is_a( $wp_styles, 'WP_Scripts' ) ) {
			$wp_styles = new WP_Styles();
		}

		$query = $wp_styles->query( $handle, $list );

		if ( is_object( $query ) ) {
			return true;
		}

		return $query;
	}
}

if ( ! function_exists( 'fetch_feed' ) ) {
	/**
	 * Fetch Feed (displays error)
	 *
	 * @param string $url  Feed URL.
	 *
	 * @return object  WP Error object.
	 */
	function fetch_feed( $url ) {
		return new WP_Error( 'unsupported', 'This version of WordPress does not support this function.' );
	}
}


/** 2.6 */

if ( ! function_exists( 'wp_remote_post' ) ) {
	/**
	 * Remote Post Function
	 *
	 * @param string $url   URL to call.
	 * @param array  $args  Array of args.
	 *
	 * @return object  WP Error object.
	 */
	function wp_remote_post( $url, $args = array() ) {
		return new WP_Error( 'unsupported', 'This version of WordPress does not support this function.' );
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	/**
	 * Remote Get Function
	 *
	 * @param string $url   URL to call.
	 * @param array  $args  Array of args.
	 *
	 * @return object  WP Error object.
	 */
	function wp_remote_get( $url, $args = array() ) {
		return new WP_Error( 'unsupported', 'This version of WordPress does not support this function.' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * Retrieve remote response code.
	 *
	 * @param array $response  Response array.
	 *
	 * @return string  Returns empty string.
	 */
	function wp_remote_retrieve_response_code( &$response ) {
		return '';
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * [wp_remote_retrieve_body description]
	 *
	 * @param array $response  Response array.
	 *
	 * @return string  Returns empty string.
	 */
	function wp_remote_retrieve_body( &$response ) {
		return '';
	}
}
