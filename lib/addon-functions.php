<?php
/**
 * The following file contains utility functions specific to our 2Checkout add-on
 * If you're building your own transaction-method addon, it's likely that you will
 * need to do similar things. This includes enqueueing scripts, formatting data for 2Checkout, etc.
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

    $actions['setup_addon'] = '<a href="' . get_admin_url( NULL, 'admin.php?page=it-exchange-addons&add-on-settings=2checkout' ) . '">' . __( 'Setup Add-on', 'LION' ) . '</a>';

    return $actions;

}
add_filter( 'plugin_action_links_exchange-addon-2checkout/exchange-addon-2checkout.php', 'it_exchange_2checkout_plugin_row_actions', 10, 4 );

/**
 * Grab the 2Checkout customer ID for a WP user
 *
 * @since 1.0.0
 *
 * @param integer $customer_id the WP customer ID
 * @return integer
*/
function it_exchange_2checkout_addon_get_customer_id( $customer_id ) {

    $settings = it_exchange_get_option( 'addon_2checkout' );
    $mode     = ( $settings['2checkout_sandbox_mode'] ) ? '_test_mode' : '_live_mode';

    return get_user_meta( $customer_id, '_it_exchange_2checkout_id' . $mode, true );

}

/**
 * Add the 2Checkout customer ID as user meta on a WP user
 *
 * @since 1.0.0
 *
 * @param integer $customer_id the WP user ID
 * @param integer $twocheckout_id the 2Checkout customer ID
 * @return boolean
*/
function it_exchange_2checkout_addon_set_customer_id( $customer_id, $twocheckout_id ) {
    $settings = it_exchange_get_option( 'addon_2checkout' );
    $mode     = ( $settings['2checkout_sandbox_mode'] ) ? '_test_mode' : '_live_mode';

    return update_user_meta( $customer_id, '_it_exchange_2checkout_id' . $mode, $twocheckout_id );
}

/**
 * Add the stripe customer's subscription ID as user meta on a WP user
 *
 * @since 1.0.0
 *
 * @param integer $twocheckout_id id of 2Checkout transaction
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
 * Updates a 2Checkout transaction status based on 2Checkout ID
 *
 * @since 1.0.0
 *
 * @param integer $twocheckout_id id of 2Checkout transaction
 * @param string $new_status new status
 * @return bool
*/
function it_exchange_2checkout_addon_update_transaction_status( $twocheckout_id, $new_status ) {
    $transactions = it_exchange_2checkout_addon_get_transaction_id( $twocheckout_id );
    foreach( $transactions as $transaction ) { //really only one
        $current_status = it_exchange_get_transaction_status( $transaction );

        if ( $new_status !== $current_status )
            it_exchange_update_transaction_status( $transaction, $new_status );

		return true;
    }
	return false;
}

/**
 * Adds a refund to post_meta for a 2Checkout transaction
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
 * Removes a 2Checkout Customer ID from a WP user
 *
 * @since 1.0.0
 *
 * @param integer $twocheckout_id the id of the 2Checkout transaction
*/
function it_exchange_2checkout_addon_delete_id_from_customer( $twocheckout_id ) {
    $settings = it_exchange_get_option( 'addon_2checkout' );
    $mode     = ( $settings['2checkout_sandbox_mode'] ) ? '_test_mode' : '_live_mode';

    $transactions = it_exchange_2checkout_addon_get_transaction_id( $twocheckout_id );
    foreach( $transactions as $transaction ) { //really only one
        $customer_id = get_post_meta( $transaction->ID, '_it_exchange_customer_id', true );
        if ( false !== $current_2checkout_id = it_exchange_2checkout_addon_get_customer_id( $customer_id ) ) {

            if ( $current_2checkout_id === $twocheckout_id )
                delete_user_meta( $customer_id, '_it_exchange_2checkout_id' . $mode );

        }
    }
}

/**
 * @param IT_Exchange_Customer $it_exchange_customer
 * @param object $transaction_object
 * @param array $args
 *
 * @return array
 * @throws Exception
 */
