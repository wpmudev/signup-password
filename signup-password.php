<?php
/*
Plugin Name: Signup Password
Plugin URI: 
Description:
Author: Andrew Billits
Version: 1.0.5
Author URI:
*/

/* 
Copyright 2007-2009 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//
$signup_password_use_encryption = 'no'; //Either 'yes' OR 'no'
//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//
add_action('wp_head', 'signup_password_stylesheet');
add_action('signup_extra_fields', 'signup_password_fields');
add_filter('wpmu_validate_user_signup', 'signup_password_filter');
add_filter('signup_blogform', 'signup_password_fields_pass_through');
add_filter('add_signup_meta', 'signup_password_meta_filter',99);
add_filter('random_password', 'signup_password_random_password_filter');
//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//

function signup_password_encrypt($data) {
	if(!isset($chars))
	{
	// 3 different symbols (or combinations) for obfuscation
	// these should not appear within the original text
	$sym = array('¶', '¥xQ', '|');
	
	foreach(range('a','z') as $key=>$val)
	$chars[$val] = str_repeat($sym[0],($key + 1)).$sym[1];
	$chars[' '] = $sym[2];
	
	unset($sym);
	}
	
	// encrypt
	$data = strtr(strtolower($data), $chars);
	return $data;
	
}

function signup_password_decrypt($data) {
	if(!isset($chars))
	{
	// 3 different symbols (or combinations) for obfuscation
	// these should not appear within the original text
	$sym = array('¶', '¥xQ', '|');
	
	foreach(range('a','z') as $key=>$val)
	$chars[$val] = str_repeat($sym[0],($key + 1)).$sym[1];
	$chars[' '] = $sym[2];
	
	unset($sym);
	}
	
	// decrypt
	$charset = array_flip($chars);
	$charset = array_reverse($charset, true);
	
	$data = strtr($data, $charset);
	unset($charset);
	return $data;
}

function signup_password_filter($content) {
	$password_1 = $_POST['password_1'];
	$password_2 = $_POST['password_2'];
	if ( !empty( $password_1 )  && $_POST['stage'] == 'validate-user-signup' ) {
		if ( $password_1 != $password_2 ) {
			$content['errors']->add('password', __('Passwords do not match.'));
		}
	}
	return $content;
}

function signup_password_meta_filter($meta) {
	global $signup_password_use_encryption;
	$password_1 = $_POST['password_1'];
	if ( !empty( $password_1 ) ) {
		if ( $signup_password_use_encryption == 'yes' ) {
			$password_1 = signup_password_encrypt($password_1);
		}
		$add_meta = array('password' => $password_1);
		$meta = array_merge($add_meta, $meta);
	}
	return $meta;
}

function signup_password_random_password_filter($password) {
	global $wpdb, $signup_password_use_encryption;

	if ( ! empty($_GET['key']) ) {
		$key = $_GET['key'];
	} else {
		$key = $_POST['key'];
	}
	if ( !empty($_POST['password_1']) ) {
		$password = $_POST['password_1'];
	} else if ( !empty( $key ) ) {
		$signup = $wpdb->get_row("SELECT * FROM " . $wpdb->signups . " WHERE activation_key = '" . $key . "'");
		if ( empty($signup) || $signup->active ) {
			//bad key or already active
		} else {
			//check for password in signup meta
			$meta = unserialize($signup->meta);
			if ( !empty( $meta['password'] ) ) {
				if ( $signup_password_use_encryption == 'yes' ) {
					$password = signup_password_decrypt($meta['password']);
				} else {
					$password = $meta['password'];
				}
			}
		}		
	}
	return $password;
}

//------------------------------------------------------------------------//
//---Output Functions-----------------------------------------------------//
//------------------------------------------------------------------------//

function signup_password_stylesheet() {
?>
<style type="text/css">
	.mu_register #password_1,
	.mu_register #password_2 { width:100%; font-size: 24px; margin:5px 0; }
</style>
<?php
}

function signup_password_fields_pass_through() {
	if ( !empty( $_POST['password_1'] ) && !empty( $_POST['password_2'] ) ) {
		?>
        <input type="hidden" name="password_1" value="<?php echo $_POST['password_1']; ?>" />
	    <?php
	}
}

function signup_password_fields($errors) {
	$error = $errors->get_error_message('password');
	?>
    <label for="password"><?php _e('Password'); ?>:</label>
		<?php
        if($error) {
			echo '<p class="error">' . $error . '</p>';
        }
		?>
		<input name="password_1" type="password" id="password_1" value="" autocomplete="off" maxlength="20" /><br />
		(<?php _e('Leave fields blank for a random password to be generated.') ?>)
    <label for="password"><?php _e('Confirm Password'); ?>:</label>
		<input name="password_2" type="password" id="password_2" value="" autocomplete="off" maxlength="20" /><br />
		(<?php _e('Type your new password again.') ?>)
	<?php
}

?>
