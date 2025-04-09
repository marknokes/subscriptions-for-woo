<?php

namespace PPSFWOO;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use WooCommerce\PayPalCommerce\PPCP;

class PluginMain
{
    /**
     * Options group used to register settings/options.
     *
     * @var string
     */
    public static $options_group = 'ppsfwoo_options_group';

    /**
     * Link where users can find the various plugins offered.
     *
     * @var string
     */
    public static $upgrade_link = 'https://wp-subscriptions.com/compare-plans/';

    /**
     * Link to the WooCommerce PayPal Payments plugin settings.
     *
     * @var string
     */
    public static $ppcp_settings_url = 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-connection';

    /**
     * Plugin options.
     *
     * @var array
     */
    public static $options = [
        'ppsfwoo_thank_you_page_id' => [
            'name' => 'Order thank you page',
            'type' => 'select',
            'default' => 0,
            'description' => 'Select the page that customers will be redirected to after checkout.',
            'sanitize_callback' => 'absint',
            'meta_key' => 'ppsfwoo_thank_you_page', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        ],
        'ppsfwoo_rows_per_page' => [
            'name' => 'Subscribers Rows Per Page',
            'type' => 'select',
            'options' => [
                '10' => 10,
                '20' => 20,
                '30' => 30,
                '40' => 40,
                '50' => 50,
            ],
            'default' => 10,
            'description' => 'Choose the number of subscribers visible on each page of the Subscribers tab.',
            'sanitize_callback' => 'absint',
        ],
        'ppsfwoo_delete_plugin_data' => [
            'name' => 'Delete plugin data on deactivation',
            'type' => 'checkbox',
            'default' => 0,
            'description' => 'Careful: choosing this will delete all your subscribers locally. Take a backup first!',
            'sanitize_callback' => 'absint',
        ],
        'ppsfwoo_hide_inactive_plans' => [
            'name' => 'Hide inactive plans',
            'type' => 'checkbox',
            'default' => 1,
            'description' => 'Choose to show/hide inactive PayPal plans on the Plans tab.',
            'sanitize_callback' => 'absint',
        ],
        'ppsfwoo_subscribed_webhooks' => [
            'type' => 'skip_settings_field',
            'default' => '',
        ],
        'ppsfwoo_webhook_id' => [
            'type' => 'skip_settings_field',
            'default' => '',
        ],
        'ppsfwoo_plans' => [
            'type' => 'skip_settings_field',
            'default' => [
                'sandbox' => [
                    '000' => [
                        'name' => 'Refresh required',
                        'product_name' => '',
                        'frequency' => '',
                        'status' => '',
                    ],
                ],
                'production' => [
                    '000' => [
                        'name' => 'Refresh required',
                        'product_name' => '',
                        'frequency' => '',
                        'status' => '',
                    ],
                ],
            ],
        ],
        'ppsfwoo_button_text' => [
            'name' => 'Button Text',
            'type' => 'text',
            'default' => 'Subscribe',
            'description' => 'Choose the text displayed on the product page subscribe button.',
            'sanitize_callback' => 'sanitize_text_field',
        ],
        'ppsfwoo_reminder' => [
            'name' => 'Resubscribe Email Reminder (in days)',
            'type' => 'number',
            'default' => 10,
            'is_enterprise' => true,
            'description' => 'Email reminders with a link to resubscribe should be sent this many days before expiration of a canceled subscription. {wc_settings_tab_email}',
            'sanitize_callback' => 'absint',
        ],
        'ppsfwoo_discount_offer_expires' => [
            'name' => 'Offer expires',
            'type' => 'number',
            'min' => 0,
            'default' => 10,
            'is_enterprise' => true,
            'description' => 'Number of days after a subscribtion expiration that the resubscribe offer is valid. 0 = no limit.',
            'sanitize_callback' => 'absint',
        ],
        'ppsfwoo_resubscribe_landing_page_id' => [
            'name' => 'Resubscribe landing page',
            'type' => 'select',
            'is_enterprise' => true,
            'default' => 0,
            'description' => 'Select the page that customers will visit upon resubscribing to a canceled subscription.',
            'sanitize_callback' => 'absint',
            'meta_key' => 'ppsfwoo_resubscribe_landing_page', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        ],
        'ppsfwoo_discount' => [
            'name' => 'Resubscribe Discount Percent',
            'type' => 'number',
            'default' => 10,
            'is_enterprise' => true,
            'description' => 'Percentage discount for canceled subscribers that resubscribe.',
            'sanitize_callback' => 'absint',
        ],
        'ppsfwoo_discount_apply_to_trial' => [
            'name' => 'Apply to Trial',
            'type' => 'checkbox',
            'default' => 0,
            'is_enterprise' => true,
            'description' => 'Upon resubscribing, choose to apply the discount to all trial periods.',
            'sanitize_callback' => 'absint',
        ],
    ];

