<?php
if ( ! defined( 'PB_IMPORTBUDDY' ) || ( true !== PB_IMPORTBUDDY ) ) {
	die( '<html></html>' );
}

if ( '' == pb_backupbuddy::_POST( 'newpassword' ) ) {
	die( 'Error #8493489: Missing password.' );
}

die( md5( pb_backupbuddy::_POST( 'newpassword' ) ) );