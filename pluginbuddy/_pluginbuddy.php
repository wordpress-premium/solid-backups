<?php
/**
 * Framework for handling all plugin functioality, architecture, etc.
 * $settings variable is expected to be in the same scope of this file and previously populated with all plugin settings.
 *
 * @author Dustin Bolton
 * @package BackupBuddy
 * @subpackage PluginBuddy
 */

/**
 * PluginBuddy Class
 */
class pb_backupbuddy {

	/**
	 * PluginBuddy Framework Version
	 *
	 * @var string
	 */
	private static $pbframework_version = '1.0.28';

	/**
	 * Microtime when init() was first run.
	 *
	 * @var float
	 */
	public static $start_time;

	/**
	 * Stores all options for plugin that will change such as user defined settings.
	 *
	 * @var array
	 */
	public static $options;

	/**
	 * User interface class for rapidly constructing WP-styled GUIs.
	 *
	 * REMOVED. Now added at runtime when init_class_controller() called
	 *
	 * @var object
	 */
	public static $ui;

	/**
	 * Class for manipulating & interfacing file system.
	 *
	 * @var object
	 */
	public static $filesystem;

	/**
	 * Class for formatting data or text in human friendly forms.
	 *
	 * @var object
	 */
	public static $format;

	/**
	 * Array holder for user-defined classes needed globally by plugin. Set/get with $class['class_slug'].
	 *
	 * @var array
	 */
	public static $classes = array();

	/**
	 * Array holder for user-defined variables needed globally by plugin.
	 * Set/get with $variables['var_name']. Useful for things such as an instance counter that increments.
	 *
	 * @var array
	 */
	public static $variables = array();


	/**
	 * Default framework settings for this plugin.
	 * NOT the same as options.
	 *
	 * Access via self::settings().
	 *
	 * @var array
	 */
	private static $_settings = array(
		'slug'            => '',
		'series'          => '',
		'default_options' => '',
		'log_serial'      => '',
		'init'            => '',
	);

	/**
	 * Holds admin page settings for adding to the admin menu on a hook later.
	 *
	 * @var array
	 */
	private static $_page_settings;

	/**
	 * Serial for writing the status for this page load.
	 *
	 * @var string
	 */
	public static $_status_serial = '';

	/**
	 * Whether or not flush() has been called yet or not.
	 *
	 * @var bool
	 */
	private static $_has_flushed = false;

	/**
	 * Controller objects. See: /controllers/ directory.
	 */

	/**
	 * Controller for WordPress actions.
	 *
	 * @var object
	 */
	private static $_actions;

	/**
	 * Controller for WordPress AJAX actions.
	 *
	 * @var object
	 */
	public static $_ajax;

	/**
	 * Controller for WordPress scheduled crons.
	 *
	 * @var object
	 */
	private static $_cron;

	/**
	 * Controller for WordPress admin dashboard items.
	 *
	 * @var object
	 */
	private static $_dashboard;

	/**
	 * Controller for WordPress filters.
	 *
	 * @var object
	 */
	private static $_filters;

	/**
	 * Controller for WordPress shortcodes.
	 *
	 * @var object
	 */
	private static $_shortcodes;

	/**
	 * Controller for WordPress widgets.
	 */
	// private static $_widgets;

	/**
	 * Controller for WordPress pages. See /controllers/pages/ directory.
	 *
	 * @var object
	 */
	private static $_pages;

	/**
	 * Local path to plugin.
	 * Ex: /users/pb/www/wp-content/plugins/my_plugin (no trailing slash)
	 *
	 * @see pluginbuddy:plugin_path()
	 *
	 * @var string
	 */
	private static $_plugin_path;

	/**
	 * URL to plugin directory.
	 * Ex: http://pluginbuddy.com/wp-content/plugins/my_plugin/ (with trailing slash)
	 *
	 * @see self::plugin_url()
	 *
	 * @var string
	 */
	private static $_plugin_url;

	/**
	 * Returns URL to the current admin page if on a plugin page.
	 * Ex: http://pluginbuddy.com/wp-admin/index.php?page=pb_myplugin
	 *
	 * @see self::page_link()
	 *
	 * @var string
	 */
	private static $_self_link;

	/**
	 * DISABLED. Using create_function() to bypass need for this. Currently only holding callback for the admin menu.
	 * Note: Try not to use create_function().
	 *
	 * @see pluginbuddy_callbacks class
	 *
	 * @var array
	 */
	// private static $_callbacks;

	/**
	 * Holds tag and title for unconstructed dashboard widgets temporarily.
	 *
	 * @var array
	 */
	public static $_dashboard_widgets;

	/**
	 * Contains updater object (if enabled) of the most up to date updater found. Populated on init hook.
	 *
	 * @var object
	 */
	public static $_updater;

	/**
	 * If unable to write to log then skip all future attempts.
	 *
	 * @var bool
	 */
	private static $_skiplog;

	/**
	 * Constructor for this static class.
	 *
	 * Called from the plugin's init (or other defined in pb_backupbuddy::settings( 'init' )) file.
	 *
	 * @param array  $pluginbuddy_settings  Array of plugin settings such as slug, default options.
	 * @param string $pluginbuddy_init      Init file.
	 */
	public static function init( $pluginbuddy_settings, $pluginbuddy_init = 'init.php' ) {
		self::$start_time = microtime( true );
		self::$_settings  = array_merge( (array) self::$_settings, (array) $pluginbuddy_settings ); // Merge settings over framework defaults.

		if ( function_exists( 'plugin_dir_url' ) ) { // URL and path functions available (not in ImportBuddy but inside WordPress).
			self::$_plugin_path = rtrim( plugin_dir_path( dirname( __FILE__ ) ), '/\\' );
			self::$_plugin_url  = rtrim( plugin_dir_url( dirname( __FILE__ ) ), '/\\' );
		} else { // Generate URL and paths old way (old WordPress versions or inside ImportBuddy).
			self::$_plugin_path = dirname( dirname( __FILE__ ) );
			$relative_path      = ltrim( str_replace( '\\', '/', str_replace( rtrim( ABSPATH, '\\\/' ), '', self::$_plugin_path ) ), '\\\/' );
			if ( pb_is_standalone() ) {
				self::$_plugin_url = 'importbuddy'; // Relative importbuddy path.
			} else { // Normal full path.
				self::$_plugin_url = site_url() . '/' . ltrim( $relative_path, '/' );
				if ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ) { // Handle https URLs properly.
					self::$_plugin_url = str_replace( 'http://', 'https://', self::$_plugin_url );
				}
			}
		}

		if ( isset( $_GET['page'] ) ) { // If in an admin page then append page querystring.
			$arr              = explode( '?', $_SERVER['REQUEST_URI'] ); // avoid reference error by setting here.
			self::$_self_link = array_shift( $arr ) . '?page=' . htmlentities( $_GET['page'] );
			unset( $arr );
		}

		// Set the init file.
		self::$_settings['init'] = $pluginbuddy_init;

		// filesystem class controller.
		if ( isset( self::$_settings['modules']['filesystem'] ) && true === self::$_settings['modules']['filesystem'] ) {
			self::init_class_controller( 'filesystem' );
		}
		// format class controller.
		if ( isset( self::$_settings['modules']['format'] ) && true === self::$_settings['modules']['format'] ) {
			self::init_class_controller( 'format' );
		}

