<?php
/**
 * Plugin Name: Subscriptions for Woo
 * Description: Enjoy recurring PayPal subscription payments leveraging WooCommerce and WooCommerce PayPal Payments
 * Requires Plugins: woocommerce, woocommerce-paypal-payments
 * Author: WP Subscriptions
 * Author URI: https://wp-subscriptions.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 2.2.4
 * WC requires at least: 8.6.0
 * WC tested up to: 8.9.2
 * Requires at least: 6.4.3
 * Tested up to: 6.5.4
 * Requires PHP: 7.4
 */

use PPSFWOO\PluginMain;
use PPSFWOO\Product;
use PPSFWOO\PluginExtras;

if (!defined('ABSPATH')) exit;

add_action('plugins_loaded', 'ppsfwoo_init', 11);

require_once 'autoload.php';

spl_autoload_register('ppsfwoo_autoload');

define('PPSFWOO_PLUGIN_PATH', __FILE__);

define('PPSFWOO_PLUGIN_EXTRAS', class_exists(PluginExtras::class));

function ppsfwoo_init()
{
	if(!class_exists('WC_Product') || !class_exists('WooCommerce\PayPalCommerce\PPCP')) {

		return;

	}

	class WC_Product_ppsfwoo extends \WC_Product
    {
        public $product_type;
        
        public function __construct($product)
        {
            $this->product_type = 'ppsfwoo';

            parent::__construct($product);
        }
    }

	PluginMain::get_instance(true);

	new Product();

	if(PPSFWOO_PLUGIN_EXTRAS) { new PluginExtras(); };
}
