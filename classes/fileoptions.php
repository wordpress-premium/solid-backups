<?php
/* Class pb_backupbuddy_fileoptions
 *
 * @author Dustin Bolton
 * @date April, 2013
 *
 * Uses the filesystem for storing options data. Data is serialized & base64 encoded.
 * By default uses a locking mechanism to lock out accessing the options from another instance
 * while open with this instance. Lock automatically removed on class destruction.
 *
 * After construction check is_ok() function to verify === true. If not true returns error message.
 *
 * Example usage:
 * $backup_options = new pb_backupbuddy_fileoptions( $filename );
 * if ( $backup_options->is_ok() ) {
 * 	$backup_options->options = array( 'hello' => 'world' );
 * 	$backup_options->save(); // Optional force save now. If omitted destructor will hopefully save.
 * }

 Another in-use example:

pb_backupbuddy::status( 'details', 'About to load fileoptions data.' );
require_once( pb_backupbuddy::plugin_path() . '/classes/fileoptions.php' );
$fileoptions_obj = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/send-' . $send_id . '.txt', $read_only = true, $ignore_lock = true, $create_file = false );
if ( true !== ( $result = $fileoptions_obj->is_ok() ) ) {
	pb_backupbuddy::status( 'error', __('Fatal Error #9034.2344848. Unable to access fileoptions data.', 'it-l10n-backupbuddy' ) . ' Error: ' . $result );
	return false;
}
pb_backupbuddy::status( 'details', 'Fileoptions data loaded.' );
$fileoptions = &$fileoptions_obj->options;

 *
 */

class pb_backupbuddy_fileoptions {

	public $options = array(); // Current options.
	private $_options_hash = ''; // Hold hash of options so we don't perform file operations needlessly if things have not changed.
	private $_file; // Filename options are stored in.
	private $_is_ok = 'UNKNOWN'; // true on error; error message otherwise
	private $_read_only = false;
	private $_loaded = false; // Has file been succesfully loaded yet?
	private $_live = false; // Stash Live mode.
	private $_my_lock_id = ''; // Track my lock ID for this instance so we do not unlock for other instances/page loads by default.

	/* __construct()
	 *
	 * Reads and creates file lock. If file does not exist, creates it. Places options into this class's $options.
	 *
	 * @param	string		$file			Full filename to save fileoptions into.
	 * @param	bool		$read_only		true read only mode; false writable.
	 * @param	bool		$ignore_lock	When true ignore file locking. default: false
	 * @param	bool		$create_file	Create file if it does not yet exist and mark is_ok value to true.
	 * @return	null
	 *
	 */
	function __construct( $file, $read_only = false, $ignore_lock = false, $create_file = false, $live_mode = false ) {
		$this->_file = $file;
		$this->_read_only = $read_only;

		// If read-only then ignore locks is forced.
		if ( $read_only === true ) {
			$ignore_lock = true;
		}

		if ( ! file_exists( dirname( $file ) ) ) { // Directory exist?
			pb_backupbuddy::anti_directory_browsing( dirname( $file ), $die_on_fail = false, $deny_all = true );
		}

		/*
		if ( ! file_exists( $file ) ) { // File exist?
			//$this->save();
		}
		*/

		if ( true === $live_mode ) {
			$this->_live = true;
		}

		$before_memory = memory_get_usage() / 1048576;
		$this->load( $ignore_lock, $create_file );
		$after_memory = memory_get_usage() / 1048576;
		pb_backupbuddy::status( 'details', 'Fileoptions load using ' . round( $after_memory - $before_memory, 2 ) . ' MB of memory.' );

	} // End __construct().



	/* __destruct()
	 *
	 * Saves options on destruction.
	 *
	 * @return null
	 *
	 */
	function __destruct() {

		// IMPORTANT: We can NOT rely on any outside classes from here on out such as the framework status method. Pass true to unlock to insure it does not perform any status logging.
		$this->unlock( $destructorCalled = true );

	} // End __destruct().



	/* is_ok()
	 *
	 * Determine whether options was loaded correctly and is ok.
	 *
	 * @return true\string		True on valid, else returns error message string.
	 *
	 */
	public function is_ok() {

		return $this->_is_ok;

	} // End is_ok().



