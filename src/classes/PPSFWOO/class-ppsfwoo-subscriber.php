<?php

namespace PPSFWOO;

class Subscriber
{
    /**
     * PayPal subscription.
     *
     * @var object
     */
    public \stdClass $subscription;

    /**
     * PayPal subscription webhook event type.
     *
     * @var string
     */
    public $event_type;

    /**
     * WordPress user id.
     *
     * @var int
     */
    public $user_id;

    /**
     * Subscriber email address.
     *
     * @var string
     */
    public $email;

    /**
     * Subscriber's first name.
     *
     * @var string
     */
    public $first_name;

    /**
     * Subscriber's last name.
     *
     * @var string
     */
    public $last_name;

    /**
     * Subscriber's address line 1.
     *
     * @var string
     */
    public $address_line_1;

    /**
     * Subscriber's address line 2.
     *
     * @var string
     */
    public $address_line_2;

    /**
     * Subscriber's city.
     *
     * @var string
     */
    public $city;

    /**
     * Subscriber's state.
     *
     * @var string
     */
    public $state;

    /**
     * Subscriber's zip code.
     *
     * @var string
     */
    public $postal_code;

    /**
     * Subscriber's country code.
     *
     * @var string
     */
    public $country_code;

    /**
     * Subscriber's chosen plan.
     *
     * @var object
     */
    public $plan;

    /**
     * Constructor for the Subscription class.
     *
     * @param array       $subscription the subscription data
     * @param null|string $event_type   the type of event
     */
    public function __construct($subscription, $event_type = null)
    {
        $type = isset($subscription['response']) ? 'response' : 'resource';

        $this->subscription = (object) $subscription[$type];

        $this->plan = new Plan($this->get_plan_id());

        $this->subscription->start_time = !empty($this->subscription->start_time)
            ? new \DateTime($this->subscription->start_time)
            : null;

        $this->subscription->last_payment = !empty($this->subscription->billing_info['last_payment']['time'])
            ? new \DateTime($this->subscription->billing_info['last_payment']['time'])
            : null;

        $this->subscription->next_billing = !empty($this->subscription->billing_info['next_billing_time'])
            ? new \DateTime($this->subscription->billing_info['next_billing_time'])
            : null;

        $expiration = $this->add_frequency(
            $this->subscription->last_payment,
            $this->plan->frequency
        );

        $trial_expiry = $this->get_trial_expiry($this->subscription, $this->plan);

        $this->subscription->expiration = $expiration
            ?? $this->subscription->next_billing
            ?? $trial_expiry
            ?? new \DateTime();

        $this->email = $this->subscription->subscriber['email_address'];

        $this->first_name = $this->subscription->subscriber['name']['given_name'];

        $this->last_name = $this->subscription->subscriber['name']['surname'];

        $this->address_line_1 = $this->subscription->subscriber['shipping_address']['address']['address_line_1'];

        $this->address_line_2 = $this->subscription->subscriber['shipping_address']['address']['address_line_2'] ?? '';

        $this->city = $this->subscription->subscriber['shipping_address']['address']['admin_area_2'];

        $this->state = $this->subscription->subscriber['shipping_address']['address']['admin_area_1'];

        $this->postal_code = $this->subscription->subscriber['shipping_address']['address']['postal_code'];

        $this->country_code = $this->subscription->subscriber['shipping_address']['address']['country_code'];

        $this->event_type = $event_type;
    }

    /**
     * Retrieves the ID of the subscription associated with this object.
     *
     * @return string the ID of the subscription, or an empty string if no subscription is associated
     */
    public function get_id()
    {
        return $this->subscription->id ?? '';
    }

    /**
     * Retrieves the plan ID associated with the subscription.
     *
     * @return string the plan ID, or an empty string if not available
     */
    public function get_plan_id()
    {
        return $this->subscription->plan_id ?? '';
    }

    /**
     * Retrieves the redirect URL for a subscription based on the given subscription ID.
     *
     * @param int $subs_id the subscription ID to retrieve the redirect URL for
     *
     * @return bool|string the redirect URL for the subscription, or false if the subscription ID is not set
     */
    public static function get($subs_id)
    {
        if (!isset($subs_id)) {
            return esc_attr('false');
        }

        $redirect = false;

        $query = "SELECT `wp_customer_id`
                       , `order_id`
                  FROM {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber
                  WHERE `id` = %s;";

        $results = new Database($query, [$subs_id]);

        $order_id = $results->result[0]->order_id ?? null;

        if ($order_id && $order = wc_get_order($order_id)) {
            $order->set_status('completed', 'Order complete.');

            $order->save();

            $redirect = $order->get_checkout_order_received_url();

            if ($user = get_user_by('id', $results->result[0]->wp_customer_id)) {
                wp_set_auth_cookie($user->ID);
            }
        } elseif (
            false !== get_transient('ppsfwoo_customer_nonce')
            && $response = PayPal::request(PayPal::EP_SUBSCRIPTIONS.$subs_id)
        ) {
            if (self::is_active($response)) {
                delete_transient('ppsfwoo_customer_nonce');

                (new self($response, Webhook::ACTIVATED))->subscribe();
            }
        }

        return $redirect ? esc_url($redirect) : esc_attr('false');
    }

    /**
     * Checks if the given response is active.
     *
     * @param array $response the response to check
     *
     * @return bool returns true if the response is active, false otherwise
     */
    public static function is_active($response)
    {
        return isset($response['response']['status']) && 'ACTIVE' === $response['response']['status'];
    }

    /**
     * Cancels the subscription and updates the expiration date and event type in the database.
     *
     * @return array an array containing any errors that occurred during the cancellation process
     */
    public function cancel()
    {
        $expiration = $this->subscription->expiration->format('Y-m-d');

        if ($order_id = Order::get_order_id_by_subscription_id($this->get_id())) {
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
                gmdate('Y-m-d H:i:s'),
                $expiration,
                $this->get_id(),
            ]
        );

        $errors = isset($result->result['error']) ? $result->result['error'] : false;

        return [
            'errors' => $errors,
        ];
    }

