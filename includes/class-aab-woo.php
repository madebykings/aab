<?php
if (!defined('ABSPATH')) exit;

class AAB_Woo {

    public static function init() {
        add_action('wp_loaded', [__CLASS__, 'handle_claim_submission']);

        add_filter('woocommerce_add_cart_item_data', [__CLASS__, 'add_cart_item_data'], 10, 3);
        add_filter('woocommerce_get_item_data', [__CLASS__, 'display_cart_item_data'], 10, 2);

        add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'add_order_item_meta'], 10, 4);
        add_action('woocommerce_checkout_update_order_meta', [__CLASS__, 'save_custom_checkout_fields']);
        add_action('woocommerce_checkout_create_order', [__CLASS__, 'add_order_level_meta'], 10, 2);
        add_action('woocommerce_payment_complete', [__CLASS__, 'mark_brick_sold'], 10);
        add_action('woocommerce_thankyou', [__CLASS__, 'mark_brick_sold'], 20);

        add_filter('woocommerce_checkout_fields', [__CLASS__, 'customise_checkout_fields'], 20);
        add_filter('woocommerce_default_address_fields', [__CLASS__, 'customise_default_address_fields'], 20);
        add_filter('woocommerce_checkout_get_value', [__CLASS__, 'prefill_checkout_values'], 20, 2);

        add_filter('woocommerce_enable_order_notes_field', '__return_false');
        add_filter('woocommerce_cart_needs_shipping', [__CLASS__, 'force_shipping_needed'], 20, 1);

        add_action('woocommerce_before_cart', [__CLASS__, 'handle_cart_access']);
        add_action('woocommerce_cart_item_removed', [__CLASS__, 'maybe_release_removed_brick'], 10, 2);
        add_action('woocommerce_cart_emptied', [__CLASS__, 'maybe_release_session_brick']);

        add_action('wp_ajax_aab_refresh_reservation', [__CLASS__, 'ajax_refresh_reservation']);
        add_action('wp_ajax_nopriv_aab_refresh_reservation', [__CLASS__, 'ajax_refresh_reservation']);

        add_action('wp_footer', [__CLASS__, 'render_checkout_ui_script'], 99);

        add_filter('woocommerce_is_checkout', [__CLASS__, 'is_brick_checkout_page']);
    }

    public static function get_product_id() {
        return AAB_Settings::get_product_id();
    }

    public static function handle_claim_submission() {
        if (empty($_POST['aab_action']) || $_POST['aab_action'] !== 'claim_brick') {
            return;
        }

        if (empty($_POST['aab_claim_nonce']) || !wp_verify_nonce($_POST['aab_claim_nonce'], 'aab_claim_brick')) {
            wc_add_notice('Security check failed. Please try again.', 'error');
            return;
        }

        if (!function_exists('WC') || !WC()->session || !WC()->cart) {
            return;
        }

        $anonymous     = !empty($_POST['anonymous']);
        $brick_message = sanitize_textarea_field($_POST['brick_message'] ?? '');
        $reply_to      = absint($_POST['reply_to_brick_id'] ?? 0);

        // Reserve a brick atomically at the moment of submission.
        $brick_id = AAB_Bricks::reserve_next_available_for_session();
        if (!$brick_id) {
            wc_add_notice('No bricks are currently available. Please try again soon.', 'error');
            return;
        }

        $brick_number = get_post_meta($brick_id, 'brick_number', true);
        $prefill      = self::get_reply_prefill_data($reply_to);

        $product_id = self::get_product_id();

        if (!$product_id) {
            wc_add_notice('Adopt A Brick product ID is not configured.', 'error');
            return;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wc_add_notice('Configured Adopt A Brick product could not be found.', 'error');
            return;
        }

        WC()->cart->empty_cart();

        // Set claim data AFTER empty_cart so it isn't wiped by maybe_release_session_brick.
        // sender_name / display_name are derived from billing details at order completion.
        WC()->session->set('aab_claim_data', [
            'brick_id'               => $brick_id,
            'brick_number'           => $brick_number,
            'anonymous'              => $anonymous ? 1 : 0,
            'sender_name'            => '',
            'display_name'           => $anonymous ? 'Anonymous' : '',
            'brick_message'          => $brick_message,
            'reply_to_brick_id'      => $reply_to,
            'enable_return_address'  => 0,
            'prefill_recipient_name' => $prefill['recipient_name'],
            'prefill_address_1'      => $prefill['address_1'],
            'prefill_address_2'      => $prefill['address_2'],
            'prefill_city'           => $prefill['city'],
            'prefill_state'          => $prefill['state'],
            'prefill_postcode'       => $prefill['postcode'],
            'prefill_country'        => $prefill['country'],
        ]);

        $added = WC()->cart->add_to_cart($product_id, 1);

        if (!$added) {
            // Release the reservation we just made so it doesn't stay locked.
            AAB_Bricks::release_brick($brick_id, AAB_Bricks::get_reservation_token());
            wc_add_notice('Could not add the brick to cart. Please try again.', 'error');
            return;
        }

        // Store brick_id in session so the heartbeat can refresh the reservation.
        WC()->session->set('aab_brick_id', $brick_id);

        wp_safe_redirect(home_url('/brick-checkout/'));
        exit;
    }

    public static function ajax_refresh_reservation() {
        check_ajax_referer('aab_nonce', 'nonce');

        if (!function_exists('WC') || !WC()->session) {
            wp_send_json_error(['message' => 'No session']);
        }

        $brick_id = (int) WC()->session->get('aab_brick_id');
        $token    = AAB_Bricks::get_reservation_token();

        if (!$brick_id) {
            wp_send_json_error(['message' => 'No brick']);
        }

        $ok = AAB_Bricks::refresh_reservation($brick_id, $token);

        if (!$ok) {
            wp_send_json_error(['message' => 'Reservation lost']);
        }

        wp_send_json_success(['brick_id' => $brick_id]);
    }

    public static function get_reply_prefill_data($reply_to_brick_id) {
        $reply_to_brick_id = (int) $reply_to_brick_id;

        $defaults = [
            'recipient_name' => '',
            'address_1'      => '',
            'address_2'      => '',
            'city'           => '',
            'state'          => '',
            'postcode'       => '',
            'country'        => 'GB',
        ];

        if (!$reply_to_brick_id) {
            return $defaults;
        }

        $sender_name = (string) get_post_meta($reply_to_brick_id, 'display_name', true);
        if ((bool) get_post_meta($reply_to_brick_id, 'anonymous', true)) {
            $sender_name = '';
        }

        return [
            'recipient_name' => $sender_name,
            'address_1'      => (string) get_post_meta($reply_to_brick_id, 'property_address_1', true),
            'address_2'      => (string) get_post_meta($reply_to_brick_id, 'property_address_2', true),
            'city'           => (string) get_post_meta($reply_to_brick_id, 'property_city', true),
            'state'          => (string) get_post_meta($reply_to_brick_id, 'property_county', true),
            'postcode'       => (string) get_post_meta($reply_to_brick_id, 'property_postcode', true),
            'country'        => (string) get_post_meta($reply_to_brick_id, 'property_country', true) ?: 'GB',
        ];
    }

    public static function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        if (!function_exists('WC') || !WC()->session) {
            return $cart_item_data;
        }

        $claim = WC()->session->get('aab_claim_data');

        if (!$claim || !is_array($claim)) {
            return $cart_item_data;
        }

        $configured_product_id = self::get_product_id();

        if ((int) $product_id !== (int) $configured_product_id) {
            return $cart_item_data;
        }

        $cart_item_data['aab_claim']  = $claim;
        $cart_item_data['unique_key'] = md5(wp_json_encode($claim) . microtime(true));

        WC()->session->__unset('aab_claim_data');

        return $cart_item_data;
    }

    public static function display_cart_item_data($item_data, $cart_item) {
        if (empty($cart_item['aab_claim']) || !is_array($cart_item['aab_claim'])) {
            return $item_data;
        }

        $claim = $cart_item['aab_claim'];

        $item_data[] = [
            'key'   => 'Brick Number',
            'value' => '#' . esc_html($claim['brick_number'] ?? ''),
        ];

        $item_data[] = [
            'key'   => 'Appears As',
            'value' => esc_html($claim['display_name'] ?? ''),
        ];

        if (!empty($claim['brick_message'])) {
            $item_data[] = [
                'key'   => 'Message',
                'value' => esc_html($claim['brick_message']),
            ];
        }

        return $item_data;
    }

    public static function add_order_item_meta($item, $cart_item_key, $values, $order) {
        if (empty($values['aab_claim']) || !is_array($values['aab_claim'])) {
            return;
        }

        foreach ($values['aab_claim'] as $key => $value) {
            $item->add_meta_data($key, $value, true);
        }
    }

    public static function save_custom_checkout_fields($order_id) {
        update_post_meta($order_id, '_aab_enable_return_address', isset($_POST['aab_enable_return_address']) ? 1 : 0);
    }

    public static function add_order_level_meta($order, $data) {
        $order->update_meta_data('_aab_checkout_mode', 'brick_flow');
    }

    public static function customise_default_address_fields($fields) {
        $fields['company']['required'] = false;
        $fields['company']['priority'] = 999;
        $fields['address_2']['required'] = false;
        return $fields;
    }

    public static function customise_checkout_fields($fields) {
        $claim = self::get_active_claim_from_cart();
        if (!$claim) {
            return $fields;
        }

        unset($fields['shipping']['shipping_company']);
        unset($fields['order']['order_comments']);

        $fields['billing']['billing_first_name']['label'] = 'Your first name';
        $fields['billing']['billing_last_name']['label']  = 'Your last name';
        $fields['billing']['billing_email']['label']      = 'Your email';
        $fields['billing']['billing_phone']['label']      = 'Your phone';
        $fields['billing']['billing_phone']['required']   = false;

        $fields['billing']['aab_enable_return_address'] = [
            'type'     => 'checkbox',
            'label'    => 'Let them send one back to me',
            'required' => false,
            'class'    => ['form-row-wide', 'aab-return-toggle-row'],
            'priority' => 35,
        ];

        $fields['billing']['billing_country']['label']   = 'Your country';
        $fields['billing']['billing_address_1']['label'] = 'Your address';
        $fields['billing']['billing_address_2']['label'] = 'Address line 2';
        $fields['billing']['billing_city']['label']      = 'Town / City';
        $fields['billing']['billing_state']['label']     = 'County / State';
        $fields['billing']['billing_postcode']['label']  = 'Postcode';

        $fields['billing']['billing_country']['required']   = false;
        $fields['billing']['billing_address_1']['required'] = false;
        $fields['billing']['billing_address_2']['required'] = false;
        $fields['billing']['billing_city']['required']      = false;
        $fields['billing']['billing_state']['required']     = false;
        $fields['billing']['billing_postcode']['required']  = false;

        $fields['shipping']['shipping_first_name']['label'] = 'Their first name';
        $fields['shipping']['shipping_last_name']['label']  = 'Their last name';
        $fields['shipping']['shipping_country']['label']    = 'Their country';
        $fields['shipping']['shipping_address_1']['label']  = 'Their address';
        $fields['shipping']['shipping_address_2']['label']  = 'Address line 2';
        $fields['shipping']['shipping_city']['label']       = 'Town / City';
        $fields['shipping']['shipping_state']['label']      = 'County / State';
        $fields['shipping']['shipping_postcode']['label']   = 'Postcode';

        return $fields;
    }

    public static function prefill_checkout_values($value, $input) {
        $claim = self::get_active_claim_from_cart();
        if (!$claim) {
            return $value;
        }

        $recipient_name = trim((string) ($claim['prefill_recipient_name'] ?? ''));
        $name_parts = preg_split('/\s+/', $recipient_name, 2);

        $has_prefill = !empty($name_parts[0]);
        $map = [
            'shipping_first_name' => $has_prefill ? $name_parts[0] : 'The',
            'shipping_last_name'  => $has_prefill ? ($name_parts[1] ?? '') : 'Homeowner',
            'shipping_address_1'  => $claim['prefill_address_1'] ?? '',
            'shipping_address_2'  => $claim['prefill_address_2'] ?? '',
            'shipping_city'       => $claim['prefill_city'] ?? '',
            'shipping_state'      => $claim['prefill_state'] ?? '',
            'shipping_postcode'   => $claim['prefill_postcode'] ?? '',
            'shipping_country'    => $claim['prefill_country'] ?? 'GB',
        ];

        if (array_key_exists($input, $map) && $map[$input] !== '') {
            return $map[$input];
        }

        return $value;
    }

    public static function force_shipping_needed($needs_shipping) {
        $claim = self::get_active_claim_from_cart();
        if ($claim) {
            return true;
        }
        return $needs_shipping;
    }

    public static function get_active_claim_from_cart() {
        if (!function_exists('WC') || !WC()->cart) {
            return null;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (!empty($cart_item['aab_claim']) && is_array($cart_item['aab_claim'])) {
                return $cart_item['aab_claim'];
            }
        }

        return null;
    }

    public static function handle_cart_access() {
        if (is_admin()) {
            return;
        }

        $claim = self::get_active_claim_from_cart();
        if (!$claim) {
            return;
        }

        if (is_cart()) {
            wp_safe_redirect(home_url('/brick-checkout/'));
            exit;
        }
    }

    public static function is_brick_checkout_page($is_checkout) {
        return $is_checkout || is_page('brick-checkout');
    }

    public static function maybe_release_removed_brick($cart_item_key, $cart) {
        if (empty($cart->removed_cart_contents[$cart_item_key]['aab_claim'])) {
            return;
        }

        $claim    = $cart->removed_cart_contents[$cart_item_key]['aab_claim'];
        $brick_id = (int) ($claim['brick_id'] ?? 0);
        $token    = AAB_Bricks::get_reservation_token();

        if ($brick_id) {
            AAB_Bricks::release_brick($brick_id, $token);
        }

        if (function_exists('WC') && WC()->session) {
            WC()->session->__unset('aab_brick_id');
            WC()->session->__unset('aab_claim_data');
        }
    }

    public static function maybe_release_session_brick() {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        $brick_id = (int) WC()->session->get('aab_brick_id');
        $token    = AAB_Bricks::get_reservation_token();

        if ($brick_id) {
            AAB_Bricks::release_brick($brick_id, $token);
        }

        WC()->session->__unset('aab_brick_id');
        WC()->session->__unset('aab_claim_data');
    }

    public static function mark_brick_sold($order_id) {
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->get_meta('_aab_bricks_finalised')) {
            return;
        }

        foreach ($order->get_items() as $item) {
            $brick_id = (int) $item->get_meta('brick_id', true);

            if (!$brick_id) {
                continue;
            }

            $reply_to = (int) $item->get_meta('reply_to_brick_id', true);
            $chain    = AAB_Bricks::build_chain_data($reply_to);

            $shipping_first      = $order->get_shipping_first_name();
            $shipping_last       = $order->get_shipping_last_name();
            $recipient_name      = trim($shipping_first . ' ' . $shipping_last);
            $brick_number        = $item->get_meta('brick_number', true);
            $reveal_url          = home_url('/brick/' . absint($brick_number) . '/');

            // Determine if the buyer left the default "The Homeowner" or entered a real name.
            $is_homeowner_default   = (strtolower(trim($shipping_first)) === 'the' && strtolower(trim($shipping_last)) === 'homeowner');
            $recipient_type         = $is_homeowner_default ? 'homeowner_default' : 'named_recipient';
            $recipient_display_name = $is_homeowner_default ? 'The Homeowner' : $recipient_name;

            $enable_return_address = (int) get_post_meta($order_id, '_aab_enable_return_address', true);

            $anonymous_flag = (bool) $item->get_meta('anonymous', true);
            $billing_name   = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
            $display_name   = $anonymous_flag ? 'Anonymous' : ($billing_name ?: 'Unknown');

            // Write resolved names back to the order item for admin/email reads.
            $item->update_meta_data('display_name', $display_name);
            $item->update_meta_data('sender_name', $billing_name);
            $item->save();

            $data = [
                'sender_name'              => $billing_name,
                'anonymous'                => $anonymous_flag ? 1 : 0,
                'display_name'             => $display_name,
                'brick_message'            => $item->get_meta('brick_message', true),
                'order_id'                 => $order_id,
                'reply_to_brick_id'        => $chain['reply_to_brick_id'],
                'chain_root_id'            => $chain['chain_root_id'] ?: $brick_id,
                'chain_depth'              => $chain['chain_depth'],
                'recipient_name'           => $recipient_name,
                'recipient_display_name'   => $recipient_display_name,
                'recipient_type'           => $recipient_type,
                'property_address_1'       => $order->get_shipping_address_1(),
                'property_address_2'       => $order->get_shipping_address_2(),
                'property_city'            => $order->get_shipping_city(),
                'property_county'          => $order->get_shipping_state(),
                'property_postcode'        => $order->get_shipping_postcode(),
                'property_country'         => $order->get_shipping_country(),
                'reveal_url'               => $reveal_url,
            ];

            if ($enable_return_address) {
                $data['return_address_1'] = $order->get_billing_address_1();
                $data['return_address_2'] = $order->get_billing_address_2();
                $data['return_city']      = $order->get_billing_city();
                $data['return_county']    = $order->get_billing_state();
                $data['return_postcode']  = $order->get_billing_postcode();
                $data['return_country']   = $order->get_billing_country();
            }

            AAB_Bricks::mark_sold($brick_id, $data);
            AAB_Bricks::ensure_root_if_missing($brick_id);
        }

        // Store clean recipient and property data on the order itself.
        $order->update_meta_data('_aab_recipient_display_name', $recipient_display_name);
        $order->update_meta_data('_aab_recipient_type', $recipient_type);
        $order->update_meta_data('_aab_property_address_1', $order->get_shipping_address_1());
        $order->update_meta_data('_aab_property_address_2', $order->get_shipping_address_2());
        $order->update_meta_data('_aab_property_city', $order->get_shipping_city());
        $order->update_meta_data('_aab_property_county', $order->get_shipping_state());
        $order->update_meta_data('_aab_property_postcode', $order->get_shipping_postcode());
        $order->update_meta_data('_aab_property_country', $order->get_shipping_country());
        $order->update_meta_data('_aab_brick_number', $brick_number);
        $order->update_meta_data('_aab_reveal_url', $reveal_url);
        $order->update_meta_data('_aab_bricks_finalised', 1);
        $order->save();

        if (function_exists('WC') && WC()->session) {
            WC()->session->__unset('aab_brick_id');
            WC()->session->__unset('aab_claim_data');
        }
    }

    public static function render_checkout_ui_script() {
        if (!is_page('brick-checkout')) {
            return;
        }

        $claim = self::get_active_claim_from_cart();
        if (!$claim) {
            return;
        }
        ?>
        <script>
        jQuery(function($) {
            var $form = $('.aab-checkout-form');
            if (!$form.length) return;

            function updateProgress(step) {
                $('.aab-progress__item').removeClass('is-current is-complete');
                $('.aab-progress__item:not([data-step])').addClass('is-complete');
                $('.aab-progress__item[data-step]').each(function() {
                    var idx = parseInt($(this).data('step'), 10);
                    if (idx < step) $(this).addClass('is-complete');
                    if (idx === step) $(this).addClass('is-current');
                });

                var displayStep = step + 1;
                $('.aab-rail__count-current').text(String(displayStep).padStart(2, '0'));
                $('.aab-rail__progress-bar').css('width', ((displayStep / 4) * 100) + '%');
            }

            function toggleReturnAddress() {
                var checked = $('#aab_enable_return_address').is(':checked');
                $('.aab-return-address-wrap').toggle(checked);
            }

            function clearErrors() {
                $('.aab-field-error').remove();
                $('.woocommerce-invalid').removeClass('woocommerce-invalid');
            }

            function showStep(step) {
                clearErrors();
                $('.aab-panel').hide();
                $('.aab-panel--step' + step).show();
                updateProgress(step);
                $('html, body').animate({ scrollTop: $('.aab-progress').offset().top - 20 }, 150);
            }

            function validateFields(selectors) {
                clearErrors();
                var valid = true;
                var $firstInvalid = null;

                selectors.forEach(function(selector) {
                    var $field = $(selector);
                    if (!$field.length || !$field.is(':visible')) return;

                    if (($field.val() || '').trim() === '') {
                        var $row = $field.closest('.form-row');
                        $row.addClass('woocommerce-invalid');
                        if (!$row.find('.aab-field-error').length) {
                            $field.after('<span class="aab-field-error">This field is required.</span>');
                        }
                        if (!$firstInvalid) $firstInvalid = $row;
                        valid = false;
                    }
                });

                if (!valid && $firstInvalid) {
                    $('html, body').animate({ scrollTop: $firstInvalid.offset().top - 30 }, 200);
                }

                return valid;
            }

            // Live "Adopting as" update from billing name fields.
            <?php if (!$anonymous): ?>
            function updateAdoptingAs() {
                var first = $.trim($('#billing_first_name').val());
                var last  = $.trim($('#billing_last_name').val());
                var name  = $.trim(first + ' ' + last);
                $('#aab-adopting-as-value').text(name || 'Your name');
            }
            $(document.body).on('input', '#billing_first_name, #billing_last_name', updateAdoptingAs);
            <?php endif; ?>

            toggleReturnAddress();
            $(document.body).on('change', '#aab_enable_return_address', toggleReturnAddress);

            // Clicking a completed step pill navigates back to it.
            $(document.body).on('click', '.aab-progress__item.is-complete[data-step]', function() {
                showStep(parseInt($(this).data('step'), 10));
            });

            $(document.body).on('click', '.aab-btn-next-step1', function(e) {
                e.preventDefault();

                var required = ['#billing_first_name', '#billing_last_name', '#billing_email'];

                if ($('#aab_enable_return_address').is(':checked')) {
                    required = required.concat([
                        '#billing_country',
                        '#billing_address_1',
                        '#billing_city',
                        '#billing_postcode'
                    ]);
                }

                if (!validateFields(required)) return;
                showStep(2);
            });

            $(document.body).on('click', '.aab-btn-next-step2', function(e) {
                e.preventDefault();

                var required = [
                    '#shipping_first_name',
                    '#shipping_address_1',
                    '#shipping_city',
                    '#shipping_postcode'
                ];

                if (!validateFields(required)) return;
                showStep(3);
            });

            $(document.body).on('click', '.aab-btn-back-step1', function(e) {
                e.preventDefault();
                showStep(1);
            });

            $(document.body).on('click', '.aab-btn-back-step2', function(e) {
                e.preventDefault();
                showStep(2);
            });

            showStep(1);
        });
        </script>
        <?php
    }
}