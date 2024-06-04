<?php
/**
 * StellarWP Telemetry functions.
 *
 * @link    https://github.com/stellarwp/telemetry
 *
 * @package Solid_Backups
 * @since   9.1.6
 */

use Solid_Backups\Strauss\StellarWP\Telemetry\Config;
use Solid_Backups\Strauss\StellarWP\Telemetry\Core as Telemetry;
use Solid_Backups\Strauss\StellarWP\Telemetry\Opt_In\Status;

class Solid_Backups_Telemetry {

	// The plugin slug.
	const SLUG = 'solid-backups';

	public static function run_hooks() {
		add_action( 'plugins_loaded', array( __CLASS__, 'initialize' ) );
		add_action( 'admin_footer', array( __CLASS__, 'render_opt_in' ), 99 );
		add_filter( 'stellarwp/telemetry/optin_args', array( __CLASS__, 'optin_args' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'save_opt_in_setting_field' ) );
		add_filter( 'debug_information', [ __CLASS__, 'add_site_health_info' ] );
	}

	/**
	 * Initialize the Telemetry library.
	 *
	 * @action plugins_loaded
	 *
	 * @since  9.1.6
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
	 * @since  9.1.6
	 *
	 * @filter stellarwp/telemetry/optin_args
	 *
	 * @link   https://github.com/stellarwp/telemetry/blob/develop/docs/filters.md#stellarwptelemetryoptin_args
	 *
	 * @param array  $args The default arguments.
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
	 * @since  9.1.6
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
	 * @since  9.1.6
	 *
	 * @action admin_init
	 *
	 * @return void
	 */
	public static function save_opt_in_setting_field() {
		// Return early if not saving the Opt In Status field.
		if ( ! isset( $_POST['pb_backupbuddy_telemetry_opt_in_status'] ) ) {
			return;
		}

		// Get an instance of the Status class.
		$status = Config::get_container()->get( Status::class );

		$value = ! empty( intval( $_POST['pb_backupbuddy_telemetry_opt_in_status'] ) );
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
	 * @see   StellarWP\Telemetry\Opt_In\Status
	 *
	 * @return int
	 */
	public static function get_opt_in_status_value() {
		$container = Telemetry::instance()->container();

		return (int) $container->get( Status::class )->is_active();
	}

	/**
	 * Track the currently activated destinations
	 *
	 * @return array
	 */
	public static function add_site_health_info( $info ): array {

		$site_size       = backupbuddy_core::get_site_size();
		$database_size   = backupbuddy_core::get_database_size();
		$database_method = pb_backupbuddy::$options['database_method_strategy'];

		// Get the schedules and format for site info ( key = title, value = interval | backup type )
		$enabled_schedule_array_simplified  = [];
		$disabled_schedule_array_simplified = [];
		foreach ( pb_backupbuddy::$options['schedules'] as $schedule ) {
			if ( $schedule['on_off'] ) {
				$enabled_schedule_array_simplified[ $schedule['title'] ] = $schedule['interval'] . ' | ' . pb_backupbuddy::$options['profiles'][ $schedule['profile'] ]['title'];
			} else {
				$disabled_schedule_array_simplified[ $schedule['title'] ] = $schedule['interval'] . ' | ' . pb_backupbuddy::$options['profiles'][ $schedule['profile'] ]['title'];
			}
		}

		// Get the destinations and format for site info ( key = type, value = title )
		$destinations_simplified = [];
		$stash_active            = false;
		foreach ( pb_backupbuddy::$options['remote_destinations'] as $destination ) {
			if ( $destination['type'] === 'live' ) {
				$stash_active = true;
			} else {
				$destinations_simplified[ $destination['type'] ] = $destination['title'];
			}
		}

		$info['solid-backups'] = [
			'label'  => __( 'Solid Backups', 'it-l10n-backupbuddy' ),
			'fields' => [
				'active_destinations'            => [
					'label' => __( 'Active Destinations', 'it-l10n-backupbuddy' ),
					'value' => $destinations_simplified,
					'debug' => $destinations_simplified,
				],
				'active_schedules'               => [
					'label' => __( 'Active Schedules', 'it-l10n-backupbuddy' ),
					'value' => $enabled_schedule_array_simplified,
					'debug' => $enabled_schedule_array_simplified,
				],
				'inactive_schedules'             => [
					'label' => __( 'Inactive Schedules', 'it-l10n-backupbuddy' ),
					'value' => $disabled_schedule_array_simplified,
					'debug' => $disabled_schedule_array_simplified,
				],
				'stash_live_active'              => [
					'label' => __( 'Stash Live Active', 'it-l10n-backupbuddy' ),
					'value' => $stash_active ? __( 'Yes', 'it-l10n-backupbuddy' ) : __( 'No', 'it-l10n-backupbuddy' ),
					'debug' => $stash_active,
				],
				'network_activated'              => [
					'label' => __( 'Network Activated', 'it-l10n-backupbuddy' ),
					'value' => backupbuddy_core::is_network_activated() ? __( 'Yes', 'it-l10n-backupbuddy' ) : __( 'No', 'it-l10n-backupbuddy' ),
					'debug' => backupbuddy_core::is_network_activated(),
				],
				'backup_directory_override'      => [
					'label' => __( 'Backup Directory Override', 'it-l10n-backupbuddy' ),
					'value' => '' != pb_backupbuddy::$options['backup_directory'] ? __( 'Yes', 'it-l10n-backupbuddy' ) : __( 'No', 'it-l10n-backupbuddy' ),
					'debug' => '' != pb_backupbuddy::$options['backup_directory'],
				],
				'site_size_total'                => [
					'label' => __( 'Total Site Size', 'it-l10n-backupbuddy' ),
					'value' => self::convertSizeToHumanReadable( $site_size[0] ),
					'debug' => $site_size[0],
				],
				'site_size_exclusions'           => [
					'label' => __( 'Site Size minus exclusions', 'it-l10n-backupbuddy' ),
					'value' => self::convertSizeToHumanReadable( $site_size[1] ),
					'debug' => $site_size[1],
				],
				'number_of_objects'              => [
					'label' => __( 'Total number of objects, files and folders', 'it-l10n-backupbuddy' ),
					'value' => $site_size[2],
					'debug' => $site_size[2],
				],
				'number_of_objects_exclusions'   => [
					'label' => __( 'Total number of objects, files and folders, minus exclusions', 'it-l10n-backupbuddy' ),
					'value' => $site_size[3],
					'debug' => $site_size[3],
				],
				'database_size_total'            => [
					'label' => __( 'Database size', 'it-l10n-backupbuddy' ),
					'value' => self::convertSizeToHumanReadable( $database_size[0] ),
					'debug' => $database_size[0],
				],
				'database_size_total_exclusions' => [
					'label' => __( 'Database size minus exclusions', 'it-l10n-backupbuddy' ),
					'value' => self::convertSizeToHumanReadable( $database_size[1] ),
					'debug' => $database_size[1],
				],
				'database_method'                => [
					'label' => __( 'Database Method Strategy', 'it-l10n-backupbuddy' ),
					'value' => $database_method,
					'debug' => $database_method,
				],
				'force_compatibility'            => [
					'label' => __( 'Force Compatibility Mode', 'it-l10n-backupbuddy' ),
					'value' => pb_backupbuddy::$options['force_compatibility'] ? __( 'Yes', 'it-l10n-backupbuddy' ) : __( 'No', 'it-l10n-backupbuddy' ),
					'debug' => pb_backupbuddy::$options['force_compatibility'],
				],
				'force_mysqldump_compatibility'  => [
					'label' => __( 'Force Compatibility Mode for MySQL', 'it-l10n-backupbuddy' ),
					'value' => pb_backupbuddy::$options['force_mysqldump_compatibility'] ? __( 'Yes', 'it-l10n-backupbuddy' ) : __( 'No', 'it-l10n-backupbuddy' ),
					'debug' => pb_backupbuddy::$options['force_mysqldump_compatibility'],
				],
				'deployment_allowed'             => [
					'label' => __( 'Deployments Allowed', 'it-l10n-backupbuddy' ),
					'value' => pb_backupbuddy::$options['deployment_allowed'] ? __( 'Yes', 'it-l10n-backupbuddy' ) : __( 'No', 'it-l10n-backupbuddy' ),
					'debug' => pb_backupbuddy::$options['deployment_allowed'],
				],
			]
		];

		return $info;
	}

	public static function convertSizeToHumanReadable( $bytes, $decimals = 2 ): string {
		$size   = array(
			'B',
			'KB',
			'MB',
			'GB',
			'TB',
			'PB',
			'EB',
			'ZB',
			'YB',
		);
		$factor = floor( ( strlen( $bytes ) - 1 ) / 3 );

		return sprintf( "%.{$decimals}f", $bytes / pow( 1024, $factor ) ) . ' ' . @$size[ $factor ];
	}

	/**
	 * @param $event_type
	 * @param $event_data
	 *
	 * @return void
	 */
	public static function trackEvent( $event_type, $event_data ) {
		do_action( 'stellarwp/telemetry/' . self::SLUG . '/event', $event_type, $event_data );
	}
}
