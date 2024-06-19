<?php

namespace PPSFWOO;

use PPSFWOO\Webhook;
use PPSFWOO\PayPal;
use PPSFWOO\PluginMain;
use PPSFWOO\Subscriber;
use PPSFWOO\DatabaseQuery;

class AjaxActions
{
	public function admin_ajax_callback()
    {  
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $method = isset($_POST['method']) ? sanitize_text_field(wp_unslash($_POST['method'])): "";

        if(method_exists($this, $method)) {
            
            echo call_user_func([$this, $method]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        } else {
            
            echo ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

            error_log(__CLASS__ . "->$method does not exist.");

        }

        wp_die();
    }

    public static function subs_id_redirect_nonce()
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
        if(!session_id()) session_start();
        
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $subs_id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])): NULL;

        $redirect = false;

        if(!isset($subs_id)) {

            return "";

        }

        $results = new DatabaseQuery("SELECT `wp_customer_id`, `order_id` FROM {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber WHERE `id` = %s", [$subs_id]);

        $order_id = $results->result[0]->order_id ?? NULL;

        if ($order_id && $order = wc_get_order($order_id)) {

            $redirect = $order->get_checkout_order_received_url();

            if ($user = get_user_by('id', $results->result[0]->wp_customer_id)) {

                wp_set_auth_cookie($user->ID);

            }

        } else if(isset($_SESSION['ppsfwoo_customer_nonce']) && $response = PayPal::request("/v1/billing/subscriptions/$subs_id")) {

            if(Subscriber::is_active($response)) {

                unset($_SESSION['ppsfwoo_customer_nonce']);

                $Subscriber = new Subscriber($response, Webhook::ACTIVATED);

                $Subscriber->subscribe();
            }
        }

        return $redirect ? esc_url($redirect): esc_attr("false");
    }

    protected function log_paypal_buttons_error()
    {
        $logged_error = false;

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $message = isset($_POST['message'], $_POST['method']) && $_POST['method'] === __FUNCTION__ ? sanitize_text_field(wp_unslash($_POST['message'])): false;
        
        if($message) {

            wc_get_logger()->error("PayPal subscription button error: $message", ['source' => PluginMain::get_instance()::plugin_data('Name')]);

            $logged_error = true;

        }

        return wp_json_encode(['logged_error' => $logged_error]);
    }
}
