<?php

namespace PPSFWOO;

use PPSFWOO\PayPal;

class Plan extends \PPSFWOO\PluginMain
{
	public $id,
		   $frequency;

	public function __construct($product_id = NULL)
	{
		parent::__construct(false);

		if($product_id) {

			$this->id = $this->get_id_by_product_id($product_id);

			$this->frequency = $this->get_frequency_by_product_id($product_id);

		}
	}

	private function get_id_by_product_id($product_id)
    {
        return get_post_meta($product_id, "{$this->env['env']}_ppsfwoo_plan_id", true) ?? "";
    }

	private function get_frequency_by_product_id($product_id)
    {
        $plan_id = $this->get_id_by_product_id($product_id);

        $plans = $this->get_plans();

        return $plans[$plan_id]['frequency'] ?? "";
    }

	public function modify_plan()
	{
		$response = ['error' => 'An unexpected error occurred.'];

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $plan_id = isset($_POST['plan_id']) ? sanitize_text_field(wp_unslash($_POST['plan_id'])): "";

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $paypal_action = isset($_POST['paypal_action']) ? sanitize_text_field(wp_unslash($_POST['paypal_action'])): "";

        try {

            if($plan_id && $paypal_response = PayPal::request("/v1/billing/plans/$plan_id/$paypal_action", [], "POST")) {

                if(204 === $paypal_response['status']) {

                    $response = ['success' => true];
                
                }

            }
            
        } catch(\Exception $e) {

            $response = ['error' => $e->getMessage()];

        }

        return $response;
	}

	public function get_plans($update = false)
	{
		if(false === $update) {

			return $this->ppsfwoo_plans[$this->env['env']];

		}

		$plans = [];

		if($plan_data = PayPal::request("/v1/billing/plans")) {

            if(isset($plan_data['response']['plans'])) {

                $products = [];

                foreach($plan_data['response']['plans'] as $plan)
                {
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
                        'frequency'     => $plan_freq['response']['billing_cycles'][0]['frequency']['interval_unit'],
                        'status'        => $plan['status']
                    ];
                }
            
                update_option('ppsfwoo_plans', [$this->env['env'] => $plans]);

            }

        }

        return $plans;
	}

	public static function create_test_plan()
    {
        $product = PayPal::request("/v1/catalogs/products", [
            "name"        => "Video Streaming Service",
            "type"        => "SERVICE",
            "description" => "Video streaming service",
            "category"    => "SOFTWARE",
            "image_url"   => "https://example.com/streaming.jpg",
            "home_url"    => "https://example.com/home"
        ], "POST");

        if(isset($product['response']['id'])) {

            return PayPal::request("/v1/billing/plans",
            [
                "product_id"      => $product['response']['id'],
                "name"            => "Video Streaming Service Plan",
                "billing_cycles"  => [
                    [
                        "frequency" => [
                            "interval_unit"  => "YEAR",
                            "interval_count" => "1"
                        ],
                        "tenure_type"       => "REGULAR",
                        "sequence"          => "1",
                        "total_cycles"      => "1",
                        "pricing_scheme"    => [
                            "fixed_price" => [
                                "value"         => "149",
                                "currency_code" => "USD"
                            ]
                        ]
                    ]
                ],
                "payment_preferences" => [
                    "auto_bill_outstanding" => "true",
                        "setup_fee"             => [
                            "value"         => "0",
                            "currency_code" => "USD"
                        ],
                    "setup_fee_failure_action"  => "CANCEL",
                    "payment_failure_threshold" => "0"
                ],
                "description" => "Video Streaming Service basic plan",
                "status"      => "ACTIVE"
            ], "POST");

        }
    }
}
