<?php

/*
Provides a simple interface for connecting iThemes' packages with the updater API.
Written by Chris Jean for iThemes.com
Version 1.4.1

Version History
	1.0.0 - 2013-04-11 - Chris Jean
		Release ready
	1.0.1 - 2013-09-19 - Chris Jean
		Changed the logic in process_server_response to skip updatable packages that have the 'upgrade' data set to a true value.
		Updated requires to not use dirname().
		Updated ithemes-updater-object to ithemes-updater-settings.
	1.1.0 - 2013-10-02 - Chris Jean
		Updated 'url' data for themes to point to the plugin-install.php file in order to show changelog notes as plugins have.
	1.2.0 - 2013-10-04 - Chris Jean
		Added logic to handle skipped updates when force_minor_version_update is set.
	1.2.1 - 2013-10-04 - Chris Jean
		Added a fix to prevent the code from executing if it is loaded by an older updater version. This can happen when updating a theme or plugin.
	1.3.0 - 2013-10-23 - Chris Jean
		Enhancement: Added support for quick_releases setting to force an update to a quick release.
	1.4.0 - 2014-11-13 - Chris Jean
		Improved cache flush handling.
		Removed server-cache setting change handler.
		Added timeout-multiplier setting change handler.
	1.4.1 - 2015-04-23 - Chris Jean
		Added "plugin" entry for plugins in order to handle changes in WordPress 4.2.
		Added "theme" entry for themes in order to handle changes in WordPress 4.2.
		Added support for both "autoupdate" and "upgrade_notice" fields to be supplied from the server.
*/


class Ithemes_Updater_Updates {
	public static function run_update() {
		// Prevent the code from running if the code was loaded by an older updater version.
		if ( ! isset( $GLOBALS['ithemes_updater_path'] ) ) {
			return;
		}

		require_once( $GLOBALS['ithemes_updater_path'] . '/api.php' );
		require_once( $GLOBALS['ithemes_updater_path'] . '/packages.php' );
		require_once( $GLOBALS['ithemes_updater_path'] . '/keys.php' );


		$keys = Ithemes_Updater_Keys::get();
		$legacy_keys = Ithemes_Updater_Keys::get_legacy();

		if ( empty( $keys ) && empty( $legacy_keys ) ) {
			return;
		}


		Ithemes_Updater_API::get_package_details( false );
	}

	public static function process_server_response( $response, $cached = false ) {
		if ( empty( $response['packages'] ) ) {
			return;
		}


		require_once( $GLOBALS['ithemes_updater_path'] . '/keys.php' );
		require_once( $GLOBALS['ithemes_updater_path'] . '/packages.php' );
		require_once( $GLOBALS['ithemes_updater_path'] . '/settings.php' );


		$keys = array();

		foreach ( $response['packages'] as $package => $data ) {
			if ( isset( $data['key'] ) ) {
				$keys[$package] = $data['key'];
			} else if ( isset( $data['status'] ) && ( 'inactive' == $data['status'] ) ) {
				$keys[$package] = '';
			}
		}

		Ithemes_Updater_Keys::set( $keys );


		$details = Ithemes_Updater_Packages::get_full_details( $response );

		$updates = array(
			'update_themes'            => array(),
			'update_themes_no_update'  => array(),
			'update_plugins'           => array(),
			'update_plugins_no_update' => array(),
			'package_details'          => array(),
			'expiration'               => $details['expiration'],
		);

		if ( ! $cached ) {
			$updates['timestamp'] = time();
		}


		if ( isset( $response['timeout_multiplier'] ) ) {
			$updates['timeout-multiplier'] = $response['timeout_multiplier'];
		}

		if ( ! isset( $updates['timeout-multiplier'] ) || ( $updates['timeout-multiplier'] < 1 ) ) {
			$updates['timeout-mulitplier'] = 1;
		} else if ( $updates['timeout-multiplier'] > 10 ) {
			$updates['timeout-mulitplier'] = 10;
		}

		$use_ssl = $GLOBALS['ithemes-updater-settings']->get_option( 'use_ssl' );


		foreach ( $details['packages'] as $path => $data ) {

			if ( ! isset( $data['status'] ) ) {
				$data['status'] = $GLOBALS['ithemes-updater-settings']->get_license_status( $data['package'] );
			}

			$updates['package_details'][$data['package']] = array(
				'type'           => $data['type'],
				'path'           => $path,
				'version'        => $data['installed'],
				'license_status' => $data['status'],
			);

			if ( empty( $data['package-url'] ) ) {
				continue;
			}


			$force_minor_version_update = $GLOBALS['ithemes-updater-settings']->get_option( 'force_minor_version_update' );
			$quick_releases = $GLOBALS['ithemes-updater-settings']->get_option( 'quick_releases' );

			$update_available = true;

			if ( version_compare( $data['installed'], $data['available'], '>=' ) ) {
				$update_available = false;
			} else if ( ( isset( $data['upgrade'] ) && ! $data['upgrade'] ) && ! $force_minor_version_update && ! $quick_releases ) {
				$update_available = false;
			}


			$update = $data['wp_update_data'];

			if ( 'plugin' == $data['type'] ) {
				$update['slug']   = dirname( $path );
				$update['plugin'] = $path;

				if ( isset( $update['compatibility'] ) && is_array( $update['compatibility'] ) ) {
					$update['compatibility'] = (object) $update['compatibility'];
				}
			} else {
				$update['theme'] = $path;
				$update['url']   = self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . dirname( $path ) . '&section=changelog&TB_iframe=true&width=600&height=800' );
			}

			if ( ! $update_available ) {
				unset( $update['autoupdate'] );
				unset( $update['disable_autoupdate'] );
				unset( $update['upgrade_notice'] );
			}

			if ( ! $use_ssl ) {
				$update['package'] = preg_replace( '/^https/', 'http', $update['package'] );
			}


			if ( 'plugin' == $data['type'] ) {
				$update = (object) $update;
			} else {
				$path = dirname( $path );
			}


			if ( $update_available ) {
				$updates["update_{$data['type']}s"][$path] = $update;
			} else {
				$updates["update_{$data['type']}s_no_update"][$path] = $update;
			}
		}


		$GLOBALS['ithemes-updater-settings']->update_options( $updates );
	}
}
