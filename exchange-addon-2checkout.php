<?php
/*
 * Plugin Name: iThemes Exchange - 2Checkout Add-on
 * Version: 1.1.0
 * Description: Adds the ability for users to checkout with 2Checkout.
 * Plugin URI: http://ithemes.com/exchange/2checkout/
 * Author: WebDevStudios
 * Author URI: http://webdevstudios.com
 * iThemes Package: exchange-addon-2checkout

 * Installation:
 * 1. Download and unzip the latest release zip file.
 * 2. If you use the WordPress plugin uploader to install this plugin skip to step 4.
 * 3. Upload the entire plugin directory to your `/wp-content/plugins/` directory.
 * 4. Activate the plugin through the 'Plugins' menu in WordPress Administration.
 *
*/

/**
 * This registers our plugin as a 2checkout addon
 *
 * @since 1.0.0
 */
function it_exchange_register_2checkout_addon() {

	$options = array(
		'name' => __( '2Checkout', 'LION' ),
		'description' => __( 'Process transactions via 2Checkout.', 'LION' ),
		'author' => 'WebDevStudios',
		'author_url' => 'http://webdevstudios.com',
		'icon' => ITUtility::get_url_from_file( dirname( __FILE__ ) . '/lib/images/2checkout50px.png' ),
		'wizard-icon' => ITUtility::get_url_from_file( dirname( __FILE__ ) . '/lib/images/wizard-2checkout.png' ),
		'file' => dirname( __FILE__ ) . '/init.php',
		'category' => 'transaction-methods',
		'settings-callback' => 'it_exchange_2checkout_addon_settings_callback',
	);

	it_exchange_register_addon( '2checkout', $options );

}
add_action( 'it_exchange_register_addons', 'it_exchange_register_2checkout_addon' );

/**
 * Require other add-ons that may be needed
 *
 * @since 1.0.0
 */
function it_exchange_2checkout_required_addons() {

	add_filter( 'it_exchange_billing_address_purchase_requirement_enabled', '__return_true' );

}
add_action( 'it_exchange_enabled_addons_loaded', 'it_exchange_2checkout_required_addons' );

/**
 * Loads the translation data for WordPress
 *
 * @since 1.0.0
 */
function it_exchange_2checkout_set_textdomain() {

	load_plugin_textdomain( 'LION', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );

}
add_action( 'plugins_loaded', 'it_exchange_2checkout_set_textdomain' );

/**
 * Registers Plugin with iThemes updater class
 *
 * @since 1.0.0
 *
 * @param object $updater ithemes updater object
 */
function ithemes_exchange_addon_2checkout_updater_register( $updater ) {

	$updater->register( 'exchange-addon-2checkout', __FILE__ );

}
add_action( 'ithemes_updater_register', 'ithemes_exchange_addon_2checkout_updater_register' );
require( dirname( __FILE__ ) . '/lib/updater/load.php' );

function ithemes_exchange_2checkout_deactivate() {
	if ( empty( $_GET['remove-gateway'] ) || 'yes' !== $_GET['remove-gateway'] ) {
		$title = __( 'Payment Gateway Warning', 'LION' );
		$yes = '<a href="' . esc_url( add_query_arg( 'remove-gateway', 'yes' ) ) . '">' . __( 'Yes', 'LION' ) . '</a>';
		$no  = '<a href="javascript:history.back()">' . __( 'No', 'LION' ) . '</a>';
		$message = '<p>' . sprintf( __( 'Deactivating a payment gateway can cause customers to lose access to any membership products they have purchased using this payment gateway. Are you sure you want to proceed? %s | %s', 'LION' ), $yes, $no ) . '</p>';
		$args = array(
			'response'  => 200,
			'back_link' => false,
		);
		wp_die( $message, $title, $args );
	}
}
register_deactivation_hook( __FILE__, 'ithemes_exchange_2checkout_deactivate' );