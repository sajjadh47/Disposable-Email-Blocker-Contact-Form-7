<?php
/**
 * Plugin Name: Disposable Email Blocker - Contact Form 7
 * Plugin URI: https://wordpress.org/plugins/disposable-email-blocker-contact-form-7/
 * Author: Sajjad Hossain Sagor
 * Description: Now You Can Easily Block/Prevent Disposable/Temporary Spam Emails From Submitting on CF7 Form.
 * Version: 1.0.1
 * Author URI: https://sajjadhsagor.com
 * Text Domain: disposable-email-blocker-contact-form-7
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// plugin root path....
define( 'DEBCF7_ROOT_DIR', dirname( __FILE__ ) );

// plugin root url....
define( 'DEBCF7_ROOT_URL', plugin_dir_url( __FILE__ ) );

// plugin version
define( 'DEBCF7_VERSION', '1.0.0' );

// Checking if Contact Form 7 plugin is either installed or active
add_action( 'admin_init', 'debcf7_check' );

register_activation_hook( __FILE__, 'debcf7_check' );

function debcf7_check()
{
	if ( ! in_array( 'contact-form-7/wp-contact-form-7.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )
	{
		// Deactivate the plugin
		deactivate_plugins( __FILE__ );

		// Throw an error in the wordpress admin console
		$error_message = __( 'Disposable Email Blocker - Contact Form 7 requires <a href="https://wordpress.org/plugins/contact-form-7/">Contact Form 7</a> plugin to be active!', 'disposable-email-blocker-contact-form-7' );

		wp_die( $error_message, __( 'Contact Form 7 Not Found', 'disposable-email-blocker-contact-form-7' ) );
	}
}

// load translation files...
add_action( 'plugins_loaded', 'debcf7_load_plugin_textdomain' );

function debcf7_load_plugin_textdomain()
{	
	load_plugin_textdomain( 'disposable-email-blocker-contact-form-7', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

// add disposable emails found message to show on form validation
add_filter( 'wpcf7_messages', 'debcf7_disposable_emails_found_msg' );

function debcf7_disposable_emails_found_msg( $message )
{
	$message['disposable_emails_found'] = array(
		'description' => __( "Email was disposable/temporary", 'disposable-email-blocker-contact-form-7' ),
		'default' => __( "Disposable/Temporary emails are not allowed! Please use a non temporary email", 'disposable-email-blocker-contact-form-7' ),
	);

	return $message;
}

// check if disposable email is found and if so then mark form as invalid and show message
add_filter( 'wpcf7_validate_email', 'debcf7_block_disposable_emails', 99, 2 );

add_filter( 'wpcf7_validate_email*', 'debcf7_block_disposable_emails', 99, 2 );

function debcf7_block_disposable_emails( $result, $tag )
{
	// first check if blokcing disposable emails are enabled
	if ( isset( $_POST['_wpcf7'] ) && ! empty( $_POST['_wpcf7'] ) )
	{	
		$post_id = sanitize_text_field( $_POST['_wpcf7'] );

		if ( get_post_meta( $post_id, 'debcf7_enabled', true ) !== 'on' )
		{	
			return $result;
		}
	}
	
	$name = $tag->name;

	$email = isset( $_POST[$name] ) ? trim( wp_unslash( strtr( (string) $_POST[$name], "\n", " " ) ) ) : '';

	if( filter_var( $email, FILTER_VALIDATE_EMAIL ) )
	{
		// split on @ and return last value of array (the domain)
    	$domain = explode('@', $field_submit );
    	
    	$domain = array_pop( $domain );

    	// get domains list from json file
		$disposable_emails_db = file_get_contents( DEBCF7_ROOT_DIR . '/assets/data/domains.min.json' );

		// convert json to php array
		$disposable_emails = json_decode( $disposable_emails_db );

		// check if domain is in disposable db
		if ( in_array( $domain, $disposable_emails ) )
		{	
			$result->invalidate( $tag, wpcf7_get_message( 'disposable_emails_found' ) );
		}
	}

	return $result;
}

// add enable checkox for plugin feature
add_action( 'wpcf7_admin_misc_pub_section', 'debcf7_disposable_email_checkup_switch' );

function debcf7_disposable_email_checkup_switch( $post_id )
{
	$enabled = get_post_meta( $post_id, 'debcf7_enabled', true );
	
	echo '<p style="padding: 0 1em;">
			<label for="debcf7_enable">
				<input type="checkbox" id="debcf7_enable" name="debcf7_enable" '. checked( $enabled, 'on', false ) .'>
				'. __( "Block Disposable/Temporary Emails", "disposable-email-blocker-contact-form-7" ) .'
			</label>
		</p>';
}

// save checkbox for plugin feature
add_action( 'wpcf7_save_contact_form', 'debcf7_disposable_email_checkup_switch_save', 10, 3 );

function debcf7_disposable_email_checkup_switch_save( $contact_form, $args, $action )
{	
	if ( 'save' == $action && isset( $args['post_ID'] ) )
	{
		$debcf7_enable = sanitize_text_field( $args['debcf7_enable'] );
	
		$post_id = sanitize_text_field( $args['post_ID'] );

		if ( current_user_can( 'wpcf7_edit_contact_form', $post_id ) )
		{	
			if ( isset( $_POST['debcf7_enable'] ) && $debcf7_enable == 'on' )
			{	
				update_post_meta( $post_id, 'debcf7_enabled', $debcf7_enable );
			}
			else
			{
				update_post_meta( $post_id, 'debcf7_enabled', $debcf7_enable );
			}
		}
	}
}
