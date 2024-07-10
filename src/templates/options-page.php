<?php

if (!defined('ABSPATH')) exit;

$tabs = [
	'tab-subscribers' => 'Subscribers',
	'tab-plans' 	  => 'Plans',
	'tab-general' 	  => 'General Settings',
	'tab-advanced' 	  => 'Advanced'
];

?>

<div class="flex-container">
	
	<div class="wrap <?php echo PPSFWOO_PLUGIN_EXTRAS ? 'full-width': 'partial-width'; ?>">

	    <h1><?php echo esc_html(self::plugin_data("Name")); ?></h1>

		<div class="nav-tab-wrapper">

			<?php

			foreach ($tabs as $tab_id => $display_name)
			{
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$active = isset($_GET['tab']) && $tab_id === $_GET['tab'] ? "nav-tab-active": "";

				echo '<a href="' . esc_attr($tab_id) . '" class="nav-tab ' . esc_attr($active) . '">' . esc_html($display_name) . '</a>';
			}

			?>

		</div>

		<?php

		foreach ($tabs as $tab_id => $display_name)
		{
			echo '<div id="' . esc_attr($tab_id) . '" class="tab-content">';

			include $this->template_dir . "tab-content/$tab_id.php";

			echo '</div>';
		}

		?>

	</div>

	<?php if(!PPSFWOO_PLUGIN_EXTRAS) { include $this->template_dir . "tab-content/go-pro.php"; } ?>

</div>
