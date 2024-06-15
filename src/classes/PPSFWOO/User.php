<?php

namespace PPSFWOO;

use \PPSFWOO\PluginMain;

class User
{
	public $user_id,
		   $subscription_id,
		   $plan_id,
		   $email,
		   $first_name,
		   $last_name,
		   $address_line_1,
		   $address_line_2,
		   $city,
		   $state,
		   $postal_code,
		   $country_code;

	public function __construct($request, $type = 'resource')
	{
		$this->user_id          = NULL;

        $this->subscription_id  = $request[$type]['id'];

        $this->plan_id          = $request[$type]['plan_id'];

        $this->email            = $request[$type]['subscriber']['email_address'];

        $this->first_name       = $request[$type]['subscriber']['name']['given_name'];

        $this->last_name        = $request[$type]['subscriber']['name']['surname'];

        $this->address_line_1   = $request[$type]['subscriber']['shipping_address']['address']['address_line_1'];

        $this->address_line_2   = $request[$type]['subscriber']['shipping_address']['address']['address_line_2'] ?? "";

        $this->city             = $request[$type]['subscriber']['shipping_address']['address']['admin_area_2'];

        $this->state            = $request[$type]['subscriber']['shipping_address']['address']['admin_area_1'];

        $this->postal_code      = $request[$type]['subscriber']['shipping_address']['address']['postal_code'];

        $this->country_code     = $request[$type]['subscriber']['shipping_address']['address']['country_code'];

        return $this;
	}

    public function create_wp_user()
    {
        $user_id = wp_insert_user([
            'user_login' => strtolower($this->first_name) . '.' . strtolower($this->last_name),
            'user_pass'  => wp_generate_password(12, false),
            'user_email' => $this->email,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'role'       => 'customer'
        ]);

        if (!is_wp_error($user_id)) {

            wp_new_user_notification($user_id, null, 'user');

            return $user_id;

        } else {

            wc_get_logger()->error("Error creating user", ['source' => PluginMain::plugin_data('Name')]);

            return false;
        }
    }

	public function create_woocommerce_customer()
    {
        $customer = new \WC_Customer($this->user_id);

        $customer->set_billing_first_name($this->first_name);

        $customer->set_billing_last_name($this->last_name);

        $customer->set_billing_email($this->email);

        $customer->set_billing_address_1($this->address_line_1);

        $customer->set_billing_address_2($this->address_line_2);

        $customer->set_billing_city($this->city);

        $customer->set_billing_state($this->state);

        $customer->set_billing_postcode($this->postal_code);

        $customer->set_billing_country($this->country_code);

        $customer->save();
    }

}
