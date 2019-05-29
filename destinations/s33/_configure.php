<?php
/**
 * Settings to display in a form for a user to configure.
 *
 * Pre-populated variables coming into this script:
 *      $destination_settings
 *      $mode
 *
 * @package BackupBuddy
 */

global $pb_hide_test, $pb_hide_save;
$pb_hide_test = false;

if ( ! is_callable( 'curl_init' ) ) {
	pb_backupbuddy::alert( 'Error #43893489: The Amazon S3 destination requires curl. Please enable it on your server to use this destination.', true );
	echo '<br>';
	//return false;
}

$default_name = null;

if ( 'save' != $mode ) {
	if ( 'add' == $mode ) {
		$default_name = 'My S3 (v3)';
	}
} else { // save mode.
	if ( isset( $_POST['pb_backupbuddy_directory'] ) ) {
		$_POST['pb_backupbuddy_bucket'] = strtolower( $_POST['pb_backupbuddy_bucket'] ); // bucket must be lower-case.
	}
}

// Form settings.
$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'title',
		'title'     => __( 'Destination name', 'it-l10n-backupbuddy' ),
		'tip'       => __( 'Name of the new destination to create. This is for your convenience only.', 'it-l10n-backupbuddy' ),
		'rules'     => 'required|string[1-45]',
		'default'   => $default_name,
	)
);

$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'accesskey',
		'title'     => __( 'AWS access key', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Example: BSEGHGSDEUOXSQOPGSBE] - Log in to your Amazon S3 AWS Account and navigate to Account: Access Credentials: Security Credentials.', 'it-l10n-backupbuddy' ),
		'rules'     => 'required|string[1-45]',
		'after'     => ' <a target="_new" href="https://ithemeshelp.zendesk.com/hc/en-us/articles/211129517-Amazon-S3">Help setting up S3</a>',
		'css'       => 'width: 250px;',
	)
);

if ( 'add' === $mode ) { // text mode to show secret key during adding.
	$secretkey_type_mode = 'text';
} else { // pass field to hide secret key for editing.
	$secretkey_type_mode = 'password';
}

$settings_form->add_setting(
	array(
		'type'      => $secretkey_type_mode,
		'name'      => 'secretkey',
		'title'     => __( 'AWS secret key', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Example: GHOIDDWE56SDSAZXMOPR] - Log in to your Amazon S3 AWS Account and navigate to Account: Access Credentials: Security Credentials.', 'it-l10n-backupbuddy' ),
		'css'       => 'width: 250px;',
		'rules'     => 'required|string[1-45]',
	)
);

$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'bucket',
		'title'     => __( 'Bucket name', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Example: wordpress_backups] - This bucket will be created for you automatically if it does not already exist. Bucket names must be globally unique amongst all Amazon S3 users.', 'it-l10n-backupbuddy' ),
		'after'     => '',
		'css'       => 'width: 250px;',
		'rules'     => 'required|string[1-500]',
	)
);

$settings_form->add_setting(
	array(
		'type'      => 'select',
		'name'      => 'region',
		'title'     => __( 'Bucket region', 'it-l10n-backupbuddy' ),
		'options'   => array(
			's3.amazonaws.com'                   => 'us-east-1 &nbsp;|&nbsp; US East 1 (US Standard; N. Virginia)',
			's3-us-east-2.amazonaws.com'         => 'us-east-2 &nbsp;|&nbsp; US East 2 (Ohio)',

			's3-us-west-1.amazonaws.com'         => 'us-west-1 &nbsp;|&nbsp; US West 1 (N. California)',
			's3-us-west-2.amazonaws.com'         => 'us-west-2 &nbsp;|&nbsp; US West 2 (Oregon)',

			's3-ca-central-1.amazonaws.com'      => 'ca-central-1 &nbsp;|&nbsp; Canada Central 1',

			's3-ap-south-1.amazonaws.com'        => 'ap-south-1 &nbsp;|&nbsp; Asia Pacific South 1 (Mumbai)',

			's3-ap-northeast-1.amazonaws.com'    => 'ap-northeast-1 &nbsp;|&nbsp; Asia Pacific Northeast 1 (Tokyo)',
			's3-ap-northeast-2.amazonaws.com'    => 'ap-northeast-2 &nbsp;|&nbsp; Asia Pacific Northeast 2 (Seoul)',

			's3-ap-southeast-1.amazonaws.com'    => 'ap-southeast-1 &nbsp;|&nbsp; Asia Pacific Southeast 1 (Singapore)',
			's3-ap-southeast-2.amazonaws.com'    => 'ap-southeast-2 &nbsp;|&nbsp; Asia Pacific Southeast 2 (Sydney)',

			's3-eu-central-1.amazonaws.com'      => 'eu-central-1 &nbsp;|&nbsp; EU Central 1 (Frankfurt)',

			's3-eu-west-1.amazonaws.com'         => 'eu-west-1 &nbsp;|&nbsp; EU West 1 (Ireland)',
			's3-eu-west-2.amazonaws.com'         => 'eu-west-2 &nbsp;|&nbsp; EU West 2 (London)',

			's3-sa-east-1.amazonaws.com'         => 'sa-east-1 &nbsp;|&nbsp; South America East 1 (Sao Paulo)',

			's3-cn-north-1.amazonaws.com.cn'     => 'cn-north-1 &nbsp;|&nbsp; China North 1 (Beijing)',
			's3-cn-northwest-1.amazonaws.com.cn' => 'cn-northwest-1 &nbsp;|&nbsp; China Northwest 1 (Ningxia)',

			/*
			's3-us-gov-west-1.amazonaws.com'            =>      'US GovCloud',
			's3-fips-us-gov-west-1.amazonaws.com'       =>      'US GovCloud (FIPS 140-2)',
			's3-website-us-gov-west-1.amazonaws.com'    =>      'US GovCloud (website)',
			*/
		),
		'tip'       => __( '[Default: US East aka US Standard] - Determines the region where your S3 bucket exists. This must be correct for BackupBuddy to access your bucket. Select the S3 Transfer Acceleration option to potentially significantly increase speeds, especially when sending to a bucket outside your geographical location. You must enable this option per-bucket in your AWS Console. Amazon may charge for use of this feature.', 'it-l10n-backupbuddy' ),
		'rules'     => 'required',
	)
);

