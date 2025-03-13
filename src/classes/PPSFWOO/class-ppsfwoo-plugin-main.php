<?php

namespace PPSFWOO;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

use PPSFWOO\AjaxActions,
    PPSFWOO\AjaxActionsPriv,
    PPSFWOO\Webhook,
    PPSFWOO\PayPal,
    PPSFWOO\Database,
    PPSFWOO\Product,
    PPSFWOO\Order;

class PluginMain
{
    private static $instance = NULL;

    public static $options_group = "ppsfwoo_options_group";

    public static $upgrade_link = "https://wp-subscriptions.com/compare-plans/";

    public static $ppcp_settings_url = "admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-connection";

    public static $options = [
        'ppsfwoo_thank_you_page_id' => [
            'name'    => 'Order thank you page',
            'type'    => 'select',
            'default' => 0,
            'description' => 'Select the page that customers will be redirected to after checkout.',
            'sanitize_callback' => 'absint'
        ],
        'ppsfwoo_rows_per_page' => [
            'name'    => 'Subscribers Rows Per Page',
            'type'    => 'select',
            'options' => [
                '10' => 10,
                '20' => 20,
                '30' => 30,
                '40' => 40,
                '50' => 50
            ],
            'default' => 10,
            'description' => 'Choose the number of subscribers visible on each page of the Subscribers tab.',
            'sanitize_callback' => 'absint'
        ],
        'ppsfwoo_delete_plugin_data' => [
            'name'    => 'Delete plugin data on deactivation',
            'type'    => 'checkbox',
            'default' => 0,
            'description' => 'Careful: choosing this will delete all your subscribers locally. Take a backup first!',
            'sanitize_callback' => 'absint'
        ],
        'ppsfwoo_hide_inactive_plans' => [
            'name'    => 'Hide inactive plans',
            'type'    => 'checkbox',
            'default' => 1,
            'description' => 'Choose to show/hide inactive PayPal plans on the Plans tab.',
            'sanitize_callback' => 'absint'
        ],
        'ppsfwoo_subscribed_webhooks' => [
            'type'    => 'skip_settings_field',
            'default' => ''
        ],
        'ppsfwoo_webhook_id' => [
            'type'    => 'skip_settings_field',
            'default' => ''
        ],
        'ppsfwoo_plans' => [
            'type'    => 'skip_settings_field',
            'default' => [
                'sandbox' => [
                    '000' => [
                        'name'     => 'Refresh required',
                        'product_name'  => '',
                        'frequency'     => '',
                        'status'        => ''
                    ]
                ],
                'production' => [
                    '000' => [
                        'name'     => 'Refresh required',
                        'product_name'  => '',
                        'frequency'     => '',
                        'status'        => ''
                    ]
                ]
            ]
        ],
        'ppsfwoo_button_text' => [
            'name'    => 'Button Text',
            'type'    => 'text',
            'default' => 'Subscribe',
            'description' => 'Choose the text displayed on the product page subscribe button.',
            'sanitize_callback' => 'sanitize_text_field'
        ],
        'ppsfwoo_reminder' => [
            'name'    => 'Resubscribe Email Reminder (in days)',
            'type'    => 'number',
            'default' => 10,
            'is_enterprise' => true,
            'description' => 'Email reminders with a link to resubscribe should be sent this many days before expiration of a canceled subscription. {wc_settings_tab_email}',
            'sanitize_callback' => 'absint'
        ],
        'ppsfwoo_resubscribe_landing_page_id' => [
            'name'    => 'Resubscribe landing page',
            'type'    => 'select',
            'is_enterprise' => true,
            'default' => 0,
            'description' => 'Select the page that customers will visit upon resubscribing to a canceled subscription.',
            'sanitize_callback' => 'absint'
        ],
        'ppsfwoo_discount' => [
            'name'    => 'Resubscribe Discount Percent',
            'type'    => 'number',
            'default' => 10,
            'is_enterprise' => true,
            'description' => 'Percentage discount for canceled subscribers that resubscribe.',
            'sanitize_callback' => 'absint'
        ],
        'ppsfwoo_discount_apply_to_trial' => [
            'name'    => 'Apply to Trial',
            'type'    => 'checkbox',
            'default' => 0,
            'is_enterprise' => true,
            'description' => 'Upon resubscribing, choose to apply the discount to all trial periods.',
            'sanitize_callback' => 'absint'
        ]
    ];

