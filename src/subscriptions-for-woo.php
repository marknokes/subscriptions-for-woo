<?php
/**
 * Plugin Name: Subscriptions for Woo
 * Description: Enjoy recurring PayPal subscription payments leveraging WooCommerce and WooCommerce PayPal Payments
 * Requires Plugins: woocommerce, woocommerce-paypal-payments
 * Author: WP Subscriptions
 * Author URI: https://wp-subscriptions.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 1.5.1
 * WC requires at least: 8.6.0
 * WC tested up to: 8.9.2
 * Requires at least: 6.4.3
 * Tested up to: 6.5.4
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

require_once 'classes/WooCustomProduct.php';

spl_autoload_register(function ($class_name) {

    $file =  __DIR__ . DIRECTORY_SEPARATOR . "classes" . DIRECTORY_SEPARATOR  . preg_replace("~[\\\\/]~", DIRECTORY_SEPARATOR, $class_name) . ".php";
    
    if(file_exists($file)) {

    	require_once $file;

    }
    
});

define('PPSFWOO_PLUGIN_PATH', __FILE__);

define('PPSFWOO_PERMISSIONS', class_exists(\PPSFWOO\SubsForWooPermissions::class));

new \PPSFWOO\SubsForWoo();

$SubsForWooPermissions = PPSFWOO_PERMISSIONS ? new \PPSFWOO\SubsForWooPermissions(): NULL;
