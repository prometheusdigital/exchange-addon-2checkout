<?php
/**
 * Exchange Transaction Add-ons require several hooks in order to work properly.
 * Most of these hooks are called in api/transactions.php and are named dynamically
 * so that individual add-ons can target them. eg: it_exchange_refund_url_for_2checkout
 * We've placed them all in one file to help add-on devs identify them more easily
*/

/**
 * 2Checkout URL to perform refunds
 *
 * The it_exchange_refund_url_for_[addon-slug] filter is
 * used to generate the link for the 'Refund Transaction' button
 * found in the admin under Customer Payments
 *
 * @since 1.0.0
 *
 * @param string $url passed by WP filter.
 * @param string $url transaction URL
*/
function it_exchange_refund_url_for_2checkout( $url ) {
	return 'https://www.2checkout.com/va/sales/';
}
add_filter( 'it_exchange_refund_url_for_2checkout', 'it_exchange_refund_url_for_2checkout' );

/**
 * Process the faux PayPal Standard form
 *
 * @since 0.4.19
 *
 * @param array $options
 * @return string HTML button
*/
function it_exchange_process_2checkout_form() {

	if ( isset( $_REQUEST[ '2checkout_purchase' ] ) && !empty( $_REQUEST[ '2checkout_purchase' ] ) ) {
		$it_exchange_customer = it_exchange_get_current_customer();
		$transaction_object = it_exchange_generate_transaction_object();

		$transaction_object->ID = it_exchange_add_transaction( '2checkout', 0, 'pending', $it_exchange_customer->id, $transaction_object );

		echo it_exchange_2checkout_addon_direct_checkout( $it_exchange_customer, $transaction_object );

		die();
	}

}
add_action( 'wp', 'it_exchange_process_2checkout_form' );

/**
 * This proccesses a 2Checkout transaction.
 *
 * The it_exchange_do_transaction_[addon-slug] action is called when
 * the site visitor clicks a specific add-ons 'purchase' button. It is
 * passed the default status of false along with the transaction object
 * The transaction object is a package of data describing what was in the user's cart
 *
 * Exchange expects your add-on to either return false if the transaction failed or to
 * call it_exchange_add_transaction() and return the transaction ID
 *
 * @since 1.0.0
 *
 * @param string $status passed by WP filter.
 * @param object $transaction_object The transaction object
*/
function it_exchange_2checkout_addon_process_transaction( $status, $transaction_object ) {

	// If this has been modified as true already, return.
	if ( $status ) {
		return $status;
	}

	$payment_id = $invoice_id = 0;

	if ( isset( $_REQUEST[ 'it-exchange-transaction-method' ] ) && '2checkout' === $_REQUEST[ 'it-exchange-transaction-method' ] ) {
		$it_exchange_customer = it_exchange_get_current_customer();

		if ( isset( $_REQUEST[ 'it-exchange-transaction-return' ] ) && 'complete' === $_REQUEST[ 'it-exchange-transaction-return' ] ) {
			try {
				// Check for return to thank you page or 2Checkout INS-response
				if ( isset( $_REQUEST[ 'credit_card_processed' ] ) || isset( $_REQUEST[ 'message_type' ] ) ) {
					$settings = it_exchange_get_option( 'addon_2checkout' );

					$twocheckout_sid = $settings[ '2checkout_sid' ];
					$twocheckout_secret = $settings[ '2checkout_secret' ];

					// 2Checkout Receipt Return URL
					if ( isset( $_REQUEST[ 'merchant_order_id' ] ) ) {
						$payment_id = $_REQUEST[ 'merchant_order_id' ];
						$invoice_id = $_REQUEST[ 'order_number' ];
						$total = $_REQUEST[ 'total' ];

						$twocheckout_md5 = strtoupper( $_REQUEST[ 'key' ] );

						if ( $settings[ '2checkout_sandbox_mode' ] ) {
							$check_key = $twocheckout_secret . $twocheckout_sid . '1' . $total;
						}
						else {
							$check_key = $twocheckout_secret . $twocheckout_sid . $invoice_id . $total;
						}

						$check_key = strtoupper( md5( $check_key ) );
					}
					// 2Checkout IPN Notifications
					elseif ( isset( $_REQUEST[ 'vendor_order_id' ] ) ) {
						$payment_id = $_REQUEST[ 'sale_id' ];
						$invoice_id = $_REQUEST[ 'invoice_id' ];

						// $vendor_order_id = $_REQUEST[ 'vendor_order_id' ];
						// $vendor_id = $_REQUEST[ 'vendor_id' ];

						$twocheckout_md5 = strtoupper( $_REQUEST[ 'md5_hash' ] );

						$check_key = $payment_id . $twocheckout_sid . $invoice_id . $twocheckout_secret;
						$check_key = strtoupper( md5( $check_key ) );
					}
					else {
						throw new Exception( __( 'Invalid request', 'LION' ) );
					}

					if ( $check_key != $twocheckout_md5 ) {
						throw new Exception( __( 'Invalid request', 'LION' ) );
					}
				}
			}
			catch ( Exception $e ) {
				it_exchange_flag_purchase_dialog_error( '2checkout' );
				it_exchange_add_message( 'error', $e->getMessage() );

				return false;
			}

			if ( !empty( $payment_id ) ) {
				$args = array(
					'ID' => $payment_id,
					'_it_exchange_transaction_status' => 'paid',
				);

				return it_exchange_update_transaction( $args );
			}
			else {
				return it_exchange_add_transaction( '2checkout', $invoice_id, 'paid', $it_exchange_customer->id, $transaction_object );
			}
		}
	}

	return false;

}
add_filter( 'it_exchange_do_transaction_2checkout', 'it_exchange_2checkout_addon_process_transaction', 10, 2 );

