<?php

namespace PPSFWOO;

use WooCommerce\PayPalCommerce\Webhooks\Endpoint\ResubscribeEndpoint;

if (!defined('ABSPATH')) {
    exit;
}

?>

<h3>Subscribed Webhooks</h3>

<?php

$webhooks = Webhook::get_instance()->list();

$resubscribe_nonce = wp_create_nonce(ResubscribeEndpoint::nonce());

if ($webhooks && sizeof($webhooks)) {
    self::display_template('table-webhooks', [
        'webhooks' => $webhooks,
    ]);
}
?>

<p>Listen Address: <code><?php echo esc_url(Webhook::get_instance()->listen_address()); ?></code></p>

<a class="button" id="resubscribe" href="#" data-nonce="<?php echo esc_attr($resubscribe_nonce); ?>">Resubscribe webhooks</a>

<h3>Users and Capabilities</h3>

<?php
if (PPSFWOO_PLUGIN_EXTRAS) {
    PluginExtras::get_users_by_capabilities(true);
} else {
    echo "<a href='".esc_url(self::$upgrade_link)."' target='_blank'>Pro feature</a>: Select custom capabilities for users.";
}
?>