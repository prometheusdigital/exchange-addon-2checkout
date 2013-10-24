<?php
/**
 * Most Payment Gateway APIs use some concept of webhooks or notifications to communicate with
 * clients. While add-ons are not required to use the Exchange API, we have created a couple of functions
 * to register and listen for these webooks. The stripe add-on uses this API and we have placed the
 * registering and processing functions in this file.
*/



/**
 * Adds the 2Checkout webhook to the global array of keys to listen for
 *
 * @since 0.4.0
 *
 * @param array $webhooks existing
 * @return array
*/
function it_exchange_2checkout_addon_register_webhook() {
	$key   = '2checkout';
	$param = apply_filters( 'it_exchange_2checkout_webhook', 'it_exchange_2checkout' );
	it_exchange_register_webhook( $key, $param );
}
add_filter( 'init', 'it_exchange_2checkout_addon_register_webhook' );

/**
 * Processes webhooks for 2Checkout Web Standard
 *
 * @since 0.4.0
 * @todo actually handle the exceptions
 *
 * @param array $request really just passing  $_REQUEST
 */
function it_exchange_2checkout_addon_process_webhook( $request ) {

	$general_settings = it_exchange_get_option( 'settings_general' );
	$settings = it_exchange_get_option( 'addon_2checkout' );

	$subscriber_id = !empty( $request['subscr_id'] ) ? $request['subscr_id'] : false;
	$subscriber_id = !empty( $request['recurring_payment_id'] ) ? $request['recurring_payment_id'] : $subscriber_id;

	if ( !empty( $request['txn_type'] ) ) {

		switch( $request['txn_type'] ) {

			case 'web_accept':
				switch( strtolower( $request['payment_status'] ) ) {

					case 'completed' :
						it_exchange_2checkout_addon_update_transaction_status( $request['txn_id'], $request['payment_status'] );
						break;
					case 'reversed' :
						it_exchange_2checkout_addon_update_transaction_status( $request['parent_txn_id'], $request['reason_code'] );
						break;
				}
				break;

			case 'subscr_payment':
				switch( strtolower( $request['payment_status'] ) ) {
					case 'completed' :
						if ( !it_exchange_2checkout_addon_update_transaction_status( $request['txn_id'], $request['payment_status'] ) ) {
							//If the transaction isn't found, we've got a new payment
							it_exchange_2checkout_addon_add_child_transaction( $request['txn_id'], $request['payment_status'], $subscriber_id, $request['mc_gross'] );
						} else {
							//If it is found, make sure the subscriber ID is attached to it
							it_exchange_2checkout_addon_update_subscriber_id( $request['txn_id'], $subscriber_id );
						}
						it_exchange_2checkout_addon_update_subscriber_status( $subscriber_id, 'active' );
						break;
				}
				break;

			case 'subscr_signup':
				it_exchange_2checkout_addon_update_subscriber_status( $subscriber_id, 'active' );
				break;

			case 'recurring_payment_suspended':
				it_exchange_2checkout_addon_update_subscriber_status( $subscriber_id, 'suspended' );
				break;

			case 'subscr_cancel':
				it_exchange_2checkout_addon_update_subscriber_status( $subscriber_id, 'cancelled' );
				break;

			case 'subscr_eot':
				it_exchange_2checkout_addon_update_subscriber_status( $subscriber_id, 'deactivated' );
				break;

		}

	} else {

		if ( !empty( $request['reason_code'] ) ) {

			switch( $request['reason_code'] ) {

				case 'refund' :
					it_exchange_2checkout_addon_update_transaction_status( $request['parent_txn_id'], $request['payment_status'] );
					it_exchange_2checkout_addon_add_refund_to_transaction( $request['parent_txn_id'], $request['mc_gross'] );
					if ( $subscriber_id )
						it_exchange_2checkout_addon_update_subscriber_status( $subscriber_id, 'refunded' );
					break;

			}

		}

	}

}
add_action( 'it_exchange_webhook_it_exchange_2checkout', 'it_exchange_2checkout_addon_process_webhook' );

