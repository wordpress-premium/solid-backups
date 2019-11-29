<?php
/**
 * Backup retrieval and listing.
 *
 * @package BackupBuddy
 */

/**
 * Show backups tables and list backups.
 */
class BackupBuddy_Backups {

	/**
	 * Stores single instance of this object.
	 *
	 * @var object
	 */
	private static $instance;

	/**
	 * Array of backups.
	 *
	 * @var array
	 */
	private $backups;

	/**
	 * Maximum backups to display per page.
	 *
	 * @var int
	 */
	private $max_per_page = 20;

	/**
	 * Backup listing mode.
	 *
	 * @var string
	 */
	private $mode = 'default';

	/**
	 * Holds backup Destination ID.
	 *
	 * @var string
	 */
	private $destination_id = '';

	/**
	 * Class Constructor.
	 */
	public function __construct() {
		return $this;
	}

	/**
	 * Instance generator.
	 *
	 * @return object  BackupBuddy_Backups instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new BackupBuddy_Backups();
		}
		return self::$instance;
	}

	/**
	 * Get table hover actions.
	 *
	 * @return array  Array of hover actions.
	 */
	private function get_hover_actions() {
		$hover_actions = array();

		if ( 'legacy' !== $this->mode ) {
			return apply_filters( 'backupbuddy_backup_hover_actions', $hover_actions, $this );
		}

		// If download URL is within site root then allow downloading via web.
		$backup_directory = backupbuddy_core::getBackupDirectory(); // Normalize for Windows paths.
		$backup_directory = str_replace( '\\', '/', $backup_directory );
		$backup_directory = rtrim( $backup_directory, '/\\' ) . '/'; // Enforce single trailing slash.

		$hover_actions[ pb_backupbuddy::ajax_url( 'download_archive' ) . '&backupbuddy_backup=' ] = __( 'Download', 'it-l10n-backupbuddy' );
		$hover_actions['send']       = __( 'Send', 'it-l10n-backupbuddy' );
		$hover_actions['note']       = __( 'Note', 'it-l10n-backupbuddy' );
		$hover_actions['zip_viewer'] = __( 'Browse & Restore Files', 'it-l10n-backupbuddy' );
		$hover_actions['rollback']   = __( 'Database Rollback', 'it-l10n-backupbuddy' );

		add_filter( 'backupbuddy_list_table_hover_actions', array( $this, 'use_dat_viewer' ), 10, 3 );

		return apply_filters( 'backupbuddy_backup_hover_actions', $hover_actions, $this );
	}

	/**
	 * For local backups, use dat Browse & Restore.
	 *
	 * @param array  $hover_actions  Array of hover actions.
	 * @param string $item_id        Table row item id.
	 * @param array  $item           Table row contents array.
	 *
	 * @return array  Modified hover actions.
	 */
	public function use_dat_viewer( $hover_actions, $item_id, $item ) {
		// Skip items that are backup tables.
		if ( ! is_string( $item_id ) || empty( $hover_actions['zip_viewer'] ) ) {
			return $hover_actions;
		}

		$backups_directory = backupbuddy_core::getBackupDirectory(); // Normalize for Windows paths.
		$backups_directory = str_replace( '\\', '/', $backups_directory );
		$backups_directory = rtrim( $backups_directory, '/\\' ) . '/'; // Enforce single trailing slash.

		$path_to_zip = $backups_directory . $item_id;

		if ( backupbuddy_data_file()->locate( $path_to_zip ) ) {
			$new_hover_actions = array();
			foreach ( $hover_actions as $key => $val ) {
				if ( 'zip_viewer' === $key ) {
					$new_hover_actions['dat_viewer'] = $val;
				} else {
					$new_hover_actions[ $key ] = $val;
				}
			}
			return $new_hover_actions;
		}

		return $hover_actions;
	}

	/**
	 * Get table bulk actions.
	 *
	 * @return array  Array of bulk actions.
	 */
	private function get_bulk_actions() {
		$bulk_actions = array();

		if ( 'restore' === $this->mode ) {
			return apply_filters( 'backupbuddy_backup_bulk_actions', $bulk_actions, $this );
		}

		$bulk_actions['delete_backup'] = __( 'Delete', 'it-l10n-backupbuddy' );

		return apply_filters( 'backupbuddy_backup_bulk_actions', $bulk_actions, $this );
	}

