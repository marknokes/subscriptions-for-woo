<?php

namespace PPSFWOO;

use PPSFWOO\Product;

class Order
{
	public static function get_order_id_by_subscription_id($subs_id)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT `order_id` FROM {$wpdb->prefix}ppsfwoo_subscriber WHERE `id` = %s",
                $subs_id
            )
        );

        return isset($result[0]->order_id) ? $result[0]->order_id: false;
    }

    public static function insert_order($user)
    {   
        $order = wc_create_order();

        $order->set_customer_id($user->user_id);

        $order->add_product(wc_get_product(Product::get_product_id_by_plan_id($user->plan_id)));

        $address = [
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'company'    => '',
            'email'      => $user->email,
            'phone'      => '',
            'address_1'  => $user->address_line_1,
            'address_2'  => $user->address_line_2,
            'city'       => $user->city,
            'state'      => $user->state,
            'postcode'   => $user->postal_code,
            'country'    => $user->country_code
        ];

        $order->set_address($address, 'billing');

        $order->set_address($address, 'shipping');

        $order->set_payment_method('paypal');

        $order->set_payment_method_title('Online');

        $order->calculate_shipping();
        
        $order->calculate_totals();
        
        $order->set_status('wc-completed', 'Order created programmatically.');

        $order->save();

        return $order->get_id();
    }
}