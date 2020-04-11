<?php

namespace Moneris\Checkout;

use Moneris\Checkout\Helper\Data;

class Gateway extends \WC_Payment_Gateway
{
    const PAYMENT_CODE = 'moneris_checkout_woocommerce';

    public function add_moneris_checkout_method($methods) {
        $methods[] = '\Moneris\Checkout\Gateway';
        return $methods;
    }

    public function __construct()
    {
        $this->id = self::PAYMENT_CODE;
        $this->has_fields = false;
        $this->title = $this->get_option('title');
        $this->method_title = __('Moneris Checkout Woocommerce Integration');
        $this->method_description = __('Checkout with Moneris one stop payment solution');
        $this->init_form_fields();
        $this->init_settings();
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        $this->title = $this->get_option('title');
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Payment', 'woocommerce'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default' => __('Pay with Moneris Checkout', 'woocommerce'),
                'desc_tip' => true,
            ),
        );
    }

    /**
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        global $woocommerce;
        $order = new \WC_Order($order_id);
        $quoteId = WC()->cart->get_cart_hash();
        $ticketID = wc_clean(wp_unslash($_POST['moneris_checkout_id']));
        $ticket = new Ticket();
        $receipt = $ticket->getReceiptData($ticketID);


        $helper = new Data();
        $helper->log('hash ' . $quoteId);
        $helper->log('order_id moneris ' . $receipt['response']['request']['order_no']);
        $helper->log('receipt for order id ' . $order_id . ' _ ' . json_encode($receipt));

        if ($quoteId == $receipt['response']['request']['order_no']) {
            $helper->log('hash matched ');
            wc_add_notice(__('Thank you for your order', 'woocommerce'), 'success');
            $order->payment_complete($receipt['response']['request']['ticket']);
        } else {
            wc_add_notice(__('Payment error', 'woocommerce'), 'error');
            return;
        }

        // Remove cart
        $woocommerce->cart->empty_cart();

        return [
            'result' => 'success',
        ];
    }
}
