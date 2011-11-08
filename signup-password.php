<?php
/*
Plugin Name: Set Password on WordPress Multisite Blog Creation
Plugin URI: http://premium.wpmudev.org/project/set-password-on-wordpress-mu-blog-creation
Description: Set Password on WordPress Multisite Blog Creation
Author: S H Mohanjith (Incsub), Andrew Billits (Incsub)
Version: 1.0.7
Author URI: http://premium.wpmudev.org
Network: true
WDP ID: 35
Text Domain: signup_password
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

global $signup_password_form_printed;
//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//
$signup_password_use_encryption = 'no'; //Either 'yes' OR 'no'
$signup_password_form_printed = 0;
//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//
add_action('init', 'signup_password_init');
add_action('wp_footer', 'signup_password_stylesheet');
add_action('signup_extra_fields', 'signup_password_fields');
add_filter('wpmu_validate_user_signup', 'signup_password_filter');
add_filter('signup_blogform', 'signup_password_fields_pass_through');
add_filter('add_signup_meta', 'signup_password_meta_filter',99);
add_filter('random_password', 'signup_password_random_password_filter');
//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//

function signup_password_init() {
	if ( !is_multisite() )
		exit( 'The Signup Password plugin is only compatible with WordPress Multisite.' );
		
	load_plugin_textdomain('signup_password', false, dirname(plugin_basename(__FILE__)).'/languages');
}

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
			$content['errors']->add('password', __('Passwords do not match.', 'signup_password'));
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
	global $signup_password_form_printed;
	
	if ($signup_password_form_printed) {
?>
<style type="text/css">
	.mu_register #password_1,
	.mu_register #password_2 { width:100%; font-size: 24px; margin:5px 0; }
</style>
<?php
	}
}

function signup_password_fields_pass_through() {
	global $signup_password_form_printed;
	
	if ( !empty( $_POST['password_1'] ) && !empty( $_POST['password_2'] ) ) {
		$signup_password_form_printed = 1;
		?>
        <input type="hidden" name="password_1" value="<?php echo $_POST['password_1']; ?>" />
	    <?php
	}
}

function signup_password_fields($errors) {
	global $signup_password_form_printed;
	
	$error = $errors->get_error_message('password');
	$signup_password_form_printed = 1;
	?>
    <label for="password"><?php _e('Password', 'signup_password'); ?>:</label>
		<?php
        if($error) {
			echo '<p class="error">' . $error . '</p>';
        }
		?>
		<input name="password_1" type="password" id="password_1" value="" autocomplete="off" maxlength="20" /><br />
		(<?php _e('Leave fields blank for a random password to be generated.', 'signup_password') ?>)
    <label for="password"><?php _e('Confirm Password', 'signup_password'); ?>:</label>
		<input name="password_2" type="password" id="password_2" value="" autocomplete="off" maxlength="20" /><br />
		(<?php _e('Type your new password again.', 'signup_password') ?>)
	<?php
}

if ( !function_exists( 'wdp_un_check' ) ) {
	add_action( 'admin_notices', 'wdp_un_check', 5 );
	add_action( 'network_admin_notices', 'wdp_un_check', 5 );

	function wdp_un_check() {
		if ( !class_exists( 'WPMUDEV_Update_Notifications' ) && current_user_can( 'edit_users' ) )
			echo '<div class="error fade"><p>' . __('Please install the latest version of <a href="http://premium.wpmudev.org/project/update-notifications/" title="Download Now &raquo;">our free Update Notifications plugin</a> which helps you stay up-to-date with the most stable, secure versions of WPMU DEV themes and plugins. <a href="http://premium.wpmudev.org/wpmu-dev/update-notifications-plugin-information/">More information &raquo;</a>', 'wpmudev') . '</a></p></div>';
	}
}

