<?php

namespace Moneris\Checkout;

use Moneris\Checkout\Helper\Data;

class Gateway extends \WC_Payment_Gateway {
	const PAYMENT_CODE = 'moneris_checkout_woocommerce';

	public function add_moneris_checkout_method( $methods ) {
		$methods[] = '\Moneris\Checkout\Gateway';

		return $methods;
	}

	public function __construct() {
		$this->id                 = self::PAYMENT_CODE;
		$this->has_fields         = false;
		$this->title              = $this->get_option( 'title' );
		$this->method_title       = __( 'Moneris Checkout Woocommerce Integration' );
		$this->method_description = __( 'Checkout with Moneris one stop payment solution' );
		$this->supports           = array(
			'products',
			'refunds',
		);
		$this->init_form_fields();
		$this->init_settings();
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'capture_payment' ) );
		$this->title = $this->get_option( 'title' );
	}

	/**
	 * Capture payment when the order is changed from on-hold to complete or processing.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 *
	 * @param  int $order_id
	 */
	public function capture_payment( $order_id ) {
		$captured = get_post_meta( $order_id, 'moneris_captured', true);

		if (!empty($captured)) {
			return;
		}

		$helper = new Data();
		$order  = wc_get_order( $order_id );
		$helper->log( 'Capturing ' . $order_id );
		$charge_id   = $order->get_transaction_id();
		$ticket      = new Ticket();
		$receipt     = $ticket->getReceiptData( $charge_id );
		$order_total = $order->get_total();

		$mpgResponse = \Moneris\Checkout\Model\Moneris::capture(
			$order_total,
			$helper->getStoreID(),
			$helper->getApiToken(),
			$helper->isTestMode(),
			$receipt['response']['receipt']['cc']['order_no'],
			$receipt['response']['receipt']['cc']['transaction_no'],
			$receipt['response']['receipt']['cc']['cust_id']
		);

		if ( $mpgResponse->getComplete() === 'true' ) {
			add_post_meta( $order_id, 'moneris_captured', true);
			add_post_meta( $order_id, 'moneris_chare_id', $mpgResponse->getTxnNumber() );
			$order->add_order_note( sprintf( __( 'Moneris charge complete (Charge ID: %s)' ), $mpgResponse->getTxnNumber() ) );

			if ( is_callable( array( $order, 'save' ) ) ) {
				$order->save();
			}
		}
	}

	/**
	 * Refund a charge.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 *
	 * @param  int $order_id
	 * @param  float $amount
	 *
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order  = wc_get_order( $order_id );

		$helper = new Data();

		if ( ! $order ) {
			return false;
		}
		$charge_id = $order->get_transaction_id();
		$helper->log( 'Refunding ' . $charge_id );
		$ticket  = new Ticket();
		$receipt = $ticket->getReceiptData( $charge_id );
		$helper->log( 'found ' . print_r( $receipt, 1 ) );

		if ( empty( $amount ) ) {


			return [
				'result' => 'error',
			];
		}

		$mpgResponse = \Moneris\Checkout\Model\Moneris::refund(
			$amount,
			$helper->getStoreID(),
			$helper->getApiToken(),
			$helper->isTestMode(),
			$receipt['response']['receipt']['cc']['order_no'],
			get_post_meta( $order_id, 'moneris_chare_id', true ),
			$receipt['response']['receipt']['cc']['cust_id']
		);

		if ( $mpgResponse->getComplete() === 'true' ) {
			$refund_message = sprintf( __( 'Refunded %1$s - Refund ID: %2$s - Reason: %3$s' ), $amount, $mpgResponse->getTxnNumber(), $reason );

			$order->add_order_note( $refund_message );

			return true;
		}

		return false;
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Payment', 'woocommerce' ),
				'default' => 'yes'
			),
			'title'   => array(
				'title'       => __( 'Title', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
				'default'     => __( 'Pay with Moneris Checkout', 'woocommerce' ),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * @param int $order_id
	 *
	 * @return array|void
	 * @throws \WC_Data_Exception
	 */
	public function process_payment( $order_id ) {
		global $woocommerce;
		$order    = new \WC_Order( $order_id );
		$quoteId  = WC()->cart->get_cart_hash();
		$ticketID = wc_clean( wp_unslash( $_POST['moneris_checkout_id'] ) );
		$ticket   = new Ticket();
		$receipt  = $ticket->getReceiptData( $ticketID );


		$helper = new Data();
		$helper->log( 'hash ' . $quoteId );
		$helper->log( 'order_id moneris ' . $receipt['response']['request']['order_no'] );
		$helper->log( 'receipt for order id ' . $order_id . ' _ ' . json_encode( $receipt ) );

		if ( $quoteId == $receipt['response']['request']['order_no'] ) {
			$helper->log( 'hash matched ' );
			wc_add_notice( __( 'Thank you for your order', 'woocommerce' ), 'success' );

			if ( isset( $receipt['response']["receipt"]["cc"]["transaction_code"] ) && $receipt['response']["receipt"]["cc"]["transaction_code"] !== '00' ) {
				/* translators: transaction id */
				$order->set_transaction_id( $receipt['response']['request']['ticket'] );
				$order->update_status( 'on-hold', sprintf( __( 'Moneris charge authorized (Charge ID: %s). Process order to take payment' ), $receipt['response']["receipt"]["cc"]["transaction_no"] ) );
			} else {
				$order->payment_complete( $receipt['response']['request']['ticket'] );
				add_post_meta( $order_id, 'moneris_chare_id', $receipt['response']["receipt"]["cc"]["transaction_no"] );
			}
//			$order->set_status()
		} else {
			wc_add_notice( __( 'Payment error', 'woocommerce' ), 'error' );

			return;
		}

		// Remove cart
		$woocommerce->cart->empty_cart();

		return [
			'result' => 'success',
		];
	}
}
