<?php if (!defined('ABSPATH')) {
    exit;
} ?>

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
    if (!isset($plans['000'])) {
        foreach ($plans as $ppsfwoo_plan_id => $ppsfwoo_plan) {
            $ppsfwoo_plan_active = 'ACTIVE' === $ppsfwoo_plan->status;

            $ppsfwoo_paypal_action = $ppsfwoo_plan_active ? 'deactivate' : 'activate';

            $ppsfwoo_status_indicator = $ppsfwoo_plan_active ? 'green' : 'red';

            $ppsfwoo_formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);

            ?>
			<tr class="plan-row">
				<td><a href='<?php echo esc_url($paypal_url); ?>/billing/plans/<?php echo esc_attr($ppsfwoo_plan_id); ?>' target='_blank'><?php echo esc_html($ppsfwoo_plan_id); ?></a></td>
				<td><?php echo esc_html($ppsfwoo_plan->name); ?></td>
				<td><?php echo esc_html($ppsfwoo_plan->product_name); ?></td>
				<td><?php echo esc_html($ppsfwoo_plan->frequency); ?></td>
				<td><?php echo esc_html($ppsfwoo_formatter->formatCurrency($ppsfwoo_plan->price, 'USD')); ?></td>
				<td>
					<p class="copy-text"><?php echo esc_url($paypal_url); ?>/webapps/billing/plans/subscribe?plan_id=<?php echo esc_html($ppsfwoo_plan_id); ?></p>
					<button class="copy-button">Copy to clipboard</button>
				</td>
				<td><span class='tooltip status <?php echo esc_attr($ppsfwoo_status_indicator); ?>'><span class='tooltip-text'><?php echo esc_html($ppsfwoo_plan->status); ?></span></span></td>
				<td><a href='#' class='<?php echo esc_attr($ppsfwoo_paypal_action); ?>' data-plan-id='<?php echo esc_attr($ppsfwoo_plan_id); ?>' data-nonce='<?php echo esc_attr(wp_create_nonce('modify_plan')); ?>'><?php echo esc_html(ucfirst($ppsfwoo_paypal_action)); ?></a></td>
			</tr>
		<?php
        }
    }
?>
</table>
