<?php if (!defined('ABSPATH')) {
    exit;
} ?>

<div class='ppsfwoo-subscribe-button-container'>

	<div class='lds-ellipsis' id='lds-ellipsis-<?php echo esc_attr($product_id); ?>'><div></div><div></div><div></div><div></div></div>
	
	<div id="ppsfwoo-quantity-input-container-<?php echo esc_attr($product_id); ?>"></div>

	<button style='<?php echo esc_attr($style); ?>' class='ppsfwoo-subscribe-button' id='ppsfwoo-subscribe-button-<?php echo esc_attr($product_id); ?>'><?php echo esc_html($button_text); ?></button>

</div>