	/* load()
	 *
	 * Load options from file. Use is_ok() to verify integrity. If is_ok() !== true, returns error message.
	 *
	 * @param	bool|int		$ignore_lock	Whether or not to ignore the file being locked. If int then the lock will be ignored ONLY if fileoptions has not been modified in this number of seconds based on filemtime.
	 * @param	bool		$create_file	Create file if it does not yet exist and mark is_ok value to true.
	 * @param	int			$retryCount		If ERROR_EMPTY_FILE_NON_CREATE_MODE error then we will retry a couple of times after a slight pause in case there was a race condition while another process was updating the file.
	 * @return	bool		true on load success, else false.
	 *
	 */
	public function load( $ignore_lock = false, $create_file = false, $retryCount = 0 ) {

		// Check if file exists.
		if ( ! file_exists( $this->_file ) ) {
			@unlink( $this->_file . '.lock' ); // fileoptions file did not exist so delete lock file for it if it exists.
			if ( true !== $create_file ) {
				pb_backupbuddy::status( 'warning', 'Warning #3489347944: Fileoptions file `' . $this->_file . '` not found and NOT in create mode. Verify file exists & check permissions.' );
				$this->_is_ok = 'ERROR_FILE_MISSING_NON_CREATE_MODE';
				return false;
			}
			$options = '';
		}

		// If NOT ignoring lock (false|int) AND current locked.
		if ( ( true !== $ignore_lock ) && ( true === $this->is_locked() ) ) {

			// Not set to ignore lock.
			if ( false === $ignore_lock ) {
				sleep( 1 ); // Wait 1 second befoe trying again.
				if ( true === $this->is_locked() ) { // Check lock one more time.
					sleep( 3 ); // Wait 1 second before trying again.
					if ( true === $this->is_locked() ) { // Check lock one more time.
						pb_backupbuddy::status( 'warning', 'Warning #54555. Unable to read fileoptions file `' . $this->_file . '` as it is currently locked. Lock file ID: `' . $this->_last_seen_lock_id . '`. My lock ID: `' . $this->_my_lock_id . '`.' );
						$this->_is_ok = 'ERROR_LOCKED';
						return false;
					}
				}
			}

			// Ignore lock only if file is unmodified for this number of seconds.
			if ( is_numeric( $ignore_lock ) ) {
				if ( false === ( $modified = @filemtime( $this->_file ) ) ) {
					if ( ! file_exists( $this->_file ) ) {
						pb_backupbuddy::status( 'error', 'Error #83494984: fileoptions file `' . $this->_file . '` trying to be read was not found.' );
					} else {
						pb_backupbuddy::status( 'warning', 'Warning #5349847. Unable to read fileoptions file MTIME of locked file `' . $this->_file . '`. Lock file ID: `' . $this->_last_seen_lock_id . '`. My lock ID: `' . $this->_my_lock_id . '`.' );
					}
					$this->_is_ok = 'ERROR_CANT_READ_MTIME';
					return false;
				}
				if ( ( time() - $modified ) < $ignore_lock ) {
					pb_backupbuddy::status( 'warning', 'Warning #54556. Unable to read fileoptions file `' . $this->_file . '` as it is currently locked AND not enough time has passed (time passed: `' . ( time() - $modified ) . '`, limit: `' . $ignore_lock . '`). This is often caused by an earlier step dying, whether due to TIMING OUT, RUNNING OUT OF MEMORY, or BEING KILLED BY THE SERVER, or OTHER PHP ERROR. Find the last line(s) to run before warnings began to see what was the last thing to run before failure. Increase memory if nearing memory limit, decrease max operation time Stash Live Advanced Settings if server is overreporting available runtime. Check PHP error logs. Lock file ID: ' . $this->_last_seen_lock_id . '. My lock ID: `' . $this->_my_lock_id . '`.' );
					pb_backupbuddy::status( 'action', 'possible_timeout' );
					$this->_is_ok = 'ERROR_LOCKED';
					return false;
				}
				pb_backupbuddy::status( 'warning', 'Fileoptions file was locked `' . ( time() - $modified ) . '` seconds ago. Wait time of `' . $ignore_lock . '` seconds was exceeded so unlocking.' );
				$this->unlock( false, $only_unlock_my_id = false ); // $only_unlock_my_id = false forces unlock since locking process likely timed out.
			}

		}


		// Only lock when not in read-only mode.
		if ( false === $this->_read_only ) {

			$lock_results = $this->_lock();

			$lock_tries = 0;
			$lock_tries_limit = 5;
			while( ( $lock_tries < $lock_tries_limit ) && ( false === $lock_results ) ) {
				pb_backupbuddy::status( 'warning', 'Warning #34893484: Trying to lock already locked file. Ignore this warning of the process proceeds. Retry attempts: `' . $lock_tries . '` of `' . $lock_tries_limit . '`.' );
				$lock_tries++;
				sleep( 1 );
				$lock_results = $this->_lock();
			}

			if ( false === $lock_results ) { // If lock fails (possibly due to existing lock file) then fail load.
				$this->_is_ok = 'ERROR_UNABLE_TO_LOCK';
				return false;
			}
		}

		// BackupBuddy Stash Live mode. (one array entry per line for performance/memory usage).
		if ( true === $this->_live ) {
			$found_start = false;
			$found_end = false;
			$found_corrupt = false;

			if ( ! isset( $options ) ) { // Not set so file exists. Read in data.
				if ( false === ( $fso = @fopen( $this->_file, 'r' ) ) ) {
					pb_backupbuddy::status( 'error', 'Error #4394974: Unable to read fileoptions file `' . $this->_file . '` in Live mode. Verify permissions on this directory.' );
					$this->_is_ok = 'ERROR_READ';
					@fclose( $fso );
					return false;
				}
				if ( ! is_array( $this->options ) ) {
					$this->options = array();
				}
				while ( false !== ( $buffer = fgets( $fso ) ) ) {
					if ( ( false === $found_start ) && ( "FILEOPTIONS_START\n" == $buffer ) ) { // Check for start marker -- know we should have an end marker as well in this case (as of BB v7.2.2.2).
						$found_start = true;
						continue;
					}
					if ( ( true === $found_start ) && ( false === $found_end ) && ( 'FILEOPTIONS_END' == $buffer ) ) { // Check for end (as of BB v7.2.2.2). Only bother to check if found start.
						$found_end = true;
						continue;
					}

					$buffer = explode( '|', $buffer );
					/*
					if ( false === ( $data = base64_decode( $buffer[1] ) ) ) {
						pb_backupbuddy::status( 'error', 'Error #43974334: Unable to base64 decode data from fileoptions file `' . $this->_file . '`. Live mode.' );
						$this->_is_ok = 'ERROR_BASE64_DECODE';
						@fclose( $fso );
						return false;
					}
					if ( false === ( $data = maybe_unserialize( $data ) ) ) {
						pb_backupbuddy::status( 'error', 'Error #43979434: Unable to unserialize data from fileoptions file `' . $this->_file . '`. Live mode.' );
						$this->_is_ok = 'ERROR_UNSERIALIZE';
						@fclose( $fso );
						return false;
					}
					*/
					if ( ! isset( $buffer[1] ) ) {
						$error = 'BackupBuddy Error #893484: Corrupt fileoptions line skipped in `' . $this->_file .  '`: `' . print_r( $buffer, true ) . '`.';
						error_log( $error );
						pb_backupbuddy::status( 'error', $error );
						$found_corrupt = true;
						continue;
					}
					if ( false === ( $data = json_decode( $buffer[1], true ) ) ) {
						pb_backupbuddy::status( 'error', 'Error #43843843743: Unable to decode json data from fileoptions file `' . $this->_file . '`. Live mode.' );
						$this->_is_ok = 'ERROR_UNSERIALIZE';
						@fclose( $fso );
						return false;
					}
					$this->options[ $buffer[0] ] = $data;
				}
				if ( ! feof( $fso ) ) { // Made it here despite not being to end. Error!
					pb_backupbuddy::status( 'error', 'Error #438975532: Unable to read fileoptions file `' . $this->_file . '` in Live mode. fgets failed before EOF! Verify permissions on this directory.' );
					$this->_is_ok = 'ERROR_READ';
					@fclose( $fso );
					return false;
				}

				@fclose( $fso );
			}

			// Corrupt line encountered OR ( found start marker but did not find end marker ). If file seems incomplete or corrupted then we will check the .bak catalog backup and load it if its filesize is larger. Help avoid restarting process too far back.
			if ( ( true === $found_corrupt ) || ( ( true === $found_start ) && ( false === $found_end ) ) ) {
				pb_backupbuddy::status( 'warning', 'Live catalog detected corruption or incomplete file. Checking if we can load backup catalog instead.' );

				// Check if .bak backup file is larger than this catalog file.
				if ( false !== ( $current_fileoptions_size = @filesize( $this->_file ) ) ) {
					if ( false !== ( $backup_fileoptions_size = @filesize( $this->_file . '.bak' ) ) ) {
						if ( $backup_fileoptions_size > $current_fileoptions_size ) { // Backup is larger. Load it instead.
							pb_backupbuddy::status( 'warning', 'Live backup catalog is larger than current catalog. Copying over and re-loading backup instead.' );

							// Copy over file.
							if ( true === @copy( $this->_file . '.bak', $this->_file ) ) {
								pb_backupbuddy::status( 'warning', 'Restored previous catalog backup. Loading...' );
								// Unlock fileoptions.
								$this->unlock();

								return $this->load( $ignore_lock, $create_file, $retryCount );
							} else {
								pb_backupbuddy::status( 'error', 'Error #84949834: Failed restoring previous catalog backup. Could not copy.' );
							}

						} else {
							pb_backupbuddy::status( 'warning', 'Live backup catalog is SMALLER than current catalog. NOT switching to backup.' );
						}
					} else {
						pb_backupbuddy::status( 'warning', 'Warning #3328733: Unable to get size of backup Live catalog `' . $this->_file . '.bak' . '`.' );
					}
				} else {
					pb_backupbuddy::status( 'warning', 'Warning #3489239823: Unable to get size of current Live catalog `' . $this->_file . '`.' );
				}

			}

			$this->_is_ok = true;
			$this->_loaded = true;

			return true;
		}

		if ( ! isset( $options ) ) { // File exists so try to load.
			$options = @file_get_contents( $this->_file );
		}

		if ( false === $options ) {
			pb_backupbuddy::status( 'error', 'Error #3894843: Unable to read fileoptions file `' . $this->_file . '`. Verify permissions on this directory.' );
			$this->_is_ok = 'ERROR_READ';
			return false;
		}
		if ( false === ( $options = base64_decode( $options ) ) ) {
			pb_backupbuddy::status( 'error', 'Error #8965576: Unable to base64 decode data from fileoptions file `' . $this->_file . '`.' );
			$this->_is_ok = 'ERROR_BASE64_DECODE';
			return false;
		}
		if ( false === ( $options = maybe_unserialize( $options ) ) ) {
			pb_backupbuddy::status( 'error', 'Error #8956895665: Unable to unserialize data from fileoptions file `' . $this->_file . '`.' );
			$this->_is_ok = 'ERROR_UNSERIALIZE';
			return false;
		}

		if ( true === $create_file ) {
			$this->_is_ok = true;
		} elseif ( '' != $options ) {
			$this->_is_ok = true;
		} else {
			$this->_is_ok = 'ERROR_EMPTY_FILE_NON_CREATE_MODE';
			if ( $retryCount < 2 ) { // Give it one more chance by sleeping then loading once more. Return whatever result that gives.
				$this->unlock();
				$retryCount++;
				pb_backupbuddy::status( 'details', 'Fileoptions file was EMPTY. Unlocking & sleeping momentarily and then trying again. Attempt #1' . $retryCount );
				sleep( 3 );
				return $this->load( $ignore_lock, $create_file, $retryCount );
			}
		}
		if ( is_array( $options ) ) {
			$this->options = $options;
		}
		$this->_loaded = true;
		$this->_options_hash = md5( serialize( $options ) );

		return true;
	} // End load();



