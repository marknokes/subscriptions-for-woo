<?php

namespace PPSFWOO;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\PPCP;

class PayPal
{
    /**
     * PayPal enpoint for subscriptions.
     *
     * @var string
     */
    public const EP_SUBSCRIPTIONS = '/v1/billing/subscriptions/';

    /**
     * PayPal enpoint for plans.
     *
     * @var string
     */
    public const EP_PLANS = '/v1/billing/plans/';

    /**
     * PayPal enpoint for products.
     *
     * @var string
     */
    public const EP_PRODUCTS = '/v1/catalogs/products/';

    /**
     * PayPal enpoint for webhooks.
     *
     * @var string
     */
    public const EP_WEBHOOKS = '/v1/notifications/webhooks/';

    /**
     * PayPal enpoint for verifying a webhook signature.
     *
     * @var string
     */
    public const EP_VERIFY_SIG = '/v1/notifications/verify-webhook-signature';

    /**
     * Displays a PayPal button for a specific product, allowing customers to subscribe to a subscription product.
     *
     * @param null|int $product_id  The ID of the product to display the button for. Defaults to the current product ID if not provided.
     * @param mixed    $button_text
     * @param mixed    $style
     */
    public static function button($product_id = null, $button_text = '', $style = '')
    {
        $product_id = !empty($product_id) ? $product_id : get_the_ID();

        $button_text = !empty($button_text) ? $button_text : PluginMain::get_option('ppsfwoo_button_text');

        $product = wc_get_product($product_id);

        if ($product && !$product->is_type(Product::TYPE)) {
            return;
        }

        $client_id = self::env()['client_id'];

        wp_enqueue_script(
            'ppsfwoo-paypal-sdk',
            "https://www.paypal.com/sdk/js?client-id={$client_id}&vault=true&intent=subscription",
            [],
            null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
            true
        );

        wp_enqueue_script(
            'ppsfwoo-paypal-button',
            plugin_dir_url(PPSFWOO_PLUGIN_PATH).'js/paypal-button.min.js',
            ['ppsfwoo-paypal-sdk'],
            PluginMain::plugin_data('Version'),
            true
        );

        wp_add_inline_script('ppsfwoo-paypal-button', "
            document.getElementById('ppsfwoo-subscribe-button-{$product_id}')
                .addEventListener('click', function() {
                    this.style.display = 'none';
                    document.getElementById('lds-ellipsis-{$product_id}').style.setProperty('display', 'block');
                    ppsfwooInitializePayPalSubscription({$product_id}, this);
                });
        ");

        wp_localize_script('ppsfwoo-paypal-button', 'ppsfwoo_paypal_ajax_var', [
            'redirect' => get_permalink(PluginMain::get_option('ppsfwoo_thank_you_page_id')),
        ]);

        PluginMain::display_template('paypal-button', [
            'button_text' => $button_text,
            'product_id' => $product_id,
            'style' => $style,
        ]);
    }

    /**
     * Returns an array of environment settings for the PayPal API based on the current settings in the container.
     *
     * @return array An array containing the following keys:
     *               - paypal_api_url: The URL for the PayPal API, either for the sandbox or production environment.
     *               - paypal_url: The URL for the PayPal website, either for the sandbox or production environment.
     *               - client_id: The client ID for the PayPal API, if available.
     *               - env: The current environment, either 'sandbox' or 'production'.
     */
    public static function env()
    {
        try {
            $container = PPCP::container();
            $settings = $container->get('wcgateway.settings');
            $sandbox_on = $settings->has('sandbox_on') && $settings->get('sandbox_on');

            return [
                'paypal_api_url' => $sandbox_on ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com',
                'paypal_url' => $sandbox_on ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com',
                'client_id' => $settings->has('client_id') && $settings->get('client_id') ? $settings->get('client_id') : '',
                'env' => $sandbox_on ? 'sandbox' : 'production',
            ];
        } catch (\Exception $e) {
            Exception::log($e);
        }
    }

    /**
     * Checks if the status of the given response matches the specified status.
     *
     * @param array $response the response to check
     * @param int   $status   the status to compare against
     *
     * @return bool true if the response status matches the specified status, false otherwise
     */
    public static function response_status_is($response, $status)
    {
        return isset($response['status']) && $status === $response['status'];
    }

    /**
     * Retrieves the access token from PayPal for use in API requests.
     *
     * @param bool $log_error whether to log any errors encountered
     *
     * @return bool|string the access token, or false if an error occurred
     */
    public static function access_token($log_error = true)
    {
        try {
            $container = PPCP::container();

            $PayPalBearer = new PayPalBearer(
                new Cache('ppcp-paypal-bearer'),
                $container->get('api.host'),
                $container->get('api.key'),
                $container->get('api.secret'),
                $container->get('woocommerce.logger.woocommerce'),
                $container->get('wcgateway.settings')
            );

            return $PayPalBearer->bearer()->token();
        } catch (\Exception $e) {
            if ($log_error) {
                Exception::log($e);
            }

            return false;
        }
    }

    /**
     * Sends a request to the PayPal API.
     *
     * @param string $api                the API endpoint to send the request to
     * @param array  $payload            (Optional) The data to be sent with the request
     * @param string $method             (Optional) The HTTP method to use for the request
     * @param array  $additional_headers (Optional) Additional headers to be included in the request
     *
     * @return array|bool an array containing the response and status code, or false if there was an error
     */
    public static function request($api, $payload = [], $method = 'GET', $additional_headers = [])
    {
        if (empty(self::env()['client_id']) || !$token = self::access_token()) {
            return false;
        }

        $args = [
            'method' => $method,
            'timeout' => 10,
            'headers' => array_merge([
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
            ], $additional_headers),
        ];

        $url = self::env()['paypal_api_url'].$api;

        if ($payload) {
            if ('GET' === $method) {
                $url = add_query_arg($payload, $url);
            } else {
                $args['body'] = wp_json_encode($payload);
            }
        }

        $remote_response = wp_remote_request($url, $args);

        if (is_wp_error($remote_response)) {
            Exception::log('wp_remote_request() error: '.$remote_response->get_error_message()." [{$url}]");

            return [
                'error' => $remote_response->get_error_message(),
            ];
        }

        $response_array = json_decode(wp_remote_retrieve_body($remote_response), true);

        if (isset($response_array['message'], $response_array['details'][0]['description'])) {
            Exception::log('PayPal API Error: '.$response_array['message'].' - '.$response_array['details'][0]['description']);

            return [
                'error' => $response_array['name'],
            ];
        }

        return [
            'response' => $response_array,
            'status' => $remote_response['response']['code'],
        ];
    }

    /**
     * Validates a webhook request from PayPal.
     *
     * @param string $webhook_id the ID of the webhook to validate against
     *
     * @return bool returns true if the request is valid, false otherwise
     */
    public static function valid_request($webhook_id)
    {
        $request_body = json_decode(file_get_contents('php://input'));

        if (null === $request_body && JSON_ERROR_NONE !== json_last_error()) {
            return false;
        }

        $headers = array_change_key_case(getallheaders(), CASE_UPPER);

        if (
            (!array_key_exists('PAYPAL-AUTH-ALGO', $headers))
            || (!array_key_exists('PAYPAL-TRANSMISSION-ID', $headers))
            || (!array_key_exists('PAYPAL-CERT-URL', $headers))
            || (!array_key_exists('PAYPAL-TRANSMISSION-SIG', $headers))
            || (!array_key_exists('PAYPAL-TRANSMISSION-TIME', $headers))
        ) {
            return false;
        }

        $response = self::request(self::EP_VERIFY_SIG, [
            'auth_algo' => $headers['PAYPAL-AUTH-ALGO'],
            'cert_url' => $headers['PAYPAL-CERT-URL'],
            'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'],
            'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'],
            'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'],
            'webhook_id' => $webhook_id,
            'webhook_event' => $request_body,
        ], 'POST');

        $success = isset($response['response']['verification_status']) ? $response['response']['verification_status'] : false;

        return 'SUCCESS' === $success;
    }
}