    public static $tabs = [
        'tab-subscribers' => 'Subscribers',
        'tab-plans'       => 'Plans',
        'tab-general'     => 'General Settings',
        'tab-advanced'    => 'Advanced'
    ];

    public $client_id,
           $paypal_url,
           $ppsfwoo_webhook_id,
           $ppsfwoo_subscribed_webhooks,
           $ppsfwoo_hide_inactive_plans,
           $ppsfwoo_plans,
           $ppsfwoo_thank_you_page_id,
           $ppsfwoo_rows_per_page,
           $ppsfwoo_delete_plugin_data,
           $ppsfwoo_reminder,
           $ppsfwoo_resubscribe_landing_page_id,
           $ppsfwoo_discount_apply_to_trial,
           $ppsfwoo_discount,
           $template_dir,
           $plugin_dir_url,
           $ppsfwoo_button_text,
           $env;

    protected function __construct()
    {
        $this->env = PayPal::env();

        $this->template_dir = plugin_dir_path(PPSFWOO_PLUGIN_PATH) . "templates/";

        $this->plugin_dir_url = plugin_dir_url(PPSFWOO_PLUGIN_PATH);

        $this->client_id = $this->env['client_id'];

        $this->paypal_url = $this->env['paypal_url'];

        foreach (self::$options as $option_name => $option_value)
        {
            if(self::skip_option($option_value)) continue;

            $this->$option_name = self::get_option($option_name);

            add_action("update_option_$option_name", [$this, 'after_update_option'], 10, 3);
        }
    }

    public static function get_instance()
    {
        if (self::$instance === null) {
            
            self::$instance = new self();

        }

        return self::$instance;
    }

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

    public function add_filters()
    {
        add_filter('plugin_action_links_' . plugin_basename(PPSFWOO_PLUGIN_PATH), [$this, 'settings_link']);

        add_filter('plugin_row_meta', [$this, 'plugin_row_meta'], 10, 2);

        add_filter('wp_new_user_notification_email', [$this, 'new_user_notification_email'], 10, 4);

        add_filter('woocommerce_get_order_item_totals', [$this, 'update_receipt_subtotal'], 10, 2);

        add_filter('woocommerce_email_recipient_customer_processing_order', [$this, 'suppress_processing_order_email'], 10, 2 );
    }

    public function suppress_processing_order_email($recipient, $order)
    {
        if(Order::has_subscription($order)) {

            return '';

        }

        return $recipient;
    }

    public function update_receipt_line_item_totals($item_id, $item, $order)
    {
        if(!Order::has_subscription($order)) {

            return;

        }

        $meta = $item->get_meta('exclude_from_order_total')['value'] ?? '';

        $exclude_from_order_total = $meta === 'yes';

        if($exclude_from_order_total) {

            $item->set_subtotal($item->get_total());
        
        }
    }

    private function receipt_item_value_as_int($item)
    {
        $decoded = html_entity_decode($item);

        $stripped = wp_strip_all_tags($decoded);

        $num_only = preg_replace('/[^0-9.]/', '', $stripped);

        return floatval($num_only);
    }

