<?php

namespace PPSFWOO;

use PPSFWOO\Webhook;
use PPSFWOO\PayPal;
use PPSFWOO\PluginMain;

class AjaxActions
{
    private static $instance = NULL;

    public function __construct()
    {
    }

    public static function get_instance()
    {
        if (self::$instance === null) {

            self::$instance = new self();

        }

        return self::$instance;
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
        return Webhook::get_instance()->list();
    }

    public function subs_id_redirect_nonce()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing 
        $is_ajax = isset($_POST['action'], $_POST['method']) && $_POST['method'] === __FUNCTION__;

        $nonce_name = "";

        if(!session_id()) session_start();

        if (!isset($_SESSION['ppsfwoo_customer_nonce'])) {

            $nonce_name = $_SESSION['ppsfwoo_customer_nonce'] = wp_generate_password(24, false);

        } else {

            $nonce_name = $_SESSION['ppsfwoo_customer_nonce'];

        }

        return $is_ajax ? wp_json_encode(['nonce' => wp_create_nonce($nonce_name)]): $nonce_name;
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
