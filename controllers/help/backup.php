<?php
/**
 * Backup Help Controller
 *
 * Incoming variable:
 *
 *   $screen
 *
 * @package BackupBuddy
 */

$screen->add_help_tab(
	array(
		'id'      => 'pb_backupbuddy_backuptypes',
		'title'   => __( 'Backup Types', 'it-l10n-backupbuddy' ),
		'content' => '<p>' . __( '<strong>Full backups</strong> by default contain everything found in Database backups as well as all files in your WordPress installation directory
			and its subdirectories. This includes files such as media, plugins, themes, images, and any other files found in your WordPress directory and subdirectories.
			Additional inclusions or exclusions may be defined on the Settings page or by modifying the profile below.
			</p><p>
			<strong>Database backups</strong> by default contain WordPress tables which includes posts, pages, comments widget content,
			media titles & descriptions (but not the media files themselves), and other WordPress settings.
			Additional inclusions or exclusions may be defined on the Settings page or by modifying the profile below.
			</p><p>
			<strong>Themes Only backups</strong> only backup the site\'s themes folder which contains all themes installed on your site..
			</p><p>
			<strong>Plugins Only backups</strong> only backup the site\'s plugins folder which contains all plugins installed on your site. Optionally, you can backup only plugins that have been activated by enable Active Plugins Only from the Settings page.
			</p><p>
			<strong>Media Only backups</strong> only backup the site\'s uploads folder.
			</p><p>
			<strong>Files Only backups</strong> omit the database and only backup files. You may customize which files are included or excluded from files only
			backups using Profiles to customize this to your needs.', 'it-l10n-backupbuddy' ) . '</p>',
	)
);

$screen->add_help_tab(
	array(
		'id'      => 'pb_backupbuddy_backupprofiles',
		'title'   => __( 'Backup Profiles', 'it-l10n-backupbuddy' ),
		'content' => '<p>' . __( 'Backup profiles allow you to customize what is backed up and other various backup settings on a case-by-case basis.
			This allows for greater customization and control. There are three types of profiles which may be created:
			Complete / Full Backups, Database Only Backups, and Files Only Backups.', 'it-l10n-backupbuddy' ) . '</p><p>' .
			__( 'Click the plus (+) icon in the Profiles list to create a new profile. Click the gear icon next to an existing profile to edit it.', 'it-l10n-backupbuddy' ) . '</p>',
	)
);
