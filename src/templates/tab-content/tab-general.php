<?php if (!defined('ABSPATH')) exit; ?>

<h2>General Settings</h2>

<?php

$wp_keses_options = [
	'option' => [
		'value'    => [],
		'selected' => []
	]
];

if(!is_super_admin() && !current_user_can('ppsfwoo_manage_settings')) {

	echo "<p>You do not have permission to edit plugin settings.</p>";

} else {

?>

<a class="button" href="<?php echo esc_url(admin_url(self::$ppcp_settings_url)); ?>">Manage PayPal Connection</a>

<form id="ppsfwoo_options" method="post" action="options.php">

	<?php settings_fields(self::$options_group); ?>

	<table id="settings-main">

		<?php foreach (self::$options as $option => $array)
		{
			if('skip_settings_field' === $array['type']) continue;

			$value = self::get_option($option);

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
	</table>
	<?php submit_button(); ?>
</form>

<?php
}
?>