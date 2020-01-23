<?php
/**
 * Stash v3 Destination main class.
 *
 * @package BackupBuddy
 */

/**
 * DO NOT CALL THIS CLASS DIRECTLY. CALL VIA: pb_backupbuddy_destination in bootstrap.php.
 */
class pb_backupbuddy_destination_stash3 {

	/**
	 * Minimum size, in MB to allow chunks to be. Anything less will not be chunked even if requested.
	 */
	const MINIMUM_CHUNK_SIZE = 5;

	/**
	 * Destination Properties.
	 *
	 * @var array
	 */
	public static $destination_info = array(
		'name'        => 'BackupBuddy Stash (v3)',
		'description' => '<b>The easiest of all destinations</b> for PHP v5.5+; just enter your iThemes login and Stash away! Store your backups in the BackupBuddy cloud safely with high redundancy and encrypted storage.  Supports multipart uploads for larger file support with both bursting and chunking. Active BackupBuddy customers receive <b>free</b> storage! Additional storage upgrades optionally available. <a href="http://ithemes.com/backupbuddy-stash/" target="_blank">Learn more here.</a>',
		'category'    => 'best', // best, normal, or legacy.
	);

	/**
	 * Default settings. Should be public static for auto-merging.
	 *
	 * @var array
	 */
	public static $default_settings = array(
		'data_version'            => '1',
		'type'                    => 'stash3',   // MUST MATCH your destination slug. Required destination field.
		'title'                   => '',         // Required destination field.

		'itxapi_username'         => '',         // Username to connect to iThemes API.
		'itxapi_token'            => '',         // Site token for iThemes API.

		'ssl'                     => '1',        // Whether or not to use SSL encryption for connecting.
		'server_encryption'       => 'AES256',   // Encryption (if any) to have the destination enact. Empty string for none.
		'max_time'                => '',         // Default max time in seconds to allow a send to run for. Set to 0 for no time limit. Aka no chunking.
		'max_burst'               => '10',       // Max size in mb of each burst within the same page load.
		'use_packaged_cert'       => '0',        // When 1, use the packaged cacert.pem file included with the AWS SDK.

		'db_archive_limit'        => '0',        // Maximum number of db backups for this site in this directory for this account. No limit if zero 0.
		'full_archive_limit'      => '0',        // Maximum number of full backups for this site in this directory for this account. No limit if zero 0.
		'themes_archive_limit'    => '0',
		'plugins_archive_limit'   => '0',
		'media_archive_limit'     => '0',
		'files_archive_limit'     => '0',

		'manage_all_files'        => '0',        // Allow user to manage all files in Stash? If enabled then user can view all files after entering their password. If disabled the link to view all is hidden.
		'disable_file_management' => '0',        // When 1, _manage.php will not load which renders remote file management DISABLED.
		'disabled'                => '0',        // When 1, disable this destination.
		'skip_bucket_prepare'     => '1',        // Always skip bucket prepare for Stash.
		'stash_mode'              => '1',        // Master destination is Stash.

		'_multipart_id'           => '',         // Instance var. Internal use only for continuing a chunked upload.
	);

	/**
	 * Init destination.
	 *
	 * @param array $settings  Destination settings.
	 *
	 * @return array  Formatted destination settings.
	 */
	public static function _init( $settings ) {
		require_once pb_backupbuddy::plugin_path() . '/destinations/s33/init.php';
		$settings = self::_formatSettings( $settings );
		return $settings;
	} // End _init().

