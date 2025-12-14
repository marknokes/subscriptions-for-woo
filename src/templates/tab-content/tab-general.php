<?php

namespace PPSFWOO;

if (!defined('ABSPATH')) {
    exit;
} ?>

<h2>General Settings</h2>

<?php

$ppsfwoo_wp_keses_options = [
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

		<?php foreach (self::$options as $ppsfwoo_option => $ppsfwoo_array) {
		    if (isset($ppsfwoo_array['is_premium']) && $ppsfwoo_is_premium = $ppsfwoo_array['is_premium']) {
		        $ppsfwoo_feature = 'Premium';

		        $ppsfwoo_disabled = $ppsfwoo_is_premium && (!PPSFWOO_PLUGIN_EXTRAS || !PluginExtras::onboarding_complete()) ? 'disabled=true' : '';
		    } elseif (isset($ppsfwoo_array['is_enterprise']) && $ppsfwoo_is_enterprise = $ppsfwoo_array['is_enterprise']) {
		        $ppsfwoo_feature = 'Enterprise';

		        $ppsfwoo_disabled = $ppsfwoo_is_enterprise && (!PPSFWOO_ENTERPRISE || !PluginExtras::onboarding_complete()) ? 'disabled=true' : '';
		    } else {
		        $ppsfwoo_feature = $ppsfwoo_disabled = '';
		    }

		    if ('skip_settings_field' === $ppsfwoo_array['type']) {
		        continue;
		    }

		    $ppsfwoo_name = !$ppsfwoo_disabled ? $ppsfwoo_option : '';

		    $ppsfwoo_value = self::get_option($ppsfwoo_option);

		    ?>

			<tr valign="top">

				<td style="padding-bottom: 15px;">

					<h3>
						<label for="<?php echo esc_attr($ppsfwoo_name); ?>"><?php echo esc_attr($ppsfwoo_array['name']); ?></label>
					</h3>

					<?php

		            switch ($ppsfwoo_array['type']) {
		                case 'checkbox':
		                    $ppsfwoo_checked = checked(1, $ppsfwoo_value, false);
		                    ?>
							<input type='checkbox' name='<?php echo esc_attr($ppsfwoo_name); ?>' value='1' <?php echo esc_attr($ppsfwoo_checked); ?> <?php echo esc_attr($ppsfwoo_disabled); ?> />
							<?php
		                    break;

		                case 'wysiwyg':
		                    wp_editor($ppsfwoo_value, $ppsfwoo_option, $ppsfwoo_settings = ['textarea_rows' => '10']);

		                    break;

		                case 'textarea':
		                    ?>
							<textarea rows="10" cols="100" id="<?php echo esc_attr($ppsfwoo_option); ?>" name="<?php echo esc_attr($ppsfwoo_name); ?>" <?php echo esc_attr($ppsfwoo_disabled); ?>><?php echo esc_textarea($ppsfwoo_value); ?></textarea>
							<?php
		                    break;

		                case 'select':
		                    echo "<select name='".esc_attr($ppsfwoo_name)."' ".esc_attr($ppsfwoo_disabled).'>';

		                    $ppsfwoo_options = $ppsfwoo_array['options'] ?? false;

		                    if ($ppsfwoo_options) {
		                        foreach (array_keys($ppsfwoo_options) as $ppsfwoo_option) {
		                            $ppsfwoo_selected = selected($ppsfwoo_option, $ppsfwoo_value, false);

		                            echo wp_kses("<option value='{$ppsfwoo_option}' {$ppsfwoo_selected}>{$ppsfwoo_options[$ppsfwoo_option]}</option>", $ppsfwoo_wp_keses_options);
		                        }
		                    } else {
		                        $ppsfwoo_post_type = isset($ppsfwoo_array['post_type']) ? $ppsfwoo_array['post_type'] : 'page';

		                        switch ($ppsfwoo_post_type) {
		                            case 'category':
		                                if ($ppsfwoo_categories = get_terms($ppsfwoo_array['taxonomy'])) {
		                                    foreach ($ppsfwoo_categories as $ppsfwoo_category) {
		                                        $ppsfwoo_selected = selected($ppsfwoo_category->slug, $ppsfwoo_value, false);

		                                        echo wp_kses("<option value='{$ppsfwoo_category->slug}' {$ppsfwoo_selected}>{$ppsfwoo_category->name}</option>", $ppsfwoo_wp_keses_options);
		                                    }
		                                }

		                                break;

		                            default:
		                                if ($ppsfwoo_pages = get_posts(['numberposts' => -1, 'post_status' => 'any', 'post_type' => [$ppsfwoo_post_type]])) {
		                                    foreach ($ppsfwoo_pages as $ppsfwoo_page) {
		                                        $ppsfwoo_selected = selected($ppsfwoo_page->ID, $ppsfwoo_value, false);

		                                        echo wp_kses("<option value='{$ppsfwoo_page->ID}' {$ppsfwoo_selected}>{$ppsfwoo_page->post_title}</option>", $ppsfwoo_wp_keses_options);
		                                    }
		                                }

		                                break;
		                        }
		                    }

		                    echo '</select>';

		                    break;

		                case 'multiselect':
		                    echo wp_kses("<select name='{$ppsfwoo_name['page_ids']}[]' multiple='multiple'".esc_attr($ppsfwoo_disabled).'>', ['select' => ['name' => [], 'multiple' => []]]);

		                    $ppsfwoo_type = isset($ppsfwoo_array['post_type']) ? $ppsfwoo_array['post_type'] : 'page';

		                    if ($ppsfwoo_pages = get_posts(['numberposts' => -1, 'post_type' => [$ppsfwoo_type]])) {
		                        foreach ($ppsfwoo_pages as $ppsfwoo_page) {
		                            $ppsfwoo_key = array_search($ppsfwoo_page->ID, $ppsfwoo_value['page_ids']);

		                            $ppsfwoo_selected = selected($ppsfwoo_page->ID, $ppsfwoo_value['page_ids'][$ppsfwoo_key]);

		                            echo wp_kses("<option value='{$ppsfwoo_page->ID}' {$ppsfwoo_selected}>{$ppsfwoo_page->post_title}</option>", $ppsfwoo_wp_keses_options);
		                        }
		                    }

		                    echo '</select>';

		                    break;

		                case 'number':
		                    $ppsfwoo_value = esc_attr($ppsfwoo_value);

		                    $ppsfwoo_min = $ppsfwoo_array['min'] ?? 1;

		                    $ppsfwoo_max = $ppsfwoo_array['max'] ?? 100;

		                    echo "<input size='2' type='number' min='".esc_attr($ppsfwoo_min)."' max='".esc_attr($ppsfwoo_max)."' id='".esc_attr($ppsfwoo_option)."' name='".esc_attr($ppsfwoo_name)."' value='".esc_attr($ppsfwoo_value)."' ".esc_attr($ppsfwoo_disabled).' />';

		                    break;

		                default:
		                    $ppsfwoo_value = esc_attr($ppsfwoo_value);

		                    echo "<input size='20' type='text' id='".esc_attr($ppsfwoo_option)."' name='".esc_attr($ppsfwoo_name)."' value='".esc_attr($ppsfwoo_value)."' ".esc_attr($ppsfwoo_disabled).' />';

		                    break;
		            }

		    ?>
					<p class="description">
						<span class="pro-name"><?php echo $ppsfwoo_disabled ? esc_html($ppsfwoo_feature).' feature: ' : ''; ?></span><?php echo wp_kses_post(wptexturize(self::format_description($ppsfwoo_array['description'], $ppsfwoo_disabled))); ?>
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
