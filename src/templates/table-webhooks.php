<table id="webhooks" class="pp-inner-table">
	<tr>
		<th>Subscribed Webhook</th>
		<th>Description</th>
	</tr>
	<?php
	foreach($webhooks as $webhook)
	{
	?>
		<tr class="webhook-row">
			<td><?php echo esc_html($webhook['name']); ?></td>
			<td><?php echo esc_html($webhook['description']); ?></td>
		</tr>
	<?php 
	}
	?>
</table>