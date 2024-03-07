<?php
/**
 * Backup file restore class.
 *
 * @package BackupBuddy
 */

/**
 * Used for restoring files from backups.
 */
class BackupBuddy_Restore {

	// Prep stages.
	const STATUS_NOT_STARTED = 0;

	const STATUS_STARTED = 1;

	const STATUS_DOWNLOADING = 2;

	const STATUS_READY = 3;

	const STATUS_UNZIPPING = 4;

	const STATUS_UNZIPPED = 5;

	const STATUS_VERIFYING = 6;

	const STATUS_VERIFIED = 7;

	const STATUS_PERMISSIONS = 8;

	const STATUS_DB_READY = 9;

	const STATUS_DB_TABLES = 10;

	const STATUS_PAUSED = 11;

	// Action Stages.
	const STATUS_RESTORING = 100;

	const STATUS_COPYING = 110;

	const STATUS_RESTORING_FILES = 115;

	const STATUS_DATABASE = 120;

	const STATUS_RESTORING_DB = 125;

	const STATUS_CLEANUP = 130;

	// Completed statuses.
	const STATUS_COMPLETE = 200;

	const STATUS_FAILED = 400;

	const STATUS_ABORTED = 500;

	const STATUS_USER_ABORTED = 600;

	// Number of files to be moved in one pass.
	const FILE_BATCH_SIZE = 1000;

	const CRON_GROUP = 'solid-backups-restore';

	/**
	 * Path to storage file.
	 *
	 * @var string
	 */
	private $restore_storage;

	/**
	 * Current Restore Storage
	 *
	 * @var string
	 */
	private $current_restore;

	/**
	 * Array of completed statuses.
	 *
	 * @var array
	 */
	private $completed_statuses = array();

	/**
	 * Stores single instance of this object.
	 *
	 * @var object
	 */
	private static $instance;

	/**
	 * Array of restores.
	 *
	 * @var array
	 */
	private $restores = array();

	/**
	 * Current restore array.
	 *
	 * @var array
	 */
	private $restore = array();

	/**
	 * Path to archive for restore.
	 *
	 * @var string
	 */
	private $archive;

	/**
	 * Files to restore.
	 *
	 * @var array
	 */
	private $files;

	/**
	 * Tables to restore.
	 *
	 * @var array
	 */
	private $tables;

	/**
	 * Path where files are being restored.
	 *
	 * @var string
	 */
	private $restore_path;

	/**
	 * Temporary directory for zip extraction.
	 * Contains backup files to be restored.
	 *
	 * @var string
	 */
	private $temp_dir;

	/**
	 * Backup Serial/ID.
	 *
	 * @var string
	 */
	private $serial;

	/**
	 * Used for restoring tables.
	 *
	 * @var string
	 */
	private $table_prefix;

	/**
	 * Directory files to ignore when restoring.
	 *
	 * @var array
	 */
	private $ignore_files = array();

	/**
	 * Maximum number of restores to keep in history.
	 *
	 * @var int
	 */
	private $max_history = 10;

	/**
	 * Class Constructor.
	 */
	public function __construct() {
		if ( ! class_exists( 'backupbuddy_core' ) ) {
			require_once pb_backupbuddy::plugin_path() . '/classes/core.php';
		}

		$this->restore_storage = backupbuddy_core::getLogDirectory() . 'backupbuddy-restores.txt';

		$this->completed_statuses = array(
			self::STATUS_COMPLETE,
			self::STATUS_FAILED,
			self::STATUS_ABORTED,
			self::STATUS_USER_ABORTED,
		);

		$this->ignore_files = apply_filters(
			'backupbuddy_restore_ignore_files',
			array(
				'*.DS_Store',
				'*.itbub',
			)
		);

		return $this;
	}

	/**
	 * Instance generator.
	 *
	 * @return object  BackupBuddy_Restore instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new BackupBuddy_Restore();
		}
		return self::$instance;
	}

	/**
	 * Returns array of completed statuses.
	 *
	 * @return array  Completed statuses array.
	 */
	public function get_completed_statuses() {
		return $this->completed_statuses;
	}

	/**
	 * Returns array of files to ignore when restoring.
	 *
	 * @return array  Files to ignore.
	 */
	public function get_ignore_files() {
		return $this->ignore_files;
	}

	/**
	 * Return the Temp directory path.
	 *
	 * This normalizes the path with a trailing slash.
	 *
	 * @return string  Temp directory path or empty string if not available.
	 */
	private function temp_dir() {
		if ( ! empty( $this->temp_dir ) ) {
			return self::trailingslashit( $this->temp_dir );
		}

		if ( ! empty( $this->restore['temp_dir'] ) ) {
			$this->temp_dir = self::trailingslashit( $this->restore['temp_dir'] );
			return $this->temp_dir;
		}

		if ( ! empty( $this->restore['id'] ) ) {
			$restore = $this->details( $this->restore['id'] );

			if ( ! empty( $restore['temp_dir'] ) ) {
				$this->temp_dir = self::trailingslashit( $restore['temp_dir'] );
				return $this->temp_dir;
			}
		}

		return '';
	}

	/**
	 * Queue up files to be restored.
	 *
	 * @param string $zip_file        Zip File name.
	 * @param array  $files           Array of files to be restored.
	 * @param array  $tables          Array of tables to be restored.
	 * @param string $destination_id  ID of destination where zip is stored.
	 * @param string $what            What to restore (db, files, or both).
	 *
	 * @return bool|string  Fails on failure, otherwise Restore ID.
	 */
	public function queue( $zip_file, $files, $tables, $destination_id = null, $what = 'files' ) {
		if ( ! pb_is_standalone() ) {
			if ( ! current_user_can( pb_backupbuddy::$options['role_access'] ) ) {
				// Restore Error: Access denied.
				return false;
			}
		}

		if ( ! $zip_file ) {
			// Restore Error: Missing zip file.
			return false;
		}

		$serial = backupbuddy_core::parse_file( $zip_file, 'serial' );

		foreach ( $this->get_queue() as $restore_json ) {
			$restore = json_decode( $restore_json, true );
			if ( $restore['serial'] === $serial && ! in_array( $restore['status'], $this->get_completed_statuses(), true ) ) {
				// Restore Error: Restore still in progress.
				return false;
			}
		}

		$id      = $serial . '-' . uniqid();
		$restore = array(
			'backup_file' => $zip_file,
			'type'        => 'partial',
			'what'        => $what,
			'serial'      => $serial,
			'id'          => $id,
		);

		// Setup Files.
		if ( '*' === $files ) {
			// Full Restore.
			$restore['type'] = 'full';
			$backup_type     = backupbuddy_core::parse_file( $zip_file, 'type' );
			if ( 'db' === $what ) {
				if ( 'db' === $backup_type ) {
					$files = array( '*' );
				} else {
					$files = array( 'wp-content/uploads/backupbuddy_temp/*' );
				}
			}
		} elseif ( ! empty( $files ) && ! is_array( $files ) ) {
			// Restoring a single file/directory.
			$files = array( $files );
		}

		/*
		 * Formatting of $this->files:
		 *
		 * Selective Restore Format will be an array of specific
		 * file paths to be restored.
		 * It may contain wildcard strings representing entire
		 * directories, but it will not include a listing
		 * of all of the files in those directores.
		 *
		 * Example:
		 * array(
		 *    wp-content/plugins/plugin/index.php,
		 *    wp-content/plugins/another-plugin/*,
		 * )
		 *
		 * Full Restore Format will simply be:
		 * array( '*' )
		*/
		$restore['files'] = $files;

		// Setup Tables.
		if ( '*' === $tables ) {
			$restore['type'] = 'full';
		} elseif ( ! empty( $tables ) && ! is_array( $tables ) ) {
			$tables = array( $tables );
		}

		$restore['tables'] = $tables;

		$backups_dir = backupbuddy_core::getBackupDirectory();
		if ( ! file_exists( $backups_dir . $zip_file ) ) {
			if ( ! $destination_id && 0 !== $destination_id && '0' !== $destination_id ) {
				// Restore Error: Could not locate backup file.
				return false;
			}

			if ( empty( pb_backupbuddy::$options['remote_destinations'][ $destination_id ] ) ) {
				// Restore Error: Remote destination not found.
				return false;
			}

			// TODO: Check remote destination to make sure backup file exists.
			$restore['destination_id']   = $destination_id;
			$restore['destination_args'] = pb_backupbuddy::$options['remote_destinations'][ $destination_id ];
			$dat_file                    = backupbuddy_data_file()->get( $zip_file, $destination_id );
			if ( $dat_file && ! empty( $dat_file['zip_size'] ) ) {
				$restore['zip_size'] = $dat_file['zip_size'];
			}
		}

		$restore['zip_path'] = $backups_dir . $zip_file;

		$backup_type = backupbuddy_core::parse_file( $zip_file, 'type' );
		$restore_dir = ABSPATH;
		if ( 'themes' === $backup_type ) {
			$restore_dir = backupbuddy_core::get_themes_root();
		} elseif ( 'plugins' === $backup_type ) {
			$restore_dir = backupbuddy_core::get_plugins_root();
		} elseif ( 'media' === $backup_type ) {
			$restore_dir = backupbuddy_core::get_media_root();
		}

		$restore['profile']      = $backup_type;
		$restore['restore_path'] = self::trailingslashit( $restore_dir );

		return $this->add_to_queue( $restore );
	}

	/**
	 * Add a restore to the queue to be processed.
	 *
	 * @param array $restore  Restore args.
	 *
	 * @return string  Restore ID.
	 */
	private function add_to_queue( $restore ) {
		$file_defaults = array(
			'perms'          => array(), // folders where permissions have been changed.
			'perm_fails'     => array(), // files that failed to set permissions.
			'moved'          => array(), // files/folders successfully moved.
			'files_started'  => false,   // If files have started processing.
			'active_plugins' => array(), // If files have started processing.
		);
		$db_defaults   = array(
			'tables'            => array(), // requested tables for restore.
			'sql_files'         => array(), // sql files in backup.
			'sql_path'          => false, // path to sql files.
			'single_file'       => false, // If import is all 1 file (db_1.sql).
			'table_queue'       => array(), // array of tables for restoring.
			'imported_tables'   => array(), // array of tables that have been imported.
			'post_import'       => array(), // array of queries to run after temp table imported.
			'incomplete_tables' => array(), // array of tables that need to finish importing.
			'failed_tables'     => array(), // array of tables that failed to import.
			'last_tables'       => array(), // array of tables that need to be imported last.
			'restored_tables'   => array(), // array of tables that have been restored.
			'cleanup_db'        => array(), // SQL queries to run during cleanup.
			'finalize_db'       => array(), // SQL queries to run during cleanup.
		);
		$base_defaults = array(
			'id'               => false, // restore id.
			'serial'           => false, // backup serial.

			'backup_file'      => false, // backup filename.
			'zip_path'         => false, // path to backup zip.
			'zip_size'         => false, // Expected zip file size.
			'temp_dir'         => false, // path for zip extraction.
			'files'            => array(), // Files for restore.
			'extract_files'    => array(), // files to extract from zip.

			'type'             => false, // full or partial.
			'what'             => 'files', // files, db, or both.
			'profile'          => false, // backup zip profile.
			'restore_path'     => false, // path to restore files to.

			'status'           => self::STATUS_NOT_STARTED,
			'files_ready'      => false, // status of file restore.
			'db_ready'         => false, // status of db restore.
			'file_status'      => false, // completion of file restore.
			'db_status'        => false, // completion of db restore.

			'destination_id'   => false,
			'destination_args' => false,
			'download'         => false,

			'initialized'      => current_time( 'timestamp' ),
			'started'          => false,
			'completed'        => false,
			'elapsed'          => false,
			'viewed'           => false,
			'aborted'          => false,

			'errors'           => array(),
			'log'              => array(),
		);

		if ( 'files' === $restore['what'] ) {
			$defaults = array_merge( $base_defaults, $file_defaults );
		} elseif ( 'db' === $restore['what'] ) {
			$defaults = array_merge( $base_defaults, $db_defaults );
		} else {
			$defaults = array_merge( $base_defaults, $file_defaults, $db_defaults );
		}

		$this->restore = array_merge( $defaults, $restore );

		// Trigger download if necessary.
		if ( ! empty( $this->restore['destination_args'] ) && ! file_exists( $this->restore['zip_path'] ) ) {
			if ( ! $this->restore['download'] ) {
				$this->schedule_download();
			}
		}

		$this->get_queue();
		$this->restores = array_merge( array( $this->restore['id'] => wp_json_encode( $this->restore ) ), $this->restores ); // Put newest in front.
		$this->save();

		// Make sure we're starting fresh.
		$this->unlock_cron();
		$this->remove_abort_file();
		$this->schedule_cron();

		return $this->restore['id'];
	}

	/**
	 * Schedules the cron to process the queue.
	 *
	 * @return bool  If scheduled.
	 */
	public function schedule_cron() {
		if ( ! $this->in_progress() ) {
			return false;
		}

		// Attempt to schedule the cron to run now.
		$restore_id = ! empty( $this->restore['id'] ) ? $this->restore['id'] : 0;

		$has_scheduled_event = backupbuddy_core::has_scheduled_event(
			'process_restore_queue',
			array( $restore_id ),
			self::CRON_GROUP
		);

		if ( $has_scheduled_event ) {
			return;
		}

		$scheduled  = backupbuddy_core::schedule_single_event(
			time(),
			'process_restore_queue',
			array( $restore_id ),
			self::CRON_GROUP
		);

		backupbuddy_core::maybe_spawn_cron();

		return $scheduled;
	}

	/**
	 * Schedule Destination Zip download.
	 *
	 * @return bool  If scheduled.
	 */
	public function schedule_download() {
		$this->log( 'Scheduling remote zip download...' );

		$remote_types = array( 'stash2', 'stash3', 's32', 's33' );
		if ( in_array( $this->restore['destination_args']['type'], $remote_types, true ) ) {
			$schedule_args   = array(
				$this->restore['destination_args']['type'],
				$this->restore['backup_file'],
				$this->restore['destination_args'],
			);
			$schedule_method = 'process_remote_copy';
		} else {
			$schedule_args   = array(
				$this->restore['destination_args'],
				$this->restore['backup_file'],
			);
			$schedule_method = 'process_destination_copy';
		}

		$scheduled = backupbuddy_core::has_scheduled_event( $schedule_method, $schedule_args, self::CRON_GROUP );
		if ( ! $scheduled ) {
			$scheduled = backupbuddy_core::schedule_single_event( time(), $schedule_method, $schedule_args, self::CRON_GROUP );
		}

		if ( ! empty( $this->restore ) && $scheduled ) {
			// The idea is this should always result in a true value.
			$this->restore['download'] = $scheduled;
			$this->log( '✓ Remote zip download scheduled.' );
			$this->save();

			backupbuddy_core::maybe_spawn_cron();
		}

		return $scheduled;
	}

