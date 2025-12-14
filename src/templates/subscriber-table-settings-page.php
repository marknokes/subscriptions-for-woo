<?php

namespace PPSFWOO;

if (!defined('ABSPATH')) {
    exit;
}

?>

<table id="subscribers" class="pp-inner-table">
    
    <th>Customer</th>
    <th>Order</th>
    <th>PayPal Plan ID</th>
    <th>Subscribed On</th>
    <th>Status</th>
    <th>Manage Subscription</th>

    <?php

    foreach ($results as $ppsfwoo_row) {
        $ppsfwoo_user = get_user_by('id', $ppsfwoo_row->wp_customer_id);

        if (!is_object($ppsfwoo_user)) {
            continue;
        }

        $ppsfwoo_user_profile_link = admin_url("user-edit.php?user_id={$ppsfwoo_row->wp_customer_id}");

        $ppsfwoo_order_link = admin_url("admin.php?page=wc-orders&action=edit&id={$ppsfwoo_row->order_id}");

        switch ($ppsfwoo_row->event_type) {
            case Webhook::ACTIVATED:
                $ppsfwoo_class = 'status green';

                break;

            case Webhook::SUSPENDED:
                $ppsfwoo_class = 'status orange';

                break;

            case Webhook::CANCELLED:
            case Webhook::PAYMENT_FAILED:
            case Webhook::EXPIRED:
                $ppsfwoo_class = 'status red';

                break;

            default:
                $ppsfwoo_class = '';

                break;
        }

        $ppsfwoo_date = gmdate('F j, Y', strtotime($ppsfwoo_row->created));

        $ppsfwoo_tooltip = !empty($ppsfwoo_row->canceled_date) ? "Canceled: {$ppsfwoo_row->canceled_date}, Expires: {$ppsfwoo_row->expires}" : 'Active';

        ?>
        <tr>

            <td><a href='<?php echo esc_attr($ppsfwoo_user_profile_link); ?>' target='_blank'><?php echo esc_html($ppsfwoo_user->display_name); ?></a></td>

            <td><a href='<?php echo esc_attr($ppsfwoo_order_link); ?>' target='_blank'>Order #<?php echo esc_html($ppsfwoo_row->order_id); ?><a></td>

            <td><a href='<?php echo esc_url($paypal_url); ?>/billing/plans/<?php echo esc_attr($ppsfwoo_row->paypal_plan_id); ?>' target='_blank'><?php echo esc_html($ppsfwoo_row->paypal_plan_id); ?></a></td>

            <td><?php echo esc_html($ppsfwoo_date); ?></td>

            <td><span class='tooltip <?php echo esc_attr($ppsfwoo_class); ?>'><span class="tooltip-text"><?php echo esc_html($ppsfwoo_tooltip); ?></span></span></td>

            <td><a href='<?php echo esc_url($paypal_url); ?>/billing/subscriptions/<?php echo esc_attr($ppsfwoo_row->id); ?>' target='_blank'>Manage Subscription</a></td>

        </tr>

    <?php

    }

?>

</table>
