<?php

/*
Load the updater and licensing system without loading unneeded parts.
Written by Chris Jean for iThemes.com
Version 1.2.1

Version History
	1.0.0 - 2013-04-11 - Chris Jean
		Release ready
	1.0.1 - 2013-05-01 - Chris Jean
		Fixed a bug where some plugins caused the filter_update_plugins and filter_update_themes to run when load hadn't run, causing errors.
	1.1.0 - 2013-09-19 - Chris Jean
		Complete restructuring of this file as most of the code has been relocated to other files.
	1.2.0 - 2013-12-13 - Chris Jean
		Added the ability to force clear the server timeout hold by adding a query variable named ithemes-updater-force-clear-server-timeout-hold to the URL.
	1.2.1 - 2014-10-23 - Chris Jean
		Removed ithemes-updater-force-clear-server-timeout-hold code.
*/


if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI_Command' ) && ! class_exists( 'Ithemes_Updater_WP_CLI_Ithemes_Licensing' ) ) {
	require( dirname( __FILE__ ) . '/wp-cli.php' );
	WP_CLI::add_command( 'ithemes-licensing', 'Ithemes_Updater_WP_CLI_Ithemes_Licensing' );
}


if ( defined( 'ITHEMES_UPDATER_DISABLE' ) && ITHEMES_UPDATER_DISABLE ) {
	return;
}


$GLOBALS['ithemes_updater_path'] = dirname( __FILE__ );


if ( is_admin() ) {
	require( $GLOBALS['ithemes_updater_path'] . '/admin.php' );
}


function ithemes_updater_filter_update_plugins( $update_plugins ) {
	if ( ! class_exists( 'Ithemes_Updater_Settings' ) ) {
		require( $GLOBALS['ithemes_updater_path'] . '/settings.php' );
	}

	return $GLOBALS['ithemes-updater-settings']->filter_update_plugins( $update_plugins );
}
add_filter( 'site_transient_update_plugins', 'ithemes_updater_filter_update_plugins' );
add_filter( 'transient_update_plugins', 'ithemes_updater_filter_update_plugins' );


function ithemes_updater_filter_update_themes( $update_themes ) {
	if ( ! class_exists( 'Ithemes_Updater_Settings' ) ) {
		require( $GLOBALS['ithemes_updater_path'] . '/settings.php' );
	}

	return $GLOBALS['ithemes-updater-settings']->filter_update_themes( $update_themes );
}
add_filter( 'site_transient_update_themes', 'ithemes_updater_filter_update_themes' );
add_filter( 'transient_update_themes', 'ithemes_updater_filter_update_themes' );


function ithemes_updater_get_licensed_site_url() {
	if ( ! class_exists( 'Ithemes_Updater_Settings' ) ) {
		require( $GLOBALS['ithemes_updater_path'] . '/settings.php' );
	}

	return $GLOBALS['ithemes-updater-settings']->get_licensed_site_url();
}

function ithemes_updater_get_seen_hostnames() {
	if ( ! class_exists( 'Ithemes_Updater_Settings' ) ) {
		require( $GLOBALS['ithemes_updater_path'] . '/settings.php' );
	}

	return $GLOBALS['ithemes-updater-settings']->get_hostname_history();
}

function ithemes_updater_is_request_on_licensed_site_url() {
	if ( ! class_exists( 'Ithemes_Updater_Settings' ) ) {
		require( $GLOBALS['ithemes_updater_path'] . '/settings.php' );
	}

	return $GLOBALS['ithemes-updater-settings']->is_request_on_licensed_site_url();
}

function ithemes_updater_get_change_licensed_site_url( $redirect = '' ) {
	return admin_url( 'options-general.php?page=ithemes-licensing&action=change_licensed_site_url&redirect=' . urlencode( $redirect ) );
}

function ithemes_updater_change_licensed_site_url( $redirect = '' ) {
	wp_redirect( ithemes_updater_get_change_licensed_site_url( $redirect ) );
	exit();
}

function ithemes_updater_is_licensed_site_url_confirmed() {
	if ( ! class_exists( 'Ithemes_Updater_Settings' ) ) {
		require( $GLOBALS['ithemes_updater_path'] . '/settings.php' );
	}

	return $GLOBALS['ithemes-updater-settings']->is_licensed_site_url_confirmed();
}

function ithemes_updater_site_has_patchstack( $cache = true, $comparison_url = '' ) {
	if ( ! class_exists( 'Ithemes_Updater_Keys' ) ) {
		require( $GLOBALS['ithemes_updater_path'] . '/keys.php' );
	}

	if ( ! class_exists( 'Ithemes_Updater_Packages' ) ) {
		require( $GLOBALS['ithemes_updater_path'] . '/packages.php' );
	}

	$key = Ithemes_Updater_Keys::get( 'ithemes-security-pro' );

	if ( ! $key ) {
		return false;
	}

	if ( ! class_exists( 'Ithemes_Updater_API' ) ) {
		require( $GLOBALS['ithemes_updater_path'] . '/api.php' );
	}

	$quota = Ithemes_Updater_API::get_patchstack_quota( $key, $cache );
	$site_url = ithemes_updater_get_licensed_site_url();
	$site_url = preg_replace( '|^https?://|', '', $site_url );
	$site_url = str_replace( 'www.', '', $site_url );
	if ( $comparison_url ) {
		$comparison_url = preg_replace( '|^https?://|', '', $comparison_url );
		if ( $comparison_url === $site_url && in_array( $comparison_url, $quota['sites'], true ) ) {
			return true;
		}

		return false;
	}

	return in_array( $site_url, $quota['sites'], true );
}

function ithemes_updater_get_licensed_username( $package ) {
	if ( ! class_exists( 'Ithemes_Updater_API' ) ) {
		require( $GLOBALS['ithemes_updater_path'] . '/api.php' );
	}

	$details = Ithemes_Updater_API::get_package_details();

	if ( ! isset( $details['packages'][ $package ]['user'] ) ) {
		return '';
	}

	return $details['packages'][ $package ]['user'];
}
