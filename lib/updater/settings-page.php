<?php

/*
Code to render and manage the settings page for the updater system.
Written by Chris Jean for iThemes.com
Version 1.1.0

Version History
	1.0.0 - 2013-04-11 - Chris Jean
		Release ready
	1.0.1 - 2013-09-19 - Chris Jean
		Updated requires to not use dirname().
		Updated ithemes-updater-object to ithemes-updater-settings.
	1.1.0 - 2013-10-23 - Chris Jean
		Enhancement: Added the quick_releases setting.
		Misc: Removed the show_on_sites setting as it is no longer used.
*/


class Ithemes_Updater_Settings_Page {
	private $page_name = 'ithemes-licensing';

	private $path_url = '';
	private $self_url = '';
	private $messages = array();
	private $message_strings = array();
	private $errors = array();
	private $soft_errors = array();


	public function __construct() {
		require_once( $GLOBALS['ithemes_updater_path'] . '/functions.php' );
		require_once( $GLOBALS['ithemes_updater_path'] . '/api.php' );
		require_once( $GLOBALS['ithemes_updater_path'] . '/keys.php' );


		$this->path_url = Ithemes_Updater_Functions::get_url( $GLOBALS['ithemes_updater_path'] );

		list( $this->self_url ) = explode( '?', $_SERVER['REQUEST_URI'] );
		$this->self_url .= '?page=' . $this->page_name;


		add_action( 'ithemes_updater_settings_page_index', array( $this, 'index' ) );
		add_action( 'admin_print_scripts', array( $this, 'add_scripts' ) );
		add_action( 'admin_print_styles', array( $this, 'add_styles' ) );
	}

	public function add_scripts() {
		wp_enqueue_script( 'ithemes-updater-settings-page-script', "{$this->path_url}/js/settings-page.js", array(), 3 );
	}

	public function add_styles() {
		wp_enqueue_style( 'ithemes-updater-settings-page-style', "{$this->path_url}/css/settings-page.css", array(), 4 );
	}

	public function index() {
		$post_data = Ithemes_Updater_Functions::get_post_data( array( 'it-updater-username', 'it-updater-password', 'packages', 'patchstack_sites', 'action', 'site_url', 'redirect', 'relicense_option' ), true );

		if ( empty( $post_data['packages'] ) ) {
			$post_data['packages'] = array();
		}

		if ( empty( $post_data['patchstack_sites'] ) ) {
			$post_data['patchstack_sites'] = array();
		}

		if ( ! empty( $post_data['action'] ) ) {
			$action = $post_data['action'];
		} else if ( ! empty( $_REQUEST['action'] ) ) {
			$action = $_REQUEST['action'];
		} else {
			$action = 'list_packages';
		}

		if ( 'save_licensed_site_url' === $action ) {
			$this->save_licensed_site_url( $post_data );
		} else if ( 'relicense' === $action ) {
			$this->relicense( $post_data );
		} else if ( 'change_licensed_site_url' === $action || ! $GLOBALS['ithemes-updater-settings']->is_licensed_site_url_confirmed() ) {
			$this->show_licensed_site_url_confirmation_page( $post_data );
		} else {
			if ( 'license_packages' === $action ) {
				$this->license_packages( $post_data );
			} else if ( 'unlicense_packages' === $action ) {
				$this->unlicense_packages( $post_data );
			} else if ( 'save_settings' === $action ) {
				$this->save_settings();
			} else if ( 'license_patchstack_site' === $action ) {
				$this->license_patchstack_site( $post_data );
			} else if ( 'unlicense_patchstack_sites' === $action ) {
				$this->unlicense_patchstack_sites( $post_data );
			}

			$this->list_packages( $action, $post_data );
		}
	}

	private function save_settings() {
		check_admin_referer( 'save_settings', 'ithemes_updater_nonce' );


		$settings_defaults = array(
			'quick_releases' => false,
		);

		$settings = array();

		foreach ( $settings_defaults as $var => $val ) {
			if ( isset( $_POST[$var] ) )
				$settings[$var] = $_POST[$var];
			else
				$settings[$var] = $val;
		}

		if ( $settings['quick_releases'] )
			$settings['quick_releases'] = true;

		$GLOBALS['ithemes-updater-settings']->update_options( $settings );

		$GLOBALS['ithemes-updater-settings']->flush( 'settings saved' );


		$this->messages[] = __( 'Settings saved', 'it-l10n-backupbuddy' );
	}

	private function license_packages( $data ) {
		check_admin_referer( 'license_packages', 'ithemes_updater_nonce' );

		if ( empty( $data['username'] ) && empty( $data['password'] ) )
			$this->errors[] = __( 'You must supply a SolidWP membership username and password in order to license products.', 'it-l10n-backupbuddy' );
		else if ( empty( $data['username'] ) )
			$this->errors[] = __( 'You must supply a SolidWP membership username in order to license products.', 'it-l10n-backupbuddy' );
		else if ( empty( $data['password'] ) )
			$this->errors[] = __( 'You must supply a SolidWP membership password in order to license products.', 'it-l10n-backupbuddy' );
		else if ( empty( $data['packages'] ) )
			$this->errors[] = __( 'You must select at least one product to license. Ensure that you select the products that you wish to license in the listing below.', 'it-l10n-backupbuddy' );

		if ( ! empty( $this->errors ) )
			return;


		$response = Ithemes_Updater_API::activate_package( $data['username'], $data['password'], $data['packages'] );

		if ( is_wp_error( $response ) ) {
			$this->errors[] = Ithemes_Updater_API::get_error_explanation( $response );

			return;
		}

		if ( empty( $response['packages'] ) ) {
			$this->errors[] = __( 'An unknown server error occurred. Please try to license your products again at another time.', 'it-l10n-backupbuddy' );
			return;
		}


		uksort( $response['packages'], 'strnatcasecmp' );

		$success = array();
		$warn = array();
		$fail = array();

		foreach ( $response['packages'] as $package => $data ) {
			if ( preg_match( '/ \|\|\| \d+$/', $package ) ) {
				continue;
			}

			$name = esc_html( Ithemes_Updater_Functions::get_package_name( $package ) );

			if ( ! empty( $data['key'] ) ) {
				$success[] = $name;
			} else if ( ! empty( $data['status'] ) && ( 'expired' == $data['status'] ) ) {
				$warn[ $name ] = __( 'Your product subscription has expired', 'it-l10n-backupbuddy' );
			} else {
				$fail[ $name ] = esc_html( $data['error']['message'] );
			}
		}


		if ( ! empty( $success ) ) {
			$this->messages[] = wp_sprintf( __( 'Successfully licensed %l.', 'it-l10n-backupbuddy' ), $success );
		}

		if ( ! empty( $fail ) ) {
			foreach ( $fail as $name => $reason ) {
				$this->errors[] = sprintf( __( 'Unable to license %1$s. Reason: %2$s', 'it-l10n-backupbuddy' ), $name, $reason );
			}
		}

		if ( ! empty( $warn ) ) {
			foreach ( $warn as $name => $reason ) {
				$this->soft_errors[] = sprintf( __( 'Unable to license %1$s. Reason: %2$s', 'it-l10n-backupbuddy' ), $name, $reason );
			}
		}
	}