function it_exchange_2checkout_addon_direct_checkout( $it_exchange_customer, $transaction_object, $args = array() ) {

	$settings = it_exchange_get_option( 'addon_2checkout' );

	if ( !isset( $transaction_object->ID ) ) {
		$transaction_object->ID = 0;
	}

	$recurring_products = array();

	if ( 1 === it_exchange_get_cart_products_count() ) {
		$cart = it_exchange_get_cart_products();

		foreach( $cart as $product ) {
			if ( it_exchange_product_supports_feature( $product[ 'product_id' ], 'recurring-payments', array( 'setting' => 'auto-renew' ) ) ) {
				if ( it_exchange_product_has_feature( $product[ 'product_id' ], 'recurring-payments', array( 'setting' => 'auto-renew' ) ) ) {
					$time = it_exchange_get_product_feature( $product[ 'product_id' ], 'recurring-payments', array( 'setting' => 'time' ) );

					switch( $time ) {

						case 'yearly':
							$unit = 'Year';
							break;

						// Doesn't exist yet, but building support for it now
						case 'weekly':
							$unit = 'Week';
							break;

						case 'monthly':
						default:
							$unit = 'Month';
							break;

					}

					$recurring_products[ $product[ 'product_id' ] ] = array(
						'product' => $product,
						'unit' => apply_filters( 'it_exchange_2checkout_subscription_unit', $unit, $time, $product, $transaction_object, $it_exchange_customer ),
						'duration' => apply_filters( 'it_exchange_2checkout_subscription_duration', 1, $time, $product, $transaction_object, $it_exchange_customer ),
						'cycles' => apply_filters( 'it_exchange_2checkout_subscription_cycles', 0, $time, $product, $transaction_object, $it_exchange_customer )
					);
				}
			}
		}
	}

	$total = $transaction_object->total;
	$discount = 0;

	if ( isset( $transaction_object->coupons_total_discounts ) ) {
		$discount = $transaction_object->coupons_total_discounts;
	}

	$total_pre_discount = $total + $discount;
	$taxes = '0.00';

	$default_address = array(
		'first-name'   => empty( $it_exchange_customer->data->first_name ) ? '' : $it_exchange_customer->data->first_name,
		'last-name'    => empty( $it_exchange_customer->data->last_name ) ? '' : $it_exchange_customer->data->last_name,
		'company-name' => '',
		'address1' => '',
		'address2' => '',
		'city'         => '',
		'state'        => '',
		'zip'          => '',
		'country'      => '',
		'email'        => empty( $it_exchange_customer->data->user_email ) ? '' : $it_exchange_customer->data->user_email,
		'phone'        => ''
	);

	$billing_address = $shipping_address = it_exchange_get_customer_billing_address( $it_exchange_customer->id );

	if ( function_exists( 'it_exchange_get_customer_shipping_address' ) ) {
		$shipping_address = it_exchange_get_customer_shipping_address( $it_exchange_customer->id );
	}

	if ( !empty( $billing_address ) ) {
		$billing_address = array_merge( $default_address, $billing_address );
	}
	else {
		$billing_address = $default_address;
	}

	if ( !empty( $shipping_address ) ) {
		$shipping_address = array_merge( $default_address, $shipping_address );
	}
	else {
		$shipping_address = $billing_address;
	}

	// Pass through Products
	// https://www.2checkout.com/documentation/checkout/parameter-sets/pass-through-products/

	$twocheckout_data = array(
		// Customer information
		'card_holder_name' => trim( $billing_address[ 'first-name' ] . ' ' . $billing_address[ 'last-name' ] ),
		'street_address' => $billing_address[ 'address1' ],
		'street_address2' => $billing_address[ 'address2' ],
		'city' => $billing_address[ 'city' ],
		'state' => $billing_address[ 'state' ],
		'zip' => $billing_address[ 'zip' ],
		'country' => $billing_address[ 'country' ],
		'email' => ( empty( $billing_address[ 'email' ] ) ? $it_exchange_customer->data->user_email : $billing_address[ 'email' ] ),

		// 'phone' => $billing_address[ 'email' ], @todo Phone support
		// 'phone_extension' => $billing_address[ 'email' ], @todo Phone extension support

		// Shipping information
		'ship_name' => trim( $shipping_address[ 'first-name' ] . ' ' . $shipping_address[ 'last-name' ] ),
		'ship_street_address' => $shipping_address[ 'address1' ],
		'ship_street_address2' => $shipping_address[ 'address2' ],
		'ship_city' => $shipping_address[ 'city' ],
		'ship_state' => $shipping_address[ 'state' ],
		'ship_zip' => $shipping_address[ 'zip' ],
		'ship_country' => $shipping_address[ 'country' ],

		// API settings
		'sid' => $settings[ '2checkout_sid' ],
		'mode' => '2CO',
		'currency_code' => $transaction_object->currency,
		'merchant_order_id' => $transaction_object->ID,
		'pay_method' => $settings[ '2checkout_default_payment_method' ],
		'x_receipt_link_url' => add_query_arg( array( 'it-exchange-transaction-method' => '2checkout', 'it-exchange-transaction-return' => 'complete' ), it_exchange_get_page_url( 'transaction' ) ),

		// 'lang' => 'en', @todo Multi-lingual support
		// 'coupon' => '', @todo 2Checkout coupon support

		'notify' => get_site_url() . '/?' . it_exchange_get_webhook( '2checkout' ) . '=1',
	);

	if ( $settings[ '2checkout_sandbox_mode' ] ) {
		$twocheckout_data[ 'demo' ] = 'Y';
	}

	$item_count = 0;

	foreach ( $transaction_object->products as $product ) {
		$price = $product[ 'product_subtotal' ]; // base price * quantity, w/ any changes by plugins
		$price = $price / $product[ 'count' ]; // get final base price (possibly different from $product[ 'product_base_price' ])

		// @todo handle product discounts
		//$price -= ( ( ( ( $total * $price ) / $total_pre_discount ) / 100 ) * $price ); // get discounted item price

		$price = it_exchange_format_price( $price, false );

		$twocheckout_data[ 'li_' . $item_count . '_type' ] = 'product'; // product|shipping|tax|coupon
		$twocheckout_data[ 'li_' . $item_count . '_product_id' ] = $product[ 'product_id' ];
		$twocheckout_data[ 'li_' . $item_count . '_name' ] = $product[ 'product_name' ];
		$twocheckout_data[ 'li_' . $item_count . '_price' ] = $price;
		$twocheckout_data[ 'li_' . $item_count . '_quantity' ] = $product[ 'count' ];

		$twocheckout_data[ 'li_' . $item_count . '_tangible' ] = 'N';//'Y';

		/*
		we're going to set all of them as non-tangible, because Exchange keeps track of all that
		if ( it_exchange_product_supports_feature( $product['product_id'], 'downloads', array( 'setting' => 'digital-downloads-product-type' ) ) ) {
			if ( it_exchange_product_has_feature( $product['product_id'], 'downloads', array( 'setting' => 'digital-downloads-product-type' ) ) ) {
				$twocheckout_data[ 'li_' . $item_count . '_tangible' ] = 'N';
			}
		}
		*/

		// Recurring info
		if ( !empty( $recurring_products ) ) {
			$recurring_product = current( $recurring_products );

			$twocheckout_data[ 'li_' . $item_count . '_recurrence' ] = $recurring_product[ 'duration' ] . ' ' . $recurring_product[ 'unit' ]; // _ Week|_ Month|_ Year
			$twocheckout_data[ 'li_' . $item_count . '_duration' ] = $recurring_product[ 'cycles' ] . ' ' . $recurring_product[ 'unit' ]; // Forever|_ Week|_ Month|_ Year

			if ( $recurring_product[ 'cycles' ] < 1 ) {
				$twocheckout_data[ 'li_' . $item_count . '_duration' ] = 'Forever';
			}

			//$twocheckout_data[ 'li_' . $item_count . '_startup_fee' ] = '';

		}

		//$twocheckout_data[ 'li_' . $item_count . '_option_' . $option_count . '_name' ] = ''; // Option name (Size); 64 chars max, no < or > characters
		//$twocheckout_data[ 'li_' . $item_count . '_option_' . $option_count . '_value' ] = ''; // Option value (Small); 64 chars max, no < or > characters
		//$twocheckout_data[ 'li_' . $item_count . '_option_' . $option_count . '_surcharge' ] = ''; // Option price; 0.00 for no cost options
		//$twocheckout_data[ 'li_' . $item_count . '_description' ] = ''; // 255 chars max, no < or > characters

		$item_count++;
	}

	/*
	// @todo Handle taxes?
	//$twocheckout_data[ 'li_' . $item_count . '_type' ] = 'tax';
	//$twocheckout_data[ 'li_' . $item_count . '_name' ] = 'Tax';
	//$twocheckout_data[ 'li_' . $item_count . '_price' ] = $taxes;
	//$twocheckout_data[ 'li_' . $item_count . '_quantity' ] = 1;
	//$twocheckout_data[ 'li_' . $item_count . '_tangible' ] = 'N';
	//$item_count++;

	// @todo Handle discounts?
	//$twocheckout_data[ 'li_' . $item_count . '_type' ] = 'discount';
	//$twocheckout_data[ 'li_' . $item_count . '_name' ] = 'Discount';
	//$twocheckout_data[ 'li_' . $item_count . '_price' ] = $discount;
	//$twocheckout_data[ 'li_' . $item_count . '_quantity' ] = 1;
	//$twocheckout_data[ 'li_' . $item_count . '_tangible' ] = 'N';
	//$item_count++;
	*/

	$twocheckout_data = apply_filters( 'it_exchange_2checkout_data', $twocheckout_data, $transaction_object, $it_exchange_customer );

	ob_start();

	Twocheckout_Charge::direct( $twocheckout_data, $settings[ '2checkout_purchase_button_label' ] );

	return ob_get_clean();
}

