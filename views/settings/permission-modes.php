<?php
/**
 * Permission Modes Table
 *
 * @package BackupBuddy
 */

?>
<table class="backupbuddy-permission-modes">
	<thead>
		<tr>
			<th>Modes are:</th>
			<th>Files</th>
			<th>Folders</th>
			<th>wp-config.php</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<th>Standard</th>
			<td>0644</td>
			<td>0755</td>
			<td>0600</td>
		</tr>
		<tr>
			<th>Loose</th>
			<td>0664</td>
			<td>0775</td>
			<td>0660</td>
		</tr>
		<tr>
			<th>Strict</th>
			<td>0640</td>
			<td>0750</td>
			<td>0400</td>
		</tr>
	</tbody>
</table>
