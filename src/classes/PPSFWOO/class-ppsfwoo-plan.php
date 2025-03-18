<?php

namespace PPSFWOO;

use PPSFWOO\PayPal;
use PPSFWOO\PluginMain;

class Plan
{
    public $env;
    public $id;
    public $frequency;
    public $price;
    public $product_name;
    public $image_url;
    public $version;
    public $product_id;
    public $name;
    public $status;
    public $usage_type;
    public $billing_cycles;
    public $payment_preferences;
    public $taxes;
    public $quantity_supported;
    public $payee;
    public $create_time;
    public $update_time;
    public $links;
    public $description;

    public function __construct($id = null)
    {
        $this->env = PayPal::env()['env'];

        $this->id = $id;

        $plan_data = PluginMain::get_option('ppsfwoo_plans')[$this->env][$this->id] ?? null;

        if (!empty($plan_data)) {

            foreach ($plan_data as $response_key => $response_item) {

                $this->$response_key = $response_item;

            }

            $this->frequency = $this->get_from_billing_cycles('frequency');

            $this->price = $this->get_from_billing_cycles('price');

            $this->product_name = $plan_data['product_name'] ?? "";
        }
    }

    public function __call($name, $arguments)
    {
        if (strpos($name, 'get_') === 0) {

            $property = lcfirst(substr($name, 4));

            if (property_exists($this, $property)) {

                return $this->$property;

            }
        }

        throw new \BadMethodCallException("Property " . esc_attr($name) . " does not exist.");
    }

    private function get_from_billing_cycles($find, $response = null)
    {
        $billing_cycles = $this->billing_cycles ?? $response['billing_cycles'] ?? $response ?? [];

        foreach ($billing_cycles as $cycle) {
            if ($cycle['tenure_type'] === 'REGULAR') {

                if ('price' === $find) {

                    if (isset($cycle['pricing_scheme']['fixed_price'])) {

                        return floatval($cycle['pricing_scheme']['fixed_price']['value']);

                    } elseif (isset($cycle['pricing_scheme']['pricing_model'])) {

                        $values = [];

                        foreach ($cycle['pricing_scheme']['tiers'] as $tier) {
                            $values[] = $tier['amount']['value'];
                        }

                        return min($values);

                    }

                } elseif ('frequency' === $find) {

                    return $cycle['frequency']['interval_unit'];

                }
            }
        }

        return;
    }

    public function modify_plan()
    {
        $response = ['error' => 'An unexpected error occurred.'];

        if (
            !isset($_POST['nonce'], $_POST['plan_id'], $_POST['paypal_action'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'modify_plan')
        ) {

            return ['error' => 'Security check failed.'];

        }

        $plan_id = sanitize_text_field(wp_unslash($_POST['plan_id']));

        $paypal_action = sanitize_text_field(wp_unslash($_POST['paypal_action']));

        try {

            if ($plan_id && $paypal_response = PayPal::request(PayPal::EP_PLANS . "$plan_id/$paypal_action", [], "POST")) {

                if (PayPal::response_status_is($paypal_response, 204)) {

                    $response = ['success' => true];

                }

            }

        } catch (\Exception $e) {

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

            if ($plan_data
                && isset($plan_data['response']['plans'])
                && count($plan_data['response']['plans']) > 0
            ) {

                foreach ($plan_data['response']['plans'] as $plan) {
                    if (PluginMain::get_option('ppsfwoo_hide_inactive_plans') && "ACTIVE" !== $plan['status']) {

                        continue;

                    }

                    $product_data = PayPal::request(PayPal::EP_PRODUCTS . $plan['product_id']);

                    if (isset($plan['taxes'])) {

                        $tax_rate_id = $this->insert_tax_rate(floatval($plan['taxes']['percentage']));

                    }

                    $plan['product_name'] = $product_data['response']['name'] ?? "";

                    $plan['image_url'] = $product_data['response']['image_url'] ?? "";

                    $plans[$plan['id']] = $plan;
                }

                $page++;

            } else {

                break;

            }

        } while (true);

        uasort($plans, function ($a, $b) {
            return strcmp($a['status'], $b['status']);
        });

        PluginMain::clear_option_cache('ppsfwoo_plans');

        update_option('ppsfwoo_plans', [
            "{$this->env}" => $plans
        ]);

        return $plans;
    }

    public function get_tax_rate_data()
    {
        $class = PluginMain::plugin_data('Name');

        $slug = strtolower(str_replace(' ', '-', $class));

        $tax_rate = 0;

        $inclusive = null;

        if ($taxes = $this->get_taxes()) {

            if (isset($taxes)) {

                $tax_rate = number_format($taxes['percentage'], 4) ?? 0;

                $inclusive = !empty($taxes['inclusive']);

            }

        }

        return [
            'tax_rate'       => $tax_rate,
            'inclusive'      => $inclusive,
            'tax_rate_class' => $class,
            'tax_rate_slug'  => $slug
        ];
    }

    private function insert_tax_rate($tax_rate)
    {
        $tax_rate_data = $this->get_tax_rate_data();

        $create_tax_rate = true;

        $tax_rate_id = 0;

        $taxes = \WC_Tax::get_rates_for_tax_class($tax_rate_data['tax_rate_slug']);

        if ($taxes) {

            foreach ($taxes as $id => $tax_rate_object) {
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

        if (!$taxes || $create_tax_rate) {

            if (!\WC_Tax::get_tax_class_by('name', $tax_rate_data['tax_rate_class'])) {

                \WC_Tax::create_tax_class($tax_rate_data['tax_rate_class'], $tax_rate_data['tax_rate_slug']);

            }

            $tax_rate_id = \WC_Tax::_insert_tax_rate(
                [
                'tax_rate_country' => '*',
                'tax_rate_state'   => '*',
                'tax_rate'         => $tax_rate,
                'tax_rate_name'    => 'Tax',
                'tax_rate_class'   => $tax_rate_data['tax_rate_class'],
                'tax_rate_priority' => 1,
                'tax_rate_compound' => 0,
                'tax_rate_shipping' => 0,
                'tax_rate_order'   => 1]
            );
        }

        return $tax_rate_id;
    }

    public static function get_plans()
    {
        $plan_objects = [];

        $plans = PluginMain::get_option('ppsfwoo_plans');

        $env = PayPal::env()['env'];

        if ($plans && isset($plans[$env])) {

            foreach ($plans[$env] as $plan_id => $plan) {

                $plan_objects[$plan_id] = new self($plan_id);

            }

        }

        return $plan_objects;
    }
}
