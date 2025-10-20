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
            if ( ! get_option( 'restaurant_booking_settings' ) ) {
                add_option(
                    'restaurant_booking_settings',
                    array(
                        'theme'                 => 'light',
                        'enable_dark_mode'      => true,
                        'booking_buffer_time'   => 30,
                        'max_party_size'        => 20,
                        'booking_advance_days'  => 90,
                    )
                );
            }
        }
    }
}
