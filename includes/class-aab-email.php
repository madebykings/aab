<?php
if (!defined('ABSPATH')) exit;

/**
 * Email handling for Adopt A Brick.
 *
 * WooCommerce's standard order confirmation email goes to the buyer automatically.
 * No custom email logic is needed here. No homeowner/recipient email is sent —
 * recipient contact details are not collected as part of this flow.
 *
 * If a dedicated branded email is added in future, hook into
 * woocommerce_email_after_order_table or register a custom WC_Email class here.
 */
class AAB_Email {
    public static function init() {}
}
