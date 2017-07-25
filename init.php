<?php
/**
 * ExchangeWP Exchange 2Checkout Add-on
 * @package IT_Exchange_Addon_2Checkout
 * @since 1.0.0
*/

/**
 * Exchange Transaction Add-ons require several hooks in order to work properly.
 * Most of these hooks are called in api/transactions.php and are named dynamically
 * so that individual add-ons can target them. eg: it_exchange_refund_url_for_2checkout
 * We've placed them all in one file to help add-on devs identify them more easily
*/
include( 'lib/required-hooks.php' );

/**
 * Exchange will build your add-on's settings page for you and link to it from our add-on
 * screen. You are free to link from it elsewhere as well if you'd like... or to not use our API
 * at all. This file has all the functions related to registering the page, printing the form, and saving
 * the options. This includes the wizard settings. Additionally, we use the Exchange storage API to
 * save / retreive options. Add-ons are not required to do this.
*/
include( 'lib/addon-settings.php' );

/**
 * The following file contains utility functions specific to our 2checkout add-on
 * If you're building your own transaction-method addon, it's likely that you will
 * need to do similar things. This includes enqueueing scripts, formatting data for 2checkout, etc.
*/
include( 'lib/addon-functions.php' );

/**
 * Webhooks
*/
include( 'lib/addon-webhooks.php' );

/**
 * The following file contains the 2Checkout PHP Library
 *
 * @link https://github.com/2Checkout/2checkout-php
*/
include( 'lib/Twocheckout.php' );