    /**
     * Options page tabs.
     *
     * @var array
     */
    public static $tabs = [
        'tab-subscribers' => 'Subscribers',
        'tab-plans' => 'Plans',
        'tab-general' => 'General Settings',
        'tab-advanced' => 'Advanced',
    ];

    /**
     * PayPal client id.
     *
     * @var string
     */
    public $client_id;

    /**
     * PayPal url.
     *
     * @var string
     */
    public $paypal_url;

    /**
     * PayPal webhook id.
     *
     * @var string
     */
    public $ppsfwoo_webhook_id;

    /**
     * Array of subscribed webhooks.
     *
     * @var array
     */
    public $ppsfwoo_subscribed_webhooks;

    /**
     * Whether to hide inactive plans in the plugin settings page.
     *
     * @var bool
     */
    public $ppsfwoo_hide_inactive_plans = true;

    /**
     * Array of PayPal plans.
     *
     * @var array
     */
    public $ppsfwoo_plans = [];

    /**
     * Page id for the plugin generated thank you page.
     *
     * @var int
     */
    public $ppsfwoo_thank_you_page_id = 0;

    /**
     * Number of rows per page on the subscribers tab.
     *
     * @var int
     */
    public $ppsfwoo_rows_per_page = 10;

    /**
     * Whether to delete all plugin data on deactivation.
     *
     * @var int
     */
    public $ppsfwoo_delete_plugin_data = 0;

    /**
     * Resubscribe Email Reminder (in days).
     *
     * @var int
     */
    public $ppsfwoo_reminder = 10;

    /**
     * Number of days after a subscribtion expiration that the resubscribe offer is valid. 0 = no limit.
     *
     * @var int
     */
    public $ppsfwoo_discount_offer_expires = 10;

    /**
     * Page that customers will visit upon resubscribing to a canceled subscription.
     *
     * @var string
     */
    public $ppsfwoo_resubscribe_landing_page_id = 0;

    /**
     * Upon resubscribing, choose to apply the discount to all trial periods.
     *
     * @var int
     */
    public $ppsfwoo_discount_apply_to_trial = 0;

    /**
     * Percentage discount for canceled subscribers that resubscribe.
     *
     * @var int
     */
    public $ppsfwoo_discount = 10;

    /**
     * Plugin template directory.
     *
     * @var string
     */
    public $template_dir;

    /**
     * Plugin directory url.
     *
     * @var string
     */
    public $plugin_dir_url;

    /**
     * Button text displayed on subscribe buttons.
     *
     * @var string
     */
    public $ppsfwoo_button_text = 'Subscribe';

    /**
     * PayPal environment information.
     *
     * @var array
     */
    public $env = [];

    /**
     * The PluginMain class instance.
     *
     * @var object
     */
    private static $instance;

    /**
     * Constructor for the class.
     *
     * Sets the environment, template directory, plugin directory URL, client ID, and PayPal URL.
     * Loops through the options array and sets each option name and value.
     * Adds an action to update each option and calls the after_update_option method.
     */
    protected function __construct()
    {
        $this->env = PayPal::env();

        $this->template_dir = plugin_dir_path(PPSFWOO_PLUGIN_PATH).'templates/';

        $this->plugin_dir_url = plugin_dir_url(PPSFWOO_PLUGIN_PATH);

        $this->client_id = $this->env['client_id'];

        $this->paypal_url = $this->env['paypal_url'];

        foreach (self::$options as $option_name => $option_value) {
            if (self::skip_option($option_value)) {
                continue;
            }

            $this->{$option_name} = self::get_option($option_name);

            add_action("update_option_{$option_name}", [$this, 'after_update_option'], 10, 3);
        }
    }

