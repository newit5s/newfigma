<?php
/**
 * Plugin activator
 */

if ( ! class_exists( 'Restaurant_Booking_Plugin_Activator' ) ) {

    class Restaurant_Booking_Plugin_Activator {

        public static function activate() {
            $previous_version = get_option( 'restaurant_booking_version', false );

            if ( false === $previous_version ) {
                $previous_version = get_option( 'rb_plugin_version', false );
            }

            self::create_tables();
            self::run_migrations( $previous_version );
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

        /**
         * Execute database migrations when upgrading from legacy versions.
         *
         * @param string|false $previous_version Previously stored plugin version.
         */
        protected static function run_migrations( $previous_version ) {
            require_once RESTAURANT_BOOKING_PATH . 'includes/database/migrations.php';

            if ( class_exists( 'Restaurant_Booking_Database_Migrator' ) ) {
                Restaurant_Booking_Database_Migrator::maybe_run( $previous_version );
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
