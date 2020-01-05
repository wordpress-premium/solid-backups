<?php
/**
 * Restore Detail Shown in Recent Restores Table
 *
 * Incoming var: $restore
 *
 * @package BackupBuddy
 */

if ( ! in_array( $restore['status'], backupbuddy_restore()->get_completed_statuses(), true ) ) {
	return;
}
?>
<div class="restore-detail">
	<div class="restore-stats">
		<h4>Restore Stats</h4>

		<div>Restore Zip: <?php echo esc_html( $restore['backup_file'] ); ?></div>
		<div>Restore Type: <?php echo esc_html( $restore['type'] ); ?></div>
		<div>What to Restore: <?php echo esc_html( $restore['what'] ); ?></div>
		<div>Restore Profile: <?php echo esc_html( $restore['profile'] ); ?></div>
		<div>Restore Path: <?php echo esc_html( $restore['restore_path'] ); ?></div>
		<?php
		if ( ! empty( $restore['destination_id'] ) ) :
			$destination = pb_backupbuddy::$options['remote_destinations'][ $restore['destination_id'] ];
			?>
			<div>Destination: <?php echo esc_html( $destination['title'] ); ?> (<?php echo esc_html( $restore['destination_id'] ); ?>)</div>
			<div>Download Required? <?php echo ! empty( $restore['download'] ) ? 'Yes' : 'No'; ?></div>
		<?php endif; ?>
		<div>Initialized: <?php echo esc_html( pb_backupbuddy::$format->date( $restore['initialized'] ) ); ?></div>
		<?php if ( ! empty( $restore['started'] ) ) : ?>
			<div>Started: <?php echo esc_html( pb_backupbuddy::$format->date( $restore['started'] ) ); ?></div>
		<?php endif; ?>
		<?php if ( ! empty( $restore['aborted'] ) ) : ?>
			<div>Aborted: <?php echo esc_html( pb_backupbuddy::$format->date( $restore['aborted'] ) ); ?></div>
		<?php endif; ?>
		<?php if ( ! empty( $restore['elapsed'] ) ) : ?>
			<div>Elapsed: <?php echo esc_html( pb_backupbuddy::$format->time_duration( $restore['elapsed'] ) ); ?></div>
		<?php endif; ?>
		<div>Viewed: <?php echo $restore['viewed'] ? esc_html( pb_backupbuddy::$format->date( $restore['viewed'] ) ) : 'No'; ?></div>

		<?php if ( in_array( $restore['what'], array( 'both', 'files' ), true ) ) : ?>
			<hr style="max-width: 90%;margin:5px 0;">
			<div>
				<?php
				echo 'Files: ';
				if ( '*' === $restore['files'] ) {
					echo esc_html__( 'Full backup restore, All files/folders.', 'it-l10n-backupbuddy' );
				} elseif ( is_array( $restore['files'] ) ) {
					echo '<ul class="restore-file-list">';
						echo '<li>' . implode( '</li><li>', $restore['files'] ) . '</li>';
					echo '</ul>';
				}
				?>
			</div>
			<?php
			if ( ! empty( $restore['extract_files'] ) ) {
				if ( is_array( $restore['extract_files'] ) ) {
					$count = count( $restore['extract_files'] );
				} else {
					$count = $restore['extract_files'];
				}
				$label = _n( 'Files', 'Files', $count, 'it-l10n-backupbuddy' );
				printf( '<div>Extracted Files: <label for="%s-extracted-files">%s %s</label>', esc_attr( $restore['id'] ), esc_html( number_format( $count ) ), esc_html( $label ) );
				if ( is_array( $restore['extract_files'] ) ) {
					printf( '<input type="checkbox" id="%s-extracted-files" class="toggler">', esc_attr( $restore['id'] ) );
					echo '<pre style="white-space: pre-wrap;">';
					print_r( $restore['extract_files'] );
					echo '</pre>';
				}
				echo '</div>';
			}
			if ( ! empty( $restore['copied'] ) ) {
				$count = count( $restore['copied'] );
				$label = _n( 'Files', 'Files', $count, 'it-l10n-backupbuddy' );
				printf( '<div>Copied Files: <label for="%s-copied-files">%s %s</label>', esc_attr( $restore['id'] ), esc_html( $count ), esc_html( $label ) );
				printf( '<input type="checkbox" id="%s-copied-files" class="toggler">', esc_attr( $restore['id'] ) );
				echo '<pre style="white-space: pre-wrap;">';
				print_r( $restore['copied'] );
				echo '</pre>';
				echo '</div>';
			}
			if ( ! empty( $restore['skipped'] ) ) {
				$count = count( $restore['skipped'] );
				$label = _n( 'Files', 'Files', $count, 'it-l10n-backupbuddy' );
				printf( '<div>Skipped Files (Identical): <label for="%s-skipped-files">%s %s</label>', esc_attr( $restore['id'] ), esc_html( number_format( $count ) ), esc_html( $label ) );
				printf( '<input type="checkbox" id="%s-skipped-files" class="toggler">', esc_attr( $restore['id'] ) );
				echo '<pre style="white-space: pre-wrap;">';
				print_r( $restore['skipped'] );
				echo '</pre>';
				echo '</div>';
			}
			if ( ! empty( $restore['cleanup'] ) ) {
				$count = count( $restore['cleanup'] );
				$label = _n( 'Files', 'Files', $count, 'it-l10n-backupbuddy' );
				printf( '<div>Cleanup Files: <label for="%s-cleanup-files">%s %s</label>', esc_attr( $restore['id'] ), esc_html( $count ), esc_html( $label ) );
				printf( '<input type="checkbox" id="%s-cleanup-files" class="toggler">', esc_attr( $restore['id'] ) );
				echo '<pre style="white-space: pre-wrap;">';
				print_r( $restore['cleanup'] );
				echo '</pre>';
				echo '</div>';
			}
			if ( ! empty( $restore['perms'] ) ) {
				$count = count( $restore['perms'] );
				$label = _n( 'Files', 'Files', $count, 'it-l10n-backupbuddy' );
				printf( '<div>Permissions Changes: <label for="%s-perms-changes">%s %s</label>', esc_attr( $restore['id'] ), esc_html( $count ), esc_html( $label ) );
				printf( '<input type="checkbox" id="%s-perms-changes" class="toggler">', esc_attr( $restore['id'] ) );
				echo '<pre style="white-space: pre-wrap;">';
				print_r( $restore['perms'] );
				echo '</pre>';
				echo '</div>';
			}
			if ( ! empty( $restore['perm_fails'] ) ) {
				$count = count( $restore['perm_fails'] );
				$label = _n( 'Files', 'Files', $count, 'it-l10n-backupbuddy' );
				printf( '<div>Permission Change Failures: <label for="%s-perms-fails">%s %s</label>', esc_attr( $restore['id'] ), esc_html( $count ), esc_html( $label ) );
				printf( '<input type="checkbox" id="%s-perms-fails" class="toggler">', esc_attr( $restore['id'] ) );
				echo '<pre style="white-space: pre-wrap;">';
				print_r( $restore['perm_fails'] );
				echo '</pre>';
				echo '</div>';
			}
			?>
		<?php endif; ?>

		<?php if ( in_array( $restore['what'], array( 'both', 'db' ), true ) ) : ?>
			<hr style="max-width: 90%;margin:5px 0;">
			<div>Tables:
				<?php
				if ( '*' === $restore['tables'] ) {
					echo esc_html__( 'Full backup restore, All tables.', 'it-l10n-backupbuddy' );
				} elseif ( is_array( $restore['tables'] ) ) {
					echo '<br>' . implode( '<br>', $restore['tables'] );
				}
				?>
			</div>
			<?php
			if ( ! empty( $restore['imported_tables'] ) ) {
				$count = count( $restore['imported_tables'] );
				$label = _n( 'Table', 'Tables', $count, 'it-l10n-backupbuddy' );
				printf( '<div>Imported: <label for="%s-imported-tables">%s %s</label>', esc_attr( $restore['id'] ), esc_html( $count ), esc_html( $label ) );
				printf( '<input type="checkbox" id="%s-imported-tables" class="toggler">', esc_attr( $restore['id'] ) );
				echo '<pre style="white-space: pre-wrap;">';
				print_r( $restore['imported_tables'] );
				echo '</pre>';
				echo '</div>';
			}
			if ( ! empty( $restore['restored_tables'] ) ) {
				$count = count( $restore['restored_tables'] );
				$label = _n( 'Table', 'Tables', $count, 'it-l10n-backupbuddy' );
				printf( '<div>Restored: <label for="%s-restored-tables">%s %s</label>', esc_attr( $restore['id'] ), esc_html( $count ), esc_html( $label ) );
				printf( '<input type="checkbox" id="%s-restored-tables" class="toggler">', esc_attr( $restore['id'] ) );
				echo '<pre style="white-space: pre-wrap;">';
				print_r( $restore['restored_tables'] );
				echo '</pre>';
				echo '</div>';
			}
			if ( ! empty( $restore['failed_tables'] ) ) {
				$count = count( $restore['failed_tables'] );
				$label = _n( 'Table', 'Tables', $count, 'it-l10n-backupbuddy' );
				printf( '<div>Failed: <label for="%s-failed-tables">%s %s</label>', esc_attr( $restore['id'] ), esc_html( $count ), esc_html( $label ) );
				printf( '<input type="checkbox" id="%s-failed-tables" class="toggler">', esc_attr( $restore['id'] ) );
				echo '<pre style="white-space: pre-wrap;">';
				print_r( $restore['failed_tables'] );
				echo '</pre>';
				echo '</div>';
			}
			if ( ! empty( $restore['last_tables'] ) ) {
				$count = count( $restore['last_tables'] );
				$label = _n( 'Table', 'Tables', $count, 'it-l10n-backupbuddy' );
				printf( '<div>Last to Import: <label for="%s-last-tables">%s %s</label>', esc_attr( $restore['id'] ), esc_html( $count ), esc_html( $label ) );
				printf( '<input type="checkbox" id="%s-last-tables" class="toggler">', esc_attr( $restore['id'] ) );
				echo '<pre style="white-space: pre-wrap;">';
				print_r( $restore['last_tables'] );
				echo '</pre>';
				echo '</div>';
			}
			if ( ! empty( $restore['incomplete_tables'] ) ) {
				$count = count( $restore['incomplete_tables'] );
				$label = _n( 'Table', 'Tables', $count, 'it-l10n-backupbuddy' );
				printf( '<div>Incomplete: <label for="%s-incomplete-tables">%s %s</label>', esc_attr( $restore['id'] ), esc_html( $count ), esc_html( $label ) );
				printf( '<input type="checkbox" id="%s-incomplete-tables" class="toggler">', esc_attr( $restore['id'] ) );
				echo '<pre style="white-space: pre-wrap;">';
				print_r( $restore['incomplete_tables'] );
				echo '</pre>';
				echo '</div>';
			}
			if ( ! empty( $restore['cleanup_db'] ) ) {
				$count = count( $restore['cleanup_db'] );
				$label = _n( 'Query', 'Queries', $count, 'it-l10n-backupbuddy' );
				printf( '<div>DB Cleanup: <label for="%s-db-cleanup">%s %s</label>', esc_attr( $restore['id'] ), esc_html( $count ), esc_html( $label ) );
				printf( '<input type="checkbox" id="%s-db-cleanup" class="toggler">', esc_attr( $restore['id'] ) );
				echo '<pre style="white-space: pre-wrap;">';
				print_r( $restore['cleanup_db'] );
				echo '</pre>';
				echo '</div>';
			}
			?>
		<?php endif; ?>
		<?php
		if ( pb_backupbuddy::_GET( 'debug-restore' ) ) {
			echo '<hr style="max-width:90%;margin:5px 0;">';
			printf( '<div>Restore Array: <label for="%s-debug-restore">Toggle</label>', esc_attr( $restore['id'] ) );
			printf( '<input type="checkbox" id="%s-debug-restore" class="toggler">', esc_attr( $restore['id'] ) );
			echo '<pre style="white-space: pre-wrap;">';
			print_r( $restore );
			echo '</pre>';
			echo '</div>';
		}
		?>
	</div>
	<div class="restore-log">
		<h4>Restore Log</h4>
		<div class="log-entry"><?php echo implode( '</div><div class="log-entry">', $restore['log'] ); ?></div>
	</div>
</div>
