<?php
if (!defined('ABSPATH')) exit;

class AAB_Admin {

    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);

        add_action('brick_type_add_form_fields',  [__CLASS__, 'brick_type_add_fields']);
        add_action('brick_type_edit_form_fields', [__CLASS__, 'brick_type_edit_fields']);
        add_action('created_brick_type',          [__CLASS__, 'save_brick_type_fields']);
        add_action('edited_brick_type',           [__CLASS__, 'save_brick_type_fields']);
    }

    public static function brick_type_add_fields($taxonomy) {
        ?>
        <div class="form-field">
            <label for="aab_type_product_id">WooCommerce Product ID</label>
            <input type="number" name="aab_type_product_id" id="aab_type_product_id" value="" min="0">
            <p>Leave blank to use the default Adopt A Brick product. Enter a product ID to use a different product (e.g. a higher-priced limited edition).</p>
        </div>
        <div class="form-field">
            <label for="aab_type_description">Short description</label>
            <textarea name="aab_type_description" id="aab_type_description" rows="3"></textarea>
            <p>Shown on the brick selection card.</p>
        </div>
        <div class="form-field">
            <label for="aab_type_image_id">Image attachment ID</label>
            <input type="number" name="aab_type_image_id" id="aab_type_image_id" value="" min="0">
            <p>Attachment ID from the Media Library. Falls back to the product's featured image if blank.</p>
        </div>
        <?php
    }

    public static function brick_type_edit_fields($term) {
        $product_id  = (int) get_term_meta($term->term_id, 'aab_type_product_id', true);
        $description = (string) get_term_meta($term->term_id, 'aab_type_description', true);
        $image_id    = (int) get_term_meta($term->term_id, 'aab_type_image_id', true);
        ?>
        <tr class="form-field">
            <th><label for="aab_type_product_id">WooCommerce Product ID</label></th>
            <td>
                <input type="number" name="aab_type_product_id" id="aab_type_product_id" value="<?php echo esc_attr($product_id ?: ''); ?>" min="0">
                <p class="description">Leave blank to use the default product. Enter a product ID for a different price.</p>
            </td>
        </tr>
        <tr class="form-field">
            <th><label for="aab_type_description">Short description</label></th>
            <td>
                <textarea name="aab_type_description" id="aab_type_description" rows="3"><?php echo esc_textarea($description); ?></textarea>
                <p class="description">Shown on the brick selection card.</p>
            </td>
        </tr>
        <tr class="form-field">
            <th><label for="aab_type_image_id">Image attachment ID</label></th>
            <td>
                <input type="number" name="aab_type_image_id" id="aab_type_image_id" value="<?php echo esc_attr($image_id ?: ''); ?>" min="0">
                <p class="description">Attachment ID from the Media Library. Falls back to the product's featured image if blank.</p>
            </td>
        </tr>
        <?php
    }

    public static function save_brick_type_fields($term_id) {
        if (isset($_POST['aab_type_product_id'])) {
            update_term_meta($term_id, 'aab_type_product_id', absint($_POST['aab_type_product_id']));
        }
        if (isset($_POST['aab_type_description'])) {
            update_term_meta($term_id, 'aab_type_description', sanitize_textarea_field($_POST['aab_type_description']));
        }
        if (isset($_POST['aab_type_image_id'])) {
            update_term_meta($term_id, 'aab_type_image_id', absint($_POST['aab_type_image_id']));
        }
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