    public function update_receipt_subtotal($totals, $order)
    {
        if(!Order::has_subscription($order)) {

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

    public function options_page_tab_menu($tabs)
    {
        foreach ($tabs as $tab_id => $display_name)
        {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $active = isset($_GET['tab']) && $tab_id === $_GET['tab'] ? "nav-tab-active": "";

            echo '<a href="' . esc_attr($tab_id) . '" class="nav-tab ' . esc_attr($active) . '">' . esc_html($display_name) . '</a>';
        }
    }

    public function options_page_tab_content($tabs)
    {
        foreach ($tabs as $tab_id => $display_name)
        {
            $file = $this->template_dir . "tab-content/$tab_id.php";

            if(!file_exists($file)) continue;

            echo '<div id="' . esc_attr($tab_id) . '" class="tab-content">';

            include $file;

            echo '</div>';
        }
    }

    public function after_options_page()
    {
        include $this->template_dir . "tab-content/go-pro.php";
    }

    public static function clear_option_cache($option_name)
    {
        if (array_key_exists($option_name, self::$options)) {
            
            wp_cache_delete($option_name, 'options');

        }
    }

    public function after_update_option($old_value, $new_value, $option_name)
    {
        self::clear_option_cache($option_name);

        if('ppsfwoo_hide_inactive_plans' === $option_name) {

            do_action('ppsfwoo_refresh_plans');

        }
    }

    public static function get_option($option_name)
    {
        if(isset(self::$options[$option_name]) && self::skip_option(self::$options[$option_name])) return false;

        $cached_value = wp_cache_get($option_name, 'options');
        
        if ($cached_value === false) {

            $option_value = get_option($option_name);

            if ($option_value === false) {

                $option_value = self::$options[$option_name]['default'] ?? false;

                add_option($option_name, $option_value, '', false);

            }

            wp_cache_set($option_name, $option_value, 'options');

            return $option_value;

        } else {

            return maybe_unserialize($cached_value);

        }
    }

    public static function format_description($string, $disabled)
    {
        $class = $disabled ? 'disabled-link': '';

        $replacements = [
            '{wc_settings_tab_email}' => "<a class='". $class ."' href='" . admin_url("admin.php?page=wc-settings&tab=email&section=wc_ppsfwoo_email") . "'>Edit / Disable</a>"
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $string);
    }

    public static function schedule_webhook_resubscribe()
    {
        if (!get_transient('ppsfwoo_ppcp_updated') && !wp_next_scheduled('ppsfwoo_cron_resubscribe_webhooks')) {

            set_transient('ppsfwoo_ppcp_updated', true);

            wp_schedule_single_event(time(), 'ppsfwoo_cron_resubscribe_webhooks');

            do_action('wp_cron');
        }
    }

    protected static function is_upgrade_target($basename, $plugin)
    {
        return (is_array($plugin) && in_array($basename, $plugin)) || (is_string($plugin) && $basename === $plugin);
    }

    public static function upgrader_process_complete($upgrader, $hook_extra)
    {
        if ($hook_extra['action'] === 'update' && $hook_extra['type'] === 'plugin') {

            $plugin = $hook_extra['plugins'] ?? $hook_extra['plugin'] ?? [];

            if (self::is_upgrade_target("woocommerce-paypal-payments/woocommerce-paypal-payments.php", $plugin)) {

                self::schedule_webhook_resubscribe();

            } else if (self::is_upgrade_target(plugin_basename(PPSFWOO_PLUGIN_PATH), $plugin)) {

                Database::upgrade();

            }
        }
    }

    public function check_ppcp_updated()
    {
        if(get_transient('ppsfwoo_ppcp_updated')) {

            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p><strong><?php echo esc_html(self::plugin_data("Name")); ?>:</strong> If you switched from sandbox to production and have existing subscription products, please resave them now with their corresponding plan (if necessary) and republish.</p>
                </div>
                <?php
            });

            delete_transient('ppsfwoo_ppcp_updated');
        }
    }

    public static function plugin_data($data)
    {
        $plugin_data = get_file_data(PPSFWOO_PLUGIN_PATH, [
            'Version' => 'Version',
            'Name'    => 'Plugin Name'
        ], 'plugin');

        return $plugin_data[$data];
    }

