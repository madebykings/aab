<?php
if (!defined('ABSPATH')) exit;

class AAB_Cron {

    public static function init() {
        add_action('aab_cleanup_reserved_bricks', [__CLASS__, 'cleanup_reserved']);
    }

    public static function cleanup_reserved() {
        $posts = get_posts([
            'post_type'      => 'brick',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'   => 'brick_status',
                    'value' => 'reserved',
                ]
            ]
        ]);

        $now = time();

        foreach ($posts as $brick) {
            $until = get_post_meta($brick->ID, 'reserved_until', true);
            if ($until && strtotime($until) < $now) {
                AAB_Bricks::release_brick($brick->ID);
            }
        }
    }
}