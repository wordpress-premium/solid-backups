<?php
/**
 * This code runs whenever in the wp-admin. pb_backupbuddy::$options preloaded.
 *
 * @package BackupBuddy
 */

add_filter( 'admin_body_class', 'backupbuddy_admin_body_class' );

/**
 * BackupBuddy Global Admin Page Class.
 *
 * @param string $body_classes  String of current body classes.
 *
 * @return string  Modified with BackupBuddy Admin Class.
 */
function backupbuddy_admin_body_class( $body_classes = '' ) {
	if ( ! backupbuddy_is_admin_page() ) {
		return $body_classes;
	}

	$body_classes .= ' backupbuddy-admin-page';
	return $body_classes;
}

if ( false !== stristr( pb_backupbuddy::_GET( 'page' ), 'backupbuddy' ) ) {
	add_action( 'in_admin_header', 'bb_admin_head' );
}
/**
 * Insert BackupBuddy Header logo/bar and version.
 */
function bb_admin_head() {
	printf(
		'<div class="bb-topbar-title"><a href="%s"><strong>BACKUP</strong>BUDDY</a><span><span>v</span>%s</span></div>',
		esc_attr( admin_url( 'admin.php?page=pb_backupbuddy_backup' ) ),
		esc_html( pb_backupbuddy::settings( 'version' ) )
	);

	// Javascript functions used in various places.
	?>
	<script type="text/javascript" charset="utf-8">
		var pb_status_append = function( json ) {
			if( 'undefined' === typeof statusBox ) { // No status box yet so may need to create it.
				statusBox = jQuery( '#pb_backupbuddy_status' );
				if( statusBox.length == 0 ) { // No status box yet so suppress.
					return;
				}
			}

			if ( 'string' == ( typeof json ) ) {
				backupbuddy_log( json );
				console.log( 'Status log received string: ' + json );
				return;
			}

			// Used in BackupBuddy _backup-perform.php and ImportBuddy _header.php
			json.date = new Date();
			json.date = new Date(  ( json.time * 1000 ) + json.date.getTimezoneOffset() * 60000 );
			var seconds = json.date.getSeconds();
			if ( seconds < 10 ) {
				seconds = '0' + seconds;
			}
			json.date = backupbuddy_hourpad( json.date.getHours() ) + ':' + json.date.getMinutes() + ':' + seconds;

			triggerEvent = 'backupbuddy_' + json.event;


			// Log non-text events.
			if ( ( 'details' !== json.event ) && ( 'message' !== json.event ) && ( 'error' !== json.event ) ) {
				//console.log( 'Non-text event `' + triggerEvent + '`.' );
			} else {
				//console.log( json.data );
			}
			//console.log( 'trigger: ' + triggerEvent );

			backupbuddy_log( json );

		}; // End function pb_status_append().

		// left hour pad with zeros
		var backupbuddy_hourpad = function(n) {
			return ("0" + n).slice(-2);
		};

		// Used in BackupBuddy _backup-perform.php and ImportBuddy _header.php and _rollback.php
		var backupbuddy_log = function( json ) {
			message = '';

			if ( 'string' == ( typeof json ) ) {
				message = "-----------\t\t-------\t-------\t" + json;
			} else {
				message = json.date + '.' + json.u + " \t" + json.run + "sec \t" + json.mem + "MB\t" + json.data;
			}

			statusBox.append( "\r\n" + message );
			statusBox.scrollTop( statusBox[0].scrollHeight - statusBox.height() );
		};
	</script>
	<?php
}

/********** MISC */

/**
 * Enqueues wp-admin.css file.
 */
function bb_enqueue_wp_admin_style() {
	if ( ! backupbuddy_is_admin_page() ) {
		return;
	}

	wp_register_style( 'backupbuddy-core', pb_backupbuddy::plugin_url() . '/css/backupbuddy-core.css', array(), pb_backupbuddy::settings( 'version' ) );
	wp_enqueue_style( 'backupbuddy-core' );
	wp_enqueue_style( 'pb_backupbuddy-wp-admin', pb_backupbuddy::plugin_url() . '/css/wp-admin.css', array(), pb_backupbuddy::settings( 'version' ) );
}

