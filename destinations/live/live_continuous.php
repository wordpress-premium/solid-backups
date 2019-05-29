<?php
/* BackupBuddy Stash Live Continuous (live activity watching) Class
 *
 * @author Dustin Bolton
 * @since 7.0
 *
 */
class backupbuddy_live_continuous {
	
	private static $_dbqueue = array();
	private static $_dbqueue_rendered = array();
	private static $_queue_sent = false;
	private static $_options_exclusions = array(
		'cron',
		'rewrite_rules',
		'akismet_spam_count',
		'wpsupercache_gc_time',
		'pb_backupbuddy_notifications',
		'vaultpress',
		'/_transient_/',
		'/^_vp_/',
		
		'wdp_un_local_themes',
		'wdp_un_farm133_themes',
		'sbp_page_time',
		'itsec_malware_scheduling_last_scans_0',
		'itsec_malware_scheduling_last_scans',
		'term_split.lock',
		'SWPA_PLUGIN_ENTRIES_LIVE_TRAFFIC',
		'wps_live_traffic_entries',
		'spamshield_count',
		'count_per_day_online',
		'adding_plustags',
		'auto_updater.lock',
		'image_default_link_type',
		'adding_tags',
		'tribe_last_save_post',
		'wdp_un_refresh_local_flag',
		'pmpro_views',
		'uninstall_plugins',
		'wwcAmzAff_sync_last_updated_product',
		'popup_domination_updateinfo',
		'wordfence_syncAttackDataAttempts',
	);
	private static $_postmeta_exclusions = array(
		'pvc_views',
		'_edit_lock',
		'views',
		'option_overall_score',
		'_total_views',
		'tve_leads_impressions',
		'opanda_imperessions',
		'_edit_last',
		'_views_today',
		'_inbound_impressions_count',
		'post_views_count',
		'_publicize_twitter_user',
		'tie_views',
		'_jetpack_related_posts_cache',
		'_thumbnail_id',
		'_pt_cv_view_count',
		'_et_social_shares_twitter',
		'avada_post_views_count',
		'opanda_unlocks',
		'_et_social_shares_googleplus',
		'_et_social_shares_linkedin',
		'_et_social_shares_stumbleupon',
		'_et_social_shares_pinterest',
		'_et_social_shares_blogger',
		'dsq_needs_sync',
		'fsb_social_google',
		'_encloseme',
		'source_cron',
		'/^thisorthat_/',
		'option_overall_score',
		'/^_amzaff_/',
		'_price',
		'_sale_price',
		'_price_update_date',
		'/_impressions/',
		'snapImportedFBComments',
		'/_count-views_.+/', // Adrotate plugin
	);
	
