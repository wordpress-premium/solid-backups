<?php
/**
 * Backup Detail (shown in modal)
 *
 * Incoming Vars:
 *   $zip_file
 *   $data
 *
 * @package BackupBuddy
 */

$backup_serial = backupbuddy_core::parse_file( $zip_file, 'serial' );
$backup_date   = backupbuddy_core::parse_file( $zip_file, 'nicename' );

if ( file_exists( backupbuddy_core::getLogDirectory() . 'fileoptions/' . $backup_serial . '.txt' ) ) {
	pb_backupbuddy::status( 'details', 'Loading fileoptions data instance #50...' );
	require_once pb_backupbuddy::plugin_path() . '/classes/fileoptions.php';
	$read_only      = false;
	$ignore_lock    = false;
	$create_file    = true;
	$backup_options = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/' . $backup_serial . '.txt', $read_only, $ignore_lock, $create_file ); // Will create file to hold integrity data if nothing exists.
} else {
	$backup_options = '';
}

$options = array(
	'suppress_rescan_alert' => true,
);

$backup_integrity = backupbuddy_core::backup_integrity_check( $zip_file, $backup_options, $options );

// Backup status.
$pretty_status = array(
	true     => '<span class="pb_label pb_label-success">Good</span>', // v4.0+ Good.
	'pass'   => '<span class="pb_label pb_label-success">Good</span>', // Pre-v4.0 Good.
	false    => '<span class="pb_label pb_label-important">Bad</span>', // v4.0+ Bad.
	'fail'   => '<span class="pb_label pb_label-important">Bad</span>', // Pre-v4.0 Bad.
	'remote' => '<span class="pb_label pb_label-info">Remote</span>', // Remote backups.
);

// Backup type.
$pretty_type = array(
	'full'    => __( 'Full', 'it-l10n-backupbuddy' ),
	'db'      => __( 'Database', 'it-l10n-backupbuddy' ),
	'files'   => __( 'Files', 'it-l10n-backupbuddy' ),
	'themes'  => __( 'Themes', 'it-l10n-backupbuddy' ),
	'plugins' => __( 'Plugins', 'it-l10n-backupbuddy' ),
);

// Defaults.
$status        = null;
$detected_type = '';
$file_count    = '';
$modified      = '';
$modified_time = 0;
$integrity     = '';
$note          = '';
$scan_notes    = '';
$actions       = '';

