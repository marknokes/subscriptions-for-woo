<?php

namespace PPSFWOO;

use PPSFWOO\PayPal;
use PPSFWOO\PluginMain;

class Plan extends PluginMain
{
	public $id,
		   $frequency;

	public function __construct($construct_by = "", $value = NULL)
	{
		parent::__construct();

        switch ($construct_by)
        {
            case 'plan_id':

                $this->id = $value;

                $this->frequency = $this->get_frequency();

                break;

            case 'product_id':

                $this->id = $this->get_id_by_product_id($value);

                $this->frequency = $this->get_frequency();

                break;
        }
	}

	private function get_id_by_product_id($product_id)
    {
        return get_post_meta($product_id, "{$this->env['env']}_ppsfwoo_plan_id", true) ?? "";
    }

    public static function get_plan_price($plan_details)
    {
        $billing_cycles = $plan_details['billing_cycles'] ?? [];
        
        foreach ($billing_cycles as $cycle)
        {
            if ($cycle['tenure_type'] === 'REGULAR') {

                return intval($cycle['pricing_scheme']['fixed_price']['value']);

            }
        }
        
        return;
    }

	private function get_frequency()
    {
        $plans = $this->get_plans();

        return $plans[$this->id]['frequency'] ?? "";
    }

	public function modify_plan()
	{
		$response = ['error' => 'An unexpected error occurred.'];

        if(!isset($_POST['nonce'], $_POST['plan_id'], $_POST['paypal_action']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'modify_plan')) {

            return ['error' => 'Security check failed.'];

        }

        $plan_id = sanitize_text_field(wp_unslash($_POST['plan_id']));

        $paypal_action = sanitize_text_field(wp_unslash($_POST['paypal_action']));

        try {

            if($plan_id && $paypal_response = PayPal::request(PayPal::EP_PLANS . "$plan_id/$paypal_action", [], "POST")) {

                if(204 === $paypal_response['status']) {

                    $response = ['success' => true];
                
                }

            }
            
        } catch(\Exception $e) {

            $response = ['error' => $e->getMessage()];

        }

        return $response;
	}

    public function refresh_all()
    {
        $plans = [];

        if($plan_data = PayPal::request(PayPal::EP_PLANS)) {

            if(isset($plan_data['response']['plans'])) {

                $products = [];

                foreach($plan_data['response']['plans'] as $plan)
                {
                    if($this->ppsfwoo_hide_inactive_plans && "ACTIVE" !== $plan['status']) {

                        continue;

                    }

                    $plan_freq = PayPal::request(PayPal::EP_PLANS . $plan['id']);

                    if(!in_array($plan['product_id'], array_keys($products))) {
                    
                        $product_data = PayPal::request(PayPal::EP_PRODUCTS . $plan['product_id']);

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
            
                $env = $this->env['env'];

                update_option('ppsfwoo_plans', [
                    $env => $plans
                ]);

            }

        }

        return $plans;
    }

	public function get_plans()
	{
		return $this->ppsfwoo_plans[$this->env['env']] ?? [];
	}
}
