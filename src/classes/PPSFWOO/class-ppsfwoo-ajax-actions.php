<?php

namespace PPSFWOO;

class AjaxActions
{
    // phpcs:disable
    /**
     * Handles all AJAX callbacks.
     */
    public function admin_ajax_callback()
    {
        $method = isset($_POST['method']) ? sanitize_text_field(wp_unslash($_POST['method'])) : '';

        if (method_exists($this, $method)) {
            echo call_user_func([$this, $method]);
        } elseif (has_action($method)) {
            do_action($method);
        } else {
            echo '';

            Exception::log(__CLASS__."->{$method} does not exist.");
        }

        wp_die();
    }

    // phpcs:enable
    /**
     * Retrieves a unique nonce for subscription ID redirects.
     *
     * @param bool $is_ajax whether the request is an AJAX request
     *
     * @return false|string the generated nonce or false if the request is not an AJAX request
     */
    public static function subs_id_redirect_nonce($is_ajax = true)
    {
        $nonce_name = '';

        $ppsfwoo_customer_nonce = get_transient('ppsfwoo_customer_nonce');

        if (!$ppsfwoo_customer_nonce) {
            $nonce_name = wp_generate_password(24, false);

            set_transient('ppsfwoo_customer_nonce', $nonce_name, 3600);
        } else {
            $nonce_name = $ppsfwoo_customer_nonce;
        }

        if ($is_ajax) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : null;

            $plan_id = get_post_meta($product_id, Product::get_plan_id_meta_key(), true) ?? null;

            $Plan = isset($product_id, $plan_id) ? new Plan($plan_id) : null;

            if (isset($Plan)) {
                $response = [
                    'nonce' => wp_create_nonce($nonce_name),
                    'plan_id' => $Plan->id,
                    'quantity_supported' => $Plan->quantity_supported,
                ];
            } else {
                $response = ['error' => true];
            }

            return wp_json_encode($response);
        }

        return $nonce_name;
    }

    /**
     * Retrieves a subscriber object based on the provided ID.
     *
     * @return null|Subscriber the subscriber object, or null if not found
     */
    protected function get_sub()
    {
        if (!isset($_POST['nonce'], $_POST['id']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ajax_get_sub')) {
            wp_die('Security check failed.');
        }

        return Subscriber::get(sanitize_text_field(wp_unslash($_POST['id'])));
    }

    /**
     * Logs any errors related to PayPal subscription buttons.
     *
     * @return string JSON-encoded string indicating whether an error was logged or not
     */
    protected function log_paypal_buttons_error()
    {
        $logged_error = false;

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $message = isset($_POST['message'], $_POST['method']) && __FUNCTION__ === $_POST['method'] ? sanitize_text_field(wp_unslash($_POST['message'])) : false;

        if ($message) {
            Exception::log("PayPal subscription button error: {$message}");

            $logged_error = true;
        }

        return wp_json_encode(['logged_error' => $logged_error]);
    }
}