    /**
     * Initializes the plugin on plugins_loaded.
     */
    public static function plugin_main_init()
    {
        if (!class_exists(\WC_Product::class) || !class_exists(PPCP::class)) {
            return;
        }

        global $product;

        $ClassName = 'WC_Product_'.Product::TYPE;

        $ClassDefinition = new class($product) extends \WC_Product
        {
            public $product_type;

            public function __construct($product)
            {
                $this->product_type = Product::TYPE;

                parent::__construct($product);
            }
        };

        class_alias(get_class($ClassDefinition), $ClassName);

        $PluginMain = self::get_instance();

        Database::upgrade();

        register_deactivation_hook(PPSFWOO_PLUGIN_PATH, [$PluginMain, 'plugin_deactivation']);

        $PluginMain->add_actions();

        $PluginMain->add_filters();

        new Product();

        new Order();

        if (PPSFWOO_PLUGIN_EXTRAS) {
            new PluginExtras();
        }
    }

    /**
     * Returns the instance of the class.
     *
     * @return self the instance of the class
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Adds all necessary actions for the plugin.
     */
    public function add_actions()
    {
        add_action('wp_ajax_nopriv_ppsfwoo_admin_ajax_callback', [new AjaxActions(), 'admin_ajax_callback']);

        add_action('wp_ajax_ppsfwoo_admin_ajax_callback', [new AjaxActionsPriv(), 'admin_ajax_callback']);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend'], 11);

        add_action('admin_init', [$this, 'register_settings']);

        add_action('admin_init', [Database::class, 'handle_export_action']);

        add_action('admin_init', [$this, 'check_ppcp_updated']);

        add_action('ppsfwoo_cron_resubscribe_webhooks', [Webhook::get_instance(), 'resubscribe']);

        add_action('admin_menu', [$this, 'register_options_page']);

        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);

        add_action('edit_user_profile', [$this, 'add_custom_user_fields']);

        add_action('rest_api_init', [Webhook::get_instance(), 'rest_api_init']);

        add_action('before_woocommerce_init', [$this, 'wc_declare_compatibility']);

        add_action('ppsfwoo_options_page_tab_menu', [$this, 'options_page_tab_menu'], 10, 1);

        add_action('ppsfwoo_options_page_tab_content', [$this, 'options_page_tab_content'], 10, 1);

        add_action('ppsfwoo_after_options_page', [$this, 'after_options_page']);