/**
 * Update profile status for a subscription
 *
 * @param string $profile_id
 * @param string $action
 * @param string $note
 *
 * @return array
 * @throws Exception
 */
function it_exchange_2checkout_addon_update_profile_status( $profile_id, $action = 'Cancel', $note = '' ) {

	$settings = it_exchange_get_option( 'addon_2checkout' );

	$url = 'https://api-3t.paypal.com/nvp';

	if ( $settings[ '2checkout_sandbox_mode' ] ) {
		$url = 'https://api-3t.sandbox.paypal.com/nvp';
	}

	// Hello future self...
	// https://developer.paypal.com/webapps/developer/docs/classic/api/merchant/ManageRecurringPaymentsProfileStatus_API_Operation_NVP/
	$twocheckout_data = array(
		'METHOD' => 'ManageRecurringPaymentsProfileStatus',
		'PROFILEID' => $profile_id,
		'ACTION' => $action,
		'NOTE' => $note,

		// API info
		'USER' => $settings[ '2checkout_api_username' ],
		'PWD' => $settings[ '2checkout_api_password' ],
		'SIGNATURE' => $settings[ '2checkout_api_signature' ],

		// Additional info
		'IPADDRESS' => $_SERVER[ 'REMOTE_ADDR' ],
		'VERSION' => '59.0'
	);

	$twocheckout_data = apply_filters( 'it_exchange_2checkout_update_profile_status_post_data', $twocheckout_data, $profile_id );

	$args = array(
		'method' => 'POST',
		'body' => $twocheckout_data,
		'user-agent' => 'iThemes Exchange',
		'timeout' => 90,
		'sslverify' => false
	);

	$response = wp_remote_request( $url, $args );

	if ( is_wp_error( $response ) ) {
		throw new Exception( __( 'Subscription API unavailable, please try again.', 'LION' ) );
	}

	$body = wp_remote_retrieve_body( $response );

	if ( empty( $body ) ) {
		throw new Exception( __( 'Subscription API error, please try again.', 'LION' ) );
	}

	parse_str( $body, $api_response );

	$status = strtolower( $api_response[ 'ACK' ] );

	switch ( $status ) {
		case 'success':
		case 'successwithwarning':
			// all good

			break;

		case 'failure':
		default:
			$messages = array();

			$message_count = 0;

			while ( isset( $api_response[ 'L_LONGMESSAGE' . $message_count ] ) ) {
				$message = $api_response[ 'L_SHORTMESSAGE' . $message_count ] . ': ' . $api_response[ 'L_LONGMESSAGE' . $message_count ] . ' (Error Code #' . $api_response[ 'L_ERRORCODE' . $message_count ] . ')';

				$messages[] = $message;

				$message_count++;
			}

			if ( empty( $messages ) ) {
				$message_count = 0;

				while ( isset( $api_response[ 'L_SHORTMESSAGE' . $message_count ] ) ) {
					$message = $api_response[ 'L_SHORTMESSAGE' . $message_count ] . ' (Error Code #' . $api_response[ 'L_ERRORCODE' . $message_count ] . ')';

					$messages[] = $message;

					$message_count++;
				}
			}

			if ( empty( $messages ) ) {
				$message_count = 0;

				while ( isset( $api_response[ 'L_SEVERITYCODE' . $message_count ] ) ) {
					$message = $api_response[ 'L_SEVERITYCODE' . $message_count ] . ' (Error Code #' . $api_response[ 'L_ERRORCODE' . $message_count ] . ')';

					$messages[] = $message;

					$message_count++;
				}
			}

			throw new Exception( sprintf( __( 'Error(s) with Payment Profile Update: %s', 'LION' ), '<ul><li>' . implode( '</li><li>', $messages ) . '</li></ul>' ) );

			break;
	}

	return true;
}

