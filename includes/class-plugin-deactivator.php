<?php
/**
 * Plugin deactivator
 */

if ( ! class_exists( 'Restaurant_Booking_Plugin_Deactivator' ) ) {

    class Restaurant_Booking_Plugin_Deactivator {

        public static function deactivate() {
            flush_rewrite_rules();
        }
    }
}
