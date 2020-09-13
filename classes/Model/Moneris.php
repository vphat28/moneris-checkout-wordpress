<?php

namespace Moneris\Checkout\Model;

include_once MONERIS_WC_PLUGIN_DIR . 'includes/mpgClasses.php';

class Moneris {
	public static function capture( $amount, $store_id, $api_token, $orderid, $txnnumber, $customer_id = 'Customer ID', $dynamic_descriptor = 'Capture WooCommerce' ) {
		$compamount = $amount;

## step 1) create transaction array ###
		$txnArray = array(
			'type'               => 'completion',
			'txn_number'         => $txnnumber,
			'order_id'           => $orderid,
			'comp_amount'        => $compamount,
			'crypt_type'         => '7',
			'cust_id'            => $customer_id,
			'dynamic_descriptor' => $dynamic_descriptor
		);


## step 2) create a transaction  object passing the hash created in
## step 1.

		$mpgTxn = new \mpgTransaction( $txnArray );

## step 3) create a mpgRequest object passing the transaction object created
## in step 2
		$mpgRequest = new \mpgRequest( $mpgTxn );
		$mpgRequest->setProcCountryCode( "CA" ); //"US" for sending transaction to US environment
		$mpgRequest->setTestMode( true ); //false or comment out this line for production transactions

## step 4) create mpgHttpsPost object which does an https post ##
		$mpgHttpPost = new mpgHttpsPost( $store_id, $api_token, $mpgRequest );

## step 5) get an mpgResponse object ##
		$mpgResponse = $mpgHttpPost->getMpgResponse();

## step 6) retrieve data using get methods

		return $mpgResponse;
	}

	public static function refund( $amount, $store_id, $api_token, $orderid, $txnnumber, $customer_id = 'Customer ID', $dynamic_descriptor = 'refund WooCommerce' ) {
//		$store_id           = 'store5';
//		$api_token          = 'yesguy';
//		$orderid            = 'ord-110515-11:32:49';
//		$txnnumber          = '31451-0_10';
//		$dynamic_descriptor = '123';

## step 1) create transaction array ###
		$txnArray = array(
			'type'               => 'refund',
			'txn_number'         => $txnnumber,
			'order_id'           => $orderid,
			'amount'             => $amount,
			'crypt_type'         => '7',
			'cust_id'            => $customer_id,
			'dynamic_descriptor' => $dynamic_descriptor
		);

## step 2) create a transaction  object passing the array created in
## step 1.

		$mpgTxn = new \mpgTransaction( $txnArray );

## step 3) create a mpgRequest object passing the transaction object created
## in step 2
		$mpgRequest = new \mpgRequest( $mpgTxn );
		$mpgRequest->setProcCountryCode( "CA" ); //"US" for sending transaction to US environment
		$mpgRequest->setTestMode( true ); //false or comment out this line for production transactions

## step 4) create mpgHttpsPost object which does an https post ##
		$mpgHttpPost = new \mpgHttpsPost( $store_id, $api_token, $mpgRequest );

## step 5) get an mpgResponse object ##
		$mpgResponse = $mpgHttpPost->getMpgResponse();

		return $mpgResponse;
	}
}