/**
 * Get card types and their settings
 *
 * Props to Gravity Forms / Rocket Genius for the logic
 *
 * @return array
 */
function it_exchange_2checkout_addon_get_card_types() {

	$cards = array(

		array(
			'name' => 'Amex',
			'slug' => 'amex',
			'lengths' => '15',
			'prefixes' => '34,37',
			'checksum' => true
		),
		array(
			'name' => 'Discover',
			'slug' => 'discover',
			'lengths' => '16',
			'prefixes' => '6011,622,64,65',
			'checksum' => true
		),
		array(
			'name' => 'MasterCard',
			'slug' => 'mastercard',
			'lengths' => '16',
			'prefixes' => '51,52,53,54,55',
			'checksum' => true
		),
		array(
			'name' => 'Visa',
			'slug' => 'visa',
			'lengths' => '13,16',
			'prefixes' => '4,417500,4917,4913,4508,4844',
			'checksum' => true
		),
		array(
			'name' => 'JCB',
			'slug' => 'jcb',
			'lengths' => '16',
			'prefixes' => '35',
			'checksum' => true
		),
		array(
			'name' => 'Maestro',
			'slug' => 'maestro',
			'lengths' => '12,13,14,15,16,18,19',
			'prefixes' => '5018,5020,5038,6304,6759,6761',
			'checksum' => true
		)

	);

	return $cards;
}