function it_exchange_2checkout_get_transaction_confirmation_url( $url, $transaction_id ) {
	it_exchange_recurring_payments_addon_update_transaction_subscription_id( it_exchange_get_transaction( get_post( $transaction_id ) ), get_post_meta( $transaction_id, '_it_exchange_transaction_method_id', true ) );

	return $url;
}

/**
 * Returns the button for making the payment
 *
 * Exchange will loop through activated Payment Methods on the checkout page
 * and ask each transaction method to return a button using the following filter:
 * - it_exchange_get_[addon-slug]_make_payment_button
 * Transaction Method add-ons must return a button hooked to this filter if they
 * want people to be able to make purchases.
 *
 * @since 1.0.0
 *
 * @param string $button
 * @param array $options
 * @return string HTML button
*/
function it_exchange_2checkout_addon_make_payment_button( $button, $options ) {

    if ( 0 >= it_exchange_get_cart_total( false ) )
        return $button;

	$settings = it_exchange_get_option( 'addon_2checkout' );

	$it_exchange_customer = it_exchange_get_current_customer();

	$button = '<form action="' . get_site_url() . '/?2checkout-purchase-form=1" method="post">';
	$button .= '<input type="submit" class="it-exchange-2checkout-button" name="2checkout_purchase" value="' . $settings[ '2checkout_purchase_button_label' ] .'" />';
	$button .= '</form>';

	return $button;

}
add_filter( 'it_exchange_get_2checkout_make_payment_button', 'it_exchange_2checkout_addon_make_payment_button', 10, 2 );