	/**
	 * Get table columns.
	 *
	 * @return array  Array of table columns.
	 */
	public function get_columns() {
		$columns = array(
			__( 'Backups', 'it-l10n-backupbuddy' ),
			__( 'Profile', 'it-l10n-backupbuddy' ),
			__( 'File Size', 'it-l10n-backupbuddy' ),
		);

		if ( 'restore' === $this->mode ) {
			$columns[] = __( 'Details', 'it-l10n-backupbuddy' );
		}

		$columns[] = 'restore' === $this->mode ? __( 'Restore', 'it-l10n-backupbuddy' ) : __( 'Actions', 'it-l10n-backupbuddy' );

		return apply_filters( 'backupbuddy_backup_columns', $columns, $this );
	}

	/**
	 * List Backups in UI Table.
	 *
	 * @param string $mode      Display mode.
	 * @param array  $backups   Array of backups.
	 * @param array  $settings  UI Table settings.
	 */
	public function table( $mode = 'default', $backups = false, $settings = array() ) {
		$this->mode = $mode;

		if ( false === $backups ) {
			$args = array(
				'mode' => $this->mode,
			);
			if ( 'restore' === $this->mode ) {
				$args['include_types'] = array( 'full', 'themes', 'plugins', 'db', 'media' );
			}
			$backups = $this->get_backups( $args );
		}

		if ( ! is_array( $backups ) ) {
			return;
		}

		$this->backups = $backups;
		$total_backups = count( $this->backups );

		if ( $total_backups <= 0 ) {
			if ( isset( $settings['destination_id'] ) ) {
				printf( '<p><strong>%s</strong></p>', esc_html__( 'No backups were found at this destination.', 'it-l10n-backupbuddy' ) );
			} else {
				printf( '<p>%s</p>', esc_html__( 'No backups have been created yet.', 'it-l10n-backupbuddy' ) );
			}
			return;
		}

		if ( isset( $settings['destination_id'] ) ) {
			$this->set_destination_id( $settings['destination_id'] );
		}

		/*if ( empty( $settings['disable_pagination'] ) ) {
			if ( $total_backups > $this->max_per_page ) {
				$current_page = pb_backupbuddy::_GET( 'backup_page' ) ? pb_backupbuddy::_GET( 'backup_page' ) : 1;
				$offset       = $this->max_per_page * ( $current_page - 1 );
				$backups      = array_slice( $backups, $offset, $this->max_per_page );
			}
		}*/

		add_filter( 'backupbuddy_list_table_first_column_content', array( $this, 'format_backup_title' ), 10, 5 );

		require pb_backupbuddy::plugin_path() . '/views/backups/listing.php';
	}

	/**
	 * If pagination is needed for the backup listings.
	 *
	 * @return bool  If pagination is needed.
	 */
	public function has_pagination() {
		return false; // Disables pagination.
		$backup_count = count( $this->backups );
		if ( $backup_count <= $this->max_per_page ) {
			return false;
		}
		return true;
	}

	/**
	 * Output backups pagination if needed.
	 */
	public function pagination() {
		return '';
		$backup_count = count( $this->backups );
		if ( $backup_count <= $this->max_per_page ) {
			return;
		}

		$current_page = pb_backupbuddy::_GET( 'backup_page' ) ? (int) pb_backupbuddy::_GET( 'backup_page' ) : 1;
		$total_pages  = (int) ceil( $backup_count / $this->max_per_page );

		if ( '' !== $this->get_destination_id() ) {
			$pagination_url = pb_backupbuddy::ajax_url( 'remoteClient' ) . '&destination_id=' . $this->get_destination_id() . '&backup_page=%s';
		} else {
			$pagination_url = admin_url( 'admin.php?page=pb_backupbuddy_backup&backup_page=%s' );
		}

		require pb_backupbuddy::plugin_path() . '/views/backups/pagination.php';
	}

