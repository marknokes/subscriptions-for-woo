<?php

namespace PPSFWOO;

use PPSFWOO\PluginMain,
    PPSFWOO\Exception,
    PPSFWOO\Product;

use WooCommerce\PayPalCommerce\PPCP,
    WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer,
    WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;

class PayPal
{
    const EP_SUBSCRIPTIONS  = "/v1/billing/subscriptions/";

    const EP_PLANS          = "/v1/billing/plans/";

    const EP_PRODUCTS       = "/v1/catalogs/products/";

    const EP_WEBHOOKS       = "/v1/notifications/webhooks";

    const EP_VERIFY_SIG     = "/v1/notifications/verify-webhook-signature";

    public static function button($product_id = NULL)
    {
        $product_id = !empty($product_id) ? $product_id: get_the_ID();
        
        $product = wc_get_product($product_id);

        if($product && !$product->is_type(Product::TYPE)) {

            echo "<div><p>Product ID " . absint($product_id) . " is not a subscribtion product.</p></div>";

        }

        $PluginMain = PluginMain::get_instance();

        wp_enqueue_script(
            'ppsfwoo-paypal-sdk',
            "https://www.paypal.com/sdk/js?client-id=$PluginMain->client_id&vault=true&intent=subscription",
            [],
            null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion 
            true
        );

        wp_enqueue_script(
            'ppsfwoo-paypal-button',
            $PluginMain->plugin_dir_url . "js/paypal-button.min.js",
            ['ppsfwoo-paypal-sdk'],
            $PluginMain::plugin_data('Version'),
            true
        );

        wp_add_inline_script('ppsfwoo-paypal-button', "
            document.getElementById('ppsfwoo-subscribe-button-$product_id')
                .addEventListener('click', function() {
                    this.style.display = 'none';
                    document.getElementById('lds-ellipsis-$product_id').style.setProperty('display', 'inline-block', 'important');
                    ppsfwooInitializePayPalSubscription($product_id, this);
                });
        ");
        
        $PluginMain::display_template("paypal-button", [
            'button_text' => $PluginMain->ppsfwoo_button_text,
            'product_id' => $product_id
        ]);
    }

	public static function env()
    {
        $ppcp = new PPCP();
                    
        $container = $ppcp->container();

        $settings = $container->get('wcgateway.settings');

        $sandbox_on = $settings->has('sandbox_on') && $settings->get('sandbox_on');

        $env = [
            'paypal_api_url' => $sandbox_on ? 'https://api-m.sandbox.paypal.com': 'https://api-m.paypal.com',
            'paypal_url'     => $sandbox_on ? 'https://www.sandbox.paypal.com': 'https://www.paypal.com',
            'client_id'      => $settings->has('client_id') && $settings->get('client_id') ? $settings->get('client_id'): '',
            'env'            => $sandbox_on ? 'sandbox': 'production'
        ];

        return $env;
    }

    public static function response_status_is($response, $status)
    {
        return isset($response['status']) && $status === $response['status'];
    }

	public static function access_token($log_error = true)
    {
        try {
            
            $ppcp = new PPCP();
                    
            $container = $ppcp->container();

            $PayPalBearer = new PayPalBearer(
                new Cache('ppcp-paypal-bearer'),
                $container->get('api.host'),
                $container->get('api.key'),
                $container->get('api.secret'),
                $container->get('woocommerce.logger.woocommerce'),
                $container->get('wcgateway.settings')
            );

            return $PayPalBearer->bearer()->token();

        } catch(\Exception $e) {

            if($log_error) {

                Exception::log($e);

            }

            return false;

        }
    }

	public static function request($api, $payload = [], $method = "GET", $additional_headers = [])
    {
        if(empty(self::env()['client_id']) || !$token = self::access_token()) {

            return false;
            
        }

        $args = [
            'method'  => $method,
            'timeout' => 10,
            'headers' => array_merge([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ], $additional_headers)
        ];

        $url = self::env()['paypal_api_url'] . $api;

        if($payload) {

            if("GET" === $method) {

                $url = add_query_arg($payload, $url);

            } else {

                $args['body'] = wp_json_encode($payload);

            }

        }

        $remote_response = wp_remote_request($url, $args);

        if (is_wp_error($remote_response)) {

            Exception::log("wp_remote_request() error: " . $remote_response->get_error_message() . " [$url]");

            return [
                'error' => $remote_response->get_error_message()
            ];

        }

        $response_array = json_decode(wp_remote_retrieve_body($remote_response), true);

        if (isset($response_array['message'], $response_array['details'][0]['description'])) {

            Exception::log("PayPal API Error: " . $response_array['message'] .  " - " . $response_array['details'][0]['description']);

            return [
                'error' => $response_array['name']
            ];
        }

        return [
            'response' => $response_array,
            'status'   => $remote_response['response']['code']
        ];
    }

    public static function valid_request($webhook_id)
    {
        $request_body = json_decode(file_get_contents('php://input'));

        if ($request_body === NULL && json_last_error() !== JSON_ERROR_NONE) {
            
            return false;

        }

        $headers = array_change_key_case(getallheaders(), CASE_UPPER);

        if(
            (!array_key_exists('PAYPAL-AUTH-ALGO', $headers)) ||
            (!array_key_exists('PAYPAL-TRANSMISSION-ID', $headers)) ||
            (!array_key_exists('PAYPAL-CERT-URL', $headers)) ||
            (!array_key_exists('PAYPAL-TRANSMISSION-SIG', $headers)) ||
            (!array_key_exists('PAYPAL-TRANSMISSION-TIME', $headers)) 
        )
        {
            return false;
        }

        $response = self::request(self::EP_VERIFY_SIG, [
            'auth_algo'         => $headers['PAYPAL-AUTH-ALGO'],
            'cert_url'          => $headers['PAYPAL-CERT-URL'],
            'transmission_id'   => $headers['PAYPAL-TRANSMISSION-ID'],
            'transmission_sig'  => $headers['PAYPAL-TRANSMISSION-SIG'],
            'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'],
            'webhook_id'        => $webhook_id,
            'webhook_event'     => $request_body
        ], "POST");

        $success = isset($response['response']['verification_status']) ? $response['response']['verification_status']: false;

        return $success === "SUCCESS";
    }
}