        add_action('woocommerce_order_item_meta_end', [$this, 'update_receipt_line_item_totals'], 10, 3);
    }

    /**
     * Adds filters for various WordPress and WooCommerce functions.
     */
    public function add_filters()
    {
        add_filter('plugin_action_links_'.plugin_basename(PPSFWOO_PLUGIN_PATH), [$this, 'settings_link']);

        add_filter('plugin_row_meta', [$this, 'plugin_row_meta'], 10, 2);

        add_filter('wp_new_user_notification_email', [$this, 'new_user_notification_email'], 10, 4);

        add_filter('woocommerce_get_order_item_totals', [$this, 'update_receipt_subtotal'], 10, 2);

        add_filter('woocommerce_email_recipient_customer_processing_order', [$this, 'suppress_processing_order_email'], 10, 2);
    }

    /**
     * Suppresses the processing order email for a given recipient and order.
     *
     * @param string $recipient the email address of the recipient
     * @param Order  $order     the order to check for a subscription
     *
     * @return string the recipient's email address if the order does not have a subscription, otherwise an empty string
     */
    public function suppress_processing_order_email($recipient, $order)
    {
        if (Order::has_subscription($order)) {
            return '';
        }

        return $recipient;
    }

    /**
     * Updates the line item totals for a specific item in an order.
     *
     * @param int    $item_id the ID of the item to update
     * @param object $item    the item object to update
     * @param object $order   the order object to update
     */
    public function update_receipt_line_item_totals($item_id, $item, $order)
    {
        if (!Order::has_subscription($order)) {
            return;
        }

        if (empty($item->get_total())) {
            $item->set_subtotal(0);
        }
    }

    /**
     * Updates the subtotal for a receipt based on the given totals and order.
     *
     * @param array $totals the current totals for the receipt
     * @param Order $order  the order for which the receipt is being updated
     *
     * @return array the updated totals for the receipt
     */
    public function update_receipt_subtotal($totals, $order)
    {
        if (!Order::has_subscription($order)) {
            return $totals;
        }

        $subtotal = Order::exclude_from_subtotal(
            $this->receipt_item_value_as_int($totals['cart_subtotal']['value']),
            $order
        );

        $formatter = new \NumberFormatter('en_US', \NumberFormatter::CURRENCY);

        $totals['cart_subtotal']['value'] = $formatter->formatCurrency($subtotal, 'USD');

        return $totals;
    }

    /**
     * Displays the tab menu for the options page.
     *
     * @param array $tabs An array of tabs to be displayed in the menu. The keys represent the tab IDs and the values represent the display names.
     */
    public function options_page_tab_menu($tabs)
    {
        foreach ($tabs as $tab_id => $display_name) {
            $file = $this->template_dir."tab-content/{$tab_id}.php";

            if (!file_exists($file)) {
                continue;
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $active = isset($_GET['tab']) && $tab_id === $_GET['tab'] ? 'nav-tab-active' : '';

            echo '<a href="'.esc_attr($tab_id).'" class="nav-tab '.esc_attr($active).'">'.esc_html($display_name).'</a>';
        }
    }

    /**
     * Displays the content for each tab on the options page.
     *
     * @param array $tabs an array of tab IDs and display names
     */
    public function options_page_tab_content($tabs)
    {
        foreach ($tabs as $tab_id => $display_name) {
            $file = $this->template_dir."tab-content/{$tab_id}.php";

            if (!file_exists($file)) {
                continue;
            }

            echo '<div id="'.esc_attr($tab_id).'" class="tab-content">';

            include $file;

            echo '</div>';
        }
    }

    /**
     * Displays the content for the "Go Pro" tab on the options page.
     */
    public function after_options_page()
    {
        include $this->template_dir.'tab-content/go-pro.php';
    }

    /**
     * Clears the cached value of a specific option.
     *
     * @param string $option_name the name of the option to clear the cache for
     */
    public static function clear_option_cache($option_name)
    {
        if (array_key_exists($option_name, self::$options)) {
            wp_cache_delete($option_name, 'options');
        }
    }

    /**
     * Clears the option cache and performs specific actions based on the updated option.
     *
     * @param mixed  $old_value   the old value of the option
     * @param mixed  $new_value   the new value of the option
     * @param string $option_name the name of the option being updated
     */
    public function after_update_option($old_value, $new_value, $option_name)
    {
        self::clear_option_cache($option_name);

        switch ($option_name) {
            case 'ppsfwoo_hide_inactive_plans':
                do_action('ppsfwoo_refresh_plans');

                break;

            case 'ppsfwoo_resubscribe_landing_page_id':
            case 'ppsfwoo_thank_you_page_id':
                $meta_key = self::$options[$option_name]['meta_key'];

                update_post_meta($new_value, $meta_key, true);

                delete_post_meta($old_value, $meta_key);

                update_option($option_name, $new_value);

                break;

            default:
                break;
        }
    }

    /**
     * Retrieves the value of a specific option.
     *
     * @param string $option_name the name of the option to retrieve
     *
     * @return false|mixed the value of the option, or false if it does not exist or is skipped
     */
    public static function get_option($option_name)
    {
        if (isset(self::$options[$option_name]) && self::skip_option(self::$options[$option_name])) {
            return false;
        }

        $cached_value = wp_cache_get($option_name, 'options');

        if (false === $cached_value) {
            $option_value = get_option($option_name);

            if (false === $option_value) {
                $option_value = self::$options[$option_name]['default'] ?? false;

                add_option($option_name, $option_value, '', false);
            }

            wp_cache_set($option_name, $option_value, 'options');

            return $option_value;
        }

        return maybe_unserialize($cached_value);
    }

    /**
     * Formats a description string by replacing specific placeholders with links and adding a class if the link is disabled.
     *
     * @param string $string   the description string to be formatted
     * @param bool   $disabled whether the link should be disabled or not
     *
     * @return string the formatted description string
     */
    public static function format_description($string, $disabled)
    {
        $class = $disabled ? 'disabled-link' : '';

        $replacements = [
            '{wc_settings_tab_email}' => "<a class='".$class."' href='".admin_url('admin.php?page=wc-settings&tab=email&section=wc_ppsfwoo_email')."'>Edit / Disable</a>",
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $string);
    }

    /**
     * Schedule a webhook resubscribe event.
     *
     * This function checks if the ppsfwoo_ppcp_updated transient is set and if the ppsfwoo_cron_resubscribe_webhooks event is not already scheduled. If both conditions are met, the transient is set to true and a single event is scheduled to run immediately using the wp_schedule_single_event function. Finally, the wp_cron action is triggered.
     *
     * @static
     */
    public static function schedule_webhook_resubscribe()
    {
        if (!get_transient('ppsfwoo_ppcp_updated') && !wp_next_scheduled('ppsfwoo_cron_resubscribe_webhooks')) {
            set_transient('ppsfwoo_ppcp_updated', true);

            wp_schedule_single_event(time(), 'ppsfwoo_cron_resubscribe_webhooks');

            do_action('wp_cron');
        }
    }

    /**
     * Handles actions after a plugin update has been completed.
     *
     * @param object $upgrader   the Upgrader object
     * @param array  $hook_extra additional data passed to the hook
     */
    public static function upgrader_process_complete($upgrader, $hook_extra)
    {
        if ('update' === $hook_extra['action'] && 'plugin' === $hook_extra['type']) {
            $plugin = $hook_extra['plugins'] ?? $hook_extra['plugin'] ?? [];

            if (self::is_upgrade_target('woocommerce-paypal-payments/woocommerce-paypal-payments.php', $plugin)) {
                self::schedule_webhook_resubscribe();
            } elseif (self::is_upgrade_target(plugin_basename(PPSFWOO_PLUGIN_PATH), $plugin)) {
                Database::upgrade();
            }
        }
    }

    /**
     * Checks if the PayPal for WooCommerce plugin has been updated.
     *
     * If the plugin has been updated, a warning message will be displayed in the admin area, reminding the user to resave any existing subscription products if they have switched from sandbox to production.
     */
    public function check_ppcp_updated()
    {
        if (get_transient('ppsfwoo_ppcp_updated')) {
            add_action('admin_notices', function () {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p><strong><?php echo esc_html(self::plugin_data('Name')); ?>:</strong> If you switched from sandbox to production and have existing subscription products, please resave them now with their corresponding plan (if necessary) and republish.</p>
                </div>
                <?php
            });

            delete_transient('ppsfwoo_ppcp_updated');
        }
    }

    /**
     * Retrieves the specified data from the plugin file.
     *
     * @param string $data the data to retrieve from the plugin file
     *
     * @return mixed the requested data from the plugin file
     */
    public static function plugin_data($data)
    {
        $plugin_data = get_file_data(PPSFWOO_PLUGIN_PATH, [
            'Version' => 'Version',
            'Name' => 'Plugin Name',
        ], 'plugin');

        return $plugin_data[$data];
    }

    /**
     * Enqueues frontend scripts and styles.
     */
    public function enqueue_frontend()
    {
        if (!is_admin()) {
            wp_enqueue_style('ppsfwoo-styles', $this->plugin_dir_url.'css/frontend.min.css', [], self::plugin_data('Version'));

            if (is_product()) {
                global $post;

                $product = wc_get_product($post->ID);

                if ($product && $product->is_type(Product::TYPE) && wp_script_is('ppcp-smart-button', 'enqueued')) {
                    wp_dequeue_script('ppcp-smart-button');
                }
            }
        }

        $subs_id = isset($_GET['subs_id']) ? sanitize_text_field(wp_unslash($_GET['subs_id'])) : null;

        if (
            !isset($subs_id, $_GET['subs_id_redirect_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['subs_id_redirect_nonce'])), AjaxActions::subs_id_redirect_nonce(false))
        ) {
            return;
        }

        wp_enqueue_script('ppsfwoo-scripts', $this->plugin_dir_url.'js/get-sub.min.js', ['jquery'], self::plugin_data('Version'), true);

        wp_localize_script('ppsfwoo-scripts', 'ppsfwoo_ajax_var', [
            'subs_id' => $subs_id,
            'nonce' => wp_create_nonce('ajax_get_sub'),
        ]);
    }

    /**
     * Retrieves subscriber table options page.
     *
     * @param string $email Optional. Subscriber email address.
     *
     * @return array|bool returns an array containing the number of subscribers and the HTML for the table, or false if the user does not have permission to view the content
     */
    public function subscriber_table_options_page($email = '')
    {
        if (PPSFWOO_PLUGIN_EXTRAS && !current_user_can('ppsfwoo_manage_subscriptions')) {
            echo '<p>Your user permissions do not allow you to view this content. Please contact your website administrator.</p>';

            return false;
        }

        $per_page = $this->ppsfwoo_rows_per_page ?: 10;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['subs_page_num']) ? absint($_GET['subs_page_num']) : 1;

        $offset = max(0, ($page - 1) * $per_page);

        if ($email) {
            $result = new Database(
                "SELECT `s`.* FROM {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber `s`
                 JOIN {$GLOBALS['wpdb']->base_prefix}users `u`
                 ON `s`.`wp_customer_id` = `u`.`ID`
                 WHERE `u`.`user_email` = %s;",
                [$email]
            );
        } else {
            $result = new Database(
                "SELECT * FROM {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber ORDER BY order_id DESC LIMIT %d OFFSET %d",
                [$per_page, $offset]
            );
        }

        $num_subs = is_array($result->result) ? sizeof($result->result) : 0;

        $html = '';

        if ($num_subs) {
            ob_start();

            $row_query = new Database("SELECT COUNT(*) AS `count` FROM {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber");

            $total_rows = $row_query->result[0]->count ?? 0;

            $total_pages = ceil($total_rows / $per_page);

            self::display_template('subscriber-table-settings-page', [
                'results' => $result->result,
                'paypal_url' => $this->env['paypal_url'],
            ]);

            if ('' === $email && $total_pages > 1) {
                echo "<div class='pagination'>Page: ";

                for ($i = 1; $i <= $total_pages; ++$i) {
                    $query_string = http_build_query([
                        'tab' => 'tab-subscribers',
                        'subs_page_num' => $i,
                    ]);

                    $class = $i === $page ? ' current' : '';

                    echo "<a href='".esc_url(admin_url('admin.php?page=subscriptions_for_woo&').$query_string)."' class='pagination-link".esc_attr($class)."'>".esc_attr($i).'</a>';
                }

                echo '</div>';
            }

            $html = ob_get_clean();
        }

        return [
            'num_subs' => $num_subs,
            'html' => $html,
        ];
    }

    /**
     * Declares compatibility for the custom order tables feature.
     */
    public function wc_declare_compatibility()
    {
        if (class_exists(FeaturesUtil::class)) {
            FeaturesUtil::declare_compatibility('custom_order_tables', PPSFWOO_PLUGIN_PATH);
        }
    }

    /**
     * Displays a specified template with optional arguments.
     *
     * @param string $template the name of the template file to display
     * @param array  $args     optional arguments to be extracted and passed to the template
     */
    public static function display_template($template = '', $args = [])
    {
        $template = self::get_instance()->template_dir."/{$template}.php";

        if (!file_exists($template)) {
            return;
        }

        extract($args);

        include $template;
    }

    /**
     * Sends a new user notification email with a password reset link.
     *
     * @param array   $notification_email the notification email array
     * @param WP_User $user               the user object
     * @param string  $blogname           the name of the blog
     *
     * @return array the updated notification email array
     */
    public function new_user_notification_email($notification_email, $user, $blogname)
    {
        $key = get_password_reset_key($user);

        if (is_wp_error($key)) {
            return;
        }

        $message = sprintf('Username: %s', $user->user_login)."\r\n\r\n";

        $message .= 'To set your password, visit the following address:'."\r\n\r\n";

        $message .= get_permalink(wc_get_page_id('myaccount'))."?action=rp&key={$key}&login=".rawurlencode($user->user_login)."\r\n\r\n";

        $notification_email['message'] = $message;

        return $notification_email;
    }

    /**
     * Creates a thank you page for the PPSFWOO plugin.
     */
    public static function create_thank_you_page()
    {
        $option_name = 'ppsfwoo_thank_you_page_id';

        $meta_key = self::$options[$option_name]['meta_key'];

        if (get_post_meta(self::get_option($option_name), $meta_key, true)) {
            return;
        }

        $thank_you_template = plugin_dir_url(PPSFWOO_PLUGIN_PATH).'templates/thank-you.php';

        $response = wp_remote_get($thank_you_template);

        $page_id = wp_insert_post([
            'post_title' => 'Thank you for your order',
            'post_content' => wp_remote_retrieve_body($response),
            'post_status' => 'publish',
            'post_type' => 'page',
        ]);

        update_post_meta($page_id, $meta_key, true);

        update_option($option_name, $page_id);
    }

    /**
     * Activates the plugin by setting default options, installing/upgrading the database, creating necessary pages, and refreshing plans.
     */
    public static function plugin_activation()
    {
        foreach (self::$options as $option_name => $option_value) {
            if (self::skip_option($option_value)) {
                continue;
            }

            add_option($option_name, $option_value['default'], '', false);
        }

        Database::install();

        Database::upgrade();

        self::create_thank_you_page();

        if (PPSFWOO_ENTERPRISE) {
            Enterprise::create_resubscribe_page();
        }

        $Webhook = Webhook::get_instance();

        if (!$Webhook->id()) {
            $Webhook->create();
        }

        do_action('ppsfwoo_refresh_plans');
    }

    /**
     * Deactivates the plugin and performs necessary cleanup tasks.
     */
    public function plugin_deactivation()
    {
        delete_transient('ppsfwoo_refresh_plans_ran');

        if ('1' === $this->ppsfwoo_delete_plugin_data) {
            new Database('SET FOREIGN_KEY_CHECKS = 0;');

            new Database("DROP TABLE IF EXISTS {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber;");

            new Database('SET FOREIGN_KEY_CHECKS = 1;');

            Webhook::get_instance()->delete();

            wp_delete_post($this->ppsfwoo_thank_you_page_id, true);

            wp_delete_post($this->ppsfwoo_resubscribe_landing_page_id, true);

            foreach (self::$options as $option_name => $option_value) {
                delete_option($option_name);

                self::clear_option_cache($option_name);
            }

            delete_option('ppsfwoo_db_version');

            wp_cache_delete('ppsfwoo_db_version', 'options');
        }
    }

    /**
     * Adds upgrade and bug submission links to the plugin's row meta.
     *
     * @param array  $links an array of existing plugin row meta links
     * @param string $file  the plugin file path
     *
     * @return array the modified array of plugin row meta links
     */
    public function plugin_row_meta($links, $file)
    {
        if (plugin_basename(PPSFWOO_PLUGIN_PATH) !== $file) {
            return $links;
        }

        $upgrade = [
            'docs' => '<a href="'.esc_url(self::$upgrade_link).'" target="_blank"><span class="dashicons dashicons-star-filled" style="font-size: 14px; line-height: 1.5"></span>Upgrade</a>',
        ];

        $bugs = [
            'bugs' => '<a href="'.esc_url('https://github.com/marknokes/subscriptions-for-woo/issues/new?assignees=marknokes&labels=bug&template=bug_report.md').'" target="_blank">Submit a bug</a>',
        ];

        if (!PPSFWOO_PLUGIN_EXTRAS) {
            return array_merge($links, $upgrade, $bugs);
        }

        return array_merge($links, $bugs);
    }

    /**
     * Adds a "Settings" link to the plugin's list of links on the WordPress admin plugins page.
     *
     * @param array $links the array of links to be displayed on the plugin's list of links
     *
     * @return array the updated array of links, with the "Settings" link added
     */
    public function settings_link($links)
    {
        $settings_url = esc_url(admin_url('admin.php?page=subscriptions_for_woo'));

        $settings = ["<a href='{$settings_url}'>Settings</a>"];

        return array_merge($settings, $links);
    }

    /**
     * Enqueues necessary scripts and styles for the admin page of the Subscriptions for WooCommerce plugin.
     *
     * @param string $hook the current admin page hook
     */
    public function admin_enqueue_scripts($hook)
    {
        global $post_type;

        if ('woocommerce_page_subscriptions_for_woo' !== $hook && 'product' !== $post_type) {
            return;
        }

        wp_enqueue_style('ppsfwoo-styles', $this->plugin_dir_url.'css/style.min.css', [], self::plugin_data('Version'));

        wp_enqueue_script('ppsfwoo-scripts', $this->plugin_dir_url.'js/main.min.js', ['jquery'], self::plugin_data('Version'), true);

        wp_localize_script('ppsfwoo-scripts', 'ppsfwoo_ajax_var', [
            'settings_url' => admin_url(self::$ppcp_settings_url),
            'paypal_url' => $this->env['paypal_url'],
        ]);
    }

    /**
     * Registers the settings for the plugin.
     */
    public function register_settings()
    {
        foreach (self::$options as $option_name => $option_value) {
            if ('skip_settings_field' === $option_value['type'] || self::skip_option($option_value)) {
                continue;
            }

            // phpcs:ignore PluginCheck.CodeAnalysis.SettingSanitization.register_settingDynamic
            register_setting(
                self::$options_group,
                $option_name,
                [
                    'type' => gettype($option_value['default']),
                    'sanitize_callback' => $option_value['sanitize_callback'],
                ]
            );
        }
    }

    /**
     * Registers the options page for the Subscriptions for WooCommerce plugin.
     *
     * This function adds a submenu page under the WooCommerce menu, allowing users with the 'manage_options' capability to access the plugin's settings and options.
     */
    public function register_options_page()
    {
        add_submenu_page(
            'woocommerce',
            'Settings',
            'Subscriptions',
            'manage_options',
            'subscriptions_for_woo',
            [$this, 'options_page']
        );
    }

    /**
     * Adds custom fields to the user profile page.
     *
     * @param object $user the user object
     */
    public function add_custom_user_fields($user)
    {
        self::display_template('edit-user');
    }

    /**
     * Displays the options page for the plugin.
     */
    public function options_page()
    {
        $tabs = self::$tabs;

        include $this->template_dir.'/options-page.php';
    }

    /**
     * Checks if the given plugin is the target for an upgrade.
     *
     * @param string $basename the basename of the plugin
     * @param mixed  $plugin   the plugin to check against
     *
     * @return bool true if the plugin is the target for an upgrade, false otherwise
     */
    protected static function is_upgrade_target($basename, $plugin)
    {
        return (is_array($plugin) && in_array($basename, $plugin)) || (is_string($plugin) && $basename === $plugin);
    }

    /**
     * Checks if the given array contains the necessary information to determine if the option should be skipped.
     *
     * @param array $array the array containing the necessary information
     *
     * @return bool true if the option should be skipped, false otherwise
     */
    protected static function skip_option($array)
    {
        return isset($array['is_premium']) && $array['is_premium'] && !PPSFWOO_PLUGIN_EXTRAS
               || isset($array['is_enterprise']) && $array['is_enterprise'] && !PPSFWOO_ENTERPRISE;
    }

    /**
     * Converts the value of a receipt item to an integer.
     *
     * @param string $item the receipt item to be converted
     *
     * @return int the converted integer value of the receipt item
     */
    private function receipt_item_value_as_int($item)
    {
        $decoded = html_entity_decode($item);

        $stripped = wp_strip_all_tags($decoded);

        $num_only = preg_replace('/[^0-9.]/', '', $stripped);

        return floatval($num_only);
    }
}
