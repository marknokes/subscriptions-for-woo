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

        $Plan = new Plan(get_the_ID());

        if($Plan->id) {

            $PluginMain = PluginMain::get_instance();

            $PluginMain::display_template("paypal-button", [
                'plan_id' => $Plan->id
            ]);

            wp_enqueue_script('paypal-sdk', $PluginMain->plugin_dir_url . "js/paypal-button.min.js", [], $PluginMain::plugin_data('Version'), true);

            wp_localize_script('paypal-sdk', 'ppsfwoo_paypal_ajax_var', [
                'client_id' => $PluginMain->client_id,
                'plan_id'   => $Plan->id,
                'redirect'  => get_permalink($PluginMain->ppsfwoo_thank_you_page_id)
            ]);
        }
    }

	public static function env()
    {
        $env = [
            'paypal_api_url' => 'https://api-m.sandbox.paypal.com',
            'paypal_url'     => 'https://sandbox.paypal.com',
            'client_id'      => '',
            'env'            => 'sandbox'
        ];

        $results = new DatabaseQuery("SELECT `option_value` FROM {$GLOBALS['wpdb']->base_prefix}options WHERE `option_name` = 'woocommerce-ppcp-settings'");

        $settings = isset($results->result[0]->option_value) ? unserialize($results->result[0]->option_value): false;

        if($settings) {

            if(isset($settings['sandbox_on'], $settings['client_id_sandbox']) && $settings['sandbox_on']) {

                $env['client_id'] = $settings['client_id_sandbox'];

            } else if(isset($settings['client_id_production'])) {

                $env['paypal_api_url'] = "https://api-m.paypal.com";

                $env['paypal_url'] = "https://www.paypal.com";

                $env['client_id'] = $settings['client_id_production'];

                $env['env'] = "production";

            }
        }

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

            return false;

        }

        $response_array = json_decode(wp_remote_retrieve_body($remote_response), true);

        if (isset($response_array['name']) && isset($response_array['message'])) {

            Exception::log("PayPal API Error: " . $response_array['name'] .  " - " . $response_array['message']);

            return false;
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
