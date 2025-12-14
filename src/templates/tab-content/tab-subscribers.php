<?php if (!defined('ABSPATH')) {
    exit;
} ?>

<h2>Subscribers</h2>

<?php

if ($ppsfwoo_data = $this->subscriber_table_options_page()) {
    if ($ppsfwoo_data['num_subs']) {
        ?>
		
		<form id="subs-search">
			<input type="email" id="email-input" placeholder="Search by email address" />
			<?php wp_nonce_field('search_by_email', 'search_by_email'); ?>
			<input type="submit" name="search" value="Search">
		</form>
		<br />
		
		<?php

        echo wp_kses_post($ppsfwoo_data['html']);

        $ppsfwoo_export_url = add_query_arg([
            'ppsfwoo_export_table' => 1,
            '_wpnonce' => wp_create_nonce('db_export_nonce'),
        ], admin_url('admin.php?page=subscriptions_for_woo'));

        ?>

		<a class="button export-table-data" href="<?php echo esc_url($ppsfwoo_export_url); ?>" target="_blank">Export Table Data</a>
		
		<?php

    } elseif (0 === $ppsfwoo_data['num_subs']) {
        echo '<p>When you receive a new subscriber, they will appear here. </p>';
    }
}

?>

<a class="button" style="display: none;" id="reset" href="<?php echo esc_url(admin_url('admin.php?page=subscriptions_for_woo')); ?>">Reset</a>