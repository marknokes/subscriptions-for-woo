<?php

namespace PPSFWOO;

use PPSFWOO\Webhook;
use PPSFWOO\PayPal;
use PPSFWOO\PluginMain;

class AjaxActionsPriv extends \PPSFWOO\AjaxActions
{
    protected function modify_plan()
    {
        $return = ['error' => true];

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $plan_id = isset($_POST['plan_id']) ? sanitize_text_field(wp_unslash($_POST['plan_id'])): "";

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $paypal_action = isset($_POST['paypal_action']) ? sanitize_text_field(wp_unslash($_POST['paypal_action'])): "";

        try {

            if($plan_id && $response = PayPal::request("/v1/billing/plans/$plan_id/$paypal_action", [], "POST")) {

                if(204 === $response['status']) {

                    $return = ['success' => true];

                    self::refresh_plans();
                
                }

            }
            
        } catch(\Exception $e) {

            $return = ['error' => $e->getMessage()];

        }

        return wp_json_encode($return);
    }

	protected function list_plans()
    {
        $PluginMain = PluginMain::get_instance();

        $env = $PluginMain->env['env'];

        return isset($PluginMain->ppsfwoo_plans[$env]) ? wp_json_encode($PluginMain->ppsfwoo_plans[$env]) : false;
    }

    protected function list_webhooks()
    {
        return Webhook::get_instance()->list();
    }

    public static function refresh_plans()
    {
        $PluginMain = PluginMain::get_instance();

        $env = $PluginMain->env['env'];
        
        $success = "false";

        if($plan_data = PayPal::request("/v1/billing/plans")) {

            $plans = [];

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
            
                update_option('ppsfwoo_plans', [$env => $plans]);

                $success = "true";
            }
        }

        return wp_json_encode([
            "success" => $success,
            "plans"   => $plans
        ]);
    }

    protected function search_subscribers()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])): "";

        if(empty($email)) { 

            return "";

        }

        $PluginMain = PluginMain::get_instance();

        $subscriber_table_options_page = $PluginMain->subscriber_table_options_page($email);

        if(!$subscriber_table_options_page['num_subs']) {

            return "false";

        } else {

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $subscriber_table_options_page['html'];

        }
    }
}
