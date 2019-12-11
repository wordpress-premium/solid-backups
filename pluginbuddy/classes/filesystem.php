<?php
/**
 * Handles interfacing with the file system.
 *
 * @author Dustin Bolton
 * @package BackupBuddy
 */

/**
 * File System Class
 */
class pb_backupbuddy_filesystem {

	/**
	 * A mkdir function that defaults to recursive behaviour. 99% of the time this is what we want.
	 *
	 * @param string $pathname   Path of directory to create.
	 * @param int    $mode       Default: 0755. See PHP's mkdir() function for details.
	 * @param bool   $recursive  Default: true. See PHP's mkdir() function for details.
	 *
	 * @return bool  Returns TRUE on success or FALSE on failure.
	 */
	public static function mkdir( $pathname, $mode = 0755, $recursive = true ) {
		// Attempt to make the directory with the passed mode
		$result = @mkdir( $pathname, $mode, $recursive );

		// Specifically chmod the directory to the correct mode. This is needed on some systems.
		if ( true == $result ) {
			@chmod( $pathname, $mode );
		}
		return $result;
	} // End mkdir().

	/**
	 * Unlink a directory recursively. Files all files and directories within. USE WITH CAUTION.
	 *
	 * @param string $dir  Directory to delete -- all contents within, subdirectories, files, etc will be permanently deleted.
	 *
	 * @return bool  True on success; else false.
	 */
	public function unlink_recursive( $dir ) {
		if ( defined( 'PB_DEMO_MODE' ) ) {
			return false;
		}

		if ( ! file_exists( $dir ) ) {
			return true;
		}
		if ( ! is_dir( $dir ) || is_link( $dir ) ) {
			@chmod( $dir, 0777 );
			return unlink( $dir );
		}
		foreach ( scandir( $dir ) as $item ) {
			if ( '.' == $item || '..' == $item ) {
				continue;
			}
			if ( ! $this->unlink_recursive( $dir . '/' . $item ) ) {
				@chmod( $dir . '/' . $item, 0777 );
				if ( ! $this->unlink_recursive( $dir . '/' . $item ) ) {
					return false;
				}
			}
		}
		return @rmdir( $dir );
	} // End unlink_recursive().

	/**
	 * Like the glob() function except walks down into paths to create a full listing of all results in the directory and all subdirectories.
	 * This is essentially a recursive glob() although it does not use recursion to perform this.
	 *
	 * @param string $dir       Path to pass to glob and walk through.
	 * @param array  $excludes  Array of directories to exclude, relative to the $dir.  Include beginning slash. No trailing slash.
	 *
	 * @return array  Returns array of all matches found.
	 */
	public function deepglob( $dir, $excludes = array() ) {
		$dir      = rtrim( $dir, '/\\' ); // Make sure no trailing slash.
		$excludes = str_replace( $dir, '', $excludes );
		$dir_len  = strlen( $dir );

		$items = glob( $dir . '/*' );
		if ( false === $items ) {
			$items = array();
		}
		for ( $i = 0; $i < count( $items ); $i++ ) { // $items changes within loop.
			// If this file/directory begins with an exclusion then jump to next file/directory.
			foreach ( $excludes as $exclude ) {
				if ( backupbuddy_core::startsWith( substr( $items[ $i ], $dir_len ), $exclude ) ) {
					unset( $items[ $i ] );
					continue 2;
				}
			}

			if ( is_dir( $items[ $i ] ) ) {
				$add = glob( $items[ $i ] . '/*' );
				if ( false === $add ) {
					$add = array();
				}
				$items = array_merge( $items, $add );
			}
		}

		return $items;
	} // End deepglob().

