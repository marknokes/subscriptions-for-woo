<?php

namespace PPSFWOO;

class SubsForWoo
{
    public static $instance;

    public static $template_dir = ABSPATH . "/wp-content/plugins/subscriptions-for-woo/templates";

    public static $api_namespace = "subscriptions-for-woo/v1";

    public static $endpoint = "/incoming";

    public static $options_group = "ppsfwoo_options_group";

    public static $upgrade_link = "https://wp-subscriptions.com/";

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
                '000' => [
                    'plan_name'     => 'Refresh required',
                    'product_name'  => '',
                    'frequency'     => ''
                ]
            ]
        ]
    ];

    public $client_id,
           $paypal_url,
           $site_url,
           $listen_address,
           $ppsfwoo_subscribed_webhooks,
           $ppsfwoo_webhook_id,
           $ppsfwoo_plans,
           $ppsfwoo_thank_you_page_id,
           $ppsfwoo_rows_per_page,
           $ppsfwoo_delete_plugin_data,
           $user,
           $event_type,
           $plugin_version;

    public function __construct()
    {
        $plugin_data = get_file_data(PPSFWOO_PLUGIN_PATH, [
            'Version' => 'Version',
        ], 'plugin');

        $this->plugin_version = $plugin_data['Version'];

        $env = self::ppsfwoo_get_env();

        $this->client_id = $env['client_id'];

        $this->paypal_url = $env['paypal_url'];

        $this->site_url = network_site_url();

        $this->listen_address = $this->site_url . "/wp-json/" . self::$api_namespace . self::$endpoint;

        foreach (self::$options as $option => $option_value)
        {
            $this->$option = get_option($option);
        }

        register_activation_hook(PPSFWOO_PLUGIN_PATH, [$this, 'ppsfwoo_plugin_activation']);

        register_deactivation_hook(PPSFWOO_PLUGIN_PATH, [$this, 'ppsfwoo_plugin_deactivation']);

        $this->ppsfwoo_add_actions();

        $this->ppsfwoo_add_filters();

        self::$instance = $this;
    }

    protected function ppsfwoo_add_actions()
    {
        add_action('admin_init', [$this, 'ppsfwoo_register_settings']);

        add_action('admin_init', [$this, 'ppsfwoo_handle_export_action']);

        add_action('admin_menu', [$this, 'ppsfwoo_register_options_page']);

        add_action('admin_enqueue_scripts', [$this, 'ppsfwoo_script_handler']);

        add_action('wp_ajax_ppsfwoo_admin_ajax_callback', [$this, 'ppsfwoo_admin_ajax_callback']);

        add_action('wp_ajax_nopriv_ppsfwoo_admin_ajax_callback', [$this, 'ppsfwoo_admin_ajax_callback']);

        add_action('edit_user_profile', [$this, 'ppsfwoo_add_custom_user_fields']);
        
        add_action('rest_api_init', [$this, 'ppsfwoo_rest_api_init']);
        
        add_action('before_woocommerce_init', [$this, 'ppsfwoo_wc_declare_compatibility']);

        add_action('woocommerce_product_meta_start', [$this, 'ppsfwoo_add_custom_paypal_button']);

        add_action('plugins_loaded', 'ppsfwoo_register_product_type');
        
        add_action('woocommerce_product_data_panels', [$this, 'ppsfwoo_options_product_tab_content']);
        
        add_action('woocommerce_process_product_meta_ppsfwoo', [$this, 'ppsfwoo_save_option_field']);

        add_action('admin_head', [$this, 'ppsfwoo_edit_product_css']);

        add_action('wp', [$this, 'ppsfwoo_add_custom_js_for_ajax']);   

        add_action('wp_enqueue_scripts', [$this, 'ppsfwoo_enqueue_styles_on_order_received_page']);     
    }

    protected function ppsfwoo_add_filters()
    {
        add_filter('plugin_action_links_subscriptions-for-woo/subscriptions-for-woo.php', [$this, 'ppsfwoo_settings_link']);

        add_filter('plugin_row_meta', [$this, 'ppsfwoo_plugin_row_meta'], 10, 2);

        add_filter('wp_new_user_notification_email', [$this, 'ppsfwoo_new_user_notification_email'], 10, 4);

        add_filter('woocommerce_get_price_html', [$this, 'ppsfwoo_change_product_price_display']);

        add_filter('product_type_selector', [$this, 'ppsfwoo_add_product']);

        add_filter('woocommerce_product_data_tabs', [$this, 'ppsfwoo_custom_product_tabs']);
    }

    public function ppsfwoo_handle_export_action()
    {
        $export_table = isset($_GET['export_table']) ? sanitize_text_field(wp_unslash($_GET['export_table'])): "";

        if(empty($export_table) || $export_table !== 'true') {

            return;

        }

        if (!isset($_GET['_wpnonce'] ) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'db_export_nonce')) {

            wp_die('Security check failed.');

        }

        global $wpdb;

        $data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ppsfwoo_subscriber", ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if(!isset($data[0])) {

            exit;

        }

        $columns = array_keys($data[0]);

        $values = array();

        foreach ($data as $row)
        {
            $row_values = array_map([$wpdb, 'prepare'], array_fill(0, count($row), '%s'), $row);

            $values[] = '(' . implode(', ', $row_values) . ')';
        }

        $db_name = DB_NAME;

        $sql_content = "INSERT INTO `$db_name`.`{$wpdb->prefix}ppsfwoo_subscriber` (`" . implode('`, `', $columns) . "`) VALUES \n";
            
        $sql_content .= implode(",\n", $values) . ";\n";

        header('Content-Type: application/sql');

        header('Content-Disposition: attachment; filename="table_backup.sql"');

        echo $sql_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        exit();
    }

    public function ppsfwoo_enqueue_styles_on_order_received_page()
    {
        if (is_wc_endpoint_url('order-received') ||
            is_wc_endpoint_url('view-order') ||
            is_page($this->ppsfwoo_thank_you_page_id)
        ) {

            wp_enqueue_style('pp-subs-styles', str_replace("classes/", "", plugin_dir_url(PPSFWOO_PLUGIN_PATH) . "css/my-account.min.css"), [], $this->plugin_version);

        }
    }

    public function ppsfwoo_add_custom_js_for_ajax()
    {
        $subs_id = isset($_GET['subs_id']) ? sanitize_text_field(wp_unslash($_GET['subs_id'])): null;

        if (
            !isset($subs_id) ||
            !isset($_GET['_wpnonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'subs_id_redirect_nonce')
        ) {

            return;

        }

        add_action('wp_footer', function() use ($subs_id) {

            ?>
            <script type="text/javascript">
                var redirected = false;
                function sendAjaxRequest() {
                    if(false === redirected) {
                        jQuery.ajax({
                            url: '/wp-admin/admin-ajax.php',
                            type: 'POST',
                            data: {
                                'action': 'ppsfwoo_admin_ajax_callback',
                                'do'   : 'get_sub',
                                'id'   : '<?php echo esc_attr($subs_id); ?>'
                            },
                            success: function(response) {
                                if("false" !== response) {
                                    redirected = true;
                                    location.href = response
                                }
                            },
                            error: function(xhr, status, error) {
                                if(error) {
                                    console.log('Ajax request failed', error);
                                }
                            }
                        });
                    }
                }
                setInterval(sendAjaxRequest, 1000);
            </script>
            <?php

        });
    }

    public static function ppsfwoo_add_product($types)
    {
        if(PPSFWOO_PERMISSIONS && !current_user_can('ppsfwoo_manage_subscription_products')) {

            return $types;
        }

        $types['ppsfwoo'] = "Subscription";

        return $types;
    }

    public static function ppsfwoo_edit_product_css()
    {
        echo '<style>ul.wc-tabs li.ppsfwoo_options a::before {
          content: "\f515" !important;
        }</style>';
    }

    public static function ppsfwoo_custom_product_tabs($tabs)
    {
        if(PPSFWOO_PERMISSIONS && !current_user_can('ppsfwoo_manage_subscription_products')) {

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

    public function ppsfwoo_options_product_tab_content()
    {
        ?><div id='ppsfwoo_options' class='panel woocommerce_options_panel'><?php

            ?><div class='options_group'><?php

                if($plans = $this->ppsfwoo_plans) {

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

    public function ppsfwoo_save_option_field($post_id)
    {
        if (!isset($_POST['ppsfwoo_plan_id']) || !isset($_POST['ppsfwoo_plan_id_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ppsfwoo_plan_id_nonce'])), 'ppsfwoo_plan_id_nonce')) {

            wp_die("Security check failed");

        }

        $plan_id = sanitize_text_field(wp_unslash($_POST['ppsfwoo_plan_id']));

        update_post_meta($post_id, 'ppsfwoo_plan_id', $plan_id);
    }

    public function ppsfwoo_admin_ajax_callback()
    {  
        $do = isset($_POST['do']) ? sanitize_text_field(wp_unslash($_POST['do'])): ""; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        switch ($do)
        {
            case 'search':

                $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])): ""; // phpcs:ignore WordPress.Security.NonceVerification.Missing

                if(empty($email)) { 

                    return;

                }

                if(!$this->display_subs($email)) {

                    echo esc_attr("false");

                }

                break;

            case 'get_sub':

                $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])): null; // phpcs:ignore WordPress.Security.NonceVerification.Missing

                if(!isset($id)) {

                    return;

                }

                global $wpdb;

                $redirect = false;

                $results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->prepare(
                        "SELECT `wp_customer_id`, `order_id` FROM {$wpdb->prefix}ppsfwoo_subscriber WHERE `id` = %s",
                        $id
                    )
                );

                $order_id = isset($results[0]->order_id) ? $results[0]->order_id: null;

                if ($order = wc_get_order($order_id)) {

                    $redirect = $order->get_checkout_order_received_url();

                    if ($user = get_user_by('id', $results[0]->wp_customer_id)) {

                        wp_set_auth_cookie($user->ID);

                    }

                }

                echo $redirect ? esc_url($redirect): esc_attr("false");

                break;

            case 'resubscribe':

                $success = "false";

                if($webhooks = self::ppsfwoo_paypal_data("/v1/notifications/webhooks")) {

                    if(isset($webhooks['response']['webhooks'])) {

                        foreach($webhooks['response']['webhooks'] as $key => $webhook)
                        {
                            if($this->listen_address === $webhooks['response']['webhooks'][$key]['url']) {

                                $this->ppsfwoo_delete_webhooks($webhooks['response']['webhooks'][$key]['id']);

                            }
                        }

                        $this->ppsfwoo_create_webhooks();

                        $success = "true";

                    }

                }

                echo esc_attr($success);

                break;

            case 'list_webhooks':
                
                if($this->ppsfwoo_webhook_id && $webhooks = $this->ppsfwoo_subscribed_webhooks) {

                    $subscribed = $webhooks;

                } else if($webhooks = self::ppsfwoo_paypal_data("/v1/notifications/webhooks")) {

                    $subscribed = [];

                    if(isset($webhooks['response']['webhooks'])) {

                        foreach($webhooks['response']['webhooks'] as $key => $webhook)
                        {
                            if($webhook['id'] === $this->ppsfwoo_webhook_id) {

                                $subscribed = $webhook;

                            }
                        }

                    }

                    update_option('ppsfwoo_subscribed_webhooks', $subscribed);
                }

                echo isset($subscribed['event_types']) ? wp_json_encode($subscribed['event_types']): "";

                break;

            case 'list_plans':

                echo wp_json_encode($this->ppsfwoo_plans);

                break;

            case 'refresh':

                $success = "false";

                if($plan_data = self::ppsfwoo_paypal_data("/v1/billing/plans")) {

                    $plans = [];

                    if(isset($plan_data['response']['plans'])) {

                        $products = [];

                        foreach($plan_data['response']['plans'] as $plan)
                        {
                            if($plan['status'] !== "ACTIVE") {

                                continue;

                            }

                            $plan_freq = self::ppsfwoo_paypal_data("/v1/billing/plans/{$plan['id']}");

                            if(!in_array($plan['product_id'], array_keys($products))) {
                            
                                $product_data = self::ppsfwoo_paypal_data("/v1/catalogs/products/{$plan['product_id']}");

                                $product_name = $product_data['response']['name'];

                                $products[$plan['product_id']] = $product_name;

                            } else {

                                $product_name = $products[$plan['product_id']];
                            }

                            $plans[$plan['id']] = [
                                'plan_name'     => $plan['name'],
                                'product_name'  => $product_name,
                                'frequency'     => $plan_freq['response']['billing_cycles'][0]['frequency']['interval_unit']
                            ];
                        }
                    
                        update_option('ppsfwoo_plans', $plans);

                        $success = "true";
                    }
                }

                flush_rewrite_rules();

                echo esc_attr($success);

                break;

            default:

                // do nothing

                break;
        }

        wp_die();
    }

    public function display_subs($email = "")
    {
        if(PPSFWOO_PERMISSIONS && !current_user_can('ppsfwoo_manage_subscriptions')) {

            echo "<p>You're user permissions do not allow you to view this content. Please contact your website administrator.</p>";

            return false;

        }

        global $wpdb;

        $per_page = $this->ppsfwoo_rows_per_page ?: 10;

        $subs_page_num = isset($_GET['subs_page_num']) ? sanitize_text_field(wp_unslash($_GET['subs_page_num'])): null; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended

        $page = isset($subs_page_num) ? absint($subs_page_num) : 1;

        $offset = max(0, ($page - 1) * $per_page);

        if($email) {

            $stmt = $wpdb->prepare(
                "SELECT `s`.*
                FROM {$wpdb->prefix}ppsfwoo_subscriber `s`
                JOIN {$wpdb->prefix}users `u`
                    ON `s`.`wp_customer_id` = `u`.`ID`
                WHERE `u`.`user_email` = %s;",
                $email
            );

        } else {

            $stmt = $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ppsfwoo_subscriber LIMIT %d OFFSET %d",
                $per_page,
                $offset
            );

        }

        $results = $wpdb->get_results($stmt); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

        $num_subs = is_array($results) ? sizeof($results): 0;

        if($num_subs) {

            $total_rows = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppsfwoo_subscriber"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            $total_pages = ceil($total_rows / $per_page);

            self::ppsfwoo_display_template("subscriber-table-settings-page", [
                'results'    => $results,
                'paypal_url' => self::ppsfwoo_get_env()['paypal_url']
            ]);

            if($email === "" && $total_pages > 1) {

                echo "<div class='pagination'>Page: ";

                for ($i = 1; $i <= $total_pages; $i++)
                {
                    $href = esc_url(add_query_arg([
                        'page'          => 'subscriptions_for_woo',
                        'subs_page_num' => $i
                    ]));

                    $class = $i === $page ? " current": "";

                    echo "<a href='" . esc_attr($href) . "' class='pagination-link" . esc_attr($class) . "'>" . esc_attr($i) . "</a>";
                }

                echo "</div>";
            }
        }

        return $num_subs;
    }

    public function ppsfwoo_wc_declare_compatibility()
    {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {

            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', PPSFWOO_PLUGIN_PATH);
            
        }
    }

    protected static function ppsfwoo_display_template($template = "", $args = [])
    {
        $template = self::$template_dir . "/$template.php";

        if(!file_exists($template)) {

            return;

        }

        extract($args);
            
        include $template;
    }

    public function ppsfwoo_add_custom_paypal_button()
    {
        global $product;

        if(!$product->is_type('ppsfwoo')) {

            return;
        }
        
        if($plan_id = self::ppsfwoo_get_plan_id_by_product_id(get_the_ID())) {

            echo "<button id='subscribeButton' style='margin-bottom:15px;font-size:1.5em'>Subscribe with PayPal</button>";
    
            echo "<div id='paypal-button-container-" . esc_attr($plan_id) . "'></div>";

            wp_register_script('paypal-sdk', '', [], '', true); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion

            wp_enqueue_script('paypal-sdk');

            $redirect = add_query_arg([
                '_wpnonce' => wp_create_nonce('subs_id_redirect_nonce')
            ], get_permalink($this->ppsfwoo_thank_you_page_id));

            wp_add_inline_script('paypal-sdk', "
                function loadPayPalScript(callback) {
                    if (window.paypal) {
                        callback();
                    } else {
                        var script = document.createElement('script');
                        script.src = 'https://www.paypal.com/sdk/js?client-id=$this->client_id&vault=true&intent=subscription';
                        script.async = true;
                        script.onload = callback;
                        document.body.appendChild(script);
                    }
                }

                function initializePayPalSubscription() {
                    paypal.Buttons({
                        style: {
                            shape: 'rect',
                            layout: 'vertical',
                            color: 'gold',
                            label: 'subscribe'
                        },
                        createSubscription: function(data, actions) {
                            return actions.subscription.create({
                                plan_id: '$plan_id'
                            });
                        },
                        onApprove: function(data, actions) {
                            location.href = '$redirect&subs_id=' + data.subscriptionID;
                        }
                    }).render('#paypal-button-container-$plan_id');
                }

                document.getElementById('subscribeButton').addEventListener('click', function() {
                    this.style.display = 'none';
                    loadPayPalScript(initializePayPalSubscription);
                });
            ");
        }
    }

    public function ppsfwoo_change_product_price_display($price)
    {
        global $product;

        $product_id = $product ? $product->get_id(): false;

        if(false === $product_id || !$product->is_type('ppsfwoo')) {

            return $price;

        }

        if ($frequency = $this->ppsfwoo_get_plan_frequency_by_product_id($product_id)) {

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

    public function ppsfwoo_new_user_notification_email($notification_email, $user, $blogname)
    {
        $key = get_password_reset_key($user);

        if (is_wp_error($key)) { return; }

        $message  = sprintf('Username: %s', $user->user_login) . "\r\n\r\n";

        $message .= 'To set your password, visit the following address:' . "\r\n\r\n";

        $message .= get_permalink(wc_get_page_id('myaccount')) . "?action=rp&key=$key&login=" . rawurlencode($user->user_login) . "\r\n\r\n";

        $notification_email['message'] = $message;

        return $notification_email;
    }

    protected function ppsfwoo_get_page_by_title($title)
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

    protected function ppsfwoo_create_thank_you_page()
    {
        $title = "Thank you for your order";

        $page_id = $this->ppsfwoo_get_page_by_title($title);

        if (!$page_id) {

            $thank_you_template = plugin_dir_url(PPSFWOO_PLUGIN_PATH) . "/templates/thank-you.php";

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

    public function ppsfwoo_plugin_activation()
    {
        foreach (self::$options as $option => $option_value)
        {
            add_option($option, $option_value['default']);
        }

        $this->ppsfwoo_db_install();

        $this->ppsfwoo_create_thank_you_page();

        if(get_transient('ppcp-paypal-bearerppcp-bearer') && !$this->ppsfwoo_webhook_id) {

            $this->ppsfwoo_create_webhooks();

        }
    }

    public function ppsfwoo_plugin_deactivation()
    {
        global $wpdb;

        if("1" === $this->ppsfwoo_delete_plugin_data) {
            
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ppsfwoo_subscriber"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

            if(get_transient('ppcp-paypal-bearerppcp-bearer')) {
            
                $this->ppsfwoo_delete_webhooks();

            }
            
            wp_delete_post($this->ppsfwoo_thank_you_page_id, true);
            
            foreach(self::$options as $option => $option_value) {

                delete_option($option);

            }
        }
    }

    protected function ppsfwoo_create_webhooks()
    {
        $response = self::ppsfwoo_paypal_data("/v1/notifications/webhooks", [
            'url' => $this->listen_address,
            'event_types' => [
                ['name' => 'BILLING.SUBSCRIPTION.ACTIVATED'],
                ['name' => 'BILLING.SUBSCRIPTION.EXPIRED'],
                ['name' => 'BILLING.SUBSCRIPTION.CANCELLED'],
                ['name' => 'BILLING.SUBSCRIPTION.SUSPENDED'],
                ['name' => 'BILLING.SUBSCRIPTION.PAYMENT.FAILED']
            ]
        ], "POST");

        if(isset($response['response']['id'])) {

            update_option('ppsfwoo_webhook_id', $response['response']['id']);
        
        }

        $this->ppfswoo_replace_webhooks();

        return $response['response'] ?? false;
    }

    protected function ppfswoo_replace_webhooks()
    {
        if($webhooks = self::ppsfwoo_paypal_data("/v1/notifications/webhooks")) {

            if(isset($webhooks['response']['webhooks'])) {

                foreach($webhooks['response']['webhooks'] as $key => $webhook)
                {
                    if($this->listen_address !== $webhooks['response']['webhooks'][$key]['url']) {

                        $webhook_id = $webhooks['response']['webhooks'][$key]['id'];

                        $types = [];

                        foreach($webhooks['response']['webhooks'][$key]['event_types'] as $type_key => $type)
                        {
                            if(strpos($type['name'], "BILLING.SUBSCRIPTION") === 0) {

                                unset($webhooks['response']['webhooks'][$key]['event_types'][$type_key]);
                                    
                            }
                        }

                        foreach ($webhooks['response']['webhooks'][$key]['event_types'] as $type)
                        {
                            array_push($types, ['name' => $type['name']]);
                        }

                        $data = [
                            "op"    => "replace",
                            "path"  => "/event_types",
                            "value" => $types
                        ];

                        self::ppsfwoo_paypal_data("/v1/notifications/webhooks/$webhook_id", [$data], "PATCH");
                    }
                }
            }
        }
    }

    protected function ppsfwoo_delete_webhooks($webhook_id = "")
    {
        $webhook_id = $webhook_id ?: $this->ppsfwoo_webhook_id;

        $response = self::ppsfwoo_paypal_data("/v1/notifications/webhooks/$webhook_id", [], "DELETE");

        return $response['response'];
    }

    protected function ppsfwoo_db_install()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $create_table = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ppsfwoo_subscriber ( 
          id varchar(64) NOT NULL,
          wp_customer_id bigint(20) UNSIGNED NOT NULL,
          paypal_plan_id varchar(64) NOT NULL,
          order_id bigint(20) UNSIGNED DEFAULT NULL,
          event_type varchar(35) NOT NULL,
          created datetime NOT NULL,
          last_updated datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          canceled_date date DEFAULT '0000-00-00',
          PRIMARY KEY (id),
          KEY idx_wp_customer_id (wp_customer_id),
          KEY idx_order_id (order_id),
          FOREIGN KEY fk_user_id (wp_customer_id)
            REFERENCES {$wpdb->prefix}users(ID)
            ON UPDATE CASCADE ON DELETE CASCADE,
          FOREIGN KEY fk_order_id (order_id)
            REFERENCES {$wpdb->prefix}wc_orders(id)
            ON UPDATE CASCADE ON DELETE CASCADE
        ) $charset_collate;";

        $wpdb->query($create_table); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /*
    * Registering routes here should be reserved for routes that require a full WP instance.
    * Otherwise, routes should be registered in the parent class's $endpoints
    */
    public function ppsfwoo_rest_api_init()
    {
        register_rest_route(
            self::$api_namespace,
            self::$endpoint, [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'permission_callback' => '__return_true',
                    'callback'            => [$this, 'ppsfwoo_subscription_webhook'],
                    'args'                => [
                        'event_type' => [
                            'validate_callback' => function($param, $request, $key) {
                                return strpos($param, "BILLING.SUBSCRIPTION") === 0;
                            }
                        ]
                    ]
                ],
                [
                    'methods' => \WP_REST_Server::READABLE,
                    'permission_callback' => '__return_true',
                    'callback' => [$this, 'ppsfwoo_subscription_webhook']
                ]
            ]
        );
    }

    public function ppsfwoo_plugin_row_meta($links, $file)
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

        if (!PPSFWOO_PERMISSIONS) {

            return array_merge($links, $upgrade, $bugs);

        }

        return array_merge($links, $bugs);
    }

    public function ppsfwoo_settings_link($links)
    {
        $settings_url = esc_url(admin_url('admin.php?page=subscriptions_for_woo'));

        $settings = ["<a href='$settings_url'>Settings</a>"];
        
        return array_merge($settings, $links);
    }

    public function ppsfwoo_script_handler($hook)
    {
        if ('woocommerce_page_subscriptions_for_woo' !== $hook) {

            return;

        }

        wp_enqueue_style('pp-subs-styles', str_replace("classes/", "", plugin_dir_url(PPSFWOO_PLUGIN_PATH) . "css/style.min.css"), [], $this->plugin_version);

        wp_enqueue_script('pp-subs-scripts', str_replace("classes/", "", plugin_dir_url(PPSFWOO_PLUGIN_PATH) . "js/main.min.js"), ['jquery'], $this->plugin_version, true);
    }

    public function ppsfwoo_register_settings()
    {
        foreach (self::$options as $option => $option_value)
        {
            if('skip_settings_field' === $option_value['type']) continue;
            
            register_setting(self::$options_group, $option);
        }
    }

    public function ppsfwoo_register_options_page()
    {
        add_submenu_page(
            'woocommerce',
            'Settings',
            'Subscriptions',
            'manage_options',
            'subscriptions_for_woo',
            [$this,'ppsfwoo_options_page']
        );
    }

    public function ppsfwoo_add_custom_user_fields($user)
    {
        self::ppsfwoo_display_template("edit-user");
    }

    public function ppsfwoo_options_page()
    {
        include self::$template_dir . "/options-page.php";
    }

    public static function ppsfwoo_get_plan_id_by_product_id($product_id)
    {
        return $product_id ? get_post_meta($product_id, 'ppsfwoo_plan_id', true): "";
    }

    public function ppsfwoo_get_plan_frequency_by_product_id($product_id)
    {
        $plan_id = get_post_meta($product_id, 'ppsfwoo_plan_id', true);

        $plans = $this->ppsfwoo_plans;

        return $product_id && isset($plans[$plan_id]['frequency']) ? $plans[$plan_id]['frequency']: "";
    }

    public static function ppsfwoo_is_token_expired($created, $expiration)
    {
        $currentTime = time();

        $timeDifference = $currentTime - $created;

        if ($timeDifference >= $expiration) {
            return true;
        } else {
            return false;
        }
    }

    protected static function ppsfwoo_refresh_access_token()
    {
        if(!class_exists('\WooCommerce\PayPalCommerce\PPCP')) {

            return;

        }

        $ppcp = new \WooCommerce\PayPalCommerce\PPCP();
                
        $container = $ppcp->container();
                
        do_action('woocommerce_paypal_payments_clear_apm_product_status', $container->get('wcgateway.settings'));

        $token = get_transient('ppcp-paypal-bearerppcp-bearer');

        return $token;
    }


    public static function ppsfwoo_get_paypal_access_token()
    {
        $access_token = "";

        if($token = get_transient('ppcp-paypal-bearerppcp-bearer')) {
        
            $token_data = json_decode($token);

            $access_token = $token_data->access_token ?? "";

        }

        if ("" === $access_token || ($access_token && self::ppsfwoo_is_token_expired($token_data->created, $token_data->expires_in))) {

            $token = self::ppsfwoo_refresh_access_token();

            $token_data = json_decode($token);

            $access_token = $token_data->access_token ?? "";

        }

        return $access_token;

    }

    public static function ppsfwoo_get_env()
    {
        $env = [
            'paypal_api_url' => '',
            'paypal_url'     => '',
            'client_id'      => ''
        ];

        if($settings = get_option('woocommerce-ppcp-settings')) {

            if(isset($settings['sandbox_on']) && $settings['sandbox_on']) {

                $env['paypal_api_url'] = "https://api-m.sandbox.paypal.com";

                $env['paypal_url'] = "https://www.sandbox.paypal.com";

                $env['client_id'] = $settings['client_id_sandbox'];

            } else if(isset($settings['client_id_production'])) {

                $env['paypal_api_url'] = "https://api-m.paypal.com";

                $env['paypal_url'] = "https://www.paypal.com";

                $env['client_id'] = $settings['client_id_production'];

            }
        }

        return $env;

    }

    public function ppsfwoo_respond($response = [])
    {
        header('Content-Type: application/json; charset=utf-8');

        $response = $this->response ?? $response;

        if(!empty($response['status'])) {

            http_response_code($response['status']);

        }

        die(wp_json_encode($response));
    }

    public static function ppsfwoo_paypal_data($api, $payload = [], $method = "GET")
    {    
        if(!$token = self::ppsfwoo_get_paypal_access_token()) {

            return false;
            
        }

        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ]
        ];

        if($payload) {

            $args['body'] = wp_json_encode($payload);

        }

        $url = self::ppsfwoo_get_env()['paypal_api_url'] . $api;

        $remote_response = wp_remote_request($url, $args);

        $response = wp_remote_retrieve_body($remote_response);

        return [
            'response' => json_decode($response, true),
            'status'   => $remote_response['response']['code']
        ];
    }

    protected function ppsfwoo_request_is_from_paypal()
    {
        $request_body = file_get_contents('php://input');

        if(!$request_body) {

            return false;

        }

        $headers = array_change_key_case(getallheaders(), CASE_UPPER);

        if(
            (!array_key_exists('PAYPAL-AUTH-ALGO', $headers)) ||
            (!array_key_exists('PAYPAL-TRANSMISSION-ID', $headers)) ||
            (!array_key_exists('PAYPAL-CERT-URL', $headers)) ||
            (!array_key_exists('PAYPAL-TRANSMISSION-SIG', $headers)) ||
            (!array_key_exists('PAYPAL-TRANSMISSION-TIME', $headers)) 
        )
        {
            return false;
        }

        $response = self::ppsfwoo_paypal_data("/v1/notifications/verify-webhook-signature", [
            'auth_algo'         => $headers['PAYPAL-AUTH-ALGO'],
            'cert_url'          => $headers['PAYPAL-CERT-URL'],
            'transmission_id'   => $headers['PAYPAL-TRANSMISSION-ID'],
            'transmission_sig'  => $headers['PAYPAL-TRANSMISSION-SIG'],
            'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'],
            'webhook_id'        => $this->ppsfwoo_webhook_id,
            'webhook_event'     => json_decode($request_body)
        ], "POST");

        $success = isset($response['response']['verification_status']) ? $response['response']['verification_status']: false;

        return $success === "SUCCESS";
    }

    protected function ppsfwoo_create_wp_user($user_data)
    {
        $user_id = wp_insert_user($user_data);

        if (!is_wp_error($user_id)) {

            wp_new_user_notification($user_id, null, 'user');

            return $user_id;

        } else {

            error_log('Error creating user');

            return false;
        }
    }

    protected function ppsfwoo_create_woocommerce_customer()
    {
        $customer = new \WC_Customer($this->user->user_id);

        $customer->set_billing_first_name($this->user->first_name);

        $customer->set_billing_last_name($this->user->last_name);

        $customer->set_billing_email($this->user->email);

        $customer->set_billing_address_1($this->user->address_line_1);

        $customer->set_billing_address_2($this->user->address_line_2);

        $customer->set_billing_city($this->user->city);

        $customer->set_billing_state($this->user->state);

        $customer->set_billing_postcode($this->user->postal_code);

        $customer->set_billing_country($this->user->country_code);

        $customer->save();
    }

    protected function ppsfwoo_insert_subscriber()
    {
        global $wpdb;

        $wp_user = !empty($this->user->email) ? get_user_by('email', $this->user->email): false;

        $this->user->user_id = $wp_user->ID ?? false;

        if(!$this->user->user_id) {

            $this->user->user_id = $this->ppsfwoo_create_wp_user([
                'user_login' => strtolower($this->user->first_name) . '.' . strtolower($this->user->last_name),
                'user_pass'  => wp_generate_password(12, false),
                'user_email' => $this->user->email,
                'first_name' => $this->user->first_name,
                'last_name'  => $this->user->last_name,
                'role'       => 'customer'
            ]);

            $this->ppsfwoo_create_woocommerce_customer();

        }

        $result = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT `order_id` FROM {$wpdb->prefix}ppsfwoo_subscriber WHERE `id` = %s",
                $this->user->subscription_id
            )
        );

        $order_id = isset($result[0]->order_id) ? $result[0]->order_id: false;

        if(false === $order_id) {

             $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "INSERT INTO {$wpdb->prefix}ppsfwoo_subscriber (
                        `id`,
                        `wp_customer_id`,
                        `paypal_plan_id`,
                        `event_type`,
                        `created`
                    )
                    VALUES (%s,%d,%s,%s,%s)",
                    [
                        $this->user->subscription_id,
                        $this->user->user_id,
                        $this->user->plan_id,
                        $this->event_type,
                        $this->user->create_time
                    ]
                )
            );

        } else {

            $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}ppsfwoo_subscriber SET
                        `paypal_plan_id` = %s,
                        `event_type` = %s,
                        `canceled_date` = '0000-00-00'
                    WHERE `id` = %s;",
                    [
                        $this->user->plan_id,
                        $this->event_type,
                        $this->user->subscription_id
                    ]
                )
            );

            $this->ppsfwoo_update_download_permissions($order_id, 'grant');
        }

        $errors = !empty($wpdb->last_error) ? $wpdb->last_error: false;

        return [
            'errors'               => $errors,
            'action'               => false === $order_id ? 'insert': 'update'
        ];
    }

    protected function ppsfwoo_cancel_subscriber($request)
    {
        global $wpdb;

        $result = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT `order_id` FROM {$wpdb->prefix}ppsfwoo_subscriber WHERE `id` = %s",
                $request['resource']['id']
            )
        );

        if(isset($result[0]->order_id)) {
        
            $this->ppsfwoo_update_download_permissions($result[0]->order_id, 'revoke');

        }
        
        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}ppsfwoo_subscriber SET `event_type` = %s, `canceled_date` = %s WHERE `id` = %s;",
                $this->event_type,
                $request['create_time'],
                $request['resource']['id']
            )
        );

        return [
            'errors'  => $wpdb->last_error
        ];
    }

    protected function ppsfwoo_get_download_count($download_id, $order_id)
    {
        global $wpdb;

        $results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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

    public function ppsfwoo_update_download_permissions($order_id, $action = "grant") {

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
                        $download_count = $this->ppsfwoo_get_download_count($download_id, $order_id);

                        if($action === 'grant' && $default_download_limit === "-1") {

                            $downloads_remaining = "";

                        } else if($action === 'revoke') {

                            $downloads_remaining = "0";

                        } else {

                            $downloads_remaining = (int) $default_download_limit - (int) $download_count;

                        }

                        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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

    protected function ppsfwoo_get_the_product_att($plan_id, $att = "")
    {
        $data = "";

        $products_query = new \WP_Query([
            'post_type'      => 'product',
            'posts_per_page' => -1, 
            'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                [
                    'key'     => 'ppsfwoo_plan_id',
                    'value'   => $plan_id,
                    'compare' => 'LIKE'
                ],
            ],
        ]);

        if ($products_query->have_posts()) {

            while ($products_query->have_posts())
            {
                $products_query->the_post();
                
                switch ($att) {

                    case 'title':

                        $data = get_the_title();

                        break;

                    default:

                        $data = get_the_id();

                        break;
                }
            }
            wp_reset_postdata();
        }

        return $data;
    }

    protected function ppsfwoo_insert_order()
    {   
        $order = wc_create_order();

        $order->set_customer_id($this->user->user_id);

        $order->add_product(wc_get_product($this->ppsfwoo_get_the_product_att($this->user->plan_id, "ID")));

        $address = [
            'first_name' => $this->user->first_name,
            'last_name'  => $this->user->last_name,
            'company'    => '',
            'email'      => $this->user->email,
            'phone'      => '',
            'address_1'  => $this->user->address_line_1,
            'address_2'  => $this->user->address_line_2,
            'city'       => $this->user->city,
            'state'      => $this->user->state,
            'postcode'   => $this->user->postal_code,
            'country'    => $this->user->country_code
        ];

        $order->set_address($address, 'billing');

        $order->set_address($address, 'shipping');

        $order->set_payment_method('paypal');

        $order->set_payment_method_title('Online');

        $order->calculate_shipping();
        
        $order->calculate_totals();
        
        $order->set_status('wc-completed', 'Order created programmatically.');

        $order->save();

        return $order->get_id();
    }

    protected function ppsfwoo_subs()
    {
        global $wpdb;

        $response = $this->ppsfwoo_insert_subscriber();

        if(false === $response['errors'] && 'insert' === $response['action']) {

            $order_id = $this->ppsfwoo_insert_order();

            $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}ppsfwoo_subscriber SET `order_id` = %d WHERE `id` = %s;",
                    $order_id,
                    $this->user->subscription_id
                )
            );
        }

        $this->ppsfwoo_respond([
            "status"    => 200,
            "id"        => $this->user->subscription_id
        ]);
    }

    public function ppsfwoo_create_user_object_from_request($request)
    {
        $user = new \stdClass();

        $user->create_time      = $request['create_time'];

        $user->subscription_id  = $request['resource']['id'];

        $user->plan_id          = $request['resource']['plan_id'];

        $user->email            = $request['resource']['subscriber']['email_address'];

        $user->first_name       = $request['resource']['subscriber']['name']['given_name'];

        $user->last_name        = $request['resource']['subscriber']['name']['surname'];

        $user->address_line_1   = $request['resource']['subscriber']['shipping_address']['address']['address_line_1'];

        $user->address_line_2   = $request['resource']['subscriber']['shipping_address']['address']['address_line_2'] ?? "";

        $user->city             = $request['resource']['subscriber']['shipping_address']['address']['admin_area_2'];

        $user->state            = $request['resource']['subscriber']['shipping_address']['address']['admin_area_1'];

        $user->postal_code      = $request['resource']['subscriber']['shipping_address']['address']['postal_code'];

        $user->country_code     = $request['resource']['subscriber']['shipping_address']['address']['country_code'];

        $this->user = $user;
    }

    public function ppsfwoo_subscription_webhook(\WP_REST_Request $request)
    {
        if(\WP_REST_Server::READABLE === $request->get_method()) {

            $this->ppsfwoo_respond([
                'status'=> 200
            ]);

        }

        if (!$this->ppsfwoo_request_is_from_paypal()) {

            $this->ppsfwoo_respond([
                'status'=> 403,
                'error' => 'Signature invalid'
            ]);

        }

        $this->event_type = $request['event_type'] ?: "";

        switch($this->event_type)
        {
            case 'BILLING.SUBSCRIPTION.ACTIVATED':
                $this->ppsfwoo_create_user_object_from_request($request);
                $this->ppsfwoo_subs();
                break;
            case 'BILLING.SUBSCRIPTION.EXPIRED':
            case 'BILLING.SUBSCRIPTION.CANCELLED':
            case 'BILLING.SUBSCRIPTION.SUSPENDED':
            case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
                $this->ppsfwoo_cancel_subscriber($request);
                break;
        }
    }
}
