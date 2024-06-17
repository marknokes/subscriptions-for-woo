<?php

namespace PPSFWOO;

use PPSFWOO\PluginMain;

class Product
{
	private static $instance = NULL;

    private $PluginMain;

	public function __construct()
    {
    	$this->PluginMain = PluginMain::get_instance();

    	$this->add_actions();

    	$this->add_filters();
    }

    private function add_actions()
    {
		add_action('admin_head', [$this, 'edit_product_css']);

    	add_action('woocommerce_product_meta_start', [$this, 'add_custom_paypal_button']);
        
        add_action('woocommerce_product_data_panels', [$this, 'options_product_tab_content']);
        
        add_action('woocommerce_process_product_meta_ppsfwoo', [$this, 'save_option_field']);
    }

    private function add_filters()
    {
    	add_filter('woocommerce_get_price_html', [$this, 'change_product_price_display']);

        add_filter('product_type_selector', [$this, 'add_product']);

        add_filter('woocommerce_product_data_tabs', [$this, 'custom_product_tabs']);
    }

	public static function get_instance()
    {
        if (self::$instance === null) {

            self::$instance = new self();

        }

        return self::$instance;
    }

	public static function add_product($types)
    {
        if(PPSFWOO_PLUGIN_EXTRAS && !current_user_can('ppsfwoo_manage_subscription_products')) {

            return $types;
        }

        $types['ppsfwoo'] = "Subscription";

        return $types;
    }

    public static function custom_product_tabs($tabs)
    {
        if(PPSFWOO_PLUGIN_EXTRAS && !current_user_can('ppsfwoo_manage_subscription_products')) {

            return $tabs;
        }
        
        $tabs['ppsfwoo'] = [
            'label'     => 'Subscription Plan',
            'target'    => 'ppsfwoo_options',
            'class'     => [
                'show_if_ppsfwoo'
            ],
            'priority' => 11
        ];

        return $tabs;
    }

    public function options_product_tab_content()
    {
        ?><div id='ppsfwoo_options' class='panel woocommerce_options_panel'><?php

            ?><div class='options_group'><?php

                if($plans = $this->PluginMain->ppsfwoo_plans) {

                    foreach($plans as $plan_id => $plan_data)
                    {
                        $plans[$plan_id] = "{$plan_data['product_name']} [{$plan_data['plan_name']}] [{$plan_data['frequency']}]";
                    }

                    wp_nonce_field('ppsfwoo_plan_id_nonce', 'ppsfwoo_plan_id_nonce', false);

                    woocommerce_wp_select([
                        'id'          => 'ppsfwoo_plan_id',
                        'label'       => 'PayPal Subscription Plan',
                        'options'     => $plans,
                        'desc_tip'    => true,
                        'description' => 'Subscription plans created in your PayPal account will be listed here in the format:<br />"Product [Plan] [Frequency]"',
                    ]);
                }

            ?></div>

        </div><?php
    }