/**
 * Enqueues wp-admin-global.css file.
 */
function bb_enqueue_wp_admin_global_styles() {
	wp_enqueue_style( 'pb_backupbuddy-wp-admin-global', pb_backupbuddy::plugin_url() . '/css/wp-admin-global.css', array(), pb_backupbuddy::settings( 'version' ) );
}

// Needed for retina icons in menu.
add_action( 'admin_enqueue_scripts', 'bb_enqueue_wp_admin_style' );
global $wp_version;
if ( $wp_version >= 3.8 ) {
	add_action( 'admin_enqueue_scripts', 'bb_enqueue_wp_admin_global_styles' );
}


// Dashboard widget.
if ( '0' == pb_backupbuddy::$options['hide_dashboard_widget'] ) {
	// Enqueue styles for Dashboard Widget.
	function backupbuddy_enqueue_dashboard_stylesheet( $hook ) {
		if ( 'index.php' != $hook ) {
			return;
		}
		wp_enqueue_style( 'bub_dashboard_widget', pb_backupbuddy::plugin_url() . '/css/dashboard_widget.css' );
	}
	add_action( 'admin_enqueue_scripts', 'backupbuddy_enqueue_dashboard_stylesheet' );

	// Display stats in Dashboard.
	if ( ( ! is_multisite() ) || ( is_multisite() && is_network_admin() ) ) { // Only show if standalone OR in main network admin.
		pb_backupbuddy::add_dashboard_widget( 'stats', 'BackupBuddy v' . pb_backupbuddy::settings( 'version' ), 'godmode' );
	}
}


// Load backupbuddy class with helper functions.
if ( ! class_exists( 'backupbuddy_core' ) ) {
	require_once pb_backupbuddy::plugin_path() . '/classes/core.php';
}



/* BEGIN HANDLING DATA STRUCTURE UPGRADE */
$default_options = pb_backupbuddy::settings( 'default_options' );
if ( pb_backupbuddy::$options['data_version'] < $default_options['data_version'] ) {
	backupbuddy_core::verify_directories( true );
	pb_backupbuddy::status( 'details', 'Data structure version of `' . pb_backupbuddy::$options['data_version'] . '` behind current version of `' . $default_options['data_version'] . '`. Running activation upgrade.' );
	require_once pb_backupbuddy::plugin_path() . '/controllers/activation.php';
}
/* END HANDLING DATA STRUCTURE UPGRADE */



// Schedule daily housekeeping.
backupbuddy_core::verifyHousekeeping();
backupbuddy_core::verifyLiveCron();


/********** ACTIONS (admin) */


// Set up reminders if enabled.
if ( pb_backupbuddy::$options['backup_reminders'] == '1' ) {
	pb_backupbuddy::add_action( array( 'load-update-core.php', 'wp_update_backup_reminder' ) );
	pb_backupbuddy::add_action( array( 'post_updated_messages', 'content_editor_backup_reminder_on_update' ) );
}

// Display warning to network activate if running in normal mode on a MultiSite Network.
if ( is_multisite() && ! backupbuddy_core::is_network_activated() ) {
	pb_backupbuddy::add_action( array( 'all_admin_notices', 'multisite_network_warning' ) ); // BB should be network activated while on Multisite.
}


pb_backupbuddy::add_action( array( 'itbub_save_setting', 'enable_advanced_dashboard_widget' ), 10, 3 );

/********** AJAX (admin) */



pb_backupbuddy::add_ajax( 'backupbuddy' ); // New AJAX wrapper to begin passing all AJAX through this single call to reduce number of registered hooks. POST or GET the var function containing the function.php file to run within controllers/ajax.
// pb_backupbuddy::add_ajax( 'ajax_controller_callback_function' ); // Tell WordPress about this AJAX callback.
// Register BackupBuddy API. As of BackupBuddy v5.0. Access credentials will be checked within callback.
add_action( 'wp_ajax_backupbuddy_api', array( pb_backupbuddy::$_ajax, 'api' ) );
add_action( 'wp_ajax_nopriv_backupbuddy_api', array( pb_backupbuddy::$_ajax, 'api' ) );





