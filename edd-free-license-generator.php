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
			$download_url =	eddflg_build_download_url( $payment_id );
		}
	}
	?>

	<?php if ( $success ) { ?>
		<h3><?php _e( 'Get Started', 'textdomain' ); ?></h3>
		<p>Download the <a href="<?php echo $download_url; ?>">Instant Content</a> plugin.</p>
		<p>Enter your license key: <?php echo $license; ?></p>
	<?php } else { ?>

	<p>To download the plugin, please agree to the terms and sign up with you e-mail address.</p>

	<p>After registering, you will also get a license key for your account, which will be used to enable to plugin and your content library.</p>

	<form action="" id="generate-license-form" method="POST">

		<fieldset style="margin-bottom:20px">
			<label for="email"><?php _e( 'E-mail Address', 'textdomain' ) ?></label>
			<input type="email" name="email" id="email" value="<?php if ( isset( $_POST['email'] ) ) echo $_POST['email']; ?>" class="required" />
			<?php if ( $emailError ) { ?>
			<p class="error"><?php echo $emailError; ?></p>
			<?php } ?>
		</fieldset>

		<fieldset style="margin-bottom:20px">
			<textarea readonly style="width:90%; height:200px; padding:10px; line-height:1.2;">By clicking "Agree" or similar button and placing an order through this site, you expressly agree that we are authorized to charge to the payment method you provide at that time (or to a different payment method you designate that we accept) the applicable fee at the then-current rate displayed on the site plus any applicable taxes, fees, and any other charges you may incur in connection with your use of the site or your order. Pricing details and procedures for payment and delivery are displayed on the site, and are subject to change without notice. We reserve all rights in and to the content made available on the site, until your payment for the applicable content has been processed and accepted. You may not copy, store, modify, download, distribute or create derivative works of the site or any content available on the site except with respect to the applicable content for which you have paid. All transactions are final. You agree not to do, or attempt to do, any of the following: (a) access or use the site in any way that violates or is not in full compliance with any applicable local, state, national or international law, regulation, or statute (including export laws), contracts, intellectual property rights or constitutes the commission of a tort, or for any purpose that is harmful or unintended (by us); (b) access, tamper with, or use services or areas of the site that you are not authorized to access; (c) use any robot, spider, scraper or other automated means or interface not provided by us to access the site; or extract content or data from the site without our authorization; (d) reverse engineer any aspect of the site or content, or bypass or circumvent measures employed to prevent or limit access to any area, content or code of the site; (e) send to or otherwise impact us or the site (or anything or anyone else) with harmful, illegal, deceptive or disruptive code such as a virus, “spyware,” “adware” or other code that could adversely impact the site or any user; or (f) take any action which might impose a significant burden on the site’s infrastructure or computer systems, or otherwise interfere with the ordinary operation of the site. WE PROVIDE THE SITE AND CONTENT ON AN "AS IS" AND "AS AVAILABLE" BASIS. WE MAKE NO WARRANTIES, EXPRESS OR IMPLIED, INCLUDING WITHOUT LIMITATION, ANY IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, AND NON-INFRINGEMENT. WE DO NOT REPRESENT OR WARRANT THAT THE SITE, CONTENT, INFORMATION, OR MATERIALS AVAILABLE ON OR THROUGH THE SITE WILL: (I) BE UNINTERRUPTED, (II) BE FREE OF DEFECTS, VIRUSES, INACCURACIES, ERRORS, OR OTHER HARMFUL COMPONENTS, (III) MEET YOUR REQUIREMENTS OR SATISFACTION, (IV) BE SECURE FROM HACKERS OR OTHER UNAUTHORIZED PERSONS; OR (V) OPERATE IN THE CONFIGURATION OR WITH OTHER HARDWARE OR SOFTWARE THAT YOU USE. IF APPLICABLE LAW DOES NOT ALLOW THE EXCLUSION OF SOME OR ALL OF THE ABOVE WARRANTIES TO APPLY TO YOU, THE ABOVE EXCLUSIONS WILL APPLY TO YOU TO THE EXTENT PERMITTED BY APPLICABLE LAW. YOU USE THE SITE AND CONTENT AT YOUR OWN RISK. WE ASSUME NO RESPONSIBILITY FOR AND MAKE NO WARRANTY OR REPRESENTATION AS TO: (i) THE ACCURACY, CURRENCY, COMPLETENESS, RELIABILITY, OR USEFULNESS OF THE SITE OR CONTENT (INCLUDING DESCRIPTIONS THEREOF); OR (ii) ANY RESULTS THAT MAY BE OBTAINED FROM USE OF THE SITE OR CONTENT. TO THE MAXIMUM EXTENT PERMITTED BY LAW, YOU AGREE THAT WE WILL NOT BE LIABLE FOR ANY INDIRECT, INCIDENTAL, PUNITIVE, EXEMPLARY, SPECIAL OR CONSEQUENTIAL DAMAGES, LOST PROFITS, LOST REVENUE, LOSS OF DATA, LOSS OF PRIVACY, LOSS OF GOODWILL OR ANY OTHER LOSSES, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGES AND EVEN IN THE EVENT OF FAULT, TORT (INCLUDING NEGLIGENCE) OR STRICT OR PRODUCT LIABILITY. WITHOUT LIMITING THE FOREGOING, IN NO EVENT WILL OUR AGGREGATE LIABILITY EXCEED, IN TOTAL, THE AMOUNTS PAID BY YOU TO US WITH RESPECT TO THE TRANSACTION THAT GIVES RISE TO THE CLAIM.</textarea>
		</fieldset>

		<fieldset style="margin-bottom:20px">
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