<?php
/**
 * List of recent edits.
 *
 * @package BackupBuddy
 */

is_admin() || die( 'Access Denied.' );

$rel = isset( $_GET['rel'] ) ? trim( esc_html( (string) $_GET['rel'] ) ) : '';
?>
<div class="wrapper">
	<div class="itbub-recent-edits-summary">
		<h2><?php esc_html_e( 'Recent Edits Summary', 'it-l10n-backupbuddy' ); ?></h2>
		<div class="itbub-edits-summary">
			<?php
			$first = true;
			foreach ( $edit_groups as $action => $edits ) {
				$count = count( $edits );
				$class = ( ! $rel && $first ) || strpos( $action, $rel ) !== false ? ' itbub-active' : '';
				$first = false;
				if ( isset( $descriptions[ $action ] ) ) {
					$string = 1 === $count ? $descriptions[ $action ]['singular'] : $descriptions[ $action ]['plural'];
				} else {
					$string = $action;
				}
				printf( '<div class="itbub-summary-item%s" rel="itbub-edit-group-%s"><span class="count">%s</span> %s</div>',
					esc_attr( $class ),
					esc_attr( $action ),
					esc_html( $count ),
					esc_html( $string )
				);
			}
			?>
		</div>
	</div>
	<?php
	$first = true; // Reset first var.
	foreach ( $edit_groups as $action => $edits ) {
		$class = ( ! $rel && $first ) || strpos( $action, $rel ) !== false ? ' itbub-active' : '';
		$first = false;
		printf( '<div id="itbub-edit-group-%s" class="itbub-edit-group %s">',
			esc_attr( $action ),
			esc_attr( $action . $class )
		);
		$count = count( $edits );
		if ( isset( $descriptions[ $action ] ) ) {
			$heading = 1 === $count ? $descriptions[ $action ]['singular'] : $descriptions[ $action ]['plural'];
		} else {
			$heading = $action;
		}
		?>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php echo esc_html( $heading ); ?></th>
					<th class="itbub-timestamp"><?php esc_html_e( 'Last Updated', 'it-l10n-backupbuddy' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $edits as $edit ) : ?>
					<tr>
						<td>
							<?php if ( $edit['modified'] > 1 ) : ?>
								<span class="itbub-modified-count" title="<?php echo esc_attr( sprintf( 'Modified %s times', $edit['modified'] ) ); ?>"><?php echo esc_html( $edit['modified'] ); ?></span>
							<?php endif; ?>
							<?php
							switch ( $edit['type'] ) {
								case 'post':
									echo esc_html( $edit['post']->post_title );

									$permalink = get_the_permalink( $edit['post'] );

									if ( 'auto-draft' === $edit['post']->post_status ) {
										$permalink = get_edit_post_link( $edit['post_id'] );
									}

									if ( $permalink ) {
										if ( 'trash_post' === $action ) {
											printf( '<br><span class="permalink">%s</a>', esc_html( $permalink ) );
										} else {
											printf( '<br><a href="%s" class="permalink" target="_blank" rel="noopener">%s</a>', esc_url( $permalink ), esc_html( $permalink ) );
										}
									}
									break;

								case 'option':
									echo esc_html( $edit['option'] );
									break;

								case 'plugin':
									if ( ! empty( $edit['plugin_data'] ) ) {
										echo esc_html( $edit['plugin_data']['Name'] );
										if ( $edit['plugin_data']['PluginURI'] ) {
											printf( '<br><a href="%s" class="permalink" target="_blank" rel="noopener">%s</a>', esc_url( $edit['plugin_data']['PluginURI'] ), esc_html( $edit['plugin_data']['PluginURI'] ) );
										}
									} else {
										echo esc_html( $edit['plugin'] );
									}
									break;

								default:
									echo esc_html( $edit['type'] );
									break;
							}
							?>
						</td>
						<td title="<?php echo esc_attr( $edit['timestamp'] ); ?>"><?php echo esc_html( human_time_diff( strtotime( $edit['timestamp'] ), current_time( 'timestamp' ) ) ); ?> <?php esc_html_e( 'ago', 'it-l10n-backupbuddy' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		echo '</div>';
	}
	?>
</div>
