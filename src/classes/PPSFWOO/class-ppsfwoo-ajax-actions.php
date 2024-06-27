<?php

namespace PPSFWOO;

use PPSFWOO\Webhook;
use PPSFWOO\PayPal;
use PPSFWOO\PluginMain;
use PPSFWOO\Subscriber;
use PPSFWOO\DatabaseQuery;
use PPSFWOO\Exception;

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

            Exception::log(__CLASS__ . "->$method does not exist.");

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
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $subs_id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])): NULL;

        return Subscriber::get($subs_id);

    }

    protected function log_paypal_buttons_error()
    {
        $logged_error = false;

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $message = isset($_POST['message'], $_POST['method']) && $_POST['method'] === __FUNCTION__ ? sanitize_text_field(wp_unslash($_POST['message'])): false;
        
        if($message) {

            Exception::log("PayPal subscription button error: $message");

            $logged_error = true;

        }

        return wp_json_encode(['logged_error' => $logged_error]);
    }
}
