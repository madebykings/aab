<?php
if (!defined('ABSPATH')) exit;

class AAB_Checkout_Template {

    public static function init() {
        add_shortcode('aab_checkout_flow', [__CLASS__, 'render']);
    }

    public static function render() {
        if (!function_exists('WC') || !class_exists('WooCommerce')) {
            return '<p>WooCommerce is required.</p>';
        }

        if (!WC()->cart) {
            return '<p>Cart unavailable.</p>';
        }

        $claim = AAB_Woo::get_active_claim_from_cart();

        if (!$claim) {
            wp_safe_redirect(home_url('/adopt-a-brick/'));
            exit;
        }

        wp_enqueue_style('aab-style');
        wp_enqueue_script('aab-script');

        $checkout = WC()->checkout();

        ob_start();

        if (function_exists('wc_print_notices')) {
            wc_print_notices();
        }

        $anonymous = !empty($claim['anonymous']);
        $message   = $claim['brick_message'] ?? '';

        $product   = wc_get_product(AAB_Woo::get_product_id());
        $unit_type = $product ? $product->get_name() : 'Standard Red Masonry Brick';

        $cart_total = WC()->cart ? WC()->cart->get_cart_total() : '';

        ?>

        <div class="aab-custom-checkout aab-custom-checkout--v2">
            <div class="aab-layout-shell">

                <aside class="aab-rail">
                    <div class="aab-rail__inner">
                        <div class="aab-rail__header">
                            <div class="aab-rail__eyebrow">Checkout</div>
                            <div class="aab-rail__count">Step <span class="aab-rail__count-current">02</span>/04</div>
                            <div class="aab-rail__progress"><span class="aab-rail__progress-bar"></span></div>
                        </div>

                        <div class="aab-progress aab-progress--rail">
                            <div class="aab-progress__item is-complete aab-progress__item--selection">
                                <span>1</span>
                                <label>Selection</label>
                            </div>
                            <div class="aab-progress__item is-current" data-step="1">
                                <span>2</span>
                                <label>Your details</label>
                            </div>
                            <div class="aab-progress__item" data-step="2">
                                <span>3</span>
                                <label>Recipient</label>
                            </div>
                            <div class="aab-progress__item" data-step="3">
                                <span>4</span>
                                <label>Payment</label>
                            </div>
                        </div>
                    </div>
                </aside>

                <div class="aab-content-wrap">
                    <div class="aab-checkout-main">
                        <form name="checkout" method="post" class="checkout woocommerce-checkout aab-checkout-form" action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data">

                            <section class="aab-panel aab-panel--step1">
                                <div class="aab-panel__intro">
                                    <div class="aab-step-pill">Step 02 / 04</div>
                                    <h2>Your Details</h2>
                                    <p>We need your name and email for the order. This makes sure your digital certificate and order updates reach you safely.</p>
                                </div>

                                <div class="aab-fields aab-fields--details">
                                    <?php
                                    woocommerce_form_field('billing_first_name', $checkout->get_checkout_fields('billing')['billing_first_name'], $checkout->get_value('billing_first_name'));
                                    woocommerce_form_field('billing_last_name',  $checkout->get_checkout_fields('billing')['billing_last_name'],  $checkout->get_value('billing_last_name'));
                                    woocommerce_form_field('billing_email',      $checkout->get_checkout_fields('billing')['billing_email'],      $checkout->get_value('billing_email'));
                                    woocommerce_form_field('billing_phone',      $checkout->get_checkout_fields('billing')['billing_phone'],      $checkout->get_value('billing_phone'));
                                    woocommerce_form_field('aab_enable_return_address', $checkout->get_checkout_fields('billing')['aab_enable_return_address'], $checkout->get_value('aab_enable_return_address'));
                                    ?>
                                </div>

                                <div class="aab-return-address-wrap">
                                    <div class="aab-subsection-head">
                                        <h3>Your address</h3>
                                        <p>Only needed if you want them to send one back to you.</p>
                                    </div>

                                    <div class="aab-fields">
                                        <?php
                                        woocommerce_form_field('billing_country',   $checkout->get_checkout_fields('billing')['billing_country'],   $checkout->get_value('billing_country'));
                                        woocommerce_form_field('billing_address_1', $checkout->get_checkout_fields('billing')['billing_address_1'], $checkout->get_value('billing_address_1'));
                                        woocommerce_form_field('billing_address_2', $checkout->get_checkout_fields('billing')['billing_address_2'], $checkout->get_value('billing_address_2'));
                                        woocommerce_form_field('billing_city',      $checkout->get_checkout_fields('billing')['billing_city'],      $checkout->get_value('billing_city'));
                                        woocommerce_form_field('billing_state',     $checkout->get_checkout_fields('billing')['billing_state'],     $checkout->get_value('billing_state'));
                                        woocommerce_form_field('billing_postcode',  $checkout->get_checkout_fields('billing')['billing_postcode'],  $checkout->get_value('billing_postcode'));
                                        ?>
                                    </div>
                                </div>

                                <div class="aab-actions">
                                    <button type="button" class="button aab-btn-next-step1">Continue to their details</button>
                                </div>
                            </section>

                            <section class="aab-panel aab-panel--step2">
                                <div class="aab-panel__intro">
                                    <h2>Their Details</h2>
                                    <p>Tell us who is looking after the brick and where it needs to land. Don’t know their name? “The Homeowner” works perfectly.</p>
                                </div>

                                <div class="aab-fields">
                                    <?php
                                    woocommerce_form_field('shipping_first_name', $checkout->get_checkout_fields('shipping')['shipping_first_name'], $checkout->get_value('shipping_first_name'));
                                    woocommerce_form_field('shipping_last_name',  $checkout->get_checkout_fields('shipping')['shipping_last_name'],  $checkout->get_value('shipping_last_name'));
                                    ?>
                                    <div class="form-row form-row-wide aab-readonly-field">
                                        <label>Their country</label>
                                        <span class="aab-readonly-value">United Kingdom</span>
                                        <input type="hidden" name="shipping_country" value="GB">
                                    </div>
                                    <?php
                                    woocommerce_form_field('shipping_address_1',  $checkout->get_checkout_fields('shipping')['shipping_address_1'],  $checkout->get_value('shipping_address_1'));
                                    woocommerce_form_field('shipping_address_2',  $checkout->get_checkout_fields('shipping')['shipping_address_2'],  $checkout->get_value('shipping_address_2'));
                                    woocommerce_form_field('shipping_city',       $checkout->get_checkout_fields('shipping')['shipping_city'],       $checkout->get_value('shipping_city'));
                                    woocommerce_form_field('shipping_state',      $checkout->get_checkout_fields('shipping')['shipping_state'],      $checkout->get_value('shipping_state'));
                                    woocommerce_form_field('shipping_postcode',   $checkout->get_checkout_fields('shipping')['shipping_postcode'],   $checkout->get_value('shipping_postcode'));
                                    ?>
                                </div>

                                <div class="aab-actions">
                                    <button type="button" class="button aab-btn-back-step1 aab-btn-secondary">Back</button>
                                    <button type="button" class="button aab-btn-next-step2">Continue to payment</button>
                                </div>
                            </section>

                            <section class="aab-panel aab-panel--step3">
                                <div class="aab-panel__intro">
                                    <div class="aab-step-pill">Step 04 / 04</div>
                                    <h2>Payment</h2>
                                    <p>One final step. Review the order and complete payment. Your brick number will be revealed once payment is confirmed.</p>
                                </div>

                                <div class="aab-payment-shell">
                                    <div id="order_review" class="woocommerce-checkout-review-order">
                                        <?php woocommerce_order_review(); ?>
                                    </div>

                                    <?php woocommerce_checkout_payment(); ?>
                                </div>

                                <div class="aab-actions">
                                    <button type="button" class="button aab-btn-back-step2 aab-btn-secondary">Back</button>
                                </div>
                            </section>

                        </form>
                    </div>

                    <aside class="aab-checkout-side">
                        <div class="aab-side-card">
                            <div class="aab-side-card__title">Order Summary</div>

                            <div class="aab-side-card__visual">
                                <div class="aab-side-card__img aab-side-card__img--fallback"></div>
                                <div class="aab-side-card__img-overlay">
                                    <span class="aab-side-card__serial-label">Brick ID</span>
                                    <div class="aab-side-card__serial-number">Assigned at checkout</div>
                                </div>
                            </div>

                            <div class="aab-side-meta">
                                <strong>Unit type</strong>
                                <span><?php echo esc_html($unit_type); ?></span>
                            </div>
                            <div class="aab-side-meta">
                                <strong>Adopting as</strong>
                                <span id="aab-adopting-as-value"><?php echo $anonymous ? 'Anonymous' : 'Your name'; ?></span>
                            </div>
                            <?php if ($message): ?>
                                <div class="aab-side-meta">
                                    <strong>Message</strong>
                                    <span><?php echo esc_html($message); ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="aab-side-card__total">
                                <span class="aab-side-card__total-label">Total cost</span>
                                <span class="aab-side-card__total-value"><?php echo wp_kses_post($cart_total); ?></span>
                            </div>

                        </div>
                    </aside>
                </div>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }
}
