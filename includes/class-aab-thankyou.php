<?php
if (!defined('ABSPATH')) exit;

class AAB_Thankyou {

    public static function init() {
        add_action('woocommerce_thankyou', [__CLASS__, 'render_thankyou_block'], 30);
    }

    public static function render_thankyou_block($order_id) {
        if (!$order_id || !is_order_received_page()) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $brick_number    = '';
        $reply_to_number = '';

        foreach ($order->get_items() as $item) {
            $brick_number = $item->get_meta('brick_number', true);
            $reply_to_id  = (int) $item->get_meta('reply_to_brick_id', true);

            if ($reply_to_id) {
                $reply_to_number = get_post_meta($reply_to_id, 'brick_number', true);
            }
            break;
        }

        if (!$brick_number) {
            return;
        }

        $reveal_url = home_url('/brick/' . absint($brick_number) . '/');

        echo '<section class="aab-thankyou">';
        echo '<div class="aab-thankyou__inner">';
        echo '<h2>Brick #' . esc_html($brick_number) . ' has been adopted.</h2>';

        if ($reply_to_number) {
            echo '<p>You sent one back. Brick #' . esc_html($reply_to_number) . ' started it — now they have another one to deal with.</p>';
        } else {
            echo '<p>It\'s on its way to its new home. They\'re responsible for it now.</p>';
        }

        echo '<div class="aab-button-row">';
        echo '<a class="aab-button" href="' . esc_url($reveal_url) . '">See your brick</a>';
        echo '<a class="aab-button aab-button-secondary" href="' . esc_url(home_url('/adopt-a-brick/')) . '">Adopt another</a>';
        echo '</div>';
        echo '</div>';
        echo '</section>';
    }
}
