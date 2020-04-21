<?php

/*
Misc functions to assist the updater code.
Written by Chris Jean for iThemes.com
Version 1.0.0

Version History
	1.0.0 - 2013-04-11 - Chris Jean
		Release ready
*/


class Ithemes_Updater_Functions {
	public static function get_url( $path ) {
		$path = str_replace( '\\', '/', $path );
		$wp_content_dir = str_replace( '\\', '/', WP_CONTENT_DIR );

		if ( 0 === strpos( $path, $wp_content_dir ) )
			return content_url( str_replace( $wp_content_dir, '', $path ) );

		$abspath = str_replace( '\\', '/', ABSPATH );

		if ( 0 === strpos( $path, $abspath ) )
			return site_url( str_replace( $abspath, '', $path ) );

		$wp_plugin_dir = str_replace( '\\', '/', WP_PLUGIN_DIR );
		$wpmu_plugin_dir = str_replace( '\\', '/', WPMU_PLUGIN_DIR );

		if ( 0 === strpos( $path, $wp_plugin_dir ) || 0 === strpos( $path, $wpmu_plugin_dir ) )
			return plugins_url( basename( $path ), $path );

		return false;
	}

	public static function get_package_name( $package ) {
		$name = str_replace( 'builderchild', 'Builder Child', $package );
		$name = str_replace( '-', ' ', $name );
		$name = ucwords( $name );
		$name = str_replace( 'buddy', 'Buddy', $name );
		$name = str_replace( 'Ithemes', 'iThemes', $name );

		return $name;
	}

	public static function get_post_data( $vars, $fill_missing = false ) {
		$data = array();

		foreach ( $vars as $var ) {
			if ( isset( $_POST[$var] ) ) {
				$clean_var = preg_replace( '/^it-updater-/', '', $var );
				$data[$clean_var] = $_POST[$var];
			}
			else if ( $fill_missing ) {
				$data[$var] = '';
			}
		}

		return stripslashes_deep( $data );
	}

	public static function get_site_option( $option ) {
		global $wpdb;

		$options = get_site_option( $option, false );

		if ( is_array( $options ) ) {
			return $options;
		}

		// Attempt to get the stored option manually to ensure that the failure wasn't due to a DB or other glitch.

		if ( is_multisite() ) {
			$network_id = get_current_network_id();
			$query = $wpdb->prepare( "SELECT meta_value FROM $wpdb->sitemeta WHERE meta_key = %s AND site_id = %d LIMIT 1", $option, $network_id );
			$index = 'meta_value';
		} else {
			$query = $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option );
			$index = 'option_value';
		}

		$result = $wpdb->query( $query );

		if ( false === $result ) {
			// Something went wrong with the DB.
			return false;
		}

		if ( empty( $wpdb->last_result[0] ) || empty( $wpdb->last_result[0]->$index ) ) {
			// The value has not been set yet, return the default value.
			return null;
		}

		$value = $wpdb->last_result[0]->$index;

		$options = @unserialize( $value );

		if ( is_array( $options ) ) {
			return $options;
		}

		// Seems that we have some bad data.
		// TODO: Attempt data restoration.

		return null;
	}
}