$settings_form->add_setting(
	array(
		'type'      => 'select',
		'name'      => 'storage',
		'title'     => __( 'Storage Class', 'it-l10n-backupbuddy' ),
		'options'   => array(
			'STANDARD'                  => 'Standard Storage [default] 99.999999999% durability &nbsp;|&nbsp; 99.99% availability',
			'REDUCED_REDUNDANCY'        => 'Reduced Redundancy &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 99.99% durability &nbsp;|&nbsp; 99.99% availability (less cost, less robust)',
			'STANDARD_IA'               => 'Infrequent Access &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 99.999999999% durability &nbsp;|&nbsp; &nbsp;&nbsp;99.9% availability (less storage cost, fee to restore)',
			'GLACIER'                   => 'Glacier &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 99.999999999% durability &nbsp;|&nbsp; &nbsp;&nbsp;99.9% availability (less cost, slow restore)',
		),
		'tip'       => __( '[Default: Standard Storage] - Determines the type of storage to use when placing this file on Amazon S3. Reduced redundancy offers less protection against loss but costs less. Infrequent access is cheaper for storage but requires a fee for retrieval. Glacier is cheaper for storage but requires a very slow restore before files are accessible. See Amazon for for details.', 'it-l10n-backupbuddy' ),
		'rules'     => 'required',
	)
);

$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'directory',
		'title'     => __( 'Directory (optional)', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Example: backupbuddy] - Directory name to place the backup within.', 'it-l10n-backupbuddy' ),
		'rules'     => 'string[0-500]',
	)
);

