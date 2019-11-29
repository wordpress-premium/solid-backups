<?php

class BackupBuddy_HTTP_Request {
	protected $request_method = 'GET';
	protected $timeout = DAY_IN_SECONDS;
	protected $blocking = true;

	protected $url = '';
	protected $headers = array();
	protected $get_vars = array();
	protected $post_vars = array();
	protected $cookies = array();
	protected $body = null;
	protected $files = array();
	protected $multipart_stream = false;

	protected $restricted_characters = array( "\0", "\"", "\r", "\n" );

	protected $boundary = '';
	protected $cached_data_to_send = '';
	protected $current_file_index = false;
	protected $fh = false;
	protected $multipart_complete = false;


	public function __construct( $url = '', $request_method = '' ) {
		$this->url = $url;

		if ( ! empty( $request_method ) ) {
			$this->request_method = $request_method;
		}
	}

	public function get_built_url() {
		$url = apply_filters( 'it_bub_filter_http_request_url', $this->url );
		
		return "$url/?" . http_build_query( $this->get_vars, null, '&' );
	}

	public function get_response() {
		$url = $this->get_built_url();

		if ( ! empty( $this->body ) ) {
			$body = $this->body;
		} else {
			$body = $this->post_vars;
		}

		if ( $this->multipart_stream ) {
			add_action( 'requests-curl.before_send', array( $this, 'configure_multipart_stream' ) );

			$this->boundary = sha1( random_int( -PHP_INT_MAX, PHP_INT_MAX ) );
			$this->build_cached_data_to_send();

			$this->set_header( 'Content-Type', "multipart/form-data; boundary=\"{$this->boundary}\"" );

			$this->files = array_values( $this->files );
			$this->multipart_complete = false;
		}

		$args = array(
			'method'   => $this->request_method,
			'timeout'  => $this->timeout,
			'blocking' => $this->blocking,
			'headers'  => $this->headers,
			'cookies'  => $this->cookies,
			'body'     => $body,
		);

		set_time_limit( $this->timeout + HOUR_IN_SECONDS );

		$response = wp_remote_request( $url, $args );

		if ( $this->multipart_stream ) {
			remove_action( 'requests-curl.before_send', array( $this, 'configure_multipart_stream' ) );
		}

		return $response;
	}

	protected function build_cached_data_to_send() {
		foreach ( $this->post_vars as $var => $val ) {
			$this->cached_data_to_send .= $this->get_multipart_header( $var );
			$this->cached_data_to_send .= filter_var( $val );
		}

		$this->cached_data_to_send .= "\r\n";
	}

	protected function get_body_size() {
		$size = strlen( $this->cached_data_to_send );

		foreach ( $this->files as $index => $data ) {
			$this->files[$index]['header'] = $this->get_multipart_header( $data['var'], $data );
			$size += strlen( $this->files[$index]['header'] );
			$size += filesize( $data['file'] );
		}

		$size += strlen( $this->boundary ) + 4;

		return $size;
	}

	protected function get_multipart_header( $var, $data = false ) {
		$var = str_replace( $this->restricted_characters, '_', $var );

		$header = "--{$this->boundary}\r\n";
		$header .= "Content-Disposition: form-data; name=\"$var\"";

		if ( is_array( $data ) ) {
			$filename = str_replace( $this->restricted_characters, '_', $data['name'] );
			$header .= "; filename=\"$filename\"\r\n";
			$header .= "Content-Type: application/octet-stream";
		}

		$header .= "\r\n\r\n";

		return $header;
	}

	public function configure_multipart_stream( $curl ) {
		curl_setopt( $curl, CURLOPT_READFUNCTION, array( $this, 'send_multipart_data' ) );
		curl_setopt( $curl, CURLOPT_INFILESIZE, $this->get_body_size() );
		curl_setopt( $curl, CURLOPT_UPLOAD, true );
		curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'POST' );
	}

	public function send_multipart_data( $curl, $fp, $length ) {
		if ( ! empty( $this->cached_data_to_send ) ) {
			$data = substr( $this->cached_data_to_send, 0, $length );
			$this->cached_data_to_send = substr( $this->cached_data_to_send, $length );

			return $data;
		}

		if ( $this->multipart_complete ) {
			return '';
		}

		if ( false === $this->current_file_index ) {
			$this->current_file_index = -1;
		}

		if ( false === $this->fh || feof( $this->fh ) ) {
			if ( false !== $this->fh ) {
				fclose( $this->fh );
				$this->fh = false;

				$this->cached_data_to_send .= "\r\n";
			}

			$this->current_file_index++;

			if ( isset( $this->files[$this->current_file_index] ) ) {
				$this->fh = @fopen( $this->files[$this->current_file_index]['file'], 'r' );
				$this->cached_data_to_send .= $this->files[$this->current_file_index]['header'];
			} else {
				$this->cached_data_to_send .= "--{$this->boundary}--";
				$this->multipart_complete = true;
			}

			return $this->send_multipart_data( $curl, $fp, $length );
		}

		return fread( $this->fh, $length );
	}

	public function set_request_method( $request_method ) {
		$this->request_method = $request_method;
	}

	public function set_timeout( $timeout ) {
		$this->timeout = max( 0, (int) $timeout );
	}

	public function set_blocking( $blocking ) {
		$this->blocking = (bool) $blocking;
	}

	public function set_url( $url ) {
		$this->url = $url;
	}

	public function set_header( $var, $val ) {
		$this->headers[$var] = $val;
	}

	public function set_headers( $vars ) {
		$this->headers = $vars;
	}

	public function set_get_var( $var, $val ) {
		$this->get_vars[$var] = $val;
	}

	public function set_get_vars( $vars ) {
		$this->get_vars = $vars;
	}

	public function set_post_var( $var, $val ) {
		$this->post_vars[$var] = $val;
	}

	public function set_post_vars( $vars ) {
		$this->post_vars = $vars;
	}

	public function add_file( $var, $file, $name = '' ) {
		if ( empty( $name ) ) {
			$name = basename( $file );
		}

		$this->files[] = compact( 'var', 'file', 'name' );

		$this->set_multipart_stream( true );
	}

	public function set_cookie( $var, $val ) {
		$this->cookies[$var] = $val;
	}

	public function set_cookies( $vars ) {
		$this->cookies = $vars;
	}

	public function set_body( $body ) {
		$this->body = $body;
	}

	public function set_multipart_stream( $multipart_stream ) {
		$this->multipart_stream = $multipart_stream;
	}
}
