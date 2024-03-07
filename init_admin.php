<?php
/**
 * This code runs whenever in the wp-admin. pb_backupbuddy::$options preloaded.
 *
 * @package BackupBuddy
 */

add_filter( 'admin_body_class', 'backupbuddy_admin_body_class' );

/**
 * Solid Backups Global Admin Page Class.
 *
 * @param string $body_classes String of current body classes.
 *
 * @return string  Modified with Solid Backups Admin Class.
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
 * Insert Solid Backups Header logo/bar.
 */
function bb_admin_head() {

	printf(
		'<div class="bb-topbar-title"><a href="%s" class="solid-backups-logo"><span class="screen-reader-text"><strong>Solid</strong> Backups</a></span></div>',
		esc_attr( admin_url( 'admin.php?page=pb_backupbuddy_backup' ) )
	);

	// Javascript functions used in various places.
	?>
	<script type="text/javascript" charset="utf-8">
		var pb_status_append = function ( json ) {
			if ( 'undefined' === typeof statusBox ) { // No status box yet so may need to create it.
				statusBox = jQuery( '#pb_backupbuddy_status' );
				if ( statusBox.length == 0 ) { // No status box yet so suppress.
					return;
				}
			}

			if ( 'string' == (typeof json) ) {
				backupbuddy_log( json );
				console.log( 'Status log received string: ' + json );
				return;
			}

			// Used in Solid Backups _backup-perform.php and Importer _header.php
			json.date = new Date();
			json.date = new Date( (json.time * 1000) + json.date.getTimezoneOffset() * 60000 );
			var seconds = json.date.getSeconds();
			if ( seconds < 10 ) {
				seconds = '0' + seconds;
			}
			json.date = backupbuddy_hourpad( json.date.getHours() ) + ':' + json.date.getMinutes() + ':' + seconds;

			triggerEvent = 'backupbuddy_' + json.event;


			// Log non-text events.
			if ( ('details' !== json.event) && ('message' !== json.event) && ('error' !== json.event) ) {
				//console.log( 'Non-text event `' + triggerEvent + '`.' );
			} else {
				//console.log( json.data );
			}
			//console.log( 'trigger: ' + triggerEvent );

			backupbuddy_log( json );

		}; // End function pb_status_append().

		// left hour pad with zeros
		var backupbuddy_hourpad = function ( n ) {
			return ("0" + n).slice( -2 );
		};

		// Used in Solid Backups _backup-perform.php and Importer _header.php and _rollback.php
		var backupbuddy_log = function ( json ) {
			message = '';

			if ( 'string' == (typeof json) ) {
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



// Dashboard widget.
if ( '0' == pb_backupbuddy::$options['hide_dashboard_widget'] ) {
	// Enqueue styles for Dashboard Widget.
	function backupbuddy_enqueue_dashboard_stylesheet( $hook ) {
		if ( 'index.php' != $hook ) {
			return;
		}
		wp_enqueue_style( 'solid-backups-dashboard-widget', pb_backupbuddy::plugin_url() . '/assets/dist/css/dashboard-widget.css' );
	}

	add_action( 'admin_enqueue_scripts', 'backupbuddy_enqueue_dashboard_stylesheet' );

	// Display stats in Dashboard.
	if ( ( ! is_multisite() ) || ( is_multisite() && is_network_admin() ) ) { // Only show if standalone OR in main network admin.
		pb_backupbuddy::add_dashboard_widget( 'stats', 'Solid Backups v' . pb_backupbuddy::settings( 'version' ), 'godmode' );
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

/**
 * Schedule daily housekeeping.
 */
function pb_backupbuddy_schedule_housekeeping() {
	backupbuddy_core::verifyHousekeeping();
	backupbuddy_core::verifyLiveCron();
}
add_action( 'action_scheduler_init', 'pb_backupbuddy_schedule_housekeeping', 20 );


pb_backupbuddy::add_action( array( 'itbub_save_setting', 'enable_advanced_dashboard_widget' ), 10, 3 );

/********** AJAX (admin) */


pb_backupbuddy::add_ajax( 'backupbuddy' ); // New AJAX wrapper to begin passing all AJAX through this single call to reduce number of registered hooks. POST or GET the var function containing the function.php file to run within controllers/ajax.
// pb_backupbuddy::add_ajax( 'ajax_controller_callback_function' ); // Tell WordPress about this AJAX callback.
// Register Solid Backups API. As of Solid Backups v5.0. Access credentials will be checked within callback.
add_action( 'wp_ajax_backupbuddy_api', array( pb_backupbuddy::$_ajax, 'api' ) );
add_action( 'wp_ajax_nopriv_backupbuddy_api', array( pb_backupbuddy::$_ajax, 'api' ) );


/********** FILTERS (admin) */


pb_backupbuddy::add_filter( 'plugin_row_meta', 10, 2 );


/********** PAGES (admin) */


$icon = '';

if ( is_multisite() && backupbuddy_core::is_network_activated() && ! defined( 'PB_DEMO_MODE' ) ) { // Multisite installation.
	if ( defined( 'PB_BACKUPBUDDY_MULTISITE_EXPERIMENT' ) && ( PB_BACKUPBUDDY_MULTISITE_EXPERIMENT == true ) ) { // comparing with bool but loose so string is acceptable.

		if ( is_network_admin() ) { // Network Admin pages.
			pb_backupbuddy::add_page( '', 'backup', __('Backups', 'it-l10n-backupbuddy'), 'manage_network', 'dashicons-database' );
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
				$export_title = '<span title="Note: Enabled for both subsite Admins and Network Superadmins based on Solid Backups settings">' . __( 'MS Export (experimental)', 'it-l10n-backupbuddy' ) . '</span>';
			} else { // Settings do NOT allow admins to export; set capability for superadmins only.
				$capability   = 'manage_network';
				$export_title = '<span title="Note: Enabled for Network Superadmins only based on Solid Backups settings">' . __( 'MS Export SA (experimental)', 'it-l10n-backupbuddy' ) . '</span>';
			}

			// pb_backupbuddy::add_page( '', 'getting_started', array( pb_backupbuddy::settings( 'name' ), 'Getting Started' . $export_note ), $capability );
			pb_backupbuddy::add_page( '', 'multisite_export', array( pb_backupbuddy::settings( 'name' ), $export_title ), $capability, $icon );
		}
	} else { // PB_BACKUPBUDDY_MULTISITE_EXPERIMENT not in wp-config / set to TRUE.
		pb_backupbuddy::status( 'error', 'Multisite detected but PB_BACKUPBUDDY_MULTISITE_EXPERIMENT definition not found in wp-config.php / not defined to boolean TRUE.' );
	}
} else { // Standalone site.

	pb_backupbuddy::add_page( '', 'backup',  __('Backups', 'it-l10n-backupbuddy'), pb_backupbuddy::$options['role_access'], 'dashicons-database' );
	if ( '1' !== pb_backupbuddy::$options['hide_live'] && ( true !== apply_filters( 'itbub_hide_stash_live', false ) ) ) {
		pb_backupbuddy::add_page( 'backup', 'live', __( 'Stash Live', 'it-l10n-backupbuddy' ), pb_backupbuddy::$options['role_access'] );
	}
	pb_backupbuddy::add_page( 'backup', 'destinations', __( 'Destinations', 'it-l10n-backupbuddy' ), pb_backupbuddy::$options['role_access'] );
	pb_backupbuddy::add_page( 'backup', 'scheduling', __( 'Schedules', 'it-l10n-backupbuddy' ), pb_backupbuddy::$options['role_access'] );
	pb_backupbuddy::add_page( 'backup', 'diagnostics', __( 'Diagnostics', 'it-l10n-backupbuddy' ), pb_backupbuddy::$options['role_access'] );
	pb_backupbuddy::add_page( 'backup', 'settings', __( 'Settings', 'it-l10n-backupbuddy' ), pb_backupbuddy::$options['role_access'] );
}


/**
 * Add Contextual Help when necessary.
 */

add_action( 'admin_head', 'pb_backupbuddy_contextual_help' );

/**
 * Add contextual Help to Solid Backups pages.
 */
function pb_backupbuddy_contextual_help() {
	// Get the current screen object.
	$screen = get_current_screen();

	// Loads help from file in controllers/help/:PAGENAME:.php
	if ( ! current_user_can( pb_backupbuddy::$options['role_access'] ) ) {
		return;
	}

	// WordPress pre-v3.3 so no contextual help.
	if ( is_object( $screen ) && ! method_exists( $screen, 'add_help_tab' ) ) {
		return;
	}

	// Not a backupbuddy page.
	if ( false === stristr( $screen->id, 'backupbuddy' ) ) {
		return;
	}

	// Load page-specific help.
	$page      = str_replace( 'pb_backupbuddy_', '', str_replace( 'toplevel_page_', '', str_replace( 'backupbuddy_page_pb_backupbuddy_', '', $screen->id ) ) );
	$help_file = dirname( __FILE__ ) . '/controllers/help/' . $page . '.php';

	if ( file_exists( $help_file ) ) {
		include $help_file;
	}

	// Global help.
	$screen->add_help_tab(
		array(
			'id'      => 'pb_backupbuddy_additionalhelp',
			'title'   => __( 'Tutorials & Support', 'it-l10n-backupbuddy' ),
			'content' => '<p>
					<a href="https://go.solidwp.com/getting-started-backups" target="_blank">' . __( 'Getting Started eBook', 'it-l10n-backupbuddy' ) . '</a>
					</p><p>
					<a href="https://go.solidwp.com/help-center" target="_blank">' . __( 'Knowledge Base & Tutorials', 'it-l10n-backupbuddy' ) . '</a>
					</p><p>
					<a href="https://go.solidwp.com/solid-support" target="_blank"><b>' . __( 'Support', 'it-l10n-backupbuddy' ) . '</b></a>
				</p>',
		)
	);
} // End pb_backupbuddy_contextual_help().


/***** BEGIN STASH LIVE ADMIN BAR *****/
function backupbuddy_live_admin_bar_menu( $wp_admin_bar ) {
	if ( ! current_user_can( pb_backupbuddy::$options['role_access'] ) || '1' === pb_backupbuddy::$options['hide_live'] ) {
		return;
	}

	$args = array(
		'id'    => 'backupbuddy_stash_live_admin_bar',
		'title' => 'Solid Backups Stash Live',
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
 * Global admin javascript files.
 */
function backupbuddy_global_admin_scripts() {
	wp_register_script( 'backupbuddy_global_admin_scripts', pb_backupbuddy::plugin_url() . '/assets/dist/js/global-admin.js', array( 'jquery' ), pb_backupbuddy::settings( 'version' ), true );
	wp_register_script( 'backupbuddy-min', pb_backupbuddy::plugin_url() . '/assets/dist/js/backupbuddy.js', array( 'jquery' ), pb_backupbuddy::settings( 'version' ), true );
	wp_register_style( 'backupbuddy-core', pb_backupbuddy::plugin_url() . '/assets/dist/css/solid-backups.css', array(), pb_backupbuddy::settings( 'version' ) );
	wp_register_style( 'solid-jit-icicle', pb_backupbuddy::plugin_url() . '/assets/dist/css/jit-icicle.css', array(), pb_backupbuddy::settings( 'version' ) );
	wp_register_style( 'solid-jquery-smoothness', pb_backupbuddy::plugin_url() . '/assets/dist/css/vendor-styles/jquery-smoothness.css', array(), pb_backupbuddy::settings( 'version' ) );

	if ( ! backupbuddy_is_admin_page() ) {
		return;
	}
	wp_enqueue_script( 'backupbuddy_global_admin_scripts' );
	pb_backupbuddy::load_script( 'backupbuddy-min', false, backupbuddy_js_vars() );
	pb_backupbuddy::load_style( 'backupbuddy-core' );
}

add_action( 'admin_enqueue_scripts', 'backupbuddy_global_admin_scripts' );

/**
 * JS Vars for main Solid Backups script.
 *
 * @return array  Array of JS vars for localizing variables.
 */
function backupbuddy_js_vars() {
	return apply_filters(
		'backupbuddy_js_vars',
		array(
			'ajax_url'             => admin_url( 'admin-ajax.php' ),
			'admin_url'            => is_network_admin() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' ),
			'ajax_base'            => pb_backupbuddy::ajax_url( '' ),
			'strings'              => backupbuddy_admin_get_strings(),
			'hide_quick_setup'     => true === apply_filters( 'itbub_hide_quickwizard', false ),
			'importbuddy_pass_set' => '' == pb_backupbuddy::$options['importbuddy_pass_hash'] ? 0 : 1,
			'page_url'             => pb_backupbuddy::page_url(),
			'destination_ids'      => backupbuddy_admin_get_destination_ids(),
		)
	);
}

/**
 * Get Strings for use in JS.
 *
 * @return array  Array of I18n strings.
 */
function backupbuddy_admin_get_strings() {
	return array(
		'error'                          => esc_html__( 'Error', 'it-l10n-backupbuddy' ),
		'quick_setup'                    => esc_html__( 'Quick Setup', 'it-l10n-backupbuddy' ),
		'importbuddy'                    => esc_html__( 'Standalone Importer', 'it-l10n-backupbuddy' ),
		'importbuddy_download'           => esc_html__( 'Download importbuddy.php', 'it-l10n-backupbuddy' ),
		'importbuddy_send'               => esc_html__( 'Send importbuddy.php to a remote destination', 'it-l10n-backupbuddy' ),
		'sending_importbuddy'            => esc_html__( 'Sending Importer file. This may take several seconds. Please wait ...', 'it-l10n-backupbuddy' ),
		'importbuddy_prompt_nopass'      => esc_html__( 'To download, enter a password to lock the Importer script from unauthorized access. You will be prompted for this password when you go to importbuddy.php in your browser. Since you have not defined a default password yet this will be used as your default and can be changed later from the Settings page.', 'it-l10n-backupbuddy' ),
		'importbuddy_prompt_haspass'     => esc_html__( 'To download, either enter a new password for just this download OR LEAVE BLANK to use your default Importer password (set on the Settings page) to lock the Importer script from unauthorized access.', 'it-l10n-backupbuddy' ),
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
		'local_backups'                  => esc_html__( 'Local Backups', 'it-l10n-backupbuddy' ),
		'remote_backups'                 => esc_html__( 'Remote Backups', 'it-l10n-backupbuddy' ),
		'admin_notices'                  => esc_html__( 'Admin Notices', 'it-l10n-backupbuddy' ),
		'stash_table_header'             => esc_html__( 'Stash Traditional Backup Files', 'it-l10n-backupbuddy' ),
		'backups_table_header'           => esc_html__( 'Backups', 'it-l10n-backupbuddy' ),
	);
}

/**
 * Get array of destination IDs.
 *
 * @return array  Array of destination IDs.
 */
function backupbuddy_admin_get_destination_ids() {
	$destinations = array();
	foreach ( pb_backupbuddy::$options['remote_destinations'] as $destination_id => $destination_settings ) {
		if ( 'live' === $destination_settings['type'] ) {
			continue;
		}
		$destinations[] = $destination_id;
	}

	return $destinations;
}

/**
 * Stash Download Backup.
 */
function backupbuddy_stash_download() {
	if ( ! pb_backupbuddy::_GET( 'stash-download' ) ) {
		return;
	}

	$file        = pb_backupbuddy::_GET( 'stash-download' );
	$destination = pb_backupbuddy::_GET( 'stash-destination-id' );

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

add_action( 'admin_init', 'backupbuddy_stash_download' );


/**
 * SFTP Download Backup.
 */
function backupbuddy_sftp_download() {
	if ( ! pb_backupbuddy::_GET( 'sftp-download' ) ) {
		return;
	}

	$file        = pb_backupbuddy::_GET( 'sftp-download' );
	$destination = pb_backupbuddy::_GET( 'sftp-destination-id' );

	if ( empty( pb_backupbuddy::$options['remote_destinations'][ $destination ] ) ) {
		return false;
	}

	if ( ! class_exists( 'pb_backupbuddy_destination_sftp' ) ) {
		require_once pb_backupbuddy::plugin_path() . '/destinations/sftp/init.php';
	}

	$settings = pb_backupbuddy::$options['remote_destinations'][ $destination ];

	pb_backupbuddy_destination_sftp::stream_download( $settings, $file );
}

add_action( 'admin_init', 'backupbuddy_sftp_download' );


/**
 * FTP Download Backup.
 */
function backupbuddy_ftp_download() {
	if ( ! pb_backupbuddy::_GET( 'ftp-download' ) ) {
		return;
	}

	$file        = pb_backupbuddy::_GET( 'ftp-download' );
	$destination = pb_backupbuddy::_GET( 'ftp-destination-id' );

	if ( empty( pb_backupbuddy::$options['remote_destinations'][ $destination ] ) ) {
		return false;
	}

	if ( ! class_exists( 'pb_backupbuddy_destination_ftp' ) ) {
		require_once pb_backupbuddy::plugin_path() . '/destinations/ftp/init.php';
	}

	$settings = pb_backupbuddy::$options['remote_destinations'][ $destination ];

	pb_backupbuddy_destination_ftp::stream_download( $settings, $file );
}

add_action( 'admin_init', 'backupbuddy_ftp_download' );

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

/**
 * Dropbox OAuth Redirect.
 */
function backupbuddy_dropbox_redirect() {
	if ( ! pb_backupbuddy::_GET( 'dropbox-authorize' ) ) {
		return;
	}

	if ( ! class_exists( 'pb_backupbuddy_destination_dropbox3' ) ) {
		require_once pb_backupbuddy::plugin_path() . '/destinations/dropbox3/init.php';
	}

	pb_backupbuddy_destination_dropbox3::oauth_redirect();
}

add_action( 'admin_init', 'backupbuddy_dropbox_redirect' );

/**
 * Dropbox Force Download.
 */
function backupbuddy_dropbox_download() {
	if ( ! pb_backupbuddy::_GET( 'dropbox-download' ) ) {
		return;
	}

	$file        = pb_backupbuddy::_GET( 'dropbox-download' );
	$destination = pb_backupbuddy::_GET( 'dropbox-destination-id' );

	if ( ! class_exists( 'pb_backupbuddy_destination_dropbox3' ) ) {
		require_once pb_backupbuddy::plugin_path() . '/destinations/dropbox3/init.php';
	}

	$settings = pb_backupbuddy::$options['remote_destinations'][ $destination ];
	pb_backupbuddy_destination_dropbox3::force_download( $settings, $file );
}

add_action( 'admin_init', 'backupbuddy_dropbox_download' );

/**
 * Google Drive (v2) Force Download.
 */
function backupbuddy_gdrive2_download() {
	if ( ! pb_backupbuddy::_GET( 'gdrive2-download' ) ) {
		return;
	}

	$file        = pb_backupbuddy::_GET( 'gdrive2-download' );
	$destination = pb_backupbuddy::_GET( 'gdrive2-destination-id' );

	if ( ! class_exists( 'pb_backupbuddy_destination_gdrive2' ) ) {
		require_once pb_backupbuddy::plugin_path() . '/destinations/gdrive2/init.php';
	}

	$settings = pb_backupbuddy::$options['remote_destinations'][ $destination ];
	pb_backupbuddy_destination_gdrive2::force_download( $settings, $file );
}

add_action( 'admin_init', 'backupbuddy_gdrive2_download' );

/**
 * Local Force Download.
 */
function backupbuddy_local_download() {
	if ( ! pb_backupbuddy::_GET( 'local-download' ) ) {
		return;
	}

	$file        = pb_backupbuddy::_GET( 'local-download' );
	$destination = pb_backupbuddy::_GET( 'local-destination-id' );

	if ( ! class_exists( 'pb_backupbuddy_destination_local' ) ) {
		require_once pb_backupbuddy::plugin_path() . '/destinations/local/init.php';
	}

	if ( isset( pb_backupbuddy::$options['remote_destinations'][ $destination ] ) ) {
		$settings = pb_backupbuddy::$options['remote_destinations'][ $destination ];
		pb_backupbuddy_destination_local::force_download( $settings, $file );
	} else {
		pb_backupbuddy::status( 'error', 'Remote Destination not set' );
	}

}

add_action( 'admin_init', 'backupbuddy_local_download' );

function solid_backups_disable_dat_file_creation( $old, $new ) {
	backupbuddy_data_file()->disable_dat_file_creation( $old, $new );
}
add_action( 'solid_backups_option_update', 'solid_backups_disable_dat_file_creation', 10, 2 );