/********** FILTERS (admin) */



pb_backupbuddy::add_filter( 'plugin_row_meta', 10, 2 );



/********** PAGES (admin) */



$icon = '';

if ( is_multisite() && backupbuddy_core::is_network_activated() && ! defined( 'PB_DEMO_MODE' ) ) { // Multisite installation.
	if ( defined( 'PB_BACKUPBUDDY_MULTISITE_EXPERIMENT' ) && ( PB_BACKUPBUDDY_MULTISITE_EXPERIMENT == true ) ) { // comparing with bool but loose so string is acceptable.

		if ( is_network_admin() ) { // Network Admin pages.
			pb_backupbuddy::add_page( '', 'backup', array( pb_backupbuddy::settings( 'name' ), __( 'Backups', 'it-l10n-backupbuddy' ) ), 'manage_network', $icon );
			if ( '1' !== pb_backupbuddy::$options['hide_live'] && ( true !== apply_filters( 'itbub_hide_stash_live', false ) ) ) {
				pb_backupbuddy::add_page( 'backup', 'live', __( 'Stash Live', 'it-l10n-backupbuddy' ), 'manage_network' );
			}
			pb_backupbuddy::add_page( 'backup', 'destinations', __( 'Destinations', 'it-l10n-backupbuddy' ), 'manage_network' );
			pb_backupbuddy::add_page( 'backup', 'multisite_import', __( 'MS Import (beta)', 'it-l10n-backupbuddy' ), 'manage_network' );
			pb_backupbuddy::add_page( 'backup', 'scheduling', __( 'Schedules', 'it-l10n-backupbuddy' ), 'manage_network' );
			pb_backupbuddy::add_page( 'backup', 'diagnostics', __( 'Diagnostics', 'it-l10n-backupbuddy' ), 'manage_network' );
			pb_backupbuddy::add_page( 'backup', 'settings', __( 'Settings', 'it-l10n-backupbuddy' ), 'manage_network' );
		} else { // Subsite pages.
			$export_note = '';

			$options          = get_site_option( 'pb_' . pb_backupbuddy::settings( 'slug' ) );
			$multisite_export = $options['multisite_export'];
			unset( $options );

			if ( $multisite_export == '1' ) { // Settings enable admins to export. Set capability to admin and higher only.
				$capability   = pb_backupbuddy::$options['role_access'];
				$export_title = '<span title="Note: Enabled for both subsite Admins and Network Superadmins based on BackupBuddy settings">' . __( 'MS Export (experimental)', 'it-l10n-backupbuddy' ) . '</span>';
			} else { // Settings do NOT allow admins to export; set capability for superadmins only.
				$capability   = 'manage_network';
				$export_title = '<span title="Note: Enabled for Network Superadmins only based on BackupBuddy settings">' . __( 'MS Export SA (experimental)', 'it-l10n-backupbuddy' ) . '</span>';
			}

			// pb_backupbuddy::add_page( '', 'getting_started', array( pb_backupbuddy::settings( 'name' ), 'Getting Started' . $export_note ), $capability );
			pb_backupbuddy::add_page( '', 'multisite_export', array( pb_backupbuddy::settings( 'name' ), $export_title ), $capability, $icon );
		}
	} else { // PB_BACKUPBUDDY_MULTISITE_EXPERIMENT not in wp-config / set to TRUE.
		pb_backupbuddy::status( 'error', 'Multisite detected but PB_BACKUPBUDDY_MULTISITE_EXPERIMENT definition not found in wp-config.php / not defined to boolean TRUE.' );
	}
} else { // Standalone site.

	pb_backupbuddy::add_page( '', 'backup', array( pb_backupbuddy::settings( 'name' ), __( 'Backups', 'it-l10n-backupbuddy' ) ), pb_backupbuddy::$options['role_access'], $icon );
	if ( '1' !== pb_backupbuddy::$options['hide_live'] && ( true !== apply_filters( 'itbub_hide_stash_live', false ) ) ) {
		pb_backupbuddy::add_page( 'backup', 'live', __( 'Stash Live', 'it-l10n-backupbuddy' ), pb_backupbuddy::$options['role_access'] );
	}
	pb_backupbuddy::add_page( 'backup', 'destinations', __( 'Destinations', 'it-l10n-backupbuddy' ), pb_backupbuddy::$options['role_access'] );
	pb_backupbuddy::add_page( 'backup', 'scheduling', __( 'Schedules', 'it-l10n-backupbuddy' ), pb_backupbuddy::$options['role_access'] );
	pb_backupbuddy::add_page( 'backup', 'diagnostics', __( 'Diagnostics', 'it-l10n-backupbuddy' ), pb_backupbuddy::$options['role_access'] );
	pb_backupbuddy::add_page( 'backup', 'settings', __( 'Settings', 'it-l10n-backupbuddy' ), pb_backupbuddy::$options['role_access'] );
}