    public function enqueue_frontend()
    {
        if(!is_admin()) {

            wp_localize_script('ppsfwoo-paypal-button', 'ppsfwoo_paypal_ajax_var', [
                'redirect' => get_permalink($this->ppsfwoo_thank_you_page_id)
            ]);
            
            wp_enqueue_style('ppsfwoo-styles', $this->plugin_dir_url . "css/frontend.min.css", [], self::plugin_data('Version'));

            if (is_product()) {

                global $post;

                $product = wc_get_product($post->ID);

                if ($product && $product->is_type(Product::TYPE) && wp_script_is('ppcp-smart-button', 'enqueued')) {

                    wp_dequeue_script('ppcp-smart-button');

                }

            }

        }
        
        $subs_id = isset($_GET['subs_id']) ? sanitize_text_field(wp_unslash($_GET['subs_id'])): NULL;

        if (
            !isset($subs_id, $_GET['subs_id_redirect_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['subs_id_redirect_nonce'])), AjaxActions::subs_id_redirect_nonce(false))
        ) {

            return;

        }

        wp_enqueue_script('ppsfwoo-scripts', $this->plugin_dir_url . "js/get-sub.min.js", ['jquery'], self::plugin_data('Version'), true);

        wp_localize_script('ppsfwoo-scripts', 'ppsfwoo_ajax_var', [
            'subs_id' => $subs_id,
            'nonce'   => wp_create_nonce('ajax_get_sub')
        ]);
    }

    public function subscriber_table_options_page($email = "")
    {
        if(PPSFWOO_PLUGIN_EXTRAS && !current_user_can('ppsfwoo_manage_subscriptions')) {

            echo "<p>Your user permissions do not allow you to view this content. Please contact your website administrator.</p>";

            return false;

        }

        $per_page = $this->ppsfwoo_rows_per_page ?: 10;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['subs_page_num']) ? absint($_GET['subs_page_num']):  1;

        $offset = max(0, ($page - 1) * $per_page);

        if($email) {

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

        $num_subs = is_array($result->result) ? sizeof($result->result): 0;

        $html = "";

        if($num_subs) {

            ob_start();

            $row_query = new Database("SELECT COUNT(*) AS `count` FROM {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber");

            $total_rows = $row_query->result[0]->count ?? 0;

            $total_pages = ceil($total_rows / $per_page);

            self::display_template("subscriber-table-settings-page", [
                'results'    => $result->result,
                'paypal_url' => $this->env['paypal_url']
            ]);

            if($email === "" && $total_pages > 1) {

                echo "<div class='pagination'>Page: ";

                for ($i = 1; $i <= $total_pages; $i++)
                {
                    $query_string = http_build_query([
                        'tab'           => 'tab-subscribers',
                        'subs_page_num' => $i
                    ]);

                    $class = $i === $page ? " current": "";

                    echo "<a href='" . esc_url(admin_url('admin.php?page=subscriptions_for_woo&') . $query_string) . "' class='pagination-link" . esc_attr($class) . "'>" . esc_attr($i) . "</a>";
                }

                echo "</div>";
            }

            $html = ob_get_clean();
        }

        return [
            'num_subs' => $num_subs,
            'html'     => $html
        ];
    }

    public function wc_declare_compatibility()
    {
        if (class_exists(FeaturesUtil::class)) {

            FeaturesUtil::declare_compatibility('custom_order_tables', PPSFWOO_PLUGIN_PATH);
            
        }
    }

    public static function display_template($template = "", $args = [])
    {
        $template = self::get_instance()->template_dir . "/$template.php";

        if(!file_exists($template)) {

            return;

        }

        extract($args);
            
        include $template;
    }

    public function new_user_notification_email($notification_email, $user, $blogname)
    {
        $key = get_password_reset_key($user);

        if (is_wp_error($key)) { return; }

        $message  = sprintf('Username: %s', $user->user_login) . "\r\n\r\n";

        $message .= 'To set your password, visit the following address:' . "\r\n\r\n";

        $message .= get_permalink(wc_get_page_id('myaccount')) . "?action=rp&key=$key&login=" . rawurlencode($user->user_login) . "\r\n\r\n";

        $notification_email['message'] = $message;

        return $notification_email;
    }

    public static function get_post_by_title($args = [])
    {        
        $args['post_status'] = array_values(get_post_stati());

        $query = new \WP_Query($args);

        if ($query->have_posts()) {

            $query->the_post();
            
            $page_id = get_the_ID();
            
            wp_reset_postdata();
            
            return $page_id;
        
        } else {
        
            return false;
        
        }
    }

    protected static function create_thank_you_page()
    {
        $title = "Thank you for your order";

        $page_id = self::get_post_by_title([
            'post_type'   => 'page',
            'title'       => $title,
        ]);

        if (!$page_id) {

            $thank_you_template = plugin_dir_url(PPSFWOO_PLUGIN_PATH) . "templates/thank-you.php";

            $response = wp_remote_get($thank_you_template);

            $page_id = wp_insert_post([
                'post_title'     => $title,
                'post_content'   => wp_remote_retrieve_body($response),
                'post_status'    => 'publish',
                'post_type'      => 'page'
            ]);

        }

        update_option('ppsfwoo_thank_you_page_id', $page_id);
    }

    public static function plugin_activation()
    {
        foreach (self::$options as $option_name => $option_value)
        {
            if(self::skip_option($option_value)) continue;

            add_option($option_name, $option_value['default'], '', false);
        }

        Database::install();

        Database::upgrade();

        self::create_thank_you_page();

        if(PPSFWOO_ENTERPRISE) {

            Enterprise::create_resubscribe_page();

        }

        $Webhook = Webhook::get_instance();

        if(!$Webhook->id()) {

            $Webhook->create();

        }

        do_action('ppsfwoo_refresh_plans');
    }

    public function plugin_deactivation()
    {
        delete_transient('ppsfwoo_refresh_plans_ran');

        if("1" === $this->ppsfwoo_delete_plugin_data) {
            
            new Database("SET FOREIGN_KEY_CHECKS = 0;");

            new Database("DROP TABLE IF EXISTS {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber;");

            new Database("SET FOREIGN_KEY_CHECKS = 1;");

            Webhook::get_instance()->delete();
            
            wp_delete_post($this->ppsfwoo_thank_you_page_id, true);

            wp_delete_post($this->ppsfwoo_resubscribe_landing_page_id, true);
            
            foreach(self::$options as $option_name => $option_value) {

                delete_option($option_name);

                self::clear_option_cache($option_name);

            }

            delete_option('ppsfwoo_db_version');

            wp_cache_delete('ppsfwoo_db_version', 'options');
        }
    }

    public function plugin_row_meta($links, $file)
    {
        if(plugin_basename(PPSFWOO_PLUGIN_PATH) !== $file) {

            return $links;

        }

        $upgrade = [
            'docs' => '<a href="' . esc_url(self::$upgrade_link) . '" target="_blank"><span class="dashicons dashicons-star-filled" style="font-size: 14px; line-height: 1.5"></span>Upgrade</a>'
        ];

        $bugs = [
            'bugs' => '<a href="' . esc_url("https://github.com/marknokes/subscriptions-for-woo/issues/new?assignees=marknokes&labels=bug&template=bug_report.md") . '" target="_blank">Submit a bug</a>'
        ];

        if (!PPSFWOO_PLUGIN_EXTRAS) {

            return array_merge($links, $upgrade, $bugs);

        }

        return array_merge($links, $bugs);
    }

    public function settings_link($links)
    {
        $settings_url = esc_url(admin_url('admin.php?page=subscriptions_for_woo'));

        $settings = ["<a href='$settings_url'>Settings</a>"];
        
        return array_merge($settings, $links);
    }

    public function admin_enqueue_scripts($hook)
    {
        if ('woocommerce_page_subscriptions_for_woo' !== $hook) {

            return;

        }

        wp_enqueue_style('ppsfwoo-styles', $this->plugin_dir_url . "css/style.min.css", [], self::plugin_data('Version'));

        wp_enqueue_script('ppsfwoo-scripts', $this->plugin_dir_url . "js/main.min.js", ['jquery'], self::plugin_data('Version'), true);

        wp_localize_script('ppsfwoo-scripts', 'ppsfwoo_ajax_var', [
            'settings_url' => admin_url(self::$ppcp_settings_url),
            'paypal_url'   => $this->env['paypal_url']
        ]);
    }

    protected static function skip_option($array)
    {
        return isset($array['is_premium']) && $array['is_premium'] && !PPSFWOO_PLUGIN_EXTRAS ||
               isset($array['is_enterprise']) && $array['is_enterprise'] && !PPSFWOO_ENTERPRISE;
    }

    public function register_settings()
    {
        foreach (self::$options as $option_name => $option_value)
        {
            if('skip_settings_field' === $option_value['type'] || self::skip_option($option_value)) continue;

            // phpcs:ignore PluginCheck.CodeAnalysis.SettingSanitization.register_settingDynamic
            register_setting(
                self::$options_group,
                $option_name,
                [
                    'type'              => gettype($option_value['default']),
                    'sanitize_callback' => $option_value['sanitize_callback']
                ]
            );
        }
    }

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

    public function add_custom_user_fields($user)
    {
        self::display_template("edit-user");
    }

    public function options_page()
    {
        $tabs = self::$tabs;
        
        include $this->template_dir . "/options-page.php";
    }
}
