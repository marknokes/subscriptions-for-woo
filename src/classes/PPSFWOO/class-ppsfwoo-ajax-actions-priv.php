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

        if (!isset($_POST['search_by_email']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['search_by_email'])), 'search_by_email')) {

            return wp_json_encode([
                'error' => 'Security check failed.'
            ]);

        }

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])): "";

        if(empty($email)) { 

            return wp_json_encode([
                'error' => 'Email address is empty.'
            ]);

        }

        $data = PluginMain::get_instance()->subscriber_table_options_page($email);

        if(!$data['num_subs']) {

            return wp_json_encode([
                'error' => 'No subscribers with that email address.'
            ]);

        } else {

            return wp_json_encode([
                'html' => $data['html']
            ]);

        }
    }
}
