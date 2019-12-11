<?php
/**
 * IMPORTANT: Copy of this file ( standalone_preloader.php ) included in _rollback_undo.php.
 *
 * @package BackupBuddy
 */

$pb_styles  = array();
$pb_scripts = array();
$pb_actions = array();
$wp_scripts = array();

/**
 * NOTE: Modified from WP to rtrim on dirname() due to Windows issues.
 *
 * @return string  Site URL.
 */
function site_url() {
	$page_url = 'http';
	if ( isset( $_SERVER['HTTPS'] ) && ( $_SERVER['HTTPS'] == 'on' ) ) {
		$page_url .= 's';}
	$page_url .= '://';
	if ( $_SERVER['SERVER_PORT'] != '80' ) {
		$page_url .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . rtrim( dirname( $_SERVER['PHP_SELF'] ), '/\\' );
	} else {
		$page_url .= $_SERVER['SERVER_NAME'] . rtrim( dirname( $_SERVER['PHP_SELF'] ), '/\\' );
	}

	return $page_url;
}

/**
 * Navigates through an array and removes slashes from the values.
 *
 * If an array is passed, the array_map() function causes a callback to pass the
 * value back to the function. The slashes from this value will removed.
 *
 * @since 2.0.0
 *
 * @param array|string $value The array or string to be stripped.
 * @return array|string Stripped array (or string in the callback).
 */
function stripslashes_deep( $value ) {
	if ( is_array( $value ) ) {
		$value = array_map( 'stripslashes_deep', $value );
	} elseif ( is_object( $value ) ) {
		$vars = get_object_vars( $value );
		foreach ( $vars as $key => $data ) {
			$value->{$key} = stripslashes_deep( $data );
		}
	} else {
		$value = stripslashes( $value );
	}

	return $value;
}


/**
 * Check value to find if it was serialized.
 *
 * If $data is not an string, then returned value will always be false.
 * Serialized data is always a string.
 * Courtesy WordPress; since WordPress 2.0.5.
 *
 * @param mixed $data Value to check to see if was serialized.
 * @return bool False if not serialized and true if it was.
 */
function is_serialized( $data ) {
	// If it isn't a string, it isn't serialized.
	if ( ! is_string( $data ) ) {
		return false;
	}
	$data = trim( $data );
	if ( 'N;' == $data ) {
		return true;
	}
	$length = strlen( $data );
	if ( $length < 4 ) {
		return false;
	}
	if ( ':' !== $data[1] ) {
		return false;
	}
	$lastc = $data[ $length - 1 ];
	if ( ';' !== $lastc && '}' !== $lastc ) {
		return false;
	}
	$token = $data[0];
	switch ( $token ) {
		case 's':
			if ( '"' !== $data[ $length - 2 ] ) {
				return false;
			}
			// no break.
		case 'a':
		case 'O':
			return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
		case 'b':
		case 'i':
		case 'd':
			return (bool) preg_match( "/^{$token}:[0-9.E-]+;\$/", $data );
	}
	return false;
} // End is_serialized().

/**
 * Translation Function.
 *
 * @param string $text    Text to translate.
 * @param string $domain  Text domain.
 *
 * @return string  Translated Text.
 */
function __( $text, $domain = '' ) { // @codingStandardsIgnoreLine: Replacement function declaration.
	return $text;
}

/**
 * Escapes html from text.
 *
 * @param string $text    Text to escape.
 * @param string $domain  Text domain.
 *
 * @return string  Escaped translated Text.
 */
function esc_html__( $text, $domain = '' ) { // @codingStandardsIgnoreLine: Replacement function declaration.
	return esc_html( __( $text, $domain ) ); // @codingStandardsIgnoreLine: Escape HTML, then relay __ function.
}

/**
 * Escapes html from variable. Poor mans version.
 *
 * @param string $text    Text to escape.
 *
 * @return string  Translated Text.
 */
function esc_html( $text ) { // @codingStandardsIgnoreLine: Replacement function declaration.
	return htmlentities( $text ); // @codingStandardsIgnoreLine: Escape HTML, then relay __ function.
}

/**
 * Echo translated text.
 *
 * @param string $text    Text to translate.
 * @param string $domain  Text domain.
 */