if ( is_array( $backup_integrity ) ) { // Data intact... put it all together.
	// Calculate time ago.
	$time_ago = '';
	if ( ! empty( $backup_integrity['modified'] ) ) {
		$time_ago = ' (' . pb_backupbuddy::$format->time_ago( $backup_integrity['modified'] ) . ' ago)';
	}

	$detected_type = pb_backupbuddy::$format->prettify( $backup_integrity['detected_type'], $pretty_type );
	if ( '' == $detected_type ) {
		$detected_type = backupbuddy_core::pretty_backup_type( backupbuddy_core::parse_file( $zip_file, 'type' ) );
		if ( '' == $detected_type ) {
			$detected_type = '<span class="description">Unknown</span>';
		}
	} else {
		if ( isset( $backup_options->options['profile'] ) ) {
			$profile_title = isset( $backup_options->options['profile']['title'] ) ? htmlentities( $backup_options->options['profile']['title'] ) : '';
			$detected_type = '<span class="backup-stats-profile-type-icon profile_type-' . $backup_integrity['detected_type'] . '" title="' . backupbuddy_core::pretty_backup_type( $detected_type ) . '">' . pb_backupbuddy::$ui->get_icon_by_profile_type( $backup_integrity['detected_type'] ) . '</span>' . $profile_title;
		} else {
			$detected_type = '<span class="backup-stats-profile-type-icon profile_type-' . $detected_type . '" title="' . backupbuddy_core::pretty_backup_type( $detected_type ) . '">' . pb_backupbuddy::$ui->get_icon_by_profile_type( $detected_type ) . '</span>' . ucwords( $detected_type );
		}
	}

	$modified      = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $backup_integrity['modified'] ), 'l, F j, Y - g:i:s a' );
	$modified_time = $backup_integrity['modified'];
	if ( ! empty( $backup_integrity['status'] ) ) { // Pre-v4.0.
		$status = $backup_integrity['status'];
	} else { // v4.0+.
		$status = $backup_integrity['is_ok'];
	}

	// Add comment to main row string if applicable.
	if ( ! empty( $backup_integrity['comment'] ) ) {
		$note = $backup_integrity['comment'];
	}

	if ( isset( $backup_integrity['scan_notes'] ) && count( (array) $backup_integrity['scan_notes'] ) > 0 ) {
		foreach ( (array) $backup_integrity['scan_notes'] as $scan_note ) {
			$scan_notes .= $scan_note . ' ';
		}
	}

	// No integrity check for themes or plugins types.
	$raw_type             = backupbuddy_core::getBackupTypeFromFile( $file );
	$skip_integrity_types = array( 'themes', 'plugins', 'media', 'files' );
	if ( in_array( $raw_type, $skip_integrity_types, true ) ) {
		foreach ( (array) $backup_integrity['tests'] as $test ) {
			if ( isset( $test['fileCount'] ) ) {
				$file_count = $test['fileCount'];
			}
		}
	}

	//$actions = '<a href="' . esc_attr( admin_url( '?page=pb_backupbuddy_backup&reset_integrity=' . $backup_serial ) ) . '" title="Rescan integrity. Last checked ' . esc_attr( pb_backupbuddy::$format->date( $backup_integrity['scan_time'] ) ) . '.">Rescan integrity</a> | ';
} else { // end if is_array( $backup_options ).
	if ( file_exists( backupbuddy_core::getBackupDirectory() . $zip_file ) ) {
		$modified_time = filemtime( backupbuddy_core::getBackupDirectory() . $zip_file );
	}
}

if ( ! file_exists( backupbuddy_core::getBackupDirectory() . $zip_file ) ) {
	$status = 'remote';
}

$parsed = backupbuddy_core::parse_file( $zip_file );
if ( ! $detected_type ) {
	$detected_type = $parsed['type'];
}
if ( ! $modified_time ) {
	$modified_time = $parsed['timestamp'];
}
if ( ! $modified ) {
	$format   = empty( $parsed['time'] ) ? 'l, F j, Y' : 'l, F j, Y - g:i:s a';
	$modified = pb_backupbuddy::$format->date( pb_backupbuddy::$format->localize_time( $modified_time ), $format );
}

if ( ! $time_ago ) {
	if ( empty( $parsed['time'] ) ) {
		if ( current_time( 'Y-m-d' ) === $parsed['date'] ) {
			$time_ago = ' (' . __( 'Today', 'it-l10n-backupbuddy' ) . ')';
		} else {
			$time_ago = ' (' . pb_backupbuddy::$format->time_ago( $backup_date ) . ' ago)';
		}
	} else {
		$time_ago_date = $backup_date;
		if ( ! is_numeric( $time_ago_date ) ) {
			$time_ago_date = strtotime( $time_ago_date );
		}
		$time_ago = ' (' . pb_backupbuddy::$format->time_ago( $time_ago_date ) . ' ago)';
	}
}

if ( $data ) {
	$zip_size = $data['zip_size'];
	if ( ! $file_count ) {
		$file_count = number_format( count( $data['zip_contents'] ) );
	}
} else {
	$zip_size = filesize( backupbuddy_core::getBackupDirectory() . $zip_file );
}
$file_size = pb_backupbuddy::$format->file_size( $zip_size );

