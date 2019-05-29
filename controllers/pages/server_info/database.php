<?php
/**
 * Database Tab of Server Tools Page
 *
 * @package BackupBuddy
 */

if ( ! isset( $parent_class ) ) {
	$parent_class = $this;
}

$profile_id = 0;
if ( is_numeric( pb_backupbuddy::_GET( 'profile' ) ) ) {
	$profile_id = pb_backupbuddy::_GET( 'profile' );
	if ( isset( pb_backupbuddy::$options['profiles'][ $profile_id ] ) ) {
		$profile = pb_backupbuddy::$options['profiles'][ $profile_id ];
		pb_backupbuddy::$options['profiles'][ pb_backupbuddy::_GET( 'profile' ) ] = array_merge( pb_backupbuddy::settings( 'profile_defaults' ), $profile ); // Set defaults if not set.
	} else {
		pb_backupbuddy::alert( 'Error #45849458: Invalid profile ID number `' . htmlentities( pb_backupbuddy::_GET( 'profile' ) ) . '`. Displaying with default profile.', true );
	}
}

// Get profile array.
$profile = array_merge( pb_backupbuddy::settings( 'profile_defaults' ), pb_backupbuddy::$options['profiles'][ $profile_id ] );
foreach ( $profile as $profile_item_name => &$profile_item ) { // replace non-overridden defaults with actual default value.
	if ( '-1' == $profile_item ) { // Set to use default so go grab default.
		if ( isset( pb_backupbuddy::$options['profiles'][0][ $profile_item_name ] ) ) {
			$profile_item = pb_backupbuddy::$options['profiles'][0][ $profile_item_name ]; // Grab value from defaults profile and replace with it.
		}
	}
}
?>
<div style="margin-bottom: 4px;"><?php esc_html_e( 'Backup profile for calculating exclusions', 'it-l10n-backupbuddy' ); ?>:
	<select id="pb_backupbuddy_databaseprofile" onChange="window.location.href = '<?php echo pb_backupbuddy::page_url(); ?>&tab=1&profile=' + jQuery(this).val();">
	<?php foreach ( pb_backupbuddy::$options['profiles'] as $this_profile_id => $this_profile ) { ?>
		<option value="<?php echo esc_attr( $this_profile_id ); ?>"
			<?php
			if ( $profile_id == $this_profile_id ) {
				echo 'selected';
			}
			?>
		><?php echo esc_html( $this_profile['title'] ); ?> (<?php echo esc_html( $this_profile['type'] ); ?>)</a>
	<?php } ?>
	</select>
</div>

