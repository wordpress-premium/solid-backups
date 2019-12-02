<?php
/**
 * BackupBuddy Privacy Policy
 *
 * @package BackupBuddy
 * @since 8.2.5.5
 */

?>
<h2><?php esc_html_e( 'What personal data we collect and why we collect it', 'it-l10n-backupbuddy' ); ?></h2>

<h3><?php esc_html_e( 'Backups', 'it-l10n-backupbuddy' ); ?></h3>
<p><?php esc_html_e( 'Per the functionality of this plugin, backups of your website files and/or database are created and stored locally on your server and/or remotely on 3rd party servers based on the settings of this plugin. Archives can include, but are not limited to, file and database assets, including hashed passwords, 3rd party data, uploads and user information. These backups are stored to provide critical functionality of this plugin.', 'it-l10n-backupbuddy' ); ?></p>

<h3><?php esc_html_e( 'Cookies', 'it-l10n-backupbuddy' ); ?></h3>
<p><?php esc_html_e( 'Cookies are used to handle importing and restoring backups.', 'it-l10n-backupbuddy' ); ?></p>

<h3><?php esc_html_e( 'Plugin Settings', 'it-l10n-backupbuddy' ); ?></h3>
<p><?php esc_html_e( 'Some plugin settings ask for an email address or login credentials to 3rd party services. This is stored to provide functional features to the plugin.', 'it-l10n-backupbuddy' ); ?></p>

<h3><?php esc_html_e( 'Recent Activity', 'it-l10n-backupbuddy' ); ?></h3>
<p><?php esc_html_e( 'This plugin tracks dates, times and actions of successful and unsuccessful backups and remote data transfers. This is stored to help make the plugin better and assist with troubleshooting problems.', 'it-l10n-backupbuddy' ); ?></p>

<h3><?php esc_html_e( 'Logging', 'it-l10n-backupbuddy' ); ?></h3>
<p><?php esc_html_e( 'This plugin logs some personal information such as email addresses, usernames, database and server information. This is stored to help make the plugin better and assist with troubleshooting problems.', 'it-l10n-backupbuddy' ); ?></p>

<h2><?php esc_html_e( 'How long we retain your data', 'it-l10n-backupbuddy' ); ?></h2>

<h3><?php esc_html_e( 'Backups', 'it-l10n-backupbuddy' ); ?></h3>
<p><?php esc_html_e( 'Backup retention is completely up to the website owner. This can vary from less than one minute to indefinitely.', 'it-l10n-backupbuddy' ); ?></p>

<h3><?php esc_html_e( 'Cookies', 'it-l10n-backupbuddy' ); ?></h3>
<p><?php esc_html_e( 'Cookies used during the restore/import process expire after 24 hours.', 'it-l10n-backupbuddy' ); ?></p>

<h3><?php esc_html_e( 'Plugin Settings', 'it-l10n-backupbuddy' ); ?></h3>
<p><?php esc_html_e( 'Settings are retained indefinitely until they are changed or removed manually.', 'it-l10n-backupbuddy' ); ?></p>

<h2><?php esc_html_e( 'Where we send your data', 'it-l10n-backupbuddy' ); ?></h2>

<h3><?php esc_html_e( 'Backups', 'it-l10n-backupbuddy' ); ?></h3>
<p><?php esc_html_e( 'Backups are not automatically sent to remote destinations automatically, however backups can be configured to be sent to third-party servers.', 'it-l10n-backupbuddy' ); ?></p>

<h2><?php esc_html_e( 'How we protect your data', 'it-l10n-backupbuddy' ); ?></h2>

<h3><?php esc_html_e( 'Backups', 'it-l10n-backupbuddy' ); ?></h3>
<p><?php esc_html_e( 'Backup zip files are stored with hashed file names to prevent filename guessing and directory browsing is disabled.', 'it-l10n-backupbuddy' ); ?></p>

<h2><?php esc_html_e( 'What third parties we receive data from', 'it-l10n-backupbuddy' ); ?></h2>

<h3><?php esc_html_e( 'Backup Destinations', 'it-l10n-backupbuddy' ); ?></h3>
<p><?php esc_html_e( 'Backups can be sent to third-party destination servers, including but not limited to:', 'it-l10n-backupbuddy' ); ?></p>

<ul>
	<?php
	foreach ( $third_parties as $third_party ) :
		echo '<li>';
		echo esc_html( $third_party['name'] );
		if ( ! empty( $third_party['privacy_url'] ) ) :
			printf( ' | <a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $third_party['privacy_url'] ), esc_html__( 'Privacy Policy', 'it-l10n-backupbuddy' ) );
		endif;
		echo '</li>';
	endforeach;
	?>
</ul>

<p><?php esc_html_e( 'Links to third party privacy policies have been included.', 'it-l10n-backupbuddy' ); ?></p>