	/* save()
	 *
	 * Save the options into file now without removing lock.
	 *
	 * @param		bool	$remove_lock	When true the lock will be removed as well. default: false
	 * @return		bool					true on save success, else false.
	 *
	 */
	public function save( $remove_lock = false, $only_save_my_lock_id = true ) {

		if ( '' == $this->_file ) { // No file set yet. Just return.
			return true;
		}

		if ( true === $this->_read_only ) {
			pb_backupbuddy::status( 'error', 'Attempted to write to fileoptions while in readonly mode; denied.' );
			return false;
		}

		if ( false === $this->_loaded ) { // Skip saving if we have not successfully loaded yet to prevent overwriting data.
			return false;
		}


		// If file is locked and we ae only saving when it's our own lock ID then make sure IDs match.
		if ( ( true === $this->is_locked() ) && ( false !== $only_save_my_lock_id ) ) {
			if ( ( $this->_last_seen_lock_id != $this->_my_lock_id ) || ( '' == $this->_my_lock_id ) ) {
				pb_backupbuddy::status( 'error', 'Error #43894384: Unable to write to fileoptions as file `' . $this->_file . '` is currently locked by another fileoptions instance/process. Last seen lock ID: `' . $this->_last_seen_lock_id . '`. My lock ID: `' . $this->_my_lock_id . '`.' );
				return false;
			}
		}


		// BackupBuddy Stash Live mode. (one array entry per line for performance/memory usage).
		if ( true === $this->_live ) {

			// Open for writing.
			if ( false === ( $fso = @fopen( $this->_file, 'w' ) ) ) {
				pb_backupbuddy::status( 'error', 'Error #83494757: Unable to open fileoptions file `' . $this->_file . '` for writing. Verify permissions.' );
				if ( true === $remove_lock ) {
					$this->unlock();
				}
				@fclose( $fso );
				return false;
			}

			$bytesWritten = 0;

			// Write file start marker (new in data version 2 (BB v7.2.2.2)
			if ( false === ( $written = @fwrite( $fso, "FILEOPTIONS_START\n" ) ) ) {
				pb_backupbuddy::status( 'error', 'Error #329823955: Unable to write to fileoptions file `' . $this->_file . '`. Verify permissions.' );
				if ( true === $remove_lock ) {
					$this->unlock();
				}
				@fclose( $fso );
				return false;
			} else { // Wrote.
				$bytesWritten += $written;
			}

			foreach( $this->options as $optionKey => $optionItem ) {
				$optionItem = json_encode( $optionItem, JSON_FORCE_OBJECT );

				if ( false === ( $written = @fwrite( $fso, $optionKey . '|' . $optionItem . "\n" ) ) ) {
					pb_backupbuddy::status( 'error', 'Error #839494455: Unable to write to fileoptions file `' . $this->_file . '`. Verify permissions.' );
					if ( true === $remove_lock ) {
						$this->unlock();
					}
					@fclose( $fso );
					return false;
				} else { // Wrote.
					$bytesWritten += $written;
				}
			}

			// Write file end marker (new in data version 2 (BB v7.2.2.2)
			if ( false === ( $written = @fwrite( $fso, 'FILEOPTIONS_END') ) ) {
				pb_backupbuddy::status( 'error', 'Error #32893355: Unable to write to fileoptions file `' . $this->_file . '`. Verify permissions.' );
				if ( true === $remove_lock ) {
					$this->unlock();
				}
				@fclose( $fso );
				return false;
			} else { // Wrote.
				$bytesWritten += $written;
			}

			// Success.
			pb_backupbuddy::status( 'details', 'Fileoptions `' . basename( $this->_file ) . '` saved (Live mode). ' . $bytesWritten . ' bytes written.' );
			//$this->_options_hash = $options_hash;
			if ( true === $remove_lock ) {
				$this->unlock();
			}
			@fclose( $fso );
			return true;
		}



		$serialized = serialize( $this->options );
		$options_hash = md5( $serialized );

		if ( $options_hash == $this->_options_hash ) { // Only update if options has changed so if equal then no change so return.
			if ( true === $remove_lock ) {
				$this->unlock();
			}
			return true;
		}

		$options = base64_encode( $serialized );

		if ( false === ( $bytesWritten = file_put_contents( $this->_file, $options ) ) ) { // unable to write.
			pb_backupbuddy::status( 'error', 'Error #478374745: Unable to write fileoptions file `' . $this->_file . '`. Verify permissions.' );
			if ( true === $remove_lock ) {
				$this->unlock();
			}
			return false;
		} else { // wrote to file.
			pb_backupbuddy::status( 'details', 'Fileoptions `' . basename( $this->_file ) . '` saved. ' . $bytesWritten . ' bytes written.' );
			$this->_options_hash = $options_hash;
			if ( true === $remove_lock ) {
				$this->unlock();
			}
			return true;
		}

	} // End save().



