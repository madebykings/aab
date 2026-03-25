<?php
if (!defined('ABSPATH')) exit;

class AAB_Flow {

    public static function init() {
        add_shortcode('aab_brick_flow', [__CLASS__, 'render_flow']);
    }

    public static function render_flow($atts = []) {
        if (!class_exists('WooCommerce')) {
            return '<p>WooCommerce is required.</p>';
        }

        wp_enqueue_style('aab-style');
        wp_enqueue_script('aab-script');

        // ── Reply-to context ──────────────────────────────────────────────────
        $reply_to_number   = isset($_GET['reply_to']) ? absint($_GET['reply_to']) : 0;
        $reply_to_post_id  = $reply_to_number ? AAB_Bricks::get_brick_post_id_from_number($reply_to_number) : 0;
        $reply_sender_name = '';
        if ($reply_to_post_id) {
            if (!(bool) get_post_meta($reply_to_post_id, 'anonymous', true)) {
                $reply_sender_name = (string) get_post_meta($reply_to_post_id, 'display_name', true);
            }
        }

        // ── Sidebar data ──────────────────────────────────────────────────────
        $product   = wc_get_product(AAB_Woo::get_product_id());
        $unit_type = $product ? $product->get_name() : 'Standard Red Masonry Brick';
        $price     = $product ? wc_price($product->get_price()) : '';

        $sold_query  = new WP_Query([
            'post_type'      => 'brick',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [['key' => 'brick_status', 'value' => 'sold']],
        ]);
        $sold_bricks = (int) $sold_query->found_posts;

        // Check there are actually bricks available before rendering the form
        $available = AAB_Bricks::get_next_available();
        if (empty($available)) {
            return '<div class="aab-custom-checkout aab-custom-checkout--v2"><div style="padding:80px 42px;text-align:center;"><p>All bricks have been adopted for now. Check back soon.</p></div></div>';
        }

        ob_start();

        if (function_exists('wc_print_notices')) {
            wc_print_notices();
        }
        ?>
        <div class="aab-custom-checkout aab-custom-checkout--v2">
            <div class="aab-layout-shell">

                <aside class="aab-rail">
                    <div class="aab-rail__inner">
                        <div class="aab-rail__header">
                            <div class="aab-rail__eyebrow">Checkout</div>
                            <div class="aab-rail__count">Step <span>01</span>/04</div>
                            <div class="aab-rail__progress">
                                <span class="aab-rail__progress-bar" style="width:25%"></span>
                            </div>
                        </div>

                        <div class="aab-progress aab-progress--rail">
                            <div class="aab-progress__item is-current">
                                <span>1</span><label>Adopt</label>
                            </div>
                            <div class="aab-progress__item">
                                <span>2</span><label>About you</label>
                            </div>
                            <div class="aab-progress__item">
                                <span>3</span><label>Who's it for?</label>
                            </div>
                            <div class="aab-progress__item">
                                <span>4</span><label>Payment</label>
                            </div>
                        </div>
                    </div>
                </aside>

                <div class="aab-content-wrap">
                    <div class="aab-checkout-main">

                        <section class="aab-panel" style="display:block;">
                            <div class="aab-panel__intro">
                                <div class="aab-step-pill">Step 01 / 04</div>
                                <h2>Adopt A Brick</h2>
                                <p><?php echo esc_html(AAB_Settings::get_default_price_copy()); ?></p>
                                <p>You adopt it. They're the ones who have to look after it.</p>
                                <?php if ($sold_bricks > 0): ?>
                                <p class="aab-social-proof"><?php echo number_format($sold_bricks); ?> <?php echo $sold_bricks === 1 ? 'brick has' : 'bricks have'; ?> already found a new home.</p>
                                <?php endif; ?>
                            </div>

                            <?php if ($reply_to_number): ?>
                                <div class="aab-reply-banner">
                                    Replying to Brick #<?php echo esc_html($reply_to_number); ?>
                                    <?php if ($reply_sender_name): ?>
                                        — sending one back to <?php echo esc_html($reply_sender_name); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <form method="post" class="aab-claim-form">
                                <?php wp_nonce_field('aab_adopt_brick', 'aab_adopt_nonce'); ?>
                                <input type="hidden" name="aab_action" value="adopt_brick">
                                <input type="hidden" name="reply_to_brick_id" value="<?php echo esc_attr($reply_to_post_id); ?>">

                                <div class="aab-fields">
                                    <div class="form-row form-row-wide">
                                        <label class="checkbox" for="aab_anonymous">
                                            <input type="checkbox" id="aab_anonymous" name="anonymous" value="1">
                                            Stay anonymous — they'll never know it was you
                                        </label>
                                    </div>
                                    <div class="form-row form-row-wide">
                                        <label for="brick_message">What do you want to say? <span class="optional">(optional)</span></label>
                                        <textarea id="brick_message" name="brick_message" rows="4" class="input-text" placeholder="Say something they won't forget…"></textarea>
                                    </div>
                                </div>

                                <div class="aab-actions">
                                    <button type="submit" class="button">Adopt this brick</button>
                                </div>
                            </form>
                        </section>

                    </div><!-- /.aab-checkout-main -->

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
                            <div class="aab-side-meta" style="opacity:0.35;" id="aab-adopting-as-row">
                                <strong>Adopting as</strong>
                                <span id="aab-adopting-as-value">—</span>
                            </div>

                            <div class="aab-side-card__total">
                                <span class="aab-side-card__total-label">Total cost</span>
                                <span class="aab-side-card__total-value"><?php echo wp_kses_post($price); ?></span>
                            </div>

                        </div>
                    </aside>

                </div><!-- /.aab-content-wrap -->
            </div><!-- /.aab-layout-shell -->
        </div><!-- /.aab-custom-checkout -->
        <script>
        jQuery(function($) {
            var $row   = $('#aab-adopting-as-row');
            var $value = $('#aab-adopting-as-value');
            var $anon  = $('#aab_anonymous');

            function updateAdoptingAs() {
                if ($anon.is(':checked')) {
                    $value.text('Anonymous');
                    $row.css('opacity', '1');
                } else {
                    $value.text('—');
                    $row.css('opacity', '0.35');
                }
            }

            $anon.on('change', updateAdoptingAs);
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
