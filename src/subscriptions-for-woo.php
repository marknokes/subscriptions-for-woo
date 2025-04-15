<?php

namespace PPSFWOO;

/*
 * Plugin Name: Subscriptions for Woo
 * Description: Enjoy recurring PayPal subscription payments leveraging WooCommerce and WooCommerce PayPal Payments
 * Requires Plugins: woocommerce, woocommerce-paypal-payments
 * Author: WP Subscriptions
 * Author URI: https://wp-subscriptions.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 2.5.5
 * WC requires at least: 8.6.0
 * WC tested up to: 9.7.1
 * Requires at least: 6.4.3
 * Tested up to: 6.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once 'autoload.php';

spl_autoload_register('ppsfwoo_autoload');

define('PPSFWOO_PLUGIN_PATH', __FILE__);

define('PPSFWOO_PLUGIN_EXTRAS', class_exists(PluginExtras::class));

define('PPSFWOO_ENTERPRISE', class_exists(Enterprise::class));

register_activation_hook(PPSFWOO_PLUGIN_PATH, [PluginMain::class, 'plugin_activation']);

add_action('wc_ajax_ppc-webhooks-resubscribe', [PluginMain::class, 'schedule_webhook_resubscribe']);

add_action('update_option_woocommerce-ppcp-settings', [PluginMain::class, 'schedule_webhook_resubscribe']);

add_action('upgrader_process_complete', [PluginMain::class, 'upgrader_process_complete'], 10, 2);

add_action('ppsfwoo_refresh_plans', [AjaxActionsPriv::class, 'refresh_plans']);

add_action('plugins_loaded', [PluginMain::class, 'plugin_main_init'], 12);
