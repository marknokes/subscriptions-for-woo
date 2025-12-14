<?php if (!defined('ABSPATH')) {
    exit;
} ?>

<table id="webhooks" class="pp-inner-table">
	<tr>
		<th>Subscribed Webhook</th>
		<th>Description</th>
	</tr>
	<?php
    foreach ($webhooks as $ppsfwoo_webhook) {
        ?>
		<tr class="webhook-row">
			<td><?php echo esc_html($ppsfwoo_webhook['name']); ?></td>
			<td><?php echo esc_html($ppsfwoo_webhook['description']); ?></td>
		</tr>
	<?php
    }
?>
</table>