	/* init()
	 *
	 * Registers various hooks to watch for database changes.
	 *
	 */
	public static function init() {
		require_once( pb_backupbuddy::plugin_path() . '/classes/core.php' );
		require_once( pb_backupbuddy::plugin_path() . '/destinations/live/live.php' );
		
		// Make sure we are not DISABLED entirely.
		if ( '1' == backupbuddy_live::getOption( 'pause_continuous' ) ) { // Paused.
			return false;
		}
		
		// Make sure we are not PAUSED.
		$liveID = backupbuddy_live::getLiveID();
		if ( '1' == pb_backupbuddy::$options['remote_destinations'][ $liveID ]['pause_continuous'] ) {
			//pb_backupbuddy::status( 'details', 'Aborting continuous process as it is currently PAUSED based on settings.' );
			return false;
		}
		
		
		// Comments
		add_action( 'delete_comment',        array( 'backupbuddy_live_continuous', 'handle_comment' ) );
		add_action( 'wp_set_comment_status', array( 'backupbuddy_live_continuous', 'handle_comment' ) );
		add_action( 'trashed_comment',       array( 'backupbuddy_live_continuous', 'handle_comment' ) );
		add_action( 'untrashed_comment',     array( 'backupbuddy_live_continuous', 'handle_comment' ) );
		add_action( 'wp_insert_comment',     array( 'backupbuddy_live_continuous', 'handle_comment' ) );
		add_action( 'comment_post',          array( 'backupbuddy_live_continuous', 'handle_comment' ) );
		add_action( 'edit_comment',          array( 'backupbuddy_live_continuous', 'handle_comment' ) );
		
		// Commentmeta
		add_action( 'added_comment_meta',   array( 'backupbuddy_live_continuous', 'handle_commentmeta_insert' ), 10, 2 );
		add_action( 'updated_comment_meta', array( 'backupbuddy_live_continuous', 'handle_commentmeta' ), 10, 4 );
		add_action( 'deleted_comment_meta', array( 'backupbuddy_live_continuous', 'handle_commentmeta' ), 10, 4 );
		
		// Users
		if ( self::_is_main_site() ) {
			add_action( 'user_register',  array( 'backupbuddy_live_continuous', 'handle_user' ) );
			add_action( 'password_reset', array( 'backupbuddy_live_continuous', 'handle_user' ) );
			add_action( 'profile_update', array( 'backupbuddy_live_continuous', 'handle_user' ) );
			add_action( 'user_register',  array( 'backupbuddy_live_continuous', 'handle_user' ) );
			add_action( 'deleted_user',   array( 'backupbuddy_live_continuous', 'handle_user' ) );
		}
		
		// Usermeta
		if ( self::_is_main_site() ) {
			add_action( 'added_usermeta',  array( 'backupbuddy_live_continuous', 'handle_usermeta' ), 10, 4 );
			add_action( 'update_usermeta', array( 'backupbuddy_live_continuous', 'handle_usermeta' ), 10, 4 );
			add_action( 'delete_usermÏeta', array( 'backupbuddy_live_continuous', 'handle_usermeta' ), 10, 4 );
		}
		
		// Posts
		add_action( 'delete_post',              array( 'backupbuddy_live_continuous', 'handle_post' ) );
		add_action( 'trash_post',               array( 'backupbuddy_live_continuous', 'handle_post' ) );
		add_action( 'untrash_post',             array( 'backupbuddy_live_continuous', 'handle_post' ) );
		add_action( 'edit_post',                array( 'backupbuddy_live_continuous', 'handle_post' ) );
		add_action( 'save_post',                array( 'backupbuddy_live_continuous', 'handle_post' ) );
		add_action( 'wp_insert_post',           array( 'backupbuddy_live_continuous', 'handle_post' ) );
		add_action( 'edit_attachment',          array( 'backupbuddy_live_continuous', 'handle_post' ) );
		add_action( 'add_attachment',           array( 'backupbuddy_live_continuous', 'handle_post' ) );
		add_action( 'delete_attachment',        array( 'backupbuddy_live_continuous', 'handle_post' ) );
		add_action( 'private_to_published',     array( 'backupbuddy_live_continuous', 'handle_post' ) );
		add_action( 'wp_restore_post_revision', array( 'backupbuddy_live_continuous', 'handle_post' ) );
		
		// Postmeta
		add_action( 'added_post_meta',   array( 'backupbuddy_live_continuous', 'handle_post_postmeta' ), 10, 4 );
		add_action( 'update_post_meta',  array( 'backupbuddy_live_continuous', 'handle_post_postmeta' ), 10, 4 );
		add_action( 'updated_post_meta', array( 'backupbuddy_live_continuous', 'handle_post_postmeta' ), 10, 4 );
		add_action( 'delete_post_meta',  array( 'backupbuddy_live_continuous', 'handle_post_postmeta' ), 10, 4 );
		add_action( 'deleted_post_meta', array( 'backupbuddy_live_continuous', 'handle_post_postmeta' ), 10, 4 );
		add_action( 'added_postmeta',    array( 'backupbuddy_live_continuous', 'handle_postmeta' ), 10, 3 );
		add_action( 'update_postmeta',   array( 'backupbuddy_live_continuous', 'handle_postmeta' ), 10, 3 );
		add_action( 'delete_postmeta',   array( 'backupbuddy_live_continuous', 'handle_postmeta' ), 10, 3 );
		
		// Links
		add_action( 'edit_link',   array( 'backupbuddy_live_continuous', 'handle_link' ) );
		add_action( 'add_link',    array( 'backupbuddy_live_continuous', 'handle_link' ) );
		add_action( 'delete_link', array( 'backupbuddy_live_continuous', 'handle_link' ) );
		
		// Taxonomy
		add_action( 'created_term',              array( 'backupbuddy_live_continuous', 'handle_term' ), 2 );
		add_action( 'edited_terms',              array( 'backupbuddy_live_continuous', 'handle_term' ), 2 );
		add_action( 'delete_term',               array( 'backupbuddy_live_continuous', 'handle_term' ), 2 );
		add_action( 'edit_term_taxonomy',        array( 'backupbuddy_live_continuous', 'handle_term_taxonomy' ) );
		add_action( 'delete_term_taxonomy',      array( 'backupbuddy_live_continuous', 'handle_term_taxonomy' ) );
		add_action( 'edit_term_taxonomies',      array( 'backupbuddy_live_continuous', 'handle_term_taxonomy' ) );
		add_action( 'add_term_relationship',     array( 'backupbuddy_live_continuous', 'handle_term_relationship' ), 10, 2 );
		add_action( 'delete_term_relationships', array( 'backupbuddy_live_continuous', 'handle_term_relationship' ), 10, 2 );
		add_action( 'set_object_terms',          array( 'backupbuddy_live_continuous', 'handle_set_object_terms' ), 10, 3 );
		
		// Files
		if ( self::_is_main_site() ) {
			add_action( 'switch_theme',      array( 'backupbuddy_live_continuous', 'handle_theme' ) );
			add_action( 'activate_plugin',   array( 'backupbuddy_live_continuous', 'handle_plugin' ) );
			add_action( 'deleted_plugin', array( 'backupbuddy_live_continuous', 'handle_plugin' ) );
		}
		
		// Uploads
		add_action( 'wp_handle_upload',  array( 'backupbuddy_live_continuous', 'handle_upload' ) );
		
		// Media deletion.
		add_action( 'delete_attachment', array( 'backupbuddy_live_continuous', 'handle_delete_attachment' ) );
		
		// Options
		add_action( 'deleted_option', array( 'backupbuddy_live_continuous', 'handle_option' ), 1 );
		add_action( 'updated_option', array( 'backupbuddy_live_continuous', 'handle_option' ), 1 );
		add_action( 'added_option',   array( 'backupbuddy_live_continuous', 'handle_option' ), 1 );
		
		// Core LIVE functionality.
		add_action( 'shutdown', array( 'backupbuddy_live_continuous', 'run_live_send' ) ); // On shutdown send data to API.
		add_filter( 'pre_update_option_active_plugins', array( 'backupbuddy_live_continuous', 'load_bb_first' ) ); // Load this plugin first.
		
	} // End init().
	
	
	
