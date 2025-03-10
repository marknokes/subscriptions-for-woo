<?php

namespace PPSFWOO;

use PPSFWOO\PayPal,
    PPSFWOO\Subscriber,
    PPSFWOO\PluginMain,
    PPSFWOO\Exception;

class Webhook
{
	const WEBHOOK_PREFIX = "BILLING.SUBSCRIPTION";

    const ACTIVATED = 'BILLING.SUBSCRIPTION.ACTIVATED';

    const EXPIRED = 'BILLING.SUBSCRIPTION.EXPIRED';

    const CANCELLED = 'BILLING.SUBSCRIPTION.CANCELLED';

    const SUSPENDED = 'BILLING.SUBSCRIPTION.SUSPENDED';

    const PAYMENT_FAILED = 'BILLING.SUBSCRIPTION.PAYMENT.FAILED';

    private static $instance = NULL;

    public static $api_namespace = "subscriptions-for-woo/v1";

    public static $endpoint = "/incoming";

    public $site_url,
    	   $listen_address,
    	   $webhook_id,
    	   $subscribed_webhooks;

    public function __construct()
    {
    	$this->site_url = network_site_url('', 'https');

    	$this->listen_address = $this->site_url . "/wp-json/" . self::$api_namespace . self::$endpoint;

    	$this->webhook_id = PluginMain::get_option('ppsfwoo_webhook_id');

    	$this->subscribed_webhooks = PluginMain::get_option('ppsfwoo_subscribed_webhooks');
    }

    public static function get_instance()
    {
        if (self::$instance === null) {

            self::$instance = new self();

        }

        return self::$instance;
    }

    public function handle_request(\WP_REST_Request $request)
    {
        $response = new \WP_REST_Response();
        
        if(\WP_REST_Server::READABLE === $request->get_method()) {

        	$response->set_status(200);

      		return $response;

        }

        if (!PayPal::valid_request($this->id())) {

        	$response->set_status(403);

      		return $response;

        }
        
        $event_type = $request['event_type'] ?? "";

        $Subscriber = new Subscriber($request, $event_type);

        switch($event_type)
        {
            case Webhook::ACTIVATED:
                $Subscriber->subscribe();
                break;
            case Webhook::EXPIRED:
            case Webhook::CANCELLED:
            case Webhook::SUSPENDED:
            case Webhook::PAYMENT_FAILED:
                $Subscriber->cancel();
                break;
        }

        $response->set_status(200);

      	return $response;
    }

    public function rest_api_init()
    {
        register_rest_route(
            self::$api_namespace,
            self::$endpoint, [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'permission_callback' => '__return_true',
                    'callback'            => [$this, 'handle_request'],
                    'args'                => [
                        'event_type' => [
                            'validate_callback' => function($param, $request, $key) {
                                return strpos($param, self::WEBHOOK_PREFIX) === 0;
                            }
                        ]
                    ]
                ],
                [
                    'methods' => \WP_REST_Server::READABLE,
                    'permission_callback' => '__return_true',
                    'callback' => [$this, 'handle_request']
                ]
            ]
        );
    }

    public function id()
    {
    	return $this->webhook_id;
    }

    public function listen_address()
    {
    	return $this->listen_address;
    }

    public function resubscribe()
    {
        $created = false;
        
        if($webhooks = PayPal::request(PayPal::EP_WEBHOOKS)) {

            if(isset($webhooks['response']['webhooks'])) {

                foreach($webhooks['response']['webhooks'] as $key => $webhook)
                {
                    if($this->listen_address() === $webhooks['response']['webhooks'][$key]['url']) {

                        $this->delete($webhooks['response']['webhooks'][$key]['id']);

                    }
                }

                $created = $this->create();
            }
        }

        return $created;
    }

    public function list()
    {
        if($this->id() && is_array($this->subscribed_webhooks) && sizeof($this->subscribed_webhooks)) {

            $subscribed = $this->subscribed_webhooks;

        } else if($webhooks = PayPal::request(PayPal::EP_WEBHOOKS)) {

            $subscribed = [];

            if(isset($webhooks['response']['webhooks'])) {

                foreach($webhooks['response']['webhooks'] as $key => $webhook)
                {
                    if($webhook['id'] === $this->id()) {

                        $subscribed = $webhook['event_types'];

                    }
                }

            }

            PluginMain::clear_option_cache('ppsfwoo_subscribed_webhooks');
            
            update_option('ppsfwoo_subscribed_webhooks', $subscribed);

        }

        return $subscribed ?? false;
    }

    public function create()
    {
        $webhook_id = "";

        $event_types = [];

        try{

            $response = PayPal::request(PayPal::EP_WEBHOOKS, [
                'url' => $this->listen_address(),
                'event_types' => [
                    ['name' => self::ACTIVATED],
                    ['name' => self::EXPIRED],
                    ['name' => self::CANCELLED],
                    ['name' => self::SUSPENDED],
                    ['name' => self::PAYMENT_FAILED]
                ]
            ], "POST");

            if(isset($response['response']['id'])) {

                $webhook_id = $response['response']['id'];

                $event_types = $response['response']['event_types'];
            
            } else if(!PayPal::response_status_is($response, 201)) {

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
            
        } catch(\Exception $e) {

            Exception::log($e);

            return false;

        }
    }

    protected function patch($get_new_response = false)
    {
        if($webhooks = PayPal::request(PayPal::EP_WEBHOOKS)) {

            if(isset($webhooks['response']['webhooks'])) {

                foreach($webhooks['response']['webhooks'] as $key => $webhook)
                {
                    if(!$get_new_response && $this->listen_address() !== $webhooks['response']['webhooks'][$key]['url']) {

                        $webhook_id = $webhooks['response']['webhooks'][$key]['id'];

                        $types = [];

                        foreach($webhooks['response']['webhooks'][$key]['event_types'] as $type_key => $type)
                        {
                            if(strpos($type['name'], self::WEBHOOK_PREFIX) !== 0) {

                                array_push($types, [
                                    'name' => $type['name']
                                ]);
                                    
                            }
                        }

                        if(sizeof($webhooks['response']['webhooks'][$key]['event_types']) !== sizeof($types)) {

                            $data = [
                                "op"    => "replace",
                                "path"  => "/event_types",
                                "value" => $types
                            ];

                            PayPal::request(PayPal::EP_WEBHOOKS . $webhook_id, [$data], "PATCH");
                        }
                        
                    } else if($get_new_response && $this->listen_address() === $webhooks['response']['webhooks'][$key]['url']) {

                        return [
                            'response' => $webhooks['response']['webhooks'][$key]
                        ];

                    }
                }
            }
        }
    }

    public function delete($webhook_id = "")
    {
        $webhook_id = $webhook_id ?: $this->id();

        $response = PayPal::request(PayPal::EP_WEBHOOKS . $webhook_id, [], "DELETE");

        return $response['response'] ?? false;
    }

}
