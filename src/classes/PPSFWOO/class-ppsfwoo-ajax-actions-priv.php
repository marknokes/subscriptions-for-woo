<?php

namespace PPSFWOO;

use PPSFWOO\PluginMain;
use PPSFWOO\Plan;
use PPSFWOO\AjaxActions;

class AjaxActionsPriv extends AjaxActions
{
    protected function modify_plan()
    {
        if(!is_super_admin() && !current_user_can('ppsfwoo_manage_settings')) {

            return wp_json_encode([
                'error' => 'Insufficient permissions.'
            ]);

        }

        $Plan = new Plan();

        $response = $Plan->modify_plan();

        if(isset($response['success']) && true === $response['success']) {

            $Plan->refresh_all();

        }

        return wp_json_encode($response);
    }

    public static function refresh_plans()
    {
        if(!is_super_admin() && !current_user_can('ppsfwoo_manage_settings')) {

            return wp_json_encode([
                'error' => 'Insufficient permissions.'
            ]);

        }

        $Plan = new Plan();

        $plans = $Plan->refresh_all();

        return wp_json_encode([
            "success" => !empty($plans),
            "plans"   => $plans
        ]);
    }

    protected function search_subscribers()
    {
        if(!is_super_admin() && !current_user_can('ppsfwoo_manage_settings')) {

            return wp_json_encode([
                'error' => 'Insufficient permissions.'
            ]);

        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])): "";

        if(empty($email)) { 

            return wp_json_encode([
                'error' => 'Email address is empty.'
            ]);

        }

        $PluginMain = PluginMain::get_instance();

        $subscriber_table_options_page = $PluginMain->subscriber_table_options_page($email);

        if(!$subscriber_table_options_page['num_subs']) {

            return wp_json_encode([
                'error' => 'No subscribers with that email address.'
            ]);

        } else {

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return wp_json_encode([
                'html' => $subscriber_table_options_page['html']
            ]);

        }
    }
}
