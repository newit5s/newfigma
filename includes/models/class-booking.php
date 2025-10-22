<?php
/**
 * Booking model implementation powered by custom database tables.
 *
 * Provides data access helpers for bookings that are consumed by
 * the booking management UI, analytics dashboards, and WordPress
 * admin controllers.
 *
 * @package RestaurantBooking\Models
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'RB_Booking' ) ) {

    /**
     * Booking data model.
     */
    class RB_Booking {

        /**
         * Booking identifier.
         *
         * @var int
         */
        protected $id = 0;

        /**
         * Cached booking record.
         *
         * @var array|null
         */
        protected $data = null;

        /**
         * WordPress database instance.
         *
         * @var wpdb
         */
        protected $wpdb;

        /**
         * Fully qualified bookings table name.
         *
         * @var string
         */
        protected $table;

        /**
         * Related tables.
         *
         * @var array
         */
        protected $related_tables = array();

        /**
         * Cached list of table columns.
         *
         * @var array|null
         */
        protected $columns = null;

        /**
         * Notification service instance cache.
         *
         * @var RB_Notification_Service|null
         */
        protected $notification_service = null;

        /**
         * Singleton instance cache.
         *
         * @var self|null
         */
        protected static $instance = null;

        /**
         * Constructor.
         *
         * @param int $id Optional booking identifier.
         */
        public function __construct( $id = 0 ) {
            global $wpdb;

            $this->wpdb  = $wpdb;
            $this->table = $wpdb->prefix . 'rb_bookings';

            $this->related_tables = array(
                'customers' => $wpdb->prefix . 'rb_customers',
                'locations' => $wpdb->prefix . 'rb_locations',
                'tables'    => $wpdb->prefix . 'rb_tables',
            );

            $this->id = absint( $id );

            if ( $this->id > 0 ) {
                $this->load();
            }
        }

        /**
         * Retrieve singleton instance.
         *
         * @return self
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Determine whether the booking exists.
         *
         * @return bool
         */
        public function exists() {
            $this->load();

            return ! empty( $this->data );
        }

        /**
         * Return the hydrated booking record as an associative array.
         *
         * @return array
         */
        public function get_data() {
            $this->load();

            return is_array( $this->data ) ? $this->data : array();
        }

        /**
         * Retrieve the current booking status.
         *
         * @return string
         */
        public function get_status() {
            $data = $this->get_data();

            return isset( $data['status'] ) ? (string) $data['status'] : '';
        }

        /**
         * Retrieve the customer's email address.
         *
         * @return string
         */
        public function get_customer_email() {
            $data = $this->get_data();

            return isset( $data['customer_email'] ) ? (string) $data['customer_email'] : '';
        }

        /**
         * Retrieve the customer's display name.
         *
         * @return string
         */
        public function get_customer_name() {
            $data = $this->get_data();

            return isset( $data['customer_name'] ) ? (string) $data['customer_name'] : '';
        }

        /**
         * Trigger confirmation email delivery.
         *
         * @return bool
         */
        public function send_confirmation_email() {
            $service = $this->get_notification_service();

            if ( ! $service ) {
                return false;
            }

            return $service->send_booking_confirmation( $this->get_data() );
        }

        /**
         * Trigger cancellation email delivery.
         *
         * @return bool
         */
        public function send_cancellation_email() {
            $service = $this->get_notification_service();

            if ( ! $service ) {
                return false;
            }

            return $service->send_booking_cancellation( $this->get_data() );
        }

        /**
         * Trigger reminder email delivery.
         *
         * @return bool
         */
        public function send_reminder_email() {
            $service = $this->get_notification_service();

            if ( ! $service ) {
                return false;
            }

            return $service->send_booking_reminder( $this->get_data() );
        }

        /**
         * Update the booking status.
         *
         * @param string $status New status identifier.
         *
         * @return bool
         */
        public function update_status( $status ) {
            if ( ! $this->id ) {
                return false;
            }

            $previous_status = $this->get_status();

            if ( ! $this->bookings_table_exists() ) {
                $repository = $this->get_fallback_repository();

                if ( $repository && $repository->update_status( $this->id, $status ) ) {
                    $this->data = $repository->get_booking( $this->id );

                    $this->trigger_status_hooks( $previous_status, $status );

                    return true;
                }

                return false;
            }

            $status = sanitize_key( $status );

            $data   = array( 'status' => $status );
            $format = array( '%s' );

            if ( $this->has_column( 'updated_at' ) ) {
                $data['updated_at'] = current_time( 'mysql', true );
                $format[]           = '%s';
            }

            $updated = $this->wpdb->update(
                $this->table,
                $data,
                array( 'id' => $this->id ),
                $format,
                array( '%d' )
            );

            if ( false !== $updated ) {
                $this->load( true );
                $this->trigger_status_hooks( $previous_status, $status );
            }

            return false !== $updated;
        }

        /**
         * Trigger hooks after a status change.
         *
         * @param string $previous_status Previous status value.
         * @param string $new_status      New status value.
         */
        protected function trigger_status_hooks( $previous_status, $new_status ) {
            $previous_status = sanitize_key( $previous_status );
            $new_status      = sanitize_key( $new_status );

            if ( '' === $new_status || $previous_status === $new_status ) {
                return;
            }

            $data = $this->get_data();

            /**
             * Fires when a booking status changes.
             *
             * @param int    $booking_id      Booking identifier.
             * @param string $previous_status Previous status slug.
             * @param string $new_status      New status slug.
             * @param array  $booking         Booking data.
             */
            do_action( 'rb_booking_status_changed', $this->id, $previous_status, $new_status, $data );

            /**
             * Fires when a booking is moved into a specific status.
             *
             * @param int    $booking_id      Booking identifier.
             * @param string $previous_status Previous status slug.
             * @param string $new_status      New status slug.
             * @param array  $booking         Booking data.
             */
            do_action( 'rb_booking_status_changed_' . $new_status, $this->id, $previous_status, $new_status, $data );
        }

        /**
         * Delete the booking record.
         *
         * @return bool
         */
        public function delete() {
            if ( ! $this->id ) {
                return false;
            }

            if ( ! $this->bookings_table_exists() ) {
                $repository = $this->get_fallback_repository();

                if ( $repository ) {
                    return $repository->delete_booking( $this->id );
                }

                return false;
            }

            $deleted = $this->wpdb->delete(
                $this->table,
                array( 'id' => $this->id ),
                array( '%d' )
            );

            return false !== $deleted;
        }

        /**
         * Fetch paginated booking data for the WordPress admin table.
         *
         * @param string $location Location filter.
         * @param string $status   Status filter.
         * @param int    $page     Page number.
         * @param array  $args     Additional query arguments.
         *
         * @return array
         */
        public static function get_admin_bookings( $location = '', $status = '', $page = 1, $args = array() ) {
            $model = new self();

            $filters = array_merge(
                array(
                    'location_id'     => absint( $location ),
                    'page'            => max( 1, (int) $page ),
                    'per_page'        => 20,
                    'include_summary' => true,
                    'source'          => '',
                ),
                (array) $args
            );

            $filters['location_id'] = absint( $location );
            $filters['page']        = max( 1, (int) $filters['page'] );
            $filters['per_page']    = isset( $filters['per_page'] ) ? max( 1, (int) $filters['per_page'] ) : 20;

            if ( '' !== $status ) {
                $filters['status'] = sanitize_key( $status );
            } elseif ( isset( $filters['status'] ) ) {
                $filters['status'] = sanitize_key( $filters['status'] );
            } else {
                $filters['status'] = '';
            }

            if ( isset( $filters['source'] ) && '' !== $filters['source'] ) {
                $filters['source'] = sanitize_key( $filters['source'] );
            } else {
                $filters['source'] = '';
            }

            $results = $model->query_bookings( $filters );

            return array(
                'items'      => $results['rows'],
                'pagination' => array(
                    'current_page' => $results['page'],
                    'total_pages'  => $results['total_pages'],
                    'total_items'  => $results['total'],
                    'per_page'     => $results['per_page'],
                ),
                'summary'    => isset( $results['summary'] ) ? $results['summary'] : array(),
            );
        }

        /**
         * Retrieve bookings using the legacy public signature.
         *
         * Provides backwards compatibility for code that expected the
         * previous `get_bookings` method while returning the richer
         * payload required by the modern admin screens.
         *
         * @param string|int $location Location identifier or slug.
         * @param string     $status   Optional status filter.
         * @param int        $page     Page number.
         * @param array      $args     Additional query arguments.
         *
         * @return array
         */
        public static function get_bookings( $location = '', $status = '', $page = 1, $args = array() ) {
            $results = self::get_admin_bookings( $location, $status, $page, $args );

            $pagination = isset( $results['pagination'] ) && is_array( $results['pagination'] )
                ? $results['pagination']
                : array();

            $default_per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 20;
            $default_per_page = max( 1, $default_per_page );

            $current_page = isset( $pagination['current_page'] ) ? (int) $pagination['current_page'] : (int) $page;
            $total_pages  = isset( $pagination['total_pages'] ) ? (int) $pagination['total_pages'] : 1;
            $total_items  = isset( $pagination['total_items'] ) ? (int) $pagination['total_items'] : 0;
            $per_page     = isset( $pagination['per_page'] ) ? (int) $pagination['per_page'] : $default_per_page;

            $current_page = max( 1, $current_page );
            $total_pages  = max( 1, $total_pages );
            $total_items  = max( 0, $total_items );
            $per_page     = max( 1, $per_page );

            $results['pagination'] = array(
                'current_page' => $current_page,
                'total_pages'  => $total_pages,
                'total_items'  => $total_items,
                'per_page'     => $per_page,
            );

            $results['total']       = $total_items;
            $results['total_pages'] = $total_pages;
            $results['page']        = $current_page;
            $results['per_page']    = $per_page;

            if ( ! isset( $results['summary'] ) || ! is_array( $results['summary'] ) ) {
                $results['summary'] = array();
            }

            if ( ! isset( $results['items'] ) || ! is_array( $results['items'] ) ) {
                $results['items'] = array();
            }

            return $results;
        }

        /**
         * Create a booking record.
         *
         * @param array $data Booking payload.
         *
         * @return array
         */
        public static function create_booking( $data ) {
            $model = new self();

            if ( ! $model->bookings_table_exists() ) {
                $repository = $model->get_fallback_repository();

                if ( $repository ) {
                    $payload = $model->prepare_fallback_booking_payload( $data );
                    $booking = $repository->add_booking( $payload );

                    return $booking ? $model->map_booking_row_from_array( $booking ) : array();
                }

                return array();
            }

            list( $insert, $formats ) = $model->prepare_insert_payload( $data );

            if ( empty( $insert ) ) {
                return array();
            }

            $result = $model->wpdb->insert( $model->table, $insert, $formats );

            if ( false === $result ) {
                return array();
            }

            $booking_id = (int) $model->wpdb->insert_id;
            $record     = new self( $booking_id );

            return $record->get_data();
        }

        /**
         * Count bookings for a given date and location.
         *
         * @param string $date        Target date (Y-m-d).
         * @param int    $location_id Location identifier.
         *
         * @return int
         */
        public static function count_by_date_and_location( $date, $location_id ) {
            $model = new self();

            if ( ! $model->bookings_table_exists() ) {
                $repository = $model->get_fallback_repository();

                return $repository ? $repository->count_by_date_and_location( $date, $location_id ) : 0;
            }

            $date        = $model->normalize_date( $date );
            $location_id = absint( $location_id );

            $where   = array();
            $params  = array();
            $where[] = $model->get_date_expression() . ' = %s';
            $params[] = $date;

            if ( $location_id > 0 ) {
                $where[]  = 'b.location_id = %d';
                $params[] = $location_id;
            }

            $sql = 'SELECT COUNT(*) FROM ' . $model->table . ' b WHERE ' . implode( ' AND ', $where );
            $sql = $model->prepare( $sql, $params );

            return (int) $model->wpdb->get_var( $sql );
        }

        /**
         * Sum booking revenue for a given date and location.
         *
         * @param string $date        Target date (Y-m-d).
         * @param int    $location_id Location identifier.
         *
         * @return float
         */
        public static function sum_revenue_by_date_and_location( $date, $location_id ) {
            $model = new self();

            if ( ! $model->bookings_table_exists() || ! $model->has_column( 'total_amount' ) ) {
                $repository = $model->get_fallback_repository();

                if ( $repository ) {
                    $stats = $repository->get_location_stats( $location_id, $date );

                    return isset( $stats['revenue'] ) ? (float) $stats['revenue'] : 0.0;
                }

                return 0.0;
            }

            $date        = $model->normalize_date( $date );
            $location_id = absint( $location_id );

            $where   = array( $model->get_date_expression() . ' = %s' );
            $params  = array( $date );

            if ( $location_id > 0 ) {
                $where[]  = 'b.location_id = %d';
                $params[] = $location_id;
            }

            $sql = 'SELECT SUM(total_amount) FROM ' . $model->table . ' b WHERE ' . implode( ' AND ', $where );
            $sql = $model->prepare( $sql, $params );

            $value = $model->wpdb->get_var( $sql );

            return $value ? (float) $value : 0.0;
        }

        /**
         * Count bookings by status.
         *
         * @param string $status      Booking status.
         * @param int    $location_id Optional location identifier.
         *
         * @return int
         */
        public static function count_by_status( $status, $location_id = 0 ) {
            $model = new self();

            if ( ! $model->bookings_table_exists() || ! $model->has_column( 'status' ) ) {
                $repository = $model->get_fallback_repository();

                return $repository ? $repository->count_by_status( $status, $location_id ) : 0;
            }

            $status = sanitize_key( $status );

            if ( '' === $status ) {
                return 0;
            }

            $where   = array( 'b.status = %s' );
            $params  = array( $status );

            $location_id = absint( $location_id );
            if ( $location_id > 0 ) {
                $where[]  = 'b.location_id = %d';
                $params[] = $location_id;
            }

            $sql = 'SELECT COUNT(*) FROM ' . $model->table . ' b WHERE ' . implode( ' AND ', $where );
            $sql = $model->prepare( $sql, $params );

            return (int) $model->wpdb->get_var( $sql );
        }

        /**
         * Fetch recent bookings for portal dashboards.
         *
         * @param int $location_id Location identifier.
         * @param int $limit       Result limit.
         *
         * @return array
         */
        public static function get_recent_for_portal( $location_id, $limit = 5 ) {
            $model = new self();

            $args = array(
                'location_id' => absint( $location_id ),
                'per_page'    => max( 1, (int) $limit ),
                'page'        => 1,
                'sort_by'     => 'booking_datetime',
                'sort_order'  => 'desc',
            );

            $results = $model->query_bookings( $args );

            return $results['rows'];
        }

        /**
         * Run a generic booking query.
         *
         * @param array $args Query arguments.
         *
         * @return array
         */
        public function query( $args = array() ) {
            $results = $this->query_bookings( $args );

            return array(
                'items'      => $results['rows'],
                'total'      => $results['total'],
                'totalPages' => $results['total_pages'],
                'summary'    => isset( $results['summary'] ) ? $results['summary'] : array(),
            );
        }

        /**
         * Fetch bookings with filters for the management UI.
         *
         * @param array  $filters   Filter arguments.
         * @param int    $page      Page number.
         * @param int    $page_size Items per page.
         * @param string $sort_by   Sort field.
         * @param string $sort_order Sort direction.
         *
         * @return array
         */
        public static function get_bookings_with_filters( $filters, $page = 1, $page_size = 25, $sort_by = 'booking_datetime', $sort_order = 'desc' ) {
            $model = new self();

            $args = array_merge(
                (array) $filters,
                array(
                    'page'       => max( 1, (int) $page ),
                    'per_page'   => max( 1, (int) $page_size ),
                    'sort_by'    => sanitize_key( $sort_by ),
                    'sort_order' => sanitize_key( $sort_order ),
                )
            );

            $results = $model->query_bookings( $args );

            return array(
                'bookings'     => $results['rows'],
                'total_items'  => $results['total'],
                'total_pages'  => $results['total_pages'],
                'current_page' => $results['page'],
                'page_size'    => $results['per_page'],
            );
        }

        /**
         * Retrieve bookings suitable for CSV export.
         *
         * @param array $filters Filter arguments.
         *
         * @return array
         */
        public static function get_bookings_for_export( $filters ) {
            $model = new self();

            $args = array_merge(
                (array) $filters,
                array(
                    'per_page'  => apply_filters( 'rb_booking_export_limit', 1000 ),
                    'page'      => 1,
                    'no_limit'  => true,
                    'sort_by'   => 'booking_datetime',
                    'sort_order'=> 'desc',
                )
            );

            $results = $model->query_bookings( $args, true );

            return $results['rows'];
        }

        /**
         * Provide calendar view data grouped by date.
         *
         * @param int    $month   Month (1-12).
         * @param int    $year    Four-digit year.
         * @param string $view    Calendar view (month/week/day).
         * @param array  $filters Additional filters.
         *
         * @return array
         */
        public static function get_calendar_data( $month, $year, $view, $filters = array() ) {
            $model = new self();

            if ( ! $model->bookings_table_exists() ) {
                return array();
            }

            $month = max( 1, min( 12, (int) $month ) );
            $year  = max( 1970, (int) $year );

            $start = gmdate( 'Y-m-d', strtotime( sprintf( '%04d-%02d-01', $year, $month ) ) );
            $end   = gmdate( 'Y-m-d', strtotime( $start . ' +1 month -1 day' ) );

            $args = array_merge(
                (array) $filters,
                array(
                    'date_from' => isset( $filters['date_from'] ) && $filters['date_from'] ? $filters['date_from'] : $start,
                    'date_to'   => isset( $filters['date_to'] ) && $filters['date_to'] ? $filters['date_to'] : $end,
                    'per_page'  => 500,
                    'page'      => 1,
                    'no_limit'  => true,
                )
            );

            $results = $model->query_bookings( $args, true );

            $grouped = array();

            foreach ( $results['rows'] as $booking ) {
                $date_key = isset( $booking['booking_date'] ) ? $booking['booking_date'] : $start;

                if ( ! isset( $grouped[ $date_key ] ) ) {
                    $grouped[ $date_key ] = array(
                        'bookings' => array(),
                        'count'    => 0,
                    );
                }

                $grouped[ $date_key ]['count']++;
                $grouped[ $date_key ]['bookings'][] = array(
                    'id'            => $booking['id'],
                    'customer_name' => $booking['customer_name'],
                    'time'          => $booking['booking_time'],
                    'status'        => $booking['status'],
                    'party_size'    => $booking['party_size'],
                    'table_number'  => $booking['table_number'],
                );
            }

            return $grouped;
        }

        /**
         * Fetch bookings on a specific date for a location.
         *
         * @param int    $location_id Location identifier.
         * @param string $date        Target date (Y-m-d).
         *
         * @return array
         */
        public static function get_bookings_by_date( $location_id, $date ) {
            $model = new self();

            $args = array(
                'location_id' => absint( $location_id ),
                'date_from'   => $model->normalize_date( $date ),
                'date_to'     => $model->normalize_date( $date ),
                'per_page'    => 200,
                'page'        => 1,
                'no_limit'    => true,
                'sort_by'     => 'booking_datetime',
                'sort_order'  => 'asc',
            );

            $results = $model->query_bookings( $args, true );

            return $results['rows'];
        }

        /**
         * Retrieve aggregated statistics for a location on a given date.
         *
         * @param int    $location_id Location identifier.
         * @param string $date        Target date (Y-m-d).
         *
         * @return array
         */
        public function get_location_stats( $location_id, $date = null ) {
            if ( ! $this->bookings_table_exists() ) {
                $repository = $this->get_fallback_repository();

                if ( $repository ) {
                    return $repository->get_location_stats( $location_id, $date );
                }

                return $this->get_default_stats();
            }

            $location_id = absint( $location_id );
            $date        = $this->normalize_date( $date );

            $where   = array();
            $params  = array();
            $where[] = $this->get_date_expression() . ' = %s';
            $params[] = $date;

            if ( $location_id > 0 ) {
                $where[]  = 'b.location_id = %d';
                $params[] = $location_id;
            }

            $amount_expression = $this->has_column( 'total_amount' ) ? 'COALESCE(b.total_amount, 0)' : '0';
            $table_expression  = $this->has_column( 'table_id' ) ? 'b.table_id' : 'NULL';

            $sql = 'SELECT
                COUNT(*) AS total_bookings,
                SUM(CASE WHEN b.status = "confirmed" THEN 1 ELSE 0 END) AS confirmed,
                SUM(CASE WHEN b.status = "pending" THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN b.status = "cancelled" THEN 1 ELSE 0 END) AS cancelled,
                SUM(COALESCE(b.party_size, 0)) AS total_guests,
                SUM(' . $amount_expression . ') AS revenue,
                COUNT(DISTINCT CASE WHEN b.status != "cancelled" THEN ' . $table_expression . ' END) AS used_tables
            FROM ' . $this->table . ' b';

            if ( $where ) {
                $sql .= ' WHERE ' . implode( ' AND ', $where );
            }

            $sql = $this->prepare( $sql, $params );

            $row = $this->wpdb->get_row( $sql, ARRAY_A );

            if ( ! is_array( $row ) ) {
                return $this->get_default_stats();
            }

            $available_tables = 0;
            if ( class_exists( 'RB_Table' ) ) {
                $available_tables = RB_Table::count_by_location( $location_id );
            }

            $used_tables = isset( $row['used_tables'] ) ? (int) $row['used_tables'] : 0;
            $occupancy   = ( 0 < $available_tables ) ? min( 100.0, round( ( $used_tables / $available_tables ) * 100, 1 ) ) : 0.0;

            return array(
                'total_bookings'  => (int) $row['total_bookings'],
                'confirmed'       => (int) $row['confirmed'],
                'pending'         => (int) $row['pending'],
                'cancelled'       => (int) $row['cancelled'],
                'total_guests'    => (int) $row['total_guests'],
                'revenue'         => $row['revenue'] ? (float) $row['revenue'] : 0.0,
                'currency'        => apply_filters( 'rb_booking_currency', '$', $location_id ),
                'occupancy_rate'  => $occupancy,
                'available_tables'=> $available_tables,
                'used_tables'     => $used_tables,
            );
        }

        /**
         * Internal loader.
         *
         * @param bool $force_refresh Force re-fetch.
         */
        protected function load( $force_refresh = false ) {
            if ( $this->data !== null && ! $force_refresh ) {
                return;
            }

            if ( ! $this->id ) {
                $this->data = null;
                return;
            }

            if ( ! $this->bookings_table_exists() ) {
                $repository = $this->get_fallback_repository();
                $record     = $repository ? $repository->get_booking( $this->id ) : null;

                $this->data = $record ? $this->map_booking_row_from_array( $record ) : null;

                return;
            }

            $sql = $this->prepare(
                'SELECT * FROM ' . $this->table . ' WHERE id = %d',
                array( $this->id )
            );

            $row = $this->wpdb->get_row( $sql, ARRAY_A );

            $this->data = $row ? $this->map_booking_row_from_array( $row ) : null;
        }

        /**
         * Execute the underlying booking query.
         *
         * @param array $args        Query arguments.
         * @param bool  $allow_unbounded Allow unlimited results.
         *
         * @return array
         */
        protected function query_bookings( $args, $allow_unbounded = false ) {
            if ( ! $this->bookings_table_exists() ) {
                $repository = $this->get_fallback_repository();

                if ( $repository ) {
                    $page       = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
                    $per_page   = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 25;
                    $sort_by    = isset( $args['sort_by'] ) ? $args['sort_by'] : 'booking_datetime';
                    $sort_order = isset( $args['sort_order'] ) ? $args['sort_order'] : 'desc';

                    $filters = array(
                        'status'    => isset( $args['status'] ) ? $args['status'] : '',
                        'location'  => isset( $args['location_id'] ) ? $args['location_id'] : 0,
                        'date_from' => isset( $args['date_from'] ) ? $args['date_from'] : '',
                        'date_to'   => isset( $args['date_to'] ) ? $args['date_to'] : '',
                        'search'    => isset( $args['search'] ) ? $args['search'] : '',
                        'source'    => isset( $args['source'] ) ? $args['source'] : '',
                    );

                    $results = $repository->get_bookings( $filters, $page, $per_page, $sort_by, $sort_order );

                    return array(
                        'rows'        => isset( $results['bookings'] ) ? array_values( $results['bookings'] ) : array(),
                        'total'       => isset( $results['total_items'] ) ? (int) $results['total_items'] : 0,
                        'total_pages' => isset( $results['total_pages'] ) ? (int) $results['total_pages'] : 1,
                        'page'        => isset( $results['current_page'] ) ? (int) $results['current_page'] : $page,
                        'per_page'    => isset( $results['page_size'] ) ? (int) $results['page_size'] : $per_page,
                        'summary'     => isset( $results['summary'] ) ? $results['summary'] : $this->get_empty_summary(),
                    );
                }

                return array(
                    'rows'       => array(),
                    'total'      => 0,
                    'total_pages'=> 1,
                    'page'       => 1,
                    'per_page'   => isset( $args['per_page'] ) ? (int) $args['per_page'] : 1,
                    'summary'    => $this->get_empty_summary(),
                );
            }

            $defaults = array(
                'status'      => '',
                'location_id' => 0,
                'date_from'   => '',
                'date_to'     => '',
                'search'      => '',
                'source'      => '',
                'page'        => 1,
                'per_page'    => 25,
                'sort_by'     => 'booking_datetime',
                'sort_order'  => 'desc',
                'no_limit'    => false,
                'include_summary' => false,
            );

            $args = wp_parse_args( $args, $defaults );

            $page      = max( 1, (int) $args['page'] );
            $per_page  = max( 1, (int) $args['per_page'] );
            $no_limit  = $allow_unbounded && ! empty( $args['no_limit'] );
            $sort_by   = sanitize_key( $args['sort_by'] );
            $sort_order = strtolower( sanitize_key( $args['sort_order'] ) );
            $sort_order = 'asc' === $sort_order ? 'ASC' : 'DESC';

            $joins = array();
            $select_fields = array(
                'b.id',
                'b.status',
                'b.location_id',
                'COALESCE(b.party_size, 0) AS party_size',
                'COALESCE(b.special_requests, "") AS special_requests',
            );

            $date_expression = $this->get_date_expression();
            $time_expression = $this->get_time_expression();

            $select_fields[] = $date_expression . ' AS booking_date';
            $select_fields[] = $time_expression . ' AS booking_time';

            if ( $this->has_column( 'table_id' ) ) {
                $select_fields[] = 'b.table_id';
            } else {
                $select_fields[] = '0 AS table_id';
            }

            if ( $this->has_column( 'total_amount' ) ) {
                $select_fields[] = 'COALESCE(b.total_amount, 0) AS total_amount';
            } else {
                $select_fields[] = '0 AS total_amount';
            }

            if ( $this->has_column( 'created_at' ) ) {
                $select_fields[] = 'b.created_at';
            } else {
                $select_fields[] = 'COALESCE(b.updated_at, NOW()) AS created_at';
            }

            if ( $this->has_column( 'updated_at' ) ) {
                $select_fields[] = 'b.updated_at';
            } else {
                $select_fields[] = 'COALESCE(b.created_at, NOW()) AS updated_at';
            }

            if ( $this->has_column( 'source' ) ) {
                $select_fields[] = 'COALESCE(b.source, "") AS source';
            } else {
                $select_fields[] = '"" AS source';
            }

            // Customer fields.
            if ( $this->has_column( 'customer_name' ) ) {
                $select_fields[] = 'COALESCE(b.customer_name, "") AS customer_name';
                $select_fields[] = $this->has_column( 'customer_email' ) ? 'COALESCE(b.customer_email, "") AS customer_email' : '"" AS customer_email';
                $select_fields[] = $this->has_column( 'customer_phone' ) ? 'COALESCE(b.customer_phone, "") AS customer_phone' : '"" AS customer_phone';
            } elseif ( $this->table_exists( $this->related_tables['customers'] ) ) {
                $joins[]          = 'LEFT JOIN ' . $this->related_tables['customers'] . ' c ON c.id = b.customer_id';
                $select_fields[]  = 'COALESCE(CONCAT_WS(" ", c.first_name, c.last_name), "") AS customer_name';
                $select_fields[]  = 'COALESCE(c.email, "") AS customer_email';
                $select_fields[]  = 'COALESCE(c.phone, "") AS customer_phone';
            } else {
                $select_fields[] = '"" AS customer_name';
                $select_fields[] = '"" AS customer_email';
                $select_fields[] = '"" AS customer_phone';
            }

            // Table details.
            if ( $this->table_exists( $this->related_tables['tables'] ) ) {
                $joins[]         = 'LEFT JOIN ' . $this->related_tables['tables'] . ' t ON t.id = b.table_id';
                $select_fields[] = 'COALESCE(t.table_number, "") AS table_number';
            } else {
                $select_fields[] = '"" AS table_number';
            }

            // Location label.
            if ( $this->table_exists( $this->related_tables['locations'] ) ) {
                $joins[]         = 'LEFT JOIN ' . $this->related_tables['locations'] . ' l ON l.id = b.location_id';
                $select_fields[] = 'COALESCE(l.name, "") AS location_name';
            } else {
                $select_fields[] = '"" AS location_name';
            }

            $where  = array();
            $params = array();

            if ( ! empty( $args['status'] ) ) {
                $where[]  = 'b.status = %s';
                $params[] = sanitize_key( $args['status'] );
            }

            $location_id = absint( $args['location_id'] );
            if ( $location_id > 0 ) {
                $where[]  = 'b.location_id = %d';
                $params[] = $location_id;
            }

            if ( ! empty( $args['source'] ) && $this->has_column( 'source' ) ) {
                $where[]  = 'b.source = %s';
                $params[] = sanitize_key( $args['source'] );
            }

            if ( ! empty( $args['date_from'] ) ) {
                $where[]  = $date_expression . ' >= %s';
                $params[] = $this->normalize_date( $args['date_from'] );
            }

            if ( ! empty( $args['date_to'] ) ) {
                $where[]  = $date_expression . ' <= %s';
                $params[] = $this->normalize_date( $args['date_to'] );
            }

            if ( ! empty( $args['search'] ) ) {
                $like     = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
                $where[]  = '(b.customer_name LIKE %s OR b.customer_email LIKE %s OR b.customer_phone LIKE %s)';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }

            $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
            $join_sql  = $joins ? ' ' . implode( ' ', $joins ) : '';

            $count_sql = 'SELECT COUNT(*) FROM ' . $this->table . ' b' . $join_sql . ' ' . $where_sql;
            $count_sql = $this->prepare( $count_sql, $params );
            $total     = (int) $this->wpdb->get_var( $count_sql );

            $allowed_sort_fields = array(
                'booking_datetime' => $date_expression . ' ' . $sort_order . ', ' . $time_expression,
                'booking_date'     => $date_expression,
                'status'           => 'b.status',
                'customer_name'    => 'customer_name',
                'party_size'       => 'b.party_size',
                'table_number'     => 'table_number',
                'created_at'       => $this->has_column( 'created_at' ) ? 'b.created_at' : $date_expression,
            );

            $sort_field = isset( $allowed_sort_fields[ $sort_by ] ) ? $allowed_sort_fields[ $sort_by ] : $allowed_sort_fields['booking_datetime'];

            $select_sql = 'SELECT ' . implode( ', ', $select_fields ) . ' FROM ' . $this->table . ' b' . $join_sql . ' ' . $where_sql . ' ORDER BY ' . $sort_field . ' ' . $sort_order;

            $select_params = $params;

            if ( ! $no_limit ) {
                $offset         = ( $page - 1 ) * $per_page;
                $select_sql    .= ' LIMIT %d OFFSET %d';
                $select_params[] = $per_page;
                $select_params[] = $offset;
            }

            $select_sql = $this->prepare( $select_sql, $select_params );

            $rows = $this->wpdb->get_results( $select_sql );

            $mapped = array();

            if ( $rows ) {
                foreach ( $rows as $row ) {
                    $mapped[] = $this->map_booking_row( $row );
                }
            }

            $total_pages = max( 1, (int) ceil( $total / $per_page ) );

            $summary                    = $this->get_empty_summary();
            $summary['total_bookings']  = $total;
            $summary['revenue_total']   = 0.0;

            if ( ! empty( $args['include_summary'] ) ) {
                $status_sql = 'SELECT b.status, COUNT(*) AS status_count FROM ' . $this->table . ' b' . $join_sql . ' ' . $where_sql . ' GROUP BY b.status';
                $status_sql = $this->prepare( $status_sql, $params );
                $status_rows = $this->wpdb->get_results( $status_sql );

                if ( $status_rows ) {
                    foreach ( $status_rows as $status_row ) {
                        $status_key = isset( $status_row->status ) ? sanitize_key( $status_row->status ) : '';
                        $count      = isset( $status_row->status_count ) ? (int) $status_row->status_count : 0;

                        if ( '' === $status_key ) {
                            continue;
                        }

                        if ( ! isset( $summary['status_counts'][ $status_key ] ) ) {
                            $summary['status_counts'][ $status_key ] = 0;
                        }

                        $summary['status_counts'][ $status_key ] = $count;
                    }
                }

                if ( $this->has_column( 'source' ) ) {
                    $source_sql = 'SELECT b.source, COUNT(*) AS source_count FROM ' . $this->table . ' b' . $join_sql . ' ' . $where_sql . ' GROUP BY b.source';
                    $source_sql = $this->prepare( $source_sql, $params );
                    $source_rows = $this->wpdb->get_results( $source_sql );

                    if ( $source_rows ) {
                        foreach ( $source_rows as $source_row ) {
                            $source_key = isset( $source_row->source ) ? sanitize_key( $source_row->source ) : '';
                            $count      = isset( $source_row->source_count ) ? (int) $source_row->source_count : 0;

                            if ( '' === $source_key ) {
                                continue;
                            }

                            $summary['source_counts'][ $source_key ] = $count;
                        }
                    }
                }

                $amount_expression = $this->has_column( 'total_amount' ) ? 'COALESCE(b.total_amount, 0)' : '0';
                $party_expression  = $this->has_column( 'party_size' ) ? 'COALESCE(b.party_size, 0)' : '0';

                $summary_sql = 'SELECT SUM(' . $party_expression . ') AS total_guests, SUM(' . $amount_expression . ') AS total_revenue FROM ' . $this->table . ' b' . $join_sql . ' ' . $where_sql;
                $summary_sql = $this->prepare( $summary_sql, $params );
                $summary_row = $this->wpdb->get_row( $summary_sql, ARRAY_A );

                if ( $summary_row ) {
                    $summary['total_guests']  = isset( $summary_row['total_guests'] ) ? (int) $summary_row['total_guests'] : 0;
                    $summary['total_revenue'] = isset( $summary_row['total_revenue'] ) ? (float) $summary_row['total_revenue'] : 0.0;
                    $summary['revenue_total'] = $summary['total_revenue'];
                }

                if ( $summary['total_bookings'] > 0 ) {
                    $summary['average_party_size'] = round( $summary['total_guests'] / $summary['total_bookings'], 1 );
                }

                $summary['pending_total']    = isset( $summary['status_counts']['pending'] ) ? (int) $summary['status_counts']['pending'] : 0;
                $summary['bookings_change']  = isset( $summary['bookings_change'] ) ? (float) $summary['bookings_change'] : 0.0;
                $summary['revenue_change']   = isset( $summary['revenue_change'] ) ? (float) $summary['revenue_change'] : 0.0;
                $summary['party_size_change'] = isset( $summary['party_size_change'] ) ? (float) $summary['party_size_change'] : 0.0;
            }

            return array(
                'rows'        => $mapped,
                'total'       => $total,
                'total_pages' => $total_pages,
                'page'        => $page,
                'per_page'    => $per_page,
                'summary'     => $summary,
            );
        }

        /**
         * Convert a database row to a structured array.
         *
         * @param object $row Database row.
         *
         * @return array
         */
        protected function map_booking_row( $row ) {
            $booking_date = isset( $row->booking_date ) ? $row->booking_date : '';
            $booking_time = isset( $row->booking_time ) ? $row->booking_time : '';

            return array(
                'id'              => isset( $row->id ) ? (int) $row->id : 0,
                'status'          => isset( $row->status ) ? $row->status : 'pending',
                'location_id'     => isset( $row->location_id ) ? (int) $row->location_id : 0,
                'location_name'   => isset( $row->location_name ) ? $row->location_name : '',
                'customer_name'   => isset( $row->customer_name ) ? $row->customer_name : '',
                'customer_email'  => isset( $row->customer_email ) ? $row->customer_email : '',
                'customer_phone'  => isset( $row->customer_phone ) ? $row->customer_phone : '',
                'booking_date'    => $booking_date,
                'booking_time'    => $booking_time,
                'party_size'      => isset( $row->party_size ) ? (int) $row->party_size : 0,
                'table_id'        => isset( $row->table_id ) ? (int) $row->table_id : 0,
                'table_number'    => isset( $row->table_number ) ? $row->table_number : '',
                'special_requests'=> isset( $row->special_requests ) ? $row->special_requests : '',
                'created_at'      => isset( $row->created_at ) ? $row->created_at : $booking_date,
                'updated_at'      => isset( $row->updated_at ) ? $row->updated_at : $booking_date,
                'total_amount'    => isset( $row->total_amount ) ? (float) $row->total_amount : 0.0,
                'source'          => isset( $row->source ) ? $row->source : '',
            );
        }

        /**
         * Map array data (used by loader).
         *
         * @param array $row Raw database row.
         *
         * @return array
         */
        protected function map_booking_row_from_array( $row ) {
            $object = (object) $row;

            return $this->map_booking_row( $object );
        }

        /**
         * Provide default stats payload.
         *
         * @return array
         */
        protected function get_default_stats() {
            return array(
                'total_bookings'  => 0,
                'confirmed'       => 0,
                'pending'         => 0,
                'cancelled'       => 0,
                'total_guests'    => 0,
                'revenue'         => 0.0,
                'currency'        => apply_filters( 'rb_booking_currency', '$', 0 ),
                'occupancy_rate'  => 0.0,
                'available_tables'=> 0,
                'used_tables'     => 0,
            );
        }

        /**
         * Provide a default summary payload for admin tables.
         *
         * @return array
         */
        protected function get_empty_summary() {
            return array(
                'total_bookings'    => 0,
                'total_revenue'     => 0.0,
                'revenue_total'     => 0.0,
                'total_guests'      => 0,
                'average_party_size'=> 0.0,
                'bookings_change'   => 0.0,
                'revenue_change'    => 0.0,
                'party_size_change' => 0.0,
                'pending_total'     => 0,
                'status_counts'     => array(
                    'pending'   => 0,
                    'confirmed' => 0,
                    'completed' => 0,
                    'cancelled' => 0,
                ),
                'source_counts'     => array(),
            );
        }

        /**
         * Determine if the bookings table exists.
         *
         * @return bool
         */
        protected function bookings_table_exists() {
            return $this->table_exists( $this->table );
        }

        /**
         * Check if a table exists in the database.
         *
         * @param string $table_name Table name.
         *
         * @return bool
         */
        protected function table_exists( $table_name ) {
            $table_name = trim( $table_name );
            if ( '' === $table_name ) {
                return false;
            }

            $query = $this->prepare( 'SHOW TABLES LIKE %s', array( $table_name ) );

            $result = $this->wpdb->get_var( $query );

            return $result === $table_name;
        }

        /**
         * Load table columns lazily.
         */
        protected function load_columns() {
            if ( null !== $this->columns ) {
                return;
            }

            if ( ! $this->bookings_table_exists() ) {
                $this->columns = array();
                return;
            }

            $results = $this->wpdb->get_results( 'SHOW COLUMNS FROM ' . $this->table, ARRAY_A );
            $this->columns = array();

            if ( $results ) {
                foreach ( $results as $column ) {
                    if ( isset( $column['Field'] ) ) {
                        $this->columns[] = $column['Field'];
                    }
                }
            }
        }

        /**
         * Determine if a column exists on the bookings table.
         *
         * @param string $column Column name.
         *
         * @return bool
         */
        protected function has_column( $column ) {
            $this->load_columns();

            return in_array( $column, $this->columns, true );
        }

        /**
         * Return the SQL expression for the booking date.
         *
         * @return string
         */
        protected function get_date_expression() {
            if ( $this->has_column( 'booking_date' ) ) {
                return 'b.booking_date';
            }

            if ( $this->has_column( 'booking_datetime' ) ) {
                return 'DATE(b.booking_datetime)';
            }

            if ( $this->has_column( 'created_at' ) ) {
                return 'DATE(b.created_at)';
            }

            return 'DATE(NOW())';
        }

        /**
         * Return the SQL expression for the booking time.
         *
         * @return string
         */
        protected function get_time_expression() {
            if ( $this->has_column( 'booking_time' ) ) {
                return 'b.booking_time';
            }

            if ( $this->has_column( 'booking_datetime' ) ) {
                return 'DATE_FORMAT(b.booking_datetime, "%H:%i")';
            }

            if ( $this->has_column( 'created_at' ) ) {
                return 'DATE_FORMAT(b.created_at, "%H:%i")';
            }

            return '\'00:00\'';
        }

        /**
         * Provide column format mapping for inserts and updates.
         *
         * @return array
         */
        protected function get_column_formats() {
            return array(
                'customer_id'      => '%d',
                'location_id'      => '%d',
                'table_id'         => '%d',
                'booking_date'     => '%s',
                'booking_time'     => '%s',
                'booking_datetime' => '%s',
                'party_size'       => '%d',
                'status'           => '%s',
                'total_amount'     => '%f',
                'special_requests' => '%s',
                'customer_name'    => '%s',
                'customer_email'   => '%s',
                'customer_phone'   => '%s',
                'created_at'       => '%s',
                'updated_at'       => '%s',
                'source'           => '%s',
            );
        }

        /**
         * Prepare insert payload for database persistence.
         *
         * @param array $data Booking payload.
         *
         * @return array
         */
        protected function prepare_insert_payload( $data ) {
            $formats_map = $this->get_column_formats();
            $payload     = array();
            $formats     = array();

            if ( $this->has_column( 'customer_id' ) && isset( $formats_map['customer_id'] ) ) {
                $customer_id = 0;

                if ( class_exists( 'RB_Customer' ) && method_exists( 'RB_Customer', 'upsert_customer_from_booking' ) ) {
                    $customer_id = RB_Customer::upsert_customer_from_booking( $data );
                }

                $payload['customer_id'] = $customer_id;
                $formats[]              = $formats_map['customer_id'];
            }

            if ( $this->has_column( 'location_id' ) && isset( $formats_map['location_id'] ) ) {
                $payload['location_id'] = absint( $data['location_id'] ?? ( $data['location'] ?? 0 ) );
                $formats[]              = $formats_map['location_id'];
            }

            if ( $this->has_column( 'table_id' ) && isset( $formats_map['table_id'] ) ) {
                $payload['table_id'] = absint( $data['table_id'] ?? 0 );
                $formats[]           = $formats_map['table_id'];
            }

            if ( $this->has_column( 'booking_date' ) && isset( $formats_map['booking_date'] ) ) {
                $payload['booking_date'] = $this->normalize_date( $data['date'] ?? ( $data['booking_date'] ?? '' ) );
                $formats[]               = $formats_map['booking_date'];
            }

            if ( $this->has_column( 'booking_time' ) && isset( $formats_map['booking_time'] ) ) {
                $payload['booking_time'] = $this->normalize_time( $data['time'] ?? ( $data['booking_time'] ?? '' ) );
                $formats[]               = $formats_map['booking_time'];
            }

            if ( $this->has_column( 'booking_datetime' ) && isset( $formats_map['booking_datetime'] ) ) {
                $date = isset( $payload['booking_date'] ) ? $payload['booking_date'] : $this->normalize_date( $data['date'] ?? '' );
                $time = $this->normalize_time( $data['time'] ?? ( $data['booking_time'] ?? '' ) );

                $payload['booking_datetime'] = gmdate( 'Y-m-d H:i:s', strtotime( $date . ' ' . $time ) );
                $formats[]                   = $formats_map['booking_datetime'];
            }

            if ( $this->has_column( 'party_size' ) && isset( $formats_map['party_size'] ) ) {
                $payload['party_size'] = max( 1, (int) ( $data['party_size'] ?? ( $data['guests'] ?? 0 ) ) );
                $formats[]             = $formats_map['party_size'];
            }

            if ( $this->has_column( 'status' ) && isset( $formats_map['status'] ) ) {
                $payload['status'] = sanitize_key( $data['status'] ?? 'pending' );
                $formats[]         = $formats_map['status'];
            }

            if ( $this->has_column( 'source' ) && isset( $formats_map['source'] ) ) {
                $source = isset( $data['source'] ) ? sanitize_key( $data['source'] ) : '';
                if ( '' === $source ) {
                    $source = 'online';
                }
                $payload['source'] = $source;
                $formats[]         = $formats_map['source'];
            }

            if ( $this->has_column( 'total_amount' ) && isset( $formats_map['total_amount'] ) ) {
                $payload['total_amount'] = isset( $data['total_amount'] ) ? (float) $data['total_amount'] : 0.0;
                $formats[]               = $formats_map['total_amount'];
            }

            if ( $this->has_column( 'special_requests' ) && isset( $formats_map['special_requests'] ) ) {
                $payload['special_requests'] = isset( $data['special_requests'] ) ? wp_kses_post( $data['special_requests'] ) : '';
                $formats[]                   = $formats_map['special_requests'];
            }

            $first_name = isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : '';
            $last_name  = isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : '';
            $full_name  = isset( $data['customer_name'] ) ? sanitize_text_field( $data['customer_name'] ) : trim( $first_name . ' ' . $last_name );

            if ( '' === $full_name ) {
                $full_name = __( 'Guest', 'restaurant-booking' );
            }

            if ( $this->has_column( 'customer_name' ) && isset( $formats_map['customer_name'] ) ) {
                $payload['customer_name'] = $full_name;
                $formats[]                = $formats_map['customer_name'];
            }

            if ( $this->has_column( 'customer_email' ) && isset( $formats_map['customer_email'] ) ) {
                $payload['customer_email'] = sanitize_email( $data['email'] ?? ( $data['customer_email'] ?? '' ) );
                $formats[]                 = $formats_map['customer_email'];
            }

            if ( $this->has_column( 'customer_phone' ) && isset( $formats_map['customer_phone'] ) ) {
                $payload['customer_phone'] = sanitize_text_field( $data['phone'] ?? ( $data['customer_phone'] ?? '' ) );
                $formats[]                 = $formats_map['customer_phone'];
            }

            $timestamp = current_time( 'mysql', true );

            if ( $this->has_column( 'created_at' ) && isset( $formats_map['created_at'] ) ) {
                $payload['created_at'] = $timestamp;
                $formats[]             = $formats_map['created_at'];
            }

            if ( $this->has_column( 'updated_at' ) && isset( $formats_map['updated_at'] ) ) {
                $payload['updated_at'] = $timestamp;
                $formats[]             = $formats_map['updated_at'];
            }

            return array( $payload, $formats );
        }

        /**
         * Prepare payload for fallback repository usage.
         *
         * @param array $data Booking payload.
         *
         * @return array
         */
        protected function prepare_fallback_booking_payload( $data ) {
            $first_name = isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : '';
            $last_name  = isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : '';
            $full_name  = isset( $data['customer_name'] ) ? sanitize_text_field( $data['customer_name'] ) : trim( $first_name . ' ' . $last_name );

            if ( '' === $first_name && '' === $last_name && '' !== $full_name ) {
                $parts      = preg_split( '/\s+/', $full_name, 2 );
                $first_name = $parts[0] ?? '';
                $last_name  = $parts[1] ?? '';
            }

            return array(
                'first_name'        => $first_name,
                'last_name'         => $last_name,
                'email'             => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : ( isset( $data['customer_email'] ) ? sanitize_email( $data['customer_email'] ) : '' ),
                'phone'             => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : ( isset( $data['customer_phone'] ) ? sanitize_text_field( $data['customer_phone'] ) : '' ),
                'special_requests'  => isset( $data['special_requests'] ) ? wp_kses_post( $data['special_requests'] ) : '',
                'location_id'       => isset( $data['location_id'] ) ? absint( $data['location_id'] ) : ( isset( $data['location'] ) ? absint( $data['location'] ) : 0 ),
                'party_size'        => isset( $data['party_size'] ) ? (int) $data['party_size'] : ( isset( $data['guests'] ) ? (int) $data['guests'] : 0 ),
                'date'              => isset( $data['date'] ) ? sanitize_text_field( $data['date'] ) : ( isset( $data['booking_date'] ) ? sanitize_text_field( $data['booking_date'] ) : '' ),
                'time'              => isset( $data['time'] ) ? sanitize_text_field( $data['time'] ) : ( isset( $data['booking_time'] ) ? sanitize_text_field( $data['booking_time'] ) : '' ),
                'source'            => sanitize_key( $data['source'] ?? 'fallback' ),
            );
        }

        /**
         * Prepare an SQL query safely.
         *
         * @param string $sql    SQL statement.
         * @param array  $params Parameters.
         *
         * @return string
         */
        protected function prepare( $sql, $params = array() ) {
            if ( empty( $params ) ) {
                return $sql;
            }

            return $this->wpdb->prepare( $sql, $params );
        }

        /**
         * Normalise date input.
         *
         * @param string $date Date string.
         *
         * @return string
         */
        protected function normalize_time( $time ) {
            $time = trim( (string) $time );

            if ( '' === $time ) {
                return '00:00:00';
            }

            if ( preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $time, $matches ) ) {
                $hours   = str_pad( $matches[1], 2, '0', STR_PAD_LEFT );
                $minutes = str_pad( $matches[2], 2, '0', STR_PAD_LEFT );
                $seconds = isset( $matches[3] ) && '' !== $matches[3] ? str_pad( $matches[3], 2, '0', STR_PAD_LEFT ) : '00';

                return $hours . ':' . $minutes . ':' . $seconds;
            }

            $timestamp = strtotime( $time );

            if ( false === $timestamp ) {
                return '00:00:00';
            }

            return gmdate( 'H:i:s', $timestamp );
        }

        protected function normalize_date( $date ) {
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
         * Retrieve the notification service instance.
         *
         * @return RB_Notification_Service|null
         */
        protected function get_notification_service() {
            if ( $this->notification_service instanceof RB_Notification_Service ) {
                return $this->notification_service;
            }

            if ( class_exists( 'RB_Notification_Service' ) ) {
                $this->notification_service = new RB_Notification_Service();

                return $this->notification_service;
            }

            return null;
        }

        /**
         * Retrieve the fallback booking repository when database tables are missing.
         *
         * @return RB_Fallback_Booking_Repository|null
         */
        protected function get_fallback_repository() {
            if ( class_exists( 'RB_Fallback_Booking_Repository' ) ) {
                return RB_Fallback_Booking_Repository::instance();
            }

            return null;
        }
    }
}
