<?php
/**
 * Customer model implementation with fallback data storage.
 *
 * @package RestaurantBooking\Models
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'RB_Customer' ) ) {

    /**
     * Customer data helpers bridging database tables and fallback storage.
     */
    class RB_Customer {

        /**
         * Option key used to persist fallback customers.
         */
        const OPTION_KEY = 'rb_fallback_customers';

        /**
         * Cached customer dataset.
         *
         * @var array|null
         */
        protected static $cache = null;

        /**
         * Cached table existence check.
         *
         * @var bool|null
         */
        protected static $table_exists_cache = null;

        /**
         * Retrieve all customers with optional filters.
         *
         * @param array $args Filter arguments.
         *
         * @return array
         */
        public static function get_all( $args = array() ) {
            return self::filter_customers( self::load_customers(), $args );
        }

        /**
         * Backwards compatible alias for get_all().
         *
         * @param array $args Filter arguments.
         *
         * @return array
         */
        public static function get_customers( $args = array() ) {
            return self::get_all( $args );
        }

        /**
         * Retrieve customers for a given segment (status) and optional location.
         *
         * @param string $segment     Segment identifier (vip, regular, blacklist, all).
         * @param int    $location_id Optional location filter.
         *
         * @return array
         */
        public static function get_segment( $segment = 'all', $location_id = 0 ) {
            $args = array();

            if ( 'all' !== $segment ) {
                $args['status'] = array( sanitize_key( $segment ) );
            }

            if ( $location_id ) {
                $args['location_id'] = (int) $location_id;
            }

            return self::filter_customers( self::load_customers(), $args );
        }

        /**
         * Retrieve a single customer record.
         *
         * @param int $customer_id Customer identifier.
         *
         * @return array|null
         */
        public static function get_customer( $customer_id ) {
            $customers  = self::load_customers();
            $customer_id = (int) $customer_id;

            return isset( $customers[ $customer_id ] ) ? $customers[ $customer_id ] : null;
        }

        /**
         * Update the stored status for a customer record.
         *
         * @param int    $customer_id Customer identifier.
         * @param string $status      New status (vip, regular, blacklist).
         *
         * @return bool
         */
        public static function update_status( $customer_id, $status ) {
            $customers   = self::load_customers();
            $customer_id = (int) $customer_id;

            if ( ! isset( $customers[ $customer_id ] ) ) {
                return false;
            }

            if ( self::customers_table_exists() ) {
                global $wpdb;

                $status = sanitize_key( $status );

                if ( ! in_array( $status, array( 'vip', 'regular', 'blacklist' ), true ) ) {
                    $status = 'regular';
                }

                $table  = self::get_table_name();
                $result = $wpdb->update(
                    $table,
                    array(
                        'status'     => $status,
                        'updated_at' => current_time( 'mysql', true ),
                    ),
                    array( 'id' => $customer_id ),
                    array( '%s', '%s' ),
                    array( '%d' )
                );

                return false !== $result;
            }

            $status = sanitize_key( $status );

            if ( ! in_array( $status, array( 'vip', 'regular', 'blacklist' ), true ) ) {
                $status = 'regular';
            }

            $customers[ $customer_id ]['status']     = $status;
            $customers[ $customer_id ]['updated_at'] = current_time( 'mysql', true );

            self::save_customers( $customers );

            return true;
        }

        /**
         * Insert or update a customer record from booking payload.
         *
         * @param array $data Booking payload.
         *
         * @return int Customer identifier.
         */
        public static function upsert_customer_from_booking( $data ) {
            if ( ! self::customers_table_exists() ) {
                return 0;
            }

            global $wpdb;

            $table      = self::get_table_name();
            $email      = sanitize_email( $data['email'] ?? ( $data['customer_email'] ?? '' ) );
            $phone      = sanitize_text_field( $data['phone'] ?? ( $data['customer_phone'] ?? '' ) );
            $first_name = sanitize_text_field( $data['first_name'] ?? '' );
            $last_name  = sanitize_text_field( $data['last_name'] ?? '' );
            $full_name  = sanitize_text_field( $data['customer_name'] ?? trim( $first_name . ' ' . $last_name ) );

            if ( '' === $first_name && '' === $last_name && '' !== $full_name ) {
                $parts      = preg_split( '/\s+/', $full_name, 2 );
                $first_name = $parts[0] ?? '';
                $last_name  = $parts[1] ?? '';
            }

            $existing_id = 0;

            if ( $email ) {
                $existing_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $table . ' WHERE email = %s LIMIT 1', $email ) );
            } elseif ( $phone ) {
                $existing_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $table . ' WHERE phone = %s LIMIT 1', $phone ) );
            }

            $payload = array(
                'first_name' => $first_name ? $first_name : ( $full_name ? $full_name : __( 'Guest', 'restaurant-booking' ) ),
                'last_name'  => $last_name,
                'email'      => $email,
                'phone'      => $phone,
                'status'     => 'regular',
            );

            if ( $existing_id ) {
                $payload['updated_at'] = current_time( 'mysql', true );

                $wpdb->update(
                    $table,
                    $payload,
                    array( 'id' => $existing_id ),
                    array( '%s', '%s', '%s', '%s', '%s', '%s' ),
                    array( '%d' )
                );

                return $existing_id;
            }

            $timestamp            = current_time( 'mysql', true );
            $payload['notes']     = '';
            $payload['preferences'] = '';
            $payload['created_at'] = $timestamp;
            $payload['updated_at'] = $timestamp;

            $result = $wpdb->insert(
                $table,
                $payload,
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
            );

            if ( false === $result ) {
                return 0;
            }

            return (int) $wpdb->insert_id;
        }

        /**
         * Persist the full customer dataset.
         *
         * @param array $customers Customer list keyed by identifier.
         */
        protected static function save_customers( $customers ) {
            self::$cache = $customers;
            update_option( self::OPTION_KEY, $customers, false );
        }

        /**
         * Retrieve the database table name.
         *
         * @return string
         */
        protected static function get_table_name() {
            global $wpdb;

            return $wpdb->prefix . 'rb_customers';
        }

        /**
         * Determine if the customers table exists.
         *
         * @return bool
         */
        protected static function customers_table_exists() {
            if ( null !== self::$table_exists_cache ) {
                return self::$table_exists_cache;
            }

            global $wpdb;

            $table = self::get_table_name();
            $query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table );
            $found = $wpdb->get_var( $query );

            self::$table_exists_cache = ( $found === $table );

            return self::$table_exists_cache;
        }

        /**
         * Load customer dataset from storage, seeding defaults when empty.
         *
         * @return array
         */
        protected static function load_customers() {
            if ( null !== self::$cache ) {
                return self::$cache;
            }

            $stored = get_option( self::OPTION_KEY, array() );

            if ( ! is_array( $stored ) || empty( $stored ) ) {
                $stored = self::seed_customers();
            }

            self::$cache = $stored;

            return self::$cache;
        }

        /**
         * Filter customers using query arguments.
         *
         * Supported args: status (array|string), location_id, search.
         *
         * @param array $customers Customer dataset.
         * @param array $args      Filters.
         *
         * @return array
         */
        protected static function filter_customers( $customers, $args ) {
            $args = wp_parse_args(
                (array) $args,
                array(
                    'status'      => array(),
                    'location_id' => 0,
                    'search'      => '',
                )
            );

            $statuses = array();
            if ( ! empty( $args['status'] ) ) {
                $statuses = array_map( 'sanitize_key', (array) $args['status'] );
            }

            $location_id = (int) $args['location_id'];
            $search      = $args['search'] ? strtolower( wp_strip_all_tags( $args['search'] ) ) : '';

            $results = array();

            foreach ( $customers as $customer ) {
                if ( ! empty( $statuses ) && ( ! isset( $customer['status'] ) || ! in_array( $customer['status'], $statuses, true ) ) ) {
                    continue;
                }

                if ( $location_id && (int) ( $customer['location_id'] ?? 0 ) !== $location_id ) {
                    continue;
                }

                if ( $search ) {
                    $haystack = strtolower(
                        implode(
                            ' ',
                            array(
                                $customer['name'] ?? '',
                                $customer['email'] ?? '',
                                $customer['phone'] ?? '',
                                $customer['notes'] ?? '',
                            )
                        )
                    );

                    if ( false === strpos( $haystack, $search ) ) {
                        continue;
                    }
                }

                $results[] = $customer;
            }

            return array_values( $results );
        }

        /**
         * Seed fallback customers for demo environments.
         *
         * @return array
         */
        protected static function seed_customers() {
            $customers = array();

            $customers[101] = self::build_customer(
                101,
                array(
                    'first_name'    => 'Ava',
                    'last_name'     => 'Carter',
                    'email'         => 'ava.carter@example.com',
                    'phone'         => '+1 555-0101',
                    'status'        => 'vip',
                    'location_id'   => 1,
                    'total_visits'  => 24,
                    'total_spent'   => '$2,480.00',
                    'avg_party_size'=> 3.2,
                    'last_visit'    => '2024-03-10',
                    'notes'         => __( 'Prefers window seating and Rioja red wine.', 'restaurant-booking' ),
                    'preferences'   => array(
                        __( 'Window table', 'restaurant-booking' ),
                        __( 'Sommelier welcome pour', 'restaurant-booking' ),
                    ),
                    'tags'          => array( 'VIP', 'Wine Club' ),
                    'history'       => array(
                        self::build_history_item( '2024-03-10', __( 'Main Dining Room', 'restaurant-booking' ), 'T1', 4, __( 'Completed', 'restaurant-booking' ) ),
                        self::build_history_item( '2024-02-18', __( 'Main Dining Room', 'restaurant-booking' ), 'T3', 2, __( 'Completed', 'restaurant-booking' ) ),
                        self::build_history_item( '2024-01-05', __( 'Rooftop Terrace', 'restaurant-booking' ), 'R1', 3, __( 'Completed', 'restaurant-booking' ) ),
                    ),
                )
            );

            $customers[102] = self::build_customer(
                102,
                array(
                    'first_name'    => 'Sarah',
                    'last_name'     => 'Wilson',
                    'email'         => 'sarah.wilson@example.com',
                    'phone'         => '+1 555-0102',
                    'status'        => 'regular',
                    'location_id'   => 1,
                    'total_visits'  => 8,
                    'total_spent'   => '$640.00',
                    'avg_party_size'=> 3,
                    'last_visit'    => '2024-03-14',
                    'notes'         => __( 'Allergic to shellfish. Enjoys early seatings.', 'restaurant-booking' ),
                    'preferences'   => array(
                        __( 'Early evening reservations', 'restaurant-booking' ),
                        __( 'Allergy note: shellfish', 'restaurant-booking' ),
                    ),
                    'tags'          => array( 'Family', 'Allergy' ),
                    'history'       => array(
                        self::build_history_item( '2024-03-14', __( 'Main Dining Room', 'restaurant-booking' ), 'T2', 3, __( 'Completed', 'restaurant-booking' ) ),
                        self::build_history_item( '2024-02-02', __( 'Main Dining Room', 'restaurant-booking' ), 'T4', 4, __( 'Completed', 'restaurant-booking' ) ),
                    ),
                )
            );

            $customers[103] = self::build_customer(
                103,
                array(
                    'first_name'    => 'Liam',
                    'last_name'     => 'Nguyen',
                    'email'         => 'liam.nguyen@example.com',
                    'phone'         => '+1 555-0103',
                    'status'        => 'regular',
                    'location_id'   => 2,
                    'total_visits'  => 12,
                    'total_spent'   => '$980.00',
                    'avg_party_size'=> 2.5,
                    'last_visit'    => '2024-03-12',
                    'notes'         => __( 'Loyal lunch guest. Appreciates seasonal tasting menus.', 'restaurant-booking' ),
                    'preferences'   => array( __( 'Chef tasting menu alerts', 'restaurant-booking' ) ),
                    'tags'          => array( 'Loyal', 'Lunch' ),
                    'history'       => array(
                        self::build_history_item( '2024-03-12', __( 'Rooftop Terrace', 'restaurant-booking' ), 'R1', 2, __( 'Completed', 'restaurant-booking' ) ),
                        self::build_history_item( '2024-02-28', __( 'Rooftop Terrace', 'restaurant-booking' ), 'Lounge', 3, __( 'Completed', 'restaurant-booking' ) ),
                    ),
                )
            );

            $customers[104] = self::build_customer(
                104,
                array(
                    'first_name'    => 'Noah',
                    'last_name'     => 'Garcia',
                    'email'         => 'noah.garcia@example.com',
                    'phone'         => '+1 555-0104',
                    'status'        => 'vip',
                    'location_id'   => 3,
                    'total_visits'  => 5,
                    'total_spent'   => '$3,200.00',
                    'avg_party_size'=> 10,
                    'last_visit'    => '2024-02-25',
                    'notes'         => __( 'Corporate client booking private dining space quarterly.', 'restaurant-booking' ),
                    'preferences'   => array( __( 'Projector & AV prep', 'restaurant-booking' ) ),
                    'tags'          => array( 'Corporate', 'High Value' ),
                    'history'       => array(
                        self::build_history_item( '2024-02-25', __( 'Private Dining Loft', 'restaurant-booking' ), 'Loft A', 12, __( 'Completed', 'restaurant-booking' ) ),
                        self::build_history_item( '2023-12-05', __( 'Private Dining Loft', 'restaurant-booking' ), 'Loft B', 9, __( 'Completed', 'restaurant-booking' ) ),
                    ),
                )
            );

            $customers[105] = self::build_customer(
                105,
                array(
                    'first_name'    => 'Emily',
                    'last_name'     => 'Stone',
                    'email'         => 'emily.stone@example.com',
                    'phone'         => '+1 555-0105',
                    'status'        => 'blacklist',
                    'location_id'   => 1,
                    'total_visits'  => 3,
                    'total_spent'   => '$180.00',
                    'avg_party_size'=> 2,
                    'last_visit'    => '2024-01-20',
                    'notes'         => __( 'Multiple last-minute cancellations without notice.', 'restaurant-booking' ),
                    'preferences'   => array(),
                    'tags'          => array( 'No-show' ),
                    'history'       => array(
                        self::build_history_item( '2024-01-20', __( 'Main Dining Room', 'restaurant-booking' ), 'T3', 2, __( 'Cancelled', 'restaurant-booking' ) ),
                        self::build_history_item( '2023-11-18', __( 'Main Dining Room', 'restaurant-booking' ), 'T2', 2, __( 'Cancelled', 'restaurant-booking' ) ),
                    ),
                )
            );

            self::save_customers( $customers );

            return $customers;
        }

        /**
         * Build a customer record with sane defaults.
         *
         * @param int   $id     Customer identifier.
         * @param array $data   Record data.
         *
         * @return array
         */
        protected static function build_customer( $id, $data ) {
            $first_name = isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : '';
            $last_name  = isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : '';
            $name       = isset( $data['name'] ) ? $data['name'] : trim( $first_name . ' ' . $last_name );

            return array(
                'id'             => (int) $id,
                'name'           => $name ? $name : __( 'Guest', 'restaurant-booking' ),
                'first_name'     => $first_name,
                'last_name'      => $last_name,
                'email'          => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
                'phone'          => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
                'status'         => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'regular',
                'location_id'    => isset( $data['location_id'] ) ? (int) $data['location_id'] : 0,
                'total_visits'   => isset( $data['total_visits'] ) ? (int) $data['total_visits'] : 0,
                'total_spent'    => isset( $data['total_spent'] ) ? $data['total_spent'] : '$0.00',
                'avg_party_size' => isset( $data['avg_party_size'] ) ? (float) $data['avg_party_size'] : 0,
                'last_visit'     => isset( $data['last_visit'] ) ? $data['last_visit'] : __( 'Never', 'restaurant-booking' ),
                'notes'          => isset( $data['notes'] ) ? wp_kses_post( $data['notes'] ) : '',
                'preferences'    => isset( $data['preferences'] ) && is_array( $data['preferences'] ) ? array_values( $data['preferences'] ) : array(),
                'tags'           => isset( $data['tags'] ) && is_array( $data['tags'] ) ? array_values( $data['tags'] ) : array(),
                'history'        => isset( $data['history'] ) && is_array( $data['history'] ) ? array_values( $data['history'] ) : array(),
                'initials'       => strtoupper( mb_substr( wp_strip_all_tags( $name ), 0, 2, 'UTF-8' ) ),
                'created_at'     => current_time( 'mysql', true ),
                'updated_at'     => current_time( 'mysql', true ),
            );
        }

        /**
         * Construct a history item for the fallback dataset.
         *
         * @param string $date      Date string.
         * @param string $location  Location label.
         * @param string $table     Table identifier.
         * @param int    $party     Party size.
         * @param string $status    Booking status label.
         *
         * @return array
         */
        protected static function build_history_item( $date, $location, $table, $party, $status ) {
            return array(
                'date'       => $date,
                'location'   => $location,
                'table'      => $table,
                'party_size' => (int) $party,
                'status'     => $status,
            );
        }
    }
}