<table class="widefat striped">
	<thead>
		<tr class="thead">
				<th><?php esc_html_e( 'Database Table', 'it-l10n-backupbuddy' ); ?></th>
				<th><?php esc_html_e( 'Status', 'it-l10n-backupbuddy' ); ?></th>
				<th><?php esc_html_e( 'Settings', 'it-l10n-backupbuddy' ); ?></th>
				<th><?php esc_html_e( 'Updated / Checked', 'it-l10n-backupbuddy' ); ?></th>
				<th><?php esc_html_e( 'Rows', 'it-l10n-backupbuddy' ); ?></th>
				<th><?php esc_html_e( 'Size', 'it-l10n-backupbuddy' ); ?></th>
				<th><?php esc_html_e( 'Excluded Size', 'it-l10n-backupbuddy' ); ?></th>
		</tr>
	</thead>
	<tfoot>
		<tr class="thead">
			<th><?php esc_html_e( 'Database Table', 'it-l10n-backupbuddy' ); ?></th>
			<th><?php esc_html_e( 'Status', 'it-l10n-backupbuddy' ); ?></th>
			<th><?php esc_html_e( 'Settings', 'it-l10n-backupbuddy' ); ?></th>
			<th><?php esc_html_e( 'Updated / Checked', 'it-l10n-backupbuddy' ); ?></th>
			<th><?php esc_html_e( 'Rows', 'it-l10n-backupbuddy' ); ?></th>
			<th><?php esc_html_e( 'Size', 'it-l10n-backupbuddy' ); ?></th>
			<th><?php esc_html_e( 'Excluded Size', 'it-l10n-backupbuddy' ); ?></th>
		</tr>
	</tfoot>
	<tbody>
		<?php
		global $wpdb;
		$prefix        = $wpdb->prefix;
		$prefix_length = strlen( $wpdb->prefix );

		$additional_includes = backupbuddy_core::get_mysqldump_additional( 'includes', $profile );
		$additional_excludes = backupbuddy_core::get_mysqldump_additional( 'excludes', $profile );

		$total_size                 = 0;
		$total_size_with_exclusions = 0;
		$total_rows                 = 0;
		$rows                       = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );
		foreach ( $rows as $row ) {
			$excluded = true; // Default.

			// TABLE STATUS.
			$rowsb = $wpdb->get_results( "CHECK TABLE `{$row['Name']}`", ARRAY_A );
			foreach ( $rowsb as $rowb ) {
				if ( 'status' == $rowb['Msg_type'] ) {
					$status = $rowb['Msg_text'];
				}
			}
			unset( $rowsb );

			// Fix up row count and average row length for InnoDB engine which returns inaccurate
			// (and changing) values for these.
			if ( 'InnoDB' === $row['Engine'] ) {
				$count = $wpdb->get_var( "SELECT COUNT(1) FROM `{$row['Name']}`" );
				if ( false !== $count ) {
					$row['Rows'] = $count;
					if ( 0 < $row['Rows'] ) {
						$row['Avg_row_length'] = ( $row['Data_length'] / $row['Rows'] );
					}
				}
			}

			// TABLE SIZE.
			$size        = ( $row['Data_length'] + $row['Index_length'] );
			$total_size += $size;

			// HANDLE EXCLUSIONS.
			if ( 0 == $profile['backup_nonwp_tables'] ) { // Only matching prefix.
				if ( substr( $row['Name'], 0, $prefix_length ) == $prefix || in_array( $row['Name'], $additional_includes ) ) {
					if ( ! in_array( $row['Name'], $additional_excludes ) ) {
						$total_size_with_exclusions += $size;
						$excluded                    = false;
					}
				}
			} else { // All tables.
				if ( ! in_array( $row['Name'], $additional_excludes ) ) {
					$total_size_with_exclusions += $size;
					$excluded                    = false;
				}
			}
			// OUTPUT TABLE ROW.
			echo '<tr class="entry-row"';
			if ( true === $excluded ) {
				echo ' style="background: #fcc9c9;"';
			}
			echo '>';
			?>
				<td><?php echo esc_html( $row['Name'] ); ?>
					<div class="row-actions">
						<a href="<?php echo pb_backupbuddy::ajax_url( 'db_check' ); ?>&table=<?php echo esc_attr( base64_encode( $row['Name'] ) ); ?>&#038;TB_iframe=1&#038;width=640&#038;height=600" class="thickbox" title="Check database table for any errors or corruption.">Check</a>
						|
						<a href="<?php echo pb_backupbuddy::ajax_url( 'db_repair' ); ?>&table=<?php echo esc_attr( base64_encode( $row['Name'] ) ); ?>&#038;TB_iframe=1&#038;width=640&#038;height=600" class="thickbox" title="Repair table that has been corrupted. Only needed if the status or check response indicated damage.">Repair</a>
					</div>
				</td>
				<td><?php echo esc_html( $status ); ?></td>
				<td><?php esc_html_e( 'Engine', 'it-l10n-backupbuddy' ); ?>: <?php echo esc_html( $row['Engine'] ); ?><br><?php esc_html_e( 'Collation', 'it-l10n-backupbuddy' ); ?>: <?php echo esc_html( $row['Collation'] ); ?></td>
				<td><?php esc_html_e( 'Updated', 'it-l10n-backupbuddy' ); ?>:
					<?php
					if ( empty( $row['Update_time'] ) ) {
						esc_html_e( 'Unavailable', 'it-l10n-backupbuddy' );
					} else {
						echo esc_html( $row['Update_time'] );
					}
					echo '<br>' . esc_html__( 'Checked', 'it-l10n-backupbuddy' ) . ': ';
					if ( empty( $row['Check_time'] ) ) {
						esc_html_e( 'Unavailable', 'it-l10n-backupbuddy' );
					} else {
						echo esc_html( $row['Check_time'] );
					}
					?>
				</td>
				<td><?php echo esc_html( $row['Rows'] ); ?></td>
				<td><?php echo esc_html( pb_backupbuddy::$format->file_size( $size ) ); ?></td>
				<?php if ( true === $excluded ) { ?>
					<td><span class="pb_label pb_label-important"><?php esc_html_e( 'Excluded', 'it-l10n-backupbuddy' ); ?></span></td>
				<?php } else { ?>
					<td><?php echo esc_html( pb_backupbuddy::$format->file_size( $size ) ); ?></td>
				<?php } ?>
				<?php $total_rows += $row['Rows']; ?>
			</tr>
		<?php } ?>
		<tr class="entry-row alternate">
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			<td><strong><?php esc_html_e( 'TOTALS', 'it-l10n-backupbuddy' ); ?>:</strong></td>
			<td><strong><?php echo esc_html( $total_rows ); ?></strong></td>
			<td><strong><?php echo esc_html( pb_backupbuddy::$format->file_size( $total_size ) ); ?></strong></td>
			<td><strong><?php echo esc_html( pb_backupbuddy::$format->file_size( $total_size_with_exclusions ) ); ?></strong></td>
		</tr>
		<?php
		pb_backupbuddy::$options['stats']['db_size']          = $total_size;
		pb_backupbuddy::$options['stats']['db_size_excluded'] = $total_size_with_exclusions;
		pb_backupbuddy::$options['stats']['db_size_updated']  = time();
		pb_backupbuddy::save();

		unset( $total_size );
		unset( $total_rows );
		unset( $rows );
		?>
	</tbody>
</table>
<br>
