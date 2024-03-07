<?php
/**
 * Diagnostics Page Server Info
 *
 * @package BackupBuddy
 */

?>
<style type="text/css">
	.pb_backupbuddy_refresh_stats {
		cursor: pointer;
	}
</style>
<script>
jQuery(function( $ ) {
	function bb_isNumber( n ) {
		return !isNaN(parseFloat(n)) && isFinite(n);
	};

	$('.pb_backupbuddy_testErrorLog').on( 'click', function(e) {
		$( '.pb_backupbuddy_loading' ).show();
		$.post( $(this).attr( 'rel' ), { function: 'testErrorLog' },
			function(data) {
				$( '.pb_backupbuddy_loading' ).hide();
				alert( data );
			}
		);
		return false;
	});

	$( '.pb_backupbuddy_testPHPRuntime' ).on( 'click', function(e){
		loading = $(this).children( '.pb_backupbuddy_loading' );
		serializedForm = $(this).closest( 'form' ).serialize();

		testPHPRuntimeInterval = setInterval( function(){
			loading.show();
			$.post(
				'<?php echo pb_backupbuddy::ajax_url( 'run_php_runtime_test_results' ); ?>',
				serializedForm,
				function(data) {
					loading.hide();
					if ( bb_isNumber( data ) ) { // Finished
						result_obj.html( data + ' <?php _e( 'secs', 'it-l10n-backupbuddy' ); ?>' );
						clearInterval( testPHPRuntimeInterval );
					} else { // In progress.
						result_obj.html( data );
					}
				}
			);
		}, 5000 );
	});

	$( '.pb_backupbuddy_testPHPMemory' ).on( 'click', function(e){
		loading = $(this).children( '.pb_backupbuddy_loading' );
		serializedForm = $(this).closest( 'form' ).serialize();

		testPHPMemoryInterval = setInterval( function(){
			loading.show();
			$.post(
				'<?php echo pb_backupbuddy::ajax_url( 'run_php_memory_test_results' ); ?>',
				serializedForm,
				function(data) {
					loading.hide();
					if ( bb_isNumber( data ) ) { // Finished
						result_obj.html( data + ' <?php _e( 'MB', 'it-l10n-backupbuddy' ); ?>' );
						clearInterval( testPHPMemoryInterval );
					} else { // In progress.
						result_obj.html( data );
					}
				}
			);
		}, 5000 );
	});

	$('.pb_backupbuddy_refresh_stats').on( 'click', function(e) {
		loading = $(this).children( '.pb_backupbuddy_loading' );
		loading.show();

		result_obj = $( '#pb_stats_' + $(this).attr( 'rel' ) );

		$.post( $(this).attr( 'alt' ), $(this).closest( 'form' ).serialize(),
			function(data) {
				loading.hide();
				result_obj.html( data );
			}
		);

		return false;
	});
});
</script>
<?php require '_server_tests.php'; ?>

<table class="widefat striped">
	<thead>
		<tr class="thead">

			<th><?php esc_html_e( 'Server Configuration', 'it-l10n-backupbuddy' ); ?></th>
			<th style="width: 15px;">&nbsp;</th>
			<th><?php esc_html_e( 'Suggestion', 'it-l10n-backupbuddy' ); ?></th>
			<th><?php esc_html_e( 'Value', 'it-l10n-backupbuddy' ); ?></th>
			<th style="width: 60px;"><?php esc_html_e( 'Status', 'it-l10n-backupbuddy' ); ?></th>
		</tr>
	</thead>
	<tfoot>
		<tr class="tfoot">

			<th><?php esc_html_e( 'Server Configuration', 'it-l10n-backupbuddy' ); ?></th>
			<th style="width: 15px;">&nbsp;</th>
			<th><?php esc_html_e( 'Suggestion', 'it-l10n-backupbuddy' ); ?></th>
			<th><?php esc_html_e( 'Value', 'it-l10n-backupbuddy' ); ?></th>
			<th style="width: 60px;"><?php esc_html_e( 'Status', 'it-l10n-backupbuddy' ); ?></th>
		</tr>
	</tfoot>
	<tbody>
		<?php foreach ( $tests as $parent_class_test ) { ?>
			<tr class="entry-row">

				<td class="diagnostics-table-headings"><?php echo $parent_class_test['title']; ?></td>
				<td class="td-tip-only"><?php echo pb_backupbuddy::tip( $parent_class_test['tip'], '', false ); ?></td>
				<td><?php echo $parent_class_test['suggestion']; ?></td>
				<td><?php echo $parent_class_test['value']; ?></td>
				<?php if ( isset( $parent_class_test['status'] ) ) {?>
					<td>
						<?php if ( 'OK' == $parent_class_test['status'] ) { ?>
							<span class="pb_label pb_label-success"><?php esc_html_e( 'Pass', 'it-l10n-backupbuddy' ); ?></span>
						<?php } elseif ( 'FAIL' == $parent_class_test['status'] ) { ?>
							<span class="pb_label pb_label-important"><?php esc_html_e( 'Fail', 'it-l10n-backupbuddy' ); ?></span>
						<?php } elseif ( 'WARNING' == $parent_class_test['status'] ) { ?>
							<span class="pb_label pb_label-warning"><?php esc_html_e( 'Warning', 'it-l10n-backupbuddy' ); ?></span>
						<?php } ?>
					</td>
				<?php } ?>
			</tr>
		<?php } ?>
	</tbody>
</table>
<br>
<center>
	<?php
	if ( ! defined( 'PB_IMPORTBUDDY' ) ) {
		echo '<a href="#TB_inline?width=640&#038;height=600&#038;inlineId=pb_serverinfotext_modal" class="button button-secondary thickbox" title="Server Information Results">Display Server Configuration in Text Format</a> &nbsp;&nbsp;&nbsp; ';
		echo '<a href="' . pb_backupbuddy::ajax_url( 'pinfo' ) . '&#038;TB_iframe=1&#038;width=640&#038;height=600" class="thickbox button button-secondary secondary-button" title="' . esc_html__( 'Display Extended PHP Settings via phpinfo()', 'it-l10n-backupbuddy' ) . '">' . esc_html__( 'Display Extended PHP Settings via phpinfo()', 'it-l10n-backupbuddy' ) . '</a>';
	} else {
		echo '<a id="serverinfotext" class="button thickbox toggle" title="Server Information Results">Display Results in Text Format</a> &nbsp;&nbsp;&nbsp; ';
	}
	?>
</center>
<br>

<?php
$div_id         = ! defined( 'PB_IMPORTBUDDY' ) ? 'pb_serverinfotext_modal' : 'toggle-serverinfotext';
$textarea_width = ! defined( 'PB_IMPORTBUDDY' ) ? '100%' : '95%';
?>
<div id="<?php echo esc_attr( $div_id ); ?>" style="display: none;">
	<?php if ( ! defined( 'PB_IMPORTBUDDY' ) ) : ?>
		<h3><?php esc_html_e( 'Server Information Results', 'it-l10n-backupbuddy' ); ?></h3>
	<?php endif; ?>
<textarea style="width: <?php echo esc_attr( $textarea_width ); ?>;" readonly="readonly" wrap="off">
<?php
foreach ( $tests as $test ) :
// This alignment is required.
echo '[' . esc_html( $test['status'] ) . ']     ' . esc_html( $test['title'] ) . '   =   ' . esc_html( strip_tags( $test['value'] ) ) . "\n";
endforeach;
unset( $tests );
?>
</textarea>
</div>