function _e( $text, $domain = '' ) { // @codingStandardsIgnoreLine: Replacement function declaration.
	echo __( $text, $domain ); // @codingStandardsIgnoreLine: Relay __ function.
}

/**
 * Escapes html and echo translated text.
 *
 * @param string $text    Text to translate.
 * @param string $domain  Text domain.
 */
function esc_html_e( $text, $domain = '' ) { // @codingStandardsIgnoreLine: Replacement function declaration.
	echo esc_html( $text, $domain ); // @codingStandardsIgnoreLine: Relay esc_html__ function.
}

/**
 * Escapes HTML Attribute
 *
 * @param string $text  Text to escape.
 *
 * @return string  Escaped text.
 */
function esc_attr( $text ) {
	return $text;
}

/**
 * Escapes HTML Attribute Text String
 *
 * @param string $text    Text to escape.
 * @param string $domain  Text domain.
 */
function esc_attr_e( $text, $domain ) { // @codingStandardsIgnoreLine: Replacement function declaration.
	echo esc_attr( $text );
}

/**
 * Check if style exists.
 *
 * @param string $name  Style tag.
 *
 * @return bool  If style tag exists.
 */
function wp_style_is( $name ) {
	global $pb_styles;
	return array_key_exists( $name, $pb_styles );
}

/**
 * Poor man's version of WP Enqueue Style.
 *
 * @param string $name  Style tag name.
 * @param string $file  Path to file.
 * @param array  $deps  Dependencies.
 * @param string $ver   Version.
 */
function wp_enqueue_style( $name, $file, $deps = array(), $ver = '' ) {
	global $pb_styles;
	$pb_styles[ $name ]['file']    = $file;
	$pb_styles[ $name ]['version'] = $ver;
	$pb_styles[ $name ]['printed'] = false;
}
/**
 * Poor man's print styles function.
 *
 * @param string $name  Tag name.
 */
function wp_print_styles( $name ) {
	global $pb_styles;
	if ( false === $pb_styles[ $name ]['printed'] ) {
		$pb_styles[ $name ]['printed'] = true;

		echo '<link rel="stylesheet" type="text/css" href="' . $pb_styles[ $name ]['file'] . '?ver=' . md5( $pb_styles[ $name ]['version'] ) . '">';
	}
}

/**
 * Checks if script exists.
 *
 * @param string $name  Script tag name.
 *
 * @return bool  If script exists.
 */
function wp_script_is( $name ) {
	global $pb_scripts;
	return array_key_exists( $name, $pb_scripts );
}

/**
 * Poor man's version of WP Enqueue Script.
 *
 * @param string $name  Script tag name.
 * @param string $file  Path to script.
 * @param array  $deps  Dependencies.
 * @param string $ver   Version.
 */
function wp_enqueue_script( $name, $file, $deps = array(), $ver = '' ) {
	global $pb_scripts;
	$pb_scripts[ $name ]['file']    = $file;
	$pb_scripts[ $name ]['version'] = $ver;
	$pb_scripts[ $name ]['printed'] = false;
}
/**
 * Poor man's WP Print Scripts
 *
 * @param string $name  Script tag name.
 */
function wp_print_scripts( $name ) {
	global $pb_scripts;
	if ( $pb_scripts[ $name ]['printed'] === false ) {
		$pb_scripts[ $name ]['printed'] = true;

		echo '<script src="' . $pb_scripts[ $name ]['file'] . '?ver=' . md5( $pb_scripts[ $name ]['version'] ) . '" type="text/javascript"></script>';
	}
}

/**
 * Poor man's add_action.
 *
 * @param string                $tag       Action tag.
 * @param string|array|function $callback  Callback function name or array.
 */
function add_action( $tag, $callback ) {
	global $pb_actions;
	$pb_actions[ $tag ]['callback'] = $callback;
}

/**
 * Check if is admin.
 *
 * @return bool  Always returns true.
 */
function is_admin() {
	return true;
}

/**
 * Poor man's apply_filters.
 *
 * @param string $filter  Filter name.
 * @param mixed  $value   Value to filter.
 *
 * @return mixed  Filtered $value.
 */
function apply_filters( $filter, $value ) {
	return $value;
}

/**
 * Poor man's sanitize title for ImportBuddy
 *
 * @param string $title  Title string to sanitize.
 *
 * @return string  Lowercased, hyphenated title.
 */