	/**
	 * Get array of backups.
	 *
	 * @param array $args  Arguments to adjust list of backups.
	 *
	 * @return array  Array of backups.
	 */
	public function get_backups( $args = array() ) {
		$backups           = array();
		$backup_sort_dates = array();

		$default_args = array(
			'mode'          => 'default',
			'subsite_mode'  => false, // When in subsite mode only backups for that specific subsite will be listed.
			'include_types' => array(), // Only return backup types in passed array.
		);

		$args = apply_filters( 'backupbuddy_backups_args', array_merge( $default_args, $args ), $this );

		/*
		$cached = $this->get_cached( $args );
		if ( false !== $cached ) {
			return $cached;
		}*/

		$backups = glob( backupbuddy_core::getBackupDirectory() . 'backup*.zip' );
		if ( ! is_array( $backups ) ) {
			$backups = array();
		}

		$snapshots = glob( backupbuddy_core::getBackupDirectory() . 'snapshot*.zip' );
		if ( ! is_array( $snapshots ) ) {
			$snapshots = array();
		}

		$all_backups = array_merge( $backups, $snapshots );

		if ( is_array( $all_backups ) && ! empty( $all_backups ) ) { // For robustness. Without open_basedir the glob() function returns an empty array for no match. With open_basedir in effect the glob() function returns a boolean false for no match.

			$backup_prefix = backupbuddy_core::backup_prefix(); // Backup prefix for this site. Used for MS checking that this user can see this backup.

			foreach ( $all_backups as $file_id => $file ) {
				if ( true === $args['subsite_mode'] && is_multisite() ) { // If a Network and NOT the superadmin must make sure they can only see the specific subsite backups for security purposes.

					// Only allow viewing of their own backups.
					if ( ! strstr( $file, $backup_prefix ) ) {
						unset( $all_backups[ $file_id ] ); // Remove this backup from the list. This user does not have access to it.
						continue; // Skip processing to next file.
					}
				}

				$integrity_data = $this->get_integrity_data( $file, $args );

				if ( false === $integrity_data ) {
					continue;
				}

				$backup_date = backupbuddy_core::parse_file( $file, 'timestamp' );

				$backup_row = array(
					array(
						$file,
						$backup_date,
						$integrity_data['comment'],
					),
					$integrity_data['detected_type'],
					pb_backupbuddy::$format->file_size( $integrity_data['file_size'] ),
				);

				if ( 'legacy' === $this->mode ) {
					$backup_row[] = $integrity_data['output'];
				} elseif ( 'default' === $this->mode ) {
					$backup_row[] = $this->get_action_menu( $file );
				} elseif ( 'restore' === $this->mode ) {
					$backup_row[] = $this->get_details_link( $file );
					$backup_row[] = $this->get_restore_buttons( $file, $integrity_data['type'] );
				}

				$backup_row = apply_filters( 'backupbuddy_backups_list_row', $backup_row, $file, $this );

				$backups[ basename( $file ) ] = $backup_row;

				$backup_sort_dates[ basename( $file ) ] = $backup_date;
			} // End foreach.
		} // End if.

		$backups = $this->sort_backups( $backups, $backup_sort_dates );

		// $this->cache( $args, $backups );

		return $backups;
	}

	/**
	 * Sort backups by date, newest to oldest.
	 *
	 * @param array $backups     Array of backups.
	 * @param array $sort_dates  Array of backup dates in timestamp form.
	 *
	 * @return array  Sorted backups.
	 */
	public function sort_backups( $backups, $sort_dates ) {
		// Sort backup by date.
		arsort( $sort_dates );
		// Re-arrange backups based on sort dates.
		$sorted_backups = array();
		foreach ( $sort_dates as $backup_file => $sort_date ) {
			$sorted_backups[ $backup_file ] = $backups[ $backup_file ];
			unset( $backups[ $backup_file ] );
		}
		unset( $backups );

		return $sorted_backups;
	}