		if ( is_admin() ) {

			// Load UI system.
			self::init_class_controller( 'ui' );

			// Load activation hook if in admin and activation file exists.
			if ( file_exists( self::$_plugin_path . '/controllers/activation.php' ) ) {
				register_activation_hook( self::$_plugin_path . '/' . pb_backupbuddy::settings( 'init' ), array( 'pb_backupbuddy', 'load_activation_controller' ) ); // Run some code when plugin is activated in dashboard.
			}
		}

	} // End init().

	/**
	 * Loads Activation Controller
	 */
	public static function load_activation_controller() {
		// Replace a path starting with \\ to be \\\\ so that when create_function parses the backslash it will return back to \\.
		$escaped_plugin_path = preg_replace( '#^\\\\\\\\#', '\\\\\\\\\\\\\\\\', self::$_plugin_path );
		require_once $escaped_plugin_path . '/controllers/activation.php';
	}

	/**
	 * Returns local path to plugin.Ex: /users/pb/www/wp-content/plugins/my_plugin (no trailing slash)
	 *
	 * @return string Plugin path directory (no trailing slash).
	 */
	public static function plugin_path() {
		return self::$_plugin_path;
	} // End plugin_path().

	/**
	 * Returns URL to plugin directory.
	 * Ex: http://pluginbuddy.com/wp-content/plugins/my_plugin/ (with trailing slash)
	 *
	 * @return string  Plugin path URL (with trailing slash).
	 */
	public static function plugin_url() {
		return self::$_plugin_url;
	} // End plugin_url().

	/**
	 * Returns URL to the current admin page if on a plugin page.
	 * Ex: http://pluginbuddy.com/wp-admin/index.php?page=pb_myplugin
	 *
	 * @return string  Plugin page URL (with trailing slash).
	 */
	public static function page_url() {
		return self::$_self_link;
	} // End page_url().

	/**
	 * Returns the admin-side AJAX URL. Properly handles prefixing and everything for PB framework.
	 *
	 * @todo provide non-admin-side functionality?
	 *
	 * @param string $tag  Tag / slug of AJAX.
	 *
	 * @return string  URL for AJAX.
	 */
	public static function ajax_url( $tag ) {
		return admin_url( 'admin-ajax.php?action=pb_' . self::settings( 'slug' ) . '_backupbuddy&function=' . $tag );
	} // End ajax_url().

	/**
	 * Retrieves misc plugin settings both passed from the init file ( defined in pb_backupbuddy::settings( 'init' ) ) into self::$_settings
	 *
	 * Also plugin settings defined in the init file ( defined in pb_backupbuddy::settings( 'init' ) ) header including:
	 * name, title, description, author, authoruri, version, pluginuri OR url, textdomain, domainpath, network.
	 *
	 * @see self::init()
	 *
	 * @param string $type  Type of setting to retrieve.
	 *
	 * @return mixed Value associated with that settings. Null if not found.
	 */
	public static function settings( $type ) {
		if ( isset( self::$_settings[ $type ] ) ) {
			return self::$_settings[ $type ];
		}

		if ( pb_is_standalone() ) {
			if ( 'version' == $type ) {
				return PB_BB_VERSION;
			}
		}

		// The variable does not exist so check to see if it can be extracted from the plugin's header.
		if ( ! isset( self::$_settings['name'] ) || '' == self::$_settings['name'] ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$info        = array_change_key_case( get_plugin_data( self::$_plugin_path . '/' . pb_backupbuddy::settings( 'init' ), false, false ), CASE_LOWER );
			$info['url'] = $info['pluginuri'];
			unset( $info['pluginuri'] );
			self::$_settings = array_merge( self::$_settings, $info );
		}

		// Try to return setting otherwise throw an error.
		if ( isset( self::$_settings[ $type ] ) ) {
			return self::$_settings[ $type ];
		} else {
			return '{Unknown settings() variable `' . $type . '`}';
		}
	} // End settings().

	/**
	 * Returns whether a not a variable is blank (empty string, null, undefined) or not.
	 * Be sure to suppress errors if using this function where indexes may be non-existant with @ sign.
	 *
	 * @param mixed $value  Variable to determine if it is blank or not.
	 *
	 * @return boolean  True if variable is set and is not an empty string.
	 */
	public static function blank( $value ) {
		return empty( $value ) && ! is_numeric( $value );
	} // End blank().

	/**
	 * Returns $_POST value if available, else returns a blank. Prevents having to check isset first. Strips WP's added slashes.
	 *
	 * @param string $value  Key of POST variable to check.
	 *
	 * @return mixed  Value of POST variable if set. If not set returns a blank string ''.
	 */
	public static function _POST( $value = null ) {
		if ( '' == $value || null == $value ) { // Requesting $_POST variable.
			if ( pb_is_standalone() && ! get_magic_quotes_gpc() ) { // If in ImportBuddy mode AND magic quotes is not on, dont strip. WP escapes for us if magic quotes are off.
				return $_POST;
			}
			return stripslashes_deep( $_POST );
		} else {
			$post_value = '';
			if ( isset( $_POST[ $value ] ) ) {
				$post_value = $_POST[ $value ];
			}
			if ( pb_is_standalone() && ! get_magic_quotes_gpc() ) { // If in ImportBuddy mode AND magic quotes is not on, dont strip. WP escapes for us if magic quotes are off.
				return $post_value;
			} else {
				return stripslashes_deep( $post_value ); // Remove WordPress' magic-quotes-style escaping of data.
			}
		}
	} // End _POST().

	/**
	 * Returns $_GET value if available, else returns a blank. Prevents having to check isset first.
	 *
	 * @todo Do we need to stripslashes_deep() on GET vars also like POSTs?
	 *
	 * @param string $value  Key of POST variable to check.
	 *
	 * @return mixed  Value of POST variable if set. If not set returns a blank string ''.
	 */
	public static function _GET( $value = '' ) {
		if ( '' == $value || null == $value ) { // Requesting $_GET variable.
			if ( pb_is_standalone() && ! get_magic_quotes_gpc() ) { // If in ImportBuddy mode AND magic quotes is not on, dont strip. WP escapes for us if magic quotes are off.
				return $_GET;
			}
			return stripslashes_deep( $_GET );
		} else {
			$get_value = '';
			if ( isset( $_GET[ $value ] ) ) {
				$get_value = $_GET[ $value ];
			}
			if ( pb_is_standalone() && ! get_magic_quotes_gpc() ) { // If in ImportBuddy mode AND magic quotes is not on, dont strip. WP escapes for us if magic quotes are off.
				return $get_value;
			} else {
				return stripslashes_deep( $get_value ); // Remove WordPress' magic-quotes-style escaping of data.
			}
		}
	} // End _GET().

	/**
	 * Grabs & returns a reference to a specified point in the options array.
	 * Ex usage: $group = &self::get_group( 'groups#' . $_GET['edit'] );
	 *
	 * @param string $savepoint_root  Path in the array to return a reference to.
	 *                                Ex: groups#5 will grab self::$options['groups'][5].
	 *
	 * @return mixed  Value within the array at the specified point.
	 *                Can be used as a reference. See example in description.
	 *                NOTE: Returns false if not found.
	 */
	public static function &get_group( $savepoint_root ) {
		if ( '' == $savepoint_root ) { // Root was requested.
			$return = &self::$options;
			return $return;
		}

		$savepoint_subsection = &self::$options;
		$savepoint_levels     = explode( '#', $savepoint_root );
		foreach ( $savepoint_levels as $savepoint_level ) {
			if ( isset( $savepoint_subsection{$savepoint_level} ) ) {
				$savepoint_subsection = &$savepoint_subsection{$savepoint_level};
			} else {
				echo '{Error #4489045: Invalid array in path: `' . $savepoint_root . '`}';
				return false;
			}
		}

		return $savepoint_subsection;
	} // End get_group().

	/**
	 * Load from Backup
	 *
	 * @todo i18n.
	 *
	 * @return mixed  $settings or false.
	 */
	public static function load_from_backup() {
		$restore_fail_message    = 'Error #84938943: Your BackupBuddy Settings were detected as missing or corrupt. BackupBuddy has attempted to load BackupBuddy settings from its settings backup file but failed. Verify your BackupBuddy settings are still intact and valid. This could have been caused by a database error or corruption.';
		$restore_success_message = 'Warning #894384: Your BackupBuddy Settings were detected as missing or corrupt. BackupBuddy has restored your previous BackupBuddy settings from its settings backup file. Please verify your restored BackupBuddy settings look okay. This could have been caused by a database error or corruption.';

		require_once pb_backupbuddy::plugin_path() . '/classes/core.php';
		$backup_dir       = backupbuddy_core::getLogDirectory();
		$existing_backups = glob( $backup_dir . 'settings_backup-*.php' );
		if ( ! is_array( $existing_backups ) || count( $existing_backups ) < 1 ) { // No backups so just load defaults. May be a new install.
			return false;
		}

		// FIXME: Avoid @ symbol here. Possibly use try/catch?
		$settings = @file_get_contents( $existing_backups[0] );
		if ( false === $settings ) {
			backupbuddy_core::mail_error( $restore_fail_message . ' Details: Unable to open/read backup file that was found.' );
			return false;
		}

		// Skip first line.
		$second_line_pos = strpos( $settings, "\n" ) + 1;
		$settings        = substr( $settings, $second_line_pos );

		// Decode back into an array.
		$settings = unserialize( base64_decode( $settings ) );

		if ( is_array( $settings ) && ( isset( $settings['data_version'] ) ) ) { // Good restore.
			return $settings;
		} else { // Restore failed. Bad data!
			error_log( 'BackupBuddy settings failed restore.' );
			return false;
		}

	} // End load_from_backup().

	/**
	 * Loads the plugin options array containing all user-configurable options, etc.
	 * Access options via self::$options. Bypasses WP options caching for reliability.
	 *
	 * @param bool $retry_db  Retry DB.
	 *
	 * @return null
	 */
	public static function load( $retry_db = true ) {
		if ( pb_is_standalone() ) { // Standalone framework mode (outside WordPress).
			// Load options from file if it exists.
			$dat_file = ABSPATH . 'importbuddy/_settings_dat.php';
			if ( file_exists( $dat_file ) ) {
				$options = file_get_contents( $dat_file );

				// Skip first line.
				$second_line_pos = strpos( $options, "\n" ) + 1;
				$options         = substr( $options, $second_line_pos );

				// Decode back into an array.
				$options = json_decode( base64_decode( $options ), true );
			} else { // No existing options. Empty options.
				$options = array();
			}

			// Merge defaults.
			$options = array_merge( (array) self::settings( 'default_options' ), $options );

			pb_backupbuddy::$options = $options;
		} else { // Normal BB in WordPress.
			self::$options = self::_get_option( 'pb_' . self::settings( 'slug' ) );
		}

		// Merge defaults into temporary $options variable and save if it differs with the pre-merge options. Only retries this once.
		if ( ( empty( self::$options ) || ! isset( self::$options['data_version'] ) ) && true === $retry_db ) { // If empty options or corrupt.
			global $wpdb;
			// If the database goes away in the middle of a query, wait 5 seconds and try again. Otherwise, we unintentionally overwrite the settings.
			if ( ! empty( $wpdb->last_error ) && false !== strpos( $wpdb->last_error, "SELECT option_value FROM `$wpdb->options` WHERE option_name = 'pb_backupbuddy'" ) ) {
				sleep( 5 );
				self::load( false );
				return;
			} else { // Missing or corrupt options when loading. Either a new install or settings went missing.

				// Check for a settings backup and try to load it if were not in a standalone script.
				if ( ! pb_is_standalone() ) {
					$restored_settings = self::load_from_backup();
					if ( false !== $restored_settings ) {
						$options = $restored_settings;
					} else { // Load defaults.
						$options = (array) self::settings( 'default_options' );
					}
				}
			}
		} else { // Normal merge.
			$defaults = (array) self::settings( 'default_options' );

			// Apply defaults.
			$options = array_merge( $defaults, (array) self::$options );

			// Apply default profiles.
			$options['profiles'] = (array) self::$options['profiles'] + $defaults['profiles']; // Merge arrays on numeric indices. Left side is preserved (opposite of array_merge()).
		}
		if ( self::$options !== $options ) {
			self::$options = $options;
			self::save();
		}
	} // End load().

	/**
	 * Bypasses WordPress options cache. Unfortunately there appears to be race condition issues with the built-in WP options system.
	 * Used by load() function internally. Taken and modified from WordPress core.
	 *
	 * @see load()
	 *
	 * @param string $option   Option name.
	 * @param mixed  $default  default = false; we do not use this.
	 *
	 * @return mixed Saved option value.
	 */
	private static function _get_option( $option, $default = false ) {
		global $wpdb;

		$option = trim( $option );
		if ( empty( $option ) ) {
			return false;
		}

		if ( defined( 'WP_SETUP_CONFIG' ) ) {
			return false;
		}

		// FIXME: possibly use get_site_option() here?
		$base_prefix = $wpdb->base_prefix;
		$row         = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM {$base_prefix}options WHERE option_name = %s LIMIT 1", $option ) );
		// Has to be get_row instead of get_var because of funkiness with 0, false, null values.
		if ( is_object( $row ) ) {
			$value = $row->option_value;
		} else {
			$value = $default;
		}

		// If home is not set use siteurl.
		if ( 'home' == $option && '' == $value ) {
			return get_option( 'siteurl' );
		}

		if ( in_array( $option, array( 'siteurl', 'home', 'category_base', 'tag_base' ) ) ) {
			$value = untrailingslashit( $value );
		}

		$value = maybe_unserialize( $value );

		return $value;
	} // End get_option().

	/**
	 * Save plugin options to database.
	 *
	 * @return bool  True if save succeeded, false otherwise.
	 */
	public static function save() {
		if ( pb_is_standalone() ) {
			$options_content = base64_encode( json_encode( pb_backupbuddy::$options ) );
			$result          = file_put_contents( ABSPATH . 'importbuddy/_settings_dat.php', "<?php die('<!-- // Silence is golden. -->'); ?>\n" . $options_content );
			// FIXME: Could we just return $result?
			if ( false === $result ) {
				return false;
			} else {
				return true;
			}
		}

		add_site_option( 'pb_' . self::settings( 'slug' ), self::$options, '', 'no' ); // 'No' prevents autoload if we wont always need the data loaded.
		return self::_update_option( 'pb_' . self::settings( 'slug' ), self::$options );
	} // End save().

	/**
	 * Bypasses WordPress built in update option cache. Taken from WordPress core and modified.
	 *
	 * @see self::_get_option()
	 * @see self::save()
	 *
	 * @param string $option    Option name.
	 * @param mixed  $newvalue  New value to save into option.
	 *
	 * @return bool  True on success; false otherwise.
	 */
	private static function _update_option( $option, $newvalue ) {
		global $wpdb;

		$option = trim( $option );
		if ( empty( $option ) ) {
			return false;
		}

		$oldvalue = get_option( $option );
		if ( false === $oldvalue ) {
			return add_option( $option, $newvalue );
		} else {
			$newvalue = sanitize_option( $option, $newvalue );
			$newvalue = maybe_serialize( $newvalue );
			$result   = $wpdb->update(
				$wpdb->options, array(
					'option_value' => $newvalue,
				), array(
					'option_name' => $option,
				)
			);

			if ( $result ) {
				return true;
			}
		}

		return false;
	} // End _update_option().

	/**
	 * Helps security by attempting to block directory browsing by creating
	 * both index.htm files and .htaccess files turning browsing off.
	 *
	 * @param string $directory       Full absolute pass to insert anti-directory-browsing files into. No trailing slash.
	 * @param bool   $die_on_fail     When true also enforce denying ALL web-based access to directory. Default false.
	 * @param bool   $deny_all        Deny all.
	 * @param bool   $suppress_alert  Suppress alert.
	 *
	 * @return bool  True on success securing directory, false otherwise.
	 */
	public static function anti_directory_browsing( $directory = '', $die_on_fail = true, $deny_all = false, $suppress_alert = false ) {

		// Check directory exists & create if it doesn't.
		if ( ! file_exists( $directory ) ) {
			$mode    = apply_filters( 'itbub-default-file-mode', 0755 );
			$recurse = true;
			if ( self::$filesystem->mkdir( $directory, $mode, $recurse ) === false ) {
				$error = 'Error #9002: BackupBuddy unable to create directory `' . $directory . '`. Please verify write permissions for the parent directory `' . dirname( $directory ) . '` or manually create the specified directory & set permissions.';
				if ( true !== $suppress_alert ) {
					self::alert( $error, true, '9002' );
					pb_backupbuddy::status( 'error', $error );
				}
				if ( true === $die_on_fail ) {
					die( $error );
				}
				return false;
			}
		}

		// Check writable.
		if ( ! is_writable( $directory ) ) {
			$error = 'Error #9002d: BackupBuddy directory `' . $directory . '` is indicated as NOT being writable. Please verify write permissions for it and parent directories as applicable.';
			if ( true !== $suppress_alert ) {
				self::alert( $error, true, '9002' );
				pb_backupbuddy::status( 'error', $error );
			}
			if ( true === $die_on_fail ) {
				die( $error );
			}
			return false;
		}

		// .htaccess contents for denying.
		if ( true === $deny_all ) {
			$deny_all = "\ndeny from all";
		} else {
			$deny_all = '';
		}

		$error = '';

		if ( ! file_exists( $directory . '/index.php' ) ) {
			// FIXME: Avoid @ symbol here. Possibly use try/catch?
			if ( false === @file_put_contents( $directory . '/index.php', '<html></html>' ) ) {
				$error .= 'Unable to write index.php file. ';
			}
		}

		if ( ! file_exists( $directory . '/index.htm' ) ) {
			// FIXME: Avoid @ symbol here. Possibly use try/catch?
			if ( false === @file_put_contents( $directory . '/index.htm', '<html></html>' ) ) {
				$error .= 'Unable to write index.htm file. ';
			}
		}

		if ( ! file_exists( $directory . '/index.html' ) ) {
			// FIXME: Avoid @ symbol here. Possibly use try/catch?
			if ( false === @file_put_contents( $directory . '/index.html', '<html></html>' ) ) {
				$error .= 'Unable to write index.html file. ';
			}
		}

		// .htaccess if we aren't in the importbuddy script.
		if ( ! file_exists( $directory . '/.htaccess' ) ) {
			// FIXME: Avoid @ symbol here. Possibly use try/catch?
			if ( false === @file_put_contents( $directory . '/.htaccess', 'Options -Indexes' . $deny_all ) ) {
				$error .= 'Unable to write .htaccess file. ';
			}
		}

		if ( '' != $error ) { // Failure.
			if ( true !== $suppress_alert ) {
				$error = 'Error creating anti directory browsing security files in directory `' . $directory . '`. Please verify this directory\'s permissions allow writing & reading. Errors: `' . $error . '`.';
				self::alert( $error );
				pb_backupbuddy::status( 'error', $error );
			}
			if ( true === $die_on_fail ) {
				die( 'Script halted for security. Please verify permissions and try again.' );
			}
		} else { // Success.
			return true;
		}
	} // End anti_directory_browsing().

	/**
	 * Define a default serial for all subsequent status() calls.
	 *
	 * @param string $serial  Unique identifier to use as default serial.
	 *
	 * @return null
	 */
	public static function set_status_serial( $serial ) {
		self::$_status_serial = $serial;
		return;
	} // End set_status_serial().

	/**
	 * Add a serial for all subsequent status() calls to log to in addition to any currently logging serials.
	 *
	 * @param string $serial  Unique identifier to add to serials to log to.
	 *
	 * return null
	 */
	public static function add_status_serial( $serial ) {
		pb_backupbuddy::status( 'details', 'Adding status serial `' . $serial . '`.' );
		if ( is_array( self::$_status_serial ) ) {
			self::$_status_serial[] = $serial;
		} else {
			self::$_status_serial = array( self::$_status_serial, $serial );
		}
		return;
	} // End add_status_serial().

	/**
	 * Remove a serial for all subsequent status() calls to log to in addition to any currently logging serials.
	 *
	 * @param string $serial  Unique identifier to remove from serials to log to.
	 *
	 * @return null
	 */
	public static function remove_status_serial( $serial ) {
		if ( is_array( self::$_status_serial ) ) {
			foreach ( self::$_status_serial as $i => $this_serial ) {
				if ( $this_serial == $serial ) {
					unset( self::$_status_serial[ $i ] );
					return;
				}
			}
		} else { // should be a string.
			if ( self::$_status_serial == $serial ) {
				self::$_status_serial = '';
			}
		}

		pb_backupbuddy::status( 'details', 'Removed status serial `' . $serial . '`.' );

		return;
	} // End remove_status_serial().

	/**
	 * Get current serial status logs are going to.
	 *
	 * @return string $status_serial  Current serial set.
	 */
	public static function get_status_serial() {
		return self::$_status_serial;
	} // End get_status_serial().

	/**
	 * Logs data to a CSV file. Optional unique serial identifier.
	 * If a serial is passed then EVERYTHING will be logged to the specified serial file in addition to whatever (if anything) is logged to main status file.
	 * Always logs to main status file based on logging settings whether serial is passed or not.
	 *
	 * NOTE: When full logging is on AND a serial is passed, it will be written to a _sum_ text file instead of the main log file.
	 *
	 * @see self::get_status().
	 *
	 * @param string $type            Valid types: error, warning, details, message.
	 * @param string $message         Message to log.
	 * @param string $serials         Optional. Optional unique identifier for this plugin's message.
	 *                                Status messages are unique per plugin so this adds an additional unique layer for retrieval.
	 *                                If self::$_status_serial has been set by set_status_serial() then it will override if $serial is blank.
	 * @param bool   $js_mode         If JS mode.
	 * @param bool   $echo_not_write  Echo output instead of write to file.
	 */
	public static function status( $type, $message, $serials = '', $js_mode = false, $echo_not_write = false ) {

		if ( ! class_exists( 'backupbuddy_core' ) ) {
			require_once pb_backupbuddy::plugin_path() . '/classes/core.php';
		}

		if ( ! isset( self::$options['log_level'] ) ) { // If settings are corrupted default to no logging.
			self::$options['log_level'] = 0;
		}

		if ( '' != self::$_status_serial && '' == $serials ) {
			$serials = self::$_status_serial;
		}

		if ( defined( 'BACKUPBUDDY_WP_CLI' ) && true === BACKUPBUDDY_WP_CLI ) {
			if ( class_exists( 'WP_CLI' ) ) {
				WP_CLI::line( $type . ' - ' . $message );
			}
		}

		// Make sure we have a unique log serial for all logs for security.
		if ( ! isset( self::$options['log_serial'] ) || '' == self::$options['log_serial'] ) {
			self::$options['log_serial'] = self::random_string( 15 );
			self::save();
		}

		if ( ! is_array( $serials ) ) {
			$serials = array( $serials );
		}

		// Calculate log directory.
		$log_directory = backupbuddy_core::getLogDirectory(); // Also handles when within importbuddy.

		// Prepare directory for log files. Return if unable to do so.
		if ( true === self::$_skiplog ) { // bool true so skip.
			return;
		} elseif ( false !== self::$_skiplog ) { // something other than bool false so check directory before proceeding.
			$die_on_fail    = false;
			$deny_all       = false;
			$suppress_alert = true;
			if ( true !== self::anti_directory_browsing( $log_directory, $die_on_fail, $deny_all, $suppress_alert ) ) { // Unable to secure directory. Fail.
				self::$_skiplog = true;
				return;
			} else {
				self::$_skiplog = false;
			}
		}

		foreach ( $serials as $serial ) {

			// ImportBuddy always write to main status log.
			if ( defined( 'PB_IMPORTBUDDY' ) && ( PB_IMPORTBUDDY === true ) ) { // IMPORTBUDDY.

				$write_serial = false;
				$write_main   = true;
				$main_file    = $log_directory . 'status-' . self::$options['log_serial'] . '.txt';

			} else { // STANDALONE.

				// Determine whether writing to main extraneous log file.
				$write_main = false;
				if ( 0 == self::$options['log_level'] ) { // No logging.
						$write_main = false;
				} elseif ( 1 == self::$options['log_level'] ) { // Errors only.
					if ( 'error' === $type ) {
						$write_main = true;
						self::log( '[' . $serial . '] ' . $message, 'error' );
					}
				} else { // Everything else.
					$write_main = true;
					self::log( '[' . $serial . '] ' . $message, $type );
				}

				// Determine which normal status log files to write.
				if ( '' != $serial ) {
					$write_serial = true;
					$write_main   = false;
					if ( ( false === stristr( $serial, 'remote_send-' ) ) && ( false === stristr( $serial, 'live_periodic' ) ) ) { // Only write sum file if NOT a remote send and NOT Live log.
						$write_main = true;
						$main_file  = $log_directory . 'status-' . $serial . '_sum_' . self::$options['log_serial'] . '.txt';
					}
				} else {
					$write_serial = false;
					$write_main   = false;
				}
			}

			if ( ! function_exists( 'write_status_line' ) ) {
				/**
				 * Function for writing actual log CSV data. Used later.
				 *
				 * @param string $file           File to write.
				 * @param array  $content_array  Array of content to write.
				 * @param bool   $echo           Echo instead of write.
				 */
				function write_status_line( $file, $content_array, $echo ) {
					$write_data = json_encode( $content_array ) . PHP_EOL;
					if ( true === $echo ) { // Echo data instead of writing to file. Used by ajax when checking status log and needing to prepend before log.
						echo $write_data;
					} else {
						// FIXME: Avoid @ symbol here.
						$file_handle = @fopen( $file, 'a' );
						if ( false !== $file_handle ) { // Append mode.
							// FIXME: Avoid @ symbol here.
							@fwrite( $file_handle, $write_data );
							// FIXME: Avoid @ symbol here.
							@fclose( $file_handle );
						}
					}
				}
			}

			$content_array = array(
				'event' => $type,
				'time'  => pb_backupbuddy::$format->localize_time( time() ), // Time this happened.
				'u'     => substr( (string) microtime(), 2, 2 ),
				'run'   => sprintf( '%01.2f', round( microtime( true ) - self::$start_time, 2 ) ), // Elapsed PHP time.
				'mem'   => sprintf( '%01.2f', round( memory_get_usage() / 1048576, 2 ) ), // Memory used.	Pre-7.0 was: memory_get_peak_usage().
				'data'  => str_replace( chr( 9 ), '   ', $message ), // Body of the message.
			);

			/********** MAIN LOG FILE or SUM FILE */
			if ( true === $write_main ) { // WRITE TO MAIN LOG FILE or SUM FILE.
				write_status_line( $main_file, $content_array, $echo_not_write );
			}

			/********** SERIAL LOG FILE */
			if ( true === $write_serial ) {
				$serial_file = $log_directory . 'status-' . $serial . '_' . self::$options['log_serial'] . '.txt';
				write_status_line( $serial_file, $content_array, $echo_not_write );
			}

			// Output importbuddy status log to screen.
			global $pb_backupbuddy_js_status;
			if ( ( defined( 'PB_IMPORTBUDDY' ) || ( isset( $pb_backupbuddy_js_status ) && true === $pb_backupbuddy_js_status ) ) && ( 'true' != pb_backupbuddy::_GET( 'deploy' ) ) ) { // If importbuddy, js mode, and not a deployment.
				echo '<script>pb_status_append( ' . json_encode( $content_array ) . ' );</script>' . "\n";
				pb_backupbuddy::flush();
			}
		} // end foreach $serials.

	} // End status().

	/**
	 * Gets all status information logged via status(). Returns an array of arrays with logged data.
	 *
	 *  Return format:
	 *     array(
	 *         array( TIMESTAMP, TIME_IN, PEAK_MEMORY, TYPE, MESSAGE ),
	 *         array( TIMESTAMP, TYPE, MESSAGE ),
	 *     )
	 *
	 * @see self::status().
	 *
	 * @param string $serial              Unique identifier. Retrieves a subset of logged information based on this unique ID that was passed to status() when logging.
	 * @param bool   $clear_retrieved      Default: true. On true status information will be purged after retrieval.
	 * @param bool   $erase_retrieved      Default: true. Whether or not to delete log file on retrieval. NOTE: PCLZip can NOT lose files mid-backup so log files cannot delete mid-zip.
	 * @param bool   $hide_getting_status  Default: false. Whether or not to output status retrieval message.
	 * @param bool   $copy_retrieved       Array of arrays.  Each sub-array contains three values: timestamp, type of message, and the message itself. See function description for details. Empty array if non-existing log.
	 *
	 * @return array $status_lines  Or Null if status file isn't writeable.
	 */
	public static function get_status( $serial = '', $clear_retrieved = true, $erase_retrieved = true, $hide_getting_status = false, $copy_retrieved = false ) {
		// Calculate log directory.
		$log_directory = backupbuddy_core::getLogDirectory(); // Also handles when importbuddy.

		$status_file = $log_directory . 'status-';
		if ( '' != $serial ) {
			$status_file .= $serial . '_';
		}
		$status_file .= self::$options['log_serial'] . '.txt';

		if ( ! file_exists( $status_file ) ) {
			return array(); // No log.
		}

		if ( false === $hide_getting_status ) {
			self::status( 'details', 'Getting status for serial `' . $serial . '`. Clear: `' . ( $clear_retrieved ? 'true' : 'false' ) . '`', $serial );
		}

		// FIXME: Avoid @ symbol here.
		$fh = @fopen( $status_file, 'r' );
		if ( false !== $fh ) { // Read write mode.
			$status_lines = array();
			while ( false !== ( $status_line = fgets( $fh ) ) ) {
				$status_lines[] = $status_line;
			}
			fclose( $fh );

			if ( true === $clear_retrieved ) {
				file_put_contents( $status_file, '' );
			}

			if ( true === $erase_retrieved ) {
				// FIXME: Avoid @ symbol here. Try/Catch errors on this? suppress?
				@unlink( $status_file );
			}

			if ( false !== $copy_retrieved ) {
				@file_put_contents( $copy_retrieved, $status_lines, FILE_APPEND );
			}

			return $status_lines;
		} else {
			// TODO: Log an error here?
			// self::alert( 'Unable to open file handler for status file `' . $status_file . '`. Unable to write status log.' );
		}
	} // End get_status().

	/**
	 * Displays a textarea for placing status text into.
	 *
	 * @param string $default_text  First line of text to display.
	 * @param bool   $hidden          Whether or not to apply display: none; CSS.
	 *
	 * @return string  HTML for textarea.
	 */
	public static function status_box( $default_text = '', $hidden = false ) {
		define( 'PB_STATUS', true ); // Tells framework status() function to output future logging info into status box via javascript.
		$return = '<textarea readonly="readonly" id="pb_backupbuddy_status" wrap="off"';
		if ( true === $hidden ) {
			$return .= ' style="display: none; "';
		}
		$return .= '>' . $default_text . '</textarea>';

		return $return;
	} // End status_box().

	/**
	 * Sets greedy script limits to help prevent timeouts, running out of memory, etc.
	 *
	 * @param bool $supress_status  Do not set status message.
	 */
	public static function set_greedy_script_limits( $supress_status = false ) {

		$requested_socket_timeout = 60 * 60 * 2;
		$requested_execution_time = 60 * 60 * 2;

		// Don't abort script if the client connection is lost/closed
		@ignore_user_abort( true );

		// Set socket timeout to requested period.
		@ini_set( 'default_socket_timeout', $requested_socket_timeout );

		pb_backupbuddy::status( 'details', 'Checking max PHP execution time settings.' );
		// Set maximum execution time to requested period if not already better than that
		// See if we can get a current value (of any sort)
		$original_execution_time = @ini_get( 'max_execution_time' );
		if ( false === $original_execution_time ) {
			$original_execution_time = 'Unknown';
		}

		// Check if we need to try and set/increase.
		if ( is_numeric( $original_execution_time ) && ( ( 0 == $original_execution_time ) || ( $requested_execution_time <= $original_execution_time ) ) ) {
			// There is no need to change max_execution_time
			if ( false === $supress_status ) {
				if ( false === ( $configured_execution_time = @get_cfg_var( 'max_execution_time' ) ) ) {
					$configured_execution_time = 'Unknown';
				}
				$current_execution_time = @ini_get( 'max_execution_time' );
				if ( false === $current_execution_time ) {
					$current_execution_time = 'Unknown';
				}
				self::status( 'details', __( 'Maximum PHP execution time was not modified', 'it-l10n-backupbuddy' ) );
				self::status( 'details', sprintf( __( 'Reported PHP execution time - Configured: %1$s; Original: %2$s; Current: %3$s', 'it-l10n-backupbuddy' ), $configured_execution_time, $original_execution_time, $current_execution_time ) );
			}
		} else { // Either not a numeric value or we need to try and increase

			if ( isset( pb_backupbuddy::$options['set_greedy_execution_time'] ) && ( '1' == pb_backupbuddy::$options['set_greedy_execution_time'] ) ) {
				if ( false === $supress_status ) {
					self::status( 'details', sprintf( __( 'Attempting to set PHP execution time to %1$s', 'it-l10n-backupbuddy' ), $requested_execution_time ) );
				}
				@set_time_limit( $requested_execution_time );
			} elseif ( false === $supress_status ) {// end setting max execution time
				pb_backupbuddy::status( 'details', 'Skipped attempting to override max PHP execution time based on settings.' );
			}

			if ( false === $supress_status ) {
				$configured_execution_time = @get_cfg_var( 'max_execution_time' );
				if ( false === $configured_execution_time ) {
					$configured_execution_time = 'Unknown';
				}
				$current_execution_time = @ini_get( 'max_execution_time' );
				if ( false === $current_execution_time ) {
					$current_execution_time = 'Unknown';
				}
				self::status( 'details', sprintf( __( 'Reported PHP execution time - Configured: %1$s; Original: %2$s; Current: %3$s', 'it-l10n-backupbuddy' ), $configured_execution_time, $original_execution_time, $current_execution_time ) );
			}
		}

		// Set memory_limit to either the user defined (WordPress defaulted) or over-ridden value.
		// Need to get the original value here as we will be updating it.
		$original_memory_limit = @ini_get( 'memory_limit' );
		if ( false === $original_memory_limit ) {
			$original_memory_limit = 'Unknown';
		}

		// Need to check if we are running outside of WordPress in which case we don't try and change anything
		// but just report the memory_limit values. The user will have to update config if necessary because
		// there is no other mechanism to set the valid memory_limit.
		// If we are running under WordPress then need a little fakery for earlier versions.
		if ( ! pb_is_standalone() ) {
			// Note: WP_MAX_MEMORY_LIMIT was introduced WP3.2 so we need to fake it if constant not already defined
			// Use the default value that WordPress uses if the user hasn't defined it
			if ( ! defined( 'WP_MAX_MEMORY_LIMIT' ) ) {
				define( 'WP_MAX_MEMORY_LIMIT', '256M' );
			}
			@ini_set( 'memory_limit', apply_filters( 'admin_memory_limit', WP_MAX_MEMORY_LIMIT ) );
			if ( false === $supress_status ) {
				self::status( 'details', sprintf( __( 'Attempted to set PHP memory limit to user defined WP_MAX_MEMORY_LIMIT (%1$s) or over-ridden value', 'it-l10n-backupbuddy' ), WP_MAX_MEMORY_LIMIT ) );
			}
		}
		if ( false === $supress_status ) {
			$configured_memory_limit = @get_cfg_var( 'memory_limit' );
			if ( false === $configured_memory_limit ) {
				$configured_memory_limit = 'Unknown';
			}
			$current_memory_limit = @ini_get( 'memory_limit' );
			if ( false === $current_memory_limit ) {
				$current_memory_limit = 'Unknown';
			}
			self::status( 'details', sprintf( __( 'Reported PHP memory limits - Configured: %1$s; Original: %2$s; Current: %3$s', 'it-l10n-backupbuddy' ), $configured_memory_limit, $original_memory_limit, $current_memory_limit ) );
		}

	} // End set_greedy_script_limits().

	/**
	 * Logs to a text file depending on settings.
	 *
	 * 0 = none
	 * 1 = errors only
	 * 2 = errors + warnings
	 * 3 = debugging (all kinds of actions)
	 *
	 * @param string $text      Text to log.
	 * @param string $log_type  Valid options: error, warning, all (default so may be omitted).
	 *
	 * @return [type] [description]
	 */
	public static function log( $text, $log_type = 'all' ) {
		if ( defined( 'PB_DEMO_MODE' ) || ! isset( self::$options['log_level'] ) || 0 == self::$options['log_level'] ) { // No logging in this plugin or disabled.
			return;
		}

		$write = false;
		if ( 1 == self::$options['log_level'] ) { // Errors only.
			if ( 'error' === $log_type ) {
				$write = true;
			}
		} else { // All logging (debug mode).
			$write = true;
		}

		if ( true === $write ) {
			if ( ! isset( self::$options['log_serial'] ) ) {
				self::$options['log_serial'] = self::random_string( 15 );
				self::save();
			}
			$fh = @fopen( backupbuddy_core::getLogDirectory() . 'log-' . self::$options['log_serial'] . '.txt', 'a' );
			if ( $fh ) {
				if ( function_exists( 'get_option' ) ) {
					$gmt_offset = get_option( 'gmt_offset' );
				} else {
					$gmt_offset = 0;
				}
				fwrite( $fh, '[' . date( 'M j, Y H:i:s ' . $gmt_offset, time() + ( $gmt_offset * 3600 ) ) . '-' . $log_type . '] ' . $text . "\n" );
				fclose( $fh );
			}
		}
	} // End log().

	/**
	 * Generate a random string of characters.
	 *
	 * @param int    $length  Length of random string.
	 * @param string $chars   Characters to include in string.
	 *
	 * @return string $string  Random string.
	 */
	public static function random_string( $length = 32, $chars = 'abcdefghijkmnopqrstuvwxyz1234567890' ) {
		$chars_length = ( strlen( $chars ) - 1 );
		$string       = $chars{rand( 0, $chars_length )};
		for ( $i = 1; $i < $length; $i = strlen( $string ) ) {
			$r = $chars{rand( 0, $chars_length )};
			if ( $r != $string{$i - 1} ) {
				$string .= $r;
			}
		}
		return $string;
	} // End random_string().

	/**
	 * Displays a message to the user when they hover over the question mark. Gracefully falls back to normal tooltip.
	 * HTML is allowed within tooltips.
	 *
	 * @param string $video_key  YouTube video key from the URL ?v=VIDEO_KEY_HERE.
	 * @param string $title      (Optional) Title of message to show to user. This is displayed at top of tip in bigger letters. Default is blank.
	 * @param bool   $echo_tip   (Optional) Whether to echo the tip (default; true), or return the tip (false).
	 *
	 * @return mixed  If not echoing tip then the string will be returned. When echoing there is no return.
	 */
	public static function video( $video_key, $title = '', $echo_tip = true ) {
		self::init_class_controller( 'ui' ); // $ui class required pages controller and may not be set up if not in our own pages.
		return self::$ui->video( $video_key, $title, $echo_tip );
	} // End video().

	/**
	 * Enqueues the required scripts / styles needed to use thickbox
	 *
	 * @return null
	 */
	public static function enqueue_thickbox() {
		self::init_class_controller( 'ui' ); // $ui class required pages controller and may not be set up if not in our own pages.
		return self::$ui->enqueue_thickbox();
	} // End enqueue_thickbox

	/**
	 * Displays a message to the user at the top of the page when in the dashboard.
	 *
	 * @param string $message     Message you want to display to the user.
	 * @param bool   $error       OPTIONAL! true indicates this alert is an error and displays as red. Default: false.
	 * @param string $error_code  OPTIONAL! Error code number to use in linking in the wiki for easy reference.
	 * @param string $rel_tag     If not echoing alert then the string will be returned. When echoing there is no return.
	 *
	 * @return mixed  String or null.
	 */
	public static function alert( $message, $error = false, $error_code = '', $rel_tag = '' ) {
		self::init_class_controller( 'ui' ); // $ui class required pages controller and may not be set up if not in our own pages.
		self::$ui->alert( $message, $error, $error_code, $rel_tag );
	} // End alert().

	/**
	 * Dismissable alert system.
	 *
	 * @uses alert()
	 *
	 * @param string $unique_id  Unique ID for alert.
	 * @param string $message    Message you want to display to the user.
	 * @param bool   $error      Optional. Error code number to use in linking to the wiki for easy reference.
	 * @param string $more_css   Additional css to apply to alert.
	 */
	public static function disalert( $unique_id, $message, $error = false, $more_css = '' ) {
		self::init_class_controller( 'ui' ); // $ui class required pages controller and may not be set up if not in our own pages.
		self::$ui->disalert( $unique_id, $message, $error, $more_css );
	} // End disalert().

	/**
	 * Displays a message to the user when they hover over the question mark. Gracefully falls back to normal tooltip.
	 * HTML is allowed within tooltips.
	 *
	 * @param string $message   Actual message to show to user.
	 * @param string $title     Title of message to show to user. This is displayed at top of tip in bigger letters. (optional) Default is blank.
	 * @param bool   $echo_tip  (Optional) Whether to echo the tip (default; true), or return the tip (false).
	 *
	 * @return mixed  If not echoing tip then the string will be returned. When echoing there is no return.
	 */
	public static function tip( $message, $title = '', $echo_tip = true ) {
		self::init_class_controller( 'ui' ); // $ui class required pages controller and may not be set up if not in our own pages.
		return self::$ui->tip( $message, $title, $echo_tip );
	} // End tip().

	/**
	 * Adds a page into the admin. Stores menu items to add in self::$_page_settings array. Registers callback to register_admin_menu() with WordPress to actually set up the pages.
	 *
	 * @see self::register_admin_menu()
	 *
	 * @param string $parent_slug  Slug of the parent menu item to go under. If a series use `SERIES` for the value to automatically handle the series. PB prefix automatically applied unless $slug_prefix overrides.
	 * @param string $page_slug    Slug for this page. PB prefix automatically applied unless $slug_prefix overrides.
	 * @param string $page_title   Title of the page. If this menu item has no parent this can be an array of TWO titles. The root menu and the first submenu item that links to the same place.
	 * @param string $capability   Capability required to access page. Default: activate_plugins.
	 * @param string $icon         Menu icon graphic. Automatically prefixes this value with the full URL to plugin's images directory. Default: icon_16x16.png.
	 * @param string $slug_prefix  Prefix to use with this menu. Override if needing to add menu under another plugin or core menus. Default: DEFAULT.
	 * @param int    $position     Priority on where in the menu to add this. By default it is added to the bottom of the menu. It's possible to overwrite another menu item if this number matches. Use caution. Default: null.
	 */
	public static function add_page( $parent_slug, $page_slug, $page_title, $capability = 'activate_plugins', $icon = 'icon_menu_16x16.png', $slug_prefix = 'DEFAULT', $position = null ) {
		if ( 'DEFAULT' == $slug_prefix ) {
			$slug_prefix = 'pb_' . self::settings( 'slug' ) . '_';
		}

		if ( ! is_object( self::$_pages ) ) {
			self::_init_core_controller( 'pages' );

			if ( is_network_admin() ) { // Multisite installation admin; uses different hook.
				add_action( 'network_admin_menu', array( 'pb_backupbuddy', 'init_register_admin_menu' ) );
			} else { // Standalone admin.
				add_action( 'admin_menu', array( 'pb_backupbuddy', 'init_register_admin_menu' ) );
			}
		}

		self::$_page_settings[] = array(
			'parent'      => $parent_slug,
			'slug'        => $page_slug,
			'title'       => $page_title,
			'capability'  => $capability,
			'icon'        => $icon,
			'slug_prefix' => $slug_prefix,
			'position'    => $position,
		);
	} // End add_page().

	/**
	 * Call Class Admin Menu Registration
	 */
	public static function init_register_admin_menu() {
		$class = 'pb_' . self::settings( 'slug' );
		call_user_func( array( $class, 'register_admin_menu' ) );
	}

	/**
	 * Internal callback for actually registering the menu items into WordPress. Registers pages defined by self::add_page().
	 *
	 * @see self::add_page()
	 */
	public static function register_admin_menu() {
		if ( ! self::blank( self::$_settings['series'] ) ) { // SERIES.
			$series_slug = 'pb_' . self::$_settings['series'];
			// We need to see first if this series' root menu has been created by a plugin yet.
			global $menu;
			$found_series = false;
			foreach ( $menu as $menus => $item ) { // Loop through existing menu items looking for our series.
				if ( $item[0] == $series_slug ) {
					$found_series = true;
				}
			}
			if ( false === $found_series ) { // Series root menu does not exist; create it.
				add_menu_page( self::$_settings['series'] . ' Getting Started', self::$_settings['series'], 'activate_plugins', $series_slug, array( &self::$_pages, 'getting_started' ), self::plugin_url() . '/images/series_icon_16x16.png' ); // , $page['position']
				add_submenu_page( $series_slug, self::$_settings['series'] . ' Getting Started', 'Getting Started', 'activate_plugins', $series_slug, array( &self::$_pages, 'getting_started' ) );
			}

			// Register for getting started page.
			global $pluginbuddy_series;
			if ( ! isset( $pluginbuddy_series[ self::$_settings['series'] ] ) ) {
				$pluginbuddy_series[ self::$_settings['series'] ] = array();
			}

			// Add this plugin into global series variable.
			$pluginbuddy_series[ self::$_settings['series'] ][ self::$_settings['slug'] ] = array(
				'path' => self::plugin_path(),
				'name' => self::settings( 'name' ),
				'slug' => self::settings( 'slug' ),
			);
		}

		// Add all registered pages for this plugin.
		foreach ( self::$_page_settings as $page ) {
			$menu_slug = $page['slug_prefix'] . $page['slug'];
			if ( 'SERIES' === $page['parent'] ) { // Adding page into series.
				$parent_slug = 'pb_' . self::$_settings['series'];
				if ( self::blank( self::$_settings['series'] ) ) { // No series set but menu is registered into a series.
					echo '{WARNING: Menu item registered into a series but no plugin series is defined.}';
				}
			} else { // Non-series page.
				$parent_slug = $page['slug_prefix'] . $page['parent'];
			}

			if ( is_array( $page['title'] ) ) {
				$page_title     = $page['title'][0];
				$page_title_alt = $page['title'][1];
			} else { // Not an array so only one page title.
				$page_title     = $page['title'];
				$page_title_alt = $page['title'];
			}

			// Calculate icon.
			if ( '' != $page['icon'] ) { // If icon specified then figure out url.
				$icon = $page['icon'];
			} else { // No icon. Usually used when manually doing CSS for retina icon.
				$icon = '';
			}

			if ( self::blank( $page['parent'] ) ) { // Top-level menu.
				add_menu_page( $page_title, $page_title, $page['capability'], $menu_slug, array( &self::$_pages, $page['slug'] ), $icon, $page['position'] );
				add_submenu_page( $menu_slug, self::settings( 'name' ) . ' &lsaquo; ' . $page_title_alt, $page_title_alt, $page['capability'], $menu_slug, array( &self::$_pages, $page['slug'] ) ); // Allows naming of first submenu item differently from the parent. Else its auto created with same name.
			} else { // Sub-menu.
				add_submenu_page( $parent_slug, self::settings( 'name' ) . ' &lsaquo; ' . $page_title, $page_title, $page['capability'], $menu_slug, array( &self::$_pages, $page['slug'] ) );
			}
		}
	} // End register_admin_menu().

	/**
	 * Registers a WordPress action. Action of the name $tag will call the method in /controllers/actions.php with the matching name.
	 *
	 * @param string/array $tag            Tag / slug for the action. If an array the first item is the tag, the second is an optional custom callback method name.
	 * @param int          $priority       Integer priority number for the action.
	 * @param int          $accepted_args  Number of arguments this action may accept in its method.
	 */
	public static function add_action( $tag, $priority = 10, $accepted_args = 1 ) {
		if ( ! is_object( self::$_actions ) ) {
			self::_init_core_controller( 'actions' ); }
		if ( is_array( $tag ) ) { // If array then first param is tag, second param is custom callback method name.
			$callback_method = $tag[1];
			$tag             = $tag[0];
		} else { // No custom method name so tag and callback method name are the same.
			$callback_method = $tag;
			if ( strpos( $tag, '.' ) !== false ) {
				echo '{Warning: Your tag contains disallowed characters. Tag names are equal to the PHP method that is called back so they must conform to PHP method name standards. For custom callback method names use an array for the tag parameter in the form: array( \'tag\', \'callback_name\' ).}';
			}
		}
		add_action( $tag, array( &self::$_actions, $callback_method ), $priority, $accepted_args );
	} // End add_action().

	/**
	 * Deregisters a WordPress action. Action of the name $tag will call the method in /controllers/actions.php with the matching name.
	 *
	 * @param string/array $tag       Tag / slug for the action. If an array the first item is the tag, the second is an optional custom callback method name.
	 * @param int          $priority  Integer priority number for the action.
	 */
	public static function remove_action( $tag, $priority = 10 ) {
		if ( ! is_object( self::$_actions ) ) {
			self::_init_core_controller( 'actions' );
		}
		if ( is_array( $tag ) ) { // If array then first param is tag, second param is custom callback method name.
			$callback_method = $tag[1];
			$tag             = $tag[0];
		} else { // No custom method name so tag and callback method name are the same.
			$callback_method = $tag;
			if ( strpos( $tag, '.' ) !== false ) {
				echo '{Warning: Your tag contains disallowed characters. Tag names are equal to the PHP method that is called back so they must conform to PHP method name standards. For custom callback method names use an array for the tag parameter in the form: array( \'tag\', \'callback_name\' ).}';
			}
		}
		remove_action( $tag, array( &self::$_actions, $callback_method ), $priority );
	} // End add_action().

	/**
	 * Registers a WordPress ajax action. Ajax action of the name $tag will call the method in /controllers/ajax.php with the matching name.
	 *
	 * @param string/array $tag  Tag / slug for the action. If an array the first item is the tag, the second is an optional custom callback method name.
	 */
	public static function add_ajax( $tag ) {
		if ( ! is_object( self::$_ajax ) ) {
			self::_init_core_controller( 'ajax' ); }
		if ( is_array( $tag ) ) { // If array then first param is tag, second param is custom callback method name.
			$callback_method = $tag[1];
			$tag             = $tag[0];
		} else { // No custom method name so tag and callback method name are the same.
			$callback_method = $tag;
			if ( strpos( $tag, '.' ) !== false ) {
				echo '{Warning: Your tag contains disallowed characters. Tag names are equal to the PHP method that is called back so they must conform to PHP method name standards. For custom callback method names use an array for the tag parameter in the form: array( \'tag\', \'callback_name\' ).}';
			}
		}
		add_action( 'wp_ajax_pb_' . self::settings( 'slug' ) . '_' . $tag, array( &self::$_ajax, $callback_method ) );
	} // End add_ajax().

	/**
	 * Registers a WordPress cron callback (technically an action). Cron action of the name $tag will call the method in /controllers/cron.php with the matching name.
	 *
	 * @param string/array $tag                Tag / slug for the cron action. If an array the first item is the tag, the second is an optional custom callback method name.
	 * @param int          $priority           Integer priority number for the cron action.
	 * @param int          $accepted_args_num  Number of arguments this action may accept in its method.
	 */
	public static function add_cron( $tag, $priority = 10, $accepted_args_num = 1 ) {
		if ( ! is_object( self::$_cron ) ) {
			self::_init_core_controller( 'cron' ); }
		if ( is_array( $tag ) ) { // If array then first param is tag, second param is custom callback method name.
			$callback_method = $tag[1];
			$tag             = $tag[0];
		} else { // No custom method name so tag and callback method name are the same.
			$callback_method = $tag;
			if ( strpos( $tag, '.' ) !== false ) {
				echo '{Warning: Your tag contains disallowed characters. Tag names are equal to the PHP method that is called back so they must conform to PHP method name standards. For custom callback method names use an array for the tag parameter in the form: array( \'tag\', \'callback_name\' ).}';
			}
		}
		add_action( self::settings( 'slug' ) . '_' . $tag, array( &self::$_cron, $callback_method ), $priority, $accepted_args_num );
	} // End add_cron().

	/**
	 * Registers a WordPress action. Action of the name $tag will call the method in /controllers/dashboard.php with the matching name.
	 *
	 * @param string/array $tag         Tag / slug for the action.
	 * @param string       $title       Dashboard widget title.
	 * @param string       $capability  Required capability to display. Also accepts `godmode` to only allow superadmins in multisite and admins in standalone.
	 * @param bool         $force_top   Number of arguments this action may accept in its method.
	 */
	public static function add_dashboard_widget( $tag, $title, $capability, $force_top = false ) {
		if ( ! is_object( self::$_dashboard ) ) {
			self::$_dashboard_widgets = array(); // Init variable.

			self::_init_core_controller( 'dashboard' );

			if ( is_network_admin() ) { // Network admin.
				add_action( 'wp_network_dashboard_setup', array( &self::$_dashboard, 'register_widgets' ) );
			} else { // Normal admin.
				add_action( 'wp_dashboard_setup', array( &self::$_dashboard, 'register_widgets' ) );
			}
		}
		self::$_dashboard_widgets[] = array(
			'tag'        => $tag,
			'title'      => $title,
			'capability' => $capability,
			'force_top'  => $force_top,
		); // Push into array to be later registered via dashboard controller's register_widgets function.
	} // End add_dashboard_widget().

	/**
	 * Registers a WordPress filter. Filter of the name $tag will call the method in /controllers/filters.php with the matching name.
	 *
	 * @param string/array $tag            Tag / slug for the action. If an array the first item is the tag, the second is an optional custom callback method name.
	 * @param int          $priority       Integer priority number for the filter.
	 * @param int          $accepted_args  Number of arguments this filter may accept in its method.
	 */
	public static function add_filter( $tag, $priority = 10, $accepted_args = 1 ) {
		if ( ! is_object( self::$_filters ) ) {
			self::_init_core_controller( 'filters' ); }
		if ( is_array( $tag ) ) { // If array then first param is tag, second param is custom callback method name.
			$callback_method = $tag[1];
			$tag             = $tag[0];
		} else { // No custom method name so tag and callback method name are the same.
			$callback_method = $tag;
			if ( strpos( $tag, '.' ) !== false ) {
				echo '{Warning: Your tag contains disallowed characters. Tag names are equal to the PHP method that is called back so they must conform to PHP method name standards. For custom callback method names use an array for the tag parameter in the form: array( \'tag\', \'callback_name\' ).}';
			}
		}
		add_filter( $tag, array( &self::$_filters, $callback_method ), $priority, $accepted_args );
	} // End add_filter().

	/**
	 * Registers a WordPress shortcode. Shortcode of the name $tag will call the method in /controllers/shortcodes.php with the matching name.
	 *
	 * @param string/array $tag  Tag / slug for the shortcode. If an array the first item is the tag, the second is an optional custom callback method name.
	 */
	public static function add_shortcode( $tag ) {
		if ( ! is_object( self::$_shortcodes ) ) {
			self::_init_core_controller( 'shortcodes' ); }
		if ( is_array( $tag ) ) { // If array then first param is tag, second param is custom callback method name.
			$callback_method = $tag[1];
			$tag             = $tag[0];
		} else { // No custom method name so tag and callback method name are the same.
			$callback_method = $tag;
			if ( strpos( $tag, '.' ) !== false ) {
				echo '{Warning: Your tag contains disallowed characters. Tag names are equal to the PHP method that is called back so they must conform to PHP method name standards. For custom callback method names use an array for the tag parameter in the form: array( \'tag\', \'callback_name\' ).}';
			}
		}
		add_shortcode( $tag, array( &self::$_shortcodes, $callback_method ) );
	} // End add_shortcode().

	/**
	 * Registers the UI class into the pluginbuddy framework for pages. Registered on demand by pages controller.
	 *
	 * @see pages controller
	 *
	 * @param string $class_slug  Slug of class.
	 */
	public static function init_class_controller( $class_slug ) {
		if ( ! is_object( self::$$class_slug ) ) {
			$class_file = self::plugin_path() . '/pluginbuddy/classes/' . $class_slug . '.php';
			if ( file_exists( $class_file ) ) {
				require_once $class_file;
				$class_name        = 'pb_' . self::settings( 'slug' ) . '_' . $class_slug;
				self::$$class_slug = new $class_name();
			} else {
				echo '{Error: Missing class controller file `' . $class_file . '`.}';
			}
		}
	}

	/**
	 * Initialize a core controller class (ex: pages, ajax, filters, etc) for pluginbuddy framework usage.
	 *
	 * @param string $name  Name of the controller to register. Valid controllers: actions, ajax, cron, dashboard, filters, shortcodes, pages.
	 */
	private static function _init_core_controller( $name ) {
		if ( ! is_array( self::$options ) ) {
			self::load();
		} // Assume we need plugin options need loaded if controllers are loaded for this session.

		require_once self::$_plugin_path . '/controllers/' . $name . '.php';
		$classname                 = 'pb_backupbuddy_' . $name;
		$internal_classname        = '_' . $name;
		self::$$internal_classname = new $classname();
	} // End _init_core_controller().

	/**
	 * Echos or returns a WordPress nonce for the framework. Handles prefixing. Use with forms for security. Verifies the user came from a WP generated page.
	 *
	 * @param bool $echo True: echos the none; false: returns nonce.
	 *
	 * @return null/string  wp_nonce_field()
	 */
	public static function nonce( $echo = true ) {
		$name        = '_wpnonce';
		$nonce_field = wp_nonce_field( 'pb_' . self::settings( 'name' ) . '-nonce', $name, true, false );
		$nonce_id    = $name . '_' . uniqid();
		$nonce_field = str_replace( array( ' id="' . $name, ' id=\'' . $name ), array( ' id="' . $nonce_id, ' id=\'' . $nonce_id ), $nonce_field );
		if ( false === $echo ) {
			return $nonce_field;
		}
		echo $nonce_field;
	} // End nonce().

	/**
	 * WordPress nonce for URLs. Handles prefixing. Use with URLs for security. Verifies the user came from a WP generated page.
	 *
	 * @param string $bare_url  URL to nonce.
	 *
	 * @return string wp_nonce_url()
	 */
	public static function nonce_url( $bare_url ) {
		return wp_nonce_url( $bare_url, 'pb_' . self::settings( 'name' ) . '-nonce' );
	}

	/**
	 * Verifies the nonce submitted in form.
	 * Script die()'s on failure
	 */
	public static function verify_nonce() {
		check_admin_referer( 'pb_' . self::settings( 'name' ) . '-nonce' );
	} // End verify_nonce().

	/**
	 * Load a JavaScript file into the page. Handles prefixed, enqueuing, etc.
	 *
	 * @param string $script       If a .js file is included then a file in the js directory is loaded; else loads a built-in named library script.
	 *                             Ex: load_script( 'sort.js' ) will load /wp-content/plugins/my_plugin/js/sort.js;
	 *                             load_script( 'jquery' ) will load internal jquery library in WordPress if it exists.
	 * @param bool   $core_script  If true scripts are loaded from /pluginbuddy/js/SCRIPT.js. Else scripts loaded from plugin's js directory.
	 */
	public static function load_script( $script, $core_script = false ) {
		if ( strstr( $script, '.js' ) ) { // Loading a file specifically.
			if ( true === $core_script ) {
				if ( pb_is_standalone() ) {
					$url_path = 'importbuddy/pluginbuddy/js/';
				} else {
					$url_path = self::$_plugin_url . '/pluginbuddy/js/';
				}
				$local_path  = self::$_plugin_path . '/pluginbuddy/js/';
				$script_name = 'pb_' . self::settings( 'slug' ) . '_core_' . $script;
			} else {
				if ( pb_is_standalone() ) {
					$url_path = 'importbuddy/js/';
				} else {
					$url_path = self::$_plugin_url . '/js/';
				}
				$local_path  = self::$_plugin_path . '/js/';
				$script_name = 'pb_' . self::settings( 'slug' ) . '_' . $script;
			}

			if ( ! wp_script_is( $script_name ) ) { // Only load script once.
				if ( file_exists( $local_path . $script ) ) { // Load our local script if file exists.
					wp_enqueue_script( $script_name, $url_path . $script, array(), pb_backupbuddy::settings( 'version' ) );
					wp_print_scripts( $script_name );
				} else {
					echo '{Error: Javascript file was set to load that did not exist: `' . $url_path . $script . '`}';
				}
			}
		} else { // Not a specific file.
			if ( ! wp_script_is( $script, 'done' ) ) { // Only PRINT script once. Checks the done wpscript list to see if it's been printed yet or not.
				wp_enqueue_script( $script );
				wp_print_scripts( $script );
			}
		}
	} // End load_script().

	/**
	 * Load a CSS file into the page. Handles prefixed, enqueuing, etc.
	 *
	 * @param string $style       If a .css file is included then a file in the css directory is loaded; else loads a built-in named library style.
	 *                            Ex: load_style( 'sort.css' ) will load /wp-content/plugins/my_plugin/css/sort.css;
	 *                            load_style( 'dashboard' ) will load internal dashboard css in WordPress if it exists.
	 * @param bool   $core_style  If true styles are loaded from /pluginbuddy/css/STYLE.css. Else styles loaded from plugin's css directory.
	 */
	public static function load_style( $style, $core_style = false ) {
		if ( strstr( $style, '.css' ) ) { // Loading a file specifically.
			if ( true === $core_style ) {
				if ( pb_is_standalone() ) {
					$url_path = 'importbuddy/pluginbuddy/css/';
				} else {
					$url_path = self::$_plugin_url . '/pluginbuddy/css/';
				}
				$local_path = self::$_plugin_path . '/pluginbuddy/css/';
				$core_type  = 'core';
			} else {
				if ( pb_is_standalone() ) {
					$url_path = 'importbuddy/css/';
				} else {
					$url_path = self::$_plugin_url . '/css/';
				}
				$local_path = self::$_plugin_path . '/css/';
				$core_type  = 'noncore';
			}
			$style_name = 'pb_' . self::settings( 'slug' ) . '_' . $core_type . '_' . $style;
			if ( ! wp_style_is( $style_name ) ) { // Only load style once.
				if ( file_exists( $local_path . $style ) ) { // Load our local style if file exists.
					wp_enqueue_style( $style_name, $url_path . $style, array(), pb_backupbuddy::settings( 'version' ) );
					wp_print_styles( $style_name );
				} else {
					echo '{Error: CSS file was set to load that did not exist: `' . $url_path . $style . '`}';
				}
			}
		} else { // Not a specific file.
			if ( ! wp_style_is( $style ) ) { // Only load style once.
				wp_enqueue_style( $style );
				wp_print_styles( $style );
			}
		}
	} // End load_style().

	/**
	 * Loads a view. Typically called from within a controller. Data passed as second argument will has extract() ran on it within the view for easy variable access.
	 *
	 * @param string $view_name         Name of view. Corresponds to the view filename: /views/view_name.php.
	 * @param array  $pluginbuddy_data  Array of variables to be extracted for use by the view.
	 */
	public static function load_view( $view_name, $pluginbuddy_data = array() ) {
		// Variable named this way as the included file inherits this variable and we don't want an accidental collision.
		$pluginbuddy_view_file = self::$_plugin_path . '/views/' . $view_name . '.php';
		if ( file_exists( $pluginbuddy_view_file ) ) {
			unset( $view_name );
			if ( is_array( $pluginbuddy_data ) ) {
				extract( $pluginbuddy_data );
			} else {
				echo '{Warning: Data parameter passed to view was not an array.}';
			}
			require $pluginbuddy_view_file;
		} else {
			echo '{INVALID VIEW: `' . $view_name . '`; file not found.}';
		}
	} // End load_view().

	/**
	 * Loads a controller. Controllers may load controllers. Controller uses require_once to avoid problems.
	 *
	 * @param string $controller  Name of controller. Corresponds to the controller filename: /controllers/controller_name.php.
	 */
	public static function load_controller( $controller ) {
		// Using this method so load_controller() may be used anywhere.
		if ( file_exists( self::plugin_path() . '/controllers/' . $controller . '.php' ) ) {
			require_once self::plugin_path() . '/controllers/' . $controller . '.php';
		} else {
			echo '{Error: Unable to load page controller `' . $controller . '`; file not found.}';
		}
	} // End load_controller().

	/**
	 * Registers a widget. Will register widget class in /controllers/widget/slug.php. Widget class extend WP_Widgets.
	 *
	 * @param string $slug  Name / slug for widget. Must match filename in controllers\widgets\ directory. Class name in the format: pb_{PLUGINSLUG}_widget_{WIDGETSLUG}.
	 */
	public static function register_widget( $slug ) {
		if ( file_exists( self::plugin_path() . '/controllers/widgets/' . $slug . '.php' ) ) {
			require self::plugin_path() . '/controllers/widgets/' . $slug . '.php';
			add_action( 'widgets_init', array( 'pb_backupbuddy', 'do_register_widget' ) );
		} else {
			echo '{Error #3444548922: Unable to load widget file `controllers/widgets/' . $slug . '.php`.}';
		}
	} // End register_widget().

	/**
	 * Handle the widget registration.
	 */
	public static function do_register_widget() {
		register_widget( 'pb_' . self::settings( 'slug' ) . '_widget_' . $slug );
	}

	/**
	 * Removes array values in $remove from $array.
	 *
	 * @param array $array   Source array. This will have values removed and be returned.
	 * @param array $remove  Array of values to search for in $array and remove.
	 *
	 * @return array  Returns array $array stripped of all values found in $remove
	 */
	public static function array_remove( $array, $remove ) {
		if ( ! is_array( $remove ) ) {
			$remove = array( $remove );
		}
		return array_values( array_diff( $array, $remove ) );
	} // End array_remove().

	/**
	 * Attempt to strongarm a flush to actually work.
	 * Prevent flushing by adding this to wp-config.php:
	 *     define( 'BACKUPBUDDY_NOFLUSH', true );
	 * OR
	 *     set advanced option to prevent flush
	 *
	 * @param bool $force  Force flush.
	 *
	 * @return null
	 */
	public static function flush( $force = false ) {
		if ( true === $force ) {
			self::$_has_flushed = false;
		}

		if ( defined( 'BACKUPBUDDY_NOFLUSH' ) && ( BACKUPBUDDY_NOFLUSH === true ) ) { // Some servers seem to die on multiple flushes in the same pageload. Define this to prevent flushing.
			return;
		}
		if ( isset( pb_backupbuddy::$options ) && ( isset( pb_backupbuddy::$options['prevent_flush'] ) ) && ( '1' == pb_backupbuddy::$options['prevent_flush'] ) ) {
			return;
		}
		if ( true !== self::$_has_flushed ) { // Only run this once.
			if ( function_exists( 'apache_setenv' ) ) {
				@apache_setenv( 'no-gzip', 1 ); // Compression could cause server to wait for page to finish before proceeding. Turn off compression.
			}
			@ini_set( 'zlib.output_compression', 0 ); // Compression could cause server to wait for page to finish before proceeding. Turn off compression.
			self::$_has_flushed = true;
		}
		flush();
	} // End flush().

	/**
	 * Reset plugin options to defaults. Getting started page uses this.
	 *
	 * @return bool  True on success; false otherwise.
	 */
	public static function reset_defaults() {
		if ( isset( pb_backupbuddy::$_settings['default_options'] ) ) {
			pb_backupbuddy::$options = pb_backupbuddy::$_settings['default_options'];
			pb_backupbuddy::save();
			return true;
		}
		return false;
	} // End reset_defaults().

	/**
	 * Logs caller to error_log() if xdebug available.
	 *
	 * @return null
	 */
	public static function xdebug() {
		if ( ! function_exists( 'xdebug_call_file' ) ) {
			return;
		}
		error_log( 'Called @ ' . xdebug_call_file() . ':' . xdebug_call_line() . ' from ' . xdebug_call_function() );
	}

	/**
	 * Track Recent Edits
	 *
	 * @param string $action    Action how it was tracked.
	 * @param mixed  $relevant  Relevant content or object.
	 */
	public static function track_edit( $action, $relevant ) {
		$increment_counter = false;
		$edit_template     = array(
			'type'      => 'unknown',
			'action'    => $action,
			'timestamp' => current_time( 'mysql' ),
			'modified'  => 1,
			'deletion'  => false,
		);

		// Remove the post_content value to reduce size of the stored object.
		if ( is_a( $relevant, 'WP_Post' ) && isset( $relevant->post_content ) ) {
			$relevant->post_content = '';
		}

		if ( 'save_post' === $action || 'post_updated' === $action ) {
			if ( ! is_object( $relevant ) || ! isset( $relevant->ID ) ) {
				return;
			}

			$post_id = $relevant->ID;

			if ( isset( pb_backupbuddy::$options['recent_edits'][ $post_id ] ) ) {
				pb_backupbuddy::$options['recent_edits'][ $post_id ]['modified']++;
				pb_backupbuddy::$options['recent_edits'][ $post_id ]['post']      = $relevant;
				pb_backupbuddy::$options['recent_edits'][ $post_id ]['timestamp'] = current_time( 'mysql' );
			} else {
				$increment_counter = 'post'; // Trigger a counter increment.
				$save_post         = array_merge( $edit_template, array(
					'type'    => 'post',
					'post_id' => $post_id,
					'post'    => $relevant,
				) );

				pb_backupbuddy::$options['recent_edits'][ $post_id ] = $save_post;
			}
		} elseif ( 'insert_post' === $action ) {
			if ( ! is_object( $relevant ) || ! isset( $relevant->ID ) ) {
				return;
			}

			$post_id = $relevant->ID;

			if ( isset( pb_backupbuddy::$options['recent_edits'][ $post_id ] ) ) {
				pb_backupbuddy::$options['recent_edits'][ $post_id ]['modified']++;
				pb_backupbuddy::$options['recent_edits'][ $post_id ]['post']      = $relevant;
				pb_backupbuddy::$options['recent_edits'][ $post_id ]['timestamp'] = current_time( 'mysql' );
			} else {
				$increment_counter = 'post'; // Trigger a counter increment.
				$insert_post       = array_merge( $edit_template, array(
					'type'    => 'post',
					'post_id' => $post_id,
					'post'    => $relevant,
				) );

				pb_backupbuddy::$options['recent_edits'][ $post_id ] = $insert_post;
			}
		} elseif ( 'trash_post' === $action ) {
			if ( is_int( $relevant ) ) {
				$post_id = $relevant;
			} elseif ( ! is_object( $relevant ) || ! isset( $relevant->ID ) ) {
				return;
			} else {
				$post_id = $relevant->ID;
			}

			if ( isset( pb_backupbuddy::$options['recent_edits'][ $post_id ] ) ) {
				pb_backupbuddy::$options['recent_edits'][ $post_id ]['modified']++;
				pb_backupbuddy::$options['recent_edits'][ $post_id ]['deletion']  = true;
				pb_backupbuddy::$options['recent_edits'][ $post_id ]['post']      = $relevant;
				pb_backupbuddy::$options['recent_edits'][ $post_id ]['timestamp'] = current_time( 'mysql' );
			} else {
				$increment_counter = 'post'; // Trigger a counter increment.
				$delete_post       = array_merge( $edit_template, array(
					'type'     => 'post',
					'post_id'  => $post_id,
					'post'     => is_object( $relevant ) ? $relevant : false,
					'deletion' => true,
				) );

				pb_backupbuddy::$options['recent_edits'][ $post_id ] = $delete_post;
			}
		} elseif ( 'update_option' === $action ) {
			if ( isset( pb_backupbuddy::$options['recent_edits'][ 'option-' . $relevant['option'] ] ) ) {
				pb_backupbuddy::$options['recent_edits'][ 'option-' . $relevant['option'] ]['modified']++;
				pb_backupbuddy::$options['recent_edits'][ 'option-' . $relevant['option'] ]['timestamp'] = current_time( 'mysql' );
			} else {
				$increment_counter = 'option'; // Trigger a counter increment.
				$update_option     = array_merge( $edit_template, array(
					'type'   => 'option',
					'option' => $relevant['option'],
				) );
				pb_backupbuddy::$options['recent_edits'][ 'option-' . $relevant['option'] ] = $update_option;
			}
		} elseif ( 'delete_option' === $action ) {
			if ( isset( pb_backupbuddy::$options['recent_edits'][ 'option-' . $relevant['option'] ] ) ) {
				pb_backupbuddy::$options['recent_edits'][ 'option-' . $relevant['option'] ]['modified']++;
				pb_backupbuddy::$options['recent_edits'][ 'option-' . $relevant['option'] ]['deletion']  = true;
				pb_backupbuddy::$options['recent_edits'][ 'option-' . $relevant['option'] ]['timestamp'] = current_time( 'mysql' );
			} else {
				$increment_counter = 'option'; // Trigger a counter increment.
				$delete_option     = array_merge( $edit_template, array(
					'type'     => 'option',
					'option'   => $relevant['option'],
					'deletion' => true,
				) );
				pb_backupbuddy::$options['recent_edits'][ 'option-' . $relevant['option'] ] = $delete_option;
			}
		} elseif ( 'activate_plugin' === $action || 'deactivate_plugin' === $action || 'update_plugin' === $action ) {
			if ( isset( pb_backupbuddy::$options['recent_edits'][ 'plugin-' . $relevant['plugin'] ] ) ) {
				pb_backupbuddy::$options['recent_edits'][ 'plugin-' . $relevant['plugin'] ]['modified']++;
				pb_backupbuddy::$options['recent_edits'][ 'plugin-' . $relevant['plugin'] ]['timestamp'] = current_time( 'mysql' );
				pb_backupbuddy::$options['recent_edits'][ 'plugin-' . $relevant['plugin'] ]['action']    = $action;
			} else {
				$increment_counter = 'plugin'; // Trigger a counter increment.
				$plugin_data       = array();
				if ( file_exists( WP_PLUGIN_DIR . '/' . $relevant['plugin'] ) ) {
					$full_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $relevant['plugin'], false );
					// Only store what we need.
					if ( isset( $full_plugin_data['Name'] ) ) {
						$plugin_data['Name'] = $full_plugin_data['Name'];
					}
					if ( isset( $full_plugin_data['PluginURI'] ) ) {
						$plugin_data['PluginURI'] = $full_plugin_data['PluginURI'];
					}
				}
				$plugin_detail = array_merge( $edit_template, array(
					'type'        => 'plugin',
					'plugin'      => $relevant['plugin'],
					'plugin_data' => $plugin_data,
				) );
				pb_backupbuddy::$options['recent_edits'][ 'plugin-' . $relevant['plugin'] ] = $plugin_detail;
			}
		}

		if ( false !== $increment_counter ) {
			if ( ! is_array( pb_backupbuddy::$options['edits_since_last'] ) ) {
				pb_backupbuddy::$options['edits_since_last'] = array(
					'all'    => pb_backupbuddy::$options['edits_since_last'],
					'post'   => 0,
					'plugin' => 0,
					'option' => 0,
				);
			}

			pb_backupbuddy::$options['edits_since_last']['all']++;
			pb_backupbuddy::$options['edits_since_last'][ $increment_counter ]++;
			pb_backupbuddy::save();
		}
	}

} // End class pluginbuddy.