/********** OTHER (admin) */
add_filter( 'contextual_help', 'pb_backupbuddy_contextual_help', 10, 3 );
function pb_backupbuddy_contextual_help( $contextual_help, $screen_id, $screen ) {
	// Loads help from file in controllers/help/:PAGENAME:.php
	if ( ! current_user_can( pb_backupbuddy::$options['role_access'] ) ) {
		return;
	}

	// WordPress pre-v3.3 so no contextual help.
	if ( ! method_exists( $screen, 'add_help_tab' ) ) {
		return $contextual_help;
	}

	// Not a backupbuddy page.
	if ( false === stristr( $screen_id, 'backupbuddy' ) ) {
		return $contextual_help;
	}

	// Load page-specific help.
	$page     = str_replace( 'pb_backupbuddy_', '', str_replace( 'toplevel_page_', '', str_replace( 'backupbuddy_page_pb_backupbuddy_', '', $screen_id ) ) );
	$helpFile = dirname( __FILE__ ) . '/controllers/help/' . $page . '.php';
	if ( file_exists( $helpFile ) ) {
		include $helpFile;
	}

	// Global help.
	$screen->add_help_tab(
		array(
			'id'      => 'pb_backupbuddy_additionalhelp',
			'title'   => __( 'Tutorials & Support', 'it-l10n-backupbuddy' ),
			'content' => '<p>
					<a href="http://ithemes.com/publishing/getting-started-with-backupbuddy/" target="_blank">' . __( 'Getting Started eBook', 'it-l10n-backupbuddy' ) . '</a>
					<br>
					<a href="https://ithemeshelp.zendesk.com/hc/en-us" target="_blank">' . __( 'Knowledge Base & Tutorials', 'it-l10n-backupbuddy' ) . '</a>
					<br>
					<a href="http://ithemes.com/support/" target="_blank"><b>' . __( 'Support', 'it-l10n-backupbuddy' ) . '</b></a>
				</p>',
		)
	);

	return $contextual_help;

} // End pb_backupbuddy_contextual_help().



/***** BEGIN STASH LIVE ADMIN BAR *****/
function backupbuddy_live_admin_bar_menu( $wp_admin_bar ) {
	if ( ! current_user_can( pb_backupbuddy::$options['role_access'] ) || '1' === pb_backupbuddy::$options['hide_live'] ) {
		return;
	}

	$args = array(
		'id'    => 'backupbuddy_stash_live_admin_bar',
		'title' => 'BackupBuddy Stash Live',
	);
	$wp_admin_bar->add_node( $args );

	$child_args = array();

	array_push(
		$child_args, array(
			'id'     => 'backupbuddy_stash_live_admin_bar_stats',
			'title'  => '<div class="backupbuddy-stash-live-admin-bar-stats-container"><span class="backupbuddy-pulsing-orb"></span><span class="backupbuddy-stash-live-admin-bar-stats-text">' . __( 'Loading...', 'it-l10n-backupbuddy' ) . '</span></div>',
			'href'   => admin_url( 'admin.php?page=pb_backupbuddy_live' ),
			'parent' => 'backupbuddy_stash_live_admin_bar',
		)
	);

	foreach ( $child_args as $args ) {
		$wp_admin_bar->add_node( $args );
	}

}
function backupbuddy_live_admin_bar_script() {
	if ( ! current_user_can( pb_backupbuddy::$options['role_access'] ) ) {
		return;
	}

	wp_register_script( 'backupbuddy_live_admin_bar', pb_backupbuddy::plugin_url() . '/destinations/live/admin_bar.js', array( 'jquery' ) );
	wp_localize_script(
		'backupbuddy_live_admin_bar',
		'backupbuddy_live_admin_bar_translations',
		array(
			'currently' => __( 'Currently', 'it-l10n-backupbuddy' ),
		)
	);
	wp_enqueue_script( 'backupbuddy_live_admin_bar' );
	wp_enqueue_style( 'backupbuddy_live_admin_bar_style', pb_backupbuddy::plugin_url() . '/destinations/live/admin_bar.css' );
}