	/**
	 * Retrieve data about backup from fileoptions file.
	 *
	 * @param string $file  Path to backup file.
	 * @param array  $args  Array of args.
	 *
	 * @return array  Integrity Data array.
	 */
	public function get_integrity_data( $file, $args = array() ) {
		$serial = backupbuddy_core::parse_file( $file, 'serial' );

		$options = array();
		if ( file_exists( backupbuddy_core::getLogDirectory() . 'fileoptions/' . $serial . '.txt' ) ) {
			require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
			pb_backupbuddy::status( 'details', 'Fileoptions instance #33.' );
			$read_only      = false;
			$ignore_lock    = false;
			$create_file    = true;
			$backup_options = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/' . $serial . '.txt', $read_only, $ignore_lock, $create_file ); // Will create file to hold integrity data if nothing exists.
		} else {
			$backup_options = '';
		}
		$backup_integrity = backupbuddy_core::backup_integrity_check( $file, $backup_options, $options );

		// Backup status.
		$pretty_status = array(
			true   => '<span class="pb_label pb_label-success">Good</span>', // v4.0+ Good.
			'pass' => '<span class="pb_label pb_label-success">Good</span>', // Pre-v4.0 Good.
			false  => '<span class="pb_label pb_label-important">Bad</span>', // v4.0+ Bad.
			'fail' => '<span class="pb_label pb_label-important">Bad</span>', // Pre-v4.0 Bad.
		);

		// Backup type.
		$pretty_type = array(
			'full'    => __( 'Full', 'it-l10n-backupbuddy' ),
			'db'      => __( 'Database', 'it-l10n-backupbuddy' ),
			'files'   => __( 'Files', 'it-l10n-backupbuddy' ),
			'themes'  => __( 'Themes', 'it-l10n-backupbuddy' ),
			'plugins' => __( 'Plugins', 'it-l10n-backupbuddy' ),
		);

		// Defaults.
		$integrity_data = array(
			'type'          => backupbuddy_core::parse_file( $file, 'type' ),
			'detected_type' => backupbuddy_core::pretty_backup_type( backupbuddy_core::parse_file( $file, 'type' ) ),
			'file_size'     => file_exists( $file ) ? filesize( $file ) : false,
			'modified_time' => backupbuddy_core::parse_file( $file, 'timestamp' ),
			'modified'      => '',
			'comment'       => '',
			'output'        => '',
		);

		if ( is_array( $backup_integrity ) ) { // Data intact... put it all together.
			if ( ! empty( $args['include_types'] ) && ! in_array( $backup_integrity['detected_type'], $args['include_types'], true ) ) {
				return false;
			}

			$integrity_data['type'] = $backup_integrity['detected_type'];

			$detected_type = pb_backupbuddy::$format->prettify( $backup_integrity['detected_type'], $pretty_type );
			if ( '' == $detected_type ) {
				$detected_type = backupbuddy_core::pretty_backup_type( backupbuddy_core::getBackupTypeFromFile( $file ) );
				if ( '' == $detected_type ) {
					$detected_type = '<span class="description">Unknown</span>';
				}
			} else {
				if ( isset( $backup_options->options['profile'] ) ) {
					$profile_title = isset( $backup_options->options['profile']['title'] ) ? htmlentities( $backup_options->options['profile']['title'] ) : '';
					if ( 'legacy' === $this->mode ) {
						$detected_type = '
						<div>
							<span class="profile_type-' . $backup_integrity['detected_type'] . '" style="float: left;" title="' . backupbuddy_core::pretty_backup_type( $detected_type ) . '"></span>
							<span style="display: inline-block; float: left; height: 15px; border-right: 1px solid #EBEBEB; margin-left: 6px; margin-right: 6px;"></span>
							' . $profile_title . '
						</div>
						';
					} else {
						$detected_type = $profile_title;
					}
				} else {
					$detected_type = backupbuddy_core::pretty_backup_type( $detected_type );
				}
			}

			if ( $detected_type ) {
				$integrity_data['detected_type'] = $detected_type;
			}

			$integrity_data['modified_time'] = $backup_integrity['modified'];
			$integrity_data['file_size']     = $backup_integrity['size'];
			$integrity_data['modified']      = pb_backupbuddy::$format->date( $integrity_data['modified_time'], 'l, F j, Y - g:i a' );

			if ( isset( $backup_integrity['status'] ) ) { // Pre-v4.0.
				$integrity_data['status'] = $backup_integrity['status'];
			} else { // v4.0+.
				$integrity_data['status'] = $backup_integrity['is_ok'];
			}

			// Add comment to data array if exists.
			if ( ! empty( $backup_integrity['comment'] ) ) {
				$integrity_data['comment'] = $backup_integrity['comment'];
			}

			// No integrity check for themes or plugins types.
			$raw_type             = backupbuddy_core::getBackupTypeFromFile( $file );
			$skip_integrity_types = array( 'themes', 'plugins', 'media', 'files' );
			if ( in_array( $raw_type, $skip_integrity_types, true ) ) {
				unset( $file_count );
				foreach ( (array) $backup_integrity['tests'] as $test ) {
					if ( isset( $test['fileCount'] ) ) {
						$file_count = $test['fileCount'];
					}
				}
				$integrity_data['output'] = pb_backupbuddy::$format->prettify( $integrity_data['status'], $pretty_status ) . ' ';
				if ( isset( $file_count ) ) {
					$integrity_data['output'] .= '<span class="pb_label pb_label-warning" style="display: none;">' . $file_count . ' ' . esc_html__( 'files', 'it-l10n-backupbuddy' ) . '</span> '; // TODO: Hidden until future version.
				}
			} else {
				$integrity_data['output'] = pb_backupbuddy::$format->prettify( $integrity_data['status'], $pretty_status ) . ' ';
			}

			if ( isset( $backup_integrity['scan_notes'] ) && count( (array) $backup_integrity['scan_notes'] ) > 0 ) {
				foreach ( (array) $backup_integrity['scan_notes'] as $scan_note ) {
					$integrity_data['output'] .= $scan_note . ' ';
				}
			}

			$integrity_data['output'] .= '<a href="' . pb_backupbuddy::page_url() . '&reset_integrity=' . $serial . '" title="Rescan integrity. Last checked ' . pb_backupbuddy::$format->date( $backup_integrity['scan_time'] ) . '."><img src="' . pb_backupbuddy::plugin_url() . '/images/refresh_gray.gif" style="vertical-align: -1px;"></a>';
			$integrity_data['output'] .= '<div class="row-actions"><a title="' . __( 'Backup Status', 'it-l10n-backupbuddy' ) . '" href="' . pb_backupbuddy::ajax_url( 'integrity_status' ) . '&serial=' . $serial . '&#038;TB_iframe=1&#038;width=640&#038;height=600" class="thickbox">' . __( 'View Details', 'it-l10n-backupbuddy' ) . '</a></div>';

			$sum_log_file = backupbuddy_core::getLogDirectory() . 'status-' . $serial . '_' . pb_backupbuddy::$options['log_serial'] . '.txt';
			if ( file_exists( $sum_log_file ) ) {
				$integrity_data['output'] .= '<div class="row-actions"><a title="' . __( 'View Backup Log', 'it-l10n-backupbuddy' ) . '" href="' . pb_backupbuddy::ajax_url( 'view_log' ) . '&serial=' . $serial . '&#038;TB_iframe=1&#038;width=640&#038;height=600" class="thickbox">' . __( 'View Log', 'it-l10n-backupbuddy' ) . '</a></div>';
			}
		}

		if ( ! $integrity_data['file_size'] ) {
			$dat = backupbuddy_data_file()->get( $file );
			if ( ! empty( $dat['zip_size'] ) ) {
				$integrity_data['file_size'] = $dat['zip_size'];
			}
		}

		$integrity_data['output'] .= '<div class="row-actions"><a href="javascript:void(0);" class="pb_backupbuddy_hoveraction_hash" rel="' . basename( $file ) . '">View Checksum</a></div>';

		return $integrity_data;
	}

