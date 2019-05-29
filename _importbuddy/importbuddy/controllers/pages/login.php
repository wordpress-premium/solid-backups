<?php
/**
 * Login Page
 *
 * @package BackupBuddy
 */

?>
<script>jQuery( '#pageTitle' ).html( 'Authentication Required' );</script>
<?php
if ( '' != pb_backupbuddy::_POST( 'password' ) ) {
	global $pb_login_attempts;
	pb_backupbuddy::alert( 'Invalid password. Please enter the password you provided within BackupBuddy Settings. Attempt #' . $pb_login_attempts . '.' );
	echo '<br>';
}

if ( ! is_callable( 'json_decode' ) ) {
	$message = 'Error #84398434: Missing required PHP function json_decode(). Your PHP version is too old or damaged. It is NOT compatible with WordPress as it is. Please contact your host to fix this.';
	pb_backupbuddy::status( 'error', $message );
	pb_backupbuddy::alert( $message, true );
}
?>

<p>Enter your ImportBuddy password below to begin.</p>

<br>

<form method="post" id="importbuddy-auth-form">
	<input type="hidden" name="action" value="login">
	<input type="password" name="password" style="width: 250px; vertical-align: -2px;">
	<input type="submit" name="submit" value="Authenticate" class="it-button">
	<button href="#pb_forgotpassword_modal" class="button button-secondary leanModal createdb_modal_link">Forgot Password?</button>
</form>

<script type="text/javascript">
	jQuery(function() {
		jQuery( '#importbuddy-auth-form input[type=password]' ).focus();

		jQuery('.leanModal').leanModal(
			{ top : 45, overlay : 0.4, closeButton: ".modal_close" }
		);

		jQuery( '#createpass_form' ).submit(function(){

			if ( jQuery( '#new_pass' ).val() != jQuery(this).find( '#new_pass_confirm' ).val() ) {
				alert( 'Password and confirmation do not match.' );
				return false;
			}

			if ( '' === jQuery( '#new_pass' ).val() ) {
				alert( 'You must provide a new password.' );
				return false;
			}

			jQuery( '.createpass_loading' ).show();
			jQuery.post('importbuddy.php?ajax=hash_forgotpass',
			{
				newpassword: jQuery( '#new_pass' ).val(),
			}, function(data) {

					data = jQuery.trim( data );
					jQuery( '.createpass_loading' ).hide();

					jQuery( '.forgotpass_form_wrap' ).hide();
					jQuery( '.forgotpass_finish_hash').val( data );
					jQuery( '.forgotpass_finish_wrap' ).show();
				}
			);

			return false;

		});
	});
</script>

<div id="pb_forgotpassword_modal" style="display: none;">
	<div class="modal">
		<div class="modal_header">
			<a class="modal_close">&times;</a>
			<h2>Password Reset</h2>
			After submitting you will need to edit your importbuddy.php file on this server and edit a line of code with a hashed version of this password.
		</div>
		<div class="modal_content">


			<div class="forgotpass_form_wrap">


				<center>
					<form id="createpass_form">
						<table>
							<tr>
								<td>New Password</td><td><input type="password" name="newpassword" id="new_pass"></td>
							</tr>
							<tr>
								<td>Confirm Password</td><td><input type="password" name="newpassword_confirm" id="new_pass_confirm"></td>
							</tr>
						</table>
						<input type="submit" name="submit" value="Submit" class="button-primary">
					</form>
					<span class="createpass_loading" style="display: none; margin-left: 10px;"><img src="<?php echo pb_backupbuddy::plugin_url(); ?>/images/loading.gif" alt="'Loading..." title="Loading..." width="16" height="16" style="vertical-align: -3px;"></span>
				</center>

			</div>


			<div class="forgotpass_finish_wrap" style="display: none;">
				To enable this new password for accessing importbuddy.php open importbuddy.php in a text editor and find line 13 that looks like the following, replacing the X's in this line with the password hash code below. Make sure you re-save this edited version to the server then refresh this page to log in.<br><br>

				<i>define( 'PB_PASSWORD', 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX' );</i><br><br>

				<b>New password hash code:</b><br>
				<input type="text" class="forgotpass_finish_hash" value="" readonly="readonly" size="40">
			</div>


		</div>
	</div>
</div>
