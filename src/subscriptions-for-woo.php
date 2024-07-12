<?php
/**
 * Plugin Name: Subscriptions for Woo
 * Description: Enjoy recurring PayPal subscription payments leveraging WooCommerce and WooCommerce PayPal Payments
 * Requires Plugins: woocommerce, woocommerce-paypal-payments
 * Author: WP Subscriptions
 * Author URI: https://wp-subscriptions.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 2.3.1
 * WC requires at least: 8.6.0
 * WC tested up to: 8.9.2
 * Requires at least: 6.4.3
 * Tested up to: 6.6
 * Requires PHP: 7.4
 */

use PPSFWOO\PluginMain;
use PPSFWOO\Product;
use PPSFWOO\PluginExtras;
use WooCommerce\PayPalCommerce\PPCP;

if (!defined('ABSPATH')) exit;

add_action('plugins_loaded', 'ppsfwoo_init', 11);

add_action('wc_ajax_ppc-webhooks-resubscribe', function() { set_transient('ppsfwoo_ppcp_updated', true, 60); });

add_action('update_option_woocommerce-ppcp-settings', function() { set_transient('ppsfwoo_ppcp_updated', true, 60); });

require_once 'autoload.php';

spl_autoload_register('ppsfwoo_autoload');

define('PPSFWOO_PLUGIN_PATH', __FILE__);

define('PPSFWOO_PLUGIN_EXTRAS', class_exists(PluginExtras::class));

register_activation_hook(PPSFWOO_PLUGIN_PATH, [PluginMain::class, 'plugin_activation']);

function ppsfwoo_init()
{
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

	register_deactivation_hook(PPSFWOO_PLUGIN_PATH, [$PluginMain, 'plugin_deactivation']);

	$PluginMain->add_actions();

	$PluginMain->add_filters();

	new Product();

	if(PPSFWOO_PLUGIN_EXTRAS) { new PluginExtras(); };
}
