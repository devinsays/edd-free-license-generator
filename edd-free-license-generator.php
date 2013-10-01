<?php
/*
Plugin Name: Easy Digital Downloads - Free License Generator
Plugin URI: http://wptheming.com
Description: Allows users to bypass checkout page when downloading a specific product.  Should be used in conjuction with the EDD Software Licensing Plugin.
Version: 0.1
Author: Devin Price
Author URI:  http://wptheming.com
Contributors: downstairsdev
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

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
	$agreeError = false;
	$hasError = false;
	$success = false;

	if ( isset($_POST['download-plugin']) && isset($_POST['license_nonce_field'] ) && wp_verify_nonce( $_POST['license_nonce_field'], 'license_nonce') ) {

		$data = array();

		$data['download_id'] = $id;
		$data['receipt'] = $receipt;

		if ( is_email( $_POST['email'] ) ) {
			$data['email'] = sanitize_email( $_POST['email'] );
		} else {
			$emailError = __( 'Please enter a valid e-mail address.', 'textdomain' );
			$hasError = true;
		}

		if ( isset( $_POST['agree_to_terms'] ) && $_POST['agree_to_terms'] ) {
			$data['agree_to_terms'] = 1;
		} else {
			$agreeError = __( 'Please agree to the terms to download', 'textdomain' );
			$hasError = true;
		}

		if ( !$hasError ) {
			$payment_id = eddflg_manual_create_payment( $data );
			$success = true;
			$license = eddflg_get_licence_key( $payment_id );
			$link = eddflg_build_download_url( $data['download_id'] );
		}
	}
	?>

	<?php if ( $success ) { ?>
		<h3><?php _e( 'Get Started', 'textdomain' ); ?></h3>
		<p>Download the <a href="<?php echo $link; ?>">Instant Content</a> plugin.</p>
		<p>Enter your license key: <?php echo $license; ?></p>
	<?php } else { ?>

	<p>To download the plugin, please agree to the terms and sign up with you e-mail address.</p>

	<p>After registering, you will also get a license key for your account, which will be used to enable to plugin and your content library.</p>

	<form action="" id="generate-license-form" method="POST">

		<fieldset>
			<label for="email"><?php _e( 'E-mail Address', 'textdomain' ) ?></label>
			<input type="email" name="email" id="email" value="<?php if ( isset( $_POST['email'] ) ) echo $_POST['email']; ?>" class="required" />
			<?php if ( $emailError ) { ?>
			<p class="error"><?php echo $emailError; ?></p>
			<?php } ?>
		</fieldset>

		<fieldset>
			<label for="agree_to_terms"><?php _e( 'Agree to terms', 'textdomain' ) ?></label>
			<input name="agree_to_terms" type="checkbox" id="agree-to-terms" <?php if ( isset( $data['agree_to_terms'] ) ) checked( $data['agree_to_terms'], true, true ); ?>>
			<?php if ( $agreeError ) { ?>
			<p class="error"><?php echo $agreeError; ?></p>
			<?php } ?>
		</fieldset>

		<fieldset>
			<?php wp_nonce_field( 'license_nonce', 'license_nonce_field' ); ?>
			<input type="hidden" name="download-plugin" value="true" />
			<button type="submit"><?php _e( 'Download Plugin', 'textdomain' ) ?></button>
		</fieldset>

	</form>
	<?php }

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
		'currency'		=> $edd_options['currency'],
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
	edd_email_purchase_receipt( $payment_id, true );

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
function eddflg_build_download_url( $download_id ) {

	global $edd_options;
	$files = edd_get_download_files( $download_id );

	$hours = isset( $edd_options['download_link_expiration'] )
			&& is_numeric( $edd_options['download_link_expiration'] )
			? absint($edd_options['download_link_expiration']) : 24;

	if( ! ( $date = strtotime( '+' . $hours . 'hours' ) ) )
		$date = 2147472000; // Highest possible date, January 19, 2038

	$params = array(
		'file' 			=> $files[0],
		'did'    		=> $download_id,
		'vp_edd_act'	=> 'download',
		'expire' 		=> rawurlencode( base64_encode( $date ) )
	);

	$params = apply_filters( 'edd_download_file_url_args', $params );

	$download_url = add_query_arg( $params, home_url() );

	return $download_url;
}