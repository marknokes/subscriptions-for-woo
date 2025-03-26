<?php

namespace PPSFWOO;

if (!defined('ABSPATH')) {
    exit;
} ?>

<h2>General Settings</h2>

<?php

$wp_keses_options = [
    'option' => [
        'value' => [],
        'selected' => [],
    ],
];

if (!is_super_admin() && !current_user_can('ppsfwoo_manage_settings')) {
    echo '<p>You do not have permission to edit plugin settings.</p>';
} else {
    ?>

<a class="button" href="<?php echo esc_url(admin_url(self::$ppcp_settings_url)); ?>">Manage PayPal Connection</a>

<form id="ppsfwoo_options" method="post" action="options.php">

	<?php settings_fields(self::$options_group); ?>

	<table id="settings-main">

		<?php foreach (self::$options as $option => $array) {
		    if (isset($array['is_premium']) && $is_premium = $array['is_premium']) {
		        $feature = 'Premium';

		        $disabled = $is_premium && (!PPSFWOO_PLUGIN_EXTRAS || !PluginExtras::onboarding_complete()) ? 'disabled=true' : '';
		    } elseif (isset($array['is_enterprise']) && $is_enterprise = $array['is_enterprise']) {
		        $feature = 'Enterprise';

		        $disabled = $is_enterprise && (!PPSFWOO_ENTERPRISE || !PluginExtras::onboarding_complete()) ? 'disabled=true' : '';
		    } else {
		        $feature = $disabled = '';
		    }

		    if ('skip_settings_field' === $array['type']) {
		        continue;
		    }

		    $name = !$disabled ? $option : '';

		    $value = self::get_option($option);

		    ?>

			<tr valign="top">

				<td style="padding-bottom: 15px;">

					<h3>
						<label for="<?php echo esc_attr($name); ?>"><?php echo esc_attr($array['name']); ?></label>
					</h3>

					<?php

		            switch ($array['type']) {
		                case 'checkbox':
		                    $checked = checked(1, $value, false);
		                    ?>
							<input type='checkbox' name='<?php echo esc_attr($name); ?>' value='1' <?php echo esc_attr($checked); ?> <?php echo esc_attr($disabled); ?> />
							<?php
		                    break;

		                case 'wysiwyg':
		                    wp_editor($value, $option, $settings = ['textarea_rows' => '10']);

		                    break;

		                case 'textarea':
		                    ?>
							<textarea rows="10" cols="100" id="<?php echo esc_attr($option); ?>" name="<?php echo esc_attr($name); ?>" <?php echo esc_attr($disabled); ?>><?php echo esc_textarea($value); ?></textarea>
							<?php
		                    break;

		                case 'select':
		                    echo "<select name='".esc_attr($name)."' ".esc_attr($disabled).'>';

		                    $options = $array['options'] ?? false;

		                    if ($options) {
		                        foreach (array_keys($options) as $option) {
		                            $selected = selected($option, $value, false);

		                            echo wp_kses("<option value='{$option}' {$selected}>{$options[$option]}</option>", $wp_keses_options);
		                        }
		                    } else {
		                        $post_type = isset($array['post_type']) ? $array['post_type'] : 'page';

		                        switch ($post_type) {
		                            case 'category':
		                                if ($categories = get_terms($array['taxonomy'])) {
		                                    foreach ($categories as $category) {
		                                        $selected = selected($category->slug, $value, false);

		                                        echo wp_kses("<option value='{$category->slug}' {$selected}>{$category->name}</option>", $wp_keses_options);
		                                    }
		                                }

		                                break;

		                            default:
		                                if ($pages = get_posts(['numberposts' => -1, 'post_status' => 'any', 'post_type' => [$post_type]])) {
		                                    foreach ($pages as $page) {
		                                        $selected = selected($page->ID, $value, false);

		                                        echo wp_kses("<option value='{$page->ID}' {$selected}>{$page->post_title}</option>", $wp_keses_options);
		                                    }
		                                }

		                                break;
		                        }
		                    }

		                    echo '</select>';

		                    break;

		                case 'multiselect':
		                    echo wp_kses("<select name='{$name['page_ids']}[]' multiple='multiple'".esc_attr($disabled).'>', ['select' => ['name' => [], 'multiple' => []]]);

		                    $type = isset($array['post_type']) ? $array['post_type'] : 'page';

		                    if ($pages = get_posts(['numberposts' => -1, 'post_type' => [$type]])) {
		                        foreach ($pages as $page) {
		                            $key = array_search($page->ID, $value['page_ids']);

		                            $selected = selected($page->ID, $value['page_ids'][$key]);

		                            echo wp_kses("<option value='{$page->ID}' {$selected}>{$page->post_title}</option>", $wp_keses_options);
		                        }
		                    }

		                    echo '</select>';

		                    break;

		                case 'number':
		                    $value = esc_attr($value);

		                    $min = $array['min'] ?? 1;

		                    $max = $array['max'] ?? 100;

		                    echo "<input size='2' type='number' min='".esc_attr($min)."' max='".esc_attr($max)."' id='".esc_attr($option)."' name='".esc_attr($name)."' value='".esc_attr($value)."' ".esc_attr($disabled).' />';

		                    break;

		                default:
		                    $value = esc_attr($value);

		                    echo "<input size='20' type='text' id='".esc_attr($option)."' name='".esc_attr($name)."' value='".esc_attr($value)."' ".esc_attr($disabled).' />';

		                    break;
		            }

		    ?>
					<p class="description">
						<span class="pro-name"><?php echo $disabled ? esc_html($feature).' feature: ' : ''; ?></span><?php echo wp_kses_post(wptexturize(self::format_description($array['description'], $disabled))); ?>
					</p>
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