	/**
	 * Gets the array of restores.
	 *
	 * @param bool $refresh  Grabs a fresh copy of remote storage.
	 *
	 * @return array  Restore queue array.
	 */
	public function get_queue( $refresh = false ) {
		if ( empty( $this->restore ) ) {
			$this->save(); // Handle pruning and update storage file.
		}

		if ( $this->restores && false === $refresh ) {
			return $this->restores;
		}

		$restores = array();
		if ( file_exists( $this->restore_storage ) ) {
			$restores = trim( @file_get_contents( $this->restore_storage ) );

			if ( ! $restores ) {
				$restores = array();
			} else {
				$restores = json_decode( $restores, true );
				if ( ! is_array( $restores ) ) {
					pb_backupbuddy::status( 'details', 'There was a problem parsing the restore history. Error returned: `' . json_last_error() . '`. Value was: ' . print_r( $restores, true ) );
					$restores = array();
				}
			}
		}

		$this->restores = $restores;

		return $this->restores;
	}

	/**
	 * Restore Archive
	 *
	 * @param array $exclude  Array of Restore IDs to exclude.
	 *
	 * @return array  Archive of Restores.
	 */
	public function get_archive( $exclude = array() ) {
		$archive_files = glob( backupbuddy_core::getLogDirectory() . 'backupbuddy-restore-*.txt' ) ?: array();
		$archive_files = array_diff( $archive_files, array( '.', '..' ) );
		if ( ! count( $archive_files ) ) {
			return array();
		}

		$archives = array();

		foreach ( $archive_files as $archive_file ) {
			$restore = file_get_contents( $archive_file );
			$restore = json_decode( $restore, true );
			if ( $restore && is_array( $restore ) ) {
				if ( ! in_array( $restore['id'], $exclude, true ) ) {
					$archives[] = $restore;
				}
			} else {
				$archives[] = $archive_file;
			}
		}
		return $archives;
	}

	/**
	 * Get restore details.
	 *
	 * @param string $restore_id  Restore ID.
	 *
	 * @return array  Restore array.
	 */
	public function details( $restore_id ) {
		$this->get_queue();

		if ( ! empty( $this->restores[ $restore_id ] ) ) {
			$restore = json_decode( $this->restores[ $restore_id ], true );
			return $this->load_restore( $restore );
		}

		return false;
	}

	/**
	 * Check if restore needs to be shown on restore page.
	 *
	 * @param string $restore_id  Restore ID.
	 *
	 * @return string|false  Restore ID in progress or false.
	 */
	public function in_progress( $restore_id = false ) {
		$this->get_queue();

		if ( $restore_id ) {
			$restore = $this->details( $restore_id );
			if ( ! is_array( $restore ) ) {
				return false;
			}

			if ( in_array( $restore['status'], $this->get_completed_statuses(), true ) ) {
				return false;
			}

			return $restore['id'];
		}

		foreach ( $this->restores as $restore_json ) {
			$restore = json_decode( $restore_json, true );
			if ( empty( $restore['viewed'] ) ) {
				if ( in_array( $restore['status'], $this->get_completed_statuses(), true ) ) {
					$this->restore_viewed( $restore['id'] );
					continue;
				}

				return $restore['id'];
			}
		}

		return false;
	}

	/**
	 * Mark Restore as viewed.
	 *
	 * @param string $restore_id  Restore ID.
	 *
	 * @return bool  If marked.
	 */
	public function restore_viewed( $restore_id ) {
		$restore = $this->details( $restore_id );
		if ( ! is_array( $restore ) ) {
			return false;
		}

		if ( empty( $restore['viewed'] ) ) {
			$restore['viewed'] = current_time( 'timestamp' );
			$this->restore     = $restore;
			$this->save();
			return true;
		}

		return false;
	}

	/**
	 * Unmark Restore as viewed.
	 *
	 * @param string $restore_id  Restore ID.
	 *
	 * @return bool  If cleared.
	 */
	public function clear_viewed( $restore_id ) {
		$restore = $this->details( $restore_id );
		if ( ! is_array( $restore ) ) {
			return false;
		}

		$restore['viewed'] = false;
		$this->restore     = $restore;
		$this->save();
		return true;
	}