	/**
	 * Like the glob() function except walks down into paths to create a full listing of all results in the directory and all subdirectories.
	 * This is essentially a recursive glob() although it does not use recursion to perform this.
	 *
	 * @param string $dir         Path to pass to glob and walk through.
	 * @param array  $excludes    Array of directories to exclude, relative to the $dir.  Include beginning slash. No trailing slash.
	 * @param int    $start_at    Offset to start calculating from for resumed chunking. $items must also be passed from previous run.
	 * @param array  $items       Array of items to use for resuming. Returned by this function when chunking.
	 * @param int    $start_time  Timestamp to calculate elapsed runtime from.
	 * @param int    $max_time    Max seconds to run for before returning for chunking if approaching. Zero (0) to disabling chunking. IMPORTANT: Does _NOT_ apply a wiggle room. Subtract wiggle from $max_time before passing.
	 *
	 * @return array|string  String error message OR Returns array of all matches found OR array( $finished = false, array( $start_at, $items ) ) if chunking due to running out of time.
	 */
	public function deepscandir( $dir, $excludes = array(), $start_at = 0, $items = array(), $start_time = 0, $max_time = 0 ) {

		$dir      = rtrim( $dir, '/\\' ); // Make sure no trailing slash.
		$excludes = str_replace( $dir, '', $excludes );
		$dir_len  = strlen( $dir );

		// If not resuming a chunked process then get items.
		if ( ! is_array( $items ) || 0 == count( $items ) ) {
			$items = scandir( $dir );
			if ( false === $items ) {
				$items = array();
			} else {
				foreach ( $items as $i => &$item ) {
					if ( '.' == $item || '..' == $item ) {
						unset( $items[ $i ] );
						continue;
					}
					$item = $dir . '/' . $item; // Add directory.
				}
			}
			$items = array_values( $items ); // Remove missing keyed items.
		} else {
			pb_backupbuddy::status( 'details', 'Deep scan resuming at `' . $start_at . '`.' );
		}

		for ( $i = $start_at; $i < count( $items ); $i++ ) { // $items is modified in loop.

			// If this file/directory begins with an exclusion then jump to next file/directory.
			foreach ( $excludes as $exclude ) {
				if ( backupbuddy_core::startsWith( substr( $items[ $i ], $dir_len ), $exclude ) ) {
					$items[ $i ] = '';
					continue 2;
				}
			}

			if ( is_dir( $items[ $i ] ) ) {
				$adds = scandir( $items[ $i ] );
				if ( ! is_array( $adds ) ) {
					$adds = array();
				} else {
					foreach ( $adds as $j => &$add_item ) {
						if ( '.' == $add_item || '..' == $add_item ) {
							unset( $adds[ $j ] );
							continue;
						}
						$add_item = $items[ $i ] . '/' . $add_item; // Add directory.
					}
				}
				$items = array_merge( $items, $adds );
			}

			// Check if enough time remains to continue, else chunk.
			if ( 0 != $max_time ) { // Chunking enabled.
				if ( ( time() - $start_time ) > $max_time ) { // Not enough time left.
					if ( $i == $start_at ) { // Did not increase position.
						if ( $max_time < 0 ) {
							pb_backupbuddy::status( 'error', 'Error #834743745: Max time negative. This usually means the max PHP time for this server is exceptionally low. Check max PHP runtime to verify it is 30 seconds or more.' );
						}
						$error = 'Error #34848934: No progress was made during file scan. Halting to prevent looping repeatedly at beginning of deep scan. Elapsed: `' . ( time() - $start_time ) . '`. Max time: `' . $max_time . '`. startAt: `' . $start_at . '`. Items count: `' . count( $items ) . '`.';
						pb_backupbuddy::status( 'error', $error );
						return $error;
					}
					$start_at = $i;
					pb_backupbuddy::status( 'details', 'Running out of time calculating deep file scan. Chunking at position `' . $start_at . '`. Items so far: `' . count( $items ) . '`. Elapsed: `' . ( time() - $start_time ) . '` secs. Max time: `' . $max_time . '` secs.' );
					return array( false, array( ( $i + 1 ), $items ) );
				}
			}
		} // end for.

		return array_filter( $items ); // Removed any empty values (excludes items).
	} // End deepscandir().

	/**
	 * Recursive Copy
	 *
	 * @param string $src  Source directory.
	 * @param string $dst  Destination directory.
	 */
	public function recursive_copy( $src, $dst ) {
		if ( is_dir( $src ) ) {
			pb_backupbuddy::status( 'details', 'Copying directory `' . $src . '` to `' . $dst . '` recursively.' );
			@$this->mkdir( $dst, 0777 );
			$files = scandir( $src );
			foreach ( $files as $file ) {
				if ( '.' != $file && '..' != $file ) {
					$this->recursive_copy( "$src/$file", "$dst/$file" );
				}
			}
		} elseif ( file_exists( $src ) ) {
			@copy( $src, $dst ); // Todo: should this need suppression? Media copying was throwing $dst is directory errors.
		}
	}

