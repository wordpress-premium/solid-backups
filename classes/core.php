<?php
/**
 * Solid Backups Core Class
 *
 * @package BackupBuddy
 */

/**
 * Helper functions for BackupBuddy.
 *
 * TODO: Eventually break out of a lot of these from BB core. Migrating from old framework to new resulted in this mid-way transition but it's a bit messy...
 */
class backupbuddy_core {

	/**
	 * Cached log dir for getLogDirectory() to prevent having to call WP to retrieve.
	 *
	 * @var string
	 */
	private static $_cachedLogDirectory = '';

	public static $warn_plugins = array(
		'w3-total-cache.php' => 'W3 Total Cache',
		'wp-cache.php'       => 'WP Super Cache',
		'wordfence.php'      => 'Wordfence',
	);

	/**
	 * Converts an interval time period (numeric) into an array of Array( interval tag, the display title ).
	 * Returns false if no matching interval found registered with WordPress.
	 *
	 * @param string $interval  Cron interval.
	 *
	 * @return array|false  Array( $tag, $display ) or false if interval not found.
	 */
	public static function prettyCronInterval( $interval ) {
		$schedule_intervals = wp_get_schedules();
		foreach ( $schedule_intervals as $interval_tag => $schedule_interval ) {
			if ( $interval == $schedule_interval['interval'] ) {
				return array( $interval_tag, $schedule_interval['display'] );
			}
		}
		return false; // Not found.
	}

	/**
	 * Convert function name to pretty title.
	 *
	 * @param string $function  Function name.
	 * @param string $args      Array of arguments.
	 *
	 * @return string  Prettified title.
	 */
	public static function prettyFunctionTitle( $function, $args = array() ) {
		if ( 'backup_create_database_dump' === $function ) {
			$function_title = 'Backing up database';
			if ( ! empty( $args ) ) {
				// This is not used.
				$sub_function_title = 'Tables: ' . implode( ', ', $args[0] );
			}
		} elseif ( 'backup_zip_files' === $function ) {
			$function_title = 'Zipping up files';
		} elseif ( 'integrity_check' === $function ) {
			$function_title = 'Verifying backup file integrity';
		} elseif ( 'post_backup' === $function ) {
			$function_title = 'Cleaning up';
		} elseif ( 'ms_download_extract_wordpress' === $function ) {
			$function_title = 'Downloading WordPress core files from wordpress.org';
		} elseif ( 'ms_create_wp_config' === $function ) {
			$function_title = 'Generating standard wp-config.php for export';
		} elseif ( 'ms_copy_plugins' === $function ) {
			$function_title = 'Copying plugins';
		} elseif ( 'ms_copy_themes' === $function ) {
			$function_title = 'Copying themes';
		} elseif ( 'ms_copy_media' === $function ) {
			$function_title = 'Copying media';
		} elseif ( 'ms_copy_users_table' === $function ) {
			$function_title = 'Copying users';
		} elseif ( 'ms_cleanup' === $function ) {
			$function_title = 'Cleaning up Multisite-specific temporary data';
		} else {
			// Attempt to prettify automatically.
			$function_title = ucwords( str_replace( '_', ' ', $function ) );
		}

		return $function_title;
	} // end prettyFunctionTitle().


