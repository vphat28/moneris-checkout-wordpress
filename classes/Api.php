<?php

namespace Moneris\Checkout;

class Api
{
    public function execute()
    {
        $endpoint = sanitize_title($_GET['type']);

        switch ($endpoint) {
            case 'shipping_rates':
                $this->shipping_rates();
                break;
            case 'pay':
                $this->pay();
                break;
        }
    }

    private function pay()
    {
        $helper = new Helper\Data();
        $helper->log("Pay action called");
        $ticketID = sanitize_text_field($_REQUEST['ticket']);
        $ticket = new Ticket();
        $body = $ticket->getReceiptData($ticketID);
        $helper->log('Receipt ' . json_encode($body));

        if ($body['response']['success'] === "true") {
            // Generate order

            echo wp_json_encode([
                'success' => true,
                'data' => $body['response'],
            ]);
        } else {
            echo wp_json_encode([
                'success' => false,
                'data' => __('Get receipt request failed'),
            ]);
        }
    }

    /**
     * @throws \Exception
     */
    private function shipping_rates()
    {
        $helper = new Helper\Data();
        $input = json_decode(file_get_contents('php://input'), true);
        $inputAddress = $input['address'];
        $address = array();

        $address['country']  = isset( $inputAddress['countryId'] ) ? wc_clean( wp_unslash( $inputAddress['countryId'] ) ) : ''; // WPCS: input var ok, CSRF ok, sanitization ok.
        $address['state']    = isset( $inputAddress['region'] ) ? wc_clean( wp_unslash( $inputAddress['region'] ) ) : ''; // WPCS: input var ok, CSRF ok, sanitization ok.
        $address['postcode'] = isset( $inputAddress['postcode'] ) ? wc_clean( wp_unslash( $inputAddress['postcode'] ) ) : ''; // WPCS: input var ok, CSRF ok, sanitization ok.
        $address['city']     = isset( $inputAddress['city'] ) ? wc_clean( wp_unslash( $inputAddress['city'] ) ) : ''; // WPCS: input var ok, CSRF ok, sanitization ok.
        $helper->log('calculate shipping' . json_encode($address));
        $this->calculate_shipping($address);
        $rates = $helper->get_cart_rates();
        $output = [];

        foreach ($rates as $rate) {
            /** @var \WC_Shipping_Rate $rate */
            $output[] = [
                'cost' => $rate->get_cost(),
                'code' => $rate->get_id(),
                'label' => $rate->get_label(),
            ];
        }
        echo wp_json_encode($output);
    }


    /**
     * @param $address
     */
    public function calculate_shipping($address) {
        try {
            WC()->shipping()->reset_shipping();

            $address = apply_filters( 'woocommerce_cart_calculate_shipping_address', $address );

            if ( $address['postcode'] && ! \WC_Validation::is_postcode( $address['postcode'], $address['country'] ) ) {
                throw new \Exception( __( 'Please enter a valid postcode / ZIP.', 'woocommerce' ) );
            } elseif ( $address['postcode'] ) {
                $address['postcode'] = wc_format_postcode( $address['postcode'], $address['country'] );
            }

            if ( $address['country'] ) {
                if ( ! WC()->customer->get_billing_first_name() ) {
                    WC()->customer->set_billing_location( $address['country'], $address['state'], $address['postcode'], $address['city'] );
                }
                WC()->customer->set_shipping_location( $address['country'], $address['state'], $address['postcode'], $address['city'] );
            } else {
                WC()->customer->set_billing_address_to_base();
                WC()->customer->set_shipping_address_to_base();
            }

            WC()->customer->set_calculated_shipping( true );
            WC()->customer->save();

            wc_add_notice( __( 'Shipping costs updated.', 'woocommerce' ), 'notice' );

            do_action( 'woocommerce_calculated_shipping' );

        } catch ( \Exception $e ) {
            if ( ! empty( $e ) ) {
                wc_add_notice( $e->getMessage(), 'error' );
            }
        }
    }
}