	/**
	 * Set the Destination ID for backups.
	 *
	 * @param int|string $destination_id  Remote Destination ID.
	 */
	public function set_destination_id( $destination_id ) {
		if ( $destination_id || 0 === $destination_id || '0' === $destination_id ) {
			$this->destination_id = $destination_id;
		}
	}

	/**
	 * Get the currently set destination ID.
	 *
	 * @return string  ID of current destination.
	 */
	public function get_destination_id() {
		return $this->destination_id;
	}

	/**
	 * Check if we are using remote backups.
	 *
	 * @return bool  If remote backups.
	 */
	public function is_remote() {
		return $this->destination_id || '0' === $this->destination_id || 0 === $this->destination_id;
	}

	/**
	 * Backup restore buttons.
	 *
	 * @param string $file         Path to backup file.
	 * @param string $backup_type  Backup type.
	 *
	 * @return string  Restore buttons HTML.
	 */
	public function get_restore_buttons( $file, $backup_type = false ) {
		$restore_buttons   = '';
		$button            = '<a href="%s" class="button restore-button%s"%s>%s</a>';
		$full_restore_attr = sprintf( ' data-zip="%s" data-destination-id="%s"', basename( $file ), $this->get_destination_id() );
		$full_restore_url  = admin_url( 'admin.php?page=pb_backupbuddy_backup' ) . '#restore-backup';

		if ( false === $backup_type ) {
			$backup_type = backupbuddy_core::parse_file( $file, 'type' );
		}

		if ( in_array( $backup_type, array( 'full', 'themes', 'plugins', 'media' ), true ) ) {
			$browse_tool = 'zip_viewer';
			$can_browse  = true;
			$destination = '';
			if ( backupbuddy_data_file()->locate( $file, $this->get_destination_id() ) ) {
				$browse_tool = 'dat_viewer';
				$destination = '&destination=' . $this->get_destination_id();
			} elseif ( $this->is_remote() ) {
				$can_browse = false;
			}
			if ( $can_browse ) {
				$restore_files_url = admin_url( 'admin.php' ) . '?page=pb_backupbuddy_backup&' . $browse_tool . '=' . basename( $file ) . '&value=' . basename( $file ) . $destination . '#restore-backup';
				$restore_buttons  .= sprintf( $button, $restore_files_url, '', '', esc_html__( 'Restore Files', 'it-l10n-backupbuddy' ) );
			}

			if ( 'full' !== $backup_type ) {
				$full_restore_attr .= ' data-what="files"';
			}
			$restore_buttons .= sprintf( $button, esc_attr( $full_restore_url ), ' restore-full-backup', $full_restore_attr, esc_html__( 'Restore', 'it-l10n-backupbuddy' ) );
		} elseif ( 'db' === $backup_type ) {
			$full_restore_attr .= ' data-what="db"';
			$restore_buttons   .= sprintf( $button, esc_attr( $full_restore_url ), ' restore-full-backup', $full_restore_attr, esc_html__( 'Restore', 'it-l10n-backupbuddy' ) );
		} else {
			$restore_buttons = '<span class="no-restore-available">N/A</span>';
		}

		return $restore_buttons;
	}

