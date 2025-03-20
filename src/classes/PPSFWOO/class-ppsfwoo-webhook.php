<?php

namespace PPSFWOO;

class Webhook
{
    /**
     * PayPal webhook event type indicating that a subscription is activated.
     *
     * @var string
     */
    public const ACTIVATED = 'BILLING.SUBSCRIPTION.ACTIVATED';

    /**
     * PayPal webhook event type indicating that a subscription is expired.
     *
     * @var string
     */
    public const EXPIRED = 'BILLING.SUBSCRIPTION.EXPIRED';

    /**
     * PayPal webhook event type indicating that a subscription is cancelled.
     *
     * @var string
     */
    public const CANCELLED = 'BILLING.SUBSCRIPTION.CANCELLED';

    /**
     * PayPal webhook event type indicating that a subscription is paused.
     *
     * @var string
     */
    public const SUSPENDED = 'BILLING.SUBSCRIPTION.SUSPENDED';

    /**
     * PayPal webhook event type indicating that a subscription payment failed.
     *
     * @var string
     */
    public const PAYMENT_FAILED = 'BILLING.SUBSCRIPTION.PAYMENT.FAILED';

    /**
     * PayPal webhook event type indicating that a subscription plan is created.
     *
     * @var string
     */
    public const BP_CREATED = 'BILLING.PLAN.CREATED';

    /**
     * PayPal webhook event type indicating that a subscription plan is activated.
     *
     * @var string
     */
    public const BP_ACTIVATED = 'BILLING.PLAN.ACTIVATED';

    /**
     * PayPal webhook event type indicating that a subscription plan price is updated.
     *
     * @var string
     */
    public const BP_PRICE_CHANGE_ACTIVATED = 'BILLING.PLAN.PRICING-CHANGE.ACTIVATED';

    /**
     * PayPal webhook event type indicating that a subscription plan is deactivated.
     *
     * @var string
     */
    public const BP_DEACTIVATED = 'BILLING.PLAN.DEACTIVATED';

    /**
     * PayPal webhook event type indicating that a subscription plan is updated.
     *
     * @var string
     */
    public const BP_UPDATED = 'BILLING.PLAN.UPDATED';

    /**
     * WordPress api namespace for the plugin to receive webhooks.
     *
     * @var string
     */
    public static $api_namespace = 'subscriptions-for-woo/v1';

    /**
     * WordPress api endpoint for the plugin to receive webhooks.
     *
     * @var string
     */
    public static $endpoint = '/incoming';

    /**
     * url of the WordPress site.
     *
     * @var object
     */
    public $site_url;

    /**
     * Listen address - Concatenation of $site_url, $api_namespace, and $endpoint.
     *
     * @var string
     */
    public $listen_address;

    /**
     * PayPal webhook id.
     *
     * @var string
     */
    public $webhook_id;

    /**
     * Array of subscribed webhooks.
     *
     * @var array
     */
    public $subscribed_webhooks;

    /**
     * Class instance.
     *
     * @var object
     */
    private static $instance;

    /**
     * Constructor for the class.
     *
     * Initializes the site URL, listen address, webhook ID, and subscribed webhooks for the class.
     */
    public function __construct()
    {
        $this->site_url = network_site_url('', 'https');

        $this->listen_address = $this->site_url.'/wp-json/'.self::$api_namespace.self::$endpoint;

        $this->webhook_id = PluginMain::get_option('ppsfwoo_webhook_id');

        $this->subscribed_webhooks = PluginMain::get_option('ppsfwoo_subscribed_webhooks');
    }

    /**
     * Returns an instance of the current class.
     *
     * @return self the instance of the current class
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Handles a REST request.
     *
     * @param \WP_REST_Request $request the request object
     *
     * @return \WP_REST_Response the response object
     */
    public function handle_request(\WP_REST_Request $request)
    {
        $response = new \WP_REST_Response();

        if (\WP_REST_Server::READABLE === $request->get_method()) {
            $response->set_status(200);

            return $response;
        }

        if (!PayPal::valid_request($this->id())) {
            $response->set_status(403);

            return $response;
        }

        $event_type = $request['event_type'] ?? '';

        switch ($event_type) {
            case self::ACTIVATED:
                (new Subscriber($request, $event_type))->subscribe();

                break;

            case self::EXPIRED:
            case self::CANCELLED:
            case self::SUSPENDED:
            case self::PAYMENT_FAILED:
                (new Subscriber($request, $event_type))->cancel();

                break;

            case self::BP_UPDATED:
            case self::BP_ACTIVATED:
            case self::BP_DEACTIVATED:
            case self::BP_PRICE_CHANGE_ACTIVATED:
                do_action('ppsfwoo_refresh_plans');

                break;
        }

        $response->set_status(200);

        return $response;
    }

    /**
     * Initializes the REST API for the plugin.
     */
    public function rest_api_init()
    {
        register_rest_route(
            self::$api_namespace,
            self::$endpoint,
            [
                [
                    'methods' => \WP_REST_Server::CREATABLE,
                    'permission_callback' => '__return_true',
                    'callback' => [$this, 'handle_request'],
                    'args' => [
                        'event_type' => [
                            'validate_callback' => function ($param, $request, $key) {
                                return in_array(
                                    $param,
                                    array_column($this->get_event_types(), 'name')
                                );
                            },
                        ],
                    ],
                ],
                [
                    'methods' => \WP_REST_Server::READABLE,
                    'permission_callback' => '__return_true',
                    'callback' => [$this, 'handle_request'],
                ],
            ]
        );
    }

    /**
     * Returns the webhook ID.
     *
     * @return int the webhook ID
     */
    public function id()
    {
        return $this->webhook_id;
    }

