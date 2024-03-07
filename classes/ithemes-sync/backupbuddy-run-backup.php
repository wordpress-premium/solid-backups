<?php
class Ithemes_Sync_Verb_Backupbuddy_Run_Backup extends Ithemes_Sync_Verb {
	public static $name = 'backupbuddy-run-backup';
	public static $description = 'Run a backup profile.';

	private $default_arguments = array(
		'profile'	   => '', // Valid values: db, full, [numeric profile ID]
		'destinations' => array(),
		'delete_after' => false,

	);

	/*
	 * Return:
	 *		array(
	 *			'success'	=>	'0' | '1'
	 *			'status'	=>	'Status message.'
	 *		)
	 *
	 */
	public function run( $arguments ) {
		$arguments = Ithemes_Sync_Functions::merge_defaults( $arguments, $this->default_arguments );
		$results = backupbuddy_api::runBackup( $arguments['profile'], 'Solid Central', $backupMode = '2', '', $arguments['destinations'], $arguments['delete_after'] );

		if ( empty( $results['success'] ) || true !== $results['success'] ) {
			return array(
				'api' => '0',
				'status' => 'error',
				'message' => 'Error running backup. Details: ' . $results,
			);

		} else {

			return array(
				'api'     => '0',
				'status'  => 'ok',
				'message' => 'Backup initiated successfully.',
				'serial'  => $results['serial'],
				'profile' => empty( pb_backupbuddy::$options['profiles'][$arguments['profile']] ) ? false : pb_backupbuddy::$options['profiles'][$arguments['profile']],
			);

		}

	} // End run().


} // End class.