	/* _lock()
	 *
	 * Lock file.
	 *
	 * @return		bool	true on lock success, else false.
	 *
	 */
	private function _lock() {

		$lockFile = $this->_file . '.lock';

		if ( true === $this->_read_only ) {
			pb_backupbuddy::status( 'error', 'Error #348943784: Attempted to lock fileoptions while in readonly mode; denied.' );
			return false;
		}

		$handle = @fopen( $lockFile, 'x' );
		if ( false === $handle ) { // Failed creating file.
			if ( file_exists( $lockFile ) ) {
				$this->_last_seen_lock_id = @file_get_contents( $lockFile );
				pb_backupbuddy::status( 'warning', 'Warning #437479545: Unable to create fileoptions lock file as it already exists: `' . $lockFile . '`. Lock file ID: ' . $this->_last_seen_lock_id . '.' );
			} else {
				pb_backupbuddy::status( 'error', 'Error #48943743: Unable to create fileoptions lock file `' . $lockFile . '`. Verify permissions on this directory.' );
			}
			return false;
		} else { // Created file.
			$lockID = uniqid( '', true );
			if ( false === @fwrite( $handle, $lockID ) ) {
				pb_backupbuddy::status( 'warning', 'Warning #38943944444: Unable to write unique lock ID `' . $lockID . '` to lock file `' . $lockFile . '`.' );
			} else {
				$this->_my_lock_id = $lockID;
				pb_backupbuddy::status( 'details', 'Created fileoptions lock file `' . basename( $lockFile ) . '` with ID: ' . $lockID . '.' );
			}
			@fclose( $handle );
		}

	}



