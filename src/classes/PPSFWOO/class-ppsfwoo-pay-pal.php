<?php

namespace PPSFWOO;

use PPSFWOO\PluginMain;
use PPSFWOO\Plan;
use PPSFWOO\Exception;
use WooCommerce\PayPalCommerce\PPCP;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;

class PayPal
{
    public static function button()
    {
        global $product;

        if(!$product->is_type('ppsfwoo')) {

            return;

        }

        $PluginMain = PluginMain::get_instance();

        $PluginMain::display_template("paypal-button");

        wp_enqueue_script('paypal-sdk', $PluginMain->plugin_dir_url . "js/paypal-button.min.js", [], $PluginMain::plugin_data('Version'), true);

        wp_localize_script('paypal-sdk', 'ppsfwoo_paypal_ajax_var', [
            'product_id' => get_the_ID(),
            'redirect'   => get_permalink($PluginMain->ppsfwoo_thank_you_page_id)
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
            'paypal_url'     => $sandbox_on ? 'https://sandbox.paypal.com': 'https://www.paypal.com',
            'client_id'      => $settings->has('client_id') && $settings->get('client_id') ? $settings->get('client_id'): '',
            'env'            => $sandbox_on ? 'sandbox': 'production'
        ];

        return $env;
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

	public static function request($api, $payload = [], $method = "GET")
    {
        if(empty(self::env()['client_id']) || !$token = self::access_token()) {

            return false;
            
        }

        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ]
        ];

        if($payload) {

            $args['body'] = wp_json_encode($payload);

        }

        $url = self::env()['paypal_api_url'] . $api;

        $remote_response = wp_remote_request($url, $args);

        if (is_wp_error($remote_response)) {

            Exception::log("wp_remote_request() error: " . $remote_response->get_error_message() . " [$url]");

            return [
                'error' => $remote_response->get_error_message()
            ];

        }

        $response_array = json_decode(wp_remote_retrieve_body($remote_response), true);

        if (isset($response_array['name']) && isset($response_array['message'])) {

            Exception::log("PayPal API Error: " . $response_array['name'] .  " - " . $response_array['message']);

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
        $request_body = file_get_contents('php://input');

        if(!$request_body) {

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

        $response = self::request("/v1/notifications/verify-webhook-signature", [
            'auth_algo'         => $headers['PAYPAL-AUTH-ALGO'],
            'cert_url'          => $headers['PAYPAL-CERT-URL'],
            'transmission_id'   => $headers['PAYPAL-TRANSMISSION-ID'],
            'transmission_sig'  => $headers['PAYPAL-TRANSMISSION-SIG'],
            'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'],
            'webhook_id'        => $webhook_id,
            'webhook_event'     => json_decode($request_body)
        ], "POST");

        $success = isset($response['response']['verification_status']) ? $response['response']['verification_status']: false;

        return $success === "SUCCESS";
    }
}