/**
 * Gets the interpretted transaction status from valid 2Checkout transaction statuses
 *
 * Most gateway transaction stati are going to be lowercase, one word strings.
 * Hooking a function to the it_exchange_transaction_status_label_[addon-slug] filter
 * will allow add-ons to return the human readable label for a given transaction status.
 *
 * @since 1.0.0
 *
 * @param string $status the string of the 2Checkout transaction
 * @return string translaction transaction status
*/
function it_exchange_2checkout_addon_transaction_status_label( $status ) {
    switch ( $status ) {
        case 'succeeded':
		case 'paid':
            return __( 'Paid', 'LION' );
		case 'pending':
            return __( 'Pending', 'LION' );
        case 'refunded':
            return __( 'Refunded', 'LION' );
        case 'partial-refund':
            return __( 'Partially Refunded', 'LION' );
        case 'needs_response':
            return __( 'Disputed: 2Checkout needs a response', 'LION' );
        case 'under_review':
            return __( 'Disputed: Under review', 'LION' );
        case 'won':
            return __( 'Disputed: Won, Paid', 'LION' );
        default:
            return __( 'Unknown', 'LION' );
    }
}
add_filter( 'it_exchange_transaction_status_label_2checkout', 'it_exchange_2checkout_addon_transaction_status_label' );

/**
 * Returns a boolean. Is this transaction a status that warrants delivery of any products attached to it?
 *
 * Just because a transaction gets added to the DB doesn't mean that the admin is ready to give over
 * the goods yet. Each payment gateway will have different transaction stati. Exchange uses the following
 * filter to ask transaction-methods if a current status is cleared for delivery. Return true if the status
 * means its okay to give the download link out, ship the product, etc. Return false if we need to wait.
 * - it_exchange_[addon-slug]_transaction_is_cleared_for_delivery
 *
 * @since 1.0.0
 *
 * @param boolean $cleared passed in through WP filter. Ignored here.
 * @param object $transaction
 * @return boolean
*/
function it_exchange_2checkout_transaction_is_cleared_for_delivery( $cleared, $transaction ) {
    $valid_stati = array( 'succeeded', 'partial-refund', 'won' );

    return in_array( it_exchange_get_transaction_status( $transaction ), $valid_stati );
}
add_filter( 'it_exchange_2checkout_transaction_is_cleared_for_delivery', 'it_exchange_2checkout_transaction_is_cleared_for_delivery', 10, 2 );




/**
 * Returns the Unsubscribe button for 2Checkout
 *
 * @since 1.1.0
 *
 * @param string $output 2Checkout output (should be empty)
 * @param array $options Recurring Payments options
 * @param object $transaction Transaction object
 * @return string
*/
function it_exchange_2checkout_unsubscribe_action( $output, $options, $transaction ) {
	$twocheckout_profile_id = it_exchange_get_recurring_payments_addon_transaction_subscription_id( $transaction );

	// Temporary workaround
	$twocheckout_profile_id = 1;

	if ( !empty( $twocheckout_profile_id ) ) {
		$output  = '<a class="button" href="https://www.2checkout.com/va/sales/" target="_blank">';
		$output .= $options['label'];
		$output .= '</a>';
	}

	return $output;
}
add_filter( 'it_exchange_2checkout_unsubscribe_action', 'it_exchange_2checkout_unsubscribe_action', 10, 3 );

