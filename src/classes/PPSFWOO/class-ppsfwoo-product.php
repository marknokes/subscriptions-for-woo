<?php

namespace PPSFWOO;

use PPSFWOO\PluginMain,
    PPSFWOO\Database,
    PPSFWOO\PayPal,
    PPSFWOO\Plan;

class Product
{
    const TYPE = "subscription";

    private $PluginMain,
            $env;

	public function __construct()
    {
    	$this->PluginMain = PluginMain::get_instance();

        $this->env = $this->PluginMain->env['env'];

    	$this->add_actions();

    	$this->add_filters();
    }

    private function add_actions()
    {
        add_action('woocommerce_product_meta_start', ['PPSFWOO\PayPal', 'button']);

		add_action('admin_head', [$this, 'edit_product_css']);
        
        add_action('woocommerce_product_data_panels', [$this, 'options_product_tab_content']);
        
        add_action('woocommerce_process_product_meta_' . self::TYPE, [$this, 'save_option_field']);
    }

    private function add_filters()
    {
    	add_filter('woocommerce_get_price_html', [$this, 'change_product_price_display']);

        add_filter('product_type_selector', [$this, 'add_product']);

        add_filter('woocommerce_product_data_tabs', [$this, 'custom_product_tabs']);
    }

	public static function add_product($types)
    {
        if(PPSFWOO_PLUGIN_EXTRAS && !current_user_can('ppsfwoo_manage_subscription_products')) {

            return $types;
        }

        $types[self::TYPE] = "Subscription";

        return $types;
    }

    public static function custom_product_tabs($tabs)
    {
        if(PPSFWOO_PLUGIN_EXTRAS && !current_user_can('ppsfwoo_manage_subscription_products')) {

            return $tabs;
        }
        
        $tabs[self::TYPE] = [
            'label'     => 'Subscription Plan',
            'target'    => 'ppsfwoo_options',
            'class'     => [
                'show_if_' . self::TYPE
            ],
            'priority' => 11
        ];

        return $tabs;
    }

    public function options_product_tab_content()
    {
        ?>

        <div id='ppsfwoo_options' class='panel woocommerce_options_panel'>

            <div class='options_group'><?php

                $Plan = new Plan();

                $plans = $Plan->get_plans();

                if($plans && !isset($plans['000'])) {

                    $formatter = new \NumberFormatter('en_US', \NumberFormatter::CURRENCY);

                    $options = "<option value=''>Select a plan [" . $this->env . "]</option>";

                    foreach($plans as $plan_id => $plan_data)
                    {
                        if("ACTIVE" !== $plan_data['status']) {

                            unset($plans[$plan_id]);

                        } else {

                            $options .= '<option value="' . esc_attr($plan_id) . '" data-price="' . esc_attr($formatter->formatCurrency($plan_data['price'], 'USD')) . '">' . esc_html("{$plan_data['plan_name']} [{$plan_data['product_name']}] [{$plan_data['frequency']}]") . '</option>';

                        }

                    }

                    wp_nonce_field('ppsfwoo_plan_id_nonce', 'ppsfwoo_plan_id_nonce', false);

                    ?>
                    <p class="form-field">
                        <label for="<?php echo esc_attr("{$this->env}_ppsfwoo_plan_id"); ?>">PayPal Subscription Plan</label>
                        <select id="<?php echo esc_attr("{$this->env}_ppsfwoo_plan_id"); ?>" name="<?php echo esc_attr("{$this->env}_ppsfwoo_plan_id"); ?>">
                            <?php echo $options; ?>
                        </select>
                    </p>
                    <script type="text/javascript">
                        jQuery(document).ready(function($){
                            $('#<?php echo esc_attr("{$this->env}_ppsfwoo_plan_id"); ?>')
                                .change(function(){
                                    var $price_field = $('#_regular_price'),
                                        selectedOption = $(this).find('option:selected'),
                                        price = selectedOption.data('price').replace('$', '');
                                    $price_field.val(price);
                                });
                        });
                    </script>
                    <?php
                } else {

                    ?>

                    <h3 style="padding: 2em">

                        Please be sure your <a href="<?php echo esc_url(admin_url($this->PluginMain::$ppcp_settings_url)); ?>">connection to PayPal</a>

                        is setup and that you've created at least one plan in your <span style="text-decoration: underline;"><?php echo esc_html($this->env); ?></span> environment. <a href="<?php echo esc_url($this->PluginMain->env['paypal_url']); ?>/billing/plans" target="_blank">Create a plan now.</a>

                    </h3>

                <?php

                }

            ?></div>

        </div><?php
    }

