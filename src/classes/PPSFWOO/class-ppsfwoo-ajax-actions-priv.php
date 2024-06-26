<?php

namespace PPSFWOO;

use PPSFWOO\Webhook;
use PPSFWOO\PayPal;
use PPSFWOO\PluginMain;
use PPSFWOO\Plan;

class AjaxActionsPriv extends \PPSFWOO\AjaxActions
{
    protected function modify_plan()
    {
        $Plan = new Plan();

        $response = $Plan->modify_plan();

        if(isset($response['success']) && true === $response['success']) {

            self::refresh_plans();

        }

        return wp_json_encode($response);
    }

	protected function list_plans()
    {
        $Plan = new Plan();

        $plans = $Plan->get_plans();

        return $plans ? wp_json_encode($plans) : false;
    }

    protected function list_webhooks()
    {
        return Webhook::get_instance()->list();
    }

    public static function refresh_plans()
    {
        $Plan = new Plan();

        $plans = $Plan->get_plans($update = true);

        return wp_json_encode([
            "success" => sizeof($plans) ? "true": "false",
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
