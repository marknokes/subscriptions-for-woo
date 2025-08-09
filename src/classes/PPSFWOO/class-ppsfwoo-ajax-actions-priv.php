<?php

namespace PPSFWOO;

use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\PPCP;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\ResubscribeEndpoint;

class AjaxActionsPriv extends AjaxActions
{
    /**
     * Refreshes all plans.
     *
     * @return string JSON-encoded response with success and length properties
     */
    public static function refresh_plans()
    {
        $wait = 10;

        if (true === get_transient('ppsfwoo_refresh_plans_ran')) {
            return wp_json_encode([
                'error' => 'Please wait at least '.absint($wait).' seconds and try again.',
            ]);
        }

        if (defined('\DOING_AJAX')
            && \DOING_AJAX
            && !is_super_admin()
            && !current_user_can('ppsfwoo_manage_settings')
        ) {
            return wp_json_encode([
                'error' => 'Insufficient permissions.',
            ]);
        }

        set_transient('ppsfwoo_refresh_plans_ran', true, $wait);

        $Plan = new Plan();

        $plans = $Plan->refresh_all();

        return wp_json_encode([
            'success' => !empty($plans),
            'length' => sizeof($plans),
        ]);
    }

    /**
     * Modifies the current plan.
     *
     * @return string JSON-encoded response from the Plan class
     */
    protected function modify_plan()
    {
        if (!is_super_admin() && !current_user_can('ppsfwoo_manage_settings')) {
            return wp_json_encode([
                'error' => 'Insufficient permissions.',
            ]);
        }

        $Plan = new Plan();

        $response = $Plan->modify_plan();

        if (isset($response['success']) && true === $response['success']) {
            $Plan->refresh_all();
        }

        return wp_json_encode($response);
    }

    /**
     * Searches for subscribers by email address.
     *
     * @return string JSON-encoded response containing either an error message or HTML of subscriber data
     */
    protected function search_subscribers()
    {
        if (!is_super_admin() && !current_user_can('ppsfwoo_manage_settings')) {
            return wp_json_encode([
                'error' => 'Insufficient permissions.',
            ]);
        }

        if (!isset($_POST['search_by_email']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['search_by_email'])), 'search_by_email')) {
            return wp_json_encode([
                'error' => 'Security check failed.',
            ]);
        }

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';

        if (empty($email)) {
            return wp_json_encode([
                'error' => 'Email address is empty.',
            ]);
        }

        $data = PluginMain::get_instance()->subscriber_table_options_page($email);

        if (!$data['num_subs']) {
            return wp_json_encode([
                'error' => 'No subscribers with that email address.',
            ]);
        }

        return wp_json_encode([
            'html' => $data['html'],
        ]);
    }

    /**
     * Resubscribe webhooks.
     */
    protected function resubscribe_webhooks()
    {
        try {
            $container = PPCP::container();
            $registrar = $container->get('webhook.registrar');
            $request_data = new RequestData();
            $endpoint = new ResubscribeEndpoint($registrar, $request_data);
            $endpoint->handle_request();
        } catch (Exception $e) {
            Exception::log($e);
        }
    }
}
