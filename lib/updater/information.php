<?php

/*
Provides details formatted for use in "View version *** details" boxes.
Written by Chris Jean for iThemes.com
Version 1.1.1

Version History
	1.0.0 - 2013-04-11 - Chris Jean
		Release ready
	1.0.1 - 2013-09-19 - Chris Jean
		Updated requires to not use dirname().
	1.1.0 - 2013-10-02 - Chris Jean
		Added get_theme_information().
	1.1.1 - 2013-12-18 - Chris Jean
		Removed unneeded code that checked package-info.ithemes.com.
*/


class Ithemes_Updater_Information {
	public static function get_theme_information( $path ) {
		return self::get_plugin_information( "$path/style.css" );
	}

	public static function get_plugin_information( $path ) {
		require_once( $GLOBALS['ithemes_updater_path'] . '/packages.php' );
		$details = Ithemes_Updater_Packages::get_full_details();

		if ( ! isset( $details['packages'][$path] ) )
			return false;


		$package = $details['packages'][$path];

		require_once( $GLOBALS['ithemes_updater_path'] . '/functions.php' );
		require_once( $GLOBALS['ithemes_updater_path'] . '/information.php' );

		$changelog = Ithemes_Updater_API::get_package_changelog( $package['package'], $details['packages'][$path]['installed'] );

		if ( is_wp_error( $changelog ) ) {
			/* translators: 1. Error message, 2. Error code */
			$changelog = sprintf( __( '<p>Unable to get changelog data at this time.</p><p>%1$s (%2$s)</p>', 'it-l10n-backupbuddy' ), esc_html( $changelog->get_error_message() ), esc_html( $changelog->get_error_code() ) );
		}


		$info = array(
			'name'          => esc_html( Ithemes_Updater_Functions::get_package_name( $package['package'] ) ),
			'slug'          => dirname( $path ),
			'version'       => isset( $package['available'] ) ? $package['available'] : '',
			'author'        => '<a href="https://solidwp.com/">SolidWP</a>',
			'download_link' => isset( $package['package-url'] ) ? $package['package-url'] : '',
			'sections'      => array(
				'changelog'    => $changelog,
			),
		);


		return (object) $info;
	}
}
