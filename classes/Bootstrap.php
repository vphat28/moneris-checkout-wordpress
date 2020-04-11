<?php

namespace Moneris\Checkout;

class Bootstrap
{
    public function init()
    {
        if ($this->check_end_point()) {
            $api = new Api();
            $api->execute();
            exit;
        }

        $gateway = new \Moneris\Checkout\Gateway();
        add_filter('woocommerce_payment_gateways', [$gateway, 'add_moneris_checkout_method']);


        add_shortcode( 'moneris_checkout_woocommerce', 'moneris_checkout_woocommerce_add_shortcode_callback');
    }

    public function check_end_point()
    {
        $endpoint = trim($_SERVER['REQUEST_URI'], '/');

        if ( strpos( $endpoint, 'moneris-checkout-wc' ) === 0 ) {
            return true;
        }

        return false;
    }
}
