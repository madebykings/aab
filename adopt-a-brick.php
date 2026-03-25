<?php
/**
 * Plugin Name: Adopt A Brick
 * Description: Custom brick claim, checkout, reveal and chain logic for WooCommerce.
 * Version: 0.2.9
 * Author: Made By Kings
 */

if (!defined('ABSPATH')) exit;

define('AAB_VERSION', '0.2.9');
define('AAB_PATH', plugin_dir_path(__FILE__));
define('AAB_URL', plugin_dir_url(__FILE__));

require_once AAB_PATH . 'includes/class-aab-core.php';
require_once AAB_PATH . 'includes/class-aab-settings.php';
require_once AAB_PATH . 'includes/class-aab-assets.php';
require_once AAB_PATH . 'includes/class-aab-bricks.php';
require_once AAB_PATH . 'includes/class-aab-flow.php';
require_once AAB_PATH . 'includes/class-aab-woo.php';
require_once AAB_PATH . 'includes/class-aab-reveal.php';
require_once AAB_PATH . 'includes/class-aab-admin.php';
require_once AAB_PATH . 'includes/class-aab-cron.php';
require_once AAB_PATH . 'includes/class-aab-thankyou.php';
require_once AAB_PATH . 'includes/class-aab-email.php';
require_once AAB_PATH . 'includes/class-aab-checkout-template.php';

add_action('plugins_loaded', function () {
    AAB_Core::instance();
});

register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('aab_cleanup_reserved_bricks')) {
        wp_schedule_event(time(), 'hourly', 'aab_cleanup_reserved_bricks');
    }
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('aab_cleanup_reserved_bricks');
    flush_rewrite_rules();
});