	// Load BackupBuddy first.
	public static function load_bb_first( $load ) {
		$load = array_unique( $load ); // Remove dupes.
		return array_merge(
			preg_grep( '/backupbuddy\.php$/', $load ),
			preg_grep( '/backupbuddy\.php$/', $load, PREG_GREP_INVERT )
		);
	}
	
	
	
	// Handle comment changes.
	public static function handle_comment( $comments ) {
		if ( ! is_array( $comments ) ) {
			$comments = array( $comments );
		}
		foreach ( $comments as $comment_id ) {
			if ( wp_get_comment_status( $comment_id ) != 'spam' ) {
				self::dbqueue( 'comments', 'comment_ID', $comment_id );
				self::dbqueue( 'commentmeta', 'comment_id', $comment_id );
			}
		}
	}
	
	
	
	// Handle user changes.
	public static function handle_user( $user_or_id ) {
		if ( is_object( $user_or_id ) )
			$user_id = intval( $user_or_id->ID );
		else
			$user_id = intval( $user_or_id );
		if ( ! $user_id ) {
			return;
		}
		self::dbqueue( 'users', 'ID', $user_id );
		self::dbqueue( 'usermeta', 'user_id', $user_id );
	}
	
	
	
	// Handle user meta changes.
	public static function handle_usermeta( $umeta_id, $user_id, $meta_key, $meta_value='' ) {
		self::dbqueue( 'usermeta', 'umeta_id', $umeta_id );
	}
	
	
	
	// Handle post changes.
	public static function handle_post( $post_id ) {
		self::dbqueue( 'posts', 'ID', $post_id );
		self::dbqueue( 'postmeta', 'post_id', $post_id );
	}
	
	
	
	// Handle link changes.
	public static function handle_link( $link_id ) {
		self::dbqueue( 'links', 'link_id', $link_id );
	}
	
	
	
