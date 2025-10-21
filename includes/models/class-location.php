<?php
/**
 * Location model implementation powered by WordPress database tables.
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
         * Shared instance for convenience wrappers.
         *
         * @var RB_Location|null
         */
        protected static $instance = null;

        /**
         * Cache table checks to minimise SHOW TABLES calls.
         *
         * @var array<string,bool>
         */
        protected static $table_exists_cache = array();

        /**
         * Cache column checks to minimise SHOW COLUMNS calls.
         *
         * @var array<string,bool>
         */
        protected static $column_exists_cache = array();

        /**
         * Locations table name.
         *
         * @var string|null
         */
        protected $locations_table;

        /**
         * Tables table name.
         *
         * @var string|null
         */
        protected $tables_table;

        /**
         * Bookings table name.
         *
         * @var string|null
         */
        protected $bookings_table;

        /**
         * Constructor prepares table references.
         */
        public function __construct() {
            global $wpdb;

            $this->locations_table = $this->resolve_table_name( $wpdb->prefix . 'rb_locations', $wpdb->prefix . 'restaurant_locations' );
            $this->tables_table    = $this->resolve_table_name( $wpdb->prefix . 'rb_tables', $wpdb->prefix . 'restaurant_tables' );
            $this->bookings_table  = $this->resolve_table_name( $wpdb->prefix . 'rb_bookings', $wpdb->prefix . 'restaurant_bookings' );
        }

        /**
         * Retrieve shared instance.
         *
         * @return RB_Location
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Determine if a table exists.
         *
         * @param string|null $table Table name.
         *
         * @return bool
         */
        protected function table_exists( $table ) {
            if ( empty( $table ) ) {
                return false;
            }

            if ( isset( self::$table_exists_cache[ $table ] ) ) {
                return self::$table_exists_cache[ $table ];
            }

            global $wpdb;
            $result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            self::$table_exists_cache[ $table ] = ( $result === $table );

            return self::$table_exists_cache[ $table ];
        }

        /**
         * Check column existence.
         *
         * @param string|null $table  Table name.
         * @param string      $column Column name.
         *
         * @return bool
         */
        protected function column_exists( $table, $column ) {
            if ( empty( $table ) || empty( $column ) ) {
                return false;
            }

            $cache_key = $table . ':' . $column;
            if ( isset( self::$column_exists_cache[ $cache_key ] ) ) {
                return self::$column_exists_cache[ $cache_key ];
            }

            global $wpdb;
            $exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM ' . $table . ' LIKE %s', $column ) );
            self::$column_exists_cache[ $cache_key ] = $exists;

            return $exists;
        }

        /**
         * Resolve an available table name, optionally using a fallback.
         *
         * @param string      $preferred Preferred table.
         * @param string|null $fallback  Optional fallback.
         *
         * @return string|null
         */
        protected function resolve_table_name( $preferred, $fallback = null ) {
            if ( $this->table_exists( $preferred ) ) {
                return $preferred;
            }

            if ( $fallback && $this->table_exists( $fallback ) ) {
                return $fallback;
            }

            return $preferred;
        }

        /**
         * Retrieve locations with optional filtering.
         *
         * @param array $args Filters.
         *
         * @return array<int,object>
         */
        public function get_locations( $args = array() ) {
            if ( ! $this->table_exists( $this->locations_table ) ) {
                return array();
            }

            global $wpdb;

            $defaults = array(
                'status' => '',
                'search' => '',
                'order'  => 'ASC',
            );

            $args = wp_parse_args( $args, $defaults );

            $where = array();

            if ( ! empty( $args['status'] ) && $this->column_exists( $this->locations_table, 'status' ) ) {
                $where[] = $wpdb->prepare( 'l.status = %s', sanitize_text_field( $args['status'] ) );
            }

            if ( ! empty( $args['search'] ) && $this->column_exists( $this->locations_table, 'name' ) ) {
                $like = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
                $where[] = $wpdb->prepare( 'l.name LIKE %s', $like );
            }

            $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
            $order     = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
            $orderby   = $this->column_exists( $this->locations_table, 'name' ) ? 'l.name' : 'l.id';

            $sql = 'SELECT l.* FROM ' . $this->locations_table . ' l ' . $where_sql . ' ORDER BY ' . $orderby . ' ' . $order;
            $rows = $wpdb->get_results( $sql, ARRAY_A );

            $locations = array();
            foreach ( (array) $rows as $row ) {
                $locations[] = (object) array(
                    'id'       => isset( $row['id'] ) ? (int) $row['id'] : 0,
                    'name'     => isset( $row['name'] ) ? $row['name'] : '',
                    'status'   => isset( $row['status'] ) ? $row['status'] : '',
                    'address'  => isset( $row['address'] ) ? $row['address'] : '',
                    'capacity' => isset( $row['capacity'] ) ? (int) $row['capacity'] : 0,
                    'phone'    => isset( $row['phone'] ) ? $row['phone'] : '',
                    'email'    => isset( $row['email'] ) ? $row['email'] : '',
                );
            }

            return $locations;
        }

        /**
         * Static wrapper returning all locations.
         *
         * @param array $args Optional filters.
         *
         * @return array<int,object>
         */
        public static function get_all_locations( $args = array() ) {
            return self::instance()->get_locations( $args );
        }

        /**
         * Retrieve table definitions including current occupancy information.
         *
         * @param int $location_id Location identifier.
         *
         * @return array<int,array>
         */
        public function get_tables( $location_id ) {
            if ( ! $this->table_exists( $this->tables_table ) ) {
                return array();
            }

            global $wpdb;

            $location_id = absint( $location_id );
            $where       = array();

            if ( $location_id && $this->column_exists( $this->tables_table, 'location_id' ) ) {
                $where[] = $wpdb->prepare( 't.location_id = %d', $location_id );
            }

            $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
            $orderby   = $this->column_exists( $this->tables_table, 'name' ) ? 't.name' : 't.id';

            $sql   = 'SELECT t.* FROM ' . $this->tables_table . ' t ' . $where_sql . ' ORDER BY ' . $orderby . ' ASC';
            $rows  = $wpdb->get_results( $sql, ARRAY_A );
            $today = gmdate( 'Y-m-d' );

            $bookings_by_table = array();
            if ( class_exists( 'RB_Booking' ) ) {
                $booking_model = RB_Booking::instance();
                $bookings      = $booking_model->get_todays_bookings( $location_id, $today );
                foreach ( $bookings as $booking ) {
                    $table_id = isset( $booking->table_id ) ? (int) $booking->table_id : 0;
                    if ( $table_id <= 0 ) {
                        continue;
                    }
                    if ( ! isset( $bookings_by_table[ $table_id ] ) ) {
                        $bookings_by_table[ $table_id ] = array();
                    }
                    $bookings_by_table[ $table_id ][] = array(
                        'id'         => isset( $booking->id ) ? (int) $booking->id : 0,
                        'time'       => isset( $booking->booking_time ) ? $booking->booking_time : '',
                        'party_size' => isset( $booking->party_size ) ? (int) $booking->party_size : 0,
                        'status'     => isset( $booking->status ) ? $booking->status : '',
                    );
                }
            }

            $tables = array();
            foreach ( (array) $rows as $row ) {
                $table_id = isset( $row['id'] ) ? (int) $row['id'] : 0;
                $bookings = isset( $bookings_by_table[ $table_id ] ) ? $bookings_by_table[ $table_id ] : array();
                $status   = isset( $row['status'] ) ? $row['status'] : 'available';

                if ( ! empty( $bookings ) ) {
                    $status = 'occupied';
                }

                $tables[] = array(
                    'id'              => $table_id,
                    'name'            => isset( $row['name'] ) ? $row['name'] : '',
                    'capacity'        => isset( $row['capacity'] ) ? (int) $row['capacity'] : 0,
                    'status'          => $status,
                    'location_id'     => isset( $row['location_id'] ) ? (int) $row['location_id'] : $location_id,
                    'currentBookings' => $bookings,
                );
            }

            return $tables;
        }

        /**
         * Gather extended statistics for a location over a period.
         *
         * @param int    $location_id Location identifier.
         * @param string $period      Period key (7d, 30d, 90d).
         *
         * @return array
         */
        public function get_location_statistics( $location_id, $period = '30d' ) {
            $days  = $this->period_to_days( $period );
            $end   = gmdate( 'Y-m-d' );
            $start = gmdate( 'Y-m-d', strtotime( '-' . ( $days - 1 ) . ' days', strtotime( $end ) ) );

            $location_id = absint( $location_id );
            $bookings    = array();

            if ( class_exists( 'RB_Booking' ) ) {
                $bookings = RB_Booking::instance()->get_bookings(
                    array(
                        'location_id' => $location_id,
                        'date_from'   => $start,
                        'date_to'     => $end,
                        'per_page'    => 5000,
                        'page'        => 1,
                        'orderby'     => 'booking_date',
                        'order'       => 'ASC',
                    )
                );

                $bookings = isset( $bookings['bookings'] ) ? $bookings['bookings'] : array();
            }

            $total_bookings = count( $bookings );
            $total_guests   = 0;
            $revenue        = 0.0;
            $time_slots     = array();

            foreach ( $bookings as $booking ) {
                $party = isset( $booking->party_size ) ? (int) $booking->party_size : 0;
                $total_guests += $party;

                if ( isset( $booking->total_amount ) ) {
                    $revenue += (float) $booking->total_amount;
                }

                $time_key = '00:00';
                if ( isset( $booking->booking_time ) && $booking->booking_time ) {
                    $time_key = substr( $booking->booking_time, 0, 5 );
                }

                if ( ! isset( $time_slots[ $time_key ] ) ) {
                    $time_slots[ $time_key ] = 0;
                }
                $time_slots[ $time_key ]++;
            }

            arsort( $time_slots );
            $popular_times = array();
            foreach ( array_slice( $time_slots, 0, 5, true ) as $time => $count ) {
                $popular_times[] = array(
                    'time'  => $time,
                    'count' => $count,
                );
            }

            $trend = array();
            if ( class_exists( 'RB_Booking' ) ) {
                $booking_model = RB_Booking::instance();
                for ( $i = $days - 1; $i >= 0; $i-- ) {
                    $date  = gmdate( 'Y-m-d', strtotime( '-' . $i . ' days', strtotime( $end ) ) );
                    $stats = $booking_model->get_location_stats( $location_id, $date );
                    $trend[] = array(
                        'date'      => $date,
                        'occupancy' => isset( $stats['occupancy_rate'] ) ? (float) $stats['occupancy_rate'] : 0.0,
                        'bookings'  => isset( $stats['total_bookings'] ) ? (int) $stats['total_bookings'] : 0,
                    );
                }
            }

            return array(
                'total_bookings'     => $total_bookings,
                'total_guests'       => $total_guests,
                'revenue'            => $revenue,
                'average_party_size' => $total_bookings ? round( $total_guests / $total_bookings, 2 ) : 0,
                'popular_times'      => $popular_times,
                'occupancy_trend'    => $trend,
                'period'             => array(
                    'start' => $start,
                    'end'   => $end,
                ),
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
                return null;
            }

            $sql = self::prepare(
                'SELECT id, name, address, phone, email, capacity, status FROM ' . $table . ' WHERE id = %d',
                array( absint( $location_id ) )
            );

            $record = $wpdb->get_row( $sql );

            if ( ! $record ) {
                return null;
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
            $default = new self( 1, __( 'Main Dining Room', 'restaurant-booking' ) );
            $default->address = __( '123 Example Street', 'restaurant-booking' );
            $default->status  = 'active';

            return array( $default );
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

        /**
         * Translate a period key to number of days.
         *
         * @param string $period Period key.
         *
         * @return int
         */
        protected function period_to_days( $period ) {
            $map = array(
                '7d'  => 7,
                '30d' => 30,
                '90d' => 90,
            );

            return isset( $map[ $period ] ) ? (int) $map[ $period ] : 30;
        }
    }
}