	/**
	 * Directory Size Map
	 *
	 * @todo Document.
	 *
	 * @param array|bool $dir         Array of directory paths to exclude.  If true then this directory is excluded so no need to check with exclusion directory.
	 * @param string     $base        Base.
	 * @param array      $exclusions  Never modified so just use PHP's copy on modify default behaviour for memory management.
	 * @param array      $dir_array   Array of directories.
	 *
	 * @return array  array( TOTAL_DIRECTORY_SIZE, TOTAL_SIZE_WITH_EXCLUSIONS_TAKEN_INTO_ACCOUNT, OBJECTS_FOUND, OBJECTS_FOUND_WITH_EXCLUSIONS )
	 */
	public function dir_size_map( $dir, $base, $exclusions, &$dir_array ) {
		$dir = rtrim( $dir, '/\\' ); // Force no trailing slash.

		if ( ! is_dir( $dir ) ) {
			return 0;
		}

		$ret                         = 0;
		$ret_with_exclusions         = 0;
		$ret_objects                 = 0;
		$ret_objects_with_exclusions = 0;
		$exclusions_result           = $exclusions;
		$sub                         = @opendir( $dir );
		if ( false === $sub ) { // Cannot access.
			pb_backupbuddy::alert( 'Error #568385: Unable to access directory: `' . $dir . '`. Verify proper permissions.', true );
			return 0;
		} else {
			while ( $file = readdir( $sub ) ) {
				$exclusions_result = $exclusions;

				$dir_path = '/' . str_replace( $base, '', $dir . '/' . $file ) . '/'; // str_replace( $base, '', $dir . $file . '/' );

				if ( '.' == $file || '..' == $file ) {

					// Do nothing.
				} elseif ( is_dir( $dir . '/' . $file ) ) { // DIRECTORY.

					if ( true === $exclusions || self::in_array_substr( $exclusions, $dir_path, '/' ) ) {
						$exclusions_result = true;
					}
					$result       = $this->dir_size_map( $dir . '/' . $file . '/', $base, $exclusions, $dir_array );
					$this_size    = $result[0];
					$this_objects = $result[2];

					if ( true === $exclusions_result ) { // If excluding then wipe excluded value.
						$this_size_with_exclusions    = false;
						$this_objects_with_exclusions = 0;
					} else {
						$this_size_with_exclusions    = $result[1]; // 1048576.
						$this_objects_with_exclusions = $result[3]; // 1048576.
					}

					$dir_array[ $dir_path ] = array( $this_size, $this_size_with_exclusions, $this_objects, $this_objects_with_exclusions );

					$ret                         += $this_size;
					$ret_objects                 += $this_objects;
					$ret_with_exclusions         += $this_size_with_exclusions;
					$ret_objects_with_exclusions += $this_objects_with_exclusions;

					unset( $file );

				} else { // FILE.

					$stats = @stat( $dir . '/' . $file );
					if ( is_array( $stats ) ) {
						$ret += $stats['size'];
						$ret_objects++;
						if ( true !== $exclusions && ! in_array( $dir_path, $exclusions ) ) { // Not excluding.
							$ret_with_exclusions += $stats['size'];
							$ret_objects_with_exclusions++;
						}
					}
					unset( $file );

				}
			}
			closedir( $sub );
			unset( $sub );
			return array( $ret, $ret_with_exclusions, $ret_objects, $ret_objects_with_exclusions );
		}
	} // End dir_size_map().

	/**
	 * Checks array for a string segment.
	 *
	 * @param array  $haystack  Array to search.
	 * @param string $needle    String segment to look for.
	 * @param string $trailing  Optional trailing string.
	 *
	 * @return bool  If string was found.
	 */
	public static function in_array_substr( $haystack, $needle, $trailing = '' ) {
		foreach ( $haystack as $hay ) {
			if ( ( $hay . $trailing ) == substr( $needle . $trailing, 0, strlen( $hay . $trailing ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Batch exit code lookup.
	 *
	 * @param string|int $code  Batch exit code.
	 *
	 * @return string  Error message based on batch exit code.
	 */
	public function exit_code_lookup( $code ) {
		switch ( (string) $code ) {
			case '0':
				return 'Command completed & returned normally.';
			case '126':
				return 'Command invoked cannot execute. Check command has valid permisions and execute capability.';
			case '127':
				return 'Command not found.';
			case '152':
				return 'SIGXCPU 152; CPU time limit exceeded.';
			case '153':
				return 'SIGXFSZ 153; File size limit exceeded. Verify enough free space exists & filesystem max size not exceeded.';
			case '158':
				return 'SIGXCPU 158; CPU time limit exceeded.';
			case '159':
				return 'SIGXFSZ 159; File size limit exceeded. Verify enough free space exists & filesystem max size not exceeded.';
		}

		return '-No information available for this exit code- See: https://wiki.ncsa.illinois.edu/display/MRDPUB/Batch+Exit+Codes';
	}

	/**
	 * Newest to oldest.
	 *
	 * @param string $pattern  Glob pattern.
	 * @param string $mode     Date pattern, default: ctime.
	 *
	 * @return bool|array  False if error, otherwise sorted array.
	 */
	public function glob_by_date( $pattern, $mode = 'ctime' ) {
		$file_array  = array();
		$glob_result = glob( $pattern );
		if ( ! is_array( $glob_result ) ) {
			$glob_result = array();
		}
		foreach ( $glob_result as $i => $filename ) {
			if ( 'ctime' == $mode ) {
				$time = @filectime( $filename );
			} elseif ( 'mtime' == $mode ) {
				$time = @filemtime( $filename );
			} else {
				error_log( 'BackupBuddy Error #2334984489383: Invalid glob_by_date mode: `' . $mode . '`.' );
				return false;
			}
			if ( false === $time ) { // File missing or no longer accessible?
				if ( ! file_exists( $filename ) ) { // File went away.
					unset( $glob_result[ $i ] );
				} else { // Uknown mod time. Set as current time.
					$time = time();
				}
			}
			while ( isset( $file_array[ $time ] ) ) { // Avoid collisions.
				$time = $time + 0.1;
			}
			$file_array[ $time ] = $filename; // or just $filename.
		}
		krsort( $file_array );
		return $file_array;

	} // End glob_by_date().


} // End class pluginbuddy_settings.
