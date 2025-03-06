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

    private static $Subscriber = NULL;

    public function __construct()
    {
        add_filter('woocommerce_order_get_subtotal', [$this, 'exclude_from_subtotal'], 10, 2);
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

    public static function get_address()
    {
        return [
            'first_name' => self::$Subscriber->first_name,
            'last_name'  => self::$Subscriber->last_name,
            'company'    => '',
            'email'      => self::$Subscriber->email,
            'phone'      => '',
            'address_1'  => self::$Subscriber->address_line_1,
            'address_2'  => self::$Subscriber->address_line_2,
            'city'       => self::$Subscriber->city,
            'state'      => self::$Subscriber->state,
            'postcode'   => self::$Subscriber->postal_code,
            'country'    => self::$Subscriber->country_code
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

    private static function parse_billing_cycles()
    {
        $items = [];

        foreach (self::$Subscriber->plan->get_billing_cycles() as $cycle)
        {
            $sequence = intval($cycle['sequence']);

            if (isset($cycle['pricing_scheme']['fixed_price'])) {

                $price = floatval($cycle['pricing_scheme']['fixed_price']['value']);

                $name = "{$cycle['tenure_type']} (period $sequence)";

                $item = self::create_line_item($cycle, $price * self::$quantity, $sequence, $name);
  
                $items[$sequence] = $item;             

            } elseif (isset($cycle['pricing_scheme']['pricing_model'])) {

                foreach ($cycle['pricing_scheme']['tiers'] as $key => $tier)
                {
                    $ending_quantity = $tier['ending_quantity'] ?? INF;

                    if (self::$quantity >= $tier['starting_quantity'] && self::$quantity <= $ending_quantity) {

                        $tier_num = $key + 1;

                        $price = floatval($tier['amount']['value']);

                        $name = "{$cycle['tenure_type']} (period $sequence) tier $tier_num";

                        $item = self::create_line_item($cycle, $price * self::$quantity, $sequence, $name);

                        $items[$sequence] = $item;

                    }
                }
            }
        }

        return $items;
    }

    private static function parse_order_items($order)
    {
        $payment_preferences = self::$Subscriber->plan->get_payment_preferences() ?? [];

        $plan_id = self::$Subscriber->get_plan_id();

        $product_id = Product::get_product_id_by_plan_id($plan_id);

        $product = wc_get_product($product_id);

        $items = self::parse_billing_cycles();

        if(
            isset($payment_preferences['setup_fee']['value'])
            && $payment_preferences['setup_fee']['value'] > 0
        ) {

            $fee = new \WC_Order_Item_Fee();
            
            $fee->set_name('One-time setup fee');

            $fee->set_total(floatval($payment_preferences['setup_fee']['value']));

            array_push($items, $fee);

        }

        // handle adding product to order first & seperately
        $product->set_price(self::$line_item_price);

        $order->add_product($product, self::$quantity);

        foreach ($order->get_items() as $item)
        {
            $product = $item->get_product();

            if (self::$has_trial && $product && $product->exists()) {

                $item->set_subtotal(self::$line_item_price * self::$quantity);

                $item->set_total(0);

                $item->add_meta_data('exclude_from_order_total', ['value' => 'yes']);

            }

            self::set_taxes($item);
        }

        // add additional line items to order
        foreach($items as $sequence => $item)
        {
            self::set_taxes($item);

            $order->add_item($item);
        }
    }

    public static function set_taxes($item, $tax_rate_data = NULL)
    {
        $tax_rate_data = $tax_rate_data ?? self::$tax_rate_data;

        $taxes = \WC_Tax::get_rates_for_tax_class($tax_rate_data['tax_rate_slug']);

        $found_rate = NULL;

        if($tax_rate_data['tax_rate'] === 0 || !empty($tax_rate_data['inclusive']) || !$taxes)
        {
            $item->set_tax_class("");

            return;
        }

        foreach ($taxes as $tax_rate_object)
        {
            if($tax_rate_data['tax_rate'] === $tax_rate_object->tax_rate) {

                $found_rate = $tax_rate_object;

                break;

            }
        }

        if(!isset($found_rate)) {

            $item->set_tax_class("");

            return;

        }

        $tax_rates[ $found_rate->tax_rate_id ] = [
            'rate'     => (float) $found_rate->tax_rate,
            'label'    => $found_rate->tax_rate_name,
            'shipping' => $found_rate->tax_rate_shipping ? 'yes' : 'no',
            'compound' => $found_rate->tax_rate_compound ? 'yes' : 'no',
        ];

        $get_subtotal = method_exists($item, 'get_subtotal') ? 'get_subtotal': 'get_total';

        $taxes = \WC_Tax::calc_tax($item->get_total(), $tax_rates, false);

        $subtotal_taxes = \WC_Tax::calc_tax($item->$get_subtotal(), $tax_rates, false);

        $item->set_tax_class($tax_rate_data['tax_rate_slug']);
        
        $item->set_taxes([
            'subtotal' => $subtotal_taxes,
            'total'    => $taxes,
        ]);
    }

    public static function insert(Subscriber $Subscriber)
    {   
        self::$Subscriber = $Subscriber;

        self::$tax_rate_data = self::$Subscriber->plan->get_tax_rate_data();

        self::$quantity = self::$Subscriber->subscription->quantity ?? self::$quantity;

        $order = wc_create_order();

        $order->set_customer_id(self::$Subscriber->user_id);

        self::parse_order_items($order);

        $order->set_address(self::get_address(), 'billing');

        $order->set_address(self::get_address(), 'shipping');

        $order->set_payment_method('paypal');

        $order->set_payment_method_title('Online');

        $order->calculate_shipping();

        $order->update_taxes();

        $order->calculate_totals(false);

        $order->set_discount_total(0);

        $order->set_status('processing', 'Order created by Subscriptions for Woo.');

        $order->save();

        remove_filter('pre_option_woocommerce_prices_include_tax', '__return_empty_string');

        return $order->get_id();
    }
}
