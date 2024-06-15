<?php

use PPSFWOO\Webhook;

if (!defined('ABSPATH')) exit;

?>

<div class="flex-container">
	
<div class="wrap <?php echo PPSFWOO_PLUGIN_EXTRAS ? 'full-width': 'partial-width'; ?>">

	<script type="text/javascript">
    	var tab_subs_active = <?php echo isset($_GET['subs_page_num']) ? "true": "false"; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
    </script>

    <h1>Subscriptions for Woo</h1>

	<h2 class="nav-tab-wrapper">
	    <a href="tab-subscribers" class="nav-tab subs-list nav-tab-active">Subscribers</a>
	    <a href="tab-general" class="nav-tab">General Settings</a>
	    <a href="tab-advanced" class="nav-tab">Advanced</a>
	</h2>
	
	<div id="tab-subscribers" class="tab-content">

        <h2>Subscribers</h2>

        <?php

        $num_subs = $this->ppsfwoo_display_subs();

        if($num_subs) {

        	?>
        	
        	<form id="subs-search">
				<input type="email" id="email-input" placeholder="Search by email address" />
	        	<input type="submit" name="search" value="Search">
	        </form>
			<br />
        	
        	<?php

			$export_url = add_query_arg([
			    'export_table'  => 'true',
			    '_wpnonce' 		=> wp_create_nonce('db_export_nonce')
			], admin_url('admin.php?page=subscriptions_for_woo'));
			
			?>

			<a class="button export-table-data" href="<?php echo esc_url($export_url); ?>" target="_blank">Export Table Data</a>
	        
	        <?php 

    	} else if(0 === $num_subs) {

    		echo "<p>When you receive a new subscriber, they will appear here. </p>";

    	}

        ?>
        <a class="button" style="display: none;" id="reset" href="<?php echo esc_url(admin_url('admin.php?page=subscriptions_for_woo')); ?>">Reset</a>
    </div>	

	<div id="tab-general" class="tab-content">

		<h2>General Settings</h2>

		<?php
		
		$settings_class = "";

		$wp_keses_options = [
			'option' => [
				'value'    => [],
				'selected' => []
			]
		];

		if(!is_super_admin() && !current_user_can('ppsfwoo_manage_settings')) {

			echo "<p>You do not have permission to edit plugin settings.</p>";

			$settings_class = "hide";
		}
		?>

		<form class="<?php echo esc_attr($settings_class); ?>" id="ppsfwoo_options" method="post" action="options.php">

			<?php settings_fields(self::$options_group); ?>

			<table id="settings-main">

				<?php foreach (self::$options as $option => $array)
				{
					if('skip_settings_field' === $array['type']) continue;

					$value = get_option($option);

					?>

					<tr valign="top">

						<td style="padding-bottom: 15px;">

							<h3>
								<label for="<?php echo esc_attr($option); ?>"><?php echo esc_attr($array['name']); ?></label>
							</h3>

							<?php

							switch ($array['type'])
							{
								case 'checkbox':

									$checked = checked(1, $value, false);
									?>
									<input type='checkbox' name='<?php echo esc_attr($option); ?>' value='1' <?php echo esc_attr($checked); ?> />
									<?php
									break;

								case 'wysiwyg':

									wp_editor($value, $option, $settings = ['textarea_rows'=> '10']);

									break;

								case 'textarea':

									?>
									<textarea rows="10" cols="100" id="<?php echo esc_attr($option); ?>" name="<?php echo esc_attr($option); ?>"><?php echo esc_textarea($value); ?></textarea>
									<?php
									break;

								case 'select':

									echo "<select name='" . esc_attr($option) . "'>";

										$options = $array['options'] ?? false;

										if($options) {

											foreach(array_keys($options) as $option)
											{
												$selected = selected($option, $value, false);

												echo wp_kses("<option value='$option' $selected>$options[$option]</option>", $wp_keses_options);
											}

										} else {

											$post_type = isset($array['post_type']) ? $array['post_type']: 'page';

											switch ($post_type)
											{
												case 'category':

													if($categories = get_terms($array['taxonomy'])) {

						                                foreach($categories as $category)
						                                {
						                                	$selected = selected($category->slug, $value, false);

						                                    echo wp_kses("<option value='$category->slug' $selected>$category->name</option>", $wp_keses_options);
						                                }
						                            }

													break;

												default:

													if($pages = get_posts(['numberposts' => -1, 'post_status' => 'any', 'post_type' => [$post_type]])) {

						                                foreach($pages as $page)
						                                {
						                                	$selected = selected($page->ID, $value, false);

						                                    echo wp_kses("<option value='$page->ID' $selected>$page->post_title</option>", $wp_keses_options);
						                                }
						                            }

													break;

											}
										}

									echo "</select>";

									break;
								
								case 'multiselect':

									echo wp_kses("<select name='$option[page_ids][]' multiple='multiple'>", ['select' => ['name' => [], 'multiple' => []]]);

										$type = isset($array['post_type']) ? $array['post_type']: 'page';

			                            if($pages = get_posts(['numberposts' => -1, 'post_type' => [$type]])) {

			                                foreach($pages as $page)
			                                {
			                                	$key = array_search($page->ID, $value['page_ids']);

			                                	$selected = selected($page->ID, $value['page_ids'][$key]);

			                                    echo wp_kses("<option value='$page->ID' $selected>$page->post_title</option>", $wp_keses_options);
			                                }
			                            }

									echo "</select>";

									break;

								default:

									$value = esc_attr($value);

									echo "<input size='20' type='text' id='" . esc_attr($option) . "' name='" . esc_attr($option) . "' value='" . esc_attr($value) . "' />";
									
									break;
							}

							?>

						</td>

					</tr>

					<?php

				}
				?>
				<tr>
					<td>
						<h2>PayPal Subscription Plans</h2>
						<table id="plans" class="pp-inner-table">
							<tr>
								<th>Plan ID</th>
								<th>Plan Name</th>
								<th>Product Name</th>
								<th>Plan Frequency</th>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td>
						<a class="button" id="refresh" href="#">Refresh Plans</a><span class="spinner"></span>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>

    <div id="tab-advanced" class="tab-content">
    	<h3>Subscribed Webhooks</h3>
		<table id="webhooks" class="pp-inner-table">
			<tr>
				<th>Subscribed Webhook</th>
				<th>Description</th>
			</tr>
		</table>
		<p>Listen Address: <code><?php $Webhook = new Webhook(); echo esc_url($Webhook->listen_address()); ?></code></p>
		<a class="button" id="resubscribe" href="<?php echo esc_url(admin_url(self::$ppcp_settings_url) . "#field-webhooks_list"); ?>">Resubscribe webhooks</a>
		<h3>Users and Capabilities</h3>
		<?php
		if(PPSFWOO_PLUGIN_EXTRAS) {

			\PPSFWOO\PluginExtras::ppsfwoo_get_users_by_capabilities(true);

		} else {

			echo "<a href='" . esc_url(self::$upgrade_link) . "' target='_blank'>Pro feature</a>: Select custom capabilities for users.";

		}
		?>
    </div>

</div>

<?php if(!PPSFWOO_PLUGIN_EXTRAS) { ?>
<div class="wrap">

	<div class="go-pro">
		<h2>Upgrading to <span class="pro-name">Subscriptions for Woo Pro</span> gives you these added features:</h2>
		<ul>
			<li>Allow specific users to edit plugin settings and permissions</li>
			<li>Allow specific users to view and manage subscribers</li>
			<li>Allow specific users to edit subscription products</li>
			<li>Give subscribers the ability to manage their own plan, including pausing, cancelling, or re-activating, without needing to wait on you for help</li>
			<li>Create virtual and downloadable subscription products</li>
		</ul>
		<a class="button button-primary" target="_blank" rel="noopener" href="<?php echo esc_url(self::$upgrade_link); ?>">Learn more</a>
	</div>

<div>
<?php } ?>

</div>
