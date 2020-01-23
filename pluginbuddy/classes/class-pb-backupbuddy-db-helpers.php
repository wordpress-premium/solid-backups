<?php
/**
 * Database Helpers Class for PluginBuddy
 *
 * @package BackupBuddy
 * @author Brian DiChiara <bdichiara@ithemes.com>
 * @since 8.2.7
 */

/**
 * Adds static methods to get information about database version and type.
 */
class PB_Backupbuddy_DB_Helpers {

	/**
	 * Checks if properties have been initialized yet.
	 *
	 * @var bool
	 */
	private static $has_init = false;

	/**
	 * Is MariaDB being used? Default false.
	 *
	 * @var bool
	 */
	private static $is_maria_db = false;

	/**
	 * Is MySQL being Used? Default: true.
	 *
	 * @var bool
	 */
	private static $is_mysql = true;

	/**
	 * Local cache storage
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * WP Cache Group
	 *
	 * @var string
	 */
	private static $wp_cache_group = 'PB_Backupbuddy_DB_Helpers';

	/**
	 * Local and WP Cache Retrieval
	 *
	 * @param string $key  Cache storage key.
	 *
	 * @return mixed|false  Cached value or false when not present.
	 */
	public static function cache_get( $key ) {
		$value = isset( self::$cache[ $key ] ) ? self::$cache[ $key ] : false;
		if ( function_exists( 'wp_cache_get' ) ) {
			$value = wp_cache_get( $key, self::$wp_cache_group );
		}

		return $value;
	}

	/**
	 * Local and WP Cache setting.
	 *
	 * @param string $key    Cache storage key.
	 * @param mixed  $value  Value to store.
	 *
	 * @return true  Always returns true.
	 */
	public static function cache_set( $key, $value ) {
		self::$cache[ $key ] = $value;

		if ( function_exists( 'wp_cache_set' ) ) {
			return wp_cache_set( $key, $value, self::$wp_cache_group );
		}

		return true;
	}

	/**
	 * Retrieve and store version information about the database.
	 *
	 * @return array  Contains version and type information.
	 */
	public static function get_db_version_info() {
		// Pull from cache first.
		$version_info = self::cache_get( 'db_version_info' );

		if ( false === $version_info ) {
			global $wpdb;

			$version_info = array();

			// Retrieves raw MySQL or MariaDB version.
			$result = $wpdb->get_row( 'SELECT VERSION() as `version`' );

			if ( $result ) {
				$version_info['db_version'] = $result->version;
			}

			// Fallback on mysql*_get_server_info.
			if ( ! isset( $version_info['db_version'] ) ) {
				if ( $wpdb->use_mysqli ) {
					$version_info['db_version'] = mysqli_get_server_info( $wpdb->dbh );
				} else {
					$version_info['db_version'] = mysql_get_server_info( $wpdb->dbh );
				}
			}

			if ( isset( $version_info['db_version'] ) ) {
				// Mark if using MariaDB.
				$version_info['is_mariadb'] = ( false !== stripos( $version_info['db_version'], 'mariadb' ) );
				self::$is_maria_db          = $version_info['is_mariadb'];
				$version_info['is_mysql']   = ! $version_info['is_mariadb']; // Store the opposite of if MariaDB.
				self::$is_mysql             = $version_info['is_mysql'];

				// Store real version number (x.x.x), stripping out additional text.
				if ( true === $version_info['is_mariadb'] && preg_match( '/(.\d+\-)\K(\d+\.)(\d+\.)(\d+)/', $version_info['db_version'], $maria_db_version ) ) {
					// MariaDB may look like 5.5.5-10.x.x-MariaDB~ so check for this format first.
					$version_info['version'] = $maria_db_version[0];
				} elseif ( preg_match( '/(\d+\.)(\d+\.)(\d+)/', $version_info['db_version'], $raw_db_version ) ) {
					// If not prefixed with 5.5.5-, just get the first semantic version number.
					$version_info['version'] = $raw_db_version[0];
				} else {
					// Fallback on whatever $wpdb returns.
					$version_info['version'] = $wpdb->db_version();
				}
			}

			if ( count( $version_info ) > 0 ) {
				// Store in Object cache.
				self::cache_set( 'db_version_info', $version_info );
			}

			self::$has_init = true;
		}

		return $version_info;
	}

	/**
	 * Checks if server is running MariaDB.
	 *
	 * @return bool  If MariaDB is being used.
	 */
	public static function is_maria_db() {
		if ( false === self::$has_init ) {
			self::get_db_version_info();
		}
		return self::$is_maria_db;
	}

	/**
	 * Checks if server is running MySQL.
	 *
	 * @return bool  If MySQL is being used.
	 */
	public static function is_mysql() {
		if ( false === self::$has_init ) {
			self::get_db_version_info();
		}
		return self::$is_mysql;
	}

	/**
	 * Gets Real DB Version, either MariaDB or MySQL version.
	 *
	 * @return string  Version of MariaDB or MySQL.
	 */
	public static function get_db_version() {
		$version_info = self::get_db_version_info();
		if ( isset( $version_info['version'] ) ) {
			return $version_info['version'];
		}
		global $wpdb;
		return $wpdb->db_version();
	}
}
