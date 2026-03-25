<?php
if (!defined('ABSPATH')) exit;

class AAB_Admin {

    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
    }

    public static function add_meta_box() {
        add_meta_box(
            'aab_order_brick_meta',
            'Adopt A Brick',
            [__CLASS__, 'render_order_meta_box'],
            'shop_order',
            'side',
            'default'
        );
    }

    public static function render_order_meta_box($post) {
        $order = wc_get_order($post->ID);

        if (!$order) {
            echo '<p>No order found.</p>';
            return;
        }

        $found = false;

        foreach ($order->get_items() as $item) {
            $brick_id = (int) $item->get_meta('brick_id', true);
            if (!$brick_id) {
                continue;
            }

            $found = true;

            $brick_number = $item->get_meta('brick_number', true);
            // Fall back to brick post meta in case mark_brick_sold hasn't run yet.
            $display_name = $item->get_meta('display_name', true) ?: get_post_meta($brick_id, 'display_name', true);
            $reply_to_id  = (int) $item->get_meta('reply_to_brick_id', true);

            $reply_to_number = '';
            if ($reply_to_id) {
                $reply_to_number = get_post_meta($reply_to_id, 'brick_number', true);
            }

            $status                 = get_post_meta($brick_id, 'brick_status', true);
            $recipient_display_name = get_post_meta($brick_id, 'recipient_display_name', true);
            $recipient_type         = get_post_meta($brick_id, 'recipient_type', true);
            $property_city          = get_post_meta($brick_id, 'property_city', true);
            $property_postcode      = get_post_meta($brick_id, 'property_postcode', true);
            $chain_depth            = (int) get_post_meta($brick_id, 'chain_depth', true);
            $views                  = (int) get_post_meta($brick_id, 'revealed_views', true);

            echo '<p><strong>Brick:</strong> #' . esc_html($brick_number) . '</p>';
            echo '<p><strong>Status:</strong> ' . esc_html($status) . '</p>';
            echo '<p><strong>Adopted by:</strong> ' . esc_html($display_name ?: '—') . '</p>';
            echo '<p><strong>For:</strong> ' . esc_html($recipient_display_name ?: '—') . '</p>';

            $type_label = $recipient_type === 'homeowner_default' ? 'Default (The Homeowner)' : 'Named recipient';
            echo '<p><strong>Recipient type:</strong> ' . esc_html($type_label ?: '—') . '</p>';

            if ($property_city || $property_postcode) {
                echo '<p><strong>Property:</strong> ' . esc_html(trim($property_city . ' ' . $property_postcode)) . '</p>';
            }

            if ($reply_to_number) {
                echo '<p><strong>Reply to:</strong> #' . esc_html($reply_to_number) . '</p>';
            }

            echo '<p><strong>Chain depth:</strong> ' . esc_html($chain_depth) . '</p>';
            echo '<p><strong>Reveal views:</strong> ' . esc_html($views) . '</p>';

            $message = get_post_meta($brick_id, 'brick_message', true);
            if ($message) {
                echo '<hr>';
                echo '<p><strong>Message</strong></p>';
                echo '<div style="white-space: pre-wrap;">' . esc_html($message) . '</div>';
            }

            break;
        }

        if (!$found) {
            echo '<p>No brick data on this order.</p>';
        }
    }
}