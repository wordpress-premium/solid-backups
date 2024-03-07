<script>
	jQuery( '#pageTitle' ).addClass('login-screen').html( 'Welcome, please log in to continue.' );
	jQuery('.main_box_wrap').addClass('main_box_wrap--small');
</script>
<?php


if ( pb_backupbuddy::_POST( 'password' ) != '' ) {
	global $pb_login_attempts;
	pb_backupbuddy::alert( 'Invalid password. Please enter the password you provided within Solid Backups Settings. Attempt #' . $pb_login_attempts . '.' );
	echo '<br>';
}
?>

<p>Enter your Importer password below to begin.</p>

<form method="post">
	<input type="hidden" name="action" value="login">
	<input type="password" name="password" style="width: 250px;">
	<input type="submit" name="submit" value="Authenticate" class="it-button">
</form>
