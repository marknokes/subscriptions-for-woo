<?php

namespace PPSFWOO;

class Product
{
    /**
     * WooCommerce product type.
     *
     * @var string
     */
    public const TYPE = 'subscription';

    /**
     * Meta key saved with each product. Value is plan id.
     *
     * @var string
     */
    private $plan_id_meta_key;

    /**
     * PayPal environment information.
     *
     * @var array
     */
    private $env;

    /**
     * Constructor for the PayPal class.
     * Initializes the class by setting the environment and plan ID meta key,
     * and adding necessary actions and filters.
     */
    public function __construct()
    {
        $this->env = PayPal::env();

        $this->plan_id_meta_key = self::get_plan_id_meta_key($this->env);

        $this->add_actions();

        $this->add_filters();
    }

    /**
     * Filters the product query by user capability.
     *
     * @param array $query the query to be filtered
     *
     * @return array the filtered query
     */
    public function filter_product_query_by_capability($query)
    {
        if (is_admin()
            && isset($query['post_type'], $query['tax_query'][0]['terms'])
            && 'product' === $query['post_type']
            && current_user_can('ppsfwoo_manage_settings')
        ) {
            array_push($query['tax_query'][0]['terms'], self::TYPE);
        }

        return $query;
    }

    /**
     * Returns the meta key used for storing the PayPal plan ID in the specified environment.
     * If no environment is specified, the default environment from the PayPal class will be used.
     *
     * @param null|string $env the environment for which the meta key should be retrieved
     *
     * @return string the meta key for the specified environment
     */
    public static function get_plan_id_meta_key($env = null)
    {
        $env = $env ?? PayPal::env();

        return "{$env['env']}_ppsfwoo_plan_id";
    }

    /**
     * Generates custom JavaScript for product page.
     */
    public function custom_js()
    {
        if ('product' !== get_post_type()) {
            return;
        }

        $tax_rate_slug = (new Plan())->get_tax_rate_data()['tax_rate_slug'];

        $plans = Plan::get_plans();

        $taxes = [];

        if (sizeof($plans)) {
            foreach ($plans as $plan_id => $plan) {
                if (!empty($plan->taxes)) {
                    $taxes[] = $plan_id;
                }
            }
        }

        ?><script type='text/javascript'>
            jQuery(document).ready(function($) {
                var taxes = <?php echo json_encode($taxes); ?>;
                $('.show_if_simple, .general_options')
                    .addClass('show_if_<?php echo esc_attr(self::TYPE); ?>')
                    .show();
                $('#<?php echo esc_attr($this->plan_id_meta_key); ?>')        
                    .change(function(){
                        var selectedOption = $(this).find('option:selected'),
                            price = selectedOption.data('price').replace('$', '');
                        $('#_regular_price').val(price);
                        if (taxes.includes(selectedOption.val())) {
                            $('#_tax_status').val("taxable");
                            $('#_tax_class').val("<?php echo esc_attr($tax_rate_slug); ?>");
                        } else {
                            $('#_tax_status').val("none");
                            $('#_tax_class').val(""); 
                        }
                    });
            });

        </script><?php
    }

    /**
     * Adds the "Subscription" product type to the given array of product types.
     *
     * @param array $types an array of product types
     *
     * @return array the updated array of product types
     */
    public static function add_product($types)
    {
        if (PPSFWOO_PLUGIN_EXTRAS && !current_user_can('ppsfwoo_manage_subscription_products')) {
            return $types;
        }

        $types[self::TYPE] = 'Subscription';

        return $types;
    }

    /**
     * Adds a custom tab for subscription plans to the product page.
     *
     * @param array $tabs the existing product tabs
     *
     * @return array the updated product tabs
     */
    public static function custom_product_tabs($tabs)
    {
        if (PPSFWOO_PLUGIN_EXTRAS && !current_user_can('ppsfwoo_manage_subscription_products')) {
            return $tabs;
        }

        $tabs[self::TYPE] = [
            'label' => 'Subscription Plan',
            'target' => 'ppsfwoo_options',
            'class' => [
                'show_if_'.self::TYPE,
            ],
            'priority' => 11,
        ];

        return $tabs;
    }

