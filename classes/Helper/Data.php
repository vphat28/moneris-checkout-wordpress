<?php

namespace Moneris\Checkout\Helper;

class Data
{
    public function getStoreID()
    {
        return get_option('_moneris_store_id');
    }

    public function getApiToken()
    {
        return get_option('_moneris_api_key');
    }

    public function getCheckoutId()
    {
        return get_option('_moneris_checkout_checkout_id');
    }

    public function getMode()
    {
        return get_option('_moneris_checkout_test_mode') == 'yes' ? 'qa' : 'live';
    }

    public function isTestMode()
    {
        return get_option('_moneris_checkout_test_mode') == 'yes' ? true : false;
    }

    public function getBaseUrl()
    {
        return trim(home_url(), '/');
    }

    public function get_shop_url()
    {
        return get_permalink( get_option( 'woocommerce_shop_page_id' ));
    }

    public function log($data)
    {
        file_put_contents(ABSPATH . 'wp-content/uploads/moneris.log', PHP_EOL . $data . PHP_EOL, FILE_APPEND);
    }

    public function get_cart_rates()
    {
        $shipping = WC()->shipping();
        $packages = $shipping->calculate_shipping(WC()->cart->get_shipping_packages());

        if (isset($packages[0])) {
            return ($packages[0]['rates']);
        }

        return [];
    }

    public function get_cart_total()
    {
        $total = WC()->cart->get_subtotal();

        return !empty($total) ? $total : 0;
    }

    public function get_cart_tax()
    {
        $total = WC()->cart->get_total_tax();

        return !empty($total) ? $total : 0;
    }

    public function get_cart_items()
    {
        $qty = WC()->cart->get_cart_item_quantities();

        return $qty;
    }
}
