<?php if (!defined('ABSPATH')) {
    exit;
} ?>

<div class="flex-container">
	
	<div class="wrap <?php echo PPSFWOO_PLUGIN_EXTRAS ? 'full-width' : 'partial-width'; ?>">

	    <h1><?php echo esc_html(self::plugin_data("Name")); ?></h1>

		<div class="nav-tab-wrapper">

			<?php do_action('ppsfwoo_options_page_tab_menu', $tabs); ?>

		</div>

		<?php do_action('ppsfwoo_options_page_tab_content', $tabs); ?>

	</div>

	<?php do_action('ppsfwoo_after_options_page'); ?>

</div>