    public static function edit_product_css()
    {
        wp_enqueue_style('ppsfwoo-edit_product_css', true, [], '1');

        wp_add_inline_style('ppsfwoo-edit_product_css', 'ul.wc-tabs li.ppsfwoo_options a::before { content: "\f515" !important;}');
    }

    public function save_option_field($product_id)
    {
        if (!isset($_POST["{$this->env}_ppsfwoo_plan_id"]) ||
            !isset($_POST['ppsfwoo_plan_id_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ppsfwoo_plan_id_nonce'])), 'ppsfwoo_plan_id_nonce')
        ) {

            wp_die("Security check failed");

        }

        $plan_id = sanitize_text_field(wp_unslash($_POST["{$this->env}_ppsfwoo_plan_id"]));

        update_post_meta($product_id, "{$this->env}_ppsfwoo_plan_id", $plan_id);
    }

    public static function get_product_id_by_plan_id($plan_id)
    {
        $env = PayPal::env()['env'];

        $query = new \WP_Query ([
            'post_type'      => 'product',
            'posts_per_page' => 1, 
            'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                [
                    'key'     => "{$env}_ppsfwoo_plan_id",
                    'value'   => $plan_id,
                    'compare' => '='
                ],
            ],
        ]);

        $products = $query->get_posts();

        return $products ? $products[0]->ID: 0;
    }

	public function change_product_price_display($price)
    {
        global $product;

        $product_id = $product ? $product->get_id(): false;

        if(empty($price) || false === $product_id || !$product->is_type(self::TYPE)) {

            return $price;

        }

        $Plan = new Plan('product_id', $product_id);

        if ($Plan->frequency) {

            $dom = new \DOMDocument();

            @$dom->loadHTML($price, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            $span = $dom->getElementsByTagName('span');

            foreach ($span as $tag)
            {
                $current = $tag->nodeValue;

                $new = $current . "/" . ucfirst(strtolower($Plan->frequency));

                $tag->nodeValue = $new;
            }

            return $dom->saveHTML();
        }

        return $price;
    }

    public static function update_download_permissions($order_id, $access_expires = "")
    {
        if (class_exists('\WC_Product')) {

            $order = wc_get_order($order_id);

            if(!$order) {

                return;

            }

            foreach ($order->get_items() as $item)
            {
                $product = $item->get_product(); 

                if ($product && $product->exists() && $product->is_downloadable()) {
                    
                    $downloads = $product->get_downloads();

                    foreach (array_keys($downloads) as $download_id)
                    {
                        if(!empty($access_expires)) {

                            new Database(
                                "UPDATE {$GLOBALS['wpdb']->base_prefix}woocommerce_downloadable_product_permissions
                                 SET `access_expires` = %s
                                 WHERE `download_id` = %s
                                 AND `order_id` = %d;",
                                [   
                                    $access_expires,
                                    $download_id,
                                    $order_id
                                ]
                            );

                        } else {

                            new Database(
                                "UPDATE {$GLOBALS['wpdb']->base_prefix}woocommerce_downloadable_product_permissions
                                 SET `access_expires` = NULL
                                 WHERE `download_id` = %s
                                 AND `order_id` = %d;",
                                [
                                    $download_id,
                                    $order_id
                                ]
                            );

                        }
                    } 
                } 
            }
        }
    }
}