	/* unlock()
	 *
	 * Unlock file.
	 * WARNING!!! IMPORTANT!!! We cannot reliably call pb_backupbuddy::status() here _IF_ calling via destructor, $destructorCalled = true.
	 *
	 * @param		bool	$destructorCalled		Whether or not this function was called via the destructor. See warning in comments above.
	 * @return		bool	true on unlock success, else false.
	 *
	 */
	public function unlock( $destructorCalled = false, $only_unlock_my_id = true ) {

		$lockFile = $this->_file . '.lock';

		if ( file_exists( $lockFile ) ) { // Locked; continue to unlock;
			$this->_last_seen_lock_id = @file_get_contents( $lockFile );

			if ( false !== $only_unlock_my_id ) {
				if ( ( $this->_last_seen_lock_id != $this->_my_lock_id ) || ( '' == $this->_my_lock_id ) ) {
					//pb_backupbuddy::status( 'details', 'Lock status: Last seen lock ID: `' . $this->_last_seen_lock_id . '`. My lock ID: `' . $this->_my_lock_id . '`.' );
					return false;
				}
			}

			$result = @unlink(  $lockFile );
			if ( true === $result ) { // Unlocked.
				$this->_my_lock_id = '';
				if ( false === $destructorCalled ) {
					pb_backupbuddy::status( 'details', 'Unlocked fileoptions lock file `' . basename( $lockFile ) . '` in with lock ID `' . $this->_last_seen_lock_id . '`.' );
				}
				return true;
			} else {
				if ( class_exists( 'pb_backupbuddy' ) ) {
					if ( false === $destructorCalled ) {
						pb_backupbuddy::status( 'error', 'Unable to delete fileoptions lock file `' . basename( $lockFile ) . '` with lock ID `' . $this->_last_seen_lock_id . '`. Verify permissions on this file / directory.' );
					}
				}
				return false;
			}
		} else { // File already unlocked.
			return true;
		}

	} // End unlock().



	/* is_locked()
	 *
	 * Is this file locked / in use?
	 *
	 * @return		bool		Whether or not file is currenty locked.
	 *
	 */
	public function is_locked() {

		$lockFile = $this->_file . '.lock';

		if ( file_exists( $lockFile ) ) {
			$this->_last_seen_lock_id = @file_get_contents( $lockFile );
			return true;
		} else {
			return false;
		}

	} // End is_locked().



} // End class pb_backupbuddy_fileoptions.