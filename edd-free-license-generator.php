<?php
/*
Plugin Name: Easy Digital Downloads - Free License Generator
Plugin URI: http://wptheming.com
Description: Allows users to bypass checkout page when downloading a specific product.  Should be used in conjuction with the EDD Software Licensing Plugin.
Version: 0.2
Author: Devin Price
Author URI:  http://wptheming.com
Contributors: downstairsdev
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( class_exists( 'Easy_Digital_Downloads' ) ) :

/**
 * Creates license shortcode
 *
 * Example: [edd_license_from id="100" receipt="0"]
 *
 * "id" param is the download id for the product
 * "receipt" param is for e-mail receipts
 *
 */
add_shortcode( 'edd_license_form', 'eddflg_generate_license_form' );

/**
 * Creates the license form
 *
 * @since 0.1
 */
function eddflg_generate_license_form( $atts ) {

	extract( shortcode_atts(
		array(
			'id' => 0,
			'receipt' => 1
		),
	$atts ) );

	$emailError = false;
	$hasError = false;
	$success = false;
	$output = '';

	if ( isset($_POST['license_nonce_field'] ) && wp_verify_nonce( $_POST['license_nonce_field'], 'license_nonce') ) {

		$data = array();

		$data['download_id'] = $id;
		$data['receipt'] = $receipt;

		if ( is_email( $_POST['email'] ) ) {
			$data['email'] = sanitize_email( $_POST['email'] );
		} else {
			$emailError = __( 'Please enter a valid e-mail address.', 'textdomain' );
			$hasError = true;
		}

		if ( !$hasError ) {
			$payment_id = eddflg_manual_create_payment( $data );
			$license = eddflg_get_licence_key( $payment_id );
			$download_url =	eddflg_build_download_url( $payment_id );
			$success = true;
		}
	}
	?>

	<?php if ( $success ) {
		$output .= '<div class="submission-success clearfix">';
		$output .= '<h3>Congratulations!  You\'re registered.</h3>';
		$output .= '<p class="license">Your license key is: ' . $license . '</p>';
		$output .= '<a href="' . $download_url . '" class="download button">' . __( 'Download the Plugin', 'textdomain' ) . '</a>';
		$output .= '</div>';
		$output .= '<div class="license-terms">';
		$output .= '<p>Copyright Demand Media, Inc. 2013.  The Instant Content Plug-In is free software, licensed and distributed under the <a href="http://www.gnu.org/licenses/gpl-2.0.txt">GNU General Public License v 2.0</a> or later version.</p>';
		$output .= '<p>This program is distributed WITHOUT WARRANTY; without even the implied warranty of MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE or NON-INFRINGMENT.  See the GNU General Public License for more details.</p>';
		$output .= '</div>';
	} else {
		$output .= '<h3>The fastest way to get quality content for your WordPress site.</h3>';
		$output .= '<form action="" id="generate-license-form" class="clearfix" method="POST">';
		$emailValue = '';
		if ( isset( $_POST['email'] ) ) {
			$emailValue = $_POST['email'];
		}
		$output .= '<p>Register with your e-mail address:</p>';
		$output .= '<input id="email" type="email" name="email" value="' . $emailValue . '" placeholder="Your e-mail address" class="required">';
		if ( $emailError ) {
			$output .= '<p class="error">' . $emailError . '</p>';
		}
		$output .= '<input type="submit" value="' . __( 'Download Plugin', 'textdomain' ) . '" class="button">';
		$output .= wp_nonce_field( 'license_nonce', 'license_nonce_field' );
		$output .= '</form>';
	}

	return $output;

}

/**
 * Creates the "payment" in EDD
 *
 * @since 0.1
 */
function eddflg_manual_create_payment( $data ) {

	global $edd_options;

	// These is the product id that EDD assigned
	$data['downloads'][0] = array(
		'id' => $data['download_id']
	);

	// We've already sanitized the e-mail on the form
	$email = $data['email'];

	$user_info = array(
		'id' 			=> 0,
		'email' 		=> $email,
		'first_name'	=> '',
		'last_name'		=> '',
		'discount'		=> 'none'
	);

	$cart_details = array();

	foreach( $data['downloads'] as $key => $download ) {

		// Manually set item price
		$item_price = '0.00';

		$cart_details[$key] = array(
			'name' 			=> get_the_title( $download['id'] ),
			'id' 			=> $download['id'],
			'item_number' 	=> $download,
			'price' 		=> $item_price,
			'quantity' 		=> 1,
		);

	}

	// Manually set the total to 0.00
	$total = '0.00';

	$purchase_data 		= array(
		'price' 		=> number_format( (float) $total, 2 ),
		'date' 			=> date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
		'purchase_key'	=> strtolower( md5( uniqid() ) ), // random key
		'user_email'	=> $email,
		'user_info' 	=> $user_info,
		'currency'		=> 'USD',
		'downloads'		=> $data['downloads'],
		'cart_details' 	=> $cart_details,
		'status'		=> 'pending' // start with pending so we can call the update function, which logs all stats
	);

	if ( $data['receipt'] != '1' ) {
		// remove_action( 'edd_complete_purchase', 'edd_trigger_purchase_receipt', 999 );
	}

	$payment_id = edd_insert_payment( $purchase_data );

	// Increase stats and log earnings
	edd_update_payment_status( $payment_id, 'complete' ) ;

	// Send email with secure download link
	// edd_email_purchase_receipt( $payment_id, true );

	return $payment_id;
}

/**
 * Returns the license associated with the payment ID
 *
 * @since 0.1
 */
function eddflg_get_licence_key( $payment_id ) {
	$licensing = edd_software_licensing();
	$license_key = $licensing->get_licenses_of_purchase( $payment_id );
	if ( $license_key ) {
		$license_key = get_post_meta( $license_key[0]->ID, '_edd_sl_key', true );
	}
	return $license_key;
}

/**
 * Returns the link to the file available for download
 *
 * @since 0.1
 */
function eddflg_build_download_url( $payment_id ) {

	$downloads = edd_get_payment_meta_cart_details( $payment_id, true );
	$purchase_data 	= edd_get_payment_meta( $payment_id );

	if ( $downloads ) {
		$price_id = edd_get_cart_item_price_id( $downloads[0] );
		$download_files = edd_get_download_files( $downloads[0]['id'], $price_id );
	}

	foreach ( $download_files as $filekey => $file ) {
		$download_url = edd_get_download_file_url( $purchase_data['key'], $purchase_data['email'], $filekey, $downloads[0]['id'], $price_id );
	}

	return $download_url;
}

endif;