/**
 * Performs user requested unsubscribe
 *
 * @since 1.3.0
 *
 * @return void
*/
function it_exchange_2checkout_unsubscribe_action_submit() {
	if ( isset( $_GET[ 'it-exchange-2checkout-nonce' ] )
		 && isset( $_GET[ 'it-exchange-2checkout-action' ] )
		 && wp_verify_nonce( $_GET[ 'it-exchange-2checkout-nonce' ], '2checkout-' . $_GET[ 'it-exchange-2checkout-action' ] )
		 && isset( $_GET[ 'it-exchange-2checkout-profile-id' ] ) ) {

		$settings = it_exchange_get_option( 'addon_2checkout' );
		$twocheckout_profile_id = $_GET[ 'it-exchange-2checkout-profile-id' ];
		$transaction = it_exchange_get_transaction( get_post( $_GET[ 'it-exchange-2checkout-transaction-id' ] ) );

		if ( 'unsubscribe-user' == $_GET[ 'it-exchange-2checkout-action' ] && !( is_admin() && current_user_can( 'administrator' ) ) ) {
			return;
		}

		try {
			switch( $_GET[ 'it-exchange-2checkout-action' ] ) {

				case 'unsubscribe':
					it_exchange_2checkout_addon_update_profile_status( $twocheckout_profile_id, 'Cancel', 'Admin cancelled' );

					$transaction->update_transaction_meta( 'subscriber_status', 'cancelled' );
					$transaction->update_transaction_meta( 'subscriber_status_user', get_current_user_id() );
					$transaction->update_transaction_meta( 'subscriber_status_date', date_i18n( 'm/d/Y' ) );

					break;

				case 'unsubscribe-user':
					it_exchange_2checkout_addon_update_profile_status( $twocheckout_profile_id, 'Cancel', 'User cancelled' );

					$transaction->update_transaction_meta( 'subscriber_status', 'cancelled' );
					$transaction->update_transaction_meta( 'subscriber_status_user', get_current_user_id() );
					$transaction->update_transaction_meta( 'subscriber_status_date', date_i18n( 'm/d/Y' ) );

					break;

			}

			it_exchange_recurring_payments_addon_update_transaction_subscription_id( $transaction, '' );
		}
		catch( Exception $e ) {
			it_exchange_add_message( 'error', $e->getMessage() );
		}

	}
}
add_action( 'init', 'it_exchange_2checkout_unsubscribe_action_submit' );


/**
 * Output the Cancel URL for the Payments screen
 *
 * @since 1.3.1
 *
 * @param object $transaction iThemes Transaction object
 * @return void
*/
function it_exchange_2checkout_after_payment_details_cancel_url( $transaction = null ) {
	if ( empty( $transaction ) ) {
		$transaction = it_exchange_get_transaction( $GLOBALS[ 'post' ] );
	}

	$cart_object = get_post_meta( $transaction->ID, '_it_exchange_cart_object', true );
	foreach ( $cart_object->products as $product ) {
		$autorenews = $transaction->get_transaction_meta( 'subscription_autorenew_' . $product['product_id'], true );

		if ( $autorenews ) {
			$twocheckout_profile_id = it_exchange_get_recurring_payments_addon_transaction_subscription_id( $transaction );

			$status = $transaction->get_transaction_meta( 'subscriber_status', true );
			$status_user = $transaction->get_transaction_meta( 'subscriber_status_user', true );
			$status_date = $transaction->get_transaction_meta( 'subscriber_status_date', true );

			// Temporary workaround
			$twocheckout_profile_id = 1;
			$status = 'active';

			switch( $status ) {

				case 'deactivated':
					$output = __( 'Recurring payment has been deactivated', 'LION' );
					break;

				case 'cancelled':
					$by = '';

					if ( !empty( $status_user ) ) {
						$status_user = get_userdata( $status_user );

						if ( !empty( $status_user ) ) {
							$by = sprintf( __( 'by %s', 'LION' ), $status_user->user_login );
						}
					}

					if ( !empty( $status_date ) ) {
						$output = sprintf( __( 'Recurring payment was cancelled %s on %s', 'LION' ), $by, $status_date );
					}
					else {
						$output = sprintf( __( 'Recurring payment has been cancelled %s', 'LION' ), $by );
					}
					break;

				case 'active':
				default:
					if ( empty( $twocheckout_profile_id ) ) {
						$output = __( 'Recurring Profile not found', 'LION' );
					}
					else {
						$output  = '<a href="https://www.2checkout.com/va/sales/" target="_blank">' . __( 'Cancel Recurring Payment', 'LION' ) . '</a>';
					}
					break;
			}
			?>
			<div class="transaction-autorenews clearfix spacing-wrapper">
				<div class="recurring-payment-cancel-options left">
					<div class="recurring-payment-status-name"><?php echo $output; ?></div>
				</div>
			</div>
			<?php
			continue;
		}
	}
}
add_action( 'it_exchange_after_payment_details_cancel_url_for_2checkout', 'it_exchange_2checkout_after_payment_details_cancel_url' );