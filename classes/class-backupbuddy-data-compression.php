<?php
/**
 * Data compression and decompression.
 *
 * @package BackupBuddy
 */

/**
 * Used to compress/decompress data.
 */
class BackupBuddy_Data_Compression {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		return $this;
	}

	/**
	 * Returns an array of compression methods from best to worst.
	 *
	 * @param bool $inverse  Returns the decompression methods.
	 *
	 * @return array  Available methods for compression or decompression.
	 */
	public function available_methods( $inverse = false ) {
		$methods = array(
			//'lz4' => 'lz4_compress', // TODO: Currently unsupported.
			//'zop' => 'zopfli_compress', // TODO: Currently unsupported.
			'bz'  => 'bzcompress',
			'gz'  => 'gzcompress',
			'gze' => 'gzencode',
			'gzd' => 'gzdeflate',
		);

		$reverse = array(
			//'lz4' => 'lz4_uncompress', // TODO: Currently unsupported.
			//'zop' => 'zopfli_uncompress', // TODO: Currently unsupported.
			'bz'  => 'bzdecompress',
			'gz'  => 'gzuncompress',
			'gze' => 'gzdecode',
			'gzd' => 'gzinflate',
		);

		// Remove all unsupported compression methods.
		foreach ( $methods as $k => $v ) {
			if ( 'none' !== $v && ! function_exists( $v ) ) {
				unset( $methods[ $k ] );
				unset( $reverse[ $k ] );
			}
		}

		// Remove all unsupported decompression methods.
		foreach ( $reverse as $k => $v ) {
			if ( ! function_exists( $v ) ) {
				unset( $reverse[ $k ] );
				unset( $methods[ $k ] );
			}
		}

		if ( true === $inverse ) {
			return $reverse;
		}

		return $methods;
	}

	/**
	 * Get compression method from file mime type.
	 *
	 * @param string $type  File mime type.
	 *
	 * @return string|false  Method key or false if invalid.
	 */
	public static function get_method_from_type( $type ) {
		$mime_types = array(
			'bz'  => 'application/x-bzip2',
			'gz'  => 'application/zlib',
			'gze' => 'application/x-gzip',
			'gzd' => 'application/octet-stream',
		);

		foreach ( $mime_types as $method_key => $mime_type ) {
			if ( $type === $mime_type ) {
				return $method_key;
			}
		}

		return false;
	}

	/**
	 * Get first available compression method.
	 *
	 * @param bool $inverse  If decompression method should be returned.
	 *
	 * @return string  Compression or decompression method key.
	 */
	public function get_best_method( $inverse = false ) {
		return array_key_first( $this->available_methods( $inverse ) );
	}

	/**
	 * Get a method function name.
	 *
	 * @param string $method_key  Compression method key.
	 * @param bool   $inverse     If decompression function should be return.
	 *
	 * @return string  Compression or decompression function name.
	 */
	public function get_method( $method_key, $inverse = false ) {
		$methods = $this->available_methods( $inverse );
		if ( ! isset( $methods[ $method_key ] ) ) {
			return false;
		}

		return $methods[ $method_key ];
	}

	/**
	 * Compress a string. By default uses best available method.
	 *
	 * @param string $string  String to compress.
	 * @param bool   $method  Force a compression method.
	 *
	 * @return string  Compressed string.
	 */
	public function compress( $string, $method = false ) {
		if ( false === $method ) {
			$method = $this->get_best_method();
		}

		$func = $this->get_method( $method );

		if ( false === $func ) {
			return false;
		}

		return $func( $string );
	}

	/**
	 * Decompress a string.
	 *
	 * @param string $data    Compressed string data.
	 * @param string $method  Compression method (key) used.
	 *
	 * @return string|false  Decompressed string or false if failed.
	 */
	public function decompress( $data, $method ) {
		$func = $this->get_method( $method, true );

		if ( false === $func ) {
			return false;
		}

		return $func( $string );
	}
}