    /**
     * Returns the listen address of the current object.
     *
     * @return string the listen address
     */
    public function listen_address()
    {
        return $this->listen_address;
    }

    /**
     * Resubscribes the webhook by deleting any existing webhooks with the same listen address and creating a new one.
     *
     * @return bool true if the webhook was successfully resubscribed, false otherwise
     */
    public function resubscribe()
    {
        $created = false;

        if ($webhooks = PayPal::request(PayPal::EP_WEBHOOKS)) {
            if (isset($webhooks['response']['webhooks'])) {
                foreach ($webhooks['response']['webhooks'] as $key => $webhook) {
                    if ($this->listen_address() === $webhooks['response']['webhooks'][$key]['url']) {
                        $this->delete($webhooks['response']['webhooks'][$key]['id']);
                    }
                }

                $created = $this->create();
            }
        }

        return $created;
    }

    /**
     * Retrieves a list of subscribed webhooks for the current user.
     *
     * @return array|bool returns an array of subscribed webhooks if successful, or false if there are no subscribed webhooks or an error occurs
     */
    public function list()
    {
        if ($this->id() && is_array($this->subscribed_webhooks) && sizeof($this->subscribed_webhooks)) {
            $subscribed = $this->subscribed_webhooks;
        } elseif ($webhooks = PayPal::request(PayPal::EP_WEBHOOKS)) {
            $subscribed = [];

            if (isset($webhooks['response']['webhooks'])) {
                foreach ($webhooks['response']['webhooks'] as $key => $webhook) {
                    if ($webhook['id'] === $this->id()) {
                        $subscribed = $webhook['event_types'];
                    }
                }
            }

            PluginMain::clear_option_cache('ppsfwoo_subscribed_webhooks');

            update_option('ppsfwoo_subscribed_webhooks', $subscribed);
        }

        return $subscribed ?? false;
    }

    /**
     * Creates a new webhook for PayPal events.
     *
     * @return array|bool returns the response from PayPal if successful, or false if there was an error
     */
    public function create()
    {
        $webhook_id = '';

        $event_types = [];

        try {
            $response = PayPal::request(PayPal::EP_WEBHOOKS, [
                'url' => $this->listen_address(),
                'event_types' => $this->get_event_types(),
            ], 'POST');

            if (isset($response['response']['id'])) {
                $webhook_id = $response['response']['id'];

                $event_types = $response['response']['event_types'];
            } elseif (!PayPal::response_status_is($response, 201)) {
                $response = $this->patch(true);

                $webhook_id = $response['response']['id'];

                $event_types = $response['response']['event_types'];
            }

            PluginMain::clear_option_cache('ppsfwoo_webhook_id');

            update_option('ppsfwoo_webhook_id', $webhook_id);

            PluginMain::clear_option_cache('ppsfwoo_subscribed_webhooks');

            update_option('ppsfwoo_subscribed_webhooks', $event_types);

            $this->patch();

            return $response['response'] ?? false;
        } catch (\Exception $e) {
            Exception::log($e);

            return false;
        }
    }

    /**
     * Deletes a webhook from PayPal.
     *
     * @param string $webhook_id (Optional) The ID of the webhook to be deleted. If not provided, the ID of the current object will be used.
     *
     * @return bool|mixed returns the response from PayPal if successful, or false if unsuccessful
     */
    public function delete($webhook_id = '')
    {
        $webhook_id = $webhook_id ?: $this->id();

        $response = PayPal::request(PayPal::EP_WEBHOOKS.$webhook_id, [], 'DELETE');

        return $response['response'] ?? false;
    }

    /**
     * Retrieves an array of event types.
     *
     * @return array an array of event types
     */
    protected function get_event_types()
    {
        return [
            ['name' => self::ACTIVATED],
            ['name' => self::EXPIRED],
            ['name' => self::CANCELLED],
            ['name' => self::SUSPENDED],
            ['name' => self::PAYMENT_FAILED],
            ['name' => self::BP_ACTIVATED],
            ['name' => self::BP_CREATED],
            ['name' => self::BP_PRICE_CHANGE_ACTIVATED],
            ['name' => self::BP_DEACTIVATED],
            ['name' => self::BP_UPDATED],
        ];
    }

    /**
     * Patch the webhook with the given parameters.
     *
     * @param bool $get_new_response whether to return a new response or not
     *
     * @return null|array returns the response if $get_new_response is true, otherwise null
     */
    protected function patch($get_new_response = false)
    {
        if ($webhooks = PayPal::request(PayPal::EP_WEBHOOKS)) {
            if (isset($webhooks['response']['webhooks'])) {
                foreach ($webhooks['response']['webhooks'] as $key => $webhook) {
                    if (!$get_new_response && $this->listen_address() !== $webhooks['response']['webhooks'][$key]['url']) {
                        $webhook_id = $webhooks['response']['webhooks'][$key]['id'];

                        $to_remove = array_column($this->get_event_types(), 'name');

                        $types = array_filter($webhooks['response']['webhooks'][$key]['event_types'], function ($item) use ($to_remove) {
                            return !in_array($item['name'], $to_remove);
                        });

                        if (sizeof($webhooks['response']['webhooks'][$key]['event_types']) !== sizeof($types)) {
                            $data = [
                                'op' => 'replace',
                                'path' => '/event_types',
                                'value' => array_values($types),
                            ];

                            PayPal::request(PayPal::EP_WEBHOOKS.$webhook_id, [$data], 'PATCH');
                        }
                    } elseif ($get_new_response && $this->listen_address() === $webhooks['response']['webhooks'][$key]['url']) {
                        return [
                            'response' => $webhooks['response']['webhooks'][$key],
                        ];
                    }
                }
            }
        }
    }
}