?>
<div class="backup-detail">
	<div class="backup-date"><?php echo esc_html( $backup_date . $time_ago ); ?></div>
	<table class="backup-stats">
		<tbody>
			<?php if ( null !== $status ) : ?>
				<tr>
					<th>Status</th>
					<td><?php echo pb_backupbuddy::$format->prettify( $status, $pretty_status ); ?></td>
				</tr>
			<?php endif; ?>

			<?php if ( $data ) : ?>
				<?php if ( ! empty( $data['plugin_data']['backupbuddy/backupbuddy.php'] ) ) : ?>
					<tr>
						<th>Solid Backups Version</th>
						<td><?php echo esc_html( $data['plugin_data']['backupbuddy/backupbuddy.php']['Version'] ); ?></td>
					</tr>
				<?php endif; ?>
				<tr>
					<th>WordPress Version</th>
					<td><?php echo esc_html( $data['wp_version'] ); ?></td>
				</tr>
			<?php endif; ?>

			<?php if ( $file_count ) : ?>
				<tr>
					<th>Total Files</th>
					<td><?php echo esc_html( $file_count ); ?></td>
				</tr>
			<?php endif; ?>

			<?php if ( $file_size ) : ?>
				<tr>
					<th>Backup Size</th>
					<td><?php echo esc_html( $file_size ); ?></td>
				</tr>
			<?php endif; ?>

			<?php if ( $detected_type ) : ?>
			<tr>
				<th>Backup Type</th>
				<td><?php echo $detected_type; ?></td>
			</tr>
			<?php endif; ?>

			<?php if ( $note ) : ?>
				<tr>
					<th>Backup Note</th>
					<td><span class="pb_backupbuddy_notetext"><?php echo esc_html( $note ); ?></span></td>
				</tr>
			<?php endif; ?>

			<?php if ( $scan_notes ) : ?>
				<tr>
					<th>Scan Notes</th>
					<td><?php echo esc_html( $scan_notes ); ?></td>
				</tr>
			<?php endif; ?>

			<?php if ( $actions ) : ?>
				<tr>
					<th>&nbsp;</th>
					<td colspan="2">
						<?php echo $actions; ?>
						<a title="<?php echo esc_attr( __( 'Backup Status', 'it-l10n-backupbuddy' ) ); ?>" href="<?php echo esc_attr( pb_backupbuddy::ajax_url( 'integrity_status' ) . '&serial=' . $backup_serial ); ?>&#038;TB_iframe=1&#038;width=640&#038;height=600" class="thickbox"><?php esc_html_e( 'Full Details', 'it-l10n-backupbuddy' ); ?></a>
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>
</div>
<div class="download-backup-actions-wrap">
	<?php if ( file_exists( backupbuddy_core::getBackupDirectory() . $zip_file ) ) :
		$download_url  = pb_backupbuddy::ajax_url( 'download_archive' ) . '&backupbuddy_backup=' . basename( $zip_file );
		?>
		<div class="download-backup-file">
			<a class="button button-primary" href="<?php esc_attr_e( $download_url ); ?>" title="<?php echo esc_attr( __( 'Download Backup', 'it-l10n-backupbuddy' ) ); ?>" class="thickbox"><?php esc_html_e('Download Backup', 'it-l10n-backupbuddy'); ?></a>
		</div>
	<?php endif; ?>
	<?php
	$sum_log_file = backupbuddy_core::getLogDirectory() . 'status-' . $backup_serial . '_' . pb_backupbuddy::$options['log_serial'] . '.txt';
	if ( file_exists( $sum_log_file ) ) :
		?>
		<div class="download-status-log">
			<a class="button button-secondary thickbox" href="<?php esc_attr_e( pb_backupbuddy::ajax_url( 'view_log' ) . '&serial=' . $backup_serial . '&#038;TB_iframe=1&#038;width=640&#038;height=600' ); ?>" title="<?php echo esc_attr( __( 'View Backup Log', 'it-l10n-backupbuddy' ) ); ?>"><?php esc_html_e('Download Status Log', 'it-l10n-backupbuddy'); ?></a>
		</div>
	<?php endif; ?>
</div>