	// Handle commentmeta changes except adding.
	public static function handle_commentmeta( $meta_ids, $object_id, $meta_key, $meta_value ) {
		if ( ! is_array( $meta_ids ) ) {
			$meta_ids = array( $meta_ids );
		}
		foreach ( $meta_ids as $meta_id ) {
			self::dbqueue( 'commentmeta', 'meta_id', $meta_id );
		}
	}
	
	
	
	// Handle commentmeta addition.
	public static function handle_commentmeta_insert( $meta_id, $comment_id=null ) {
		if ( empty( $comment_id ) || wp_get_comment_status( $comment_id ) != 'spam' ) {
			self::dbqueue( 'commentmeta', 'meta_id', $meta_id );
		}
	}
	
	
	
	// Handle postmeta for a post changes.
	public static function handle_post_postmeta( $meta_ids, $object_id, $meta_key, $meta_value ) {
		self::handle_postmeta( $meta_ids, $object_id, $meta_key );
	}
	
	
	
	// Handle postmeta changes.
	public static function handle_postmeta( $meta_ids, $post_id = null, $meta_key = null ) {
		$excludes = array_merge( self::$_postmeta_exclusions, (array)backupbuddy_live::getOption( 'postmeta_key_excludes', true ) );
		foreach( $excludes as $exclude ) {
			if ( $exclude{0} == '/' ) {
				if ( preg_match( $exclude, $meta_key ) ) { // Excluding this key.
					return;
				}
			} else {
				if ( $meta_key == $exclude ) {
					return;
				}
			}
		}
		
		if ( in_array( $meta_key, $excludes ) ) {
			return;	
		}
		if ( ! is_array( $meta_ids ) ) {
			$meta_ids = array( $meta_ids );
		}
		foreach ( $meta_ids as $meta_id ) {
			self::dbqueue( 'postmeta', 'meta_id', $meta_id );
		}
	}
	
	
	
	// Handle term changes.
	public static function handle_term( $term_id, $tt_id = null ) {
		self::dbqueue( 'terms', 'term_id', $term_id );
		if ( $tt_id ) {
			self::handle_term_taxonomy( $tt_id );
		}
	}
	
	
	
	// Handle term taxonomy changes.
	public static function handle_term_taxonomy( $tt_ids ) {
		if ( ! is_array( $tt_ids ) ) {
			$tt_ids = array( $tt_ids );
		}
		foreach( $tt_ids as $tt_id ) {
			self::dbqueue( 'term_taxonomy', 'term_taxonomy_id', $tt_id );
		}
	}
	
	
	
	// Handle term relationship changes.
	public static function handle_term_relationship( $object_ids, $term_id ) {
		if ( ! is_array( $object_ids ) ) {
			$object_ids = array( $object_ids );
		}
		foreach( $object_ids as $object_id ) {
			self::dbqueue( 'term_relationships', 'object_id', $object_id );
		}
	}
	
	
	
	public static function handle_set_object_terms( $object_id, $terms, $tt_ids ) {
		self::handle_term_relationship( $object_id, $tt_ids );
	}
	
	
	
	// Handle option changes.
	public static function handle_option( $option_name ) {
		$ignores = backupbuddy_live::getOption( 'options_excludes', true );
		$ignores = array_merge( self::$_options_exclusions, $ignores ); // Add hard-coded exclusions.
		foreach( $ignores as $ignore ) {
			if ( $ignore{0} == '/' ) {
				if ( preg_match( $ignore, $option_name ) ) { // Excluding this option.
					return $option_name;
				}
			} else {
				if ( $option_name == $ignore ) {
					//error_log( 'exclude: ' . $ignore );
					return $option_name;
				}
			}
		}
		if ( '' != $option_name ) {
			self::dbqueue( 'options', 'option_name', $option_name );
		}
		return $option_name;
	}
	
	
	
	// Handle upload of a file. NOT database portion here.
	public static function handle_upload( $file ) {
		require_once( 'live.php' );
		
		if ( isset( $file['file'] ) ) {
			$directory = dirname( $file['file'] );
			backupbuddy_live::queue_manual_file_scan( $directory );
		}
		
		return $file;
	}
	
	
	
