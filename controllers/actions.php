<?php
/**
 * Each shortcode method is passed two parameters: $atts (shortcode attributes) and $content (content if shortcode wraps text).
 *
 * Shortcodes should RETURN. Widgets echo.
 *
 * @package BackupBuddy
 */

/**
 * Backup Buddy Actions Class
 */
class pb_backupbuddy_actions extends pb_backupbuddy_actionscore {

	/**
	 * Track actions only once per request.
	 *
	 * @var array
	 */
	private $action_tracked = array();

	/**
	 * Run anything in the admin that may output a notice / error. Runs at proper time to not gunk up HTML.
	 */
	public function admin_notices() {
		backupbuddy_core::verify_directories( true );
	} // End admin_notices().

	/**
	 * Sets up output buffering for reminder to backup before upgrading WordPress.
	 *
	 * @see wp_update_backup_reminder_dump()
	 */
	public function wp_update_backup_reminder() {
		ob_start( array( $this, 'wp_update_backup_reminder_dump' ) );
		add_action( 'admin_footer', array( $this, 'ob_end_flush_suppressed' ) );
	}

	/**
	 * Output buffer dump callback to output actual reminder text.
	 *
	 * @see wp_update_backup_reminder()
	 *
	 * @param string $text  Additional text to display.
	 *
	 * @return string  Text of notice to display.
	 */
	public function wp_update_backup_reminder_dump( $text = '' ) {
		return str_replace(
			'<h2>WordPress Updates</h2>',
			'<h2>' . __( 'WordPress Updates', 'it-l10n-backupbuddy' ) . '</h2><div id="message" class="updated fade"><p><img src="' . pb_backupbuddy::plugin_url() . '/images/pluginbuddy.png" style="vertical-align: -3px;" /> <a href="admin.php?page=pb_backupbuddy_backup" target="_blank" style="text-decoration: none;">' . __( 'Remember to back up your site with BackupBuddy before upgrading!', 'it-l10n-backupbuddy' ) . '</a></p></div>',
			$text
		);
	}

	/**
	 * Calls ob_end_flush, suppressing errors.
	 */
	public function ob_end_flush_suppressed() {
		@ob_end_flush();
	}

	/**
	 * Retrieves the Recent Edits Tracking Mode Option
	 *
	 * @return string  Recent Edits Tracking Mode.
	 */
	public function get_edits_tracking_mode() {
		$mode = 'basic'; // Default to basic.
		if ( ! empty( pb_backupbuddy::$options['edits_tracking_mode'] ) ) {
			$mode = pb_backupbuddy::$options['edits_tracking_mode'];
		}

		return $mode;
	}

