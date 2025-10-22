<?php
/**
 * Database migration helpers for upgrading from legacy plugin versions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Restaurant_Booking_Database_Migrator' ) ) {

    class Restaurant_Booking_Database_Migrator {

        const LEGACY_MIGRATION_OPTION = 'restaurant_booking_migration_v1_complete';

        /**
         * Attempt to run migrations based on the previously stored version.
         *
         * @param string|null $previous_version Stored plugin version prior to update.
         *
         * @return bool True when a migration executed, false otherwise.
         */
        public static function maybe_run( $previous_version = null ) {
            $previous_version = self::normalize_version( $previous_version );

            if ( ! $previous_version ) {
                return false;
            }

            if ( version_compare( $previous_version, '2.0.0', '>=' ) ) {
                return false;
            }

            if ( self::migration_already_completed() ) {
                return false;
            }

            $migrated = self::migrate_from_v1();

            if ( $migrated ) {
                update_option( 'restaurant_booking_version', RESTAURANT_BOOKING_VERSION );
            }

            return $migrated;
        }

        /**
         * Determine whether the legacy migration has already executed.
         *
         * @return bool
         */
        protected static function migration_already_completed() {
            return (bool) get_option( self::LEGACY_MIGRATION_OPTION, false );
        }

        /**
         * Normalize the provided version string and fall back to stored values.
         *
         * @param string|null $version Version string.
         *
         * @return string
         */
        protected static function normalize_version( $version ) {
            if ( is_string( $version ) && '' !== trim( $version ) ) {
                return trim( $version );
            }

            $stored_version = get_option( 'restaurant_booking_version', '' );

            if ( '' === $stored_version ) {
                $stored_version = get_option( 'rb_plugin_version', '' );
            }

            return is_string( $stored_version ) ? trim( $stored_version ) : '';
        }

        /**
         * Perform the migration from the legacy 1.x dataset to the custom tables.
         *
         * @return bool True when any dataset was migrated.
         */
        protected static function migrate_from_v1() {
            $location_result = self::migrate_locations();
            $table_result    = self::migrate_tables( $location_result['map'] );
            $customer_result = self::migrate_customers();
            $bookings_migrated = self::migrate_bookings(
                $location_result['map'],
                $customer_result,
                $table_result['map']
            );

            $did_migrate = (
                $location_result['migrated'] ||
                $table_result['migrated'] ||
                $customer_result['migrated'] ||
                $bookings_migrated
            );

            update_option( self::LEGACY_MIGRATION_OPTION, $did_migrate ? 1 : time(), false );

            return $did_migrate;
        }

        /**
         * Migrate legacy locations stored in options.
         *
         * @return array{
         *     map: array<int,int>,
         *     migrated: bool,
         * }
         */
        protected static function migrate_locations() {
            global $wpdb;

            $table = $wpdb->prefix . 'rb_locations';
            $map   = array();

            if ( ! self::table_exists( $table ) ) {
                return array(
                    'map'      => $map,
                    'migrated' => false,
                );
            }

            $existing = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table );

            if ( $existing > 0 ) {
                $rows = $wpdb->get_results( 'SELECT id FROM ' . $table );

                if ( $rows ) {
                    foreach ( $rows as $row ) {
                        $id       = isset( $row->id ) ? (int) $row->id : 0;
                        $map[ $id ] = $id;
                    }
                }

                return array(
                    'map'      => $map,
                    'migrated' => false,
                );
            }

            $stored_locations = get_option( 'rb_fallback_locations', array() );

            if ( empty( $stored_locations ) || ! is_array( $stored_locations ) ) {
                return array(
                    'map'      => $map,
                    'migrated' => false,
                );
            }

            $meta = get_option( 'rb_location_meta', array() );
            if ( ! is_array( $meta ) ) {
                $meta = array();
            }

            $migrated = false;

            foreach ( $stored_locations as $location ) {
                if ( is_object( $location ) ) {
                    $location = (array) $location;
                }

                $location = array_change_key_case( (array) $location, CASE_LOWER );

                $id = isset( $location['id'] ) ? absint( $location['id'] ) : 0;

                $insert  = array();
                $formats = array();

                if ( $id > 0 ) {
                    $insert['id'] = $id;
                    $formats[]    = '%d';
                }

                $insert['name']     = sanitize_text_field( $location['name'] ?? '' );
                $formats[]          = '%s';
                $insert['address']  = sanitize_text_field( $location['address'] ?? '' );
                $formats[]          = '%s';
                $insert['phone']    = sanitize_text_field( $location['phone'] ?? '' );
                $formats[]          = '%s';
                $insert['email']    = sanitize_email( $location['email'] ?? '' );
                $formats[]          = '%s';
                $insert['capacity'] = isset( $location['capacity'] ) ? max( 0, (int) $location['capacity'] ) : 0;
                $formats[]          = '%d';

                $status = sanitize_key( $location['status'] ?? 'active' );
                if ( '' === $status ) {
                    $status = 'active';
                }
                $insert['status'] = $status;
                $formats[]        = '%s';

                $insert['created_at'] = self::normalize_datetime( $location['created_at'] ?? '' );
                $formats[]            = '%s';
                $insert['updated_at'] = self::normalize_datetime( $location['updated_at'] ?? '' );
                $formats[]            = '%s';

                $result = $wpdb->insert( $table, $insert, $formats );

                if ( false === $result ) {
                    continue;
                }

                $new_id = $id > 0 ? $id : (int) $wpdb->insert_id;
                $map[ $id ?: $new_id ] = $new_id;
                $migrated = true;

                $meta[ $new_id ] = array(
                    'hours_weekday'    => isset( $location['hours_weekday'] ) ? sanitize_text_field( $location['hours_weekday'] ) : '',
                    'hours_weekend'    => isset( $location['hours_weekend'] ) ? sanitize_text_field( $location['hours_weekend'] ) : '',
                    'waitlist_enabled' => ! empty( $location['waitlist_enabled'] ),
                );
            }

            update_option( 'rb_location_meta', $meta, false );

            return array(
                'map'      => $map,
                'migrated' => $migrated,
            );
        }

        /**
         * Migrate legacy table layouts from options.
         *
         * @param array<int,int> $location_map Mapping of legacy to new location IDs.
         *
         * @return array{
         *     map: array<int, array<string,int>>,
         *     migrated: bool,
         * }
         */
        protected static function migrate_tables( $location_map ) {
            global $wpdb;

            $table = $wpdb->prefix . 'rb_tables';
            $map   = array();

            if ( ! self::table_exists( $table ) ) {
                return array(
                    'map'      => $map,
                    'migrated' => false,
                );
            }

            $existing = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table );

            if ( $existing > 0 ) {
                $rows = $wpdb->get_results( 'SELECT id, location_id, table_number FROM ' . $table );

                if ( $rows ) {
                    foreach ( $rows as $row ) {
                        $location_id = isset( $row->location_id ) ? (int) $row->location_id : 0;
                        $number      = isset( $row->table_number ) ? strtolower( sanitize_text_field( $row->table_number ) ) : '';
                        $id          = isset( $row->id ) ? (int) $row->id : 0;

                        if ( $location_id > 0 && '' !== $number && $id > 0 ) {
                            if ( ! isset( $map[ $location_id ] ) ) {
                                $map[ $location_id ] = array();
                            }

                            $map[ $location_id ][ $number ] = $id;
                        }
                    }
                }

                return array(
                    'map'      => $map,
                    'migrated' => false,
                );
            }

            $stored_layouts = get_option( 'rb_fallback_tables_layout', array() );

            if ( empty( $stored_layouts ) || ! is_array( $stored_layouts ) ) {
                return array(
                    'map'      => $map,
                    'migrated' => false,
                );
            }

            $migrated = false;

            foreach ( $stored_layouts as $legacy_location_id => $tables ) {
                if ( empty( $tables ) || ! is_array( $tables ) ) {
                    continue;
                }

                $target_location_id = self::map_location_id( $legacy_location_id, $location_map );

                foreach ( $tables as $table_row ) {
                    if ( is_object( $table_row ) ) {
                        $table_row = (array) $table_row;
                    }

                    $table_row = array_change_key_case( (array) $table_row, CASE_LOWER );

                    $id = isset( $table_row['id'] ) ? absint( $table_row['id'] ) : 0;

                    $insert  = array();
                    $formats = array();

                    if ( $id > 0 ) {
                        $insert['id'] = $id;
                        $formats[]    = '%d';
                    }

                    $insert['location_id'] = $target_location_id;
                    $formats[]             = '%d';

                    $number                = sanitize_text_field( $table_row['table_number'] ?? '' );
                    $insert['table_number'] = $number;
                    $formats[]              = '%s';

                    $insert['capacity'] = isset( $table_row['capacity'] ) ? max( 0, (int) $table_row['capacity'] ) : 0;
                    $formats[]          = '%d';

                    $status = sanitize_key( $table_row['status'] ?? 'available' );
                    if ( '' === $status ) {
                        $status = 'available';
                    }
                    $insert['status'] = $status;
                    $formats[]        = '%s';

                    $insert['position_x'] = isset( $table_row['position_x'] ) ? (int) $table_row['position_x'] : 0;
                    $formats[]            = '%d';

                    $insert['position_y'] = isset( $table_row['position_y'] ) ? (int) $table_row['position_y'] : 0;
                    $formats[]            = '%d';

                    $insert['shape'] = sanitize_text_field( $table_row['shape'] ?? 'rectangle' );
                    $formats[]       = '%s';

                    $insert['width'] = isset( $table_row['width'] ) ? (int) $table_row['width'] : 120;
                    $formats[]       = '%d';

                    $insert['height'] = isset( $table_row['height'] ) ? (int) $table_row['height'] : 120;
                    $formats[]        = '%d';

                    $insert['rotation'] = isset( $table_row['rotation'] ) ? (int) $table_row['rotation'] : 0;
                    $formats[]          = '%d';

                    $insert['created_at'] = self::normalize_datetime( $table_row['created_at'] ?? '' );
                    $formats[]            = '%s';
                    $insert['updated_at'] = self::normalize_datetime( $table_row['updated_at'] ?? '' );
                    $formats[]            = '%s';

                    $result = $wpdb->insert( $table, $insert, $formats );

                    if ( false === $result ) {
                        continue;
                    }

                    $new_id = $id > 0 ? $id : (int) $wpdb->insert_id;
                    $key    = strtolower( $number );

                    if ( '' !== $key ) {
                        if ( ! isset( $map[ $target_location_id ] ) ) {
                            $map[ $target_location_id ] = array();
                        }

                        $map[ $target_location_id ][ $key ] = $new_id;
                    }

                    $migrated = true;
                }
            }

            return array(
                'map'      => $map,
                'migrated' => $migrated,
            );
        }

        /**
         * Migrate fallback customers stored in options.
         *
         * @return array{
         *     by_id: array<int,int>,
         *     by_email: array<string,int>,
         *     by_phone: array<string,int>,
         *     migrated: bool,
         * }
         */
        protected static function migrate_customers() {
            global $wpdb;

            $table = $wpdb->prefix . 'rb_customers';
            $map   = array(
                'by_id'    => array(),
                'by_email' => array(),
                'by_phone' => array(),
                'migrated' => false,
            );

            if ( ! self::table_exists( $table ) ) {
                return $map;
            }

            $existing_rows = $wpdb->get_results( 'SELECT id, email, phone FROM ' . $table );

            if ( $existing_rows ) {
                foreach ( $existing_rows as $row ) {
                    $id = isset( $row->id ) ? (int) $row->id : 0;

                    if ( $id <= 0 ) {
                        continue;
                    }

                    $map['by_id'][ $id ] = $id;

                    if ( ! empty( $row->email ) ) {
                        $map['by_email'][ sanitize_email( $row->email ) ] = $id;
                    }

                    if ( ! empty( $row->phone ) ) {
                        $map['by_phone'][ sanitize_text_field( $row->phone ) ] = $id;
                    }
                }

                return $map;
            }

            $stored_customers = get_option( 'rb_fallback_customers', array() );

            if ( empty( $stored_customers ) || ! is_array( $stored_customers ) ) {
                return $map;
            }

            foreach ( $stored_customers as $customer ) {
                if ( is_object( $customer ) ) {
                    $customer = (array) $customer;
                }

                $customer = array_change_key_case( (array) $customer, CASE_LOWER );

                $id         = isset( $customer['id'] ) ? absint( $customer['id'] ) : 0;
                $first_name = sanitize_text_field( $customer['first_name'] ?? '' );
                $last_name  = sanitize_text_field( $customer['last_name'] ?? '' );
                $full_name  = sanitize_text_field( $customer['name'] ?? trim( $first_name . ' ' . $last_name ) );

                if ( '' === $full_name ) {
                    $full_name = __( 'Guest', 'restaurant-booking' );
                }

                $email = sanitize_email( $customer['email'] ?? '' );
                $phone = sanitize_text_field( $customer['phone'] ?? '' );

                $status = sanitize_key( $customer['status'] ?? 'regular' );
                if ( '' === $status ) {
                    $status = 'regular';
                }

                $notes = '';
                if ( isset( $customer['notes'] ) ) {
                    if ( is_array( $customer['notes'] ) ) {
                        $notes = implode( "\n", array_map( 'wp_kses_post', $customer['notes'] ) );
                    } else {
                        $notes = wp_kses_post( (string) $customer['notes'] );
                    }
                }

                $preferences = '';
                if ( isset( $customer['preferences'] ) ) {
                    if ( is_array( $customer['preferences'] ) ) {
                        $clean_preferences = array_map( 'sanitize_text_field', $customer['preferences'] );
                        $preferences       = wp_json_encode( array_values( array_filter( $clean_preferences ) ) );
                    } else {
                        $single_pref = sanitize_text_field( (string) $customer['preferences'] );
                        $preferences = $single_pref ? wp_json_encode( array( $single_pref ) ) : '';
                    }
                }

                if ( ! is_string( $preferences ) ) {
                    $preferences = '';
                }

                $insert  = array();
                $formats = array();

                if ( $id > 0 ) {
                    $insert['id'] = $id;
                    $formats[]    = '%d';
                }

                $insert['first_name'] = $first_name;
                $formats[]            = '%s';
                $insert['last_name']  = $last_name;
                $formats[]            = '%s';
                $insert['email']      = $email;
                $formats[]            = '%s';
                $insert['phone']      = $phone;
                $formats[]            = '%s';
                $insert['status']     = $status;
                $formats[]            = '%s';
                $insert['notes']      = $notes;
                $formats[]            = '%s';
                $insert['preferences'] = $preferences;
                $formats[]             = '%s';
                $insert['created_at']  = self::normalize_datetime( $customer['created_at'] ?? '' );
                $formats[]             = '%s';
                $insert['updated_at']  = self::normalize_datetime( $customer['updated_at'] ?? '' );
                $formats[]             = '%s';

                $result = $wpdb->insert( $table, $insert, $formats );

                if ( false === $result ) {
                    continue;
                }

                $new_id = $id > 0 ? $id : (int) $wpdb->insert_id;

                $map['by_id'][ $id ?: $new_id ] = $new_id;

                if ( $email ) {
                    $map['by_email'][ $email ] = $new_id;
                }

                if ( $phone ) {
                    $map['by_phone'][ $phone ] = $new_id;
                }

                $map['migrated'] = true;
            }

            return $map;
        }

        /**
         * Migrate legacy bookings stored in options.
         *
         * @param array<int,int>                $location_map Location identifier mapping.
         * @param array<string,array|bool>      $customer_map Customer mapping dataset.
         * @param array<int,array<string,int>>  $table_map    Table mapping dataset.
         *
         * @return bool
         */
        protected static function migrate_bookings( $location_map, $customer_map, $table_map ) {
            global $wpdb;

            $table = $wpdb->prefix . 'rb_bookings';

            if ( ! self::table_exists( $table ) ) {
                return false;
            }

            $existing = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table );

            if ( $existing > 0 ) {
                return false;
            }

            $stored_bookings = get_option( 'rb_fallback_bookings', array() );

            if ( empty( $stored_bookings ) || ! is_array( $stored_bookings ) ) {
                return false;
            }

            $migrated = false;

            foreach ( $stored_bookings as $booking ) {
                if ( is_object( $booking ) ) {
                    $booking = (array) $booking;
                }

                $booking = array_change_key_case( (array) $booking, CASE_LOWER );

                $id = isset( $booking['id'] ) ? absint( $booking['id'] ) : 0;

                $date = self::normalize_date( $booking['booking_date'] ?? ( $booking['date'] ?? '' ) );
                $time = self::normalize_time( $booking['booking_time'] ?? ( $booking['time'] ?? '' ) );

                $location_id = isset( $booking['location_id'] ) ? absint( $booking['location_id'] ) : 0;
                $location_id = self::map_location_id( $location_id, $location_map );

                $email = sanitize_email( $booking['customer_email'] ?? '' );
                $phone = sanitize_text_field( $booking['customer_phone'] ?? '' );

                $customer_name = sanitize_text_field( $booking['customer_name'] ?? '' );

                if ( '' === $customer_name ) {
                    $first = sanitize_text_field( $booking['first_name'] ?? '' );
                    $last  = sanitize_text_field( $booking['last_name'] ?? '' );
                    $customer_name = trim( $first . ' ' . $last );
                }

                if ( '' === $customer_name ) {
                    $customer_name = __( 'Guest', 'restaurant-booking' );
                }

                $customer_id = 0;

                if ( $email && isset( $customer_map['by_email'][ $email ] ) ) {
                    $customer_id = (int) $customer_map['by_email'][ $email ];
                } elseif ( $phone && isset( $customer_map['by_phone'][ $phone ] ) ) {
                    $customer_id = (int) $customer_map['by_phone'][ $phone ];
                }

                $table_number = sanitize_text_field( $booking['table_number'] ?? '' );
                $table_key    = strtolower( $table_number );

                $table_id = 0;
                if ( $table_key && isset( $table_map[ $location_id ][ $table_key ] ) ) {
                    $table_id = (int) $table_map[ $location_id ][ $table_key ];
                }

                $status = sanitize_key( $booking['status'] ?? 'pending' );
                if ( '' === $status ) {
                    $status = 'pending';
                }

                $insert  = array();
                $formats = array();

                if ( $id > 0 ) {
                    $insert['id'] = $id;
                    $formats[]    = '%d';
                }

                $insert['customer_id'] = $customer_id;
                $formats[]             = '%d';

                $insert['location_id'] = $location_id;
                $formats[]             = '%d';

                $insert['table_id'] = $table_id;
                $formats[]          = '%d';

                $insert['booking_date'] = $date;
                $formats[]              = '%s';

                $insert['booking_time'] = $time;
                $formats[]              = '%s';

                $insert['booking_datetime'] = self::combine_datetime( $date, $time );
                $formats[]                  = '%s';

                $insert['party_size'] = isset( $booking['party_size'] ) ? max( 1, (int) $booking['party_size'] ) : 1;
                $formats[]            = '%d';

                $insert['status'] = $status;
                $formats[]        = '%s';

                $insert['total_amount'] = isset( $booking['total_amount'] ) ? (float) $booking['total_amount'] : 0.0;
                $formats[]              = '%f';

                $insert['special_requests'] = isset( $booking['special_requests'] ) ? wp_kses_post( $booking['special_requests'] ) : '';
                $formats[]                  = '%s';

                $insert['customer_name']  = $customer_name;
                $formats[]                = '%s';
                $insert['customer_email'] = $email;
                $formats[]                = '%s';
                $insert['customer_phone'] = $phone;
                $formats[]                = '%s';

                $insert['created_at'] = self::normalize_datetime( $booking['created_at'] ?? '' );
                $formats[]            = '%s';
                $insert['updated_at'] = self::normalize_datetime( $booking['updated_at'] ?? '' );
                $formats[]            = '%s';

                $result = $wpdb->insert( $table, $insert, $formats );

                if ( false === $result ) {
                    continue;
                }

                $migrated = true;
            }

            return $migrated;
        }

        /**
         * Check whether a database table exists.
         *
         * @param string $table Fully qualified table name.
         *
         * @return bool
         */
        protected static function table_exists( $table ) {
            global $wpdb;

            $table = trim( $table );

            if ( '' === $table ) {
                return false;
            }

            $query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table );
            $found = $wpdb->get_var( $query );

            return $found === $table;
        }

        /**
         * Normalize date strings to Y-m-d.
         *
         * @param string $date Input date.
         *
         * @return string
         */
        protected static function normalize_date( $date ) {
            if ( empty( $date ) ) {
                return gmdate( 'Y-m-d' );
            }

            $timestamp = strtotime( (string) $date );

            if ( false === $timestamp ) {
                return gmdate( 'Y-m-d' );
            }

            return gmdate( 'Y-m-d', $timestamp );
        }

        /**
         * Normalize time strings to H:i:s format.
         *
         * @param string $time Input time.
         *
         * @return string
         */
        protected static function normalize_time( $time ) {
            if ( empty( $time ) ) {
                return '00:00:00';
            }

            $timestamp = strtotime( (string) $time );

            if ( false === $timestamp ) {
                return '00:00:00';
            }

            return gmdate( 'H:i:s', $timestamp );
        }

        /**
         * Normalize datetime strings to Y-m-d H:i:s.
         *
         * @param string $datetime Input datetime.
         *
         * @return string
         */
        protected static function normalize_datetime( $datetime ) {
            if ( empty( $datetime ) ) {
                return current_time( 'mysql', true );
            }

            $timestamp = strtotime( (string) $datetime );

            if ( false === $timestamp ) {
                return current_time( 'mysql', true );
            }

            return gmdate( 'Y-m-d H:i:s', $timestamp );
        }

        /**
         * Combine normalized date and time strings into a datetime value.
         *
         * @param string $date Date string (Y-m-d).
         * @param string $time Time string (H:i:s).
         *
         * @return string
         */
        protected static function combine_datetime( $date, $time ) {
            $date = $date ? $date : gmdate( 'Y-m-d' );
            $time = $time ? $time : '00:00:00';

            $timestamp = strtotime( $date . ' ' . $time . ' UTC' );

            if ( false === $timestamp ) {
                return current_time( 'mysql', true );
            }

            return gmdate( 'Y-m-d H:i:s', $timestamp );
        }

        /**
         * Resolve the mapped location identifier.
         *
         * @param int              $legacy_id Legacy identifier.
         * @param array<int,int>   $location_map Mapping dataset.
         *
         * @return int
         */
        protected static function map_location_id( $legacy_id, $location_map ) {
            $legacy_id = absint( $legacy_id );

            if ( $legacy_id > 0 && isset( $location_map[ $legacy_id ] ) ) {
                return (int) $location_map[ $legacy_id ];
            }

            return $legacy_id;
        }
    }
}