    /**
     * Displays the options for the product tab content.
     */
    public function options_product_tab_content()
    {
        ?>

        <div id='ppsfwoo_options' class='panel woocommerce_options_panel'>

        <div class='options_group'>

        <?php

        $plans = Plan::get_plans();

        if ($plans && !isset($plans['000'])) {
            $options = $this->get_select_options($plans);

            wp_nonce_field('ppsfwoo_plan_id_nonce', 'ppsfwoo_plan_id_nonce', false); ?>
            <p class="form-field">
                <label for="<?php echo esc_attr($this->plan_id_meta_key); ?>">PayPal Subscription Plan</label>
                <select id="<?php echo esc_attr($this->plan_id_meta_key); ?>" name="<?php echo esc_attr($this->plan_id_meta_key); ?>">
                    <?php echo wp_kses($options['html'], $options['wp_kses_options']); ?>
                </select>
            </p><?php
        } else { ?>
            <h3 style="padding: 2em">
                Please be sure your <a href="<?php echo esc_url(admin_url(PluginMain::$ppcp_settings_url)); ?>">connection to PayPal</a>
                is setup and that you've created at least one plan in your <span style="text-decoration: underline;"><?php echo esc_html($this->env['env']); ?></span> environment. <a href="<?php echo esc_url($this->env['paypal_url']); ?>/billing/plans" target="_blank">Create a plan now.</a>
            </h3>
            <?php
        }
        ?>

        </div>

        </div>

        <?php
    }