function sanitize_title( $title ) {
	$title = strip_tags( $title );
	// Preserve escaped octets.
	$title = preg_replace( '|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title );
	// Remove percent signs that are not part of an octet.
	$title = str_replace( '%', '', $title );
	// Restore octets.
	$title = preg_replace( '|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title );

	$title = strtolower( $title );

	$title = preg_replace( '/&.+?;/', '', $title );
	$title = str_replace( '.', '-', $title );

	$title = preg_replace( '/[^%a-z0-9 _-]/', '', $title );
	$title = preg_replace( '/\s+/', '-', $title );
	$title = preg_replace( '|-+|', '-', $title );
	$title = trim( $title, '-' );

	return apply_filters( 'sanitize_title', $title );
}

/**
 * Cleanup Header Comment.
 *
 * @param string $str  Header comment.
 *
 * @return string  Cleaned up header comment.
 */
function _cleanup_header_comment( $str ) {
	return trim( preg_replace( '/\s*(?:\*\/|\?>).*/', '', $str ) );
}

/**
 * Get Plugin Data from plugin header.
 *
 * @param string $plugin_file  Path to plugin file.
 * @param bool   $markup       Include markup.
 * @param bool   $translate    Translate output.
 *
 * @return array  Plugin Data array.
 */
function get_plugin_data( $plugin_file, $markup = true, $translate = true ) {

	$default_headers = array(
		'Name'        => 'Plugin Name',
		'PluginURI'   => 'Plugin URI',
		'Version'     => 'Version',
		'Description' => 'Description',
		'Author'      => 'Author',
		'AuthorURI'   => 'Author URI',
		'TextDomain'  => 'Text Domain',
		'DomainPath'  => 'Domain Path',
		'Network'     => 'Network',
		// Site Wide Only is deprecated in favor of Network.
		'_sitewide'   => 'Site Wide Only',
	);

	$plugin_data = get_file_data( $plugin_file, $default_headers, 'plugin' );

	// Site Wide Only is the old header for Network.
	if ( empty( $plugin_data['Network'] ) && ! empty( $plugin_data['_sitewide'] ) ) {
		_deprecated_argument( __FUNCTION__, '3.0', sprintf( __( 'The <code>%1$s</code> plugin header is deprecated. Use <code>%2$s</code> instead.' ), 'Site Wide Only: true', 'Network: true' ) );
		$plugin_data['Network'] = $plugin_data['_sitewide'];
	}
	$plugin_data['Network'] = ( 'true' == strtolower( $plugin_data['Network'] ) );
	unset( $plugin_data['_sitewide'] );

	// For backward compatibility by default Title is the same as Name.
	$plugin_data['Title'] = $plugin_data['Name'];

	if ( $markup || $translate ) {
		$plugin_data = _get_plugin_data_markup_translate( $plugin_file, $plugin_data, $markup, $translate );
	} else {
		$plugin_data['AuthorName'] = $plugin_data['Author'];
	}

	return $plugin_data;
}

/**
 * Get file Data.
 *
 * @param string $file             Path to file.
 * @param array  $default_headers  Array of Headers.
 * @param string $context          Context.
 *
 * @return array  File data array.
 */
function get_file_data( $file, $default_headers, $context = '' ) {
	// We don't need to write to the file, so just open for reading.
	$fp = fopen( $file, 'r' );

	// Pull only the first 8kiB of the file in.
	$file_data = fread( $fp, 8192 );

	// PHP will close file handle, but we are good citizens.
	fclose( $fp );

	if ( '' != $context ) {
		$extra_headers = apply_filters( "extra_{$context}_headers", array() );

		$extra_headers = array_flip( $extra_headers );
		foreach ( $extra_headers as $key => $value ) {
			$extra_headers[ $key ] = $key;
		}
		$all_headers = array_merge( $extra_headers, (array) $default_headers );
	} else {
		$all_headers = $default_headers;
	}

	foreach ( $all_headers as $field => $regex ) {
		preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $file_data, ${$field} );
		if ( ! empty( ${$field} ) ) {
			${ $field } = _cleanup_header_comment( ${ $field }[1] );
		} else {
			${ $field } = '';
		}
	}

	$file_data = compact( array_keys( $all_headers ) );

	return $file_data;
}

/**
 * Placeholder function for wp_nonce_field().
 *
 * @return null
 */
function wp_nonce_field() {
	return;
}

if ( ! function_exists( 'ngettext' ) ) {
	/**
	 * Some PHP installs don't have ngettext. Needed by human_time_diff().
	 *
	 * @param string $singular  Singular version.
	 * @param string $plural    Plural version.
	 * @param int    $num       Number to check for plural.
	 *
	 * @return string  Singular or plural version.
	 */
	function ngettext( $singular, $plural, $num ) {
		if ( $num > 1 ) {
			return $plural;
		}
		return $singular;
	}
} // End ngettext().

/**
 * Determines the difference between two timestamps.
 *
 * The difference is returned in a human readable format such as "1 hour",
 * "5 mins", "2 days".
 *
 * @since 1.5.0
 *
 * @param int $from Unix timestamp from which the difference begins.
 * @param int $to Optional. Unix timestamp to end the time difference. Default becomes time() if not set.
 * @return string Human readable time difference.
 */
function human_time_diff( $from, $to = '' ) {
	if ( empty( $to ) ) {
		$to = time();
	}
	$diff = (int) abs( $to - $from );
	if ( $diff <= 3600 ) {
		$mins = round( $diff / 60 );
		if ( $mins <= 1 ) {
			$mins = 1;
		}
		/* translators: min=minute */
		$since = sprintf( ngettext( '%s min', '%s mins', $mins ), $mins );
	} elseif ( ( $diff <= 86400 ) && ( $diff > 3600 ) ) {
		$hours = round( $diff / 3600 );
		if ( $hours <= 1 ) {
			$hours = 1;
		}
		$since = sprintf( ngettext( '%s hour', '%s hours', $hours ), $hours );
	} elseif ( $diff >= 86400 ) {
		$days = round( $diff / 86400 );
		if ( $days <= 1 ) {
			$days = 1;
		}
		$since = sprintf( ngettext( '%s day', '%s days', $days ), $days );
	}
	return $since;
}


/**
 * Unserialize value only if it was serialized.
 *
 * @since 2.0.0
 *
 * @param string $original Maybe unserialized original, if is needed.
 * @return mixed Unserialized data can be any type.
 */
function maybe_unserialize( $original ) {
	if ( is_serialized( $original ) ) { // don't attempt to unserialize data that wasn't serialized going in
		return @unserialize( $original );
	}
	return $original;
}



// NOT IMPLEMENTED BUT NON-BLOCKING.
/**
 * Not implemented but non-blocking
 */
function register_activation_hook() {
}
/**
 * Not implemented but non-blocking
 */
function load_plugin_textdomain() {
}
/**
 * Not implemented but non-blocking
 *
 * @param string $capability  Permission.
 *
 * @return bool  Always returns true.
 */
function current_user_can( $capability ) {
	return true;
}

/**
 * Get temp directory.
 *
 * @return string  Temp directory path.
 */
function get_temp_dir() {

	if ( function_exists( 'sys_get_temp_dir' ) ) {
		$temp = sys_get_temp_dir();
		if ( @is_dir( $temp ) && is_writable( $temp ) ) {
			return rtrim( $temp, '/\\' ) . '/';
		}
	}

	$temp = ABSPATH . 'temp/';
	@mkdir( $temp );
	if ( is_dir( $temp ) && is_writable( $temp ) ) {
		return $temp;
	}

	$temp = '/tmp/';
	@mkdir( $temp );
	return $temp;
}

/**
 * Poor man's wp_upload_dir
 *
 * @return array  Array of upload dir info.
 */
function wp_upload_dir() {
	return array( 'basedir' => ABSPATH );
}

/**
 * Poor man's wp_die function.
 *
 * @param string $message  Message to display.
 */
function wp_die( $message ) {
	pb_backupbuddy::status( 'error', 'wp_die() called with message: ' . $message );
	die( $message );
}

/**
 * Not implemented but non-blocking
 */
function wp_load_translations_early() {
}

/**
 * Not implemented but non-blocking
 */
function wp_debug_backtrace_summary() {
}

if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}
if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
	define( 'WP_DEBUG_DISPLAY', true );
}
if ( ! defined( 'WP_DEBUG_LOG' ) ) {
	define( 'WP_DEBUG_LOG', false );
}
if ( ! defined( 'WP_CACHE' ) ) {
	define( 'WP_CACHE', false );
}

