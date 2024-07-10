<?php
use PPSFWOO\Plan;

if (!defined('ABSPATH')) exit;

?>

<h2>PayPal Subscription Plans</h2>

<?php

$Plan = new Plan();

$plans = $Plan->get_plans();

if(sizeof($plans)) {

	self::display_template("table-plans", [
        'plans'      => $plans,
    	'paypal_url' => $this->env['paypal_url']
    ]);

}

?>

<a class="button" id="refresh" href="#">Refresh Plans</a>

<a class="button" id="create" href="<?php echo esc_url($this->env['paypal_url']); ?>/billing/plans" target="_blank">Create Plan</a>