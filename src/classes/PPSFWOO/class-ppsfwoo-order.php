<?php

namespace PPSFWOO;

use PPSFWOO\Product,
    PPSFWOO\Subscriber,
    PPSFWOO\Database;

class Order
{
    private static $order_total = 0;

    private static $has_trial = NULL;

    private static $tax_rate_data = [];

    private static $quantity = 1;

    private static $line_item_price = 0;

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

    private static function create_line_item($cycle, $total, $sequence, $name)
    {
        $item = new \WC_Order_Item_Product();

        $item->set_name($name);

        $item->set_subtotal($total);

        $item->set_quantity(self::$quantity);

        if($cycle['tenure_type'] === 'TRIAL' && 1 === $sequence) {

            $item->set_total($total);

            self::$has_trial = true;

        } else {

            $item->set_total(0);

            $item->add_meta_data('exclude_from_order_total', ['value' => 'yes']);

        }

        if(0 === self::$line_item_price && $cycle['tenure_type'] === 'REGULAR') {

            self::$line_item_price = $total / self::$quantity;

        }


        return $item;
    }

    private static function create_fee($name, $amount)
    {
        $fee = new \WC_Order_Item_Fee();

        $fee->set_name($name);

        $fee->set_amount($amount);

        $fee->set_total($amount);

        // Need this here because the loop below over $order->get_items() does not contain fee(s)
        if(self::$tax_rate_data['tax_rate'] !== 0 && empty(self::$tax_rate_data['inclusive'])) {
                
            $fee->set_tax_class(self::$tax_rate_data['tax_rate_slug']);

        }

        return $fee;
    }

    private static function parse_order_items($order, $Subscriber)
    {
        $plan_id = $Subscriber->get_plan_id();

        $product_id = Product::get_product_id_by_plan_id($plan_id);

        $product = wc_get_product($product_id);

        foreach ($Subscriber->plan->get_billing_cycles() as $cycle)
        {
            $sequence = intval($cycle['sequence']);

            if (isset($cycle['pricing_scheme']['fixed_price'])) {

                $price = floatval($cycle['pricing_scheme']['fixed_price']['value']);

                $name = "{$cycle['tenure_type']} (period $sequence)";

                $item = self::create_line_item($cycle, $price * self::$quantity, $sequence, $name);

                $order->add_item($item);                

            } elseif (isset($cycle['pricing_scheme']['pricing_model'])) {

                foreach ($cycle['pricing_scheme']['tiers'] as $key => $tier)
                {
                    $ending_quantity = $tier['ending_quantity'] ?? INF;

                    if (self::$quantity >= $tier['starting_quantity'] && self::$quantity <= $ending_quantity) {

                        $tier_num = $key + 1;

                        $price = floatval($tier['amount']['value']);

                        $name = "{$cycle['tenure_type']} (period $sequence) tier $tier_num";

                        $item = self::create_line_item($cycle, $price * self::$quantity, $sequence, $name);

                        $order->add_item($item);

                    }
                }
            }
        }

        $payment_preferences = $Subscriber->plan->get_payment_preferences();

        if(isset($payment_preferences['setup_fee']['value']) && $payment_preferences['setup_fee']['value'] > 0) {

            $fee_amount = floatval($payment_preferences['setup_fee']['value']);

            $fee = self::create_fee('One-time setup fee', $fee_amount);

            $order->add_item($fee);
        }

        $product->set_price(self::$line_item_price);

        $order->add_product($product, self::$quantity);

        foreach ($order->get_items() as $item_id => $item)
        {
            $product = $item->get_product();

            $is_product = $product && $product->exists();

            if (self::$has_trial && $is_product) {

                $item->set_total(0);

                $item->add_meta_data('exclude_from_order_total', ['value' => 'yes']);

            }

            if(self::$tax_rate_data['tax_rate'] !== 0 && empty(self::$tax_rate_data['inclusive'])) {

                $item->set_tax_class(self::$tax_rate_data['tax_rate_slug']);

            }
            
            $item->save();
        }
    }

    public static function insert(Subscriber $Subscriber)
    {   
        self::$tax_rate_data = $Subscriber->plan->get_tax_rate_data();

        self::$quantity = $Subscriber->subscription->quantity ?? self::$quantity;

        $order = wc_create_order();

        $order->set_customer_id($Subscriber->user_id);

        self::parse_order_items($order, $Subscriber);

        $order->set_address(self::get_address($Subscriber), 'billing');

        $order->set_address(self::get_address($Subscriber), 'shipping');

        $order->set_payment_method('paypal');

        $order->set_payment_method_title('Online');

        $order->calculate_shipping();

        $order->calculate_totals();

        $order->set_discount_total(0);

        $order->set_status('processing', 'Order created by Subscriptions for Woo.');

        $order->save();

        remove_filter('pre_option_woocommerce_prices_include_tax', '__return_empty_string');

        return $order->get_id();
    }
}
