<?php
/**
 * Plugin Name: Subscriptions for Woo
 * Description: Enjoy recurring PayPal subscription payments leveraging WooCommerce and WooCommerce PayPal Payments
 * Requires Plugins: woocommerce, woocommerce-paypal-payments
 * Author: WP Subscriptions
 * Author URI: https://wp-subscriptions.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 2.4.3
 * WC requires at least: 8.6.0
 * WC tested up to: 9.3.3
 * Requires at least: 6.4.3
 * Tested up to: 6.7.2
 * Requires PHP: 7.4
 */

use WooCommerce\PayPalCommerce\PPCP;

use PPSFWOO\PluginMain,
	PPSFWOO\Product,
	PPSFWOO\PluginExtras,
	PPSFWOO\Enterprise,
	PPSFWOO\Database,
	PPSFWOO\Order;

if (!defined('ABSPATH')) exit;

require_once 'autoload.php';

spl_autoload_register('ppsfwoo_autoload');

define('PPSFWOO_PLUGIN_PATH', __FILE__);

define('PPSFWOO_PLUGIN_EXTRAS', class_exists(PluginExtras::class));

define('PPSFWOO_ENTERPRISE', class_exists(Enterprise::class));

register_activation_hook(PPSFWOO_PLUGIN_PATH, [PluginMain::class, 'plugin_activation']);

add_action('wc_ajax_ppc-webhooks-resubscribe', [PluginMain::class, 'schedule_webhook_resubscribe']);

add_action('update_option_woocommerce-ppcp-settings', [PluginMain::class, 'schedule_webhook_resubscribe']);

add_action('upgrader_process_complete', [PluginMain::class, 'upgrader_process_complete'], 10, 2);

add_action('plugins_loaded', function() {

	if(!class_exists(WC_Product::class) || !class_exists(PPCP::class)) {

		return;

	}

	global $product;

	$ClassName = 'WC_Product_' . Product::TYPE;

	$ClassDefinition = new class($product) extends WC_Product
	{
	    public $product_type;
		
		public function __construct($product)
		{
			$this->product_type = Product::TYPE;

			parent::__construct($product);
		}
	};

	class_alias(get_class($ClassDefinition), $ClassName);

	$PluginMain = PluginMain::get_instance();

	Database::upgrade();

	register_deactivation_hook(PPSFWOO_PLUGIN_PATH, [$PluginMain, 'plugin_deactivation']);

	$PluginMain->add_actions();

	$PluginMain->add_filters();

	new Product();

	new Order();

	if(PPSFWOO_PLUGIN_EXTRAS) { new PluginExtras(); };

}, 11);