	/**
	 * Returns a boolean indicating whether a plugin is network activated or not.
	 *
	 * @return bool  True if plugin is network activated, else false.
	 */
	public static function is_network_activated() {

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) { // Function is not available on all WordPress pages for some reason according to codex.
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}
		if ( is_plugin_active_for_network( basename( pb_backupbuddy::plugin_path() ) . '/' . pb_backupbuddy::settings( 'init' ) ) ) { // Path relative to wp-content\plugins\ directory.
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Integrity Check
	 *
	 * Scans a backup file and saves the result in data structure. Checks for key files & that .zip can be read properly. Stores results with details in data structure.
	 *
	 * @param string $file               Full pathname & filename to backup file to check.
	 * @param obj    $fileoptions        Fileoptions object currently holding the fileoptions file open, if any.
	 * @param array  $options            Array of options.
	 * @param bool   $skip_log_redirect  Skip log redirect.
	 *
	 * @return array  Returns integrity data array.
	 */
	public static function backup_integrity_check( $file, $fileoptions = '', $options = array(), $skip_log_redirect = false ) {
		include_once 'integrity_check.php';
		$check = new backupbuddy_integrity_check( $file, $fileoptions, $options, $skip_log_redirect );
		return $check->integrity_array;
	}

	/**
	 * Returns the backup serial based on the filename.
	 *
	 * @param string $file  Filename containing a serial to extract.
	 *
	 * @return string  Serial found. Empty string if unable to find serial.
	 */
	public static function get_serial_from_file( $file ) {
		$dashpos = strrpos( $file, '-' );
		if ( false === $dashpos ) {
			return '';
		}
		$serial = $dashpos + 1;
		$serial = substr( $file, $serial, ( strlen( $file ) - $serial - 4 ) );
		$serial = trim( $serial ); // Trim any potential whitespace.

		return $serial;

	}

	/**
	 * Get Backup Information from File name.
	 *
	 * @param string $file  Zip file or path to zip.
	 * @param string $info  Specific info to return.
	 *
	 * @return array|string  Info array or info contents.
	 */
	public static function parse_file( $file, $info = false ) {
		if ( false === strpos( $file, '.zip' ) ) {
			// Invalid file.
			return false;
		}

		$segments = explode( '-', substr( basename( $file ), 0, -4 ) );
		$return   = array();
		$defaults = array(
			'source'    => __( 'Unknown', 'it-l10n-backupbuddy' ),
			'domain'    => __( 'Unknown', 'it-l10n-backupbuddy' ),
			'profile'   => __( 'None', 'it-l10n-backupbuddy' ),
			'date'      => __( 'Unknown', 'it-l10n-backupbuddy' ),
			'time'      => null,
			'type'      => __( 'Unknown', 'it-l10n-backupbuddy' ),
			'serial'    => __( 'Unknown', 'it-l10n-backupbuddy' ),
			'timestamp' => __( 'Unknown', 'it-l10n-backupbuddy' ),
			'datetime'  => __( 'Unknown', 'it-l10n-backupbuddy' ),
			'id'        => __( 'Unknown', 'it-l10n-backupbuddy' ),
			'nicename'  => __( 'Unknown', 'it-l10n-backupbuddy' ),
		);

		$total_segments = count( $segments );
		$rev_segments   = array_reverse( $segments );

		// We need a minimum of 5 segments.
		if ( $total_segments >= 5 ) {
			$return = array(
				'source' => $segments[0], // Usually "backup" or "snapshot".
				'type'   => strtolower( $rev_segments[1] ),
				'serial' => $rev_segments[0],
				'id'     => $rev_segments[0], // Alias ID for serial.
			);

			$last_seg   = $total_segments - 5; // 5 represents serial, type, date, time plus one more.
			$maybe_date = $rev_segments[2];

			if ( is_numeric( $maybe_date ) ) { // timestamp.
				$return['timestamp'] = (int) $maybe_date;
				$last_seg++; // Bump up one for missing date.
			} elseif ( false !== strtotime( str_replace( '_', '-', $maybe_date ) ) || false !== strtotime( str_replace( '_', ':', $maybe_date ) ) ) {
				if ( false !== strtotime( str_replace( '_', '-', $rev_segments[3] ) ) ) { // date and time.
					$return['date'] = str_replace( '_', '-', $rev_segments[3] ); // Date.
					$return['time'] = str_replace( '_', ':', $maybe_date ); // Time.
				} else { // just date.
					$return['date'] = str_replace( '_', '-', $maybe_date ); // Date.
					$last_seg--; // drop down one for missing time.
				}
			} else { // Support for profile.
				$return['profile'] = $maybe_date;
				$maybe_date        = $rev_segments[3];

				if ( is_numeric( $maybe_date ) ) { // Timestamp.
					$return['timestamp'] = (int) $maybe_date;
				} elseif ( false !== strtotime( str_replace( '_', '-', $rev_segments[4] ) ) ) { // date and time.
					$return['date'] = str_replace( '_', '-', $rev_segments[4] );
					$return['time'] = str_replace( '_', ':', $maybe_date );
					$last_seg--; // we have 1 extra (profile), so bump down one.
				} else { // just date.
					$return['date'] = str_replace( '_', '-', $maybe_date );
				}
			}

			// Domain is anything between the first element and the last determined value.
			$domain           = implode( '-', array_slice( $segments, 1, $last_seg ) );
			$return['domain'] = str_replace( '_', '.', $domain );

			if ( ! empty( $return['date'] ) ) {
				$time                = ! empty( $return['time'] ) ? ' ' . $return['time'] : '';
				$return['timestamp'] = (int) strtotime( $return['date'] . $time );
			}
			if ( ! empty( $return['timestamp'] ) ) {
				$format             = empty( $return['time'] ) ? 'Y-m-d' : 'Y-m-d H:i:s';
				$return['datetime'] = date( $format, $return['timestamp'] );
			}

			// Normalize date/time.
			if ( ! empty( $return['timestamp'] ) ) {
				$return['date'] = date( 'Y-m-d', $return['timestamp'] );
				if ( ! empty( $return['time'] ) ) {
					$return['time'] = date( 'H:i:s', $return['timestamp'] );
				}
				$format             = empty( $return['time'] ) ? 'l, F j, Y' : 'l, F j, Y g:ia';
				$return['nicename'] = pb_backupbuddy::$format->date( $return['timestamp'], $format );
			}
		}

		$return = array_merge( $defaults, $return );

		if ( false === $info ) {
			return $return;
		}

		if ( ! empty( $return[ $info ] ) ) {
			return $return[ $info ];
		}

		return false;
	}

	/**
	 * Check the version of an item and compare it to the minimum requirements Solid Backups requires.
	 *
	 * @param string $type    Optional. If left blank '' then all tests will be performed. Valid values: WordPress, php, ''.
	 * @param bool   $notify  Optional. Whether or not to alert to the screen (and throw error to log) of a version issue.
	 *
	 * @return bool  True if the selected type is a bad version
	 */
	public static function versions_confirm( $type = '', $notify = false ) {

		$bad_version = false;

		if ( 'wordpress' == $type || '' == $type ) { // @codingStandardsIgnoreLine: Should be all lowercase here.
			global $wp_version;
			if ( version_compare( $wp_version, pb_backupbuddy::settings( 'wp_minimum' ), '<=' ) ) {
				if ( true === $notify ) {
					pb_backupbuddy::alert( sprintf( __( 'ERROR: Solid Backups requires WordPress version %1$s or higher. You may experience unexpected behavior or complete failure in this environment. Please consider upgrading WordPress.', 'it-l10n-backupbuddy' ), self::_wp_minimum ) );
					pb_backupbuddy::log( 'Unsupported WordPress Version: ' . $wp_version, 'error' );
				}
				$bad_version = true;
			}
		}
		if ( 'php' === $type || '' == $type ) {
			if ( version_compare( PHP_VERSION, pb_backupbuddy::settings( 'php_minimum' ), '<=' ) ) {
				if ( true === $notify ) {
					pb_backupbuddy::alert( sprintf( __( 'ERROR: Solid Backups requires PHP version %1$s or higher. You may experience unexpected behavior or complete failure in this environment. Please consider upgrading PHP.', 'it-l10n-backupbuddy' ), PHP_VERSION ) );
					pb_backupbuddy::log( 'Unsupported PHP Version: ' . PHP_VERSION, 'error' );
				}
				$bad_version = true;
			}
		}
		return $bad_version;
	}

	/**
	 * Retrieve directory for storing backups within.
	 *
	 * @return string  Full path to directory, including trailing slash.
	 */
	public static function getBackupDirectory() {
		if ( '' == pb_backupbuddy::$options['backup_directory'] ) {
			$dir = self::_getBackupDirectoryDefault();
		} else {
			$dir = pb_backupbuddy::$options['backup_directory'];
		}
		return $dir;
	}

	/**
	 * Retrieve directory for storing logs within. Cached.
	 *
	 * @return string  Full path to directory, including trailing slash.
	 */
	public static function getLogDirectory() {
		if ( '' != self::$_cachedLogDirectory ) {
			return self::$_cachedLogDirectory;
		}
		if ( pb_is_standalone() ) {
			return ABSPATH . 'importbuddy/';
		}

		$uploads_dirs              = wp_upload_dir();
		self::$_cachedLogDirectory = $uploads_dirs['basedir'] . '/pb_backupbuddy/';

		return self::$_cachedLogDirectory;
	}

	/**
	 * Retrieve temporary directory for storing temporary files within.
	 *
	 * @return string  Full path to directory, including trailing slash.
	 */
	public static function getTempDirectory() {
		// TODO: Should this use wp_upload_dir()?
		return ABSPATH . 'wp-content/uploads/backupbuddy_temp/';
	}

	/**
	 * Default directory backups will be stored in. getBackupDirectory() uses this as the default if no path is specifically set.
	 *
	 * @return string  Full path to directory, including trailing slash.
	 */
	public static function _getBackupDirectoryDefault() {
		if ( defined( 'PB_IMPORTBUDDY' ) && true === PB_IMPORTBUDDY ) {
			return ABSPATH;
		}
		$uploads_dirs = wp_upload_dir();
		return $uploads_dirs['basedir'] . '/backupbuddy_backups/';
	}

	/**
	 * Takes a profile custom root path and normalizes it, taking into account /./, /../, etc.
	 *
	 * @param string $path  Additional path from root.
	 *
	 * @return string  Full normalized custom path from root.
	 */
	public static function get_normalized_custom_root( $path ) {
		return realpath( ABSPATH . $path ) . '/';
	}

	/**
	 * Get sanitized directory exclusions. Exclusions are relative to site root (ABSPATH). See important note below!
	 *
	 * IMPORTANT NOTE: Cannot exclude the temp directory here as this is where SQL and DAT files are stored for inclusion in the backup archive.
	 *
	 * @param array  $profile      Profile array of data. Key 'excludes' can be array or newline-deliminated string.
	 * @param bool   $trim_suffix  True (default) if trailing slash should be trimmed from directories.
	 * @param string $serial       Optional serial of current backup. By default all subdirectories within the backupbuddy_temp dir are explicitly excluded. Specifying allows this serial subdirectory to not be excluded.
	 *
	 * @return array  Array of directories to exclude.
	 */
	public static function get_directory_exclusions( $profile, $trim_suffix = true, $serial = '' ) {
		$profile = array_merge( pb_backupbuddy::settings( 'profile_defaults' ), $profile );

		// No trailing slash $abspath.
		$abspath = rtrim( ABSPATH, '/' );

		// Custom root (if any).
		$backup_root = ABSPATH; // Default root.
		if ( isset( $profile['custom_root'] ) && '' != $profile['custom_root'] ) {
			pb_backupbuddy::status( 'details', 'Exclusion custom root (raw): ' . esc_html( $profile['custom_root'] ) );
			$backup_root = backupbuddy_core::get_normalized_custom_root( $profile['custom_root'] );
		}

		// Handle smart profile types.
		if ( 'media' === $profile['type'] ) {
			$backup_root = backupbuddy_core::get_media_root();
		} elseif ( 'themes' === $profile['type'] ) {
			$backup_root = backupbuddy_core::get_themes_root();
		} elseif ( 'plugins' === $profile['type'] ) {
			$backup_root = backupbuddy_core::get_plugins_root();
		}

		$excludes = array();

		// Hardcoded exclusions, relative to $abspath.
		foreach ( backupbuddy_constants::$HARDCODED_DIR_EXCLUSIONS as $exclusion ) {
			$excludes[] = $abspath . $exclusion;
		}

		// Backup storage directory.
		$excludes[] = self::getBackupDirectory();

		// Log directory.
		$excludes[] = self::getLogDirectory();

		// Restore directories.
		if ( function_exists( 'wp_upload_dir' ) ) {
			$uploads_dir  = wp_upload_dir();
			$restore_dirs = glob( rtrim( $uploads_dir['basedir'], '/' ) . '/backupbuddy-restoretmp-*' );
			if ( is_array( $restore_dirs ) && ! empty( $restore_dirs ) ) {
				foreach ( $restore_dirs as $restore_dir ) {
					// Make sure we don't accidentally exclude the root folder.
					if ( ! $restore_dir || ! ! rtrim( $restore_dir, '/' ) ) {
						continue;
					}
					$excludes[] = rtrim( $restore_dir, '/' ) . '/';
				}
			}
		}

		// Media/themes/plugins excluded option.
		if ( isset( $profile['exclude_media'] ) && '1' == $profile['exclude_media'] ) {
			pb_backupbuddy::status( 'details', __( 'Excluding media directories.', 'it-l10n-backupbuddy' ) );
			$excludes[] = backupbuddy_core::get_media_root();
		}
		if ( isset( $profile['exclude_themes'] ) && '1' == $profile['exclude_themes'] ) {
			pb_backupbuddy::status( 'details', __( 'Excluding theme directories.', 'it-l10n-backupbuddy' ) );
			$excludes[] = backupbuddy_core::get_themes_root();
		}
		if ( isset( $profile['exclude_plugins'] ) && '1' == $profile['exclude_plugins'] ) {
			pb_backupbuddy::status( 'details', __( 'Excluding plugin directories.', 'it-l10n-backupbuddy' ) );
			$excludes[] = backupbuddy_core::get_plugins_root();
		} elseif ( isset( $profile['active_plugins_only'] ) && '1' === $profile['active_plugins_only'] ) {
			pb_backupbuddy::status( 'details', __( 'Excluding inactive plugin directories.', 'it-l10n-backupbuddy' ) );
			$excludes = array_merge( $excludes, backupbuddy_core::get_inactive_plugins() );
		}

		// Exclude all temp directories within backupbuddy_temp, except any subdirectories containing the serial specified (if any).
		$temp_dirs = @glob( self::getTempDirectory() . '*', GLOB_ONLYDIR );
		if ( ! is_array( $temp_dirs ) ) {
			$temp_dirs = array();
		}
		foreach ( $temp_dirs as $temp_dir ) {
			if ( '' == $serial || false === strstr( $temp_dir, $serial ) ) { // If no specific serial supplied OR this dir does not contain the serial, exclude it.
				if ( '/' != substr( $temp_dir, -1 ) ) {
					$temp_dir = $temp_dir . '/';
				}
				pb_backupbuddy::status( 'details', 'Excluding additional temp directory subdir: `' . esc_html( $temp_dir ) . '`.' );
				$excludes[] = $temp_dir;
			}
		}

		// Profile-specific OR global excludes.
		if ( ! is_array( $profile['excludes'] ) ) {
			$profile_exclusions = trim( $profile['excludes'] ); // Trim string.
			if ( '-1' == $profile_exclusions ) { // Use default global exclusions instead of profile.
				$profile_exclusions = pb_backupbuddy::$options['profiles'][0]['excludes'];
				$profile_exclusions = preg_split( '/\n|\r|\r\n/', $profile_exclusions ); // Break into array on any type of line ending.
				pb_backupbuddy::status( 'details', 'Profile inheriting global exclusions.' );
				foreach ( $profile_exclusions as &$profile_exclusion ) { // Handle possible path abose $abspath in custom root.
					$profile_exclusion = trim( $profile_exclusion );
					if ( '' === $profile_exclusion ) {
						continue;
					}
					$profile_exclusion = $abspath . $profile_exclusion;
				}
			} else {
				$profile_exclusions = preg_split( '/\n|\r|\r\n/', $profile_exclusions ); // Break into array on any type of line ending.
				foreach ( $profile_exclusions as &$profile_exclusion ) {
					if ( '' == $profile_exclusion ) {
						continue;
					}
					$profile_exclusion = rtrim( $backup_root, '/' ) . $profile_exclusion;
				}
			}
		} else {
			$profile_exclusions = $profile['excludes'];
			foreach ( $profile_exclusions as &$profile_exclusion ) { // Handle possible path abose $abspath in custom root.
				$profile_exclusion = trim( $profile_exclusion );
				if ( '' === $profile_exclusion ) {
					continue;
				}
				$profile_exclusion = $abspath . $profile_exclusion;
			}
		}
		$excludes = array_merge( $excludes, $profile_exclusions );

		// Process exclusion variables.
		foreach ( $excludes as &$absolute_exclude ) {
			if ( ! $absolute_exclude ) {
				continue;
			}
			$absolute_exclude = str_replace( '{media}', self::get_media_root(), $absolute_exclude );
			$absolute_exclude = str_replace( '{themes}', self::get_themes_root(), $absolute_exclude );
			$absolute_exclude = str_replace( '{plugins}', self::get_plugins_root(), $absolute_exclude );
		}

		// Clean up & sanitize array.
		$excludes = array_map( 'trim', $excludes ); // Apply (whitespace-only) trim to all items within.
		if ( $trim_suffix ) {
			foreach ( $excludes as &$exclude ) { // Apply rtrim to all items within.
				$exclude = rtrim( $exclude, '/' );
			}
		}

		// Apply filter for 3rd party modifications.
		$excludes = apply_filters( 'backupbuddy_zip_exclusions', $excludes );

		// Remove duplicates.
		$excludes = array_unique( $excludes );

		// Remove all exclusions that do not begin at the backup root (custom or default).
		foreach ( $excludes as &$absolute_exclude ) {
			if ( 0 !== strpos( $absolute_exclude, $backup_root ) ) {
				$absolute_exclude = '';
			}
		}

		// Remove any empty / blank lines.
		$excludes = array_filter( $excludes, 'strlen' );

		// Fix array indexes for removed items.
		$excludes = array_values( $excludes );

		// Make all exclusions relative to backup root.
		foreach ( $excludes as &$exclude ) {
			$exclude = '/' . str_replace( $backup_root, '', $exclude );
		}

		return $excludes;

	}

	/**
	 * Sends an error email to the defined email address(es) on settings page.
	 *
	 * @param string $message             Message to be included in the body of the email.
	 * @param string $override_recipient  Email address(es) to send to instead of the normal recipient.
	 * @param array  $attachments         String or array of filename(s) to send as email attachments.
	 */
	public static function mail_error( $message, $override_recipient = '', $attachments = array() ) {
		require_once pb_backupbuddy::plugin_path() . '/classes/constants.php';

		if ( ! isset( pb_backupbuddy::$options ) ) {
			pb_backupbuddy::load();
		}

		// If there is an error due to a problem with the plugin settings then load defaults temporarily.
		if ( ( ! is_array( pb_backupbuddy::$options ) ) || ( ! isset( pb_backupbuddy::$options['data_version'] ) ) ) {
			$settings_copy = pb_backupbuddy::settings( 'default_options' );
		} else {
			$settings_copy = pb_backupbuddy::$options;
		}

		if ( ( time() - $settings_copy['last_error_email_time'] ) < backupbuddy_constants::MIMIMUM_TIME_BETWEEN_ERROR_EMAILS ) {
			pb_backupbuddy::status( 'warning', 'Warning #4389484: Tried to send error email too fast. Skipping send. Message: `' . esc_html( $message ) . '`.' );
			return true;
		}

		// Track last sent time to prevent flood.
		$settings_copy['last_error_email_time'] = time();
		pb_backupbuddy::save();

		$subject = $settings_copy['email_notify_error_subject'];
		$body    = $settings_copy['email_notify_error_body'];

		$replacements = array(
			'{site_url}'            => site_url(),
			'{logo_url}'            => $settings_copy['built_email_logo'],
			'{home_url}'            => home_url(),
			'{backupbuddy_version}' => pb_backupbuddy::settings( 'version' ),
			'{current_datetime}'    => date( DATE_RFC822 ),
			'{heading}'             => 'Solid Backups Error',
			'{support_display}'     => 'table',
			'{message}'             => $message,
		);

		// Customize Subject and user customizable message.
		foreach ( $replacements as $replace_key => $replacement ) {
			$subject = str_replace( $replace_key, $replacement, $subject );
			$body    = str_replace( $replace_key, $replacement, $body );
		}

		$replacements['{body}'] = $body;

		// Customize the final body based on our HTML template.
		$custom_email_template = apply_filters( 'backupbuddy_custom_email_template', get_theme_root() . '/backupbuddy-email-template.php' );
		if ( @file_exists( $custom_email_template ) ) {
			$body = file_get_contents( $custom_email_template );
		} else {
			$body = file_get_contents( dirname( dirname( __FILE__ ) ) . '/views/backupbuddy-email-template.php' );
		}

		foreach ( $replacements as $replace_key => $replacement ) {
			$body = str_replace( $replace_key, $replacement, $body );
		}

		/*
		 * Added 10/17/2023 by Jared Hill
		 * This is just intended to replace "cached" variables still containing
		 * BackupBuddy rather than Solid Backups. Previous installations of
		 * Backups will still contain BackupBuddy, which we dont want.
		 * TODO Remove Line Below
		 */
		$body = str_replace( 'BackupBuddy', 'Solid Backups', $body );

		$email = $settings_copy['email_notify_error'];
		if ( '' != $override_recipient ) {
			$email = $override_recipient;
			pb_backupbuddy::status( 'details', 'Overriding email recipient to: `' . esc_html( $override_recipient ) . '`.' );
		}

		// Default to admin_email if not configured anywhere else.
		if ( '' == $email ) {
			$email = get_option( 'admin_email' );
		}

		if ( ! empty( $email ) ) {
			pb_backupbuddy::status( 'details', 'Sending email error notification with subject `' . esc_html( $subject ) . '` to recipient(s): `' . esc_html( $email ) . '`.' );
			if ( '' != $settings_copy['email_return'] ) {
				$email_return = $settings_copy['email_return'];
			} else {
				$email_return = get_option( 'admin_email' );
			}

			if ( function_exists( 'wp_mail' ) ) {
				$result = wp_mail( $email, $subject, $body, 'From: Solid Backups <' . $email_return . ">\r\n" . 'Content-Type: text/html;' . "\r\n" . 'Reply-To: ' . get_option( 'admin_email' ) . "\r\n", $attachments );
				if ( false === $result ) {
					pb_backupbuddy::status( 'error', 'Error #45443554: Unable to send error email with WordPress wp_mail(). Verify WordPress & Server settings.' );
				}
			} else {
				pb_backupbuddy::status( 'error', 'Warning #3289239: wp_mail() unavailable. Inside WordPress?' );
			}
		} else {
			pb_backupbuddy::status( 'warning', 'No email addresses are set to receive error notifications on the Settings page AND get_option("admin_email") not set. Setting a notification email is suggested.' );
		}

	}

	/**
	 * Sends a message email to the defined email address(es) on settings page.
	 *
	 * @param string $serial              Backup serial.
	 * @param string $start_or_complete   Whether this is the notifcation for starting or completing. Valid values: start, complete, destinationComplete.
	 * @param string $message             Message to be included in the body of the email.
	 * @param array  $extra_replacements  Extra replacements.
	 */
	public static function mail_notify_scheduled( $serial, $start_or_complete, $message, $extra_replacements = array() ) {

		if ( ! isset( pb_backupbuddy::$options ) ) {
			pb_backupbuddy::load();
		}

		if ( 'start' === $start_or_complete ) {
			$email = pb_backupbuddy::$options['email_notify_scheduled_start'];

			$subject = pb_backupbuddy::$options['email_notify_scheduled_start_subject'];
			$body    = pb_backupbuddy::$options['email_notify_scheduled_start_body'];

			$replacements = array(
				'{logo_url}'            => pb_backupbuddy::$options['built_email_logo'],
				'{site_url}'            => site_url(),
				'{home_url}'            => home_url(),
				'{backupbuddy_version}' => pb_backupbuddy::settings( 'version' ),
				'{current_datetime}'    => date( DATE_RFC822 ),
				'{heading}'             => 'Backup Started',
				'{message}'             => $message,
			);
		} elseif ( 'complete' === $start_or_complete ) {
			$email = pb_backupbuddy::$options['email_notify_scheduled_complete'];

			$subject = pb_backupbuddy::$options['email_notify_scheduled_complete_subject'];
			$body    = pb_backupbuddy::$options['email_notify_scheduled_complete_body'];

			$archive_file = '';
			pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #37...' );
			require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
			$backup_options = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/' . $serial . '.txt', true, true );
			$result         = $backup_options->is_ok();
			if ( true !== $result ) {
				pb_backupbuddy::status( 'error', 'Error retrieving fileoptions file `' . esc_html( backupbuddy_core::getLogDirectory() ) . 'fileoptions/' . $serial . '.txt`. Error #35564332.' );
				$archive_file = '[file_unknown]';
				$backup_size  = '[size_unknown]';
				$backup_type  = '[type_unknown]';
			} else {
				$archive_file = $backup_options->options['archive_file'];
				$backup_size  = $backup_options->options['archive_size'];
				$backup_type  = $backup_options->options['type'];
			}

			if ( is_array( $archive_file ) ) {
				$backup_file_text = '';
				foreach ( $archive_file as $single_file ) {
					$backup_file_text .= $backup_file_text ? ', ' . basename( $single_file ) : basename( $single_file );
				}
			} else {
				$backup_file_text = basename( is_array( $archive_file ) );
			}

			$replacements = array(
				'{banner}'              => pb_backupbuddy::$options['email_notify_send_finish_banner'],
				'{logo_url}'            => pb_backupbuddy::$options['built_email_logo'],
				'{site_url}'            => site_url(),
				'{home_url}'            => home_url(),
				'{backupbuddy_version}' => pb_backupbuddy::settings( 'version' ),
				'{current_datetime}'    => date( DATE_RFC822 ),
				'{heading}'             => 'Backup Completed',
				'{message}'             => $message,

				'{backup_serial}'       => $serial,
				'{download_link}'       => pb_backupbuddy::ajax_url( 'download_archive' ) . '&backupbuddy_backup=' . basename( $archive_file ),
				'{backup_file}'         => $backup_file_text,
				'{backup_size}'         => $backup_size,
				'{backup_type}'         => $backup_type,
			);
		} elseif ( 'destinationComplete' == $start_or_complete ) {
			$email = pb_backupbuddy::$options['email_notify_send_finish'];

			$subject = pb_backupbuddy::$options['email_notify_send_finish_subject'];
			$body    = pb_backupbuddy::$options['email_notify_send_finish_body'];

			$replacements = array(
				'{banner}'              => pb_backupbuddy::$options['email_notify_send_finish_banner'],
				'{logo_url}'            => pb_backupbuddy::$options['built_email_logo'],
				'{site_url}'            => site_url(),
				'{home_url}'            => home_url(),
				'{backupbuddy_version}' => pb_backupbuddy::settings( 'version' ),
				'{current_datetime}'    => date( DATE_RFC822 ),
				'{heading}'             => 'Backup successfully sent to destination',
				'{message}'             => $message,
			);
		} else {
			pb_backupbuddy::status( 'error', 'ERROR #54857845785: Fatally halted. Invalid schedule type. Expected `start` or `complete`. Got `' . $start_or_complete . '`.' );
		}

		$replacements = array_merge( $replacements, $extra_replacements );

		// Customize Subject and user customizable message.
		$replacements['{support_display}'] = 'none';
		foreach ( $replacements as $replace_key => $replacement ) {
			$subject = str_replace( $replace_key, $replacement, $subject );
			$body    = str_replace( $replace_key, $replacement, $body );
		}

		$replacements['{body}'] = $body;

		// Customize the final body based on our HTML template.
		if ( @file_exists( get_theme_root() . '/backupbuddy-email-template.php' ) ) {
			$body = file_get_contents( get_theme_root() . '/backupbuddy-email-template.php' );
		} else {
			$body = file_get_contents( dirname( dirname( __FILE__ ) ) . '/views/backupbuddy-email-template.php' );
		}

		foreach ( $replacements as $replace_key => $replacement ) {
			$body = str_replace( $replace_key, $replacement, $body );
		}

		/*
		 * Added 10/17/2023 by Jared Hill
		 * This is just intended to replace "cached" variables still containing
		 * BackupBuddy rather than Solid Backups. Previous installations of
		 * Backups will still contain BackupBuddy, which we dont want.
		 * TODO Remove Line Below
		 */
		$body = str_replace( 'BackupBuddy', 'Solid Backups', $body );

		if ( '' != pb_backupbuddy::$options['email_return'] ) {
			$email_return = pb_backupbuddy::$options['email_return'];
		} else {
			$email_return = get_option( 'admin_email' );
		}

		pb_backupbuddy::status( 'details', 'Sending email schedule notification. Subject: `' . $subject . '`; recipient(s): `' . $email . '`.' );
		if ( ! empty( $email ) ) {
			if ( function_exists( 'wp_mail' ) ) {
				wp_mail( $email, $subject, $body, 'From: Solid Backups <' . $email_return . ">\r\n" . 'Reply-To: ' . get_option( 'admin_email' ) . "\r\n" . 'Content-type: text/html;' . "\r\n" );
			} else {
				pb_backupbuddy::status( 'error', 'Error #32892393: wp_mail() does not exist. Inside WordPress?' );
			}
		}
	}

	/**
	 * Strips all non-file-friendly characters from the site URL. Used in making backup zip filename.
	 *
	 * @return string  The filename friendly converted site URL.
	 */
	public static function backup_prefix() {

		$siteurl = site_url();
		$siteurl = str_replace( 'http://', '', $siteurl );
		$siteurl = str_replace( 'https://', '', $siteurl );
		$siteurl = str_replace( '/', '_', $siteurl );
		$siteurl = str_replace( '\\', '_', $siteurl );
		$siteurl = str_replace( '.', '_', $siteurl );
		$siteurl = str_replace( ':', '_', $siteurl ); // Alternative port from 80 is stored in the site url.
		$siteurl = str_replace( '~', '_', $siteurl ); // Strip ~.
		return $siteurl;

	}

	/**
	 * Returns true if resending or false if retry limit already met.
	 *
	 * @param object $fileoptions_obj  File Options object. (passed by reference unnecessarily...).
	 * @param int    $send_id          Send ID.
	 * @param int    $maximum_retries  Maximum nunber of retries.
	 *
	 * @return bool  If successful.
	 */
	public static function remoteSendRetry( &$fileoptions_obj, $send_id, $maximum_retries = 1 ) {
		// Destination settings are stored for this destination so see if we can retry sending it (if settings permit).
		if ( isset( $fileoptions_obj->options['destinationSettings'] ) && ( count( $fileoptions_obj->options['destinationSettings'] ) > 0 ) ) {

			$destination_settings = $fileoptions_obj->options['destinationSettings']; // these are the latest; includes info needed for chunking too.
			$delete_after         = $fileoptions_obj->options['deleteAfter'];
			$retries              = $fileoptions_obj->options['retries'];
			$file                 = $fileoptions_obj->options['file'];

			if ( $retries < $maximum_retries ) {
				pb_backupbuddy::status( 'details', 'Timed out remote send has not exceeded retry limit (`' . $maximum_retries . '`). Trying to send again.' );

				// Schedule send of this piece.
				pb_backupbuddy::status( 'details', 'Scheduling cron to send to this remote destination...' );
				$cron_args = array(
					$destination_settings,
					$file,
					$send_id,
					$delete_after,
					'', // identifier.
					true, // isRetry.
				);

				$schedule_result = backupbuddy_core::trigger_async_event( 'destination_send', $cron_args );
				if ( false === $schedule_result ) {
					$error = esc_html__( 'Error scheduling file transfer. Please check your Solid Backups error log for details. A plugin may have prevented scheduling or the database rejected it.', 'it-l10n-backupbuddy' );
					pb_backupbuddy::status( 'error', $error );
					echo $error;
				} else {
					pb_backupbuddy::status( 'details', 'Cron to send to remote destination scheduled.' );
				}

				self::maybe_spawn_cron();

				return true;
			} else {
				pb_backupbuddy::status( 'details', 'Maximum remote send timeout retries (`' . $maximum_retries . '`) passed to function met. Not resending.' );
				return false;
			}
		}
	}

	/**
	 * Get default array values for the remote_sends fileoptions files.
	 *
	 * @return array  Remote send defaults.
	 */
	public static function get_remote_send_defaults() {
		return array(
			'destination'         => 0,
			'file'                => '',
			'file_size'           => 0,
			'trigger'             => '',                     // What triggered this backup. Valid values: scheduled, manual.
			'send_importbuddy'    => false,
			'start_time'          => time(),
			'finish_time'         => 0,
			'update_time'         => time(),
			'status'              => 'running',  // success, failure, running, timeout (default assumption if this is not updated in this PHP load).
			'write_speed'         => 0,
			'destinationSettings' => array(),
			'sendID'              => '',
			'deleteAfter'         => false,
			'retries'             => 0,
		);
	}

	/**
	 * Send Remote Destination.
	 *
	 * @param int    $destination_id        ID number (index of the destinations array) to send it.
	 * @param string $file                  Full file path of file to send. MAY be an array of files.
	 * @param string $trigger               What triggered this backup. Valid values: scheduled, manual.
	 * @param bool   $send_importbuddy      Whether or not importbuddy.php should also be sent with the file to destination.
	 * @param bool   $delete_after          Whether or not to delete after send success after THIS send.
	 * @param string $identifier            Identifier.
	 * @param string $destination_settings  If passed then this array is used instead of grabbing from settings.
	 *
	 * @return bool  Send status. true success, false failed.
	 */
	public static function send_remote_destination( $destination_id, $file, $trigger = '', $send_importbuddy = false, $delete_after = false, $identifier = '', $destination_settings = '' ) {

		if ( ! is_array( $file ) ) {
			if ( ! file_exists( $file ) ) {
				// Check if utf8 decoding the filename helps us find it.
				$utf_decoded_filename = utf8_decode( $file );
				if ( file_exists( $utf_decoded_filename ) ) {
					$file = $utf_decoded_filename;
				} else {
					pb_backupbuddy::status( 'error', 'Error #8583489734: Unable to send file `' . esc_html( $file ) . '` to remote destination as it no longer exists. It may have been deleted or permissions are invalid.' );
					return false;
				}
			}
		}

		$migrationkey_transient_time = 60 * 60 * 24;

		if ( ! is_array( $file ) ) {
			if ( '' == $file ) {
				$backup_file_size = 50000; // not sure why anything current would be sending importbuddy but NOT sending a backup but just in case...
			} else {
				$backup_file_size = filesize( $file );
			}
		} else {
			$backup_file_size = -1;
		}

		// Generate remote send ID for reference and add it as a new logging serial for better recording details.
		if ( '' == $identifier ) {
			$identifier = pb_backupbuddy::random_string( 12 );
		}

		// Set migration key for later determining last initiated migration.
		if ( 'migration' == $trigger ) {
			set_transient( 'pb_backupbuddy_migrationkey', $identifier, $migrationkey_transient_time );
		}

		if ( ! is_array( $file ) ) {
			pb_backupbuddy::status( 'details', 'Sending file `' . esc_html( $file ) . '` to remote destination `' . $destination_id . '` with ID `' . $identifier . '` triggered by `' . $trigger . '`.' );
		} else {
			pb_backupbuddy::status( 'details', 'Sending multiple files (' . count( $file ) . ') in single pass to remote destination `' . $destination_id . '` with ID `' . $identifier . '` triggered by `' . $trigger . '`.' );
		}

		pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #35...' );
		require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
		$fileoptions_obj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $identifier . '.txt', false, true, true );
		$result          = $fileoptions_obj->is_ok();
		if ( true !== $result ) {
			pb_backupbuddy::status( 'error', __( 'Fatal Error #9034 A. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
			return false;
		}

		$fileoptions = &$fileoptions_obj->options; // Set reference.

		// Record some statistics.
		$fileoptions = array_merge(
			self::get_remote_send_defaults(),
			array(
				'destination'      => $destination_id,
				'file'             => $file,
				'file_size'        => $backup_file_size,
				'trigger'          => $trigger,                       // What triggered this backup. Valid values: scheduled, manual.
				'send_importbuddy' => $send_importbuddy,
				'start_time'       => time(),
				'finish_time'      => 0,
				'status'           => 'running',  // success, failure, running, timeout (default assumption if this is not updated in this PHP load).
				'write_speed'      => 0,
			)
		);
		pb_backupbuddy::save();

		// Destination settings were not passed so get them based on the destination ID provided.
		if ( ! is_array( $destination_settings ) ) {
			$destination_settings = &pb_backupbuddy::$options['remote_destinations'][ $destination_id ];
		}

		if ( true === $send_importbuddy ) {
			pb_backupbuddy::status( 'details', 'Generating temporary importbuddy.php file for remote send.' );
			pb_backupbuddy::anti_directory_browsing( backupbuddy_core::getTempDirectory(), false );
			$importbuddy_temp = backupbuddy_core::getTempDirectory() . 'importbuddy.php'; // Full path & filename to temporary importbuddy.
			self::importbuddy( $importbuddy_temp ); // Create temporary importbuddy.
			pb_backupbuddy::status( 'details', 'Generated temporary importbuddy.' );
			$importbuddy_file = $importbuddy_temp; // Add importbuddy file to the list of files to send.
			$send_importbuddy = true; // Track to delete after finished.
		} else {
			pb_backupbuddy::status( 'details', 'Not sending importbuddy.' );
		}

		// Clear fileoptions so other stuff can access it if needed.
		$fileoptions_obj->save();
		$fileoptions_obj->unlock();
		unset( $fileoptions_obj );

		// Pass off to destination handler.
		require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';

		pb_backupbuddy::status( 'details', 'Calling destination send() function.' );
		$send_result = pb_backupbuddy_destinations::send( $destination_settings, $file, $identifier, $delete_after );
		pb_backupbuddy::status( 'details', 'Finished destination send() function.' );

		if ( ! empty( $send_importbuddy ) && is_readable( $importbuddy_file ) ) {
			pb_backupbuddy::status( 'details', 'Calling destination send() function for importbuddy.php.' );
			$send_result = pb_backupbuddy_destinations::send( $destination_settings, $importbuddy_file, $identifier, $delete_after );
			pb_backupbuddy::status( 'details', 'Finished destination send() function for importbuddy.php.' );
		}

		self::kick_db(); // Kick the database to make sure it didn't go away, preventing options saving.

		// Reload fileoptions.
		pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #34 for saving send status...' );
		require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
		$fileoptions_obj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $identifier . '.txt', false, false, false );
		$result          = $fileoptions_obj->is_ok();
		if ( true !== $result ) {
			pb_backupbuddy::status( 'error', __( 'Fatal Error #9034 G. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
			return false;
		}
		pb_backupbuddy::status( 'details', 'Fileoptions data loaded for ID `' . $identifier . '`.' );
		$fileoptions = &$fileoptions_obj->options; // Set reference.

		// Update stats.
		$fileoptions[ $identifier ]['finish_time'] = microtime( true );

		if ( true === $send_result ) { // succeeded.
			$fileoptions['status']      = 'success';
			$fileoptions['finish_time'] = microtime( true );
			pb_backupbuddy::status( 'details', 'Remote send SUCCESS.' );

			$backups = is_array( $file ) ? $file : array( $file );
			foreach ( $backups as $backup ) {
				$data_file = backupbuddy_data_file()->locate( $backup );
				if ( false !== $data_file ) {
					pb_backupbuddy::status( 'details', 'Scheduling .dat file to be sent: `' . esc_html( $data_file ) . '`.' );
					self::trigger_async_event( 'remote_send', array( $destination_id, $data_file, $trigger ) );
				}
			}
		} elseif ( false === $send_result ) { // failed.
			$fileoptions['status'] = 'failure';
			pb_backupbuddy::status( 'details', 'Remote send FAILURE.' );
		} elseif ( is_array( $send_result ) ) { // Array so multipart.
			$fileoptions['status']            = 'multipart';
			$fileoptions['finish_time']       = 0;
			$fileoptions['_multipart_id']     = $send_result[0];
			$fileoptions['_multipart_status'] = $send_result[1];
			pb_backupbuddy::status( 'details', 'Multipart send in progress.' );
		} else {
			pb_backupbuddy::status( 'error', 'Error #5485785576463. Invalid status send result: `' . $send_result . '`.' );
		}
		$fileoptions_obj->save();

		// If we sent importbuddy then delete the local copy to clean up.
		if ( false !== $send_importbuddy ) {
			@unlink( $importbuddy_temp ); // Delete temporary importbuddy.
		}

		// As of v5.0: Post-send deletion now handled within destinations/bootstrap.php send() to support chunked sends.
		return $send_result;
	}

	/**
	 * Send file(s) to a destination. Pass full array of destination settings.
	 *
	 * @param array  $destination_settings  All settings for this destination for this action.
	 * @param array  $files                 Array of files to send (full path).
	 * @param string $send_id               Send ID.
	 * @param bool   $delete_after          Whether or not to delete after send success after THIS send.
	 * @param bool   $is_retry              If is a retry attempt.
	 *
	 * @return bool|array  Bool true = success, bool false = fail, array = multipart transfer.
	 */
	public static function destination_send( $destination_settings, $files, $send_id = '', $delete_after = false, $is_retry = false ) {

		// Pass off to destination handler.
		require_once pb_backupbuddy::plugin_path() . '/destinations/bootstrap.php';
		$send_result = pb_backupbuddy_destinations::send( $destination_settings, $files, $send_id, $delete_after, $is_retry );

		if ( true === $send_result ) { // succeeded.
			$backups = is_array( $files ) ? $files : array( $files );
			foreach ( $backups as $backup ) {
				$data_file = backupbuddy_data_file()->locate( $backup );
				if ( false !== $data_file ) {
					pb_backupbuddy::status( 'details', 'Scheduling .dat file to be sent: `' . esc_html( $data_file ) . '`.' );
					if ( isset( $destination_settings['destination_id'] ) ) {
						self::trigger_async_event( 'remote_send', array( $destination_settings['destination_id'], $data_file, 'Automatic' ) );
					} else {
						$dat_settings = $destination_settings;
						// Clean up settings for fresh dat file send.
						unset(
							$dat_settings['_chunk_upload_id'], // Dropbox3.
							$dat_settings['_chunk_offset'], // Dropbox3.
							$dat_settings['_chunk_maxsize'], // Dropbox3.
							$dat_settings['_chunk_next_offset'], // Dropbox3.
							$dat_settings['_chunk_total_sent'], // Dropbox3.
							$dat_settings['_chunk_sent_count'], // Dropbox3.
							$dat_settings['_chunk_total_count'], // Dropbox3.
							$dat_settings['_chunk_transfer_speeds'], // Dropbox3.
							$dat_settings['_chunks_sent'], // GDrive2.
							$dat_settings['_chunks_total'], // GDrive2.
							$dat_settings['_media_resumeUri'], // GDrive2.
							$dat_settings['_media_progress'], // GDrive2.
							$dat_settings['_multipart_id'], // S3v3.
							$dat_settings['_multipart_file'], // S3v3.
							$dat_settings['_multipart_partnumber'], // S3v3.
							$dat_settings['_multipart_remotefile'], // S3v3.
							$dat_settings['_multipart_etag_parts'], // S3v3.
							$dat_settings['_multipart_transferspeeds'], // S3v3.
							$dat_settings['_multipart_backup_type'], // S3v3.
							$dat_settings['_multipart_backup_size'], // S3v3.
							$dat_settings['_retry_stash_confirm'] // S3v3.
						);
						$dat_send_id = pb_backupbuddy::random_string( 12 );
						self::trigger_async_event( 'destination_send', array( $dat_settings, array( $data_file ), $dat_send_id, false, $dat_send_id ) );
					}
				}
			}
		}
		return $send_result;
	}

	/**
	 * Gets a list of backups.
	 *
	 * @param string $type          Valid options: default, migrate.
	 * @param bool   $subsite_mode  When in subsite mode only backups for that specific subsite will be listed.
	 *
	 * @return array  Sorted array of backups.
	 */
	public static function backups_list( $type = 'default', $subsite_mode = false ) {

		if ( 'delete_backup' === pb_backupbuddy::_POST( 'bulk_action' ) && is_array( pb_backupbuddy::_POST( 'items' ) ) ) {
			$needs_save = false;
			pb_backupbuddy::verify_nonce( pb_backupbuddy::_POST( '_wpnonce' ) ); // Security check to prevent unauthorized deletions by posting from a remote place.
			$deleted_files = array();
			foreach ( pb_backupbuddy::_POST( 'items' ) as $item ) {
				if ( file_exists( backupbuddy_core::getBackupDirectory() . $item ) ) {
					if ( true === @unlink( backupbuddy_core::getBackupDirectory() . $item ) ) {
						$deleted_files[] = $item;

						// Delete data file.
						$data_file = backupbuddy_data_file()->locate( backupbuddy_core::getBackupDirectory() . $item );
						if ( false !== $data_file ) {
							backupbuddy_data_file()->delete( $data_file );
						}

						// Cleanup any related fileoptions files.
						$serial = backupbuddy_core::get_serial_from_file( $item );

						$backup_files = glob( backupbuddy_core::getBackupDirectory() . '*.zip' );
						if ( ! is_array( $backup_files ) ) {
							$backup_files = array();
						}
						if ( count( $backup_files ) > 5 ) { // Keep a minimum number of backups in array for stats.
							$this_serial      = self::get_serial_from_file( $item );
							$fileoptions_file = backupbuddy_core::getLogDirectory() . 'fileoptions/' . $this_serial . '.txt';
							if ( file_exists( $fileoptions_file ) ) {
								@unlink( $fileoptions_file );
							}
							if ( file_exists( $fileoptions_file . '.lock' ) ) {
								@unlink( $fileoptions_file . '.lock' );
							}
							$needs_save = true;
						}
					} else {
						pb_backupbuddy::alert( 'Error: Unable to delete backup file `' . esc_attr( $item ) . '`. Please verify permissions.', true );
					}
				} // End if file exists.
			} // End foreach.
			if ( true === $needs_save ) {
				pb_backupbuddy::save();
			}

			pb_backupbuddy::alert( __( 'Deleted:', 'it-l10n-backupbuddy' ) . ' ' . implode( ', ', $deleted_files ) );
		} // End if deleting backup(s).

		$backups           = array();
		$backup_sort_dates = array();

		$files = glob( backupbuddy_core::getBackupDirectory() . 'backup*.zip' );
		if ( ! is_array( $files ) ) {
			$files = array();
		}

		$files2 = glob( backupbuddy_core::getBackupDirectory() . 'snapshot*.zip' );
		if ( ! is_array( $files2 ) ) {
			$files2 = array();
		}

		$files = array_merge( $files, $files2 );

		if ( is_array( $files ) && ! empty( $files ) ) { // For robustness. Without open_basedir the glob() function returns an empty array for no match. With open_basedir in effect the glob() function returns a boolean false for no match.

			$backup_prefix = self::backup_prefix(); // Backup prefix for this site. Used for MS checking that this user can see this backup.
			foreach ( $files as $file_id => $file ) {

				if ( true === $subsite_mode && is_multisite() ) { // If a Network and NOT the superadmin must make sure they can only see the specific subsite backups for security purposes.

					// Only allow viewing of their own backups.
					if ( ! strstr( $file, $backup_prefix ) ) {
						unset( $files[ $file_id ] ); // Remove this backup from the list. This user does not have access to it.
						continue; // Skip processing to next file.
					}
				}

				$serial = backupbuddy_core::get_serial_from_file( $file );

				$options = array();
				if ( file_exists( backupbuddy_core::getLogDirectory() . 'fileoptions/' . $serial . '.txt' ) ) {
					pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #33...' );
					require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
					$read_only      = false;
					$ignore_lock    = false;
					$create_file    = true;
					$backup_options = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/' . $serial . '.txt', $read_only, $ignore_lock, $create_file ); // Will create file to hold integrity data if nothing exists.
				} else {
					$backup_options = '';
				}
				$backup_integrity = backupbuddy_core::backup_integrity_check( $file, $backup_options, $options );

				// Backup status.
				$pretty_status = array(
					true   => '<span class="pb_label pb_label-success">Good</span>', // v4.0+ Good.
					'pass' => '<span class="pb_label pb_label-success">Good</span>', // Pre-v4.0 Good.
					false  => '<span class="pb_label pb_label-important">Bad</span>', // v4.0+ Bad.
					'fail' => '<span class="pb_label pb_label-important">Bad</span>', // Pre-v4.0 Bad.
				);

				// Backup type.
				$pretty_type = array(
					'full'    => 'Full',
					'db'      => 'Database',
					'files'   => 'Files',
					'themes'  => 'Themes',
					'plugins' => 'Plugins',
				);

				// Defaults.
				$detected_type = '';
				$file_size     = '';
				$modified      = '';
				$modified_time = 0;
				$integrity     = '';

				$main_string = 'Warn#284.';
				if ( is_array( $backup_integrity ) ) { // Data intact... put it all together.
					// Calculate time ago.
					$time_ago = '';
					if ( isset( $backup_integrity['modified'] ) ) {
						$time_ago = pb_backupbuddy::$format->time_ago( $backup_integrity['modified'] ) . ' ago';
					}

					$detected_type = pb_backupbuddy::$format->prettify( $backup_integrity['detected_type'], $pretty_type );
					if ( '' == $detected_type ) {
						$detected_type = backupbuddy_core::pretty_backup_type( backupbuddy_core::getBackupTypeFromFile( $file ) );
						if ( '' == $detected_type ) {
							$detected_type = '<span class="description">Unknown</span>';
						}
					} else {
						if ( isset( $backup_options->options['profile'] ) ) {
							$profile_title = isset( $backup_options->options['profile']['title'] ) ? htmlentities( $backup_options->options['profile']['title'] ) : '';
							$detected_type = '
							<div>
								<span class="profile_type-' . $backup_integrity['detected_type'] . '" style="float: left;" title="' . backupbuddy_core::pretty_backup_type( $detected_type ) . '"></span>
								<span style="display: inline-block; float: left; height: 15px; border-right: 1px solid #EBEBEB; margin-left: 6px; margin-right: 6px;"></span>
								' . $profile_title . '
							</div>
							';
						}
					}

					$file_size     = pb_backupbuddy::$format->file_size( $backup_integrity['size'] );
					$modified      = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $backup_integrity['modified'] ), 'l, F j, Y - g:i:s a' );
					$modified_time = $backup_integrity['modified'];
					if ( isset( $backup_integrity['status'] ) ) { // Pre-v4.0.
						$status = $backup_integrity['status'];
					} else { // v4.0+.
						$status = $backup_integrity['is_ok'];
					}

					// Calculate main row string.
					if ( 'default' === $type ) { // Default backup listing.
						$main_string = '<a href="' . pb_backupbuddy::ajax_url( 'download_archive' ) . '&backupbuddy_backup=' . basename( $file ) . '" class="backupbuddyFileTitle" title="' . basename( $file ) . '">' . $modified . ' (' . $time_ago . ')</a>';
					} elseif ( 'migrate' === $type ) { // Migration backup listing.
						$main_string = '<a class="pb_backupbuddy_hoveraction_migrate backupbuddyFileTitle" rel="' . basename( $file ) . '" href="' . esc_attr( pb_backupbuddy::page_url() ) . '&migrate=' . basename( $file ) . '&value=' . basename( $file ) . '" title="' . basename( $file ) . '">' . $modified . ' (' . $time_ago . ')</a>';
					} else {
						$main_string = '{Unknown type.}';
					}

					$data_file = backupbuddy_data_file()->locate( $file );
					if ( false !== $data_file ) {
						$main_string .= '<a href="#" style="display:inline-block; margin-left:5px;"><img src="' . esc_attr( pb_backupbuddy::plugin_url() ) . '/assets/dist/images/check.png" style="vertical-align:top;max-width: 16px;"></a>';
					}

					// Add comment to main row string if applicable.
					if ( ! empty( $backup_integrity['comment'] ) ) {
						$main_string .= '<br><span class="description">Note: <span class="pb_backupbuddy_notetext">' . htmlentities( $backup_integrity['comment'] ) . '</span></span>';
					}

					// No integrity check for themes or plugins types.
					$raw_type             = backupbuddy_core::getBackupTypeFromFile( $file );
					$skip_integrity_types = array( 'themes', 'plugins', 'media', 'files' );
					if ( in_array( $raw_type, $skip_integrity_types, true ) ) {
						unset( $file_count );
						foreach ( (array) $backup_integrity['tests'] as $test ) {
							if ( isset( $test['fileCount'] ) ) {
								$file_count = $test['fileCount'];
							}
						}
						$integrity = pb_backupbuddy::$format->prettify( $status, $pretty_status ) . ' ';
						if ( isset( $file_count ) ) {
							$integrity .= '<span class="pb_label pb_label-warning" style="display: none;">' . $file_count . ' ' . esc_html__( 'files', 'it-l10n-backupbuddy' ) . '</span> '; // TODO: Hidden until future version.
						}
					} else {
						$integrity = pb_backupbuddy::$format->prettify( $status, $pretty_status ) . ' ';
					}

					if ( isset( $backup_integrity['scan_notes'] ) && count( (array) $backup_integrity['scan_notes'] ) > 0 ) {
						foreach ( (array) $backup_integrity['scan_notes'] as $scan_note ) {
							$integrity .= $scan_note . ' ';
						}
					}
					$integrity .= '<a href="' . esc_attr( pb_backupbuddy::page_url() ) . '&reset_integrity=' . $serial . '" title="Rescan integrity. Last checked ' . pb_backupbuddy::$format->date( $backup_integrity['scan_time'] ) . '."><img src="' . pb_backupbuddy::plugin_url() . '/assets/dist/images/refresh_gray.gif" style="vertical-align: -1px;"></a>';
					$integrity .= '<div class="row-actions"><a title="' . __( 'Backup Status', 'it-l10n-backupbuddy' ) . '" href="' . pb_backupbuddy::ajax_url( 'integrity_status' ) . '&serial=' . $serial . '&#038;TB_iframe=1&#038;width=640&#038;height=600" class="thickbox">' . __( 'View Details', 'it-l10n-backupbuddy' ) . '</a></div>';

					$sum_log_file = backupbuddy_core::getLogDirectory() . 'status-' . $serial . '_' . pb_backupbuddy::$options['log_serial'] . '.txt';
					if ( file_exists( $sum_log_file ) ) {
						$integrity .= '<div class="row-actions"><a title="' . __( 'View Backup Log', 'it-l10n-backupbuddy' ) . '" href="' . pb_backupbuddy::ajax_url( 'view_log' ) . '&serial=' . $serial . '&#038;TB_iframe=1&#038;width=640&#038;height=600" class="thickbox">' . __( 'View Log', 'it-l10n-backupbuddy' ) . '</a></div>';
					}
				} // end if is_array( $backup_options ).

				$integrity .= '<div class="row-actions"><a href="javascript:void(0);" class="pb_backupbuddy_hoveraction_hash" rel="' . basename( $file ) . '">View Checksum</a></div>';

				$backups[ basename( $file ) ] = array(
					array( basename( $file ), $main_string . '<br><span class="description" style="color: #AAA; display: inline-block; margin-top: 5px;">' . basename( $file ) . '</span>' ),
					$detected_type,
					$file_size,
					$integrity,
				);

				$backup_sort_dates[ basename( $file ) ] = $modified_time;

			} // End foreach().
		} // End if.

		// Sort backup by date.
		arsort( $backup_sort_dates );
		// Re-arrange backups based on sort dates.
		$sorted_backups = array();
		foreach ( $backup_sort_dates as $backup_file => $backup_sort_date ) {
			$sorted_backups[ $backup_file ] = $backups[ $backup_file ];
			unset( $backups[ $backup_file ] );
		}
		unset( $backups );

		return $sorted_backups;

	}

	/**
	 * Get Dat Array from Zip
	 *
	 * @param string $file  Path to file.
	 *
	 * @return array|false  Dat array from zip or false if failed.
	 */
	public static function getDatArrayFromZip( $file ) {
		require_once pb_backupbuddy::plugin_path() . '/lib/zipbuddy/zipbuddy.php';
		$zipbuddy = new pluginbuddy_zipbuddy( backupbuddy_core::getBackupDirectory() );
		$serial   = self::get_serial_from_file( $file );

		$find   = false;
		$search = array(
			'wp-content/uploads/backupbuddy_temp/' . $serial . '/backupbuddy_dat.php', // Post 2.0 full backup.
			'wp-content/uploads/temp_' . $serial . '/backupbuddy_dat.php', // Pre 2.0 full backup.
			'backupbuddy_dat.php', // DB backup.
		);

		if ( ! isset( pb_backupbuddy::$classes['zipbuddy'] ) ) {
			pb_backupbuddy::$classes['zipbuddy'] = $zipbuddy;
		}

		foreach ( $search as $location ) {
			if ( true === pb_backupbuddy::$classes['zipbuddy']->file_exists( $file, $location ) ) {
				$find = $location;
				break;
			}
		}

		if ( false === $find ) {
			// Could not find DAT file.
			return false;
		}

		// Calculate temp directory & lock it down.
		$temp_dir    = get_temp_dir();
		$destination = $temp_dir;
		if ( ! file_exists( $destination ) && false === mkdir( $destination ) ) {
			$error = esc_html__( 'Error #458485945b: Unable to create temporary location.', 'it-l10n-backupbuddy' );
			pb_backupbuddy::status( 'error', $error );
			die( $error ); // @codingStandardsIgnoreLine: ok.
		}

		// If temp directory is within webroot then lock it down.
		$temp_dir = str_replace( '\\', '/', $temp_dir ); // Normalize for Windows.
		$temp_dir = rtrim( $temp_dir, '/\\' ) . '/'; // Enforce single trailing slash.
		if ( false !== stristr( $temp_dir, ABSPATH ) ) { // Temp dir is within webroot.
			pb_backupbuddy::anti_directory_browsing( $destination );
		}

		$dest_filename  = 'temp_dat_read-' . $serial . '.php';
		$extractions    = array( $find => $dest_filename );
		$extract_result = $zipbuddy->extract( $file, $destination, $extractions );
		if ( false === $extract_result ) { // failed.
			return false;
		} else {
			$dat_array = backupbuddy_data_file()->get_dat_file_array( rtrim( $destination, '\\/' ) . '/temp_dat_read-' . $serial . '.php' );
			@unlink( $temp_dir . $dest_filename );
			if ( is_array( $dat_array ) ) {
				return $dat_array;
			} else {
				return false;
			}
		}
	}

	/**
	 * Generate importbuddy.
	 *
	 * IMPORTANT: If outputting to browser (no output file) must die() after outputting content if using AJAX. Do not output to browser anything after this function in this case.
	 * IMPORTANT: _ALWAYS_ returns FALSE if no importbuddy pass hash is passed AND no pass hash is set in the Settings page.
	 * If $output_file is blank then importbuddy will either be returned or returned to the brwoser as a downloaded file based on 3rd parameter.
	 * If $output_file is defined then returns true on success writing file, else false.
	 *
	 * Use $importbuddy_pass_hash = null to leave #PASSWORD# placeholder. Eg for use with Live.
	 *
	 * @param string $output_file            Output Filename.
	 * @param string $importbuddy_pass_hash  Importer Password Hash.
	 * @param bool   $return_not_echo        Return instead of Echo.
	 *
	 * @return bool|void  If $return_not_echo is true, otherwise void.
	 */
	public static function importbuddy( $output_file = '', $importbuddy_pass_hash = '', $return_not_echo = false ) {

		pb_backupbuddy::set_greedy_script_limits(); // Some people run out of PHP memory.

		if ( null !== $importbuddy_pass_hash ) {
			if ( '' == $importbuddy_pass_hash ) {
				if ( ! isset( pb_backupbuddy::$options ) ) {
					pb_backupbuddy::load();
				}
				$importbuddy_pass_hash = pb_backupbuddy::$options['importbuddy_pass_hash'];
			}

			if ( '' == $importbuddy_pass_hash ) {
				$message = 'Warning #9032 - You have not set an Importer password on the Solid Backups Settings page. '. "\n" . '---------- 	-------	------	A copy of the importbuddy.php file needed to restore your backup is included in Full backup zip files for convenience. ' . "\n" . '---------- 	-------	------	Since a password is not a random one has been applied to this importbuddy.php so you will need to use the "Forgot Password" option if using it to restore.';
				pb_backupbuddy::status( 'warning', $message );
				$importbuddy_pass_hash = pb_backupbuddy::random_string( 45 ); // Random long hash for dummy password.
			}
		}

		pb_backupbuddy::status( 'details', 'Loading importbuddy core file into memory.' );
		$output = file_get_contents( pb_backupbuddy::plugin_path() . '/_importbuddy/_importbuddy.php' );

		if ( null !== $importbuddy_pass_hash && '' != $importbuddy_pass_hash ) {
			$output = preg_replace( '/#PASSWORD#/', $importbuddy_pass_hash, $output, 1 ); // Only replaces first instance.
		}

		$version_string = pb_backupbuddy::settings( 'version' ) . ' (downloaded ' . date( DATE_W3C ) . ')';

		// If on DEV system (.git dir exists) then append some details on current.
		if ( defined( 'BACKUPBUDDY_DEV' ) && true === BACKUPBUDDY_DEV ) {
			if ( @file_exists( pb_backupbuddy::plugin_path() . '/.git/logs/HEAD' ) ) {
				$commit_log      = escapeshellarg( pb_backupbuddy::plugin_path() . '/.git/logs/HEAD' );
				$commit_line     = str_replace( '\'', '`', exec( "tail -n 1 {$commit_log}" ) );
				$version_string .= ' <span style="font-size: 8px;">[DEV: ' . $commit_line . ']</span>';
			}
		}

		$output = preg_replace( '/#VERSION#/', $version_string, $output, 2 ); // Only replaces first TWO instances.

		$ib_dir   = 'importbuddy/';

		// PACK IMPORTBUDDY.
		$_packdata = array( // NO TRAILING OR PRECEEDING SLASHES!

			'_importbuddy/importbuddy'               => 'importbuddy',
			'classes/_migrate_database.php'          => $ib_dir . 'classes/_migrate_database.php',
			'classes/core.php'                       => $ib_dir . 'classes/core.php',
			'classes/import.php'                     => $ib_dir . 'classes/import.php',
			'classes/restore.php'                    => $ib_dir . 'classes/restore.php',
			'classes/_restoreFiles.php'              => $ib_dir . 'classes/_restoreFiles.php',
			'classes/remote_api.php'                 => $ib_dir . 'classes/remote_api.php',

			'lib/dbreplace'                          => $ib_dir . 'lib/dbreplace',
			'lib/dbimport'                           => $ib_dir . 'lib/dbimport',
			'lib/commandbuddy'                       => $ib_dir . 'lib/commandbuddy',
			'lib/pclzip'                             => $ib_dir . 'lib/pclzip',
			'lib/zipbuddy'                           => $ib_dir . 'lib/zipbuddy',
			'lib/mysqlbuddy'                         => $ib_dir . 'lib/mysqlbuddy',
			'lib/textreplacebuddy'                   => $ib_dir . 'lib/textreplacebuddy',
			'lib/cpanel'                             => $ib_dir . 'lib/cpanel',

			'pluginbuddy'                            => $ib_dir . 'pluginbuddy',

			'controllers/pages/server_info'          => $ib_dir . 'controllers/pages/server_info',
			'controllers/pages/diagnostics.php'      => $ib_dir . 'controllers/pages/diagnostics.php',

			// Stash
			// 'destinations/stash3/init.php'             => $ib_dir . 'lib/stash3/init.php',
			'destinations/stash3/class.itx_helper2.php'   => $ib_dir . 'lib/stash3/class.itx_helper2.php',
			'destinations/stash3/class.itcred.php'        => $ib_dir . 'lib/stash3/class.itcred.php',
			'destinations/stash3/class-phpass.php'        => $ib_dir . 'lib/stash3/class-phpass.php',
			'destinations/_s3lib/aws-sdk/lib/requestcore' => $ib_dir . 'lib/requestcore', // Used for dB operations in importbuddy.
		);

		pb_backupbuddy::status( 'details', 'Loading each file into memory for writing master importbuddy file.' );

		$output .= "\n/*\n###PACKDATA,BEGIN\n";
		foreach ( $_packdata as $pack_source => $pack_destination ) {
			$pack_source = '/' . $pack_source;
			if ( is_dir( pb_backupbuddy::plugin_path() . $pack_source ) ) {
				$files = pb_backupbuddy::$filesystem->deepscandir( pb_backupbuddy::plugin_path() . $pack_source );
			} else {
				$files = array( pb_backupbuddy::plugin_path() . $pack_source );
			}
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					$source      = str_replace( pb_backupbuddy::plugin_path(), '', $file );
					$destination = $pack_destination . substr( $source, strlen( $pack_source ) );
					$output     .= "###PACKDATA,FILE_START,{$source},{$destination}\n";
					$output     .= base64_encode( file_get_contents( $file ) );
					$output     .= "\n";
					$output     .= "###PACKDATA,FILE_END,{$source},{$destination}\n";
				}
			}
		}
		$output .= "###PACKDATA,END\n*/";
		$output .= "\n\n\n\n\n\n\n\n\n\n";