/**
 * Poll Stash Live Stats.
 */
function backupbuddy_live_statsPoll() {
	if ( ! current_user_can( pb_backupbuddy::$options['role_access'] ) ) {
		return;
	}
	if ( backupbuddy_restore()->in_progress() ) {
		return;
	}

	include pb_backupbuddy::plugin_path() . '/destinations/live/_statsPoll.php';
}

if ( 'disconnect' != pb_backupbuddy::_GET( 'live_action' ) ) { // If not disconnecting from Live this pageload.
	foreach ( pb_backupbuddy::$options['remote_destinations'] as $destination ) { // Look for Live destination.
		if ( ( 'live' == $destination['type'] ) && ( isset( $destination['show_admin_bar'] ) ) && ( '1' == $destination['show_admin_bar'] ) ) {
			add_action( 'admin_bar_menu', 'backupbuddy_live_admin_bar_menu', 999 );
			add_action( 'admin_enqueue_scripts', 'backupbuddy_live_admin_bar_script' );
			add_action( 'admin_footer', 'backupbuddy_live_statsPoll', 999 );
			break;
		}
	}
}
/***** END STASH LIVE ADMIN BAR *****/

/**
 * Admin Notices/Release Banner.
 */
function backupbuddy_admin_notices() {
	if ( ! current_user_can( pb_backupbuddy::$options['role_access'] ) ) {
		return;
	}

	/*
	if ( is_network_admin() ) {
		$stashlive_url = network_admin_url( 'admin.php' );
	} else {
		$stashlive_url = admin_url( 'admin.php' );
	}
	$stashlive_url .= '?page=pb_backupbuddy_backup';
	pb_backupbuddy::disalert( 'backupbuddy_version_eight', '<p>BackupBuddy 8.0 is here with new supercharged Backup Profiles.&nbsp;&nbsp;<a class="backupbuddy-nag-button pb_backupbuddy_disalert" href="' . $stashlive_url . '" alt="' . pb_backupbuddy::ajax_url( 'disalert' ) . '">Get Started</a><a class="backupbuddy-nag-button" href="https://ithemes.com/backupbuddy-8-0-is-here" target="_blank">See What\'s New</a></p><span>With BackupBuddy\'s new smart backup profiles, you have granular control over the contents of your backups. Check out new backup profiles to backup themes, plugins and media files, along with new powerful options to customize your backups.  <a href="https://ithemes.com/backupbuddy-8-0-is-here" target="_blank">Read more </a></span>' );
	*/

	// Stash / S3 deprecation notice. This block can be updated or deleted after 2018-06-08.
	$deprecated_destinations = array();
	foreach ( (array) pb_backupbuddy::$options['remote_destinations'] as $destination ) {
		if ( in_array( $destination['type'], array( 'stash', 'stash2', 's3', 's32' ) ) ) {
			$deprecated_destinations[] = $destination['title'];
		}
	}
	if ( ! empty( $deprecated_destinations ) && version_compare( phpversion(), '5.5', '<' ) ) {
		wp_enqueue_style( 'backupbuddy_deprecated_s3_destinations', pb_backupbuddy::plugin_url() . '/css/deprecated_s3_notice.css' );
		$notice  = '<p>Warning: Active BackupBuddy Destinations will soon require a PHP upgrade.</p>';
		$notice .= '<span>As early as <strong>June 8, 2018</strong>, Stash and Amazon S3 destination types will require PHP 5.5 or higher to work. The following destinations will be affected on this site:';
		$notice .= ' <em>' . implode( $deprecated_destinations, ', ' ) . '</em></span>';
		$notice .= '<p><a href="#" class="backupbuddy-nag-button">More Information</a></p>';
		$notice .= '<span class="more_info">Your webserver is currently running PHP version ' . phpversion() . '. The latest Amazon S3 libraries will not support PHP versions less than 5.5. Please contact your host and ask them about how to upgrade as soon as possible. Here is an example email you can send to them:<br /><blockquote>Hello,<br />My website (' . get_option("siteurl" ) . ') is currently using an outdated version of PHP (v. ' . phpversion() . '). Can you tell me what I need to do to update it to the latest stable version?.<br />Thank you.</blockquote>';
		$notice .= '<br /><span>You may manage your BackupBuddy remote destinations <a href="' . esc_url( admin_url( 'admin.php?page=pb_backupbuddy_destinations' ) ) . '">here</a></span>';
		pb_backupbuddy::disalert( 'deprecated_s3_destinations', $notice );
	}
}
if ( ( ! is_multisite() ) || ( is_multisite() && is_network_admin() ) ) { // Only show if standalone OR in main network admin.
	add_action( 'admin_notices', 'backupbuddy_admin_notices' );
}