    /**
     * Edits the CSS for the product page and order page if there is a subscription.
     *
     * @param null|int $order Optional. The order ID. Default null.
     */
    public static function edit_product_css($order = null)
    {
        $screen = get_current_screen();

        if ('product' === $screen->post_type) {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    var style = "<style>ul.wc-tabs li.subscription_options a::before {content: '\\f515' !important}</style>";
                    $('head').append(style);
                });
            </script>
            <?php

        } elseif (isset($order) && 'shop_order' === $screen->post_type && Order::has_subscription($order)) {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $(".wc-order-item-discount").hide();
                });
            </script>
            <?php

        }
    }

    /**
     * Saves the option field for a given product ID.
     *
     * @param int $product_id the ID of the product to save the option field for
     */
    public function save_option_field($product_id)
    {
        if (!isset($_POST[$this->plan_id_meta_key])
            || !isset($_POST['ppsfwoo_plan_id_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ppsfwoo_plan_id_nonce'])), 'ppsfwoo_plan_id_nonce')
        ) {
            wp_die('Security check failed');
        }

        $plan_id = sanitize_text_field(wp_unslash($_POST[$this->plan_id_meta_key]));

        update_post_meta($product_id, $this->plan_id_meta_key, $plan_id);
    }

    /**
     * Retrieves the product ID associated with a given plan ID.
     *
     * @param int $plan_id the ID of the plan to retrieve the product ID for
     *
     * @return int the product ID associated with the given plan ID, or 0 if no product is found
     */
    public static function get_product_id_by_plan_id($plan_id)
    {
        $query = new \WP_Query([
            'post_type' => 'product',
            'posts_per_page' => 1,
            'post_status' => array_values(get_post_stati()),
            'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                [
                    'key' => self::get_plan_id_meta_key(),
                    'value' => $plan_id,
                    'compare' => '=',
                ],
            ],
        ]);

        $products = $query->get_posts();

        return $products ? $products[0]->ID : 0;
    }

    /**
     * Changes the display of the product price to include the frequency of the plan, if applicable.
     *
     * @param string $price the original price of the product
     *
     * @return string the modified price with the frequency of the plan included, if applicable
     */
    public function change_product_price_display($price)
    {
        global $product;

        $product_id = $product ? $product->get_id() : false;

        if (empty($price) || false === $product_id || !$product->is_type(self::TYPE)) {
            return $price;
        }

        $plan_id = get_post_meta($product_id, $this->plan_id_meta_key, true) ?? null;

        $Plan = new Plan($plan_id);

        if ($Plan->frequency) {
            $dom = new \DOMDocument();

            @$dom->loadHTML($price, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            $span = $dom->getElementsByTagName('span');

            foreach ($span as $tag) {
                $current = $tag->nodeValue;

                $new = $current.'/'.ucfirst(strtolower($Plan->frequency));

                $tag->nodeValue = $new;
            }

            return $dom->saveHTML();
        }

        return $price;
    }

    /**
     * Updates the download permissions for a given order.
     *
     * @param int    $order_id       the ID of the order to update permissions for
     * @param string $access_expires Optional. The expiration date for the download access.
     */
    public static function update_download_permissions($order_id, $access_expires = '')
    {
        if (class_exists('\WC_Product')) {
            $order = wc_get_order($order_id);

            if (!$order) {
                return;
            }

            foreach ($order->get_items() as $item) {
                $product = $item->get_product();

                if ($product && $product->exists() && $product->is_downloadable()) {
                    $downloads = $product->get_downloads();

                    foreach (array_keys($downloads) as $download_id) {
                        if (!empty($access_expires)) {
                            new Database(
                                "UPDATE {$GLOBALS['wpdb']->base_prefix}woocommerce_downloadable_product_permissions
                                 SET `access_expires` = %s
                                 WHERE `download_id` = %s
                                 AND `order_id` = %d;",
                                [
                                    $access_expires,
                                    $download_id,
                                    $order_id,
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
                                    $order_id,
                                ]
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * Get the html select options for options_product_tab_content.
     *
     * @param array $plans the available plans
     *
     * @global $post
     *
     * @return string the select option html
     */
    protected function get_select_options($plans)
    {
        global $post;

        $selected_plan_id = get_post_meta($post->ID, $this->plan_id_meta_key, true);

        $formatter = new \NumberFormatter('en_US', \NumberFormatter::CURRENCY);

        $options = "<option value=''>Select a plan [".$this->env['env'].']</option>';

        foreach ($plans as $plan_id => $plan) {
            if ('ACTIVE' !== $plan->status) {
                unset($plans[$plan_id]);
            } else {
                $selected = $selected_plan_id === $plan_id ? 'selected' : '';

                $options .= '<option value="'.esc_attr($plan_id).'" '.$selected.' data-price="'.esc_attr($formatter->formatCurrency($plan->price, 'USD')).'">'.esc_html("{$plan->name} [{$plan->product_name}] [{$plan->frequency}]").'</option>';
            }
        }

        return [
            'html' => $options,
            'wp_kses_options' => [
                'option' => [
                    'value' => [],
                    'selected' => [],
                    'data-price' => [],
                ],
            ],
        ];
    }

    /**
     * Adds necessary actions for the plugin to function properly.
     */
    private function add_actions()
    {
        add_action('woocommerce_product_meta_start', [PayPal::class, 'button']);

        add_action('admin_head', [$this, 'edit_product_css']);

        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'edit_product_css'], 10, 1);

        add_action('woocommerce_product_data_panels', [$this, 'options_product_tab_content']);

        add_action('woocommerce_process_product_meta_'.self::TYPE, [$this, 'save_option_field']);

        add_action('admin_footer', [$this, 'custom_js']);

        add_action('woocommerce_product_data_store_cpt_get_products_query', [$this, 'filter_product_query_by_capability']);
    }

    /**
     * Adds filters to modify product price display, add product types, and customize product data tabs.
     */
    private function add_filters()
    {
        add_filter('woocommerce_get_price_html', [$this, 'change_product_price_display']);

        add_filter('product_type_selector', [$this, 'add_product']);

        add_filter('woocommerce_product_data_tabs', [$this, 'custom_product_tabs']);
    }
}
