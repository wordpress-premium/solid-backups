<?php
/**
 * Deploy Status AJAX Controller
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$backup_serial = pb_backupbuddy::_POST( 'serial' );
$profile_id    = pb_backupbuddy::_POST( 'profileID' );
$this_step     = pb_backupbuddy::_POST( 'step' );
$step_counter  = pb_backupbuddy::_POST( 'stepCounter' );

if ( '0' == $this_step ) {
	$backup_files = glob( backupbuddy_core::getBackupDirectory() . 'backup*' . $backup_serial . '*.zip' );
	if ( ! is_array( $backup_files ) ) {
		$backup_files = array();
	}
	if ( count( $backup_files ) > 0 ) {
		$backup_file = $backup_files[0];
		die(
			json_encode(
				array(
					'statusStep' => 'backupComplete',
					'stepTitle'  => 'Backup finished. File: ' . $backup_file . ' -- Next step start sending the file chunks to remote API server via curl.',
					'nextStep'   => 'sendFiles',
				)
			)
		);
	}

	$last_backup_stats = backupbuddy_api::getLatestBackupStats();
	if ( $backup_serial != $last_backup_stats['serial'] ) {
		die(
			json_encode(
				array(
					'stepTitle'  => 'Waiting for backup to begin.',
					'statusStep' => 'waitingBackupBegin',
				)
			)
		);
	} else { // Last backup stats is our deploy backup.
		die(
			json_encode(
				array(
					'stepTitle'  => $last_backup_stats['processStepTitle'] . ' with profile "' . pb_backupbuddy::$options['profiles'][ $profile_id ]['title'] . '".',
					'statusStep' => 'backupStats',
					'stats'      => $last_backup_stats,
				)
			)
		);

	}
} elseif ( 'sendFiles' == $this_step ) {

	if ( '0' == $step_counter ) {
		die(
			json_encode(
				array(
					'stepTitle'  => 'FIRST SENDFILES RUN',
					'statusStep' => 'sendFiles',
					'nextStep'   => 'sendFiles',
				)
			)
		);
	} else {
		die(
			json_encode(
				array(
					'stepTitle'  => 'Sending files...',
					'statusStep' => 'sendFiles',
					'nextStep'   => 'sendFiles',
				)
			)
		);
	}
}

die( 'Invalid step `' . esc_html( $this_step ) . '`.' );