	// Handle deletion of an attachment (eg media file).
	public static function handle_delete_attachment( $post_id ) {
		$file = get_attached_file( $post_id );
		if ( ( false !== $file ) && ( '' != $file ) ) {
			$directory = dirname( $file );
			backupbuddy_live::queue_manual_file_scan( $directory );
		}
		
		return $post_id;
	}
	
	
	
	// Handle theme being activated (maybe new files or not).
	public static function handle_theme( $theme ) {
		require_once( 'live.php' );
		
		backupbuddy_live::queue_manual_file_scan( get_template_directory() );
		
		return $theme;
	}
	
	
	
	// Handle plugin being activated (maybe new files or not).
	public static function handle_plugin( $plugin ) {
		require_once( 'live.php' );
		
		$directory = dirname( WP_PLUGIN_DIR . '/' . $plugin );
		backupbuddy_live::queue_manual_file_scan( $directory );
		
		return $plugin;
	}
	
	
	
	/* dbqueue()
	 *
	 * Queues a change to a table, identified by column, including the micro timestamp it occurred so we can play back in order.
	 * Subsequent call to _process_dbqueue() and _send_dbqueue() to occur later.
	 *
	 */
	public static function dbqueue( $table_no_prefix, $column, $value, $timestamp = '' ) {
		if ( '' == $timestamp ) {
			$timestamp = microtime(true);
		}
		self::$_dbqueue[] = array(
			't' => $table_no_prefix, // Table name (without prefix).
			'c' => $column, // Column identifier.
			'v' => $value, // New Value (if applicable).
			'w' => $timestamp // Timestamp (when).
		);
	}
	
	
	
	/* _process_dbqueue()
	 *
	 * Processes queued db actions (via dbqueue()), generating SQL from it to later be sent via _send_dbqueue().
	 *
	 */
	public static function _process_dbqueue() {
		global $wpdb;
		$count = 0;
		
		// Render SQL.
		$last_event_hash = ''; // Used for preventing multiple of the same SQL in a row. Needless overhead.
		foreach( self::$_dbqueue as $event ) {
			$count++;
			$prefixed_table = $wpdb->prefix . $event['t'];
			
			// Make sure we do not send multiple of the same SQL queries in a row, needlessly adding overhead.
			$this_event_hash = md5( $event['t'] . $event['c'] . $event['v'] );
			if ( $last_event_hash == $this_event_hash ) { // Duplicate event in a row. Skip sending. Note: Does NOT filter duplicates not in a row so we can roll back actions in a proper order.
				continue; // Skip duplicate.
			}
			$last_event_hash = $this_event_hash;
			
			$encoded_sql = base64_encode( gzcompress( self::_render_insert( $event['t'], $event['c'], $event['v'] ) ) );
			self::$_dbqueue_rendered[ 'wp-content/uploads/backupbuddy_temp/SERIAL/_' . $event['w'] . '-' . $prefixed_table . '.sql' ] = $encoded_sql; // Save generated SQL for later sending. Filename includes timestamp.
		}
		self::$_dbqueue = array(); // Clear out dbqueue.
		return $count;
	}
	
	
	
	/* _send_dbqueue()
	 *
	 * Does the actual sending of encoded SQL data to the Live servers for storage as a timestamped SQL file. Called via send_data() on shutdown hook.
	 *
	 */
	public static function _send_dbqueue() {
		$settings = pb_backupbuddy::$options['remote_destinations'][ backupbuddy_live::getLiveID() ];
		if ( ! isset( $settings['destination_version'] ) ) {
			$settings['destination_version'] = '2';
		}
		
		require_once( pb_backupbuddy::plugin_path() . '/destinations/stash' . $settings['destination_version'] . '/init.php' );
		$response = call_user_func_array( array( 'pb_backupbuddy_destination_stash' . $settings['destination_version'], 'stashAPI' ), array( $settings, 'live-put', array( 'files' => self::$_dbqueue_rendered ) ) );
		//error_log( 'Live db send response:' );
		//error_log( print_r( $response, true ) );
		
		$errors = array();
		if ( ! is_array( $response ) ) { // Error message.
			$errors[] = 'Error #3279237: Unexpected server response. Check your BackupBuddy Stash Live login and try again. Detailed response: `' . print_r( $response, true ) .'`.';
		} else { // Errors.
			if ( isset( $response['error'] ) ) {
				$errors[] = $response['error']['message'];
			} else { // No error?
				if ( ! isset( $response['success'] ) || ( '1' != $response['success'] ) ) {
					$errors[] = 'Error #9327324: Something went wrong. Success was not reported. Detailed response: `' . print_r( $response, true ) .'`.';
				}
			}
		}
		
		self::$_queue_sent = true; // Prevent potentially sending again if shutdown hook double-fires.
		
		if ( count( $errors ) > 0 ) {
			pb_backupbuddy::status( 'error', 'Error sending live continuous data. Error(s): `' . implode( ', ', $errors ) . '`.' );
			//backupbuddy_core::addNotification( 'live_continuous_error', 'BackupBuddy Stash Live Errors', implode( ', ', $errors ), $errors );
		} else {
			// Update last activity time.
			backupbuddy_live::update_db_live_activity_time();
			if ( pb_backupbuddy::$options['log_level'] == '3' ) { // Full logging enabled.
				pb_backupbuddy::status( 'details', 'Success sending live continuous data to server.' );
			}
		}
		
		return true;
	} // End _send_dbqueue().
	
	
	
