<?php

namespace PPSFWOO;

use PPSFWOO\PayPal,
    PPSFWOO\PluginMain;

class Plan extends PluginMain
{
	public $id,
		   $frequency,
           $version,
           $product_id,
           $name,
           $status,
           $usage_type,
           $billing_cycles,
           $payment_preferences,
           $taxes,
           $quantity_supported,
           $payee,
           $create_time,
           $update_time,
           $links,
           $description;

	public function __construct($construct_by = "", $value = NULL)
	{
		parent::__construct();

        switch ($construct_by)
        {
            case 'plan_id':

                $this->id = $value;

                break;

            case 'product_id':

                $this->id = $this->get_id_by_product_id($value);

                break;
        }

        if(isset($this->id) && $cached_response = $this->get_cached_response()) {

            foreach($cached_response as $response_key => $response_item)
            {

                $this->$response_key = $response_item;

            }
            
            $this->frequency = self::get_from_response_billing_cycles('frequency', $this->billing_cycles);
        }
	}

    public function get_cached_response()
    {
        return $this->ppsfwoo_plans[$this->env['env']][$this->id]['response'] ?? NULL;
    }

    public function get_billing_cycles()
    {
        return $this->billing_cycles;
    }

	private function get_id_by_product_id($product_id)
    {
        return get_post_meta($product_id, "{$this->env['env']}_ppsfwoo_plan_id", true) ?? "";
    }

    public static function get_from_response_billing_cycles($find, $response = NULL)
    {
        $billing_cycles = $response['billing_cycles'] ?? $response ?? [];
        
        foreach ($billing_cycles as $cycle)
        {
            if ($cycle['tenure_type'] === 'REGULAR') {

                if('price' === $find) {

                    if (isset($cycle['pricing_scheme']['fixed_price'])) {

                        return floatval($cycle['pricing_scheme']['fixed_price']['value']);

                    } elseif (isset($cycle['pricing_scheme']['pricing_model'])) {

                        $values = [];

                        foreach ($cycle['pricing_scheme']['tiers'] as $tier)
                        {
                            $values[] = $tier['amount']['value'];
                        }

                        return min($values);

                    }

                } elseif('frequency' === $find) {

                    return $cycle['frequency']['interval_unit'];

                }
            }
        }
        
        return;
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

                if(PayPal::response_status_is($paypal_response, 204)) {

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

        $page = 1;

        do {

            $plan_data = PayPal::request(
                PayPal::EP_PLANS,
                ['page_size' => 20, 'page' => $page],
                "GET",
                ['Prefer' => 'return=representation']
            );

            if($plan_data
                && isset($plan_data['response']['plans'])
                && count($plan_data['response']['plans']) > 0
            ) {

                $products = [];

                foreach($plan_data['response']['plans'] as $plan)
                {
                    if($this->ppsfwoo_hide_inactive_plans && "ACTIVE" !== $plan['status']) {

                        continue;

                    }

                    if(!in_array($plan['product_id'], array_keys($products))) {
                    
                        $product_data = PayPal::request(PayPal::EP_PRODUCTS . $plan['product_id']);

                        $product_name = $product_data['response']['name'];

                        $products[$plan['product_id']] = $product_name;

                    } else {

                        $product_name = $products[$plan['product_id']];
                    }

                    if(isset($plan['taxes'])) {

                        $tax_rate_id = $this->insert_tax_rate(floatval($plan['taxes']['percentage']));

                    }

                    $plans[$plan['id']] = [
                        'plan_name'     => $plan['name'],
                        'product_name'  => $product_name,
                        'frequency'     => self::get_from_response_billing_cycles('frequency', $plan),
                        'status'        => $plan['status'],
                        'price'         => self::get_from_response_billing_cycles('price', $plan),
                        'response'      => $plan
                    ];
                }

                $page++;

            } else {
                
                break;

            }

        } while (true);

        uasort($plans, function ($a, $b) {
            return strcmp($a['status'], $b['status']);
        });
    
        $env = $this->env['env'];

        update_option('ppsfwoo_plans', [
            $env => $plans
        ]);


        return $plans;
    }

    public function get_payment_preferences()
    {
        $cached_response = $this->get_cached_response();

        return isset($cached_response, $cached_response['payment_preferences']) ? $cached_response['payment_preferences']: [];
    }

    public function get_tax_rate_data()
    {
        $class = self::plugin_data('Name');

        $slug = strtolower(str_replace(' ', '-', $class));

        $tax_rate = 0;

        $inclusive = NULL;

        if($cached_response = $this->get_cached_response()) {

            if(isset($cached_response['taxes'])) {

                $tax_rate = number_format($cached_response['taxes']['percentage'], 4) ?? 0;

                $inclusive = !empty($cached_response['taxes']['inclusive']);
                
            }

        }

        return [
            'tax_rate'       => $tax_rate,
            'inclusive'      => $inclusive,
            'tax_rate_class' => $class,
            'tax_rate_slug'  => $slug
        ];
    }

    public function insert_tax_rate($tax_rate)
    {
        $tax_rate_data = $this->get_tax_rate_data();

        $create_tax_rate = true;

        $tax_rate_id = 0;

        $taxes = \WC_Tax::get_rates_for_tax_class($tax_rate_data['tax_rate_slug']);

        if($taxes) {

            foreach ($taxes as $id => $tax_rate_object)
            {
                if (
                    $tax_rate_object->tax_rate_class === $tax_rate_data['tax_rate_slug']
                    && $tax_rate_object->tax_rate === number_format($tax_rate, 4)
                ) {

                    $tax_rate_id = $id;

                    $create_tax_rate = false;

                    break;

                }
            }
        }

        if(!$taxes || $create_tax_rate) {

            if(!\WC_Tax::get_tax_class_by('name', $tax_rate_data['tax_rate_class'])) {

                \WC_Tax::create_tax_class($tax_rate_data['tax_rate_class'], $tax_rate_data['tax_rate_slug']);

            }

            $tax_rate_id = \WC_Tax::_insert_tax_rate([
                'tax_rate_country' => '*',
                'tax_rate_state'   => '*',
                'tax_rate'         => $tax_rate,
                'tax_rate_name'    => 'Tax',
                'tax_rate_class'   => $tax_rate_data['tax_rate_class'],
                'tax_rate_priority'=> 1,
                'tax_rate_compound'=> 0,
                'tax_rate_shipping'=> 0,
                'tax_rate_order'   => 1]
            );
        }

        return $tax_rate_id;
    }

	public function get_plans()
	{
		return $this->ppsfwoo_plans[$this->env['env']] ?? [];
	}
}
