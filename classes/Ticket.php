<?php

namespace Moneris\Checkout;

use GuzzleHttp\Client;
use Moneris\Checkout\Helper\Data;

class Ticket
{
    const PREREQUEST_ENDPOINT = 'https://gatewayt.moneris.com/chkt/request/request.php';

    /** @var Data */
    private $data;

    public function __construct()
    {
        $this->data = new Data();
    }

    public function getEndpoint()
    {
        return self::PREREQUEST_ENDPOINT;
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
        $requestData->shipping_rates = [];

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

        $body = json_decode($response->getBody()->getContents(), true);

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
        $url = $this->getEndpoint();

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
