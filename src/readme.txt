=== Subscriptions for Woo ===
Contributors: marknokes
Tags: woocommerce, paypal, payments, ecommerce, subscriptions
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 2.5.4
WC tested up to: 9.7.1
Requires at least: 6.4.3
Tested up to: 6.8
Requires PHP: 7.4

Enjoy recurring PayPal subscription payments leveraging WooCommerce and WooCommerce PayPal Payments

== Description ==
Subscriptions for Woo takes the hassle (and high cost) out of managing subscriptions products and services for your business. Simply create your PayPal subscription products and plans in your PayPal business subscriptions dashboard, and sync them with the plugin. After sync, a new product type "Subscription" is added to the product menu. Selection provides an additional tab where you choose your plan. Save and done!

= Offer Subscription payments to help drive repeat business =
Create stable, predictable income by offering subscription plans.

- Subscription plans are created and managed at PayPal where customer payments are securely maintained. **Consumers are nearly three times more likely to purchase when you offer PayPal.** 
- Built to seamlessly integrate with your WooCommerce store, offering a tailored solution for managing recurring payments and subscriptions.
- Designed to streamline the subscription process, from setup to management, providing you with the tools you need to drive revenue and foster long-term customer relationships.
- As your business grows and evolves, our plugin grows with you, seamlessly accommodating increased subscription volumes, expanding product catalogs, and evolving customer needs.
- You can confidently scale your subscription offerings without worrying about technical limitations or disruptions.
- [Subscriptions for Woo](https://wp-subscriptions.com/) allow business and casual sellers to accept reliable recurring payments on a fixed billing schedule (buyers may require a PayPal account).
- It’s easy for shoppers, simple for you, and great for your business!

= Subscriptions for Woo Features =
- Unlimited downloads
- Unlimited domains
- PayPal subscription plan chosen on WooCommerce product

= Subscriptions for Woo Premium Features =
Everything from above plus the following:

- Allow specific users to edit plugin settings and permissions
- Allow specific users to view and manage subscribers
- Allow specific users to edit subscription products
- Give subscribers the ability to manage their own plan, including pausing, cancelling, or re-activating, without needing to wait on you for help
- Create virtual and downloadable subscription products

= Subscriptions for Woo Enterprise Features =
Everything from above plus the following:

- Create custom roles to restrict content based on customer subscription(s)
- Shortcode allows adding PayPal subscribe button to any page
- Allow canceled subscribers to resubscribe and receive a discount
- Easily generate WooCommerce products from each plan with one click
- Enterprise support

[Compare plans at https://wp-subscriptions.com/compare-plans/](https://wp-subscriptions.com/compare-plans/)

= Activate PayPal =
Are you new to PayPal? [Learn how to add it to your store.](https://woocommerce.com/document/woocommerce-paypal-payments/)
Need to update your existing PayPal integration? [Learn how to upgrade your integration.](https://woocommerce.com/document/woocommerce-paypal-payments/paypal-payments-upgrade-guide/)

== Frequently Asked Questions ==

= What other plugins are required for Subscriptions for Woo to work? =

WooCommerce and WooCommerce PayPal Payments

= Where can I report bugs? =

Please report confirmed bugs on the Subscriptions for Woo [github](https://github.com/marknokes/subscriptions-for-woo/issues/new?assignees=marknokes&labels=bug&template=bug_report.md) directly. Include any screenshots and as much detail as possible.

== Installation ==

= Requirements =

To install and configure Subscriptions for Woo, you will need:

* WordPress Version 6.4.3 or newer (installed)
* WooCommerce Version 8.7.0 or newer (installed and activated)
* WooCommerce PayPal Payments Version 2.6.1 or newer (installed and activated)
* PHP Version 7.2 or newer
* A PayPal business account

= Setup and Configuration =

Follow these steps first to connect the plugin to your PayPal account:

1. After you have activated the WooCommerce PayPal Payments plugin, go to **WooCommerce  > Settings**.
2. Click the **Payments** tab.
3. The Payment methods list may include two PayPal options. Click on **PayPal** (not PayPal Standard).
4. Click on the **Activate PayPal** button.
5. Sign in to your PayPal account. If you do not have a PayPal account yet, sign up for a new PayPal business or personal account.
6. After you have successfully connected your PayPal account, click on the **Standard Payments** tab and check the **Enable Paypal features for your store** checkbox to enable PayPal.
7. Click **Save changes**.

Complete onboarding instructions can be found in the [woocommerce documentation](https://woocommerce.com/document/woocommerce-paypal-payments/#connect-paypal-account).

= Installation instructions =

1. Log in to WordPress admin.
2. Go to **Plugins > Add New**.
3. Search for the **Subscriptions for Woo** plugin.
4. Click on **Install Now** and wait until the plugin is installed successfully.
5. You can activate the plugin after WooCommerce and WooCommerce PayPal Payments are installed by clicking on **Activate** now on the success page.

= PayPal Integration =

This plugin integrates with the PayPal SDK to process payments securely. Please note the following:

* This plugin relies on the PayPal SDK to facilitate payment transactions.
* For more information about PayPal's services, visit [PayPal](https://www.paypal.com/).
* Before using this plugin, please review PayPal's [Terms of Use](https://www.paypal.com/us/legalhub/home) and [Privacy Policy](https://www.paypal.com/us/legalhub/privacy-full) to understand how PayPal handles your data and transactions.

== Screenshots ==
1. Subscribers list
2. General settings
3. Advanced settings
4. Product edit screen
5. Subscriptions capabilities
6. Copy shortcode link

== Upgrade Notice ==

Automatic updates should work generally smoothly, but we still recommend you back up your site.

If you encounter issues with the PayPal buttons not appearing after an update, purge your website cache.

== Changelog ==

= 2.5.4 =
* Bugfix: deleted products cause error on order template in admin area
* Improvemnet: add missing css identifier in js
* Improvemnet: add missing docblock
* Improvemnet: check for tab content before attempting to include in options page menu
* Improvemnet: add min and max to number type for options page
* Improvemnet: move localize script for paypal button to paypal class

= 2.5.3 =
* Improvemnet: remove superfluous import statements
* Improvement: remove superfluous period after required PHP version
* Improvement: move plugin init to PluginMain class
* Improvement: simplify order line item meta
* Improvement: add docblocks to class methods and properties
* Improvement: normalize line endings

= 2.5.2 =
* Improvement: normalize line endings
* Improvement: php cs fixer
* Improvement: add phpcs ignore for false positive plugin check results
* Improvement: add product url to plan
* Improvement: remove get_post_by_title and use post meta to identify integral pages
* Improvement: add BILLING.PLAN.CREATED to class Webhook
* Improvement: update ajaxActions to do_action if exists

= 2.5.1 =
* Bugfix: plans not refreshing on plugin activation

= 2.5 =
* Bugfix: product general settings missing on product type change
* Bugfix: PayPal webhook endpoint missing trailing slash
* Improvement: remove superfluous plugin main instances
* Improvement: update validate_callback on rest_api_init
* Improvement: general refactoring for efficiency & clarity

= 2.4.9 =
* Bugfix: email error when checking out with non subscription product
* Bugfix: product general settings missing when clicking downloadable checkbox
* Improvement: return null for button when product is not subscription
* Improvement: refactor paypal button js for clarity

= 2.4.8 =
* Improvement: remove unused class imports
* Improvement: update some general settings field types for clarity
* Improvement: set default option when no option exists
* Improvement: move inline css to existing stylesheet

= 2.4.7 =
* Feature: add subscription start time to order details
* Bugfix: plans not updating in gui on plugin activation when using redis cache
* Bugfix: plugin reactivation causes database error on upgrade
* Improvement: increase timeout for wp_remote_request
* Improvement: isset check for plan_id and product_id in ajax actions
* Improvement: change Order set_taxes method from private to public static

= 2.4.6 =
* Feature: Allow applying different tax rates to woocommerce order based on paypal plan definition
* Bugfix: Max 20 paypal plans appearing in admin area
* Improvement: Remove default tax_class from order product when tax inclusive
* Improvement: Refactor Plan class

= 2.4.5 =
* Bugfix: general tab on product page not displaying
* Bugfix: improve tax handling and fix totals

= 2.4.4 =
* Feature: display plan price in table on plan tab
* Bugfix: taxes not being handled properly
* Bugfix: paypal subscription plans other than fixed not properly displayed on receipts
* Bugfix: replace intval for floatval where floats used

= 2.4.3 =
* Refactor: combine class import statements
* Refactor: rename database class and move install/upgrade from plugin main to database

= 2.4.2 =
* Change data type for new expires column
* Update plugin activation/deactivation

= 2.4.1 =
* Improvement: Add expires column to subscriber table
* Improvement: Refactor Plan class

= 2.4 =
* Improvement: Replace session variables with transients
* Improvement: Set order status first to processing then complete after checking PayPal subscription status is ACTIVE

= 2.3.9 =
* Bugfix: Customers unable to download products up to expiration date after cancellation
* Feat: Plan table on settings page now deeplinks into PayPal

= 2.3.8 =
* Security enhancements: Sanitize options passed to register_setting

= 2.3.7 =
* Bugfix: PayPal sandbox deep links not redirecting for customers. Update PayPal sandbox URI to include www prefix.

= 2.3.6 =
* Improvement: remove superfluous transient check during webhook resubscribe

= 2.3.5 =
* Bugfix: webhooks not re-created on upgrader_process_complete

= 2.3.4 =
* Initial release.