		if ( true === $return_not_echo ) {
			return $output;
		}

		if ( '' == $output_file ) { // No file so output to browser.
			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: text/plain; name=importbuddy.php' );
			header( 'Content-Disposition: attachment; filename=importbuddy.php' );
			header( 'Expires: 0' );
			header( 'Content-Length: ' . strlen( $output ) );

			pb_backupbuddy::flush();
			echo $output;
			pb_backupbuddy::flush();

			// BE SURE TO die() AFTER THIS AND NOT OUTPUT TO BROWSER!
		} else { // Write to file.
			pb_backupbuddy::status( 'details', 'Writing importbuddy master file to disk.' );
			if ( false === file_put_contents( $output_file, $output ) ) {
				pb_backupbuddy::status( 'error', 'Error #483894: Unable to write to file `' . esc_html( $output_file ) . '`.' );
				return false;
			}

			return true;
		}

	}

	/**
	 * Leading & trailing slash. Does not create if it does not exist.
	 *
	 * @return string  Media root path.
	 */
	public static function get_media_root() {
		$uploads_dirs = wp_upload_dir( null, false );
		return rtrim( $uploads_dirs['basedir'], '/' ) . '/';
	}

	/**
	 * Leading & trailing slash.
	 *
	 * @return string  Themes root path.
	 */
	public static function get_themes_root() {
		return rtrim( dirname( get_template_directory() ), '/' ) . '/';
	}

	/**
	 * Leading & trailing slash.
	 *
	 * @return string  Plugins root path.
	 */
	public static function get_plugins_root() {
		return rtrim( WP_PLUGIN_DIR, '/' ) . '/';
	}

	/**
	 * Returns array of paths to inactive plugins.
	 *
	 * @return array  Paths to inactive plugins.
	 */
	public static function get_inactive_plugins() {
		$inactive_plugins = array();

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();

		foreach ( $all_plugins as $plugin_path => $plugin ) {
			if ( ! $plugin_path ) {
				continue;
			}

			if ( is_plugin_active( $plugin_path ) === false ) {
				if ( dirname( self::get_plugins_root() . $plugin_path ) == WP_PLUGIN_DIR ) {
					// Exclude the full path (like plugins/hello.php).
					$inactive_plugins[] = self::get_plugins_root() . $plugin_path;
				} else {
					// Exclude the parent folder (like plugins/akismet).
					$inactive_plugins[] = dirname( self::get_plugins_root() . $plugin_path );
				}
			}
		}

		return $inactive_plugins;
	}

	/**
	 * Return a nice human string for a specified backup type.
	 *
	 * @param string $type  Type of backup. Eg. full, db, files.
	 *
	 * @return string  Pretty name for type of backup. Eg. Full, Database, Files.
	 */
	public static function pretty_backup_type( $type ) {
		$types = array(
			'full'    => __( 'Complete Backup', 'it-l10n-backupbuddy' ),
			'db'      => __( 'Database Only', 'it-l10n-backupbuddy' ),
			'files'   => __( 'Custom', 'it-l10n-backupbuddy' ),
			'themes'  => __( 'Themes Only', 'it-l10n-backupbuddy' ),
			'plugins' => __( 'Plugins Only', 'it-l10n-backupbuddy' ),
			'media'   => __( 'Media Only', 'it-l10n-backupbuddy' ),
		);

		if ( isset( $types[ strtolower( $type ) ] ) ) {
			return $types[ strtolower( $type ) ];
		} else {
			return $type;
		}
	}

	/**
	 * Take a destination type slug and change it into a user-friendly display of the destination type.
	 *
	 * @param string $type  Internal destination slug. (Ex: s3).
	 *
	 * @return string  Friendly destination title. Eg: Amazon S3
	 */
	public static function pretty_destination_type( $type ) {
		if ( 'rackspace' === $type ) {
			return __( 'Rackspace', 'it-l10n-backupbuddy' );
		} elseif ( 'email' === $type ) {
			return __( 'Email', 'it-l10n-backupbuddy' );
		} elseif ( 's32' === $type ) {
			return __( 'Amazon S3 v2', 'it-l10n-backupbuddy' );
		} elseif ( 's33' === $type ) {
			return __( 'Amazon S3 v3', 'it-l10n-backupbuddy' );
		} elseif ( 'ftp' === $type ) {
			return __( 'FTP', 'it-l10n-backupbuddy' );
		} elseif ( 'stash2' === $type ) {
			return __( 'Solid Backups Stash v2', 'it-l10n-backupbuddy' );
		} elseif ( 'stash3' === $type ) {
			return __( 'Solid Backups Stash v3', 'it-l10n-backupbuddy' );
		} elseif ( 'sftp' === $type ) {
			return __( 'sFTP', 'it-l10n-backupbuddy' );
		} elseif ( 'dropbox2' === $type ) {
			return __( 'Dropbox v2', 'it-l10n-backupbuddy' );
		} elseif ( 'dropbox3' === $type ) {
			return __( 'Dropbox v3', 'it-l10n-backupbuddy' );
		} elseif ( 'gdrive' === $type ) {
			return __( 'Google Drive', 'it-l10n-backupbuddy' );
		} elseif ( 'site' === $type ) {
			return __( 'Solid Backups Deployment', 'it-l10n-backupbuddy' );
		} else {
			return ucwords( str_replace( '_', ' ', $type ) );
		}
	}

	/**
	 * Build directory tree for use with the "icicle" javascript library for the graphical directory display on Diagnostics page.
	 *
	 * @param string $dir          Directory.
	 * @param string $base         Base.
	 * @param string $icicle_json  JSON to build.
	 * @param int    $max_depth    Maximum depth of tree to display. Note that deeper depths are still traversed for size calculations. Default: 10.
	 * @param int    $depth_count  Depth count. Default: 0.
	 * @param bool   $is_root      Is root? Default: true.
	 *
	 * @return array  Icicle JSON and directory size.
	 */
	public static function build_icicle( $dir, $base, $icicle_json, $max_depth = 10, $depth_count = 0, $is_root = true ) {
		$bg_color = '005282';

		$depth_count++;
		$bg_color = dechex( hexdec( $bg_color ) - ( $depth_count * 15 ) );

		$icicle_json = '{' . "\n";

		$dir_name = $dir;
		$dir_name = str_replace( ABSPATH, '', $dir );
		$dir_name = str_replace( '\\', '/', $dir_name );

		$dir_size     = 0;
		$sub          = opendir( $dir );
		$has_children = false;
		while ( $file = readdir( $sub ) ) {
			if ( '.' === $file || '..' === $file ) {
				continue; // Next loop.
			} elseif ( is_dir( $dir . '/' . $file ) ) {
				$dir_array = '';
				$response  = self::build_icicle( $dir . '/' . $file, $base, $dir_array, $max_depth, $depth_count, false );
				if ( $max_depth - 1 > 0 || -1 == $max_depth ) { // Only adds to the visual tree if depth isn't exceeded.
					if ( $max_depth > 0 ) {
						$max_depth--;
					}

					if ( false === $has_children ) { // first loop add children section.
						$icicle_json .= '"children": [' . "\n";
					} else {
						$icicle_json .= ',';
					}
					$icicle_json .= $response[0];

					$has_children = true;
				}
				$dir_size += $response[1];
				unset( $response );
				unset( $file );

			} else {
				$stats     = stat( $dir . '/' . $file );
				$dir_size += $stats['size'];
				unset( $file );
			}
		}
		closedir( $sub );
		unset( $sub );

		if ( true === $has_children ) {
			$icicle_json .= ' ]' . "\n";
			$icicle_json .= ',';
		}

		$icicle_json .= '"id": "node_' . str_replace( '/', ':', $dir_name ) . ': ^' . str_replace( ' ', '~', pb_backupbuddy::$format->file_size( $dir_size ) ) . '"' . "\n";

		$dir_name = str_replace( '/', '', strrchr( $dir_name, '/' ) );
		if ( '' == $dir_name ) { // Set root to be /.
			$dir_name = '/';
		}
		$icicle_json .= ', "name": "' . $dir_name . ' (' . pb_backupbuddy::$format->file_size( $dir_size ) . ')"' . "\n";

		$icicle_json .= ',"data": { "$dim": ' . ( $dir_size + 10 ) . ', "$color": "#' . str_pad( $bg_color, 6, '0', STR_PAD_LEFT ) . '" }' . "\n";
		$icicle_json .= '}';

		return array( $icicle_json, $dir_size );
	}

	/**
	 * Preflight Check
	 *
	 * @return array  Tests and their results.
	 */
	public static function preflight_check() {
		$tests = array();

		// MULTISITE BETA WARNING.
		if ( is_multisite() && backupbuddy_core::is_network_activated() && ! defined( 'PB_DEMO_MODE' ) ) { // Multisite installation.
			$instruction = defined( 'PB_BACKUPBUDDY_MULTISITE_EXPERIMENT' ) && true === PB_BACKUPBUDDY_MULTISITE_EXPERIMENT ? '' : 'To enable experimental Solid Backups Multisite functionality you must add the following line to your wp-config.php file: <b>define( \'PB_BACKUPBUDDY_MULTISITE_EXPERIMENT\', true );</b>';
			$tests[] = array(
				'test'    => 'multisite_beta',
				'success' => false,
				'message' => 'WARNING: Solid Backups Multisite functionality is EXPERIMENTAL and NOT officially supported. Multiple issues are known. Usage of it is at your own risk and should not be relied upon. Standalone WordPress sites are suggested. You may use the "Export" feature to export your subsites into standalone WordPress sites.' . $instruction,
			);
		} // end network-activated multisite.

		// LOOPBACKS TEST.
		$loopback_response = self::loopback_test();
		if ( true === $loopback_response ) {
			$success = true;
			$message = '';
		} else { // Failed.
			$success = false;
			if ( defined( 'ALTERNATE_WP_CRON' ) && true == ALTERNATE_WP_CRON ) {
				$message = '<p>' . __( 'Running in Alternate WordPress Cron mode. HTTP Loopback Connections are not enabled on this server but you have overridden this in the wp-config.php file (this is a good thing).', 'it-l10n-backupbuddy' ) . '</p><a href="https://go.solidwp.com/http-loopback" target="_blank">' . __( 'Additional Information Here', 'it-l10n-backupbuddy' ) . '</a>.';
			} else {
				$message  = '<p>' . __( 'HTTP Loopback Connections are not enabled on this server or are not functioning properly. You may encounter stalled, significantly delayed backups, or other difficulties. See details in the box below. This may be caused by a conflicting plugin such as a caching plugin.', 'it-l10n-backupbuddy' ) . '</p><a href="https://go.solidwp.com/http-loopback-connection" target="_blank">' . __( 'Click for instructions on how to resolve this issue.', 'it-l10n-backupbuddy' ) . '</a>';
				$message .= '<div><strong>Details:</strong> <textarea style="height: 50px; width: 100%;">' . $loopback_response . '</textarea></div>';
			}
		}
		$tests[] = array(
			'test'    => 'loopbacks',
			'success' => $success,
			'message' => $message,
		);

		// CRONBACKS TEST.
		$cronback_response = self::cronback_test();
		if ( true === $cronback_response ) {
			$success = true;
			$message = '';
		} else { // Failed.
			$success = false;
			if ( defined( 'ALTERNATE_WP_CRON' ) && true == ALTERNATE_WP_CRON ) {
				$message = '<p>' . __( 'Running in Alternate WordPress Cron mode. HTTP cronback Connections are not enabled on this server but you have overridden this in the wp-config.php file (this is a good thing).', 'it-l10n-backupbuddy' ) . '</p><a href="https://go.solidwp.com/http-loopback-connection-disabled" target="_blank">' . __( 'Additional Information Here', 'it-l10n-backupbuddy' ) . '</a>.';
			} else {
				$message  = '<p>' . __( 'HTTP loopback connections are not enabled to the wp-cron.php on this server or are not functioning properly. You may encounter stalled, significantly delayed backups, or other difficulties. See details in the box below. This may be caused by a conflicting plugin such as a caching plugin.', 'it-l10n-backupbuddy' ) . '</p><a href="https://go.solidwp.com/http-loopback-connection-" target="_blank">' . __( 'Click for instructions on how to resolve this issue.', 'it-l10n-backupbuddy' ) . '</a>';
				$message .= '<div><strong>Details:</strong> <textarea style="height: 50px; width: 100%;">' . $cronback_response . '</textarea></div>';
			}
		}
		$tests[] = array(
			'test'    => 'cronbacks',
			'success' => $success,
			'message' => $message,
		);

		// POSSIBLE CACHING PLUGIN CONFLICT WARNING.
		$success       = true;
		$message       = '';
		$found_plugins = array();
		if ( ! is_multisite() ) {
			$active_plugins = serialize( get_option( 'active_plugins' ) );
			foreach ( self::$warn_plugins as $warn_plugin => $warn_plugin_title ) {
				if ( false !== strpos( $active_plugins, $warn_plugin ) ) { // Plugin active.
					$found_plugins[] = $warn_plugin_title;
					$success         = false;
				}
			}
		}
		if ( count( $found_plugins ) > 0 ) {
			$message  = '<p>' . __( 'One or more caching or security plugins known to possibly cause problems was detected as activated. Some caching or security plugin configurations may possibly cache or interfere with backup processes or WordPress cron. If you encounter problems clear the caching plugin\'s cache or check security settings (deactivating the plugin may help) to troubleshoot.', 'it-l10n-backupbuddy' ) . '</p>';
			$message .= '<p>' . __( 'Activated caching plugins detected:', 'it-l10n-backupbuddy' ) . '</p>';
			$message .= implode( ', ', $found_plugins );
			$message .= '.';
		}
		$tests[] = array(
			'test'    => 'loopbacks',
			'success' => $success,
			'message' => $message,
		);

		// WordPress IN SUBDIRECTORIES TEST.
		$wordpress_locations = self::get_wordpress_locations();
		if ( count( $wordpress_locations ) > 0 ) {
			$success = false;
			$message = '<p>' . __( 'WordPress may have been detected in one or more subdirectories. Backing up multiple instances of WordPress may result in server timeouts due to increased backup time. You may exclude WordPress directories via the Settings page. Detected non-excluded locations:', 'it-l10n-backupbuddy' ) . '</p>' . implode( ', ', $wordpress_locations );
		} else {
			$success = true;
			$message = '';
		}
		$tests[] = array(
			'test'    => 'wordpress_subdirectories',
			'success' => $success,
			'message' => $message,
		);

		// Log file directory writable for status logging.
		$status_directory = backupbuddy_core::getLogDirectory();
		if ( ! file_exists( $status_directory ) ) {
			if ( false === pb_backupbuddy::anti_directory_browsing( $status_directory, false ) ) {
				$success = false;
				$message = 'The status log file directory `' . esc_html( $status_directory ) . '` is not creatable or permissions prevent access. Verify permissions of it and/or its parent directory. Backup status information will be unavailable until this is resolved.';
			}
		}
		if ( ! is_writable( $status_directory ) ) {
			$success = false;
			$message = 'The status log file directory `' . esc_html( $status_directory ) . '` is not writable. Please verify permissions before creating a backup. Backup status information will be unavailable until this is resolved.';
		} else {
			$success = true;
			$message = '';
		}
		$tests[] = array(
			'test'    => 'status_directory_writable',
			'success' => $success,
			'message' => $message,
		);

		// CHECK ZIP AVAILABILITY.
		require_once pb_backupbuddy::plugin_path() . '/lib/zipbuddy/zipbuddy.php';

		if ( ! isset( pb_backupbuddy::$classes['zipbuddy'] ) ) {
			pb_backupbuddy::$classes['zipbuddy'] = new pluginbuddy_zipbuddy( backupbuddy_core::getBackupDirectory() );
		}

		/***** BEGIN LOOKING FOR UNFINISHED RECENT BACKUPS */
		if ( '' != pb_backupbuddy::$options['last_backup_serial'] ) {
			$last_backup_fileoptions = backupbuddy_core::getLogDirectory() . 'fileoptions/' . pb_backupbuddy::$options['last_backup_serial'] . '.txt';
			if ( file_exists( $last_backup_fileoptions ) ) {
				pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #32...' );
				require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
				$backup_options = new pb_backupbuddy_fileoptions( $last_backup_fileoptions, true );
				$result         = $backup_options->is_ok();
				if ( true !== $result || ! isset( $backup_options->options['updated_time'] ) ) {
					// NOTE: If this files during a backup it may try to read the fileoptions file too early due to the last_backup_serial being set. Suppressing errors for now.
					pb_backupbuddy::status( 'details', 'Unable to retrieve fileoptions file (this is normal if a backup is currently in process & may be ignored) `' . esc_html( backupbuddy_core::getLogDirectory() ) . 'fileoptions/' . pb_backupbuddy::$options['last_backup_serial'] . '.txt' . '`. Err 54478236765. Details: `' . $result . '`.' );
				} else {
					if ( $backup_options->options['updated_time'] < 180 ) { // Been less than 3min since last backup.

						if ( ! empty( $backup_options->options['steps'] ) ) { // Look for incomplete steps.
							$found_unfinished = false;
							foreach ( (array) $backup_options->options['steps'] as $step ) {
								if ( '0' == $step['finish_time'] ) { // Found an unfinished step.
									$found_unfinished = true;
									break;
								}
							} // end foreach.

							if ( true === $found_unfinished ) {
								$tests[] = array(
									'test'    => 'recent_backup',
									'success' => false,
									'message' => '<p>' . __( 'A backup was recently started and reports unfinished steps. You should wait unless you are sure the previous backup has completed or failed to avoid placing a heavy load on your server.', 'it-l10n-backupbuddy' ) . '<br>' .
										' Last updated: ' . pb_backupbuddy::$format->date( $backup_options->options['updated_time'] ) . '; <br>' .
										' Serial: ' . pb_backupbuddy::$options['last_backup_serial'] . '</p>',
								);
							} // end $found_unfinished === true.
						} // end if.
					}
				}
			}
		}
		/***** END LOOKING FOR UNFINISHED RECENT BACKUPS */

		/***** BEGIN LOOKING FOR BACKUP FILES IN SITE ROOT */
		$files = glob( ABSPATH . 'backup-*.zip' );
		if ( ! is_array( $files ) || empty( $files ) ) {
			$files = array();
		}

		$files2 = glob( ABSPATH . 'snapshot-*.zip' );
		if ( ! is_array( $files2 ) || empty( $files2 ) ) {
			$files2 = array();
		}

		$files = array_merge( $files, $files2 );
		foreach ( $files as &$file ) {
			$file = basename( $file );
		}

		if ( count( $files ) > 0 ) {
			$files_string = implode( ', ', $files );
			$tests[]      = array(
				'test'    => 'root_backups-' . $files_string,
				'success' => false,
				'message' => 'One or more backup files, `' . $files_string . '` was found in the root directory of this site. This may be leftover from a recent restore. You should usually remove backup files from the site root for security.',
			);
		}
		/***** END LOOKING FOR BACKUP FILES IN SITE ROOT */

		if ( ! is_writable( backupbuddy_core::getBackupDirectory() ) && false === backupbuddy_is_restoring() ) {
			$tests[] = array(
				'test'    => 'backup_dir_permissions',
				'success' => false,
				'message' => 'Invalid backup directory permissions. Verify the directory `' . esc_attr( backupbuddy_core::getBackupDirectory() ) . '` is writable.',
			);
		}

		return $tests;

	}

	/**
	 * Connects back to same site via AJAX call to an AJAX slug that has NOT been registered.
	 * WordPress AJAX returns a -1 (or 0 in newer version?) for these. Also not logged into
	 * admin when connecting back. Checks to see if body contains -1 / 0. If loopbacks are not
	 * enabled then will fail connecting or do something else.
	 *
	 * @return bool  True on success, string error message otherwise.
	 */
	public static function loopback_test() {
		$loopback_url = admin_url( 'admin-ajax.php?action=itbub_http_loop_back_test' ) . '&serial=' . pb_backupbuddy::$options['log_serial'];
		pb_backupbuddy::status( 'details', 'Testing loopback connections by connecting back to site at the URL: `' . $loopback_url . '`. It should display simply "0" or "-1" in the body.' );

		$response = wp_remote_get(
			$loopback_url,
			array(
				'method'      => 'GET',
				'timeout'     => 8, // X second delay. A loopback should be very fast.
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => array(),
				'body'        => null,
				'cookies'     => array(),
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
			)
		);

		global $backupbuddy_loopback_details;
		if ( is_wp_error( $response ) ) { // Loopback failed. Some kind of error.
			$error = $response->get_error_message();
			$error = 'Error #9038: Loopback test error: `' . $error . '`. URL: `' . $loopback_url . '`. If you need to contact your web host, tell them that when PHP tries to connect back to the site at the URL `' . $loopback_url . '` via curl (or other fallback connection method built into WordPress) that it gets the error `' . $error . '`. This means that WordPress\' built-in simulated cron system cannot function properly, breaking some WordPress features & subsequently some plugins. There may be a problem with the server configuration (eg local DNS problems, mod_security, etc) preventing connections from working properly.';
			pb_backupbuddy::status( 'error', $error );
			$backupbuddy_loopback_details = 'Error: ' . $error;
			return $error;
		} else {
			if ( in_array( $response['body'], array( '-1', '0' ) ) ) { // Loopback succeeded.
				pb_backupbuddy::status( 'details', 'HTTP Loopback test success. Returned `' . $response['body'] . '`.' );
				$backupbuddy_loopback_details = 'Returned: `' . $response['body'] . '` with code `' . $response['response']['code'] . ' ' . $response['response']['message'] . '`.';
				return true;
			} else { // Loopback failed.
				$error = 'Warning #9038: Connected to server but unexpected output: `' . htmlentities( $response['body'] . '`. Code: `' . $response['response']['code'] . ' ' . $response['response']['message'] . ' ' . $response['response']['message'] . '`.' );
				pb_backupbuddy::status( 'warning', $error );
				$backupbuddy_loopback_details = $error;
				return $error;
			}
		}
	}

	/**
	 * Tests if wp-cron.php is accessible by the server
	 * Optionally tests if cron actually fired
	 *
	 * @param bool $confirm_cron_fired  If true, will set a test cron and confirm it fired.
	 *
	 * @return string|bool  Error message or true if worked.
	 */
	public static function cronback_test( $confirm_cron_fired = false ) {
		global $wpdb;
		global $backupbuddy_cronback_details;
		$option = 'itbub_doing_cron_test';
		$hook   = 'itbub_cron_test';

		// Do our preparation for things that apply whatever we are doing - we will always make a loopback
		// access but for the passive test we will not be trying to trigger the execution of our own scheduled
		// task whereas with the active test that will be the intention.
		$gmt_time      = microtime( true );
		$doing_wp_cron = sprintf( '%.22F', $gmt_time );
		$cron_url      = add_query_arg( 'doing_wp_cron', $doing_wp_cron, site_url( 'wp-cron.php' ) );
		$cron_request  = array(
			'url'  => $cron_url,
			'key'  => $doing_wp_cron,
			'args' => array(
				'timeout'   => 4,
				'blocking'  => true,
				/** This filter is documented in wp-includes/class-http.php */
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			),
		);

		pb_backupbuddy::status( 'details', 'Testing wp-cron.php loopback connections by connecting back to site at the URL: `' . $cron_url . '`. Expect 200 OK response.' );

		// Delete Previous temp crons in the event they weren't cleared... even if we aren't flagged to test again.
		wp_clear_scheduled_hook( $hook );

		// Make sure we delete any existing option in the event it wasn't deleted... even if we aren't flagged to test again.
		$result = $wpdb->delete( $wpdb->options, array( 'option_name' => $option ) );

		if ( $confirm_cron_fired ) {
			// Problem: the site access to this page may already have triggered cron if there was any due task.
			// We don't really want to tread on its toes _but_ also we might have problems scheduling and running
			// our task at the same time as the due tasks are being executed because of possible race conditions.
			// NOTE: We may need to force bypass any caches to get most up to date value?
			$lock = get_transient( 'doing_cron' );

			// If lock has already been set then the lock should be non-zero _but_ we also want to account for the
			// lock not having been released due to some failure so just check with a guard time and if the lock
			// was set more than 10s ago then we'll steal it regardless.
			// Note that if a task is actually still being run then it will continue to run but when that task has
			// finished the wp-cron.php "instance" running will find it has lost the lock and will not try and run
			// anything further which is what we want as this shuld stop simultaneous cron array changes and consequent
			// race conditions.
			if ( ( $lock + 10 ) > $gmt_time ) {
				// We are going to do only the "passive" access test
				// Note that the $doing_wp_cron value we set above is still usuable but essentially irrelevant
				// as it is no longer needed to set as the transient to match the key in the access.
				$confirm_cron_fired = false;

				// Warn that we are not running the active test - this gets pre-pended to later details.
				$backupbuddy_cronback_details = 'Running passive access test only - active cron test could not be run due to other apparent cron activity - try again later.' . PHP_EOL;
			} else {
				// Over 10s since lock set - steal the lock which should stop any further due tasks being run.
				set_transient( 'doing_cron', $doing_wp_cron );

				as_schedule_single_action( time(), $hook, array(), '', true );
			}
		}

		// Make the actual loopback access that was initialised above.
		// This is passive or active dependent on whether we scheduled a test task above.
		$response = wp_remote_post( $cron_request['url'], $cron_request['args'] );

		if ( is_wp_error( $response ) ) {

			// WordPress classifies the response as an error.
			$error_msg = $response->get_error_message();
			$error     = true == $confirm_cron_fired ? 'Active ' : 'Passive ';
			$error    .= 'wp-cron.php loopback test failure: `' . $error_msg . '`. URL: `' . $cron_url . '`. If you need to contact your web host, tell them that when PHP tries to connect back to the site at the URL `' . $cron_url . '` via curl (or other fallback connection method built into WordPress) that it gets the error `' . $error_msg . '`. This means that WordPress\' built-in simulated cron system cannot function properly, breaking some WordPress features & subsequently some plugins. There may be a problem with the server configuration (eg local DNS problems, mod_security, etc) preventing connections from working properly.';
			pb_backupbuddy::status( 'error', $error );
			$backupbuddy_cronback_details = $error;
			return $error;

		} elseif ( $confirm_cron_fired ) {

			// WordPress classifies the response a "ok" and we are making an active test.
			$cronstamp   = 0;
			$retry_count = 2; // Ok - we don't like magic numbers but these are just provisional...
			$retry_delay = 5;

			// Try and get the itbub_doing_cron_test option value (timestamp when the option was created)
			// In some cases it may take a while for the cron task to be executed so allow a couple of retries -
			// this will slow the page load down but _only_ in the cases where this is required which should not
			// be common?
			$query = $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option );
			$row   = $wpdb->get_row( $query, OBJECT );
			while ( is_null( $row ) && ( 0 < $retry_count-- ) ) {
				sleep( $retry_delay );
				$row = $wpdb->get_row( $query, OBJECT );
			}

			// If we retrieve option as an object we should have a good value - the timestamp when the task was run
			// Otherwise we couldn't get an option value so drop through with default cronstamp value.
			if ( is_object( $row ) ) {
				$cronstamp = maybe_unserialize( $row->option_value );
				// Always delete the option here as we no longer need it.
				$result = $wpdb->delete( $wpdb->options, array( 'option_name' => $option ) );
			}

			$now = microtime( true );
			if ( $cronstamp && ( abs( $now - $cronstamp ) < 15 ) ) {

				// If here, the cron was executed and the time difference (also allowing for possible time sync
				// differences on different servers in the case of cloud shared hosting) is < 15s.
				pb_backupbuddy::status( 'details', 'Active wp-cron.php loopback test success. Returned `' . $response['body'] . '` with code `' . $response['response']['code'] . ' ' . $response['response']['message'] . '`.' );
				$backupbuddy_cronback_details = 'Active test success' . PHP_EOL . 'Returned: `' . $response['body'] . '` with code `' . $response['response']['code'] . ' ' . $response['response']['message'] . '`.';
				return true;

			} else {

				// If we made it here, the script isn't blocked, but the crons aren't being executed.
				$error  = 'Active wp-cron.php loopback test failure. Your wp-cron.php appears to be reachable by the server but something is preventing it from executing code properly - Coming Soon plugins and Basic Authentication often break this. ';
				$error .= 'This may be a temporary site/server/hosting issue but if it persists you will need to investigate further. ';
				$error .= 'Returned: `' . $response['body'] . '` with code `' . $response['response']['code'] . ' ' . $response['response']['message'] . '`.';
				pb_backupbuddy::status( 'error', $error );
				$backupbuddy_cronback_details = $error;
				return $error;

			}
		} else {

			// WordPress classifies the response as "ok"
			// No access error but we're not checking for actual firing of the cron, so return success (like on backup page).
			pb_backupbuddy::status( 'details', 'Passive wp-cron.php loopback test success. Returned `' . $response['body'] . '` with code `' . $response['response']['code'] . ' ' . $response['response']['message'] . '`.' );
			$backupbuddy_cronback_details .= 'Passive test success' . PHP_EOL . 'Returned: `' . $response['body'] . '` with code `' . $response['response']['code'] . ' ' . $response['response']['message'] . '`.';
			return true;

		}

	}

	/**
	 * Get an array of subdirectories where potential WordPress installations have been detected.
	 *
	 * @return array  Array of full paths, WITHOUT trailing slashes.
	 */
	public static function get_wordpress_locations() {
		$wordpress_locations = array();

		$files = glob( ABSPATH . '*/' );
		if ( ! is_array( $files ) || empty( $files ) ) {
			$files = array();
		}

		foreach ( $files as $file ) {
			if ( file_exists( $file . 'wp-config.php' ) ) {
				$wordpress_locations[] = rtrim( '/' . str_replace( ABSPATH, '', $file ), '/\\' );
			}
		}

		// Remove any excluded directories from showing up in this.
		$directory_exclusions = self::get_directory_exclusions( pb_backupbuddy::$options['profiles'][0] ); // default profile.
		$wordpress_locations  = array_diff( $wordpress_locations, $directory_exclusions );

		return $wordpress_locations;
	}

	/**
	 * Returns an array with the site size and the site size sans exclusions. Saves updates stats in options.
	 *
	 * @return array  Index 0: site size; Index 1: site size sans excluded files/dirs.; Index 2: Total number of objects (files+folders); Index 3: Total objects sans excluded files/folders.
	 */
	public static function get_site_size() {
		$exclusions = backupbuddy_core::get_directory_exclusions( pb_backupbuddy::$options['profiles'][0] );
		$dir_array  = array();
		$result     = pb_backupbuddy::$filesystem->dir_size_map( ABSPATH, ABSPATH, $exclusions, $dir_array );
		unset( $dir_array ); // Free this large chunk of memory.

		$total_size             = $result[0];
		$total_size_excluded    = $result[1];
		$total_objects          = $result[2];
		$total_objects_excluded = $result[3];

		pb_backupbuddy::$options['stats']['site_size']             = $total_size;
		pb_backupbuddy::$options['stats']['site_size_excluded']    = $total_size_excluded;
		pb_backupbuddy::$options['stats']['site_objects']          = $total_objects;
		pb_backupbuddy::$options['stats']['site_objects_excluded'] = $total_objects_excluded;
		pb_backupbuddy::$options['stats']['site_size_updated']     = time();
		pb_backupbuddy::save();

		return array( $total_size, $total_size_excluded, $total_objects, $total_objects_excluded );
	}

	/**
	 * Return array of database size, database sans exclusions.
	 *
	 * @param int $profile_id  ID of Backup Profile.
	 *
	 * @return array  Index 0: db size, Index 1: db size sans exclusions.
	 */
	public static function get_database_size( $profile_id = 0 ) {
		global $wpdb;
		$prefix        = $wpdb->prefix;
		$prefix_length = strlen( $wpdb->prefix );

		$additional_includes = backupbuddy_core::get_mysqldump_additional( 'includes', pb_backupbuddy::$options['profiles'][ $profile_id ] );
		$additional_excludes = backupbuddy_core::get_mysqldump_additional( 'excludes', pb_backupbuddy::$options['profiles'][ $profile_id ] );

		$total_size                 = 0;
		$total_size_with_exclusions = 0;
		$rows                       = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );
		foreach ( $rows as $row ) {
			$excluded = true; // Default.

			// TABLE STATUS.
			$rowsb = $wpdb->get_results( "CHECK TABLE `{$row['Name']}`", ARRAY_A );
			foreach ( $rowsb as $rowb ) {
				if ( 'status' == $rowb['Msg_type'] ) {
					$status = $rowb['Msg_text'];
				}
			}
			unset( $rowsb );

			// Table size.
			$size        = ( $row['Data_length'] + $row['Index_length'] );
			$total_size += $size;

			// Handle exclusions.
			if ( 0 == pb_backupbuddy::$options['profiles'][ $profile_id ]['backup_nonwp_tables'] ) { // Only matching prefix.
				if ( substr( $row['Name'], 0, $prefix_length ) == $prefix || in_array( $row['Name'], $additional_includes ) ) {
					if ( ! in_array( $row['Name'], $additional_excludes ) ) {
						$total_size_with_exclusions += $size;
						$excluded                    = false;
					}
				}
			} else { // All tables.
				if ( ! in_array( $row['Name'], $additional_excludes ) ) {
					$total_size_with_exclusions += $size;
					$excluded                    = false;
				}
			}
		}

		pb_backupbuddy::$options['stats']['db_size']          = $total_size;
		pb_backupbuddy::$options['stats']['db_size_excluded'] = $total_size_with_exclusions;
		pb_backupbuddy::$options['stats']['db_size_updated']  = time();
		pb_backupbuddy::save();

		unset( $rows );

		return array( $total_size, $total_size_with_exclusions );
	}


	/**
	 * Attempt to verify the database server is still alive and functioning.  If not, try to re-establish connection.
	 */
	public static function kick_db() {
		// Need to make sure the database connection is active. Sometimes it goes away during long bouts doing other things -- sigh.
		// This is not essential so use include and not require (suppress any warning).
		@include_once pb_backupbuddy::plugin_path() . '/lib/wpdbutils/wpdbutils.php';

		if ( class_exists( 'pluginbuddy_wpdbutils' ) ) {
			// This is the database object we want to use
			global $wpdb;

			// Get our helper object and let it use us to output status messages
			$dbhelper = new pluginbuddy_wpdbutils( $wpdb );

			// If we cannot kick the database into life then signal the error and return false which will stop the backup
			// Otherwise all is ok and we can just fall through and let the function return true
			if ( ! $dbhelper->kick() ) {
				pb_backupbuddy::status( 'error', __( 'Database Server has gone away, unable to update remote destination transfer status. This is most often caused by mysql running out of memory or timing out far too early. Please contact your host.', 'it-l10n-backupbuddy' ) );
			}
		} else {
			// Utils not available so cannot verify database connection status - just notify
			pb_backupbuddy::status( 'details', __( 'Database Server connection status unverified.', 'it-l10n-backupbuddy' ) );
		}

	}

	/**
	 * Verify existence and security of key directories. Result available via global $pb_backupbuddy_directory_verification with return value.
	 *
	 * @param bool $skip_temp_generation  Skips creation of temp directory.
	 *
	 * @return bool  True on success creating / verifying, else false.
	 */
	public static function verify_directories( $skip_temp_generation = false ) {

		$success = true;

		// Update backup directory if unable to write to the defined one.
		if ( ! @is_writable( backupbuddy_core::getBackupDirectory() ) ) {
			pb_backupbuddy::status( 'details', 'Backup directory invalid. Updating from `' . esc_html( backupbuddy_core::getBackupDirectory() ) . '` to default.' );
			pb_backupbuddy::$options['backup_directory'] = ''; // Reset to default (blank).
			pb_backupbuddy::save();
		}
		$response = pb_backupbuddy::anti_directory_browsing( backupbuddy_core::getBackupDirectory(), false );
		if ( false === $response ) {
			$success = false;
		}

		// Update log directory if unable to write to the defined one.
		if ( ! @is_writable( backupbuddy_core::getLogDirectory() ) ) {
			pb_backupbuddy::status( 'details', 'Log directory invalid. Updating from `' . esc_html( backupbuddy_core::getLogDirectory() ) . '` to default.' );
			pb_backupbuddy::$options['log_directory'] = ''; // Reset to default (blank).
			pb_backupbuddy::save();
		}
		pb_backupbuddy::anti_directory_browsing( backupbuddy_core::getLogDirectory(), false );
		if ( false === $response ) {
			$success = false;
		}

		// Update temp directory if unable to write to the defined one.
		if ( true !== $skip_temp_generation ) {
			if ( ! @is_writable( backupbuddy_core::getTempDirectory() ) ) {
				pb_backupbuddy::status( 'details', 'Temporary directory invalid. Updating from `' . esc_html( backupbuddy_core::getTempDirectory() ) . '` to default.' );
				pb_backupbuddy::$options['temp_directory'] = '';
				pb_backupbuddy::save();
			}
			pb_backupbuddy::anti_directory_browsing( backupbuddy_core::getTempDirectory(), false );
			if ( false === $response ) {
				$success = false;
			}
		}

		// If temp directory exists (should only be transient but just in case it is hanging around) make sure it's secured. BB will try to delete this directory but if it can't it will at least be checked to be secure.
		if ( file_exists( backupbuddy_core::getTempDirectory() ) ) {
			pb_backupbuddy::anti_directory_browsing( backupbuddy_core::getTempDirectory(), false );
		}

		global $pb_backupbuddy_directory_verification;
		$pb_backupbuddy_directory_verification = $success;
		return $success;

	}

	/**
	 * Get the next scheduled time for a given hook.
	 *
	 * This checks first for Action Scheduler Actions, then falls back to WP Cron.
	 *
	 * @param string  $method  Method name to check for.
	 * @param array   $args    Optional. Arguments to pass to the hook.
	 * @param string  $group   Optional. The action group.
	 *
	 * @return int|bool Timestamp of next scheduled event, or false if none.
	 */
	public static function next_scheduled( $method, $args = array( true ), $group = backupbuddy_constants::CRON_GROUP ) {
		$args = array( $method, $args );

		// Save a DB query if we know there are no scheduled actions.
		if (
			function_exists( 'as_has_scheduled_action' )
			&& as_has_scheduled_action( backupbuddy_constants::CRON_HOOK, $args, $group )
		) {
			return as_next_scheduled_action( backupbuddy_constants::CRON_HOOK, $args, $group );
		} elseif ( function_exists( 'as_has_scheduled_action' ) ) {
			return false;
		}

		return wp_next_scheduled( backupbuddy_constants::CRON_HOOK, $args );
	}

	/**
	 * Check if a scheduled event exists for a given hook.
	 *
	 * This checks first for Action Scheduler Actions, then falls back to WP Cron.
	 *
	 * @param string  $method       Method name to check for.
	 * @param array   $action_args  Optional. Arguments to pass to the hook.
	 * @param string  $group        Optional. The action group.
	 *
	 * @return int|bool Timestamp of next scheduled event, or false if none.
	 */
	public static function has_scheduled_event( $method, $action_args = array( true ), $group = backupbuddy_constants::CRON_GROUP ) {
		$args = array( $method, $action_args );

		if (
			function_exists( 'as_has_scheduled_action' )
			&& as_has_scheduled_action( backupbuddy_constants::CRON_HOOK, $args, $group )
		) {
			return true;
		}

		return ! empty( wp_next_scheduled( backupbuddy_constants::CRON_HOOK, $args ) );
	}

	public static function has_pending_scheduled_event( $group ) {
		$args = array(
			'hook' => backupbuddy_constants::CRON_HOOK,
			'status' => ActionScheduler_Store::STATUS_PENDING,
			'group' => $group,
		);

		if ( function_exists( 'as_get_scheduled_actions' ) ){
			return as_get_scheduled_actions( $args, 'ids' );
		}

		return false;
	}

	/**
	 * Schedule a single event.
	 *
	 * API to as_schedule_single_action() that also verifies that the schedule actually got created in WordPress.
	 * Sometimes the database rejects this update so we need to do actual verification.
	 *
	 * @param int     $time         Timestamp.
	 * @param string  $method       Class Method.
	 * @param array   $action_args  Optional. Args to include. It is highly recommended that this not be empty.
	 * @param string  $group        Optional. The action group.
	 *
	 * @return bool  True on verified schedule success, else false.
	 */
	public static function schedule_single_event( $time, $method, $action_args = array( true ), $group = backupbuddy_constants::CRON_GROUP ) {

		// Try scheduling.
		if ( ! empty(
			as_schedule_single_action(
				$time,
				backupbuddy_constants::CRON_HOOK,
				array(
					$method,
					$action_args,
				),
				$group
			)
		) ) {
			return true;
		}

		// If failed, sleep 1 then try again.
		sleep( backupbuddy_constants::SCHEDULE_RETRY_WAIT );
		if ( ! empty(
			as_schedule_single_action(
				$time,
				backupbuddy_constants::CRON_HOOK,
				array(
					$method,
					$action_args,
				),
				$group
			)
		) ) {
			return true;
		}

		// Try scheduling one last time.
		sleep( backupbuddy_constants::SCHEDULE_RETRY_WAIT );
		if ( ! empty(
			as_schedule_single_action(
				$time,
				backupbuddy_constants::CRON_HOOK,
				array(
					$method,
					$action_args,
				),
				$group
			)
		) ) {
			return true;
		}

		pb_backupbuddy::status(
			'error',
			sprintf(
				__( 'Warning %s: This may not be a fatal error. Ignore if process proceeds without other errors. WordPress reported success scheduling BUT as_next_scheduled_action() could NOT confirm schedule existence. A plugin may have prevented it (e.g. caching) or it is already scheduled (but could not be detected).', 'it-l10n-backupbuddy' ),
				'#34894934'
			)
		);

		return false;
	}

	/**
	 * Schedule a recurring event.
	 *
	 * API to as_schedule_action() that also verifies that the schedule actually got created in WordPress.
	 * Sometimes the database rejects this update so we need to do actual verification.
	 *
	 * @param int     $time         Timestamp.
	 * @param string  $interval     Cron Schedule name.
	 * @param string  $method       Class method.
	 * @param array   $action_args  Optional. Args to include. It is highly recommended that this not be empty.
	 * @param string  $group        Optional. The action group.
	 *
	 * @return bool  True on verified schedule success, else false.
	 */
	public static function schedule_event( $time, $interval, $method, $action_args = array( true ), $group = backupbuddy_constants::CRON_GROUP ) {
		$interval = self::schedule_name_to_interval( $interval );

		if ( ! empty(
			as_schedule_recurring_action(
				$time,
				$interval,
				backupbuddy_constants::CRON_HOOK,
				array(
					$method,
					$action_args
				),
				$group
			)
		) ) {
			return true;
		}

		// If failed, sleep 1 then try scheduling again.
		sleep( backupbuddy_constants::SCHEDULE_RETRY_WAIT );
		if ( ! empty(
			as_schedule_recurring_action(
				$time,
				$interval,
				backupbuddy_constants::CRON_HOOK,
				array(
					$method,
					$action_args
				),
				$group
			)
		) ) {
			return true;
		}

		// If still failing, sleep for 1 second, then try again.
		pb_backupbuddy::status(
			'details',
			sprintf(
				__( 'Confirming cron scheduled for `%s`...', 'it-l10n-backupbuddy' ),
				$method
			)
		);

		sleep( backupbuddy_constants::SCHEDULE_RETRY_WAIT );
		if ( ! empty( self::has_scheduled_event( $method, $action_args, $group ) ) ) {
			return true;
		}

		pb_backupbuddy::status(
			'error',
			sprintf(
				__( 'Error %1$s: This may not be a fatal error. Ignore if backup proceeds without other errors. WordPress reported success scheduling BUT as_has_scheduled_action() could NOT confirm schedule existence of method `%2$s`. A plugin may have prevented it (e.g. caching) or it is already scheduled (but could not be detected).', 'it-l10n-backupbuddy' ),
				'#82938329',
				$method
			)
		);

		return false;
	}

	/**
	 * Trigger an async action using Action Scheduler.
	 *
	 * If the action already exists it will be added to the queue.
	 *
	 * @param string  $method       The method name to call.
	 * @param array   $action_args  Array of args to pass to the method.
	 * @param string  $group        Optional. The action group.
	 *
	 * @return bool  If the event was enqueued.
	 */
	public static function trigger_async_event( $method, $action_args = array( true ), $group = backupbuddy_constants::CRON_GROUP ) {

		if ( empty( as_enqueue_async_action( backupbuddy_constants::CRON_HOOK, array( $method, $action_args ), $group ) ) ) {
			return self::schedule_single_event( time(), $method, $action_args, $group );
		}
		return true;
	}

	/**
	 * If an event exists, reschedule it for the next minute.
	 *
	 * This prevents conflicts with existing events to ensure
	 * that the event is run as soon as possible.
	 *
	 * @param string  $method      The method name to call.
	 * @param array   $action_args Array of args to pass to the method.
	 * @param string  $group       Optional. The action group.
	 *
	 * @return bool  If the event was rescheduled.
	 */
	public static function schedule_next_event( $method, $action_args = array( true ), $group = backupbuddy_constants::CRON_GROUP ) {

		$next_scheduled   = self::next_scheduled( $method, $action_args, $group );

		if ( is_numeric( $next_scheduled ) ) {
			// Already scheduled.
			return $next_scheduled;
		} elseif ( false === $next_scheduled ) {
			// Not scheduled_at all.
			return self::trigger_async_event( $method, $action_args, $group );
		}

		// Presently running. Schedule for next minute.
		return self::schedule_single_event( time() + 60, $method, $action_args, $group );
	}

	/**
	 * Unschedule an event.
	 *
	 * Note that this handles checking for the existence of the event so you don't have to.
	 *
	 * API to as_unschedule_action() that also verifies that the schedule actually got removed WordPress.
	 * Sometimes the database rejects this update so we need to do actual verification.
	 *
	 * This is used for multiple action hooks beyond `backupbuddy_cron`, so you must pass the hook name.
	 *
	 * @param string $hook Action hook.   Note that this is not the same as a method name.
	 * @param array  $args Array of args. Typically contains the method name and an array of args.
	 * @param string $group Optional.     Optional. Group slug to clean up. Default is the Solid Backups Cron Group.
	 *
	 * @return bool  True on verified schedule deletion success, else false.
	 */
	public static function unschedule_event( $hook, $args, $group = backupbuddy_constants::CRON_GROUP ) {
		// Remove the event.
		if ( false !== as_has_scheduled_action( $hook, $args, $group ) ) {
			as_unschedule_action( $hook, $args, $group );
			as_unschedule_all_actions( $hook, $args, $group );
		} else {
			$timestamp = wp_next_scheduled( $hook );
			if ( false !== $timestamp && is_numeric( $timestamp ) ) {
				wp_unschedule_event( $timestamp, $hook, $args );
			}
		}

		if ( false !== self::has_scheduled_event( $hook, $args, $group ) ) {
			pb_backupbuddy::status(
				'error',
				sprintf(
					__( 'WordPress reported success unscheduling BUT as_has_scheduled_action() confirmed schedule existence of hook `%s`. The database may have rejected the removal.', 'it-l10n-backupbuddy' ),
					$hook
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Clean up events.
	 *
	 * By default this cleans up Completed events with the basic Solid Backups hook.
	 *
	 * @param array $query_args  Optional. Query args to pass to as_get_scheduled_actions().
	 *
	 * @return int Number of events deleted.
	 */
	public static function delete_events( $query_args = array() ) {
		$deleted    = 0;
		$safety     = 0;
		$action_ids = true;
		$store      = ActionScheduler_Store::instance();
		$query_args = wp_parse_args(
			$query_args,
			array(
				'hook'     => backupbuddy_constants::CRON_HOOK,
				'status'   => ActionScheduler_Store::STATUS_COMPLETE,
				'per_page' => 1000,
			)
		);

		while ( ! empty( $action_ids ) && ( $safety <= 10 ) ) {

			// Get the Action IDs we need based on the provided args.
			$action_ids = $store->query_actions( $query_args );

			// Delete the actions.
			if ( ! empty( $action_ids ) && is_array( $action_ids ) ) {
				foreach ( $action_ids as $id ) {
					$store->delete_action( $id, 1 );
					$deleted++;
				}
			}
			$safety++;
		}

		return intval( $deleted );
	}

	/**
	 * Delete *all* events in a given group.
	 *
	 * This handles Complete, Pending, Failed, and Canceled statuses.
	 *
	 * @param string $group_slug  Optional. Group slug to clean up. Default is the Solid Backups Cron Group.
	 *
	 * @return int Number of events deleted.
	 */
	public static function delete_all_events_by_group( $group_slug = '' ) {
		$group_slug = empty( $group_slug ) ? backupbuddy_constants::CRON_GROUP : $group_slug;

		$deleted = self::delete_events( array(
			'group'  => $group_slug,
		) );

		$deleted += self::delete_events( array(
			'group'  => $group_slug,
			'status' => ActionScheduler_Store::STATUS_PENDING,
		) );

		$deleted += self::delete_events( array(
			'group'  => $group_slug,
			'status' => ActionScheduler_Store::STATUS_FAILED,
		) );

		$deleted += self::delete_events( array(
			'group'  => $group_slug,
			'status' => ActionScheduler_Store::STATUS_CANCELED,
		) );

		return intval( $deleted );
	}

	/**
	 * Filter Cron Schedule names to numeric intervals.
	 *
	 * This is used to convert string names to numeric intervals for Action Scheduler.
	 *
	 * @param mixed $schedule Cron schedule name, else a numeric interval.
	 *
	 * @return int Numeric interval, in seconds.
	 */
	public static function schedule_name_to_interval( $schedule ) {
		if ( ! is_numeric( $schedule ) ) {
			$filters = wp_get_schedules();
			if ( in_array( $schedule, array_keys( $filters ) ) ) {
				// Intval sanity check.
				return intval( $filters[ $schedule ]['interval'] );
			}
		}
		return $schedule;
	}

	/**
	 * Spawn cron if enabled.
	 */
	public static function maybe_spawn_cron() {
		if ( '1' == pb_backupbuddy::$options['skip_spawn_cron_call'] ) {
			return;
		}
		pb_backupbuddy::status( 'details', 'Spawning cron now.' );
		update_option( '_transient_doing_cron', 0 ); // Prevent cron-blocking for next item.
		spawn_cron( time() + 150 ); // Adds > 60 seconds to get around once per minute cron running limit.
	}

	/**
	 * Handle normalizing zip comment data, defaults, etc.
	 *
	 * @param array $comment  Array of meta data to normalize & apply defaults to.
	 *
	 * @return array  Normalized array.
	 */
	public static function normalize_comment_data( $comment ) {

		$defaults = array(
			'serial'     => '',
			'siteurl'    => '',
			'type'       => '',
			'profile'    => '',
			'created'    => '',
			'generator'  => '',
			'db_prefix'  => '',
			'bb_version' => '',
			'wp_version' => '',
			'posts'      => '',
			'pages'      => '',
			'comments'   => '',
			'users'      => '',
			'dat_path'   => '',
			'note'       => '',
		);

		if ( ! is_array( $comment ) ) { // Plain text; place in note field.

			$maybe_comment = json_decode( $comment, true );
			if ( null !== $maybe_comment ) {
				return array_merge( $defaults, $maybe_comment );
			} else {

				if ( is_string( $comment ) ) {
					$defaults['note'] = $comment;
				}
				return $defaults;
			}
		} else { // Array. Merge defaults and return.
			return array_merge( $defaults, $comment );
		}
	}

	/**
	 * Translates meta information field names and values into nice readable forms.
	 *
	 * @param string $comment_line_name   Meta field name.
	 * @param string $comment_line_value  Value of meta item.
	 *
	 * @return array|false  Array with two entries: the updates comment line name and updated comment line value. false if empty.
	 */
	public static function pretty_meta_info( $comment_line_name, $comment_line_value ) {

		if ( 'serial' === $comment_line_name ) {
			$comment_line_name = __( 'Unique serial identifier', 'it-l10n-backupbuddy' );
		} elseif ( 'siteurl' === $comment_line_name ) {
			$comment_line_name = __( 'Site URL', 'it-l10n-backupbuddy' );
		} elseif ( 'type' === $comment_line_name ) {
			$comment_line_name = __( 'Backup Type', 'it-l10n-backupbuddy' );
			if ( 'db' === $comment_line_value ) {
				$comment_line_value = __( 'Database', 'it-l10n-backupbuddy' );
			} elseif ( 'full' === $comment_line_value ) {
				$comment_line_value = __( 'Full', 'it-l10n-backupbuddy' );
			} elseif ( 'export' === $comment_line_value ) {
				$comment_line_value = __( 'Multisite Subsite Export', 'it-l10n-backupbuddy' );
			}
		} elseif ( 'profile' === $comment_line_name ) {
			$comment_line_name = __( 'Backup Profile', 'it-l10n-backupbuddy' );
		} elseif ( 'created' === $comment_line_name ) {
			$comment_line_name = __( 'Creation Date', 'it-l10n-backupbuddy' );
			if ( '' != $comment_line_value ) {
				$comment_line_value = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $comment_line_value ) );
			}
		} elseif ( 'bb_version' === $comment_line_name ) {
			$comment_line_name = __( 'Solid Backups version at creation', 'it-l10n-backupbuddy' );
		} elseif ( 'wp_version' === $comment_line_name ) {
			$comment_line_name = __( 'WordPress version at creation', 'it-l10n-backupbuddy' );
		} elseif ( 'dat_path' === $comment_line_name ) {
			$comment_line_name = __( 'Solid Backups data file (relative)', 'it-l10n-backupbuddy' );
		} elseif ( 'posts' === $comment_line_name ) {
			$comment_line_name = __( 'Total Posts', 'it-l10n-backupbuddy' );
		} elseif ( 'pages' === $comment_line_name ) {
			$comment_line_name = __( 'Total Pages', 'it-l10n-backupbuddy' );
		} elseif ( 'comments' === $comment_line_name ) {
			$comment_line_name = __( 'Total Comments', 'it-l10n-backupbuddy' );
		} elseif ( 'users' === $comment_line_name ) {
			$comment_line_name = __( 'Total Users', 'it-l10n-backupbuddy' );
		} elseif ( 'note' === $comment_line_name ) {
			$comment_line_name = __( 'User-specified note', 'it-l10n-backupbuddy' );
			if ( '' != $comment_line_value ) {
				$comment_line_value = '"' . htmlentities( $comment_line_value ) . '"';
			}
		} else {
			$comment_line_name = $comment_line_name;
		}

		if ( '' != $comment_line_value ) {
			return array( $comment_line_name, $comment_line_value );
		} else {
			return array( $comment_line_name, '-Empty-' );
		}

	}

	/**
	 * Outputs an alert warning if a core db table is excluded.
	 *
	 * @param array $excludes  Array of tables excluded from the backup.
	 *
	 * @return array  Array of message warnings about potential issues found with these exclusions, if any. Index = unique identifer, Value = message.
	 */
	public static function alert_core_table_excludes( $excludes ) {
		global $wpdb;
		$prefix = $wpdb->prefix;

		// If these tables are found excluded then warn that may be a bad idea.
		$warn_tables = array(
			$prefix . 'comments',
			$prefix . 'posts',
			$prefix . 'users',
			$prefix . 'commentmeta',
			$prefix . 'postmeta',
			$prefix . 'term_relationships',
			$prefix . 'options',
			$prefix . 'term_taxonomy',
			$prefix . 'links',
			$prefix . 'terms',
		);

		$return_array = array();
		foreach ( $warn_tables as $warn_table ) {
			if ( in_array( $warn_table, $excludes ) ) {
				$return_array[ 'excluding_coretable-' . md5( $warn_table ) ] = __( 'Warning: You are excluding one or more core WordPress tables `' . $warn_table . '` which may result in an incomplete backup. Remove exclusions or backup with another profile or method.', 'it-l10n-backupbuddy' );
			}
		}

		return $return_array;
	}

	/**
	 * Outputs an alert warning if a core db directory is excluded.
	 *
	 * @param array $excludes  Array of paths excluded from the backup.
	 *
	 * @return array  Array of message warnings about potential issues found with these exclusions, if any. Index = unique identifer, Value = message.
	 */
	public static function alert_core_file_excludes( $excludes ) {

		// If these paths are found excluded then warn that may be a bad idea.
		$warn_dirs = array( // No trailing slash.
			'/wp-content',
			'/wp-content/uploads',
			'/wp-content/uploads/backupbuddy_temp',
			'/' . ltrim( str_replace( ABSPATH, '', backupbuddy_core::getBackupDirectory() ), '\\/' ),
		);

		foreach ( $excludes as &$exclude ) { // Strip trailing slash(es).
			$exclude = rtrim( $exclude, '\\/' );
		}

		$return_array = array();
		foreach ( $warn_dirs as $warn_dir ) {
			if ( in_array( $warn_dir, $excludes ) ) {
				$return_array[ 'excluding_corefile-' . md5( $warn_dir ) ] = __( 'Warning: You are excluding one or more WordPress core or Solid Backups directories `' . esc_attr( $warn_dir ) . '` which may result in an incomplete or malfunctioning backup. Remove exclusions or backup with another profile or method to avoid problems.', 'it-l10n-backupbuddy' );
			}
		}

		return $return_array;
	}

	/**
	 * Output meta info in a table.
	 *
	 * @param string $file  Backup file to get comment meta data from.
	 *
	 * @return array|false  Array of meta data or false on failure to retrieve.
	 */
	public static function getZipMeta( $file ) {
		if ( ! isset( pb_backupbuddy::$classes['zipbuddy'] ) ) {
			require_once pb_backupbuddy::plugin_path() . '/lib/zipbuddy/zipbuddy.php';
			pb_backupbuddy::$classes['zipbuddy'] = new pluginbuddy_zipbuddy( backupbuddy_core::getBackupDirectory() );
		}
		$comment_meta = array();
		if ( isset( $file ) ) {
			$comment = pb_backupbuddy::$classes['zipbuddy']->get_comment( $file );
			$comment = backupbuddy_core::normalize_comment_data( $comment );

			$comment_meta = array();
			foreach ( $comment as $comment_line_name => $comment_line_value ) { // Loop through all meta fields in the comment array to display.
				$response = backupbuddy_core::pretty_meta_info( $comment_line_name, $comment_line_value );
				if ( false !== $response ) {
					$response[0]                        = '<span title="' . $comment_line_name . '">' . $response[0] . '</span>';
					$comment_meta[ $comment_line_name ] = $response;
				}
			}
		}

		if ( count( $comment_meta ) > 0 ) {
			return $comment_meta;
		} else {
			return false;
		}
	}

	/**
	 * Latest version info. Array of latest major,minor. False on fail to get.
	 *
	 * @param bool $bypass_cache  Bypass cache.
	 *
	 * @return string|false  Solid Backups Version or false.
	 */
	public static function determineLatestVersion( $bypass_cache = false ) {
		$latest_backupbuddy_version_cache_minutes = 60 * 12; // Define how many minutes to cache the latest backupbuddy version number.

		function pb_backupbuddy_split2( $string, $needle, $nth ) {
			$max = strlen( $string );
			$n   = 0;
			for ( $i = 0;$i < $max;$i++ ) {
				if ( $string[ $i ] == $needle ) {
					$n++;
					if ( $n >= $nth ) {
						break;
					}
				}
			}
			$arr[] = substr( $string, 0, $i );
			$arr[] = substr( $string, $i + 1, $max );
			return $arr;
		}

		if ( true === $bypass_cache ) {
			$latest_backupbuddy_version = false;
		} else {
			$latest_backupbuddy_version = get_transient( 'pb_backupbuddy_latest_version' );
		}

		if ( false === $latest_backupbuddy_version || ! is_array( $latest_backupbuddy_version ) ) {
			$response = wp_remote_get(
				'http://api.ithemes.com/product/version?apikey=ixho7dk0p244n0ob&package=backupbuddy&channel=stable', array(
					'method'      => 'GET',
					'timeout'     => 7,
					'redirection' => 3,
					'httpversion' => '1.0',
					// 'blocking' => true,
					'headers'     => array(),
					'body'        => null,
					'cookies'     => array(),
				)
			);
			if ( is_wp_error( $response ) ) {
				$latest_backupbuddy_version = array( 0, 0 ); // Set to 0 for transient to prevent hitting server again for a bit since something went wrong.
			} else {
				$minor_version              = $response['body'];
				$major_version              = pb_backupbuddy_split2( $minor_version, '.', 3 );
				$major_version              = $major_version[0];
				$latest_backupbuddy_version = array( $minor_version, $major_version );
			}
			set_transient( 'pb_backupbuddy_latest_version', $latest_backupbuddy_version, 60 * $latest_backupbuddy_version_cache_minutes );
		} // end not cached.

		if ( 0 == $latest_backupbuddy_version[0] && 0 == $latest_backupbuddy_version[1] ) { // Server not responding.
			return false;
		}

		return $latest_backupbuddy_version;

	}

	/**
	 * Renders importbuddy on this server.
	 *
	 * @param string $password               Importer Password.
	 * @param string $backup_file            Full filename with path to backup file to import.
	 * @param string $additional_state_info  Array of additional state information to merge into state array, such as session tokens.
	 * @param bool   $do_cleanup             Deprecated.
	 *
	 * @return string|array  If string then importbuddy serial. If array then an error has been encountered. Array format: array( false, 'Error message.' ).
	 */
	public static function deploymentImportBuddy( $password, $backup_file, $additional_state_info = '', $do_cleanup = true ) {
		if ( ! file_exists( $backup_file ) ) {
			$error = 'Error #43848378: Backup file `' . esc_html( $backup_file ) . '` not found uploaded.';
			pb_backupbuddy::status( 'error', $error );
			return array( false, $error );
		}

		$backup_serial = backupbuddy_core::get_serial_from_file( $backup_file );

		$import_file_serial = pb_backupbuddy::random_string( 15 );
		$import_filename    = 'importbuddy-' . $import_file_serial . '.php';
		backupbuddy_core::importbuddy( ABSPATH . $import_filename, $password );

		// Render default config file overrides. Overrrides default restore.php state data.
		$state = array();
		global $wpdb;
		$state['type']                             = 'deploy';
		$state['archive']                          = $backup_file;
		$state['siteurl']                          = preg_replace( '|/*$|', '', site_url() ); // Strip trailing slashes.
		$state['homeurl']                          = preg_replace( '|/*$|', '', home_url() ); // Strip trailing slashes.
		$state['restoreFiles']                     = false;
		$state['migrateHtaccess']                  = false;
		$state['remote_api']                       = pb_backupbuddy::$options['remote_api']; // For use by importbuddy api auth. Enables remote api in this importbuddy.
		$state['databaseSettings']['server']       = DB_HOST;
		$state['databaseSettings']['database']     = DB_NAME;
		$state['databaseSettings']['username']     = DB_USER;
		$state['databaseSettings']['password']     = DB_PASSWORD;
		$state['databaseSettings']['prefix']       = $wpdb->prefix;
		$state['databaseSettings']['renamePrefix'] = true;

		if ( is_array( $additional_state_info ) ) {
			$state = array_merge_recursive( $state, $additional_state_info );
		}

		// Write default state overrides.
		$state_file  = ABSPATH . 'importbuddy-' . $import_file_serial . '-state.php';
		$file_handle = @fopen( $state_file, 'w' );
		if ( false === $file_handle ) {
			$error = 'Error #8384784: Temp state file is not creatable/writable. Check your permissions. (' . esc_html( $state_file ) . ')';
			pb_backupbuddy::status( 'error', $error );
			return array( false, $error );
		}
		fwrite( $file_handle, "<?php die('Access Denied.'); // <!-- ?>\n" . base64_encode( json_encode( $state ) ) );
		fclose( $file_handle );

		$undo_file = 'backupbuddy_deploy_undo-' . $backup_serial . '.php';
		if ( false === copy( pb_backupbuddy::plugin_path() . '/classes/_rollback_undo.php', ABSPATH . $undo_file ) ) {
			$error = 'Error #3289447: Unable to write undo file `' . ABSPATH . esc_html( $undo_file ) . '`. Check permissions on directory.';
			pb_backupbuddy::status( 'error', $error );
			return array( false, $error );
		}

		return $import_file_serial;

	}

	/**
	 * Detect Likely Highest Execution Time
	 *
	 * @return int  Highest execution time.
	 */
	public static function detectLikelyHighestExecutionTime() {
		$detected_execution = backupbuddy_core::detectMaxExecutionTime();
		if ( pb_backupbuddy::$options['tested_php_runtime'] > $detected_execution ) {
			$detected_execution = pb_backupbuddy::$options['tested_php_runtime'];
		}
		return $detected_execution;
	}

	/**
	 * Attempt to detect the max execution time allowed by PHP. Defaults to 30 if unable to detect or a suspicious value is detected.
	 * NOTE: This DOES take into account the TESTED PHP runtime IF its value is available. Lesser of two values of used.
	 * IMPORTANT: This does NOT take into account user-specified override via settings page. For that, use adjustedMaxExecutionTime().
	 */
	public static function detectMaxExecutionTime() {
		$detected_max_execution_time = str_ireplace( 's', '', ini_get( 'max_execution_time' ) );
		if ( is_numeric( $detected_max_execution_time ) ) {
			$detected_max_execution_time = $detected_max_execution_time;
		} else {
			$detected_max_execution_time = 30;
		}
		if ( $detected_max_execution_time <= 0 ) {
			$detected_max_execution_time = 30;
		}

		if ( isset( pb_backupbuddy::$options['tested_php_runtime'] ) && ( pb_backupbuddy::$options['tested_php_runtime'] > 0 ) ) { // Check isset for importbuddy.
			if ( pb_backupbuddy::$options['tested_php_runtime'] < $detected_max_execution_time ) {
				$detected_max_execution_time = pb_backupbuddy::$options['tested_php_runtime'];
			}
		}

		return $detected_max_execution_time;
	}

	/**
	 * Same as detectedMaxExecutionTime EXCEPT takes into account user overrided value in settings (if any)..
	 *
	 * @param int $max  Max Execution time (in seconds).
	 *
	 * @return int  Adjusted max execution time.
	 */
	public static function adjustedMaxExecutionTime( $max = '' ) {
		if ( '' == $max ) {
			if ( ! isset( pb_backupbuddy::$options['max_execution_time'] ) ) {
				$max = '30';
			} else {
				$max = pb_backupbuddy::$options['max_execution_time'];
			}
		}

		$detected = self::detectMaxExecutionTime();
		if ( '' != $max && is_numeric( $max ) && $max > 0 ) { // If set and a number, use user-specified runtime.
			return $max;
		} else { // Nothing user-specified so user detected value.
			return $detected;
		}
	}

	/**
	 * Escape SQL using either mysql or mysqli based on whichever WordPress is using.
	 * WP 3.9 introducing mysqli support.
	 *
	 * @param string $sql  String to escape.
	 *
	 * @return string  Escaped string.
	 */
	public static function dbEscape( $sql ) {
		global $wpdb;
		return mysqli_real_escape_string( $wpdb->dbh, $sql );
	}

	/**
	 * Verifies AJAX access.
	 *
	 * !! IMPORTANT FOR ANY AJAX PAGES FOR SECURITY !! Verify user is both logged into admin and has appropriate role access to BackupBuddy.
	 *
	 * @return die|true  On failure, dies/halts PHP script for security.  On access franted, returns true.
	 */
	public static function verifyAjaxAccess() {
		if ( ! is_admin() ) {
			die( 'Error #2389833. Access Denied.' );
		}
		if ( ! current_user_can( pb_backupbuddy::$options['role_access'] ) ) {
			die( 'Error #2373823. Access Denied.' );
		}

		return true;
	}

	/**
	 * Get Solid Backups Notifications
	 *
	 * @return array  Notifications option.
	 */
	public static function getNotifications() {
		$default = array();
		return get_site_option( backupbuddy_constants::NOTIFICATIONS_OPTION_SLUG, $default, false );
	}

	/**
	 * Update Notifications in Site Options
	 *
	 * @param array $notificationArray  New notifications array.
	 */
	public static function replaceNotifications( $notificationArray ) {
		// Save.
		add_site_option( backupbuddy_constants::NOTIFICATIONS_OPTION_SLUG, $notificationArray, '', 'no' );
		update_site_option( backupbuddy_constants::NOTIFICATIONS_OPTION_SLUG, $notificationArray );
	}

	/**
	 * Determine backup type from file whether by filename or embedded fileoptions.
	 *
	 * @param string $file              Full filename path of backup to determine type of.
	 * @param bool   $quiet             Suppress status logs.
	 * @param bool   $skip_fileoptions  When false if the backup type cannot be detected via filename then try to open the fileoptions file to get more details. Set true for non-crucial cases for speed.
	 *
	 * @return string  Type of backup (eg full, db). If unknown, empty string '' returned.
	 */
	public static function getBackupTypeFromFile( $file, $quiet = false, $skip_fileoptions = false ) {

		// If not a zip file, return blank.
		if ( 'zip' !== strtolower( substr( $file, -3 ) ) ) {
			return '';
		}

		if ( false === $quiet ) {
			pb_backupbuddy::status( 'details', 'Detecting backup type if possible.' );
		}

		$type = self::parse_file( $file, 'type' );

		if ( ! $type ) {
			if ( false !== stristr( $file, 'importbuddy.php' ) ) {
				$type = 'Importer Tool';
			} elseif ( stristr( $file, '-export-' ) !== false ) {
				$type = 'export';
			}
		}

		if ( $type ) {
			if ( false === $quiet ) {
				pb_backupbuddy::status( 'details', 'Detected backup type as `' . $type . '` via filename.' );
			}
			return $type;
		}

		// See if we can get backup type from fileoptions data.
		if ( false === $skip_fileoptions ) {
			$fileoptions_file = backupbuddy_core::getLogDirectory() . 'fileoptions/' . backupbuddy_core::get_serial_from_file( $file ) . '.txt';
			if ( file_exists( $fileoptions_file ) ) {
				require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
				$backup_options = new pb_backupbuddy_fileoptions( $fileoptions_file, true, true );
				if ( file_exists( $fileoptions_file ) ) {
					$result = $backup_options->is_ok();
					if ( true !== $result ) {
						// pb_backupbuddy::status( 'warning', 'Warning only: Unable to open fileoptions file `' . $fileoptionsFile . '`. This may be normal.' );
					} else {
						if ( isset( $backup_options->options['integrity']['detected_type'] ) ) {
							if ( false === $quiet ) {
								pb_backupbuddy::status( 'details', 'Detected backup type as `' . $backup_options->options['integrity']['detected_type'] . '` via integrity check data.' );
							}
							return $backup_options->options['integrity']['detected_type'];
						}
					}
				}
			}
		}
		// Type unknown.
		return '';
	}

	/**
	 * Calculates the backup zip filename.
	 *
	 * @param string $serial   Backup Serial.
	 * @param string $type     Type of backup.
	 * @param int    $profile  Profile ID.
	 *
	 * @return string  Calculated filename.
	 */
	public static function calculateArchiveFilename( $serial, $type, $profile ) {

		// Prepare some values for setting up the backup data.
		$siteurl_stripped = backupbuddy_core::backup_prefix();

		// Add profile to filename if set in options and exists.
		if ( empty( pb_backupbuddy::$options['archive_name_profile'] ) || empty( $profile['title'] ) ) {
			$backupfile_profile = '';
		} else {
			$backupfile_profile  = sanitize_file_name( strtolower( $profile['title'] ) );
			$backupfile_profile  = str_replace( '/', '_', $backupfile_profile );
			$backupfile_profile  = str_replace( '\\', '_', $backupfile_profile );
			$backupfile_profile  = str_replace( '.', '_', $backupfile_profile );
			$backupfile_profile  = str_replace( ' ', '_', $backupfile_profile );
			$backupfile_profile  = str_replace( '-', '_', $backupfile_profile );
			$backupfile_profile .= '-';
		}

		// Calculate customizable section of archive filename (date vs date+time).
		if ( 'date' === pb_backupbuddy::$options['archive_name_format'] ) { // "date" = Date only.
			$backupfile_datetime = date( backupbuddy_constants::ARCHIVE_NAME_FORMAT_DATE, pb_backupbuddy::$format->localize_time( time() ) );
		} elseif ( 'datetime24' === pb_backupbuddy::$options['archive_name_format'] ) { // "datetime" = Date + time in 24hr format.
			$backupfile_datetime = date( backupbuddy_constants::ARCHIVE_NAME_FORMAT_DATETIME24, pb_backupbuddy::$format->localize_time( time() ) );
		} elseif ( 'timestamp' === pb_backupbuddy::$options['archive_name_format'] ) { // "datetime" = Date + time in 24hr format.
			$backupfile_datetime = pb_backupbuddy::$format->localize_time( time() );
		} else { // "datetime" = date + time (the default).
			$backupfile_datetime = date( backupbuddy_constants::ARCHIVE_NAME_FORMAT_DATETIME, pb_backupbuddy::$format->localize_time( time() ) );
		}
		$archive_file = backupbuddy_core::getBackupDirectory() . 'backup-' . $siteurl_stripped . '-' . $backupfile_datetime . '-' . $backupfile_profile . $type . '-' . $serial . '.zip';

		// Make dure we can make backup dir & it exists & is protected.
		pb_backupbuddy::anti_directory_browsing( backupbuddy_core::getBackupDirectory(), false );
		if ( ! file_exists( backupbuddy_core::getBackupDirectory() ) ) {
			pb_backupbuddy::status( 'error', 'Error #84893434: Backup storage directory `' . esc_html( backupbuddy_core::getBackupDirectory() ) . '` does not exist and unable to create it. Check permissions. If using a custom path verify it is correct.' );
		}

		pb_backupbuddy::status( 'details', 'Calculated archive file: `' . esc_html( $archive_file ) . '`.' );
		return $archive_file;
	}

	/**
	 * Calculate comparison data for all files within a path. Useful for tracking file changes between two locations.
	 *
	 * @param string $root           ABSOLUTE full path.
	 * @param bool   $generate_sha1  Generate sha1?
	 * @param array  $excludes       Directories to exclude, RELATIVE to the root. Include LEADING slash for each entry.
	 * @param bool   $utf8_encode    Should we encode any file names that are in UTF-8 format?
	 * @param string $prepend_path   String path to prepend in the resulting array key returned. Does not impact actual calculations.
	 *
	 * @return array  Nested array of file/directory structure.
	 */
	public static function hashGlob( $root, $generate_sha1 = false, $excludes = array(), $utf8_encode = false, $prepend_path = '' ) {
		$root     = rtrim( $root, '/\\' ); // Make sure no trailing slash.
		$excludes = str_replace( $root, '', $excludes ); // Make sure all relative to the root.

		if ( ! file_exists( $root ) ) {
			pb_backupbuddy::status( 'warning', 'Warning #8949834934: Unable to read hashGlob of dir as it does not exist: `' . esc_html( $root ) . '`. SKIPPING OVER.' );
			return array();
		}

		$files        = (array) pb_backupbuddy::$filesystem->deepscandir( $root ); // As of v7.0 changed from deepscan to deepglob to get any dirs or files beginning with a period.
		$root_len     = strlen( $root );
		$hashed_files = array();
		foreach ( $files as $file_id => &$file ) {
			$new_file = substr( $file, $root_len );

			// If this file/directory begins with an exclusion then jump to next file/directory.
			foreach ( $excludes as $exclude ) {
				if ( backupbuddy_core::startsWith( $new_file, $exclude ) ) {
					continue 2;
				}
			}

			// Omit directories themselves.
			if ( is_dir( $file ) ) {
				continue;
			}

			$stat = stat( $file );
			if ( false === $stat ) {
				pb_backupbuddy::status( 'error', 'Unable to read file `' . esc_html( $file ) . '` stat. Skipping file.' );
				continue;
			}

			// If the filename is in UTF-8 and the flag is set, encode before using as an array key.
			if ( $utf8_encode && 'UTF-8' == mb_detect_encoding( $new_file ) ) {
				$new_file = utf8_encode( $new_file );
			}

			$new_file = $prepend_path . $new_file;

			$hashed_files[ $new_file ] = array(
				'size'     => $stat['size'],
				'modified' => $stat['mtime'],
			);
			if ( defined( 'BACKUPBUDDY_DEV' ) && true === BACKUPBUDDY_DEV ) {
				$hashed_files[ $new_file ]['debug_filename']   = base64_encode( $file );
				$hashed_files[ $new_file ]['debug_filelength'] = strlen( $file );
			}
			if ( true === $generate_sha1 && $stat['size'] < 1073741824 ) { // < 100mb
				$hashed_files[ $new_file ]['sha1'] = sha1_file( $file );
			}
			unset( $files[ $file_id ] ); // Better to free memory or leave out for performance?

		}
		unset( $files );

		return $hashed_files;

	}

	/**
	 * Search backwards starting from haystack length characters from the end.
	 *
	 * @param string $haystack  String to search.
	 * @param string $needle    What to search for.
	 *
	 * @return bool  If $needle was found backwards in $haystack.
	 */
	public static function startsWith( $haystack, $needle ) {
		if ( '' == $needle ) { // Blank needle is invalid so always say false, it does not start with a blank.
			return false;
		}
		return '' === $needle || false !== strrpos( $haystack, $needle, -strlen( $haystack ) );
	}

	/**
	 * Add Notification to notifications array.
	 *
	 * @param string $slug     Notification slug.
	 * @param string $title    Title of notification.
	 * @param string $message  Notification message.
	 * @param array  $data     Data to attach to notification.
	 * @param bool   $urgent   If urgent or not.
	 * @param int    $time     Timestamp.
	 */
	public static function addNotification( $slug, $title, $message = '', $data = array(), $urgent = false, $time = '' ) {

		if ( '' == $time ) {
			$time = time();
		}

		// Create this new notification data.
		$notification = array(
			'slug'    => $slug,
			'time'    => $time,
			'title'   => $title,
			'message' => $message,
			'data'    => $data,
			'urgent'  => false,
		);
		$notification = array_merge( pb_backupbuddy::settings( 'notification_defaults' ), $notification ); // Apply defaults.

		// Load current notifications.
		$notification_array = self::getNotifications();

		// Add to current notifications.
		do_action( 'backupbuddy_core_add_notification', $notification );
		$notification_array[] = $notification;

		// Only keep last X notifications to prevent buildup.
		$notification_array = array_slice( $notification_array, ( -1 * backupbuddy_constants::NOTIFICATIONS_MAX_COUNT ), backupbuddy_constants::NOTIFICATIONS_MAX_COUNT, true );

		// Save.
		self::replaceNotifications( $notification_array );

	}

	/**
	 * Get additional includes or excludes of db tables. Populates {prefix} variable and sanitizes, removes dupes, etc. Returns array.
	 *
	 * @param string $includes_or_excludes  Either "includes" or "excludes".
	 * @param array  $profile               Profile array.
	 * @param string $override_prefix       Override Prefix.
	 *
	 * @return array  Array of Tables.
	 */
	public static function get_mysqldump_additional( $includes_or_excludes, $profile, $override_prefix = '' ) {
		if ( ! in_array( $includes_or_excludes, array( 'includes', 'excludes' ), true ) ) {
			$error = 'Error #839328973: Invalid getIncludeExcludeTables() parameter in core.php.';
			error_log( 'Solid Backups ' . esc_html( $error ) );
			echo esc_html( $error );
			return false;
		}

		if ( ! isset( $profile[ 'mysqldump_additional_' . $includes_or_excludes ] ) ) {
			error_log( 'Solid Backups Error #4389348934: Profile missing expected key. Profile: `' . print_r( $profile, true ) . '`.' );
		}

		$tables = explode( "\n", $profile[ 'mysqldump_additional_' . $includes_or_excludes ] );
		$tables = array_map( 'trim', $tables ); // Trim whitespace around tables.

		if ( '' == $override_prefix ) {
			global $wpdb;
			$prefix = $wpdb->prefix;
		} else {
			$prefix = $override_prefix;
		}
		foreach ( $tables as &$table ) {
			$table = str_replace( '{prefix}', $prefix, $table ); // Populate prefix variable.
		}

		$tables = array_unique( $tables ); // Remove any duplicate tables.
		return $tables;
	}

	/**
	 * Process Backup
	 *
	 * @param string $serial  Passed to set_status_serial(). Default 'blank'.
	 *
	 * @return bool  Result of pb_backupbuddy_backup::process_backup().
	 */
	public static function process_backup( $serial = 'blank' ) {
		pb_backupbuddy::set_status_serial( $serial );
		pb_backupbuddy::status( 'details', '--- New PHP process.' );
		pb_backupbuddy::set_greedy_script_limits();
		pb_backupbuddy::status( 'message', 'Running process for serial `' . $serial . '`...' );

		require_once pb_backupbuddy::plugin_path() . '/classes/backup.php';
		$new_backup = new pb_backupbuddy_backup();
		return $new_backup->process_backup( $serial );
	}

	/**
	 * Verify Housekeeping
	 */
	public static function verifyHousekeeping() {
		if ( is_multisite() ) { // For Multisite only run on main Network site.
			if ( ! is_main_site() ) {
				return;
			}
		}

		// Ensure Schedule Exists.
		if ( ! self::has_scheduled_event( 'housekeeping' ) ) {
			backupbuddy_core::schedule_event( time() + ( 60 * 60 * 2 ), 'daily', 'housekeeping' );
		}
	}

	/**
	 * Verify Live Cron
	 */
	public static function verifyLiveCron() {
		require_once pb_backupbuddy::plugin_path() . '/destinations/live/live.php';
		if ( false === backupbuddy_live::getLiveID() ) { // If no Live destination set up then return.
			return;
		}

		// Ensure Live Schedule Exists.
		if ( ! self::has_scheduled_event( 'live', array( true ), 'live' ) ) {
			backupbuddy_core::schedule_event( time() + ( 60 * 60 ), 'hourly', 'live', array( true ), 'live');
		}
	}

	/**
	 * Clear Live Logs
	 *
	 * @param string $serial  Serial of backup.
	 */
	public static function clearLiveLogs( $serial ) {
		pb_backupbuddy::status( 'details', __( 'Deleting live logs and catalog', 'it-l10n-backupbuddy' ) );

		$log_files = array_merge(
			(array) glob( backupbuddy_core::getLogDirectory() . 'fileoptions/send-live_*.txt' ),
			(array) glob( backupbuddy_core::getTempDirectory() . $serial ),
			array( backupbuddy_core::getLogDirectory() . 'live' ),
			(array) glob( backupbuddy_core::getLogDirectory() . '*' . $serial . '*' )
		);

		foreach ( $log_files as $log_file ) {
			pb_backupbuddy::$filesystem->unlink_recursive( $log_file );
		}

	}

	/**
	 * Delete All Data Files
	 */
	public static function deleteAllDataFiles() {
		$temp_dir = self::getTempDirectory();
		$log_dir  = self::getLogDirectory();
		pb_backupbuddy::alert(
			sprintf(
				__( 'Deleting all files contained within `%1$s` and `%2$s`.', 'it-l10n-backupbuddy' ),
				$temp_dir,
				$log_dir
			),
			false, '', '', '', array( 'class' => 'below-h2' ) );
		pb_backupbuddy::$filesystem->unlink_recursive( $temp_dir );
		pb_backupbuddy::$filesystem->unlink_recursive( $log_dir );
		// Put log dir back in place.
		pb_backupbuddy::anti_directory_browsing( $log_dir, false );

	}


	/**
	 * Takes a base level to calculate tables from.  Then adds additional tables.  Then removes any exclusions. Returns array of final table listing to backup.
	 *
	 * @see dump()
	 *
	 * @param string $base_dump_mode       Determines which database tables to dump by default. Valid values:  all, none, prefix
	 * @param array  $additional_includes  Array of additional table(s) to INCLUDE in dump. Added in addition to those found by the $base_dump_mode
	 * @param array  $additional_excludes  Array of additional table(s) to EXCLUDE from dump. Removed from those found by the $base_dump_mode + $additional_includes.
	 *
	 * @return array  Array of tables to backup.
	 */
	public static function calculate_tables( $base_dump_mode, $additional_includes = array(), $additional_excludes = array() ) {

		global $wpdb;
		$wpdb->show_errors(); // Turn on error display.

		$tables = array();
		pb_backupbuddy::status( 'details', __( 'Calculating mysql database tables to backup.', 'it-l10n-backupbuddy' ) );
		pb_backupbuddy::status(
			'details',
			esc_html(
				sprintf(
					__( 'Base database dump mode (before inclusions/exclusions): `%s`.', 'it-l10n-backupbuddy' ),
					$base_dump_mode
				)
			)
		);


		// Calculate base tables.
		if ( 'all' === $base_dump_mode ) { // All tables in database to start with.
			$sql = 'SELECT table_name AS `table_name` FROM information_schema.tables WHERE table_schema = DATABASE()';
			pb_backupbuddy::status( 'startAction', 'schemaTables' );
			$results = $wpdb->get_results( $sql, ARRAY_A );
			pb_backupbuddy::status( 'finishAction', 'schemaTables' );
			if ( ( null === $results ) || ( false === $results ) ) {
				pb_backupbuddy::status(
					'error',
					esc_html(
						sprintf(
							__( 'Error %1$s: Unable to calculate database tables with query: `%2$s`. Check database permissions or contact host.', 'it-l10n-backupbuddy' ),
							'#8493894a',
							$sql
						)
					)
				);
			}
			foreach ( (array) $results as $result ) {
				array_push( $tables, $result['table_name'] );
			}
			unset( $results );

		} elseif ( 'prefix' === $base_dump_mode ) { // Tables matching prefix.

			pb_backupbuddy::status(
				'details',
				esc_html(
					sprintf(
						__( 'Determining database tables with prefix `%s`.', 'it-l10n-backupbuddy' ),
						$wpdb->prefix
					)
				)
			);
			$prefix_sql = str_replace( '_', '\_', $wpdb->prefix );
			$sql        = "SELECT table_name AS `table_name` FROM information_schema.tables WHERE table_name LIKE '{$prefix_sql}%' AND table_schema = DATABASE()";
			pb_backupbuddy::status( 'startAction', 'schemaTables' );
			$results = $wpdb->get_results( $sql, ARRAY_A );
			pb_backupbuddy::status( 'finishAction', 'schemaTables' );
			if ( ( null === $results ) || ( false === $results ) ) {
				pb_backupbuddy::status( 'error', 'Error #8493894b: Unable to calculate database tables with query: `' . esc_html( $sql ) . '`. Check database permissions or contact host.' );
			}
			foreach ( (array) $results as $result ) {
				array_push( $tables, $result['table_name'] );
			}
			unset( $results );

		} elseif ( 'none' !== $base_dump_mode ) { // unknown dump mode.

			pb_backupbuddy::status( 'error', 'Error #454545: Unknown database dump mode.' ); // Should never see this.

		}
		pb_backupbuddy::status( 'details', 'Base database tables based on settings (' . count( $tables ) . ' tables): `' . implode( ',', $tables ) . '`' );

		// Add additional tables.
		$tables = array_merge( $tables, $additional_includes );
		$tables = array_filter( $tables ); // Trim any phantom tables that the above line may have introduced.
		pb_backupbuddy::status( 'details', 'Database tables after addition (' . count( $tables ) . ' tables): `' . implode( ',', $tables ) . '`' );

		// Remove excluded tables.
		$tables = array_diff( $tables, $additional_excludes );
		pb_backupbuddy::status( 'details', 'Database tables after exclusion (' . count( $tables ) . ' tables): `' . implode( ',', $tables ) . '`' );

		// Remove any duplicate tables.
		$tables = array_unique( $tables );

		return array_values( $tables ); // Clean up indexing & return.

	}


	/**
	 * Truncates a file from the BEGINNING. Eg for trimming logs while leaving the most recent (at the bottom) content.
	 *
	 * @param string $filename      Path to file to truncate.
	 * @param int    $maxfilesize   Maximum file size.
	 * @param int    $keep_percent  Percentage to keep.
	 */
	public static function truncate_file_beginning( $filename, $maxfilesize, $keep_percent = 50 ) {
		@clearstatcache( true, $filename );
		$size = @filesize( $filename );
		if ( false === $size ) {
			return; // File did not exist/cannot access.
		}
		if ( $size < $maxfilesize ) {
			return;
		}

		pb_backupbuddy::status( 'details', 'Truncating LARGE file `' . esc_html( $filename ) . '` of size `' . pb_backupbuddy::$format->file_size( $size ) . '` exceeding threshold, only keeping newest ' . $keep_percent . '%.' );

		$maxfilesize = $maxfilesize * ( $keep_percent / 100 ); // keep newest part up to cetain percent.
		$fh          = fopen( $filename, 'r+' );
		$start       = @ftell( $fh );
		if ( false === $start ) {
			return; // File did not exist/cannot access.
		}
		fseek( $fh, -$maxfilesize, SEEK_END ); // Seek to middle from the end.
		$start  = fgets( $fh ); // Get start position.
		$length = ( (int) $size - (int) $start );

		// Catch instances where fread length parameter was coming in less than 0.
		if ( $length < 1 ) {
			return false;
		}

		// Read in file from middle point.
		$contents = fread( $fh, $length );

		// Find first newline & cut off everything before it so we make sure to start at a fresh line (no half lines as beginning).
		$first_newline = strpos( $contents, "\n" );
		if ( false === $first_newline ) {
			$first_newline = 0; // No newline.
		} else {
			$first_newline++; // After new line.
		}
		$contents = substr( $contents, $first_newline ); // Cut out up to the first newline (just after).

		// Write new file contents over existing file data.
		ftruncate( $fh, 0 ); // Erase current contents.
		fseek( $fh, 0 );
		fwrite( $fh, $contents );

		// Cleanup.
		fclose( $fh );
	}

	/**
	 * Source: http://stackoverflow.com/questions/2961618/how-to-read-only-5-last-line-of-the-text-file-in-php
	 *
	 * @param string $filename  File name.
	 * @param array  $lines     Lines in file to read.
	 * @param bool   $revers    Read in reverse. Default false.
	 *
	 * @return string  Last lines.
	 */
	public static function read_backward_line( $filename, $lines, $revers = false ) {
		$offset = -1;
		$c      = '';
		$read   = '';
		$i      = 0;
		$fp     = @fopen( $filename, 'r' );
		while ( $lines && fseek( $fp, $offset, SEEK_END ) >= 0 ) {
			$c = fgetc( $fp );
			if ( "\n" == $c || "\r" == $c ) {
				$lines--;
				if ( $revers ) {
					$read[ $i ] = strrev( $read[ $i ] );
					$i++;
				}
			}
			if ( $revers ) {
				$read[ $i ] .= $c;
			} else {
				$read .= $c;
			}
			$offset--;
		}
		fclose( $fp );
		if ( $revers ) {
			if ( "\n" == $read[ $i ] || "\r" == $read[ $i ] ) {
				array_pop( $read );
			} else {
				$read[ $i ] = strrev( $read[ $i ] );
			}
			return implode( '', $read );
		}
		return strrev( rtrim( $read, "\n\r" ) );
	}

	/**
	 * Truncates a file from the BEGINNING. Eg for trimming logs while leaving the most recent (at the bottom) content.
	 *
	 * OLD function. More precise but SLOW...
	 *
	 * @param string $filename      Path to file to truncate.
	 * @param int    $maxfilesize   Max file size.
	 * @param int    $keep_percent  Percentage of file to keep.
	 */
	public static function truncate_file_beginning_slow( $filename, $maxfilesize, $keep_percent = 50 ) {
		$size = filesize( $filename );
		if ( $size < $maxfilesize ) {
			return;
		}

		pb_backupbuddy::status( 'details', 'Truncating LARGE file `' . esc_html( $filename ) . '` of size `' . pb_backupbuddy::$format->file_size( $size ) . '` exceeding threshold, only keeping newest ' . $keep_percent . '%.' );

		$maxfilesize = $maxfilesize * ( $keep_percent / 100 ); // keep newest part up to cetain percent.
		$fh          = fopen( $filename, 'r+' );
		$start       = ftell( $fh );
		fseek( $fh, -$maxfilesize, SEEK_END );
		$drop   = fgets( $fh );
		$offset = ftell( $fh );
		for ( $x = 0;$x < $maxfilesize;$x++ ) {
			fseek( $fh, $x + $offset );
			$c = fgetc( $fh );
			fseek( $fh, $x );
			fwrite( $fh, $c );
		}
		ftruncate( $fh, $maxfilesize - strlen( $drop ) );
		fclose( $fh );
	}

	/**
	 * Attempts to calculate the actual maximum PHP execution time by writing to a text file once per second the time elapsed since pb_backupbuddy class loaded.
	 *
	 * @param bool $schedule_results  Schedule results.
	 * @param bool $force_run         Force run.
	 *
	 * @return false|void  False if didn't run.
	 */
	public static function php_runtime_test( $schedule_results = false, $force_run = false ) {
		pb_backupbuddy::status( 'details', 'Beginning PHP runtime test function.' );
		$test_file = backupbuddy_core::getLogDirectory() . 'php_runtime_test.txt';

		// Make sure not running too often, even if scheduled.
		if ( false === $force_run ) {
			$elapsed = time() - pb_backupbuddy::$options['last_tested_php_runtime'];
			if ( $elapsed < pb_backupbuddy::$options['php_runtime_test_minimum_interval'] ) { // Not enough time elapsed since last run.
				pb_backupbuddy::status( 'details', 'Not enough time elapsed since last PHP runtime test interval. Elapsed: `' . $elapsed . '`. Interval limit: `' . pb_backupbuddy::$options['php_runtime_test_minimum_interval'] . '`.' );
				return false;
			}
		}

		// If test file already exists, make sure it doesn't look like a test is already running.
		if ( file_exists( $test_file ) ) {
			$last_update_time = filemtime( $test_file );
			if ( false !== $last_update_time ) { // if we can get filemtime...
				if ( ( time() - $last_update_time ) < backupbuddy_constants::PHP_RUNTIME_RETEST_DELAY ) { // Not enough time has passed since last scan last updated file so it MAY still be going.
					pb_backupbuddy::status( 'details', 'PHP runtime test: Not enough time has passed since the last test updated the file.' );
					return false;
				}
			}
		}

		// Open test file for writing.
		$fso = @fopen( $test_file, 'w' );
		if ( false === $fso ) {
			return false;
		}

		// Schedule results calculation to happen afterwards if enabled.
		if ( true === $schedule_results ) {
			// Schedule test results calculation to run.
			$delay           = backupbuddy_constants::PHP_RUNTIME_TEST_MAX_TIME + backupbuddy_constants::PHP_RUNTIME_RETEST_DELAY + 5;
			$schedule_result = backupbuddy_core::schedule_single_event( time() + $delay, 'php_runtime_test_results' );
			if ( true === $schedule_result ) {
				pb_backupbuddy::status( 'details', 'PHP runtime test results cron event scheduled.' );
			} else {
				pb_backupbuddy::status( 'error', 'PHP runtime test results cron event FAILED to be scheduled.' );
			}
		}

		// Once per second write to the file the number of seconds elapsed since the test began.
		pb_backupbuddy::status( 'details', 'Start PHP runtime test loops.' );
		$loop_count       = 0;
		$loops_per_second = false;
		$total_loops      = backupbuddy_constants::PHP_RUNTIME_TEST_MAX_TIME; // [default: 300 (sec) = 5min].

		// Store current error reporting level and temp set to 0.
		$reporting_level = error_reporting();
		error_reporting( 0 );

		while ( $loop_count <= $total_loops ) {
			$loop_count++;
			@ftruncate( $fso, 0 ); // Erase existing file contents.
			$time_elapsed = round( microtime( true ) - pb_backupbuddy::$start_time, 2 );
			if ( false === @fwrite( $fso, $time_elapsed ) ) { // Update time elapsed into file.
				$total_loops = 0;
				// Reset original error_reporting level before exiting.
				error_reporting( $reporting_level );
				break; // Stop since writing failed.
			}

			if ( false === $loops_per_second ) {
				$start   = microtime( true );
				$counter = 0;

				while ( $counter++ < 100000 ) {
				}

				$time             = microtime( true ) - $start;
				$loops_per_second = $counter / $time;

				if ( $time >= 1 ) {
					$loop_count += floor( $time );
				} else {
					// Waste the remaining portion of a second
					while ( $counter++ < $loops_per_second ) {
					}
				}
			} else {
				$counter = 0;
				// Waste approximately 1 second
				while ( $counter++ < $loops_per_second ) {
				}
			}
		} // end while.

		// Set error reporting back to original level, although the script is probably dead at this point.
		error_reporting( $reporting_level );
		pb_backupbuddy::status( 'details', 'End PHP runtime test loops.' );

		@fclose( $fso );

	}

	/**
	 * Attempts to calculate the actual maximum PHP memory limit by writing to a text file while increasing memory usage.
	 *
	 * @param bool $schedule_results  Schedule results.
	 * @param bool $force_run         Force Run.
	 *
	 * @return false|void  False if didn't run.
	 */
	public static function php_memory_test( $schedule_results = false, $force_run = false ) {
		$increment_mb = 1; // How many MB to increment per chunk.

		pb_backupbuddy::status( 'details', 'Beginning PHP memory test function.' );
		$test_file = backupbuddy_core::getLogDirectory() . 'php_memory_test.txt';

		// Make sure not running too often, even if scheduled.
		if ( false === $force_run ) {
			$elapsed = time() - pb_backupbuddy::$options['last_tested_php_memory'];
			if ( $elapsed < pb_backupbuddy::$options['php_memory_test_minimum_interval'] ) { // Not enough time elapsed since last run.
				pb_backupbuddy::status( 'details', 'Not enough time elapsed since last PHP memory test interval. Elapsed: `' . $elapsed . '`. Interval limit: `' . pb_backupbuddy::$options['php_memory_test_minimum_interval'] . '`.' );
				return false;
			}
		}

		// If test file already exists, make sure it doesn't look like a test is already running.
		if ( file_exists( $test_file ) ) {
			$last_update_time = filemtime( $test_file );
			if ( false !== $last_update_time ) { // if we can get filemtime...
				if ( ( time() - $last_update_time ) < backupbuddy_constants::PHP_MEMORY_RETEST_DELAY ) { // Not enough time has passed since last scan last updated file so it MAY still be going.
					pb_backupbuddy::status( 'details', 'PHP memory test: Not enough time has passed since the last test updated the file.' );
					return false;
				}
			}
		}

		// Open test file for writing.
		$fso = @fopen( $test_file, 'w' );
		if ( false === $fso ) {
			return false;
		}

		// Schedule results calculation to happen afterwards if enabled.
		if ( true === $schedule_results ) {
			// Schedule test results calculation to run.
			$delay           = backupbuddy_constants::PHP_MEMORY_RETEST_DELAY + 5;
			$schedule_result = backupbuddy_core::schedule_single_event( time() + $delay, 'php_memory_test_results' );
			if ( true === $schedule_result ) {
				pb_backupbuddy::status( 'details', 'PHP memory test results cron event scheduled.' );
			} else {
				pb_backupbuddy::status( 'error', 'PHP memory test results cron event FAILED to be scheduled.' );
			}
		}

		// Store current error reporting level and temp set to 0.
		$reporting_level = error_reporting();
		error_reporting( 0 );

		// Once per second write to the file the number of seconds elapsed since the test began.
		pb_backupbuddy::status( 'details', 'Start PHP memory test loops.' );
		$loop_count = 0;
		$buffer     = '';
		$loop       = true;
		while ( true === $loop ) {
			$loop_count++;
			if ( $loop_count > 1000 ) {
				$loop = false;
				error_reporting( $reporting_level ); // Set error reporting back to original level, although the script is probably dead at this point.
				break;
			}

			${ 'mem_str_' . $loop_count} = str_pad( '', 1048576 * $increment_mb );

			// $usage = round( memory_get_usage() / 1048576, 2 );
			$usage = round( memory_get_usage( true ) / 1048576, 2 );

			@ftruncate( $fso, 0 ); // Erase existing file contents.
			if ( false === @fwrite( $fso, $usage ) ) { // Update time elapsed into file.
				$loop = false;
				error_reporting( $reporting_level ); // Set error reporting back to original level, although the script is probably dead at this point.
				break; // Stop since writing failed.
			}
		}

		pb_backupbuddy::status( 'details', 'End PHP memory test loops.' );

		$buffer = '';
		@fclose( $fso );
		error_reporting( $reporting_level ); // Set error reporting back to original level, although the script is probably dead at this point.
	}

	/**
	 * Stores tested runtime in pb_backupbuddy::$options['tested_php_runtime'], rounded up to next whole number. Note: pb_backupbuddy::$options['tested_php_runtime'] is 0 until test is successful.
	 *
	 * @return string|false  PHP runtime or false.
	 */
	public static function php_runtime_test_results() {
		$test_file = backupbuddy_core::getLogDirectory() . 'php_runtime_test.txt';

		// If test file already exists, make sure it doesn't look like a test is already running.
		if ( file_exists( $test_file ) ) {
			$last_update_time = filemtime( $test_file );
			if ( ( time() - $last_update_time ) < backupbuddy_constants::PHP_RUNTIME_RETEST_DELAY ) {
				pb_backupbuddy::status( 'details', 'PHP runtime test results: Not enough time has passed since the last test updated the file.' );
				return false;
			}
		} else { // File does not exist.
			return false;
		}

		// Read file contents.
		$tested_runtime = @file_get_contents( $test_file );
		if ( false === $tested_runtime ) {
			pb_backupbuddy::status( 'error', 'Error #82934894: Unable to read php runtime test results file.' );
			return false;
		}

		// Sanitize.
		$tested_runtime = trim( $tested_runtime );

		// Verify not blank.
		if ( '' == $tested_runtime ) {
			pb_backupbuddy::status( 'details', 'NOTE: PHP runtime test blank. It may be in progress.' );
			return false;
		}

		// Verify numeric.
		if ( ! is_numeric( $tested_runtime ) ) {
			pb_backupbuddy::status( 'error', 'Error #3284934734: PHP runtime test results non-numeric. Trimmed result: `' . $tested_runtime . '`.' );
			return false;
		}

		pb_backupbuddy::$options['tested_php_runtime']      = ceil( $tested_runtime ); // Round up.
		pb_backupbuddy::$options['last_tested_php_runtime'] = time(); // Timestamp test results were last saved.
		pb_backupbuddy::save();

		@unlink( $test_file ); // Delete test file as it is no longer needed.

		return pb_backupbuddy::$options['tested_php_runtime'];

	}

	/**
	 * Stores tested memory in pb_backupbuddy::$options['tested_php_memory'], rounded up to next whole number. Note: pb_backupbuddy::$options['tested_php_memory'] is 0 until test is successful.
	 *
	 * @return string|false  PHP tested memory or false.
	 */
	public static function php_memory_test_results() {
		$test_file = backupbuddy_core::getLogDirectory() . 'php_memory_test.txt';

		// If test file already exists, make sure it doesn't look like a test is already running.
		if ( file_exists( $test_file ) ) {
			$last_update_time = filemtime( $test_file );
			if ( ( time() - $last_update_time ) < backupbuddy_constants::PHP_MEMORY_RETEST_DELAY ) {
				pb_backupbuddy::status( 'details', 'PHP memory test results: Not enough time has passed since the last test updated the file.' );
				return false;
			}
		} else { // File does not exist.
			return false;
		}

		// Read file contents.
		$tested_memory = @file_get_contents( $test_file );
		if ( false === $tested_memory ) {
			pb_backupbuddy::status( 'error', 'Error #66364: Unable to read php memory test results file.' );
			return false;
		}

		// Sanitize.
		$tested_memory = trim( $tested_memory );

		// Verify not blank.
		if ( '' == $tested_memory ) {
			pb_backupbuddy::status( 'details', 'NOTE: PHP memory test blank. It may be in progress.' );
			return false;
		}

		// Verify numeric.
		if ( ! is_numeric( $tested_memory ) ) {
			pb_backupbuddy::status( 'error', 'Error #7684354990: PHP memory test results non-numeric. Trimmed result: `' . $tested_memory . '`.' );
			return false;
		}

		pb_backupbuddy::$options['tested_php_memory']      = ceil( $tested_memory ); // Round up.
		pb_backupbuddy::$options['last_tested_php_memory'] = time(); // Timestamp test results were last saved.
		pb_backupbuddy::save();

		@unlink( $test_file ); // Delete test file as it is no longer needed.

		return pb_backupbuddy::$options['tested_php_memory'];

	}

	/**
	 * Get a http header. Returns blank string if not set.
	 *
	 * @param string $header  HTTP Header to get.
	 *
	 * @return mixed  Contents of header or blank when not set.
	 */
	public static function getHttpHeader( $header ) {
		$header = str_replace( '-', '_', $header ); // Populated vars get dash replaced with underscore.
		if ( ! isset( $_SERVER[ 'HTTP_' . strtoupper( $header ) ] ) ) {
			return '';
		}
		return $_SERVER[ 'HTTP_' . strtoupper( $header ) ];
	}

	/**
	 * Source: http://stackoverflow.com/questions/190421/caller-function-in-php-5/
	 *
	 * @param bool $complete_trace  Loop through all of debug_backtrace or not.
	 *
	 * @return string  Detailed debug_backtrace
	 */
	public static function getCallingFunctionName( $complete_trace = false ) {
		$trace = debug_backtrace();
		if ( $complete_trace ) {
			$str = '';
			foreach ( $trace as $caller ) {
				$str .= "{$caller['function']}()";
				if ( isset( $caller['class'] ) ) {
					$str = "{$caller['class']}{$caller['type']}" . $str;
				}
			}
		} else {
			$caller = $trace[2];
			$str    = "{$caller['function']}()";
			if ( isset( $caller['class'] ) ) {
				$str = "{$caller['class']}{$caller['type']}" . $str;
			}
		}
		return $str;
	}


	/**
	 * Complements to Drupal:
	 * https://api.drupal.org/api/drupal/includes%21file.inc/function/file_upload_max_size/7.x
	 *
	 * Returns a file size limit in bytes based on the PHP upload_max_filesize.
	 * and post_max_size.
	 *
	 * @return int  Post Max size.
	 */
	public static function file_upload_max_size() {
		static $max_size = -1;

		if ( $max_size < 0 ) {
			// Start with post_max_size.
			$post_max_size = backupbuddy_core::parse_php_size( ini_get( 'post_max_size' ) );
			if ( $post_max_size > 0 ) {
				$max_size = $post_max_size;
			}

			// If upload_max_size is less, then reduce. Except if upload_max_size is
			// zero, which indicates no limit.
			$upload_max = backupbuddy_core::parse_php_size( ini_get( 'upload_max_filesize' ) );
			if ( $upload_max > 0 && $upload_max < $max_size ) {
				$max_size = $upload_max;
			}
		}
		return $max_size;
	}

	/**
	 * Complements to Drupal:
	 * https://api.drupal.org/api/drupal/includes%21common.inc/function/parse_size/7.x
	 *
	 * @param string $size  PHP formatted size.
	 *
	 * @return int  Size.
	 */
	public static function parse_php_size( $size ) {
		$unit = preg_replace( '/[^bkmgtpezy]/i', '', $size ); // Remove the non-unit characters from the size.
		$size = preg_replace( '/[^0-9\.]/', '', $size ); // Remove the non-numeric characters from the size.
		if ( $unit ) {
			// Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
			return round( $size * pow( 1024, stripos( 'bkmgtpezy', $unit[0] ) ) );
		} else {
			return round( $size );
		}
	}

	/**
	 * Checks if a particular function is allowed to be called.
	 *
	 * Checks disabled functions and the function blacklist.
	 *
	 * @param string $func
	 *
	 * @return bool
	 */
	public static function is_func_allowed( $func ) {
		static $cache = array();
		static $disabled;
		static $suhosin;

		if ( isset( $cache[ $func ] ) ) {
			return $cache[ $func ];
		}

		if ( $disabled === null ) {
			$disabled = preg_split( '/\s*,\s*/', (string) ini_get( 'disable_functions' ) );
		}

		if ( $suhosin === null ) {
			$suhosin = preg_split( '/\s*,\s*/', (string) ini_get( 'suhosin.executor.func.blacklist' ) );
		}

		if ( ! is_callable( $func ) ) {
			return $cache[ $func ] = false;
		}

		if ( in_array( $func, $disabled, true ) ) {
			return $cache[ $func ] = false;
		}

		if ( in_array( $func, $suhosin, true ) ) {
			return $cache[ $func ] = false;
		}

		return $cache[ $func ] = true;
	}

}