	/**
	 * Check to see if we should track this post.
	 *
	 * @param int    $post_id  The post ID.
	 * @param object $post     The post object.
	 *
	 * @return bool  Whether or not to track it this time.
	 */
	public function should_track_post( $post_id, $post ) {
		if ( in_array( $post_id, $this->action_tracked, true ) ) {
			return false;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { // Ignore revisions and autosaves.
			return false;
		}

		$ignored_post_types = apply_filters( 'itbub_edits_ignore_post_types', array(
			'revision',
		) );

		if ( ! is_a( $post, 'WP_Post' ) ) {
			$post = get_post( $post );
		}

		if ( is_a( $post, 'WP_Post' ) ) {
			if ( in_array( $post->post_type, $ignored_post_types, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Increments BackupBuddy option `edits_since_last` by 1 on save_post.
	 *
	 * @param int    $post_id  The Post ID.
	 * @param object $post     The Post Object.
	 * @param bool   $updated  If updated.
	 */
	public function save_post_iterate_edits_since_last( $post_id, $post, $updated = false ) {
		if ( false === $this->should_track_post( $post_id, $post ) ) {
			return;
		}

		$action = 'save_post';
		if ( false === $updated ) {
			$action = 'insert_post';
		}
		if ( is_a( $post, 'WP_Post' ) ) {
			if ( 'trash' === $post->post_status ) {
				$action = 'trash_post';
			}
		}

		$this->action_tracked[] = $post_id;
		pb_backupbuddy::track_edit( $action, $post );
	}

	/**
	 * Increments BackupBuddy option `edits_since_last` by 1 on post_updated.
	 *
	 * @param int    $post_id      The Post ID.
	 * @param object $post_after   The Post Object.
	 * @param object $post_before  If updated.
	 */
	public function post_updated_iterate_edits_since_last( $post_id, $post_after, $post_before ) {
		$post = get_post( $post_id );
		if ( false === $this->should_track_post( $post_id, $post ) ) {
			return;
		}

		$action = 'post_updated';

		if ( is_a( $post, 'WP_Post' ) ) {
			if ( 'trash' === $post->post_status ) {
				$action = 'trash_post';
			}
		}

		$this->action_tracked[] = $post_id;
		pb_backupbuddy::track_edit( $action, $post );
	}

	/**
	 * Increments BackupBuddy option `edits_since_last` by 1 on wp_insert_post.
	 *
	 * @param int    $post_id  The Post ID.
	 * @param object $post     The Post Object.
	 * @param bool   $updated  If updated.
	 */
	public function insert_post_iterate_edits_since_last( $post_id, $post, $updated = false ) {
		if ( false === $this->should_track_post( $post_id, $post ) ) {
			return;
		}

		$this->action_tracked[] = $post_id;
		pb_backupbuddy::track_edit( 'insert_post', $post );
	}

	/**
	 * Increments BackupBuddy option `edits_since_last` by 1 on wp_trash_post.
	 *
	 * @param int $post_id  The Post ID.
	 */
	public function trash_post_iterate_edits_since_last( $post_id ) {
		$post = get_post( $post_id );
		if ( false === $this->should_track_post( $post_id, $post ) ) {
			return;
		}

		if ( ! is_a( $post, 'WP_Post' ) ) {
			$post = $post_id;
		}

		$this->action_tracked[] = $post_id;
		pb_backupbuddy::track_edit( 'trash_post', $post );
	}

	/**
	 * Track changes to plugins when updated.
	 *
	 * @param object $upgrader_object  WP_Upgrader object.
	 * @param array  $options          Upgrader options array.
	 */
	public function update_plugin_iterate_edits_since_last( $upgrader_object, $options ) {
		if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			if ( ! isset( $options['plugins'] ) || ! is_array( $options['plugins'] ) ) {
				return;
			}
			foreach ( $options['plugins'] as $plugin ) {
				if ( 'backupbuddy/backupbuddy.php' === $plugin ) { // TODO: Set to constant from `uninstall-cleanup` branch.
					continue;
				}
				$track = array(
					'plugin' => $plugin,
				);
				pb_backupbuddy::track_edit( 'update_plugin', $track );
			}
		}
	}

	/**
	 * Track plugin activations.
	 *
	 * @param string $plugin        Plugin slug.
	 * @param bool   $network_wide  Activation occurred network-wide.
	 */
	public function activate_plugin_iterate_edits_since_last( $plugin, $network_wide ) {
		if ( 'backupbuddy/backupbuddy.php' === $plugin ) { // TODO: Set to constant from `uninstall-cleanup` branch.
			return;
		}
		$track = array(
			'plugin' => $plugin,
		);
		pb_backupbuddy::track_edit( 'activate_plugin', $track );
	}

	/**
	 * Track plugin deactivations.
	 *
	 * @param string $plugin        Plugin slug.
	 * @param bool   $network_wide  Activation occurred network-wide.
	 */
	public function deactivate_plugin_iterate_edits_since_last( $plugin, $network_wide ) {
		if ( 'backupbuddy/backupbuddy.php' === $plugin ) { // TODO: Set to constant from `uninstall-cleanup` branch.
			return;
		}
		$track = array(
			'plugin' => $plugin,
		);
		pb_backupbuddy::track_edit( 'deactivate_plugin', $track );
	}

	/**
	 * Check to see if we should track this option.
	 *
	 * @param string $option  The option key.
	 *
	 * @return bool  Whether or not to track it this time.
	 */
	public function should_track_option( $option ) {
		if ( $this->get_edits_tracking_mode() !== 'advanced' ) {
			return false;
		}

		if ( in_array( 'option_' . $option, $this->action_tracked, true ) ) {
			return false;
		}

		if ( false !== strpos( $option, 'transient' ) ) { // disregard transient changes.
			return false;
		}

		$ignored_options = apply_filters( 'itbub_edits_ignore_options', array(
			// Misc/WordPress.
			'cron',
			'rewrite_rules',
			'auto_updater.lock',
			'stats_cache',

			// BUB & iThemes.
			'pb_backupbuddy',
			'pb_backupbuddy_notifications',
			'ithemes-updater-cache',
			'itsec-storage',
			'itsec_cron',

			// Jetpack.
			'jetpack_next_sync_time_full-sync-enqueue',
			'jetpack_updates_sync_checksum',
			'jetpack_log',
			'jetpack-sitemap-state',

			// Ninja Forms.
			'ninja_forms_mailchimp_interests',

			// Stop Spammers.
			'ss_stop_sp_reg_stats',

			// W3 Total Cache.
			'w3tc_extensions_hooks',
			'w3tc_stats_hotspot_start',
			'w3tc_stats_history',

			// WooCommerce.
			'wc_connect_services_last_update',
			'wc_connect_last_heartbeat',

			// WordFence.
			'wordfence_syncingAttackData',
			'wordfence_syncAttackDataAttempts',
			'wordfence_lastSyncAttackData',

			// Yoast.
			'wpseo_sitemap_1_cache_validator',
			'wpseo_sitemap_jp_sitemap_cache_validator',
			'wpseo_sitemap_jp_sitemap_master_cache_validator',
		) );

		if ( in_array( $option, $ignored_options, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Increments BackupBuddy option `edits_since_last` by 1 on update_option.
	 *
	 * @param string $option     Option name.
	 * @param mixed  $old_value  Old option value.
	 * @param mixed  $new_value  New option value.
	 */
	public function update_option_iterate_edits_since_last( $option, $old_value, $new_value ) {
		if ( false === $this->should_track_option( $option ) ) {
			return;
		}

		// Return array of various details.
		$option_array = array(
			'option' => $option,
		);

		$this->action_tracked[] = 'option_' . $option;
		pb_backupbuddy::track_edit( 'update_option', $option_array );
	}

	/**
	 * Increments BackupBuddy option `edits_since_last` by 1 on delete_option.
	 *
	 * @param string $option     Option name.
	 */
	public function delete_option_iterate_edits_since_last( $option ) {
		if ( false === $this->should_track_option( $option ) ) {
			return;
		}

		// Return array of various details.
		$option_array = array(
			'option' => $option,
		);

		$this->action_tracked[] = 'option_' . $option;
		pb_backupbuddy::track_edit( 'delete_option', $option_array );
	}

	/**
	 * Enable Advanced Dashboard user for current user when setting changes to Advanced.
	 *
	 * @param string       $option_name       Name of option.
	 * @param string|array $option_value      New Value of option.
	 * @param string|array $option_old_value  Old Value of option.
	 */
	public function enable_advanced_dashboard_widget( $option_name, $option_value, $option_old_value ) {
		if ( 'edits_tracking_mode' !== $option_name ) {
			return;
		}
		if ( ( empty( $option_old_value ) || 'basic' === $option_old_value ) && 'advanced' === $option_value ) {
			$user = get_current_user_id();
			update_user_meta( $user, 'backupbuddy_dashboard_widget_mode', 'advanced' );
		}
	}

	/**
	 * On post / page save injects an additional reminder to remember to back the site up if reminders are enabled.
	 *
	 * @param array $messages  Array of messages to be displayed.
	 *
	 * @return array  Returns modified array (or original if this is not the message to edit).
	 */
	public function content_editor_backup_reminder_on_update( $messages ) {
		if ( ! isset( $messages['post'] ) ) { // Fixes conflict with Simpler CSS plugin. Issue #226.
			return $messages;
		}

		$admin_url = '';

		// Only show the backup message for network admins or adminstrators.
		if ( is_multisite() && current_user_can( 'manage_network' ) ) { // Network Admin in multisite. Don't show messages in this case.
			return $messages;
		} elseif ( ! is_multisite() && current_user_can( pb_backupbuddy::$options['role_access'] ) ) { // User with access in standalone.
			$admin_url = admin_url( 'admin.php' );
		} else {
			return $messages;
		}

		$fullbackup = esc_url(
			add_query_arg(
				array(
					'page'               => 'pb_backupbuddy_backup',
					'backupbuddy_backup' => '2',
				),
				$admin_url
			)
		);

		$dbbackup = esc_url(
			add_query_arg(
				array(
					'page'               => 'pb_backupbuddy_backup',
					'backupbuddy_backup' => '1',
				),
				$admin_url
			)
		);

		$backup_message = " | <a href='{$dbbackup}'>" . __( 'Database Backup', 'it-l10n-backupbuddy' ) . "</a> | <a href='{$fullbackup}'>" . __( 'Full Backup', 'it-l10n-backupbuddy' ) . '</a>';

		$reminder_posts = array(); // empty array to store customized post messages array.
		$reminder_pages = array(); // empty array to store customized page messages array.
		$others         = array(); // An empty array to store the array for custom post types.

		foreach ( $messages['post'] as $index => $message ) {
			if ( 0 === $index ) {
				$message = ''; // The first element in the messages['post'] array is always empty.
			} else {
				$message .= $backup_message;
			}
			array_push( $reminder_posts, $message ); // Insert/copy the modified message value to the last element of reminder array.
		}
		$reminder_posts = array( 'post' => $reminder_posts ); // Apply the post key to the first dimension of messages array.

		foreach ( $messages['page'] as $index => $message ) {
			if ( 0 === $index ) {
				$message = ''; // The first element in the messages['page'] array is always empty.
			} else {
				$message .= $backup_message;
			}
			array_push( $reminder_pages, $message ); // Insert/copy the modified message value to the last element of reminder array.
		}
		$reminder_pages  = array( 'page' => $reminder_pages ); // Apply the page key to the first dimension of messages array.
		$reminder        = array_merge( $reminder_posts, $reminder_pages );
		$post_page_types = array(
			'post',
			'page',
		);
		foreach ( $messages as $type => $message ) {
			if ( in_array( $type, $post_page_types, true ) ) { // Skip the post key since it is already defined.
				continue;
			}
			$others[ $type ] = $message; // Since message is an array, this statement forms 2D array.
		}
		$reminder = array_merge( $reminder, $others ); // Merge the arrays in the others array with reminder array in order to form an appropriate format for messages array.

		return $reminder;
	}

	/**
	 * If BackupBuddy is detected to be running on Multisite but not Network Activated this warning is displayed as a reminder.
	 *
	 * @todo Only show this on BackupBuddy pages AND plugins.php?
	 */
	public function multisite_network_warning() {

		$message = 'BackupBuddy Multisite support is experimental beta software and is not officially supported in a Multisite setting.';

		if ( ! backupbuddy_core::is_network_activated() ) {
			$message .= ' You must <a href="' . esc_url( admin_url( 'network/plugins.php' ) ) . '">Network Activate</a> BackupBuddy to use it with Multisite (not activate within subsites nor the main site).';
		}

		if ( ! defined( 'PB_BACKUPBUDDY_MULTISITE_EXPERIMENT' ) || true != PB_BACKUPBUDDY_MULTISITE_EXPERIMENT ) {
			$message .= ' You must add the following line to your wp-config.php to activate Multisite experimental functionality: <b>define( \'PB_BACKUPBUDDY_MULTISITE_EXPERIMENT\', true );</b>';
		}

		pb_backupbuddy::alert( $message, true );
	}

}
