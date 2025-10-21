<?php
/**
 * Plugin deactivator
 */

if ( ! class_exists( 'Restaurant_Booking_Plugin_Deactivator' ) ) {

    class Restaurant_Booking_Plugin_Deactivator {

        public static function deactivate() {
            if ( function_exists( 'restaurant_booking_remove_role_capabilities' ) ) {
                restaurant_booking_remove_role_capabilities();
            }
            flush_rewrite_rules();
        }
    }
}