	/**
	 * Send one or more files.
	 *
	 * @param array  $settings       Destination settings.
	 * @param array  $file           Array of one or more files to send (even though only 1 file is supported).
	 * @param string $send_id        The send ID.
	 * @param bool   $delete_after   Delete the file afterwards.
	 * @param bool   $clear_uploads  Clear uploads.
	 *
	 * @return bool|array  True on success, false on failure, array if a multipart chunked send so there is no status yet.
	 */
	public static function send( $settings = array(), $file, $send_id = '', $delete_after = false, $clear_uploads = false ) {
		require_once pb_backupbuddy::plugin_path() . '/lib/stash/stash-api.php';

		pb_backupbuddy::status( 'details', 'Starting stash3 send().' );
		$settings = self::_init( $settings );

		if ( '1' == $settings['disabled'] ) {
			self::_error( __( 'Error #48933: This destination is currently disabled. Enable it under this destination\'s Advanced Settings.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		if ( is_array( $file ) ) {
			$file = $file[0];
		}

		if ( '' == $settings['_multipart_id'] ) { // New transfer. Populate initial Stash settings.
			$result = BackupBuddy_Stash_API::send_file( $settings['itxapi_username'], $settings['itxapi_token'], $file );

			if ( true === $result ) {
				return true;
			}

			$response = BackupBuddy_Stash_API::get_fallback_upload_action_response( $settings['itxapi_username'], $settings['itxapi_token'], $file );

			if ( ! is_array( $response ) ) {
				$error = 'Error #82333232973: Unable to initiate Stash (v3) upload. Details: `' . $response . '`.';
				self::_error( $error );
				return false;
			}

			if ( '3' == pb_backupbuddy::$options['log_level'] ) { // Full logging enabled.
				pb_backupbuddy::status( 'details', 'Stash API upload action response due to logging level: `' . print_r( $response, true ) . '`.' );
			}

			$settings               = array_merge( $settings, $response );
			$settings['stash_mode'] = '1'; // Stash is calling the s33 destination.
		}

		// Send file.
		$result = pb_backupbuddy_destination_s33::send( $settings, $file, $send_id, $delete_after, $clear_uploads );

		if ( is_array( $result ) ) { // Chunking. Notify Stash API to kick cron.
			self::cron_kick_api( $settings, false );
		}

		return $result;
	} // End send().

	/**
	 * Test Destination Settings.
	 *
	 * @param array $settings  Destination Settings.
	 *
	 * @return bool  If test was successful.
	 */
	public static function test( $settings ) {
		$settings = self::_init( $settings );
		if ( '3' == pb_backupbuddy::$options['log_level'] ) { // Full logging enabled.
			error_log( 'Debug #8393283:' );
			error_log( print_r( $settings, true ) );
		}
		return pb_backupbuddy_destination_s33::test( $settings );
	} // End test().

	/**
	 * Communicate with the Stash API.
	 *
	 * @param array  $settings           Destination settings array.
	 * @param string $action             API verb/action to call.
	 * @param array  $additional_params  When false, we will handle parsing errors here, returning a string error message. When true, pass back the entire array from the server.
	 * @param bool   $blocking           Should it be blocking?
	 * @param bool   $passthru_errors    Passthru errors?
	 *
	 * @return array|string  Array with response data on success. String with error message if something went wrong. Auto-logs all errors to status log.
	 */
	public static function stashAPI( $settings, $action, $additional_params = array(), $blocking = true, $passthru_errors = false ) {
		require_once pb_backupbuddy::plugin_path() . '/lib/stash/stash-api.php';
		return BackupBuddy_Stash_API::request( $action, $settings, $additional_params, $blocking, $passthru_errors );
	} // End stashAPI().

	/**
	 * Get Stash quota.
	 *
	 * @param array $settings      Destination settings.
	 * @param bool  $bypass_cache  Should cache be ignored.
	 *
	 * @return array|false  Stash quota array or false on error.
	 */
	public static function get_quota( $settings, $bypass_cache = false ) {
		$settings = self::_init( $settings );

		$cache_time   = 60 * 5; // 5 minutes.
		$bypass_cache = true;

		if ( false === $bypass_cache ) {
			$transient = get_transient( 'pb_backupbuddy_stash3quota_' . $settings['itxapi_username'] );
			if ( false !== $transient ) {
				pb_backupbuddy::status( 'details', 'Stash quota information CACHED. Returning cached version.' );
				return $transient;
			}
		} else {
			pb_backupbuddy::status( 'details', 'Stash bypassing cached quota information. Getting new values.' );
		}

		// Contact API.
		$quota_data = self::stashAPI( $settings, 'quota' );

		if ( ! is_array( $quota_data ) ) {
			return false;
		} else {
			set_transient( 'pb_backupbuddy_stash3quota_' . $settings['itxapi_username'], $quota_data, $cache_time );
			return $quota_data;
		}
	} // End get_quota().

	/**
	 * Returns the site-specific remote path to store into.
	 * Slashes (caused by subdirectories in url) are replaced with underscores.
	 * Always has a leading and trailing slash.
	 *
	 * @param string $directory  Remote path/directory.
	 *
	 * @return string  Ex: /dustinbolton.com_blog/
	 */
	public static function get_remote_path( $directory = '' ) {
		$remote_path = str_replace( 'www.', '', site_url() );
		$remote_path = str_ireplace( 'http://', '', $remote_path );
		$remote_path = str_ireplace( 'https://', '', $remote_path );

		// $remote_path = preg_replace('/[^\da-z]/i', '_', $remote_path );
		$remote_path = str_ireplace( '/', '_', $remote_path );
		$remote_path = str_ireplace( '~', '_', $remote_path );
		$remote_path = str_ireplace( ':', '_', $remote_path );

		$remote_path = '/' . trim( $remote_path, '/\\' ) . '/';

		$directory = trim( $directory, '/\\' );
		if ( '' != $directory ) {
			$remote_path .= $directory . '/';
		}

		return $remote_path;

	} // End get_remote_path().

	/**
	 * Returns the progress quota bar showing usage.
	 *
	 * @param array $account_info  Array of account info from API call.
	 * @param array $echo          Should the output be echo'd.
	 *
	 * @return string|void  Quota bar HTML or void when echo'd.
	 */
	public static function get_quota_bar( $account_info, $echo = false ) {
		$return  = '<div class="backupbuddy-stash3-quotawrap">';
		$return .= '
		<style>
			.outer_progress {
				-moz-border-radius: 4px;
				-webkit-border-radius: 4px;
				-khtml-border-radius: 4px;
				border-radius: 4px;

				border: 1px solid #DDD;
				background: #EEE;

				max-width: 700px;

				margin-left: auto;
				margin-right: auto;

				height: 30px;
			}

			.inner_progress {
				border-right: 1px solid #85bb3c;
				background: #8cc63f url("' . pb_backupbuddy::plugin_url() . '/destinations/stash3/progress.png") 50% 50% repeat-x;

				height: 100%;
			}

			.progress_table {
				color: #5E7078;
				font-family: "Open Sans", Arial, Helvetica, Sans-Serif;
				font-size: 14px;
				line-height: 20px;
				text-align: center;

				margin-left: auto;
				margin-right: auto;
				margin-bottom: 20px;
				max-width: 700px;
			}
		</style>';

		if ( isset( $account_info['quota_warning'] ) && ( $account_info['quota_warning'] != '' ) ) {
			// echo '<div style="color: red; max-width: 700px; margin-left: auto; margin-right: auto;"><b>Warning</b>: ' . $account_info['quota_warning'] . '</div><br>';
		}

		$return .= '
		<div class="outer_progress">
			<div class="inner_progress" style="width: ' . $account_info['quota_used_percent'] . '%"></div>
		</div>

		<table align="center" class="progress_table">
			<tbody><tr align="center">
			    <td style="width: 10%; font-weight: bold; text-align: center">Free Tier</td>
			    <td style="width: 10%; font-weight: bold; text-align: center">Paid Tier</td>
			    <td style="width: 10%"></td>
			    <td style="width: 10%; font-weight: bold; text-align: center">Total</td>
			    <td style="width: 10%; font-weight: bold; text-align: center">Used</td>
			    <td style="width: 10%; font-weight: bold; text-align: center">Available</td>
			</tr>

			<tr align="center">
				<td style="text-align: center">' . $account_info['quota_free_nice'] . '</td>
				<td style="text-align: center">';
		if ( '0' == $account_info['quota_paid'] ) {
			$return .= 'none';
		} else {
			$return .= $account_info['quota_paid_nice'];
		}
					$return .= '</td>
				<td></td>
				<td style="text-align: center">' . $account_info['quota_total_nice'] . '</td>
				<td style="text-align: center">' . $account_info['quota_used_nice'] . ' (' . $account_info['quota_used_percent'] . '%)</td>
				<td style="text-align: center">' . $account_info['quota_available_nice'] . '</td>
			</tr>
			';
		$return             .= '
		</tbody></table>';

		$return .= '<div style="text-align: center;">';
		$return .= '
		<b>' . __( 'Upgrade storage', 'it-l10n-backupbuddy' ) . ':</b> &nbsp;
		<a href="https://ithemes.com/member/cart.php?action=add&id=290" target="_blank" style="text-decoration: none;">+ 5GB</a>, &nbsp;
		<a href="https://ithemes.com/member/cart.php?action=add&id=291" target="_blank" style="text-decoration: none;">+ 10GB</a>, &nbsp;
		<a href="https://ithemes.com/member/cart.php?action=add&id=292" target="_blank" style="text-decoration: none;">+ 25GB</a>

		&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;<a href="https://sync.ithemes.com/stash/" target="_blank" style="text-decoration: none;"><b>Manage Stash & Stash Live Files</b></a>&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;<a href="https://sync.ithemes.com/stash/" target="_blank" style="text-decoration: none;"><b>Manage Account</b></a>';

		// Welcome text.
		$up_path = '/';

		$return .= '<br><br></div>';
		$return .= '</div>';

		if ( false === $echo ) {
			return $return;
		}

		echo $return;
	} // End get_quota_bar().

	/**
	 * Format/normalize settings.
	 *
	 * @param array $settings  Destination settings.
	 *
	 * @return array  Formatted settings.
	 */
	public static function _formatSettings( $settings ) {
		$settings['skip_bucket_prepare'] = '1';
		$settings['stash_mode']          = '1';
		return pb_backupbuddy_destination_s33::_formatSettings( $settings );
	} // End _formatSettings().

	/**
	 * Get Files from Stash API.
	 *
	 * @param array      $settings   Destination settings.
	 * @param bool|array $extensions Array of extensions.
	 *
	 * @return array  Array of files.
	 */
	public static function get_files( $settings, $extensions = false ) {
		$site_only = isset( $settings['site_only'] ) ? $settings['site_only'] : true;
		if ( ! empty( $settings['remote_path'] ) ) {
			$prefix = $settings['remote_path'];
		}

		require_once pb_backupbuddy::plugin_path() . '/lib/stash/stash-api.php';

		$settings = self::_init( $settings );

		if ( $site_only ) {
			$backups = BackupBuddy_Stash_API::list_site_files( $settings['itxapi_username'], $settings['itxapi_token'], $extensions );
		} else {
			$backups = BackupBuddy_Stash_API::list_files( $settings['itxapi_username'], $settings['itxapi_token'], $extensions );
		}
		return $backups;
	}

	/**
	 * Get list of files with optional prefix. Returns stashAPI response.
	 *
	 * @param array  $settings  Destination Settings.
	 * @param string $mode      Output mode.
	 *
	 * @return array  Array of backups.
	 */
	public static function listFiles( $settings, $mode = 'default' ) {
		global $pb_backupbuddy_destination_errors;
		if ( '1' == $settings['disabled'] ) {
			$pb_backupbuddy_destination_errors[] = __( 'Error #48933: This destination is currently disabled. Enable it under this destination\'s Advanced Settings.', 'it-l10n-backupbuddy' );
			return false;
		}

		if ( 'live' === $settings['type'] ) {
			$settings['remote_path'] = 'snapshot-'; // . backupbuddy_core::backup_prefix();
		} else {
			$settings['remote_path'] = 'backup-'; // . backupbuddy_core::backup_prefix();
		}

		$prefix  = $settings['remote_path'];
		$backups = self::get_files( $settings );

		$backup_list       = array();
		$backup_sort_dates = array();

		foreach ( (array) $backups as $file ) {
			$backup = $file['filename'];
			if ( $prefix && ! backupbuddy_core::startsWith( basename( $file['filename'] ), $prefix ) ) { // Only show backups for this site unless set to show all.
				continue;
			}

			$backup_type = backupbuddy_core::getBackupTypeFromFile( $backup, false, true );

			if ( ! $backup_type ) {
				continue;
			}

			$uploaded    = $file['uploaded_timestamp'];
			$backup_date = backupbuddy_core::parse_file( $backup, 'timestamp' );
			$size        = (double) $file['size'];

			add_filter( 'backupbuddy_backup_columns', array( 'pb_backupbuddy_destination_stash3', 'set_table_column_header' ), 10, 2 );

			if ( 'live' === $settings['type'] ) {
				$backup_array = array(
					array(
						$backup,
						$backup_date,
					),
					backupbuddy_core::pretty_backup_type( $backup_type ),
					pb_backupbuddy::$format->file_size( $size ),
				);
			} else {
				$backup_array = array(
					array(
						$backup,
						$backup_date,
					),
					backupbuddy_core::pretty_backup_type( $backup_type ),
					pb_backupbuddy::$format->file_size( $size ),
				);
			}

			if ( 'restore' === $mode ) {
				$backup_array[] = backupbuddy_backups()->get_details_link( $backup );
			}

			if ( 'default' === $mode ) {
				$download_link  = admin_url() . sprintf( '?stash3-destination-id=%s&stash3-download=%s', backupbuddy_backups()->get_destination_id(), rawurlencode( $backup ) );
				$copy_link      = '&cpy=' . rawurlencode( $backup );
				$actions        = array(
					$download_link => __( 'Download Backup', 'it-l10n-backupbuddy' ),
					$copy_link     => __( 'Copy to Local', 'it-l10n-backupbuddy' ),
				);
				$backup_array[] = backupbuddy_backups()->get_action_menu( $backup, $actions );
			} elseif ( 'restore' === $mode ) {
				$backup_array[] = backupbuddy_backups()->get_restore_buttons( $backup, $backup_type );
			}

			$backup_list[ $backup ]       = $backup_array;
			$backup_sort_dates[ $backup ] = $backup_date;
		}

		$backup_list = backupbuddy_backups()->sort_backups( $backup_list, $backup_sort_dates );

		return $backup_list;
	} // End listFiles().

	/**
	 * Alter the backup table first column heading.
	 *
	 * @param array  $columns  Array of column headings.
	 * @param object $obj      BackupBuddy_Backups class instance.
	 */
	public static function set_table_column_header( $columns, $obj ) {
		if ( ! $obj->is_remote() ) {
			return $columns;
		}

		$settings = pb_backupbuddy::$options['remote_destinations'][ $obj->get_destination_id() ];

		if ( 'live' === $settings['type'] ) {
			$columns[0] = __( 'Snapshots Stored Remotely on Stash Live Servers', 'it-l10n-backupbuddy' );
		} else {
			$columns[0] = __( 'Stash Traditional Backup Files', 'it-l10n-backupbuddy' );
		}

		return $columns;
	}

	/**
	 * Alias to deleteFiles().
	 *
	 * @param array  $settings  Destination settings.
	 * @param string $file      File to delete.
	 *
	 * @return bool|string  True if success, else string error message.
	 */
	public static function deleteFile( $settings, $file ) {
		return self::deleteFiles( $settings, $file );
	} // End deleteFile().

	/**
	 * Delete files.
	 *
	 * @param array $settings  Destination settings.
	 * @param array $files     Array of files to delete.
	 *
	 * @return bool|string  True if success, else string error message.
	 */
	public static function deleteFiles( $settings, $files = array() ) {
		require_once pb_backupbuddy::plugin_path() . '/lib/stash/stash-api.php';

		$settings = self::_init( $settings );

		if ( ! is_array( $files ) ) {
			$file = array( $files );
		}

		if ( true === BackupBuddy_Stash_API::delete_files( $settings['itxapi_username'], $settings['itxapi_token'], $files ) ) {
			return true;
		}

		$remote_path = self::get_remote_path(); // Has leading and trailng slashes.
		$manage_data = self::stashAPI( $settings, 'manage' );
		if ( ! is_array( $manage_data ) ) {
			$error = 'Error #47349723: Unable to initiate file deletion for file(s) `' . implode( ', ', $files ) . '`. Details: `' . print_r( $manage_data, true ) . '`.';
			self::_error( $error );
			return $error;
		}
		$settings['bucket']      = $manage_data['bucket'];
		$settings['credentials'] = $manage_data['credentials'];

		$settings['directory'] = $manage_data['subkey'];
		foreach ( $files as &$file ) {
			$file = $remote_path . $file;
		}

		return pb_backupbuddy_destination_s33::deleteFiles( $settings, $files );

	} // End deleteFiles().

	/**
	 * Enforce archive limits.
	 *
	 * Called from s32 init.php.
	 *
	 * @param array  $settings     Destination settings.
	 * @param string $backup_type  Type of backup.
	 *
	 * @return bool  If limits were enforced.
	 */
	public static function archiveLimit( $settings, $backup_type ) {
		pb_backupbuddy::status( 'details', 'Starting remote archive limiting. Limiting to `' . $settings['db_archive_limit'] . '` database and `' . $settings['full_archive_limit'] . '` full archives based on destination settings.' );

		$settings = self::_init( $settings );

		$additional_params = array(
			'types'  => array(
				'db'      => $settings['db_archive_limit'],
				'full'    => $settings['full_archive_limit'],
				'themes'  => $settings['themes_archive_limit'],
				'plugins' => $settings['plugins_archive_limit'],
				'media'   => $settings['media_archive_limit'],
				'files'   => $settings['files_archive_limit'],
			),
			'delete' => true,
		);

		if ( '3' == pb_backupbuddy::$options['log_level'] ) { // Full logging enabled.
			pb_backupbuddy::status( 'details', 'Trim params based on settings: `' . print_r( $additional_params, true ) . '`.' );
		}

		$response = self::stashAPI( $settings, 'trim', $additional_params );
		if ( ! is_array( $response ) ) {
			$error = 'Error #8329545445573: Unable to trim Stash (v3) upload. Details: `' . print_r( $response, true ) . '`.';
			self::_error( $error );
			return false;
		} else {
			pb_backupbuddy::status( 'details', 'Trimmed remote archives. Results: `' . print_r( $response, true ) . '`.' );
		}

		return true;
	} // End archiveLimit();

	/**
	 * Find Xth occurance position from the write.
	 *
	 * @param string $haystack  String to search.
	 * @param string $needle    String to find.
	 * @param int    $count     Desired nth count.
	 *
	 * @return int  String position.
	 */
	public static function strrpos_count( $haystack, $needle, $count ) {
		if ( $count <= 0 ) {
			return false;
		}

		$len = strlen( $haystack );
		$pos = $len;

		for ( $i = 0; $i < $count && $pos; $i++ ) {
			$pos = strrpos( $haystack, $needle, $pos - $len - 1 );
		}

		return $pos;
	} // End _strrpost_count().

	/**
	 * Log error into status logger and destination error global.
	 *
	 * @param string $message  The error message.
	 *
	 * @return false  Always false.
	 */
	private static function _error( $message ) {
		global $pb_backupbuddy_destination_errors;
		$pb_backupbuddy_destination_errors[] = $message;
		pb_backupbuddy::status( 'error', 'Error #3892343283: ' . $message );
		return false;
	}

	/**
	 * Instructs Stash API to kick our cron at this URL. Note: If $blocking === false then the HTTP request is non-blocking and we will get no response logged.
	 *
	 * @param array $settings  Destination settings.
	 * @param bool  $blocking  Should be blocking?
	 *
	 * @return bool  If kicked.
	 */
	public static function cron_kick_api( $settings, $blocking = true ) {
		$activity_time_file = backupbuddy_core::getLogDirectory() . 'live/cron_kick-' . pb_backupbuddy::$options['log_serial'] . '.txt';

		if ( file_exists( $activity_time_file ) ) {
			$mtime = @filemtime( $activity_time_file );
			if ( false !== $mtime ) {
				$ago = time() - $mtime;
				if ( $ago < backupbuddy_constants::MINIMUM_CRON_KICK_INTERVAL ) { // Trying to kick too soon.
					pb_backupbuddy::status( 'details', 'cron-kick API call too soon (' . pb_backupbuddy::$format->time_ago( $mtime ) . ' ago). Skipping for now.' );
					return true;
				}
			}
		}

		@touch( $activity_time_file );

		$response = pb_backupbuddy_destination_stash3::stashAPI( $settings, 'cron-kick', array(), $blocking );
		if ( false === $blocking ) {
			return true;
		}
		if ( ! is_array( $response ) ) { // Error message.
			pb_backupbuddy::status( 'error', 'Error #3279723: Unexpected server response. Detailed response: `' . print_r( $response, true ) . '`.' );
		} else { // Errors.
			if ( isset( $response['error'] ) ) {
				pb_backupbuddy::status( 'error', $response['error']['message'] );
			} else { // No error?
				if ( ! isset( $response['success'] ) || ( '1' != $response['success'] ) ) {
					pb_backupbuddy::status( 'error', 'Error #3289327932: Something went wrong. Success was not reported. Detailed response: `' . print_r( $response, true ) . '`.' );
				} else {
					pb_backupbuddy::status( 'details', 'Successfully inititated cron kicker with API.' );
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Get URL to remote file.
	 *
	 * @param array  $settings     Destination settings.
	 * @param string $remote_file  The backup filename.
	 *
	 * @return string|false  URL to file or false on fail.
	 */
	public static function get_file_url( $settings, $remote_file ) {
		$ext   = substr( $remote_file, -4 );
		$files = self::get_files( $settings, array( $ext ) );
		foreach ( $files as $file ) {
			$filename = $file['filename'];
			if ( $remote_file === $filename ) {
				return $file['url'];
			}
		}
		return false;
	}

	/**
	 * Download remote file to local.
	 *
	 * @param array  $settings          Destination settings.
	 * @param string $remote_file       File URL.
	 * @param string $destination_file  Local destination to copy file.
	 *
	 * @return bool  If successful.
	 */
	public static function download_file( $settings, $remote_file, $destination_file ) {
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		pb_backupbuddy::status( 'details', 'About to begin downloading `' . $remote_file . '` from URL.' );
		$url      = self::get_file_url( $settings, $remote_file );
		$download = download_url( $url );
		pb_backupbuddy::status( 'details', 'Download process complete.' );

		if ( is_wp_error( $download ) ) {
			$error = 'Error #83444: Unable to download file `' . $remote_file . '` from URL: `' . $url . '`. Details: `' . $download->get_error_message() . '`.';
			pb_backupbuddy::status( 'error', $error );
			pb_backupbuddy::alert( $error );
			return false;
		}

		if ( false === copy( $download, $destination_file ) ) {
			$error = 'Error #3344433: Unable to copy file from `' . $download . '` to `' . $destination_file . '`.';
			pb_backupbuddy::status( 'error', $error );
			pb_backupbuddy::alert( $error );
			@unlink( $download );
			return false;
		}

		pb_backupbuddy::status( 'details', 'File saved to `' . $destination_file . '`.' );
		@unlink( $download );
		return true;
	}

	/**
	 * Download all backup dat files.
	 *
	 * @param array $settings  Destination Settings array.
	 *
	 * @return bool  If successful or not.
	 */
	public static function download_dat_files( $settings ) {
		$settings  = self::_init( $settings );
		$dat_files = self::get_files( $settings, array( '.dat' ) );
		$success   = true;

		if ( ! count( $dat_files ) ) {
			return false;
		}

		foreach ( $dat_files as $dat_file ) {
			$local_file = backupbuddy_core::getBackupDirectory() . basename( $dat_file['basename'] );

			if ( true !== self::download_file( $settings, $dat_file['filename'], $local_file ) ) {
				$success = false;
			}
		}

		return $success;
	}

} // End class.