/**
 * Grab a transaction from the 2Checkout subscriber ID
 *
 * @since 0.4.0
 *
 * @param integer $twocheckout_id id of 2Checkout transaction
 * @return transaction object
*/
function it_exchange_2checkout_addon_get_transaction_id_by_subscriber_id( $subscriber_id ) {
	$args = array(
		'meta_key'    => '_it_exchange_transaction_subscriber_id',
		'meta_value'  => $subscriber_id,
		'numberposts' => 1, //we should only have one, so limit to 1
	);
	return it_exchange_get_transactions( $args );
}

/**
 * Add a new transaction, really only used for subscription payments.
 * If a subscription pays again, we want to create another transaction in Exchange
 * This transaction needs to be linked to the parent transaction.
 *
 * @since 1.3.0
 *
 * @param integer $twocheckout_id id of 2Checkout transaction
 * @param string $payment_status new status
 * @param string $subscriber_id from 2Checkout (optional)
 * @return bool
*/
function it_exchange_2checkout_addon_add_child_transaction( $twocheckout_id, $payment_status, $subscriber_id=false, $amount ) {
	$transactions = it_exchange_2checkout_addon_get_transaction_id( $twocheckout_id );
	if ( !empty( $transactions ) ) {
		//this transaction DOES exist, don't try to create a new one, just update the status
		it_exchange_2checkout_addon_update_transaction_status( $twocheckout_id, $payment_status );
	} else {

		if ( !empty( $subscriber_id ) ) {

			$transactions = it_exchange_2checkout_addon_get_transaction_id_by_subscriber_id( $subscriber_id );
			foreach( $transactions as $transaction ) { //really only one
				$parent_tx_id = $transaction->ID;
				$customer_id = get_post_meta( $transaction->ID, '_it_exchange_customer_id', true );
			}

		} else {
			$parent_tx_id = false;
			$customer_id = false;
		}

		if ( $parent_tx_id && $customer_id ) {
			$transaction_object = new stdClass;
			$transaction_object->total = $amount;
			it_exchange_add_child_transaction( '2checkout', $twocheckout_id, $payment_status, $customer_id, $parent_tx_id, $transaction_object );
			return true;
		}
	}
	return false;
}

/**
 * Updates a subscription ID to post_meta for a 2Checkout transaction
 *
 * @since 1.3.0
 * @param string $twocheckout_id 2Checkout Transaction ID
 * @param string $subscriber_id 2Checkout Subscriber ID
*/
function it_exchange_2checkout_addon_update_subscriber_id( $twocheckout_id, $subscriber_id ) {
	$transactions = it_exchange_2checkout_addon_get_transaction_id( $twocheckout_id );
	foreach( $transactions as $transaction ) { //really only one
		do_action( 'it_exchange_update_transaction_subscription_id', $transaction, $subscriber_id );
	}
}

/**
 * Updates a subscription status to post_meta for a 2Checkout transaction
 *
 * @since 1.3.0
 * @param string $subscriber_id 2Checkout Subscriber ID
 * @param string $status Status of Subscription
*/
function it_exchange_2checkout_addon_update_subscriber_status( $subscriber_id, $status ) {
	$transactions = it_exchange_2checkout_addon_get_transaction_id_by_subscriber_id( $subscriber_id );
	foreach( $transactions as $transaction ) { //really only one
		// If the subscription has been cancelled/suspended and fully refunded, they need to be deactivated
		if ( !in_array( $status, array( 'active', 'deactivated' ) ) ) {
			if ( $transaction->has_refunds() && 0 === it_exchange_get_transaction_total( $transaction, false ) )
				$status = 'deactivated';

			if ( $transaction->has_children() ) {
				//Get the last child and make sure it hasn't been fully refunded
				$args = array(
					'numberposts' => 1,
					'order'       => 'ASC',
				);
				$last_child_transaction = $transaction->get_children( $args );
				foreach( $last_child_transaction as $last_transaction ) { //really only one
					$last_transaction = it_exchange_get_transaction( $last_transaction );
					if ( $last_transaction->has_refunds() && 0 === it_exchange_get_transaction_total( $last_transaction, false ) )
						$status = 'deactivated';
				}
			}
		}
		do_action( 'it_exchange_update_transaction_subscription_status', $transaction, $subscriber_id, $status );
	}
}