	/* send_data()
	 *
	 * Triggered by shutdown hook to send all queued data to the Live API via _send_dbqueue().
	 *
	 */
	public static function run_live_send() {
		if ( true === self::$_queue_sent ) { // Already sent. Shutdown fired again.
			return;
		}
		
		pb_backupbuddy::flush(); // Send any pending data to browser.
		if ( ! isset( pb_backupbuddy::$options['remote_destinations'][ backupbuddy_live::getLiveID() ] ) ) { // Live destination went away. Deleted?
			return;
		}
		
		$count = self::_process_dbqueue();
		if ( 0 == $count ) { // Nothing needs to be sent.
			return;
		}
		
		self::_send_dbqueue();
	}
	
	
	
	/* _render_insert()
	 *
	 * Takes the stored table, column update data, and creates an SQL insert statement.
	 *
	 */
	private static function _render_insert( $table_no_prefix, $column, $value ) {
		global $wpdb;
		$table = $wpdb->prefix . $table_no_prefix;
		
		//error_log( 'Render insert. Table: `' . $table_no_prefix . '`, Column: `' . $column . '`, Value: `' . $value . '`.' );
		
		$query = "SELECT * FROM `$table` WHERE $column = %s";
		$table_query = $wpdb->get_results( $wpdb->prepare( $query, array( $value ) ), ARRAY_N );
		
		if ( $table_query === false ) {
			pb_backupbuddy::status( 'error', 'ERROR #237273. Unable to retrieve data from table `' . $table . '`. This table may be corrupt (try repairing the database) or too large to hold in memory (increase mysql and/or PHP memory). Check your PHP error log for further errors which may provide further information. Not continuing database dump to insure backup integrity.' );
			return false;
		}
		$tableCount = count( $table_query );
		//pb_backupbuddy::status( 'details', 'Got `' . $tableCount . '` rows from `' . $table . '`.' );
		
		$insert_sql = $wpdb->prepare( "DELETE FROM `$table` WHERE $column = %s", array( $value ) ) . ";\n"; // Initially delete everything that matches this query to get ready for insert.
		$columns = $wpdb->get_col_info();
		$num_fields = count( $columns );
		foreach( $table_query as $fetch_row ) {
			$insert_sql .= "INSERT INTO `$table` VALUES(";
			for ( $n=1; $n<=$num_fields; $n++ ) {
				$m = $n - 1;
				
				if ( $fetch_row[$m] === NULL ) {
					$insert_sql .= "NULL, ";
				} else {
					$insert_sql .= "'" . backupbuddy_core::dbEscape( $fetch_row[$m] ) . "', ";
				}
			}
			$insert_sql = substr( $insert_sql, 0, -2 );
			$insert_sql .= ");\n";
		} // end foreach table row.
		
		return $insert_sql;
	} // End _render_insert().
	
	
	
	private static function _is_main_site() {
		if ( ! function_exists( 'is_main_site' ) || ! self::_is_multisite() ) {
			return true;
		} else {
			return is_main_site();
		}
	} // End _is_main_site().
	
	
	
	private static function _is_multisite() {
		if ( function_exists( 'is_multisite' ) )
			return is_multisite();
		return false;
	}
	
	
} // End class backupbuddy_live_continuous.
