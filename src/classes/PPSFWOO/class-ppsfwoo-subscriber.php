<?php

namespace PPSFWOO;

use PPSFWOO\Product,
    PPSFWOO\Plan,
    PPSFWOO\Order,
    PPSFWOO\Webhook,
    PPSFWOO\Database,
    PPSFWOO\Exception,
    PPSFWOO\PayPal;

class Subscriber
{
    public \stdClass $subscription;

    public $event_type,
           $user_id,
           $email,
           $first_name,
           $last_name,
           $address_line_1,
           $address_line_2,
           $city,
           $state,
           $postal_code,
           $country_code,
           $plan;

	public function __construct($subscription, $event_type = NULL)
    {
        $type = isset($subscription['response']) ? "response": "resource";

        $this->subscription = (object) $subscription[$type];

        $this->plan = new Plan($this->get_plan_id());

        $this->subscription->last_payment = !empty($this->subscription->billing_info['last_payment']['time'])
            ? new \DateTime($this->subscription->billing_info['last_payment']['time'])
            : NULL;

        $this->subscription->next_billing = !empty($this->subscription->billing_info['next_billing_time'])
            ? new \DateTime($this->subscription->billing_info['next_billing_time'])
            : NULL;

        $expiration = $this->add_frequency_to_last_payment(
            $this->subscription->last_payment,
            $this->plan->frequency
        );

        $this->subscription->expiration = $expiration
            ?? $this->subscription->next_billing
            ?? new \DateTime();
        
        $this->email            = $this->subscription->subscriber['email_address'];

        $this->first_name       = $this->subscription->subscriber['name']['given_name'];

        $this->last_name        = $this->subscription->subscriber['name']['surname'];

        $this->address_line_1   = $this->subscription->subscriber['shipping_address']['address']['address_line_1'];

        $this->address_line_2   = $this->subscription->subscriber['shipping_address']['address']['address_line_2'] ?? "";

        $this->city             = $this->subscription->subscriber['shipping_address']['address']['admin_area_2'];

        $this->state            = $this->subscription->subscriber['shipping_address']['address']['admin_area_1'];

        $this->postal_code      = $this->subscription->subscriber['shipping_address']['address']['postal_code'];

        $this->country_code     = $this->subscription->subscriber['shipping_address']['address']['country_code'];

        $this->event_type       = $event_type;

    }

    protected static function add_frequency_to_last_payment($datetime, $interval_type)
    {
        if(empty($datetime)) return NULL;

        $intervals = [
            'DAY'   => 'P1D',
            'WEEK'  => 'P1W',
            'MONTH' => 'P1M',
            'YEAR'  => 'P1Y'
        ];

        if (!isset($intervals[$interval_type])) {

            throw new \InvalidArgumentException("Invalid interval type. Use WEEK, MONTH, or YEAR.");

        }

        $new_date = clone $datetime;

        $new_date->add(new \DateInterval($intervals[$interval_type]));

        return $new_date;
    }

    public function get_id()
    {
        return $this->subscription->id ?? '';
    }

    public function get_plan_id()
    {
        return $this->subscription->plan_id ?? '';
    }

    public static function get($subs_id)
    {
        if(!isset($subs_id)) {

            return esc_attr("false");

        }
        
        $redirect = false;

        $query = "SELECT `wp_customer_id`
                       , `order_id`
                  FROM {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber
                  WHERE `id` = %s;";

        $results = new Database($query, [$subs_id]);

        $order_id = $results->result[0]->order_id ?? NULL;

        if ($order_id && $order = wc_get_order($order_id)) {

            $order->set_status('completed', 'Order complete.');

            $order->save();

            $redirect = $order->get_checkout_order_received_url();

            if ($user = get_user_by('id', $results->result[0]->wp_customer_id)) {

                wp_set_auth_cookie($user->ID);

            }

        } else if(
            false !== get_transient('ppsfwoo_customer_nonce')
            && $response = PayPal::request(PayPal::EP_SUBSCRIPTIONS . $subs_id)
        ) {

            if(self::is_active($response)) {

                delete_transient('ppsfwoo_customer_nonce');

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

        $order_id = Order::get_order_id_by_subscription_id($this->get_id());

        if(false === $order_id) {

            $result = new Database(
                "INSERT INTO {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber (
                    `id`,
                    `wp_customer_id`,
                    `paypal_plan_id`,
                    `event_type`
                )
                VALUES (%s,%d,%s,%s);",
                [
                    $this->get_id(),
                    $this->user_id,
                    $this->get_plan_id(),
                    $this->event_type
                ]
            );

        } else {

            $result = new Database(
                "UPDATE {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber SET
                    `paypal_plan_id` = %s,
                    `event_type` = %s,
                    `canceled_date` = NULL,
                    `expires` = NULL
                WHERE `id` = %s;",
                [
                    $this->get_plan_id(),
                    $this->event_type,
                    $this->get_id()
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
        $expiration = $this->subscription->expiration->format('Y-m-d');

        if($order_id = Order::get_order_id_by_subscription_id($this->get_id())) {
        
            Product::update_download_permissions($order_id, $expiration);

        }

        $result = new Database(
            "UPDATE {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber
             SET `event_type` = %s,
                 `canceled_date` = %s,
                 `expires` = %s
             WHERE `id` = %s;",
            [
                $this->event_type,
                gmdate("Y-m-d H:i:s"),
                $expiration,
                $this->get_id()
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

            new Database(
                "UPDATE {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber SET `order_id` = %d, `canceled_date` = NULL WHERE `id` = %s;",
                [
                    $order_id,
                    $this->get_id()
                ]
            );
        }

        return $this->get_id() ?? false;
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
