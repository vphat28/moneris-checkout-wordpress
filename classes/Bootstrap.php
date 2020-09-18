<?php

namespace Moneris\Checkout;

use Moneris\Checkout\Helper\Data;

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
        add_filter('woocommerce_cart_needs_shipping', [$this, 'check_needs_shipping']);
        add_action('carbon_fields_container_moneris_checkout_account_details_before_fields', [$this, 'add_moneris_checkout_introduction']);


        add_shortcode( 'moneris_checkout_woocommerce', 'moneris_checkout_woocommerce_add_shortcode_callback');
    }

    public function check_needs_shipping($needs_shipping)
    {
		  $helper = new Data();
		  
		  if (!$helper->isShippingMode()) {
		    return false;
      }

      return $needs_shipping;
    }

    public function check_end_point()
    {
        $endpoint = trim($_SERVER['REQUEST_URI'], '/');

        if ( strpos( $endpoint, 'moneris-checkout-wc' ) === 0 ) {
            return true;
        }

        return false;
    }

    public function add_moneris_checkout_introduction()
    {
    	?>
		<p><img src='https://www.moneris.com/-/media/Moneris/Files/EN/Moneris-Logos/Moneris_MD_BIL_CMYK_2016.ashx?h=59&w=166&hash=5E684F4C56A2FFCA6DA0A48FDC19C909'/><br><b>Accept payments on your website</b>
			<br>
			With Moneris Checkout, a
			comprehensive online payment solution,
			you can easily and securely process
			customer transactions on your website.
			Bring the power of ecommerce to your
			website with Moneris Checkout.
			<br><br>
			Don’t have an account with Moneris
			yet? It’s easy to get started. Call
			Moneris at 1-855-232-2365 and mention
			configuration code ECNP-00444.</p>
		<?php
    }
}
