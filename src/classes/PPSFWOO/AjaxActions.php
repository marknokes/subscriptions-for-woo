<?php

namespace PPSFWOO;

use PPSFWOO\Webhook;
use PPSFWOO\PayPal;
use PPSFWOO\PluginMain;

class AjaxActions
{
    public function __construct()
    {
    }

	public function admin_ajax_callback()
    {  
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $method = isset($_POST['method']) ? sanitize_text_field(wp_unslash($_POST['method'])): "";

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo method_exists($this, $method) ? call_user_func([$this, $method]): "";

        wp_die();
    }

    protected function list_plans()
    {
        $PluginMain = PluginMain::get_instance();

        return wp_json_encode($PluginMain->ppsfwoo_plans);
    }

    protected function list_webhooks()
    {
        $Webhook = new Webhook();

        return $Webhook->list();
    }

    protected function get_sub()
    {
        global $wpdb;

        if(!session_id()) session_start();
        
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $subs_id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])): NULL;

        $redirect = false;

        if(!isset($subs_id)) {

            return "";

        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT `wp_customer_id`, `order_id` FROM {$wpdb->prefix}ppsfwoo_subscriber WHERE `id` = %s",
                $subs_id
            )
        );

        $order_id = $results[0]->order_id ?? NULL;

        if ($order_id && $order = wc_get_order($order_id)) {

            $redirect = $order->get_checkout_order_received_url();

            if ($user = get_user_by('id', $results[0]->wp_customer_id)) {

                wp_set_auth_cookie($user->ID);

            }

        } else if(isset($_SESSION['ppsfwoo_customer_nonce']) && $response = PayPal::request("/v1/billing/subscriptions/$subs_id")) {

            $PluginMain = PluginMain::get_instance();

            if(true === $PluginMain->activate_subscriber($response)) {

                unset($_SESSION['ppsfwoo_customer_nonce']);

            }
        }

        return $redirect ? esc_url($redirect): esc_attr("false");
    }

    protected function refresh_plans()
    {
        $success = "false";

        if($plan_data = PayPal::request("/v1/billing/plans")) {

            $plans = [];

            if(isset($plan_data['response']['plans'])) {

                $products = [];

                foreach($plan_data['response']['plans'] as $plan)
                {
                    if($plan['status'] !== "ACTIVE") {

                        continue;

                    }

                    $plan_freq = PayPal::request("/v1/billing/plans/{$plan['id']}");

                    if(!in_array($plan['product_id'], array_keys($products))) {
                    
                        $product_data = PayPal::request("/v1/catalogs/products/{$plan['product_id']}");

                        $product_name = $product_data['response']['name'];

                        $products[$plan['product_id']] = $product_name;

                    } else {

                        $product_name = $products[$plan['product_id']];
                    }

                    $plans[$plan['id']] = [
                        'plan_name'     => $plan['name'],
                        'product_name'  => $product_name,
                        'frequency'     => $plan_freq['response']['billing_cycles'][0]['frequency']['interval_unit']
                    ];
                }
            
                update_option('ppsfwoo_plans', $plans);

                $success = "true";
            }
        }

        return $success;
    }

    protected function search_subscribers()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])): "";

        if(empty($email)) { 

            return "";

        }

        $PluginMain = PluginMain::get_instance();

        if(!$PluginMain->display_subs($email)) {

            return "false";

        }
    }
}