	private function license_patchstack_site( $data ) {
		check_admin_referer( 'license_patchstack_site', 'ithemes_updater_nonce' );

		if ( empty( $data['username'] ) && empty( $data['password'] ) )
			$this->errors[] = __( 'You must supply a SolidWP membership username and password in order to remove patchstack licenses.', 'it-l10n-backupbuddy' );
		else if ( empty( $data['username'] ) )
			$this->errors[] = __( 'You must supply a SolidWP membership username in order to remove patchstack licenses.', 'it-l10n-backupbuddy' );
		else if ( empty( $data['password'] ) )
			$this->errors[] = __( 'You must supply a SolidWP membership password in order to remove patchstack licenses.', 'it-l10n-backupbuddy' );

		$keys = Ithemes_Updater_Keys::get();
		if ( empty( $keys['ithemes-security-pro'] ) )
			$this->errors[] = __( 'Unable to locate API key for Solid Security Pro.', 'it-l10n-backupbuddy' );

		if ( ! empty( $this->errors ) )
			return;

		$response = Ithemes_Updater_API::consume_patchstack_quota( $data['username'], $data['password'], $keys['ithemes-security-pro'] );

		if ( is_wp_error( $response ) ) {
			$this->errors[] = Ithemes_Updater_API::get_error_explanation( $response );

			return;
		}

		$success = array();
		$fail = array();

		$site = parse_url( $GLOBALS['ithemes-updater-settings']->get_licensed_site_url(), PHP_URL_HOST );

		if ( in_array( $site, $response['response']['sites'] ) ) {
			$success[] = $site;
		} else {
			$fail[] = $site;
		}

		if ( ! empty( $success ) )
			$this->messages[] = wp_sprintf( _n( 'Successfully added Patchstack license to %l.', 'Successfully removed Patchstack licenses from %l sites.', count( $success ), 'it-l10n-backupbuddy' ), $success );

		if ( ! empty( $fail ) ) {
			foreach ( $fail as $name => $reason )
				$this->errors[] = sprintf( __( 'Unable to remove patchstack license from %1$s. Reason: %2$s', 'it-l10n-backupbuddy' ), $name, $reason );
		}
	}

	private function unlicense_patchstack_sites( $data ) {
		check_admin_referer( 'unlicense_patchstack_sites', 'ithemes_updater_nonce' );

		if ( empty( $data['username'] ) && empty( $data['password'] ) )
			$this->errors[] = __( 'You must supply a SolidWP membership username and password in order to remove patchstack licenses.', 'it-l10n-backupbuddy' );
		else if ( empty( $data['username'] ) )
			$this->errors[] = __( 'You must supply a SolidWP membership username in order to remove patchstack licenses.', 'it-l10n-backupbuddy' );
		else if ( empty( $data['password'] ) )
			$this->errors[] = __( 'You must supply a SolidWP membership password in order to remove patchstack licenses.', 'it-l10n-backupbuddy' );
		else if ( empty( $data['patchstack_sites'] ) )
			$this->errors[] = __( 'You must select at least one license to remove. Ensure that you select the licenses that you wish to remove in the listing below.', 'it-l10n-backupbuddy' );

		if ( ! empty( $this->errors ) )
			return;

		$response = Ithemes_Updater_API::remove_patchstack_quota( $data['username'], $data['password'], $data['patchstack_sites'] );

		if ( is_wp_error( $response ) ) {
			$this->errors[] = Ithemes_Updater_API::get_error_explanation( $response );

			return;
		}

		$success = array();
		$fail = array();

		foreach ( $data['patchstack_sites'] as $site ) {
			if ( !in_array( $site, $response['response']['sites'] ) ) {
				$success[] = $site;
			} else {
				$fail[] = $site;
			}
		}

		if ( ! empty( $success ) )
			$this->messages[] = wp_sprintf( _n( 'Successfully removed Patchstack license from %l site.', 'Successfully removed Patchstack licenses from %l sites.', count( $success ), 'it-l10n-backupbuddy' ), $success );

		if ( ! empty( $fail ) ) {
			foreach ( $fail as $name => $reason )
				$this->errors[] = sprintf( __( 'Unable to remove license from %1$s. Reason: %2$s', 'it-l10n-backupbuddy' ), $name, $reason );
		}

	}

	private function unlicense_packages( $data ) {
		check_admin_referer( 'unlicense_packages', 'ithemes_updater_nonce' );

		if ( empty( $data['username'] ) && empty( $data['password'] ) )
			$this->errors[] = __( 'You must supply a SolidWP membership username and password in order to remove licenses.', 'it-l10n-backupbuddy' );
		else if ( empty( $data['username'] ) )
			$this->errors[] = __( 'You must supply a SolidWP membership username in order to remove licenses.', 'it-l10n-backupbuddy' );
		else if ( empty( $data['password'] ) )
			$this->errors[] = __( 'You must supply a SolidWP membership password in order to remove licenses.', 'it-l10n-backupbuddy' );
		else if ( empty( $data['packages'] ) )
			$this->errors[] = __( 'You must select at least one license to remove. Ensure that you select the licenses that you wish to remove in the listing below.', 'it-l10n-backupbuddy' );

		if ( ! empty( $this->errors ) )
			return;

		$response = Ithemes_Updater_API::deactivate_package( $data['username'], $data['password'], $data['packages'] );

		if ( is_wp_error( $response ) ) {
			$this->errors[] = Ithemes_Updater_API::get_error_explanation( $response );

			return;
		}

		if ( empty( $response['packages'] ) ) {
			$this->errors[] = __( 'An unknown server error occurred. Please try to remove licenses from your products again at another time.', 'it-l10n-backupbuddy' );
			return;
		}


		uksort( $response['packages'], 'strnatcasecmp' );

		$success = array();
		$fail = array();

		foreach ( $response['packages'] as $package => $data ) {
			if ( preg_match( '/ \|\|\| \d+$/', $package ) )
				continue;

			$name = esc_html( Ithemes_Updater_Functions::get_package_name( $package ) );

			if ( isset( $data['status'] ) && ( 'inactive' == $data['status'] ) )
				$success[] = $name;
			else if ( isset( $data['error'] ) && isset( $data['error']['message'] ) )
				$fail[$name] = esc_html( $data['error']['message'] );
			else
				$fail[$name] = __( 'Unknown server error.', 'it-l10n-backupbuddy' );
		}


		if ( ! empty( $success ) )
			$this->messages[] = wp_sprintf( _n( 'Successfully removed license from %l.', 'Successfully removed licenses from %l.', count( $success ), 'it-l10n-backupbuddy' ), $success );

		if ( ! empty( $fail ) ) {
			foreach ( $fail as $name => $reason )
				$this->errors[] = sprintf( __( 'Unable to remove license from %1$s. Reason: %2$s', 'it-l10n-backupbuddy' ), $name, $reason );
		}
	}

