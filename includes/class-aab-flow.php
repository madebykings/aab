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

        // ── Brick type selection ──────────────────────────────────────────────
        $types     = AAB_Bricks::get_available_types();
        $has_types = !empty($types);

        // ── Availability check ────────────────────────────────────────────────
        if (!$has_types) {
            $available = AAB_Bricks::get_next_available();
            if (empty($available)) {
                return '<div class="aab-custom-checkout aab-custom-checkout--v2"><div style="padding:80px 42px;text-align:center;"><p>All bricks have been adopted for now. Check back soon.</p></div></div>';
            }
        }

        // ── Default sidebar data (overridden per type when types exist) ────────
        $default_product = wc_get_product(AAB_Woo::get_product_id());
        $default_img     = $default_product ? get_the_post_thumbnail_url($default_product->get_id(), 'large') : '';
        $default_tags    = $default_product ? wp_get_post_terms($default_product->get_id(), 'product_tag') : [];
        $default_unit    = (!empty($default_tags) && !is_wp_error($default_tags)) ? $default_tags[0]->name : ($default_product ? $default_product->get_name() : 'Standard Red Masonry Brick');
        $default_price   = $default_product ? wc_price($default_product->get_price()) : '';

        // When types exist, sidebar starts blank until user picks one.
        $sidebar_img   = $has_types ? '' : $default_img;
        $sidebar_unit  = $has_types ? '' : $default_unit;
        $sidebar_price = $has_types ? '' : $default_price;

        $sold_query  = new WP_Query([
            'post_type'      => 'brick',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [['key' => 'brick_status', 'value' => 'sold']],
        ]);
        $sold_bricks = (int) $sold_query->found_posts;

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
                            <div class="aab-rail__count">Step <span class="aab-flow-step-current">0<?php echo $has_types ? '1' : '1'; ?></span>/04</div>
                            <div class="aab-rail__progress">
                                <span class="aab-rail__progress-bar" style="width:25%"></span>
                            </div>
                        </div>

                        <div class="aab-progress aab-progress--rail">
                            <div class="aab-progress__item is-current" id="aab-rail-step1">
                                <span>1</span><label><?php echo $has_types ? 'Select' : 'Adopt'; ?></label>
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

                        <?php if ($has_types): ?>
                        <section class="aab-panel aab-panel--type-select" style="display:block;">
                            <div class="aab-panel__intro">
                                <h2>Select Your Brick</h2>
                                <p>Choose the type of brick you'd like to adopt.</p>
                                <?php if ($sold_bricks > 0): ?>
                                <p class="aab-social-proof"><?php echo number_format($sold_bricks); ?> <?php echo $sold_bricks === 1 ? 'brick has' : 'bricks have'; ?> already found a new home.</p>
                                <?php endif; ?>
                            </div>

                            <div class="aab-type-grid">
                                <?php foreach ($types as $type_data):
                                    $term       = $type_data['term'];
                                    $type_img   = $type_data['image_url'] ?: $default_img;
                                    $type_unit  = esc_attr($term->name);
                                    $type_price = $type_data['price_html'] ?: $default_price;
                                    $type_desc  = $type_data['description'];
                                ?>
                                <div class="aab-type-card"
                                     data-type="<?php echo esc_attr($term->slug); ?>"
                                     data-img="<?php echo esc_attr($type_img); ?>"
                                     data-unit="<?php echo esc_attr($term->name); ?>"
                                     data-price="<?php echo esc_attr(wp_strip_all_tags($type_price)); ?>"
                                     data-price-html="<?php echo esc_attr($type_price); ?>"
                                >
                                    <div class="aab-type-card__visual">
                                        <?php if ($type_img): ?>
                                            <img src="<?php echo esc_url($type_img); ?>" alt="<?php echo esc_attr($term->name); ?>">
                                        <?php else: ?>
                                            <div class="aab-type-card__img-fallback"></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="aab-type-card__body">
                                        <h3><?php echo esc_html($term->name); ?></h3>
                                        <?php if ($type_desc): ?>
                                            <p><?php echo esc_html($type_desc); ?></p>
                                        <?php endif; ?>
                                        <?php if ($type_price): ?>
                                            <div class="aab-type-card__price"><?php echo wp_kses_post($type_price); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="aab-type-card__check" aria-hidden="true">&#10003;</div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="aab-actions">
                                <button type="button" class="button aab-btn-type-continue" disabled>Continue</button>
                            </div>
                        </section>
                        <?php endif; ?>

                        <section class="aab-panel aab-panel--compose"<?php echo $has_types ? '' : ' style="display:block;"'; ?>>
                            <div class="aab-panel__intro">
                                <h2>Adopt A Brick</h2>
                                <p><?php echo esc_html(AAB_Settings::get_default_price_copy()); ?></p>
                                <p>You adopt it. They're the ones who have to look after it.</p>
                                <?php if (!$has_types && $sold_bricks > 0): ?>
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
                                <input type="hidden" name="brick_type" id="aab_brick_type" value="">

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
                                    <?php if ($has_types): ?>
                                        <button type="button" class="button aab-btn-type-back aab-btn-secondary">Back</button>
                                    <?php endif; ?>
                                    <button type="submit" class="button">Adopt this brick</button>
                                </div>
                            </form>
                        </section>

                    </div><!-- /.aab-checkout-main -->

                    <aside class="aab-checkout-side">
                        <div class="aab-side-card">
                            <div class="aab-side-card__title">Order Summary</div>

                            <div class="aab-side-card__visual">
                                <?php if ($sidebar_img): ?>
                                    <img src="<?php echo esc_url($sidebar_img); ?>" alt="" class="aab-side-card__img" id="aab-sidebar-img">
                                <?php else: ?>
                                    <div class="aab-side-card__img aab-side-card__img--fallback" id="aab-sidebar-img-fallback"></div>
                                    <img src="" alt="" class="aab-side-card__img" id="aab-sidebar-img" style="display:none;">
                                <?php endif; ?>
                            </div>

                            <div class="aab-side-meta">
                                <strong>Unit type</strong>
                                <span id="aab-sidebar-unit"><?php echo esc_html($sidebar_unit); ?></span>
                            </div>
                            <div class="aab-side-meta" style="opacity:0.35;" id="aab-adopting-as-row">
                                <strong>Adopting as</strong>
                                <span id="aab-adopting-as-value">—</span>
                            </div>

                            <div class="aab-side-card__total">
                                <span class="aab-side-card__total-label">Total cost</span>
                                <span class="aab-side-card__total-value" id="aab-sidebar-price"><?php echo wp_kses_post($sidebar_price); ?></span>
                            </div>

                        </div>
                    </aside>

                </div><!-- /.aab-content-wrap -->
            </div><!-- /.aab-layout-shell -->
        </div><!-- /.aab-custom-checkout -->
        <script>
        jQuery(function($) {
            var hasTypes = <?php echo $has_types ? 'true' : 'false'; ?>;

            // ── Type selection ────────────────────────────────────────────────
            if (hasTypes) {
                var $cards      = $('.aab-type-card');
                var $typeInput  = $('#aab_brick_type');
                var $continueBtn = $('.aab-btn-type-continue');
                var $backBtn    = $('.aab-btn-type-back');
                var $panelType  = $('.aab-panel--type-select');
                var $panelCompose = $('.aab-panel--compose');

                $cards.on('click', function() {
                    $cards.removeClass('is-selected');
                    $(this).addClass('is-selected');

                    var type    = $(this).data('type');
                    var img     = $(this).data('img');
                    var unit    = $(this).data('unit');
                    var priceHtml = $(this).data('price-html');

                    $typeInput.val(type);
                    $continueBtn.prop('disabled', false);

                    // Update sidebar
                    if (img) {
                        $('#aab-sidebar-img').attr('src', img).show();
                        $('#aab-sidebar-img-fallback').hide();
                    }
                    $('#aab-sidebar-unit').text(unit);
                    $('#aab-sidebar-price').html(priceHtml);
                });

                $continueBtn.on('click', function() {
                    $panelType.hide();
                    $panelCompose.show();
                    $('html, body').animate({ scrollTop: $panelCompose.offset().top - 20 }, 150);
                });

                $backBtn.on('click', function() {
                    $panelCompose.hide();
                    $panelType.show();
                    $('html, body').animate({ scrollTop: $panelType.offset().top - 20 }, 150);
                });
            }

            // ── Anonymous toggle ──────────────────────────────────────────────
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
