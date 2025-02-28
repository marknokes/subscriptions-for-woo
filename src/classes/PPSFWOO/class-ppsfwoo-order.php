<?php

namespace PPSFWOO;

use PPSFWOO\Product,
    PPSFWOO\Subscriber,
    PPSFWOO\Database;

class Order
{
    public function __construct()
    {
        add_filter('woocommerce_order_get_total', [$this, 'exclude_from_total'], 10, 2);

        add_filter('woocommerce_order_get_subtotal', [$this, 'exclude_from_subtotal'], 10, 2);
    }

    public function exclude_from_total($total, $order)
    {
        foreach ($order->get_items() as $item_id => $item)
        {
            $exclude_from_order_total = $item->get_meta('exclude_from_order_total')['value'] ?? '';

            if ($exclude_from_order_total === 'yes') {

                $total -= $item->get_total();

            }
        }

        return $total;
    }

    public static function exclude_from_subtotal($subtotal, $order)
    {
        foreach ($order->get_items() as $item_id => $item)
        {
            $exclude_from_order_total = $item->get_meta('exclude_from_order_total')['value'] ?? '';

            if ($exclude_from_order_total === 'yes') {

                $subtotal -= $item->get_subtotal();

            }
        }

        return $subtotal;
    }

	public static function get_order_id_by_subscription_id($subs_id)
    {
        $results = new Database("SELECT `order_id` FROM {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber WHERE `id` = %s", [$subs_id]);

        return $results->result[0]->order_id ?? false;
    }

    public static function get_address(Subscriber $Subscriber)
    {
        return [
            'first_name' => $Subscriber->first_name,
            'last_name'  => $Subscriber->last_name,
            'company'    => '',
            'email'      => $Subscriber->email,
            'phone'      => '',
            'address_1'  => $Subscriber->address_line_1,
            'address_2'  => $Subscriber->address_line_2,
            'city'       => $Subscriber->city,
            'state'      => $Subscriber->state,
            'postcode'   => $Subscriber->postal_code,
            'country'    => $Subscriber->country_code
        ];
    }

    public static function has_subscription($order)
    {
        $has_subscription = false;

        if(isset($order) && $order instanceof \WC_Order) {

            foreach ($order->get_items() as $item)
            {
                $product = $item->get_product();

                if($product->is_type(Product::TYPE)) {

                    $has_subscription = true;

                    break;

                }
            }

        }

        return $has_subscription;
    }

    private static function parse_order_items($order, $plan, $tax_rate_data)
    {
        $first_price = 0;

        $has_trial = NULL;

        foreach ($plan->get_billing_cycles() as $cycle)
        {
            $sequence = intval($cycle['sequence']);

            if (isset($cycle['pricing_scheme']['fixed_price'])) {

                $price = $cycle['pricing_scheme']['fixed_price']['value'];

                if(0 === $first_price && 1 === $sequence) {

                    $first_price = floatval($price);

                }

                $item = new \WC_Order_Item_Product();

                $item->set_name("{$cycle['tenure_type']} (period $sequence)");

                $item->set_subtotal($price);

                if($cycle['tenure_type'] === 'TRIAL' && 1 === $sequence) {

                    $item->set_total($price);

                    $has_trial = true;

                } else {

                    $item->set_total(0);

                    $item->add_meta_data('exclude_from_order_total', ['value' => 'yes']);

                }

                $order->add_item($item);

            } elseif (isset($cycle['pricing_scheme']['pricing_model'])) {

                foreach ($cycle['pricing_scheme']['tiers'] as $key => $tier)
                {
                    $tier_num = $key + 1;

                    $price = $tier['amount']['value'];

                    if(0 === $first_price && 1 === $sequence) {

                        $first_price = $price;

                    }

                    $item = new \WC_Order_Item_Product();

                    $item->set_name("{$cycle['tenure_type']} (period $sequence) tier $tier_num");

                    $item->set_subtotal($price);

                    if($cycle['tenure_type'] === 'TRIAL' && 1 === $sequence) {

                        $item->set_total($price);

                        $has_trial = true;

                    } else {

                        $item->set_total(0);

                        $item->add_meta_data('exclude_from_order_total', ['value' => 'yes']);

                    }

                    $order->add_item($item);

                }
            }
        }

        $payment_preferences = $plan->get_payment_preferences();

        if(isset($payment_preferences['setup_fee']['value']) && $payment_preferences['setup_fee']['value'] > 0) {

            $fee = new \WC_Order_Item_Fee();

            $fee->set_name('One-time setup fee');

            $fee->set_amount($payment_preferences['setup_fee']['value']);

            $fee->set_total($payment_preferences['setup_fee']['value']);

            // Need this here because the loop below over $order->get_items() does not contain fee(s)
            if($tax_rate_data['tax_rate'] !== 0) {
                    
                $fee->set_tax_class($tax_rate_data['tax_rate_slug']);

            }

            $order->add_item($fee);

            $first_price = floatval($first_price) + floatval($payment_preferences['setup_fee']['value']);
        }

        foreach ($order->get_items() as $item_id => $item)
        {
            $product = $item->get_product();

            if ($has_trial && $product && $product->exists()) {

                $item->set_total(0);

                $item->add_meta_data('exclude_from_order_total', ['value' => 'yes']);

            }

            if($tax_rate_data['tax_rate'] !== 0) {
                    
                $item->set_tax_class($tax_rate_data['tax_rate_slug']);

            }
            
            $item->save();
        }

        return $first_price;
    }

    public static function insert(Subscriber $Subscriber)
    {   
        $tax_rate_data = $Subscriber->plan->get_tax_rate_data();

        $order = wc_create_order();

        $order->set_customer_id($Subscriber->user_id);

        $order->add_product(wc_get_product(Product::get_product_id_by_plan_id($Subscriber->get_plan_id())));

        $first_price = self::parse_order_items($order, $Subscriber->plan, $tax_rate_data);

        $order->set_address(self::get_address($Subscriber), 'billing');

        $order->set_address(self::get_address($Subscriber), 'shipping');

        $order->set_payment_method('paypal');

        $order->set_payment_method_title('Online');

        $order->calculate_shipping();

        $order->calculate_totals();

        if(0 !== $tax_rate_data['tax_rate']) {

            if(empty($tax_rate_data['inclusive'])) {

                $order->set_total($first_price + (($tax_rate_data['tax_rate'] / 100) * $first_price));

            } else {

                $order->set_total($first_price);

            }

        } else {

            $order->set_total($first_price);

        }

        $order->set_discount_total(0);

        $order->set_status('processing', 'Order created by Subscriptions for Woo.');

        $order->save();

        return $order->get_id();
    }
}