	public function list_packages( $action = 'list_packages', $post_data = array() ) {
		require_once( $GLOBALS['ithemes_updater_path'] . '/packages.php' );
		$packages = Ithemes_Updater_Packages::get_packages_to_include_in_ui( 'full' );

		$licensed = array();
		$unlicensed = array();
		$unrecognized = array();
		$patchstack_quota = array();
		$has_patchstack = false;

		foreach ( $packages as $path => $data ) {
			$name = Ithemes_Updater_Functions::get_package_name( $data['package'] );
			$data['path'] = $path;

			if ( isset( $data['status'] ) && 'unlicensed' == $data['status'] ) {
				$unlicensed[$name] = $data;
			} else if ( isset( $data['status'] ) && in_array( $data['status'], array( 'active', 'expired' ) ) ) {
				$licensed[$name] = $data;
			} else {
				$unrecognized[$name] = $data;
			}
		}

		$security_package = wp_list_filter( $packages, array( 'package' => 'ithemes-security-pro' ) );
		$security_package = reset( $security_package );

		if ( $security_package && ! empty( $security_package['key'] ) ) {
			$patchstack_quota = Ithemes_Updater_API::get_patchstack_quota( $security_package['key'] );

			if ( ! is_wp_error( $patchstack_quota ) ) {
				$has_patchstack = ! empty( $patchstack_quota['total'] );
			}
		}

		if ( ! empty( $_REQUEST['updated_url'] ) ) {
			$this->messages[] = __( 'Successfully updated the Licensed URL.', 'it-l10n-backupbuddy' );
		}

		$this->show_notices();

?>
	<div class="solidwp-licensing-page-header">
		<img src="<?php echo esc_attr( $this->path_url . '/images/solid_wp_logo.svg' ); ?>" />
	</div>
	<div class="solidwp-licensing">
		<div class="solidwp-licensing-wrap">
			<div class="solidwp-licensing-wrap-header">
				<h2><?php _e( 'SolidWP Licensing', 'it-l10n-backupbuddy' ); ?></h2>
			</div>

		<?php
			$this->show_sunset_banner( $packages );
			$this->list_licensed_products( $licensed, $post_data, $action );
			if ( $has_patchstack ) {
				if ( ( $security_package['total'] > 0 || $security_package['total'] == -1 ) && $patchstack_quota['total'] > 0 ) {
					$this->list_patchstack_quota( $patchstack_quota, $post_data, $action );
				}
			}
			$this->list_unlicensed_products( $unlicensed, $post_data, $action );
			$this->list_unrecognized_products( $unrecognized );
			$this->show_settings();
		?>
	</div>
<?php

	}

