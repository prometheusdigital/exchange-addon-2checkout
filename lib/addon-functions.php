<?php
/**
 * The following file contains utility functions specific to our 2checkout add-on
 * If you're building your own transaction-method addon, it's likely that you will
 * need to do similar things. This includes enqueueing scripts, formatting data for 2checkout, etc.
*/

/**
 * Adds actions to the plugins page for the iThemes Exchange 2Checkout plugin
 *
 * @since 1.0.0
 *
 * @param array $meta Existing meta
 * @param string $plugin_file the wp plugin slug (path)
 * @param array $plugin_data the data WP harvested from the plugin header
 * @param string $context
 * @return array
*/
function it_exchange_2checkout_plugin_row_actions( $actions, $plugin_file, $plugin_data, $context ) {

    $actions['setup_addon'] = '<a href="' . get_admin_url( NULL, 'admin.php?page=it-exchange-addons&add-on-settings=2checkout' ) . '">' . __( 'Setup Add-on', 'it-l10n-exchange-addon-2checkout' ) . '</a>';

    return $actions;

}
add_filter( 'plugin_action_links_exchange-addon-2checkout/exchange-addon-2checkout.php', 'it_exchange_2checkout_plugin_row_actions', 10, 4 );

/**
 * Enqueues any scripts we need on the frontend during a 2checkout checkout
 *
 * @since 1.0.0
 *
 * @return void
*/
function it_exchange_2checkout_addon_enqueue_script() {
    wp_enqueue_script( '2checkout-addon-js', ITUtility::get_url_from_file( dirname( __FILE__ ) ) . '/js/2checkout-addon.js', array( 'jquery' ) );
    wp_localize_script( '2checkout-addon-js', '2checkoutAddonL10n', array(
            'processing_payment_text' => __( 'Processing payment, please wait...', 'it-l10n-exchange-addon-2checkout' ),
        )
    );
}
add_action( 'wp_enqueue_scripts', 'it_exchange_2checkout_addon_enqueue_script' );

/**
 * Grab the 2checkout customer ID for a WP user
 *
 * @since 1.0.0
 *
 * @param integer $customer_id the WP customer ID
 * @return integer
*/
function it_exchange_2checkout_addon_get_2checkout_customer_id( $customer_id ) {
    $settings = it_exchange_get_option( 'addon_2checkout' );
    $mode     = ( $settings['2checkout-test-mode'] ) ? '_test_mode' : '_live_mode';

    return get_user_meta( $customer_id, '_it_exchange_2checkout_id' . $mode, true );
}

/**
 * Add the 2checkout customer ID as user meta on a WP user
 *
 * @since 1.0.0
 *
 * @param integer $customer_id the WP user ID
 * @param integer $twocheckout_id the 2checkout customer ID
 * @return boolean
*/
function it_exchange_2checkout_addon_set_2checkout_customer_id( $customer_id, $twocheckout_id ) {
    $settings = it_exchange_get_option( 'addon_2checkout' );
    $mode     = ( $settings['2checkout-test-mode'] ) ? '_test_mode' : '_live_mode';

    return update_user_meta( $customer_id, '_it_exchange_2checkout_id' . $mode, $twocheckout_id );
}

/**
 * Grab a transaction from the 2checkout transaction ID
 *
 * @since 1.0.0
 *
 * @param integer $twocheckout_id id of 2checkout transaction
 * @return transaction object
*/
function it_exchange_2checkout_addon_get_transaction_id( $twocheckout_id ) {
    $args = array(
        'meta_key'    => '_it_exchange_transaction_method_id',
        'meta_value'  => $twocheckout_id,
        'numberposts' => 1, //we should only have one, so limit to 1
    );
    return it_exchange_get_transactions( $args );
}

/**
 * Updates a 2checkout transaction status based on 2checkout ID
 *
 * @since 1.0.0
 *
 * @param integer $twocheckout_id id of 2checkout transaction
 * @param string $new_status new status
 * @return void
*/
function it_exchange_2checkout_addon_update_transaction_status( $twocheckout_id, $new_status ) {
    $transactions = it_exchange_2checkout_addon_get_transaction_id( $twocheckout_id );
    foreach( $transactions as $transaction ) { //really only one
        $current_status = it_exchange_get_transaction_status( $transaction );
        if ( $new_status !== $current_status )
            it_exchange_update_transaction_status( $transaction, $new_status );
    }
}

/**
 * Adds a refund to post_meta for a 2checkout transaction
 *
 * @since 1.0.0
*/
function it_exchange_2checkout_addon_add_refund_to_transaction( $twocheckout_id, $refund ) {

    // 2Checkout money format comes in as cents. Divide by 100.
    $refund = ( $refund / 100 );

    // Grab transaction
    $transactions = it_exchange_2checkout_addon_get_transaction_id( $twocheckout_id );
    foreach( $transactions as $transaction ) { //really only one

        $refunds = it_exchange_get_transaction_refunds( $transaction );

        $refunded_amount = 0;
        foreach( ( array) $refunds as $refund_meta ) {
            $refunded_amount += $refund_meta['amount'];
        }

        // In 2Checkout the Refund is the total amount that has been refunded, not just this transaction
        $this_refund = $refund - $refunded_amount;

        // This refund is already formated on the way in. Don't reformat.
        it_exchange_add_refund_to_transaction( $transaction, $this_refund );
    }
}

/**
 * Removes a 2checkout Customer ID from a WP user
 *
 * @since 1.0.0
 *
 * @param integer $twocheckout_id the id of the 2checkout transaction
*/
function it_exchange_2checkout_addon_delete_2checkout_id_from_customer( $twocheckout_id ) {
    $settings = it_exchange_get_option( 'addon_2checkout' );
    $mode     = ( $settings['2checkout-test-mode'] ) ? '_test_mode' : '_live_mode';

    $transactions = it_exchange_2checkout_addon_get_transaction_id( $twocheckout_id );
    foreach( $transactions as $transaction ) { //really only one
        $customer_id = get_post_meta( $transaction->ID, '_it_exchange_customer_id', true );
        if ( false !== $current_2checkout_id = it_exchange_2checkout_addon_get_2checkout_customer_id( $customer_id ) ) {

            if ( $current_2checkout_id === $twocheckout_id )
                delete_user_meta( $customer_id, '_it_exchange_2checkout_id' . $mode );

        }
    }
}
