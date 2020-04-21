<?php
/**
 * Display site site listing on Server Info page.
 *
 * @package BackupBuddy
 */

backupbuddy_core::verifyAjaxAccess();

$profile_id = 0;
if ( is_numeric( pb_backupbuddy::_GET( 'profile' ) ) ) {
	$profile_id = pb_backupbuddy::_GET( 'profile' );
	if ( isset( pb_backupbuddy::$options['profiles'][ $profile_id ] ) ) {
		$profile = pb_backupbuddy::$options['profiles'][ $profile_id ];
		pb_backupbuddy::$options['profiles'][ $profile_id ] = array_merge( pb_backupbuddy::settings( 'profile_defaults' ), $profile ); // Set defaults if not set.
	} else {
		pb_backupbuddy::alert( 'Error #45849458b: Invalid profile ID number `' . htmlentities( $profile_id ) . '`. Displaying with default profile.', true );
	}
}

echo '<!-- profile: ' . esc_html( $profile_id ) . ' -->';

$exclusions             = backupbuddy_core::get_directory_exclusions( pb_backupbuddy::$options['profiles'][ $profile_id ] );
$exclusion_profile_name = htmlentities( pb_backupbuddy::$options['profiles'][ $profile_id ]['title'] );

$basedir = ABSPATH;
if ( '' != pb_backupbuddy::$options['profiles'][ $profile_id ]['custom_root'] ) {
	$basedir = backupbuddy_core::get_normalized_custom_root( pb_backupbuddy::$options['profiles'][ $profile_id ]['custom_root'] );
}

// Handle smart profile types.
if ( 'media' == pb_backupbuddy::$options['profiles'][ $profile_id ]['type'] ) {
	$basedir = backupbuddy_core::get_media_root();
} elseif ( 'themes' == pb_backupbuddy::$options['profiles'][ $profile_id ]['type'] ) {
	$basedir = backupbuddy_core::get_themes_root();
} elseif ( 'plugins' == pb_backupbuddy::$options['profiles'][ $profile_id ]['type'] ) {
	$basedir = backupbuddy_core::get_plugins_root();
}

$result = pb_backupbuddy::$filesystem->dir_size_map( $basedir, $basedir, $exclusions, $dir_array );
if ( 0 == $result ) {
	pb_backupbuddy::alert( 'Error #5656653. Unable to access directory map listing for directory `' . $basedir . '`.' );
	die();
}

$total_size           = $result[0];
$total_size_excluded  = $result[1];
$total_count          = $result[2];
$total_count_excluded = $result[3];

pb_backupbuddy::$options['stats']['site_size']          = $total_size;
pb_backupbuddy::$options['stats']['site_size_excluded'] = $total_size_excluded;
pb_backupbuddy::$options['stats']['site_size_updated']  = time();
pb_backupbuddy::save();

arsort( $dir_array );