	/**
	 * Backup Details Link.
	 *
	 * @param string $file  Path to backup file.
	 *
	 * @return string  Details link.
	 */
	public function get_details_link( $file ) {
		return sprintf(
			'<a href="#backup-details" data-backup-zip="%s" data-destination-id="%s" class="backup-details">',
			esc_attr( basename( $file ) ),
			esc_attr( $this->get_destination_id() )
		) . esc_html__( 'Details', 'it-l10n-backupbuddy' ) . '</a>';
	}

	/**
	 * Get ellipsis action menu.
	 *
	 * @param string $file            Path to backup file.
	 * @param array  $custom_actions  Array of additional actions.
	 *
	 * @return string  Action menu markup.
	 */
	public function get_action_menu( $file, $custom_actions = array() ) {
		$action_menu = array();

		if ( $this->is_remote() ) {
			$default_actions = array(
				'&cpy=' . basename( $file ) => __( 'Copy to Local', 'it-l10n-backupbuddy' ),
			);
			if ( ! is_array( $custom_actions ) ) {
				$custom_actions = $default_actions;
			} else {
				$custom_actions = array_merge( $default_actions, $custom_actions );
			}
		} else {
			$download_url  = pb_backupbuddy::ajax_url( 'download_archive' ) . '&backupbuddy_backup=' . basename( $file );
			$download_link = sprintf( '<a href="%s" class="download-backup">%s</a>', esc_attr( $download_url ), esc_html__( 'Download', 'it-l10n-backupbuddy' ) );
			$remote_link   = sprintf( '<a href="#remote-send" data-backup-zip="%s" class="remote-send">', esc_attr( basename( $file ) ) ) . esc_html__( 'Send to Remote Destination', 'it-l10n-backupbuddy' ) . '</a>';
			$details_link  = $this->get_details_link( $file );

			$action_menu['download-backup'] = $download_link;
			$action_menu['remote-send']     = $remote_link;
			$action_menu['backup-details']  = $details_link;
		}

		if ( ! empty( $custom_actions ) ) {
			foreach ( $custom_actions as $action_url => $action_label ) {
				if ( $this->is_remote() ) {
					if ( 'http' === strtolower( substr( $action_url, 0, 4 ) ) || '#' === substr( $action_url, 0, 1 ) ) {
						$url = $action_url;
					} else {
						$url  = pb_backupbuddy::ajax_url( 'remoteClient' ) . '&destination_id=' . $this->get_destination_id() . $action_url;
						$url .= '&bub_rand=' . rand( 100, 999 );
					}
				} else {
					$url = $action_url;
				}
				$action_class = sanitize_title( $action_label );
				$action_link  = sprintf( '<a href="%s" class="%s">%s</a>', esc_attr( $url ), esc_attr( $action_class ), esc_html( $action_label ) );

				$action_menu[ $action_class ] = $action_link;
			}
		}

		$action_menu = apply_filters( 'backupbuddy_backups_action_menu', $action_menu, $file, $this );

		if ( ! count( $action_menu ) ) {
			return;
		}

		$actions  = '<a href="#actions" class="backup-actions">&hellip;</a>';
		$actions .= '<div class="backup-actions-menu">';
		$actions .= implode( $action_menu );
		$actions .= '</div>';

		return $actions;
	}

