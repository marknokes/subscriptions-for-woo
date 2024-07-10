<?php

namespace PPSFWOO;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use PPSFWOO\AjaxActions;
use PPSFWOO\AjaxActionsPriv;
use PPSFWOO\Webhook;
use PPSFWOO\PayPal;
use PPSFWOO\DatabaseQuery;

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
            'default' => 0
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
            'default' => '10'
        ],
        'ppsfwoo_delete_plugin_data' => [
            'name'    => 'Delete plugin data on deactivation',
            'type'    => 'checkbox',
            'default' => 0
        ],
        'ppsfwoo_hide_inactive_plans' => [
            'name'    => 'Hide inactive plans',
            'type'    => 'checkbox',
            'default' => 0
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
                        'plan_name'     => 'Refresh required',
                        'product_name'  => '',
                        'frequency'     => '',
                        'status'        => ''
                    ]
                ],
                'production' => [
                    '000' => [
                        'plan_name'     => 'Refresh required',
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
            'default' => 'Subscribe'
        ]
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

        add_action('admin_init', [$this, 'handle_export_action']);

        add_action('admin_init', [$this, 'ppsfwoo_ppcp_updated']);

        add_action('admin_menu', [$this, 'register_options_page']);

        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        
        add_action('edit_user_profile', [$this, 'add_custom_user_fields']);
        
        add_action('rest_api_init', [Webhook::get_instance(), 'rest_api_init']);
        
        add_action('before_woocommerce_init', [$this, 'wc_declare_compatibility']);
    }

    public function add_filters()
    {
        add_filter('plugin_action_links_subscriptions-for-woo/subscriptions-for-woo.php', [$this, 'settings_link']);

        add_filter('plugin_row_meta', [$this, 'plugin_row_meta'], 10, 2);

        add_filter('wp_new_user_notification_email', [$this, 'new_user_notification_email'], 10, 4);
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

        if(
            'ppsfwoo_hide_inactive_plans' === $option_name &&
            false === get_transient('ppsfwoo_refresh_plans_ran')
        ) {

            set_transient('ppsfwoo_refresh_plans_ran', true, 10);

            AjaxActionsPriv::refresh_plans();

        }
    }

    public static function get_option($option_name)
    {
        $cached_value = wp_cache_get($option_name, 'options');
        
        if ($cached_value === false) {

            $option_value = get_option($option_name);

            if ($option_value !== false) {

                wp_cache_set($option_name, $option_value, 'options');

            }

            return $option_value;

        } else {

            return $cached_value;

        }
    }

    public function ppsfwoo_ppcp_updated()
    {
        if(false === get_transient('ppsfwoo_ppcp_updated_ran') && get_transient('ppsfwoo_ppcp_updated')) {

            set_transient('ppsfwoo_ppcp_updated_ran', true, 60);

            Webhook::get_instance()->resubscribe();

            AjaxActionsPriv::refresh_plans();

            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p><strong><?php echo esc_html(self::plugin_data("Name")); ?>:</strong> If you switched from sandbox to production and have existing subscription products, please resave them now with their corresponding plan (if necessary) and republish.</p>
                </div>
                <?php
            });

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

    public function handle_export_action()
    {
        $export_table = isset($_GET['export_table']) ? sanitize_text_field(wp_unslash($_GET['export_table'])): "";

        if(empty($export_table) || $export_table !== 'true') {

            return;

        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'db_export_nonce')) {

            wp_die('Security check failed.');

        }

        header('Content-Type: application/sql');

        header('Content-Disposition: attachment; filename="table_backup.sql"');

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo DatabaseQuery::export();

        exit();
    }

    public function enqueue_frontend()
    {
        if(!is_admin()) {
            
            wp_enqueue_style('ppsfwoo-styles', $this->plugin_dir_url . "css/frontend.min.css", [], self::plugin_data('Version'));

            if (is_product()) {

                global $post;

                $product = wc_get_product($post->ID);

                if ($product && $product->is_type('ppsfwoo') && wp_script_is('ppcp-smart-button', 'enqueued')) {

                    wp_dequeue_script( 'ppcp-smart-button' );

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

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
        $subs_page_num = isset($_GET['subs_page_num']) ? sanitize_text_field(wp_unslash($_GET['subs_page_num'])): NULL;

        $page = isset($subs_page_num) ? absint($subs_page_num) : 1;

        $offset = max(0, ($page - 1) * $per_page);

        if($email) {

            $result = new DatabaseQuery(
                "SELECT `s`.* FROM {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber `s`
                 JOIN {$GLOBALS['wpdb']->base_prefix}users `u`
                 ON `s`.`wp_customer_id` = `u`.`ID`
                 WHERE `u`.`user_email` = %s;",
                [$email]
            );

        } else {

            $result = new DatabaseQuery(
                "SELECT * FROM {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber ORDER BY order_id DESC LIMIT %d OFFSET %d",
                [$per_page, $offset]
            );

        }

        $num_subs = is_array($result->result) ? sizeof($result->result): 0;

        $html = "";

        if($num_subs) {

            ob_start();

            $row_query = new DatabaseQuery("SELECT COUNT(*) AS `count` FROM {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber");

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

    protected static function get_page_by_title($title)
    {
        $query = new \WP_Query([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'title'          => $title,
        ]);

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

        $page_id = self::get_page_by_title($title);

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
            add_option($option_name, $option_value['default'], '', false);
        }

        self::db_install();

        self::create_thank_you_page();

        $Webhook = Webhook::get_instance();

        if(!$Webhook->id()) {

            $Webhook->create();

        }

        AjaxActionsPriv::refresh_plans();
    }

    public function plugin_deactivation()
    {
        if("1" === $this->ppsfwoo_delete_plugin_data) {
            
            new DatabaseQuery("DROP TABLE IF EXISTS {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber");

            Webhook::get_instance()->delete();
            
            wp_delete_post($this->ppsfwoo_thank_you_page_id, true);
            
            foreach(self::$options as $option_name => $option_value) {

                delete_option($option_name);

                self::clear_option_cache($option_name);

            }
        }
    }

    protected static function db_install()
    {
        $create_table = "CREATE TABLE IF NOT EXISTS {$GLOBALS['wpdb']->base_prefix}ppsfwoo_subscriber ( 
          id varchar(64) NOT NULL,
          wp_customer_id bigint(20) UNSIGNED NOT NULL,
          paypal_plan_id varchar(64) NOT NULL,
          order_id bigint(20) UNSIGNED DEFAULT NULL,
          event_type varchar(35) NOT NULL,
          created datetime DEFAULT current_timestamp(),
          last_updated datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          canceled_date datetime DEFAULT NULL,
          PRIMARY KEY (id),
          KEY idx_wp_customer_id (wp_customer_id),
          KEY idx_order_id (order_id),
          FOREIGN KEY fk_user_id (wp_customer_id)
            REFERENCES {$GLOBALS['wpdb']->base_prefix}users(ID)
            ON UPDATE CASCADE ON DELETE CASCADE,
          FOREIGN KEY fk_order_id (order_id)
            REFERENCES {$GLOBALS['wpdb']->base_prefix}wc_orders(id)
            ON UPDATE CASCADE ON DELETE CASCADE
        );";

        new DatabaseQuery($create_table);
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

    public function register_settings()
    {
        foreach (self::$options as $option_name => $option_value)
        {
            if('skip_settings_field' === $option_value['type']) continue;
            
            register_setting(self::$options_group, $option_name);
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
        include $this->template_dir . "/options-page.php";
    }
}
