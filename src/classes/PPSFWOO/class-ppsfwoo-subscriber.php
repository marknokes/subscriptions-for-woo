<?php

namespace PPSFWOO;

use PPSFWOO\Product;
use PPSFWOO\Order;
use PPSFWOO\Webhook;
use PPSFWOO\DatabaseQuery;
use PPSFWOO\Exception;

class Subscriber
{
    public $event_type,
           $user_id,
           $subscription_id,
           $plan_id,
           $email,
           $first_name,
           $last_name,
           $address_line_1,
           $address_line_2,
           $city,
           $state,
           $postal_code,
           $country_code;

	public function __construct($request, $event_type = NULL)
    {
        $type = isset($request['response']) ? "response": "resource";

        $this->user_id          = NULL;

        $this->subscription_id  = $request[$type]['id'];

        $this->plan_id          = $request[$type]['plan_id'];

        $this->email            = $request[$type]['subscriber']['email_address'];

        $this->first_name       = $request[$type]['subscriber']['name']['given_name'];

        $this->last_name        = $request[$type]['subscriber']['name']['surname'];

        $this->address_line_1   = $request[$type]['subscriber']['shipping_address']['address']['address_line_1'];

        $this->address_line_2   = $request[$type]['subscriber']['shipping_address']['address']['address_line_2'] ?? "";

        $this->city             = $request[$type]['subscriber']['shipping_address']['address']['admin_area_2'];

        $this->state            = $request[$type]['subscriber']['shipping_address']['address']['admin_area_1'];

        $this->postal_code      = $request[$type]['subscriber']['shipping_address']['address']['postal_code'];

        $this->country_code     = $request[$type]['subscriber']['shipping_address']['address']['country_code'];

    	$this->event_type = $event_type;
    }

    public static function get($subs_id)
    {
        if(!isset($subs_id)) {

            return esc_attr("false");

        }

        if(!session_id()) session_start();
        
        $redirect = false;

        $results = new DatabaseQuery("SELECT `wp_customer_id`, `order_id` FROM {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber WHERE `id` = %s", [$subs_id]);

        $order_id = $results->result[0]->order_id ?? NULL;

        if ($order_id && $order = wc_get_order($order_id)) {

            $redirect = $order->get_checkout_order_received_url();

            if ($user = get_user_by('id', $results->result[0]->wp_customer_id)) {

                wp_set_auth_cookie($user->ID);

            }

        } else if(isset($_SESSION['ppsfwoo_customer_nonce']) && $response = PayPal::request("/v1/billing/subscriptions/$subs_id")) {

            if(self::is_active($response)) {

                unset($_SESSION['ppsfwoo_customer_nonce']);

                $Subscriber = new self($response, Webhook::ACTIVATED);

                $Subscriber->subscribe();
            }
        }

        return $redirect ? esc_url($redirect): esc_attr("false");
    }

    public static function is_active($response)
    {
        return isset($response['response']['status']) && "ACTIVE" === $response['response']['status'];
    }

    private function insert()
    {
        $wp_user = !empty($this->email) ? get_user_by('email', $this->email): false;

        $this->user_id = $wp_user->ID ?? false;

        if(!$this->user_id) {

            $this->user_id = $this->create_wp_user();

            $this->create_woocommerce_customer();

        }

        $order_id = Order::get_order_id_by_subscription_id($this->subscription_id);

        if(false === $order_id) {

            $result = new DatabaseQuery(
                "INSERT INTO {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber (
                    `id`,
                    `wp_customer_id`,
                    `paypal_plan_id`,
                    `event_type`
                )
                VALUES (%s,%d,%s,%s);",
                [
                    $this->subscription_id,
                    $this->user_id,
                    $this->plan_id,
                    $this->event_type
                ]
            );

        } else {

            $result = new DatabaseQuery(
                "UPDATE {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber SET
                    `paypal_plan_id` = %s,
                    `event_type` = %s,
                    `canceled_date` = NULL
                WHERE `id` = %s;",
                [
                    $this->plan_id,
                    $this->event_type,
                    $this->subscription_id
                ]
            );

            Product::update_download_permissions($order_id);
        }

        $errors = isset($result->result['error']) ? $result->result['error']: false;

        return [
            'errors' => $errors,
            'action' => false === $order_id ? 'insert': 'update'
        ];
    }

    public function cancel()
    {
        if($order_id = Order::get_order_id_by_subscription_id($this->subscription_id)) {

            $results = new DatabaseQuery(
                "SELECT DATE(`created` + INTERVAL 1 YEAR) AS `access_expires`
                 FROM {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber
                 WHERE `order_id` = %d;", [$order_id]
            );

            $access_expires = isset($results->result[0]->access_expires)
                ? $results->result[0]->access_expires
                : "1999-12-31";
        
            Product::update_download_permissions($order_id, $access_expires);

        }

        $result = new DatabaseQuery(
            "UPDATE {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber SET `event_type` = %s, `canceled_date` = %s WHERE `id` = %s;",
            [
                $this->event_type,
                gmdate("Y-m-d H:i:s"),
                $this->subscription_id
            ]
        );

        $errors = isset($result->result['error']) ? $result->result['error']: false;

        return [
            'errors' => $errors
        ];
    }

    public function subscribe()
    {
        $response = $this->insert();

        if(false === $response['errors'] && 'insert' === $response['action']) {

            $order_id = Order::insert($this);

            new DatabaseQuery(
                "UPDATE {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber SET `order_id` = %d, `canceled_date` = NULL WHERE `id` = %s;",
                [
                    $order_id,
                    $this->subscription_id
                ]
            );
        }

        return $this->subscription_id ?? false;
    }

    private function create_wp_user()
    {
        $user_id = wp_insert_user([
            'user_login' => strtolower($this->first_name) . '.' . strtolower($this->last_name),
            'user_pass'  => wp_generate_password(12, false),
            'user_email' => $this->email,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'role'       => 'customer'
        ]);

        if (!is_wp_error($user_id)) {

            wp_new_user_notification($user_id, null, 'user');

            return $user_id;

        } else {

            Exception::log("Error creating user");

            return false;
        }
    }

    private function create_woocommerce_customer()
    {
        $customer = new \WC_Customer($this->user_id);

        $customer->set_billing_first_name($this->first_name);

        $customer->set_billing_last_name($this->last_name);

        $customer->set_billing_email($this->email);

        $customer->set_billing_address_1($this->address_line_1);

        $customer->set_billing_address_2($this->address_line_2);

        $customer->set_billing_city($this->city);

        $customer->set_billing_state($this->state);

        $customer->set_billing_postcode($this->postal_code);

        $customer->set_billing_country($this->country_code);

        $customer->save();
    }
}
