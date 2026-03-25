<?php
if (!defined('ABSPATH')) exit;

class AAB_Reveal {

    public static function init() {
        add_action('init', [__CLASS__, 'add_rewrite']);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_action('template_redirect', [__CLASS__, 'render_virtual_page']);
    }

    public static function add_rewrite() {
        add_rewrite_rule('^brick/([0-9]+)/?$', 'index.php?aab_brick_number=$matches[1]', 'top');
    }

    public static function query_vars($vars) {
        $vars[] = 'aab_brick_number';
        return $vars;
    }

    public static function render_virtual_page() {
        $brick_number = absint(get_query_var('aab_brick_number'));
        if (!$brick_number) return;

        $brick = AAB_Bricks::get_by_number($brick_number);

        status_header(200);
        nocache_headers();

        get_header();

        echo '<main class="aab-reveal-page">';

        if (!$brick) {
            echo '<div class="aab-container"><h1>That brick does not exist.</h1></div>';
            echo '</main>';
            get_footer();
            exit;
        }

        $status = get_post_meta($brick->ID, 'brick_status', true);

        if ($status !== 'sold') {
            echo '<div class="aab-container"><h1>This brick hasn\'t been adopted yet.</h1></div>';
            echo '</main>';
            get_footer();
            exit;
        }

        AAB_Bricks::increment_views($brick->ID);

        $display_name = get_post_meta($brick->ID, 'display_name', true);
        $anonymous       = (bool) get_post_meta($brick->ID, 'anonymous', true);
        $message         = get_post_meta($brick->ID, 'brick_message', true);
        $views           = (int) get_post_meta($brick->ID, 'revealed_views', true);
        $chain           = AAB_Bricks::get_chain_posts($brick->ID);
        $chain_count     = is_array($chain) ? count($chain) : 0;
        $can_send_back   = !$anonymous && !empty(get_post_meta($brick->ID, 'return_address_1', true));

        $reply_link = add_query_arg('reply_to', $brick_number, home_url('/adopt-a-brick/'));

        echo '<div class="aab-container">';
        echo '<div class="aab-step-pill">Brick #' . esc_html($brick_number) . '</div>';

        if ($anonymous) {
            echo '<h1>' . esc_html(AAB_Settings::get_funny_anonymous_text()) . '</h1>';
        } else {
            echo '<h1>Adopted by ' . esc_html($display_name) . '</h1>';
        }

        if ($message) {
            echo '<div class="aab-message"><strong>Message:</strong><br>' . nl2br(esc_html($message)) . '</div>';
        }

        echo '<div class="aab-reveal-stats">';
        echo '<div class="aab-stat"><strong>' . esc_html($views) . '</strong><span>Views</span></div>';
        if ($chain_count > 1) {
            echo '<div class="aab-stat"><strong>' . esc_html($chain_count) . '</strong><span>In this chain</span></div>';
        }
        echo '</div>';

        if ($chain_count > 1) {
            echo '<section class="aab-chain-section">';
            echo '<h2>The chain</h2>';
            echo '<div class="aab-chain-list">';

            foreach ($chain as $chain_brick) {
                $chain_number = get_post_meta($chain_brick->ID, 'brick_number', true);
                $chain_name   = get_post_meta($chain_brick->ID, 'display_name', true);
                $is_anonymous = (bool) get_post_meta($chain_brick->ID, 'anonymous', true);
                $is_current   = ((int) $chain_brick->ID === (int) $brick->ID);

                echo '<div class="aab-chain-item' . ($is_current ? ' is-current' : '') . '">';
                echo '<div class="aab-chain-number">Brick #' . esc_html($chain_number) . '</div>';
                echo '<div class="aab-chain-name">Adopted by ' . esc_html($is_anonymous ? 'Anonymous' : $chain_name) . '</div>';
                if ($is_current) {
                    echo '<div class="aab-chain-badge">This one</div>';
                }
                echo '</div>';
            }

            echo '</div>';
            echo '</section>';
        }

        echo '<section class="aab-chain-cta">';
        if ($can_send_back) {
            echo '<h2>' . esc_html(AAB_Settings::get_chain_cta_heading()) . '</h2>';
            echo '<p>' . esc_html(AAB_Settings::get_chain_cta_subtext()) . '</p>';
        } else {
            echo '<h2>Want to adopt a brick for someone?</h2>';
            echo '<p>Pick a brick, add a message, and let someone else deal with it.</p>';
        }
        echo '<div class="aab-button-row">';
        if ($can_send_back) {
            echo '<a class="aab-button" href="' . esc_url($reply_link) . '">Send one back</a>';
        }
        echo '<a class="aab-button ' . ($can_send_back ? 'aab-button-secondary' : '') . '" href="' . esc_url(home_url('/adopt-a-brick/')) . '">Adopt one for someone else</a>';
        echo '</div>';
        echo '</section>';

        echo '</div>';
        echo '</main>';

        get_footer();
        exit;
    }
}
