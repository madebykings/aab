<?php
if (!defined('ABSPATH')) exit;

class AAB_Bricks {

    const POST_TYPE = 'brick';

    public static function init() {
        // no hooks yet
    }

    public static function get_by_number($brick_number) {
        $posts = get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => 'brick_number',
                    'value' => (int) $brick_number,
                ]
            ]
        ]);

        return !empty($posts) ? $posts[0] : null;
    }

    public static function get_next_available() {
        $posts = get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'meta_key'       => 'brick_number',
            'meta_query'     => [
                [
                    'key'   => 'brick_status',
                    'value' => 'available',
                ]
            ]
        ]);

        return $posts ?: [];
    }

    public static function get_reservation_token() {
        if (!function_exists('WC') || !WC()->session) {
            return '';
        }

        $token = WC()->session->get('aab_reservation_token');

        if (!$token) {
            $token = wp_generate_password(20, false, false);
            WC()->session->set('aab_reservation_token', $token);
        }

        return $token;
    }

    public static function reserve_next_available_for_session() {
        $candidates = self::get_next_available();

        if (empty($candidates)) {
            return 0;
        }

        $token = self::get_reservation_token();

        foreach ($candidates as $brick) {
            $brick_id = (int) $brick->ID;
            if (self::try_reserve_brick($brick_id, $token)) {
                return $brick_id;
            }
        }

        return 0;
    }

    public static function try_reserve_brick($brick_id, $token = '') {
        $brick_id = (int) $brick_id;
        if (!$brick_id) {
            return false;
        }

        $status = get_post_meta($brick_id, 'brick_status', true);

        if ($status !== 'available') {
            return false;
        }

        $minutes = AAB_Settings::get_reserve_timeout();
        $reserved_until = gmdate('Y-m-d H:i:s', time() + ($minutes * 60));

        update_post_meta($brick_id, 'brick_status', 'reserved');
        update_post_meta($brick_id, 'reserved_until', $reserved_until);
        update_post_meta($brick_id, 'reservation_token', $token);

        // Re-read to reduce race risk.
        $check_status = get_post_meta($brick_id, 'brick_status', true);
        $check_token  = get_post_meta($brick_id, 'reservation_token', true);

        return ($check_status === 'reserved' && $check_token === $token);
    }

    public static function refresh_reservation($brick_id, $token = '') {
        $brick_id = (int) $brick_id;
        if (!$brick_id) {
            return false;
        }

        $status = get_post_meta($brick_id, 'brick_status', true);
        $saved_token = (string) get_post_meta($brick_id, 'reservation_token', true);

        if ($status !== 'reserved') {
            return false;
        }

        if ($token && $saved_token && $token !== $saved_token) {
            return false;
        }

        $minutes = AAB_Settings::get_reserve_timeout();
        $reserved_until = gmdate('Y-m-d H:i:s', time() + ($minutes * 60));
        update_post_meta($brick_id, 'reserved_until', $reserved_until);

        return true;
    }

    public static function release_brick($brick_id, $token = '') {
        $brick_id = (int) $brick_id;
        if (!$brick_id) {
            return false;
        }

        $status = get_post_meta($brick_id, 'brick_status', true);
        $saved_token = (string) get_post_meta($brick_id, 'reservation_token', true);

        if ($status === 'sold') {
            return false;
        }

        if ($token && $saved_token && $token !== $saved_token) {
            return false;
        }

        update_post_meta($brick_id, 'brick_status', 'available');
        update_post_meta($brick_id, 'reserved_until', '');
        update_post_meta($brick_id, 'reservation_token', '');
        update_post_meta($brick_id, 'sender_name', '');
        update_post_meta($brick_id, 'display_name', '');
        update_post_meta($brick_id, 'anonymous', false);
        update_post_meta($brick_id, 'brick_message', '');
        update_post_meta($brick_id, 'recipient_name', '');
        update_post_meta($brick_id, 'recipient_display_name', '');
        update_post_meta($brick_id, 'recipient_type', '');
        update_post_meta($brick_id, 'property_address_1', '');
        update_post_meta($brick_id, 'property_address_2', '');
        update_post_meta($brick_id, 'property_city', '');
        update_post_meta($brick_id, 'property_county', '');
        update_post_meta($brick_id, 'property_postcode', '');
        update_post_meta($brick_id, 'property_country', '');
        update_post_meta($brick_id, 'reply_to_brick_id', '');
        update_post_meta($brick_id, 'chain_root_id', '');
        update_post_meta($brick_id, 'chain_depth', 0);
        update_post_meta($brick_id, 'order_id', '');
        update_post_meta($brick_id, 'return_address_1', '');
        update_post_meta($brick_id, 'return_address_2', '');
        update_post_meta($brick_id, 'return_city', '');
        update_post_meta($brick_id, 'return_county', '');
        update_post_meta($brick_id, 'return_postcode', '');
        update_post_meta($brick_id, 'return_country', '');

        return true;
    }

    public static function mark_sold($brick_id, $data = []) {
        update_post_meta($brick_id, 'brick_status', 'sold');
        update_post_meta($brick_id, 'reserved_until', '');
        update_post_meta($brick_id, 'reservation_token', '');

        foreach ($data as $key => $value) {
            update_post_meta($brick_id, $key, $value);
        }
    }

    public static function increment_views($brick_id) {
        $views = (int) get_post_meta($brick_id, 'revealed_views', true);
        update_post_meta($brick_id, 'revealed_views', $views + 1);
    }

    public static function build_chain_data($reply_to_brick_id = 0) {
        $reply_to_brick_id = (int) $reply_to_brick_id;

        if (!$reply_to_brick_id) {
            return [
                'reply_to_brick_id' => 0,
                'chain_root_id'     => 0,
                'chain_depth'       => 0,
            ];
        }

        $parent = get_post($reply_to_brick_id);
        if (!$parent || $parent->post_type !== self::POST_TYPE) {
            return [
                'reply_to_brick_id' => 0,
                'chain_root_id'     => 0,
                'chain_depth'       => 0,
            ];
        }

        $parent_root  = (int) get_post_meta($reply_to_brick_id, 'chain_root_id', true);
        $parent_depth = (int) get_post_meta($reply_to_brick_id, 'chain_depth', true);

        return [
            'reply_to_brick_id' => $reply_to_brick_id,
            'chain_root_id'     => $parent_root ?: $reply_to_brick_id,
            'chain_depth'       => $parent_depth + 1,
        ];
    }

    public static function ensure_root_if_missing($brick_id) {
        $root = (int) get_post_meta($brick_id, 'chain_root_id', true);
        if (!$root) {
            update_post_meta($brick_id, 'chain_root_id', $brick_id);
        }
    }

    public static function get_chain_posts($brick_id) {
        $brick_id = (int) $brick_id;
        if (!$brick_id) {
            return [];
        }

        $root_id = (int) get_post_meta($brick_id, 'chain_root_id', true);
        if (!$root_id) {
            $root_id = $brick_id;
        }

        $posts = get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'meta_key'       => 'chain_depth',
            'meta_query'     => [
                [
                    'key'   => 'chain_root_id',
                    'value' => $root_id,
                ],
                [
                    'key'   => 'brick_status',
                    'value' => 'sold',
                ]
            ]
        ]);

        if (empty($posts)) {
            $root_post = get_post($root_id);
            if ($root_post && $root_post->post_type === self::POST_TYPE) {
                $posts = [$root_post];
            }
        }

        return $posts;
    }

    public static function get_brick_post_id_from_number($brick_number) {
        $brick = self::get_by_number($brick_number);
        return $brick ? (int) $brick->ID : 0;
    }

    public static function generate_range($start, $end) {
        $start = (int) $start;
        $end   = (int) $end;

        if ($start <= 0 || $end < $start) {
            return 0;
        }

        $created = 0;

        for ($i = $start; $i <= $end; $i++) {
            $existing = get_posts([
                'post_type'      => self::POST_TYPE,
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'meta_query'     => [
                    [
                        'key'   => 'brick_number',
                        'value' => $i,
                    ]
                ]
            ]);

            if ($existing) {
                continue;
            }

            $post_id = wp_insert_post([
                'post_type'   => self::POST_TYPE,
                'post_title'  => 'Brick #' . $i,
                'post_status' => 'publish',
            ]);

            if ($post_id) {
                update_post_meta($post_id, 'brick_number', $i);
                update_post_meta($post_id, 'brick_status', 'available');
                update_post_meta($post_id, 'chain_depth', 0);
                update_post_meta($post_id, 'revealed_views', 0);
                $created++;
            }
        }

        return $created;
    }
}