<?php
/**
 * Backups Pagination
 *
 * @package BackupBuddy
 */

$prev_page = sprintf( $pagination_url, $current_page - 1 );
$next_page = sprintf( $pagination_url, $current_page + 1 );
?>
<div class="backupbuddy-backups-pagination <?php echo esc_attr( 'mode-' . $this->mode ); ?>">
	<?php if ( 1 === $current_page ) : ?>
		<span>&lsaquo;</span>
	<?php else : ?>
		<a href="<?php echo esc_attr( $prev_page ); ?>">&lsaquo;</a>
	<?php endif; ?>

	<div><?php echo esc_html( $current_page ); ?> of <?php echo esc_html( $total_pages ); ?></div>

	<?php if ( $current_page === $total_pages ) : ?>
		<span>&rsaquo;</span>
	<?php else : ?>
		<a href="<?php echo esc_attr( $next_page ); ?>">&rsaquo;</a>
	<?php endif; ?>
</div>
