
const $ = jQuery;
const urlBuilder = {
   build: function (path) {
       var baseUrl = window.MonerisCheckoutConfig.base_url;
       return baseUrl + '/' + path;
   }
};

const fullScreenLoader = {
    startLoader: function () {

    },
    stopLoader: function () {

    },
};
    window.MonerisCheckoutWC = {

        defaults: {
            template: 'Moneris_MonerisCheckout/button-in-cart'
        },

        cartId: null,

        isMonerisCheckoutActive: function () {
            return true;
        },

        initialize: function () {
            this._super();

            if (
                $('body').hasClass('checkout-index-index') ||
                $('body').hasClass('checkout-cart-index')
            ) {
                this.isInCheckoutPages = true;
            }

        },

        myCheckout: {},
        formattedAddress: {
        },
        isInCheckoutPages: false,
        configureButton: function () {
            if (typeof window.MonerisCheckoutConfig === 'undefined') {
                return;
            }
            var self = this;
            window.$ = $;
            $(document).ready(function () {
                self.myCheckout = new monerisCheckout();
                var res = {};

                if (window.MonerisCheckoutConfig.mode == 'qa') {
                    self.myCheckout.setMode("dev");
                }

                self.myCheckout.setCheckoutDiv("monerisCheckout");

                self.myCheckout.setCallback("address_change", myAddressChange);
                self.myCheckout.setCallback("cancel_transaction", myCancelTransaction);
                self.myCheckout.setCallback("payment_receipt", myPaymentReceipt);
                self.myCheckout.setCallback("payment_complete", myPaymentComplete);
                self.myCheckout.setCallback("error_event", myErrorEvent);
                self.myCheckout.setCallback("page_loaded", myPageLoad);

                self.myCheckout.logConfig();

                self.myCheckout.startCheckout(window.MonerisCheckoutConfig.ticket);

                function myAddressChange(msg) {
                    console.log("new address is: ");
                    var response = JSON.parse(msg);
                    console.log(response);
                    var urlXhr;
                    urlXhr = urlBuilder.build('moneris-checkout-wc?type=shipping_rates');

                    var address = response.address;
                    var newAddress = {
                        firstname: 'test',
                        lastname: 'test',
                        street: [address.address_1, address.address_2],
                        city: address.city,
                        countryId: address.country,
                        postcode: address.postal_code,
                        region: address.province,
                        telephone: '123456789',
                    };

                    self.formattedAddress = newAddress;
                    console.log(urlXhr);

                    $.ajax({
                        url: urlXhr,
                        data: JSON.stringify({ 'address': self.formattedAddress }),
                        type: 'post',
                        dataType: 'json',
                        contentType: 'application/json'
                    }).done(function (result) {
                        var rates = {};
                        rates["action"] = "set_shipping_rates";
                        rates["data"] = {};

                        var shippingOptions = [];

                        for (var i = 0; i < result.length; i++) {
                            shippingOptions.push({
                                'code': result[i]['code'],
                                'description': result[i]['label'],
                                'date': result[i]['label'],
                                'amount': result[i]['cost'],
                                'txn_taxes': 0,
                                'txn_total': window.MonerisCheckoutConfig.cart_total + result[i]['cost'],
                            });
                        }

                        rates["data"] = shippingOptions;
                        var json_rate = JSON.stringify(rates)
                        console.log(json_rate);
                        self.myCheckout.setNewShippingRates(json_rate);
                    });
                }

                function myCancelTransaction(data) {
                    console.log(data);
                    fullScreenLoader.stopLoader();
                    self.myCheckout.closeCheckout();
                    window.location.href = window.MonerisCheckoutConfig.shop_url;
                }

                function myPaymentComplete(data) {
                    fullScreenLoader.startLoader();
                    self.myCheckout.closeCheckout();
                    window.location.href = window.MonerisCheckoutConfig.shop_url;
                }

                function myPaymentReceipt(data) {
                    fullScreenLoader.startLoader();
                    console.log('payment receipt');
                    console.log(data);
                    var json = JSON.parse(data);
                    var urlXhr = urlBuilder.build('moneris-checkout-wc?type=pay');

                    $.post(urlXhr, json, function(data) {
                        self.chargeRequest(data.data.request);
                    }, "json");
                }

                function myErrorEvent() {
                    console.log('error');
                    self.myCheckout.closeCheckout();
                    window.location.href = window.MonerisCheckoutConfig.shop_url;
                }

                function myPageLoad(data) {
                    console.log(data);
                    var load = JSON.parse(data);
                }
            });
        },

        initButton: function () {
            var self = this;

            $('body').append('<div id="monerisCheckout" style="z-index: 9999"></div>');
            var url = "https://gatewaydev.moneris.com/chkt/js/chkt_v1.00.js";

            $.getScript(url, function () {
                self.configureButton();
            });
        },

        chargeRequest: function (ev) {
            var self = this;

            self.makeMineOrderShipping(ev);
        },

        makeMineOrderShipping: function (ev) {
            console.log('make min order shipping');
            console.log(ev);
            var self = this;
            var urlXhr = urlBuilder.build('?wc-ajax=checkout');
            var shippingAddress = ev.shipping;
            var billingAddress = ev.billing;
            var customer = ev.cust_info;
            var shippingOption = ev.ship_rates.code;
            var s = [];
            s[0] = shippingOption;

            $.ajax({
                url: urlXhr,
                data: {
                    billing_first_name: customer.first_name,
                    billing_last_name: customer.last_name,
                    // billing_company: Mr
                    billing_country: billingAddress.country,
                    billing_address_1: billingAddress.address_1,
                    billing_address_2: billingAddress.address_2,
                    billing_city: billingAddress.city,
                    billing_state: billingAddress.province,
                    billing_postcode: billingAddress.postal_code,
                    billing_phone: customer.phone,
                    billing_email: customer.email,
                    shipping_first_name: customer.first_name,
                    shipping_last_name: customer.last_name,
                    // shipping_company: Mr
                    shipping_country: shippingAddress.country,
                    shipping_address_1: shippingAddress.address_1,
                    shipping_address_2: shippingAddress.address_2,
                        shipping_city: shippingAddress.city,
                    shipping_state: shippingAddress.province,
                    shipping_postcode: shippingAddress.postal_code,
                    moneris_checkout_id: ev.ticket,
                    // order_comments:
                    'shipping_method[0]': shippingOption,
                    payment_method: 'moneris_checkout_woocommerce',
                    'woocommerce-process-checkout-nonce': $('#woocommerce-process-checkout-nonce').attr('value')
                },
                type: 'post',
            }).done(function (returnData) {
                if (returnData.result == "success") {
                    window.location.href = window.MonerisCheckoutConfig.shop_url;;
                }
            }).fail(function () {
                console.log('can not make order');
            });
        },

        makeMineOrder: function (ev) {
            var self = this;
            var urlXhr = urlBuilder.build('rest/V1/carts/' + 'mine' + '/payment-information');
            var paymentInformation = {
                'email': ev.cust_info.email,
                'paymentMethod': {
                    'method': 'chmoneriscc',
                    'additional_data': {
                        'moneris_checkout_ticket': ev.ticket
                    }
                },
                'billingAddress': self.formattedAddress
            };

            $.ajax({
                url: urlXhr,
                data: JSON.stringify(paymentInformation),
                type: 'post',
                dataType: 'json',
                contentType: 'application/json'
            }).done(function () {
                Data.set('cart', {});
                window.location.href = urlBuilder.build('checkout/onepage/success');
            });
        },

        makeQuestOrder: function (ev) {
            var self = this;
            var urlXhr = urlBuilder.build('rest/V1/guest-carts/' + self.cartId + '/payment-information');
            var paymentInformation = {
                'email': ev.cust_info.email,
                'paymentMethod': {
                    'method': 'chmoneriscc',
                    'additional_data': {
                        'moneris_checkout_ticket': ev.ticket
                    }
                },
                'billingAddress': self.formattedAddress
            };

            $.ajax({
                url: urlXhr,
                data: JSON.stringify(paymentInformation),
                type: 'post',
                dataType: 'json',
                contentType: 'application/json'
            }).done(function () {
                Data.set('cart', {});
                fullScreenLoader.stopLoader();
                window.location.href = urlBuilder.build('checkout/onepage/success');
            });
        },
    };

    window.MonerisCheckoutWC.initButton();
