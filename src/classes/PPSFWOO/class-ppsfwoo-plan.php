<?php

namespace PPSFWOO;

use PPSFWOO\PayPal;
use PPSFWOO\PluginMain;

class Plan extends PluginMain
{
	public $id,
		   $frequency;

	public function __construct($product_id = NULL)
	{
		parent::__construct(false);

		if($product_id) {

			$this->id = $this->get_id_by_product_id($product_id);

			$this->frequency = $this->get_frequency();

		}
	}

	private function get_id_by_product_id($product_id)
    {
        return get_post_meta($product_id, "{$this->env['env']}_ppsfwoo_plan_id", true) ?? "";
    }

	private function get_frequency()
    {
        $plans = $this->get_plans();

        return $plans[$this->id]['frequency'] ?? "";
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

    public function refresh_plans()
    {
        $plans = [];

        if($plan_data = PayPal::request("/v1/billing/plans")) {

            if(isset($plan_data['response']['plans'])) {

                $products = [];

                foreach($plan_data['response']['plans'] as $plan)
                {
                    if($this->ppsfwoo_hide_inactive_plans && "ACTIVE" !== $plan['status']) {

                        continue;

                    }

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

                uasort($plans, function ($a, $b) {
                    return strcmp($a['status'], $b['status']);
                });
            
                PluginMain::update_option('ppsfwoo_plans', [$this->env['env'] => $plans]);

            }

        }

        return $plans;
    }

	public function get_plans($update = false)
	{
		return $this->ppsfwoo_plans[$this->env['env']] ?? [];
	}
}