/**
 * Global admin javascript files.
 */
function backupbuddy_global_admin_scripts() {
	wp_register_script( 'backupbuddy_global_admin_scripts', pb_backupbuddy::plugin_url() . '/js/global_admin.js', array( 'jquery' ) );
	wp_enqueue_script( 'backupbuddy_global_admin_scripts' );

	if ( ! backupbuddy_is_admin_page() ) {
		return;
	}

	$js_vars = array(
		'ajax_url'             => admin_url( 'admin-ajax.php' ),
		'admin_url'            => is_network_admin() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' ),
		'ajax_base'            => pb_backupbuddy::ajax_url( '' ),
		'strings'              => array(
			'error'                          => esc_html__( 'Error', 'it-l10n-backupbuddy' ),
			'quick_setup'                    => esc_html__( 'Quick Setup', 'it-l10n-backupbuddy' ),
			'importbuddy'                    => esc_html__( 'ImportBuddy', 'it-l10n-backupbuddy' ),
			'importbuddy_download'           => esc_html__( 'Download importbuddy.php', 'it-l10n-backupbuddy' ),
			'importbuddy_send'               => esc_html__( 'Send importbuddy.php to a remote destination', 'it-l10n-backupbuddy' ),
			'importbuddy_prompt_nopass'      => esc_html__( 'To download, enter a password to lock the ImportBuddy script from unauthorized access. You will be prompted for this password when you go to importbuddy.php in your browser. Since you have not defined a default password yet this will be used as your default and can be changed later from the Settings page.', 'it-l10n-backupbuddy' ),
			'importbuddy_prompt_haspass'     => esc_html__( 'To download, either enter a new password for just this download OR LEAVE BLANK to use your default ImportBuddy password (set on the Settings page) to lock the ImportBuddy script from unauthorized access.', 'it-l10n-backupbuddy' ),
			'importbuddy_no_password'        => esc_html__( 'You have not set a default password on the Settings page so you must provide a password here to download ImportBuddy.', 'it-l10n-backupbuddy' ),
			'remote_send_error'              => esc_html__( 'Error starting remote send', 'it-l10n-backupbuddy' ),
			'local_delete_upon_success'      => esc_html__( 'The local backup will be deleted upon successful transfer as selected.', 'it-l10n-backupbuddy' ),
			'remote_send_confirmed'          => esc_html__( 'Your file has been scheduled to be sent now. It should arrive shortly. You will be notified by email if any problems are encountered.', 'it-l10n-backupbuddy' ),
			'note_instructions'              => esc_html__( 'Enter a short descriptive note to apply to this archive for your reference. (175 characters max)', 'it-l10n-backupbuddy' ),
			'file'                           => esc_html__( 'file', 'it-l10n-backupbuddy' ),
			'files'                          => esc_html__( 'files', 'it-l10n-backupbuddy' ),
			'folder'                         => esc_html__( 'folder', 'it-l10n-backupbuddy' ),
			'folders'                        => esc_html__( 'folders', 'it-l10n-backupbuddy' ),
			'wp_version'                     => esc_html__( 'WordPress version', 'it-l10n-backupbuddy' ),
			'confirm_restore_title'          => esc_html__( 'WARNING', 'it-l10n-backupbuddy' ),
			'select_restore_title'           => esc_html__( 'Restore', 'it-l10n-backupbuddy' ),
			'confirm_full_restore'           => '<p>' . esc_html__( 'Any existing database tables and files will be overwritten.', 'it-l10n-backupbuddy' ) . ' <strong>' . __( 'This cannot be undone.', 'it-l10n-backupbuddy' ) . '</strong></p><p>' . esc_html__( 'Are you sure you want to restore this entire backup?', 'it-l10n-backupbuddy' ) . '</p>',
			'confirm_full_db_restore'        => '<p>' . esc_html__( 'Any existing database tables will be overwritten.', 'it-l10n-backupbuddy' ) . ' <strong>' . __( 'This cannot be undone.', 'it-l10n-backupbuddy' ) . '</strong></p><p>' . esc_html__( 'Are you sure you want to restore this entire database?', 'it-l10n-backupbuddy' ) . '</p>',
			'confirm_full_files_restore'     => '<p>' . esc_html__( 'Any existing files will be overwritten.', 'it-l10n-backupbuddy' ) . ' <strong>' . __( 'This cannot be undone.', 'it-l10n-backupbuddy' ) . '</strong></p><p>' . esc_html__( 'Are you sure you want to restore this entire backup?', 'it-l10n-backupbuddy' ) . '</p>',
			'confirm_partial_restore'        => '<p>' . esc_html__( 'Any existing files will be overwritten.', 'it-l10n-backupbuddy' ) . ' <strong>' . __( 'This cannot be undone.', 'it-l10n-backupbuddy' ) . '</strong></p><p>' . esc_html__( 'Are you sure you want to restore the selected files/folders?', 'it-l10n-backupbuddy' ) . '</p>',
			'confirm_full_restore_cbx'       => esc_html__( 'Yes, restore this entire backup.', 'it-l10n-backupbuddy' ),
			'confirm_full_files_restore_cbx' => esc_html__( 'Yes, restore all files in this backup.', 'it-l10n-backupbuddy' ),
			'confirm_full_db_restore_cbx'    => esc_html__( 'Yes, restore this entire database.', 'it-l10n-backupbuddy' ),
			'confirm_partial_restore_cbx'    => esc_html__( 'Yes, restore the selected files/folders.', 'it-l10n-backupbuddy' ),
			'confirm_restore_affirmative'    => esc_html__( 'Yes, Proceed', 'it-l10n-backupbuddy' ),
			'continue'                       => esc_html__( 'Continue', 'it-l10n-backupbuddy' ),
			'confirm_restore_error'          => esc_html__( 'You must check this box in order to proceed:', 'it-l10n-backupbuddy' ),
			'select_restore_type_error'      => esc_html__( 'Please select an option.', 'it-l10n-backupbuddy' ),
			'aborting_restore'               => esc_html__( 'Aborting...', 'it-l10n-backupbuddy' ),
			'starting_restore'               => esc_html__( 'Starting backup restore...', 'it-l10n-backupbuddy' ),
			'error_testing'                  => esc_html__( 'Error testing', 'it-l10n-backupbuddy' ),
			'email_test_sent'                => esc_html__( 'Email has been sent. If you do not receive it check your WordPress and server settings.', 'it-l10n-backupbuddy' ),
			'local_backups'                  => esc_html__( 'Local Backups' ),
			'remote_backups'                 => esc_html__( 'Remote Backups' ),
		),
		'hide_quick_setup'     => true === apply_filters( 'itbub_hide_quickwizard', false ),
		'importbuddy_pass_set' => '' == pb_backupbuddy::$options['importbuddy_pass_hash'] ? 0 : 1,
		'page_url'             => pb_backupbuddy::page_url(),
	);

	wp_register_script( 'backupbuddy-min', pb_backupbuddy::plugin_url() . '/js/backupbuddy.min.js', array( 'jquery' ), pb_backupbuddy::settings( 'version' ) );
	pb_backupbuddy::load_script( 'backupbuddy-min', false, $js_vars );
}
add_action( 'admin_enqueue_scripts', 'backupbuddy_global_admin_scripts' );

