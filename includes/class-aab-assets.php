<?php
if (!defined('ABSPATH')) exit;

class AAB_Assets {
    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
    }

    public static function enqueue() {
        wp_register_style(
            'aab-style',
            AAB_URL . 'assets/css/aab.css',
            [],
            AAB_VERSION
        );

        wp_register_script(
            'aab-script',
            AAB_URL . 'assets/js/aab.js',
            ['jquery'],
            AAB_VERSION,
            true
        );

        wp_localize_script('aab-script', 'AAB', [
            'ajax_url'              => admin_url('admin-ajax.php'),
            'nonce'                 => wp_create_nonce('aab_nonce'),
            'heartbeat_interval_ms' => 60000,
        ]);
    }
}