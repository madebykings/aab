<?php
if (!defined('ABSPATH')) exit;

class AAB_Core {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        AAB_Settings::init();
        AAB_Assets::init();
        AAB_Bricks::init();
        AAB_Flow::init();
        AAB_Woo::init();
        AAB_Reveal::init();
        AAB_Admin::init();
        AAB_Cron::init();
        AAB_Thankyou::init();
        AAB_Email::init();
        AAB_Checkout_Template::init();
    }
}