<?php if (!defined('ABSPATH')) exit; ?>

<table id="plans" class="pp-inner-table">
	<tr>
		<th>Plan ID</th>
		<th>Plan Name</th>
		<th>Product Name</th>
		<th>Frequency</th>
		<th>Price</th>
		<th>Share Link</th>
		<th>Status</th>
		<th>Modify</th>
	</tr>
	<?php
	foreach ($plans as $plan_id => $plan_data)
	{
		$plan_active = "ACTIVE" === $plan_data['status'];

		$paypal_action = $plan_active ? 'deactivate': 'activate';

		$status_indicator = $plan_active ? 'green': 'red';

		$formatter = new \NumberFormatter('en_US', \NumberFormatter::CURRENCY);

		?>
		<tr class="plan-row">
			<td><a href='<?php echo esc_url($paypal_url); ?>/billing/plans/<?php echo esc_attr($plan_id); ?>' target='_blank'><?php echo esc_html($plan_id); ?></a></td>
			<td><?php echo esc_html($plan_data['plan_name']); ?></td>
			<td><?php echo esc_html($plan_data['product_name']); ?></td>
			<td><?php echo esc_html($plan_data['frequency']); ?></td>
			<td><?php echo esc_html($formatter->formatCurrency($plan_data['price'], 'USD')); ?></td>
			<td>
				<p class="copy-text"><?php echo esc_url($paypal_url); ?>/webapps/billing/plans/subscribe?plan_id=<?php echo esc_html($plan_id); ?></p>
				<button class="copy-button">Copy to clipboard</button>
			</td>
			<td><span class='tooltip status <?php echo esc_attr($status_indicator); ?>'><span class='tooltip-text'><?php echo esc_html($plan_data['status']); ?></span></span></td>
			<td><a href='#' class='<?php echo esc_attr($paypal_action); ?>' data-plan-id='<?php echo esc_attr($plan_id); ?>' data-nonce='<?php echo esc_attr(wp_create_nonce('modify_plan')); ?>'><?php echo esc_html(ucfirst($paypal_action)); ?></a></td>
		</tr>
	<?php
	}
	?>
</table>