/**
 * Not implemented, adds ? before param.
 *
 * @param string $val  URL to return.
 *
 * @return string  $val, prefixed with ?
 */
function admin_url( $val ) {
	return '?' . $val;
}


/**
 * WordPress Error class.
 *
 * Container for checking for WordPress errors and error messages. Return
 * WP_Error and use {@link is_wp_error()} to check if this class is returned.
 * Many core WordPress functions pass this class in the event of an error and
 * if not handled properly will result in code errors.
 *
 * @package WordPress
 * @since 2.1.0
 */
class WP_Error {
	/**
	 * Stores the list of errors.
	 *
	 * @since 2.1.0
	 * @var array
	 */
	public $errors = array();

	/**
	 * Stores the list of data for error codes.
	 *
	 * @since 2.1.0
	 * @var array
	 */
	public $error_data = array();

	/**
	 * Initialize the error.
	 *
	 * If `$code` is empty, the other parameters will be ignored.
	 * When `$code` is not empty, `$message` will be used even if
	 * it is empty. The `$data` parameter will be used only if it
	 * is not empty.
	 *
	 * Though the class is constructed with a single error code and
	 * message, multiple codes can be added using the `add()` method.
	 *
	 * @since 2.1.0
	 *
	 * @param string|int $code     Error code.
	 * @param string     $message  Error message.
	 * @param mixed      $data     Optional. Error data.
	 */
	public function __construct( $code = '', $message = '', $data = '' ) {
		if ( empty( $code ) ) {
			return;
		}

		$this->errors[ $code ][] = $message;

		if ( ! empty( $data ) ) {
			$this->error_data[ $code ] = $data;
		}
	}