/**
 * Get the Card Type from a Card Number
 *
 * Props to Gravity Forms / Rocket Genius for the logic
 *
 * @param int|string $number
 *
 * @return bool
 */
function it_exchange_2checkout_addon_get_card_type( $number ) {

	//removing spaces from number
	$number = str_replace( array( '-', ' ' ), '', $number );

	if ( empty( $number ) ) {
		return false;
	}

	$cards = it_exchange_2checkout_addon_get_card_types();

	$matched_card = false;

	foreach ( $cards as $card ) {
		if ( it_exchange_2checkout_addon_matches_card_type( $number, $card ) ) {
			$matched_card = $card;

			break;
		}
	}

	if ( $matched_card && $matched_card[ 'checksum' ] && !it_exchange_2checkout_addon_is_valid_card_checksum( $number ) ) {
		$matched_card = false;
	}

	return $matched_card ? $matched_card : false;

}

/**
 * Match the Card Number to a Card Type
 *
 * Props to Gravity Forms / Rocket Genius for the logic
 *
 * @param int $number
 * @param array $card
 *
 * @return bool
 */
function it_exchange_2checkout_addon_matches_card_type( $number, $card ) {

	//checking prefix
	$prefixes = explode( ',', $card[ 'prefixes' ] );
	$matches_prefix = false;
	foreach ( $prefixes as $prefix ) {
		if ( preg_match( "|^{$prefix}|", $number ) ) {
			$matches_prefix = true;
			break;
		}
	}

	//checking length
	$lengths = explode( ',', $card[ 'lengths' ] );
	$matches_length = false;
	foreach ( $lengths as $length ) {
		if ( strlen( $number ) == absint( $length ) ) {
			$matches_length = true;
			break;
		}
	}

	return $matches_prefix && $matches_length;

}

/**
 * Check Credit Card number checksum
 *
 * Props to Gravity Forms / Rocket Genius for the logic
 *
 * @param int $number
 *
 * @return bool
 */
function it_exchange_2checkout_addon_is_valid_card_checksum( $number ) {

	$checksum = 0;
	$num = 0;
	$multiplier = 1;

	// Process each character starting at the right
	for ( $i = strlen( $number ) - 1; $i >= 0; $i-- ) {

		//Multiply current digit by multiplier (1 or 2)
		$num = $number{$i} * $multiplier;

		// If the result is in greater than 9, add 1 to the checksum total
		if ( $num >= 10 ) {
			$checksum++;
			$num -= 10;
		}

		//Update checksum
		$checksum += $num;

		//Update multiplier
		$multiplier = $multiplier == 1 ? 2 : 1;
	}

	return $checksum % 10 == 0;

}