    public static function edit_product_css()
    {
        echo '<style>ul.wc-tabs li.ppsfwoo_options a::before {
          content: "\f515" !important;
        }</style>';
    }

    public function save_option_field($post_id)
    {
        if (!isset($_POST['ppsfwoo_plan_id']) ||
            !isset($_POST['ppsfwoo_plan_id_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ppsfwoo_plan_id_nonce'])), 'ppsfwoo_plan_id_nonce')
        ) {

            wp_die("Security check failed");

        }

        $plan_id = sanitize_text_field(wp_unslash($_POST['ppsfwoo_plan_id']));

        update_post_meta($post_id, 'ppsfwoo_plan_id', $plan_id);
    }

    public static function get_plan_id_by_product_id($product_id)
    {
        return $product_id ? get_post_meta($product_id, 'ppsfwoo_plan_id', true): "";
    }

    public static function get_product_id_by_plan_id($plan_id)
    {
        $query = new \WP_Query ([
            'post_type'      => 'product',
            'posts_per_page' => 1, 
            'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                [
                    'key'     => 'ppsfwoo_plan_id',
                    'value'   => $plan_id,
                    'compare' => '='
                ],
            ],
        ]);

        $products = $query->get_posts();

        return $products ? $products[0]->ID: 0;
    }

    public function get_plan_frequency_by_product_id($product_id)
    {
        $plan_id = get_post_meta($product_id, 'ppsfwoo_plan_id', true);

        return $product_id && isset($this->PluginMain->ppsfwoo_plans[$plan_id]['frequency']) ? $this->PluginMain->ppsfwoo_plans[$plan_id]['frequency']: "";
    }

    public function add_custom_paypal_button()
    {
        global $product;

        if(!$product->is_type('ppsfwoo')) {

            return;

        }

        if($plan_id = self::get_plan_id_by_product_id(get_the_ID())) {

            $this->PluginMain::display_template("paypal-button", [
                'plan_id' => $plan_id
            ]);

            wp_enqueue_script('paypal-sdk', $this->PluginMain->plugin_dir_url . "js/paypal-button.min.js", [], $this->PluginMain::plugin_data('Version'), true);

            wp_localize_script('paypal-sdk', 'ppsfwoo_paypal_ajax_var', [
                'client_id' => $this->PluginMain->client_id,
                'plan_id'   => $plan_id,
                'redirect'  => get_permalink($this->PluginMain->ppsfwoo_thank_you_page_id)
            ]);
        }
    }

	public function change_product_price_display($price)
    {
        global $product;

        $product_id = $product ? $product->get_id(): false;

        if(false === $product_id || !$product->is_type('ppsfwoo')) {

            return $price;

        }

        if ($frequency = $this->get_plan_frequency_by_product_id($product_id)) {

            $dom = new \DOMDocument();

            @$dom->loadHTML($price, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            $span = $dom->getElementsByTagName('span');

            foreach ($span as $tag)
            {
                $current = $tag->nodeValue;

                $new = $current . "/" . ucfirst(strtolower($frequency));

                $tag->nodeValue = $new;
            }

            return $dom->saveHTML();
        }

        return $price;
    }

    protected static function get_download_count($download_id, $order_id)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT `download_count` FROM {$wpdb->prefix}woocommerce_downloadable_product_permissions
                WHERE `download_id` = %s
                AND `order_id` = %d;",
                $download_id,
                $order_id
            )
        );

        return isset($results[0]->download_count) ? $results[0]->download_count: 0;
    }

    public static function update_download_permissions($order_id, $action = "grant")
    {
        global $wpdb;

        if (class_exists('\WC_Product')) {

            $order = wc_get_order($order_id);

            if(!$order) {

                return;

            }

            foreach ($order->get_items() as $item)
            {
                $product = $item->get_product(); 

                if ($product && $product->exists() && $product->is_downloadable()) {

                    $default_download_limit = get_post_meta($product->get_id(), '_download_limit', true);
                    
                    $downloads = $product->get_downloads();

                    foreach (array_keys($downloads) as $download_id)
                    {
                        $download_count = self::get_download_count($download_id, $order_id);

                        if($action === 'grant' && $default_download_limit === "-1") {

                            $downloads_remaining = "";

                        } else if($action === 'revoke') {

                            $downloads_remaining = "0";

                        } else {

                            $downloads_remaining = (int) $default_download_limit - (int) $download_count;

                        }

                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                        $wpdb->query(
                            $wpdb->prepare(
                                "UPDATE {$wpdb->prefix}woocommerce_downloadable_product_permissions
                                SET `downloads_remaining` = %s
                                WHERE `download_id` = %s
                                AND `order_id` = %d;",
                                (string) $downloads_remaining,
                                $download_id,
                                $order_id
                            )
                        );              
                    } 
                } 
            }
        }
    }
}