	/**
	 * Add days ago text to first column of backups listing.
	 *
	 * @param string $output    Column content.
	 * @param array  $item      Row item array.
	 * @param int    $item_id   ID of item.
	 * @param int    $itemi     Index of item.
	 * @param array  $settings  Settings array.
	 *
	 * @return string  Modified output.
	 */
	public function format_backup_title( $output, $item, $item_id, $itemi, $settings ) {
		if ( ! empty( $item[0][1] ) ) {
			$file     = $item[0][0];
			$modified = $item[0][1];
		} else {
			return $output;
		}

		$mode     = $settings['display_mode'];
		$filename = basename( $file );
		$label    = pb_backupbuddy::$format->date( $modified, 'l, F j, Y - g:i a' );
		$label   .= ' (' . pb_backupbuddy::$format->time_ago( $modified, (int) current_time( 'timestamp' ) ) . ' ago)';

		if ( 'default' === $mode ) { // Default backup listing.
			$output = '<a href="' . pb_backupbuddy::ajax_url( 'download_archive' ) . '&backupbuddy_backup=' . $filename . '" class="backupbuddyFileTitle" title="' . $filename . '">' . $label . '</a>';
		} elseif ( 'legacy' === $mode ) { // Copied default in case of changes.
			$output = '<a href="' . pb_backupbuddy::ajax_url( 'download_archive' ) . '&backupbuddy_backup=' . $filename . '" class="backupbuddyFileTitle" title="' . $filename . '">' . $label . '</a>';
			$integrity_data = $this->get_integrity_data( $file );
			if ( ! empty( $integrity_data['comment'] ) ) {
				$output .= '<br><span class="description">Note: <span class="pb_backupbuddy_notetext">' . htmlentities( $integrity_data['comment'] ) . '</span></span>';
			}
			$output .= '<br><span class="description" style="color: #AAA; display: inline-block; margin-top: 5px;">' . $filename . '</span>';
		} elseif ( 'restore' === $mode ) { // Default backup listing.
			$output = '<span class="backupbuddyFileTitle" title="' . $filename . '">' . $label . '</span>';
		} elseif ( 'migrate' === $mode ) { // Migration backup listing.
			$output = '<a class="pb_backupbuddy_hoveraction_migrate backupbuddyFileTitle" rel="' . $filename . '" href="' . pb_backupbuddy::page_url() . '&migrate=' . $filename . '&value=' . $filename . '" title="' . $filename . '">' . $label . '</a>';
		} else {
			$output = '{Unknown render mode.}';
		}

		return $output;
	}

