<?php
/**
 * StellarWP Telemetry functions.
 *
 * @link https://github.com/stellarwp/telemetry
 *
 * @package Solid_Backups
 * @since 9.1.6
 */

use Solid_Backups\Strauss\StellarWP\Telemetry\Config;
use Solid_Backups\Strauss\StellarWP\Telemetry\Core as Telemetry;
use Solid_Backups\Strauss\StellarWP\Telemetry\Opt_In\Status;

Class Solid_Backups_Telemetry {

	// The plugin slug.
	const SLUG = 'solid-backups';

	public static function run_hooks() {
		add_action( 'plugins_loaded', array( __CLASS__, 'initialize' ) );
		add_action( 'admin_footer', array( __CLASS__, 'render_opt_in' ), 99 );
		add_filter( 'stellarwp/telemetry/optin_args', array( __CLASS__, 'optin_args' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'save_opt_in_setting_field' ) );
	}

	/**
	 * Initialize the Telemetry library.
	 *
	 * @action plugins_loaded
	 *
	 * @since 9.1.6
	 */
	public static function initialize() {
		$container = new Solid_Backups\Container();
		Config::set_container( $container );

		// Set a unique prefix for actions & filters.
		Config::set_hook_prefix( self::SLUG );

		// Set a unique plugin slug.
		Config::set_stellar_slug( self::SLUG );

		// Initialize the library.
		Telemetry::instance()->init( BACKUPBUDDY_PLUGIN_FILE );
	}

	/**
	 * Customize the Telemetry opt-in modal.
	 *
	 * @since 9.1.6
	 *
	 * @filter stellarwp/telemetry/optin_args
	 *
	 * @link https://github.com/stellarwp/telemetry/blob/develop/docs/filters.md#stellarwptelemetryoptin_args
	 *
	 * @param array $args The default arguments.
	 * @param string $slug The plugin slug.
	 *
	 * @return array
	 */
	public static function optin_args( $args, $slug ) {
		if ( self::SLUG === $slug ) {
			$args['plugin_name']        = 'Solid Backups';
			$args['plugin_slug']        = self::SLUG;
			$args['heading']            = __( 'Help us improve Solid Backups', 'it-l10n-backupbuddy' );
			$args['intro']              = sprintf(
				// translators: The user's display name.
				esc_html__( 'Hi %s! At Solid, we\'re committed to delivering top-notch services, and your valuable insights play a crucial role in helping us achieve that goal. We\'re excited to invite you to participate in our opt-in program, designed to enhance your experience with Solid Backups and contribute to the continuous improvement of StellarWP products. By opting in, you allow our teams to access certain data related to your website data. This information will be used responsibly to gain insights into your preferences and patterns, enabling us to tailor our services and products to better meet your needs. Rest assured, we take data privacy seriously, and our usage of your information will adhere to the highest standards, respecting all relevant regulations and guidelines. Your trust means the world to us, and we are committed to maintaining the confidentiality and security of your data. To join this initiative and be part of shaping the future of Solid Backups and StellarWP, simply click “Allow & Continue” below.', 'it-l10n-backupbuddy' ),
				$args['user_name']
			);
			$args['plugin_logo']        = plugin_dir_url( BACKUPBUDDY_PLUGIN_FILE ) . 'assets/dist/images/solid-backups-logo.svg';
			$args['plugin_logo_width']  = 250;
			$args['plugin_logo_height'] = 40;
			$args['permissions_url']    = 'https://go.solidwp.com/solid-backups-opt-in-usage-sharing';
			$args['tos_url']            = 'https://go.solidwp.com/solid-backups-terms-usage-modal';
			$args['privacy_url']        = 'https://go.solidwp.com/solid-backups-privacy-usage-modal';

		}

		return $args;
	}

	/**
	 * Display the Telemetry opt-in notice on the plugin's admin pages.
	 *
	 * Although this runs on current_screen, it only registers
	 * the hooks; it does not execute them.
	 *
	 * For testing, use the WP-CLI command
	 * wp option delete stellarwp_telemetry
	 *
	 * @since 9.1.6
	 *
	 * @action current_screen
	 */
	public static function render_opt_in() {
		$screen = get_current_screen();

		if ( empty( $screen ) || ( false === strpos( $screen->base, 'pb_backupbuddy' ) ) ) {
			return;
		}

		do_action( 'stellarwp/telemetry/' . self::SLUG . '/optin' );
	}

	/**
	 * Save the Opt In value from the Advanced Settings Page
	 *
	 * Note that this Solid Backups setting is saved elsewhere.
	 * This simply sets the value in the Telemetry library.
	 *
	 * @since 9.1.6
	 *
	 * @action admin_init
	 *
	 * @return void
	 */
	public static function save_opt_in_setting_field() {
		// Return early if not saving the Opt In Status field.
		if ( ! isset( $_POST[ 'pb_backupbuddy_telemetry_opt_in_status' ] ) ) {
			return;
		}

		// Get an instance of the Status class.
		$status = Config::get_container()->get( Status::class );

		$value = ! empty( intval( $_POST[ 'pb_backupbuddy_telemetry_opt_in_status' ] ) );
		$status->set_status( $value );
	}

	/**
	 * Get the Opt-In Status value.
	 *
	 * Although the Opt-In status value is saved in
	 * the Solid Backups settings, this method
	 * retrieves the value from the Telemetry library to
	 * ensure that the value is always up-to-date.
	 *
	 * Because the values used by Telemetry\Opt_In\Status
	 * are 1 = Active and 2 = Inactive, we simply get the boolean
	 * from is_active() and cast it to an integer to get our
	 * checkbox input value.
	 *
	 * @since 9.1.6
	 *
	 * @see StellarWP\Telemetry\Opt_In\Status
	 *
	 * @return int
	 */
	public static function get_opt_in_status_value() {
		$container = Telemetry::instance()->container();
		return (int) $container->get( Status::class )->is_active();
	}
}
