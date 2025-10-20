<?php
/**
 * Booking model implementation backed by WordPress database tables.
 *
 * The legacy plugin shipped with placeholder data models. This implementation
 * replaces those placeholders with real `wpdb` powered queries so analytics,
 * dashboards, and managers surface actual booking information.
 */

if ( ! class_exists( 'RB_Booking' ) ) {

    class RB_Booking {

        /**
         * Shared singleton instance for convenience wrappers.
         *
         * @var RB_Booking|null
         */
        protected static $instance = null;

        /**
         * Cache table existence lookups to avoid repeated SHOW TABLES queries.
         *
         * @var array<string,bool>
         */
        protected static $table_exists_cache = array();

        /**
         * Cache column existence lookups keyed by table+column.
         *
         * @var array<string,bool>
         */
        protected static $column_exists_cache = array();

        /**
         * Cached currency symbol for revenue reporting.
         *
         * @var string|null
         */
        protected static $currency_symbol = null;

        /**
         * Current booking identifier.
         *
         * @var int
         */
        protected $id = 0;

        /**
         * Loaded booking record data.
         *
         * @var array
         */
        protected $record = array();

        /**
         * Primary bookings table name.
         *
         * @var string|null
         */
        protected $table_name;

        /**
         * Tables table name (for capacity and availability calculations).
         *
         * @var string|null
         */
        protected $tables_table;

        /**
         * Locations table name.
         *
         * @var string|null
         */
        protected $locations_table;

        /**
         * Booking date column name.
         *
         * @var string
         */
        protected $date_column = 'booking_date';

        /**
         * Booking time column name.
         *
         * @var string
         */
        protected $time_column = 'booking_time';

        /**
         * Revenue column name when available.
         *
         * @var string|null
         */
        protected $total_column = null;

        /**
         * Constructor optionally loads a specific booking record.
         *
         * @param int $id Booking identifier.
         */
        public function __construct( $id = 0 ) {
            global $wpdb;

            $this->table_name      = $this->resolve_table_name( $wpdb->prefix . 'rb_bookings', $wpdb->prefix . 'restaurant_bookings' );
            $this->tables_table    = $this->resolve_table_name( $wpdb->prefix . 'rb_tables', $wpdb->prefix . 'restaurant_tables' );
            $this->locations_table = $this->resolve_table_name( $wpdb->prefix . 'rb_locations', $wpdb->prefix . 'restaurant_locations' );

            $this->date_column  = $this->detect_column( array( 'booking_date', 'booking_day', 'booking_datetime' ), 'booking_date' );
            $this->time_column  = $this->detect_column( array( 'booking_time', 'booking_datetime' ), 'booking_time' );
            $this->total_column = $this->detect_column( array( 'total_amount', 'total', 'estimated_total' ), null );

            if ( $id ) {
                $this->load( absint( $id ) );
            }
        }

        /**
         * Retrieve shared singleton instance.
         *
         * @return RB_Booking
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Resolve an available table name, preferring the provided table.
         *
         * @param string      $preferred Preferred table name.
         * @param string|null $fallback  Optional fallback table name.
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

            return $this->table_exists( $preferred ) ? $preferred : $preferred;
        }

        /**
         * Detect the first available column from a list of candidates.
         *
         * @param array       $candidates Ordered list of column names.
         * @param string|null $default    Fallback when no column exists.
         *
         * @return string|null
         */
        protected function detect_column( $candidates, $default = null ) {
            if ( empty( $this->table_name ) || ! $this->table_exists( $this->table_name ) ) {
                return $default;
            }

            foreach ( $candidates as $candidate ) {
                if ( $this->column_exists( $this->table_name, $candidate ) ) {
                    return $candidate;
                }
            }

            return $default;
        }

        /**
         * Determine if a table exists in the current database.
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
         * Determine if the given column exists on a table.
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
         * Normalise a date string to Y-m-d or return today's date.
         *
         * @param string|null $date Date input.
         *
         * @return string
         */
        protected function normalize_date( $date = null ) {
            if ( empty( $date ) ) {
                return gmdate( 'Y-m-d' );
            }

            $timestamp = strtotime( $date );
            if ( false === $timestamp ) {
                return gmdate( 'Y-m-d' );
            }

            return gmdate( 'Y-m-d', $timestamp );
        }

        /**
         * Load a booking record into the current instance.
         *
         * @param int $id Booking identifier.
         */
        protected function load( $id ) {
            $row = $this->fetch_booking_row( $id );
            if ( $row ) {
                $this->id     = (int) $id;
                $this->record = $this->normalize_booking_row( $row );
            }
        }

        /**
         * Fetch a raw booking database row.
         *
         * @param int $id Booking identifier.
         *
         * @return array|null
         */
        protected function fetch_booking_row( $id ) {
            if ( ! $this->table_exists( $this->table_name ) ) {
                return null;
            }

            global $wpdb;

            $select = 'b.*';
            $joins  = '';

            if ( $this->tables_table && $this->column_exists( $this->table_name, 'table_id' ) && $this->table_exists( $this->tables_table ) ) {
                $table_label = $this->column_exists( $this->tables_table, 'table_number' ) ? 'table_number' : 'name';
                $select     .= ', t.' . $table_label . ' AS table_label';
                $joins      .= ' LEFT JOIN ' . $this->tables_table . ' t ON t.id = b.table_id';
            }

            $sql = $wpdb->prepare( 'SELECT ' . $select . ' FROM ' . $this->table_name . ' b ' . $joins . ' WHERE b.id = %d LIMIT 1', $id );

            $row = $wpdb->get_row( $sql, ARRAY_A );
            if ( ! $row ) {
                return null;
            }

            return $row;
        }

        /**
         * Determine whether the loaded record exists.
         *
         * @return bool
         */
        public function exists() {
            return ! empty( $this->record );
        }

        /**
         * Return loaded booking data.
         *
         * @return array
         */
        public function get_data() {
            return $this->record;
        }

        /**
         * Retrieve bookings using database-backed filters.
         *
         * @param array $args Query arguments.
         *
         * @return array{
         *     bookings: array<int,object>,
         *     total: int,
         *     pages: int,
         *     page: int,
         *     per_page: int
         * }
         */
        public function get_bookings( $args = array() ) {
            $defaults = array(
                'status'      => '',
                'location_id' => 0,
                'date_from'   => '',
                'date_to'     => '',
                'search'      => '',
                'per_page'    => 20,
                'page'        => 1,
                'orderby'     => $this->date_column ? $this->date_column : 'id',
                'order'       => 'DESC',
            );

            $args = wp_parse_args( $args, $defaults );

            if ( ! $this->table_exists( $this->table_name ) ) {
                return array(
                    'bookings' => array(),
                    'total'    => 0,
                    'pages'    => 0,
                    'page'     => (int) $args['page'],
                    'per_page' => (int) $args['per_page'],
                );
            }

            global $wpdb;

            $where = array();

            if ( ! empty( $args['status'] ) && $this->column_exists( $this->table_name, 'status' ) ) {
                $where[] = $wpdb->prepare( 'b.status = %s', sanitize_text_field( $args['status'] ) );
            }

            $location_id = absint( $args['location_id'] );
            if ( $location_id && $this->column_exists( $this->table_name, 'location_id' ) ) {
                $where[] = $wpdb->prepare( 'b.location_id = %d', $location_id );
            }

            if ( ! empty( $args['date_from'] ) ) {
                $where[] = $this->build_date_clause( '>=', $args['date_from'] );
            }

            if ( ! empty( $args['date_to'] ) ) {
                $where[] = $this->build_date_clause( '<=', $args['date_to'] );
            }

            if ( ! empty( $args['search'] ) ) {
                $search_like = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
                $search_cols = array();

                if ( $this->column_exists( $this->table_name, 'customer_name' ) ) {
                    $search_cols[] = 'b.customer_name LIKE %s';
                }

                if ( $this->column_exists( $this->table_name, 'customer_email' ) ) {
                    $search_cols[] = 'b.customer_email LIKE %s';
                }

                if ( $this->column_exists( $this->table_name, 'customer_phone' ) ) {
                    $search_cols[] = 'b.customer_phone LIKE %s';
                }

                if ( ! empty( $search_cols ) ) {
                    $prepared = array();
                    foreach ( $search_cols as $clause ) {
                        $prepared[] = $wpdb->prepare( $clause, $search_like );
                    }
                    $where[] = '(' . implode( ' OR ', $prepared ) . ')';
                }
            }

            $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

            $orderby = $this->normalize_orderby( $args['orderby'] );
            $order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

            $count_sql = 'SELECT COUNT(*) FROM ' . $this->table_name . ' b ' . $where_sql;
            $total     = (int) $wpdb->get_var( $count_sql );

            $per_page = max( 1, (int) $args['per_page'] );
            $pages    = $per_page ? (int) ceil( $total / $per_page ) : 0;
            $page     = max( 1, (int) $args['page'] );
            if ( $pages && $page > $pages ) {
                $page = $pages;
            }
            $offset = ( $page - 1 ) * $per_page;

            $select = 'b.*';
            $joins  = '';

            if ( $this->tables_table && $this->column_exists( $this->table_name, 'table_id' ) && $this->table_exists( $this->tables_table ) ) {
                $table_label = $this->column_exists( $this->tables_table, 'table_number' ) ? 'table_number' : 'name';
                $select     .= ', t.' . $table_label . ' AS table_label';
                $joins      .= ' LEFT JOIN ' . $this->tables_table . ' t ON t.id = b.table_id';
            }

            $data_sql  = 'SELECT ' . $select . ' FROM ' . $this->table_name . ' b ' . $joins . ' ' . $where_sql;
            $data_sql .= ' ORDER BY ' . $orderby . ' ' . $order;
            $data_sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $per_page, $offset );

            $rows     = $wpdb->get_results( $data_sql, ARRAY_A );
            $bookings = array();

            if ( $rows ) {
                foreach ( $rows as $row ) {
                    $bookings[] = (object) $this->normalize_booking_row( $row );
                }
            }

            return array(
                'bookings' => $bookings,
                'total'    => $total,
                'pages'    => $pages,
                'page'     => $page,
                'per_page' => $per_page,
            );
        }

        /**
         * Normalise order by input into a safe SQL fragment.
         *
         * @param string $orderby Order by key.
         *
         * @return string
         */
        protected function normalize_orderby( $orderby ) {
            $orderby = sanitize_key( $orderby );

            $map = array(
                'id'            => 'b.id',
                'status'        => $this->column_exists( $this->table_name, 'status' ) ? 'b.status' : 'b.id',
                'party_size'    => $this->column_exists( $this->table_name, 'party_size' ) ? 'b.party_size' : 'b.id',
                'created_at'    => $this->column_exists( $this->table_name, 'created_at' ) ? 'b.created_at' : 'b.id',
                'updated_at'    => $this->column_exists( $this->table_name, 'updated_at' ) ? 'b.updated_at' : 'b.id',
                'booking_date'  => $this->date_column && 'booking_datetime' !== $this->date_column ? 'b.' . $this->date_column : 'DATE(b.' . $this->date_column . ')',
                'booking_time'  => $this->time_column && 'booking_datetime' !== $this->time_column ? 'b.' . $this->time_column : 'TIME(b.' . $this->time_column . ')',
            );

            if ( isset( $map[ $orderby ] ) ) {
                return $map[ $orderby ];
            }

            return isset( $map['created_at'] ) ? $map['created_at'] : 'b.id';
        }

        /**
         * Build a date comparison clause accounting for schema differences.
         *
         * @param string $operator SQL comparison operator.
         * @param string $value    Date value.
         *
         * @return string
         */
        protected function build_date_clause( $operator, $value ) {
            global $wpdb;

            $normalized = $this->normalize_date( $value );

            if ( $this->date_column && 'booking_datetime' === $this->date_column ) {
                return $wpdb->prepare( 'DATE(b.' . $this->date_column . ') ' . $operator . ' %s', $normalized );
            }

            return $wpdb->prepare( 'b.' . $this->date_column . ' ' . $operator . ' %s', $normalized );
        }

        /**
         * Normalise a raw booking row into a predictable array structure.
         *
         * @param array $row Raw booking row.
         *
         * @return array
         */
        protected function normalize_booking_row( array $row ) {
            $defaults = array(
                'id'               => 0,
                'customer_name'    => '',
                'customer_email'   => '',
                'customer_phone'   => '',
                'booking_date'     => null,
                'booking_time'     => null,
                'party_size'       => 0,
                'table_id'         => 0,
                'location_id'      => 0,
                'status'           => '',
                'special_requests' => '',
                'total_amount'     => 0.0,
                'created_at'       => null,
                'updated_at'       => null,
            );

            $data = array_merge( $defaults, $row );

            if ( isset( $row['table_label'] ) && ! isset( $row['table_number'] ) ) {
                $data['table_number'] = $row['table_label'];
            }

            if ( $this->date_column && 'booking_datetime' === $this->date_column && isset( $row[ $this->date_column ] ) ) {
                $timestamp = strtotime( $row[ $this->date_column ] );
                if ( ! isset( $row['booking_date'] ) ) {
                    $data['booking_date'] = $timestamp ? gmdate( 'Y-m-d', $timestamp ) : null;
                }
                if ( ! $this->column_exists( $this->table_name, 'booking_time' ) || empty( $row['booking_time'] ) ) {
                    $data['booking_time'] = $timestamp ? gmdate( 'H:i:s', $timestamp ) : null;
                }
            }

            if ( $this->total_column && isset( $row[ $this->total_column ] ) ) {
                $data['total_amount'] = (float) $row[ $this->total_column ];
            }

            $data['id']          = (int) $data['id'];
            $data['party_size']  = isset( $data['party_size'] ) ? (int) $data['party_size'] : 0;
            $data['table_id']    = isset( $data['table_id'] ) ? (int) $data['table_id'] : 0;
            $data['location_id'] = isset( $data['location_id'] ) ? (int) $data['location_id'] : 0;

            return $data;
        }

        /**
         * Retrieve a single booking as an object.
         *
         * @param int $booking_id Booking identifier.
         *
         * @return object|false
         */
        public function get_booking( $booking_id ) {
            $row = $this->fetch_booking_row( absint( $booking_id ) );
            if ( ! $row ) {
                return false;
            }

            return (object) $this->normalize_booking_row( $row );
        }

        /**
         * Retrieve bookings scheduled for the provided date.
         *
         * @param int|null $location_id Location identifier.
         * @param string   $date        Date string.
         *
         * @return array<int,object>
         */
        public function get_todays_bookings( $location_id = null, $date = null ) {
            $date = $this->normalize_date( $date );
            $args = array(
                'date_from'   => $date,
                'date_to'     => $date,
                'per_page'    => 200,
                'page'        => 1,
                'orderby'     => 'booking_time',
                'order'       => 'ASC',
                'location_id' => absint( $location_id ),
            );

            $results = $this->get_bookings( $args );

            return $results['bookings'];
        }

        /**
         * Convenience wrapper for booking lists by date.
         *
         * @param int    $location_id Location identifier.
         * @param string $date        Date string.
         *
         * @return array<int,object>
         */
        /**
         * Aggregate booking statistics for dashboards.
         *
         * @param int         $location_id Location identifier.
         * @param string|null $date        Date to analyse.
         *
         * @return array
         */
        public function get_location_stats( $location_id, $date = null ) {
            if ( ! $this->table_exists( $this->table_name ) ) {
                return $this->empty_stats();
            }

            global $wpdb;

            $location_id = absint( $location_id );
            $date        = $this->normalize_date( $date );

            $where = array( $this->build_date_clause( '=', $date ) );
            if ( $location_id && $this->column_exists( $this->table_name, 'location_id' ) ) {
                $where[] = $wpdb->prepare( 'b.location_id = %d', $location_id );
            }

            $where_sql = 'WHERE ' . implode( ' AND ', $where );

            $guest_expression = $this->column_exists( $this->table_name, 'party_size' ) ? 'COALESCE(SUM(b.party_size), 0)' : '0';
            $revenue_expression = $this->total_column ? 'COALESCE(SUM(b.' . $this->total_column . '), 0)' : '0';

            $stats_sql = 'SELECT
                COUNT(*) AS total_bookings,
                SUM(CASE WHEN b.status = "confirmed" THEN 1 ELSE 0 END) AS confirmed,
                SUM(CASE WHEN b.status = "pending" THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN b.status = "cancelled" THEN 1 ELSE 0 END) AS cancelled,
                ' . $guest_expression . ' AS total_guests,
                ' . $revenue_expression . ' AS revenue
            FROM ' . $this->table_name . ' b ' . $where_sql;

            $row = $wpdb->get_row( $stats_sql, ARRAY_A );

            if ( ! $row ) {
                $row = array();
            }

            $stats = array_merge(
                $this->empty_stats(),
                array(
                    'total_bookings' => isset( $row['total_bookings'] ) ? (int) $row['total_bookings'] : 0,
                    'confirmed'      => isset( $row['confirmed'] ) ? (int) $row['confirmed'] : 0,
                    'pending'        => isset( $row['pending'] ) ? (int) $row['pending'] : 0,
                    'cancelled'      => isset( $row['cancelled'] ) ? (int) $row['cancelled'] : 0,
                    'total_guests'   => isset( $row['total_guests'] ) ? (int) $row['total_guests'] : 0,
                    'revenue'        => isset( $row['revenue'] ) ? (float) $row['revenue'] : 0.0,
                )
            );

            $capacity           = $this->get_location_capacity( $location_id );
            $stats['occupancy_rate'] = $capacity > 0 ? round( min( 1, $stats['total_guests'] / $capacity ) * 100, 1 ) : 0.0;
            $stats['currency']       = self::get_currency_symbol();

            return $stats;
        }

        /**
         * Return the default empty stats payload.
         *
         * @return array
         */
        protected function empty_stats() {
            return array(
                'total_bookings' => 0,
                'confirmed'      => 0,
                'pending'        => 0,
                'cancelled'      => 0,
                'total_guests'   => 0,
                'revenue'        => 0.0,
                'currency'       => self::get_currency_symbol(),
                'occupancy_rate' => 0.0,
            );
        }

        /**
         * Calculate overall seating capacity for a location.
         *
         * @param int $location_id Location identifier.
         *
         * @return int
         */
        protected function get_location_capacity( $location_id ) {
            if ( ! $location_id || ! $this->tables_table || ! $this->table_exists( $this->tables_table ) ) {
                return 0;
            }

            if ( ! $this->column_exists( $this->tables_table, 'capacity' ) ) {
                return 0;
            }

            global $wpdb;

            $sql = $wpdb->prepare( 'SELECT COALESCE(SUM(capacity), 0) FROM ' . $this->tables_table . ' WHERE location_id = %d', $location_id );

            return (int) $wpdb->get_var( $sql );
        }

        /**
         * Create a new booking record.
         *
         * @param array $data Booking data.
         *
         * @return int|WP_Error
         */
        public function create_booking( $data ) {
            if ( ! $this->table_exists( $this->table_name ) ) {
                return new WP_Error( 'rb_table_missing', __( 'Booking table not installed.', 'restaurant-booking' ) );
            }

            global $wpdb;

            $payload = $this->sanitize_booking_input( $data );
            if ( is_wp_error( $payload ) ) {
                return $payload;
            }

            $payload['created_at'] = current_time( 'mysql', true );
            $payload['updated_at'] = current_time( 'mysql', true );

            $inserted = $wpdb->insert( $this->table_name, $payload );
            if ( false === $inserted ) {
                return new WP_Error( 'rb_insert_failed', __( 'Unable to create booking.', 'restaurant-booking' ) );
            }

            return (int) $wpdb->insert_id;
        }

        /**
         * Update an existing booking record.
         *
         * @param int   $booking_id Booking identifier.
         * @param array $data       Updated data.
         *
         * @return bool|WP_Error
         */
        public function update_booking( $booking_id, $data ) {
            if ( ! $this->table_exists( $this->table_name ) ) {
                return new WP_Error( 'rb_table_missing', __( 'Booking table not installed.', 'restaurant-booking' ) );
            }

            global $wpdb;

            $payload = $this->sanitize_booking_input( $data, true );
            if ( is_wp_error( $payload ) ) {
                return $payload;
            }

            $payload['updated_at'] = current_time( 'mysql', true );

            $updated = $wpdb->update( $this->table_name, $payload, array( 'id' => absint( $booking_id ) ) );
            if ( false === $updated ) {
                return new WP_Error( 'rb_update_failed', __( 'Unable to update booking.', 'restaurant-booking' ) );
            }

            return true;
        }

        /**
         * Instance helper to update booking status.
         *
         * @param string $status New status slug.
         *
         * @return bool
         */
        public function update_status( $status ) {
            if ( ! $this->id ) {
                return false;
            }

            $result = $this->update_booking( $this->id, array( 'status' => sanitize_key( $status ) ) );
            if ( is_wp_error( $result ) ) {
                return false;
            }

            $this->record = $this->normalize_booking_row( $this->fetch_booking_row( $this->id ) ?: array() );

            return true;
        }

        /**
         * Delete (soft delete) a booking record.
         *
         * @param int $booking_id Booking identifier.
         *
         * @return bool
         */
        public function delete_booking( $booking_id ) {
            if ( ! $this->table_exists( $this->table_name ) ) {
                return false;
            }

            global $wpdb;
            $booking_id = absint( $booking_id );

            if ( $this->column_exists( $this->table_name, 'status' ) && $this->column_exists( $this->table_name, 'updated_at' ) ) {
                $updated = $wpdb->update(
                    $this->table_name,
                    array(
                        'status'     => 'deleted',
                        'updated_at' => current_time( 'mysql', true ),
                    ),
                    array( 'id' => $booking_id )
                );

                return false !== $updated;
            }

            $deleted = $wpdb->delete( $this->table_name, array( 'id' => $booking_id ) );

            return false !== $deleted;
        }

        /**
         * Instance deletion helper used by controllers.
         *
         * @return bool
         */
        public function delete() {
            if ( ! $this->id ) {
                return false;
            }

            $deleted = $this->delete_booking( $this->id );
            if ( $deleted ) {
                $this->record = array();
            }

            return $deleted;
        }

        /**
         * Sanitize booking input payload before persistence.
         *
         * @param array $data       Raw data.
         * @param bool  $for_update Flag indicating update context.
         *
         * @return array|WP_Error
         */
        protected function sanitize_booking_input( $data, $for_update = false ) {
            if ( empty( $data ) || ! is_array( $data ) ) {
                return new WP_Error( 'rb_invalid_data', __( 'No booking data supplied.', 'restaurant-booking' ) );
            }

            $allowed = array();

            $map = array(
                'customer_name'    => 'sanitize_text_field',
                'customer_email'   => 'sanitize_email',
                'customer_phone'   => 'sanitize_text_field',
                'status'           => 'sanitize_key',
                'special_requests' => 'wp_kses_post',
            );

            foreach ( $map as $field => $callback ) {
                if ( isset( $data[ $field ] ) && $this->column_exists( $this->table_name, $field ) ) {
                    $allowed[ $field ] = call_user_func( $callback, $data[ $field ] );
                }
            }

            if ( isset( $data['party_size'] ) && $this->column_exists( $this->table_name, 'party_size' ) ) {
                $allowed['party_size'] = max( 1, (int) $data['party_size'] );
            }

            if ( isset( $data['table_id'] ) && $this->column_exists( $this->table_name, 'table_id' ) ) {
                $allowed['table_id'] = absint( $data['table_id'] );
            }

            if ( isset( $data['location_id'] ) && $this->column_exists( $this->table_name, 'location_id' ) ) {
                $allowed['location_id'] = absint( $data['location_id'] );
            }

            if ( isset( $data['booking_date'] ) && $this->column_exists( $this->table_name, 'booking_date' ) ) {
                $allowed['booking_date'] = $this->normalize_date( $data['booking_date'] );
            }

            if ( isset( $data['booking_time'] ) && $this->column_exists( $this->table_name, 'booking_time' ) ) {
                $allowed['booking_time'] = sanitize_text_field( $data['booking_time'] );
            }

            if ( isset( $data['booking_datetime'] ) && $this->column_exists( $this->table_name, 'booking_datetime' ) ) {
                $allowed['booking_datetime'] = gmdate( 'Y-m-d H:i:s', strtotime( $data['booking_datetime'] ) );
            }

            if ( $this->total_column && isset( $data[ $this->total_column ] ) ) {
                $allowed[ $this->total_column ] = floatval( $data[ $this->total_column ] );
            }

            if ( ! $for_update && empty( $allowed ) ) {
                return new WP_Error( 'rb_invalid_data', __( 'No valid booking fields supplied.', 'restaurant-booking' ) );
            }

            return $allowed;
        }

        /**
         * Static convenience wrapper for admin booking lists.
         *
         * @param int|string $location Location identifier.
         * @param string     $status   Status filter.
         * @param int        $page     Page number.
         *
         * @return array
         */
        public static function get_admin_bookings( $location = '', $status = '', $page = 1 ) {
            $model = self::instance();

            $results = $model->get_bookings(
                array(
                    'location_id' => absint( $location ),
                    'status'      => $status,
                    'page'        => max( 1, (int) $page ),
                )
            );

            return array(
                'items'      => $results['bookings'],
                'pagination' => array(
                    'current_page' => $results['page'],
                    'total_pages'  => $results['pages'],
                    'total_items'  => $results['total'],
                ),
            );
        }

        /**
         * Count bookings by date and location.
         *
         * @param string $date        Date string.
         * @param int    $location_id Location identifier.
         *
         * @return int
         */
        public static function count_by_date_and_location( $date, $location_id ) {
            $stats = self::instance()->get_location_stats( $location_id, $date );

            return isset( $stats['total_bookings'] ) ? (int) $stats['total_bookings'] : 0;
        }

        /**
         * Sum revenue by date and location.
         *
         * @param string $date        Date string.
         * @param int    $location_id Location identifier.
         *
         * @return float
         */
        public static function sum_revenue_by_date_and_location( $date, $location_id ) {
            $stats = self::instance()->get_location_stats( $location_id, $date );

            return isset( $stats['revenue'] ) ? (float) $stats['revenue'] : 0.0;
        }

        /**
         * Retrieve recent bookings for the portal dashboard.
         *
         * @param int $location_id Location identifier.
         * @param int $limit       Result limit.
         *
         * @return array<int,object>
         */
        public static function get_recent_for_portal( $location_id, $limit = 5 ) {
            $results = self::instance()->get_bookings(
                array(
                    'location_id' => absint( $location_id ),
                    'per_page'    => max( 1, (int) $limit ),
                    'page'        => 1,
                    'orderby'     => 'created_at',
                    'order'       => 'DESC',
                )
            );

            return $results['bookings'];
        }

        /**
         * Generic query wrapper used by legacy components.
         *
         * @param array $args Query arguments.
         *
         * @return array
         */
        public static function query( $args = array() ) {
            $results = self::instance()->get_bookings( $args );

            return array(
                'items' => $results['bookings'],
                'total' => $results['total'],
            );
        }

        /**
         * Fetch bookings for export routines.
         *
         * @param array $filters Export filters.
         *
         * @return array<int,array>
         */
        public static function get_bookings_for_export( $filters = array() ) {
            $results = self::instance()->get_bookings(
                array_merge(
                    $filters,
                    array(
                        'per_page' => 5000,
                        'page'     => 1,
                        'order'    => 'DESC',
                    )
                )
            );

            $export = array();
            foreach ( $results['bookings'] as $booking ) {
                $export[] = (array) $booking;
            }

            return $export;
        }

        /**
         * Provide a filtered booking dataset tailored for paginated listings.
         *
         * @param array  $filters   Filters.
         * @param int    $page      Page number.
         * @param int    $page_size Page size.
         * @param string $sort_by   Sort column.
         * @param string $sort_order Sort direction.
         *
         * @return array
         */
        public static function get_bookings_with_filters( $filters = array(), $page = 1, $page_size = 20, $sort_by = 'booking_date', $sort_order = 'DESC' ) {
            $filters = is_array( $filters ) ? $filters : array();

            $args = array_merge(
                $filters,
                array(
                    'page'     => max( 1, (int) $page ),
                    'per_page' => max( 1, (int) $page_size ),
                    'orderby'  => $sort_by,
                    'order'    => $sort_order,
                )
            );

            $results = self::instance()->get_bookings( $args );

            return array(
                'items'    => $results['bookings'],
                'total'    => $results['total'],
                'page'     => $results['page'],
                'pages'    => $results['pages'],
                'per_page' => $results['per_page'],
            );
        }

        /**
         * Provide calendar-style booking data grouped by day.
         *
         * @param int    $month  Month number.
         * @param int    $year   Year.
         * @param string $view   View key (month/week).
         * @param array  $filters Additional filters.
         *
         * @return array
         */
        public static function get_calendar_data( $month, $year, $view = 'month', $filters = array() ) {
            $month = max( 1, min( 12, (int) $month ) );
            $year  = max( 1970, (int) $year );

            $start = gmdate( 'Y-m-01', strtotime( sprintf( '%04d-%02d-01', $year, $month ) ) );
            $end   = gmdate( 'Y-m-t', strtotime( $start ) );

            $args = array_merge(
                $filters,
                array(
                    'date_from' => $start,
                    'date_to'   => $end,
                    'per_page'  => 5000,
                    'page'      => 1,
                    'orderby'   => 'booking_date',
                    'order'     => 'ASC',
                )
            );

            $results  = self::instance()->get_bookings( $args );
            $calendar = array();

            foreach ( $results['bookings'] as $booking ) {
                $date_key = isset( $booking->booking_date ) && $booking->booking_date ? $booking->booking_date : $start;
                if ( ! isset( $calendar[ $date_key ] ) ) {
                    $calendar[ $date_key ] = array();
                }
                $calendar[ $date_key ][] = $booking;
            }

            $days = array();
            foreach ( $calendar as $date => $items ) {
                $days[] = array(
                    'date'     => $date,
                    'bookings' => $items,
                );
            }

            return array(
                'month' => $month,
                'year'  => $year,
                'view'  => $view,
                'days'  => $days,
            );
        }

        /**
         * Static helper to expose bookings by date for other components.
         *
         * @param int    $location_id Location identifier.
         * @param string $date        Date string.
         *
         * @return array<int,object>
         */
        public static function get_bookings_by_date( $location_id, $date ) {
            return self::instance()->get_todays_bookings( $location_id, $date );
        }

        /**
         * Expose today's bookings for convenience.
         *
         * @param int    $location_id Location identifier.
         * @param string $date        Date string.
         *
         * @return array<int,object>
         */
        public static function get_todays_bookings_for_location( $location_id, $date ) {
            return self::instance()->get_todays_bookings( $location_id, $date );
        }

        /**
         * Retrieve the currency symbol used for revenue totals.
         *
         * @return string
         */
        public static function get_currency_symbol() {
            if ( null !== self::$currency_symbol ) {
                return self::$currency_symbol;
            }

            $symbol = get_option( 'restaurant_booking_currency_symbol' );
            if ( empty( $symbol ) && function_exists( 'get_woocommerce_currency_symbol' ) ) {
                $symbol = get_woocommerce_currency_symbol();
            }

            if ( empty( $symbol ) ) {
                $currency = get_option( 'restaurant_booking_currency', get_option( 'woocommerce_currency', 'USD' ) );
                $symbol   = apply_filters( 'restaurant_booking_currency_symbol', $currency, $currency );
            }

            if ( empty( $symbol ) ) {
                $symbol = '$';
            }

            self::$currency_symbol = $symbol;

            return self::$currency_symbol;
        }
    }
}
