<?php
/**
 * Plugin Name: Subscriptions for Woo
 * Description: Enjoy recurring PayPal subscription payments leveraging WooCommerce and WooCommerce PayPal Payments
 * Requires Plugins: woocommerce, woocommerce-paypal-payments
 * Author: WP Subscriptions
 * Author URI: https://wp-subscriptions.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 2.1.5
 * WC requires at least: 8.6.0
 * WC tested up to: 8.9.2
 * Requires at least: 6.4.3
 * Tested up to: 6.5.4
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

require_once 'classes/class-ppsfwoo-custom-product.php';

require_once 'autoload.php';

spl_autoload_register('ppsfwoo_autoload');

define('PPSFWOO_PLUGIN_PATH', __FILE__);

define('PPSFWOO_PLUGIN_EXTRAS', class_exists(\PPSFWOO\PluginExtras::class));

\PPSFWOO\PluginMain::get_instance(true);

\PPSFWOO\Product::get_instance();

if(PPSFWOO_PLUGIN_EXTRAS) { new \PPSFWOO\PluginExtras(); };