/**
 * Stash3 Download Backup.
 */
function backupbuddy_stash3_download() {
	if ( ! pb_backupbuddy::_GET( 'stash3-download' ) ) {
		return;
	}

	$file        = pb_backupbuddy::_GET( 'stash3-download' );
	$destination = pb_backupbuddy::_GET( 'stash3-destination-id' );

	if ( empty( pb_backupbuddy::$options['remote_destinations'][ $destination ] ) ) {
		return false;
	}

	if ( ! class_exists( 'pb_backupbuddy_destination_stash3' ) ) {
		require_once pb_backupbuddy::plugin_path() . '/destinations/stash3/init.php';
	}

	$settings = pb_backupbuddy::$options['remote_destinations'][ $destination ];
	$url      = pb_backupbuddy_destination_stash3::get_file_url( $settings, $file );

	if ( ! $url ) {
		return false;
	}

	header( 'Location: ' . $url );
	exit();
}
add_action( 'admin_init', 'backupbuddy_stash3_download' );

/**
 * Stash2 Download Backup.
 */
function backupbuddy_stash2_download() {
	if ( ! pb_backupbuddy::_GET( 'stash2-download' ) ) {
		return;
	}

	$file        = pb_backupbuddy::_GET( 'stash2-download' );
	$destination = pb_backupbuddy::_GET( 'stash2-destination-id' );

	if ( empty( pb_backupbuddy::$options['remote_destinations'][ $destination ] ) ) {
		return false;
	}

	if ( ! class_exists( 'pb_backupbuddy_destination_stash2' ) ) {
		require_once pb_backupbuddy::plugin_path() . '/destinations/stash2/init.php';
	}

	$settings = pb_backupbuddy::$options['remote_destinations'][ $destination ];
	$url      = pb_backupbuddy_destination_stash2::get_file_url( $settings, $file );

	if ( ! $url ) {
		return false;
	}

	header( 'Location: ' . $url );
	exit();
}
add_action( 'admin_init', 'backupbuddy_stash2_download' );

