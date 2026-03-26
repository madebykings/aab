<?php
if (!defined('ABSPATH')) exit;

class AAB_Bricks {

    const POST_TYPE = 'brick';

    public static function init() {
        add_action('init', [__CLASS__, 'register_taxonomy']);
    }

    public static function register_taxonomy() {
        register_taxonomy('brick_type', self::POST_TYPE, [
            'label'        => 'Brick Types',
            'public'       => true,
            'show_ui'      => true,
            'show_in_rest' => true,
            'hierarchical' => false,
            'rewrite'      => ['slug' => 'brick-type'],
        ]);
    }

    public static function get_available_types() {
        $terms = get_terms(['taxonomy' => 'brick_type', 'hide_empty' => false]);
        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        $available = [];
        foreach ($terms as $term) {
            $has_stock = get_posts([
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'tax_query'      => [['taxonomy' => 'brick_type', 'field' => 'term_id', 'terms' => $term->term_id]],
                'meta_query'     => [['key' => 'brick_status', 'value' => 'available']],
            ]);

            if (empty($has_stock)) {
                continue;
            }

            $product_id  = (int) get_term_meta($term->term_id, 'aab_type_product_id', true);
            $product     = $product_id ? wc_get_product($product_id) : null;
            $image_id    = (int) get_term_meta($term->term_id, 'aab_type_image_id', true);
            $image_url   = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';

            if (!$image_url && $product) {
                $image_url = get_the_post_thumbnail_url($product->get_id(), 'large');
            }

            $description = (string) get_term_meta($term->term_id, 'aab_type_description', true) ?: $term->description;
            $price_html  = $product ? wc_price($product->get_price()) : '';

            $available[] = [
                'term'        => $term,
                'product_id'  => $product_id,
                'image_url'   => $image_url,
                'description' => $description,
                'price_html'  => $price_html,
            ];
        }

        return $available;
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

    public static function assign_next_available($type_slug = '') {
        $args = [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'meta_key'       => 'brick_number',
            'meta_query'     => [['key' => 'brick_status', 'value' => 'available']],
        ];

        if ($type_slug) {
            $args['tax_query'] = [
                ['taxonomy' => 'brick_type', 'field' => 'slug', 'terms' => sanitize_key($type_slug)],
            ];
        }

        $candidates = get_posts($args);

        if (empty($candidates)) {
            return 0;
        }

        foreach ($candidates as $brick) {
            $brick_id = (int) $brick->ID;
            $status   = get_post_meta($brick_id, 'brick_status', true);

            if ($status !== 'available') {
                continue;
            }

            update_post_meta($brick_id, 'brick_status', 'sold');

            // Re-read to confirm we won the race.
            if (get_post_meta($brick_id, 'brick_status', true) === 'sold') {
                return $brick_id;
            }
        }

        return 0;
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