if ( pb_backupbuddy::_GET( 'text' ) == 'true' ) {
	pb_backupbuddy::$ui->ajax_header();
	echo '<h3>' . esc_html__( 'Site Size Listing & Exclusions', 'it-l10n-backupbuddy' ) . '</h3>';
	echo '<textarea style="width:100%; height: 300px; font-family: monospace;" wrap="off">';
	echo esc_html__( 'Size + Children', 'it-l10n-backupbuddy' ) . "\t";
	echo esc_html__( '- Exclusions', 'it-l10n-backupbuddy' ) . "\t";
	echo esc_html__( 'Directory', 'it-l10n-backupbuddy' ) . "\n";
} else {
	?>
	<style>
		.backupbuddy_sizemap_table th {
			white-space: nowrap;
		}
		.backupbuddy_sizemap_table td {
			word-break: break-all;
		}
	</style>
	<strong>Backup root for profile:</strong> <?php echo esc_html( $basedir ); ?><br><br>
	<table class="widefat striped backupbuddy_sizemap_table">
		<thead>
			<tr class="thead">
				<th><?php esc_html_e( 'Directory (relative to backup root)', 'it-l10n-backupbuddy' ); ?></th>
				<th><?php esc_html_e( 'Size with Children', 'it-l10n-backupbuddy' ); ?></th>
				<th><?php esc_html_e( 'Size with Exclusions', 'it-l10n-backupbuddy' ); ?><br>
					<span class="description"><?php echo esc_html( $exclusion_profile_name ); ?> profile</span></th>
				<th><?php esc_html_e( 'Children Count', 'it-l10n-backupbuddy' ); ?><br>
					<span class="description">(Files + Dirs)</span></th>
				<th><?php esc_html_e( 'Children with Exclusions', 'it-l10n-backupbuddy' ); ?><br>
					<span class="description"><?php echo esc_html( $exclusion_profile_name ); ?> profile</span></th>
			</tr>
		</thead>
		<tfoot>
			<tr class="thead">
				<th><?php esc_html_e( 'Directory', 'it-l10n-backupbuddy' ); ?></th>
				<th><?php esc_html_e( 'Size with Children', 'it-l10n-backupbuddy' ); ?></th>
				<th><?php esc_html_e( 'Size with Exclusions', 'it-l10n-backupbuddy' ); ?><br>
					<span class="description"><?php echo esc_html( $exclusion_profile_name ); ?> profile</span></th>
				<th><?php esc_html_e( 'Children Count', 'it-l10n-backupbuddy' ); ?><br>
					<span class="description">(Files + Dirs)</span></th>
				<th><?php esc_html_e( 'Children with Exclusions', 'it-l10n-backupbuddy' ); ?><br>
					<span class="description"><?php echo esc_html( $exclusion_profile_name ); ?> profile</span></th>
			</tr>
		</tfoot>
		<tbody>
	<?php
}
if ( pb_backupbuddy::_GET( 'text' ) == 'true' ) {
	echo str_pad( pb_backupbuddy::$format->file_size( $total_size ), 10, ' ', STR_PAD_RIGHT ) . "\t" . str_pad( pb_backupbuddy::$format->file_size( $total_size_excluded ), 10, ' ', STR_PAD_RIGHT ) . "\t" . esc_html__( 'TOTALS', 'it-l10n-backupbuddy' ) . "\n";
} else {
	echo '<tr><td align="right"><b>' . esc_html__( 'TOTALS', 'it-l10n-backupbuddy' ) . ':</b></td><td><b>' . pb_backupbuddy::$format->file_size( $total_size ) . '</b></td><td><b>' . pb_backupbuddy::$format->file_size( $total_size_excluded ) . '</b></td><td><b>' . esc_html( $total_count ) . '</b></td><td><b>' . esc_html( $total_count_excluded ) . '</b></td></tr>';
}
$item_count = 0;
foreach ( $dir_array as $id => $item ) { // Each $item is in format array( TOTAL_SIZE, TOTAL_SIZE_TAKING_EXCLUSIONS_INTO_ACCOUNT, file count, file count excluded ).
	$item_count++;
	if ( $item_count > 100 ) {
		flush();
		$item_count = 0;
	}
	if ( false === $item[1] ) {
		if ( pb_backupbuddy::_GET( 'text' ) == 'true' ) {
			$excluded_size = 'EXCLUDED';
			echo '**';
		} else {
			$excluded_size = '<span class="pb_label pb_label-important">Excluded</span>';
			echo '<tr style="background: #fcc9c9;">';
		}
	} else {
		$excluded_size = pb_backupbuddy::$format->file_size( $item[1] );
		if ( 'true' != pb_backupbuddy::_GET( 'text' ) ) {
			echo '<tr>';
		}
	}
	if ( 'true' == pb_backupbuddy::_GET( 'text' ) ) {
		echo str_pad( pb_backupbuddy::$format->file_size( $item[0] ), 10, ' ', STR_PAD_RIGHT ) . "\t" . str_pad( $excluded_size, 10, ' ', STR_PAD_RIGHT ) . "\t" . esc_html( $id ) . "\n";
	} else {
		echo '<td>' . esc_html( $id ) . '</td>
		<td>' . pb_backupbuddy::$format->file_size( $item[0] ) . '</td>
		<td>' . $excluded_size . '</td>
		<td>' . esc_html( $item[2] ) . '</td>
		<td> ' . esc_html( $item[3] ) . '</td></tr>';
	}
}
if ( pb_backupbuddy::_GET( 'text' ) == 'true' ) {
		echo str_pad( pb_backupbuddy::$format->file_size( $total_size ), 10, ' ', STR_PAD_RIGHT ) . "\t" . str_pad( pb_backupbuddy::$format->file_size( $total_size_excluded ), 10, ' ', STR_PAD_RIGHT ) . "\t" . esc_html__( 'TOTALS', 'it-l10n-backupbuddy' ) . "\n";
} else {
	echo '<tr>
		<td align="right"><b>' . esc_html__( 'TOTALS', 'it-l10n-backupbuddy' ) . ':</b></td>
		<td><b>' . pb_backupbuddy::$format->file_size( $total_size ) . '</b></td>
		<td><b>' . pb_backupbuddy::$format->file_size( $total_size_excluded ) . '</b></td>
		<td><b>' . esc_html( $total_count ) . '</b></td>
		<td><b>' . esc_html( $total_count_excluded ) . '</b></td>
	</tr>';
}
if ( pb_backupbuddy::_GET( 'text' ) == 'true' ) {
	echo "\n\nEXCLUSIONS (" . count( $exclusions ) . '):' . "\n" . implode( "\n", $exclusions );
	echo '</textarea>';
	pb_backupbuddy::$ui->ajax_footer();
} else {
	echo '</tbody>';
	echo '</table>';

	echo '<br>';
	echo 'Exclusions (' . count( $exclusions ) . ')';
	pb_backupbuddy::tip( 'List of directories that will be excluded in an actual backup. This includes user-defined directories and BackupBuddy directories such as the archive directory and temporary directories.' );
	echo '<div id="pb_backupbuddy_serverinfo_exclusions" style="background-color: #EEEEEE; padding: 4px; float: right; white-space: nowrap; height: 90px; width: 70%; min-width: 400px; overflow: auto;"><i>' . implode( '<br>', $exclusions ) . '</i></div>';
	echo '<br style="clear: both;">';
	echo '<br><br><center>';
	echo '<a href="' . pb_backupbuddy::ajax_url( 'site_size_listing' ) . '&text=true&#038;TB_iframe=1&#038;width=640&#038;height=600" class="thickbox button secondary-button">' . esc_html__( 'Display Directory Size Listing in Text Format', 'it-l10n-backupbuddy' ) . '</a>';
	echo '</center>';
}

die();
