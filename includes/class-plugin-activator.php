<?php
/**
 * Plugin activator
 */

if ( ! class_exists( 'Restaurant_Booking_Plugin_Activator' ) ) {

    class Restaurant_Booking_Plugin_Activator {

        public static function activate() {
            self::create_tables();
            update_option( 'restaurant_booking_version', RESTAURANT_BOOKING_VERSION );
            self::set_default_options();
            if ( function_exists( 'restaurant_booking_register_roles' ) ) {
                restaurant_booking_register_roles();
            }
            if ( function_exists( 'restaurant_booking_add_role_capabilities' ) ) {
                restaurant_booking_add_role_capabilities();
            }
            if ( function_exists( 'restaurant_booking_register_rewrite_rules' ) ) {
                restaurant_booking_register_rewrite_rules();
            }
            flush_rewrite_rules();
        }

        protected static function create_tables() {
            global $wpdb;

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            require_once RESTAURANT_BOOKING_PATH . 'includes/database/schema.php';

            $schema = restaurant_booking_get_schema_definitions();

            foreach ( $schema as $sql ) {
                dbDelta( $sql );
            }
        }

        protected static function set_default_options() {
            if ( false === get_option( 'restaurant_booking_settings', false ) ) {
                $defaults = function_exists( 'restaurant_booking_get_default_settings' )
                    ? restaurant_booking_get_default_settings()
                    : array();

                add_option( 'restaurant_booking_settings', $defaults );
            }
        }
    }
}
