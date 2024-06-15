<?php

namespace PPSFWOO;

use \WooCommerce\PayPalCommerce\PPCP;
use \WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use \WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;

class PayPal
{
	public static function env()
    {
        $env = [
            'paypal_api_url' => '',
            'paypal_url'     => '',
            'client_id'      => ''
        ];

        if($settings = get_option('woocommerce-ppcp-settings')) {

            if(isset($settings['sandbox_on']) && $settings['sandbox_on']) {

                $env['paypal_api_url'] = "https://api-m.sandbox.paypal.com";

                $env['paypal_url'] = "https://www.sandbox.paypal.com";

                $env['client_id'] = $settings['client_id_sandbox'];

            } else if(isset($settings['client_id_production'])) {

                $env['paypal_api_url'] = "https://api-m.paypal.com";

                $env['paypal_url'] = "https://www.paypal.com";

                $env['client_id'] = $settings['client_id_production'];

            }
        }

        return $env;
    }

	protected static function access_token()
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

            wc_get_logger()->error($e->getMessage(), ['source' => 'Subscriptions for Woo']);

            return false;

        }
    }

	public static function request($api, $payload = [], $method = "GET")
    {
        if(!$token = self::access_token()) {

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

        $response_body = wp_remote_retrieve_body($remote_response);

        $response_array = json_decode($response_body, true);

        if (isset($response_array['name']) && isset($response_array['message'])) {

            $error_name = $response_array['name'];

            $error_message = $response_array['message'];

            wc_get_logger()->error("PayPal API Error: $error_name - $error_message", ['source' => 'Subscriptions for Woo']);
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