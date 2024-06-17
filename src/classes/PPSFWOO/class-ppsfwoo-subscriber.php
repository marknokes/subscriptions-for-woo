<?php

namespace PPSFWOO;

use PPSFWOO\Product;
use PPSFWOO\User;
use PPSFWOO\Order;
use PPSFWOO\Webhook;

class Subscriber
{
	private $user,
			$event_type;

	public function __construct($user = NULL, $event_type = NULL)
    {
    	$this->user = $user;

    	$this->event_type = $event_type;
    }

    public function activate($response)
    {
        if(isset($response['response']['status']) && "ACTIVE" === $response['response']['status']) {

        	$this->user = new User($response);

        	$this->event_type = Webhook::ACTIVATED;

            $this->subscribe();

            return true;
        }

        return false;
    }

    private function insert()
    {
        global $wpdb;

        $wp_user = !empty($this->user->email) ? get_user_by('email', $this->user->email): false;

        $this->user->user_id = $wp_user->ID ?? false;

        if(!$this->user->user_id) {

            $this->user->user_id = $this->user->create_wp_user();

            $this->user->create_woocommerce_customer();

        }

        $order_id = Order::get_order_id_by_subscription_id($this->user->subscription_id);

        if(false === $order_id) {

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query('SET time_zone = "+00:00"');
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$wpdb->prefix}ppsfwoo_subscriber (
                        `id`,
                        `wp_customer_id`,
                        `paypal_plan_id`,
                        `event_type`
                    )
                    VALUES (%s,%d,%s,%s)",
                    [
                        $this->user->subscription_id,
                        $this->user->user_id,
                        $this->user->plan_id,
                        $this->event_type
                    ]
                )
            );

        } else {

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query('SET time_zone = "+00:00"');
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}ppsfwoo_subscriber SET
                        `paypal_plan_id` = %s,
                        `event_type` = %s,
                        `canceled_date` = '0000-00-00 00:00:00'
                    WHERE `id` = %s;",
                    [
                        $this->user->plan_id,
                        $this->event_type,
                        $this->user->subscription_id
                    ]
                )
            );

            Product::update_download_permissions($order_id, 'grant');
        }

        $errors = !empty($wpdb->last_error) ? $wpdb->last_error: false;

        return [
            'errors' => $errors,
            'action' => false === $order_id ? 'insert': 'update'
        ];
    }

    public function cancel()
    {
        global $wpdb;

        if($order_id = Order::get_order_id_by_subscription_id($this->user->subscription_id)) {
        
            Product::update_download_permissions($order_id, 'revoke');

        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query('SET time_zone = "+00:00"');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}ppsfwoo_subscriber SET `event_type` = %s WHERE `id` = %s;",
                $this->event_type,
                $this->user->subscription_id
            )
        );

        return [
            'errors' => $wpdb->last_error
        ];
    }

    public function subscribe()
    {
        global $wpdb;

        $response = $this->insert();

        if(false === $response['errors'] && 'insert' === $response['action']) {

            $order_id = Order::insert_order($this->user);

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query('SET time_zone = "+00:00"');
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}ppsfwoo_subscriber SET
                        `order_id` = %d,
                        `canceled_date` = '0000-00-00 00:00:00'
                        WHERE `id` = %s;",
                    $order_id,
                    $this->user->subscription_id
                )
            );
        }

        return $this->user->subscription_id ?? false;
    }
}