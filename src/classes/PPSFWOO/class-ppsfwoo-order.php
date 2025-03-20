<?php

namespace PPSFWOO;

use PPSFWOO\Product;
use PPSFWOO\Subscriber;
use PPSFWOO\Database;

class Order
{
    /**
    * The order total
     *
     * @var int
    */
    private static $order_total = 0;
    /**
    * Whether the subscription has a trial period
     *
     * @var bool
    */
    private static $has_trial = false;
    /**
    * Tax data from the subscription
     *
     * @var array
    */
    private static $tax_rate_data = [];
    /**
    * Order quantity
     *
     * @var int
    */
    private static $quantity = 1;
    /**
    * Price for the individual item
     *
     * @var float
    */
    private static $line_item_price = 0;
    /**
    * The Subscriber object
     *
     * @var object
    */
    private static $Subscriber = null;
    /**
    * Constructor for the class.
     * Adds a filter to exclude certain items from the order subtotal in WooCommerce.
     *
     * @since 1.0.0
    */
    public function __construct()
    {
        add_filter('woocommerce_order_get_subtotal', [$this, 'exclude_from_subtotal'], 10, 2);
    }
    /**
    * Calculates the subtotal of an order, excluding any items marked as excluded from the order total.
     *
     * @param float $subtotal The current subtotal of the order.
     * @param WC_Order $order The order object.
     * @return float The updated subtotal.
    */
    public static function exclude_from_subtotal($subtotal, $order)
    {
        foreach ($order->get_items() as $item_id => $item) {

            if (empty($item->get_total())) {

                $subtotal -= $item->get_subtotal();

            }
        }

        return $subtotal;
    }
    /**
    * Retrieves the order ID associated with a given subscription ID.
     *
     * @param int $subs_id The subscription ID to retrieve the order ID for.
     * @return int|bool The order ID if found, or false if not found.
    */
    public static function get_order_id_by_subscription_id($subs_id)
    {
        $results = new Database("SELECT `order_id` FROM {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber WHERE `id` = %s", [$subs_id]);

        return $results->result[0]->order_id ?? false;
    }
    /**
    * Returns an array containing the subscriber's address information.
     *
     * @return array
    */
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
    /**
    * Checks if an order has a subscription product.
     *
     * @param \WC_Order $order The order to check.
     * @return bool True if the order contains a subscription product, false otherwise.
    */
    public static function has_subscription($order)
    {
        $has_subscription = false;

        if (isset($order) && $order instanceof \WC_Order) {

            foreach ($order->get_items() as $item) {

                $product = $item->get_product();

                if ($product->is_type(Product::TYPE)) {

                    $has_subscription = true;

                    break;

                }
            }

        }

        return $has_subscription;
    }
    /**
    * Creates a line item for a subscription order.
     *
     * @param array $cycle The subscription cycle details.
     * @param float $total The total price for the line item.
     * @param int $sequence The sequence number for the line item.
     * @param string $name The name of the line item.
     * @return \WC_Order_Item_Product The created line item.
    */
    private static function create_line_item($cycle, $total, $sequence, $name)
    {
        $item = new \WC_Order_Item_Product();

        $is_trial_period = $cycle['tenure_type'] === 'TRIAL';

        $is_first_sequence = 1 === $sequence;

        if ($is_first_sequence) {

            $start_time =  (new \DateTime(self::$Subscriber->subscription->start_time))->format('l, F j, Y');

            $name = sprintf(
                ' %s <span class="ppsfwoo-receipt-start-time">starts %s </span>',
                $name,
                $start_time
            );

        }

        $item->set_name($name);

        $item->set_subtotal($total);

        $item->set_quantity(self::$quantity);

        if ($is_trial_period) {

            $item->add_meta_data('is_trial_period', ['value' => 'yes']);

        }

        if ($is_trial_period && $is_first_sequence) {

            $item->set_total($total);

            self::$has_trial = true;

        } else {

            $item->set_total(0);

        }

        if (0 === self::$line_item_price && $cycle['tenure_type'] === 'REGULAR') {

            self::$line_item_price = $total / self::$quantity;

        }

        return $item;
    }
    /**
    * Parses the billing cycles for the current subscriber's plan and creates line items for each cycle.
     *
     * @return array An array of line items for each billing cycle.
    */
    private static function parse_billing_cycles()
    {
        $items = [];

        foreach (self::$Subscriber->plan->get_billing_cycles() as $cycle) {

            $sequence = intval($cycle['sequence']);

            if (isset($cycle['pricing_scheme']['fixed_price'])) {

                $price = floatval($cycle['pricing_scheme']['fixed_price']['value']);

                $name = sprintf(
                    '<span class="ppsfwoo-receipt-period">%s (period %d) </span>
                    <span class="ppsfwoo-receipt-regular-price">@$%.2f/each </span>',
                    $cycle['tenure_type'],
                    $sequence,
                    $price
                );

                $item = self::create_line_item($cycle, $price * self::$quantity, $sequence, $name);

                $items[$sequence] = $item;

            } elseif (isset($cycle['pricing_scheme']['pricing_model'])) {

                foreach ($cycle['pricing_scheme']['tiers'] as $key => $tier) {

                    $ending_quantity = $tier['ending_quantity'] ?? INF;

                    if (self::$quantity >= $tier['starting_quantity'] && self::$quantity <= $ending_quantity) {

                        $tier_num = $key + 1;

                        $price = floatval($tier['amount']['value']);

                        $name = sprintf(
                            '<span class="ppsfwoo-receipt-period">%s (period %d) </span>
                            <span class="ppsfwoo-receipt-regular-price">@$%.2f/each </span>
                            <span class="ppsfwoo-receipt-tier">tier %d </span>',
                            $cycle['tenure_type'],
                            $sequence,
                            $price,
                            $tier_num
                        );

                        $item = self::create_line_item($cycle, $price * self::$quantity, $sequence, $name);

                        $items[$sequence] = $item;

                    }
                }
            }
        }

        return $items;
    }
    /**
    * Parses the order items for a given order.
     *
     * @param WC_Order $order The order to parse items for.
    */
    private static function parse_order_items($order)
    {
        $payment_preferences = self::$Subscriber->plan->get_payment_preferences() ?? [];

        $plan_id = self::$Subscriber->get_plan_id();

        $product_id = Product::get_product_id_by_plan_id($plan_id);

        $product = wc_get_product($product_id);

        $items = self::parse_billing_cycles();

        if (
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

        foreach ($order->get_items() as $item) {

            $product = $item->get_product();

            if (self::$has_trial && $product && $product->exists()) {

                $item->set_subtotal(self::$line_item_price * self::$quantity);

                $item->set_total(0);

            }

            self::set_taxes($item);
        }

        // add additional line items to order
        foreach ($items as $sequence => $item) {

            self::set_taxes($item);

            $order->add_item($item);
        }
    }
    /**
    * Sets taxes for a given item using the provided tax rate data.
     *
     * @param object $item The item for which taxes will be set.
     * @param array|null $tax_rate_data The tax rate data to be used for calculating taxes.
     * @return void
    */
    public static function set_taxes($item, $tax_rate_data = null)
    {
        $tax_rate_data = $tax_rate_data ?? self::$tax_rate_data;

        $taxes = \WC_Tax::get_rates_for_tax_class($tax_rate_data['tax_rate_slug']);

        $found_rate = null;

        if ($tax_rate_data['tax_rate'] === 0 || !empty($tax_rate_data['inclusive']) || !$taxes) {
            $item->set_tax_class("");

            return;
        }

        foreach ($taxes as $tax_rate_object) {

            if ($tax_rate_data['tax_rate'] === $tax_rate_object->tax_rate) {

                $found_rate = $tax_rate_object;

                break;

            }
        }

        if (!isset($found_rate)) {

            $item->set_tax_class("");

            return;

        }

        $tax_rates[ $found_rate->tax_rate_id ] = [
            'rate'     => (float) $found_rate->tax_rate,
            'label'    => $found_rate->tax_rate_name,
            'shipping' => $found_rate->tax_rate_shipping ? 'yes' : 'no',
            'compound' => $found_rate->tax_rate_compound ? 'yes' : 'no',
        ];

        $get_subtotal = method_exists($item, 'get_subtotal') ? 'get_subtotal' : 'get_total';

        $taxes = \WC_Tax::calc_tax($item->get_total(), $tax_rates, false);

        $subtotal_taxes = \WC_Tax::calc_tax($item->$get_subtotal(), $tax_rates, false);

        $item->set_tax_class($tax_rate_data['tax_rate_slug']);

        $item->set_taxes([
            'subtotal' => $subtotal_taxes,
            'total'    => $taxes,
        ]);
    }
    /**
    * Inserts a new subscriber into the system and creates a new order for their subscription.
     *
     * @param Subscriber $Subscriber The subscriber to be inserted.
     * @return int The ID of the newly created order.
    */
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