    /**
     * Subscribes the user to the service.
     *
     * @return false|int the ID of the subscriber if successful, false otherwise
     */
    public function subscribe()
    {
        $response = $this->insert();

        if (false === $response['errors'] && 'insert' === $response['action']) {
            $order_id = Order::insert($this);

            new Database(
                "UPDATE {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber SET `order_id` = %d, `canceled_date` = NULL WHERE `id` = %s;",
                [
                    $order_id,
                    $this->get_id(),
                ]
            );
        }

        return $this->get_id() ?? false;
    }

    /**
     * Gets the trial expiration from the subscription create time and plan data.
     *
     * @param DateTime $subscription the subscription object
     * @param string   $plan         the plan data
     *
     * @return null|DateTime the new date with the added interval, or null if no trial
     *
     * @throws Exception if no start time or create time
     */
    protected function get_trial_expiry($subscription, $plan)
    {
        $start_time = $subscription->start_time ?? $subscription->create_time ?? null;

        if (!$start_time) {
            throw new \Exception('Subscription missing start_time and create_time');
        }

        $start_time_clone = clone $start_time;

        $billing_cycles = $plan->billing_cycles ?? [];

        $trial_cycle = null;

        foreach ($billing_cycles as $cycle) {
            if (isset($cycle['tenure_type']) && 'TRIAL' === $cycle['tenure_type']) {
                $trial_cycle = $cycle;

                break;
            }
        }

        if (!$trial_cycle) {
            return null;
        }

        $unit = $trial_cycle['frequency']['interval_unit'] ?? null;

        $interval_count = (int) ($trial_cycle['frequency']['interval_count'] ?? 1);

        $total_cycles = (int) ($trial_cycle['total_cycles'] ?? 1);

        $total_intervals = $interval_count * $total_cycles;

        switch (strtoupper($unit)) {
            case 'DAY':
                $interval_spec = "P{$total_intervals}D";

                break;

            case 'WEEK':
                $interval_spec = "P{$total_intervals}W";

                break;

            case 'MONTH':
                $interval_spec = "P{$total_intervals}M";

                break;

            case 'YEAR':
                $interval_spec = "P{$total_intervals}Y";

                break;

            default:
                throw new \Exception("Unsupported interval unit in trial trial expiry");
        }

        $trial_end = $start_time_clone->add(new \DateInterval($interval_spec));

        return $trial_end->format(\DateTime::ATOM);
    }

    /**
     * Adds a specified interval to a given datetime and returns the new date.
     *
     * @param DateTime $datetime      the datetime to add the interval to
     * @param string   $interval_type the type of interval to add, must be DAY, WEEK, MONTH, or YEAR
     *
     * @return null|DateTime the new date with the added interval, or null if the datetime is empty
     *
     * @throws InvalidArgumentException if the interval type is not valid
     */
    protected static function add_frequency($datetime, $interval_type)
    {
        if (empty($datetime)) {
            return null;
        }

        $intervals = [
            'DAY' => 'P1D',
            'WEEK' => 'P1W',
            'MONTH' => 'P1M',
            'YEAR' => 'P1Y',
        ];

        if (!isset($intervals[$interval_type])) {
            throw new \InvalidArgumentException('Invalid interval type. Use WEEK, MONTH, or YEAR.');
        }

        $new_date = clone $datetime;

        $new_date->add(new \DateInterval($intervals[$interval_type]));

        return $new_date;
    }

    /**
     * Inserts a new subscriber into the database or updates an existing one.
     *
     * @return array an array containing any errors and the action performed (insert or update)
     */
    private function insert()
    {
        $wp_user = !empty($this->email) ? get_user_by('email', $this->email) : false;

        $this->user_id = $wp_user->ID ?? false;

        if (!$this->user_id) {
            $this->user_id = $this->create_wp_user();

            $this->create_woocommerce_customer();
        }

        $order_id = Order::get_order_id_by_subscription_id($this->get_id());

        if (false === $order_id) {
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
                    $this->event_type,
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
                    $this->get_id(),
                ]
            );

            Product::update_download_permissions($order_id);
        }

        $errors = isset($result->result['error']) ? $result->result['error'] : false;

        return [
            'errors' => $errors,
            'action' => false === $order_id ? 'insert' : 'update',
        ];
    }

    /**
     * Creates a new WordPress user with the given information.
     *
     * @return bool|int the user ID if successful, false if there was an error
     */
    private function create_wp_user()
    {
        $user_id = wp_insert_user([
            'user_login' => strtolower($this->first_name).'.'.strtolower($this->last_name),
            'user_pass' => wp_generate_password(12, false),
            'user_email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'role' => 'customer',
        ]);

        if (!is_wp_error($user_id)) {
            wp_new_user_notification($user_id, null, 'user');

            return $user_id;
        }

        Exception::log('Error creating user');

        return false;
    }

    /**
     * Creates a new WooCommerce customer with the provided user information.
     */
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
