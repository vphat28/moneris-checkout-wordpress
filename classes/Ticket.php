<?php

namespace Moneris\Checkout;

use GuzzleHttp\Client;
use Moneris\Checkout\Helper\Data;

class Ticket
{
    const PREREQUEST_ENDPOINT = 'https://gatewaydev.moneris.com/chkt/request/request.php';
    const PREREQUEST_ENDPOINT_PROD = 'https://gateway.moneris.com/chkt/request/request.php';

    /** @var Data */
    private $data;

    public function __construct()
    {
        $this->data = new Data();
    }

    public function getEndpoint($test = true)
    {
    	if ($test) {
			return self::PREREQUEST_ENDPOINT;
		} else {
    		return self::PREREQUEST_ENDPOINT_PROD;
		}
    }

    private function formatPrice($number)
    {
        return number_format((float)$number, 2, '.', '');
    }

    public function getTicket()
    {
        $woocommerce = WC();
        $url = self::PREREQUEST_ENDPOINT;

        /** @var Client $client */
        $client = new Client([
            'headers' => ['Content-Type' => 'application/json']
        ]);

        // Get quote
        $quoteId = (WC()->cart->get_cart_hash());

        $requestData = new \stdClass;
        $requestData->store_id = $this->data->getStoreId();
        $requestData->api_token = $this->data->getApiToken();
        $requestData->checkout_id = $this->data->getCheckoutId();
        $requestData->integrator = "cr_dev";
        $requestData->txn_total = $this->formatPrice(WC()->cart->get_total(false));
        $requestData->environment = $this->data->getMode();
        $requestData->action = "preload";
        $requestData->order_no = $quoteId;
        $requestData->cust_id = "chkt - cust";
        $requestData->dynamic_descripto = "dyndesc";
        $requestData->cart = new \stdClass;
        $requestData->cart->items = [];
        if ($this->data->isShippingMode()) {
			$rates = $this->data->get_cart_rates();

			if ( ! empty( $rates ) ) {
				$requestData->shipping_rates = [];

				foreach ( $rates as $rate ) {
					/** @var \WC_Shipping_Rate $rate */
					$newRate = new \stdClass();

					$newRate->code                 = $rate->get_id();
					$newRate->description          = $rate->get_label();
					$newRate->date                 = " ";
					$newRate->amount               = $rate->get_cost();
					$newRate->txn_taxes            = $this->formatPrice( $this->data->get_cart_tax() );
					$newRate->txn_total            = $this->formatPrice( $this->data->get_cart_total() + $rate->get_cost() );
					$newRate->default_rate         = "false";
					$requestData->shipping_rates[] = $newRate;
				}
			}
		}

        $quoteItems = $woocommerce->cart->get_cart_contents();

        if (!empty($quoteItems)) {
            foreach ($quoteItems as $item) {
                $product = wc_get_product($item['product_id']);
                $itemDataToSend = new \stdClass();
                $image_id  = $product->get_image_id();
                $image_url = wp_get_attachment_image_url( $image_id, 'small' );
                $itemDataToSend->url = $image_url;

                $itemDataToSend->description = $product->get_name();
                $itemDataToSend->product_code = $product->get_sku();
                $itemDataToSend->unit_cost = $this->formatPrice($product->get_price());
                $itemDataToSend->quantity = $item['quantity'];

                $requestData->cart->items[] = $itemDataToSend;
            }
        }

        $requestData->cart->quote_id = $quoteId;

        $requestData->subtotal = $this->formatPrice($woocommerce->cart->get_total(false));

        $this->data->log(json_encode($requestData));

		$response = $client->post($url,
            ['body' => json_encode(
                $requestData
            )]
        );

		$body_content = $response->getBody()->getContents();
		$body = json_decode($body_content, true);

		if ($body['response']['success'] === "true") {
            return [
                'ticket' => $body['response']['ticket'],
                'quote_id' => $requestData->order_no,
            ];
        }

        return null;
    }

    public function getReceiptData($ticket)
    {
        $url = $this->getEndpoint($this->data->getMode());

        /** @var Client $client */
        $client = new Client([
            'headers' => ['Content-Type' => 'application/json']
        ]);

        $requestData = new \stdClass;
        $requestData->store_id = $this->data->getStoreId();
        $requestData->api_token = $this->data->getApiToken();
        $requestData->checkout_id = $this->data->getCheckoutId();
        $requestData->ticket = $ticket;
        $requestData->environment = $this->data->getMode();
        $requestData->action = 'receipt';
        $this->data->log('Request receipt ' . json_encode($requestData));

        $response = $client->post($url,
            ['body' => json_encode(
                $requestData
            )]
        );

        $body = json_decode($response->getBody()->getContents(), true);

        return $body;
    }
}