/**
 * OneDrive OAuth Redirect.
 */
function backupbuddy_onedrive_redirect() {
	if ( ! pb_backupbuddy::_GET( 'onedrive-authorize' ) ) {
		return;
	}

	if ( ! class_exists( 'pb_backupbuddy_destination_onedrive' ) ) {
		require_once pb_backupbuddy::plugin_path() . '/destinations/onedrive/init.php';
	}

	pb_backupbuddy_destination_onedrive::oauth_redirect();
}
add_action( 'admin_init', 'backupbuddy_onedrive_redirect' );

/**
 * OneDrive Force Download.
 */
function backupbuddy_onedrive_download() {
	if ( ! pb_backupbuddy::_GET( 'onedrive-download' ) ) {
		return;
	}

	$file_id     = pb_backupbuddy::_GET( 'onedrive-download' );
	$destination = pb_backupbuddy::_GET( 'onedrive-destination-id' );

	if ( ! class_exists( 'pb_backupbuddy_destination_onedrive' ) ) {
		require_once pb_backupbuddy::plugin_path() . '/destinations/onedrive/init.php';
	}

	$settings = pb_backupbuddy::$options['remote_destinations'][ $destination ];
	pb_backupbuddy_destination_onedrive::force_download( $settings, $file_id );
}
add_action( 'admin_init', 'backupbuddy_onedrive_download' );
