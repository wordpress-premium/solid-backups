<?php
/**
 * Main class for Email Destination
 *
 * DO NOT CALL THIS CLASS DIRECTLY. CALL VIA: pb_backupbuddy_destination in bootstrap.php.
 *
 * @package BackupBuddy
 */

/**
 * Email Destination base class.
 */
class pb_backupbuddy_destination_email {

	/**
	 * Destination info array.
	 *
	 * @var array
	 */
	public static $destination_info = array(
		'name'        => 'Email',
		'description' => 'Send files as email attachments. With most email servers attachments are typically <b>limited to about 10 MB</b> in size so only small backups typically can be sent this way.',
		'category'    => 'normal', // best, normal, legacy.
	);

	/**
	 * Default settings. Should be public static for auto-merging.
	 *
	 * @var array
	 */
	public static $default_settings = array(
		'type'     => 'email',    // MUST MATCH your destination slug.
		'title'    => '',         // Required destination field.
		'address'  => '',
		'disabled' => '0',        // When 1, disable this destination.
	);

	/**
	 * Send one or more files.
	 *
	 * @param array $settings  Destination settings array.
	 * @param array $files     Array of one or more files to send.
	 *
	 * @return bool  True on success, else false.
	 */
	public static function send( $settings = array(), $files = array() ) {
		global $pb_backupbuddy_destination_errors;
		if ( '1' == $settings['disabled'] ) {
			$pb_backupbuddy_destination_errors[] = __( 'Error #48933: This destination is currently disabled. Enable it under this destination\'s Advanced Settings.', 'it-l10n-backupbuddy' );
			return false;
		}
		if ( ! is_array( $files ) ) {
			$files = array( $files );
		}

		$email = $settings['address'];

		if ( pb_backupbuddy::$options['email_return'] != '' ) {
			$email_return = pb_backupbuddy::$options['email_return'];
		} else {
			$email_return = get_option( 'admin_email' );
		}

		pb_backupbuddy::status( 'details', 'Sending remote email.' );
		$headers        = 'From: Solid Backups <' . $email_return . '>' . "\r\n";
		$wp_mail_result = wp_mail( $email, 'Solid Backups backup for ' . site_url(), 'Solid Backups backup for ' . site_url(), $headers, $files );
		pb_backupbuddy::status( 'details', 'Sent remote email.' );

		if ( true === $wp_mail_result ) { // WP sent. Hopefully it makes it!
			return true;
		}

		// WP couldn't try to send.
		return false;
	} // End send().

	/**
	 * Sends a text email with ImportBuddy.php zipped up and attached to it.
	 *
	 * @param array  $settings  Destination settings array.
	 * @param string $file      File to use for testing.
	 *
	 * @return bool  True on success, string error message on failure.
	 */
	public static function test( $settings, $file = false ) {
		pb_backupbuddy::status( 'details', 'Testing email destination.' );

		if ( false !== $file ) {
			$files = array( $file );
		} else {
			$files = array( pb_backupbuddy::plugin_path() . '/destinations/remote-send-test.php' );
		}

		if ( true === self::send( $settings, $files ) ) { // WP sent. Hopefully it makes it!
			return true;
		}

		echo 'WordPress was unable to attempt to send email. Check your WordPress & server settings.';
		return false;
	} // End test().

} // End class.
