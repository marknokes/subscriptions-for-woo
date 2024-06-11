<?php if (!defined('ABSPATH')) exit; ?>

<table id="subscribers" class="pp-inner-table">
    
    <th>Customer</th>
    <th>Order</th>
    <th>PayPal Plan ID</th>
    <th>Subscribed On</th>
    <th>Status</th>
    <th>Manage Subscription</th>

    <?php

    foreach ($results as $row)
    {
        $user = get_user_by('id', $row->wp_customer_id);

        if(!is_object($user)) {

            continue;

        }

        $user_profile_link = admin_url("user-edit.php?user_id=$row->wp_customer_id");

        $order_link = admin_url("admin.php?page=wc-orders&action=edit&id=$row->order_id");

        $class = $row->event_type === self::ACTIVATED ? "status green": "status red";

        $date = gmdate("F j, Y", strtotime($row->created));

        ?>
        <tr>

            <td><a href='<?php echo esc_attr($user_profile_link); ?>' target='_blank'><?php echo esc_html($user->display_name); ?></a></td>

            <td><a href='<?php echo esc_attr($order_link); ?>' target='_blank'>Order #<?php echo esc_html($row->order_id); ?><a></td>

            <td><a href='<?php echo esc_url($paypal_url); ?>/billing/plans/<?php echo esc_attr($row->paypal_plan_id); ?>' target='_blank'><?php echo esc_html($row->paypal_plan_id); ?></a></td>

            <td><?php echo esc_html($date); ?></td>

            <td><span class='<?php echo esc_attr($class); ?>'><?php echo esc_html($row->event_type); ?></span></td>

            <td><a href='<?php echo esc_url($paypal_url); ?>/billing/subscriptions/<?php echo esc_attr($row->id); ?>' target='_blank'>Manage Subscription</a></td>

        </tr>

    <?php

    }

    ?>

</table>