// Load pluginbuddy is_standalone helper functions.
require_once 'helpers/is_standalone.php';

// Load pluginbuddy database helper class.
require_once 'classes/class-pb-backupbuddy-db-helpers.php';

// FIXME: Isolate class, move the following code somewhere else, possibly a helper function.
if ( pb_is_standalone() ) {
	require_once 'standalone_preloader.php';
}

// ********** Load core classes **********
require_once dirname( __FILE__ ) . '/classes/core_controllers.php';
if ( is_admin() ) {
	require_once dirname( __FILE__ ) . '/classes/form.php';
	require_once dirname( __FILE__ ) . '/classes/settings.php';
}

// ********** Initialize PluginBuddy framework **********
if ( ! isset( $pluginbuddy_init ) ) {
	$pluginbuddy_init = 'init.php'; // default init file.
}
pb_backupbuddy::init( $pluginbuddy_settings, $pluginbuddy_init );
unset( $pluginbuddy_settings );
unset( $pluginbuddy_init );

pb_backupbuddy::load();

// ********** Load initialization files **********
require_once dirname( dirname( __FILE__ ) ) . '/init_global.php';
if ( is_admin() ) {
	require_once dirname( dirname( __FILE__ ) ) . '/init_admin.php';
}

if ( pb_is_standalone() ) {
	pb_backupbuddy::load_controller( 'pages/default' );
}
