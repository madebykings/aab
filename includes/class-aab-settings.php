<?php
if (!defined('ABSPATH')) exit;

class AAB_Settings {

    /**
     * Put your most likely JetEngine options page storage keys here.
     * We check several because JetEngine/WP setups vary.
     */
    const OPTION_BUCKET_CANDIDATES = [
        'adopt_a_brick_settings',
        'adopt-a-brick-settings',
        'adoptabricksettings',
    ];

    public static function init() {
        // no hooks needed
    }

    public static function get($key, $default = '') {

        /*
         * 1) Check likely JetEngine options page buckets.
         * JetEngine often stores the whole page as a single WP option array.
         */
        foreach (self::OPTION_BUCKET_CANDIDATES as $bucket) {
            $options = get_option($bucket);

            if (is_array($options) && array_key_exists($key, $options)) {
                $value = $options[$key];

                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }

        /*
         * 2) Check standalone option fallback.
         * Some setups/plugins save individual values directly.
         */
        $single = get_option($key, null);

        if ($single !== null && $single !== '') {
            return $single;
        }

        return $default;
    }

    public static function get_product_id() {
        return (int) self::get('aab_product_id', 0);
    }

    public static function get_reserve_timeout() {
        $minutes = (int) self::get('aab_reserve_timeout', 30);
        return $minutes > 0 ? $minutes : 30;
    }

    public static function get_funny_anonymous_text() {
        return (string) self::get(
            'aab_funny_anonymous_text',
            'This brick was sent by... someone who enjoys chaos 👀'
        );
    }

    public static function get_chain_cta_heading() {
        return (string) self::get(
            'aab_chain_cta_heading',
            'Don’t stop the chain.'
        );
    }

    public static function get_chain_cta_subtext() {
        return (string) self::get(
            'aab_chain_cta_subtext',
            'Your move.'
        );
    }

    public static function get_default_price_copy() {
        return (string) self::get(
            'aab_default_price_copy',
            'Claim your brick before someone else does.'
        );
    }
}