$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'full_archive_limit',
		'title'     => __( 'Full backup limit', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Example: 5] - Enter 0 for no limit. This is the maximum number of Full (complete) backup archives for this site (based on filename) to be stored in this specific destination. If this limit is met the oldest backup of this type will be deleted.', 'it-l10n-backupbuddy' ),
		'rules'     => 'required|int[0-9999999]',
		'css'       => 'width: 50px;',
		'after'     => ' backups',
	)
);
$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'db_archive_limit',
		'title'     => __( 'Database only limit', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Example: 5] - Enter 0 for no limit. This is the maximum number of Database Only backup archives for this site (based on filename) to be stored in this specific destination. If this limit is met the oldest backup of this type will be deleted.', 'it-l10n-backupbuddy' ),
		'rules'     => 'required|int[0-9999999]',
		'css'       => 'width: 50px;',
		'after'     => ' backups',
	)
);
$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'themes_archive_limit',
		'title'     => __( 'Themes only limit', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Example: 5] - Enter 0 for no limit. This is the maximum number of this type of archive to be stored in this specific destination. If this limit is met the oldest backup of this type will be deleted.', 'it-l10n-backupbuddy' ),
		'rules'     => 'int[0-9999999]',
		'css'       => 'width: 50px;',
		'after'     => ' backups. &nbsp;<span class="description">0 or blank for no limit.</span>',
	)
);
$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'plugins_archive_limit',
		'title'     => __( 'Plugins only limit', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Example: 5] - Enter 0 for no limit. This is the maximum number of this type of archive to be stored in this specific destination. If this limit is met the oldest backup of this type will be deleted.', 'it-l10n-backupbuddy' ),
		'rules'     => 'int[0-9999999]',
		'css'       => 'width: 50px;',
		'after'     => ' backups. &nbsp;<span class="description">0 or blank for no limit.</span>',
	)
);
$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'media_archive_limit',
		'title'     => __( 'Media only limit', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Example: 5] - Enter 0 for no limit. This is the maximum number of this type of archive to be stored in this specific destination. If this limit is met the oldest backup of this type will be deleted.', 'it-l10n-backupbuddy' ),
		'rules'     => 'int[0-9999999]',
		'css'       => 'width: 50px;',
		'after'     => ' backups. &nbsp;<span class="description">0 or blank for no limit.</span>',
	)
);
$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'files_archive_limit',
		'title'     => __( 'Files only limit', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Example: 5] - Enter 0 for no limit. This is the maximum number of this type of archive to be stored in this specific destination. If this limit is met the oldest backup of this type will be deleted.', 'it-l10n-backupbuddy' ),
		'rules'     => 'int[0-9999999]',
		'css'       => 'width: 50px;',
		'after'     => ' backups. &nbsp;<span class="description">0 or blank for no limit.</span>',
	)
);
$settings_form->add_setting(
	array(
		'type'      => 'checkbox',
		'name'      => 'accelerate',
		'options'   => array(
			'unchecked'  => '0',
			'checked'    => '1',
		),
		'title'     => __( 'Use Transfer Acceleration', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Default: disabled] - When enabled, transfers will be sent using a geographically near endpoint to your server and then transferred internally by Amazon to your final endpoint location. This allows for faster transfers from your server into Amazon. Additional charges may apply from Amazon. Transfer acceleration must be enabled by you within your Amazon control panel for this bucket prior to enabling this feature.', 'it-l10n-backupbuddy' ),
		'css'       => '',
		'after'     => '<span class="description"> ' . __( 'Additional charges may apply from Amazon.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'     => '',
	)
);
$settings_form->add_setting(
	array(
		'type'      => 'title',
		'name'      => 'advanced_begin',
		'title'     => '<span class="dashicons dashicons-arrow-right"></span> ' . __( 'Advanced Options', 'it-l10n-backupbuddy' ),
		'row_class' => 'advanced-toggle-title',
	)
);
$settings_form->add_setting(
	array(
		'type'      => 'select',
		'name'      => 'storage',
		'title'     => __( 'Storage Class', 'it-l10n-backupbuddy' ),
		'options'   => array(
			'STANDARD'                  => 'Standard Storage [default] 99.999999999% durability &nbsp;|&nbsp; 99.99% availability',
			'REDUCED_REDUNDANCY'        => 'Reduced Redundancy &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 99.99% durability &nbsp;|&nbsp; 99.99% availability',

		),
		'tip'       => __( '[Default: Standard Storage] - Determines the type of storage to use when placing this file on Amazon S3. Reduced redundancy offers less protection against loss but costs less. See Amazon for for details.', 'it-l10n-backupbuddy' ),
		'rules'     => 'required',
		'row_class' => 'advanced-toggle',
	)
);
$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'max_burst',
		'title'     => __( 'Send per burst', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Default 15] - This is the amount of data that will be sent per burst within a single PHP page load/chunk. Bursts happen within a single page load. Chunks occur when broken up between page loads/PHP instances. Reduce if hitting PHP memory limits. Chunking time limits will only be checked between bursts. Lower burst size if timeouts occur before chunking checks trigger.', 'it-l10n-backupbuddy' ),
		'rules'     => 'required|int[5-9999999]',
		'css'       => 'width: 50px;',
		'after'     => ' MB',
		'row_class' => 'advanced-toggle',
	)
);
$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'max_time',
		'title'     => __( 'Max time per chunk', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Example: 30] - Enter 0 for no limit (aka no chunking; bursts may still occur based on burst size setting). This is the maximum number of seconds per page load that bursts will occur. If this time is exceeded when a burst finishes then the next burst will be chunked and ran on a new page load. Multiple bursts may be sent within each chunk.', 'it-l10n-backupbuddy' ),
		'rules'     => '',
		'css'       => 'width: 50px;',
		'after'     => ' secs. <span class="description">' . __( 'Blank for detected default:', 'it-l10n-backupbuddy' ) . ' ' . backupbuddy_core::detectMaxExecutionTime() . ' sec</span>',
		'row_class' => 'advanced-toggle',
	)
);
$settings_form->add_setting(
	array(
		'type'      => 'checkbox',
		'name'      => 'ssl',
		'options'   => array(
			'unchecked'  => '0',
			'checked'    => '1',
		),
		'title'     => __( 'Encrypt connection', 'it-l10n-backupbuddy' ) . '*',
		'tip'       => __( '[Default: enabled] - When enabled, all transfers will be encrypted with SSL encryption. Disabling this may aid in connection troubles but results in lessened security. Note: Once your files arrive on our server they are encrypted using AES256 encryption. They are automatically decrypted upon download as needed.', 'it-l10n-backupbuddy' ),
		'css'       => '',
		'after'     => '<span class="description"> ' . __( 'Enable connecting over SSL.', 'it-l10n-backupbuddy' ) . '<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;* Files are always encrypted with AES256 upon arrival at S3.</span>',
		'rules'     => '',
		'row_class' => 'advanced-toggle',
	)
);
$settings_form->add_setting(
	array(
		'type'      => 'checkbox',
		'name'      => 'use_server_cert',
		'options'   => array(
			'unchecked'  => '0',
			'checked'    => '1',
		),
		'title'     => __( 'Use system CA bundle', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Default: disabled] - When enabled, BackupBuddy will use your web server\'s certificate bundle for connecting to the server instead of BackupBuddy bundle. Use this if SSL fails due to SSL certificate issues.', 'it-l10n-backupbuddy' ),
		'css'       => '',
		'after'     => '<span class="description"> ' . __( 'Use webserver certificate bundle instead of BackupBuddy\'s.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'     => '',
		'row_class' => 'advanced-toggle',
	)
);
$settings_form->add_setting(
	array(
		'type'      => 'checkbox',
		'name'      => 'disable_hostpeer_verficiation',
		'options'   => array(
			'unchecked'  => '0',
			'checked'    => '1',
		),
		'title'     => __( 'Disable SSL Verifications', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Default: disabled] - When enabled, the SSL host and peer information will not be verified. While the connection will still be encrypted SSL\'s man-in-the-middle protection will be voided. Disable only if you understand and if directed by support to work around host issues.', 'it-l10n-backupbuddy' ),
		'css'       => '',
		'after'     => '<span class="description"> ' . __( 'Check only if directed by support. Use with caution.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'     => '',
		'row_class' => 'advanced-toggle',
	)
);
$settings_form->add_setting(
	array(
		'type'      => 'checkbox',
		'name'      => 'debug_mode',
		'options'   => array(
			'unchecked'  => '0',
			'checked'    => '1',
		),
		'title'     => __( 'Enable SDK debug mode', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Default: disabled] - When enabled, additional data will be logged by the SDK for troubleshooting.', 'it-l10n-backupbuddy' ),
		'css'       => '',
		'after'     => '<span class="description"> ' . __( 'Check if directed by support.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'     => '',
		'row_class' => 'advanced-toggle',
	)
);
$settings_form->add_setting(
	array(
		'type'      => 'text',
		'name'      => 'max_filelist_keys',
		'title'     => __( 'Max number of files to list', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Example: 250] - This is the maximum number of files to return in a given file listing request from the S3 servers.', 'it-l10n-backupbuddy' ),
		'rules'     => 'required|int[0-9999999]',
		'css'       => 'width: 50px;',
		'after'     => ' files',
		'row_class' => 'advanced-toggle',
	)
);

if ( 'edit' !== $mode || '0' == $destination_settings['disable_file_management'] ) {
	$settings_form->add_setting(
		array(
			'type'      => 'checkbox',
			'name'      => 'disable_file_management',
			'options'   => array(
				'unchecked'  => '0',
				'checked'    => '1',
			),
			'title'     => __( 'Disable file management', 'it-l10n-backupbuddy' ),
			'tip'       => __( '[[Default: unchecked] - When checked, selecting this destination disables browsing or accessing files stored at this destination from within BackupBuddy. NOTE: Once enabled this cannot be disabled without deleting and re-creating this destination. NOTE: Once enabled this cannot be disabled without deleting and re-creating this destination.', 'it-l10n-backupbuddy' ),
			'css'       => '',
			'rules'     => '',
			'after'     => __( 'Once disabled you must recreate the destination to re-enable.', 'it-l10n-backupbuddy' ),
			'row_class' => 'advanced-toggle',
		)
	);
}
$settings_form->add_setting(
	array(
		'type'      => 'checkbox',
		'name'      => 'disabled',
		'options'   => array(
			'unchecked' => '0',
			'checked' => '1',
		),
		'title'     => __( 'Disable destination', 'it-l10n-backupbuddy' ),
		'tip'       => __( '[Default: unchecked] - When checked, this destination will be disabled and unusable until re-enabled. Use this if you need to temporary turn a destination off but don\t want to delete it.', 'it-l10n-backupbuddy' ),
		'css'       => '',
		'after'     => '<span class="description"> ' . __( 'Check to disable this destination until re-enabled.', 'it-l10n-backupbuddy' ) . '</span>',
		'rules'     => '',
		'row_class' => 'advanced-toggle',
	)
);