	/**
	 * Convert restore into status array used for Javascript.
	 *
	 * @param array $restore  Restore array.
	 *
	 * @return array  JS Status array.
	 */
	public function get_js_status( $restore ) {
		$status_js = array(
			'text'       => esc_html__( 'Restoring...', 'it-l10n-backupbuddy' ),
			'dot'        => '',
			'end_status' => '',
			'code'       => $restore['status'],
			'locked'     => $restore['status'] >= self::STATUS_RESTORING,
			'html'       => '',
			'details'    => '',
		);

		if ( self::STATUS_STARTED === $restore['status'] ) {
			$status_js['dot']  = 'started';
			$status_js['text'] = esc_html__( 'Restore started...', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_DOWNLOADING === $restore['status'] ) {
			$status_js['dot']  = 'downloading';
			$status_js['text'] = esc_html__( 'Downloading zip file...', 'it-l10n-backupbuddy' );
		} elseif ( in_array( $restore['status'], array( self::STATUS_READY, self::STATUS_UNZIPPING, self::STATUS_UNZIPPED, self::STATUS_VERIFYING, self::STATUS_VERIFIED, self::STATUS_PERMISSIONS ), true ) ) {
			$status_js['dot'] = 'unzipping';

			if ( self::STATUS_VERIFYING === $restore['status'] || self::STATUS_VERIFIED === $restore['status'] ) {
				$status_js['text'] = esc_html__( 'Verifying extraction...', 'it-l10n-backupbuddy' );
			} elseif ( self::STATUS_PERMISSIONS === $restore['status'] ) {
				$status_js['text'] = esc_html__( 'Handling permissions...', 'it-l10n-backupbuddy' );
			} elseif ( self::STATUS_READY === $restore['status'] ) {
				$status_js['text'] = esc_html__( 'Extracting zipped files...', 'it-l10n-backupbuddy' );
			} elseif ( self::STATUS_UNZIPPED === $restore['status'] ) {
				$status_js['text'] = esc_html__( 'Zip extraction complete.', 'it-l10n-backupbuddy' );
			} else {
				$status_js['text'] = esc_html__( 'Unzipping backup contents...', 'it-l10n-backupbuddy' );
			}
		} elseif ( in_array( $restore['status'], array( self::STATUS_DB_READY, self::STATUS_DB_TABLES ), true ) ) {
			$status_js['dot'] = 'database';
			if ( isset( $restore['failed_tables'] ) || isset( $restore['last_tables'] ) ) {
				$current_table = count( array_merge( $restore['failed_tables'], $restore['last_tables'], $restore['imported_tables'] ) );
			} else {
				$current_table = 0;
			}
			if ( isset( $restore['table_queue'] ) ) {
				$total_tables = count( $restore['table_queue'] );
				if ( 1 === $total_tables && true === $restore['single_file'] ) {
					$total_tables = isset( $restore['imported_tables'] ) ? count( $restore['imported_tables'] ) : '?';
				}
			} else {
				$total_tables = 0;
			}

			if ( self::STATUS_DB_READY === $restore['status'] && ! $current_table ) {
				if ( $total_tables ) {
					$status_js['text'] = esc_html__( 'Performing database restore...', 'it-l10n-backupbuddy' );
				} else {
					$status_js['text'] = esc_html__( 'Checking for database restore...', 'it-l10n-backupbuddy' );
				}
			} else {
				if ( $current_table <= 0 ) {
					$status_js['text'] = esc_html__( 'Preparing to restore database tables...', 'it-l10n-backupbuddy' );
				} else {
					if ( $current_table > $total_tables ) {
						$current_table = $total_tables;
					}
					$completed         = $current_table . '/' . $total_tables;
					$status_js['text'] = esc_html__( 'Importing database tables', 'it-l10n-backupbuddy' ) . ' ' . $completed . '...';
				}
			}
		} elseif ( in_array( $restore['status'], array( self::STATUS_RESTORING, self::STATUS_RESTORING_FILES, self::STATUS_RESTORING_DB, self::STATUS_COPYING, self::STATUS_PERMISSIONS, self::STATUS_DATABASE, self::STATUS_CLEANUP, self::STATUS_PAUSED ), true ) ) {
			$status_js['dot'] = 'restoring';

			if ( self::STATUS_CLEANUP === $restore['status'] ) {
				$status_js['text'] = esc_html__( 'Cleaning up...', 'it-l10n-backupbuddy' );
			} elseif ( self::STATUS_PAUSED === $restore['status'] ) {
				$status_js['text'] = esc_html__( 'Restore Paused.', 'it-l10n-backupbuddy' );
			} else {
				$status_js['text'] = $this->get_restore_progress_status( $restore );
			}
		} elseif ( in_array( $restore['status'], $this->get_completed_statuses(), true ) ) {
			$status_js['dot']  = 'complete';
			$status_js['html'] = $this->get_status_html( $restore, __( 'Details', 'it-l10n-backupbuddy' ) );

			if ( self::STATUS_FAILED === $restore['status'] ) {
				$status_js['text']       = esc_html__( 'Restore failed.', 'it-l10n-backupbuddy' );
				$status_js['error']      = $this->get_last_error( $restore );
				$status_js['end_status'] = 'failed';
			} elseif ( self::STATUS_ABORTED === $restore['status'] ) {
				$status_js['text']       = esc_html__( 'Restore aborted.', 'it-l10n-backupbuddy' );
				$status_js['end_status'] = 'aborted';
			} elseif ( self::STATUS_USER_ABORTED === $restore['status'] ) {
				$status_js['text']       = esc_html__( 'Restore aborted by user.', 'it-l10n-backupbuddy' );
				$status_js['end_status'] = 'aborted';
			} else {
				$status_js['text']       = esc_html__( 'Restore complete.', 'it-l10n-backupbuddy' );
				$status_js['end_status'] = 'complete';
			}
		}

		return $status_js;
	}

	/**
	 * Try to determine where we are in the restore process.
	 *
	 * @param array $restore  Restore array.
	 *
	 * @return string  Restore status string.
	 */
	public function get_restore_progress_status( $restore ) {
		$status = esc_html__( 'Restoring...', 'it-l10n-backupbuddy' );

		if ( true !== $restore['file_status'] && in_array( $restore['what'], array( 'both', 'files' ), true ) ) {
			$extract_files = 0;
			if ( ! empty( $restore['extract_files'] ) && is_array( $restore['extract_files'] ) ) {
				$extract_files = count( $restore['extract_files'] );
			}
			$total_files = $extract_files ? '/' . number_format( $extract_files ) : '';
			$moved = 0;
			if ( ! empty( $restore['moved'] )  && is_array( $restore['moved'] ) ) {
				$moved = count( array_unique( $restore['moved'] ) );
			}
			if ( 0 !== $moved ) {
				$status = sprintf( '%s%s Files Restored.', number_format( $moved ), $total_files );
			}
		} elseif ( true !== $restore['db_status'] && in_array( $restore['what'], array( 'both', 'db' ), true ) ) {
			if ( isset( $restore['restored_tables'] ) && isset( $restore['imported_tables'] ) ) {
				$status = sprintf( '%d/%d Database Tables Restored.', count( $restore['restored_tables'] ), count( $restore['imported_tables'] ) );
			} else {
				$status = '0/? Database Tables Restored.';
			}
		}

		return $status;
	}

	/**
	 * Check to see if a Restore cron is currently in progress.
	 *
	 * @param bool $create_lock  Create a lock file if one doesn't exist.
	 *
	 * @return bool  If restore cron is in progress.
	 */
	public function cron_in_progress( $create_lock = true ) {
		$lock_dir  = backupbuddy_core::getLogDirectory();
		$lock_file = 'backupbuddy-restore.lock';
		$lock_path = self::trailingslashit( $lock_dir ) . $lock_file;
		if ( file_exists( $lock_path ) ) {
			return true;
		}
		if ( true === $create_lock ) {
			$restore_id = $this->in_progress();
			$lock       = fopen( $lock_path, 'w' );
			if ( ! $restore_id ) {
				$restore_id = 'unknown';
			}

			// Wipe file first.
			if ( is_resource( $lock ) ) {
				fwrite( $lock, '' );
			}

			fwrite( $lock, $restore_id );
			fclose( $lock );
		}
		return false;
	}

	/**
	 * Remove cron lock file.
	 *
	 * @return bool  If removed.
	 */
	public function unlock_cron() {
		$lock_dir  = backupbuddy_core::getLogDirectory();
		$lock_file = 'backupbuddy-restore.lock';
		$lock_path = self::trailingslashit( $lock_dir ) . $lock_file;
		if ( ! file_exists( $lock_path ) ) {
			return false;
		}
		return @unlink( $lock_path );
	}

	/**
	 * Frees up memory and removes cron lock file.
	 *
	 * @param bool $schedule_new  Schedule a new cron.
	 *
	 * @return bool  If lock file was removed.
	 */
	public function cron_complete( $schedule_new = true ) {
		// Reset these vars since we're done.
		$this->restore         = array();
		$this->current_restore = false;
		pb_backupbuddy::flush();

		// Clean up Action Scheduler logs.
		self::housekeeping();

		$return = $this->unlock_cron();
		if ( true === $schedule_new ) {
			$this->schedule_cron();
		}
		return $return;
	}

	/**
	 * Load Full restore data (from individual restore file) into restore array.
	 *
	 * @param array $restore_array  Restore array.
	 * @param int   $attempts       Number of attempts to read file.
	 *
	 * @return array  Full restore array if avialable.
	 */
	public function load_restore( $restore_array = array(), $attempts = 1 ) {
		$max_attempts_threshold = 10;

		if ( empty( $restore_array ) && ! empty( $this->restore ) ) {
			$restore_array = $this->restore;
		}

		if ( empty( $restore_array['id'] ) ) {
			return false;
		}

		$this->current_restore = backupbuddy_core::getLogDirectory() . 'backupbuddy-restore-' . $restore_array['id'] . '.txt';

		if ( file_exists( $this->current_restore ) ) {
			$load_restore = json_decode( file_get_contents( $this->current_restore ), true );
			if ( is_array( $load_restore ) ) {
				// Keep status from master file.
				$restore_status = $restore_array['status'];
				$restore_array  = array_merge( $restore_array, $load_restore );
				// Restore original status value.
				$restore_array['status'] = $restore_status;
			} else {
				// File unavailable maybe it's being written, try again after a few seconds.
				$attempts++;
				$sleep = $attempts < 5 ? 2 : $attempts;
				sleep( $sleep );
				if ( $attempts <= $max_attempts_threshold ) {
					return $this->load_restore( $restore_array, $attempts );
				}
				if ( ! $this->restore ) {
					$this->error( __( 'Could not load restore file.', 'it-l10n-backupbuddy' ) );
					return false;
				}
			}
		}

		return $restore_array;
	}

	/**
	 * Save individual restore file.
	 *
	 * @param array $restore_array  The restore array to write, defaults to $this->restore.
	 *
	 * @return bool  If save was successful.
	 */
	public function save_restore( $restore_array = array() ) {
		if ( empty( $restore_array ) ) {
			$restore_array = $this->restore;
		}

		if ( empty( $restore_array['id'] ) ) {
			return false;
		}

		$this->current_restore = backupbuddy_core::getLogDirectory() . 'backupbuddy-restore-' . $restore_array['id'] . '.txt';

		// Write the full restore detail to separate file.
		if ( ! @file_put_contents( $this->current_restore, wp_json_encode( $restore_array ) ) ) {
			pb_backupbuddy::status( 'details', 'Attempt to write to restore log failed. Check folder permissions for ' . backupbuddy_core::getLogDirectory() . '.' );
			return false;
		}

		return true;
	}

	/**
	 * Save changes made to restore queue array.
	 *
	 * @return bool  If saved.
	 */
	private function save() {
		if ( ! is_array( $this->restores ) ) {
			return false;
		}

		$restore_queue = $this->restores;

		if ( ! empty( $this->restore ) ) {
			$restore_array = $this->restore;

			// Trim the larger chunks of data from the main queue file.
			unset( $restore_array['extract_files'] );
			unset( $restore_array['perms'] );
			unset( $restore_array['perm_fails'] );
			unset( $restore_array['sql_files'] );
			unset( $restore_array['tables'] );
			unset( $restore_array['imported_tables'] );
			unset( $restore_array['restored_tables'] );
			unset( $restore_array['failed_tables'] );
			unset( $restore_array['last_tables'] );
			unset( $restore_array['incomplete_tables'] );
			unset( $restore_array['cleanup_db'] );
			unset( $restore_array['finalize_db'] );
			unset( $restore_array['errors'] );
			unset( $restore_array['log'] );

			// Clean up moved files array.
			if ( isset( $restore_array['moved'] ) ) {
				$restore_array['moved'] = is_array($restore_array['moved']) ? $restore_array['moved'] : array();
				$restore_array['moved'] = array_unique($restore_array['moved']);
			} else {
				$restore_array['moved'] = array();
			}

			// Save Queue without extra data.
			$restore_queue[ $restore_array['id'] ] = wp_json_encode( $restore_array );

			if ( ! $this->save_restore() ) {
				pb_backupbuddy::status( 'details', 'Attempted to save restore but was unsuccessful.' );
				return false;
			}

			if ( ! $this->current_restore || ! file_exists( $this->current_restore ) ) {
				pb_backupbuddy::status( 'details', 'Attempted to write to restore log, but log was unavailable.' );
				return false;
			}

			if ( ! $this->write_restore_queue( $restore_queue ) ) {
				return false;
			}

			// Keep cron alive.
			if ( ! in_array( $restore_array['status'], $this->get_completed_statuses(), true ) ) {
				$this->schedule_cron();
			}
		} else {
			// Only prune if we're not changing restore details.
			if ( $this->prune() ) {
				if ( ! $this->write_restore_queue() ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Perform Restore Queue file save.
	 *
	 * @param array $restore_queue  Array to use for queue.
	 *
	 * @return bool  If successful.
	 */
	private function write_restore_queue( $restore_queue = array() ) {
		if ( empty( $restore_queue ) ) {
			$restore_queue = $this->restores;
		}

		if ( empty( $restore_queue ) ) {
			pb_backupbuddy::status( 'details', 'Nothing to write to restore queue. Bailing early.' );
			return false;
		}

		if ( ! file_put_contents( $this->restore_storage, wp_json_encode( $restore_queue ) ) ) {
			pb_backupbuddy::status( 'details', 'Unable to write restore queue. Check folder permissions.' );
			return false;
		}

		return true;
	}

	/**
	 * Prune restores based on max_history.
	 *
	 * @return bool  If pruned.
	 */
	private function prune() {
		if ( count( $this->restores ) <= $this->max_history ) {
			// Pruning not necessary.
			return false;
		}

		$restores = array();
		$counter  = 0;
		foreach ( $this->restores as $index => $restore ) {
			$restores[ $index ] = $restore;
			$counter++;
			if ( $counter >= $this->max_history ) {
				break;
			}
		}
		$this->restores = $restores;
		return true;
	}

	/**
	 * Determines if is full backup restore.
	 *
	 * @return bool  Is full restore.
	 */
	private function is_full_restore() {
		return '*' === $this->files;
	}

	/**
	 * Cron to process the restore queue.
	 *
	 * @return bool  If processed.
	 */
	public function process() {

		$this->get_queue();

		if ( empty( $this->restores ) ) {
			return false;
		}

		// Handle user abort early.
		if ( $this->check_for_user_abort() ) {
			$this->cron_complete( false );
			return false;
		}

		// Prevent overlapping requests.
		if ( $this->cron_in_progress() ) {
			$this->log( 'Cron in progress...' );
			$this->schedule_cron();
		}

		foreach ( $this->restores as $restore_json ) {
			$this->restore = json_decode( $restore_json, true );

			// Don't re-process anything already complete.
			if ( in_array( $this->restore['status'], $this->get_completed_statuses(), true ) ) {
				// Handle all abort types first.
				if ( $this->is_aborted() && ! $this->has_aborted() ) {
					$this->abort( $this->is_user_aborted() );
				}
				continue;
			}

			$this->prevent_stalls();

			if ( ! $this->restore_init() ) {
				$this->abort();
				return false;
			}

			if ( ! $this->restore['status'] ) {
				if ( $this->start() ) {
					$this->log( '✓ Restore started.' );
					$this->save();
				}
			}

			if ( self::STATUS_STARTED === $this->restore['status'] || self::STATUS_DOWNLOADING === $this->restore['status'] ) {
				if ( ! $this->ready() ) {
					// Not ready yet. Still downloading zip file from remote.
					$this->cron_complete();
					return false;
				}

				$this->log( '✓ Restore ready.' );
				$this->set_status( self::STATUS_READY );
				$this->cron_complete();
				return true; // Milestone.
			}

			if ( self::STATUS_READY === $this->restore['status'] ) {
				$this->set_status( self::STATUS_UNZIPPING );

				if ( ! $this->unzip() ) {
					$this->abort();
					return false;
				}

				$this->set_status( self::STATUS_UNZIPPED );
				$this->cron_complete();
				return true; // Milestone.
			}

			if ( self::STATUS_UNZIPPED === $this->restore['status'] || self::STATUS_VERIFYING === $this->restore['status'] ) {
				if ( self::STATUS_UNZIPPED === $this->restore['status'] ) {
					// Don't keep overwriting status.
					$this->set_status( self::STATUS_VERIFYING );
				}

				if ( ! $this->verify_extraction() ) {
					$this->abort();
					return false;
				}

				$this->set_status( self::STATUS_VERIFIED );
				$this->cron_complete();
				return true; // Milestone.
			}

			if ( self::STATUS_VERIFIED === $this->restore['status'] ) {
				$this->set_status( self::STATUS_PERMISSIONS );
				if ( ! $this->copy_permissions() ) {
					$this->abort();
					return false;
				}

				$this->restore['files_ready'] = true;
				if ( $this->should_restore_db() ) {
					$this->set_status( self::STATUS_DB_READY );
				} else {
					$this->restore['db_ready'] = true;
					$this->save();
				}
				$this->cron_complete();
				return true; // Milestone.
			}

			if ( self::STATUS_DB_READY === $this->restore['status'] ) {
				if ( $this->should_restore_db() ) {

					// Change status while restoring a table.
					$this->set_status( self::STATUS_DB_TABLES );

					if ( false === $this->database() ) {
						$this->cron_complete();
						return true; // Milestone.
					}
				}

				$this->restore['db_ready'] = true;
				$this->save();
				$this->cron_complete();
				return true; // Milestone.
			}

			// Once the files have been extracted and the database tables imported, we're ready to start actually changing files/tables.
			if ( $this->is_ready_to_restore() ) {
				$this->set_status( self::STATUS_RESTORING );
			}

			if ( self::STATUS_RESTORING === $this->restore['status'] ) {
				if ( false === $this->restore['file_status'] ) {
					$this->set_status( self::STATUS_RESTORING_FILES );
				} elseif ( false === $this->restore['db_status'] ) {
					$this->set_status( self::STATUS_RESTORING_DB );
				}
			}

			// Final steps. No turning back now.
			if ( self::STATUS_RESTORING_FILES === $this->restore['status'] ) {
				if ( $this->should_restore_files() && empty( $this->restore['file_status'] ) ) {
					$this->set_status( self::STATUS_COPYING );

					$this->enable_maintenance_mode();

					// Enqueue Action Scheduler jobs if we haven't already.
					if ( empty( $this->restore['files_started'] ) ) {
						$this->restore['files_started'] = true;
						$this->deactivate_plugins();
						$this->delete_destination_files();

						// Don't start moving files until deletion is complete.
						$this->enqueue_files_jobs( $this->restore['id'] );
						$this->save();

						// Take a breather before proceeding.
						$this->cron_complete();
						return true;
					}

					/*
					 * This is the point where we both start moving files and
					 * confirm all of the files have moved.
					 *
					 * Restoration starts the first time this is hit
					 * then it will continue to loop around until all files are moved.
					 * In the meantime, the async jobs are running.
					 *
					 * This will always get to run one last time to mop up.
					 */
					$files_status = $this->restore_files( $this->restore['id'] );

					if ( null === $files_status ) {
						$this->set_status( self::STATUS_RESTORING_FILES );
						$this->cron_complete();
						return true;
					}

					$this->disable_maintenance_mode();

					// Made it this far so files all exist. Move them all.
					$this->restore['file_status'] = $files_status;
				} else {
					$this->restore['file_status'] = true;
				}

				if ( false === $this->restore['file_status'] ) {
					$this->abort();
					return false;
				}

				$this->set_status( self::STATUS_RESTORING );
				$this->cron_complete();
				return true;
			}

			// Final steps. No turning back now.
			if ( self::STATUS_RESTORING_DB === $this->restore['status'] ) {
				if ( $this->should_restore_db() ) {
					$this->set_status( self::STATUS_DATABASE );

					$this->enable_maintenance_mode();

					$db_status = $this->finalize_database();
					if ( null === $db_status ) {
						$this->set_status( self::STATUS_RESTORING_DB );
						$this->cron_complete();
						return true; // Milestone.
					}

					$this->disable_maintenance_mode();

					$this->restore['db_status'] = $db_status;
				} else {
					$this->restore['db_status'] = true;
				}

				if ( false === $this->restore['db_status'] ) {
					$this->abort();
					return false;
				}

				$this->set_status( self::STATUS_RESTORING );
				$this->cron_complete();
				return true;
			}

			// Once file_status and db_status are both true, we're completely done.
			if ( $this->restore['file_status'] && $this->restore['db_status'] ) {
				// Cleanup.
				$this->set_status( self::STATUS_CLEANUP );
				$this->cleanup( false );

				$this->restore['completed'] = current_time( 'timestamp' );
				$this->restore['elapsed']   = $this->restore['completed'] - $this->restore['started'];
				$this->log( '✓ Restore finished successfully.' );
				$this->set_status( self::STATUS_COMPLETE );

				// Make sure maintenance mode is off.
				$this->disable_maintenance_mode();
			}

			$this->save();
		}

		$this->cron_complete();

		return true;
	}

	/**
	 * Try to prevent stalls in restore.
	 *
	 * @param array $restore  Restore array to load.
	 */
	public function prevent_stalls( $restore = false ) {
		if ( false !== $restore ) {
			// Load Restore File if available.
			$this->restore = $this->load_restore( $restore );
		}

		if ( ! $this->restore ) {
			return false;
		}

		$threshold = 10;
		$log_file  = backupbuddy_core::getLogDirectory() . 'backupbuddy-stall.log';
		$stall_log = array(
			'updated'  => false,
			'checksum' => false,
		);

		if ( file_exists( $log_file ) ) {
			$stall_log_contents = file_get_contents( $log_file );
			if ( json_decode( $stall_log_contents, true ) ) {
				$stall_log = json_decode( $stall_log_contents, true );
			}
		}

		// Increase counter.
		if ( empty( $stall_log[ $this->restore['id'] ][ $this->restore['status'] ] ) ) {
			$stall_log[ $this->restore['id'] ][ $this->restore['status'] ] = 0;
		}
		$stall_log[ $this->restore['id'] ][ $this->restore['status'] ]++;

		$last_checksum = $stall_log['checksum'];
		$this_checksum = md5( wp_json_encode( $this->get_js_status( $this->restore ) ) );
		$stall_count   = $stall_log[ $this->restore['id'] ][ $this->restore['status'] ];

		// Update the log.
		$stall_log['updated']  = gmdate( 'Y-m-d H:i:s' );
		$stall_log['checksum'] = $this_checksum;

		file_put_contents( $log_file, wp_json_encode( $stall_log ) );

		// If the status hasn't moved within the threshold, we may be stalled.
		if ( $last_checksum === $this_checksum && $stall_count >= $threshold ) {
			pb_backupbuddy::status( 'details', 'Restore cron maybe stalled. Rescheduling...' );
			$this->schedule_cron();
		}
	}

	/**
	 * Check if we're ready to finalize the restore.
	 *
	 * @return bool  Ready to restore.
	 */
	private function is_ready_to_restore() {
		if ( $this->should_restore_files() && ! $this->restore['files_ready'] ) {
			return false;
		}
		if ( $this->should_restore_db() && ! $this->restore['db_ready'] ) {
			return false;
		}
		if ( $this->is_aborted() ) {
			return false;
		}

		return true;
	}

	/**
	 * Trigger the restore start.
	 *
	 * @return bool  If started.
	 */
	private function start() {
		if ( $this->is_aborted() ) {
			return false;
		}

		$this->log( 'Starting Restore...' );

		// Wipe Stall log.
		$this->wipe_stall_log();

		$this->restore['started'] = current_time( 'timestamp' );
		$this->restore['status']  = self::STATUS_STARTED;

		if ( $this->should_restore_files() && is_array( $this->files ) ) {
			$this->log( 'Total Files: ' . count( $this->files ) );
		} elseif ( $this->should_restore_files() ) {
			$this->log( 'Restore Path: ' . $this->restore_path );
		}

		if ( $this->should_restore_db() && is_array( $this->tables ) ) {
			$this->log( 'Total Tables: ' . count( $this->tables ) );
		}

		return true;
	}

	/**
	 * Check to see if the zip is ready to start restoring.
	 *
	 * @return bool  If zip has been downloaded and is ready.
	 */
	private function ready() {
		if ( file_exists( $this->restore['zip_path'] ) ) {
			if ( true === $this->restore['download'] ) {
				if ( ! empty( $this->restore['zip_size'] ) ) {
					// Make sure file size is expected to ensure download is complete.
					$size     = (int) filesize( $this->restore['zip_path'] );
					$expected = (int) $this->restore['zip_size'];
					if ( $size !== $expected ) {
						$this->set_status( self::STATUS_DOWNLOADING );
						return false;
					}
				}
				$this->log( '✓ Zip download complete.' );
			}
			return true;
		}

		if ( ! empty( $this->restore['destination_args'] ) ) {
			if ( false === $this->restore['download'] ) {
				// Try to schedule the download again.
				if ( ! $this->schedule_download() ) {
					$this->error( 'Zip download failed to schedule from remote destination.' );
					$this->set_status( self::STATUS_FAILED );
				}
			} else {
				$this->set_status( self::STATUS_DOWNLOADING );
			}
		} else {
			$this->error( 'Zip file not found.' );
			$this->set_status( self::STATUS_FAILED );
		}

		$this->save();

		return false;
	}

	/**
	 * Whether to restore files or not.
	 *
	 * @return bool  If files should be restored.
	 */
	private function should_restore_files() {
		if ( empty( $this->restore ) ) {
			return false;
		}

		// Files already restored.
		if ( true === $this->restore['file_status'] ) {
			return false;
		}

		return in_array( $this->restore['what'], array( 'both', 'files' ), true );
	}

	/**
	 * Whether to restore the database or not.
	 *
	 * @return bool  If database should be restored.
	 */
	private function should_restore_db() {
		if ( empty( $this->restore ) ) {
			return false;
		}

		// Database already restored.
		if ( true === $this->restore['db_status'] ) {
			return false;
		}

		return in_array( $this->restore['what'], array( 'both', 'db' ), true );
	}

	/**
	 * Modified version of the original restore function.
	 * Loads at the start of every restore pass.
	 */
	private function restore_init() {
		// Prevent Core auto-updates during restore.
		if ( ! defined( 'WP_AUTO_UPDATE_CORE' ) ) {
			define( 'WP_AUTO_UPDATE_CORE', false );
		}

		// Load Restore File if available.
		$this->restore = $this->load_restore();

		if ( ! $this->restore ) {
			$this->error( __( 'Could not load restore data.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		// Set class properties.
		$this->archive      = $this->restore['zip_path'];
		$this->files        = $this->restore['files'];
		$this->tables       = empty( $this->restore['tables'] ) ? array() : $this->restore['tables'];
		$this->restore_path = self::trailingslashit( $this->restore['restore_path'] );
		$this->temp_dir     = ! empty( $this->restore['temp_dir'] ) ? $this->restore['temp_dir'] : '';
		$this->serial       = backupbuddy_core::parse_file( $this->archive, 'serial' );
		$this->table_prefix = 'bbrestore' . sanitize_text_field( strtolower( substr( $this->restore['id'], -4 ) ) ) . '_';

		if ( $this->should_restore_files() && empty( $this->files ) ) {
			$this->error( __( 'Restore Failed, missing files to restore.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		if ( $this->should_restore_db() && empty( $this->tables ) ) {
			$this->error( __( 'Restore Failed, missing tables to restore.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		return true;
	}

	/**
	 * Unzip files to temp directory.
	 *
	 * @return bool  If unzipped successfully.
	 */
	private function unzip() {
		$this->log( 'Starting Unzip...' );

		$this->log( 'Setting temp directory to unzip...' );

		// Calculate temp directory & lock it down.
		if ( empty( $this->temp_dir() ) ) {
			$uploads_dir               = wp_upload_dir();
			$this->restore['temp_dir'] = self::trailingslashit( $uploads_dir['basedir'] ). 'backupbuddy-restoretmp-' . $this->serial . '/';
			$this->temp_dir            = $this->restore['temp_dir'];
			if ( file_exists( $this->temp_dir ) ) {
				// Make sure directory is empty.
				$this->log( 'Emptying temp restore directory for unzipping...' );
				pb_backupbuddy::$filesystem->unlink_recursive( $this->temp_dir, true );
			} elseif ( false === pb_backupbuddy::$filesystem->mkdir( $this->temp_dir ) ) {
				$this->error( 'Error #458485945: Unable to create temporary location `' . $this->temp_dir . '`. Check permissions.' );
				return false;
			}

			$this->log( '✓ Temporary directory created: ' . $this->temp_dir );
			$this->save();
		}

		if ( ! class_exists( 'pluginbuddy_zipbuddy' ) ) {
			$this->log( '✓ Loading Zip Extractor Class.' );
			require_once pb_backupbuddy::plugin_path() . '/lib/zipbuddy/zipbuddy.php';
		}

		$zipbuddy = new pluginbuddy_zipbuddy( backupbuddy_core::getBackupDirectory() );

		$this->log( 'Generating file list...' );
		$zip_file_list = $zipbuddy->get_file_list( $this->archive );

		if ( ! $zip_file_list ) {
			$this->error( 'Failed to generate file list from zip file.' );
			return false;
		}

		$this->log( 'Extracting backup files from zip.' );

		// Generate array of literal files (no wildcards) to extract.

		// Full Restores.
		if ( $this->is_full_restore() ) {
			$path = $this->get_file_restore_safe_path();
			$this->insert_zip_files( $path, $zip_file_list );
		} else {
			// Selective Restores.
			foreach ( $this->files as $key => &$file ) {
				$is_a_dir = false !== strpos( $file, '*' );
				$file     = str_replace( '*', '', $file );
				if ( $is_a_dir ) {
					$this->insert_zip_files( $file, $zip_file_list );
				} else {
					$this->restore['extract_files'][ $file ] = $file;
				}
			}
		}

		// Only extract files that are going to be restored.
		$this->restore['extract_files'] = array_filter(
			$this->restore['extract_files'],
			array( $this, 'should_restore_path' )
		);

		/*
		 * If the user only selected files that should
		 * not be restored, abort the File Restore.
		 *
		 * The Restore will continue to process the database if requested.
		 */
		if ( ! count( $this->restore['extract_files'] ) ) {
			$this->log( 'No restorable files selected. Solid Backups and WP Core files cannot be restored.' );
			unset( $zipbuddy );
			$this->restore['file_status'] = true;
			$this->save();
			return true;
		}

		$this->log( 'Extracting zip files...' );
		$this->save();

		// Do the actual extraction.
		$extraction = $zipbuddy->extract( $this->archive, $this->temp_dir(), $this->restore['extract_files'] );

		unset( $zipbuddy );

		if ( false === $extraction ) {
			$this->error( 'Error #584984458b. Unable to extract files from Restore zip.' );
			return false;
		}

		$this->log( '✓ Zip extracted.' );

		return true;
	}

	/**
	 * Make sure all the files got extracted successfully.
	 *
	 * @return bool  If extraction was successful.
	 */
	private function verify_extraction() {
		$this->log( 'Verifying Extraction...' );

		if ( ! count( $this->restore['extract_files'] ) ) {
			$this->log( 'Files list empty. Nothing to verify.' );
			return true;
		}

		$files = glob( $this->temp_dir() . '*' );
		if ( count( $files ) <= 0 ) {
			$this->error( 'Zip extraction failed. No files found.' );
			$this->log( '× Glob of directory `' . $this->temp_dir() . '`: <pre>' . print_r( $files, true ) . '</pre>' );
			return false;
		}

		$verified = true;

		// Verify all files/folders to be extracted exist in temp directory. If any missing then delete everything and bail out.
		foreach ( $this->restore['extract_files'] as $file ) {
			if ( ! $file ) {
				continue;
			}

			if ( ! file_exists( $this->source_path( $file ) ) ) {
				$this->error( 'Could not verify extraction of file: ' . $this->source_path( $file ) );
				$verified = false;
				break;
			}
		}

		if ( ! $verified ) {
			$this->error( 'Error #854783474. One or more expected files/directories missing from zip extraction directory.' );
		} else {
			$this->log( '✓ Verification complete.' );
		}

		return $verified;
	}

	/**
	 * Insert files within subfolders in wildcard directories.
	 *
	 * @param string $folder         Wildcard folder to check.
	 * @param array  $zip_file_list  Array of files in zip.
	 */
	private function insert_zip_files( $folder, $zip_file_list ) {
		foreach ( $zip_file_list as $file_array ) {
			$file = $file_array[0];
			if ( ! $file || strlen( $file ) <= strlen( $folder ) ) {
				continue;
			}
			if ( '/' === substr( $file, -1 ) ) { // Don't verify empty folder names.
				continue;
			}
			if ( substr( $file, 0, strlen( $folder ) ) !== $folder ) {
				continue;
			}
			if ( in_array( $file, $this->get_ignore_files(), true ) || in_array( '*' . basename( $file ), $this->get_ignore_files(), true ) ) {
				continue;
			}
			$this->restore['extract_files'][ $file ] = $file;
		}
	}

	/**
	 * Deactivate active plugins.
	 *
	 * Records active plugins so they can be reactivated later.
	 *
	 * @todo need to decide how to handle Network plugins.
	 */
	private function deactivate_plugins() {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Get file paths.
		$plugins = get_option( 'active_plugins' );
		$files   = $this->restore['extract_files'];

		$plugins = array_filter( $plugins, function( $plugin ) use ( $files ) {
			// Don't deactivate Solid Backups.
			if ( false !== strpos( $plugin, 'backupbuddy' ) ) {
				return false;
			}

			// Don't deactivate plugins that are not being restored.
			foreach ( $files as $file ) {
				if ( false !== strpos( $plugin, $file ) ) {
					return false;
				}
			}

			return true;
		} );

		if ( empty( $plugins ) ) {
			return;
		}

		// Store for later.
		$this->restore['active_plugins'] = $plugins;

		// Don't call deactivation hooks.
		deactivate_plugins( $plugins, true );
		$this->log( '✓ Active Plugins Deactivated: ' . print_r( $plugins, true ) );
	}

	/**
	 * Reactivate plugins.
	 */
	private function reactivate_plugins() {
		if (
			empty( $this->restore['active_plugins'] )
			|| ! is_array( $this->restore['active_plugins'] )
		) {
			return;
		}

		if ( ! function_exists( 'activate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Only reactivate plugins that still exist after the restore.
		$this->restore['active_plugins'] = array_filter( $this->restore['active_plugins'], function( $plugin ) {
			return file_exists( WP_PLUGIN_DIR . '/' . $plugin );
		} );

		if ( empty( $this->restore['active_plugins'] ) ) {
			$this->log( '✓ No Plugins to Reactivate after files restored.');
			return;
		}

		// Don't call activation hooks.
		$result = activate_plugins( $this->restore['active_plugins'], '', false, true );

		if ( is_wp_error( $result ) ) {
			$this->error(
				sprintf(
					'Error Reactivating Plugins. Message: %s',
					$result->get_error_message()
				)
			);
		} elseif ( true !== $result ) {
			$this->error( 'Error Reactivating Plugins.' );
			return;
		}

		$this->log( '✓ Plugins Reactivated: ' . print_r( $this->restore['active_plugins'], true ) );

	}

	/**
	 * Find the relative path we should used based on the backup type.
	 *
	 * This is designed to locate the correct path in the backup zip,
	 * as well as protect files outside of the wp-content dir from deletion.
	 *
	 * @return string A relative path to the safe directory.
	 */
	private function get_file_restore_safe_path() {
		// Media, Plugins, & Themes backup Types are in the root of the backup zip.
		$backup_type    = backupbuddy_core::parse_file( $this->restore['zip_path'], 'type' );
		$special_types  = array( 'media', 'plugins', 'themes' );
		return in_array( $backup_type, $special_types, true ) ? '' : 'wp-content/';
	}

	/**
	 * Enqueue Action Scheduler jobs for chunks of files.
	 *
	 * @param int $id  Restore ID.
	 */
	private function enqueue_files_jobs( $id ) {
		$chunks = 0;

		if ( $this->is_full_restore() ) {
			$chunks = self::count_chunks_in_dir( $this->temp_dir() );
		} else {
			$single_files = 0;
			foreach ( $this->files as $rel_path ) {
				// Format the file path.
				$rel_path = str_replace( '*', '', $rel_path );
				if ( ! $rel_path ) {
					continue;
				}

				$src_path = $this->source_path( $rel_path );

				if ( is_file( $src_path ) ) {
					$single_files++;
					continue;
				}

				$add_chunks = self::count_chunks_in_dir( $src_path );
				$chunks     = $chunks + $add_chunks;
			}

			if ( $single_files ) {
				// Add chunks for any files in our top-level directory.
				$addl_chunk = ceil( $ $single_files / self::FILE_BATCH_SIZE );
				$chunks     = $chunks + $addl_chunk;
			}
		}

		// Sanity Check.
		if ( $chunks > 0 && $chunks <= 50 ) {
			for ( $i = 0; $i < $chunks; $i++ ) {
				backupbuddy_core::trigger_async_event( 'restore_files', array( $id, $i ) );
			}
		}
	}

	/**
	 * Count the number of chunks in a directory.
	 *
	 * @param string $dir  Directory to count chunks in.
	 *
	 * @return int  Number of chunks.
	 */
	private static function count_chunks_in_dir( $dir ) {
		if ( empty( $dir ) || ! is_dir( $dir ) ) {
			return 0;
		}

		$files = self::get_dir_files( $dir );
		return ceil( count( $files ) / self::FILE_BATCH_SIZE );
	}

	/**
	 * Delete files before restoring.
	 *
	 * @return bool  True on success, false on failure.
	 */
	function delete_destination_files() {
		if ( $this->is_full_restore() ) {
			$dest_paths = $this->get_full_delete_paths();
		} else {
			$dest_paths = $this->get_selective_delete_paths();
		}

		if ( empty( $dest_paths ) || ! is_array( $dest_paths ) ) {
			$this->log( '✓ No Destination Files to delete.' );
			return true;
		}

		// This seems superfluous, but we need it to preserve the temp dir itself.
		$dest_paths = array_filter( $dest_paths, function( $path ) {
			return ( false === strpos( strtolower( $path ), 'backupbuddy' ) );
		} );

		$dest_paths = array_filter( $dest_paths, array( $this, 'should_restore_path' ) );
		$success    = $this->delete_dest_paths( $dest_paths );

		if ( $success ) {
			$this->log( '✓ Destination Files Deleted.' );
		} else {
			$this->error( 'Error deleting Destination files.' );
		}

		return $success;
	}

	/**
	 * Get the Files to delete when running a Full Restore.
	 *
	 * This simply selects all the files in the destination directory. It will
	 * be refined in a later step.
	 *
	 * @return array  Array of file paths.
	 */
	function get_full_delete_paths() {
		if ( ! $this->is_full_restore() ) {
			// Safety Check.
			return array();
		}

		$path       = $this->get_file_restore_safe_path();
		$dest_files = self::get_dir_files( $this->destination_path( $path ) );

		// Only going to use the paths.
		return array_keys( $dest_files );
	}

	/**
	 * Get the files to delete when running a Selective Restore.
	 *
	 * We will only delete destination files that are in the $this->files
	 * array, not simply everything found in the temp directory.
	 *
	 * $this->files is the only way for us to know the user's intent -- if a directory
	 * should be deleted or if just some of the files within it will need deletion.
	 * For instance, a directory may exist in the temp dir, but those may just be
	 * hand-picked files, not a representation of the entire directory.
	 *
	 * With this in mind we must carefully select the files to delete, which involves
	 * comparing the source and destination directories.
	 *
	 * @return array  Array of destination file paths to be deleted.
	 */
	protected function get_selective_delete_paths() {
		if ( $this->is_full_restore() ) {
			return array();
		}

		$dest_items = array();

		foreach ( $this->files as $rel_path ) {
			// Directories end with wildcard symbols at this point.
			$is_a_dir = false !== strpos( '*', $rel_path );
			$rel_path = str_replace( '*', '', $rel_path );

			if ( empty( $rel_path ) ) {
				continue;
			}

			$src_path  = $this->source_path( $rel_path );
			$dest_path = $this->destination_path( $rel_path );

			if ( ! file_exists( $src_path ) || ! file_exists( $dest_path ) ) {
				continue;
			}

			// Add this item to the deletion array.
			$dest_items[] = $dest_path;

			// Add contents of any user-selected directories to the array.
			if ( $is_a_dir ) {

				// Get all subdirectories and files in this directory.
				$src_paths = array_keys( self::get_dir_files( $src_path ) );

				// Locate the destination item and add it to the deletion array.
				foreach ( $src_paths as $src_path ) {
					$dest_path = $this->destination_path( $src_path );

					if ( ! file_exists( $dest_path ) ) {
						continue;
					}

					$dest_items[] = $dest_path;
				}
			}
		}

		return $dest_items;
	}

	/**
	 * Remove files from the destination directory.
	 *
	 * This is a complicated procedure, as it's not as simple as just
	 * deleting everything. There are a essential files that need to be preserved,
	 * and we can't simply skip them individually, as their parent directories might
	 * be deleted later if we are not careful.
	 *
	 * Additionally, when doing a Selective Restore some contents of directories
	 * will need to be deleted, but perhaps not all.
	 *
	 * Extensive documenatation provided here for clarity.
	 */
	protected function delete_dest_paths( $dest_paths ) {
		$success = true;
		$preserve_parents = array();

		// Put the deepest subdirectories/files at the top of the array.
		usort( $dest_paths, function( $a, $b ) {
			return substr_count( $b, '/' ) - substr_count( $a, '/' );
		});

		foreach ( $dest_paths as $dest_path ) {

			// We've already marked this for preservation.
			if ( in_array( $dest_path, $preserve_parents, true ) ) {
				continue;
			}

			/*
			 * If this is an important file, skip it, and
			 * store its parent path so the parent doesn't get
			 * deleted in this loop. We'll re-check the parent later.
			 */
			if ( ! $this->should_restore_path( $dest_path ) ) {
				$preserve_parents[] = dirname( $dest_path );
				continue;
			}

			if ( is_file( $dest_path ) ) {
				unlink( $dest_path );
			} elseif ( is_dir( $dest_path ) && self::is_empty_dir( $dest_path ) ) {
				rmdir( $dest_path );
			}
		}

		/*
		 * Now, starting at the most deeply-nested dirs, re-check
		 * the preserved dirs and mop up any empty ones.
		 */
		foreach ( $preserve_parents as $path ) {
			if ( is_dir( $path ) && self::is_empty_dir( $path ) ) {
				rmdir( $path );
			}
		}

		// @todo Add some error handling here.
		return $success;
	}

	/**
	 * Restore files from restore tmp (src) folder to (dest) folder.
	 *
	 * Prepare for recursive directory copy.
	 *
	 * @param int $id         Restore ID.
	 * @param int $iteration  Iteration number. Used for creating unique Action Scheduler jobs.
	 *
	 * @return bool If copied successfully. Null means there are still more files to copy later.
	 */
	public function restore_files( $id, $iteration = 0) {
		if ( empty( $this->restore ) ) {
			$this->restore = $this->load_restore( array( 'id' => $id ) );
		}

		$success = false;

		// Log file may be unavailable during file moving.
		if ( ! count( $this->restore['moved'] ) ) {
			$this->log( 'Restoring files...' );
		}

		if ( $this->is_full_restore() ) {
			// Full backup restore.
			$source_files = $this->full_restore_files();
		} elseif ( $this->files ) {
			// Selective backup restore.
			$source_files = $this->selective_restore_files();
		}

		if ( empty( $source_files ) || ! is_array( $source_files ) ) {
			$this->log( 'No backup files found.' );
		} else {
			$this->log( 'Continuing restore.' );
		}

		$success = $this->restore_files_splfileinfo(array_keys( $source_files ) );

		if ( ! empty( $success ) ) {
			$this->log( '✓ Files restored successfully.' );
		}

		$this->save();

		return $success;
	}

	/**
	 * Restore files selectively.
	 *
	 * This is when the user specifies which files to restore, as opposed
	 * to a Full Restore.
	 *
	 * @return array Array of SplFileInfo objects.
	 */
	function selective_restore_files() {
		/*
		 * Gather all files to be moved.
		 * This array will consist of SplFileInfo objects.
		 * The array key is the path to the source file.
		 */
		$source_files = array();

		foreach ( $this->files as $rel_path ) {
			// Directories end with wildcard symbols at this point.
			$rel_path = str_replace( '*', '', $rel_path );
			$src_path = $this->source_path( $rel_path );

			// Add this path item to the array.
			$src_item = new SplFileInfo( $src_path );
			if ( $src_item->isDir() || $src_item->isFile() ) {
				$source_files[ $src_path ] = $src_item;
			}

			// Add contents of any directories to the array.
			if ( $src_item->isDir() ) {

				// Get all subdirectories and files in this directory.
				$dir_files    = self::get_dir_files( $src_item->getPathname() );
				$source_files = $source_files + $dir_files;
			}
		}
		return $source_files;

	}

	/**
	 * Restore all files.
	 *
	 * @return array  Array of SplFileInfo objects representing files to move.
	 */
	function full_restore_files() {
		// Restore everything in the temp directory.
		return self::get_dir_files( $this->temp_dir() );
	}

	/**
	 * Move files from the unzip directory to the restore directory.
	 *
	 * The input array is an array of SplFileInfo objects, keyed with the path to the temp dir file.
	 * This expects the array to be sorted by subdirectory depth, deepest first.
	 *
	 * @param array $files  Array of SplFileInfo objects representing files to move.
	 *
	 * @return bool  If files were moved successfully.
	 */
	function restore_files_splfileinfo( $src_paths ) {

		$moved_count = 0;

		foreach ( $src_paths as $src_path ) {

			// Prevent this array from growing too large.
			$this->restore['moved'] = array_unique( $this->restore['moved'] );

			// Maybe end this batch. Needs to be before continue statements.
			if ( $moved_count >= self::FILE_BATCH_SIZE ) {
				$this->save();
				return null;
			}

			$rel_path  = $this->relative_path( $src_path );
			$dest_path = $this->destination_path( $src_path );

			if ( in_array( $rel_path, $this->restore['moved'], true ) ) {
				// Already moved/created.
				continue;
			}

			if ( ! $this->should_restore_path( $src_path ) ) {
				continue;
			}

			if ( is_dir( $src_path ) ) {

				if ( is_dir( $dest_path ) ) {
					$this->restore['moved'][] = $rel_path;
					$moved_count++;
					continue;
				}

				// Create the directory (files will follow).
				pb_backupbuddy::$filesystem->mkdir( $dest_path );
				$this->restore['moved'][] = $rel_path;
				$moved_count++;

			} elseif ( is_file( $src_path ) ) {

				// Create this file's parent directory if it doesn't exist.
				$parent_dir = dirname( $dest_path );
				$rel_parent_dir = $this->relative_path( $parent_dir );
				if ( ! file_exists( $parent_dir ) && ! in_array( $rel_parent_dir, $this->restore['moved'], true ) ) {
					pb_backupbuddy::$filesystem->mkdir( $parent_dir );
					$this->restore['moved'][] = $rel_parent_dir;
					$moved_count++;
				}

				// This must be checked late in execution.
				if ( ! file_exists( $src_path ) ) {
					$this->restore['moved'][] = $rel_path;
					$moved_count++;
					continue;
				}

				// This must be checked as late as possible in execution.
				if ( $this->is_full_restore() && file_exists( $dest_path ) ) {
					// Already moved.
					if ( file_exists( $src_path ) ) {
						unlink( $src_path );
					}
					$this->restore['moved'][] = $rel_path;
					$moved_count++;
					continue;
				} elseif ( file_exists( $dest_path ) ) {
					// Selective Restore. File already exists.
					unlink( $dest_path );
					$this->restore['moved'][] = $rel_path;
					$moved_count++;
				}

				// Move the file from the source to the destination.
				if ( rename( $src_path, $dest_path ) ) {

					if ( $this->file_rename_successful( $dest_path ) ) {
						$moved_count++;
					} else {
						return false;
					}
				} else {
					sleep(2);
					// Try one more time. Files are moving quickly.
					if ( ! file_exists( $src_path ) && $this->file_rename_successful( $dest_path ) ) {
						$this->restore['moved'][] = $rel_path;
						$moved_count++;
						continue;
					}
					$this->error(
						sprintf(
							'Error #9035. Unable to move `%1$s` to `%2$s`.
							' . PHP_EOL . 'Please retry the Restore again and/or verify file permissions.',
							esc_html( $src_path ),
							esc_html( $dest_path )

						)
					);
					return false;
				}
			} else {
				// Already moved.
				$this->restore['moved'][] = $rel_path;
			}
		}

		$this->save();

		return true;
	}

	/**
	 * Handle a file rename.
	 *
	 * If successful, add the file to the list of moved files.
	 * Else, record the error.
	 *
	 * @param string $path  Path to (destination) file.
	 *
	 * @return bool  If file was moved successfully.
	 */
	private function file_rename_successful( $path ) {
		// Verify the file was moved successfully.
		if ( file_exists( $path ) ) {
			$this->restore['moved'][] = $this->relative_path( $path );
			$this->save();
			return true;
		}
		$this->error(
			sprintf(
				'File moved but failed integrity check: %s',
				esc_html( $path )
			)
		);
		return false;
	}

	/**
	 * Recursively get files in a directory.
	 *
	 * The items at the top of this array will be those
	 * most deeply nested in the directory.
	 *
	 * This is used to quickly get data on all files in a directory.
	 * It is used during file deletion and file restoration.
	 *
	 * File deletion uses this only to get the paths of files to delete
	 * by getting the array keys.
	 *
	 * @param string $dir  Directory to get files from.
	 *
	 * @return array  Array of SplFileInfo objects, keyed with the path to the file.
	 */
	private static function get_dir_files( $dir ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(
				$dir,
				RecursiveDirectoryIterator::SKIP_DOTS
			),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		/*
		 * Freeze the state of the iterator as it would
		 * otherwise constantly morph as we modify files
		 * and run forever.
		 */
		return iterator_to_array( $iterator );
	}

	/**
	 * Ensure a trailing slash is present.
	 *
	 * We use this because this file might be used by the
	 * Sandalone Importer (importbudy) outside of a WP install.
	 *
	 * @param string $path  Path to add trailing slash to.
	 *
	 * @return string  Path with trailing slash.
	 */
	public static function trailingslashit( $path ) {
		if ( function_exists( 'trailingslashit' ) ) {
			return trailingslashit( $path );
		}
		return rtrim( $path, '/' ) . '/';
	}

	/**
	 * Ensure no trailing slash is present.
	 *
	 * We use this because this file might be used by the
	 * Sandalone Importer (importbudy) outside of a WP install.
	 *
	 * @param string $path  Path to remove a trailing slash from.
	 *
	 * @return string  Path without trailing slash.
	 */
	public static function untrailingslashit( $path ) {
		if ( function_exists( 'untrailingslashit' ) ) {
			return untrailingslashit( $path );
		}
		return rtrim( $path, '/' );
	}

	/**
	 * Create path to a file in the temp directory.
	 *
	 * @param string $path  Relative path to file.
	 *
	 * @return string  Full path to file.
	 */
	public function source_path( $path ) {
		$path = $this->relative_path( $path );
		return $this->temp_dir() . $path;
	}

	/**
	 * Get the destination path for a file.
	 *
	 * @param string $path  File path to get destination for.
	 *
	 * @return string  Destination path.
	 */
	public function destination_path( $path = '' ) {
		$path = $this->relative_path( $path );
		return $this->restore_path . $path;
	}

	/**
	 * Get the relative path for a file.
	 *
	 * This is used for normalization. Provided file paths
	 * will be forematted to look like they do relative
	 * to the WP Root directory (ABSPATH).
	 *
	 * Examples:
	 *  - wp-content/uploads/2018/01/image.jpg
	 *  - wp-content/themes/twentyseventeen/style.css
	 *  - wp-config.php
	 *
	 * Removes the temp path and the destination path and
	 * returns the relative path.
	 *
	 * @param string $path  Path to get relative path for.
	 *
	 * @return string  A relative path.
	 */
	public function relative_path( $path ) {
		$rel_path = str_replace( $this->temp_dir(), '', $path );
		$rel_path = str_replace( $this->restore_path, '', $rel_path );
		return ltrim( $rel_path, '/' );
	}

	/**
	 * Determine if a directory is empty.
	 *
	 * @param string $path  Path to check.
	 *
	 * @return bool  If directory is empty.
	 */
	public static function is_empty_dir( $path ) {
		return ( is_dir( $path ) && count( scandir( $path ) ) === 2 );
	}


	/**
	 * Determine if a file or directory should be Restored.
	 *
	 * Briefly put, the intent is to prevent WP Core and Solid Backups
	 * files from being deleted/modified during Restore.
	 *
	 * To maintain versatility, file paths here are basic WP Core paths.
	 * These may be found in the WP root (ABSPATH) or
	 * in the "root" of the temp/restore directory after files are extracted
	 * from the backup zip file.
	 *
	 * @param string $relative_path  File path to check.
	 *
	 * @return bool  If file should be ignored.
	 */
	public function should_restore_path( $path ) {
		$path = $this->relative_path( $path );

		// Check if the user has specified any files to ignore.
		if (
			in_array( $path, $this->get_ignore_files(), true )
			|| in_array( '*' . basename( $path ), $this->get_ignore_files(), true )
		) {
			return false;
		}

		/*
		 * Always allow .sql files to be restored.
		 *
		 * This lets sql files be extracted and processed
		 * when doing a database/full restore.
		 */
		if ( false !== strpos( strtolower( $path ), '.sql' ) ) {
			return true;
		}

		/*
		 * Ignore any Solid Backups files.
		 * This includes this plugin, as well as any related
		 * files/directories found inside of wp-content/uploads.
		 */
		if ( false !== strpos( strtolower( $path ), 'backupbuddy' ) ) {
			return false;
		}

		// Ignore mu-plugins.
		if ( false !== strpos( strtolower( $path ), 'mu-plugins' ) ) {
			return false;
		}

		$root_files = array(
			'wp-activate.php',
			'wp-blog-header.php',
			'wp-comments-post.php',
			'wp-config-sample.php',
			'wp-config.php',
			'wp-cron.php',
			'wp-links-opml.php',
			'wp-load.php',
			'wp-login.php',
			'wp-mail.php',
			'wp-settings.php',
			'wp-signup.php',
			'wp-trackback.php',
			'xmlrpc.php',
		);

		/*
		 * These file names are specifically found in the root directory.
		 *
		 * We don't want to ignore files like this:
		 * wp-content/plugins/plugin/xmlrpc.php
		 */
		if ( in_array( $path, $root_files, true ) ) {
			return false;
		}

		/*
		 * This regex matches strings that may or may not start with a slash,
		 * but will start with wp-admin or wp-includes. Additionally, it may or
		 * may not end with a slash.
		 * It does not match anything in the wp-content directory.
		 *
		 * Example of ignored from Restoration:
		 * Anything starting with wp-admin or wp-admin/.
		 *
		 * Not ignored:
		 * wp-content/themes/theme/scripts/wp-admin.js
		 */
		if ( preg_match( '/^\/?(wp-admin|wp-includes)\/?/', $path ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Correct file permissions (if necessary).
	 *
	 * @return bool  If successful or not.
	 */
	private function copy_permissions() {
		$this->log( 'Setting file/folder permissions...' );

		if ( ! is_dir( $this->temp_dir() ) || self::is_empty_dir( $this->temp_dir() ) ) {
			$this->log( 'Unable to copy permissions. No extracted files found.' );
			return true;
		}
		$success    = true;
		$fails      = array();
		$temp_paths = array_keys( self::get_dir_files( $this->temp_dir() ) );

		foreach( $temp_paths as $rel_path ) {
			$src_path  = $this->source_path( $rel_path );
			$dest_path = $this->destination_path( $rel_path );
			$dest_path = file_exists( $dest_path ) ? $dest_path : false;

			if ( ! $this->set_wp_permission( $src_path, $dest_path ) ) {
				$success = false;
				$fails[] = $rel_path;
			}
		}

		if ( count( $fails ) ) {
			$this->error( '× Error setting permissions for the following files:' );
			foreach( $fails as $file ) {
				$this->log( '× ' . $file );
			}
		} elseif ( $success ) {
			$this->log( '✓ Permissions set successfully.' );
		}

		$this->save();

		return $success;
	}

	/**
	 * Set Permissions based on WP Permission Scheme.
	 *
	 * @param string    $full_path  Full path to file or folder.
	 * @param string    $copy_path  Mirrored path to copy permissions from.
	 * @param int|false $owner      Owner ID to use.
	 * @param int|false $group      Group ID to use.
	 */
	private function set_wp_permission( $full_path, $copy_path = false, $owner = false, $group = false ) {
		if ( ! file_exists( $full_path ) ) {
			return false;
		}

		// We're all set. No need to adjust anything.
		if ( is_writable( $full_path ) ) {
			return true;
		}

		$perms_status = false;
		$owner_status = false;
		$group_status = false;
		$current_mode = (string) substr( sprintf( '%o', fileperms( $full_path ) ), -4 );

		if ( false === $perms_status ) {
			$perm = false;

			if ( false !== $copy_path ) {
				if ( file_exists( $copy_path ) ) {
					$copy_mode = (string) substr( sprintf( '%o', fileperms( $copy_path ) ), -4 );
					if ( $current_mode === $copy_mode ) {
						$perm = true; // Don't change anything.
					} else {
						// Don't copy 777 permissions.
						if ( ! in_array( $copy_mode, array( '0777', '1777' ), true ) ) {
							$perm = octdec( $copy_mode );
						}
					}
				} elseif ( decoct( (int) octdec( $copy_path ) ) === $copy_path ) {
					// ocdec returns a float, so we need to convert it to an int for decoct.
					$perm = $copy_path;
				}
			}

			if ( false === $perm ) {
				$modes = array(
					'standard' => array(
						'file'      => 0644,
						'folder'    => 0755,
						'wp-config' => 0600,
					),
					'loose'    => array(
						'file'      => 0664,
						'folder'    => 0775,
						'wp-config' => 0660,
					),
					'strict'   => array(
						'file'      => 0640,
						'folder'    => 0750,
						'wp-config' => 0400,
					),
				);

				$default = ! empty( pb_backupbuddy::$options['default_restores_permissions'] ) ? pb_backupbuddy::$options['default_restores_permissions'] : 'standard';
				$mode    = apply_filters( 'backupbuddy_restore_permission_mode', $default );
				if ( ! isset( $modes[ $mode ] ) ) {
					$mode = 'standard';
				}

				if ( is_dir( $full_path ) ) {
					$perm = $modes[ $mode ]['folder'];
				} elseif ( 'wp-config.php' === basename( $full_path ) ) {
					$perm = $modes[ $mode ]['wp-config'];
				} else {
					$perm = $modes[ $mode ]['file'];
				}
			}

			if ( true === $perm ) {
				$perms_status = true;
			} else {
				$new_mode = (string) sprintf( '%o', $perm );
				if ( $current_mode === $new_mode ) {
					$perms_status = true;
				} else {
					// Track permission changes.
					$this->restore['perms'][ $full_path ] = $new_mode;
					$perms_status = @chmod( $full_path, $perm );
					if ( ! $perms_status ) {
						$this->restore['perm_fails'][ $full_path ] = $new_mode;
						$this->log( '× Could not set mode for: `' . $full_path . '` from `' . $current_mode . '` to `' . sprintf( '%o', $perm ) . '`' );
					}
				}
			}
		}

		if ( false !== $copy_path ) {
			if ( false === $owner && file_exists( $copy_path ) ) {
				$owner = fileowner( $copy_path );
			}
			if ( false === $group && file_exists( $copy_path ) ) {
				$group = filegroup( $copy_path );
			}
		}

		if ( is_int( $owner ) ) {
			$current_owner = fileowner( $full_path );
			if ( $owner !== $current_owner ) {
				$owner_status = @chown( $full_path, $owner );
				if ( ! $owner_status ) {
					$this->log( '× Could not set owner for: `' . $full_path . '` from `' . $current_owner . '` to `' . $owner . '`' );
				}
			} else {
				$owner_status = true;
			}
		} else {
			$owner_status = true;
		}

		if ( is_int( $group ) ) {
			$current_group = filegroup( $full_path );
			if ( $group !== $current_group ) {
				$group_status = @chgrp( $full_path, $group );
				if ( ! $group_status ) {
					$this->log( '× Could not set group for: `' . $full_path . '` from `' . $current_group . '` to `' . $group . '`' );
				}
			} else {
				$group_status = true;
			}
		} else {
			$group_status = true;
		}

		return ( $perms_status && $owner_status && $group_status );
	}

	/**
	 * Triggers a user abort.
	 *
	 * @param array $restore  Restore array.
	 */
	public function user_abort( $restore ) {
		// Load Restore File if available.
		$this->restore = $this->load_restore( $restore );

		if ( ! $this->restore ) {
			$this->log( '× Could not load restore during user abort.' );
			return false;
		}

		$abort_dir  = backupbuddy_core::getLogDirectory();
		$abort_file = 'backupbuddy-restore-abort.nfo';
		$abort_path = self::trailingslashit( $abort_dir ) . $abort_file;
		if ( ! file_exists( $abort_path ) ) {
			$abort = fopen( $abort_path, 'w' );

			// Wipe file first.
			if ( is_resource( $abort ) ) {
				fwrite( $abort, '' );
			}

			fwrite( $abort, $this->restore['id'] );
			fclose( $abort );
		}

		$this->restore['status'] = self::STATUS_USER_ABORTED;
		$this->restore['viewed'] = current_time( 'timestamp' );
		$this->save();

		return $this->restore;
	}

	/**
	 * Check for user abort file.
	 *
	 * @return bool  If valid user abort was found.
	 */
	public function check_for_user_abort() {
		$abort_file = backupbuddy_core::getLogDirectory() . 'backupbuddy-restore-abort.nfo';
		if ( ! file_exists( $abort_file ) ) {
			return false;
		}

		$restore_id = file_get_contents( $abort_file );
		if ( ! $restore_id ) {
			return false;
		}

		$this->restore = $this->details( $restore_id );
		if ( $this->has_aborted() ) {
			return false;
		}

		$this->abort( true );
		return true;
	}

	/**
	 * Remove abort file for fresh restore.
	 *
	 * @return bool  If it was removed.
	 */
	public function remove_abort_file() {
		$abort_file = backupbuddy_core::getLogDirectory() . 'backupbuddy-restore-abort.nfo';
		if ( file_exists( $abort_file ) ) {
			@unlink( $abort_file );
		}

		return file_exists( $abort_file );
	}

	/**
	 * Check if restore is user initiated.
	 *
	 * @return bool  If aborted by user.
	 */
	public function is_user_aborted() {
		if ( self::STATUS_USER_ABORTED === $this->restore['status'] ) {
			return true;
		}
		if ( file_exists( backupbuddy_core::getLogDirectory() . 'backupbuddy-restore-abort.nfo' ) ) {
			// Make sure status is updated.
			$this->restore['status'] = self::STATUS_USER_ABORTED;
			return true;
		}

		return false;
	}

	/**
	 * Changes the status of the current restore.
	 *
	 * @param int $new_status  New status code.
	 */
	private function set_status( $new_status ) {
		$restore = $this->refresh( false );
		if ( $this->is_aborted( $restore ) ) {
			// Refresh the stored status.
			$new_status = $restore['status'];
			return false;
		}

		if ( $this->restore['status'] !== $new_status ) {
			$this->restore['status'] = $new_status;
			$this->save();
		}
	}

	/**
	 * Refresh Queue and Restore array.
	 *
	 * @param bool $change_restore  Change stored restore value.
	 *
	 * @return array|bool  Restore array or True on success.
	 */
	private function refresh( $change_restore = true ) {
		$this->get_queue( true );
		if ( ! empty( $this->restore ) ) {
			$restore = $this->details( $this->restore['id'] );
			if ( true === $change_restore ) {
				$this->restore = $restore;
			}
			return $restore;
		}
		return true;
	}

	/**
	 * Checks if status is aborted.
	 *
	 * @param array $restore  Restore array to check.
	 *
	 * @return bool  If aborted or not.
	 */
	private function is_aborted( $restore = false ) {
		$aborted = false;
		$restore = ! empty( $restore ) ? $restore : $this->restore;

		if ( empty( $restore ) ) {
			return $aborted;
		}

		if ( ! empty( $restore['aborted'] ) ) {
			$aborted = true;
		} elseif ( self::STATUS_ABORTED === $restore['status'] ) {
			$aborted = true;
		}

		if ( ! $aborted ) {
			$aborted = $this->is_user_aborted();
		}

		return $aborted;
	}

	/**
	 * Check if restore has been properly aborted.
	 *
	 * @return bool  If aborted.
	 */
	public function has_aborted() {
		if ( ! isset( $this->restore['aborted'] ) ) {
			$this->restore = $this->load_restore();
		}
		if ( empty( $this->restore['aborted'] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Clean up extraction, logging any error messages.
	 *
	 * @param bool $forced  User forced abort.
	 */
	private function abort( $forced = false ) {
		global $wpdb;

		// Make sure we have a full restore array.
		if ( empty( $this->restore['log'] ) ) {
			$this->restore = $this->load_restore();
		}

		// Clean up tmp folder.
		if ( ! empty( $this->temp_dir() ) ) {
			pb_backupbuddy::$filesystem->unlink_recursive( $this->temp_dir() );
		}

		$this->reactivate_plugins();

		if ( true === $forced ) {
			// Only write to log one time.
			if ( empty( $this->restore['aborted'] ) ) {
				$this->restore['aborted'] = current_time( 'timestamp' );
				$this->log( 'Restore Aborted by user.' );
			}
			$this->set_status( self::STATUS_USER_ABORTED );
		} elseif ( $this->is_aborted() ) {
			if ( empty( $this->restore['aborted'] ) ) {
				$this->restore['aborted'] = current_time( 'timestamp' );
			}
			$this->set_status( self::STATUS_ABORTED );
		} else {
			$this->log( 'Restore Failed.' );
			$this->set_status( self::STATUS_FAILED );
		}

		$this->restore['completed'] = current_time( 'timestamp' );
		if ( ! empty( $this->restore['started'] ) ) {
			$this->restore['elapsed'] = $this->restore['completed'] - $this->restore['started'];
		}

		// Delete local backup if downloaded from remote.
		if ( true === $this->restore['download'] ) {
			if ( backupbuddy_backups()->exists( $this->restore['backup_file'] ) ) {
				if ( backupbuddy_backups()->delete( $this->restore['backup_file'], true ) ) {
					$this->log( '✓ Deleted local copy of remote backup zip file.' );
				} else {
					$this->log( '× Could not delete local copy of remote backup zip file.' );
				}
			}
		}

		$this->cleanup_db();

		$this->save();

		$this->disable_maintenance_mode();
		$this->cron_complete();
	}

	/**
	 * Perform cleanup queries.
	 */
	public function cleanup_db() {
		if ( ! empty( $this->restore['cleanup_db'] ) ) {
			global $wpdb;
			$this->enable_wpdb_errors();

			$this->log( 'Processing database cleanup queries...' );
			$success = true;
			foreach ( $this->restore['cleanup_db'] as $label => $query ) {
				if ( ! $query ) {
					continue;
				}
				if ( false === $wpdb->query( $query ) ) {
					$this->log_wpdb_error( '× Database cleanup query failed: ' . $label );
					$success = false;
				} else {
					// Prevent accidental re-runs.
					$this->restore['cleanup_db'][ $label ] = false;
				}
			}

			if ( $success ) {
				$this->log( '✓ Database cleanup complete.' );
			}
		}
	}

	/**
	 * Post restore cleanup.
	 *
	 * Leave debugging should only be set to false whenever restore has completed successfully.
	 *
	 * @param bool $leave_debugging  Should debugging items remain.
	 */
	private function cleanup( $leave_debugging = true ) {
		$this->log( 'Performing cleanup...' );
		$files_successful = $this->restore['file_status'];
		$db_successful    = $this->restore['db_status'];

		// Remove the unzipped backup temp directory.
		if ( ! empty( $this->temp_dir() ) && file_exists( $this->temp_dir() ) ) {
			if ( false === pb_backupbuddy::$filesystem->unlink_recursive( $this->temp_dir() ) ) {
				$this->log( '× Unable to delete temporary holding directory `' . $this->temp_dir() . '`.' );
				$error = error_get_last();
				$this->log( '× Error was: ' . $error['message'] );
			} else {
				$this->log( '✓ Cleaned up temporary files created from zip extraction.' );
			}
		}

		$this->reactivate_plugins();
		self::housekeeping();

		// Cleanup File Restore.
		if ( true === $files_successful ) {
			$this->restore['extract_files'] = count( $this->restore['extract_files'] );
			$this->log( '✓ Cleaned up restore temporary files.' );
		}

		// Delete local backup if downloaded from remote.
		if ( true === $this->restore['download'] ) {
			if ( backupbuddy_backups()->delete( $this->restore['backup_file'], true ) ) {
				$this->log( '✓ Deleted local copy of remote backup zip file.' );
			} else {
				$this->log( '× Could not delete local copy of remote backup zip file.' );
			}
		}

		$this->files  = array();
		$this->tables = array();

		// Finalize Database changes.
		if ( ! empty( $this->restore['finalize_db'] ) ) {
			global $wpdb;
			$this->enable_wpdb_errors();

			$this->log( 'Processing final database queries.' );
			$success = true;
			foreach ( $this->restore['finalize_db'] as $label => $query ) {
				if ( false === $wpdb->query( $query ) ) {
					$this->log_wpdb_error( '× Database finalization query failed: ' . $label );
					$success = false;
				}
			}

			if ( $success ) {
				$this->log( '✓ Database finalization complete.' );
			}
		}

		if ( $db_successful ) {
			$this->cleanup_db();
		}

		if ( true !== $leave_debugging ) {
			$this->wipe_stall_log();
		}

		$this->log( '✓ Cleanup complete.' );
	}

	/**
	 * Remove Completed & Cancelled backup creation events.
	 *
	 * Used by class backupbuddy_housekeeping.
	 *
	 * @return int The number of events deleted.
	 */
	public static function housekeeping() {
		$count = self::remove_events_by_status( ActionScheduler_Store::STATUS_COMPLETE );
		$count += self::remove_events_by_status( ActionScheduler_Store::STATUS_CANCELED );
		return $count;
	}

	/**
	 * Remove backup creation events.
	 *
	 * @return int The number of events deleted.
	 */
	private static function remove_events_by_status( $status = '' ) {
		if ( empty( $status ) ) {
			$status = ActionScheduler_Store::STATUS_COMPLETE;
		}

		$query_args = array(
			'group'  => self::CRON_GROUP,
			'status' => $status,
		);

		return backupbuddy_core::delete_events( $query_args );
	}

	/**
	 * Delete the Stall log if exists.
	 *
	 * @return bool  If deleted.
	 */
	private function wipe_stall_log() {
		$stall_log = backupbuddy_core::getLogDirectory() . 'backupbuddy-stall.log';
		if ( file_exists( $stall_log ) ) {
			unlink( $stall_log );
		}
		if ( file_exists( $stall_log ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Enable WPDB errors.
	 */
	private function enable_wpdb_errors() {
		global $wpdb;
		$wpdb->suppress_errors = false;
		ob_start();
		$wpdb->show_errors();
	}

	/**
	 * Log the WPDB error.
	 *
	 * @param string $addl        Additional message to preceed the error.
	 * @param bool   $true_error  If error should be logged instead of log.
	 */
	private function log_wpdb_error( $addl = '', $true_error = false ) {
		global $wpdb;
		$db_error = ob_get_clean();
		if ( $db_error && $addl ) {
			$db_error = ' (' . $db_error . ')';
		}

		if ( ! $addl && ! $db_error ) {
			return;
		}

		if ( $true_error ) {
			$this->error( $addl . $db_error );
		} else {
			$this->log( $addl . $db_error );
		}
	}

	/**
	 * Log a message for this restore.
	 *
	 * @param string $message  Message to log.
	 * @param string $type     Type of message.
	 *
	 * @return bool  If logged.
	 */
	public function log( $message, $type = 'details' ) {
		if ( empty( $this->restore ) ) {
			pb_backupbuddy::status( $type, $message );
			return false;
		}

		if ( empty( $this->restore['log'] ) ) {
			$this->restore['log'] = array();
		}

		$hidden = 'hidden' === $type;
		if ( $hidden ) {
			$type = 'details';
		}

		pb_backupbuddy::status( $type, $message, $this->restore['id'] ); // Log globally for good measure.

		if ( 'error' === $type ) {
			$this->restore['errors'][] = $message;

			// Prepend ERROR to restore log if not already clear in message.
			if ( false === strpos( strtolower( $message ), 'error' ) ) {
				$message = 'ERROR: ' . $message;
			}
		}

		if ( $hidden ) {
			$message = '<span class="hidden">' . $message . '</span>';
		}

		$this->restore['log'][] = $message;

		return true;
	}

	/**
	 * Logs an error message.
	 *
	 * @param string $message  Error message.
	 *
	 * @return bool  If logged.
	 */
	public function error( $message ) {
		return $this->log( $message, 'error' );
	}

	/**
	 * Logs a warning message.
	 *
	 * @param string $message  Warning message.
	 *
	 * @return bool  If logged.
	 */
	public function warning( $message ) {
		return $this->log( $message, 'warning' );
	}

	/**
	 * Get last error.
	 *
	 * @param array $restore  Restore array to pull errors from.
	 *
	 * @return string  Last error message;
	 */
	public function get_last_error( $restore = false ) {
		$array = false === $restore ? $this->restore : $restore;
		if ( empty( $array['errors'] ) || ! count( $array['errors'] ) ) {
			return false;
		}
		$array_values = array_values( array_slice( $array['errors'], -1 ) );
		return $array_values[0];
	}

	/**
	 * Get summary of restore.
	 *
	 * @param array $restore  Restore array.
	 *
	 * @return string|false  String summary or false on error.
	 */
	public function get_summary( $restore ) {
		if ( '*' === $restore['files'] ) {
			$extra = 'full' !== $restore['profile'] ? ' (' . $restore['profile'] . ')' : ( 'both' !== $restore['what'] ? ' (' . $restore['what'] . ')' : '' );
			return __( 'Full Backup', 'it-l10n-backupbuddy' ) . $extra;
		}

		if ( ! is_array( $restore['files'] ) ) {
			return false;
		}

		$files   = 0;
		$folders = 0;
		$tables  = 0;

		if ( 'db' === $restore['what'] ) {
			$tables = __( 'All', 'it-l10n-backupbuddy' );
		} else {
			foreach ( $restore['files'] as $file ) {
				$file = rtrim( $file, '*' );
				if ( '/' === substr( $file, -1 ) ) {
					$folders++;
				} else {
					$files++;
				}
			}
		}

		if ( ! $files && ! $folders && ! $tables ) {
			return false;
		}

		$summary = '';

		if ( $folders ) {
			$summary = $folders . ' ' . _n( 'Folder', 'Folders', $folders, 'it-l10n-backupbuddy' );
		}
		if ( $files ) {
			if ( $summary ) {
				$summary .= ' & ';
			}
			$summary .= $files . ' ' . _n( 'File', 'Files', $files, 'it-l10n-backupbuddy' );
		}
		if ( $tables ) {
			if ( $summary ) {
				$summary .= ' & ';
			}
			if ( is_numeric( $tables ) ) {
				$summary .= $tables . ' ' . _n( 'Table', 'Tables', $tables, 'it-l10n-backupbuddy' );
			} else {
				$summary .= $tables . ' ' . __( 'Tables', 'it-l10n-backupbuddy' );
			}
		}

		return $summary;
	}

	/**
	 * Returns HTML for status of restore.
	 *
	 * @param array  $restore     Restore array.
	 * @param string $use_text    Text to display (default is status text).
	 * @param bool   $echo        Echo or return.
	 * @param bool   $is_archive  If used for archive listing.
	 *
	 * @return string  Status HTML.
	 */
	public function get_status_html( $restore, $use_text = false, $echo = false, $is_archive = false ) {
		$text = false === $use_text ? $this->get_status_text( $restore['status'] ) : $use_text;

		if ( in_array( $restore['status'], $this->get_completed_statuses(), true ) ) {
			$html = sprintf( '<a href="#restore-details-%s">%s</a>', esc_attr( $restore['id'] ), esc_html( $text ) );
		} elseif ( $restore['status'] < self::STATUS_COMPLETE ) {
			$class = $is_archive ? '' : ' class="restore-in-progress"';
			$html  = sprintf( '<span data-restore-id="%s"%s>%s</span>', esc_attr( $restore['id'] ), $class, esc_html( $text ) );
		} else {
			$html = false === $use_text ? 'Unknown Status: ' . $restore['status'] : $text;
		}

		if ( true !== $echo ) {
			return $html;
		}

		echo $html;
	}

	/**
	 * Convert status code to human readable text.
	 *
	 * @param int  $status_code  Restore status code.
	 * @param bool $echo         Echo or return.
	 *
	 * @return string  Text for status code.
	 */
	public function get_status_text( $status_code, $echo = false ) {
		$text = __( 'Unknown', 'it-l10n-backupbuddy' );

		if ( ! $status_code ) {
			$text = __( 'Not Started', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_STARTED === $status_code ) {
			$text = __( 'Started', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_DOWNLOADING === $status_code ) {
			$text = __( 'Downloading', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_READY === $status_code ) {
			$text = __( 'Ready', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_UNZIPPING === $status_code ) {
			$text = __( 'Unzipping', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_UNZIPPED === $status_code ) {
			$text = __( 'Unzipped', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_VERIFYING === $status_code ) {
			$text = __( 'Verifying', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_VERIFIED === $status_code ) {
			$text = __( 'Verifying', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_PERMISSIONS === $status_code ) {
			$text = __( 'Handling Permissions', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_DB_READY === $status_code ) {
			$text = __( 'Preparing Database', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_DB_TABLES === $status_code ) {
			$text = __( 'Restoring Database Tables', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_PAUSED === $status_code ) {
			$text = __( 'Paused', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_RESTORING === $status_code ) {
			$text = __( 'Restoring', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_COPYING === $status_code ) {
			$text = __( 'Copying', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_DATABASE === $status_code ) {
			$text = __( 'Restoring Database', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_CLEANUP === $status_code ) {
			$text = __( 'Cleanup', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_COMPLETE === $status_code ) {
			$text = __( 'Complete', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_FAILED === $status_code ) {
			$text = __( 'Failed', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_USER_ABORTED === $status_code ) {
			$text = __( 'Aborted by User', 'it-l10n-backupbuddy' );
		} elseif ( self::STATUS_ABORTED === $status_code ) {
			$text = __( 'Aborted', 'it-l10n-backupbuddy' );
		}

		if ( true !== $echo ) {
			return $text;
		}

		echo $text;
	}

	/**
	 * Returns a link to delete a restore from the archive.
	 *
	 * @param array $restore  Restore array.
	 * @param bool  $echo     If link should be echo'd.
	 *
	 * @return string  Delete Link HTML.
	 */
	public function get_delete_link( $restore, $echo = false ) {
		$attr = '';
		if ( is_array( $restore ) ) {
			$rel = $restore['id'];
		} else {
			$rel  = basename( $restore );
			$attr = ' data-corrupt="true"';
		}
		$link = sprintf( '<a href="#delete-restore" class="delete-restore" rel="%s"%s>%s</a>', esc_attr( $rel ), $attr, esc_html__( 'Delete', 'it-l10n-backupbuddy' ) );
		if ( false === $echo ) {
			return $link;
		}
		echo $link;
	}

	/**
	 * Initialize database variables.
	 *
	 * @return bool  If successful.
	 */
	private function database_init() {
		$this->log( 'Setting up Database Restore...' );

		$this->log( 'Locating database files...' );

		$sql_files = array();
		$sql_path  = '';

		// Possible locations of .SQL file. Look for SQL files in root LAST in case user left files there.
		$possible_sql_file_paths = array(
			// Full backup < v2.0.
			$this->temp_dir() . 'wp-content/uploads/temp_' . $this->serial . '/',
			// Full backup >= v2.0.
			$this->temp_dir() . 'wp-content/uploads/backupbuddy_temp/' . $this->serial . '/',
			// Determined from detecting DAT file. Should always be the location really... As of v4.1.
			$this->temp_dir(),
		);

		foreach ( $possible_sql_file_paths as $possible_sql_file_path ) { // Check each file location to see which hits.
			$possible_sql_files = glob( $possible_sql_file_path . '*.sql' );
			if ( ! is_array( $possible_sql_files ) || 0 === count( $possible_sql_files ) ) { // No SQL files here.
				continue;
			}

			// Remove path information.
			$possible_sql_files = array_map( 'basename', $possible_sql_files );

			// Take SQL files out of list that begin with underscore (BackupBuddy Stash Live timestamped files) to put them at end of the array to play back at the end.
			$live_sql_files = array();
			foreach ( $possible_sql_files as $index => $sql_file ) {
				if ( '_' === substr( $sql_file, 0, 1 ) ) {
					$live_sql_files[] = $sql_file; // Copy into new array.
					unset( $possible_sql_files[ $index ] ); // Remove from original array.
				}
			}

			// Fix missing indexes of removed items.
			$possible_sql_files = array_filter( $possible_sql_files );

			// Append LIVE SQL files to end of normal SQL files list.
			$sql_files = array_merge( $possible_sql_files, $live_sql_files );
			$sql_path  = $possible_sql_file_path;
			break;

		} // End foreach.
		unset( $possible_sql_file_paths );

		if ( ! count( $sql_files ) ) {
			$this->error( 'No SQL files found in backup.' );
			return false;
		}

		$this->log( '✓ Found ' . count( $sql_files ) . ' SQL files in `' . $sql_path . '`.' );

		$this->restore['sql_files'] = $sql_files;
		$this->restore['sql_path']  = $sql_path;

		if ( ! $this->build_table_queue() ) {
			$this->error( 'Could not create table queue.' );
			return false;
		}

		$this->set_status( self::STATUS_DB_READY );
		return true;
	}

	/**
	 * Restore Database.
	 *
	 * @return bool  If successful.
	 */
	private function database() {
		if ( empty( $this->restore['table_queue'] ) ) {
			if ( false === $this->database_init() ) {
				$this->abort();
				return false;
			}
			return false; // Milestone (we're not done).
		}

		// Check to see if we're done.
		$completed_tables = array_merge( $this->restore['failed_tables'], $this->restore['imported_tables'] );
		if ( ! count( $this->restore['incomplete_tables'] ) && count( $this->restore['table_queue'] ) === count( $completed_tables ) ) {
			if ( count( $this->restore['failed_tables'] ) ) {
				$this->log( '✓ Some SQL files did not import successfully:<pre>' . print_r( $this->restore['failed_tables'], true ) . '</pre>' );
			} else {
				$this->log( '✓ All SQL files imported.' );
			}
			return true; // Proceed to finalization.
		}

		if ( ! class_exists( 'pb_backupbuddy_mysqlbuddy' ) ) {
			require_once pb_backupbuddy::plugin_path() . '/lib/mysqlbuddy/mysqlbuddy.php';
		}

		// Try incomplete first.
		foreach ( $this->restore['incomplete_tables'] as $table => $continued ) {
			$ignore_errors = isset( $this->restore['last_tables'][ $table ] );
			if ( ! $this->temp_import_table( $table, $continued, $ignore_errors ) ) {
				$this->error( 'Failed to complete table import: `' . $table . '`.' );
				$this->abort();
				return false;
			}
			$this->set_status( self::STATUS_DB_READY );
			return false; // Milestone.
		}

		// Skip This section on single file import where db_1 has already been imported.
		if ( ! $this->restore['single_file'] || count( $this->restore['single_file'] ) > 1 ) {

			// Restore each SQL file individually.
			foreach ( $this->restore['table_queue'] as $table ) {
				if ( in_array( $table, $this->restore['imported_tables'], true ) ) {
					continue; // Skip successfully imported tables.
				}
				if ( in_array( $table, $this->restore['failed_tables'], true ) ) {
					continue; // Skip failed tables.
				}
				if ( isset( $this->restore['last_tables'][ $table ] ) ) {
					continue; // Skip tables to be imported later.
				}

				if ( ! $this->temp_import_table( $table ) ) {
					$this->error( 'Failed to import table: `' . $table . '`.' );
					$this->abort();
					return false;
				}

				$this->set_status( self::STATUS_DB_READY );
				return false; // Milestone.
			}
		}

		// Lastly perform tables that have dependencies.
		foreach ( $this->restore['last_tables'] as $table => $dependencies ) {
			if ( in_array( $table, $this->restore['imported_tables'], true ) ) {
				continue; // Skip successfully imported tables.
			}
			if ( in_array( $table, $this->restore['failed_tables'], true ) ) {
				continue; // Skip failed tables.
			}
			if ( ! $this->temp_import_table( $table, false, true ) ) {
				$this->error( 'Failed to import table (last): `' . $table . '`.' );
				$this->abort();
				return false;
			}
			// Try them one at a time.
			break;
		}

		// Run through again for next table.
		$this->set_status( self::STATUS_DB_READY );
		return false; // Milestone.
	}

	/**
	 * Create a queue of tables to restore.
	 *
	 * @return bool  If queue was created successfully.
	 */
	private function build_table_queue() {
		if ( '*' === $this->restore['tables'] ) {
			foreach ( $this->restore['sql_files'] as $sql_file ) {
				$this->restore['table_queue'][ $sql_file ] = str_replace( '.sql', '', $sql_file );
			}
		} elseif ( is_array( $this->restore['tables'] ) ) {
			foreach ( $this->restore['tables'] as $table ) {
				if ( ! in_array( $table . '.sql', $this->restore['sql_files'], true ) ) {
					$this->log( 'Could not restore database table `' . $table . '`. SQL file not found.' );
				} else {
					$this->restore['table_queue'][ $table . '.sql' ] = $table;
				}
			}
		}

		if ( ! count( $this->restore['table_queue'] ) ) {
			$this->error( 'Could not create table restore queue.' );
			return false;
		}

		return true;
	}

	/**
	 * Perform temp table restore.
	 *
	 * @param string   $table          Table name to restore.
	 * @param bool|int $continued      Continuation import pointer.
	 * @param bool     $ignore_errors  Ignore SQL errors.
	 *
	 * @return bool  If restored or needs to continue.
	 */
	private function temp_import_table( $table, $continued = false, $ignore_errors = false ) {
		global $wpdb;

		$import_file = $this->restore['sql_path'] . $table . '.sql';

		if ( ! file_exists( $import_file ) ) {
			$this->log( '× Could not locate SQL file to import table: `' . $table . '`.' );
			return false;
		}

		$this->restore['single_file'] = 'db_1' === $table;

		$importbuddy  = new pb_backupbuddy_mysqlbuddy( DB_HOST, DB_NAME, DB_USER, DB_PASSWORD, $this->table_prefix );
		$dependencies = $this->get_table_dependency( $import_file );

		if ( ! $this->restore['single_file'] ) {
			$table_basename = preg_replace( '/' . preg_quote( $wpdb->prefix ) . '/', '', $table, 1 );
		}

		// If any dependencies have not been imported yet and are scheduled to import, move to end.
		if ( is_array( $dependencies ) && empty( array_intersect( $dependencies, $this->restore['imported_tables'] ) ) ) {
			$not_queued = array_intersect( $dependencies, $this->restore['table_queue'] );
			if ( ! empty( $not_queued ) ) {
				if ( ! isset( $this->restore['last_tables'][ $table ] ) ) {
					$this->restore['last_tables'][ $table ] = $dependencies;
					$this->log( 'Shifting import for `' . $table . '` to the end of the queue due to table dependency.' );
					$this->save();
				}
				return true;
			} else {
				$this->log( 'WARNING: Table `' . $table . '` contains dependency on `' . implode( '`, `', $not_queued ) . '` which is not queued for import.' );
			}
		}

		$this->handle_constraint( $import_file, $table );
		$result = $importbuddy->import( $import_file, $wpdb->prefix, $continued, $ignore_errors );

		// More processing needed later.
		if ( true !== $result && is_array( $result ) ) {
			$this->restore['incomplete_tables'][ $table ] = $result[0];
			$this->save();
			return true;
		}

		// Remove from incomplete tables.
		if ( isset( $this->restore['incomplete_tables'][ $table ] ) ) {
			unset( $this->restore['incomplete_tables'][ $table ] );
		}

		if ( false === $result ) {
			$this->restore['failed_tables'][] = $table;
			$this->log( '× Table query failed for: ' . $table );

			// Ignore and continue.
			if ( $ignore_errors && $this->table_exists( $table ) ) {
				$this->log( '× There was a problem importing: `' . $table . '`. Ignoring and continuing...' );
				$this->save();
				return true;
			}

			// Fail.
			return false;
		}

		// Final Table check.
		if ( ! $this->restore['single_file'] && ! $this->table_exists( $this->table_prefix . $table_basename ) ) {
			$this->restore['failed_tables'][] = $table;
			$this->log( '× Table failed exists check: ' . $table );

			if ( $ignore_errors && $this->table_exists( $table ) ) {
				$this->log( '× There was a problem importing: `' . $table . '`. Ignoring and continuing...' );
				$this->save();
				return true;
			}

			$this->error( 'Something went wrong during table import for: `' . $table . '`. Check your error logs for more information.<pre>' . print_r( $result, true ) . '</pre>' );
			return false;
		}

		if ( ! empty( $this->restore['post_import'][ $table ] ) ) {
			if ( false === $wpdb->query( $this->restore['post_import'][ $table ] ) ) {
				$this->error( 'Post import query failed for: ' . $table );
				return false;
			}
			unset( $this->restore['post_import'][ $table ] );
		}

		if ( ! $this->restore['single_file'] ) {
			$this->restore['imported_tables'][] = $table;
			$this->restore['cleanup_db'][ 'Drop Temp Table `' . $this->table_prefix . $table_basename . '`' ] = sprintf( "DROP TABLE IF EXISTS `%s`;", $this->table_prefix . $table_basename );
		} else {
			// Delete db_1 from table queue.
			unset( $this->restore['table_queue']['db_1.sql'] );
			$temp_tables = $this->get_temp_table_names();

			foreach ( $temp_tables as $temp_table ) {
				$table_basename = preg_replace( '/' . preg_quote( $this->table_prefix ) . '/', '', $temp_table, 1 );
				if ( in_array( $wpdb->prefix . $table_basename, $this->restore['imported_tables'], true ) ) {
					continue;
				}
				$this->restore['table_queue'][]     = $wpdb->prefix . $table_basename;
				$this->restore['imported_tables'][] = $wpdb->prefix . $table_basename;
				$this->restore['cleanup_db'][ 'Drop Temp Table `' . $temp_table . '`' ] = sprintf( "DROP TABLE IF EXISTS `%s`;", $temp_table );
			}
		}

		$this->save();
		return true;
	}

	/**
	 * Pull temp table names from single DB file import.
	 */
	private function get_temp_table_names() {
		global $wpdb;

		$temp_tables = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `TABLE_NAME` FROM INFORMATION_SCHEMA.TABLES WHERE `TABLE_SCHEMA` = %s AND `TABLE_NAME` LIKE %s",
				DB_NAME,
				$this->table_prefix . '%'
			)
		);

		return wp_list_pluck( $temp_tables, 'TABLE_NAME' );
	}

	/**
	 * Check if there's a table dependency.
	 *
	 * @param string $sql_file  Path to SQL file.
	 *
	 * @return bool|string  False or table name.
	 */
	private function get_table_dependency( $sql_file ) {
		if ( ! file_exists( $sql_file ) ) {
			return false;
		}

		$contents = file_get_contents( $sql_file );
		preg_match_all( '/REFERENCES\s*`(.*?)`/m', $contents, $dependency );
		if ( ! empty( $dependency[1] ) ) {
			$dependencies = array();
			foreach ( $dependency[1] as $table ) {
				if ( ! in_array( $table, $dependencies ) ) {
					$dependencies[] = $table;
				}
			}
			return $dependencies;
		}
		return false;
	}

	/**
	 * Find CONSTRAINT statements and temporarily remove them.
	 *
	 * @param string $sql_file  Path to SQL file.
	 * @param string $table     The table name.
	 */
	private function handle_constraint( $sql_file, $table ) {
		if ( ! file_exists( $sql_file ) ) {
			return false;
		}

		$contents = file_get_contents( $sql_file );
		preg_match_all( '/CONSTRAINT\s+.*?\s+REFERENCES\s+[^,)]*\)(\s+(ON DELETE|ON UPDATE)\s+[^,)]*)?[,)]/i', $contents, $constraints );
		if ( empty( $constraints[0][0] ) ) {
			return false;
		}

		foreach ( $constraints[0] as $i => $constraint ) {
			if ( empty( $constraint ) ) {
				continue;
			}

			$constraint = trim( $constraint );
			$last_char  = substr( $constraint, -1 );
			$constraint = rtrim( $constraint, $last_char );

			if ( ')' === $last_char ) {
				$contents = preg_replace( '/,\s+' . preg_quote( $constraint ) . '/', '', $contents );
			} else {
				$contents = preg_replace( '/' . preg_quote( $constraint ) . '/', '', $contents );
			}
			$num = $i + 1;
			$this->restore['finalize_db'][ 'Constraint ' . $num . ' for `' . $table . '`' ] = sprintf( 'ALTER TABLE `%s` ADD %s;', $table, $constraint );
		}

		// Write changes to the SQL file.
		if ( @file_put_contents( $sql_file, $contents ) ) {
			$this->save();
			return true;
		}

		return false;
	}

	/**
	 * Check if a table exists.
	 *
	 * @param string $table  Table name.
	 *
	 * @return bool  If table exists.
	 */
	private function table_exists( $table ) {
		global $wpdb;
		$check = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s;', $table ) );
		return $check === $table;
	}

	/**
	 * Swap temp tables with original tables.
	 *
	 * @return bool  If finalization was successful.
	 */
	private function finalize_database() {
		global $wpdb;

		$this->enable_wpdb_errors();

		if ( count( $this->restore['restored_tables'] ) === count( $this->restore['imported_tables'] ) ) {
			$this->log( '✓ Database restored.' );
			$this->save();
			return true;
		}

		if ( ! count( $this->restore['restored_tables'] ) && ! count( $this->restore['failed_tables'] ) ) {
			$this->log( 'Finalizing database restore...' );
		}

		$failsafe_prefix = $this->table_prefix . 'fs_';

		$wpdb->query( "SET FOREIGN_KEY_CHECKS=0;" );

		foreach ( $this->restore['imported_tables'] as $table ) {

			// Skip failed tables.
			if ( in_array( $table, $this->restore['failed_tables'], true ) ) {
				continue;
			}

			// Skip Restored tables.
			if ( in_array( $table, $this->restore['restored_tables'], true ) ) {
				continue;
			}

			// Rename Original to Failsafe.
			$table_basename = preg_replace( '/' . preg_quote( $wpdb->prefix ) . '/', '', $table, 1 );
			$table_swap     = false;

			if ( $table === $wpdb->options ) {
				// Copy over cron option value to prevent malfunctions during restore.
				$restored_options = $this->table_prefix . $table_basename;
				$cron_value       = $wpdb->get_var( "SELECT `option_value` FROM {$wpdb->options} WHERE `option_name` = 'cron';" );
				if ( $cron_value ) {
					if ( false === $wpdb->query( $wpdb->prepare( "UPDATE `$restored_options` SET `option_value` = %s WHERE `option_name` = 'cron';", $cron_value ) ) ) {
						$this->log( '× Unable to copy cron from original options table.' );
					}
				}
			} elseif ( $table === $wpdb->usermeta ) {
				// Copy over session tokens so user isn't logged out.
				$restored_usermeta = $this->table_prefix . $table_basename;
				$session_tokens    = $wpdb->get_var( $wpdb->prepare( "SELECT `meta_value` FROM {$wpdb->usermeta} WHERE `user_id` = %s AND `meta_key` = 'session_tokens';", get_current_user_id() ) );
				if ( $session_tokens ) {
					if ( false === $wpdb->query( $wpdb->prepare( "UPDATE `$restored_usermeta` SET `meta_value` = %s WHERE `meta_key` = 'session_tokens' AND `user_id` = %s;", $session_tokens, get_current_user_id() ) ) ) {
						$this->log( '× Unable to copy session tokens from original usermeta table.' );
					}
				}
			}

			// Swap restore table with original.
			if ( $this->table_exists( $table ) ) {
				// Drop failsafe table if it already exists (maybe from a previous restore).
				$fs_table_drop = sprintf( "DROP TABLE IF EXISTS `%s`;", $failsafe_prefix . $table_basename );
				$wpdb->query( $fs_table_drop );

				// Store original table as Failsafe, Rename restored table to original.
				$table_swap = sprintf( "RENAME TABLE `%s` TO `%s`, `%s` TO `%s`;",
					$table, // Original Table.
					$failsafe_prefix . $table_basename, // Failsafe backup.
					$this->table_prefix . $table_basename, // Imported Table.
					$table // New, restored table.
				);
			} elseif ( $this->table_exists( $this->table_prefix . $table_basename ) ) {
				// Rename Imported to Original.
				$table_swap = sprintf( "RENAME TABLE `%s` TO `%s`;", $this->table_prefix . $table_basename, $table );
			} else {
				$this->log( 'No table swap performed for `' . $this->table_prefix . $table_basename . '`. Table not found.' );
				continue;
			}

			if ( $table_swap && false === $wpdb->query( $table_swap ) ) {
				$this->restore['failed_tables'][] = $table;
				$this->log_wpdb_error( 'Table restore failed on `' . $table . '`. Could not perform table swap.', true );
				return false;
			}

			// Go ahead and drop Failsafe Table.
			if ( $this->table_exists( $table ) ) {
				$drop_failsafe = sprintf( "DROP TABLE IF EXISTS `%s`;", $failsafe_prefix . $table_basename );
				$wpdb->query( $drop_failsafe );
			}

			$this->restore['restored_tables'][] = $table;
			$this->save();
			// sleep( 0.5 );
		}

		if ( count( $this->restore['restored_tables'] ) !== count( $this->restore['imported_tables'] ) ) {
			$this->error( 'Database was not restored. Table count mismatch.' );
			return false;
		}

		return true;
	}

	/**
	 * Enable Maintenance Mode
	 */
	public function enable_maintenance_mode() {
		if ( ! defined( 'BACKUPBUDDY_IS_RESTORING' ) ) {
			define( 'BACKUPBUDDY_IS_RESTORING', true );
		}

		add_action( 'get_header', array( $this, 'maintenance_mode' ) );
	}

	/**
	 * Disable Maintenance Mode
	 */
	public function disable_maintenance_mode() {
		remove_action( 'get_header', array( $this, 'maintenance_mode' ) );
	}

	/**
	 * Display Maintenance Mode Message to site users.
	 */
	public function maintenance_mode() {
		if ( ! is_admin() ) {
			ob_start();
			require pb_backupbuddy::plugin_path() . '/views/maintenance-mode.php';
			wp_die( ob_get_clean() );
		}
	}

	/**
	 * Determine if umask is being used.
	 *
	 * @param bool $show_alert  Display dismissable alert.
	 * @param bool $retest      If test should be run again.
	 *
	 * @return bool  If umask is used.
	 */
	public function confirm_umask( $show_alert = false, $retest = false ) {
		$passed   = true;
		$alert_id = 'umask-warning';

		$umask_check = ABSPATH . 'backupbuddy-umask-check';
		if ( file_exists( $umask_check ) ) {
			@rmdir( $umask_check ); // Always make sure this folder doesn't exist.
		}

		if ( false !== $retest ) {
			pb_backupbuddy::$options['umask_check'] = false;
			unset( pb_backupbuddy::$options['disalerts'][ $alert_id ] );
		}

		if ( ! empty( pb_backupbuddy::$options['umask_check'] ) ) {
			list( $umask, $mode_test ) = explode( '|', pb_backupbuddy::$options['umask_check'] );
		} else {
			$umask = sprintf( '%o', umask() );
			pb_backupbuddy::$filesystem->mkdir( $umask_check, 0777 );
			$mode_test = (string) substr( sprintf( '%o', fileperms( $umask_check ) ), -4 );
			@rmdir( $umask_check );
		}

		if ( (int) $umask <= 0 ) {
			$passed = false;
		}
		if ( '0777' === $mode_test ) {
			$passed = false;
		}

		pb_backupbuddy::$options['umask_check'] = $umask . '|' . $mode_test;
		pb_backupbuddy::save();

		if ( false === $passed && true === $show_alert ) {
			$settings_url  = admin_url( 'admin.php?page=pb_backupbuddy_settings&tab=advanced' ) . '#pb_backupbuddy_default_restores_permissions';
			$settings_link = sprintf( '<a href="%s">%s</a>', $settings_url, esc_html__( 'Advanced Settings > Restore Permissions', 'it-l10n-backupbuddy' ) );
			pb_backupbuddy::disalert( $alert_id, esc_html__( 'WARNING: Solid Backups has detected potential issues with creating new directories on your server. Double check file/folder permissions set here: ', 'it-l10n-backupbuddy' ) . $settings_link, true, '', array( 'class' => 'below-h2' ) );
		}

		return $passed;
	}
}
