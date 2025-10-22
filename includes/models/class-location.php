<?php
/**
 * Location model implementation.
 *
 * @package RestaurantBooking\Models
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'RB_Location' ) ) {

    /**
     * Location data representation.
     */
    class RB_Location {

        /**
         * Location identifier.
         *
         * @var int
         */
        public $id = 0;

        /**
         * Location display name.
         *
         * @var string
         */
        public $name = '';

        /**
         * Address line.
         *
         * @var string
         */
        public $address = '';

        /**
         * Contact phone number.
         *
         * @var string
         */
        public $phone = '';

        /**
         * Contact email.
         *
         * @var string
         */
        public $email = '';

        /**
         * Seating capacity.
         *
         * @var int
         */
        public $capacity = 0;

        /**
         * Location status.
         *
         * @var string
         */
        public $status = 'active';

        /**
         * Weekday opening hours label.
         *
         * @var string
         */
        public $hours_weekday = '';

        /**
         * Weekend opening hours label.
         *
         * @var string
         */
        public $hours_weekend = '';

        /**
         * Whether waitlist is enabled.
         *
         * @var bool
         */
        public $waitlist_enabled = false;

        /**
         * Option key storing fallback locations when custom tables are unavailable.
         */
        const FALLBACK_OPTION_KEY = 'rb_fallback_locations';

        /**
         * Option key storing extended metadata for each location.
         */
        const META_OPTION_KEY = 'rb_location_meta';

        /**
         * Cached fallback dataset.
         *
         * @var array|null
         */
        protected static $fallback_cache = null;

        /**
         * Cached metadata dataset.
         *
         * @var array|null
         */
        protected static $meta_cache = null;

        /**
         * Constructor.
         *
         * @param int    $id   Location identifier.
         * @param string $name Display name.
         */
        public function __construct( $id = 0, $name = '' ) {
            $this->id   = absint( $id );
            $this->name = $name;
        }

        /**
         * Fetch all locations from the database or fallback storage.
         *
         * @param array $args Query arguments.
         *
         * @return array
         */
        public static function get_all_locations( $args = array() ) {
            global $wpdb;

            $defaults = array(
                'status' => '',
                'search' => '',
            );

            $args = wp_parse_args( $args, $defaults );

            $table = self::get_table_name();

            if ( ! self::table_exists( $table ) ) {
                return self::filter_locations( self::get_fallback_locations(), $args );
            }

            $where  = array();
            $params = array();

            if ( ! empty( $args['status'] ) ) {
                $where[]  = 'status = %s';
                $params[] = sanitize_key( $args['status'] );
            }

            if ( ! empty( $args['search'] ) ) {
                $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
                $where[]  = '(name LIKE %s OR address LIKE %s)';
                $params[] = $like;
                $params[] = $like;
            }

            $sql = 'SELECT id, name, address, phone, email, capacity, status FROM ' . $table;

            if ( $where ) {
                $sql .= ' WHERE ' . implode( ' AND ', $where );
            }

            $sql .= ' ORDER BY name ASC';
            $sql  = self::prepare( $sql, $params );

            $records   = $wpdb->get_results( $sql );
            $locations = array();

            if ( $records ) {
                foreach ( $records as $record ) {
                    $location              = new self( $record->id, $record->name );
                    $location->address     = isset( $record->address ) ? $record->address : '';
                    $location->phone       = isset( $record->phone ) ? $record->phone : '';
                    $location->email       = isset( $record->email ) ? $record->email : '';
                    $location->capacity    = isset( $record->capacity ) ? (int) $record->capacity : 0;
                    $location->status      = isset( $record->status ) ? $record->status : 'active';
                    $locations[]           = self::hydrate_location( $location );
                }
            }

            if ( empty( $locations ) ) {
                return self::filter_locations( self::get_fallback_locations(), $args );
            }

            return $locations;
        }

        /**
         * Retrieve tables for a location.
         *
         * @param int $location_id Location identifier.
         *
         * @return array
         */
        public static function get_tables( $location_id ) {
            $location_id = absint( $location_id );

            if ( class_exists( 'RB_Table' ) ) {
                if ( method_exists( 'RB_Table', 'get_tables_by_location' ) ) {
                    return RB_Table::get_tables_by_location( $location_id );
                } elseif ( method_exists( 'RB_Table', 'get_tables' ) ) {
                    return RB_Table::get_tables( $location_id );
                }
            }

            return array();
        }

        /**
         * Fetch a single location.
         *
         * @param int $location_id Location identifier.
         *
         * @return RB_Location|null
         */
        public static function get_location( $location_id ) {
            global $wpdb;

            $location_id = absint( $location_id );
            if ( $location_id <= 0 ) {
                return null;
            }

            $table = self::get_table_name();

            if ( ! self::table_exists( $table ) ) {
                $fallback = self::load_fallback_locations_raw();

                if ( isset( $fallback[ $location_id ] ) ) {
                    return self::hydrate_location( self::build_location_from_array( $fallback[ $location_id ] ) );
                }

                return self::get_default_location_by_id( $location_id );
            }

            $sql = self::prepare(
                'SELECT id, name, address, phone, email, capacity, status FROM ' . $table . ' WHERE id = %d',
                array( $location_id )
            );

            $record = $wpdb->get_row( $sql );

            if ( ! $record ) {
                return self::get_default_location_by_id( $location_id );
            }

            $location              = new self( $record->id, $record->name );
            $location->address     = isset( $record->address ) ? $record->address : '';
            $location->phone       = isset( $record->phone ) ? $record->phone : '';
            $location->email       = isset( $record->email ) ? $record->email : '';
            $location->capacity    = isset( $record->capacity ) ? (int) $record->capacity : 0;
            $location->status      = isset( $record->status ) ? $record->status : 'active';

            return self::hydrate_location( $location );
        }

        /**
         * Create a new location record.
         *
         * @param array $data Location payload.
         *
         * @return RB_Location|null
         */
        public static function create_location( $data ) {
            global $wpdb;

            $name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';

            if ( '' === $name ) {
                return null;
            }

            $location_data = array(
                'name'     => $name,
                'address'  => isset( $data['address'] ) ? sanitize_text_field( $data['address'] ) : '',
                'phone'    => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
                'email'    => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
                'capacity' => isset( $data['capacity'] ) ? max( 0, (int) $data['capacity'] ) : 0,
                'status'   => self::sanitize_status( isset( $data['status'] ) ? $data['status'] : 'active' ),
            );

            $table = self::get_table_name();

            if ( self::table_exists( $table ) ) {
                $now    = current_time( 'mysql', true );
                $insert = array_merge(
                    $location_data,
                    array(
                        'created_at' => $now,
                        'updated_at' => $now,
                    )
                );

                $result = $wpdb->insert(
                    $table,
                    $insert,
                    array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
                );

                if ( false === $result ) {
                    return null;
                }

                $location_id = (int) $wpdb->insert_id;
                self::save_location_meta( $location_id, $data );
                self::reset_caches();

                return self::get_location( $location_id );
            }

            $locations             = self::load_fallback_locations_raw();
            $location_id           = self::generate_fallback_location_id( $locations );
            $locations[ $location_id ] = array_merge(
                $location_data,
                array(
                    'id'               => $location_id,
                    'hours_weekday'    => isset( $data['hours_weekday'] ) ? sanitize_text_field( $data['hours_weekday'] ) : '',
                    'hours_weekend'    => isset( $data['hours_weekend'] ) ? sanitize_text_field( $data['hours_weekend'] ) : '',
                    'waitlist_enabled' => ! empty( $data['waitlist_enabled'] ),
                    'created_at'       => current_time( 'mysql', true ),
                    'updated_at'       => current_time( 'mysql', true ),
                )
            );

            self::save_fallback_locations( $locations );
            self::save_location_meta( $location_id, $data );
            self::reset_caches();

            return self::build_location_from_array( $locations[ $location_id ] );
        }

        /**
         * Update an existing location.
         *
         * @param int   $location_id Location identifier.
         * @param array $data        Updated data.
         *
         * @return RB_Location|null
         */
        public static function update_location( $location_id, $data ) {
            global $wpdb;

            $location_id = absint( $location_id );

            if ( $location_id <= 0 ) {
                return null;
            }

            $table = self::get_table_name();

            $existing_location = self::get_location( $location_id );
            $existing_capacity  = $existing_location && isset( $existing_location->capacity ) ? (int) $existing_location->capacity : 0;

            $location_data = array(
                'name'     => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
                'address'  => isset( $data['address'] ) ? sanitize_text_field( $data['address'] ) : '',
                'phone'    => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
                'email'    => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
                'capacity' => isset( $data['capacity'] ) ? max( 0, (int) $data['capacity'] ) : $existing_capacity,
                'status'   => self::sanitize_status( isset( $data['status'] ) ? $data['status'] : 'active' ),
            );

            if ( self::table_exists( $table ) ) {
                $update = array_merge(
                    $location_data,
                    array(
                        'updated_at' => current_time( 'mysql', true ),
                    )
                );

                $result = $wpdb->update(
                    $table,
                    $update,
                    array( 'id' => $location_id ),
                    array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' ),
                    array( '%d' )
                );

                if ( false === $result ) {
                    return null;
                }

                self::save_location_meta( $location_id, $data );
                self::reset_caches();

                return self::get_location( $location_id );
            }

            $locations = self::load_fallback_locations_raw();

            if ( ! isset( $locations[ $location_id ] ) ) {
                return null;
            }

            $locations[ $location_id ] = array_merge(
                $locations[ $location_id ],
                $location_data,
                array(
                    'hours_weekday'    => isset( $data['hours_weekday'] ) ? sanitize_text_field( $data['hours_weekday'] ) : ( $locations[ $location_id ]['hours_weekday'] ?? '' ),
                    'hours_weekend'    => isset( $data['hours_weekend'] ) ? sanitize_text_field( $data['hours_weekend'] ) : ( $locations[ $location_id ]['hours_weekend'] ?? '' ),
                    'waitlist_enabled' => isset( $data['waitlist_enabled'] ) ? (bool) $data['waitlist_enabled'] : ( $locations[ $location_id ]['waitlist_enabled'] ?? false ),
                    'updated_at'       => current_time( 'mysql', true ),
                )
            );

            self::save_fallback_locations( $locations );
            self::save_location_meta( $location_id, $data );
            self::reset_caches();

            return self::build_location_from_array( $locations[ $location_id ] );
        }

        /**
         * Delete a location and its related metadata.
         *
         * @param int $location_id Location identifier.
         *
         * @return bool
         */
        public static function delete_location( $location_id ) {
            global $wpdb;

            $location_id = absint( $location_id );

            if ( $location_id <= 0 ) {
                return false;
            }

            $table = self::get_table_name();

            if ( self::table_exists( $table ) ) {
                $deleted = $wpdb->delete( $table, array( 'id' => $location_id ), array( '%d' ) );

                if ( false === $deleted ) {
                    return false;
                }
            } else {
                $locations = self::load_fallback_locations_raw();

                if ( isset( $locations[ $location_id ] ) ) {
                    unset( $locations[ $location_id ] );
                    self::save_fallback_locations( $locations );
                }
            }

            self::delete_location_meta( $location_id );

            if ( class_exists( 'RB_Table' ) ) {
                if ( method_exists( 'RB_Table', 'delete_tables_by_location' ) ) {
                    RB_Table::delete_tables_by_location( $location_id );
                }
            }

            self::reset_caches();

            return true;
        }

        /**
         * Provide default fallback locations.
         *
         * @return array
         */
        protected static function get_default_locations() {
            $defaults = self::default_fallback_locations();
            $locations = array();

            foreach ( $defaults as $location ) {
                $locations[] = self::build_location_from_array( $location );
            }

            return $locations;
        }

        /**
         * Locate a default location by identifier.
         *
         * @param int $location_id Location identifier.
         *
         * @return RB_Location|null
         */
        protected static function get_default_location_by_id( $location_id ) {
            $defaults = self::default_fallback_locations();

            if ( isset( $defaults[ $location_id ] ) ) {
                return self::build_location_from_array( $defaults[ $location_id ] );
            }

            return null;
        }

        /**
         * Convert stored array into RB_Location instance.
         *
         * @param array $data Raw data array.
         *
         * @return RB_Location
         */
        protected static function build_location_from_array( $data ) {
            $location              = new self( $data['id'] ?? 0, $data['name'] ?? '' );
            $location->address     = isset( $data['address'] ) ? $data['address'] : '';
            $location->phone       = isset( $data['phone'] ) ? $data['phone'] : '';
            $location->email       = isset( $data['email'] ) ? $data['email'] : '';
            $location->capacity    = isset( $data['capacity'] ) ? (int) $data['capacity'] : 0;
            $location->status      = isset( $data['status'] ) ? $data['status'] : 'active';
            $location->hours_weekday   = isset( $data['hours_weekday'] ) ? $data['hours_weekday'] : '';
            $location->hours_weekend   = isset( $data['hours_weekend'] ) ? $data['hours_weekend'] : '';
            $location->waitlist_enabled = ! empty( $data['waitlist_enabled'] );

            return self::hydrate_location( $location );
        }

        /**
         * Ensure metadata is merged into the location object.
         *
         * @param RB_Location $location Location instance.
         *
         * @return RB_Location
         */
        protected static function hydrate_location( RB_Location $location ) {
            $meta = self::load_location_meta();
            $id   = $location->id;

            if ( isset( $meta[ $id ] ) && is_array( $meta[ $id ] ) ) {
                $location->hours_weekday    = isset( $meta[ $id ]['hours_weekday'] ) ? $meta[ $id ]['hours_weekday'] : $location->hours_weekday;
                $location->hours_weekend    = isset( $meta[ $id ]['hours_weekend'] ) ? $meta[ $id ]['hours_weekend'] : $location->hours_weekend;
                $location->waitlist_enabled = isset( $meta[ $id ]['waitlist_enabled'] ) ? (bool) $meta[ $id ]['waitlist_enabled'] : $location->waitlist_enabled;
            } else {
                $location->waitlist_enabled = (bool) $location->waitlist_enabled;
            }

            return $location;
        }

        /**
         * Filter locations when using fallback storage.
         *
         * @param array $locations Location objects.
         * @param array $args      Filters.
         *
         * @return array
         */
        protected static function filter_locations( $locations, $args ) {
            $status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';
            $search = isset( $args['search'] ) ? strtolower( wp_strip_all_tags( $args['search'] ) ) : '';

            if ( '' === $status && '' === $search ) {
                return $locations;
            }

            $filtered = array();

            foreach ( $locations as $location ) {
                if ( $status && sanitize_key( $location->status ) !== $status ) {
                    continue;
                }

                if ( $search ) {
                    $haystack = strtolower(
                        implode(
                            ' ',
                            array(
                                $location->name,
                                $location->address,
                                $location->phone,
                                $location->email
                            )
                        )
                    );

                    if ( false === strpos( $haystack, $search ) ) {
                        continue;
                    }
                }

                $filtered[] = $location;
            }

            return $filtered;
        }

        /**
         * Load fallback locations stored in WordPress options.
         *
         * @return array
         */
        protected static function load_fallback_locations_raw() {
            if ( null !== self::$fallback_cache ) {
                return self::$fallback_cache;
            }

            $stored = get_option( self::FALLBACK_OPTION_KEY, array() );

            if ( empty( $stored ) || ! is_array( $stored ) ) {
                $stored = self::default_fallback_locations();
                update_option( self::FALLBACK_OPTION_KEY, $stored, false );
            }

            self::$fallback_cache = $stored;

            return self::$fallback_cache;
        }

        /**
         * Persist fallback locations.
         *
         * @param array $locations Locations array keyed by identifier.
         */
        protected static function save_fallback_locations( $locations ) {
            self::$fallback_cache = $locations;
            update_option( self::FALLBACK_OPTION_KEY, $locations, false );
        }

        /**
         * Retrieve fallback locations as RB_Location objects.
         *
         * @return array
         */
        protected static function get_fallback_locations() {
            $raw       = self::load_fallback_locations_raw();
            $locations = array();

            foreach ( $raw as $location ) {
                $locations[] = self::build_location_from_array( $location );
            }

            return $locations;
        }

        /**
         * Provide the default fallback dataset.
         *
         * @return array
         */
        protected static function default_fallback_locations() {
            $now = current_time( 'mysql', true );

            return array(
                1 => array(
                    'id'               => 1,
                    'name'             => __( 'Main Dining Room', 'restaurant-booking' ),
                    'address'          => __( '123 Market Street, Downtown', 'restaurant-booking' ),
                    'phone'            => '+1 (555) 010-3700',
                    'email'            => 'main@modernrestaurant.example',
                    'capacity'         => 64,
                    'status'           => 'active',
                    'hours_weekday'    => '09:00 - 22:00',
                    'hours_weekend'    => '10:00 - 23:00',
                    'waitlist_enabled' => true,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ),
                2 => array(
                    'id'               => 2,
                    'name'             => __( 'Rooftop Terrace', 'restaurant-booking' ),
                    'address'          => __( '200 Skyline Avenue, Level 12', 'restaurant-booking' ),
                    'phone'            => '+1 (555) 010-3705',
                    'email'            => 'terrace@modernrestaurant.example',
                    'capacity'         => 48,
                    'status'           => 'active',
                    'hours_weekday'    => '16:00 - 23:00',
                    'hours_weekend'    => '16:00 - 00:00',
                    'waitlist_enabled' => true,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ),
                3 => array(
                    'id'               => 3,
                    'name'             => __( 'Private Dining Loft', 'restaurant-booking' ),
                    'address'          => __( '45 Artisan Way, Suite 4B', 'restaurant-booking' ),
                    'phone'            => '+1 (555) 010-3710',
                    'email'            => 'events@modernrestaurant.example',
                    'capacity'         => 24,
                    'status'           => 'active',
                    'hours_weekday'    => '11:00 - 21:00',
                    'hours_weekend'    => '11:00 - 22:00',
                    'waitlist_enabled' => false,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ),
            );
        }

        /**
         * Generate a new fallback location identifier.
         *
         * @param array $locations Current fallback locations.
         *
         * @return int
         */
        protected static function generate_fallback_location_id( $locations ) {
            if ( empty( $locations ) ) {
                return 1;
            }

            $ids = array_map( 'intval', array_keys( $locations ) );

            return max( $ids ) + 1;
        }

        /**
         * Load stored location meta.
         *
         * @return array
         */
        protected static function load_location_meta() {
            if ( null !== self::$meta_cache ) {
                return self::$meta_cache;
            }

            $stored = get_option( self::META_OPTION_KEY, array() );

            if ( ! is_array( $stored ) ) {
                $stored = array();
            }

            self::$meta_cache = $stored;

            return self::$meta_cache;
        }

        /**
         * Persist location metadata.
         *
         * @param int   $location_id Location identifier.
         * @param array $data        Metadata payload.
         */
        protected static function save_location_meta( $location_id, $data ) {
            $location_id = absint( $location_id );

            if ( $location_id <= 0 ) {
                return;
            }

            $meta = self::load_location_meta();

            $meta[ $location_id ] = array(
                'hours_weekday'    => isset( $data['hours_weekday'] ) ? sanitize_text_field( $data['hours_weekday'] ) : ( $meta[ $location_id ]['hours_weekday'] ?? '' ),
                'hours_weekend'    => isset( $data['hours_weekend'] ) ? sanitize_text_field( $data['hours_weekend'] ) : ( $meta[ $location_id ]['hours_weekend'] ?? '' ),
                'waitlist_enabled' => isset( $data['waitlist_enabled'] ) ? (bool) $data['waitlist_enabled'] : ( $meta[ $location_id ]['waitlist_enabled'] ?? false ),
            );

            self::$meta_cache = $meta;
            update_option( self::META_OPTION_KEY, $meta, false );
        }

        /**
         * Remove stored metadata for a location.
         *
         * @param int $location_id Location identifier.
         */
        protected static function delete_location_meta( $location_id ) {
            $location_id = absint( $location_id );

            if ( $location_id <= 0 ) {
                return;
            }

            $meta = self::load_location_meta();

            if ( isset( $meta[ $location_id ] ) ) {
                unset( $meta[ $location_id ] );
                self::$meta_cache = $meta;
                update_option( self::META_OPTION_KEY, $meta, false );
            }
        }

        /**
         * Reset cached datasets.
         */
        protected static function reset_caches() {
            self::$fallback_cache = null;
            self::$meta_cache     = null;
        }

        /**
         * Sanitize status values.
         *
         * @param string $status Raw status.
         *
         * @return string
         */
        protected static function sanitize_status( $status ) {
            $status = sanitize_key( $status );

            if ( '' === $status ) {
                return 'active';
            }

            return $status;
        }

        /**
         * Return qualified table name.
         *
         * @return string
         */
        protected static function get_table_name() {
            global $wpdb;

            return $wpdb->prefix . 'rb_locations';
        }

        /**
         * Check if the locations table exists.
         *
         * @param string $table Table name.
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
         * Prepare SQL with optional parameters.
         *
         * @param string $sql    SQL statement.
         * @param array  $params Optional parameters.
         *
         * @return string
         */
        protected static function prepare( $sql, $params = array() ) {
            global $wpdb;

            if ( empty( $params ) ) {
                return $sql;
            }

            return $wpdb->prepare( $sql, $params );
        }
    }
}