	/**
	 * Retrieve all error codes.
	 *
	 * @since 2.1.0
	 * @access public
	 *
	 * @return array List of error codes, if available.
	 */
	public function get_error_codes() {
		if ( empty( $this->errors ) ) {
			return array();
		}

		return array_keys( $this->errors );
	}

	/**
	 * Retrieve first error code available.
	 *
	 * @since 2.1.0
	 * @access public
	 *
	 * @return string|int Empty string, if no error codes.
	 */
	public function get_error_code() {
		$codes = $this->get_error_codes();

		if ( empty( $codes ) ) {
			return '';
		}

		return $codes[0];
	}

	/**
	 * Retrieve all error messages or error messages matching code.
	 *
	 * @since 2.1.0
	 *
	 * @param string|int $code Optional. Retrieve messages matching code, if exists.
	 * @return array Error strings on success, or empty array on failure (if using code parameter).
	 */
	public function get_error_messages( $code = '' ) {
		// Return all messages if no code specified.
		if ( empty( $code ) ) {
			$all_messages = array();
			foreach ( (array) $this->errors as $code => $messages ) {
				$all_messages = array_merge( $all_messages, $messages );
			}

			return $all_messages;
		}

		if ( isset( $this->errors[ $code ] ) ) {
			return $this->errors[ $code ];
		} else {
			return array();
		}
	}

	/**
	 * Get single error message.
	 *
	 * This will get the first message available for the code. If no code is
	 * given then the first code available will be used.
	 *
	 * @since 2.1.0
	 *
	 * @param string|int $code Optional. Error code to retrieve message.
	 * @return string
	 */
	public function get_error_message( $code = '' ) {
		if ( empty( $code ) ) {
			$code = $this->get_error_code();
		}
		$messages = $this->get_error_messages( $code );
		if ( empty( $messages ) ) {
			return '';
		}
		return $messages[0];
	}

	/**
	 * Retrieve error data for error code.
	 *
	 * @since 2.1.0
	 *
	 * @param string|int $code Optional. Error code.
	 * @return mixed Error data, if it exists.
	 */
	public function get_error_data( $code = '' ) {
		if ( empty( $code ) ) {
			$code = $this->get_error_code();
		}

		if ( isset( $this->error_data[ $code ] ) ) {
			return $this->error_data[ $code ];
		}
	}