	/**
	 * Get cached backup listing.
	 *
	 * @param array $args  Filter args.
	 *
	 * @return false|array  Array of backups or false;
	 */
	public function get_cached( $args ) {
		$transient = get_transient( 'backupbuddy_backups' );
		if ( ! $transient || ! is_array( $transient ) ) {
			return false;
		}
		$key = wp_json_encode( $args );
		if ( empty( $transient[ $key ] ) ) {
			return false;
		}
		return $transient[ $key ];
	}

	/**
	 * Cache array of backups.
	 *
	 * @param array $args    Array of args.
	 * @param array $backups Backups array.
	 */
	public function cache( $args, $backups ) {
		$transient = get_transient( 'backupbuddy_backups' );
		if ( ! $transient ) {
			$transient = array();
		}
		$transient[ wp_json_encode( $args ) ] = $backups;
		set_transient( 'backupbuddy_backups', $transient, DAY_IN_SECONDS );
	}

	/**
	 * Clear backup list cache.
	 */
	public function clear_cache() {
		delete_transient( 'backupbuddy_backups' );
	}

	/**
	 * Handle Table Bulk Actions.
	 */
	public function bulk_actions() {
		if ( 'delete_backup' === pb_backupbuddy::_POST( 'bulk_action' ) && is_array( pb_backupbuddy::_POST( 'items' ) ) ) {
			$this->bulk_delete_backups();
		} // End if deleting backup(s).
	}

	/**
	 * Handle Bulk Delete of Backups.
	 */
	private function bulk_delete_backups() {
		$needs_save = false;
		pb_backupbuddy::verify_nonce( pb_backupbuddy::_POST( '_wpnonce' ) ); // Security check to prevent unauthorized deletions by posting from a remote place.
		$deleted_files = array();
		foreach ( pb_backupbuddy::_POST( 'items' ) as $item ) {
			if ( $this->delete( $item ) ) {
				$deleted_files[] = $item;

				// Cleanup any related fileoptions files.
				$serial = backupbuddy_core::parse_file( $item, 'serial' );

				// When deleting 2 or more backups.
				$backup_files = glob( backupbuddy_core::getBackupDirectory() . '*.zip' );
				if ( ! is_array( $backup_files ) ) {
					$backup_files = array();
				}
				if ( count( $backup_files ) > 5 ) { // Keep a minimum number of backups in array for stats.
					$this_serial      = backupbuddy_core::parse_file( $item, 'serial' );
					$fileoptions_file = backupbuddy_core::getLogDirectory() . 'fileoptions/' . $this_serial . '.txt';
					if ( file_exists( $fileoptions_file ) ) {
						@unlink( $fileoptions_file );
					}
					if ( file_exists( $fileoptions_file . '.lock' ) ) {
						@unlink( $fileoptions_file . '.lock' );
					}
					$needs_save = true;
				}
			} else {
				pb_backupbuddy::alert( 'Error: Unable to delete backup file `' . $item . '`. Please verify permissions and file exists.', true, '', '', '', array( 'class' => 'below-h2' ) );
			}
		} // End foreach.

		if ( true === $needs_save ) {
			pb_backupbuddy::save();
		}

		$this->clear_cache();

		pb_backupbuddy::alert( __( 'Deleted:', 'it-l10n-backupbuddy' ) . ' ' . implode( ', ', $deleted_files ), false, '', '', '', array( 'class' => 'below-h2' ) );
	}

	/**
	 * Delete Backup.
	 *
	 * @param string $backup_file  Zip file name.
	 *
	 * @return bool  If deleted.
	 */
	public function delete( $backup_file, $keep_dat = false ) {
		$path_to_backup = backupbuddy_core::getBackupDirectory() . $backup_file;
		if ( ! file_exists( $path_to_backup ) ) {
			return false;
		}
		$data_file = backupbuddy_data_file()->locate( $path_to_backup );
		if ( true === @unlink( $path_to_backup ) ) {
			if ( true === $keep_dat ) {
				return true;
			}

			// Delete data file.
			if ( false !== $data_file ) {
				backupbuddy_data_file()->delete( $data_file );
			}
			return true;
		}
		return false;
	}
}
