<?php

namespace PPSFWOO;

use PPSFWOO\Product,
    PPSFWOO\Subscriber,
    PPSFWOO\Exception;

class AjaxActions
{
    // phpcs:disable
	public function admin_ajax_callback()
    {  
        $method = isset($_POST['method']) ? sanitize_text_field(wp_unslash($_POST['method'])): "";

        if(method_exists($this, $method)) {
            
            echo call_user_func([$this, $method]);

        } else if(has_action($method)) {

            do_action($method);

        } else {
            
            echo "";

            Exception::log(__CLASS__ . "->$method does not exist.");

        }

        wp_die();
    }
    // phpcs:enable

    public static function subs_id_redirect_nonce($is_ajax = true)
    {
        $nonce_name = "";

        $ppsfwoo_customer_nonce = get_transient('ppsfwoo_customer_nonce');

        if (!$ppsfwoo_customer_nonce) {

            $nonce_name = wp_generate_password(24, false);

            set_transient('ppsfwoo_customer_nonce', $nonce_name, 3600);

        } else {

            $nonce_name = $ppsfwoo_customer_nonce;

        }

        if($is_ajax) {

            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']): NULL;

            $plan_id = get_post_meta($product_id, Product::get_plan_id_meta_key(), true) ?? NULL;

            $Plan = isset($product_id, $plan_id) ? new Plan($plan_id): NULL;

            if(isset($Plan)) {

                $response = [
                    'nonce'     => wp_create_nonce($nonce_name),
                    'plan_id'   => $Plan->id,
                    'quantity_supported' => $Plan->quantity_supported
                ];

            } else {

                $response = ['error' => true];

            }

            return wp_json_encode($response);

        } else {

            return $nonce_name;

        }
    }

    protected function get_sub()
    {
        if(!isset($_POST['nonce'], $_POST['id']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ajax_get_sub')) {

            wp_die('Security check failed.');

        }

        return Subscriber::get(sanitize_text_field(wp_unslash($_POST['id'])));

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
