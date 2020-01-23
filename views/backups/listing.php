<?php
/**
 * Backups Listing
 *
 * Part of BackupBuddy Backups class.
 *
 * Incoming vars:
 *     $backups
 *     $settings
 *
 * @package BackupBuddy
 */

pb_backupbuddy::$ui->list_table(
	$backups,
	array_merge( array(
		'action'                   => pb_backupbuddy::page_url(),
		'columns'                  => $this->get_columns(),
		'hover_actions'            => $this->get_hover_actions(),
		'hover_action_column_key'  => '0',
		'bulk_actions'             => $this->get_bulk_actions(),
		'css'                      => 'width: 100%;',
		'disable_top_bulk_actions' => true,
		'disable_tfoot'            => true,
		'form_class'               => $this->has_pagination() ? 'has-pagination' : '',
		'table_class'              => 'minimal',
		'disable_wrapper'          => true,
		'display_mode'             => $this->mode,
	), $settings )
);

$this->pagination();