	/**
	 * Add an error or append additional message to an existing error.
	 *
	 * @since 2.1.0
	 * @access public
	 *
	 * @param string|int $code Error code.
	 * @param string     $message Error message.
	 * @param mixed      $data Optional. Error data.
	 */
	public function add( $code, $message, $data = '' ) {
		$this->errors[ $code ][] = $message;
		if ( ! empty( $data ) ) {
			$this->error_data[ $code ] = $data;
		}
	}

	/**
	 * Add data for error code.
	 *
	 * The error code can only contain one error data.
	 *
	 * @since 2.1.0
	 *
	 * @param mixed      $data Error data.
	 * @param string|int $code Error code.
	 */
	public function add_data( $data, $code = '' ) {
		if ( empty( $code ) ) {
			$code = $this->get_error_code();
		}

		$this->error_data[ $code ] = $data;
	}

	/**
	 * Removes the specified error.
	 *
	 * This function removes all error messages associated with the specified
	 * error code, along with any error data for that code.
	 *
	 * @since 4.1.0
	 *
	 * @param string|int $code Error code.
	 */
	public function remove( $code ) {
		unset( $this->errors[ $code ] );
		unset( $this->error_data[ $code ] );
	}
}

/**
 * Check whether variable is a WordPress Error.
 *
 * Returns true if $thing is an object of the WP_Error class.
 *
 * @since 2.1.0
 *
 * @param mixed $thing Check if unknown variable is a WP_Error object.
 * @return bool True, if WP_Error. False, if not WP_Error.
 */
function is_wp_error( $thing ) {
	return ( $thing instanceof WP_Error );
}

/**
 * Set the mbstring internal encoding to a binary safe encoding when func_overload
 * is enabled.
 *
 * When mbstring.func_overload is in use for multi-byte encodings, the results from
 * strlen() and similar functions respect the utf8 characters, causing binary data
 * to return incorrect lengths.
 *
 * This function overrides the mbstring encoding to a binary-safe encoding, and
 * resets it to the users expected encoding afterwards through the
 * `reset_mbstring_encoding` function.
 *
 * It is safe to recursively call this function, however each
 * `mbstring_binary_safe_encoding()` call must be followed up with an equal number
 * of `reset_mbstring_encoding()` calls.
 *
 * @since 3.7.0
 *
 * @see reset_mbstring_encoding()
 *
 * @staticvar array $encodings
 * @staticvar bool  $overloaded
 *
 * @param bool $reset Optional. Whether to reset the encoding back to a previously-set encoding.
 *                    Default false.
 */
function mbstring_binary_safe_encoding( $reset = false ) {
	static $encodings  = array();
	static $overloaded = null;

	if ( is_null( $overloaded ) ) {
		$overloaded = function_exists( 'mb_internal_encoding' ) && ( ini_get( 'mbstring.func_overload' ) & 2 );
	}

	if ( false === $overloaded ) {
		return;
	}

	if ( ! $reset ) {
		$encoding = mb_internal_encoding();
		array_push( $encodings, $encoding );
		mb_internal_encoding( 'ISO-8859-1' );
	}

	if ( $reset && $encodings ) {
		$encoding = array_pop( $encodings );
		mb_internal_encoding( $encoding );
	}
}

/**
 * Reset the mbstring internal encoding to a users previously set encoding.
 *
 * @see mbstring_binary_safe_encoding()
 *
 * @since 3.7.0
 */
function reset_mbstring_encoding() {
	mbstring_binary_safe_encoding( true );
}

/**
 * Checks if Template Redirect action has happened.
 *
 * @param string $action  Checks if action has been used.
 *
 * @return bool|null  True if $action is 'template_redirect'
 */
function did_action( $action ) {
	if ( 'template_redirect' == $action ) {
		return true;
	}
}

/**
 * Placeholder function for WP Doing it Wrong function. Not implemented.
 *
 * @param string $function  Function name.
 * @param string $message   Message to display.
 * @param string $version   Version to note.
 */
function _doing_it_wrong( $function, $message, $version ) { // @codingStandardsIgnoreLine: Placeholder function.
}

/**
 * Placeholder function for WP has_filter.
 *
 * @param string                $name      Name of filter.
 * @param string|array|function $callback  Callback function name, array or function.
 *
 * @return bool  Always returns true.
 */
function has_filter( $name, $callback ) {
	return true;
}