	private function show_settings() {
		$quick_releases = $GLOBALS['ithemes-updater-settings']->get_option( 'quick_releases' );

?>
	<form id="posts-filter" enctype="multipart/form-data" method="post" action="<?php echo esc_attr( $this->self_url ); ?>">
		<?php wp_nonce_field( 'save_settings', 'ithemes_updater_nonce' ); ?>

		<div id="ithemes-updater-settings">

			<div class="solidwp-table-header">
				<div>
					<h3 class="subtitle"><?php _e( 'Settings', 'it-l10n-backupbuddy' ); ?></h3>
				</div>
			</div>

			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row">
							<?php _e( 'Licensed URL', 'it-l10n-backupbuddy' ); ?>
						</th>
						<td>
							<p>
								<code><?php echo esc_html( $GLOBALS['ithemes-updater-settings']->get_licensed_site_url() ); ?></code>
								<a href="<?php echo admin_url( 'options-general.php?page=ithemes-licensing&action=change_licensed_site_url' ); ?>" class="button button-primary"><?php _e( 'Change', 'it-l10n-backupbuddy' ); ?></a>
							</p>

							<?php if ( is_multisite() ) : ?>
								<p class="description"><?php _e( 'The Licensed URL should be the primary URL of this WordPress network. If this is not set correctly, some features may not function as expected.', 'it-l10n-backupbuddy' ); ?></p>
							<?php else : ?>
								<p class="description"><?php _e( 'The Licensed URL should be the primary URL of this WordPress site. If this is not set correctly, some features may not function as expected.', 'it-l10n-backupbuddy' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="quick_releases"><?php _e( 'Quick Release Updates', 'it-l10n-backupbuddy' ); ?></label>
						</th>
						<td>
							<?php $checked = ( $quick_releases ) ? ' checked="checked"' : ''; ?>

							<label>
								<input id="quick_releases" type="checkbox" name="quick_releases" value="1" <?php echo $checked; ?>/>
								<?php _e( 'Enable quick release updates', 'it-l10n-backupbuddy' ); ?>
							</label>

							<p class="description"><?php _e( 'Some products have quick releases that are created to solve specific issues that some users experience. In order to avoid causing users to have updates show up too frequently, automatic updates to these quick releases are disabled by default. Enabling this feature allows quick releases to be available to the automatic update system. Using this option is only recommended if support has requested that you enable it in order to receive a quick release. You should disable this option at a later time after confirming that the quick release solves the issue for you.', 'it-l10n-backupbuddy' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<input id="save_settings" class="button button-primary" type="submit" value="<?php _e( 'Save Settings', 'it-l10n-backupbuddy' ); ?>" />
				<input type="hidden" name="action" value="save_settings" />
			</p>
		</div>
	</form>
<?php

	}

	private function show_sunset_banner( $packages ) {
		$sunset_slugs = array_flip( include __DIR__ . '/sunset-packages.php' );

		$sunset = [];

		foreach ( $packages as $package ) {
			if ( isset( $sunset_slugs[ $package['package'] ] ) ) {
				$sunset[] = $package['package'];
			}
		}

		if ( ! $sunset ) {
			return;
		}

		if ( count( $sunset ) === 1 ) {
			$sunset_text = sprintf(
				__( '%s is being Sunset, but we have alternatives for you!', 'it-l10n-backupbuddy' ),
				Ithemes_Updater_Functions::get_package_name( $sunset[0] )
			);
		} else {
			$sunset_text = __( 'iThemes plugins are being Sunset, but we have alternatives for you!', 'it-l10n-backupbuddy' );
		}

		include __DIR__ . '/sunset-banner.php';
	}

	private function list_licensed_products( $products, $post_data, $action ) {
		if ( empty( $products ) )
			return;

		uksort( $products, 'strnatcasecmp' );

		$time = time();

		$headings = array(
			__( 'Product', 'it-l10n-backupbuddy' ),
			__( 'Member', 'it-l10n-backupbuddy' ),
			__( 'Expiration', 'it-l10n-backupbuddy' ),
			__( 'Remaining Licenses', 'it-l10n-backupbuddy' ),
		);

		if ( ( 'unlicense_packages' != $action ) || empty( $this->errors ) ) {
			$post_data = array(
				'username' => '',
				'password' => '',
				'packages' => array(),
			);
		}

?>
	<form id="posts-filter" enctype="multipart/form-data" method="post" action="<?php echo esc_attr( $this->self_url ); ?>" autocomplete="off">
		<?php wp_nonce_field( 'unlicense_packages', 'ithemes_updater_nonce' ); ?>

		<div class="ithemes-updater-products" id="ithemes-updater-licensed">
			<div class="solidwp-table-header">
				<div>
					<h3 class="subtitle"><?php _e( 'Licensed Products', 'it-l10n-backupbuddy' ); ?></h3>
				</div>
			</div>

			<table class="ithemes-updater-listing widefat">
				<thead>
					<tr>
						<th id="cb" class="manage-column column-cb check-column" scope="col">
							<label class="screen-reader-text" for="cb-select-all-1"><?php _e( 'Select All' ); ?></label>
							<label>
								<input id="cb-select-all-1" type="checkbox" />
							</label>
						</th>
						<th scope="col">
							<label for="cb-select-all-1"><?php _e( 'Product', 'it-l10n-backupbuddy' ); ?></label>
						</th>
						<th scope="col" class="mobile-hidden"><?php _e( 'Member', 'it-l10n-backupbuddy' ); ?></th>
						<th scope="col"><?php _e( 'Product Status', 'it-l10n-backupbuddy' ); ?></th>
						<th scope="col" class="mobile-hidden"><?php _e( 'Expiration', 'it-l10n-backupbuddy' ); ?></th>
						<th scope="col" class="mobile-hidden"><?php _e( 'Remaining Licenses', 'it-l10n-backupbuddy' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php $count = 0; ?>
					<?php foreach ( $products as $name => $data ) : ?>
						<?php
							if ( -1 == $data['total'] )
								$remaining = __( 'unlimited', 'it-l10n-backupbuddy' );
							else
								$remaining = $data['total'] - $data['used'];

//							if ( 0 == $remaining )
//								$remaining .= ' <a class="button-secondary upgrade">' . __( 'Upgrade', 'it-l10n-backupbuddy' ) . '</a>';


							$expiration = $this->get_expiration_string( $data['expiration'] );
							$expiration = '<time datetime="' . date( 'Y-m-d\TH:i:s\Z', $data['expiration'] ) . '">' . esc_html( $expiration ) . '</time>';


							$time_left = $data['expiration'] - $time;
							$class = 'expiring';

							if ( $time_left > ( 86400 * 30 ) )
								$class = 'active';
							else if ( $time_left <= 0 )
								$class = 'expired';

							if ( 'expired' == $data['status'] ) {
								$class = 'expired';
								$remaining = '&nbsp;';
							}


							$status = ucfirst( $class );


							if ( ++$count % 2 ) {
								$class .= ' alt';
							}


							$check_id = "cb-select-{$data['package']}";


							$checked = ( in_array( $data['package'], $post_data['packages'] ) ) ? ' checked' : '';
						?>
						<tr class="<?php echo esc_attr( $class ); ?>">
							<th class="check-column" scope="row">
								<label class="screen-reader-text" for="<?php echo esc_attr( $check_id ); ?>"><?php printf( __( 'Select %s' ), esc_html( $name ) ); ?></label>
								<label for="<?php echo esc_attr( $check_id ); ?>">
									<input id="<?php echo esc_attr( $check_id ) ?>" name="packages[]" value="<?php echo esc_attr( $data['package'] ); ?>" type="checkbox"<?php echo $checked; ?>>
								</label>
							</th>
							<td>
								<label for="<?php echo esc_attr( $check_id ); ?>"><?php echo esc_html( $name ); ?></label>
							</td>
							<td class="mobile-hidden"><?php echo esc_html( $data['user'] ); ?></td>
							<td><?php echo $status; ?></td>
							<td class="mobile-hidden"><?php echo $expiration; ?></td>
							<td class="mobile-hidden"><?php echo $remaining; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<td colspan="6">
							<input type="text" name="it-updater-username" placeholder="SolidWP Username" value="<?php echo esc_attr( $post_data['username'] ); ?>" autocomplete="off" />
							<input type="password" name="it-updater-password" placeholder="Password" value="<?php echo esc_attr( $post_data['password'] ); ?>" />
							<input class="button-primary" type="submit" name="submit" value="<?php _e( 'Remove Licenses', 'it-l10n-backupbuddy' ); ?>" />
							<input type="hidden" name="action" value="unlicense_packages" />
						</td>
					</tr>
				</tfoot>
			</table>
		</div>
	</form>
<?php

	}

	private function list_patchstack_quota( $patchstack, $post_data, $action ) {


		if ( ( 'unlicense_patchstack_sites' != $action ) || empty( $this->errors ) ) {
			$post_data = array(
				'username' => '',
				'password' => '',
				'patchstack_sites' => array(),
			);
		}

		$patchstack_available = $patchstack['total'] - $patchstack['used'];

?>
	<form id="posts-filter" enctype="multipart/form-data" method="post" action="<?php echo esc_attr( $this->self_url ); ?>" autocomplete="off">
		<?php wp_nonce_field( 'unlicense_patchstack_sites', 'ithemes_updater_nonce' ); ?>

		<div class="ithemes-updater-products" id="ithemes-updater-licensed">
			<div class="solidwp-table-header">
				<div>
					<h3 class="subtitle"><?php _e( 'Patchstack Enabled Sites', 'it-l10n-backupbuddy' ); ?></h3>
				</div>
			</div>


			<table class="ithemes-updater-listing widefat">
				<thead>
					<tr>
						<th id="cb" class="manage-column column-cb check-column" scope="col">
							<label class="screen-reader-text" for="cb-select-all-patchstack"><?php _e( 'Select All' ); ?></label>
							<label>
								<input id="cb-select-all-patchstack" type="checkbox" />
							</label>
						</th>
						<th scope="col">
							<label for="cb-select-all-patchstack"><?php printf( __( 'Site (%d Remaining Licenses)', 'it-l10n-backupbuddy' ), $patchstack_available ); ?></label>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php $count = 0; ?>
					<?php foreach ( $patchstack['sites'] as $site ) : ?>
						<?php

							$class = 'active';
							if ( ++$count % 2 ) {
								$class .= ' alt';
							}

							$check_id = "cb-select-{$site}";

							$checked = ( in_array( $site, $post_data['patchstack_sites'] ) ) ? ' checked' : '';
						?>
						<tr class="<?php echo $class; ?>">
							<th class="check-column" scope="row">
								<label class="screen-reader-text" for="<?php echo esc_attr( $check_id ); ?>"><?php printf( __( 'Select %s' ), esc_html( $site ) ); ?></label>
								<label for="<?php echo esc_attr( $check_id ); ?>">
									<input id="<?php echo esc_attr( $check_id ) ?>" name="patchstack_sites[]" value="<?php echo esc_attr( $site ); ?>" type="checkbox"<?php echo $checked; ?>>
								</label>
							</th>
							<td>
								<label for="<?php echo esc_attr( $check_id ); ?>"><?php echo esc_html( $site ); ?></label>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<?php if ( !empty( $patchstack['sites'] ) ) { ?>
				<tfoot>
					<tr>
						<td colspan="2">
							<input type="text" name="it-updater-username" placeholder="SolidWP Username" value="<?php echo esc_attr( $post_data['username'] ); ?>" autocomplete="off" />
							<input type="password" name="it-updater-password" placeholder="Password" value="<?php echo esc_attr( $post_data['password'] ); ?>" />
							<input class="button-primary" type="submit" name="submit" value="<?php _e( 'Remove Patchstack Licenses', 'it-l10n-backupbuddy' ); ?>" />
							<input type="hidden" name="action" value="unlicense_patchstack_sites" />
						</td>
					</tr>
				</tfoot>
				<?php } ?>
			</table>
		</div>
	</form>
	<?php
	$site = parse_url( $GLOBALS['ithemes-updater-settings']->get_licensed_site_url(), PHP_URL_HOST );
	if ( !in_array( $site, $patchstack['sites'] ) && !empty( $patchstack_available ) ) {
	?>
	<form id="posts-filter" enctype="multipart/form-data" method="post" action="<?php echo esc_attr( $this->self_url ); ?>" autocomplete="off">
		<div class="ithemes-updater-products" id="ithemes-updater-licensed">
			<table class="ithemes-updater-listing widefat">
				<tfoot>
					<tr>
						<td>
						<?php wp_nonce_field( 'license_patchstack_site', 'ithemes_updater_nonce' ); ?>
						<input type="text" name="it-updater-username" placeholder="SolidWP Username" value="<?php echo esc_attr( $post_data['username'] ); ?>" autocomplete="off" />
						<input type="password" name="it-updater-password" placeholder="Password" value="<?php echo esc_attr( $post_data['password'] ); ?>" />
						<input class="button-secondary" type="submit" name="submit" value="<?php _e( 'License This Site', 'it-l10n-backupbuddy' ); ?>" />
						<input type="hidden" name="action" value="license_patchstack_site" />
						</td>
					</tr>
				</tfoot>
			</table>
		</div>
	</form>
	<?php
	}
	?>
<?php

	}

	private function list_unlicensed_products( $products, $post_data, $action ) {
		if ( empty( $products ) )
			return;

		uksort( $products, 'strnatcasecmp' );

		if ( ( 'license_packages' != $action ) || empty( $this->errors ) ) {
			$post_data = array(
				'username' => '',
				'password' => '',
				'packages' => array(),
			);

			foreach ( $products as $name => $data )
				$post_data['packages'][] = $data['package'];
		}

?>
	<form id="posts-filter" enctype="multipart/form-data" method="post" action="<?php echo esc_attr( $this->self_url ); ?>" autocomplete="off">
		<?php wp_nonce_field( 'license_packages', 'ithemes_updater_nonce' ); ?>

		<div class="ithemes-updater-products" id="ithemes-updater-unlicensed">
			<div class="solidwp-table-header">
				<div>
					<h3><?php _e( 'Unlicensed Products', 'it-l10n-backupbuddy' ); ?></h3>
					<p><?php _e( 'The following products have not been licensed. Licensing a product gives you access to automatic updates from within WordPress.', 'it-l10n-backupbuddy' ); ?></p>
					<p><?php _e( 'To license products, select the products you wish to license, enter your SolidWP membership username and password, and press the License Products button.', 'it-l10n-backupbuddy' ); ?></p>
					<p><?php printf( __( 'Need help? <a href="%s">Click here for a quick video tutorial</a>.', 'it-l10n-backupbuddy' ), 'https://go.solidwp.com/solidwp-updater' ); ?></p>
				</div>
			</div>

			<table class="ithemes-updater-listing widefat">
				<thead>
					<tr>
						<th id="cb" class="manage-column column-cb check-column" scope="col">
							<label class="screen-reader-text" for="cb-select-all-2"><?php _e( 'Select All' ); ?></label>
							<label>
								<input id="cb-select-all-2" type="checkbox"<?php if ( count( $post_data['packages'] ) == count( $products ) ) echo ' checked'; ?> />
							</label>
						</th>
						<th scope="col">
							<label for="cb-select-all-2"><?php _e( 'Product', 'it-l10n-backupbuddy' ); ?></label>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php $count = 0; ?>
					<?php foreach ( $products as $name => $data ) : ?>
						<?php
							$check_id = "cb-select-{$data['package']}";

							if ( 'license_packages' == $action )
								$checked = ( in_array( $data['package'], $post_data['packages'] ) ) ? ' checked' : '';
							else
								$checked = ' checked';

							if ( ++$count % 2 ) {
								$class = 'alt';
							} else {
								$class = '';
							}

						?>
						<tr class="<?php echo $class; ?>">
							<th class="check-column" scope="row">
								<label class="screen-reader-text" for="<?php echo esc_attr( $check_id ); ?>"><?php printf( __( 'Select %s' ), esc_html( $name ) ); ?></label>
								<label for="<?php echo esc_attr( $check_id ); ?>">
									<input id="<?php echo esc_attr( $check_id ); ?>" name="packages[]" value="<?php echo esc_attr( $data['package'] ); ?>" type="checkbox" <?php echo $checked; ?>>
								</label>
							</th>
							<td>
								<label for="<?php echo esc_attr( $check_id ); ?>"><?php echo esc_html( $name ); ?></label>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<td colspan="2">
							<input type="text" name="it-updater-username" placeholder="SolidWP Username" value="<?php echo esc_attr( $post_data['username'] ); ?>" autocomplete="off" />
							<input type="password" name="it-updater-password" placeholder="Password" value="<?php echo esc_attr( $post_data['password'] ); ?>" />
							<input class="button-primary" type="submit" name="submit" value="<?php _e( 'License Products', 'it-l10n-backupbuddy' ); ?>" />
							<input type="hidden" name="action" value="license_packages" />
						</td>
					</tr>
				</tfoot>
			</table>
		</div>
	</form>
<?php

	}

	private function list_unrecognized_products( $products ) {
		if ( empty( $products ) )
			return;

		uksort( $products, 'strnatcasecmp' );

?>
	<div class="ithemes-updater-products" id="ithemes-updater-unrecognized">
		<h3 class="subtitle"><?php _e( 'Unrecognized Products', 'it-l10n-backupbuddy' ); ?></h3>

		<p><?php _e( 'The following products were not recognized by the licensing system. This can be due to a bug in the product code, a temporary server issue, or because the product is no longer supported.', 'it-l10n-backupbuddy' ); ?></p>
		<p><?php printf( __( 'Please check this page again at a later time to see if the problem resolves itself. If the product remains, please contact <a href="%s">SolidWP support</a> and provide them with the details given below.', 'it-l10n-backupbuddy' ), 'https://solidwp.com/support/' ); ?></p>

		<table class="ithemes-updater-listing widefat">
			<thead>
				<tr>
					<th scope="col"><?php _e( 'Product', 'it-l10n-backupbuddy' ); ?></th>
					<th scope="col"><?php _e( 'Type', 'it-l10n-backupbuddy' ); ?></th>
					<th scope="col"><?php _e( 'Package', 'it-l10n-backupbuddy' ); ?></th>
					<th scope="col"><?php _e( 'Version', 'it-l10n-backupbuddy' ); ?></th>
					<th scope="col"><?php _e( 'Server Response', 'it-l10n-backupbuddy' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php $count = 0; ?>
				<?php foreach ( $products as $name => $data ) : ?>
					<?php
						if ( ( isset( $data['status'] ) && 'error' == $data['status'] ) && ( ! empty( $data['error']['message'] ) ) )
							$response = esc_html( "{$data['error']['message']} ({$data['error']['code']})" );
						else
							$response = __( 'Unknown Error', 'it-l10n-backupbuddy' );

						if ( ++$count % 2 ) {
							$class = 'alt';
						} else {
							$class = '';
						}
					?>
					<tr class="<?php echo $class; ?>">
						<td><?php echo esc_html( $name ); ?></td>
						<td><?php echo esc_html( $data['type'] ); ?></td>
						<td><?php echo esc_html( $data['package'] ); ?></td>
						<td><?php echo esc_html( $data['installed'] ); ?></td>
						<td><?php echo $response; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php

	}

	public function show_licensed_site_url_confirmation_page( $post_data = array() ) {
		$this->show_notices();


		$site_url = $GLOBALS['ithemes-updater-settings']->get_licensed_site_url();

		if ( empty( $site_url ) ) {
			$site_url = network_home_url();
		}


		if ( empty( $post_data['username'] ) || empty( $this->errors ) ) {
			$post_data['username'] = '';
		}
		if ( empty( $post_data['password'] ) || empty( $this->errors ) ) {
			$post_data['password'] = '';
		}
		if ( ! empty( $post_data['redirect'] ) ) {
			$redirect = $post_data['redirect'];
		} else if ( ! empty( $_GET['redirect'] ) ) {
			$redirect = $_GET['redirect'];
		} else {
			$redirect = '';
		}


?>
	<div class="wrap" id="ithemes-updater-site-url-confirmation">
		<div class="solidwp-licensing-page-header">
			<img src="<?php echo esc_attr( $this->path_url . '/images/solid_wp_logo.svg' ); ?>" />
		</div>
		<div class="solidwp-licensing">
			<div class="solidwp-licensing-wrap">
				<div class="solidwp-licensing-wrap-header">
					<h2><?php _e( 'Licensing', 'it-l10n-backupbuddy' ); ?></h2>
					<p><?php _e( "Please confirm this site's licensed URL.", 'it-l10n-backupbuddy' ); ?></p>
				</div>

				<form id="posts-filter" enctype="multipart/form-data" method="post" action="<?php echo esc_attr( $this->self_url ); ?>">
					<?php wp_nonce_field( 'save_licensed_site_url', 'ithemes_updater_nonce' ); ?>

					<div id="ithemes-updater-settings">
						<div id="ithemes-updater-table-wrapper">
							<table class="form-table">
								<tbody>
									<tr valign="top">
										<th scope="row">
											<label for="site_url"><?php _e( 'Licensed URL', 'it-l10n-backupbuddy' ); ?></label>

											<?php if ( is_multisite() ) : ?>
												<p class="description"><?php _e( 'The Licensed URL should be the primary URL of this WordPress network.', 'it-l10n-backupbuddy' ); ?></p>
												<p class="description ithemes-updater-description-warning"><?php _e( 'If not set correctly, some features may not function as expected.', 'it-l10n-backupbuddy' ); ?></p>
											<?php else : ?>
												<p class="description"><?php _e( 'The Licensed URL should be the primary URL of this WordPress site.', 'it-l10n-backupbuddy' ); ?></p>
												<p class="description ithemes-updater-description-warning"><?php _e( 'If not set correctly, some features may not function as expected.', 'it-l10n-backupbuddy' ); ?></p>
											<?php endif; ?>
										</th>
									</tr>
									<tr>
										<td>
											<label>
												<input id="site_url" class="regular-text" type="text" name="site_url" value="<?php echo esc_attr( $site_url ); ?>" />
											</label>
										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<p class="submit">
							<input id="save_licensed_site_url" class="button button-primary" type="submit" value="<?php _e( 'Save', 'it-l10n-backupbuddy' ); ?>" />
							<input type="hidden" name="action" value="save_licensed_site_url" />
							<input type="hidden" name="redirect" value="<?php echo esc_attr( $redirect ); ?>" />
						</p>
					</div>
				</form>
				</div>
		</div>
	</div>
<?php

	}

	private function save_licensed_site_url( $data ) {
		check_admin_referer( 'save_licensed_site_url', 'ithemes_updater_nonce' );

		if ( empty( $data['site_url'] ) ) {
			$this->errors[] = __( 'The licensed URL cannot be blank.', 'it-l10n-backupbuddy' );
		} else if ( false === filter_var( $data['site_url'], FILTER_VALIDATE_URL ) ) {
			$this->errors[] = __( 'The licensed URL must be a valid URL.', 'it-l10n-backupbuddy' );
		}

		if ( ! empty( $this->errors ) ) {
			$this->show_licensed_site_url_confirmation_page( $data );
			return;
		}


		$site_url = $GLOBALS['ithemes-updater-settings']->get_site_url( $data['site_url'] );

		if ( $this->has_license_keys() ) {
			$site_url_from_server = $GLOBALS['ithemes-updater-settings']->get_licensed_site_url_from_server();

			if ( $site_url_from_server !== $site_url ) {
				$data['site_url'] = $site_url;
				$this->show_relicensing_page( $data );
				return;
			}
		}


		$GLOBALS['ithemes-updater-settings']->set_licensed_site_url( $site_url );
		$this->messages[] = __( 'Successfully set the Licensed URL.', 'it-l10n-backupbuddy' );

		if ( empty( $data['redirect'] ) ) {
			$redirect = admin_url( 'options-general.php?page=ithemes-licensing&updated_url=true' );
		} else {
			$redirect = $data['redirect'];
		}

		echo '<input id="ithemes-updater-redirect-to-url" type="hidden" value="' . esc_attr( $redirect ) . '" />' . "\n";

		$this->list_packages();
	}

	public function show_relicensing_page( $data = array() ) {
		$this->show_notices();

		$site_url_from_server = $GLOBALS['ithemes-updater-settings']->get_licensed_site_url_from_server();

		if ( empty( $data['relicense_option'] ) ) {
			$data['relicense_option'] = 'relicense';
		}
		if ( empty( $data['username'] ) ) {
			$data['username'] = '';
		}
		if ( empty( $data['password'] ) ) {
			$data['password'] = '';
		}

?>
	<div class="wrap" id="ithemes-updater-relicense">
		<div class="solidwp-licensing-page-header">
			<img src="<?php echo esc_attr( $this->path_url . '/images/solid_wp_logo.svg' ); ?>" />
		</div>
		<div class="solidwp-licensing-wrap">
			<h2><?php _e( 'Licensing', 'it-l10n-backupbuddy' ); ?></h2>

			<p><?php printf( __( 'The licenses on this site are for <code>%s</code>.', 'it-l10n-backupbuddy' ), esc_html( $site_url_from_server ) ); ?></p>

			<form id="posts-filter" enctype="multipart/form-data" method="post" action="<?php echo esc_attr( $this->self_url ); ?>">
				<?php wp_nonce_field( 'relicense', 'ithemes_updater_nonce' ); ?>

				<div id="ithemes-updater-settings">
					<div id="ithemes-updater-table-wrapper">
						<h3><?php _e( 'License Option', 'it-l10n-backupbuddy' ); ?></h3>
						<p>
							<label>
								<input type="radio" name="relicense_option" value="relicense" <?php if ( 'relicense' === $data['relicense_option'] ) echo 'checked="checked"'; ?> />
								<?php _e( 'Create new licenses for this site.', 'it-l10n-backupbuddy' ); ?>
							</label>
						</p>
						<p class="description ithemes-updater-description-warning"><?php _e( 'Use this option if this site was cloned from another site and needs to have its own licenses.', 'it-l10n-backupbuddy' ); ?></p>
						<p>
							<label>
								<input type="radio" name="relicense_option" value="update" <?php if ( 'update' === $data['relicense_option'] ) echo 'checked="checked"'; ?> />
								<?php printf( __( 'Change the existing licenses to be for <code>%s</code>.', 'it-l10n-backupbuddy' ), esc_html( $data['site_url'] ) ); ?>
							</label>
						</p>
						<p class="description ithemes-updater-description-notice">
							<span class="dashicons dashicons-warning"></span>
							<?php printf( __( 'Note: If the <code>%s</code> site still exists and is different from this site, you will have to create new licenses on that site.', 'it-l10n-backupbuddy' ), esc_html( $data['site_url'] ) ); ?>
						</p>
						<p class="description ithemes-updater-description-warning secondary-description"><?php printf( __( 'Use this option if this site\'s primary URL has changed from <code>%1$s</code> to <code>%2$s</code>.', 'it-l10n-backupbuddy' ), esc_html( $site_url_from_server ), esc_html( $data['site_url'] ) ); ?></p>
						<div class="input-container">
							<label for="it-updater-username"><?php _e( 'SolidWP Username', 'it-l10n-backupbuddy' ); ?></label>
							<input id="it-updater-username" type="text" name="it-updater-username" value="<?php echo esc_attr( $data['username'] ); ?>" />
							<label for="it-updater-password"><?php _e( 'SolidWP Password', 'it-l10n-backupbuddy' ); ?></label>
							<input id="it-updater-password" type="password" name="it-updater-password" value="<?php echo esc_attr( $data['password'] ); ?>" />
						</div>
					</div>
				</div>
				<p class="submit">
					<input id="relicense" class="button button-primary" type="submit" value="<?php _e( 'Save', 'it-l10n-backupbuddy' ); ?>" />
					<input type="hidden" name="action" value="relicense" />
					<input type="hidden" name="site_url" value="<?php echo esc_attr( $data['site_url'] ); ?>" />
					<input type="hidden" name="redirect" value="<?php echo esc_attr( $data['redirect'] ); ?>" />
				</p>
			</form>
		</div>
	</div>
<?php

	}

	private function relicense( $data ) {
		check_admin_referer( 'relicense', 'ithemes_updater_nonce' );

		if ( empty( $data['username'] ) && empty( $data['password'] ) ) {
			$this->errors[] = __( 'You must supply a SolidWP membership username and password in order to change the licensed URL.', 'it-l10n-backupbuddy' );
		} else if ( empty( $data['username'] ) ) {
			$this->errors[] = __( 'You must supply a SolidWP membership username in order to change the licensed URL.', 'it-l10n-backupbuddy' );
		} else if ( empty( $data['password'] ) ) {
			$this->errors[] = __( 'You must supply a SolidWP membership password in order to change the licensed URL.', 'it-l10n-backupbuddy' );
		} else if ( empty( $data['site_url'] ) ) {
			$this->errors[] = __( 'The licensed URL cannot be blank.', 'it-l10n-backupbuddy' );
		} else if ( false === filter_var( $data['site_url'], FILTER_VALIDATE_URL ) ) {
			$this->errors[] = __( 'The licensed URL must be a valid URL.', 'it-l10n-backupbuddy' );
		} else if ( empty( $data['relicense_option'] ) || ! in_array( $data['relicense_option'], array( 'relicense', 'update' ) ) ) {
			$this->errors[] = __( 'You must pick one of the License Option options.', 'it-l10n-backupbuddy' );
		}

		if ( ! empty( $this->errors ) ) {
			$this->show_relicensing_page( $data );
			return;
		}


		if ( 'update' === $data['relicense_option'] ) {
			$response = Ithemes_Updater_API::set_licensed_site_url( $data['username'], $data['password'], $data['site_url'] );

			if ( is_wp_error( $response ) ) {
				$this->errors[] = Ithemes_Updater_API::get_error_explanation( $response );
				$this->show_relicensing_page( $data );
				return;
			}

			$GLOBALS['ithemes-updater-settings']->set_licensed_site_url( $data['site_url'] );
			$this->messages[] = __( 'Successfully updated the Licensed URL.', 'it-l10n-backupbuddy' );
		} else {
			require_once( $GLOBALS['ithemes_updater_path'] . '/keys.php' );
			$keys = Ithemes_Updater_Keys::get();
			$packages = array_keys( $keys );

			$response = Ithemes_Updater_API::deactivate_package( $data['username'], $data['password'], $packages );

			if ( is_wp_error( $response ) ) {
				$this->errors[] = Ithemes_Updater_API::get_error_explanation( $response );
				$this->show_relicensing_page( $data );
				return;
			}

			$GLOBALS['ithemes-updater-settings']->set_licensed_site_url( $data['site_url'] );
			$this->messages[] = __( 'Successfully updated the Licensed URL.', 'it-l10n-backupbuddy' );

			$response = Ithemes_Updater_API::activate_package( $data['username'], $data['password'], $packages );

			if ( is_wp_error( $response ) ) {
				$this->errors[] = Ithemes_Updater_API::get_error_explanation( $response );
				$this->show_relicensing_page( $data );
				return;
			}
		}

		if ( empty( $data['redirect'] ) ) {
			$redirect = admin_url( 'options-general.php?page=ithemes-licensing&updated_url=true' );
		} else {
			$redirect = $data['redirect'];
		}

		echo '<input id="ithemes-updater-redirect-to-url" type="hidden" value="' . esc_attr( $redirect ) . '" />' . "\n";

		$this->list_packages();
	}


	private function show_notices() {
		if ( ! empty( $this->messages ) ) {
			foreach ( $this->messages as $message ) {
				echo "<div class=\"updated fade\"><p><strong>$message</strong></p></div>\n";
			}
		}

		if ( ! empty( $this->errors ) ) {
			foreach ( $this->errors as $error ) {
				echo "<div class=\"error\"><p><strong>$error</strong></p></div>\n";
			}
		}

		if ( ! empty( $this->soft_errors ) ) {
			foreach ( $this->soft_errors as $error ) {
				echo "<div class=\"error\"><p><strong>$error</strong></p></div>\n";
			}
		}
	}

	private function get_expiration_string( $expiration_timestamp ) {
		$time = time();

		$time_left = $expiration_timestamp - $time;

		$expired = false;

		if ( $time_left < 0 ) {
			$expired = true;
			$time_left = abs( $time_left );
		}

		if ( $time_left > ( 86400 * 30 ) )
			$expiration = date( 'Y-m-d', $expiration_timestamp );
		else {
			if ( $time_left > 86400 )
				$expiration = sprintf( _n( '%d day', '%d days', intval( $time_left / 86400 ), 'it-l10n-backupbuddy' ), intval( $time_left / 86400 ) );
			else if ( $time_left > 3600 )
				$expiration = sprintf( _n( '%d hour', '%d hours', intval( $time_left / 3600 ), 'it-l10n-backupbuddy' ), intval( $time_left / 3600 ) );
			else if ( $time_left > 60 )
				$expiration = sprintf( _n( '%d minute', '%d minutes', intval( $time_left / 60 ), 'it-l10n-backupbuddy' ), intval( $time_left / 60 ) );
			else
				$expiration = sprintf( _n( '%d second', '%d seconds', $time_left, 'it-l10n-backupbuddy' ), intval( $time_left / 60 ) );

			if ( $expired )
				$expiration = sprintf( __( '%s ago', 'it-l10n-backupbuddy' ), $expiration );
		}

		return $expiration;
	}

	private function has_license_keys() {
		require_once( $GLOBALS['ithemes_updater_path'] . '/keys.php' );

		$keys = Ithemes_Updater_Keys::get();
		$legacy_keys = Ithemes_Updater_Keys::get_legacy();

		if ( empty( $keys ) && empty( $legacy_keys ) ) {
			return false;
		}

		return true;
	}
}


new Ithemes_Updater_Settings_Page();
