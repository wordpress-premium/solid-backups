<?php

/**
 * Manage licensing of iThemes plugins and themes.
 */
final class Ithemes_Updater_WP_CLI_Ithemes_Licensing extends WP_CLI_Command {
	/**
	 * Show iThemes plugins and themes on the site and the licensing status for each.
	 *
	 * ## OPTIONS
	 *
	 * [<product>]
	 * : Only show the status for the supplied product.
	 *
	 * [--verbose]
	 * : Show increased detail about each product.
	 *
	 * [--status=<status>]
	 * : Limit the list to products with a specific licensing status.
	 * ---
	 * default: all
	 * options:
	 *  - all
	 *  - active
	 *  - inactive
	 *
	 * [--format=<format>]
	 * : Output formatting
	 * ---
	 * default: table
	 * options:
	 *  - table
	 *  - json
	 *  - csv
	 *  - yaml
	 *  - count
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function show( $args, $assoc_args ) {
		$this->verify_updater_is_present();

		require_once( $GLOBALS['ithemes_updater_path'] . '/api.php' );

		if ( ! empty( $args ) ) {
			list( $product ) = $args;
		} else {
			$product = null;
		}

		$default_args = array(
			'verbose' => false,
			'status'  => 'all',
			'format'  => 'table',
		);

		$assoc_args = wp_parse_args( $assoc_args, $default_args );
		$package_details = Ithemes_Updater_API::get_package_details();

		if ( is_wp_error( $package_details ) ) {
			WP_CLI::error( sprintf( 'Unable to retrieve product details: %1$s (%2$s)', $package_details->get_error_message(), $package_details->get_error_code() ) );
			return;
		}

		ksort( $package_details['packages'] );
		$packages = array();

		foreach ( $package_details['packages'] as $name => $details ) {
			if ( ! is_null( $product ) && $name !== $product ) {
				continue;
			}

			if ( ! isset( $details['status'] ) ) {
				$details['status'] = 'inactive';
			}

			if ( 'all' !== $assoc_args['status'] && $details['status'] !== $assoc_args['status'] ) {
				continue;
			}

			$package = array(
				'name'   => $name,
				'status' => isset( $details['status'] ) ? $details['status'] : 'inactive',
			);

			if ( $assoc_args['verbose'] ) {
				$package = array_merge( $package, $details );
			}

			$packages[] = $package;
		}

		if ( ! empty( $packages ) ) {
			if ( $assoc_args['verbose'] ) {
				$columns = array(
					'name',
					'status',
					'used',
					'total',
					'ver',
					'user',
					'sub_expire',
					'release_timestamp',
					'upgrade',
					'link_expire',
					'link',
					'key',
					'error',
				);
			} else {
				$columns = array(
					'name',
					'status',
				);
			}

			foreach ( $packages as &$package ) {
				foreach ( $columns as $column ) {
					if ( ! isset( $package[$column] ) ) {
						$package[$column] = '';
					}
				}
			}

			WP_CLI\Utils\format_items( $assoc_args['format'], $packages, $columns );
		} else if ( $assoc_args['verbose'] ) {
			WP_CLI::error( 'No iThemes products were found matching the current criteria.' );
		}
	}

	/**
	 * Activate licensing for one or more products
	 *
	 * ## OPTIONS
	 *
	 * Specificy the iThemes account username and password using the environment variables ITHEMES_USER and ITHEMES_PASS
	 *
	 * [<product>...]
	 * : Product to activate licensing for.
	 *
	 * [--ithemes-user=<user>]
	 * : iThemes member username. This value can also be supplied by an ITHEMES_USER environment variable.
	 *
	 * [--ithemes-pass=<pass>]
	 * : iThemes member password. This value can also be supplied by an ITHEMES_PASS environment variable.
	 *
	 * [--all]
	 * : Activate licensing for all currently installed, inactive products.
	 *
	 * ## EXAMPLES
	 *
	 *     # Activate licensing for the ithemes-security-pro plugin.
	 *     $ wp ithemes-licensing activate ithemes-security-pro --ithemes-user=example --ithemes-pass=example
	 *
	 *     # Activate licensing for all installed iThemes products using environment variables for member details.
	 *     $ ITHEMES_USER=example ITHEMES_PASS=example wp ithemes-licensing activate --all
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function activate( $args, $assoc_args ) {
		$this->handle_request( 'activate', $args, $assoc_args );
	}

	/**
	 * Deactivate licensing for one or more products
	 *
	 * ## OPTIONS
	 *
	 * Specificy the iThemes account username and password using the environment variables ITHEMES_USER and ITHEMES_PASS
	 *
	 * [<product>...]
	 * : Product to deactivate licensing for.
	 *
	 * [--ithemes-user=<user>]
	 * : iThemes member username. This value can also be supplied by an ITHEMES_USER environment variable.
	 *
	 * [--ithemes-pass=<pass>]
	 * : iThemes member password. This value can also be supplied by an ITHEMES_PASS environment variable.
	 *
	 * [--all]
	 * : Deactivate licensing for all currently installed, active products.
	 *
	 * ## EXAMPLES
	 *
	 *     # Deactivate licensing for the ithemes-security-pro plugin.
	 *     $ wp ithemes-licensing deactivate ithemes-security-pro --ithemes-user=example --ithemes-pass=example
	 *
	 *     # Deactivate licensing for all installed iThemes products using environment variables for member details.
	 *     $ ITHEMES_USER=example ITHEMES_PASS=example wp ithemes-licensing deactivate --all
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function deactivate( $args, $assoc_args ) {
		$this->handle_request( 'deactivate', $args, $assoc_args );
	}

	private function handle_request( $verb, $args, $assoc_args ) {
		$this->verify_updater_is_present();

		require_once( $GLOBALS['ithemes_updater_path'] . '/api.php' );
		require_once( $GLOBALS['ithemes_updater_path'] . '/functions.php' );

		$products = $args;

		$default_args = array(
			'all' => false,
		);

		$assoc_args = wp_parse_args( $assoc_args, $default_args );
		$user = getenv( 'ITHEMES_USER' );
		$pass = getenv( 'ITHEMES_PASS' );

		if ( isset( $assoc_args['ithemes-user'] ) ) {
			$user = $assoc_args['ithemes-user'];
		}
		if ( isset( $assoc_args['ithemes-pass'] ) ) {
			$pass = $assoc_args['ithemes-pass'];
		}

		if ( empty( $user ) ) {
			WP_CLI::error( 'You must supply the iThemes member username.' );
			return;
		}
		if ( empty( $pass ) ) {
			WP_CLI::error( 'You must supply the iThemes member password.' );
			return;
		}

		if ( empty( $products ) && ! $assoc_args['all'] ) {
			WP_CLI::error( "You must supply one or more products or use the --all flag." );
			return;
		} else if ( ! empty( $products ) && $assoc_args['all'] ) {
			WP_CLI::error( 'You must supply one or more products or use the --all flag, but not both.' );
			return;
		}


		$package_details = Ithemes_Updater_API::get_package_details();

		if ( $assoc_args['all'] ) {
			if ( is_wp_error( $package_details ) ) {
				WP_CLI::error( sprintf( 'Unable to retrieve product details: %1$s (%2$s)', $package_details->get_error_message(), $package_details->get_error_code() ) );
				return;
			}

			$products = array();

			foreach ( $package_details['packages'] as $name => $details ) {
				if ( 'activate' === $verb xor ( isset( $details['status'] ) && 'active' === $details['status'] ) ) {
					$products[] = $name;
				}
			}

			if ( empty( $products ) ) {
				if ( 'activate' === $verb ) {
					WP_CLI::success( 'No products with inactive licensing were found.' );
				} else {
					WP_CLI::success( 'No products with active licensing were found.' );
				}

				return;
			}
		} else {
			foreach ( $products as $index => $product ) {
				if ( ! isset( $package_details['packages'][$product] ) ) {
					WP_CLI::error( "$product is not a valid iThemes product. Licensing for it cannot be {$verb}d." );
					unset( $products[$index] );
				}
			}

			if ( empty( $products ) ) {
				return;
			}
		}

		if ( 'activate' === $verb ) {
			$response = Ithemes_Updater_API::activate_package( $user, $pass, $products );
		} else {
			$response = Ithemes_Updater_API::deactivate_package( $user, $pass, $products );
		}

		if ( is_wp_error( $response ) ) {
			WP_CLI::error( Ithemes_Updater_API::get_error_explanation( $response ) );
			return;
		}

		if ( empty( $response['packages'] ) ) {
			if ( 1 === count( $products ) ) {
				WP_CLI::error( "An unknown server error occurred. Please try to $verb your product license again at another time." );
			} else {
				WP_CLI::error( "An unknown server error occurred. Please try to $verb your product licenses again at another time." );
			}

			return;
		}


		uksort( $response['packages'], 'strnatcasecmp' );

		foreach ( $response['packages'] as $package => $data ) {
			if ( preg_match( '/ \|\|\| \d+$/', $package ) ) {
				continue;
			}

			if ( 'activate' === $verb ) {
				if ( ! empty( $data['key'] ) ) {
					WP_CLI::success( "Activated the product license for $package." );
				} else if ( ! empty( $data['status'] ) && ( 'expired' === $data['status'] ) ) {
					WP_CLI::warning( "Unable to activate the product license for $package. Your product subscription has expired." );
				} else {
					WP_CLI::error( "Unable to activate the product license for $package. {$data['error']['message']}" );
				}
			} else {
				if ( isset( $data['status'] ) && ( 'inactive' == $data['status'] ) ) {
					WP_CLI::success( "Deactivated the product license for $package." );
				} else if ( isset( $data['error'] ) && isset( $data['error']['message'] ) ) {
					WP_CLI::error( "Unable to deactivate the product license for $package. {$data['error']['message']}" );
				} else {
					WP_CLI::error( "Unable to deactivate the product license for $package. Unknown server error." );
				}
			}
		}
	}

	private function verify_updater_is_present() {
		if ( empty( $GLOBALS['ithemes_updater_path'] ) ) {
			if ( defined( 'ITHEMES_UPDATER_DISABLE' ) && ITHEMES_UPDATER_DISABLE ) {
				WP_CLI::error( 'The iThemes updater library is disabled on this site due to the ITHEMES_UPDATER_DISABLE define being set to a truthy value. Licensing for this site cannot be managed.' );
			} else {
				WP_CLI::error( 'The $GLOBALS[\'ithemes_updater_path\'] variable is empty or not set. This indicates that the updater was not loaded although the cause for this is not known.' );
			}

			exit;
		}
	}
}
