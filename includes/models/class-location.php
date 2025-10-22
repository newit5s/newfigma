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
         * Fetch all locations from the database.
         *
         * @param array $args Query arguments.
         *
         * @return array
         */
        public static function get_all_locations( $args = array() ) {
            global $wpdb;

            $table = self::get_table_name();

            if ( ! self::table_exists( $table ) ) {
                return self::get_default_locations();
            }

            $defaults = array(
                'status' => '',
                'search' => '',
            );

            $args = wp_parse_args( $args, $defaults );

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
                    $location            = new self( $record->id, $record->name );
                    $location->address   = isset( $record->address ) ? $record->address : '';
                    $location->phone     = isset( $record->phone ) ? $record->phone : '';
                    $location->email     = isset( $record->email ) ? $record->email : '';
                    $location->capacity  = isset( $record->capacity ) ? (int) $record->capacity : 0;
                    $location->status    = isset( $record->status ) ? $record->status : 'active';
                    $locations[]         = $location;
                }
            }

            if ( empty( $locations ) ) {
                return self::get_default_locations();
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

            $table = self::get_table_name();

            if ( ! self::table_exists( $table ) ) {
                return self::get_default_location_by_id( $location_id );
            }

            $sql = self::prepare(
                'SELECT id, name, address, phone, email, capacity, status FROM ' . $table . ' WHERE id = %d',
                array( absint( $location_id ) )
            );

            $record = $wpdb->get_row( $sql );

            if ( ! $record ) {
                return self::get_default_location_by_id( $location_id );
            }

            $location           = new self( $record->id, $record->name );
            $location->address  = isset( $record->address ) ? $record->address : '';
            $location->phone    = isset( $record->phone ) ? $record->phone : '';
            $location->email    = isset( $record->email ) ? $record->email : '';
            $location->capacity = isset( $record->capacity ) ? (int) $record->capacity : 0;
            $location->status   = isset( $record->status ) ? $record->status : 'active';

            return $location;
        }

        /**
         * Provide default fallback locations.
         *
         * @return array
         */
        protected static function get_default_locations() {
            $main = new self( 1, __( 'Main Dining Room', 'restaurant-booking' ) );
            $main->address  = __( '123 Market Street, Downtown', 'restaurant-booking' );
            $main->phone    = '+1 (555) 010-3700';
            $main->email    = 'main@modernrestaurant.example';
            $main->capacity = 64;
            $main->status   = 'active';

            $terrace = new self( 2, __( 'Rooftop Terrace', 'restaurant-booking' ) );
            $terrace->address  = __( '200 Skyline Avenue, Level 12', 'restaurant-booking' );
            $terrace->phone    = '+1 (555) 010-3705';
            $terrace->email    = 'terrace@modernrestaurant.example';
            $terrace->capacity = 48;
            $terrace->status   = 'active';

            $loft = new self( 3, __( 'Private Dining Loft', 'restaurant-booking' ) );
            $loft->address  = __( '45 Artisan Way, Suite 4B', 'restaurant-booking' );
            $loft->phone    = '+1 (555) 010-3710';
            $loft->email    = 'events@modernrestaurant.example';
            $loft->capacity = 24;
            $loft->status   = 'active';

            return array( $main, $terrace, $loft );
        }

        /**
         * Locate a default location by identifier.
         *
         * @param int $location_id Location identifier.
         *
         * @return RB_Location|null
         */
        protected static function get_default_location_by_id( $location_id ) {
            foreach ( self::get_default_locations() as $location ) {
                if ( (int) $location->id === (int) $location_id ) {
                    return $location;
                }
            }

            return null;
        }

        /**
         * Return the qualified table name.
         *
         * @return string
         */
        protected static function get_table_name() {
            global $wpdb;

            return $wpdb->prefix . 'rb_locations';
        }

        /**
         * Determine if the given table exists.
         *
         * @param string $table Table name.
         *
         * @return bool
         */
        protected static function table_exists( $table ) {
            global $wpdb;

            $query = self::prepare( 'SHOW TABLES LIKE %s', array( $table ) );

            $result = $wpdb->get_var( $query );

            return $result === $table;
        }

        /**
         * Prepare SQL with optional parameters.
         *
         * @param string $sql    SQL string.
         * @param array  $params Parameters.
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
