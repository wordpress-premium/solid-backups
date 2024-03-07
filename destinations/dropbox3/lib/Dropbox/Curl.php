<?php
namespace Dropbox;

/**
 * A minimal wrapper around a cURL handle.
 *
 * @internal
 */
final class Curl
{
    /** @var resource */
    public $handle;

    /** @var string[] */
    private $headers = array();

    private $debugout;

    /**
     * @param string $url
     */
    function __construct($url)
    {

		if ( \pb_backupbuddy::full_logging() ) {
			ob_start();
			$this->debugout = fopen('php://output', 'w');
		}

        // Make sure there aren't any spaces in the URL (i.e. the caller forgot to URL-encode).
        if (strpos($url, ' ') !== false) {
            throw new \InvalidArgumentException("Found space in \$url; it should be encoded");
        }
        \pb_backupbuddy::status( 'details', 'Curl URL: `' . $url . '`.' );
        $this->handle = curl_init($url);

        if ( \pb_backupbuddy::full_logging() ) {
	        curl_setopt($this->handle, CURLOPT_VERBOSE, true);
	        curl_setopt($this->handle, CURLOPT_STDERR, $this->debugout);
	   }

        // NOTE: Though we turn on all the correct SSL settings, many PHP installations
        // don't respect these settings.  Run "examples/test-ssl.php" to run some basic
        // SSL tests to see how well your PHP implementation behaves.

        // Use our own certificate list.
        $this->set(CURLOPT_SSL_VERIFYPEER, true);   // Enforce certificate validation
        $this->set(CURLOPT_SSL_VERIFYHOST, 2);      // Enforce hostname validation

        // Force the use of TLS (SSL v2 and v3 are not secure).
        // TODO: Use "CURL_SSLVERSION_TLSv1" instead of "1" once we can rely on PHP 5.5+.
        if ( defined( 'CURL_SSLVERSION_TLSv1_2' ) ) {
	        $this->set(CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        } else {
			throw new Exception_NetworkIO( 'Error executing HTTP request: TLS v1.2 or greater is required, please update your version of CURL' );
        }

        // Limit the set of ciphersuites used.
        global $sslCiphersuiteList;
        if ($sslCiphersuiteList !== null) {
            $this->set(CURLOPT_SSL_CIPHER_LIST, $sslCiphersuiteList);
        }

        list($rootCertsFilePath, $rootCertsFolderPath) = RootCertificates::getPaths();
        // Certificate file.
        $this->set(CURLOPT_CAINFO, $rootCertsFilePath);
        // Certificate folder.  If not specified, some PHP installations will use
        // the system default, even when CURLOPT_CAINFO is specified.
        $this->set(CURLOPT_CAPATH, $rootCertsFolderPath);

        // Limit vulnerability surface area.  Supported in cURL 7.19.4+
        if (defined('CURLOPT_PROTOCOLS')) $this->set(CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        if (defined('CURLOPT_REDIR_PROTOCOLS')) $this->set(CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
    }

    /**
     * @param string $header
     */
    function addHeader($header)
    {
        $this->headers[] = $header;
    }

	/**
	 * Execute CURL request
	 *
	 * @throws Exception_NetworkIO  When response body is false.
	 *
	 * @param string $contentType  Content type.
	 * @param bool   $is_retry     If is retry without content length.
	 *
	 * @return HttpResponse  HTTP Response object.
	 */
	public function exec( $contentType = '', $is_retry = false ) {
		if ( '' == $contentType ) {
			// $contentType = 'application/json';
		}

		// Prevent Duplicate Content-Type headers.
		if ( ! empty( $contentType ) ) {
			$found_content_type = false;
			foreach ( $this->headers as $index => $header ) {
				if ( false !== strpos( strtolower( $header ), 'content-type' ) ) {
					$this->headers[ $index ] = 'Content-Type: '. $contentType;
					$found_content_type      = true;
				}
			}
			if ( false === $found_content_type ) {
				$this->headers = array_merge( $this->headers, array( 'Content-Type: '. $contentType ) );
			}
		}

		// Try to remove Content-Length header upon failed request (if retry).
		if ( $is_retry && in_array( 'Content-Length: 0', $this->headers, true ) ) {
			foreach ( $this->headers as $index => $header ) {
				if ( 'Content-Length: 0' === $header ) {
					\pb_backupbuddy::status( 'details', 'Removing `Content-Length: 0` from cURL headers.' );
					unset( $this->headers[ $index ] );
					break;
				}
			}
		}

		$this->set( CURLOPT_HTTPHEADER, $this->headers );
		$curl_version = curl_version();
		if ( ! empty( $curl_version['version'] ) ) {
			\pb_backupbuddy::status( 'details', 'Curl version: `' . $curl_version['version'] . '`' );
		}
		\pb_backupbuddy::status( 'details', 'About to exec in Curl.php curl_exec(). contentType: `' . $contentType . '`.' );
		$body = curl_exec( $this->handle );
		if ( false === $body ) {
			if ( ! $is_retry ) {
				\pb_backupbuddy::status( 'details', 'Attempting Curl exec retry due to false body.' );
				return $this->exec( $contentType, true );
			}
			throw new Exception_NetworkIO( 'Error executing HTTP request: ' . curl_error( $this->handle ) );
		}

		$statusCode = curl_getinfo( $this->handle, CURLINFO_HTTP_CODE );

		if ( \pb_backupbuddy::full_logging() ) {
			fclose( $this->debugout );
			$debug = ob_get_clean();
			\pb_backupbuddy::status( 'details', 'Dropbox HTTP debug: `' . $debug . '`.' );
		}

		if ( 400 === $statusCode && ! $is_retry ) {
			\pb_backupbuddy::status( 'details', 'Attempting Curl exec retry due to status 400.' );
			return $this->exec( $contentType, true );
		}

		return new HttpResponse( $statusCode, $body );
	}

    function get( $option ) {

    }

    /**
     * @param int $option
     * @param mixed $value
     */
    function set($option, $value)
    {
        curl_setopt($this->handle, $option, $value);
    }

    function __destruct()
    {
        curl_close($this->handle);
    }
}

// Different cURL SSL backends use different names for ciphersuites.
$curlVersion = \curl_version();
$curlSslBackend = $curlVersion['ssl_version'];
if (\substr_compare($curlSslBackend, "NSS/", 0, strlen("NSS/")) === 0) {
    // Can't figure out how to reliably set ciphersuites for NSS.
    $sslCiphersuiteList = null;
}
else {
    // Use the OpenSSL names for all other backends.  We may have to
    // refine this if users report errors.
    $sslCiphersuiteList =
        'ECDHE-RSA-AES256-GCM-SHA384:'.
        'ECDHE-RSA-AES128-GCM-SHA256:'.
        'ECDHE-RSA-AES256-SHA384:'.
        'ECDHE-RSA-AES128-SHA256:'.
        'ECDHE-RSA-AES256-SHA:'.
        'ECDHE-RSA-AES128-SHA:'.
        'ECDHE-RSA-RC4-SHA:'.
        'DHE-RSA-AES256-GCM-SHA384:'.
        'DHE-RSA-AES128-GCM-SHA256:'.
        'DHE-RSA-AES256-SHA256:'.
        'DHE-RSA-AES128-SHA256:'.
        'DHE-RSA-AES256-SHA:'.
        'DHE-RSA-AES128-SHA:'.
        'AES256-GCM-SHA384:'.
        'AES128-GCM-SHA256:'.
        'AES256-SHA256:'.
        'AES128-SHA256:'.
        'AES256-SHA:'.
        'AES128-SHA';
}
