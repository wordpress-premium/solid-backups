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

	/**
	 * Path to storage file.
	 *
	 * @var string
	 */
	private $restore_storage;

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
		);

		$this->ignore_files = apply_filters( 'backupbuddy_restore_ignore_files', array(
			'*.DS_Store',
			'*.itbub',
		) );

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
				// Restore Error: Restore for this zip still in progress.
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
			$files = array( $files );
		}

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
		$restore['restore_path'] = $restore_dir;

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
			'perms'      => array(), // folders where permissions have been changed.
			'perm_fails' => array(), // files that failed to set permissions.
			'copied'     => array(), // files/folders successfully copied.
			'skipped'    => array(), // identical files skipped during restore.
			'cleanup'    => array(), // files/folders to cleanup afterwards.
		);
		$db_defaults   = array(
			'tables'            => array(),
			'sql_files'         => array(), // sql files in backup.
			'sql_path'          => false, // path to sql files.
			'table_queue'       => array(), // array of tables for restoring.
			'imported_tables'   => array(), // array of tables that have been imported.
			'post_import'       => array(), // array of queries to run after temp table imported.
			'incomplete_tables' => array(), // array of tables that need to finish importing.
			'failed_tables'     => array(), // array of tables that failed to import.
			'last_tables'       => array(), // array of tables that need to be imported last.
			'restored_tables'   => array(), // array of tables that have been restored.
			'cleanup_db'        => array(), // SQL queries to run during cleanup.
		);
		$base_defaults = array(
			'id'               => false, // restore id.
			'serial'           => false, // backup serial.

			'backup_file'      => false, // backup filename.
			'zip_path'         => false, // path to backup zip.
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
			'statarted'        => false,
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

		$restore = array_merge( $defaults, $restore );

		// Trigger download if necessary.
		if ( ! empty( $restore['destination_args'] ) && ! file_exists( $restore['zip_path'] ) ) {
			$destination_copy_args = array(
				$restore['destination_args'],
				$restore['backup_file'],
			);
			$restore['download']   = $this->schedule_download( $destination_copy_args );
		}

		$restore_json = wp_json_encode( $restore );
		$this->get_queue();
		$this->restores = array_merge( array( $restore['id'] => $restore_json ), $this->restores ); // Put newest in front.
		$this->save();

		$this->schedule_cron();

		return $restore['id'];
	}

	/**
	 * Schedules the cron to process the queue.
	 *
	 * @return bool  If scheduled.
	 */
	public function schedule_cron() {
		$scheduled = wp_next_scheduled( 'pb_backupbuddy_process_restore_queue' );
		if ( false === $scheduled ) {
			$scheduled = backupbuddy_core::schedule_single_event( time(), 'process_restore_queue', array() );
		}
		return $scheduled;
	}

	/**
	 * Schedule Destination Zip download.
	 *
	 * @param array $schedule_args  Schedule args.
	 *
	 * @return bool  If scheduled.
	 */
	public function schedule_download( $schedule_args ) {
		$this->log( 'Scheduling remote zip download...' );
		$scheduled = wp_next_scheduled( 'process_destination_copy', $schedule_args );
		if ( false === $scheduled ) {
			$scheduled = backupbuddy_core::schedule_single_event( time(), 'process_destination_copy', $schedule_args );
		}
		if ( ! empty( $this->restore ) ) {
			$this->restore['download'] = $scheduled;
			$this->log( '✓ Remote zip download scheduled.' );
			$this->save();
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
	 * Get restore details.
	 *
	 * @param string $restore_id  Restore ID.
	 *
	 * @return array  Restore array.
	 */
	public function details( $restore_id ) {
		$this->get_queue();

		if ( ! empty( $this->restores[ $restore_id ] ) ) {
			return json_decode( $this->restores[ $restore_id ], true );
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
			} else {
				$status_js['text'] = esc_html__( 'Unzipping backup contents...', 'it-l10n-backupbuddy' );
			}
		} elseif ( in_array( $restore['status'], array( self::STATUS_DB_READY, self::STATUS_DB_TABLES ), true ) ) {
			$status_js['dot'] = 'database';
			$current_table    = count( array_merge( $restore['failed_tables'], $restore['last_tables'], $restore['imported_tables'] ) );
			$total_tables     = count( $restore['table_queue'] );

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
			$total_files = count( $restore['extract_files'] ) ? '/' . number_format( count( $restore['extract_files'] ) ) : '';
			$restored    = ! empty( $restore['copied'] ) ? count( $restore['copied'] ) : 0;
			if ( 0 !== $restored ) {
				$status = sprintf( '%s%s Files Restored.', number_format( $restored ), $total_files );
			}
		} elseif ( true !== $restore['db_status'] && in_array( $restore['what'], array( 'both', 'db' ), true ) ) {
			$status = sprintf( '%d/%d Database Tables Restored.', count( $restore['restored_tables'] ), count( $restore['imported_tables'] ) );
		}

		return $status;
	}

	/**
	 * Check to see if a Restore Status request is currently in progress.
	 *
	 * @param bool $create_lock  Create a lock file if one doesn't exist.
	 *
	 * @return bool  If status request is in progress.
	 */
	public function status_request_in_progress( $create_lock = true ) {
		$lock_dir  = backupbuddy_core::getLogDirectory();
		$lock_file = 'backupbuddy-restore-lock.txt';
		$lock_path = trailingslashit( $lock_dir ) . $lock_file;
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
	 * Remove Status request lock file.
	 *
	 * @return bool  If lock file was removed.
	 */
	public function status_request_complete() {
		$lock_dir  = backupbuddy_core::getLogDirectory();
		$lock_file = 'backupbuddy-restore-lock.txt';
		$lock_path = trailingslashit( $lock_dir ) . $lock_file;
		if ( ! file_exists( $lock_path ) ) {
			return false;
		}
		return @unlink( $lock_path );
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

		if ( ! empty( $this->restore ) ) {
			$this->restores[ $this->restore['id'] ] = wp_json_encode( $this->restore );

			// Keep cron alive.
			if ( ! in_array( $this->restore['status'], $this->get_completed_statuses(), true ) ) {
				$this->schedule_cron();
			}
		} else {
			// Only prune if we're not changing restore details.
			$this->prune();
		}

		if ( ! empty( $this->restore ) && ! file_exists( $this->restore_storage ) ) {
			pb_backupbuddy::status( 'details', 'Attempted to write to restore log, but log was unavailable.' );
			return false;
		}

		if ( @file_put_contents( $this->restore_storage, wp_json_encode( $this->restores ) ) ) {
			return true;
		}

		return false;
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

		foreach ( $this->restores as $restore_json ) {
			$this->restore = json_decode( $restore_json, true );

			// Don't re-process anything already complete.
			if ( in_array( $this->restore['status'], $this->get_completed_statuses(), true ) ) {
				continue;
			}

			if ( ! $this->restore_init() ) {
				$this->abort();
				return false;
			}

			if ( $this->is_aborted() ) {
				return false;
			}

			// Once the files have been extracted and the database tables imported, we're ready to start actually changing files/tables.
			if ( $this->is_ready_to_restore() ) {
				$this->set_status( self::STATUS_RESTORING );
			}

			if ( self::STATUS_NOT_STARTED === $this->restore['status'] ) {
				if ( $this->start() ) {
					$this->save();
				}
			}

			if ( self::STATUS_STARTED === $this->restore['status'] || self::STATUS_DOWNLOADING === $this->restore['status'] ) {
				if ( ! $this->ready() ) {
					// Not ready yet. Still downloading zip file from remote.
					return false;
				}

				$this->set_status( self::STATUS_READY );
				pb_backupbuddy::flush();
				return true; // Milestone.
			}

			if ( self::STATUS_READY === $this->restore['status'] ) {
				$this->set_status( self::STATUS_UNZIPPING );

				if ( ! $this->unzip() ) {
					$this->abort();
					return false;
				}

				$this->set_status( self::STATUS_UNZIPPED );
				pb_backupbuddy::flush();
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
				pb_backupbuddy::flush();
				return true; // Milestone.
			}

			if ( self::STATUS_VERIFIED === $this->restore['status'] ) {
				$this->set_status( self::STATUS_PERMISSIONS );
				if ( ! $this->copy_permissions() ) {
					$this->abort();
					return false;
				}

				if ( $this->is_aborted() ) {
					return false;
				}

				$this->restore['files_ready'] = true;
				if ( $this->restore_db() ) {
					$this->set_status( self::STATUS_DB_READY );
				} else {
					$this->restore['db_ready'] = true;
					$this->save();
				}
				pb_backupbuddy::flush();
				return true; // Milestone.
			}

			if ( self::STATUS_DB_READY === $this->restore['status'] ) {
				if ( $this->restore_db() ) {

					// Change status while restoring a table.
					$this->set_status( self::STATUS_DB_TABLES );

					if ( false === $this->database() ) {
						pb_backupbuddy::flush();
						return true; // Milestone.
					}
				}

				$this->restore['db_ready'] = true;
				$this->save();
				pb_backupbuddy::flush();
				return true; // Milestone.
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

				if ( $this->is_aborted() ) {
					return false;
				}

				if ( $this->restore_files() ) {
					$this->set_status( self::STATUS_COPYING );

					$this->enable_maintenance_mode();

					$files_status = $this->files();

					if ( null === $files_status ) {
						$this->set_status( self::STATUS_RESTORING_FILES );
						pb_backupbuddy::flush();
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
				pb_backupbuddy::flush();
				return true;
			}

			// Final steps. No turning back now.
			if ( self::STATUS_RESTORING_DB === $this->restore['status'] ) {

				if ( $this->is_aborted() ) {
					return false;
				}

				if ( $this->restore_db() ) {
					$this->set_status( self::STATUS_DATABASE );

					$this->enable_maintenance_mode();

					$db_status = $this->finalize_database();
					if ( null === $db_status ) {
						$this->set_status( self::STATUS_RESTORING_DB );
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
				pb_backupbuddy::flush();
				return true;
			}

			// Once file_status and db_status are both true, we're completely done.
			if ( $this->restore['file_status'] && $this->restore['db_status'] ) {
				// Cleanup.
				$this->set_status( self::STATUS_CLEANUP );
				$this->cleanup();

				$this->restore['completed'] = current_time( 'timestamp' );
				$this->restore['elapsed']   = $this->restore['completed'] - $this->restore['started'];
				$this->log( '✓ Restore finished successfully.' );
				$this->set_status( self::STATUS_COMPLETE );

				// Make sure maintenance mode is off.
				$this->disable_maintenance_mode();
			}

			$this->save();
		}

		// Reset these vars since we're done.
		$this->restore = array();
		pb_backupbuddy::flush();
		return true;
	}

	/**
	 * Check if we're ready to finalize the restore.
	 *
	 * @return bool  Ready to restore.
	 */
	private function is_ready_to_restore() {
		if ( $this->restore_files() && ! $this->restore['files_ready'] ) {
			return false;
		}
		if ( $this->restore_db() && ! $this->restore['db_ready'] ) {
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

		// Cleanup any lingering lock file from previous restore attempt.
		backupbuddy_restore()->status_request_complete();

		$this->restore['started'] = current_time( 'timestamp' );
		$this->restore['status']  = self::STATUS_STARTED;
		return true;
	}

	/**
	 * Check to see if the zip is ready to start restoring.
	 *
	 * @return bool  If zip has been downloaded and is ready.
	 */
	private function ready() {
		if ( file_exists( $this->restore['zip_path'] ) ) {
			// TODO: When using remote destination, maybe compare file sizes?
			return true;
		}

		if ( ! empty( $this->restore['destination_args'] ) ) {
			if ( false === $this->restore['download'] ) {
				// Try to schedule the download again.
				$destination_copy_args = array(
					$this->restore['destination_args'],
					$this->restore['backup_file'],
				);
				if ( ! $this->schedule_download( $destination_copy_args ) ) {
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
	private function restore_files() {
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
	private function restore_db() {
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
	 */
	private function restore_init() {
		// Prevent Core auto-updates during restore.
		if ( ! defined( 'WP_AUTO_UPDATE_CORE' ) ) {
			define( 'WP_AUTO_UPDATE_CORE', false );
		}

		$this->archive      = $this->restore['zip_path'];
		$this->files        = $this->restore['files'];
		$this->tables       = empty( $this->restore['tables'] ) ? array() : $this->restore['tables'];
		$this->restore_path = $this->restore['restore_path'];
		$this->serial       = backupbuddy_core::parse_file( $this->archive, 'serial' );
		$this->table_prefix = 'bbrestore' . sanitize_text_field( strtolower( substr( $this->restore['id'], -4 ) ) ) . '_';

		if ( $this->restore_files() && empty( $this->files ) ) {
			$this->error( __( 'Restore Failed, missing files to restore.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		if ( $this->restore_db() && empty( $this->tables ) ) {
			$this->error( __( 'Restore Failed, missing tables to restore.', 'it-l10n-backupbuddy' ) );
			return false;
		}

		if ( self::STATUS_NOT_STARTED === $this->restore['status'] ) {
			$this->log( 'Starting Restore...' );

			if ( $this->restore_files() && is_array( $this->files ) ) {
				$this->log( 'Total Files: ' . count( $this->files ) );
			} elseif ( $this->restore_files() ) {
				$this->log( 'Restore Path: ' . $this->restore_path );
			}

			if ( $this->restore_db() && is_array( $this->tables ) ) {
				$this->log( 'Total Tables: ' . count( $this->tables ) );
			}
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

		$this->log( '✓ Setting temp directory to unzip.' );

		// Calculate temp directory & lock it down.
		if ( empty( $this->restore['temp_dir'] ) ) {
			$uploads_dir               = wp_upload_dir();
			$this->restore['temp_dir'] = rtrim( $uploads_dir['basedir'], '/' ) . '/backupbuddy-restoretmp-' . $this->serial . '/';
			if ( file_exists( $this->restore['temp_dir'] ) ) {
				// Make sure directory is empty.
				$this->log( 'Emptying temp restore directory for unzipping...' );
				pb_backupbuddy::$filesystem->unlink_recursive( $this->restore['temp_dir'], true );
			} elseif ( false === pb_backupbuddy::$filesystem->mkdir( $this->restore['temp_dir'] ) ) {
				$this->error( 'Error #458485945: Unable to create temporary location `' . $this->restore['temp_dir'] . '`. Check permissions.' );
				return false;
			}

			$this->log( '✓ Temporary directory created: ' . $this->restore['temp_dir'] );
			$this->save();
		}

		if ( ! class_exists( 'pluginbuddy_zipbuddy' ) ) {
			$this->log( '✓ Loading Zip Extractor Class.' );
			require_once pb_backupbuddy::plugin_path() . '/lib/zipbuddy/zipbuddy.php';
		}

		$zipbuddy = new pluginbuddy_zipbuddy( backupbuddy_core::getBackupDirectory() );

		$this->log( 'Generating file list...' );

		$zip_file_list = $zipbuddy->get_file_list( $this->archive );

		// Generate array of literal files (no wildcards) to extract.
		if ( $this->is_full_restore() ) { // Full Restores.
			$this->log( '✓ Extracting all files inside zip.' );
			// Only used to verify files.
			$this->restore['extract_files'] = $this->flatten_file_array( $zip_file_list );
			foreach ( $this->restore['extract_files'] as $key => &$file ) {
				$file = str_replace( '*', '', $file ); // Remove any wildcard.
				if ( ! $file || in_array( $file, $this->get_ignore_files(), true ) || in_array( '*' . basename( $file ), $this->get_ignore_files(), true ) ) {
					unset( $this->restore['extract_files'][ $key ] );
				}
			}
		} else { // Partial Restores.
			foreach ( $this->files as $key => &$file ) {
				$wildcard = false !== strpos( $file, '*' );
				$file     = str_replace( '*', '', $file ); // Remove any wildcard.
				if ( $wildcard ) {
					$this->insert_zip_files( $file, $zip_file_list );
				} else {
					$this->restore['extract_files'][ $file ] = $file;
				}
			}
		}

		$this->log( 'Extracting zip files...' );
		$this->save();

		// Do the actual extraction.
		if ( $this->is_full_restore() ) {
			$extraction = $zipbuddy->extract( $this->archive, $this->restore['temp_dir'] );
		} else {
			$extraction = $zipbuddy->extract( $this->archive, $this->restore['temp_dir'], $this->restore['extract_files'] );
		}

		unset( $zipbuddy );

		if ( false === $extraction ) {
			$this->error( 'Error #584984458b. Unable to extract.' );
			return false;
		}

		$this->log( '✓ Zip extracted.' );
		$this->save();

		return true;
	}

	/**
	 * Convert array of file arrays to single dimension array.
	 *
	 * @param array $file_array  Multidimensional file array.
	 *
	 * @return array|false  Single dimension file array or false on error.
	 */
	public function flatten_file_array( $file_array ) {
		if ( ! is_array( $file_array ) || empty( $file_array ) ) {
			return false;
		}

		$flat = array();
		foreach ( $file_array as $file ) {
			$flat[] = $file[0];
		}
		return $flat;
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

		$files = glob( $this->restore['temp_dir'] . '*' );
		if ( count( $files ) <= 0 ) {
			$this->error( 'Zip extraction failed. No files found.' );
			$this->log( '× Glob of directory `' . $this->restore['temp_dir'] . '`: <pre>' . print_r( $files, true ) . '</pre>' );
			return false;
		}

		$verified = true;

		// Verify all files/folders to be extracted exist in temp directory. If any missing then delete everything and bail out.
		foreach ( $this->restore['extract_files'] as $file ) {
			if ( ! $file ) { // Skip empty values.
				continue;
			}

			if ( ! file_exists( $this->restore['temp_dir'] . $file ) ) {
				$this->error( 'Could not verify extraction of file: ' . $this->restore['temp_dir'] . $file );
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
	 * Move zip files from tmp folder to restore folder.
	 *
	 * @return bool  If copied successfully.
	 */
	private function files() {
		$success = false;

		// Log file may be unavailable during file copying/moving.
		if ( ! count( $this->restore['copied'] ) ) {
			$this->preserve_directories();

			$this->log( 'Restoring files...' );
		}

		// Selective backup restore.
		if ( is_array( $this->files ) ) {
			$success = true;

			foreach ( $this->files as $file ) {
				if ( ! $file || is_array( $file ) ) {
					continue;
				}

				$file   = str_replace( '*', '', $file ); // Remove any wildcard.
				$result = $this->recursive_copy( $this->restore['temp_dir'] . $file, $this->restore_path . $file );

				if ( false === $result ) {
					$success = false;
				} elseif ( null === $result ) {
					// More work to do.
					return null;
				}
			}

			if ( ! $success ) {
				$this->error( 'Error #9035. Unable to move `' . $this->restore['temp_dir'] . $file . '` to `' . $this->restore_path . $file . '`. Verify permissions on temp folder location & destination directory.' );
			}
		} elseif ( $this->is_full_restore() ) { // Full backup restore.
			$result = $this->recursive_copy( $this->restore['temp_dir'], $this->restore_path );
			if ( true === $result ) {
				$success = true;
			} elseif ( null === $result ) {
				// More work to do.
				return null;
			} else {
				$this->error( 'Error #9035. Unable to move `' . $this->restore['temp_dir'] . '` to `' . $this->restore_path . '`. Verify permissions on temp folder location & destination directory.' );
			}
		}

		if ( $success ) {
			// Reset Status check just in case.
			backupbuddy_restore()->status_request_complete();
			$this->log( '✓ Files restored successfully.' );
		}

		$this->save();

		return $success;
	}

	/**
	 * Move files from unzip directory to restore directory.
	 *
	 * @param string $src    Source file/folder.
	 * @param string $dest   Destination file/folder.
	 * @param int    $depth  Level of directory depth.
	 *
	 * @return bool|null  If successful or null if more work to be done.
	 */
	private function recursive_copy( $src, $dest, $depth = 0 ) {
		$success = true;
		if ( in_array( $dest, $this->restore['copied'], true ) ) {
			return true;
		}

		if ( is_dir( $src ) ) {
			if ( ! file_exists( $dest ) ) {
				pb_backupbuddy::$filesystem->mkdir( $dest );
			}

			$files = scandir( $src ) ?: array();
			$files = array_diff( $files, array( '.', '..' ) );

			foreach ( $files as $file ) {
				$src  = rtrim( $src, '/' );
				$dest = rtrim( $dest, '/' );
				if ( false === $this->recursive_copy( "$src/$file", "$dest/$file", ( $depth + 1 ) ) ) {
					$success = false;
					break;
				}
			}
			if ( 1 === $depth && $success ) {
				// Break up into chunks at 1 level deep.
				return null;
			}
		} elseif ( file_exists( $src ) && is_file( $src ) ) {
			// Skip identical files.
			$src_size  = @filesize( $src );
			$dest_size = @filesize( $dest );
			$md5_src   = @md5_file( $src );
			$md5_dest  = @md5_file( $dest );

			if ( false !== $md5_src && false !== $src_size && $src_size === $dest_size && $md5_src === $md5_dest ) {
				$this->restore['copied'][]  = $dest;
				$this->restore['skipped'][] = $dest;
				return true;
			}

			$rename = $this->get_backup_filename( $dest );
			if ( file_exists( $dest ) && is_file( $dest ) && ! in_array( $rename, $this->restore['cleanup'], true ) ) {
				// Make a backup of the original.
				@rename( $dest, $rename );
				$this->restore['cleanup'][ $dest ] = $rename; // Mark file for cleanup.
			}
			if ( ! @copy( $src, $dest ) ) {
				$success = false;
			} else {
				if ( ! file_exists( $dest ) ) {
					$this->error( 'File copied but failed integrity check: ' . esc_html( $dest ) );
					$success = false;
				} else {
					$this->restore['copied'][] = $dest;
					$this->save();
				}
			}
		} else {
			$this->error( 'Failed to locate source file to restore: ' . esc_html( $src ) );
			$success = false;
		}

		return $success;
	}

	/**
	 * Auto-generate a temporary name for file for backup copy.
	 *
	 * @param string $file  File to create backup name for.
	 *
	 * @return string  New backup filename.
	 */
	private function get_backup_filename( $file ) {
		$backup = $file . '.bak';
		if ( file_exists( $backup ) ) {
			for ( $i = 1; $i <= 99; $i++ ) {
				$backup = $file . '(' . $i . ').bak';
				if ( ! file_exists( $backup ) ) {
					break;
				}
			}
		}
		return $backup;
	}

	/**
	 * Correct file permissions (if necessary).
	 *
	 * @return bool  If successful or not.
	 */
	private function copy_permissions() {
		$success = true;
		$fails   = array();
		$this->log( 'Setting file/folder permissions...' );
		if ( is_array( $this->files ) ) {
			foreach ( $this->files as $file ) {
				if ( ! $file || is_array( $file ) ) {
					continue;
				}

				$file = str_replace( '*', '', $file ); // Remove any wildcard.

				if ( file_exists( $this->restore_path . $file ) && is_dir( $this->restore_path . $file ) ) {
					if ( ! $this->restore_permissions( $this->restore['temp_dir'] . $file, $this->restore_path . $file ) ) {
						$success = false;
						$fails[] = $this->restore['temp_dir'] . $file;
					}
				} else {
					// Copy permissions/owner/group from old file to new file.
					$original_mode  = false;
					$original_owner = false;
					$original_group = false;
					if ( file_exists( $this->restore_path . $file ) ) {
						$original_mode  = (string) substr( sprintf( '%o', fileperms( $this->restore_path . $file ) ), -4 );
						$original_mode  = ! in_array( $original_mode, array( '0777', '1777' ), true ) ? octdec( $original_mode ) : false;
						$original_group = filegroup( $this->restore_path . $file );
					}
					if ( ! $this->restore_permissions( $this->restore['temp_dir'] . $file, $original_mode, $original_owner, $original_group ) ) {
						$success = false;
						$fails[] = $this->restore['temp_dir'] . $file;
					}
				}
			}
		} elseif ( $this->is_full_restore() ) { // Full backup restore.
			if ( ! $this->restore_permissions( $this->restore['temp_dir'], $this->restore_path ) ) {
				$success = false;
				$fails[] = $this->restore['temp_dir'];
			}
		}

		if ( count( $fails ) ) {
			$this->restore['perm_fails'] = $fails;
		} elseif ( $success ) {
			$this->log( '✓ Permissions set successfully.' );
		}
		$this->save();

		return $success;
	}

	/**
	 * Recursively restore original file/folder permissions for given path.
	 *
	 * @param string        $path      Path to file/folder.
	 * @param string|octdec $original  Original File/Folder to copy permissions from.
	 * @param int|false     $owner     Original owner ID.
	 * @param int|false     $group     Original group ID.
	 *
	 * @return bool  If permissions set.
	 */
	private function restore_permissions( $path, $original = false, $owner = false, $group = false ) {
		$success = true;

		if ( ! file_exists( $path ) ) {
			$this->log( '× Attempted to set permissions for non-existent file: `' . $path . '`.' );
			return false;
		}

		// First set permissions for the requested file/folder.
		if ( ! $this->set_wp_permission( $path, $original, $owner, $group ) ) {
			$success = false;
		}

		// Set permissions for subfolders/files.
		if ( is_dir( $path ) ) {
			$files = scandir( $path ) ?: array();
			$files = array_diff( $files, array( '.', '..' ) );
			foreach ( $files as $file ) {
				$file_path = rtrim( $path, '/' ) . '/' . $file;
				if ( false !== $original && is_string( $original ) ) {
					$original_path = rtrim( $original, '/' ) . '/' . $file;
				}
				if ( ! $this->restore_permissions( $file_path, $original_path, $owner, $group ) ) {
					$success = false;
				}
			}
		}

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
				} elseif ( decoct( octdec( $copy_path ) ) === $copy_path ) {
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
		$this->restore = $restore;

		$this->restore['viewed'] = current_time( 'timestamp' );
		$this->abort( true );
		return $this->restore;
	}

	/**
	 * Changes the status of the current restore.
	 *
	 * @param int $new_status  New status code.
	 */
	private function set_status( $new_status ) {
		$restore = $this->refresh( false );
		if ( $this->is_aborted( $restore ) ) {
			return false;
		}
		$this->restore['status'] = $new_status;
		$this->save();
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
			return false;
		}

		if ( ! empty( $restore['aborted'] ) ) {
			$aborted = true;
		} elseif ( self::STATUS_ABORTED === $restore['status'] ) {
			$aborted = true;
		}

		if ( $aborted ) {
			// Make sure status stays aborted.
			if ( ! empty( $this->restore ) && $this->restore['id'] === $restore['id'] && self::STATUS_ABORTED !== $this->restore['status'] ) {
				$this->restore['status'] = self::STATUS_ABORTED;
				$this->save();
			}
		}

		return $aborted;
	}

	/**
	 * Clean up extraction, logging any error messages.
	 *
	 * @param bool $forced  User forced abort.
	 */
	private function abort( $forced = false ) {
		global $wpdb;

		// Clean up tmp folder.
		if ( ! empty( $this->restore['temp_dir'] ) ) {
			pb_backupbuddy::$filesystem->unlink_recursive( $this->restore['temp_dir'] );
		}

		if ( ! empty( $this->restore['cleanup'] ) ) {
			foreach ( $this->restore['cleanup'] as $original_path => $tmp_path ) {
				if ( file_exists( $tmp_path ) ) {
					if ( file_exists( $original_path ) ) {
						pb_backupbuddy::$filesystem->unlink_recursive( $original_path );
					}
					@rename( $tmp_path, $original_path );
				}
			}

			$this->log( 'Restored original files.' );
		}

		if ( true === $forced ) {
			if ( empty( $this->restore['aborted'] ) ) {
				$this->restore['aborted'] = current_time( 'timestamp' );
				$this->log( 'Restore Aborted by user.' );
			}
			$this->set_status( self::STATUS_ABORTED );
		} elseif ( false === $this->is_aborted() ) {
			$this->log( 'Restore Failed.' );
			$this->set_status( self::STATUS_FAILED );
		}

		$this->restore['completed'] = current_time( 'timestamp' );
		if ( ! empty( $this->restore['started'] ) ) {
			$this->restore['elapsed'] = $this->restore['completed'] - $this->restore['started'];
		}
		$this->save();

		$this->disable_maintenance_mode();
		pb_backupbuddy::flush();
	}

	/**
	 * Make a copy of folders that aren't in the backup that need to stay on the site.
	 *
	 * @return bool  If files were preserved.
	 */
	private function preserve_directories() {
		$preserve = false;

		if ( $this->is_full_restore() ) {
			if ( 'media' === $this->restore['profile'] ) {
				$preserve = 'uploads';
			} elseif ( 'plugins' === $this->restore['profile'] ) {
				$preserve = 'plugins';
			} elseif ( 'full' === $this->restore['profile'] ) {
				$preserve = 'both';
			}
		}

		if ( ! $preserve && is_array( $this->files ) ) {
			if ( in_array( 'wp-content/*', $this->files, true ) ) {
				$preserve = 'both';
			} elseif ( in_array( 'wp-content/uploads/*', $this->files, true ) ) {
				$preserve = 'uploads';
			} elseif ( in_array( 'wp-content/plugins/*', $this->files, true ) ) {
				$preserve = 'plugins';
			}
		}

		if ( false === $preserve ) {
			return false;
		}

		$this->log( 'Preserving BackupBuddy Directories...' );

		// Preserve current BackupBuddy Plugin directory.
		if ( in_array( $preserve, array( 'both', 'plugins' ), true ) ) {
			$plugins_path = 'plugins' === $this->restore['profile'] ? '/' : '/wp-content/plugins/';
			$tmp_bub_path = $this->restore['temp_dir'] . $plugins_path . 'backupbuddy';

			// Remove backup/tmp copy of BackupBuddy.
			pb_backupbuddy::$filesystem->unlink_recursive( $tmp_bub_path );

			// Make sure the installed version of BackupBuddy remains after the restore.
			$real_bub_path = backupbuddy_core::get_plugins_root() . 'backupbuddy';
			if ( is_link( $real_bub_path ) ) {
				symlink( readlink( $real_bub_path ), $tmp_bub_path );
			} else {
				pb_backupbuddy::$filesystem->recursive_copy( $real_bub_path, $tmp_bub_path );
			}
			$this->restore['copied'][] = $real_bub_path;
			$this->log( '✓ Preserved BackupBuddy Plugin.' );
		}

		// Preserve BackupBuddy Backups/Logs Directories.
		if ( in_array( $preserve, array( 'both', 'uploads' ), true ) ) {
			$uploads_path = 'media' === $this->restore['profile'] ? '/' : '/wp-content/uploads/';
			pb_backupbuddy::$filesystem->recursive_copy( backupbuddy_core::getBackupDirectory(), $this->restore['temp_dir'] . $uploads_path . 'backupbuddy_backups' );
			pb_backupbuddy::$filesystem->recursive_copy( backupbuddy_core::getLogDirectory(), $this->restore['temp_dir'] . $uploads_path . 'pb_backupbuddy' );
			$this->restore['copied'][] = backupbuddy_core::getBackupDirectory();
			$this->restore['copied'][] = backupbuddy_core::getLogDirectory();
			$this->restore['copied'][] = backupbuddy_core::getTempDirectory(); // Ignore this folder in the backup.
			$this->log( '✓ Preserved BackupBuddy Backups and Logs Directories.' );
		}

		$this->save();

		return true;
	}

	/**
	 * Post restore cleanup.
	 */
	private function cleanup() {
		$this->log( 'Performing cleanup...' );
		$files_successful = $this->restore['file_status'];
		$db_successful    = $this->restore['db_status'];

		if ( ! empty( $this->restore['temp_dir'] ) && file_exists( $this->restore['temp_dir'] ) ) {
			if ( false === pb_backupbuddy::$filesystem->unlink_recursive( $this->restore['temp_dir'] ) ) {
				$this->log( '× Unable to delete temporary holding directory `' . $this->restore['temp_dir'] . '`.' );
				$error = error_get_last();
				$this->log( '× Error was: ' . $error['message'] );
			} else {
				$this->log( '✓ Cleaned up temporary files created from zip extraction.' );
			}
		}

		// Cleanup Folders.
		if ( true === $files_successful ) {
			if ( ! empty( $this->restore['cleanup'] ) ) {
				foreach ( $this->restore['cleanup'] as $remove_dir ) {
					if ( false === pb_backupbuddy::$filesystem->unlink_recursive( $remove_dir ) ) {
						$this->log( '× Unable to cleanup `' . $remove_dir . '`.' );
						$error = error_get_last();
						$this->log( '× Error was: ' . $error['message'] );
					}
				}
			}

			$file_count = count( $this->restore['extract_files'] );

			unset( $this->restore['copied'] );
			unset( $this->restore['cleanup'] );
			$this->restore['extract_files'] = $file_count;

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

		// Database cleanup.
		if ( ! empty( $this->restore['cleanup_db'] ) ) {
			global $wpdb;
			$this->enable_wpdb_errors();

			$this->log( 'Processing final database queries.' );
			$success = true;
			foreach ( $this->restore['cleanup_db'] as $label => $query ) {
				if ( false === $wpdb->query( $query ) ) {
					$this->log_wpdb_error( '× Database cleanup query failed: ' . $label );
					$success = false;
				}
			}

			if ( $success ) {
				$this->log( '✓ Database cleanup complete.' );
			}
		}

		// Make sure a fresh status request is made.
		$this->status_request_complete();
		$this->log( 'Cleanup complete.' );
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
	public function log( $message, $type = 'detail' ) {
		if ( empty( $this->restore ) ) {
			pb_backupbuddy::status( $type, $message );
			return false;
		}

		if ( empty( $this->restore['log'] ) ) {
			$this->restore['log'] = array();
		}

		$hidden = 'hidden' === $type;
		if ( $hidden ) {
			$type = 'detail';
		}

		pb_backupbuddy::status( $type, $message ); // Log globally for good measure.

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
			$extra = 'full' !== $restore['profile'] ? ' (' . $restore['profile'] . ')' : ( 'both' !== $restore['what'] ) ? ' (' . $restore['what'] . ')' : '';
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
	 * @param array  $restore   Restore array.
	 * @param string $use_text  Text to display (default is status text).
	 * @param bool   $echo      Echo or return.
	 *
	 * @return string  Status HTML.
	 */
	public function get_status_html( $restore, $use_text = false, $echo = false ) {
		$text = false === $use_text ? $this->get_status_text( $restore['status'] ) : $use_text;

		if ( in_array( $restore['status'], $this->get_completed_statuses(), true ) ) {
			$html = sprintf( '<a href="#restore-details-%s">%s</a>', esc_attr( $restore['id'] ), esc_html( $text ) );
		} elseif ( $restore['status'] < self::STATUS_COMPLETE ) {
			$html = sprintf( '<span data-restore-id="%s" class="restore-in-progress">%s</span>', esc_attr( $restore['id'] ), esc_html( $text ) );
		} else {
			$html = false === $use_text ? '' : $text;
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

		if ( self::STATUS_NOT_STARTED === $status_code ) {
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
		} elseif ( self::STATUS_ABORTED === $status_code ) {
			$text = __( 'Aborted', 'it-l10n-backupbuddy' );
		}

		if ( true !== $echo ) {
			return $text;
		}

		echo $text;
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
			$this->restore['temp_dir'] . 'wp-content/uploads/temp_' . $this->serial . '/',
			// Full backup >= v2.0.
			$this->restore['temp_dir'] . 'wp-content/uploads/backupbuddy_temp/' . $this->serial . '/',
			// Determined from detecting DAT file. Should always be the location really... As of v4.1.
			$this->restore['temp_dir'],
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

		$importbuddy    = new pb_backupbuddy_mysqlbuddy( DB_HOST, DB_NAME, DB_USER, DB_PASSWORD, $this->table_prefix );
		$table_basename = preg_replace( '/' . preg_quote( $wpdb->prefix ) . '/', '', $table, 1 );
		$dependencies   = $this->get_table_dependency( $import_file );

		// If any dependencies has not been imported yet and is scheduled to import, move to end.
		if ( $dependencies && empty( array_intersect( $dependencies, $this->restore['imported_tables'] ) ) ) {
			$not_queued = array_intersect( $dependencies, $this->restore['table_queue'] );
			if ( ! empty( $not_queued ) ) {
				if ( ! isset( $this->restore['last_tables'][ $table ] ) ) {
					$this->restore['last_tables'][ $table ] = $dependencies;
					$this->log( 'Shifting import for `' . $table . '` to the end of the queue due to table dependency.' );
					$this->save();
				}
				return true;
			} else {
				$this->log( 'WARNING: Table `' . $table . '` contains dependency on `' . implode( $not_queued, '`, `' ) . '` which is not queued for import.' );
			}
		}

		$this->handle_constraint( $import_file, $table );
		$result = $importbuddy->import( $import_file, $wpdb->prefix, $continued, $ignore_errors );

		// More work to be done.
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
		if ( ! $this->table_exists( $this->table_prefix . $table_basename ) ) {
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

		$this->restore['imported_tables'][] = $table;
		$this->save();
		return true;
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
			$this->restore['cleanup_db'][ 'Constraint ' . $num . ' for `' . $table . '`' ] = sprintf( 'ALTER TABLE `%s` ADD %s;', $table, $constraint );
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
			// Force new status check.
			backupbuddy_restore()->status_request_complete();
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

			if ( $table === $wpdb->options ) {
				// Copy over cron option value to prevent malfunctions during restore.
				$restored_options = $this->table_prefix . $table_basename;
				$cron_value       = $wpdb->get_var( "SELECT `option_value` FROM {$wpdb->options} WHERE `option_name` = 'cron';" );
				if ( $cron_value ) {
					$wpdb->query( $wpdb->prepare( "UPDATE `$restored_options` SET `option_value` = %s WHERE `option_name` = 'cron';", $cron_value ) );
				}
			} elseif ( $table === $wpdb->usermeta ) {
				// Copy over session tokens so user isn't logged out.
				$restored_usermeta = $this->table_prefix . $table_basename;
				$session_tokens    = $wpdb->get_var( $wpdb->prepare( "SELECT `meta_value` FROM {$wpdb->usermeta} WHERE `user_id` = %s AND `meta_key` = 'session_tokens';", get_current_user_id() ) );
				if ( $session_tokens ) {
					$wpdb->query( $wpdb->prepare( "UPDATE `$restored_usermeta` SET `meta_value` = %s WHERE `meta_key` = 'session_tokens' AND `user_id` = %s;", $session_tokens, get_current_user_id() ) );
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
			} else {
				// Rename Imported to Original.
				$table_swap = sprintf( "RENAME TABLE `%s` TO `%s`;", $this->table_prefix . $table_basename, $table );
			}

			if ( false === $wpdb->query( $table_swap ) ) {
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
			return null;
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
			pb_backupbuddy::disalert( $alert_id, esc_html__( 'WARNING: BackupBuddy has detected potential issues with creating new directories on your server. Double check file/folder permissions set here: ', 'it-l10n-backupbuddy' ) . $settings_link, true, '', array( 'class' => 'below-h2' ) );
		}

		return $passed;
	}
}
